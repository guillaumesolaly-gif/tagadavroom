<?php
/**
 * Vérifie la couche métier de la sélection de plusieurs chevaux (includes/cheval-selection.php,
 * Suite V1 « Partager & vendre », Lot 2A) : persistance (CPT + une seule meta pour la liste
 * ordonnée), token (générer/activer/régénérer/révoquer/URL/recherche inverse), règle d'éligibilité
 * (réutilisation EXCLUSIVE de gwseq_horse_diffusion_state(), jamais un recalcul), résolution pour
 * affichage (référence toujours actuelle, jamais une copie figée — §6 de la demande), et création
 * (§7). Même méthodologie que le reste de cette suite : fonctions pures exercées avec des données
 * réalistes, réutilisant les VRAIS helpers du module (jamais une réimplémentation dans ce fichier).
 *
 * Ne fait pas partie des paquets livrés (gws-core.zip / gws-starter.zip).
 */

$failures = 0;
function gws_test_assert($condition, $label) {
  global $failures;
  if ($condition) { echo "OK   - $label\n"; }
  else { echo "FAIL - $label\n"; $failures++; }
}

// --- Stubs WordPress minimaux (mêmes conventions que gws-equestrian-cheval-share-logic-test.php,
// dont ce fichier réutilise la même approche de registre de posts) ---
function wp_unslash($value) { return is_array($value) ? array_map('wp_unslash', $value) : (is_string($value) ? stripslashes($value) : $value); }
// Fidèle au comportement réel de WordPress (wp-includes/formatting.php) : un tableau/objet renvoie
// immédiatement une chaîne vide, jamais une conversion PHP hasardeuse (§19 : "données malformées").
function sanitize_text_field($value) { if (is_array($value) || is_object($value)) return ''; return trim(strip_tags((string) $value)); }
function sanitize_textarea_field($value) { if (is_array($value) || is_object($value)) return ''; return trim(strip_tags((string) $value)); }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value)); }
function esc_url_raw($value) { $value = trim((string) $value); return $value === '' ? '' : $value; }
function esc_url($value) { return $value; }
function absint($value) { return abs((int) $value); }
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function wp_parse_args($args, $defaults = array()) { return array_merge((array) $defaults, (array) $args); }
function __($text, $domain = 'default') { return $text; }
function esc_html__($text, $domain = 'default') { return esc_html($text); }
function esc_html_e($text, $domain = 'default') { echo esc_html__($text, $domain); }
function home_url($path = '') { return 'https://example.test' . $path; }
function get_permalink($post_id) { return 'https://example.test/chevaux/cheval-' . (int) $post_id . '/'; }
function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {}
function is_singular($post_type = '') { return false; }
function get_queried_object_id() { return 0; }
function get_query_var($var, $default = '') { return $default; }

class WP_Error {
  public $code; public $message;
  public function __construct($code = '', $message = '') { $this->code = $code; $this->message = $message; }
}
function is_wp_error($thing) { return $thing instanceof WP_Error; }

$GLOBALS['__gwseq_test_meta'] = array();
function update_post_meta($post_id, $key, $value) { $GLOBALS['__gwseq_test_meta'][$post_id][$key] = wp_unslash($value); return true; }
function get_post_meta($post_id, $key, $single = false) { return $GLOBALS['__gwseq_test_meta'][$post_id][$key] ?? ''; }
function delete_post_meta($post_id, $key) { unset($GLOBALS['__gwseq_test_meta'][$post_id][$key]); return true; }

// --- Registre de posts (chevaux ET sélections, même registre — comme dans WordPress réel où
// tous les post types partagent la table wp_posts) ---
$GLOBALS['__gwseq_test_posts'] = array();
function gws_test_make_post($id, $post_type, $title, $status = 'publish', $password = '', $author = 1) {
  $GLOBALS['__gwseq_test_posts'][$id] = array('post_type' => $post_type, 'post_status' => $status, 'post_title' => $title, 'post_password' => $password, 'post_author' => $author);
}
function gws_test_make_post_object($id) {
  $p = $GLOBALS['__gwseq_test_posts'][$id];
  return (object) array_merge(array('ID' => $id), $p);
}
function get_post_type($post_id) { return $GLOBALS['__gwseq_test_posts'][$post_id]['post_type'] ?? false; }
function get_post($post_id) { return isset($GLOBALS['__gwseq_test_posts'][$post_id]) ? gws_test_make_post_object($post_id) : null; }
function get_the_title($post) {
  $id = is_object($post) ? $post->ID : $post;
  return $GLOBALS['__gwseq_test_posts'][$id]['post_title'] ?? '';
}
function wp_update_post($postarr, $wp_error = false) {
  $id = (int) ($postarr['ID'] ?? 0);
  if (!$id || !isset($GLOBALS['__gwseq_test_posts'][$id])) return 0;
  foreach (array('post_status', 'post_password') as $field) {
    if (array_key_exists($field, $postarr)) $GLOBALS['__gwseq_test_posts'][$id][$field] = $postarr[$field];
  }
  return $id;
}

$GLOBALS['__gwseq_test_next_post_id'] = 1000;
function wp_insert_post($postarr, $wp_error = false) {
  $id = $GLOBALS['__gwseq_test_next_post_id']++;
  gws_test_make_post(
    $id,
    $postarr['post_type'],
    $postarr['post_title'] ?? '',
    $postarr['post_status'] ?? 'draft',
    '',
    $postarr['post_author'] ?? 1
  );
  return $id;
}

// --- WP_Query minimal, suffisant pour gwseq_selection_find_by_token() : post_type/post_status
// ('publish' strict ou 'any' = tout sauf corbeille)/meta_query (correspondance EXACTE d'une seule
// clause) --- même principe que gws-equestrian-cheval-share-logic-test.php.
class WP_Query {
  public $posts = array();
  public function __construct($args = array()) {
    $post_type = $args['post_type'] ?? 'post';
    $status_filter = $args['post_status'] ?? 'publish';
    $meta_query = $args['meta_query'] ?? array();
    $limit = $args['posts_per_page'] ?? -1;
    $results = array();
    foreach ($GLOBALS['__gwseq_test_posts'] as $id => $post) {
      if ($post['post_type'] !== $post_type) continue;
      if ($status_filter === 'any') {
        if ($post['post_status'] === 'trash') continue;
      } elseif ($post['post_status'] !== $status_filter) {
        continue;
      }
      $match = true;
      foreach ($meta_query as $clause) {
        if (!isset($clause['key'])) continue;
        $value = get_post_meta($id, $clause['key'], true);
        if ((string) $value !== (string) ($clause['value'] ?? '')) { $match = false; break; }
      }
      if (!$match) continue;
      $results[] = $id;
    }
    if ($limit > 0) $results = array_slice($results, 0, $limit);
    $this->posts = $results;
  }
}

define('ABSPATH', __DIR__ . '/');
const GWSEQ_CPT_CHEVAL = 'gwseq_cheval';
const GWSEQ_CPT_SELECTION = 'gwseq_selection';

$repo_root = dirname(__DIR__);
$module_dir = $repo_root . '/wp-content/plugins/gws-core/modules/gws-equestrian/';
require $repo_root . '/wp-content/plugins/gws-core/includes/fields.php';
require $module_dir . 'includes/cheval-share.php';
require $module_dir . 'includes/cheval-selection.php';

// --- Aides de test : constituer un cheval dans un état de diffusion donné, sans jamais recalculer
// la règle nous-mêmes (on pilote seulement post_status/post_password/le token — exactement les
// deux mécanismes natifs que gwseq_horse_diffusion_state() lit déjà). ---
function gws_test_make_horse($id, $title, $state, $author = 1) {
  if ($state === GWSEQ_HORSE_DIFFUSION_VISIBLE_SITE) {
    gws_test_make_post($id, GWSEQ_CPT_CHEVAL, $title, 'publish', '', $author);
  } elseif ($state === GWSEQ_HORSE_DIFFUSION_PRIVEE) {
    gws_test_make_post($id, GWSEQ_CPT_CHEVAL, $title, 'draft', '', $author);
    gwseq_horse_private_share_activate($id);
  } else {
    gws_test_make_post($id, GWSEQ_CPT_CHEVAL, $title, 'draft', '', $author);
  }
}

// =====================================================================================
// Token — §4 de la demande : même architecture que le partage privé Cheval.
// =====================================================================================

gws_test_make_post(1, GWSEQ_CPT_SELECTION, 'Ma sélection');

gws_test_assert(gwseq_selection_token(1) === '', 'Token : absent par défaut');
gws_test_assert(gwseq_selection_is_active(1) === false, 'Token : "actif" faux tant qu\'aucun token n\'existe');
gws_test_assert(gwseq_selection_url(1) === '', 'URL : vide tant qu\'aucun token n\'existe');

$token_regex = '/^[a-f0-9]{64}$/';
$token1 = gwseq_selection_activate(1);
gws_test_assert(preg_match($token_regex, $token1) === 1, 'Token : 64 hexadécimaux (32 octets aléatoires, même format que le partage privé Cheval)');
gws_test_assert(gwseq_selection_token(1) === $token1, 'Token : relu identique après activation');
gws_test_assert(gwseq_selection_is_active(1) === true, 'Token : "actif" vrai une fois activé');
gws_test_assert(gwseq_selection_url(1) === 'https://example.test/selection/' . $token1 . '/', 'URL : construite à partir du token, chemin "/selection/{token}/"');

$found_id = gwseq_selection_find_by_token($token1);
gws_test_assert($found_id === 1, 'Recherche inverse : le token actif résout vers la bonne sélection');
gws_test_assert(gwseq_selection_find_by_token('') === 0, 'Recherche inverse : chaîne vide rejetée sans requête');
gws_test_assert(gwseq_selection_find_by_token('pas-un-token-valide') === 0, 'Recherche inverse : format invalide rejeté avant toute requête');
gws_test_assert(gwseq_selection_find_by_token(str_repeat('f', 64)) === 0, 'Recherche inverse : format valide mais token inconnu -> aucune sélection');

// Régénération : nouveau token, ancien immédiatement invalidé (une seule opération, comme le
// partage privé Cheval).
$token1_old = $token1;
$token1_new = gwseq_selection_activate(1);
gws_test_assert($token1_new !== $token1_old, 'Régénération : produit un token différent du précédent');
gws_test_assert(gwseq_selection_find_by_token($token1_old) === 0, 'Régénération : l\'ANCIEN token ne résout plus rien');
gws_test_assert(gwseq_selection_find_by_token($token1_new) === 1, 'Régénération : le NOUVEAU token résout vers la même sélection');

// Révocation non destructive (§13 de la demande : "je préfère une révocation non destructive").
gwseq_selection_revoke(1);
gws_test_assert(gwseq_selection_is_active(1) === false, 'Révocation : plus aucun token actif');
gws_test_assert(gwseq_selection_url(1) === '', 'Révocation : URL vide après révocation');
gws_test_assert(gwseq_selection_find_by_token($token1_new) === 0, 'Révocation : l\'ancien lien envoyé ne fonctionne plus');
gws_test_assert(get_post(1) !== null, 'Révocation NON DESTRUCTIVE : le post de la sélection existe toujours');
gws_test_assert(get_the_title(1) === 'Ma sélection', 'Révocation NON DESTRUCTIVE : le titre est conservé');

// Une sélection en corbeille ne doit jamais résoudre par token, même avec un token techniquement
// toujours présent en meta (défense en profondeur, §17 : "comportement d'une sélection... corbeille").
gws_test_make_post(2, GWSEQ_CPT_SELECTION, 'Sélection corbeille');
$token2 = gwseq_selection_activate(2);
gws_test_assert(gwseq_selection_find_by_token($token2) === 2, 'Recherche inverse : token actif d\'une sélection publiée résout normalement');
$GLOBALS['__gwseq_test_posts'][2]['post_status'] = 'trash';
gws_test_assert(gwseq_selection_find_by_token($token2) === 0, 'Recherche inverse : une sélection en corbeille ne résout plus, même avec un token techniquement présent');

// Vérifie précisément la STRICTESSE "publish" (et non un simple "any" qui exclurait seulement la
// corbeille) : un post de ce CPT dans un statut ni publié ni en corbeille (cas qu'aucune fonction
// de ce module ne produit aujourd'hui, gwseq_selection_create() créant toujours en `publish` —
// défense en profondeur pour un futur statut éventuel) ne doit pas non plus résoudre.
gws_test_make_post(3, GWSEQ_CPT_SELECTION, 'Sélection statut inattendu', 'draft');
$token3 = gwseq_selection_activate(3);
gws_test_assert(gwseq_selection_find_by_token($token3) === 0, 'Recherche inverse : strictement "publish" — un statut ni publié ni en corbeille ne résout pas non plus (défense en profondeur)');

// =====================================================================================
// Sanitation de la liste de chevaux (§17 : "validation des IDs chevaux", §19 : "données
// malformées") — dédoublonnage en conservant la position de la PREMIÈRE occurrence.
// =====================================================================================

gws_test_assert(gwseq_selection_sanitize_cheval_ids(array(5, 3, 5, '7', 0, -1, 'abc', 3)) === array(5, 3, 7), 'Sanitation IDs : entiers positifs uniquement, dédoublonnés, ordre de première occurrence conservé');
gws_test_assert(gwseq_selection_sanitize_cheval_ids('pas-un-tableau') === array(), 'Sanitation IDs : une valeur non-tableau ne fait jamais planter, résultat vide');
gws_test_assert(gwseq_selection_sanitize_cheval_ids(array()) === array(), 'Sanitation IDs : tableau vide -> liste vide');

// =====================================================================================
// Éligibilité (§5, cœur architectural du lot) — réutilise EXCLUSIVEMENT
// gwseq_horse_diffusion_state(), jamais un recalcul.
// =====================================================================================

gws_test_make_horse(10, 'Visible', GWSEQ_HORSE_DIFFUSION_VISIBLE_SITE);
gws_test_make_horse(11, 'Privee', GWSEQ_HORSE_DIFFUSION_PRIVEE);
gws_test_make_horse(12, 'Preparation', GWSEQ_HORSE_DIFFUSION_EN_PREPARATION);

gws_test_assert(gwseq_selection_horse_is_eligible(10) === true, 'Éligibilité : "Visible sur le site" éligible');
gws_test_assert(gwseq_selection_horse_is_eligible(11) === true, 'Éligibilité : "Diffusion privée" éligible');
gws_test_assert(gwseq_selection_horse_is_eligible(12) === false, 'Éligibilité : "En préparation" JAMAIS éligible (§5)');
gws_test_assert(gwseq_selection_horse_is_eligible(999) === false, 'Éligibilité : cheval inexistant -> non éligible, jamais une erreur');
gws_test_assert(gwseq_selection_horse_is_eligible(0) === false, 'Éligibilité : ID nul/négatif -> non éligible sans requête');

gws_test_make_post(13, 'page', 'Une page WordPress ordinaire');
gws_test_assert(gwseq_selection_horse_is_eligible(13) === false, 'Éligibilité : un post d\'un AUTRE type de contenu -> jamais éligible (§17 : "appartenance au bon CPT")');

gws_test_make_horse(14, 'Cheval en corbeille', GWSEQ_HORSE_DIFFUSION_VISIBLE_SITE);
$GLOBALS['__gwseq_test_posts'][14]['post_status'] = 'trash';
gws_test_assert(gwseq_selection_horse_is_eligible(14) === false, 'Éligibilité : un cheval en corbeille -> jamais éligible, même publié juste avant (§19)');

$filtered = gwseq_selection_filter_eligible_cheval_ids(array(10, 12, 11, 999, 10, 13));
gws_test_assert($filtered === array(10, 11), 'Filtre d\'éligibilité : seuls les chevaux réellement éligibles et uniques sont conservés, ordre préservé');

// =====================================================================================
// Résolution pour affichage (§6/§13) — RELIT toujours l'état actuel, ne modifie JAMAIS la liste
// stockée, quel que soit ce qu'elle trouve.
// =====================================================================================

$resolved_visible = gwseq_selection_resolve_cheval(10);
gws_test_assert($resolved_visible['exists'] === true && $resolved_visible['displayable'] === true, 'Résolution : cheval "Visible sur le site" -> présentable');
gws_test_assert($resolved_visible['diffusion_state'] === GWSEQ_HORSE_DIFFUSION_VISIBLE_SITE, 'Résolution : état de diffusion reflété fidèlement');
gws_test_assert(strpos($resolved_visible['fiche_url'], 'chevaux/cheval-10') !== false, 'Résolution : lien de fiche PUBLIC pour un cheval visible sur le site');

$resolved_privee = gwseq_selection_resolve_cheval(11);
gws_test_assert($resolved_privee['displayable'] === true, 'Résolution : cheval "Diffusion privée" -> présentable');
gws_test_assert(strpos($resolved_privee['fiche_url'], '/partage/') !== false, 'Résolution : lien de fiche PRIVÉ pour un cheval en diffusion privée (réutilise gwseq_horse_share_fiche_url(), jamais un second calcul)');

$resolved_preparation = gwseq_selection_resolve_cheval(12);
gws_test_assert($resolved_preparation['exists'] === true && $resolved_preparation['displayable'] === false, 'Résolution : cheval "En préparation" -> jamais présentable');
gws_test_assert($resolved_preparation['fiche_url'] === '', 'Résolution : aucun lien de fiche pour un cheval non présentable, jamais une URL inventée');

$resolved_missing = gwseq_selection_resolve_cheval(999999);
gws_test_assert($resolved_missing['exists'] === false && $resolved_missing['displayable'] === false, 'Résolution : cheval inexistant (supprimé) -> non présentable, sans erreur fatale (§19)');

$resolved_trashed = gwseq_selection_resolve_cheval(14);
gws_test_assert($resolved_trashed['exists'] === false && $resolved_trashed['displayable'] === false, 'Résolution : cheval en corbeille -> non présentable (§19)');

// Changement d'état APRÈS résolution initiale (§6/§19 : "changement ultérieur de diffusion") —
// le cheval 11 passe de "Diffusion privée" à "Visible sur le site" : son lien devient PUBLIC,
// sans jamais avoir besoin de recréer quoi que ce soit.
gwseq_horse_diffusion_set_visible_site(11);
$resolved_11_now_public = gwseq_selection_resolve_cheval(11);
gws_test_assert($resolved_11_now_public['diffusion_state'] === GWSEQ_HORSE_DIFFUSION_VISIBLE_SITE, 'Changement de diffusion : cheval passé de "Diffusion privée" à "Visible sur le site" -> état reflété immédiatement');
gws_test_assert(strpos($resolved_11_now_public['fiche_url'], '/partage/') === false, 'Changement de diffusion : le lien devient PUBLIC (plus jamais l\'ancien lien privé), calculé à la volée');

// Le cheval 10 repasse "En préparation" (retiré de la diffusion après envoi d'une sélection) :
// non "cassé", simplement absent/non accessible au rendu (§6 : "ne pas casser toute la sélection").
gwseq_horse_diffusion_set_en_preparation(10);
$resolved_10_now_prep = gwseq_selection_resolve_cheval(10);
gws_test_assert($resolved_10_now_prep['displayable'] === false, 'Changement de diffusion : cheval repassé "En préparation" -> devient non présentable, sans jamais retirer son ID de la liste stockée (voir plus bas)');

// =====================================================================================
// Titre (§3) — jamais une donnée inventée STOCKÉE, seulement calculée à l'affichage.
// =====================================================================================

gws_test_make_post(20, GWSEQ_CPT_SELECTION, '');
gws_test_assert(gwseq_selection_display_title(20) === 'Sélection de chevaux', 'Titre : libellé neutre de repli quand aucun titre n\'a été saisi');
gws_test_assert(get_the_title(20) === '', 'Titre : le libellé de repli n\'est JAMAIS écrit en base (aucune invention stockée)');

gws_test_make_post(21, GWSEQ_CPT_SELECTION, 'Chevaux pour Guillaume');
gws_test_assert(gwseq_selection_display_title(21) === 'Chevaux pour Guillaume', 'Titre : titre personnalisé restitué tel quel');

// =====================================================================================
// Création (§7) — point d'entrée unique, réutilisable par un futur écran mobile (§16).
// =====================================================================================

// Chevaux frais pour ce bloc (identifiants distincts des précédents, dont certains ont changé
// d'état plus haut).
gws_test_make_horse(30, 'Premier choix', GWSEQ_HORSE_DIFFUSION_VISIBLE_SITE);
gws_test_make_horse(31, 'Deuxième choix', GWSEQ_HORSE_DIFFUSION_PRIVEE);
gws_test_make_horse(32, 'Non éligible', GWSEQ_HORSE_DIFFUSION_EN_PREPARATION);

$selection_id = gwseq_selection_create(array(
  'title' => 'Chevaux pour Guillaume',
  'cheval_ids' => array(31, 30, 32, 30), // ordre volontairement non trié + doublon + un inéligible
  'author' => 7,
));

gws_test_assert($selection_id > 0, 'Création : renvoie un identifiant de post valide');
gws_test_assert(get_post_type($selection_id) === GWSEQ_CPT_SELECTION, 'Création : post créé avec le bon type de contenu');
gws_test_assert(get_the_title($selection_id) === 'Chevaux pour Guillaume', 'Création : titre enregistré tel quel');
gws_test_assert((int) get_post($selection_id)->post_author === 7, 'Création : auteur enregistré (préparation de la restriction "mes propres sélections")');
gws_test_assert(gwseq_selection_get_cheval_ids($selection_id) === array(31, 30), 'Création : ordre PRÉSERVÉ (31 avant 30, tel que soumis), dédoublonné, le cheval "En préparation" (32) automatiquement exclu (défense en profondeur, §5)');
gws_test_assert(gwseq_selection_is_active($selection_id) === true, 'Création : token actif IMMÉDIATEMENT (§3 : propriété minimale toujours présente, contrairement au partage privé Cheval qui s\'active sur demande)');
gws_test_assert(gwseq_selection_url($selection_id) !== '', 'Création : URL exploitable dès la création');

// 1 seul cheval (§19) — un cas parfaitement valide.
$selection_one = gwseq_selection_create(array('title' => '', 'cheval_ids' => array(30)));
gws_test_assert(gwseq_selection_get_cheval_ids($selection_one) === array(30), 'Création : 1 seul cheval accepté (aucune limite basse artificielle)');

// Aucune limite haute arbitraire (§7 : "ne pas imposer arbitrairement une limite de 3 ou 5
// chevaux") — une sélection de nombreux chevaux reste acceptée telle quelle.
$many_ids = range(100, 149);
foreach ($many_ids as $id) { gws_test_make_horse($id, 'Cheval ' . $id, GWSEQ_HORSE_DIFFUSION_VISIBLE_SITE); }
$selection_many = gwseq_selection_create(array('cheval_ids' => $many_ids));
gws_test_assert(gwseq_selection_get_cheval_ids($selection_many) === $many_ids, 'Création : aucune limite haute arbitraire, un grand nombre de chevaux reste accepté et ordonné tel quel (§7)');

// Données malformées (§19) — un titre non-chaîne, une liste de chevaux non-tableau : jamais
// d'erreur fatale, un résultat toujours cohérent.
$selection_malformed = gwseq_selection_create(array('title' => array('pas' => 'une chaîne'), 'cheval_ids' => 'pas-un-tableau'));
gws_test_assert($selection_malformed === 0 || $selection_malformed > 0, 'Création : entrée malformée -> jamais d\'erreur fatale');
if ($selection_malformed > 0) {
  gws_test_assert(gwseq_selection_get_cheval_ids($selection_malformed) === array(), 'Création : liste de chevaux malformée -> liste vide, jamais une valeur inventée');
}

// Sélection sans aucun cheval diffusable (§19 : "sélection ne contenant plus aucun cheval
// diffusable") — reste un objet valide, jamais supprimé/cassé automatiquement.
$selection_now_empty = $selection_id; // 31 et 30 vont tous deux repasser "En préparation"
gwseq_horse_diffusion_set_en_preparation(30);
gwseq_horse_diffusion_set_en_preparation(31);
gws_test_assert(gwseq_selection_diffusable_count($selection_now_empty) === 0, 'Comptage : 0 cheval diffusable une fois que tous les chevaux référencés sont repassés "En préparation"');
gws_test_assert(gwseq_selection_get_cheval_ids($selection_now_empty) === array(31, 30), 'Comptage : la liste stockée reste pourtant INTACTE (jamais cassée), voir gwseq_selection_resolve_chevaux() pour le détail par cheval');
$resolved_all = gwseq_selection_resolve_chevaux($selection_now_empty);
gws_test_assert(count($resolved_all) === 2 && !$resolved_all[0]['displayable'] && !$resolved_all[1]['displayable'], 'Comptage : chaque cheval individuellement résolu comme non présentable, aucune exception levée');
gws_test_assert(get_post($selection_now_empty) !== null, 'Comptage : la sélection elle-même continue d\'exister normalement');

echo "\n";
if ($failures > 0) {
  echo "$failures test(s) en échec.\n";
  exit(1);
}
echo "Tous les tests sont passés.\n";

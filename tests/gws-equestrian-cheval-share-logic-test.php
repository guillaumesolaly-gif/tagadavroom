<?php
/**
 * Vérifie la couche métier du partage commercial d'un cheval (includes/cheval-share.php) :
 * l'Accroche commerciale (nouveau champ, includes/cheval-editorial.php), la détermination des
 * informations partageables (gwseq_get_horse_shareable_data() — identité, origines, taille/indice,
 * règle statut/prix, vidéos, lien de fiche publique), la composition du message
 * (gwseq_build_horse_share_message()), et les métadonnées Open Graph de la fiche Cheval. Même
 * méthodologie que le reste de cette suite : fonctions pures exercées avec des données réalistes,
 * réutilisant les VRAIS helpers du module (jamais une réimplémentation dans ce fichier).
 *
 * Ne fait pas partie des paquets livrés (gws-core.zip / gws-starter.zip).
 */

$failures = 0;
function gws_test_assert($condition, $label) {
  global $failures;
  if ($condition) { echo "OK   - $label\n"; }
  else { echo "FAIL - $label\n"; $failures++; }
}

// --- Stubs WordPress minimaux (mêmes conventions que le reste de cette suite, notamment
// gws-equestrian-pedigree-logic-test.php dont ce fichier réutilise le pedigree resolver réel) ---
function wp_unslash($value) { return is_array($value) ? array_map('wp_unslash', $value) : (is_string($value) ? stripslashes($value) : $value); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function sanitize_textarea_field($value) {
  $value = (string) $value;
  $value = preg_replace('@<(script|style)[^>]*?>.*?</\1>@si', '', $value);
  return trim(strip_tags($value));
}
function wp_parse_url($url, $component = -1) { return parse_url($url, $component); }
function wp_parse_args($args, $defaults = array()) { return array_merge((array) $defaults, (array) $args); }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value)); }
function esc_url_raw($value) { $value = trim((string) $value); return $value === '' ? '' : $value; }
function esc_url($value) { return $value; }
function absint($value) { return abs((int) $value); }
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function esc_textarea($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function selected($a, $b, $echo = true) { $r = $a == $b ? " selected='selected'" : ''; if ($echo) echo $r; return $r; }
function checked($a, $b = true, $echo = true) { $r = $a == $b ? " checked='checked'" : ''; if ($echo) echo $r; return $r; }
function wp_nonce_field($action, $field) { echo '<input type="hidden" name="' . esc_attr($field) . '" value="stub-nonce">'; }
function wp_json_encode($data, $options = 0, $depth = 512) { return json_encode($data, $options, $depth); }
function remove_accents($text) { return strtr((string) $text, array('é' => 'e', 'è' => 'e', 'ê' => 'e', 'à' => 'a', 'ô' => 'o', 'î' => 'i', 'ç' => 'c', 'É' => 'E')); }
$GLOBALS['__gwseq_test_domains_used'] = array();
function __($text, $domain = 'default') { return $text; }
function _n($single, $plural, $number, $domain = 'default') { return $number == 1 ? $single : $plural; }
function esc_html__($text, $domain = 'default') { return esc_html($text); }
function esc_attr__($text, $domain = 'default') { return esc_attr($text); }
function esc_html_e($text, $domain = 'default') { echo esc_html__($text, $domain); }

$GLOBALS['__gwseq_test_registered_meta'] = array();
function register_post_meta($object_type, $meta_key, $args = array()) { $GLOBALS['__gwseq_test_registered_meta'][$meta_key] = $args; }
function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {}
function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {}
function add_meta_box($id, $title, $callback, $post_type = null, $context = 'advanced', $priority = 'default') {}

$GLOBALS['__gwseq_test_meta'] = array();
function update_post_meta($post_id, $key, $value) { $GLOBALS['__gwseq_test_meta'][$post_id][$key] = wp_unslash($value); return true; }
function get_post_meta($post_id, $key, $single = false) { return $GLOBALS['__gwseq_test_meta'][$post_id][$key] ?? ''; }
function delete_post_meta($post_id, $key) { unset($GLOBALS['__gwseq_test_meta'][$post_id][$key]); return true; }
function metadata_exists($type, $post_id, $key) {
  return array_key_exists($post_id, $GLOBALS['__gwseq_test_meta']) && array_key_exists($key, $GLOBALS['__gwseq_test_meta'][$post_id]);
}

// --- Registre de posts, avec post_password (nécessaire pour la règle de visibilité publique du
// partage — absent des autres fichiers de cette suite, qui n'en avaient jamais eu besoin) ---
$GLOBALS['__gwseq_test_posts'] = array();
function gws_test_make_post($id, $post_type, $title, $status = 'publish', $password = '') {
  $GLOBALS['__gwseq_test_posts'][$id] = array('post_type' => $post_type, 'post_status' => $status, 'post_title' => $title, 'post_password' => $password);
}
function gws_test_make_post_object($id) {
  $p = $GLOBALS['__gwseq_test_posts'][$id];
  return (object) array('ID' => $id, 'post_type' => $p['post_type'], 'post_status' => $p['post_status'], 'post_title' => $p['post_title'], 'post_password' => $p['post_password']);
}
function get_post_type($post_id) { return $GLOBALS['__gwseq_test_posts'][$post_id]['post_type'] ?? false; }
function get_post($post_id) { return isset($GLOBALS['__gwseq_test_posts'][$post_id]) ? gws_test_make_post_object($post_id) : null; }
function get_the_title($post) {
  $id = is_object($post) ? $post->ID : $post;
  return $GLOBALS['__gwseq_test_posts'][$id]['post_title'] ?? '';
}
function get_permalink($post_id) { return 'https://example.test/chevaux/cheval-' . (int) $post_id . '/'; }
function home_url($path = '') { return 'https://example.test' . $path; }

$GLOBALS['__gwseq_test_context'] = array('is_singular' => false, 'queried_id' => 0, 'query_vars' => array());
function is_singular($post_type = '') { return $GLOBALS['__gwseq_test_context']['is_singular'] === $post_type; }
function get_queried_object_id() { return $GLOBALS['__gwseq_test_context']['queried_id']; }
function get_query_var($var, $default = '') { return $GLOBALS['__gwseq_test_context']['query_vars'][$var] ?? $default; }

// --- WP_Query minimal, suffisant pour gwseq_horse_private_share_find_cheval_id() (correspondance
// EXACTE d'une seule clause meta_query sur une clé donnée — pas besoin ici du moteur complet de
// gws-equestrian-cheval-share-admin-test.php, dédié aux filtres de recherche BO) ---
class WP_Query {
  public $posts = array();
  public function __construct($args = array()) {
    $post_type = $args['post_type'] ?? 'post';
    $meta_query = $args['meta_query'] ?? array();
    $limit = $args['posts_per_page'] ?? -1;
    $results = array();
    foreach ($GLOBALS['__gwseq_test_posts'] as $id => $post) {
      if ($post['post_type'] !== $post_type) continue;
      $match = true;
      foreach ($meta_query as $clause) {
        if (!isset($clause['key'])) continue;
        $value = get_post_meta($id, $clause['key'], true);
        if ((string) $value !== (string) ($clause['value'] ?? '')) { $match = false; break; }
      }
      if ($match) $results[] = $id;
    }
    if ($limit > 0) $results = array_slice($results, 0, $limit);
    $this->posts = $results;
  }
}

$GLOBALS['__gwseq_test_attachment_urls'] = array();
function wp_get_attachment_image_url($id, $size = 'thumbnail') { return $GLOBALS['__gwseq_test_attachment_urls'][$id][$size] ?? false; }
$GLOBALS['__gwseq_test_attachment_srcs'] = array();
function wp_get_attachment_image_src($id, $size = 'thumbnail') { return $GLOBALS['__gwseq_test_attachment_srcs'][$id][$size] ?? false; }
$GLOBALS['__gwseq_test_thumbnails'] = array();
function get_post_thumbnail_id($post_id) { return $GLOBALS['__gwseq_test_thumbnails'][$post_id] ?? 0; }

$GLOBALS['__gwseq_test_options'] = array();
function get_option($name, $default = false) {
  return array_key_exists($name, $GLOBALS['__gwseq_test_options']) ? $GLOBALS['__gwseq_test_options'][$name] : $default;
}

define('ABSPATH', __DIR__ . '/');
const GWSEQ_CPT_CHEVAL = 'gwseq_cheval';
define('GWSEQ_MODULE_URL', 'https://example.test/wp-content/plugins/gws-core/modules/gws-equestrian/');
define('GWSEQ_MODULE_VERSION', 'test');

$repo_root = dirname(__DIR__);
$module_dir = $repo_root . '/wp-content/plugins/gws-core/modules/gws-equestrian/';
require $repo_root . '/wp-content/plugins/gws-core/includes/fields.php';
require $module_dir . 'includes/settings.php';
require $module_dir . 'includes/race-referentiel.php';
require $module_dir . 'includes/cheval-fields.php';
require $module_dir . 'includes/cheval-editorial.php';
require $module_dir . 'includes/cheval-indices.php';
require $module_dir . 'includes/cheval-media.php';
require $module_dir . 'includes/pedigree-resolver.php';
require $module_dir . 'includes/cheval-pedigree.php';
require $module_dir . 'includes/cheval-share.php';

function gws_test_make_horse($id, $title, $overrides = array()) {
  gws_test_make_post($id, GWSEQ_CPT_CHEVAL, $title, $overrides['post_status'] ?? 'publish', $overrides['post_password'] ?? '');
  gwseq_set_cheval_identity($id, $overrides['identity'] ?? array());
  if (isset($overrides['commercial'])) {
    $commercial = gwseq_sanitize_cheval_commercial_input($overrides['commercial']);
    update_post_meta($id, '_gwseq_statut_commercial', $commercial['statut_commercial']);
    update_post_meta($id, '_gwseq_prix_mode', $commercial['prix_mode']);
    update_post_meta($id, '_gwseq_prix_fixe', $commercial['prix_fixe']);
    update_post_meta($id, '_gwseq_prix_min', $commercial['prix_min']);
    update_post_meta($id, '_gwseq_prix_max', $commercial['prix_max']);
    update_post_meta($id, '_gwseq_prix_demande_libelle', $commercial['prix_demande_libelle']);
  }
  if (isset($overrides['editorial'])) gwseq_set_cheval_editorial($id, $overrides['editorial']);
  if (isset($overrides['videos'])) gwseq_set_cheval_videos($id, $overrides['videos']);
}

// =====================================================================================
// Accroche commerciale (§3) : champ distinct de Présentation/Description, sanitation cohérente
// avec un texte court/multiligne, aucun fallback, aucune altération des autres champs éditoriaux.
// =====================================================================================

gws_test_make_post(1, GWSEQ_CPT_CHEVAL, 'Jamerose de Felines');
gwseq_set_cheval_editorial(1, array(
  '_gwseq_accroche_commerciale' => "Jument respectueuse et facile,\navec du sang.",
  '_gwseq_presentation' => 'Présentation longue et détaillée de la jument.',
));
$editorial_1 = gwseq_get_cheval_editorial(1);
gws_test_assert(strpos($editorial_1['accroche_commerciale'], 'Jument respectueuse') !== false, 'Accroche : enregistrée et relue correctement');
gws_test_assert(strpos($editorial_1['accroche_commerciale'], "\n") !== false, 'Accroche : multiligne préservée (texte court mais pas forcément une seule ligne)');
gws_test_assert($editorial_1['presentation'] === 'Présentation longue et détaillée de la jument.', 'Accroche : n’altère jamais le champ Présentation/Description, bien distinct');
gws_test_assert(strpos($editorial_1['accroche_commerciale'], '<') === false, 'Accroche : aucun HTML, texte simple uniquement');

gwseq_set_cheval_editorial(1, array('_gwseq_accroche_commerciale' => '<strong>test</strong> <script>x()</script>'));
$editorial_1_html = gwseq_get_cheval_editorial(1);
gws_test_assert(strpos($editorial_1_html['accroche_commerciale'], '<') === false, 'Accroche : les balises HTML sont retirées à la sanitation (sanitize_textarea_field)');

gws_test_make_post(2, GWSEQ_CPT_CHEVAL, 'Cheval Sans Accroche');
gwseq_set_cheval_editorial(2, array());
gws_test_assert(gwseq_get_cheval_editorial(2)['accroche_commerciale'] === '', 'Accroche : absente par défaut, aucun contenu généré à sa place (§2/§3 — aucun fallback)');

// =====================================================================================
// gwseq_get_horse_shareable_data() — identité, origines, taille/indice, statut/prix, accroche
// =====================================================================================

// --- Cheval très renseigné : tous les items présents ---
gws_test_make_horse(10, 'Jamerose de Felines', array(
  'identity' => array('_gwseq_sexe' => 'female', '_gwseq_race' => 'SF', '_gwseq_annee_naissance' => (int) gmdate('Y') - 7, '_gwseq_taille_cm' => '168'),
  'commercial' => array('_gwseq_statut_commercial' => 'for_sale', '_gwseq_prix_mode' => 'fixed', '_gwseq_prix_fixe' => '25000'),
  'editorial' => array('_gwseq_accroche_commerciale' => 'Jument respectueuse et facile.'),
  'videos' => array(array('url' => 'https://example.test/v1', 'titre' => 'Allures à 3 ans'), array('url' => 'https://example.test/v2', 'titre' => '')),
));
gwseq_set_cheval_sport_indice(10, 'iso', array('valeur' => '135', 'annee' => (int) gmdate('Y') - 1));
// Origines (§8) : Père (externe, "Untouchable") × Père de la MÈRE ("damsire") — la mère elle-même
// est une fiche GWS ("La Mère", #11) dont le père est l'externe "Kannan".
gwseq_set_horse_parent(10, 'father', array('mode' => 'external', 'external' => array('name' => 'Untouchable')));
gws_test_make_post(11, GWSEQ_CPT_CHEVAL, 'La Mère');
gwseq_set_horse_parent(11, 'father', array('mode' => 'external', 'external' => array('name' => 'Kannan')));
gwseq_set_horse_parent(10, 'mother', array('mode' => 'gws', 'horse_id' => 11));

$shareable_10 = gwseq_get_horse_shareable_data(10);
gws_test_assert($shareable_10['nom'] === 'Jamerose de Felines', 'Shareable : nom brut de la fiche');
gws_test_assert($shareable_10['nom_affiche'] === 'JAMEROSE DE FELINES', 'Shareable : nom affiché en majuscules sans accents (convention déjà en place)');
gws_test_assert($shareable_10['items']['identite']['label'] === 'Jument Selle Français — 7 ans', 'Shareable : identité composée (vocabulaire commercial "Jument", race, âge)');
gws_test_assert($shareable_10['items']['identite']['default_checked'] === true, 'Shareable : identité présélectionnée par défaut (§9)');
gws_test_assert($shareable_10['items']['origines']['label'] === 'Par UNTOUCHABLE × KANNAN', 'Shareable : origines = Père (externe) × Père de la mère (GWS), noms en majuscules');
gws_test_assert($shareable_10['items']['taille_indice']['label'] === '1,68 m • ISO 135', 'Shareable : taille (virgule française) + UN SEUL indice sportif (priorité ISO)');
gws_test_assert(array_key_exists('prix', $shareable_10['items']) && $shareable_10['items']['prix']['label'] === 'À vendre — 25 000 €', 'Shareable : prix proposé car statut "À vendre" ET prix renseigné');
gws_test_assert($shareable_10['items']['prix']['default_checked'] === false, 'Shareable : le prix n’est JAMAIS présélectionné par défaut (§9, prudence commerciale)');
gws_test_assert($shareable_10['items']['accroche']['label'] === 'Jument respectueuse et facile.', 'Shareable : accroche présente reprise telle quelle');
gws_test_assert($shareable_10['items']['accroche']['default_checked'] === true, 'Shareable : accroche présélectionnée par défaut lorsqu’elle existe');
gws_test_assert(count($shareable_10['videos']) === 2, 'Shareable : les deux vidéos réellement présentes sont proposées');
gws_test_assert($shareable_10['videos'][0]['label'] === 'Allures à 3 ans', 'Shareable : vidéo AVEC titre -> "{titre}", SANS pictogramme (correctif de recette WhatsApp — transport peu fiable sur appareil réel)');
gws_test_assert($shareable_10['videos'][1]['label'] === 'Vidéo', 'Shareable : vidéo SANS titre -> "Vidéo" (jamais un titre inventé, §11 ; sans pictogramme non plus)');
gws_test_assert(strpos($shareable_10['videos'][0]['label'], '🎥') === false && strpos($shareable_10['videos'][1]['label'], '🎥') === false, 'Shareable : aucun pictogramme vidéo résiduel, ni un autre emoji de remplacement (§2 du correctif — "ne pas le remplacer par un autre emoji")');
gws_test_assert($shareable_10['videos'][0]['default_checked'] === true && $shareable_10['videos'][1]['default_checked'] === true, 'Shareable : les deux premières vidéos sont présélectionnées (2 <= présélection par défaut)');
gws_test_assert($shareable_10['fiche_url'] === 'https://example.test/chevaux/cheval-10/', 'Shareable : lien de fiche complète présent (cheval publié, non protégé)');
gws_test_assert($shareable_10['fiche_default_checked'] === true, 'Shareable : fiche complète présélectionnée par défaut lorsqu’un lien public existe (§9/§13)');

// --- Cheval peu renseigné : uniquement ce qui existe réellement, rien d'autre (§2) ---
gws_test_make_horse(20, 'Cheval Anonyme');
$shareable_20 = gwseq_get_horse_shareable_data(20);
gws_test_assert($shareable_20['items'] === array(), 'Shareable : cheval peu renseigné -> aucun item structuré proposé, jamais une case vide');
gws_test_assert($shareable_20['videos'] === array(), 'Shareable : aucune vidéo -> tableau vide');
gws_test_assert($shareable_20['photo_url'] === '', 'Shareable : aucune photo -> chaîne vide, jamais une image de remplacement fabriquée');

// --- Trois vidéos ou plus : présélection des deux premières, mais toutes sélectionnables (§11) ---
gws_test_make_horse(21, 'Cheval Multi-Videos', array('videos' => array(
  array('url' => 'https://example.test/v1', 'titre' => 'Une'),
  array('url' => 'https://example.test/v2', 'titre' => 'Deux'),
  array('url' => 'https://example.test/v3', 'titre' => 'Trois'),
)));
$shareable_21 = gwseq_get_horse_shareable_data(21);
gws_test_assert(count($shareable_21['videos']) === 3, 'Shareable : trois vidéos, toutes proposées (aucune limite artificielle de sélection)');
gws_test_assert($shareable_21['videos'][2]['default_checked'] === false, 'Shareable : la troisième vidéo n’est PAS présélectionnée par défaut, mais reste sélectionnable');

// =====================================================================================
// Règle statut commercial / prix (§10) — le prix ne doit JAMAIS être proposé hors contexte actif
// =====================================================================================

gws_test_make_horse(30, 'Cheval Non Propose', array('commercial' => array('_gwseq_statut_commercial' => 'not_offered', '_gwseq_prix_mode' => 'fixed', '_gwseq_prix_fixe' => '30000')));
gws_test_assert(!array_key_exists('prix', gwseq_get_horse_shareable_data(30)['items']), 'Règle prix/statut : "Non proposé" -> le prix n’est JAMAIS proposé au partage, même techniquement enregistré (§10, cas explicitement cité par la demande)');

gws_test_make_horse(31, 'Cheval Vendu', array('commercial' => array('_gwseq_statut_commercial' => 'sold', '_gwseq_prix_mode' => 'fixed', '_gwseq_prix_fixe' => '30000')));
gws_test_assert(!array_key_exists('prix', gwseq_get_horse_shareable_data(31)['items']), 'Règle prix/statut : "Vendu" -> le prix n’est pas proposé au partage (contexte commercial non actif)');

gws_test_make_horse(32, 'Cheval Reserve', array('commercial' => array('_gwseq_statut_commercial' => 'reserved', '_gwseq_prix_mode' => 'fixed', '_gwseq_prix_fixe' => '30000')));
gws_test_assert(gwseq_get_horse_shareable_data(32)['items']['prix']['label'] === 'Réservé — 30 000 €', 'Règle prix/statut : "Réservé" -> le prix reste proposable (contexte commercial encore actif)');

gws_test_make_horse(33, 'Cheval A Vendre Sans Prix', array('commercial' => array('_gwseq_statut_commercial' => 'for_sale', '_gwseq_prix_mode' => 'on_request', '_gwseq_prix_demande_libelle' => '')));
gws_test_assert(!array_key_exists('prix', gwseq_get_horse_shareable_data(33)['items']), 'Règle prix/statut : statut actif mais aucun prix exploitable -> aucun item prix (jamais une ligne vide)');

// =====================================================================================
// Public / non public (§13/§20) — lien de fiche complète
// =====================================================================================

gws_test_make_horse(40, 'Cheval Brouillon', array('post_status' => 'draft'));
gws_test_assert(gwseq_horse_is_publicly_viewable(40) === false, 'Visibilité publique : un brouillon n’est jamais publiquement visible');
gws_test_assert(gwseq_get_horse_shareable_data(40)['fiche_url'] === '', 'Partage : aucun lien de fiche pour un cheval non publiquement visible (jamais de brouillon exposé, §13/§20)');

gws_test_make_horse(41, 'Cheval Protege', array('post_password' => 'secret'));
gws_test_assert(gwseq_horse_is_publicly_viewable(41) === false, 'Visibilité publique : un contenu protégé par mot de passe n’est jamais publiquement visible');
gws_test_assert(gwseq_get_horse_shareable_data(41)['fiche_url'] === '', 'Partage : aucun lien de fiche pour un cheval protégé par mot de passe');

gws_test_make_horse(42, 'Cheval Public', array('post_status' => 'publish'));
gws_test_assert(gwseq_horse_is_publicly_viewable(42) === true, 'Visibilité publique : une fiche publiée sans mot de passe est publiquement visible');
gws_test_assert(gwseq_get_horse_shareable_data(42)['fiche_url'] !== '', 'Partage : lien de fiche présent pour un cheval réellement public');

// =====================================================================================
// gwseq_build_horse_share_message() — composition du message final (§14/§16)
// =====================================================================================

$message_full = gwseq_build_horse_share_message($shareable_10, array(
  'items' => array('identite', 'origines', 'taille_indice', 'accroche'),
  'videos' => array(0, 1),
  'fiche' => true,
  'message_personnel' => '',
));
gws_test_assert(strpos($message_full, "JAMEROSE DE FELINES\nJument Selle Français — 7 ans\nPar UNTOUCHABLE × KANNAN\n1,68 m • ISO 135") === 0, 'Message : bloc identité (nom + lignes structurées sélectionnées) en tête, sans ligne vide entre elles');
gws_test_assert(strpos($message_full, "\n\nJument respectueuse et facile.") !== false, 'Message : accroche sélectionnée en paragraphe séparé (ligne vide avant)');
gws_test_assert(strpos($message_full, 'Allures à 3 ans : https://example.test/v1') !== false, 'Message : ligne vidéo "{libellé} : {url}"');
gws_test_assert(strpos($message_full, 'Vidéo : https://example.test/v2') !== false, 'Message : deuxième vidéo (sans titre) bien incluse également');
gws_test_assert(strpos($message_full, '🎥') === false, 'Message : aucun pictogramme vidéo dans le message final (correctif de recette — transport peu fiable vers WhatsApp sur appareil réel, retiré sans être remplacé par un autre emoji)');
gws_test_assert(strpos($message_full, 'Fiche complète, photos et pedigree :') !== false, 'Message : intitulé fixe du lien de fiche');
gws_test_assert(substr($message_full, -strlen($shareable_10['fiche_url'])) === $shareable_10['fiche_url'], 'Message : URL de la fiche complète en toute fin de message');
gws_test_assert(strpos($message_full, 'À vendre') === false, 'Message : le prix n’apparaît pas s’il n’a pas été sélectionné, même s’il existe');

$message_with_price = gwseq_build_horse_share_message($shareable_10, array('items' => array('prix'), 'videos' => array(), 'fiche' => false));
gws_test_assert(strpos($message_with_price, 'À vendre — 25 000 €') !== false, 'Message : ligne commerciale adaptée intégrée lorsque le prix est explicitement sélectionné');
gws_test_assert(strpos($message_with_price, 'Fiche complète') === false, 'Message : aucun lien de fiche si "fiche" non sélectionné');

// =====================================================================================
// Correctif de recette (test réel WhatsApp §3) — bascule explicite de "Ajouter la fiche complète"
// vérifiée isolément côté moteur de composition : cochée -> bloc "Fiche complète, photos et
// pedigree :\n{URL}" intégralement présent ; décochée -> ce bloc est ABSENT, y compris son
// intitulé fixe (jamais seulement l'URL retirée en laissant l'intitulé orphelin).
// =====================================================================================

$message_fiche_on = gwseq_build_horse_share_message($shareable_10, array('items' => array(), 'videos' => array(), 'fiche' => true));
gws_test_assert(strpos($message_fiche_on, "Fiche complète, photos et pedigree :\n" . $shareable_10['fiche_url']) !== false, 'Fiche complète cochée : intitulé + URL présents, sur deux lignes, bloc entier intact');

$message_fiche_off = gwseq_build_horse_share_message($shareable_10, array('items' => array(), 'videos' => array(), 'fiche' => false));
gws_test_assert(strpos($message_fiche_off, 'Fiche complète') === false, 'Fiche complète décochée : intitulé totalement absent');
gws_test_assert(strpos($message_fiche_off, $shareable_10['fiche_url']) === false, 'Fiche complète décochée : URL totalement absente elle aussi (jamais un intitulé orphelin ni une URL orpheline)');

$message_personnel = gwseq_build_horse_share_message($shareable_10, array('items' => array(), 'videos' => array(), 'fiche' => false, 'message_personnel' => "Bonjour Pierre, je pensais à cette jument suite à notre échange :"));
gws_test_assert(strpos($message_personnel, "Bonjour Pierre, je pensais à cette jument suite à notre échange :\n\nJAMEROSE DE FELINES") === 0, 'Message : message personnel en tête, séparé par une ligne vide, avant le nom du cheval');

$message_minimal = gwseq_build_horse_share_message(gwseq_get_horse_shareable_data(20), array('items' => array(), 'videos' => array(), 'fiche' => false));
gws_test_assert(trim($message_minimal) === 'CHEVAL ANONYME', 'Message : aucune sélection -> seul le nom du cheval demeure (toujours inclus, §14)');

// =====================================================================================
// Correctif de recette — « le prix apparaît dans l'aperçu alors qu'il n'est pas sélectionné ».
// La cause racine identifiée était CÔTÉ CLIENT (une réponse AJAX obsolète pouvait écraser
// l'aperçu à jour, voir assets/cheval-share-admin.js et le test runtime dédié) : ces assertions
// prouvent ici que gwseq_build_horse_share_message() lui-même, isolément, a TOUJOURS respecté la
// sélection transmise — le "coché puis décoché" round-trip et les autres blocs sélectionnables,
// pas seulement le prix.
// =====================================================================================

$message_prix_toggle_on = gwseq_build_horse_share_message($shareable_10, array('items' => array('prix'), 'videos' => array(), 'fiche' => false));
gws_test_assert(strpos($message_prix_toggle_on, '25 000') !== false, 'Round-trip prix : sélectionné -> présent dans le message');
$message_prix_toggle_off = gwseq_build_horse_share_message($shareable_10, array('items' => array(), 'videos' => array(), 'fiche' => false));
gws_test_assert(strpos($message_prix_toggle_off, '25 000') === false, 'Round-trip prix : re-décoché juste après -> disparaît immédiatement du message, aucune trace résiduelle de la sélection précédente');

// --- Même principe vérifié pour chaque AUTRE bloc sélectionnable (§1 : "vérifier qu'il n'existe
// pas le même problème avec les autres informations sélectionnables") — jamais présent si non
// sélectionné, jamais absent si sélectionné, indépendamment des autres blocs. ---
foreach (array(
  'identite' => 'Jument Selle Français',
  'origines' => 'UNTOUCHABLE',
  'taille_indice' => '1,68 m',
  'accroche' => 'Jument respectueuse',
) as $item_key => $expected_fragment) {
  $with = gwseq_build_horse_share_message($shareable_10, array('items' => array($item_key), 'videos' => array(), 'fiche' => false));
  gws_test_assert(strpos($with, $expected_fragment) !== false, "Round-trip \"$item_key\" : sélectionné -> présent dans le message");
  $without = gwseq_build_horse_share_message($shareable_10, array('items' => array(), 'videos' => array(), 'fiche' => false));
  gws_test_assert(strpos($without, $expected_fragment) === false, "Round-trip \"$item_key\" : non sélectionné -> absent du message, même s'il est disponible pour ce cheval");
}

// --- Vidéos : même principe (présentes seulement si leur index précis est sélectionné) ---
$with_video_0 = gwseq_build_horse_share_message($shareable_10, array('items' => array(), 'videos' => array(0), 'fiche' => false));
gws_test_assert(strpos($with_video_0, 'Allures à 3 ans') !== false, 'Round-trip vidéo : index sélectionné -> présent');
$without_video_0 = gwseq_build_horse_share_message($shareable_10, array('items' => array(), 'videos' => array(), 'fiche' => false));
gws_test_assert(strpos($without_video_0, 'Allures à 3 ans') === false, 'Round-trip vidéo : index non sélectionné -> absent, même s\'il est disponible pour ce cheval');

// --- Fiche complète : même principe ---
$with_fiche = gwseq_build_horse_share_message($shareable_10, array('items' => array(), 'videos' => array(), 'fiche' => true));
gws_test_assert(strpos($with_fiche, $shareable_10['fiche_url']) !== false, 'Round-trip fiche complète : sélectionnée -> lien présent');
$without_fiche = gwseq_build_horse_share_message($shareable_10, array('items' => array(), 'videos' => array(), 'fiche' => false));
gws_test_assert(strpos($without_fiche, $shareable_10['fiche_url']) === false, 'Round-trip fiche complète : non sélectionnée -> lien absent');

$message_no_selection_but_content = gwseq_build_horse_share_message($shareable_10, array('items' => array('inexistant'), 'videos' => array(999), 'fiche' => true));
gws_test_assert(strpos($message_no_selection_but_content, 'Fiche complète') !== false, 'Message : une clé d’item ou un index de vidéo invalide est simplement ignoré, jamais une erreur');

// =====================================================================================
// Open Graph (§19) — jamais de prix, jamais de second système SEO si un plugin est actif
// =====================================================================================

gws_test_assert(gwseq_horse_og_description($shareable_10) === 'Jument Selle Français — 7 ans — Par UNTOUCHABLE × KANNAN — Jument respectueuse et facile.', 'Open Graph : description = identité + origines + accroche, jamais le prix');
gws_test_assert(strpos(gwseq_horse_og_description($shareable_10), '25 000') === false, 'Open Graph : le prix n’apparaît JAMAIS dans la description, même sélectionnable par ailleurs');

$long_shareable = $shareable_10;
$long_shareable['items']['accroche']['label'] = str_repeat('Une très longue accroche commerciale bien détaillée. ', 10);
$long_description = gwseq_horse_og_description($long_shareable);
gws_test_assert(mb_strlen($long_description) <= GWSEQ_HORSE_OG_DESCRIPTION_MAX_LENGTH + 1, 'Open Graph : description tronquée proprement à une longueur raisonnable');

ob_start();
$GLOBALS['__gwseq_test_context'] = array('is_singular' => GWSEQ_CPT_CHEVAL, 'queried_id' => 10);
$GLOBALS['__gwseq_test_thumbnails'][10] = 555;
$GLOBALS['__gwseq_test_attachment_srcs'][555]['medium_large'] = array('https://example.test/photo-derivee.jpg', 800, 600);
gwseq_render_horse_og_meta();
$og_html = ob_get_clean();
gws_test_assert(strpos($og_html, 'og:title" content="Jamerose de Felines"') !== false, 'Open Graph : og:title émis pour un cheval public');
gws_test_assert(strpos($og_html, 'og:image" content="https://example.test/photo-derivee.jpg"') !== false, 'Open Graph : og:image utilise une DÉRIVÉE adaptée (medium_large), jamais l’original');
gws_test_assert(strpos($og_html, 'og:image:width" content="800"') !== false && strpos($og_html, 'og:image:height" content="600"') !== false, 'Open Graph : dimensions de l’image incluses');
gws_test_assert(strpos($og_html, 'og:url" content="https://example.test/chevaux/cheval-10/"') !== false, 'Open Graph : og:url cohérent avec le permalien réel');
gws_test_assert(strpos($og_html, '25 000') === false && strpos($og_html, 'gwseq_prix') === false, 'Open Graph : aucune donnée commerciale dans les balises émises');

ob_start();
$GLOBALS['__gwseq_test_context'] = array('is_singular' => GWSEQ_CPT_CHEVAL, 'queried_id' => 40);
gwseq_render_horse_og_meta();
$og_html_draft = ob_get_clean();
gws_test_assert($og_html_draft === '', 'Open Graph : rien n’est émis pour un cheval non publiquement visible (brouillon), même en prévisualisation interne');

ob_start();
$GLOBALS['__gwseq_test_context'] = array('is_singular' => false, 'queried_id' => 10);
gwseq_render_horse_og_meta();
gws_test_assert(ob_get_clean() === '', 'Open Graph : rien n’est émis hors d’une page singulière de cheval');

// =====================================================================================
// Correctif de recette — un titre de cheval contenant une entité HTML littérale (ex. "NACELLE
// D&rsquo;ELLE" affiché tel quel au lieu de "NACELLE D'ELLE") doit être décodé AVANT toute
// utilisation dans ce module (gwseq_horse_share_decode_title()) — jamais réécrit en base (aucun
// gws_test_make_post() ci-dessous ne modifie le titre stocké, uniquement le titre LU). Couvre a
// minima : apostrophe droite, apostrophe typographique, entité nommée encodée, esperluette, lettres
// accentuées, et une chaîne HTML potentiellement dangereuse — pour prouver qu'aucune injection
// n'est possible une fois décodée (jamais de HTML brut rendu, voir gwseq_render_horse_og_meta() plus
// haut qui réapplique esc_attr() sur la valeur décodée).
// =====================================================================================

gws_test_assert(gwseq_horse_share_decode_title("NACELLE D'ELLE") === "NACELLE D'ELLE", 'Décodage titre : apostrophe droite déjà correcte -> inchangée');
gws_test_assert(gwseq_horse_share_decode_title('NACELLE D’ELLE') === 'NACELLE D’ELLE', 'Décodage titre : apostrophe typographique (caractère réel) déjà correcte -> inchangée');
gws_test_assert(gwseq_horse_share_decode_title('NACELLE D&rsquo;ELLE') === 'NACELLE D’ELLE', 'Décodage titre : entité nommée "&rsquo;" littérale -> décodée en caractère apostrophe typographique (cause racine du bug de recette)');
gws_test_assert(gwseq_horse_share_decode_title('NACELLE D&#8217;ELLE') === 'NACELLE D’ELLE', 'Décodage titre : entité numérique "&#8217;" -> décodée de façon identique');
gws_test_assert(gwseq_horse_share_decode_title('Bibi &amp; Co') === 'Bibi & Co', 'Décodage titre : esperluette encodée "&amp;" -> caractère "&" simple');
gws_test_assert(gwseq_horse_share_decode_title('Étalon Décoré') === 'Étalon Décoré', 'Décodage titre : lettres accentuées déjà correctes -> inchangées (pas de sur-décodage)');
gws_test_assert(gwseq_horse_share_decode_title('&lt;script&gt;alert(1)&lt;/script&gt;') === '<script>alert(1)</script>', 'Décodage titre : chaîne HTML dangereuse encodée -> décodée en texte littéral inerte (chaîne PHP simple, jamais exécutée)');

gws_test_make_horse(50, "NACELLE D&rsquo;ELLE");
$shareable_50 = gwseq_get_horse_shareable_data(50);
gws_test_assert($shareable_50['nom'] === 'NACELLE D’ELLE', 'Shareable : "nom" décodé -> plus jamais l’entité littérale "&rsquo;" affichée telle quelle');
gws_test_assert($shareable_50['nom_affiche'] === 'NACELLE D’ELLE', 'Shareable : "nom_affiche" construit à partir du titre déjà décodé (convention majuscules déjà en place, gwseq_format_horse_name_display)');
gws_test_assert(strpos($shareable_50['nom'], '&rsquo;') === false && strpos($shareable_50['nom_affiche'], '&rsquo;') === false, 'Shareable : aucune entité littérale résiduelle dans les champs exposés au partage');

// --- Titre dangereux : jamais de HTML exécutable, à aucune étape (message texte, Open Graph) ---
gws_test_make_horse(51, '&lt;img src=x onerror=alert(1)&gt;');
$shareable_51 = gwseq_get_horse_shareable_data(51);
gws_test_assert($shareable_51['nom'] === '<img src=x onerror=alert(1)>', 'Shareable : titre dangereux décodé en texte littéral inerte (chaîne, pas du HTML interprété)');
$message_51 = gwseq_build_horse_share_message($shareable_51, array('items' => array(), 'videos' => array(), 'fiche' => false));
gws_test_assert(trim($message_51) === '<IMG SRC=X ONERROR=ALERT(1)>', 'Message : le texte décodé (via nom_affiche) est repris tel quel dans un message TEXTE (aucun contexte HTML, aucun échappement supplémentaire nécessaire ni pertinent)');
gws_test_assert(strpos($message_51, '&lt;') === false && strpos($message_51, '&gt;') === false, 'Message : aucune entité résiduelle dans le message texte (le décodage a bien eu lieu en amont)');

ob_start();
$GLOBALS['__gwseq_test_context'] = array('is_singular' => GWSEQ_CPT_CHEVAL, 'queried_id' => 51);
gwseq_render_horse_og_meta();
$og_html_dangerous = ob_get_clean();
gws_test_assert(strpos($og_html_dangerous, 'og:title" content="&lt;img src=x onerror=alert(1)&gt;"') !== false, 'Open Graph : valeur décodée rééchappée par esc_attr() pour un rendu HTML sûr en attribut (jamais de balise "<img>" brute dans la page)');
gws_test_assert(strpos($og_html_dangerous, '<img') === false, 'Open Graph : aucune balise HTML brute injectée dans <head> (pas de vulnérabilité XSS via le titre)');

// --- Origines (pedigree) : le nom d'un parent (externe ou fiche GWS) contenant une entité littérale
// doit lui aussi être décodé — gwseq_horse_share_pedigree_node_name() réutilise
// gwseq_horse_share_decode_title() avant gwseq_format_horse_name_display() ---
gws_test_make_horse(52, 'Cheval Origines Entite');
gwseq_set_horse_parent(52, 'father', array('mode' => 'external', 'external' => array('name' => "Fils d&rsquo;Or")));
$origines_52 = gwseq_horse_share_origines_label(52);
gws_test_assert(strpos($origines_52, '&rsquo;') === false, 'Origines : aucune entité littérale résiduelle dans le nom d’un parent externe');
gws_test_assert(strpos($origines_52, "FILS D") !== false, 'Origines : nom du parent externe avec entité décodée bien repris (convention majuscules)');

// =====================================================================================
// Suite V1 « Partager & vendre » — Lot 1 : partage privé (§2.B/§16 de la demande).
// =====================================================================================

// --- Génération du token : long, aléatoire, jamais un identifiant métier prévisible ---
$token_a = gwseq_horse_private_share_generate_token();
$token_b = gwseq_horse_private_share_generate_token();
gws_test_assert(strlen($token_a) === 64, 'Partage privé : token de 64 caractères (32 octets hexadécimaux — random_bytes(32))');
gws_test_assert(ctype_xdigit($token_a), 'Partage privé : token entièrement hexadécimal');
gws_test_assert($token_a !== $token_b, 'Partage privé : deux générations successives produisent des tokens différents (aucune valeur figée/prévisible)');

gws_test_make_horse(60, 'Cheval Prive');
update_post_meta(60, '_gwseq_global_id', 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee');
gws_test_assert(gwseq_horse_private_share_is_active(60) === false, 'Partage privé : inactif par défaut, aucun token créé automatiquement');
gws_test_assert(gwseq_horse_private_share_url(60) === '', 'Partage privé : aucune URL tant qu’aucun token n’est actif');

$token_60 = gwseq_horse_private_share_activate(60);
gws_test_assert(gwseq_horse_private_share_is_active(60) === true, 'Partage privé : actif immédiatement après activation');
gws_test_assert($token_60 !== (string) 60 && $token_60 !== 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee', 'Partage privé : le token n’est JAMAIS l’ID WordPress ni le Global Horse ID (§ "jamais un identifiant prévisible comme token")');
gws_test_assert(strlen($token_60) === 64 && $token_60 !== gwseq_get_cheval_global_id(60), 'Partage privé : format et valeur totalement distincts du Global Horse ID (36 caractères avec tirets, jamais un secret)');
gws_test_assert(gwseq_horse_private_share_url(60) === 'https://example.test/partage/' . $token_60 . '/', 'Partage privé : URL "/partage/{token}/" construite sur home_url()');

gws_test_assert(gwseq_horse_private_share_find_cheval_id($token_60) === 60, 'Partage privé : recherche inverse token -> cheval fonctionne pour un token actif');
gws_test_assert(gwseq_horse_private_share_find_cheval_id(str_repeat('0', 64)) === 0, 'Partage privé : un token bien formé mais qui ne correspond à aucun cheval -> introuvable, jamais une erreur');
gws_test_assert(gwseq_horse_private_share_find_cheval_id('abc') === 0, 'Partage privé : un token trop court est rejeté avant toute requête (format invalide)');
gws_test_assert(gwseq_horse_private_share_find_cheval_id(strtoupper($token_60)) === 0, 'Partage privé : la casse compte — un token en MAJUSCULES ne correspond jamais (le format produit est toujours en minuscules)');
gws_test_assert(gwseq_horse_private_share_find_cheval_id('') === 0, 'Partage privé : une chaîne vide est rejetée avant toute requête');

// --- Régénération : l'ancien lien cesse IMMÉDIATEMENT de fonctionner (§ "la régénération invalide
// immédiatement l'ancien lien") ---
$token_60_regenere = gwseq_horse_private_share_activate(60);
gws_test_assert($token_60_regenere !== $token_60, 'Partage privé : régénérer produit un NOUVEAU token, distinct de l’ancien');
gws_test_assert(gwseq_horse_private_share_find_cheval_id($token_60) === 0, 'Partage privé : l’ANCIEN token ne retrouve plus aucun cheval immédiatement après régénération');
gws_test_assert(gwseq_horse_private_share_find_cheval_id($token_60_regenere) === 60, 'Partage privé : le NOUVEAU token fonctionne immédiatement');

// --- Révocation : réellement effective ---
gwseq_horse_private_share_revoke(60);
gws_test_assert(gwseq_horse_private_share_is_active(60) === false, 'Partage privé : révocation -> inactif');
gws_test_assert(gwseq_horse_private_share_url(60) === '', 'Partage privé : révocation -> plus aucune URL');
gws_test_assert(gwseq_horse_private_share_find_cheval_id($token_60_regenere) === 0, 'Partage privé : révocation -> l’ancien token ne retrouve plus jamais le cheval');

// =====================================================================================
// AJUSTEMENT D'ARCHITECTURE (recette) — visibilité publique et existence d'un lien privé sont
// DÉCOUPLÉES : la priorité "privé > public > aucun" initiale s'est révélée trop risquée (créer un
// lien privé sur un cheval déjà publié rendait involontairement son permalink public inaccessible).
// Nouvelle priorité : PUBLIC d'abord (si le cheval est réellement visible), sinon PRIVÉ (si un
// token est actif), sinon AUCUN. Un token existant ne doit JAMAIS masquer une fiche publique
// valide — les 8 scénarios ci-dessous couvrent explicitement chaque cas demandé.
// =====================================================================================

// --- 1. Cheval public SANS token -> public ---
gws_test_make_horse(61, 'Cheval Public Seul'); // publish par défaut (gws_test_make_post())
$info_public_sans_token = gwseq_horse_share_fiche_info(61);
gws_test_assert($info_public_sans_token['type'] === 'publique' && $info_public_sans_token['url'] === get_permalink(61), 'Fiche info : cheval public sans token -> lien PUBLIC normal');

// --- 2. Cheval public AVEC token -> le lien PUBLIC reste utilisé, la fiche publique reste
// accessible (jamais de 404 publique causée UNIQUEMENT par l'existence d'un token) ---
gwseq_horse_private_share_activate(61);
gws_test_assert(gwseq_horse_private_share_is_active(61) === true, 'Pré-requis : le cheval public 61 porte bien un token actif pour ce scénario');
$info_public_avec_token = gwseq_horse_share_fiche_info(61);
gws_test_assert($info_public_avec_token['type'] === 'publique' && $info_public_avec_token['url'] === get_permalink(61), 'Fiche info : cheval public AVEC un token actif -> le lien reste PUBLIC (un token ne masque jamais une fiche publique valide)');
gws_test_assert(gwseq_horse_is_publicly_viewable(61) === true, 'Visibilité publique : reste vraie pour un cheval publié même avec un token privé actif — jamais bloquée par l’existence d’un token (aucune "404 publique causée uniquement par l’existence d’un token")');

// --- 8. bis : gwseq_horse_is_private_share_only() — le prédicat qui remplace l'ancienne priorité
// "privé d'abord" : faux pour un cheval public même avec token, vrai seulement si NI public NI
// autre chose ---
gws_test_assert(gwseq_horse_is_private_share_only(61) === false, 'Mode partage privé exclusif : FAUX pour un cheval public, même avec un token actif (§ "ne doit plus modifier ni bloquer la fiche publique")');

// --- 6. bis : passage public -> privé (dépublication d'un cheval qui a un ancien token) : le
// token, laissé inchangé par la dépublication elle-même (aucune révocation automatique), redevient
// le seul lien utilisable — exactement la règle "sinon, si un token privé actif existe -> URL
// privée" ---
$GLOBALS['__gwseq_test_posts'][61]['post_status'] = 'draft'; // dépublication (simulateur direct de statut, sans passer par une API de test dédiée)
gws_test_assert(gwseq_horse_is_publicly_viewable(61) === false, 'Pré-requis : le cheval 61 n’est plus publiquement visible après dépublication');
$info_devenu_prive = gwseq_horse_share_fiche_info(61);
gws_test_assert($info_devenu_prive['type'] === 'privee' && $info_devenu_prive['url'] === gwseq_horse_private_share_url(61), 'Passage public -> privé : la fiche publique cesse d’être proposée, le token existant (jamais révoqué automatiquement) redevient utilisable immédiatement');
gws_test_assert(gwseq_horse_is_private_share_only(61) === true, 'Mode partage privé exclusif : VRAI une fois le cheval dépublié, tant que son token reste actif');
$GLOBALS['__gwseq_test_posts'][61]['post_status'] = 'publish'; // republication pour la suite du test
gwseq_horse_private_share_revoke(61); // remis dans l'état "public sans token" pour la suite

// --- 4. Cheval non public SANS token -> aucun lien (jamais accessible par accident, §2.C) ---
gws_test_make_horse(62, 'Cheval Brouillon Seul', array('post_status' => 'draft'));
$info_brouillon = gwseq_horse_share_fiche_info(62);
gws_test_assert($info_brouillon['type'] === '' && $info_brouillon['url'] === '', 'Fiche info : cheval non public sans token -> aucun lien, jamais accessible par accident (§2.C)');

// --- 3. Cheval non public AVEC token -> privé (logique explicite, jamais automatique) ---
gwseq_horse_private_share_activate(62);
$info_brouillon_prive = gwseq_horse_share_fiche_info(62);
gws_test_assert($info_brouillon_prive['type'] === 'privee' && $info_brouillon_prive['url'] === gwseq_horse_private_share_url(62), 'Fiche info : cheval non public AVEC token -> lien PRIVÉ (§2.C — logique explicite, jamais automatique)');
gws_test_assert(gwseq_horse_is_private_share_only(62) === true, 'Mode partage privé exclusif : VRAI pour un brouillon avec token actif — seul cas où le traitement "privé" s’applique');

// --- gwseq_get_horse_shareable_data() expose bien fiche_type, cohérent avec fiche_url ---
$shareable_62 = gwseq_get_horse_shareable_data(62);
gws_test_assert($shareable_62['fiche_type'] === 'privee' && $shareable_62['fiche_url'] === gwseq_horse_private_share_url(62) && $shareable_62['fiche_default_checked'] === true, 'Shareable : fiche_type "privee" exposé et cohérent avec fiche_url/fiche_default_checked');

// --- 5. Passage privé -> public : la fiche publique doit devenir accessible, le partage utilise
// désormais l'URL publique, MAIS le token n'est pas révoqué automatiquement (§ "ne pas casser les
// liens déjà envoyés") ---
$GLOBALS['__gwseq_test_posts'][62]['post_status'] = 'publish'; // publication du cheval 62
$info_devenu_public = gwseq_horse_share_fiche_info(62);
gws_test_assert($info_devenu_public['type'] === 'publique' && $info_devenu_public['url'] === get_permalink(62), 'Passage privé -> public : le partage utilise désormais l’URL PUBLIQUE, plus l’URL privée');
gws_test_assert(gwseq_horse_private_share_is_active(62) === true, 'Passage privé -> public : le token existant N’EST PAS révoqué automatiquement par la seule publication (§ "ne pas révoquer automatiquement sans nécessité")');
// --- 7. L'ancien token reste techniquement valide après ce passage (décision produit assumée :
// "il peut rester valide pour ne pas casser les liens déjà envoyés") — la route /partage/{token}
// continuerait donc de fonctionner pour ce cheval désormais public ---
gws_test_assert(gwseq_horse_private_share_find_cheval_id(gwseq_horse_private_share_token(62)) === 62, 'Ancien token toujours valide après passage privé -> public : la recherche inverse le retrouve toujours (le lien déjà envoyé continue de fonctionner)');
gws_test_assert(gwseq_horse_is_private_share_only(62) === false, 'Mode partage privé exclusif : redevient FAUX dès que le cheval est de nouveau public, même si son ancien token reste actif');

gwseq_horse_private_share_revoke(62);
$shareable_62_apres_revoke = gwseq_get_horse_shareable_data(62);
gws_test_assert($shareable_62_apres_revoke['fiche_type'] === 'publique' && $shareable_62_apres_revoke['fiche_url'] === get_permalink(62), 'Shareable : après révocation d’un cheval désormais public, le lien PUBLIC reste proposé normalement (jamais de régression vers "aucun lien")');

// --- Open Graph sur la route de partage privé (§4) : fonctionne, og:url pointe vers le lien
// PRIVÉ effectivement partagé (jamais l'URL publique), et une balise noindex est ajoutée ---
gws_test_make_horse(63, 'Cheval Prive OG', array('post_status' => 'draft'));
$token_63 = gwseq_horse_private_share_activate(63);

ob_start();
$GLOBALS['__gwseq_test_context'] = array('is_singular' => GWSEQ_CPT_CHEVAL, 'queried_id' => 63, 'query_vars' => array('gwseq_partage_token' => $token_63));
gwseq_render_horse_og_meta();
$og_html_prive = ob_get_clean();
gws_test_assert($og_html_prive !== '', 'Open Graph : émis sur la route de partage privé, alors que le cheval est un brouillon (jamais bloqué par gwseq_horse_is_publicly_viewable() seule)');
gws_test_assert(strpos($og_html_prive, 'og:title" content="Cheval Prive OG"') !== false, 'Open Graph (privé) : og:title correct');
gws_test_assert(strpos($og_html_prive, 'og:url" content="' . gwseq_horse_private_share_url(63) . '"') !== false, 'Open Graph (privé) : og:url pointe vers le lien PRIVÉ effectivement visité, jamais l’URL publique normale (§4)');
gws_test_assert(strpos($og_html_prive, 'name="robots" content="noindex, nofollow"') !== false, 'Open Graph (privé) : balise noindex systématiquement ajoutée (§16 — jamais utilisée seule comme mécanisme de sécurité, seulement une indication aux moteurs de recherche)');
gws_test_assert(strpos($og_html_prive, '25 000') === false && strpos($og_html_prive, 'gwseq_prix') === false, 'Open Graph (privé) : aucune donnée commerciale/prix, même règle que la route publique');

// --- Sans le partage privé actif (query_var absent), la même fiche brouillon ne produit AUCUN
// Open Graph — bien la route qui fait la différence, pas seulement l'existence d'un token ---
ob_start();
$GLOBALS['__gwseq_test_context'] = array('is_singular' => GWSEQ_CPT_CHEVAL, 'queried_id' => 63, 'query_vars' => array());
gwseq_render_horse_og_meta();
gws_test_assert(ob_get_clean() === '', 'Open Graph : un brouillon consulté HORS de sa route de partage privé (query_var absente) ne produit toujours aucune balise, même si un token existe par ailleurs pour lui');

// --- Ajustement d'architecture : un cheval RÉELLEMENT PUBLIC visité via son ANCIEN lien
// `/partage/{token}` (jamais révoqué automatiquement lors de sa publication) est traité comme la
// fiche PUBLIQUE qu'il est devenu — og:url public, JAMAIS de noindex — cohérent avec
// gwseq_horse_share_fiche_info() où la publication prend toujours le pas sur un token qui traîne ---
$token_61_ancien = gwseq_horse_private_share_activate(61);
gws_test_assert(gwseq_horse_is_publicly_viewable(61) === true, 'Pré-requis : le cheval 61 est bien public à ce stade (republié plus haut)');
ob_start();
$GLOBALS['__gwseq_test_context'] = array('is_singular' => GWSEQ_CPT_CHEVAL, 'queried_id' => 61, 'query_vars' => array('gwseq_partage_token' => $token_61_ancien));
gwseq_render_horse_og_meta();
$og_html_public_via_ancien_lien = ob_get_clean();
gws_test_assert(strpos($og_html_public_via_ancien_lien, 'og:url" content="' . get_permalink(61) . '"') !== false, 'Open Graph : un cheval PUBLIC visité via un ancien lien privé affiche l’URL PUBLIQUE (og:url), jamais l’URL privée — la publication prend le pas sur le token');
gws_test_assert(strpos($og_html_public_via_ancien_lien, 'noindex') === false, 'Open Graph : aucune balise noindex pour un cheval réellement public, même consulté via son ancien lien privé');
gwseq_horse_private_share_revoke(61);

// --- Aucune migration destructive : activer/révoquer un partage privé ne modifie JAMAIS le titre,
// le statut, ni aucune autre donnée du cheval ---
$post_63_avant = get_post(63);
gwseq_horse_private_share_activate(63);
gwseq_horse_private_share_revoke(63);
$post_63_apres = get_post(63);
gws_test_assert($post_63_avant->post_title === $post_63_apres->post_title && $post_63_avant->post_status === $post_63_apres->post_status, 'Partage privé : activer/révoquer ne modifie jamais le titre ni le statut du cheval (aucune migration destructive)');

// --- Aucun second système SEO concurrent : si un plugin SEO est détecté, rien n'est émis ---
// (placé en tout dernier : WPSEO_VERSION est une constante PHP, donc définitive une fois posée —
// tout test Open Graph placé après elle dans ce fichier serait à tort neutralisé.)
define('WPSEO_VERSION', '99.0');
ob_start();
$GLOBALS['__gwseq_test_context'] = array('is_singular' => GWSEQ_CPT_CHEVAL, 'queried_id' => 10);
gwseq_render_horse_og_meta();
gws_test_assert(ob_get_clean() === '', 'Open Graph : rien n’est émis si un plugin SEO tiers est détecté actif (jamais de second système concurrent, §19)');

// --- Exception documentée (§4) : sur la route de partage PRIVÉ précisément, notre balisage reste
// actif MÊME si un plugin SEO tiers est détecté — on ne peut pas garantir qu'il gère correctement
// le noindex/l'absence de prix pour un contexte qu'il ne connaît pas nativement. WPSEO_VERSION est
// déjà défini par le bloc précédent (constante PHP, définitive) : ce test s'exécute donc bien avec
// un plugin SEO "actif" simulé. ---
gws_test_make_horse(64, 'Cheval Prive Malgre Plugin SEO', array('post_status' => 'draft'));
$token_64 = gwseq_horse_private_share_activate(64);
ob_start();
$GLOBALS['__gwseq_test_context'] = array('is_singular' => GWSEQ_CPT_CHEVAL, 'queried_id' => 64, 'query_vars' => array('gwseq_partage_token' => $token_64));
gwseq_render_horse_og_meta();
$og_html_prive_malgre_seo = ob_get_clean();
gws_test_assert($og_html_prive_malgre_seo !== '' && strpos($og_html_prive_malgre_seo, 'noindex') !== false, 'Open Graph : sur la route de partage privé, notre balisage (avec noindex) reste actif MÊME si un plugin SEO tiers est détecté — jamais garanti qu’il connaisse ce contexte spécifique');

echo ($failures === 0 ? 'Tous les tests sont passés.' : "$failures test(s) en échec.") . "\n";
exit($failures === 0 ? 0 : 1);

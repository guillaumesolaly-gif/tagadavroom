<?php
/**
 * Vérifie la présentation éditoriale et les informations complémentaires de la fiche Cheval
 * (Étape 6, §7-8 de la demande) : chaque champ enregistré/lu indépendamment, sanitation stricte
 * (HTML retiré, sauts de ligne conservés), et surtout la séparation stricte entre :
 * - la Production éditoriale (`_gwseq_commentaire_production`, texte libre) et la Production
 *   CALCULÉE (gwseq_get_horse_offspring(), Étape 5, donnée relationnelle jamais stockée) ;
 * - le commentaire Origines éditorial (`_gwseq_origines_commentaire`) et le pedigree STRUCTURÉ
 *   (`_gwseq_pere_*`/`_gwseq_mere_*`, Étape 5).
 * Chemin programmatique sans $_POST ni nonce (même méthodologie que le pedigree).
 *
 * Ne fait pas partie des paquets livrés (gws-core.zip / gws-starter.zip).
 */

$failures = 0;
function gws_test_assert($condition, $label) {
  global $failures;
  if ($condition) { echo "OK   - $label\n"; }
  else { echo "FAIL - $label\n"; $failures++; }
}

// --- Stubs WordPress minimaux ---
function wp_unslash($value) { return is_array($value) ? array_map('wp_unslash', $value) : $value; }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
// Fidèle au comportement réel de sanitize_textarea_field()/wp_strip_all_tags() : le contenu d'une
// balise <script>/<style> est retiré ENTIÈREMENT (pas seulement les délimiteurs de balise, à la
// différence d'un simple strip_tags()) avant de retirer le reste des balises — préserve les sauts
// de ligne (contrairement à sanitize_text_field()), important ici pour des textes libres
// potentiellement multi-lignes (§7 de la demande).
function sanitize_textarea_field($value) {
  $value = (string) $value;
  $value = preg_replace('@<(script|style)[^>]*?>.*?</\1>@si', '', $value);
  return trim(strip_tags($value));
}
function sanitize_key($value) { return strtolower(preg_replace('/[^a-z0-9_\-]/', '', (string) $value)); }
function absint($value) { return abs((int) $value); }
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function esc_textarea($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function wp_nonce_field($action, $field) { echo '<input type="hidden" name="' . esc_attr($field) . '" value="stub-nonce">'; }
function selected($a, $b = true, $echo = true) { $r = $a == $b ? ' selected' : ''; if ($echo) echo $r; return $r; }
function checked($a, $b = true, $echo = true) { $r = $a == $b ? ' checked' : ''; if ($echo) echo $r; return $r; }

$GLOBALS['__gwseq_test_domains_used'] = array();
function __($text, $domain = 'default') { $GLOBALS['__gwseq_test_domains_used'][] = $domain; return $text; }
function esc_html__($text, $domain = 'default') { $GLOBALS['__gwseq_test_domains_used'][] = $domain; return esc_html($text); }
function esc_attr__($text, $domain = 'default') { $GLOBALS['__gwseq_test_domains_used'][] = $domain; return esc_attr($text); }
function esc_html_e($text, $domain = 'default') { echo esc_html__($text, $domain); }
function esc_attr_e($text, $domain = 'default') { echo esc_attr__($text, $domain); }

$GLOBALS['__gwseq_test_registered_meta'] = array();
function register_post_meta($object_type, $meta_key, $args = array()) { $GLOBALS['__gwseq_test_registered_meta'][$meta_key] = $args; }
function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {}
function add_meta_box($id, $title, $callback, $post_type = null, $context = 'advanced', $priority = 'default') { $GLOBALS['__gwseq_test_meta_boxes'][] = $id; }

$GLOBALS['__gwseq_test_meta'] = array();
function update_post_meta($post_id, $key, $value) { $GLOBALS['__gwseq_test_meta'][$post_id][$key] = $value; return true; }
function get_post_meta($post_id, $key, $single = false) { return $GLOBALS['__gwseq_test_meta'][$post_id][$key] ?? ''; }

$GLOBALS['__gwseq_test_security'] = array('nonce_valid' => true, 'can_edit' => true, 'is_revision' => false);
function wp_verify_nonce($nonce, $action) { return $GLOBALS['__gwseq_test_security']['nonce_valid']; }
function current_user_can($cap, $post_id = null) { return $GLOBALS['__gwseq_test_security']['can_edit']; }
function wp_is_post_revision($post_id) { return $GLOBALS['__gwseq_test_security']['is_revision']; }

// --- Lot 2A : mécanisme de notice d'erreur (redirect_post_location + admin_notices) ---
// add_query_arg($key, $value, $url) — même stub que gws-equestrian-cheval-selection-admin-test.php.
function add_query_arg(...$args) {
  if (count($args) === 3) {
    list($key, $value, $url) = $args;
    $params = array($key => $value);
  } else {
    list($params, $url) = $args;
  }
  $sep = strpos($url, '?') === false ? '?' : '&';
  return $url . $sep . http_build_query($params);
}
$GLOBALS['__gwseq_test_screen'] = null;
function get_current_screen() { return $GLOBALS['__gwseq_test_screen']; }
$GLOBALS['__gwseq_test_filters'] = array();
function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) { $GLOBALS['__gwseq_test_filters'][$hook][] = $callback; }
function apply_filters($hook, $value) {
  foreach ($GLOBALS['__gwseq_test_filters'][$hook] ?? array() as $cb) { $value = call_user_func($cb, $value); }
  return $value;
}

function gws_test_strip_php_comments($source) {
  $tokens = token_get_all($source);
  $code = '';
  foreach ($tokens as $token) {
    if (is_array($token) && in_array($token[0], array(T_COMMENT, T_DOC_COMMENT), true)) continue;
    $code .= is_array($token) ? $token[1] : $token;
  }
  return $code;
}

define('ABSPATH', __DIR__ . '/');
const GWSEQ_CPT_CHEVAL = 'gwseq_cheval';
$repo_root = dirname(__DIR__);
$module_dir = $repo_root . '/wp-content/plugins/gws-core/modules/gws-equestrian/';
require $repo_root . '/wp-content/plugins/gws-core/includes/fields.php';
require $module_dir . 'includes/cheval-fields.php';
require $module_dir . 'includes/race-referentiel.php';
require $module_dir . 'includes/cheval-pdf-fields.php';
require $module_dir . 'includes/cheval-editorial.php';

$cheval_editorial_source = file_get_contents($module_dir . 'includes/cheval-editorial.php');
$cheval_editorial_code_only = gws_test_strip_php_comments($cheval_editorial_source);
$cheval_pedigree_source = file_get_contents($module_dir . 'includes/cheval-pedigree.php');
$cheval_pedigree_code_only = gws_test_strip_php_comments($cheval_pedigree_source);
$pedigree_resolver_source = file_get_contents($module_dir . 'includes/pedigree-resolver.php');
$pedigree_resolver_code_only = gws_test_strip_php_comments($pedigree_resolver_source);

// =====================================================================================
// Chaque champ éditorial, enregistré et lu indépendamment (§7 de la demande)
// =====================================================================================

$all_fields = gwseq_cheval_editorial_field_map();
// 8 depuis le correctif recette réelle (retrait de "osteo_articulaire", remplacé par la note en
// étoiles) — auparavant 9 depuis le Lot 2A (retrait de "points_forts", devenu "Qualités" — liste
// structurée, voir plus bas).
gws_test_assert(count($all_fields) === 8, 'Modèle : les 8 champs éditoriaux TEXTE LIBRE attendus sont bien déclarés — ni "points_forts" (Lot 2A) ni "osteo_articulaire" (correctif recette réelle) n’y figurent plus');
gws_test_assert(!array_key_exists('points_forts', $all_fields), 'Modèle : "points_forts" a bien été retiré de gwseq_cheval_editorial_field_map() (Lot 2A — devenu "Qualités", liste structurée)');

// --- Chaque champ peut être enregistré seul, les autres restant vides ---
foreach ($all_fields as $field_key => $meta_key) {
  gws_test_assert(1, 'placeholder'); // no-op pour garder la boucle lisible ; les assertions réelles suivent
}
gwseq_set_cheval_editorial(10, array('_gwseq_presentation' => 'Un beau cheval de sport.'));
$editorial_10 = gwseq_get_cheval_editorial(10);
gws_test_assert($editorial_10['presentation'] === 'Un beau cheval de sport.', 'Champ indépendant : "Présentation" enregistré seul, valeur exacte conservée');
foreach ($all_fields as $field_key => $meta_key) {
  if ($field_key === 'presentation') continue;
  gws_test_assert($editorial_10[$field_key] === '', "Champ indépendant : \"$field_key\" reste vide, jamais affecté par l’enregistrement de \"presentation\" seul");
}

// --- Champ vide accepté : un enregistrement entièrement vide est parfaitement valide (Lot 2A :
// gwseq_set_cheval_editorial() retourne désormais un tableau des champs REJETÉS, vide = succès
// complet, plus jamais un simple booléen `true`) ---
gws_test_assert(gwseq_set_cheval_editorial(11, array()) === array(), 'Champ vide accepté : un enregistrement sans aucun champ renseigné réussit (tous facultatifs), aucun champ rejeté');
foreach (gwseq_get_cheval_editorial(11) as $field_key => $value) {
  gws_test_assert($value === '', "Champ vide accepté : \"$field_key\" reste bien une chaîne vide, jamais une erreur ni une valeur par défaut inventée");
}
gws_test_assert(gwseq_get_cheval_qualites(11) === array() && gwseq_get_cheval_faits_marquants(11) === array(), 'Champ vide accepté : Qualités et Faits marquants restent des tableaux vides, jamais une erreur');

// --- Potentiel, Résultats, Conditions de vente, Conseils de croisement : enregistrement croisé de
// plusieurs champs à la fois ---
gwseq_set_cheval_editorial(12, array(
  '_gwseq_potentiel' => 'Potentiel CSO Amateur.',
  '_gwseq_resultats' => 'Plusieurs podiums en 2024.',
  '_gwseq_conditions_vente' => 'Visite sur rendez-vous uniquement.',
  '_gwseq_conseils_croisement' => 'Se marie bien avec des lignées de sang.',
));
$editorial_12 = gwseq_get_cheval_editorial(12);
gws_test_assert(
  $editorial_12['potentiel'] === 'Potentiel CSO Amateur.'
  && $editorial_12['resultats'] === 'Plusieurs podiums en 2024.'
  && $editorial_12['conditions_vente'] === 'Visite sur rendez-vous uniquement.'
  && $editorial_12['conseils_croisement'] === 'Se marie bien avec des lignées de sang.',
  'Plusieurs champs éditoriaux enregistrés simultanément : chacun conserve exactement sa propre valeur'
);

// --- "Conseils de croisement" disponible pour TOUS les chevaux, jamais conditionné (§7) : aucune
// vérification de sexe/catégorie n'existe dans le fichier avant de proposer ce champ ---
gws_test_assert(strpos($cheval_editorial_code_only, "'_gwseq_sexe'") === false && strpos($cheval_editorial_code_only, '"_gwseq_sexe"') === false, 'Conseils de croisement : aucune lecture du sexe du cheval n’existe dans ce fichier — le champ est proposé à tous, sans condition');

// --- Sanitation correcte : HTML/scripts retirés, texte conservé ---
gwseq_set_cheval_editorial(13, array('_gwseq_presentation' => '<script>alert(1)</script>Un très bon cheval.'));
gws_test_assert(gwseq_get_cheval_editorial(13)['presentation'] === 'Un très bon cheval.', 'Sanitation : une balise <script> est retirée, le reste du texte conservé intact');

// =====================================================================================
// Production éditoriale DISTINCTE de la Production calculée (§7 de la demande)
// =====================================================================================

gwseq_set_cheval_editorial(20, array('_gwseq_commentaire_production' => 'Production très regardée, plusieurs produits primés.'));
gws_test_assert(gwseq_get_cheval_editorial(20)['commentaire_production'] === 'Production très regardée, plusieurs produits primés.', 'Production éditoriale : enregistrée et lue correctement via son propre champ');

// --- Le nom de meta lui-même est sans ambiguïté : jamais "_gwseq_production" tout court, qui
// prêterait à confusion avec la donnée calculée ---
gws_test_assert($all_fields['commentaire_production'] === '_gwseq_commentaire_production', 'Nom de meta explicite : "_gwseq_commentaire_production", jamais un simple "_gwseq_production" ambigu');
gws_test_assert(!array_key_exists('_gwseq_production', $GLOBALS['__gwseq_test_meta'][20] ?? array()), 'Production éditoriale : aucune meta "_gwseq_production" (sans préfixe "commentaire_") n’est jamais créée');

// --- Ce fichier ne lit ni n'écrit jamais la Production calculée (gwseq_get_horse_offspring(),
// définie dans cheval-pedigree.php) — vérification déclarative directe ---
gws_test_assert(strpos($cheval_editorial_code_only, 'gwseq_get_horse_offspring') === false, 'Production éditoriale : ce fichier n’appelle jamais gwseq_get_horse_offspring() (la Production calculée reste exclusivement gérée par cheval-pedigree.php)');
// --- Et réciproquement : cheval-pedigree.php (Production calculée) ne connaît pas ce champ éditorial ---
gws_test_assert(strpos($cheval_pedigree_code_only, '_gwseq_commentaire_production') === false, 'Production calculée : cheval-pedigree.php ne lit ni n’écrit jamais "_gwseq_commentaire_production" — les deux concepts restent complètement indépendants en code');

// =====================================================================================
// Origines éditoriales DISTINCTES du pedigree structuré (§7 de la demande)
// =====================================================================================

gwseq_set_cheval_editorial(21, array('_gwseq_origines_commentaire' => 'Une lignée maternelle reconnue pour sa production de sauteurs.'));
gws_test_assert(gwseq_get_cheval_editorial(21)['origines_commentaire'] === 'Une lignée maternelle reconnue pour sa production de sauteurs.', 'Origines éditoriales : enregistrées et lues correctement via leur propre champ');

// --- Ce fichier ne lit ni n'écrit jamais les meta du pedigree structuré ---
foreach (array('_gwseq_pere_mode', '_gwseq_pere_id', '_gwseq_pere_externe', '_gwseq_mere_mode', '_gwseq_mere_id', '_gwseq_mere_externe') as $pedigree_meta_key) {
  gws_test_assert(strpos($cheval_editorial_code_only, "'" . $pedigree_meta_key . "'") === false, "Origines éditoriales : ce fichier ne lit ni n'écrit jamais la meta de pedigree structuré \"$pedigree_meta_key\"");
}
// --- Et réciproquement : ni cheval-pedigree.php ni le resolver ne connaissent ce commentaire
// éditorial — jamais reconstruit à partir de lui, ni l'inverse ---
gws_test_assert(strpos($cheval_pedigree_code_only, '_gwseq_origines_commentaire') === false, 'Pedigree structuré : cheval-pedigree.php ne lit ni n’écrit jamais "_gwseq_origines_commentaire"');
gws_test_assert(strpos($pedigree_resolver_code_only, '_gwseq_origines_commentaire') === false, 'Resolver : pedigree-resolver.php ne lit ni n’écrit jamais "_gwseq_origines_commentaire" — le pedigree résolu n’est jamais reconstruit à partir de ce texte');

// --- Enregistrer le commentaire Origines ne modifie jamais la relation pedigree, et
// réciproquement (fonctionnel, pas seulement déclaratif) ---
gwseq_set_cheval_editorial(21, array('_gwseq_origines_commentaire' => 'Un commentaire modifié.'));
gws_test_assert(!array_key_exists('_gwseq_pere_mode', $GLOBALS['__gwseq_test_meta'][21] ?? array()), 'Origines éditoriales : enregistrer ce commentaire ne crée ni ne modifie jamais la relation "père" du pedigree structuré');

// =====================================================================================
// Ostéo-articulaire — l'ancien champ texte libre (§8 du Lot initial) est RETIRÉ (correctif recette
// réelle) : remplacé par la note structurée en étoiles, rendue dans includes/cheval-pdf-fields.php
// et affichée depuis includes/cheval-editorial.php (voir plus bas, "Rendu admin"). Jamais un
// dossier vétérinaire structuré, ni ici ni dans son remplaçant.
// =====================================================================================

gws_test_assert(!array_key_exists('osteo_articulaire', gwseq_cheval_editorial_field_map()), 'Ostéo-articulaire : l’ancien champ texte libre "osteo_articulaire" a bien été retiré de gwseq_cheval_editorial_field_map() (correctif recette réelle — remplacé par la note structurée en étoiles)');

// Vérification portant sur le MODÈLE DE DONNÉES (la seule chose qui compte ici) : aucun de ces
// concepts de dossier vétérinaire structuré n'existe comme champ/meta déclaré — mentionner ces
// mots dans un texte d'aide expliquant ce qui est volontairement exclu (ce que fait ce fichier,
// légitimement) est tout autre chose qu'un champ structuré, d'où la vérification sur les CLÉS du
// tableau de champs plutôt que sur le texte brut du fichier.
$editorial_meta_keys = array_values(gwseq_cheval_editorial_field_map());
foreach (array('veterinaire', 'traitement', 'ordonnance', 'radio', 'historique_soin') as $forbidden_concept) {
  $matching_keys = array_filter($editorial_meta_keys, function ($meta_key) use ($forbidden_concept) {
    return strpos($meta_key, $forbidden_concept) !== false;
  });
  gws_test_assert(empty($matching_keys), "Modèle de données : aucun champ structuré de dossier vétérinaire (\"$forbidden_concept\") n’existe — texte libre uniquement, conformément au périmètre volontairement restreint");
}
gws_test_assert(count($editorial_meta_keys) === 8, 'Modèle de données : le modèle éditorial texte libre compte exactement 8 champs déclarés (Accroche commerciale incluse, "points_forts" retiré au Lot 2A, "osteo_articulaire" retiré au correctif recette réelle), aucun ajout non demandé (dossier vétérinaire, etc.)');

// =====================================================================================
// Persistance et compatibilité (§13 de la demande)
// =====================================================================================

// --- Sauvegardes successives sans perte de données ---
gwseq_set_cheval_editorial(30, array('_gwseq_presentation' => 'V1'));
gwseq_set_cheval_editorial(30, array('_gwseq_presentation' => 'V1', '_gwseq_potentiel' => 'V2'));
$editorial_30 = gwseq_get_cheval_editorial(30);
gws_test_assert($editorial_30['presentation'] === 'V1' && $editorial_30['potentiel'] === 'V2', 'Persistance : un second enregistrement complet conserve les champs déjà présents et ajoute le nouveau');

// --- Compatibilité avec une fiche Cheval créée avant l’Étape 6 (jamais enregistrée) ---
foreach (gwseq_get_cheval_editorial(999) as $field_key => $value) {
  gws_test_assert($value === '', "Compatibilité : \"$field_key\" reste vide sur une fiche jamais enregistrée avec ces champs, jamais une erreur");
}

// --- Désactivation/réactivation du module : aucune suppression de meta n'est jamais construite ---
gws_test_assert(strpos($cheval_editorial_code_only, 'delete_post_meta') === false, 'Désactivation/réactivation : ce fichier n’appelle jamais delete_post_meta() — aucune donnée éditoriale ne peut être supprimée par une (dés)activation du module');

// --- Programmatique, sans $_POST ni nonce ---
$import_result = gwseq_set_cheval_editorial(40, array('_gwseq_presentation' => 'Importé depuis un futur CSV.'));
gws_test_assert($import_result === array() && gwseq_get_cheval_editorial(40)['presentation'] === 'Importé depuis un futur CSV.', 'Programmatique : un appel direct (simulant un futur import) enregistre correctement, sans $_POST ni nonce, aucun champ rejeté');

// =====================================================================================
// LOT 2A — Qualités / Faits marquants : gwseq_sanitize_cheval_text_list() (§1-2 de la demande)
// =====================================================================================

// --- (1) Cas nominal : ordre conservé, aucune troncature ---
$result = gwseq_sanitize_cheval_text_list(array('Respect', 'Sang', 'Force', 'Bon galop', 'Équilibre'), GWSEQ_CHEVAL_QUALITES_MAX_ITEMS, GWSEQ_CHEVAL_QUALITES_MAX_LENGTH);
gws_test_assert(!$result['rejected'] && $result['values'] === array('Respect', 'Sang', 'Force', 'Bon galop', 'Équilibre'), '(1) Qualités : 5 valeurs valides acceptées, ordre de saisie strictement conservé');

// --- (2) Entrées vides retirées, jamais comptées dans le total ---
$result = gwseq_sanitize_cheval_text_list(array('Respect', '', '  ', 'Sang'), 10, GWSEQ_CHEVAL_QUALITES_MAX_LENGTH);
gws_test_assert(!$result['rejected'] && $result['values'] === array('Respect', 'Sang'), '(2) Entrées vides (ou uniquement des espaces) retirées de la liste, jamais stockées, jamais comptées comme "un élément"');

// --- (3) Espaces superflus retirés en début/fin ---
$result = gwseq_sanitize_cheval_text_list(array('  Respect  '), 5, 25);
gws_test_assert($result['values'] === array('Respect'), '(3) Espaces superflus en début/fin d’une valeur retirés (trim)');

// --- (4) HTML retiré, jamais stocké tel quel ---
$result = gwseq_sanitize_cheval_text_list(array('<strong>Sang</strong>'), 5, 25);
gws_test_assert($result['values'] === array('Sang'), '(4) Balises HTML retirées à la sanitation (gws_core_field_sanitize(\'text\', ...))');

// --- (5) Une seule entrée trop longue -> REJET DE LA LISTE ENTIÈRE, jamais une troncature à 25
// caractères ni les autres entrées valides silencieusement gardées ---
$trop_long = str_repeat('a', GWSEQ_CHEVAL_QUALITES_MAX_LENGTH + 1);
$result = gwseq_sanitize_cheval_text_list(array('Respect', $trop_long, 'Sang'), GWSEQ_CHEVAL_QUALITES_MAX_ITEMS, GWSEQ_CHEVAL_QUALITES_MAX_LENGTH);
gws_test_assert($result['rejected'] === true && $result['reason'] === 'too_long' && $result['values'] === null, '(5) Une qualité de 26 caractères (limite 25) fait rejeter la LISTE ENTIÈRE — jamais une troncature à 25 caractères, jamais les autres qualités valides gardées seules');

// --- (5bis) Exactement à la limite : accepté ---
$exact = str_repeat('a', GWSEQ_CHEVAL_QUALITES_MAX_LENGTH);
$result = gwseq_sanitize_cheval_text_list(array($exact), GWSEQ_CHEVAL_QUALITES_MAX_ITEMS, GWSEQ_CHEVAL_QUALITES_MAX_LENGTH);
gws_test_assert(!$result['rejected'] && $result['values'] === array($exact), '(5bis) Une qualité de EXACTEMENT 25 caractères est acceptée (limite inclusive)');

// --- (6) Trop d'éléments (au-delà du maximum) -> REJET DE LA LISTE ENTIÈRE ---
$result = gwseq_sanitize_cheval_text_list(array('A', 'B', 'C', 'D', 'E', 'F'), GWSEQ_CHEVAL_QUALITES_MAX_ITEMS, GWSEQ_CHEVAL_QUALITES_MAX_LENGTH);
gws_test_assert($result['rejected'] === true && $result['reason'] === 'too_many' && $result['values'] === null, '(6) 6 qualités soumises (maximum 5) : rejet de la liste entière — jamais les 5 premières gardées, la 6e silencieusement perdue');

// --- (6bis) Exactement au maximum : accepté ---
$result = gwseq_sanitize_cheval_text_list(array('A', 'B', 'C', 'D', 'E'), GWSEQ_CHEVAL_QUALITES_MAX_ITEMS, GWSEQ_CHEVAL_QUALITES_MAX_LENGTH);
gws_test_assert(!$result['rejected'], '(6bis) Exactement 5 qualités (le maximum) est accepté');

// --- (7) Faits marquants : mêmes garanties, bornes différentes (3 éléments, 80 caractères) ---
$result = gwseq_sanitize_cheval_text_list(array(
  'Finaliste Championnat de France 7 ans',
  'Classé CSI4* 1,50 m',
  'Mère de 3 chevaux indicés > 140',
), GWSEQ_CHEVAL_FAITS_MARQUANTS_MAX_ITEMS, GWSEQ_CHEVAL_FAITS_MARQUANTS_MAX_LENGTH);
gws_test_assert(!$result['rejected'] && count($result['values']) === 3, '(7) Faits marquants : les 3 exemples de la demande sont acceptés tels quels');

$trop_long_fait = str_repeat('a', GWSEQ_CHEVAL_FAITS_MARQUANTS_MAX_LENGTH + 1);
$result = gwseq_sanitize_cheval_text_list(array($trop_long_fait), GWSEQ_CHEVAL_FAITS_MARQUANTS_MAX_ITEMS, GWSEQ_CHEVAL_FAITS_MARQUANTS_MAX_LENGTH);
gws_test_assert($result['rejected'] === true && $result['reason'] === 'too_long', '(7bis) Un fait marquant de 81 caractères (limite 80) est rejeté');

// --- (8) Payload malformé : jamais une erreur fatale ---
$result = gwseq_sanitize_cheval_text_list('pas un tableau', 5, 25);
gws_test_assert(!$result['rejected'] && $result['values'] === array(), '(8) Payload entièrement non-tableau : traité comme une liste vide, jamais une erreur');
$result = gwseq_sanitize_cheval_text_list(array('Respect', array('imbriqué')), 5, 25);
gws_test_assert(!$result['rejected'] && $result['values'] === array('Respect'), '(8bis) Une entrée elle-même tableau (payload trafiqué) est ignorée, jamais une erreur fatale');

// =====================================================================================
// LOT 2A — Qualités / Faits marquants : persistance réelle via gwseq_set_cheval_editorial()
// =====================================================================================

// --- (9) Enregistrement valide : lu ensuite via les accesseurs dédiés ---
// NOTE méthodologique : comme pour les champs texte libre existants (déjà documenté avant ce lot —
// "un appel complet est attendu"), gwseq_set_cheval_editorial() traite une clé ABSENTE de $raw
// comme "liste vide soumise", pas comme "ne pas toucher à ce champ" : un formulaire réel soumet
// TOUJOURS les deux listes ensemble (même boîte "Présentation") — mais un test isolant Qualités de
// Faits marquants doit donc utiliser des post_id SÉPARÉS, ou repasser la valeur déjà connue à
// chaque appel, pour ne pas se re-wiper lui-même entre deux appels successifs sur le MÊME cheval.
gwseq_set_cheval_editorial(50, array('_gwseq_qualites' => array('Respect', 'Sang', 'Force')));
gws_test_assert(gwseq_get_cheval_qualites(50) === array('Respect', 'Sang', 'Force'), '(9) Qualités valides enregistrées et relues via gwseq_get_cheval_qualites(), ordre conservé');

gwseq_set_cheval_editorial(55, array('_gwseq_faits_marquants' => array('Finaliste Championnat de France 7 ans')));
gws_test_assert(gwseq_get_cheval_faits_marquants(55) === array('Finaliste Championnat de France 7 ans'), '(9bis) Faits marquants valides enregistrés et relus via gwseq_get_cheval_faits_marquants(), sur une fiche distincte');

// --- (10) Élément trop long soumis (post 50, qui porte déjà Respect/Sang/Force depuis le test 9) :
// rejeté, valeur précédente CONSERVÉE (jamais tronquée, jamais perdue), champ signalé dans le
// tableau retourné avec la bonne raison ---
$rejected = gwseq_set_cheval_editorial(50, array('_gwseq_qualites' => array('Respect', str_repeat('x', 30))));
gws_test_assert(($rejected['qualites'] ?? null) === 'too_long', '(10) Qualités : un élément de 30 caractères (limite 25) est signalé rejeté avec la raison "too_long"');
gws_test_assert(gwseq_get_cheval_qualites(50) === array('Respect', 'Sang', 'Force'), '(10bis) Qualités : la valeur précédemment enregistrée reste EXACTEMENT inchangée après un rejet — jamais tronquée à 25 caractères, jamais partiellement remplacée');

// --- (11) Trop d'éléments soumis sur cette même fiche 50 : rejeté, valeur précédente conservée ---
$rejected = gwseq_set_cheval_editorial(50, array('_gwseq_qualites' => array('A', 'B', 'C', 'D', 'E', 'F')));
gws_test_assert(($rejected['qualites'] ?? null) === 'too_many', '(11) Qualités : 6 éléments soumis (maximum 5) signalés rejetés avec la raison "too_many"');
gws_test_assert(gwseq_get_cheval_qualites(50) === array('Respect', 'Sang', 'Force'), '(11bis) Qualités : la valeur précédente reste inchangée après un rejet pour excès d’éléments');

// --- (12) Un rejet sur Qualités n'affecte JAMAIS Faits marquants (et réciproquement), ni aucun
// autre champ de la même soumission ---
gwseq_set_cheval_editorial(51, array(
  '_gwseq_qualites' => array('A', 'B', 'C', 'D', 'E', 'F'), // rejeté (trop d'éléments)
  '_gwseq_faits_marquants' => array('Classé CSI4* 1,50 m'), // valide
  '_gwseq_presentation' => 'Présentation valide.', // valide
));
gws_test_assert(gwseq_get_cheval_qualites(51) === array(), '(12) Qualités rejetées (jamais enregistrées la première fois) restent vides — aucun effet de bord sur les autres champs');
gws_test_assert(gwseq_get_cheval_faits_marquants(51) === array('Classé CSI4* 1,50 m'), '(12bis) Faits marquants, valides dans la même soumission, sont bien enregistrés malgré le rejet de Qualités');
gws_test_assert(gwseq_get_cheval_editorial(51)['presentation'] === 'Présentation valide.', '(12ter) Présentation, valide dans la même soumission, est bien enregistrée malgré le rejet de Qualités — "une erreur sur un champ ne provoque pas la perte d’autres données"');

// =====================================================================================
// LOT 2A — Limites de longueur des six champs éditoriaux existants (§3 de la demande)
// =====================================================================================

// --- (13) Valeur strictement dans la limite : enregistrée normalement, aucun rejet ---
$ok_180 = str_repeat('a', 180);
$rejected = gwseq_set_cheval_editorial(60, array('_gwseq_accroche_commerciale' => $ok_180));
gws_test_assert($rejected === array(), '(13) Accroche commerciale de EXACTEMENT 180 caractères (la limite) : acceptée, aucun rejet (limite inclusive)');
gws_test_assert(gwseq_get_cheval_editorial(60)['accroche_commerciale'] === $ok_180, '(13bis) La valeur de 180 caractères est enregistrée intégralement, jamais tronquée à 179');

// --- (14) Valeur d'UN caractère au-delà de la limite : REJETÉE, JAMAIS TRONQUÉE (ne devient
// jamais une version coupée à 180 caractères) ---
$trop_181 = str_repeat('a', 181);
$rejected = gwseq_set_cheval_editorial(60, array('_gwseq_accroche_commerciale' => $trop_181));
gws_test_assert(($rejected['accroche_commerciale'] ?? null) === 'too_long', '(14) Accroche commerciale de 181 caractères (1 de trop) : rejetée avec la raison "too_long"');
gws_test_assert(gwseq_get_cheval_editorial(60)['accroche_commerciale'] === $ok_180, '(14bis) AUCUNE TRONCATURE SILENCIEUSE : la valeur reste EXACTEMENT celle de 180 caractères déjà enregistrée — jamais une version de $trop_181 coupée à 180, jamais vidée');

// --- (15) Une soumission COMPLÈTE avec un seul champ en trop (les autres valides) : SEUL le champ
// fautif est rejeté, TOUS les autres champs de CETTE MÊME requête sont enregistrés normalement —
// exactement l'exigence "une erreur sur un champ ne doit pas faire perdre les autres données" ---
$rejected = gwseq_set_cheval_editorial(61, array(
  '_gwseq_accroche_commerciale' => str_repeat('a', 181), // invalide (181 > 180)
  '_gwseq_presentation' => 'Une présentation tout à fait valide.', // valide
  '_gwseq_potentiel' => 'Un potentiel tout à fait valide.', // valide
  '_gwseq_resultats' => 'Des résultats (jamais plafonnés dans ce lot).', // valide, sans limite
));
gws_test_assert(array_keys($rejected) === array('accroche_commerciale'), '(15) Un seul champ trop long dans une soumission de 4 champs : seul ce champ apparaît dans le tableau des rejets');
$editorial_61 = gwseq_get_cheval_editorial(61);
gws_test_assert($editorial_61['accroche_commerciale'] === '', '(15bis) Accroche commerciale rejetée : reste vide (aucune valeur précédente existait), jamais la version de 181 caractères ni une version tronquée');
gws_test_assert(
  $editorial_61['presentation'] === 'Une présentation tout à fait valide.'
  && $editorial_61['potentiel'] === 'Un potentiel tout à fait valide.'
  && $editorial_61['resultats'] === 'Des résultats (jamais plafonnés dans ce lot).',
  '(15ter) Les trois autres champs valides de la MÊME soumission sont bien enregistrés, malgré le rejet du premier — aucune perte de données provoquée par une erreur sur un champ voisin'
);

// --- (16) Chacune des six limites (§3 de la demande), vérifiées individuellement ---
foreach (array(
  'accroche_commerciale' => 180,
  'presentation' => 1200,
  'potentiel' => 500,
  'commentaire_production' => 600,
  'conseils_croisement' => 600,
  'origines_commentaire' => 600,
) as $field_key => $max) {
  $meta_key = $all_fields[$field_key];
  $post_id = 70 + array_search($field_key, array_keys(gwseq_cheval_editorial_field_max_length()), true);
  $rejected = gwseq_set_cheval_editorial($post_id, array($meta_key => str_repeat('a', $max + 1)));
  gws_test_assert(($rejected[$field_key] ?? null) === 'too_long', "(16) \"$field_key\" : une valeur de " . ($max + 1) . " caractères (limite $max) est bien rejetée");
  $rejected_ok = gwseq_set_cheval_editorial($post_id, array($meta_key => str_repeat('a', $max)));
  gws_test_assert($rejected_ok === array(), "(16bis) \"$field_key\" : une valeur d'EXACTEMENT $max caractères est bien acceptée (limite inclusive)");
}

// --- (17) Résultats, Conditions de vente, Ostéo-articulaire : toujours SANS limite dans ce lot ---
$tres_long = str_repeat('a', 5000);
$rejected = gwseq_set_cheval_editorial(80, array('_gwseq_resultats' => $tres_long, '_gwseq_conditions_vente' => $tres_long));
gws_test_assert($rejected === array(), '(17) "Résultats" et "Conditions de vente" acceptent un texte de 5000 caractères sans le moindre rejet — hors périmètre de ce lot, comportement inchangé');
gws_test_assert(gwseq_get_cheval_editorial(80)['resultats'] === $tres_long && gwseq_get_cheval_editorial(80)['conditions_vente'] === $tres_long, '(17bis) Ces deux champs sont bien enregistrés intégralement, sans troncature');

// =====================================================================================
// LOT 2A — Ancien champ "_gwseq_points_forts" : aucune suppression, aucune écriture
// =====================================================================================

// --- (18) Déclaratif : ce fichier n'écrit plus jamais "_gwseq_points_forts" ---
gws_test_assert(strpos($cheval_editorial_code_only, "'_gwseq_points_forts'") === false, '(18) Déclaratif : la chaîne "_gwseq_points_forts" n’apparaît plus nulle part dans le code de ce fichier (ni lue, ni écrite)');

// --- (19) Fonctionnel : une valeur déjà présente en base sous l'ancienne clé n'est JAMAIS
// modifiée ni supprimée par un enregistrement normal (identité de test simulant une fiche de
// recette existante) ---
$GLOBALS['__gwseq_test_meta'][90]['_gwseq_points_forts'] = 'Ancien texte libre de recette, jamais migré.';
gwseq_set_cheval_editorial(90, array(
  '_gwseq_presentation' => 'Nouvelle présentation.',
  '_gwseq_qualites' => array('Respect', 'Sang'),
));
gws_test_assert($GLOBALS['__gwseq_test_meta'][90]['_gwseq_points_forts'] === 'Ancien texte libre de recette, jamais migré.', '(19) L’ancienne meta "_gwseq_points_forts" reste EXACTEMENT intacte après un enregistrement normal — ni supprimée, ni écrasée, simplement ignorée (aucune migration automatique, conformément à la demande)');

// =====================================================================================
// LOT 2A — Mécanisme de notice d'erreur (redirect_post_location + admin_notices, §3)
// =====================================================================================

// --- (20) gwseq_save_cheval_editorial_meta() relaie bien les champs rejetés vers le mécanisme de
// redirection, qui les encode dans l'URL ---
$_POST = array(
  GWSEQ_CHEVAL_NONCE_FIELD => 'stub-nonce',
  '_gwseq_accroche_commerciale' => str_repeat('a', 181),
  '_gwseq_presentation' => 'Une présentation valide.',
);
gwseq_save_cheval_editorial_meta(100);
$redirect_url = gwseq_cheval_editorial_redirect_with_rejected_fields('https://example.test/wp-admin/post.php?post=100&action=edit', 100);
gws_test_assert(strpos($redirect_url, 'gwseq_editorial_rejected=accroche_commerciale%3Atoo_long') !== false, '(20) L’URL de redirection porte bien "gwseq_editorial_rejected=accroche_commerciale:too_long" après un enregistrement avec un champ trop long');
gws_test_assert(gwseq_get_cheval_editorial(100)['presentation'] === 'Une présentation valide.', '(20bis) Le champ valide de la même soumission (Présentation) est bien enregistré malgré le rejet de l’Accroche');

// --- (20ter) Aucun champ rejeté : l'URL de redirection n'est jamais modifiée ---
$_POST = array(GWSEQ_CHEVAL_NONCE_FIELD => 'stub-nonce', '_gwseq_presentation' => 'Tout va bien.');
gwseq_save_cheval_editorial_meta(101);
$redirect_url_ok = gwseq_cheval_editorial_redirect_with_rejected_fields('https://example.test/wp-admin/post.php?post=101&action=edit', 101);
gws_test_assert($redirect_url_ok === 'https://example.test/wp-admin/post.php?post=101&action=edit', '(20ter) Aucun champ rejeté : l’URL de redirection reste strictement inchangée (aucun paramètre ajouté)');

// --- (21) Le message d'erreur affiché est compréhensible : nomme le champ, la raison, et précise
// que la valeur précédente est conservée ---
$GLOBALS['__gwseq_test_screen'] = (object) array('post_type' => GWSEQ_CPT_CHEVAL);
$_GET['gwseq_editorial_rejected'] = 'accroche_commerciale:too_long,qualites:too_many';
ob_start();
gwseq_cheval_editorial_rejected_admin_notice();
$notice_html = ob_get_clean();
gws_test_assert(strpos($notice_html, 'notice-error') !== false, '(21) Le message d’erreur utilise bien la classe standard WordPress "notice-error"');
gws_test_assert(strpos($notice_html, 'Accroche commerciale') !== false, '(21bis) Le message nomme précisément "Accroche commerciale" (jamais un message générique)');
gws_test_assert(strpos($notice_html, '180') !== false, '(21ter) Le message précise la limite exacte dépassée (180 caractères)');
gws_test_assert(strpos($notice_html, 'Qualités') !== false, '(21quater) Le message nomme également "Qualités", second champ rejeté dans cette même redirection');
gws_test_assert(strpos($notice_html, '5') !== false, '(21quinquies) Le message précise le maximum de 5 éléments pour Qualités');
gws_test_assert(strpos($notice_html, 'conservée') !== false, '(21sexies) Le message précise explicitement que la version précédente a été conservée — jamais laisser croire à une perte de données');
unset($_GET['gwseq_editorial_rejected']);
$GLOBALS['__gwseq_test_screen'] = null;

// --- (22) La notice ne s'affiche jamais en dehors de l'écran d'édition Cheval, même si le
// paramètre est présent dans l'URL (ex. copié/collé) ---
$GLOBALS['__gwseq_test_screen'] = (object) array('post_type' => 'post');
$_GET['gwseq_editorial_rejected'] = 'accroche_commerciale:too_long';
ob_start();
gwseq_cheval_editorial_rejected_admin_notice();
$notice_html_wrong_screen = ob_get_clean();
gws_test_assert($notice_html_wrong_screen === '', '(22) Aucune notice affichée sur un écran autre que l’édition d’une fiche Cheval, même avec le paramètre présent dans l’URL');
unset($_GET['gwseq_editorial_rejected']);
$GLOBALS['__gwseq_test_screen'] = null;

// =====================================================================================
// Rendu admin et i18n
// =====================================================================================

$post_stub = (object) array('ID' => 12);
$GLOBALS['__gwseq_test_meta_boxes'] = array();
gwseq_add_cheval_editorial_meta_boxes();
gws_test_assert(in_array('gwseq-cheval-presentation', $GLOBALS['__gwseq_test_meta_boxes'], true), 'Meta box "Présentation" : bien enregistrée');
gws_test_assert(!in_array('gwseq-cheval-infos-complementaires', $GLOBALS['__gwseq_test_meta_boxes'], true), 'Meta box "Informations complémentaires" : retirée (correctif recette réelle) — ne contenait plus que l’ancien champ Ostéo-articulaire texte libre, lui-même retiré');

ob_start();
gwseq_render_cheval_presentation_box($post_stub);
$presentation_box_html = ob_get_clean();
foreach (array('_gwseq_accroche_commerciale', '_gwseq_presentation', '_gwseq_potentiel', '_gwseq_resultats', '_gwseq_origines_commentaire', '_gwseq_commentaire_production', '_gwseq_conditions_vente', '_gwseq_conseils_croisement') as $meta_key) {
  gws_test_assert(strpos($presentation_box_html, 'name="' . $meta_key . '"') !== false, "Rendu admin : le champ $meta_key est réellement rendu dans la meta box Présentation");
}
gws_test_assert(strpos($presentation_box_html, 'name="_gwseq_points_forts"') === false, 'Rendu admin : "_gwseq_points_forts" (ancien champ texte libre) n’est plus jamais rendu — remplacé par "Qualités" (Lot 2A)');
gws_test_assert(strpos($presentation_box_html, 'name="_gwseq_osteo_articulaire"') === false, 'Rendu admin : l’ancien champ texte libre "Ostéo-articulaire" n’est plus jamais rendu nulle part (correctif recette réelle)');

// --- Correctif recette réelle : Statut ostéo-articulaire (étoiles)/Stud-books/WFFS déplacés
// depuis la boîte « Fiche PDF » et rendus ICI, dans la boîte Présentation ---
gws_test_assert(strpos($presentation_box_html, 'name="_gwseq_statut_osteo_articulaire"') !== false, 'Rendu admin : le statut ostéo-articulaire (notation en étoiles) est bien rendu dans la meta box Présentation');
gws_test_assert(substr_count($presentation_box_html, 'name="_gwseq_statut_osteo_articulaire"') === 6, 'Rendu admin : 6 boutons radio (5 étoiles + "Non renseigné"), aucune option de note supprimée');
gws_test_assert(strpos($presentation_box_html, 'value="0"') !== false, 'Rendu admin : une valeur vide ("Non renseigné", 0) reste bien possible pour le statut ostéo-articulaire');
gws_test_assert(strpos($presentation_box_html, 'name="_gwseq_studbooks_approbation[]"') !== false, 'Rendu admin : les stud-books d’approbation sont bien rendus dans la meta box Présentation');
gws_test_assert(strpos($presentation_box_html, 'type="checkbox"') !== false, 'Rendu admin : les stud-books se sélectionnent désormais via des cases à cocher (jamais un <select multiple> nécessitant Ctrl/Cmd)');
gws_test_assert(strpos($presentation_box_html, '<select') === false || strpos($presentation_box_html, 'multiple') === false, 'Rendu admin : aucun <select multiple> natif ne subsiste pour les stud-books');
gws_test_assert(strpos($presentation_box_html, 'name="_gwseq_wffs"') !== false, 'Rendu admin : le WFFS est bien rendu dans la meta box Présentation');

// --- Lot 2A : maxlength HTML natif présent sur les six champs à limite fixe, avec la valeur
// exacte de gwseq_cheval_editorial_field_max_length() — jamais un nombre différent codé ailleurs ---
foreach (gwseq_cheval_editorial_field_max_length() as $field_key => $max) {
  $meta_key = $all_fields[$field_key];
  gws_test_assert(strpos($presentation_box_html, 'name="' . $meta_key . '" maxlength="' . $max . '"') !== false, "Rendu admin : le champ \"$field_key\" porte bien maxlength=\"$max\" (confort de saisie client)");
}
// --- Résultats/Conditions de vente : aucun maxlength (hors périmètre du Lot 2A) ---
foreach (array('_gwseq_resultats', '_gwseq_conditions_vente') as $meta_key) {
  gws_test_assert(strpos($presentation_box_html, 'name="' . $meta_key . '" maxlength') === false, "Rendu admin : \"$meta_key\" ne porte aucun maxlength — champ volontairement laissé sans limite dans ce lot");
}

// --- Lot 2A : Qualités et Faits marquants rendus comme des listes ordonnées (name="...[]"),
// jamais comme un champ texte libre unique ---
gws_test_assert(strpos($presentation_box_html, 'name="_gwseq_qualites[]"') !== false, 'Rendu admin : "Qualités" rend bien un champ répété "_gwseq_qualites[]"');
gws_test_assert(strpos($presentation_box_html, 'name="_gwseq_faits_marquants[]"') !== false, 'Rendu admin : "Faits marquants" rend bien un champ répété "_gwseq_faits_marquants[]"');
gws_test_assert(strpos($presentation_box_html, 'maxlength="' . GWSEQ_CHEVAL_QUALITES_MAX_LENGTH . '"') !== false, 'Rendu admin : le champ de saisie d’une qualité porte maxlength="25"');
gws_test_assert(strpos($presentation_box_html, 'maxlength="' . GWSEQ_CHEVAL_FAITS_MARQUANTS_MAX_LENGTH . '"') !== false, 'Rendu admin : le champ de saisie d’un fait marquant porte maxlength="80"');
gws_test_assert(strpos($presentation_box_html, '(maximum 5)') !== false, 'Rendu admin : le maximum de 5 qualités est indiqué à l’écran');
gws_test_assert(strpos($presentation_box_html, '(maximum 3)') !== false, 'Rendu admin : le maximum de 3 faits marquants est indiqué à l’écran');
gws_test_assert(strpos($presentation_box_html, 'gwseq-text-list__add') !== false, 'Rendu admin : un bouton d’ajout est bien présent pour les listes structurées');
gws_test_assert(strpos($presentation_box_html, 'gwseq-text-list__move-up') !== false && strpos($presentation_box_html, 'gwseq-text-list__move-down') !== false, 'Rendu admin : des contrôles de réordonnancement (haut/bas) sont bien présents');
gws_test_assert(strpos($presentation_box_html, 'gwseq-text-list__remove') !== false, 'Rendu admin : un bouton de suppression est bien présent par ligne');
gws_test_assert(substr_count($presentation_box_html, 'class="gwseq-text-list__template"') === 2, 'Rendu admin : un gabarit <template> pour l’ajout côté JS, une fois par liste structurée (Qualités + Faits marquants)');

// --- Escaping : un contenu avec balise n'est jamais rendu tel quel dans le HTML du formulaire,
// ni pour un champ texte libre, ni pour un élément d'une liste structurée (Lot 2A) ---
gwseq_set_cheval_editorial(12, array('_gwseq_presentation' => '</textarea><script>alert(1)</script>'));
ob_start();
gwseq_render_cheval_presentation_box($post_stub);
$escaped_html = ob_get_clean();
gws_test_assert(strpos($escaped_html, '<script>') === false, 'Escaping admin (champ texte libre) : un contenu contenant une balise n’est jamais injecté tel quel dans le rendu (esc_textarea())');

$GLOBALS['__gwseq_test_meta'][13]['_gwseq_qualites'] = array('"><script>alert(1)</script>');
ob_start();
gwseq_render_cheval_presentation_box((object) array('ID' => 13));
$escaped_list_html = ob_get_clean();
gws_test_assert(strpos($escaped_list_html, '<script>') === false, 'Escaping admin (Qualités) : un contenu contenant une balise n’est jamais injecté tel quel dans le rendu (esc_attr())');

foreach ($GLOBALS['__gwseq_test_domains_used'] as $domain) {
  gws_test_assert($domain === 'gws-core', "i18n : aucun appel de traduction n’utilise un text domain autre que \"gws-core\" (trouvé : $domain)");
}

echo ($failures === 0 ? 'Tous les tests sont passés.' : "$failures test(s) en échec.") . "\n";
exit($failures === 0 ? 0 : 1);

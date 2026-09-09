<?php
/**
 * Vérifie les fonctions PURES du renderer de fiche cheval PDF (Lot "PDF Cheval & Catalogue",
 * Lot 3A — audit + prototype) : sélection/tri de la Production affichée, formatage d'une ligne de
 * Production, assemblage des données (gwseq_build_horse_pdf_data()), et les utilitaires génériques
 * du moteur PDF partagé de gws-core (includes/pdf-engine.php — conversion de couleurs, ajustement
 * d'image dans une boîte).
 *
 * Ne teste JAMAIS le rendu visuel réel (TCPDF) : ce fichier n'exige pas que
 * `composer install --no-dev` ait été exécuté sur cet environnement (vendor/ gitignoré, voir
 * wp-content/plugins/gws-core/composer.json) — un test qui l'exigerait casserait le déroulement
 * normal `git clone && php tests/*.php` sans étape supplémentaire. Un bloc dédié, à la fin de ce
 * fichier, exécute un smoke-test de rendu réel UNIQUEMENT si la bibliothèque est disponible
 * (gws_core_pdf_available()) — jamais un échec si elle ne l'est pas, seulement une ligne
 * d'information. La recette VISUELLE réelle (mise en page, branding, lisibilité) a été faite
 * séparément via un harnais de prototype dédié (scratchpad, non livré) produisant 3 vrais PDF —
 * voir le CR de ce lot.
 *
 * Ne fait pas partie des paquets livrés (gws-core.zip / gws-starter.zip).
 */

$failures = 0;
function gws_test_assert($condition, $label) {
  global $failures;
  if ($condition) { echo "OK   - $label\n"; } else { echo "FAIL - $label\n"; $failures++; }
}

// --- Stubs WordPress minimaux (mêmes conventions que le reste de ce dossier) ---
function wp_unslash($value) { return is_array($value) ? array_map('wp_unslash', $value) : stripslashes((string) $value); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function sanitize_textarea_field($value) { return trim((string) $value); }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value)); }
function esc_url_raw($value) { $value = trim((string) $value); return $value === '' ? '' : $value; }
function esc_url($value) { return $value; }
function absint($value) { return abs((int) $value); }
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function wp_http_validate_url($url) { return $url; }
function __($text, $domain = 'default') { return $text; }
function _n($single, $plural, $number, $domain = 'default') { return $number == 1 ? $single : $plural; }
function esc_html__($text, $domain = 'default') { return esc_html($text); }
function esc_html_e($text, $domain = 'default') { echo esc_html__($text, $domain); }
function register_post_meta($object_type, $meta_key, $args = array()) {}
function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {}
function apply_filters($hook, $value) { return $value; }
function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {}
function add_meta_box(...$args) {}
function add_image_size(...$args) {}
function wp_parse_args($args, $defaults) { return is_array($args) ? array_merge($defaults, $args) : $defaults; }
function get_bloginfo($key = '') { return 'Site GWS Test'; }
function wp_json_encode($data, $options = 0, $depth = 512) { return json_encode($data, $options, $depth); }

$GLOBALS['__test_options'] = array();
function get_option($key, $default = false) { return $GLOBALS['__test_options'][$key] ?? $default; }
function update_option($key, $value, $autoload = null) { $GLOBALS['__test_options'][$key] = $value; return true; }

$GLOBALS['__test_posts'] = array();
$GLOBALS['__test_meta'] = array();
function gws_test_make_post($id, $post_type, $title, $status = 'publish') {
  $GLOBALS['__test_posts'][$id] = array('post_type' => $post_type, 'post_status' => $status, 'post_title' => $title);
}
function gws_test_make_post_object($id) {
  $p = $GLOBALS['__test_posts'][$id];
  return (object) array('ID' => $id, 'post_type' => $p['post_type'], 'post_status' => $p['post_status'], 'post_title' => $p['post_title']);
}
function get_post_type($post_id) { return $GLOBALS['__test_posts'][$post_id]['post_type'] ?? false; }
function get_post($post_id) { return isset($GLOBALS['__test_posts'][$post_id]) ? gws_test_make_post_object($post_id) : null; }
function get_the_title($post) {
  $id = is_object($post) ? $post->ID : $post;
  return $GLOBALS['__test_posts'][$id]['post_title'] ?? '';
}
function update_post_meta($post_id, $key, $value) { $GLOBALS['__test_meta'][$post_id][$key] = $value; return true; }
function get_post_meta($post_id, $key, $single = false) { return $GLOBALS['__test_meta'][$post_id][$key] ?? ''; }
function delete_post_meta($post_id, $key) { unset($GLOBALS['__test_meta'][$post_id][$key]); return true; }
function metadata_exists($type, $post_id, $key) { return array_key_exists($key, $GLOBALS['__test_meta'][$post_id] ?? array()); }

function gws_test_meta_query_matches($post_id, $clause) {
  if (isset($clause['key'])) return (string) get_post_meta($post_id, $clause['key'], true) === (string) $clause['value'];
  $relation = strtoupper($clause['relation'] ?? 'AND');
  $subclauses = array_filter($clause, function ($k) { return is_int($k); }, ARRAY_FILTER_USE_KEY);
  foreach ($subclauses as $sub) {
    $match = gws_test_meta_query_matches($post_id, $sub);
    if ($relation === 'OR' && $match) return true;
    if ($relation === 'AND' && !$match) return false;
  }
  return $relation === 'AND';
}
function get_posts($args = array()) {
  $post_type = $args['post_type'] ?? 'post';
  $statuses = isset($args['post_status']) ? (array) $args['post_status'] : array('publish');
  $exclude = isset($args['exclude']) ? array_map('intval', (array) $args['exclude']) : array();
  $meta_query = $args['meta_query'] ?? null;
  $results = array();
  foreach ($GLOBALS['__test_posts'] as $id => $post) {
    if ($post['post_type'] !== $post_type) continue;
    if (!in_array($post['post_status'], $statuses, true)) continue;
    if (in_array((int) $id, $exclude, true)) continue;
    if ($meta_query && !gws_test_meta_query_matches($id, $meta_query)) continue;
    $results[] = gws_test_make_post_object($id);
  }
  return $results;
}

$GLOBALS['__test_attachments'] = array();
function gws_test_register_attachment($id, $path, $url) { $GLOBALS['__test_attachments'][$id] = array('path' => $path, 'url' => $url); }
function get_attached_file($id) { return $GLOBALS['__test_attachments'][$id]['path'] ?? false; }
function wp_get_attachment_image_url($id, $size = 'full') { return $GLOBALS['__test_attachments'][$id]['url'] ?? false; }
function get_post_thumbnail_id($post_id) { return (int) get_post_meta($post_id, '_thumbnail_id', true); }

if (!defined('MINUTE_IN_SECONDS')) define('MINUTE_IN_SECONDS', 60);
define('ABSPATH', __DIR__ . '/');
const GWSEQ_CPT_CHEVAL = 'gwseq_cheval';
define('GWSEQ_MODULE_URL', 'https://example.test/');
define('GWSEQ_MODULE_VERSION', 'test');

$repo_root = dirname(__DIR__);
define('GWS_CORE_DIR', $repo_root . '/wp-content/plugins/gws-core/');
$module_dir = $repo_root . '/wp-content/plugins/gws-core/modules/gws-equestrian/';

require $repo_root . '/wp-content/plugins/gws-core/includes/fields.php';
require $repo_root . '/wp-content/plugins/gws-core/includes/settings.php';
require $repo_root . '/wp-content/plugins/gws-core/includes/pdf-engine.php';
require $module_dir . 'includes/settings.php';
require $module_dir . 'includes/race-referentiel.php';
require $module_dir . 'includes/cheval-fields.php';
require $module_dir . 'includes/pedigree-resolver.php';
require $module_dir . 'includes/cheval-pedigree.php';
require $module_dir . 'includes/cheval-indices.php';
require $module_dir . 'includes/cheval-media.php';
require $module_dir . 'includes/cheval-editorial.php';
require $module_dir . 'includes/ifce-production-store.php';
require $module_dir . 'includes/cheval-pdf.php';

// =====================================================================================
// 1. gws_core_pdf_hex_to_rgb() / gws_core_pdf_lighten_color() (includes/pdf-engine.php) — pures.
// =====================================================================================

gws_test_assert(gws_core_pdf_hex_to_rgb('#1d4ed8') === array(29, 78, 216), 'gws_core_pdf_hex_to_rgb() : conversion exacte pour la couleur GWS par défaut');
gws_test_assert(gws_core_pdf_hex_to_rgb('pas-une-couleur') === array(0, 0, 0), 'gws_core_pdf_hex_to_rgb() : repli sur le noir pour une valeur invalide (garde défensive)');
gws_test_assert(gws_core_pdf_lighten_color('#1d4ed8', 0) === array(29, 78, 216), 'gws_core_pdf_lighten_color() : amount=0 -> couleur d’origine inchangée');
gws_test_assert(gws_core_pdf_lighten_color('#1d4ed8', 1) === array(255, 255, 255), 'gws_core_pdf_lighten_color() : amount=1 -> blanc pur');
$mid = gws_core_pdf_lighten_color('#000000', 0.5);
gws_test_assert($mid[0] === 128 || $mid[0] === 127, 'gws_core_pdf_lighten_color() : amount=0.5 sur le noir -> proche du gris moyen (127-128)');

// =====================================================================================
// 2. gws_core_pdf_fit_image_box() — pure, aucun accès TCPDF, uniquement getimagesize().
// =====================================================================================

gws_test_assert(gws_core_pdf_fit_image_box('/chemin/inexistant.jpg', 50, 50) === null, 'gws_core_pdf_fit_image_box() : chemin illisible -> null, jamais une erreur (§19, "fallback si aucune image")');

// =====================================================================================
// 3. gwseq_horse_pdf_best_sport_value() — meilleure valeur ISO/ICC/IDR.
// =====================================================================================

gws_test_assert(gwseq_horse_pdf_best_sport_value(array('iso' => array('valeur' => 120), 'icc' => array('valeur' => 145), 'idr' => array('valeur' => 100))) === 145.0, 'Meilleur indice sportif : ICC 145 retenu comme le plus élevé des trois');
gws_test_assert(gwseq_horse_pdf_best_sport_value(array()) === null, 'Meilleur indice sportif : aucun indice -> null (jamais une valeur inventée)');
gws_test_assert(gwseq_horse_pdf_best_sport_value(array('iso' => array('valeur' => ''))) === null, 'Meilleur indice sportif : valeur vide -> ignorée, null au global si c’est la seule');

// =====================================================================================
// 4. gwseq_horse_pdf_production_line() — direction de design §8 : "Nom (Père) · Année · ISO Valeur".
// =====================================================================================

$line_full = gwseq_horse_pdf_production_line(array('nom' => 'Vaillant de Félines', 'pere' => 'Pegase Gerbaux', 'annee' => 2009, 'iso' => array('valeur' => 118), 'icc' => array('valeur' => ''), 'idr' => array('valeur' => '')));
gws_test_assert($line_full === 'Vaillant de Félines (Pegase Gerbaux) · 2009 · ISO 118', 'Ligne de Production complète : format exact conforme à la direction de design');

$line_no_pere = gwseq_horse_pdf_production_line(array('nom' => 'Espoir de Félines', 'pere' => '', 'annee' => 2017, 'iso' => array('valeur' => ''), 'icc' => array('valeur' => ''), 'idr' => array('valeur' => '')));
gws_test_assert($line_no_pere === 'Espoir de Félines · 2017', 'Ligne de Production : père absent -> segment omis proprement, jamais "()"');

$line_bare = gwseq_horse_pdf_production_line(array('nom' => 'Sans Donnee', 'pere' => '', 'annee' => '', 'iso' => array('valeur' => ''), 'icc' => array('valeur' => ''), 'idr' => array('valeur' => '')));
gws_test_assert($line_bare === 'Sans Donnee', 'Ligne de Production : ni père, ni année, ni indice -> juste le nom, jamais un séparateur orphelin');

$line_best_of_three = gwseq_horse_pdf_production_line(array('nom' => 'Multi Indices', 'pere' => '', 'annee' => 2015, 'iso' => array('valeur' => 100), 'icc' => array('valeur' => 130), 'idr' => array('valeur' => 90)));
gws_test_assert(strpos($line_best_of_three, 'ICC 130') !== false && strpos($line_best_of_three, 'ISO') === false && strpos($line_best_of_three, 'IDR') === false, 'Ligne de Production : SEUL le meilleur indice (ICC 130) est affiché, jamais les trois');

gws_test_assert(strpos(json_encode(gwseq_horse_pdf_production_line(array('nom' => 'X', 'pere' => '', 'annee' => '', 'iso' => array('valeur' => ''), 'icc' => array('valeur' => ''), 'idr' => array('valeur' => ''), 'bso' => array('valeur' => 999)))), 'bso') === false, 'Ligne de Production (§3/§8) : le BLUP (bso/bcc/bdr) d’un produit n’est jamais lu ni affiché, même présent dans l’entrée');

// =====================================================================================
// 5. gwseq_horse_pdf_select_production_entries() — tri par indice, non-indicés en dernier, ordre
//    d'affichage final = ordre d'origine (direction de design §8).
// =====================================================================================

$entries = array(
  array('nom' => 'A', 'iso' => array('valeur' => 100)),
  array('nom' => 'B', 'iso' => array('valeur' => '')), // non indicé
  array('nom' => 'C', 'iso' => array('valeur' => 150)), // meilleur
  array('nom' => 'D', 'iso' => array('valeur' => 80)), // le plus faible indicé
);
$selected_all = gwseq_horse_pdf_select_production_entries($entries, 10);
gws_test_assert(count($selected_all) === 4, 'Sélection Production : aucune entrée perdue quand $max n’est pas dépassé');
gws_test_assert(array_column($selected_all, 'nom') === array('A', 'B', 'C', 'D'), 'Sélection Production : ordre d’affichage final = ordre d’origine du document, jamais un tri visible par indice');

$selected_trimmed = gwseq_horse_pdf_select_production_entries($entries, 2);
$kept_names = array_column($selected_trimmed, 'nom');
gws_test_assert(count($selected_trimmed) === 2, 'Sélection Production (limite dépassée) : exactement $max entrées conservées');
gws_test_assert(in_array('C', $kept_names, true) && in_array('A', $kept_names, true), 'Sélection Production (limite dépassée) : les DEUX mieux indicés (C=150, A=100) sont conservés');
gws_test_assert(!in_array('B', $kept_names, true), 'Sélection Production (limite dépassée) : le produit NON INDICÉ (B) est retiré en premier, avant même le plus faible indicé (D)');
gws_test_assert($kept_names === array('A', 'C'), 'Sélection Production (limite dépassée) : ordre d’affichage final toujours = ordre d’origine (A avant C), même après un tri de sélection par indice');

$only_non_indexed = gwseq_horse_pdf_select_production_entries(array(
  array('nom' => 'X', 'iso' => array('valeur' => '')),
  array('nom' => 'Y', 'iso' => array('valeur' => '')),
), 10);
gws_test_assert(array_column($only_non_indexed, 'nom') === array('X', 'Y'), 'Sélection Production : deux produits non indicés -> ordre d’origine préservé entre eux (tri stable)');

// =====================================================================================
// 6. gwseq_build_horse_pdf_data() — assemblage, sans TCPDF.
// =====================================================================================

gws_test_assert(gwseq_build_horse_pdf_data(0) === null, 'gwseq_build_horse_pdf_data() : ID invalide (0) -> null');
gws_test_assert(gwseq_build_horse_pdf_data(999999) === null, 'gwseq_build_horse_pdf_data() : ID inexistant -> null');

gws_test_make_post(50, 'post', 'Un article, pas un cheval');
gws_test_assert(gwseq_build_horse_pdf_data(50) === null, 'gwseq_build_horse_pdf_data() : un post d’un AUTRE type (pas gwseq_cheval) -> null, jamais une fiche pour le mauvais contenu');

gws_test_make_post(60, GWSEQ_CPT_CHEVAL, 'Cheval Complet PDF Test');
gwseq_set_cheval_identity(60, array('_gwseq_sexe' => 'female', '_gwseq_annee_naissance' => '2015', '_gwseq_robe' => 'bai', '_gwseq_race' => 'sf', '_gwseq_sire' => '12345678A'));
update_post_meta(60, '_gwseq_statut_commercial', 'for_sale');
update_post_meta(60, '_gwseq_prix_mode', 'fixed');
update_post_meta(60, '_gwseq_prix_fixe', 15000);
gwseq_set_cheval_sport_indice(60, 'iso', array('valeur' => 130));

$data60 = gwseq_build_horse_pdf_data(60);
gws_test_assert($data60 !== null && $data60['name'] === 'Cheval Complet PDF Test', 'gwseq_build_horse_pdf_data() : nom correctement assemblé');
gws_test_assert($data60['sexe_label'] === 'Femelle', 'gwseq_build_horse_pdf_data() : libellé sexe correctement résolu');
gws_test_assert($data60['race_label'] !== '', 'gwseq_build_horse_pdf_data() : libellé race correctement résolu via le référentiel (jamais dupliqué)');
gws_test_assert($data60['price_summary'] !== '' && strpos($data60['price_summary'], '15') !== false, 'gwseq_build_horse_pdf_data() : résumé de prix réutilise bien gwseq_cheval_price_summary() existant');
gws_test_assert(array_key_exists('iso', $data60['sport_indices']) && !array_key_exists('icc', $data60['sport_indices']), 'gwseq_build_horse_pdf_data() : seuls les indices RÉELLEMENT renseignés apparaissent (ICC absent, jamais une valeur vide)');
gws_test_assert(is_array($data60['structure']) && array_key_exists('primary_color', $data60['structure']), 'gwseq_build_horse_pdf_data() : branding assemblé via gws_core_structure_identity() (aucune donnée dupliquée)');
gws_test_assert($data60['photo_path'] === '', 'gwseq_build_horse_pdf_data() : aucune photo principale définie -> chemin vide, jamais une erreur');

// --- Garde de sexe (§3/§8 de la demande) : jamais de bloc Production pour un mâle/hongre, même si
// gwseq_get_horse_direct_production() était un jour appelée par erreur sur un mâle ---
gws_test_make_post(61, GWSEQ_CPT_CHEVAL, 'Etalon PDF Test');
gwseq_set_cheval_identity(61, array('_gwseq_sexe' => 'male', '_gwseq_annee_naissance' => '2012'));
$data61 = gwseq_build_horse_pdf_data(61);
gws_test_assert($data61['production'] === array(), 'gwseq_build_horse_pdf_data() (§3/§8) : Production structurellement vide pour un mâle, garde de sexe appliquée à l’assemblage lui-même');

// --- Branding par défaut vs personnalisé (§2 de la demande) — jamais dupliqué, juste consommé ---
$GLOBALS['__test_options']['gws_core_settings'] = array(); // aucune personnalisation
$data_default_branding = gwseq_build_horse_pdf_data(60);
gws_test_assert($data_default_branding['structure']['primary_color'] === gws_core_default_primary_color(), 'gwseq_build_horse_pdf_data() : couleur principale PAR DÉFAUT de Core utilisée quand aucune couleur personnalisée n’est renseignée (§2 de la demande)');

$GLOBALS['__test_options']['gws_core_settings'] = array('primary_color' => '#7a1f2b', 'secondary_color' => '#b08d4f', 'entity_name' => 'Haras Test');
$data_custom_branding = gwseq_build_horse_pdf_data(60);
gws_test_assert($data_custom_branding['structure']['primary_color'] === '#7a1f2b' && $data_custom_branding['structure']['name'] === 'Haras Test', 'gwseq_build_horse_pdf_data() : couleur/nom personnalisés de "Ma structure" bien répercutés sans duplication');

// =====================================================================================
// 7. Smoke-test de rendu réel — UNIQUEMENT si la bibliothèque PDF est disponible (voir docblock de
//    fichier). Jamais un échec de la suite si vendor/ n'a pas été installé sur cet environnement.
// =====================================================================================

if (gws_core_pdf_available()) {
  $pdf = gwseq_generate_horse_pdf(60);
  gws_test_assert($pdf !== null, 'Smoke-test rendu réel (TCPDF disponible) : gwseq_generate_horse_pdf() ne lève aucune exception pour une fiche complète');
  if ($pdf !== null) {
    $bytes = $pdf->Output('', 'S');
    gws_test_assert(strpos($bytes, '%PDF-') === 0, 'Smoke-test rendu réel : la sortie commence bien par l’en-tête PDF standard ("%PDF-")');
    gws_test_assert(strlen($bytes) > 500, 'Smoke-test rendu réel : la sortie n’est pas un fichier PDF vide/tronqué');
  }
  $pdf_minimal = gwseq_generate_horse_pdf(61); // mâle, quasiment aucune donnée annexe
  gws_test_assert($pdf_minimal !== null, 'Smoke-test rendu réel : gwseq_generate_horse_pdf() ne lève aucune exception pour une fiche à données minimales (§18)');
  echo "INFO - Bibliothèque PDF disponible (vendor/ installé) : smoke-test de rendu réel exécuté.\n";
} else {
  echo "INFO - Bibliothèque PDF non disponible (vendor/ non installé sur cet environnement, voir composer.json) : smoke-test de rendu réel ignoré, sans échec. Recette visuelle réelle documentée séparément au CR du lot.\n";
}

echo ($failures === 0 ? 'Tous les tests sont passés.' : "$failures test(s) en échec.") . "\n";
exit($failures === 0 ? 0 : 1);

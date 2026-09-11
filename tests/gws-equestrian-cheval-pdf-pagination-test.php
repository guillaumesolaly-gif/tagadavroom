<?php
/**
 * Non-régression pagination adaptative (Lot "PDF Cheval & Catalogue" — correctif recette réelle
 * "Kado de Félines") : une fiche Étalon dense (photo + galerie, naisseur, 2 indices, 4 qualités,
 * 3 lignes « à retenir », pedigree complet à 6 ascendants, Présentation + Conseil de croisement,
 * Reproduction, Conditions de monte en une seule ligne) doit tenir sur UNE SEULE page A4 — avec
 * Conditions de monte, le pied de page et le QR code sur cette même page, jamais renvoyée sur une
 * page 2 quasiment vide pour un reliquat de quelques millimètres (voir gwseq_etalon_hero_identity(),
 * gwseq_etalon_hero(), gwseq_etalon_tree(), gwseq_etalon_section() dans cheval-pdf.php — paramètre
 * `$squeeze`, essayé en mode compact avant toute création de page 2).
 *
 * Contenu reconstruit à partir du PDF réel fourni par le client en recette (texte exact, couleur
 * d'en-tête échantillonnée) — voir le CR du lot pour le détail. UNIQUEMENT si la bibliothèque PDF
 * est disponible (gws_core_pdf_available()) : jamais un échec si vendor/ n'a pas été installé sur
 * cet environnement (même garde que gws-equestrian-cheval-pdf-test.php).
 *
 * Photos de test générées à la volée via GD (voir gws_test_make_placeholder_jpeg() ci-dessous) —
 * jamais un fichier binaire commité dans ce dossier.
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
function wp_attachment_is_image($id) { return isset($GLOBALS['__test_attachments'][$id]); }

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
require $module_dir . 'includes/cheval-pdf-fields.php';
require $module_dir . 'includes/cheval-pdf.php';

if (!gws_core_pdf_available()) {
  echo "INFO - Bibliothèque PDF non disponible (vendor/ non installé sur cet environnement, voir composer.json) : test de non-régression pagination ignoré, sans échec.\n";
  echo "Tous les tests sont passés.\n";
  exit(0);
}

/**
 * Génère un JPEG de test à la volée (jamais un fichier binaire commité dans ce dossier) — seules
 * les dimensions comptent pour ce test (mise sous tension du budget vertical du hero avec
 * photo+galerie), jamais le contenu visuel de l'image.
 */
function gws_test_make_placeholder_jpeg($width, $height) {
  $path = tempnam(sys_get_temp_dir(), 'gwseq-test-photo-') . '.jpg';
  $image = imagecreatetruecolor($width, $height);
  imagefill($image, 0, 0, imagecolorallocate($image, 90, 80, 65));
  imagejpeg($image, $path, 80);
  imagedestroy($image);
  return $path;
}

// URL publique factice (jamais un accès réseau réel — write2DBarcode() encode n'importe quelle
// chaîne localement) : force la présence du QR dans le budget du pied de page, exactement comme
// dans la fiche réelle du client (pied de page PLUS haut avec QR que sans) — condition la plus
// exigeante pour ce test de non-régression.
function gwseq_horse_share_fiche_url($cheval_id) {
  return 'https://haras-de-felines.test/cheval/' . $cheval_id . '/';
}

$photo_main = gws_test_make_placeholder_jpeg(1200, 800);
$photo_thumb = gws_test_make_placeholder_jpeg(1200, 800);
register_shutdown_function(function () use ($photo_main, $photo_thumb) {
  @unlink($photo_main);
  @unlink($photo_thumb);
});

/* =====================================================================================
 * « Ma structure » — couleur turquoise échantillonnée sur le PDF réel fourni par le client.
 * ================================================================================== */

update_option('gws_core_settings', array(
  'entity_name' => 'Tagada Vroom',
  'primary_color' => '#16b5d8',
  'secondary_color' => '#0f766e',
  'website_url' => 'www.tagadavroom.fr',
  'public_email' => 'hello@tagadavroom.fr',
  'phone_display' => '0621167239',
));

function gws_test_make_pedigree($subject_id, $subject_year, $father_name, $father_year, $mother_name, $mother_year, $father_race, $ffather, $ffather_race, $fmother, $mother_race, $mfather, $mmother, $mmother_race, $base_id) {
  $id = $base_id;
  gws_test_make_post($id, GWSEQ_CPT_CHEVAL, $father_name);
  gwseq_set_cheval_identity($id, array('_gwseq_sexe' => 'male', '_gwseq_annee_naissance' => (string) $father_year, '_gwseq_race' => $father_race));
  gwseq_set_horse_parent($subject_id, 'father', array('mode' => 'gws', 'horse_id' => $id));
  if ($ffather !== null) gwseq_set_horse_parent($id, 'father', array('mode' => 'external', 'external' => array('name' => $ffather, 'race' => $ffather_race)));
  if ($fmother !== null) gwseq_set_horse_parent($id, 'mother', array('mode' => 'external', 'external' => array('name' => $fmother, 'race' => '')));
  $id++;
  gws_test_make_post($id, GWSEQ_CPT_CHEVAL, $mother_name);
  gwseq_set_cheval_identity($id, array('_gwseq_sexe' => 'female', '_gwseq_annee_naissance' => (string) $mother_year, '_gwseq_race' => $mother_race));
  gwseq_set_horse_parent($subject_id, 'mother', array('mode' => 'gws', 'horse_id' => $id));
  if ($mfather !== null) gwseq_set_horse_parent($id, 'father', array('mode' => 'external', 'external' => array('name' => $mfather, 'race' => 'bwp')));
  if ($mmother !== null) gwseq_set_horse_parent($id, 'mother', array('mode' => 'external', 'external' => array('name' => $mmother, 'race' => $mmother_race)));
  return $id + 1;
}

/* =====================================================================================
 * KADO DE FELINES — reconstruction fidèle du cas réel de recette (texte exact du PDF fourni).
 * ================================================================================== */

gws_test_register_attachment(701, $photo_main, 'https://example.test/kado-principale.jpg');
gws_test_register_attachment(702, $photo_thumb, 'https://example.test/kado-vignette-1.jpg');
gws_test_register_attachment(703, $photo_thumb, 'https://example.test/kado-vignette-2.jpg');
gws_test_make_post(700, GWSEQ_CPT_CHEVAL, 'Kado de Felines');
$GLOBALS['__test_meta'][700]['_thumbnail_id'] = 701;
gwseq_set_cheval_galerie(700, array(702, 703));
gwseq_set_cheval_identity(700, array(
  '_gwseq_sexe' => 'male',
  '_gwseq_annee_naissance' => '2020',
  '_gwseq_robe' => 'bai',
  '_gwseq_race' => 'sf',
  '_gwseq_taille_cm' => '169',
  '_gwseq_eleveur' => 'S.a.s. Haras De Felines',
));
gwseq_set_cheval_sport_indice(700, 'iso', array('valeur' => 125));
gwseq_set_cheval_genetic_indice(700, 'bso', array('valeur' => 20));
gwseq_set_cheval_editorial(700, array(
  '_gwseq_presentation' => "Depuis l'âge de deux ans, Kado démontre une grande facilité et un sens de la barre remarquable, réunissant toutes les qualités recherchées chez un cheval de sport moderne.",
  '_gwseq_conseils_croisement' => "Kado sera un choix idéal pour apporter technique, chic et force aux juments. Ses premiers produits séduisent déjà par leur modèle harmonieux, avec une belle sortie d'encolure et un dos fort et tonique. Le détail qui fait la différence : des poulains expressifs, dotés de très belles têtes, apportant chic et distinction.",
  '_gwseq_conditions_vente' => "IAC : 250 € HT à la réservation (hors frais d'envoi) + 550 € HT au poulain vivant à 48 h.",
  '_gwseq_qualites' => array('Force', 'Souplesse', 'Mental', 'Respect'),
  '_gwseq_faits_marquants' => array(
    '5e place du championnat des étalons Selle Français de deux ans à Saint-Lô',
    'Finaliste à Fontainebleau, il termine 14e du championnat des mâles et hongres',
    '8 sans-faute sur 12 en Cycle classique 5 ans',
  ),
));
gwseq_set_cheval_statut_osteo_articulaire(700, 3);
gwseq_set_cheval_studbooks_approbation(700, array('AMHR', 'AWR', 'AA'));
gws_test_make_pedigree(700, 2020, 'Conthargos', 2008, 'Ja Barones', 2009, 'old', 'Converter', 'old', 'Cajandra Z Z', 'kwpn', "Vigo D'Arsouilles", 'Barones RV', 'kwpn', 750);

$pdf_kado = gwseq_generate_horse_pdf(700);
gws_test_assert($pdf_kado !== null, 'Kado de Félines : génération sans exception');

if ($pdf_kado !== null) {
  // Compression désactivée UNIQUEMENT pour ce test, après coup (le rendu est déjà entièrement
  // composé) : rend les flux de contenu directement lisibles dans les octets de sortie, pour
  // vérifier par une recherche de texte simple que Conditions de monte/pied de page/QR sont bien
  // sur la même page — sans dépendre d'une bibliothèque d'extraction PDF tierce dans ce dépôt.
  $pdf_kado->setCompression(false);
  $bytes = $pdf_kado->Output('', 'S');

  $page_count = preg_match_all('/\/Type\s*\/Page[^s]/', $bytes);
  gws_test_assert($page_count === 1, "Kado de Félines : tient sur UNE SEULE page A4 (régression de recette réelle — obtenu : $page_count page(s))");

  gws_test_assert(strpos($bytes, 'CONTHARGOS') !== false, 'Kado de Félines : le nom du père de pedigree est fidèle à la donnée saisie ("Conthargos"), jamais transformé par le renderer (mb_strtoupper() uniquement)');
  gws_test_assert(strpos($bytes, 'CONTHAROGOS') === false, 'Kado de Félines : jamais la coquille historique "Contharogos" (corrigée en recette)');
  gws_test_assert(strpos($bytes, 'CONDITIONS DE MONTE') !== false, 'Kado de Félines : le bloc CONDITIONS DE MONTE est bien présent dans le PDF généré');
  gws_test_assert(strpos($bytes, "IAC") !== false, 'Kado de Félines : le contenu réel de Conditions de monte est bien composé (jamais tronqué)');
  gws_test_assert(strpos($bytes, 'Tagada Vroom') !== false, 'Kado de Félines : le pied de page (nom de structure) est bien présent');
  gws_test_assert(strpos($bytes, 'hello@tagadavroom.fr') !== false, 'Kado de Félines : les coordonnées du pied de page sont bien présentes');
}

echo ($failures === 0 ? 'Tous les tests sont passés.' : "$failures test(s) en échec.") . "\n";
exit($failures === 0 ? 0 : 1);

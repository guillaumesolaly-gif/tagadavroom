<?php
/**
 * Vérifie l'import IFCE (Étape 7 de la demande) : extraction de texte PDF (mécanique minimale,
 * PDF synthétique auto-généré), reconnaissance/analyse du texte vers une structure normalisée
 * (fixture texte reproduisant exactement l'exemple Jamerose de Félines fourni dans la demande),
 * mapping vers les fonctions métier existantes (jamais un accès direct aux post meta), et contrôle
 * déclaratif de la glue d'administration (sécurité du téléversement, aucune écriture avant
 * validation, ascendants toujours externes, aucun PDF conservé).
 *
 * N'a PAS pu être validé contre un PDF IFCE réel (aucun accès réseau pour en télécharger un) — le
 * pipeline d'extraction PDF est testé contre un PDF minimal auto-généré pour ce fichier ; la
 * reconnaissance/l'analyse du texte est testée séparément contre une fixture texte reproduisant
 * fidèlement l'exemple fourni dans la demande. Voir le CR de livraison pour le détail de cette
 * limitation assumée.
 *
 * Ne fait pas partie des paquets livrés (gws-core.zip / gws-starter.zip).
 */

$failures = 0;
function gws_test_assert($condition, $label) {
  global $failures;
  if ($condition) { echo "OK   - $label\n"; }
  else { echo "FAIL - $label\n"; $failures++; }
}

// --- Stubs WordPress minimaux (mêmes conventions que les autres tests de ce dossier) ---
function wp_unslash($value) { return is_array($value) ? array_map('wp_unslash', $value) : stripslashes((string) $value); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function sanitize_textarea_field($value) { return trim((string) $value); }
function sanitize_key($value) { return strtolower(preg_replace('/[^a-z0-9_\-]/', '', (string) $value)); }
function esc_url_raw($value) { $value = trim((string) $value); return $value === '' ? '' : $value; }
function esc_url($value) { return $value; }
function absint($value) { return abs((int) $value); }
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function selected($a, $b) { return $a == $b ? ' selected' : ''; }
function checked($a, $b = true) { return $a == $b ? ' checked' : ''; }
function disabled($a, $b = true, $echo = true) { $r = $a == $b ? ' disabled' : ''; if ($echo) echo $r; return $r; }
function wp_nonce_field($action, $field) { echo '<input type="hidden" name="' . esc_attr($field) . '" value="stub-nonce">'; }
function wp_json_encode($data, $options = 0, $depth = 512) { return json_encode($data, $options, $depth); }
function submit_button($text = '', $type = 'primary', $name = 'submit', $wrap = true) { echo '<button>' . esc_html($text) . '</button>'; }
function metadata_exists($type, $post_id, $key) { return array_key_exists($key, $GLOBALS['__gwseq_test_meta'][$post_id] ?? array()); }

function remove_accents($text) {
  $map = array(
    'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ã' => 'a', 'å' => 'a',
    'À' => 'A', 'Â' => 'A', 'Ä' => 'A', 'Á' => 'A', 'Ã' => 'A', 'Å' => 'A',
    'ç' => 'c', 'Ç' => 'C',
    'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
    'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
    'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
    'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
    'ñ' => 'n', 'Ñ' => 'N',
    'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
    'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Ö' => 'O', 'Õ' => 'O',
    'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
    'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U',
    'ý' => 'y', 'ÿ' => 'y', 'Ý' => 'Y',
    'œ' => 'oe', 'Œ' => 'OE', 'æ' => 'ae', 'Æ' => 'AE',
  );
  return strtr($text, $map);
}

$GLOBALS['__gwseq_test_domains_used'] = array();
function __($text, $domain = 'default') { $GLOBALS['__gwseq_test_domains_used'][] = $domain; return $text; }
function _n($single, $plural, $number, $domain = 'default') { $GLOBALS['__gwseq_test_domains_used'][] = $domain; return $number == 1 ? $single : $plural; }
function esc_html__($text, $domain = 'default') { $GLOBALS['__gwseq_test_domains_used'][] = $domain; return esc_html($text); }
function esc_attr__($text, $domain = 'default') { $GLOBALS['__gwseq_test_domains_used'][] = $domain; return esc_attr($text); }
function esc_html_e($text, $domain = 'default') { echo esc_html__($text, $domain); }
function esc_attr_e($text, $domain = 'default') { echo esc_attr__($text, $domain); }

$GLOBALS['__gwseq_test_registered_meta'] = array();
function register_post_meta($object_type, $meta_key, $args = array()) { $GLOBALS['__gwseq_test_registered_meta'][$meta_key] = $args; }
function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {}
function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {}
function add_submenu_page($parent, $title, $menu_title, $capability, $slug, $callback) {
  $GLOBALS['__gwseq_test_submenu_pages'][] = compact('parent', 'title', 'menu_title', 'capability', 'slug');
}
$GLOBALS['__gwseq_test_submenu_pages'] = array();

// --- "Base de données" en mémoire : posts, meta, transients ---
$GLOBALS['__gwseq_test_posts'] = array();
$GLOBALS['__gwseq_test_meta'] = array();
$GLOBALS['__gwseq_test_transients'] = array();
$GLOBALS['__gwseq_test_next_post_id'] = 1000;

function gws_test_make_post($id, $post_type, $title, $status = 'publish') {
  $GLOBALS['__gwseq_test_posts'][$id] = array('post_type' => $post_type, 'post_status' => $status, 'post_title' => $title);
}
function get_post_type($post_id) { return $GLOBALS['__gwseq_test_posts'][$post_id]['post_type'] ?? false; }
function get_post($post_id) { return isset($GLOBALS['__gwseq_test_posts'][$post_id]) ? (object) array('ID' => $post_id) + $GLOBALS['__gwseq_test_posts'][$post_id] : null; }
function get_the_title($post) {
  $id = is_object($post) ? $post->ID : $post;
  return $GLOBALS['__gwseq_test_posts'][$id]['post_title'] ?? '';
}
function get_edit_post_link($post_id, $context = 'display') { return 'https://example.test/wp-admin/post.php?post=' . (int) $post_id . '&action=edit'; }
function get_posts($args = array()) { return array(); }

function update_post_meta($post_id, $key, $value) { $GLOBALS['__gwseq_test_meta'][$post_id][$key] = $value; return true; }
function get_post_meta($post_id, $key, $single = false) { return $GLOBALS['__gwseq_test_meta'][$post_id][$key] ?? ''; }
function delete_post_meta($post_id, $key) { unset($GLOBALS['__gwseq_test_meta'][$post_id][$key]); return true; }

function set_transient($key, $value, $ttl) { $GLOBALS['__gwseq_test_transients'][$key] = $value; return true; }
function get_transient($key) { return $GLOBALS['__gwseq_test_transients'][$key] ?? false; }
function delete_transient($key) { unset($GLOBALS['__gwseq_test_transients'][$key]); return true; }

function get_current_user_id() { return $GLOBALS['__gwseq_test_current_user_id'] ?? 1; }
function wp_generate_password($length = 32, $special = true, $extra_special = false) { return $GLOBALS['__gwseq_test_next_token'] ?? bin2hex(random_bytes(16)); }
function admin_url($path = '') { return 'https://example.test/wp-admin/' . $path; }
function add_query_arg($args, $url) { return $url . '?' . http_build_query($args); }

function wp_insert_post($postarr, $wp_error = false) {
  $id = $GLOBALS['__gwseq_test_next_post_id']++;
  gws_test_make_post($id, $postarr['post_type'], $postarr['post_title'], $postarr['post_status'] ?? 'draft');
  return $id;
}
function is_wp_error($thing) { return $thing instanceof WP_Error; }
class WP_Error {}

$GLOBALS['__gwseq_test_security'] = array('nonce_valid' => true, 'can_edit' => true, 'is_revision' => false);
function wp_verify_nonce($nonce, $action) { return $GLOBALS['__gwseq_test_security']['nonce_valid']; }
function current_user_can($cap, $post_id = null) { return $GLOBALS['__gwseq_test_security']['can_edit']; }
function wp_is_post_revision($post_id) { return $GLOBALS['__gwseq_test_security']['is_revision']; }
function check_admin_referer($action, $field) { return $GLOBALS['__gwseq_test_security']['nonce_valid']; }
function wp_die($message = '') { throw new Exception('wp_die: ' . (is_string($message) ? $message : 'error')); }
function wp_safe_redirect($url) { $GLOBALS['__gwseq_test_last_redirect'] = $url; }

if (!defined('MINUTE_IN_SECONDS')) define('MINUTE_IN_SECONDS', 60);
define('ABSPATH', __DIR__ . '/');
const GWSEQ_CPT_CHEVAL = 'gwseq_cheval';
define('GWSEQ_MODULE_URL', 'https://example.test/wp-content/plugins/gws-core/modules/gws-equestrian/');
define('GWSEQ_MODULE_VERSION', 'test');

$repo_root = dirname(__DIR__);
$module_dir = $repo_root . '/wp-content/plugins/gws-core/modules/gws-equestrian/';
require $repo_root . '/wp-content/plugins/gws-core/includes/fields.php';
require $module_dir . 'includes/settings.php';
require $module_dir . 'includes/cheval-fields.php';
require $module_dir . 'includes/pedigree-resolver.php';
require $module_dir . 'includes/cheval-pedigree.php';
require $module_dir . 'includes/cheval-indices.php';
require $module_dir . 'includes/ifce-pdf-text.php';
require $module_dir . 'includes/ifce-import-parser.php';
require $module_dir . 'includes/ifce-import-mapper.php';
require $module_dir . 'includes/ifce-import-admin.php';

$ifce_pdf_text_source = file_get_contents($module_dir . 'includes/ifce-pdf-text.php');
$ifce_parser_source = file_get_contents($module_dir . 'includes/ifce-import-parser.php');
$ifce_mapper_source = file_get_contents($module_dir . 'includes/ifce-import-mapper.php');
$ifce_admin_source = file_get_contents($module_dir . 'includes/ifce-import-admin.php');

/**
 * Retire les commentaires PHP (docblocs et lignes) d'un code source, pour ne jamais confondre une
 * mention en documentation (ex. "n'appelle jamais update_post_meta()") avec un appel réellement
 * exécuté — même principe que les vérifications déclaratives déjà utilisées ailleurs dans ce
 * dossier de tests (voir tests/gws-equestrian-cheval-indices-logic-test.php).
 */
function gws_test_strip_php_comments($source) {
  $code = '';
  foreach (token_get_all($source) as $token) {
    if (is_array($token) && in_array($token[0], array(T_COMMENT, T_DOC_COMMENT), true)) continue;
    $code .= is_array($token) ? $token[1] : $token;
  }
  return $code;
}

$ifce_mapper_code_only = gws_test_strip_php_comments($ifce_mapper_source);
$ifce_admin_code_only = gws_test_strip_php_comments($ifce_admin_source);

// =====================================================================================
// 1. Extraction de texte PDF (mécanique minimale) — §3/§11 de la demande
// =====================================================================================

function gws_test_pdf_escape($s) {
  return str_replace(array('\\', '(', ')'), array('\\\\', '\\(', '\\)'), $s);
}

/**
 * Construit un PDF minimal — un seul objet contenant un flux de contenu affichant $lines (une par
 * Tj/T*) — suffisant pour gwseq_ifce_extract_pdf_text_from_string(), qui ne s'appuie que sur les
 * blocs stream...endstream, jamais sur une table xref/trailer complète.
 */
function gws_test_build_minimal_pdf($lines, $use_flate = true) {
  $content = "BT /F1 12 Tf 72 780 Td\n";
  foreach ($lines as $line) {
    $content .= '(' . gws_test_pdf_escape($line) . ") Tj T*\n";
  }
  $content .= 'ET';

  $stream_bytes = $use_flate ? gzcompress($content, 6) : $content;
  $filter_entry = $use_flate ? ' /Filter /FlateDecode' : '';
  return "%PDF-1.4\n5 0 obj\n<< /Length " . strlen($stream_bytes) . $filter_entry . " >>\nstream\n" . $stream_bytes . "\nendstream\nendobj\n";
}

$jamerose_pdf_lines_ascii = array(
  'FICHE DE SYNTHESE - IFCE',
  'JAMEROSE DE FELINES',
  'Selle Francais, Femelle, Gris, 1m68, nee en 2019',
  'Naisseur : Haras de Felines',
  'SIRE : 05123456A',
  'ISO 115 (0.70) (2023)',
  'BSO +12 (0.59)',
  'Genealogie',
  'UNTOUCHABLE', 'HORS LA LOI II', 'PAPILLON ROUGE', 'ARIANE DU PLESSIS II',
  'PROMESSE', 'HEARTBREAKER', 'CHABLIS',
  'NATIVE DE FELINES', 'ROSIRE', 'URIEL', 'EOLIENNE',
  'FALINE GENEVRIS', 'PEGASE GERBAUX', 'LOUVE VARFEUIL',
);

// --- Flux compressé FlateDecode (cas réel le plus courant) ---
$pdf_bytes_flate = gws_test_build_minimal_pdf($jamerose_pdf_lines_ascii, true);
$extracted_flate = gwseq_ifce_extract_pdf_text_from_string($pdf_bytes_flate);
gws_test_assert(strpos($extracted_flate, 'JAMEROSE DE FELINES') !== false, 'Extraction PDF (FlateDecode) : le nom du cheval est bien retrouvé après décompression zlib');
gws_test_assert(strpos($extracted_flate, 'ISO 115 (0.70) (2023)') !== false, 'Extraction PDF (FlateDecode) : une chaîne contenant des parenthèses échappées est décodée correctement');
gws_test_assert(strpos($extracted_flate, 'UNTOUCHABLE') !== false && strpos($extracted_flate, 'LOUVE VARFEUIL') !== false, 'Extraction PDF (FlateDecode) : les 14 lignes de généalogie sont bien présentes dans le texte extrait');

// --- Flux non compressé (sans /FlateDecode) : toléré tel quel ---
$pdf_bytes_raw = gws_test_build_minimal_pdf(array('SANS COMPRESSION'), false);
gws_test_assert(strpos(gwseq_ifce_extract_pdf_text_from_string($pdf_bytes_raw), 'SANS COMPRESSION') !== false, 'Extraction PDF : un flux non compressé (sans /FlateDecode) reste exploité tel quel');

// --- Robustesse : entrée non-PDF, flux corrompu, fichier illisible ---
gws_test_assert(gwseq_ifce_extract_pdf_text_from_string('ceci n’est pas un PDF') === '', 'Extraction PDF : une entrée sans en-tête "%PDF-" renvoie une chaîne vide, jamais une erreur');
gws_test_assert(gwseq_ifce_extract_pdf_text_from_string('') === '', 'Extraction PDF : une entrée vide renvoie une chaîne vide');
gws_test_assert(gwseq_ifce_extract_pdf_text('/chemin/inexistant.pdf') === '', 'Extraction PDF : un chemin de fichier illisible renvoie une chaîne vide, jamais une erreur fatale');
$corrupted_pdf = "%PDF-1.4\n5 0 obj\n<< /Length 10 /Filter /FlateDecode >>\nstream\nCECI N'EST PAS DU ZLIB\nendstream\nendobj\n";
gws_test_assert(gwseq_ifce_extract_pdf_text_from_string($corrupted_pdf) === '', 'Extraction PDF : un flux annoncé FlateDecode mais corrompu est ignoré proprement, jamais une erreur fatale');

// --- Échappement des chaînes littérales PDF ---
gws_test_assert(gwseq_ifce_decode_pdf_literal_string('a\\(b\\)c') === 'a(b)c', 'Décodage chaîne PDF : parenthèses échappées');
gws_test_assert(gwseq_ifce_decode_pdf_literal_string('a\\nb') === "a\nb", 'Décodage chaîne PDF : saut de ligne échappé (\\n)');
gws_test_assert(gwseq_ifce_decode_pdf_literal_string('a\\\\b') === 'a\\b', 'Décodage chaîne PDF : antislash échappé');

// =====================================================================================
// 2. Reconnaissance et analyse du texte — fixture reproduisant l'exemple Jamerose de Félines
//    fourni dans la demande (§4-6, avec accents réels pour valider la robustesse des expressions
//    régulières indépendamment de la limitation d'encodage du PDF)
// =====================================================================================

$jamerose_text = implode("\n", array(
  'FICHE DE SYNTHESE - IFCE',
  'JAMEROSE DE FELINES',
  'Selle Français, Femelle, Gris, 1m68, née en 2019',
  'Naisseur : Haras de Félines',
  'SIRE : 05123456A',
  'UELN : 250012345678901',
  'ISO 115 (0.70) (2023)',
  'ICC 108 (0.65) (2022)',
  'BSO +12 (0.59)',
  'BCC -3 (0.45)',
  'Généalogie',
  'UNTOUCHABLE', 'HORS LA LOI II', 'PAPILLON ROUGE', 'ARIANE DU PLESSIS II',
  'PROMESSE', 'HEARTBREAKER', 'CHABLIS',
  'NATIVE DE FELINES', 'ROSIRE', 'URIEL', 'EOLIENNE',
  'FALINE GENEVRIS', 'PEGASE GERBAUX', 'LOUVE VARFEUIL',
));

$jamerose_parsed = gwseq_ifce_parse_text($jamerose_text);
gws_test_assert($jamerose_parsed['valid'] === true, 'Reconnaissance : la fixture Jamerose est bien reconnue comme une fiche IFCE');

$identity = $jamerose_parsed['identity'];
gws_test_assert($identity['nom'] === 'JAMEROSE DE FELINES', 'Identité : nom exact');
gws_test_assert($identity['race'] === 'selle_francais' && $identity['race_autre'] === '', 'Identité : race reconnue et mappée au code canonique "selle_francais"');
gws_test_assert($identity['sexe'] === 'female', 'Identité : sexe "Femelle" mappé à "female"');
gws_test_assert($identity['robe'] === 'gris', 'Identité : robe "Gris" mappée au code canonique');
gws_test_assert($identity['taille_cm'] === 168, 'Identité : taille "1m68" convertie en 168 cm');
gws_test_assert($identity['annee_naissance'] === 2019, 'Identité : année de naissance exacte');
gws_test_assert($identity['eleveur'] === 'Haras de Félines', 'Identité : naisseur/éleveur exact (avec accents préservés)');
gws_test_assert($identity['sire'] === '05123456A', 'Identité : numéro SIRE exact');
gws_test_assert($identity['ueln'] === '250012345678901', 'Identité : UELN exact');

$indices = $jamerose_parsed['indices'];
gws_test_assert($indices['iso']['valeur'] === 115 && $indices['iso']['cd'] === 0.7 && $indices['iso']['annee'] === 2023, 'Indices : ISO 115 (CD 0.70) (2023) — exemple exact de la demande, valeur/CD/année stockés séparément');
gws_test_assert($indices['icc']['valeur'] === 108 && $indices['icc']['cd'] === 0.65 && $indices['icc']['annee'] === 2022, 'Indices : ICC également reconnu indépendamment de l’ISO');
gws_test_assert($indices['idr']['valeur'] === '', 'Indices : IDR absent du texte -> resté vide, jamais deviné');
gws_test_assert($indices['bso']['valeur'] === 12.0 && $indices['bso']['cd'] === 0.59, 'Indices : BSO +12 (CD 0.59) — exemple exact de la demande, sans année (indice génétique)');
gws_test_assert($indices['bcc']['valeur'] === -3.0 && $indices['bcc']['cd'] === 0.45, 'Indices : BCC négatif reconnu avec son signe');
gws_test_assert($indices['bdr']['valeur'] === '', 'Indices : BDR absent du texte -> resté vide');
gws_test_assert(!array_key_exists('annee', $indices['bso']), 'Indices : aucune clé "annee" n’existe pour un indice génétique (jamais confondu avec un indice sportif)');

$pedigree = $jamerose_parsed['pedigree'];
gws_test_assert($pedigree['count'] === 14, 'Pedigree : exactement 14 ascendants détectés, comme dans l’exemple de la demande');

$father = $pedigree['father'];
$mother = $pedigree['mother'];
gws_test_assert($father['name'] === 'UNTOUCHABLE', 'Pedigree : Père exact (UNTOUCHABLE)');
gws_test_assert($father['father']['name'] === 'HORS LA LOI II', 'Pedigree : Père du Père exact (HORS LA LOI II)');
gws_test_assert($father['father']['father']['name'] === 'PAPILLON ROUGE', 'Pedigree : Père du Père du Père exact (PAPILLON ROUGE)');
gws_test_assert($father['father']['mother']['name'] === 'ARIANE DU PLESSIS II', 'Pedigree : Mère du Père du Père exacte (ARIANE DU PLESSIS II)');
gws_test_assert($father['mother']['name'] === 'PROMESSE', 'Pedigree : Mère du Père exacte (PROMESSE)');
gws_test_assert($father['mother']['father']['name'] === 'HEARTBREAKER', 'Pedigree : Père de la Mère du Père exact (HEARTBREAKER)');
gws_test_assert($father['mother']['mother']['name'] === 'CHABLIS', 'Pedigree : Mère de la Mère du Père exacte (CHABLIS)');
gws_test_assert($mother['name'] === 'NATIVE DE FELINES', 'Pedigree : Mère exacte (NATIVE DE FELINES)');
gws_test_assert($mother['father']['name'] === 'ROSIRE', 'Pedigree : Père de la Mère exact (ROSIRE)');
gws_test_assert($mother['father']['father']['name'] === 'URIEL', 'Pedigree : Père du Père de la Mère exact (URIEL)');
gws_test_assert($mother['father']['mother']['name'] === 'EOLIENNE', 'Pedigree : Mère du Père de la Mère exacte (EOLIENNE)');
gws_test_assert($mother['mother']['name'] === 'FALINE GENEVRIS', 'Pedigree : Mère de la Mère exacte (FALINE GENEVRIS)');
gws_test_assert($mother['mother']['father']['name'] === 'PEGASE GERBAUX', 'Pedigree : Père de la Mère de la Mère exact (PEGASE GERBAUX)');
gws_test_assert($mother['mother']['mother']['name'] === 'LOUVE VARFEUIL', 'Pedigree : Mère de la Mère de la Mère exacte (LOUVE VARFEUIL)');
gws_test_assert($father['father']['father']['father'] === null && $father['father']['father']['mother'] === null, 'Pedigree : la dernière génération détectée n’a jamais de sous-branche inventée (null, pas un nœud vide)');

// --- Robustesse de la sanitation en aval : l'arbre produit est bien accepté tel quel par
// gwseq_sanitize_external_ancestor_tree() (même fonction que la saisie manuelle, §7) ---
$father_sanitized = gwseq_sanitize_external_ancestor_tree($father, GWSEQ_PEDIGREE_MAX_DEPTH - 1);
gws_test_assert($father_sanitized['name'] === 'UNTOUCHABLE' && $father_sanitized['father']['name'] === 'HORS LA LOI II', 'Pedigree : l’arbre produit par le parseur IFCE est accepté sans perte par le sanitiseur existant du pedigree manuel');

// =====================================================================================
// 3. Documents non reconnus (§10) — jamais un import "best effort"
// =====================================================================================

gws_test_assert(gwseq_ifce_parse_text('Un texte quelconque, sans rapport avec une fiche cheval.')['valid'] === false, 'Reconnaissance : un texte sans rapport est rejeté, jamais un import silencieux');
gws_test_assert(gwseq_ifce_parse_text('FICHE DE SYNTHESE IFCE — document incomplet, aucune ligne d’identité reconnaissable.')['valid'] === false, 'Reconnaissance : un marqueur IFCE présent mais sans ligne d’identité valide reste rejeté');
gws_test_assert(gwseq_ifce_parse_text('Selle Français, Femelle, Gris, 1m68, née en 2019 — sans aucun marqueur IFCE/Info Chevaux')['valid'] === false, 'Reconnaissance : une ligne d’identité seule, sans marqueur d’en-tête IFCE, reste rejetée (les deux signaux sont exigés)');
gws_test_assert(gwseq_ifce_parse_text('')['valid'] === false, 'Reconnaissance : un texte vide (PDF illisible/non extrait) est rejeté proprement');

// =====================================================================================
// 4. Mapping vers les fonctions métier existantes (§7-9) — jamais un accès direct aux post meta
// =====================================================================================

gws_test_make_post(60, GWSEQ_CPT_CHEVAL, 'JAMEROSE DE FELINES');

// --- Import complet (les trois sections) ---
$map_result = gwseq_ifce_map_import(60, $jamerose_parsed, array('identity' => true, 'indices' => true, 'pedigree' => true));
gws_test_assert($map_result === true, 'Mapping : l’import complet réussit');

$mapped_identity = gwseq_get_cheval_identity(60);
gws_test_assert(
  $mapped_identity['sexe'] === 'female' && $mapped_identity['race'] === 'selle_francais' && $mapped_identity['robe'] === 'gris'
  && $mapped_identity['taille_cm'] === 168 && $mapped_identity['annee_naissance'] === 2019
  && $mapped_identity['eleveur'] === 'Haras de Félines' && $mapped_identity['sire'] === '05123456A',
  'Mapping identité : toutes les valeurs sont bien persistées via gwseq_set_cheval_identity(), relecture exacte'
);

$mapped_iso = gwseq_get_cheval_sport_indice(60, 'iso');
gws_test_assert($mapped_iso['valeur'] === 115 && $mapped_iso['cd'] === 0.7 && $mapped_iso['annee'] === 2023, 'Mapping indices : ISO persisté via gwseq_set_cheval_sport_indice(), valeur/CD/année exacts');
$mapped_bso = gwseq_get_cheval_genetic_indice(60, 'bso');
gws_test_assert($mapped_bso['valeur'] === 12.0 && $mapped_bso['cd'] === 0.59, 'Mapping indices : BSO persisté via gwseq_set_cheval_genetic_indice()');

$mapped_father = gwseq_get_horse_parent(60, 'father');
$mapped_mother = gwseq_get_horse_parent(60, 'mother');
gws_test_assert($mapped_father['mode'] === 'external' && $mapped_father['horse_id'] === 0, 'Mapping pedigree : le Père est importé en mode "external" — jamais "gws" (§8, aucune fiche GWS créée pour un ascendant)');
gws_test_assert($mapped_father['external']['name'] === 'UNTOUCHABLE' && $mapped_father['external']['father']['name'] === 'HORS LA LOI II', 'Mapping pedigree : l’arbre Père est bien persisté via gwseq_set_horse_parent(), relecture exacte');
gws_test_assert($mapped_mother['mode'] === 'external' && $mapped_mother['external']['name'] === 'NATIVE DE FELINES', 'Mapping pedigree : la Mère est bien persistée en mode "external"');

// --- Import partiel (§9) : seule l’Identité est cochée -> Indices/Pedigree jamais touchés ---
gws_test_make_post(61, GWSEQ_CPT_CHEVAL, 'JAMEROSE DE FELINES');
gwseq_ifce_map_import(61, $jamerose_parsed, array('identity' => true, 'indices' => false, 'pedigree' => false));
gws_test_assert(gwseq_get_cheval_identity(61)['race'] === 'selle_francais', 'Import partiel : l’Identité est bien importée quand seule cette case est cochée');
gws_test_assert(gwseq_get_cheval_sport_indice(61, 'iso')['valeur'] === '', 'Import partiel : les Indices ne sont jamais écrits quand la case correspondante est décochée');
gws_test_assert(gwseq_get_horse_parent(61, 'father')['mode'] === '', 'Import partiel : le Pedigree n’est jamais écrit quand la case correspondante est décochée');

// --- Refus propre : structure non valide (document non reconnu) -> aucune écriture, jamais d'erreur fatale ---
gws_test_make_post(62, GWSEQ_CPT_CHEVAL, '(sans titre)');
gws_test_assert(gwseq_ifce_map_import(62, array('valid' => false), array('identity' => true, 'indices' => true, 'pedigree' => true)) === false, 'Mapping : une structure invalide (document non reconnu) est refusée, retourne false');
gws_test_assert($GLOBALS['__gwseq_test_meta'][62] ?? array() === array(), 'Mapping : aucune meta n’est écrite pour un import refusé');
gws_test_assert(gwseq_ifce_map_import(0, $jamerose_parsed, array('identity' => true)) === false, 'Mapping : un post_id invalide (0) est refusé');

// --- Aucune création automatique de fiche pour un ascendant (§8) : vérification déclarative,
// le mapper n'appelle jamais wp_insert_post ---
gws_test_assert(strpos($ifce_mapper_source, 'wp_insert_post') === false, 'Aucune fiche fantôme : le fichier de mapping n’appelle jamais wp_insert_post() — seule l’écran d’administration crée la fiche du cheval importé lui-même, jamais un ascendant');

// --- Jamais un accès direct aux post meta depuis le mapper (§7) : seules les fonctions métier
// existantes sont utilisées ---
gws_test_assert(strpos($ifce_mapper_code_only, 'update_post_meta') === false, 'Architecture (§7) : le fichier de mapping n’appelle jamais update_post_meta() directement — uniquement les fonctions métier existantes');
foreach (array('gwseq_set_cheval_identity', 'gwseq_set_cheval_sport_indice', 'gwseq_set_cheval_genetic_indice', 'gwseq_set_horse_parent') as $business_fn) {
  gws_test_assert(strpos($ifce_mapper_code_only, $business_fn) !== false, "Architecture (§7) : le mapping réutilise bien la fonction métier existante $business_fn(), jamais une réécriture ad hoc");
}

// =====================================================================================
// 5. Sécurité du téléversement et glue d'administration (§1/§11-12) — contrôles purs testables
//    directement, complétés par une vérification déclarative pour la partie non testable hors
//    d'une vraie requête HTTP (is_uploaded_file(), sniffing MIME réel)
// =====================================================================================

gws_test_assert(gwseq_ifce_validate_pdf_upload_shape(null) !== '', 'Sécurité upload : aucun fichier fourni -> message d’erreur explicite');
gws_test_assert(gwseq_ifce_validate_pdf_upload_shape(array('error' => UPLOAD_ERR_NO_FILE)) !== '', 'Sécurité upload : UPLOAD_ERR_NO_FILE -> rejeté');
gws_test_assert(gwseq_ifce_validate_pdf_upload_shape(array('error' => UPLOAD_ERR_INI_SIZE, 'size' => 1, 'name' => 'x.pdf')) !== '', 'Sécurité upload : un code d’erreur PHP de téléversement -> rejeté');
gws_test_assert(gwseq_ifce_validate_pdf_upload_shape(array('error' => UPLOAD_ERR_OK, 'size' => 0, 'name' => 'x.pdf')) !== '', 'Sécurité upload : taille nulle -> rejetée');
gws_test_assert(gwseq_ifce_validate_pdf_upload_shape(array('error' => UPLOAD_ERR_OK, 'size' => GWSEQ_IFCE_IMPORT_MAX_SIZE + 1, 'name' => 'x.pdf')) !== '', 'Sécurité upload : taille au-dessus de la limite (15 Mo) -> rejetée');
gws_test_assert(gwseq_ifce_validate_pdf_upload_shape(array('error' => UPLOAD_ERR_OK, 'size' => 1000, 'name' => 'x.docx')) !== '', 'Sécurité upload : une extension autre que .pdf -> rejetée, même avec un contenu par ailleurs valide');
gws_test_assert(gwseq_ifce_validate_pdf_upload_shape(array('error' => UPLOAD_ERR_OK, 'size' => 1000, 'name' => 'fiche-ifce.pdf')) === '', 'Sécurité upload : un fichier de forme valide (erreur OK, taille correcte, extension .pdf) passe ce contrôle');

foreach (array('finfo_open', 'is_uploaded_file') as $security_marker) {
  gws_test_assert(strpos($ifce_admin_source, $security_marker) !== false, "Sécurité upload (vérification déclarative) : $security_marker() est bien utilisé pour valider le contenu réel du fichier, jamais seulement son extension déclarée");
}
gws_test_assert(strpos($ifce_admin_source, 'GWSEQ_IFCE_IMPORT_MAX_SIZE') !== false, 'Sécurité upload (vérification déclarative) : une taille maximale est bien appliquée');

// --- Suppression du fichier temporaire (§11) : jamais de conservation du PDF après traitement ---
$upload_handler_body = substr($ifce_admin_code_only, strpos($ifce_admin_code_only, 'function gwseq_handle_ifce_import_upload'));
$upload_handler_body = substr($upload_handler_body, 0, strpos($upload_handler_body, "\nfunction "));
gws_test_assert(strpos($upload_handler_body, 'unlink') !== false, 'Suppression du fichier temporaire (§11) : le gestionnaire de téléversement supprime bien le PDF après extraction du texte');

// --- Aucune écriture avant validation (§1) : le gestionnaire d'UPLOAD n'écrit jamais de fiche ni
// de meta — seul le gestionnaire de CONFIRMATION (déclenché uniquement après un clic explicite sur
// l'écran de prévisualisation) crée la fiche et appelle le mapping ---
foreach (array('wp_insert_post', 'gwseq_ifce_map_import') as $write_marker) {
  gws_test_assert(strpos($upload_handler_body, $write_marker) === false, "Aucune écriture avant validation (§1) : le gestionnaire de téléversement n’appelle jamais $write_marker() — seule l’étape de confirmation explicite écrit quoi que ce soit");
}
$confirm_handler_body = substr($ifce_admin_code_only, strpos($ifce_admin_code_only, 'function gwseq_handle_ifce_import_confirm'));
$confirm_handler_body = substr($confirm_handler_body, 0, strpos($confirm_handler_body, "\nfunction "));
gws_test_assert(strpos($confirm_handler_body, 'wp_insert_post') !== false && strpos($confirm_handler_body, 'gwseq_ifce_map_import') !== false, 'Écriture différée (§1) : la fiche n’est créée et le mapping appelé QUE dans le gestionnaire de confirmation');

// --- Capacité : edit_posts, jamais manage_options (cohérent avec la capacité de création d’un
// cheval, à la différence des pages de réglages globales du plugin) ---
gws_test_assert(strpos($ifce_admin_code_only, "'edit_posts'") !== false, 'Capacité : la page d’import utilise bien "edit_posts", cohérente avec la création d’une fiche Cheval');
gws_test_assert(strpos($ifce_admin_code_only, 'manage_options') === false, 'Capacité : jamais "manage_options" pour cet écran (réservé aux réglages globaux du plugin)');

// --- Le PDF n'est jamais stocké comme pièce jointe / media de la fiche (§11) ---
foreach (array('wp_handle_upload', 'media_handle_upload', 'wp_insert_attachment') as $attachment_marker) {
  gws_test_assert(strpos($ifce_admin_code_only, $attachment_marker) === false, "Aucune conservation du PDF (§11) : $attachment_marker() n’est jamais appelé — le PDF n’est jamais transformé en pièce jointe/media de la fiche");
}

// --- Compatibilité (§12) : ce fichier n'appelle jamais save_post/add_meta_box (n'interfère jamais
// avec le formulaire de création manuelle existant) ---
gws_test_assert(strpos($ifce_admin_code_only, 'add_meta_box') === false, 'Compatibilité (§12) : l’écran d’import n’ajoute aucune boîte sur l’écran d’édition existant, ne modifie jamais le formulaire manuel');

// --- Enregistrement du sous-menu (déclaratif, sans réellement appeler admin_menu ici) ---
gwseq_add_ifce_import_page();
gws_test_assert(count($GLOBALS['__gwseq_test_submenu_pages']) === 1 && $GLOBALS['__gwseq_test_submenu_pages'][0]['capability'] === 'edit_posts', 'Menu : la page d’import est bien enregistrée en sous-menu du CPT Cheval, avec la capacité edit_posts');

// =====================================================================================
// i18n
// =====================================================================================

foreach ($GLOBALS['__gwseq_test_domains_used'] as $domain) {
  gws_test_assert($domain === 'gws-core', "i18n : aucun appel de traduction n’utilise un text domain autre que \"gws-core\" (trouvé : $domain)");
}

echo ($failures === 0 ? 'Tous les tests sont passés.' : "$failures test(s) en échec.") . "\n";
exit($failures === 0 ? 0 : 1);

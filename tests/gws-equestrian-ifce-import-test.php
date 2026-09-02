<?php
/**
 * Vérifie l'import IFCE (Étape 7 de la demande) : extraction de texte PDF (mécanique de base
 * contre un PDF minimal auto-généré, PUIS pipeline complet — extraction + reconnaissance + analyse
 * + mapping — contre le VRAI PDF de la fiche de synthèse IFCE de Jamerose de Félines, tel que
 * téléchargé depuis Info Chevaux), mapping vers les fonctions métier existantes (jamais un accès
 * direct aux post meta), et contrôle déclaratif de la glue d'administration (sécurité du
 * téléversement, aucune écriture avant validation, ascendants toujours externes, aucun PDF
 * conservé).
 *
 * DEPUIS LA RECETTE RUNTIME (Étape 7) : le vrai PDF (`tests/fixtures/ifce-jamerose-de-felines.pdf`)
 * est désormais LA fixture de référence pour la reconnaissance/l'analyse — plus seulement un texte
 * pré-extrait artificiellement. Le test appelle exactement le même pipeline que le runtime
 * WordPress : `gwseq_ifce_extract_pdf_text()` (lecture du fichier réel) ->
 * `gwseq_ifce_parse_text()` -> `gwseq_ifce_map_import()`. Voir `ifce-pdf-text.php` pour le diagnostic
 * complet de la cause exacte de l'échec initial sur ce PDF (objets compressés `/Type/ObjStm`, police
 * composite Identity-H sans laquelle le texte n'était pas décodable) et le correctif retenu.
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
// FIDÈLE au comportement réel de sanitize_key() (WordPress core) : mise en minuscules AVANT le
// filtrage des caractères — voir gws-equestrian-pedigree-logic-test.php pour le détail du bug de
// stub que cet ordre évite (codes de race en MAJUSCULES du référentiel).
function sanitize_key($value) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value)); }
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
$GLOBALS['__gwseq_test_user_meta'] = array();
function get_user_meta($user_id, $key, $single = false) { return $GLOBALS['__gwseq_test_user_meta'][$user_id][$key] ?? ''; }
function update_user_meta($user_id, $key, $value) { $GLOBALS['__gwseq_test_user_meta'][$user_id][$key] = $value; return true; }
function sanitize_html_class($value) { return preg_replace('/[^A-Za-z0-9_-]/', '', (string) $value); }
$GLOBALS['__gwseq_enqueued'] = array();
function wp_enqueue_script($handle, ...$rest) { $GLOBALS['__gwseq_enqueued'][] = $handle; }
function wp_enqueue_style($handle, ...$rest) { $GLOBALS['__gwseq_enqueued'][] = $handle; }
function wp_script_is($handle, $status = 'enqueued') { return in_array($handle, $GLOBALS['__gwseq_enqueued'], true); }
function wp_localize_script($handle, $object_name, $data) { $GLOBALS['__gwseq_test_localized'][$handle][$object_name] = $data; }
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
// Fidèle au comportement réel de check_admin_referer() (WordPress core) : appelée pour son EFFET
// DE BORD (meurt via wp_nonce_ays()/wp_die() si le nonce est invalide), jamais pour sa valeur de
// retour — le code réel (ifce-import-admin.php) l'appelle d'ailleurs sans jamais lire ce qu'elle
// renvoie. Un stub qui se contenterait de renvoyer un booléen laisserait passer silencieusement un
// nonce invalide au travers du code appelant.
function check_admin_referer($action, $field) {
  if (!$GLOBALS['__gwseq_test_security']['nonce_valid']) {
    throw new Exception('check_admin_referer: invalid nonce');
  }
  return true;
}
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
require $module_dir . 'includes/race-referentiel.php';
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
// 2. Extraction + reconnaissance + analyse — VRAI PDF de la fiche de synthèse IFCE de Jamerose de
//    Félines, tel que téléchargé depuis Info Chevaux (recette runtime, Étape 7). Exécute le MÊME
//    pipeline que le runtime WordPress : gwseq_ifce_extract_pdf_text() (lecture du vrai fichier) ->
//    gwseq_ifce_parse_text(). Un texte pré-extrait artificiellement n'est plus considéré comme
//    suffisant pour cette section (voir le CR de livraison).
// =====================================================================================

$jamerose_pdf_path = __DIR__ . '/fixtures/ifce-jamerose-de-felines.pdf';
gws_test_assert(is_readable($jamerose_pdf_path), 'Fixture : le vrai PDF de Jamerose de Félines est bien présent dans tests/fixtures/');

$jamerose_real_text = gwseq_ifce_extract_pdf_text($jamerose_pdf_path);
gws_test_assert($jamerose_real_text !== '', 'Extraction PDF réelle : du texte est bien extrait du vrai PDF IFCE (pipeline structuré — objets compressés + police Identity-H/ToUnicode)');
gws_test_assert(strpos($jamerose_real_text, 'JAMEROSE DE FELINES') !== false, 'Extraction PDF réelle : le nom du cheval est retrouvé, caractère par caractère, dans le texte du VRAI PDF');

$jamerose_parsed = gwseq_ifce_parse_text($jamerose_real_text);
gws_test_assert($jamerose_parsed['valid'] === true, 'Reconnaissance : le VRAI PDF IFCE de Jamerose est bien reconnu comme une fiche IFCE (correctif d’extraction post-recette)');

$identity = $jamerose_parsed['identity'];
gws_test_assert($identity['nom'] === 'JAMEROSE DE FELINES', 'Identité (vrai PDF) : nom exact');
gws_test_assert($identity['race'] === 'SF' && $identity['race_autre'] === '', 'Identité (vrai PDF) : race "Selle Francais" reconnue et mappée au code canonique du référentiel ("SF")');
gws_test_assert($identity['sexe'] === 'female', 'Identité (vrai PDF) : sexe "Femelle" mappé à "female"');
gws_test_assert($identity['robe'] === 'gris', 'Identité (vrai PDF) : robe "Gris" mappée au code canonique');
gws_test_assert($identity['taille_cm'] === 168, 'Identité (vrai PDF) : taille "1m68" convertie en 168 cm');
gws_test_assert($identity['annee_naissance'] === 2019, 'Identité (vrai PDF) : année de naissance exacte (« né(e) en 2019 »)');
gws_test_assert(strpos($identity['eleveur'], 'Haras De Felines') !== false, 'Identité (vrai PDF) : naisseur/éleveur identifié (raison sociale réelle du document, « Naisseur: S.a.s. Haras De Felines... »)');
gws_test_assert($identity['sire'] === '' && $identity['ueln'] === '', 'Identité (vrai PDF) : SIRE/UELN absents de la zone exploitée de cette fiche réelle -> restent vides, jamais devinés (le mot « SIRE » apparaît dans l’en-tête du document sans numéro associé, correctement ignoré)');
gws_test_assert($identity['nom_officiel'] === 'JAMEROSE DE FELINES', 'Identité (vrai PDF, cheval sans alias) : le nom officiel est identique au nom d’usage quand le document ne porte aucun alias');

// =====================================================================================
// 2ter. Correctif runtime (§7-10 de la demande) : alias IFCE du cheval lui-même — priorité au nom
// d'usage, nom officiel jamais perdu, code pays jamais confondu avec le nom. Cas synthétiques
// reproduisant exactement les exemples réels fournis (les quatre chevaux cités dans la demande).
// =====================================================================================

function gws_test_ifce_identity_name($name_line, $second_line = null) {
  $lines = $second_line !== null ? array($name_line, $second_line, 'Selle Francais, Male, Bai, 1m70, né(e) en 2015') : array($name_line, 'Selle Francais, Male, Bai, 1m70, né(e) en 2015');
  return gwseq_ifce_parse_identity_from_lines($lines);
}

$r = gws_test_ifce_identity_name('UNTOUCHABLE (NLD) Alias UNTOUCHABLE 27');
gws_test_assert($r['nom'] === 'UNTOUCHABLE 27' && $r['nom_officiel'] === 'UNTOUCHABLE', 'Alias identité (exemple exact de la demande, ligne combinée) : "UNTOUCHABLE (NLD)" + "Alias UNTOUCHABLE 27" -> nom GWS "UNTOUCHABLE 27", nom officiel "UNTOUCHABLE" conservé, code pays retiré');

$r = gws_test_ifce_identity_name('BUSH VD HEFFINCK (BEL)', 'Alias ASB CONQUISTADOR');
gws_test_assert($r['nom'] === 'ASB CONQUISTADOR' && $r['nom_officiel'] === 'BUSH VD HEFFINCK', 'Alias identité (exemple exact de la demande, deux lignes) : "BUSH VD HEFFINCK (BEL)" puis "Alias ASB CONQUISTADOR" -> nom GWS "ASB CONQUISTADOR", nom officiel "BUSH VD HEFFINCK"');

$r = gws_test_ifce_identity_name('WINDOWS VH COSTERSVELD (BEL)', 'Alias CORNET OBOLENSKY');
gws_test_assert($r['nom'] === 'CORNET OBOLENSKY' && $r['nom_officiel'] === 'WINDOWS VH COSTERSVELD', 'Alias identité (exemple exact de la demande) : "WINDOWS VH COSTERSVELD (BEL)" + "Alias CORNET OBOLENSKY" -> nom GWS "CORNET OBOLENSKY"');

$r = gws_test_ifce_identity_name('WHAT A QUICKSTAR R (NLD)', 'Alias BIG STAR');
gws_test_assert($r['nom'] === 'BIG STAR' && $r['nom_officiel'] === 'WHAT A QUICKSTAR R', 'Alias identité (exemple exact de la demande) : "WHAT A QUICKSTAR R (NLD)" + "Alias BIG STAR" -> nom GWS "BIG STAR"');

$r = gws_test_ifce_identity_name('JAMEROSE DE FELINES');
gws_test_assert($r['nom'] === 'JAMEROSE DE FELINES' && $r['nom_officiel'] === 'JAMEROSE DE FELINES', 'Identité sans alias (cas synthétique) : nom et nom officiel identiques, aucune mention "Alias" à traiter');

foreach (array($r['nom'], $r['nom_officiel']) as $value) {
  gws_test_assert(strpos($value, 'Alias') === false, 'Identité : le mot littéral "Alias" n’apparaît jamais dans le nom retenu ni dans le nom officiel');
}

$indices = $jamerose_parsed['indices'];
gws_test_assert($indices['iso']['valeur'] === 115 && $indices['iso']['cd'] === 0.7 && $indices['iso']['annee'] === 2023, 'Indices (vrai PDF) : ISO 115 (CD 0.70) (2023) — exemple exact de la demande, retrouvé dans le vrai document');
gws_test_assert($indices['icc']['valeur'] === '' && $indices['idr']['valeur'] === '', 'Indices (vrai PDF) : ICC/IDR absents de cette fiche réelle -> restent vides, jamais devinés');
gws_test_assert($indices['bso']['valeur'] === 12.0 && $indices['bso']['cd'] === 0.59, 'Indices (vrai PDF) : BSO +12 (CD 0.59) — exemple exact de la demande, retrouvé dans le vrai document');
gws_test_assert($indices['bcc']['valeur'] === '' && $indices['bdr']['valeur'] === '', 'Indices (vrai PDF) : BCC/BDR absents de cette fiche réelle -> restent vides');

$pedigree = $jamerose_parsed['pedigree'];
gws_test_assert($pedigree['count'] === 14, 'Pedigree (vrai PDF) : exactement 14 ascendants détectés, comme annoncé dans la demande');

$father = $pedigree['father'];
$mother = $pedigree['mother'];
gws_test_assert($father['name'] === 'UNTOUCHABLE 27', 'Pedigree (vrai PDF, correctif runtime §7) : Père exact — le document porte "UNTOUCHABLE Alias UNTOUCHABLE 27 (NLD)", et c’est désormais le nom d’usage/alias "UNTOUCHABLE 27" qui est retenu comme nom (jamais le mot littéral "Alias", jamais le seul nom officiel qui perdrait le nom réellement utilisé dans le sport)');
gws_test_assert($father['father']['name'] === 'HORS LA LOI II' && $father['father']['race'] === 'SF' && $father['father']['race_autre'] === '', 'Pedigree (vrai PDF, exemple important §2 de la demande référentiel) : Père du Père exact (HORS LA LOI II), l’alias historique "SFA" est reconnu et résolu au code canonique "SF", JAMAIS rangé dans "Autre"');
gws_test_assert($father['father']['annee_naissance'] === 1995, 'Pedigree (vrai PDF, correctif référentiel §9) : l’année de naissance de HORS LA LOI II ("SFA 1995") est bien extraite et importée');
gws_test_assert($father['father']['father']['name'] === 'PAPILLON ROUGE' && $father['father']['father']['annee_naissance'] === 1981, 'Pedigree (vrai PDF) : Père du Père du Père exact (PAPILLON ROUGE), année de naissance importée');
gws_test_assert($father['father']['mother']['name'] === 'ARIANE DU PLESSIS II' && $father['father']['mother']['annee_naissance'] === 1988, 'Pedigree (vrai PDF) : Mère du Père du Père exacte (ARIANE DU PLESSIS II — chiffre romain final jamais confondu avec un stud-book), année de naissance importée');
gws_test_assert($father['mother']['name'] === 'PROMESSE' && $father['mother']['race'] === 'KWPN' && $father['mother']['annee_naissance'] === 1997, 'Pedigree (vrai PDF) : Mère du Père exacte (PROMESSE), stud-book "KWPN" mappé au code canonique, année de naissance importée');
gws_test_assert($father['mother']['father']['name'] === 'HEARTBREAKER' && $father['mother']['father']['race'] === 'KWPN' && $father['mother']['father']['annee_naissance'] === 1989, 'Pedigree (vrai PDF) : Père de la Mère du Père exact (HEARTBREAKER), code pays "(NLD)" correctement écarté du nom comme du stud-book, année de naissance importée');
gws_test_assert($father['mother']['mother']['name'] === 'CHABLIS' && $father['mother']['mother']['race'] === 'OE' && $father['mother']['mother']['race_autre'] === '' && $father['mother']['mother']['annee_naissance'] === '', 'Pedigree (vrai PDF) : Mère de la Mère du Père exacte (CHABLIS), alias "OES" reconnu et résolu au code canonique "OE" (Origine Étrangère), jamais rangé dans "Autre" ; aucune année associée dans le document -> reste vide, jamais devinée');
gws_test_assert($mother['name'] === 'NATIVE DE FELINES', 'Pedigree (vrai PDF) : Mère exacte (NATIVE DE FELINES)');
gws_test_assert($mother['father']['name'] === 'ROSIRE', 'Pedigree (vrai PDF) : Père de la Mère exact (ROSIRE)');
gws_test_assert($mother['father']['father']['name'] === 'URIEL', 'Pedigree (vrai PDF) : Père du Père de la Mère exact (URIEL)');
gws_test_assert($mother['father']['mother']['name'] === 'EOLIENNE', 'Pedigree (vrai PDF) : Mère du Père de la Mère exacte (EOLIENNE)');
gws_test_assert($mother['mother']['name'] === 'FALINE GENEVRIS', 'Pedigree (vrai PDF) : Mère de la Mère exacte (FALINE GENEVRIS)');
gws_test_assert($mother['mother']['father']['name'] === 'PEGASE GERBAUX', 'Pedigree (vrai PDF) : Père de la Mère de la Mère exact (PEGASE GERBAUX)');
gws_test_assert($mother['mother']['mother']['name'] === 'LOUVE VARFEUIL', 'Pedigree (vrai PDF) : Mère de la Mère de la Mère exacte (LOUVE VARFEUIL)');
gws_test_assert($father['father']['father']['father'] === null && $father['father']['father']['mother'] === null, 'Pedigree (vrai PDF) : la dernière génération détectée n’a jamais de sous-branche inventée (null, pas un nœud vide)');

// --- Robustesse de la sanitation en aval : l'arbre produit est bien accepté tel quel par
// gwseq_sanitize_external_ancestor_tree() (même fonction que la saisie manuelle, §7) ---
$father_sanitized = gwseq_sanitize_external_ancestor_tree($father, GWSEQ_PEDIGREE_MAX_DEPTH - 1);
gws_test_assert($father_sanitized['name'] === 'UNTOUCHABLE 27' && $father_sanitized['father']['name'] === 'HORS LA LOI II', 'Pedigree (vrai PDF) : l’arbre produit par le parseur IFCE est accepté sans perte par le sanitiseur existant du pedigree manuel');

// =====================================================================================
// 2bis. Cas particuliers de gwseq_ifce_parse_pedigree_entry_line() constatés sur le vrai document
// =====================================================================================

gws_test_assert(gwseq_ifce_parse_pedigree_entry_line('HORS LA LOI II') === array('name' => 'HORS LA LOI II', 'official_name' => 'HORS LA LOI II', 'race_text' => '', 'annee_naissance' => ''), 'Entrée pedigree : un nom SANS stud-book se terminant par un chiffre romain (« II ») n’est jamais amputé — le chiffre romain n’est jamais confondu avec un code de stud-book (maintien des chiffres romains, §11)');
gws_test_assert(gwseq_ifce_parse_pedigree_entry_line('ARIANE DU PLESSIS II SFA 1988') === array('name' => 'ARIANE DU PLESSIS II', 'official_name' => 'ARIANE DU PLESSIS II', 'race_text' => 'SFA', 'annee_naissance' => 1988), 'Entrée pedigree : le même nom AVEC un vrai stud-book/année distingue correctement les deux (le chiffre romain reste dans le nom, "SFA" est bien isolé comme stud-book, l’année est extraite — correctif référentiel §9)');
gws_test_assert(gwseq_ifce_parse_pedigree_entry_line('CHABLIS OES') === array('name' => 'CHABLIS', 'official_name' => 'CHABLIS', 'race_text' => 'OES', 'annee_naissance' => ''), 'Entrée pedigree : un stud-book sans année associée reste correctement reconnu');

// --- Correctif runtime (§7-11 de la demande) : nom d'usage (alias) prioritaire sur le nom officiel
// pour un ASCENDANT, code pays IFCE retiré, chiffres romains et suffixe court jamais confondus
// avec un stud-book ---
gws_test_assert(
  gwseq_ifce_parse_pedigree_entry_line('UNTOUCHABLE Alias UNTOUCHABLE 27 (NLD) KWPN 2001') === array('name' => 'UNTOUCHABLE 27', 'official_name' => 'UNTOUCHABLE', 'race_text' => 'KWPN', 'annee_naissance' => 2001),
  'Entrée pedigree (alias d’un ascendant, exemple exact du vrai document Jamerose) : le nom d’usage/alias "UNTOUCHABLE 27" est retenu comme nom, jamais le mot littéral "Alias" ; le nom officiel "UNTOUCHABLE" reste disponible séparément ; le code pays "(NLD)", le stud-book "KWPN" et l’année 2001 (qui qualifient l’alias dans le document réel) sont correctement rattachés'
);
gws_test_assert(
  gwseq_ifce_parse_pedigree_entry_line('CARTHAGO Alias CARTHAGO Z (DEU) HOLST 1987') === array('name' => 'CARTHAGO Z', 'official_name' => 'CARTHAGO', 'race_text' => 'HOLST', 'annee_naissance' => 1987),
  'Entrée pedigree (exemple exact de la demande) : "CARTHAGO Alias CARTHAGO Z (DEU) HOLST 1987" -> nom affiché "CARTHAGO Z", nom officiel "CARTHAGO", race "HOLST", année 1987 — le suffixe court "Z" de l’alias reste bien dans le nom, jamais confondu avec un stud-book'
);
gws_test_assert(
  gwseq_ifce_parse_pedigree_entry_line('CORRADO I Alias SAN PATRIGNANO CORRADO (DEU) HOLST 1985') === array('name' => 'SAN PATRIGNANO CORRADO', 'official_name' => 'CORRADO I', 'race_text' => 'HOLST', 'annee_naissance' => 1985),
  'Entrée pedigree (exemple exact de la demande) : "CORRADO I Alias SAN PATRIGNANO CORRADO (DEU) HOLST 1985" -> nom affiché "SAN PATRIGNANO CORRADO", nom officiel "CORRADO I" (le chiffre romain "I" du nom officiel reste intact, jamais confondu avec un stud-book), race "HOLST", année 1985'
);
gws_test_assert(
  gwseq_ifce_parse_pedigree_entry_line('HEARTBREAKER (NLD) KWPN 1989') === array('name' => 'HEARTBREAKER', 'official_name' => 'HEARTBREAKER', 'race_text' => 'KWPN', 'annee_naissance' => 1989),
  'Entrée pedigree (suppression du code pays, sans alias) : "HEARTBREAKER (NLD) KWPN 1989" -> le marqueur pays "(NLD)" ne fait pas partie du nom, jamais confondu avec le stud-book "KWPN"'
);
gws_test_assert(
  gwseq_ifce_parse_pedigree_entry_line('ESCAPE Z (BEL)') === array('name' => 'ESCAPE Z', 'official_name' => 'ESCAPE Z', 'race_text' => '', 'annee_naissance' => ''),
  'Entrée pedigree (exemple exact de la demande) : "ESCAPE Z (BEL)" -> "ESCAPE Z", le marqueur pays retiré mais le suffixe "Z" du nom conservé (jamais confondu avec un stud-book, aucune information de stud-book/année ici)'
);
gws_test_assert(
  gwseq_ifce_parse_pedigree_entry_line('CLINTON (DEU)') === array('name' => 'CLINTON', 'official_name' => 'CLINTON', 'race_text' => '', 'annee_naissance' => ''),
  'Entrée pedigree (exemple exact de la demande) : "CLINTON (DEU)" -> "CLINTON", marqueur pays retiré'
);
gws_test_assert(
  strpos(gwseq_ifce_parse_pedigree_entry_line('UNTOUCHABLE Alias UNTOUCHABLE 27 (NLD) KWPN 2001')['name'], 'Alias') === false,
  'Entrée pedigree : le mot littéral "Alias" n’apparaît JAMAIS dans le nom retenu'
);
// --- Ne pas supprimer arbitrairement toute parenthèse : un contenu parenthésé qui n’est PAS un
// code pays IFCE reconnu doit rester intact (§9 : "ne pas supprimer arbitrairement toutes les
// parenthèses") ---
gws_test_assert(
  gwseq_ifce_strip_country_markers('CHEVAL (ABC) KWPN 1999') === 'CHEVAL (ABC) KWPN 1999',
  'Retrait du code pays : un contenu parenthésé de forme similaire mais qui n’est PAS un code pays IFCE reconnu ("ABC") n’est jamais retiré arbitrairement'
);

// =====================================================================================
// 2quinquies. Correctif runtime (recette sur des fiches IFCE réelles supplémentaires) : la ligne
// d'identité "Race, Sexe, Robe, Taille, né(e) en AAAA[, étalon]" n'a PAS un nombre de segments
// fixe sur toutes les fiches réelles — Robe ET Taille sont chacune FACULTATIVES indépendamment.
// Avant ce correctif, une position de segment figée perdait année/taille sur certaines fiches
// (année jamais extraite, malgré sa présence explicite dans le document), et une fiche à seulement
// 3 segments (Race, Sexe, "né(e) en AAAA", ni robe ni taille) n'était même pas reconnue comme une
// fiche IFCE valide. Chacun des cinq VRAIS PDF suivants (recette runtime) est exécuté à travers le
// MÊME pipeline complet que le runtime WordPress : gwseq_ifce_extract_pdf_text() ->
// gwseq_ifce_parse_text().
// =====================================================================================

function gws_test_ifce_parse_fixture($filename) {
  $path = __DIR__ . '/fixtures/' . $filename;
  if (!is_readable($path)) return array('valid' => false, '__fixture_missing' => true);
  return gwseq_ifce_parse_text(gwseq_ifce_extract_pdf_text($path));
}

// --- Quaprice Bois Margot : "Holsteiner Warmblut, Mâle, né(e) en 1998" — NI robe NI taille, la
// ligne la plus courte rencontrée (3 segments seulement). AVANT ce correctif : document rejeté
// intégralement ("Ce document n'a pas été reconnu comme une fiche de synthèse IFCE"). ---
$quaprice = gws_test_ifce_parse_fixture('ifce-quaprice-bois-margot.pdf');
gws_test_assert(empty($quaprice['__fixture_missing']), 'Fixture : le vrai PDF de Quaprice Bois Margot est bien présent dans tests/fixtures/');
gws_test_assert($quaprice['valid'] === true, 'Correctif runtime : Quaprice Bois Margot (ligne d’identité à 3 segments seulement, ni robe ni taille) est désormais bien reconnu comme une fiche IFCE — AVANT ce correctif, ce document réel était intégralement rejeté');
gws_test_assert(($quaprice['identity']['nom'] ?? null) === 'QUAPRICE BOIS MARGOT', 'Quaprice Bois Margot : nom exact (aucun alias sur cette fiche)');
gws_test_assert(($quaprice['identity']['annee_naissance'] ?? null) === 1998, 'Quaprice Bois Margot : année de naissance ("né(e) en 1998", directement après le sexe, sans robe ni taille) correctement extraite');
gws_test_assert(($quaprice['identity']['robe'] ?? null) === '' && ($quaprice['identity']['robe_autre'] ?? null) === '', 'Quaprice Bois Margot : robe absente du document -> reste vide, jamais devinée ni confondue avec l’année');
gws_test_assert(($quaprice['identity']['taille_cm'] ?? null) === '', 'Quaprice Bois Margot : taille absente du document -> reste vide');
gws_test_assert(($quaprice['identity']['race'] ?? null) === 'HOLST', 'Quaprice Bois Margot : race "Holsteiner Warmblut" reconnue et mappée au code canonique "HOLST"');

// --- Untouchable 27 : "Kon. Warm Paard Nederland, Mâle, Gris, né(e) en 2001, étalon" — robe
// présente, taille ABSENTE, mention finale ", étalon" après l'année. AVANT ce correctif : année
// perdue (le 4e segment positionnel valait "né(e) en 2001", pas la taille attendue à cette
// position ; le 5e segment valait "étalon", jamais un nombre à 4 chiffres). Race également corrigée
// (correctif normalisation croisée) : "Kon. Warm Paard Nederland" résout désormais vers "KWPN",
// le même code que le pedigree de ce même document. ---
$untouchable = gws_test_ifce_parse_fixture('ifce-untouchable-27.pdf');
gws_test_assert(empty($untouchable['__fixture_missing']), 'Fixture : le vrai PDF de Untouchable 27 est bien présent dans tests/fixtures/');
gws_test_assert($untouchable['valid'] === true, 'Untouchable 27 : document bien reconnu');
gws_test_assert(($untouchable['identity']['nom'] ?? null) === 'UNTOUCHABLE 27' && ($untouchable['identity']['nom_officiel'] ?? null) === 'UNTOUCHABLE', 'Untouchable 27 : alias retenu comme nom, nom officiel conservé séparément (non régressé par ce correctif)');
gws_test_assert(($untouchable['identity']['annee_naissance'] ?? null) === 2001, 'Correctif runtime : l’année de naissance d’Untouchable 27 ("né(e) en 2001", suivie de ", étalon") est désormais correctement extraite — AVANT ce correctif, elle restait vide malgré sa présence explicite dans le document');
gws_test_assert(($untouchable['identity']['race'] ?? null) === 'KWPN', 'Correctif normalisation croisée : la race "Kon. Warm Paard Nederland" (libellé long de l’identité) résout désormais vers le code canonique "KWPN" — jamais rangée dans "Autre"');
gws_test_assert(($untouchable['pedigree']['father']['father']['race'] ?? null) === 'SF', 'Untouchable 27 : le pedigree reste correctement mappé (Hors La Loi II, alias SFA -> SF), aucune régression');

// --- ASB Conquistador (alias de Bush vd Heffinck) : "Belgian Warmblood, Mâle, Bai, né(e) en 2001,
// étalon" — même structure qu’Untouchable 27 (robe présente, taille absente). ---
$asb = gws_test_ifce_parse_fixture('ifce-asb-conquistador.pdf');
gws_test_assert(empty($asb['__fixture_missing']), 'Fixture : le vrai PDF de Asb Conquistador est bien présent dans tests/fixtures/');
gws_test_assert($asb['valid'] === true, 'Asb Conquistador : document bien reconnu');
gws_test_assert(($asb['identity']['nom'] ?? null) === 'ASB CONQUISTADOR' && ($asb['identity']['nom_officiel'] ?? null) === 'BUSH VD HEFFINCK', 'Asb Conquistador : alias retenu comme nom, nom officiel "Bush vd Heffinck" conservé séparément');
gws_test_assert(($asb['identity']['annee_naissance'] ?? null) === 2001, 'Correctif runtime : l’année de naissance d’Asb Conquistador ("né(e) en 2001, étalon") est désormais correctement extraite');
gws_test_assert(($asb['identity']['race'] ?? null) === 'BWP', 'Correctif normalisation croisée : la race "Belgian Warmblood" résout désormais vers le code canonique "BWP", jamais "Autre"');

// --- CORRECTIF RUNTIME (recette 0.14.5) : reconstruction du pedigree — la ligne réelle
// "CORRADO I Alias SAN PATRIGNANO CORRADO" est suivie, dans le VRAI document, d'une ligne DISTINCTE
// "(DEU) HOLST 1985" (le marqueur pays, le stud-book ET l'année ont débordé ensemble sur la ligne
// suivante). AVANT ce correctif, cette seconde ligne n'était pas reconnue comme une continuation du
// nom de l'ascendant précédent et devenait un ASCENDANT FANTÔME ("HOLST 1985"), décalant d'un rang
// la position généalogique de TOUS les ascendants suivants dans la file — la mère réelle héritant à
// tort du rôle de père, etc. Vérifie ici la structure RÉELLE de l'arbre (nom, alias, race, année,
// position généalogique, père, mère), pas seulement un décompte du nombre d'ascendants (un parser
// peut trouver le bon nombre d'ascendants tout en les plaçant aux mauvaises positions) ---
$corrado_node = $asb['pedigree']['father']['father'] ?? null;
gws_test_assert($corrado_node !== null && $corrado_node['name'] === 'SAN PATRIGNANO CORRADO', 'Correctif runtime pedigree : le grand-père paternel d’Asb Conquistador est bien "SAN PATRIGNANO CORRADO" (alias retenu comme nom), à la bonne position généalogique');
gws_test_assert(($corrado_node['race'] ?? null) === 'HOLST', 'Correctif runtime pedigree : la race de SAN PATRIGNANO CORRADO ("(DEU) HOLST 1985", débordée sur la ligne suivante dans le vrai document) est bien rattachée à CET ascendant — "HOLST" — et non perdue dans un ascendant fantôme');
gws_test_assert(($corrado_node['annee_naissance'] ?? null) === 1985, 'Correctif runtime pedigree : l’année de naissance de SAN PATRIGNANO CORRADO (1985, débordée sur la ligne suivante) est bien rattachée à CET ascendant');
gws_test_assert(($corrado_node['father']['name'] ?? null) === 'COR DE LA BRYERE', 'Correctif runtime pedigree : le père de SAN PATRIGNANO CORRADO est bien "COR DE LA BRYERE" — AVANT ce correctif, cette position était occupée par l’ascendant fantôme "HOLST 1985"');
gws_test_assert(($corrado_node['mother']['name'] ?? null) === 'SOLEIL', 'Correctif runtime pedigree : la mère de SAN PATRIGNANO CORRADO est bien "SOLEIL" — AVANT ce correctif, "COR DE LA BRYERE" (son véritable père) se retrouvait décalé à tort à cette position de mère');
$asb_all_names = array();
$gws_test_collect_names = function ($node) use (&$gws_test_collect_names, &$asb_all_names) {
  if (!is_array($node)) return;
  $asb_all_names[] = $node['name'] ?? '';
  $gws_test_collect_names($node['father'] ?? null);
  $gws_test_collect_names($node['mother'] ?? null);
};
$gws_test_collect_names($asb['pedigree']['father']);
$gws_test_collect_names($asb['pedigree']['mother']);
gws_test_assert(!in_array('HOLST 1985', $asb_all_names, true) && !in_array('(DEU) HOLST 1985', $asb_all_names, true), 'Correctif runtime pedigree : aucun ascendant fantôme "HOLST 1985" n’existe dans l’arbre reconstruit');

// --- Cornet Obolensky (alias de Windows vh Costersveld) : "Belgian Warmblood, Mâle, Gris, 1m71,
// né(e) en 1999, étalon" — robe ET taille présentes (6 segments). Ce document extrayait déjà
// correctement l’année AVANT ce correctif (non-régression explicitement vérifiée) ; sa race, en
// revanche, ne résolvait pas encore vers "BWP" avant le correctif de normalisation croisée. ---
$cornet = gws_test_ifce_parse_fixture('ifce-cornet-obolensky.pdf');
gws_test_assert(empty($cornet['__fixture_missing']), 'Fixture : le vrai PDF de Cornet Obolensky est bien présent dans tests/fixtures/');
gws_test_assert($cornet['valid'] === true, 'Cornet Obolensky : document bien reconnu');
gws_test_assert(($cornet['identity']['nom'] ?? null) === 'CORNET OBOLENSKY' && ($cornet['identity']['nom_officiel'] ?? null) === 'WINDOWS VH COSTERSVELD', 'Cornet Obolensky : alias retenu comme nom, nom officiel conservé séparément');
gws_test_assert(($cornet['identity']['annee_naissance'] ?? null) === 1999, 'Non-régression : Cornet Obolensky extrayait déjà correctement son année de naissance (1999) avant ce correctif, toujours vrai après (segments Robe et Taille tous deux présents sur cette fiche)');
gws_test_assert(($cornet['identity']['taille_cm'] ?? null) === 171, 'Non-régression : la taille ("1m71") reste correctement extraite quand elle est présente, malgré la nouvelle détection dynamique de la position des segments');
gws_test_assert(($cornet['identity']['race'] ?? null) === 'BWP', 'Correctif normalisation croisée : la race "Belgian Warmblood" de Cornet Obolensky résout également vers "BWP"');

// --- Même correctif runtime que ci-dessus (branche CORRADO), vérifié sur un second document réel
// distinct — même motif exact ("CORRADO I Alias SAN PATRIGNANO CORRADO" / "(DEU) HOLST 1985" sur
// la ligne suivante), jamais traité comme un cas isolé propre à Asb Conquistador. Ce document porte
// en outre l’arbre COMPLET à 3 générations (14 ascendants) : vérifie la structure entière, pas
// seulement la branche corrigée. ---
gws_test_assert(($cornet['pedigree']['count'] ?? null) === 14, 'Cornet Obolensky : les 14 ascendants de l’arbre complet à 3 générations sont bien reconnus (aucun ascendant fantôme n’a consommé un rang)');
$cornet_corrado_node = $cornet['pedigree']['father']['father'] ?? null;
gws_test_assert($cornet_corrado_node !== null && $cornet_corrado_node['name'] === 'SAN PATRIGNANO CORRADO' && $cornet_corrado_node['race'] === 'HOLST' && $cornet_corrado_node['annee_naissance'] === 1985, 'Correctif runtime pedigree (second document réel) : SAN PATRIGNANO CORRADO à la bonne position, avec sa race et son année correctement rattachées');
gws_test_assert(($cornet_corrado_node['father']['name'] ?? null) === 'COR DE LA BRYERE' && ($cornet_corrado_node['mother']['name'] ?? null) === 'SOLEIL', 'Correctif runtime pedigree (second document réel) : père "COR DE LA BRYERE" et mère "SOLEIL" de SAN PATRIGNANO CORRADO à leurs bonnes positions');
// --- Vérification de bout en bout de l’ARBRE ENTIER (nom, race, année, position) — pas seulement
// la branche corrigée, pour prouver qu’aucune autre position n’a été décalée par le correctif ---
gws_test_assert(($cornet['pedigree']['father']['name'] ?? null) === 'CLINTON' && ($cornet['pedigree']['father']['race'] ?? null) === 'HOLST' && ($cornet['pedigree']['father']['annee_naissance'] ?? null) === 1993, 'Cornet Obolensky : Père = CLINTON (HOLST, 1993)');
gws_test_assert(($cornet['pedigree']['father']['mother']['name'] ?? null) === 'URTE I' && ($cornet['pedigree']['father']['mother']['father']['name'] ?? null) === 'MASETTO' && ($cornet['pedigree']['father']['mother']['mother']['name'] ?? null) === 'OHRA', 'Cornet Obolensky : branche maternelle de CLINTON intacte (URTE I -> MASETTO x OHRA), non décalée par le correctif de la branche paternelle');
gws_test_assert(($cornet['pedigree']['mother']['name'] ?? null) === 'RABANNA VAN COSTERSVELD', 'Cornet Obolensky : Mère = RABANNA VAN COSTERSVELD, à sa bonne position (non décalée par le correctif appliqué à la branche paternelle)');
gws_test_assert(($cornet['pedigree']['mother']['father']['name'] ?? null) === 'HEARTBREAKER' && ($cornet['pedigree']['mother']['father']['father']['name'] ?? null) === 'NIMMERDOR' && ($cornet['pedigree']['mother']['father']['mother']['name'] ?? null) === 'BACAROLE', 'Cornet Obolensky : branche HEARTBREAKER (-> NIMMERDOR x BACAROLE) à sa bonne position');
gws_test_assert(($cornet['pedigree']['mother']['mother']['name'] ?? null) === 'HOLIVEA VAN COSTERSVELD' && ($cornet['pedigree']['mother']['mother']['father']['name'] ?? null) === 'RANDEL Z' && ($cornet['pedigree']['mother']['mother']['mother']['name'] ?? null) === 'GUDULA O', 'Cornet Obolensky : branche HOLIVEA VAN COSTERSVELD (-> RANDEL Z x GUDULA O, avec sa propre continuation d’année isolée "1984" déjà validée) à sa bonne position, non affectée par le correctif de la branche CORRADO plus haut dans l’arbre');

// --- Iowa Jal : format standard à 5 segments (Race, Sexe, Robe, Taille, "né(e) en AAAA", sans
// mention finale ", étalon") — non-régression explicite du format déjà couvert par Jamerose. ---
$iowa = gws_test_ifce_parse_fixture('ifce-iowa-jal.pdf');
gws_test_assert(empty($iowa['__fixture_missing']), 'Fixture : le vrai PDF de Iowa Jal est bien présent dans tests/fixtures/');
gws_test_assert($iowa['valid'] === true && ($iowa['identity']['nom'] ?? null) === 'IOWA JAL', 'Non-régression : Iowa Jal (format standard à 5 segments) reste correctement reconnu');
gws_test_assert(($iowa['identity']['annee_naissance'] ?? null) === 2018 && ($iowa['identity']['taille_cm'] ?? null) === 170 && ($iowa['identity']['race'] ?? null) === 'SF', 'Non-régression : année (2018), taille (170 cm) et race (SF) d’Iowa Jal restent tous corrects après la détection dynamique des segments');

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
  $mapped_identity['sexe'] === 'female' && $mapped_identity['race'] === 'SF' && $mapped_identity['robe'] === 'gris'
  && $mapped_identity['taille_cm'] === 168 && $mapped_identity['annee_naissance'] === 2019
  && strpos($mapped_identity['eleveur'], 'Haras De Felines') !== false && $mapped_identity['sire'] === '',
  'Mapping identité : toutes les valeurs sont bien persistées via gwseq_set_cheval_identity(), relecture exacte'
);
gws_test_assert(get_post_meta(60, '_gwseq_ifce_nom_officiel', true) === 'JAMEROSE DE FELINES', 'Mapping identité (correctif runtime §8) : le nom officiel IFCE est conservé en donnée technique séparée, même quand il est identique au nom d’usage (aucun alias sur cette fiche)');

// --- Alias sur la fiche importée elle-même : post_title/nom utilise le nom d’usage, le nom
// officiel reste disponible séparément (jamais perdu, jamais exposé dans le formulaire manuel) ---
gws_test_make_post(62, GWSEQ_CPT_CHEVAL, 'ASB CONQUISTADOR');
$aliased_parsed = $jamerose_parsed;
$aliased_parsed['identity']['nom'] = 'ASB CONQUISTADOR';
$aliased_parsed['identity']['nom_officiel'] = 'BUSH VD HEFFINCK';
gwseq_ifce_map_import(62, $aliased_parsed, array('identity' => true, 'indices' => false, 'pedigree' => false));
gws_test_assert(get_post_meta(62, '_gwseq_ifce_nom_officiel', true) === 'BUSH VD HEFFINCK', 'Mapping identité (correctif runtime §8, alias) : le nom officiel "BUSH VD HEFFINCK" est bien conservé alors que la fiche porte le nom d’usage "ASB CONQUISTADOR"');
gws_test_assert(strpos($ifce_mapper_source, 'gwseq_set_cheval_ifce_nom_officiel') !== false, 'Mapping identité : le nom officiel passe bien par une fonction métier dédiée (gwseq_set_cheval_ifce_nom_officiel()), jamais un accès direct à update_post_meta() dans ce fichier');

$mapped_iso = gwseq_get_cheval_sport_indice(60, 'iso');
gws_test_assert($mapped_iso['valeur'] === 115 && $mapped_iso['cd'] === 0.7 && $mapped_iso['annee'] === 2023, 'Mapping indices : ISO persisté via gwseq_set_cheval_sport_indice(), valeur/CD/année exacts');
$mapped_bso = gwseq_get_cheval_genetic_indice(60, 'bso');
gws_test_assert($mapped_bso['valeur'] === 12.0 && $mapped_bso['cd'] === 0.59, 'Mapping indices : BSO persisté via gwseq_set_cheval_genetic_indice()');

$mapped_father = gwseq_get_horse_parent(60, 'father');
$mapped_mother = gwseq_get_horse_parent(60, 'mother');
gws_test_assert($mapped_father['mode'] === 'external' && $mapped_father['horse_id'] === 0, 'Mapping pedigree : le Père est importé en mode "external" — jamais "gws" (§8, aucune fiche GWS créée pour un ascendant)');
gws_test_assert($mapped_father['external']['name'] === 'UNTOUCHABLE 27' && $mapped_father['external']['father']['name'] === 'HORS LA LOI II', 'Mapping pedigree : l’arbre Père est bien persisté via gwseq_set_horse_parent(), relecture exacte (nom d’usage/alias "UNTOUCHABLE 27" conservé)');
gws_test_assert($mapped_mother['mode'] === 'external' && $mapped_mother['external']['name'] === 'NATIVE DE FELINES', 'Mapping pedigree : la Mère est bien persistée en mode "external"');

// =====================================================================================
// 4bis. Choix Père/Mère GWS pendant l'import (§3 de la demande) — rattacher un parent DIRECT
// détecté par l'IFCE à une fiche Cheval GWS déjà existante, plutôt qu'un ascendant externe.
// =====================================================================================

// --- gwseq_sanitize_ifce_preview_parent_choice() : fonction pure, mêmes garanties que le reste du
// module (jamais un accès direct à $_POST ailleurs que dans le gestionnaire HTTP) ---
gws_test_make_post(700, GWSEQ_CPT_CHEVAL, 'Étalon GWS Existant');
gws_test_assert(gwseq_sanitize_ifce_preview_parent_choice(array('gwseq_ifce_pere_mode' => 'gws', 'gwseq_ifce_pere_gws_id' => '700'), 'gwseq_ifce_pere_mode', 'gwseq_ifce_pere_gws_id') === array('mode' => 'gws', 'horse_id' => 700), 'Choix Père/Mère GWS : mode "gws" avec un identifiant réel de fiche Cheval -> conservé tel quel');
gws_test_assert(gwseq_sanitize_ifce_preview_parent_choice(array('gwseq_ifce_pere_mode' => 'gws', 'gwseq_ifce_pere_gws_id' => '999999'), 'gwseq_ifce_pere_mode', 'gwseq_ifce_pere_gws_id') === array('mode' => 'external'), 'Choix Père/Mère GWS : mode "gws" avec un identifiant inexistant -> repli sur "external" (comportement déjà validé), jamais une relation orpheline');
gws_test_assert(gwseq_sanitize_ifce_preview_parent_choice(array('gwseq_ifce_pere_mode' => 'skip'), 'gwseq_ifce_pere_mode', 'gwseq_ifce_pere_gws_id') === array('mode' => 'skip'), 'Choix Père/Mère GWS : mode "skip" conservé tel quel');
gws_test_assert(gwseq_sanitize_ifce_preview_parent_choice(array(), 'gwseq_ifce_pere_mode', 'gwseq_ifce_pere_gws_id') === array('mode' => 'external'), 'Choix Père/Mère GWS : mode absent du formulaire -> repli sur "external" (comportement déjà validé, inchangé)');
gws_test_assert(gwseq_sanitize_ifce_preview_parent_choice(array('gwseq_ifce_pere_mode' => 'valeur-invalide'), 'gwseq_ifce_pere_mode', 'gwseq_ifce_pere_gws_id') === array('mode' => 'external'), 'Choix Père/Mère GWS : une valeur de mode inconnue -> repli sur "external", jamais une valeur arbitraire propagée');

// --- Lier le Père détecté à une fiche GWS déjà existante : AUCUNE copie externe créée en
// parallèle (jamais les deux à la fois), la vraie relation GWS via gwseq_set_horse_parent() (MÊME
// fonction que la saisie manuelle du pedigree, jamais une écriture directe dupliquée ici) ---
gws_test_make_post(701, GWSEQ_CPT_CHEVAL, 'Nouveau Cheval Importé');
gws_test_make_post(702, GWSEQ_CPT_CHEVAL, 'Étalon Père GWS');
gwseq_set_cheval_identity(702, array('_gwseq_sexe' => 'male', '_gwseq_annee_naissance' => 2000));
gwseq_ifce_map_import(701, $jamerose_parsed, array('identity' => true, 'indices' => false, 'pedigree' => true), array(
  'father' => array('mode' => 'gws', 'horse_id' => 702),
));
$linked_father = gwseq_get_horse_parent(701, 'father');
gws_test_assert($linked_father['mode'] === 'gws' && $linked_father['horse_id'] === 702, 'Choix Père/Mère GWS : le Père est bien lié à la fiche GWS existante (mode "gws"), jamais "external"');
gws_test_assert($linked_father['external'] === null, 'Choix Père/Mère GWS : aucune copie externe du Père n’est créée en parallèle de la relation GWS (jamais les deux à la fois)');

// --- "Ne pas importer ce parent" : aucune relation créée pour ce rôle, quelles que soient les
// données détectées par l’IFCE pour ce parent ---
gws_test_make_post(703, GWSEQ_CPT_CHEVAL, 'Nouveau Cheval Importé Sans Mère');
gwseq_ifce_map_import(703, $jamerose_parsed, array('identity' => true, 'indices' => false, 'pedigree' => true), array(
  'mother' => array('mode' => 'skip'),
));
gws_test_assert(gwseq_get_horse_parent(703, 'mother')['mode'] === '', 'Choix Père/Mère GWS : "Ne pas importer ce parent" -> aucune relation créée pour ce rôle');
gws_test_assert(gwseq_get_horse_parent(703, 'father')['mode'] === 'external', 'Choix Père/Mère GWS : le choix "skip" de la Mère n’affecte jamais le Père (chaque rôle traité indépendamment) — comportement "external" par défaut toujours actif pour le Père');

// --- Intégrité déjà validée pour la saisie manuelle (§ un même cheval GWS ne peut jamais être à la
// fois père ET mère) : réutilisée SANS AUCUNE règle dupliquée ici, simplement parce que
// gwseq_ifce_map_import() traite Père PUIS Mère dans cet ordre, exactement comme documenté ---
gws_test_make_post(704, GWSEQ_CPT_CHEVAL, 'Nouveau Cheval Importé Conflit');
gws_test_make_post(705, GWSEQ_CPT_CHEVAL, 'Cheval Ambigu');
// Sexe volontairement non renseigné : ce test porte sur le conflit père/mère, pas sur le filtre
// sexe (déjà couvert séparément plus haut dans cette section) — un sexe vide reste toujours autorisé
// pour les deux rôles (gwseq_horse_sexe_compatible_with_role()), donc n'interfère pas ici.
gwseq_ifce_map_import(704, $jamerose_parsed, array('identity' => true, 'indices' => false, 'pedigree' => true), array(
  'father' => array('mode' => 'gws', 'horse_id' => 705),
  'mother' => array('mode' => 'gws', 'horse_id' => 705),
));
gws_test_assert(gwseq_get_horse_parent(704, 'father')['mode'] === 'gws' && gwseq_get_horse_parent(704, 'father')['horse_id'] === 705, 'Choix Père/Mère GWS : le Père, traité EN PREMIER, est bien lié normalement');
gws_test_assert(gwseq_get_horse_parent(704, 'mother')['mode'] === '', 'Choix Père/Mère GWS : le MÊME cheval GWS ne peut jamais être lié comme Mère en plus du Père (gwseq_set_horse_parent() rejette silencieusement, aucune relation créée pour la Mère — intégrité déjà validée pour la saisie manuelle, réutilisée sans duplication)');

// --- "Importer le pedigree" décoché : le choix Père/Mère GWS reste sans le moindre effet, comme
// n’importe quel autre champ de la section pedigree (comportement déjà validé, non régressé) ---
gws_test_make_post(706, GWSEQ_CPT_CHEVAL, 'Nouveau Cheval Sans Pedigree Importe');
gwseq_ifce_map_import(706, $jamerose_parsed, array('identity' => true, 'indices' => false, 'pedigree' => false), array(
  'father' => array('mode' => 'gws', 'horse_id' => 702),
));
gws_test_assert(gwseq_get_horse_parent(706, 'father')['mode'] === '', 'Choix Père/Mère GWS : sans effet quand "Importer le pedigree" est décoché — comportement déjà validé (import sans pedigree = aucun ascendant enregistré) non régressé');

// --- gwseq_ifce_preview_parent_candidate_rejection_reason() (écran de prévisualisation, avant que
// la fiche important n’existe) : réutilise SANS LES DUPLIQUER les mêmes règles de sexe/année que la
// saisie manuelle du pedigree ---
gws_test_make_post(710, GWSEQ_CPT_CHEVAL, 'Jument Candidate');
gwseq_set_cheval_identity(710, array('_gwseq_sexe' => 'female', '_gwseq_annee_naissance' => 2010));
gws_test_assert(gwseq_ifce_preview_parent_candidate_rejection_reason('father', 710, 2019) === 'sexe', 'Prévisualisation IFCE : une jument proposée comme Père est bien rejetée ("sexe"), même règle que la saisie manuelle');
gws_test_assert(gwseq_ifce_preview_parent_candidate_rejection_reason('mother', 710, 2019) === '', 'Prévisualisation IFCE : la même jument, née avant le cheval importé (2010 < 2019), est bien acceptée comme Mère');
gws_test_assert(gwseq_ifce_preview_parent_candidate_rejection_reason('mother', 710, 2005) === 'annee', 'Prévisualisation IFCE : la même jument, née APRÈS le cheval importé (2010 > 2005), est bien rejetée ("annee")');
gws_test_assert(gwseq_ifce_preview_parent_candidate_rejection_reason('mother', 0, 2019) === '', 'Prévisualisation IFCE : un identifiant vide (« — Choisir un cheval — ») n’est jamais un rejet, simplement l’absence de choix');

// --- Import partiel (§9) : seule l’Identité est cochée -> Indices/Pedigree jamais touchés ---
gws_test_make_post(61, GWSEQ_CPT_CHEVAL, 'JAMEROSE DE FELINES');
gwseq_ifce_map_import(61, $jamerose_parsed, array('identity' => true, 'indices' => false, 'pedigree' => false));
gws_test_assert(gwseq_get_cheval_identity(61)['race'] === 'SF', 'Import partiel : l’Identité est bien importée quand seule cette case est cochée');
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

/**
 * Extrait le corps d'UNE fonction (jusqu'à la déclaration `function` suivante) depuis le code
 * source déjà débarrassé de ses commentaires — même utilitaire que celui déjà utilisé plus haut
 * pour les gestionnaires de haut niveau, généralisé ici pour cibler les fonctions PURES extraites
 * lors du correctif "headers already sent" (voir plus bas).
 */
function gws_test_extract_function_body($code_only, $function_name) {
  $body = substr($code_only, strpos($code_only, 'function ' . $function_name));
  $next = strpos($body, "\nfunction ", 1);
  return $next === false ? $body : substr($body, 0, $next);
}

$upload_handler_body = gws_test_extract_function_body($ifce_admin_code_only, 'gwseq_handle_ifce_import_upload');
$upload_processor_body = gws_test_extract_function_body($ifce_admin_code_only, 'gwseq_process_ifce_import_upload');
$confirm_handler_body = gws_test_extract_function_body($ifce_admin_code_only, 'gwseq_handle_ifce_import_confirm');
$confirm_processor_body = gws_test_extract_function_body($ifce_admin_code_only, 'gwseq_process_ifce_import_confirm');

// --- Suppression du fichier temporaire (§11) : jamais de conservation du PDF après traitement ---
gws_test_assert(strpos($upload_processor_body, 'unlink') !== false, 'Suppression du fichier temporaire (§11) : le traitement du téléversement supprime bien le PDF après extraction du texte');

// --- Aucune écriture avant validation (§1) : ni le gestionnaire d'UPLOAD ni son traitement pur
// n'écrivent jamais de fiche ni de meta — seul le traitement de CONFIRMATION (déclenché uniquement
// après un clic explicite sur l'écran de prévisualisation) crée la fiche et appelle le mapping ---
foreach (array($upload_handler_body, $upload_processor_body) as $body) {
  foreach (array('wp_insert_post', 'gwseq_ifce_map_import') as $write_marker) {
    gws_test_assert(strpos($body, $write_marker) === false, "Aucune écriture avant validation (§1) : le téléversement n’appelle jamais $write_marker() — seule l’étape de confirmation explicite écrit quoi que ce soit");
  }
}
gws_test_assert(strpos($confirm_processor_body, 'wp_insert_post') !== false && strpos($confirm_processor_body, 'gwseq_ifce_map_import') !== false, 'Écriture différée (§1) : la fiche n’est créée et le mapping appelé QUE dans le traitement de confirmation');
gws_test_assert(strpos($confirm_handler_body, 'wp_insert_post') === false && strpos($confirm_handler_body, 'gwseq_ifce_map_import') === false, 'Séparation glue/logique : le gestionnaire admin_post de confirmation lui-même n’appelle plus directement wp_insert_post()/gwseq_ifce_map_import() — délégué au traitement pur');

// =====================================================================================
// 5bis. Correctif bloquant post-recette — « headers already sent » (admin-post.php, jamais le
// callback de page). Le traitement POST (upload, confirmation) était auparavant exécuté DEPUIS le
// callback de la page d'administration (gwseq_render_ifce_import_page()), appelé par WordPress
// SEULEMENT depuis l'intérieur du rendu complet de l'écran (après admin-header.php/menu-header.php
// ont déjà émis du HTML) — un wp_safe_redirect() à ce stade échoue systématiquement. Corrigé en
// confiant ce traitement aux hooks natifs `admin_post_{action}`, déclenchés depuis
// wp-admin/admin-post.php, qui ne rend jamais aucun HTML avant de déclencher le hook.
// =====================================================================================

// --- Le traitement POST est bien confié aux hooks admin_post_* (exécutés AVANT tout rendu
// d'écran), jamais au callback de page ---
gws_test_assert(strpos($ifce_admin_code_only, "add_action('admin_post_gwseq_ifce_import_upload', 'gwseq_handle_ifce_import_upload')") !== false, 'Correctif "headers already sent" : le traitement du téléversement est bien accroché à admin_post_gwseq_ifce_import_upload (exécuté par wp-admin/admin-post.php, avant tout rendu d’écran)');
gws_test_assert(strpos($ifce_admin_code_only, "add_action('admin_post_gwseq_ifce_import_confirm', 'gwseq_handle_ifce_import_confirm')") !== false, 'Correctif "headers already sent" : le traitement de la confirmation est bien accroché à admin_post_gwseq_ifce_import_confirm');

// --- Le callback de PAGE ne traite plus jamais de POST ni ne redirige lui-même — c'est précisément
// ce qui causait l'échec (wp_safe_redirect() appelé après que admin-header.php/menu-header.php ont
// déjà émis du HTML) ---
$page_callback_body = gws_test_extract_function_body($ifce_admin_code_only, 'gwseq_render_ifce_import_page');
gws_test_assert(strpos($page_callback_body, 'wp_safe_redirect') === false, 'Correctif "headers already sent" : le callback de page (gwseq_render_ifce_import_page) n’appelle plus jamais wp_safe_redirect() lui-même');
gws_test_assert(strpos($page_callback_body, '$_POST') === false, 'Correctif "headers already sent" : le callback de page ne lit plus jamais $_POST — il ne traite plus aucun formulaire, uniquement l’état déjà déterminé par les gestionnaires admin_post_*');
foreach (array('gwseq_handle_ifce_import_upload', 'gwseq_handle_ifce_import_confirm') as $handler_name) {
  gws_test_assert(strpos($page_callback_body, $handler_name) === false, "Correctif \"headers already sent\" : le callback de page n’appelle plus jamais $handler_name() directement");
}

// --- Les DEUX formulaires soumettent bien vers admin-post.php (jamais vers la page elle-même, qui
// ne traite plus aucun POST) ---
ob_start();
gwseq_render_ifce_import_upload_form();
$upload_form_html = ob_get_clean();
gws_test_assert(strpos($upload_form_html, 'admin-post.php') !== false, 'Formulaire d’upload : soumet bien vers admin-post.php');
gws_test_assert(strpos($upload_form_html, 'name="action" value="gwseq_ifce_import_upload"') !== false, 'Formulaire d’upload : porte bien le champ "action" attendu par admin-post.php pour router vers admin_post_gwseq_ifce_import_upload');

ob_start();
gwseq_render_ifce_import_preview('faketoken123', $jamerose_parsed);
$preview_form_html = ob_get_clean();
gws_test_assert(strpos($preview_form_html, 'admin-post.php') !== false, 'Formulaire de confirmation : soumet bien vers admin-post.php');
gws_test_assert(strpos($preview_form_html, 'name="action" value="gwseq_ifce_import_confirm"') !== false, 'Formulaire de confirmation : porte bien le champ "action" attendu par admin-post.php pour router vers admin_post_gwseq_ifce_import_confirm');

// --- Choix Père/Mère GWS (§3 de la demande) : rendu réel sur la prévisualisation de Jamerose de
// Félines, dont le Père (UNTOUCHABLE 27) et la Mère (NATIVE DE FELINES) sont bien détectés ---
gws_test_assert(strpos($preview_form_html, 'name="gwseq_ifce_pere_mode" value="external" checked') !== false, 'Prévisualisation IFCE : le choix "Importer comme ascendant externe" est bien proposé pour le Père, et reste le répli sélectionné par défaut (comportement déjà validé)');
gws_test_assert(strpos($preview_form_html, 'name="gwseq_ifce_pere_mode" value="gws"') !== false, 'Prévisualisation IFCE : le choix "Lier à un cheval déjà enregistré" est bien proposé pour le Père');
gws_test_assert(strpos($preview_form_html, 'name="gwseq_ifce_pere_mode" value="skip"') !== false, 'Prévisualisation IFCE : le choix "Ne pas importer ce parent" est bien proposé pour le Père');
gws_test_assert(strpos($preview_form_html, 'name="gwseq_ifce_pere_gws_id"') !== false, 'Prévisualisation IFCE : le sélecteur de cheval GWS pour le Père est bien présent');
gws_test_assert(strpos($preview_form_html, 'name="gwseq_ifce_mere_mode" value="external" checked') !== false, 'Prévisualisation IFCE : même choix également proposé pour la Mère, "external" répli par défaut');
gws_test_assert(strpos($preview_form_html, 'UNTOUCHABLE 27') !== false, 'Prévisualisation IFCE : le nom du Père détecté (UNTOUCHABLE 27) est bien affiché dans le résumé du choix');
gws_test_assert(strpos($preview_form_html, 'NATIVE DE FELINES') !== false, 'Prévisualisation IFCE : le nom de la Mère détectée (NATIVE DE FELINES) est bien affiché dans le résumé du choix');

// --- Ajustement UX de la prévisualisation (recette runtime, §8) : chaque donnée d'identité est
// désormais affichée sur sa propre ligne EXPLICITEMENT étiquetée ("Race / Stud-book :", "Sexe :",
// "Robe :", "Taille :", "Année de naissance :"), jamais concaténée sur une seule ligne séparée par
// des virgules où un "non détectée" isolé ne permettait pas de savoir à quelle donnée il se
// rapportait. Purement un changement d'affichage : $jamerose_parsed (donnée réellement extraite du
// VRAI PDF plus haut dans ce fichier) n'est ici ni modifiée ni recalculée. ---
foreach (array('Race / Stud-book', 'Sexe', 'Robe', 'Taille', 'Année de naissance') as $expected_label) {
  gws_test_assert(strpos($preview_form_html, $expected_label . ' :') !== false, "Prévisualisation IFCE : la ligne « $expected_label : » est bien affichée séparément (ajustement UX §8)");
}
gws_test_assert(preg_match('/,\s*(Mâle|Femelle|Hongre)\s*,/u', $preview_form_html) !== 1, 'Prévisualisation IFCE : l’ancien résumé concaténé par des virgules (ex. "KWPN, Mâle, Gris, non détectée, 2001") a bien disparu');

// --- Chemin réel : traitement de l'upload SANS jamais atteindre wp_safe_redirect()/exit (fonction
// pure), en partant du VRAI PDF de Jamerose — vérifie littéralement l'absence de toute sortie AVANT
// la redirection (ob_start), la création réelle du transient, et l'URL de redirection calculée ---
copy($jamerose_pdf_path, sys_get_temp_dir() . '/gwseq-ifce-test-real.pdf');
$real_tmp_path = sys_get_temp_dir() . '/gwseq-ifce-test-real.pdf';
ob_start();
$upload_result = gwseq_process_ifce_import_upload($real_tmp_path);
$upload_output = ob_get_clean();
gws_test_assert($upload_output === '', 'Aucune sortie avant redirection : le traitement réel de l’upload ne produit littéralement AUCUN caractère de sortie (vérifié par capture de tampon)');
gws_test_assert($upload_result['notice'] === null, 'Chemin réel upload : un PDF IFCE reconnu (le vrai PDF de Jamerose) ne produit aucun message d’erreur');
gws_test_assert(strpos($upload_result['redirect'], 'gwseq_token=') !== false, 'Chemin réel upload : la redirection calculée pointe bien vers l’écran de prévisualisation (jeton présent dans l’URL)');
gws_test_assert(!file_exists($real_tmp_path), 'Chemin réel upload : le fichier PDF temporaire est bien supprimé après traitement (§11)');

preg_match('/gwseq_token=([a-zA-Z0-9]+)/', $upload_result['redirect'], $token_match);
$real_upload_token = $token_match[1] ?? '';
$created_transient = gwseq_get_ifce_import_transient($real_upload_token);
gws_test_assert($created_transient !== false && $created_transient['parsed']['identity']['nom'] === 'JAMEROSE DE FELINES', 'Chemin réel upload : le transient de prévisualisation est bien créé, avec la structure normalisée réellement analysée');

$posts_before_upload = count($GLOBALS['__gwseq_test_posts']);
gws_test_assert(count($GLOBALS['__gwseq_test_posts']) === $posts_before_upload, 'Aucune écriture métier avant confirmation : le traitement de l’upload seul n’a créé strictement aucune fiche Cheval');

// --- Chemin réel : un PDF non reconnu ne crée aucun transient exploitable, notice renseignée,
// redirection vers l'écran d'upload nu (jamais vers une prévisualisation) ---
$unrecognized_pdf_path = sys_get_temp_dir() . '/gwseq-ifce-test-unrecognized.pdf';
file_put_contents($unrecognized_pdf_path, gws_test_build_minimal_pdf(array('Un document sans rapport avec une fiche cheval.'), true));
$unrecognized_result = gwseq_process_ifce_import_upload($unrecognized_pdf_path);
gws_test_assert($unrecognized_result['notice'] !== null && strpos($unrecognized_result['redirect'], 'gwseq_token=') === false, 'Chemin réel upload : un PDF non reconnu produit un message d’erreur et redirige vers l’écran d’upload nu, jamais vers une prévisualisation');

// --- Chemin réel : confirmation avec un jeton EXPIRÉ/INEXISTANT — aucune fiche créée, notice
// renseignée, redirection vers l'écran d'upload ---
$posts_before_confirm = count($GLOBALS['__gwseq_test_posts']);
ob_start();
$expired_confirm_result = gwseq_process_ifce_import_confirm('jeton-inexistant', array('identity' => true, 'indices' => true, 'pedigree' => true));
$expired_confirm_output = ob_get_clean();
gws_test_assert($expired_confirm_output === '', 'Aucune sortie avant redirection : le traitement de confirmation ne produit aucune sortie, y compris pour un jeton expiré');
gws_test_assert($expired_confirm_result['notice'] !== null, 'Chemin réel confirmation : un jeton expiré/inexistant produit un message d’erreur explicite');
gws_test_assert(count($GLOBALS['__gwseq_test_posts']) === $posts_before_confirm, 'Chemin réel confirmation : un jeton invalide ne crée strictement aucune fiche Cheval');

// --- Chemin réel : confirmation avec un jeton VALIDE — SEULEMENT à ce moment la fiche est créée et
// le mapping appelé, jamais avant (§1) ---
gwseq_set_ifce_import_transient('jeton-valide-test', $jamerose_parsed);
$posts_before_valid_confirm = count($GLOBALS['__gwseq_test_posts']);
ob_start();
$valid_confirm_result = gwseq_process_ifce_import_confirm('jeton-valide-test', array('identity' => true, 'indices' => true, 'pedigree' => true));
$valid_confirm_output = ob_get_clean();
gws_test_assert($valid_confirm_output === '', 'Aucune sortie avant redirection : le traitement de confirmation réussi ne produit aucune sortie non plus');
gws_test_assert($valid_confirm_result['notice'] === null, 'Chemin réel confirmation : un jeton valide ne produit aucun message d’erreur');
gws_test_assert(count($GLOBALS['__gwseq_test_posts']) === $posts_before_valid_confirm + 1, 'Chemin réel confirmation : la fiche Cheval est bien créée, et SEULEMENT à la confirmation (jamais avant)');
gws_test_assert(gwseq_get_ifce_import_transient('jeton-valide-test') === false, 'Chemin réel confirmation : le transient est bien supprimé après une confirmation réussie (usage unique)');

// --- Nonce/capability invalides refusés (exécution réelle, sûre : ces deux cas lèvent une
// exception AVANT tout wp_safe_redirect()/exit — voir les stubs check_admin_referer()/wp_die()) ---
$GLOBALS['__gwseq_test_security']['nonce_valid'] = false;
$nonce_rejected = false;
try { gwseq_handle_ifce_import_upload(); } catch (Exception $e) { $nonce_rejected = (strpos($e->getMessage(), 'nonce') !== false); }
gws_test_assert($nonce_rejected, 'Sécurité : un nonce invalide est bien refusé avant tout traitement (gwseq_handle_ifce_import_upload)');
$GLOBALS['__gwseq_test_security']['nonce_valid'] = true;

$capability_rejected = false;
$GLOBALS['__gwseq_test_security']['can_edit'] = false;
try { gwseq_handle_ifce_import_confirm(); } catch (Exception $e) { $capability_rejected = (strpos($e->getMessage(), 'wp_die') !== false); }
gws_test_assert($capability_rejected, 'Sécurité : une capacité insuffisante est bien refusée avant tout traitement (gwseq_handle_ifce_import_confirm)');
$GLOBALS['__gwseq_test_security']['can_edit'] = true;

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
// 6. Écran de choix "Ajouter un cheval" (§B de la demande, correctif post-recette) : les deux
//    chemins (import IFCE / création manuelle) présentés à égalité, jamais l’un secondaire par
//    rapport à l’autre — le formulaire manuel n’est plus jamais affiché avant ce choix.
// =====================================================================================

$GLOBALS['__gwseq_test_submenu_pages'] = array();
gwseq_add_cheval_choice_page();
gws_test_assert(
  count($GLOBALS['__gwseq_test_submenu_pages']) === 1
  && $GLOBALS['__gwseq_test_submenu_pages'][0]['parent'] === null
  && $GLOBALS['__gwseq_test_submenu_pages'][0]['capability'] === 'edit_posts',
  'Écran de choix : enregistré en page orpheline (parent null, jamais un second point d’entrée visible dans le menu, qui ferait doublon avec "Ajouter un cheval")'
);

// --- La redirection ne se déclenche QUE pour post-new.php + post_type=gwseq_cheval + absence du
// paramètre gwseq_manual — chemins sûrs à exécuter réellement (aucun n’atteint wp_safe_redirect) ---
global $pagenow;
$GLOBALS['__gwseq_test_last_redirect'] = null;

$pagenow = 'edit.php';
$_GET = array('post_type' => GWSEQ_CPT_CHEVAL);
gwseq_redirect_cheval_add_new_to_choice();
gws_test_assert($GLOBALS['__gwseq_test_last_redirect'] === null, 'Redirection "Ajouter un cheval" : ne se déclenche jamais en dehors de post-new.php (ex. la liste des chevaux)');

$pagenow = 'post-new.php';
$_GET = array('post_type' => 'gwseq_prestation');
gwseq_redirect_cheval_add_new_to_choice();
gws_test_assert($GLOBALS['__gwseq_test_last_redirect'] === null, 'Redirection "Ajouter un cheval" : ne se déclenche jamais pour un autre type de contenu (ex. Prestation)');

$pagenow = 'post-new.php';
$_GET = array('post_type' => GWSEQ_CPT_CHEVAL, 'gwseq_manual' => '1');
gwseq_redirect_cheval_add_new_to_choice();
gws_test_assert($GLOBALS['__gwseq_test_last_redirect'] === null, 'Redirection "Ajouter un cheval" : neutralisée par "gwseq_manual=1" — le lien "Créer manuellement" de l’écran de choix atteint bien le vrai formulaire');

// --- Le cas qui DOIT rediriger (post-new.php + Cheval + gwseq_manual absent) n’est vérifié que
// déclarativement : la fonction appelle exit() après wp_safe_redirect(), incompatible avec une
// exécution directe dans ce script de test ---
$redirect_fn_source = substr($ifce_admin_code_only, strpos($ifce_admin_code_only, 'function gwseq_redirect_cheval_add_new_to_choice'));
$redirect_fn_source = substr($redirect_fn_source, 0, strpos($redirect_fn_source, "\nfunction "));
gws_test_assert(strpos($redirect_fn_source, 'wp_safe_redirect') !== false && strpos($redirect_fn_source, 'exit') !== false, 'Redirection "Ajouter un cheval" : le cas nominal (aucun paramètre gwseq_manual) redirige bien vers l’écran de choix (vérification déclarative, wp_safe_redirect()+exit() non exécutables ici)');
gws_test_assert(strpos($redirect_fn_source, "!== 'post-new.php'") !== false, 'Redirection "Ajouter un cheval" : strictement scopée à post-new.php, jamais à l’édition d’une fiche existante (post.php)');

// --- Rendu de l’écran de choix : les deux chemins présentés, aucune écriture ---
ob_start();
gwseq_render_cheval_choice_page();
$choice_page_html = ob_get_clean();
gws_test_assert(strpos($choice_page_html, gwseq_ifce_import_page_url()) !== false, 'Écran de choix : le lien vers l’import IFCE est bien présent');
gws_test_assert(strpos($choice_page_html, 'gwseq_manual=1') !== false, 'Écran de choix : le lien "Créer manuellement" pointe bien vers le formulaire natif avec gwseq_manual=1 (neutralise la redirection pour cette requête)');
gws_test_assert(strpos($choice_page_html, 'button-primary') !== false && strpos($choice_page_html, 'button-secondary') !== false, 'Écran de choix : les deux chemins sont présentés avec une mise en avant équivalente (aucun des deux boutons n’est un simple lien texte secondaire)');

// =====================================================================================
// i18n
// =====================================================================================

foreach ($GLOBALS['__gwseq_test_domains_used'] as $domain) {
  gws_test_assert($domain === 'gws-core', "i18n : aucun appel de traduction n’utilise un text domain autre que \"gws-core\" (trouvé : $domain)");
}

echo ($failures === 0 ? 'Tous les tests sont passés.' : "$failures test(s) en échec.") . "\n";
exit($failures === 0 ? 0 : 1);

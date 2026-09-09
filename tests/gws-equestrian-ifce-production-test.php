<?php
/**
 * Vérifie la Production directe structurée des juments importée depuis l'IFCE (Lot 2B.2, verdict
 * READY FOR 2B.2 des audits 2B.1/2B.1 bis/2B.1 ter) : extraction de la Zone Production (positions
 * X/Y, multi-page, profondeur relative avec tolérance, exclusion des lignes "saillie", produits sans
 * nom jamais importés), modèle de stockage et fusion non destructive au réimport, resolver
 * GWS + externe, rattachement certain/probable, actualisation ISO/ICC/IDR d'un produit GWS lié, et
 * garde de sexe de bout en bout (extraction, stockage, resolver, mapping).
 *
 * Utilise les VRAIS PDF IFCE de Nacelle d'Elle et de Teldame de la Nutria (`tests/fixtures/`),
 * exactement les deux documents ayant servi à valider cette architecture au fil des audits 2B.1 à
 * 2B.1 ter — jamais un texte pré-extrait artificiellement pour ces vérifications d'extraction.
 *
 * Ne fait pas partie des paquets livrés (gws-core.zip / gws-starter.zip).
 */

$failures = 0;
function gws_test_assert($condition, $label) {
  global $failures;
  if ($condition) { echo "OK   - $label\n"; }
  else { echo "FAIL - $label\n"; $failures++; }
}

// --- Stubs WordPress minimaux (mêmes conventions que le reste de ce dossier) ---
function wp_unslash($value) { return is_array($value) ? array_map('wp_unslash', $value) : stripslashes((string) $value); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value)); }
function esc_url_raw($value) { $value = trim((string) $value); return $value === '' ? '' : $value; }
function esc_url($value) { return $value; }
function absint($value) { return abs((int) $value); }
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
// FIDÈLE au comportement réel de selected()/checked() (WordPress core, via
// _checked_selected_helper()) : échouent par défaut ($echo = true), comme disabled() ci-dessous —
// convention déjà utilisée telle quelle dans includes/ifce-import-admin.php (appels sans echo()
// explicite, en confiance dans ce comportement natif).
function selected($a, $b = true, $echo = true) { $r = $a == $b ? ' selected' : ''; if ($echo) echo $r; return $r; }
function checked($a, $b = true, $echo = true) { $r = $a == $b ? ' checked' : ''; if ($echo) echo $r; return $r; }
function disabled($a, $b = true, $echo = true) { $r = $a == $b ? ' disabled' : ''; if ($echo) echo $r; return $r; }
function wp_nonce_field($action, $field) { echo '<input type="hidden" name="' . esc_attr($field) . '" value="stub-nonce">'; }
function wp_json_encode($data, $options = 0, $depth = 512) { return json_encode($data, $options, $depth); }
function submit_button($text = '', $type = 'primary', $name = 'submit', $wrap = true) { echo '<button>' . esc_html($text) . '</button>'; }
function metadata_exists($type, $post_id, $key) { return array_key_exists($key, $GLOBALS['__gwseq_test_meta'][$post_id] ?? array()); }
function sanitize_html_class($value) { return preg_replace('/[^A-Za-z0-9_-]/', '', (string) $value); }

function remove_accents($text) {
  $map = array(
    'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ã' => 'a', 'å' => 'a',
    'À' => 'A', 'Â' => 'A', 'Ä' => 'A', 'Á' => 'A', 'Ã' => 'A', 'Å' => 'A',
    'ç' => 'c', 'Ç' => 'C', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
    'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
    'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I', 'ñ' => 'n', 'Ñ' => 'N',
    'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o', 'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Ö' => 'O', 'Õ' => 'O',
    'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U',
    'ý' => 'y', 'ÿ' => 'y', 'Ý' => 'Y', 'œ' => 'oe', 'Œ' => 'OE', 'æ' => 'ae', 'Æ' => 'AE',
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
// Lot SHF : add_filter()/apply_filters() sont désormais de VRAIS stubs capturants (jamais des
// no-op) — indispensables au seul point d'extension prévu pour mocker le réseau dans les tests
// (`gwseq_ifce_shf_fetch_override`, voir includes/ifce-shf-enrichment.php).
$GLOBALS['__gwseq_test_filters'] = array();
function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) { $GLOBALS['__gwseq_test_filters'][$hook][] = $callback; }
function remove_all_filters($hook) { unset($GLOBALS['__gwseq_test_filters'][$hook]); }
function apply_filters($hook, ...$args) {
  foreach ($GLOBALS['__gwseq_test_filters'][$hook] ?? array() as $cb) { $args[0] = call_user_func_array($cb, $args); }
  return $args[0];
}
function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {}
$GLOBALS['__gwseq_test_meta_boxes'] = array();
function add_meta_box($id, $title, $callback, $post_type = null, $context = 'advanced', $priority = 'default') { $GLOBALS['__gwseq_test_meta_boxes'][] = $id; }
function add_submenu_page($parent, $title, $menu_title, $capability, $slug, $callback) {
  $GLOBALS['__gwseq_test_submenu_pages'][] = compact('parent', 'title', 'menu_title', 'capability', 'slug');
}
$GLOBALS['__gwseq_test_submenu_pages'] = array();

// --- "Base de données" en mémoire : posts, meta, transients — avec un VRAI get_posts()/meta_query ---
$GLOBALS['__gwseq_test_posts'] = array();
$GLOBALS['__gwseq_test_meta'] = array();
$GLOBALS['__gwseq_test_transients'] = array();
$GLOBALS['__gwseq_test_next_post_id'] = 1000;

function gws_test_make_post($id, $post_type, $title, $status = 'publish') {
  $GLOBALS['__gwseq_test_posts'][$id] = array('post_type' => $post_type, 'post_status' => $status, 'post_title' => $title);
}
function gws_test_make_post_object($id) {
  $p = $GLOBALS['__gwseq_test_posts'][$id];
  return (object) array('ID' => $id, 'post_type' => $p['post_type'], 'post_status' => $p['post_status'], 'post_title' => $p['post_title']);
}
function get_post_type($post_id) { return $GLOBALS['__gwseq_test_posts'][$post_id]['post_type'] ?? false; }
function get_post($post_id) { return isset($GLOBALS['__gwseq_test_posts'][$post_id]) ? gws_test_make_post_object($post_id) : null; }
function get_the_title($post) {
  $id = is_object($post) ? $post->ID : $post;
  return $GLOBALS['__gwseq_test_posts'][$id]['post_title'] ?? '';
}
function get_edit_post_link($post_id, $context = 'display') { return 'https://example.test/wp-admin/post.php?post=' . (int) $post_id . '&action=edit'; }

function update_post_meta($post_id, $key, $value) { $GLOBALS['__gwseq_test_meta'][$post_id][$key] = $value; return true; }
function get_post_meta($post_id, $key, $single = false) { return $GLOBALS['__gwseq_test_meta'][$post_id][$key] ?? ''; }
function delete_post_meta($post_id, $key) { unset($GLOBALS['__gwseq_test_meta'][$post_id][$key]); return true; }

function gws_test_meta_query_matches($post_id, $clause) {
  if (isset($clause['key'])) {
    return (string) get_post_meta($post_id, $clause['key'], true) === (string) $clause['value'];
  }
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
  foreach ($GLOBALS['__gwseq_test_posts'] as $id => $post) {
    if ($post['post_type'] !== $post_type) continue;
    if (!in_array($post['post_status'], $statuses, true)) continue;
    if (in_array((int) $id, $exclude, true)) continue;
    if ($meta_query && !gws_test_meta_query_matches($id, $meta_query)) continue;
    $results[] = gws_test_make_post_object($id);
  }
  usort($results, function ($a, $b) { return strcmp($a->post_title, $b->post_title); });
  return $results;
}

function set_transient($key, $value, $ttl) { $GLOBALS['__gwseq_test_transients'][$key] = $value; return true; }
function get_transient($key) { return $GLOBALS['__gwseq_test_transients'][$key] ?? false; }
function delete_transient($key) { unset($GLOBALS['__gwseq_test_transients'][$key]); return true; }

function get_current_user_id() { return $GLOBALS['__gwseq_test_current_user_id'] ?? 1; }
$GLOBALS['__gwseq_test_user_meta'] = array();
function get_user_meta($user_id, $key, $single = false) { return $GLOBALS['__gwseq_test_user_meta'][$user_id][$key] ?? ''; }
function update_user_meta($user_id, $key, $value) { $GLOBALS['__gwseq_test_user_meta'][$user_id][$key] = $value; return true; }
$GLOBALS['__gwseq_enqueued'] = array();
function wp_enqueue_script($handle, ...$rest) { $GLOBALS['__gwseq_enqueued'][] = $handle; }
function wp_enqueue_style($handle, ...$rest) { $GLOBALS['__gwseq_enqueued'][] = $handle; }
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
function check_admin_referer($action, $field) {
  if (!$GLOBALS['__gwseq_test_security']['nonce_valid']) throw new Exception('check_admin_referer: invalid nonce');
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
require $module_dir . 'includes/ifce-production-pdf-text.php';
require $module_dir . 'includes/ifce-production-parser.php';
require $module_dir . 'includes/ifce-production-store.php';
require $module_dir . 'includes/ifce-shf-enrichment.php';
require $module_dir . 'includes/ifce-import-mapper.php';
require $module_dir . 'includes/ifce-import-admin.php';

$ifce_mapper_source = file_get_contents($module_dir . 'includes/ifce-import-mapper.php');
$ifce_production_store_source = file_get_contents($module_dir . 'includes/ifce-production-store.php');

function gws_test_strip_php_comments($source) {
  $code = '';
  foreach (token_get_all($source) as $token) {
    if (is_array($token) && in_array($token[0], array(T_COMMENT, T_DOC_COMMENT), true)) continue;
    $code .= is_array($token) ? $token[1] : $token;
  }
  return $code;
}
$ifce_mapper_code_only = gws_test_strip_php_comments($ifce_mapper_source);
$ifce_production_store_code_only = gws_test_strip_php_comments($ifce_production_store_source);

$nacelle_pdf_path = __DIR__ . '/fixtures/ifce-nacelle-d-elle.pdf';
$teldame_pdf_path = __DIR__ . '/fixtures/ifce-teldame-de-la-nutria.pdf';
gws_test_assert(is_readable($nacelle_pdf_path), 'Fixture : le vrai PDF de Nacelle d’Elle est bien présent dans tests/fixtures/');
gws_test_assert(is_readable($teldame_pdf_path), 'Fixture : le vrai PDF de Teldame de la Nutria est bien présent dans tests/fixtures/');

// =====================================================================================
// 1. Extraction Zone Production — Nacelle d'Elle (7 produits directs attendus, cf. audit 2B.1)
// =====================================================================================

$nacelle_binary = file_get_contents($nacelle_pdf_path);
$nacelle_production = gwseq_ifce_extract_production_from_pdf_string($nacelle_binary);

gws_test_assert($nacelle_production['found'] === true, 'Nacelle : titre "Production" bien trouvé');
gws_test_assert(count($nacelle_production['entries']) === 7, 'Nacelle : exactement 7 produits directs détectés (audit 2B.1)');

$nacelle_expected_names = array('VAILLANT DE FELINES', 'ATWOOD DE FELINES', 'COTICE DE FELINES', 'DIVINE DE FELINES', 'ESPOIR DE FELINES', 'GAILLARD DE FELINES', 'KONIVENCE DE FELINES');
$nacelle_actual_names = array_column($nacelle_production['entries'], 'nom');
gws_test_assert($nacelle_actual_names === $nacelle_expected_names, 'Nacelle : les 7 produits sont exacts, ET dans l’ordre du document (jamais un tri par nom/année)');

gws_test_assert($nacelle_production['entries'][0]['annee'] === 2009 && $nacelle_production['entries'][6]['annee'] === 2020, 'Nacelle : années exactes du premier (2009) et du dernier (2020) produit');
gws_test_assert($nacelle_production['entries'][1]['pere'] === 'LANDO', 'Nacelle : père d’Atwood de Félines exact ("LANDO")');
gws_test_assert($nacelle_production['ignored']['niveau_superieur'] > 0, 'Nacelle : au moins un petit-enfant (niveau 2) a bien été exclu, jamais importé comme produit direct');
gws_test_assert($nacelle_production['ignored']['saillie'] === 0 && $nacelle_production['ignored']['sans_nom'] === 0, 'Nacelle : aucune ligne "saillie" ni produit sans nom sur ce document (comportement attendu, non régressé)');

foreach ($nacelle_production['entries'] as $entry) {
  gws_test_assert(!array_key_exists('bso', $entry) && !array_key_exists('bco', $entry), 'Nacelle : structurellement, une entrée de Production ne porte JAMAIS de clé BSO/BCC/BDR (§11 — indices génétiques des produits jamais importés)');
  gws_test_assert(array_key_exists('iso', $entry) && array_key_exists('icc', $entry) && array_key_exists('idr', $entry), 'Nacelle : chaque entrée porte bien ses trois indices sportifs (même vides)');
}

// =====================================================================================
// 2. Extraction Zone Production — Teldame de la Nutria (multi-page 16->17, tolérance X, saillie,
//    produits sans nom, identifiant provisoire "QZ") — audit 2B.1 ter
// =====================================================================================

$teldame_binary = file_get_contents($teldame_pdf_path);
$teldame_production = gwseq_ifce_extract_production_from_pdf_string($teldame_binary);

gws_test_assert($teldame_production['found'] === true, 'Teldame : titre "Production" bien trouvé (page 16)');
gws_test_assert(count($teldame_production['entries']) === 18, 'Teldame : 18 produits directs importables — réconciliation exacte avec le compteur IFCE "18 prod." une fois les produits totalement sans nom exclus (2B.1 ter), sans jamais viser ce compteur comme une cible');
gws_test_assert($teldame_production['ignored']['saillie'] === 4, 'Teldame : les 4 occurrences de lignes "saillie par [étalon]" (2 en niveau 1, 2 en niveau 2 imbriqué) sont bien exclues, jamais importées comme produits (2B.1 ter)');
gws_test_assert($teldame_production['ignored']['sans_nom'] === 2, 'Teldame : les 2 poulains 2026 totalement sans nom ("f par [étalon]", aucun identifiant) sont bien exclus (§9)');
gws_test_assert($teldame_production['ignored']['niveau_superieur'] > 20, 'Teldame : un grand nombre de petits-enfants (niveau 2, nesting extensif) sont bien exclus de la Production directe');

$teldame_names = array_column($teldame_production['entries'], 'nom');
gws_test_assert(in_array('QZ', $teldame_names, true), 'Teldame : le poulain identifié uniquement par un code provisoire "QZ" EST importé — un identifiant provisoire n’est jamais traité comme une absence de nom (§9)');
gws_test_assert($teldame_names[0] === 'CHUMBA LS' && end($teldame_names) === 'QZ', 'Teldame : ordre du document conservé de bout en bout, y compris à travers le changement de page 16 -> 17');

// Multi-page sans second titre "Production" (§6) : au moins un produit détecté vient bien de la
// page 17 (les 5 derniers produits nommés du document, situés après le changement de page).
gws_test_assert(in_array('NAPOLEON DE BAUMONT', $teldame_names, true) && in_array('PTITE DAME D\'AUBIGNY', $teldame_names, true), 'Teldame : des produits situés sur la page 17 (après le changement de page, sans second titre "Production") sont bien détectés — confirme la poursuite multi-page (§6)');

// Tolérance de regroupement par X (§7) : Kingsley de Reux et Monterrey d'Aubigny sont à x≈20.2,
// les autres produits directs de ce document à x≈20.6 — un écart de 0.4, bien inférieur à la
// tolérance, sans jamais fusionner avec le niveau 2 (x≈42.7, écart de plus de 22).
gws_test_assert(in_array('KINGSLEY DE REUX', $teldame_names, true) && in_array('CHUMBA LS', $teldame_names, true), 'Teldame : deux produits directs à des X légèrement différents (20.2 vs 20.6) sont bien classés au MÊME niveau 1 grâce à la tolérance de regroupement (§7), jamais une égalité stricte');

$chumba_entry = $teldame_production['entries'][array_search('CHUMBA LS', $teldame_names, true)];
gws_test_assert($chumba_entry['annee'] === 2014 && $chumba_entry['iso']['valeur'] === 132, 'Teldame : Chumba LS — année et ISO 132 correctement extraits (indice porté sur la même ligne que "3 prod.", correctement isolé)');
gws_test_assert($chumba_entry['pere'] === 'CARUSSO LS LA SILLA', 'Teldame : père de Chumba LS exact, marqueur pays "(MEX)" et stud-book "oes" bien retirés');

// =====================================================================================
// 3. Absence de section Production — état valide, jamais une erreur (§6). Jamerose de Félines
//    (2019, aucune Production propre — trop jeune, cf. audit 2B.1 bis) sert de fixture de référence.
// =====================================================================================

$jamerose_binary = file_get_contents(__DIR__ . '/fixtures/ifce-jamerose-de-felines.pdf');
$jamerose_production = gwseq_ifce_extract_production_from_pdf_string($jamerose_binary);
gws_test_assert($jamerose_production['found'] === false && $jamerose_production['entries'] === array(), 'Jamerose : absence de section Production -> état valide (found=false, entries vide), jamais une erreur');

// =====================================================================================
// 4. Garde de sexe (§5) au niveau de l'extraction déclenchée par l'upload — jamais une extraction de
//    Zone Production pour un sujet mâle/hongre, MÊME quand sa propre fiche IFCE contient une section
//    "Production" détaillant SES PROPRES produits (cas réel d'un étalon).
// =====================================================================================

$untouchable_upload = gwseq_process_ifce_import_upload_test_copy(__DIR__ . '/fixtures/ifce-untouchable-27.pdf');
gws_test_assert(($untouchable_upload['parsed']['identity']['sexe'] ?? null) === 'male', 'Untouchable 27 : sexe bien reconnu "male" (préalable au test de garde ci-dessous)');
gws_test_assert(($untouchable_upload['parsed']['production']['found'] ?? null) === false && ($untouchable_upload['parsed']['production']['entries'] ?? null) === array(), 'GARDE DE SEXE (§5) : Untouchable 27 (étalon) — aucune Zone Production jamais recherchée ni extraite, même si sa propre fiche IFCE détaille ses propres produits en tant que père');

/**
 * Copie temporaire jetable + appel réel de gwseq_process_ifce_import_upload() — évite de dupliquer
 * cette mécanique (copie/suppression du fichier temporaire) à chaque fixture testée ci-dessous.
 */
function gwseq_process_ifce_import_upload_test_copy($fixture_path) {
  $tmp = sys_get_temp_dir() . '/gwseq-production-test-' . bin2hex(random_bytes(6)) . '.pdf';
  copy($fixture_path, $tmp);
  $result = gwseq_process_ifce_import_upload($tmp);
  preg_match('/gwseq_token=([a-zA-Z0-9]+)/', $result['redirect'], $m);
  $transient = gwseq_get_ifce_import_transient($m[1] ?? '');
  return array('result' => $result, 'parsed' => $transient !== false ? $transient['parsed'] : null);
}

// =====================================================================================
// 5. Tolérance de regroupement X et exclusion "saillie" — cas synthétiques isolés, indépendants de
//    la fidélité d'extraction PDF réelle (§7-8).
// =====================================================================================

gws_test_assert(gwseq_ifce_production_entry_is_saillie('saillie par GRAND DUC DU PARADISO') === true, 'Détection saillie : forme nominale');
gws_test_assert(gwseq_ifce_production_entry_is_saillie('  saillie par X') === true, 'Détection saillie : espaces superflus après l’année tolérés');
gws_test_assert(gwseq_ifce_production_entry_is_saillie('SAILLIE PAR X') === true, 'Détection saillie : insensible à la casse');
gws_test_assert(gwseq_ifce_production_entry_is_saillie('CHUMBA LS (MEX) oes, f alezan de X par Y') === false, 'Détection saillie : un vrai produit nommé n’est jamais confondu avec une saillie');

$synthetic_zone = array(
  array('x' => 20.0, 'y' => 800, 'text' => '2020ALPHA sf, f bai de PERE1 sf par GPERE1 sf'),
  array('x' => 20.3, 'y' => 780, 'text' => '2021BETA sf, m bai de PERE2 sf par GPERE2 sf'),   // même palier (tolérance)
  array('x' => 42.0, 'y' => 760, 'text' => '2022GAMMA sf, f bai de PERE3 sf'),                  // niveau 2, exclu
  array('x' => 20.1, 'y' => 740, 'text' => '2023saillie par ETALON X'),                          // saillie, exclu
  array('x' => 20.2, 'y' => 720, 'text' => '2024f par PERE4 sf'),                                 // sans nom, exclu
);
$synthetic_result = gwseq_ifce_production_group_candidate_entries($synthetic_zone);
gws_test_assert(count($synthetic_result) === 5, 'Regroupement synthétique : 5 lignes -> 5 entrées candidates (aucune fusion accidentelle, chacune commence par une année)');

$min_x_test = min(array_column($synthetic_result, 'x'));
gws_test_assert(abs($min_x_test - 20.0) < 0.01, 'Regroupement synthétique : le X minimum du document est bien 20.0');

// =====================================================================================
// 6. Analyse d'une entrée : nom/père isolés correctement dans les deux formes rencontrées
// =====================================================================================

$parsed_named = gwseq_ifce_parse_production_entry_text('ALPHA (MEX) sf, f bai de PERE UN (BEL) sf par GRAND PERE sf');
gws_test_assert($parsed_named['nom'] === 'ALPHA' && $parsed_named['pere'] === 'PERE UN', 'Analyse d’entrée (avec nom) : nom et père isolés, marqueurs pays et stud-books retirés, le grand-père (après le second "par") ignoré');

$parsed_unnamed = gwseq_ifce_parse_production_entry_text('f par PERE DEUX selle francais par GRAND PERE DEUX belgian warmblood');
gws_test_assert($parsed_unnamed['nom'] === '' && $parsed_unnamed['pere'] === 'PERE DEUX', 'Analyse d’entrée (sans nom, ex. Teldame 2026) : aucun nom isolé (pas de virgule), père isolé après le premier "par", stud-book à deux mots ("selle francais") entièrement retiré');

$parsed_provisional = gwseq_ifce_parse_production_entry_text('QZ ri, f alezan de GIOVANI DE LA POMME bwp par SHINDLER DE MUZE sbs');
gws_test_assert($parsed_provisional['nom'] === 'QZ', 'Analyse d’entrée : un identifiant provisoire ("QZ ri") est bien retenu comme nom, le code de stud-book "ri" retiré');

$parsed_de_in_name = gwseq_ifce_parse_production_entry_text('KINGSLEY DE REUX sf, m bai de WINNINGMOOD VD ARENBERG bwp par DARCO bwp');
gws_test_assert($parsed_de_in_name['nom'] === 'KINGSLEY DE REUX', 'Analyse d’entrée : un nom contenant lui-même "DE" ("KINGSLEY DE REUX") n’est jamais tronqué — la virgule reste la frontière structurelle fiable, jamais une recherche naïve du premier "de "');

// =====================================================================================
// 7. Modèle de stockage : sanitation, lecture/écriture, garde de sexe
// =====================================================================================

gws_test_make_post(100, GWSEQ_CPT_CHEVAL, 'Jument Stockage');
gwseq_set_cheval_identity(100, array('_gwseq_sexe' => 'female', '_gwseq_annee_naissance' => 2010));

gwseq_set_cheval_production_externe(100, array(
  array('annee' => 2020, 'nom' => 'Produit Un', 'pere' => 'Etalon A', 'iso' => array('valeur' => 110, 'cd' => 0.5, 'annee' => 2023)),
));
$stored = gwseq_get_cheval_production_externe(100);
gws_test_assert(count($stored) === 1 && $stored[0]['nom'] === 'Produit Un' && $stored[0]['iso']['valeur'] === 110, 'Stockage : une entrée valide est bien persistée et relue à l’identique');

gwseq_set_cheval_production_externe(100, array(array('annee' => 2020, 'nom' => '', 'pere' => 'Sans Nom')));
gws_test_assert(count(gwseq_get_cheval_production_externe(100)) === 1, 'Stockage (§9) : une entrée SANS nom n’est jamais ajoutée — la seule entrée déjà stockée reste seule');

// --- Garde de sexe (§5) : jamais de suppression, seulement une invisibilité tant que non femelle ---
gwseq_set_cheval_identity(100, array('_gwseq_sexe' => 'male', '_gwseq_annee_naissance' => 2010));
gws_test_assert(gwseq_get_cheval_production_externe(100) === array(), 'GARDE DE SEXE (§5) : sexe corrigé vers "male" -> la Production stockée devient invisible via le getter gardé');
gws_test_assert(gwseq_get_cheval_production_externe_raw(100) !== array(), 'GARDE DE SEXE (§5) : la donnée BRUTE reste bien intacte en base — AUCUNE suppression silencieuse');
gwseq_set_cheval_identity(100, array('_gwseq_sexe' => 'female', '_gwseq_annee_naissance' => 2010));
gws_test_assert(count(gwseq_get_cheval_production_externe(100)) === 1, 'GARDE DE SEXE (§5) : sexe corrigé de nouveau vers "female" -> la Production réapparaît intacte, rien n’a jamais été perdu');

// =====================================================================================
// 8. Réimport non destructif et idempotent (§21) : dédup année+nom normalisé, rattachement préservé,
//    produit absent conservé, nouveau produit ajouté, snapshot actualisé.
// =====================================================================================

gws_test_make_post(101, GWSEQ_CPT_CHEVAL, 'Jument Réimport');
gwseq_set_cheval_identity(101, array('_gwseq_sexe' => 'female', '_gwseq_annee_naissance' => 2005));

gwseq_set_cheval_production_externe(101, array(
  array('annee' => 2015, 'nom' => 'Konivence', 'pere' => 'Etalon X', 'iso' => array('valeur' => 115, 'cd' => '', 'annee' => '')),
  array('annee' => 2016, 'nom' => 'Espoir', 'pere' => 'Etalon Y'),
));
// Rattachement déjà confirmé sur Konivence AVANT le réimport (simulé directement, indépendant du
// mécanisme de rattachement lui-même, testé séparément plus bas).
$before_reimport = gwseq_get_cheval_production_externe_raw(101);
$before_reimport[0]['cheval_gws_id'] = 555;
update_post_meta(101, '_gwseq_production_externe', wp_json_encode($before_reimport, JSON_UNESCAPED_UNICODE));

// Réimport : Konivence avec un NOUVEL ISO (120, évolution), Espoir absent du nouveau PDF, un nouveau
// produit "Gaillard" apparaît.
gwseq_set_cheval_production_externe(101, array(
  array('annee' => 2015, 'nom' => 'Konivence', 'pere' => 'Etalon X', 'iso' => array('valeur' => 120, 'cd' => '', 'annee' => '')),
  array('annee' => 2018, 'nom' => 'Gaillard', 'pere' => 'Etalon Z'),
));
$after_reimport = gwseq_get_cheval_production_externe(101);
gws_test_assert(count($after_reimport) === 3, 'Réimport (§21) : 3 produits après réimport (Konivence actualisé, Espoir conservé bien qu’absent du nouveau PDF, Gaillard ajouté) — jamais un doublon de Konivence');
$konivence_after = current(array_filter($after_reimport, function ($e) { return $e['nom'] === 'Konivence'; }));
gws_test_assert($konivence_after['iso']['valeur'] === 120, 'Réimport (§21) : le snapshot ISO de Konivence est bien actualisé (115 -> 120)');
gws_test_assert((int) $konivence_after['cheval_gws_id'] === 555, 'Réimport (§21) : le rattachement déjà confirmé de Konivence (cheval_gws_id) est bien PRÉSERVÉ, jamais écrasé par le réimport');
gws_test_assert(current(array_filter($after_reimport, function ($e) { return $e['nom'] === 'Espoir'; })) !== false, 'Réimport (§21) : Espoir, absent du nouveau PDF, n’est JAMAIS supprimé silencieusement');
gws_test_assert(current(array_filter($after_reimport, function ($e) { return $e['nom'] === 'Gaillard'; })) !== false, 'Réimport (§21) : Gaillard, nouveau produit, est bien ajouté');

// =====================================================================================
// 9. Rattachement certain / probable (§13-14)
// =====================================================================================

gws_test_make_post(200, GWSEQ_CPT_CHEVAL, 'Nacelle Test');
gwseq_set_cheval_identity(200, array('_gwseq_sexe' => 'female', '_gwseq_annee_naissance' => 2005));
gws_test_make_post(201, GWSEQ_CPT_CHEVAL, 'Konivence De Felines');
gwseq_set_cheval_identity(201, array('_gwseq_sexe' => 'male', '_gwseq_annee_naissance' => 2020));
gwseq_set_horse_parent(201, 'mother', array('mode' => 'gws', 'horse_id' => 200)); // filiation GWS déjà déclarée

gws_test_assert(gwseq_ifce_find_certain_production_match(200, 'Konivence de Felines', 2020) === 201, 'Rattachement CERTAIN (§13) : une filiation GWS déjà déclarée (Konivence a Nacelle Test comme mère GWS) est bien détectée, nom normalisé (casse/accents ignorés)');
gws_test_assert(gwseq_ifce_find_certain_production_match(200, 'Konivence de Felines', 1999) === 0, 'Rattachement CERTAIN : année différente -> aucun rattachement, jamais le nom seul');
gws_test_assert(gwseq_ifce_find_certain_production_match(200, 'Un Autre Nom', 2020) === 0, 'Rattachement CERTAIN : nom différent -> aucun rattachement');

gws_test_make_post(202, GWSEQ_CPT_CHEVAL, 'Gaillard De Felines');
gwseq_set_cheval_identity(202, array('_gwseq_sexe' => 'male', '_gwseq_annee_naissance' => 2016));
// Gaillard N'A PAS de filiation GWS déclarée vers Nacelle Test -> jamais "certain", seulement "probable"
gws_test_assert(gwseq_ifce_find_certain_production_match(200, 'Gaillard de Felines', 2016) === 0, 'Rattachement CERTAIN : sans filiation GWS déjà déclarée, même un nom+année exact ne donne jamais un rattachement certain');
gws_test_assert(gwseq_ifce_find_probable_production_match('Gaillard de Felines', 2016) === 202, 'Rattachement PROBABLE (§14) : nom normalisé + année identifient une fiche GWS existante, proposé mais jamais automatique');
gws_test_assert(gwseq_ifce_find_probable_production_match('Gaillard de Felines', 1999) === 0, 'Rattachement PROBABLE : année différente -> aucun rapprochement');
gws_test_assert(gwseq_ifce_find_probable_production_match('Un Nom Quelconque', 2016) === 0, 'Rattachement PROBABLE : le nom seul (sans correspondance) n’est jamais suffisant');

// --- Ambiguïté : deux fiches partagent le même nom normalisé ET la même année -> aucun rapprochement
// proposé, jamais un choix arbitraire ---
gws_test_make_post(203, GWSEQ_CPT_CHEVAL, 'Divine De Felines');
gwseq_set_cheval_identity(203, array('_gwseq_sexe' => 'female', '_gwseq_annee_naissance' => 2013));
gws_test_make_post(204, GWSEQ_CPT_CHEVAL, 'Divine De Felines');
gwseq_set_cheval_identity(204, array('_gwseq_sexe' => 'female', '_gwseq_annee_naissance' => 2013));
gws_test_assert(gwseq_ifce_find_probable_production_match('Divine de Felines', 2013) === 0, 'Rattachement PROBABLE : en cas d’ambiguïté (deux fiches identiques par nom+année), aucun rapprochement n’est jamais deviné');

// =====================================================================================
// 9bis. CORRECTIF RECETTE (cas réel Goldame d'Aubigny) : normalisation robuste aux variantes
// typographiquement équivalentes d'apostrophe — le nom extrait du PDF IFCE utilise toujours une
// apostrophe droite ASCII ('), tandis qu'une fiche GWS déjà enregistrée peut porter une apostrophe
// courbe (’/‘), un accent utilisé en guise d'apostrophe, une apostrophe modificatrice, OU encore une
// entité HTML restée littérale (&rsquo;/&#8217;/&apos;, résidu d'un import/copier-coller antérieur —
// même cause racine que le correctif 0.24.0 du module Partage). AVANT ce correctif, ces variantes
// étaient des chaînes différentes après normalisation -> aucun rapprochement PROBABLE proposé, alors
// même que nom et année correspondaient exactement (symptôme réel signalé en recette).
// =====================================================================================

gws_test_assert(
  gwseq_ifce_normalize_horse_name_for_match("Goldame d'Aubigny") === gwseq_ifce_normalize_horse_name_for_match("Goldame d\xE2\x80\x99Aubigny"),
  'CORRECTIF RECETTE : apostrophe droite (\') et apostrophe courbe (’, U+2019) normalisées de façon strictement identique'
);
gws_test_assert(
  gwseq_ifce_normalize_horse_name_for_match("Goldame d'Aubigny") === gwseq_ifce_normalize_horse_name_for_match("Goldame d\xE2\x80\x98Aubigny"),
  'CORRECTIF RECETTE : apostrophe courbe ouvrante (‘, U+2018) également canonisée'
);
gws_test_assert(
  gwseq_ifce_normalize_horse_name_for_match("Goldame d'Aubigny") === gwseq_ifce_normalize_horse_name_for_match("Goldame d\xCA\xBCAubigny"),
  'CORRECTIF RECETTE : apostrophe modificatrice (ʼ, U+02BC) également canonisée'
);
gws_test_assert(
  gwseq_ifce_normalize_horse_name_for_match("Goldame d'Aubigny") === gwseq_ifce_normalize_horse_name_for_match("Goldame d`Aubigny"),
  'CORRECTIF RECETTE : accent grave (`) utilisé en guise d’apostrophe également canonisé'
);
gws_test_assert(
  gwseq_ifce_normalize_horse_name_for_match("Goldame d'Aubigny") === gwseq_ifce_normalize_horse_name_for_match('Goldame d&rsquo;Aubigny'),
  'CORRECTIF RECETTE : entité HTML NOMMÉE encore littérale ("&rsquo;") décodée puis canonisée avant comparaison'
);
gws_test_assert(
  gwseq_ifce_normalize_horse_name_for_match("Goldame d'Aubigny") === gwseq_ifce_normalize_horse_name_for_match('Goldame d&#8217;Aubigny'),
  'CORRECTIF RECETTE : entité HTML NUMÉRIQUE ("&#8217;") décodée puis canonisée avant comparaison'
);
gws_test_assert(
  gwseq_ifce_normalize_horse_name_for_match("Goldame d'Aubigny") === gwseq_ifce_normalize_horse_name_for_match('Goldame d&apos;Aubigny'),
  'CORRECTIF RECETTE : entité HTML "&apos;" décodée puis canonisée avant comparaison'
);
gws_test_assert(
  gwseq_ifce_normalize_horse_name_for_match('Chevalière') !== gwseq_ifce_normalize_horse_name_for_match("Chevali're"),
  'Non-permissivité : la canonisation reste une liste FERMÉE de variantes d’apostrophe — un caractère réellement différent (accent aigu é vs apostrophe) ne doit jamais rendre deux noms distincts identiques après normalisation'
);

// --- Reproduction EXACTE du cas réel Goldame d'Aubigny : rapprochement PROBABLE désormais trouvé ---
gws_test_make_post(205, GWSEQ_CPT_CHEVAL, "Goldame d\xE2\x80\x99Aubigny"); // titre GWS réel, apostrophe courbe
gwseq_set_cheval_identity(205, array('_gwseq_sexe' => 'female', '_gwseq_annee_naissance' => 2016));
gws_test_assert(
  gwseq_ifce_find_probable_production_match("Goldame d'Aubigny", 2016) === 205, // nom IFCE réel, apostrophe droite
  'CORRECTIF RECETTE (cas réel) : "Goldame d\'Aubigny" (nom IFCE, apostrophe droite) est désormais bien rapproché de la fiche GWS "Goldame d’Aubigny" (apostrophe courbe) — année identique (2016) — AVANT ce correctif, aucun rapprochement n’était trouvé malgré une correspondance visuelle parfaite'
);

// =====================================================================================
// 10. Mapping (gwseq_ifce_map_production) : garde de sexe, rattachement certain/probable, actualisation
//     ISO/ICC/IDR UNIQUEMENT — jamais un autre champ du produit lié.
// =====================================================================================

// --- Garde de sexe : jamais de Production pour un mâle, même si explicitement demandé ---
gws_test_make_post(300, GWSEQ_CPT_CHEVAL, 'Etalon Test');
gwseq_set_cheval_identity(300, array('_gwseq_sexe' => 'male', '_gwseq_annee_naissance' => 2015));
gws_test_assert(gwseq_ifce_map_production(300, array(array('annee' => 2020, 'nom' => 'Produit Interdit'))) === false, 'GARDE DE SEXE (§5) : gwseq_ifce_map_production() refuse toute écriture pour un cheval dont le sexe n’est pas "female", quelle que soit la demande');
gws_test_assert(gwseq_get_cheval_production_externe_raw(300) === array(), 'GARDE DE SEXE (§5) : aucune donnée de Production n’a été écrite pour l’étalon');

// --- Rattachement certain, appliqué automatiquement (aucun choix nécessaire) + actualisation ISO ---
gws_test_make_post(301, GWSEQ_CPT_CHEVAL, 'Jument Mapping');
gwseq_set_cheval_identity(301, array('_gwseq_sexe' => 'female', '_gwseq_annee_naissance' => 2005));
gws_test_make_post(302, GWSEQ_CPT_CHEVAL, 'Produit Certain');
gwseq_set_cheval_identity(302, array('_gwseq_sexe' => 'male', '_gwseq_annee_naissance' => 2018));
gwseq_set_cheval_sport_indice(302, 'iso', array('valeur' => 115, 'cd' => '', 'annee' => ''));
gwseq_set_horse_parent(302, 'mother', array('mode' => 'gws', 'horse_id' => 301));
// Autres données du produit lié, qui ne doivent JAMAIS être modifiées par ce mécanisme (§18) :
gwseq_set_cheval_identity(302, array('_gwseq_sexe' => 'male', '_gwseq_annee_naissance' => 2018, '_gwseq_robe' => 'bai'));

gwseq_ifce_map_production(301, array(
  array('annee' => 2018, 'nom' => 'Produit Certain', 'pere' => 'Un Père', 'iso' => array('valeur' => 120, 'cd' => 0.8, 'annee' => 2024)),
));
$linked_after_map = gwseq_get_cheval_production_externe(301);
gws_test_assert(count($linked_after_map) === 1 && (int) $linked_after_map[0]['cheval_gws_id'] === 302, 'Mapping (§13) : rattachement certain appliqué automatiquement, sans le moindre choix explicite requis');
gws_test_assert(gwseq_get_cheval_sport_indice(302, 'iso')['valeur'] === 120, 'ACTUALISATION ISO (§15-19) : la fiche GWS liée voit son ISO passer de 115 à 120 — "la dernière actualisation validée gagne"');
gws_test_assert(gwseq_get_cheval_identity(302)['robe'] === 'bai' && gwseq_get_cheval_identity(302)['sexe'] === 'male' && gwseq_get_cheval_identity(302)['annee_naissance'] === 2018, 'LIMITATION STRICTE (§18) : robe/sexe/année de naissance du produit lié restent INCHANGÉS — seuls ISO/ICC/IDR sont jamais propagés par ce mécanisme');

// --- Modification manuelle ultérieure du produit lié -> devient la donnée courante, jamais écrasée
// par le snapshot IFCE déjà stocké sur l'entrée de Production (§20) ---
gwseq_set_cheval_sport_indice(302, 'iso', array('valeur' => 125, 'cd' => '', 'annee' => ''));
$resolved_after_manual = gwseq_get_horse_direct_production(301);
$resolved_konivence = current(array_filter($resolved_after_manual, function ($e) { return (int) $e['cheval_gws_id'] === 302; }));
gws_test_assert($resolved_konivence['iso']['valeur'] === 125, 'SOURCE DE VÉRITÉ (§20) : après une modification manuelle du produit lié (125), le resolver restitue bien 125 — jamais le snapshot IFCE figé (120) stocké sur l’entrée');

// --- Nouvel import IFCE ultérieur (§17) : actualise de nouveau, "la dernière actualisation validée
// gagne" jusqu'à la prochaine ---
gwseq_ifce_map_production(301, array(
  array('annee' => 2018, 'nom' => 'Produit Certain', 'pere' => 'Un Père', 'iso' => array('valeur' => 130, 'cd' => '', 'annee' => '')),
));
gws_test_assert(gwseq_get_cheval_sport_indice(302, 'iso')['valeur'] === 130, 'Nouvel import IFCE (§17) : le produit lié passe à 130 — un nouvel import validé redevient la donnée courante');

// --- Aucune valeur inventée : un indice non détecté (vide) n'écrase jamais une valeur déjà
// enregistrée sur le produit lié ---
gwseq_ifce_map_production(301, array(
  array('annee' => 2018, 'nom' => 'Produit Certain', 'pere' => 'Un Père', 'iso' => array('valeur' => '', 'cd' => '', 'annee' => '')),
));
gws_test_assert(gwseq_get_cheval_sport_indice(302, 'iso')['valeur'] === 130, 'Aucune valeur inventée : un snapshot sans ISO détecté (vide) n’écrase jamais l’ISO déjà enregistré sur le produit lié');

// --- Rattachement PROBABLE, via un choix explicite transmis par la prévisualisation ---
gws_test_make_post(303, GWSEQ_CPT_CHEVAL, 'Jument Mapping Probable');
gwseq_set_cheval_identity(303, array('_gwseq_sexe' => 'female', '_gwseq_annee_naissance' => 2000));
gws_test_make_post(304, GWSEQ_CPT_CHEVAL, 'Produit Probable');
gwseq_set_cheval_identity(304, array('_gwseq_sexe' => 'female', '_gwseq_annee_naissance' => 2019)); // sans filiation GWS déclarée
gwseq_ifce_map_production(303, array(
  array('annee' => 2019, 'nom' => 'Produit Probable', 'pere' => 'Un Père'),
), array(0 => array('mode' => 'link', 'horse_id' => 304)));
gws_test_assert((int) gwseq_get_cheval_production_externe(303)[0]['cheval_gws_id'] === 304, 'Mapping (§14) : un rattachement PROBABLE confirmé via le choix transmis est bien appliqué');

gws_test_make_post(305, GWSEQ_CPT_CHEVAL, 'Jument Mapping Non Confirme');
gwseq_set_cheval_identity(305, array('_gwseq_sexe' => 'female', '_gwseq_annee_naissance' => 2000));
gwseq_ifce_map_production(305, array(
  array('annee' => 2019, 'nom' => 'Produit Probable', 'pere' => 'Un Père'),
)); // aucun choix transmis
gws_test_assert((int) gwseq_get_cheval_production_externe(305)[0]['cheval_gws_id'] === 0, 'Mapping (§14) : sans choix explicite transmis, un rattachement seulement probable n’est JAMAIS appliqué automatiquement — le produit reste externe');

// --- Rattachement (probable confirmé ou certain) : la RÈGLE A CHANGÉ (correctif de recette, cas réel
// Goldame d'Aubigny) — confirmer un rattachement écrit désormais, EN RETOUR, une relation de
// filiation cohérente sur la fiche tierce liée (voir section dédiée "Cohérence bidirectionnelle"
// ci-dessous pour le détail des 5 cas) — jamais pour un rapprochement seulement PROPOSÉ mais non
// confirmé (voir la fiche 305 ci-dessus, restée externe, jamais examinée pour sa filiation).
gws_test_assert(gwseq_get_horse_parent(304, 'mother')['mode'] === 'gws' && gwseq_get_horse_parent(304, 'mother')['horse_id'] === 303, 'COHÉRENCE BIDIRECTIONNELLE (Cas C) : confirmer le rattachement PROBABLE de "Produit Probable" crée désormais la relation Mère GWS vers la jument (303) — aucune mère n’était renseignée avant');
gws_test_assert(gwseq_get_horse_parent(302, 'mother')['mode'] === 'gws' && gwseq_get_horse_parent(302, 'mother')['horse_id'] === 301, 'Non-régression (Cas A) : la filiation GWS de "Produit Certain" (déjà existante avant tout mapping) reste inchangée par le mapping de Production — déjà cohérente, aucune écriture supplémentaire');

// =====================================================================================
// 10bis. COHÉRENCE BIDIRECTIONNELLE PRODUCTION -> FILIATION (correctif de recette, cas réel
// Goldame d'Aubigny/Teldame de la Nutria) : gwseq_ifce_production_maternity_case() (5 cas A-E),
// gwseq_ifce_apply_production_maternity_case(), câblage dans gwseq_ifce_map_production(), et
// non-duplication du resolver après conversion.
// =====================================================================================

gws_test_make_post(600, GWSEQ_CPT_CHEVAL, 'Jument Maternite');
gwseq_set_cheval_identity(600, array('_gwseq_sexe' => 'female', '_gwseq_annee_naissance' => 2007));

// --- Cas A : filiation GWS déjà cohérente -> noop ---
gws_test_make_post(601, GWSEQ_CPT_CHEVAL, 'Produit Cas A');
gwseq_set_horse_parent(601, 'mother', array('mode' => 'gws', 'horse_id' => 600));
gws_test_assert(gwseq_ifce_production_maternity_case(601, 600) === 'noop', 'Cas A : filiation GWS déjà cohérente -> "noop"');

// --- Cas B : mère externe correspondant à la jument (nom normalisé + année) -> "convert" ---
gws_test_make_post(602, GWSEQ_CPT_CHEVAL, 'Produit Cas B Annee');
gwseq_set_horse_parent(602, 'mother', array('mode' => 'external', 'external' => array('name' => 'Jument Maternite', 'annee_naissance' => 2007)));
gws_test_assert(gwseq_ifce_production_maternity_case(602, 600) === 'convert', 'Cas B (nom + année correspondants) : mère externe -> "convert"');

gws_test_make_post(603, GWSEQ_CPT_CHEVAL, 'Produit Cas B Sans Annee');
gwseq_set_horse_parent(603, 'mother', array('mode' => 'external', 'external' => array('name' => 'Jument Maternite'))); // année absente côté externe
gws_test_assert(gwseq_ifce_production_maternity_case(603, 600) === 'convert', 'Cas B (§ "année lorsqu’elle est disponible") : nom correspondant, année ABSENTE côté externe -> "convert" tout de même, jamais bloqué par une donnée manquante');

// --- Cas C : aucune mère renseignée -> "create" ---
gws_test_make_post(604, GWSEQ_CPT_CHEVAL, 'Produit Cas C');
gws_test_assert(gwseq_ifce_production_maternity_case(604, 600) === 'create', 'Cas C : aucune mère renseignée -> "create"');

// --- Cas D : une AUTRE fiche GWS est déjà mère -> "conflict_gws", jamais écrasée ---
gws_test_make_post(605, GWSEQ_CPT_CHEVAL, 'Autre Jument GWS');
gws_test_make_post(606, GWSEQ_CPT_CHEVAL, 'Produit Cas D');
gwseq_set_horse_parent(606, 'mother', array('mode' => 'gws', 'horse_id' => 605));
gws_test_assert(gwseq_ifce_production_maternity_case(606, 600) === 'conflict_gws', 'Cas D : une autre fiche GWS déjà mère -> "conflict_gws"');

// --- Cas E : une mère externe DIFFÉRENTE (nom différent) est déjà renseignée -> "conflict_external" ---
gws_test_make_post(607, GWSEQ_CPT_CHEVAL, 'Produit Cas E Nom');
gwseq_set_horse_parent(607, 'mother', array('mode' => 'external', 'external' => array('name' => 'Une Autre Jument', 'annee_naissance' => 2007)));
gws_test_assert(gwseq_ifce_production_maternity_case(607, 600) === 'conflict_external', 'Cas E (nom différent) : mère externe différente -> "conflict_external"');

// --- Cas E : même nom, mais année DIFFÉRENTE des deux côtés -> ambigu, "conflict_external" par
// prudence (jamais deviné) ---
gws_test_make_post(608, GWSEQ_CPT_CHEVAL, 'Produit Cas E Annee');
gwseq_set_horse_parent(608, 'mother', array('mode' => 'external', 'external' => array('name' => 'Jument Maternite', 'annee_naissance' => 1999)));
gws_test_assert(gwseq_ifce_production_maternity_case(608, 600) === 'conflict_external', 'Cas E (nom identique, ANNÉES différentes et toutes deux connues) : "conflict_external", jamais deviné');

// --- gwseq_ifce_apply_production_maternity_case() : n'écrit RIEN pour noop/conflict_*, applique
// bien "create"/"convert" ---
gwseq_ifce_apply_production_maternity_case('conflict_gws', 606, 600);
gws_test_assert(gwseq_get_horse_parent(606, 'mother')['horse_id'] === 605, 'apply() : "conflict_gws" n’écrit strictement rien — la mère existante (605) reste intacte');
gwseq_ifce_apply_production_maternity_case('conflict_external', 607, 600);
gws_test_assert(gwseq_get_horse_parent(607, 'mother')['mode'] === 'external' && gwseq_get_horse_parent(607, 'mother')['external']['name'] === 'Une Autre Jument', 'apply() : "conflict_external" n’écrit strictement rien — la mère externe existante reste intacte');

gwseq_ifce_apply_production_maternity_case('create', 604, 600);
gws_test_assert(gwseq_get_horse_parent(604, 'mother')['mode'] === 'gws' && gwseq_get_horse_parent(604, 'mother')['horse_id'] === 600, 'apply() : "create" crée bien la relation Mère GWS');

// --- Cas B appliqué : conversion réelle, ANCIENNE branche externe conservée (inactive, jamais
// supprimée — conservation non destructive déjà garantie par gwseq_set_horse_parent(), aucune
// meta orpheline, aucune duplication : le resolver ne lit jamais la branche inactive) ---
gwseq_ifce_apply_production_maternity_case('convert', 602, 600);
$converted_mother = gwseq_get_horse_parent(602, 'mother');
gws_test_assert($converted_mother['mode'] === 'gws' && $converted_mother['horse_id'] === 600, 'apply() : "convert" bascule bien la relation Mère en mode GWS vers la jument');
gws_test_assert(is_array($converted_mother['external']) && $converted_mother['external']['name'] === 'Jument Maternite', 'CONSERVATION NON DESTRUCTIVE : l’ancien arbre externe reste lisible en base (inactif, jamais supprimé, aucune meta orpheline) après la conversion');

// --- Aucune destruction de donnée de pedigree NON concernée : le Père du produit converti, sans
// aucun rapport avec cette conversion, reste strictement intact ---
gwseq_set_horse_parent(602, 'father', array('mode' => 'external', 'external' => array('name' => 'Un Pere Independant')));
gwseq_ifce_apply_production_maternity_case('convert', 602, 600); // reconversion idempotente
gws_test_assert(gwseq_get_horse_parent(602, 'father')['external']['name'] === 'Un Pere Independant', 'NON-DESTRUCTION : le Père du produit, sans rapport avec cette conversion de la Mère, reste strictement intact');

// --- Câblage bout en bout dans gwseq_ifce_map_production() : Cas B (conversion) sur un vrai
// rattachement PROBABLE confirmé ---
gws_test_make_post(700, GWSEQ_CPT_CHEVAL, 'Jument E2E');
gwseq_set_cheval_identity(700, array('_gwseq_sexe' => 'female', '_gwseq_annee_naissance' => 2007));
gws_test_make_post(701, GWSEQ_CPT_CHEVAL, 'Produit E2E');
gwseq_set_cheval_identity(701, array('_gwseq_sexe' => 'female', '_gwseq_annee_naissance' => 2016));
gwseq_set_horse_parent(701, 'mother', array('mode' => 'external', 'external' => array('name' => 'Jument E2E', 'annee_naissance' => 2007)));
gwseq_ifce_map_production(700, array(
  array('annee' => 2016, 'nom' => 'Produit E2E', 'pere' => 'Un Père'),
), array(0 => array('mode' => 'link', 'horse_id' => 701)));
gws_test_assert(gwseq_get_horse_parent(701, 'mother')['mode'] === 'gws' && gwseq_get_horse_parent(701, 'mother')['horse_id'] === 700, 'BOUT EN BOUT (Cas B via mapper) : la confirmation du rattachement PROBABLE convertit bien la mère externe correspondante en relation GWS');

// --- Câblage bout en bout : un rapprochement PROBABLE proposé mais JAMAIS confirmé ne modifie
// AUCUNE filiation d'une fiche tierce (§ "aucun rapprochement probable non confirmé ne doit
// modifier le pedigree d'un autre Cheval GWS") ---
gws_test_make_post(702, GWSEQ_CPT_CHEVAL, 'Jument E2E Non Confirme');
gwseq_set_cheval_identity(702, array('_gwseq_sexe' => 'female', '_gwseq_annee_naissance' => 2007));
gws_test_make_post(703, GWSEQ_CPT_CHEVAL, 'Produit E2E Non Confirme');
gwseq_set_cheval_identity(703, array('_gwseq_sexe' => 'female', '_gwseq_annee_naissance' => 2016));
gwseq_set_horse_parent(703, 'mother', array('mode' => 'external', 'external' => array('name' => 'Jument E2E Non Confirme', 'annee_naissance' => 2007)));
gwseq_ifce_map_production(702, array(
  array('annee' => 2016, 'nom' => 'Produit E2E Non Confirme', 'pere' => 'Un Père'),
)); // AUCUN choix transmis -> rapprochement seulement proposé, jamais confirmé
gws_test_assert(gwseq_get_horse_parent(703, 'mother')['mode'] === 'external' && gwseq_get_horse_parent(703, 'mother')['external']['name'] === 'Jument E2E Non Confirme', 'BOUT EN BOUT : un rapprochement PROBABLE proposé mais NON confirmé ne touche STRICTEMENT RIEN à la filiation de la fiche tierce — celle-ci reste externe et inchangée');

// --- Câblage bout en bout : la relation n'est créée QU'À la validation globale de l'import — la
// simple DÉTECTION/proposition du rapprochement (gwseq_ifce_find_probable_production_match(), déjà
// utilisée en amont) n'écrit jamais rien par elle-même (vérification déclarative : cette fonction
// pure ne contient aucun appel d'écriture) ---
$probable_fn_body = substr($ifce_production_store_code_only, strpos($ifce_production_store_code_only, 'function gwseq_ifce_find_probable_production_match'));
$probable_fn_body = substr($probable_fn_body, 0, strpos($probable_fn_body, "\nfunction "));
foreach (array('update_post_meta', 'gwseq_set_horse_parent', 'gwseq_ifce_apply_production_maternity_case') as $write_marker) {
  gws_test_assert(strpos($probable_fn_body, $write_marker) === false, "BOUT EN BOUT : gwseq_ifce_find_probable_production_match() (simple détection/proposition) n’appelle jamais $write_marker() — la relation n’est créée qu’à la validation globale, jamais à la seule détection");
}

// --- Resolver SANS DOUBLON après conversion : gwseq_get_horse_offspring() ET
// gwseq_get_horse_direct_production() sont désormais cohérents, sans qu'aucun code du resolver
// n'ait eu besoin d'être modifié (le mécanisme de déduplication déjà existant, basé sur
// $linked_gws_ids, couvre déjà ce nouveau cas) ---
$e2e_offspring_ids = array_map(function ($p) { return $p->ID; }, gwseq_get_horse_offspring(700));
gws_test_assert(in_array(701, $e2e_offspring_ids, true), 'RESOLVER SANS DOUBLON : gwseq_get_horse_offspring(jument) inclut désormais le produit converti (descendant GWS relationnel à part entière)');

$e2e_production = gwseq_get_horse_direct_production(700);
$e2e_matches = array_values(array_filter($e2e_production, function ($e) { return (int) $e['cheval_gws_id'] === 701; }));
gws_test_assert(count($e2e_matches) === 1, 'RESOLVER SANS DOUBLON : le produit converti apparaît EXACTEMENT une fois dans gwseq_get_horse_direct_production(jument), jamais deux (une fois comme descendant GWS, une seconde fois comme entrée externe liée)');
gws_test_assert($e2e_matches[0]['source'] === 'gws', 'RESOLVER SANS DOUBLON : le produit converti est bien restitué comme source "gws" (fiche liée, jamais le snapshot externe désormais inactif)');

// =====================================================================================
// 11. Resolver gwseq_get_horse_direct_production() : fusion GWS + externe, jamais de doublon
// =====================================================================================

gws_test_make_post(400, GWSEQ_CPT_CHEVAL, 'Jument Resolver');
gwseq_set_cheval_identity(400, array('_gwseq_sexe' => 'female', '_gwseq_annee_naissance' => 2000));
gws_test_make_post(401, GWSEQ_CPT_CHEVAL, 'Produit GWS Relationnel');
gwseq_set_cheval_identity(401, array('_gwseq_sexe' => 'male', '_gwseq_annee_naissance' => 2015));
gwseq_set_horse_parent(401, 'mother', array('mode' => 'gws', 'horse_id' => 400));
gwseq_set_cheval_sport_indice(401, 'iso', array('valeur' => 100, 'cd' => '', 'annee' => ''));

gwseq_set_cheval_production_externe(400, array(
  array('annee' => 2015, 'nom' => 'Produit GWS Relationnel', 'pere' => 'X', 'cheval_gws_id' => 401), // même produit, déjà représenté via (1)
  array('annee' => 2018, 'nom' => 'Produit Externe Seul', 'pere' => 'Y'),
));

$resolved = gwseq_get_horse_direct_production(400);
gws_test_assert(count($resolved) === 2, 'Resolver (§12) : exactement 2 produits — jamais 3 — le produit à la fois GWS et externe n’apparaît qu’UNE seule fois');
gws_test_assert($resolved[0]['source'] === 'gws' && $resolved[0]['cheval_gws_id'] === 401 && $resolved[0]['iso']['valeur'] === 100, 'Resolver : le produit GWS relationnel est bien restitué depuis sa fiche courante (source de vérité), jamais depuis le snapshot externe');
gws_test_assert($resolved[1]['source'] === 'ifce' && $resolved[1]['nom'] === 'Produit Externe Seul', 'Resolver : le produit purement externe (sans rattachement) est bien restitué tel quel');

// --- Garde de sexe du resolver lui-même ---
gwseq_set_cheval_identity(400, array('_gwseq_sexe' => 'male', '_gwseq_annee_naissance' => 2000));
gws_test_assert(gwseq_get_horse_direct_production(400) === array(), 'GARDE DE SEXE (§5) : le resolver renvoie un tableau vide dès que le sujet n’est plus une femelle');

// =====================================================================================
// 12. Interface de prévisualisation/confirmation IFCE — bout en bout sur le VRAI PDF de Teldame,
//     y compris réimport sur une fiche existante (§21)
// =====================================================================================

// Titre alignant EXACTEMENT le nom officiel réel du PDF (nécessaire depuis le verrou d'identité de
// réimport, correctif recette — cette fiche n'a pas encore de _gwseq_ifce_id enregistré, cas
// "legacy" : nom officiel + année doivent concorder pour autoriser le réimport, voir
// gwseq_ifce_validate_reimport_identity()).
gws_test_make_post(500, GWSEQ_CPT_CHEVAL, 'TELDAME DE LA NUTRIA');
gwseq_set_cheval_identity(500, array('_gwseq_sexe' => 'female', '_gwseq_annee_naissance' => 2007));

$teldame_tmp = sys_get_temp_dir() . '/gwseq-teldame-reimport-test.pdf';
copy($teldame_pdf_path, $teldame_tmp);
$teldame_upload = gwseq_process_ifce_import_upload($teldame_tmp, 500);
gws_test_assert(!file_exists($teldame_tmp), 'Réimport : le fichier temporaire est bien supprimé après traitement');
preg_match('/gwseq_token=([a-zA-Z0-9]+)/', $teldame_upload['redirect'], $tm);
$teldame_transient = gwseq_get_ifce_import_transient($tm[1] ?? '');
gws_test_assert($teldame_transient !== false && (int) $teldame_transient['reimport_cheval_id'] === 500, 'Réimport (§21) : le transient conserve bien l’identifiant de la fiche existante à mettre à jour');

ob_start();
gwseq_render_ifce_import_preview($tm[1], $teldame_transient['parsed'], 500);
$teldame_preview_html = ob_get_clean();
gws_test_assert(strpos($teldame_preview_html, 'TELDAME DE LA NUTRIA') !== false, 'Prévisualisation réimport : le nom de la fiche existante concernée est bien affiché');
gws_test_assert(strpos($teldame_preview_html, 'name="gwseq_ifce_import_production"') !== false, 'Prévisualisation : la case "Importer la Production" est bien proposée pour cette jument');
gws_test_assert(strpos($teldame_preview_html, 'CHUMBA LS') !== false, 'Prévisualisation : au moins un produit direct détecté (Chumba LS) apparaît bien dans le tableau de Production');
gws_test_assert(strpos($teldame_preview_html, 'QZ') !== false, 'Prévisualisation : le produit à identifiant provisoire (QZ) apparaît bien, comme tout autre produit importable');

$posts_before_confirm_reimport = count($GLOBALS['__gwseq_test_posts']);
$reimport_confirm = gwseq_process_ifce_import_confirm($tm[1], array('identity' => true, 'indices' => true, 'pedigree' => true, 'production' => true));
gws_test_assert(count($GLOBALS['__gwseq_test_posts']) === $posts_before_confirm_reimport, 'Réimport (§21) : la confirmation NE CRÉE AUCUNE nouvelle fiche — la fiche existante (500) est mise à jour à sa place');
gws_test_assert(strpos($reimport_confirm['redirect'], (string) 500) !== false, 'Réimport : la redirection pointe bien vers la fiche existante');

$production_after_reimport = gwseq_get_cheval_production_externe(500);
gws_test_assert(count($production_after_reimport) === 18, 'Réimport bout en bout : les 18 produits directs de Teldame sont bien persistés sur la fiche existante après confirmation');

// --- Réimport UNE SECONDE FOIS du même PDF (idempotence, §21) : aucun doublon ---
copy($teldame_pdf_path, $teldame_tmp);
$teldame_upload_2 = gwseq_process_ifce_import_upload($teldame_tmp, 500);
preg_match('/gwseq_token=([a-zA-Z0-9]+)/', $teldame_upload_2['redirect'], $tm2);
gwseq_process_ifce_import_confirm($tm2[1], array('identity' => true, 'indices' => true, 'pedigree' => true, 'production' => true));
gws_test_assert(count(gwseq_get_cheval_production_externe(500)) === 18, 'Idempotence (§21) : réimporter deux fois le MÊME document ne crée jamais de doublon — toujours 18 produits');

// =====================================================================================
// 13. Architecture (§7-9, §18) — vérifications déclaratives
// =====================================================================================

gws_test_assert(strpos($ifce_mapper_code_only, 'update_post_meta') === false, 'Architecture : le fichier de mapping (production comprise) n’appelle jamais update_post_meta() directement');
gws_test_assert(strpos($ifce_production_store_code_only, 'wp_insert_post') === false, 'Architecture : le fichier de stockage de Production n’appelle jamais wp_insert_post()');
foreach (array('gwseq_set_cheval_production_externe', 'gwseq_set_cheval_sport_indice') as $business_fn) {
  gws_test_assert(strpos($ifce_mapper_code_only, $business_fn) !== false, "Architecture (§18) : le mapping de Production réutilise bien $business_fn(), jamais une écriture ad hoc");
}
// LIMITATION STRICTE (§18) : le mapping de Production n'appelle JAMAIS un setter portant sur un
// autre champ qu'un indice sportif sur le produit lié — recherche déclarative des seuls setters de
// champs métier du module, aucun autre que les indices sportifs ne doit apparaître dans ce fichier.
foreach (array('gwseq_set_cheval_identity', 'gwseq_set_horse_parent', 'gwseq_set_cheval_genetic_indice', 'gwseq_set_cheval_editorial') as $forbidden_fn) {
  $count_in_production_map = substr_count(gws_test_extract_function_body_production($ifce_mapper_code_only, 'gwseq_ifce_map_production'), $forbidden_fn);
  gws_test_assert($count_in_production_map === 0, "LIMITATION STRICTE (§18) : gwseq_ifce_map_production() n’appelle jamais $forbidden_fn() — seuls ISO/ICC/IDR peuvent jamais être propagés à un produit lié");
}

function gws_test_extract_function_body_production($code_only, $function_name) {
  $body = substr($code_only, strpos($code_only, 'function ' . $function_name));
  $next = strpos($body, "\nfunction ", 1);
  return $next === false ? $body : substr($body, 0, $next);
}

// =====================================================================================
// 13. Lot IFCE — clôture POC : identité IFCE + correction du rapprochement pedigree Père/Mère
//     (cause exacte : Grandame d'Aubigny / Teldame de la Nutria, voir le CR)
// =====================================================================================

// --- Resolver générique nom+année : UNE SEULE implémentation, gwseq_ifce_find_probable_production_match()
// devient un simple alias, sans changement de comportement pour la Production (§11 : audit préalable) ---
gws_test_make_post(800, GWSEQ_CPT_CHEVAL, 'Homonyme Unique');
gwseq_set_cheval_identity(800, array('_gwseq_sexe' => 'female', '_gwseq_annee_naissance' => 2010));
gws_test_assert(
  gwseq_ifce_find_unique_horse_match_by_name_year('Homonyme Unique', 2010) === 800,
  'Resolver générique nom+année : trouve bien un candidat unique'
);
gws_test_assert(
  gwseq_ifce_find_probable_production_match('Homonyme Unique', 2010) === gwseq_ifce_find_unique_horse_match_by_name_year('Homonyme Unique', 2010),
  'Compatibilité (§11) : gwseq_ifce_find_probable_production_match() reste un simple alias, même résultat que le resolver générique'
);
gws_test_assert(
  gwseq_ifce_find_unique_horse_match_by_name_year('Homonyme Unique', 2010, array(800)) === 0,
  'Resolver générique : un candidat explicitement exclu (ex. la fiche important elle-même) n’est jamais retourné'
);

// --- Fixtures pour gwseq_ifce_resolve_parent_proposal() (Cas A/B/C/D, §12 de la demande) ---
// Nom distinct de "TELDAME DE LA NUTRIA"/500 (§12 ci-dessus, réutilisée avec son nom officiel réel
// depuis le verrou d'identité de réimport) pour ne pas créer une ambiguïté artificielle entre deux
// fixtures de test au même nom+année.
gws_test_make_post(810, GWSEQ_CPT_CHEVAL, 'TELDAME REFERENCE PEDIGREE');
gwseq_set_cheval_identity(810, array('_gwseq_sexe' => 'female', '_gwseq_annee_naissance' => 2007));

$teldame_branch = array('name' => 'TELDAME REFERENCE PEDIGREE', 'race' => '', 'race_autre' => '', 'annee_naissance' => 2007);

// --- Cas A : candidat unique nom+année — cause exacte du bug Grandame/Teldame, désormais proposé ---
$proposal_a = gwseq_ifce_resolve_parent_proposal('mother', $teldame_branch, 0, 2016);
gws_test_assert($proposal_a['default_mode'] === 'gws', 'Cas A (ajusté après recette réelle) : le mode par défaut devient "gws" pour un candidat unique et fiable — présélectionné, jamais écrit avant le clic sur "Valider l\'import"');
gws_test_assert($proposal_a['preselected_horse_id'] === 810, 'Cas A : le candidat unique (Teldame) est bien pré-sélectionné, visible avant validation (§12)');
gws_test_assert($proposal_a['note'] === 'unique_match', 'Cas A : le code de note est bien "unique_match"');

// --- Normalisation (§13) : apostrophe typographique, casse, accents — même candidat retrouvé ---
// (nom distinct de "Goldame d'Aubigny"/205 déjà utilisée plus haut dans ce fichier, pour ne pas
// créer une ambiguïté artificielle entre deux fixtures de test)
gws_test_make_post(811, GWSEQ_CPT_CHEVAL, "Solaire d’Argentan"); // apostrophe typographique dans le titre GWS
gwseq_set_cheval_identity(811, array('_gwseq_sexe' => 'female', '_gwseq_annee_naissance' => 2016));
$proposal_norm = gwseq_ifce_resolve_parent_proposal('mother', array('name' => "SOLAIRE D'ARGENTAN", 'annee_naissance' => 2016), 0, 2020); // apostrophe droite côté IFCE
gws_test_assert($proposal_norm['preselected_horse_id'] === 811, 'Normalisation (§13) : apostrophe typographique vs droite, casse — le candidat est bien retrouvé malgré la variante');

// --- Cas B : homonyme MÊME nom mais ANNÉE DIFFÉRENTE — jamais proposé comme candidat unique (§14) ---
gws_test_make_post(812, GWSEQ_CPT_CHEVAL, 'HOMONYME ANNEE');
gwseq_set_cheval_identity(812, array('_gwseq_sexe' => 'female', '_gwseq_annee_naissance' => 1999));
$proposal_year_mismatch = gwseq_ifce_resolve_parent_proposal('mother', array('name' => 'HOMONYME ANNEE', 'annee_naissance' => 2016), 0, 2020);
gws_test_assert($proposal_year_mismatch['preselected_horse_id'] === 0, 'Cas B (§14) : un homonyme dont l’année diffère n’est jamais proposé — l’année participe au niveau de confiance');

// --- Cas B : plusieurs candidats plausibles (même nom+année) — AUCUN choix arbitraire (§12/§18) ---
gws_test_make_post(813, GWSEQ_CPT_CHEVAL, 'HOMONYME AMBIGU');
gwseq_set_cheval_identity(813, array('_gwseq_sexe' => 'female', '_gwseq_annee_naissance' => 2012));
gws_test_make_post(814, GWSEQ_CPT_CHEVAL, 'HOMONYME AMBIGU');
gwseq_set_cheval_identity(814, array('_gwseq_sexe' => 'female', '_gwseq_annee_naissance' => 2012));
$proposal_ambigu = gwseq_ifce_resolve_parent_proposal('mother', array('name' => 'HOMONYME AMBIGU', 'annee_naissance' => 2012), 0, 2020);
gws_test_assert($proposal_ambigu['preselected_horse_id'] === 0 && $proposal_ambigu['default_mode'] === 'external', 'Cas B (§12/§18) : deux candidats également plausibles -> aucun choix arbitraire, comportement "external" inchangé');

// --- Cas C : aucun candidat — comportement inchangé ---
$proposal_c = gwseq_ifce_resolve_parent_proposal('mother', array('name' => 'INTROUVABLE DU TOUT', 'annee_naissance' => 2016), 0, 2020);
gws_test_assert($proposal_c['default_mode'] === 'external' && $proposal_c['preselected_horse_id'] === 0 && $proposal_c['note'] === '', 'Cas C : aucun candidat -> comportement "external" strictement inchangé');

// --- Absence d'année (§14 : "ne doit jamais provoquer un rapprochement arbitraire") ---
gws_test_make_post(815, GWSEQ_CPT_CHEVAL, 'SANS ANNEE CONNUE');
gwseq_set_cheval_identity(815, array('_gwseq_sexe' => 'female', '_gwseq_annee_naissance' => 2016));
$proposal_no_year = gwseq_ifce_resolve_parent_proposal('mother', array('name' => 'SANS ANNEE CONNUE', 'annee_naissance' => ''), 0, 2020);
gws_test_assert($proposal_no_year['preselected_horse_id'] === 0, 'Absence d’année (§14) : jamais de rapprochement arbitraire sur le seul nom, même avec un candidat homonyme');

// --- Protection contre les faux positifs (§13/§18) : candidat trouvé par nom+année mais sexe
// incompatible avec le rôle -> jamais proposé (réutilise gwseq_ifce_preview_parent_candidate_rejection_reason(),
// cheval-pedigree.php, jamais une règle dupliquée) ---
gws_test_make_post(816, GWSEQ_CPT_CHEVAL, 'MALE HOMONYME MERE');
gwseq_set_cheval_identity(816, array('_gwseq_sexe' => 'male', '_gwseq_annee_naissance' => 2007));
$proposal_sexe_reject = gwseq_ifce_resolve_parent_proposal('mother', array('name' => 'MALE HOMONYME MERE', 'annee_naissance' => 2007), 0, 2020);
gws_test_assert($proposal_sexe_reject['preselected_horse_id'] === 0, 'Protection faux positif (§13/§18) : un homonyme au sexe incompatible avec le rôle (mâle proposé comme Mère) n’est jamais proposé');

// --- Cas D : parent DÉJÀ lié — IDEMPOTENCE (§12/§18), prioritaire sur tout candidat nom+année,
// même si le nom actuellement détecté par l'IFCE diffère du nom de la fiche déjà liée ---
gws_test_make_post(817, GWSEQ_CPT_CHEVAL, 'Fille Deja Liee');
gwseq_set_cheval_identity(817, array('_gwseq_sexe' => 'female', '_gwseq_annee_naissance' => 2018));
gwseq_set_horse_parent(817, 'mother', array('mode' => 'gws', 'horse_id' => 810)); // déjà lié à Teldame (810)
$proposal_d = gwseq_ifce_resolve_parent_proposal('mother', array('name' => 'UN AUTRE NOM DETECTE', 'annee_naissance' => 1999), 817, 2018);
gws_test_assert($proposal_d['default_mode'] === 'gws', 'Cas D (§12) : parent déjà lié -> mode par défaut "gws", jamais "external" (idempotence)');
gws_test_assert($proposal_d['preselected_horse_id'] === 810, 'Cas D : le candidat pré-sélectionné reste la relation DÉJÀ ENREGISTRÉE (810), jamais recalculée par nom');
gws_test_assert($proposal_d['note'] === 'already_linked', 'Cas D : code de note "already_linked"');

// =====================================================================================
// 14. Régression bout en bout — GRANDAME D'AUBIGNY / TELDAME DE LA NUTRIA (cas de recette exact)
// =====================================================================================

gws_test_make_post(820, GWSEQ_CPT_CHEVAL, 'TELDAME DE LA NUTRIA E2E');
gwseq_set_cheval_identity(820, array('_gwseq_sexe' => 'female', '_gwseq_annee_naissance' => 2007));

$grandame_parsed = array(
  'valid' => true,
  'identity' => array(
    'nom' => 'GRANDAME D’AUBIGNY', 'nom_officiel' => '', 'sexe' => 'female', 'annee_naissance' => 2016,
    'robe' => '', 'robe_autre' => '', 'race' => '', 'race_autre' => '', 'taille_cm' => '',
    'eleveur' => '', 'ueln' => '', 'sire' => '',
  ),
  'indices' => array(),
  'pedigree' => array(
    'count' => 1,
    'father' => null,
    'mother' => array('name' => 'TELDAME DE LA NUTRIA E2E', 'race' => '', 'race_autre' => '', 'annee_naissance' => 2007),
  ),
);

// Simule EXACTEMENT ce que soumet le formulaire de prévisualisation UNE FOIS CORRIGÉ (§12) : le
// candidat proposé par gwseq_ifce_resolve_parent_proposal() (Cas A), explicitement confirmé par
// l'utilisateur en cliquant "Lier à un cheval déjà enregistré" — jamais une sélection automatique
// côté serveur, seule la PROPOSITION est automatique.
$grandame_proposal = gwseq_ifce_resolve_parent_proposal('mother', $grandame_parsed['pedigree']['mother'], 0, 2016);
gws_test_assert($grandame_proposal['preselected_horse_id'] === 820, 'Régression Grandame/Teldame : le candidat proposé pour la Mère est bien la fiche GWS existante de Teldame');
gws_test_assert($grandame_proposal['default_mode'] === 'gws', 'Régression Grandame/Teldame : le mode par défaut est bien "gws" (corrigé après recette réelle — un clic direct sur "Valider l\'import" ne doit plus créer un ascendant externe)');

// --- Test portant RÉELLEMENT sur le radio présélectionné dans la preview rendue, pas uniquement
// sur le résultat interne du resolver (exigence explicite après recette réelle) ---
ob_start();
gwseq_render_ifce_preview_parent_choice('mother', $grandame_parsed['pedigree']['mother'], 'Mère', 'gwseq_ifce_mere_mode', 'gwseq_ifce_mere_gws_id', 2016, 0);
$grandame_mother_choice_html = ob_get_clean();
gws_test_assert(
  strpos($grandame_mother_choice_html, 'name="gwseq_ifce_mere_mode" value="gws" checked') !== false,
  'Preview réelle (radio rendu) : le choix "Lier à un cheval déjà enregistré" est bien COCHÉ par défaut pour la Mère (Cas A, candidat unique Teldame)'
);
gws_test_assert(
  strpos($grandame_mother_choice_html, 'name="gwseq_ifce_mere_mode" value="external" >') !== false || strpos($grandame_mother_choice_html, 'name="gwseq_ifce_mere_mode" value="external">') !== false,
  'Preview réelle : le choix "Importer comme ascendant externe" n’est PLUS coché par défaut pour ce cas'
);
gws_test_assert(
  preg_match('/<option value="' . 820 . '" selected/', $grandame_mother_choice_html) === 1,
  'Preview réelle : le sélecteur de cheval GWS a bien TELDAME (820) présélectionnée comme option'
);

$grandame_id = wp_insert_post(array('post_type' => GWSEQ_CPT_CHEVAL, 'post_status' => 'draft', 'post_title' => 'GRANDAME D’AUBIGNY'), true);
gwseq_ifce_map_import($grandame_id, $grandame_parsed, array('identity' => true, 'indices' => true, 'pedigree' => true), array(
  'mother' => array('mode' => 'gws', 'horse_id' => $grandame_proposal['preselected_horse_id']),
));

$grandame_mother_relation = gwseq_get_horse_parent($grandame_id, 'mother');
gws_test_assert($grandame_mother_relation['mode'] === 'gws', 'Régression Grandame/Teldame : la relation Mère est bien enregistrée en mode "gws", jamais "external"');
gws_test_assert($grandame_mother_relation['horse_id'] === 820, 'Régression Grandame/Teldame : la relation Mère pointe bien vers la fiche GWS existante de Teldame, aucune copie externe dupliquée');
gws_test_assert(in_array($grandame_id, array_map(function ($p) { return $p->ID; }, gwseq_get_horse_offspring(820)), true), 'Régression Grandame/Teldame : Grandame apparaît bien dans la Production calculée de Teldame après validation (relation GWS réelle)');

// --- Cas D en conditions réelles : un RÉIMPORT de Grandame (pedigree coché, aucun choix explicite
// soumis — le cas normal où l'utilisateur ne fait que confirmer) ne doit JAMAIS faire régresser la
// relation Mère déjà correcte vers "external" (§19) ---
gwseq_ifce_map_import($grandame_id, $grandame_parsed, array('identity' => true, 'indices' => true, 'pedigree' => true), array(
  // Aucune clé 'mother' : simule un $parent_choices construit par gwseq_sanitize_ifce_preview_parent_choice()
  // avec le safe-default (§19, includes/ifce-import-admin.php) — voir le test dédié plus bas pour
  // cette fonction précise ; ce test-ci vérifie le résultat une fois appliqué par le mapper.
  'mother' => array('mode' => 'gws', 'horse_id' => 820),
));
$grandame_mother_after_reimport = gwseq_get_horse_parent($grandame_id, 'mother');
gws_test_assert($grandame_mother_after_reimport['mode'] === 'gws' && $grandame_mother_after_reimport['horse_id'] === 820, 'Idempotence réimport (§18/§19) : la relation Mère GWS déjà correcte reste intacte après un réimport, jamais remplacée par un ascendant externe');

// =====================================================================================
// 15. Défense en profondeur — gwseq_sanitize_ifce_preview_parent_choice() (§19)
// =====================================================================================

$choice_field_absent_no_existing = gwseq_sanitize_ifce_preview_parent_choice(array(), 'gwseq_ifce_mere_mode', 'gwseq_ifce_mere_gws_id', 0);
gws_test_assert($choice_field_absent_no_existing === array('mode' => 'external'), 'Défense en profondeur : champ radio absent ET aucune relation existante -> repli "external" inchangé (comportement historique)');

$choice_field_absent_existing = gwseq_sanitize_ifce_preview_parent_choice(array(), 'gwseq_ifce_mere_mode', 'gwseq_ifce_mere_gws_id', 820);
gws_test_assert($choice_field_absent_existing === array('mode' => 'gws', 'horse_id' => 820), 'Défense en profondeur (§19) : champ radio totalement absent MAIS une relation GWS est déjà active -> repli sur cette relation, JAMAIS "external" (ne remplace jamais silencieusement un parent GWS)');

$choice_explicit_external_overrides_existing = gwseq_sanitize_ifce_preview_parent_choice(array('gwseq_ifce_mere_mode' => 'external'), 'gwseq_ifce_mere_mode', 'gwseq_ifce_mere_gws_id', 820);
gws_test_assert($choice_explicit_external_overrides_existing === array('mode' => 'external'), 'Défense en profondeur : un choix EXPLICITE "external" soumis par l’utilisateur reste toujours respecté (une correction volontaire n’est jamais bloquée)');

// =====================================================================================
// 16. Identité IFCE — ID opaque extrait du nom de fichier PDF, non-destructif (§3/§20)
// =====================================================================================

gws_test_assert(
  gwseq_ifce_extract_id_from_pdf_filename('fs-complet_classique_FlqOAbSQRt6yLcBQACZbfA_1788355627601.pdf') === 'FlqOAbSQRt6yLcBQACZbfA',
  'Extraction ID IFCE : exemple réel n°1 (Windows VH Costersveld / Cornet Obolensky)'
);
gws_test_assert(
  gwseq_ifce_extract_id_from_pdf_filename('fs-complet_classique_M8yvnNYRRru6thoBR_NYvg_1788944320384.pdf') === 'M8yvnNYRRru6thoBR_NYvg',
  'Extraction ID IFCE : exemple réel n°2 (Dollar du Mûrier), y compris un ID contenant lui-même un "_"'
);
gws_test_assert(
  gwseq_ifce_extract_id_from_pdf_filename('mon-fichier-renomme.pdf') === '',
  'Extraction ID IFCE : un fichier renommé par l’utilisateur ne matche jamais approximativement -> chaîne vide, jamais une extraction partielle'
);
gws_test_assert(
  gwseq_ifce_extract_id_from_pdf_filename('') === '',
  'Extraction ID IFCE : nom de fichier vide -> chaîne vide, jamais une erreur'
);

gws_test_make_post(830, GWSEQ_CPT_CHEVAL, 'Fiche Identite IFCE');
gws_test_assert(gwseq_get_cheval_ifce_id(830) === '', 'ID IFCE : vide par défaut sur une fiche jamais importée depuis un PDF');
gws_test_assert(gwseq_set_cheval_ifce_id(830, 'ABCDEFGHIJKLMNOPQRSTUV') === true, 'ID IFCE : première écriture acceptée');
gws_test_assert(gwseq_get_cheval_ifce_id(830) === 'ABCDEFGHIJKLMNOPQRSTUV', 'ID IFCE : relecture correcte après écriture');
gws_test_assert(gwseq_set_cheval_ifce_id(830, 'ABCDEFGHIJKLMNOPQRSTUV') === true, 'ID IFCE : réécriture de la MÊME valeur -> idempotent, acceptée');
gws_test_assert(gwseq_cheval_ifce_id_conflicts(830, 'UN_ID_DIFFERENT_XXXXXX') === true, 'ID IFCE (§20) : une valeur différente de celle déjà enregistrée est bien détectée comme un conflit');
gws_test_assert(gwseq_set_cheval_ifce_id(830, 'UN_ID_DIFFERENT_XXXXXX') === false, 'ID IFCE (§20) : le setter refuse LUI-MÊME (défense en profondeur) d’écraser silencieusement un ID différent déjà enregistré');
gws_test_assert(gwseq_get_cheval_ifce_id(830) === 'ABCDEFGHIJKLMNOPQRSTUV', 'ID IFCE (§20) : la valeur déjà enregistrée est bien restée intacte après la tentative de conflit');

gws_test_assert(gwseq_get_cheval_ifce_url(830) === '', 'URL IFCE (§8) : jamais construite sur le seul ID -- vide tant que le slug n’est pas connu ("il vaut mieux aucune URL qu’une URL supposée")');
gwseq_set_cheval_ifce_slug(830, 'fiche-identite-ifce-officielle');
gws_test_assert(
  gwseq_get_cheval_ifce_url(830) === 'https://infochevaux.ifce.fr/fr/fiche-identite-ifce-officielle-ABCDEFGHIJKLMNOPQRSTUV/infos-generales',
  'URL IFCE (§4/§6) : construite UNIQUEMENT à partir du slug et de l’ID déjà enregistrés (jamais reconstruite depuis le nom GWS) une fois le slug connu'
);

// =====================================================================================
// 17. SIRE/UELN — non-destructif au réimport (§7/§20-21), jamais inventé, jamais écrasé par un
//     conflit silencieux
// =====================================================================================

gws_test_make_post(840, GWSEQ_CPT_CHEVAL, 'Cheval SIRE UELN');
gwseq_set_cheval_identity(840, array('_gwseq_sexe' => 'male', '_gwseq_annee_naissance' => 2000, '_gwseq_sire' => '91412674X', '_gwseq_ueln' => '25000191412674X'));

$parsed_no_sire_ueln = array(
  'valid' => true,
  'identity' => array(
    'nom' => 'Cheval SIRE UELN', 'nom_officiel' => '', 'sexe' => 'male', 'annee_naissance' => 2000,
    'robe' => '', 'robe_autre' => '', 'race' => '', 'race_autre' => '', 'taille_cm' => '',
    'eleveur' => '', 'ueln' => '', 'sire' => '', // rien détecté dans ce PDF (cas ALME, §7)
  ),
  'indices' => array(), 'pedigree' => array('count' => 0, 'father' => null, 'mother' => null),
);
gwseq_ifce_map_import(840, $parsed_no_sire_ueln, array('identity' => true));
$identity_after_blank_reimport = gwseq_get_cheval_identity(840);
gws_test_assert(
  $identity_after_blank_reimport['sire'] === '91412674X' && $identity_after_blank_reimport['ueln'] === '25000191412674X',
  'SIRE/UELN (§7) : une absence de détection dans un réimport (cas ALME) n’efface jamais une valeur déjà enregistrée'
);

$parsed_conflicting_sire = $parsed_no_sire_ueln;
$parsed_conflicting_sire['identity']['sire'] = '99999999Z'; // différent de la valeur déjà enregistrée
gwseq_ifce_map_import(840, $parsed_conflicting_sire, array('identity' => true));
gws_test_assert(gwseq_get_cheval_identity(840)['sire'] === '91412674X', 'SIRE (§20-21) : une valeur détectée DIFFÉRENTE d’une valeur déjà enregistrée est un conflit -> jamais écrasée silencieusement, l’existante est conservée');

gws_test_make_post(841, GWSEQ_CPT_CHEVAL, 'Cheval Sans SIRE Encore');
gwseq_set_cheval_identity(841, array('_gwseq_sexe' => 'male', '_gwseq_annee_naissance' => 2000));
$parsed_first_sire = $parsed_no_sire_ueln;
$parsed_first_sire['identity']['nom'] = 'Cheval Sans SIRE Encore';
$parsed_first_sire['identity']['sire'] = '12345678A';
gwseq_ifce_map_import(841, $parsed_first_sire, array('identity' => true));
gws_test_assert(gwseq_get_cheval_identity(841)['sire'] === '12345678A', 'SIRE : une première détection (aucune valeur préexistante) est bien enregistrée normalement');

// =====================================================================================
// 18. Dérivation opportuniste de l'UELN à partir du SIRE — correctif "UELN Selle Français" :
//     UNIQUEMENT stud-book Selle Français ('SF'), UNIQUEMENT si l'UELN est vide, quelle que soit la
//     provenance du SIRE (détecté par ce PDF ici — le cas "via SHF" est couvert séparément par un
//     test bout en bout sur le vrai PDF de GOLDAME D'AUBIGNY dans gws-equestrian-ifce-import-test.php).
// =====================================================================================

// --- Cas éligible : Selle Français + SIRE détecté + UELN vide -> dérivé et provenance posée ---
gws_test_make_post(842, GWSEQ_CPT_CHEVAL, 'Cheval SF Eligible UELN');
gwseq_set_cheval_identity(842, array('_gwseq_sexe' => 'female', '_gwseq_annee_naissance' => 2016));
$parsed_sf_eligible = array(
  'valid' => true,
  'identity' => array(
    'nom' => 'Cheval SF Eligible UELN', 'nom_officiel' => '', 'sexe' => 'female', 'annee_naissance' => 2016,
    'robe' => '', 'robe_autre' => '', 'race' => 'SF', 'race_autre' => '', 'taille_cm' => '',
    'eleveur' => '', 'ueln' => '', 'sire' => '16398915R',
  ),
  'indices' => array(), 'pedigree' => array('count' => 0, 'father' => null, 'mother' => null),
);
gwseq_ifce_map_import(842, $parsed_sf_eligible, array('identity' => true));
gws_test_assert(gwseq_get_cheval_identity(842)['ueln'] === '25000116398915R', 'UELN Selle Français : dérivé correctement (250001 + SIRE) — exemple réel GOLDAME D’AUBIGNY (SIRE 16398915R -> UELN 25000116398915R)');
gws_test_assert(gwseq_get_cheval_ueln_source(842) === 'derived_sire', 'Provenance UELN : marqueur "derived_sire" posé après dérivation réelle');

// --- Cheval étranger/importé (stud-book non SF, ex. KWPN) + SIRE disponible -> AUCUNE dérivation,
// même avec un SIRE français valide (§ "un cheval étranger peut avoir un SIRE français sans que son
// UELN soit basé sur 250001") ---
gws_test_make_post(843, GWSEQ_CPT_CHEVAL, 'Cheval KWPN Avec SIRE FR');
gwseq_set_cheval_identity(843, array('_gwseq_sexe' => 'female', '_gwseq_annee_naissance' => 2016));
$parsed_foreign_race = $parsed_sf_eligible;
$parsed_foreign_race['identity']['nom'] = 'Cheval KWPN Avec SIRE FR';
$parsed_foreign_race['identity']['race'] = 'KWPN';
gwseq_ifce_map_import(843, $parsed_foreign_race, array('identity' => true));
gws_test_assert(gwseq_get_cheval_identity(843)['ueln'] === '', 'UELN Selle Français : cheval étranger/importé (KWPN) avec SIRE disponible -> AUCUNE dérivation automatique');
gws_test_assert(gwseq_get_cheval_ueln_source(843) === '', 'Provenance UELN : aucun marqueur posé quand rien n’a été dérivé');

// --- Origine incertaine (race non détectée/vide) + SIRE disponible -> AUCUNE dérivation (jamais une
// déduction hasardeuse en l'absence de stud-book identifié) ---
gws_test_make_post(844, GWSEQ_CPT_CHEVAL, 'Cheval Race Inconnue');
gwseq_set_cheval_identity(844, array('_gwseq_sexe' => 'female', '_gwseq_annee_naissance' => 2016));
$parsed_unknown_race = $parsed_sf_eligible;
$parsed_unknown_race['identity']['nom'] = 'Cheval Race Inconnue';
$parsed_unknown_race['identity']['race'] = '';
gwseq_ifce_map_import(844, $parsed_unknown_race, array('identity' => true));
gws_test_assert(gwseq_get_cheval_identity(844)['ueln'] === '', 'UELN Selle Français : origine/stud-book incertain (race non détectée) -> AUCUNE dérivation');

// --- UELN déjà renseigné (même sur un cheval Selle Français avec SIRE) -> jamais recalculé/écrasé ---
gws_test_make_post(845, GWSEQ_CPT_CHEVAL, 'Cheval SF UELN Deja Present');
gwseq_set_cheval_identity(845, array('_gwseq_sexe' => 'female', '_gwseq_annee_naissance' => 2016, '_gwseq_ueln' => '25000199999999Z'));
$parsed_ueln_already_present = $parsed_sf_eligible;
$parsed_ueln_already_present['identity']['nom'] = 'Cheval SF UELN Deja Present';
gwseq_ifce_map_import(845, $parsed_ueln_already_present, array('identity' => true));
gws_test_assert(gwseq_get_cheval_identity(845)['ueln'] === '25000199999999Z', 'UELN Selle Français (non-destruction) : un UELN déjà enregistré n’est JAMAIS recalculé ni écrasé, même Selle Français avec SIRE disponible');
gws_test_assert(gwseq_get_cheval_ueln_source(845) === '', 'Provenance UELN : aucun marqueur "derived_sire" pour une valeur qui n’a jamais été dérivée par ce lot');

// --- SIRE absent -> aucune dérivation, même Selle Français ---
gws_test_make_post(846, GWSEQ_CPT_CHEVAL, 'Cheval SF Sans SIRE');
gwseq_set_cheval_identity(846, array('_gwseq_sexe' => 'female', '_gwseq_annee_naissance' => 2016));
$parsed_sf_no_sire = $parsed_sf_eligible;
$parsed_sf_no_sire['identity']['nom'] = 'Cheval SF Sans SIRE';
$parsed_sf_no_sire['identity']['sire'] = '';
gwseq_ifce_map_import(846, $parsed_sf_no_sire, array('identity' => true));
gws_test_assert(gwseq_get_cheval_identity(846)['ueln'] === '', 'UELN Selle Français : SIRE absent -> AUCUNE dérivation, même pour un stud-book éligible');

// --- Réimport : le SIRE existe DÉJÀ sur la fiche (déjà couvert §1, zéro appel SHF nécessaire), le
// PDF réimporté ne le redétecte pas lui-même mais confirme le stud-book Selle Français -> l'UELN
// encore vide PEUT être complété SANS jamais avoir appelé SHF (§ "le prochain import IFCE doit
// pouvoir compléter l'UELN sans appeler SHF, puisque le SIRE existe déjà") ---
gws_test_make_post(847, GWSEQ_CPT_CHEVAL, 'Cheval SF Reimport Sire Existant');
gwseq_set_cheval_identity(847, array('_gwseq_sexe' => 'female', '_gwseq_annee_naissance' => 2016, '_gwseq_sire' => '16398915R'));
$parsed_reimport_ueln_completion = array(
  'valid' => true,
  'identity' => array(
    'nom' => 'Cheval SF Reimport Sire Existant', 'nom_officiel' => '', 'sexe' => 'female', 'annee_naissance' => 2016,
    'robe' => '', 'robe_autre' => '', 'race' => 'SF', 'race_autre' => '', 'taille_cm' => '',
    'eleveur' => '', 'ueln' => '', 'sire' => '', // ce PDF-ci ne redétecte pas le SIRE lui-même (cas ALME)
  ),
  'indices' => array(), 'pedigree' => array('count' => 0, 'father' => null, 'mother' => null),
  'shf_sire' => '', // aucun appel SHF n'était nécessaire : le SIRE existait déjà (§1)
);
gwseq_ifce_map_import(847, $parsed_reimport_ueln_completion, array('identity' => true));
gws_test_assert(gwseq_get_cheval_identity(847)['sire'] === '16398915R', 'UELN Selle Français (réimport) : le SIRE déjà enregistré reste bien préservé (non-destruction inchangée)');
gws_test_assert(gwseq_get_cheval_identity(847)['ueln'] === '25000116398915R', 'UELN Selle Français (réimport) : complété à partir du SIRE déjà enregistré, sans qu’aucun appel SHF n’ait été nécessaire ($parsed[\'shf_sire\'] vide)');
gws_test_assert(gwseq_get_cheval_ueln_source(847) === 'derived_sire', 'Provenance UELN (réimport) : marqueur "derived_sire" bien posé même quand le SIRE utilisé était déjà enregistré (pas nouvellement détecté)');

// =====================================================================================
// 18. Correctif recette réelle — Production : lien fantôme GOLDAME supprimée -> TELDAME
// =====================================================================================

gws_test_make_post(850, GWSEQ_CPT_CHEVAL, 'TELDAME PHANTOM LINK');
gwseq_set_cheval_identity(850, array('_gwseq_sexe' => 'female', '_gwseq_annee_naissance' => 2007));
gws_test_make_post(851, GWSEQ_CPT_CHEVAL, "GOLDAME D'AUBIGNY PHANTOM");
gwseq_set_cheval_identity(851, array('_gwseq_sexe' => 'female', '_gwseq_annee_naissance' => 2016));
gwseq_set_cheval_production_externe(850, array(array(
  'annee' => 2016, 'nom' => "GOLDAME D'AUBIGNY PHANTOM", 'pere' => 'UN PERE',
  'iso' => array('valeur' => 120, 'cd' => 0.6, 'annee' => 2022), 'icc' => array(), 'idr' => array(),
  'cheval_gws_id' => 851,
)));

// --- Produit GWS lié valide : lien généré normalement ---
$production_valid = gwseq_get_horse_direct_production(850);
gws_test_assert($production_valid[0]['cheval_gws_id'] === 851, 'Production : produit GWS lié valide -> cheval_gws_id conservé');
ob_start();
gwseq_render_cheval_production_box((object) array('ID' => 850));
$production_html_valid = ob_get_clean();
gws_test_assert(strpos($production_html_valid, 'action=edit') !== false, 'Production (rendu) : un produit GWS lié valide produit bien un lien d’édition');

// --- Produit GWS à la corbeille : le lien reste généré (logique trash déjà prévue, respectée) ---
$GLOBALS['__gwseq_test_posts'][851]['post_status'] = 'trash';
$production_trash = gwseq_get_horse_direct_production(850);
gws_test_assert($production_trash[0]['cheval_gws_id'] === 851, 'Production : produit GWS à la corbeille -> reste considéré comme lié (post toujours réel)');
$GLOBALS['__gwseq_test_posts'][851]['post_status'] = 'publish'; // restauration pour la suite

// --- Défense en profondeur (2e couche, garde de LECTURE) : même SANS que le nettoyage
// before_delete_post n'ait été appelé, un cheval_gws_id qui ne résout plus vers aucune fiche
// réelle n'est jamais traité comme lié. Simulé sur une fixture DISTINCTE (854/855) pour isoler
// cette vérification de celle du nettoyage effectif ci-dessous. ---
gws_test_make_post(854, GWSEQ_CPT_CHEVAL, 'TELDAME PHANTOM LINK DEFENSE LECTURE');
gwseq_set_cheval_identity(854, array('_gwseq_sexe' => 'female', '_gwseq_annee_naissance' => 2007));
gws_test_make_post(855, GWSEQ_CPT_CHEVAL, 'PRODUIT SUPPRIME SANS NETTOYAGE');
gwseq_set_cheval_production_externe(854, array(array(
  'annee' => 2016, 'nom' => 'PRODUIT SUPPRIME SANS NETTOYAGE', 'pere' => '', 'iso' => array(), 'icc' => array(), 'idr' => array(), 'cheval_gws_id' => 855,
)));
unset($GLOBALS['__gwseq_test_posts'][855]); // suppression définitive simulée SANS appeler le nettoyage avant
$production_no_cleanup = gwseq_get_horse_direct_production(854);
gws_test_assert($production_no_cleanup[0]['cheval_gws_id'] === 0, 'Garde de lecture (2e couche) : un cheval_gws_id qui ne résout plus vers aucune fiche réelle n’est jamais traité comme lié, même SANS nettoyage préalable en base');
gws_test_assert($production_no_cleanup[0]['cheval_gws_id'] !== 854, 'Aucun fallback vers la jument courante (854) : la neutralisation retombe sur 0, jamais sur l’ID de la fiche en cours de lecture');
gws_test_assert($production_no_cleanup[0]['nom'] === 'PRODUIT SUPPRIME SANS NETTOYAGE', 'Les données IFCE de la ligne (nom) sont bien préservées après disparition de la cible, même sans nettoyage');
ob_start();
gwseq_render_cheval_production_box((object) array('ID' => 854));
$production_html_no_cleanup = ob_get_clean();
gws_test_assert(strpos($production_html_no_cleanup, '<a ') === false, 'Production (rendu) : plus aucun lien cliquable — jamais un href vide qui pointerait silencieusement sur la page courante (le bug exact constaté en recette)');
gws_test_assert(strpos($production_html_no_cleanup, esc_html('PRODUIT SUPPRIME SANS NETTOYAGE')) !== false, 'Production (rendu) : la ligne reste visible en texte non cliquable ("2016 — nom du produit")');

// --- Nettoyage effectif en base (before_delete_post) : DÉCLENCHÉ AU BON MOMENT, c'est-à-dire
// PENDANT que le post référencé existe encore (avant_delete_post se déclenche AVANT la suppression
// réelle de la ligne, jamais après — timing WordPress natif, reproduit ici fidèlement) ---
$goldame_snapshot_before_delete = gwseq_get_cheval_production_externe_raw(850)[0];
gwseq_cleanup_production_links_on_delete(851); // 851 (Goldame) existe ENCORE à cet instant, comme en conditions réelles
unset($GLOBALS['__gwseq_test_posts'][851]); // la suppression réelle du post se produit ENSUITE
$goldame_snapshot_after_cleanup = gwseq_get_cheval_production_externe_raw(850)[0];
gws_test_assert($goldame_snapshot_after_cleanup['cheval_gws_id'] === 0, 'Nettoyage (before_delete_post) : cheval_gws_id bien remis à 0 EN BASE, pas seulement neutralisé à la lecture');
gws_test_assert(
  $goldame_snapshot_after_cleanup['nom'] === $goldame_snapshot_before_delete['nom']
  && $goldame_snapshot_after_cleanup['annee'] === $goldame_snapshot_before_delete['annee']
  && $goldame_snapshot_after_cleanup['pere'] === $goldame_snapshot_before_delete['pere'],
  'Nettoyage : nom/année/père de la ligne de Production restent identiques — seul cheval_gws_id est modifié'
);

$production_after_hard_delete = gwseq_get_horse_direct_production(850);
ob_start();
gwseq_render_cheval_production_box((object) array('ID' => 850));
$production_html_after_delete = ob_get_clean();
gws_test_assert($production_after_hard_delete[0]['cheval_gws_id'] === 0, 'Après suppression définitive et nettoyage : la ligne redevient un produit externe non lié');
gws_test_assert(strpos($production_html_after_delete, '<a ') === false, 'Production (rendu) après nettoyage : plus aucun lien cliquable');
gws_test_assert(strpos($production_html_after_delete, esc_html("GOLDAME D'AUBIGNY PHANTOM")) !== false, 'Production (rendu) après nettoyage : la ligne reste visible en texte non cliquable, comme demandé ("2016 — GOLDAME D\'AUBIGNY")');

// --- Nouveau rapprochement ultérieur possible : une NOUVELLE fiche GWS portant le même nom+année
// peut de nouveau être proposée (certain/probable), rien n'est verrouillé par l'ancien lien mort ---
gws_test_make_post(852, GWSEQ_CPT_CHEVAL, "GOLDAME D'AUBIGNY PHANTOM");
gwseq_set_cheval_identity(852, array('_gwseq_sexe' => 'female', '_gwseq_annee_naissance' => 2016));
gws_test_assert(
  gwseq_ifce_find_probable_production_match("GOLDAME D'AUBIGNY PHANTOM", 2016) === 852,
  'Réimport après disparition : un nouveau rapprochement PROBABLE reste possible vers une nouvelle fiche GWS correspondante, l’ancien lien mort ne bloque rien'
);

// --- Produit externe jamais lié (cas de base, non affecté) : aucune régression ---
gws_test_make_post(853, GWSEQ_CPT_CHEVAL, 'TELDAME PHANTOM LINK 2');
gwseq_set_cheval_identity(853, array('_gwseq_sexe' => 'female', '_gwseq_annee_naissance' => 2007));
gwseq_set_cheval_production_externe(853, array(array('annee' => 2018, 'nom' => 'PRODUIT JAMAIS LIE', 'pere' => '', 'iso' => array(), 'icc' => array(), 'idr' => array(), 'cheval_gws_id' => 0)));
$production_never_linked = gwseq_get_horse_direct_production(853);
gws_test_assert($production_never_linked[0]['source'] === 'ifce' && $production_never_linked[0]['cheval_gws_id'] === 0, 'Produit externe jamais lié : comportement de base inchangé');

// =====================================================================================
// 19. Correctif recette réelle — verrou d'identité du réimport (gwseq_ifce_validate_reimport_identity)
// =====================================================================================

gws_test_make_post(860, GWSEQ_CPT_CHEVAL, 'CORNET OBOLENSKY LOCK'); // alias GWS
gwseq_set_cheval_identity(860, array('_gwseq_sexe' => 'male', '_gwseq_annee_naissance' => 2005));
gwseq_set_cheval_ifce_id(860, 'Me1Q_SYWTCa6femD__2NEg');
update_post_meta(860, '_gwseq_ifce_nom_officiel', 'WINDOWS VH COSTERSVELD LOCK'); // nom officiel distinct de l'alias GWS

function gws_test_parsed_identity($nom, $nom_officiel, $annee) {
  return array('nom' => $nom, 'nom_officiel' => $nom_officiel, 'annee_naissance' => $annee);
}

// --- A : même ID + même nom officiel + même année -> autorisé ---
$val_a = gwseq_ifce_validate_reimport_identity(860, gws_test_parsed_identity('WINDOWS VH COSTERSVELD LOCK', 'WINDOWS VH COSTERSVELD LOCK', 2005), 'Me1Q_SYWTCa6femD__2NEg');
gws_test_assert($val_a['ok'] === true, 'Verrou réimport, Cas A : même ID + même nom officiel + même année -> autorisé');

// --- B : ID différent -> BLOCAGE DUR, quelles que soient les autres données ---
$val_b = gwseq_ifce_validate_reimport_identity(860, gws_test_parsed_identity('WINDOWS VH COSTERSVELD LOCK', 'WINDOWS VH COSTERSVELD LOCK', 2005), 'UN_AUTRE_ID_XXXXXXXXXX');
gws_test_assert($val_b['ok'] === false && $val_b['reason'] === 'id_mismatch', 'Verrou réimport, Cas B : ID différent -> BLOQUÉ (id_mismatch), aucune autre vérification ne compte');

// --- C : même ID mais nom officiel contradictoire -> BLOQUÉ (défense §7) ---
$val_c = gwseq_ifce_validate_reimport_identity(860, gws_test_parsed_identity('AUTRE NOM', 'UN NOM OFFICIEL TOTALEMENT DIFFERENT', 2005), 'Me1Q_SYWTCa6femD__2NEg');
gws_test_assert($val_c['ok'] === false && $val_c['reason'] === 'id_match_name_mismatch', 'Verrou réimport, Cas C : même ID mais nom officiel contradictoire -> BLOQUÉ');

// --- D : même ID mais année contradictoire -> BLOQUÉ (défense §7) ---
$val_d = gwseq_ifce_validate_reimport_identity(860, gws_test_parsed_identity('WINDOWS VH COSTERSVELD LOCK', 'WINDOWS VH COSTERSVELD LOCK', 1999), 'Me1Q_SYWTCa6femD__2NEg');
gws_test_assert($val_d['ok'] === false && $val_d['reason'] === 'id_match_year_mismatch', 'Verrou réimport, Cas D : même ID mais année contradictoire -> BLOQUÉ');

// --- Alias (§8) : le nom d'usage/alias GWS ("CORNET OBOLENSKY LOCK") n'intervient JAMAIS dans la
// comparaison — seul le nom officiel IFCE compte, des deux côtés ---
$val_alias = gwseq_ifce_validate_reimport_identity(860, gws_test_parsed_identity('CORNET OBOLENSKY LOCK', 'WINDOWS VH COSTERSVELD LOCK', 2005), 'Me1Q_SYWTCa6femD__2NEg');
gws_test_assert($val_alias['ok'] === true, 'Verrou réimport (alias, §8) : le nom d’usage GWS diffère du nom officiel mais n’est jamais comparé -> autorisé sur la base du seul nom officiel IFCE');

// --- E : legacy — aucun ID enregistré, nom officiel (à défaut, titre GWS) + année concordants -> autorisé ---
gws_test_make_post(861, GWSEQ_CPT_CHEVAL, 'LEGACY SANS ID');
gwseq_set_cheval_identity(861, array('_gwseq_sexe' => 'female', '_gwseq_annee_naissance' => 2010));
$val_legacy_ok = gwseq_ifce_validate_reimport_identity(861, gws_test_parsed_identity('LEGACY SANS ID', '', 2010), 'NOUVEL_ID_JAMAIS_VU_XXXXX');
gws_test_assert($val_legacy_ok['ok'] === true, 'Verrou réimport, Cas E (legacy) : aucun ID enregistré, nom (titre GWS à défaut de nom officiel) + année concordants -> autorisé');

// --- Legacy : nom différent -> BLOQUÉ ---
$val_legacy_name_ko = gwseq_ifce_validate_reimport_identity(861, gws_test_parsed_identity('UN AUTRE CHEVAL', '', 2010), 'ID_XXXXXXXXXXXXXXXXXXXX');
gws_test_assert($val_legacy_name_ko['ok'] === false && $val_legacy_name_ko['reason'] === 'legacy_name_mismatch', 'Verrou réimport, Cas E (legacy) : nom différent -> BLOQUÉ');

// --- Legacy : même nom mais année différente -> BLOQUÉ ---
$val_legacy_year_ko = gwseq_ifce_validate_reimport_identity(861, gws_test_parsed_identity('LEGACY SANS ID', '', 1999), 'ID_XXXXXXXXXXXXXXXXXXXX');
gws_test_assert($val_legacy_year_ko['ok'] === false && $val_legacy_year_ko['reason'] === 'legacy_year_mismatch', 'Verrou réimport, Cas E (legacy) : même nom mais année différente -> BLOQUÉ');

// --- Legacy : année absente d'un côté -> BLOQUÉ (§10 : l'année est OBLIGATOIRE pour un legacy, jamais deviné) ---
$val_legacy_no_year = gwseq_ifce_validate_reimport_identity(861, gws_test_parsed_identity('LEGACY SANS ID', '', ''), 'ID_XXXXXXXXXXXXXXXXXXXX');
gws_test_assert($val_legacy_no_year['ok'] === false && $val_legacy_no_year['reason'] === 'legacy_year_missing', 'Verrou réimport, Cas E (legacy) : année absente dans le PDF -> BLOQUÉ, jamais deviné');

gws_test_make_post(862, GWSEQ_CPT_CHEVAL, 'LEGACY SANS ANNEE GWS');
gwseq_set_cheval_identity(862, array('_gwseq_sexe' => 'female', '_gwseq_annee_naissance' => '')); // année elle-même jamais renseignée côté GWS
$val_legacy_no_year_gws = gwseq_ifce_validate_reimport_identity(862, gws_test_parsed_identity('LEGACY SANS ANNEE GWS', '', 2010), 'ID_XXXXXXXXXXXXXXXXXXXX');
gws_test_assert($val_legacy_no_year_gws['ok'] === false && $val_legacy_no_year_gws['reason'] === 'legacy_year_missing', 'Verrou réimport, Cas E (legacy) : année absente côté fiche GWS -> BLOQUÉ également');

// =====================================================================================
// 20. Verrou d'identité — câblage bout en bout (upload + confirmation), aucune écriture sur échec
// =====================================================================================

gws_test_make_post(870, GWSEQ_CPT_CHEVAL, 'GRANDAME LOCK CIBLE');
gwseq_set_cheval_identity(870, array('_gwseq_sexe' => 'female', '_gwseq_annee_naissance' => 2016, '_gwseq_sire' => 'SIRE_INITIAL'));
$identity_before_wrong_upload = gwseq_get_cheval_identity(870);
$meta_before_wrong_upload = $GLOBALS['__gwseq_test_meta'][870];

// --- Upload du PDF réel de TELDAME (autre cheval, autre identité) pour "réimporter" 870 : doit
// être bloqué AVANT même la création du transient de prévisualisation ---
$wrong_tmp = sys_get_temp_dir() . '/gwseq-wrong-cheval-reimport-test.pdf';
copy($teldame_pdf_path, $wrong_tmp);
$wrong_upload = gwseq_process_ifce_import_upload($wrong_tmp, 870);
gws_test_assert(!file_exists($wrong_tmp), 'Verrou réimport (upload) : le fichier temporaire est bien supprimé même en cas de blocage');
gws_test_assert($wrong_upload['notice'] !== null && strpos($wrong_upload['notice'], 'ne correspond pas') !== false, 'Verrou réimport (upload) : message explicite renvoyé, import bloqué avant la preview');
gws_test_assert(strpos($wrong_upload['redirect'], 'gwseq_token') === false, 'Verrou réimport (upload) : AUCUN jeton de prévisualisation créé -- l’écran de preview n’est jamais atteint');
gws_test_assert($GLOBALS['__gwseq_test_meta'][870] === $meta_before_wrong_upload, 'Verrou réimport (upload) : STRICTEMENT AUCUNE meta modifiée sur la fiche cible (identité, SIRE compris)');

// --- Résistance côté serveur entre preview et confirmation (§12) : même si un transient VALIDE
// existe déjà (upload légitime), un changement d'identité de la fiche cible ENTRE upload et
// confirmation doit être détecté et bloquer la confirmation elle-même ---
gws_test_make_post(871, GWSEQ_CPT_CHEVAL, 'TELDAME DE LA NUTRIA'); // nom correspondant au vrai PDF
gwseq_set_cheval_identity(871, array('_gwseq_sexe' => 'female', '_gwseq_annee_naissance' => 2007));
$legit_tmp = sys_get_temp_dir() . '/gwseq-legit-reimport-test.pdf';
copy($teldame_pdf_path, $legit_tmp);
$legit_upload = gwseq_process_ifce_import_upload($legit_tmp, 871);
preg_match('/gwseq_token=([a-zA-Z0-9]+)/', $legit_upload['redirect'], $tm_legit);
gws_test_assert(!empty($tm_legit[1]), 'Verrou réimport : un import légitime (identité concordante) atteint bien la preview normalement');

// L'identité de la fiche 871 change ENTRE l'upload et la confirmation (ex. corrigée manuellement,
// ou un ID IFCE verrouillé sur un AUTRE cheval entre-temps) :
gwseq_set_cheval_ifce_id(871, 'ID_VERROUILLE_ENTRE_TEMPS_XX');
$meta_before_confirm_after_tamper = $GLOBALS['__gwseq_test_meta'][871];
$confirm_after_tamper = gwseq_process_ifce_import_confirm($tm_legit[1], array('identity' => true, 'pedigree' => true));
gws_test_assert($confirm_after_tamper['notice'] !== null, 'Résistance preview -> confirmation (§12) : un changement d’identité de la cible entre upload et confirmation bloque bien la confirmation');
gws_test_assert($GLOBALS['__gwseq_test_meta'][871] === $meta_before_confirm_after_tamper, 'Résistance preview -> confirmation : AUCUNE meta modifiée par une confirmation bloquée a posteriori');

// =====================================================================================
// Enrichissement opportuniste du N° SIRE via SHF (Lot SHF) — chemin de RÉIMPORT, réseau
// intégralement mocké via le filtre `gwseq_ifce_shf_fetch_override` (§10 : jamais de dépendance
// réelle à shf.eu). Réutilise le vrai PDF de Teldame de la Nutria (dont le SIRE, vérifié en amont,
// n'est pas non plus détecté dans la zone exploitée de ce document — même situation que Jamerose).
// =====================================================================================

const GWS_TEST_TELDAME_IFCE_FILENAME = 'fs-complet_classique_0oKAI1qsRfuEsAT2BIQgVA_1788999999999.pdf';

// --- SIRE déjà présent sur la fiche réimportée -> AUCUN appel SHF (§1, "si le cheval possède déjà
// un SIRE non vide : aucun appel SHF"), quelle que soit par ailleurs la validité de l'identité/de
// l'ID IFCE ---
gws_test_make_post(880, GWSEQ_CPT_CHEVAL, 'TELDAME DE LA NUTRIA');
gwseq_set_cheval_identity(880, array('_gwseq_sexe' => 'female', '_gwseq_annee_naissance' => 2007, '_gwseq_sire' => 'SIRE_DEJA_PRESENT'));
$shf_reimport_call_count_1 = 0;
add_filter('gwseq_ifce_shf_fetch_override', function ($default, $url) use (&$shf_reimport_call_count_1) {
  $shf_reimport_call_count_1++;
  return array('http_code' => 200, 'content_type' => 'text/html', 'body' => '<html><body><h1>TELDAME DE LA NUTRIA</h1><div>N° SIRE : 99999999Z</div></body></html>');
});
$teldame_sire_present_tmp = sys_get_temp_dir() . '/gwseq-shf-reimport-sire-present.pdf';
copy($teldame_pdf_path, $teldame_sire_present_tmp);
$reimport_sire_present_upload = gwseq_process_ifce_import_upload($teldame_sire_present_tmp, 880, GWS_TEST_TELDAME_IFCE_FILENAME);
gws_test_assert($shf_reimport_call_count_1 === 0, 'Enrichissement SHF (réimport, §1) : le cheval possède déjà un SIRE non vide -> zéro appel SHF, même avec un ID IFCE valide et une identité concordante');
preg_match('/gwseq_token=([a-zA-Z0-9]+)/', $reimport_sire_present_upload['redirect'], $tm880);
$transient_880 = gwseq_get_ifce_import_transient($tm880[1] ?? '');
gws_test_assert(($transient_880['parsed']['shf_sire'] ?? 'absent') === '', 'Enrichissement SHF (réimport, §1) : $parsed[\'shf_sire\'] reste vide quand aucun appel n’a été tenté (SIRE déjà présent)');
remove_all_filters('gwseq_ifce_shf_fetch_override');

// --- SIRE vide + identité concordante (legacy, nom+année) + ID IFCE extrait -> SHF interrogé UNE
// FOIS, résultat écrit SEULEMENT à la confirmation, avec provenance 'shf' ---
gws_test_make_post(881, GWSEQ_CPT_CHEVAL, 'TELDAME DE LA NUTRIA');
gwseq_set_cheval_identity(881, array('_gwseq_sexe' => 'female', '_gwseq_annee_naissance' => 2007));
$shf_reimport_call_count_2 = 0;
add_filter('gwseq_ifce_shf_fetch_override', function ($default, $url) use (&$shf_reimport_call_count_2) {
  $shf_reimport_call_count_2++;
  return array('http_code' => 200, 'content_type' => 'text/html', 'body' => '<html><body><h1>TELDAME DE LA NUTRIA</h1><div>N° SIRE : 50440832H</div></body></html>');
});
$teldame_sire_empty_tmp = sys_get_temp_dir() . '/gwseq-shf-reimport-sire-empty.pdf';
copy($teldame_pdf_path, $teldame_sire_empty_tmp);
$reimport_sire_empty_upload = gwseq_process_ifce_import_upload($teldame_sire_empty_tmp, 881, GWS_TEST_TELDAME_IFCE_FILENAME);
gws_test_assert($shf_reimport_call_count_2 === 1, 'Enrichissement SHF (réimport, §1) : SIRE vide + identité concordante + ID IFCE extrait -> exactement un appel SHF');
preg_match('/gwseq_token=([a-zA-Z0-9]+)/', $reimport_sire_empty_upload['redirect'], $tm881);
$token_881 = $tm881[1] ?? '';
$transient_881 = gwseq_get_ifce_import_transient($token_881);
gws_test_assert(($transient_881['parsed']['shf_sire'] ?? '') === '50440832H', 'Enrichissement SHF (réimport) : le SIRE trouvé par SHF est bien transporté dans le transient');
$confirm_881 = gwseq_process_ifce_import_confirm($token_881, array('identity' => true));
gws_test_assert(gwseq_get_cheval_identity(881)['sire'] === '50440832H', 'Enrichissement SHF (réimport) : le SIRE proposé par SHF est bien écrit à la confirmation');
gws_test_assert(gwseq_get_cheval_sire_source(881) === 'shf', 'Provenance (§8) : marqueur "shf" bien posé après un réimport ayant réellement utilisé la valeur SHF');
gws_test_assert(gwseq_get_cheval_identity(881)['ueln'] === '', 'UELN (audit, aucune dérivation dans ce lot) : reste vide même après un réimport ayant écrit un SIRE via SHF — aucune déduction hasardeuse de nationalité française');
remove_all_filters('gwseq_ifce_shf_fetch_override');

// --- SIRE vide MAIS mauvais PDF (identité non concordante) -> le verrou d'identité bloque AVANT
// tout appel SHF (§1, "ne contacte pas SHF pour un PDF qui sera ensuite refusé") -- zéro appel,
// zéro écriture ---
gws_test_make_post(882, GWSEQ_CPT_CHEVAL, 'UN AUTRE CHEVAL ATTENDU');
gwseq_set_cheval_identity(882, array('_gwseq_sexe' => 'female', '_gwseq_annee_naissance' => 1999)); // ne concorde jamais avec le vrai PDF de Teldame (2007)
$meta_882_before = $GLOBALS['__gwseq_test_meta'][882];
$shf_reimport_call_count_3 = 0;
add_filter('gwseq_ifce_shf_fetch_override', function ($default, $url) use (&$shf_reimport_call_count_3) {
  $shf_reimport_call_count_3++;
  return array('http_code' => 200, 'content_type' => 'text/html', 'body' => '');
});
$teldame_wrong_identity_tmp = sys_get_temp_dir() . '/gwseq-shf-reimport-wrong-identity.pdf';
copy($teldame_pdf_path, $teldame_wrong_identity_tmp);
$reimport_wrong_identity_upload = gwseq_process_ifce_import_upload($teldame_wrong_identity_tmp, 882, GWS_TEST_TELDAME_IFCE_FILENAME);
gws_test_assert($shf_reimport_call_count_3 === 0, 'Enrichissement SHF (réimport, §1/§12) : un PDF dont l’identité ne concorde pas est bloqué AVANT tout appel SHF -- zéro appel');
gws_test_assert($reimport_wrong_identity_upload['notice'] !== null && strpos($reimport_wrong_identity_upload['redirect'], 'gwseq_token') === false, 'Enrichissement SHF (réimport) : import bloqué par le verrou d’identité, aucun jeton de prévisualisation créé');
gws_test_assert($GLOBALS['__gwseq_test_meta'][882] === $meta_882_before, 'Enrichissement SHF (réimport) : aucune meta modifiée sur la fiche cible quand le PDF est refusé avant tout appel SHF');
remove_all_filters('gwseq_ifce_shf_fetch_override');

// --- Import INITIAL (§13) : le verrou ne s'applique JAMAIS ($reimport_cheval_id = 0), workflow
// inchangé -- déjà couvert par l'ensemble des tests d'import initial de ce fichier et de
// gws-equestrian-ifce-import-test.php ; vérification déclarative explicite ici ---
$initial_tmp = sys_get_temp_dir() . '/gwseq-initial-import-lock-test.pdf';
copy($teldame_pdf_path, $initial_tmp);
$initial_upload = gwseq_process_ifce_import_upload($initial_tmp, 0);
gws_test_assert($initial_upload['notice'] === null, 'Verrou réimport (§13) : un import INITIAL (aucun reimport_cheval_id) n’est jamais soumis au verrou d’identité');

// =====================================================================================
// i18n
// =====================================================================================

foreach ($GLOBALS['__gwseq_test_domains_used'] as $domain) {
  gws_test_assert($domain === 'gws-core', "i18n : aucun appel de traduction n’utilise un text domain autre que \"gws-core\" (trouvé : $domain)");
}

echo ($failures === 0 ? 'Tous les tests sont passés.' : "$failures test(s) en échec.") . "\n";
exit($failures === 0 ? 0 : 1);

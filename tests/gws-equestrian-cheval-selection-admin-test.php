<?php
/**
 * Vérifie l'écran métier « Chevaux → Sélections » (includes/cheval-selection-admin.php, Suite V1
 * « Partager & vendre », Lot 2A) : accès depuis le menu, réutilisation du moteur de recherche/
 * filtrage de l'écran « Partager » avec l'exclusion supplémentaire des chevaux "En préparation"
 * (§5), le point d'entrée AJAX de création (validation/sanitation serveur des IDs soumis, jamais
 * une confiance dans le client), les URLs de gestion du token (régénérer/révoquer) et leur
 * prédicat de permission, la restriction "mes propres sélections" pour un auteur sans
 * `edit_others_posts`, et le chargement conditionnel des assets. Même méthodologie que le reste de
 * cette suite (notamment gws-equestrian-cheval-share-admin-test.php, dont ce fichier réutilise la
 * même architecture de stubs).
 *
 * Ne fait pas partie des paquets livrés (gws-core.zip / gws-starter.zip).
 */

$failures = 0;
function gws_test_assert($condition, $label) {
  global $failures;
  if ($condition) { echo "OK   - $label\n"; }
  else { echo "FAIL - $label\n"; $failures++; }
}

// --- Stubs WordPress minimaux (repris de gws-equestrian-cheval-share-admin-test.php) ---
function wp_unslash($value) { return is_array($value) ? array_map('wp_unslash', $value) : (is_string($value) ? stripslashes($value) : $value); }
function sanitize_text_field($value) { if (is_array($value) || is_object($value)) return ''; return trim(strip_tags((string) $value)); }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value)); }
function sanitize_title($value) { return trim(preg_replace('/[^a-z0-9_\-]+/', '-', strtolower((string) $value)), '-'); }
function esc_url_raw($value) { $value = trim((string) $value); return $value === '' ? '' : $value; }
function esc_url($value) { return $value; }
function absint($value) { return abs((int) $value); }
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function esc_html_e($text, $domain = 'default') { echo esc_html($text); }
function __($text, $domain = 'default') { return $text; }
function esc_html__($text, $domain = 'default') { return esc_html($text); }
function wp_parse_url($url, $component = -1) { return parse_url($url, $component); }
function wp_parse_args($args, $defaults = array()) { return array_merge((array) $defaults, (array) $args); }
function wp_die($message = '') { throw new Gws_Test_Wp_Die_Exception(is_string($message) ? $message : ''); }
class Gws_Test_Wp_Die_Exception extends Exception {}

class WP_Error {
  public $code; public $message;
  public function __construct($code = '', $message = '') { $this->code = $code; $this->message = $message; }
}
function is_wp_error($thing) { return $thing instanceof WP_Error; }

function admin_url($path = '') { return 'https://example.test/wp-admin/' . $path; }
function home_url($path = '') { return 'https://example.test' . $path; }
function add_query_arg($args, $url) {
  $sep = strpos($url, '?') === false ? '?' : '&';
  return $url . $sep . http_build_query($args);
}

const GWSEQ_TAX_CATEGORIE_CHEVAL = 'gwseq_categorie_cheval';
$GLOBALS['__gwseq_test_all_terms'] = array();
function get_terms($args = array()) { return $GLOBALS['__gwseq_test_all_terms']; }
function term_exists($term, $taxonomy = '') { return null; }

$GLOBALS['__gwseq_test_registered_meta'] = array();
function register_post_meta($object_type, $meta_key, $args = array()) { $GLOBALS['__gwseq_test_registered_meta'][$meta_key] = $args; }
$GLOBALS['__gwseq_test_actions'] = array();
function add_action($hook, $callback, $priority = 10, $accepted_args = 1) { $GLOBALS['__gwseq_test_actions'][$hook][] = $callback; }
function remove_action($hook, $callback, $priority = 10) {
  $GLOBALS['__gwseq_test_actions'][$hook] = array_values(array_diff($GLOBALS['__gwseq_test_actions'][$hook] ?? array(), array($callback)));
}
$GLOBALS['__gwseq_test_filters'] = array();
function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) { $GLOBALS['__gwseq_test_filters'][$hook][] = $callback; }
function apply_filters($hook, $value) { return $value; }
$GLOBALS['__gwseq_test_meta_boxes'] = array();
function add_meta_box($id, $title, $callback, $post_type = null, $context = 'advanced', $priority = 'default') {
  $GLOBALS['__gwseq_test_meta_boxes'][] = compact('id', 'title', 'callback', 'post_type', 'context', 'priority');
}
function remove_meta_box($id, $post_type, $context) { $GLOBALS['__gwseq_test_removed_meta_boxes'][] = compact('id', 'post_type', 'context'); }
$GLOBALS['__gwseq_test_removed_meta_boxes'] = array();
$GLOBALS['__gwseq_test_menu_pages'] = array();
function add_submenu_page($parent, $page_title, $menu_title, $capability, $slug, $callback) {
  $GLOBALS['__gwseq_test_menu_pages'][] = compact('parent', 'page_title', 'menu_title', 'capability', 'slug', 'callback');
}

$GLOBALS['__gwseq_test_meta'] = array();
function update_post_meta($post_id, $key, $value) { $GLOBALS['__gwseq_test_meta'][$post_id][$key] = wp_unslash($value); return true; }
function get_post_meta($post_id, $key, $single = false) { return $GLOBALS['__gwseq_test_meta'][$post_id][$key] ?? ''; }
function delete_post_meta($post_id, $key) { unset($GLOBALS['__gwseq_test_meta'][$post_id][$key]); return true; }
function metadata_exists($type, $post_id, $key) {
  return array_key_exists($post_id, $GLOBALS['__gwseq_test_meta']) && array_key_exists($key, $GLOBALS['__gwseq_test_meta'][$post_id]);
}

// --- Registre de posts (chevaux ET sélections) ---
$GLOBALS['__gwseq_test_posts'] = array();
function gws_test_make_post($id, $post_type, $title, $status = 'publish', $author = 1, $password = '') {
  $GLOBALS['__gwseq_test_posts'][$id] = array('post_type' => $post_type, 'post_status' => $status, 'post_title' => $title, 'post_author' => $author, 'post_password' => $password);
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
function get_permalink($post_id) { return 'https://example.test/chevaux/cheval-' . (int) $post_id . '/'; }
function get_the_date($format, $post_id) { return '2026-09-04'; }
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
  gws_test_make_post($id, $postarr['post_type'], $postarr['post_title'] ?? '', $postarr['post_status'] ?? 'draft', $postarr['post_author'] ?? 1);
  return $id;
}

$GLOBALS['__gwseq_test_attachment_urls'] = array();
function wp_get_attachment_image_url($id, $size = 'thumbnail') { return $GLOBALS['__gwseq_test_attachment_urls'][$id][$size] ?? false; }
$GLOBALS['__gwseq_test_thumbnails'] = array();
function get_post_thumbnail_id($post_id) { return $GLOBALS['__gwseq_test_thumbnails'][$post_id] ?? 0; }

$GLOBALS['__gwseq_test_options'] = array();
function get_option($name, $default = false) { return array_key_exists($name, $GLOBALS['__gwseq_test_options']) ? $GLOBALS['__gwseq_test_options'][$name] : $default; }

// --- Sécurité : nonce, capacités générales ET spécifiques à une fiche ---
$GLOBALS['__gwseq_test_security'] = array('nonce_valid' => true, 'edit_posts' => true, 'edit_others_posts' => true, 'publish_posts' => true, 'current_user_id' => 1);
function check_ajax_referer($action, $arg_name = false, $die = true) {
  if (!$GLOBALS['__gwseq_test_security']['nonce_valid']) throw new Gws_Test_Wp_Die_Exception('nonce invalide');
  return true;
}
function current_user_can($cap, $post_id = null) {
  $security = $GLOBALS['__gwseq_test_security'];
  if ($cap === 'edit_posts') return $security['edit_posts'];
  if ($cap === 'edit_others_posts') return $security['edit_others_posts'];
  if ($cap === 'publish_posts') return $security['publish_posts'];
  if ($cap === 'edit_post') {
    if (!$security['edit_posts']) return false;
    $post = get_post($post_id);
    if (!$post) return false;
    if ((int) $post->post_author === (int) $security['current_user_id']) return true;
    return $security['edit_others_posts'];
  }
  return false;
}
function get_current_user_id() { return $GLOBALS['__gwseq_test_security']['current_user_id']; }
function gws_test_reset_security() {
  $GLOBALS['__gwseq_test_security'] = array('nonce_valid' => true, 'edit_posts' => true, 'edit_others_posts' => true, 'publish_posts' => true, 'current_user_id' => 1);
}

$GLOBALS['__gwseq_test_json_response'] = null;
function wp_send_json_success($data = null) { $GLOBALS['__gwseq_test_json_response'] = array('success' => true, 'data' => $data); throw new Gws_Test_Json_Exit(); }
function wp_send_json_error($data = null, $status = null) { $GLOBALS['__gwseq_test_json_response'] = array('success' => false, 'data' => $data, 'status' => $status); throw new Gws_Test_Json_Exit(); }
class Gws_Test_Json_Exit extends Exception {}

$GLOBALS['__gwseq_test_screen'] = null;
function get_current_screen() { return $GLOBALS['__gwseq_test_screen']; }
$GLOBALS['__gwseq_enqueued'] = array();
function wp_enqueue_script($handle, ...$rest) { $GLOBALS['__gwseq_enqueued'][] = $handle; }
function wp_enqueue_style($handle, ...$rest) { $GLOBALS['__gwseq_enqueued'][] = $handle; }
$GLOBALS['__gwseq_test_localized'] = array();
function wp_localize_script($handle, $name, $data) { $GLOBALS['__gwseq_test_localized'][$handle][$name] = $data; }
function wp_create_nonce($action) { return 'nonce-' . $action; }
function wp_nonce_url($url, $action = -1, $name = '_wpnonce') {
  $sep = strpos($url, '?') === false ? '?' : '&';
  return $url . $sep . $name . '=' . wp_create_nonce($action);
}

// --- meta_query/tax_query minimaux (repris de gws-equestrian-cheval-share-admin-test.php) ---
function gws_test_meta_query_matches($post_id, $clause) {
  if (isset($clause['key'])) {
    $value = get_post_meta($post_id, $clause['key'], true);
    if ($value === '') return false;
    $numeric = ($clause['type'] ?? '') === 'NUMERIC';
    $compare = $clause['compare'] ?? '=';
    $v = $numeric ? (float) $value : $value;
    if ($compare === 'BETWEEN') return $v >= $clause['value'][0] && $v <= $clause['value'][1];
    if ($compare === '>=') return $v >= $clause['value'];
    if ($compare === '<=') return $v <= $clause['value'];
    return (string) $value === (string) $clause['value'];
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

class WP_Query {
  public $posts = array();
  public function __construct($args = array()) {
    $post_type = $args['post_type'] ?? 'post';
    $status_filter = $args['post_status'] ?? 'publish';
    $author = $args['author'] ?? null;
    $search = isset($args['s']) ? mb_strtolower(trim((string) $args['s'])) : '';
    $limit = $args['posts_per_page'] ?? -1;
    $meta_query = $args['meta_query'] ?? null;
    $post__in = $args['post__in'] ?? null;
    $orderby = $args['orderby'] ?? null;

    $results = array();
    foreach ($GLOBALS['__gwseq_test_posts'] as $id => $post) {
      if ($post['post_type'] !== $post_type) continue;
      if ($status_filter === 'any') {
        if (in_array($post['post_status'], array('trash', 'auto-draft'), true)) continue;
      } elseif (is_array($status_filter)) {
        if (!in_array($post['post_status'], $status_filter, true)) continue;
      } elseif ($post['post_status'] !== $status_filter) {
        continue;
      }
      if ($author !== null && (int) $post['post_author'] !== (int) $author) continue;
      if ($search !== '' && mb_strpos(mb_strtolower($post['post_title']), $search) === false) continue;
      if ($meta_query && !gws_test_meta_query_matches($id, $meta_query)) continue;
      if (is_array($post__in) && !in_array($id, $post__in, true)) continue;
      $results[] = $id;
    }
    if ($orderby === 'date') $results = array_reverse($results);
    if ($limit > 0) $results = array_slice($results, 0, $limit);
    $this->posts = $results;
  }
}

define('ABSPATH', __DIR__ . '/');
const GWSEQ_CPT_CHEVAL = 'gwseq_cheval';
const GWSEQ_CPT_PRESTATION = 'gwseq_prestation';
const GWSEQ_CPT_GROUPE = 'gwseq_groupe';
const GWSEQ_CPT_MEMBRE = 'gwseq_membre';
const GWSEQ_CPT_SELECTION = 'gwseq_selection';
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
require $module_dir . 'includes/cheval-share-admin.php';
require $module_dir . 'includes/cheval-selection.php';
require $module_dir . 'includes/cheval-selection-admin.php';
require $module_dir . 'includes/admin-ui.php';

function gws_test_make_horse($id, $title, $state, $author = 1) {
  if ($state === GWSEQ_HORSE_DIFFUSION_VISIBLE_SITE) {
    gws_test_make_post($id, GWSEQ_CPT_CHEVAL, $title, 'publish', $author);
  } elseif ($state === GWSEQ_HORSE_DIFFUSION_PRIVEE) {
    gws_test_make_post($id, GWSEQ_CPT_CHEVAL, $title, 'draft', $author);
    gwseq_horse_private_share_activate($id);
  } else {
    gws_test_make_post($id, GWSEQ_CPT_CHEVAL, $title, 'draft', $author);
  }
  gwseq_set_cheval_identity($id, array());
}

// =====================================================================================
// Accès depuis le menu — capacité edit_posts, sous-menu rattaché à "Chevaux"
// =====================================================================================

gwseq_add_selection_page();
$menu_page = $GLOBALS['__gwseq_test_menu_pages'][0];
gws_test_assert($menu_page['parent'] === 'edit.php?post_type=gwseq_cheval', 'Menu : sous-menu rattaché à "Chevaux" (même famille d’écrans que "Partager", pas un menu top-level séparé)');
gws_test_assert($menu_page['capability'] === 'edit_posts', 'Menu : capacité edit_posts, aucune capacité inventée pour ce nouveau CPT interne');
gws_test_assert($menu_page['slug'] === 'gwseq-selections', 'Menu : identifiant d’écran stable');

$url_plain = gwseq_selection_page_url();
gws_test_assert(strpos($url_plain, 'post_type=gwseq_cheval') !== false && strpos($url_plain, 'page=gwseq-selections') !== false, 'URL : écran Sélections correctement construit');

// =====================================================================================
// §5 : les chevaux "En préparation" ne sont jamais proposés sur cet écran, quel que soit le filtre.
// =====================================================================================

$diffusion_options = gwseq_selection_diffusion_filter_options();
gws_test_assert(count($diffusion_options) === 2 && array_key_exists(GWSEQ_HORSE_DIFFUSION_PRIVEE, $diffusion_options) && array_key_exists(GWSEQ_HORSE_DIFFUSION_VISIBLE_SITE, $diffusion_options), 'Filtre "État de diffusion" de cet écran : seulement "Diffusion privée"/"Visible sur le site", jamais "En préparation" (qui ne renverrait jamais aucun résultat ici)');

gws_test_make_horse(200, 'Cheval Visible', GWSEQ_HORSE_DIFFUSION_VISIBLE_SITE);
gws_test_make_horse(201, 'Cheval Prive', GWSEQ_HORSE_DIFFUSION_PRIVEE);
gws_test_make_horse(202, 'Cheval Preparation', GWSEQ_HORSE_DIFFUSION_EN_PREPARATION);

$results_default = gwseq_selection_search_chevaux();
$result_ids_default = array_column($results_default, 'id');
gws_test_assert(in_array(200, $result_ids_default, true) && in_array(201, $result_ids_default, true), 'Recherche Sélections : les chevaux "Visible sur le site" et "Diffusion privée" apparaissent bien par défaut');
gws_test_assert(!in_array(202, $result_ids_default, true), 'Recherche Sélections : "En préparation" jamais proposé, même sans filtre explicite (§5)');

// Tenter de forcer malgré tout le filtre "En préparation" (valeur non permise sur cet écran) —
// traité comme "aucun filtre", jamais une erreur, et ne fait jamais réapparaître le cheval exclu.
$results_forced = gwseq_selection_search_chevaux('', array('diffusion' => GWSEQ_HORSE_DIFFUSION_EN_PREPARATION));
gws_test_assert(!in_array(202, array_column($results_forced, 'id'), true), 'Recherche Sélections : une valeur de filtre "En préparation" forcée depuis le client reste sans effet, jamais un contournement de la restriction serveur');

$results_visible_only = gwseq_selection_search_chevaux('', array('diffusion' => GWSEQ_HORSE_DIFFUSION_VISIBLE_SITE));
$result_ids_visible_only = array_column($results_visible_only, 'id');
gws_test_assert(in_array(200, $result_ids_visible_only, true) && !in_array(201, $result_ids_visible_only, true), 'Recherche Sélections : le filtre "Visible sur le site" reste cumulable et restrictif, exactement comme sur l’écran « Partager »');

// =====================================================================================
// AJAX — sécurité générale (nonce, capacité edit_posts), réutilisée telle quelle.
// =====================================================================================

$GLOBALS['__gwseq_test_security']['nonce_valid'] = false;
$thrown = null;
try { gwseq_ajax_selection_search_cheval(); } catch (Exception $e) { $thrown = $e; }
gws_test_assert($thrown instanceof Gws_Test_Wp_Die_Exception, 'AJAX recherche : nonce invalide -> rejeté avant toute exécution');
gws_test_reset_security();

$GLOBALS['__gwseq_test_security']['edit_posts'] = false;
try { gwseq_ajax_selection_search_cheval(); } catch (Gws_Test_Json_Exit $e) {}
gws_test_assert($GLOBALS['__gwseq_test_json_response']['success'] === false && $GLOBALS['__gwseq_test_json_response']['status'] === 403, 'AJAX recherche : capacité edit_posts absente -> erreur 403');
gws_test_reset_security();

$_POST = array('s' => '', 'filters' => array());
try { gwseq_ajax_selection_search_cheval(); } catch (Gws_Test_Json_Exit $e) {}
gws_test_assert($GLOBALS['__gwseq_test_json_response']['success'] === true, 'AJAX recherche : requête valide -> succès');
gws_test_assert(!in_array(202, array_column($GLOBALS['__gwseq_test_json_response']['data']['resultats'], 'id'), true), 'AJAX recherche : "En préparation" exclu aussi via le point d’entrée AJAX réel');

// =====================================================================================
// AJAX — création (§7/§17) : validation/sanitation serveur des IDs, jamais une confiance dans le
// client.
// =====================================================================================

// Aucun cheval soumis -> erreur explicite, jamais une sélection vide créée silencieusement.
$_POST = array('title' => 'Test', 'cheval_ids' => array());
try { gwseq_ajax_selection_create(); } catch (Gws_Test_Json_Exit $e) {}
gws_test_assert($GLOBALS['__gwseq_test_json_response']['success'] === false, 'AJAX création : aucun cheval soumis -> erreur');

// Un ID "En préparation", un ID d’un autre type de contenu, un ID inexistant, un doublon : tous
// silencieusement écartés (§19 : "données malformées"), seuls les IDs valides et éligibles créent
// la sélection.
gws_test_make_post(203, 'page', 'Une page ordinaire');
$_POST = array('title' => 'Ma sélection AJAX', 'cheval_ids' => array(200, 202, 203, 999999, 200, '201'));
try { gwseq_ajax_selection_create(); } catch (Gws_Test_Json_Exit $e) {}
gws_test_assert($GLOBALS['__gwseq_test_json_response']['success'] === true, 'AJAX création : requête avec des IDs partiellement invalides -> succès malgré tout (les IDs valides suffisent)');
gws_test_assert(!empty($GLOBALS['__gwseq_test_json_response']['data']['redirect']), 'AJAX création : une URL de redirection vers la liste est renvoyée');

// Retrouver la sélection fraîchement créée pour vérifier son contenu réel.
$created_ids = gwseq_selection_query_ids();
$created_id = max($created_ids);
gws_test_assert(gwseq_selection_get_cheval_ids($created_id) === array(200, 201), 'AJAX création : seuls les deux IDs valides et éligibles (200, 201) sont retenus, 202/203/999999/doublon écartés');
gws_test_assert(get_the_title($created_id) === 'Ma sélection AJAX', 'AJAX création : titre transmis correctement enregistré');

// Un cheval qui appartient à un AUTRE auteur, sans droit `edit_others_posts` -> écarté (même
// exigence que gwseq_ajax_partager_get_cheval(), jamais une confiance dans l’ID transmis).
gws_test_make_horse(204, 'Cheval Autre Auteur', GWSEQ_HORSE_DIFFUSION_VISIBLE_SITE, 99);
$GLOBALS['__gwseq_test_security']['edit_others_posts'] = false;
$sanitized = gwseq_selection_sanitize_submitted_cheval_ids(array(200, 204));
gws_test_assert($sanitized === array(200), 'Sanitation IDs soumis : un cheval d’un autre auteur, sans edit_others_posts, est écarté (défense en profondeur, §17)');
gws_test_reset_security();

// Nonce/capacité générale de l’écran, même garde que la recherche.
$GLOBALS['__gwseq_test_security']['nonce_valid'] = false;
$thrown = null;
try { gwseq_ajax_selection_create(); } catch (Exception $e) { $thrown = $e; }
gws_test_assert($thrown instanceof Gws_Test_Wp_Die_Exception, 'AJAX création : nonce invalide -> rejeté avant toute exécution');
gws_test_reset_security();

// =====================================================================================
// Gestion du token (régénérer/révoquer) — URL nonce-protégée + prédicat de permission, jamais les
// fonctions qui appellent réellement exit() (voir gws-equestrian-cheval-share-admin-test.php pour
// la même convention de test).
// =====================================================================================

$selection_owned = gwseq_selection_create(array('title' => 'À moi', 'cheval_ids' => array(200), 'author' => 1));
$selection_others = gwseq_selection_create(array('title' => 'À quelqu’un d’autre', 'cheval_ids' => array(200), 'author' => 42));

gws_test_assert(gwseq_selection_user_can_manage($selection_owned) === true, 'Permission de gestion : le propriétaire peut gérer sa propre sélection');

$GLOBALS['__gwseq_test_security']['edit_others_posts'] = false;
gws_test_assert(gwseq_selection_user_can_manage($selection_owned) === true, 'Permission de gestion : reste vrai pour SA PROPRE sélection même sans edit_others_posts (même modèle que `edit_post` sur Cheval)');
gws_test_assert(gwseq_selection_user_can_manage($selection_others) === false, 'Permission de gestion : refusé pour la sélection d’un AUTRE auteur, sans edit_others_posts');
gws_test_reset_security();

gws_test_assert(gwseq_selection_user_can_manage($selection_others) === true, 'Permission de gestion : autorisé pour la sélection d’un autre auteur avec edit_others_posts (comportement natif WordPress)');
gws_test_assert(gwseq_selection_user_can_manage(999999) === false, 'Permission de gestion : identifiant inexistant -> toujours refusé, jamais une erreur');
gws_test_assert(gwseq_selection_user_can_manage(200) === false, 'Permission de gestion : un ID d’un AUTRE type de contenu (ici un cheval) -> toujours refusé (§17 : "appartenance au bon CPT")');

$url_regenerer = gwseq_selection_action_url('regenerer', $selection_owned);
gws_test_assert(strpos($url_regenerer, 'admin-post.php') !== false, 'URL action token : cible bien admin-post.php');
gws_test_assert(strpos($url_regenerer, 'action=gwseq_selection_regenerer') !== false, 'URL action token : action="regenerer" correcte');
gws_test_assert(strpos($url_regenerer, 'selection_id=' . $selection_owned) !== false, 'URL action token : identifiant de la sélection correctement transmis');
gws_test_assert(strpos($url_regenerer, '_wpnonce=') !== false, 'URL action token : nonce présent');
gws_test_assert(strpos($url_regenerer, 'nonce-gwseq_selection_action_' . $selection_owned) !== false, 'URL action token : nonce généré pour L’ACTION PRÉCISE de cette sélection, jamais un nonce générique réutilisable ailleurs');

$url_revoquer = gwseq_selection_action_url('revoquer', $selection_owned);
gws_test_assert(strpos($url_revoquer, 'action=gwseq_selection_revoquer') !== false, 'URL action token : action="revoquer" correcte, distincte de "regenerer"');

// =====================================================================================
// Liste des sélections (§13, restriction "mes propres sélections" — §21 réutilisé tel quel).
// =====================================================================================

gws_test_assert(gwseq_selection_current_user_restricted_to_own() === false, 'Restriction "mes sélections" : un utilisateur avec edit_others_posts voit tout');
$GLOBALS['__gwseq_test_security']['edit_others_posts'] = false;
gws_test_assert(gwseq_selection_current_user_restricted_to_own() === true, 'Restriction "mes sélections" : un auteur sans edit_others_posts est restreint');

$own_ids = gwseq_selection_query_ids();
gws_test_assert(in_array($selection_owned, $own_ids, true), 'Liste des sélections : ma propre sélection apparaît bien');
gws_test_assert(!in_array($selection_others, $own_ids, true), 'Liste des sélections : la sélection d’un AUTRE auteur n’apparaît pas, sans edit_others_posts');
gws_test_reset_security();

$row = gwseq_selection_admin_row($selection_owned);
gws_test_assert($row['titre'] === 'À moi', 'Ligne d’administration : titre correct');
gws_test_assert($row['total_chevaux'] === 1 && $row['chevaux_diffusables'] === 1, 'Ligne d’administration : comptage total/diffusable correct pour un cheval visible sur le site');
gws_test_assert($row['token_actif'] === true && $row['url'] !== '', 'Ligne d’administration : token actif dès la création, URL exploitable');
gws_test_assert(strpos($row['url_regenerer'], 'gwseq_selection_regenerer') !== false && strpos($row['url_revoquer'], 'gwseq_selection_revoquer') !== false, 'Ligne d’administration : les deux URLs de gestion du token sont fournies');

// Un cheval de la sélection repasse "En préparation" après coup (§6/§19) — la ligne reflète bien
// 0 diffusable sur 1 total, jamais une erreur, jamais la sélection retirée de la liste.
gwseq_horse_diffusion_set_en_preparation(200);
$row_after_change = gwseq_selection_admin_row($selection_owned);
gws_test_assert($row_after_change['total_chevaux'] === 1 && $row_after_change['chevaux_diffusables'] === 0, 'Ligne d’administration : "0 diffusable(s) / 1" après un changement de diffusion ultérieur, calculé à la volée');
gwseq_horse_diffusion_set_visible_site(200);

// =====================================================================================
// Assets — uniquement sur l’écran Sélections.
// ================================ =====================================================

$GLOBALS['__gwseq_test_screen'] = (object) array('id' => 'chevaux_page_gwseq-partager');
$GLOBALS['__gwseq_enqueued'] = array();
gwseq_enqueue_selection_admin_assets('chevaux_page_gwseq-partager');
gws_test_assert($GLOBALS['__gwseq_enqueued'] === array(), 'Assets : jamais chargés sur un autre écran du BO (ici l’écran « Partager »)');

$GLOBALS['__gwseq_test_screen'] = (object) array('id' => 'chevaux_page_gwseq-selections');
gwseq_enqueue_selection_admin_assets('chevaux_page_gwseq-selections');
gws_test_assert(in_array('gwseq-cheval-selection-admin', $GLOBALS['__gwseq_enqueued'], true), 'Assets : script chargé sur l’écran Sélections');
$localized = $GLOBALS['__gwseq_test_localized']['gwseq-cheval-selection-admin']['gwseqSelections'];
gws_test_assert(is_array($localized['existantes']) && count($localized['existantes']) >= 1, 'Assets : la liste des sélections existantes est bien transmise au script');
gws_test_assert(array_key_exists('diffusion', $localized['filters']) && count($localized['filters']['diffusion']) === 2, 'Assets : le filtre diffusion transmis exclut bien "En préparation"');
gws_test_assert($localized['i18n']['allDiffusion'] === 'Tous les états de diffusion', 'Assets : vocabulaire identique à l’écran « Partager », jamais un second référentiel de libellés');

echo "\n";
if ($failures > 0) {
  echo "$failures test(s) en échec.\n";
  exit(1);
}
echo "Tous les tests sont passés.\n";

<?php
/**
 * Vérifie l'écran métier « Chevaux → Partager » (includes/cheval-share-admin.php) : accès depuis
 * le menu et depuis une fiche cheval (action de ligne + boîte latérale), les trois points d'entrée
 * AJAX (recherche légère, données complètes, composition du message) et leur sécurité
 * (nonce/capacités, y compris la restriction "chevaux auxquels l'utilisateur a accès" pour un
 * auteur sans `edit_others_posts`), la sanitation de la sélection soumise par le client, et le
 * chargement conditionnel des assets. Même méthodologie que le reste de cette suite.
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
function wp_unslash($value) { return is_array($value) ? array_map('wp_unslash', $value) : (is_string($value) ? stripslashes($value) : $value); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function sanitize_textarea_field($value) {
  $value = (string) $value;
  $value = preg_replace('@<(script|style)[^>]*?>.*?</\1>@si', '', $value);
  return trim(strip_tags($value));
}
function sanitize_key($value) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value)); }
function sanitize_title($value) { return trim(preg_replace('/[^a-z0-9_\-]+/', '-', strtolower((string) $value)), '-'); }
function esc_url_raw($value) { $value = trim((string) $value); return $value === '' ? '' : $value; }
function esc_url($value) { return $value; }
function absint($value) { return abs((int) $value); }
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function esc_html_e($text, $domain = 'default') { echo esc_html($text); }
function esc_attr_e($text, $domain = 'default') { echo esc_attr($text); }
function __($text, $domain = 'default') { return $text; }
function _n($single, $plural, $number, $domain = 'default') { return $number == 1 ? $single : $plural; }
function esc_html__($text, $domain = 'default') { return esc_html($text); }
function esc_attr__($text, $domain = 'default') { return esc_attr($text); }
function selected($a, $b, $echo = true) { $r = $a == $b ? " selected='selected'" : ''; if ($echo) echo $r; return $r; }
function checked($a, $b = true, $echo = true) { $r = $a == $b ? " checked='checked'" : ''; if ($echo) echo $r; return $r; }
function wp_nonce_field($action, $field) { echo '<input type="hidden" name="' . esc_attr($field) . '" value="stub-nonce">'; }
function wp_json_encode($data, $options = 0, $depth = 512) { return json_encode($data, $options, $depth); }
function remove_accents($text) { return strtr((string) $text, array('é' => 'e', 'è' => 'e', 'à' => 'a')); }
function wp_parse_url($url, $component = -1) { return parse_url($url, $component); }
function wp_parse_args($args, $defaults = array()) { return array_merge((array) $defaults, (array) $args); }

const GWSEQ_TAX_CATEGORIE_CHEVAL = 'gwseq_categorie_cheval';
$GLOBALS['__gwseq_test_all_terms'] = array();
function gws_test_make_term($slug, $name) {
  $GLOBALS['__gwseq_test_all_terms'][] = (object) array('slug' => $slug, 'name' => $name, 'taxonomy' => GWSEQ_TAX_CATEGORIE_CHEVAL);
}
function get_terms($args = array()) { return $GLOBALS['__gwseq_test_all_terms']; }
function term_exists($term, $taxonomy = '') {
  foreach ($GLOBALS['__gwseq_test_all_terms'] as $t) {
    if ($t->slug === $term && ($taxonomy === '' || $t->taxonomy === $taxonomy)) return array('term_id' => $t->slug, 'term_taxonomy_id' => $t->slug);
  }
  return null;
}
function wp_die($message = '') { throw new Gws_Test_Wp_Die_Exception(is_string($message) ? $message : ''); }
class Gws_Test_Wp_Die_Exception extends Exception {}

class WP_Error {
  public $code; public $message; public $data;
  public function __construct($code = '', $message = '', $data = null) { $this->code = $code; $this->message = $message; $this->data = $data; }
}
function is_wp_error($thing) { return $thing instanceof WP_Error; }

function admin_url($path = '') { return 'https://example.test/wp-admin/' . $path; }
function home_url($path = '') { return 'https://example.test' . $path; }
function is_admin() { return false; }
function get_query_var($var, $default = '') { return $default; }
$GLOBALS['__gwseq_test_nocache_headers_called'] = 0;
function nocache_headers() { $GLOBALS['__gwseq_test_nocache_headers_called']++; }
function add_query_arg($args, $url) {
  $sep = strpos($url, '?') === false ? '?' : '&';
  return $url . $sep . http_build_query($args);
}

$GLOBALS['__gwseq_test_registered_meta'] = array();
function register_post_meta($object_type, $meta_key, $args = array()) { $GLOBALS['__gwseq_test_registered_meta'][$meta_key] = $args; }
$GLOBALS['__gwseq_test_actions'] = array();
function add_action($hook, $callback, $priority = 10, $accepted_args = 1) { $GLOBALS['__gwseq_test_actions'][$hook][] = $callback; }
$GLOBALS['__gwseq_test_filters'] = array();
function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) { $GLOBALS['__gwseq_test_filters'][$hook][] = $callback; }
function apply_filters($hook, $value) { return $value; }
$GLOBALS['__gwseq_test_menu_pages'] = array();
function add_submenu_page($parent, $page_title, $menu_title, $capability, $slug, $callback) {
  $GLOBALS['__gwseq_test_menu_pages'][] = compact('parent', 'page_title', 'menu_title', 'capability', 'slug', 'callback');
}
$GLOBALS['__gwseq_test_meta_boxes'] = array();
function add_meta_box($id, $title, $callback, $post_type = null, $context = 'advanced', $priority = 'default') {
  $GLOBALS['__gwseq_test_meta_boxes'][] = compact('id', 'title', 'callback', 'post_type', 'context', 'priority');
}

$GLOBALS['__gwseq_test_meta'] = array();
function update_post_meta($post_id, $key, $value) { $GLOBALS['__gwseq_test_meta'][$post_id][$key] = wp_unslash($value); return true; }
function get_post_meta($post_id, $key, $single = false) { return $GLOBALS['__gwseq_test_meta'][$post_id][$key] ?? ''; }
function delete_post_meta($post_id, $key) { unset($GLOBALS['__gwseq_test_meta'][$post_id][$key]); return true; }
function metadata_exists($type, $post_id, $key) {
  return array_key_exists($post_id, $GLOBALS['__gwseq_test_meta']) && array_key_exists($key, $GLOBALS['__gwseq_test_meta'][$post_id]);
}

// --- Registre de posts, avec auteur (nécessaire pour la restriction "chevaux auxquels
// l'utilisateur a accès", §21/§27) ---
$GLOBALS['__gwseq_test_posts'] = array();
function gws_test_make_post($id, $post_type, $title, $status = 'publish', $author = 1) {
  $GLOBALS['__gwseq_test_posts'][$id] = array('post_type' => $post_type, 'post_status' => $status, 'post_title' => $title, 'post_author' => $author, 'post_password' => '');
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
// Réplique fidèle du comportement réel de get_edit_post_link() suffisant pour ce test : chaîne vide
// si le post n'existe pas ou si l'utilisateur courant ne peut pas l'éditer (WordPress fait de même),
// sinon l'URL d'édition admin — jamais dérivée d'une entrée utilisateur (aucun risque d'open
// redirect, quel que soit le résultat).
function get_edit_post_link($post_id, $context = 'display') {
  $post = get_post($post_id);
  if (!$post || !current_user_can('edit_post', $post_id)) return '';
  return 'https://example.test/wp-admin/post.php?post=' . (int) $post_id . '&action=edit';
}

$GLOBALS['__gwseq_test_attachment_urls'] = array();
function wp_get_attachment_image_url($id, $size = 'thumbnail') { return $GLOBALS['__gwseq_test_attachment_urls'][$id][$size] ?? false; }
$GLOBALS['__gwseq_test_thumbnails'] = array();
function get_post_thumbnail_id($post_id) { return $GLOBALS['__gwseq_test_thumbnails'][$post_id] ?? 0; }

$GLOBALS['__gwseq_test_options'] = array();
function get_option($name, $default = false) {
  return array_key_exists($name, $GLOBALS['__gwseq_test_options']) ? $GLOBALS['__gwseq_test_options'][$name] : $default;
}

// --- Sécurité : nonce, capacités générales ET spécifiques à une fiche (méta-capacité `edit_post`,
// fidèle au modèle natif WordPress : un auteur peut toujours éditer SES PROPRES fiches, seule
// `edit_others_posts` autorise l'accès aux fiches d'un AUTRE auteur) ---
$GLOBALS['__gwseq_test_security'] = array('nonce_valid' => true, 'edit_posts' => true, 'edit_others_posts' => true, 'current_user_id' => 1);
function check_ajax_referer($action, $arg_name = false, $die = true) {
  if (!$GLOBALS['__gwseq_test_security']['nonce_valid']) throw new Gws_Test_Wp_Die_Exception('nonce invalide');
  return true;
}
function current_user_can($cap, $post_id = null) {
  $security = $GLOBALS['__gwseq_test_security'];
  if ($cap === 'edit_posts') return $security['edit_posts'];
  if ($cap === 'edit_others_posts') return $security['edit_others_posts'];
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

$GLOBALS['__gwseq_test_json_response'] = null;
function wp_send_json_success($data = null) { $GLOBALS['__gwseq_test_json_response'] = array('success' => true, 'data' => $data); throw new Gws_Test_Json_Exit(); }
function wp_send_json_error($data = null, $status = null) { $GLOBALS['__gwseq_test_json_response'] = array('success' => false, 'data' => $data, 'status' => $status); throw new Gws_Test_Json_Exit(); }
class Gws_Test_Json_Exit extends Exception {}

$GLOBALS['__gwseq_test_screen'] = null;
function get_current_screen() { return $GLOBALS['__gwseq_test_screen']; }
$GLOBALS['__gwseq_enqueued'] = array();
function wp_enqueue_script($handle, ...$rest) { $GLOBALS['__gwseq_enqueued'][] = $handle; }
function wp_enqueue_style($handle, ...$rest) { $GLOBALS['__gwseq_enqueued'][] = $handle; }
function wp_localize_script($handle, $name, $data) { $GLOBALS['__gwseq_test_localized'][$handle][$name] = $data; }
function wp_create_nonce($action) { return 'nonce-' . $action; }
function wp_nonce_url($url, $action = -1, $name = '_wpnonce') {
  $sep = strpos($url, '?') === false ? '?' : '&';
  return $url . $sep . $name . '=' . wp_create_nonce($action);
}

// --- WP_Query minimal, fidèle au strict nécessaire de gwseq_horse_share_query_chevaux() :
// post_type/post_status('any' = tout sauf corbeille/auto-draft)/author/s (recherche substring sur
// le titre)/posts_per_page/fields('ids') ---
// --- meta_query minimal, avec support "compare"/"type" (correctif de recette §3-4 : plage d'année
// de naissance) — même principe que gws-equestrian-pedigree-logic-test.php, étendu ici avec
// >= / <= / BETWEEN, jamais interprété comme du SQL réel. ---
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

// --- Catégorie de cheval : association post <-> terme, minimale mais suffisante pour tax_query ---
$GLOBALS['__gwseq_test_post_terms'] = array();
function gws_test_assign_term($post_id, $slug) { $GLOBALS['__gwseq_test_post_terms'][$post_id][] = $slug; }
function gws_test_tax_query_matches($post_id, $tax_query) {
  $post_terms = $GLOBALS['__gwseq_test_post_terms'][$post_id] ?? array();
  foreach ($tax_query as $clause) {
    if (!is_array($clause) || !isset($clause['terms'])) continue;
    if (!array_intersect((array) $clause['terms'], $post_terms)) return false;
  }
  return true;
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
    $tax_query = $args['tax_query'] ?? null;

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
      if ($tax_query && !gws_test_tax_query_matches($id, $tax_query)) continue;
      $results[] = $id;
    }
    if ($limit > 0) $results = array_slice($results, 0, $limit);
    $this->posts = $results;
  }
}

define('ABSPATH', __DIR__ . '/');
const GWSEQ_CPT_CHEVAL = 'gwseq_cheval';
const GWSEQ_CPT_PRESTATION = 'gwseq_prestation';
const GWSEQ_CPT_GROUPE = 'gwseq_groupe';
const GWSEQ_CPT_MEMBRE = 'gwseq_membre';
define('GWSEQ_MODULE_URL', 'https://example.test/wp-content/plugins/gws-core/modules/gws-equestrian/');
define('GWSEQ_MODULE_VERSION', 'test');
function remove_meta_box($id, $post_type, $context) {}

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
require $module_dir . 'includes/admin-ui.php';

function gws_test_make_horse($id, $title, $author = 1, $overrides = array()) {
  gws_test_make_post($id, GWSEQ_CPT_CHEVAL, $title, $overrides['post_status'] ?? 'publish', $author);
  gwseq_set_cheval_identity($id, $overrides['identity'] ?? array());
}

// =====================================================================================
// Accès depuis le menu (§5) : capacité edit_posts, jamais une capacité inventée
// =====================================================================================

gwseq_add_horse_share_page();
$menu_page = $GLOBALS['__gwseq_test_menu_pages'][0];
gws_test_assert($menu_page['parent'] === 'edit.php?post_type=gwseq_cheval', 'Menu : sous-menu rattaché à "Chevaux" (même écran, pas un menu top-level séparé)');
gws_test_assert($menu_page['capability'] === 'edit_posts', 'Menu : capacité edit_posts, cohérente avec le type d’objet Cheval (aucune capacité personnalisée)');
gws_test_assert($menu_page['slug'] === 'gwseq-partager', 'Menu : identifiant d’écran stable');

$url_plain = gwseq_horse_share_page_url();
gws_test_assert(strpos($url_plain, 'post_type=gwseq_cheval') !== false && strpos($url_plain, 'page=gwseq-partager') !== false, 'URL : écran Partager correctement construit');
$url_with_id = gwseq_horse_share_page_url(array('cheval_id' => 42));
gws_test_assert(strpos($url_with_id, 'cheval_id=42') !== false, 'URL : présélection d’un cheval encodée dans le lien');

// =====================================================================================
// Accès depuis une fiche cheval (§6) : action de ligne + boîte latérale, MÊME écran
// =====================================================================================

gws_test_make_horse(100, 'Jamerose de Felines', 1);
$post_100 = get_post(100);
$actions = gwseq_add_horse_share_row_action(array('edit' => '<a>Modifier</a>'), $post_100);
gws_test_assert(array_key_exists('gwseq_partager', $actions), 'Action de ligne : "Partager" ajoutée pour un cheval');
gws_test_assert(strpos($actions['gwseq_partager'], 'cheval_id=100') !== false, 'Action de ligne : lien vers le MÊME écran, cheval présélectionné');
gws_test_assert(array_key_exists('edit', $actions), 'Action de ligne : les autres actions natives restent intactes');

$other_post = (object) array('ID' => 5, 'post_type' => 'page', 'post_author' => 1);
$actions_page = gwseq_add_horse_share_row_action(array('edit' => '<a>Modifier</a>'), $other_post);
gws_test_assert(!array_key_exists('gwseq_partager', $actions_page), 'Action de ligne : jamais ajoutée sur un autre post type (Pages)');

gwseq_add_horse_share_meta_box();
$meta_box = $GLOBALS['__gwseq_test_meta_boxes'][0];
gws_test_assert($meta_box['post_type'] === GWSEQ_CPT_CHEVAL && $meta_box['context'] === 'side', 'Boîte latérale "Partage" enregistrée en colonne latérale, jamais surchargée dans le corps de l’écran');

ob_start();
call_user_func($meta_box['callback'], $post_100);
$meta_box_html = ob_get_clean();
gws_test_assert(strpos($meta_box_html, 'cheval_id=100') !== false, 'Boîte latérale : bouton "Partager ce cheval" pointe vers le MÊME écran que l’action de ligne (jamais une seconde interface)');

$auto_draft = (object) array('ID' => 101, 'post_status' => 'auto-draft');
ob_start();
call_user_func($meta_box['callback'], $auto_draft);
$meta_box_auto_draft_html = ob_get_clean();
gws_test_assert(strpos($meta_box_auto_draft_html, 'cheval_id=') === false, 'Boîte latérale : pas de lien de partage tant que la fiche n’a jamais été enregistrée (auto-draft)');

// =====================================================================================
// Vignette de remplacement neutre (correctif de recette §2) — élément d'interface réutilisable,
// jamais un média/une image à la une fabriqués.
// =====================================================================================

$placeholder_html = gwseq_render_media_placeholder();
gws_test_assert(strpos($placeholder_html, 'gwseq-media-placeholder') !== false, 'Vignette : classe CSS partagée présente (réutilisable ailleurs dans le BO)');
gws_test_assert(strpos($placeholder_html, 'dashicons-pets') !== false, 'Vignette : réutilise le dashicon déjà choisi comme icône de menu "Chevaux", jamais une nouvelle icône');
gws_test_assert(strpos($placeholder_html, 'aria-hidden="true"') !== false, 'Vignette : élément purement décoratif, masqué aux technologies d’assistance');

$placeholder_html_extra = gwseq_render_media_placeholder('gwseq-partager-result__photo');
gws_test_assert(strpos($placeholder_html_extra, 'gwseq-media-placeholder') !== false && strpos($placeholder_html_extra, 'gwseq-partager-result__photo') !== false, 'Vignette : classe supplémentaire de dimensionnement combinable avec la classe partagée');

// =====================================================================================
// AJAX — sécurité générale (nonce, capacité edit_posts)
// =====================================================================================

function gws_test_reset_security() {
  $GLOBALS['__gwseq_test_security'] = array('nonce_valid' => true, 'edit_posts' => true, 'edit_others_posts' => true, 'current_user_id' => 1);
}

$GLOBALS['__gwseq_test_security']['nonce_valid'] = false;
$thrown = null;
try { gwseq_ajax_partager_search_cheval(); } catch (Exception $e) { $thrown = $e; }
gws_test_assert($thrown instanceof Gws_Test_Wp_Die_Exception, 'AJAX recherche : nonce invalide -> rejeté avant toute exécution');
gws_test_reset_security();

$GLOBALS['__gwseq_test_security']['edit_posts'] = false;
$json = null;
try { gwseq_ajax_partager_search_cheval(); } catch (Gws_Test_Json_Exit $e) { $json = $GLOBALS['__gwseq_test_json_response']; }
gws_test_assert($json !== null && $json['success'] === false, 'AJAX recherche : capacité edit_posts manquante -> erreur, jamais de résultat');
gws_test_reset_security();

// =====================================================================================
// AJAX recherche (§27) : légère, scopée aux chevaux accessibles, "s" vide -> derniers modifiés
// =====================================================================================

gws_test_make_horse(200, 'Jamerose de Felines', 1, array('identity' => array('_gwseq_sexe' => 'female', '_gwseq_annee_naissance' => (int) gmdate('Y') - 5)));
gws_test_make_horse(201, 'Kannan du Fief', 1);
gws_test_make_horse(202, 'Cheval d’un autre utilisateur', 2);
$GLOBALS['__gwseq_test_attachment_urls'][0]['thumbnail'] = false;

// Utilisateur courant (#1) SANS edit_others_posts : ne doit voir que ses propres chevaux (200/201),
// jamais celui d'un autre auteur (202) — même règle que la liste native `Chevaux → Tous les chevaux`.
$GLOBALS['__gwseq_test_security']['edit_others_posts'] = false;
$_POST = array('nonce' => 'valid', 's' => '');
$json = null;
try { gwseq_ajax_partager_search_cheval(); } catch (Gws_Test_Json_Exit $e) { $json = $GLOBALS['__gwseq_test_json_response']; }
$ids_recents = array_column($json['data']['resultats'], 'id');
gws_test_assert($json['success'] === true, 'AJAX recherche : réponse de succès');
gws_test_assert(in_array(200, $ids_recents, true) && in_array(201, $ids_recents, true), 'AJAX recherche : requête vide -> liste des chevaux accessibles (recherche "récents")');
gws_test_assert(!in_array(202, $ids_recents, true), 'AJAX recherche : un auteur sans edit_others_posts ne voit JAMAIS les chevaux d’un autre auteur (§21/§27)');

$row_200 = current(array_filter($json['data']['resultats'], function ($row) { return $row['id'] === 200; }));
gws_test_assert($row_200['nom'] === 'Jamerose de Felines' && isset($row_200['sous_titre']), 'AJAX recherche : ligne légère (nom + sous-titre résumé), jamais les données complètes');

$_POST = array('nonce' => 'valid', 's' => 'kannan');
$json = null;
try { gwseq_ajax_partager_search_cheval(); } catch (Gws_Test_Json_Exit $e) { $json = $GLOBALS['__gwseq_test_json_response']; }
$ids_kannan = array_column($json['data']['resultats'], 'id');
gws_test_assert($ids_kannan === array(201), 'AJAX recherche : recherche par nom fonctionnelle, insensible à la casse');

// --- Un utilisateur avec edit_others_posts voit bien tous les chevaux, y compris ceux des autres ---
$GLOBALS['__gwseq_test_security']['edit_others_posts'] = true;
$GLOBALS['__gwseq_test_security']['current_user_id'] = 2;
$_POST = array('nonce' => 'valid', 's' => '');
$json = null;
try { gwseq_ajax_partager_search_cheval(); } catch (Gws_Test_Json_Exit $e) { $json = $GLOBALS['__gwseq_test_json_response']; }
$ids_all = array_column($json['data']['resultats'], 'id');
gws_test_assert(in_array(200, $ids_all, true) && in_array(202, $ids_all, true), 'AJAX recherche : un utilisateur avec edit_others_posts voit bien les chevaux de tous les auteurs');
gws_test_reset_security();
$_POST = array();

// =====================================================================================
// Correctif de recette — décodage du titre dans la ligne légère de résultat (gwseq_horse_share_
// lightweight_row(), même correctif que gwseq_get_horse_shareable_data() dans cheval-share.php) :
// un titre contenant une entité HTML littérale ne doit jamais apparaître tel quel dans les
// résultats de recherche, et un titre "dangereux" ne doit jamais produire de HTML exécutable une
// fois décodé (texte simple uniquement, jamais un `wp_kses`/`innerHTML` — voir le rendu JS qui
// n'utilise que `textContent`).
// =====================================================================================

gws_test_make_horse(220, "Nacelle D&rsquo;Elle", 1);
gws_test_make_horse(221, '&lt;img src=x onerror=alert(1)&gt;', 1);
$_POST = array('nonce' => 'valid', 's' => 'nacelle');
$json = null;
try { gwseq_ajax_partager_search_cheval(); } catch (Gws_Test_Json_Exit $e) { $json = $GLOBALS['__gwseq_test_json_response']; }
$row_220 = current($json['data']['resultats']);
gws_test_assert($row_220['nom'] === 'Nacelle D’Elle', 'Ligne légère (recherche) : titre contenant une entité littérale "&rsquo;" décodé, plus jamais affiché tel quel');
gws_test_assert(strpos($row_220['nom'], '&rsquo;') === false, 'Ligne légère (recherche) : aucune entité résiduelle dans le nom exposé au client');

$row_221 = gwseq_horse_share_lightweight_row(221);
gws_test_assert($row_221['nom'] === '<img src=x onerror=alert(1)>', 'Ligne légère : titre dangereux décodé en texte littéral inerte (chaîne PHP simple, jamais du HTML exécuté côté serveur)');
$_POST = array();

// =====================================================================================
// Filtres métier (correctif de recette §3-4) : sexe, statut commercial, plage d'année de
// naissance, catégorie de cheval — cumulatifs entre eux ET avec la recherche texte.
// =====================================================================================

gws_test_assert(
  gwseq_sanitize_horse_share_filters(array('sexe' => 'female'))['sexe'] === 'female',
  'Filtres : sexe valide conservé (valeur technique du référentiel existant)'
);
gws_test_assert(
  gwseq_sanitize_horse_share_filters(array('sexe' => 'licorne'))['sexe'] === '',
  'Filtres : une valeur de sexe hors référentiel est ignorée, jamais propagée à la requête'
);
gws_test_assert(
  gwseq_sanitize_horse_share_filters(array('statut' => 'for_sale'))['statut'] === 'for_sale',
  'Filtres : statut commercial valide conservé (valeur interne exacte)'
);
gws_test_assert(
  gwseq_sanitize_horse_share_filters(array('statut' => 'invalide'))['statut'] === '',
  'Filtres : un statut hors référentiel est ignoré'
);
$filters_annee = gwseq_sanitize_horse_share_filters(array('annee_min' => '2021', 'annee_max' => '2018'));
gws_test_assert($filters_annee['annee_min'] === 2018 && $filters_annee['annee_max'] === 2021, 'Filtres : des bornes d’année inversées sont simplement échangées, jamais une erreur');
gws_test_assert(
  gwseq_sanitize_horse_share_filters(array('annee_min' => '1500'))['annee_min'] === 0,
  'Filtres : une année hors des bornes déjà établies pour Cheval (GWSEQ_CHEVAL_ANNEE_MIN) est ignorée, aucune seconde limite inventée'
);
gws_test_make_term('chevaux_de_sport', 'Chevaux de sport');
gws_test_assert(
  gwseq_sanitize_horse_share_filters(array('categorie' => 'chevaux_de_sport'))['categorie'] === 'chevaux_de_sport',
  'Filtres : catégorie réellement configurée sur le site conservée'
);
gws_test_assert(
  gwseq_sanitize_horse_share_filters(array('categorie' => 'inexistante'))['categorie'] === '',
  'Filtres : une catégorie qui n’existe pas est ignorée, jamais créée automatiquement (§3)'
);

$args_annee = gwseq_horse_share_filters_to_query_args(array('annee_min' => 2018, 'annee_max' => 2021, 'sexe' => '', 'statut' => '', 'categorie' => ''));
gws_test_assert($args_annee['meta_query'][0]['compare'] === 'BETWEEN', 'Filtres : plage d’année complète -> comparaison BETWEEN');
$args_categorie = gwseq_horse_share_filters_to_query_args(array('categorie' => 'chevaux_de_sport', 'sexe' => '', 'statut' => '', 'annee_min' => 0, 'annee_max' => 0));
gws_test_assert($args_categorie['tax_query'][0]['taxonomy'] === GWSEQ_TAX_CATEGORIE_CHEVAL && $args_categorie['tax_query'][0]['terms'] === 'chevaux_de_sport', 'Filtres : catégorie transformée en tax_query sur la taxonomie déjà existante');
$args_vides = gwseq_horse_share_filters_to_query_args(array());
gws_test_assert(!isset($args_vides['meta_query']) && !isset($args_vides['tax_query']), 'Filtres : aucun filtre actif -> aucune contrainte meta_query/tax_query ajoutée à la requête');

// --- Cumul réel via l'AJAX de recherche : sexe + statut + plage d'année + catégorie + texte ---
gws_test_make_horse(210, 'Jument Filtrée', 1, array('identity' => array('_gwseq_sexe' => 'female', '_gwseq_annee_naissance' => 2019)));
$commercial_210 = gwseq_sanitize_cheval_commercial_input(array('_gwseq_statut_commercial' => 'for_sale'));
update_post_meta(210, '_gwseq_statut_commercial', $commercial_210['statut_commercial']);
gws_test_assign_term(210, 'chevaux_de_sport');

gws_test_make_horse(211, 'Jument Hors Categorie', 1, array('identity' => array('_gwseq_sexe' => 'female', '_gwseq_annee_naissance' => 2019)));
update_post_meta(211, '_gwseq_statut_commercial', 'for_sale');
// Pas de catégorie assignée à 211 -> doit être exclue par le filtre catégorie.

gws_test_make_horse(212, 'Étalon Filtré', 1, array('identity' => array('_gwseq_sexe' => 'male', '_gwseq_annee_naissance' => 2019)));
update_post_meta(212, '_gwseq_statut_commercial', 'for_sale');
gws_test_assign_term(212, 'chevaux_de_sport');
// Sexe différent -> doit être exclu par le filtre sexe.

$_POST = array('nonce' => 'valid', 's' => 'filtr', 'filters' => array('sexe' => 'female', 'statut' => 'for_sale', 'annee_min' => '2018', 'annee_max' => '2021', 'categorie' => 'chevaux_de_sport'));
$json = null;
try { gwseq_ajax_partager_search_cheval(); } catch (Gws_Test_Json_Exit $e) { $json = $GLOBALS['__gwseq_test_json_response']; }
$ids_filtres = array_column($json['data']['resultats'], 'id');
gws_test_assert($ids_filtres === array(210), 'Filtres cumulés + recherche texte : seul le cheval correspondant à TOUS les critères à la fois est retourné (§4)');
$_POST = array();

// --- Réinitialisation : filtres vides -> comportement identique à une recherche sans filtre ---
$_POST = array('nonce' => 'valid', 's' => '', 'filters' => array());
$json = null;
try { gwseq_ajax_partager_search_cheval(); } catch (Gws_Test_Json_Exit $e) { $json = $GLOBALS['__gwseq_test_json_response']; }
$ids_sans_filtre = array_column($json['data']['resultats'], 'id');
gws_test_assert(in_array(210, $ids_sans_filtre, true) && in_array(211, $ids_sans_filtre, true) && in_array(212, $ids_sans_filtre, true), 'Réinitialisation des filtres : tous les chevaux accessibles réapparaissent');
$_POST = array();

// --- Non-régression de la restriction de permission avec des filtres actifs ---
$GLOBALS['__gwseq_test_security']['edit_others_posts'] = false;
$GLOBALS['__gwseq_test_security']['current_user_id'] = 2;
$_POST = array('nonce' => 'valid', 's' => '', 'filters' => array('sexe' => 'female'));
$json = null;
try { gwseq_ajax_partager_search_cheval(); } catch (Gws_Test_Json_Exit $e) { $json = $GLOBALS['__gwseq_test_json_response']; }
$ids_filtres_scoped = array_column($json['data']['resultats'], 'id');
gws_test_assert(!in_array(210, $ids_filtres_scoped, true) && !in_array(211, $ids_filtres_scoped, true), 'Filtres : la restriction de permission (§21) reste appliquée même avec des filtres actifs — aucune fuite de chevaux inaccessibles');
gws_test_reset_security();
$_POST = array();

// --- Les recherches/filtres ne modifient jamais aucune donnée Cheval ---
$identity_210_before = gwseq_get_cheval_identity(210);
$commercial_210_before = gwseq_get_cheval_commercial(210);
gwseq_horse_share_search_chevaux('filtr', array('sexe' => 'female', 'statut' => 'for_sale', 'annee_min' => 2018, 'annee_max' => 2021, 'categorie' => 'chevaux_de_sport'));
gws_test_assert($identity_210_before === gwseq_get_cheval_identity(210) && $commercial_210_before === gwseq_get_cheval_commercial(210), 'Filtres/recherche : aucune donnée Cheval modifiée par une simple recherche ou un filtrage');

// =====================================================================================
// AJAX données complètes (§27) : uniquement une fois choisi, capacité edit_post SPÉCIFIQUE
// =====================================================================================

$_POST = array('nonce' => 'valid', 'cheval_id' => '200');
$json = null;
try { gwseq_ajax_partager_get_cheval(); } catch (Gws_Test_Json_Exit $e) { $json = $GLOBALS['__gwseq_test_json_response']; }
gws_test_assert($json['success'] === true && $json['data']['cheval']['id'] === 200, 'AJAX données complètes : renvoie bien gwseq_get_horse_shareable_data() pour le cheval demandé');

// --- Un autre auteur, sans edit_others_posts, ne peut pas récupérer les données d'un cheval qui
// ne lui appartient pas en devinant simplement son identifiant ---
$GLOBALS['__gwseq_test_security']['current_user_id'] = 2;
$GLOBALS['__gwseq_test_security']['edit_others_posts'] = false;
$_POST = array('nonce' => 'valid', 'cheval_id' => '200');
$json = null;
try { gwseq_ajax_partager_get_cheval(); } catch (Gws_Test_Json_Exit $e) { $json = $GLOBALS['__gwseq_test_json_response']; }
gws_test_assert($json['success'] === false, 'AJAX données complètes : un auteur sans edit_others_posts ne peut pas charger un cheval d’un autre auteur (§21)');
gws_test_reset_security();

$_POST = array('nonce' => 'valid', 'cheval_id' => '999999');
$json = null;
try { gwseq_ajax_partager_get_cheval(); } catch (Gws_Test_Json_Exit $e) { $json = $GLOBALS['__gwseq_test_json_response']; }
gws_test_assert($json['success'] === false, 'AJAX données complètes : identifiant inexistant -> erreur, jamais une fiche fabriquée');

gws_test_make_post(300, 'page', 'Une page');
$_POST = array('nonce' => 'valid', 'cheval_id' => '300');
$json = null;
try { gwseq_ajax_partager_get_cheval(); } catch (Gws_Test_Json_Exit $e) { $json = $GLOBALS['__gwseq_test_json_response']; }
gws_test_assert($json['success'] === false, 'AJAX données complètes : un post d’un autre post type (Page) est toujours refusé, même avec un ID valide');
$_POST = array();

// =====================================================================================
// Sanitation de la sélection (§4) — jamais un contenu libre pour les lignes structurées
// =====================================================================================

$selection = gwseq_sanitize_horse_share_selection(array(
  'items' => array('identite', '<script>x</script>', 'origines'),
  'videos' => array('0', '2', 'abc', '-1'),
  'fiche' => '1',
  'message_personnel' => "Bonjour <b>Pierre</b>\nà bientôt",
));
gws_test_assert(in_array('identite', $selection['items'], true) && in_array('origines', $selection['items'], true), 'Sélection : clés d’item valides conservées');
gws_test_assert(count($selection['items']) === 3, 'Sélection : une clé porteuse de HTML est neutralisée par sanitize_key(), jamais rejetée silencieusement ni source d’erreur');
gws_test_assert($selection['videos'] === array(0, 2, -1), 'Sélection : seuls les index numériques sont conservés (castés en entier), une valeur non numérique est ignorée');
gws_test_assert($selection['fiche'] === true, 'Sélection : indicateur fiche complète bien casté en booléen');
gws_test_assert(strpos($selection['message_personnel'], '<b>') === false, 'Sélection : message personnel sanitisé (aucun HTML), jamais un contenu libre non filtré');
gws_test_assert(strpos($selection['message_personnel'], "\n") !== false, 'Sélection : message personnel reste multiligne (texte libre court, pas une seule ligne imposée)');

$selection_empty = gwseq_sanitize_horse_share_selection(array());
gws_test_assert($selection_empty['items'] === array() && $selection_empty['videos'] === array() && $selection_empty['fiche'] === false, 'Sélection : payload vide -> sélection entièrement vide, aucune erreur');

// --- Correctif de recette (§3, vérification explicite de "Ajouter la fiche complète") : un
// booléen JavaScript `false` transite par FormData/$_POST comme la CHAÎNE littérale "false", jamais
// un vrai booléen PHP — une case décochée envoyée ainsi ne doit JAMAIS être interprétée comme vraie
// (un simple `!empty('false')` vaudrait TRUE, chaîne non vide, ce qui aurait laissé le lien de
// fiche apparaître malgré la case décochée). ---
$selection_fiche_false_string = gwseq_sanitize_horse_share_selection(array('fiche' => 'false'));
gws_test_assert($selection_fiche_false_string['fiche'] === false, 'Sélection : la chaîne littérale "false" (valeur réelle transmise par un booléen JS décoché via FormData) est bien interprétée comme FAUX, jamais comme une chaîne non vide truthy');

$selection_fiche_zero_string = gwseq_sanitize_horse_share_selection(array('fiche' => '0'));
gws_test_assert($selection_fiche_zero_string['fiche'] === false, 'Sélection : la chaîne "0" est également interprétée comme faux');

$selection_fiche_true_string = gwseq_sanitize_horse_share_selection(array('fiche' => 'true'));
gws_test_assert($selection_fiche_true_string['fiche'] === true, 'Sélection : la chaîne littérale "true" (valeur réelle transmise par un booléen JS coché via FormData) est bien interprétée comme VRAI');

// =====================================================================================
// AJAX composition du message (§14) : mêmes données que gwseq_get_horse_shareable_data(),
// jamais un contenu fourni par le client pour les lignes structurées
// =====================================================================================

gws_test_make_horse(400, 'Cheval De Test', 1, array('identity' => array('_gwseq_sexe' => 'male', '_gwseq_annee_naissance' => (int) gmdate('Y') - 4)));
$_POST = array(
  'nonce' => 'valid',
  'cheval_id' => '400',
  'selection' => array('items' => array('identite'), 'videos' => array(), 'fiche' => '', 'message_personnel' => 'Bonjour !'),
);
$json = null;
try { gwseq_ajax_partager_build_message(); } catch (Gws_Test_Json_Exit $e) { $json = $GLOBALS['__gwseq_test_json_response']; }
gws_test_assert($json['success'] === true, 'AJAX composition : réponse de succès');
gws_test_assert(strpos($json['data']['message'], 'Bonjour !') === 0, 'AJAX composition : message personnel repris en tête');
gws_test_assert(strpos($json['data']['message'], 'Étalon') !== false, 'AJAX composition : ligne structurée composée SERVEUR (vocabulaire commercial), jamais fournie par le client');

// --- Le client ne peut jamais injecter une ligne fabriquée à la place d'un item structuré ---
$_POST = array(
  'nonce' => 'valid',
  'cheval_id' => '400',
  'selection' => array('items' => array('identite'), 'videos' => array(), 'fiche' => '', 'message_personnel' => ''),
);
// On altère volontairement les données réelles du cheval après coup : la composition doit relire
// la base à cet instant, jamais une valeur qui aurait pu être soumise par le client pour "identite".
gwseq_set_cheval_identity(400, array('_gwseq_sexe' => 'female', '_gwseq_annee_naissance' => (int) gmdate('Y') - 4));
$json = null;
try { gwseq_ajax_partager_build_message(); } catch (Gws_Test_Json_Exit $e) { $json = $GLOBALS['__gwseq_test_json_response']; }
gws_test_assert(strpos($json['data']['message'], 'Jument') !== false, 'AJAX composition : les lignes structurées reflètent TOUJOURS l’état réel en base au moment de la requête, jamais un contenu soumis par le client');
$_POST = array();

$GLOBALS['__gwseq_test_security']['current_user_id'] = 2;
$GLOBALS['__gwseq_test_security']['edit_others_posts'] = false;
$_POST = array('nonce' => 'valid', 'cheval_id' => '400', 'selection' => array());
$json = null;
try { gwseq_ajax_partager_build_message(); } catch (Gws_Test_Json_Exit $e) { $json = $GLOBALS['__gwseq_test_json_response']; }
gws_test_assert($json['success'] === false, 'AJAX composition : même restriction de permission qu’au chargement des données complètes');
gws_test_reset_security();
$_POST = array();

// =====================================================================================
// Suite V1 « Partager & vendre » — Lot 1 : glue WordPress du partage privé (§2.B/§16).
// La logique de token elle-même (génération/activation/révocation/URL/recherche inverse) est
// testée dans gws-equestrian-cheval-share-logic-test.php ; ce fichier-ci teste UNIQUEMENT ce qui
// est propre à la glue (permissions, exclusion recherche/sitemap/REST, rendu de la boîte latérale)
// — jamais la même logique reconstruite ici en double.
// =====================================================================================

// --- Prédicat de permission (§16 : "utilisateur sans permission ne peut pas générer un lien pour
// un cheval qu'il ne peut pas éditer") ---
gws_test_make_horse(500, 'Cheval Partage Prive Test', 1);
gws_test_assert(gwseq_horse_private_share_user_can_manage(500) === true, 'Permission partage privé : le propriétaire de la fiche peut gérer son partage privé');
gws_test_assert(gwseq_horse_private_share_user_can_manage(999999) === false, 'Permission partage privé : un identifiant inexistant est toujours refusé');
gws_test_assert(gwseq_horse_private_share_user_can_manage(0) === false, 'Permission partage privé : un identifiant à zéro est toujours refusé, jamais interprété comme "tous les chevaux"');

gws_test_make_post(501, 'page', 'Une page');
gws_test_assert(gwseq_horse_private_share_user_can_manage(501) === false, 'Permission partage privé : un post d’un autre type (Page) est toujours refusé, même avec un ID valide');

$GLOBALS['__gwseq_test_security']['edit_others_posts'] = false;
$GLOBALS['__gwseq_test_security']['current_user_id'] = 2;
gws_test_assert(gwseq_horse_private_share_user_can_manage(500) === false, 'Permission partage privé : un utilisateur sans edit_others_posts ne peut pas gérer le partage privé du cheval d’un autre auteur (§16)');
gws_test_reset_security();

// --- Exclusion recherche/sitemap/REST : une seule clause réutilisée aux QUATRE endroits (§16) ---
$exclusion_clause = gwseq_horse_private_share_exclusion_meta_clause();
gws_test_assert($exclusion_clause['relation'] === 'OR', 'Exclusion partage privé : clause meta_query à deux branches (méta absente OU vide) — couvre aussi bien un cheval qui n’a jamais eu de partage privé qu’un cheval révoqué');

$rest_args = gwseq_horse_private_share_filter_rest_query(array(), null);
gws_test_assert(isset($rest_args['meta_query']) && in_array($exclusion_clause, $rest_args['meta_query'], true), 'REST : la clause d’exclusion est bien ajoutée aux arguments de requête de LISTE (jamais une reconstruction séparée de la clause)');
$rest_args_avec_meta_existante = gwseq_horse_private_share_filter_rest_query(array('meta_query' => array(array('key' => 'autre_chose', 'value' => 'x'))), null);
gws_test_assert(count($rest_args_avec_meta_existante['meta_query']) === 2, 'REST : la clause d’exclusion s’AJOUTE à un meta_query déjà présent, ne l’écrase jamais');

// --- Correctif de recette : `/wp/v2/search` (contrôleur transversal, y compris avec
// `subtype=gwseq_cheval`) est un point d'entrée REST SÉPARÉ du contrôleur CRUD ci-dessus — jamais
// couvert par rest_gwseq_cheval_query/rest_prepare_gwseq_cheval. Il force en dur post_status =>
// 'publish', ce qui n'exclut PAS un cheval en partage privé actif resté publié (cas volontairement
// permis par ce module) : la même clause d'exclusion doit donc être injectée sur son propre filtre
// (`wp_rest_search_query`), sans dupliquer sa définition. ---
$search_args = gwseq_horse_private_share_filter_rest_search_query(array('post_type' => array('post', 'page', GWSEQ_CPT_CHEVAL)), null);
gws_test_assert(isset($search_args['meta_query']) && in_array($exclusion_clause, $search_args['meta_query'], true), 'REST /wp/v2/search : la MÊME clause d’exclusion (jamais une reconstruction séparée) est ajoutée à la requête transversale, y compris quand elle porte sur plusieurs post types à la fois');
$search_args_avec_meta_existante = gwseq_horse_private_share_filter_rest_search_query(array('meta_query' => array(array('key' => 'autre_chose', 'value' => 'x'))), null);
gws_test_assert(count($search_args_avec_meta_existante['meta_query']) === 2, 'REST /wp/v2/search : la clause d’exclusion s’AJOUTE à un meta_query déjà présent, ne l’écrase jamais');

$sitemap_args_cheval = gwseq_horse_private_share_filter_sitemap_args(array(), GWSEQ_CPT_CHEVAL);
gws_test_assert(isset($sitemap_args_cheval['meta_query']), 'Sitemap : la clause d’exclusion est appliquée pour le post_type Cheval');
$sitemap_args_autre = gwseq_horse_private_share_filter_sitemap_args(array('foo' => 'bar'), 'post');
gws_test_assert($sitemap_args_autre === array('foo' => 'bar'), 'Sitemap : aucune modification pour un autre post_type que Cheval (jamais un filtre trop large)');

update_post_meta(500, '_gwseq_partage_prive_token', str_repeat('a', 64));
$GLOBALS['__gwseq_test_security']['edit_others_posts'] = false;
$GLOBALS['__gwseq_test_security']['current_user_id'] = 2; // pas l'auteur (1) de la fiche 500
$rest_response_bloquee = gwseq_horse_private_share_filter_rest_response('reponse-normale', get_post(500), null);
gws_test_assert($rest_response_bloquee instanceof WP_Error && $rest_response_bloquee->data['status'] === 404, 'REST : un accès DIRECT par identifiant à un cheval en partage privé actif est bloqué (404), pour un visiteur qui ne peut pas éditer la fiche');
gws_test_reset_security();
$rest_response_editeur = gwseq_horse_private_share_filter_rest_response('reponse-normale', get_post(500), null);
gws_test_assert($rest_response_editeur === 'reponse-normale', 'REST : l’éditeur de la fiche continue, lui, d’obtenir la réponse normale (jamais bloqué sur sa propre fiche)');
delete_post_meta(500, '_gwseq_partage_prive_token');
gws_test_reset_security();

// --- Filtre pre_get_posts : n'agit QUE sur les requêtes touchant réellement Cheval (recherche/
// archive/taxonomie), jamais sur une requête sans rapport, et jamais sur notre propre route privée ---
class Gws_Test_Fake_Query {
  public $flags; public $vars = array();
  public function __construct($flags) { $this->flags = $flags; }
  public function is_main_query() { return $this->flags['main'] ?? true; }
  public function is_search() { return $this->flags['search'] ?? false; }
  public function is_post_type_archive($pt) { return ($this->flags['archive'] ?? '') === $pt; }
  public function is_tax($tax) { return ($this->flags['tax'] ?? '') === $tax; }
  public function get($key) { return $this->vars[$key] ?? ($key === 'meta_query' ? array() : ''); }
  public function set($key, $value) { $this->vars[$key] = $value; }
}

$fake_query_recherche = new Gws_Test_Fake_Query(array('search' => true));
gwseq_horse_private_share_filter_public_query($fake_query_recherche);
gws_test_assert(!empty($fake_query_recherche->vars['meta_query']), 'pre_get_posts : une recherche publique (is_search) reçoit bien la clause d’exclusion');

$fake_query_archive_cheval = new Gws_Test_Fake_Query(array('archive' => GWSEQ_CPT_CHEVAL));
gwseq_horse_private_share_filter_public_query($fake_query_archive_cheval);
gws_test_assert(!empty($fake_query_archive_cheval->vars['meta_query']), 'pre_get_posts : l’archive Cheval reçoit bien la clause d’exclusion');

$fake_query_taxonomie = new Gws_Test_Fake_Query(array('tax' => GWSEQ_TAX_CATEGORIE_CHEVAL));
gwseq_horse_private_share_filter_public_query($fake_query_taxonomie);
gws_test_assert(!empty($fake_query_taxonomie->vars['meta_query']), 'pre_get_posts : une page de taxonomie Catégorie de cheval reçoit bien la clause d’exclusion');

$fake_query_sans_rapport = new Gws_Test_Fake_Query(array('search' => false));
gwseq_horse_private_share_filter_public_query($fake_query_sans_rapport);
gws_test_assert(!isset($fake_query_sans_rapport->vars['meta_query']), 'pre_get_posts : une requête sans rapport (page normale, autre CPT) n’est jamais touchée — aucune jointure meta inutile');

$fake_query_sous_query = new Gws_Test_Fake_Query(array('search' => true, 'main' => false));
gwseq_horse_private_share_filter_public_query($fake_query_sous_query);
gws_test_assert(!isset($fake_query_sous_query->vars['meta_query']), 'pre_get_posts : une sous-requête (is_main_query false) n’est jamais touchée, même si elle ressemble à une recherche');

// --- Boîte latérale "Partage" : contrôles de partage privé rendus dans LA MÊME boîte (§ "jamais
// une seconde interface"), état "inactif" puis état "actif" ---
gws_test_make_horse(510, 'Cheval Boite Partage Prive', 1);
ob_start();
call_user_func($meta_box['callback'], get_post(510));
$meta_box_html_inactif = ob_get_clean();
gws_test_assert(strpos($meta_box_html_inactif, 'Créer un lien de partage privé') !== false, 'Boîte latérale : bouton de création affiché tant qu’aucun partage privé n’est actif');
gws_test_assert(strpos($meta_box_html_inactif, 'Révoquer') === false, 'Boîte latérale : aucune action de révocation proposée tant qu’il n’y a rien à révoquer');

gwseq_horse_private_share_activate(510);
ob_start();
call_user_func($meta_box['callback'], get_post(510));
$meta_box_html_actif = ob_get_clean();
gws_test_assert(strpos($meta_box_html_actif, gwseq_horse_private_share_url(510)) !== false, 'Boîte latérale : l’URL de partage privé actuelle est affichée une fois active');
gws_test_assert(strpos($meta_box_html_actif, 'Révoquer') !== false && strpos($meta_box_html_actif, 'Régénérer') !== false, 'Boîte latérale : actions Révoquer/Régénérer proposées une fois le partage privé actif');
gws_test_assert(strpos($meta_box_html_actif, 'Créer un lien de partage privé') === false, 'Boîte latérale : le bouton de création initiale disparaît une fois un partage déjà actif (jamais les deux affichés en même temps)');

// --- Un utilisateur qui ne peut pas éditer la fiche ne voit AUCUN contrôle de partage privé ---
$GLOBALS['__gwseq_test_security']['edit_others_posts'] = false;
$GLOBALS['__gwseq_test_security']['current_user_id'] = 2;
ob_start();
call_user_func($meta_box['callback'], get_post(510));
$meta_box_html_non_autorise = ob_get_clean();
gws_test_assert(strpos($meta_box_html_non_autorise, 'Partage privé') === false, 'Boîte latérale : aucun contrôle de partage privé affiché à un utilisateur qui ne peut pas éditer cette fiche');
gws_test_reset_security();
gwseq_horse_private_share_revoke(510);

// =====================================================================================
// Bug runtime bloquant (premier test réel du Lot 1) — CAUSE RACINE : les actions de partage privé
// étaient rendues comme des <form> imbriqués dans le grand formulaire d'édition WordPress
// (<form id="post">...), ce qui est invalide en HTML — le clic soumettait en réalité le formulaire
// EXTÉRIEUR (vers post.php), jamais notre gestionnaire admin-post.php, d'où la redirection
// constatée vers la liste "Actualités" au lieu de revenir sur la fiche. Corrigé en remplaçant les
// <form> par de simples liens <a> nonce-protégés (même schéma que les actions de ligne natives de
// WordPress). Ces tests couvrent EXPLICITEMENT la construction de l'URL de retour finale — les
// tests précédents ne le faisaient pas, ce qui n'avait donc pas permis de reproduire ce bug.
// =====================================================================================

// --- Régression : plus JAMAIS de <form> dans cette boîte, dans AUCUN état (garde-fou explicite
// contre la cause racine ci-dessus, qui ne se voit qu'en HTML réellement rendu dans un navigateur —
// jamais dans un test qui se contente de vérifier la présence d'un texte de bouton) ---
gws_test_make_horse(511, 'Cheval Sans Form Imbrique', 1);
ob_start();
call_user_func($meta_box['callback'], get_post(511));
$meta_box_html_sans_partage = ob_get_clean();
gws_test_assert(stripos($meta_box_html_sans_partage, '<form') === false, 'Boîte latérale : aucun <form> imbriqué (état sans partage privé) — cause racine du bug de redirection vers Actualités');

gwseq_horse_private_share_activate(511);
ob_start();
call_user_func($meta_box['callback'], get_post(511));
$meta_box_html_avec_partage = ob_get_clean();
gws_test_assert(stripos($meta_box_html_avec_partage, '<form') === false, 'Boîte latérale : aucun <form> imbriqué non plus une fois le partage privé actif (Régénérer/Révoquer)');
gws_test_assert(substr_count($meta_box_html_avec_partage, '<a class="button"') + substr_count($meta_box_html_avec_partage, 'class="button') >= 2, 'Boîte latérale : Régénérer et Révoquer sont bien rendus comme des liens (<a>), jamais comme des boutons de formulaire');
gwseq_horse_private_share_revoke(511);

// --- gwseq_horse_private_share_action_url() : construction de l'URL cliquée, testable isolément ---
$url_activer = gwseq_horse_private_share_action_url('activer', 510);
gws_test_assert(strpos($url_activer, 'admin-post.php') !== false, 'URL action partage privé : cible bien admin-post.php');
gws_test_assert(strpos($url_activer, 'action=gwseq_partage_prive_activer') !== false, 'URL action partage privé : action="activer" correcte (Créer ET Régénérer réutilisent la même)');
gws_test_assert(strpos($url_activer, 'cheval_id=510') !== false, 'URL action partage privé : identifiant du cheval correctement transmis');
gws_test_assert(strpos($url_activer, '_wpnonce=') !== false, 'URL action partage privé : nonce présent dans l’URL (GET protégé, comme les actions de ligne natives de WordPress)');
gws_test_assert(strpos($url_activer, 'nonce-gwseq_partage_prive_510') !== false, 'URL action partage privé : le nonce est bien généré pour L’ACTION PRÉCISE "gwseq_partage_prive_510" — la même chaîne que celle vérifiée par check_admin_referer() dans gwseq_horse_private_share_handle_admin_post(), jamais une action générique');

$url_revoquer = gwseq_horse_private_share_action_url('revoquer', 510);
gws_test_assert(strpos($url_revoquer, 'action=gwseq_partage_prive_revoquer') !== false, 'URL action partage privé : action="revoquer" correcte, distincte de "activer"');

$url_autre_cheval = gwseq_horse_private_share_action_url('activer', 42);
gws_test_assert(strpos($url_autre_cheval, 'cheval_id=42') !== false && strpos($url_autre_cheval, 'nonce-gwseq_partage_prive_42') !== false, 'URL action partage privé : nonce et identifiant varient bien avec le cheval concerné (jamais un nonce générique réutilisable sur un autre cheval)');

// --- gwseq_horse_private_share_redirect_url_after_action() : URL DE RETOUR après activer/
// régénérer/révoquer — exactement le point qui manquait de couverture et qui a laissé passer le
// bug (§ "ajouter un test couvrant explicitement l'URL de redirection finale") ---
gws_test_make_horse(512, 'Cheval Redirection Retour', 1);
$redirect_url = gwseq_horse_private_share_redirect_url_after_action(512);
gws_test_assert($redirect_url === get_edit_post_link(512, 'raw'), 'Redirection après action : ramène bien vers l’écran d’édition DU MÊME cheval (post.php?post=512&action=edit), jamais vers une autre liste');
gws_test_assert(strpos($redirect_url, 'post=512') !== false, 'Redirection après action : l’identifiant du cheval concerné est bien celui de la redirection, jamais un autre');
gws_test_assert(strpos($redirect_url, '/wp-admin/') === 0 || strpos($redirect_url, 'https://example.test/wp-admin/') === 0, 'Redirection après action : URL interne à wp-admin (jamais une URL externe — pas d’open redirect possible, get_edit_post_link()/admin_url() ne dépendent jamais d’une entrée utilisateur)');

// --- Repli explicite si get_edit_post_link() ne peut pas produire d’URL (ex. capacité réévaluée
// différemment entre-temps) : jamais une URL vide, jamais un repli WordPress générique vers le
// Tableau de bord (qui a précisément produit le symptôme observé : atterrissage sur Actualités) ---
$GLOBALS['__gwseq_test_security']['edit_others_posts'] = false;
$GLOBALS['__gwseq_test_security']['current_user_id'] = 2; // pas l'auteur (1) de la fiche 512
gws_test_assert(get_edit_post_link(512, 'raw') === '', 'Pré-requis du test de repli : get_edit_post_link() renvoie bien vide quand l’utilisateur ne peut pas éditer cette fiche (comportement réel de WordPress)');
$redirect_url_repli = gwseq_horse_private_share_redirect_url_after_action(512);
gws_test_assert($redirect_url_repli !== '', 'Redirection après action : jamais une URL vide transmise à wp_safe_redirect(), même si get_edit_post_link() échoue');
gws_test_assert(strpos($redirect_url_repli, 'edit.php?post_type=' . GWSEQ_CPT_CHEVAL) !== false, 'Redirection après action : repli explicite vers la liste des Chevaux (jamais le Tableau de bord générique, jamais Actualités)');
gws_test_reset_security();

// --- Correctif de recette : la route de partage privé ne doit JAMAIS être mise en cache (page
// cache, reverse proxy, CDN) — sans quoi une révocation/régénération ne serait pas immédiatement
// effective pour un visiteur servi depuis un cache intermédiaire. Testé en DEUX temps : les
// DIRECTIVES elles-mêmes (données pures, sans dépendre de l'état réel des en-têtes HTTP du
// processus PHP), puis que l'envoi réel déclenche bien nocache_headers() + DONOTCACHEPAGE. ---
$nocache_values = gwseq_horse_private_share_nocache_header_values();
$cache_control = current(array_filter($nocache_values, function ($h) { return $h[0] === 'Cache-Control'; }));
gws_test_assert($cache_control !== false && strpos($cache_control[1], 'no-store') !== false, 'Cache privé : l’en-tête Cache-Control envoyé par la route de partage privé contient "no-store" — la seule directive comprise SANS AMBIGUÏTÉ par tout reverse proxy/CDN comme "ne jamais mettre en cache"');
$pragma = current(array_filter($nocache_values, function ($h) { return $h[0] === 'Pragma'; }));
gws_test_assert($pragma !== false && $pragma[1] === 'no-cache', 'Cache privé : en-tête Pragma: no-cache également envoyé (compatibilité intermédiaires HTTP/1.0)');

$GLOBALS['__gwseq_test_nocache_headers_called'] = 0;
gwseq_horse_private_share_send_nocache_headers();
gws_test_assert($GLOBALS['__gwseq_test_nocache_headers_called'] === 1, 'Cache privé : nocache_headers() native de WordPress est bien appelée en plus de nos propres directives (défense en profondeur)');
gws_test_assert(defined('DONOTCACHEPAGE') && DONOTCACHEPAGE === true, 'Cache privé : la constante DONOTCACHEPAGE est définie — convention reconnue par la plupart des plugins de cache plein-page WordPress (WP Super Cache, W3 Total Cache, WP Rocket...)');

// Appeler une seconde fois ne doit jamais tenter de redéfinir la constante (PHP lèverait une
// erreur sur une redéfinition de constante) — vérifie que la garde `!defined(...)` fonctionne.
$redefinition_erreur = false;
try { gwseq_horse_private_share_send_nocache_headers(); } catch (\Throwable $e) { $redefinition_erreur = true; }
gws_test_assert($redefinition_erreur === false, 'Cache privé : un appel répété (ex. plusieurs requêtes dans le même process) ne tente jamais de redéfinir DONOTCACHEPAGE');

// =====================================================================================
// Assets — uniquement sur l'écran Partager (§7)
// =====================================================================================

$GLOBALS['__gwseq_test_screen'] = (object) array('id' => 'chevaux_page_gwseq-partager');
gwseq_enqueue_horse_share_admin_assets('edit.php');
gws_test_assert(in_array('gwseq-cheval-share-admin', $GLOBALS['__gwseq_enqueued'], true), 'Assets : chargés sur l’écran Partager');

$GLOBALS['__gwseq_enqueued'] = array();
$GLOBALS['__gwseq_test_screen'] = (object) array('id' => 'edit-gwseq_cheval');
gwseq_enqueue_horse_share_admin_assets('edit.php');
gws_test_assert(!in_array('gwseq-cheval-share-admin', $GLOBALS['__gwseq_enqueued'], true), 'Assets : jamais chargés sur un autre écran (ex. la liste native des chevaux)');

echo ($failures === 0 ? 'Tous les tests sont passés.' : "$failures test(s) en échec.") . "\n";
exit($failures === 0 ? 0 : 1);

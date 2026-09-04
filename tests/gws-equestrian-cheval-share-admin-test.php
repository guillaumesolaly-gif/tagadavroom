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
// Utilisé par gwseq_horse_apply_diffusion_transition_on_save() (garde de réentrance, motif standard
// WordPress) : ce stub retire réellement l'entrée trackée, pour que la re-registration via
// add_action() juste après reste observable comme un aller-retour cohérent, sans jamais fausser les
// autres assertions basées sur $GLOBALS['__gwseq_test_actions'] (portée strictement limitée au hook
// précis passé en argument).
function remove_action($hook, $callback, $priority = 10) {
  $GLOBALS['__gwseq_test_actions'][$hook] = array_values(array_diff($GLOBALS['__gwseq_test_actions'][$hook] ?? array(), array($callback)));
}
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
// Fidèle au strict nécessaire des transitions de diffusion (gwseq_horse_diffusion_set_*(),
// cheval-share.php) : seuls post_status/post_password sont jamais écrits par ces fonctions.
function wp_update_post($postarr, $wp_error = false) {
  $id = (int) ($postarr['ID'] ?? 0);
  if (!$id || !isset($GLOBALS['__gwseq_test_posts'][$id])) return 0;
  foreach (array('post_status', 'post_password') as $field) {
    if (array_key_exists($field, $postarr)) {
      $GLOBALS['__gwseq_test_posts'][$id][$field] = $postarr[$field];
    }
  }
  return $id;
}
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
$GLOBALS['__gwseq_test_security'] = array('nonce_valid' => true, 'edit_posts' => true, 'edit_others_posts' => true, 'publish_posts' => true, 'current_user_id' => 1, 'is_revision' => false);
function check_ajax_referer($action, $arg_name = false, $die = true) {
  if (!$GLOBALS['__gwseq_test_security']['nonce_valid']) throw new Gws_Test_Wp_Die_Exception('nonce invalide');
  return true;
}
// Même convention que le reste de la suite (ex. gws-equestrian-cheval-logic-test.php) : piloté par
// le test, jamais un état WordPress réel simulé lourdement.
function wp_is_post_revision($post_id) { return $GLOBALS['__gwseq_test_security']['is_revision']; }
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
  // `publish_post` (méta-capacité) — fidèle au comportement réel de map_meta_cap() : contrairement à
  // `edit_post`, il n'existe AUCUNE exception "ses propres fiches" pour publier — elle se résout
  // TOUJOURS vers la seule capacité primitive `publish_posts`, quel que soit l'auteur de la fiche.
  if ($cap === 'publish_post') {
    $post = get_post($post_id);
    if (!$post) return false;
    return $security['publish_posts'];
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
    $post__in = $args['post__in'] ?? null;

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
      if (is_array($post__in) && !in_array($id, $post__in, true)) continue;
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
$GLOBALS['__gwseq_test_removed_meta_boxes'] = array();
function remove_meta_box($id, $post_type, $context) { $GLOBALS['__gwseq_test_removed_meta_boxes'][] = compact('id', 'post_type', 'context'); }

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
  $GLOBALS['__gwseq_test_security'] = array('nonce_valid' => true, 'edit_posts' => true, 'edit_others_posts' => true, 'publish_posts' => true, 'current_user_id' => 1, 'is_revision' => false);
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

// --- Filtre "État de diffusion" (audit UX/métier suivant, §"réutiliser la logique de recherche
// déjà existante") — mêmes trois valeurs que gwseq_horse_diffusion_states(), jamais un second
// vocabulaire ---
gws_test_assert(
  gwseq_sanitize_horse_share_filters(array('diffusion' => 'diffusion_privee'))['diffusion'] === 'diffusion_privee',
  'Filtres : état de diffusion valide conservé'
);
gws_test_assert(
  gwseq_sanitize_horse_share_filters(array('diffusion' => 'etat-invente'))['diffusion'] === '',
  'Filtres : un état de diffusion hors des trois valeurs connues est ignoré, jamais propagé à la requête'
);
gws_test_assert(gwseq_sanitize_horse_share_filters(array())['diffusion'] === '', 'Filtres : état de diffusion absent -> chaîne vide ("Tous"), jamais une erreur');

// État de diffusion dérivé (statut + token) : jamais exprimable par un meta_query/tax_query direct
// -> restreint la requête via post__in, à partir de gwseq_cheval_ids_by_diffusion_state() (seule
// source de vérité, includes/cheval-share.php).
gws_test_make_horse(216, 'Cheval Filtre Diffusion Prive', 1, array('post_status' => 'draft'));
gwseq_horse_private_share_activate(216);
gws_test_make_horse(217, 'Cheval Filtre Diffusion Publiee'); // publish par défaut
$args_diffusion = gwseq_horse_share_filters_to_query_args(array('sexe' => '', 'statut' => '', 'annee_min' => 0, 'annee_max' => 0, 'categorie' => '', 'diffusion' => 'diffusion_privee'));
gws_test_assert(isset($args_diffusion['post__in']) && in_array(216, $args_diffusion['post__in'], true) && !in_array(217, $args_diffusion['post__in'], true), 'Filtres : "État de diffusion" transformé en restriction post__in (état dérivé, jamais exprimable par un meta_query direct) — ne retient que le cheval réellement dans l’état demandé');
$args_sans_diffusion = gwseq_horse_share_filters_to_query_args(array('sexe' => '', 'statut' => '', 'annee_min' => 0, 'annee_max' => 0, 'categorie' => '', 'diffusion' => ''));
gws_test_assert(!isset($args_sans_diffusion['post__in']), 'Filtres : "Tous" (valeur vide) n’ajoute aucune restriction post__in');

// --- Cumul réel via l'AJAX de recherche : État de diffusion + Sexe, jamais l’un n’écrase l’autre ---
gws_test_make_horse(222, 'Jument En Preparation', 1, array('post_status' => 'draft', 'identity' => array('_gwseq_sexe' => 'female')));
gws_test_make_horse(223, 'Etalon En Preparation', 1, array('post_status' => 'draft', 'identity' => array('_gwseq_sexe' => 'male')));
gws_test_make_horse(224, 'Jument Diffusion Privee', 1, array('post_status' => 'draft', 'identity' => array('_gwseq_sexe' => 'female')));
gwseq_horse_private_share_activate(224);

$_POST = array('nonce' => 'valid', 's' => '', 'filters' => array('diffusion' => 'en_preparation', 'sexe' => 'female'));
$json = null;
try { gwseq_ajax_partager_search_cheval(); } catch (Gws_Test_Json_Exit $e) { $json = $GLOBALS['__gwseq_test_json_response']; }
$ids_diffusion_filtres = array_column($json['data']['resultats'], 'id');
gws_test_assert($ids_diffusion_filtres === array(222), 'Filtre "État de diffusion" (cumulé avec Sexe) : seule la jument réellement "En préparation" est retournée — jamais l’étalon (sexe différent) ni la jument en "Diffusion privée" (état différent)');
$_POST = array();

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

// --- Ajustement d'architecture (recette) : les filtres d'exclusion recherche/sitemap/REST/
// pre_get_posts ont été RETIRÉS — ils excluaient tout cheval PORTANT UN TOKEN, indépendamment de
// son statut réel, ce que la nouvelle règle interdit explicitement ("un token ne doit jamais
// dégrader ni masquer une fiche publique valide"). Un cheval en mode "partage privé exclusif" est
// par construction non publié : WordPress l'exclut déjà nativement de ces quatre surfaces, sans
// code supplémentaire (voir includes/cheval-share-admin.php pour le détail). Ces fonctions
// n'existent donc plus, remplacé ci-dessous par les tests de la boîte latérale adaptés aux QUATRE
// états désormais possibles (public/non public × avec/sans token).

// --- Boîte latérale "Partage" : contrôles de partage privé rendus dans LA MÊME boîte (§ "jamais
// une seconde interface"), adaptés à la visibilité RÉELLE du cheval, JAMAIS au seul fait qu'un
// token existe (§6 de l'ajustement d'architecture — quatre états explicites) ---

// --- État 1 : cheval PUBLIC, SANS token -> indique que le partage utilise la fiche publique,
// jamais de bouton "Créer" ni de mention d'un lien privé ---
gws_test_make_horse(510, 'Cheval Boite Partage Public', 1); // publish par défaut
ob_start();
call_user_func($meta_box['callback'], get_post(510));
$meta_box_html_public_sans_token = ob_get_clean();
gws_test_assert(strpos($meta_box_html_public_sans_token, 'la fiche publique du site') !== false, 'Boîte latérale, cheval public sans token : indique clairement que le partage utilise la fiche publique');
gws_test_assert(strpos($meta_box_html_public_sans_token, 'Créer un lien de partage privé') === false, 'Boîte latérale, cheval public sans token : jamais de bouton "Créer" proposé (le lien privé n’est pas le mode principal)');
gws_test_assert(strpos($meta_box_html_public_sans_token, 'Révoquer') === false, 'Boîte latérale, cheval public sans token : rien à révoquer, aucune action de révocation affichée');

// --- État 2 : cheval PUBLIC, AVEC un ancien token -> toujours le message "fiche publique", PLUS
// une mention discrète de l'ancien lien encore valide, avec la seule action "Révoquer" (jamais mis
// en avant comme mode principal, jamais de "Créer"/"Régénérer") ---
gwseq_horse_private_share_activate(510);
ob_start();
call_user_func($meta_box['callback'], get_post(510));
$meta_box_html_public_avec_token = ob_get_clean();
gws_test_assert(strpos($meta_box_html_public_avec_token, 'la fiche publique du site') !== false, 'Boîte latérale, cheval public AVEC token : le message "fiche publique" reste affiché en premier (pas remplacé par le token)');
gws_test_assert(strpos($meta_box_html_public_avec_token, gwseq_horse_private_share_url(510)) !== false, 'Boîte latérale, cheval public AVEC token : l’ancien lien reste affiché (signalé), pas masqué');
gws_test_assert(strpos($meta_box_html_public_avec_token, 'Révoquer') !== false, 'Boîte latérale, cheval public AVEC token : l’action Révoquer reste accessible');
gws_test_assert(strpos($meta_box_html_public_avec_token, 'Créer un lien de partage privé') === false && strpos($meta_box_html_public_avec_token, 'Régénérer') === false, 'Boîte latérale, cheval public AVEC token : jamais "Créer"/"Régénérer" — le lien privé n’est PAS présenté comme le mode principal pour un cheval déjà public');
gwseq_horse_private_share_revoke(510);

// --- État 3 : cheval NON PUBLIC, SANS token -> plus de bouton "Créer" dans CETTE boîte (ajustement
// suivant — centralisation des transitions) : renvoie vers la boîte "État de diffusion" ---
gws_test_make_horse(513, 'Cheval Boite Partage Non Public', 1, array('post_status' => 'draft'));
ob_start();
call_user_func($meta_box['callback'], get_post(513));
$meta_box_html_non_public_sans_token = ob_get_clean();
gws_test_assert(strpos($meta_box_html_non_public_sans_token, 'Créer un lien de partage privé') === false, 'Boîte latérale, cheval non public sans token : le bouton "Créer" a été retiré de CETTE boîte (centralisé dans "État de diffusion")');
gws_test_assert(strpos($meta_box_html_non_public_sans_token, 'État de diffusion') !== false, 'Boîte latérale, cheval non public sans token : renvoie explicitement vers la boîte "État de diffusion" pour activer le partage');
gws_test_assert(strpos($meta_box_html_non_public_sans_token, 'Révoquer') === false, 'Boîte latérale, cheval non public sans token : aucune action de révocation proposée tant qu’il n’y a rien à révoquer');
gws_test_assert(strpos($meta_box_html_non_public_sans_token, 'la fiche publique du site') === false, 'Boîte latérale, cheval non public sans token : jamais le message "fiche publique", puisqu’il n’y en a pas');
gws_test_assert(strpos($meta_box_html_non_public_sans_token, 'name="' . GWSEQ_HORSE_PRIVATE_SHARE_SUBMIT_FIELD . '"') === false, 'Boîte latérale, cheval non public sans token : plus aucun champ de soumission dans cette boîte tant qu’aucun partage n’est déjà actif');

// --- État 4 : cheval NON PUBLIC, AVEC token -> affiche l'URL privée + Régénérer/Révoquer (INCHANGÉ,
// § "le lien privé et ses actions de gestion peuvent rester dans la boîte Partage") ---
gwseq_horse_private_share_activate(513);
ob_start();
call_user_func($meta_box['callback'], get_post(513));
$meta_box_html_non_public_avec_token = ob_get_clean();
gws_test_assert(strpos($meta_box_html_non_public_avec_token, gwseq_horse_private_share_url(513)) !== false, 'Boîte latérale, cheval non public AVEC token : l’URL de partage privé actuelle est affichée');
gws_test_assert(strpos($meta_box_html_non_public_avec_token, 'Révoquer') !== false && strpos($meta_box_html_non_public_avec_token, 'Régénérer') !== false, 'Boîte latérale, cheval non public AVEC token : actions Régénérer/Révoquer proposées');
gws_test_assert(strpos($meta_box_html_non_public_avec_token, 'Créer un lien de partage privé') === false, 'Boîte latérale, cheval non public AVEC token : le bouton de création initiale n’a jamais existé ici');
gws_test_assert(strpos($meta_box_html_non_public_avec_token, '<button type="submit"') !== false && strpos($meta_box_html_non_public_avec_token, 'name="' . GWSEQ_HORSE_PRIVATE_SHARE_SUBMIT_FIELD . '"') !== false, 'Boîte latérale, cheval non public AVEC token : "Régénérer" reste un vrai bouton de soumission (correctif Lot 1, inchangé)');
gws_test_assert(strpos($meta_box_html_non_public_avec_token, 'action=gwseq_partage_prive_activer') === false, 'Boîte latérale, cheval non public AVEC token : "Régénérer" ne pointe plus vers admin-post.php');
gws_test_assert(strpos($meta_box_html_non_public_avec_token, 'action=gwseq_partage_prive_revoquer') !== false, 'Boîte latérale, cheval non public AVEC token : "Révoquer" reste un lien admin-post.php (aucun risque de fausse impression de fraîcheur pour cette action)');
gwseq_horse_private_share_revoke(513);

// --- Un utilisateur qui ne peut pas éditer la fiche ne voit AUCUN contrôle de partage privé,
// quel que soit l'état de visibilité ---
$GLOBALS['__gwseq_test_security']['edit_others_posts'] = false;
$GLOBALS['__gwseq_test_security']['current_user_id'] = 2;
ob_start();
call_user_func($meta_box['callback'], get_post(510));
$meta_box_html_non_autorise = ob_get_clean();
gws_test_assert(strpos($meta_box_html_non_autorise, 'Partage privé') === false, 'Boîte latérale : aucun contrôle de partage privé affiché à un utilisateur qui ne peut pas éditer cette fiche');
gws_test_reset_security();

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
gws_test_make_horse(511, 'Cheval Sans Form Imbrique', 1, array('post_status' => 'draft'));
ob_start();
call_user_func($meta_box['callback'], get_post(511));
$meta_box_html_sans_partage = ob_get_clean();
gws_test_assert(stripos($meta_box_html_sans_partage, '<form') === false, 'Boîte latérale : aucun <form> imbriqué (état sans partage privé) — cause racine du bug de redirection vers Actualités');

gwseq_horse_private_share_activate(511);
ob_start();
call_user_func($meta_box['callback'], get_post(511));
$meta_box_html_avec_partage = ob_get_clean();
gws_test_assert(stripos($meta_box_html_avec_partage, '<form') === false, 'Boîte latérale : aucun <form> imbriqué non plus une fois le partage privé actif (Régénérer/Révoquer) — la cause racine ici était un <form> IMBRIQUÉ, jamais un <button> ordinaire du formulaire d’édition existant (voir audit UX/métier ci-dessous, qui introduit précisément un tel bouton pour "Régénérer")');
gws_test_assert(strpos($meta_box_html_avec_partage, '<button type="submit"') !== false, 'Boîte latérale : "Régénérer" est un vrai bouton de soumission du formulaire d’édition existant (audit UX/métier — sauvegarde la fiche avant de régénérer), pas un <form> imbriqué');
gws_test_assert(strpos($meta_box_html_avec_partage, 'Révoquer') !== false, 'Boîte latérale : "Révoquer" reste accessible (lien admin-post.php, inchangé)');
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

// =====================================================================================
// Audit UX/métier (§2) — gwseq_horse_private_share_maybe_activate_on_save() : greffée sur le hook
// NATIF save_post_{cpt}, jamais une seconde requête admin-post.php ni une logique de sauvegarde
// dupliquée. Vérifie ici précisément les gardes qui protègent cette activation, dans le MÊME ordre
// que gwseq_save_cheval_meta() (cheval-fields.php) pour rester cohérent avec le reste du module.
// =====================================================================================

gws_test_assert(
  in_array('gwseq_horse_private_share_maybe_activate_on_save', $GLOBALS['__gwseq_test_actions']['save_post_' . GWSEQ_CPT_CHEVAL] ?? array(), true),
  'Sauvegarde-avant-partage : gwseq_horse_private_share_maybe_activate_on_save() bien greffée sur le hook NATIF save_post_{cpt} (jamais un second point d’entrée admin-post.php)'
);

gws_test_make_horse(520, 'Cheval Sauvegarde Avant Partage', 1, array('post_status' => 'draft'));

// --- Champ absent du $_POST (sauvegarde normale de la fiche, ex. "Enregistrer le brouillon" tel
// quel, sans avoir cliqué "Créer") -> aucune activation, jamais un effet de bord inattendu ---
$_POST = array();
gwseq_horse_private_share_maybe_activate_on_save(520);
gws_test_assert(gwseq_horse_private_share_is_active(520) === false, 'Sauvegarde-avant-partage : une sauvegarde normale de la fiche (champ absent) n’active jamais le partage privé par accident');

// --- Champ présent -> active RÉELLEMENT le partage privé, exactement comme un clic direct sur
// gwseq_horse_private_share_activate() (même fonction métier, aucune duplication) ---
$_POST = array(GWSEQ_HORSE_PRIVATE_SHARE_SUBMIT_FIELD => '1');
gwseq_horse_private_share_maybe_activate_on_save(520);
gws_test_assert(gwseq_horse_private_share_is_active(520) === true, 'Sauvegarde-avant-partage : le champ soumis par le bouton "Créer" déclenche bien l’activation du partage privé, greffée sur la sauvegarde réelle de la fiche');
$token_520_initial = gwseq_horse_private_share_token(520);

// --- Régénérer réutilise le MÊME champ/même fonction (cohérent avec le bouton "Régénérer" ci-dessus,
// qui réutilise lui aussi l'action "activer") -> nouveau token, l'ancien cesse de fonctionner ---
$_POST = array(GWSEQ_HORSE_PRIVATE_SHARE_SUBMIT_FIELD => '1');
gwseq_horse_private_share_maybe_activate_on_save(520);
gws_test_assert(gwseq_horse_private_share_token(520) !== $token_520_initial, 'Sauvegarde-avant-partage : ressoumettre le même champ régénère bien un nouveau token (même opération que "Créer", §"Créer ET Régénérer réutilisent la même action")');

// --- Un utilisateur qui ne peut pas éditer CETTE fiche ne peut jamais activer son partage privé par
// ce mécanisme, même si le champ est présent (défense en profondeur — post.php aurait de toute façon
// déjà bloqué la sauvegarde elle-même avant d'arriver ici, mais ce garde reste testable isolément) ---
gwseq_horse_private_share_revoke(520);
$GLOBALS['__gwseq_test_security']['edit_others_posts'] = false;
$GLOBALS['__gwseq_test_security']['current_user_id'] = 2;
$_POST = array(GWSEQ_HORSE_PRIVATE_SHARE_SUBMIT_FIELD => '1');
gwseq_horse_private_share_maybe_activate_on_save(520);
gws_test_assert(gwseq_horse_private_share_is_active(520) === false, 'Sauvegarde-avant-partage : un utilisateur sans droit d’édition sur cette fiche ne peut jamais activer son partage privé par ce mécanisme');
gws_test_reset_security();

// --- Révision : jamais d'activation sur une révision (même garde que gwseq_save_cheval_meta()) ---
$GLOBALS['__gwseq_test_security']['is_revision'] = true;
$_POST = array(GWSEQ_HORSE_PRIVATE_SHARE_SUBMIT_FIELD => '1');
gwseq_horse_private_share_maybe_activate_on_save(520);
gws_test_assert(gwseq_horse_private_share_is_active(520) === false, 'Sauvegarde-avant-partage : aucune activation lors de la sauvegarde d’une révision');
gws_test_reset_security();
$_POST = array();

// =====================================================================================
// Audit UX/métier (§4) — gwseq_cheval_admin_list_post_states() : remplace "— Brouillon" par l'état
// MÉTIER pour le seul CPT Cheval, jamais pour un autre contenu WordPress (scopé, §4 : "sans altérer
// les autres contenus WordPress").
// =====================================================================================

gws_test_assert(
  in_array('gwseq_cheval_admin_list_post_states', $GLOBALS['__gwseq_test_filters']['display_post_states'] ?? array(), true),
  'Liste Chevaux : gwseq_cheval_admin_list_post_states() bien greffée sur le filtre natif display_post_states'
);

gws_test_make_horse(530, 'Cheval Liste En Preparation', 1, array('post_status' => 'draft'));
$states_en_preparation = gwseq_cheval_admin_list_post_states(array('draft' => 'Brouillon'), get_post(530));
gws_test_assert($states_en_preparation === array('gwseq_diffusion' => 'En préparation'), 'Liste Chevaux : brouillon sans token -> "En préparation" REMPLACE intégralement "Brouillon" natif, jamais cumulé avec lui');

gwseq_horse_private_share_activate(530);
$states_diffusion_privee = gwseq_cheval_admin_list_post_states(array('draft' => 'Brouillon'), get_post(530));
gws_test_assert($states_diffusion_privee === array('gwseq_diffusion' => 'Diffusion privée'), 'Liste Chevaux : brouillon AVEC token actif -> "Diffusion privée", plus jamais présenté comme un simple brouillon inachevé (§4 de la demande)');
gwseq_horse_private_share_revoke(530);

gws_test_make_horse(531, 'Cheval Liste Visible', 1); // publish par défaut
$states_visible = gwseq_cheval_admin_list_post_states(array('sticky' => 'À la une'), get_post(531));
gws_test_assert($states_visible === array(), 'Liste Chevaux : cheval visible sur le site -> aucun état affiché, exactement comme WordPress n’affiche déjà rien à côté d’un contenu publié');

$autre_post = (object) array('ID' => 900, 'post_type' => 'page');
$states_autre_type = gwseq_cheval_admin_list_post_states(array('draft' => 'Brouillon'), $autre_post);
gws_test_assert($states_autre_type === array('draft' => 'Brouillon'), 'Liste Chevaux : jamais appliqué à un autre type de contenu (scopé au seul CPT Cheval, §4 — "sans altérer les autres contenus WordPress")');

// =====================================================================================
// Ajustement UX suivant — "piloter la diffusion avec le vocabulaire GWS" : la boîte "État de
// diffusion" remplace la boîte native "Publier", UNIQUEMENT pour le CPT Cheval (§1/§6).
// =====================================================================================

gwseq_replace_cheval_publish_box();
$removed_submitdiv = end($GLOBALS['__gwseq_test_removed_meta_boxes']);
gws_test_assert($removed_submitdiv['id'] === 'submitdiv' && $removed_submitdiv['post_type'] === GWSEQ_CPT_CHEVAL && $removed_submitdiv['context'] === 'side', 'Boîte "État de diffusion" : la boîte native "Publier" (submitdiv) est retirée, SCOPÉE au seul CPT Cheval — jamais un désenregistrement global qui affecterait Pages/Actualités/Prestations/Membres');

$diffusion_box = null;
foreach ($GLOBALS['__gwseq_test_meta_boxes'] as $box) {
  if ($box['id'] === 'gwseq-cheval-diffusion') $diffusion_box = $box;
}
gws_test_assert($diffusion_box !== null && $diffusion_box['post_type'] === GWSEQ_CPT_CHEVAL && $diffusion_box['context'] === 'side' && $diffusion_box['priority'] === 'high', 'Boîte "État de diffusion" : nouvelle boîte enregistrée à la place de "Publier", même colonne (side), priorité haute');

// --- Rendu : État "En préparation" -> Enregistrer / Activer la diffusion privée / Rendre visible
// sur le site (si capacité publish_post) ---
gws_test_make_horse(540, 'Cheval Diffusion En Preparation', 1, array('post_status' => 'draft'));
ob_start();
gwseq_render_cheval_diffusion_box(get_post(540));
$diffusion_html_en_preparation = ob_get_clean();
gws_test_assert(strpos($diffusion_html_en_preparation, 'name="post_status" value="draft"') !== false, 'Boîte "État de diffusion", En préparation : champ caché post_status préservant le statut WordPress ACTUEL (aucun contrôle natif direct laissé à l’utilisateur)');
gws_test_assert(strpos($diffusion_html_en_preparation, 'État de diffusion') !== false && strpos($diffusion_html_en_preparation, 'En préparation') !== false, 'Boîte "État de diffusion", En préparation : libellé métier affiché ("État de diffusion : En préparation")');
gws_test_assert(strpos($diffusion_html_en_preparation, '>Enregistrer<') !== false, 'Boîte "État de diffusion", En préparation : bouton neutre "Enregistrer" (jamais "Enregistrer le brouillon")');
gws_test_assert(strpos($diffusion_html_en_preparation, 'name="' . GWSEQ_HORSE_DIFFUSION_TRANSITION_FIELD . '" value="' . GWSEQ_HORSE_DIFFUSION_PRIVEE . '"') !== false, 'Boîte "État de diffusion", En préparation : bouton "Activer la diffusion privée" présent');
gws_test_assert(strpos($diffusion_html_en_preparation, 'name="' . GWSEQ_HORSE_DIFFUSION_TRANSITION_FIELD . '" value="' . GWSEQ_HORSE_DIFFUSION_VISIBLE_SITE . '"') !== false, 'Boîte "État de diffusion", En préparation : bouton "Rendre visible sur le site" présent (capacité publish_post accordée par défaut)');
gws_test_assert(strpos($diffusion_html_en_preparation, 'Repasser en préparation') === false, 'Boîte "État de diffusion", En préparation : jamais de bouton "Repasser en préparation" (déjà dans cet état)');
gws_test_assert(strpos($diffusion_html_en_preparation, 'Brouillon') === false && strpos($diffusion_html_en_preparation, 'Publier') === false, 'Boîte "État de diffusion" : jamais le vocabulaire technique WordPress "Brouillon"/"Publier" proposé comme commande métier (§7)');

// --- Sans la capacité publish_post : "Rendre visible sur le site" disparaît, "Activer la diffusion
// privée" reste proposé (§4 : "ne jamais rendre public si l'utilisateur n'a pas la capacité") ---
$GLOBALS['__gwseq_test_security']['publish_posts'] = false;
ob_start();
gwseq_render_cheval_diffusion_box(get_post(540));
$diffusion_html_sans_publish_cap = ob_get_clean();
gws_test_assert(strpos($diffusion_html_sans_publish_cap, 'Rendre visible sur le site') === false, 'Boîte "État de diffusion" : sans la capacité publish_post, "Rendre visible sur le site" n’est JAMAIS proposé (§4)');
gws_test_assert(strpos($diffusion_html_sans_publish_cap, 'Activer la diffusion privée') !== false, 'Boîte "État de diffusion" : "Activer la diffusion privée" reste proposé sans la capacité publish_post (rendre non public n’exige jamais cette capacité)');
$GLOBALS['__gwseq_test_security']['publish_posts'] = true;

// --- Rendu : État "Diffusion privée" -> Enregistrer les modifications / Rendre visible sur le site
// / Repasser en préparation ---
gws_test_make_horse(541, 'Cheval Diffusion Privee', 1, array('post_status' => 'draft'));
gwseq_horse_private_share_activate(541);
ob_start();
gwseq_render_cheval_diffusion_box(get_post(541));
$diffusion_html_privee = ob_get_clean();
gws_test_assert(strpos($diffusion_html_privee, 'Diffusion privée') !== false, 'Boîte "État de diffusion", Diffusion privée : libellé métier affiché');
gws_test_assert(strpos($diffusion_html_privee, '>Enregistrer les modifications<') !== false, 'Boîte "État de diffusion", Diffusion privée : bouton "Enregistrer les modifications" (jamais le seul "Enregistrer" neutre de l’état "En préparation")');
gws_test_assert(strpos($diffusion_html_privee, 'name="' . GWSEQ_HORSE_DIFFUSION_TRANSITION_FIELD . '" value="' . GWSEQ_HORSE_DIFFUSION_VISIBLE_SITE . '"') !== false, 'Boîte "État de diffusion", Diffusion privée : bouton "Rendre visible sur le site" présent');
gws_test_assert(strpos($diffusion_html_privee, 'name="' . GWSEQ_HORSE_DIFFUSION_TRANSITION_FIELD . '" value="' . GWSEQ_HORSE_DIFFUSION_EN_PREPARATION . '"') !== false, 'Boîte "État de diffusion", Diffusion privée : bouton "Repasser en préparation" présent');
gws_test_assert(strpos($diffusion_html_privee, 'Activer la diffusion privée') === false, 'Boîte "État de diffusion", Diffusion privée : jamais de bouton "Activer la diffusion privée" (déjà dans cet état)');
gwseq_horse_private_share_revoke(541);

// --- Rendu : État "Visible sur le site" -> Enregistrer les modifications / retrait explicite à
// deux choix, jamais un "Dépublier" ambigu (§2) ---
gws_test_make_horse(542, 'Cheval Diffusion Visible', 1); // publish par défaut
ob_start();
gwseq_render_cheval_diffusion_box(get_post(542));
$diffusion_html_visible = ob_get_clean();
gws_test_assert(strpos($diffusion_html_visible, 'Visible sur le site') !== false, 'Boîte "État de diffusion", Visible sur le site : libellé métier affiché');
gws_test_assert(strpos($diffusion_html_visible, '>Enregistrer les modifications<') !== false, 'Boîte "État de diffusion", Visible sur le site : bouton "Enregistrer les modifications"');
gws_test_assert(strpos($diffusion_html_visible, 'Dépublier') === false, 'Boîte "État de diffusion", Visible sur le site : jamais le wording ambigu "Dépublier" (§2)');
gws_test_assert(strpos($diffusion_html_visible, 'Retirer la fiche du site') !== false, 'Boîte "État de diffusion", Visible sur le site : section explicite "Retirer la fiche du site"');
gws_test_assert(strpos($diffusion_html_visible, 'name="' . GWSEQ_HORSE_DIFFUSION_TRANSITION_FIELD . '" value="' . GWSEQ_HORSE_DIFFUSION_EN_PREPARATION . '"') !== false, 'Boîte "État de diffusion", Visible sur le site : choix explicite "Repasser en préparation" pour le retrait');
gws_test_assert(strpos($diffusion_html_visible, 'name="' . GWSEQ_HORSE_DIFFUSION_TRANSITION_FIELD . '" value="' . GWSEQ_HORSE_DIFFUSION_PRIVEE . '"') !== false, 'Boîte "État de diffusion", Visible sur le site : choix explicite "Activer la diffusion privée" pour le retrait (§2 : "interaction simple et explicite" entre les deux états cibles possibles)');

// --- Utilisateur sans droit d'édition : rien n'est rendu ---
$GLOBALS['__gwseq_test_security']['edit_others_posts'] = false;
$GLOBALS['__gwseq_test_security']['current_user_id'] = 2;
ob_start();
gwseq_render_cheval_diffusion_box(get_post(540));
gws_test_assert(ob_get_clean() === '', 'Boîte "État de diffusion" : rien n’est rendu à un utilisateur qui ne peut pas éditer cette fiche');
gws_test_reset_security();

// =====================================================================================
// Ajustement UX suivant — gwseq_horse_apply_diffusion_transition_on_save() : applique la
// transition demandée, greffée sur le hook NATIF save_post_{cpt} (§3 : sauvegarde en un seul geste,
// jamais "Enregistrer le brouillon" PUIS changer la diffusion en deux opérations séparées).
// =====================================================================================

gws_test_assert(
  in_array('gwseq_horse_apply_diffusion_transition_on_save', $GLOBALS['__gwseq_test_actions']['save_post_' . GWSEQ_CPT_CHEVAL] ?? array(), true),
  'Transition de diffusion : gwseq_horse_apply_diffusion_transition_on_save() bien greffée sur le hook NATIF save_post_{cpt}'
);

gws_test_make_horse(560, 'Cheval Transition Save', 1, array('post_status' => 'draft'));

// --- Champ absent (sauvegarde normale, aucune transition demandée) -> aucun changement ---
$_POST = array();
gwseq_horse_apply_diffusion_transition_on_save(560);
gws_test_assert($GLOBALS['__gwseq_test_posts'][560]['post_status'] === 'draft' && gwseq_horse_private_share_is_active(560) === false, 'Transition de diffusion : champ absent -> aucun changement d’état');

// --- Valeur invalide (jamais un statut/token fabriqué à partir d’une entrée arbitraire) ---
$_POST = array(GWSEQ_HORSE_DIFFUSION_TRANSITION_FIELD => 'valeur_inventee');
gwseq_horse_apply_diffusion_transition_on_save(560);
gws_test_assert($GLOBALS['__gwseq_test_posts'][560]['post_status'] === 'draft' && gwseq_horse_private_share_is_active(560) === false, 'Transition de diffusion : une valeur hors des trois états connus est ignorée, jamais appliquée');

// --- Transition valide : préparation -> diffusion privée, en un seul geste ---
$_POST = array(GWSEQ_HORSE_DIFFUSION_TRANSITION_FIELD => GWSEQ_HORSE_DIFFUSION_PRIVEE);
gwseq_horse_apply_diffusion_transition_on_save(560);
gws_test_assert($GLOBALS['__gwseq_test_posts'][560]['post_status'] === 'draft' && gwseq_horse_private_share_is_active(560) === true, 'Transition de diffusion : "diffusion_privee" appliquée (statut draft + token actif), déclenchée par le hook de sauvegarde réelle');

// --- Réentrance : remove_action()/add_action() se sont bien annulés l'un l'autre (le hook reste
// enregistré EXACTEMENT une fois après l'opération, ni perdu ni dupliqué) ---
$occurrences_hook = array_count_values($GLOBALS['__gwseq_test_actions']['save_post_' . GWSEQ_CPT_CHEVAL])['gwseq_horse_apply_diffusion_transition_on_save'] ?? 0;
gws_test_assert($occurrences_hook === 1, 'Transition de diffusion : la garde de réentrance (remove_action() avant l’appel, add_action() après) laisse le hook enregistré EXACTEMENT une fois, ni perdu ni dupliqué');

// --- Transition valide : diffusion privée -> préparation (token révoqué) ---
$_POST = array(GWSEQ_HORSE_DIFFUSION_TRANSITION_FIELD => GWSEQ_HORSE_DIFFUSION_EN_PREPARATION);
gwseq_horse_apply_diffusion_transition_on_save(560);
gws_test_assert($GLOBALS['__gwseq_test_posts'][560]['post_status'] === 'draft' && gwseq_horse_private_share_is_active(560) === false, 'Transition de diffusion : "en_preparation" appliquée (token révoqué)');

// --- "Rendre visible sur le site" SANS la capacité publish_post -> refusée, aucun changement
// (§4 : défense en profondeur au niveau du hook, pas seulement l'affichage du bouton) ---
$GLOBALS['__gwseq_test_security']['publish_posts'] = false;
$_POST = array(GWSEQ_HORSE_DIFFUSION_TRANSITION_FIELD => GWSEQ_HORSE_DIFFUSION_VISIBLE_SITE);
gwseq_horse_apply_diffusion_transition_on_save(560);
gws_test_assert($GLOBALS['__gwseq_test_posts'][560]['post_status'] === 'draft', 'Transition de diffusion : "visible_site" REFUSÉE sans la capacité publish_post, même si le champ est soumis (défense en profondeur, §4)');
$GLOBALS['__gwseq_test_security']['publish_posts'] = true;

// --- Avec la capacité : appliquée ---
gwseq_horse_apply_diffusion_transition_on_save(560);
gws_test_assert($GLOBALS['__gwseq_test_posts'][560]['post_status'] === 'publish', 'Transition de diffusion : "visible_site" appliquée avec la capacité publish_post');
$_POST = array();

// --- Utilisateur sans droit d'édition sur CETTE fiche -> aucune transition ---
gws_test_make_horse(561, 'Cheval Transition Sans Droit', 1, array('post_status' => 'draft'));
$GLOBALS['__gwseq_test_security']['edit_others_posts'] = false;
$GLOBALS['__gwseq_test_security']['current_user_id'] = 2;
$_POST = array(GWSEQ_HORSE_DIFFUSION_TRANSITION_FIELD => GWSEQ_HORSE_DIFFUSION_PRIVEE);
gwseq_horse_apply_diffusion_transition_on_save(561);
gws_test_assert(gwseq_horse_private_share_is_active(561) === false, 'Transition de diffusion : un utilisateur sans droit d’édition sur cette fiche ne peut déclencher aucune transition');
gws_test_reset_security();

// --- Révision : jamais de transition appliquée ---
gws_test_make_horse(562, 'Cheval Transition Revision', 1, array('post_status' => 'draft'));
$GLOBALS['__gwseq_test_security']['is_revision'] = true;
$_POST = array(GWSEQ_HORSE_DIFFUSION_TRANSITION_FIELD => GWSEQ_HORSE_DIFFUSION_PRIVEE);
gwseq_horse_apply_diffusion_transition_on_save(562);
gws_test_assert(gwseq_horse_private_share_is_active(562) === false, 'Transition de diffusion : aucune transition appliquée lors de la sauvegarde d’une révision');
gws_test_reset_security();
$_POST = array();

// =====================================================================================
// Complément de recette (04/09) — audit NON DESTRUCTIF des chevaux déjà en visibilité WordPress
// native (statut "private" ou protection par mot de passe), jamais migrés silencieusement.
// =====================================================================================

gws_test_assert(gwseq_cheval_native_visibility_mismatches() === array(), 'Audit visibilité native : aucun cheval concerné avant l’introduction de tels cas (aucun faux positif)');

gws_test_make_post(570, GWSEQ_CPT_CHEVAL, 'Cheval Statut Prive Natif', 'private', 1);
gws_test_make_post(571, GWSEQ_CPT_CHEVAL, 'Cheval Protege Mot De Passe', 'draft', 1);
$GLOBALS['__gwseq_test_posts'][571]['post_password'] = 'secret';
gws_test_make_horse(572, 'Cheval Normal Sans Souci', 1, array('post_status' => 'draft'));

$mismatches = gwseq_cheval_native_visibility_mismatches();
sort($mismatches);
gws_test_assert($mismatches === array(570, 571), 'Audit visibilité native : détecte le statut "private" ET la protection par mot de passe, jamais un cheval "normal"');

$statut_570_avant = get_post(570)->post_status;
gwseq_cheval_native_visibility_mismatches();
gws_test_assert(get_post(570)->post_status === $statut_570_avant, 'Audit visibilité native : fonction PURE (lecture seule) — aucune écriture/migration déclenchée par son seul appel');

$GLOBALS['__gwseq_test_screen'] = (object) array('id' => 'edit-gwseq_cheval');
ob_start();
gwseq_cheval_admin_native_visibility_notice();
$notice_html = ob_get_clean();
gws_test_assert(strpos($notice_html, 'notice-warning') !== false, 'Audit visibilité native : notice affichée sur la liste Chevaux lorsque des fiches sont concernées');
gws_test_assert(strpos($notice_html, 'Cheval Statut Prive Natif') !== false && strpos($notice_html, 'Cheval Protege Mot De Passe') !== false, 'Audit visibilité native : les fiches concernées sont nommément listées, avec lien d’édition');
gws_test_assert(strpos($notice_html, 'Cheval Normal Sans Souci') === false, 'Audit visibilité native : un cheval sans souci n’apparaît jamais dans la notice');

$GLOBALS['__gwseq_test_screen'] = (object) array('id' => 'edit-gwseq_prestation');
ob_start();
gwseq_cheval_admin_native_visibility_notice();
gws_test_assert(ob_get_clean() === '', 'Audit visibilité native : jamais affichée hors de l’écran liste Chevaux (scopé, aucun impact sur les autres post types)');

$GLOBALS['__gwseq_test_posts'][570]['post_status'] = 'draft';
unset($GLOBALS['__gwseq_test_posts'][571]);
$GLOBALS['__gwseq_test_screen'] = (object) array('id' => 'edit-gwseq_cheval');
ob_start();
gwseq_cheval_admin_native_visibility_notice();
gws_test_assert(ob_get_clean() === '', 'Audit visibilité native : plus aucune notice une fois les fiches concernées corrigées');

// --- DOING_AUTOSAVE : testé en TOUT DERNIER, même contrainte que le reste de la suite (constante
// PHP réelle, ne peut être définie qu'une seule fois par processus — resterait sinon "vraie" pour
// tous les tests précédents si définie plus tôt) ---
gws_test_make_horse(521, 'Cheval Autosave Partage', 1, array('post_status' => 'draft'));
define('DOING_AUTOSAVE', true);
$_POST = array(GWSEQ_HORSE_PRIVATE_SHARE_SUBMIT_FIELD => '1');
gwseq_horse_private_share_maybe_activate_on_save(521);
gws_test_assert(gwseq_horse_private_share_is_active(521) === false, 'Sauvegarde-avant-partage : aucune activation pendant un autosave WordPress (même garde que gwseq_save_cheval_meta())');
$_POST = array();

gws_test_make_horse(563, 'Cheval Autosave Transition', 1, array('post_status' => 'draft'));
$_POST = array(GWSEQ_HORSE_DIFFUSION_TRANSITION_FIELD => GWSEQ_HORSE_DIFFUSION_PRIVEE);
gwseq_horse_apply_diffusion_transition_on_save(563);
gws_test_assert(gwseq_horse_private_share_is_active(563) === false, 'Transition de diffusion : aucune transition appliquée pendant un autosave WordPress');
$_POST = array();

echo ($failures === 0 ? 'Tous les tests sont passés.' : "$failures test(s) en échec.") . "\n";
exit($failures === 0 ? 0 : 1);

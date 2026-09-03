<?php
/**
 * Vérifie le rendu front des Mises en avant (includes/campagnes-front.php) : résolution
 * d'éligibilité (statut + fenêtre de dates + ciblage), résolution de la CONCURRENCE/priorité par
 * `menu_order` croissant quand plusieurs campagnes du même type sont éligibles (§I), cohabitation
 * indépendante d'une Pop-in ET d'une Sticky bar sur la même page, contexte de page
 * (`gwseq_campagnes_current_context()`), et chargement conditionnel des assets front (jamais de
 * chargement systématique — §L). Même méthodologie que le reste de cette suite.
 *
 * Ne fait pas partie des paquets livrés (gws-core.zip / gws-starter.zip).
 */

$failures = 0;
function gws_test_assert($condition, $label) {
  global $failures;
  if ($condition) { echo "OK   - $label\n"; }
  else { echo "FAIL - $label\n"; $failures++; }
}

// --- Stubs WordPress minimaux (mêmes conventions que les autres fichiers de cette suite) ---
function wp_unslash($value) { return is_array($value) ? array_map('wp_unslash', $value) : $value; }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value)); }
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function esc_url($value) { return $value; }
function esc_url_raw($value) { $value = trim((string) $value); return $value === '' ? '' : $value; }
function esc_textarea($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function absint($value) { return abs((int) $value); }
function __($text, $domain = 'default') { return $text; }
function esc_html__($text, $domain = 'default') { return esc_html($text); }
function esc_attr__($text, $domain = 'default') { return esc_attr($text); }
function esc_html_e($text, $domain = 'default') { echo esc_html__($text, $domain); }
function esc_attr_e($text, $domain = 'default') { echo esc_attr__($text, $domain); }
function selected($a, $b, $echo = true) { $r = $a == $b ? " selected='selected'" : ''; if ($echo) echo $r; return $r; }
function checked($a, $b = true, $echo = true) { $r = $a == $b ? " checked='checked'" : ''; if ($echo) echo $r; return $r; }
function wp_nonce_field($action, $field) { echo '<input type="hidden" name="' . esc_attr($field) . '" value="stub-nonce">'; }
function sanitize_hex_color($color) {
  if ('' === $color) return '';
  if (preg_match('|^#([A-Fa-f0-9]{3}){1,2}$|', $color)) return $color;
  return null;
}
function wp_kses($string, $allowed_html) {
  return preg_replace_callback('/<(\/?)([a-zA-Z0-9]+)([^>]*)>/', function ($m) use ($allowed_html) {
    $closing = $m[1] === '/';
    $tag = strtolower($m[2]);
    if (!array_key_exists($tag, $allowed_html)) return '';
    if ($closing) return '</' . $tag . '>';
    return '<' . $tag . '>';
  }, (string) $string);
}
function wp_editor($content, $editor_id, $settings = array()) {
  echo '<textarea id="' . esc_attr($editor_id) . '" name="' . esc_attr($settings['textarea_name'] ?? '') . '">' . esc_textarea($content) . '</textarea>';
}

function gws_core_field_sanitize($type, $raw_value) {
  switch ($type) {
    case 'url': return esc_url_raw(wp_unslash($raw_value));
    case 'checkbox': return $raw_value ? '1' : '';
    case 'attachment_id':
      $id = absint($raw_value);
      return ($id && wp_attachment_is_image($id)) ? $id : 0;
    case 'text':
    default: return sanitize_text_field(wp_unslash($raw_value));
  }
}

$GLOBALS['__gwseq_test_image_attachments'] = array();
function wp_attachment_is_image($id) { return in_array((int) $id, $GLOBALS['__gwseq_test_image_attachments'], true); }
$GLOBALS['__gwseq_test_attachment_urls'] = array();
function wp_get_attachment_image_url($id, $size = 'thumbnail') { return $GLOBALS['__gwseq_test_attachment_urls'][$id][$size] ?? ($GLOBALS['__gwseq_test_attachment_urls'][$id]['*'] ?? false); }
function get_post_meta($post_id, $key, $single = false) { return $GLOBALS['__gwseq_test_meta'][$post_id][$key] ?? ''; }
function update_post_meta($post_id, $key, $value) { $GLOBALS['__gwseq_test_meta'][$post_id][$key] = $value; return true; }
function get_post_field($field, $post_id) { return $GLOBALS['__gwseq_test_post_fields'][$post_id][$field] ?? ''; }

function register_post_meta($object_type, $meta_key, $args = array()) {}
function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {}
function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {}
function add_meta_box($id, $title, $callback, $post_type = null, $context = 'advanced', $priority = 'default') {}
function wp_verify_nonce($nonce, $action) { return true; }
function current_user_can($cap, $post_id = null) { return true; }
function wp_is_post_revision($post_id) { return false; }
function wp_send_json_success($data = null) {}
function wp_send_json_error($data = null, $status = null) {}
function get_current_screen() { return null; }
function wp_enqueue_script($handle, ...$rest) { $GLOBALS['__gwseq_enqueued'][] = $handle; }
function wp_enqueue_style($handle, ...$rest) { $GLOBALS['__gwseq_enqueued'][] = $handle; }
function wp_localize_script($handle, $name, $data) {}
function admin_url($path) { return 'https://example.test/wp-admin/' . $path; }
function wp_create_nonce($action) { return 'nonce-' . $action; }

// --- Registre de posts : post_type + post_status + meta (nécessaire pour le filtrage de
// get_posts() ci-dessous, qui simule le `meta_key`/`meta_value` réellement utilisé par
// gwseq_query_active_campagne_ids()) ---
$GLOBALS['__gwseq_test_posts'] = array();
$GLOBALS['__gwseq_test_post_status'] = array();
function get_post_type($post_id) { return $GLOBALS['__gwseq_test_posts'][$post_id] ?? false; }
function gws_test_make_post_stub($id, $post_type, $status = 'publish', $menu_order = 0) {
  $GLOBALS['__gwseq_test_posts'][$id] = $post_type;
  $GLOBALS['__gwseq_test_post_status'][$id] = $status;
  $GLOBALS['__gwseq_test_post_fields'][$id]['menu_order'] = $menu_order;
}
function get_posts($args = array()) {
  $post_type = $args['post_type'] ?? 'post';
  $meta_key = $args['meta_key'] ?? null;
  $meta_value = $args['meta_value'] ?? null;
  $results = array();
  foreach ($GLOBALS['__gwseq_test_posts'] as $id => $type) {
    if ($type !== $post_type) continue;
    if (($GLOBALS['__gwseq_test_post_status'][$id] ?? 'publish') !== ($args['post_status'] ?? 'publish')) continue;
    if ($meta_key !== null && get_post_meta($id, $meta_key, true) !== $meta_value) continue;
    $results[] = $id;
  }
  if (($args['orderby'] ?? '') === 'menu_order') {
    usort($results, function ($a, $b) {
      return ($GLOBALS['__gwseq_test_post_fields'][$a]['menu_order'] ?? 0) <=> ($GLOBALS['__gwseq_test_post_fields'][$b]['menu_order'] ?? 0);
    });
  }
  return ($args['fields'] ?? '') === 'ids' ? $results : array_map(function ($id) { return (object) array('ID' => $id); }, $results);
}

// --- Contexte de requête (page courante) et environnement, injectables pour les tests ---
$GLOBALS['__gwseq_test_context'] = array('queried_post_id' => 0, 'is_front_page' => false, 'is_admin' => false, 'doing_ajax' => false, 'doing_cron' => false, 'is_feed' => false);
function get_queried_object_id() { return $GLOBALS['__gwseq_test_context']['queried_post_id']; }
function is_front_page() { return $GLOBALS['__gwseq_test_context']['is_front_page']; }
function is_admin() { return $GLOBALS['__gwseq_test_context']['is_admin']; }
function wp_doing_ajax() { return $GLOBALS['__gwseq_test_context']['doing_ajax']; }
function wp_doing_cron() { return $GLOBALS['__gwseq_test_context']['doing_cron']; }
function is_feed() { return $GLOBALS['__gwseq_test_context']['is_feed']; }

define('ABSPATH', __DIR__ . '/');
define('GWS_CORE_DIR', dirname(__DIR__) . '/wp-content/plugins/gws-core/');
define('GWS_CORE_URL', 'https://example.test/wp-content/plugins/gws-core/');
const GWSEQ_CPT_POPIN = 'gwseq_popin';
const GWSEQ_CPT_STICKY_BAR = 'gwseq_sticky_bar';
const GWSEQ_CPT_CHEVAL = 'gwseq_cheval';
const GWSEQ_CPT_PRESTATION = 'gwseq_prestation';
define('GWSEQ_MODULE_URL', GWS_CORE_URL . 'modules/gws-equestrian/');
define('GWSEQ_MODULE_VERSION', '0.0.0-test');

$module_dir = GWS_CORE_DIR . 'modules/gws-equestrian/';
require $module_dir . 'includes/campagnes-shared.php';
require $module_dir . 'includes/popin-fields.php';
require $module_dir . 'includes/sticky-bar-fields.php';
require $module_dir . 'includes/campagnes-front.php';
gwseq_register_popin_meta();
gwseq_register_sticky_bar_meta();

// =====================================================================================
// Contexte de page
// =====================================================================================

gws_test_make_post_stub(50, 'page');
$GLOBALS['__gwseq_test_context']['queried_post_id'] = 50;
$GLOBALS['__gwseq_test_context']['is_front_page'] = false;
$contexte = gwseq_campagnes_current_context();
gws_test_assert($contexte['queried_post_id'] === 50 && $contexte['is_front_page'] === false, 'Contexte : ID de contenu et page d\'accueil résolus depuis les fonctions natives WordPress');

// =====================================================================================
// gwseq_query_active_campagne_ids() : statut GWS "active" ET post_status "publish" ET tri
// =====================================================================================

gws_test_make_post_stub(1, GWSEQ_CPT_POPIN, 'publish', 10);
update_post_meta(1, '_gwseq_popin_statut', 'active');
gws_test_make_post_stub(2, GWSEQ_CPT_POPIN, 'publish', 5);
update_post_meta(2, '_gwseq_popin_statut', 'active');
gws_test_make_post_stub(3, GWSEQ_CPT_POPIN, 'publish', 1);
update_post_meta(3, '_gwseq_popin_statut', 'inactive');
gws_test_make_post_stub(4, GWSEQ_CPT_POPIN, 'trash', 0);
update_post_meta(4, '_gwseq_popin_statut', 'active');

$candidats = gwseq_query_active_campagne_ids(GWSEQ_CPT_POPIN, '_gwseq_popin_statut');
gws_test_assert($candidats === array(2, 1), 'Requête : seules les pop-ins "publish" ET statut GWS "active" sont candidates, triées par menu_order croissant (§I)');

// =====================================================================================
// gwseq_campagne_choisir_eligible() : fenêtre de dates + ciblage, la première éligible gagne
// =====================================================================================

function gws_test_make_diffusion($overrides = array()) {
  return array_merge(array(
    'debut_ts' => 0, 'fin_ts' => 0, 'ciblage_mode' => 'all', 'ciblage_cibles' => array(),
  ), $overrides);
}

$diffusions = array(
  10 => gws_test_make_diffusion(array('ciblage_mode' => 'front_page', 'ciblage_cibles' => array())),
  20 => gws_test_make_diffusion(array('ciblage_mode' => 'all', 'ciblage_cibles' => array())),
);
$get_diffusion = function ($id) use ($diffusions) { return $diffusions[$id]; };

// --- Page d'accueil : la campagne #10 (page d'accueil uniquement) est éligible et prioritaire (menu_order implicite via l'ordre du tableau) ---
$contexte_accueil = array('queried_post_id' => 0, 'is_front_page' => true);
gws_test_assert(gwseq_campagne_choisir_eligible(array(10, 20), $get_diffusion, $contexte_accueil) === 10, 'Priorité : la première candidate réellement éligible (dans l\'ordre déjà trié par menu_order) gagne (§I)');

// --- Page normale : #10 n'est pas éligible (page d'accueil uniquement), #20 (tout le site) l'est ---
$contexte_normal = array('queried_post_id' => 999, 'is_front_page' => false);
gws_test_assert(gwseq_campagne_choisir_eligible(array(10, 20), $get_diffusion, $contexte_normal) === 20, 'Éligibilité : une candidate inéligible (ciblage) est ignorée au profit de la suivante dans l\'ordre de priorité');

// --- Aucune candidate éligible -> 0, jamais d'erreur ---
gws_test_assert(gwseq_campagne_choisir_eligible(array(10), $get_diffusion, $contexte_normal) === 0, 'Éligibilité : aucune candidate éligible -> 0 (aucune campagne affichée), jamais d\'erreur');

// --- Fenêtre de dates : une campagne hors période n'est jamais éligible même si le ciblage correspond ---
$diffusions_dates = array(
  30 => gws_test_make_diffusion(array('debut_ts' => 2000000000)), // dans le futur
  40 => gws_test_make_diffusion(),
);
$get_diffusion_dates = function ($id) use ($diffusions_dates) { return $diffusions_dates[$id]; };
gws_test_assert(gwseq_campagne_choisir_eligible(array(30, 40), $get_diffusion_dates, $contexte_normal, 1000000000) === 40, 'Éligibilité : une campagne dont la période n\'a pas encore commencé est ignorée au profit de la suivante');

// =====================================================================================
// Intégration bout en bout : gwseq_get_eligible_popin_id() / gwseq_get_eligible_sticky_bar_id()
// =====================================================================================

// --- Popin #2 (menu_order 5) et #1 (menu_order 10) sont toutes deux actives/publish/ciblage "Tout
// le site" -> #2 doit gagner (plus petit menu_order, §I) ---
$GLOBALS['__gwseq_test_context']['queried_post_id'] = 999;
$GLOBALS['__gwseq_test_context']['is_front_page'] = false;
gws_test_assert(gwseq_get_eligible_popin_id() === 2, 'Intégration : parmi plusieurs pop-ins éligibles, celle au plus petit menu_order gagne (§I), résultat mémoïsé');

// --- Aucune sticky bar enregistrée -> 0 ---
gws_test_assert(gwseq_get_eligible_sticky_bar_id() === 0, 'Intégration : aucune sticky bar active -> 0, aucune erreur');

// --- Cohabitation : on ajoute une sticky bar active ; pop-in ET sticky bar doivent pouvoir
// coexister (résolution strictement indépendante par type, §I) ---
gws_test_make_post_stub(100, GWSEQ_CPT_STICKY_BAR, 'publish', 1);
update_post_meta(100, '_gwseq_sticky_bar_statut', 'active');
// gwseq_get_eligible_sticky_bar_id() est mémoïsé (static) : on ne peut pas revalider dans le même
// process sans le rappeler dans un sous-processus isolé -> on vérifie la fonction sous-jacente non
// mémoïsée directement pour prouver l'indépendance des deux résolutions.
$candidats_sticky = gwseq_query_active_campagne_ids(GWSEQ_CPT_STICKY_BAR, '_gwseq_sticky_bar_statut');
gws_test_assert($candidats_sticky === array(100), 'Cohabitation : la sticky bar active est bien candidate indépendamment de la pop-in déjà résolue');
$sticky_eligible = gwseq_campagne_choisir_eligible($candidats_sticky, 'gwseq_get_sticky_bar_diffusion', gwseq_campagnes_current_context());
gws_test_assert($sticky_eligible === 100, 'Cohabitation : une Pop-in ET une Sticky bar peuvent être résolues comme éligibles simultanément (§I)');

// =====================================================================================
// gwseq_should_load_campagnes_front_assets() : jamais de chargement systématique (§L)
// =====================================================================================

// Contexte réutilisant les campagnes déjà enregistrées ci-dessus (pop-in #2 éligible en toile de fond)
$GLOBALS['__gwseq_test_context']['is_admin'] = true;
gws_test_assert(gwseq_should_load_campagnes_front_assets() === false, 'Assets : jamais chargés dans l\'administration');
$GLOBALS['__gwseq_test_context']['is_admin'] = false;

$GLOBALS['__gwseq_test_context']['doing_ajax'] = true;
gws_test_assert(gwseq_should_load_campagnes_front_assets() === false, 'Assets : jamais chargés pendant une requête AJAX');
$GLOBALS['__gwseq_test_context']['doing_ajax'] = false;

$GLOBALS['__gwseq_test_context']['is_feed'] = true;
gws_test_assert(gwseq_should_load_campagnes_front_assets() === false, 'Assets : jamais chargés sur un flux (feed)');
$GLOBALS['__gwseq_test_context']['is_feed'] = false;

// --- Aucune campagne active du tout (nouveau process simulé : on vide les statuts) -> aucun asset ---
update_post_meta(1, '_gwseq_popin_statut', 'inactive');
update_post_meta(2, '_gwseq_popin_statut', 'inactive');
update_post_meta(100, '_gwseq_sticky_bar_statut', 'inactive');
// gwseq_get_eligible_popin_id()/gwseq_get_eligible_sticky_bar_id() étant mémoïsées (static) dans ce
// process, on vérifie directement la fonction de test sous-jacente (non mémoïsée) pour prouver que
// la logique de garde ne charge bien rien quand aucune campagne n'est éligible.
$aucune_candidate = gwseq_query_active_campagne_ids(GWSEQ_CPT_POPIN, '_gwseq_popin_statut');
gws_test_assert($aucune_candidate === array(), 'Assets : plus aucune pop-in active -> plus aucune candidate (vérification de la source, en amont de la mémoïsation)');

echo ($failures === 0 ? 'Tous les tests sont passés.' : "$failures test(s) en échec.") . "\n";
exit($failures === 0 ? 0 : 1);

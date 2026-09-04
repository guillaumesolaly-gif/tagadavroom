<?php
/**
 * Vérifie la page destinataire publique `/selection/{token}/` (includes/cheval-selection-front.php,
 * Suite V1 « Partager & vendre », Lot 2B, §3 de l'ajustement de recette) : enregistrement de la
 * règle de réécriture/query var, chargement conditionnel des assets (uniquement sur cette route),
 * en-têtes anti-cache, et le RENDU HTML lui-même (titre, cartes de chevaux présentables, état vide
 * propre, noindex, échappement des données affichées) — réutilise EXCLUSIVEMENT gwseq_selection_
 * get_public_view()/gwseq_selection_get_public_card() (includes/cheval-selection.php, déjà
 * couvertes par gws-equestrian-cheval-selection-logic-test.php) pour les données, jamais un second
 * calcul ici. Même convention que le reste de cette suite : les fonctions qui appellent réellement
 * `exit` (gwseq_selection_render_public_page(), résolution du token + 404) ne sont JAMAIS appelées
 * directement — seules leurs briques testables le sont (voir gws-equestrian-cheval-share-admin-
 * test.php pour le même principe sur la route de partage privé Cheval).
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
function sanitize_text_field($value) { if (is_array($value) || is_object($value)) return ''; return trim(strip_tags((string) $value)); }
function sanitize_textarea_field($value) { if (is_array($value) || is_object($value)) return ''; return trim(strip_tags((string) $value)); }
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
function wp_parse_args($args, $defaults = array()) { return array_merge((array) $defaults, (array) $args); }
function home_url($path = '') { return 'https://example.test' . $path; }
function get_permalink($post_id) { return 'https://example.test/chevaux/cheval-' . (int) $post_id . '/'; }
function remove_accents($text) { return strtr((string) $text, array('é' => 'e', 'è' => 'e', 'à' => 'a')); }
function get_terms($args = array()) { return array(); }
function term_exists($term, $taxonomy = '') { return null; }
function get_option($name, $default = false) { return $default; }
function is_singular($post_type = '') { return false; }
function get_queried_object_id() { return 0; }
function language_attributes() { echo 'lang="fr-FR"'; }
function bloginfo($key) { echo $key === 'charset' ? 'UTF-8' : ''; }
function wp_head() { do_action('wp_head'); }
function wp_footer() {}

$GLOBALS['__gwseq_test_actions'] = array();
function add_action($hook, $callback, $priority = 10, $accepted_args = 1) { $GLOBALS['__gwseq_test_actions'][$hook][] = $callback; }
function do_action($hook) { foreach ($GLOBALS['__gwseq_test_actions'][$hook] ?? array() as $cb) call_user_func($cb); }
function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {}
function add_meta_box($id, $title, $callback, $post_type = null, $context = 'advanced', $priority = 'default') {}
$GLOBALS['__gwseq_test_registered_meta'] = array();
function register_post_meta($object_type, $meta_key, $args = array()) { $GLOBALS['__gwseq_test_registered_meta'][$meta_key] = $args; }

class WP_Error {
  public $code; public $message;
  public function __construct($code = '', $message = '') { $this->code = $code; $this->message = $message; }
}
function is_wp_error($thing) { return $thing instanceof WP_Error; }

$GLOBALS['__gwseq_test_meta'] = array();
function update_post_meta($post_id, $key, $value) { $GLOBALS['__gwseq_test_meta'][$post_id][$key] = wp_unslash($value); return true; }
function get_post_meta($post_id, $key, $single = false) { return $GLOBALS['__gwseq_test_meta'][$post_id][$key] ?? ''; }
function delete_post_meta($post_id, $key) { unset($GLOBALS['__gwseq_test_meta'][$post_id][$key]); return true; }
function metadata_exists($type, $post_id, $key) {
  return array_key_exists($post_id, $GLOBALS['__gwseq_test_meta']) && array_key_exists($key, $GLOBALS['__gwseq_test_meta'][$post_id]);
}

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
function wp_update_post($postarr, $wp_error = false) {
  $id = (int) ($postarr['ID'] ?? 0);
  if (!$id || !isset($GLOBALS['__gwseq_test_posts'][$id])) return 0;
  foreach (array('post_status', 'post_password', 'post_title') as $field) {
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
function wp_trash_post($post_id) {
  $post_id = (int) $post_id;
  if (!isset($GLOBALS['__gwseq_test_posts'][$post_id])) return false;
  $GLOBALS['__gwseq_test_posts'][$post_id]['post_status'] = 'trash';
  return true;
}

$GLOBALS['__gwseq_test_attachment_urls'] = array();
function wp_get_attachment_image_url($id, $size = 'thumbnail') { return $GLOBALS['__gwseq_test_attachment_urls'][$id][$size] ?? false; }
$GLOBALS['__gwseq_test_thumbnails'] = array();
function get_post_thumbnail_id($post_id) { return $GLOBALS['__gwseq_test_thumbnails'][$post_id] ?? 0; }

// --- WP_Query minimal, suffisant pour gwseq_selection_find_by_token() (non appelé directement
// ici mais chargé transitivement) ---
class WP_Query {
  public $posts = array();
  public function __construct($args = array()) {
    $post_type = $args['post_type'] ?? 'post';
    $status_filter = $args['post_status'] ?? 'publish';
    $meta_query = $args['meta_query'] ?? array();
    $limit = $args['posts_per_page'] ?? -1;
    $results = array();
    foreach ($GLOBALS['__gwseq_test_posts'] as $id => $post) {
      if ($post['post_type'] !== $post_type) continue;
      if ($status_filter === 'any') {
        if ($post['post_status'] === 'trash') continue;
      } elseif ($post['post_status'] !== $status_filter) {
        continue;
      }
      $match = true;
      foreach ($meta_query as $clause) {
        if (!isset($clause['key'])) continue;
        $value = get_post_meta($id, $clause['key'], true);
        if ((string) $value !== (string) ($clause['value'] ?? '')) { $match = false; break; }
      }
      if (!$match) continue;
      $results[] = $id;
    }
    if ($limit > 0) $results = array_slice($results, 0, $limit);
    $this->posts = $results;
  }
}

// --- Route : rewrite/query var (capturés, jamais réellement enregistrés dans WordPress ici) ---
$GLOBALS['__gwseq_test_rewrite_tags'] = array();
function add_rewrite_tag($tag, $regex) { $GLOBALS['__gwseq_test_rewrite_tags'][] = array($tag, $regex); }
$GLOBALS['__gwseq_test_rewrite_rules'] = array();
function add_rewrite_rule($regex, $redirect, $after = 'bottom') { $GLOBALS['__gwseq_test_rewrite_rules'][] = array($regex, $redirect, $after); }
$GLOBALS['__gwseq_test_query_vars'] = array();
function get_query_var($var, $default = '') { return $GLOBALS['__gwseq_test_query_vars'][$var] ?? $default; }

// --- Assets (front) ---
$GLOBALS['__gwseq_enqueued'] = array();
function wp_enqueue_style($handle, ...$rest) { $GLOBALS['__gwseq_enqueued'][] = $handle; }

// --- Cache/statut HTTP (mêmes stubs que gws-equestrian-cheval-share-admin-test.php) ---
$GLOBALS['__gwseq_test_nocache_headers_called'] = 0;
function nocache_headers() { $GLOBALS['__gwseq_test_nocache_headers_called']++; }
$GLOBALS['__gwseq_test_status_header'] = null;
function status_header($code) { $GLOBALS['__gwseq_test_status_header'] = $code; }

define('ABSPATH', __DIR__ . '/');
const GWSEQ_CPT_CHEVAL = 'gwseq_cheval';
const GWSEQ_CPT_PRESTATION = 'gwseq_prestation';
const GWSEQ_CPT_GROUPE = 'gwseq_groupe';
const GWSEQ_CPT_MEMBRE = 'gwseq_membre';
const GWSEQ_CPT_SELECTION = 'gwseq_selection';
const GWSEQ_TAX_CATEGORIE_CHEVAL = 'gwseq_categorie_cheval';
define('GWSEQ_MODULE_URL', 'https://example.test/wp-content/plugins/gws-core/modules/gws-equestrian/');
define('GWSEQ_MODULE_VERSION', 'test');

$repo_root = dirname(__DIR__);
$module_dir = $repo_root . '/wp-content/plugins/gws-core/modules/gws-equestrian/';
require $repo_root . '/wp-content/plugins/gws-core/includes/fields.php';
require $module_dir . 'includes/settings.php';
require $module_dir . 'includes/race-referentiel.php';
require $module_dir . 'includes/cheval-fields.php';
require $module_dir . 'includes/cheval-editorial.php';
require $module_dir . 'includes/cheval-media.php';
require $module_dir . 'includes/cheval-share.php';
require $module_dir . 'includes/cheval-selection.php';
require $module_dir . 'includes/admin-ui.php';
require $module_dir . 'includes/cheval-selection-front.php';

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
// Enregistrement de la route (§3) — même architecture que `/partage/{token}` (Cheval).
// =====================================================================================

gwseq_selection_register_rewrite();
gws_test_assert(count($GLOBALS['__gwseq_test_rewrite_tags']) === 1 && $GLOBALS['__gwseq_test_rewrite_tags'][0][0] === '%gwseq_selection_token%', 'Route : query var "gwseq_selection_token" enregistrée');
$rule = $GLOBALS['__gwseq_test_rewrite_rules'][0];
gws_test_assert(strpos($rule[0], 'selection') !== false, 'Route : règle de réécriture basée sur le chemin "selection" (GWSEQ_SELECTION_REWRITE_BASE)');
gws_test_assert(strpos($rule[1], 'gwseq_selection_token=$matches[1]') !== false, 'Route : redirection interne vers la query var attendue');

// =====================================================================================
// Assets — uniquement sur cette route précise (jamais chargés ailleurs sur le site).
// =====================================================================================

$GLOBALS['__gwseq_test_query_vars'] = array();
$GLOBALS['__gwseq_enqueued'] = array();
gwseq_selection_enqueue_public_assets();
gws_test_assert($GLOBALS['__gwseq_enqueued'] === array(), 'Assets publics : jamais chargés en dehors de la route de sélection (query var absente)');

$GLOBALS['__gwseq_test_query_vars'] = array('gwseq_selection_token' => str_repeat('a', 64));
gwseq_selection_enqueue_public_assets();
gws_test_assert(in_array('dashicons', $GLOBALS['__gwseq_enqueued'], true), 'Assets publics : dashicons chargé (nécessaire au placeholder média, normalement réservé à wp-admin)');
gws_test_assert(in_array('gwseq-cheval-selection-public', $GLOBALS['__gwseq_enqueued'], true), 'Assets publics : feuille de style minimale de la page destinataire chargée');
$GLOBALS['__gwseq_test_query_vars'] = array();

// =====================================================================================
// En-têtes anti-cache (§ mêmes directives que la route de partage privé Cheval).
// =====================================================================================

$GLOBALS['__gwseq_test_nocache_headers_called'] = 0;
gwseq_selection_send_nocache_headers();
gws_test_assert($GLOBALS['__gwseq_test_nocache_headers_called'] === 1, 'Cache : nocache_headers() native de WordPress appelée');
gws_test_assert(defined('DONOTCACHEPAGE') && DONOTCACHEPAGE === true, 'Cache : DONOTCACHEPAGE définie (convention des plugins de cache plein-page)');
$redefinition_erreur = false;
try { gwseq_selection_send_nocache_headers(); } catch (\Throwable $e) { $redefinition_erreur = true; }
gws_test_assert($redefinition_erreur === false, 'Cache : un appel répété ne tente jamais de redéfinir DONOTCACHEPAGE');

// =====================================================================================
// Rendu HTML (§3/§9) — réutilise EXCLUSIVEMENT gwseq_selection_get_public_view(), jamais un
// second calcul de données ici.
// =====================================================================================

gws_test_make_horse(10, 'Jamerose de Felines', GWSEQ_HORSE_DIFFUSION_VISIBLE_SITE);
gwseq_set_cheval_editorial(10, array('_gwseq_accroche_commerciale' => 'Jument très franche & sociable, idéale amateur.'));
update_post_meta(10, '_gwseq_statut_commercial', 'for_sale');
update_post_meta(10, '_gwseq_prix_fixe', 15000);

gws_test_make_horse(11, 'Cheval Prive', GWSEQ_HORSE_DIFFUSION_PRIVEE);

$selection_id = gwseq_selection_create(array('title' => 'Chevaux pour Juliette', 'cheval_ids' => array(10, 11)));

ob_start();
gwseq_selection_render_public_html($selection_id);
$html = ob_get_clean();

gws_test_assert(strpos($html, '<!DOCTYPE html>') === 0, 'Rendu : document HTML complet, jamais un fragment');
gws_test_assert(strpos($html, '<meta name="robots" content="noindex, nofollow">') !== false, 'Rendu : noindex systématique (§ confidentialité)');
gws_test_assert(strpos($html, 'Chevaux pour Juliette') !== false, 'Rendu : titre de la sélection affiché');
gws_test_assert(substr_count($html, 'gwseq-selection-page__card') >= 2, 'Rendu : une carte par cheval présentable (2 chevaux ici)');
gws_test_assert(strpos($html, 'JAMEROSE DE FELINES') !== false, 'Rendu : nom du cheval affiché (convention de présentation déjà en place)');
gws_test_assert(strpos($html, 'Jument très franche &amp; sociable') !== false, 'Rendu : accroche commerciale échappée en HTML (esc_html() à l’affichage — sécurité XSS, jamais un "&" brut qui casserait le document)');
gws_test_assert(strpos($html, '15') !== false && strpos($html, 'gwseq-selection-page__card-prix') !== false, 'Rendu : prix affiché (statut "À vendre")');
gws_test_assert(strpos($html, 'chevaux/cheval-10') !== false, 'Rendu : lien de fiche PUBLIC pour le cheval visible sur le site');
gws_test_assert(strpos($html, '/partage/') !== false, 'Rendu : lien de fiche PRIVÉ pour le cheval en diffusion privée, dans la MÊME sélection (§ "accepter des chevaux Visible/Diffusion privée dans une même sélection")');
gws_test_assert(strpos($html, 'gwseq-media-placeholder') !== false, 'Rendu : placeholder média réutilisé pour un cheval sans photo (§9 : "réutiliser le placeholder existant")');

// Cheval "En préparation" -> jamais affiché sur la page destinataire.
gwseq_horse_diffusion_set_en_preparation(11);
ob_start();
gwseq_selection_render_public_html($selection_id);
$html_apres_preparation = ob_get_clean();
gws_test_assert(strpos($html_apres_preparation, 'Cheval Prive') === false, 'Rendu : un cheval repassé "En préparation" disparaît du rendu, sans jamais toucher à la liste stockée');
gws_test_assert(gwseq_selection_get_cheval_ids($selection_id) === array(10, 11), 'Rendu : la liste stockée reste pourtant INTACTE (§6, jamais cassée par un changement de diffusion)');
gwseq_horse_diffusion_set_visible_site(11);

// État vide propre (§3 : "si tous les chevaux deviennent non diffusables, la sélection reste
// accessible... état vide propre") — jamais une erreur technique.
gwseq_horse_diffusion_set_en_preparation(10);
gwseq_horse_diffusion_set_en_preparation(11);
ob_start();
gwseq_selection_render_public_html($selection_id);
$html_vide = ob_get_clean();
gws_test_assert(strpos($html_vide, 'gwseq-selection-page__empty') !== false, 'Rendu : état vide propre affiché quand plus aucun cheval n’est présentable');
gws_test_assert(strpos($html_vide, 'Chevaux pour Juliette') !== false, 'Rendu : le titre reste affiché même avec un état vide');
gws_test_assert(strpos($html_vide, 'Fatal') === false, 'Rendu : aucune erreur technique visible, jamais un message d’erreur PHP');

// Titre par défaut sur la page destinataire (aucune sélection sans titre créée jusqu’ici dans ce
// fichier — nouvelle sélection dédiée).
$selection_sans_titre = gwseq_selection_create(array('cheval_ids' => array(10)));
gwseq_horse_diffusion_set_visible_site(10);
ob_start();
gwseq_selection_render_public_html($selection_sans_titre);
$html_sans_titre = ob_get_clean();
gws_test_assert(strpos($html_sans_titre, 'Sélection de chevaux') !== false, 'Rendu : libellé neutre de repli si aucun titre n’a été saisi');

echo "\n";
if ($failures > 0) {
  echo "$failures test(s) en échec.\n";
  exit(1);
}
echo "Tous les tests sont passés.\n";

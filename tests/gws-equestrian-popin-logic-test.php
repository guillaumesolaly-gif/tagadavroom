<?php
/**
 * Vérifie l'objet métier Pop-in (`gwseq_popin`, includes/popin-fields.php) : sanitation des quatre
 * sections (Contenu/Apparence/Déclenchement/Diffusion), bornes serveur, sauvegarde/rechargement,
 * rendu des meta boxes, colonnes de liste, sécurité de la sauvegarde, désactivation de Gutenberg,
 * point d'entrée AJAX de preview, et la fonction de rendu partagée preview/front
 * (gwseq_render_popin_markup()). Même méthodologie que le reste de cette suite.
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

$GLOBALS['__gwseq_test_meta'] = array();
function update_post_meta($post_id, $key, $value) { $GLOBALS['__gwseq_test_meta'][$post_id][$key] = $value; return true; }
function get_post_meta($post_id, $key, $single = false) { return $GLOBALS['__gwseq_test_meta'][$post_id][$key] ?? ''; }
function get_post_field($field, $post_id) { return $GLOBALS['__gwseq_test_post_fields'][$post_id][$field] ?? ''; }

$GLOBALS['__gwseq_test_posts'] = array();
$GLOBALS['__gwseq_test_titles'] = array();
$GLOBALS['__gwseq_test_post_status'] = array();
function get_post_type($post_id) { return $GLOBALS['__gwseq_test_posts'][$post_id] ?? false; }
function get_the_title($post) { $id = is_object($post) ? $post->ID : $post; return $GLOBALS['__gwseq_test_titles'][$id] ?? ''; }
function get_posts($args = array()) {
  $post_type = $args['post_type'] ?? 'post';
  $results = array();
  foreach ($GLOBALS['__gwseq_test_posts'] as $id => $type) {
    if ($type !== $post_type) continue;
    if (($GLOBALS['__gwseq_test_post_status'][$id] ?? 'publish') === 'trash') continue;
    $results[] = (object) array('ID' => $id);
  }
  return $results;
}

$GLOBALS['__gwseq_test_registered_meta'] = array();
function register_post_meta($object_type, $meta_key, $args = array()) { $GLOBALS['__gwseq_test_registered_meta'][$meta_key] = $args; }
function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {}
function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {}
$GLOBALS['__gwseq_test_meta_boxes'] = array();
function add_meta_box($id, $title, $callback, $post_type = null, $context = 'advanced', $priority = 'default') { $GLOBALS['__gwseq_test_meta_boxes'][] = $id; }

$GLOBALS['__gwseq_test_security'] = array('nonce_valid' => true, 'can_edit' => true, 'is_revision' => false);
function wp_verify_nonce($nonce, $action) { return $GLOBALS['__gwseq_test_security']['nonce_valid']; }
function current_user_can($cap, $post_id = null) { return $GLOBALS['__gwseq_test_security']['can_edit']; }
function wp_is_post_revision($post_id) { return $GLOBALS['__gwseq_test_security']['is_revision']; }

$GLOBALS['__gwseq_test_json_response'] = null;
function wp_send_json_success($data = null) { $GLOBALS['__gwseq_test_json_response'] = array('success' => true, 'data' => $data); throw new Gws_Test_Json_Exit(); }
function wp_send_json_error($data = null, $status = null) { $GLOBALS['__gwseq_test_json_response'] = array('success' => false, 'data' => $data, 'status' => $status); throw new Gws_Test_Json_Exit(); }
class Gws_Test_Json_Exit extends Exception {}

$GLOBALS['__gwseq_test_screen'] = null;
function get_current_screen() { return $GLOBALS['__gwseq_test_screen']; }
$GLOBALS['__gwseq_enqueued'] = array();
function wp_enqueue_script($handle, ...$rest) { $GLOBALS['__gwseq_enqueued'][] = $handle; }
function wp_enqueue_style($handle, ...$rest) { $GLOBALS['__gwseq_enqueued'][] = $handle; }
function wp_enqueue_media() { $GLOBALS['__gwseq_enqueued'][] = 'media'; }
function wp_localize_script($handle, $name, $data) { $GLOBALS['__gwseq_test_localized'][$handle][$name] = $data; }
function admin_url($path) { return 'https://example.test/wp-admin/' . $path; }
function wp_create_nonce($action) { return 'nonce-' . $action; }

define('ABSPATH', __DIR__ . '/');
define('GWS_CORE_DIR', dirname(__DIR__) . '/wp-content/plugins/gws-core/');
define('GWS_CORE_URL', 'https://example.test/wp-content/plugins/gws-core/');
const GWSEQ_CPT_POPIN = 'gwseq_popin';
const GWSEQ_CPT_CHEVAL = 'gwseq_cheval';
const GWSEQ_CPT_PRESTATION = 'gwseq_prestation';
define('GWSEQ_MODULE_URL', GWS_CORE_URL . 'modules/gws-equestrian/');
define('GWSEQ_MODULE_VERSION', '0.0.0-test');

$module_dir = GWS_CORE_DIR . 'modules/gws-equestrian/';
require $module_dir . 'includes/campagnes-shared.php';
require $module_dir . 'includes/popin-fields.php';
gwseq_register_popin_meta();

// =====================================================================================
// Sanitation — Contenu
// =====================================================================================

$GLOBALS['__gwseq_test_image_attachments'] = array(5);
$contenu_full = gwseq_sanitize_popin_contenu_input(array(
  '_gwseq_popin_titre' => 'Bienvenue !',
  '_gwseq_popin_texte' => '<strong>Texte</strong> <script>x()</script>',
  '_gwseq_popin_cta_active' => '1',
  '_gwseq_popin_cta_libelle' => 'Découvrir',
  '_gwseq_popin_cta_url' => 'https://example.test/offre',
  '_gwseq_popin_image_id' => '5',
));
gws_test_assert($contenu_full['titre'] === 'Bienvenue !', 'Contenu : titre affiché conservé');
gws_test_assert(strpos($contenu_full['texte'], '<script>') === false, 'Contenu : texte jamais du HTML arbitraire');
gws_test_assert($contenu_full['cta_active'] === '1' && $contenu_full['cta_libelle'] === 'Découvrir' && $contenu_full['cta_url'] === 'https://example.test/offre', 'Contenu : CTA complet conservé');
gws_test_assert($contenu_full['image_id'] === 5, 'Contenu : image de contenu conservée (attachment_id valide)');

$contenu_empty = gwseq_sanitize_popin_contenu_input(array());
gws_test_assert($contenu_empty['titre'] === '' && $contenu_empty['image_id'] === 0, 'Contenu : payload vide -> tout vide, aucune erreur (tous les champs sont facultatifs)');

$contenu_bad_image = gwseq_sanitize_popin_contenu_input(array('_gwseq_popin_image_id' => '999'));
gws_test_assert($contenu_bad_image['image_id'] === 0, 'Contenu : un ID qui n\'est pas une image valide -> jamais conservé');

// =====================================================================================
// Sanitation — Apparence (style/couleurs/image de fond/taille)
// =====================================================================================

$GLOBALS['__gwseq_test_image_attachments'] = array(5, 8);
$apparence_custom = gwseq_sanitize_popin_apparence_input(array(
  '_gwseq_popin_style_mode' => 'custom',
  '_gwseq_popin_taille' => 'large',
  '_gwseq_popin_couleur_fond' => '#ffffff',
  '_gwseq_popin_couleur_texte' => '#111111',
  '_gwseq_popin_couleur_cta' => '#1d4ed8',
  '_gwseq_popin_couleur_cta_texte' => '#ffffff',
  '_gwseq_popin_image_fond_id' => '8',
));
gws_test_assert($apparence_custom['style_mode'] === 'custom', 'Apparence : mode "Personnaliser" conservé');
gws_test_assert($apparence_custom['taille'] === 'large', 'Apparence : taille "Large" conservée');
gws_test_assert($apparence_custom['couleur_fond'] === '#ffffff' && $apparence_custom['couleur_cta'] === '#1d4ed8', 'Apparence : couleurs personnalisées conservées en mode "Personnaliser"');
gws_test_assert($apparence_custom['image_fond_id'] === 8, 'Apparence : image de fond conservée en mode "Personnaliser" (distincte de l\'image de contenu)');

// --- Repasser en "Style du site" nettoie TOUJOURS les champs personnalisés, même si le payload
// les soumet encore (le serveur reste l'autorité, même discipline que Membre/Langues) ---
$apparence_site = gwseq_sanitize_popin_apparence_input(array(
  '_gwseq_popin_style_mode' => 'site',
  '_gwseq_popin_couleur_fond' => '#ffffff',
  '_gwseq_popin_image_fond_id' => '8',
));
gws_test_assert(
  $apparence_site['couleur_fond'] === '' && $apparence_site['image_fond_id'] === 0,
  'Apparence : "Style du site" nettoie systématiquement les couleurs ET l\'image de fond, même si d\'anciennes valeurs sont encore soumises'
);

$apparence_default = gwseq_sanitize_popin_apparence_input(array());
gws_test_assert($apparence_default['style_mode'] === 'site' && $apparence_default['taille'] === 'standard', 'Apparence : "Style du site" et taille "Standard" par défaut (§D)');

$apparence_taille_invalide = gwseq_sanitize_popin_apparence_input(array('_gwseq_popin_taille' => 'geante'));
gws_test_assert($apparence_taille_invalide['taille'] === 'standard', 'Apparence : taille invalide -> repli sur "Standard"');

// =====================================================================================
// Sanitation — Déclenchement + Fréquence, bornes serveur (§E)
// =====================================================================================

gws_test_assert(gwseq_sanitize_popin_declenchement_input(array())['mode'] === 'immediate', 'Déclenchement : "Immédiatement" par défaut');

$declenchement_delay = gwseq_sanitize_popin_declenchement_input(array('_gwseq_popin_declenchement_mode' => 'delay', '_gwseq_popin_delai_secondes' => '12'));
gws_test_assert($declenchement_delay['mode'] === 'delay' && $declenchement_delay['delai_secondes'] === 12, 'Déclenchement : "Après X secondes" avec la valeur soumise');
gws_test_assert($declenchement_delay['scroll_pourcentage'] === 0, 'Déclenchement : le pourcentage de scroll n\'est jamais conservé pour le mode "secondes" (jamais une valeur orpheline)');

// --- Bornes serveur : jamais une valeur absurde, quel que soit ce qui est soumis ---
gws_test_assert(gwseq_sanitize_popin_declenchement_input(array('_gwseq_popin_declenchement_mode' => 'delay', '_gwseq_popin_delai_secondes' => '99999'))['delai_secondes'] === GWSEQ_POPIN_DELAI_SECONDES_MAX, 'Déclenchement : délai trop grand -> plafonné à la borne maximale');
gws_test_assert(gwseq_sanitize_popin_declenchement_input(array('_gwseq_popin_declenchement_mode' => 'delay', '_gwseq_popin_delai_secondes' => '-5'))['delai_secondes'] === GWSEQ_POPIN_DELAI_SECONDES_MIN, 'Déclenchement : délai négatif -> plancher à la borne minimale');
gws_test_assert(gwseq_sanitize_popin_declenchement_input(array('_gwseq_popin_declenchement_mode' => 'delay', '_gwseq_popin_delai_secondes' => 'abc'))['delai_secondes'] === 5, 'Déclenchement : délai non numérique -> valeur par défaut raisonnable (5 s), jamais une erreur');

$declenchement_scroll = gwseq_sanitize_popin_declenchement_input(array('_gwseq_popin_declenchement_mode' => 'scroll', '_gwseq_popin_scroll_pourcentage' => '75'));
gws_test_assert($declenchement_scroll['mode'] === 'scroll' && $declenchement_scroll['scroll_pourcentage'] === 75, 'Déclenchement : "Après X % de scroll" avec la valeur soumise');
gws_test_assert(gwseq_sanitize_popin_declenchement_input(array('_gwseq_popin_declenchement_mode' => 'scroll', '_gwseq_popin_scroll_pourcentage' => '150'))['scroll_pourcentage'] === GWSEQ_POPIN_SCROLL_POURCENTAGE_MAX, 'Déclenchement : scroll > 100% -> plafonné à 100');

$declenchement_exit = gwseq_sanitize_popin_declenchement_input(array('_gwseq_popin_declenchement_mode' => 'exit_intent'));
gws_test_assert($declenchement_exit['mode'] === 'exit_intent', '"À l\'intention de sortie" conservé');

$declenchement_invalide = gwseq_sanitize_popin_declenchement_input(array('_gwseq_popin_declenchement_mode' => 'n-importe-quoi'));
gws_test_assert($declenchement_invalide['mode'] === 'immediate', 'Déclenchement : mode invalide -> repli sur "Immédiatement"');

gws_test_assert(gwseq_sanitize_popin_declenchement_input(array())['frequence_mode'] === 'every_visit', 'Fréquence : "À chaque visite" par défaut');
$frequence_days = gwseq_sanitize_popin_declenchement_input(array('_gwseq_popin_frequence_mode' => 'days', '_gwseq_popin_frequence_jours' => '14'));
gws_test_assert($frequence_days['frequence_mode'] === 'days' && $frequence_days['frequence_jours'] === 14, 'Fréquence : "Une fois tous les X jours" avec la valeur soumise');
gws_test_assert(gwseq_sanitize_popin_declenchement_input(array('_gwseq_popin_frequence_mode' => 'session'))['frequence_jours'] === 0, 'Fréquence : X jours jamais conservé pour un autre mode (jamais une valeur orpheline)');
gws_test_assert(gwseq_sanitize_popin_declenchement_input(array('_gwseq_popin_frequence_mode' => 'days', '_gwseq_popin_frequence_jours' => '900'))['frequence_jours'] === GWSEQ_POPIN_FREQUENCE_JOURS_MAX, 'Fréquence : X jours trop grand -> plafonné');

// =====================================================================================
// Sanitation — Diffusion (statut/dates/ciblage)
// =====================================================================================

$diffusion_default = gwseq_sanitize_popin_diffusion_input(array());
gws_test_assert($diffusion_default['statut'] === 'inactive', 'Diffusion : statut "Inactive" par défaut (repli prudent, jamais actif par omission)');
gws_test_assert($diffusion_default['debut_ts'] === 0 && $diffusion_default['fin_ts'] === 0, 'Diffusion : sans dates -> aucune limite');
gws_test_assert($diffusion_default['ciblage_mode'] === 'all', 'Diffusion : ciblage "Tout le site" par défaut');

$diffusion_active = gwseq_sanitize_popin_diffusion_input(array('_gwseq_popin_statut' => 'active'));
gws_test_assert($diffusion_active['statut'] === 'active', 'Diffusion : statut "Active" bien pris en compte quand soumis explicitement');

gws_test_make_post_stub(100, 'page');
$diffusion_ciblage = gwseq_sanitize_popin_diffusion_input(array(
  '_gwseq_popin_ciblage_mode' => 'include',
  '_gwseq_popin_ciblage_cibles' => array('page:100'),
));
gws_test_assert($diffusion_ciblage['ciblage_mode'] === 'include' && $diffusion_ciblage['ciblage_cibles'] === array('page:100'), 'Diffusion : ciblage "Certains contenus" correctement délégué à la fonction partagée');

function gws_test_make_post_stub($id, $post_type) { $GLOBALS['__gwseq_test_posts'][$id] = $post_type; }

// =====================================================================================
// Sauvegarde/rechargement complet
// =====================================================================================

$_POST = array(GWSEQ_POPIN_NONCE_FIELD => 'stub-nonce');
gwseq_save_popin_meta(200);
gws_test_assert(gwseq_get_popin_contenu(200)['titre'] === '', 'Sauvegarde : pop-in minimale (aucun champ soumis) -> tout vide, aucune erreur');

$_POST = array(
  GWSEQ_POPIN_NONCE_FIELD => 'stub-nonce',
  '_gwseq_popin_titre' => 'Offre spéciale',
  '_gwseq_popin_texte' => '<strong>Profitez-en</strong>',
  '_gwseq_popin_cta_active' => '1',
  '_gwseq_popin_cta_libelle' => 'Je réserve',
  '_gwseq_popin_cta_url' => 'https://example.test/reservation',
  '_gwseq_popin_image_id' => '5',
  '_gwseq_popin_style_mode' => 'custom',
  '_gwseq_popin_taille' => 'compact',
  '_gwseq_popin_couleur_fond' => '#fef3c7',
  '_gwseq_popin_couleur_texte' => '#78350f',
  '_gwseq_popin_couleur_cta' => '#d97706',
  '_gwseq_popin_couleur_cta_texte' => '#ffffff',
  '_gwseq_popin_image_fond_id' => '8',
  '_gwseq_popin_declenchement_mode' => 'scroll',
  '_gwseq_popin_scroll_pourcentage' => '60',
  '_gwseq_popin_frequence_mode' => 'days',
  '_gwseq_popin_frequence_jours' => '10',
  '_gwseq_popin_statut' => 'active',
  '_gwseq_popin_ciblage_mode' => 'include',
  '_gwseq_popin_ciblage_cibles' => array('page:100'),
);
gwseq_save_popin_meta(201);

$reloaded_contenu = gwseq_get_popin_contenu(201);
gws_test_assert($reloaded_contenu['titre'] === 'Offre spéciale', 'Sauvegarde/rechargement : titre affiché');
gws_test_assert(strpos($reloaded_contenu['texte'], '<strong>Profitez-en</strong>') !== false, 'Sauvegarde/rechargement : texte enrichi');
gws_test_assert($reloaded_contenu['cta_url'] === 'https://example.test/reservation', 'Sauvegarde/rechargement : URL du CTA');
gws_test_assert($reloaded_contenu['image_id'] === 5, 'Sauvegarde/rechargement : image de contenu');

$reloaded_apparence = gwseq_get_popin_apparence(201);
gws_test_assert($reloaded_apparence['style_mode'] === 'custom' && $reloaded_apparence['taille'] === 'compact', 'Sauvegarde/rechargement : style personnalisé + taille');
gws_test_assert($reloaded_apparence['couleur_fond'] === '#fef3c7' && $reloaded_apparence['image_fond_id'] === 8, 'Sauvegarde/rechargement : couleur de fond + image de fond');

$reloaded_declenchement = gwseq_get_popin_declenchement(201);
gws_test_assert($reloaded_declenchement['mode'] === 'scroll' && $reloaded_declenchement['scroll_pourcentage'] === 60, 'Sauvegarde/rechargement : déclenchement scroll');
gws_test_assert($reloaded_declenchement['frequence_mode'] === 'days' && $reloaded_declenchement['frequence_jours'] === 10, 'Sauvegarde/rechargement : fréquence X jours');

$reloaded_diffusion = gwseq_get_popin_diffusion(201);
gws_test_assert($reloaded_diffusion['statut'] === 'active', 'Sauvegarde/rechargement : statut actif');
gws_test_assert($reloaded_diffusion['ciblage_mode'] === 'include' && $reloaded_diffusion['ciblage_cibles'] === array('page:100'), 'Sauvegarde/rechargement : ciblage');

// =====================================================================================
// Sécurité de la sauvegarde
// =====================================================================================

$_POST = array(GWSEQ_POPIN_NONCE_FIELD => 'stub-nonce', '_gwseq_popin_titre' => 'Ne pas enregistrer');
$GLOBALS['__gwseq_test_security']['nonce_valid'] = false;
gwseq_save_popin_meta(300);
gws_test_assert(gwseq_get_popin_contenu(300)['titre'] === '', 'Sécurité : nonce invalide -> aucune sauvegarde');
$GLOBALS['__gwseq_test_security']['nonce_valid'] = true;

$GLOBALS['__gwseq_test_security']['can_edit'] = false;
gwseq_save_popin_meta(300);
gws_test_assert(gwseq_get_popin_contenu(300)['titre'] === '', 'Sécurité : permissions insuffisantes -> aucune sauvegarde');
$GLOBALS['__gwseq_test_security']['can_edit'] = true;

$GLOBALS['__gwseq_test_security']['is_revision'] = true;
gwseq_save_popin_meta(300);
gws_test_assert(gwseq_get_popin_contenu(300)['titre'] === '', 'Sécurité : révision -> aucune sauvegarde');
$GLOBALS['__gwseq_test_security']['is_revision'] = false;

gwseq_save_popin_meta(300);
gws_test_assert(gwseq_get_popin_contenu(300)['titre'] === 'Ne pas enregistrer', 'Sécurité : nonce + permissions + non-révision -> sauvegarde réelle effectuée');
$_POST = array();

// =====================================================================================
// Rendu partagé preview/front — gwseq_render_popin_markup()
// =====================================================================================

$config = gwseq_get_popin_config(201);
gws_test_assert($config['image_url'] === '', 'Config : aucune URL renvoyée par wp_get_attachment_image_url() (stub sans URL configurée) -> chaîne vide, jamais une erreur');

$GLOBALS['__gwseq_test_attachment_urls'][5] = array('large' => 'https://example.test/image-contenu.jpg');
$GLOBALS['__gwseq_test_attachment_urls'][8] = array('large' => 'https://example.test/image-fond.jpg');
$config = gwseq_get_popin_config(201);
$html = gwseq_render_popin_markup($config);
gws_test_assert(strpos($html, 'gwseq-popin--compact') !== false, 'Rendu : classe de taille appliquée');
gws_test_assert(strpos($html, 'gwseq-popin--custom') !== false, 'Rendu : classe de style personnalisé appliquée');
gws_test_assert(strpos($html, '--gws-popin-bg:#fef3c7') !== false, 'Rendu : couleur de fond injectée en variable CSS personnalisée');
// Le style inline passe par esc_attr() (ENT_QUOTES) : les apostrophes du url('...') CSS sont donc
// encodées en entités HTML dans le HTML source (un navigateur les redécode avant d'interpréter le
// style, donc le rendu réel est correct) — on vérifie ici la forme réellement produite.
gws_test_assert(strpos($html, '--gws-popin-bg-image:url(&#039;https://example.test/image-fond.jpg&#039;)') !== false, 'Rendu : image de FOND injectée en variable CSS (distincte de l\'image de contenu)');
gws_test_assert(strpos($html, 'src="https://example.test/image-contenu.jpg"') !== false, 'Rendu : image de CONTENU affichée comme <img>, distincte de l\'image de fond');
gws_test_assert(strpos($html, 'Offre spéciale') !== false, 'Rendu : titre affiché');
gws_test_assert(strpos($html, 'Je réserve') !== false && strpos($html, 'https://example.test/reservation') !== false, 'Rendu : CTA affiché avec son URL');
gws_test_assert(strpos($html, 'gwseq-popin__close') !== false, 'Rendu : la pop-in est TOUJOURS fermable (bouton de fermeture toujours présent, §D)');

// --- CTA inactif -> jamais affiché, même si libellé/URL sont renseignés ---
$config_cta_off = $config;
$config_cta_off['cta']['active'] = '';
gws_test_assert(strpos(gwseq_render_popin_markup($config_cta_off), 'gwseq-popin__cta') === false, 'Rendu : CTA désactivé -> jamais affiché même si libellé/URL existent');

// --- Configuration par défaut minimale -> rendu propre, jamais d'erreur ---
$html_defaults = gwseq_render_popin_markup(array());
gws_test_assert(strpos($html_defaults, 'gwseq-popin--standard') !== false, 'Rendu : configuration vide -> taille "Standard" par défaut, rendu propre');
gws_test_assert(strpos($html_defaults, 'gwseq-popin--custom') === false, 'Rendu : configuration vide -> style du site par défaut (jamais de classe "custom")');

// --- Attributs supplémentaires (comportement front) fusionnés sans reconstruire le HTML ---
$html_with_attrs = gwseq_render_popin_markup($config, array('data-gwseq-popin-id' => 201, 'data-gwseq-declenchement' => 'scroll'));
gws_test_assert(strpos($html_with_attrs, 'data-gwseq-popin-id="201"') !== false && strpos($html_with_attrs, 'data-gwseq-declenchement="scroll"') !== false, 'Rendu : attributs de comportement front fusionnés dans le même balisage (une seule fonction de rendu pour preview ET front)');

// =====================================================================================
// AJAX preview (§J) : état de formulaire -> mêmes sanitizers -> même fonction de rendu
// =====================================================================================

$_POST = array(
  'nonce' => 'valid',
  '_gwseq_popin_titre' => 'Aperçu en direct',
  '_gwseq_popin_style_mode' => 'site',
  '_gwseq_popin_taille' => 'standard',
);
$json = null;
try { gwseq_ajax_preview_popin(); } catch (Gws_Test_Json_Exit $e) { $json = $GLOBALS['__gwseq_test_json_response']; }
gws_test_assert($json !== null && $json['success'] === true, 'Preview AJAX : réponse de succès');
gws_test_assert(strpos($json['data']['html'], 'Aperçu en direct') !== false, 'Preview AJAX : le HTML retourné reflète l\'état de formulaire soumis, via LA MÊME fonction de rendu que le front');

// --- Sécurité du point d'entrée AJAX ---
$GLOBALS['__gwseq_test_security']['nonce_valid'] = false;
$json = null;
try { gwseq_ajax_preview_popin(); } catch (Gws_Test_Json_Exit $e) { $json = $GLOBALS['__gwseq_test_json_response']; }
gws_test_assert($json !== null && $json['success'] === false, 'Preview AJAX : nonce invalide -> erreur, jamais de rendu');
$GLOBALS['__gwseq_test_security']['nonce_valid'] = true;

$GLOBALS['__gwseq_test_security']['can_edit'] = false;
$json = null;
try { gwseq_ajax_preview_popin(); } catch (Gws_Test_Json_Exit $e) { $json = $GLOBALS['__gwseq_test_json_response']; }
gws_test_assert($json !== null && $json['success'] === false, 'Preview AJAX : permissions insuffisantes -> erreur');
$GLOBALS['__gwseq_test_security']['can_edit'] = true;
$_POST = array();

// =====================================================================================
// Meta boxes : quatre sections + aperçu
// =====================================================================================

gwseq_add_popin_meta_boxes();
gws_test_assert(
  $GLOBALS['__gwseq_test_meta_boxes'] === array('gwseq-popin-contenu', 'gwseq-popin-apparence', 'gwseq-popin-declenchement', 'gwseq-popin-diffusion', 'gwseq-popin-preview'),
  'Meta boxes : quatre sections (Contenu/Apparence/Déclenchement/Diffusion) + le panneau d\'aperçu, dans cet ordre'
);

$post_201 = (object) array('ID' => 201);
ob_start();
gwseq_render_popin_contenu_box($post_201);
$contenu_html = ob_get_clean();
gws_test_assert(strpos($contenu_html, 'name="_gwseq_popin_titre"') !== false, 'Rendu meta box Contenu : champ Titre réellement rendu');
gws_test_assert(strpos($contenu_html, 'jamais affiché sur le site') !== false, 'Rendu meta box Contenu : rappel clair que le nom interne n\'est jamais public (§D)');
gws_test_assert(preg_match('/data-gwseq-campagne-fields="cta"[^>]*style="[^"]*"/', $contenu_html) === 1, 'Rendu meta box Contenu : bloc CTA présent avec son attribut de visibilité conditionnelle');

ob_start();
gwseq_render_popin_apparence_box($post_201);
$apparence_html = ob_get_clean();
gws_test_assert(strpos($apparence_html, 'name="_gwseq_popin_style_mode"') !== false, 'Rendu meta box Apparence : sélecteur de style rendu');
gws_test_assert(strpos($apparence_html, 'Distincte de l’image de contenu') !== false, 'Rendu meta box Apparence : distinction explicite image de contenu / image de fond (§D)');
gws_test_assert(strpos($apparence_html, 'toujours centrée') !== false, 'Rendu meta box Apparence : rappel que la pop-in est toujours centrée');

ob_start();
gwseq_render_popin_declenchement_box($post_201);
$declenchement_html = ob_get_clean();
gws_test_assert(strpos($declenchement_html, 'intention de sortie est disponible uniquement sur ordinateur') !== false, 'Rendu meta box Déclenchement : aide claire sur la limite desktop de l\'intention de sortie (§E)');
gws_test_assert(strpos($declenchement_html, 'compte comme une exposition') !== false, 'Rendu meta box Déclenchement : rappel que fermer la pop-in compte comme une exposition (§F)');

// =====================================================================================
// Colonnes de liste (§Q) : Nom | État | Période | Ciblage | Déclenchement | Ordre
// =====================================================================================

$native_columns = array('cb' => '<input type="checkbox">', 'title' => 'Titre', 'date' => 'Date');
$columns = gwseq_popin_admin_columns($native_columns);
gws_test_assert(
  array_keys($columns) === array('cb', 'title', 'gwseq_campagne_etat', 'gwseq_campagne_periode', 'gwseq_campagne_ciblage', 'gwseq_popin_declenchement', 'gwseq_campagne_ordre'),
  'Colonnes : ordre exact Nom | État | Période | Ciblage | Déclenchement | Ordre (cb natif en premier)'
);
gws_test_assert($columns['title'] === 'Nom', 'Colonnes : "title" relabellé "Nom"');
gws_test_assert(!array_key_exists('date', $columns), 'Colonnes : colonne native "Date" retirée');

ob_start();
gwseq_popin_admin_column_content('gwseq_campagne_etat', 201);
gws_test_assert(ob_get_clean() === 'Active', 'Colonne État : libellé résolu depuis le statut');

ob_start();
gwseq_popin_admin_column_content('gwseq_popin_declenchement', 201);
gws_test_assert(ob_get_clean() === 'Après X % de scroll', 'Colonne Déclenchement : libellé résolu depuis le mode');

$GLOBALS['__gwseq_test_post_fields'][201]['menu_order'] = 2;
ob_start();
gwseq_popin_admin_column_content('gwseq_campagne_ordre', 201);
gws_test_assert(ob_get_clean() === '2', 'Colonne Ordre : menu_order natif affiché (réutilisation du mécanisme déjà existant)');

// =====================================================================================
// Éditeur par blocs désactivé (pas de Gutenberg), placeholder du titre natif
// =====================================================================================

gws_test_assert(gwseq_disable_block_editor_for_popin(true, GWSEQ_CPT_POPIN) === false, 'Éditeur par blocs : désactivé pour gwseq_popin (fiche structurée, pas de page builder)');
gws_test_assert(gwseq_disable_block_editor_for_popin(true, 'post') === true, 'Éditeur par blocs : inchangé pour un autre post type (Actualités)');

$placeholder = gwseq_popin_title_placeholder('Ajouter un titre', (object) array('post_type' => GWSEQ_CPT_POPIN));
gws_test_assert(strpos($placeholder, 'jamais affiché sur le site') !== false, 'Titre natif : placeholder rappelle clairement que le nom interne n\'est jamais public');
gws_test_assert(gwseq_popin_title_placeholder('Ajouter un titre', (object) array('post_type' => 'page')) === 'Ajouter un titre', 'Titre natif : placeholder inchangé pour un autre post type');

// =====================================================================================
// Meta enregistrées : jamais exposées en REST
// =====================================================================================

foreach ($GLOBALS['__gwseq_test_registered_meta'] as $meta_key => $args) {
  if (strpos($meta_key, '_gwseq_popin_') !== 0) continue;
  gws_test_assert(($args['show_in_rest'] ?? null) === false, "Meta enregistrées : $meta_key jamais exposée en REST");
}
gws_test_assert(($GLOBALS['__gwseq_test_registered_meta']['_gwseq_popin_ciblage_cibles']['type'] ?? null) === 'array', 'Meta enregistrées : ciblage_cibles déclarée de type \'array\' (sélection multiple)');

echo ($failures === 0 ? 'Tous les tests sont passés.' : "$failures test(s) en échec.") . "\n";
exit($failures === 0 ? 0 : 1);

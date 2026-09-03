<?php
/**
 * Vérifie l'objet métier Sticky bar (`gwseq_sticky_bar`, includes/sticky-bar-fields.php) :
 * sanitation des trois sections (Contenu/Apparence/Diffusion — pas de Déclenchement, §G), absence
 * d'image de fond (contrairement à Pop-in), fermeture conditionnelle, position haut/bas,
 * sauvegarde/rechargement, rendu des meta boxes, colonnes de liste, sécurité de la sauvegarde,
 * désactivation de Gutenberg, point d'entrée AJAX de preview, et la fonction de rendu partagée
 * preview/front (gwseq_render_sticky_bar_markup()). Même méthodologie que le reste de cette suite.
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

function gws_core_field_sanitize($type, $raw_value) {
  switch ($type) {
    case 'url': return esc_url_raw(wp_unslash($raw_value));
    case 'checkbox': return $raw_value ? '1' : '';
    case 'text':
    default: return sanitize_text_field(wp_unslash($raw_value));
  }
}

$GLOBALS['__gwseq_test_meta'] = array();
function update_post_meta($post_id, $key, $value) { $GLOBALS['__gwseq_test_meta'][$post_id][$key] = $value; return true; }
function get_post_meta($post_id, $key, $single = false) { return $GLOBALS['__gwseq_test_meta'][$post_id][$key] ?? ''; }
function get_post_field($field, $post_id) { return $GLOBALS['__gwseq_test_post_fields'][$post_id][$field] ?? ''; }

$GLOBALS['__gwseq_test_posts'] = array();
function get_post_type($post_id) { return $GLOBALS['__gwseq_test_posts'][$post_id] ?? false; }
function gws_test_make_post_stub($id, $post_type) { $GLOBALS['__gwseq_test_posts'][$id] = $post_type; }

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
function wp_localize_script($handle, $name, $data) { $GLOBALS['__gwseq_test_localized'][$handle][$name] = $data; }
function admin_url($path) { return 'https://example.test/wp-admin/' . $path; }
function wp_create_nonce($action) { return 'nonce-' . $action; }

define('ABSPATH', __DIR__ . '/');
define('GWS_CORE_DIR', dirname(__DIR__) . '/wp-content/plugins/gws-core/');
define('GWS_CORE_URL', 'https://example.test/wp-content/plugins/gws-core/');
const GWSEQ_CPT_STICKY_BAR = 'gwseq_sticky_bar';
const GWSEQ_CPT_CHEVAL = 'gwseq_cheval';
const GWSEQ_CPT_PRESTATION = 'gwseq_prestation';
define('GWSEQ_MODULE_URL', GWS_CORE_URL . 'modules/gws-equestrian/');
define('GWSEQ_MODULE_VERSION', '0.0.0-test');

$module_dir = GWS_CORE_DIR . 'modules/gws-equestrian/';
require $module_dir . 'includes/campagnes-shared.php';
require $module_dir . 'includes/sticky-bar-fields.php';
gwseq_register_sticky_bar_meta();

// =====================================================================================
// Sanitation — Contenu (texte court en texte simple, jamais de HTML enrichi ici)
// =====================================================================================

$contenu_full = gwseq_sanitize_sticky_bar_contenu_input(array(
  '_gwseq_sticky_bar_texte' => 'Portes ouvertes <strong>ce week-end</strong>',
  '_gwseq_sticky_bar_cta_active' => '1',
  '_gwseq_sticky_bar_cta_libelle' => 'Je m’inscris',
  '_gwseq_sticky_bar_cta_url' => 'https://example.test/inscription',
));
gws_test_assert(strpos($contenu_full['texte'], '<strong>') === false, 'Contenu : texte court -> texte simple, jamais de balises (distinct du texte enrichi de Pop-in)');
gws_test_assert($contenu_full['cta_active'] === '1' && $contenu_full['cta_libelle'] === 'Je m’inscris' && $contenu_full['cta_url'] === 'https://example.test/inscription', 'Contenu : CTA complet conservé');

$contenu_empty = gwseq_sanitize_sticky_bar_contenu_input(array());
gws_test_assert($contenu_empty['texte'] === '' && $contenu_empty['cta_active'] === '', 'Contenu : payload vide -> tout vide, aucune erreur');

// =====================================================================================
// Sanitation — Apparence (style/couleurs/position/fermable, AUCUNE image de fond)
// =====================================================================================

$apparence_custom = gwseq_sanitize_sticky_bar_apparence_input(array(
  '_gwseq_sticky_bar_style_mode' => 'custom',
  '_gwseq_sticky_bar_position' => 'bottom',
  '_gwseq_sticky_bar_fermable' => '1',
  '_gwseq_sticky_bar_couleur_fond' => '#1a1a1a',
  '_gwseq_sticky_bar_couleur_texte' => '#ffffff',
  '_gwseq_sticky_bar_couleur_cta' => '#1d4ed8',
  '_gwseq_sticky_bar_couleur_cta_texte' => '#ffffff',
));
gws_test_assert($apparence_custom['style_mode'] === 'custom', 'Apparence : mode "Personnaliser" conservé');
gws_test_assert($apparence_custom['position'] === 'bottom', 'Apparence : position "Bas" conservée');
gws_test_assert($apparence_custom['fermable'] === '1', 'Apparence : "fermable" conservé quand coché');
gws_test_assert($apparence_custom['couleur_fond'] === '#1a1a1a' && $apparence_custom['couleur_cta'] === '#1d4ed8', 'Apparence : couleurs personnalisées conservées en mode "Personnaliser"');
gws_test_assert(!array_key_exists('image_fond_id', $apparence_custom), 'Apparence : aucune image de fond pour la Sticky bar (§G, contrairement à Pop-in)');

$apparence_site = gwseq_sanitize_sticky_bar_apparence_input(array(
  '_gwseq_sticky_bar_style_mode' => 'site',
  '_gwseq_sticky_bar_couleur_fond' => '#1a1a1a',
));
gws_test_assert($apparence_site['couleur_fond'] === '', 'Apparence : "Style du site" nettoie systématiquement les couleurs, même si d\'anciennes valeurs sont encore soumises');

$apparence_default = gwseq_sanitize_sticky_bar_apparence_input(array());
gws_test_assert($apparence_default['style_mode'] === 'site' && $apparence_default['position'] === 'top' && $apparence_default['fermable'] === '', 'Apparence : "Style du site", position "Haut" et non fermable par défaut');

$apparence_position_invalide = gwseq_sanitize_sticky_bar_apparence_input(array('_gwseq_sticky_bar_position' => 'milieu'));
gws_test_assert($apparence_position_invalide['position'] === 'top', 'Apparence : position invalide -> repli sur "Haut"');

// =====================================================================================
// Sanitation — Diffusion (statut/dates/ciblage), déléguée à campagnes-shared.php
// =====================================================================================

$diffusion_default = gwseq_sanitize_sticky_bar_diffusion_input(array());
gws_test_assert($diffusion_default['statut'] === 'inactive', 'Diffusion : statut "Inactive" par défaut (repli prudent, jamais actif par omission)');
gws_test_assert($diffusion_default['debut_ts'] === 0 && $diffusion_default['fin_ts'] === 0, 'Diffusion : sans dates -> aucune limite');
gws_test_assert($diffusion_default['ciblage_mode'] === 'all', 'Diffusion : ciblage "Tout le site" par défaut');

$diffusion_active = gwseq_sanitize_sticky_bar_diffusion_input(array('_gwseq_sticky_bar_statut' => 'active'));
gws_test_assert($diffusion_active['statut'] === 'active', 'Diffusion : statut "Active" bien pris en compte quand soumis explicitement');

gws_test_make_post_stub(100, 'page');
$diffusion_ciblage = gwseq_sanitize_sticky_bar_diffusion_input(array(
  '_gwseq_sticky_bar_ciblage_mode' => 'exclude',
  '_gwseq_sticky_bar_ciblage_cibles' => array('page:100'),
));
gws_test_assert($diffusion_ciblage['ciblage_mode'] === 'exclude' && $diffusion_ciblage['ciblage_cibles'] === array('page:100'), 'Diffusion : ciblage "Tout sauf certains contenus" correctement délégué à la fonction partagée');

// =====================================================================================
// Sauvegarde/rechargement complet
// =====================================================================================

$_POST = array(GWSEQ_STICKY_BAR_NONCE_FIELD => 'stub-nonce');
gwseq_save_sticky_bar_meta(200);
gws_test_assert(gwseq_get_sticky_bar_contenu(200)['texte'] === '', 'Sauvegarde : sticky bar minimale (aucun champ soumis) -> tout vide, aucune erreur');

$_POST = array(
  GWSEQ_STICKY_BAR_NONCE_FIELD => 'stub-nonce',
  '_gwseq_sticky_bar_texte' => 'Portes ouvertes le 12 mai',
  '_gwseq_sticky_bar_cta_active' => '1',
  '_gwseq_sticky_bar_cta_libelle' => 'Je m’inscris',
  '_gwseq_sticky_bar_cta_url' => 'https://example.test/inscription',
  '_gwseq_sticky_bar_style_mode' => 'custom',
  '_gwseq_sticky_bar_position' => 'bottom',
  '_gwseq_sticky_bar_fermable' => '1',
  '_gwseq_sticky_bar_couleur_fond' => '#1a1a1a',
  '_gwseq_sticky_bar_couleur_texte' => '#ffffff',
  '_gwseq_sticky_bar_couleur_cta' => '#1d4ed8',
  '_gwseq_sticky_bar_couleur_cta_texte' => '#ffffff',
  '_gwseq_sticky_bar_statut' => 'active',
  '_gwseq_sticky_bar_ciblage_mode' => 'front_page',
);
gwseq_save_sticky_bar_meta(201);

$reloaded_contenu = gwseq_get_sticky_bar_contenu(201);
gws_test_assert($reloaded_contenu['texte'] === 'Portes ouvertes le 12 mai', 'Sauvegarde/rechargement : texte court');
gws_test_assert($reloaded_contenu['cta_url'] === 'https://example.test/inscription', 'Sauvegarde/rechargement : URL du CTA');

$reloaded_apparence = gwseq_get_sticky_bar_apparence(201);
gws_test_assert($reloaded_apparence['style_mode'] === 'custom' && $reloaded_apparence['position'] === 'bottom', 'Sauvegarde/rechargement : style personnalisé + position');
gws_test_assert($reloaded_apparence['fermable'] === '1' && $reloaded_apparence['couleur_fond'] === '#1a1a1a', 'Sauvegarde/rechargement : fermable + couleur de fond');

$reloaded_diffusion = gwseq_get_sticky_bar_diffusion(201);
gws_test_assert($reloaded_diffusion['statut'] === 'active', 'Sauvegarde/rechargement : statut actif');
gws_test_assert($reloaded_diffusion['ciblage_mode'] === 'front_page', 'Sauvegarde/rechargement : ciblage page d\'accueil');

// =====================================================================================
// Sécurité de la sauvegarde
// =====================================================================================

$_POST = array(GWSEQ_STICKY_BAR_NONCE_FIELD => 'stub-nonce', '_gwseq_sticky_bar_texte' => 'Ne pas enregistrer');
$GLOBALS['__gwseq_test_security']['nonce_valid'] = false;
gwseq_save_sticky_bar_meta(300);
gws_test_assert(gwseq_get_sticky_bar_contenu(300)['texte'] === '', 'Sécurité : nonce invalide -> aucune sauvegarde');
$GLOBALS['__gwseq_test_security']['nonce_valid'] = true;

$GLOBALS['__gwseq_test_security']['can_edit'] = false;
gwseq_save_sticky_bar_meta(300);
gws_test_assert(gwseq_get_sticky_bar_contenu(300)['texte'] === '', 'Sécurité : permissions insuffisantes -> aucune sauvegarde');
$GLOBALS['__gwseq_test_security']['can_edit'] = true;

$GLOBALS['__gwseq_test_security']['is_revision'] = true;
gwseq_save_sticky_bar_meta(300);
gws_test_assert(gwseq_get_sticky_bar_contenu(300)['texte'] === '', 'Sécurité : révision -> aucune sauvegarde');
$GLOBALS['__gwseq_test_security']['is_revision'] = false;

gwseq_save_sticky_bar_meta(300);
gws_test_assert(gwseq_get_sticky_bar_contenu(300)['texte'] === 'Ne pas enregistrer', 'Sécurité : nonce + permissions + non-révision -> sauvegarde réelle effectuée');
$_POST = array();

// =====================================================================================
// Rendu partagé preview/front — gwseq_render_sticky_bar_markup()
// =====================================================================================

$config = gwseq_get_sticky_bar_config(201);
$html = gwseq_render_sticky_bar_markup($config);
gws_test_assert(strpos($html, 'gwseq-sticky-bar--bottom') !== false, 'Rendu : classe de position appliquée (Bas)');
gws_test_assert(strpos($html, 'gwseq-sticky-bar--custom') !== false, 'Rendu : classe de style personnalisé appliquée');
gws_test_assert(strpos($html, '--gws-sticky-bg:#1a1a1a') !== false, 'Rendu : couleur de fond injectée en variable CSS personnalisée');
gws_test_assert(strpos($html, 'Portes ouvertes le 12 mai') !== false, 'Rendu : texte court affiché');
gws_test_assert(strpos($html, 'Je m’inscris') !== false && strpos($html, 'https://example.test/inscription') !== false, 'Rendu : CTA affiché avec son URL');
gws_test_assert(strpos($html, 'gwseq-sticky-bar__close') !== false, 'Rendu : bouton de fermeture présent quand "fermable" est activé');
gws_test_assert(strpos($html, 'background') === false && strpos($html, 'bg-image') === false, 'Rendu : jamais d\'image de fond pour la Sticky bar (§G)');

// --- Non fermable -> jamais de bouton de fermeture ---
$config_non_fermable = $config;
$config_non_fermable['fermable'] = '';
gws_test_assert(strpos(gwseq_render_sticky_bar_markup($config_non_fermable), 'gwseq-sticky-bar__close') === false, 'Rendu : "fermable" désactivé -> aucun bouton de fermeture (contrairement à Pop-in qui est TOUJOURS fermable)');

// --- CTA inactif -> jamais affiché ---
$config_cta_off = $config;
$config_cta_off['cta']['active'] = '';
gws_test_assert(strpos(gwseq_render_sticky_bar_markup($config_cta_off), 'gwseq-sticky-bar__cta') === false, 'Rendu : CTA désactivé -> jamais affiché même si libellé/URL existent');

// --- Configuration par défaut minimale -> rendu propre, jamais d'erreur ---
$html_defaults = gwseq_render_sticky_bar_markup(array());
gws_test_assert(strpos($html_defaults, 'gwseq-sticky-bar--top') !== false, 'Rendu : configuration vide -> position "Haut" par défaut, rendu propre');
gws_test_assert(strpos($html_defaults, 'gwseq-sticky-bar--custom') === false, 'Rendu : configuration vide -> style du site par défaut (jamais de classe "custom")');
gws_test_assert(strpos($html_defaults, 'gwseq-sticky-bar__close') === false, 'Rendu : configuration vide -> non fermable par défaut');

// --- Attributs supplémentaires (comportement front) fusionnés sans reconstruire le HTML ---
$html_with_attrs = gwseq_render_sticky_bar_markup($config, array('data-gwseq-sticky-id' => 201));
gws_test_assert(strpos($html_with_attrs, 'data-gwseq-sticky-id="201"') !== false, 'Rendu : attributs de comportement front fusionnés dans le même balisage (une seule fonction de rendu pour preview ET front)');

// =====================================================================================
// AJAX preview (§J) : état de formulaire -> mêmes sanitizers -> même fonction de rendu
// =====================================================================================

$_POST = array(
  'nonce' => 'valid',
  '_gwseq_sticky_bar_texte' => 'Aperçu en direct',
  '_gwseq_sticky_bar_style_mode' => 'site',
  '_gwseq_sticky_bar_position' => 'top',
);
$json = null;
try { gwseq_ajax_preview_sticky_bar(); } catch (Gws_Test_Json_Exit $e) { $json = $GLOBALS['__gwseq_test_json_response']; }
gws_test_assert($json !== null && $json['success'] === true, 'Preview AJAX : réponse de succès');
gws_test_assert(strpos($json['data']['html'], 'Aperçu en direct') !== false, 'Preview AJAX : le HTML retourné reflète l\'état de formulaire soumis, via LA MÊME fonction de rendu que le front');

// --- Sécurité du point d'entrée AJAX ---
$GLOBALS['__gwseq_test_security']['nonce_valid'] = false;
$json = null;
try { gwseq_ajax_preview_sticky_bar(); } catch (Gws_Test_Json_Exit $e) { $json = $GLOBALS['__gwseq_test_json_response']; }
gws_test_assert($json !== null && $json['success'] === false, 'Preview AJAX : nonce invalide -> erreur, jamais de rendu');
$GLOBALS['__gwseq_test_security']['nonce_valid'] = true;

$GLOBALS['__gwseq_test_security']['can_edit'] = false;
$json = null;
try { gwseq_ajax_preview_sticky_bar(); } catch (Gws_Test_Json_Exit $e) { $json = $GLOBALS['__gwseq_test_json_response']; }
gws_test_assert($json !== null && $json['success'] === false, 'Preview AJAX : permissions insuffisantes -> erreur');
$GLOBALS['__gwseq_test_security']['can_edit'] = true;
$_POST = array();

// =====================================================================================
// Meta boxes : trois sections (pas de Déclenchement, §G) + aperçu
// =====================================================================================

gwseq_add_sticky_bar_meta_boxes();
gws_test_assert(
  $GLOBALS['__gwseq_test_meta_boxes'] === array('gwseq-sticky-bar-contenu', 'gwseq-sticky-bar-apparence', 'gwseq-sticky-bar-diffusion', 'gwseq-sticky-bar-preview'),
  'Meta boxes : trois sections (Contenu/Apparence/Diffusion, pas de Déclenchement) + le panneau d\'aperçu, dans cet ordre'
);

$post_201 = (object) array('ID' => 201);
ob_start();
gwseq_render_sticky_bar_contenu_box($post_201);
$contenu_html = ob_get_clean();
gws_test_assert(strpos($contenu_html, 'name="_gwseq_sticky_bar_texte"') !== false, 'Rendu meta box Contenu : champ Texte court réellement rendu');
gws_test_assert(strpos($contenu_html, 'jamais affiché sur le site') !== false, 'Rendu meta box Contenu : rappel clair que le nom interne n\'est jamais public');

ob_start();
gwseq_render_sticky_bar_apparence_box($post_201);
$apparence_html = ob_get_clean();
gws_test_assert(strpos($apparence_html, 'name="_gwseq_sticky_bar_position"') !== false, 'Rendu meta box Apparence : sélecteur de position rendu');
gws_test_assert(strpos($apparence_html, 'Pas d’image de fond') !== false, 'Rendu meta box Apparence : rappel explicite qu\'il n\'y a pas d\'image de fond (§G)');
gws_test_assert(strpos($apparence_html, 'name="_gwseq_sticky_bar_fermable"') !== false, 'Rendu meta box Apparence : case "L\'utilisateur peut fermer la barre" rendue');

// =====================================================================================
// Colonnes de liste (§Q) : Nom | État | Période | Ciblage | Ordre (pas de Déclenchement)
// =====================================================================================

$native_columns = array('cb' => '<input type="checkbox">', 'title' => 'Titre', 'date' => 'Date');
$columns = gwseq_sticky_bar_admin_columns($native_columns);
gws_test_assert(
  array_keys($columns) === array('cb', 'title', 'gwseq_campagne_etat', 'gwseq_campagne_periode', 'gwseq_campagne_ciblage', 'gwseq_campagne_ordre'),
  'Colonnes : ordre exact Nom | État | Période | Ciblage | Ordre, pas de colonne Déclenchement (objet plus simple que Pop-in, §Q)'
);
gws_test_assert($columns['title'] === 'Nom', 'Colonnes : "title" relabellé "Nom"');
gws_test_assert(!array_key_exists('date', $columns), 'Colonnes : colonne native "Date" retirée');

ob_start();
gwseq_sticky_bar_admin_column_content('gwseq_campagne_etat', 201);
gws_test_assert(ob_get_clean() === 'Active', 'Colonne État : libellé résolu depuis le statut');

$GLOBALS['__gwseq_test_post_fields'][201]['menu_order'] = 3;
ob_start();
gwseq_sticky_bar_admin_column_content('gwseq_campagne_ordre', 201);
gws_test_assert(ob_get_clean() === '3', 'Colonne Ordre : menu_order natif affiché (réutilisation du mécanisme déjà existant)');

// =====================================================================================
// Éditeur par blocs désactivé (pas de Gutenberg), placeholder du titre natif
// =====================================================================================

gws_test_assert(gwseq_disable_block_editor_for_sticky_bar(true, GWSEQ_CPT_STICKY_BAR) === false, 'Éditeur par blocs : désactivé pour gwseq_sticky_bar (fiche structurée, pas de page builder)');
gws_test_assert(gwseq_disable_block_editor_for_sticky_bar(true, 'post') === true, 'Éditeur par blocs : inchangé pour un autre post type (Actualités)');

$placeholder = gwseq_sticky_bar_title_placeholder('Ajouter un titre', (object) array('post_type' => GWSEQ_CPT_STICKY_BAR));
gws_test_assert(strpos($placeholder, 'jamais affiché sur le site') !== false, 'Titre natif : placeholder rappelle clairement que le nom interne n\'est jamais public');
gws_test_assert(gwseq_sticky_bar_title_placeholder('Ajouter un titre', (object) array('post_type' => 'page')) === 'Ajouter un titre', 'Titre natif : placeholder inchangé pour un autre post type');

// =====================================================================================
// Meta enregistrées : jamais exposées en REST
// =====================================================================================

foreach ($GLOBALS['__gwseq_test_registered_meta'] as $meta_key => $args) {
  if (strpos($meta_key, '_gwseq_sticky_bar_') !== 0) continue;
  gws_test_assert(($args['show_in_rest'] ?? null) === false, "Meta enregistrées : $meta_key jamais exposée en REST");
}
gws_test_assert(($GLOBALS['__gwseq_test_registered_meta']['_gwseq_sticky_bar_ciblage_cibles']['type'] ?? null) === 'array', 'Meta enregistrées : ciblage_cibles déclarée de type \'array\' (sélection multiple)');
gws_test_assert(!array_key_exists('_gwseq_sticky_bar_image_fond_id', $GLOBALS['__gwseq_test_registered_meta']), 'Meta enregistrées : aucune meta d\'image de fond pour la Sticky bar (§G)');

echo ($failures === 0 ? 'Tous les tests sont passés.' : "$failures test(s) en échec.") . "\n";
exit($failures === 0 ? 0 : 1);

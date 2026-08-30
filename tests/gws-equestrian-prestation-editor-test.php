<?php
/**
 * Vérifie les trois corrections apportées suite au CR de recette runtime de l'Étape 3 :
 *
 * 1. Accessibilité réelle des modèles de prestations (cause racine : l'éditeur par blocs, actif
 *    par défaut sur gwseq_prestation, ne déclenche jamais le hook edit_form_after_title utilisé
 *    par le sélecteur — voir includes/prestation-editor.php). Contrairement aux tests précédents,
 *    qui se contentaient d'exercer gwseq_render_preset_picker() sans vérifier qu'elle serait
 *    réellement appelée par WordPress, ce fichier vérifie le VRAI rendu produit (contenu HTML)
 *    et le comportement du filtre qui restaure l'éditeur classique.
 * 2. UX Nom/Description : post_title/post_content restent les seules sources de vérité, aucune
 *    meta dupliquée n'est créée pour les représenter.
 * 3. Internationalisation : text domain cohérent, fonctions de traduction WordPress utilisées,
 *    HT/TTC indépendants de la devise, contenu utilisateur jamais traduit.
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
function sanitize_textarea_field($value) { return trim((string) $value); }
function sanitize_key($value) { return strtolower(preg_replace('/[^a-z0-9_\-]/', '', (string) $value)); }
function esc_url_raw($value) { $value = trim((string) $value); return $value === '' ? '' : $value; }
function absint($value) { return abs((int) $value); }
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function selected($a, $b) { return $a == $b ? ' selected' : ''; }
function checked($a, $b = true) { return $a == $b ? ' checked' : ''; }
function wp_parse_args($args, $defaults = array()) { return array_merge((array) $defaults, (array) $args); }
function register_post_meta($object_type, $meta_key, $args = array()) {}
function register_setting($group, $name, $args = array()) {}
function add_submenu_page(...$args) {}
function current_user_can($cap, $post_id = null) { return true; }
function get_post_type($post_id) { return false; }

// i18n : __() etc. renvoient la chaîne telle quelle (comportement WordPress par défaut sans
// traduction chargée), MAIS on capture le text domain utilisé à chaque appel pour vérifier sa
// cohérence — c'est le comportement réel qui nous intéresse ici, pas seulement "la fonction existe".
$GLOBALS['__gwseq_test_domains_used'] = array();
function __($text, $domain = 'default') { $GLOBALS['__gwseq_test_domains_used'][] = $domain; return $text; }
function esc_html__($text, $domain = 'default') { $GLOBALS['__gwseq_test_domains_used'][] = $domain; return esc_html($text); }
function esc_attr__($text, $domain = 'default') { $GLOBALS['__gwseq_test_domains_used'][] = $domain; return esc_attr($text); }
function esc_html_e($text, $domain = 'default') { echo esc_html__($text, $domain); }
function esc_attr_e($text, $domain = 'default') { echo esc_attr__($text, $domain); }

// --- add_action/add_filter : on capture les callbacks enregistrés pour pouvoir tester le vrai
// comportement du filtre use_block_editor_for_post_type (pas seulement "il est enregistré") ---
$GLOBALS['__gwseq_test_filters'] = array();
function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {}
function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {
  $GLOBALS['__gwseq_test_filters'][$hook][] = $callback;
}
function apply_filters($hook, $value, ...$args) {
  foreach ($GLOBALS['__gwseq_test_filters'][$hook] ?? array() as $callback) {
    $value = call_user_func($callback, $value, ...$args);
  }
  return $value;
}

// --- Registres en mémoire (posts/meta), comme dans les autres tests de ce dossier ---
$GLOBALS['__gwseq_test_meta'] = array();
function update_post_meta($post_id, $key, $value) { $GLOBALS['__gwseq_test_meta'][$post_id][$key] = $value; return true; }
function get_post_meta($post_id, $key, $single = false) { return $GLOBALS['__gwseq_test_meta'][$post_id][$key] ?? ''; }
function metadata_exists($type, $post_id, $key) {
  return array_key_exists($post_id, $GLOBALS['__gwseq_test_meta']) && array_key_exists($key, $GLOBALS['__gwseq_test_meta'][$post_id]);
}
function get_posts($args = array()) { return array(); }
function wp_nonce_field($action, $field) { echo '<input type="hidden" name="' . esc_attr($field) . '" value="stub-nonce">'; }
function admin_url($path = '') { return 'https://example.test/wp-admin/' . $path; }
function esc_url($url) { return $url; }
function get_the_title($post_id) { return ''; }

// --- Réglages globaux ---
$GLOBALS['__gwseq_test_options'] = array();
function get_option($name, $default = false) {
  return array_key_exists($name, $GLOBALS['__gwseq_test_options']) ? $GLOBALS['__gwseq_test_options'][$name] : $default;
}

// --- Assets : capture des handles réellement mis en file, sans effet ---
$GLOBALS['__gwseq_enqueued'] = array();
function wp_enqueue_script($handle, ...$rest) { $GLOBALS['__gwseq_enqueued'][] = $handle; }
function wp_enqueue_style($handle, ...$rest) { $GLOBALS['__gwseq_enqueued'][] = $handle; }
$GLOBALS['__gwseq_test_screen'] = null;
function get_current_screen() { return $GLOBALS['__gwseq_test_screen']; }

define('ABSPATH', __DIR__ . '/');
const GWSEQ_CPT_PRESTATION = 'gwseq_prestation';
const GWSEQ_CPT_GROUPE = 'gwseq_groupe';
define('GWSEQ_MODULE_URL', 'https://example.test/wp-content/plugins/gws-core/modules/gws-equestrian/');
define('GWSEQ_MODULE_VERSION', 'test');
$repo_root = dirname(__DIR__);
$module_dir = $repo_root . '/wp-content/plugins/gws-core/modules/gws-equestrian/';
require $repo_root . '/wp-content/plugins/gws-core/includes/fields.php';
require $module_dir . 'includes/settings.php';
require $module_dir . 'includes/prestation-fields.php';
require $module_dir . 'includes/prestation-editor.php';
require $module_dir . 'includes/presets.php';

// =====================================================================================
// 1. PRESETS — cause racine et correction (bug signalé en recette runtime)
// =====================================================================================

// --- Le filtre use_block_editor_for_post_type désactive bien l'éditeur par blocs UNIQUEMENT
// pour gwseq_prestation, jamais globalement (comportement réel, pas juste "le hook existe") ---
gws_test_assert(
  apply_filters('use_block_editor_for_post_type', true, GWSEQ_CPT_PRESTATION) === false,
  'Éditeur par blocs : désactivé pour gwseq_prestation (cause racine du bug corrigée)'
);
gws_test_assert(
  apply_filters('use_block_editor_for_post_type', true, 'post') === true,
  'Éditeur par blocs : inchangé pour les Articles (pas de désactivation globale)'
);
gws_test_assert(
  apply_filters('use_block_editor_for_post_type', true, GWSEQ_CPT_GROUPE) === true,
  'Éditeur par blocs : inchangé pour le Groupe tarifaire (lui-même sans éditeur de contenu)'
);

// --- Rendu réel du sélecteur de modèle sur une prestation neuve (auto-draft) : on vérifie le
// VRAI contenu produit, pas seulement que la fonction est déclarée ---
$new_prestation = (object) array('post_type' => GWSEQ_CPT_PRESTATION, 'post_status' => 'auto-draft');
ob_start();
gwseq_render_preset_picker($new_prestation);
$picker_html = ob_get_clean();

gws_test_assert(strpos($picker_html, '<select') !== false, 'Presets : un vrai élément <select> est rendu sur l’écran d’ajout');
gws_test_assert(strpos($picker_html, 'id="gwseq-preset-select"') !== false, 'Presets : le sélecteur porte l’identifiant attendu par le script d’application');
gws_test_assert(strpos($picker_html, '<optgroup') !== false, 'Presets : les familles apparaissent comme groupes d’options (sélection en 2 temps : famille puis modèle)');
gws_test_assert(strpos($picker_html, 'value="pension_pre_avec_infra"') !== false, 'Presets : un modèle connu (Pension pré avec infrastructures) apparaît réellement comme option');
gws_test_assert(strpos($picker_html, 'id="gwseq-preset-apply"') !== false, 'Presets : le bouton d’application du modèle est réellement rendu');

// --- Jamais affiché sur une prestation déjà publiée, ni sur un autre post type ---
$existing_prestation = (object) array('post_type' => GWSEQ_CPT_PRESTATION, 'post_status' => 'publish');
ob_start();
gwseq_render_preset_picker($existing_prestation);
gws_test_assert(ob_get_clean() === '', 'Presets : jamais affiché sur une prestation déjà existante (uniquement à la création)');

$other_post_type = (object) array('post_type' => 'page', 'post_status' => 'auto-draft');
ob_start();
gwseq_render_preset_picker($other_post_type);
gws_test_assert(ob_get_clean() === '', 'Presets : jamais affiché sur un autre post type que Prestation');

// --- Sélection d'un modèle -> préremplissage réel du titre (chemin complet, pas juste la
// présence du filtre) ---
$_GET['gwseq_preset'] = 'pension_pre_avec_infra';
gws_test_assert(
  gwseq_prefill_prestation_title('', $new_prestation) === 'Pension pré avec infrastructures',
  'Presets : sélectionner un modèle préremplit réellement le titre de la nouvelle prestation'
);
unset($_GET['gwseq_preset']);
gws_test_assert(
  gwseq_prefill_prestation_title('Titre WordPress par défaut', $new_prestation) === 'Titre WordPress par défaut',
  'Presets : sans modèle sélectionné, le comportement natif de WordPress n’est pas modifié'
);

// --- Absence d'écriture avant sauvegarde : ni le rendu du sélecteur ni la résolution d'un
// modèle ne doivent jamais écrire de meta ---
$GLOBALS['__gwseq_test_meta'] = array();
$_GET['gwseq_preset'] = 'iaf_chaleur';
ob_start();
gwseq_render_preset_picker($new_prestation);
ob_get_clean();
gwseq_get_requested_preset_defaults();
gwseq_prefill_prestation_title('', $new_prestation);
unset($_GET['gwseq_preset']);
gws_test_assert($GLOBALS['__gwseq_test_meta'] === array(), 'Presets : aucune meta écrite tant que l’utilisateur n’a pas lui-même enregistré le formulaire');

// --- Indépendance après sauvegarde : aucune meta ne référence jamais un identifiant de modèle
// (pas de relation persistante conservée) ---
$prestation_fields_source = file_get_contents($module_dir . 'includes/prestation-fields.php');
$presets_source = file_get_contents($module_dir . 'includes/presets.php');
gws_test_assert(
  !preg_match('/update_post_meta\([^;]*preset/i', $prestation_fields_source . $presets_source),
  'Presets : aucune meta n’est écrite pour mémoriser le modèle d’origine d’une prestation (indépendance totale après enregistrement)'
);

// =====================================================================================
// 2. UX — Nom/Description restent post_title/post_content, aucune meta dupliquée
// =====================================================================================

gws_test_assert(
  gwseq_prestation_title_placeholder('Ajouter un titre', $new_prestation) === 'Nom de la prestation',
  'UX : l’espace réservé du titre est explicite ("Nom de la prestation") pour une Prestation'
);
$page_post = (object) array('post_type' => 'page');
gws_test_assert(
  gwseq_prestation_title_placeholder('Ajouter un titre', $page_post) === 'Ajouter un titre',
  'UX : l’espace réservé du titre est inchangé pour un autre post type (aucun effet de bord global)'
);

ob_start();
gwseq_render_prestation_description_label($new_prestation);
$description_label_html = ob_get_clean();
gws_test_assert(strpos($description_label_html, 'Description') !== false, 'UX : un libellé "Description" est réellement rendu au-dessus de l’éditeur natif');

ob_start();
gwseq_render_prestation_description_label($page_post);
gws_test_assert(ob_get_clean() === '', 'UX : aucun libellé "Description" injecté sur un autre post type');

// --- Aucune meta dupliquée pour représenter le nom ou la description (post_title/post_content
// restent les seules sources de vérité) ---
$prestation_editor_source = file_get_contents($module_dir . 'includes/prestation-editor.php');
foreach (array('_gwseq_nom', '_gwseq_titre', '_gwseq_description') as $forbidden_meta_key) {
  gws_test_assert(
    strpos($prestation_fields_source, "'" . $forbidden_meta_key) === false && strpos($prestation_editor_source, "'" . $forbidden_meta_key) === false,
    "UX : aucune meta '$forbidden_meta_key' créée (post_title/post_content restent l'unique source de vérité)"
  );
}

// --- Assets scopés au bon écran uniquement ---
$GLOBALS['__gwseq_test_screen'] = (object) array('post_type' => 'page');
$GLOBALS['__gwseq_enqueued'] = array();
gwseq_enqueue_prestation_admin_assets('post.php');
gws_test_assert(empty($GLOBALS['__gwseq_enqueued']), 'Assets Prestation : jamais chargés sur l’écran d’un autre post type');

$GLOBALS['__gwseq_test_screen'] = (object) array('post_type' => GWSEQ_CPT_PRESTATION);
$GLOBALS['__gwseq_enqueued'] = array();
gwseq_enqueue_prestation_admin_assets('post.php');
gws_test_assert(in_array('gwseq-prestation-admin', $GLOBALS['__gwseq_enqueued'], true), 'Assets Prestation : chargés sur l’écran d’édition d’une Prestation');

$GLOBALS['__gwseq_enqueued'] = array();
gwseq_enqueue_prestation_admin_assets('edit.php');
gws_test_assert(empty($GLOBALS['__gwseq_enqueued']), 'Assets Prestation : jamais chargés sur un écran sans rapport (edit.php)');

$_GET['post_type'] = GWSEQ_CPT_PRESTATION;
$GLOBALS['__gwseq_enqueued'] = array();
gwseq_enqueue_preset_picker_assets('post-new.php');
gws_test_assert(in_array('gwseq-presets-admin', $GLOBALS['__gwseq_enqueued'], true), 'Assets Presets : chargés sur l’écran d’ajout d’une Prestation');

$_GET['post_type'] = 'page';
$GLOBALS['__gwseq_enqueued'] = array();
gwseq_enqueue_preset_picker_assets('post-new.php');
gws_test_assert(empty($GLOBALS['__gwseq_enqueued']), 'Assets Presets : jamais chargés pour un autre post type');

$_GET['post_type'] = GWSEQ_CPT_PRESTATION;
$GLOBALS['__gwseq_enqueued'] = array();
gwseq_enqueue_preset_picker_assets('post.php');
gws_test_assert(empty($GLOBALS['__gwseq_enqueued']), 'Assets Presets : jamais chargés sur l’écran d’édition (uniquement post-new.php)');
unset($_GET['post_type']);

// =====================================================================================
// 3. INTERNATIONALISATION
// =====================================================================================

// --- Text domain cohérent : chaque appel de traduction rencontré doit utiliser 'gws-core' ---
$GLOBALS['__gwseq_test_domains_used'] = array();
gwseq_prestation_tarif_mode_options();
gwseq_prestation_unit_options();
gwseq_price_display_mode_options();
gwseq_currency_options();
gwseq_prestation_preset_families();
$other_domains = array_diff(array_unique($GLOBALS['__gwseq_test_domains_used']), array('gws-core'));
gws_test_assert(count($GLOBALS['__gwseq_test_domains_used']) > 0, 'i18n : les fonctions de traduction WordPress sont réellement appelées (pas de chaînes en dur non traductibles)');
gws_test_assert(empty($other_domains), 'i18n : text domain cohérent "gws-core" sur tous les appels rencontrés (aucun domaine différent)');

// --- Grep direct du code source : toute chaîne d'appel à une fonction de traduction du module
// utilise bien le domaine 'gws-core' ---
$all_module_php = '';
foreach (glob($module_dir . 'includes/*.php') as $file) {
  $all_module_php .= file_get_contents($file);
}
preg_match_all('/\b(?:__|_e|esc_html__|esc_html_e|esc_attr__|esc_attr_e)\(\s*[\'"](?:[^\'"\\\\]|\\\\.)*[\'"]\s*,\s*[\'"]([^\'"]*)[\'"]\s*\)/', $all_module_php, $domain_matches);
$mismatched_domains = array_diff(array_unique($domain_matches[1]), array('gws-core'));
gws_test_assert(empty($mismatched_domains), 'i18n : aucun appel de traduction dans le code source du module n’utilise un text domain autre que "gws-core" (trouvé : ' . implode(', ', $mismatched_domains) . ')');

// --- HT/TTC : jamais lié à la devise (le même suffixe apparaît quelle que soit la devise) ---
$tarif_simple = array('mode' => 'unique', 'prix' => 10.0, 'unite' => '', 'unite_autre' => '', 'prix_public' => '1');
$summary_eur_ht = gwseq_prestation_price_summary($tarif_simple, 'ht', 'EUR');
$summary_gbp_ht = gwseq_prestation_price_summary($tarif_simple, 'ht', 'GBP');
$summary_usd_ttc = gwseq_prestation_price_summary($tarif_simple, 'ttc', 'USD');
gws_test_assert(strpos($summary_eur_ht, 'HT') !== false, 'i18n : suffixe HT présent avec EUR');
gws_test_assert(strpos($summary_gbp_ht, 'HT') !== false, 'i18n : suffixe HT également présent avec GBP (bug signalé en recette : GBP ne détermine aucune langue)');
gws_test_assert(strpos($summary_usd_ttc, 'TTC') !== false, 'i18n : suffixe TTC présent avec USD (aucune association devise -> terminologie fiscale)');

// --- Valeurs techniques ht/ttc et hidden indépendantes des libellés traduits ---
gws_test_assert(gwseq_get_price_display_mode() === 'ttc', 'i18n : la valeur technique retournée reste "ttc" (jamais le libellé traduit)');
$GLOBALS['__gwseq_test_options'] = array('gwseq_settings' => array('price_display_mode' => 'ht'));
gws_test_assert(gwseq_get_price_display_mode() === 'ht', 'i18n : la valeur technique retournée reste "ht" (jamais le libellé traduit)');
$GLOBALS['__gwseq_test_options'] = array();

// --- Contenu utilisateur jamais traduit : un libellé personnalisé "Sur demande" contenant un
// texte métier ne passe jamais par gwseq_prestation_demande_libelle_default() (qui est la seule
// chaîne du logiciel de ce champ) une fois enregistré explicitement ---
$GLOBALS['__gwseq_test_meta'][77] = array('_gwseq_tarif_mode' => 'devis', '_gwseq_tarif_demande_libelle' => 'Contactez notre écurie au 06 12 34 56 78');
gws_test_assert(
  gwseq_get_prestation_demande_libelle(77) === 'Contactez notre écurie au 06 12 34 56 78',
  'i18n : un libellé personnalisé (donnée du site) est restitué strictement tel quel, jamais altéré par une fonction de traduction'
);

// --- Le nom d'un groupe tarifaire (donnée utilisateur) n'est jamais passé à une fonction de
// traduction dans le rendu de la relation Prestation -> Groupe ---
$groupe_box_source = $prestation_fields_source;
gws_test_assert(
  !preg_match('/__\s*\(\s*get_the_title/', $groupe_box_source),
  'i18n : le nom d’un groupe tarifaire (donnée utilisateur) n’est jamais passé à une fonction de traduction'
);

echo ($failures === 0 ? 'Tous les tests sont passés.' : "$failures test(s) en échec.") . "\n";
exit($failures === 0 ? 0 : 1);

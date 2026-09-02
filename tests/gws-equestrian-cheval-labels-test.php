<?php
/**
 * Vérifie les Labels ANSF de `gws-equestrian` (nouveau lot, volontairement minimal — uniquement
 * SFO, Étalon SF Génétique Avenir, et les trois familles de labels poulinières Sport/Élevage/
 * Modèle & Allures). Même méthodologie que les étapes précédentes : on exerce les fonctions avec
 * des données à la forme réelle de $_POST, jamais seulement des helpers appelés avec des valeurs
 * déjà parfaites, et on vérifie le comportement réel des hooks WordPress (rendu, sauvegarde).
 *
 * RÈGLES VÉRIFIÉES (voir includes/cheval-labels.php pour le détail complet) :
 * - SFO disponible pour les trois sexes, jamais touché par un changement de sexe ;
 * - labels poulinières (Sport/Élevage/Modèle & Allures) réservés à la femelle, UNE SEULE valeur
 *   possible par famille (enum fermé, jamais quatre cases indépendantes) ;
 * - Étalon SF Génétique Avenir réservé au mâle ET au hongre ;
 * - sanitation serveur OBLIGATOIRE, appliquée indépendamment de l'affichage conditionnel admin —
 *   un payload $_POST délibérément incohérent (ex. une femelle avec un label poulinière ET
 *   SF Génétique Avenir soumis simultanément) ne doit jamais produire une donnée incohérente en
 *   base ;
 * - changement de sexe d'un cheval existant : nettoyage des labels devenus incompatibles, SFO
 *   toujours préservé.
 *
 * Ne fait pas partie des paquets livrés (gws-core.zip / gws-starter.zip).
 */

$failures = 0;
function gws_test_assert($condition, $label) {
  global $failures;
  if ($condition) { echo "OK   - $label\n"; }
  else { echo "FAIL - $label\n"; $failures++; }
}

// --- Stubs WordPress minimaux (même convention que gws-equestrian-cheval-logic-test.php) ---
function wp_unslash($value) { return is_array($value) ? array_map('wp_unslash', $value) : $value; }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function sanitize_textarea_field($value) { return trim((string) $value); }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value)); }
function esc_url_raw($value) { $value = trim((string) $value); return $value === '' ? '' : $value; }
function absint($value) { return abs((int) $value); }
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
// FIDÈLE au comportement réel de checked()/selected() (WordPress core) : ÉCHOENT par défaut (le
// code réel les appelle sans jamais préfixer d'echo, ex. includes/cheval-labels.php) — un stub qui
// se contenterait de RETOURNER la chaîne sans l'imprimer laisserait cet attribut silencieusement
// absent de tout rendu testé ici, sans qu'aucune assertion sur sa présence ne puisse jamais réussir.
function selected($a, $b, $echo = true) { $r = $a == $b ? ' selected' : ''; if ($echo) echo $r; return $r; }
function checked($a, $b = true, $echo = true) { $r = $a == $b ? ' checked' : ''; if ($echo) echo $r; return $r; }
function wp_parse_args($args, $defaults = array()) { return array_merge((array) $defaults, (array) $args); }
function wp_nonce_field($action, $field) { echo '<input type="hidden" name="' . esc_attr($field) . '" value="stub-nonce">'; }

$GLOBALS['__gwseq_test_domains_used'] = array();
function __($text, $domain = 'default') { $GLOBALS['__gwseq_test_domains_used'][] = $domain; return $text; }
function _n($single, $plural, $number, $domain = 'default') { $GLOBALS['__gwseq_test_domains_used'][] = $domain; return $number == 1 ? $single : $plural; }
function esc_html__($text, $domain = 'default') { $GLOBALS['__gwseq_test_domains_used'][] = $domain; return esc_html($text); }
function esc_html_e($text, $domain = 'default') { echo esc_html__($text, $domain); }

$GLOBALS['__gwseq_test_registered_meta'] = array();
function register_post_meta($object_type, $meta_key, $args = array()) { $GLOBALS['__gwseq_test_registered_meta'][$meta_key] = $args; }
function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {}
function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {}

$GLOBALS['__gwseq_test_meta'] = array();
function update_post_meta($post_id, $key, $value) { $GLOBALS['__gwseq_test_meta'][$post_id][$key] = $value; return true; }
function get_post_meta($post_id, $key, $single = false) { return $GLOBALS['__gwseq_test_meta'][$post_id][$key] ?? ''; }
function metadata_exists($type, $post_id, $key) {
  return array_key_exists($post_id, $GLOBALS['__gwseq_test_meta']) && array_key_exists($key, $GLOBALS['__gwseq_test_meta'][$post_id]);
}
function get_post_field($field, $post_id) { return $GLOBALS['__gwseq_test_post_fields'][$post_id][$field] ?? ''; }
$GLOBALS['__gwseq_test_terms'] = array();
function get_the_terms($post_id, $taxonomy) { return $GLOBALS['__gwseq_test_terms'][$post_id] ?? false; }
function wp_list_pluck($list, $field) {
  return array_map(function ($item) use ($field) { return is_object($item) ? $item->$field : $item[$field]; }, $list);
}

$GLOBALS['__gwseq_test_options'] = array();
function get_option($name, $default = false) {
  return array_key_exists($name, $GLOBALS['__gwseq_test_options']) ? $GLOBALS['__gwseq_test_options'][$name] : $default;
}

$GLOBALS['__gwseq_test_security'] = array('nonce_valid' => true, 'can_edit' => true, 'is_revision' => false);
function wp_verify_nonce($nonce, $action) { return $GLOBALS['__gwseq_test_security']['nonce_valid']; }
function current_user_can($cap, $post_id = null) { return $GLOBALS['__gwseq_test_security']['can_edit']; }
function wp_is_post_revision($post_id) { return $GLOBALS['__gwseq_test_security']['is_revision']; }
if (!defined('DOING_AUTOSAVE')) define('DOING_AUTOSAVE', false);

$GLOBALS['__gwseq_test_environment'] = 'production';
function wp_get_environment_type() { return $GLOBALS['__gwseq_test_environment']; }
$GLOBALS['__gwseq_test_meta_boxes'] = array();
function add_meta_box($id, $title, $callback, $post_type = null, $context = 'advanced', $priority = 'default') {
  $GLOBALS['__gwseq_test_meta_boxes'][] = $id;
}

function wp_generate_uuid4() {
  $data = random_bytes(16);
  $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
  $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
  $hex = bin2hex($data);
  return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20, 12));
}

$GLOBALS['__gwseq_test_screen'] = null;
function get_current_screen() { return $GLOBALS['__gwseq_test_screen']; }
$GLOBALS['__gwseq_enqueued'] = array();
function wp_enqueue_script($handle, ...$rest) { $GLOBALS['__gwseq_enqueued'][] = $handle; }
function wp_enqueue_style($handle, ...$rest) { $GLOBALS['__gwseq_enqueued'][] = $handle; }
function wp_script_is($handle, $status = 'enqueued') { return in_array($handle, $GLOBALS['__gwseq_enqueued'], true); }
function wp_localize_script($handle, $object_name, $data) { $GLOBALS['__gwseq_test_localized'][$handle][$object_name] = $data; }

$GLOBALS['__gwseq_test_current_user_id'] = 1;
function get_current_user_id() { return $GLOBALS['__gwseq_test_current_user_id']; }

$GLOBALS['__gwseq_test_posts'] = array();
function gws_test_make_post($id, $post_type, $title, $status = 'publish') {
  $GLOBALS['__gwseq_test_posts'][$id] = array('post_type' => $post_type, 'post_status' => $status, 'post_title' => $title);
}
function get_post_type($post_id) { return $GLOBALS['__gwseq_test_posts'][$post_id]['post_type'] ?? false; }
$GLOBALS['__gwseq_test_user_meta'] = array();
function get_user_meta($user_id, $key, $single = false) { return $GLOBALS['__gwseq_test_user_meta'][$user_id][$key] ?? ''; }
function update_user_meta($user_id, $key, $value) { $GLOBALS['__gwseq_test_user_meta'][$user_id][$key] = $value; return true; }

define('ABSPATH', __DIR__ . '/');
const GWSEQ_CPT_CHEVAL = 'gwseq_cheval';
const GWSEQ_TAX_CATEGORIE_CHEVAL = 'gwseq_categorie_cheval';
define('GWSEQ_MODULE_URL', 'https://example.test/wp-content/plugins/gws-core/modules/gws-equestrian/');
define('GWSEQ_MODULE_VERSION', 'test');
$repo_root = dirname(__DIR__);
$module_dir = $repo_root . '/wp-content/plugins/gws-core/modules/gws-equestrian/';
require $repo_root . '/wp-content/plugins/gws-core/includes/fields.php';
require $module_dir . 'includes/settings.php';
require $module_dir . 'includes/race-referentiel.php';
require $module_dir . 'includes/cheval-fields.php';
require $module_dir . 'includes/cheval-labels.php';

function gws_test_make_post_object($id) { return (object) array('ID' => $id); }

// =====================================================================================
// 1. Sanitation pure — gwseq_sanitize_cheval_labels_input($raw, $sexe)
// =====================================================================================

// --- (1) Femelle : par défaut (aucun champ soumis), SFO non coché et les trois familles à "none" ---
$labels = gwseq_sanitize_cheval_labels_input(array(), 'female');
gws_test_assert(
  $labels === array('sfo' => '', 'sf_genetique_avenir' => '', 'sport' => 'none', 'elevage' => 'none', 'modele_allures' => 'none'),
  '(1) Femelle, aucun champ soumis : SFO non coché, SF Génétique Avenir absent (jamais applicable), les trois familles à "none"'
);

// --- (2) Femelle : peut enregistrer Sport=Élite + Élevage=Excellente + Modèle&Allures=Très Bonne
// simultanément (trois familles indépendantes les unes des autres) ---
$labels = gwseq_sanitize_cheval_labels_input(array(
  '_gwseq_label_sfo' => '1',
  '_gwseq_label_sport' => 'elite',
  '_gwseq_label_elevage' => 'excellente',
  '_gwseq_label_modele_allures' => 'tres_bonne',
), 'female');
gws_test_assert(
  $labels['sfo'] === '1' && $labels['sport'] === 'elite' && $labels['elevage'] === 'excellente' && $labels['modele_allures'] === 'tres_bonne',
  '(2) Femelle : Sport=Élite + Élevage=Excellente + Modèle&Allures=Très Bonne enregistrés simultanément, chaque famille indépendante des deux autres'
);

// --- (3) Impossibilité d'avoir deux niveaux simultanés pour une même famille : le payload
// $_POST-shaped ne PEUT physiquement porter qu'une seule valeur par nom de champ (un groupe de
// boutons radio, jamais des checkboxes) — un enum fermé garantit qu'AUCUNE valeur autre que les
// quatre attendues ne peut jamais être stockée, y compris une tentative de payload multi-valeurs
// (un tableau au lieu d'une chaîne, ex. formulaire trafiqué) ---
$labels = gwseq_sanitize_cheval_labels_input(array('_gwseq_label_sport' => array('elite', 'tres_bonne')), 'female');
gws_test_assert($labels['sport'] === 'none', '(3) Impossibilité de deux niveaux simultanés : un payload trafiqué (tableau au lieu d’une valeur unique) pour une même famille est rejeté -> repli sur "none", jamais une valeur composite');
$labels = gwseq_sanitize_cheval_labels_input(array('_gwseq_label_sport' => 'elite-ou-tres_bonne'), 'female');
gws_test_assert($labels['sport'] === 'none', '(3) Impossibilité de deux niveaux simultanés : une valeur inconnue de l’enum fermé (ex. concaténation de deux niveaux) est rejetée -> repli sur "none"');

// --- (4) Femelle : pas de SF Génétique Avenir, même si explicitement soumis (formulaire trafiqué
// ou changement de sexe non encore répercuté côté client) ---
$labels = gwseq_sanitize_cheval_labels_input(array('_gwseq_label_sf_genetique_avenir' => '1'), 'female');
gws_test_assert($labels['sf_genetique_avenir'] === '', '(4) Femelle : "SF Génétique Avenir" jamais retenu, même explicitement soumis à "1" (réservé au mâle/hongre)');

// --- (5) Mâle : SFO + SF Génétique Avenir disponibles, jamais de labels poulinières même soumis ---
$labels = gwseq_sanitize_cheval_labels_input(array(
  '_gwseq_label_sfo' => '1',
  '_gwseq_label_sf_genetique_avenir' => '1',
  '_gwseq_label_sport' => 'elite',
), 'male');
gws_test_assert(
  $labels['sfo'] === '1' && $labels['sf_genetique_avenir'] === '1' && $labels['sport'] === 'none' && $labels['elevage'] === 'none' && $labels['modele_allures'] === 'none',
  '(5) Mâle : SFO et SF Génétique Avenir bien enregistrés, "Sport" soumis explicitement ("elite") mais jamais retenu (labels poulinières réservés à la femelle)'
);

// --- (6) Hongre : même comportement que mâle (autorisé volontairement pour Étalon SF Génétique
// Avenir — un hongre a pu avoir une carrière de reproducteur avant castration) ---
$labels = gwseq_sanitize_cheval_labels_input(array(
  '_gwseq_label_sfo' => '1',
  '_gwseq_label_sf_genetique_avenir' => '1',
  '_gwseq_label_elevage' => 'tres_bonne',
), 'gelding');
gws_test_assert(
  $labels['sfo'] === '1' && $labels['sf_genetique_avenir'] === '1' && $labels['elevage'] === 'none',
  '(6) Hongre : même comportement que mâle — SFO et SF Génétique Avenir disponibles, "Élevage" soumis mais jamais retenu'
);

// --- Sexe non renseigné (repli prudent : aucun des deux groupes sexe-dépendants n’est retenu tant
// que le sexe n’est pas confirmé) — non demandé explicitement dans la liste mais couvre le cas
// limite documenté dans includes/cheval-labels.php ---
$labels = gwseq_sanitize_cheval_labels_input(array(
  '_gwseq_label_sfo' => '1',
  '_gwseq_label_sf_genetique_avenir' => '1',
  '_gwseq_label_sport' => 'elite',
), '');
gws_test_assert(
  $labels['sfo'] === '1' && $labels['sf_genetique_avenir'] === '' && $labels['sport'] === 'none',
  'Sexe non renseigné : SFO reste disponible (indépendant du sexe), mais AUCUN des deux groupes sexe-dépendants (SF Génétique Avenir, labels poulinières) n’est retenu tant que le sexe n’est pas confirmé'
);

// --- (12) Payload POST volontairement invalide : valeurs hors enum, champs absents, types
// inattendus -> sanitation correcte, jamais une erreur ni une valeur brute propagée ---
$labels = gwseq_sanitize_cheval_labels_input(array('_gwseq_label_sport' => 'legendaire', '_gwseq_label_elevage' => null, '_gwseq_label_modele_allures' => ''), 'female');
gws_test_assert($labels['sport'] === 'none' && $labels['elevage'] === 'none' && $labels['modele_allures'] === 'none', '(12) Payload invalide : une valeur hors enum ("legendaire"), null, ou une chaîne vide replient chacune sur "none", jamais une erreur');
$labels = gwseq_sanitize_cheval_labels_input(array('_gwseq_label_sfo' => array('malformé')), 'male');
gws_test_assert($labels['sfo'] === '1', '(12) Payload invalide : gws_core_field_sanitize(\'checkbox\', ...) traite toute valeur non vide (y compris un tableau malformé) comme "coché", jamais une erreur fatale — comportement déjà établi ailleurs dans le module, non spécifique aux labels');
$labels = gwseq_sanitize_cheval_labels_input(null, 'female');
gws_test_assert($labels === array('sfo' => '', 'sf_genetique_avenir' => '', 'sport' => 'none', 'elevage' => 'none', 'modele_allures' => 'none'), '(12) Payload invalide : $raw entièrement absent (null) -> tous les champs replient sur leur valeur par défaut, jamais une erreur fatale');

// =====================================================================================
// 2. Rendu réel de la meta box (§A : "son contenu dépend du sexe du cheval")
// =====================================================================================

gws_test_make_post(800, GWSEQ_CPT_CHEVAL, 'Jument Labels');
$GLOBALS['__gwseq_test_meta'][800]['_gwseq_sexe'] = 'female';
$GLOBALS['__gwseq_test_meta'][800]['_gwseq_label_sport'] = 'elite';
ob_start();
gwseq_render_cheval_labels_box(gws_test_make_post_object(800));
$labels_html_female = ob_get_clean();
gws_test_assert(strpos($labels_html_female, 'name="_gwseq_label_sfo"') !== false, 'Rendu (femelle) : le champ SFO est bien présent');
gws_test_assert(strpos($labels_html_female, 'name="_gwseq_label_sport"') !== false && strpos($labels_html_female, 'name="_gwseq_label_elevage"') !== false && strpos($labels_html_female, 'name="_gwseq_label_modele_allures"') !== false, 'Rendu (femelle) : les trois familles de labels poulinières sont bien présentes');
gws_test_assert(strpos($labels_html_female, 'name="_gwseq_label_sf_genetique_avenir"') === false, 'Rendu (femelle) : "SF Génétique Avenir" n’est jamais rendu (réservé au mâle/hongre)');
gws_test_assert(preg_match('/name="_gwseq_label_sport" value="elite"\s+checked/', $labels_html_female) === 1, 'Rendu (femelle) : le niveau actuellement enregistré ("elite") est bien précoché pour "Sport"');
gws_test_assert(preg_match_all('/name="_gwseq_label_sport" value="[a-z_]+"\s+checked/', $labels_html_female) === 1, 'Rendu (femelle) : un SEUL des quatre boutons radio "Sport" est précoché (jamais deux niveaux simultanés)');
gws_test_assert(substr_count($labels_html_female, 'name="_gwseq_label_sport"') === 4, 'Rendu (femelle) : exactement quatre boutons radio pour la famille "Sport" (Aucun/Très Bonne/Excellente/Élite), jamais quatre checkboxes indépendantes');

gws_test_make_post(801, GWSEQ_CPT_CHEVAL, 'Étalon Labels');
$GLOBALS['__gwseq_test_meta'][801]['_gwseq_sexe'] = 'male';
$GLOBALS['__gwseq_test_meta'][801]['_gwseq_label_sf_genetique_avenir'] = '1';
ob_start();
gwseq_render_cheval_labels_box(gws_test_make_post_object(801));
$labels_html_male = ob_get_clean();
gws_test_assert(strpos($labels_html_male, 'name="_gwseq_label_sfo"') !== false, 'Rendu (mâle) : le champ SFO est bien présent');
gws_test_assert(preg_match('/name="_gwseq_label_sf_genetique_avenir" value="1"\s+checked/', $labels_html_male) === 1, 'Rendu (mâle) : "SF Génétique Avenir" est bien présent et précoché selon la valeur enregistrée');
gws_test_assert(strpos($labels_html_male, 'name="_gwseq_label_sport"') === false, 'Rendu (mâle) : aucune des trois familles de labels poulinières n’est jamais rendue');

gws_test_make_post(802, GWSEQ_CPT_CHEVAL, 'Hongre Labels');
$GLOBALS['__gwseq_test_meta'][802]['_gwseq_sexe'] = 'gelding';
ob_start();
gwseq_render_cheval_labels_box(gws_test_make_post_object(802));
$labels_html_gelding = ob_get_clean();
gws_test_assert(strpos($labels_html_gelding, 'name="_gwseq_label_sf_genetique_avenir"') !== false && strpos($labels_html_gelding, 'name="_gwseq_label_sport"') === false, 'Rendu (hongre) : même comportement que mâle — "SF Génétique Avenir" présent, labels poulinières absents');

gws_test_make_post(803, GWSEQ_CPT_CHEVAL, 'Sexe Non Renseigne');
ob_start();
gwseq_render_cheval_labels_box(gws_test_make_post_object(803));
$labels_html_unknown = ob_get_clean();
gws_test_assert(strpos($labels_html_unknown, 'name="_gwseq_label_sfo"') !== false, 'Rendu (sexe non renseigné) : SFO reste toujours affiché (indépendant du sexe)');
gws_test_assert(strpos($labels_html_unknown, 'name="_gwseq_label_sport"') === false && strpos($labels_html_unknown, 'name="_gwseq_label_sf_genetique_avenir"') === false, 'Rendu (sexe non renseigné) : aucun des deux groupes sexe-dépendants n’est affiché tant que le sexe n’est pas connu');

// =====================================================================================
// 3. Sauvegarde réelle (gwseq_save_cheval_labels_meta(), déclenchée par save_post_gwseq_cheval)
// =====================================================================================

function gws_test_labels_save($post_id, $post_data) {
  $_POST = $post_data;
  gwseq_save_cheval_labels_meta($post_id);
}

// --- (7) Sauvegarde/rechargement SANS interaction avec l’onglet Labels : les valeurs déjà
// enregistrées restent conservées (le formulaire natif WordPress soumet TOUJOURS l’état affiché de
// tous les champs, y compris ceux d’un onglet jamais ouvert — voir cheval-admin-tabs.php, "les
// onglets sont uniquement une couche de présentation") ---
gws_test_make_post(810, GWSEQ_CPT_CHEVAL, 'Jument Sauvegarde Sans Interaction');
gws_test_labels_save(810, array(
  GWSEQ_CHEVAL_NONCE_FIELD => 'stub-nonce',
  '_gwseq_sexe' => 'female',
  '_gwseq_label_sfo' => '1',
  '_gwseq_label_sport' => 'excellente',
));
gws_test_assert(gwseq_get_cheval_labels(810) === array('sfo' => '1', 'sf_genetique_avenir' => '', 'sport' => 'excellente', 'elevage' => 'none', 'modele_allures' => 'none'), '(7) Sauvegarde initiale : les valeurs soumises (SFO + Sport=Excellente) sont bien persistées');
// Rejoue EXACTEMENT le même payload (formulaire réenregistré sans qu’aucun champ Labels n’ait
// changé à l’écran) — les valeurs doivent rester identiques, jamais dérivées ou perdues.
gws_test_labels_save(810, array(
  GWSEQ_CHEVAL_NONCE_FIELD => 'stub-nonce',
  '_gwseq_sexe' => 'female',
  '_gwseq_label_sfo' => '1',
  '_gwseq_label_sport' => 'excellente',
));
gws_test_assert(gwseq_get_cheval_labels(810) === array('sfo' => '1', 'sf_genetique_avenir' => '', 'sport' => 'excellente', 'elevage' => 'none', 'modele_allures' => 'none'), '(7) Sauvegarde/rechargement sans interaction avec les Labels : les valeurs restent conservées à l’identique');

// --- (8) Modification d’un autre onglet (ex. la robe, gérée par cheval-fields.php/save distinct)
// n’affecte jamais les Labels — chaque save_post_gwseq_cheval indépendant lit son propre payload,
// jamais un état partagé qui pourrait interférer ---
gws_test_labels_save(810, array(
  GWSEQ_CHEVAL_NONCE_FIELD => 'stub-nonce',
  '_gwseq_sexe' => 'female',
  '_gwseq_robe' => 'gris',
  '_gwseq_label_sfo' => '1',
  '_gwseq_label_sport' => 'excellente',
));
gws_test_assert(gwseq_get_cheval_labels(810) === array('sfo' => '1', 'sf_genetique_avenir' => '', 'sport' => 'excellente', 'elevage' => 'none', 'modele_allures' => 'none'), '(8) Modification d’un autre champ (robe) dans le même payload : les Labels restent inchangés');

// --- (9) Changement de sexe femelle -> mâle/hongre : nettoyage des labels poulinières devenus
// incompatibles, SFO intact ---
gws_test_make_post(811, GWSEQ_CPT_CHEVAL, 'Jument Devenue Male');
gws_test_labels_save(811, array(
  GWSEQ_CHEVAL_NONCE_FIELD => 'stub-nonce',
  '_gwseq_sexe' => 'female',
  '_gwseq_label_sfo' => '1',
  '_gwseq_label_sport' => 'elite',
  '_gwseq_label_elevage' => 'excellente',
  '_gwseq_label_modele_allures' => 'tres_bonne',
));
gws_test_assert(gwseq_get_cheval_labels(811)['sport'] === 'elite', '(9) État initial (avant changement de sexe) : "Sport=Élite" bien enregistré pour cette femelle');
gws_test_labels_save(811, array(
  GWSEQ_CHEVAL_NONCE_FIELD => 'stub-nonce',
  '_gwseq_sexe' => 'male', // changement de sexe soumis dans CE MÊME payload
  '_gwseq_label_sfo' => '1',
  // Les champs poulinières peuvent encore être présents dans un $_POST réel (onglet Labels jamais
  // rouvert après le changement de sexe, formulaire toujours soumis dans son état précédent) —
  // c'est justement ce que ce test vérifie : ils sont nettoyés MALGRÉ leur présence dans le payload.
  '_gwseq_label_sport' => 'elite',
  '_gwseq_label_elevage' => 'excellente',
  '_gwseq_label_modele_allures' => 'tres_bonne',
));
gws_test_assert(
  gwseq_get_cheval_labels(811) === array('sfo' => '1', 'sf_genetique_avenir' => '', 'sport' => 'none', 'elevage' => 'none', 'modele_allures' => 'none'),
  '(9) Changement de sexe femelle -> mâle : les trois labels poulinières sont bien nettoyés (remis à "none") malgré leur présence dans le payload soumis, SFO reste intact'
);

gws_test_make_post(812, GWSEQ_CPT_CHEVAL, 'Jument Devenue Hongre');
gws_test_labels_save(812, array(GWSEQ_CHEVAL_NONCE_FIELD => 'stub-nonce', '_gwseq_sexe' => 'female', '_gwseq_label_sport' => 'tres_bonne'));
gws_test_labels_save(812, array(GWSEQ_CHEVAL_NONCE_FIELD => 'stub-nonce', '_gwseq_sexe' => 'gelding', '_gwseq_label_sport' => 'tres_bonne'));
gws_test_assert(gwseq_get_cheval_labels(812)['sport'] === 'none', '(9bis) Changement de sexe femelle -> hongre : les labels poulinières sont également nettoyés (pas seulement vers mâle)');

// --- (10) Changement de sexe mâle/hongre -> femelle : nettoyage de SF Génétique Avenir devenu
// incompatible, SFO intact ---
gws_test_make_post(813, GWSEQ_CPT_CHEVAL, 'Etalon Devenu Femelle');
gws_test_labels_save(813, array(
  GWSEQ_CHEVAL_NONCE_FIELD => 'stub-nonce',
  '_gwseq_sexe' => 'male',
  '_gwseq_label_sfo' => '1',
  '_gwseq_label_sf_genetique_avenir' => '1',
));
gws_test_assert(gwseq_get_cheval_labels(813)['sf_genetique_avenir'] === '1', '(10) État initial (avant changement de sexe) : "SF Génétique Avenir" bien enregistré pour ce mâle');
gws_test_labels_save(813, array(
  GWSEQ_CHEVAL_NONCE_FIELD => 'stub-nonce',
  '_gwseq_sexe' => 'female', // changement de sexe soumis dans CE MÊME payload
  '_gwseq_label_sfo' => '1',
  '_gwseq_label_sf_genetique_avenir' => '1', // encore présent dans le payload, doit être ignoré
));
gws_test_assert(
  gwseq_get_cheval_labels(813) === array('sfo' => '1', 'sf_genetique_avenir' => '', 'sport' => 'none', 'elevage' => 'none', 'modele_allures' => 'none'),
  '(10) Changement de sexe mâle -> femelle : "SF Génétique Avenir" est bien nettoyé (remis à vide) malgré sa présence dans le payload soumis, SFO reste intact'
);

// --- (11) SFO reste intact lors d’un changement de sexe, dans les DEUX sens ---
gws_test_make_post(814, GWSEQ_CPT_CHEVAL, 'SFO Intact Femelle Vers Male');
gws_test_labels_save(814, array(GWSEQ_CHEVAL_NONCE_FIELD => 'stub-nonce', '_gwseq_sexe' => 'female', '_gwseq_label_sfo' => '1'));
gws_test_labels_save(814, array(GWSEQ_CHEVAL_NONCE_FIELD => 'stub-nonce', '_gwseq_sexe' => 'male', '_gwseq_label_sfo' => '1'));
gws_test_assert(gwseq_get_cheval_labels(814)['sfo'] === '1', '(11) SFO reste intact lors d’un changement de sexe femelle -> mâle');

gws_test_make_post(815, GWSEQ_CPT_CHEVAL, 'SFO Intact Male Vers Femelle');
gws_test_labels_save(815, array(GWSEQ_CHEVAL_NONCE_FIELD => 'stub-nonce', '_gwseq_sexe' => 'male', '_gwseq_label_sfo' => '1'));
gws_test_labels_save(815, array(GWSEQ_CHEVAL_NONCE_FIELD => 'stub-nonce', '_gwseq_sexe' => 'female', '_gwseq_label_sfo' => '1'));
gws_test_assert(gwseq_get_cheval_labels(815)['sfo'] === '1', '(11bis) SFO reste intact lors d’un changement de sexe mâle -> femelle');

// --- (12bis) Sécurité de la sauvegarde : mêmes garanties que le reste du module (nonce invalide,
// permissions insuffisantes, révision, autosave -> aucune meta écrite) ---
function gws_test_reset_security() {
  $GLOBALS['__gwseq_test_security'] = array('nonce_valid' => true, 'can_edit' => true, 'is_revision' => false);
}

gws_test_make_post(820, GWSEQ_CPT_CHEVAL, 'Securite Nonce');
gws_test_reset_security();
$GLOBALS['__gwseq_test_security']['nonce_valid'] = false;
gws_test_labels_save(820, array(GWSEQ_CHEVAL_NONCE_FIELD => 'stub-nonce', '_gwseq_sexe' => 'female', '_gwseq_label_sfo' => '1'));
gws_test_assert(($GLOBALS['__gwseq_test_meta'][820] ?? array()) === array(), '(12bis) Nonce invalide : aucune meta Labels écrite');

gws_test_make_post(821, GWSEQ_CPT_CHEVAL, 'Securite Permissions');
gws_test_reset_security();
$GLOBALS['__gwseq_test_security']['can_edit'] = false;
gws_test_labels_save(821, array(GWSEQ_CHEVAL_NONCE_FIELD => 'stub-nonce', '_gwseq_sexe' => 'female', '_gwseq_label_sfo' => '1'));
gws_test_assert(($GLOBALS['__gwseq_test_meta'][821] ?? array()) === array(), '(12bis) Permissions insuffisantes : aucune meta Labels écrite');

gws_test_make_post(822, GWSEQ_CPT_CHEVAL, 'Securite Revision');
gws_test_reset_security();
$GLOBALS['__gwseq_test_security']['is_revision'] = true;
gws_test_labels_save(822, array(GWSEQ_CHEVAL_NONCE_FIELD => 'stub-nonce', '_gwseq_sexe' => 'female', '_gwseq_label_sfo' => '1'));
gws_test_assert(($GLOBALS['__gwseq_test_meta'][822] ?? array()) === array(), '(12bis) Révision : aucune meta Labels écrite');
gws_test_reset_security();

echo "\n";
if ($failures === 0) {
  echo "Tous les tests sont passés.\n";
} else {
  echo "$failures test(s) en échec.\n";
}

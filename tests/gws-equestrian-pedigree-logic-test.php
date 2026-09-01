<?php
/**
 * Vérifie le pedigree de `gws-equestrian` (Étape 5, avec le correctif "ascendants externes
 * récursifs") : relations Père/Mère (cheval GWS ou arbre d'ascendants externes structuré),
 * resolver (structure de données, profondeur, cycles, dégradation propre, mélange GWS/externe),
 * production (descendants calculés, jamais stockés), chemin programmatique sans $_POST/nonce, et
 * sécurité de la sauvegarde. Même méthodologie que les étapes précédentes : données à la forme
 * réelle de $_POST, comportement réel des hooks, jamais seulement des helpers appelés avec des
 * valeurs déjà parfaites.
 *
 * Ne fait pas partie des paquets livrés (gws-core.zip / gws-starter.zip).
 */

$failures = 0;
function gws_test_assert($condition, $label) {
  global $failures;
  if ($condition) { echo "OK   - $label\n"; }
  else { echo "FAIL - $label\n"; $failures++; }
}

/**
 * Détecte la cause exacte du bug runtime 0.14.4 ("resultsList=false" au moment de
 * l'initialisation JS, malgré search/codeInput trouvés) : un `<p>` ne peut structurellement JAMAIS
 * contenir un élément de contenu "flow" (spécification HTML5/WHATWG — `<ul>`, `<div>`, `<table>`...
 * liste exhaustive ci-dessous). Un VRAI navigateur ferme IMPLICITEMENT le `<p>` (et tout ce qui est
 * encore ouvert à l'intérieur) dès qu'il rencontre l'un de ces éléments, expulsant tout le reste du
 * contenu prévu hors de la structure attendue — exactement ce qui arrachait le `<ul class="gwseq-
 * race-field__results">` du composant hors de `.gwseq-race-field`. Voir la même fonction dans
 * gws-equestrian-cheval-logic-test.php pour le détail complet (fichiers de test autonomes, sans
 * dépendance partagée, même convention que le reste de cette suite).
 */
function gws_test_assert_no_flow_content_inside_p($html, $label) {
  global $failures;
  $autoclose_p_tags = array('address','article','aside','blockquote','details','div','dl','fieldset','figcaption','figure','footer','form','h1','h2','h3','h4','h5','h6','header','hgroup','hr','main','menu','nav','ol','p','pre','section','table','ul');
  $violation = null;
  if (preg_match_all('/<(\/)?([a-zA-Z][a-zA-Z0-9]*)\b[^>]*>/', $html, $matches, PREG_OFFSET_CAPTURE)) {
    $p_depth = 0;
    foreach ($matches[0] as $i => $full_match) {
      $is_closing = $matches[1][$i][0] === '/';
      $tag_name = strtolower($matches[2][$i][0]);
      if (!$is_closing && $tag_name === 'p') {
        $p_depth++;
        continue;
      }
      if ($is_closing && $tag_name === 'p') {
        $p_depth = max(0, $p_depth - 1);
        continue;
      }
      if ($p_depth > 0 && !$is_closing && in_array($tag_name, $autoclose_p_tags, true)) {
        $violation = $tag_name;
        break;
      }
    }
  }
  gws_test_assert($violation === null, $violation === null
    ? $label
    : "$label (ÉCHEC : <$violation> trouvé à l'intérieur d'un <p> encore ouvert — un vrai navigateur fermerait ce <p> implicitement avant, expulsant tout son contenu prévu restant)");
}

// --- Stubs WordPress minimaux ---
// wp_unslash() FIDÈLE au comportement réel (stripslashes_deep()) — CRUCIAL pour ce fichier :
// c'est précisément parce qu'un stub précédent (ici et dans update_post_meta() ci-dessous)
// faisait un simple passe-plat que le bug bloquant "é" -> "u00e9" n'avait jamais été détecté par
// les 563 assertions déjà vertes avant cette correction. Voir le CR pour l'analyse complète.
function wp_unslash($value) { return is_array($value) ? array_map('wp_unslash', $value) : stripslashes((string) $value); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function sanitize_textarea_field($value) { return trim((string) $value); }
// FIDÈLE au comportement réel de sanitize_key() (WordPress core) : la valeur est mise en
// minuscules AVANT le filtrage des caractères (jamais l'inverse) — un stub filtrant avant de
// mettre en minuscules supprimerait à tort toute lettre majuscule (ex. les codes de race du
// référentiel, tous en MAJUSCULES : "AA", "KWPN"...), un bug resté invisible tant qu'aucune
// donnée réelle ne contenait de majuscule.
function sanitize_key($value) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value)); }
function esc_url_raw($value) { $value = trim((string) $value); return $value === '' ? '' : $value; }
function esc_url($value) { return $value; }
function absint($value) { return abs((int) $value); }
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function sanitize_html_class($value) { return preg_replace('/[^A-Za-z0-9_-]/', '', (string) $value); }
function selected($a, $b) { return $a == $b ? ' selected' : ''; }
function checked($a, $b = true) { return $a == $b ? ' checked' : ''; }
// FIDÈLE au comportement réel de disabled() (WordPress core) : ÉCHO par défaut ($echo = true),
// contrairement à selected()/checked() ci-dessus qui ne sont jamais appelées avec leur résultat
// affiché autrement que via cet écho natif dans le vrai WordPress — un stub non-échoïsant
// masquerait silencieusement l'attribut "disabled" du rendu, comme un stub non fidèle l'a déjà
// fait une fois pour wp_unslash()/update_post_meta() (voir plus haut).
function disabled($value, $compare_value = true, $echo = true) {
  $result = ($value == $compare_value) ? ' disabled' : '';
  if ($echo) echo $result;
  return $result;
}
function wp_parse_args($args, $defaults = array()) { return array_merge((array) $defaults, (array) $args); }
function wp_nonce_field($action, $field) { echo '<input type="hidden" name="' . esc_attr($field) . '" value="stub-nonce">'; }
// Signature fidèle à wp_json_encode() (WordPress core) : le paramètre $options DOIT être
// transmis à json_encode() — un stub à un seul paramètre ignorerait silencieusement
// JSON_UNESCAPED_UNICODE et masquerait exactement le bug corrigé ici.
function wp_json_encode($data, $options = 0, $depth = 512) { return json_encode($data, $options, $depth); }

// --- remove_accents() : natif WordPress, stub couvrant les caractères utilisés par les tests
// (suffisant pour valider le comportement, pas une table de translittération complète) ---
function remove_accents($text) {
  $map = array(
    'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ã' => 'a', 'å' => 'a',
    'À' => 'A', 'Â' => 'A', 'Ä' => 'A', 'Á' => 'A', 'Ã' => 'A', 'Å' => 'A',
    'ç' => 'c', 'Ç' => 'C',
    'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
    'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
    'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
    'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
    'ñ' => 'n', 'Ñ' => 'N',
    'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
    'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Ö' => 'O', 'Õ' => 'O',
    'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
    'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U',
    'ý' => 'y', 'ÿ' => 'y', 'Ý' => 'Y',
    'œ' => 'oe', 'Œ' => 'OE', 'æ' => 'ae', 'Æ' => 'AE',
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
function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {}
function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {}

// --- "Base de données" en mémoire : posts (id => post_type/post_status/post_title) et meta ---
$GLOBALS['__gwseq_test_posts'] = array();
$GLOBALS['__gwseq_test_meta'] = array();

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
function get_edit_post_link($post_id) { return 'https://example.test/wp-admin/post.php?post=' . (int) $post_id . '&action=edit'; }

// FIDÈLE au comportement réel de update_metadata() (WordPress core) : la valeur passe par
// wp_unslash() AVANT stockage, quelle que soit son origine (pas seulement $_POST) — c'est ce
// détail, absent des stubs précédents, qui a laissé passer le bug bloquant de corruption Unicode
// (voir la remarque sur wp_unslash() ci-dessus et le CR de cette correction).
function update_post_meta($post_id, $key, $value) { $GLOBALS['__gwseq_test_meta'][$post_id][$key] = wp_unslash($value); return true; }
function get_post_meta($post_id, $key, $single = false) { return $GLOBALS['__gwseq_test_meta'][$post_id][$key] ?? ''; }
// FIDÈLE au comportement réel de delete_metadata() : la clé disparaît complètement (get_post_meta()
// retombe alors sur '' via ?? '' ci-dessus, exactement comme une meta jamais enregistrée).
function delete_post_meta($post_id, $key) { unset($GLOBALS['__gwseq_test_meta'][$post_id][$key]); return true; }
function metadata_exists($type, $post_id, $key) {
  return array_key_exists($post_id, $GLOBALS['__gwseq_test_meta']) && array_key_exists($key, $GLOBALS['__gwseq_test_meta'][$post_id]);
}

// --- get_posts() avec un vrai support de meta_query (OR de AND, égalité simple) ---
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

// --- Sécurité (nonce/capability/autosave/révision) ---
$GLOBALS['__gwseq_test_security'] = array('nonce_valid' => true, 'can_edit' => true, 'is_revision' => false);
function wp_verify_nonce($nonce, $action) { return $GLOBALS['__gwseq_test_security']['nonce_valid']; }
function current_user_can($cap, $post_id = null) { return $GLOBALS['__gwseq_test_security']['can_edit']; }
function wp_is_post_revision($post_id) { return $GLOBALS['__gwseq_test_security']['is_revision']; }

// --- Environnement et méta boxes ---
$GLOBALS['__gwseq_test_environment'] = 'production';
function wp_get_environment_type() { return $GLOBALS['__gwseq_test_environment']; }
$GLOBALS['__gwseq_test_meta_boxes'] = array();
function add_meta_box($id, $title, $callback, $post_type = null, $context = 'advanced', $priority = 'default') {
  $GLOBALS['__gwseq_test_meta_boxes'][] = $id;
}

// --- Préférences utilisateur (récents du référentiel, correctif référentiel §5-6) et assets ---
$GLOBALS['__gwseq_test_current_user_id'] = 1;
function get_current_user_id() { return $GLOBALS['__gwseq_test_current_user_id']; }
$GLOBALS['__gwseq_test_user_meta'] = array();
function get_user_meta($user_id, $key, $single = false) { return $GLOBALS['__gwseq_test_user_meta'][$user_id][$key] ?? ''; }
function update_user_meta($user_id, $key, $value) { $GLOBALS['__gwseq_test_user_meta'][$user_id][$key] = $value; return true; }
$GLOBALS['__gwseq_enqueued'] = array();
function wp_enqueue_script($handle, ...$rest) { $GLOBALS['__gwseq_enqueued'][] = $handle; }
function wp_enqueue_style($handle, ...$rest) { $GLOBALS['__gwseq_enqueued'][] = $handle; }
function wp_script_is($handle, $status = 'enqueued') { return in_array($handle, $GLOBALS['__gwseq_enqueued'], true); }
function wp_localize_script($handle, $object_name, $data) { $GLOBALS['__gwseq_test_localized'][$handle][$object_name] = $data; }

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

$cheval_pedigree_source = file_get_contents($module_dir . 'includes/cheval-pedigree.php');
$resolver_source = file_get_contents($module_dir . 'includes/pedigree-resolver.php');

// =====================================================================================
// Validation d'une relation GWS (§26)
// =====================================================================================

gws_test_make_post(1, GWSEQ_CPT_CHEVAL, 'Kannan');
gws_test_make_post(2, GWSEQ_CPT_CHEVAL, 'Jamerose');
gws_test_make_post(99, 'page', 'Une page quelconque');

gws_test_assert(gwseq_sanitize_horse_parent_gws_id('1', 2) === 1, 'Relation GWS : un ID pointant vers un vrai cheval est conservé');
gws_test_assert(gwseq_sanitize_horse_parent_gws_id('2', 2) === 0, 'Relation GWS : auto-référence rejetée (un cheval ne peut pas être son propre parent)');
gws_test_assert(gwseq_sanitize_horse_parent_gws_id('12345', 2) === 0, 'Relation GWS : ID inexistant rejeté');
gws_test_assert(gwseq_sanitize_horse_parent_gws_id('99', 2) === 0, 'Relation GWS : ID d’un autre post type (page) rejeté');
gws_test_assert(gwseq_sanitize_horse_parent_gws_id('abc', 2) === 0, 'Relation GWS : valeur non numérique rejetée, jamais d’erreur');

// =====================================================================================
// Intégrité du pedigree — un même cheval GWS ne peut jamais être à la fois père ET mère
// (correctif complémentaire post-recette, 0.9.0). Distinct de l'auto-référence (ci-dessus, déjà
// protégée) : ici, deux relations valides prises séparément (chacune vers un vrai cheval GWS,
// différent du cheval édité) créeraient ensemble une incohérence biologique.
// =====================================================================================

gws_test_make_post(940, GWSEQ_CPT_CHEVAL, 'Cheval Intégrité Racine');
gws_test_make_post(941, GWSEQ_CPT_CHEVAL, 'Étalon Intégrité A');
gws_test_make_post(942, GWSEQ_CPT_CHEVAL, 'Étalon Intégrité B');

// --- Auto-parenté : déjà protégée, revérifiée ici de bout en bout via gwseq_set_horse_parent()
// (et non plus seulement gwseq_sanitize_horse_parent_gws_id() isolément) ---
$result_self_father = gwseq_set_horse_parent(940, 'father', array('mode' => 'gws', 'horse_id' => 940));
gws_test_assert($result_self_father === true, 'Intégrité : gwseq_set_horse_parent() accepte l’appel (auto-référence neutralisée en interne, pas une erreur d’appel)');
gws_test_assert(gwseq_get_horse_parent(940, 'father')['mode'] === '', 'Intégrité : le cheval courant reste impossible comme son propre père (relation restée désactivée)');

$result_self_mother = gwseq_set_horse_parent(940, 'mother', array('mode' => 'gws', 'horse_id' => 940));
gws_test_assert($result_self_mother === true, 'Intégrité : gwseq_set_horse_parent() accepte l’appel pour la mère également (auto-référence neutralisée en interne)');
gws_test_assert(gwseq_get_horse_parent(940, 'mother')['mode'] === '', 'Intégrité : le cheval courant reste impossible comme sa propre mère (relation restée désactivée)');

// --- Même cheval GWS comme père ET comme mère : la seconde affectation est refusée ---
$result_father_A = gwseq_set_horse_parent(940, 'father', array('mode' => 'gws', 'horse_id' => 941));
gws_test_assert($result_father_A === true && gwseq_get_horse_parent(940, 'father')['horse_id'] === 941, 'Intégrité : le père peut être défini normalement sur un premier cheval GWS distinct');

$result_mother_same_as_father = gwseq_set_horse_parent(940, 'mother', array('mode' => 'gws', 'horse_id' => 941));
gws_test_assert($result_mother_same_as_father === false, 'Intégrité : affecter le MÊME cheval GWS comme mère alors qu’il est déjà père est REFUSÉ (valeur de retour false, comportement documenté)');
gws_test_assert(gwseq_get_horse_parent(940, 'mother')['mode'] === '', 'Intégrité : la mère reste désactivée après le refus — aucune valeur incohérente n’a été enregistrée');
gws_test_assert(gwseq_get_horse_parent(940, 'father')['horse_id'] === 941, 'Intégrité : le refus d’affecter la mère ne modifie jamais la relation existante du père');

// --- Ordre inverse : si la mère est déjà établie, le même cheval est refusé comme père ---
gws_test_make_post(943, GWSEQ_CPT_CHEVAL, 'Cheval Intégrité Ordre Inverse');
gwseq_set_horse_parent(943, 'mother', array('mode' => 'gws', 'horse_id' => 941));
$result_father_same_as_mother = gwseq_set_horse_parent(943, 'father', array('mode' => 'gws', 'horse_id' => 941));
gws_test_assert($result_father_same_as_mother === false, 'Intégrité : le refus s’applique dans les deux sens — même cheval déjà mère refusé comme père');
gws_test_assert(gwseq_get_horse_parent(943, 'father')['mode'] === '', 'Intégrité : le père reste désactivé après ce refus (ordre inverse)');

// --- Une relation EXISTANTE côté père n'est jamais silencieusement effacée par une tentative
// refusée côté mère : elle reste EXACTEMENT celle enregistrée avant la tentative ---
gws_test_make_post(944, GWSEQ_CPT_CHEVAL, 'Cheval Intégrité Non Écrasement');
gwseq_set_horse_parent(944, 'father', array('mode' => 'gws', 'horse_id' => 941));
gwseq_set_horse_parent(944, 'mother', array('mode' => 'gws', 'horse_id' => 942));
gwseq_set_horse_parent(944, 'mother', array('mode' => 'gws', 'horse_id' => 941)); // refusé
$relation_944_mother = gwseq_get_horse_parent(944, 'mother');
gws_test_assert($relation_944_mother['mode'] === 'gws' && $relation_944_mother['horse_id'] === 942, 'Intégrité : une tentative refusée ne supprime ni ne remplace silencieusement une relation mère déjà valide (elle reste celle enregistrée juste avant)');

// --- Deux chevaux GWS différents : acceptés normalement (cas nominal, aucune régression) ---
gws_test_make_post(945, GWSEQ_CPT_CHEVAL, 'Cheval Intégrité Deux Parents Distincts');
$result_father_distinct = gwseq_set_horse_parent(945, 'father', array('mode' => 'gws', 'horse_id' => 941));
$result_mother_distinct = gwseq_set_horse_parent(945, 'mother', array('mode' => 'gws', 'horse_id' => 942));
$relation_945 = array('father' => gwseq_get_horse_parent(945, 'father'), 'mother' => gwseq_get_horse_parent(945, 'mother'));
gws_test_assert($result_father_distinct === true && $result_mother_distinct === true, 'Intégrité : deux chevaux GWS différents comme père et mère sont acceptés sans restriction');
gws_test_assert($relation_945['father']['horse_id'] === 941 && $relation_945['mother']['horse_id'] === 942, 'Intégrité : les deux relations distinctes sont bien actives simultanément');

// --- Père GWS + mère externe : accepté (l'ascendant externe n'a pas d'identifiant de fiche, ne
// peut jamais entrer en conflit avec une relation GWS) ---
gws_test_make_post(946, GWSEQ_CPT_CHEVAL, 'Cheval Intégrité GWS Plus Externe');
$result_father_gws_mixed = gwseq_set_horse_parent(946, 'father', array('mode' => 'gws', 'horse_id' => 941));
$result_mother_external_mixed = gwseq_set_horse_parent(946, 'mother', array('mode' => 'external', 'external' => array('name' => 'Étalon Intégrité A')));
gws_test_assert($result_father_gws_mixed === true && $result_mother_external_mixed === true, 'Intégrité : père GWS + mère externe (même nom qu’un cheval GWS existant) accepté sans restriction');
$relation_946_mother = gwseq_get_horse_parent(946, 'mother');
gws_test_assert($relation_946_mother['mode'] === 'external' && $relation_946_mother['external']['name'] === 'Étalon Intégrité A', 'Intégrité : un ascendant externe portant le même NOM qu’un cheval GWS n’est jamais comparé par ce nom — aucun rapprochement, aucun refus');

// --- Père externe + mère GWS : symétriquement accepté ---
gws_test_make_post(947, GWSEQ_CPT_CHEVAL, 'Cheval Intégrité Externe Plus GWS');
$result_father_external_mixed = gwseq_set_horse_parent(947, 'father', array('mode' => 'external', 'external' => array('name' => 'Étalon Intégrité B')));
$result_mother_gws_mixed = gwseq_set_horse_parent(947, 'mother', array('mode' => 'gws', 'horse_id' => 942));
gws_test_assert($result_father_external_mixed === true && $result_mother_gws_mixed === true, 'Intégrité : père externe (même nom qu’un cheval GWS existant) + mère GWS accepté sans restriction');

// --- La protection ne dépend d'AUCUN JavaScript : ces mêmes appels programmatiques directs à
// gwseq_set_horse_parent() (sans $_POST, sans nonce) sont exactement le chemin qu'un futur
// importeur CSV/XLSX emprunterait — la validation ci-dessus s'applique donc identiquement là ---
gws_test_make_post(948, GWSEQ_CPT_CHEVAL, 'Cheval Intégrité Import Simulé');
$import_father = gwseq_set_horse_parent(948, 'father', array('mode' => 'gws', 'horse_id' => 941));
$import_mother_conflict = gwseq_set_horse_parent(948, 'mother', array('mode' => 'gws', 'horse_id' => 941));
gws_test_assert($import_father === true && $import_mother_conflict === false, 'Intégrité : la validation s’applique identiquement à un appel programmatique direct (chemin d’un futur importeur), sans dépendre de $_POST ni de JavaScript');

// --- Aucune régression : Production toujours calculée correctement malgré la nouvelle validation ---
// (941 est actif comme père GWS de plusieurs chevaux créés plus haut, dont 940 — voir la
// vérification "Production" attendue depuis 941)
gws_test_assert(
  in_array(940, array_map(function ($p) { return $p->ID; }, gwseq_get_horse_offspring(941)), true),
  'Intégrité : aucune régression sur la Production — un cheval GWS actif comme père d’un autre reste bien listé comme producteur'
);

// --- Aucune régression : changement de mode et conservation non destructive toujours valides —
// passer une relation "father" de gws (941) à externe ne touche jamais la mère, et l'ancien
// identifiant GWS reste conservé, inactif, exactement comme avant ce correctif ---
gws_test_make_post(949, GWSEQ_CPT_CHEVAL, 'Cheval Intégrité Conservation');
gwseq_set_horse_parent(949, 'father', array('mode' => 'gws', 'horse_id' => 941));
gwseq_set_horse_parent(949, 'mother', array('mode' => 'gws', 'horse_id' => 942));
gwseq_set_horse_parent(949, 'father', array('mode' => 'external', 'external' => array('name' => 'Nouvel Étalon Externe')));
$relation_949_father = gwseq_get_horse_parent(949, 'father');
$relation_949_mother = gwseq_get_horse_parent(949, 'mother');
gws_test_assert($relation_949_father['mode'] === 'external' && $relation_949_father['horse_id'] === 941, 'Intégrité : changement de mode GWS -> externe toujours fonctionnel, ancien ID GWS conservé inactif (conservation non destructive inchangée)');
gws_test_assert($relation_949_mother['mode'] === 'gws' && $relation_949_mother['horse_id'] === 942, 'Intégrité : la mère n’est jamais affectée par un changement de mode du père (aucune régression)');
// L'ancien identifiant du père (941) redevient maintenant librement réutilisable comme mère,
// puisque le père n'est plus actif en mode "gws" sur ce cheval :
$result_mother_reuse_old_father_id = gwseq_set_horse_parent(949, 'mother', array('mode' => 'gws', 'horse_id' => 941));
gws_test_assert($result_mother_reuse_old_father_id === true, 'Intégrité : un identifiant GWS redevient utilisable pour l’autre rôle dès que la relation qui l’utilisait n’est plus active en mode "gws" (la vérification porte sur l’état ACTIF, jamais sur l’historique)');

// =====================================================================================
// Filtrage métier des parents GWS — sexe et année de naissance (correctif complémentaire
// post-recette, 0.10.0). Ne concerne QUE les relations GWS, jamais les ascendants externes.
// =====================================================================================

gws_test_make_post(960, GWSEQ_CPT_CHEVAL, 'Produit Filtrage 2020');
$GLOBALS['__gwseq_test_meta'][960] = array('_gwseq_annee_naissance' => 2020);

gws_test_make_post(961, GWSEQ_CPT_CHEVAL, 'Jument Filtrage');
$GLOBALS['__gwseq_test_meta'][961] = array('_gwseq_sexe' => 'female', '_gwseq_annee_naissance' => 2010);

gws_test_make_post(962, GWSEQ_CPT_CHEVAL, 'Entier Filtrage');
$GLOBALS['__gwseq_test_meta'][962] = array('_gwseq_sexe' => 'male', '_gwseq_annee_naissance' => 2010);

gws_test_make_post(963, GWSEQ_CPT_CHEVAL, 'Hongre Filtrage');
$GLOBALS['__gwseq_test_meta'][963] = array('_gwseq_sexe' => 'gelding', '_gwseq_annee_naissance' => 2012);

gws_test_make_post(964, GWSEQ_CPT_CHEVAL, 'Sexe Inconnu Filtrage');
$GLOBALS['__gwseq_test_meta'][964] = array('_gwseq_annee_naissance' => 2015);

// --- §1 : femelle refusée comme père, acceptée comme mère ---
gws_test_assert(gwseq_horse_parent_candidate_rejection_reason(960, 'father', 961) === 'sexe', 'Filtrage sexe : une jument est refusée comme père');
gws_test_assert(gwseq_horse_parent_candidate_rejection_reason(960, 'mother', 961) === '', 'Filtrage sexe : une jument est acceptée comme mère');

// --- §1 : mâle/entier accepté comme père, refusé comme mère ---
gws_test_assert(gwseq_horse_parent_candidate_rejection_reason(960, 'father', 962) === '', 'Filtrage sexe : un entier est accepté comme père');
gws_test_assert(gwseq_horse_parent_candidate_rejection_reason(960, 'mother', 962) === 'sexe', 'Filtrage sexe : un entier est refusé comme mère');

// --- §1 : hongre accepté comme père (a pu reproduire avant castration), refusé comme mère ---
gws_test_assert(gwseq_horse_parent_candidate_rejection_reason(960, 'father', 963) === '', 'Filtrage sexe : un hongre est accepté comme père');
gws_test_assert(gwseq_horse_parent_candidate_rejection_reason(960, 'mother', 963) === 'sexe', 'Filtrage sexe : un hongre est refusé comme mère');

// --- §1 : sexe inconnu accepté dans les deux rôles ---
gws_test_assert(gwseq_horse_parent_candidate_rejection_reason(960, 'father', 964) === '', 'Filtrage sexe : un sexe non renseigné est accepté comme père');
gws_test_assert(gwseq_horse_parent_candidate_rejection_reason(960, 'mother', 964) === '', 'Filtrage sexe : un sexe non renseigné est accepté comme mère');

// --- §2 : parent né avant le produit accepté ; même année refusée ; parent plus jeune refusé ---
gws_test_make_post(965, GWSEQ_CPT_CHEVAL, 'Candidat Né Avant');
$GLOBALS['__gwseq_test_meta'][965] = array('_gwseq_sexe' => 'male', '_gwseq_annee_naissance' => 2010);
gws_test_assert(gwseq_horse_parent_candidate_rejection_reason(960, 'father', 965) === '', 'Filtrage année : un candidat né strictement avant le produit (2010 < 2020) est accepté');

gws_test_make_post(966, GWSEQ_CPT_CHEVAL, 'Candidat Même Année');
$GLOBALS['__gwseq_test_meta'][966] = array('_gwseq_sexe' => 'male', '_gwseq_annee_naissance' => 2020);
gws_test_assert(gwseq_horse_parent_candidate_rejection_reason(960, 'father', 966) === 'annee', 'Filtrage année : un candidat né la MÊME année que le produit est refusé');

gws_test_make_post(967, GWSEQ_CPT_CHEVAL, 'Candidat Né Après');
$GLOBALS['__gwseq_test_meta'][967] = array('_gwseq_sexe' => 'gelding', '_gwseq_annee_naissance' => 2021);
gws_test_assert(gwseq_horse_parent_candidate_rejection_reason(960, 'father', 967) === 'annee', 'Filtrage année : un candidat né APRÈS le produit (2021 > 2020) est refusé');

// --- §2 : année du candidat inconnue -> toujours autorisé (l'absence de donnée n'est jamais une
// interdiction), y compris pour un mâle dont l'année est inconnue (exemple exact de la demande) ---
gws_test_make_post(968, GWSEQ_CPT_CHEVAL, 'Candidat Année Inconnue');
$GLOBALS['__gwseq_test_meta'][968] = array('_gwseq_sexe' => 'male');
gws_test_assert(gwseq_horse_parent_candidate_rejection_reason(960, 'father', 968) === '', 'Filtrage année : un candidat dont l’année de naissance n’est pas renseignée reste autorisé (ici un mâle, exemple exact de la demande)');

// --- §2 : année du PRODUIT inconnue -> aucune restriction d'âge, quel que soit le candidat ---
gws_test_make_post(969, GWSEQ_CPT_CHEVAL, 'Produit Année Inconnue');
gws_test_make_post(970, GWSEQ_CPT_CHEVAL, 'Candidat Née Après Mais Produit Sans Année');
$GLOBALS['__gwseq_test_meta'][970] = array('_gwseq_sexe' => 'male', '_gwseq_annee_naissance' => 2099);
gws_test_assert(gwseq_horse_parent_candidate_rejection_reason(969, 'father', 970) === '', 'Filtrage année : le produit n’a pas d’année renseignée -> aucun filtre d’année appliqué, même pour un candidat né très tard');

// --- §3 : combinaison sexe + année — exemple exact de la demande (produit né en 2020) ---
gws_test_assert(gwseq_horse_parent_candidate_rejection_reason(960, 'father', 964) === '', 'Combinaison (exemple demande) : un cheval au sexe et à l’année inconnus est accepté comme père');
gws_test_make_post(971, GWSEQ_CPT_CHEVAL, 'Sexe Et Année Inconnus');
gws_test_assert(gwseq_horse_parent_candidate_rejection_reason(960, 'father', 971) === '', 'Combinaison (exemple demande) : un cheval dont sexe ET année sont inconnus est accepté');
gws_test_assert(gwseq_horse_parent_candidate_rejection_reason(960, 'father', 961) !== '' , 'Combinaison : une jument reste refusée comme père même si son année est compatible (2010 < 2020)');

// --- §3 : la combinaison s'applique aussi via gwseq_set_horse_parent() (persistance réelle) ---
gws_test_make_post(972, GWSEQ_CPT_CHEVAL, 'Cheval Persistance Filtrage');
$GLOBALS['__gwseq_test_meta'][972] = array('_gwseq_annee_naissance' => 2020);
$result_sexe_refused = gwseq_set_horse_parent(972, 'father', array('mode' => 'gws', 'horse_id' => 961));
gws_test_assert($result_sexe_refused === false, 'Persistance : l’enregistrement d’une jument comme père est refusé (retour false)');
gws_test_assert(gwseq_get_horse_parent(972, 'father')['mode'] === '', 'Persistance : aucune relation incohérente n’a été enregistrée pour le père');

$result_annee_refused = gwseq_set_horse_parent(972, 'mother', array('mode' => 'gws', 'horse_id' => 966));
gws_test_assert($result_annee_refused === false, 'Persistance : l’enregistrement d’un parent né la même année que le produit est refusé (retour false)');
gws_test_assert(gwseq_get_horse_parent(972, 'mother')['mode'] === '', 'Persistance : aucune relation incohérente n’a été enregistrée pour la mère');

// --- Aucune écriture partielle : une tentative refusée sur le sexe/l'année ne modifie ni ne
// supprime une relation déjà valide existante ---
gwseq_set_horse_parent(972, 'father', array('mode' => 'gws', 'horse_id' => 962)); // valide : entier né en 2010
$result_overwrite_attempt = gwseq_set_horse_parent(972, 'father', array('mode' => 'gws', 'horse_id' => 961)); // jument : refusé
gws_test_assert($result_overwrite_attempt === false, 'Persistance : la seconde tentative (jument) est bien refusée');
$relation_972_father = gwseq_get_horse_parent(972, 'father');
gws_test_assert($relation_972_father['mode'] === 'gws' && $relation_972_father['horse_id'] === 962, 'Persistance : la relation valide précédente (l’entier) n’est ni supprimée ni remplacée par la tentative refusée');

// --- Auto-parenté et conflit père/mère toujours protégés, combinés avec les nouvelles règles ---
gws_test_assert(gwseq_horse_parent_candidate_rejection_reason(960, 'father', 960) === 'self', 'Combinaison : l’auto-parenté reste toujours impossible, filtrage sexe/année inclus');
// 964 (sexe inconnu, née en 2015) est compatible en sexe ET en année pour les DEUX rôles de 972 —
// seul le conflit "déjà l'autre parent" doit alors expliquer le refus, isolant bien cette règle
// des règles de sexe/année :
gwseq_set_horse_parent(972, 'mother', array('mode' => 'gws', 'horse_id' => 964)); // valide, distinct du père (962)
gws_test_assert(gwseq_get_horse_parent(972, 'mother')['horse_id'] === 964, 'Combinaison : la mère (sexe/année inconnus, donc compatible) est bien enregistrée');
gws_test_assert(gwseq_horse_parent_candidate_rejection_reason(972, 'father', 964) === 'other_role', 'Combinaison : le même cheval GWS reste impossible comme père ET mère, même compatible en sexe/année (964 est déjà mère)');

// --- Deux parents différents, tous deux compatibles (sexe + année) : acceptés normalement ---
gws_test_make_post(973, GWSEQ_CPT_CHEVAL, 'Cheval Deux Parents Compatibles');
$GLOBALS['__gwseq_test_meta'][973] = array('_gwseq_annee_naissance' => 2020);
$result_compatible_father = gwseq_set_horse_parent(973, 'father', array('mode' => 'gws', 'horse_id' => 962));
$result_compatible_mother = gwseq_set_horse_parent(973, 'mother', array('mode' => 'gws', 'horse_id' => 961));
gws_test_assert($result_compatible_father === true && $result_compatible_mother === true, 'Combinaison : un entier compatible comme père et une jument compatible comme mère sont tous deux acceptés');

// --- §7 : les ascendants externes ne sont jamais concernés par ce filtrage (pas de champ sexe, pas
// de comparaison par nom, pas des contraintes des chevaux GWS) ---
gws_test_make_post(974, GWSEQ_CPT_CHEVAL, 'Cheval Externe Non Affecte');
$GLOBALS['__gwseq_test_meta'][974] = array('_gwseq_annee_naissance' => 2020);
$result_external_unaffected = gwseq_set_horse_parent(974, 'father', array('mode' => 'external', 'external' => array('name' => 'Jument Filtrage', 'race' => '')));
gws_test_assert($result_external_unaffected === true, 'Externe : un ascendant externe portant le même nom qu’une jument GWS incompatible est accepté sans restriction (aucune comparaison par nom, aucune contrainte de sexe/année appliquée)');

// --- §5 : validation identique via un appel programmatique direct, sans JavaScript ---
gws_test_make_post(975, GWSEQ_CPT_CHEVAL, 'Cheval Import Filtrage');
$GLOBALS['__gwseq_test_meta'][975] = array('_gwseq_annee_naissance' => 2020);
$import_sexe_refused = gwseq_set_horse_parent(975, 'father', array('mode' => 'gws', 'horse_id' => 961));
$import_annee_refused = gwseq_set_horse_parent(975, 'father', array('mode' => 'gws', 'horse_id' => 966));
gws_test_assert($import_sexe_refused === false && $import_annee_refused === false, 'Programmatique : un futur importeur ne peut pas créer, via gwseq_set_horse_parent(), une relation que l’interface WordPress aurait refusée (sexe ou année incompatible)');

// --- Aucune régression : Production, changement de mode et conservation des branches externes ---
gws_test_assert(
  in_array(973, array_map(function ($p) { return $p->ID; }, gwseq_get_horse_offspring(962)), true),
  'Aucune régression : Production toujours calculée correctement malgré le nouveau filtrage sexe/année'
);
gwseq_set_horse_parent(973, 'father', array('mode' => 'external', 'external' => array('name' => 'Nouvel Ascendant Filtrage')));
$relation_973_father_switched = gwseq_get_horse_parent(973, 'father');
gws_test_assert($relation_973_father_switched['mode'] === 'external' && $relation_973_father_switched['horse_id'] === 962, 'Aucune régression : changement de mode GWS -> externe toujours fonctionnel, ancien ID GWS conservé inactif, malgré le nouveau filtrage');

// --- Rendu admin : options désactivées avec indication de la raison, verrouillage sexe/année ---
ob_start();
gwseq_render_cheval_parent_fields(gws_test_make_post_object(960), 'father', 'Père');
$father_select_html = ob_get_clean();
gws_test_assert(preg_match('/<option value="961"[^>]*\bdisabled\b[^>]*>[^<]*sexe incompatible/u', $father_select_html) === 1, 'Rendu UX : la jument apparaît désactivée dans le sélecteur Père, avec l’indication "sexe incompatible"');
gws_test_assert(preg_match('/<option value="961"[^>]*data-gwseq-locked-disabled="1"/', $father_select_html) === 1, 'Rendu UX : l’option désactivée pour sexe incompatible porte bien l’attribut de verrouillage (le script ne doit jamais la réactiver)');
gws_test_assert(preg_match('/<option value="966"[^>]*\bdisabled\b[^>]*>[^<]*année incompatible/u', $father_select_html) === 1, 'Rendu UX : un candidat né la même année apparaît désactivé avec l’indication "année incompatible"');
preg_match('/<option value="962"[^>]*>/', $father_select_html, $option_962_match);
gws_test_assert(isset($option_962_match[0]) && strpos($option_962_match[0], 'disabled') === false, 'Rendu UX : un candidat compatible (entier né avant le produit) reste normalement sélectionnable, sans attribut disabled');

// =====================================================================================
// Sanitation récursive d'un arbre d'ascendants externes (§2-4, §11, §16 du correctif)
// =====================================================================================

// --- Ascendant externe simple, sans ascendants propres — le code de race saisi (n'importe quelle
// casse) est résolu au CODE CANONIQUE exact du référentiel (ex. "kwpn" -> "KWPN"), jamais stocké
// tel quel avec une casse divergente (§ correctif référentiel : gwseq_sanitize_race_referentiel_code(),
// mutualisée avec l'identité du cheval) ---
$tree = gwseq_sanitize_external_ancestor_tree(array('name' => 'Kannan', 'race' => 'kwpn'), 3);
gws_test_assert($tree === array('name' => 'Kannan', 'race' => 'KWPN', 'race_autre' => '', 'annee_naissance' => '', 'father' => null, 'mother' => null), 'Ascendant externe simple : nom et race (référentiel mutualisé, code canonique) conservés, aucun ascendant propre fabriqué');

// --- Race facultative ---
$tree = gwseq_sanitize_external_ancestor_tree(array('name' => 'Voltaire'), 3);
gws_test_assert($tree['race'] === '' && $tree['race_autre'] === '', 'Ascendant externe : race facultative, absente -> chaîne vide');

// --- Sans nom : aucun nœud, même si un père/une mère étaient fournis (§25) ---
$tree = gwseq_sanitize_external_ancestor_tree(array('race' => 'kwpn', 'father' => array('name' => 'Un Père')), 3);
gws_test_assert($tree === null, 'Ascendant externe sans nom : aucun nœud stocké, y compris si un sous-arbre était fourni');

// --- Race inconnue/invalide : jamais stockée telle quelle, comme n'importe quel autre enum du
// module (§1 : même logique que pour la fiche Cheval) ---
$tree = gwseq_sanitize_external_ancestor_tree(array('name' => 'Kannan', 'race' => 'stud-book-invente'), 3);
gws_test_assert($tree['race'] === '', 'Ascendant externe : code de race inconnu rejeté, jamais stocké tel quel');

// --- "Autre" avec précision libre, même mécanisme que la fiche Cheval ---
$tree = gwseq_sanitize_external_ancestor_tree(array('name' => 'Kannan', 'race' => 'autre', 'race_autre' => 'Camargue'), 3);
gws_test_assert($tree['race'] === 'autre' && $tree['race_autre'] === 'Camargue', 'Ascendant externe : "Autre" avec précision libre conservé');

// --- Ascendant externe possédant deux parents externes ---
$tree = gwseq_sanitize_external_ancestor_tree(array(
  'name' => 'Kannan', 'race' => 'kwpn',
  'father' => array('name' => 'Voltaire', 'race' => 'han'),
  'mother' => array('name' => 'Cemeta', 'race' => 'trak'),
), 3);
gws_test_assert($tree['father']['name'] === 'Voltaire' && $tree['father']['race'] === 'HAN' && $tree['mother']['name'] === 'Cemeta' && $tree['mother']['race'] === 'TRAK', 'Ascendant externe avec deux parents externes : les deux sont conservés, chacun avec sa propre race (code canonique) du référentiel');

// --- Référentiel réellement mutualisé avec la fiche Cheval (correctif référentiel, §1/§8/§13 de la
// demande) : le référentiel Race/Stud-book/Appellation (race-referentiel.php, 154 entrées) est
// l'UNIQUE source de vérité, utilisée à l'identique pour l'identité du cheval et chaque génération
// d'ascendant externe — aucune seconde liste codée en dur dans cheval-pedigree.php ---
foreach (gwseq_race_referentiel_entries() as $entry) {
  $code = $entry['code'];
  $tree = gwseq_sanitize_external_ancestor_tree(array('name' => 'Test', 'race' => $code), 3);
  gws_test_assert($tree['race'] === $code, "Référentiel mutualisé : le code de race/appellation \"$code\" du référentiel est accepté tel quel (code canonique) pour un ascendant externe");
}
gws_test_assert(count(gwseq_race_referentiel_entries()) === 154, 'Référentiel : le nombre d’entrées correspond exactement au référentiel source fourni (154 races/appellations)');
gws_test_assert(
  !preg_match('/function\s+gwseq_race_referentiel_raw_entries/', $cheval_pedigree_source),
  'Aucune seconde liste de races/stud-books/appellations codée en dur dans cheval-pedigree.php (le référentiel vient uniquement de includes/race-referentiel.php)'
);

// --- Branche externe partiellement renseignée : père rempli, mère absente ---
$tree = gwseq_sanitize_external_ancestor_tree(array(
  'name' => 'Kannan',
  'father' => array('name' => 'Voltaire'),
), 3);
gws_test_assert($tree['father']['name'] === 'Voltaire' && $tree['mother'] === null, 'Branche externe partiellement renseignée : le père est conservé, la mère reste null (jamais fabriquée)');

// --- Branche externe complète sur plusieurs générations (3 niveaux : L1 à L3, correctif
// référentiel §10 — la profondeur standard GWS est désormais de 3 générations, 14 ascendants,
// alignée sur la fiche IFCE) ---
$deep_input = array(
  'name' => 'L1',
  'father' => array(
    'name' => 'L2',
    'father' => array(
      'name' => 'L3',
      'father' => array('name' => 'L4 (ne doit jamais apparaître)'),
    ),
  ),
);
$tree = gwseq_sanitize_external_ancestor_tree($deep_input, GWSEQ_PEDIGREE_MAX_DEPTH - 1);
gws_test_assert(
  $tree['name'] === 'L1' && $tree['father']['name'] === 'L2' && $tree['father']['father']['name'] === 'L3',
  'Branche externe complète : les 3 générations autorisées (L1 à L3) sont bien conservées'
);
gws_test_assert(
  $tree['father']['father']['father'] === null && $tree['father']['father']['mother'] === null,
  'Génération 3 : nœud strictement terminal à la sanitation, father/mother restent à null (la génération 4 n’est jamais fabriquée)'
);
gws_test_assert(
  strpos(json_encode($tree), 'L4') === false,
  'Génération 4 (correctif référentiel §10-11) : silencieusement ignorée à la sanitation, jamais stockée nulle part dans l’arbre, jamais de contournement de la limite serveur'
);

// --- Sanitation récursive : caractères spéciaux conservés à un niveau imbriqué (nom, et
// précision libre "Autre" pour la race) ---
$tree = gwseq_sanitize_external_ancestor_tree(array(
  'name' => 'Kannan',
  'father' => array('name' => "L'Étalon d'Or", 'race' => 'autre', 'race_autre' => "Race d'origine espagnole"),
), 3);
gws_test_assert($tree['father']['name'] === "L'Étalon d'Or" && $tree['father']['race_autre'] === "Race d'origine espagnole", 'Sanitation récursive : caractères spéciaux (apostrophes) conservés à un niveau imbriqué, nom et précision "Autre"');

// --- Donnée mal formée à un niveau imbriqué : repli sûr, jamais d'erreur ---
$tree = gwseq_sanitize_external_ancestor_tree(array('name' => 'Kannan', 'father' => 'pas un tableau'), 3);
gws_test_assert($tree['father'] === null, 'Sanitation récursive : une valeur mal formée à un niveau imbriqué (pas un tableau) -> aucun nœud, jamais d’erreur');

// =====================================================================================
// CORRECTIF BLOQUANT — corruption Unicode d'un nom accentué (bug constaté en recette réelle)
//
// CAUSE RACINE EXACTE : gwseq_set_horse_parent() encodait l'arbre externe avec
// wp_json_encode($tree) SANS le drapeau JSON_UNESCAPED_UNICODE. json_encode() sans ce drapeau
// échappe tout caractère non-ASCII en séquence littérale "\uXXXX" (ex. "é" -> les 6 caractères
// \, u, 0, 0, e, 9). Cette chaîne JSON — qui contient donc un antislash réel — est ensuite passée
// à update_post_meta(), lequel appelle EN INTERNE wp_unslash() sur la valeur avant stockage
// (comportement natif de update_metadata() dans WordPress, totalement indépendant de ce module).
// wp_unslash() ne sait pas distinguer "un antislash issu des magic quotes à retirer" d'"un
// antislash faisant partie du contenu légitime" : il retire le antislash de "é", laissant le
// texte littéral "u00e9" — une chaîne JSON syntaxiquement valide (donc json_decode() ne lève
// jamais d'erreur), mais dont le contenu est désormais faux. Une fois ce nom corrompu relu et
// réaffiché dans le champ Nom, un nouvel enregistrement le fige définitivement dans cet état :
// la corruption devient permanente. AUCUN rapport avec gwseq_format_horse_name_display() (la
// fonction de présentation) : elle se contente de mettre en majuscules une donnée déjà corrompue
// en amont — "u00e9" en majuscules donne "U00E9", exactement le symptôme observé.
//
// CORRECTIF : wp_json_encode($tree, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES). Avec ce
// drapeau, "é" est écrit tel quel (aucun antislash), donc rien que wp_unslash() puisse corrompre.
//
// Les stubs wp_unslash()/update_post_meta()/wp_json_encode() de ce fichier ont été rendus
// fidèles au comportement réel de WordPress (stripslashes réel, options JSON réellement
// transmises) précisément pour que ce bug soit reproductible — et donc vérifiable — sans
// WordPress réel : un stub "passe-plat" est ce qui a laissé passer ce bug à travers 563
// assertions déjà vertes avant cette correction.
// =====================================================================================

gws_test_make_post(950, GWSEQ_CPT_CHEVAL, 'A Un Ascendant Accentué');

// --- La donnée source survit intacte à un enregistrement ---
gwseq_set_horse_parent(950, 'father', array('mode' => 'external', 'external' => array('name' => 'Native de Félines')));
$relation = gwseq_get_horse_parent(950, 'father');
gws_test_assert($relation['external']['name'] === 'Native de Félines', 'Correctif Unicode : la donnée source ("Native de Félines") est restituée exactement après un enregistrement, aucune séquence "\uXXXX" échappée résiduelle');
gws_test_assert(strpos($relation['external']['name'], 'u00e9') === false && strpos($relation['external']['name'], '\\u00e9') === false, 'Correctif Unicode : ni "u00e9" ni "\\u00e9" n’apparaît dans la donnée source relue');

// --- Le JSON brut stocké contient bien le caractère accentué littéral, jamais une séquence
// échappée (vérification directement sur la valeur telle qu'écrite en base) ---
$raw_stored_json = $GLOBALS['__gwseq_test_meta'][950]['_gwseq_pere_externe'];
gws_test_assert(strpos($raw_stored_json, 'é') !== false, 'Correctif Unicode : le JSON stocké en base contient le caractère "é" littéral (JSON_UNESCAPED_UNICODE), pas une séquence échappée');
gws_test_assert(strpos($raw_stored_json, '\\u00e9') === false, 'Correctif Unicode : le JSON stocké en base ne contient aucune séquence "\\u00e9" échappée qui serait vulnérable à un futur wp_unslash()');

// --- La donnée source survit intacte à PLUSIEURS enregistrements consécutifs (§6 de la demande :
// "sauvegarde 1 / sauvegarde 2 / sauvegarde 3", noir sur blanc) ---
for ($i = 1; $i <= 3; $i++) {
  gwseq_set_horse_parent(950, 'father', array('mode' => 'external', 'external' => array('name' => 'Native de Félines')));
  $relation = gwseq_get_horse_parent(950, 'father');
  gws_test_assert($relation['external']['name'] === 'Native de Félines', "Correctif Unicode : la source reste \"Native de Félines\" après l’enregistrement n°$i (aucune corruption cumulative)");
}

// --- Non-altération à travers un changement de mode et une modification de branche (§2 de la
// demande) : la donnée accentuée reste intacte même après avoir été rendue inactive puis
// réactivée ---
gwseq_set_horse_parent(950, 'father', array('mode' => 'gws', 'horse_id' => 10));
gwseq_set_horse_parent(950, 'father', array('mode' => 'external', 'external' => array('name' => 'Native de Félines', 'race' => 'sf')));
$relation = gwseq_get_horse_parent(950, 'father');
gws_test_assert($relation['external']['name'] === 'Native de Félines' && $relation['external']['race'] === 'SF', 'Correctif Unicode : la source reste intacte après un changement de mode aller-retour (GWS -> externe) et l’ajout d’une race (résolue au code canonique du référentiel)');

// --- Un nom accentué imbriqué à une génération profonde (pas seulement au premier niveau) reste
// intact lui aussi ---
gws_test_make_post(951, GWSEQ_CPT_CHEVAL, 'A Un Pedigree Accentué Profond');
gwseq_set_horse_parent(951, 'father', array('mode' => 'external', 'external' => array(
  'name' => 'Étalon Racine',
  'father' => array('name' => 'Père Accentué : Aimé', 'mother' => array('name' => 'Arrière-grand-mère : Bérénice')),
)));
$relation = gwseq_get_horse_parent(951, 'father');
gws_test_assert(
  $relation['external']['name'] === 'Étalon Racine'
    && $relation['external']['father']['name'] === 'Père Accentué : Aimé'
    && $relation['external']['father']['mother']['name'] === 'Arrière-grand-mère : Bérénice',
  'Correctif Unicode : des noms accentués à plusieurs niveaux imbriqués (pas seulement le premier) restent tous intacts'
);

// --- Le helper de présentation reste correct sur un exemple élargi (§5 de la demande), et
// n'altère jamais la donnée source qu'on continue de lire séparément ---
foreach (array(
  'Félines' => 'FELINES',
  'Étoile' => 'ETOILE',
  'Hélios' => 'HELIOS',
  'À bientôt' => 'A BIENTOT',
  'Crème Brûlée' => 'CREME BRULEE',
  "L'Arc" => "L'ARC",
  'Native de Félines' => 'NATIVE DE FELINES',
) as $source => $expected_display) {
  $display = gwseq_format_horse_name_display($source);
  gws_test_assert($display === $expected_display, "Helper de présentation : \"$source\" -> \"$expected_display\"");
  gws_test_assert(strpos($display, 'u00') === false, "Helper de présentation : le résultat pour \"$source\" ne contient aucune séquence Unicode échappée résiduelle");
}

// --- Le helper n'est jamais APPELÉ dans la sanitation/persistance de la branche externe (§4 de
// la demande) : vérifié directement sur le code source, commentaires PHP retirés au préalable
// pour ne pas confondre un appel réel avec sa simple mention dans une explication en commentaire
// (le code source documente délibérément, en commentaire, que ce helper n'est PAS en cause dans
// le bug — cette mention ne doit pas faire échouer le test) ---
function gws_test_strip_php_comments($source) {
  $tokens = token_get_all('<?php ' . $source);
  $stripped = '';
  foreach ($tokens as $token) {
    if (is_array($token) && in_array($token[0], array(T_COMMENT, T_DOC_COMMENT), true)) continue;
    $stripped .= is_array($token) ? $token[1] : $token;
  }
  return $stripped;
}
$set_horse_parent_block = gws_test_strip_php_comments(substr($cheval_pedigree_source, strpos($cheval_pedigree_source, 'function gwseq_set_horse_parent'), strpos($cheval_pedigree_source, 'function gwseq_get_horse_parent') - strpos($cheval_pedigree_source, 'function gwseq_set_horse_parent')));
$sanitize_tree_block = gws_test_strip_php_comments(substr($cheval_pedigree_source, strpos($cheval_pedigree_source, 'function gwseq_sanitize_external_ancestor_tree'), strpos($cheval_pedigree_source, 'function gwseq_set_horse_parent') - strpos($cheval_pedigree_source, 'function gwseq_sanitize_external_ancestor_tree')));
gws_test_assert(strpos($set_horse_parent_block, 'gwseq_format_horse_name_display') === false, 'Séparation source/présentation : gwseq_set_horse_parent() n’appelle jamais le helper de présentation (hors commentaires)');
gws_test_assert(strpos($sanitize_tree_block, 'gwseq_format_horse_name_display') === false, 'Séparation source/présentation : gwseq_sanitize_external_ancestor_tree() n’appelle jamais le helper de présentation (hors commentaires)');
gws_test_assert(strpos($cheval_pedigree_source, 'JSON_UNESCAPED_UNICODE') !== false, 'Correctif Unicode : le drapeau JSON_UNESCAPED_UNICODE est bien présent dans le code source (vérification directe, pas seulement comportementale)');

// =====================================================================================
// gwseq_set_horse_parent() / gwseq_get_horse_parent() : persistance, conservation non
// destructive lors d'un changement de mode (§8-9 du correctif)
// =====================================================================================

gws_test_make_post(10, GWSEQ_CPT_CHEVAL, 'Étalon A');
gws_test_make_post(11, GWSEQ_CPT_CHEVAL, 'Jument B');
gws_test_make_post(20, GWSEQ_CPT_CHEVAL, 'Poulain C');

gwseq_set_horse_parent(20, 'father', array('mode' => 'gws', 'horse_id' => 10));
$father = gwseq_get_horse_parent(20, 'father');
gws_test_assert($father['mode'] === 'gws' && $father['horse_id'] === 10, 'Père GWS valide : relation persistée et relue fidèlement');

gwseq_set_horse_parent(20, 'mother', array('mode' => 'external', 'external' => array('name' => 'Jument Externe', 'race' => 'sf')));
$mother = gwseq_get_horse_parent(20, 'mother');
gws_test_assert($mother['mode'] === 'external' && $mother['external']['name'] === 'Jument Externe' && $mother['external']['race'] === 'SF', 'Mère externe : arbre persisté et relu fidèlement, race du référentiel (code canonique) incluse');

// --- Aucune duplication (§22) : seules les meta de relation existent, jamais de copie du nom/
// race/Global Horse ID du père GWS sur l'enfant ---
$stored = $GLOBALS['__gwseq_test_meta'][20];
$expected_keys = array('_gwseq_pere_mode', '_gwseq_pere_id', '_gwseq_mere_mode', '_gwseq_mere_externe');
gws_test_assert(count(array_diff(array_keys($stored), $expected_keys)) === 0, 'Aucune duplication : seules les meta de relation strictement nécessaires existent sur l’enfant');

// --- Changement GWS -> externe : conservation non destructive (§8-9) ---
gwseq_set_horse_parent(20, 'father', array('mode' => 'external', 'external' => array('name' => 'Nouvel Ascendant Externe')));
gws_test_assert($GLOBALS['__gwseq_test_meta'][20]['_gwseq_pere_mode'] === 'external', 'Changement GWS -> externe : le nouveau mode est bien actif');
gws_test_assert((int) $GLOBALS['__gwseq_test_meta'][20]['_gwseq_pere_id'] === 10, 'Changement GWS -> externe : l’ancien identifiant GWS reste stocké, inactif (conservation non destructive)');

// --- Changement externe -> GWS : symétrique ---
gwseq_set_horse_parent(20, 'father', array('mode' => 'gws', 'horse_id' => 11));
gws_test_assert($GLOBALS['__gwseq_test_meta'][20]['_gwseq_pere_mode'] === 'gws' && (int) $GLOBALS['__gwseq_test_meta'][20]['_gwseq_pere_id'] === 11, 'Changement externe -> GWS : la nouvelle relation GWS est bien active');
gws_test_assert(
  strpos($GLOBALS['__gwseq_test_meta'][20]['_gwseq_pere_externe'], 'Nouvel Ascendant Externe') !== false,
  'Changement externe -> GWS : l’ancienne branche externe reste stockée telle quelle, inactive (conservation non destructive, §9)'
);

// --- Auto-référence rejetée via gwseq_set_horse_parent() : mode retombe à vide ---
gwseq_set_horse_parent(20, 'mother', array('mode' => 'gws', 'horse_id' => 20));
gws_test_assert(gwseq_get_horse_parent(20, 'mother')['mode'] === '', 'gwseq_set_horse_parent() : auto-référence rejetée, la relation retombe à "aucune"');

// --- Mode externe sans nom via gwseq_set_horse_parent() : mode retombe à vide ---
gwseq_set_horse_parent(20, 'mother', array('mode' => 'external', 'external' => array('race' => 'sf')));
gws_test_assert(gwseq_get_horse_parent(20, 'mother')['mode'] === '', 'gwseq_set_horse_parent() : mode externe sans nom -> relation retombe à "aucune"');

// --- Donnée mal formée : jamais d'erreur ---
$result = gwseq_set_horse_parent(20, 'father', 'pas un tableau');
gws_test_assert($result === true, 'gwseq_set_horse_parent() : donnée mal formée -> aucune erreur (repli sûr sur "aucune relation")');

// =====================================================================================
// Compatibilité ascendante avec l'ancien format "breed" texte libre (§2 et §26 de la demande de
// correction) : aucune perte de données pour un pedigree déjà enregistré (type Jamerose)
// =====================================================================================

// --- Ancienne valeur texte correspondant exactement à un libellé canonique : reconnue proprement ---
gws_test_make_post(900, GWSEQ_CPT_CHEVAL, 'A Un Pedigree Ancien Format');
$old_format_recognized = array('name' => 'Kannan', 'breed' => 'Selle Français', 'father' => null, 'mother' => null);
$GLOBALS['__gwseq_test_meta'][900] = array('_gwseq_pere_mode' => 'external', '_gwseq_pere_externe' => wp_json_encode($old_format_recognized));
$relation = gwseq_get_horse_parent(900, 'father');
gws_test_assert($relation['external']['name'] === 'Kannan', 'Compatibilité ascendante : le nom d’un ancien ascendant externe est restitué sans aucune perte');
gws_test_assert($relation['external']['race'] === 'SF' && $relation['external']['race_autre'] === '', 'Compatibilité ascendante : une ancienne valeur "Selle Français" (libellé canonique exact) est reconnue et convertie proprement au code canonique du référentiel ("SF")');

// --- Ancienne valeur texte correspondant à un CODE technique (insensible à la casse) ---
$old_format_code = array('name' => 'Voltaire', 'breed' => 'kwpn', 'father' => null, 'mother' => null);
$GLOBALS['__gwseq_test_meta'][901] = array('_gwseq_pere_mode' => 'external', '_gwseq_pere_externe' => wp_json_encode($old_format_code));
gws_test_make_post(901, GWSEQ_CPT_CHEVAL, 'Autre Ancien Format');
$relation = gwseq_get_horse_parent(901, 'father');
gws_test_assert($relation['external']['race'] === 'KWPN', 'Compatibilité ascendante : une ancienne valeur correspondant au code technique ("kwpn") est également reconnue et normalisée au code canonique ("KWPN")');

// --- Ancienne valeur texte qui ne correspond à rien du référentiel (alias SFA->SF, §2 de la
// demande référentiel : "important exemple" — reconnu, jamais rangé dans "Autre") ET une valeur
// qui ne correspond réellement à rien : jamais perdue, reste récupérable via "Autre" ---
$old_format_alias = array('name' => 'Jument Alias', 'breed' => 'SFA', 'father' => null, 'mother' => null);
$GLOBALS['__gwseq_test_meta'][905] = array('_gwseq_pere_mode' => 'external', '_gwseq_pere_externe' => wp_json_encode($old_format_alias));
gws_test_make_post(905, GWSEQ_CPT_CHEVAL, 'Ancien Format Alias SFA');
$relation = gwseq_get_horse_parent(905, 'father');
gws_test_assert($relation['external']['race'] === 'SF' && $relation['external']['race_autre'] === '', 'Compatibilité ascendante — exemple important de la demande : l’alias historique "SFA" est reconnu et résolu au code canonique "SF", jamais rangé dans "Autre"');

$old_format_unmatched = array('name' => 'Jument Ancienne', 'breed' => 'Zzzabrégénotarace', 'father' => null, 'mother' => null);
$GLOBALS['__gwseq_test_meta'][902] = array('_gwseq_mere_mode' => 'external', '_gwseq_mere_externe' => wp_json_encode($old_format_unmatched));
gws_test_make_post(902, GWSEQ_CPT_CHEVAL, 'Ancien Format Abrégé');
$relation = gwseq_get_horse_parent(902, 'mother');
gws_test_assert($relation['external']['name'] === 'Jument Ancienne', 'Compatibilité ascendante : le nom reste intact même quand la race ne correspond à rien de connu');
gws_test_assert($relation['external']['race'] === 'autre' && $relation['external']['race_autre'] === 'Zzzabrégénotarace', 'Compatibilité ascendante : une valeur ne correspondant à rien du référentiel n’est jamais perdue ni devinée arbitrairement — récupérable via "Autre", texte original conservé intégralement');

// --- Ancienne valeur texte vide (aucune race jamais renseignée à l'époque) ---
$old_format_empty = array('name' => 'Sans Race', 'breed' => '', 'father' => null, 'mother' => null);
$GLOBALS['__gwseq_test_meta'][903] = array('_gwseq_pere_mode' => 'external', '_gwseq_pere_externe' => wp_json_encode($old_format_empty));
gws_test_make_post(903, GWSEQ_CPT_CHEVAL, 'Ancien Format Vide');
$relation = gwseq_get_horse_parent(903, 'father');
gws_test_assert($relation['external']['race'] === '' && $relation['external']['race_autre'] === '', 'Compatibilité ascendante : une ancienne race jamais renseignée reste vide, jamais forcée sur "Autre"');

// --- Ancien format sur PLUSIEURS générations (pedigree complet type Jamerose) : chaque niveau
// est converti indépendamment, aucune perte à aucune profondeur ---
$old_multi_gen = array(
  'name' => 'Jamerose', 'breed' => 'Selle Français',
  'father' => array('name' => 'Kannan', 'breed' => 'KWPN', 'father' => array('name' => 'Voltaire', 'breed' => 'inconnue-non-reconnue'), 'mother' => null),
  'mother' => array('name' => 'Une Jument', 'breed' => '', 'father' => null, 'mother' => null),
);
$GLOBALS['__gwseq_test_meta'][904] = array('_gwseq_pere_mode' => 'external', '_gwseq_pere_externe' => wp_json_encode($old_multi_gen));
gws_test_make_post(904, GWSEQ_CPT_CHEVAL, 'A Un Pedigree Complet Ancien Format');
$relation = gwseq_get_horse_parent(904, 'father');
gws_test_assert(
  $relation['external']['name'] === 'Jamerose' && $relation['external']['race'] === 'SF'
    && $relation['external']['father']['name'] === 'Kannan' && $relation['external']['father']['race'] === 'KWPN'
    && $relation['external']['father']['father']['name'] === 'Voltaire' && $relation['external']['father']['father']['race'] === 'autre' && $relation['external']['father']['father']['race_autre'] === 'inconnue-non-reconnue'
    && $relation['external']['mother']['name'] === 'Une Jument' && $relation['external']['mother']['race'] === '',
  'Compatibilité ascendante : un pedigree ancien format sur plusieurs générations (type Jamerose) est converti sans aucune perte, à chaque niveau, aux codes canoniques du référentiel'
);
// --- Le resolver fonctionne aussi directement sur une ancienne fiche jamais resauvegardée ---
$node = gwseq_resolve_horse_pedigree(904);
gws_test_assert($node['father']['name'] === 'Jamerose' && $node['father']['breed'] === 'Selle Français' && $node['father']['father']['name'] === 'Kannan', 'Compatibilité ascendante : le resolver fonctionne directement sur une fiche jamais resauvegardée depuis la correction');

// --- Aucune réécriture automatique de la base à la simple lecture (§2 : "aucune migration
// destructive") : le format brut stocké reste inchangé tant que l'utilisateur n'a pas
// lui-même réenregistré cette relation ---
gws_test_assert(
  strpos($GLOBALS['__gwseq_test_meta'][900]['_gwseq_pere_externe'], '"breed"') !== false,
  'Aucune migration destructive : le format brut stocké en base (ancien champ "breed") reste inchangé après une simple lecture, la conversion n’a lieu qu’en mémoire à l’affichage'
);

// =====================================================================================
// Chemin programmatique (§15/§31 du correctif) : aucune dépendance à $_POST ni à un faux nonce,
// y compris pour une structure externe imbriquée sur plusieurs générations
// =====================================================================================
$_POST = array(); // volontairement vide : la preuve que la fonction n'en a pas besoin
gws_test_make_post(30, GWSEQ_CPT_CHEVAL, 'Import Test');
$result = gwseq_set_horse_parent(30, 'father', array('mode' => 'external', 'external' => array(
  'name' => 'Ascendant Importé',
  'race' => 'aqps',
  'father' => array('name' => 'Grand-père Importé'),
  'mother' => array('name' => 'Grand-mère Importée'),
)));
gws_test_assert($result === true, 'Chemin programmatique : une structure externe sur plusieurs générations peut être définie avec $_POST vide (aucun formulaire, aucun nonce fabriqué)');
$imported = gwseq_get_horse_parent(30, 'father');
gws_test_assert($imported['external']['father']['name'] === 'Grand-père Importé' && $imported['external']['mother']['name'] === 'Grand-mère Importée', 'Chemin programmatique : la structure imbriquée est bien persistée et relue intacte');

$set_function_block = substr($cheval_pedigree_source, strpos($cheval_pedigree_source, 'function gwseq_set_horse_parent'), strpos($cheval_pedigree_source, 'function gwseq_get_horse_parent') - strpos($cheval_pedigree_source, 'function gwseq_set_horse_parent'));
gws_test_assert(strpos($set_function_block, '$_POST') === false, 'gwseq_set_horse_parent() : le code source ne référence jamais $_POST');
gws_test_assert(strpos($set_function_block, 'wp_verify_nonce') === false, 'gwseq_set_horse_parent() : le code source ne vérifie jamais de nonce (autorisation déléguée à l’appelant)');
gws_test_assert(strpos($set_function_block, 'current_user_can') === false, 'gwseq_set_horse_parent() : le code source ne vérifie jamais de capability (autorisation déléguée à l’appelant)');

// =====================================================================================
// Resolver : structure de données, profondeur, cycles, dégradation propre, mélange GWS/externe
// =====================================================================================

// --- Cheval sans pedigree ---
gws_test_make_post(100, GWSEQ_CPT_CHEVAL, 'Solo');
$node = gwseq_resolve_horse_pedigree(100);
gws_test_assert($node['type'] === 'gws_horse' && $node['id'] === 100 && $node['name'] === 'Solo', 'Resolver : cheval sans pedigree -> nœud racine correctement identifié');
gws_test_assert($node['father'] === null && $node['mother'] === null, 'Resolver : cheval sans pedigree -> père et mère à null (aucune donnée fabriquée)');

// --- Ascendant externe simple (sans ascendants propres) ---
gws_test_make_post(103, GWSEQ_CPT_CHEVAL, 'A Une Mère Externe Simple');
gwseq_set_horse_parent(103, 'mother', array('mode' => 'external', 'external' => array('name' => 'Mère Externe', 'race' => 'co')));
$node = gwseq_resolve_horse_pedigree(103);
gws_test_assert($node['mother']['type'] === 'external' && $node['mother']['name'] === 'Mère Externe' && $node['mother']['breed'] === 'Connemara', 'Resolver : ascendant externe simple -> résolu correctement, libellé de race résolu depuis le référentiel');
gws_test_assert($node['mother']['father'] === null && $node['mother']['mother'] === null, 'Resolver : ascendant externe simple -> aucun ascendant propre fabriqué');
gws_test_assert($node['father'] === null, 'Resolver : seulement mère -> père reste null');

// --- Ascendant externe possédant deux parents externes ---
gws_test_make_post(110, GWSEQ_CPT_CHEVAL, 'A Un Père Externe Avec Origines');
gwseq_set_horse_parent(110, 'father', array('mode' => 'external', 'external' => array(
  'name' => 'Kannan', 'race' => 'kwpn',
  'father' => array('name' => 'Voltaire', 'race' => 'han'),
  'mother' => array('name' => 'Cemeta', 'race' => 'trak'),
)));
$node = gwseq_resolve_horse_pedigree(110);
gws_test_assert($node['father']['name'] === 'Kannan' && $node['father']['breed'] === 'KWPN' && $node['father']['father']['name'] === 'Voltaire' && $node['father']['mother']['name'] === 'Cemeta', 'Resolver : ascendant externe avec deux parents externes -> tous résolus, libellés de race résolus');
gws_test_assert($node['father']['father']['type'] === 'external' && $node['father']['mother']['type'] === 'external', 'Resolver : les ascendants d’un ascendant externe sont eux-mêmes de type "external"');

// --- Branche externe complète sur plusieurs générations (jusqu'à la profondeur maximale, désormais
// 3 générations — correctif référentiel §10) ---
gws_test_make_post(120, GWSEQ_CPT_CHEVAL, 'Pedigree Entièrement Externe En Père');
gwseq_set_horse_parent(120, 'father', array('mode' => 'external', 'external' => array(
  'name' => 'G1',
  'father' => array('name' => 'G2-P', 'father' => array('name' => 'G3-PP', 'father' => array('name' => 'G4-PPP (jamais stocké)'))),
  'mother' => array('name' => 'G2-M'),
)));
$node = gwseq_resolve_horse_pedigree(120);
gws_test_assert(
  $node['father']['name'] === 'G1' && $node['father']['father']['name'] === 'G2-P' && $node['father']['father']['father']['name'] === 'G3-PP',
  'Resolver : branche externe complète -> résolution récursive correcte sur 3 générations'
);
gws_test_assert(
  !array_key_exists('father', $node['father']['father']['father']),
  'Resolver : la 3e génération d’une branche externe est strictement terminale (aucune clé father, la 4e génération soumise n’est de toute façon jamais stockée)'
);
gws_test_assert($node['father']['mother']['name'] === 'G2-M' && $node['father']['mother']['father'] === null, 'Resolver : branche externe partiellement renseignée -> la partie non renseignée reste null');

// --- Pedigree entièrement externe (père ET mère de la racine sont des arbres externes) ---
gws_test_make_post(130, GWSEQ_CPT_CHEVAL, 'Jument À Vendre');
gwseq_set_horse_parent(130, 'father', array('mode' => 'external', 'external' => array('name' => 'Kannan', 'race' => 'kwpn', 'father' => array('name' => 'Voltaire'))));
gwseq_set_horse_parent(130, 'mother', array('mode' => 'external', 'external' => array('name' => 'Jument X', 'father' => array('name' => 'Étalon Y'))));
$node = gwseq_resolve_horse_pedigree(130);
gws_test_assert(
  $node['type'] === 'gws_horse' && $node['father']['name'] === 'Kannan' && $node['father']['father']['name'] === 'Voltaire' && $node['mother']['name'] === 'Jument X' && $node['mother']['father']['name'] === 'Étalon Y',
  'Resolver : pedigree entièrement externe (aucun ascendant n’est une fiche GWS) -> résolu intégralement sans créer la moindre fiche'
);
gws_test_assert(count($GLOBALS['__gwseq_test_posts']) === count(array_unique(array_keys($GLOBALS['__gwseq_test_posts']))), 'Resolver : aucune fiche cheval artificielle créée pour les ascendants externes (aucun nouvel enregistrement dans la base de test)');

// --- Profondeur maximale (génération 3, correctif référentiel §10) pour une branche externe : la
// nouvelle profondeur STANDARD GWS (14 ascendants, alignée sur la fiche IFCE) ---
gws_test_make_post(140, GWSEQ_CPT_CHEVAL, 'Racine Profondeur Externe');
gwseq_set_horse_parent(140, 'father', array('mode' => 'external', 'external' => array(
  'name' => 'Gen1', 'father' => array('name' => 'Gen2', 'father' => array('name' => 'Gen3', 'father' => array('name' => 'Gen4 (jamais stocké)'))),
)));
$node = gwseq_resolve_horse_pedigree(140);
$gen3 = $node['father']['father']['father'];
gws_test_assert($gen3['type'] === 'external' && $gen3['name'] === 'Gen3', 'Resolver : profondeur maximale (3) -> la 3e génération d’une branche externe est bien résolue en entier');
gws_test_assert(!array_key_exists('father', $gen3) && !array_key_exists('mother', $gen3), 'Génération terminale (correctif référentiel §10) : un nœud de génération 3 n’a AUCUNE clé father/mother, ni même null — jamais une 4e génération représentée, même sous forme d’absence de donnée');

// --- Mélange branche GWS + branche externe À L'INTÉRIEUR d'une chaîne GWS (§12 du correctif) ---
gws_test_make_post(150, GWSEQ_CPT_CHEVAL, 'Poulain Mixte');
gws_test_make_post(151, GWSEQ_CPT_CHEVAL, 'Jument X Mixte');
gws_test_make_post(152, GWSEQ_CPT_CHEVAL, 'Jument Y Mixte');
gwseq_set_horse_parent(150, 'mother', array('mode' => 'gws', 'horse_id' => 151));
gwseq_set_horse_parent(151, 'mother', array('mode' => 'gws', 'horse_id' => 152));
gwseq_set_horse_parent(152, 'father', array('mode' => 'external', 'external' => array('name' => 'Étalon Z Externe')));
$node = gwseq_resolve_horse_pedigree(150);
gws_test_assert(
  $node['mother']['type'] === 'gws_horse' && $node['mother']['mother']['type'] === 'gws_horse' && $node['mother']['mother']['father']['type'] === 'external' && $node['mother']['mother']['father']['name'] === 'Étalon Z Externe',
  'Resolver : le resolver mélange naturellement des relations GWS et des branches externes au sein d’une même chaîne'
);

// --- Absence de mélange entre source active et données inactives (§9 du correctif) ---
gws_test_make_post(160, GWSEQ_CPT_CHEVAL, 'Ancien GWS Devenu Externe');
gws_test_make_post(161, GWSEQ_CPT_CHEVAL, 'A Testé Le Changement De Source');
gwseq_set_horse_parent(161, 'father', array('mode' => 'gws', 'horse_id' => 160));
gwseq_set_horse_parent(161, 'father', array('mode' => 'external', 'external' => array('name' => 'Ascendant Externe Actif')));
$node = gwseq_resolve_horse_pedigree(161);
gws_test_assert($node['father']['type'] === 'external' && $node['father']['name'] === 'Ascendant Externe Actif', 'Passage GWS -> externe : le resolver suit la nouvelle source active');
gws_test_assert($node['father']['name'] !== 'Ancien GWS Devenu Externe', 'Passage GWS -> externe : aucune trace de l’ancienne branche GWS inactive dans le résultat (jamais de mélange)');
gws_test_assert((int) $GLOBALS['__gwseq_test_meta'][161]['_gwseq_pere_id'] === 160, 'Passage GWS -> externe : l’ancien identifiant GWS reste bien stocké en base (inactif), preuve que le resolver l’ignore par choix, pas par absence de donnée');

gwseq_set_horse_parent(161, 'father', array('mode' => 'gws', 'horse_id' => 160));
$node = gwseq_resolve_horse_pedigree(161);
gws_test_assert($node['father']['type'] === 'gws_horse' && $node['father']['name'] === 'Ancien GWS Devenu Externe', 'Passage externe -> GWS (retour) : le resolver suit de nouveau la source GWS, sans mélange avec la branche externe restée stockée');

// --- Structure malformée/excessivement profonde — OU une DONNÉE HISTORIQUE réellement enregistrée
// en génération 4+ avant le correctif référentiel (§11 : "compatibilité avec les anciennes données
// de génération 4") — ne peut pas contourner la limite serveur STANDARD (désormais 3 générations),
// même injectée directement en base : défense en profondeur du resolver lui-même, indépendante de
// la sanitation à la sauvegarde. Utilise ici la profondeur PAR DÉFAUT (aucun override), exactement
// le rendu standard d'une fiche — la donnée au-delà de 3 générations reste en base (documentée
// §11), simplement jamais résolue/rendue ---
gws_test_make_post(170, GWSEQ_CPT_CHEVAL, 'Donnée Corrompue En Base');
$corrupted = array('name' => 'Niveau 1');
$cursor = &$corrupted;
for ($i = 2; $i <= 10; $i++) {
  $cursor['father'] = array('name' => 'Niveau ' . $i);
  $cursor = &$cursor['father'];
}
unset($cursor);
$GLOBALS['__gwseq_test_meta'][170] = array('_gwseq_pere_mode' => 'external', '_gwseq_pere_externe' => wp_json_encode($corrupted));
$node = gwseq_resolve_horse_pedigree(170);
$depth_reached = 0;
$cursor = $node['father'];
while (is_array($cursor) && ($cursor['type'] ?? '') === 'external') {
  $depth_reached++;
  $cursor = $cursor['father'] ?? null;
}
gws_test_assert($depth_reached === GWSEQ_PEDIGREE_MAX_DEPTH, 'Resolver (§11, compatibilité génération 4) : une structure sur 10 niveaux (données historiques d’avant le correctif incluses) est strictement bornée à la profondeur STANDARD (désormais 3 générations) au rendu, quelle que soit la profondeur réelle stockée en base');
gws_test_assert($cursor === null, 'Resolver : au-delà de la limite, plus aucune génération "external" supplémentaire n’apparaît (jamais de contournement de la borne serveur)');

// --- Complément de recette : un arbre résolu à la profondeur standard ne contient AUCUN enfant de
// génération 4, ni sous forme de nœud, ni même sous forme de clé father/mother valant null — la
// donnée de génération 4+ reste en base (jamais supprimée), simplement jamais rendue ---
$gen3_from_corrupted = $node['father']['father']['father'];
gws_test_assert(
  $gen3_from_corrupted['type'] === 'external' && !array_key_exists('father', $gen3_from_corrupted) && !array_key_exists('mother', $gen3_from_corrupted),
  'Complément (§11) : même à partir d’une donnée historique/corrompue sur 10 niveaux, le nœud de génération 3 reste strictement terminal au rendu standard (aucune clé father/mother, aucune 4e génération représentée)'
);

// --- Même règle pour une chaîne GWS (pas seulement une branche externe) : un cheval GWS résolu
// à la génération 3 (nouvelle profondeur standard, correctif référentiel §10) est lui aussi
// strictement terminal, même si son PROPRE pedigree continue réellement en base au-delà
// (génération 4, hors périmètre, jamais interrogée ni représentée — §11, compatibilité avec
// d'éventuelles données de génération 4 déjà enregistrées).
// Numérotation ci-dessous relative à la racine résolue (185, génération 0). ---
gws_test_make_post(180, GWSEQ_CPT_CHEVAL, 'GWS Génération 5 (hors périmètre)');
gws_test_make_post(181, GWSEQ_CPT_CHEVAL, 'GWS Génération 4 (hors périmètre)');
gws_test_make_post(182, GWSEQ_CPT_CHEVAL, 'GWS Génération 3');
gws_test_make_post(183, GWSEQ_CPT_CHEVAL, 'GWS Génération 2');
gws_test_make_post(184, GWSEQ_CPT_CHEVAL, 'GWS Génération 1');
gws_test_make_post(185, GWSEQ_CPT_CHEVAL, 'GWS Racine (génération 0)');
gwseq_set_horse_parent(181, 'father', array('mode' => 'gws', 'horse_id' => 180)); // gen4 -> gen5 (réel en base)
gwseq_set_horse_parent(182, 'father', array('mode' => 'gws', 'horse_id' => 181)); // gen3 -> gen4
gwseq_set_horse_parent(183, 'father', array('mode' => 'gws', 'horse_id' => 182)); // gen2 -> gen3
gwseq_set_horse_parent(184, 'father', array('mode' => 'gws', 'horse_id' => 183)); // gen1 -> gen2
gwseq_set_horse_parent(185, 'father', array('mode' => 'gws', 'horse_id' => 184)); // gen0 -> gen1
$node = gwseq_resolve_horse_pedigree(185);
$gws_gen3 = $node['father']['father']['father'];
gws_test_assert($gws_gen3['type'] === 'gws_horse' && $gws_gen3['name'] === 'GWS Génération 3', 'Chaîne GWS : la génération 3 est bien résolue en entier (nom, identité)');
gws_test_assert(
  !array_key_exists('father', $gws_gen3) && !array_key_exists('mother', $gws_gen3),
  'Chaîne GWS : un nœud GWS de génération 3 est également strictement terminal (aucune clé father/mother), alors même que "GWS Génération 3" a réellement un père enregistré en base ("GWS Génération 4") — cette 4e génération n’est jamais interrogée ni représentée (§11)'
);

// --- Le rendu de vérification admin/développement ne produit plus "Père : Non renseigné"/
// "Mère : Non renseigné" sous un nœud terminal (bug constaté en recette : laissait croire à tort
// qu'une génération supplémentaire existerait dans le modèle) ---
$terminal_preview_html = gwseq_render_pedigree_node_preview($gws_gen3);
gws_test_assert(strpos($terminal_preview_html, 'Non renseigné') === false, 'Aperçu resolver : aucun "Non renseigné" sous un nœud de génération 3 (terminal), qui n’a structurellement aucune ligne Père/Mère');
gws_test_assert(strpos($terminal_preview_html, '<ul') === false, 'Aperçu resolver : aucune liste Père/Mère n’est même rendue pour un nœud terminal');

// --- Cycle direct (données incohérentes forcées, en défense en profondeur du contrôle déjà fait
// à la sauvegarde) : le resolver ne boucle jamais indéfiniment ---
gws_test_make_post(400, GWSEQ_CPT_CHEVAL, 'Cycle Direct');
$GLOBALS['__gwseq_test_meta'][400] = array('_gwseq_pere_mode' => 'gws', '_gwseq_pere_id' => 400);
$node = gwseq_resolve_horse_pedigree(400);
gws_test_assert($node['father']['type'] === 'cycle_detected' && $node['father']['id'] === 400, 'Resolver : cycle direct (auto-référence forcée en base) détecté proprement, sans boucle infinie ni erreur fatale');

// --- Cycle indirect (A -> père B -> père A), construit via la vraie API publique ---
gws_test_make_post(410, GWSEQ_CPT_CHEVAL, 'A');
gws_test_make_post(411, GWSEQ_CPT_CHEVAL, 'B');
gwseq_set_horse_parent(410, 'father', array('mode' => 'gws', 'horse_id' => 411));
gwseq_set_horse_parent(411, 'father', array('mode' => 'gws', 'horse_id' => 410));
$node = gwseq_resolve_horse_pedigree(410);
gws_test_assert($node['father']['name'] === 'B', 'Resolver : cycle indirect -> la première génération (B) est résolue normalement');
gws_test_assert($node['father']['father']['type'] === 'cycle_detected' && $node['father']['father']['id'] === 410, 'Resolver : cycle indirect -> détecté à la génération où A réapparaît dans son propre chemin, résolution interrompue proprement');

// --- Parent supprimé / inaccessible : dégradation propre, jamais de fatal error ---
gws_test_make_post(420, GWSEQ_CPT_CHEVAL, 'A Un Parent Supprimé');
gws_test_make_post(421, GWSEQ_CPT_CHEVAL, 'Sera Supprimé');
gwseq_set_horse_parent(420, 'father', array('mode' => 'gws', 'horse_id' => 421));
unset($GLOBALS['__gwseq_test_posts'][421]); // suppression définitive simulée
$node = gwseq_resolve_horse_pedigree(420);
gws_test_assert($node['father']['type'] === 'unavailable' && $node['father']['id'] === 421, 'Resolver : parent définitivement supprimé -> dégradation propre ("unavailable"), jamais d’erreur fatale');
gws_test_assert($node['type'] === 'gws_horse', 'Resolver : la fiche elle-même reste résolue normalement malgré un parent introuvable (pas de casse en cascade)');

// --- Mise à jour d'un parent GWS répercutée automatiquement (§24) ---
gws_test_make_post(430, GWSEQ_CPT_CHEVAL, 'Nom Original');
gws_test_make_post(431, GWSEQ_CPT_CHEVAL, 'A Un Parent');
gwseq_set_horse_parent(431, 'father', array('mode' => 'gws', 'horse_id' => 430));
gws_test_assert(gwseq_resolve_horse_pedigree(431)['father']['name'] === 'Nom Original', 'Avant modification : le nom du père résolu est "Nom Original"');
$GLOBALS['__gwseq_test_posts'][430]['post_title'] = 'Nouveau Nom';
gws_test_assert(gwseq_resolve_horse_pedigree(431)['father']['name'] === 'Nouveau Nom', 'Modification du père (renommage) : répercutée automatiquement à la résolution suivante, sans toucher à la fiche enfant');

// --- Mémoïsation locale (§20) : un même ascendant GWS croisé deux fois (diamant) ---
gws_test_make_post(500, GWSEQ_CPT_CHEVAL, 'Étalon Croisé');
gws_test_make_post(501, GWSEQ_CPT_CHEVAL, 'Grand-père Paternel');
gws_test_make_post(502, GWSEQ_CPT_CHEVAL, 'Grand-mère Maternelle');
gws_test_make_post(503, GWSEQ_CPT_CHEVAL, 'Père Diamant');
gws_test_make_post(504, GWSEQ_CPT_CHEVAL, 'Mère Diamant');
gws_test_make_post(505, GWSEQ_CPT_CHEVAL, 'Racine Diamant');
gwseq_set_horse_parent(501, 'father', array('mode' => 'gws', 'horse_id' => 500));
gwseq_set_horse_parent(502, 'father', array('mode' => 'gws', 'horse_id' => 500));
gwseq_set_horse_parent(503, 'father', array('mode' => 'gws', 'horse_id' => 501));
gwseq_set_horse_parent(504, 'father', array('mode' => 'gws', 'horse_id' => 502));
gwseq_set_horse_parent(505, 'father', array('mode' => 'gws', 'horse_id' => 503));
gwseq_set_horse_parent(505, 'mother', array('mode' => 'gws', 'horse_id' => 504));
$node = gwseq_resolve_horse_pedigree(505);
$via_father_side = $node['father']['father']['father'];
$via_mother_side = $node['mother']['father']['father'];
gws_test_assert($via_father_side['type'] === 'gws_horse' && $via_father_side['id'] === 500 && $via_mother_side['id'] === 500, 'Mémoïsation : le même étalon (diamant) apparaît correctement des deux côtés du pedigree');
gws_test_assert($via_father_side === $via_mother_side, 'Mémoïsation : les deux occurrences produisent un résultat strictement identique (pas de recalcul incohérent)');

// --- Aucune duplication métier nécessaire pour le resolver : il ne persiste jamais rien ---
gws_test_assert(strpos($resolver_source, 'update_post_meta') === false, 'Resolver : ne persiste jamais rien (aucune écriture de meta dans tout le fichier)');

// =====================================================================================
// Production (descendants calculés) — §13/§30, uniquement pour de vraies relations GWS
// =====================================================================================

gws_test_make_post(600, GWSEQ_CPT_CHEVAL, 'Étalon Producteur');
gws_test_make_post(601, GWSEQ_CPT_CHEVAL, 'Jument Productrice');
gws_test_make_post(610, GWSEQ_CPT_CHEVAL, 'Produit Via Père');
gws_test_make_post(611, GWSEQ_CPT_CHEVAL, 'Produit Via Mère');
gwseq_set_horse_parent(610, 'father', array('mode' => 'gws', 'horse_id' => 600));
gwseq_set_horse_parent(611, 'mother', array('mode' => 'gws', 'horse_id' => 601));

gws_test_assert(count(gwseq_get_horse_offspring(600)) === 1 && gwseq_get_horse_offspring(600)[0]->ID === 610, 'Production : produit retrouvé via le père');
gws_test_assert(count(gwseq_get_horse_offspring(601)) === 1 && gwseq_get_horse_offspring(601)[0]->ID === 611, 'Production : produit retrouvé via la mère');

gws_test_make_post(613, GWSEQ_CPT_CHEVAL, 'Second Produit Via Père');
gwseq_set_horse_parent(613, 'father', array('mode' => 'gws', 'horse_id' => 600));
gws_test_assert(count(gwseq_get_horse_offspring(600)) === 2, 'Production : plusieurs produits correctement retrouvés');

gws_test_make_post(620, GWSEQ_CPT_CHEVAL, 'Cheval Sans Produit');
gws_test_assert(gwseq_get_horse_offspring(620) === array(), 'Production : cheval sans produit -> tableau vide, jamais d’erreur');

// --- Un ascendant externe (même nom identique dans plusieurs pedigrees) n'est jamais rapproché
// ni compté comme une "production" quelconque : seules les vraies fiches gwseq_cheval comptent ---
gws_test_make_post(614, GWSEQ_CPT_CHEVAL, 'A Un Père Externe Nommé Kannan');
gwseq_set_horse_parent(614, 'father', array('mode' => 'external', 'external' => array('name' => 'Kannan')));
gws_test_make_post(615, GWSEQ_CPT_CHEVAL, 'A Aussi Un Père Externe Nommé Kannan');
gwseq_set_horse_parent(615, 'father', array('mode' => 'external', 'external' => array('name' => 'Kannan')));
$grep_dedup = preg_match('/dedup|deduplicat/i', $cheval_pedigree_source . $resolver_source);
gws_test_assert(!$grep_dedup, 'Aucune tentative de déduplication automatique des ascendants externes (vérifié directement dans le code source, §7)');

// --- Absence de liste descendante stockée dans les metas du parent (§22/§30) ---
gws_test_assert(!array_key_exists('_gwseq_produits', $GLOBALS['__gwseq_test_meta'][600] ?? array()), 'Production : aucune liste de descendants stockée sur le parent (calculée à la volée uniquement)');

// --- Changement de mode et cohérence de la production : un ancien identifiant laissé par un
// mode révolu ne doit jamais produire de faux positif ---
gws_test_make_post(630, GWSEQ_CPT_CHEVAL, 'Ex-Produit');
gwseq_set_horse_parent(630, 'father', array('mode' => 'gws', 'horse_id' => 600));
gws_test_assert(in_array(630, array_map(function ($p) { return $p->ID; }, gwseq_get_horse_offspring(600)), true), 'Avant changement de mode : bien listé comme produit de 600');
gwseq_set_horse_parent(630, 'father', array('mode' => 'external', 'external' => array('name' => 'En réalité un ascendant externe')));
gws_test_assert(!in_array(630, array_map(function ($p) { return $p->ID; }, gwseq_get_horse_offspring(600)), true), 'Après changement de mode vers externe : plus jamais listé comme produit de 600 (aucun faux positif, malgré l’ID GWS resté stocké inactif)');

// =====================================================================================
// Sécurité de la sauvegarde (§32) : nonce invalide / permissions / autosave / révision /
// sanitation serveur — chemin réel via $_POST et gwseq_save_cheval_pedigree_meta()
// =====================================================================================
function gws_test_reset_security() {
  $GLOBALS['__gwseq_test_security'] = array('nonce_valid' => true, 'can_edit' => true, 'is_revision' => false);
}
gws_test_make_post(700, GWSEQ_CPT_CHEVAL, 'Sécurité Pedigree');
gws_test_make_post(701, GWSEQ_CPT_CHEVAL, 'Cible Sécurité');
function gws_test_pedigree_post_payload() {
  return array(
    GWSEQ_CHEVAL_NONCE_FIELD => 'nonce',
    '_gwseq_pere_mode' => 'gws',
    '_gwseq_pere_id' => '701',
  );
}

gws_test_reset_security();
$GLOBALS['__gwseq_test_security']['nonce_valid'] = false;
$_POST = gws_test_pedigree_post_payload();
$GLOBALS['__gwseq_test_meta'][700] = array();
gwseq_save_cheval_pedigree_meta(700);
gws_test_assert($GLOBALS['__gwseq_test_meta'][700] === array(), 'Nonce invalide : aucune meta de pedigree écrite');

gws_test_reset_security();
$GLOBALS['__gwseq_test_security']['can_edit'] = false;
$_POST = gws_test_pedigree_post_payload();
$GLOBALS['__gwseq_test_meta'][700] = array();
gwseq_save_cheval_pedigree_meta(700);
gws_test_assert($GLOBALS['__gwseq_test_meta'][700] === array(), 'Permissions insuffisantes : aucune meta de pedigree écrite');

gws_test_reset_security();
$GLOBALS['__gwseq_test_security']['is_revision'] = true;
$_POST = gws_test_pedigree_post_payload();
$GLOBALS['__gwseq_test_meta'][700] = array();
gwseq_save_cheval_pedigree_meta(700);
gws_test_assert($GLOBALS['__gwseq_test_meta'][700] === array(), 'Révision : aucune meta de pedigree écrite');

gws_test_reset_security();
$_POST = gws_test_pedigree_post_payload();
$GLOBALS['__gwseq_test_meta'][700] = array();
gwseq_save_cheval_pedigree_meta(700);
gws_test_assert($GLOBALS['__gwseq_test_meta'][700]['_gwseq_pere_mode'] === 'gws' && (int) $GLOBALS['__gwseq_test_meta'][700]['_gwseq_pere_id'] === 701, 'Cas valide : nonce/capability/autosave/révision tous corrects -> la relation est bien enregistrée');

// --- Sanitation serveur : un ID de cheval inexistant envoyé malgré un <select> valide côté
// serveur n'est jamais fait confiance (ne jamais se fier au JavaScript) ---
gws_test_reset_security();
$_POST = array(GWSEQ_CHEVAL_NONCE_FIELD => 'nonce', '_gwseq_pere_mode' => 'gws', '_gwseq_pere_id' => '999999');
$GLOBALS['__gwseq_test_meta'][700] = array();
gwseq_save_cheval_pedigree_meta(700);
gws_test_assert($GLOBALS['__gwseq_test_meta'][700]['_gwseq_pere_mode'] === '', 'Sanitation serveur : un ID de cheval inexistant soumis malgré tout est rejeté côté serveur, jamais fait confiance au JavaScript');

// --- Structure externe excessivement profonde soumise via un vrai $_POST : ne contourne jamais
// la limite serveur (§16), même via le chemin de sauvegarde réel formulaire -> $_POST ---
gws_test_reset_security();
$deep_post_external = array('name' => 'Niveau 1');
$cursor = &$deep_post_external;
for ($i = 2; $i <= 8; $i++) {
  $cursor['father'] = array('name' => 'Niveau ' . $i);
  $cursor = &$cursor['father'];
}
unset($cursor);
$_POST = array(GWSEQ_CHEVAL_NONCE_FIELD => 'nonce', '_gwseq_mere_mode' => 'external', '_gwseq_mere_externe' => $deep_post_external);
$GLOBALS['__gwseq_test_meta'][700] = array();
gwseq_save_cheval_pedigree_meta(700);
$stored_external = json_decode($GLOBALS['__gwseq_test_meta'][700]['_gwseq_mere_externe'], true);
$depth_reached = 0;
$cursor = $stored_external;
while (is_array($cursor) && ($cursor['name'] ?? '') !== '') {
  $depth_reached++;
  $cursor = $cursor['father'] ?? null;
}
gws_test_assert($depth_reached === GWSEQ_PEDIGREE_MAX_DEPTH, 'Sanitation serveur (chemin $_POST réel) : une structure externe soumise sur 8 niveaux est strictement bornée à la profondeur standard (désormais 3 générations) avant même d’être enregistrée');

// --- Autosave : testé en dernier (DOING_AUTOSAVE ne peut être défini qu'une fois par processus) ---
gws_test_reset_security();
define('DOING_AUTOSAVE', true);
$_POST = gws_test_pedigree_post_payload();
$GLOBALS['__gwseq_test_meta'][700] = array();
gwseq_save_cheval_pedigree_meta(700);
gws_test_assert($GLOBALS['__gwseq_test_meta'][700] === array(), 'Autosave : aucune meta de pedigree écrite');

// =====================================================================================
// Meta boxes : Pedigree toujours présente, Production seulement si des produits existent,
// Aperçu du resolver réservé au local/développement (§34)
// =====================================================================================
$GLOBALS['__gwseq_test_environment'] = 'production';
$GLOBALS['__gwseq_test_meta_boxes'] = array();
gwseq_add_cheval_pedigree_meta_boxes(gws_test_make_post_object(620));
gws_test_assert(in_array('gwseq-cheval-pedigree', $GLOBALS['__gwseq_test_meta_boxes'], true), 'Meta box Pedigree : toujours enregistrée');
gws_test_assert(!in_array('gwseq-cheval-production', $GLOBALS['__gwseq_test_meta_boxes'], true), 'Meta box Production : jamais enregistrée pour un cheval sans aucun produit');
gws_test_assert(!in_array('gwseq-cheval-pedigree-preview', $GLOBALS['__gwseq_test_meta_boxes'], true), 'Meta box Aperçu du resolver : jamais enregistrée en production');

$GLOBALS['__gwseq_test_meta_boxes'] = array();
gwseq_add_cheval_pedigree_meta_boxes(gws_test_make_post_object(600));
gws_test_assert(in_array('gwseq-cheval-production', $GLOBALS['__gwseq_test_meta_boxes'], true), 'Meta box Production : enregistrée dès qu’au moins un produit existe');

$GLOBALS['__gwseq_test_environment'] = 'local';
$GLOBALS['__gwseq_test_meta_boxes'] = array();
gwseq_add_cheval_pedigree_meta_boxes(gws_test_make_post_object(620));
gws_test_assert(in_array('gwseq-cheval-pedigree-preview', $GLOBALS['__gwseq_test_meta_boxes'], true), 'Meta box Aperçu du resolver : enregistrée en environnement local');
$GLOBALS['__gwseq_test_environment'] = 'production';

// =====================================================================================
// Rendu réel des champs (progressive disclosure, escaping admin) et de l'aperçu resolver
// =====================================================================================
gws_test_make_post(800, GWSEQ_CPT_CHEVAL, 'Fiche De Test');
ob_start();
gwseq_render_cheval_pedigree_box(gws_test_make_post_object(800));
$pedigree_box_html = ob_get_clean();
foreach (array('_gwseq_pere_mode', '_gwseq_pere_id', '_gwseq_pere_externe[name]', '_gwseq_pere_externe[race]', '_gwseq_pere_externe[race_autre]', '_gwseq_mere_mode', '_gwseq_mere_id', '_gwseq_mere_externe[name]', '_gwseq_mere_externe[race]', '_gwseq_mere_externe[race_autre]') as $field_name) {
  gws_test_assert(strpos($pedigree_box_html, 'name="' . $field_name . '"') !== false, "Meta box Pedigree : le champ $field_name est réellement rendu");
}
gws_test_assert(strpos($pedigree_box_html, 'name="_gwseq_pere_externe[father][name]"') !== false, 'Meta box Pedigree : les champs de la génération suivante (père du père externe) sont bien rendus, jusqu’à la profondeur autorisée');
gws_test_assert(strpos($pedigree_box_html, '<details') !== false, 'Meta box Pedigree : la divulgation progressive (§5) utilise l’élément natif <details>, sans JavaScript nécessaire pour se déplier');
// --- Correctif runtime 0.14.4 : le champ Race de CHAQUE ascendant externe (à toutes les
// générations rendues ci-dessus) n'est plus jamais enveloppé dans un <p> — voir
// gws_test_assert_no_flow_content_inside_p() ci-dessus pour la règle HTML5 exacte ---
gws_test_assert_no_flow_content_inside_p($pedigree_box_html, 'Meta box Pedigree : aucun <p> encore ouvert ne contient d’élément de contenu "flow" (le champ Race de chaque ascendant externe n’est plus enveloppé dans un <p>, cause exacte du bug runtime "resultsList=false")');

// --- Corrections lexicales validées (passe intégrité du pedigree) ---
gws_test_assert(strpos($pedigree_box_html, 'Cheval déjà enregistré') !== false, 'Correction lexicale : le libellé "Cheval déjà enregistré" est bien utilisé pour le mode GWS');
gws_test_assert(strpos($pedigree_box_html, 'Cheval déjà présent dans GWS') === false, 'Correction lexicale : l’ancien libellé "Cheval déjà présent dans GWS" n’est plus utilisé');
gws_test_assert(strpos($pedigree_box_html, 'Nouvel ascendant') !== false, 'Correction lexicale : le libellé "Nouvel ascendant" est bien utilisé pour le mode externe');
gws_test_assert(strpos($pedigree_box_html, 'Ascendant hors GWS') === false, 'Correction lexicale : l’ancien libellé "Ascendant hors GWS" n’est plus utilisé');

// --- Câblage nécessaire à l'intégrité du pedigree côté UX admin (§1 de la demande) : chaque
// sélecteur de cheval GWS porte la classe et l'attribut de rôle utilisés par l'écoute déléguée du
// script pour désactiver, dans l'autre sélecteur, le cheval déjà choisi ---
gws_test_assert(substr_count($pedigree_box_html, 'class="gwseq-parent-gws-select"') === 2, 'Intégrité UX : les deux sélecteurs de cheval GWS (père et mère) portent la classe utilisée par l’écoute déléguée du script');
gws_test_assert(strpos($pedigree_box_html, 'data-gwseq-parent-role="father"') !== false && strpos($pedigree_box_html, 'data-gwseq-parent-role="mother"') !== false, 'Intégrité UX : chaque sélecteur porte son propre rôle (père/mère) en attribut data-*');

$cheval_admin_js_source_early = file_get_contents($module_dir . 'assets/cheval-admin.js');
$disabled_js_start = strpos($cheval_admin_js_source_early, 'gwseq-parent-gws-select');
$disabled_js_end = strpos($cheval_admin_js_source_early, 'Race/Stud-book/Appellation (cheval GWS et ascendant externe)');
$disabled_js_block = substr($cheval_admin_js_source_early, $disabled_js_start, $disabled_js_end - $disabled_js_start);
gws_test_assert($disabled_js_start !== false && $disabled_js_end !== false && $disabled_js_end > $disabled_js_start, 'Intégrité UX : le bloc de synchronisation des sélecteurs GWS est bien un bloc distinct et localisable dans le script');
gws_test_assert(strpos($disabled_js_block, 'option.disabled') !== false, 'Intégrité UX : le script désactive bien une option plutôt que de la supprimer ou de changer la sélection en cours');
gws_test_assert(preg_match('/\.value\s*=(?!=)/', $disabled_js_block) === 0, 'Intégrité UX : le script ne modifie jamais automatiquement la valeur sélectionnée d’un sélecteur (uniquement désactivation d’options — les comparaisons "===" sur .value ne sont pas des affectations)');

// --- Rendu réel : le cheval déjà actif comme GWS pour l'autre rôle est bien désactivé dans CE
// sélecteur dès le rendu serveur (défense supplémentaire, avant même l'exécution du script) ---
gws_test_make_post(801, GWSEQ_CPT_CHEVAL, 'Cheval Test Rendu Désactivation');
gws_test_make_post(802, GWSEQ_CPT_CHEVAL, 'Étalon Déjà Père Du Rendu');
gwseq_set_horse_parent(801, 'father', array('mode' => 'gws', 'horse_id' => 802));
ob_start();
gwseq_render_cheval_pedigree_box(gws_test_make_post_object(801));
$pedigree_box_html_disabled = ob_get_clean();
gws_test_assert(
  preg_match('/<option value="802"[^>]*\bdisabled\b[^>]*>/', $pedigree_box_html_disabled) === 1,
  'Intégrité UX : dans le sélecteur Mère, l’option correspondant au cheval déjà actif comme Père est bien rendue désactivée dès le serveur'
);

// --- Câblage nécessaire à la mise à jour dynamique du contexte (§9-10 de la demande de
// correction) : les données traduites sont fournies au script via des attributs data-*, jamais
// codées en dur côté JavaScript ; les classes utilisées par l'écoute déléguée sont bien présentes ---
gws_test_assert(strpos($pedigree_box_html, 'class="gwseq-pedigree-i18n"') !== false, 'Contexte dynamique : le conteneur porteur des libellés traduits pour le JavaScript est bien rendu');
gws_test_assert(strpos($pedigree_box_html, 'data-father-prefix="Père de "') !== false, 'Contexte dynamique : le préfixe "Père de " traduit est fourni en attribut data-*');
gws_test_assert(strpos($pedigree_box_html, 'data-mother-prefix="Mère de "') !== false, 'Contexte dynamique : le préfixe "Mère de " traduit est fourni en attribut data-*');
gws_test_assert(strpos($pedigree_box_html, 'data-summary-prefix="+ Renseigner les origines de "') !== false, 'Contexte dynamique : le préfixe du résumé de divulgation progressive est fourni en attribut data-*');
gws_test_assert(strpos($pedigree_box_html, 'data-fallback-name="cet ascendant"') !== false, 'Contexte dynamique : le repli traduit ("cet ascendant") est fourni en attribut data-*');
gws_test_assert(substr_count($pedigree_box_html, 'class="gwseq-ancestor-node"') >= 2, 'Contexte dynamique : chaque nœud d’ascendant externe porte la classe utilisée par l’écoute déléguée du script (au moins le premier niveau côté Père et côté Mère)');
gws_test_assert(strpos($pedigree_box_html, 'gwseq-external-name-input') !== false, 'Contexte dynamique : le champ Nom porte la classe utilisée par l’écoute déléguée du script');

// --- Le JavaScript ne doit jamais être décrit comme modifiant la donnée : vérification déclarative
// directe sur le fichier (aucune assignation à .value dans le bloc de mise à jour du contexte,
// borné à l'écoute 'input' elle-même — le bloc de suppression explicite plus bas, qui remet lui
// bien des valeurs à vide de façon assumée, est vérifié séparément ci-dessous) ---
$cheval_admin_js_source = file_get_contents($module_dir . 'assets/cheval-admin.js');
$context_update_js_start = strpos($cheval_admin_js_source, 'gwseqPedigreeDisplayName');
$delete_button_js_start = strpos($cheval_admin_js_source, "addEventListener('click'");
$context_update_js_block = substr($cheval_admin_js_source, $context_update_js_start, $delete_button_js_start - $context_update_js_start);
gws_test_assert($delete_button_js_start !== false && $delete_button_js_start > $context_update_js_start, 'Contexte dynamique : le bloc de suppression explicite (écoute "click") est bien un bloc distinct, situé après celui de mise à jour du contexte (écoute "input")');
gws_test_assert(strpos($context_update_js_block, '.value =') === false, 'Contexte dynamique : le script ne réaffecte jamais la propriété .value d’un champ (aucune normalisation de la saisie)');
gws_test_assert(strpos($context_update_js_block, 'e.target.value') !== false, 'Contexte dynamique : le script LIT bien la valeur courante du champ Nom (sans jamais l’écrire)');

// =====================================================================================
// Correctif complémentaire — suppression d'un ascendant externe vide (recette runtime)
//
// CAUSE : un nœud sans nom n'a jamais été stockable (gwseq_sanitize_external_ancestor_tree()
// renvoie déjà null dès qu'un nom est vide, y compris récursivement pour tout sous-arbre — cette
// garantie existait déjà, elle n'a pas changé ici). Le vrai défaut était ailleurs : quand
// l'utilisateur vidait la totalité de l'arbre externe tout en restant sur le mode "Ascendant hors
// GWS", gwseq_set_horse_parent() réinitialisait bien "..._mode" mais laissait l'ANCIENNE
// "..._externe" intacte (conservation prévue pour un CHANGEMENT DE MODE, pas pour un contenu vidé
// en restant sur le même mode) — l'ancien ascendant réapparaissait donc à la prochaine ouverture
// de la fiche, ou dès qu'on rebasculait sur "externe". CORRECTIF : "..._externe" est désormais
// explicitement supprimée avec delete_post_meta() dans ce cas précis.
// =====================================================================================

gws_test_make_post(900, GWSEQ_CPT_CHEVAL, 'Cheval Nettoyage Ascendant');

// --- Nœud externe vide (aucun nom) : jamais stocké, comme avant ce correctif (§2 de la demande :
// nœud partiellement renseigné conservé, nœud totalement vide jamais stocké) ---
gwseq_set_horse_parent(900, 'father', array('mode' => 'external', 'external' => array('name' => '', 'race' => '', 'race_autre' => '')));
gws_test_assert(get_post_meta(900, '_gwseq_pere_mode', true) === '', 'Nettoyage : un arbre externe totalement vide à la sauvegarde désactive la relation (mode vide)');
gws_test_assert(get_post_meta(900, '_gwseq_pere_externe', true) === '', 'Nettoyage : aucune structure JSON vide n’est stockée pour un ascendant externe jamais nommé');

// --- Scénario exact du bug rapporté : un ascendant est créé (nom saisi, enregistré), puis
// entièrement vidé par l’utilisateur tout en restant sur le mode "externe" ---
gwseq_set_horse_parent(900, 'mother', array('mode' => 'external', 'external' => array('name' => 'Kannan', 'race' => 'kwpn')));
gws_test_assert(get_post_meta(900, '_gwseq_mere_mode', true) === 'external', 'Nettoyage (avant vidage) : l’ascendant Kannan est bien enregistré, mode externe actif');
gws_test_assert(json_decode(get_post_meta(900, '_gwseq_mere_externe', true), true)['name'] === 'Kannan', 'Nettoyage (avant vidage) : le nom "Kannan" est bien présent dans la structure JSON stockée');

gwseq_set_horse_parent(900, 'mother', array('mode' => 'external', 'external' => array('name' => '', 'race' => '')));
gws_test_assert(get_post_meta(900, '_gwseq_mere_mode', true) === '', 'Nettoyage : après avoir vidé le nom, la relation est bien désactivée (mode redevenu vide)');
gws_test_assert(get_post_meta(900, '_gwseq_mere_externe', true) === '', 'Nettoyage (correctif) : la structure JSON "Kannan" précédemment enregistrée est bien SUPPRIMÉE — elle ne réapparaît pas à la prochaine lecture (c’est exactement le bug rapporté en recette : "le nœud continue d’exister vide")');
$relation_after_clear = gwseq_get_horse_parent(900, 'mother');
gws_test_assert($relation_after_clear['mode'] === '' && $relation_after_clear['external'] === null, 'Nettoyage : gwseq_get_horse_parent() ne renvoie plus aucune trace de l’ancien ascendant "Kannan" après vidage');

// --- Nœud partiellement renseigné (nom seul) : conservé, jamais confondu avec un nœud vide ---
gwseq_set_horse_parent(900, 'father', array('mode' => 'external', 'external' => array('name' => 'Voltaire')));
$relation_partial = gwseq_get_horse_parent(900, 'father');
gws_test_assert($relation_partial['mode'] === 'external' && $relation_partial['external']['name'] === 'Voltaire', 'Nettoyage : un nœud partiellement renseigné (nom seul, ni race ni descendant) est bien conservé, jamais traité comme vide');

// --- Nœud avec un descendant renseigné à un niveau plus profond : conservé sur toute sa branche ---
gwseq_set_horse_parent(900, 'father', array('mode' => 'external', 'external' => array(
  'name' => 'Kannan', 'race' => 'kwpn',
  'father' => array('name' => 'Voltaire', 'race' => 'han'),
)));
$relation_with_child = gwseq_get_horse_parent(900, 'father');
gws_test_assert($relation_with_child['external']['name'] === 'Kannan' && $relation_with_child['external']['father']['name'] === 'Voltaire', 'Nettoyage : un nœud avec un descendant renseigné (père de Kannan = Voltaire) est bien conservé sur toute sa branche');

// --- Vider ce même père (Kannan + Voltaire) : toute la branche disparaît proprement, la mère
// (une autre relation, gérée indépendamment) reste totalement intacte ---
gwseq_set_horse_parent(900, 'mother', array('mode' => 'external', 'external' => array('name' => 'Une Jument Intacte', 'race' => 'trak')));
gwseq_set_horse_parent(900, 'father', array('mode' => 'external', 'external' => array('name' => '', 'father' => array('name' => ''))));
$relation_after_full_clear = gwseq_get_horse_parent(900, 'father');
$relation_mother_untouched = gwseq_get_horse_parent(900, 'mother');
gws_test_assert($relation_after_full_clear['mode'] === '' && $relation_after_full_clear['external'] === null, 'Nettoyage : vider un ascendant avec toute sa sous-branche (Kannan + Voltaire) supprime bien toute la structure, y compris le descendant');
gws_test_assert($relation_mother_untouched['mode'] === 'external' && $relation_mother_untouched['external']['name'] === 'Une Jument Intacte', 'Nettoyage : l’autre branche (la mère) reste totalement intacte — le nettoyage n’agit que sur la relation ciblée');

// --- Une relation GWS n'est jamais concernée par ce nettoyage : le choix "Non renseigné" désactive
// la relation sans jamais toucher à la fiche Cheval référencée (§4 de la demande) ---
gws_test_make_post(901, GWSEQ_CPT_CHEVAL, 'Étalon GWS Référencé');
gwseq_set_horse_parent(900, 'father', array('mode' => 'gws', 'horse_id' => 901));
gwseq_set_horse_parent(900, 'father', array('mode' => ''));
gws_test_assert(get_post_type(901) === GWSEQ_CPT_CHEVAL && get_post(901) !== null, 'Nettoyage : désactiver une relation GWS ("Non renseigné") ne supprime jamais la fiche Cheval référencée');
gws_test_assert((int) get_post_meta(900, '_gwseq_pere_id', true) === 901, 'Nettoyage : conservation non destructive inchangée pour une relation GWS — l’ancien ID reste récupérable, comme avant ce correctif');

// --- Le resolver ne produit jamais de nœud "external" vide, y compris pour une donnée héritée
// d'avant ce correctif (garde défensive déjà en place, ici testée explicitement — §5 de la
// demande : "une branche vide = absence de branche") ---
gws_test_make_post(902, GWSEQ_CPT_CHEVAL, 'Cheval Donnée Historique Vide');
$GLOBALS['__gwseq_test_meta'][902] = array(
  '_gwseq_pere_mode' => 'external',
  '_gwseq_pere_externe' => wp_json_encode(array('name' => '', 'race' => 'kwpn', 'race_autre' => '', 'father' => null, 'mother' => null)),
);
$resolved_902 = gwseq_resolve_horse_pedigree(902);
gws_test_assert($resolved_902['father'] === null, 'Resolver : une donnée héritée d’un nœud externe sans nom (même avec une race renseignée) n’est jamais résolue en un nœud "external" fantôme — reste null');

// --- Bouton "Supprimer cet ascendant" : présence du contrôle et du texte de confirmation
// contextuel dans le rendu admin, câblage JS correspondant ---
gws_test_make_post(903, GWSEQ_CPT_CHEVAL, 'Cheval Bouton Suppression');
gwseq_set_horse_parent(903, 'father', array('mode' => 'external', 'external' => array('name' => 'Kannan')));
ob_start();
gwseq_render_cheval_pedigree_box(gws_test_make_post_object(903));
$delete_button_html = ob_get_clean();
gws_test_assert(strpos($delete_button_html, 'gwseq-delete-ancestor') !== false, 'Bouton de suppression : le contrôle "Supprimer cet ascendant" est bien rendu pour un ascendant externe');
gws_test_assert(strpos($delete_button_html, 'data-delete-confirm="Supprimer cet ascendant et ses origines ?"') !== false, 'Bouton de suppression : le texte de confirmation traduit est fourni en attribut data-* (jamais codé en dur côté JavaScript)');

$delete_js_block = substr($cheval_admin_js_source, $delete_button_js_start);
gws_test_assert(strpos($delete_js_block, 'gwseq-delete-ancestor') !== false, 'Bouton de suppression : le script écoute bien les clics sur le bouton "Supprimer cet ascendant"');
gws_test_assert(strpos($delete_js_block, "closest('.gwseq-ancestor-node')") !== false, 'Bouton de suppression : le script cible le nœud le plus proche du bouton cliqué, jamais un autre nœud ou le formulaire entier');
gws_test_assert(strpos($delete_js_block, 'confirm(') !== false, 'Bouton de suppression : une confirmation est bien demandée avant de vider un nœud possédant des origines enfants');
gws_test_assert(strpos($delete_js_block, 'window.confirm(confirmText)') !== false || strpos($delete_js_block, 'confirm(confirmText)') !== false, 'Bouton de suppression : le texte de la confirmation provient bien de la donnée traduite fournie par PHP (attribut data-delete-confirm), jamais codé en dur');

// =====================================================================================
// Contexte de saisie (§3-11 de la demande de correction) : jamais un Père/Mère nu, toujours le
// nom du cheval concerné en présentation GWS ; compteur de génération ; arrêt visuel strict à la
// génération 4 — reproduit l'exemple exact de la demande (UNTOUCHABLE 27 / HORS LA LOI II)
// =====================================================================================
gws_test_make_post(850, GWSEQ_CPT_CHEVAL, 'Untouchable 27');
gwseq_set_horse_parent(850, 'father', array('mode' => 'external', 'external' => array(
  'name' => 'Hors La Loi II',
  'father' => array('name' => 'Grand-père De Hors La Loi'),
)));
ob_start();
gwseq_render_cheval_pedigree_box(gws_test_make_post_object(850));
$context_html = ob_get_clean();

gws_test_assert(strpos($context_html, 'Origines de UNTOUCHABLE 27') !== false, 'Contexte : l’en-tête « Origines de X » utilise le nom du cheval en présentation GWS (majuscules, sans accents)');
gws_test_assert(strpos($context_html, 'Père de UNTOUCHABLE 27') !== false, 'Contexte : le bloc Père affiche « Père de UNTOUCHABLE 27 », jamais un « Père » nu (exemple exact de la demande)');
gws_test_assert(strpos($context_html, 'Mère de UNTOUCHABLE 27') !== false, 'Contexte : le bloc Mère affiche « Mère de UNTOUCHABLE 27 », jamais un « Mère » nu');
gws_test_assert(strpos($context_html, 'Renseigner les origines de HORS LA LOI II') !== false, 'Contexte : le bouton de divulgation progressive est contextualisé avec le nom déjà saisi de l’ascendant, jamais générique');
gws_test_assert(strpos($context_html, 'Père de HORS LA LOI II') !== false, 'Contexte : en développant les origines de Hors La Loi II, le niveau suivant affiche « Père de HORS LA LOI II »');
gws_test_assert(strpos($context_html, 'Mère de HORS LA LOI II') !== false, 'Contexte : et « Mère de HORS LA LOI II » pour le niveau suivant');
gws_test_assert(strpos($context_html, 'grand-père') === false && strpos($context_html, 'arrière-grand') === false, 'Contexte : aucune nomenclature généalogique complexe utilisée (grand-père/arrière-grand-père...), conformément au choix retenu (§4)');

// --- Fallback tant que le nom n'est pas encore renseigné (§7) : jamais "Père de"/"Origines de"
// suivi de rien ---
gws_test_make_post(851, GWSEQ_CPT_CHEVAL, 'Cheval Sans Nom De Pere Externe');
$GLOBALS['__gwseq_test_meta'][851] = array('_gwseq_pere_mode' => 'external', '_gwseq_pere_externe' => wp_json_encode(array('name' => '', 'race' => '', 'race_autre' => '', 'father' => null, 'mother' => null)));
ob_start();
gwseq_render_cheval_pedigree_box(gws_test_make_post_object(851));
$fallback_html = ob_get_clean();
gws_test_assert(strpos($fallback_html, 'de cet ascendant') !== false, 'Contexte : repli explicite (« cet ascendant ») tant que le nom d’un ascendant n’est pas encore renseigné');
gws_test_assert(!preg_match('/Origines de\s*<\/strong>/', $fallback_html), 'Contexte : jamais « Origines de » affiché avec un nom vide accolé juste derrière');

// --- Compteur de génération (§9, correctif référentiel §10 : profondeur standard désormais 3) :
// présence des trois indications, y compris dès le premier niveau (l'ascendant externe immédiat
// EST déjà la génération 1) ---
gws_test_make_post(860, GWSEQ_CPT_CHEVAL, 'Racine Generations');
gwseq_set_horse_parent(860, 'father', array('mode' => 'external', 'external' => array(
  'name' => 'G1', 'father' => array('name' => 'G2', 'father' => array('name' => 'G3')),
)));
ob_start();
gwseq_render_cheval_pedigree_box(gws_test_make_post_object(860));
$generations_html = ob_get_clean();
gws_test_assert(strpos($generations_html, 'Génération 1 sur 3') !== false, 'Compteur de génération : « Génération 1 sur 3 » affiché dès le premier niveau d’ascendant externe');
gws_test_assert(strpos($generations_html, 'Génération 2 sur 3') !== false, 'Compteur de génération : « Génération 2 sur 3 » affiché au deuxième niveau');
gws_test_assert(strpos($generations_html, 'Génération 3 sur 3 — dernière génération') !== false, 'Compteur de génération : la génération 3 est explicitement identifiée comme la dernière (correctif référentiel §10-11)');

// --- Arrêt visuel strict à la génération 3 (correctif référentiel §10-11) : appel isolé au rendu
// récursif avec $depth_remaining = 0 (exactement la situation d'un nœud de génération 3) — aucun
// contrôle de divulgation progressive ne doit y apparaître, quelle que soit la donnée présente ou
// non ---
ob_start();
gwseq_render_external_ancestor_fields('_gwseq_pere_externe', array('name' => 'G3', 'race' => '', 'race_autre' => '', 'father' => array('name' => 'G4 (ne doit jamais être proposé)'), 'mother' => null), 0, __('Père de G2', 'gws-core'));
$last_generation_html = ob_get_clean();
gws_test_assert(strpos($last_generation_html, 'Génération 3 sur 3 — dernière génération') !== false, 'Génération 3 : le compteur identifie explicitement la dernière génération');
gws_test_assert(strpos($last_generation_html, '<details') === false, 'Arrêt visuel strict : un nœud de génération 3 ne rend JAMAIS de contrôle « + Renseigner ses origines », même si une donnée de génération 4 existe déjà en base (§11) — impossible de la proposer depuis l’interface');
gws_test_assert(strpos($last_generation_html, 'G4') === false, 'Arrêt visuel strict : une éventuelle donnée de génération 4 présente en base n’est jamais affichée ni éditable depuis l’interface de génération 3');

// --- La limite serveur reste la garantie réelle, indépendamment de l'interface (déjà vérifié
// plus haut via gwseq_sanitize_external_ancestor_tree() et un vrai $_POST profond — rappel ici
// que retirer visuellement le contrôle ne remplace jamais ce contrôle serveur) ---
$raw_beyond_limit = array('name' => 'G1', 'father' => array('name' => 'G2', 'father' => array('name' => 'G3', 'father' => array('name' => 'G4 (doit être ignoré même si soumis manuellement)'))));
$tree_beyond_limit = gwseq_sanitize_external_ancestor_tree($raw_beyond_limit, GWSEQ_PEDIGREE_MAX_DEPTH - 1);
gws_test_assert($tree_beyond_limit['father']['father']['father'] === null, 'Arrêt strict également côté serveur : une génération 4 soumise malgré l’absence de contrôle visuel n’est de toute façon jamais stockée');

// --- Escaping admin : un nom externe contenant du HTML n'est jamais rendu tel quel ---
gwseq_set_horse_parent(800, 'father', array('mode' => 'external', 'external' => array('name' => '<script>alert(1)</script>Voltaire')));
ob_start();
gwseq_render_cheval_pedigree_box(gws_test_make_post_object(800));
$pedigree_box_html_xss = ob_get_clean();
gws_test_assert(strpos($pedigree_box_html_xss, '<script>') === false, 'Escaping admin : un nom externe contenant du HTML n’est jamais injecté tel quel (balise absente du rendu, retirée dès la sanitation)');
gws_test_assert(strpos($pedigree_box_html_xss, 'Voltaire') !== false, 'Escaping admin : le reste du texte saisi (hors balise) est bien conservé et affiché');

// --- Aperçu du resolver (dev) : rendu réel, multi-générations, mélange GWS/externe lisible ---
gws_test_make_post(810, GWSEQ_CPT_CHEVAL, 'Aperçu Racine');
gwseq_set_horse_parent(810, 'father', array('mode' => 'external', 'external' => array('name' => 'Aperçu Père Externe', 'father' => array('name' => 'Aperçu Grand-père Externe'))));
$preview_html = gwseq_render_pedigree_node_preview(gwseq_resolve_horse_pedigree(810));
gws_test_assert(strpos($preview_html, 'Aperçu Père Externe') !== false, 'Aperçu resolver : le nom du père externe apparaît dans le rendu');
gws_test_assert(strpos($preview_html, 'Aperçu Grand-père Externe') !== false, 'Aperçu resolver : le pedigree externe multi-générations est bien rendu récursivement (pas seulement le premier niveau)');
gws_test_assert(strpos($preview_html, 'externe') !== false, 'Aperçu resolver : les ascendants externes sont identifiés comme tels');

// --- Correction lexicale validée : texte de la boîte d'aperçu développeur ---
ob_start();
gwseq_render_cheval_pedigree_preview_box(gws_test_make_post_object(810));
$preview_box_html = ob_get_clean();
gws_test_assert(strpos($preview_box_html, 'Aperçu du pedigree enregistré — actualisé après sauvegarde.') !== false, 'Correction lexicale : la boîte d’aperçu développeur affiche bien le nouveau texte validé');

// --- Production : rendu réel d'une liste de descendants (liens vers les fiches) ---
ob_start();
gwseq_render_cheval_offspring_box(gws_test_make_post_object(600));
$offspring_html = ob_get_clean();
gws_test_assert(strpos($offspring_html, 'Produit Via Père') !== false, 'Production : la fiche des descendants est réellement rendue avec le nom du produit');
gws_test_assert(strpos($offspring_html, '<a href=') !== false, 'Production : un lien vers la fiche du produit est bien généré');

ob_start();
gwseq_render_cheval_offspring_box(gws_test_make_post_object(620));
gws_test_assert(ob_get_clean() === '', 'Production : aucun rendu si le cheval n’a aucun produit (§27 : absence de donnée = absence d’affichage)');

// =====================================================================================
// Compatibilité avec les données de génération 4 déjà enregistrées (correctif référentiel, §11 de
// la demande) : la profondeur standard passe de 4 à 3 générations, mais une sous-branche de
// génération 4 DÉJÀ enregistrée (recettes précédentes) ne doit JAMAIS être supprimée
// silencieusement au prochain enregistrement — le formulaire actuel ne peut plus la soumettre (il
// ne la rend plus), le mécanisme de préservation (paramètre $previous_node de
// gwseq_sanitize_external_ancestor_tree(), relecture préalable dans gwseq_set_horse_parent()) doit
// donc la conserver telle quelle tant que l'ascendant de génération 3 n'a pas changé de nom.
// =====================================================================================

gws_test_make_post(990, GWSEQ_CPT_CHEVAL, 'Cheval Génération 4 Historique');

// --- Étape 1 : une branche de génération 4 est enregistrée directement en base (simulant une
// saisie antérieure au correctif référentiel, alors que la profondeur standard était encore 4) ---
$GLOBALS['__gwseq_test_meta'][990] = array(
  '_gwseq_pere_mode' => 'external',
  '_gwseq_pere_externe' => wp_json_encode(array(
    'name' => 'G1', 'race' => '', 'race_autre' => '', 'annee_naissance' => '',
    'father' => array(
      'name' => 'G2', 'race' => '', 'race_autre' => '', 'annee_naissance' => '',
      'father' => array(
        'name' => 'G3', 'race' => '', 'race_autre' => '', 'annee_naissance' => '',
        'father' => array('name' => 'G4 Historique', 'race' => 'KWPN', 'race_autre' => '', 'annee_naissance' => 1990, 'father' => null, 'mother' => null),
        'mother' => null,
      ),
      'mother' => null,
    ),
    'mother' => null,
  )),
);
$relation_before_resave = gwseq_get_horse_parent(990, 'father');
gws_test_assert($relation_before_resave['external']['father']['father']['father']['name'] === 'G4 Historique', 'Compatibilité génération 4 (§11) : avant tout nouvel enregistrement, la donnée historique de génération 4 est bien lisible telle quelle');

// --- Étape 2 : l'utilisateur enregistre à nouveau la fiche via le formulaire actuel — qui ne rend
// et ne soumet plus QUE 3 générations (G1, G2, G3), sans jamais reproposer G4. Le nom de G3 N'A PAS
// changé : sa sous-branche (G4 Historique) doit être PRÉSERVÉE, jamais supprimée silencieusement ---
$resave_result = gwseq_set_horse_parent(990, 'father', array('mode' => 'external', 'external' => array(
  'name' => 'G1',
  'father' => array('name' => 'G2', 'father' => array('name' => 'G3')),
)));
gws_test_assert($resave_result === true, 'Compatibilité génération 4 (§11) : le nouvel enregistrement (limité à 3 générations soumises) réussit normalement');
$relation_after_resave = gwseq_get_horse_parent(990, 'father');
gws_test_assert(
  $relation_after_resave['external']['father']['father']['name'] === 'G3',
  'Compatibilité génération 4 (§11) : G3 (génération 3, nom inchangé) reste bien le nœud actif après le nouvel enregistrement'
);
gws_test_assert(
  $relation_after_resave['external']['father']['father']['father']['name'] === 'G4 Historique'
    && $relation_after_resave['external']['father']['father']['father']['race'] === 'KWPN'
    && $relation_after_resave['external']['father']['father']['father']['annee_naissance'] === 1990,
  'Compatibilité génération 4 (§11) : la sous-branche de génération 4 déjà enregistrée ("G4 Historique") est bien PRÉSERVÉE intacte (nom, race, année) — le formulaire ne peut plus la soumettre, mais l’enregistrement ne la supprime jamais silencieusement'
);

// --- Le RESOLVER/rendu standard, lui, s'arrête bien à la génération 3 : la donnée de génération 4
// reste en base (jamais supprimée) mais n'est simplement plus jamais rendue à la profondeur
// standard — exactement le comportement documenté §11 ---
$resolved_990 = gwseq_resolve_horse_pedigree(990);
$g3_resolved = $resolved_990['father']['father']['father'];
gws_test_assert($g3_resolved['name'] === 'G3' && !array_key_exists('father', $g3_resolved), 'Compatibilité génération 4 (§11) : au rendu standard (resolver), G3 reste strictement terminal — la génération 4 conservée en base n’est jamais interrogée ni affichée');

// --- Si en revanche le nom de G3 CHANGE lors du nouvel enregistrement, l'ancienne sous-branche
// (qui appartenait à un ascendant différent) est légitimement abandonnée — ce n'est alors PAS une
// perte de donnée de l'ascendant actuel, mais la conséquence normale du remplacement de G3 par un
// autre ascendant ---
gwseq_set_horse_parent(990, 'father', array('mode' => 'external', 'external' => array(
  'name' => 'G1',
  'father' => array('name' => 'G2', 'father' => array('name' => 'G3 Remplacé')),
)));
$relation_after_name_change = gwseq_get_horse_parent(990, 'father');
gws_test_assert(
  $relation_after_name_change['external']['father']['father']['name'] === 'G3 Remplacé' && $relation_after_name_change['external']['father']['father']['father'] === null,
  'Compatibilité génération 4 (§11) : si le nom de l’ascendant de génération 3 change, l’ancienne sous-branche de génération 4 (qui appartenait à un AUTRE ascendant) n’est légitimement plus reprise — la préservation ne s’applique qu’au même ascendant identifié par son nom'
);

// =====================================================================================
// Internationalisation : text domain cohérent, contenu utilisateur jamais traduit
// =====================================================================================
foreach (array($module_dir . 'includes/cheval-pedigree.php', $module_dir . 'includes/pedigree-resolver.php') as $file) {
  preg_match_all('/\b(?:__|_e|esc_html__|esc_html_e|esc_attr__|esc_attr_e)\(\s*[\'"](?:[^\'"\\\\]|\\\\.)*[\'"]\s*,\s*[\'"]([^\'"]*)[\'"]\s*\)/', file_get_contents($file), $domain_matches);
  $mismatched = array_diff(array_unique($domain_matches[1]), array('gws-core'));
  gws_test_assert(empty($mismatched), basename($file) . ' : aucun appel de traduction n’utilise un text domain autre que "gws-core" (trouvé : ' . implode(', ', $mismatched) . ')');
}
gws_test_assert(strpos($preview_html, 'Aperçu Père Externe') !== false, 'i18n : le nom d’un ascendant externe (donnée utilisateur) apparaît strictement tel quel, jamais passé dans une fonction de traduction');

echo ($failures === 0 ? 'Tous les tests sont passés.' : "$failures test(s) en échec.") . "\n";
exit($failures === 0 ? 0 : 1);

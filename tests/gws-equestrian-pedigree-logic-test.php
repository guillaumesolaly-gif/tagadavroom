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

// --- Stubs WordPress minimaux ---
function wp_unslash($value) { return is_array($value) ? array_map('wp_unslash', $value) : $value; }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function sanitize_textarea_field($value) { return trim((string) $value); }
function sanitize_key($value) { return strtolower(preg_replace('/[^a-z0-9_\-]/', '', (string) $value)); }
function esc_url_raw($value) { $value = trim((string) $value); return $value === '' ? '' : $value; }
function esc_url($value) { return $value; }
function absint($value) { return abs((int) $value); }
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function selected($a, $b) { return $a == $b ? ' selected' : ''; }
function checked($a, $b = true) { return $a == $b ? ' checked' : ''; }
function wp_parse_args($args, $defaults = array()) { return array_merge((array) $defaults, (array) $args); }
function wp_nonce_field($action, $field) { echo '<input type="hidden" name="' . esc_attr($field) . '" value="stub-nonce">'; }
function wp_json_encode($data) { return json_encode($data); }

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

function update_post_meta($post_id, $key, $value) { $GLOBALS['__gwseq_test_meta'][$post_id][$key] = $value; return true; }
function get_post_meta($post_id, $key, $single = false) { return $GLOBALS['__gwseq_test_meta'][$post_id][$key] ?? ''; }
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

define('ABSPATH', __DIR__ . '/');
const GWSEQ_CPT_CHEVAL = 'gwseq_cheval';
define('GWSEQ_MODULE_URL', 'https://example.test/wp-content/plugins/gws-core/modules/gws-equestrian/');
define('GWSEQ_MODULE_VERSION', 'test');
$repo_root = dirname(__DIR__);
$module_dir = $repo_root . '/wp-content/plugins/gws-core/modules/gws-equestrian/';
require $repo_root . '/wp-content/plugins/gws-core/includes/fields.php';
require $module_dir . 'includes/settings.php';
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
// Sanitation récursive d'un arbre d'ascendants externes (§2-4, §11, §16 du correctif)
// =====================================================================================

// --- Ascendant externe simple, sans ascendants propres ---
$tree = gwseq_sanitize_external_ancestor_tree(array('name' => 'Kannan', 'breed' => 'KWPN'), 3);
gws_test_assert($tree === array('name' => 'Kannan', 'breed' => 'KWPN', 'father' => null, 'mother' => null), 'Ascendant externe simple : nom et race conservés, aucun ascendant propre fabriqué');

// --- Race facultative ---
$tree = gwseq_sanitize_external_ancestor_tree(array('name' => 'Voltaire'), 3);
gws_test_assert($tree['breed'] === '', 'Ascendant externe : race facultative, absente -> chaîne vide');

// --- Sans nom : aucun nœud, même si un père/une mère étaient fournis (§25) ---
$tree = gwseq_sanitize_external_ancestor_tree(array('breed' => 'KWPN', 'father' => array('name' => 'Un Père')), 3);
gws_test_assert($tree === null, 'Ascendant externe sans nom : aucun nœud stocké, y compris si un sous-arbre était fourni');

// --- Ascendant externe possédant deux parents externes ---
$tree = gwseq_sanitize_external_ancestor_tree(array(
  'name' => 'Kannan', 'breed' => 'KWPN',
  'father' => array('name' => 'Voltaire', 'breed' => 'Hanovrien'),
  'mother' => array('name' => 'Cemeta', 'breed' => 'Trakehner'),
), 3);
gws_test_assert($tree['father']['name'] === 'Voltaire' && $tree['mother']['name'] === 'Cemeta', 'Ascendant externe avec deux parents externes : les deux sont conservés');

// --- Branche externe partiellement renseignée : père rempli, mère absente ---
$tree = gwseq_sanitize_external_ancestor_tree(array(
  'name' => 'Kannan',
  'father' => array('name' => 'Voltaire'),
), 3);
gws_test_assert($tree['father']['name'] === 'Voltaire' && $tree['mother'] === null, 'Branche externe partiellement renseignée : le père est conservé, la mère reste null (jamais fabriquée)');

// --- Branche externe complète sur plusieurs générations (4 niveaux : L1 à L4) ---
$deep_input = array(
  'name' => 'L1',
  'father' => array(
    'name' => 'L2',
    'father' => array(
      'name' => 'L3',
      'father' => array(
        'name' => 'L4',
        'father' => array('name' => 'L5 (ne doit jamais apparaître)'),
      ),
    ),
  ),
);
$tree = gwseq_sanitize_external_ancestor_tree($deep_input, GWSEQ_PEDIGREE_MAX_DEPTH - 1);
gws_test_assert(
  $tree['name'] === 'L1' && $tree['father']['name'] === 'L2' && $tree['father']['father']['name'] === 'L3' && $tree['father']['father']['father']['name'] === 'L4',
  'Branche externe complète : les 4 générations autorisées (L1 à L4) sont bien conservées'
);

// --- Refus/ignorance propre d'une génération 5 (§16) : jamais stockée, jamais d'erreur ---
gws_test_assert(
  $tree['father']['father']['father']['father'] === null,
  'Génération 5 : silencieusement ignorée à la sanitation, jamais stockée, jamais de contournement de la limite serveur'
);

// --- Sanitation récursive : caractères spéciaux conservés à un niveau imbriqué ---
$tree = gwseq_sanitize_external_ancestor_tree(array(
  'name' => 'Kannan',
  'father' => array('name' => "L'Étalon d'Or", 'breed' => 'Pur-sang Anglais'),
), 3);
gws_test_assert($tree['father']['name'] === "L'Étalon d'Or" && $tree['father']['breed'] === 'Pur-sang Anglais', 'Sanitation récursive : caractères spéciaux (apostrophe, accents) conservés à un niveau imbriqué');

// --- Donnée mal formée à un niveau imbriqué : repli sûr, jamais d'erreur ---
$tree = gwseq_sanitize_external_ancestor_tree(array('name' => 'Kannan', 'father' => 'pas un tableau'), 3);
gws_test_assert($tree['father'] === null, 'Sanitation récursive : une valeur mal formée à un niveau imbriqué (pas un tableau) -> aucun nœud, jamais d’erreur');

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

gwseq_set_horse_parent(20, 'mother', array('mode' => 'external', 'external' => array('name' => 'Jument Externe', 'breed' => 'Camargue')));
$mother = gwseq_get_horse_parent(20, 'mother');
gws_test_assert($mother['mode'] === 'external' && $mother['external']['name'] === 'Jument Externe' && $mother['external']['breed'] === 'Camargue', 'Mère externe : arbre persisté et relu fidèlement');

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
gwseq_set_horse_parent(20, 'mother', array('mode' => 'external', 'external' => array('breed' => 'Camargue')));
gws_test_assert(gwseq_get_horse_parent(20, 'mother')['mode'] === '', 'gwseq_set_horse_parent() : mode externe sans nom -> relation retombe à "aucune"');

// --- Donnée mal formée : jamais d'erreur ---
$result = gwseq_set_horse_parent(20, 'father', 'pas un tableau');
gws_test_assert($result === true, 'gwseq_set_horse_parent() : donnée mal formée -> aucune erreur (repli sûr sur "aucune relation")');

// =====================================================================================
// Chemin programmatique (§15/§31 du correctif) : aucune dépendance à $_POST ni à un faux nonce,
// y compris pour une structure externe imbriquée sur plusieurs générations
// =====================================================================================
$_POST = array(); // volontairement vide : la preuve que la fonction n'en a pas besoin
gws_test_make_post(30, GWSEQ_CPT_CHEVAL, 'Import Test');
$result = gwseq_set_horse_parent(30, 'father', array('mode' => 'external', 'external' => array(
  'name' => 'Ascendant Importé',
  'breed' => 'AQPS',
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
gwseq_set_horse_parent(103, 'mother', array('mode' => 'external', 'external' => array('name' => 'Mère Externe', 'breed' => 'Camargue')));
$node = gwseq_resolve_horse_pedigree(103);
gws_test_assert($node['mother']['type'] === 'external' && $node['mother']['name'] === 'Mère Externe' && $node['mother']['breed'] === 'Camargue', 'Resolver : ascendant externe simple -> résolu correctement');
gws_test_assert($node['mother']['father'] === null && $node['mother']['mother'] === null, 'Resolver : ascendant externe simple -> aucun ascendant propre fabriqué');
gws_test_assert($node['father'] === null, 'Resolver : seulement mère -> père reste null');

// --- Ascendant externe possédant deux parents externes ---
gws_test_make_post(110, GWSEQ_CPT_CHEVAL, 'A Un Père Externe Avec Origines');
gwseq_set_horse_parent(110, 'father', array('mode' => 'external', 'external' => array(
  'name' => 'Kannan', 'breed' => 'KWPN',
  'father' => array('name' => 'Voltaire', 'breed' => 'Hanovrien'),
  'mother' => array('name' => 'Cemeta', 'breed' => 'Trakehner'),
)));
$node = gwseq_resolve_horse_pedigree(110);
gws_test_assert($node['father']['name'] === 'Kannan' && $node['father']['father']['name'] === 'Voltaire' && $node['father']['mother']['name'] === 'Cemeta', 'Resolver : ascendant externe avec deux parents externes -> tous résolus');
gws_test_assert($node['father']['father']['type'] === 'external' && $node['father']['mother']['type'] === 'external', 'Resolver : les ascendants d’un ascendant externe sont eux-mêmes de type "external"');

// --- Branche externe complète sur plusieurs générations (jusqu'à la profondeur maximale) ---
gws_test_make_post(120, GWSEQ_CPT_CHEVAL, 'Pedigree Entièrement Externe En Père');
gwseq_set_horse_parent(120, 'father', array('mode' => 'external', 'external' => array(
  'name' => 'G1',
  'father' => array('name' => 'G2-P', 'father' => array('name' => 'G3-PP', 'father' => array('name' => 'G4-PPP'))),
  'mother' => array('name' => 'G2-M'),
)));
$node = gwseq_resolve_horse_pedigree(120);
gws_test_assert(
  $node['father']['name'] === 'G1' && $node['father']['father']['name'] === 'G2-P' && $node['father']['father']['father']['name'] === 'G3-PP' && $node['father']['father']['father']['father']['name'] === 'G4-PPP',
  'Resolver : branche externe complète -> résolution récursive correcte sur 4 générations'
);
gws_test_assert($node['father']['mother']['name'] === 'G2-M' && $node['father']['mother']['father'] === null, 'Resolver : branche externe partiellement renseignée -> la partie non renseignée reste null');

// --- Pedigree entièrement externe (père ET mère de la racine sont des arbres externes) ---
gws_test_make_post(130, GWSEQ_CPT_CHEVAL, 'Jument À Vendre');
gwseq_set_horse_parent(130, 'father', array('mode' => 'external', 'external' => array('name' => 'Kannan', 'breed' => 'KWPN', 'father' => array('name' => 'Voltaire'))));
gwseq_set_horse_parent(130, 'mother', array('mode' => 'external', 'external' => array('name' => 'Jument X', 'father' => array('name' => 'Étalon Y'))));
$node = gwseq_resolve_horse_pedigree(130);
gws_test_assert(
  $node['type'] === 'gws_horse' && $node['father']['name'] === 'Kannan' && $node['father']['father']['name'] === 'Voltaire' && $node['mother']['name'] === 'Jument X' && $node['mother']['father']['name'] === 'Étalon Y',
  'Resolver : pedigree entièrement externe (aucun ascendant n’est une fiche GWS) -> résolu intégralement sans créer la moindre fiche'
);
gws_test_assert(count($GLOBALS['__gwseq_test_posts']) === count(array_unique(array_keys($GLOBALS['__gwseq_test_posts']))), 'Resolver : aucune fiche cheval artificielle créée pour les ascendants externes (aucun nouvel enregistrement dans la base de test)');

// --- Profondeur maximale (génération 4) pour une branche externe ---
gws_test_make_post(140, GWSEQ_CPT_CHEVAL, 'Racine Profondeur Externe');
gwseq_set_horse_parent(140, 'father', array('mode' => 'external', 'external' => array(
  'name' => 'Gen1', 'father' => array('name' => 'Gen2', 'father' => array('name' => 'Gen3', 'father' => array('name' => 'Gen4', 'father' => array('name' => 'Gen5 (jamais stocké)')))),
)));
$node = gwseq_resolve_horse_pedigree(140, 4);
$gen4 = $node['father']['father']['father']['father'];
gws_test_assert($gen4['type'] === 'external' && $gen4['name'] === 'Gen4', 'Resolver : profondeur maximale (4) -> la 4e génération d’une branche externe est bien résolue en entier');
gws_test_assert($gen4['father'] === null, 'Resolver : au-delà de la 4e génération, une branche externe n’a de toute façon plus rien de stocké (déjà tronquée à la sauvegarde) -> null, jamais une 5e génération');

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

// --- Structure malformée/excessivement profonde ne peut pas contourner la limite serveur, même
// si elle a été injectée directement en base (défense en profondeur du resolver lui-même,
// indépendante de la sanitation à la sauvegarde) ---
gws_test_make_post(170, GWSEQ_CPT_CHEVAL, 'Donnée Corrompue En Base');
$corrupted = array('name' => 'Niveau 1');
$cursor = &$corrupted;
for ($i = 2; $i <= 10; $i++) {
  $cursor['father'] = array('name' => 'Niveau ' . $i);
  $cursor = &$cursor['father'];
}
unset($cursor);
$GLOBALS['__gwseq_test_meta'][170] = array('_gwseq_pere_mode' => 'external', '_gwseq_pere_externe' => wp_json_encode($corrupted));
$node = gwseq_resolve_horse_pedigree(170, 4);
$depth_reached = 0;
$cursor = $node['father'];
while (is_array($cursor) && ($cursor['type'] ?? '') === 'external') {
  $depth_reached++;
  $cursor = $cursor['father'];
}
gws_test_assert($depth_reached === 4, 'Resolver : une structure corrompue en base sur 10 niveaux est strictement bornée à 4 générations, quelle que soit la profondeur réelle des données stockées');
gws_test_assert($cursor === null || ($cursor['type'] ?? '') !== 'external', 'Resolver : au-delà de la limite, plus aucune génération "external" supplémentaire n’apparaît (jamais de contournement de la borne serveur)');

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
gws_test_assert($depth_reached === GWSEQ_PEDIGREE_MAX_DEPTH, 'Sanitation serveur (chemin $_POST réel) : une structure externe soumise sur 8 niveaux est strictement bornée à 4 générations avant même d’être enregistrée');

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
foreach (array('_gwseq_pere_mode', '_gwseq_pere_id', '_gwseq_pere_externe[name]', '_gwseq_pere_externe[breed]', '_gwseq_mere_mode', '_gwseq_mere_id', '_gwseq_mere_externe[name]', '_gwseq_mere_externe[breed]') as $field_name) {
  gws_test_assert(strpos($pedigree_box_html, 'name="' . $field_name . '"') !== false, "Meta box Pedigree : le champ $field_name est réellement rendu");
}
gws_test_assert(strpos($pedigree_box_html, 'name="_gwseq_pere_externe[father][name]"') !== false, 'Meta box Pedigree : les champs de la génération suivante (père du père externe) sont bien rendus, jusqu’à la profondeur autorisée');
gws_test_assert(strpos($pedigree_box_html, '<details') !== false, 'Meta box Pedigree : la divulgation progressive (§5) utilise l’élément natif <details>, sans JavaScript nécessaire pour se déplier');

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

<?php
/**
 * Vérifie la logique de l'Étape 1 (Fondations) du module GWS Equestrian : ce que le module
 * enregistre réellement (post types, taxonomie) et le respect des contraintes techniques et de
 * convention qui ont guidé sa conception (limite de longueur WordPress, préfixe distinct,
 * absence de page publique pour le Groupe tarifaire). Ne remplace pas une recette réelle dans
 * WordPress (voir AI-AGENT.md §7) : ce test porte sur les arguments passés aux fonctions
 * d'enregistrement, pas sur le comportement réel de WordPress une fois ces types enregistrés.
 *
 * Ne fait pas partie des paquets livrés (gws-core.zip / gws-starter.zip).
 */

$failures = 0;
function gws_test_assert($condition, $label) {
  global $failures;
  if ($condition) { echo "OK   - $label\n"; }
  else { echo "FAIL - $label\n"; $failures++; }
}

// --- Stubs WordPress minimaux : on capture les appels plutôt que de simuler un vrai registre ---
$GLOBALS['__gwseq_test_post_types'] = array();
function register_post_type($post_type, $args = array()) {
  $GLOBALS['__gwseq_test_post_types'][$post_type] = $args;
}

$GLOBALS['__gwseq_test_taxonomies'] = array();
function register_taxonomy($taxonomy, $object_type, $args = array()) {
  $GLOBALS['__gwseq_test_taxonomies'][$taxonomy] = array('object_type' => $object_type, 'args' => $args);
}

// Simule le hook 'init' en exécutant immédiatement le callback : suffisant pour les fonctions
// d'enregistrement testées ici. Les autres hooks (ex. admin_enqueue_scripts, ajoutés depuis
// l'Étape 2 pour la démonstration QA du composant répétable) ne sont pas déclenchés par ce stub
// : hors du périmètre de ce test, qui ne porte que sur les fondations (post types/taxonomie).
function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
  if ($hook === 'init' && is_callable($callback)) call_user_func($callback);
}

// Depuis l'Étape 2, module.php charge aussi le composant répétable et sa démonstration QA
// (includes/repeater-field.php, includes/qa-repeater.php). Ce test ne porte que sur les
// fondations (post types/taxonomie) : environnement "production" simulé pour que la
// démonstration QA (réservée à local/development) ne s'enregistre pas et n'ajoute donc aucun
// objet supplémentaire aux assertions ci-dessous — voir gws-equestrian-repeater-logic-test.php
// pour les tests dédiés au composant répétable lui-même.
function wp_get_environment_type() { return 'production'; }

define('ABSPATH', __DIR__ . '/');
define('GWS_CORE_DIR', dirname(__DIR__) . '/wp-content/plugins/gws-core/');
define('GWS_CORE_URL', 'https://example.test/wp-content/plugins/gws-core/');
$module_dir = GWS_CORE_DIR . 'modules/gws-equestrian/';

require $module_dir . 'module.php';

$post_types = $GLOBALS['__gwseq_test_post_types'];
$taxonomies = $GLOBALS['__gwseq_test_taxonomies'];

// --- Post types attendus, ni plus ni moins ---
gws_test_assert(
  count($post_types) === 3,
  'Exactement trois post types enregistrés à cette étape'
);
foreach (array(GWSEQ_CPT_PRESTATION, GWSEQ_CPT_GROUPE, GWSEQ_CPT_CHEVAL) as $expected) {
  gws_test_assert(
    array_key_exists($expected, $post_types),
    "Post type attendu enregistré : $expected"
  );
}

// --- Contrainte technique WordPress : 20 caractères max pour un nom de post type ---
foreach ($post_types as $slug => $args) {
  gws_test_assert(
    strlen($slug) <= 20,
    "Post type '$slug' respecte la limite WordPress de 20 caractères (longueur réelle : " . strlen($slug) . ')'
  );
}

// --- Contrainte technique WordPress : 32 caractères max pour un nom de taxonomie ---
foreach ($taxonomies as $slug => $data) {
  gws_test_assert(
    strlen($slug) <= 32,
    "Taxonomie '$slug' respecte la limite WordPress de 32 caractères (longueur réelle : " . strlen($slug) . ')'
  );
}

// --- Préfixe distinct du module (AI-AGENT.md §3 / ARCHITECTURE.md §8) : jamais gws_/gws_core_,
// toujours gwseq_, aussi bien pour les slugs que pour les noms de fonctions déclarées ---
foreach (array_keys($post_types) as $slug) {
  gws_test_assert(strpos($slug, 'gwseq_') === 0, "Post type '$slug' porte bien le préfixe gwseq_");
}
foreach (array_keys($taxonomies) as $slug) {
  gws_test_assert(strpos($slug, 'gwseq_') === 0, "Taxonomie '$slug' porte bien le préfixe gwseq_");
}

$module_files = array(
  $module_dir . 'module.php',
  $module_dir . 'includes/post-types.php',
  $module_dir . 'includes/taxonomies.php',
  $module_dir . 'includes/repeater-field.php',
  $module_dir . 'includes/qa-repeater.php',
);
$prefix_violation_found = false;
$non_gwseq_functions = array();
foreach ($module_files as $file) {
  $contents = file_get_contents($file);
  if (preg_match('/\bfunction\s+(gws_core_|gws_)[a-zA-Z0-9_]*\s*\(/', $contents)) {
    $prefix_violation_found = true;
  }
  if (preg_match_all('/\bfunction\s+([a-zA-Z0-9_]+)\s*\(/', $contents, $matches)) {
    foreach ($matches[1] as $function_name) {
      if (strpos($function_name, 'gwseq_') !== 0) $non_gwseq_functions[] = $function_name;
    }
  }
}
gws_test_assert(
  !$prefix_violation_found,
  'Aucune fonction du module ne réutilise le préfixe réservé au cœur (gws_/gws_core_)'
);
gws_test_assert(
  empty($non_gwseq_functions),
  'Toutes les fonctions déclarées par le module utilisent le préfixe gwseq_ (' . (empty($non_gwseq_functions) ? 'aucune exception' : implode(', ', $non_gwseq_functions)) . ')'
);

// --- Aucune collision de slug avec les post types déjà utilisés par d'autres modules du projet
// (voir le registre de modules/README.md) ---
$other_modules_post_types = array('bp_item', 'gws_qa_item');
foreach (array_keys($post_types) as $slug) {
  gws_test_assert(
    !in_array($slug, $other_modules_post_types, true),
    "Post type '$slug' ne collisionne pas avec un post type d'un autre module du projet"
  );
}

// --- Groupe tarifaire : jamais de page publique (décision de conception validée) ---
$groupe = $post_types[GWSEQ_CPT_GROUPE] ?? array();
gws_test_assert(($groupe['public'] ?? null) === false, 'Groupe tarifaire : public => false');
gws_test_assert(($groupe['has_archive'] ?? null) === false, 'Groupe tarifaire : has_archive => false');
gws_test_assert(($groupe['rewrite'] ?? null) === false, 'Groupe tarifaire : rewrite => false (aucune URL générée)');
gws_test_assert(($groupe['exclude_from_search'] ?? null) === true, 'Groupe tarifaire : exclu de la recherche');

// --- Prestation et Cheval : publics avec archive, à l'inverse du Groupe tarifaire ---
foreach (array(GWSEQ_CPT_PRESTATION => 'Prestation', GWSEQ_CPT_CHEVAL => 'Cheval') as $slug => $label) {
  $args = $post_types[$slug] ?? array();
  gws_test_assert(($args['public'] ?? null) === true, "$label : public => true");
  gws_test_assert(($args['has_archive'] ?? null) === true, "$label : has_archive => true");
}

// --- Taxonomie catégorie de cheval : attachée au bon post type, multi-valeurs (non hiérarchique) ---
$categorie = $taxonomies[GWSEQ_TAX_CATEGORIE_CHEVAL] ?? null;
gws_test_assert($categorie !== null, 'Taxonomie catégorie de cheval enregistrée');
if ($categorie !== null) {
  gws_test_assert(
    $categorie['object_type'] === GWSEQ_CPT_CHEVAL,
    'Taxonomie catégorie de cheval attachée au post type Cheval'
  );
  gws_test_assert(
    ($categorie['args']['hierarchical'] ?? null) === false,
    'Taxonomie catégorie de cheval non hiérarchique (compatible multi-valeurs, un cheval peut avoir plusieurs catégories)'
  );
}

echo ($failures === 0 ? 'Tous les tests sont passés.' : "$failures test(s) en échec.") . "\n";
exit($failures === 0 ? 0 : 1);

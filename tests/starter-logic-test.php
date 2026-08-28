<?php
/**
 * Tests de logique autonomes (aucune installation WordPress requise) pour les comportements
 * corrigés dans cette passe :
 *   1) priorité projet > module > fallback pour les gabarits single/archive de CPT ;
 *   2) bascule de développement du module QA (sans édition manuelle de config/modules.php).
 *
 * Ne fait pas partie des paquets livrés (exclu de gws-core.zip / gws-starter.zip). Exécuter :
 *   php tests/starter-logic-test.php
 *
 * Ce script vérifie la LOGIQUE pure (manipulation de tableaux, lecture d'options, détection
 * d'environnement) en stubant le minimum d'API WordPress nécessaire pour charger les vrais
 * fichiers du starter sans un WordPress complet. Il ne remplace pas une recette réelle dans
 * WordPress (rendu HTML, focus clavier, comportement navigateur de `inert`, admin réel) — voir
 * la procédure QA pour ces vérifications.
 */

$failures = 0;
function gws_test_assert($condition, $label) {
  global $failures;
  if ($condition) {
    echo "OK   - $label\n";
  } else {
    echo "FAIL - $label\n";
    $failures++;
  }
}

// --- Stubs WordPress minimaux (juste ce qu'il faut pour charger les fichiers réels) ---
function add_action(...$args) {}
function add_filter(...$args) {}
function sanitize_key($key) { return strtolower(preg_replace('/[^a-z0-9_\-]/', '', (string) $key)); }

$GLOBALS['__gws_test_options'] = array();
function get_option($name, $default = false) {
  return array_key_exists($name, $GLOBALS['__gws_test_options']) ? $GLOBALS['__gws_test_options'][$name] : $default;
}
function update_option($name, $value, $autoload = null) { $GLOBALS['__gws_test_options'][$name] = $value; return true; }
function delete_option($name) { unset($GLOBALS['__gws_test_options'][$name]); return true; }

$GLOBALS['__gws_test_environment'] = 'production';
function wp_get_environment_type() { return $GLOBALS['__gws_test_environment']; }

define('ABSPATH', __DIR__ . '/');
$repo_root = dirname(__DIR__);

// =====================================================================================
// 1) Priorité des gabarits single/archive fournis par un module (inc/module-templates.php)
// =====================================================================================

define('GWS_THEME_DIR', $repo_root . '/wp-content/themes/gws-starter');

// gws_active_module_slugs() lit gws_core_active_modules() si elle existe : on la stub
// nous-mêmes pour piloter précisément la liste des modules "actifs" dans chaque scénario,
// sans dépendre du vrai plugin (déjà testé séparément plus bas).
$GLOBALS['__gws_test_active_modules'] = array();
function gws_core_active_modules() { return $GLOBALS['__gws_test_active_modules']; }

require $repo_root . '/wp-content/themes/gws-starter/inc/module-templates.php';

echo "\n--- Hiérarchie single/archive : QA actif, aucun gabarit projet ---\n";
$GLOBALS['__gws_test_active_modules'] = array('qa');
$hierarchy = array('single-gws_qa_item-un-slug.php', 'single-gws_qa_item.php', 'single.php');
$result = gws_insert_module_template_in_hierarchy($hierarchy, 'single-gws_qa_item.php');
gws_test_assert(
  $result === array('single-gws_qa_item-un-slug.php', 'single-gws_qa_item.php', 'modules/qa/templates/single-gws_qa_item.php', 'single.php'),
  'single_template_hierarchy : le gabarit du module QA est inséré entre l’entrée spécifique et le fallback générique'
);

echo "\n--- Hiérarchie single/archive : QA actif, un gabarit projet existe déjà ---\n";
// Le gabarit spécifique au projet reste à sa position d'origine, AVANT celui du module : c'est
// locate_template() (WordPress) qui, en cherchant dans l'ordre, le trouvera en premier s'il
// existe réellement sur le disque du thème — cette hiérarchie n'a pas besoin de le savoir.
gws_test_assert(
  array_search('single-gws_qa_item.php', $result, true) === 1 && array_search('single-gws_qa_item.php', $result, true) < array_search('modules/qa/templates/single-gws_qa_item.php', $result, true),
  'single_template_hierarchy : l’entrée spécifique au projet reste avant celle du module (locate_template() la choisira en premier si le fichier existe)'
);

echo "\n--- Hiérarchie single/archive : module désactivé ---\n";
$GLOBALS['__gws_test_active_modules'] = array();
$hierarchy = array('single-gws_qa_item-un-slug.php', 'single-gws_qa_item.php', 'single.php');
$result = gws_insert_module_template_in_hierarchy($hierarchy, 'single-gws_qa_item.php');
gws_test_assert(
  $result === $hierarchy,
  'single_template_hierarchy : module désactivé -> hiérarchie inchangée (fallback WordPress/thème normal, sans erreur)'
);

echo "\n--- Même contrôle pour les archives ---\n";
$GLOBALS['__gws_test_active_modules'] = array('qa');
$hierarchy = array('archive-gws_qa_item.php', 'archive.php');
$result = gws_insert_module_template_in_hierarchy($hierarchy, 'archive-gws_qa_item.php');
gws_test_assert(
  $result === array('archive-gws_qa_item.php', 'modules/qa/templates/archive-gws_qa_item.php', 'archive.php'),
  'archive_template_hierarchy : le gabarit du module QA est inséré entre l’entrée spécifique et le fallback générique'
);

$GLOBALS['__gws_test_active_modules'] = array();
$hierarchy = array('archive-gws_qa_item.php', 'archive.php');
$result = gws_insert_module_template_in_hierarchy($hierarchy, 'archive-gws_qa_item.php');
gws_test_assert($result === $hierarchy, 'archive_template_hierarchy : module désactivé -> hiérarchie inchangée');

echo "\n--- Vérification sur le disque réel (le fichier annoncé doit exister, aucun résidu de copie) ---\n";
gws_test_assert(
  file_exists(GWS_THEME_DIR . '/modules/qa/templates/single-gws_qa_item.php'),
  'Le fichier modules/qa/templates/single-gws_qa_item.php existe réellement (locate_template() pourra le trouver)'
);
gws_test_assert(
  !file_exists(GWS_THEME_DIR . '/single-gws_qa_item.php') && !file_exists(GWS_THEME_DIR . '/archive-gws_qa_item.php'),
  'Aucun fichier single-/archive-gws_qa_item.php ne traîne à la racine du thème (pas de résidu de copie manuelle)'
);
gws_test_assert(
  !is_dir(GWS_THEME_DIR . '/page-templates'),
  'Aucun dossier page-templates/ à la racine du thème (aucun gabarit de module copié)'
);

// =====================================================================================
// 1bis) Simulation fidèle de locate_template() (algorithme WordPress réel : premier fichier
// existant dans l'ordre de la hiérarchie) pour les trois scénarios explicitement demandés,
// avec création/suppression réelle d'un fichier "projet" temporaire.
// =====================================================================================
function gws_test_locate_template($templates, $theme_dir) {
  foreach ($templates as $name) {
    if (file_exists($theme_dir . '/' . $name)) return $name;
  }
  return '';
}

echo "\n--- Scénario 1 : module QA actif, aucun single-gws_qa_item.php spécifique -> gabarit du module utilisé ---\n";
$GLOBALS['__gws_test_active_modules'] = array('qa');
$hierarchy = gws_insert_module_template_in_hierarchy(array('single-gws_qa_item.php', 'single.php'), 'single-gws_qa_item.php');
gws_test_assert(
  gws_test_locate_template($hierarchy, GWS_THEME_DIR) === 'modules/qa/templates/single-gws_qa_item.php',
  'Scénario 1 (single) : le gabarit du module gagne face au seul fallback single.php'
);

echo "\n--- Scénario 2 : ajout d'un single-gws_qa_item.php spécifique au projet -> celui-ci devient prioritaire ---\n";
$project_override = GWS_THEME_DIR . '/single-gws_qa_item.php';
file_put_contents($project_override, "<?php // fichier de test temporaire\n");
try {
  gws_test_assert(
    gws_test_locate_template($hierarchy, GWS_THEME_DIR) === 'single-gws_qa_item.php',
    'Scénario 2 (single) : le gabarit spécifique au projet passe devant celui du module dès qu’il existe'
  );
} finally {
  unlink($project_override); // ne jamais laisser de résidu, même si l'assertion échoue
}
gws_test_assert(!file_exists($project_override), 'Scénario 2 : le fichier de test temporaire a bien été nettoyé');

echo "\n--- Scénario 3 : module désactivé -> fallback WordPress/thème normal, sans erreur ---\n";
$GLOBALS['__gws_test_active_modules'] = array();
$hierarchy = gws_insert_module_template_in_hierarchy(array('single-gws_qa_item.php', 'single.php'), 'single-gws_qa_item.php');
gws_test_assert(
  gws_test_locate_template($hierarchy, GWS_THEME_DIR) === 'single.php',
  'Scénario 3 (single) : bascule sur le fallback générique du thème, aucune entrée module ni erreur'
);

echo "\n--- Mêmes trois scénarios pour l'archive ---\n";
$GLOBALS['__gws_test_active_modules'] = array('qa');
$hierarchy = gws_insert_module_template_in_hierarchy(array('archive-gws_qa_item.php', 'archive.php'), 'archive-gws_qa_item.php');
gws_test_assert(
  gws_test_locate_template($hierarchy, GWS_THEME_DIR) === 'modules/qa/templates/archive-gws_qa_item.php',
  'Scénario 1 (archive) : le gabarit du module gagne face au seul fallback archive.php'
);

$project_override = GWS_THEME_DIR . '/archive-gws_qa_item.php';
file_put_contents($project_override, "<?php // fichier de test temporaire\n");
try {
  gws_test_assert(
    gws_test_locate_template($hierarchy, GWS_THEME_DIR) === 'archive-gws_qa_item.php',
    'Scénario 2 (archive) : le gabarit spécifique au projet passe devant celui du module'
  );
} finally {
  unlink($project_override);
}
gws_test_assert(!file_exists($project_override), 'Scénario 2 (archive) : le fichier de test temporaire a bien été nettoyé');

$GLOBALS['__gws_test_active_modules'] = array();
$hierarchy = gws_insert_module_template_in_hierarchy(array('archive-gws_qa_item.php', 'archive.php'), 'archive-gws_qa_item.php');
gws_test_assert(
  gws_test_locate_template($hierarchy, GWS_THEME_DIR) === 'archive.php',
  'Scénario 3 (archive) : bascule sur le fallback générique, aucune entrée module ni erreur'
);

// =====================================================================================
// 2) Bascule de développement du module QA (includes/modules.php)
// =====================================================================================

define('GWS_CORE_DIR', $repo_root . '/wp-content/plugins/gws-core/');
// gws_core_active_modules() est déjà définie plus haut (stub) : on la retire pour charger la
// VRAIE implémentation du plugin, celle qu'on veut réellement tester ici.
// (Impossible de "undefine" une fonction en PHP : on isole donc ce bloc dans un processus PHP
// séparé pour ne pas entrer en collision avec le stub déjà chargé ci-dessus.)
$isolated = shell_exec(PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/qa-toggle-logic-test.php') . ' 2>&1');
echo "\n--- Bascule de développement QA (processus isolé) ---\n";
echo $isolated;
if (strpos($isolated, 'FAIL') !== false || $isolated === null) $failures++;

echo "\n" . ($failures === 0 ? 'Tous les tests sont passés.' : "$failures test(s) en échec.") . "\n";
exit($failures === 0 ? 0 : 1);

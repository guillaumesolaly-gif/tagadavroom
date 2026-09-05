<?php
/**
 * Vérifie le diagnostic instrumenté de performance de l'écran d'édition Cheval (includes/cheval-
 * perf-diagnostic.php, anomalie de recette Lot 2B — ~38 secondes constatées) : ce fichier NE
 * MODIFIE AUCUN COMPORTEMENT MÉTIER, cette suite vérifie donc PRÉCISÉMENT cette propriété — gating
 * strict local/développement uniquement (même garde que includes/admin/qa-tool-page.php de
 * gws-core), portée strictement limitée à l'écran d'édition d'UNE fiche Cheval (jamais la liste,
 * jamais un autre CPT, jamais le front), et surtout que l'enveloppement d'un callback de boîte
 * méta ne change JAMAIS son comportement réel (mêmes arguments, même sortie, même valeur de
 * retour) — seule une mesure est ajoutée autour de l'appel existant.
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
$GLOBALS['__gws_test_environment'] = 'production';
function wp_get_environment_type() { return $GLOBALS['__gws_test_environment']; }

$GLOBALS['__gwseq_test_is_admin'] = true;
function is_admin() { return $GLOBALS['__gwseq_test_is_admin']; }

$GLOBALS['__gwseq_test_screen'] = null;
function get_current_screen() { return $GLOBALS['__gwseq_test_screen']; }

$GLOBALS['__gwseq_test_actions'] = array();
function add_action($hook, $callback, $priority = 10, $accepted_args = 1) { $GLOBALS['__gwseq_test_actions'][$hook][] = array($callback, $priority); }

function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function get_the_ID() { return $GLOBALS['__gwseq_test_current_post_id'] ?? 0; }
function wp_normalize_path($path) { return str_replace('\\', '/', (string) $path); }

// Arborescence FICTIVE (créée uniquement pour ce test, jamais dans le vrai dépôt) reproduisant les
// emplacements réels que gwseq_perf_diag_describe_callable() doit savoir classer par réflexion :
// un plugin précis, un thème précis, un mu-plugin, et le cœur WordPress (wp-admin/wp-includes) —
// sans jamais connaître ces chemins à l'avance dans le code de production, exactement comme un
// vrai plugin tiers déjà installé sur le site du cabinet.
$fixture_root = sys_get_temp_dir() . '/gwseq_perf_diag_fixtures_' . getmypid() . '_' . uniqid();
$fixture_dirs = array(
  'wp-admin',
  'wp-includes',
  'wp-content/plugins/mon-plugin-tiers',
  'wp-content/themes/mon-theme',
  'wp-content/mu-plugins',
  'ailleurs-hors-wordpress',
);
foreach ($fixture_dirs as $dir) {
  if (!is_dir($fixture_root . '/' . $dir)) { mkdir($fixture_root . '/' . $dir, 0777, true); }
}
$fixture_files = array(
  'wp-admin/core-fixture.php' => 'function gws_test_fixture_wp_admin_function() {}',
  'wp-includes/includes-fixture.php' => 'function gws_test_fixture_wp_includes_function() {}',
  'wp-content/plugins/mon-plugin-tiers/plugin-fixture.php' => "function gws_test_fixture_plugin_function() {}\nclass Gws_Test_Fixture_Plugin_Class { public function methode_instance() {} public static function methode_statique() {} }",
  'wp-content/themes/mon-theme/theme-fixture.php' => 'function gws_test_fixture_theme_function() {}',
  'wp-content/mu-plugins/mu-fixture.php' => 'function gws_test_fixture_mu_function() {}',
  'ailleurs-hors-wordpress/raw-fixture.php' => 'function gws_test_fixture_raw_function() {}',
);
foreach ($fixture_files as $relative => $body) {
  file_put_contents($fixture_root . '/' . $relative, "<?php\n$body\n");
  require $fixture_root . '/' . $relative;
}
register_shutdown_function(function () use ($fixture_root) {
  $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fixture_root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
  foreach ($it as $file) { $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname()); }
  rmdir($fixture_root);
});

define('ABSPATH', $fixture_root . '/');
define('WP_CONTENT_DIR', $fixture_root . '/wp-content');
const GWSEQ_CPT_CHEVAL = 'gwseq_cheval';

$repo_root = dirname(__DIR__);
$module_dir = $repo_root . '/wp-content/plugins/gws-core/modules/gws-equestrian/';

// =====================================================================================
// Gating (§ même garde que le module QA) : entièrement inerte hors local/développement.
// =====================================================================================

$GLOBALS['__gws_test_environment'] = 'production';
require $module_dir . 'includes/cheval-perf-diagnostic.php';
gws_test_assert($GLOBALS['__gwseq_test_actions'] === array(), 'Gating : en production, AUCUN hook n’est enregistré (fichier entièrement inerte, même garde que le module QA)');
gws_test_assert(!isset($GLOBALS['__gwseq_perf_diag']), 'Gating : en production, aucune structure de mesure n’est même initialisée');

// Recharge le fichier dans un SOUS-PROCESSUS distinct pour tester le cas "local" — PHP ne permet
// pas de re-require un fichier déjà chargé (garde par nature de ce test, jamais un problème réel :
// wp_get_environment_type() est fixé pour toute la durée d'une requête WordPress).
$php_bin = PHP_BINARY ?: 'php';
$sub_script = tempnam(sys_get_temp_dir(), 'gwseq_perf_diag_local_');
file_put_contents($sub_script, <<<PHP
<?php
\$GLOBALS['__gws_test_environment'] = 'local';
function wp_get_environment_type() { return \$GLOBALS['__gws_test_environment']; }
\$GLOBALS['__gwseq_test_actions'] = array();
function add_action(\$hook, \$callback, \$priority = 10, \$accepted_args = 1) { \$GLOBALS['__gwseq_test_actions'][\$hook][] = array(\$callback, \$priority); }
function is_admin() { return true; }
function get_current_screen() { return null; }
function esc_html(\$v) { return htmlspecialchars((string) \$v, ENT_QUOTES); }
function get_the_ID() { return 0; }
define('ABSPATH', __DIR__ . '/');
const GWSEQ_CPT_CHEVAL = 'gwseq_cheval';
require '$module_dir' . 'includes/cheval-perf-diagnostic.php';
echo json_encode(array(
  'hooks' => array_keys(\$GLOBALS['__gwseq_test_actions']),
  'diag_initialized' => isset(\$GLOBALS['__gwseq_perf_diag']),
  'target_hooks' => defined('GWSEQ_PERF_DIAG_TARGET_HOOKS') ? GWSEQ_PERF_DIAG_TARGET_HOOKS : null,
));
PHP
);
$sub_output = shell_exec(escapeshellarg($php_bin) . ' ' . escapeshellarg($sub_script) . ' 2>&1');
unlink($sub_script);
$sub_result = json_decode(trim((string) $sub_output), true);
gws_test_assert(is_array($sub_result), 'Gating : le sous-processus "local" s’exécute sans erreur fatale (sortie JSON valide) — sortie brute : ' . substr((string) $sub_output, 0, 300));
// GWSEQ_PERF_DIAG_TARGET_HOOKS n'est jamais définie dans CE processus (le require initial en
// mode "production" ci-dessus s'est arrêté avant cette ligne — seules les fonctions/classes du
// fichier sont hoistées par PHP au moment du require, jamais un `const` exécuté au fil de l'eau).
// On récupère donc sa valeur RÉELLE depuis le sous-processus "local", plutôt que de la dupliquer
// à la main dans ce test (ce qui pourrait diverger silencieusement du fichier de production).
$target_hooks = array();
if (is_array($sub_result)) {
  $expected_hooks = array('plugins_loaded', 'init', 'admin_init', 'current_screen', 'load-post.php', 'load-post-new.php', 'admin_enqueue_scripts', 'add_meta_boxes_gwseq_cheval', 'admin_footer');
  $missing = array_diff($expected_hooks, $sub_result['hooks']);
  gws_test_assert($missing === array(), 'Gating : en local, tous les hooks attendus sont enregistrés (manquants : ' . implode(',', $missing) . ')');
  gws_test_assert($sub_result['diag_initialized'] === true, 'Gating : en local, la structure de mesure est bien initialisée');
  $target_hooks = is_array($sub_result['target_hooks']) ? $sub_result['target_hooks'] : array();
  gws_test_assert($target_hooks === array('current_screen', 'admin_init', 'load-post.php', 'load-post-new.php', 'admin_enqueue_scripts'), 'Gating : GWSEQ_PERF_DIAG_TARGET_HOOKS liste exactement les cinq hooks de la fenêtre concernée, dans l’ordre réel de déclenchement WordPress');
}
// Réinjecte la valeur RÉELLE (obtenue ci-dessus depuis le sous-processus qui a exécuté le vrai
// fichier de production) dans CE processus : gwseq_perf_diag_install_hook_profilers() en a besoin
// pour être appelée directement plus bas, sans jamais la dupliquer à la main ni risquer une valeur
// de complaisance qui masquerait une régression du fichier réel.
if ($target_hooks !== array()) { define('GWSEQ_PERF_DIAG_TARGET_HOOKS', $target_hooks); }

// =====================================================================================
// gwseq_perf_diag_active_screen() : portée strictement limitée à UNE fiche Cheval en édition.
// =====================================================================================

$GLOBALS['__gwseq_test_is_admin'] = false;
gws_test_assert(gwseq_perf_diag_active_screen() === false, 'Portée : jamais actif hors de l’admin (is_admin() faux)');
$GLOBALS['__gwseq_test_is_admin'] = true;

$GLOBALS['__gwseq_test_screen'] = null;
gws_test_assert(gwseq_perf_diag_active_screen() === false, 'Portée : jamais actif si aucun écran courant (contexte non standard)');

$GLOBALS['__gwseq_test_screen'] = (object) array('base' => 'edit', 'id' => 'edit-gwseq_cheval');
gws_test_assert(gwseq_perf_diag_active_screen() === false, 'Portée : jamais actif sur la LISTE des chevaux (base "edit", jamais "post")');

$GLOBALS['__gwseq_test_screen'] = (object) array('base' => 'post', 'id' => 'gwseq_membre');
gws_test_assert(gwseq_perf_diag_active_screen() === false, 'Portée : jamais actif sur l’écran d’édition d’un AUTRE type de contenu (ici Membre)');

$GLOBALS['__gwseq_test_screen'] = (object) array('base' => 'post', 'id' => GWSEQ_CPT_CHEVAL);
gws_test_assert(gwseq_perf_diag_active_screen() === true, 'Portée : actif UNIQUEMENT sur l’écran d’édition d’une fiche Cheval précise');

// =====================================================================================
// gwseq_perf_diag_mark() : n'enregistre une étape que sur l'écran actif.
// =====================================================================================

$GLOBALS['__gwseq_perf_diag'] = array('boxes' => array(), 'phases' => array(), 'hook_callbacks' => array());
$GLOBALS['__gwseq_test_screen'] = null;
gwseq_perf_diag_mark('test-hors-ecran');
gws_test_assert($GLOBALS['__gwseq_perf_diag']['phases'] === array(), 'Mesure des étapes : aucune entrée enregistrée hors de l’écran d’édition Cheval');

$GLOBALS['__gwseq_test_screen'] = (object) array('base' => 'post', 'id' => GWSEQ_CPT_CHEVAL);
gwseq_perf_diag_mark('test-etape');
gws_test_assert(count($GLOBALS['__gwseq_perf_diag']['phases']) === 1 && $GLOBALS['__gwseq_perf_diag']['phases'][0][0] === 'test-etape', 'Mesure des étapes : une entrée enregistrée sur l’écran actif, avec son horodatage');

// =====================================================================================
// gwseq_perf_diag_wrap_meta_boxes() — PROPRIÉTÉ CRITIQUE : ne change JAMAIS le comportement
// réel d'une boîte méta (mêmes arguments, même sortie, même valeur de retour).
// =====================================================================================

$call_log = array();
$original_callback = function ($post, $box) use (&$call_log) {
  $call_log[] = array('post_id' => $post->ID, 'box_id' => $box['id']);
  echo 'HTML-DE-LA-BOITE-ORIGINALE';
  return 'valeur-de-retour-originale';
};

global $wp_meta_boxes;
$wp_meta_boxes = array(
  GWSEQ_CPT_CHEVAL => array(
    'normal' => array(
      'default' => array(
        'gwseq-cheval-test' => array('id' => 'gwseq-cheval-test', 'title' => 'Test', 'callback' => $original_callback, 'args' => null),
      ),
    ),
  ),
);

// Hors écran actif : l'enveloppement ne doit RIEN modifier (le callback reste l'original).
$GLOBALS['__gwseq_test_screen'] = null;
gwseq_perf_diag_wrap_meta_boxes();
gws_test_assert($wp_meta_boxes[GWSEQ_CPT_CHEVAL]['normal']['default']['gwseq-cheval-test']['callback'] === $original_callback, 'Enveloppement : aucune modification de $wp_meta_boxes hors de l’écran d’édition Cheval');

// Sur l'écran actif : le callback est enveloppé.
$GLOBALS['__gwseq_test_screen'] = (object) array('base' => 'post', 'id' => GWSEQ_CPT_CHEVAL);
$GLOBALS['__gwseq_perf_diag'] = array('boxes' => array(), 'phases' => array(), 'hook_callbacks' => array());
gwseq_perf_diag_wrap_meta_boxes();
$wrapped_callback = $wp_meta_boxes[GWSEQ_CPT_CHEVAL]['normal']['default']['gwseq-cheval-test']['callback'];
gws_test_assert($wrapped_callback !== $original_callback, 'Enveloppement : le callback est bien remplacé par un intermédiaire chronométré');

$fake_post = (object) array('ID' => 42);
$fake_box = array('id' => 'gwseq-cheval-test');
ob_start();
$return_value = call_user_func($wrapped_callback, $fake_post, $fake_box);
$output = ob_get_clean();

gws_test_assert($output === 'HTML-DE-LA-BOITE-ORIGINALE', 'PROPRIÉTÉ CRITIQUE : la sortie HTML réellement produite est STRICTEMENT IDENTIQUE à celle du callback original (aucune altération du rendu réel de la fiche)');
gws_test_assert($return_value === 'valeur-de-retour-originale', 'PROPRIÉTÉ CRITIQUE : la valeur de retour est STRICTEMENT IDENTIQUE à celle du callback original');
gws_test_assert(count($call_log) === 1 && $call_log[0]['post_id'] === 42 && $call_log[0]['box_id'] === 'gwseq-cheval-test', 'PROPRIÉTÉ CRITIQUE : le callback original reçoit EXACTEMENT les mêmes arguments qu’un appel WordPress natif (post, $box)');
gws_test_assert(isset($GLOBALS['__gwseq_perf_diag']['boxes']['gwseq-cheval-test']), 'Mesure : une entrée de chronométrage est bien enregistrée pour cette boîte');
gws_test_assert(is_float($GLOBALS['__gwseq_perf_diag']['boxes']['gwseq-cheval-test']) && $GLOBALS['__gwseq_perf_diag']['boxes']['gwseq-cheval-test'] >= 0, 'Mesure : le temps enregistré est un nombre à virgule flottante positif ou nul (secondes)');

// =====================================================================================
// gwseq_perf_diag_describe_callable() (itération 2) — résolution de la PROVENANCE réelle d'un
// callback par réflexion, sans connaître à l'avance les plugins/thèmes installés sur le site.
// =====================================================================================

$desc = gwseq_perf_diag_describe_callable('gws_test_fixture_plugin_function');
gws_test_assert($desc['source'] === 'plugin:mon-plugin-tiers', 'Provenance : une fonction définie sous wp-content/plugins/<slug>/ est classée "plugin:<slug>" (ici un plugin tiers totalement absent de ce dépôt)');
gws_test_assert(strpos($desc['label'], 'gws_test_fixture_plugin_function') !== false && strpos($desc['label'], 'plugin-fixture.php:') !== false, 'Provenance : le libellé contient le nom de la fonction et son fichier:ligne réels');

$desc = gwseq_perf_diag_describe_callable('gws_test_fixture_theme_function');
gws_test_assert($desc['source'] === 'theme:mon-theme', 'Provenance : une fonction définie sous wp-content/themes/<slug>/ est classée "theme:<slug>"');

$desc = gwseq_perf_diag_describe_callable('gws_test_fixture_mu_function');
gws_test_assert($desc['source'] === 'mu-plugin', 'Provenance : une fonction définie sous wp-content/mu-plugins/ est classée "mu-plugin"');

$desc = gwseq_perf_diag_describe_callable('gws_test_fixture_wp_admin_function');
gws_test_assert($desc['source'] === 'wordpress-core', 'Provenance : une fonction définie sous ABSPATH/wp-admin/ est classée "wordpress-core"');

$desc = gwseq_perf_diag_describe_callable('gws_test_fixture_wp_includes_function');
gws_test_assert($desc['source'] === 'wordpress-core', 'Provenance : une fonction définie sous ABSPATH/wp-includes/ est classée "wordpress-core"');

$desc = gwseq_perf_diag_describe_callable('gws_test_fixture_raw_function');
gws_test_assert($desc['source'] === wp_normalize_path($fixture_root . '/ailleurs-hors-wordpress/raw-fixture.php'), 'Provenance : un fichier hors plugins/thèmes/mu-plugins/cœur (repli) affiche son chemin réel plutôt qu’une classification fausse');

$desc = gwseq_perf_diag_describe_callable('strlen');
gws_test_assert($desc['source'] === 'php/wordpress (fonction native)', 'Provenance : une fonction native PHP (sans fichier réflexible) est classée "php/wordpress (fonction native)"');

$desc = gwseq_perf_diag_describe_callable(array('Gws_Test_Fixture_Plugin_Class', 'methode_statique'));
gws_test_assert($desc['source'] === 'plugin:mon-plugin-tiers', 'Provenance : une méthode STATIQUE ["Classe", "methode"] est réflexie correctement (même classement que les fonctions)');
gws_test_assert(strpos($desc['label'], 'Gws_Test_Fixture_Plugin_Class::methode_statique') !== false, 'Provenance : le libellé d’une méthode statique contient Classe::méthode');

$fixture_instance = new Gws_Test_Fixture_Plugin_Class();
$desc = gwseq_perf_diag_describe_callable(array($fixture_instance, 'methode_instance'));
gws_test_assert($desc['source'] === 'plugin:mon-plugin-tiers', 'Provenance : une méthode D’INSTANCE [$objet, "methode"] est réflexie correctement');
gws_test_assert(strpos($desc['label'], 'Gws_Test_Fixture_Plugin_Class::methode_instance') !== false, 'Provenance : le libellé d’une méthode d’instance contient Classe::méthode (jamais spl_object_hash ou équivalent)');

$desc = gwseq_perf_diag_describe_callable('Gws_Test_Fixture_Plugin_Class::methode_statique');
gws_test_assert($desc['source'] === 'plugin:mon-plugin-tiers', 'Provenance : la notation chaîne "Classe::methode" est réflexie correctement (forme alternative acceptée par is_callable())');

$desc = gwseq_perf_diag_describe_callable('cette_fonction_nexiste_absolument_pas_XYZ');
gws_test_assert($desc['source'] === 'inconnu', 'Provenance : un callable illisible par réflexion (ex. fonction inexistante) ne provoque jamais d’erreur fatale, retombe sur "inconnu"');

// =====================================================================================
// gwseq_perf_diag_wrap_hook_callbacks() (itération 2) — PROPRIÉTÉ CRITIQUE : substitue en place
// les callbacks du registre natif $wp_filter SANS jamais changer leur comportement (arguments,
// valeur de retour, propagation d’une exception éventuelle).
// =====================================================================================

global $wp_filter;
$wp_filter = array();

$hook_call_log = array();
$hook_original = function ($value_a, $value_b) use (&$hook_call_log) {
  $hook_call_log[] = array($value_a, $value_b);
  return $value_a . '-' . $value_b;
};
$wp_filter['test_hook_cible'] = (object) array(
  'callbacks' => array(
    10 => array(
      'entree-a' => array('function' => $hook_original, 'accepted_args' => 2),
    ),
  ),
);

gwseq_perf_diag_wrap_hook_callbacks('test_hook_cible');
$wrapped_hook_callback = $wp_filter['test_hook_cible']->callbacks[10]['entree-a']['function'];
gws_test_assert($wrapped_hook_callback !== $hook_original, 'Enveloppement générique : le callback est bien remplacé par un intermédiaire chronométré dans $wp_filter');

$hook_return = call_user_func($wrapped_hook_callback, 'foo', 'bar');
gws_test_assert($hook_return === 'foo-bar', 'PROPRIÉTÉ CRITIQUE : la valeur de retour du callback natif enveloppé est STRICTEMENT IDENTIQUE à celle de l’original');
gws_test_assert(count($hook_call_log) === 1 && $hook_call_log[0] === array('foo', 'bar'), 'PROPRIÉTÉ CRITIQUE : le callback original reçoit EXACTEMENT les mêmes arguments, dans le même ordre');
gws_test_assert(count($GLOBALS['__gwseq_perf_diag']['hook_callbacks']) === 1, 'Mesure : une entrée de chronométrage est enregistrée pour ce callback natif');
$recorded = $GLOBALS['__gwseq_perf_diag']['hook_callbacks'][0];
gws_test_assert($recorded['hook'] === 'test_hook_cible' && $recorded['priority'] === 10, 'Mesure : le hook et la priorité réels sont enregistrés avec l’entrée');
gws_test_assert(is_float($recorded['elapsed']) && $recorded['elapsed'] >= 0, 'Mesure : la durée enregistrée est un nombre à virgule flottante positif ou nul');

// Propagation d'une exception : ne doit JAMAIS être avalée par l'enveloppement (comportement du
// callback original entièrement préservé, y compris ses erreurs).
$GLOBALS['__gwseq_perf_diag']['hook_callbacks'] = array();
$hook_throws = function () { throw new RuntimeException('erreur-originale-du-callback'); };
$wp_filter['test_hook_erreur'] = (object) array('callbacks' => array(20 => array('entree-b' => array('function' => $hook_throws, 'accepted_args' => 0))));
gwseq_perf_diag_wrap_hook_callbacks('test_hook_erreur');
$wrapped_throwing = $wp_filter['test_hook_erreur']->callbacks[20]['entree-b']['function'];
$caught = null;
try { call_user_func($wrapped_throwing); } catch (RuntimeException $e) { $caught = $e->getMessage(); }
gws_test_assert($caught === 'erreur-originale-du-callback', 'PROPRIÉTÉ CRITIQUE : une exception levée par le callback original continue de se propager normalement, message inchangé');
gws_test_assert(count($GLOBALS['__gwseq_perf_diag']['hook_callbacks']) === 1, 'Mesure : la mesure est bien enregistrée même quand le callback original lève une exception (via finally)');

// Aucun effet si le hook n'existe pas, n'est pas un objet, ou n'a aucun callback — jamais d'erreur.
unset($wp_filter['hook_absent']);
gwseq_perf_diag_wrap_hook_callbacks('hook_absent');
$wp_filter['hook_non_objet'] = array('pas-un-wp-hook');
gwseq_perf_diag_wrap_hook_callbacks('hook_non_objet');
$wp_filter['hook_sans_callbacks'] = (object) array('callbacks' => array());
gwseq_perf_diag_wrap_hook_callbacks('hook_sans_callbacks');
gws_test_assert(true, 'Enveloppement générique : un hook absent, mal formé, ou sans callback n’entraîne jamais d’erreur (no-op silencieux)');

// =====================================================================================
// gwseq_perf_diag_install_hook_profilers() (itération 2) — n'agit que sur l'écran actif, et
// enveloppe alors les CINQ hooks cibles (current_screen/admin_init/load-post(-new).php/
// admin_enqueue_scripts), jamais un hook non listé.
// =====================================================================================

function gws_test_make_hook_stub() { return function () { return 'original'; }; }
$install_originals = array();
foreach ($target_hooks as $hook_name) {
  $stub = gws_test_make_hook_stub();
  $install_originals[$hook_name] = $stub;
  $wp_filter[$hook_name] = (object) array('callbacks' => array(10 => array('entree' => array('function' => $stub, 'accepted_args' => 0))));
}
$wp_filter['un_hook_non_cible'] = (object) array('callbacks' => array(10 => array('entree' => array('function' => gws_test_make_hook_stub(), 'accepted_args' => 0))));
$non_cible_original = $wp_filter['un_hook_non_cible']->callbacks[10]['entree']['function'];

// Hors écran actif : aucun des hooks cibles n'est touché.
$GLOBALS['__gwseq_test_screen'] = null;
gwseq_perf_diag_install_hook_profilers();
$still_original = true;
foreach ($target_hooks as $hook_name) {
  if ($wp_filter[$hook_name]->callbacks[10]['entree']['function'] !== $install_originals[$hook_name]) { $still_original = false; }
}
gws_test_assert($still_original, 'Installation des profileurs : hors de l’écran d’édition Cheval, AUCUN hook cible n’est enveloppé');

// Sur l'écran actif : les cinq hooks cibles sont enveloppés, un hook non listé ne l'est jamais.
$GLOBALS['__gwseq_test_screen'] = (object) array('base' => 'post', 'id' => GWSEQ_CPT_CHEVAL);
gwseq_perf_diag_install_hook_profilers();
$all_wrapped = true;
foreach ($target_hooks as $hook_name) {
  if ($wp_filter[$hook_name]->callbacks[10]['entree']['function'] === $install_originals[$hook_name]) { $all_wrapped = false; }
}
gws_test_assert($all_wrapped, 'Installation des profileurs : sur l’écran d’édition Cheval, les CINQ hooks cibles sont bien enveloppés');
gws_test_assert($wp_filter['un_hook_non_cible']->callbacks[10]['entree']['function'] === $non_cible_original, 'Installation des profileurs : un hook NON listé dans GWSEQ_PERF_DIAG_TARGET_HOOKS n’est jamais touché');

// =====================================================================================
// gwseq_perf_diag_render_report() — rapport lisible, jamais une erreur, jamais de journal
// écrit sans WP_DEBUG_LOG.
// =====================================================================================

$GLOBALS['__gwseq_test_current_post_id'] = 42;
$GLOBALS['__gwseq_perf_diag'] = array(
  'boxes' => array('gwseq-cheval-lente' => 0.9123, 'gwseq-cheval-rapide' => 0.0012),
  'phases' => array(array('plugins_loaded', microtime(true) - 1), array('init:début', microtime(true) - 0.5)),
  'hook_callbacks' => array(),
);
ob_start();
gwseq_perf_diag_render_report();
$report_html = ob_get_clean();

gws_test_assert(strpos($report_html, 'GWS — Diagnostic performance') !== false, 'Rapport : en-tête explicite présent');
gws_test_assert(strpos($report_html, 'gwseq-cheval-lente') !== false && strpos($report_html, 'gwseq-cheval-rapide') !== false, 'Rapport : chaque boîte mesurée apparaît nommément');
gws_test_assert(strpos($report_html, '912.3 ms') !== false, 'Rapport : le temps de la boîte la plus lente est bien converti en millisecondes, lisible directement');
gws_test_assert(strpos($report_html, 'id="gwseq-perf-diag"') !== false, 'Rapport : conteneur identifiable, jamais mêlé au reste du DOM de la fiche');
gws_test_assert(strpos($report_html, 'Callbacks natifs mesurés') === false, 'Rapport : sans aucun callback natif mesuré, la table classée n’apparaît pas (jamais une section vide)');

// =====================================================================================
// gwseq_perf_diag_render_report() (itération 2) — annotation "non expliqué" par étape, et
// table classée des callbacks natifs mesurés (callback -> source -> durée).
// =====================================================================================

$t0 = 1700000000.0;
$GLOBALS['__gwseq_perf_diag'] = array(
  'boxes' => array(),
  'phases' => array(
    array('current_screen:début', $t0),
    array('current_screen:fin', $t0 + 0.100),
  ),
  'hook_callbacks' => array(
    array('hook' => 'current_screen', 'priority' => 20, 'label' => 'callback_b', 'source' => 'plugin:y', 'elapsed' => 0.02),
    array('hook' => 'current_screen', 'priority' => 10, 'label' => 'callback_a', 'source' => 'plugin:x', 'elapsed' => 0.03),
  ),
);
ob_start();
gwseq_perf_diag_render_report();
$report_html_hooks = ob_get_clean();
// Le rapport entier passe par esc_html() avant affichage (protection XSS légitime, puisqu'il
// contient des libellés de callbacks tiers non maîtrisés) : "->" y devient "-&gt;". On décode
// pour comparer au CONTENU réel plutôt qu'à sa représentation HTML échappée.
$report_text_hooks = html_entity_decode($report_html_hooks, ENT_QUOTES);

gws_test_assert(strpos($report_text_hooks, 'current_screen:fin : +100.0 ms (dont 50.0 ms dans les callbacks mesurés sur ce hook, 50.0 ms non expliqué)') !== false, 'Rapport : l’étape "current_screen:fin" annonce le délai total ET la part expliquée/non expliquée par les callbacks natifs mesurés sur ce hook');
gws_test_assert(strpos($report_text_hooks, 'Callbacks natifs mesurés') !== false, 'Rapport : la table classée des callbacks natifs apparaît dès qu’au moins un callback a été mesuré');
$pos_a = strpos($report_text_hooks, '[current_screen] callback_a -> plugin:x -> 30.0 ms');
$pos_b = strpos($report_text_hooks, '[current_screen] callback_b -> plugin:y -> 20.0 ms');
gws_test_assert($pos_a !== false && $pos_b !== false, 'Rapport : chaque callback natif mesuré apparaît au format "[hook] callback -> source -> durée"');
gws_test_assert($pos_a !== false && $pos_b !== false && $pos_a < $pos_b, 'Rapport : la table des callbacks natifs est classée du PLUS LENT au plus rapide (30 ms avant 20 ms)');

echo "\n";
if ($failures > 0) {
  echo "$failures test(s) en échec.\n";
  exit(1);
}
echo "Tous les tests sont passés.\n";

<?php
/**
 * Sous-script isolé de tests/starter-logic-test.php (processus PHP séparé, pour ne pas entrer
 * en collision avec le stub de gws_core_active_modules() utilisé dans l'autre moitié des
 * tests). Vérifie la logique réelle de includes/modules.php pour la bascule de développement
 * du module QA. Ne fait pas partie des paquets livrés.
 */

$failures = 0;
function gws_test_assert($condition, $label) {
  global $failures;
  if ($condition) { echo "OK   - $label\n"; }
  else { echo "FAIL - $label\n"; $failures++; }
}

// --- Stubs WordPress minimaux ---
function add_action(...$args) {}
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
define('GWS_CORE_DIR', dirname(__DIR__) . '/wp-content/plugins/gws-core/');

require GWS_CORE_DIR . 'includes/modules.php';

gws_test_assert(
  gws_core_active_modules() === array(),
  'config/modules.php réel du starter ne déclare aucun module par défaut'
);

// --- Environnement production : la bascule ne doit jamais avoir d'effet, même si l'option est
// activée (ex. une base de dev clonée par erreur vers la prod ne réactive pas QA) ---
$GLOBALS['__gws_test_environment'] = 'production';
update_option('gws_core_qa_dev_enabled', true);
gws_test_assert(
  gws_core_qa_dev_toggle_enabled() === false,
  'Bascule QA : ignorée en environnement production même si l’option est activée'
);
gws_test_assert(
  !in_array('qa', gws_core_active_modules(), true),
  'Bascule QA : le module QA n’apparaît jamais dans les modules actifs en production'
);

// --- Environnement local, bascule désactivée ---
$GLOBALS['__gws_test_environment'] = 'local';
update_option('gws_core_qa_dev_enabled', false);
gws_test_assert(
  !in_array('qa', gws_core_active_modules(), true),
  'Bascule QA : absente des modules actifs tant qu’elle n’a pas été activée, même en local'
);

// --- Environnement local, bascule activée ---
update_option('gws_core_qa_dev_enabled', true);
gws_test_assert(
  in_array('qa', gws_core_active_modules(), true),
  'Bascule QA : présente dans les modules actifs une fois activée en environnement local'
);

// --- Environnement development, bascule activée (l’autre valeur autorisée) ---
$GLOBALS['__gws_test_environment'] = 'development';
gws_test_assert(
  in_array('qa', gws_core_active_modules(), true),
  'Bascule QA : également prise en compte en environnement development'
);

// --- Aucune duplication si 'qa' était aussi listé dans config/modules.php ---
gws_test_assert(
  count(array_keys(gws_core_active_modules(), 'qa', true)) === 1,
  'Bascule QA : jamais de doublon dans la liste des modules actifs'
);

echo ($failures === 0 ? 'Tous les tests sont passés.' : "$failures test(s) en échec.") . "\n";
exit($failures === 0 ? 0 : 1);

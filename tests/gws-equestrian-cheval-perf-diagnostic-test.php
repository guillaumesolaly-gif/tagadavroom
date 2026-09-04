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

define('ABSPATH', __DIR__ . '/');
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
));
PHP
);
$sub_output = shell_exec(escapeshellarg($php_bin) . ' ' . escapeshellarg($sub_script) . ' 2>&1');
unlink($sub_script);
$sub_result = json_decode(trim((string) $sub_output), true);
gws_test_assert(is_array($sub_result), 'Gating : le sous-processus "local" s’exécute sans erreur fatale (sortie JSON valide) — sortie brute : ' . substr((string) $sub_output, 0, 300));
if (is_array($sub_result)) {
  $expected_hooks = array('plugins_loaded', 'init', 'admin_init', 'current_screen', 'admin_enqueue_scripts', 'add_meta_boxes_gwseq_cheval', 'admin_footer');
  $missing = array_diff($expected_hooks, $sub_result['hooks']);
  gws_test_assert($missing === array(), 'Gating : en local, tous les hooks attendus sont enregistrés (manquants : ' . implode(',', $missing) . ')');
  gws_test_assert($sub_result['diag_initialized'] === true, 'Gating : en local, la structure de mesure est bien initialisée');
}

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

$GLOBALS['__gwseq_perf_diag'] = array('boxes' => array(), 'phases' => array());
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
$GLOBALS['__gwseq_perf_diag'] = array('boxes' => array(), 'phases' => array());
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
// gwseq_perf_diag_render_report() — rapport lisible, jamais une erreur, jamais de journal
// écrit sans WP_DEBUG_LOG.
// =====================================================================================

$GLOBALS['__gwseq_test_current_post_id'] = 42;
$GLOBALS['__gwseq_perf_diag'] = array(
  'boxes' => array('gwseq-cheval-lente' => 0.9123, 'gwseq-cheval-rapide' => 0.0012),
  'phases' => array(array('plugins_loaded', microtime(true) - 1), array('init:début', microtime(true) - 0.5)),
);
ob_start();
gwseq_perf_diag_render_report();
$report_html = ob_get_clean();

gws_test_assert(strpos($report_html, 'GWS — Diagnostic performance') !== false, 'Rapport : en-tête explicite présent');
gws_test_assert(strpos($report_html, 'gwseq-cheval-lente') !== false && strpos($report_html, 'gwseq-cheval-rapide') !== false, 'Rapport : chaque boîte mesurée apparaît nommément');
gws_test_assert(strpos($report_html, '912.3 ms') !== false, 'Rapport : le temps de la boîte la plus lente est bien converti en millisecondes, lisible directement');
gws_test_assert(strpos($report_html, 'id="gwseq-perf-diag"') !== false, 'Rapport : conteneur identifiable, jamais mêlé au reste du DOM de la fiche');

echo "\n";
if ($failures > 0) {
  echo "$failures test(s) en échec.\n";
  exit(1);
}
echo "Tous les tests sont passés.\n";

<?php
/**
 * Tests de logique autonomes pour le Schema de la page d'accueil (v1.5.0) :
 * inc/schema.php doit émettre WebSite + Organization sur l'accueil quelle que soit sa
 * configuration WordPress (page statique ou index natif des articles), sans fabriquer de
 * WebPage/Breadcrumb qui ne correspondrait à aucun contenu réel.
 *
 * Exécuter : php tests/schema-homepage-logic-test.php
 * Ne fait pas partie des paquets livrés.
 */

$failures = 0;
function gws_test_assert($condition, $label) {
  global $failures;
  if ($condition) { echo "OK   - $label\n"; }
  else { echo "FAIL - $label\n"; $failures++; }
}

// --- Stubs WordPress/thème minimaux, pilotables via des globales de test ---
function add_action(...$args) {}
function apply_filters($tag, $value) { return $value; }
function esc_attr($v) { return $v; }
function esc_url($v) { return $v; }

$GLOBALS['__gws_test_is_front_page'] = false;
$GLOBALS['__gws_test_is_page'] = false;
$GLOBALS['__gws_test_has_seo_plugin'] = false;
function is_front_page() { return $GLOBALS['__gws_test_is_front_page']; }
function is_page() { return $GLOBALS['__gws_test_is_page']; }
function gws_has_seo_plugin() { return $GLOBALS['__gws_test_has_seo_plugin']; }

function home_url($path = '/') { return 'https://example.test' . $path; }
function get_queried_object_id() { return 42; }
function get_permalink($id = null) { return 'https://example.test/une-page/'; }
function get_the_title($id = null) { return 'Une page'; }
function gws_get_setting($key) { return $key === 'entity_name' ? 'Entité de test' : ''; }
function gws_phone_href() { return ''; }
function wp_json_encode($data, $flags = 0) { return json_encode($data, $flags); }

define('ABSPATH', __DIR__ . '/');
$repo_root = dirname(__DIR__);
require $repo_root . '/wp-content/themes/gws-starter/inc/schema.php';

function gws_test_captured_graph() {
  ob_start();
  gws_site_structured_data();
  $output = ob_get_clean();
  if ($output === '') return null;
  if (!preg_match('#<script type="application/ld\+json">(.*)</script>#s', $output, $m)) return 'PARSE_ERROR';
  $decoded = json_decode($m[1], true);
  return $decoded['@graph'] ?? 'PARSE_ERROR';
}

// =====================================================================================
// Accueil = index natif des derniers articles (pas de Page réelle à décrire)
// =====================================================================================
$GLOBALS['__gws_test_is_front_page'] = true;
$GLOBALS['__gws_test_is_page'] = false;
$GLOBALS['__gws_test_has_seo_plugin'] = false;
$graph = gws_test_captured_graph();
gws_test_assert(is_array($graph), 'Accueil = index des articles : le Schema est bien émis (pas de sortie vide)');
gws_test_assert(is_array($graph) && count($graph) === 2, 'Accueil = index des articles : seuls WebSite + Organization sont émis (pas de WebPage/Breadcrumb fabriqué)');
gws_test_assert(is_array($graph) && $graph[0]['@type'] === 'WebSite' && $graph[1]['@type'] === 'Organization', 'Accueil = index des articles : types WebSite puis Organization corrects');

// =====================================================================================
// Accueil = page statique
// =====================================================================================
$GLOBALS['__gws_test_is_front_page'] = true;
$GLOBALS['__gws_test_is_page'] = true;
$graph = gws_test_captured_graph();
gws_test_assert(is_array($graph) && count($graph) === 3, 'Accueil = page statique : WebSite + Organization + WebPage (pas de Breadcrumb sur l’accueil)');
gws_test_assert(is_array($graph) && $graph[2]['@type'] === 'WebPage', 'Accueil = page statique : WebPage bien présent');

// =====================================================================================
// Page interne classique (comportement historique, inchangé)
// =====================================================================================
$GLOBALS['__gws_test_is_front_page'] = false;
$GLOBALS['__gws_test_is_page'] = true;
$graph = gws_test_captured_graph();
gws_test_assert(is_array($graph) && count($graph) === 4, 'Page interne : WebSite + Organization + WebPage + Breadcrumb (comportement historique conservé)');
gws_test_assert(is_array($graph) && $graph[3]['@type'] === 'BreadcrumbList', 'Page interne : Breadcrumb bien présent');
gws_test_assert(is_array($graph) && isset($graph[2]['breadcrumb']['@id']), 'Page interne : le WebPage référence bien le Breadcrumb');

// =====================================================================================
// Ni une Page ni l'accueil (ex. une archive quelconque) : rien à émettre ici
// =====================================================================================
$GLOBALS['__gws_test_is_front_page'] = false;
$GLOBALS['__gws_test_is_page'] = false;
$graph = gws_test_captured_graph();
gws_test_assert($graph === null, 'Ni Page ni accueil : aucune sortie (comportement historique conservé)');

// =====================================================================================
// Plugin SEO actif : jamais de second graphe, quel que soit le contexte
// =====================================================================================
$GLOBALS['__gws_test_has_seo_plugin'] = true;
$GLOBALS['__gws_test_is_front_page'] = true;
$GLOBALS['__gws_test_is_page'] = false;
$graph = gws_test_captured_graph();
gws_test_assert($graph === null, 'Plugin SEO actif : aucune sortie, même sur l’accueil (pas de graphe concurrent)');

echo "\n" . ($failures === 0 ? 'Tous les tests sont passés.' : "$failures test(s) en échec.") . "\n";
exit($failures === 0 ? 0 : 1);

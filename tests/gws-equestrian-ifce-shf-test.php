<?php
/**
 * Vérifie l'enrichissement opportuniste du N° SIRE via SHF (Lot SHF) au niveau des fonctions PURES
 * de includes/ifce-shf-enrichment.php : garde-fous réseau (hôte/schéma, résolution de redirection),
 * extraction ciblée du libellé "N° SIRE" (jamais un motif recherché n'importe où sur la page),
 * classification HTTP/Content-Type/reconnaissance de fiche, et le point d'entrée
 * gwseq_ifce_shf_lookup_sire_by_id() lui-même — celui-ci sans jamais toucher au réseau réel, via le
 * filtre `gwseq_ifce_shf_fetch_override` (seul point d'extension prévu pour les tests, voir le
 * docblock de tête du fichier testé).
 *
 * N'exécute JAMAIS de vraie requête HTTP vers www.shf.eu — la validation en conditions réelles a été
 * faite séparément via le POC autonome `poc-shf-panel.php` (8/8 fiches trouvées, 8/8 SIRE extraits,
 * témoin négatif correctement classé, voir CR de ce lot) : ce fichier ne fait que vérifier que la
 * logique de décision intégrée à GWS reproduit fidèlement ce comportement déjà validé.
 *
 * Ne fait pas partie des paquets livrés (gws-core.zip / gws-starter.zip).
 */

$failures = 0;
function gws_test_assert($condition, $label) {
  global $failures;
  if ($condition) { echo "OK   - $label\n"; }
  else { echo "FAIL - $label\n"; $failures++; }
}

// --- Stubs WordPress minimaux (mêmes conventions que le reste de ce dossier) : add_filter/
// apply_filters sont ICI de VRAIS stubs capturants (jamais des no-op) — indispensables pour que le
// point d'extension `gwseq_ifce_shf_fetch_override` fonctionne réellement dans ces tests. ---
$GLOBALS['__gwseq_test_filters'] = array();
function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) { $GLOBALS['__gwseq_test_filters'][$hook][] = $callback; }
function remove_all_filters($hook) { unset($GLOBALS['__gwseq_test_filters'][$hook]); }
function apply_filters($hook, ...$args) {
  foreach ($GLOBALS['__gwseq_test_filters'][$hook] ?? array() as $cb) {
    $args[0] = call_user_func_array($cb, $args);
  }
  return $args[0];
}

if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/');

$repo_root = dirname(__DIR__);
$module_dir = $repo_root . '/wp-content/plugins/gws-core/modules/gws-equestrian/';
require $module_dir . 'includes/ifce-shf-enrichment.php';

// =====================================================================================
// 1. Garde-fous réseau (§2/§3 de la demande) — fonctions pures.
// =====================================================================================

gwseq_ifce_shf_assert_allowed_url('https://www.shf.eu/fr/cheval/test,Ilvb0qZm0QvG_PVjtz1ZBlQ.html');
gws_test_assert(true, 'Garde-fou hôte/schéma : https://www.shf.eu accepté (aucune exception levée)');

function gws_test_expect_shf_guard_exception($url) {
  try {
    gwseq_ifce_shf_assert_allowed_url($url);
    return false;
  } catch (RuntimeException $e) {
    return true;
  }
}
gws_test_assert(gws_test_expect_shf_guard_exception('http://www.shf.eu/fr/cheval/test,Ixxx.html'), 'Garde-fou (§2) : le schéma http (non https) est bien refusé');
gws_test_assert(gws_test_expect_shf_guard_exception('https://evil.example.com/fr/cheval/test,Ixxx.html'), 'Garde-fou (§2) : un hôte autre que www.shf.eu est bien refusé — jamais suivi, y compris comme cible de redirection');
gws_test_assert(gws_test_expect_shf_guard_exception('pas-une-url'), 'Garde-fou : une URL syntaxiquement invalide est bien refusée');

gws_test_assert(gwseq_ifce_shf_resolve_redirect_url('https://www.shf.eu/fr/cheval/test,Ixxx.html', 'https://www.shf.eu/fr/cheval/jamerose-de-felines,Ixxx.html') === 'https://www.shf.eu/fr/cheval/jamerose-de-felines,Ixxx.html', 'Résolution de redirection : une Location absolue est utilisée telle quelle');
gws_test_assert(gwseq_ifce_shf_resolve_redirect_url('https://www.shf.eu/fr/cheval/test,Ixxx.html', '/fr/cheval/jamerose-de-felines,Ixxx.html') === 'https://www.shf.eu/fr/cheval/jamerose-de-felines,Ixxx.html', 'Résolution de redirection : une Location relative à la racine est bien recomposée sur le même hôte');
gws_test_assert(gws_test_expect_shf_guard_exception(gwseq_ifce_shf_resolve_redirect_url('https://www.shf.eu/fr/cheval/test,Ixxx.html', 'https://evil.example.com/phishing.html')), 'Garde-fou (§2/§7) : une redirection vers un hôte hors www.shf.eu reste refusée par le garde-fou, quand bien même elle serait résolue');

// =====================================================================================
// 2. Extraction ciblée du N° SIRE (§3/§7) — jamais un motif recherché n'importe où sur la page.
// =====================================================================================

// --- Jamerose (témoin réel validé en Local) ---
$html_jamerose = '<html><body><h1>JAMEROSE DE FELINES</h1>'
  . '<table><tr><td>N&deg; SIRE</td><td>19369410S</td></tr></table></body></html>';
$sire_jamerose = gwseq_ifce_shf_extract_sire_from_html($html_jamerose);
gws_test_assert($sire_jamerose['found_label'] === true && $sire_jamerose['sire'] === '19369410S', 'Extraction ciblée : témoin réel Jamerose -> 19369410S trouvé, exactement la valeur validée en conditions réelles');

// --- Régression : balises intercalées ENTRE la valeur et un décoy juste après, sans espace
// (bug identifié pendant le développement du POC panel — strip_tags() seul recollerait les deux
// nœuds de texte, cassant la frontière de mot juste après la vraie valeur) ---
$html_decoy = '<div class="fiche"><p>Téléphone : 0102030405</p>'
  . '<table><tr><td>N&deg; SIRE</td><td>: <strong>19369410S</strong></td></tr></table>'
  . '<p>Code postal : 75008000A</p></div>';
$sire_decoy = gwseq_ifce_shf_extract_sire_from_html($html_decoy);
gws_test_assert($sire_decoy['sire'] === '19369410S', 'Extraction ciblée (régression POC) : la vraie valeur est trouvée, jamais le décoy "75008000A" présent plus loin dans la fenêtre après suppression des balises adjacentes');

// --- Libellé absent : cas légitime, jamais une erreur ---
$sire_absent = gwseq_ifce_shf_extract_sire_from_html('<div><p>Aucune information disponible.</p></div>');
gws_test_assert($sire_absent['found_label'] === false && $sire_absent['sire'] === null, 'Extraction ciblée : libellé "N° SIRE" absent -> found_label=false, sire=null, jamais une erreur');

// --- Libellé présent mais valeur absente/non conforme à proximité ---
$sire_no_value = gwseq_ifce_shf_extract_sire_from_html('<div>N° SIRE : <em>non communiqué</em></div>');
gws_test_assert($sire_no_value['found_label'] === true && $sire_no_value['sire'] === null, 'Extraction ciblée : libellé présent mais aucune valeur au format attendu à proximité -> sire=null (jamais inventé)');

// --- Balises intercalées DANS le libellé lui-même (ex. mise en forme HTML du "N°") ---
$sire_tagged_label = gwseq_ifce_shf_extract_sire_from_html('<span>N</span><sup>°</sup> <b>SIRE</b> : 12345678Z');
gws_test_assert($sire_tagged_label['sire'] === '12345678Z', 'Extraction ciblée : balises intercalées DANS le libellé "N° SIRE" lui-même toujours reconnues');

// =====================================================================================
// 3. Classification pure (§4/§7) — jamais de mock réseau nécessaire ici : arguments synthétiques.
// =====================================================================================

gws_test_assert(gwseq_ifce_shf_extract_valid_sire(200, 'text/html; charset=utf-8', $html_jamerose, 'JAMEROSE DE FELINES') === '19369410S', 'Classification : HTTP 200 + HTML + fiche reconnue (nom) + libellé SIRE valide -> SIRE retourné');
gws_test_assert(gwseq_ifce_shf_extract_valid_sire(404, 'text/html', '<html>Not Found</html>') === '', 'Classification (§7) : HTTP 404 (ID inconnu) -> toujours "" quel que soit le corps');
gws_test_assert(gwseq_ifce_shf_extract_valid_sire(200, 'text/html', '<html><body><h1>Bienvenue sur le site de la SHF</h1></body></html>', 'UN CHEVAL INCONNU') === '', 'Classification (§7) : HTTP 200 mais page générique (ni nom, ni libellé SIRE) -> "", jamais un simple 200 considéré suffisant');
gws_test_assert(gwseq_ifce_shf_extract_valid_sire(200, 'application/json', '{"ok":true}', 'JAMEROSE') === '', 'Classification : HTTP 200 mais Content-Type/corps non HTML -> ""');
gws_test_assert(gwseq_ifce_shf_extract_valid_sire(200, 'text/html', '<div>N° SIRE : non communiqué</div>', '') === '', 'Classification (§3) : libellé présent mais valeur non conforme à proximité -> "" (jamais inventé), même reconnue via le seul libellé');
gws_test_assert(gwseq_ifce_shf_extract_valid_sire(500, 'text/html', $html_jamerose, 'JAMEROSE DE FELINES') === '', 'Classification : tout code HTTP autre que 200 (ex. 500) -> ""');
// Forme stricte propre à cet extracteur (§4) : une valeur mal formée à proximité du libellé (ex.
// 7 chiffres au lieu de 8) n'est jamais retournée, même si un texte la précède exactement comme le
// vrai libellé — vérifie que la forme "8 chiffres + 1 lettre" reste strictement appliquée ICI, sans
// jamais toucher à la validation générale (bien plus permissive) du champ `_gwseq_sire` lui-même.
gws_test_assert(gwseq_ifce_shf_extract_valid_sire(200, 'text/html', '<div>N° SIRE : 1234567A</div>', '') === '', 'Classification (§4) : une valeur ne respectant pas la forme stricte 8 chiffres + 1 lettre (ici 7 chiffres) -> ""');

// =====================================================================================
// 3bis. Dérivation UELN à partir du SIRE — correctif "UELN Selle Français" (fonctions pures, aucun
// rapport avec le réseau : gwseq_ifce_derive_ueln_from_sire()/gwseq_ifce_ueln_eligible_race_codes()).
// =====================================================================================

gws_test_assert(gwseq_ifce_ueln_eligible_race_codes() === array('SF'), 'Liste d’éligibilité UELN : volontairement restreinte au seul Selle Français (\'SF\') pour l’instant');

// Exemple réel : GOLDAME D'AUBIGNY, Selle Français, SIRE 16398915R -> UELN 25000116398915R.
gws_test_assert(gwseq_ifce_derive_ueln_from_sire('SF', '16398915R') === '25000116398915R', 'Dérivation UELN : exemple réel GOLDAME D’AUBIGNY (Selle Français) — 250001 + SIRE');
gws_test_assert(gwseq_ifce_derive_ueln_from_sire('SF', '19369410S') === '25000119369410S', 'Dérivation UELN : témoin Jamerose (Selle Français) — 250001 + SIRE');

// Stud-book non éligible (étranger/importé) : AUCUNE dérivation, même avec un SIRE français valide —
// "un cheval étranger/importé peut avoir un numéro SIRE français sans que son UELN soit basé sur
// 250001", jamais conditionné au pays de naissance du cheval lui-même (règle Selle Français
// s'applique même à un SF né à l'étranger, donc pas de raccourci "race étrangère = jamais éligible"
// généralisé au-delà de ce que la liste fermée exprime déjà).
gws_test_assert(gwseq_ifce_derive_ueln_from_sire('KWPN', '16398915R') === '', 'Dérivation UELN : stud-book non éligible (KWPN) -> "" même avec un SIRE français valide');
gws_test_assert(gwseq_ifce_derive_ueln_from_sire('OE', '50440832H') === '', 'Dérivation UELN : "Origine Étrangère" (OE) -> "" — témoin réel Teldame de la Nutria');

// Origine/stud-book incertain (race non détectée) : AUCUNE dérivation, jamais une déduction hasardeuse.
gws_test_assert(gwseq_ifce_derive_ueln_from_sire('', '16398915R') === '', 'Dérivation UELN : race non détectée (chaîne vide) -> "", jamais une déduction hasardeuse');

// SIRE absent ou mal formé : AUCUNE dérivation, même pour un stud-book éligible.
gws_test_assert(gwseq_ifce_derive_ueln_from_sire('SF', '') === '', 'Dérivation UELN : SIRE absent -> "", même Selle Français');
gws_test_assert(gwseq_ifce_derive_ueln_from_sire('SF', '1234567A') === '', 'Dérivation UELN : SIRE mal formé (7 chiffres) -> "", jamais une concaténation sur une forme invalide');
gws_test_assert(gwseq_ifce_derive_ueln_from_sire('SF', 'pas-un-sire') === '', 'Dérivation UELN : SIRE non numérique -> ""');

// =====================================================================================
// 4. Point d'entrée métier gwseq_ifce_shf_lookup_sire_by_id() — réseau intégralement mocké via le
//    filtre `gwseq_ifce_shf_fetch_override` (§10 de la demande : "mocke le réseau dans la suite
//    automatisée").
// =====================================================================================

const GWS_TEST_JAMEROSE_ID = 'lvb0qZm0QvG_PVjtz1ZBlQ';

// --- ID syntaxiquement invalide -> "" IMMÉDIATEMENT, sans jamais consulter le filtre réseau (donc
// sans jamais tenter la moindre requête) ---
remove_all_filters('gwseq_ifce_shf_fetch_override');
$network_call_count = 0;
add_filter('gwseq_ifce_shf_fetch_override', function ($default, $url) use (&$network_call_count) {
  $network_call_count++;
  return array('http_code' => 200, 'content_type' => 'text/html', 'body' => $GLOBALS['html_jamerose_for_filter'] ?? '');
});
gws_test_assert(gwseq_ifce_shf_lookup_sire_by_id('id-trop-court', 'JAMEROSE') === '', 'Point d’entrée : un ID IFCE syntaxiquement invalide (mauvaise longueur/alphabet) -> "" sans requête réseau');
gws_test_assert($network_call_count === 0, 'Point d’entrée : aucun appel réseau tenté pour un ID syntaxiquement invalide (jamais même une URL construite)');
remove_all_filters('gwseq_ifce_shf_fetch_override');

// --- Succès : le filtre simule la réponse finale déjà résolue (redirection déjà suivie) ---
$GLOBALS['html_jamerose_for_filter'] = $html_jamerose;
add_filter('gwseq_ifce_shf_fetch_override', function ($default, $url) {
  gws_test_assert(strpos($url, GWS_TEST_JAMEROSE_ID) !== false, 'Point d’entrée : l’URL interrogée contient bien l’ID IFCE (construction §2, jamais un slug fabriqué depuis une donnée GWS)');
  return array('http_code' => 200, 'content_type' => 'text/html; charset=utf-8', 'body' => $GLOBALS['html_jamerose_for_filter']);
});
gws_test_assert(gwseq_ifce_shf_lookup_sire_by_id(GWS_TEST_JAMEROSE_ID, 'JAMEROSE DE FELINES') === '19369410S', 'Point d’entrée (succès mocké) : témoin Jamerose -> 19369410S, exactement la valeur validée en conditions réelles');
remove_all_filters('gwseq_ifce_shf_fetch_override');

// --- Témoin négatif : ID fictif, le filtre simule le 404 réel observé en Local ---
add_filter('gwseq_ifce_shf_fetch_override', function ($default, $url) {
  return array('http_code' => 404, 'content_type' => 'text/html', 'body' => '<html><body>Page non trouvée</body></html>');
});
gws_test_assert(gwseq_ifce_shf_lookup_sire_by_id('AAAAAAAAAAAAAAAAAAAAAA', '') === '', 'Point d’entrée : témoin négatif (ID fictif, 404 simulé) -> "", exactement le comportement observé en Local');
remove_all_filters('gwseq_ifce_shf_fetch_override');

// --- Échec réseau (timeout/erreur) : le filtre lève une exception -> "" (jamais propagée) ---
add_filter('gwseq_ifce_shf_fetch_override', function ($default, $url) {
  throw new RuntimeException('timeout simulé');
});
$threw = false;
try {
  $result_on_timeout = gwseq_ifce_shf_lookup_sire_by_id(GWS_TEST_JAMEROSE_ID, 'JAMEROSE DE FELINES');
} catch (Throwable $e) {
  $threw = true;
  $result_on_timeout = null;
}
gws_test_assert($threw === false, '§6 : une exception réseau simulée (timeout) ne remonte JAMAIS hors de gwseq_ifce_shf_lookup_sire_by_id()');
gws_test_assert($result_on_timeout === '', '§6/§7 : timeout/erreur réseau -> "" (fail closed pour l’enrichissement uniquement, jamais bloquant pour l’import)');
remove_all_filters('gwseq_ifce_shf_fetch_override');

// --- Page générique (200 mais ni nom ni libellé SIRE) -> "" ---
add_filter('gwseq_ifce_shf_fetch_override', function ($default, $url) {
  return array('http_code' => 200, 'content_type' => 'text/html', 'body' => '<html><body><h1>SHF</h1><p>Recherche de fiches...</p></body></html>');
});
gws_test_assert(gwseq_ifce_shf_lookup_sire_by_id(GWS_TEST_JAMEROSE_ID, 'UN NOM QUI NE FIGURE PAS SUR CETTE PAGE') === '', 'Point d’entrée (§7) : réponse 200 générique, non reconnue comme une fiche -> ""');
remove_all_filters('gwseq_ifce_shf_fetch_override');

echo ($failures === 0 ? 'Tous les tests sont passés.' : "$failures test(s) en échec.") . "\n";
exit($failures === 0 ? 0 : 1);

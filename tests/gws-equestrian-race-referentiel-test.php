<?php
/**
 * Vérifie le référentiel Race / Stud-book / Appellation de `gws-equestrian` (correctif référentiel
 * — dissociation richesse technique du référentiel / simplicité de l'interface, §1-16 de la
 * demande) : accès aux entrées, résolution d'alias (dont l'exemple important SFA -> SF), recherche
 * partielle pour l'autocomplétion (code/libellé, accents/casse), sanitation d'un code brut vers le
 * code canonique exact, "Autre" comme seul filet de sécurité (jamais un repli sur un code connu mal
 * interprété, jamais absent), et gestion des récents/suggestions par utilisateur (préférence
 * propre à l'utilisateur, ne modifie jamais la donnée Cheval).
 *
 * Ne fait pas partie des paquets livrés (gws-core.zip / gws-starter.zip).
 */

$failures = 0;
function gws_test_assert($condition, $label) {
  global $failures;
  if ($condition) { echo "OK   - $label\n"; }
  else { echo "FAIL - $label\n"; $failures++; }
}

// --- Stubs WordPress minimaux (mêmes conventions que les autres tests de ce dossier) ---
function wp_unslash($value) { return is_array($value) ? array_map('wp_unslash', $value) : stripslashes((string) $value); }
// FIDÈLE au comportement réel de sanitize_key() (WordPress core) : mise en minuscules AVANT le
// filtrage des caractères — voir gws-equestrian-pedigree-logic-test.php pour le détail du bug de
// stub que cet ordre évite (codes de race en MAJUSCULES du référentiel).
function sanitize_key($value) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value)); }
function wp_parse_args($args, $defaults = array()) { return array_merge((array) $defaults, (array) $args); }
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function esc_attr_e($text, $domain = 'default') { echo esc_attr($text); }
function esc_html_e($text, $domain = 'default') { echo esc_html($text); }
function __($text, $domain = 'default') { return $text; }

// --- remove_accents() : natif WordPress, stub couvrant les caractères utilisés par les tests ---
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

// --- Préférences utilisateur (récents, §5-6 de la demande) — registre en mémoire par utilisateur ---
$GLOBALS['__gwseq_test_user_meta'] = array();
function get_user_meta($user_id, $key, $single = false) { return $GLOBALS['__gwseq_test_user_meta'][$user_id][$key] ?? ''; }
function update_user_meta($user_id, $key, $value) { $GLOBALS['__gwseq_test_user_meta'][$user_id][$key] = $value; return true; }
function get_current_user_id() { return $GLOBALS['__gwseq_test_current_user_id'] ?? 1; }

// --- Assets (enqueue/localize) — juste des registres en mémoire, jamais un vrai navigateur ---
$GLOBALS['__gwseq_enqueued'] = array();
$GLOBALS['__gwseq_test_localized'] = array();
function wp_enqueue_script($handle, ...$rest) { $GLOBALS['__gwseq_enqueued'][] = $handle; }
function wp_enqueue_style($handle, ...$rest) { $GLOBALS['__gwseq_enqueued'][] = $handle; }
function wp_script_is($handle, $status = 'enqueued') { return in_array($handle, $GLOBALS['__gwseq_enqueued'], true); }
function wp_localize_script($handle, $object_name, $data) { $GLOBALS['__gwseq_test_localized'][$handle][$object_name] = $data; }

define('ABSPATH', __DIR__ . '/');
define('GWSEQ_MODULE_URL', 'https://example.test/wp-content/plugins/gws-core/modules/gws-equestrian/');
define('GWSEQ_MODULE_VERSION', 'test');
$repo_root = dirname(__DIR__);
$module_dir = $repo_root . '/wp-content/plugins/gws-core/modules/gws-equestrian/';
require $module_dir . 'includes/race-referentiel.php';

// =====================================================================================
// Référentiel : nombre d'entrées, structure de chaque entrée (§13 : code canonique STRUCTURÉ,
// jamais un libellé), distinction technique race/appellation conservée
// =====================================================================================

$all_entries = gwseq_race_referentiel_entries();
gws_test_assert(count($all_entries) === 154, 'Référentiel : 154 entrées au total, issues du fichier XLSX source fourni avec la demande');
gws_test_assert(count(array_filter($all_entries, function ($e) { return $e['type'] === 'appellation'; })) === 3, 'Référentiel : exactement 3 appellations (OC, ONC, OE), le reste étant des races/stud-books');
foreach ($all_entries as $entry) {
  gws_test_assert(
    array_key_exists('code', $entry) && array_key_exists('ifce', $entry) && array_key_exists('gws', $entry) && array_key_exists('type', $entry) && array_key_exists('alias', $entry),
    "Référentiel : l'entrée \"{$entry['code']}\" porte bien tous les champs attendus (code, ifce, gws, type, alias)"
  );
  gws_test_assert(in_array($entry['type'], array('race', 'appellation'), true), "Référentiel : le type de \"{$entry['code']}\" est bien 'race' ou 'appellation', jamais une autre valeur");
  gws_test_assert(is_array($entry['alias']), "Référentiel : les alias de \"{$entry['code']}\" sont bien un tableau (éventuellement vide)");
}

// =====================================================================================
// gwseq_race_referentiel_get() : lecture par code canonique exact (insensible à la casse)
// =====================================================================================

gws_test_assert(gwseq_race_referentiel_get('SF')['gws'] === 'Selle Français', 'get() : code exact "SF" -> entrée Selle Français');
gws_test_assert(gwseq_race_referentiel_get('sf')['gws'] === 'Selle Français', 'get() : recherche insensible à la casse ("sf" minuscule)');
gws_test_assert(gwseq_race_referentiel_get('CODE-INVENTE-INEXISTANT') === null, 'get() : un code inconnu renvoie null, jamais une entrée fabriquée');
gws_test_assert(gwseq_race_referentiel_get('') === null, 'get() : une chaîne vide renvoie null');

// =====================================================================================
// gwseq_race_referentiel_resolve_alias() : résolution exacte (post-normalisation) — code IFCE,
// libellé IFCE, libellé GWS, alias historique/import. Couverture minimale exigée par la demande
// (§14) : SF, SFA->SF (exemple important), OLD, HOLST, KWPN, WESTF, Z, OC, ONC.
// =====================================================================================

gws_test_assert(gwseq_race_referentiel_resolve_alias('SF') === 'SF', 'resolve_alias() : "SF" (code exact) -> "SF"');
gws_test_assert(gwseq_race_referentiel_resolve_alias('Selle Français') === 'SF', 'resolve_alias() : "Selle Français" (libellé GWS/IFCE) -> "SF"');
gws_test_assert(gwseq_race_referentiel_resolve_alias('SFA') === 'SF', 'resolve_alias() — EXEMPLE IMPORTANT de la demande (§2) : l’alias historique/import "SFA" est résolu au code canonique "SF", jamais perdu, jamais rangé dans "Autre"');
gws_test_assert(gwseq_race_referentiel_resolve_alias('sfa') === 'SF', 'resolve_alias() : l’alias "SFA" est reconnu quelle que soit la casse saisie ("sfa")');
gws_test_assert(gwseq_race_referentiel_resolve_alias('OLD') === 'OLD', 'resolve_alias() : "OLD" -> "OLD" (Oldenburg)');
gws_test_assert(gwseq_race_referentiel_resolve_alias('Oldenburg') === 'OLD', 'resolve_alias() : le libellé complet "Oldenburg" résout également vers le code "OLD"');
gws_test_assert(gwseq_race_referentiel_resolve_alias('HOLST') === 'HOLST', 'resolve_alias() : "HOLST" -> "HOLST" (Holsteiner)');
gws_test_assert(gwseq_race_referentiel_resolve_alias('Holsteiner') === 'HOLST', 'resolve_alias() : le libellé GWS "Holsteiner" résout également vers le code "HOLST"');
gws_test_assert(gwseq_race_referentiel_resolve_alias('KWPN') === 'KWPN', 'resolve_alias() : "KWPN" -> "KWPN"');
gws_test_assert(gwseq_race_referentiel_resolve_alias('WESTF') === 'WESTF', 'resolve_alias() : "WESTF" -> "WESTF" (Westphalien)');
gws_test_assert(gwseq_race_referentiel_resolve_alias('Westphalien') === 'WESTF', 'resolve_alias() : le libellé GWS "Westphalien" résout également vers le code "WESTF"');
gws_test_assert(gwseq_race_referentiel_resolve_alias('Z') === 'Z', 'resolve_alias() : "Z" -> "Z" (Zangersheide)');
gws_test_assert(gwseq_race_referentiel_resolve_alias('Zangersheide') === 'Z', 'resolve_alias() : le libellé complet "Zangersheide" résout également vers le code "Z"');
gws_test_assert(gwseq_race_referentiel_resolve_alias('OC') === 'OC', 'resolve_alias() : "OC" -> "OC" (Origines Constatées — appellation, même mécanisme que les races)');
gws_test_assert(gwseq_race_referentiel_resolve_alias('Origines Constatées') === 'OC', 'resolve_alias() : le libellé complet "Origines Constatées" résout également vers "OC"');
gws_test_assert(gwseq_race_referentiel_resolve_alias('ONC') === 'ONC', 'resolve_alias() : "ONC" -> "ONC" (Origines Non Constatées)');
gws_test_assert(gwseq_race_referentiel_resolve_alias('ONCS') === 'ONC', 'resolve_alias() : l’alias "ONCS" résout également vers "ONC"');
gws_test_assert(gwseq_race_referentiel_resolve_alias('OE') === 'OE', 'resolve_alias() : "OE" -> "OE" (Origine Étrangère)');
gws_test_assert(gwseq_race_referentiel_resolve_alias('OES') === 'OE', 'resolve_alias() : l’alias "OES" résout également vers "OE"');
gws_test_assert(gwseq_race_referentiel_resolve_alias('CODE-INVENTE-INEXISTANT') === '', 'resolve_alias() : un texte ne correspondant à rien du référentiel renvoie une chaîne vide (jamais un code deviné)');
gws_test_assert(gwseq_race_referentiel_type('OC') === 'appellation' && gwseq_race_referentiel_type('SF') === 'race', 'Distinction technique race/appellation (§3) : conservée et accessible, sans jamais créer deux champs UI séparés');

// --- Aucun code CONNU n'est jamais transformé en "Autre" (§2/§14) — vérifié sur TOUT le
// référentiel, pas seulement les quelques exemples ci-dessus ---
foreach ($all_entries as $entry) {
  gws_test_assert(gwseq_race_referentiel_resolve_alias($entry['code']) === $entry['code'], "Aucun repli \"Autre\" pour un code connu : \"{$entry['code']}\" résout vers lui-même");
  foreach ($entry['alias'] as $alias) {
    gws_test_assert(gwseq_race_referentiel_resolve_alias($alias) === $entry['code'], "Aucun repli \"Autre\" pour un alias connu : \"$alias\" résout vers \"{$entry['code']}\", jamais vers \"autre\"");
  }
}

// =====================================================================================
// Normalisation croisée obligatoire (correctif runtime, cas UNTOUCHABLE 27) : un même
// race/stud-book/appellation ne doit JAMAIS produire deux valeurs stockées différentes selon qu'il
// est rencontré dans l'IDENTITÉ (libellé long/officiel IFCE, ex. "Kon. Warm Paard Nederland") ou
// dans le PEDIGREE (code court, ex. "KWPN") — les deux chemins appellent la même fonction de
// résolution canonique (gwseq_match_race_to_canonical_code(), délègue à
// gwseq_race_referentiel_resolve_alias()), jamais deux implémentations divergentes.
// =====================================================================================

$cross_consistency_cases = array(
  'KWPN' => array('KWPN', 'Kon. Warm Paard Nederland', 'Koninklijke Vereniging Warmbloed Paardenstamboek Nederland'),
  'BWP' => array('BWP', 'Belgian Warmblood', 'Belgisch Warmbloedpaard'),
  'HOLST' => array('HOLST', 'Holsteiner Warmblut', 'Holsteiner'),
  'OLD' => array('OLD', 'Oldenburg'),
  'HAN' => array('HAN', 'Hannoveraner', 'Hanovrien'),
  'SF' => array('SF', 'SFA', 'Selle Français', 'Selle Francais Section A'),
  'OE' => array('OE', 'OES', 'Origine étrangère selle'),
);
foreach ($cross_consistency_cases as $expected_code => $variants) {
  $resolved = array_map('gwseq_race_referentiel_resolve_alias', $variants);
  gws_test_assert(
    count(array_unique($resolved)) === 1 && $resolved[0] === $expected_code,
    "Normalisation croisée : toutes les variantes de \"$expected_code\" (" . implode(', ', $variants) . ") résolvent vers EXACTEMENT le même code canonique, jamais deux valeurs différentes stockées pour la même race/stud-book"
  );
}

// --- Vérification explicite du cas exact rapporté (UNTOUCHABLE 27) : le libellé long rencontré
// dans l'IDENTITÉ et le code court rencontré dans le PEDIGREE produisent la MÊME valeur stockée ---
$identity_side = gwseq_race_referentiel_resolve_alias('Kon. Warm Paard Nederland');
$pedigree_side = gwseq_race_referentiel_resolve_alias('KWPN');
gws_test_assert($identity_side === 'KWPN' && $pedigree_side === 'KWPN' && $identity_side === $pedigree_side, 'Cas exact rapporté (UNTOUCHABLE 27) : le libellé IFCE long de l’identité ("Kon. Warm Paard Nederland") et le code court du pedigree ("KWPN") produisent EXACTEMENT la même valeur stockée');

// --- gwseq_match_race_to_canonical_code() (cheval-pedigree.php, appelée par l'identité ET le
// pedigree via ifce-import-parser.php) délègue bien à CETTE MÊME fonction, jamais une seconde
// implémentation divergente — vérifié directement sur le code source (léger : ce fichier de test
// n'a pas besoin de charger toute la chaîne de dépendances de cheval-pedigree.php pour une simple
// délégation d'une ligne) ---
$cheval_pedigree_source_for_delegation = file_get_contents($module_dir . 'includes/cheval-pedigree.php');
gws_test_assert(
  preg_match('/function\s+gwseq_match_race_to_canonical_code\s*\([^)]*\)\s*\{\s*return\s+gwseq_race_referentiel_resolve_alias\s*\(/', $cheval_pedigree_source_for_delegation) === 1,
  'gwseq_match_race_to_canonical_code() (utilisée à l’identique par l’identité et le pedigree dans ifce-import-parser.php) délègue bien intégralement à gwseq_race_referentiel_resolve_alias(), jamais une seconde résolution divergente'
);

// =====================================================================================
// gwseq_sanitize_race_referentiel_code() : sanitation d'un champ "race" brut ($_POST-shaped),
// utilisée à l'IDENTIQUE par l'identité du cheval et les ascendants externes (§8 : même composant,
// même référentiel, aucune divergence). "Autre" toujours disponible comme filet de sécurité.
// =====================================================================================

gws_test_assert(gwseq_sanitize_race_referentiel_code('sf') === 'SF', 'sanitize_race_referentiel_code() : code minuscule "sf" -> code canonique "SF"');
gws_test_assert(gwseq_sanitize_race_referentiel_code('SF') === 'SF', 'sanitize_race_referentiel_code() : code déjà canonique "SF" -> inchangé');
gws_test_assert(gwseq_sanitize_race_referentiel_code('autre') === 'autre', 'sanitize_race_referentiel_code() : le sentinel "autre" est toujours disponible, quel que soit l’état du référentiel');
gws_test_assert(gwseq_sanitize_race_referentiel_code('') === '', 'sanitize_race_referentiel_code() : une valeur vide reste vide (champ non renseigné)');
gws_test_assert(gwseq_sanitize_race_referentiel_code('code-invente-inexistant') === '', 'sanitize_race_referentiel_code() : un code inconnu n’est JAMAIS transformé en "autre" automatiquement — il est simplement rejeté (vide), l’appelant applique alors son propre repli explicite sur "Autre"');

// --- Même résultat que l'ascendant externe : cheval-fields.php (identité) et cheval-pedigree.php
// (ascendants externes) délèguent tous deux à CETTE MÊME fonction, jamais un second mécanisme
// divergent (§8/§13) — vérifié directement sur le code source des deux fichiers ---
$cheval_fields_source_ref = file_get_contents($module_dir . 'includes/cheval-fields.php');
$cheval_pedigree_source_ref = file_get_contents($module_dir . 'includes/cheval-pedigree.php');
gws_test_assert(strpos($cheval_fields_source_ref, 'gwseq_sanitize_race_referentiel_code(') !== false, 'Cohérence identité/ascendant : l’identité du cheval (cheval-fields.php) délègue bien à gwseq_sanitize_race_referentiel_code(), jamais une sanitation divergente');
gws_test_assert(strpos($cheval_pedigree_source_ref, 'gwseq_sanitize_race_referentiel_code(') !== false, 'Cohérence identité/ascendant : les ascendants externes (cheval-pedigree.php) délèguent bien à la MÊME gwseq_sanitize_race_referentiel_code(), jamais une seconde implémentation');

// =====================================================================================
// gwseq_race_referentiel_search() : recherche PARTIELLE pour l'autocomplétion (§4) — sur le code,
// le libellé IFCE, le libellé GWS et les alias, accents/casse ignorés, préfixe classé avant
// correspondance partielle ailleurs dans le champ.
// =====================================================================================

// --- Recherche partielle sur le CODE ---
$results_sf = gwseq_race_referentiel_search('sf');
gws_test_assert(in_array('SF', array_column($results_sf, 'code'), true), 'search() : la requête "sf" (partielle sur le code) trouve bien l’entrée "SF" (Selle Français)');
$results_kwp = gwseq_race_referentiel_search('kwp');
gws_test_assert($results_kwp[0]['code'] === 'KWPN', 'search() : la requête partielle "kwp" trouve "KWPN" en tête (préfixe du code)');
$results_old = gwseq_race_referentiel_search('old');
gws_test_assert($results_old[0]['code'] === 'OLD', 'search() : la requête partielle "old" trouve "OLD" (Oldenburg) en tête');
$results_zang = gwseq_race_referentiel_search('zang');
gws_test_assert(in_array('Z', array_column($results_zang, 'code'), true), 'search() : la requête partielle "zang" trouve l’entrée "Z" (Zangersheide, préfixe du libellé)');

// --- Recherche partielle sur le LIBELLÉ (l'utilisateur ne connaît pas forcément le code IFCE) ---
$results_sel = gwseq_race_referentiel_search('sel');
gws_test_assert(in_array('SF', array_column($results_sel, 'code'), true), 'search() : la requête "sel" (partielle sur le libellé "Selle Français") trouve bien "SF" — un utilisateur non familier des codes IFCE peut taper le libellé');
$results_conn = gwseq_race_referentiel_search('conn');
gws_test_assert(in_array('CO', array_column($results_conn, 'code'), true) && in_array('COPB', array_column($results_conn, 'code'), true), 'search() : la requête "conn" trouve à la fois "Connemara" (CO) et "Connemara Part-Bred" (COPB), exemple exact de la demande');
$results_oc = gwseq_race_referentiel_search('oc');
gws_test_assert(in_array('OC', array_column($results_oc, 'code'), true), 'search() : la requête "oc" trouve bien l’appellation "Origines Constatées" (OC) — intégrée au MÊME moteur de recherche que les races (§3)');

// --- Accents/casse ignorés (aussi bien côté requête que côté champ recherché) ---
$results_accented_query = gwseq_race_referentiel_search('selle francais'); // sans accent ni tiret
gws_test_assert(in_array('SF', array_column($results_accented_query, 'code'), true), 'search() : une requête SANS accent ("selle francais") trouve bien "Selle Français" (champ accentué)');
$results_upper_query = gwseq_race_referentiel_search('SF');
gws_test_assert(in_array('SF', array_column($results_upper_query, 'code'), true), 'search() : la casse de la requête n’a aucune importance ("SF" majuscule)');

// --- "Autre" reste toujours disponible comme filet de sécurité (§7), même si une recherche ne
// trouve rien du tout dans le référentiel ---
$results_nothing = gwseq_race_referentiel_search('zzzzzzzzzzz-aucune-correspondance');
gws_test_assert($results_nothing === array(), 'search() : une requête ne correspondant à rien renvoie un tableau vide, jamais une erreur');
gws_test_assert(gwseq_race_referentiel_get('autre') === null && gwseq_sanitize_race_referentiel_code('autre') === 'autre', '"Autre" (§7) : absent du référentiel en tant qu’entrée réelle, mais toujours accepté comme sentinel de sanitation — le filet de sécurité reste disponible même quand la recherche ne trouve rien');

// --- Limite de résultats respectée ---
$results_limited = gwseq_race_referentiel_search('a', 3);
gws_test_assert(count($results_limited) === 3, 'search() : le paramètre $limit borne bien le nombre de résultats retournés');

// =====================================================================================
// Récents / suggestions par utilisateur (§5-6) : préférence PROPRE à l'utilisateur (user meta),
// ne modifie JAMAIS la donnée Cheval ; repli neutre (jamais un profil métier rigide CSO/dressage/
// poney) tant qu'aucun historique n'existe.
// =====================================================================================

gws_test_assert(gwseq_race_referentiel_recent_codes(42) === array(), 'Récents : un utilisateur sans historique n’a aucun récent');
$default_suggestions = gwseq_race_referentiel_suggestions_for_user(42);
gws_test_assert(!empty($default_suggestions), 'Suggestions (repli neutre) : un utilisateur sans historique reçoit malgré tout des suggestions (repli sur le champ "usage" du référentiel source)');

gwseq_race_referentiel_record_recent_code(42, 'sf');
gwseq_race_referentiel_record_recent_code(42, 'kwpn');
gwseq_race_referentiel_record_recent_code(42, 'old');
gws_test_assert(gwseq_race_referentiel_recent_codes(42) === array('OLD', 'KWPN', 'SF'), 'Récents : les codes enregistrés sont bien stockés au code CANONIQUE, les plus récents en tête');

gwseq_race_referentiel_record_recent_code(42, 'sf'); // ré-enregistrement : doit remonter en tête, jamais dupliqué
gws_test_assert(gwseq_race_referentiel_recent_codes(42) === array('SF', 'OLD', 'KWPN'), 'Récents : un code déjà présent remonte en tête sans jamais être dupliqué (idempotent)');

gwseq_race_referentiel_record_recent_code(42, 'code-invente-inexistant');
gwseq_race_referentiel_record_recent_code(42, 'autre');
gws_test_assert(gwseq_race_referentiel_recent_codes(42) === array('SF', 'OLD', 'KWPN'), 'Récents : un code inconnu ou le sentinel "autre" ne sont JAMAIS enregistrés comme récent (ce ne sont pas de vraies valeurs du référentiel)');

$suggestions_with_history = gwseq_race_referentiel_suggestions_for_user(42);
gws_test_assert(
  array_column($suggestions_with_history, 'code') === array('SF', 'OLD', 'KWPN'),
  'Suggestions : dès qu’un utilisateur a au moins un récent, SES récents sont utilisés, jamais mélangés avec le repli neutre générique (un éleveur spécialisé retrouve directement ses valeurs habituelles)'
);

gws_test_assert(gwseq_race_referentiel_recent_codes(999) === array(), 'Récents : les préférences sont bien PROPRES à chaque utilisateur (aucun mélange entre utilisateurs différents)');

// --- Aucune modification de la donnée Cheval par ce mécanisme (§6) : les récents vivent
// exclusivement en user meta, jamais en post meta — vérifié directement sur le code source ---
$referentiel_source = file_get_contents($module_dir . 'includes/race-referentiel.php');
gws_test_assert(strpos($referentiel_source, 'update_post_meta') === false, 'Récents/suggestions : le fichier référentiel n’appelle jamais update_post_meta() — les préférences utilisateur ne modifient jamais la donnée Cheval');

// --- Enregistrement récursif depuis un arbre d'ascendants externes déjà sanitisé (glue de
// sauvegarde du pedigree, §5-6) ---
$GLOBALS['__gwseq_test_user_meta'] = array();
$external_tree = array(
  'name' => 'Kannan', 'race' => 'KWPN', 'race_autre' => '', 'annee_naissance' => '',
  'father' => array('name' => 'Voltaire', 'race' => 'HAN', 'race_autre' => '', 'annee_naissance' => '', 'father' => null, 'mother' => null),
  'mother' => array('name' => 'Cemeta', 'race' => 'autre', 'race_autre' => 'Race locale', 'annee_naissance' => '', 'father' => null, 'mother' => null),
);
gwseq_race_referentiel_record_recent_codes_from_external_tree($external_tree, 7);
gws_test_assert(
  in_array('KWPN', gwseq_race_referentiel_recent_codes(7), true) && in_array('HAN', gwseq_race_referentiel_recent_codes(7), true),
  'Récents (arbre externe) : chaque code de race valide rencontré, à n’importe quelle génération, est bien enregistré comme récent'
);
gws_test_assert(!in_array('autre', gwseq_race_referentiel_recent_codes(7), true), 'Récents (arbre externe) : le sentinel "autre" (Cemeta) n’est jamais enregistré comme récent');

echo ($failures === 0 ? 'Tous les tests sont passés.' : "$failures test(s) en échec.") . "\n";
exit($failures === 0 ? 0 : 1);

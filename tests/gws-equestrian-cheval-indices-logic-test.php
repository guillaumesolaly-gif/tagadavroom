<?php
/**
 * Vérifie les indices sportifs (ISO/ICC/IDR) et génétiques (BSO/BCC/BDR) de la fiche Cheval
 * (Étape 6, §2-3 de la demande) : valeur et année/CD stockées séparément (jamais une chaîne
 * unique), une seule valeur par indice (aucun historique implicite), indépendance totale entre les
 * six indices, sanitation numérique stricte (signe et décimales préservés pour les indices
 * génétiques), et chemin programmatique sans $_POST ni nonce (même méthodologie que le pedigree,
 * Étape 5).
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
function wp_unslash($value) { return is_array($value) ? array_map('wp_unslash', $value) : $value; }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function sanitize_textarea_field($value) { return trim((string) $value); }
function sanitize_key($value) { return strtolower(preg_replace('/[^a-z0-9_\-]/', '', (string) $value)); }
function esc_url_raw($value) { $value = trim((string) $value); return $value === '' ? '' : $value; }
function absint($value) { return abs((int) $value); }
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function selected($a, $b) { return $a == $b ? ' selected' : ''; }
function checked($a, $b = true) { return $a == $b ? ' checked' : ''; }
function wp_nonce_field($action, $field) { echo '<input type="hidden" name="' . esc_attr($field) . '" value="stub-nonce">'; }

$GLOBALS['__gwseq_test_domains_used'] = array();
function __($text, $domain = 'default') { $GLOBALS['__gwseq_test_domains_used'][] = $domain; return $text; }
function esc_html__($text, $domain = 'default') { $GLOBALS['__gwseq_test_domains_used'][] = $domain; return esc_html($text); }
function esc_attr__($text, $domain = 'default') { $GLOBALS['__gwseq_test_domains_used'][] = $domain; return esc_attr($text); }
function esc_html_e($text, $domain = 'default') { echo esc_html__($text, $domain); }
function esc_attr_e($text, $domain = 'default') { echo esc_attr__($text, $domain); }

$GLOBALS['__gwseq_test_registered_meta'] = array();
function register_post_meta($object_type, $meta_key, $args = array()) { $GLOBALS['__gwseq_test_registered_meta'][$meta_key] = $args; }
function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {}
function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {}
function add_meta_box($id, $title, $callback, $post_type = null, $context = 'advanced', $priority = 'default') {}

$GLOBALS['__gwseq_test_meta'] = array();
function update_post_meta($post_id, $key, $value) { $GLOBALS['__gwseq_test_meta'][$post_id][$key] = $value; return true; }
function get_post_meta($post_id, $key, $single = false) { return $GLOBALS['__gwseq_test_meta'][$post_id][$key] ?? ''; }

$GLOBALS['__gwseq_test_security'] = array('nonce_valid' => true, 'can_edit' => true, 'is_revision' => false);
function wp_verify_nonce($nonce, $action) { return $GLOBALS['__gwseq_test_security']['nonce_valid']; }
function current_user_can($cap, $post_id = null) { return $GLOBALS['__gwseq_test_security']['can_edit']; }
function wp_is_post_revision($post_id) { return $GLOBALS['__gwseq_test_security']['is_revision']; }

define('ABSPATH', __DIR__ . '/');
const GWSEQ_CPT_CHEVAL = 'gwseq_cheval';
$repo_root = dirname(__DIR__);
$module_dir = $repo_root . '/wp-content/plugins/gws-core/modules/gws-equestrian/';
require $repo_root . '/wp-content/plugins/gws-core/includes/fields.php';
require $module_dir . 'includes/cheval-fields.php';
require $module_dir . 'includes/cheval-indices.php';

$cheval_indices_source = file_get_contents($module_dir . 'includes/cheval-indices.php');

// =====================================================================================
// Indices sportifs (ISO, ICC, IDR) — §2 de la demande
// =====================================================================================

gws_test_make_post_stub_meta_reset();
function gws_test_make_post_stub_meta_reset() { $GLOBALS['__gwseq_test_meta'] = array(); }

// --- ISO : valeur + année, exemple exact de la demande (ISO 142 (2025)) ---
$result_iso = gwseq_set_cheval_sport_indice(10, 'iso', array('valeur' => '142', 'annee' => '2025'));
gws_test_assert($result_iso === true, 'ISO : l’enregistrement réussit');
$iso = gwseq_get_cheval_sport_indice(10, 'iso');
gws_test_assert($iso['valeur'] === 142 && $iso['annee'] === 2025, 'ISO : valeur et année exactes, chacune un entier');
gws_test_assert(gwseq_cheval_sport_indice_label($iso['valeur'], $iso['annee']) === '142 (2025)', 'ISO : libellé conforme à l’exemple exact de la demande');

// --- ICC : valeur + année, indépendant de l'ISO déjà enregistré sur le même cheval ---
gwseq_set_cheval_sport_indice(10, 'icc', array('valeur' => '118', 'annee' => '2024'));
$icc = gwseq_get_cheval_sport_indice(10, 'icc');
gws_test_assert($icc['valeur'] === 118 && $icc['annee'] === 2024, 'ICC : valeur et année exactes');
gws_test_assert(gwseq_get_cheval_sport_indice(10, 'iso')['valeur'] === 142, 'Indépendance : enregistrer l’ICC ne modifie jamais l’ISO déjà enregistré sur le même cheval');

// --- IDR : valeur + année, indépendant des deux précédents ---
gwseq_set_cheval_sport_indice(10, 'idr', array('valeur' => '135', 'annee' => '2023'));
$idr = gwseq_get_cheval_sport_indice(10, 'idr');
gws_test_assert($idr['valeur'] === 135 && $idr['annee'] === 2023, 'IDR : valeur et année exactes');
gws_test_assert(gwseq_get_cheval_sport_indice(10, 'iso')['valeur'] === 142 && gwseq_get_cheval_sport_indice(10, 'icc')['valeur'] === 118, 'Indépendance : les trois indices sportifs coexistent sans interférence');

// --- Champs indépendants : valeur sans année, année sans valeur ---
gwseq_set_cheval_sport_indice(11, 'iso', array('valeur' => '150'));
$iso_sans_annee = gwseq_get_cheval_sport_indice(11, 'iso');
gws_test_assert($iso_sans_annee['valeur'] === 150 && $iso_sans_annee['annee'] === '', 'Champs indépendants : une valeur sans année est acceptée, l’année reste vide');

gwseq_set_cheval_sport_indice(12, 'iso', array('annee' => '2022'));
$iso_sans_valeur = gwseq_get_cheval_sport_indice(12, 'iso');
gws_test_assert($iso_sans_valeur['annee'] === 2022 && $iso_sans_valeur['valeur'] === '', 'Champs indépendants : une année sans valeur est acceptée, la valeur reste vide');

// --- Valeurs numériques correctement sanitisées ---
gws_test_assert(gwseq_sanitize_cheval_sport_indice_valeur('abc') === '', 'Sanitation : une valeur non numérique est rejetée, jamais une erreur');
gws_test_assert(gwseq_sanitize_cheval_sport_indice_valeur('142.6') === 143, 'Sanitation : une valeur décimale est arrondie à l’entier le plus proche');
gws_test_assert(gwseq_sanitize_cheval_sport_indice_valeur('') === '', 'Sanitation : une valeur vide reste vide (champ facultatif)');
gws_test_assert(gwseq_sanitize_cheval_indice_annee('abc') === '', 'Sanitation : une année non numérique est rejetée');
gws_test_assert(gwseq_sanitize_cheval_indice_annee('1899') === '', 'Sanitation : une année en dessous de la borne minimale (1900) est rejetée');
gws_test_assert(gwseq_sanitize_cheval_indice_annee((string) ((int) gmdate('Y') + 1)) === '', 'Sanitation : contrairement à l’année de naissance, un indice ne peut jamais concerner une année future (rétrospectif par nature)');
gws_test_assert(gwseq_sanitize_cheval_indice_annee((string) gmdate('Y')) === (int) gmdate('Y'), 'Sanitation : l’année en cours reste acceptée pour un indice');

// --- Aucune création d'historique implicite : un second enregistrement REMPLACE, ne s'ajoute pas ---
gwseq_set_cheval_sport_indice(10, 'iso', array('valeur' => '160', 'annee' => '2026'));
$iso_remplace = gwseq_get_cheval_sport_indice(10, 'iso');
gws_test_assert($iso_remplace['valeur'] === 160 && $iso_remplace['annee'] === 2026, 'Aucun historique : la nouvelle valeur ISO remplace bien l’ancienne (142/2025), une seule valeur reste stockée');
gws_test_assert(!is_array(get_post_meta(10, '_gwseq_iso_valeur', true)), 'Aucun historique : la meta ISO valeur est un scalaire unique, jamais un tableau de valeurs successives');

// --- Indice/rôle invalide : refusé proprement, jamais d'erreur ---
gws_test_assert(gwseq_set_cheval_sport_indice(10, 'inconnu', array('valeur' => '100')) === false, 'Robustesse : une clé d’indice sportif inconnue est refusée');
gws_test_assert(gwseq_set_cheval_sport_indice(0, 'iso', array('valeur' => '100')) === false, 'Robustesse : un cheval_id invalide (0) est refusé');
gws_test_assert(gwseq_get_cheval_sport_indice(10, 'inconnu') === array('valeur' => '', 'annee' => ''), 'Robustesse : la lecture d’une clé d’indice sportif inconnue renvoie des valeurs vides, jamais une erreur');

// =====================================================================================
// Indices génétiques (BSO, BCC, BDR) — §3 de la demande
// =====================================================================================

// --- BSO : valeur positive + CD, exemple exact de la demande (BSO +12 (0,90)) ---
gwseq_set_cheval_genetic_indice(20, 'bso', array('valeur' => '12', 'cd' => '0.90'));
$bso = gwseq_get_cheval_genetic_indice(20, 'bso');
gws_test_assert($bso['valeur'] === 12.0 && $bso['cd'] === 0.9, 'BSO : valeur positive et CD décimal exacts, stockés séparément');
gws_test_assert(gwseq_cheval_genetic_indice_label($bso['valeur'], $bso['cd']) === '+12 (0.90)', 'BSO : le libellé ajoute le signe "+" explicite à l’affichage et présente le CD à deux décimales (jamais dans la donnée stockée elle-même)');
gws_test_assert(is_float(get_post_meta(20, '_gwseq_bso_valeur', true)), 'BSO : la valeur stockée est bien un nombre, jamais une chaîne formatée comme "+12"');

// --- Valeur négative : signe conservé ---
gwseq_set_cheval_genetic_indice(21, 'bso', array('valeur' => '-8', 'cd' => '0.85'));
$bso_negatif = gwseq_get_cheval_genetic_indice(21, 'bso');
gws_test_assert($bso_negatif['valeur'] === -8.0, 'BSO : une valeur négative conserve son signe (-8), jamais transformée en positif');
gws_test_assert(gwseq_cheval_genetic_indice_label($bso_negatif['valeur'], $bso_negatif['cd']) === '-8 (0.85)', 'BSO : le libellé d’une valeur négative garde le signe "-" natif, sans "+"');

// --- BCC : indépendant du BSO sur le même cheval ---
gwseq_set_cheval_genetic_indice(20, 'bcc', array('valeur' => '5', 'cd' => '0.75'));
gws_test_assert(gwseq_get_cheval_genetic_indice(20, 'bcc') === array('valeur' => 5.0, 'cd' => 0.75), 'BCC : valeur et CD exacts');
gws_test_assert(gwseq_get_cheval_genetic_indice(20, 'bso')['valeur'] === 12.0, 'Indépendance : enregistrer le BCC ne modifie jamais le BSO déjà enregistré sur le même cheval');

// --- BDR : indépendant des deux précédents ---
gwseq_set_cheval_genetic_indice(20, 'bdr', array('valeur' => '-3.5', 'cd' => '0.60'));
gws_test_assert(gwseq_get_cheval_genetic_indice(20, 'bdr') === array('valeur' => -3.5, 'cd' => 0.6), 'BDR : coefficient décimal et valeur négative décimale exacts');

// --- Coefficient décimal préservé, jamais tronqué ---
gwseq_set_cheval_genetic_indice(22, 'bso', array('valeur' => '20', 'cd' => '0.987'));
gws_test_assert(gwseq_get_cheval_genetic_indice(22, 'bso')['cd'] === 0.987, 'Sanitation : un coefficient décimal à 3 chiffres est conservé exactement, jamais arrondi');

// =====================================================================================
// Ajustement UX post-recette — présentation du CD à deux décimales (§1 de la demande) : le
// STOCKAGE reste un nombre, seule la PRÉSENTATION (formulaire admin, libellé) est affectée.
// =====================================================================================

gws_test_assert(gwseq_format_cheval_indice_cd(0.9) === '0.90', 'Présentation CD : 0.9 est présenté "0.90" (deux décimales), jamais "0.9"');
gws_test_assert(gwseq_format_cheval_indice_cd(0.8) === '0.80', 'Présentation CD : 0.8 est présenté "0.80"');
gws_test_assert(gwseq_format_cheval_indice_cd(0.75) === '0.75', 'Présentation CD : 0.75 (déjà deux décimales) reste "0.75"');
gws_test_assert(gwseq_format_cheval_indice_cd(0.987) === '0.99', 'Présentation CD : une valeur à 3 décimales est arrondie à 2 décimales pour l’AFFICHAGE uniquement');
gws_test_assert(gwseq_format_cheval_indice_cd('') === '', 'Présentation CD : une valeur vide reste vide, jamais "0.00" inventé');
gws_test_assert(gwseq_format_cheval_indice_cd(-0.6) === '-0.60', 'Présentation CD : un coefficient négatif reste correctement formaté');

// --- Le STOCKAGE réel n'est jamais affecté par cette présentation : relire la valeur brute après
// arrondi d'affichage donne toujours le nombre exact enregistré (0.987, pas 0.99) ---
gws_test_assert(gwseq_get_cheval_genetic_indice(22, 'bso')['cd'] === 0.987, 'Présentation CD : le stockage reste exact (0.987) même si son affichage est arrondi à "0.99" — jamais de perte de précision en base');

// --- Le libellé public de l'indice génétique respecte la même précision (exemple exact de la
// demande : BSO +12 (0.90) avant localisation éventuelle) ---
gws_test_assert(gwseq_cheval_genetic_indice_label(12, 0.9) === '+12 (0.90)', 'Libellé génétique : exemple exact de la demande, CD présenté à deux décimales avant toute localisation');
gws_test_assert(gwseq_cheval_genetic_indice_label(-8, 0.8) === '-8 (0.80)', 'Libellé génétique : deux décimales également pour une valeur négative');

// --- Rendu admin : le champ CD affiche bien la valeur formatée à deux décimales, avec un pas de
// saisie (step) cohérent, tout en conservant un séparateur décimal point (aucune conversion
// virgule/point ajoutée à ce stade) ---
gwseq_set_cheval_genetic_indice(52, 'bso', array('valeur' => '12', 'cd' => '0.9'));
$post_stub_cd = (object) array('ID' => 52);
ob_start();
gwseq_render_cheval_indices_box($post_stub_cd);
$cd_render_html = ob_get_clean();
gws_test_assert(strpos($cd_render_html, 'name="_gwseq_bso[cd]" value="0.90"') !== false, 'Rendu admin : le champ CD affiche "0.90" (deux décimales) alors que "0.9" a été enregistré, sans que le stockage n’ait changé');
gws_test_assert(strpos($cd_render_html, 'step="0.01"') !== false, 'Rendu admin : le champ CD utilise un pas de saisie de 0.01, cohérent avec sa présentation à deux décimales');

// --- Absence d'année pour les indices génétiques : aucune meta "_annee" n'existe pour BSO/BCC/BDR ---
foreach (array('bso', 'bcc', 'bdr') as $genetic_key) {
  gws_test_assert(get_post_meta(20, '_gwseq_' . $genetic_key . '_annee', true) === '', "Absence d'année : aucune donnée d'année stockée pour $genetic_key (meta jamais enregistrée)");
}
foreach (array('_gwseq_bso_annee', '_gwseq_bcc_annee', '_gwseq_bdr_annee') as $forbidden_meta_key) {
  gws_test_assert(strpos($cheval_indices_source, "'" . $forbidden_meta_key . "'") === false, "Absence d'année (vérification source) : la meta littérale \"$forbidden_meta_key\" n'existe nulle part dans le fichier");
}

// --- Composants stockés séparément : deux meta distinctes, jamais une chaîne combinée ---
gws_test_assert(array_key_exists('_gwseq_bso_valeur', $GLOBALS['__gwseq_test_meta'][20]) && array_key_exists('_gwseq_bso_cd', $GLOBALS['__gwseq_test_meta'][20]), 'Composants séparés : "_gwseq_bso_valeur" et "_gwseq_bso_cd" sont bien deux meta distinctes');
gws_test_assert(!is_string($GLOBALS['__gwseq_test_meta'][20]['_gwseq_bso_valeur']) || !preg_match('/\(/', (string) $GLOBALS['__gwseq_test_meta'][20]['_gwseq_bso_valeur']), 'Composants séparés : la valeur BSO stockée ne contient jamais le CD sous forme de chaîne combinée');

// --- Sanitation stricte : non numérique rejeté ---
gwseq_set_cheval_genetic_indice(23, 'bcc', array('valeur' => 'abc', 'cd' => 'xyz'));
gws_test_assert(gwseq_get_cheval_genetic_indice(23, 'bcc') === array('valeur' => '', 'cd' => ''), 'Sanitation : des valeurs non numériques sont rejetées, jamais stockées telles quelles');

// --- Indice génétique/rôle invalide : refusé proprement ---
gws_test_assert(gwseq_set_cheval_genetic_indice(20, 'inconnu', array('valeur' => '1')) === false, 'Robustesse : une clé d’indice génétique inconnue est refusée');
gws_test_assert(gwseq_get_cheval_genetic_indice(20, 'inconnu') === array('valeur' => '', 'cd' => ''), 'Robustesse : la lecture d’une clé d’indice génétique inconnue renvoie des valeurs vides');

// =====================================================================================
// Persistance et compatibilité (§13 de la demande)
// =====================================================================================

// --- Sauvegardes successives sans perte de données ---
gwseq_set_cheval_sport_indice(30, 'iso', array('valeur' => '100', 'annee' => '2020'));
gwseq_set_cheval_genetic_indice(30, 'bso', array('valeur' => '5', 'cd' => '0.5'));
gwseq_set_cheval_sport_indice(30, 'icc', array('valeur' => '90', 'annee' => '2021'));
gws_test_assert(
  gwseq_get_cheval_sport_indice(30, 'iso') === array('valeur' => 100, 'annee' => 2020)
  && gwseq_get_cheval_genetic_indice(30, 'bso') === array('valeur' => 5.0, 'cd' => 0.5)
  && gwseq_get_cheval_sport_indice(30, 'icc') === array('valeur' => 90, 'annee' => 2021),
  'Persistance : plusieurs enregistrements successifs sur des indices différents n’entraînent aucune perte de données'
);

// --- Compatibilité avec une fiche Cheval créée avant l’Étape 6 (jamais enregistrée) ---
gws_test_assert(
  gwseq_get_cheval_sport_indice(999, 'iso') === array('valeur' => '', 'annee' => '')
  && gwseq_get_cheval_genetic_indice(999, 'bso') === array('valeur' => '', 'cd' => ''),
  'Compatibilité : une fiche jamais enregistrée avec ces champs renvoie des valeurs vides, sans erreur ni avertissement'
);

// --- Désactivation/réactivation du module : aucune suppression de meta n’est jamais construite
// dans ce fichier (vérification déclarative directe sur le code source) ---
gws_test_assert(strpos($cheval_indices_source, 'delete_post_meta') === false, 'Désactivation/réactivation : ce fichier n’appelle jamais delete_post_meta() — aucune donnée d’indice ne peut être supprimée par une (dés)activation du module');

// --- Programmatique, sans $_POST ni nonce (§11 — même garantie que le pedigree, Étape 5) ---
$import_result_iso = gwseq_set_cheval_sport_indice(40, 'iso', array('valeur' => '145', 'annee' => '2025'));
$import_result_bso = gwseq_set_cheval_genetic_indice(40, 'bso', array('valeur' => '9', 'cd' => '0.92'));
gws_test_assert($import_result_iso === true && $import_result_bso === true, 'Programmatique : un appel direct (simulant un futur import) enregistre correctement, sans $_POST ni nonce');
// Vérification déclarative : les fonctions de sanitation/persistance elles-mêmes (avant le point
// d'entrée du formulaire) ne lisent jamais $_POST — recherchée hors commentaires PHP, pour ne pas
// confondre un $_POST mentionné dans la documentation avec un appel réel dans le code exécuté.
$indices_tokens = token_get_all($cheval_indices_source);
$before_save_handler_code = '';
foreach ($indices_tokens as $token) {
  if (is_array($token) && in_array($token[0], array(T_COMMENT, T_DOC_COMMENT), true)) continue;
  $before_save_handler_code .= is_array($token) ? $token[1] : $token;
  if (strpos($before_save_handler_code, 'function gwseq_save_cheval_indices_meta') !== false) break;
}
gws_test_assert(strpos($before_save_handler_code, '$_POST') === false, 'Programmatique : aucune fonction de sanitation/persistance (code réellement exécuté, hors commentaires) ne lit $_POST avant le point d’entrée du formulaire');

// =====================================================================================
// Rendu admin et i18n
// =====================================================================================

gws_test_make_post_stub_meta_reset();
gwseq_set_cheval_sport_indice(50, 'iso', array('valeur' => '142', 'annee' => '2025'));
$post_stub = (object) array('ID' => 50);
ob_start();
gwseq_render_cheval_indices_box($post_stub);
$indices_box_html = ob_get_clean();
gws_test_assert(strpos($indices_box_html, 'name="_gwseq_iso[valeur]"') !== false && strpos($indices_box_html, 'name="_gwseq_iso[annee]"') !== false, 'Rendu admin : les champs valeur et année de l’ISO sont bien rendus séparément (deux champs distincts)');
gws_test_assert(strpos($indices_box_html, 'value="142"') !== false && strpos($indices_box_html, 'value="2025"') !== false, 'Rendu admin : les valeurs déjà enregistrées sont bien pré-remplies');
gws_test_assert(strpos($indices_box_html, 'name="_gwseq_bso[valeur]"') !== false && strpos($indices_box_html, 'name="_gwseq_bso[cd]"') !== false, 'Rendu admin : les champs valeur et CD du BSO sont bien rendus séparément');

foreach ($GLOBALS['__gwseq_test_domains_used'] as $domain) {
  gws_test_assert($domain === 'gws-core', "i18n : aucun appel de traduction n’utilise un text domain autre que \"gws-core\" (trouvé : $domain)");
}

echo ($failures === 0 ? 'Tous les tests sont passés.' : "$failures test(s) en échec.") . "\n";
exit($failures === 0 ? 0 : 1);

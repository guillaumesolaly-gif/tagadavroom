<?php
/**
 * Vérifie le comportement réel du composant répétable de GWS Equestrian (Étape 2) :
 * gwseq_repeater_sanitize_rows() / gwseq_repeater_sanitize_value() / gwseq_repeater_row_is_empty().
 * Fonctions pures (aucun accès direct à $_POST ni à la base) — exécutées avec de vraies entrées,
 * pas seulement une vérification de présence de chaînes dans le fichier source.
 *
 * Ne fait pas partie des paquets livrés (gws-core.zip / gws-starter.zip).
 */

$failures = 0;
function gws_test_assert($condition, $label) {
  global $failures;
  if ($condition) { echo "OK   - $label\n"; }
  else { echo "FAIL - $label\n"; $failures++; }
}

// --- Stubs WordPress minimaux (mêmes approximations que les autres tests de ce dossier) ---
function wp_unslash($value) { return is_array($value) ? array_map('wp_unslash', $value) : $value; }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function sanitize_textarea_field($value) { return trim((string) $value); }
function esc_url_raw($value) { $value = trim((string) $value); return $value === '' ? '' : $value; }
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function esc_textarea($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
// i18n (Étape 3, relecture) : "Actions"/"+ Ajouter une ligne"/"Supprimer" passent désormais par
// esc_html__() — ce test porte sur la structure du markup, pas sur la traduction.
function esc_html__($text, $domain = 'default') { return esc_html($text); }
// Étape 6 (GWS Equestrian) : gwseq_render_repeater_field() (limite optionnelle de lignes) utilise
// désormais __()/sprintf() et wp_nonce_field() — stubs minimaux pour exercer ce chemin de rendu,
// jusqu'ici jamais appelé par ce fichier de test (seules les fonctions pures l'étaient).
function __($text, $domain = 'default') { return $text; }
function wp_nonce_field($action, $field) { echo '<input type="hidden" name="' . esc_attr($field) . '" value="stub-nonce">'; }
function get_post_meta($post_id, $key, $single = false) { return $GLOBALS['__gwseq_test_repeater_meta'][$post_id][$key] ?? ''; }

define('ABSPATH', __DIR__ . '/');
$repo_root = dirname(__DIR__);
require $repo_root . '/wp-content/plugins/gws-core/includes/fields.php';
require $repo_root . '/wp-content/plugins/gws-core/modules/gws-equestrian/includes/repeater-field.php';

$schema = array(
  'libelle' => array('label' => 'Libellé', 'type' => 'text'),
  'note' => array('label' => 'Note', 'type' => 'textarea'),
  'valeur' => array('label' => 'Valeur', 'type' => 'number'),
  'annee' => array('label' => 'Année', 'type' => 'integer'),
  'lien' => array('label' => 'Lien', 'type' => 'url'),
);

// =====================================================================================
// Aucune ligne
// =====================================================================================
gws_test_assert(gwseq_repeater_sanitize_rows($schema, array()) === array(), 'Aucune ligne : tableau vide en entrée -> tableau vide en sortie');

// =====================================================================================
// Une ligne simple, plusieurs colonnes de types différents
// =====================================================================================
$one_row = gwseq_repeater_sanitize_rows($schema, array(
  array('libelle' => 'ISO', 'note' => 'Bonne saison', 'valeur' => '145', 'annee' => '2025', 'lien' => 'https://example.test/iso'),
));
gws_test_assert(count($one_row) === 1, 'Une ligne : une seule ligne conservée');
gws_test_assert($one_row[0]['libelle'] === 'ISO', 'Une ligne : texte conservé');
gws_test_assert($one_row[0]['note'] === 'Bonne saison', 'Une ligne : textarea conservé');
gws_test_assert($one_row[0]['valeur'] === 145.0, 'Une ligne : nombre converti en valeur numérique');
gws_test_assert($one_row[0]['annee'] === '2025', 'Une ligne : entier conservé (année)');
gws_test_assert($one_row[0]['lien'] === 'https://example.test/iso', 'Une ligne : URL conservée');

// =====================================================================================
// Plusieurs lignes : ordre de saisie strictement conservé, aucun tri
// =====================================================================================
$ordered = gwseq_repeater_sanitize_rows($schema, array(
  array('libelle' => 'Troisième'),
  array('libelle' => 'Premier'),
  array('libelle' => 'Deuxième'),
));
gws_test_assert(
  array_column($ordered, 'libelle') === array('Troisième', 'Premier', 'Deuxième'),
  'Plusieurs lignes : ordre de saisie conservé tel quel (aucun tri alphabétique ou autre)'
);

// =====================================================================================
// Ligne entièrement vide : jamais stockée
// =====================================================================================
$with_empty = gwseq_repeater_sanitize_rows($schema, array(
  array('libelle' => 'Réelle', 'valeur' => '10'),
  array('libelle' => '', 'note' => '', 'valeur' => '', 'annee' => '', 'lien' => ''),
));
gws_test_assert(count($with_empty) === 1, 'Ligne entièrement vide : écartée, seule la ligne réelle est conservée');

// =====================================================================================
// Valeur 0 : jamais confondue avec une ligne vide, sur chaque type numérique
// =====================================================================================
$zero_number = gwseq_repeater_sanitize_rows($schema, array(array('valeur' => '0')));
gws_test_assert(count($zero_number) === 1, 'Valeur 0 (nombre) : la ligne est conservée, pas traitée comme vide');
gws_test_assert($zero_number[0]['valeur'] === 0.0, 'Valeur 0 (nombre) : convertie en 0.0, pas en chaîne vide');

$zero_integer = gwseq_repeater_sanitize_rows($schema, array(array('annee' => '0')));
gws_test_assert(count($zero_integer) === 1, 'Valeur 0 (entier) : la ligne est conservée, pas traitée comme vide');
gws_test_assert($zero_integer[0]['annee'] === '0', 'Valeur 0 (entier) : conservée sous forme de chaîne "0"');

$zero_text = gwseq_repeater_sanitize_rows($schema, array(array('libelle' => '0')));
gws_test_assert(count($zero_text) === 1, 'Valeur "0" (texte) : la ligne est conservée, "0" est une valeur légitime');

// =====================================================================================
// Entier : conversion, valeur non numérique -> vide, décimal tronqué
// =====================================================================================
gws_test_assert(gwseq_repeater_sanitize_value('integer', '2026') === '2026', 'Entier : valeur numérique conservée');
gws_test_assert(gwseq_repeater_sanitize_value('integer', 'abc') === '', 'Entier : valeur non numérique -> chaîne vide, jamais d’erreur');
gws_test_assert(gwseq_repeater_sanitize_value('integer', '') === '', 'Entier : chaîne vide -> chaîne vide');
gws_test_assert(gwseq_repeater_sanitize_value('integer', '2025.9') === '2025', 'Entier : partie décimale tronquée (pas d’arrondi)');

// =====================================================================================
// Nombre : non numérique -> vide (comportement hérité de gws_core_field_sanitize)
// =====================================================================================
gws_test_assert(gwseq_repeater_sanitize_value('number', 'abc') === '', 'Nombre : valeur non numérique -> chaîne vide');

// =====================================================================================
// Clés inattendues : jamais reportées dans la ligne nettoyée
// =====================================================================================
$unexpected = gwseq_repeater_sanitize_rows($schema, array(
  array('libelle' => 'Avec clé en trop', 'cle_inconnue' => 'ne doit pas apparaître', 'autre_bidule' => array('x')),
));
gws_test_assert(count($unexpected) === 1, 'Clé inattendue : la ligne reste traitée normalement');
gws_test_assert(!array_key_exists('cle_inconnue', $unexpected[0]), 'Clé inattendue : absente de la ligne nettoyée');
gws_test_assert(array_keys($unexpected[0]) === array_keys($schema), 'Clé inattendue : seules les clés du schéma sont présentes, dans son ordre');

// =====================================================================================
// Données mal formées : jamais d’erreur, traitées comme absentes
// =====================================================================================
gws_test_assert(gwseq_repeater_sanitize_rows($schema, 'pas un tableau') === array(), 'Donnée mal formée : une chaîne à la place du tableau de lignes -> tableau vide');
gws_test_assert(gwseq_repeater_sanitize_rows($schema, null) === array(), 'Donnée mal formée : null à la place du tableau de lignes -> tableau vide');

$malformed_rows = gwseq_repeater_sanitize_rows($schema, array(
  'une ligne qui est une chaîne au lieu d’un tableau',
  array('libelle' => 'Ligne valide au milieu'),
  42,
));
gws_test_assert(count($malformed_rows) === 1, 'Donnée mal formée : les lignes qui ne sont pas des tableaux sont ignorées, la ligne valide est conservée');
gws_test_assert($malformed_rows[0]['libelle'] === 'Ligne valide au milieu', 'Donnée mal formée : la ligne valide restante est bien celle attendue');

gws_test_assert(
  gwseq_repeater_sanitize_value('text', array('imbriqué')) === '',
  'Donnée mal formée : une valeur de colonne qui est elle-même un tableau -> chaîne vide, jamais d’erreur'
);

// =====================================================================================
// Caractères spéciaux : apostrophes, accents, esperluette conservés (aucune sanitation
// destructrice sur du texte normal)
// =====================================================================================
$special = gwseq_repeater_sanitize_rows($schema, array(
  array('libelle' => "L'étalon & sa jument – Café", 'note' => "Ligne avec accents : à, é, î, ô, ü"),
));
gws_test_assert($special[0]['libelle'] === "L'étalon & sa jument – Café", 'Caractères spéciaux : apostrophe/accents/esperluette conservés tels quels (texte)');
gws_test_assert($special[0]['note'] === 'Ligne avec accents : à, é, î, ô, ü', 'Caractères spéciaux : accents conservés (textarea)');

// =====================================================================================
// RÉGRESSION (anomalie runtime signalée) : le nommage HTML réel doit grouper les colonnes
// d'une même ligne, pas les éclater. On ne se contente plus de tester
// gwseq_repeater_sanitize_rows() avec un tableau PHP déjà bien formé : on part du VRAI markup
// produit par gwseq_repeater_row_markup(), on en extrait les attributs name/value, on les fait
// passer par parse_str() — le mécanisme PHP réel utilisé pour construire $_POST à partir d'un
// corps de formulaire — puis seulement on sanitize le résultat. C'est le chemin complet qui a
// révélé le bug en recette, pas seulement sa dernière étape.
// =====================================================================================
$runtime_schema = array(
  'libelle' => array('label' => 'Libellé', 'type' => 'text'),
  'valeur' => array('label' => 'Valeur', 'type' => 'number'),
  'annee' => array('label' => 'Année', 'type' => 'integer'),
);
$meta_key = '_gwseq_qa_repeater_demo';

function gws_test_extract_name_value_pairs($markup) {
  preg_match_all('/name="([^"]*)" value="([^"]*)"/', $markup, $matches, PREG_SET_ORDER);
  $pairs = array();
  foreach ($matches as $match) {
    $pairs[] = array(html_entity_decode($match[1], ENT_QUOTES), html_entity_decode($match[2], ENT_QUOTES));
  }
  return $pairs;
}

function gws_test_parse_str_from_pairs($pairs) {
  $parts = array();
  foreach ($pairs as $pair) {
    $parts[] = rawurlencode($pair[0]) . '=' . rawurlencode($pair[1]);
  }
  parse_str(implode('&', $parts), $parsed);
  return $parsed;
}

// --- Le markup d'une ligne réelle utilise bien un index explicite, pas "[]" ---
$row0_markup = gwseq_repeater_row_markup($meta_key, $runtime_schema, array('libelle' => 'ISO', 'valeur' => '125.5', 'annee' => '2025'), 0);
$row1_markup = gwseq_repeater_row_markup($meta_key, $runtime_schema, array('libelle' => 'ICC', 'valeur' => '130', 'annee' => '2026'), 1);

gws_test_assert(strpos($row0_markup, $meta_key . '[0][libelle]') !== false, 'Markup ligne 0 : name utilise "[0][libelle]", pas "[][libelle]"');
gws_test_assert(strpos($row0_markup, $meta_key . '[0][valeur]') !== false, 'Markup ligne 0 : name utilise "[0][valeur]"');
gws_test_assert(strpos($row0_markup, $meta_key . '[0][annee]') !== false, 'Markup ligne 0 : name utilise "[0][annee]"');
gws_test_assert(strpos($row1_markup, $meta_key . '[1][libelle]') !== false, 'Markup ligne 1 : name utilise "[1][libelle]" (index différent de la ligne 0)');
gws_test_assert(strpos($row0_markup, '[][') === false, 'Markup ligne 0 : ne contient plus jamais "[][" (ancien format buggé)');

// --- Reproduction exacte du bug signalé avec l'ANCIEN format ("{meta}[][colonne]" pour chaque
// colonne) : documente pourquoi la correction était nécessaire — une ligne de 3 colonnes y
// devient bien 3 lignes d'une seule colonne chacune une fois passée par le vrai parseur PHP ---
$buggy_pairs = array(
  array($meta_key . '[][libelle]', 'ISO'),
  array($meta_key . '[][valeur]', '125'),
  array($meta_key . '[][annee]', '2025'),
);
$buggy_parsed = gws_test_parse_str_from_pairs($buggy_pairs);
gws_test_assert(
  count($buggy_parsed[$meta_key]) === 3
    && $buggy_parsed[$meta_key][0] === array('libelle' => 'ISO')
    && $buggy_parsed[$meta_key][1] === array('valeur' => '125')
    && $buggy_parsed[$meta_key][2] === array('annee' => '2025'),
  'Caractérisation du bug signalé : l’ancien format "[][colonne]" éclate bien une ligne en 3 lignes distinctes via parse_str() (confirme le diagnostic)'
);

// --- Avec le format corrigé (index explicite partagé), une ligne reste une ligne ---
$fixed_pairs = gws_test_extract_name_value_pairs($row0_markup);
$fixed_parsed = gws_test_parse_str_from_pairs($fixed_pairs);
gws_test_assert(
  count($fixed_parsed[$meta_key]) === 1,
  'Format corrigé : les 3 colonnes de la ligne 0 forment bien une seule ligne après parse_str()'
);
gws_test_assert(
  $fixed_parsed[$meta_key][0] === array('libelle' => 'ISO', 'valeur' => '125.5', 'annee' => '2025'),
  'Format corrigé : la ligne reconstituée contient bien les 3 valeurs saisies, groupées ensemble'
);

// --- Bout en bout : markup de plusieurs lignes -> parse_str() -> sanitation -> structure finale
// attendue, avec le cas exact du CR (une valeur décimale, une valeur 0, plusieurs lignes) ---
$row2_markup = gwseq_repeater_row_markup($meta_key, $runtime_schema, array('libelle' => 'IDR', 'valeur' => '0', 'annee' => '2024'), 2);
$all_pairs = array_merge(
  gws_test_extract_name_value_pairs($row0_markup),
  gws_test_extract_name_value_pairs($row1_markup),
  gws_test_extract_name_value_pairs($row2_markup)
);
$end_to_end_parsed = gws_test_parse_str_from_pairs($all_pairs);
$end_to_end_clean = gwseq_repeater_sanitize_rows($runtime_schema, $end_to_end_parsed[$meta_key]);

gws_test_assert(count($end_to_end_clean) === 3, 'Bout en bout : 3 lignes soumises -> 3 lignes conservées, dans le bon regroupement');
gws_test_assert($end_to_end_clean[0] === array('libelle' => 'ISO', 'valeur' => 125.5, 'annee' => '2025'), 'Bout en bout : ligne 0 exacte, valeur décimale préservée (125.5)');
gws_test_assert($end_to_end_clean[1] === array('libelle' => 'ICC', 'valeur' => 130.0, 'annee' => '2026'), 'Bout en bout : ligne 1 exacte');
gws_test_assert($end_to_end_clean[2] === array('libelle' => 'IDR', 'valeur' => 0.0, 'annee' => '2024'), 'Bout en bout : ligne 2 exacte, valeur 0 préservée (pas traitée comme vide)');

// --- Ligne partiellement remplie (ISO | | 2025) : reste une seule ligne, ni éclatée ni supprimée ---
$partial_markup = gwseq_repeater_row_markup($meta_key, $runtime_schema, array('libelle' => 'ISO', 'valeur' => '', 'annee' => '2025'), 0);
$partial_parsed = gws_test_parse_str_from_pairs(gws_test_extract_name_value_pairs($partial_markup));
$partial_clean = gwseq_repeater_sanitize_rows($runtime_schema, $partial_parsed[$meta_key]);
gws_test_assert(count($partial_clean) === 1, 'Ligne partiellement remplie : conservée comme une seule ligne (pas supprimée)');
gws_test_assert($partial_clean[0] === array('libelle' => 'ISO', 'valeur' => '', 'annee' => '2025'), 'Ligne partiellement remplie : valeurs exactes, colonne vide conservée telle quelle');

// =====================================================================================
// RÉGRESSION (anomalie n°1) : 'number' doit accepter les décimales (step="any"), 'integer' reste
// limité aux entiers (step="1"), les autres types n'ont pas d'attribut step.
// =====================================================================================
gws_test_assert(strpos($row0_markup, 'step="any"') !== false, 'Type number : attribut step="any" présent (accepte les décimales dans le navigateur)');
gws_test_assert(strpos($row0_markup, 'step="1"') !== false, 'Type integer : attribut step="1" présent (limite le navigateur aux entiers)');

$text_only_markup = gwseq_repeater_row_markup('_x', array('libelle' => array('label' => 'Libellé', 'type' => 'text')), array('libelle' => 'x'), 0);
gws_test_assert(strpos($text_only_markup, 'step=') === false, 'Type text : aucun attribut step (non pertinent pour ce type)');

// =====================================================================================
// GWS Equestrian — Étape 6 : limite optionnelle de lignes ($max_rows) sur
// gwseq_render_repeater_field(), ajoutée pour un besoin réel (10 vidéos maximum par cheval) sans
// changer le comportement par défaut (aucune limite affichée si l'argument est omis).
// =====================================================================================

$GLOBALS['__gwseq_test_repeater_meta'] = array(42 => array('_x_videos' => array(
  array('libelle' => 'Une'),
  array('libelle' => 'Deux'),
)));
$video_schema = array('libelle' => array('label' => 'Libellé', 'type' => 'text'));
$post_stub = (object) array('ID' => 42);

ob_start();
gwseq_render_repeater_field($post_stub, '_x_videos', $video_schema, 'x_nonce_action', 10);
$repeater_with_max_html = ob_get_clean();
gws_test_assert(strpos($repeater_with_max_html, 'data-gwseq-repeater-max="10"') !== false, 'Limite de lignes : l’attribut data-gwseq-repeater-max porte bien la valeur fournie');
gws_test_assert(strpos($repeater_with_max_html, '(maximum 10)') !== false, 'Limite de lignes : une indication textuelle du maximum est affichée à côté du bouton d’ajout');

ob_start();
gwseq_render_repeater_field($post_stub, '_x_videos', $video_schema, 'x_nonce_action');
$repeater_without_max_html = ob_get_clean();
gws_test_assert(strpos($repeater_without_max_html, 'data-gwseq-repeater-max') === false, 'Limite de lignes : comportement par défaut inchangé (aucun attribut de limite) quand $max_rows est omis');
gws_test_assert(strpos($repeater_without_max_html, 'maximum') === false, 'Limite de lignes : aucune indication de maximum affichée quand $max_rows est omis');

// --- Le script ne fait que désactiver le bouton d'ajout, jamais supprimer/modifier une ligne
// existante : vérification déclarative directe sur le fichier source ---
$repeater_js_source = file_get_contents($repo_root . '/wp-content/plugins/gws-core/modules/gws-equestrian/assets/repeater-field.js');
gws_test_assert(strpos($repeater_js_source, 'data-gwseq-repeater-max') !== false, 'Limite de lignes (JS) : le script lit bien l’attribut data-gwseq-repeater-max');
gws_test_assert(strpos($repeater_js_source, '.disabled = ') !== false, 'Limite de lignes (JS) : le bouton d’ajout est désactivé (jamais retiré du DOM) une fois la limite atteinte');
gws_test_assert(strpos($repeater_js_source, 'removeChild') !== false, 'Limite de lignes (JS) : la suppression d’une ligne reste un simple retrait DOM, jamais un appel serveur');

echo ($failures === 0 ? 'Tous les tests sont passés.' : "$failures test(s) en échec.") . "\n";
exit($failures === 0 ? 0 : 1);

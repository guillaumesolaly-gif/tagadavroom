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

echo ($failures === 0 ? 'Tous les tests sont passés.' : "$failures test(s) en échec.") . "\n";
exit($failures === 0 ? 0 : 1);

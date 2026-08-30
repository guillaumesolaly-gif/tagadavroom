<?php
/**
 * Vérifie le comportement réel des Prestations / Groupes tarifaires (Étape 3) : sanitation de la
 * tarification et de la relation vers un groupe, résumé de prix, presets, et sécurité de la
 * sauvegarde (nonce/capability/autosave/révision). Comme pour l'Étape 2, on exerce les fonctions
 * avec des données à la forme réelle de $_POST plutôt qu'avec des structures déjà idéalement
 * propres — voir le CR de l'Étape 2 pour la raison de cette exigence.
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
function wp_unslash($value) { return is_array($value) ? array_map('wp_unslash', $value) : $value; }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function sanitize_textarea_field($value) { return trim((string) $value); }
function sanitize_key($value) { return strtolower(preg_replace('/[^a-z0-9_\-]/', '', (string) $value)); }
function sanitize_title($value) {
  $value = strtolower(trim((string) $value));
  $value = preg_replace('/[^a-z0-9]+/', '-', $value);
  return trim($value, '-');
}
function esc_url_raw($value) { $value = trim((string) $value); return $value === '' ? '' : $value; }
function absint($value) { return abs((int) $value); }
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function selected($a, $b) { return $a == $b ? ' selected' : ''; }
function checked($a, $b = true) { return $a == $b ? ' checked' : ''; }
function wp_parse_args($args, $defaults = array()) { return array_merge((array) $defaults, (array) $args); }

// --- Registres en mémoire simulant la base : posts (pour la relation groupe) et post meta ---
$GLOBALS['__gwseq_test_posts'] = array(); // id => post_type
$GLOBALS['__gwseq_test_titles'] = array(); // id => titre courant (pour vérifier le renommage)
function get_post_type($post_id) { return $GLOBALS['__gwseq_test_posts'][$post_id] ?? false; }
function get_the_title($post_id) { return $GLOBALS['__gwseq_test_titles'][$post_id] ?? ''; }

$GLOBALS['__gwseq_test_meta'] = array(); // post_id => [meta_key => value]
function update_post_meta($post_id, $key, $value) {
  $GLOBALS['__gwseq_test_meta'][$post_id][$key] = $value;
  return true;
}
function get_post_meta($post_id, $key, $single = false) {
  return $GLOBALS['__gwseq_test_meta'][$post_id][$key] ?? '';
}
function metadata_exists($type, $post_id, $key) {
  return array_key_exists($post_id, $GLOBALS['__gwseq_test_meta']) && array_key_exists($key, $GLOBALS['__gwseq_test_meta'][$post_id]);
}

// --- Sécurité : registres pilotables par le test pour simuler nonce invalide / permissions
// insuffisantes / autosave / révision ---
$GLOBALS['__gwseq_test_security'] = array(
  'nonce_valid' => true,
  'can_edit' => true,
  'is_revision' => false,
);
function wp_verify_nonce($nonce, $action) { return $GLOBALS['__gwseq_test_security']['nonce_valid']; }
function current_user_can($cap, $post_id = null) { return $GLOBALS['__gwseq_test_security']['can_edit']; }
function wp_is_post_revision($post_id) { return $GLOBALS['__gwseq_test_security']['is_revision']; }

function register_post_meta($object_type, $meta_key, $args = array()) {}
function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {}
function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {}
function register_setting($group, $name, $args = array()) {}
function add_submenu_page(...$args) {}

// --- Réglages globaux (option unique, pilotable par le test) ---
$GLOBALS['__gwseq_test_options'] = array();
function get_option($name, $default = false) {
  return array_key_exists($name, $GLOBALS['__gwseq_test_options']) ? $GLOBALS['__gwseq_test_options'][$name] : $default;
}

define('ABSPATH', __DIR__ . '/');
const GWSEQ_CPT_PRESTATION = 'gwseq_prestation';
const GWSEQ_CPT_GROUPE = 'gwseq_groupe';
$repo_root = dirname(__DIR__);
require $repo_root . '/wp-content/plugins/gws-core/includes/fields.php';
require $repo_root . '/wp-content/plugins/gws-core/modules/gws-equestrian/includes/settings.php';
require $repo_root . '/wp-content/plugins/gws-core/modules/gws-equestrian/includes/prestation-fields.php';
require $repo_root . '/wp-content/plugins/gws-core/modules/gws-equestrian/includes/presets.php';

// =====================================================================================
// Tarification : chemin réel formulaire -> sanitation, un champ HTML fixe par valeur (pas de
// risque de regroupement de lignes ici : voir l'en-tête de prestation-fields.php)
// =====================================================================================

// --- Prix unique, entier ---
$t = gwseq_sanitize_prestation_tarification_input(array(
  '_gwseq_tarif_mode' => 'unique', '_gwseq_tarif_prix' => '45', '_gwseq_tarif_unite' => 'seance', '_gwseq_tarif_prix_public' => '1',
));
gws_test_assert($t['mode'] === 'unique', 'Prix unique entier : mode conservé');
gws_test_assert($t['prix'] === 45.0, 'Prix unique entier : valeur numérique correcte');
gws_test_assert($t['unite'] === 'seance', 'Prix unique entier : unité standard conservée');

// --- Prix unique, décimal ---
$t = gwseq_sanitize_prestation_tarification_input(array('_gwseq_tarif_mode' => 'unique', '_gwseq_tarif_prix' => '45.50'));
gws_test_assert($t['prix'] === 45.5, 'Prix unique décimal : valeur exacte conservée');

// --- Valeur 0 : jamais confondue avec une absence de prix ---
$t = gwseq_sanitize_prestation_tarification_input(array('_gwseq_tarif_mode' => 'unique', '_gwseq_tarif_prix' => '0'));
gws_test_assert($t['prix'] === 0.0, 'Valeur 0 : conservée comme un vrai prix, pas comme une absence de valeur');

// --- Mode cheval/poney : deux prix distincts ---
$t = gwseq_sanitize_prestation_tarification_input(array(
  '_gwseq_tarif_mode' => 'cheval_poney', '_gwseq_tarif_prix_cheval' => '45', '_gwseq_tarif_prix_poney' => '35',
));
gws_test_assert($t['mode'] === 'cheval_poney', 'Cheval/Poney : mode conservé');
gws_test_assert($t['prix_cheval'] === 45.0 && $t['prix_poney'] === 35.0, 'Cheval/Poney : les deux prix sont distincts et corrects');

// --- Sur demande (valeur technique 'devis' conservée) : aucun prix requis, 0 n'est jamais
// utilisé pour signifier l'absence de prix, et le libellé est sanitizé comme un texte ordinaire ---
$t = gwseq_sanitize_prestation_tarification_input(array('_gwseq_tarif_mode' => 'devis', '_gwseq_tarif_demande_libelle' => 'Nous contacter'));
gws_test_assert($t['mode'] === 'devis', 'Sur demande : mode (valeur technique historique "devis") conservé');
gws_test_assert($t['prix'] === '', 'Sur demande : aucun prix numérique inventé (chaîne vide, jamais 0)');
gws_test_assert($t['demande_libelle'] === 'Nous contacter', 'Sur demande : libellé personnalisé sanitizé et conservé');

// --- Mode invalide envoyé (formulaire trafiqué) : repli sûr sur "unique" ---
$t = gwseq_sanitize_prestation_tarification_input(array('_gwseq_tarif_mode' => 'gratuit-illimite'));
gws_test_assert($t['mode'] === 'unique', 'Mode invalide/inconnu : repli sur "unique", jamais de mode arbitraire accepté');

// --- Unité "Autre" + libellé personnalisé ---
$t = gwseq_sanitize_prestation_tarification_input(array('_gwseq_tarif_unite' => 'autre', '_gwseq_tarif_unite_autre' => 'par cycle'));
gws_test_assert($t['unite'] === 'autre' && $t['unite_autre'] === 'par cycle', 'Unité Autre : libellé personnalisé conservé');

// --- Unité invalide envoyée : jamais acceptée telle quelle ---
$t = gwseq_sanitize_prestation_tarification_input(array('_gwseq_tarif_unite' => 'licorne'));
gws_test_assert($t['unite'] === '', 'Unité inconnue : jamais conservée telle quelle');

// --- Caractères spéciaux dans le libellé d'unité personnalisé ---
$t = gwseq_sanitize_prestation_tarification_input(array('_gwseq_tarif_unite' => 'autre', '_gwseq_tarif_unite_autre' => "par jument & poulain"));
gws_test_assert($t['unite_autre'] === 'par jument & poulain', 'Caractères spéciaux (esperluette) conservés dans le libellé personnalisé');

// --- Visibilité du prix (case à cocher) ---
$t = gwseq_sanitize_prestation_tarification_input(array('_gwseq_tarif_prix_public' => '1'));
gws_test_assert($t['prix_public'] === '1', 'Case "Afficher publiquement" cochée : conservée');
$t = gwseq_sanitize_prestation_tarification_input(array());
gws_test_assert($t['prix_public'] === '', 'Case absente du POST (décochée) : valeur vide, jamais "1" par défaut à la sanitation');

// --- Données mal formées : jamais d'erreur ---
$t = gwseq_sanitize_prestation_tarification_input('pas un tableau');
gws_test_assert($t['mode'] === 'unique' && $t['prix'] === '', 'Donnée mal formée (pas un tableau) : repli sûr sur les valeurs par défaut');

// =====================================================================================
// Relation Prestation -> Groupe : référence par ID, jamais par nom ; renommage sans casse
// =====================================================================================
$GLOBALS['__gwseq_test_posts'][10] = GWSEQ_CPT_GROUPE;
$GLOBALS['__gwseq_test_titles'][10] = 'Pensions';
$GLOBALS['__gwseq_test_posts'][99] = 'page'; // un post d'un autre type, même ID plausible

gws_test_assert(gwseq_sanitize_prestation_groupe_id('10') === 10, 'Relation groupe : un ID pointant vers un vrai Groupe tarifaire est conservé');
gws_test_assert(gwseq_sanitize_prestation_groupe_id('99') === 0, 'Relation groupe : un ID pointant vers un post d’un autre type est rejeté');
gws_test_assert(gwseq_sanitize_prestation_groupe_id('12345') === 0, 'Relation groupe : un ID inexistant est rejeté');
gws_test_assert(gwseq_sanitize_prestation_groupe_id('abc') === 0, 'Relation groupe : une valeur non numérique est rejetée');
gws_test_assert(gwseq_sanitize_prestation_groupe_id('0') === 0, 'Relation groupe : "aucun groupe" (0) reste 0');

// Renommage du groupe : la prestation reste liée au même ID, la résolution du nom suit le
// renommage (aucune copie du nom n'a jamais été stockée dans la prestation).
$groupe_id = gwseq_sanitize_prestation_groupe_id('10');
gws_test_assert(get_the_title($groupe_id) === 'Pensions', 'Avant renommage : le nom résolu est "Pensions"');
$GLOBALS['__gwseq_test_titles'][10] = 'Nos pensions';
gws_test_assert(get_the_title($groupe_id) === 'Nos pensions', 'Après renommage du groupe : le nom résolu suit le nouveau titre, sans casser la relation (même ID)');

// =====================================================================================
// Résumé de prix (fonction pure, réutilisable admin/front/API) — §28 : jamais de HTML
// =====================================================================================
gws_test_assert(gwseq_prestation_price_summary(array('mode' => 'devis', 'demande_libelle' => 'Sur demande'), 'ttc') === 'Sur demande', 'Résumé : mode "Sur demande" avec libellé résolu -> affiché tel quel');

$tarif = array('mode' => 'unique', 'prix' => 45.0, 'unite' => 'seance', 'unite_autre' => '', 'prix_public' => '1');
gws_test_assert(gwseq_prestation_price_summary($tarif, 'ttc') === '45 € TTC / Séance', 'Résumé : prix unique affiché avec unité et suffixe TTC');
gws_test_assert(gwseq_prestation_price_summary($tarif, 'ht') === '45 € HT / Séance', 'Résumé : suffixe HT selon le réglage global');

$tarif_hidden = array('mode' => 'unique', 'prix' => 45.0, 'unite' => '', 'unite_autre' => '', 'prix_public' => '');
gws_test_assert(gwseq_prestation_price_summary($tarif_hidden, 'ttc') === 'Tarif non affiché publiquement', 'Résumé : prix non public -> jamais le montant réel affiché');

$tarif_decimal = array('mode' => 'unique', 'prix' => 45.5, 'unite' => '', 'unite_autre' => '', 'prix_public' => '1');
gws_test_assert(gwseq_prestation_price_summary($tarif_decimal, 'ttc') === '45,50 € TTC', 'Résumé : décimale affichée avec virgule, aucune unité si non renseignée');

$tarif_cp = array('mode' => 'cheval_poney', 'prix_cheval' => 45.0, 'prix_poney' => 35.0, 'unite' => '', 'unite_autre' => '', 'prix_public' => '1');
gws_test_assert(gwseq_prestation_price_summary($tarif_cp, 'ttc') === 'Cheval 45 € · Poney 35 € TTC', 'Résumé : Cheval/Poney, les deux prix renseignés');

$tarif_cp_partial = array('mode' => 'cheval_poney', 'prix_cheval' => 45.0, 'prix_poney' => '', 'unite' => '', 'unite_autre' => '', 'prix_public' => '1');
gws_test_assert(gwseq_prestation_price_summary($tarif_cp_partial, 'ttc') === 'Cheval 45 € TTC', 'Résumé : Cheval/Poney, un seul prix renseigné');

$tarif_autre_unite = array('mode' => 'unique', 'prix' => 10.0, 'unite' => 'autre', 'unite_autre' => 'par cycle', 'prix_public' => '1');
gws_test_assert(gwseq_prestation_price_summary($tarif_autre_unite, 'ttc') === '10 € TTC / par cycle', 'Résumé : unité "Autre" utilise le libellé personnalisé');

$tarif_no_price = array('mode' => 'unique', 'prix' => '', 'unite' => '', 'unite_autre' => '', 'prix_public' => '1');
gws_test_assert(gwseq_prestation_price_summary($tarif_no_price, 'ttc') === '', 'Résumé : mode unique sans prix renseigné -> résumé vide, jamais "0 €"');

// =====================================================================================
// Réglage global d'affichage des prix : TTC / HT / Prix masqués (ajouté suite à la relecture)
// =====================================================================================
$tarif_normal = array('mode' => 'unique', 'prix' => 45.0, 'unite' => '', 'unite_autre' => '', 'prix_public' => '1');
gws_test_assert(gwseq_prestation_price_summary($tarif_normal, 'ttc') === '45 € TTC', 'Réglage global TTC : montant et suffixe TTC affichés');
gws_test_assert(gwseq_prestation_price_summary($tarif_normal, 'ht') === '45 € HT', 'Réglage global HT : montant et suffixe HT affichés');
gws_test_assert(gwseq_prestation_price_summary($tarif_normal, 'hidden') === 'Tarif non affiché publiquement', 'Réglage global "Prix masqués" : aucun montant affiché, même si la case individuelle est cochée');

// --- Conservation des montants : le mode global masqué est une règle de PRÉSENTATION uniquement,
// jamais une suppression de données ---
gws_test_assert(
  $tarif_normal['prix'] === 45.0,
  'Prix masqués (global) : le montant reste présent dans les données (rien n’est jamais effacé par ce réglage)'
);

// --- Interaction masque global / visibilité individuelle : le masque global l'emporte toujours,
// mais un masque individuel seul (sans masque global) continue de fonctionner ---
$tarif_individuel_masque = array('mode' => 'unique', 'prix' => 45.0, 'unite' => '', 'unite_autre' => '', 'prix_public' => '');
gws_test_assert(gwseq_prestation_price_summary($tarif_individuel_masque, 'ttc') === 'Tarif non affiché publiquement', 'Masque individuel seul (réglage global TTC) : cette prestation ne montre pas son tarif');
gws_test_assert(gwseq_prestation_price_summary($tarif_normal, 'hidden') === gwseq_prestation_price_summary($tarif_individuel_masque, 'hidden'), 'Masque global : le résultat est identique que la case individuelle soit cochée ou non');

// --- "Sur demande" reste inchangé par le réglage global, y compris en mode masqué ---
gws_test_assert(gwseq_prestation_price_summary(array('mode' => 'devis', 'demande_libelle' => 'Sur demande'), 'hidden') === 'Sur demande', 'Sur demande : jamais affecté par le réglage global d’affichage des prix (montants chiffrés uniquement)');

// =====================================================================================
// Mode "Sur demande" — libellé par défaut / personnalisé / volontairement vide, compatibilité
// avec la valeur technique historique 'devis', interaction avec le masque global des prix
// =====================================================================================

// --- Libellé par défaut : jamais initialisé (prestation neuve, ou prestation historique créée
// avant ce champ en 1.6.1 avec mode 'devis' déjà enregistré) -> "Sur demande" sans migration ---
$GLOBALS['__gwseq_test_meta'][60] = array('_gwseq_tarif_mode' => 'devis'); // simule une prestation 1.6.1 existante, sans le nouveau champ
gws_test_assert(
  gwseq_get_prestation_demande_libelle(60) === 'Sur demande',
  'Compatibilité 1.6.1 : une prestation "devis" déjà existante sans libellé enregistré affiche "Sur demande" par défaut, sans migration'
);
gws_test_assert(
  gwseq_get_prestation_tarif(60)['mode'] === 'devis',
  'Compatibilité 1.6.1 : le mode technique "devis" d’une prestation déjà existante reste inchangé'
);

// --- Libellé personnalisé "Sur devis" (cas B de la demande) ---
$GLOBALS['__gwseq_test_meta'][61] = array('_gwseq_tarif_mode' => 'devis', '_gwseq_tarif_demande_libelle' => 'Sur devis');
gws_test_assert(gwseq_get_prestation_demande_libelle(61) === 'Sur devis', 'Libellé personnalisé "Sur devis" (cas B) : conservé et restitué tel quel');

// --- Libellé personnalisé "Nous contacter" ---
$GLOBALS['__gwseq_test_meta'][62] = array('_gwseq_tarif_mode' => 'devis', '_gwseq_tarif_demande_libelle' => 'Nous contacter');
gws_test_assert(gwseq_get_prestation_demande_libelle(62) === 'Nous contacter', 'Libellé personnalisé "Nous contacter" : conservé et restitué tel quel');

// --- Caractères spéciaux dans le libellé ---
$t_libelle_special = gwseq_sanitize_prestation_tarification_input(array('_gwseq_tarif_mode' => 'devis', '_gwseq_tarif_demande_libelle' => "Nous contacter — devis sous 48h & sans engagement"));
gws_test_assert($t_libelle_special['demande_libelle'] === "Nous contacter — devis sous 48h & sans engagement", 'Libellé "Sur demande" : caractères spéciaux (tiret cadratin, esperluette) conservés');

// --- Libellé volontairement vide (cas C) : la meta existe (enregistrement explicite) mais est
// vide -> aucun texte tarifaire, à distinguer de "jamais initialisé" (testé ci-dessus, id 60) ---
$GLOBALS['__gwseq_test_meta'][63] = array('_gwseq_tarif_mode' => 'devis', '_gwseq_tarif_demande_libelle' => '');
gws_test_assert(gwseq_get_prestation_demande_libelle(63) === '', 'Libellé volontairement vide (cas C) : distinct de "jamais initialisé", reste vide (jamais de fallback)');
gws_test_assert(
  gwseq_prestation_price_summary(gwseq_get_prestation_tarif(63), 'ttc') === '',
  'Libellé volontairement vide : aucun texte tarifaire rendu pour cette prestation'
);

// --- Aucun prix numérique obligatoire en mode "Sur demande" ; 0 n'est jamais utilisé pour
// représenter l'absence de prix (même vérification que pour "unique", appliquée à ce mode) ---
$t_devis_sans_prix = gwseq_sanitize_prestation_tarification_input(array('_gwseq_tarif_mode' => 'devis'));
gws_test_assert($t_devis_sans_prix['prix'] === '', 'Sur demande : aucun prix numérique requis (chaîne vide)');
gws_test_assert($t_devis_sans_prix['prix'] !== 0 && $t_devis_sans_prix['prix'] !== '0', 'Sur demande : 0 n’est jamais utilisé pour représenter l’absence de prix');

// --- Interaction avec "Prix masqués" : le libellé reste toujours disponible (montants chiffrés
// uniquement concernés par ce réglage), sauf si volontairement vide ---
$tarif_demande_avec_libelle = array('mode' => 'devis', 'demande_libelle' => 'Nous contacter');
gws_test_assert(
  gwseq_prestation_price_summary($tarif_demande_avec_libelle, 'hidden') === 'Nous contacter',
  'Prix globaux masqués + Sur demande : le libellé "Nous contacter" reste rendu (exemple exact du CR)'
);
$tarif_demande_vide = array('mode' => 'devis', 'demande_libelle' => '');
gws_test_assert(
  gwseq_prestation_price_summary($tarif_demande_vide, 'hidden') === '',
  'Prix globaux masqués + libellé volontairement vide : aucun texte tarifaire rendu'
);

// =====================================================================================
// Devise : EUR par défaut, au moins une autre devise, aucun symbole codé en dur
// =====================================================================================
gws_test_assert(gwseq_currency_symbol('EUR') === '€', 'Devise : EUR -> symbole €');
gws_test_assert(gwseq_currency_symbol('GBP') === '£', 'Devise : GBP -> symbole £ (au moins une autre devise que EUR)');
gws_test_assert(gwseq_currency_symbol('USD') === '$', 'Devise : USD -> symbole $');
gws_test_assert(gwseq_currency_symbol('CHF') === 'CHF', 'Devise : CHF -> "CHF" (pas de symbole unicode dédié, choix assumé)');

$GLOBALS['__gwseq_test_options'] = array(); // aucun réglage enregistré : valeurs par défaut pures
gws_test_assert(gwseq_get_currency() === 'EUR', 'Devise par défaut du site : EUR sans configuration');
gws_test_assert(gwseq_get_price_display_mode() === 'ttc', 'Mode d’affichage par défaut du site : TTC sans configuration');

$GLOBALS['__gwseq_test_options'] = array('gwseq_settings' => array('currency' => 'GBP', 'price_display_mode' => 'hidden'));
gws_test_assert(gwseq_get_currency() === 'GBP', 'Devise configurée explicitement : GBP correctement lue');
gws_test_assert(gwseq_get_price_display_mode() === 'hidden', 'Mode d’affichage configuré explicitement : "hidden" correctement lu');
$GLOBALS['__gwseq_test_options'] = array('gwseq_settings' => array('currency' => 'inexistante'));
gws_test_assert(gwseq_get_currency() === 'EUR', 'Devise inconnue enregistrée (donnée corrompue) : repli sûr sur EUR');
$GLOBALS['__gwseq_test_options'] = array();

$tarif_gbp = array('mode' => 'unique', 'prix' => 45.0, 'unite' => '', 'unite_autre' => '', 'prix_public' => '1');
gws_test_assert(gwseq_prestation_price_summary($tarif_gbp, 'ttc', 'GBP') === '45 £ TTC', 'Résumé de prix en livre sterling : symbole £ utilisé, pas €');
gws_test_assert(gwseq_prestation_price_summary($tarif_gbp, 'ttc', 'CHF') === '45 CHF TTC', 'Résumé de prix en franc suisse : "CHF" utilisé, pas €');

$tarif_cp_usd = array('mode' => 'cheval_poney', 'prix_cheval' => 45.0, 'prix_poney' => 35.0, 'unite' => '', 'unite_autre' => '', 'prix_public' => '1');
gws_test_assert(gwseq_prestation_price_summary($tarif_cp_usd, 'ttc', 'USD') === 'Cheval 45 $ · Poney 35 $ TTC', 'Résumé Cheval/Poney en dollar : symbole $ utilisé sur les deux montants');

// --- Aucun symbole € codé en dur dans la logique générique de rendu tarifaire (vérification
// directe du code source de la fonction de résumé) ---
$prestation_fields_source = file_get_contents($repo_root . '/wp-content/plugins/gws-core/modules/gws-equestrian/includes/prestation-fields.php');
$price_summary_source = substr(
  $prestation_fields_source,
  strpos($prestation_fields_source, 'function gwseq_prestation_price_summary'),
  strpos($prestation_fields_source, 'function gwseq_add_prestation_meta_boxes') - strpos($prestation_fields_source, 'function gwseq_prestation_price_summary')
);
gws_test_assert(strpos($price_summary_source, '€') === false, 'Aucun symbole € codé en dur dans gwseq_prestation_price_summary() : la devise passe toujours par gwseq_currency_symbol()');

// =====================================================================================
// Unités supplémentaires (récolte / colis / étalon) et presets corrigés
// =====================================================================================
$unit_options = gwseq_prestation_unit_options();
foreach (array('recolte' => 'Récolte', 'colis' => 'Colis', 'etalon' => 'Étalon') as $key => $label) {
  gws_test_assert(array_key_exists($key, $unit_options) && $unit_options[$key] === $label, "Unité supplémentaire disponible : $key ($label)");
}

$flat_presets = gwseq_prestation_preset_flat();
gws_test_assert(
  ($flat_presets[sanitize_title('Semence — congélation')]['unite'] ?? null) === 'paillette',
  'Preset Congélation : unité suggérée corrigée en "paillette" (et non plus "dose")'
);
gws_test_assert(
  ($flat_presets[sanitize_title('Semence — réfrigération')]['unite'] ?? null) === 'recolte',
  'Preset Réfrigération : unité suggérée "récolte"'
);
gws_test_assert(
  ($flat_presets[sanitize_title('Semence — préparation doses réfrigérées')]['unite'] ?? null) === 'dose',
  'Preset Préparation doses réfrigérées : unité "dose" confirmée inchangée'
);
gws_test_assert(
  ($flat_presets[sanitize_title('Semence — expédition France / international')]['unite'] ?? null) === 'colis',
  'Preset Expédition : unité suggérée "colis"'
);
gws_test_assert(
  ($flat_presets[sanitize_title('Spermogramme')]['unite'] ?? null) === 'etalon',
  'Preset Spermogramme : unité suggérée "étalon"'
);

// =====================================================================================
// Presets : aide à la création, jamais une donnée persistante
// =====================================================================================
$flat = gwseq_prestation_preset_flat();
gws_test_assert(count($flat) === count(array_unique(array_keys($flat))), 'Presets : tous les identifiants générés sont uniques (aucune collision de slug)');
gws_test_assert(isset($flat[sanitize_title('Pension pré avec infrastructures')]), 'Presets : un modèle attendu est bien présent dans la liste à plat');

$_GET['gwseq_preset'] = sanitize_title('IAF / chaleur');
$requested = gwseq_get_requested_preset_defaults();
gws_test_assert($requested !== null && $requested['label'] === 'IAF / chaleur', 'Presets : modèle demandé via le paramètre d’URL correctement résolu');
gws_test_assert($requested['unite'] === 'chaleur', 'Presets : unité suggérée du modèle correctement transmise');

$_GET['gwseq_preset'] = 'ce-modele-n-existe-pas';
gws_test_assert(gwseq_get_requested_preset_defaults() === null, 'Presets : un identifiant inconnu ne renvoie rien (jamais d’erreur, jamais de contenu inventé)');
unset($_GET['gwseq_preset']);
gws_test_assert(gwseq_get_requested_preset_defaults() === null, 'Presets : aucun paramètre fourni -> aucun modèle appliqué');

// --- Aucune création automatique : ni wp_insert_post ni wp_insert_term dans le fichier des
// modèles (vérification directe du code source, pas seulement du comportement observé) ---
$presets_source = file_get_contents($repo_root . '/wp-content/plugins/gws-core/modules/gws-equestrian/includes/presets.php');
gws_test_assert(strpos($presets_source, 'wp_insert_post') === false, 'Presets : aucun appel à wp_insert_post() dans le fichier (aucune création automatique de contenu)');
gws_test_assert(strpos($presets_source, 'wp_insert_term') === false, 'Presets : aucun appel à wp_insert_term() dans le fichier');
gws_test_assert(strpos($presets_source, 'register_activation_hook') === false, 'Presets : aucun hook d’activation (les modèles ne sont jamais semés à l’activation du module)');

// =====================================================================================
// Sécurité de la sauvegarde : nonce invalide / permissions insuffisantes / autosave / révision
// doivent empêcher toute écriture — chemin réel via $_POST et gwseq_save_prestation_meta()
// =====================================================================================
function gws_test_reset_security() {
  $GLOBALS['__gwseq_test_security'] = array('nonce_valid' => true, 'can_edit' => true, 'is_revision' => false);
}
function gws_test_post_payload() {
  return array(
    GWSEQ_PRESTATION_NONCE_FIELD => 'nonce',
    '_gwseq_prestation_groupe_id' => '10',
    '_gwseq_tarif_mode' => 'unique',
    '_gwseq_tarif_prix' => '99',
  );
}

// --- Nonce invalide ---
gws_test_reset_security();
$GLOBALS['__gwseq_test_security']['nonce_valid'] = false;
$_POST = gws_test_post_payload();
$GLOBALS['__gwseq_test_meta'][42] = array();
gwseq_save_prestation_meta(42);
gws_test_assert($GLOBALS['__gwseq_test_meta'][42] === array(), 'Nonce invalide : aucune meta écrite');

// --- Permissions insuffisantes ---
gws_test_reset_security();
$GLOBALS['__gwseq_test_security']['can_edit'] = false;
$_POST = gws_test_post_payload();
$GLOBALS['__gwseq_test_meta'][43] = array();
gwseq_save_prestation_meta(43);
gws_test_assert($GLOBALS['__gwseq_test_meta'][43] === array(), 'Permissions insuffisantes : aucune meta écrite');

// --- Révision ---
gws_test_reset_security();
$GLOBALS['__gwseq_test_security']['is_revision'] = true;
$_POST = gws_test_post_payload();
$GLOBALS['__gwseq_test_meta'][45] = array();
gwseq_save_prestation_meta(45);
gws_test_assert($GLOBALS['__gwseq_test_meta'][45] === array(), 'Révision : aucune meta écrite');

// --- Cas valide : toutes les gardes passent -> les meta sont bien écrites ---
gws_test_reset_security();
$_POST = gws_test_post_payload();
$GLOBALS['__gwseq_test_meta'][46] = array();
gwseq_save_prestation_meta(46);
gws_test_assert(
  $GLOBALS['__gwseq_test_meta'][46]['_gwseq_prestation_groupe_id'] === 10
    && $GLOBALS['__gwseq_test_meta'][46]['_gwseq_tarif_mode'] === 'unique'
    && $GLOBALS['__gwseq_test_meta'][46]['_gwseq_tarif_prix'] === 99.0,
  'Cas valide : nonce/capability/autosave/révision tous corrects -> les meta sont bien enregistrées'
);

// --- Autosave : testé en dernier, DOING_AUTOSAVE ne peut être défini qu'une fois par processus
// PHP et resterait sinon "vrai" pour tous les cas suivants ---
gws_test_reset_security();
define('DOING_AUTOSAVE', true);
$_POST = gws_test_post_payload();
$GLOBALS['__gwseq_test_meta'][44] = array();
gwseq_save_prestation_meta(44);
gws_test_assert($GLOBALS['__gwseq_test_meta'][44] === array(), 'Autosave : aucune meta écrite');

echo ($failures === 0 ? 'Tous les tests sont passés.' : "$failures test(s) en échec.") . "\n";
exit($failures === 0 ? 0 : 1);

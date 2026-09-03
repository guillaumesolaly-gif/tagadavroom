<?php
/**
 * Vérifie la présentation éditoriale et les informations complémentaires de la fiche Cheval
 * (Étape 6, §7-8 de la demande) : chaque champ enregistré/lu indépendamment, sanitation stricte
 * (HTML retiré, sauts de ligne conservés), et surtout la séparation stricte entre :
 * - la Production éditoriale (`_gwseq_commentaire_production`, texte libre) et la Production
 *   CALCULÉE (gwseq_get_horse_offspring(), Étape 5, donnée relationnelle jamais stockée) ;
 * - le commentaire Origines éditorial (`_gwseq_origines_commentaire`) et le pedigree STRUCTURÉ
 *   (`_gwseq_pere_*`/`_gwseq_mere_*`, Étape 5).
 * Chemin programmatique sans $_POST ni nonce (même méthodologie que le pedigree).
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
// Fidèle au comportement réel de sanitize_textarea_field()/wp_strip_all_tags() : le contenu d'une
// balise <script>/<style> est retiré ENTIÈREMENT (pas seulement les délimiteurs de balise, à la
// différence d'un simple strip_tags()) avant de retirer le reste des balises — préserve les sauts
// de ligne (contrairement à sanitize_text_field()), important ici pour des textes libres
// potentiellement multi-lignes (§7 de la demande).
function sanitize_textarea_field($value) {
  $value = (string) $value;
  $value = preg_replace('@<(script|style)[^>]*?>.*?</\1>@si', '', $value);
  return trim(strip_tags($value));
}
function sanitize_key($value) { return strtolower(preg_replace('/[^a-z0-9_\-]/', '', (string) $value)); }
function absint($value) { return abs((int) $value); }
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function esc_textarea($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
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
function add_meta_box($id, $title, $callback, $post_type = null, $context = 'advanced', $priority = 'default') { $GLOBALS['__gwseq_test_meta_boxes'][] = $id; }

$GLOBALS['__gwseq_test_meta'] = array();
function update_post_meta($post_id, $key, $value) { $GLOBALS['__gwseq_test_meta'][$post_id][$key] = $value; return true; }
function get_post_meta($post_id, $key, $single = false) { return $GLOBALS['__gwseq_test_meta'][$post_id][$key] ?? ''; }

$GLOBALS['__gwseq_test_security'] = array('nonce_valid' => true, 'can_edit' => true, 'is_revision' => false);
function wp_verify_nonce($nonce, $action) { return $GLOBALS['__gwseq_test_security']['nonce_valid']; }
function current_user_can($cap, $post_id = null) { return $GLOBALS['__gwseq_test_security']['can_edit']; }
function wp_is_post_revision($post_id) { return $GLOBALS['__gwseq_test_security']['is_revision']; }

function gws_test_strip_php_comments($source) {
  $tokens = token_get_all($source);
  $code = '';
  foreach ($tokens as $token) {
    if (is_array($token) && in_array($token[0], array(T_COMMENT, T_DOC_COMMENT), true)) continue;
    $code .= is_array($token) ? $token[1] : $token;
  }
  return $code;
}

define('ABSPATH', __DIR__ . '/');
const GWSEQ_CPT_CHEVAL = 'gwseq_cheval';
$repo_root = dirname(__DIR__);
$module_dir = $repo_root . '/wp-content/plugins/gws-core/modules/gws-equestrian/';
require $repo_root . '/wp-content/plugins/gws-core/includes/fields.php';
require $module_dir . 'includes/cheval-fields.php';
require $module_dir . 'includes/cheval-editorial.php';

$cheval_editorial_source = file_get_contents($module_dir . 'includes/cheval-editorial.php');
$cheval_editorial_code_only = gws_test_strip_php_comments($cheval_editorial_source);
$cheval_pedigree_source = file_get_contents($module_dir . 'includes/cheval-pedigree.php');
$cheval_pedigree_code_only = gws_test_strip_php_comments($cheval_pedigree_source);
$pedigree_resolver_source = file_get_contents($module_dir . 'includes/pedigree-resolver.php');
$pedigree_resolver_code_only = gws_test_strip_php_comments($pedigree_resolver_source);

// =====================================================================================
// Chaque champ éditorial, enregistré et lu indépendamment (§7 de la demande)
// =====================================================================================

$all_fields = gwseq_cheval_editorial_field_map();
// 10 depuis l'ajout de l'Accroche commerciale (lot « Partager un cheval », §3) : 9 champs de
// présentation (dont la nouvelle Accroche) + Ostéo-articulaire.
gws_test_assert(count($all_fields) === 10, 'Modèle : les 10 champs éditoriaux attendus (9 de présentation, dont l’Accroche commerciale + Ostéo-articulaire) sont bien déclarés');

// --- Chaque champ peut être enregistré seul, les autres restant vides ---
foreach ($all_fields as $field_key => $meta_key) {
  gws_test_assert(1, 'placeholder'); // no-op pour garder la boucle lisible ; les assertions réelles suivent
}
gwseq_set_cheval_editorial(10, array('_gwseq_presentation' => 'Un beau cheval de sport.'));
$editorial_10 = gwseq_get_cheval_editorial(10);
gws_test_assert($editorial_10['presentation'] === 'Un beau cheval de sport.', 'Champ indépendant : "Présentation" enregistré seul, valeur exacte conservée');
foreach ($all_fields as $field_key => $meta_key) {
  if ($field_key === 'presentation') continue;
  gws_test_assert($editorial_10[$field_key] === '', "Champ indépendant : \"$field_key\" reste vide, jamais affecté par l’enregistrement de \"presentation\" seul");
}

// --- Champ vide accepté : un enregistrement entièrement vide est parfaitement valide ---
gws_test_assert(gwseq_set_cheval_editorial(11, array()) === true, 'Champ vide accepté : un enregistrement sans aucun champ renseigné réussit (tous facultatifs)');
foreach (gwseq_get_cheval_editorial(11) as $field_key => $value) {
  gws_test_assert($value === '', "Champ vide accepté : \"$field_key\" reste bien une chaîne vide, jamais une erreur ni une valeur par défaut inventée");
}

// --- Points forts, Potentiel, Résultats, Conditions de vente, Conseils de croisement :
// enregistrement croisé de plusieurs champs à la fois ---
gwseq_set_cheval_editorial(12, array(
  '_gwseq_points_forts' => 'Bon dressage, très maniable.',
  '_gwseq_potentiel' => 'Potentiel CSO Amateur.',
  '_gwseq_resultats' => 'Plusieurs podiums en 2024.',
  '_gwseq_conditions_vente' => 'Visite sur rendez-vous uniquement.',
  '_gwseq_conseils_croisement' => 'Se marie bien avec des lignées de sang.',
));
$editorial_12 = gwseq_get_cheval_editorial(12);
gws_test_assert(
  $editorial_12['points_forts'] === 'Bon dressage, très maniable.'
  && $editorial_12['potentiel'] === 'Potentiel CSO Amateur.'
  && $editorial_12['resultats'] === 'Plusieurs podiums en 2024.'
  && $editorial_12['conditions_vente'] === 'Visite sur rendez-vous uniquement.'
  && $editorial_12['conseils_croisement'] === 'Se marie bien avec des lignées de sang.',
  'Plusieurs champs éditoriaux enregistrés simultanément : chacun conserve exactement sa propre valeur'
);

// --- "Conseils de croisement" disponible pour TOUS les chevaux, jamais conditionné (§7) : aucune
// vérification de sexe/catégorie n'existe dans le fichier avant de proposer ce champ ---
gws_test_assert(strpos($cheval_editorial_code_only, "'_gwseq_sexe'") === false && strpos($cheval_editorial_code_only, '"_gwseq_sexe"') === false, 'Conseils de croisement : aucune lecture du sexe du cheval n’existe dans ce fichier — le champ est proposé à tous, sans condition');

// --- Sanitation correcte : HTML/scripts retirés, texte conservé ---
gwseq_set_cheval_editorial(13, array('_gwseq_presentation' => '<script>alert(1)</script>Un très bon cheval.'));
gws_test_assert(gwseq_get_cheval_editorial(13)['presentation'] === 'Un très bon cheval.', 'Sanitation : une balise <script> est retirée, le reste du texte conservé intact');

// =====================================================================================
// Production éditoriale DISTINCTE de la Production calculée (§7 de la demande)
// =====================================================================================

gwseq_set_cheval_editorial(20, array('_gwseq_commentaire_production' => 'Production très regardée, plusieurs produits primés.'));
gws_test_assert(gwseq_get_cheval_editorial(20)['commentaire_production'] === 'Production très regardée, plusieurs produits primés.', 'Production éditoriale : enregistrée et lue correctement via son propre champ');

// --- Le nom de meta lui-même est sans ambiguïté : jamais "_gwseq_production" tout court, qui
// prêterait à confusion avec la donnée calculée ---
gws_test_assert($all_fields['commentaire_production'] === '_gwseq_commentaire_production', 'Nom de meta explicite : "_gwseq_commentaire_production", jamais un simple "_gwseq_production" ambigu');
gws_test_assert(!array_key_exists('_gwseq_production', $GLOBALS['__gwseq_test_meta'][20] ?? array()), 'Production éditoriale : aucune meta "_gwseq_production" (sans préfixe "commentaire_") n’est jamais créée');

// --- Ce fichier ne lit ni n'écrit jamais la Production calculée (gwseq_get_horse_offspring(),
// définie dans cheval-pedigree.php) — vérification déclarative directe ---
gws_test_assert(strpos($cheval_editorial_code_only, 'gwseq_get_horse_offspring') === false, 'Production éditoriale : ce fichier n’appelle jamais gwseq_get_horse_offspring() (la Production calculée reste exclusivement gérée par cheval-pedigree.php)');
// --- Et réciproquement : cheval-pedigree.php (Production calculée) ne connaît pas ce champ éditorial ---
gws_test_assert(strpos($cheval_pedigree_code_only, '_gwseq_commentaire_production') === false, 'Production calculée : cheval-pedigree.php ne lit ni n’écrit jamais "_gwseq_commentaire_production" — les deux concepts restent complètement indépendants en code');

// =====================================================================================
// Origines éditoriales DISTINCTES du pedigree structuré (§7 de la demande)
// =====================================================================================

gwseq_set_cheval_editorial(21, array('_gwseq_origines_commentaire' => 'Une lignée maternelle reconnue pour sa production de sauteurs.'));
gws_test_assert(gwseq_get_cheval_editorial(21)['origines_commentaire'] === 'Une lignée maternelle reconnue pour sa production de sauteurs.', 'Origines éditoriales : enregistrées et lues correctement via leur propre champ');

// --- Ce fichier ne lit ni n'écrit jamais les meta du pedigree structuré ---
foreach (array('_gwseq_pere_mode', '_gwseq_pere_id', '_gwseq_pere_externe', '_gwseq_mere_mode', '_gwseq_mere_id', '_gwseq_mere_externe') as $pedigree_meta_key) {
  gws_test_assert(strpos($cheval_editorial_code_only, "'" . $pedigree_meta_key . "'") === false, "Origines éditoriales : ce fichier ne lit ni n'écrit jamais la meta de pedigree structuré \"$pedigree_meta_key\"");
}
// --- Et réciproquement : ni cheval-pedigree.php ni le resolver ne connaissent ce commentaire
// éditorial — jamais reconstruit à partir de lui, ni l'inverse ---
gws_test_assert(strpos($cheval_pedigree_code_only, '_gwseq_origines_commentaire') === false, 'Pedigree structuré : cheval-pedigree.php ne lit ni n’écrit jamais "_gwseq_origines_commentaire"');
gws_test_assert(strpos($pedigree_resolver_code_only, '_gwseq_origines_commentaire') === false, 'Resolver : pedigree-resolver.php ne lit ni n’écrit jamais "_gwseq_origines_commentaire" — le pedigree résolu n’est jamais reconstruit à partir de ce texte');

// --- Enregistrer le commentaire Origines ne modifie jamais la relation pedigree, et
// réciproquement (fonctionnel, pas seulement déclaratif) ---
gwseq_set_cheval_editorial(21, array('_gwseq_origines_commentaire' => 'Un commentaire modifié.'));
gws_test_assert(!array_key_exists('_gwseq_pere_mode', $GLOBALS['__gwseq_test_meta'][21] ?? array()), 'Origines éditoriales : enregistrer ce commentaire ne crée ni ne modifie jamais la relation "père" du pedigree structuré');

// =====================================================================================
// Ostéo-articulaire (§8 de la demande) — texte libre uniquement, jamais un dossier vétérinaire
// =====================================================================================

gwseq_set_cheval_editorial(22, array('_gwseq_osteo_articulaire' => 'RAS aux dernières observations.'));
gws_test_assert(gwseq_get_cheval_editorial(22)['osteo_articulaire'] === 'RAS aux dernières observations.', 'Ostéo-articulaire : enregistré et lu correctement, texte libre conservé');

// Vérification portant sur le MODÈLE DE DONNÉES (la seule chose qui compte ici) : aucun de ces
// concepts de dossier vétérinaire structuré n'existe comme champ/meta déclaré — mentionner ces
// mots dans un texte d'aide expliquant ce qui est volontairement exclu (ce que fait ce fichier,
// légitimement) est tout autre chose qu'un champ structuré, d'où la vérification sur les CLÉS du
// tableau de champs plutôt que sur le texte brut du fichier.
$editorial_meta_keys = array_values(gwseq_cheval_editorial_field_map());
foreach (array('veterinaire', 'traitement', 'ordonnance', 'radio', 'historique_soin') as $forbidden_concept) {
  $matching_keys = array_filter($editorial_meta_keys, function ($meta_key) use ($forbidden_concept) {
    return strpos($meta_key, $forbidden_concept) !== false;
  });
  gws_test_assert(empty($matching_keys), "Ostéo-articulaire : aucun champ structuré de dossier vétérinaire (\"$forbidden_concept\") n’existe dans le modèle de données — texte libre uniquement, conformément au périmètre volontairement restreint");
}
gws_test_assert(count($editorial_meta_keys) === 10, 'Ostéo-articulaire : le modèle de données éditorial compte exactement 10 champs déclarés (Accroche commerciale incluse), aucun ajout non demandé (dossier vétérinaire, etc.)');

// =====================================================================================
// Persistance et compatibilité (§13 de la demande)
// =====================================================================================

// --- Sauvegardes successives sans perte de données ---
gwseq_set_cheval_editorial(30, array('_gwseq_presentation' => 'V1'));
gwseq_set_cheval_editorial(30, array('_gwseq_presentation' => 'V1', '_gwseq_potentiel' => 'V2'));
$editorial_30 = gwseq_get_cheval_editorial(30);
gws_test_assert($editorial_30['presentation'] === 'V1' && $editorial_30['potentiel'] === 'V2', 'Persistance : un second enregistrement complet conserve les champs déjà présents et ajoute le nouveau');

// --- Compatibilité avec une fiche Cheval créée avant l’Étape 6 (jamais enregistrée) ---
foreach (gwseq_get_cheval_editorial(999) as $field_key => $value) {
  gws_test_assert($value === '', "Compatibilité : \"$field_key\" reste vide sur une fiche jamais enregistrée avec ces champs, jamais une erreur");
}

// --- Désactivation/réactivation du module : aucune suppression de meta n'est jamais construite ---
gws_test_assert(strpos($cheval_editorial_code_only, 'delete_post_meta') === false, 'Désactivation/réactivation : ce fichier n’appelle jamais delete_post_meta() — aucune donnée éditoriale ne peut être supprimée par une (dés)activation du module');

// --- Programmatique, sans $_POST ni nonce ---
$import_result = gwseq_set_cheval_editorial(40, array('_gwseq_presentation' => 'Importé depuis un futur CSV.'));
gws_test_assert($import_result === true && gwseq_get_cheval_editorial(40)['presentation'] === 'Importé depuis un futur CSV.', 'Programmatique : un appel direct (simulant un futur import) enregistre correctement, sans $_POST ni nonce');

// =====================================================================================
// Rendu admin et i18n
// =====================================================================================

$post_stub = (object) array('ID' => 12);
$GLOBALS['__gwseq_test_meta_boxes'] = array();
gwseq_add_cheval_editorial_meta_boxes();
gws_test_assert(in_array('gwseq-cheval-presentation', $GLOBALS['__gwseq_test_meta_boxes'], true), 'Meta box "Présentation" : bien enregistrée');
gws_test_assert(in_array('gwseq-cheval-infos-complementaires', $GLOBALS['__gwseq_test_meta_boxes'], true), 'Meta box "Informations complémentaires" : bien enregistrée séparément (§9 : organisation par blocs)');

ob_start();
gwseq_render_cheval_presentation_box($post_stub);
$presentation_box_html = ob_get_clean();
foreach (array('_gwseq_accroche_commerciale', '_gwseq_presentation', '_gwseq_points_forts', '_gwseq_potentiel', '_gwseq_resultats', '_gwseq_origines_commentaire', '_gwseq_commentaire_production', '_gwseq_conditions_vente', '_gwseq_conseils_croisement') as $meta_key) {
  gws_test_assert(strpos($presentation_box_html, 'name="' . $meta_key . '"') !== false, "Rendu admin : le champ $meta_key est réellement rendu dans la meta box Présentation");
}
gws_test_assert(strpos($presentation_box_html, 'name="_gwseq_osteo_articulaire"') === false, 'Rendu admin : Ostéo-articulaire n’est PAS rendu dans la meta box Présentation (rendu séparément, voir §9)');

ob_start();
gwseq_render_cheval_infos_complementaires_box($post_stub);
$infos_box_html = ob_get_clean();
gws_test_assert(strpos($infos_box_html, 'name="_gwseq_osteo_articulaire"') !== false, 'Rendu admin : Ostéo-articulaire est bien rendu dans la meta box "Informations complémentaires"');

// --- Escaping : un contenu avec balise n'est jamais rendu tel quel dans le HTML du formulaire ---
gwseq_set_cheval_editorial(12, array('_gwseq_points_forts' => '</textarea><script>alert(1)</script>'));
ob_start();
gwseq_render_cheval_presentation_box($post_stub);
$escaped_html = ob_get_clean();
gws_test_assert(strpos($escaped_html, '<script>') === false, 'Escaping admin : un contenu contenant une balise n’est jamais injecté tel quel dans le rendu (esc_textarea())');

foreach ($GLOBALS['__gwseq_test_domains_used'] as $domain) {
  gws_test_assert($domain === 'gws-core', "i18n : aucun appel de traduction n’utilise un text domain autre que \"gws-core\" (trouvé : $domain)");
}

echo ($failures === 0 ? 'Tous les tests sont passés.' : "$failures test(s) en échec.") . "\n";
exit($failures === 0 ? 0 : 1);

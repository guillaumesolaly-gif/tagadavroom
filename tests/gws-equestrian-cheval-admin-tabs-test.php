<?php
/**
 * Vérifie l'ajustement UX post-recette de l'Étape 6 : navigation par onglets dans l'admin Cheval
 * (includes/cheval-admin-tabs.php, assets/cheval-tabs-admin.js, assets/cheval-tabs.css) et la
 * présentation du coefficient de détermination (CD) des indices génétiques à deux décimales.
 *
 * Les onglets sont UNIQUEMENT une couche de présentation (§3 de la demande) : ce fichier vérifie
 * la configuration PHP (seule source de vérité du regroupement onglet -> meta boxes), le
 * chargement conditionnel des assets, et — puisqu'un script exécuté dans un navigateur ne peut pas
 * être exercé par un script PHP autonome — les garanties comportementales du JavaScript par lecture
 * déclarative directe de son code source (même méthodologie déjà utilisée pour cheval-admin.js et
 * repeater-field.js) : aucun déplacement de meta box existante dans le DOM, aucun appel AJAX,
 * aucune sauvegarde autre que celle du bouton natif WordPress, attributs ARIA du pattern
 * tablist/tab/tabpanel, navigation clavier, dégradation silencieuse si sessionStorage est
 * indisponible ou si l'écran n'a pas la structure classique attendue.
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
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }

$GLOBALS['__gwseq_test_domains_used'] = array();
function __($text, $domain = 'default') { $GLOBALS['__gwseq_test_domains_used'][] = $domain; return $text; }

function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {}

$GLOBALS['__gwseq_test_filters'] = array();
function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {
  $GLOBALS['__gwseq_test_filters'][$hook][] = $callback;
}
function gws_test_apply_filters($hook, $value) {
  foreach ($GLOBALS['__gwseq_test_filters'][$hook] ?? array() as $callback) {
    $value = call_user_func($callback, $value);
  }
  return $value;
}

$GLOBALS['__gwseq_test_screen'] = null;
function get_current_screen() { return $GLOBALS['__gwseq_test_screen']; }
$GLOBALS['__gwseq_enqueued'] = array();
function wp_enqueue_style($handle, ...$rest) { $GLOBALS['__gwseq_enqueued'][] = $handle; }
function wp_enqueue_script($handle, ...$rest) { $GLOBALS['__gwseq_enqueued'][] = $handle; }
$GLOBALS['__gwseq_localized'] = array();
function wp_localize_script($handle, $object_name, $data) { $GLOBALS['__gwseq_localized'][$handle] = array('object_name' => $object_name, 'data' => $data); }

define('ABSPATH', __DIR__ . '/');
const GWSEQ_CPT_CHEVAL = 'gwseq_cheval';
define('GWSEQ_MODULE_URL', 'https://example.test/wp-content/plugins/gws-core/modules/gws-equestrian/');
define('GWSEQ_MODULE_VERSION', 'test');
$repo_root = dirname(__DIR__);
$module_dir = $repo_root . '/wp-content/plugins/gws-core/modules/gws-equestrian/';
require $module_dir . 'includes/cheval-admin-tabs.php';

function gws_test_strip_js_comments($source) {
  // Suppression naïve mais suffisante pour ce fichier : retire les commentaires de ligne "//" et
  // de bloc "/* ... */" — le fichier ne contient aucune chaîne littérale contenant ces séquences,
  // vérifié par relecture du fichier source. Évite qu'un texte explicatif dans un commentaire
  // (ex. « jamais form.submit() ») ne fausse une recherche déclarative sur le CODE réel.
  $source = preg_replace('#/\*.*?\*/#s', '', $source);
  $source = preg_replace('#(?<!:)//.*#', '', $source);
  return $source;
}

$tabs_admin_js_source = file_get_contents($module_dir . 'assets/cheval-tabs-admin.js');
$tabs_admin_js_code_only = gws_test_strip_js_comments($tabs_admin_js_source);
$tabs_css_source = file_get_contents($module_dir . 'assets/cheval-tabs.css');
$cheval_pedigree_source = file_get_contents($module_dir . 'includes/cheval-pedigree.php');

// =====================================================================================
// Configuration PHP du regroupement onglet -> meta boxes (§2 de la demande) — seule source de
// vérité, la même que celle transmise au script.
// =====================================================================================

$tabs = gwseq_cheval_admin_tabs_config();
gws_test_assert(count($tabs) === 7, 'Onglets : les 7 onglets attendus sont bien déclarés (Identité, Commercial, Labels, Pedigree, Indices, Médias, Présentation)');

$tabs_by_id = array();
foreach ($tabs as $tab) { $tabs_by_id[$tab['id']] = $tab; }

gws_test_assert($tabs_by_id['identite']['boxes'] === array('gwseq-cheval-identite'), 'Onglet Identité : contient bien la boîte Identité, et uniquement elle');
gws_test_assert($tabs_by_id['commercial']['boxes'] === array('gwseq-cheval-commercialisation'), 'Onglet Commercial : contient bien la boîte Commercialisation');
gws_test_assert($tabs_by_id['labels']['boxes'] === array('gwseq-cheval-labels'), 'Onglet Labels (nouveau lot ANSF) : contient bien la boîte Labels, et uniquement elle');
gws_test_assert(
  $tabs_by_id['pedigree']['boxes'] === array('gwseq-cheval-pedigree', 'gwseq-cheval-production', 'gwseq-cheval-pedigree-preview'),
  'Onglet Pedigree : contient Pedigree, Production (calculée) et l’aperçu développeur, exactement comme demandé'
);
gws_test_assert($tabs_by_id['indices']['boxes'] === array('gwseq-cheval-indices'), 'Onglet Indices : contient bien la boîte Indices (ISO/ICC/IDR/BSO/BCC/BDR y sont tous rendus)');
gws_test_assert(
  $tabs_by_id['medias']['boxes'] === array('gwseq-cheval-media'),
  'Onglet Médias (correctif intégration Photo principale) : contient uniquement la boîte Médias — "postimagediv" n’est plus piloté par le mécanisme générique de visibilité, il est RÉELLEMENT déplacé dans le DOM par le script et hérite de la visibilité de cette boîte en en devenant descendant'
);
gws_test_assert(
  $tabs_by_id['presentation']['boxes'] === array('gwseq-cheval-presentation', 'gwseq-cheval-infos-complementaires'),
  'Onglet Présentation : contient les deux boîtes éditoriales, y compris "Informations complémentaires" (Ostéo-articulaire)'
);

// --- "postimagediv" n'apparaît dans AUCUNE configuration d'onglet (correctif intégration Photo
// principale) : il n'est plus piloté par le mécanisme générique de visibilité par onglet, mais
// réellement déplacé dans le DOM par le script — une seule source de vérité, jamais deux
// mécanismes de gestion de la Featured Image en parallèle. Le Global Horse ID (dev-only) et la
// boîte "Ordre d'affichage" restent eux aussi volontairement rattachés à AUCUN onglet ---
$all_configured_boxes = array();
foreach ($tabs as $tab) { $all_configured_boxes = array_merge($all_configured_boxes, $tab['boxes']); }
foreach (array('postimagediv', 'gwseq-cheval-global-id-dev', 'gwseq-ordre-gwseq_cheval') as $excluded_box_id) {
  gws_test_assert(!in_array($excluded_box_id, $all_configured_boxes, true), "Onglets : \"$excluded_box_id\" n’est rattaché à aucun onglet dans la configuration — reste hors du mécanisme générique de visibilité par onglet");
}

// =====================================================================================
// CORRECTIF RÉGRESSION (onglet Identité vide, deuxième round, §5 de la demande : « éviter deux
// vérités indépendantes ») — chaque meta box gérée par un onglet doit être marquée, dans le HTML
// réellement rendu par WordPress, d'une classe CSS déclarant explicitement son appartenance
// (filtre natif `postbox_classes_{page}_{id}`, appliqué par do_meta_boxes() pour CHAQUE boîte, y
// compris une boîte native comme "postimagediv"). Cette classe est ensuite ce que le script vérifie
// avant de construire quoi que ce soit (filet de sécurité n°1).
// =====================================================================================

foreach ($tabs as $tab) {
  foreach ($tab['boxes'] as $box_id) {
    $hook = 'postbox_classes_' . GWSEQ_CPT_CHEVAL . '_' . $box_id;
    gws_test_assert(
      array_key_exists($hook, $GLOBALS['__gwseq_test_filters']),
      "Marquage : le filtre natif WordPress \"$hook\" est bien enregistré pour rattacher la boîte \"$box_id\" à son onglet"
    );
    gws_test_assert(
      gws_test_apply_filters($hook, array()) === array('gwseq-tab-' . $tab['id']),
      "Marquage : appliquer ce filtre ajoute bien la classe \"gwseq-tab-{$tab['id']}\" (dérivée de la même configuration, jamais une seconde vérité) — jamais une autre valeur"
    );
  }
}

// =====================================================================================
// Chargement conditionnel des assets — uniquement sur l'écran d'édition d'une fiche cheval
// =====================================================================================

$GLOBALS['__gwseq_test_screen'] = null;
$GLOBALS['__gwseq_enqueued'] = array();
gwseq_enqueue_cheval_admin_tabs_assets('edit.php');
gws_test_assert(empty($GLOBALS['__gwseq_enqueued']), 'Assets : rien n’est chargé sur un écran qui n’est pas post.php/post-new.php');

$GLOBALS['__gwseq_test_screen'] = (object) array('post_type' => 'gwseq_prestation');
$GLOBALS['__gwseq_enqueued'] = array();
gwseq_enqueue_cheval_admin_tabs_assets('post.php');
gws_test_assert(empty($GLOBALS['__gwseq_enqueued']), 'Assets : rien n’est chargé sur l’écran d’édition d’un AUTRE post type (ex. Prestation)');

$GLOBALS['__gwseq_test_screen'] = (object) array('post_type' => GWSEQ_CPT_CHEVAL);
$GLOBALS['__gwseq_enqueued'] = array();
$GLOBALS['__gwseq_localized'] = array();
gwseq_enqueue_cheval_admin_tabs_assets('post.php');
gws_test_assert(in_array('gwseq-cheval-tabs', $GLOBALS['__gwseq_enqueued'], true), 'Assets : la feuille de style des onglets est bien chargée sur l’écran Cheval');
gws_test_assert(in_array('gwseq-cheval-tabs-admin', $GLOBALS['__gwseq_enqueued'], true), 'Assets : le script des onglets est bien chargé sur l’écran Cheval');
gws_test_assert(array_key_exists('gwseq-cheval-tabs-admin', $GLOBALS['__gwseq_localized']), 'Assets : la configuration est bien transmise au script via wp_localize_script() (mécanisme natif, jamais un endpoint AJAX)');
gws_test_assert($GLOBALS['__gwseq_localized']['gwseq-cheval-tabs-admin']['object_name'] === 'gwseqChevalTabs', 'Assets : l’objet JS localisé porte bien le nom attendu par le script');
gws_test_assert($GLOBALS['__gwseq_localized']['gwseq-cheval-tabs-admin']['data']['tabs'] === $tabs, 'Assets : la configuration transmise au script est EXACTEMENT celle de gwseq_cheval_admin_tabs_config() — une seule source de vérité');
gws_test_assert(array_key_exists('isDevEnvironment', $GLOBALS['__gwseq_localized']['gwseq-cheval-tabs-admin']['data']), 'Assets (correctif régression) : l’indicateur d’environnement (dev/local) est bien transmis au script, pour le message de secours du filet de sécurité n°2');
gws_test_assert(!empty($GLOBALS['__gwseq_localized']['gwseq-cheval-tabs-admin']['data']['fallbackNotice']), 'Assets (correctif régression) : le texte du message de secours (filet de sécurité n°2) est bien transmis au script, traduit via le text domain du module');

$GLOBALS['__gwseq_test_screen'] = (object) array('post_type' => GWSEQ_CPT_CHEVAL);
$GLOBALS['__gwseq_enqueued'] = array();
gwseq_enqueue_cheval_admin_tabs_assets('post-new.php');
gws_test_assert(in_array('gwseq-cheval-tabs-admin', $GLOBALS['__gwseq_enqueued'], true), 'Assets : également chargés sur l’écran de création d’une nouvelle fiche cheval (post-new.php)');

foreach ($GLOBALS['__gwseq_test_domains_used'] as $domain) {
  gws_test_assert($domain === 'gws-core', "i18n : aucun appel de traduction n’utilise un text domain autre que \"gws-core\" (trouvé : $domain)");
}

// =====================================================================================
// Contexte des meta boxes Production / Aperçu pedigree — CORRECTIF RÉGRESSION (0.12.1) : un
// premier essai de l'ajustement onglets les avait fait passer de 'side' à 'normal' pour rejoindre
// visuellement la colonne principale ; la recette runtime a révélé que ce changement de contexte
// pouvait faire disparaître des meta boxes existantes pour un utilisateur ayant déjà un ordre de
// boîtes enregistré sur cet écran (voir cheval-pedigree.php pour le détail exact du mécanisme
// WordPress en cause). Contexte 'side' restauré, exactement comme avant l'Étape 6 — le
// regroupement sous l'onglet Pedigree reste garanti par gwseq_cheval_admin_tabs_config() et le
// script (identification des boîtes par ID, jamais par position DOM), vérifié par ailleurs par
// tests/gws-equestrian-cheval-admin-tabs-runtime-test.js (exécution réelle du script).
// =====================================================================================

gws_test_assert(
  preg_match("/add_meta_box\\('gwseq-cheval-production'.*'side'/", $cheval_pedigree_source) === 1,
  'Placement (correctif régression) : la meta box "Production" reste enregistrée en contexte "side", exactement comme avant l’Étape 6'
);
gws_test_assert(
  preg_match("/add_meta_box\\('gwseq-cheval-pedigree-preview'.*'side'/", $cheval_pedigree_source) === 1,
  'Placement (correctif régression) : la meta box "Pedigree résolu" (dev-only) reste enregistrée en contexte "side"'
);
gws_test_assert(strpos($cheval_pedigree_source, "'gwseq-cheval-production', __('Production', 'gws-core'), 'gwseq_render_cheval_offspring_box', GWSEQ_CPT_CHEVAL, 'normal'") === false, 'Placement (correctif régression) : la meta box "Production" n’est plus jamais enregistrée en contexte "normal"');

// =====================================================================================
// Garanties comportementales du script (vérification déclarative directe du code source — un
// script exécuté dans un navigateur ne peut pas être exercé par ce script PHP autonome)
// =====================================================================================

// --- §3 : aucune donnée par AJAX, aucun second mécanisme de sauvegarde ---
foreach (array('fetch(', 'XMLHttpRequest', '.ajax(', 'wp.ajax') as $forbidden_pattern) {
  gws_test_assert(strpos($tabs_admin_js_source, $forbidden_pattern) === false, "Aucun AJAX : le script ne contient jamais \"$forbidden_pattern\"");
}
gws_test_assert(strpos($tabs_admin_js_code_only, '.submit()') === false, 'Une seule soumission possible : le script n’appelle jamais form.submit() directement dans le code réellement exécuté (contournerait les gestionnaires natifs de WordPress)');
gws_test_assert(strpos($tabs_admin_js_source, "getElementById('publish')") !== false, 'Bouton rapide (§4) : le script cible bien le vrai bouton natif WordPress (#publish)');
gws_test_assert(strpos($tabs_admin_js_source, 'nativeSubmitButton.click()') !== false, 'Bouton rapide (§4) : le script déclenche un clic PROGRAMMATIQUE sur le bouton natif, jamais un mécanisme de sauvegarde inventé');

// --- §3 : jamais un déplacement de meta box existante dans le DOM — seuls des éléments NOUVEAUX
// (barre d'onglets, boutons, bouton d'enregistrement) sont insérés ; les boîtes elles-mêmes ne
// sont jamais retirées de leur parent d'origine ni réinsérées ailleurs ---
gws_test_assert(strpos($tabs_admin_js_source, 'box.parentNode') === false, 'Aucun déplacement de boîte : le script ne manipule jamais le parent d’une meta box existante');
gws_test_assert(preg_match('/box(es)?\.appendChild|appendChild\(box/', $tabs_admin_js_source) !== 1, 'Aucun déplacement de boîte : aucune meta box existante n’est jamais réinsérée ailleurs dans le DOM (seul son style d’affichage est modifié)');
gws_test_assert(strpos($tabs_admin_js_source, "box.style.display") !== false, 'Affichage/masquage : le script utilise bien style.display (jamais une suppression du DOM) pour piloter la visibilité des boîtes selon l’onglet actif');

// --- Aucune donnée jamais rendue absente du DOM de façon permanente : tout masquage se fait via
// le style, jamais via remove()/removeChild() appliqué à une boîte ---
gws_test_assert(preg_match('/box(es)?\.remove\(\)|removeChild\(box/', $tabs_admin_js_source) !== 1, 'Aucune donnée absente du DOM : une meta box n’est jamais retirée du DOM, seulement masquée visuellement');

// --- CORRECTIF RÉGRESSION (onglet Identité vide en recette) : une meta box laissée REPLIÉE par le
// mécanisme natif WordPress (classe .closed, indépendant de nos onglets) doit être explicitement
// dépliée quand son onglet devient actif, sans quoi son contenu reste invisible malgré un
// style.display de conteneur correctement rétabli (`.postbox.closed .inside { display:none }`
// cible un enfant de la boîte, jamais la boîte elle-même). Vérifié en exécution réelle par
// tests/gws-equestrian-cheval-admin-tabs-runtime-test.js. ---
gws_test_assert(strpos($tabs_admin_js_code_only, "classList.remove('closed')") !== false, 'Correctif régression : le script lève bien le repli natif (.closed) d’une boîte dès que son onglet devient actif');
gws_test_assert(strpos($tabs_admin_js_source, "querySelector('.handlediv')") !== false, 'Correctif régression : le script retrouve bien le bouton natif de repli/dépli pour synchroniser son état ARIA (aria-expanded)');

// =====================================================================================
// CORRECTIF RÉGRESSION (onglet Identité vide, deuxième round) — la classe .closed seule ne
// masque jamais que l'enfant .inside d'une boîte (jamais la boîte elle-même) : ce diagnostic
// initial était incomplet. Le mécanisme réellement responsable d'une boîte ENTIÈREMENT invisible
// (en-tête compris) est la classe `.hide-if-js` posée par WordPress pour une meta box masquée via
// "Screen Options" par préférence utilisateur — une règle CSS potentiellement `!important`, qu'un
// simple `style.display = ''` ne bat jamais. Le script doit donc : lever aussi cette classe,
// vérifier RÉELLEMENT la visibilité (offsetParent), et forcer l'affichage avec la même priorité
// !important si nécessaire — voir tests/gws-equestrian-cheval-admin-tabs-runtime-test.js pour la
// vérification en exécution réelle de ce mécanisme.
// =====================================================================================

gws_test_assert(strpos($tabs_admin_js_code_only, "classList.remove('hide-if-js')") !== false, 'Correctif régression : le script lève bien le masquage "Screen Options" (.hide-if-js) d’une boîte dès que son onglet devient actif — cette classe masque la boîte ENTIÈRE, contrairement à .closed');
gws_test_assert(strpos($tabs_admin_js_source, 'offsetParent') !== false, 'Correctif régression : le script vérifie la visibilité RÉELLE d’une boîte (offsetParent), pas seulement la valeur déclarée de style.display');
gws_test_assert(strpos($tabs_admin_js_source, "setProperty('display', 'block', 'important')") !== false, 'Correctif régression : le script peut forcer l’affichage avec la priorité "important", seule façon de battre une règle !important de la feuille de style native WordPress');

// --- Filet de sécurité n°2 : si une boîte de l’onglet actif reste invisible malgré tout, le
// système d’onglets se désactive intégralement plutôt que de risquer une page vide (§4) ---
gws_test_assert(strpos($tabs_admin_js_code_only, 'function disableTabsFallback') !== false, 'Filet de sécurité n°2 : une fonction dédiée désactive intégralement le système d’onglets en cas d’échec de vérification de visibilité');
gws_test_assert(preg_match('/disableTabsFallback\(\)/', $tabs_admin_js_code_only) === 1, 'Filet de sécurité n°2 : cette fonction est bien appelée depuis la logique d’activation d’un onglet');
gws_test_assert(strpos($tabs_admin_js_source, 'removeChild(wrapper)') !== false, 'Filet de sécurité n°2 : la barre d’onglets injectée est bien retirée du DOM en cas de désactivation (jamais une meta box existante, uniquement notre propre ajout)');
gws_test_assert(strpos($tabs_admin_js_source, 'isDevEnvironment') !== false && strpos($tabs_admin_js_source, 'fallbackNotice') !== false, 'Filet de sécurité n°2 : un message signale le problème en environnement local/développement (§4), jamais un échec silencieux');

// --- Filet de sécurité n°1 : cohérence entre la configuration transmise et le marquage réel du
// DOM (classe gwseq-tab-{id} posée côté PHP) — si elle est absente, aucun onglet n’est construit
// (§5 : jamais deux vérités indépendantes) ---
gws_test_assert(preg_match("/classList\\.contains\\('gwseq-tab-' \\+ tabDef\\.id\\)/", $tabs_admin_js_code_only) === 1, 'Filet de sécurité n°1 : le script vérifie que chaque boîte trouvée par identifiant porte bien la classe posée côté PHP pour ce même onglet');
gws_test_assert(strpos($tabs_admin_js_code_only, 'consistent') !== false, 'Filet de sécurité n°1 : une incohérence détectée empêche la construction de tout onglet (jamais un onglet construit sur une hypothèse non vérifiée)');

// =====================================================================================
// CORRECTIF INTÉGRATION PHOTO PRINCIPALE — un simple masquage/affichage EN PLACE de
// "postimagediv" (comme pour Production/aperçu sous Pedigree) laissait, dans l'onglet Médias, un
// texte renvoyant vers une boîte physiquement ailleurs (colonne latérale), jugé non satisfaisant
// en recette. La boîte native est désormais RÉELLEMENT déplacée dans un emplacement dédié à
// l'intérieur de la boîte Médias — SEULE exception à la règle générale "jamais déplacer une
// boîte" de ce script, explicitement assumée et documentée en tête de fichier.
// =====================================================================================

gws_test_assert(strpos($tabs_admin_js_source, "getElementById('gwseq-cheval-media-photo-principale-slot')") !== false, 'Intégration Photo principale : le script cible bien l’emplacement dédié réservé dans la boîte Médias (cheval-media.php)');
gws_test_assert(strpos($tabs_admin_js_source, "getElementById('postimagediv')") !== false, 'Intégration Photo principale : le script cible bien la vraie boîte native WordPress "postimagediv", jamais un second champ');
gws_test_assert(strpos($tabs_admin_js_code_only, 'photoPrincipaleSlot.appendChild(postimagediv)') !== false, 'Intégration Photo principale : le script déplace réellement le nœud existant (appendChild, jamais un clone ni une recréation) — les gestionnaires wp.media() déjà attachés par WordPress ne sont donc jamais perdus');
gws_test_assert(strpos($tabs_admin_js_code_only, 'postimagedivOriginalParent') !== false && strpos($tabs_admin_js_code_only, 'postimagedivOriginalNextSibling') !== false, 'Intégration Photo principale : la position native d’origine (parent + frère suivant) est mémorisée avant le déplacement');
gws_test_assert(strpos($tabs_admin_js_code_only, 'postimagedivOriginalParent.insertBefore(postimagediv, postimagedivOriginalNextSibling)') !== false, 'Intégration Photo principale : en cas de désactivation du système d’onglets (filet de sécurité n°2), la boîte est restaurée à sa position native exacte, jamais laissée à un endroit qui n’a de sens que si les onglets fonctionnent');

// --- §5 : pattern ARIA tablist/tab/tabpanel, navigation clavier ---
foreach (array("'role', 'tablist'", "'role', 'tab'", "'role', 'tabpanel'", 'aria-selected', 'aria-controls', 'aria-labelledby') as $aria_pattern) {
  gws_test_assert(strpos($tabs_admin_js_source, $aria_pattern) !== false, "ARIA : le script met bien en place l’attribut/la valeur \"$aria_pattern\"");
}
foreach (array('ArrowRight', 'ArrowLeft', 'Home', 'End') as $key) {
  gws_test_assert(strpos($tabs_admin_js_source, "'" . $key . "'") !== false, "Navigation clavier : la touche \"$key\" est bien prise en charge");
}
gws_test_assert(strpos($tabs_admin_js_source, 'tabIndex') !== false, 'Navigation clavier : le script gère bien un tabindex mobile (roving tabindex) entre les onglets');

// --- Dégradation silencieuse : jamais d'erreur si sessionStorage est indisponible, ou si la
// structure attendue de l'écran (#post-body-content / #normal-sortables) est absente ---
gws_test_assert(strpos($tabs_admin_js_source, 'try {') !== false && strpos($tabs_admin_js_source, 'sessionStorage') !== false, 'Robustesse : l’accès à sessionStorage est protégé (try/catch), jamais une erreur bloquante si indisponible');
gws_test_assert(strpos($tabs_admin_js_source, "getElementById('post-body-content')") !== false && strpos($tabs_admin_js_source, "getElementById('normal-sortables')") !== false, 'Robustesse : le script vérifie la présence des conteneurs attendus avant toute manipulation, jamais une erreur si l’écran a une structure inattendue');

// --- CORRECTIF RÉGRESSION BLOQUANTE (0.12.1) : #post-body-content et #normal-sortables sont deux
// enfants DISTINCTS de #post-body sur l'écran classique WordPress (jamais l'un dans l'autre) — un
// insertBefore(wrapper, normalSortables) appelé sur #post-body-content lève donc systématiquement
// une DOMException dans un vrai navigateur (le nœud de référence n'est pas un enfant du nœud
// appelant), ce qui empêchait TOUJOURS l'apparition de la barre d'onglets en recette runtime. La
// barre doit désormais être insérée comme premier enfant de #normal-sortables lui-même — le seul
// élément qui soit réellement un ancêtre direct des boîtes qu'elle pilote. Vérifié en exécution
// réelle par tests/gws-equestrian-cheval-admin-tabs-runtime-test.js (à exécuter via `node`).
gws_test_assert(strpos($tabs_admin_js_code_only, 'postbody.insertBefore(wrapper, normalSortables)') === false, 'Correctif régression : le script n’appelle plus jamais insertBefore sur #post-body-content avec #normal-sortables comme référence (ce n’est pas un ancêtre direct, cela levait systématiquement une DOMException dans un vrai navigateur)');
gws_test_assert(strpos($tabs_admin_js_source, 'normalSortables.insertBefore(wrapper, normalSortables.firstChild)') !== false, 'Correctif régression : la barre d’onglets est bien insérée comme premier enfant de #normal-sortables, son véritable ancêtre DOM direct');
gws_test_assert(strpos($tabs_admin_js_source, 'gwseqChevalTabs') !== false && strpos($tabs_admin_js_source, 'undefined') !== false, 'Robustesse : le script ne fait rien si la configuration localisée est absente (ex. script chargé sur un écran inattendu)');

// --- Une boîte absente de l'écran (ex. l'aperçu développeur, jamais enregistré en production)
// est ignorée sans erreur, jamais un onglet vide n'est affiché ---
gws_test_assert(strpos($tabs_admin_js_source, 'filter(function') !== false, 'Robustesse : les identifiants de boîte qui ne correspondent à aucun élément réel sont filtrés silencieusement (ex. aperçu développeur en production)');
gws_test_assert(strpos($tabs_admin_js_source, 'if (!boxes.length) return;') !== false, 'Robustesse : un onglet qui ne recueille aucune boîte réellement présente n’est jamais affiché');

// --- Réutilisation des classes natives WordPress (.nav-tab-wrapper/.nav-tab) plutôt qu'une
// réinvention complète du style des onglets ---
gws_test_assert(strpos($tabs_admin_js_source, 'nav-tab-wrapper') !== false && preg_match('/[\'"]nav-tab\b/', $tabs_admin_js_source) === 1, 'Style : les classes natives .nav-tab-wrapper/.nav-tab de WordPress sont bien réutilisées pour l’apparence des onglets');

// --- CSS : disposition responsive (§5, écran étroit) ---
gws_test_assert(strpos($tabs_css_source, '@media') !== false, 'Responsive : une règle @media est bien présente pour l’adaptation sur écran étroit');
gws_test_assert(strpos($tabs_css_source, 'flex-wrap') !== false, 'Responsive : la disposition permet un repli des éléments (flex-wrap) sur écran étroit');

// --- CORRECTIF DIAGNOSTIC (le contenu de la Photo principale restait invisible après
// déplacement, malgré un déplacement DOM par ailleurs réussi et vérifié) : WordPress ne prévoit
// jamais qu'un .postbox soit imbriqué dans un autre .postbox — une règle CSS d'administration
// défensive pourrait cibler spécifiquement ce cas. `revert`, scopé au seul emplacement dédié,
// réinitialise chaque élément déplacé à sa valeur display par défaut, sans hypothèse sur
// l'identité exacte d'une éventuelle règle contraire, et sans effet si aucune n'existait
// réellement. Vérifié en exécution réelle (préservation du contenu) par
// tests/gws-equestrian-cheval-admin-tabs-runtime-test.js. ---
gws_test_assert(
  strpos($tabs_css_source, '.gwseq-cheval-media__photo-principale-slot #postimagediv') !== false,
  'Correctif diagnostic : une règle CSS scopée cible bien la boîte Photo principale une fois déplacée dans son emplacement dédié'
);
gws_test_assert(
  preg_match('/display:\s*revert\s*!important/', $tabs_css_source) === 1,
  'Correctif diagnostic : cette règle force bien "display: revert !important", pour contrer toute règle d’administration masquant un .postbox imbriqué dans un autre, quelle qu’en soit l’origine exacte'
);

echo ($failures === 0 ? 'Tous les tests sont passés.' : "$failures test(s) en échec.") . "\n";
exit($failures === 0 ? 0 : 1);

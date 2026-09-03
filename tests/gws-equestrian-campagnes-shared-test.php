<?php
/**
 * Vérifie les briques RÉELLEMENT communes à Pop-in et Sticky bar (includes/campagnes-shared.php) :
 * ciblage de contenus (encodage post_type:post_id, revalidation, quatre modes), dates/fuseau
 * horaire, couleurs canoniques, CTA, texte enrichi minimal (kses), et la fonction d'éligibilité de
 * page. Même méthodologie que le reste de cette suite : on exerce directement les fonctions avec
 * des données à la forme réelle de ce que WordPress leur transmettrait.
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
function sanitize_key($value) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value)); }
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function esc_url($value) { return $value; }
function esc_html__($text, $domain = 'default') { return esc_html($text); }
function esc_attr__($text, $domain = 'default') { return esc_attr($text); }
function esc_html_e($text, $domain = 'default') { echo esc_html__($text, $domain); }
function esc_attr_e($text, $domain = 'default') { echo esc_attr__($text, $domain); }
function __($text, $domain = 'default') { return $text; }
function _n($single, $plural, $number, $domain = 'default') { return $number == 1 ? $single : $plural; }
function selected($a, $b, $echo = true) { $r = $a == $b ? " selected='selected'" : ''; if ($echo) echo $r; return $r; }
function checked($a, $b = true, $echo = true) { $r = $a == $b ? " checked='checked'" : ''; if ($echo) echo $r; return $r; }
function absint($value) { return abs((int) $value); }

// FIDÈLE à la vraie fonction WordPress (wp-includes/formatting.php) : reproduite ici à l'identique
// pour vérifier notre propre usage, jamais une regex maison distincte de celle réellement utilisée
// en production.
function sanitize_hex_color($color) {
  if ('' === $color) return '';
  if (preg_match('|^#([A-Fa-f0-9]{3}){1,2}$|', $color)) return $color;
  return null;
}

// FIDÈLE au comportement réel : filtre les balises/attributs non autorisés, conserve le texte.
function wp_kses($string, $allowed_html) {
  return preg_replace_callback('/<(\/?)([a-zA-Z0-9]+)([^>]*)>/', function ($m) use ($allowed_html) {
    $closing = $m[1] === '/';
    $tag = strtolower($m[2]);
    if (!array_key_exists($tag, $allowed_html)) return '';
    if ($closing) return '</' . $tag . '>';
    $allowed_attrs = $allowed_html[$tag];
    if (empty($allowed_attrs)) return '<' . $tag . '>';
    $attrs_str = '';
    if (preg_match_all('/([a-zA-Z0-9\-]+)\s*=\s*"([^"]*)"/', $m[3], $attr_matches, PREG_SET_ORDER)) {
      foreach ($attr_matches as $am) {
        if (array_key_exists(strtolower($am[1]), $allowed_attrs)) {
          $attrs_str .= ' ' . strtolower($am[1]) . '="' . $am[2] . '"';
        }
      }
    }
    return '<' . $tag . $attrs_str . '>';
  }, (string) $string);
}

function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {}
function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {}

$GLOBALS['__gwseq_test_posts'] = array();
function get_post_type($post_id) { return $GLOBALS['__gwseq_test_posts'][$post_id] ?? false; }
function gws_test_make_post($id, $post_type) { $GLOBALS['__gwseq_test_posts'][$id] = $post_type; }

$GLOBALS['__gwseq_test_timezone'] = 'UTC';
function wp_timezone() { return new DateTimeZone($GLOBALS['__gwseq_test_timezone']); }

$GLOBALS['__gwseq_test_is_front_page'] = false;
function is_front_page() { return $GLOBALS['__gwseq_test_is_front_page']; }

function gws_core_field_sanitize($type, $raw_value) {
  switch ($type) {
    case 'url': return esc_url_raw($raw_value);
    case 'email': return sanitize_email($raw_value);
    case 'checkbox': return $raw_value ? '1' : '';
    case 'text':
    default: return sanitize_text_field($raw_value);
  }
}
function esc_url_raw($value) { $value = trim((string) $value); return $value === '' ? '' : $value; }

define('ABSPATH', __DIR__ . '/');
define('GWS_CORE_DIR', dirname(__DIR__) . '/wp-content/plugins/gws-core/');
define('GWS_CORE_URL', 'https://example.test/wp-content/plugins/gws-core/');
const GWSEQ_CPT_CHEVAL = 'gwseq_cheval';
const GWSEQ_CPT_PRESTATION = 'gwseq_prestation';

$module_dir = GWS_CORE_DIR . 'modules/gws-equestrian/';
require $module_dir . 'includes/campagnes-shared.php';

// =====================================================================================
// Style
// =====================================================================================

gws_test_assert(gwseq_sanitize_campagne_style_mode('custom') === 'custom', 'Style : "custom" conservé');
gws_test_assert(gwseq_sanitize_campagne_style_mode('site') === 'site', 'Style : "site" conservé');
gws_test_assert(gwseq_sanitize_campagne_style_mode('n-importe-quoi') === 'site', 'Style : valeur invalide -> repli sûr sur "site" (valeur par défaut recommandée)');
gws_test_assert(gwseq_sanitize_campagne_style_mode('') === 'site', 'Style : absent -> "site" par défaut');

// =====================================================================================
// Couleurs
// =====================================================================================

gws_test_assert(gwseq_sanitize_campagne_couleur('#1a2b3c') === '#1a2b3c', 'Couleur : hexadécimale valide (6 caractères) conservée');
gws_test_assert(gwseq_sanitize_campagne_couleur('#fff') === '#fff', 'Couleur : hexadécimale valide (3 caractères) conservée');
gws_test_assert(gwseq_sanitize_campagne_couleur('rouge') === '', 'Couleur : nom de couleur CSS non hexadécimal -> vide, jamais stocké tel quel');
gws_test_assert(gwseq_sanitize_campagne_couleur('<script>alert(1)</script>') === '', 'Couleur : payload malveillant -> vide, jamais injecté');
gws_test_assert(gwseq_sanitize_campagne_couleur('') === '', 'Couleur : absente -> vide');

// =====================================================================================
// CTA (partagé Pop-in / Sticky bar)
// =====================================================================================

$cta = gwseq_sanitize_campagne_cta_input(array(
  '_gwseq_popin_cta_active' => '1',
  '_gwseq_popin_cta_libelle' => 'En savoir plus',
  '_gwseq_popin_cta_url' => 'https://example.test/page',
), '_gwseq_popin_');
gws_test_assert($cta === array('active' => '1', 'libelle' => 'En savoir plus', 'url' => 'https://example.test/page'), 'CTA : actif + libellé + URL conservés avec le bon préfixe de champ');

$cta_inactive = gwseq_sanitize_campagne_cta_input(array(), '_gwseq_sticky_bar_');
gws_test_assert($cta_inactive === array('active' => '', 'libelle' => '', 'url' => ''), 'CTA : payload vide -> tout vide, jamais d\'erreur');

// =====================================================================================
// Texte enrichi minimal (gras/italique/lien/liste uniquement)
// =====================================================================================

$texte = gwseq_sanitize_campagne_texte_input('<strong>Important</strong> <script>alert(1)</script> <a href="https://example.test" onclick="steal()">lien</a><ul><li>Un</li></ul>');
gws_test_assert(strpos($texte, '<strong>Important</strong>') !== false, 'Texte : <strong> conservé');
gws_test_assert(strpos($texte, '<script>') === false, 'Texte : <script> retiré (jamais de HTML arbitraire)');
gws_test_assert(strpos($texte, 'onclick') === false, 'Texte : attribut non autorisé (onclick) retiré même sur une balise autorisée');
gws_test_assert(strpos($texte, 'href="https://example.test"') !== false, 'Texte : lien conservé avec son href');
gws_test_assert(strpos($texte, '<ul><li>Un</li></ul>') !== false, 'Texte : liste conservée');

// --- Restriction de la barre d'outils "teeny" (gras/italique/liste/lien uniquement) : scopée via
// un drapeau, jamais un effet global sur un autre usage natif de "teeny" ailleurs dans l'admin ---
$default_teeny_buttons = array('bold', 'italic', 'underline', 'blockquote', 'strikethrough', 'bullist', 'numlist', 'alignleft', 'aligncenter', 'alignright', 'undo', 'redo', 'link', 'unlink', 'fullscreen');
gws_test_assert(gwseq_restrict_campagne_teeny_buttons($default_teeny_buttons) === $default_teeny_buttons, 'Éditeur "teeny" : hors contexte campagne, la barre d\'outils par défaut de WordPress n\'est jamais modifiée');
$GLOBALS['__gwseq_campagne_teeny_editor_active'] = true;
gws_test_assert(gwseq_restrict_campagne_teeny_buttons($default_teeny_buttons) === array('bold', 'italic', 'bullist', 'numlist', 'link', 'unlink'), 'Éditeur "teeny" : réduite aux seuls boutons gras/italique/liste/lien pendant le rendu de l\'éditeur Contenu');
$GLOBALS['__gwseq_campagne_teeny_editor_active'] = false;
gws_test_assert(gwseq_restrict_campagne_teeny_buttons($default_teeny_buttons) === $default_teeny_buttons, 'Éditeur "teeny" : le drapeau est bien remis à faux après le rendu, aucun effet permanent');

// =====================================================================================
// Dates / fuseau horaire (§H) — jamais un calcul naïf sur l'heure serveur
// =====================================================================================

gws_test_assert(gwseq_sanitize_campagne_datetime_input('') === 0, 'Date : champ vide -> 0 (aucune limite), jamais une date epoch réelle');
gws_test_assert(gwseq_sanitize_campagne_datetime_input('n-importe-quoi') === 0, 'Date : valeur incohérente -> 0, jamais une erreur');

$GLOBALS['__gwseq_test_timezone'] = 'Europe/Paris';
$ts_paris = gwseq_sanitize_campagne_datetime_input('2026-06-01T14:00');
$expected_utc = (new DateTime('2026-06-01T14:00', new DateTimeZone('Europe/Paris')))->getTimestamp();
gws_test_assert($ts_paris === $expected_utc, 'Date : interprétée dans le fuseau horaire DU SITE (wp_timezone()), pas l\'heure serveur brute');
$GLOBALS['__gwseq_test_timezone'] = 'UTC';

$ts_utc = gwseq_sanitize_campagne_datetime_input('2026-06-01T14:00');
gws_test_assert($ts_utc === (new DateTime('2026-06-01T14:00', new DateTimeZone('UTC')))->getTimestamp(), 'Date : fuseau UTC, conversion correcte');

gws_test_assert(gwseq_campagne_est_dans_la_fenetre(0, 0) === true, 'Fenêtre : sans aucune date -> toujours éligible (aucune restriction)');
gws_test_assert(gwseq_campagne_est_dans_la_fenetre(2000000000, 0, 1000000000) === false, 'Fenêtre : avant la date de début -> pas encore éligible');
gws_test_assert(gwseq_campagne_est_dans_la_fenetre(0, 1000000000, 2000000000) === false, 'Fenêtre : après la date de fin -> plus éligible');
gws_test_assert(gwseq_campagne_est_dans_la_fenetre(1000000000, 2000000000, 1500000000) === true, 'Fenêtre : entre début et fin -> éligible');
gws_test_assert(gwseq_campagne_est_dans_la_fenetre(1000000000, 2000000000, 1000000000) === true, 'Fenêtre : exactement à la date de début -> éligible (borne incluse)');
gws_test_assert(gwseq_campagne_est_dans_la_fenetre(1000000000, 2000000000, 2000000000) === true, 'Fenêtre : exactement à la date de fin -> éligible (borne incluse)');

// =====================================================================================
// Ciblage de contenus (§H) : encodage post_type:post_id, jamais un simple tableau d'IDs ambigu
// =====================================================================================

gws_test_assert(gwseq_encode_campagne_cible('page', 42) === 'page:42', 'Ciblage : encodage "post_type:id"');
gws_test_assert(gwseq_decode_campagne_cible('gwseq_cheval:7') === array('post_type' => 'gwseq_cheval', 'post_id' => 7), 'Ciblage : décodage correct');
gws_test_assert(gwseq_decode_campagne_cible('invalide') === null, 'Ciblage : chaîne malformée (sans séparateur) -> null, jamais une erreur');
gws_test_assert(gwseq_decode_campagne_cible('page:abc') === null, 'Ciblage : ID non numérique -> null');
gws_test_assert(gwseq_decode_campagne_cible('') === null, 'Ciblage : chaîne vide -> null');

gws_test_make_post(10, 'page');
gws_test_make_post(20, GWSEQ_CPT_CHEVAL);
gws_test_make_post(30, GWSEQ_CPT_PRESTATION);
gws_test_make_post(40, 'post');
gws_test_make_post(50, 'gwseq_membre'); // hors périmètre V1 du ciblage (§H)

$ciblage_include = gwseq_sanitize_campagne_ciblage_input(array(
  'ciblage_mode' => 'include',
  'ciblage_cibles' => array('page:10', GWSEQ_CPT_CHEVAL . ':20', GWSEQ_CPT_PRESTATION . ':30', 'post:40'),
));
gws_test_assert($ciblage_include['mode'] === 'include', 'Ciblage : mode "Certains contenus" conservé');
gws_test_assert(count($ciblage_include['cibles']) === 4, 'Ciblage : les quatre post types autorisés (Pages, Chevaux, Prestations, Actualités) sont bien acceptés ensemble');

// --- Revalidation stricte : jamais confiance dans le post_type déclaré par le payload ---
$ciblage_usurpation = gwseq_sanitize_campagne_ciblage_input(array(
  'ciblage_mode' => 'include',
  'ciblage_cibles' => array('page:20'), // 20 est en réalité un Cheval, pas une Page
));
gws_test_assert($ciblage_usurpation['cibles'] === array(), 'Ciblage : une cible dont le post_type déclaré ne correspond pas au post_type RÉEL est rejetée (jamais confiance dans le payload)');

// --- Post type hors périmètre V1 (Équipe) : jamais accepté, même avec un ID réel ---
$ciblage_hors_perimetre = gwseq_sanitize_campagne_ciblage_input(array(
  'ciblage_mode' => 'include',
  'ciblage_cibles' => array('gwseq_membre:50'),
));
gws_test_assert($ciblage_hors_perimetre['cibles'] === array(), 'Ciblage : Équipe (hors périmètre V1 du ciblage) jamais acceptée, même avec un ID de post réel');

// --- Doublons dédupliqués ---
$ciblage_doublons = gwseq_sanitize_campagne_ciblage_input(array(
  'ciblage_mode' => 'include',
  'ciblage_cibles' => array('page:10', 'page:10'),
));
gws_test_assert($ciblage_doublons['cibles'] === array('page:10'), 'Ciblage : doublons dédupliqués');

// --- Cibles ignorées si le mode ne les utilise pas (Tout le site / Accueil) ---
$ciblage_all = gwseq_sanitize_campagne_ciblage_input(array('ciblage_mode' => 'all', 'ciblage_cibles' => array('page:10')));
gws_test_assert($ciblage_all['cibles'] === array(), 'Ciblage : "Tout le site" -> aucune cible conservée même si le payload en soumet');
$ciblage_front = gwseq_sanitize_campagne_ciblage_input(array('ciblage_mode' => 'front_page', 'ciblage_cibles' => array('page:10')));
gws_test_assert($ciblage_front['cibles'] === array(), 'Ciblage : "Page d\'accueil uniquement" -> aucune cible conservée');

// --- Mode invalide -> repli sûr sur "all" ---
$ciblage_invalide = gwseq_sanitize_campagne_ciblage_input(array('ciblage_mode' => 'n-importe-quoi'));
gws_test_assert($ciblage_invalide['mode'] === 'all', 'Ciblage : mode invalide -> repli sur "Tout le site"');

// =====================================================================================
// Éligibilité de PAGE (jamais le statut/les dates, testés séparément ci-dessus)
// =====================================================================================

$mode_all = array('mode' => 'all', 'cibles' => array());
gws_test_assert(gwseq_campagne_page_est_ciblee($mode_all, 999, false) === true, 'Éligibilité page : "Tout le site" -> toujours vrai');

$mode_front = array('mode' => 'front_page', 'cibles' => array());
gws_test_assert(gwseq_campagne_page_est_ciblee($mode_front, 0, true) === true, 'Éligibilité page : "Page d\'accueil" -> vrai sur la page d\'accueil (détectée via is_front_page(), jamais un ID de page particulier)');
gws_test_assert(gwseq_campagne_page_est_ciblee($mode_front, 10, false) === false, 'Éligibilité page : "Page d\'accueil" -> faux ailleurs');

$mode_include = array('mode' => 'include', 'cibles' => array('page:10', GWSEQ_CPT_CHEVAL . ':20'));
gws_test_assert(gwseq_campagne_page_est_ciblee($mode_include, 10, false) === true, 'Éligibilité page : "Certains contenus" -> vrai sur une page listée');
gws_test_assert(gwseq_campagne_page_est_ciblee($mode_include, 20, false) === true, 'Éligibilité page : "Certains contenus" -> vrai sur un Cheval listé');
gws_test_assert(gwseq_campagne_page_est_ciblee($mode_include, 99, false) === false, 'Éligibilité page : "Certains contenus" -> faux sur un contenu non listé');
gws_test_assert(gwseq_campagne_page_est_ciblee($mode_include, 0, false) === false, 'Éligibilité page : "Certains contenus" -> faux si aucun contenu identifiable (ex. recherche/404)');

$mode_exclude = array('mode' => 'exclude', 'cibles' => array('page:10'));
gws_test_assert(gwseq_campagne_page_est_ciblee($mode_exclude, 10, false) === false, 'Éligibilité page : "Tout sauf certains contenus" -> faux sur un contenu exclu');
gws_test_assert(gwseq_campagne_page_est_ciblee($mode_exclude, 99, false) === true, 'Éligibilité page : "Tout sauf certains contenus" -> vrai ailleurs');
gws_test_assert(gwseq_campagne_page_est_ciblee($mode_exclude, 0, false) === true, 'Éligibilité page : "Tout sauf certains contenus" -> vrai si aucun contenu identifiable (rien n\'est exclu explicitement)');

// =====================================================================================
// Statut (actif/inactif) — DISTINCT du statut de publication WordPress
// =====================================================================================

$statut_options = gwseq_campagne_statut_options();
gws_test_assert(array_key_exists('active', $statut_options) && array_key_exists('inactive', $statut_options), 'Statut : les deux valeurs actif/inactif existent');
gws_test_assert(count($statut_options) === 2, 'Statut : exactement deux valeurs, jamais un troisième état ambigu');

// =====================================================================================
// Conversion timestamp <-> datetime-local (pour le préremplissage des champs de date)
// =====================================================================================

gws_test_assert(gwseq_campagne_timestamp_to_datetime_local(0) === '', 'Conversion date : timestamp 0 -> champ vide');
$GLOBALS['__gwseq_test_timezone'] = 'Europe/Paris';
$roundtrip_ts = gwseq_sanitize_campagne_datetime_input('2026-06-01T14:00');
gws_test_assert(gwseq_campagne_timestamp_to_datetime_local($roundtrip_ts) === '2026-06-01T14:00', 'Conversion date : aller-retour timestamp -> champ conserve la même heure LOCALE (fuseau du site), pas l\'heure UTC brute');
$GLOBALS['__gwseq_test_timezone'] = 'UTC';

echo ($failures === 0 ? 'Tous les tests sont passés.' : "$failures test(s) en échec.") . "\n";
exit($failures === 0 ? 0 : 1);

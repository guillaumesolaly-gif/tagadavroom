<?php
/**
 * Vérifie la couche métier du partage commercial d'un cheval (includes/cheval-share.php) :
 * l'Accroche commerciale (nouveau champ, includes/cheval-editorial.php), la détermination des
 * informations partageables (gwseq_get_horse_shareable_data() — identité, origines, taille/indice,
 * règle statut/prix, vidéos, lien de fiche publique), la composition du message
 * (gwseq_build_horse_share_message()), et les métadonnées Open Graph de la fiche Cheval. Même
 * méthodologie que le reste de cette suite : fonctions pures exercées avec des données réalistes,
 * réutilisant les VRAIS helpers du module (jamais une réimplémentation dans ce fichier).
 *
 * Ne fait pas partie des paquets livrés (gws-core.zip / gws-starter.zip).
 */

$failures = 0;
function gws_test_assert($condition, $label) {
  global $failures;
  if ($condition) { echo "OK   - $label\n"; }
  else { echo "FAIL - $label\n"; $failures++; }
}

// --- Stubs WordPress minimaux (mêmes conventions que le reste de cette suite, notamment
// gws-equestrian-pedigree-logic-test.php dont ce fichier réutilise le pedigree resolver réel) ---
function wp_unslash($value) { return is_array($value) ? array_map('wp_unslash', $value) : (is_string($value) ? stripslashes($value) : $value); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function sanitize_textarea_field($value) {
  $value = (string) $value;
  $value = preg_replace('@<(script|style)[^>]*?>.*?</\1>@si', '', $value);
  return trim(strip_tags($value));
}
function wp_parse_url($url, $component = -1) { return parse_url($url, $component); }
function wp_parse_args($args, $defaults = array()) { return array_merge((array) $defaults, (array) $args); }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value)); }
function esc_url_raw($value) { $value = trim((string) $value); return $value === '' ? '' : $value; }
function esc_url($value) { return $value; }
function absint($value) { return abs((int) $value); }
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function esc_textarea($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function selected($a, $b, $echo = true) { $r = $a == $b ? " selected='selected'" : ''; if ($echo) echo $r; return $r; }
function checked($a, $b = true, $echo = true) { $r = $a == $b ? " checked='checked'" : ''; if ($echo) echo $r; return $r; }
function wp_nonce_field($action, $field) { echo '<input type="hidden" name="' . esc_attr($field) . '" value="stub-nonce">'; }
function wp_json_encode($data, $options = 0, $depth = 512) { return json_encode($data, $options, $depth); }
function remove_accents($text) { return strtr((string) $text, array('é' => 'e', 'è' => 'e', 'ê' => 'e', 'à' => 'a', 'ô' => 'o', 'î' => 'i', 'ç' => 'c', 'É' => 'E')); }
$GLOBALS['__gwseq_test_domains_used'] = array();
function __($text, $domain = 'default') { return $text; }
function _n($single, $plural, $number, $domain = 'default') { return $number == 1 ? $single : $plural; }
function esc_html__($text, $domain = 'default') { return esc_html($text); }
function esc_attr__($text, $domain = 'default') { return esc_attr($text); }
function esc_html_e($text, $domain = 'default') { echo esc_html__($text, $domain); }

$GLOBALS['__gwseq_test_registered_meta'] = array();
function register_post_meta($object_type, $meta_key, $args = array()) { $GLOBALS['__gwseq_test_registered_meta'][$meta_key] = $args; }
function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {}
function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {}
function add_meta_box($id, $title, $callback, $post_type = null, $context = 'advanced', $priority = 'default') {}

$GLOBALS['__gwseq_test_meta'] = array();
function update_post_meta($post_id, $key, $value) { $GLOBALS['__gwseq_test_meta'][$post_id][$key] = wp_unslash($value); return true; }
function get_post_meta($post_id, $key, $single = false) { return $GLOBALS['__gwseq_test_meta'][$post_id][$key] ?? ''; }
function delete_post_meta($post_id, $key) { unset($GLOBALS['__gwseq_test_meta'][$post_id][$key]); return true; }
function metadata_exists($type, $post_id, $key) {
  return array_key_exists($post_id, $GLOBALS['__gwseq_test_meta']) && array_key_exists($key, $GLOBALS['__gwseq_test_meta'][$post_id]);
}

// --- Registre de posts, avec post_password (nécessaire pour la règle de visibilité publique du
// partage — absent des autres fichiers de cette suite, qui n'en avaient jamais eu besoin) ---
$GLOBALS['__gwseq_test_posts'] = array();
function gws_test_make_post($id, $post_type, $title, $status = 'publish', $password = '') {
  $GLOBALS['__gwseq_test_posts'][$id] = array('post_type' => $post_type, 'post_status' => $status, 'post_title' => $title, 'post_password' => $password);
}
function gws_test_make_post_object($id) {
  $p = $GLOBALS['__gwseq_test_posts'][$id];
  return (object) array('ID' => $id, 'post_type' => $p['post_type'], 'post_status' => $p['post_status'], 'post_title' => $p['post_title'], 'post_password' => $p['post_password']);
}
function get_post_type($post_id) { return $GLOBALS['__gwseq_test_posts'][$post_id]['post_type'] ?? false; }
function get_post($post_id) { return isset($GLOBALS['__gwseq_test_posts'][$post_id]) ? gws_test_make_post_object($post_id) : null; }
function get_the_title($post) {
  $id = is_object($post) ? $post->ID : $post;
  return $GLOBALS['__gwseq_test_posts'][$id]['post_title'] ?? '';
}
function get_permalink($post_id) { return 'https://example.test/chevaux/cheval-' . (int) $post_id . '/'; }

$GLOBALS['__gwseq_test_context'] = array('is_singular' => false, 'queried_id' => 0);
function is_singular($post_type = '') { return $GLOBALS['__gwseq_test_context']['is_singular'] === $post_type; }
function get_queried_object_id() { return $GLOBALS['__gwseq_test_context']['queried_id']; }

$GLOBALS['__gwseq_test_attachment_urls'] = array();
function wp_get_attachment_image_url($id, $size = 'thumbnail') { return $GLOBALS['__gwseq_test_attachment_urls'][$id][$size] ?? false; }
$GLOBALS['__gwseq_test_attachment_srcs'] = array();
function wp_get_attachment_image_src($id, $size = 'thumbnail') { return $GLOBALS['__gwseq_test_attachment_srcs'][$id][$size] ?? false; }
$GLOBALS['__gwseq_test_thumbnails'] = array();
function get_post_thumbnail_id($post_id) { return $GLOBALS['__gwseq_test_thumbnails'][$post_id] ?? 0; }

$GLOBALS['__gwseq_test_options'] = array();
function get_option($name, $default = false) {
  return array_key_exists($name, $GLOBALS['__gwseq_test_options']) ? $GLOBALS['__gwseq_test_options'][$name] : $default;
}

define('ABSPATH', __DIR__ . '/');
const GWSEQ_CPT_CHEVAL = 'gwseq_cheval';
define('GWSEQ_MODULE_URL', 'https://example.test/wp-content/plugins/gws-core/modules/gws-equestrian/');
define('GWSEQ_MODULE_VERSION', 'test');

$repo_root = dirname(__DIR__);
$module_dir = $repo_root . '/wp-content/plugins/gws-core/modules/gws-equestrian/';
require $repo_root . '/wp-content/plugins/gws-core/includes/fields.php';
require $module_dir . 'includes/settings.php';
require $module_dir . 'includes/race-referentiel.php';
require $module_dir . 'includes/cheval-fields.php';
require $module_dir . 'includes/cheval-editorial.php';
require $module_dir . 'includes/cheval-indices.php';
require $module_dir . 'includes/cheval-media.php';
require $module_dir . 'includes/pedigree-resolver.php';
require $module_dir . 'includes/cheval-pedigree.php';
require $module_dir . 'includes/cheval-share.php';

function gws_test_make_horse($id, $title, $overrides = array()) {
  gws_test_make_post($id, GWSEQ_CPT_CHEVAL, $title, $overrides['post_status'] ?? 'publish', $overrides['post_password'] ?? '');
  gwseq_set_cheval_identity($id, $overrides['identity'] ?? array());
  if (isset($overrides['commercial'])) {
    $commercial = gwseq_sanitize_cheval_commercial_input($overrides['commercial']);
    update_post_meta($id, '_gwseq_statut_commercial', $commercial['statut_commercial']);
    update_post_meta($id, '_gwseq_prix_mode', $commercial['prix_mode']);
    update_post_meta($id, '_gwseq_prix_fixe', $commercial['prix_fixe']);
    update_post_meta($id, '_gwseq_prix_min', $commercial['prix_min']);
    update_post_meta($id, '_gwseq_prix_max', $commercial['prix_max']);
    update_post_meta($id, '_gwseq_prix_demande_libelle', $commercial['prix_demande_libelle']);
  }
  if (isset($overrides['editorial'])) gwseq_set_cheval_editorial($id, $overrides['editorial']);
  if (isset($overrides['videos'])) gwseq_set_cheval_videos($id, $overrides['videos']);
}

// =====================================================================================
// Accroche commerciale (§3) : champ distinct de Présentation/Description, sanitation cohérente
// avec un texte court/multiligne, aucun fallback, aucune altération des autres champs éditoriaux.
// =====================================================================================

gws_test_make_post(1, GWSEQ_CPT_CHEVAL, 'Jamerose de Felines');
gwseq_set_cheval_editorial(1, array(
  '_gwseq_accroche_commerciale' => "Jument respectueuse et facile,\navec du sang.",
  '_gwseq_presentation' => 'Présentation longue et détaillée de la jument.',
));
$editorial_1 = gwseq_get_cheval_editorial(1);
gws_test_assert(strpos($editorial_1['accroche_commerciale'], 'Jument respectueuse') !== false, 'Accroche : enregistrée et relue correctement');
gws_test_assert(strpos($editorial_1['accroche_commerciale'], "\n") !== false, 'Accroche : multiligne préservée (texte court mais pas forcément une seule ligne)');
gws_test_assert($editorial_1['presentation'] === 'Présentation longue et détaillée de la jument.', 'Accroche : n’altère jamais le champ Présentation/Description, bien distinct');
gws_test_assert(strpos($editorial_1['accroche_commerciale'], '<') === false, 'Accroche : aucun HTML, texte simple uniquement');

gwseq_set_cheval_editorial(1, array('_gwseq_accroche_commerciale' => '<strong>test</strong> <script>x()</script>'));
$editorial_1_html = gwseq_get_cheval_editorial(1);
gws_test_assert(strpos($editorial_1_html['accroche_commerciale'], '<') === false, 'Accroche : les balises HTML sont retirées à la sanitation (sanitize_textarea_field)');

gws_test_make_post(2, GWSEQ_CPT_CHEVAL, 'Cheval Sans Accroche');
gwseq_set_cheval_editorial(2, array());
gws_test_assert(gwseq_get_cheval_editorial(2)['accroche_commerciale'] === '', 'Accroche : absente par défaut, aucun contenu généré à sa place (§2/§3 — aucun fallback)');

// =====================================================================================
// gwseq_get_horse_shareable_data() — identité, origines, taille/indice, statut/prix, accroche
// =====================================================================================

// --- Cheval très renseigné : tous les items présents ---
gws_test_make_horse(10, 'Jamerose de Felines', array(
  'identity' => array('_gwseq_sexe' => 'female', '_gwseq_race' => 'SF', '_gwseq_annee_naissance' => (int) gmdate('Y') - 7, '_gwseq_taille_cm' => '168'),
  'commercial' => array('_gwseq_statut_commercial' => 'for_sale', '_gwseq_prix_mode' => 'fixed', '_gwseq_prix_fixe' => '25000'),
  'editorial' => array('_gwseq_accroche_commerciale' => 'Jument respectueuse et facile.'),
  'videos' => array(array('url' => 'https://example.test/v1', 'titre' => 'Allures à 3 ans'), array('url' => 'https://example.test/v2', 'titre' => '')),
));
gwseq_set_cheval_sport_indice(10, 'iso', array('valeur' => '135', 'annee' => (int) gmdate('Y') - 1));
// Origines (§8) : Père (externe, "Untouchable") × Père de la MÈRE ("damsire") — la mère elle-même
// est une fiche GWS ("La Mère", #11) dont le père est l'externe "Kannan".
gwseq_set_horse_parent(10, 'father', array('mode' => 'external', 'external' => array('name' => 'Untouchable')));
gws_test_make_post(11, GWSEQ_CPT_CHEVAL, 'La Mère');
gwseq_set_horse_parent(11, 'father', array('mode' => 'external', 'external' => array('name' => 'Kannan')));
gwseq_set_horse_parent(10, 'mother', array('mode' => 'gws', 'horse_id' => 11));

$shareable_10 = gwseq_get_horse_shareable_data(10);
gws_test_assert($shareable_10['nom'] === 'Jamerose de Felines', 'Shareable : nom brut de la fiche');
gws_test_assert($shareable_10['nom_affiche'] === 'JAMEROSE DE FELINES', 'Shareable : nom affiché en majuscules sans accents (convention déjà en place)');
gws_test_assert($shareable_10['items']['identite']['label'] === 'Jument Selle Français — 7 ans', 'Shareable : identité composée (vocabulaire commercial "Jument", race, âge)');
gws_test_assert($shareable_10['items']['identite']['default_checked'] === true, 'Shareable : identité présélectionnée par défaut (§9)');
gws_test_assert($shareable_10['items']['origines']['label'] === 'Par UNTOUCHABLE × KANNAN', 'Shareable : origines = Père (externe) × Père de la mère (GWS), noms en majuscules');
gws_test_assert($shareable_10['items']['taille_indice']['label'] === '1,68 m • ISO 135', 'Shareable : taille (virgule française) + UN SEUL indice sportif (priorité ISO)');
gws_test_assert(array_key_exists('prix', $shareable_10['items']) && $shareable_10['items']['prix']['label'] === 'À vendre — 25 000 €', 'Shareable : prix proposé car statut "À vendre" ET prix renseigné');
gws_test_assert($shareable_10['items']['prix']['default_checked'] === false, 'Shareable : le prix n’est JAMAIS présélectionné par défaut (§9, prudence commerciale)');
gws_test_assert($shareable_10['items']['accroche']['label'] === 'Jument respectueuse et facile.', 'Shareable : accroche présente reprise telle quelle');
gws_test_assert($shareable_10['items']['accroche']['default_checked'] === true, 'Shareable : accroche présélectionnée par défaut lorsqu’elle existe');
gws_test_assert(count($shareable_10['videos']) === 2, 'Shareable : les deux vidéos réellement présentes sont proposées');
gws_test_assert($shareable_10['videos'][0]['label'] === '🎥 Allures à 3 ans', 'Shareable : vidéo AVEC titre -> "🎥 {titre}"');
gws_test_assert($shareable_10['videos'][1]['label'] === '🎥 Vidéo', 'Shareable : vidéo SANS titre -> "🎥 Vidéo" (jamais un titre inventé, §11)');
gws_test_assert($shareable_10['videos'][0]['default_checked'] === true && $shareable_10['videos'][1]['default_checked'] === true, 'Shareable : les deux premières vidéos sont présélectionnées (2 <= présélection par défaut)');
gws_test_assert($shareable_10['fiche_url'] === 'https://example.test/chevaux/cheval-10/', 'Shareable : lien de fiche complète présent (cheval publié, non protégé)');
gws_test_assert($shareable_10['fiche_default_checked'] === true, 'Shareable : fiche complète présélectionnée par défaut lorsqu’un lien public existe (§9/§13)');

// --- Cheval peu renseigné : uniquement ce qui existe réellement, rien d'autre (§2) ---
gws_test_make_horse(20, 'Cheval Anonyme');
$shareable_20 = gwseq_get_horse_shareable_data(20);
gws_test_assert($shareable_20['items'] === array(), 'Shareable : cheval peu renseigné -> aucun item structuré proposé, jamais une case vide');
gws_test_assert($shareable_20['videos'] === array(), 'Shareable : aucune vidéo -> tableau vide');
gws_test_assert($shareable_20['photo_url'] === '', 'Shareable : aucune photo -> chaîne vide, jamais une image de remplacement fabriquée');

// --- Trois vidéos ou plus : présélection des deux premières, mais toutes sélectionnables (§11) ---
gws_test_make_horse(21, 'Cheval Multi-Videos', array('videos' => array(
  array('url' => 'https://example.test/v1', 'titre' => 'Une'),
  array('url' => 'https://example.test/v2', 'titre' => 'Deux'),
  array('url' => 'https://example.test/v3', 'titre' => 'Trois'),
)));
$shareable_21 = gwseq_get_horse_shareable_data(21);
gws_test_assert(count($shareable_21['videos']) === 3, 'Shareable : trois vidéos, toutes proposées (aucune limite artificielle de sélection)');
gws_test_assert($shareable_21['videos'][2]['default_checked'] === false, 'Shareable : la troisième vidéo n’est PAS présélectionnée par défaut, mais reste sélectionnable');

// =====================================================================================
// Règle statut commercial / prix (§10) — le prix ne doit JAMAIS être proposé hors contexte actif
// =====================================================================================

gws_test_make_horse(30, 'Cheval Non Propose', array('commercial' => array('_gwseq_statut_commercial' => 'not_offered', '_gwseq_prix_mode' => 'fixed', '_gwseq_prix_fixe' => '30000')));
gws_test_assert(!array_key_exists('prix', gwseq_get_horse_shareable_data(30)['items']), 'Règle prix/statut : "Non proposé" -> le prix n’est JAMAIS proposé au partage, même techniquement enregistré (§10, cas explicitement cité par la demande)');

gws_test_make_horse(31, 'Cheval Vendu', array('commercial' => array('_gwseq_statut_commercial' => 'sold', '_gwseq_prix_mode' => 'fixed', '_gwseq_prix_fixe' => '30000')));
gws_test_assert(!array_key_exists('prix', gwseq_get_horse_shareable_data(31)['items']), 'Règle prix/statut : "Vendu" -> le prix n’est pas proposé au partage (contexte commercial non actif)');

gws_test_make_horse(32, 'Cheval Reserve', array('commercial' => array('_gwseq_statut_commercial' => 'reserved', '_gwseq_prix_mode' => 'fixed', '_gwseq_prix_fixe' => '30000')));
gws_test_assert(gwseq_get_horse_shareable_data(32)['items']['prix']['label'] === 'Réservé — 30 000 €', 'Règle prix/statut : "Réservé" -> le prix reste proposable (contexte commercial encore actif)');

gws_test_make_horse(33, 'Cheval A Vendre Sans Prix', array('commercial' => array('_gwseq_statut_commercial' => 'for_sale', '_gwseq_prix_mode' => 'on_request', '_gwseq_prix_demande_libelle' => '')));
gws_test_assert(!array_key_exists('prix', gwseq_get_horse_shareable_data(33)['items']), 'Règle prix/statut : statut actif mais aucun prix exploitable -> aucun item prix (jamais une ligne vide)');

// =====================================================================================
// Public / non public (§13/§20) — lien de fiche complète
// =====================================================================================

gws_test_make_horse(40, 'Cheval Brouillon', array('post_status' => 'draft'));
gws_test_assert(gwseq_horse_is_publicly_viewable(40) === false, 'Visibilité publique : un brouillon n’est jamais publiquement visible');
gws_test_assert(gwseq_get_horse_shareable_data(40)['fiche_url'] === '', 'Partage : aucun lien de fiche pour un cheval non publiquement visible (jamais de brouillon exposé, §13/§20)');

gws_test_make_horse(41, 'Cheval Protege', array('post_password' => 'secret'));
gws_test_assert(gwseq_horse_is_publicly_viewable(41) === false, 'Visibilité publique : un contenu protégé par mot de passe n’est jamais publiquement visible');
gws_test_assert(gwseq_get_horse_shareable_data(41)['fiche_url'] === '', 'Partage : aucun lien de fiche pour un cheval protégé par mot de passe');

gws_test_make_horse(42, 'Cheval Public', array('post_status' => 'publish'));
gws_test_assert(gwseq_horse_is_publicly_viewable(42) === true, 'Visibilité publique : une fiche publiée sans mot de passe est publiquement visible');
gws_test_assert(gwseq_get_horse_shareable_data(42)['fiche_url'] !== '', 'Partage : lien de fiche présent pour un cheval réellement public');

// =====================================================================================
// gwseq_build_horse_share_message() — composition du message final (§14/§16)
// =====================================================================================

$message_full = gwseq_build_horse_share_message($shareable_10, array(
  'items' => array('identite', 'origines', 'taille_indice', 'accroche'),
  'videos' => array(0, 1),
  'fiche' => true,
  'message_personnel' => '',
));
gws_test_assert(strpos($message_full, "JAMEROSE DE FELINES\nJument Selle Français — 7 ans\nPar UNTOUCHABLE × KANNAN\n1,68 m • ISO 135") === 0, 'Message : bloc identité (nom + lignes structurées sélectionnées) en tête, sans ligne vide entre elles');
gws_test_assert(strpos($message_full, "\n\nJument respectueuse et facile.") !== false, 'Message : accroche sélectionnée en paragraphe séparé (ligne vide avant)');
gws_test_assert(strpos($message_full, '🎥 Allures à 3 ans : https://example.test/v1') !== false, 'Message : ligne vidéo "{libellé} : {url}"');
gws_test_assert(strpos($message_full, '🎥 Vidéo : https://example.test/v2') !== false, 'Message : deuxième vidéo (sans titre) bien incluse également');
gws_test_assert(strpos($message_full, 'Fiche complète, photos et pedigree :') !== false, 'Message : intitulé fixe du lien de fiche');
gws_test_assert(substr($message_full, -strlen($shareable_10['fiche_url'])) === $shareable_10['fiche_url'], 'Message : URL de la fiche complète en toute fin de message');
gws_test_assert(strpos($message_full, 'À vendre') === false, 'Message : le prix n’apparaît pas s’il n’a pas été sélectionné, même s’il existe');

$message_with_price = gwseq_build_horse_share_message($shareable_10, array('items' => array('prix'), 'videos' => array(), 'fiche' => false));
gws_test_assert(strpos($message_with_price, 'À vendre — 25 000 €') !== false, 'Message : ligne commerciale adaptée intégrée lorsque le prix est explicitement sélectionné');
gws_test_assert(strpos($message_with_price, 'Fiche complète') === false, 'Message : aucun lien de fiche si "fiche" non sélectionné');

$message_personnel = gwseq_build_horse_share_message($shareable_10, array('items' => array(), 'videos' => array(), 'fiche' => false, 'message_personnel' => "Bonjour Pierre, je pensais à cette jument suite à notre échange :"));
gws_test_assert(strpos($message_personnel, "Bonjour Pierre, je pensais à cette jument suite à notre échange :\n\nJAMEROSE DE FELINES") === 0, 'Message : message personnel en tête, séparé par une ligne vide, avant le nom du cheval');

$message_minimal = gwseq_build_horse_share_message(gwseq_get_horse_shareable_data(20), array('items' => array(), 'videos' => array(), 'fiche' => false));
gws_test_assert(trim($message_minimal) === 'CHEVAL ANONYME', 'Message : aucune sélection -> seul le nom du cheval demeure (toujours inclus, §14)');

// =====================================================================================
// Correctif de recette — « le prix apparaît dans l'aperçu alors qu'il n'est pas sélectionné ».
// La cause racine identifiée était CÔTÉ CLIENT (une réponse AJAX obsolète pouvait écraser
// l'aperçu à jour, voir assets/cheval-share-admin.js et le test runtime dédié) : ces assertions
// prouvent ici que gwseq_build_horse_share_message() lui-même, isolément, a TOUJOURS respecté la
// sélection transmise — le "coché puis décoché" round-trip et les autres blocs sélectionnables,
// pas seulement le prix.
// =====================================================================================

$message_prix_toggle_on = gwseq_build_horse_share_message($shareable_10, array('items' => array('prix'), 'videos' => array(), 'fiche' => false));
gws_test_assert(strpos($message_prix_toggle_on, '25 000') !== false, 'Round-trip prix : sélectionné -> présent dans le message');
$message_prix_toggle_off = gwseq_build_horse_share_message($shareable_10, array('items' => array(), 'videos' => array(), 'fiche' => false));
gws_test_assert(strpos($message_prix_toggle_off, '25 000') === false, 'Round-trip prix : re-décoché juste après -> disparaît immédiatement du message, aucune trace résiduelle de la sélection précédente');

// --- Même principe vérifié pour chaque AUTRE bloc sélectionnable (§1 : "vérifier qu'il n'existe
// pas le même problème avec les autres informations sélectionnables") — jamais présent si non
// sélectionné, jamais absent si sélectionné, indépendamment des autres blocs. ---
foreach (array(
  'identite' => 'Jument Selle Français',
  'origines' => 'UNTOUCHABLE',
  'taille_indice' => '1,68 m',
  'accroche' => 'Jument respectueuse',
) as $item_key => $expected_fragment) {
  $with = gwseq_build_horse_share_message($shareable_10, array('items' => array($item_key), 'videos' => array(), 'fiche' => false));
  gws_test_assert(strpos($with, $expected_fragment) !== false, "Round-trip \"$item_key\" : sélectionné -> présent dans le message");
  $without = gwseq_build_horse_share_message($shareable_10, array('items' => array(), 'videos' => array(), 'fiche' => false));
  gws_test_assert(strpos($without, $expected_fragment) === false, "Round-trip \"$item_key\" : non sélectionné -> absent du message, même s'il est disponible pour ce cheval");
}

// --- Vidéos : même principe (présentes seulement si leur index précis est sélectionné) ---
$with_video_0 = gwseq_build_horse_share_message($shareable_10, array('items' => array(), 'videos' => array(0), 'fiche' => false));
gws_test_assert(strpos($with_video_0, 'Allures à 3 ans') !== false, 'Round-trip vidéo : index sélectionné -> présent');
$without_video_0 = gwseq_build_horse_share_message($shareable_10, array('items' => array(), 'videos' => array(), 'fiche' => false));
gws_test_assert(strpos($without_video_0, 'Allures à 3 ans') === false, 'Round-trip vidéo : index non sélectionné -> absent, même s\'il est disponible pour ce cheval');

// --- Fiche complète : même principe ---
$with_fiche = gwseq_build_horse_share_message($shareable_10, array('items' => array(), 'videos' => array(), 'fiche' => true));
gws_test_assert(strpos($with_fiche, $shareable_10['fiche_url']) !== false, 'Round-trip fiche complète : sélectionnée -> lien présent');
$without_fiche = gwseq_build_horse_share_message($shareable_10, array('items' => array(), 'videos' => array(), 'fiche' => false));
gws_test_assert(strpos($without_fiche, $shareable_10['fiche_url']) === false, 'Round-trip fiche complète : non sélectionnée -> lien absent');

$message_no_selection_but_content = gwseq_build_horse_share_message($shareable_10, array('items' => array('inexistant'), 'videos' => array(999), 'fiche' => true));
gws_test_assert(strpos($message_no_selection_but_content, 'Fiche complète') !== false, 'Message : une clé d’item ou un index de vidéo invalide est simplement ignoré, jamais une erreur');

// =====================================================================================
// Open Graph (§19) — jamais de prix, jamais de second système SEO si un plugin est actif
// =====================================================================================

gws_test_assert(gwseq_horse_og_description($shareable_10) === 'Jument Selle Français — 7 ans — Par UNTOUCHABLE × KANNAN — Jument respectueuse et facile.', 'Open Graph : description = identité + origines + accroche, jamais le prix');
gws_test_assert(strpos(gwseq_horse_og_description($shareable_10), '25 000') === false, 'Open Graph : le prix n’apparaît JAMAIS dans la description, même sélectionnable par ailleurs');

$long_shareable = $shareable_10;
$long_shareable['items']['accroche']['label'] = str_repeat('Une très longue accroche commerciale bien détaillée. ', 10);
$long_description = gwseq_horse_og_description($long_shareable);
gws_test_assert(mb_strlen($long_description) <= GWSEQ_HORSE_OG_DESCRIPTION_MAX_LENGTH + 1, 'Open Graph : description tronquée proprement à une longueur raisonnable');

ob_start();
$GLOBALS['__gwseq_test_context'] = array('is_singular' => GWSEQ_CPT_CHEVAL, 'queried_id' => 10);
$GLOBALS['__gwseq_test_thumbnails'][10] = 555;
$GLOBALS['__gwseq_test_attachment_srcs'][555]['medium_large'] = array('https://example.test/photo-derivee.jpg', 800, 600);
gwseq_render_horse_og_meta();
$og_html = ob_get_clean();
gws_test_assert(strpos($og_html, 'og:title" content="Jamerose de Felines"') !== false, 'Open Graph : og:title émis pour un cheval public');
gws_test_assert(strpos($og_html, 'og:image" content="https://example.test/photo-derivee.jpg"') !== false, 'Open Graph : og:image utilise une DÉRIVÉE adaptée (medium_large), jamais l’original');
gws_test_assert(strpos($og_html, 'og:image:width" content="800"') !== false && strpos($og_html, 'og:image:height" content="600"') !== false, 'Open Graph : dimensions de l’image incluses');
gws_test_assert(strpos($og_html, 'og:url" content="https://example.test/chevaux/cheval-10/"') !== false, 'Open Graph : og:url cohérent avec le permalien réel');
gws_test_assert(strpos($og_html, '25 000') === false && strpos($og_html, 'gwseq_prix') === false, 'Open Graph : aucune donnée commerciale dans les balises émises');

ob_start();
$GLOBALS['__gwseq_test_context'] = array('is_singular' => GWSEQ_CPT_CHEVAL, 'queried_id' => 40);
gwseq_render_horse_og_meta();
$og_html_draft = ob_get_clean();
gws_test_assert($og_html_draft === '', 'Open Graph : rien n’est émis pour un cheval non publiquement visible (brouillon), même en prévisualisation interne');

ob_start();
$GLOBALS['__gwseq_test_context'] = array('is_singular' => false, 'queried_id' => 10);
gwseq_render_horse_og_meta();
gws_test_assert(ob_get_clean() === '', 'Open Graph : rien n’est émis hors d’une page singulière de cheval');

// --- Aucun second système SEO concurrent : si un plugin SEO est détecté, rien n'est émis ---
define('WPSEO_VERSION', '99.0');
ob_start();
$GLOBALS['__gwseq_test_context'] = array('is_singular' => GWSEQ_CPT_CHEVAL, 'queried_id' => 10);
gwseq_render_horse_og_meta();
gws_test_assert(ob_get_clean() === '', 'Open Graph : rien n’est émis si un plugin SEO tiers est détecté actif (jamais de second système concurrent, §19)');

echo ($failures === 0 ? 'Tous les tests sont passés.' : "$failures test(s) en échec.") . "\n";
exit($failures === 0 ? 0 : 1);

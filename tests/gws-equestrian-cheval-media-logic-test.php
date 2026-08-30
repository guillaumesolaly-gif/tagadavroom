<?php
/**
 * Vérifie les médias de la fiche Cheval (Étape 6, §4-6 de la demande) : galerie photos (jusqu'à 9
 * attachment IDs, ordonnés, sans doublon, indépendante de la photo principale/image à la une
 * native) et vidéos (URL + titre facultatif, ordonnées, jusqu'à 10, sanitation stricte de l'URL),
 * réutilisant le composant répétable générique de l'Étape 2 pour les vidéos avec une sanitation
 * dédiée. Chemin programmatique sans $_POST ni nonce (même méthodologie que le pedigree).
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
function esc_url_raw($value) {
  $value = trim((string) $value);
  if ($value === '') return '';
  // Approximation suffisante d'esc_url_raw() pour ce test : retire les schémas dangereux évidents,
  // conserve le reste tel quel (la validation stricte http/https réelle est testée séparément via
  // gwseq_sanitize_cheval_video_url(), qui s'appuie sur wp_parse_url() ci-dessous).
  if (preg_match('/^\s*javascript\s*:/i', $value)) return '';
  return $value;
}
function wp_parse_url($url, $component = -1) {
  $parts = parse_url((string) $url);
  if ($component === -1) return $parts;
  $map = array(PHP_URL_SCHEME => 'scheme', PHP_URL_HOST => 'host', PHP_URL_PATH => 'path');
  $key = $map[$component] ?? null;
  return $key !== null ? ($parts[$key] ?? null) : null;
}
function absint($value) { return abs((int) $value); }
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function esc_url($value) { return $value; }
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
function add_image_size($name, $width, $height, $crop = false) { $GLOBALS['__gwseq_test_image_sizes'][$name] = array($width, $height, $crop); }

$GLOBALS['__gwseq_test_meta'] = array();
function update_post_meta($post_id, $key, $value) { $GLOBALS['__gwseq_test_meta'][$post_id][$key] = $value; return true; }
function get_post_meta($post_id, $key, $single = false) { return $GLOBALS['__gwseq_test_meta'][$post_id][$key] ?? ''; }

$GLOBALS['__gwseq_test_security'] = array('nonce_valid' => true, 'can_edit' => true, 'is_revision' => false);
function wp_verify_nonce($nonce, $action) { return $GLOBALS['__gwseq_test_security']['nonce_valid']; }
function current_user_can($cap, $post_id = null) { return $GLOBALS['__gwseq_test_security']['can_edit']; }
function wp_is_post_revision($post_id) { return $GLOBALS['__gwseq_test_security']['is_revision']; }

// --- Médiathèque : registre en mémoire des attachements "images" existants, pilotable par le
// test — mêmes garanties que la vraie wp_attachment_is_image() (un ID inconnu ou non-image est
// rejeté) ---
$GLOBALS['__gwseq_test_attachments'] = array();
function gws_test_register_attachment($id, $is_image = true) { $GLOBALS['__gwseq_test_attachments'][$id] = $is_image; }
function wp_attachment_is_image($id) { return !empty($GLOBALS['__gwseq_test_attachments'][$id]); }
function wp_get_attachment_image_url($id, $size = 'thumbnail') { return $GLOBALS['__gwseq_test_attachments'][$id] ? "https://example.test/uploads/$id-$size.jpg" : false; }
function get_post_thumbnail_id($post_id) { return $GLOBALS['__gwseq_test_thumbnail'][$post_id] ?? 0; }
function get_current_screen() { return $GLOBALS['__gwseq_test_screen'] ?? null; }
function wp_enqueue_media() { $GLOBALS['__gwseq_enqueued'][] = 'media'; }
function wp_enqueue_style($handle, ...$rest) { $GLOBALS['__gwseq_enqueued'][] = $handle; }
function wp_enqueue_script($handle, ...$rest) { $GLOBALS['__gwseq_enqueued'][] = $handle; }

define('ABSPATH', __DIR__ . '/');
const GWSEQ_CPT_CHEVAL = 'gwseq_cheval';
define('GWSEQ_MODULE_URL', 'https://example.test/wp-content/plugins/gws-core/modules/gws-equestrian/');
define('GWSEQ_MODULE_VERSION', 'test');
$repo_root = dirname(__DIR__);
$module_dir = $repo_root . '/wp-content/plugins/gws-core/modules/gws-equestrian/';
require $repo_root . '/wp-content/plugins/gws-core/includes/fields.php';
require $module_dir . 'includes/repeater-field.php';
require $module_dir . 'includes/cheval-fields.php';
require $module_dir . 'includes/cheval-media.php';

$cheval_media_source = file_get_contents($module_dir . 'includes/cheval-media.php');

gws_test_register_attachment(101);
gws_test_register_attachment(102);
gws_test_register_attachment(103);
gws_test_register_attachment(104);
gws_test_register_attachment(999, false); // existe, mais n'est pas une image

// =====================================================================================
// Galerie — §5 de la demande
// =====================================================================================

// --- Ajout : plusieurs images valides conservées, dans l'ordre fourni ---
gwseq_set_cheval_galerie(10, array('101', '102', '103'));
gws_test_assert(gwseq_get_cheval_galerie(10) === array(101, 102, 103), 'Galerie : ajout de plusieurs images, ordre de saisie conservé, IDs castés en entiers');

// --- Suppression : un retrait dans la liste soumise retire bien l'image, sans toucher aux autres ---
gwseq_set_cheval_galerie(10, array('101', '103'));
gws_test_assert(gwseq_get_cheval_galerie(10) === array(101, 103), 'Galerie : retirer une image de la liste soumise la retire bien, les autres restent inchangées');

// --- Ordre : une resoumission dans un ordre différent est bien prise en compte ---
gwseq_set_cheval_galerie(10, array('103', '101'));
gws_test_assert(gwseq_get_cheval_galerie(10) === array(103, 101), 'Galerie : l’ordre soumis est bien celui conservé (aucun tri automatique par ID)');

// --- Maximum 9 images complémentaires : une 10e est silencieusement ignorée, jamais une erreur ---
$dix_images = array();
for ($i = 1; $i <= 10; $i++) {
  gws_test_register_attachment(200 + $i);
  $dix_images[] = (string) (200 + $i);
}
gwseq_set_cheval_galerie(11, $dix_images);
$galerie_11 = gwseq_get_cheval_galerie(11);
gws_test_assert(count($galerie_11) === GWSEQ_CHEVAL_GALERIE_MAX, 'Galerie : bornée à ' . GWSEQ_CHEVAL_GALERIE_MAX . ' images maximum, même si davantage sont soumises');
gws_test_assert($galerie_11 === array(201, 202, 203, 204, 205, 206, 207, 208, 209), 'Galerie : ce sont bien les 9 premières images soumises (dans l’ordre) qui sont conservées');

// --- Attachment IDs uniquement : jamais une URL, jamais un tableau imbriqué accepté comme ID ---
gwseq_set_cheval_galerie(12, array('101', 'https://example.test/photo.jpg', array('102')));
gws_test_assert(gwseq_get_cheval_galerie(12) === array(101), 'Galerie : seuls les identifiants numériques valides sont conservés — une URL ou une valeur mal formée est ignorée, jamais convertie ni source d’erreur');

// --- Un ID inexistant ou qui n'est pas une image est rejeté ---
gwseq_set_cheval_galerie(13, array('101', '999', '88888'));
gws_test_assert(gwseq_get_cheval_galerie(13) === array(101), 'Galerie : un ID qui n’est pas une image ou qui n’existe pas est rejeté, jamais stocké');

// --- Aucun doublon : un même ID soumis deux fois n'apparaît qu'une seule fois ---
gwseq_set_cheval_galerie(14, array('101', '102', '101'));
gws_test_assert(gwseq_get_cheval_galerie(14) === array(101, 102), 'Galerie : aucun doublon — un même attachment_id soumis deux fois n’apparaît qu’une seule fois');

// --- Featured image indépendante : modifier la galerie ne touche jamais _thumbnail_id, et
// inversement (la photo principale n’est JAMAIS lue ni écrite par ce fichier) ---
$GLOBALS['__gwseq_test_thumbnail'][10] = 555;
gwseq_set_cheval_galerie(10, array('101'));
gws_test_assert(gwseq_get_cheval_photo_principale_id(10) === 555, 'Photo principale : totalement indépendante de la galerie — modifier la galerie ne modifie jamais l’image à la une');
gws_test_assert(strpos($cheval_media_source, "'_thumbnail_id'") === false && strpos($cheval_media_source, '"_thumbnail_id"') === false, 'Photo principale : ce fichier ne déclare et ne manipule jamais directement la meta "_thumbnail_id" — uniquement via get_post_thumbnail_id(), jamais un second champ dupliqué');

// --- Aucune suppression du média WordPress lors du retrait de la galerie (vérification
// déclarative directe sur le CODE, hors commentaires PHP — le fichier mentionne
// "wp_delete_attachment" dans sa documentation pour expliquer précisément qu'il ne l'appelle
// jamais, ce qui casserait une recherche naïve sur le texte brut) ---
function gws_test_strip_php_comments($source) {
  $tokens = token_get_all($source);
  $code = '';
  foreach ($tokens as $token) {
    if (is_array($token) && in_array($token[0], array(T_COMMENT, T_DOC_COMMENT), true)) continue;
    $code .= is_array($token) ? $token[1] : $token;
  }
  return $code;
}
$cheval_media_code_only = gws_test_strip_php_comments($cheval_media_source);
gws_test_assert(strpos($cheval_media_code_only, 'wp_delete_attachment') === false, 'Galerie : aucun appel à wp_delete_attachment() n’existe dans le code réellement exécuté — retirer une image de la galerie ne supprime jamais le média de la médiathèque');

// --- Robustesse : cheval_id invalide, entrée non-tableau ---
gws_test_assert(gwseq_set_cheval_galerie(0, array('101')) === false, 'Robustesse : un cheval_id invalide (0) est refusé');
gws_test_assert(gwseq_sanitize_cheval_galerie('pas-un-tableau') === array(), 'Robustesse : une entrée qui n’est pas un tableau est traitée comme une liste vide, jamais une erreur');

// =====================================================================================
// Vidéos — §6 de la demande
// =====================================================================================

// --- Ajout : URL + titre facultatif ---
gwseq_set_cheval_videos(20, array(
  array('url' => 'https://www.youtube.com/watch?v=abc123', 'titre' => 'Présentation'),
));
$videos_20 = gwseq_get_cheval_videos(20);
gws_test_assert(count($videos_20) === 1 && $videos_20[0]['url'] === 'https://www.youtube.com/watch?v=abc123' && $videos_20[0]['titre'] === 'Présentation', 'Vidéos : ajout d’une vidéo avec URL et titre, les deux conservés exactement');

// --- Titre facultatif : une URL seule, sans titre, est parfaitement valide ---
gwseq_set_cheval_videos(21, array(array('url' => 'https://vimeo.com/123456')));
$videos_21 = gwseq_get_cheval_videos(21);
gws_test_assert(count($videos_21) === 1 && $videos_21[0]['titre'] === '', 'Vidéos : le titre est bien facultatif, une URL seule est conservée');

// --- Suppression : une resoumission avec moins de lignes retire bien les vidéos absentes ---
gwseq_set_cheval_videos(20, array(array('url' => 'https://www.youtube.com/watch?v=abc123', 'titre' => 'Présentation')));
gwseq_set_cheval_videos(20, array());
gws_test_assert(gwseq_get_cheval_videos(20) === array(), 'Vidéos : resoumettre une liste vide retire bien toutes les vidéos précédemment enregistrées');

// --- Ordre : conservé tel que soumis, jamais trié ---
gwseq_set_cheval_videos(22, array(
  array('url' => 'https://www.youtube.com/watch?v=b', 'titre' => 'B'),
  array('url' => 'https://www.youtube.com/watch?v=a', 'titre' => 'A'),
));
$videos_22 = gwseq_get_cheval_videos(22);
gws_test_assert($videos_22[0]['titre'] === 'B' && $videos_22[1]['titre'] === 'A', 'Vidéos : l’ordre de saisie est conservé, aucun tri automatique (alphabétique ou autre)');

// --- URL valide (plusieurs schémas courants) ---
gws_test_assert(gwseq_sanitize_cheval_video_url('https://www.youtube.com/watch?v=xyz') === 'https://www.youtube.com/watch?v=xyz', 'URL valide : une URL https bien formée est conservée telle quelle');
gws_test_assert(gwseq_sanitize_cheval_video_url('http://vimeo.com/1') === 'http://vimeo.com/1', 'URL valide : le schéma http (non sécurisé mais légitime) reste accepté');

// --- URL invalide rejetée/sanitisée : jamais stockée telle quelle, la ligne entière disparaît
// (un titre seul sans URL exploitable n’a pas de sens) ---
gws_test_assert(gwseq_sanitize_cheval_video_url('javascript:alert(1)') === '', 'URL invalide : un schéma dangereux (javascript:) est rejeté');
gws_test_assert(gwseq_sanitize_cheval_video_url('ceci n’est pas une URL') === '', 'URL invalide : un texte quelconque sans schéma exploitable est rejeté');
gws_test_assert(gwseq_sanitize_cheval_video_url('') === '', 'URL invalide : une chaîne vide reste vide, jamais une erreur');

gwseq_set_cheval_videos(23, array(
  array('url' => 'https://www.youtube.com/watch?v=valide', 'titre' => 'Valide'),
  array('url' => 'javascript:alert(1)', 'titre' => 'Devrait disparaître'),
  array('url' => '', 'titre' => 'Titre seul, sans URL — ne doit pas être conservé'),
));
$videos_23 = gwseq_get_cheval_videos(23);
gws_test_assert(count($videos_23) === 1 && $videos_23[0]['titre'] === 'Valide', 'Vidéos : une ligne avec une URL invalide ou absente est entièrement écartée, y compris si un titre avait été saisi');

// --- Maximum 10 vidéos ---
$onze_videos = array();
for ($i = 1; $i <= 11; $i++) {
  $onze_videos[] = array('url' => 'https://www.youtube.com/watch?v=v' . $i, 'titre' => 'Vidéo ' . $i);
}
gwseq_set_cheval_videos(24, $onze_videos);
$videos_24 = gwseq_get_cheval_videos(24);
gws_test_assert(count($videos_24) === GWSEQ_CHEVAL_VIDEOS_MAX, 'Vidéos : bornées à ' . GWSEQ_CHEVAL_VIDEOS_MAX . ' maximum, même si davantage sont soumises');
gws_test_assert($videos_24[0]['titre'] === 'Vidéo 1' && $videos_24[9]['titre'] === 'Vidéo 10', 'Vidéos : ce sont bien les 10 premières vidéos soumises (dans l’ordre) qui sont conservées');

// --- Robustesse : entrée non-tableau, ligne non-tableau ---
gws_test_assert(gwseq_sanitize_cheval_videos('pas-un-tableau') === array(), 'Robustesse : une entrée qui n’est pas un tableau est traitée comme une liste vide');
gws_test_assert(gwseq_sanitize_cheval_videos(array('pas-une-ligne')) === array(), 'Robustesse : une ligne qui n’est pas elle-même un tableau est ignorée, jamais une erreur');

// =====================================================================================
// Persistance et compatibilité (§13 de la demande)
// =====================================================================================

// --- Sauvegardes successives sans perte de données croisée (galerie <-> vidéos) ---
gwseq_set_cheval_galerie(30, array('101', '102'));
gwseq_set_cheval_videos(30, array(array('url' => 'https://www.youtube.com/watch?v=x')));
gwseq_set_cheval_galerie(30, array('101', '102', '103'));
gws_test_assert(
  gwseq_get_cheval_galerie(30) === array(101, 102, 103) && count(gwseq_get_cheval_videos(30)) === 1,
  'Persistance : modifier la galerie ne fait jamais perdre les vidéos déjà enregistrées, et réciproquement'
);

// --- Compatibilité avec une fiche Cheval créée avant l’Étape 6 (jamais enregistrée) ---
gws_test_assert(gwseq_get_cheval_galerie(999) === array(), 'Compatibilité : une fiche jamais enregistrée renvoie une galerie vide, jamais une erreur');
gws_test_assert(gwseq_get_cheval_videos(999) === array(), 'Compatibilité : une fiche jamais enregistrée renvoie une liste de vidéos vide, jamais une erreur');
gws_test_assert(gwseq_get_cheval_photo_principale_id(999) === 0, 'Compatibilité : une fiche jamais enregistrée n’a pas de photo principale, jamais une erreur (0, comme get_post_thumbnail_id() nativement)');

// --- Désactivation/réactivation du module : aucune suppression de meta n'est jamais construite ---
gws_test_assert(strpos($cheval_media_source, 'delete_post_meta') === false, 'Désactivation/réactivation : ce fichier n’appelle jamais delete_post_meta() — aucune donnée de galerie/vidéo ne peut être supprimée par une (dés)activation du module');

// --- Programmatique, sans $_POST ni nonce ---
$import_galerie = gwseq_set_cheval_galerie(40, array('101', '102'));
$import_videos = gwseq_set_cheval_videos(40, array(array('url' => 'https://www.youtube.com/watch?v=import')));
gws_test_assert($import_galerie === true && $import_videos === true, 'Programmatique : un appel direct (simulant un futur import) enregistre correctement, sans $_POST ni nonce');

// =====================================================================================
// Rendu admin, câblage JS, i18n
// =====================================================================================

$post_stub = (object) array('ID' => 10);
ob_start();
gwseq_render_cheval_media_box($post_stub);
$media_box_html = ob_get_clean();
gws_test_assert(strpos($media_box_html, 'gwseq-galerie__add') !== false, 'Rendu admin : le bouton d’ajout d’images à la galerie est bien rendu');
gws_test_assert(strpos($media_box_html, 'data-gwseq-galerie-max="' . GWSEQ_CHEVAL_GALERIE_MAX . '"') !== false, 'Rendu admin : la limite de la galerie est bien exposée au script via un attribut data-*');
gws_test_assert(strpos($media_box_html, 'name="_gwseq_galerie[]"') !== false, 'Rendu admin : les images déjà enregistrées sont bien rendues comme champs cachés ordonnés');
gws_test_assert(strpos($media_box_html, 'class="gwseq-galerie__template"') !== false, 'Rendu admin : un gabarit natif <template> est utilisé pour une nouvelle image, cohérent avec le composant répétable des vidéos');
gws_test_assert(strpos($media_box_html, 'name="_gwseq_videos[') !== false, 'Rendu admin : les champs du répétable Vidéos sont bien rendus (réutilisation du composant générique)');
gws_test_assert(strpos($media_box_html, 'data-gwseq-repeater-max="' . GWSEQ_CHEVAL_VIDEOS_MAX . '"') !== false, 'Rendu admin : la limite de 10 vidéos est bien transmise au composant répétable générique');

$media_js_source = file_get_contents($module_dir . 'assets/cheval-media-admin.js');
gws_test_assert(strpos($media_js_source, 'wp.media') !== false, 'Câblage JS : le script utilise bien la médiathèque native (wp.media()), aucun uploader personnalisé');
gws_test_assert(strpos($media_js_source, 'multiple: true') !== false, 'Câblage JS : la sélection multiple est activée dans la médiathèque');
gws_test_assert(strpos($media_js_source, 'removeChild') !== false, 'Câblage JS : le retrait d’une image reste un simple retrait DOM, jamais un appel serveur ou une suppression du média');

foreach ($GLOBALS['__gwseq_test_domains_used'] as $domain) {
  gws_test_assert($domain === 'gws-core', "i18n : aucun appel de traduction n’utilise un text domain autre que \"gws-core\" (trouvé : $domain)");
}

echo ($failures === 0 ? 'Tous les tests sont passés.' : "$failures test(s) en échec.") . "\n";
exit($failures === 0 ? 0 : 1);

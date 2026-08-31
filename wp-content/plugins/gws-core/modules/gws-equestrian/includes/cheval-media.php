<?php
/**
 * Cheval — médias : galerie photos et vidéos (Étape 6, §4-6 de la demande).
 *
 * PHOTO PRINCIPALE (§4) : reste exclusivement l'image à la une native de WordPress
 * (`_thumbnail_id`, déjà en place depuis l'Étape 4 — voir includes/post-types.php pour son
 * relabelling en "Photo principale"). Ce fichier n'enregistre AUCUN second champ de photo
 * principale : gwseq_get_cheval_photo_principale_id() ci-dessous n'est qu'un alias nommé de
 * get_post_thumbnail_id(), pour la seule cohérence de nommage avec les autres accesseurs de ce
 * fichier — aucune nouvelle donnée, aucune duplication. La boîte de rendu ci-dessous
 * (`gwseq_render_cheval_media_box()`) ne fait que réserver un emplacement vide
 * (`#gwseq-cheval-media-photo-principale-slot`) : c'est `assets/cheval-tabs-admin.js` qui y
 * réinsère RÉELLEMENT la véritable boîte native WordPress `#postimagediv` (correctif post-recette
 * de l'ajustement onglets — voir `includes/cheval-admin-tabs.php`), jamais un nouveau champ.
 *
 * GALERIE (§5) : jusqu'à GWSEQ_CHEVAL_GALERIE_MAX (9) photos complémentaires, en plus de la photo
 * principale (10 au total). Stockée en UN SEUL tableau ORDONNÉ d'identifiants d'attachement WordPress
 * (jamais des URLs — un attachment_id reste valide même si les fichiers dérivés sont régénérés ou
 * si le média est déplacé) dans `_gwseq_galerie`. Retirer une image de la galerie ne supprime
 * JAMAIS le média de la médiathèque (aucun appel à wp_delete_attachment() nulle part dans ce
 * fichier) — seule la référence dans ce tableau disparaît. Aucun système d'upload parallèle :
 * l'ajout passe exclusivement par le sélecteur natif de la médiathèque (wp.media(), voir
 * assets/cheval-media-admin.js), jamais un champ de dépôt de fichier personnalisé. Aucun
 * redimensionnement destructif de l'original : les renderers futurs (web/PDF/catalogue/print/
 * Social Kit) réutiliseront les mécanismes natifs de tailles dérivées de WordPress
 * (wp_get_attachment_image_url()/srcset) à partir de ce même attachment_id — voir la taille dédiée
 * "gwseq_large" enregistrée plus bas (~1600px, pour le grand affichage) si un renderer futur en a
 * besoin, sans jamais toucher au fichier original.
 *
 * VIDÉOS (§6) : liste répétable {url, titre facultatif}, ORDONNÉE, jusqu'à GWSEQ_CHEVAL_VIDEOS_MAX
 * (10) entrées. Réutilise le composant répétable générique déjà construit à l'Étape 2
 * (includes/repeater-field.php — dont l'en-tête mentionne d'ailleurs déjà explicitement "URLs de
 * vidéos" comme cas d'usage anticipé) pour le RENDU et le JavaScript d'ajout/suppression de lignes
 * (gwseq_render_repeater_field(), assets/repeater-field.js), mais avec une SANITATION dédiée
 * (gwseq_sanitize_cheval_videos() ci-dessous) : une ligne sans URL exploitable n'a pas de sens et
 * n'est jamais stockée (contrairement à la règle générique du composant, qui ne rejette une ligne
 * que si TOUTES ses colonnes sont vides) — c'est pourquoi ce fichier appelle
 * gwseq_render_repeater_field() directement plutôt que le raccourci gwseq_register_repeater_field(),
 * qui imposerait la sanitation générique. Aucun upload direct de fichier vidéo : seule une URL
 * (compatible avec les mécanismes sûrs/oEmbed de WordPress — un schéma http/https valide est exigé,
 * voir gwseq_sanitize_cheval_video_url()) est acceptée. Retirer une vidéo du cheval ne supprime
 * évidemment aucun contenu hébergé ailleurs.
 *
 * RÈGLE MÉTIER UNIQUE ET PROGRAMMATIQUE (§11, même architecture que le pedigree — Étape 5) :
 * gwseq_set_cheval_galerie()/gwseq_set_cheval_videos() sont des fonctions métier pures, jamais
 * couplées à $_POST ni à un nonce/capability — réutilisables telles quelles par un futur importeur
 * CSV/XLSX, une duplication de fiche, une API, ou une synchronisation GWS Network.
 */

if (!defined('ABSPATH')) exit;

const GWSEQ_CHEVAL_GALERIE_MAX = 9;
const GWSEQ_CHEVAL_VIDEOS_MAX = 10;

/* -------------------------------------------------------------------------------------------
 * Enregistrement des meta et de la taille d'image dédiée.
 * ----------------------------------------------------------------------------------------- */

function gwseq_register_cheval_media_meta() {
  register_post_meta(GWSEQ_CPT_CHEVAL, '_gwseq_galerie', array('single' => true, 'type' => 'array', 'show_in_rest' => false));
  register_post_meta(GWSEQ_CPT_CHEVAL, '_gwseq_videos', array('single' => true, 'type' => 'array', 'show_in_rest' => false));
}
add_action('init', 'gwseq_register_cheval_media_meta');

/**
 * Taille d'image dédiée pour le grand affichage (§5 de la demande) — jamais un redimensionnement
 * destructif de l'original : WordPress génère et conserve ce dérivé séparément, l'original reste
 * intact et reste lui-même disponible pour tout usage nécessitant la pleine résolution (print,
 * PDF...). Simplement enregistrée ici pour que les renderers futurs disposent d'une taille
 * cohérente sans avoir à la redéfinir chacun de leur côté ; non recadrée (false) pour ne jamais
 * déformer une image dont le ratio ne correspondrait pas exactement à 1600×1600.
 */
function gwseq_register_cheval_media_image_size() {
  if (function_exists('add_image_size')) {
    add_image_size('gwseq_large', 1600, 1600, false);
  }
}
add_action('after_setup_theme', 'gwseq_register_cheval_media_image_size');

/* -------------------------------------------------------------------------------------------
 * Fonctions pures : sanitation, lecture, persistance. Aucune dépendance à $_POST.
 * ----------------------------------------------------------------------------------------- */

function gwseq_get_cheval_photo_principale_id($cheval_id) {
  return get_post_thumbnail_id($cheval_id);
}

/**
 * Sanitise une liste brute d'identifiants d'attachement en un tableau ORDONNÉ, propre : chaque
 * valeur doit être un entier strictement positif pointant vers une vraie image de la médiathèque
 * (même vérification que le type 'attachment_id' du générateur minimal de gws-core,
 * wp_attachment_is_image()) ; aucun doublon (un même média ne peut apparaître deux fois dans la
 * galerie d'un même cheval) ; bornée à GWSEQ_CHEVAL_GALERIE_MAX quelle que soit la taille de
 * l'entrée fournie — une structure manipulée ne peut jamais contourner la limite serveur. L'ordre
 * de la liste fournie est conservé (aucun tri).
 */
function gwseq_sanitize_cheval_galerie($raw_ids) {
  if (!is_array($raw_ids)) return array();

  $clean = array();
  foreach ($raw_ids as $raw_id) {
    if (is_array($raw_id)) continue;
    $id = absint($raw_id);
    if (!$id) continue;
    if (!wp_attachment_is_image($id)) continue;
    if (in_array($id, $clean, true)) continue;
    $clean[] = $id;
    if (count($clean) >= GWSEQ_CHEVAL_GALERIE_MAX) break;
  }
  return $clean;
}

function gwseq_set_cheval_galerie($cheval_id, $raw_ids) {
  $cheval_id = (int) $cheval_id;
  if (!$cheval_id) return false;
  update_post_meta($cheval_id, '_gwseq_galerie', gwseq_sanitize_cheval_galerie($raw_ids));
  return true;
}

function gwseq_get_cheval_galerie($cheval_id) {
  $ids = get_post_meta($cheval_id, '_gwseq_galerie', true);
  return is_array($ids) ? $ids : array();
}

/**
 * Sanitise une URL de vidéo : mêmes garanties que gws_core_field_sanitize('url', ...)
 * (esc_url_raw()) PLUS une validation de schéma stricte (http/https uniquement) — une chaîne qui
 * n'est pas une URL exploitable (texte quelconque, schéma dangereux type "javascript:") est
 * traitée comme absente, jamais stockée telle quelle. Compatible avec l'usage ultérieur des
 * mécanismes sûrs/oEmbed de WordPress (wp_oembed_get()/le shortcode [embed]), qui attendent eux
 * aussi une URL http(s) valide — aucune restriction à une liste de fournisseurs oEmbed connus
 * n'est imposée ici, ce choix relevant du futur rendu (hors périmètre de cette étape).
 */
function gwseq_sanitize_cheval_video_url($raw) {
  $url = gws_core_field_sanitize('url', $raw);
  if ($url === '') return '';
  $scheme = wp_parse_url($url, PHP_URL_SCHEME);
  if (!in_array($scheme, array('http', 'https'), true)) return '';
  return $url;
}

/**
 * Sanitise la liste de vidéos soumise : contrairement à la règle générique du composant répétable
 * (gwseq_repeater_sanitize_rows(), qui ne rejette une ligne que si TOUTES ses colonnes sont
 * vides), une ligne SANS URL exploitable n'a pas de sens ici et n'est jamais stockée, même si un
 * titre a été saisi seul. L'ordre de saisie est conservé ; bornée à GWSEQ_CHEVAL_VIDEOS_MAX lignes
 * quelle que soit la taille de l'entrée fournie.
 */
function gwseq_sanitize_cheval_videos($raw_rows) {
  if (!is_array($raw_rows)) return array();

  $clean = array();
  foreach ($raw_rows as $raw_row) {
    if (!is_array($raw_row)) continue;
    $url = gwseq_sanitize_cheval_video_url($raw_row['url'] ?? '');
    if ($url === '') continue;
    $clean[] = array(
      'url' => $url,
      'titre' => gws_core_field_sanitize('text', $raw_row['titre'] ?? ''),
    );
    if (count($clean) >= GWSEQ_CHEVAL_VIDEOS_MAX) break;
  }
  return $clean;
}

function gwseq_set_cheval_videos($cheval_id, $raw_rows) {
  $cheval_id = (int) $cheval_id;
  if (!$cheval_id) return false;
  update_post_meta($cheval_id, '_gwseq_videos', gwseq_sanitize_cheval_videos($raw_rows));
  return true;
}

function gwseq_get_cheval_videos($cheval_id) {
  $rows = get_post_meta($cheval_id, '_gwseq_videos', true);
  return is_array($rows) ? $rows : array();
}

/* -------------------------------------------------------------------------------------------
 * Meta box, sauvegarde et assets (glue WordPress) — clients des fonctions ci-dessus.
 * ----------------------------------------------------------------------------------------- */

function gwseq_cheval_video_field_schema() {
  return array(
    'url' => array('label' => __('URL', 'gws-core'), 'type' => 'url'),
    'titre' => array('label' => __('Titre (facultatif)', 'gws-core'), 'type' => 'text'),
  );
}

function gwseq_add_cheval_media_meta_box() {
  add_meta_box('gwseq-cheval-media', __('Médias', 'gws-core'), 'gwseq_render_cheval_media_box', GWSEQ_CPT_CHEVAL, 'normal', 'default');
}
add_action('add_meta_boxes_' . GWSEQ_CPT_CHEVAL, 'gwseq_add_cheval_media_meta_box');

/**
 * Rendu d'un item de galerie (existant ou vierge pour le gabarit JS, id 0). Le JS
 * (assets/cheval-media-admin.js) clone ce même gabarit pour une image ajoutée depuis la
 * médiathèque, sans dupliquer cette structure entre PHP et JS.
 */
function gwseq_cheval_galerie_item_markup($attachment_id) {
  $attachment_id = (int) $attachment_id;
  $thumb_url = $attachment_id ? wp_get_attachment_image_url($attachment_id, 'thumbnail') : '';
  ob_start();
  ?>
  <li class="gwseq-galerie__item" data-attachment-id="<?php echo esc_attr($attachment_id); ?>">
    <img class="gwseq-galerie__thumb" src="<?php echo esc_url($thumb_url); ?>" alt="">
    <input type="hidden" name="_gwseq_galerie[]" value="<?php echo esc_attr($attachment_id); ?>">
    <span class="gwseq-galerie__actions">
      <button type="button" class="button gwseq-galerie__move-up" aria-label="<?php esc_attr_e('Déplacer vers le haut', 'gws-core'); ?>">&uarr;</button>
      <button type="button" class="button gwseq-galerie__move-down" aria-label="<?php esc_attr_e('Déplacer vers le bas', 'gws-core'); ?>">&darr;</button>
      <button type="button" class="button-link-delete gwseq-galerie__remove"><?php esc_html_e('Retirer', 'gws-core'); ?></button>
    </span>
  </li>
  <?php
  return ob_get_clean();
}

function gwseq_render_cheval_media_box($post) {
  wp_nonce_field(GWSEQ_CHEVAL_NONCE_ACTION, GWSEQ_CHEVAL_NONCE_FIELD);
  $galerie = gwseq_get_cheval_galerie($post->ID);
  ?>
  <h4><?php esc_html_e('Photo principale', 'gws-core'); ?></h4>
  <?php
  /**
   * Emplacement d'accueil de la véritable boîte native "Image à la une" (#postimagediv),
   * réinsérée ici par assets/cheval-tabs-admin.js (voir includes/cheval-admin-tabs.php pour le
   * détail du mécanisme) — jamais un second champ : c'est le MÊME nœud DOM, avec les mêmes
   * gestionnaires wp.media() déjà attachés par WordPress, donc la même Featured Image, l'unique
   * source de vérité. Reste vide (donc invisible) tant que JavaScript ne l'a pas rempli : SANS
   * JAVASCRIPT, cet emplacement ne contient simplement rien, et la Photo principale demeure
   * modifiable normalement via l'encadré natif de la colonne latérale, à sa place habituelle.
   */
  ?>
  <div id="gwseq-cheval-media-photo-principale-slot" class="gwseq-cheval-media__photo-principale-slot"></div>

  <h4><?php esc_html_e('Galerie', 'gws-core'); ?></h4>
  <div class="gwseq-galerie" data-gwseq-galerie-max="<?php echo esc_attr(GWSEQ_CHEVAL_GALERIE_MAX); ?>">
    <ul class="gwseq-galerie__list">
      <?php foreach ($galerie as $attachment_id) : ?>
        <?php echo gwseq_cheval_galerie_item_markup($attachment_id); ?>
      <?php endforeach; ?>
    </ul>
    <p>
      <button type="button" class="button gwseq-galerie__add"><?php esc_html_e('+ Ajouter des images', 'gws-core'); ?></button>
    </p>
    <p class="description"><?php echo esc_html(sprintf(
      /* translators: %d: nombre maximum de photos de galerie, en plus de la photo principale */
      __('Jusqu’à %d photos complémentaires à la photo principale (10 au total). Retirer une image d’ici ne la supprime jamais de la médiathèque.', 'gws-core'),
      GWSEQ_CHEVAL_GALERIE_MAX
    )); ?></p>
    <template class="gwseq-galerie__template"><?php echo gwseq_cheval_galerie_item_markup(0); ?></template>
  </div>

  <h4><?php esc_html_e('Vidéos', 'gws-core'); ?></h4>
  <p class="description"><?php esc_html_e('URL uniquement (YouTube, Vimeo...) — aucun envoi de fichier vidéo ici. Retirer une vidéo de cette liste ne supprime aucun contenu hébergé ailleurs.', 'gws-core'); ?></p>
  <?php gwseq_render_repeater_field($post, '_gwseq_videos', gwseq_cheval_video_field_schema(), GWSEQ_CHEVAL_NONCE_ACTION, GWSEQ_CHEVAL_VIDEOS_MAX); ?>
  <?php
}

function gwseq_save_cheval_media_meta($post_id) {
  if (!isset($_POST[GWSEQ_CHEVAL_NONCE_FIELD]) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[GWSEQ_CHEVAL_NONCE_FIELD])), GWSEQ_CHEVAL_NONCE_ACTION)) return;
  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
  if (function_exists('wp_is_post_revision') && wp_is_post_revision($post_id)) return;
  if (!current_user_can('edit_post', $post_id)) return;

  gwseq_set_cheval_galerie($post_id, $_POST['_gwseq_galerie'] ?? array());
  gwseq_set_cheval_videos($post_id, $_POST['_gwseq_videos'] ?? array());
}
add_action('save_post_' . GWSEQ_CPT_CHEVAL, 'gwseq_save_cheval_media_meta');

/**
 * Assets : médiathèque native (wp.media()) + composant répétable générique (vidéos), uniquement
 * sur l'écran d'édition d'une fiche cheval — jamais chargés globalement dans l'administration.
 */
function gwseq_enqueue_cheval_media_admin_assets($hook) {
  if (!in_array($hook, array('post.php', 'post-new.php'), true)) return;
  $screen = function_exists('get_current_screen') ? get_current_screen() : null;
  if (!$screen || $screen->post_type !== GWSEQ_CPT_CHEVAL) return;

  wp_enqueue_media();
  wp_enqueue_style('gwseq-repeater-field', GWSEQ_MODULE_URL . 'assets/repeater-field.css', array(), GWSEQ_MODULE_VERSION);
  wp_enqueue_script('gwseq-repeater-field', GWSEQ_MODULE_URL . 'assets/repeater-field.js', array(), GWSEQ_MODULE_VERSION, true);
  wp_enqueue_style('gwseq-cheval-media', GWSEQ_MODULE_URL . 'assets/cheval-media.css', array(), GWSEQ_MODULE_VERSION);
  wp_enqueue_script('gwseq-cheval-media-admin', GWSEQ_MODULE_URL . 'assets/cheval-media-admin.js', array(), GWSEQ_MODULE_VERSION, true);
}
add_action('admin_enqueue_scripts', 'gwseq_enqueue_cheval_media_admin_assets');

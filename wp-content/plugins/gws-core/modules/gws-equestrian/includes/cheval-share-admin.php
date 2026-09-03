<?php
/**
 * Partager un cheval — écran métier BO (Étape 8, lot « Partager un cheval »), §5-9/§17/§21/§27 de
 * la demande.
 *
 * Écran dédié `Chevaux → Partager` (jamais une simple meta box dans l'écran d'édition — §5), conçu
 * MOBILE-FIRST (§7) : recherche d'un cheval, puis sélection des informations à transmettre, aperçu
 * en temps réel, et trois actions (WhatsApp/SMS/Copier). Accessible aussi depuis une fiche cheval
 * (action de ligne + boîte latérale, §6) : les deux chemins mènent au MÊME écran, jamais deux
 * interfaces différentes — le second ne fait que présélectionner le cheval via `?cheval_id=`.
 *
 * Ce fichier ne fait QUE la glue WordPress (menu, sécurité, rendu de l'écran, points d'entrée
 * AJAX) : toute la logique de composition vient de includes/cheval-share.php, jamais dupliquée ici.
 * Les trois points d'entrée AJAX ne font QUE relire des données déjà en base et les composer via
 * ces fonctions déjà existantes — aucune écriture, aucune persistance de sélection ou de message
 * (§22 : un partage reste entièrement éphémère).
 *
 * PERFORMANCE (§27) : la recherche ne charge jamais les données complètes/médias d'aucun cheval —
 * seules des lignes légères (nom, photo, identité résumée) sont produites pour la liste de
 * résultats. Les données COMPLÈTES (gwseq_get_horse_shareable_data()) ne sont chargées qu'une fois
 * un cheval explicitement choisi, via un second aller-retour AJAX dédié.
 *
 * PERMISSIONS (§21) : capacité `edit_posts` pour accéder à l'écran (cohérent avec le type d'objet
 * Cheval — capability_type par défaut 'post', aucune capacité personnalisée, même raisonnement que
 * includes/ifce-import-admin.php) ; capacité `edit_post` (méta-capacité, spécifique à la fiche
 * demandée) pour toute lecture de données complètes d'un cheval précis — un auteur sans
 * `edit_others_posts` ne peut ni rechercher ni charger les chevaux d'un autre auteur, exactement
 * comme la liste d'administration native `Chevaux → Tous les chevaux` le lui interdit déjà.
 */

if (!defined('ABSPATH')) exit;

const GWSEQ_HORSE_SHARE_NONCE_ACTION = 'gwseq_horse_share';
const GWSEQ_HORSE_SHARE_RECENT_LIMIT = 20;
const GWSEQ_HORSE_SHARE_SEARCH_LIMIT = 20;

function gwseq_horse_share_menu_slug() {
  return 'gwseq-partager';
}

function gwseq_horse_share_page_url($args = array()) {
  return add_query_arg(
    array_merge(array('post_type' => GWSEQ_CPT_CHEVAL, 'page' => gwseq_horse_share_menu_slug()), $args),
    admin_url('edit.php')
  );
}

function gwseq_add_horse_share_page() {
  add_submenu_page(
    'edit.php?post_type=' . GWSEQ_CPT_CHEVAL,
    __('Partager un cheval', 'gws-core'),
    __('Partager', 'gws-core'),
    'edit_posts',
    gwseq_horse_share_menu_slug(),
    'gwseq_render_horse_share_page'
  );
}
add_action('admin_menu', 'gwseq_add_horse_share_page');

/**
 * Un auteur sans `edit_others_posts` ne voit, dans la liste native `Chevaux → Tous les chevaux`,
 * que ses propres fiches — cette fonction reproduit EXACTEMENT la même restriction pour la
 * recherche/liste initiale de cet écran, sans inventer de nouvelle règle de permission (§21).
 */
function gwseq_horse_share_current_user_restricted_to_own() {
  return !current_user_can('edit_others_posts');
}

/**
 * Ligne LÉGÈRE d'un cheval pour la liste de résultats (§27 : jamais les données complètes/médias).
 * Réutilise gwseq_horse_share_identite_label() (includes/cheval-share.php) — seule lecture de meta
 * nécessaire pour ce résumé, aucune requête de pedigree/vidéos/indices ici.
 */
function gwseq_horse_share_lightweight_row($post_id) {
  $identity = gwseq_get_cheval_identity($post_id);
  return array(
    'id' => $post_id,
    'nom' => get_the_title($post_id),
    'photo_url' => wp_get_attachment_image_url(gwseq_get_cheval_photo_principale_id($post_id), 'thumbnail') ?: '',
    'sous_titre' => gwseq_horse_share_identite_label($identity),
    'statut' => gwseq_cheval_statut_commercial_options()[gwseq_get_cheval_commercial($post_id)['statut_commercial']] ?? '',
  );
}

function gwseq_horse_share_query_chevaux($args) {
  $base = array(
    'post_type' => GWSEQ_CPT_CHEVAL,
    'post_status' => 'any',
    'fields' => 'ids',
  );
  if (gwseq_horse_share_current_user_restricted_to_own()) {
    $base['author'] = get_current_user_id();
  }
  $query = new WP_Query(array_merge($base, $args));
  return $query->posts;
}

function gwseq_horse_share_recent_chevaux() {
  $ids = gwseq_horse_share_query_chevaux(array(
    'posts_per_page' => GWSEQ_HORSE_SHARE_RECENT_LIMIT,
    'orderby' => 'modified',
    'order' => 'DESC',
  ));
  return array_map('gwseq_horse_share_lightweight_row', $ids);
}

/* -------------------------------------------------------------------------------------------
 * Écran.
 * ----------------------------------------------------------------------------------------- */

function gwseq_render_horse_share_page() {
  if (!current_user_can('edit_posts')) wp_die(esc_html__('Action non autorisée.', 'gws-core'));

  $preselected_id = isset($_GET['cheval_id']) ? absint($_GET['cheval_id']) : 0;
  if ($preselected_id && (!current_user_can('edit_post', $preselected_id) || get_post_type($preselected_id) !== GWSEQ_CPT_CHEVAL)) {
    $preselected_id = 0;
  }
  ?>
  <div class="wrap gwseq-partager">
    <h1><?php esc_html_e('Partager un cheval', 'gws-core'); ?></h1>
    <div id="gwseq-partager-app" data-gwseq-preselected-id="<?php echo esc_attr($preselected_id); ?>"></div>
  </div>
  <?php
}

/* -------------------------------------------------------------------------------------------
 * Points d'entrée AJAX — aucune écriture, aucune persistance (§22).
 * ----------------------------------------------------------------------------------------- */

function gwseq_horse_share_ajax_check_general() {
  check_ajax_referer(GWSEQ_HORSE_SHARE_NONCE_ACTION, 'nonce');
  if (!current_user_can('edit_posts')) wp_send_json_error(array('message' => __('Action non autorisée.', 'gws-core')), 403);
}

/**
 * Recherche (§27) : nom du cheval en priorité (recherche native WordPress, `s`), résultats limités,
 * scopée aux chevaux auxquels l'utilisateur a accès. Une requête vide renvoie la liste "récents"
 * plutôt qu'une liste vide — évite un écran de résultats vide au premier affichage.
 */
function gwseq_ajax_partager_search_cheval() {
  gwseq_horse_share_ajax_check_general();

  $search = isset($_POST['s']) ? sanitize_text_field(wp_unslash($_POST['s'])) : '';
  if ($search === '') {
    wp_send_json_success(array('resultats' => gwseq_horse_share_recent_chevaux()));
  }

  $ids = gwseq_horse_share_query_chevaux(array(
    'posts_per_page' => GWSEQ_HORSE_SHARE_SEARCH_LIMIT,
    's' => $search,
  ));
  wp_send_json_success(array('resultats' => array_map('gwseq_horse_share_lightweight_row', $ids)));
}
add_action('wp_ajax_gwseq_partager_search_cheval', 'gwseq_ajax_partager_search_cheval');

/**
 * Données complètes d'UN cheval (§27 : uniquement une fois choisi). Capacité vérifiée sur LA FICHE
 * précise demandée (`edit_post`), pas seulement la capacité générale de l'écran — un auteur sans
 * `edit_others_posts` ne peut pas obtenir les données d'un cheval qui ne lui appartient pas en
 * devinant simplement son identifiant.
 */
function gwseq_ajax_partager_get_cheval() {
  gwseq_horse_share_ajax_check_general();

  $cheval_id = isset($_POST['cheval_id']) ? absint($_POST['cheval_id']) : 0;
  if (!$cheval_id || get_post_type($cheval_id) !== GWSEQ_CPT_CHEVAL || !current_user_can('edit_post', $cheval_id)) {
    wp_send_json_error(array('message' => __('Cheval introuvable ou non autorisé.', 'gws-core')), 403);
  }

  wp_send_json_success(array('cheval' => gwseq_get_horse_shareable_data($cheval_id)));
}
add_action('wp_ajax_gwseq_partager_get_cheval', 'gwseq_ajax_partager_get_cheval');

/**
 * Sanitise la sélection soumise par le client (§4 : la sélection est un CHOIX parmi des libellés
 * déjà déterminés côté serveur, jamais un contenu libre — seul le message personnel est un texte
 * libre). Les données structurées (identity/origines/...) sont TOUJOURS relues depuis la base via
 * gwseq_get_horse_shareable_data($cheval_id), jamais acceptées telles que soumises par le client :
 * celui-ci ne peut donc jamais injecter une ligne fabriquée dans le message.
 */
function gwseq_sanitize_horse_share_selection($raw) {
  $raw = is_array($raw) ? $raw : array();

  $items = array();
  foreach ((array) ($raw['items'] ?? array()) as $key) {
    $key = sanitize_key(wp_unslash($key));
    if ($key !== '') $items[] = $key;
  }

  $videos = array();
  foreach ((array) ($raw['videos'] ?? array()) as $index) {
    if (is_numeric($index)) $videos[] = (int) $index;
  }

  return array(
    'items' => $items,
    'videos' => $videos,
    'fiche' => !empty($raw['fiche']),
    'message_personnel' => gws_core_field_sanitize('textarea', $raw['message_personnel'] ?? ''),
  );
}

/**
 * Construit le message de partage (§14/§15) — même fonction de composition
 * (gwseq_build_horse_share_message()) que TOUT futur canal ou aperçu, jamais une reconstruction
 * différente entre l'aperçu affiché et le texte réellement transmis (WhatsApp/SMS/Copier
 * consomment tous les trois ce même texte côté client, voir assets/cheval-share-admin.js).
 */
function gwseq_ajax_partager_build_message() {
  gwseq_horse_share_ajax_check_general();

  $cheval_id = isset($_POST['cheval_id']) ? absint($_POST['cheval_id']) : 0;
  if (!$cheval_id || get_post_type($cheval_id) !== GWSEQ_CPT_CHEVAL || !current_user_can('edit_post', $cheval_id)) {
    wp_send_json_error(array('message' => __('Cheval introuvable ou non autorisé.', 'gws-core')), 403);
  }

  $shareable = gwseq_get_horse_shareable_data($cheval_id);
  $selection = gwseq_sanitize_horse_share_selection($_POST['selection'] ?? array());
  $message = gwseq_build_horse_share_message($shareable, $selection);

  wp_send_json_success(array('message' => $message));
}
add_action('wp_ajax_gwseq_partager_build_message', 'gwseq_ajax_partager_build_message');

/* -------------------------------------------------------------------------------------------
 * Accès depuis une fiche cheval (§6) : action de ligne + boîte latérale, tous deux vers le MÊME
 * écran (jamais une seconde interface).
 * ----------------------------------------------------------------------------------------- */

function gwseq_add_horse_share_row_action($actions, $post) {
  if ($post->post_type !== GWSEQ_CPT_CHEVAL || !current_user_can('edit_post', $post->ID)) return $actions;
  $actions['gwseq_partager'] = '<a href="' . esc_url(gwseq_horse_share_page_url(array('cheval_id' => $post->ID))) . '">' . esc_html__('Partager', 'gws-core') . '</a>';
  return $actions;
}
add_filter('post_row_actions', 'gwseq_add_horse_share_row_action', 10, 2);

function gwseq_add_horse_share_meta_box() {
  add_meta_box('gwseq-cheval-partage', __('Partage', 'gws-core'), 'gwseq_render_horse_share_meta_box', GWSEQ_CPT_CHEVAL, 'side', 'low');
}
add_action('add_meta_boxes_' . GWSEQ_CPT_CHEVAL, 'gwseq_add_horse_share_meta_box');

function gwseq_render_horse_share_meta_box($post) {
  if ($post->post_status === 'auto-draft') {
    echo '<p class="description">' . esc_html__('Enregistrez d’abord cette fiche pour pouvoir la partager.', 'gws-core') . '</p>';
    return;
  }
  ?>
  <p><a class="button button-primary" style="width:100%;text-align:center;" href="<?php echo esc_url(gwseq_horse_share_page_url(array('cheval_id' => $post->ID))); ?>"><?php esc_html_e('Partager ce cheval', 'gws-core'); ?></a></p>
  <p class="description"><?php esc_html_e('Préparez un message (WhatsApp, SMS, copier) à partir des informations déjà renseignées sur cette fiche.', 'gws-core'); ?></p>
  <?php
}

/* -------------------------------------------------------------------------------------------
 * Assets — uniquement sur l'écran Partager.
 * ----------------------------------------------------------------------------------------- */

function gwseq_enqueue_horse_share_admin_assets($hook) {
  $screen = function_exists('get_current_screen') ? get_current_screen() : null;
  if (!$screen || strpos($screen->id, gwseq_horse_share_menu_slug()) === false) return;

  wp_enqueue_style('gwseq-cheval-share-admin', GWSEQ_MODULE_URL . 'assets/cheval-share-admin.css', array(), GWSEQ_MODULE_VERSION);
  wp_enqueue_script('gwseq-cheval-share-admin', GWSEQ_MODULE_URL . 'assets/cheval-share-admin.js', array(), GWSEQ_MODULE_VERSION, true);
  wp_localize_script('gwseq-cheval-share-admin', 'gwseqPartager', array(
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce(GWSEQ_HORSE_SHARE_NONCE_ACTION),
    'recents' => gwseq_horse_share_recent_chevaux(),
    'i18n' => array(
      'searchPlaceholder' => __('Rechercher un cheval...', 'gws-core'),
      'noResults' => __('Aucun cheval trouvé.', 'gws-core'),
      'share' => __('Partager', 'gws-core'),
      'back' => __('← Choisir un autre cheval', 'gws-core'),
      'personalMessageLabel' => __('Message personnel (facultatif)', 'gws-core'),
      'personalMessagePlaceholder' => __('Ex. Bonjour Pierre, je pensais à cette jument suite à notre échange...', 'gws-core'),
      'infoToSendLabel' => __('Informations à envoyer', 'gws-core'),
      'videosLabel' => __('Vidéos', 'gws-core'),
      'ficheLabel' => __('Ajouter la fiche complète', 'gws-core'),
      'previewLabel' => __('Aperçu du message', 'gws-core'),
      'whatsapp' => __('WhatsApp', 'gws-core'),
      'sms' => __('SMS / Messages', 'gws-core'),
      'copy' => __('Copier', 'gws-core'),
      'copied' => __('Message copié', 'gws-core'),
      'loading' => __('Chargement…', 'gws-core'),
    ),
  ));
}
add_action('admin_enqueue_scripts', 'gwseq_enqueue_horse_share_admin_assets');

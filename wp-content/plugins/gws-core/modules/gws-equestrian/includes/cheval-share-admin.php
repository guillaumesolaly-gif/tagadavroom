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

/**
 * Filtres métier de l'écran de sélection (correctif de recette §3-4) — VOLONTAIREMENT limités à
 * quatre critères déjà structurés et existants (sexe, statut commercial, plage d'année de
 * naissance, catégorie de cheval), jamais un nouveau référentiel : réutilise exactement
 * `gwseq_cheval_sexe_options()`/`gwseq_cheval_statut_commercial_options()`
 * (includes/cheval-fields.php) et la taxonomie `GWSEQ_TAX_CATEGORIE_CHEVAL` déjà en place. Pas de
 * filtre sur prix/taille/race/indices/pedigree/labels/propriétaire/éleveur dans cette V1 (§4 :
 * "éviter de transformer cet écran en moteur de recherche complexe").
 *
 * §7 de la demande — préparation du futur usage multi-chevaux, SANS le développer : cette
 * sanitation et la transformation en arguments de requête ci-dessous
 * (gwseq_horse_share_filters_to_query_args()) sont volontairement DÉCOUPLÉES de tout point d'entrée
 * AJAX précis. `gwseq_horse_share_search_chevaux()`, plus bas, est LA source de résultats unique
 * (recherche + filtres), réutilisable telle quelle par un futur écran de sélection multiple sans
 * réécrire la moindre logique de filtrage — seule l'interface de sélection (case à cocher multiple
 * au lieu d'un bouton "Partager" par ligne) resterait à ajouter le moment venu.
 */
function gwseq_sanitize_horse_share_filters($raw) {
  $raw = is_array($raw) ? $raw : array();

  $sexe = isset($raw['sexe']) ? sanitize_key(wp_unslash($raw['sexe'])) : '';
  if ($sexe !== '' && !array_key_exists($sexe, gwseq_cheval_sexe_options())) $sexe = '';

  $statut = isset($raw['statut']) ? sanitize_key(wp_unslash($raw['statut'])) : '';
  if ($statut !== '' && !array_key_exists($statut, gwseq_cheval_statut_commercial_options())) $statut = '';

  // Bornes réutilisées telles quelles de cheval-fields.php — jamais une seconde limite inventée
  // pour ce filtre. Des bornes inversées (min > max) sont simplement échangées, jamais une erreur.
  $borne_max = gwseq_cheval_annee_naissance_max();
  $annee_min = (isset($raw['annee_min']) && is_numeric($raw['annee_min'])) ? (int) $raw['annee_min'] : 0;
  $annee_max = (isset($raw['annee_max']) && is_numeric($raw['annee_max'])) ? (int) $raw['annee_max'] : 0;
  if ($annee_min && ($annee_min < GWSEQ_CHEVAL_ANNEE_MIN || $annee_min > $borne_max)) $annee_min = 0;
  if ($annee_max && ($annee_max < GWSEQ_CHEVAL_ANNEE_MIN || $annee_max > $borne_max)) $annee_max = 0;
  if ($annee_min && $annee_max && $annee_min > $annee_max) {
    $swap = $annee_min; $annee_min = $annee_max; $annee_max = $swap;
  }

  // Un slug qui ne correspond à AUCUNE catégorie réellement configurée est ignoré (jamais une
  // erreur ni une catégorie fabriquée à la volée) — voir §3 : "ne pas créer de nouvelles catégories
  // automatiquement".
  $categorie = isset($raw['categorie']) ? sanitize_title(wp_unslash($raw['categorie'])) : '';
  if ($categorie !== '' && !term_exists($categorie, GWSEQ_TAX_CATEGORIE_CHEVAL)) $categorie = '';

  return array(
    'sexe' => $sexe,
    'statut' => $statut,
    'annee_min' => $annee_min,
    'annee_max' => $annee_max,
    'categorie' => $categorie,
  );
}

/**
 * Transforme des filtres déjà sanitisés en arguments WP_Query cumulables (§4 : "tous les filtres
 * doivent être cumulatifs") — jamais une reconstruction de requête ad hoc dans chaque appelant.
 */
function gwseq_horse_share_filters_to_query_args($filters) {
  $filters = wp_parse_args(is_array($filters) ? $filters : array(), array(
    'sexe' => '', 'statut' => '', 'annee_min' => 0, 'annee_max' => 0, 'categorie' => '',
  ));
  $args = array();

  $meta_query = array();
  if ($filters['sexe'] !== '') $meta_query[] = array('key' => '_gwseq_sexe', 'value' => $filters['sexe']);
  if ($filters['statut'] !== '') $meta_query[] = array('key' => '_gwseq_statut_commercial', 'value' => $filters['statut']);
  if ($filters['annee_min'] && $filters['annee_max']) {
    $meta_query[] = array('key' => '_gwseq_annee_naissance', 'value' => array($filters['annee_min'], $filters['annee_max']), 'compare' => 'BETWEEN', 'type' => 'NUMERIC');
  } elseif ($filters['annee_min']) {
    $meta_query[] = array('key' => '_gwseq_annee_naissance', 'value' => $filters['annee_min'], 'compare' => '>=', 'type' => 'NUMERIC');
  } elseif ($filters['annee_max']) {
    $meta_query[] = array('key' => '_gwseq_annee_naissance', 'value' => $filters['annee_max'], 'compare' => '<=', 'type' => 'NUMERIC');
  }
  if ($meta_query) {
    if (count($meta_query) > 1) $meta_query['relation'] = 'AND';
    $args['meta_query'] = $meta_query;
  }

  if ($filters['categorie'] !== '') {
    $args['tax_query'] = array(array('taxonomy' => GWSEQ_TAX_CATEGORIE_CHEVAL, 'field' => 'slug', 'terms' => $filters['categorie']));
  }

  return $args;
}

/**
 * Source de résultats UNIQUE (recherche texte + filtres, §7) : une requête vide sans filtre renvoie
 * les chevaux accessibles les plus récemment modifiés (jamais un écran de résultats vide au premier
 * affichage) ; sinon, recherche/filtres cumulés, toujours limités et scopés (§21/§27).
 */
function gwseq_horse_share_search_chevaux($search = '', $filters = array()) {
  $args = array('posts_per_page' => GWSEQ_HORSE_SHARE_SEARCH_LIMIT);
  if ($search !== '') {
    $args['s'] = $search;
  } else {
    $args['posts_per_page'] = GWSEQ_HORSE_SHARE_RECENT_LIMIT;
    $args['orderby'] = 'modified';
    $args['order'] = 'DESC';
  }
  $args = array_merge($args, gwseq_horse_share_filters_to_query_args($filters));

  $ids = gwseq_horse_share_query_chevaux($args);
  return array_map('gwseq_horse_share_lightweight_row', $ids);
}

function gwseq_horse_share_recent_chevaux() {
  return gwseq_horse_share_search_chevaux();
}

/**
 * Libellés du filtre Sexe (correctif de recette §3) — vocabulaire commercial déjà retenu pour CET
 * écran (gwseq_horse_share_sexe_commercial_label(), includes/cheval-share.php : "Jument"/"Étalon"/
 * "Hongre"), pour rester cohérent avec le reste de la page — jamais un second référentiel : les clés
 * techniques restent exactement celles de gwseq_cheval_sexe_options().
 */
function gwseq_horse_share_sexe_filter_options() {
  $options = array();
  foreach (array_keys(gwseq_cheval_sexe_options()) as $sexe) {
    $options[$sexe] = gwseq_horse_share_sexe_commercial_label($sexe);
  }
  return $options;
}

/**
 * Catégories RÉELLEMENT configurées sur le site (§3 : "ne pas créer de nouvelles catégories
 * automatiquement") — même appel que le filtre déjà existant de la liste native
 * (gwseq_render_cheval_admin_list_filters(), includes/cheval-fields.php), jamais une seconde
 * énumération codée en dur.
 */
function gwseq_horse_share_categorie_filter_options() {
  $terms = get_terms(array('taxonomy' => GWSEQ_TAX_CATEGORIE_CHEVAL, 'hide_empty' => false));
  $options = array();
  foreach ((is_array($terms) ? $terms : array()) as $term) {
    $options[$term->slug] = $term->name;
  }
  return $options;
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
 * Recherche (§27, filtres §3-4) : nom du cheval en priorité (recherche native WordPress, `s`),
 * combinable avec les quatre filtres métier (sexe/statut/année/catégorie, cumulatifs), résultats
 * limités, scopés aux chevaux auxquels l'utilisateur a accès. Une requête vide sans filtre renvoie
 * la liste "récents" plutôt qu'une liste vide — évite un écran de résultats vide au premier
 * affichage.
 */
function gwseq_ajax_partager_search_cheval() {
  gwseq_horse_share_ajax_check_general();

  $search = isset($_POST['s']) ? sanitize_text_field(wp_unslash($_POST['s'])) : '';
  $filters = gwseq_sanitize_horse_share_filters($_POST['filters'] ?? array());
  wp_send_json_success(array('resultats' => gwseq_horse_share_search_chevaux($search, $filters)));
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

  wp_enqueue_style('gwseq-media-placeholder', GWSEQ_MODULE_URL . 'assets/gws-media-placeholder.css', array(), GWSEQ_MODULE_VERSION);
  wp_enqueue_style('gwseq-cheval-share-admin', GWSEQ_MODULE_URL . 'assets/cheval-share-admin.css', array('gwseq-media-placeholder'), GWSEQ_MODULE_VERSION);
  wp_enqueue_script('gwseq-cheval-share-admin', GWSEQ_MODULE_URL . 'assets/cheval-share-admin.js', array(), GWSEQ_MODULE_VERSION, true);
  wp_localize_script('gwseq-cheval-share-admin', 'gwseqPartager', array(
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce(GWSEQ_HORSE_SHARE_NONCE_ACTION),
    'recents' => gwseq_horse_share_recent_chevaux(),
    'filters' => array(
      'sexe' => gwseq_horse_share_sexe_filter_options(),
      'statut' => gwseq_cheval_statut_commercial_options(),
      'categories' => gwseq_horse_share_categorie_filter_options(),
      'anneeMin' => GWSEQ_CHEVAL_ANNEE_MIN,
      'anneeMax' => gwseq_cheval_annee_naissance_max(),
    ),
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
      'allSexe' => __('Tous', 'gws-core'),
      'allStatut' => __('Tous', 'gws-core'),
      'allCategories' => __('Toutes les catégories', 'gws-core'),
      'yearFrom' => __('De', 'gws-core'),
      'yearTo' => __('à', 'gws-core'),
      'resetFilters' => __('Réinitialiser les filtres', 'gws-core'),
    ),
  ));
}
add_action('admin_enqueue_scripts', 'gwseq_enqueue_horse_share_admin_assets');

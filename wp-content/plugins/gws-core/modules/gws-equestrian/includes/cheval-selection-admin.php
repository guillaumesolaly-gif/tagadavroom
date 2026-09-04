<?php
/**
 * Sélection de plusieurs chevaux — écran métier BO (Suite V1 « Partager & vendre », Lot 2A),
 * `Chevaux → Sélections`.
 *
 * Ce fichier ne fait QUE la glue WordPress (menu, sécurité, rendu de l'écran, points d'entrée
 * AJAX, actions de gestion du token) : toute la RÈGLE métier (éligibilité, persistance,
 * résolution d'affichage, token) vit dans includes/cheval-selection.php, jamais dupliquée ici —
 * même séparation que includes/cheval-share.php / includes/cheval-share-admin.php.
 *
 * RÉUTILISATION DU MOTEUR DE RECHERCHE (§7 de la demande : "réutiliser le moteur de recherche/
 * filtrage déjà construit pour Partager un cheval") : gwseq_selection_search_chevaux() ci-dessous
 * réutilise EXACTEMENT gwseq_sanitize_horse_share_filters()/gwseq_horse_share_filters_to_query_
 * args()/gwseq_horse_share_query_chevaux()/gwseq_horse_share_lightweight_row() (includes/
 * cheval-share-admin.php) — jamais une seconde implémentation de la recherche/du filtrage. La
 * SEULE différence avec l'écran « Partager » : les chevaux "En préparation" sont systématiquement
 * exclus des résultats (§5 de la demande), quel que soit le filtre "État de diffusion" choisi —
 * une restriction supplémentaire au niveau de la requête, jamais une réécriture du moteur.
 *
 * PÉRIMÈTRE LOT 2A : cet écran couvre la CRÉATION d'une sélection (recherche/filtres/sélection
 * multiple/ordre/titre) et la LISTE des sélections déjà créées avec la gestion de leur token
 * (régénérer/révoquer, mêmes mécanismes qu'includes/cheval-share-admin.php pour le partage privé
 * Cheval — liens admin-post.php nonce-protégés, jamais un formulaire imbriqué). La MODIFICATION
 * d'une sélection existante (ajouter/retirer un cheval, réordonner, changer le titre après
 * création — §14 de la demande) est un développement du Lot 2B, volontairement absent ici : aucun
 * bouton "Modifier"/"Ouvrir" n'est donc proposé sur la liste dans cette version.
 */

if (!defined('ABSPATH')) exit;

const GWSEQ_SELECTION_NONCE_ACTION = 'gwseq_selection';
const GWSEQ_SELECTION_SEARCH_LIMIT = 20;
const GWSEQ_SELECTION_RECENT_LIMIT = 20;

function gwseq_selection_menu_slug() {
  return 'gwseq-selections';
}

function gwseq_selection_page_url($args = array()) {
  return add_query_arg(
    array_merge(array('post_type' => GWSEQ_CPT_CHEVAL, 'page' => gwseq_selection_menu_slug()), $args),
    admin_url('edit.php')
  );
}

function gwseq_add_selection_page() {
  add_submenu_page(
    'edit.php?post_type=' . GWSEQ_CPT_CHEVAL,
    __('Sélections', 'gws-core'),
    __('Sélections', 'gws-core'),
    'edit_posts',
    gwseq_selection_menu_slug(),
    'gwseq_render_selection_page'
  );
}
add_action('admin_menu', 'gwseq_add_selection_page');

/**
 * Même restriction que l'écran « Partager » (§21 de la demande initiale, réutilisée telle quelle,
 * jamais une seconde règle) : un auteur sans `edit_others_posts` ne voit/ne gère que SES propres
 * sélections, exactement comme il ne voit déjà que ses propres chevaux dans la recherche.
 */
function gwseq_selection_current_user_restricted_to_own() {
  return !current_user_can('edit_others_posts');
}

/* -------------------------------------------------------------------------------------------
 * Recherche de chevaux éligibles (§5/§7 de la demande) — réutilise le moteur existant, avec
 * l'exclusion supplémentaire des chevaux "En préparation".
 * ----------------------------------------------------------------------------------------- */

/**
 * Les deux seuls états qui peuvent entrer dans une sélection (§5) — réutilise EXCLUSIVEMENT
 * gwseq_horse_diffusion_states()/gwseq_cheval_ids_by_diffusion_state() (includes/cheval-share.php)
 * comme sources de vérité, jamais un recalcul depuis `post_status`.
 */
function gwseq_selection_eligible_diffusion_states() {
  return array(GWSEQ_HORSE_DIFFUSION_PRIVEE, GWSEQ_HORSE_DIFFUSION_VISIBLE_SITE);
}

function gwseq_selection_eligible_cheval_ids() {
  $ids = array();
  foreach (gwseq_selection_eligible_diffusion_states() as $state) {
    $ids = array_merge($ids, gwseq_cheval_ids_by_diffusion_state($state));
  }
  return $ids;
}

/**
 * Options du filtre "État de diffusion" pour CET écran uniquement (§5) : "En préparation" n'y
 * figure jamais — le proposer laisserait croire qu'il peut produire des résultats, alors que ce
 * filtre renverrait toujours une liste vide sur cet écran précis. Réutilise le même libellé
 * (gwseq_horse_diffusion_state_label()) que partout ailleurs, jamais un second vocabulaire.
 */
function gwseq_selection_diffusion_filter_options() {
  $options = array();
  foreach (gwseq_selection_eligible_diffusion_states() as $state) {
    $options[$state] = gwseq_horse_diffusion_state_label($state);
  }
  return $options;
}

/**
 * Source de résultats de CET écran (§5/§7) : mêmes filtres cumulables que « Partager »
 * (recherche/diffusion/sexe/statut/année/catégorie, gwseq_horse_share_filters_to_query_args()),
 * avec la contrainte supplémentaire "jamais En préparation" appliquée via `post__in`, intersectée
 * avec toute restriction déjà posée par le filtre "État de diffusion" de l'utilisateur — jamais
 * une seconde requête ni un recalcul de la restriction déjà en place.
 */
function gwseq_selection_search_chevaux($search = '', $filters = array()) {
  $filters = gwseq_sanitize_horse_share_filters($filters);
  // "En préparation" n'étant jamais une valeur valide sur cet écran (voir gwseq_selection_
  // diffusion_filter_options() ci-dessus), toute valeur reçue malgré tout est ignorée — jamais une
  // erreur, simplement traitée comme "aucun filtre de diffusion" (repli sur la restriction globale
  // ci-dessous, qui exclut de toute façon "En préparation").
  if ($filters['diffusion'] !== '' && !in_array($filters['diffusion'], gwseq_selection_eligible_diffusion_states(), true)) {
    $filters['diffusion'] = '';
  }

  $args = array('posts_per_page' => GWSEQ_SELECTION_SEARCH_LIMIT);
  if ($search !== '') {
    $args['s'] = $search;
  } else {
    $args['posts_per_page'] = GWSEQ_SELECTION_RECENT_LIMIT;
    $args['orderby'] = 'modified';
    $args['order'] = 'DESC';
  }
  $args = array_merge($args, gwseq_horse_share_filters_to_query_args($filters));

  $eligible_ids = gwseq_selection_eligible_cheval_ids();
  $args['post__in'] = isset($args['post__in']) ? array_values(array_intersect($args['post__in'], $eligible_ids)) : $eligible_ids;
  if (!$args['post__in']) $args['post__in'] = array(0);

  $ids = gwseq_horse_share_query_chevaux($args);
  return array_map('gwseq_horse_share_lightweight_row', $ids);
}

function gwseq_selection_recent_chevaux() {
  return gwseq_selection_search_chevaux();
}

/* -------------------------------------------------------------------------------------------
 * Liste des sélections existantes (§13 de la demande, dans la limite du Lot 2A : titre/date/
 * nombre de chevaux diffusables/lien/gestion du token — pas de "Ouvrir/Modifier", voir note de
 * fichier en tête).
 * ----------------------------------------------------------------------------------------- */

function gwseq_selection_query_ids() {
  $args = array(
    'post_type' => GWSEQ_CPT_SELECTION,
    'post_status' => 'publish',
    'fields' => 'ids',
    'posts_per_page' => -1,
    'orderby' => 'date',
    'order' => 'DESC',
  );
  if (gwseq_selection_current_user_restricted_to_own()) $args['author'] = get_current_user_id();

  $query = new WP_Query($args);
  return $query->posts;
}

function gwseq_selection_user_can_manage($selection_id) {
  $selection_id = (int) $selection_id;
  if ($selection_id <= 0 || get_post_type($selection_id) !== GWSEQ_CPT_SELECTION) return false;
  if (!current_user_can('edit_posts')) return false;
  if (!gwseq_selection_current_user_restricted_to_own()) return true;
  $post = get_post($selection_id);
  return (bool) ($post && (int) $post->post_author === get_current_user_id());
}

function gwseq_selection_admin_row($selection_id) {
  $cheval_ids = gwseq_selection_get_cheval_ids($selection_id);
  return array(
    'id' => $selection_id,
    'titre' => gwseq_selection_display_title($selection_id),
    'date' => get_the_date('', $selection_id),
    'total_chevaux' => count($cheval_ids),
    'chevaux_diffusables' => gwseq_selection_diffusable_count($selection_id),
    'token_actif' => gwseq_selection_is_active($selection_id),
    'url' => gwseq_selection_url($selection_id),
    'url_regenerer' => gwseq_selection_action_url('regenerer', $selection_id),
    'url_revoquer' => gwseq_selection_action_url('revoquer', $selection_id),
  );
}

function gwseq_selection_admin_rows() {
  return array_map('gwseq_selection_admin_row', gwseq_selection_query_ids());
}

/* -------------------------------------------------------------------------------------------
 * Écran.
 * ----------------------------------------------------------------------------------------- */

function gwseq_render_selection_page() {
  if (!current_user_can('edit_posts')) wp_die(esc_html__('Action non autorisée.', 'gws-core'));
  ?>
  <div class="wrap gwseq-selections">
    <h1><?php esc_html_e('Sélections', 'gws-core'); ?></h1>
    <div id="gwseq-selections-app"></div>
  </div>
  <?php
}

/* -------------------------------------------------------------------------------------------
 * AJAX — recherche (lecture seule, même sécurité que l'écran « Partager », §21).
 * ----------------------------------------------------------------------------------------- */

function gwseq_selection_ajax_check_general() {
  check_ajax_referer(GWSEQ_SELECTION_NONCE_ACTION, 'nonce');
  if (!current_user_can('edit_posts')) wp_send_json_error(array('message' => __('Action non autorisée.', 'gws-core')), 403);
}

function gwseq_ajax_selection_search_cheval() {
  gwseq_selection_ajax_check_general();

  $search = isset($_POST['s']) ? sanitize_text_field(wp_unslash($_POST['s'])) : '';
  $filters = gwseq_sanitize_horse_share_filters($_POST['filters'] ?? array());
  wp_send_json_success(array('resultats' => gwseq_selection_search_chevaux($search, $filters)));
}
add_action('wp_ajax_gwseq_selection_search_cheval', 'gwseq_ajax_selection_search_cheval');

/**
 * Création (§7/§17 de la demande) — écriture. Chaque ID soumis est revérifié SERVEUR (jamais une
 * confiance dans ce que le client affirme avoir sélectionné) : appartenance au CPT Cheval,
 * capacité `edit_post` sur CE cheval précis (même exigence que gwseq_ajax_partager_get_cheval()),
 * ET éligibilité de diffusion (§5) — tout ID qui échoue l'une de ces vérifications est simplement
 * IGNORÉ (jamais une erreur bloquante pour le reste de la sélection, §19 : "données malformées").
 * gwseq_selection_create() (includes/cheval-selection.php) réapplique de toute façon lui-même le
 * filtre d'éligibilité en défense en profondeur.
 */
function gwseq_selection_sanitize_submitted_cheval_ids($raw_ids) {
  $ids = array();
  foreach ((array) $raw_ids as $raw_id) {
    $id = absint($raw_id);
    if (!$id || in_array($id, $ids, true)) continue;
    if (get_post_type($id) !== GWSEQ_CPT_CHEVAL) continue;
    if (!current_user_can('edit_post', $id)) continue;
    if (!gwseq_selection_horse_is_eligible($id)) continue;
    $ids[] = $id;
  }
  return $ids;
}

function gwseq_ajax_selection_create() {
  gwseq_selection_ajax_check_general();

  $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
  $cheval_ids = gwseq_selection_sanitize_submitted_cheval_ids($_POST['cheval_ids'] ?? array());

  if (!$cheval_ids) {
    wp_send_json_error(array('message' => __('Sélectionnez au moins un cheval.', 'gws-core')), 400);
  }

  $selection_id = gwseq_selection_create(array(
    'title' => $title,
    'cheval_ids' => $cheval_ids,
    'author' => get_current_user_id(),
  ));

  if (!$selection_id) {
    wp_send_json_error(array('message' => __('La sélection n’a pas pu être créée.', 'gws-core')), 500);
  }

  wp_send_json_success(array(
    'redirect' => gwseq_selection_page_url(),
  ));
}
add_action('wp_ajax_gwseq_selection_create', 'gwseq_ajax_selection_create');

/* -------------------------------------------------------------------------------------------
 * Gestion du token (régénérer/révoquer) — mêmes mécanismes que le partage privé Cheval
 * (includes/cheval-share-admin.php, §21 de la demande initiale) : liens admin-post.php
 * nonce-protégés (`check_admin_referer()`), jamais un formulaire imbriqué ni une action AJAX pour
 * un geste ponctuel et rare. Réutilise gwseq_selection_activate()/gwseq_selection_revoke()
 * (includes/cheval-selection.php) — jamais un second calcul de token ici.
 * ----------------------------------------------------------------------------------------- */

const GWSEQ_SELECTION_ACTION_NONCE_PREFIX = 'gwseq_selection_action';

function gwseq_selection_action_url($action, $selection_id) {
  $url = add_query_arg(
    array('action' => 'gwseq_selection_' . $action, 'selection_id' => (int) $selection_id),
    admin_url('admin-post.php')
  );
  return wp_nonce_url($url, GWSEQ_SELECTION_ACTION_NONCE_PREFIX . '_' . (int) $selection_id);
}

function gwseq_selection_handle_admin_post($activate) {
  $selection_id = isset($_REQUEST['selection_id']) ? absint($_REQUEST['selection_id']) : 0;
  check_admin_referer(GWSEQ_SELECTION_ACTION_NONCE_PREFIX . '_' . $selection_id);

  if (!gwseq_selection_user_can_manage($selection_id)) {
    wp_die(esc_html__('Action non autorisée.', 'gws-core'), '', array('response' => 403));
  }

  if ($activate) {
    gwseq_selection_activate($selection_id);
  } else {
    gwseq_selection_revoke($selection_id);
  }

  wp_safe_redirect(gwseq_selection_page_url());
  exit;
}

function gwseq_selection_admin_post_regenerate() {
  gwseq_selection_handle_admin_post(true);
}
add_action('admin_post_gwseq_selection_regenerer', 'gwseq_selection_admin_post_regenerate');

function gwseq_selection_admin_post_revoke() {
  gwseq_selection_handle_admin_post(false);
}
add_action('admin_post_gwseq_selection_revoquer', 'gwseq_selection_admin_post_revoke');

/* -------------------------------------------------------------------------------------------
 * Assets — uniquement sur l'écran Sélections.
 * ----------------------------------------------------------------------------------------- */

function gwseq_enqueue_selection_admin_assets($hook) {
  $screen = function_exists('get_current_screen') ? get_current_screen() : null;
  if (!$screen || strpos($screen->id, gwseq_selection_menu_slug()) === false) return;

  wp_enqueue_style('gwseq-media-placeholder', GWSEQ_MODULE_URL . 'assets/gws-media-placeholder.css', array(), GWSEQ_MODULE_VERSION);
  wp_enqueue_style('gwseq-cheval-selection-admin', GWSEQ_MODULE_URL . 'assets/cheval-selection-admin.css', array('gwseq-media-placeholder'), GWSEQ_MODULE_VERSION);
  wp_enqueue_script('gwseq-cheval-selection-admin', GWSEQ_MODULE_URL . 'assets/cheval-selection-admin.js', array(), GWSEQ_MODULE_VERSION, true);
  wp_localize_script('gwseq-cheval-selection-admin', 'gwseqSelections', array(
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce(GWSEQ_SELECTION_NONCE_ACTION),
    'recents' => gwseq_selection_recent_chevaux(),
    'existantes' => gwseq_selection_admin_rows(),
    'nouvelleUrl' => gwseq_selection_page_url(array('vue' => 'nouvelle')),
    'listeUrl' => gwseq_selection_page_url(),
    'filters' => array(
      'sexe' => gwseq_horse_share_sexe_filter_options(),
      'statut' => gwseq_cheval_statut_commercial_options(),
      'categories' => gwseq_horse_share_categorie_filter_options(),
      'anneeMin' => GWSEQ_CHEVAL_ANNEE_MIN,
      'anneeMax' => gwseq_cheval_annee_naissance_max(),
      'diffusion' => gwseq_selection_diffusion_filter_options(),
    ),
    'i18n' => array(
      'title' => __('Sélections', 'gws-core'),
      'newSelection' => __('+ Nouvelle sélection', 'gws-core'),
      'backToList' => __('← Retour aux sélections', 'gws-core'),
      'searchPlaceholder' => __('Rechercher un cheval...', 'gws-core'),
      'noResults' => __('Aucun cheval trouvé.', 'gws-core'),
      'add' => __('Ajouter', 'gws-core'),
      'remove' => __('Retirer', 'gws-core'),
      'up' => __('Monter', 'gws-core'),
      'down' => __('Descendre', 'gws-core'),
      'titleLabel' => __('Titre (facultatif)', 'gws-core'),
      'titlePlaceholder' => __('Ex. Chevaux pour Guillaume', 'gws-core'),
      'selectedCountZero' => __('Aucun cheval sélectionné', 'gws-core'),
      'selectedCountOne' => __('1 cheval sélectionné', 'gws-core'),
      'selectedCountMany' => __('%d chevaux sélectionnés', 'gws-core'),
      'createSelection' => __('Créer la sélection', 'gws-core'),
      'creating' => __('Création…', 'gws-core'),
      'createError' => __('La sélection n’a pas pu être créée.', 'gws-core'),
      'allSexe' => __('Tous', 'gws-core'),
      'allStatut' => __('Tous', 'gws-core'),
      'allCategories' => __('Toutes les catégories', 'gws-core'),
      'allDiffusion' => __('Tous les états de diffusion', 'gws-core'),
      'yearFrom' => __('De', 'gws-core'),
      'yearTo' => __('à', 'gws-core'),
      'resetFilters' => __('Réinitialiser les filtres', 'gws-core'),
      'sexeFilterLabel' => __('Sexe', 'gws-core'),
      'statutFilterLabel' => __('Statut commercial', 'gws-core'),
      'categorieFilterLabel' => __('Catégorie', 'gws-core'),
      'anneeFilterLabel' => __('Année de naissance', 'gws-core'),
      'diffusionFilterLabel' => __('État de diffusion', 'gws-core'),
      'emptyList' => __('Aucune sélection créée pour le moment.', 'gws-core'),
      'columnTitle' => __('Titre', 'gws-core'),
      'columnDate' => __('Date', 'gws-core'),
      'columnChevaux' => __('Chevaux diffusables', 'gws-core'),
      'columnLink' => __('Lien', 'gws-core'),
      'columnActions' => __('Actions', 'gws-core'),
      'copyLink' => __('Copier le lien', 'gws-core'),
      'copied' => __('Lien copié', 'gws-core'),
      'regenerate' => __('Régénérer', 'gws-core'),
      'revoke' => __('Révoquer', 'gws-core'),
      'revoked' => __('Lien révoqué', 'gws-core'),
      'confirmRevoke' => __('Révoquer ce lien ? L’ancien lien cessera immédiatement de fonctionner, la sélection reste conservée.', 'gws-core'),
      'confirmRegenerate' => __('Régénérer ce lien ? L’ancien lien cessera immédiatement de fonctionner.', 'gws-core'),
    ),
  ));
}
add_action('admin_enqueue_scripts', 'gwseq_enqueue_selection_admin_assets');

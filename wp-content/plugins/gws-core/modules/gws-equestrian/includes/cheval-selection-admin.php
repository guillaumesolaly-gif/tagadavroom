<?php
/**
 * Sélection de plusieurs chevaux — écran métier BO (Suite V1 « Partager & vendre », Lot 2B),
 * `Chevaux → Sélections`.
 *
 * Ce fichier ne fait QUE la glue WordPress (menu, sécurité, rendu de l'écran, points d'entrée
 * AJAX, action de suppression) : toute la RÈGLE métier (éligibilité, persistance, résolution
 * d'affichage, modification, suppression) vit dans includes/cheval-selection.php, jamais dupliquée
 * ici — même séparation que includes/cheval-share.php / includes/cheval-share-admin.php.
 *
 * RÉUTILISATION DU MOTEUR DE RECHERCHE (§7 de la demande initiale) : gwseq_selection_search_
 * chevaux() ci-dessous réutilise EXACTEMENT gwseq_sanitize_horse_share_filters()/gwseq_horse_
 * share_filters_to_query_args()/gwseq_horse_share_query_chevaux()/gwseq_horse_share_lightweight_
 * row() (includes/cheval-share-admin.php) — jamais une seconde implémentation de la recherche/du
 * filtrage. La SEULE différence avec l'écran « Partager » : les chevaux "En préparation" sont
 * systématiquement exclus des résultats (§5), quel que soit le filtre "État de diffusion" choisi.
 *
 * AJUSTEMENT DE MODÈLE (recette 2A -> Lot 2B) : plus de "Révoquer"/"Régénérer" dans cette
 * interface — une sélection existante EST une sélection active, avec un lien stable tant qu'elle
 * existe. Y mettre fin se fait en la SUPPRIMANT (corbeille WordPress, gwseq_selection_delete()) ;
 * le token reste un mécanisme technique interne (includes/cheval-selection.php), plus aucun point
 * d'entrée BO ne l'expose. Le titre d'une sélection dans la liste l'OUVRE pour modification (au
 * lieu du simple lien de partage) : ajouter/retirer un cheval, réordonner, changer le titre — sans
 * jamais toucher au token (le lien déjà envoyé reste identique après une modification).
 *
 * PÉRIMÈTRE LOT 2B : création, modification (titre/liste/ordre), suppression, page destinataire
 * (rendu + route web, voir includes/cheval-selection-front.php). Toujours volontairement absent :
 * message de partage/Open Graph/WhatsApp-SMS-Copier/PDF/QR code/catalogue/mobile/refonte
 * graphique générale du BO.
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

/**
 * URL qui ouvre une sélection existante pour modification (§2 de l'ajustement de recette : "le
 * titre de la sélection dans la liste devra permettre de rouvrir la sélection côté BO") — même
 * écran, même app JS (aucune seconde interface), un simple paramètre `vue`/`selection_id` comme
 * pour la création (`?vue=nouvelle`).
 */
function gwseq_selection_edit_url($selection_id) {
  return gwseq_selection_page_url(array('vue' => 'modifier', 'selection_id' => (int) $selection_id));
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
 * Recherche de chevaux éligibles (§5/§7 de la demande initiale) — réutilise le moteur existant,
 * avec l'exclusion supplémentaire des chevaux "En préparation".
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
 * Liste des sélections existantes (§4 de l'ajustement de recette : titre/date/chevaux diffusables/
 * lien/actions Ouvrir-modifier/Copier/Supprimer — plus de "Révoquer"/"Régénérer"/"Activer").
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
    'url' => gwseq_selection_url($selection_id),
    'url_modifier' => gwseq_selection_edit_url($selection_id),
    'url_supprimer' => gwseq_selection_action_url('supprimer', $selection_id),
  );
}

function gwseq_selection_admin_rows() {
  return array_map('gwseq_selection_admin_row', gwseq_selection_query_ids());
}

/**
 * Ligne d'un cheval pour l'écran de MODIFICATION (§2) : réutilise la même ligne légère que la
 * recherche (gwseq_horse_share_lightweight_row(), includes/cheval-share-admin.php — nom/photo/
 * sous-titre), enrichie du statut "displayable" (§6 : un cheval déjà présent reste affiché même
 * s'il est devenu "En préparation", pour que l'utilisateur puisse voir ce qu'il retire). Un cheval
 * supprimé entre-temps (§19) reste représenté, jamais une ligne qui ferait planter l'écran.
 */
function gwseq_selection_admin_editable_cheval_row($cheval_id) {
  $resolved = gwseq_selection_resolve_cheval($cheval_id);
  if (!$resolved['exists']) {
    return array('id' => $cheval_id, 'nom' => __('Cheval supprimé', 'gws-core'), 'photo_url' => '', 'sous_titre' => '', 'displayable' => false);
  }
  $row = gwseq_horse_share_lightweight_row($cheval_id);
  $row['displayable'] = $resolved['displayable'];
  return $row;
}

function gwseq_selection_admin_editable_chevaux($selection_id) {
  return array_map('gwseq_selection_admin_editable_cheval_row', gwseq_selection_get_cheval_ids($selection_id));
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
 * AJAX.
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
 * Sanitation des IDs soumis par le client (§7/§17 de la demande initiale) — jamais une confiance
 * dans ce que le client affirme avoir sélectionné. `$keep_ids` (Lot 2B, §2/§6 de l'ajustement) est
 * la liste ACTUELLE d'une sélection en cours de modification : un ID déjà présent y est conservé
 * SANS revérifier son éligibilité de diffusion actuelle (§6 — un cheval repassé "En préparation"
 * reste dans la liste tant qu'il n'est pas explicitement retiré), seul un ID RÉELLEMENT NOUVEAU
 * doit passer les mêmes contrôles qu'à la création (appartenance au CPT Cheval, capacité
 * `edit_post`, éligibilité §5). Pour une création, `$keep_ids` est vide : tout ID est alors "un
 * nouvel ajout", comportement inchangé du Lot 2A.
 */
function gwseq_selection_sanitize_submitted_cheval_ids($raw_ids, $keep_ids = array()) {
  $ids = array();
  foreach ((array) $raw_ids as $raw_id) {
    $id = absint($raw_id);
    if (!$id || in_array($id, $ids, true)) continue;
    if (get_post_type($id) !== GWSEQ_CPT_CHEVAL) continue;
    if (in_array($id, $keep_ids, true)) {
      $ids[] = $id;
      continue;
    }
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

/**
 * Données d'une sélection existante pour l'écran de modification (§2) — capacité vérifiée sur
 * CETTE sélection précise (gwseq_selection_user_can_manage()), jamais seulement la capacité
 * générale de l'écran.
 */
function gwseq_ajax_selection_get() {
  gwseq_selection_ajax_check_general();

  $selection_id = isset($_POST['selection_id']) ? absint($_POST['selection_id']) : 0;
  if (!gwseq_selection_user_can_manage($selection_id)) {
    wp_send_json_error(array('message' => __('Sélection introuvable ou non autorisée.', 'gws-core')), 403);
  }

  wp_send_json_success(array(
    'id' => $selection_id,
    // Titre BRUT (peut être vide) — l'écran de modification doit refléter la donnée réellement
    // stockée, jamais le libellé de repli "Sélection de chevaux" (qui resterait un placeholder
    // d'affichage, jamais une valeur à réinjecter dans le champ de saisie).
    'titre' => get_the_title($selection_id),
    'chevaux' => gwseq_selection_admin_editable_chevaux($selection_id),
  ));
}
add_action('wp_ajax_gwseq_selection_get', 'gwseq_ajax_selection_get');

/**
 * Modification (§2 de l'ajustement de recette) — ne touche JAMAIS au token (gwseq_selection_
 * update(), includes/cheval-selection.php, n'accepte d'ailleurs même pas ce paramètre). Mêmes
 * règles de sanitation que la création, avec conservation des chevaux déjà présents (voir
 * gwseq_selection_sanitize_submitted_cheval_ids() ci-dessus).
 */
function gwseq_ajax_selection_update() {
  gwseq_selection_ajax_check_general();

  $selection_id = isset($_POST['selection_id']) ? absint($_POST['selection_id']) : 0;
  if (!gwseq_selection_user_can_manage($selection_id)) {
    wp_send_json_error(array('message' => __('Sélection introuvable ou non autorisée.', 'gws-core')), 403);
  }

  $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
  $current_ids = gwseq_selection_get_cheval_ids($selection_id);
  $cheval_ids = gwseq_selection_sanitize_submitted_cheval_ids($_POST['cheval_ids'] ?? array(), $current_ids);

  if (!$cheval_ids) {
    wp_send_json_error(array('message' => __('Sélectionnez au moins un cheval.', 'gws-core')), 400);
  }

  gwseq_selection_update($selection_id, array('title' => $title, 'cheval_ids' => $cheval_ids));

  wp_send_json_success(array('redirect' => gwseq_selection_page_url()));
}
add_action('wp_ajax_gwseq_selection_update', 'gwseq_ajax_selection_update');

/* -------------------------------------------------------------------------------------------
 * Suppression (§1 de l'ajustement de recette) — REMPLACE "Révoquer"/"Régénérer" comme seule
 * action de fin de vie d'une sélection. Lien admin-post.php nonce-protégé, jamais un formulaire
 * imbriqué ni une action AJAX pour un geste ponctuel, exactement le même mécanisme que le partage
 * privé Cheval (includes/cheval-share-admin.php).
 * ----------------------------------------------------------------------------------------- */

const GWSEQ_SELECTION_ACTION_NONCE_PREFIX = 'gwseq_selection_action';

/**
 * CORRECTIF DE RECETTE (bug bloquant constaté sur l'ancien bouton "Révoquer", même construction
 * réutilisée ici pour "Supprimer") — CAUSE RACINE : `wp_nonce_url()` (WordPress core) applique
 * `esc_html()` à son résultat par conception, car il est prévu pour être imprimé TEL QUEL dans un
 * attribut HTML (`href="..."`) — un contexte où le navigateur DÉCODE nativement les entités HTML
 * (`&amp;`/`&#038;` -> `&`) au moment de PARSER le document. Cette URL-ci n'est JAMAIS imprimée
 * dans du HTML côté serveur : elle transite en JSON (`wp_localize_script()`, voir gwseq_selection_
 * admin_row()) puis est assignée directement à la propriété JS `.href` d'un `<a>` (assets/cheval-
 * selection-admin.js) — un contexte qui n'effectue JAMAIS ce décodage. L'entité HTML restait donc
 * littéralement dans l'URL réellement soumise par le navigateur au clic, cassant le nonce ET le
 * reste de la requête (symptôme observé : "Le lien suivi est expiré").
 *
 * CORRECTIF (pas un contournement) : construit le nonce manuellement (même action précise, même
 * nom de paramètre `_wpnonce` par défaut de `check_admin_referer()`), sans jamais passer par
 * `wp_nonce_url()` — aucune protection retirée (capacité, ID de sélection, nonce spécifique à
 * cette sélection, type de post restent vérifiés à l'identique dans gwseq_selection_handle_
 * delete_admin_post()/gwseq_selection_user_can_manage()), seul l'échappement HTML indu est retiré
 * à la source.
 */
function gwseq_selection_action_url($action, $selection_id) {
  $url = add_query_arg(
    array('action' => 'gwseq_selection_' . $action, 'selection_id' => (int) $selection_id),
    admin_url('admin-post.php')
  );
  $nonce_action = GWSEQ_SELECTION_ACTION_NONCE_PREFIX . '_' . (int) $selection_id;
  return add_query_arg('_wpnonce', wp_create_nonce($nonce_action), $url);
}

function gwseq_selection_handle_delete_admin_post() {
  $selection_id = isset($_REQUEST['selection_id']) ? absint($_REQUEST['selection_id']) : 0;
  check_admin_referer(GWSEQ_SELECTION_ACTION_NONCE_PREFIX . '_' . $selection_id);

  if (!gwseq_selection_user_can_manage($selection_id)) {
    wp_die(esc_html__('Action non autorisée.', 'gws-core'), '', array('response' => 403));
  }

  gwseq_selection_delete($selection_id);

  wp_safe_redirect(gwseq_selection_page_url());
  exit;
}
add_action('admin_post_gwseq_selection_supprimer', 'gwseq_selection_handle_delete_admin_post');

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
      'saveChanges' => __('Enregistrer les modifications', 'gws-core'),
      'saving' => __('Enregistrement…', 'gws-core'),
      'updateError' => __('La sélection n’a pas pu être modifiée.', 'gws-core'),
      'loading' => __('Chargement…', 'gws-core'),
      'loadError' => __('Impossible de charger cette sélection.', 'gws-core'),
      'editSelectionTitle' => __('Modifier la sélection', 'gws-core'),
      'notDisplayable' => __('actuellement non diffusable', 'gws-core'),
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
      'delete' => __('Supprimer', 'gws-core'),
      'confirmDelete' => __('Supprimer cette sélection ? Le lien déjà envoyé ne fonctionnera plus.', 'gws-core'),
    ),
  ));
}
add_action('admin_enqueue_scripts', 'gwseq_enqueue_selection_admin_assets');

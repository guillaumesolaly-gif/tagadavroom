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
    'nom' => gwseq_horse_share_decode_title(get_the_title($post_id)),
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

  // Filtre "État de diffusion" (audit UX/métier suivant, §"réutiliser la logique de recherche déjà
  // existante") — mêmes trois valeurs que gwseq_horse_diffusion_states() (includes/cheval-share.php,
  // seule source de vérité), jamais un second vocabulaire.
  $diffusion = isset($raw['diffusion']) ? sanitize_key(wp_unslash($raw['diffusion'])) : '';
  if ($diffusion !== '' && !in_array($diffusion, gwseq_horse_diffusion_states(), true)) $diffusion = '';

  return array(
    'sexe' => $sexe,
    'statut' => $statut,
    'annee_min' => $annee_min,
    'annee_max' => $annee_max,
    'categorie' => $categorie,
    'diffusion' => $diffusion,
  );
}

/**
 * Transforme des filtres déjà sanitisés en arguments WP_Query cumulables (§4 : "tous les filtres
 * doivent être cumulatifs") — jamais une reconstruction de requête ad hoc dans chaque appelant.
 */
function gwseq_horse_share_filters_to_query_args($filters) {
  $filters = wp_parse_args(is_array($filters) ? $filters : array(), array(
    'sexe' => '', 'statut' => '', 'annee_min' => 0, 'annee_max' => 0, 'categorie' => '', 'diffusion' => '',
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

  // État de diffusion — dérivé (statut WordPress + token), jamais exprimable par un meta_query/
  // tax_query direct (même raisonnement que le filtre équivalent de la liste d'administration, voir
  // gwseq_apply_cheval_admin_list_filters(), includes/cheval-fields.php) : restreint via `post__in`,
  // à partir de gwseq_cheval_ids_by_diffusion_state() — seule source de vérité, jamais recalculée.
  if ($filters['diffusion'] !== '') {
    $ids = gwseq_cheval_ids_by_diffusion_state($filters['diffusion']);
    $args['post__in'] = $ids ?: array(0);
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
 * Libellés du filtre "État de diffusion" (audit UX/métier suivant) — réutilise EXCLUSIVEMENT
 * gwseq_horse_diffusion_states()/gwseq_horse_diffusion_state_label() (includes/cheval-share.php)
 * comme source de vérité, jamais un second vocabulaire ni un recalcul de l'état.
 */
function gwseq_horse_share_diffusion_filter_options() {
  $options = array();
  foreach (gwseq_horse_diffusion_states() as $state) {
    $options[$state] = gwseq_horse_diffusion_state_label($state);
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
 *
 * Correctif de recette (vérification explicite de "Ajouter la fiche complète", §3) : le booléen
 * JavaScript `false` envoyé via `FormData` traverse le réseau comme la CHAÎNE littérale "false"
 * (comportement standard de `FormData`/`$_POST`, aucun booléen natif n'existe en dehors de JSON) —
 * `!empty('false')` vaut TRUE (chaîne non vide), ce qui aurait ignoré silencieusement la case
 * décochée et inclus quand même le lien de fiche. `filter_var(..., FILTER_VALIDATE_BOOLEAN)`
 * interprète correctement "false"/"0"/"" comme faux et "true"/"1" comme vrai, quelle que soit la
 * représentation texte reçue.
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
    'fiche' => filter_var($raw['fiche'] ?? '', FILTER_VALIDATE_BOOLEAN),
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
  gwseq_render_horse_private_share_controls($post);
}

/* -------------------------------------------------------------------------------------------
 * Boîte "État de diffusion" (ajustement UX suivant — "piloter la diffusion avec le vocabulaire
 * GWS") : remplace, UNIQUEMENT pour le CPT Cheval, la boîte native "Publier" de WordPress, qui
 * exposait directement à l'utilisateur le vocabulaire technique `Brouillon`/`Publier`/`État`/
 * `Visibilité` (Publique/Protégée par mot de passe/Privée) — deux modèles contradictoires pour la
 * même donnée, l'un affiché par WordPress, l'autre par la boîte "Partage" du Lot 1. Cette
 * suppression est SCOPÉE au seul post type Cheval (`remove_meta_box('submitdiv', GWSEQ_CPT_CHEVAL,
 * 'side')`, le mécanisme natif documenté pour cet usage précis) — jamais un désenregistrement
 * global : Pages, Actualités, Prestations, Membres et tout autre contenu WordPress conservent leur
 * boîte "Publier" strictement inchangée.
 *
 * `post_status`/`post_password` (mécanismes NATIFS WordPress) restent la source technique sous-
 * jacente, ainsi que le token (includes/cheval-share.php) — AUCUN statut personnalisé créé
 * (`register_post_status()` volontairement absent, aucune nécessité démontrée). En particulier, le
 * statut natif `private` de WordPress N'EST JAMAIS utilisé pour implémenter "Diffusion privée"
 * (qui reste `draft` + token GWS, exactement comme depuis le Lot 1) : les DEUX notions sont
 * homonymes mais distinctes, et le mécanisme GWS (token secret, route dédiée) reste seul responsable
 * de la confidentialité commerciale, jamais un mécanisme de visibilité WordPress natif. De même,
 * "Protégé par mot de passe" ne devient jamais un quatrième état exposé au client : une fiche avec
 * un mot de passe résiduel reste classée "En préparation"/"Diffusion privée" par
 * gwseq_horse_diffusion_state() (qui ne regarde que le statut `publish` et le token, jamais le mot
 * de passe séparément), et gwseq_horse_diffusion_set_visible_site() lève systématiquement ce mot de
 * passe en rendant une fiche visible (sans quoi la transition échouerait silencieusement à produire
 * l'état qu'elle annonce). Voir gwseq_cheval_native_visibility_mismatches() (includes/cheval-
 * fields.php) pour l'audit, non destructif, des fiches existantes qui utiliseraient encore ces
 * mécanismes natifs.
 *
 * TRANSITIONS CENTRALISÉES (§5, préparation mobile) : cette boîte n'est que de la GLUE d'affichage
 * — toute la RÈGLE de chaque transition (statut cible, gestion du token) vit dans les trois
 * fonctions dédiées de includes/cheval-share.php (gwseq_horse_diffusion_set_en_preparation()/
 * _diffusion_privee()/_visible_site()), réutilisables telles quelles par un futur écran mobile,
 * qui ne devra jamais manipuler `post_status`/`post_password`/le token directement.
 *
 * SAUVEGARDE (§3) : comme les boutons "Créer"/"Régénérer" du Lot 1 (voir gwseq_horse_private_share_
 * maybe_activate_on_save() plus bas, même principe), chaque bouton de transition est un VRAI
 * `<button type="submit">` du formulaire d'édition natif — cliquer dessus sauvegarde RÉELLEMENT la
 * fiche (hook natif save_post_{cpt}) avant d'appliquer la transition, en un seul geste. Jamais deux
 * opérations séparées ("Enregistrer le brouillon" PUIS changer la diffusion).
 * ----------------------------------------------------------------------------------------- */

/**
 * Remplace la boîte "Publier" par la boîte "État de diffusion", uniquement pour Cheval. Appelée
 * depuis `add_meta_boxes_{$post_type}` (donc APRÈS que WordPress ait enregistré `submitdiv` dans le
 * gabarit d'édition, cf. wp-admin/edit-form-advanced.php) — le moment exact où `remove_meta_box()`
 * doit être appelé pour retirer une boîte native déjà enregistrée par le cœur.
 */
function gwseq_replace_cheval_publish_box() {
  remove_meta_box('submitdiv', GWSEQ_CPT_CHEVAL, 'side');
  add_meta_box('gwseq-cheval-diffusion', __('État de diffusion', 'gws-core'), 'gwseq_render_cheval_diffusion_box', GWSEQ_CPT_CHEVAL, 'side', 'high');
}
add_action('add_meta_boxes_' . GWSEQ_CPT_CHEVAL, 'gwseq_replace_cheval_publish_box');

/**
 * Champ soumis par CHAQUE bouton de transition ci-dessous — sa VALEUR est directement l'une des
 * trois constantes GWSEQ_HORSE_DIFFUSION_* (includes/cheval-share.php), jamais un verbe d'action
 * distinct à traduire séparément en état cible : un seul vocabulaire, celui des états eux-mêmes.
 */
const GWSEQ_HORSE_DIFFUSION_TRANSITION_FIELD = 'gwseq_diffusion_transition';

/**
 * Rendu de la boîte "État de diffusion" — §1/§2 de la demande. Un unique bouton neutre "Enregistrer"
 * (jamais "Enregistrer le brouillon"/"Publier") est TOUJOURS présent (aucune transition : le champ
 * caché `post_status` préserve le statut WordPress actuel tel quel) ; les boutons de transition
 * varient selon l'état :
 *   - En préparation      -> Activer la diffusion privée ; Rendre visible sur le site (si la
 *     capacité `publish_post` est présente — §4, jamais affiché sinon).
 *   - Diffusion privée     -> Rendre visible sur le site (idem) ; Repasser en préparation.
 *   - Visible sur le site -> "Retirer la fiche du site :" puis DEUX actions explicites (jamais un
 *     "Dépublier" ambigu, §2) : Repasser en préparation OU Activer la diffusion privée — l'utilisateur
 *     choisit directement l'état cible, sans écran intermédiaire.
 * Le lien privé et ses actions de gestion (URL, Régénérer, Révoquer) restent dans la boîte
 * "Partage" (gwseq_render_horse_private_share_controls()) : cette boîte-ci ne pilote que le CHANGEMENT
 * d'état, jamais la gestion fine d'un token déjà actif.
 */
function gwseq_render_cheval_diffusion_box($post) {
  if (!current_user_can('edit_post', $post->ID)) return;

  $state = gwseq_horse_diffusion_state($post->ID);
  $can_publish = current_user_can('publish_post', $post->ID);

  echo '<input type="hidden" name="post_status" value="' . esc_attr($post->post_status) . '">';
  echo '<p><strong>' . esc_html__('État de diffusion :', 'gws-core') . '</strong> ' . esc_html(gwseq_horse_diffusion_state_label($state)) . '</p>';

  $save_label = $state === GWSEQ_HORSE_DIFFUSION_EN_PREPARATION ? __('Enregistrer', 'gws-core') : __('Enregistrer les modifications', 'gws-core');
  echo '<p><button type="submit" class="button button-primary" style="width:100%;">' . esc_html($save_label) . '</button></p>';

  $transition_button = function ($target, $label) {
    echo '<p><button type="submit" class="button" style="width:100%;" name="' . esc_attr(GWSEQ_HORSE_DIFFUSION_TRANSITION_FIELD) . '" value="' . esc_attr($target) . '">' . esc_html($label) . '</button></p>';
  };

  if ($state === GWSEQ_HORSE_DIFFUSION_EN_PREPARATION) {
    $transition_button(GWSEQ_HORSE_DIFFUSION_PRIVEE, __('Activer la diffusion privée', 'gws-core'));
    if ($can_publish) {
      $transition_button(GWSEQ_HORSE_DIFFUSION_VISIBLE_SITE, __('Rendre visible sur le site', 'gws-core'));
    }
  } elseif ($state === GWSEQ_HORSE_DIFFUSION_PRIVEE) {
    if ($can_publish) {
      $transition_button(GWSEQ_HORSE_DIFFUSION_VISIBLE_SITE, __('Rendre visible sur le site', 'gws-core'));
    }
    $transition_button(GWSEQ_HORSE_DIFFUSION_EN_PREPARATION, __('Repasser en préparation', 'gws-core'));
  } else {
    echo '<p class="description">' . esc_html__('Retirer la fiche du site :', 'gws-core') . '</p>';
    $transition_button(GWSEQ_HORSE_DIFFUSION_EN_PREPARATION, __('Repasser en préparation', 'gws-core'));
    $transition_button(GWSEQ_HORSE_DIFFUSION_PRIVEE, __('Activer la diffusion privée', 'gws-core'));
  }
}

/**
 * Applique la transition demandée — greffée sur le MÊME hook natif save_post_{cpt} que le reste du
 * module (jamais une seconde requête/un second point d'entrée), après gwseq_save_cheval_meta()
 * (priorité 20, même convention que gwseq_horse_private_share_maybe_activate_on_save() ci-dessous) :
 * la fiche est donc déjà réellement enregistrée au moment où la transition s'applique (§3).
 *
 * SÉCURITÉ (§4) : `edit_post` pour toute transition (défense en profondeur — post.php a déjà
 * vérifié ce droit avant même de déclencher save_post) ; en PLUS, `publish_post` spécifiquement
 * avant de rendre une fiche visible sur le site — la SEULE transition qui l'exige (repasser en
 * préparation ou activer la diffusion privée, y compris depuis "Visible sur le site", ne rend
 * jamais rien MOINS visible qu'avant : WordPress n'exige jamais cette capacité pour retirer une
 * fiche de la publication, seulement pour l'y faire entrer).
 *
 * RÉENTRANCE : gwseq_horse_diffusion_set_*() appelle wp_update_post(), qui redéclenche
 * save_post_{cpt} — ce gestionnaire se désenregistre donc de lui-même le temps de son propre appel
 * (motif standard WordPress), pour ne jamais boucler indéfiniment ni ré-appliquer deux fois la même
 * transition.
 */
function gwseq_horse_apply_diffusion_transition_on_save($post_id) {
  if (empty($_POST[GWSEQ_HORSE_DIFFUSION_TRANSITION_FIELD])) return;
  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
  if (function_exists('wp_is_post_revision') && wp_is_post_revision($post_id)) return;
  if (!current_user_can('edit_post', $post_id)) return;

  $target = sanitize_key(wp_unslash($_POST[GWSEQ_HORSE_DIFFUSION_TRANSITION_FIELD]));
  $valid_targets = array(GWSEQ_HORSE_DIFFUSION_EN_PREPARATION, GWSEQ_HORSE_DIFFUSION_PRIVEE, GWSEQ_HORSE_DIFFUSION_VISIBLE_SITE);
  if (!in_array($target, $valid_targets, true)) return;

  if ($target === GWSEQ_HORSE_DIFFUSION_VISIBLE_SITE && !current_user_can('publish_post', $post_id)) return;

  remove_action('save_post_' . GWSEQ_CPT_CHEVAL, 'gwseq_horse_apply_diffusion_transition_on_save', 20);
  if ($target === GWSEQ_HORSE_DIFFUSION_EN_PREPARATION) {
    gwseq_horse_diffusion_set_en_preparation($post_id);
  } elseif ($target === GWSEQ_HORSE_DIFFUSION_PRIVEE) {
    gwseq_horse_diffusion_set_diffusion_privee($post_id);
  } else {
    gwseq_horse_diffusion_set_visible_site($post_id);
  }
  add_action('save_post_' . GWSEQ_CPT_CHEVAL, 'gwseq_horse_apply_diffusion_transition_on_save', 20);
}
add_action('save_post_' . GWSEQ_CPT_CHEVAL, 'gwseq_horse_apply_diffusion_transition_on_save', 20);

/* -------------------------------------------------------------------------------------------
 * Partage privé (suite V1 « Partager & vendre », Lot 1) — §2.B de la demande : un cheval que le
 * professionnel ne veut pas exposer publiquement doit pouvoir être envoyé quand même à des
 * acheteurs précis, via un lien secret révocable/régénérable. Toute la logique de token
 * (génération/lecture/activation/révocation/URL/recherche inverse) vit dans includes/cheval-
 * share.php (couche métier, réutilisable sans connaître wp-admin) ; ce fichier-ci ne fait QUE la
 * glue WordPress : règle de réécriture, rendu de la route privée, et les deux actions nonce-
 * protégées (activer/révoquer).
 *
 * AJUSTEMENT D'ARCHITECTURE (recette) : visibilité publique et existence d'un token privé sont
 * DÉCOUPLÉES — un token n'a plus JAMAIS d'effet sur une fiche publique (ni blocage du permalink
 * normal, ni exclusion recherche/sitemap/REST, toutes deux RETIRÉES ci-dessous — voir la note à
 * leur ancien emplacement). La boîte latérale "Partage" (gwseq_render_horse_private_share_controls()
 * ci-dessous) adapte son vocabulaire à la visibilité RÉELLE du cheval (gwseq_horse_is_publicly_
 * viewable(), includes/cheval-share.php) : jamais présenté comme le mode principal pour un cheval
 * déjà public.
 *
 * AUDIT UX/MÉTIER (recette suivante) : la boîte affiche désormais explicitement l'état MÉTIER de la
 * fiche ("En préparation"/"Diffusion privée"/"Visible sur le site" — gwseq_horse_diffusion_state(),
 * includes/cheval-share.php — jamais "Brouillon"/"Publié"), et les boutons "Créer"/"Régénérer" sont
 * de VRAIS boutons de soumission du formulaire d'édition (plus des liens admin-post.php) afin de
 * toujours enregistrer correctement la fiche avant d'activer le partage privé — voir
 * gwseq_horse_private_share_maybe_activate_on_save() plus bas pour le détail et sa cause racine.
 * ----------------------------------------------------------------------------------------- */

const GWSEQ_HORSE_PRIVATE_SHARE_QUERY_VAR = 'gwseq_partage_token';
const GWSEQ_HORSE_PRIVATE_SHARE_NONCE_ACTION = 'gwseq_partage_prive';

/**
 * CAUSE RACINE d'un bug de recette bloquant (premier test réel du Lot 1) : cette boîte est rendue
 * DANS la boîte latérale "Partage" de l'écran d'édition WordPress, elle-même DÉJÀ à l'intérieur du
 * grand `<form id="post" method="post" action="post.php">` qui enveloppe TOUT l'écran d'édition
 * (titre, contenu, TOUTES les boîtes, bouton Publier/Mettre à jour). Un `<form>` imbriqué dans un
 * autre est INVALIDE en HTML : le navigateur ignore/aplatit la balise `<form>` interne, si bien que
 * cliquer sur un bouton pensé pour soumettre CE formulaire soumettait en réalité le grand formulaire
 * EXTÉRIEUR de l'écran d'édition (vers `post.php`, avec son propre champ cachÉ `action` en conflit
 * avec le nôtre) — d'où la redirection constatée vers la liste "Actualités" (repli générique de
 * `post.php` pour une valeur de `$_POST['action']` qu'il ne reconnaît pas), sans jamais atteindre
 * notre gestionnaire `admin-post.php`.
 *
 * CORRECTIF (pas un contournement) : remplace les `<form>` imbriqués par de simples liens
 * `<a class="button">` protégés par nonce (`gwseq_horse_private_share_action_url()`) — exactement
 * le même schéma que les actions de ligne natives de WordPress ("Corbeille", "Restaurer"...), qui
 * ne sont JAMAIS des formulaires imbriqués. `admin-post.php` traite indifféremment GET et POST
 * (`$_REQUEST['action']`), et `check_admin_referer()` valide un nonce transmis en GET tout aussi
 * bien qu'en POST — volontairement PAS un point d'entrée AJAX supplémentaire : créer/révoquer/
 * régénérer un lien privé est une action ponctuelle et rare, contrairement aux interactions
 * fréquentes de l'écran Partager (recherche, cases à cocher) qui, elles, justifient l'AJAX. Rendu
 * dans la MÊME boîte latérale que le bouton "Partager ce cheval" — jamais une seconde interface.
 */
/**
 * Nom du champ soumis par les boutons "Créer"/"Régénérer" ci-dessous (audit UX/métier — §2 : risque
 * de données non sauvegardées). Vit dans le formulaire d'édition standard de WordPress (aucun nonce
 * séparé nécessaire : ce formulaire est déjà protégé par le nonce natif `update-post_{id}`, vérifié
 * par post.php AVANT même de déclencher save_post — voir gwseq_horse_private_share_maybe_activate_
 * on_save() plus bas).
 */
const GWSEQ_HORSE_PRIVATE_SHARE_SUBMIT_FIELD = 'gwseq_partage_prive_submit_activer';

/**
 * Trois états MÉTIER (gwseq_horse_diffusion_state(), includes/cheval-share.php — fonction centrale
 * réutilisée telle quelle, jamais recalculée ici), adaptés à la visibilité RÉELLE du cheval, jamais
 * au seul fait qu'un token existe :
 *   1. Visible sur le site               -> indique que le partage utilise déjà la fiche publique ;
 *      un ancien token qui traînerait encore n'est JAMAIS présenté comme le mode principal, seule
 *      l'action "Révoquer" reste proposée (§ ajustement d'architecture, jamais remis en cause ici).
 *   2. Diffusion privée (non public + token actif) -> affiche l'URL privée + Régénérer/Révoquer.
 *   3. En préparation (non public + aucun token)    -> renvoie vers la boîte "État de diffusion"
 *      (ci-dessous, gwseq_render_cheval_diffusion_box()) pour ACTIVER la diffusion privée — cette
 *      boîte-ci ne fait plus que GÉRER un partage déjà actif (URL/Régénérer/Révoquer), jamais le
 *      créer, afin de ne présenter qu'un SEUL point d'entrée par transition (§ ajustement suivant —
 *      "piloter la diffusion avec le vocabulaire GWS" : centraliser les transitions, jamais deux
 *      boutons différents pour la même opération).
 * Le libellé de cet état métier est affiché en tête de la boîte "État de diffusion", pas ici — cette
 * boîte-ci ne le répète plus (éviter toute ambiguïté entre deux affichages du même état).
 */
function gwseq_render_horse_private_share_controls($post) {
  if (!current_user_can('edit_post', $post->ID)) return;

  $state = gwseq_horse_diffusion_state($post->ID);
  echo '<hr>';
  echo '<p><strong>' . esc_html__('Partage privé', 'gws-core') . '</strong></p>';

  if ($state === GWSEQ_HORSE_DIFFUSION_VISIBLE_SITE) {
    echo '<p class="description">' . esc_html__('Ce cheval est visible sur le site : le partage utilise la fiche publique du site.', 'gws-core') . '</p>';
    if (gwseq_horse_private_share_is_active($post->ID)) {
      $url = gwseq_horse_private_share_url($post->ID);
      echo '<p class="description">' . esc_html__('Un ancien lien de partage privé reste valide (créé avant que la fiche soit visible sur le site) — il n’est plus nécessaire, mais continue de fonctionner pour ne pas casser un lien déjà envoyé.', 'gws-core') . '</p>';
      echo '<p><input type="text" readonly value="' . esc_attr($url) . '" style="width:100%;" onclick="this.select();"></p>';
      echo '<p><a class="button" href="' . esc_url(gwseq_horse_private_share_action_url('revoquer', $post->ID)) . '">' . esc_html__('Révoquer cet ancien lien', 'gws-core') . '</a></p>';
    }
    return;
  }

  if ($state === GWSEQ_HORSE_DIFFUSION_PRIVEE) {
    $url = gwseq_horse_private_share_url($post->ID);
    echo '<p class="description">' . esc_html__('Ce cheval est accessible uniquement via ce lien secret, jamais publiquement (recherche, catalogue, sitemap).', 'gws-core') . '</p>';
    echo '<p><input type="text" readonly value="' . esc_attr($url) . '" style="width:100%;" onclick="this.select();"></p>';
    echo '<p>';
    echo '<button type="submit" class="button" style="margin-right:6px;" name="' . esc_attr(GWSEQ_HORSE_PRIVATE_SHARE_SUBMIT_FIELD) . '" value="1">' . esc_html__('Régénérer (invalide l’ancien lien)', 'gws-core') . '</button>';
    echo '<a class="button" href="' . esc_url(gwseq_horse_private_share_action_url('revoquer', $post->ID)) . '">' . esc_html__('Révoquer', 'gws-core') . '</a>';
    echo '</p>';
    echo '<p class="description">' . esc_html__('Enregistre également les modifications en cours de cette fiche.', 'gws-core') . '</p>';
  } else {
    echo '<p class="description">' . esc_html__('Aucun lien de partage privé actif. Utilisez « Activer la diffusion privée » dans la boîte « État de diffusion » ci-dessus pour en créer un.', 'gws-core') . '</p>';
  }
}

/**
 * AUDIT UX/MÉTIER — §2 : risque de données non sauvegardées. CAUSE RACINE identifiée en recette :
 * "Créer un lien de partage privé"/"Régénérer" étaient rendus comme de simples liens `<a>` vers
 * admin-post.php (navigation GET immédiate, hors du formulaire d'édition) — si l'utilisateur avait
 * modifié des champs de la fiche (identité, commercialisation...) sans avoir cliqué "Enregistrer"
 * au préalable, ces modifications étaient PERDUES au moment du clic, sans qu'il en soit informé : il
 * pouvait croire à tort que "sa fiche vient d'être mise en diffusion privée" alors que ses dernières
 * modifications n'avaient jamais atteint la base.
 *
 * CORRECTIF RETENU (sauvegarder correctement AVANT l'activation, plutôt que bloquer l'action) : les
 * deux boutons ci-dessus sont désormais de VRAIS `<button type="submit">` du MÊME formulaire
 * d'édition `<form id="post">` (contrairement à "Révoquer", resté un lien admin-post.php — révoquer
 * un accès ne prétend jamais refléter des données à jour, aucun risque de fausse impression pour
 * cette action précise). Cliquer dessus soumet donc RÉELLEMENT toute la fiche vers post.php, exactement
 * comme n'importe quelle sauvegarde, ce qui déclenche nativement `save_post_{cpt}` — les mêmes hooks
 * qu'un enregistrement normal (gwseq_save_cheval_meta() compris), SANS AUCUNE ligne de logique de
 * persistance dupliquée ici. Une fois la fiche réellement enregistrée par ce mécanisme NATIF,
 * cette fonction (greffée sur ce MÊME hook, priorité 20 — après la sauvegarde des champs métier)
 * active/régénère le partage privé : jamais un second aller-retour, jamais de requête admin-post.php
 * séparée pour ces deux actions. Volontairement PAS une simulation de clic sur "Enregistrer le
 * brouillon" (aucun JavaScript ne déclenche un autre bouton à la place de l'utilisateur) : le champ
 * `GWSEQ_HORSE_PRIVATE_SHARE_SUBMIT_FIELD` est sa propre action explicite, portée par son propre
 * bouton, qui se trouve simplement produire, comme effet de bord assumé et documenté, une
 * sauvegarde réelle et complète de la fiche.
 */
function gwseq_horse_private_share_maybe_activate_on_save($post_id) {
  if (empty($_POST[GWSEQ_HORSE_PRIVATE_SHARE_SUBMIT_FIELD])) return;
  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
  if (function_exists('wp_is_post_revision') && wp_is_post_revision($post_id)) return;
  if (!current_user_can('edit_post', $post_id)) return;

  gwseq_horse_private_share_activate($post_id);
}
add_action('save_post_' . GWSEQ_CPT_CHEVAL, 'gwseq_horse_private_share_maybe_activate_on_save', 20);

/**
 * Construit l'URL nonce-protégée d'une action de partage privé — POINT UNIQUE de construction,
 * jamais reconstruite ailleurs (le lien "Régénérer" réutilise l'action "activer", identique à une
 * création puisque c'est la même opération, voir gwseq_horse_private_share_activate()). `$action`
 * est l'un des deux suffixes réellement enregistrés ci-dessous ('activer'/'revoquer'), jamais une
 * valeur libre.
 */
function gwseq_horse_private_share_action_url($action, $cheval_id) {
  $url = add_query_arg(
    array('action' => 'gwseq_partage_prive_' . $action, 'cheval_id' => (int) $cheval_id),
    admin_url('admin-post.php')
  );
  return wp_nonce_url($url, GWSEQ_HORSE_PRIVATE_SHARE_NONCE_ACTION . '_' . (int) $cheval_id);
}

/**
 * Prédicat extrait à part (jamais mêlé à wp_die()/redirection ci-dessous) précisément pour rester
 * testable unitairement : mêmes règles que partout ailleurs dans ce fichier — la fiche doit
 * exister, être un Cheval, et l'utilisateur courant doit pouvoir l'éditer (§16 : "utilisateur sans
 * permission ne peut pas générer un lien pour un cheval qu'il ne peut pas éditer").
 */
function gwseq_horse_private_share_user_can_manage($cheval_id) {
  $cheval_id = (int) $cheval_id;
  return $cheval_id > 0 && get_post_type($cheval_id) === GWSEQ_CPT_CHEVAL && current_user_can('edit_post', $cheval_id);
}

/**
 * URL de retour après une action de partage privé — extraite à part (jamais mêlée à
 * wp_safe_redirect()/exit ci-dessous) pour rester testable unitairement, et pour ne JAMAIS renvoyer
 * une URL vide : `get_edit_post_link()` peut renvoyer une chaîne vide dans des cas limites (capacité
 * réévaluée différemment entre-temps, type de post modifié) — un repli sur la liste des Chevaux
 * garantit toujours un atterrissage cohérent plutôt qu'un repli WordPress générique vers le
 * Tableau de bord. `get_edit_post_link()`/`admin_url()` ne produisent jamais qu'une URL interne à ce
 * site (jamais une entrée utilisateur) : aucun risque d'open redirect ici, quel que soit le résultat.
 */
function gwseq_horse_private_share_redirect_url_after_action($cheval_id) {
  $url = get_edit_post_link($cheval_id, 'raw');
  return $url ? $url : admin_url('edit.php?post_type=' . GWSEQ_CPT_CHEVAL);
}

function gwseq_horse_private_share_handle_admin_post($activate) {
  $cheval_id = isset($_REQUEST['cheval_id']) ? absint($_REQUEST['cheval_id']) : 0;
  check_admin_referer(GWSEQ_HORSE_PRIVATE_SHARE_NONCE_ACTION . '_' . $cheval_id);

  if (!gwseq_horse_private_share_user_can_manage($cheval_id)) {
    wp_die(esc_html__('Action non autorisée.', 'gws-core'), '', array('response' => 403));
  }

  if ($activate) {
    gwseq_horse_private_share_activate($cheval_id);
  } else {
    gwseq_horse_private_share_revoke($cheval_id);
  }

  wp_safe_redirect(gwseq_horse_private_share_redirect_url_after_action($cheval_id));
  exit;
}

function gwseq_horse_private_share_admin_post_activate() {
  gwseq_horse_private_share_handle_admin_post(true);
}
add_action('admin_post_gwseq_partage_prive_activer', 'gwseq_horse_private_share_admin_post_activate');

function gwseq_horse_private_share_admin_post_revoke() {
  gwseq_horse_private_share_handle_admin_post(false);
}
add_action('admin_post_gwseq_partage_prive_revoquer', 'gwseq_horse_private_share_admin_post_revoke');

/* -------------------------------------------------------------------------------------------
 * Route front `/partage/{token}` — §2.B : accessible sans compte par quiconque possède le lien,
 * pour n'importe quel post_status (brouillon inclus, voir gwseq_horse_private_share_find_cheval_id()
 * dans cheval-share.php). Réutilise get_single_template() — la hiérarchie de gabarits NATIVE de
 * WordPress, jamais un second système de rendu : dès qu'un gabarit dédié single-gwseq_cheval.php
 * existera côté thème, cette route l'utilisera automatiquement sans aucune modification ici.
 * ----------------------------------------------------------------------------------------- */

function gwseq_horse_private_share_register_rewrite() {
  add_rewrite_tag('%' . GWSEQ_HORSE_PRIVATE_SHARE_QUERY_VAR . '%', '([a-f0-9]{64})');
  add_rewrite_rule(
    '^' . GWSEQ_HORSE_PRIVATE_SHARE_REWRITE_BASE . '/([a-f0-9]{64})/?$',
    'index.php?' . GWSEQ_HORSE_PRIVATE_SHARE_QUERY_VAR . '=$matches[1]',
    'top'
  );
}
add_action('init', 'gwseq_horse_private_share_register_rewrite');

/**
 * Directives HTTP à envoyer sur TOUTE réponse de la route de partage privé (trouvée ou non) —
 * correctif de recette : une révocation/régénération doit être immédiate, y compris en présence
 * d'un cache plein-page, d'un reverse proxy ou d'un CDN placé devant WordPress ; sans directive
 * explicite, l'un de ces intermédiaires pourrait continuer à servir une fiche PRIVÉE après sa
 * révocation. `no-store` est la seule directive comprise SANS AMBIGUÏTÉ par absolument tout
 * intermédiaire HTTP comme "ne jamais mettre en cache cette réponse" — envoyée explicitement ici en
 * plus de `nocache_headers()` (déjà utilisée par ailleurs dans ce fichier), dont l'exact contenu
 * peut varier selon la version de WordPress. Retourne des DONNÉES (jamais un side-effect direct) :
 * reste testable unitairement sans dépendre de l'état réel des en-têtes HTTP du processus — voir
 * gwseq_horse_private_share_send_nocache_headers() pour l'envoi réel.
 */
function gwseq_horse_private_share_nocache_header_values() {
  return array(
    array('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private'),
    array('Pragma', 'no-cache'),
  );
}

/**
 * Envoie réellement les directives ci-dessus, PLUS `nocache_headers()` native (en-têtes historiques
 * que certains outils reconnaissent spécifiquement) et la constante `DONOTCACHEPAGE` — convention
 * de facto reconnue par la plupart des plugins de cache plein-page WordPress (WP Super Cache,
 * W3 Total Cache, WP Rocket...) pour exclure une requête précise de leur cache, indépendamment des
 * en-têtes HTTP eux-mêmes. Jamais appelée pour une fiche PUBLIQUE : uniquement depuis la route
 * `/partage/{token}` ci-dessous — le comportement de cache d'une fiche publique reste strictement
 * inchangé.
 */
function gwseq_horse_private_share_send_nocache_headers() {
  if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
  nocache_headers();
  if (!headers_sent()) {
    foreach (gwseq_horse_private_share_nocache_header_values() as $header) {
      header($header[0] . ': ' . $header[1]);
    }
  }
}

function gwseq_horse_private_share_render() {
  $token = get_query_var(GWSEQ_HORSE_PRIVATE_SHARE_QUERY_VAR, '');
  if ($token === '') return;

  $cheval_id = gwseq_horse_private_share_find_cheval_id($token);
  if (!$cheval_id) {
    global $wp_query;
    $wp_query->set_404();
    status_header(404);
    gwseq_horse_private_share_send_nocache_headers();
    include get_404_template();
    exit;
  }

  global $post, $wp_query;
  $post = get_post($cheval_id);
  setup_postdata($post);
  $wp_query->is_404 = false;
  $wp_query->is_singular = true;
  $wp_query->is_single = true;
  $wp_query->queried_object = $post;
  $wp_query->queried_object_id = $post->ID;
  $wp_query->post = $post;
  $wp_query->posts = array($post);
  $wp_query->post_count = 1;
  $wp_query->found_posts = 1;
  $wp_query->max_num_pages = 1;

  status_header(200);
  gwseq_horse_private_share_send_nocache_headers();
  include get_single_template();
  exit;
}
add_action('template_redirect', 'gwseq_horse_private_share_render', 10);

/* -------------------------------------------------------------------------------------------
 * Exclusion recherche/archive/sitemap/REST — RETIRÉE (ajustement d'architecture, recette) : ces
 * filtres excluaient tout cheval PORTANT UN TOKEN, indépendamment de son statut réel — exactement
 * ce que la nouvelle règle interdit ("un token ne doit jamais dégrader ni masquer une fiche
 * publique valide"). Un cheval en mode "partage privé EXCLUSIF" (voir gwseq_horse_is_private_share_
 * only(), includes/cheval-share.php) est PAR CONSTRUCTION dans un post_status autre que "publish"
 * (brouillon, privé...) : WordPress exclut déjà NATIVEMENT ce statut de la recherche/archive/
 * taxonomie front-end, du sitemap natif (WP_Sitemaps_Posts ne requête jamais que les posts
 * "publish"), et de l'API REST (le contrôleur applique `read_post`/statut pour tout accès direct ou
 * listé) — sans aucun code supplémentaire. Un cheval réellement PUBLIC, lui, doit désormais
 * apparaître normalement partout, même avec un ancien token qui traîne : ces filtres l'en auraient
 * empêché à tort.
 * ----------------------------------------------------------------------------------------- */

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
      // Audit UX/métier suivant — même vocabulaire que la liste d'administration (§ "réutiliser
      // exclusivement gwseq_horse_diffusion_state() comme source de vérité") : jamais un second
      // référentiel de libellés.
      'diffusion' => gwseq_horse_share_diffusion_filter_options(),
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
      // §3 de la suite « Partager & vendre » : vocabulaire utilisateur, l'ancien libellé "Ajouter
      // la fiche complète" laissait entendre à tort un choix de permalink à comprendre — GWS
      // détermine désormais lui-même le lien approprié (voir gwseq_horse_share_fiche_info(),
      // includes/cheval-share.php) ; seul le libellé change selon qu'il s'agit d'un lien public ou
      // privé (fiche_type), jamais le comportement de sélection lui-même.
      'ficheLabel' => __('Inclure le lien vers la fiche', 'gws-core'),
      'ficheLabelPrivee' => __('Inclure le lien privé vers la fiche', 'gws-core'),
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
      // Libellés VISIBLES des groupes de filtres (correctif de recette — distincts des libellés de
      // l'option "Tous"/"Toutes les catégories" ci-dessus, qui restent le contenu de l'option, pas
      // le nom du champ) : "Sexe" / "Statut commercial" / "Catégorie" / "Année de naissance".
      'sexeFilterLabel' => __('Sexe', 'gws-core'),
      'statutFilterLabel' => __('Statut commercial', 'gws-core'),
      'categorieFilterLabel' => __('Catégorie', 'gws-core'),
      'anneeFilterLabel' => __('Année de naissance', 'gws-core'),
      'diffusionFilterLabel' => __('État de diffusion', 'gws-core'),
      'allDiffusion' => __('Tous les états de diffusion', 'gws-core'),
    ),
  ));
}
add_action('admin_enqueue_scripts', 'gwseq_enqueue_horse_share_admin_assets');

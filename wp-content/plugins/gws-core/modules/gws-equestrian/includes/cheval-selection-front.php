<?php
/**
 * Sélection de plusieurs chevaux — page destinataire publique (Suite V1 « Partager & vendre »,
 * Lot 2B, §3 de l'ajustement de recette).
 *
 * Route `/selection/{token}/`, accessible SANS COMPTE par quiconque possède le lien — même
 * architecture que la route de partage privé Cheval (`/partage/{token}`, includes/cheval-share-
 * admin.php) : règle de réécriture dédiée, query var, résolution par token AVANT toute chose,
 * 404 natif si le token est inconnu/invalide/périmé (sélection supprimée). Cette route n'est PAS
 * le permalink natif du CPT `gwseq_selection` (`public => false`, aucun rewrite déclaré côté
 * post-types.php) — elle vit entièrement à part, sur le modèle déjà validé pour Cheval.
 *
 * RENDU "FONCTIONNEL ET RÉUTILISABLE, PAS UN GABARIT GRAPHIQUE FIGÉ" (§9 de la demande initiale,
 * §3 de l'ajustement) : le design final du front GWS n'existe pas encore. Ce fichier construit
 * donc son propre document HTML minimal (jamais via get_single_template()/le Loop WordPress, qui
 * supposeraient un gabarit de thème adapté à ce post type — inexistant et non pertinent ici,
 * `gwseq_selection` n'étant pas un contenu que le thème connaît). `wp_head()`/`wp_footer()` sont
 * néanmoins appelés normalement : les styles/scripts globaux déjà enregistrés par le thème/les
 * plugins continuent de charger, pour que le thème puisse cibler les classes stables ci-dessous
 * (`gwseq-selection-page`...) le jour où son design sera prêt — sans qu'aucun code ne doive changer
 * ici. Un tout petit CSS utilitaire (assets/cheval-selection-public.css) assure juste une lisibilité
 * minimale en attendant, jamais un habillage graphique élaboré.
 *
 * DONNÉES : composées par gwseq_selection_get_public_view()/gwseq_selection_get_public_card()
 * (includes/cheval-selection.php, couche métier pure) — ce fichier-ci ne fait QUE les afficher,
 * jamais un second calcul d'éligibilité/de prix/de lien de fiche.
 *
 * CONFIDENTIALITÉ : `noindex, nofollow` systématique, aucune mise en cache (mêmes en-têtes que la
 * route de partage privé Cheval), jamais d'ID WordPress exposé (seul le token figure dans l'URL).
 */

if (!defined('ABSPATH')) exit;

const GWSEQ_SELECTION_PUBLIC_QUERY_VAR = 'gwseq_selection_token';

function gwseq_selection_register_rewrite() {
  add_rewrite_tag('%' . GWSEQ_SELECTION_PUBLIC_QUERY_VAR . '%', '([a-f0-9]{64})');
  add_rewrite_rule(
    '^' . GWSEQ_SELECTION_REWRITE_BASE . '/([a-f0-9]{64})/?$',
    'index.php?' . GWSEQ_SELECTION_PUBLIC_QUERY_VAR . '=$matches[1]',
    'top'
  );
}
add_action('init', 'gwseq_selection_register_rewrite');

/**
 * Mêmes directives que gwseq_horse_private_share_send_nocache_headers() (includes/cheval-share-
 * admin.php, jamais un second choix de directives) : une sélection modifiée/supprimée doit
 * cesser/refléter son changement immédiatement, y compris derrière un cache plein-page/CDN.
 */
function gwseq_selection_send_nocache_headers() {
  if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
  nocache_headers();
  if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private');
    header('Pragma: no-cache');
  }
}

/**
 * Une carte HTML par cheval présentable — réutilise gwseq_render_media_placeholder() (includes/
 * admin-ui.php, §9 : "réutiliser le placeholder média existant") pour l'absence de photo, jamais
 * une image de remplacement fabriquée ici. Chaque champ (identité/accroche/prix/lien) reste
 * indépendamment facultatif — absent si la donnée l'est (aucune invention, §2 de la demande
 * initiale).
 */
function gwseq_selection_render_public_card($card) {
  echo '<li class="gwseq-selection-page__card">';
  echo '<div class="gwseq-selection-page__card-media">';
  if ($card['photo_url'] !== '') {
    echo '<img class="gwseq-selection-page__card-photo" src="' . esc_url($card['photo_url']) . '" alt="">';
  } else {
    echo gwseq_render_media_placeholder('gwseq-selection-page__card-photo');
  }
  echo '</div>';
  echo '<div class="gwseq-selection-page__card-body">';
  echo '<h2 class="gwseq-selection-page__card-nom">' . esc_html($card['nom']) . '</h2>';
  if ($card['identite_label'] !== '') {
    echo '<p class="gwseq-selection-page__card-identite">' . esc_html($card['identite_label']) . '</p>';
  }
  if ($card['accroche'] !== '') {
    echo '<p class="gwseq-selection-page__card-accroche">' . esc_html($card['accroche']) . '</p>';
  }
  if ($card['prix_label'] !== '') {
    echo '<p class="gwseq-selection-page__card-prix">' . esc_html($card['prix_label']) . '</p>';
  }
  if ($card['fiche_url'] !== '') {
    echo '<a class="gwseq-selection-page__card-lien" href="' . esc_url($card['fiche_url']) . '">' . esc_html__('Voir la fiche', 'gws-core') . '</a>';
  }
  echo '</div>';
  echo '</li>';
}

/**
 * Chargement conditionnel (§9/§3) — hook standard `wp_enqueue_scripts`, jamais un enregistrement
 * inconditionnel qui chargerait ces styles sur tout le reste du site : gated sur la présence de la
 * query var de cette route précise. Réutilise gws-media-placeholder.css (§9 : "réutiliser le
 * placeholder média existant") + `dashicons` (normalement réservé à wp-admin, jamais enregistré
 * côté front par défaut — nécessaire ici pour que l'icône de la vignette de remplacement s'affiche
 * réellement).
 */
function gwseq_selection_enqueue_public_assets() {
  if (get_query_var(GWSEQ_SELECTION_PUBLIC_QUERY_VAR, '') === '') return;
  wp_enqueue_style('dashicons');
  wp_enqueue_style('gwseq-media-placeholder', GWSEQ_MODULE_URL . 'assets/gws-media-placeholder.css', array(), GWSEQ_MODULE_VERSION);
  wp_enqueue_style('gwseq-cheval-selection-public', GWSEQ_MODULE_URL . 'assets/cheval-selection-public.css', array('gwseq-media-placeholder'), GWSEQ_MODULE_VERSION);
}
add_action('wp_enqueue_scripts', 'gwseq_selection_enqueue_public_assets');

/**
 * Document HTML complet (§9/§3) : jamais get_header()/get_footer() (qui supposeraient un contexte
 * de page/article normal, absent ici), mais wp_head()/wp_footer() bien appelés pour les assets
 * globaux déjà enregistrés (§ voir note de fichier en tête) — dont ceux enregistrés ci-dessus.
 */
function gwseq_selection_render_public_html($selection_id) {
  $view = gwseq_selection_get_public_view($selection_id);
  ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php echo esc_html($view['titre']); ?></title>
<?php wp_head(); ?>
</head>
<body class="gwseq-selection-page">
<main class="gwseq-selection-page__main">
<h1 class="gwseq-selection-page__title"><?php echo esc_html($view['titre']); ?></h1>
<?php if (!$view['cartes']) : ?>
<p class="gwseq-selection-page__empty"><?php esc_html_e('Aucun cheval n’est actuellement disponible dans cette sélection.', 'gws-core'); ?></p>
<?php else : ?>
<ul class="gwseq-selection-page__list">
<?php foreach ($view['cartes'] as $card) gwseq_selection_render_public_card($card); ?>
</ul>
<?php endif; ?>
</main>
<?php wp_footer(); ?>
</body>
</html>
  <?php
}

function gwseq_selection_render_public_page() {
  $token = get_query_var(GWSEQ_SELECTION_PUBLIC_QUERY_VAR, '');
  if ($token === '') return;

  $selection_id = gwseq_selection_find_by_token($token);
  if (!$selection_id) {
    global $wp_query;
    $wp_query->set_404();
    status_header(404);
    gwseq_selection_send_nocache_headers();
    include get_404_template();
    exit;
  }

  status_header(200);
  gwseq_selection_send_nocache_headers();
  gwseq_selection_render_public_html($selection_id);
  exit;
}
add_action('template_redirect', 'gwseq_selection_render_public_page', 10);

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
 *
 * LOT 2C (§3 de la demande) : Open Graph ajouté (gwseq_selection_render_og_meta() plus bas) —
 * DONNÉES calculées exclusivement par gwseq_selection_get_og_data() (includes/cheval-
 * selection.php), jamais un second calcul ici, même séparation métier/rendu que le reste de ce
 * fichier. `noindex`/nocache/inaccessibilité sans token restent strictement inchangés : Open Graph
 * n'est qu'un enrichissement de la même page déjà protégée, jamais un second point d'entrée.
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
 * Open Graph (Lot 2C, §3 de la demande) — DONNÉES calculées exclusivement par gwseq_selection_get_
 * og_data() (includes/cheval-selection.php, couche métier pure) : ce fichier-ci n'échoue QUE les
 * balises `<meta>` elles-mêmes, jamais un second calcul de titre/description/image (même
 * séparation que gwseq_selection_render_public_card()/gwseq_selection_get_public_card()
 * ci-dessus). Même forme que gwseq_render_horse_og_meta() (includes/cheval-share.php, jamais une
 * deuxième architecture OG parallèle) : og:type/og:title/og:description/og:url puis og:image(+
 * dimensions) UNIQUEMENT si une image a été trouvée — aucune balise Twitter/X dédiée, exactement
 * comme le partage individuel Cheval, qui n'en émet pas non plus (repose sur le repli natif de
 * Twitter/X vers les balises `og:`, déjà la seule architecture de ce projet). `og:url` reflète le
 * lien RÉELLEMENT partagé (`gwseq_selection_url()`, le même que celui composé dans le message de
 * partage) — jamais un autre identifiant.
 */
function gwseq_selection_render_og_meta($selection_id) {
  $og = gwseq_selection_get_og_data($selection_id);

  echo '<meta property="og:type" content="website">' . "\n";
  echo '<meta property="og:title" content="' . esc_attr($og['title']) . '">' . "\n";
  echo '<meta property="og:description" content="' . esc_attr($og['description']) . '">' . "\n";
  echo '<meta property="og:url" content="' . esc_url(gwseq_selection_url($selection_id)) . '">' . "\n";

  if ($og['image']) {
    echo '<meta property="og:image" content="' . esc_url($og['image']['url']) . '">' . "\n";
    if (!empty($og['image']['width'])) echo '<meta property="og:image:width" content="' . (int) $og['image']['width'] . '">' . "\n";
    if (!empty($og['image']['height'])) echo '<meta property="og:image:height" content="' . (int) $og['image']['height'] . '">' . "\n";
  }
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
 *
 * CORRECTIF DE RECETTE (Lot 2C : deux `<title>` constatés dans le `<head>` produit) — CAUSE
 * EXACTE : le thème déclare `add_theme_support('title-tag')` (wp-content/themes/gws-starter/inc/
 * setup.php), ce qui accroche `_wp_render_title_tag()` (WordPress core, wp-includes/general-
 * template.php) sur le hook `wp_head`, à la priorité 1 — un mécanisme NATIF qui échoue lui-même un
 * `<title>` complet dès que `wp_head()` est appelé, quel que soit le contexte. Cette page appelant
 * `wp_head()` (voir ci-dessus), ce mécanisme s'exécutait donc ICI AUSSI, produisant un second
 * `<title>` (celui du site, WordPress ne reconnaissant pas cette route comme une page/un article
 * réel) EN PLUS de celui déjà écrit à la main juste avant. Vérifié : `/partage/{token}/` (Cheval,
 * gwseq_horse_private_share_render(), includes/cheval-share-admin.php) n'a JAMAIS ce problème — ce
 * fichier réutilise `get_single_template()`, la hiérarchie de gabarits NATIVE de WordPress (donc
 * `get_header()`/`wp_head()` s'exécutent une seule fois, dans le contexte d'une vraie requête
 * singulière simulée), il n'écrit lui-même AUCUN `<title>` manuel nulle part — la même cause
 * architecturale ne s'applique donc pas là-bas, rien n'y a été changé.
 *
 * CORRECTIF : retiré le `<title>` écrit à la main, remplacé par le mécanisme WordPress prévu
 * EXACTEMENT pour ce cas — le filtre `pre_get_document_title`, qui court-circuite
 * `wp_get_document_title()` avant tout autre traitement (celui-là même que `_wp_render_title_tag()`
 * appelle) — plutôt que de supprimer arbitrairement le `<title>` natif ou de filtrer la sortie HTML
 * a posteriori (bufferisation) : UN SEUL `<title>`, rendu UNE SEULE FOIS par le mécanisme natif
 * lui-même. Le filtre est ajouté via une fermeture LOCALE à cet appel précis (jamais un
 * `add_filter()` global gated par une query var) : il n'existe que le temps de CET appel de
 * fonction, qui ne s'exécute lui-même que pour un token de sélection déjà résolu sur cette route
 * précise (voir gwseq_selection_render_public_page() plus bas) — aucun effet possible sur une
 * autre page/article/fiche Cheval/route du site, aucune requête supplémentaire (le titre déjà
 * résolu dans `$view['titre']` est réutilisé tel quel, jamais un second calcul). N'affecte JAMAIS
 * og:title (gwseq_selection_render_og_meta() ci-dessous reste indépendante, sa propre donnée
 * provenant de gwseq_selection_get_og_data()).
 */
function gwseq_selection_render_public_html($selection_id) {
  $view = gwseq_selection_get_public_view($selection_id);
  add_filter('pre_get_document_title', function () use ($view) { return $view['titre']; });
  ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<?php gwseq_selection_render_og_meta($selection_id); ?>
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

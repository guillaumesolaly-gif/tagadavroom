<?php
/**
 * Template Name: Guide
 *
 * Gabarit d'un article de la rubrique Guides. Reste dans ce dossier — détecté sans copie par
 * inc/module-templates.php dès que le module Guides est actif (chemin virtuel attendu tel quel
 * par le module côté plugin, voir GWS_GUIDES_TEMPLATE dans
 * wp-content/plugins/gws-core/modules/guides/module.php).
 */
get_header();
$category = get_post_meta(get_the_ID(), '_gws_guides_category', true);
?>
<main id="contenu" tabindex="-1">
  <?php get_template_part('template-parts/site-header'); ?>
  <div class="container">
    <?php gws_breadcrumb(); ?>
    <?php while (have_posts()) : the_post(); ?>
      <article <?php post_class(); ?>>
        <?php if ($category) : ?><p class="kicker"><?php echo esc_html($category); ?></p><?php endif; ?>
        <h1><?php the_title(); ?></h1>
        <?php the_content(); ?>
      </article>
    <?php endwhile; ?>
    <p><a href="<?php echo esc_url(home_url('/guides/')); ?>">&larr; Retour aux guides</a></p>
  </div>
  <?php get_template_part('template-parts/site-footer'); ?>
</main>
<?php get_footer(); ?>

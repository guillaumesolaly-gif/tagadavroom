<?php
/**
 * Gabarit générique de page éditoriale : contenu administrable dans Gutenberg, aucun texte en
 * dur. Une mise en page distincte (page d'accueil, page avec aside...) est un gabarit
 * PHP séparé dans page-templates/, choisi depuis l'éditeur — pas un fichier par slug.
 */
get_header();
?>
<main id="contenu" tabindex="-1">
  <?php get_template_part('template-parts/site-header'); ?>
  <div class="container">
    <?php gws_breadcrumb(); ?>
    <?php while (have_posts()) : the_post(); ?>
      <article <?php post_class(); ?>>
        <h1><?php the_title(); ?></h1>
        <?php the_content(); ?>
      </article>
    <?php endwhile; ?>
  </div>
  <?php get_template_part('template-parts/site-footer'); ?>
</main>
<?php get_footer(); ?>

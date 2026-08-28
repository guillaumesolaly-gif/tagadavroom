<?php
/**
 * Filet de sécurité ultime de la hiérarchie de gabarits : toujours fonctionnel même si aucun
 * autre gabarit ne correspond.
 */
get_header();
?>
<main id="contenu" tabindex="-1">
  <?php get_template_part('template-parts/site-header'); ?>
  <div class="container">
    <?php if (have_posts()) : ?>
      <?php while (have_posts()) : the_post(); ?>
        <article <?php post_class(); ?>>
          <h1><?php the_title(); ?></h1>
          <?php the_content(); ?>
        </article>
      <?php endwhile; ?>
      <?php get_template_part('template-parts/content/pagination'); ?>
    <?php else : ?>
      <p>Aucun contenu à afficher.</p>
    <?php endif; ?>
  </div>
  <?php get_template_part('template-parts/site-footer'); ?>
</main>
<?php get_footer(); ?>

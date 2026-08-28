<?php
/**
 * Gabarit d'exemple pour le listing du CPT boilerplate (bp_item). À copier à la racine du thème
 * sous le nom archive-{post_type}.php une fois le module métier réel dérivé de ce squelette.
 */
get_header();
?>
<main id="contenu" tabindex="-1">
  <?php get_template_part('template-parts/site-header'); ?>
  <div class="container">
    <header class="archive-header">
      <h1><?php post_type_archive_title(); ?></h1>
    </header>
    <div class="card-grid">
      <?php while (have_posts()) : the_post(); ?>
        <?php get_template_part('template-parts/content/card'); ?>
      <?php endwhile; ?>
    </div>
    <?php get_template_part('template-parts/content/pagination'); ?>
  </div>
  <?php get_template_part('template-parts/site-footer'); ?>
</main>
<?php get_footer(); ?>

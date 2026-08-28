<?php
/**
 * Gabarit générique d'archive (CPT, catégorie, taxonomie) sans gabarit dédié
 * (archive-{post_type}.php prend le pas nativement si un module en fournit un).
 */
get_header();
?>
<main id="contenu" tabindex="-1">
  <?php get_template_part('template-parts/site-header'); ?>
  <div class="container">
    <header class="archive-header">
      <h1><?php the_archive_title(); ?></h1>
      <?php the_archive_description('<div class="archive-description">', '</div>'); ?>
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

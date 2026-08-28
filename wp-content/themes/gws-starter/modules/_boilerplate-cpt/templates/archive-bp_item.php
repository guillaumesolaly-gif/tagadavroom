<?php
/**
 * Gabarit d'exemple pour le listing du CPT boilerplate (bp_item). Reste dans ce dossier
 * (modules/<slug>/templates/archive-{post_type}.php) une fois le module métier réel dérivé de
 * ce squelette : inc/module-templates.php le détecte sans copie dès que le module est actif.
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

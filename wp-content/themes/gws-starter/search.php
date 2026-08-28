<?php
get_header();
?>
<main id="contenu" tabindex="-1">
  <?php get_template_part('template-parts/site-header'); ?>
  <div class="container">
    <header class="archive-header">
      <h1><?php printf('Résultats de recherche pour : %s', '<span>' . esc_html(get_search_query()) . '</span>'); ?></h1>
    </header>
    <?php if (have_posts()) : ?>
      <div class="card-grid">
        <?php while (have_posts()) : the_post(); ?>
          <?php get_template_part('template-parts/content/card'); ?>
        <?php endwhile; ?>
      </div>
      <?php get_template_part('template-parts/content/pagination'); ?>
    <?php else : ?>
      <p>Aucun résultat pour cette recherche.</p>
      <?php get_search_form(); ?>
    <?php endif; ?>
  </div>
  <?php get_template_part('template-parts/site-footer'); ?>
</main>
<?php get_footer(); ?>

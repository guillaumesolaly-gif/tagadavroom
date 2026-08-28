<?php
get_header();
?>
<main id="contenu" tabindex="-1">
  <?php get_template_part('template-parts/site-header'); ?>
  <div class="container">
    <h1>Page non trouvée</h1>
    <p>Le contenu recherché n’existe pas ou plus à cette adresse.</p>
    <a class="btn btn-primary" href="<?php echo esc_url(home_url('/')); ?>">Retour à l’accueil</a>
  </div>
  <?php get_template_part('template-parts/site-footer'); ?>
</main>
<?php get_footer(); ?>

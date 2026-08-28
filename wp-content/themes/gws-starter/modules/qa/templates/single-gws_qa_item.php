<?php
/**
 * Gabarit de test pour un élément QA (gws_qa_item). À copier à la racine du thème sous le nom
 * single-gws_qa_item.php pour vérifier l'affichage du CPT de recette. À supprimer avec le reste
 * du module QA avant un site réel — voir modules/qa/README.md.
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
        <p><strong>Note de test :</strong> <?php echo esc_html(get_post_meta(get_the_ID(), '_gws_qa_note', true)); ?></p>
        <p><strong>Mise en avant :</strong> <?php echo get_post_meta(get_the_ID(), '_gws_qa_featured', true) ? 'Oui' : 'Non'; ?></p>
        <?php the_content(); ?>
      </article>
    <?php endwhile; ?>
  </div>
  <?php get_template_part('template-parts/site-footer'); ?>
</main>
<?php get_footer(); ?>

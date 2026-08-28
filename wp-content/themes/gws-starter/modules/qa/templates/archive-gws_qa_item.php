<?php
/**
 * Gabarit de test pour l'archive du CPT QA (gws_qa_item). À copier à la racine du thème sous le
 * nom archive-gws_qa_item.php. Utilise le même template-part de carte qu'un vrai projet
 * (template-parts/content/card.php), pour le vérifier en conditions réelles de boucle
 * WordPress. À supprimer avec le reste du module QA avant un site réel.
 */
get_header();
?>
<main id="contenu" tabindex="-1">
  <?php get_template_part('template-parts/site-header'); ?>
  <div class="container">
    <header class="archive-header">
      <h1><?php post_type_archive_title(); ?></h1>
    </header>
    <?php if (have_posts()) : ?>
      <div class="card-grid">
        <?php while (have_posts()) : the_post(); ?>
          <?php get_template_part('template-parts/content/card'); ?>
        <?php endwhile; ?>
      </div>
      <?php get_template_part('template-parts/content/pagination'); ?>
    <?php else : ?>
      <p>Aucun élément QA créé pour l’instant — en créer un depuis le menu « QA — Éléments de test » de l’admin pour tester cette archive.</p>
    <?php endif; ?>
  </div>
  <?php get_template_part('template-parts/site-footer'); ?>
</main>
<?php get_footer(); ?>

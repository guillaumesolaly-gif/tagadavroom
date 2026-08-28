<?php
/**
 * Template Name: Guides — Hub
 *
 * Page hub de la rubrique Guides, regroupant par catégorie toutes les pages utilisant le
 * gabarit Guide. Reste dans ce dossier — détecté sans copie par inc/module-templates.php dès
 * que le module Guides est actif (chemin virtuel attendu tel quel par le module côté plugin,
 * voir GWS_GUIDES_HUB_TEMPLATE).
 */
get_header();
$grouped = function_exists('gws_guides_by_category') ? gws_guides_by_category() : array();
?>
<main id="contenu" tabindex="-1">
  <?php get_template_part('template-parts/site-header'); ?>
  <div class="container">
    <h1><?php the_title(); ?></h1>
    <?php foreach ($grouped as $category => $pages) : ?>
      <section class="guides-category">
        <h2><?php echo esc_html($category); ?></h2>
        <div class="card-grid">
          <?php foreach ($pages as $page) : ?>
            <article class="card">
              <div class="card-body">
                <h3 class="card-title"><a href="<?php echo esc_url(get_permalink($page)); ?>"><?php echo esc_html(get_the_title($page)); ?></a></h3>
                <p class="card-excerpt"><?php echo esc_html(get_post_meta($page->ID, '_gws_guides_summary', true)); ?></p>
                <a class="card-link" href="<?php echo esc_url(get_permalink($page)); ?>">Lire <?php echo gws_icon('arrow_forward'); ?></a>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endforeach; ?>
  </div>
  <?php get_template_part('template-parts/site-footer'); ?>
</main>
<?php get_footer(); ?>

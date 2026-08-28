<?php
/**
 * Gabarit d'exemple pour une fiche du CPT boilerplate (bp_item). Reste dans ce dossier
 * (modules/<slug>/templates/single-{post_type}.php) une fois le module métier réel dérivé de ce
 * squelette : inc/module-templates.php le détecte sans copie dès que le module est actif (voir
 * wp-content/plugins/gws-core/modules/_boilerplate-cpt/README.md).
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
        <?php if (has_post_thumbnail()) the_post_thumbnail('large'); ?>
        <?php
        $description = get_post_meta(get_the_ID(), '_bp_short_description', true);
        if ($description) echo '<p class="lead">' . esc_html($description) . '</p>';
        ?>
        <?php the_content(); ?>
        <?php
        $parent_a = (int) get_post_meta(get_the_ID(), '_bp_parent_a', true);
        $parent_b = (int) get_post_meta(get_the_ID(), '_bp_parent_b', true);
        if ($parent_a || $parent_b) :
        ?>
          <ul class="relations">
            <?php if ($parent_a) : ?><li><a href="<?php echo esc_url(get_permalink($parent_a)); ?>"><?php echo esc_html(get_the_title($parent_a)); ?></a></li><?php endif; ?>
            <?php if ($parent_b) : ?><li><a href="<?php echo esc_url(get_permalink($parent_b)); ?>"><?php echo esc_html(get_the_title($parent_b)); ?></a></li><?php endif; ?>
          </ul>
        <?php endif; ?>
      </article>
    <?php endwhile; ?>
  </div>
  <?php get_template_part('template-parts/site-footer'); ?>
</main>
<?php get_footer(); ?>

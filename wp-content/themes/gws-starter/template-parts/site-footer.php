<?php
/**
 * Pied de page visible du site. Structure générique : identité, coordonnées, mentions légales.
 */
?>
<footer class="site-footer">
  <div class="footer-identity">
    <p><?php echo esc_html(gws_get_setting('entity_name') ?: get_bloginfo('name')); ?></p>
  </div>
  <nav class="footer-links" aria-label="Liens de pied de page">
    <?php
    wp_nav_menu(array(
      'theme_location' => 'footer',
      'container' => false,
      'fallback_cb' => false,
      'items_wrap' => '%3$s',
    ));
    ?>
    <span>© <?php echo esc_html(wp_date('Y')); ?> <?php echo esc_html(gws_get_setting('entity_name') ?: get_bloginfo('name')); ?></span>
  </nav>
  <?php if (function_exists('gws_core_show_footer_social') && gws_core_show_footer_social()) : ?>
    <?php get_template_part('template-parts/content/social-links'); ?>
  <?php endif; ?>
  <?php gws_render_credit(); ?>
</footer>

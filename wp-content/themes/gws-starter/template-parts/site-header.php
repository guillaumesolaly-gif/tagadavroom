<?php
/**
 * En-tête visible du site. Structure générique : logo, navigation principale (menu WordPress
 * natif "primary"), bouton de menu mobile.
 */
?>
<header class="site-header">
  <a href="<?php echo esc_url(home_url('/')); ?>" class="site-logo" aria-label="<?php echo esc_attr(get_bloginfo('name')); ?> — Accueil">
    <?php echo esc_html(gws_get_setting('entity_name') ?: get_bloginfo('name')); ?>
  </a>
  <nav id="main-navigation" aria-label="Navigation principale">
    <?php
    wp_nav_menu(array(
      'theme_location' => 'primary',
      'container' => false,
      'fallback_cb' => false,
      'items_wrap' => '%3$s',
    ));
    ?>
  </nav>
  <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="main-navigation" aria-label="Ouvrir le menu">
    <?php echo gws_icon('menu'); ?>
  </button>
</header>

<?php
/**
 * Amorçage du thème : support WordPress, assets (CSS/JS), classes de body.
 */

if (!defined('ABSPATH')) exit;

function gws_theme_setup() {
  add_theme_support('title-tag');
  add_theme_support('post-thumbnails');
  add_theme_support('html5', array('script', 'style', 'navigation-widgets', 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption'));
  add_theme_support('editor-styles');
  add_editor_style('editor-style.css');
  add_theme_support('responsive-embeds');
  add_theme_support('automatic-feed-links');
  register_nav_menus(array(
    'primary' => 'Navigation principale',
    'footer' => 'Navigation de pied de page',
  ));
}
add_action('after_setup_theme', 'gws_theme_setup');

function gws_assets() {
  $css_files = array('tokens', 'base', 'layout', 'components', 'accessibility', 'utilities');
  $last_handle = null;
  foreach ($css_files as $name) {
    $handle = 'gws-' . $name;
    wp_enqueue_style($handle, GWS_THEME_URI . '/assets/css/' . $name . '.css', $last_handle ? array($last_handle) : array(), GWS_THEME_VERSION);
    $last_handle = $handle;
  }
  wp_enqueue_script('gws-theme', GWS_THEME_URI . '/assets/js/theme.js', array(), GWS_THEME_VERSION, true);
  wp_enqueue_script('gws-tracking', GWS_THEME_URI . '/assets/js/tracking.js', array('gws-theme'), GWS_THEME_VERSION, true);
}
add_action('wp_enqueue_scripts', 'gws_assets');

function gws_body_classes($classes) {
  if (is_front_page()) $classes[] = 'is-front-page';
  return $classes;
}
add_filter('body_class', 'gws_body_classes');

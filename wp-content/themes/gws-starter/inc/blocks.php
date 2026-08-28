<?php
/**
 * Bloc Gutenberg générique gws/resource-link — lien de ressource avec icône, titre et
 * description. Rendu côté serveur pour rester simple à maintenir.
 */

if (!defined('ABSPATH')) exit;

function gws_register_blocks() {
  wp_register_script(
    'gws-editor-blocks',
    GWS_THEME_URI . '/assets/js/editor-blocks.js',
    array('wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n'),
    GWS_THEME_VERSION,
    true
  );
  register_block_type('gws/resource-link', array(
    'editor_script' => 'gws-editor-blocks',
    'attributes' => array(
      'title' => array('type' => 'string', 'default' => ''),
      'description' => array('type' => 'string', 'default' => ''),
      'url' => array('type' => 'string', 'default' => ''),
      'icon' => array('type' => 'string', 'default' => 'info'),
    ),
    'render_callback' => 'gws_render_resource_block',
  ));
}
add_action('init', 'gws_register_blocks');

function gws_render_resource_block($attributes) {
  $url = $attributes['url'] ?? '';
  $icon = $attributes['icon'] ?? 'info';
  if (!array_key_exists($icon, gws_icon_glyphs())) $icon = 'info';
  $title = $attributes['title'] ?? '';
  $description = $attributes['description'] ?? '';
  return '<a class="resource-link" href="' . esc_url($url) . '">' . gws_icon($icon) . '<span><strong>' . esc_html($title) . '</strong><small>' . esc_html($description) . '</small></span>' . gws_icon('arrow_forward') . '</a>';
}

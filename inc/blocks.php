<?php
// Bloc Gutenberg spa/resource-link et carte de contact réutilisable.

function spa_register_resource_block() {
  wp_register_script(
    'spa-editor-blocks',
    get_template_directory_uri() . '/editor-blocks.js',
    array('wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components'),
    wp_get_theme()->get('Version'),
    true
  );
  register_block_type('spa/resource-link', array(
    'editor_script' => 'spa-editor-blocks',
    'attributes' => array(
      'title' => array('type' => 'string', 'default' => ''),
      'description' => array('type' => 'string', 'default' => ''),
      'url' => array('type' => 'string', 'default' => ''),
      'icon' => array('type' => 'string', 'default' => 'info'),
    ),
    'render_callback' => 'spa_render_resource_block',
  ));
}
add_action('init', 'spa_register_resource_block');

function spa_render_resource_block($attributes) {
  $url = isset($attributes['url']) ? $attributes['url'] : '';
  $icon = isset($attributes['icon']) ? $attributes['icon'] : 'info';
  // Ce champ est un texte libre côté éditeur (editor-blocks.js) : si le nom saisi ne correspond
  // à aucune icône du sprite (inc/icons.php), on retombe sur l'icône par défaut du bloc plutôt
  // que de générer un <use> vers un symbole inexistant, qui ne rendrait rien.
  if (!array_key_exists($icon, spa_icon_glyphs())) $icon = 'info';
  $title = isset($attributes['title']) ? $attributes['title'] : '';
  $description = isset($attributes['description']) ? $attributes['description'] : '';
  return '<a class="inline-resource" href="' . esc_url($url) . '">' . spa_icon($icon) . '<span><strong>' . esc_html($title) . '</strong><small>' . esc_html($description) . '</small></span><b>→</b></a>';
}

function spa_render_contact_card($email_subject = '') {
  $email_href = 'mailto:' . spa_get_cabinet_setting('public_email');
  if ($email_subject) $email_href .= '?subject=' . rawurlencode($email_subject);
  echo '<div class="contact-card"><p class="desktop-only"><span>Téléphone</span><strong>' . esc_html(spa_get_cabinet_setting('phone_display')) . '</strong></p><a class="mobile-only" href="tel:' . esc_attr(spa_cabinet_phone_href()) . '"><span>Téléphone — appeler</span><strong>' . esc_html(spa_get_cabinet_setting('phone_display')) . '</strong></a><p><span>Adresse</span><strong>' . esc_html(spa_get_cabinet_setting('address_line')) . '<br>' . esc_html(spa_get_cabinet_setting('postal_code') . ' ' . spa_get_cabinet_setting('city')) . '</strong></p><a class="btn btn-dark" href="' . esc_url($email_href) . '">Écrire au cabinet <b>→</b></a></div>';
}


<?php
/**
 * Module « Guides » — rubrique de contenu éditorial structuré (hub + articles), généralisation
 * de la rubrique « Conseils aux dirigeants » d'un cabinet d'avocat. Principe conservé :
 * insert-only (une page n'est créée qu'une fois, jamais réécrite après coup) et catégorisation
 * simple pour un regroupement automatique sur le hub.
 *
 * Ce module ne fournit ni le rendu ni le style (voir le dossier miroir côté thème) : il déclare
 * le champ de catégorie, sème un hub + deux pages d'exemple, et expose une fonction de
 * regroupement pour le gabarit du hub.
 *
 * Préfixe de ce module : gws_guides_.
 */

if (!defined('ABSPATH')) exit;

const GWS_GUIDES_TEMPLATE = 'page-templates/guide.php';
const GWS_GUIDES_HUB_TEMPLATE = 'page-templates/guides-hub.php';
const GWS_GUIDES_HUB_SLUG = 'guides';

require_once __DIR__ . '/content.sample.php';

function gws_guides_field_schema() {
  return array(
    '_gws_guides_category' => array('label' => 'Catégorie', 'type' => 'text', 'description' => 'Regroupement affiché sur la page hub.'),
    '_gws_guides_summary' => array('label' => 'Résumé', 'type' => 'textarea', 'description' => 'Court résumé affiché dans les listes.'),
  );
}

function gws_guides_add_meta_box() {
  global $post;
  if (!$post || get_page_template_slug($post->ID) !== GWS_GUIDES_TEMPLATE) return;
  add_meta_box('gws-guides-fields', 'Réglages du guide', function ($post) {
    gws_core_render_meta_fields($post, gws_guides_field_schema(), 'gws_guides_save_fields');
  }, 'page', 'normal', 'default');
}
add_action('add_meta_boxes', 'gws_guides_add_meta_box');

function gws_guides_save_meta_box($post_id) {
  if (get_page_template_slug($post_id) !== GWS_GUIDES_TEMPLATE) return;
  gws_core_save_meta_fields($post_id, gws_guides_field_schema(), 'gws_guides_save_fields');
}
add_action('save_post_page', 'gws_guides_save_meta_box');

/**
 * Regroupe les pages utilisant le gabarit Guide par catégorie — consommé par le gabarit du hub
 * côté thème. Ajouter un guide = créer une page avec ce gabarit, elle apparaît ici seule.
 */
function gws_guides_by_category() {
  $pages = get_posts(array(
    'post_type' => 'page',
    'post_status' => 'publish',
    'meta_key' => '_wp_page_template',
    'meta_value' => GWS_GUIDES_TEMPLATE,
    'orderby' => 'menu_order title',
    'order' => 'ASC',
    'numberposts' => -1,
  ));
  $grouped = array();
  foreach ($pages as $page) {
    $category = get_post_meta($page->ID, '_gws_guides_category', true) ?: 'Guides';
    $grouped[$category][] = $page;
  }
  return $grouped;
}

/**
 * Création insert-only du hub et des pages d'exemple : ne s'exécute qu'une fois (verrouillé par
 * option), et uniquement pour les pages absentes — ne touche jamais une page déjà existante.
 */
function gws_guides_seed_pages() {
  if (get_option('gws_guides_seeded')) return;
  $config = gws_guides_sample_content();

  if (!get_page_by_path(GWS_GUIDES_HUB_SLUG)) {
    wp_insert_post(array(
      'post_title' => 'Guides',
      'post_name' => GWS_GUIDES_HUB_SLUG,
      'post_type' => 'page',
      'post_status' => 'publish',
      'meta_input' => array('_wp_page_template' => GWS_GUIDES_HUB_TEMPLATE),
    ));
  }

  foreach ($config as $slug => $data) {
    if (get_page_by_path($slug)) continue;
    wp_insert_post(array(
      'post_title' => $data['title'],
      'post_name' => $slug,
      'post_type' => 'page',
      'post_status' => 'publish',
      'post_content' => $data['content'],
      'meta_input' => array(
        '_wp_page_template' => GWS_GUIDES_TEMPLATE,
        '_gws_guides_category' => $data['category'],
        '_gws_guides_summary' => $data['summary'],
      ),
    ));
  }

  if (get_page_by_path(GWS_GUIDES_HUB_SLUG)) update_option('gws_guides_seeded', true, false);
}
add_action('init', 'gws_guides_seed_pages', 20);

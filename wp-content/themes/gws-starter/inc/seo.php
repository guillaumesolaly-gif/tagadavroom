<?php
/**
 * Title, meta description, Open Graph et Twitter Card — fallback qui s'efface automatiquement
 * si un plugin SEO (Yoast, Rank Math, SEOPress, AIOSEO) est actif. Les valeurs éditables
 * viennent des champs SEO du plugin gws-core (_gws_seo_title/_gws_seo_description) ; ce fichier
 * ne fait qu'en décider l'affichage et le format de sortie — aucune donnée n'est stockée ici.
 */

if (!defined('ABSPATH')) exit;

function gws_has_seo_plugin() {
  return defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION') || defined('SEOPRESS_VERSION') || defined('AIOSEO_VERSION');
}

function gws_document_title($title) {
  if (gws_has_seo_plugin() || !is_page()) return $title;
  $custom = get_post_meta(get_queried_object_id(), '_gws_seo_title', true);
  return $custom ?: $title;
}
add_filter('pre_get_document_title', 'gws_document_title');

function gws_page_seo_meta() {
  if (gws_has_seo_plugin() || !is_page()) return;
  $description = get_post_meta(get_queried_object_id(), '_gws_seo_description', true);
  if ($description) echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
}
add_action('wp_head', 'gws_page_seo_meta', 2);

function gws_social_meta() {
  if (gws_has_seo_plugin() || !is_page()) return;
  $page_id = get_queried_object_id();
  $entity_name = gws_get_setting('entity_name');
  $title = get_post_meta($page_id, '_gws_seo_title', true) ?: (get_the_title($page_id) . ($entity_name ? ' | ' . $entity_name : ''));
  $description = get_post_meta($page_id, '_gws_seo_description', true);
  echo '<meta property="og:locale" content="' . esc_attr(str_replace('-', '_', get_locale())) . '">' . "\n";
  echo '<meta property="og:type" content="website">' . "\n";
  if ($entity_name) echo '<meta property="og:site_name" content="' . esc_attr($entity_name) . '">' . "\n";
  echo '<meta property="og:title" content="' . esc_attr($title) . '">' . "\n";
  if ($description) echo '<meta property="og:description" content="' . esc_attr($description) . '">' . "\n";
  echo '<meta property="og:url" content="' . esc_url(get_permalink($page_id)) . '">' . "\n";
  echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
  echo '<meta name="twitter:title" content="' . esc_attr($title) . '">' . "\n";
  if ($description) echo '<meta name="twitter:description" content="' . esc_attr($description) . '">' . "\n";
}
add_action('wp_head', 'gws_social_meta', 4);

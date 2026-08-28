<?php
/**
 * Données structurées Schema.org génériques (WebSite/WebPage/Organization/Breadcrumb) —
 * désactivées automatiquement si un plugin SEO est actif (voir inc/seo-yoast-bridge.php pour
 * l'intégration propre avec Yoast dans ce cas). Le type d'entité est filtrable pour s'adapter
 * au secteur du projet (Organization, LocalBusiness, ProfessionalService...).
 */

if (!defined('ABSPATH')) exit;

function gws_schema_entity_type() {
  return apply_filters('gws_schema_entity_type', 'Organization');
}

function gws_site_structured_data() {
  if (gws_has_seo_plugin() || !is_page()) return;
  $page_id = get_queried_object_id();
  $site_url = home_url('/');
  $entity_name = gws_get_setting('entity_name') ?: get_bloginfo('name');
  $business_id = $site_url . '#organization';
  $website_id = $site_url . '#website';
  $webpage_id = get_permalink($page_id) . '#webpage';

  $graph = array(
    array('@type' => 'WebSite', '@id' => $website_id, 'url' => $site_url, 'name' => $entity_name, 'publisher' => array('@id' => $business_id)),
    array('@type' => gws_schema_entity_type(), '@id' => $business_id, 'name' => $entity_name, 'url' => $site_url, 'telephone' => gws_phone_href(), 'email' => gws_get_setting('public_email')),
    array('@type' => 'WebPage', '@id' => $webpage_id, 'url' => get_permalink($page_id), 'name' => get_the_title($page_id), 'isPartOf' => array('@id' => $website_id)),
  );

  if (!is_front_page()) {
    $breadcrumb_id = get_permalink($page_id) . '#breadcrumb';
    $graph[] = array(
      '@type' => 'BreadcrumbList',
      '@id' => $breadcrumb_id,
      'itemListElement' => array(
        array('@type' => 'ListItem', 'position' => 1, 'name' => 'Accueil', 'item' => $site_url),
        array('@type' => 'ListItem', 'position' => 2, 'name' => get_the_title($page_id), 'item' => get_permalink($page_id)),
      ),
    );
    $graph[2]['breadcrumb'] = array('@id' => $breadcrumb_id);
  }

  echo '<script type="application/ld+json">' . wp_json_encode(array('@context' => 'https://schema.org', '@graph' => $graph), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
}
add_action('wp_head', 'gws_site_structured_data', 6);

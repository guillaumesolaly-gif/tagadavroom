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

/**
 * Nœud Organization/LocalBusiness... du fallback maison. Ne fixe une clé que si la donnée
 * correspondante est réellement renseignée — jamais de balise Schema vide (telephone: "",
 * sameAs: [], etc.).
 */
function gws_schema_organization_node($business_id, $site_url, $entity_name) {
  $node = array('@type' => gws_schema_entity_type(), '@id' => $business_id, 'name' => $entity_name, 'url' => $site_url);

  $phone = gws_phone_href();
  if ($phone) $node['telephone'] = $phone;

  $email = gws_get_setting('public_email');
  if ($email) $node['email'] = $email;

  $logo = function_exists('gws_core_get_logo_url') ? gws_core_get_logo_url() : '';
  if ($logo) $node['logo'] = $logo;

  $same_as = function_exists('gws_core_schema_same_as') ? gws_core_schema_same_as() : array();
  if ($same_as) $node['sameAs'] = $same_as;

  return $node;
}

/**
 * Émet WebSite + Organization sur toute Page, ET sur la page d'accueil quelle que soit sa
 * configuration WordPress (page statique ou index natif des derniers articles) — dans ce
 * second cas, il n'existe pas de Page réelle à décrire : WebSite + Organization suffisent, sans
 * fabriquer un WebPage ni un breadcrumb qui ne correspondrait à aucun contenu réel. Sur une
 * Page, WebPage est ajouté (comme avant), et le breadcrumb uniquement si ce n'est pas l'accueil.
 */
function gws_site_structured_data() {
  if (gws_has_seo_plugin()) return;
  $is_front = is_front_page();
  if (!is_page() && !$is_front) return;

  $site_url = home_url('/');
  $entity_name = gws_get_setting('entity_name') ?: get_bloginfo('name');
  $business_id = $site_url . '#organization';
  $website_id = $site_url . '#website';

  $graph = array(
    array('@type' => 'WebSite', '@id' => $website_id, 'url' => $site_url, 'name' => $entity_name, 'publisher' => array('@id' => $business_id)),
    gws_schema_organization_node($business_id, $site_url, $entity_name),
  );

  if (is_page()) {
    $page_id = get_queried_object_id();
    $webpage_id = get_permalink($page_id) . '#webpage';
    $graph[] = array('@type' => 'WebPage', '@id' => $webpage_id, 'url' => get_permalink($page_id), 'name' => get_the_title($page_id), 'isPartOf' => array('@id' => $website_id));

    if (!$is_front) {
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
  }

  echo '<script type="application/ld+json">' . wp_json_encode(array('@context' => 'https://schema.org', '@graph' => $graph), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
}
add_action('wp_head', 'gws_site_structured_data', 6);

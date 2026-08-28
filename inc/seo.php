<?php
// Title, meta description, Open Graph, Twitter Card et données structurées Schema.org — désactivés automatiquement si un plugin SEO (Yoast, Rank Math, SEOPress, AIOSEO) est actif.

function spa_document_title($title) {
  if (spa_has_seo_plugin()) return $title;
  if (is_page()) {
    $custom = get_post_meta(get_queried_object_id(), '_spa_seo_title', true);
    if ($custom) return $custom;
  }
  return $title;
}
add_filter('pre_get_document_title', 'spa_document_title');

function spa_has_seo_plugin() {
  return defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION') || defined('SEOPRESS_VERSION') || defined('AIOSEO_VERSION');
}

function spa_page_seo_meta() {
  if (!is_page() || spa_has_seo_plugin()) return;
  $page_id = get_queried_object_id();
  $description = get_post_meta($page_id, '_spa_seo_description', true);
  if (!$description && is_front_page()) $description = 'Avocat à Saint-Étienne en droit des entreprises en difficulté, prévention, procédures collectives, contentieux commercial et postulation.';
  if ($description) echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
}
add_action('wp_head', 'spa_page_seo_meta', 2);

function spa_social_meta() {
  if (!is_page() || spa_has_seo_plugin()) return;
  $page_id = get_queried_object_id();
  $title = get_post_meta($page_id, '_spa_seo_title', true);
  if (!$title) $title = is_front_page() ? 'Saint-Père Avocat | Droit des entreprises à Saint-Étienne' : get_the_title($page_id) . ' | Saint-Père Avocat';
  $description = get_post_meta($page_id, '_spa_seo_description', true);
  if (!$description) $description = 'Cabinet d’avocat à Saint-Étienne dédié aux entreprises, dirigeants et investisseurs.';
  $url = get_permalink($page_id);
  $image = get_template_directory_uri() . '/assets/saint-pere-avocat-social.jpg';
  echo '<meta property="og:locale" content="fr_FR">' . "\n";
  echo '<meta property="og:type" content="website">' . "\n";
  echo '<meta property="og:site_name" content="Saint-Père Avocat">' . "\n";
  echo '<meta property="og:title" content="' . esc_attr($title) . '">' . "\n";
  echo '<meta property="og:description" content="' . esc_attr($description) . '">' . "\n";
  echo '<meta property="og:url" content="' . esc_url($url) . '">' . "\n";
  echo '<meta property="og:image" content="' . esc_url($image) . '">' . "\n";
  echo '<meta property="og:image:width" content="1200"><meta property="og:image:height" content="630"><meta property="og:image:type" content="image/jpeg">' . "\n";
  echo '<meta property="og:image:alt" content="Maître Juliette Saint-Père dans son cabinet à Saint-Étienne">' . "\n";
  echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
  echo '<meta name="twitter:title" content="' . esc_attr($title) . '">' . "\n";
  echo '<meta name="twitter:description" content="' . esc_attr($description) . '">' . "\n";
  echo '<meta name="twitter:image" content="' . esc_url($image) . '">' . "\n";
  echo '<meta name="twitter:image:alt" content="Maître Juliette Saint-Père dans son cabinet à Saint-Étienne">' . "\n";
}
add_action('wp_head', 'spa_social_meta', 4);

function spa_faq_structured_data() {
  if (spa_has_seo_plugin() || !is_page('faq-avocat-droit-entreprises-saint-etienne')) return;
  $post = get_post(get_queried_object_id());
  if (!$post) return;
  $rendered = do_blocks($post->post_content);
  if (!preg_match_all('~<details[^>]*>\s*<summary>(.*?)</summary>(.*?)</details>~is', $rendered, $matches, PREG_SET_ORDER)) return;
  $entities = array();
  foreach ($matches as $match) {
    $question = trim(wp_strip_all_tags($match[1]));
    $answer = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags($match[2])));
    if (!$question || !$answer) continue;
    $entities[] = array('@type' => 'Question', 'name' => $question, 'acceptedAnswer' => array('@type' => 'Answer', 'text' => $answer));
  }
  if ($entities) echo '<script type="application/ld+json">' . wp_json_encode(array('@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $entities), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
}
add_action('wp_head', 'spa_faq_structured_data', 6);

function spa_site_structured_data() {
  if (!is_page() || spa_has_seo_plugin()) return;
  $page_id = get_queried_object_id();
  $page_url = get_permalink($page_id);
  $site_url = home_url('/');
  $person_id = $site_url . '#juliette-saint-pere';
  $business_id = $site_url . '#cabinet';
  $website_id = $site_url . '#website';
  $webpage_id = $page_url . '#webpage';
  $graph = array(
    array('@type' => 'WebSite', '@id' => $website_id, 'url' => $site_url, 'name' => 'Saint-Père Avocat', 'inLanguage' => 'fr-FR', 'publisher' => array('@id' => $business_id)),
    array('@type' => 'LegalService', '@id' => $business_id, 'name' => 'Saint-Père Avocat', 'url' => $site_url, 'logo' => get_template_directory_uri() . '/assets/logo-saint-pere.png', 'image' => get_template_directory_uri() . '/assets/juliette-saint-pere-cabinet-v1.webp', 'telephone' => spa_cabinet_phone_href(), 'email' => spa_get_cabinet_setting('public_email'), 'address' => array('@type' => 'PostalAddress', 'streetAddress' => spa_get_cabinet_setting('address_line'), 'postalCode' => spa_get_cabinet_setting('postal_code'), 'addressLocality' => spa_get_cabinet_setting('city'), 'addressCountry' => 'FR'), 'areaServed' => array(array('@type' => 'City', 'name' => spa_get_cabinet_setting('city')), array('@type' => 'AdministrativeArea', 'name' => 'Loire')), 'founder' => array('@id' => $person_id), 'sameAs' => array(spa_get_cabinet_setting('linkedin_url'), spa_get_cabinet_setting('avocat_url'))),
    array('@type' => 'Person', '@id' => $person_id, 'name' => 'Juliette Saint-Père', 'jobTitle' => 'Avocate au Barreau de Saint-Étienne', 'image' => get_template_directory_uri() . '/assets/portrait-saint-pere-tenue-pro-v1.webp', 'worksFor' => array('@id' => $business_id), 'sameAs' => array(spa_get_cabinet_setting('linkedin_url'), spa_get_cabinet_setting('avocat_url'))),
    array('@type' => 'WebPage', '@id' => $webpage_id, 'url' => $page_url, 'name' => get_the_title($page_id), 'isPartOf' => array('@id' => $website_id), 'about' => array('@id' => $business_id), 'inLanguage' => 'fr-FR'),
  );
  if (!is_front_page()) {
    $breadcrumb_id = $page_url . '#breadcrumb';
    $graph[] = array('@type' => 'BreadcrumbList', '@id' => $breadcrumb_id, 'itemListElement' => array(array('@type' => 'ListItem', 'position' => 1, 'name' => 'Accueil', 'item' => $site_url), array('@type' => 'ListItem', 'position' => 2, 'name' => get_the_title($page_id), 'item' => $page_url)));
    $graph[3]['breadcrumb'] = array('@id' => $breadcrumb_id);
  }
  echo '<script type="application/ld+json">' . wp_json_encode(array('@context' => 'https://schema.org', '@graph' => $graph), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
}
add_action('wp_head', 'spa_site_structured_data', 6);

<?php
// Amorçage du thème : support WordPress, assets (CSS/JS), classes de body, resource hints, préchargement des polices.

function spa_theme_setup() {
  add_theme_support('title-tag');
  add_theme_support('post-thumbnails');
  add_theme_support('html5', array('script', 'style', 'navigation-widgets'));
  add_theme_support('editor-styles');
  add_editor_style('editor-style.css');
}
add_action('after_setup_theme', 'spa_theme_setup');

function spa_assets() {
  wp_enqueue_style('spa-fonts', get_template_directory_uri() . '/fonts.css', array(), wp_get_theme()->get('Version'));
  wp_enqueue_style('spa-style', get_stylesheet_uri(), array('spa-fonts'), wp_get_theme()->get('Version'));
  wp_add_inline_style('spa-style', '.contact-float:not(.is-open) .contact-float-menu{display:none}.contact-float.is-open .contact-float-menu{display:block}');
  wp_add_inline_style('spa-style', '.legal-article .faq-section-title{font-size:46px!important;margin:64px 0 34px!important;padding-bottom:18px;border-bottom:1px solid var(--black);position:relative}.legal-article .faq-section-title:after{content:"";position:absolute;left:0;bottom:-1px;width:82px;height:4px;background:var(--salmon)}@media(max-width:560px){.legal-article .faq-section-title{font-size:38px!important;margin-top:52px!important}}');
  if (is_page('des-solutions-en-cas-de-difficultes-financieres')) wp_enqueue_style('spa-video-page', get_template_directory_uri() . '/page-video.css', array('spa-style'), wp_get_theme()->get('Version'));
  if (is_page('des-solutions-en-cas-de-difficultes-financieres')) wp_enqueue_style('spa-youtube-video', get_template_directory_uri() . '/page-video-youtube.css', array('spa-video-page'), wp_get_theme()->get('Version'));
  if (is_page('faq-avocat-droit-entreprises-saint-etienne')) wp_enqueue_style('spa-faq-page', get_template_directory_uri() . '/page-faq.css', array('spa-style'), wp_get_theme()->get('Version'));
  if (is_page('trouver-avocat-droit-entreprises-saint-etienne')) wp_enqueue_style('spa-profile-page', get_template_directory_uri() . '/page-profile.css', array('spa-style'), wp_get_theme()->get('Version'));
  if (is_page(array('mentions-legales', 'politique-de-confidentialite', 'cgu', 'gestion-de-cookies'))) wp_enqueue_style('spa-legal-page', get_template_directory_uri() . '/page-legal.css', array('spa-style'), wp_get_theme()->get('Version'));
  if (is_page('avocat-postulation-saint-etienne')) wp_add_inline_style('spa-style', '.cabinet-photo.cabinet-photo-postulation{object-position:54% top}');
  if (is_page('diagnostic-entreprise-en-difficulte')) {
    wp_enqueue_style('spa-diagnostic', get_template_directory_uri() . '/page-diagnostic.css', array('spa-style'), wp_get_theme()->get('Version'));
    wp_enqueue_script('spa-diagnostic', get_template_directory_uri() . '/diagnostic.js', array(), wp_get_theme()->get('Version'), true);
    wp_localize_script('spa-diagnostic', 'spaDiagnostic', array('questions' => spa_diagnostic_questions()));
  }
  if (is_page(array_merge(array(SPA_CONSEILS_HUB_SLUG), spa_conseils_slugs()))) {
    wp_enqueue_style('spa-conseils', get_template_directory_uri() . '/page-conseils.css', array('spa-style'), wp_get_theme()->get('Version'));
    wp_enqueue_script('spa-conseils', get_template_directory_uri() . '/conseils.js', array(), wp_get_theme()->get('Version'), true);
  }
  wp_enqueue_style('spa-accessibility', get_template_directory_uri() . '/accessibility.css', array('spa-style'), wp_get_theme()->get('Version'));
  wp_enqueue_script('spa-interactions', get_template_directory_uri() . '/theme.js', array(), wp_get_theme()->get('Version'), true);
  $spa_page_type = 'expertise';
  if (is_front_page()) $spa_page_type = 'home';
  elseif (is_page('avocat-postulation-saint-etienne')) $spa_page_type = 'postulation';
  elseif (is_page('diagnostic-entreprise-en-difficulte')) $spa_page_type = 'diagnostic';
  // Emplacement fourni au JS pour le suivi Matomo (Event Name) sans dupliquer de logique dans
  // chaque gabarit — voir spaTrack() dans theme.js.
  wp_localize_script('spa-interactions', 'spaTracking', array('pageType' => $spa_page_type));
}
add_action('wp_enqueue_scripts', 'spa_assets');

function spa_body_classes($classes) {
  if (is_page('trouver-avocat-droit-entreprises-saint-etienne')) $classes[] = 'profile-page';
  if (is_page(array('mentions-legales', 'politique-de-confidentialite', 'cgu', 'gestion-de-cookies'))) $classes[] = 'legal-page';
  return $classes;
}
add_filter('body_class', 'spa_body_classes');

function spa_resource_hints($urls, $relation_type) {
  return $urls;
}
add_filter('wp_resource_hints', 'spa_resource_hints', 10, 2);

function spa_preload_local_fonts() {
  // URL relative au protocole (set_url_scheme(..., 'relative')) plutôt qu'absolue : défense en
  // profondeur contre un is_ssl() ponctuellement erroné côté serveur (proxy/CDN OVH ne
  // transmettant pas toujours X-Forwarded-Proto), qui forcerait autrement ces deux liens en
  // http:// sur une page HTTPS (Mixed Content bloqué par Chrome). Le correctif de fond est
  // côté serveur (wp-config.php) ; ceci protège ces deux ressources même si ce correctif venait
  // à manquer un jour.
  $cormorant = set_url_scheme(get_template_directory_uri() . '/assets/fonts/cormorant-garamond-latin.woff2', 'relative');
  $montserrat = set_url_scheme(get_template_directory_uri() . '/assets/fonts/montserrat-latin.woff2', 'relative');
  echo '<link rel="preload" href="' . esc_url($cormorant) . '" as="font" type="font/woff2" crossorigin>' . "\n";
  echo '<link rel="preload" href="' . esc_url($montserrat) . '" as="font" type="font/woff2" crossorigin>' . "\n";
}
add_action('wp_head', 'spa_preload_local_fonts', 1);


<?php
/**
 * Point d'entrée du thème. Ne contient aucune logique métier : voir le plugin compagnon
 * gws-core pour les réglages, champs SEO, migrations et modules métier.
 */

if (!defined('ABSPATH')) exit;

define('GWS_THEME_VERSION', wp_get_theme()->get('Version'));
define('GWS_THEME_DIR', get_template_directory());
define('GWS_THEME_URI', get_template_directory_uri());

require_once GWS_THEME_DIR . '/inc/compat.php';
require_once GWS_THEME_DIR . '/inc/setup.php';
require_once GWS_THEME_DIR . '/inc/module-templates.php';
require_once GWS_THEME_DIR . '/inc/icons.php';
require_once GWS_THEME_DIR . '/inc/blocks.php';
require_once GWS_THEME_DIR . '/inc/template-tags.php';
require_once GWS_THEME_DIR . '/inc/seo.php';
require_once GWS_THEME_DIR . '/inc/schema.php';
if (defined('WPSEO_VERSION')) require_once GWS_THEME_DIR . '/inc/seo-yoast-bridge.php';

<?php
/**
 * Plugin Name: GWS Core
 * Description: Données et logique métier persistantes (réglages, champs structurés, migrations, modules métier) pour les sites bâtis sur le starter GWS. Ce plugin doit rester actif quel que soit le thème utilisé.
 * Version: 1.0.0
 * Requires PHP: 7.4
 * Text Domain: gws-core
 */

if (!defined('ABSPATH')) exit;

define('GWS_CORE_VERSION', '1.0.0');
define('GWS_CORE_DIR', plugin_dir_path(__FILE__));
define('GWS_CORE_URL', plugin_dir_url(__FILE__));

require_once GWS_CORE_DIR . 'includes/fields.php';
require_once GWS_CORE_DIR . 'includes/settings.php';
require_once GWS_CORE_DIR . 'includes/security.php';
require_once GWS_CORE_DIR . 'includes/contact-form.php';
require_once GWS_CORE_DIR . 'includes/seo-meta.php';
require_once GWS_CORE_DIR . 'includes/migration.php';
require_once GWS_CORE_DIR . 'includes/modules.php';

if (is_admin()) {
  require_once GWS_CORE_DIR . 'includes/admin/settings-page.php';
  require_once GWS_CORE_DIR . 'includes/admin/migration-tool-page.php';
}

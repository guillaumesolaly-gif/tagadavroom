<?php
/**
 * Plugin Name: GWS Core
 * Description: Données et logique métier persistantes (réglages, champs structurés, migrations, modules métier) pour les sites bâtis sur le starter GWS. Ce plugin doit rester actif quel que soit le thème utilisé.
 * Author: Tagada Vroom
 * Author URI: https://tagadavroom.fr/
 * Version: 1.6.0
 * Requires PHP: 7.4
 * Text Domain: gws-core
 */

if (!defined('ABSPATH')) exit;

define('GWS_CORE_VERSION', '1.6.0');
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
  // Ce fichier s'auto-neutralise hors environnement local/développement (voir sa propre garde) :
  // aucun coût, aucun menu, aucune action enregistrée en production.
  require_once GWS_CORE_DIR . 'includes/admin/qa-tool-page.php';
}

// Complète le mécanisme de flush géré par includes/modules.php : couvre le cas où le plugin
// lui-même est (dé)activé depuis l'écran Extensions (indépendamment du fichier config/modules.php).
register_activation_hook(__FILE__, 'gws_core_flag_rewrite_flush');
register_deactivation_hook(__FILE__, 'flush_rewrite_rules');

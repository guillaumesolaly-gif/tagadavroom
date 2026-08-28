<?php
/**
 * Chargeur de modules métier. Un module = un dossier sous modules/ contenant un module.php
 * autonome (CPT, taxonomies, champs, logique métier). Rien ne se charge tant que le slug du
 * dossier n'est pas listé dans config/modules.php : c'est l'unique interrupteur, versionnable
 * avec le code du projet plutôt que stocké en base.
 */

if (!defined('ABSPATH')) exit;

function gws_core_active_modules() {
  $config_file = GWS_CORE_DIR . 'config/modules.php';
  $modules = file_exists($config_file) ? include $config_file : array();
  return is_array($modules) ? $modules : array();
}

function gws_core_load_modules() {
  foreach (gws_core_active_modules() as $slug) {
    $slug = sanitize_key($slug);
    $module_file = GWS_CORE_DIR . 'modules/' . $slug . '/module.php';
    if (file_exists($module_file)) require_once $module_file;
  }
}
add_action('plugins_loaded', 'gws_core_load_modules');

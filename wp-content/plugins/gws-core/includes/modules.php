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
  $modules = is_array($modules) ? $modules : array();
  // Bascule de développement du module QA (voir includes/admin/qa-tool-page.php) : une
  // commodité locale distincte de config/modules.php, qui reste l'unique source de vérité
  // versionnée pour les modules métier réels. N'ajoute jamais rien d'autre que 'qa', et n'a
  // aucun effet hors d'un environnement local/de développement.
  if (gws_core_qa_dev_toggle_enabled()) $modules[] = 'qa';
  return array_values(array_unique(array_map('sanitize_key', $modules)));
}

function gws_core_qa_dev_toggle_enabled() {
  if (!in_array(wp_get_environment_type(), array('local', 'development'), true)) return false;
  return (bool) get_option('gws_core_qa_dev_enabled', false);
}

/**
 * Un module s'active ou se désactive en éditant config/modules.php, pas via un écran
 * Extensions : aucun événement d'activation/désactivation classique ne se déclenche donc
 * automatiquement. Cette fonction détecte le changement elle-même (comparaison avec la liste
 * mémorisée au chargement précédent) et programme un flush des règles de réécriture si besoin —
 * utile dès qu'un module déclare un CPT ou une règle de réécriture propre. Elle tourne sur
 * 'plugins_loaded', donc avant que les modules n'aient eu la chance de (dé)enregistrer quoi que
 * ce soit sur 'init' : au moment où le flush s'exécute réellement (voir plus bas), seuls les
 * modules encore actifs ont pu enregistrer leurs règles, ce qui donne un résultat toujours
 * exact, sans geste manuel dans Réglages > Permaliens.
 */
function gws_core_detect_module_change() {
  $active = gws_core_active_modules();
  sort($active);
  $stored = get_option('gws_core_active_modules', array());
  sort($stored);
  if ($active !== $stored) {
    update_option('gws_core_active_modules', $active, false);
    gws_core_flag_rewrite_flush();
  }
}

function gws_core_flag_rewrite_flush() {
  update_option('gws_core_rewrite_flush_needed', 1, false);
}

/**
 * Exécute le flush une seule fois, en priorité tardive sur 'init' : après que les modules
 * encore actifs ont eu l'occasion d'enregistrer leurs post types/taxonomies (par défaut sur
 * 'init' à la priorité 10).
 */
function gws_core_maybe_flush_rewrite_rules() {
  if (!get_option('gws_core_rewrite_flush_needed')) return;
  flush_rewrite_rules();
  delete_option('gws_core_rewrite_flush_needed');
}
add_action('init', 'gws_core_maybe_flush_rewrite_rules', 999);

function gws_core_load_modules() {
  gws_core_detect_module_change();
  foreach (gws_core_active_modules() as $slug) {
    $module_file = GWS_CORE_DIR . 'modules/' . $slug . '/module.php';
    if (file_exists($module_file)) require_once $module_file;
  }
}
add_action('plugins_loaded', 'gws_core_load_modules');

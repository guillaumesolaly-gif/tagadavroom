<?php
/**
 * Permet à un module de fournir ses propres gabarits (page template, single, archive) sans
 * qu'aucun fichier n'ait besoin d'être copié ou déplacé à la racine du thème : les fichiers
 * restent physiquement dans modules/<slug>/, WordPress est simplement informé de leur existence
 * via les filtres natifs prévus à cet effet. La disponibilité de ces gabarits suit exactement
 * la liste des modules actifs déclarée côté plugin (gws_core_active_modules()) : un module
 * retiré de sa configuration voit ses gabarits disparaître du sélecteur de l'éditeur et de la
 * hiérarchie de gabarits dès la requête suivante, sans rien à nettoyer côté thème.
 *
 * Ce fichier est toujours chargé (aucun coût quand aucun module n'en a besoin : les glob()
 * ci-dessous ne trouvent simplement rien, ou sont filtrés par module inactif), ce qui évite
 * d'avoir à toucher au cœur du thème à chaque nouveau module.
 */

if (!defined('ABSPATH')) exit;

function gws_module_templates_dir() {
  return GWS_THEME_DIR . '/modules';
}

/**
 * Modules effectivement actifs, au sens du plugin gws-core. Si le plugin est absent, aucun
 * module métier ne peut être considéré actif — cohérent avec le reste des enveloppes du thème
 * (voir inc/compat.php).
 */
function gws_active_module_slugs() {
  return function_exists('gws_core_active_modules') ? gws_core_active_modules() : array();
}

/**
 * Extrait le slug de module à partir d'un chemin de fichier sous modules/<slug>/...
 */
function gws_module_slug_from_path($file) {
  $relative = str_replace(gws_module_templates_dir() . '/', '', $file);
  $parts = explode('/', $relative);
  return $parts[0] ?? '';
}

/**
 * Gabarits de page fournis par les modules actifs : tout fichier .php sous
 * modules/<slug>/page-templates/ (slug listé dans config/modules.php) portant un en-tête
 * "Template Name:" est traité exactement comme un fichier de page-templates/ à la racine du
 * thème, mais sans y être copié.
 */
function gws_module_page_templates() {
  $active = gws_active_module_slugs();
  if (!$active) return array();
  $templates = array();
  $base = gws_module_templates_dir();
  foreach (glob($base . '/*/page-templates/*.php') ?: array() as $file) {
    if (!in_array(gws_module_slug_from_path($file), $active, true)) continue;
    $contents = file_get_contents($file, false, null, 0, 8192);
    if ($contents && preg_match('/Template Name:\s*(.+)$/mi', $contents, $match)) {
      $relative = 'modules' . str_replace($base, '', $file);
      $templates[$relative] = trim($match[1]);
    }
  }
  return $templates;
}

function gws_register_module_page_templates($post_templates) {
  return $post_templates + gws_module_page_templates();
}
add_filter('theme_page_templates', 'gws_register_module_page_templates');

function gws_load_module_page_template($template) {
  $slug = get_page_template_slug();
  if (!$slug) return $template;
  $module_templates = gws_module_page_templates();
  if (!isset($module_templates[$slug])) return $template;
  $file = GWS_THEME_DIR . '/' . $slug;
  return file_exists($file) ? $file : $template;
}
add_filter('page_template', 'gws_load_module_page_template');

/**
 * Gabarits single/archive fournis par les modules actifs : modules/<slug>/templates/
 * single-{post_type}.php et archive-{post_type}.php, utilisés uniquement si aucun fichier de
 * même nom n'existe déjà à la racine du thème (jamais de conflit avec un vrai gabarit de
 * projet) et si le module correspondant est bien listé dans config/modules.php.
 */
function gws_find_module_template($filenames) {
  $active = gws_active_module_slugs();
  if (!$active) return '';
  $base = gws_module_templates_dir();
  foreach ((array) $filenames as $filename) {
    foreach (glob($base . '/*/templates/' . basename($filename)) ?: array() as $match) {
      if (in_array(gws_module_slug_from_path($match), $active, true)) return $match;
    }
  }
  return '';
}

function gws_load_module_single_template($template) {
  if ($template) return $template;
  $found = gws_find_module_template(array('single-' . get_post_type() . '.php', 'single.php'));
  return $found ?: $template;
}
add_filter('single_template', 'gws_load_module_single_template');

function gws_load_module_archive_template($template) {
  if ($template) return $template;
  $post_type = get_query_var('post_type');
  if (is_array($post_type)) $post_type = reset($post_type);
  $found = gws_find_module_template(array('archive-' . $post_type . '.php', 'archive.php'));
  return $found ?: $template;
}
add_filter('archive_template', 'gws_load_module_archive_template');

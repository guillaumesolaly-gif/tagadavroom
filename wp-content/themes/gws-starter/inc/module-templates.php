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
 * single-{post_type}.php et archive-{post_type}.php.
 *
 * Ordre de priorité voulu : 1) un gabarit spécifique déjà présent à la racine du thème
 * (single-{post_type}.php / archive-{post_type}.php) ; 2) le gabarit fourni par le module actif
 * s'il existe ; 3) le fallback générique du thème (single.php / archive.php).
 *
 * WordPress résout déjà ce genre de priorité via {$type}_template_hierarchy (liste ordonnée de
 * noms de fichiers, du plus spécifique au plus générique) puis locate_template(), qui retourne
 * le premier fichier RÉELLEMENT PRÉSENT dans cette liste. On ne peut donc pas se contenter de
 * regarder si le $template déjà résolu par WordPress est vide : le thème fournissant toujours
 * single.php/archive.php en filet de sécurité, ce $template n'est jamais vide, et un module ne
 * serait alors jamais consulté. La bonne approche consiste à insérer le gabarit du module DANS
 * la hiérarchie elle-même, juste après l'entrée spécifique et avant le fallback générique — WP
 * choisit alors naturellement le premier fichier qui existe réellement, dans le bon ordre.
 */
function gws_module_relative_template_path($filename) {
  $active = gws_active_module_slugs();
  if (!$active) return '';
  $base = gws_module_templates_dir();
  foreach (glob($base . '/*/templates/' . basename($filename)) ?: array() as $match) {
    if (in_array(gws_module_slug_from_path($match), $active, true)) {
      return 'modules' . str_replace($base, '', $match);
    }
  }
  return '';
}

function gws_insert_module_template_in_hierarchy($templates, $specific_filename) {
  $module_relative = gws_module_relative_template_path($specific_filename);
  if (!$module_relative) return $templates;
  $position = array_search($specific_filename, $templates, true);
  // Repli défensif si l'entrée spécifique n'apparaît pas dans la hiérarchie fournie par
  // WordPress (cas non observé pour un CPT standard non hiérarchique) : insérer juste avant le
  // dernier élément plutôt qu'après, pour ne jamais faire perdre au module sa priorité sur le
  // fallback générique.
  $position = ($position === false) ? max(0, count($templates) - 1) : $position + 1;
  array_splice($templates, $position, 0, array($module_relative));
  return $templates;
}

function gws_extend_single_template_hierarchy($templates) {
  $post_type = get_post_type();
  if (!$post_type) return $templates;
  return gws_insert_module_template_in_hierarchy($templates, 'single-' . $post_type . '.php');
}
add_filter('single_template_hierarchy', 'gws_extend_single_template_hierarchy');

function gws_extend_archive_template_hierarchy($templates) {
  $post_type = get_query_var('post_type');
  if (is_array($post_type)) $post_type = reset($post_type);
  if (!$post_type) return $templates;
  return gws_insert_module_template_in_hierarchy($templates, 'archive-' . $post_type . '.php');
}
add_filter('archive_template_hierarchy', 'gws_extend_archive_template_hierarchy');

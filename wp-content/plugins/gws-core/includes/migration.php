<?php
/**
 * Cadre générique de migration de contenu — explicite, versionné, avec sauvegarde et rollback.
 *
 * Reprend le principe validé sur le projet de référence ayant inspiré ce starter : aucune
 * migration ne s'exécute automatiquement au chargement (`init`) ; une migration n'existe qu'une
 * fois déclarée par un module via gws_core_register_migration(), et ne s'exécute que sur clic
 * explicite d'un administrateur depuis Outils > Migrations. Tant qu'aucun module n'en déclare,
 * ce fichier est inerte : la page d'administration affiche une liste vide.
 *
 * Une migration déclare :
 *   'label'       => libellé affiché,
 *   'description' => texte d'aide,
 *   'version'     => version cible (chaîne), verrouille l'exécution une fois atteinte,
 *   'run'         => callable exécuté au clic sur "Lancer" — doit renvoyer une chaîne ou un
 *                     tableau de résultats à journaliser,
 *   'rollback'    => callable optionnel exécuté au clic sur "Restaurer".
 */

if (!defined('ABSPATH')) exit;

function gws_core_register_migration($slug, $args) {
  global $gws_core_migrations;
  if (!isset($gws_core_migrations)) $gws_core_migrations = array();
  $gws_core_migrations[$slug] = wp_parse_args($args, array(
    'label' => $slug,
    'description' => '',
    'version' => '1.0.0',
    'run' => null,
    'rollback' => null,
  ));
}

function gws_core_get_registered_migrations() {
  global $gws_core_migrations;
  return isset($gws_core_migrations) ? $gws_core_migrations : array();
}

function gws_core_migration_applied_version($slug) {
  return get_option('gws_core_migration_version_' . $slug, '');
}

function gws_core_migration_log_append($entry) {
  $log = get_option('gws_core_migration_log', array());
  if (!is_array($log)) $log = array();
  $entry['date'] = current_time('mysql');
  $user = wp_get_current_user();
  $entry['user'] = $user ? $user->user_login : '';
  $log[] = $entry;
  if (count($log) > 200) $log = array_slice($log, -200);
  update_option('gws_core_migration_log', $log, false);
}

function gws_core_run_migration($slug) {
  $migrations = gws_core_get_registered_migrations();
  if (!isset($migrations[$slug]) || !is_callable($migrations[$slug]['run'])) return false;
  $migration = $migrations[$slug];
  if (gws_core_migration_applied_version($slug) === $migration['version']) return false;
  $result = call_user_func($migration['run']);
  update_option('gws_core_migration_version_' . $slug, $migration['version'], false);
  gws_core_migration_log_append(array('slug' => $slug, 'action' => 'run', 'result' => $result));
  return true;
}

function gws_core_rollback_migration($slug) {
  $migrations = gws_core_get_registered_migrations();
  if (!isset($migrations[$slug]) || !is_callable($migrations[$slug]['rollback'])) return false;
  $result = call_user_func($migrations[$slug]['rollback']);
  delete_option('gws_core_migration_version_' . $slug);
  gws_core_migration_log_append(array('slug' => $slug, 'action' => 'rollback', 'result' => $result));
  return true;
}

/**
 * Helpers optionnels réutilisables par le callback 'run' d'une migration : remplacement du
 * contenu d'un post avec sauvegarde préalable, et restauration. wp_slash() est indispensable
 * ici : update_post_meta()/wp_update_post() appliquent en interne wp_unslash(), donc tout
 * backslash littéral du contenu d'origine serait sinon perdu.
 */
function gws_core_migration_backup_and_replace_post_content($post_id, $new_content, $backup_meta_key = '_gws_migration_backup_content') {
  $post = get_post($post_id);
  if (!$post) return false;
  if (!metadata_exists('post', $post_id, $backup_meta_key)) {
    update_post_meta($post_id, $backup_meta_key, wp_slash($post->post_content));
    update_post_meta($post_id, $backup_meta_key . '_date', current_time('mysql'));
  }
  wp_update_post(array('ID' => $post_id, 'post_content' => wp_slash($new_content)));
  return true;
}

function gws_core_migration_restore_post_content($post_id, $backup_meta_key = '_gws_migration_backup_content') {
  if (!metadata_exists('post', $post_id, $backup_meta_key)) return false;
  $backup = get_post_meta($post_id, $backup_meta_key, true);
  wp_update_post(array('ID' => $post_id, 'post_content' => wp_slash($backup)));
  return true;
}

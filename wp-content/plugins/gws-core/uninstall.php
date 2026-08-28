<?php
/**
 * Nettoyage à la désinstallation (pas à la simple désactivation). Supprime les options créées
 * par le cœur du plugin. Les modules métier restent responsables du nettoyage de leurs propres
 * données (CPT, meta) s'ils en ont besoin — volontairement non générique ici pour ne pas risquer
 * de supprimer du contenu métier réel sans confirmation explicite du projet.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) exit;

delete_option('gws_core_settings');
delete_option('gws_core_migration_log');
delete_option('gws_guides_seeded');

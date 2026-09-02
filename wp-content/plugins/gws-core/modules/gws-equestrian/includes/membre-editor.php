<?php
/**
 * Présentation de l'écran d'édition d'une fiche membre — désactivation de l'éditeur par blocs
 * (même arbitrage que Cheval, includes/cheval-editor.php : la fiche est 100% structurée, sans
 * support 'editor', un éditeur par blocs vide n'apporterait aucun bénéfice) et masquage du champ
 * Titre natif.
 *
 * MASQUAGE DU TITRE NATIF (§8 de la demande) : le titre technique WordPress (post_title) est
 * entièrement dérivé de Prénom + Nom (voir gwseq_auto_title_membre() dans membre-fields.php) —
 * afficher malgré tout le champ natif "Ajouter un titre" laisserait croire à une saisie possible
 * alors que toute valeur qui y serait tapée serait silencieusement remplacée au prochain
 * enregistrement. Solution volontairement simple et non fragile, même technique que
 * includes/cheval-categories.php (règle CSS ciblant l'identifiant natif du bloc, #titlediv,
 * chargée uniquement sur l'écran d'édition/de création d'une fiche membre) : aucun JavaScript,
 * 'title' reste un support déclaré du post type (post_title continue d'exister nativement pour le
 * stockage/tri/recherche), seul son bloc de saisie visuel disparaît.
 */

if (!defined('ABSPATH')) exit;

function gwseq_disable_block_editor_for_membre($use_block_editor, $post_type) {
  if ($post_type === GWSEQ_CPT_MEMBRE) return false;
  return $use_block_editor;
}
add_filter('use_block_editor_for_post_type', 'gwseq_disable_block_editor_for_membre', 10, 2);

function gwseq_hide_membre_native_title_box() {
  $screen = function_exists('get_current_screen') ? get_current_screen() : null;
  if (!$screen || $screen->post_type !== GWSEQ_CPT_MEMBRE) return;
  echo '<style>#titlediv { display: none !important; }</style>';
}
add_action('admin_head-post.php', 'gwseq_hide_membre_native_title_box');
add_action('admin_head-post-new.php', 'gwseq_hide_membre_native_title_box');

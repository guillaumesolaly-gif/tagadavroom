<?php
/**
 * Masque l'affordance native "+ Ajouter une nouvelle catégorie" sur l'écran d'édition d'une fiche
 * cheval (§7-8 de la demande) : le client crée/gère librement ses catégories depuis
 * Chevaux → Catégories, mais depuis la fiche elle-même, seule la sélection de catégories
 * existantes est proposée — pour éviter la création accidentelle de doublons quasi identiques
 * (« Chevaux à vendre » / « Chevaux a vendre » / « A vendre »...) directement depuis ce formulaire.
 *
 * Solution volontairement simple et non fragile : une règle CSS ciblant l'identifiant natif que
 * WordPress donne systématiquement à ce bloc (post_categories_meta_box(), voir includes/
 * taxonomies.php pour son activation via meta_box_cb) — aucun JavaScript, aucune modification du
 * rendu natif de la case à cocher elle-même, qui reste pleinement fonctionnelle. Chargée
 * uniquement sur l'écran d'édition/de création d'une fiche cheval.
 */

if (!defined('ABSPATH')) exit;

function gwseq_hide_cheval_category_quick_add() {
  $screen = function_exists('get_current_screen') ? get_current_screen() : null;
  if (!$screen || $screen->post_type !== GWSEQ_CPT_CHEVAL) return;
  echo '<style>#' . esc_attr(GWSEQ_TAX_CATEGORIE_CHEVAL) . '-adder { display: none !important; }</style>';
}
add_action('admin_head-post.php', 'gwseq_hide_cheval_category_quick_add');
add_action('admin_head-post-new.php', 'gwseq_hide_cheval_category_quick_add');

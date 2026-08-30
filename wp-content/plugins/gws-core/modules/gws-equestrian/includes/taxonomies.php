<?php
/**
 * Étape 1 : taxonomie de catégories de cheval, enregistrée avec l'interface WordPress native
 * (saisie libre façon étiquettes).
 *
 * Étape 4 (§6-7 de la demande) : remplacement par une interface à cases à cocher, plus lisible
 * pour un usage multi-valeurs et sans jamais exiger de comprendre le fonctionnement des
 * "étiquettes" WordPress. Solution native complète : `meta_box_cb` réutilise directement
 * post_categories_meta_box() (le même rendu que la boîte "Catégories" native des articles),
 * WordPress lui transmettant automatiquement les bons arguments pour cette taxonomie — aucun rendu
 * personnalisé. Voir includes/cheval-categories.php pour le masquage de l'affordance de création
 * rapide directement depuis la fiche (§8).
 */

if (!defined('ABSPATH')) exit;

function gwseq_register_taxonomies() {
  register_taxonomy(GWSEQ_TAX_CATEGORIE_CHEVAL, GWSEQ_CPT_CHEVAL, array(
    'labels' => array(
      'name' => __('Catégories de chevaux', 'gws-core'),
      'singular_name' => __('Catégorie de cheval', 'gws-core'),
      'add_new_item' => __('Ajouter une catégorie de cheval', 'gws-core'),
    ),
    'public' => true,
    'hierarchical' => false,
    'show_in_rest' => true,
    'meta_box_cb' => 'post_categories_meta_box',
    'rewrite' => array('slug' => 'categorie-cheval'),
  ));
}
add_action('init', 'gwseq_register_taxonomies');

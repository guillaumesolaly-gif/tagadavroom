<?php
/**
 * Étape 1 : taxonomie de catégories de cheval, enregistrée avec l'interface WordPress native
 * (saisie libre façon étiquettes). Le remplacement par une interface à cases à cocher — retenu
 * dans la conception validée pour un usage multi-valeurs plus lisible côté métier — est prévu à
 * l'étape 4, en même temps que le reste de l'écran d'administration de la fiche cheval : pas de
 * bénéfice à construire cette UI avant que ce formulaire n'existe.
 */

if (!defined('ABSPATH')) exit;

function gwseq_register_taxonomies() {
  register_taxonomy(GWSEQ_TAX_CATEGORIE_CHEVAL, GWSEQ_CPT_CHEVAL, array(
    'labels' => array(
      'name' => 'Catégories de chevaux',
      'singular_name' => 'Catégorie de cheval',
      'add_new_item' => 'Ajouter une catégorie de cheval',
    ),
    'public' => true,
    'hierarchical' => false,
    'show_in_rest' => true,
    'rewrite' => array('slug' => 'categorie-cheval'),
  ));
}
add_action('init', 'gwseq_register_taxonomies');

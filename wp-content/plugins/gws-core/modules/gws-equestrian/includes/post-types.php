<?php
/**
 * Étape 1 : enregistrement minimal des trois types de contenu du module, avec les seuls
 * réglages structurants (public/privé, archive, rewrite) déjà arbitrés par la conception
 * validée — aucun champ ni formulaire métier. Les écrans d'administration natifs de WordPress
 * (titre/contenu/image à la une) suffisent à cette étape pour prouver l'enregistrement, la
 * persistance et le comportement à la désactivation/réactivation du module.
 *
 * Rappel de conception (voir la proposition validée) : le Groupe tarifaire est un objet
 * d'organisation interne, jamais une page publique — pas d'archive, pas de rewrite, pas de
 * résultat de recherche, pour ne pas polluer le front avec une URL sans contenu éditorial réel.
 */

if (!defined('ABSPATH')) exit;

function gwseq_register_post_types() {
  register_post_type(GWSEQ_CPT_PRESTATION, array(
    'labels' => array(
      'name' => 'Prestations',
      'singular_name' => 'Prestation',
      'add_new_item' => 'Ajouter une prestation',
      'edit_item' => 'Modifier la prestation',
      'all_items' => 'Prestations',
      'not_found' => 'Aucune prestation trouvée',
    ),
    'public' => true,
    'has_archive' => true,
    'show_in_rest' => true,
    'menu_icon' => 'dashicons-cart',
    'supports' => array('title', 'editor', 'thumbnail'),
    'rewrite' => array('slug' => 'prestations'),
  ));

  register_post_type(GWSEQ_CPT_GROUPE, array(
    'labels' => array(
      'name' => 'Groupes tarifaires',
      'singular_name' => 'Groupe tarifaire',
      'add_new_item' => 'Ajouter un groupe tarifaire',
      'edit_item' => 'Modifier le groupe tarifaire',
      'all_items' => 'Groupes tarifaires',
      'not_found' => 'Aucun groupe tarifaire trouvé',
    ),
    'public' => false,
    'publicly_queryable' => false,
    'has_archive' => false,
    'exclude_from_search' => true,
    'show_in_nav_menus' => false,
    'show_ui' => true,
    'show_in_menu' => true,
    'show_in_rest' => false,
    'menu_icon' => 'dashicons-category',
    'supports' => array('title'),
    'rewrite' => false,
  ));

  register_post_type(GWSEQ_CPT_CHEVAL, array(
    'labels' => array(
      'name' => 'Chevaux',
      'singular_name' => 'Cheval',
      'add_new_item' => 'Ajouter un cheval',
      'edit_item' => 'Modifier la fiche cheval',
      'all_items' => 'Chevaux',
      'not_found' => 'Aucun cheval trouvé',
    ),
    'public' => true,
    'has_archive' => true,
    'show_in_rest' => true,
    'menu_icon' => 'dashicons-pets',
    'supports' => array('title', 'editor', 'thumbnail'),
    'rewrite' => array('slug' => 'chevaux'),
  ));
}
add_action('init', 'gwseq_register_post_types');

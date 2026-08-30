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
 *
 * Étape 3 : ajout du support natif 'page-attributes' (Prestation et Groupe) pour l'ordre
 * d'affichage (champ menu_order déjà fourni par WordPress, aucune meta custom nécessaire) et
 * 'excerpt' (Groupe uniquement) pour sa description courte — deux champs natifs réutilisés tels
 * quels plutôt que des meta dédiées. Voir includes/admin-ui.php et includes/groupe-admin.php pour
 * le renommage de leurs libellés en vocabulaire métier.
 */

if (!defined('ABSPATH')) exit;

function gwseq_register_post_types() {
  register_post_type(GWSEQ_CPT_PRESTATION, array(
    'labels' => array(
      'name' => __('Prestations', 'gws-core'),
      'singular_name' => __('Prestation', 'gws-core'),
      'add_new_item' => __('Ajouter une prestation', 'gws-core'),
      'edit_item' => __('Modifier la prestation', 'gws-core'),
      'all_items' => __('Prestations', 'gws-core'),
      'not_found' => __('Aucune prestation trouvée', 'gws-core'),
    ),
    'public' => true,
    'has_archive' => true,
    'show_in_rest' => true,
    'menu_icon' => 'dashicons-cart',
    'supports' => array('title', 'editor', 'thumbnail', 'page-attributes'),
    'rewrite' => array('slug' => 'prestations'),
  ));

  register_post_type(GWSEQ_CPT_GROUPE, array(
    'labels' => array(
      'name' => __('Groupes tarifaires', 'gws-core'),
      'singular_name' => __('Groupe tarifaire', 'gws-core'),
      'add_new_item' => __('Ajouter un groupe tarifaire', 'gws-core'),
      'edit_item' => __('Modifier le groupe tarifaire', 'gws-core'),
      'all_items' => __('Groupes tarifaires', 'gws-core'),
      'not_found' => __('Aucun groupe tarifaire trouvé', 'gws-core'),
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
    'supports' => array('title', 'excerpt', 'page-attributes'),
    'rewrite' => false,
  ));

  register_post_type(GWSEQ_CPT_CHEVAL, array(
    'labels' => array(
      'name' => __('Chevaux', 'gws-core'),
      'singular_name' => __('Cheval', 'gws-core'),
      'add_new_item' => __('Ajouter un cheval', 'gws-core'),
      'edit_item' => __('Modifier la fiche cheval', 'gws-core'),
      'all_items' => __('Chevaux', 'gws-core'),
      'not_found' => __('Aucun cheval trouvé', 'gws-core'),
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

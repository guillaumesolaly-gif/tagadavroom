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
 *
 * Étape 4 : Cheval reçoit à son tour 'page-attributes' (ordre global — voir includes/admin-ui.php)
 * et perd le support 'editor' — aucun contenu éditorial de type article n'est développé à cette
 * étape (§22 de la demande ; les blocs éditoriaux appartiennent à l'Étape 6), post_content resterait
 * une source de vérité fantôme sans jamais être exploitée. Les libellés de l'image à la une sont
 * également adaptés en "Photo principale" (§3/§5) : Featured Image reste l'unique source de vérité
 * pour la photo principale, aucune meta parallèle n'est créée.
 *
 * Module Équipe (nouvel objet métier, distinct de Cheval) : un Membre reprend exactement la même
 * philosophie — 'page-attributes' pour l'ordre (menu_order natif, voir includes/admin-ui.php),
 * 'thumbnail' relabellé "Photo" comme unique source de vérité pour la photo (aucune galerie ici,
 * contrairement à Cheval), et pas de support 'editor' (fiche 100% structurée, voir
 * includes/membre-fields.php et includes/membre-editor.php). Le titre technique WordPress
 * (post_title) n'est PAS saisi manuellement pour ce post type : il est automatiquement dérivé de
 * Prénom + Nom (voir gwseq_auto_title_membre() dans includes/membre-fields.php), 'title' reste
 * néanmoins un support déclaré (stockage/tri/recherche natifs par titre inchangés).
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
      // Sans ce libellé explicite, WordPress replie sur le défaut générique "Rechercher des
      // articles" (search_items n'est jamais dérivé automatiquement de 'name' — voir CHANGELOG.md).
      'search_items' => __('Rechercher une prestation', 'gws-core'),
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
      'search_items' => __('Rechercher un groupe tarifaire', 'gws-core'),
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
      'search_items' => __('Rechercher un cheval', 'gws-core'),
      'featured_image' => __('Photo principale', 'gws-core'),
      'set_featured_image' => __('Définir la photo principale', 'gws-core'),
      'remove_featured_image' => __('Supprimer la photo principale', 'gws-core'),
      'use_featured_image' => __('Utiliser comme photo principale', 'gws-core'),
    ),
    'public' => true,
    'has_archive' => true,
    'show_in_rest' => true,
    'menu_icon' => 'dashicons-pets',
    'supports' => array('title', 'thumbnail', 'page-attributes'),
    'rewrite' => array('slug' => 'chevaux'),
  ));

  register_post_type(GWSEQ_CPT_MEMBRE, array(
    'labels' => array(
      'name' => __('Équipe', 'gws-core'),
      'singular_name' => __('Membre', 'gws-core'),
      'add_new_item' => __('Ajouter un membre', 'gws-core'),
      'edit_item' => __('Modifier le membre', 'gws-core'),
      'all_items' => __('Tous les membres', 'gws-core'),
      'not_found' => __('Aucun membre trouvé', 'gws-core'),
      // Sans ce libellé explicite, WordPress replie sur défaut générique "Rechercher des
      // articles" (search_items n'est PAS dérivé automatiquement de 'name' — correctif runtime,
      // voir CHANGELOG.md).
      'search_items' => __('Rechercher des membres', 'gws-core'),
      'featured_image' => __('Photo', 'gws-core'),
      'set_featured_image' => __('Définir la photo', 'gws-core'),
      'remove_featured_image' => __('Supprimer la photo', 'gws-core'),
      'use_featured_image' => __('Utiliser comme photo', 'gws-core'),
    ),
    'public' => true,
    'has_archive' => true,
    'show_in_rest' => true,
    'menu_icon' => 'dashicons-groups',
    'supports' => array('title', 'thumbnail', 'page-attributes'),
    'rewrite' => array('slug' => 'equipe'),
  ));

  /**
   * Mises en avant (Pop-in / Sticky bar) : DEUX post types techniquement distincts, réunis sous
   * UNE SEULE entrée de menu d'administration ("Mises en avant") pour ne jamais multiplier les
   * menus principaux du futur BO — jamais un troisième objet WordPress ni un menu personnalisé
   * dupliqué. Technique 100% native : `gwseq_popin` porte le menu principal (son propre
   * `show_in_menu => true`, avec `labels->name` = "Mises en avant" — donc le libellé affiché en
   * haut du menu — et `labels->all_items` = "Pop-ins", le premier sous-menu, automatiquement ajouté
   * par WordPress pour tout post type avec sa propre entrée de menu). `gwseq_sticky_bar` s'y
   * rattache en second sous-menu via `show_in_menu => 'edit.php?post_type=gwseq_popin'` (le slug
   * exact du menu déjà créé par `gwseq_popin` — mécanisme natif `_add_post_type_submenus()`),
   * `labels->all_items` = "Sticky bars". Chaque post type garde son écran de liste natif et
   * indépendant (`edit.php?post_type=...`), aucune fusion de données.
   *
   * Ni public (jamais une page/URL front autonome : ces campagnes sont des overlays affichés PAR-
   * DESSUS d'autres contenus, jamais des fiches consultables en elles-mêmes — même logique déjà
   * appliquée à Groupe tarifaire), ni Gutenberg (`gwseq_disable_block_editor_for_popin()`/
   * `..._sticky_bar()`, voir includes/popin-fields.php / includes/sticky-bar-fields.php : fiche
   * structurée, jamais un page builder). `page-attributes` fournit l'ordre natif (`menu_order`),
   * réutilisé comme mécanisme de priorité en cas de campagnes concurrentes (voir
   * includes/campagnes-front.php) — jamais un second champ "Priorité" inventé. `title` = "Nom
   * interne", jamais affiché publiquement (post type non public).
   */
  register_post_type(GWSEQ_CPT_POPIN, array(
    'labels' => array(
      'name' => __('Mises en avant', 'gws-core'),
      'singular_name' => __('Pop-in', 'gws-core'),
      'add_new_item' => __('Ajouter une pop-in', 'gws-core'),
      'edit_item' => __('Modifier la pop-in', 'gws-core'),
      'all_items' => __('Pop-ins', 'gws-core'),
      'not_found' => __('Aucune pop-in trouvée', 'gws-core'),
      'search_items' => __('Rechercher une pop-in', 'gws-core'),
    ),
    'public' => false,
    'publicly_queryable' => false,
    'has_archive' => false,
    'exclude_from_search' => true,
    'show_in_nav_menus' => false,
    'show_ui' => true,
    'show_in_menu' => true,
    'show_in_rest' => false,
    'menu_icon' => 'dashicons-megaphone',
    'supports' => array('title', 'page-attributes'),
    'rewrite' => false,
  ));

  register_post_type(GWSEQ_CPT_STICKY_BAR, array(
    'labels' => array(
      'name' => __('Sticky bars', 'gws-core'),
      'singular_name' => __('Sticky bar', 'gws-core'),
      'add_new_item' => __('Ajouter une sticky bar', 'gws-core'),
      'edit_item' => __('Modifier la sticky bar', 'gws-core'),
      'all_items' => __('Sticky bars', 'gws-core'),
      'not_found' => __('Aucune sticky bar trouvée', 'gws-core'),
      'search_items' => __('Rechercher une sticky bar', 'gws-core'),
    ),
    'public' => false,
    'publicly_queryable' => false,
    'has_archive' => false,
    'exclude_from_search' => true,
    'show_in_nav_menus' => false,
    'show_ui' => true,
    'show_in_menu' => 'edit.php?post_type=' . GWSEQ_CPT_POPIN,
    'show_in_rest' => false,
    'supports' => array('title', 'page-attributes'),
    'rewrite' => false,
  ));
}
add_action('init', 'gwseq_register_post_types');

<?php
/**
 * Champs SEO génériques (titre et méta-description de secours) — persistants, indépendants du
 * thème actif. Le thème décide seul s'il les affiche (et s'efface si un plugin SEO tiers est
 * actif) ; ce fichier ne fait qu'enregistrer et proposer l'édition de la donnée.
 *
 * Actif par défaut sur les pages ('page'). Un module métier ajoute son propre post type via le
 * filtre 'gws_core_seo_post_types'.
 */

if (!defined('ABSPATH')) exit;

function gws_core_seo_post_types() {
  return apply_filters('gws_core_seo_post_types', array('page'));
}

function gws_core_seo_field_schema() {
  return array(
    '_gws_seo_title' => array('label' => 'Titre SEO', 'type' => 'text', 'description' => 'Laisser vide pour utiliser le titre WordPress de la page.'),
    '_gws_seo_description' => array('label' => 'Méta-description', 'type' => 'textarea', 'description' => 'Résumé destiné aux moteurs de recherche, environ 150-160 caractères.'),
  );
}

function gws_core_register_seo_meta() {
  foreach (gws_core_seo_post_types() as $post_type) {
    gws_core_register_field_meta_box('gws-seo-meta-' . $post_type, 'Référencement', $post_type, gws_core_seo_field_schema(), 'gws_core_save_seo_meta_' . $post_type);
  }
}
add_action('init', 'gws_core_register_seo_meta');

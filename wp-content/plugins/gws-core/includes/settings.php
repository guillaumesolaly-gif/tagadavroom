<?php
/**
 * Réglages génériques de l'entité (nom, coordonnées, réseaux sociaux) — seule source de vérité,
 * indépendante du thème actif. Un site métier étend cette liste via le filtre
 * 'gws_core_settings_fields' plutôt qu'en modifiant ce fichier.
 */

if (!defined('ABSPATH')) exit;

function gws_core_settings_defaults() {
  $defaults = array(
    'entity_name' => '',
    'phone_display' => '',
    'public_email' => '',
    'address_line' => '',
    'postal_code' => '',
    'city' => '',
    'social_links' => '', // une URL par ligne ; libre à un projet de structurer davantage via le filtre ci-dessous
  );
  return apply_filters('gws_core_settings_defaults', $defaults);
}

/**
 * Schéma d'affichage de l'écran de réglages. Un module métier peut ajouter ses propres champs
 * de réglages globaux via ce filtre, sans toucher à ce fichier.
 */
function gws_core_settings_fields() {
  $fields = array(
    'entity_name' => array('label' => 'Nom de l’entité', 'type' => 'text', 'description' => 'Nom affiché dans les données structurées et les gabarits.'),
    'phone_display' => array('label' => 'Téléphone', 'type' => 'text', 'description' => 'Format affiché sur le site.'),
    'public_email' => array('label' => 'E-mail public', 'type' => 'email', 'description' => ''),
    'address_line' => array('label' => 'Adresse', 'type' => 'text', 'description' => ''),
    'postal_code' => array('label' => 'Code postal', 'type' => 'text', 'description' => ''),
    'city' => array('label' => 'Ville', 'type' => 'text', 'description' => ''),
    'social_links' => array('label' => 'Réseaux sociaux', 'type' => 'textarea', 'description' => 'Une URL par ligne.'),
  );
  return apply_filters('gws_core_settings_fields', $fields);
}

function gws_core_settings() {
  return wp_parse_args((array) get_option('gws_core_settings', array()), gws_core_settings_defaults());
}

/**
 * Point d'accès public utilisé par le thème (et tout module) pour lire un réglage.
 * Retourne toujours une chaîne (jamais d'erreur) même si la clé est inconnue.
 */
function gws_core_get_setting($key) {
  $settings = gws_core_settings();
  return isset($settings[$key]) ? $settings[$key] : '';
}

function gws_core_phone_href() {
  $digits = preg_replace('/\D+/', '', gws_core_get_setting('phone_display'));
  return $digits ? '+' . ltrim($digits, '0') : '';
}

function gws_core_sanitize_settings($input) {
  $input = is_array($input) ? $input : array();
  $clean = array();
  foreach (gws_core_settings_fields() as $key => $field) {
    $raw = $input[$key] ?? '';
    $clean[$key] = gws_core_field_sanitize($field['type'] ?? 'text', $raw);
  }
  return $clean;
}

function gws_core_register_settings() {
  register_setting('gws_core_settings_group', 'gws_core_settings', array(
    'type' => 'array',
    'sanitize_callback' => 'gws_core_sanitize_settings',
    'default' => gws_core_settings_defaults(),
  ));
}
add_action('admin_init', 'gws_core_register_settings');

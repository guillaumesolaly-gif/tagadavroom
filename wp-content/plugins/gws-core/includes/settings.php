<?php
/**
 * Réglages génériques de l'entité (identité, coordonnées, réseaux sociaux, logo) — seule
 * source de vérité, indépendante du thème actif. Un site métier étend cette liste via le filtre
 * 'gws_core_settings_fields' plutôt qu'en modifiant ce fichier — c'est pourquoi seuls des
 * réseaux réellement universels figurent ici (pas de Viber, Messenger, etc.).
 */

if (!defined('ABSPATH')) exit;

function gws_core_settings_defaults() {
  $defaults = array(
    'entity_name' => '',
    'phone_display' => '',
    'public_email' => '',
    'whatsapp_number' => '',
    'address_line' => '',
    'postal_code' => '',
    'city' => '',
    'logo_id' => 0,
    'linkedin_url' => '',
    'facebook_url' => '',
    'instagram_url' => '',
    'youtube_url' => '',
    'tiktok_url' => '',
    'google_business_url' => '',
    'social_links' => '', // une URL par ligne ; libre à un projet de structurer davantage via le filtre ci-dessous
    'credit_enabled' => '1',
    'credit_url' => 'https://tagadavroom.fr/',
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
    'whatsapp_number' => array('label' => 'Numéro WhatsApp', 'type' => 'text', 'description' => 'Numéro complet, sans le 0 initial (ex. 33612345678) — sert à générer un lien wa.me. Laisser vide pour ne rien afficher.'),
    'address_line' => array('label' => 'Adresse', 'type' => 'text', 'description' => ''),
    'postal_code' => array('label' => 'Code postal', 'type' => 'text', 'description' => ''),
    'city' => array('label' => 'Ville', 'type' => 'text', 'description' => ''),
    'logo_id' => array('label' => 'Logo', 'type' => 'attachment_id', 'description' => 'Utilisé dans l’en-tête du site (si le thème le prend en charge) et dans les données structurées. Facultatif : sans logo, le nom de l’entité s’affiche en texte.'),
    'linkedin_url' => array('label' => 'LinkedIn', 'type' => 'url', 'description' => ''),
    'facebook_url' => array('label' => 'Facebook', 'type' => 'url', 'description' => ''),
    'instagram_url' => array('label' => 'Instagram', 'type' => 'url', 'description' => ''),
    'youtube_url' => array('label' => 'YouTube', 'type' => 'url', 'description' => ''),
    'tiktok_url' => array('label' => 'TikTok', 'type' => 'url', 'description' => ''),
    'google_business_url' => array('label' => 'Fiche Google Business Profile', 'type' => 'url', 'description' => ''),
    'social_links' => array('label' => 'Autres réseaux sociaux', 'type' => 'textarea', 'description' => 'Une URL par ligne, pour un réseau non listé ci-dessus.'),
    'credit_enabled' => array('label' => 'Crédit de réalisation', 'type' => 'checkbox', 'checkbox_label' => 'Afficher « Site réalisé par Tagada Vroom » dans le pied de page', 'description' => ''),
    'credit_url' => array('label' => 'URL Tagada Vroom', 'type' => 'url', 'description' => 'Le crédit ci-dessus ne s’affiche que si cette adresse est renseignée.'),
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

/**
 * Logo de l'entité : un seul ID d'attachement, source unique pour le thème et le Schema.
 */
function gws_core_get_logo_id() {
  return (int) gws_core_get_setting('logo_id');
}

function gws_core_get_logo_url($size = 'full') {
  $id = gws_core_get_logo_id();
  if (!$id) return '';
  $url = wp_get_attachment_image_url($id, $size);
  return $url ?: '';
}

/**
 * Lien wa.me construit à partir du numéro renseigné — vide si aucun numéro.
 */
function gws_core_whatsapp_url() {
  $digits = preg_replace('/\D+/', '', gws_core_get_setting('whatsapp_number'));
  return $digits ? 'https://wa.me/' . $digits : '';
}

/**
 * Réseaux sociaux structurés réellement renseignés, sous la forme ['linkedin' => 'https://...'].
 * Un réseau vide n'apparaît simplement pas dans le tableau retourné.
 */
function gws_core_social_links() {
  $map = array(
    'linkedin' => 'linkedin_url',
    'facebook' => 'facebook_url',
    'instagram' => 'instagram_url',
    'youtube' => 'youtube_url',
    'tiktok' => 'tiktok_url',
  );
  $links = array();
  foreach ($map as $network => $key) {
    $url = gws_core_get_setting($key);
    if ($url) $links[$network] = $url;
  }
  return $links;
}

function gws_core_google_business_url() {
  return gws_core_get_setting('google_business_url');
}

/**
 * Analyse le champ libre 'social_links' (une URL par ligne, extension générique pour un réseau
 * non listé ci-dessus) : lignes vides ignorées, chaque URL restante sanitizée puis validée —
 * jamais de ligne vide ou invalide dans le résultat. Volontairement absent de
 * gws_core_social_links() : les réseaux nommés y restent seuls, pour rester facilement
 * exploitables individuellement en front (icône dédiée, libellé...) ; ce champ libre n'alimente
 * que le Schema, via gws_core_schema_same_as() ci-dessous.
 */
function gws_core_extra_social_urls() {
  $raw = gws_core_get_setting('social_links');
  if (!$raw) return array();
  $urls = array();
  foreach (preg_split('/[\r\n]+/', $raw) as $line) {
    $line = trim($line);
    if ($line === '') continue;
    $url = esc_url_raw($line);
    if ($url && wp_http_validate_url($url)) $urls[] = $url;
  }
  return array_values(array_unique($urls));
}

/**
 * Liste dédupliquée des URLs à publier dans un `sameAs` Schema.org : réseaux structurés, fiche
 * Google Business Profile, et le champ d'extension libre 'social_links'. Ne contient jamais de
 * chaîne vide ou invalide.
 */
function gws_core_schema_same_as() {
  $urls = array_values(gws_core_social_links());
  $gbp = gws_core_google_business_url();
  if ($gbp) $urls[] = $gbp;
  $urls = array_merge($urls, gws_core_extra_social_urls());
  return array_values(array_unique(array_filter($urls)));
}

function gws_core_credit_enabled() {
  return gws_core_get_setting('credit_enabled') === '1';
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

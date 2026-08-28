<?php
/**
 * Enrichissement du graphe Schema.org généré par Yoast SEO — n'agit que si Yoast est actif.
 * Complète le nœud Organization existant via les filtres officiels de Yoast
 * (wpseo_schema_organization) : jamais de graphe parallèle, jamais de sortie meta/canonical
 * propre à ce fichier.
 *
 * Ce fichier n'est chargé par functions.php que si WPSEO_VERSION est défini — la garde
 * conditionnelle autour du corps du fichier n'est donc pas nécessaire ici (contrairement à un
 * chargement inconditionnel), mais on la documente : n'ajouter un filtre wpseo_* qu'à
 * l'intérieur de cette condition si ce fichier était un jour require() sans garde amont.
 */

if (!defined('ABSPATH') || !defined('WPSEO_VERSION')) return;

function gws_enrich_yoast_organization($data) {
  $phone = gws_phone_href();
  if ($phone) $data['telephone'] = $phone;

  $email = gws_get_setting('public_email');
  if ($email) $data['email'] = $email;

  $address_line = gws_get_setting('address_line');
  if ($address_line) {
    $data['address'] = array(
      '@type' => 'PostalAddress',
      'streetAddress' => $address_line,
      'postalCode' => gws_get_setting('postal_code'),
      'addressLocality' => gws_get_setting('city'),
    );
  }

  // Additif uniquement : ne jamais écraser un logo (souvent un ImageObject plus riche) ou des
  // sameAs déjà renseignés par les propres réglages de Yoast.
  if (empty($data['logo']) && function_exists('gws_core_get_logo_url')) {
    $logo = gws_core_get_logo_url();
    if ($logo) $data['logo'] = $logo;
  }
  if (function_exists('gws_core_schema_same_as')) {
    $same_as = gws_core_schema_same_as();
    if ($same_as) {
      $existing = isset($data['sameAs']) && is_array($data['sameAs']) ? $data['sameAs'] : array();
      $data['sameAs'] = array_values(array_unique(array_merge($existing, $same_as)));
    }
  }

  return $data;
}
add_filter('wpseo_schema_organization', 'gws_enrich_yoast_organization', 11);

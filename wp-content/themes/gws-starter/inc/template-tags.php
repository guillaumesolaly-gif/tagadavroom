<?php
/**
 * Fonctions d'affichage transverses, réutilisables par n'importe quel gabarit.
 */

if (!defined('ABSPATH')) exit;

function gws_breadcrumb() {
  if (is_front_page()) return;
  echo '<nav class="breadcrumb" aria-label="Fil d’Ariane"><a href="' . esc_url(home_url('/')) . '">Accueil</a><span aria-hidden="true">→</span><span>' . esc_html(get_the_title()) . '</span></nav>';
}

function gws_render_contact_card() {
  $email = gws_get_setting('public_email');
  $phone = gws_get_setting('phone_display');
  $address = gws_get_setting('address_line');
  $city_line = trim(gws_get_setting('postal_code') . ' ' . gws_get_setting('city'));
  echo '<div class="card contact-card">';
  if ($phone) echo '<p><span class="label">Téléphone</span><a href="tel:' . esc_attr(gws_phone_href()) . '">' . esc_html($phone) . '</a></p>';
  if ($address) echo '<p><span class="label">Adresse</span><strong>' . esc_html($address) . ($city_line ? '<br>' . esc_html($city_line) : '') . '</strong></p>';
  if ($email) echo '<a class="btn btn-primary" href="mailto:' . esc_attr($email) . '">Nous écrire ' . gws_icon('arrow_forward') . '</a>';
  echo '</div>';
}

/**
 * Crédit de réalisation Tagada Vroom — affiché uniquement si l'option est activée ET qu'une
 * URL valide est renseignée (Réglages > Entité). Aucun markup si l'une des deux conditions
 * manque. Ancre naturelle, aucun `target="_blank"` forcé.
 */
function gws_render_credit() {
  if (!function_exists('gws_core_credit_enabled') || !gws_core_credit_enabled()) return;
  $url = gws_get_setting('credit_url');
  if (!$url) return;
  echo '<p class="site-credit">Site réalisé par <a href="' . esc_url($url) . '">Tagada Vroom</a></p>';
}

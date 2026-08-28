<?php
/**
 * Le thème dépend du plugin compagnon gws-core pour les données persistantes (réglages, SEO).
 * Ces enveloppes évitent toute erreur fatale si ce plugin est désactivé par erreur : elles
 * retournent une valeur neutre plutôt que de planter le rendu.
 */

if (!defined('ABSPATH')) exit;

function gws_get_setting($key) {
  return function_exists('gws_core_get_setting') ? gws_core_get_setting($key) : '';
}

function gws_phone_href() {
  return function_exists('gws_core_phone_href') ? gws_core_phone_href() : '';
}

function gws_admin_notice_if_core_missing() {
  if (function_exists('gws_core_get_setting')) return;
  echo '<div class="notice notice-warning"><p>Le plugin compagnon <strong>GWS Core</strong> est inactif : les réglages de l’entité et les champs SEO ne sont pas disponibles. Activez-le pour un fonctionnement complet du thème.</p></div>';
}
add_action('admin_notices', 'gws_admin_notice_if_core_missing');

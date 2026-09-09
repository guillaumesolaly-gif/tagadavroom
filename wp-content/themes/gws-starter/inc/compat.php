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

/**
 * Nom de la structure (Ma structure > Nom de la structure), avec repli natif WordPress si le
 * champ est vide — centralise ce qui était dupliqué à l'identique dans site-header.php,
 * site-footer.php et inc/schema.php (Lot 2C).
 */
function gws_structure_name() {
  return function_exists('gws_core_structure_name') ? gws_core_structure_name() : get_bloginfo('name');
}

function gws_phone_href() {
  return function_exists('gws_core_phone_href') ? gws_core_phone_href() : '';
}

function gws_get_logo_url($size = 'full') {
  return function_exists('gws_core_get_logo_url') ? gws_core_get_logo_url($size) : '';
}

function gws_admin_notice_if_core_missing() {
  if (function_exists('gws_core_get_setting')) return;
  echo '<div class="notice notice-warning"><p>Le plugin compagnon <strong>GWS Core</strong> est inactif : les réglages de l’entité et les champs SEO ne sont pas disponibles. Activez-le pour un fonctionnement complet du thème.</p></div>';
}
add_action('admin_notices', 'gws_admin_notice_if_core_missing');

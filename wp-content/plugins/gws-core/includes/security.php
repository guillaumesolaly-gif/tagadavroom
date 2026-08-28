<?php
/**
 * Helpers réutilisables pour sécuriser un formulaire public (contact, prise de contact,
 * questionnaire...) : nonce, pot de miel, délai anti-bot, limite de tentatives par IP. Logique
 * métier persistante au sens où un formulaire doit continuer à fonctionner et rester protégé
 * quel que soit le thème actif — d'où sa place ici plutôt que dans le thème.
 */

if (!defined('ABSPATH')) exit;

/**
 * À insérer dans le <form> : nonce, pot de miel invisible et horodatage de départ (anti-bot).
 */
function gws_core_security_fields($nonce_action) {
  wp_nonce_field($nonce_action, $nonce_action . '_nonce');
  echo '<input type="text" name="website" value="" autocomplete="off" tabindex="-1" aria-hidden="true" style="position:absolute;left:-9999px">';
  echo '<input type="hidden" name="form_started" value="' . esc_attr(time()) . '">';
}

/**
 * Vérifie nonce + pot de miel + délai anti-bot pour un envoi de formulaire.
 * Retourne true si l'envoi est légitime, une chaîne d'erreur sinon ('security'|'honeypot'|'timing').
 */
function gws_core_verify_form_security($nonce_action, $min_delay_seconds = 4) {
  $nonce_field = $nonce_action . '_nonce';
  if (!isset($_POST[$nonce_field]) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[$nonce_field])), $nonce_action)) return 'security';
  if (!empty($_POST['website'])) return 'honeypot';
  $started = isset($_POST['form_started']) ? absint($_POST['form_started']) : 0;
  if (!$started || time() - $started < $min_delay_seconds || time() - $started > DAY_IN_SECONDS) return 'timing';
  return true;
}

/**
 * Limite de tentatives par IP, sans table dédiée (transient). $key_prefix doit être unique par
 * formulaire pour ne pas partager le même compteur entre deux usages différents.
 */
function gws_core_rate_limit_check($key_prefix, $max_attempts = 4, $window_seconds = HOUR_IN_SECONDS) {
  $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
  $key = $key_prefix . '_' . substr(hash_hmac('sha256', $ip, wp_salt('nonce')), 0, 24);
  $attempts = (int) get_transient($key);
  if ($attempts >= $max_attempts) return false;
  set_transient($key, $attempts + 1, $window_seconds);
  return true;
}

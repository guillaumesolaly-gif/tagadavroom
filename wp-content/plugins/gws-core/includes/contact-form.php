<?php
/**
 * Traitement du formulaire de contact générique fourni en exemple par le thème
 * (template-parts/forms/contact-form.php). Envoie un e-mail vers l'adresse publique des
 * réglages, ne stocke rien en base.
 */

if (!defined('ABSPATH')) exit;

function gws_core_handle_contact_form() {
  $redirect = wp_get_referer() ?: home_url('/');

  $check = gws_core_verify_form_security('gws_core_contact_form');
  if ($check !== true) {
    wp_safe_redirect(add_query_arg('contact', $check, $redirect)); exit;
  }
  if (!gws_core_rate_limit_check('gws_core_contact')) {
    wp_safe_redirect(add_query_arg('contact', 'limit', $redirect)); exit;
  }

  $name = isset($_POST['contact_name']) ? sanitize_text_field(wp_unslash($_POST['contact_name'])) : '';
  $email = isset($_POST['contact_email']) ? sanitize_email(wp_unslash($_POST['contact_email'])) : '';
  $message = isset($_POST['contact_message']) ? sanitize_textarea_field(wp_unslash($_POST['contact_message'])) : '';
  if (!$name || !is_email($email) || !$message) {
    wp_safe_redirect(add_query_arg('contact', 'missing', $redirect)); exit;
  }

  $to = gws_core_get_setting('public_email') ?: get_option('admin_email');
  $subject = 'Nouveau message de contact — ' . $name;
  $body = "Nom : $name\nE-mail : $email\n\nMessage :\n$message\n";
  $headers = array('Content-Type: text/plain; charset=UTF-8', 'Reply-To: ' . $name . ' <' . $email . '>');
  $sent = wp_mail($to, $subject, $body, $headers);

  wp_safe_redirect(add_query_arg('contact', $sent ? 'sent' : 'mail-error', $redirect)); exit;
}
add_action('admin_post_nopriv_gws_core_contact_form', 'gws_core_handle_contact_form');
add_action('admin_post_gws_core_contact_form', 'gws_core_handle_contact_form');

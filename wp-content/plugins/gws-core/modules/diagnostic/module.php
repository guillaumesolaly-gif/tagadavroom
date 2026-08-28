<?php
/**
 * Module « Diagnostic » — questionnaire d'auto-évaluation scoré, envoyé par e-mail sur demande
 * explicite du visiteur. Aucune donnée n'est stockée en base : ni les réponses, ni les
 * coordonnées — uniquement un envoi wp_mail() vers l'adresse configurée. Généralisation de
 * l'autodiagnostic d'un cabinet d'avocat ; le jeu de questions ci-dessous est un EXEMPLE
 * générique à remplacer par le questionnaire réel du projet (voir questions.sample.php).
 *
 * Préfixe de ce module : gws_diag_.
 */

if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/questions.sample.php';

function gws_diag_level($score) {
  if ($score >= 12) return array('urgent', 'Vigilance élevée');
  if ($score >= 6) return array('significant', 'Vigilance modérée');
  return array('moderate', 'Vigilance faible');
}

function gws_diag_handle_lead() {
  $redirect = home_url('/');
  if (!empty($_POST['gws_diag_redirect'])) $redirect = esc_url_raw(wp_unslash($_POST['gws_diag_redirect']));

  $check = gws_core_verify_form_security('gws_diag_submit');
  if ($check !== true) {
    $error = $check === 'honeypot' ? 'sent' : ($check === 'timing' ? 'invalid' : 'security');
    wp_safe_redirect(add_query_arg('diagnostic', $error, $redirect)); exit;
  }
  if (!gws_core_rate_limit_check('gws_diag')) {
    wp_safe_redirect(add_query_arg('diagnostic', 'limit', $redirect)); exit;
  }

  $name = isset($_POST['lead_name']) ? sanitize_text_field(wp_unslash($_POST['lead_name'])) : '';
  $email = isset($_POST['lead_email']) ? sanitize_email(wp_unslash($_POST['lead_email'])) : '';
  $phone = isset($_POST['lead_phone']) ? sanitize_text_field(wp_unslash($_POST['lead_phone'])) : '';
  if (!$name || !is_email($email)) {
    wp_safe_redirect(add_query_arg('diagnostic', 'missing', $redirect)); exit;
  }

  $posted_answers = isset($_POST['answers']) && is_array($_POST['answers']) ? wp_unslash($_POST['answers']) : array();
  $score = 0; $lines = array();
  foreach (gws_diag_questions() as $question) {
    $id = $question['id'];
    $answer = isset($posted_answers[$id]) ? sanitize_text_field($posted_answers[$id]) : '';
    if (!array_key_exists($answer, $question['choices'])) {
      wp_safe_redirect(add_query_arg('diagnostic', 'incomplete', $redirect)); exit;
    }
    $score += (int) $question['choices'][$answer];
    $lines[] = $question['question'] . "\nRéponse : " . $answer;
  }

  list(, $level_label) = gws_diag_level($score);
  $subject = '[Diagnostic] ' . $level_label . ' — ' . $name;
  $message = "Une personne a réalisé le diagnostic et demande à être recontactée.\n\n";
  $message .= "Niveau : " . $level_label . "\nScore : " . $score . "\n\n";
  $message .= "Nom : " . $name . "\nTéléphone : " . ($phone ?: 'Non précisé') . "\nE-mail : " . $email . "\n\n";
  $message .= "RÉPONSES\n\n" . implode("\n\n", $lines) . "\n";

  $public_email = gws_core_get_setting('public_email');
  $headers = array('Content-Type: text/plain; charset=UTF-8', 'Reply-To: ' . $name . ' <' . $email . '>');
  $sent = wp_mail($public_email ?: get_option('admin_email'), $subject, $message, $headers);

  wp_safe_redirect(add_query_arg('diagnostic', $sent ? 'sent' : 'mail-error', $redirect)); exit;
}
add_action('admin_post_nopriv_gws_diag_lead', 'gws_diag_handle_lead');
add_action('admin_post_gws_diag_lead', 'gws_diag_handle_lead');

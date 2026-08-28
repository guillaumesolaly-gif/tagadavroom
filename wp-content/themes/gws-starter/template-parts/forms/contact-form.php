<?php
/**
 * Exemple de formulaire de contact sécurisé. La structure HTML/CSS est la responsabilité du
 * thème ; le traitement de l'envoi (vérifications, e-mail) vit dans le plugin gws-core
 * (includes/contact-form.php) afin de continuer à fonctionner même si le thème change.
 */
$feedback = isset($_GET['contact']) ? sanitize_key(wp_unslash($_GET['contact'])) : '';
$messages = array(
  'sent' => 'Message envoyé, merci — nous revenons vers vous rapidement.',
  'missing' => 'Merci de renseigner tous les champs du formulaire.',
  'security' => 'La session a expiré, merci de renvoyer le formulaire.',
  'timing' => 'Merci de patienter quelques secondes avant d’envoyer le formulaire.',
  'limit' => 'Trop de tentatives, merci de réessayer plus tard.',
  'mail-error' => 'L’envoi a échoué, merci de nous contacter directement par e-mail.',
);
?>
<?php if ($feedback && isset($messages[$feedback])) : ?>
  <p class="form-feedback form-feedback-<?php echo esc_attr($feedback === 'sent' ? 'success' : 'error'); ?>"><?php echo esc_html($messages[$feedback]); ?></p>
<?php endif; ?>
<form class="form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
  <input type="hidden" name="action" value="gws_core_contact_form">
  <?php if (function_exists('gws_core_security_fields')) gws_core_security_fields('gws_core_contact_form'); ?>
  <p class="form-field">
    <label for="contact_name">Nom</label>
    <input type="text" id="contact_name" name="contact_name" required>
  </p>
  <p class="form-field">
    <label for="contact_email">E-mail</label>
    <input type="email" id="contact_email" name="contact_email" required>
  </p>
  <p class="form-field">
    <label for="contact_message">Message</label>
    <textarea id="contact_message" name="contact_message" rows="5" required></textarea>
  </p>
  <button class="btn btn-primary" type="submit">Envoyer <?php echo gws_icon('arrow_forward'); ?></button>
</form>

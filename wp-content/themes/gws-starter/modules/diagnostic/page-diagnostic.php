<?php
/**
 * Template Name: Diagnostic (module)
 *
 * Gabarit d'exemple pour le module Diagnostic. À copier vers page-templates/diagnostic.php à la
 * racine du thème pour qu'il apparaisse dans le sélecteur de gabarit de l'éditeur — voir
 * wp-content/themes/gws-starter/modules/README.md. Les questions affichées viennent du plugin
 * gws-core (modules/diagnostic/questions.sample.php) : les adapter là-bas, pas ici.
 *
 * Le style et le script restent référencés depuis ce dossier de module (pas besoin de les
 * copier) : seul ce fichier .php doit être déplacé pour activer le gabarit.
 */

add_action('wp_enqueue_scripts', function () {
  if (!is_page_template('page-templates/diagnostic.php')) return;
  wp_enqueue_style('gws-diagnostic', GWS_THEME_URI . '/modules/diagnostic/assets/diagnostic.css', array('gws-components'), GWS_THEME_VERSION);
  wp_enqueue_script('gws-diagnostic', GWS_THEME_URI . '/modules/diagnostic/assets/diagnostic.js', array(), GWS_THEME_VERSION, true);
});

get_header();
?>
<main id="contenu" tabindex="-1">
  <?php get_template_part('template-parts/site-header'); ?>
  <div class="container">
    <h1><?php the_title(); ?></h1>
    <?php
    $feedback = isset($_GET['diagnostic']) ? sanitize_key(wp_unslash($_GET['diagnostic'])) : '';
    $messages = array(
      'sent' => 'Merci, votre diagnostic a bien été envoyé.',
      'missing' => 'Merci de renseigner votre nom et un e-mail valide.',
      'incomplete' => 'Merci de répondre à toutes les questions.',
      'security' => 'La session a expiré, merci de recommencer.',
      'invalid' => 'Merci de patienter quelques secondes avant d’envoyer le formulaire.',
      'limit' => 'Trop de tentatives, merci de réessayer plus tard.',
      'mail-error' => 'L’envoi a échoué, merci de nous contacter directement.',
    );
    if ($feedback && isset($messages[$feedback])) {
      echo '<p class="form-feedback form-feedback-' . esc_attr($feedback === 'sent' ? 'success' : 'error') . '">' . esc_html($messages[$feedback]) . '</p>';
    }
    ?>
    <form class="form diagnostic-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
      <input type="hidden" name="action" value="gws_diag_lead">
      <input type="hidden" name="gws_diag_redirect" value="<?php echo esc_url(get_permalink()); ?>">
      <?php if (function_exists('gws_core_security_fields')) gws_core_security_fields('gws_diag_submit'); ?>

      <ol class="diagnostic-questions">
        <?php foreach ((function_exists('gws_diag_questions') ? gws_diag_questions() : array()) as $question) : ?>
          <li class="diagnostic-question">
            <p><?php echo esc_html($question['question']); ?></p>
            <?php foreach ($question['choices'] as $choice_label => $choice_score) : ?>
              <label class="diagnostic-choice">
                <input type="radio" name="answers[<?php echo esc_attr($question['id']); ?>]" value="<?php echo esc_attr($choice_label); ?>" required>
                <?php echo esc_html($choice_label); ?>
              </label>
            <?php endforeach; ?>
          </li>
        <?php endforeach; ?>
      </ol>

      <p class="form-field"><label for="lead_name">Nom</label><input type="text" id="lead_name" name="lead_name" required></p>
      <p class="form-field"><label for="lead_email">E-mail</label><input type="email" id="lead_email" name="lead_email" required></p>
      <p class="form-field"><label for="lead_phone">Téléphone (optionnel)</label><input type="tel" id="lead_phone" name="lead_phone"></p>

      <button class="btn btn-primary" type="submit">Recevoir mon diagnostic <?php echo gws_icon('arrow_forward'); ?></button>
    </form>
  </div>
  <?php get_template_part('template-parts/site-footer'); ?>
</main>
<?php get_footer(); ?>

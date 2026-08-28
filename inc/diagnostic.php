<?php
// Autodiagnostic : questions, score, traitement du formulaire (nonce, honeypot, rate limiting) et sa page dédiée.

function spa_diagnostic_questions() {
  return array(
    array('id' => 'activity', 'category' => 'Activité', 'question' => 'L’activité ou le chiffre d’affaires de l’entreprise se dégrade-t-il depuis plusieurs mois ?', 'choices' => array('Non' => 0, 'Légèrement' => 1, 'Nettement' => 2, 'Très fortement' => 3)),
    array('id' => 'cash', 'category' => 'Trésorerie', 'question' => 'La trésorerie disponible permet-elle de régler les dépenses courantes sans arbitrage permanent ?', 'choices' => array('Oui, sans difficulté' => 0, 'Avec quelques tensions' => 1, 'Difficilement' => 2, 'Non' => 3)),
    array('id' => 'suppliers', 'category' => 'Fournisseurs', 'question' => 'L’entreprise reporte-t-elle le paiement de certains fournisseurs ?', 'choices' => array('Jamais' => 0, 'Occasionnellement' => 1, 'Régulièrement' => 2, 'Très fréquemment' => 3)),
    array('id' => 'tax', 'category' => 'Dettes publiques', 'question' => 'Existe-t-il des retards concernant les cotisations sociales ou les échéances fiscales ?', 'choices' => array('Aucun retard' => 0, 'Un retard ponctuel' => 1, 'Plusieurs retards' => 2, 'Des échéances ne peuvent plus être réglées' => 3)),
    array('id' => 'bank', 'category' => 'Financement', 'question' => 'L’entreprise rencontre-t-elle des difficultés pour honorer ses échéances bancaires ou obtenir des financements ?', 'choices' => array('Non' => 0, 'Des discussions sont en cours' => 1, 'Des refus ou incidents ont eu lieu' => 2, 'Des échéances ne sont plus honorées' => 3)),
    array('id' => 'creditors', 'category' => 'Créanciers', 'question' => 'Des créanciers ont-ils engagé des relances insistantes, mises en demeure ou procédures ?', 'choices' => array('Non' => 0, 'Quelques relances' => 1, 'Des mises en demeure' => 2, 'Une ou plusieurs procédures' => 3)),
    array('id' => 'available_assets', 'category' => 'Paiements', 'question' => 'Avec les disponibilités immédiatement mobilisables, l’entreprise peut-elle régler ses dettes arrivées à échéance ?', 'choices' => array('Oui' => 0, 'Oui, mais avec difficulté' => 1, 'Je ne sais pas' => 2, 'Non' => 3)),
    array('id' => 'client', 'category' => 'Dépendance', 'question' => 'La perte d’un client, contrat ou partenaire important menace-t-elle l’équilibre de l’activité ?', 'choices' => array('Non' => 0, 'Risque limité' => 1, 'Risque significatif' => 2, 'La perte est déjà intervenue' => 3)),
    array('id' => 'management', 'category' => 'Gouvernance', 'question' => 'Des tensions entre associés ou dirigeants compliquent-elles les décisions nécessaires ?', 'choices' => array('Non' => 0, 'Des désaccords existent' => 1, 'Les décisions sont ralenties' => 2, 'La situation est bloquée' => 3)),
    array('id' => 'forecast', 'category' => 'Visibilité', 'question' => 'Disposez-vous d’une vision fiable de la trésorerie pour les trois prochains mois ?', 'choices' => array('Oui' => 0, 'Partiellement' => 1, 'Très peu' => 2, 'Aucune visibilité' => 3)),
    array('id' => 'actions', 'category' => 'Anticipation', 'question' => 'Des mesures ont-elles déjà été engagées avec l’expert-comptable, la banque ou les principaux créanciers ?', 'choices' => array('Oui, avec des résultats' => 0, 'Elles débutent' => 1, 'Elles sont insuffisantes' => 2, 'Aucune mesure n’a été engagée' => 3)),
    array('id' => 'urgency', 'category' => 'Urgence', 'question' => 'Une échéance importante ou une décision susceptible d’affecter la poursuite de l’activité est-elle imminente ?', 'choices' => array('Non' => 0, 'Dans plus d’un mois' => 1, 'Dans les prochaines semaines' => 2, 'Dans les prochains jours' => 3)),
  );
}

function spa_diagnostic_level($score, $critical = false) {
  if ($critical || $score >= 21) return array('urgent', 'Situation potentiellement urgente');
  if ($score >= 10) return array('significant', 'Tensions significatives');
  return array('moderate', 'Vigilance modérée');
}

function spa_handle_diagnostic_lead() {
  $redirect = home_url('/diagnostic-entreprise-en-difficulte/');
  if (!isset($_POST['spa_diagnostic_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['spa_diagnostic_nonce'])), 'spa_submit_diagnostic')) {
    wp_safe_redirect(add_query_arg('diagnostic', 'security', $redirect) . '#autodiagnostic'); exit;
  }
  if (!empty($_POST['website'])) {
    wp_safe_redirect(add_query_arg('diagnostic', 'sent', $redirect) . '#autodiagnostic'); exit;
  }
  $started = isset($_POST['diagnostic_started']) ? absint($_POST['diagnostic_started']) : 0;
  if (!$started || time() - $started < 8 || time() - $started > DAY_IN_SECONDS) {
    wp_safe_redirect(add_query_arg('diagnostic', 'invalid', $redirect) . '#autodiagnostic'); exit;
  }
  $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
  $rate_key = 'spa_diag_' . substr(hash_hmac('sha256', $ip, wp_salt('nonce')), 0, 24);
  $attempts = (int) get_transient($rate_key);
  if ($attempts >= 4) {
    wp_safe_redirect(add_query_arg('diagnostic', 'limit', $redirect) . '#autodiagnostic'); exit;
  }
  set_transient($rate_key, $attempts + 1, HOUR_IN_SECONDS);
  $name = isset($_POST['lead_name']) ? sanitize_text_field(wp_unslash($_POST['lead_name'])) : '';
  $company = isset($_POST['lead_company']) ? sanitize_text_field(wp_unslash($_POST['lead_company'])) : '';
  $role = isset($_POST['lead_role']) ? sanitize_text_field(wp_unslash($_POST['lead_role'])) : '';
  $phone = isset($_POST['lead_phone']) ? sanitize_text_field(wp_unslash($_POST['lead_phone'])) : '';
  $email = isset($_POST['lead_email']) ? sanitize_email(wp_unslash($_POST['lead_email'])) : '';
  $availability = isset($_POST['lead_availability']) ? sanitize_text_field(wp_unslash($_POST['lead_availability'])) : '';
  $requested = isset($_POST['lead_request']) && $_POST['lead_request'] === '1';
  if (!$name || !$company || !$phone || !is_email($email) || !$requested) {
    wp_safe_redirect(add_query_arg('diagnostic', 'missing', $redirect) . '#autodiagnostic'); exit;
  }
  $posted_answers = isset($_POST['answers']) && is_array($_POST['answers']) ? wp_unslash($_POST['answers']) : array();
  $score = 0; $critical = false; $lines = array();
  foreach (spa_diagnostic_questions() as $question) {
    $id = $question['id'];
    $answer = isset($posted_answers[$id]) ? sanitize_text_field($posted_answers[$id]) : '';
    if (!array_key_exists($answer, $question['choices'])) {
      wp_safe_redirect(add_query_arg('diagnostic', 'incomplete', $redirect) . '#autodiagnostic'); exit;
    }
    $score += (int) $question['choices'][$answer];
    if (($id === 'available_assets' && $answer === 'Non') || ($id === 'urgency' && $answer === 'Dans les prochains jours') || ($id === 'tax' && $answer === 'Des échéances ne peuvent plus être réglées')) $critical = true;
    $lines[] = $question['category'] . ' — ' . $question['question'] . "\nRéponse : " . $answer;
  }
  list($level_key, $level_label) = spa_diagnostic_level($score, $critical);
  $subject = '[Autodiagnostic] ' . $level_label . ' — ' . $company;
  $message = "Une personne demande à être recontactée après avoir réalisé l’autodiagnostic.\n\n";
  $message .= "Niveau : " . $level_label . "\nScore interne : " . $score . "/36\n\n";
  $message .= "Nom : " . $name . "\nEntreprise : " . $company . "\nFonction : " . ($role ?: 'Non précisée') . "\nTéléphone : " . $phone . "\nE-mail : " . $email . "\nDisponibilité : " . ($availability ?: 'Non précisée') . "\n\n";
  $message .= "RÉPONSES AU QUESTIONNAIRE\n\n" . implode("\n\n", $lines) . "\n\n";
  $message .= "La personne a demandé à être recontactée par le cabinet au sujet de la situation de son entreprise.\n";
  $message .= "\nConservation prévue en l’absence d’ouverture de dossier : trois mois.\n";
  $public_email = spa_get_cabinet_setting('public_email');
  $headers = array('Content-Type: text/plain; charset=UTF-8', 'From: Cabinet Saint-Père Avocat <' . $public_email . '>', 'Reply-To: ' . $name . ' <' . $email . '>');
  $sent = wp_mail(spa_get_cabinet_setting('diagnostic_email'), $subject, $message, $headers);
  wp_safe_redirect(add_query_arg('diagnostic', $sent ? 'sent' : 'mail-error', $redirect) . '#autodiagnostic'); exit;
}
add_action('admin_post_nopriv_spa_diagnostic_lead', 'spa_handle_diagnostic_lead');
add_action('admin_post_spa_diagnostic_lead', 'spa_handle_diagnostic_lead');

function spa_seed_diagnostic_page() {
  if (get_option('spa_diagnostic_page_version') === '1.0.0') return;
  $page = get_page_by_path('diagnostic-entreprise-en-difficulte');
  if (!$page) {
    $page_id = wp_insert_post(array(
      'post_title' => 'Autodiagnostic des difficultés de l’entreprise',
      'post_name' => 'diagnostic-entreprise-en-difficulte',
      'post_type' => 'page',
      'post_status' => 'publish',
    ));
    if (!is_wp_error($page_id)) $page = get_post($page_id);
  }
  if ($page && !spa_has_seo_plugin()) {
    if (!get_post_meta($page->ID, '_spa_seo_title', true)) update_post_meta($page->ID, '_spa_seo_title', 'Autodiagnostic entreprise en difficulté | Saint-Père Avocat');
    if (!get_post_meta($page->ID, '_spa_seo_description', true)) update_post_meta($page->ID, '_spa_seo_description', 'Évaluez les signaux de difficulté de votre entreprise et identifiez le niveau de vigilance avant d’échanger avec le cabinet Saint-Père Avocat.');
  }
  update_option('spa_diagnostic_page_version', '1.0.0', false);
}
add_action('init', 'spa_seed_diagnostic_page', 26);

// Le slug de cette page a été volontairement changé de autodiagnostic-entreprise-difficulte
// vers diagnostic-entreprise-en-difficulte (SEO). Redirection 301 permanente pour préserver le
// référencement et les liens externes déjà en circulation vers l'ancienne URL. Se déclenche
// uniquement quand l'ancien chemin exact ne correspond plus à aucun contenu (404), donc sans
// effet si une page existe un jour de nouveau à cette adresse.
function spa_redirect_old_diagnostic_slug() {
  if (!is_404()) return;
  $path = isset($_SERVER['REQUEST_URI']) ? trim((string) parse_url(wp_unslash($_SERVER['REQUEST_URI']), PHP_URL_PATH), '/') : '';
  if ($path === 'autodiagnostic-entreprise-difficulte') {
    wp_safe_redirect(home_url('/diagnostic-entreprise-en-difficulte/'), 301);
    exit;
  }
}
add_action('template_redirect', 'spa_redirect_old_diagnostic_slug');

// Ancienne spa_add_diagnostic_privacy_information() : appendait sur `init` un paragraphe à la
// page politique-de-confidentialite dès que le marqueur en était absent — vrai par défaut sur
// n'importe quelle page, y compris une page étrangère (même défaut que l'incident de production
// du 2.3.1, voir spa-technical-notes.md). Le paragraphe fait désormais partie du seed
// (seed/politique-de-confidentialite.blocks-v2.html) : il n'existe plus qu'une seule source de
// vérité pour le contenu de cette page, appliquée uniquement via l'outil de migration explicite
// (inc/migration-tool.php).


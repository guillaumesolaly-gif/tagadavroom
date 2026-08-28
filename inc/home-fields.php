<?php
// Système de champs éditables de la page d'accueil (meta box "Contenu de la homepage"). spa_apply_home_fields() reste un remplacement ciblé sur le HTML propre à front-page.php, pas une transformation globale : voir la note dans front-page.php.

function spa_home_fields() {
  return array(
    'En-tête principal' => array(
      'hero_kicker' => array('label' => 'Petit titre', 'search' => 'Avocat au Barreau de Saint-Étienne'),
      'hero_title_1' => array('label' => 'Titre — première ligne', 'search' => 'Avocat en droit des entreprises'),
      'hero_title_2' => array('label' => 'Titre — seconde ligne', 'search' => '<em>en difficulté</em>', 'wrap' => 'em'),
      'hero_intro' => array('label' => 'Texte d’introduction', 'search' => 'Implantée à Saint-Étienne, Maître Juliette Saint-Père conseille et défend les entreprises, leurs dirigeants et les investisseurs confrontés à des difficultés financières, de la prévention aux procédures collectives.', 'textarea' => true),
      'hero_cartouche' => array('label' => 'Promesse sur la photo', 'search' => 'Juridique, stratégique<br/>et humaine', 'default' => "Juridique, stratégique\net humaine", 'multiline' => true),
    ),
    'Domaines d’intervention' => array(
      'expertises_title' => array('label' => 'Titre de section', 'search' => 'Accompagner les décisions<br/>qui engagent l’avenir.', 'default' => "Accompagner les décisions\nqui engagent l’avenir.", 'multiline' => true),
      'expertises_intro' => array('label' => 'Introduction', 'search' => 'Chaque situation appelle une analyse précise, une stratégie compréhensible et une action au bon moment.', 'textarea' => true),
      'expertise_1_title' => array('label' => 'Expertise 1 — titre', 'search' => '<h3>Prévention des difficultés</h3>', 'wrap' => 'h3'),
      'expertise_1_text' => array('label' => 'Expertise 1 — description', 'search' => 'Mandat ad hoc, conciliation et négociation confidentielle avec les créanciers pour traiter les tensions de trésorerie avant la cessation des paiements.', 'textarea' => true),
      'expertise_2_title' => array('label' => 'Expertise 2 — titre', 'search' => '<h3>Sauvegarde &amp; redressement</h3>', 'default' => 'Sauvegarde & redressement', 'wrap' => 'h3'),
      'expertise_2_text' => array('label' => 'Expertise 2 — description', 'search' => 'Préparation, ouverture et suivi de la procédure collective afin de restructurer la dette, protéger l’activité et construire un plan durable.', 'textarea' => true),
      'expertise_3_title' => array('label' => 'Expertise 3 — titre', 'search' => '<h3>Liquidation judiciaire</h3>', 'wrap' => 'h3'),
      'expertise_3_text' => array('label' => 'Expertise 3 — description', 'search' => 'Assistance lors du dépôt de bilan, accompagnement pendant la liquidation et défense du dirigeant face aux actions en responsabilité ou en sanction.', 'textarea' => true),
      'expertise_4_title' => array('label' => 'Expertise 4 — titre', 'search' => '<h3>Contentieux commercial</h3>', 'wrap' => 'h3'),
      'expertise_4_text' => array('label' => 'Expertise 4 — description', 'search' => 'Recouvrement de créances, conflits contractuels et représentation devant le Tribunal judiciaire et le Tribunal de commerce de Saint-Étienne.', 'textarea' => true),
    ),
    'Postulation et urgence' => array(
      'postulation_title' => array('label' => 'Postulation — titre', 'search' => 'Un relais fiable devant les juridictions stéphanoises.'),
      'postulation_text_1' => array('label' => 'Postulation — premier texte', 'search' => 'Le cabinet assure les missions de postulation devant le Tribunal judiciaire et le Tribunal de commerce de Saint-Étienne pour le compte de confrères extérieurs.', 'textarea' => true),
      'postulation_text_2' => array('label' => 'Postulation — second texte', 'search' => 'Réactivité, communication fluide et suivi rigoureux permettent de sécuriser chaque étape de la procédure.', 'textarea' => true),
      'alert_title' => array('label' => 'Urgence — titre', 'search' => 'Votre entreprise rencontre<br/>des difficultés financières ?', 'default' => "Votre entreprise rencontre\ndes difficultés financières ?", 'multiline' => true),
      'alert_text' => array('label' => 'Urgence — texte', 'search' => 'Plus la situation est examinée tôt, plus les solutions sont nombreuses. Un premier échange permet d’identifier les priorités et les options adaptées.', 'textarea' => true),
      'alert_aside_title' => array('label' => 'Encadré urgence — titre', 'search' => 'Ne restez pas seul face à l’urgence.'),
      'alert_aside_text' => array('label' => 'Encadré urgence — texte', 'search' => 'Le cabinet vous aide à retrouver une vision claire de la situation et à préparer les prochaines étapes.', 'textarea' => true),
    ),
    'Guides et contenus SEO' => array(
      'guide_title' => array('label' => 'Titre de section', 'search' => 'Comprendre la situation<br/>pour agir à temps.', 'default' => "Comprendre la situation\npour agir à temps.", 'multiline' => true),
      'guide_intro' => array('label' => 'Introduction', 'search' => 'Les premières décisions conditionnent souvent la suite. Ces repères permettent d’identifier le cadre juridique pertinent avant d’engager une démarche.', 'textarea' => true),
      'guide_1_title' => array('label' => 'Guide 1 — titre', 'search' => '<h3>Que faire face aux premières difficultés financières ?</h3>', 'default' => 'Que faire face aux premières difficultés financières ?', 'wrap' => 'h3'),
      'guide_1_text' => array('label' => 'Guide 1 — description', 'search' => 'Une baisse de trésorerie, des échéances fiscales ou sociales difficiles à honorer et des retards fournisseurs doivent conduire à examiner rapidement les solutions amiables disponibles.', 'textarea' => true),
      'guide_1_url' => array('label' => 'Guide 1 — adresse de la page', 'search' => '/entreprise-en-difficulte-que-faire-saint-etienne/', 'url' => true),
      'guide_2_title' => array('label' => 'Guide 2 — titre', 'search' => '<h3>Comment reconnaître la cessation des paiements ?</h3>', 'default' => 'Comment reconnaître la cessation des paiements ?', 'wrap' => 'h3'),
      'guide_2_text' => array('label' => 'Guide 2 — description', 'search' => 'Une entreprise est en cessation des paiements lorsqu’elle ne peut plus faire face à son passif exigible avec son actif disponible. La date retenue produit des conséquences juridiques importantes.', 'textarea' => true),
      'guide_2_url' => array('label' => 'Guide 2 — adresse de la page', 'search' => '/cessation-de-paiement-que-faire-dirigeant-saint-etienne/', 'url' => true),
      'guide_3_title' => array('label' => 'Guide 3 — titre', 'search' => '<h3>Peut-on éviter une liquidation judiciaire ?</h3>', 'default' => 'Peut-on éviter une liquidation judiciaire ?', 'wrap' => 'h3'),
      'guide_3_text' => array('label' => 'Guide 3 — description', 'search' => 'La prévention, le mandat ad hoc, la conciliation, la sauvegarde ou le redressement peuvent permettre de traiter les difficultés avant qu’une liquidation ne devienne inévitable.', 'textarea' => true),
      'guide_3_url' => array('label' => 'Guide 3 — adresse de la page', 'search' => '/eviter-liquidation-judiciaire-entreprise-saint-etienne/', 'url' => true),
      'guide_4_title' => array('label' => 'Guide 4 — titre', 'search' => '<h3>Comment reprendre une entreprise en difficulté ?</h3>', 'default' => 'Comment reprendre une entreprise en difficulté ?', 'wrap' => 'h3'),
      'guide_4_text' => array('label' => 'Guide 4 — description', 'search' => 'Une offre de reprise dans le cadre d’un plan de cession doit être structurée, financée et déposée dans un calendrier contraint. Le cabinet accompagne dirigeants et investisseurs dans ce projet.', 'textarea' => true),
      'guide_4_url' => array('label' => 'Guide 4 — adresse de la page', 'search' => '/reprise-entreprise-difficulte-investisseur/', 'url' => true),
      'faq_title' => array('label' => 'Encadré FAQ — titre', 'search' => 'Une question avant de contacter le cabinet ?', 'default' => 'Une question avant de contacter le cabinet ?'),
      'faq_text' => array('label' => 'Encadré FAQ — texte', 'search' => 'Retrouvez des réponses claires aux interrogations les plus fréquentes des dirigeants.', 'textarea' => true),
      'faq_url' => array('label' => 'FAQ — adresse de la page', 'search' => '/faq-avocat-droit-entreprises-saint-etienne/', 'url' => true),
    ),
    'Présentation du cabinet' => array(
      'cabinet_title' => array('label' => 'Titre', 'search' => 'Une expertise juridique<br/>au service de l’entreprise.', 'default' => "Une expertise juridique\nau service de l’entreprise.", 'multiline' => true),
      'cabinet_intro' => array('label' => 'Introduction', 'search' => 'Inscrite au Barreau de Saint-Étienne, Maître Saint-Père intervient principalement en droit des entreprises en difficulté.', 'textarea' => true),
      'cabinet_text_1' => array('label' => 'Paragraphe 1', 'search' => 'Diplômée d’un Master II en Droit des Affaires (DJCE) de l’Université Jean Moulin Lyon III, d’un LLM de la National University of Ireland Maynooth et d’un Certificat en Finance d’Entreprise délivré par HEC Paris, elle associe maîtrise du droit et compréhension des enjeux économiques depuis sa prestation de serment en décembre 2018.', 'textarea' => true),
      'cabinet_text_2' => array('label' => 'Paragraphe 2', 'search' => 'Elle accompagne également les confrères extérieurs dans leurs missions de postulation devant les juridictions stéphanoises.', 'textarea' => true),
      'cabinet_text_3' => array('label' => 'Paragraphe 3', 'search' => 'Elle est bénévole au sein de l’association 60 000 Rebonds, qui accompagne les entrepreneurs vers un nouveau projet après une liquidation judiciaire.', 'textarea' => true),
    ),
    'Engagements' => array(
      'engagements_title' => array('label' => 'Titre de section', 'search' => 'Une relation fondée<br/>sur la confiance.', 'default' => "Une relation fondée\nsur la confiance.", 'multiline' => true),
      'engagement_1_title' => array('label' => 'Engagement 1 — titre', 'search' => '<h3>Confidentialité</h3>', 'wrap' => 'h3'),
      'engagement_1_text' => array('label' => 'Engagement 1 — texte', 'search' => 'Vos échanges et les informations confiées au cabinet sont protégés.', 'textarea' => true),
      'engagement_2_title' => array('label' => 'Engagement 2 — titre', 'search' => '<h3>Accompagnement personnalisé</h3>', 'wrap' => 'h3'),
      'engagement_2_text' => array('label' => 'Engagement 2 — texte', 'search' => 'Une interlocutrice unique et une stratégie adaptée à votre situation.', 'textarea' => true),
      'engagement_3_title' => array('label' => 'Engagement 3 — titre', 'search' => '<h3>Honoraires transparents</h3>', 'wrap' => 'h3'),
      'engagement_3_text' => array('label' => 'Engagement 3 — texte', 'search' => 'Le cadre et le coût de l’intervention sont définis dès l’ouverture du dossier.', 'textarea' => true),
    ),
    'Services en ligne et contact' => array(
      'online_title' => array('label' => 'Services en ligne — titre', 'search' => 'Échanger avec<br/>le cabinet.', 'default' => "Échanger avec\nle cabinet.", 'multiline' => true),
      'online_text' => array('label' => 'Services en ligne — texte', 'search' => 'La plateforme officielle Avocat.fr permet d’adresser une question juridique, de demander un rendez-vous au cabinet ou de solliciter une visioconférence.', 'textarea' => true),
      'online_note' => array('label' => 'Services en ligne — précision', 'search' => 'Pour une demande de rendez-vous ou de visioconférence, vous proposez vos disponibilités. Le créneau n’est définitif qu’après confirmation par le cabinet.', 'textarea' => true),
      'contact_title' => array('label' => 'Contact — titre', 'search' => 'Échangeons sur<br/>votre situation.', 'default' => "Échangeons sur\nvotre situation.", 'multiline' => true),
      'contact_text' => array('label' => 'Contact — texte', 'search' => 'Le cabinet reviendra vers vous dans les meilleurs délais.', 'textarea' => true),
    ),
    'Liens principaux' => array(
      'expertise_1_url' => array('label' => 'Prévention — adresse de la page', 'search' => '/prevention-difficultes-entreprise-saint-etienne/', 'url' => true),
      'expertise_2_url' => array('label' => 'Sauvegarde et redressement — adresse de la page', 'search' => '/sauvegarde-et-redressement-judiciaire/', 'url' => true),
      'expertise_3_url' => array('label' => 'Liquidation — adresse de la page', 'search' => '/liquidation-judiciaire-saint-etienne/', 'url' => true),
      'expertise_4_url' => array('label' => 'Contentieux — adresse de la page', 'search' => '/contentieux-civil-commercial-saint-etienne/', 'url' => true),
      'postulation_url' => array('label' => 'Postulation — adresse de la page', 'search' => '/avocat-postulation-saint-etienne/', 'url' => true),
      'profile_url' => array('label' => 'Présentation du cabinet — adresse de la page', 'search' => '/trouver-avocat-droit-entreprises-saint-etienne/', 'url' => true),
      'solutions_url' => array('label' => 'Solutions — adresse de la page', 'search' => '/des-solutions-en-cas-de-difficultes-financieres/', 'url' => true),
      'linkedin_url' => array('label' => 'Profil LinkedIn', 'search' => 'https://www.linkedin.com/in/juliette-saint-p%C3%A8re-a132a985', 'url' => true),
      'avocatfr_url' => array('label' => 'Profil Avocat.fr', 'search' => 'https://consultation.avocat.fr/avocat-44825-96e8.html', 'url' => true),
    ),
  );
}

function spa_render_home_content_box($post) {
  if ((int) $post->ID !== (int) get_option('page_on_front')) { echo '<p>Ces réglages sont réservés à la page d’accueil.</p>'; return; }
  wp_nonce_field('spa_save_home_content', 'spa_home_content_nonce');
  foreach (spa_home_fields() as $section => $fields) {
    echo '<details style="margin:12px 0;border:1px solid #c3c4c7;background:#fff"><summary style="padding:14px;cursor:pointer;font-weight:600">' . esc_html($section) . '</summary><div style="padding:4px 14px 14px">';
    foreach ($fields as $key => $field) {
      $default = isset($field['default']) ? $field['default'] : html_entity_decode(wp_strip_all_tags($field['search']), ENT_QUOTES, 'UTF-8');
      $value = spa_page_value($post->ID, '_spa_home_' . $key, $default);
      echo '<p><label><strong>' . esc_html($field['label']) . '</strong></label>';
      if (!empty($field['textarea']) || !empty($field['multiline'])) echo '<textarea class="widefat" rows="' . (!empty($field['multiline']) ? '2' : '3') . '" name="spa_home_' . esc_attr($key) . '">' . esc_textarea($value) . '</textarea>';
      else echo '<input class="widefat" type="' . (!empty($field['url']) ? 'url' : 'text') . '" name="spa_home_' . esc_attr($key) . '" value="' . esc_attr($value) . '">';
      echo '</p>';
    }
    echo '</div></details>';
  }
}

function spa_save_home_content($post_id) {
  if (!isset($_POST['spa_home_content_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['spa_home_content_nonce'])), 'spa_save_home_content')) return;
  if ((int) $post_id !== (int) get_option('page_on_front') || !current_user_can('edit_post', $post_id)) return;
  foreach (spa_home_fields() as $fields) foreach ($fields as $key => $field) {
    $name = 'spa_home_' . $key;
    $value = isset($_POST[$name]) ? (!empty($field['url']) ? esc_url_raw(wp_unslash($_POST[$name])) : sanitize_textarea_field(wp_unslash($_POST[$name]))) : '';
    update_post_meta($post_id, '_spa_home_' . $key, $value);
  }
}
add_action('save_post_page', 'spa_save_home_content');

function spa_apply_home_fields($html) {
  $page_id = (int) get_option('page_on_front');
  foreach (spa_home_fields() as $fields) foreach ($fields as $key => $field) {
    $default = isset($field['default']) ? $field['default'] : html_entity_decode(wp_strip_all_tags($field['search']), ENT_QUOTES, 'UTF-8');
    $value = spa_page_value($page_id, '_spa_home_' . $key, $default);
    $replacement = !empty($field['url']) ? esc_url($value) : (!empty($field['multiline']) ? nl2br(esc_html($value)) : esc_html($value));
    if (!empty($field['wrap'])) $replacement = '<' . $field['wrap'] . '>' . $replacement . '</' . $field['wrap'] . '>';
    $html = str_replace($field['search'], $replacement, $html);
  }
  return $html;
}


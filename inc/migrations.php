<?php
// Migrations ponctuelles versionnées (liens internes absolus -> relatifs, nettoyage de la page cookies). Chaque fonction est verrouillée par un numéro de version en option ; ne pas rejouer sans relire spa-technical-notes.md.
// Ne sont plus déclenchées automatiquement sur `init` (cf. incident de production 2.3.1) : appelées explicitement par spa_run_migration_batch() dans inc/migration-tool.php, quand l'administrateur lance la migration depuis Outils > Migration de contenu. Restent version-gated, donc sans effet si déjà appliquées.

function spa_migrate_internal_links() {
  if (get_option('spa_internal_links_version') === '1.2.1') return;
  $absolute = 'https://saint-pere-avocat.fr/avocat-postulation-saint-etienne/';
  $relative = '/avocat-postulation-saint-etienne/';
  $home = get_page_by_path('accueil');
  if ($home && get_post_meta($home->ID, '_spa_home_postulation_url', true) === $absolute) {
    update_post_meta($home->ID, '_spa_home_postulation_url', $relative);
  }
  $contentieux = get_page_by_path('contentieux-civil-commercial-saint-etienne');
  if ($contentieux && get_post_meta($contentieux->ID, '_spa_related_url_0', true) === $absolute) {
    update_post_meta($contentieux->ID, '_spa_related_url_0', $relative);
  }
  $cessation_absolute = 'https://saint-pere-avocat.fr/cessation-de-paiement-que-faire-dirigeant-saint-etienne/';
  $cessation_relative = '/cessation-de-paiement-que-faire-dirigeant-saint-etienne/';
  if ($home && get_post_meta($home->ID, '_spa_home_guide_2_url', true) === $cessation_absolute) {
    update_post_meta($home->ID, '_spa_home_guide_2_url', $cessation_relative);
  }
  foreach (array('prevention-difficultes-entreprise-saint-etienne', 'sauvegarde-et-redressement-judiciaire', 'liquidation-judiciaire-saint-etienne') as $linked_slug) {
    $linked_page = get_page_by_path($linked_slug);
    if ($linked_page && strpos($linked_page->post_content, $cessation_absolute) !== false) {
      wp_update_post(array('ID' => $linked_page->ID, 'post_content' => wp_slash(str_replace($cessation_absolute, $cessation_relative, $linked_page->post_content))));
    }
  }
  $solutions_absolute = 'https://saint-pere-avocat.fr/des-solutions-en-cas-de-difficultes-financieres/';
  if ($home && get_post_meta($home->ID, '_spa_home_solutions_url', true) === $solutions_absolute) {
    update_post_meta($home->ID, '_spa_home_solutions_url', '/des-solutions-en-cas-de-difficultes-financieres/');
  }
  $solutions_page = get_page_by_path('des-solutions-en-cas-de-difficultes-financieres');
  if ($solutions_page && get_post_meta($solutions_page->ID, '_spa_video_url', true) === 'https://www.dailymotion.com/video/xa1lg12') {
    update_post_meta($solutions_page->ID, '_spa_video_url', 'https://youtu.be/PXIOlkuHrHk');
  }
  $difficulty_absolute = 'https://saint-pere-avocat.fr/entreprise-en-difficulte-que-faire-saint-etienne/';
  $difficulty_relative = '/entreprise-en-difficulte-que-faire-saint-etienne/';
  if ($home && get_post_meta($home->ID, '_spa_home_guide_1_url', true) === $difficulty_absolute) {
    update_post_meta($home->ID, '_spa_home_guide_1_url', $difficulty_relative);
  }
  foreach (get_pages() as $internal_page) {
    for ($related_index = 0; $related_index < 3; $related_index++) {
      $related_key = '_spa_related_url_' . $related_index;
      if (get_post_meta($internal_page->ID, $related_key, true) === $difficulty_absolute) update_post_meta($internal_page->ID, $related_key, $difficulty_relative);
    }
  }
  $avoid_absolute = 'https://saint-pere-avocat.fr/eviter-liquidation-judiciaire-entreprise-saint-etienne/';
  $avoid_relative = '/eviter-liquidation-judiciaire-entreprise-saint-etienne/';
  if ($home && get_post_meta($home->ID, '_spa_home_guide_3_url', true) === $avoid_absolute) {
    update_post_meta($home->ID, '_spa_home_guide_3_url', $avoid_relative);
  }
  foreach (get_pages() as $internal_page) {
    for ($related_index = 0; $related_index < 3; $related_index++) {
      $related_key = '_spa_related_url_' . $related_index;
      if (get_post_meta($internal_page->ID, $related_key, true) === $avoid_absolute) update_post_meta($internal_page->ID, $related_key, $avoid_relative);
    }
  }
  $faq_old = '<!-- wp:heading {"level":3} --><h3>Questions fréquentes</h3><!-- /wp:heading -->';
  $faq_new = '<!-- wp:heading {"level":2,"className":"faq-section-title"} --><h2 class="wp-block-heading faq-section-title">Questions fréquentes</h2><!-- /wp:heading -->';
  foreach (array('cessation-de-paiement-que-faire-dirigeant-saint-etienne', 'entreprise-en-difficulte-que-faire-saint-etienne', 'eviter-liquidation-judiciaire-entreprise-saint-etienne') as $faq_slug) {
    $faq_page = get_page_by_path($faq_slug);
    if (!$faq_page) continue;
    $updated_content = str_replace($faq_old, $faq_new, $faq_page->post_content);
    $updated_content = str_replace('Voir les solutions présentées dans l’interview TL7', 'Voir les solutions présentées', $updated_content);
    if ($updated_content !== $faq_page->post_content) wp_update_post(array('ID' => $faq_page->ID, 'post_content' => wp_slash($updated_content)));
  }
  $difficulty_page = get_page_by_path('entreprise-en-difficulte-que-faire-saint-etienne');
  if ($difficulty_page && get_post_meta($difficulty_page->ID, '_spa_related_label_2', true) === 'Découvrir les solutions et l’interview TL7') {
    update_post_meta($difficulty_page->ID, '_spa_related_label_2', 'Voir les solutions présentées');
  }
  $takeover_absolute = 'https://saint-pere-avocat.fr/reprise-entreprise-difficulte-investisseur/';
  $takeover_relative = '/reprise-entreprise-difficulte-investisseur/';
  if ($home && get_post_meta($home->ID, '_spa_home_guide_4_url', true) === $takeover_absolute) {
    update_post_meta($home->ID, '_spa_home_guide_4_url', $takeover_relative);
  }
  foreach (get_pages() as $internal_page) {
    $updated_takeover_content = str_replace($takeover_absolute, $takeover_relative, $internal_page->post_content);
    if ($updated_takeover_content !== $internal_page->post_content) wp_update_post(array('ID' => $internal_page->ID, 'post_content' => wp_slash($updated_takeover_content)));
    for ($related_index = 0; $related_index < 3; $related_index++) {
      $related_key = '_spa_related_url_' . $related_index;
      if (get_post_meta($internal_page->ID, $related_key, true) === $takeover_absolute) update_post_meta($internal_page->ID, $related_key, $takeover_relative);
    }
  }
  $faq_absolute = 'https://saint-pere-avocat.fr/faq-avocat-droit-entreprises-saint-etienne/';
  $faq_relative = '/faq-avocat-droit-entreprises-saint-etienne/';
  if ($home && get_post_meta($home->ID, '_spa_home_faq_url', true) === $faq_absolute) update_post_meta($home->ID, '_spa_home_faq_url', $faq_relative);
  foreach (get_pages() as $internal_page) {
    for ($related_index = 0; $related_index < 3; $related_index++) {
      $related_key = '_spa_related_url_' . $related_index;
      if (get_post_meta($internal_page->ID, $related_key, true) === $faq_absolute) update_post_meta($internal_page->ID, $related_key, $faq_relative);
    }
  }
  $profile_absolute = 'https://saint-pere-avocat.fr/trouver-avocat-droit-entreprises-saint-etienne/';
  $profile_relative = '/trouver-avocat-droit-entreprises-saint-etienne/';
  if ($home && get_post_meta($home->ID, '_spa_home_profile_url', true) === $profile_absolute) update_post_meta($home->ID, '_spa_home_profile_url', $profile_relative);
  foreach (get_pages() as $internal_page) {
    for ($related_index = 0; $related_index < 3; $related_index++) {
      $related_key = '_spa_related_url_' . $related_index;
      if (get_post_meta($internal_page->ID, $related_key, true) === $profile_absolute) update_post_meta($internal_page->ID, $related_key, $profile_relative);
    }
  }
  update_option('spa_internal_links_version', '1.2.1', false);
}

function spa_remove_inactive_cookie_preferences() {
  if (get_option('spa_cookie_page_cleanup_version') === '1.0.0') return;
  $page = get_page_by_path('gestion-de-cookies');
  if ($page) {
    $old_intro = '<!-- wp:paragraph {"className":"article-intro"} --><p class="article-intro">Vous pouvez accepter, refuser ou modifier vos choix concernant les technologies non nécessaires au fonctionnement du site.</p><!-- /wp:paragraph -->';
    $new_intro = '<!-- wp:paragraph {"className":"article-intro"} --><p class="article-intro">Cette page présente les technologies susceptibles d’être utilisées lors de votre navigation sur le site.</p><!-- /wp:paragraph -->';
    $inactive_section = '<!-- wp:heading --><h2 class="wp-block-heading">Modifier vos préférences</h2><!-- /wp:heading -->' . "\n" . '<!-- wp:paragraph --><p>Vous pouvez modifier votre choix à tout moment. Le refus des cookies non nécessaires n’empêche pas l’accès aux contenus principaux du site, mais certains services externes peuvent rester indisponibles tant que la catégorie correspondante n’est pas autorisée.</p><!-- /wp:paragraph -->' . "\n" . '<!-- wp:html --><div class="cookie-preferences"><button type="button" class="cmplz-show-banner">Modifier mes préférences</button></div><!-- /wp:html -->' . "\n";
    $updated = str_replace(array($old_intro, $inactive_section), array($new_intro, ''), $page->post_content);
    if ($updated !== $page->post_content) wp_update_post(array('ID' => $page->ID, 'post_content' => wp_slash($updated)));
  }
  $privacy_page = get_page_by_path('politique-de-confidentialite');
  if ($privacy_page) {
    $privacy_content = str_replace(
      array('Modifier vos préférences de consentement', 'Accéder à la gestion des cookies →'),
      array('Comprendre l’utilisation des cookies', 'Consulter les informations sur les cookies →'),
      $privacy_page->post_content
    );
    if ($privacy_content !== $privacy_page->post_content) wp_update_post(array('ID' => $privacy_page->ID, 'post_content' => wp_slash($privacy_content)));
  }
  update_option('spa_cookie_page_cleanup_version', '1.0.0', false);
}


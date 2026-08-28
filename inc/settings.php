<?php
// Réglages centralisés du cabinet (spa_get_cabinet_setting) — la seule source de vérité pour téléphone, e-mail, adresse et liens externes.

function spa_cabinet_defaults() {
  return array(
    'phone_display' => '04 77 41 76 15',
    'public_email' => 'contact@saint-pere-avocat.fr',
    'diagnostic_email' => 'juliette@saint-pere-avocat.fr',
    'address_line' => '29 rue de la Résistance',
    'postal_code' => '42000',
    'city' => 'Saint-Étienne',
    'linkedin_url' => 'https://www.linkedin.com/in/juliette-saint-p%C3%A8re-a132a985',
    'avocat_url' => 'https://consultation.avocat.fr/avocat-44825-96e8.html',
    'avocat_widget_id' => 'bb094b12fabbcb8818bb',
    'seneque_url' => 'https://www.reseauavocatsseneque.fr/',
    'google_business_url' => 'https://maps.app.goo.gl/7UCNsS3xbDGab5Sz9',
  );
}

function spa_cabinet_settings() {
  return wp_parse_args((array) get_option('spa_cabinet_settings', array()), spa_cabinet_defaults());
}

function spa_get_cabinet_setting($key) {
  $settings = spa_cabinet_settings();
  return isset($settings[$key]) ? $settings[$key] : '';
}

function spa_cabinet_phone_href() {
  $digits = preg_replace('/\D+/', '', spa_get_cabinet_setting('phone_display'));
  if (strpos($digits, '00') === 0) return '+' . substr($digits, 2);
  if (strpos($digits, '0') === 0) return '+33' . substr($digits, 1);
  return $digits ? '+' . $digits : '';
}

function spa_sanitize_cabinet_settings($input) {
  $defaults = spa_cabinet_defaults();
  $input = is_array($input) ? $input : array();
  $clean = array();
  $clean['phone_display'] = sanitize_text_field($input['phone_display'] ?? $defaults['phone_display']);
  $clean['public_email'] = sanitize_email($input['public_email'] ?? $defaults['public_email']);
  $clean['diagnostic_email'] = sanitize_email($input['diagnostic_email'] ?? $defaults['diagnostic_email']);
  $clean['address_line'] = sanitize_text_field($input['address_line'] ?? $defaults['address_line']);
  $clean['postal_code'] = sanitize_text_field($input['postal_code'] ?? $defaults['postal_code']);
  $clean['city'] = sanitize_text_field($input['city'] ?? $defaults['city']);
  $clean['linkedin_url'] = esc_url_raw($input['linkedin_url'] ?? $defaults['linkedin_url']);
  $clean['avocat_url'] = esc_url_raw($input['avocat_url'] ?? $defaults['avocat_url']);
  $clean['avocat_widget_id'] = sanitize_key($input['avocat_widget_id'] ?? $defaults['avocat_widget_id']);
  $clean['seneque_url'] = esc_url_raw($input['seneque_url'] ?? $defaults['seneque_url']);
  $clean['google_business_url'] = esc_url_raw($input['google_business_url'] ?? $defaults['google_business_url']);
  foreach (array('public_email', 'diagnostic_email') as $email_key) {
    if (!$clean[$email_key]) $clean[$email_key] = $defaults[$email_key];
  }
  foreach (array('linkedin_url', 'avocat_url', 'seneque_url', 'google_business_url') as $url_key) {
    if (!$clean[$url_key]) $clean[$url_key] = $defaults[$url_key];
  }
  return $clean;
}

function spa_register_cabinet_settings() {
  register_setting('spa_cabinet_settings_group', 'spa_cabinet_settings', array(
    'type' => 'array',
    'sanitize_callback' => 'spa_sanitize_cabinet_settings',
    'default' => spa_cabinet_defaults(),
  ));
}
add_action('admin_init', 'spa_register_cabinet_settings');

function spa_add_cabinet_settings_page() {
  add_menu_page('Réglages du cabinet', 'Cabinet', 'manage_options', 'spa-cabinet-settings', 'spa_render_cabinet_settings_page', 'dashicons-building', 58);
}
add_action('admin_menu', 'spa_add_cabinet_settings_page');

function spa_render_cabinet_settings_page() {
  if (!current_user_can('manage_options')) return;
  $settings = spa_cabinet_settings();
  $fields = array(
    'phone_display' => array('Téléphone', 'text', 'Format affiché sur le site. Le lien d’appel mobile est généré automatiquement.'),
    'public_email' => array('E-mail public', 'email', 'Adresse utilisée par les boutons « Écrire au cabinet » et les données structurées.'),
    'diagnostic_email' => array('Destinataire des diagnostics', 'email', 'Adresse qui reçoit les demandes de rappel et les réponses au questionnaire.'),
    'address_line' => array('Adresse', 'text', ''),
    'postal_code' => array('Code postal', 'text', ''),
    'city' => array('Ville', 'text', ''),
    'linkedin_url' => array('Profil LinkedIn', 'url', ''),
    'avocat_url' => array('Profil Avocat.fr', 'url', ''),
    'avocat_widget_id' => array('Identifiant du widget Avocat.fr', 'text', 'Ne le modifiez que si Avocat.fr fournit un nouvel identifiant.'),
    'seneque_url' => array('Site du réseau Sénéque', 'url', ''),
    'google_business_url' => array('Fiche Google Business Profile', 'url', 'Utilisée dans les données structurées du cabinet (sameAs).'),
  );
  ?>
  <div class="wrap"><h1>Réglages du cabinet</h1><p>Ces informations sont utilisées sur l’ensemble du site, dans les coordonnées, les liens externes, le diagnostic et les données structurées.</p>
    <form method="post" action="options.php">
      <?php settings_fields('spa_cabinet_settings_group'); ?>
      <table class="form-table" role="presentation"><tbody>
      <?php foreach ($fields as $key => $field) : ?>
        <tr><th scope="row"><label for="spa-<?php echo esc_attr($key); ?>"><?php echo esc_html($field[0]); ?></label></th><td>
          <input class="regular-text" id="spa-<?php echo esc_attr($key); ?>" name="spa_cabinet_settings[<?php echo esc_attr($key); ?>]" type="<?php echo esc_attr($field[1]); ?>" value="<?php echo esc_attr($settings[$key]); ?>">
          <?php if ($field[2]) : ?><p class="description"><?php echo esc_html($field[2]); ?></p><?php endif; ?>
        </td></tr>
      <?php endforeach; ?>
      </tbody></table>
      <?php submit_button('Enregistrer les réglages'); ?>
    </form>
  </div>
  <?php
}


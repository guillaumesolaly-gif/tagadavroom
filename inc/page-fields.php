<?php
// Meta-boxes des pages d'expertise (accompagnement du cabinet, liens "à lire également", vidéo TL7, SEO de page) et leur rendu côté site public.

function spa_page_blocks_config() {
  static $config = null;
  if ($config !== null) return $config;
  $file = get_template_directory() . '/config/page-blocks.json';
  $config = file_exists($file) ? json_decode(file_get_contents($file), true) : array();
  return is_array($config) ? $config : array();
}

function spa_current_page_defaults($post_id = 0) {
  $slug = $post_id ? get_post_field('post_name', $post_id) : get_post_field('post_name', get_queried_object_id());
  $config = spa_page_blocks_config();
  return isset($config[$slug]) ? $config[$slug] : array();
}

function spa_page_value($post_id, $key, $default = '') {
  return metadata_exists('post', $post_id, $key) ? get_post_meta($post_id, $key, true) : $default;
}

function spa_add_page_settings_box() {
  $front_id = (int) get_option('page_on_front');
  global $post;
  if ($front_id && $post && (int) $post->ID === $front_id) add_meta_box('spa-home-content', 'Contenu de la homepage', 'spa_render_home_content_box', 'page', 'normal', 'high', array('__block_editor_compatible_meta_box' => true));
  if ($post && $post->post_name === 'des-solutions-en-cas-de-difficultes-financieres') add_meta_box('spa-video-settings', 'Interview vidéo TL7', 'spa_render_video_settings_box', 'page', 'normal', 'high');
  add_meta_box('spa-structured-blocks', 'Blocs de la page', 'spa_render_structured_blocks_box', 'page', 'normal', 'high');
  add_meta_box('spa-page-settings', 'Référencement de la page', 'spa_render_page_settings_box', 'page', 'normal', 'high');
}
add_action('add_meta_boxes', 'spa_add_page_settings_box');

function spa_render_structured_blocks_box($post) {
  $defaults = spa_current_page_defaults($post->ID);
  if (!$defaults) {
    echo '<p>Ces réglages sont disponibles sur les pages d’expertise du cabinet.</p>';
    return;
  }
  wp_nonce_field('spa_save_structured_blocks', 'spa_structured_blocks_nonce');
  echo '<h3>Accompagnement du cabinet</h3>';
  echo '<p><label><strong>Petit titre</strong></label><input class="widefat" type="text" name="spa_support_kicker" value="' . esc_attr(spa_page_value($post->ID, '_spa_support_kicker', $defaults['support']['kicker'])) . '"></p>';
  echo '<p><label><strong>Titre principal</strong></label><textarea class="widefat" rows="2" name="spa_support_title">' . esc_textarea(spa_page_value($post->ID, '_spa_support_title', $defaults['support']['title'])) . '</textarea><small>Utilise un retour à la ligne pour contrôler la coupure du titre.</small></p>';
  for ($i = 0; $i < 4; $i++) {
    echo '<p><label><strong>Engagement ' . ($i + 1) . '</strong></label><input class="widefat" type="text" name="spa_support_item_' . $i . '" value="' . esc_attr(spa_page_value($post->ID, '_spa_support_item_' . $i, $defaults['support']['items'][$i])) . '"></p>';
  }
  echo '<hr><h3>À lire également</h3>';
  echo '<p><label><strong>Petit titre</strong></label><input class="widefat" type="text" name="spa_related_kicker" value="' . esc_attr(spa_page_value($post->ID, '_spa_related_kicker', $defaults['related']['kicker'])) . '"></p>';
  echo '<p><label><strong>Titre principal</strong></label><input class="widefat" type="text" name="spa_related_title" value="' . esc_attr(spa_page_value($post->ID, '_spa_related_title', $defaults['related']['title'])) . '"></p>';
  for ($i = 0; $i < 3; $i++) {
    echo '<fieldset style="padding:12px 14px;margin:14px 0;border:1px solid #c3c4c7"><legend><strong>Lien ' . ($i + 1) . '</strong></legend>';
    echo '<p><label>Libellé</label><input class="widefat" type="text" name="spa_related_label_' . $i . '" value="' . esc_attr(spa_page_value($post->ID, '_spa_related_label_' . $i, $defaults['related']['items'][$i]['label'])) . '"></p>';
    echo '<p><label>Adresse</label><input class="widefat" type="url" name="spa_related_url_' . $i . '" value="' . esc_attr(spa_page_value($post->ID, '_spa_related_url_' . $i, $defaults['related']['items'][$i]['url'])) . '"></p></fieldset>';
  }
}

function spa_save_structured_blocks($post_id) {
  if (!isset($_POST['spa_structured_blocks_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['spa_structured_blocks_nonce'])), 'spa_save_structured_blocks')) return;
  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
  if (!current_user_can('edit_post', $post_id)) return;
  $text_fields = array('spa_support_kicker', 'spa_support_title', 'spa_related_kicker', 'spa_related_title');
  for ($i = 0; $i < 4; $i++) $text_fields[] = 'spa_support_item_' . $i;
  for ($i = 0; $i < 3; $i++) $text_fields[] = 'spa_related_label_' . $i;
  foreach ($text_fields as $field) {
    $value = isset($_POST[$field]) ? sanitize_textarea_field(wp_unslash($_POST[$field])) : '';
    update_post_meta($post_id, '_' . $field, $value);
  }
  for ($i = 0; $i < 3; $i++) {
    $field = 'spa_related_url_' . $i;
    $value = isset($_POST[$field]) ? esc_url_raw(wp_unslash($_POST[$field])) : '';
    update_post_meta($post_id, '_' . $field, $value);
  }
}
add_action('save_post_page', 'spa_save_structured_blocks');

function spa_render_support_and_related() {
  $page_id = get_queried_object_id();
  $defaults = spa_current_page_defaults($page_id);
  if (!$defaults) return;
  $support_kicker = spa_page_value($page_id, '_spa_support_kicker', $defaults['support']['kicker']);
  $support_title = spa_page_value($page_id, '_spa_support_title', $defaults['support']['title']);
  $related_kicker = spa_page_value($page_id, '_spa_related_kicker', $defaults['related']['kicker']);
  $related_title = spa_page_value($page_id, '_spa_related_title', $defaults['related']['title']);
  $diagnostic_pages = array('prevention-difficultes-entreprise-saint-etienne', 'entreprise-en-difficulte-que-faire-saint-etienne', 'cessation-de-paiement-que-faire-dirigeant-saint-etienne', 'eviter-liquidation-judiciaire-entreprise-saint-etienne', 'des-solutions-en-cas-de-difficultes-financieres');
  echo '<section class="support-band"><div><p class="kicker">' . esc_html($support_kicker) . '</p><h2>' . nl2br(esc_html($support_title)) . '</h2></div><div class="support-lines">';
  for ($i = 0; $i < 4; $i++) echo '<p>' . esc_html(spa_page_value($page_id, '_spa_support_item_' . $i, $defaults['support']['items'][$i])) . '</p>';
  echo '</div></section>';
  if (is_page($diagnostic_pages)) echo '<section class="diagnostic-inline-cta"><div><p class="kicker">Autodiagnostic de l’entreprise</p><h2>Évaluez le niveau de vigilance en quelques minutes.</h2><p>Le résultat est immédiat et vos réponses ne sont transmises que si vous demandez à être recontacté.</p></div><a class="btn" href="' . esc_url(home_url('/diagnostic-entreprise-en-difficulte/')) . '">Commencer le diagnostic <b>→</b></a></section>';
  echo '<section class="related-pages compact-related"><div><p class="kicker">' . esc_html($related_kicker) . '</p><h2>' . esc_html($related_title) . '</h2></div><div class="related-links">';
  for ($i = 0; $i < 3; $i++) {
    $label = spa_page_value($page_id, '_spa_related_label_' . $i, $defaults['related']['items'][$i]['label']);
    $url = spa_page_value($page_id, '_spa_related_url_' . $i, $defaults['related']['items'][$i]['url']);
    echo '<a href="' . esc_url($url) . '"><span>' . esc_html($label) . '</span><b>→</b></a>';
  }
  if (is_page($diagnostic_pages)) echo '<a class="related-diagnostic" href="' . esc_url(home_url('/diagnostic-entreprise-en-difficulte/')) . '"><span>Évaluer le niveau de difficulté de l’entreprise</span><b>→</b></a>';
  echo '</div></section>';
}

function spa_render_video_settings_box($post) {
  wp_nonce_field('spa_save_video_settings', 'spa_video_settings_nonce');
  $title = spa_page_value($post->ID, '_spa_video_title', 'Difficultés en entreprise : quand faire appel à un avocat ?');
  $text = spa_page_value($post->ID, '_spa_video_text', 'Maître Juliette Saint-Père répond aux questions de TL7 dans l’émission Loire Éco.');
  $url = spa_page_value($post->ID, '_spa_video_url', 'https://youtu.be/PXIOlkuHrHk');
  echo '<p><label><strong>Titre de la vidéo</strong></label><input class="widefat" type="text" name="spa_video_title" value="' . esc_attr($title) . '"></p>';
  echo '<p><label><strong>Texte de présentation</strong></label><textarea class="widefat" rows="3" name="spa_video_text">' . esc_textarea($text) . '</textarea></p>';
  echo '<p><label><strong>Adresse YouTube</strong></label><input class="widefat" type="url" name="spa_video_url" value="' . esc_attr($url) . '"></p>';
}

function spa_save_video_settings($post_id) {
  if (!isset($_POST['spa_video_settings_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['spa_video_settings_nonce'])), 'spa_save_video_settings')) return;
  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
  if (!current_user_can('edit_post', $post_id)) return;
  update_post_meta($post_id, '_spa_video_title', isset($_POST['spa_video_title']) ? sanitize_text_field(wp_unslash($_POST['spa_video_title'])) : '');
  update_post_meta($post_id, '_spa_video_text', isset($_POST['spa_video_text']) ? sanitize_textarea_field(wp_unslash($_POST['spa_video_text'])) : '');
  update_post_meta($post_id, '_spa_video_url', isset($_POST['spa_video_url']) ? esc_url_raw(wp_unslash($_POST['spa_video_url'])) : '');
}
add_action('save_post_page', 'spa_save_video_settings');

function spa_render_video_feature() {
  $page_id = get_queried_object_id();
  $title = spa_page_value($page_id, '_spa_video_title', 'Difficultés en entreprise : quand faire appel à un avocat ?');
  $text = spa_page_value($page_id, '_spa_video_text', 'Maître Juliette Saint-Père répond aux questions de TL7 dans l’émission Loire Éco.');
  $url = spa_page_value($page_id, '_spa_video_url', 'https://youtu.be/PXIOlkuHrHk');
  $video_id = '';
  if (preg_match('~(?:youtu\.be/|youtube(?:-nocookie)?\.com/(?:watch\?v=|embed/|shorts/))([a-zA-Z0-9_-]{11})~', $url, $matches)) $video_id = $matches[1];
  echo '<section class="video-feature"><div class="video-copy"><p class="kicker">Interview TL7 — Loire Éco</p><h2>' . esc_html($title) . '</h2><p>' . esc_html($text) . '</p><p class="video-note">La vidéo démarre sans le son. Vous pouvez activer le son dans le lecteur.</p></div>';
  if ($video_id) {
    $embed_src = 'https://www.youtube-nocookie.com/embed/' . rawurlencode($video_id) . '?mute=1&loop=1&playlist=' . rawurlencode($video_id) . '&playsinline=1&rel=0&modestbranding=1';
    echo '<div class="video-consent" data-video-src="' . esc_attr($embed_src) . '"><button type="button">' . spa_icon('play_arrow') . '<strong>Lancer la vidéo</strong><small>Interview TL7 — s’ouvre après votre clic</small></button></div>';
  }
  echo '</section>';
}

function spa_render_page_settings_box($post) {
  wp_nonce_field('spa_save_page_settings', 'spa_page_settings_nonce');
  $title = get_post_meta($post->ID, '_spa_seo_title', true);
  $description = get_post_meta($post->ID, '_spa_seo_description', true);
  echo '<p><label for="spa_seo_title"><strong>Titre SEO</strong></label><br>';
  echo '<input type="text" id="spa_seo_title" name="spa_seo_title" value="' . esc_attr($title) . '" class="widefat" maxlength="70">';
  echo '<small>Laisser vide pour utiliser le titre WordPress de la page.</small></p>';
  echo '<p><label for="spa_seo_description"><strong>Méta-description</strong></label><br>';
  echo '<textarea id="spa_seo_description" name="spa_seo_description" class="widefat" rows="3" maxlength="170">' . esc_textarea($description) . '</textarea>';
  echo '<small>Résumé destiné aux moteurs de recherche. Environ 150 à 160 caractères.</small></p>';
}

function spa_save_page_settings($post_id) {
  if (!isset($_POST['spa_page_settings_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['spa_page_settings_nonce'])), 'spa_save_page_settings')) return;
  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
  if (!current_user_can('edit_post', $post_id)) return;
  $title = isset($_POST['spa_seo_title']) ? sanitize_text_field(wp_unslash($_POST['spa_seo_title'])) : '';
  $description = isset($_POST['spa_seo_description']) ? sanitize_textarea_field(wp_unslash($_POST['spa_seo_description'])) : '';
  update_post_meta($post_id, '_spa_seo_title', $title);
  update_post_meta($post_id, '_spa_seo_description', $description);
}
add_action('save_post_page', 'spa_save_page_settings');


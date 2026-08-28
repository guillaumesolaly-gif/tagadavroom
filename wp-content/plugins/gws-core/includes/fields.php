<?php
/**
 * Générateur minimal de champs structurés pour les CPT/pages du starter.
 *
 * Volontairement réduit : un schéma déclaratif (tableau PHP) => rendu de champs dans une meta
 * box + sauvegarde sécurisée (nonce + sanitize par type). Ce n'est pas un concurrent d'ACF : pas
 * de repeater riche, pas de relation, pas de galerie. Pour ces besoins, ajouter une extension
 * éprouvée (ex. ACF) ou du code dédié projet par projet — ne pas étendre ce fichier pour les
 * imiter.
 *
 * Schéma attendu, un tableau associatif clé => définition :
 *   'clé_du_champ' => [
 *     'label'       => 'Libellé affiché',
 *     'type'        => 'text|textarea|url|email|number|select|checkbox|attachment_id',
 *     'description' => 'Aide optionnelle affichée sous le champ',
 *     'options'     => ['valeur' => 'Libellé', ...], // uniquement pour 'select'
 *     'default'     => '', // valeur si aucune meta enregistrée
 *   ],
 *
 * 'attachment_id' ne couvre que la sanitization (un ID d'image de la médiathèque, vérifié) : son
 * rendu (sélecteur avec aperçu) n'est pas fourni par gws_core_render_meta_fields() — c'est une
 * UI plus riche que ce générateur minimal ne prend pas en charge pour l'instant. Voir
 * includes/admin/settings-page.php pour un exemple de rendu dédié (logo de l'entité).
 *
 * La meta est stockée sous le nom exact de la clé (pas de préfixe automatique) : au module
 * appelant de préfixer ses propres clés pour éviter toute collision avec un autre module.
 */

if (!defined('ABSPATH')) exit;

function gws_core_field_sanitize($type, $raw_value) {
  switch ($type) {
    case 'url':
      return esc_url_raw(wp_unslash($raw_value));
    case 'email':
      return sanitize_email(wp_unslash($raw_value));
    case 'number':
      return is_numeric($raw_value) ? (float) $raw_value : '';
    case 'checkbox':
      return $raw_value ? '1' : '';
    case 'attachment_id':
      $attachment_id = absint($raw_value);
      return ($attachment_id && wp_attachment_is_image($attachment_id)) ? $attachment_id : 0;
    case 'textarea':
      return sanitize_textarea_field(wp_unslash($raw_value));
    case 'select':
      return sanitize_key(wp_unslash($raw_value));
    case 'text':
    default:
      return sanitize_text_field(wp_unslash($raw_value));
  }
}

/**
 * Enregistre une meta box générique à partir d'un schéma, pour un post type donné.
 *
 * @param string $box_id     Identifiant unique de la meta box.
 * @param string $title      Titre affiché dans l'admin.
 * @param string $post_type  Post type ciblé.
 * @param array  $schema     Schéma de champs (voir en-tête de fichier).
 * @param string $nonce_action Action de nonce, unique par meta box.
 */
function gws_core_register_field_meta_box($box_id, $title, $post_type, $schema, $nonce_action) {
  add_action('add_meta_boxes_' . $post_type, function ($post) use ($box_id, $title, $post_type, $schema, $nonce_action) {
    add_meta_box($box_id, $title, function ($post) use ($schema, $nonce_action) {
      gws_core_render_meta_fields($post, $schema, $nonce_action);
    }, $post_type, 'normal', 'default');
  });
  add_action('save_post_' . $post_type, function ($post_id) use ($schema, $nonce_action) {
    gws_core_save_meta_fields($post_id, $schema, $nonce_action);
  });
  foreach ($schema as $key => $field) {
    register_post_meta($post_type, $key, array(
      'show_in_rest' => !empty($field['show_in_rest']),
      'single' => true,
      'type' => in_array($field['type'] ?? 'text', array('number'), true) ? 'number' : 'string',
      'sanitize_callback' => function ($value) use ($field) {
        return gws_core_field_sanitize($field['type'] ?? 'text', $value);
      },
    ));
  }
}

function gws_core_render_meta_fields($post, $schema, $nonce_action) {
  wp_nonce_field($nonce_action, $nonce_action . '_nonce');
  foreach ($schema as $key => $field) {
    $type = $field['type'] ?? 'text';
    $value = get_post_meta($post->ID, $key, true);
    if ($value === '' && isset($field['default'])) $value = $field['default'];
    echo '<p><label for="' . esc_attr($key) . '"><strong>' . esc_html($field['label']) . '</strong></label><br>';
    if ($type === 'textarea') {
      echo '<textarea class="widefat" rows="4" id="' . esc_attr($key) . '" name="' . esc_attr($key) . '">' . esc_textarea($value) . '</textarea>';
    } elseif ($type === 'checkbox') {
      echo '<input type="checkbox" id="' . esc_attr($key) . '" name="' . esc_attr($key) . '" value="1"' . checked($value, '1', false) . '>';
    } elseif ($type === 'select' && !empty($field['options'])) {
      echo '<select class="widefat" id="' . esc_attr($key) . '" name="' . esc_attr($key) . '">';
      foreach ($field['options'] as $option_value => $option_label) {
        echo '<option value="' . esc_attr($option_value) . '"' . selected($value, $option_value, false) . '>' . esc_html($option_label) . '</option>';
      }
      echo '</select>';
    } else {
      $input_type = in_array($type, array('url', 'email', 'number'), true) ? $type : 'text';
      echo '<input class="widefat" type="' . esc_attr($input_type) . '" id="' . esc_attr($key) . '" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '">';
    }
    if (!empty($field['description'])) echo '<small>' . esc_html($field['description']) . '</small>';
    echo '</p>';
  }
}

function gws_core_save_meta_fields($post_id, $schema, $nonce_action) {
  $nonce_field = $nonce_action . '_nonce';
  if (!isset($_POST[$nonce_field]) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[$nonce_field])), $nonce_action)) return;
  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
  if (!current_user_can('edit_post', $post_id)) return;
  foreach ($schema as $key => $field) {
    $type = $field['type'] ?? 'text';
    if ($type === 'checkbox') {
      update_post_meta($post_id, $key, gws_core_field_sanitize($type, $_POST[$key] ?? ''));
      continue;
    }
    if (!isset($_POST[$key])) continue;
    update_post_meta($post_id, $key, gws_core_field_sanitize($type, $_POST[$key]));
  }
}

<?php
/**
 * Écran d'administration des réglages de l'entité (menu Réglages > Entité).
 */

if (!defined('ABSPATH')) exit;

function gws_core_add_settings_page() {
  $hook = add_options_page('Réglages de l’entité', 'Entité', 'manage_options', 'gws-core-settings', 'gws_core_render_settings_page');
  add_action('load-' . $hook, function () {
    add_action('admin_enqueue_scripts', 'gws_core_enqueue_settings_page_assets');
  });
}
add_action('admin_menu', 'gws_core_add_settings_page');

function gws_core_enqueue_settings_page_assets() {
  wp_enqueue_media();
  wp_enqueue_script('gws-core-logo-picker', GWS_CORE_URL . 'assets/admin-logo-picker.js', array(), GWS_CORE_VERSION, true);
}

function gws_core_render_logo_field($key, $attachment_id) {
  $preview_url = $attachment_id ? wp_get_attachment_image_url($attachment_id, 'thumbnail') : '';
  ?>
  <div class="gws-logo-picker">
    <img id="gws-logo-preview" src="<?php echo esc_url($preview_url); ?>" alt="" style="max-width:120px;max-height:120px;display:<?php echo $preview_url ? 'block' : 'none'; ?>;margin-bottom:8px;">
    <input type="hidden" id="gws-<?php echo esc_attr($key); ?>" name="gws_core_settings[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($attachment_id); ?>">
    <p>
      <button type="button" class="button" id="gws-logo-select">Choisir un logo</button>
      <button type="button" class="button" id="gws-logo-remove" <?php echo $attachment_id ? '' : 'style="display:none;"'; ?>>Supprimer</button>
    </p>
  </div>
  <?php
}

function gws_core_render_settings_page() {
  if (!current_user_can('manage_options')) return;
  $settings = gws_core_settings();
  ?>
  <div class="wrap">
    <h1>Réglages de l’entité</h1>
    <p>Ces informations sont utilisées par le thème et les modules métier (coordonnées, données structurées, gabarits de contact).</p>
    <form method="post" action="options.php">
      <?php settings_fields('gws_core_settings_group'); ?>
      <table class="form-table" role="presentation"><tbody>
      <?php foreach (gws_core_settings_fields() as $key => $field) :
        $type = $field['type'] ?? 'text';
        ?>
        <tr>
          <th scope="row"><label for="gws-<?php echo esc_attr($key); ?>"><?php echo esc_html($field['label']); ?></label></th>
          <td>
            <?php if ($type === 'textarea') : ?>
              <textarea class="large-text" rows="3" id="gws-<?php echo esc_attr($key); ?>" name="gws_core_settings[<?php echo esc_attr($key); ?>]"><?php echo esc_textarea($settings[$key]); ?></textarea>
            <?php elseif ($type === 'checkbox') : ?>
              <label><input type="checkbox" id="gws-<?php echo esc_attr($key); ?>" name="gws_core_settings[<?php echo esc_attr($key); ?>]" value="1" <?php checked($settings[$key], '1'); ?>> <?php echo esc_html($field['checkbox_label'] ?? 'Activer'); ?></label>
            <?php elseif ($type === 'attachment_id') : ?>
              <?php gws_core_render_logo_field($key, (int) $settings[$key]); ?>
            <?php else : ?>
              <input class="regular-text" id="gws-<?php echo esc_attr($key); ?>" name="gws_core_settings[<?php echo esc_attr($key); ?>]" type="<?php echo esc_attr($type); ?>" value="<?php echo esc_attr($settings[$key]); ?>">
            <?php endif; ?>
            <?php if (!empty($field['description'])) : ?><p class="description"><?php echo esc_html($field['description']); ?></p><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody></table>
      <?php submit_button('Enregistrer les réglages'); ?>
    </form>
  </div>
  <?php
}

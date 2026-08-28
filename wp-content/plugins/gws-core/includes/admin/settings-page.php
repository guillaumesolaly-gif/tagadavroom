<?php
/**
 * Écran d'administration des réglages de l'entité (menu Réglages > Entité).
 */

if (!defined('ABSPATH')) exit;

function gws_core_add_settings_page() {
  add_options_page('Réglages de l’entité', 'Entité', 'manage_options', 'gws-core-settings', 'gws_core_render_settings_page');
}
add_action('admin_menu', 'gws_core_add_settings_page');

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
      <?php foreach (gws_core_settings_fields() as $key => $field) : ?>
        <tr>
          <th scope="row"><label for="gws-<?php echo esc_attr($key); ?>"><?php echo esc_html($field['label']); ?></label></th>
          <td>
            <?php if (($field['type'] ?? 'text') === 'textarea') : ?>
              <textarea class="large-text" rows="3" id="gws-<?php echo esc_attr($key); ?>" name="gws_core_settings[<?php echo esc_attr($key); ?>]"><?php echo esc_textarea($settings[$key]); ?></textarea>
            <?php else : ?>
              <input class="regular-text" id="gws-<?php echo esc_attr($key); ?>" name="gws_core_settings[<?php echo esc_attr($key); ?>]" type="<?php echo esc_attr($field['type'] ?? 'text'); ?>" value="<?php echo esc_attr($settings[$key]); ?>">
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

<?php
/**
 * Réglages globaux de GWS Equestrian (§8 de la demande) — pour l'instant un seul réglage :
 * affichage des tarifs HT ou TTC. Volontairement indépendant des réglages génériques de gws-core
 * (`gws_core_settings`) : ce n'est pas un fait générique du site (comme le nom de l'entité ou son
 * logo), c'est une convention d'affichage propre aux prestations équestres — elle vit donc dans sa
 * propre option (`gwseq_settings`), gérée entièrement par ce module.
 *
 * GWS Equestrian n'effectue aucun calcul de TVA : ce réglage indique seulement la nature des
 * montants déjà saisis par le professionnel.
 */

if (!defined('ABSPATH')) exit;

function gwseq_settings_defaults() {
  return array('price_display_mode' => 'ttc');
}

function gwseq_settings() {
  return wp_parse_args((array) get_option('gwseq_settings', array()), gwseq_settings_defaults());
}

function gwseq_get_price_display_mode() {
  $settings = gwseq_settings();
  return $settings['price_display_mode'] === 'ht' ? 'ht' : 'ttc';
}

function gwseq_sanitize_settings($input) {
  $input = is_array($input) ? $input : array();
  $mode = isset($input['price_display_mode']) ? sanitize_key($input['price_display_mode']) : 'ttc';
  return array('price_display_mode' => $mode === 'ht' ? 'ht' : 'ttc');
}

function gwseq_register_settings() {
  register_setting('gwseq_settings_group', 'gwseq_settings', array(
    'type' => 'array',
    'sanitize_callback' => 'gwseq_sanitize_settings',
    'default' => gwseq_settings_defaults(),
  ));
}
add_action('admin_init', 'gwseq_register_settings');

function gwseq_add_settings_page() {
  add_submenu_page(
    'edit.php?post_type=' . GWSEQ_CPT_PRESTATION,
    'Réglages — Prestations',
    'Réglages',
    'manage_options',
    'gwseq-prestations-settings',
    'gwseq_render_settings_page'
  );
}
add_action('admin_menu', 'gwseq_add_settings_page');

function gwseq_render_settings_page() {
  if (!current_user_can('manage_options')) return;
  $settings = gwseq_settings();
  ?>
  <div class="wrap">
    <h1>Réglages — Prestations</h1>
    <form method="post" action="options.php">
      <?php settings_fields('gwseq_settings_group'); ?>
      <table class="form-table" role="presentation"><tbody>
        <tr>
          <th scope="row">Affichage des tarifs</th>
          <td>
            <label><input type="radio" name="gwseq_settings[price_display_mode]" value="ttc" <?php checked($settings['price_display_mode'], 'ttc'); ?>> TTC (toutes taxes comprises)</label><br>
            <label><input type="radio" name="gwseq_settings[price_display_mode]" value="ht" <?php checked($settings['price_display_mode'], 'ht'); ?>> HT (hors taxes)</label>
            <p class="description">Indique uniquement la nature des montants déjà saisis : GWS Equestrian ne calcule aucune TVA.</p>
          </td>
        </tr>
      </tbody></table>
      <?php submit_button('Enregistrer'); ?>
    </form>
  </div>
  <?php
}

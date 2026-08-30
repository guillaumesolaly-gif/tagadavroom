<?php
/**
 * Réglages globaux de GWS Equestrian (§8 de la demande initiale, complétés suite à la relecture
 * de l'Étape 3) : affichage des tarifs (TTC / HT / prix masqués) et devise. Volontairement
 * indépendant des réglages génériques de gws-core (`gws_core_settings`) : ce n'est pas un fait
 * générique du site (comme le nom de l'entité ou son logo), ce sont des conventions d'affichage
 * propres aux prestations équestres — elles vivent donc dans leur propre option
 * (`gwseq_settings`), gérée entièrement par ce module.
 *
 * GWS Equestrian n'effectue aucun calcul de TVA ni de conversion monétaire : ces réglages
 * indiquent seulement la nature/l'unité monétaire des montants déjà saisis par le professionnel.
 *
 * "Prix masqués" (mode global) : aucun montant tarifaire n'est rendu publiquement quelle que soit
 * la valeur de la case individuelle "Afficher ce tarif publiquement" d'une prestation — priorité
 * conceptuelle : masque global > masque individuel > rendu HT/TTC normal. Les montants stockés ne
 * sont jamais supprimés ni modifiés par ce réglage : c'est une règle de présentation uniquement,
 * réversible sans perte de données (voir gwseq_prestation_price_summary() dans
 * prestation-fields.php).
 */

if (!defined('ABSPATH')) exit;

function gwseq_price_display_mode_options() {
  return array(
    'ttc' => 'TTC (toutes taxes comprises)',
    'ht' => 'HT (hors taxes)',
    'hidden' => 'Prix masqués (aucun tarif affiché publiquement)',
  );
}

/**
 * Mapping volontairement minimal code ISO 4217 => symbole affiché — pas de bibliothèque externe,
 * pas de taux de change, aucun calcul. Étendre à une nouvelle devise se limite à ajouter une
 * entrée ici et dans gwseq_currency_options(). Le symbole est toujours placé après le montant
 * (« 45 £ », « 45 CHF »...) pour rester simple : aucun moteur de formatage monétaire localisé
 * n'est construit, conformément à la demande.
 */
function gwseq_currency_symbols() {
  return array(
    'EUR' => '€',
    'GBP' => '£',
    'USD' => '$',
    'CHF' => 'CHF',
  );
}

function gwseq_currency_options() {
  return array(
    'EUR' => 'Euro (€)',
    'GBP' => 'Livre sterling (£)',
    'USD' => 'Dollar américain ($)',
    'CHF' => 'Franc suisse (CHF)',
  );
}

function gwseq_currency_symbol($code) {
  $symbols = gwseq_currency_symbols();
  return $symbols[$code] ?? $code;
}

function gwseq_settings_defaults() {
  return array('price_display_mode' => 'ttc', 'currency' => 'EUR');
}

function gwseq_settings() {
  return wp_parse_args((array) get_option('gwseq_settings', array()), gwseq_settings_defaults());
}

function gwseq_get_price_display_mode() {
  $settings = gwseq_settings();
  return array_key_exists($settings['price_display_mode'], gwseq_price_display_mode_options()) ? $settings['price_display_mode'] : 'ttc';
}

function gwseq_get_currency() {
  $settings = gwseq_settings();
  return array_key_exists($settings['currency'], gwseq_currency_options()) ? $settings['currency'] : 'EUR';
}

function gwseq_sanitize_settings($input) {
  $input = is_array($input) ? $input : array();

  $mode = isset($input['price_display_mode']) ? sanitize_key($input['price_display_mode']) : 'ttc';
  if (!array_key_exists($mode, gwseq_price_display_mode_options())) $mode = 'ttc';

  $currency = isset($input['currency']) ? strtoupper(sanitize_key($input['currency'])) : 'EUR';
  if (!array_key_exists($currency, gwseq_currency_options())) $currency = 'EUR';

  return array('price_display_mode' => $mode, 'currency' => $currency);
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
            <?php foreach (gwseq_price_display_mode_options() as $key => $label) : ?>
              <label><input type="radio" name="gwseq_settings[price_display_mode]" value="<?php echo esc_attr($key); ?>" <?php checked($settings['price_display_mode'], $key); ?>> <?php echo esc_html($label); ?></label><br>
            <?php endforeach; ?>
            <p class="description">Indique uniquement la nature des montants déjà saisis : GWS Equestrian ne calcule aucune TVA. « Prix masqués » n’efface aucun montant enregistré — c’est réversible à tout moment.</p>
          </td>
        </tr>
        <tr>
          <th scope="row">Devise</th>
          <td>
            <select name="gwseq_settings[currency]">
              <?php foreach (gwseq_currency_options() as $key => $label) : ?>
                <option value="<?php echo esc_attr($key); ?>" <?php selected($settings['currency'], $key); ?>><?php echo esc_html($label); ?></option>
              <?php endforeach; ?>
            </select>
            <p class="description">Aucun taux de change ni conversion : les montants saisis sont simplement présentés dans cette devise.</p>
          </td>
        </tr>
      </tbody></table>
      <?php submit_button('Enregistrer'); ?>
    </form>
  </div>
  <?php
}

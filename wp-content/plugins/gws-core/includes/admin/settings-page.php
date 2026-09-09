<?php
/**
 * Écran d'administration de « Ma structure » (menu Réglages > Ma structure).
 *
 * Lot 2C : renommage BO uniquement (« Entité » => « Ma structure »). Le slug de page
 * ('gws-core-settings'), l'option ('gws_core_settings'), le groupe de réglages
 * ('gws_core_settings_group') et tous les identifiants techniques restent inchangés — voir le
 * docblock de includes/settings.php pour le détail de cette décision (non-destructivité, pas de
 * migration nécessaire).
 */

if (!defined('ABSPATH')) exit;

function gws_core_add_settings_page() {
  $hook = add_options_page('Ma structure', 'Ma structure', 'manage_options', 'gws-core-settings', 'gws_core_render_settings_page');
  add_action('load-' . $hook, function () {
    add_action('admin_enqueue_scripts', 'gws_core_enqueue_settings_page_assets');
  });
}
add_action('admin_menu', 'gws_core_add_settings_page');

/**
 * Assets chargés UNIQUEMENT sur cet écran (jamais globalement dans le BO, §20) : la médiathèque
 * pour le logo (déjà en place avant ce lot) et le sélecteur de couleur natif WordPress
 * (wp-color-picker, basé sur Iris — livré avec le cœur WordPress, aucune dépendance nouvelle,
 * §6) pour les deux champs couleur.
 */
function gws_core_enqueue_settings_page_assets() {
  wp_enqueue_media();
  wp_enqueue_script('gws-core-logo-picker', GWS_CORE_URL . 'assets/admin-logo-picker.js', array(), GWS_CORE_VERSION, true);

  wp_enqueue_style('wp-color-picker');
  wp_enqueue_script('gws-core-color-picker', GWS_CORE_URL . 'assets/admin-color-picker.js', array('wp-color-picker'), GWS_CORE_VERSION, true);
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

/**
 * Champ couleur (Lot 2C, §6-7-17-18) : simple champ texte transformé en sélecteur wp-color-picker
 * par assets/admin-color-picker.js — reste un `#rrggbb` lisible à côté du sélecteur (pas de jargon
 * technique dans le libellé principal, un peu de vocabulaire hexadécimal reste naturel juste à côté
 * du sélecteur, cf. §17). Volontairement laissé VIDE si aucune couleur n'est enregistrée : ne
 * préremplit jamais la couleur par défaut GWS dans le champ, pour ne jamais laisser croire qu'elle a
 * été choisie par la structure (§8). L'aperçu ci-dessous montre la couleur réellement UTILISÉE
 * (choisie ou, à défaut, celle par défaut), avec la mention correspondante — aperçu léger, aucune
 * maquette (§18).
 */
function gws_core_render_color_field($key, $stored_value, $effective_value, $is_default) {
  ?>
  <input type="text" class="gws-color-field" id="gws-<?php echo esc_attr($key); ?>" name="gws_core_settings[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($stored_value); ?>" placeholder="<?php echo esc_attr($effective_value); ?>">
  <p class="gws-color-preview">
    <span class="gws-color-swatch" style="display:inline-block;width:1em;height:1em;vertical-align:middle;margin-right:6px;border:1px solid #c3c4c7;border-radius:2px;background-color:<?php echo esc_attr($effective_value); ?>;"></span>
    <?php echo $is_default
      ? 'Couleur actuellement utilisée (valeur par défaut GWS) : ' . esc_html($effective_value)
      : 'Couleur actuellement utilisée : ' . esc_html($effective_value); ?>
  </p>
  <?php
}

/**
 * Regroupement visuel de l'écran (§17) — un champ dont le 'group' ne correspond à aucune entrée
 * ci-dessous (ajouté par un projet via le filtre 'gws_core_settings_fields' sans préciser de
 * groupe) atterrit dans « Autres réglages », toujours affiché en dernier.
 */
function gws_core_settings_groups() {
  return array(
    'identity' => 'Identité',
    'branding' => 'Identité visuelle',
    'presentation' => 'Présentation',
    'coordinates' => 'Coordonnées',
    'social' => 'Réseaux sociaux',
    'credit' => 'Crédit de réalisation',
    'other' => 'Autres réglages',
  );
}

function gws_core_render_settings_field_row($key, $field, $settings) {
  $type = $field['type'] ?? 'text';
  ?>
  <tr>
    <th scope="row"><label for="gws-<?php echo esc_attr($key); ?>"><?php echo esc_html($field['label']); ?></label></th>
    <td>
      <?php if ($type === 'textarea') :
        $max_length = isset($field['max_length']) ? (int) $field['max_length'] : null;
        ?>
        <textarea class="large-text" rows="<?php echo $max_length ? 6 : 3; ?>" id="gws-<?php echo esc_attr($key); ?>" name="gws_core_settings[<?php echo esc_attr($key); ?>]"<?php echo $max_length ? ' maxlength="' . esc_attr($max_length) . '"' : ''; ?>><?php echo esc_textarea($settings[$key]); ?></textarea>
      <?php elseif ($type === 'checkbox') : ?>
        <label><input type="checkbox" id="gws-<?php echo esc_attr($key); ?>" name="gws_core_settings[<?php echo esc_attr($key); ?>]" value="1" <?php checked($settings[$key], '1'); ?>> <?php echo esc_html($field['checkbox_label'] ?? 'Activer'); ?></label>
      <?php elseif ($type === 'attachment_id') : ?>
        <?php gws_core_render_logo_field($key, (int) $settings[$key]); ?>
      <?php elseif ($type === 'color') :
        $effective = $key === 'secondary_color' ? gws_core_get_secondary_color() : gws_core_get_primary_color();
        gws_core_render_color_field($key, $settings[$key], $effective, $settings[$key] === '');
        ?>
      <?php else : ?>
        <input class="regular-text" id="gws-<?php echo esc_attr($key); ?>" name="gws_core_settings[<?php echo esc_attr($key); ?>]" type="<?php echo esc_attr($type); ?>" value="<?php echo esc_attr($settings[$key]); ?>">
      <?php endif; ?>
      <?php if (!empty($field['description'])) : ?><p class="description"><?php echo esc_html($field['description']); ?></p><?php endif; ?>
    </td>
  </tr>
  <?php
}

function gws_core_render_settings_page() {
  if (!current_user_can('manage_options')) return;
  $settings = gws_core_settings();
  $groups = gws_core_settings_groups();

  $fields_by_group = array_fill_keys(array_keys($groups), array());
  foreach (gws_core_settings_fields() as $key => $field) {
    $group = (!empty($field['group']) && isset($fields_by_group[$field['group']])) ? $field['group'] : 'other';
    $fields_by_group[$group][$key] = $field;
  }
  ?>
  <div class="wrap">
    <h1>Ma structure</h1>
    <p>Ces informations décrivent votre structure une fois pour toutes : le site (et, à l’avenir,
      d’autres supports comme une fiche PDF ou un catalogue) les réutilisent, sans jamais en garder
      de copie séparée.</p>
    <?php
    // Affiche notamment le message explicite d'un champ rejeté pour dépassement de longueur (voir
    // add_settings_error() dans gws_core_sanitize_settings(), includes/settings.php) — sans cet
    // appel, un message ajouté par le sanitize_callback resterait invisible.
    settings_errors('gws_core_settings');
    ?>
    <form method="post" action="options.php">
      <?php settings_fields('gws_core_settings_group'); ?>
      <?php foreach ($groups as $group_key => $group_label) :
        if (empty($fields_by_group[$group_key])) continue;
        ?>
        <h2><?php echo esc_html($group_label); ?></h2>
        <table class="form-table" role="presentation"><tbody>
        <?php foreach ($fields_by_group[$group_key] as $key => $field) : ?>
          <?php gws_core_render_settings_field_row($key, $field, $settings); ?>
        <?php endforeach; ?>
        </tbody></table>
      <?php endforeach; ?>
      <?php submit_button('Enregistrer les réglages'); ?>
    </form>
  </div>
  <?php
}

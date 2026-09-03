<?php
/**
 * Sticky bar — fiche structurée (post type `gwseq_sticky_bar`, voir includes/post-types.php),
 * objet distinct de Pop-in mais réutilisant les briques réellement communes
 * (includes/campagnes-shared.php : CTA, couleurs, dates/fuseau, ciblage, éligibilité, preview).
 * Volontairement plus simple que Pop-in : pas de section Déclenchement (une Sticky bar éligible
 * s'affiche immédiatement, §G), pas d'image (ni de contenu, ni de fond), pas de fréquence
 * configurable en base — seule la fermeture (si activée) est mémorisée côté client.
 *
 * Pas de Gutenberg. Nom interne = post_title, jamais affiché publiquement (post type non public).
 */

if (!defined('ABSPATH')) exit;

const GWSEQ_STICKY_BAR_NONCE_ACTION = 'gwseq_save_sticky_bar_meta';
const GWSEQ_STICKY_BAR_NONCE_FIELD = 'gwseq_save_sticky_bar_meta_nonce';
const GWSEQ_STICKY_BAR_PREVIEW_NONCE_ACTION = 'gwseq_preview_sticky_bar';

function gwseq_sticky_bar_position_options() {
  return array(
    'top' => __('Haut', 'gws-core'),
    'bottom' => __('Bas', 'gws-core'),
  );
}

/* -------------------------------------------------------------------------------------------
 * Meta.
 * ----------------------------------------------------------------------------------------- */

function gwseq_register_sticky_bar_meta() {
  $string_meta = array(
    '_gwseq_sticky_bar_texte', '_gwseq_sticky_bar_cta_libelle', '_gwseq_sticky_bar_cta_url',
    '_gwseq_sticky_bar_style_mode', '_gwseq_sticky_bar_position',
    '_gwseq_sticky_bar_couleur_fond', '_gwseq_sticky_bar_couleur_texte', '_gwseq_sticky_bar_couleur_cta', '_gwseq_sticky_bar_couleur_cta_texte',
    '_gwseq_sticky_bar_statut',
    '_gwseq_sticky_bar_ciblage_mode',
  );
  foreach ($string_meta as $key) {
    register_post_meta(GWSEQ_CPT_STICKY_BAR, $key, array('single' => true, 'type' => 'string', 'show_in_rest' => false));
  }
  foreach (array('_gwseq_sticky_bar_cta_active', '_gwseq_sticky_bar_fermable') as $key) {
    register_post_meta(GWSEQ_CPT_STICKY_BAR, $key, array('single' => true, 'type' => 'string', 'show_in_rest' => false));
  }
  foreach (array('_gwseq_sticky_bar_debut_ts', '_gwseq_sticky_bar_fin_ts') as $key) {
    register_post_meta(GWSEQ_CPT_STICKY_BAR, $key, array('single' => true, 'type' => 'integer', 'show_in_rest' => false));
  }
  register_post_meta(GWSEQ_CPT_STICKY_BAR, '_gwseq_sticky_bar_ciblage_cibles', array('single' => true, 'type' => 'array', 'show_in_rest' => false));
}
add_action('init', 'gwseq_register_sticky_bar_meta');

/* -------------------------------------------------------------------------------------------
 * Sanitation.
 * ----------------------------------------------------------------------------------------- */

function gwseq_sanitize_sticky_bar_contenu_input($raw) {
  $raw = is_array($raw) ? $raw : array();
  $cta = gwseq_sanitize_campagne_cta_input($raw, '_gwseq_sticky_bar_');
  return array(
    'texte' => gws_core_field_sanitize('text', $raw['_gwseq_sticky_bar_texte'] ?? ''),
    'cta_active' => $cta['active'],
    'cta_libelle' => $cta['libelle'],
    'cta_url' => $cta['url'],
  );
}

/**
 * Même discipline que Pop-in : les couleurs ne survivent que si `style_mode === 'custom'`. Pas
 * d'image de fond pour la Sticky bar (§G, explicitement exclue).
 */
function gwseq_sanitize_sticky_bar_apparence_input($raw) {
  $raw = is_array($raw) ? $raw : array();
  $style_mode = gwseq_sanitize_campagne_style_mode($raw['_gwseq_sticky_bar_style_mode'] ?? '');
  $position = sanitize_key(wp_unslash($raw['_gwseq_sticky_bar_position'] ?? ''));
  if (!array_key_exists($position, gwseq_sticky_bar_position_options())) $position = 'top';

  $result = array(
    'style_mode' => $style_mode,
    'position' => $position,
    'fermable' => gws_core_field_sanitize('checkbox', $raw['_gwseq_sticky_bar_fermable'] ?? ''),
    'couleur_fond' => '', 'couleur_texte' => '', 'couleur_cta' => '', 'couleur_cta_texte' => '',
  );
  if ($style_mode === 'custom') {
    $result['couleur_fond'] = gwseq_sanitize_campagne_couleur($raw['_gwseq_sticky_bar_couleur_fond'] ?? '');
    $result['couleur_texte'] = gwseq_sanitize_campagne_couleur($raw['_gwseq_sticky_bar_couleur_texte'] ?? '');
    $result['couleur_cta'] = gwseq_sanitize_campagne_couleur($raw['_gwseq_sticky_bar_couleur_cta'] ?? '');
    $result['couleur_cta_texte'] = gwseq_sanitize_campagne_couleur($raw['_gwseq_sticky_bar_couleur_cta_texte'] ?? '');
  }
  return $result;
}

function gwseq_sanitize_sticky_bar_diffusion_input($raw) {
  $raw = is_array($raw) ? $raw : array();
  $statut = sanitize_key(wp_unslash($raw['_gwseq_sticky_bar_statut'] ?? ''));
  if (!array_key_exists($statut, gwseq_campagne_statut_options())) $statut = 'inactive';

  $ciblage = gwseq_sanitize_campagne_ciblage_input(array(
    'ciblage_mode' => $raw['_gwseq_sticky_bar_ciblage_mode'] ?? '',
    'ciblage_cibles' => $raw['_gwseq_sticky_bar_ciblage_cibles'] ?? array(),
  ));

  return array(
    'statut' => $statut,
    'debut_ts' => gwseq_sanitize_campagne_datetime_input($raw['_gwseq_sticky_bar_debut'] ?? ''),
    'fin_ts' => gwseq_sanitize_campagne_datetime_input($raw['_gwseq_sticky_bar_fin'] ?? ''),
    'ciblage_mode' => $ciblage['mode'],
    'ciblage_cibles' => $ciblage['cibles'],
  );
}

/* -------------------------------------------------------------------------------------------
 * Écriture.
 * ----------------------------------------------------------------------------------------- */

function gwseq_set_sticky_bar_contenu($post_id, $raw) {
  $post_id = (int) $post_id;
  if (!$post_id) return false;
  $c = gwseq_sanitize_sticky_bar_contenu_input($raw);
  update_post_meta($post_id, '_gwseq_sticky_bar_texte', $c['texte']);
  update_post_meta($post_id, '_gwseq_sticky_bar_cta_active', $c['cta_active']);
  update_post_meta($post_id, '_gwseq_sticky_bar_cta_libelle', $c['cta_libelle']);
  update_post_meta($post_id, '_gwseq_sticky_bar_cta_url', $c['cta_url']);
  return true;
}

function gwseq_set_sticky_bar_apparence($post_id, $raw) {
  $post_id = (int) $post_id;
  if (!$post_id) return false;
  $a = gwseq_sanitize_sticky_bar_apparence_input($raw);
  update_post_meta($post_id, '_gwseq_sticky_bar_style_mode', $a['style_mode']);
  update_post_meta($post_id, '_gwseq_sticky_bar_position', $a['position']);
  update_post_meta($post_id, '_gwseq_sticky_bar_fermable', $a['fermable']);
  update_post_meta($post_id, '_gwseq_sticky_bar_couleur_fond', $a['couleur_fond']);
  update_post_meta($post_id, '_gwseq_sticky_bar_couleur_texte', $a['couleur_texte']);
  update_post_meta($post_id, '_gwseq_sticky_bar_couleur_cta', $a['couleur_cta']);
  update_post_meta($post_id, '_gwseq_sticky_bar_couleur_cta_texte', $a['couleur_cta_texte']);
  return true;
}

function gwseq_set_sticky_bar_diffusion($post_id, $raw) {
  $post_id = (int) $post_id;
  if (!$post_id) return false;
  $diff = gwseq_sanitize_sticky_bar_diffusion_input($raw);
  update_post_meta($post_id, '_gwseq_sticky_bar_statut', $diff['statut']);
  update_post_meta($post_id, '_gwseq_sticky_bar_debut_ts', $diff['debut_ts']);
  update_post_meta($post_id, '_gwseq_sticky_bar_fin_ts', $diff['fin_ts']);
  update_post_meta($post_id, '_gwseq_sticky_bar_ciblage_mode', $diff['ciblage_mode']);
  update_post_meta($post_id, '_gwseq_sticky_bar_ciblage_cibles', $diff['ciblage_cibles']);
  return true;
}

/* -------------------------------------------------------------------------------------------
 * Lecture.
 * ----------------------------------------------------------------------------------------- */

function gwseq_get_sticky_bar_contenu($post_id) {
  return array(
    'texte' => get_post_meta($post_id, '_gwseq_sticky_bar_texte', true),
    'cta_active' => get_post_meta($post_id, '_gwseq_sticky_bar_cta_active', true),
    'cta_libelle' => get_post_meta($post_id, '_gwseq_sticky_bar_cta_libelle', true),
    'cta_url' => get_post_meta($post_id, '_gwseq_sticky_bar_cta_url', true),
  );
}

function gwseq_get_sticky_bar_apparence($post_id) {
  $style_mode = get_post_meta($post_id, '_gwseq_sticky_bar_style_mode', true);
  $position = get_post_meta($post_id, '_gwseq_sticky_bar_position', true);
  return array(
    'style_mode' => $style_mode !== '' ? $style_mode : 'site',
    'position' => $position !== '' ? $position : 'top',
    'fermable' => get_post_meta($post_id, '_gwseq_sticky_bar_fermable', true),
    'couleur_fond' => get_post_meta($post_id, '_gwseq_sticky_bar_couleur_fond', true),
    'couleur_texte' => get_post_meta($post_id, '_gwseq_sticky_bar_couleur_texte', true),
    'couleur_cta' => get_post_meta($post_id, '_gwseq_sticky_bar_couleur_cta', true),
    'couleur_cta_texte' => get_post_meta($post_id, '_gwseq_sticky_bar_couleur_cta_texte', true),
  );
}

function gwseq_get_sticky_bar_diffusion($post_id) {
  $statut = get_post_meta($post_id, '_gwseq_sticky_bar_statut', true);
  $ciblage_mode = get_post_meta($post_id, '_gwseq_sticky_bar_ciblage_mode', true);
  $cibles = get_post_meta($post_id, '_gwseq_sticky_bar_ciblage_cibles', true);
  return array(
    'statut' => $statut !== '' ? $statut : 'inactive',
    'debut_ts' => (int) get_post_meta($post_id, '_gwseq_sticky_bar_debut_ts', true),
    'fin_ts' => (int) get_post_meta($post_id, '_gwseq_sticky_bar_fin_ts', true),
    'ciblage_mode' => $ciblage_mode !== '' ? $ciblage_mode : 'all',
    'ciblage_cibles' => is_array($cibles) ? $cibles : array(),
  );
}

/* -------------------------------------------------------------------------------------------
 * Rendu — source unique, partagée entre preview BO et front (§J/§L).
 * ----------------------------------------------------------------------------------------- */

function gwseq_sticky_bar_config_defaults() {
  return array(
    'texte' => '',
    'cta' => array('active' => '', 'libelle' => '', 'url' => ''),
    'style_mode' => 'site', 'couleur_fond' => '', 'couleur_texte' => '', 'couleur_cta' => '', 'couleur_cta_texte' => '',
    'position' => 'top', 'fermable' => '',
  );
}

function gwseq_build_sticky_bar_config($contenu, $apparence) {
  return array(
    'texte' => $contenu['texte'],
    'cta' => array('active' => $contenu['cta_active'], 'libelle' => $contenu['cta_libelle'], 'url' => $contenu['cta_url']),
    'style_mode' => $apparence['style_mode'],
    'couleur_fond' => $apparence['couleur_fond'],
    'couleur_texte' => $apparence['couleur_texte'],
    'couleur_cta' => $apparence['couleur_cta'],
    'couleur_cta_texte' => $apparence['couleur_cta_texte'],
    'position' => $apparence['position'],
    'fermable' => $apparence['fermable'],
  );
}

function gwseq_get_sticky_bar_config($post_id) {
  return gwseq_build_sticky_bar_config(gwseq_get_sticky_bar_contenu($post_id), gwseq_get_sticky_bar_apparence($post_id));
}

/**
 * `$extra_attrs` (ex. l'ID de la campagne, pour la mémorisation de fermeture côté client — voir
 * includes/campagnes-front.php) est fusionné dans les attributs du conteneur racine, jamais un
 * second passage de reconstruction du HTML.
 */
function gwseq_render_sticky_bar_markup($config, $extra_attrs = array()) {
  $config = array_merge(gwseq_sticky_bar_config_defaults(), is_array($config) ? $config : array());
  $config['cta'] = array_merge(array('active' => '', 'libelle' => '', 'url' => ''), is_array($config['cta']) ? $config['cta'] : array());

  $position = array_key_exists($config['position'], gwseq_sticky_bar_position_options()) ? $config['position'] : 'top';
  $classes = array('gwseq-sticky-bar', 'gwseq-sticky-bar--' . $position);

  $style_attr = '';
  if ($config['style_mode'] === 'custom') {
    $classes[] = 'gwseq-sticky-bar--custom';
    $style_parts = array();
    if ($config['couleur_fond'] !== '') $style_parts[] = '--gws-sticky-bg:' . $config['couleur_fond'];
    if ($config['couleur_texte'] !== '') $style_parts[] = '--gws-sticky-text:' . $config['couleur_texte'];
    if ($config['couleur_cta'] !== '') $style_parts[] = '--gws-sticky-cta-bg:' . $config['couleur_cta'];
    if ($config['couleur_cta_texte'] !== '') $style_parts[] = '--gws-sticky-cta-text:' . $config['couleur_cta_texte'];
    if ($style_parts) $style_attr = ' style="' . esc_attr(implode(';', $style_parts)) . '"';
  }

  $extra_attr_str = '';
  foreach ((array) $extra_attrs as $attr_name => $attr_value) {
    $extra_attr_str .= ' ' . esc_attr($attr_name) . '="' . esc_attr($attr_value) . '"';
  }

  $html = '<div class="' . esc_attr(implode(' ', $classes)) . '" role="region" aria-label="' . esc_attr__('Bandeau d’information', 'gws-core') . '"' . $style_attr . $extra_attr_str . '>';
  $html .= '<div class="gwseq-sticky-bar__inner">';
  if ($config['texte'] !== '') {
    $html .= '<span class="gwseq-sticky-bar__texte">' . esc_html($config['texte']) . '</span>';
  }
  if (!empty($config['cta']['active']) && $config['cta']['libelle'] !== '' && $config['cta']['url'] !== '') {
    $html .= '<a class="gwseq-sticky-bar__cta" href="' . esc_url($config['cta']['url']) . '">' . esc_html($config['cta']['libelle']) . '</a>';
  }
  if (!empty($config['fermable'])) {
    $html .= '<button type="button" class="gwseq-sticky-bar__close" aria-label="' . esc_attr__('Fermer', 'gws-core') . '">&times;</button>';
  }
  $html .= '</div></div>';
  return $html;
}

/* -------------------------------------------------------------------------------------------
 * AJAX preview.
 * ----------------------------------------------------------------------------------------- */

function gwseq_ajax_preview_sticky_bar() {
  gwseq_verify_campagne_preview_request(GWSEQ_STICKY_BAR_PREVIEW_NONCE_ACTION);
  $contenu = gwseq_sanitize_sticky_bar_contenu_input($_POST);
  $apparence = gwseq_sanitize_sticky_bar_apparence_input($_POST);
  $config = gwseq_build_sticky_bar_config($contenu, $apparence);
  wp_send_json_success(array('html' => gwseq_render_sticky_bar_markup($config)));
}
add_action('wp_ajax_gwseq_preview_sticky_bar', 'gwseq_ajax_preview_sticky_bar');

/* -------------------------------------------------------------------------------------------
 * Meta boxes et sauvegarde.
 * ----------------------------------------------------------------------------------------- */

function gwseq_add_sticky_bar_meta_boxes() {
  add_meta_box('gwseq-sticky-bar-contenu', __('Contenu', 'gws-core'), 'gwseq_render_sticky_bar_contenu_box', GWSEQ_CPT_STICKY_BAR, 'normal', 'high');
  add_meta_box('gwseq-sticky-bar-apparence', __('Apparence', 'gws-core'), 'gwseq_render_sticky_bar_apparence_box', GWSEQ_CPT_STICKY_BAR, 'normal', 'default');
  add_meta_box('gwseq-sticky-bar-diffusion', __('Diffusion', 'gws-core'), 'gwseq_render_sticky_bar_diffusion_box', GWSEQ_CPT_STICKY_BAR, 'normal', 'default');
  add_meta_box('gwseq-sticky-bar-preview', __('Aperçu', 'gws-core'), 'gwseq_render_sticky_bar_preview_box', GWSEQ_CPT_STICKY_BAR, 'side', 'high');
}
add_action('add_meta_boxes_' . GWSEQ_CPT_STICKY_BAR, 'gwseq_add_sticky_bar_meta_boxes');

function gwseq_render_sticky_bar_contenu_box($post) {
  wp_nonce_field(GWSEQ_STICKY_BAR_NONCE_ACTION, GWSEQ_STICKY_BAR_NONCE_FIELD);
  $contenu = gwseq_get_sticky_bar_contenu($post->ID);
  ?>
  <p class="description"><?php esc_html_e('Le nom interne (titre de la fiche) sert uniquement au back-office : il n’est jamais affiché sur le site.', 'gws-core'); ?></p>
  <p>
    <label for="gwseq-sticky-bar-texte"><strong><?php esc_html_e('Texte court', 'gws-core'); ?></strong></label><br>
    <input type="text" class="widefat" id="gwseq-sticky-bar-texte" name="_gwseq_sticky_bar_texte" value="<?php echo esc_attr($contenu['texte']); ?>" placeholder="<?php esc_attr_e('Ex. Portes ouvertes le 12 mai — inscriptions ouvertes', 'gws-core'); ?>">
  </p>
  <p>
    <label>
      <input type="checkbox" name="_gwseq_sticky_bar_cta_active" value="1" <?php checked($contenu['cta_active'], '1'); ?>>
      <?php esc_html_e('Afficher un bouton d’appel à l’action (CTA)', 'gws-core'); ?>
    </label>
  </p>
  <div data-gwseq-campagne-fields="cta" style="<?php echo $contenu['cta_active'] === '1' ? '' : 'display:none;'; ?>">
    <p>
      <label for="gwseq-sticky-bar-cta-libelle"><strong><?php esc_html_e('Libellé du bouton', 'gws-core'); ?></strong></label><br>
      <input type="text" class="regular-text" id="gwseq-sticky-bar-cta-libelle" name="_gwseq_sticky_bar_cta_libelle" value="<?php echo esc_attr($contenu['cta_libelle']); ?>" placeholder="<?php esc_attr_e('Ex. Je m’inscris', 'gws-core'); ?>">
    </p>
    <p>
      <label for="gwseq-sticky-bar-cta-url"><strong><?php esc_html_e('Lien du bouton', 'gws-core'); ?></strong></label><br>
      <input type="url" class="widefat" id="gwseq-sticky-bar-cta-url" name="_gwseq_sticky_bar_cta_url" value="<?php echo esc_attr($contenu['cta_url']); ?>" placeholder="https://www.votresite.fr/votre-page">
      <br><span class="description"><?php esc_html_e('Saisissez l\'URL complète, avec https://', 'gws-core'); ?></span>
    </p>
  </div>
  <?php
}

function gwseq_render_sticky_bar_apparence_box($post) {
  $apparence = gwseq_get_sticky_bar_apparence($post->ID);
  ?>
  <p>
    <?php foreach (gwseq_campagne_style_mode_options() as $key => $label) : ?>
      <label style="display:block;margin-bottom:4px;">
        <input type="radio" name="_gwseq_sticky_bar_style_mode" value="<?php echo esc_attr($key); ?>" <?php checked($apparence['style_mode'], $key); ?>>
        <?php echo esc_html($label); ?>
      </label>
    <?php endforeach; ?>
  </p>
  <div data-gwseq-campagne-fields="style-custom" style="<?php echo $apparence['style_mode'] === 'custom' ? '' : 'display:none;'; ?>">
    <p>
      <label for="gwseq-sticky-bar-couleur-fond"><?php esc_html_e('Couleur de fond', 'gws-core'); ?></label>
      <input type="text" class="gwseq-color-field" id="gwseq-sticky-bar-couleur-fond" name="_gwseq_sticky_bar_couleur_fond" value="<?php echo esc_attr($apparence['couleur_fond']); ?>" placeholder="#1a1a1a">
    </p>
    <p>
      <label for="gwseq-sticky-bar-couleur-texte"><?php esc_html_e('Couleur du texte', 'gws-core'); ?></label>
      <input type="text" class="gwseq-color-field" id="gwseq-sticky-bar-couleur-texte" name="_gwseq_sticky_bar_couleur_texte" value="<?php echo esc_attr($apparence['couleur_texte']); ?>" placeholder="#ffffff">
    </p>
    <p>
      <label for="gwseq-sticky-bar-couleur-cta"><?php esc_html_e('Couleur du bouton (CTA)', 'gws-core'); ?></label>
      <input type="text" class="gwseq-color-field" id="gwseq-sticky-bar-couleur-cta" name="_gwseq_sticky_bar_couleur_cta" value="<?php echo esc_attr($apparence['couleur_cta']); ?>" placeholder="#1d4ed8">
    </p>
    <p>
      <label for="gwseq-sticky-bar-couleur-cta-texte"><?php esc_html_e('Couleur du texte du bouton', 'gws-core'); ?></label>
      <input type="text" class="gwseq-color-field" id="gwseq-sticky-bar-couleur-cta-texte" name="_gwseq_sticky_bar_couleur_cta_texte" value="<?php echo esc_attr($apparence['couleur_cta_texte']); ?>" placeholder="#ffffff">
    </p>
    <p class="description"><?php esc_html_e('Pas d’image de fond pour la Sticky bar.', 'gws-core'); ?></p>
  </div>
  <p>
    <label for="gwseq-sticky-bar-position"><strong><?php esc_html_e('Position', 'gws-core'); ?></strong></label><br>
    <select id="gwseq-sticky-bar-position" name="_gwseq_sticky_bar_position">
      <?php foreach (gwseq_sticky_bar_position_options() as $key => $label) : ?>
        <option value="<?php echo esc_attr($key); ?>" <?php selected($apparence['position'], $key); ?>><?php echo esc_html($label); ?></option>
      <?php endforeach; ?>
    </select>
  </p>
  <p>
    <label>
      <input type="checkbox" name="_gwseq_sticky_bar_fermable" value="1" <?php checked($apparence['fermable'], '1'); ?>>
      <?php esc_html_e('Le visiteur peut fermer la barre', 'gws-core'); ?>
    </label>
  </p>
  <p class="description"><?php esc_html_e('Aucune animation, police ou mise en forme supplémentaire n’est configurable ici : ces éléments relèvent du thème du site.', 'gws-core'); ?></p>
  <?php
}

function gwseq_render_sticky_bar_diffusion_box($post) {
  gwseq_render_campagne_diffusion_fields('sticky_bar', gwseq_get_sticky_bar_diffusion($post->ID));
}

function gwseq_render_sticky_bar_preview_box($post) {
  gwseq_render_campagne_preview_panel('sticky_bar');
}

function gwseq_save_sticky_bar_meta($post_id) {
  if (!isset($_POST[GWSEQ_STICKY_BAR_NONCE_FIELD]) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[GWSEQ_STICKY_BAR_NONCE_FIELD])), GWSEQ_STICKY_BAR_NONCE_ACTION)) return;
  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
  if (function_exists('wp_is_post_revision') && wp_is_post_revision($post_id)) return;
  if (!current_user_can('edit_post', $post_id)) return;

  gwseq_set_sticky_bar_contenu($post_id, $_POST);
  gwseq_set_sticky_bar_apparence($post_id, $_POST);
  gwseq_set_sticky_bar_diffusion($post_id, $_POST);
}
add_action('save_post_' . GWSEQ_CPT_STICKY_BAR, 'gwseq_save_sticky_bar_meta');

/* -------------------------------------------------------------------------------------------
 * Présentation de l'écran.
 * ----------------------------------------------------------------------------------------- */

function gwseq_disable_block_editor_for_sticky_bar($use_block_editor, $post_type) {
  if ($post_type === GWSEQ_CPT_STICKY_BAR) return false;
  return $use_block_editor;
}
add_filter('use_block_editor_for_post_type', 'gwseq_disable_block_editor_for_sticky_bar', 10, 2);

function gwseq_sticky_bar_title_placeholder($title, $post) {
  if ($post && $post->post_type === GWSEQ_CPT_STICKY_BAR) {
    return __('Nom interne de la sticky bar (jamais affiché sur le site)', 'gws-core');
  }
  return $title;
}
add_filter('enter_title_here', 'gwseq_sticky_bar_title_placeholder', 10, 2);

/* -------------------------------------------------------------------------------------------
 * Liste d'administration (§Q) : Nom | État | Période | Ciblage | Ordre.
 * ----------------------------------------------------------------------------------------- */

function gwseq_sticky_bar_admin_columns($columns) {
  $new = array();
  foreach ($columns as $key => $label) {
    if ($key === 'date') continue;
    if ($key === 'title') { $new[$key] = __('Nom', 'gws-core'); continue; }
    $new[$key] = $label;
  }
  $new['gwseq_campagne_etat'] = __('État', 'gws-core');
  $new['gwseq_campagne_periode'] = __('Période', 'gws-core');
  $new['gwseq_campagne_ciblage'] = __('Ciblage', 'gws-core');
  $new['gwseq_campagne_ordre'] = __('Ordre', 'gws-core');
  return $new;
}
add_filter('manage_' . GWSEQ_CPT_STICKY_BAR . '_posts_columns', 'gwseq_sticky_bar_admin_columns');

function gwseq_sticky_bar_admin_column_content($column, $post_id) {
  if ($column === 'gwseq_campagne_etat') {
    echo esc_html(gwseq_campagne_statut_options()[gwseq_get_sticky_bar_diffusion($post_id)['statut']] ?? '—');
  } elseif ($column === 'gwseq_campagne_periode') {
    echo esc_html(gwseq_campagne_periode_label(gwseq_get_sticky_bar_diffusion($post_id)));
  } elseif ($column === 'gwseq_campagne_ciblage') {
    echo esc_html(gwseq_campagne_ciblage_label(gwseq_get_sticky_bar_diffusion($post_id)));
  } elseif ($column === 'gwseq_campagne_ordre') {
    echo (int) get_post_field('menu_order', $post_id);
  }
}
add_action('manage_' . GWSEQ_CPT_STICKY_BAR . '_posts_custom_column', 'gwseq_sticky_bar_admin_column_content', 10, 2);

/* -------------------------------------------------------------------------------------------
 * Assets admin.
 * ----------------------------------------------------------------------------------------- */

function gwseq_enqueue_sticky_bar_admin_assets($hook) {
  if (!in_array($hook, array('post.php', 'post-new.php'), true)) return;
  $screen = function_exists('get_current_screen') ? get_current_screen() : null;
  if (!$screen || $screen->post_type !== GWSEQ_CPT_STICKY_BAR) return;

  wp_enqueue_style('gwseq-campagnes-admin', GWSEQ_MODULE_URL . 'assets/campagnes-admin.css', array(), GWSEQ_MODULE_VERSION);
  wp_enqueue_script('gwseq-campagnes-admin', GWSEQ_MODULE_URL . 'assets/campagnes-admin.js', array(), GWSEQ_MODULE_VERSION, true);
  wp_localize_script('gwseq-campagnes-admin', 'gwseqCampagnePreview', array(
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'action' => 'gwseq_preview_sticky_bar',
    'nonce' => wp_create_nonce(GWSEQ_STICKY_BAR_PREVIEW_NONCE_ACTION),
    'formSelector' => '#post',
    'previewSelector' => '[data-gwseq-campagne-preview-frame]',
  ));
}
add_action('admin_enqueue_scripts', 'gwseq_enqueue_sticky_bar_admin_assets');

<?php
// Rubrique "Conseils aux dirigeants" — page hub + 14 pages Conseil, entièrement additive.
// Aucune fonction de ce fichier ne modifie un mécanisme existant (Diagnostic, pages
// d'expertise, page-fields.php, blocks.php, seo.php...) : nouvelles clés de métadonnées,
// nouveau fichier de configuration, nouveaux gabarits de page dédiés.
//
// Principe "insert-only" (même principe que spa_seed_diagnostic_page() et le constat de
// l'incident du 2.3.1 documenté dans inc/migration-tool.php) : chaque page n'est créée
// qu'une seule fois, en un seul wp_insert_post() qui fixe déjà son contenu et ses réglages.
// config/conseils.json n'est relu qu'au moment de cette création, jamais après : une fois
// la page créée, post_content (WordPress) est la seule source de vérité du texte, et les
// métadonnées "_spa_conseil_*" (WordPress) sont la seule source de vérité des réglages
// structurels. Modifier ce fichier JSON plus tard n'a donc aucun effet sur une page déjà créée.
//
// Le lien "Conseils aux dirigeants" n'est volontairement pas ajouté à template-parts/site-
// header.php dans cette livraison : voir CHANGELOG.md pour le changement exact à appliquer
// lors de l'activation future dans le menu.

// --- Configuration structurelle (lue uniquement à la création d'une page, jamais après) ---
function spa_conseils_config() {
  static $config = null;
  if ($config !== null) return $config;
  $file = get_template_directory() . '/config/conseils.json';
  $config = file_exists($file) ? json_decode(file_get_contents($file), true) : array();
  return is_array($config) ? $config : array();
}

function spa_conseils_slugs() {
  return array_values(array_diff(array_keys(spa_conseils_config()), array('hub')));
}

function spa_conseils_categories_order() {
  return array('Trésorerie et dettes', 'Banques et créanciers', 'Agir avant la procédure collective', 'Redressement et liquidation');
}

const SPA_CONSEIL_TEMPLATE = 'page-templates/conseil-dirigeant.php';
const SPA_CONSEILS_HUB_TEMPLATE = 'page-templates/conseils-hub.php';
const SPA_CONSEILS_HUB_SLUG = 'conseils-aux-dirigeants';

// Liste des slugs Conseil exposée à conseils.js, pour que le tracking reconnaisse
// automatiquement un lien Conseil -> Conseil (conseil_related_click) sans tableau codé en dur
// côté JS — se met à jour d'elle-même à mesure que de nouveaux Conseils sont ajoutés.
// Enregistré après spa_assets() (inc/setup.php, priorité par défaut 10) pour que le script
// 'spa-conseils' soit déjà enregistré au moment de le localiser, sans modifier inc/setup.php.
function spa_localize_conseils_slugs() {
  if (!wp_script_is('spa-conseils', 'enqueued')) return;
  wp_localize_script('spa-conseils', 'spaConseilsSlugs', spa_conseils_slugs());
}
add_action('wp_enqueue_scripts', 'spa_localize_conseils_slugs', 20);

// =========================================================================================
// Blocs Gutenberg natifs : construction du contenu éditorial initial (post_content)
// =========================================================================================

function spa_conseil_block_heading($text, $level = 2) {
  $level = (int) $level;
  return '<!-- wp:heading {"level":' . $level . '} --><h' . $level . '>' . $text . '</h' . $level . '><!-- /wp:heading -->';
}
function spa_conseil_block_paragraph($text) {
  return '<!-- wp:paragraph --><p>' . $text . '</p><!-- /wp:paragraph -->';
}
function spa_conseil_block_list($items) {
  $html = '<!-- wp:list --><ul>';
  foreach ($items as $item) $html .= '<li>' . $item . '</li>';
  $html .= '</ul><!-- /wp:list -->';
  return $html;
}
// Lien de rebond contextuel — classe dédiée à cette rubrique (.conseil-resource-link,
// page-conseils.css). N'utilise pas le bloc spa/resource-link existant (inc/blocks.php) :
// celui-ci n'est actuellement inséré dans aucune page publiée du site, et son icône n'a pas
// de largeur/hauteur explicite dans la classe .inline-resource qu'il produit — un même souci
// que celui déjà signalé pour .definition-link. Plutôt que de dépendre d'un composant partagé
// jamais éprouvé en production, cette rubrique a sa propre règle CSS, correcte par construction.
function spa_conseil_block_resource_link($title, $description, $slug, $icon = 'info') {
  $url = home_url('/' . trim($slug, '/') . '/');
  $html = '<a class="conseil-resource-link" href="' . esc_url($url) . '">' . spa_icon($icon) . '<span><strong>' . esc_html($title) . '</strong><small>' . esc_html($description) . '</small></span><b>→</b></a>';
  return '<!-- wp:html -->' . $html . '<!-- /wp:html -->';
}
// Encarts de mise en avant Diagnostic / Contact : classes dédiées à cette rubrique
// (.conseil-diagnostic-cta / .conseil-contact-cta), définies dans page-conseils.css.
// Insérés via un bloc "HTML personnalisé" natif de l'éditeur (core/html) : aucun nouveau
// bloc Gutenberg à enregistrer, et le texte reste modifiable directement dans l'éditeur.
function spa_conseil_block_diagnostic_cta($lead, $note, $button = 'Faire le diagnostic') {
  $html = '<div class="conseil-diagnostic-cta"><div><p>' . esc_html($lead) . '</p><p class="conseil-cta-note">' . esc_html($note) . '</p></div>';
  $html .= '<a class="btn" href="' . esc_url(home_url('/diagnostic-entreprise-en-difficulte/')) . '" data-conseil-cta="diagnostic">' . esc_html($button) . ' <b>→</b></a></div>';
  return '<!-- wp:html -->' . $html . '<!-- /wp:html -->';
}
function spa_conseil_block_contact_cta($lead, $note, $button = 'Échanger avec le cabinet', $primary = false) {
  $class = 'conseil-contact-cta' . ($primary ? ' is-primary' : '');
  $html = '<div class="' . $class . '"><div><p>' . esc_html($lead) . '</p><p class="conseil-cta-note">' . esc_html($note) . '</p></div>';
  $html .= '<a class="btn btn-dark" href="#contact" data-conseil-cta="contact">' . esc_html($button) . ' <b>→</b></a></div>';
  return '<!-- wp:html -->' . $html . '<!-- /wp:html -->';
}

require get_template_directory() . '/inc/conseils-content.php';

// =========================================================================================
// Création des pages (insert-only)
// =========================================================================================

// Renseigne le title/meta description au moment du seed initial d'une page (hub ou Conseil),
// jamais relu ensuite. Si Yoast est actif, écrit directement ses metas natives — Yoast les lit
// telles quelles pour ces pages sans qu'aucun appel à son API ne soit nécessaire, c'est le
// mécanisme documenté par lequel un plugin/thème tiers renseigne un title/meta description Yoast
// de façon programmatique. Si aucun plugin SEO n'est actif, on garde le fallback existant
// (_spa_seo_title / _spa_seo_description, inc/seo.php). Si un autre plugin SEO est actif
// (RankMath, SEOPress, AIOSEO), aucune meta n'est écrite ici — comportement déjà existant avant
// cette correction, seul le cas Yoast (le plugin réellement utilisé en production) est traité.
function spa_seed_conseil_seo_meta($post_id, $seo_title, $seo_description) {
  if (defined('WPSEO_VERSION')) {
    if (!empty($seo_title)) update_post_meta($post_id, '_yoast_wpseo_title', $seo_title);
    if (!empty($seo_description)) update_post_meta($post_id, '_yoast_wpseo_metadesc', $seo_description);
  } elseif (!spa_has_seo_plugin()) {
    if (!empty($seo_title)) update_post_meta($post_id, '_spa_seo_title', $seo_title);
    if (!empty($seo_description)) update_post_meta($post_id, '_spa_seo_description', $seo_description);
  }
}

function spa_seed_conseils_pages() {
  if (get_option('spa_conseils_pages_version') === '1.0.0') return;
  $config = spa_conseils_config();

  // --- Page hub ---
  $hub = get_page_by_path(SPA_CONSEILS_HUB_SLUG);
  if (!$hub) {
    $hub_id = wp_insert_post(array(
      'post_title' => 'Conseils aux dirigeants',
      'post_name' => SPA_CONSEILS_HUB_SLUG,
      'post_type' => 'page',
      'post_status' => 'publish',
      'post_content' => '',
      'meta_input' => array('_wp_page_template' => SPA_CONSEILS_HUB_TEMPLATE),
    ));
    if (!is_wp_error($hub_id) && $hub_id) {
      $hub_defaults = isset($config['hub']) ? $config['hub'] : array();
      spa_seed_conseil_seo_meta($hub_id, $hub_defaults['seo_title'] ?? '', $hub_defaults['seo_description'] ?? '');
    }
  }

  // --- Les 14 pages Conseil ---
  foreach (spa_conseils_slugs() as $slug) {
    if (get_page_by_path($slug)) continue;
    $data = $config[$slug];
    $post_id = wp_insert_post(array(
      'post_title' => $data['title'],
      'post_name' => $slug,
      'post_type' => 'page',
      'post_status' => 'publish',
      'post_content' => spa_conseil_body_html($slug),
      'menu_order' => isset($data['order']) ? (int) $data['order'] : 0,
      'meta_input' => array('_wp_page_template' => SPA_CONSEIL_TEMPLATE),
    ));
    if (is_wp_error($post_id) || !$post_id) continue;

    spa_seed_conseil_seo_meta($post_id, $data['seo_title'] ?? '', $data['seo_description'] ?? '');

    update_post_meta($post_id, '_spa_conseil_category', $data['category']);
    update_post_meta($post_id, '_spa_conseil_short_title', $data['short_title']);
    update_post_meta($post_id, '_spa_conseil_h1_main', $data['h1_main'] ?? $data['title']);
    update_post_meta($post_id, '_spa_conseil_h1_accent', $data['h1_accent'] ?? '');
    update_post_meta($post_id, '_spa_conseil_hero_icon', $data['hero_icon']);
    update_post_meta($post_id, '_spa_conseil_hero_title', $data['hero_title']);
    update_post_meta($post_id, '_spa_conseil_hero_text', $data['hero_text']);
    update_post_meta($post_id, '_spa_conseil_aside_kicker', $data['aside_kicker']);
    update_post_meta($post_id, '_spa_conseil_aside_title', $data['aside_title']);
    update_post_meta($post_id, '_spa_conseil_aside_text', $data['aside_text']);
    foreach ($data['related'] as $i => $item) {
      if ($i > 2) break;
      update_post_meta($post_id, '_spa_conseil_related_label_' . $i, $item['label']);
      update_post_meta($post_id, '_spa_conseil_related_url_' . $i, home_url('/' . trim($item['slug'], '/') . '/'));
    }
  }

  // Le flag de seed n'est posé qu'après vérification effective que le hub et les 14 pages
  // Conseil existent bien — si une création a échoué (wp_insert_post en erreur), le flag reste
  // absent et le prochain passage sur 'init' retente uniquement les pages manquantes (le principe
  // insert-only ci-dessus garantit qu'une page déjà créée n'est jamais retouchée).
  if (!get_page_by_path(SPA_CONSEILS_HUB_SLUG)) return;
  foreach (spa_conseils_slugs() as $slug) {
    if (!get_page_by_path($slug)) return;
  }
  update_option('spa_conseils_pages_version', '1.0.0', false);
}
add_action('init', 'spa_seed_conseils_pages', 27);

// =========================================================================================
// Meta-box "Réglages du Conseil" — visible uniquement sur les pages utilisant le gabarit
// Conseil. Clés de métadonnées et nonce entièrement distincts de spa_add_page_settings_box()
// (inc/page-fields.php) : aucun risque de collision avec les réglages des pages d'expertise.
// =========================================================================================

function spa_add_conseil_settings_box() {
  global $post;
  if (!$post || get_page_template_slug($post->ID) !== SPA_CONSEIL_TEMPLATE) return;
  add_meta_box('spa-conseil-settings', 'Réglages du Conseil', 'spa_render_conseil_settings_box', 'page', 'normal', 'high');
}
add_action('add_meta_boxes', 'spa_add_conseil_settings_box');

function spa_conseil_value($post_id, $key, $default = '') {
  $value = get_post_meta($post_id, $key, true);
  return $value !== '' ? $value : $default;
}

function spa_render_conseil_settings_box($post) {
  wp_nonce_field('spa_save_conseil_settings', 'spa_conseil_settings_nonce');
  echo '<p><label><strong>Catégorie (affichée sur la page hub et en kicker)</strong></label><br>';
  echo '<select name="spa_conseil_category" class="widefat">';
  foreach (spa_conseils_categories_order() as $cat) {
    $selected = spa_conseil_value($post->ID, '_spa_conseil_category') === $cat ? ' selected' : '';
    echo '<option value="' . esc_attr($cat) . '"' . $selected . '>' . esc_html($cat) . '</option>';
  }
  echo '</select></p>';
  echo '<p><label><strong>Fil d’Ariane (libellé court)</strong></label><input class="widefat" type="text" name="spa_conseil_short_title" value="' . esc_attr(spa_conseil_value($post->ID, '_spa_conseil_short_title')) . '"></p>';
  echo '<hr><h3>Titre principal (H1)</h3>';
  echo '<p><label>Partie noire</label><input class="widefat" type="text" name="spa_conseil_h1_main" value="' . esc_attr(spa_conseil_value($post->ID, '_spa_conseil_h1_main', get_the_title($post->ID))) . '"></p>';
  echo '<p><label>Partie accentuée (saumon/italique)</label><input class="widefat" type="text" name="spa_conseil_h1_accent" value="' . esc_attr(spa_conseil_value($post->ID, '_spa_conseil_h1_accent')) . '"><small>Laisser vide pour un H1 en une seule couleur. Le texte affiché est la concaténation des deux parties, séparées par un espace — leur somme doit rester identique au titre de la page.</small></p>';
  echo '<hr><h3>Encart du hero</h3>';
  echo '<p><label>Icône</label><input class="widefat" type="text" name="spa_conseil_hero_icon" value="' . esc_attr(spa_conseil_value($post->ID, '_spa_conseil_hero_icon', 'info')) . '"><small>Nom d’icône du sprite existant (inc/icons.php), ex. schedule, gavel, account_balance…</small></p>';
  echo '<p><label>Titre</label><input class="widefat" type="text" name="spa_conseil_hero_title" value="' . esc_attr(spa_conseil_value($post->ID, '_spa_conseil_hero_title')) . '"></p>';
  echo '<p><label>Texte</label><textarea class="widefat" rows="2" name="spa_conseil_hero_text">' . esc_textarea(spa_conseil_value($post->ID, '_spa_conseil_hero_text')) . '</textarea></p>';
  echo '<hr><h3>Encart de contact latéral</h3>';
  echo '<p><label>Petit titre</label><input class="widefat" type="text" name="spa_conseil_aside_kicker" value="' . esc_attr(spa_conseil_value($post->ID, '_spa_conseil_aside_kicker')) . '"></p>';
  echo '<p><label>Titre</label><input class="widefat" type="text" name="spa_conseil_aside_title" value="' . esc_attr(spa_conseil_value($post->ID, '_spa_conseil_aside_title')) . '"></p>';
  echo '<p><label>Texte</label><textarea class="widefat" rows="2" name="spa_conseil_aside_text">' . esc_textarea(spa_conseil_value($post->ID, '_spa_conseil_aside_text')) . '</textarea></p>';
  echo '<hr><h3>Pour aller plus loin (3 liens)</h3>';
  for ($i = 0; $i < 3; $i++) {
    echo '<fieldset style="padding:12px 14px;margin:14px 0;border:1px solid #c3c4c7"><legend><strong>Lien ' . ($i + 1) . '</strong></legend>';
    echo '<p><label>Libellé</label><input class="widefat" type="text" name="spa_conseil_related_label_' . $i . '" value="' . esc_attr(spa_conseil_value($post->ID, '_spa_conseil_related_label_' . $i)) . '"></p>';
    echo '<p><label>Adresse</label><input class="widefat" type="url" name="spa_conseil_related_url_' . $i . '" value="' . esc_attr(spa_conseil_value($post->ID, '_spa_conseil_related_url_' . $i)) . '"></p></fieldset>';
  }
}

function spa_save_conseil_settings($post_id) {
  if (!isset($_POST['spa_conseil_settings_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['spa_conseil_settings_nonce'])), 'spa_save_conseil_settings')) return;
  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
  if (!current_user_can('edit_post', $post_id)) return;
  if (get_page_template_slug($post_id) !== SPA_CONSEIL_TEMPLATE) return;
  $text_fields = array('spa_conseil_category', 'spa_conseil_short_title', 'spa_conseil_h1_main', 'spa_conseil_h1_accent', 'spa_conseil_hero_icon', 'spa_conseil_hero_title', 'spa_conseil_hero_text', 'spa_conseil_aside_kicker', 'spa_conseil_aside_title', 'spa_conseil_aside_text');
  for ($i = 0; $i < 3; $i++) $text_fields[] = 'spa_conseil_related_label_' . $i;
  foreach ($text_fields as $field) {
    $value = isset($_POST[$field]) ? sanitize_textarea_field(wp_unslash($_POST[$field])) : '';
    update_post_meta($post_id, '_' . $field, $value);
  }
  for ($i = 0; $i < 3; $i++) {
    $field = 'spa_conseil_related_url_' . $i;
    $value = isset($_POST[$field]) ? esc_url_raw(wp_unslash($_POST[$field])) : '';
    update_post_meta($post_id, '_' . $field, $value);
  }
}
add_action('save_post_page', 'spa_save_conseil_settings');

// =========================================================================================
// Requête du hub : toute page publiée utilisant le gabarit Conseil, groupée par catégorie.
// Ajouter un futur Conseil = créer une page, lui assigner ce gabarit : elle apparaît ici
// automatiquement, sans toucher à aucun fichier.
// =========================================================================================

function spa_conseils_by_category() {
  $pages = get_posts(array(
    'post_type' => 'page',
    'post_status' => 'publish',
    'meta_key' => '_wp_page_template',
    'meta_value' => SPA_CONSEIL_TEMPLATE,
    'orderby' => 'menu_order title',
    'order' => 'ASC',
    'numberposts' => -1,
  ));
  $grouped = array();
  foreach ($pages as $page) {
    $category = spa_conseil_value($page->ID, '_spa_conseil_category', 'Autres situations');
    if (!isset($grouped[$category])) $grouped[$category] = array();
    $grouped[$category][] = $page;
  }
  $ordered = array();
  foreach (spa_conseils_categories_order() as $cat) {
    if (isset($grouped[$cat])) { $ordered[$cat] = $grouped[$cat]; unset($grouped[$cat]); }
  }
  foreach ($grouped as $cat => $items) $ordered[$cat] = $items; // catégories imprévues, ajoutées après coup
  return $ordered;
}

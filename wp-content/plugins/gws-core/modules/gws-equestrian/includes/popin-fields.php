<?php
/**
 * Pop-in — fiche structurée (post type `gwseq_popin`, voir includes/post-types.php), organisée en
 * quatre sections lisibles pour un utilisateur non technique : Contenu (ce que je veux dire),
 * Apparence (à quoi cela ressemble), Déclenchement (quand cela apparaît — et à quelle fréquence,
 * les deux relevant du même "comportement par visiteur", contrairement à Diffusion qui décide si
 * la campagne existe du tout sur cette page à ce moment), Diffusion (où et quand la campagne est
 * active — statut, période, ciblage, partagés avec Sticky bar via includes/campagnes-shared.php).
 *
 * Pas de Gutenberg (gwseq_disable_block_editor_for_popin() plus bas), pas de page builder : le
 * texte accepte une mise en forme minimale (gras/italique/lien/liste) via l'éditeur "teeny" natif
 * de WordPress (voir gwseq_render_campagne_texte_editor() dans campagnes-shared.php), jamais un
 * éditeur par blocs ni un champ HTML libre.
 *
 * Nom interne = post_title, jamais affiché publiquement (le post type n'est pas public) — seul le
 * placeholder du champ Titre natif est adapté pour éviter toute ambiguïté avec le Titre AFFICHÉ de
 * la pop-in (`_gwseq_popin_titre`, un champ distinct de la section Contenu).
 *
 * Preview temps réel (§J) : gwseq_render_popin_markup() est la SEULE fonction qui produit le
 * balisage d'une pop-in, appelée à la fois par le point d'entrée AJAX de preview
 * (gwseq_ajax_preview_popin(), état de formulaire non enregistré) et par le rendu front réel
 * (gwseq_get_popin_config(), état enregistré — voir includes/campagnes-front.php) : aucun rendu
 * dupliqué, aucun risque de divergence entre les deux.
 */

if (!defined('ABSPATH')) exit;

const GWSEQ_POPIN_NONCE_ACTION = 'gwseq_save_popin_meta';
const GWSEQ_POPIN_NONCE_FIELD = 'gwseq_save_popin_meta_nonce';
const GWSEQ_POPIN_PREVIEW_NONCE_ACTION = 'gwseq_preview_popin';

const GWSEQ_POPIN_DELAI_SECONDES_MIN = 1;
const GWSEQ_POPIN_DELAI_SECONDES_MAX = 120;
const GWSEQ_POPIN_SCROLL_POURCENTAGE_MIN = 1;
const GWSEQ_POPIN_SCROLL_POURCENTAGE_MAX = 100;
const GWSEQ_POPIN_FREQUENCE_JOURS_MIN = 1;
const GWSEQ_POPIN_FREQUENCE_JOURS_MAX = 365;

/* -------------------------------------------------------------------------------------------
 * Options (valeurs techniques stables, libellés traduits et destinés à un professionnel du
 * cheval — jamais un vocabulaire de développeur, §E de la demande).
 * ----------------------------------------------------------------------------------------- */

function gwseq_popin_taille_options() {
  return array(
    'compact' => __('Compacte', 'gws-core'),
    'standard' => __('Standard', 'gws-core'),
    'large' => __('Large', 'gws-core'),
  );
}

function gwseq_popin_declenchement_mode_options() {
  return array(
    'immediate' => __('Immédiatement', 'gws-core'),
    'delay' => __('Après X secondes', 'gws-core'),
    'scroll' => __('Après X % de scroll', 'gws-core'),
    'exit_intent' => __('À l’intention de sortie', 'gws-core'),
  );
}

function gwseq_popin_frequence_mode_options() {
  return array(
    'every_visit' => __('À chaque visite', 'gws-core'),
    'session' => __('Une fois par session', 'gws-core'),
    'days' => __('Une fois tous les X jours', 'gws-core'),
  );
}

/* -------------------------------------------------------------------------------------------
 * Meta (jamais exposées en REST — même choix que les autres objets du module).
 * ----------------------------------------------------------------------------------------- */

function gwseq_register_popin_meta() {
  $string_meta = array(
    '_gwseq_popin_titre', '_gwseq_popin_texte', '_gwseq_popin_cta_libelle', '_gwseq_popin_cta_url',
    '_gwseq_popin_style_mode', '_gwseq_popin_taille',
    '_gwseq_popin_couleur_fond', '_gwseq_popin_couleur_texte', '_gwseq_popin_couleur_cta', '_gwseq_popin_couleur_cta_texte',
    '_gwseq_popin_declenchement_mode', '_gwseq_popin_frequence_mode',
    '_gwseq_popin_statut',
    '_gwseq_popin_ciblage_mode',
  );
  foreach ($string_meta as $key) {
    register_post_meta(GWSEQ_CPT_POPIN, $key, array('single' => true, 'type' => 'string', 'show_in_rest' => false));
  }
  foreach (array('_gwseq_popin_cta_active') as $key) {
    register_post_meta(GWSEQ_CPT_POPIN, $key, array('single' => true, 'type' => 'string', 'show_in_rest' => false));
  }
  foreach (array('_gwseq_popin_image_id', '_gwseq_popin_image_fond_id', '_gwseq_popin_delai_secondes', '_gwseq_popin_scroll_pourcentage', '_gwseq_popin_frequence_jours', '_gwseq_popin_debut_ts', '_gwseq_popin_fin_ts') as $key) {
    register_post_meta(GWSEQ_CPT_POPIN, $key, array('single' => true, 'type' => 'integer', 'show_in_rest' => false));
  }
  register_post_meta(GWSEQ_CPT_POPIN, '_gwseq_popin_ciblage_cibles', array('single' => true, 'type' => 'array', 'show_in_rest' => false));
}
add_action('init', 'gwseq_register_popin_meta');

/* -------------------------------------------------------------------------------------------
 * Sanitation — fonctions pures, une par section.
 * ----------------------------------------------------------------------------------------- */

function gwseq_sanitize_popin_contenu_input($raw) {
  $raw = is_array($raw) ? $raw : array();
  $cta = gwseq_sanitize_campagne_cta_input($raw, '_gwseq_popin_');
  return array(
    'titre' => gws_core_field_sanitize('text', $raw['_gwseq_popin_titre'] ?? ''),
    'texte' => gwseq_sanitize_campagne_texte_input($raw['_gwseq_popin_texte'] ?? ''),
    'cta_active' => $cta['active'],
    'cta_libelle' => $cta['libelle'],
    'cta_url' => $cta['url'],
    'image_id' => gws_core_field_sanitize('attachment_id', $raw['_gwseq_popin_image_id'] ?? 0),
  );
}

/**
 * Les couleurs et l'image de fond ne sont conservées QUE si `style_mode === 'custom'` — le
 * serveur reste l'autorité (même discipline que le nettoyage de la précision "Autre" des Langues
 * de Membre) : repasser en "Style du site" nettoie systématiquement ces champs, même si d'anciennes
 * valeurs sont encore soumises dans le payload.
 */
function gwseq_sanitize_popin_apparence_input($raw) {
  $raw = is_array($raw) ? $raw : array();
  $style_mode = gwseq_sanitize_campagne_style_mode($raw['_gwseq_popin_style_mode'] ?? '');
  $taille = sanitize_key(wp_unslash($raw['_gwseq_popin_taille'] ?? ''));
  if (!array_key_exists($taille, gwseq_popin_taille_options())) $taille = 'standard';

  $result = array(
    'style_mode' => $style_mode,
    'taille' => $taille,
    'couleur_fond' => '', 'couleur_texte' => '', 'couleur_cta' => '', 'couleur_cta_texte' => '',
    'image_fond_id' => 0,
  );
  if ($style_mode === 'custom') {
    $result['couleur_fond'] = gwseq_sanitize_campagne_couleur($raw['_gwseq_popin_couleur_fond'] ?? '');
    $result['couleur_texte'] = gwseq_sanitize_campagne_couleur($raw['_gwseq_popin_couleur_texte'] ?? '');
    $result['couleur_cta'] = gwseq_sanitize_campagne_couleur($raw['_gwseq_popin_couleur_cta'] ?? '');
    $result['couleur_cta_texte'] = gwseq_sanitize_campagne_couleur($raw['_gwseq_popin_couleur_cta_texte'] ?? '');
    $result['image_fond_id'] = gws_core_field_sanitize('attachment_id', $raw['_gwseq_popin_image_fond_id'] ?? 0);
  }
  return $result;
}

function gwseq_sanitize_popin_bounded_int($raw_value, $min, $max, $default) {
  if (!is_numeric($raw_value)) return $default;
  $value = (int) $raw_value;
  if ($value < $min) return $min;
  if ($value > $max) return $max;
  return $value;
}

/**
 * Les champs numériques (délai/scroll/jours) ne sont conservés que pour le mode réellement
 * concerné — jamais une ancienne valeur "orpheline" d'un mode précédemment sélectionné. Bornes
 * serveur (§E) : jamais une valeur absurde, quel que soit ce qui est soumis.
 */
function gwseq_sanitize_popin_declenchement_input($raw) {
  $raw = is_array($raw) ? $raw : array();
  $mode = sanitize_key(wp_unslash($raw['_gwseq_popin_declenchement_mode'] ?? ''));
  if (!array_key_exists($mode, gwseq_popin_declenchement_mode_options())) $mode = 'immediate';

  $frequence_mode = sanitize_key(wp_unslash($raw['_gwseq_popin_frequence_mode'] ?? ''));
  if (!array_key_exists($frequence_mode, gwseq_popin_frequence_mode_options())) $frequence_mode = 'every_visit';

  return array(
    'mode' => $mode,
    'delai_secondes' => $mode === 'delay'
      ? gwseq_sanitize_popin_bounded_int($raw['_gwseq_popin_delai_secondes'] ?? '', GWSEQ_POPIN_DELAI_SECONDES_MIN, GWSEQ_POPIN_DELAI_SECONDES_MAX, 5)
      : 0,
    'scroll_pourcentage' => $mode === 'scroll'
      ? gwseq_sanitize_popin_bounded_int($raw['_gwseq_popin_scroll_pourcentage'] ?? '', GWSEQ_POPIN_SCROLL_POURCENTAGE_MIN, GWSEQ_POPIN_SCROLL_POURCENTAGE_MAX, 50)
      : 0,
    'frequence_mode' => $frequence_mode,
    'frequence_jours' => $frequence_mode === 'days'
      ? gwseq_sanitize_popin_bounded_int($raw['_gwseq_popin_frequence_jours'] ?? '', GWSEQ_POPIN_FREQUENCE_JOURS_MIN, GWSEQ_POPIN_FREQUENCE_JOURS_MAX, 7)
      : 0,
  );
}

function gwseq_sanitize_popin_diffusion_input($raw) {
  $raw = is_array($raw) ? $raw : array();
  $statut = sanitize_key(wp_unslash($raw['_gwseq_popin_statut'] ?? ''));
  if (!array_key_exists($statut, gwseq_campagne_statut_options())) $statut = 'inactive';

  $ciblage = gwseq_sanitize_campagne_ciblage_input(array(
    'ciblage_mode' => $raw['_gwseq_popin_ciblage_mode'] ?? '',
    'ciblage_cibles' => $raw['_gwseq_popin_ciblage_cibles'] ?? array(),
  ));

  return array(
    'statut' => $statut,
    'debut_ts' => gwseq_sanitize_campagne_datetime_input($raw['_gwseq_popin_debut'] ?? ''),
    'fin_ts' => gwseq_sanitize_campagne_datetime_input($raw['_gwseq_popin_fin'] ?? ''),
    'ciblage_mode' => $ciblage['mode'],
    'ciblage_cibles' => $ciblage['cibles'],
  );
}

/* -------------------------------------------------------------------------------------------
 * Écriture — fonctions métier pures (même architecture que gwseq_set_membre_identity() etc.).
 * ----------------------------------------------------------------------------------------- */

function gwseq_set_popin_contenu($post_id, $raw) {
  $post_id = (int) $post_id;
  if (!$post_id) return false;
  $c = gwseq_sanitize_popin_contenu_input($raw);
  update_post_meta($post_id, '_gwseq_popin_titre', $c['titre']);
  update_post_meta($post_id, '_gwseq_popin_texte', $c['texte']);
  update_post_meta($post_id, '_gwseq_popin_cta_active', $c['cta_active']);
  update_post_meta($post_id, '_gwseq_popin_cta_libelle', $c['cta_libelle']);
  update_post_meta($post_id, '_gwseq_popin_cta_url', $c['cta_url']);
  update_post_meta($post_id, '_gwseq_popin_image_id', $c['image_id']);
  return true;
}

function gwseq_set_popin_apparence($post_id, $raw) {
  $post_id = (int) $post_id;
  if (!$post_id) return false;
  $a = gwseq_sanitize_popin_apparence_input($raw);
  update_post_meta($post_id, '_gwseq_popin_style_mode', $a['style_mode']);
  update_post_meta($post_id, '_gwseq_popin_taille', $a['taille']);
  update_post_meta($post_id, '_gwseq_popin_couleur_fond', $a['couleur_fond']);
  update_post_meta($post_id, '_gwseq_popin_couleur_texte', $a['couleur_texte']);
  update_post_meta($post_id, '_gwseq_popin_couleur_cta', $a['couleur_cta']);
  update_post_meta($post_id, '_gwseq_popin_couleur_cta_texte', $a['couleur_cta_texte']);
  update_post_meta($post_id, '_gwseq_popin_image_fond_id', $a['image_fond_id']);
  return true;
}

function gwseq_set_popin_declenchement($post_id, $raw) {
  $post_id = (int) $post_id;
  if (!$post_id) return false;
  $d = gwseq_sanitize_popin_declenchement_input($raw);
  update_post_meta($post_id, '_gwseq_popin_declenchement_mode', $d['mode']);
  update_post_meta($post_id, '_gwseq_popin_delai_secondes', $d['delai_secondes']);
  update_post_meta($post_id, '_gwseq_popin_scroll_pourcentage', $d['scroll_pourcentage']);
  update_post_meta($post_id, '_gwseq_popin_frequence_mode', $d['frequence_mode']);
  update_post_meta($post_id, '_gwseq_popin_frequence_jours', $d['frequence_jours']);
  return true;
}

function gwseq_set_popin_diffusion($post_id, $raw) {
  $post_id = (int) $post_id;
  if (!$post_id) return false;
  $diff = gwseq_sanitize_popin_diffusion_input($raw);
  update_post_meta($post_id, '_gwseq_popin_statut', $diff['statut']);
  update_post_meta($post_id, '_gwseq_popin_debut_ts', $diff['debut_ts']);
  update_post_meta($post_id, '_gwseq_popin_fin_ts', $diff['fin_ts']);
  update_post_meta($post_id, '_gwseq_popin_ciblage_mode', $diff['ciblage_mode']);
  update_post_meta($post_id, '_gwseq_popin_ciblage_cibles', $diff['ciblage_cibles']);
  return true;
}

/* -------------------------------------------------------------------------------------------
 * Lecture — un tableau explicite et fermé par section (jamais get_post_meta($id) en bloc).
 * ----------------------------------------------------------------------------------------- */

function gwseq_get_popin_contenu($post_id) {
  return array(
    'titre' => get_post_meta($post_id, '_gwseq_popin_titre', true),
    'texte' => get_post_meta($post_id, '_gwseq_popin_texte', true),
    'cta_active' => get_post_meta($post_id, '_gwseq_popin_cta_active', true),
    'cta_libelle' => get_post_meta($post_id, '_gwseq_popin_cta_libelle', true),
    'cta_url' => get_post_meta($post_id, '_gwseq_popin_cta_url', true),
    'image_id' => (int) get_post_meta($post_id, '_gwseq_popin_image_id', true),
  );
}

function gwseq_get_popin_apparence($post_id) {
  $style_mode = get_post_meta($post_id, '_gwseq_popin_style_mode', true);
  $taille = get_post_meta($post_id, '_gwseq_popin_taille', true);
  return array(
    'style_mode' => $style_mode !== '' ? $style_mode : 'site',
    'taille' => $taille !== '' ? $taille : 'standard',
    'couleur_fond' => get_post_meta($post_id, '_gwseq_popin_couleur_fond', true),
    'couleur_texte' => get_post_meta($post_id, '_gwseq_popin_couleur_texte', true),
    'couleur_cta' => get_post_meta($post_id, '_gwseq_popin_couleur_cta', true),
    'couleur_cta_texte' => get_post_meta($post_id, '_gwseq_popin_couleur_cta_texte', true),
    'image_fond_id' => (int) get_post_meta($post_id, '_gwseq_popin_image_fond_id', true),
  );
}

function gwseq_get_popin_declenchement($post_id) {
  $mode = get_post_meta($post_id, '_gwseq_popin_declenchement_mode', true);
  $frequence_mode = get_post_meta($post_id, '_gwseq_popin_frequence_mode', true);
  return array(
    'mode' => $mode !== '' ? $mode : 'immediate',
    'delai_secondes' => (int) get_post_meta($post_id, '_gwseq_popin_delai_secondes', true),
    'scroll_pourcentage' => (int) get_post_meta($post_id, '_gwseq_popin_scroll_pourcentage', true),
    'frequence_mode' => $frequence_mode !== '' ? $frequence_mode : 'every_visit',
    'frequence_jours' => (int) get_post_meta($post_id, '_gwseq_popin_frequence_jours', true),
  );
}

function gwseq_get_popin_diffusion($post_id) {
  $statut = get_post_meta($post_id, '_gwseq_popin_statut', true);
  $ciblage_mode = get_post_meta($post_id, '_gwseq_popin_ciblage_mode', true);
  $cibles = get_post_meta($post_id, '_gwseq_popin_ciblage_cibles', true);
  return array(
    'statut' => $statut !== '' ? $statut : 'inactive',
    'debut_ts' => (int) get_post_meta($post_id, '_gwseq_popin_debut_ts', true),
    'fin_ts' => (int) get_post_meta($post_id, '_gwseq_popin_fin_ts', true),
    'ciblage_mode' => $ciblage_mode !== '' ? $ciblage_mode : 'all',
    'ciblage_cibles' => is_array($cibles) ? $cibles : array(),
  );
}

/* -------------------------------------------------------------------------------------------
 * Rendu — source UNIQUE, partagée entre le preview BO (AJAX) et le front réel (§J/§L).
 * ----------------------------------------------------------------------------------------- */

function gwseq_popin_config_defaults() {
  return array(
    'titre' => '', 'texte_html' => '', 'image_url' => '', 'image_alt' => '',
    'cta' => array('active' => '', 'libelle' => '', 'url' => ''),
    'style_mode' => 'site', 'couleur_fond' => '', 'couleur_texte' => '', 'couleur_cta' => '', 'couleur_cta_texte' => '',
    'image_fond_url' => '', 'taille' => 'standard',
  );
}

/**
 * Construit le tableau de configuration attendu par gwseq_render_popin_markup() à partir de
 * pièces DÉJÀ sanitisées (sections Contenu/Apparence) et d'identifiants d'attachement bruts —
 * SEULE fonction qui résout un attachment_id en URL, réutilisée par gwseq_get_popin_config()
 * (lecture en base, front réel) ET gwseq_ajax_preview_popin() (état de formulaire non enregistré) :
 * jamais deux résolutions indépendantes.
 */
function gwseq_build_popin_config($contenu, $apparence, $image_id, $image_fond_id) {
  $image_id = (int) $image_id;
  $image_fond_id = (int) $image_fond_id;
  return array(
    'titre' => $contenu['titre'],
    'texte_html' => $contenu['texte'],
    'image_url' => $image_id ? (string) (wp_get_attachment_image_url($image_id, 'large') ?: '') : '',
    'image_alt' => $image_id ? (string) get_post_meta($image_id, '_wp_attachment_image_alt', true) : '',
    'cta' => array('active' => $contenu['cta_active'], 'libelle' => $contenu['cta_libelle'], 'url' => $contenu['cta_url']),
    'style_mode' => $apparence['style_mode'],
    'couleur_fond' => $apparence['couleur_fond'],
    'couleur_texte' => $apparence['couleur_texte'],
    'couleur_cta' => $apparence['couleur_cta'],
    'couleur_cta_texte' => $apparence['couleur_cta_texte'],
    'image_fond_url' => $image_fond_id ? (string) (wp_get_attachment_image_url($image_fond_id, 'large') ?: '') : '',
    'taille' => $apparence['taille'],
  );
}

function gwseq_get_popin_config($post_id) {
  $contenu = gwseq_get_popin_contenu($post_id);
  $apparence = gwseq_get_popin_apparence($post_id);
  return gwseq_build_popin_config($contenu, $apparence, $contenu['image_id'], $apparence['image_fond_id']);
}

/**
 * Fonction PURE (aucune requête, aucun accès à $_POST) : la même sortie HTML, que l'appelant soit
 * le preview BO ou le rendu front réel. `$config['texte_html']` est supposé déjà passé par
 * gwseq_sanitize_campagne_texte_input() par l'appelant — re-filtré ici par sécurité (défense en
 * profondeur, jamais un coût réel : `wp_kses()` sur une chaîne déjà propre est un no-op).
 */
/**
 * `$extra_attrs` (ex. données de déclenchement/fréquence pour le front réel, voir
 * includes/campagnes-front.php) est fusionné dans les attributs du conteneur racine — jamais un
 * second passage de reconstruction du HTML : une seule fonction de rendu, un seul point de vérité
 * pour le balisage, que l'appelant soit le preview BO (sans attribut supplémentaire) ou le front.
 */
function gwseq_render_popin_markup($config, $extra_attrs = array()) {
  $config = array_merge(gwseq_popin_config_defaults(), is_array($config) ? $config : array());
  $config['cta'] = array_merge(array('active' => '', 'libelle' => '', 'url' => ''), is_array($config['cta']) ? $config['cta'] : array());

  $taille = array_key_exists($config['taille'], gwseq_popin_taille_options()) ? $config['taille'] : 'standard';
  $classes = array('gwseq-popin', 'gwseq-popin--' . $taille);

  $style_attr = '';
  if ($config['style_mode'] === 'custom') {
    $classes[] = 'gwseq-popin--custom';
    $style_parts = array();
    if ($config['couleur_fond'] !== '') $style_parts[] = '--gws-popin-bg:' . $config['couleur_fond'];
    if ($config['couleur_texte'] !== '') $style_parts[] = '--gws-popin-text:' . $config['couleur_texte'];
    if ($config['couleur_cta'] !== '') $style_parts[] = '--gws-popin-cta-bg:' . $config['couleur_cta'];
    if ($config['couleur_cta_texte'] !== '') $style_parts[] = '--gws-popin-cta-text:' . $config['couleur_cta_texte'];
    if ($config['image_fond_url'] !== '') $style_parts[] = "--gws-popin-bg-image:url('" . esc_url($config['image_fond_url']) . "')";
    if ($style_parts) $style_attr = ' style="' . esc_attr(implode(';', $style_parts)) . '"';
  }

  $extra_attr_str = '';
  foreach ((array) $extra_attrs as $attr_name => $attr_value) {
    $extra_attr_str .= ' ' . esc_attr($attr_name) . '="' . esc_attr($attr_value) . '"';
  }

  $html = '<div class="' . esc_attr(implode(' ', $classes)) . '" role="dialog" aria-modal="true" aria-label="' . esc_attr($config['titre'] !== '' ? $config['titre'] : __('Pop-in', 'gws-core')) . '"' . $style_attr . $extra_attr_str . '>';
  $html .= '<button type="button" class="gwseq-popin__close" aria-label="' . esc_attr__('Fermer', 'gws-core') . '">&times;</button>';
  $html .= '<div class="gwseq-popin__inner">';
  if ($config['image_url'] !== '') {
    $html .= '<img class="gwseq-popin__image" src="' . esc_url($config['image_url']) . '" alt="' . esc_attr($config['image_alt']) . '">';
  }
  if ($config['titre'] !== '') {
    $html .= '<h2 class="gwseq-popin__titre">' . esc_html($config['titre']) . '</h2>';
  }
  if ($config['texte_html'] !== '') {
    $html .= '<div class="gwseq-popin__texte">' . wp_kses($config['texte_html'], gwseq_campagne_texte_allowed_html()) . '</div>';
  }
  if (!empty($config['cta']['active']) && $config['cta']['libelle'] !== '' && $config['cta']['url'] !== '') {
    $html .= '<a class="gwseq-popin__cta" href="' . esc_url($config['cta']['url']) . '">' . esc_html($config['cta']['libelle']) . '</a>';
  }
  $html .= '</div></div>';
  return $html;
}

/* -------------------------------------------------------------------------------------------
 * AJAX preview (§J) — état de formulaire (non enregistré) -> mêmes sanitizers -> même fonction de
 * rendu -> HTML retourné. Jamais de markup dupliqué en JavaScript.
 * ----------------------------------------------------------------------------------------- */

function gwseq_ajax_preview_popin() {
  gwseq_verify_campagne_preview_request(GWSEQ_POPIN_PREVIEW_NONCE_ACTION);
  $contenu = gwseq_sanitize_popin_contenu_input($_POST);
  $apparence = gwseq_sanitize_popin_apparence_input($_POST);
  $config = gwseq_build_popin_config($contenu, $apparence, $contenu['image_id'], $apparence['image_fond_id']);
  wp_send_json_success(array('html' => gwseq_render_popin_markup($config)));
}
add_action('wp_ajax_gwseq_preview_popin', 'gwseq_ajax_preview_popin');

/* -------------------------------------------------------------------------------------------
 * Meta boxes et sauvegarde (glue WordPress) — quatre sections, jamais un système d'onglets couplé
 * (même choix architectural que Membre : trois/quatre meta boxes empilées restent lisibles sans
 * abstraction supplémentaire).
 * ----------------------------------------------------------------------------------------- */

function gwseq_add_popin_meta_boxes() {
  add_meta_box('gwseq-popin-contenu', __('Contenu', 'gws-core'), 'gwseq_render_popin_contenu_box', GWSEQ_CPT_POPIN, 'normal', 'high');
  add_meta_box('gwseq-popin-apparence', __('Apparence', 'gws-core'), 'gwseq_render_popin_apparence_box', GWSEQ_CPT_POPIN, 'normal', 'default');
  add_meta_box('gwseq-popin-declenchement', __('Déclenchement', 'gws-core'), 'gwseq_render_popin_declenchement_box', GWSEQ_CPT_POPIN, 'normal', 'default');
  add_meta_box('gwseq-popin-diffusion', __('Diffusion', 'gws-core'), 'gwseq_render_popin_diffusion_box', GWSEQ_CPT_POPIN, 'normal', 'default');
  add_meta_box('gwseq-popin-preview', __('Aperçu', 'gws-core'), 'gwseq_render_popin_preview_box', GWSEQ_CPT_POPIN, 'side', 'high');
}
add_action('add_meta_boxes_' . GWSEQ_CPT_POPIN, 'gwseq_add_popin_meta_boxes');

function gwseq_render_popin_image_picker($field_id, $field_name, $attachment_id, $button_label) {
  $attachment_id = (int) $attachment_id;
  $thumb_url = $attachment_id ? wp_get_attachment_image_url($attachment_id, 'thumbnail') : '';
  ?>
  <div class="gwseq-campagne-image-picker" data-gwseq-image-picker>
    <input type="hidden" id="<?php echo esc_attr($field_id); ?>" name="<?php echo esc_attr($field_name); ?>" value="<?php echo esc_attr($attachment_id ?: ''); ?>">
    <img class="gwseq-campagne-image-picker__preview" src="<?php echo esc_url($thumb_url); ?>" style="<?php echo $thumb_url ? '' : 'display:none;'; ?>" alt="">
    <p>
      <button type="button" class="button gwseq-campagne-image-picker__choose" data-gwseq-media-title="<?php echo esc_attr($button_label); ?>"><?php echo esc_html($button_label); ?></button>
      <button type="button" class="button gwseq-campagne-image-picker__remove" style="<?php echo $attachment_id ? '' : 'display:none;'; ?>"><?php esc_html_e('Retirer', 'gws-core'); ?></button>
    </p>
  </div>
  <?php
}

function gwseq_render_popin_contenu_box($post) {
  wp_nonce_field(GWSEQ_POPIN_NONCE_ACTION, GWSEQ_POPIN_NONCE_FIELD);
  $contenu = gwseq_get_popin_contenu($post->ID);
  ?>
  <p class="description"><?php esc_html_e('Le nom interne (titre de la fiche) sert uniquement au back-office : il n’est jamais affiché sur le site.', 'gws-core'); ?></p>
  <p>
    <label for="gwseq-popin-titre"><strong><?php esc_html_e('Titre affiché', 'gws-core'); ?></strong></label><br>
    <input type="text" class="widefat" id="gwseq-popin-titre" name="_gwseq_popin_titre" value="<?php echo esc_attr($contenu['titre']); ?>">
  </p>
  <p>
    <label for="gwseq-popin-texte"><strong><?php esc_html_e('Texte', 'gws-core'); ?></strong></label>
    <?php gwseq_render_campagne_texte_editor('gwseq-popin-texte', '_gwseq_popin_texte', $contenu['texte']); ?>
  </p>
  <p>
    <strong><?php esc_html_e('Image', 'gws-core'); ?></strong>
    <?php gwseq_render_popin_image_picker('gwseq-popin-image-id', '_gwseq_popin_image_id', $contenu['image_id'], __('Choisir une image', 'gws-core')); ?>
  </p>
  <p>
    <label>
      <input type="checkbox" name="_gwseq_popin_cta_active" value="1" <?php checked($contenu['cta_active'], '1'); ?>>
      <?php esc_html_e('Afficher un bouton d’appel à l’action (CTA)', 'gws-core'); ?>
    </label>
  </p>
  <div data-gwseq-campagne-fields="cta" style="<?php echo $contenu['cta_active'] === '1' ? '' : 'display:none;'; ?>">
    <p>
      <label for="gwseq-popin-cta-libelle"><strong><?php esc_html_e('Libellé du bouton', 'gws-core'); ?></strong></label><br>
      <input type="text" class="regular-text" id="gwseq-popin-cta-libelle" name="_gwseq_popin_cta_libelle" value="<?php echo esc_attr($contenu['cta_libelle']); ?>" placeholder="<?php esc_attr_e('Ex. En savoir plus', 'gws-core'); ?>">
    </p>
    <p>
      <label for="gwseq-popin-cta-url"><strong><?php esc_html_e('Lien du bouton', 'gws-core'); ?></strong></label><br>
      <input type="url" class="widefat" id="gwseq-popin-cta-url" name="_gwseq_popin_cta_url" value="<?php echo esc_attr($contenu['cta_url']); ?>" placeholder="https://www.votresite.fr/votre-page">
      <br><span class="description"><?php esc_html_e('Saisissez l\'URL complète, avec https://', 'gws-core'); ?></span>
    </p>
  </div>
  <?php
}

function gwseq_render_popin_apparence_box($post) {
  $apparence = gwseq_get_popin_apparence($post->ID);
  ?>
  <p>
    <?php foreach (gwseq_campagne_style_mode_options() as $key => $label) : ?>
      <label style="display:block;margin-bottom:4px;">
        <input type="radio" name="_gwseq_popin_style_mode" value="<?php echo esc_attr($key); ?>" <?php checked($apparence['style_mode'], $key); ?>>
        <?php echo esc_html($label); ?>
      </label>
    <?php endforeach; ?>
  </p>
  <div data-gwseq-campagne-fields="style-custom" style="<?php echo $apparence['style_mode'] === 'custom' ? '' : 'display:none;'; ?>">
    <p>
      <label for="gwseq-popin-couleur-fond"><?php esc_html_e('Couleur de fond', 'gws-core'); ?></label>
      <input type="text" class="gwseq-color-field" id="gwseq-popin-couleur-fond" name="_gwseq_popin_couleur_fond" value="<?php echo esc_attr($apparence['couleur_fond']); ?>" placeholder="#ffffff">
    </p>
    <p>
      <label for="gwseq-popin-couleur-texte"><?php esc_html_e('Couleur du texte', 'gws-core'); ?></label>
      <input type="text" class="gwseq-color-field" id="gwseq-popin-couleur-texte" name="_gwseq_popin_couleur_texte" value="<?php echo esc_attr($apparence['couleur_texte']); ?>" placeholder="#1a1a1a">
    </p>
    <p>
      <label for="gwseq-popin-couleur-cta"><?php esc_html_e('Couleur du bouton (CTA)', 'gws-core'); ?></label>
      <input type="text" class="gwseq-color-field" id="gwseq-popin-couleur-cta" name="_gwseq_popin_couleur_cta" value="<?php echo esc_attr($apparence['couleur_cta']); ?>" placeholder="#1d4ed8">
    </p>
    <p>
      <label for="gwseq-popin-couleur-cta-texte"><?php esc_html_e('Couleur du texte du bouton', 'gws-core'); ?></label>
      <input type="text" class="gwseq-color-field" id="gwseq-popin-couleur-cta-texte" name="_gwseq_popin_couleur_cta_texte" value="<?php echo esc_attr($apparence['couleur_cta_texte']); ?>" placeholder="#ffffff">
    </p>
    <p>
      <strong><?php esc_html_e('Image de fond (facultative)', 'gws-core'); ?></strong><br>
      <span class="description"><?php esc_html_e('Distincte de l’image de contenu ci-dessus : celle-ci s’affiche derrière toute la pop-in.', 'gws-core'); ?></span>
      <?php gwseq_render_popin_image_picker('gwseq-popin-image-fond-id', '_gwseq_popin_image_fond_id', $apparence['image_fond_id'], __('Choisir une image de fond', 'gws-core')); ?>
    </p>
  </div>
  <p>
    <label for="gwseq-popin-taille"><strong><?php esc_html_e('Taille', 'gws-core'); ?></strong></label><br>
    <select id="gwseq-popin-taille" name="_gwseq_popin_taille">
      <?php foreach (gwseq_popin_taille_options() as $key => $label) : ?>
        <option value="<?php echo esc_attr($key); ?>" <?php selected($apparence['taille'], $key); ?>><?php echo esc_html($label); ?></option>
      <?php endforeach; ?>
    </select>
  </p>
  <p class="description"><?php esc_html_e('La pop-in est toujours centrée. La police, les marges, les bordures et les ombres relèvent du thème du site.', 'gws-core'); ?></p>
  <?php
}

function gwseq_render_popin_declenchement_box($post) {
  $declenchement = gwseq_get_popin_declenchement($post->ID);
  ?>
  <p><strong><?php esc_html_e('Quand la pop-in apparaît', 'gws-core'); ?></strong></p>
  <p>
    <?php foreach (gwseq_popin_declenchement_mode_options() as $key => $label) : ?>
      <label style="display:block;margin-bottom:4px;">
        <input type="radio" name="_gwseq_popin_declenchement_mode" value="<?php echo esc_attr($key); ?>" <?php checked($declenchement['mode'], $key); ?>>
        <?php echo esc_html($label); ?>
      </label>
    <?php endforeach; ?>
  </p>
  <p data-gwseq-campagne-fields="declenchement-delay" style="<?php echo $declenchement['mode'] === 'delay' ? '' : 'display:none;'; ?>">
    <label for="gwseq-popin-delai-secondes"><?php esc_html_e('Après combien de secondes ?', 'gws-core'); ?></label>
    <input type="number" min="<?php echo esc_attr(GWSEQ_POPIN_DELAI_SECONDES_MIN); ?>" max="<?php echo esc_attr(GWSEQ_POPIN_DELAI_SECONDES_MAX); ?>" id="gwseq-popin-delai-secondes" name="_gwseq_popin_delai_secondes" value="<?php echo esc_attr($declenchement['delai_secondes'] ?: 5); ?>">
  </p>
  <p data-gwseq-campagne-fields="declenchement-scroll" style="<?php echo $declenchement['mode'] === 'scroll' ? '' : 'display:none;'; ?>">
    <label for="gwseq-popin-scroll-pourcentage"><?php esc_html_e('Après quel pourcentage de la page lue ?', 'gws-core'); ?></label>
    <input type="number" min="<?php echo esc_attr(GWSEQ_POPIN_SCROLL_POURCENTAGE_MIN); ?>" max="<?php echo esc_attr(GWSEQ_POPIN_SCROLL_POURCENTAGE_MAX); ?>" id="gwseq-popin-scroll-pourcentage" name="_gwseq_popin_scroll_pourcentage" value="<?php echo esc_attr($declenchement['scroll_pourcentage'] ?: 50); ?>"> %
  </p>
  <p data-gwseq-campagne-fields="declenchement-exit" style="<?php echo $declenchement['mode'] === 'exit_intent' ? '' : 'display:none;'; ?>" class="description">
    <?php esc_html_e('L’intention de sortie est disponible uniquement sur ordinateur. Pour cibler également les visiteurs mobiles, utilisez plutôt le délai ou le scroll.', 'gws-core'); ?>
  </p>

  <hr>
  <p><strong><?php esc_html_e('À quelle fréquence, pour un même visiteur', 'gws-core'); ?></strong></p>
  <p>
    <?php foreach (gwseq_popin_frequence_mode_options() as $key => $label) : ?>
      <label style="display:block;margin-bottom:4px;">
        <input type="radio" name="_gwseq_popin_frequence_mode" value="<?php echo esc_attr($key); ?>" <?php checked($declenchement['frequence_mode'], $key); ?>>
        <?php echo esc_html($label); ?>
      </label>
    <?php endforeach; ?>
  </p>
  <p data-gwseq-campagne-fields="frequence-days" style="<?php echo $declenchement['frequence_mode'] === 'days' ? '' : 'display:none;'; ?>">
    <label for="gwseq-popin-frequence-jours"><?php esc_html_e('Tous les combien de jours ?', 'gws-core'); ?></label>
    <input type="number" min="<?php echo esc_attr(GWSEQ_POPIN_FREQUENCE_JOURS_MIN); ?>" max="<?php echo esc_attr(GWSEQ_POPIN_FREQUENCE_JOURS_MAX); ?>" id="gwseq-popin-frequence-jours" name="_gwseq_popin_frequence_jours" value="<?php echo esc_attr($declenchement['frequence_jours'] ?: 7); ?>">
  </p>
  <p class="description"><?php esc_html_e('Fermer la pop-in compte comme une exposition : elle ne réapparaît pas immédiatement après avoir été fermée.', 'gws-core'); ?></p>
  <?php
}

function gwseq_render_popin_diffusion_box($post) {
  gwseq_render_campagne_diffusion_fields('popin', gwseq_get_popin_diffusion($post->ID));
}

function gwseq_render_popin_preview_box($post) {
  gwseq_render_campagne_preview_panel('popin');
}

function gwseq_save_popin_meta($post_id) {
  if (!isset($_POST[GWSEQ_POPIN_NONCE_FIELD]) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[GWSEQ_POPIN_NONCE_FIELD])), GWSEQ_POPIN_NONCE_ACTION)) return;
  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
  if (function_exists('wp_is_post_revision') && wp_is_post_revision($post_id)) return;
  if (!current_user_can('edit_post', $post_id)) return;

  gwseq_set_popin_contenu($post_id, $_POST);
  gwseq_set_popin_apparence($post_id, $_POST);
  gwseq_set_popin_declenchement($post_id, $_POST);
  gwseq_set_popin_diffusion($post_id, $_POST);
}
add_action('save_post_' . GWSEQ_CPT_POPIN, 'gwseq_save_popin_meta');

/* -------------------------------------------------------------------------------------------
 * Présentation de l'écran : pas de Gutenberg, placeholder du titre natif adapté.
 * ----------------------------------------------------------------------------------------- */

function gwseq_disable_block_editor_for_popin($use_block_editor, $post_type) {
  if ($post_type === GWSEQ_CPT_POPIN) return false;
  return $use_block_editor;
}
add_filter('use_block_editor_for_post_type', 'gwseq_disable_block_editor_for_popin', 10, 2);

function gwseq_popin_title_placeholder($title, $post) {
  if ($post && $post->post_type === GWSEQ_CPT_POPIN) {
    return __('Nom interne de la pop-in (jamais affiché sur le site)', 'gws-core');
  }
  return $title;
}
add_filter('enter_title_here', 'gwseq_popin_title_placeholder', 10, 2);

/* -------------------------------------------------------------------------------------------
 * Liste d'administration (§Q) : Nom | État | Période | Ciblage | Déclenchement | Ordre.
 * ----------------------------------------------------------------------------------------- */

function gwseq_popin_admin_columns($columns) {
  $new = array();
  foreach ($columns as $key => $label) {
    if ($key === 'date') continue;
    if ($key === 'title') { $new[$key] = __('Nom', 'gws-core'); continue; }
    $new[$key] = $label;
  }
  $new['gwseq_campagne_etat'] = __('État', 'gws-core');
  $new['gwseq_campagne_periode'] = __('Période', 'gws-core');
  $new['gwseq_campagne_ciblage'] = __('Ciblage', 'gws-core');
  $new['gwseq_popin_declenchement'] = __('Déclenchement', 'gws-core');
  $new['gwseq_campagne_ordre'] = __('Ordre', 'gws-core');
  return $new;
}
add_filter('manage_' . GWSEQ_CPT_POPIN . '_posts_columns', 'gwseq_popin_admin_columns');

function gwseq_popin_admin_column_content($column, $post_id) {
  if ($column === 'gwseq_campagne_etat') {
    echo esc_html(gwseq_campagne_statut_options()[gwseq_get_popin_diffusion($post_id)['statut']] ?? '—');
  } elseif ($column === 'gwseq_campagne_periode') {
    echo esc_html(gwseq_campagne_periode_label(gwseq_get_popin_diffusion($post_id)));
  } elseif ($column === 'gwseq_campagne_ciblage') {
    echo esc_html(gwseq_campagne_ciblage_label(gwseq_get_popin_diffusion($post_id)));
  } elseif ($column === 'gwseq_popin_declenchement') {
    echo esc_html(gwseq_popin_declenchement_mode_options()[gwseq_get_popin_declenchement($post_id)['mode']] ?? '—');
  } elseif ($column === 'gwseq_campagne_ordre') {
    echo (int) get_post_field('menu_order', $post_id);
  }
}
add_action('manage_' . GWSEQ_CPT_POPIN . '_posts_custom_column', 'gwseq_popin_admin_column_content', 10, 2);

/* -------------------------------------------------------------------------------------------
 * Assets admin : conditionnels + preview, uniquement sur l'écran d'édition d'une pop-in.
 * ----------------------------------------------------------------------------------------- */

function gwseq_enqueue_popin_admin_assets($hook) {
  if (!in_array($hook, array('post.php', 'post-new.php'), true)) return;
  $screen = function_exists('get_current_screen') ? get_current_screen() : null;
  if (!$screen || $screen->post_type !== GWSEQ_CPT_POPIN) return;

  wp_enqueue_media();
  wp_enqueue_style('gwseq-campagnes-admin', GWSEQ_MODULE_URL . 'assets/campagnes-admin.css', array(), GWSEQ_MODULE_VERSION);
  wp_enqueue_script('gwseq-campagnes-admin', GWSEQ_MODULE_URL . 'assets/campagnes-admin.js', array(), GWSEQ_MODULE_VERSION, true);
  wp_localize_script('gwseq-campagnes-admin', 'gwseqCampagnePreview', array(
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'action' => 'gwseq_preview_popin',
    'nonce' => wp_create_nonce(GWSEQ_POPIN_PREVIEW_NONCE_ACTION),
    'formSelector' => '#post',
    'previewSelector' => '[data-gwseq-campagne-preview-frame]',
  ));
}
add_action('admin_enqueue_scripts', 'gwseq_enqueue_popin_admin_assets');

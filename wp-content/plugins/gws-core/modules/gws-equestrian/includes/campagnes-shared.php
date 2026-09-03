<?php
/**
 * Mises en avant (Pop-in / Sticky bar) — briques RÉELLEMENT communes aux deux objets, et à elles
 * seules : sanitation des dates (fuseau horaire du site), du ciblage de contenus, des couleurs, du
 * CTA, du texte enrichi minimal, et la fonction d'éligibilité qui décide si une campagne doit
 * s'afficher sur la page en cours. Volontairement PAS de classe abstraite, PAS de schema builder,
 * PAS de moteur générique de campagnes : Pop-in et Sticky bar restent deux objets métier simples,
 * chacun avec son propre fichier (includes/popin-fields.php, includes/sticky-bar-fields.php) pour
 * tout ce qui leur est spécifique (déclenchement/fréquence pour Pop-in, position/fermeture pour
 * Sticky bar). Ce fichier n'existe QUE parce que dupliquer ces fonctions serait le genre de
 * duplication que ce projet évite systématiquement ailleurs — jamais pour anticiper un futur
 * troisième format hypothétique.
 *
 * Données structurées en meta individuelles sur chaque post type (jamais un blob JSON opaque) —
 * même discipline que Cheval/Membre. Aucune capacité WordPress technique ajoutée ici.
 */

if (!defined('ABSPATH')) exit;

/* -------------------------------------------------------------------------------------------
 * Style : "Style du site" par défaut, "Personnaliser" en option — jamais l'inverse.
 * ----------------------------------------------------------------------------------------- */

function gwseq_campagne_style_mode_options() {
  return array(
    'site' => __('Style du site (recommandé)', 'gws-core'),
    'custom' => __('Personnaliser', 'gws-core'),
  );
}

function gwseq_sanitize_campagne_style_mode($raw_value) {
  $mode = sanitize_key(wp_unslash((string) $raw_value));
  return array_key_exists($mode, gwseq_campagne_style_mode_options()) ? $mode : 'site';
}

/**
 * Couleur hexadécimale canonique via la fonction NATIVE WordPress (jamais une regex maison) —
 * `sanitize_hex_color()` renvoie la valeur telle quelle si elle est valide (#rgb ou #rrggbb), ou
 * `null` sinon. Une couleur invalide/absente n'est jamais stockée telle quelle : elle redevient
 * une chaîne vide (= "pas de couleur personnalisée pour ce champ", jamais une valeur inventée).
 */
function gwseq_sanitize_campagne_couleur($raw_value) {
  $value = sanitize_hex_color(wp_unslash((string) $raw_value));
  return $value !== null ? $value : '';
}

/* -------------------------------------------------------------------------------------------
 * CTA (bouton d'appel à l'action) — identique pour Pop-in et Sticky bar.
 * ----------------------------------------------------------------------------------------- */

function gwseq_sanitize_campagne_cta_input($raw, $prefix) {
  $raw = is_array($raw) ? $raw : array();
  return array(
    'active' => gws_core_field_sanitize('checkbox', $raw[$prefix . 'cta_active'] ?? ''),
    'libelle' => gws_core_field_sanitize('text', $raw[$prefix . 'cta_libelle'] ?? ''),
    'url' => gws_core_field_sanitize('url', $raw[$prefix . 'cta_url'] ?? ''),
  );
}

/* -------------------------------------------------------------------------------------------
 * Texte enrichi MINIMAL (§D/§G) : gras, italique, lien, liste — jamais du HTML arbitraire, jamais
 * Gutenberg, jamais un page builder. Rendu via `wp_editor(..., array('teeny' => true))`, le mode
 * "mini TinyMCE" déjà fourni nativement par WordPress (classique, pas Gutenberg) — sa barre
 * d'outils par défaut est plus large que demandé (soulignement, citation, alignements...) : le
 * filtre `teeny_mce_buttons` ci-dessous la réduit aux seuls boutons voulus, UNIQUEMENT pendant le
 * rendu de CET éditeur précis (drapeau global remis à faux juste après), pour ne jamais affecter
 * un autre usage natif de `teeny` ailleurs dans l'administration (ex. réponse rapide à un
 * commentaire, qui utilise aussi ce mode).
 * ----------------------------------------------------------------------------------------- */

function gwseq_campagne_texte_allowed_html() {
  return array(
    'strong' => array(),
    'b' => array(),
    'em' => array(),
    'i' => array(),
    'a' => array('href' => true, 'target' => array(), 'rel' => array()),
    'ul' => array(),
    'li' => array(),
    'br' => array(),
  );
}

function gwseq_sanitize_campagne_texte_input($raw_value) {
  return wp_kses((string) wp_unslash($raw_value), gwseq_campagne_texte_allowed_html());
}

$GLOBALS['__gwseq_campagne_teeny_editor_active'] = false;

function gwseq_restrict_campagne_teeny_buttons($buttons) {
  if (empty($GLOBALS['__gwseq_campagne_teeny_editor_active'])) return $buttons;
  return array('bold', 'italic', 'bullist', 'numlist', 'link', 'unlink');
}
add_filter('teeny_mce_buttons', 'gwseq_restrict_campagne_teeny_buttons');

function gwseq_render_campagne_texte_editor($field_id, $field_name, $value) {
  $GLOBALS['__gwseq_campagne_teeny_editor_active'] = true;
  wp_editor($value, $field_id, array(
    'textarea_name' => $field_name,
    'teeny' => true,
    'media_buttons' => false,
    'textarea_rows' => 5,
    'quicktags' => false,
  ));
  $GLOBALS['__gwseq_campagne_teeny_editor_active'] = false;
}

/* -------------------------------------------------------------------------------------------
 * Dates de diffusion (§H) : la saisie (format natif de <input type="datetime-local">) est
 * interprétée dans le FUSEAU HORAIRE DU SITE (`wp_timezone()`, jamais l'heure serveur brute), puis
 * stockée comme timestamp UTC — comparable directement à `time()` (toujours UTC en PHP) au moment
 * de l'évaluation, sans nouveau calcul de fuseau à la lecture. `0` = non renseigné (absence de
 * borne), jamais une date epoch réelle dans ce contexte métier.
 * ----------------------------------------------------------------------------------------- */

function gwseq_sanitize_campagne_datetime_input($raw_value) {
  $raw_value = trim((string) wp_unslash($raw_value));
  if ($raw_value === '') return 0;
  try {
    $date = date_create($raw_value, wp_timezone());
  } catch (Exception $e) {
    $date = false;
  }
  return $date ? $date->getTimestamp() : 0;
}

/**
 * `$now_ts` injectable pour les tests — jamais utilisé en production autrement qu'avec sa valeur
 * par défaut (`time()`, l'heure réelle).
 */
function gwseq_campagne_est_dans_la_fenetre($debut_ts, $fin_ts, $now_ts = null) {
  if ($now_ts === null) $now_ts = time();
  if ($debut_ts && $now_ts < $debut_ts) return false;
  if ($fin_ts && $now_ts > $fin_ts) return false;
  return true;
}

/* -------------------------------------------------------------------------------------------
 * Ciblage de contenus (§H) : quatre modes, jamais un moteur de règles avancé. Le contenu ciblé
 * n'est PAS limité aux Pages (contrairement au ciblage des Prestations par Groupe tarifaire) —
 * plusieurs post types sont concernés (Pages, Chevaux, Prestations, Actualités), donc un simple
 * tableau d'IDs serait ambigu (l'ID 12 pourrait être une Page ET un Cheval). Chaque cible est donc
 * encodée sous la forme stable "post_type:post_id" (ex. "gwseq_cheval:42") — jamais un post_type
 * deviné à la lecture, toujours explicite dans la donnée stockée elle-même.
 * ----------------------------------------------------------------------------------------- */

function gwseq_campagne_ciblage_mode_options() {
  return array(
    'all' => __('Tout le site', 'gws-core'),
    'front_page' => __('Page d’accueil uniquement', 'gws-core'),
    'include' => __('Certains contenus', 'gws-core'),
    'exclude' => __('Tout le site sauf certains contenus', 'gws-core'),
  );
}

/**
 * Post types proposables au ciblage (V1 volontairement limitée, §H) : Pages, Chevaux, Prestations,
 * Actualités (`post`). Ni Équipe, ni archives, ni taxonomies, ni recherche, ni règles regex.
 */
function gwseq_campagne_ciblage_post_types() {
  return array('page', GWSEQ_CPT_CHEVAL, GWSEQ_CPT_PRESTATION, 'post');
}

function gwseq_encode_campagne_cible($post_type, $post_id) {
  return $post_type . ':' . (int) $post_id;
}

function gwseq_decode_campagne_cible($encoded) {
  $parts = explode(':', (string) $encoded, 2);
  if (count($parts) !== 2 || $parts[0] === '' || !ctype_digit($parts[1])) return null;
  return array('post_type' => $parts[0], 'post_id' => (int) $parts[1]);
}

/**
 * Fonction pure — `$raw['mode']`/`$raw['cibles']` proviennent du même préfixe de champs que le
 * reste de la section Diffusion (voir gwseq_sanitize_popin_diffusion_input()/..._sticky_bar...()).
 * Chaque cible soumise est revalidée contre le référentiel autorisé ET contre le post_type RÉEL du
 * post_id (jamais confiance dans une valeur envoyée par le formulaire, même si elle provient du
 * sélecteur que nous générons nous-mêmes) — même discipline que
 * gwseq_sanitize_prestation_groupe_id().
 */
function gwseq_sanitize_campagne_ciblage_input($raw) {
  $raw = is_array($raw) ? $raw : array();
  $mode = isset($raw['ciblage_mode']) ? sanitize_key(wp_unslash($raw['ciblage_mode'])) : 'all';
  if (!array_key_exists($mode, gwseq_campagne_ciblage_mode_options())) $mode = 'all';

  $cibles = array();
  if (in_array($mode, array('include', 'exclude'), true) && isset($raw['ciblage_cibles']) && is_array($raw['ciblage_cibles'])) {
    $allowed_post_types = gwseq_campagne_ciblage_post_types();
    foreach ($raw['ciblage_cibles'] as $encoded) {
      $decoded = gwseq_decode_campagne_cible(sanitize_text_field(wp_unslash($encoded)));
      if (!$decoded || !$decoded['post_id'] || !in_array($decoded['post_type'], $allowed_post_types, true)) continue;
      if (get_post_type($decoded['post_id']) !== $decoded['post_type']) continue;
      $key = gwseq_encode_campagne_cible($decoded['post_type'], $decoded['post_id']);
      if (!in_array($key, $cibles, true)) $cibles[] = $key;
    }
  }

  return array('mode' => $mode, 'cibles' => $cibles);
}

/**
 * Éligibilité de PAGE (jamais de statut/dates ici, voir gwseq_campagne_est_dans_la_fenetre() et le
 * statut propre à chaque objet — cette fonction répond uniquement à "sommes-nous sur une page
 * ciblée ?"). `$queried_post_id` : ID du contenu couramment affiché (0 si aucun contenu identifiable,
 * ex. page de recherche/404). "Page d'accueil" est déterminée via `is_front_page()` (jamais un ID de
 * page particulier) — fonctionne que l'accueil soit une Page statique ou l'index des articles.
 */
function gwseq_campagne_page_est_ciblee($ciblage, $queried_post_id, $is_front_page = null) {
  if ($is_front_page === null) $is_front_page = is_front_page();
  if ($ciblage['mode'] === 'all') return true;
  if ($ciblage['mode'] === 'front_page') return $is_front_page;

  if (!$queried_post_id) return $ciblage['mode'] === 'exclude';

  $post_type = get_post_type($queried_post_id);
  $key = gwseq_encode_campagne_cible($post_type, $queried_post_id);
  $in_list = in_array($key, $ciblage['cibles'], true);

  if ($ciblage['mode'] === 'include') return $in_list;
  if ($ciblage['mode'] === 'exclude') return !$in_list;
  return false;
}

/* -------------------------------------------------------------------------------------------
 * Diffusion (§H) : statut actif/inactif — DISTINCT du statut de publication WordPress (une
 * campagne peut être publiée mais mise en pause sans perdre son statut "Publié", voir §P : un
 * Éditeur peut "activer/désactiver" ET "publier", deux actions bien séparées).
 * ----------------------------------------------------------------------------------------- */

function gwseq_campagne_statut_options() {
  return array(
    'active' => __('Active', 'gws-core'),
    'inactive' => __('Inactive', 'gws-core'),
  );
}

function gwseq_campagne_timestamp_to_datetime_local($ts) {
  $ts = (int) $ts;
  if (!$ts) return '';
  $date = new DateTime('@' . $ts);
  $date->setTimezone(wp_timezone());
  return $date->format('Y-m-d\TH:i');
}

function gwseq_campagne_periode_label($diffusion) {
  if (!$diffusion['debut_ts'] && !$diffusion['fin_ts']) return __('Sans limite', 'gws-core');
  $format = get_option('date_format') . ' ' . get_option('time_format');
  $debut = $diffusion['debut_ts'] ? date_i18n($format, $diffusion['debut_ts']) : '';
  $fin = $diffusion['fin_ts'] ? date_i18n($format, $diffusion['fin_ts']) : '';
  if ($debut !== '' && $fin !== '') return sprintf(__('Du %1$s au %2$s', 'gws-core'), $debut, $fin);
  if ($debut !== '') return sprintf(__('À partir du %s', 'gws-core'), $debut);
  return sprintf(__('Jusqu’au %s', 'gws-core'), $fin);
}

function gwseq_campagne_ciblage_label($diffusion) {
  $options = gwseq_campagne_ciblage_mode_options();
  $label = $options[$diffusion['ciblage_mode']] ?? '';
  if (in_array($diffusion['ciblage_mode'], array('include', 'exclude'), true)) {
    $count = count($diffusion['ciblage_cibles']);
    $label .= ' (' . sprintf(_n('%d contenu', '%d contenus', $count, 'gws-core'), $count) . ')';
  }
  return $label;
}

/**
 * Libellés des post types proposables au ciblage — jamais le nom technique WordPress affiché à
 * l'utilisateur.
 */
function gwseq_campagne_ciblage_post_type_labels() {
  return array(
    'page' => __('Pages', 'gws-core'),
    GWSEQ_CPT_CHEVAL => __('Chevaux', 'gws-core'),
    GWSEQ_CPT_PRESTATION => __('Prestations', 'gws-core'),
    'post' => __('Actualités', 'gws-core'),
  );
}

/**
 * Sélecteur natif (`<select multiple>`, regroupé par type de contenu) plutôt qu'une recherche en
 * temps réel : simple, accessible au clavier, sans JavaScript supplémentaire — un moteur de
 * recherche instantanée pourrait être ajouté plus tard si le volume de contenu le justifie, jamais
 * nécessaire pour une V1 qui se veut volontairement simple (§O : pas de règles avancées).
 */
function gwseq_render_campagne_ciblage_picker($meta_prefix, $selected_cibles) {
  $labels = gwseq_campagne_ciblage_post_type_labels();
  ?>
  <select multiple class="widefat" size="8" name="<?php echo esc_attr($meta_prefix); ?>ciblage_cibles[]" id="<?php echo esc_attr($meta_prefix); ?>ciblage-cibles">
    <?php foreach (gwseq_campagne_ciblage_post_types() as $post_type) :
      $items = get_posts(array(
        'post_type' => $post_type,
        'post_status' => array('publish', 'draft', 'pending', 'private'),
        'numberposts' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
      ));
      if (!$items) continue;
    ?>
      <optgroup label="<?php echo esc_attr($labels[$post_type] ?? $post_type); ?>">
        <?php foreach ($items as $item) :
          $key = gwseq_encode_campagne_cible($post_type, $item->ID);
        ?>
          <option value="<?php echo esc_attr($key); ?>" <?php selected(in_array($key, $selected_cibles, true)); ?>><?php echo esc_html(get_the_title($item)); ?></option>
        <?php endforeach; ?>
      </optgroup>
    <?php endforeach; ?>
  </select>
  <?php
}

/**
 * Champs de la section Diffusion — IDENTIQUES pour Pop-in et Sticky bar (§H : "réutiliser la même
 * logique que Pop-in"), paramétrés uniquement par le préfixe de champ (`popin`/`sticky_bar`) pour
 * générer les bons noms HTML. Aucune donnée écrite ici : uniquement du rendu, la sauvegarde reste
 * gérée par chaque objet via ses propres gwseq_sanitize_*_diffusion_input()/gwseq_set_*_diffusion().
 */
function gwseq_render_campagne_diffusion_fields($prefix, $diffusion) {
  $meta_prefix = '_gwseq_' . $prefix . '_';
  ?>
  <p><strong><?php esc_html_e('Statut', 'gws-core'); ?></strong></p>
  <p>
    <?php foreach (gwseq_campagne_statut_options() as $key => $label) : ?>
      <label style="margin-right:16px;">
        <input type="radio" name="<?php echo esc_attr($meta_prefix); ?>statut" value="<?php echo esc_attr($key); ?>" <?php checked($diffusion['statut'], $key); ?>>
        <?php echo esc_html($label); ?>
      </label>
    <?php endforeach; ?>
  </p>
  <hr>
  <p><strong><?php esc_html_e('Période', 'gws-core'); ?></strong></p>
  <p>
    <label for="<?php echo esc_attr($meta_prefix); ?>debut"><?php esc_html_e('Début (facultatif)', 'gws-core'); ?></label><br>
    <input type="datetime-local" id="<?php echo esc_attr($meta_prefix); ?>debut" name="<?php echo esc_attr($meta_prefix); ?>debut" value="<?php echo esc_attr(gwseq_campagne_timestamp_to_datetime_local($diffusion['debut_ts'])); ?>">
  </p>
  <p>
    <label for="<?php echo esc_attr($meta_prefix); ?>fin"><?php esc_html_e('Fin (facultative)', 'gws-core'); ?></label><br>
    <input type="datetime-local" id="<?php echo esc_attr($meta_prefix); ?>fin" name="<?php echo esc_attr($meta_prefix); ?>fin" value="<?php echo esc_attr(gwseq_campagne_timestamp_to_datetime_local($diffusion['fin_ts'])); ?>">
  </p>
  <p class="description"><?php esc_html_e('Sans date, aucune limite correspondante ne s’applique.', 'gws-core'); ?></p>
  <hr>
  <p><strong><?php esc_html_e('Pages concernées', 'gws-core'); ?></strong></p>
  <p>
    <?php foreach (gwseq_campagne_ciblage_mode_options() as $key => $label) : ?>
      <label style="display:block;margin-bottom:4px;">
        <input type="radio" name="<?php echo esc_attr($meta_prefix); ?>ciblage_mode" value="<?php echo esc_attr($key); ?>" <?php checked($diffusion['ciblage_mode'], $key); ?> data-gwseq-ciblage-mode>
        <?php echo esc_html($label); ?>
      </label>
    <?php endforeach; ?>
  </p>
  <div data-gwseq-campagne-fields="ciblage-cibles" style="<?php echo in_array($diffusion['ciblage_mode'], array('include', 'exclude'), true) ? '' : 'display:none;'; ?>">
    <?php gwseq_render_campagne_ciblage_picker($meta_prefix, $diffusion['ciblage_cibles']); ?>
  </div>
  <?php
}

/**
 * Panneau d'aperçu temps réel (§J) — identique pour Pop-in et Sticky bar : le sélecteur
 * Ordinateur/Mobile ne change que la largeur du cadre de preview (une seule configuration
 * responsive, jamais deux réglages indépendants). Le contenu du cadre est entièrement rempli par
 * JavaScript (assets/campagnes-admin.js) via l'appel AJAX de preview — jamais un rendu PHP figé
 * ici, qui deviendrait immédiatement obsolète au premier changement de champ.
 */
function gwseq_render_campagne_preview_panel($type) {
  ?>
  <div class="gwseq-campagne-preview">
    <p class="gwseq-campagne-preview__toggle">
      <button type="button" class="button" data-gwseq-preview-device="desktop" aria-pressed="true"><?php esc_html_e('Ordinateur', 'gws-core'); ?></button>
      <button type="button" class="button" data-gwseq-preview-device="mobile" aria-pressed="false"><?php esc_html_e('Mobile', 'gws-core'); ?></button>
    </p>
    <div class="gwseq-campagne-preview__viewport" data-gwseq-preview-viewport="desktop">
      <div data-gwseq-campagne-preview-frame></div>
    </div>
    <p class="description"><?php esc_html_e('Cet aperçu se met à jour automatiquement, sans besoin d’enregistrer.', 'gws-core'); ?></p>
  </div>
  <?php
}

/* -------------------------------------------------------------------------------------------
 * AJAX preview (§J) : garde commune (nonce + capability), réutilisée par
 * gwseq_ajax_preview_popin()/gwseq_ajax_preview_sticky_bar() — jamais une garde dupliquée deux
 * fois. `edit_posts` (capacité générique déjà détenue par tout rôle pouvant accéder à ces écrans,
 * jamais une capacité technique nouvelle) suffit : ce point d'entrée ne fait QUE re-render un
 * état de formulaire déjà transmis, jamais une lecture/écriture de données sensibles.
 * ----------------------------------------------------------------------------------------- */

function gwseq_verify_campagne_preview_request($nonce_action) {
  $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
  if (!wp_verify_nonce($nonce, $nonce_action)) {
    wp_send_json_error(array('message' => __('Session expirée, veuillez recharger la page.', 'gws-core')), 403);
  }
  if (!current_user_can('edit_posts')) {
    wp_send_json_error(array('message' => __('Action non autorisée.', 'gws-core')), 403);
  }
}

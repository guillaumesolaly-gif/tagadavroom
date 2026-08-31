<?php
/**
 * Import IFCE — écran d'administration (Étape 7, §1/§9-12 de la demande).
 *
 * Parcours en deux étapes, en simple POST classique (aucun AJAX nécessaire) :
 * 1. Téléversement + validation de sécurité du PDF + analyse -> structure normalisée stockée dans
 *    un TRANSIENT WordPress (15 minutes, jamais dans une meta de fiche à ce stade) -> redirection
 *    vers l'écran de prévisualisation ;
 * 2. Prévisualisation (§9, "aucun import silencieux") -> case à cocher par section
 *    (Identité/Indices/Pedigree, §9 "import partiel") -> validation explicite -> SEULEMENT à ce
 *    moment, création de la fiche Cheval (wp_insert_post()) et appel à gwseq_ifce_map_import().
 *
 * Le contenu structuré réellement écrit sur confirmation est TOUJOURS relu depuis le transient
 * serveur, jamais depuis un champ cru resoumis par le client (seuls le jeton et les cases à cocher
 * viennent du POST de confirmation) — un utilisateur ne peut donc jamais faire écrire une donnée
 * qu'il n'a pas d'abord vue sur l'écran de prévisualisation.
 *
 * SÉCURITÉ DU FICHIER (§11) : type MIME réel (finfo, pas seulement l'extension ni le Content-Type
 * annoncé par le navigateur), extension .pdf, taille maximale, provenance is_uploaded_file(), et
 * suppression immédiate du fichier temporaire après extraction du texte, que l'analyse réussisse
 * ou non — le PDF n'est jamais conservé après l'import (ni sur la fiche Cheval créée, ni ailleurs).
 *
 * CAPACITÉ : edit_posts (cohérent avec le type d'objet Cheval — capability_type par défaut
 * 'post', voir post-types.php, aucune capacité personnalisée n'y est enregistrée), à la différence
 * des pages de réglages globales du plugin qui utilisent manage_options.
 *
 * COMPATIBILITÉ (§12) : ce fichier ne modifie ni ne remplace le formulaire de création manuelle
 * existant (cheval-fields.php et les autres boîtes) — il n'ajoute qu'un second chemin, un lien
 * visible depuis l'écran natif "Ajouter un cheval". Toute donnée importée reste ensuite éditable
 * exactement comme une donnée saisie manuellement, aucun champ ni verrou spécifique à l'import
 * n'est introduit dans les boîtes existantes.
 */

if (!defined('ABSPATH')) exit;

const GWSEQ_IFCE_IMPORT_NONCE_ACTION = 'gwseq_ifce_import';
const GWSEQ_IFCE_IMPORT_NONCE_FIELD = 'gwseq_ifce_import_nonce';
const GWSEQ_IFCE_IMPORT_MAX_SIZE = 15728640; // 15 Mo

function gwseq_ifce_import_menu_slug() {
  return 'gwseq-ifce-import';
}

function gwseq_ifce_import_page_url($args = array()) {
  return add_query_arg(
    array_merge(array('post_type' => GWSEQ_CPT_CHEVAL, 'page' => gwseq_ifce_import_menu_slug()), $args),
    admin_url('edit.php')
  );
}

function gwseq_add_ifce_import_page() {
  add_submenu_page(
    'edit.php?post_type=' . GWSEQ_CPT_CHEVAL,
    __('Importer une fiche IFCE', 'gws-core'),
    __('Importer une fiche IFCE', 'gws-core'),
    'edit_posts',
    gwseq_ifce_import_menu_slug(),
    'gwseq_render_ifce_import_page'
  );
}
add_action('admin_menu', 'gwseq_add_ifce_import_page');

/**
 * Lien depuis l'écran natif "Ajouter un cheval" (§1 : les deux chemins — création manuelle /
 * import IFCE — doivent être proposés au même endroit). Ne modifie jamais l'écran lui-même, se
 * contente d'un avis d'administration standard, strictement scopé à cet écran précis.
 */
function gwseq_ifce_import_add_new_notice() {
  if (!function_exists('get_current_screen')) return;
  $screen = get_current_screen();
  if (!$screen || $screen->base !== 'post' || $screen->post_type !== GWSEQ_CPT_CHEVAL) return;
  global $pagenow;
  if ($pagenow !== 'post-new.php') return;
  if (!current_user_can('edit_posts')) return;

  echo '<div class="notice notice-info"><p>' . sprintf(
    /* translators: %s: lien vers l'écran d'import IFCE */
    esc_html__('Vous pouvez aussi importer cette fiche depuis une fiche de synthèse IFCE / Info Chevaux (PDF) plutôt que de la créer manuellement. %s', 'gws-core'),
    '<a href="' . esc_url(gwseq_ifce_import_page_url()) . '">' . esc_html__('Importer une fiche IFCE', 'gws-core') . '</a>'
  ) . '</p></div>';
}
add_action('admin_notices', 'gwseq_ifce_import_add_new_notice');

/* -------------------------------------------------------------------------------------------
 * Stockage temporaire de la structure analysée (transient, jamais une meta de fiche à ce stade).
 * ----------------------------------------------------------------------------------------- */

function gwseq_ifce_import_transient_key($token) {
  return 'gwseq_ifce_' . $token;
}

function gwseq_set_ifce_import_transient($token, $parsed) {
  set_transient(gwseq_ifce_import_transient_key($token), array(
    'user_id' => get_current_user_id(),
    'parsed' => $parsed,
  ), 15 * MINUTE_IN_SECONDS);
}

/**
 * Relit le transient, avec vérification que l'utilisateur courant est bien celui qui a lancé
 * l'analyse (défense en profondeur, en complément du jeton déjà imprévisible et de la capacité
 * edit_posts déjà vérifiée par WordPress pour accéder à cet écran).
 */
function gwseq_get_ifce_import_transient($token) {
  if ($token === '') return false;
  $data = get_transient(gwseq_ifce_import_transient_key($token));
  if (!is_array($data) || !isset($data['parsed']) || (int) ($data['user_id'] ?? 0) !== get_current_user_id()) return false;
  return $data;
}

function gwseq_delete_ifce_import_transient($token) {
  delete_transient(gwseq_ifce_import_transient_key($token));
}

/* -------------------------------------------------------------------------------------------
 * Validation de sécurité du fichier téléversé (§11).
 * ----------------------------------------------------------------------------------------- */

/**
 * Contrôles indépendants de toute vraie requête HTTP de téléversement (code d'erreur, taille,
 * extension) — volontairement isolés dans une fonction pure et testable. Retourne '' si valide,
 * sinon un message d'erreur explicite destiné directement à l'utilisateur.
 */
function gwseq_ifce_validate_pdf_upload_shape($file) {
  if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
    return __('Veuillez sélectionner un fichier PDF.', 'gws-core');
  }
  if (($file['error'] ?? 0) !== UPLOAD_ERR_OK) {
    return __('Le téléversement du fichier a échoué.', 'gws-core');
  }
  $size = (int) ($file['size'] ?? 0);
  if ($size <= 0 || $size > GWSEQ_IFCE_IMPORT_MAX_SIZE) {
    return __('Le fichier est vide ou dépasse la taille maximale autorisée (15 Mo).', 'gws-core');
  }
  $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
  if ($extension !== 'pdf') {
    return __('Seul un fichier au format PDF est accepté.', 'gws-core');
  }
  return '';
}

/**
 * Contrôles complémentaires nécessitant une vraie requête de téléversement (provenance réelle du
 * fichier temporaire, type MIME réel via signature binaire) — non unitairement testables hors
 * d'un vrai navigateur, revus manuellement (voir tests/gws-equestrian-ifce-import-test.php pour la
 * vérification déclarative de leur présence dans le code). Retourne le chemin temporaire validé,
 * ou false avec $error renseigné.
 */
function gwseq_ifce_validate_uploaded_pdf($file, &$error) {
  $error = gwseq_ifce_validate_pdf_upload_shape($file);
  if ($error !== '') return false;

  $tmp_name = $file['tmp_name'] ?? '';
  if ($tmp_name === '' || !is_uploaded_file($tmp_name)) {
    $error = __('Fichier temporaire invalide.', 'gws-core');
    return false;
  }

  if (function_exists('finfo_open')) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? finfo_file($finfo, $tmp_name) : false;
    if ($finfo) finfo_close($finfo);
    if ($mime !== 'application/pdf') {
      $error = __('Le contenu du fichier ne correspond pas à un PDF valide.', 'gws-core');
      return false;
    }
  }

  return $tmp_name;
}

/* -------------------------------------------------------------------------------------------
 * Traitement des deux étapes.
 * ----------------------------------------------------------------------------------------- */

function gwseq_handle_ifce_import_upload() {
  check_admin_referer(GWSEQ_IFCE_IMPORT_NONCE_ACTION, GWSEQ_IFCE_IMPORT_NONCE_FIELD);
  if (!current_user_can('edit_posts')) wp_die(esc_html__('Action non autorisée.', 'gws-core'));

  $error = '';
  $file = $_FILES['gwseq_ifce_pdf'] ?? null;
  $validated_path = gwseq_ifce_validate_uploaded_pdf($file, $error);
  if ($validated_path === false) {
    gwseq_render_ifce_import_upload_form($error);
    return;
  }

  $text = gwseq_ifce_extract_pdf_text($validated_path);
  // Suppression immédiate du fichier temporaire (§11) : le PDF n'est jamais conservé, que
  // l'analyse réussisse ou échoue ensuite — ce n'est qu'une source d'import, jamais une nouvelle
  // source de vérité stockée sur la fiche.
  if (file_exists($validated_path)) @unlink($validated_path);

  $parsed = gwseq_ifce_parse_text($text);
  if (empty($parsed['valid'])) {
    gwseq_render_ifce_import_upload_form(__('Ce document n’a pas été reconnu comme une fiche de synthèse IFCE. Vérifiez qu’il s’agit bien du PDF complet téléchargé depuis Info Chevaux, ou créez la fiche manuellement.', 'gws-core'));
    return;
  }

  $token = wp_generate_password(32, false, false);
  gwseq_set_ifce_import_transient($token, $parsed);

  wp_safe_redirect(gwseq_ifce_import_page_url(array('gwseq_token' => $token)));
  exit;
}

function gwseq_handle_ifce_import_confirm() {
  check_admin_referer(GWSEQ_IFCE_IMPORT_NONCE_ACTION, GWSEQ_IFCE_IMPORT_NONCE_FIELD);
  if (!current_user_can('edit_posts')) wp_die(esc_html__('Action non autorisée.', 'gws-core'));

  $token = isset($_POST['gwseq_token']) ? sanitize_key(wp_unslash($_POST['gwseq_token'])) : '';
  $data = gwseq_get_ifce_import_transient($token);
  if ($data === false) {
    gwseq_render_ifce_import_upload_form(__('Cet import a expiré ou n’est plus disponible. Veuillez recommencer.', 'gws-core'));
    return;
  }

  $parsed = $data['parsed'];
  $sections = array(
    'identity' => !empty($_POST['gwseq_ifce_import_identity']),
    'indices' => !empty($_POST['gwseq_ifce_import_indices']),
    'pedigree' => !empty($_POST['gwseq_ifce_import_pedigree']),
  );

  $post_id = wp_insert_post(array(
    'post_type' => GWSEQ_CPT_CHEVAL,
    'post_status' => 'draft',
    'post_title' => $parsed['identity']['nom'],
  ), true);

  if (is_wp_error($post_id) || !$post_id) {
    gwseq_delete_ifce_import_transient($token);
    gwseq_render_ifce_import_upload_form(__('La création de la fiche a échoué. Aucune donnée n’a été importée.', 'gws-core'));
    return;
  }

  gwseq_ifce_map_import($post_id, $parsed, $sections);
  gwseq_delete_ifce_import_transient($token);

  wp_safe_redirect(get_edit_post_link($post_id, 'raw'));
  exit;
}

function gwseq_render_ifce_import_page() {
  if (!current_user_can('edit_posts')) wp_die(esc_html__('Action non autorisée.', 'gws-core'));

  if (($_POST['gwseq_ifce_action'] ?? '') === 'upload') {
    gwseq_handle_ifce_import_upload();
    return;
  }
  if (($_POST['gwseq_ifce_action'] ?? '') === 'confirm') {
    gwseq_handle_ifce_import_confirm();
    return;
  }

  $token = isset($_GET['gwseq_token']) ? sanitize_key(wp_unslash($_GET['gwseq_token'])) : '';
  if ($token !== '') {
    $data = gwseq_get_ifce_import_transient($token);
    if ($data !== false) {
      gwseq_render_ifce_import_preview($token, $data['parsed']);
      return;
    }
    gwseq_render_ifce_import_upload_form(__('Cet import a expiré ou n’est plus disponible. Veuillez recommencer.', 'gws-core'));
    return;
  }

  gwseq_render_ifce_import_upload_form();
}

function gwseq_render_ifce_import_upload_form($error = '') {
  ?>
  <div class="wrap">
    <h1><?php esc_html_e('Importer une fiche IFCE', 'gws-core'); ?></h1>
    <?php if ($error !== '') : ?>
      <div class="notice notice-error"><p><?php echo esc_html($error); ?></p></div>
    <?php endif; ?>
    <p><?php esc_html_e('Où trouver cette fiche ? Rendez-vous sur Info Chevaux de l’IFCE, recherchez votre cheval avec son nom ou son numéro SIRE, ouvrez sa fiche puis téléchargez sa fiche de synthèse PDF. Importez ensuite ici le PDF complet.', 'gws-core'); ?></p>
    <form method="post" enctype="multipart/form-data">
      <?php wp_nonce_field(GWSEQ_IFCE_IMPORT_NONCE_ACTION, GWSEQ_IFCE_IMPORT_NONCE_FIELD); ?>
      <input type="hidden" name="gwseq_ifce_action" value="upload">
      <p><input type="file" name="gwseq_ifce_pdf" accept="application/pdf" required></p>
      <p><?php submit_button(__('Analyser le PDF', 'gws-core'), 'primary', 'submit', false); ?></p>
    </form>
    <p><a href="<?php echo esc_url(admin_url('post-new.php?post_type=' . GWSEQ_CPT_CHEVAL)); ?>"><?php esc_html_e('Ou créer une fiche manuellement', 'gws-core'); ?></a></p>
  </div>
  <?php
}

function gwseq_render_ifce_import_preview($token, $parsed) {
  $identity = $parsed['identity'];
  $indices = $parsed['indices'];
  $pedigree = $parsed['pedigree'];

  $race_label = $identity['race'] === 'autre'
    ? $identity['race_autre']
    : (gwseq_cheval_race_options()[$identity['race']] ?? __('non détectée', 'gws-core'));
  $robe_label = $identity['robe'] === 'autre'
    ? $identity['robe_autre']
    : (gwseq_cheval_robe_options()[$identity['robe']] ?? __('non détectée', 'gws-core'));
  $sexe_label = gwseq_cheval_sexe_options()[$identity['sexe']] ?? __('non détecté', 'gws-core');
  $taille_label = $identity['taille_cm'] !== ''
    ? sprintf(/* translators: %s: taille en mètres, ex. "1,68" */ __('%s m', 'gws-core'), number_format($identity['taille_cm'] / 100, 2))
    : __('non détectée', 'gws-core');
  $annee_label = $identity['annee_naissance'] !== '' ? $identity['annee_naissance'] : __('non détectée', 'gws-core');

  $identity_summary = sprintf('%s, %s, %s, %s, %s', $race_label, $sexe_label, $robe_label, $taille_label, $annee_label);

  $indices_labels = array();
  foreach (gwseq_cheval_sport_indice_keys() as $key) {
    if ($indices[$key]['valeur'] === '') continue;
    $label = strtoupper($key) . ' ' . $indices[$key]['valeur'];
    if ($indices[$key]['cd'] !== '') $label .= ' — CD ' . gwseq_format_cheval_indice_cd($indices[$key]['cd']);
    if ($indices[$key]['annee'] !== '') $label .= ' — ' . $indices[$key]['annee'];
    $indices_labels[] = $label;
  }
  foreach (gwseq_cheval_genetic_indice_keys() as $key) {
    if ($indices[$key]['valeur'] === '') continue;
    $label = strtoupper($key) . ' ' . $indices[$key]['valeur'];
    if ($indices[$key]['cd'] !== '') $label .= ' — CD ' . gwseq_format_cheval_indice_cd($indices[$key]['cd']);
    $indices_labels[] = $label;
  }
  ?>
  <div class="wrap">
    <h1><?php esc_html_e('Prévisualisation de l’import IFCE', 'gws-core'); ?></h1>
    <p class="description"><?php esc_html_e('Vérifiez attentivement les données ci-dessous avant de valider — rien n’a encore été enregistré sur une fiche Cheval.', 'gws-core'); ?></p>
    <p><strong><?php echo esc_html(sprintf(/* translators: %s: nom du cheval détecté */ __('Cheval reconnu : %s', 'gws-core'), $identity['nom'])); ?></strong></p>
    <p><?php echo esc_html(sprintf(/* translators: %s: résumé identité détectée */ __('Identité détectée : %s', 'gws-core'), $identity_summary)); ?></p>
    <p><?php echo esc_html(sprintf(/* translators: %s: résumé indices détectés */ __('Indices détectés : %s', 'gws-core'), $indices_labels ? implode(', ', $indices_labels) : __('aucun', 'gws-core'))); ?></p>
    <p><?php echo esc_html(sprintf(
      /* translators: %d: nombre d'ascendants détectés dans le pedigree */
      _n('Pedigree : %d ascendant détecté.', 'Pedigree : %d ascendants détectés.', $pedigree['count'], 'gws-core'),
      $pedigree['count']
    )); ?></p>

    <form method="post">
      <?php wp_nonce_field(GWSEQ_IFCE_IMPORT_NONCE_ACTION, GWSEQ_IFCE_IMPORT_NONCE_FIELD); ?>
      <input type="hidden" name="gwseq_ifce_action" value="confirm">
      <input type="hidden" name="gwseq_token" value="<?php echo esc_attr($token); ?>">
      <p><label><input type="checkbox" name="gwseq_ifce_import_identity" value="1" checked> <?php esc_html_e('Importer l’identité', 'gws-core'); ?></label></p>
      <p><label><input type="checkbox" name="gwseq_ifce_import_indices" value="1" checked> <?php esc_html_e('Importer les indices', 'gws-core'); ?></label></p>
      <p><label><input type="checkbox" name="gwseq_ifce_import_pedigree" value="1" checked> <?php esc_html_e('Importer le pedigree', 'gws-core'); ?></label></p>
      <?php submit_button(__('Valider l’import', 'gws-core')); ?>
    </form>
    <p><a href="<?php echo esc_url(gwseq_ifce_import_page_url()); ?>"><?php esc_html_e('Annuler', 'gws-core'); ?></a></p>
  </div>
  <?php
}

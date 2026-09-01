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
 * CORRECTIF BLOQUANT post-recette — « headers already sent » (admin-post.php, jamais le callback
 * de page) : la première version de ce fichier traitait le POST (upload/confirmation) DIRECTEMENT
 * depuis le callback de la page d'administration (gwseq_render_ifce_import_page(), enregistré via
 * add_submenu_page()). Or WordPress appelle ce callback UNIQUEMENT depuis l'intérieur du rendu
 * complet de l'écran d'administration — après `wp-admin/admin-header.php`/`menu-header.php` ont
 * déjà émis le `<html>`/menu HTML — jamais avant. Un `wp_safe_redirect()` déclenché à ce stade
 * échoue systématiquement (« Cannot modify header information - headers already sent »), et la
 * suite du script continue silencieusement à s'exécuter sans jamais atteindre l'écran de
 * prévisualisation. CORRECTIF : le traitement des deux formulaires (upload, confirmation) est
 * désormais confié aux hooks natifs `admin_post_{action}` de WordPress, déclenchés depuis
 * `wp-admin/admin-post.php` — un point d'entrée dédié qui ne rend JAMAIS aucun HTML d'écran
 * d'administration avant de déclencher ce hook, ce qui garantit qu'aucune sortie ne précède un
 * éventuel `wp_safe_redirect()`. Le callback de page (gwseq_render_ifce_import_page()) ne traite
 * plus JAMAIS de POST : il ne fait plus que lire l'état déjà déterminé (jeton de prévisualisation
 * en GET, message d'erreur éventuel déjà déposé dans un transient par le gestionnaire) et
 * l'afficher — jamais de redirection depuis ce callback.
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
 * existant (cheval-fields.php et les autres boîtes) — la création manuelle reste un chemin à part
 * entière, choisi explicitement. Toute donnée importée reste ensuite éditable exactement comme une
 * donnée saisie manuellement, aucun champ ni verrou spécifique à l'import n'est introduit dans les
 * boîtes existantes.
 *
 * ÉCRAN DE CHOIX "AJOUTER UN CHEVAL" (correctif post-recette, §B de la demande) : un simple bandeau
 * d'information sur le formulaire manuel (première version de cette fonctionnalité) reléguait
 * l'import IFCE au second plan, alors qu'il peut préremplir Identité + Indices + Pedigree — une
 * fonctionnalité largement aussi centrale que la création manuelle, jamais accessoire. Toute
 * requête vers l'écran natif "Ajouter un cheval" (`post-new.php?post_type=gwseq_cheval`) est
 * désormais interceptée AVANT l'affichage du formulaire manuel et redirigée vers un écran de choix
 * dédié (gwseq_render_cheval_choice_page()) proposant les deux chemins à égalité — le formulaire
 * manuel n'est atteint qu'après un clic explicite sur "Créer manuellement" (paramètre
 * `gwseq_manual=1`, qui neutralise la redirection pour CETTE requête précise uniquement, jamais de
 * façon persistante). Cet écran de choix est enregistré comme page orpheline (parent `null`) :
 * jamais un second point d'entrée visible dans le menu, qui ferait doublon avec l'entrée native
 * "Ajouter un cheval" déjà utilisée par WordPress pour déclencher cette redirection.
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
 * Écran de choix, enregistré comme page orpheline (parent `null`, jamais affichée dans un menu —
 * voir la note d'architecture en tête de fichier) : seul point d'atterrissage de la redirection
 * ci-dessous, réutilise le même mécanisme WordPress natif (`admin.php?page=...`) que n'importe
 * quelle page d'administration.
 */
function gwseq_add_cheval_choice_page() {
  add_submenu_page(
    null,
    __('Ajouter un cheval', 'gws-core'),
    __('Ajouter un cheval', 'gws-core'),
    'edit_posts',
    'gwseq-add-cheval-choice',
    'gwseq_render_cheval_choice_page'
  );
}
add_action('admin_menu', 'gwseq_add_cheval_choice_page');

function gwseq_cheval_choice_page_url() {
  return admin_url('admin.php?page=gwseq-add-cheval-choice');
}

function gwseq_cheval_manual_create_url() {
  return admin_url('post-new.php?post_type=' . GWSEQ_CPT_CHEVAL . '&gwseq_manual=1');
}

/**
 * Interception de l'écran natif "Ajouter un cheval" (§B de la demande) : redirige vers l'écran de
 * choix TANT QUE le paramètre `gwseq_manual=1` n'est pas présent — ce paramètre n'est ajouté que
 * par le lien "Créer manuellement" de l'écran de choix lui-même (voir
 * gwseq_render_cheval_choice_page()), jamais persisté au-delà de cette requête précise : revenir
 * sur "Ajouter un cheval" une prochaine fois represente de nouveau le choix. Ne concerne QUE
 * `post-new.php` pour le CPT Cheval — l'édition d'une fiche existante (`post.php?action=edit`)
 * n'est jamais concernée, quel que soit son mode de création d'origine.
 */
function gwseq_redirect_cheval_add_new_to_choice() {
  global $pagenow;
  if ($pagenow !== 'post-new.php') return;
  if (($_GET['post_type'] ?? '') !== GWSEQ_CPT_CHEVAL) return;
  if (isset($_GET['gwseq_manual'])) return;
  if (!current_user_can('edit_posts')) return;

  wp_safe_redirect(gwseq_cheval_choice_page_url());
  exit;
}
add_action('admin_init', 'gwseq_redirect_cheval_add_new_to_choice');

/**
 * Écran de choix lui-même (§B) : les deux chemins — import IFCE et création manuelle — présentés à
 * égalité, jamais l'un en retrait de l'autre. Aucune écriture, aucune logique métier ici : deux
 * simples liens vers les écrans déjà existants (import IFCE, formulaire manuel avec
 * `gwseq_manual=1`).
 */
function gwseq_render_cheval_choice_page() {
  if (!current_user_can('edit_posts')) wp_die(esc_html__('Action non autorisée.', 'gws-core'));
  ?>
  <div class="wrap gwseq-cheval-choice">
    <h1><?php esc_html_e('Ajouter un cheval', 'gws-core'); ?></h1>
    <div class="gwseq-cheval-choice__options">
      <div class="gwseq-cheval-choice__option">
        <h2><?php esc_html_e('Importer depuis l’IFCE', 'gws-core'); ?></h2>
        <p><?php esc_html_e('Importez la fiche de synthèse Info Chevaux de votre cheval pour préremplir automatiquement les informations disponibles (identité, indices, pedigree).', 'gws-core'); ?></p>
        <p><a class="button button-primary button-hero" href="<?php echo esc_url(gwseq_ifce_import_page_url()); ?>"><?php esc_html_e('Importer depuis l’IFCE', 'gws-core'); ?></a></p>
      </div>
      <div class="gwseq-cheval-choice__option">
        <h2><?php esc_html_e('Créer manuellement', 'gws-core'); ?></h2>
        <p><?php esc_html_e('Renseignez vous-même les informations du cheval.', 'gws-core'); ?></p>
        <p><a class="button button-secondary button-hero" href="<?php echo esc_url(gwseq_cheval_manual_create_url()); ?>"><?php esc_html_e('Créer manuellement', 'gws-core'); ?></a></p>
      </div>
    </div>
  </div>
  <style>
    .gwseq-cheval-choice__options { display: flex; flex-wrap: wrap; gap: 20px; margin-top: 20px; max-width: 960px; }
    .gwseq-cheval-choice__option { flex: 1 1 320px; background: #fff; border: 1px solid #ccd0d4; padding: 24px; }
  </style>
  <?php
}

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

/**
 * Message d'erreur "à traverser" une redirection (§ correctif "headers already sent" ci-dessus) :
 * un gestionnaire `admin_post_*` ne peut plus afficher directement un message d'erreur (il ne rend
 * jamais de HTML, seulement traiter puis rediriger) — le message est donc déposé dans un transient
 * scopé à l'utilisateur courant (clé fixe, jamais un jeton aléatoire : un seul message "en attente"
 * à la fois par utilisateur suffit), relu et immédiatement supprimé (affichage unique) par le
 * callback de page au prochain chargement en GET.
 */
function gwseq_ifce_import_notice_key() {
  return 'gwseq_ifce_notice_' . get_current_user_id();
}

function gwseq_set_ifce_import_notice($message) {
  set_transient(gwseq_ifce_import_notice_key(), $message, MINUTE_IN_SECONDS);
}

function gwseq_get_and_clear_ifce_import_notice() {
  $key = gwseq_ifce_import_notice_key();
  $message = get_transient($key);
  if ($message !== false) delete_transient($key);
  return $message !== false ? $message : '';
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

/**
 * Traitement PUR du téléversement, une fois le fichier déjà validé quant à sa PROVENANCE réelle
 * (chemin temporaire déjà vérifié par `is_uploaded_file()`/MIME réel côté appelant — cette étape
 * précise reste hors de cette fonction, non simulable hors d'une vraie requête HTTP de
 * téléversement, voir gwseq_ifce_validate_uploaded_pdf()). Extraction du texte, analyse, création
 * du transient si le document est reconnu — ne rend JAMAIS de HTML et n'appelle JAMAIS
 * `wp_safe_redirect()`/`exit` elle-même : retourne simplement `{redirect, notice}` pour que
 * l'appelant HTTP (gwseq_handle_ifce_import_upload() ci-dessous) se contente de rediriger. Cette
 * séparation rend le chemin réel (extraction -> analyse -> transient) directement testable par
 * appel direct, sans jamais avoir à exécuter une redirection dans un test.
 */
function gwseq_process_ifce_import_upload($validated_pdf_path) {
  $text = gwseq_ifce_extract_pdf_text($validated_pdf_path);
  // Suppression immédiate du fichier temporaire (§11) : le PDF n'est jamais conservé, que
  // l'analyse réussisse ou échoue ensuite — ce n'est qu'une source d'import, jamais une nouvelle
  // source de vérité stockée sur la fiche.
  if (file_exists($validated_pdf_path)) @unlink($validated_pdf_path);

  $parsed = gwseq_ifce_parse_text($text);
  if (empty($parsed['valid'])) {
    return array(
      'redirect' => gwseq_ifce_import_page_url(),
      'notice' => __('Ce document n’a pas été reconnu comme une fiche de synthèse IFCE. Vérifiez qu’il s’agit bien du PDF complet téléchargé depuis Info Chevaux, ou créez la fiche manuellement.', 'gws-core'),
    );
  }

  $token = wp_generate_password(32, false, false);
  gwseq_set_ifce_import_transient($token, $parsed);

  return array('redirect' => gwseq_ifce_import_page_url(array('gwseq_token' => $token)), 'notice' => null);
}

/**
 * Traite le formulaire de téléversement — déclenché par WordPress via
 * `admin_post_gwseq_ifce_import_upload` (voir enregistrement plus bas), DEPUIS
 * `wp-admin/admin-post.php`, jamais depuis le callback de page : aucune sortie HTML ne précède
 * jamais ce traitement, `wp_safe_redirect()` reste donc toujours valide, qu'il s'agisse du succès
 * ou d'une erreur (voir le correctif documenté en tête de fichier). Fine couche de glue HTTP autour
 * de gwseq_process_ifce_import_upload() ci-dessus — ne contient plus elle-même aucune logique
 * testable directement, uniquement la validation de provenance du fichier puis la redirection.
 */
function gwseq_handle_ifce_import_upload() {
  check_admin_referer(GWSEQ_IFCE_IMPORT_NONCE_ACTION, GWSEQ_IFCE_IMPORT_NONCE_FIELD);
  if (!current_user_can('edit_posts')) wp_die(esc_html__('Action non autorisée.', 'gws-core'));

  $error = '';
  $file = $_FILES['gwseq_ifce_pdf'] ?? null;
  $validated_path = gwseq_ifce_validate_uploaded_pdf($file, $error);
  if ($validated_path === false) {
    gwseq_set_ifce_import_notice($error);
    wp_safe_redirect(gwseq_ifce_import_page_url());
    exit;
  }

  $result = gwseq_process_ifce_import_upload($validated_path);
  if ($result['notice'] !== null) gwseq_set_ifce_import_notice($result['notice']);
  wp_safe_redirect($result['redirect']);
  exit;
}
add_action('admin_post_gwseq_ifce_import_upload', 'gwseq_handle_ifce_import_upload');

/**
 * Traitement PUR de la confirmation : relit le transient, et — SEULEMENT s'il est valide — crée la
 * fiche Cheval et appelle le mapping (§1 : aucune écriture avant confirmation explicite, et c'est
 * ICI, précisément, que cette confirmation a lieu). Ne rend JAMAIS de HTML et n'appelle JAMAIS
 * `wp_safe_redirect()`/`exit` elle-même — mêmes garanties de testabilité que
 * gwseq_process_ifce_import_upload() ci-dessus.
 */
function gwseq_process_ifce_import_confirm($token, $sections) {
  $data = gwseq_get_ifce_import_transient($token);
  if ($data === false) {
    return array(
      'redirect' => gwseq_ifce_import_page_url(),
      'notice' => __('Cet import a expiré ou n’est plus disponible. Veuillez recommencer.', 'gws-core'),
    );
  }

  $parsed = $data['parsed'];

  $post_id = wp_insert_post(array(
    'post_type' => GWSEQ_CPT_CHEVAL,
    'post_status' => 'draft',
    'post_title' => $parsed['identity']['nom'],
  ), true);

  if (is_wp_error($post_id) || !$post_id) {
    gwseq_delete_ifce_import_transient($token);
    return array(
      'redirect' => gwseq_ifce_import_page_url(),
      'notice' => __('La création de la fiche a échoué. Aucune donnée n’a été importée.', 'gws-core'),
    );
  }

  gwseq_ifce_map_import($post_id, $parsed, $sections);
  gwseq_delete_ifce_import_transient($token);

  return array('redirect' => get_edit_post_link($post_id, 'raw'), 'notice' => null);
}

/**
 * Traite le formulaire de confirmation — déclenché par WordPress via
 * `admin_post_gwseq_ifce_import_confirm`, DEPUIS `wp-admin/admin-post.php`, même garantie que
 * gwseq_handle_ifce_import_upload() ci-dessus. Fine couche de glue HTTP autour de
 * gwseq_process_ifce_import_confirm() ci-dessus.
 */
function gwseq_handle_ifce_import_confirm() {
  check_admin_referer(GWSEQ_IFCE_IMPORT_NONCE_ACTION, GWSEQ_IFCE_IMPORT_NONCE_FIELD);
  if (!current_user_can('edit_posts')) wp_die(esc_html__('Action non autorisée.', 'gws-core'));

  $token = isset($_POST['gwseq_token']) ? sanitize_key(wp_unslash($_POST['gwseq_token'])) : '';
  $sections = array(
    'identity' => !empty($_POST['gwseq_ifce_import_identity']),
    'indices' => !empty($_POST['gwseq_ifce_import_indices']),
    'pedigree' => !empty($_POST['gwseq_ifce_import_pedigree']),
  );

  $result = gwseq_process_ifce_import_confirm($token, $sections);
  if ($result['notice'] !== null) gwseq_set_ifce_import_notice($result['notice']);
  wp_safe_redirect($result['redirect']);
  exit;
}
add_action('admin_post_gwseq_ifce_import_confirm', 'gwseq_handle_ifce_import_confirm');

/**
 * Callback de page (GET uniquement, désormais) : ne traite plus jamais de POST ni ne redirige —
 * se contente de lire l'état déjà déterminé par les gestionnaires ci-dessus (jeton de
 * prévisualisation, message d'erreur éventuel) et de l'afficher.
 */
function gwseq_render_ifce_import_page() {
  if (!current_user_can('edit_posts')) wp_die(esc_html__('Action non autorisée.', 'gws-core'));

  $notice = gwseq_get_and_clear_ifce_import_notice();

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

  gwseq_render_ifce_import_upload_form($notice);
}

function gwseq_render_ifce_import_upload_form($error = '') {
  ?>
  <div class="wrap">
    <h1><?php esc_html_e('Importer une fiche IFCE', 'gws-core'); ?></h1>
    <?php if ($error !== '') : ?>
      <div class="notice notice-error"><p><?php echo esc_html($error); ?></p></div>
    <?php endif; ?>
    <p><?php esc_html_e('Où trouver cette fiche ? Rendez-vous sur Info Chevaux de l’IFCE, recherchez votre cheval avec son nom ou son numéro SIRE, ouvrez sa fiche puis téléchargez sa fiche de synthèse PDF. Importez ensuite ici le PDF complet.', 'gws-core'); ?></p>
    <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
      <?php wp_nonce_field(GWSEQ_IFCE_IMPORT_NONCE_ACTION, GWSEQ_IFCE_IMPORT_NONCE_FIELD); ?>
      <input type="hidden" name="action" value="gwseq_ifce_import_upload">
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

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
      <?php wp_nonce_field(GWSEQ_IFCE_IMPORT_NONCE_ACTION, GWSEQ_IFCE_IMPORT_NONCE_FIELD); ?>
      <input type="hidden" name="action" value="gwseq_ifce_import_confirm">
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

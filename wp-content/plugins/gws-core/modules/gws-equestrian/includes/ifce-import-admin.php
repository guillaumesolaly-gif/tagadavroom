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

/**
 * $reimport_cheval_id (Lot 2B.2, §21) : 0 pour un import créant une nouvelle fiche (comportement
 * historique inchangé), ou l'identifiant d'une fiche Cheval EXISTANTE que cet import doit mettre à
 * jour plutôt que dupliquer — nécessaire pour qu'un réimport ait un sens (rattachement CERTAIN,
 * actualisation d'indices sur un produit déjà lié, fusion non destructive de la Production). Revalidé
 * à chaque étape (jamais fait confiance à une valeur simplement resoumise) via
 * gwseq_sanitize_ifce_reimport_cheval_id() ci-dessous.
 */
function gwseq_set_ifce_import_transient($token, $parsed, $reimport_cheval_id = 0) {
  set_transient(gwseq_ifce_import_transient_key($token), array(
    'user_id' => get_current_user_id(),
    'parsed' => $parsed,
    'reimport_cheval_id' => (int) $reimport_cheval_id,
  ), 15 * MINUTE_IN_SECONDS);
}

/**
 * Valide un identifiant de fiche Cheval candidat à un réimport (§21) : doit désigner une VRAIE fiche
 * `gwseq_cheval` déjà existante, éditable par l'utilisateur courant — sinon 0 (traité exactement
 * comme un premier import classique, jamais une erreur bloquante : un lien de réimport mal formé ou
 * expiré ne fait que perdre le rattachement automatique à la fiche existante, rien de plus).
 */
function gwseq_sanitize_ifce_reimport_cheval_id($raw) {
  $cheval_id = absint($raw);
  if (!$cheval_id) return 0;
  if (get_post_type($cheval_id) !== GWSEQ_CPT_CHEVAL) return 0;
  if (!current_user_can('edit_post', $cheval_id)) return 0;
  return $cheval_id;
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
 * Extrait l'identifiant opaque IFCE depuis le NOM DU FICHIER PDF téléversé (Lot IFCE — clôture
 * POC, §3/§11 de la demande) — fonction PURE, testable indépendamment de toute requête HTTP.
 *
 * MÉTHODE (documentée pour le CR) : InfoChevaux nomme ses fiches de synthèse générées
 * `fs-{modele}_{type}_{ID}_{timestamp}.pdf` (exemples réels observés :
 * `fs-complet_classique_FlqOAbSQRt6yLcBQACZbfA_1788355627601.pdf`,
 * `fs-complet_classique_M8yvnNYRRru6thoBR_NYvg_1788944320384.pdf`). Plutôt que de dépendre du
 * préfixe exact ("complet_classique", susceptible de varier selon le modèle/type de fiche
 * générée), la regex cible uniquement la forme structurelle constante et fiable des DEUX exemples
 * connus : un segment de exactement 22 caractères alphanumériques/`_`/`-` (alphabet base64url —
 * même longueur exacte que l'ID observé dans les URLs `/infos-generales`, cohérence déjà vérifiée
 * lors du POC), immédiatement suivi de `_` puis d'un horodatage numérique puis `.pdf`, en fin de
 * nom de fichier. Ne matche jamais un nom de fichier renommé par l'utilisateur ou provenant d'une
 * autre source (retourne alors '' — jamais une extraction approximative, jamais un identifiant
 * partiel).
 */
function gwseq_ifce_extract_id_from_pdf_filename($filename) {
  $filename = basename((string) $filename);
  if (preg_match('/_([A-Za-z0-9_-]{22})_\d+\.pdf$/i', $filename, $m)) {
    return $m[1];
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

/**
 * Verrou d'identité du RÉIMPORT (correctif recette — sécurité critique, §5-13 de la demande) :
 * décide si un PDF IFCE nouvellement analysé PEUT être utilisé pour réimporter la fiche
 * $reimport_cheval_id — fonction PURE, aucune écriture, aucun accès `$_POST`/HTML. Sans écran
 * intermédiaire visible avant la preview, un utilisateur qui sélectionne PAR ERREUR le PDF d'un
 * autre cheval sur le lien "Réimporter depuis un nouveau PDF IFCE" d'une fiche précise verrait sinon
 * cette fiche transformée avec les données d'un autre animal — cette fonction bloque ce cas AVANT
 * toute écriture (voir gwseq_process_ifce_import_upload() ci-dessous, appelée juste après le
 * parsing, avant même la création du transient de prévisualisation).
 *
 * Ne s'applique JAMAIS à un import INITIAL (§13 : $reimport_cheval_id = 0, aucune identité GWS
 * existante à protéger) — seul l'appelant décide de l'invoquer, cette fonction elle-même ne
 * distingue pas ce cas, elle est simplement jamais appelée quand $reimport_cheval_id est nul.
 *
 * Retourne toujours `{ok: bool, reason: string}` — `reason` un code stable, jamais un texte déjà
 * traduit (voir gwseq_ifce_reimport_identity_error_message() pour la traduction, réutilisée aux
 * deux points de contrôle — upload ET confirmation, §12) :
 *
 * A/B — Fiche possédant déjà un `_gwseq_ifce_id` enregistré (gwseq_get_cheval_ifce_id(),
 *   cheval-fields.php) : c'est l'identité opaque déjà verrouillée qui fait foi.
 *   - Différent de l'ID du nouveau PDF -> 'id_mismatch' (BLOCAGE DUR, §6 : aucune autre
 *     vérification n'a d'importance, jamais de bouton pour forcer le remplacement).
 *   - Identique -> défense supplémentaire (§7, contre un fichier mal nommé/une corruption/un bug
 *     de parser) : nom officiel + année doivent ÉGALEMENT concorder avec la fiche existante,
 *     sinon 'id_match_name_mismatch'/'id_match_year_mismatch' (également un blocage — un ID
 *     identique ne suffit pas seul si le reste est incohérent).
 *
 * E — Fiche SANS `_gwseq_ifce_id` (migration progressive des chevaux importés avant 0.45.0, §9) :
 *   le réimport n'est autorisé QUE si nom officiel ET année de naissance concordent TOUS LES DEUX
 *   avec la fiche existante (§10 : l'année est OBLIGATOIRE ici, jamais un nom seul) —
 *   'legacy_year_missing' si l'année manque d'un côté ou de l'autre (jamais deviné),
 *   'legacy_year_mismatch'/'legacy_name_mismatch' sinon. Si les deux concordent, le réimport est
 *   autorisé ET gwseq_ifce_map_import() (déjà existant depuis 0.45.0, jamais modifié pour ce
 *   correctif) adoptera automatiquement le nouvel ID comme verrou pour les prochains réimports —
 *   cette fonction ne l'écrit jamais elle-même (fonction de décision pure).
 *
 * NOM DE RÉFÉRENCE (§8, alias commerciaux) : ni le nom détecté ni le nom existant ne sont comparés
 * via le seul titre GWS affiché — `_gwseq_ifce_nom_officiel` (déjà existant, cheval-fields.php) est
 * utilisé en PRIORITÉ des deux côtés quand disponible (nouveau PDF : `$parsed_identity['nom_officiel']`
 * si renseigné, sinon `$parsed_identity['nom']` — même convention que le parseur existant, qui ne
 * renseigne `nom_officiel` que si un alias a réellement été détecté ; fiche existante :
 * `_gwseq_ifce_nom_officiel` si déjà enregistrée, sinon le titre GWS lui-même — cas normal d'une
 * fiche jamais importée avec alias, où le titre EST déjà le nom officiel). La comparaison réutilise
 * gwseq_ifce_normalize_horse_name_for_match() (includes/ifce-production-store.php, déjà développée
 * pour le rapprochement Production/pedigree — jamais une seconde logique de normalisation).
 */
function gwseq_ifce_validate_reimport_identity($reimport_cheval_id, $parsed_identity, $new_ifce_id) {
  $reimport_cheval_id = (int) $reimport_cheval_id;
  $new_ifce_id = trim((string) $new_ifce_id);
  $parsed_identity = is_array($parsed_identity) ? $parsed_identity : array();
  $new_name = ($parsed_identity['nom_officiel'] ?? '') !== '' ? $parsed_identity['nom_officiel'] : ($parsed_identity['nom'] ?? '');
  $new_year = $parsed_identity['annee_naissance'] ?? '';

  $existing_ifce_id = gwseq_get_cheval_ifce_id($reimport_cheval_id);
  $existing_nom_officiel = (string) get_post_meta($reimport_cheval_id, '_gwseq_ifce_nom_officiel', true);
  $existing_name = $existing_nom_officiel !== '' ? $existing_nom_officiel : get_the_title($reimport_cheval_id);
  $existing_year = gwseq_get_cheval_identity($reimport_cheval_id)['annee_naissance'];

  if ($existing_ifce_id !== '') {
    if ($new_ifce_id === '' || $new_ifce_id !== $existing_ifce_id) {
      return array('ok' => false, 'reason' => 'id_mismatch');
    }
    if (gwseq_ifce_normalize_horse_name_for_match($new_name) !== gwseq_ifce_normalize_horse_name_for_match($existing_name)) {
      return array('ok' => false, 'reason' => 'id_match_name_mismatch');
    }
    if ($new_year !== '' && $existing_year !== '' && (string) $new_year !== (string) $existing_year) {
      return array('ok' => false, 'reason' => 'id_match_year_mismatch');
    }
    return array('ok' => true, 'reason' => '');
  }

  // Legacy (aucun ID enregistré) : nom ET année obligatoires et concordants (§9-10).
  if ($new_year === '' || $existing_year === '') {
    return array('ok' => false, 'reason' => 'legacy_year_missing');
  }
  if ((string) $new_year !== (string) $existing_year) {
    return array('ok' => false, 'reason' => 'legacy_year_mismatch');
  }
  if (gwseq_ifce_normalize_horse_name_for_match($new_name) !== gwseq_ifce_normalize_horse_name_for_match($existing_name)) {
    return array('ok' => false, 'reason' => 'legacy_name_mismatch');
  }
  return array('ok' => true, 'reason' => '');
}

/**
 * Message explicite associé à un code de rejet de gwseq_ifce_validate_reimport_identity() —
 * réutilisé aux DEUX points de contrôle (upload et confirmation, §12), jamais un texte dupliqué.
 * Volontairement le MÊME message générique pour tous les cas de blocage (§6 : jamais de détail
 * permettant de deviner l'identité IFCE d'un autre cheval par tâtonnement) — la distinction du code
 * reste disponible côté serveur/logs pour un futur diagnostic, jamais affichée à l'utilisateur.
 */
function gwseq_ifce_reimport_identity_error_message($reason) {
  return __('Ce PDF IFCE ne correspond pas au cheval actuellement édité. Aucune donnée n’a été modifiée.', 'gws-core');
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
function gwseq_process_ifce_import_upload($validated_pdf_path, $reimport_cheval_id = 0, $original_filename = '') {
  // Lu UNE SEULE FOIS en octets bruts (Lot 2B.2) : la Zone Sujet (gwseq_ifce_extract_pdf_text_from_string(),
  // page 1 uniquement, inchangée) ET la Zone Production (gwseq_ifce_extract_production_from_pdf_string(),
  // toutes les pages, jamais partagée avec la Zone Sujet — voir includes/ifce-production-parser.php)
  // partent chacune indépendamment de ces mêmes octets, jamais l'une du texte déjà extrait par l'autre.
  $pdf_binary = file_get_contents($validated_pdf_path);
  if ($pdf_binary === false) $pdf_binary = '';
  // Suppression immédiate du fichier temporaire (§11) : le PDF n'est jamais conservé, que
  // l'analyse réussisse ou échoue ensuite — ce n'est qu'une source d'import, jamais une nouvelle
  // source de vérité stockée sur la fiche.
  if (file_exists($validated_pdf_path)) @unlink($validated_pdf_path);

  $text = gwseq_ifce_extract_pdf_text_from_string($pdf_binary);
  $parsed = gwseq_ifce_parse_text($text);
  if (empty($parsed['valid'])) {
    return array(
      'redirect' => gwseq_ifce_import_page_url(),
      'notice' => __('Ce document n’a pas été reconnu comme une fiche de synthèse IFCE. Vérifiez qu’il s’agit bien du PDF complet téléchargé depuis Info Chevaux, ou créez la fiche manuellement.', 'gws-core'),
    );
  }

  // Production directe (Lot 2B.2, §5) : recherchée UNIQUEMENT pour un sujet femelle — garde de sexe
  // ET garde de performance au même endroit, avant même toute tentative de scan des pages suivantes.
  $parsed['production'] = ($parsed['identity']['sexe'] === 'female')
    ? gwseq_ifce_extract_production_from_pdf_string($pdf_binary)
    : array('found' => false, 'entries' => array(), 'ignored' => array('saillie' => 0, 'sans_nom' => 0, 'niveau_superieur' => 0));

  // Identité IFCE — ID opaque (Lot IFCE — clôture POC, §3) : extrait du nom de fichier ORIGINAL
  // (voir gwseq_ifce_extract_id_from_pdf_filename() ci-dessus), jamais du chemin temporaire
  // ($validated_pdf_path, un nom générique attribué par PHP) ni du contenu du PDF (non fourni par
  // la fiche de synthèse elle-même). Chaîne vide si le nom de fichier ne correspond pas au format
  // attendu (renommé, autre source...) — $parsed['ifce_id'] reste alors simplement absent/vide,
  // jamais une erreur bloquante pour le reste de l'import.
  $parsed['ifce_id'] = gwseq_ifce_extract_id_from_pdf_filename($original_filename);

  $reimport_cheval_id = gwseq_sanitize_ifce_reimport_cheval_id($reimport_cheval_id);

  // Verrou d'identité du réimport (correctif recette, §5/§11/§13) : contrôlé ICI, AVANT toute
  // création de transient — donc avant que la preview elle-même ne soit jamais atteinte, et a
  // fortiori avant toute écriture. Ne s'applique jamais à un import initial ($reimport_cheval_id
  // nul juste au-dessus). Le fichier temporaire est déjà supprimé (voir plus haut) qu'il s'agisse
  // d'un succès ou de cet échec — aucun résidu dans les deux cas.
  if ($reimport_cheval_id) {
    $identity_check = gwseq_ifce_validate_reimport_identity($reimport_cheval_id, $parsed['identity'], $parsed['ifce_id']);
    if (!$identity_check['ok']) {
      return array(
        'redirect' => gwseq_ifce_import_page_url(array('gwseq_reimport_cheval_id' => $reimport_cheval_id)),
        'notice' => gwseq_ifce_reimport_identity_error_message($identity_check['reason']),
      );
    }
  }

  // Enrichissement opportuniste du N° SIRE via SHF (Lot SHF, §1-3 de la demande) : ICI, APRÈS le
  // verrou d'identité du réimport ci-dessus (jamais avant — ne contacte jamais SHF pour un PDF qui
  // sera de toute façon refusé), et AU MAXIMUM un appel, UNIQUEMENT si l'ID IFCE a été extrait ET
  // qu'un SIRE ne sera de toute façon pas autrement disponible : ni détecté par CE PDF
  // ($parsed['identity']['sire']), ni déjà enregistré sur la fiche réimportée le cas échéant (§1,
  // "si le cheval possède déjà un SIRE non vide : aucun appel SHF"). Le résultat éventuel n'est
  // qu'une DONNÉE PROPOSÉE, transportée dans le transient exactement comme le reste de $parsed —
  // jamais écrite avant confirmation explicite de l'utilisateur (voir gwseq_ifce_map_import(),
  // includes/ifce-import-mapper.php, seul consommateur de $parsed['shf_sire']).
  // gwseq_ifce_shf_lookup_sire_by_id() (includes/ifce-shf-enrichment.php) ne lève jamais d'exception
  // et retourne '' au moindre doute (§6/§7) : une indisponibilité SHF ne bloque donc jamais cet
  // import, quel que soit son résultat.
  $parsed['shf_sire'] = '';
  if ($parsed['ifce_id'] !== '' && $parsed['identity']['sire'] === '') {
    $existing_sire_for_reimport = $reimport_cheval_id ? gwseq_get_cheval_identity($reimport_cheval_id)['sire'] : '';
    if ($existing_sire_for_reimport === '') {
      $expected_name = ($parsed['identity']['nom_officiel'] ?? '') !== ''
        ? $parsed['identity']['nom_officiel']
        : ($parsed['identity']['nom'] ?? '');
      $parsed['shf_sire'] = gwseq_ifce_shf_lookup_sire_by_id($parsed['ifce_id'], $expected_name);
    }
  }

  // Aperçu de la dérivation UELN — correctif "UELN Selle Français" : calcule ICI, à l'upload, EXACTEMENT
  // la même valeur finale que celle que gwseq_ifce_map_import() écrira réellement à la confirmation
  // (réutilise gwseq_ifce_resolve_nondestructive_identity_value() et gwseq_ifce_derive_ueln_from_sire(),
  // jamais une seconde implémentation de cette règle) — purement pour l'afficher en prévisualisation,
  // recalculée à neuf depuis l'état ACTUEL de la fiche à la confirmation (jamais fait confiance à
  // cette valeur de preview pour l'écriture réelle). "Le SIRE peut provenir de SHF ou être déjà
  // disponible" : $final_sire_preview couvre les deux cas indifféremment, y compris un réimport où le
  // SIRE existe déjà sur la fiche (donc SANS jamais avoir appelé SHF ci-dessus).
  $existing_identity_for_preview = $reimport_cheval_id ? gwseq_get_cheval_identity($reimport_cheval_id) : array('sire' => '', 'ueln' => '');
  $sire_detected_or_shf = $parsed['identity']['sire'] !== '' ? $parsed['identity']['sire'] : $parsed['shf_sire'];
  $final_sire_preview = gwseq_ifce_resolve_nondestructive_identity_value($sire_detected_or_shf, $existing_identity_for_preview['sire']);
  $final_ueln_preview = gwseq_ifce_resolve_nondestructive_identity_value($parsed['identity']['ueln'], $existing_identity_for_preview['ueln']);
  $parsed['derived_ueln'] = ($final_ueln_preview === '')
    ? gwseq_ifce_derive_ueln_from_sire($parsed['identity']['race'], $final_sire_preview)
    : '';

  $token = wp_generate_password(32, false, false);
  gwseq_set_ifce_import_transient($token, $parsed, $reimport_cheval_id);

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

  $reimport_cheval_id = isset($_POST['gwseq_reimport_cheval_id']) ? absint(wp_unslash($_POST['gwseq_reimport_cheval_id'])) : 0;
  $original_filename = isset($file['name']) ? sanitize_file_name(wp_unslash($file['name'])) : '';
  $result = gwseq_process_ifce_import_upload($validated_path, $reimport_cheval_id, $original_filename);
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
function gwseq_process_ifce_import_confirm($token, $sections, $parent_choices = array(), $production_choices = array()) {
  $data = gwseq_get_ifce_import_transient($token);
  if ($data === false) {
    return array(
      'redirect' => gwseq_ifce_import_page_url(),
      'notice' => __('Cet import a expiré ou n’est plus disponible. Veuillez recommencer.', 'gws-core'),
    );
  }

  $parsed = $data['parsed'];

  // Réimport (Lot 2B.2, §21) : une fiche EXISTANTE encore valide au moment de la confirmation est
  // mise à jour plutôt que dupliquée — jamais fait confiance à la seule valeur déjà stockée dans le
  // transient, revalidée ici (gwseq_sanitize_ifce_reimport_cheval_id(), même contrôle qu'à l'upload :
  // existence réelle, type de contenu, droit d'édition de l'utilisateur courant).
  $reimport_cheval_id = gwseq_sanitize_ifce_reimport_cheval_id($data['reimport_cheval_id'] ?? 0);

  // Second contrôle du verrou d'identité (correctif recette, §12) : revalidé ICI, à partir de
  // l'état ACTUEL de la fiche (jamais mis en cache depuis l'upload — entre upload et confirmation,
  // la fenêtre du transient dure 15 minutes, pendant laquelle la fiche cible a pu être modifiée par
  // ailleurs). $parsed vient du transient serveur, jamais d'un champ POST/hidden resoumis par le
  // client (voir le docblock de fichier : "un utilisateur ne peut jamais faire écrire une donnée
  // qu'il n'a pas d'abord vue sur l'écran de prévisualisation") — mais la fiche CIBLE, elle, a pu
  // changer depuis. Échec ici -> transient supprimé, AUCUNE écriture, exactement comme à l'upload.
  if ($reimport_cheval_id) {
    $identity_check = gwseq_ifce_validate_reimport_identity($reimport_cheval_id, $parsed['identity'], $parsed['ifce_id'] ?? '');
    if (!$identity_check['ok']) {
      gwseq_delete_ifce_import_transient($token);
      return array(
        'redirect' => gwseq_ifce_import_page_url(array('gwseq_reimport_cheval_id' => $reimport_cheval_id)),
        'notice' => gwseq_ifce_reimport_identity_error_message($identity_check['reason']),
      );
    }
  }

  if ($reimport_cheval_id) {
    $post_id = $reimport_cheval_id;
  } else {
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
  }

  gwseq_ifce_map_import($post_id, $parsed, $sections, $parent_choices, $production_choices);
  gwseq_delete_ifce_import_transient($token);

  return array('redirect' => get_edit_post_link($post_id, 'raw'), 'notice' => null);
}

/**
 * Sanitise UN choix de rattachement PROBABLE de Production (Lot 2B.2, §14) déjà déslashé par
 * l'appelant (gwseq_handle_ifce_import_confirm() ci-dessous) — jamais 'gws'/'skip' comme pour
 * Père/Mère (concept non pertinent ici : une entrée de Production reste simplement externe tant
 * qu'aucun rattachement n'est confirmé, il n'y a rien à "ignorer" explicitement).
 */
function gwseq_sanitize_ifce_production_choice($raw) {
  $mode = isset($raw['mode']) ? sanitize_key($raw['mode']) : 'external';
  if ($mode === 'link') {
    $horse_id = gwseq_sanitize_horse_parent_gws_id($raw['horse_id'] ?? 0, 0);
    if ($horse_id) return array('mode' => 'link', 'horse_id' => $horse_id);
  }
  return array('mode' => 'external');
}

/**
 * Sanitise le choix Père/Mère GWS soumis depuis l'écran de prévisualisation (§3 de la demande) —
 * `$_POST`-shaped, jamais un accès direct à `$_POST` ailleurs que dans gwseq_handle_ifce_import_confirm()
 * ci-dessous (même discipline que le reste du module). 'gws' n'est retenu que si l'identifiant
 * soumis correspond réellement à une fiche Cheval existante (gwseq_sanitize_horse_parent_gws_id(),
 * MÊME fonction que la saisie manuelle du pedigree — jamais une seconde validation dupliquée).
 *
 * $safe_default_gws_horse_id (Lot IFCE — clôture POC, défense en profondeur §19) : identifiant de
 * la fiche GWS ACTIVEMENT liée à ce rôle AVANT cette soumission (0 si aucune, ou premier import).
 * Le champ radio $mode_key est TOUJOURS présent dans la soumission normale (trois boutons du même
 * groupe, l'un des trois est toujours coché par défaut par gwseq_render_ifce_preview_parent_choice() —
 * voir includes/ifce-import-mapper.php, gwseq_ifce_resolve_parent_proposal()) : le repli "champ
 * totalement absent" ci-dessous ne devrait donc normalement jamais se produire via ce formulaire.
 * S'il se produisait malgré tout (requête malformée, formulaire modifié…), retomber sur 'external'
 * comme avant ce correctif remplacerait silencieusement un parent GWS déjà actif par un ascendant
 * externe — exactement ce que §19 interdit. Repli désormais sur la relation déjà active s'il y en a
 * une ; sur 'external' uniquement s'il n'y en avait aucune (comportement inchangé dans ce cas).
 */
function gwseq_sanitize_ifce_preview_parent_choice($raw, $mode_key, $horse_id_key, $safe_default_gws_horse_id = 0) {
  if (!isset($raw[$mode_key])) {
    return $safe_default_gws_horse_id
      ? array('mode' => 'gws', 'horse_id' => (int) $safe_default_gws_horse_id)
      : array('mode' => 'external');
  }

  $mode = sanitize_key(wp_unslash($raw[$mode_key]));
  if ($mode === 'gws') {
    $horse_id = gwseq_sanitize_horse_parent_gws_id($raw[$horse_id_key] ?? 0, 0);
    if (!$horse_id) return array('mode' => 'external');
    return array('mode' => 'gws', 'horse_id' => $horse_id);
  }
  if ($mode === 'skip') return array('mode' => 'skip');
  return array('mode' => 'external');
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
    'production' => !empty($_POST['gwseq_ifce_import_production']),
  );
  // Choix Père/Mère GWS (§3 de la demande) : lu ici quel que soit l'état de la case "Importer le
  // pedigree" ci-dessus — gwseq_ifce_map_import() ignore de toute façon entièrement ce paramètre
  // dès que $sections['pedigree'] est faux, exactement comme le reste de la section pedigree.
  //
  // Défense en profondeur (Lot IFCE — clôture POC, §19) : relit le transient UNE fois ici,
  // uniquement pour connaître un éventuel réimport et la relation GWS déjà active par rôle —
  // gwseq_process_ifce_import_confirm() ci-dessus relira le même transient normalement (fonction
  // volontairement "pure", jamais couplée à $_POST — voir son docblock) ; cette relecture
  // supplémentaire est sans effet de bord (get_transient()) et évite de faire porter à cette
  // fonction pure la responsabilité d'un repli sécurisé qui dépend de $_POST.
  $reimport_cheval_id_for_defaults = 0;
  $confirm_data = gwseq_get_ifce_import_transient($token);
  if (is_array($confirm_data)) {
    $reimport_cheval_id_for_defaults = gwseq_sanitize_ifce_reimport_cheval_id($confirm_data['reimport_cheval_id'] ?? 0);
  }
  $safe_default_father_id = 0;
  $safe_default_mother_id = 0;
  if ($reimport_cheval_id_for_defaults) {
    $existing_father = gwseq_get_horse_parent($reimport_cheval_id_for_defaults, 'father');
    $existing_mother = gwseq_get_horse_parent($reimport_cheval_id_for_defaults, 'mother');
    if ($existing_father['mode'] === 'gws') $safe_default_father_id = $existing_father['horse_id'];
    if ($existing_mother['mode'] === 'gws') $safe_default_mother_id = $existing_mother['horse_id'];
  }

  $parent_choices = array(
    'father' => gwseq_sanitize_ifce_preview_parent_choice($_POST, 'gwseq_ifce_pere_mode', 'gwseq_ifce_pere_gws_id', $safe_default_father_id),
    'mother' => gwseq_sanitize_ifce_preview_parent_choice($_POST, 'gwseq_ifce_mere_mode', 'gwseq_ifce_mere_gws_id', $safe_default_mother_id),
  );

  // Rapprochements PROBABLES de Production confirmés par l'utilisateur (Lot 2B.2, §14) : un tableau
  // indexé $_POST['gwseq_ifce_production_choice'][$i] = {mode, horse_id} — un index absent (aucune
  // case cochée pour cette entrée) équivaut à "external", exactement comme le rapprochement Père/Mère.
  $production_choices = array();
  $raw_production_choices = is_array($_POST['gwseq_ifce_production_choice'] ?? null) ? wp_unslash($_POST['gwseq_ifce_production_choice']) : array();
  foreach ($raw_production_choices as $i => $raw_choice) {
    $production_choices[(int) $i] = gwseq_sanitize_ifce_production_choice(is_array($raw_choice) ? $raw_choice : array());
  }

  $result = gwseq_process_ifce_import_confirm($token, $sections, $parent_choices, $production_choices);
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
      gwseq_render_ifce_import_preview($token, $data['parsed'], (int) ($data['reimport_cheval_id'] ?? 0));
      return;
    }
    gwseq_render_ifce_import_upload_form(__('Cet import a expiré ou n’est plus disponible. Veuillez recommencer.', 'gws-core'));
    return;
  }

  // Réimport (Lot 2B.2, §21) : lien "Réimporter depuis l'IFCE" d'une fiche Cheval EXISTANTE — voir
  // gwseq_render_cheval_ifce_reimport_box(), includes/ifce-production-store.php. Revalidé ici comme
  // partout ailleurs (gwseq_sanitize_ifce_reimport_cheval_id()) : un identifiant invalide/périmé
  // retombe simplement sur le formulaire de premier import, jamais une erreur bloquante.
  $reimport_cheval_id = isset($_GET['gwseq_reimport_cheval_id']) ? absint(wp_unslash($_GET['gwseq_reimport_cheval_id'])) : 0;
  gwseq_render_ifce_import_upload_form($notice, gwseq_sanitize_ifce_reimport_cheval_id($reimport_cheval_id));
}

function gwseq_render_ifce_import_upload_form($error = '', $reimport_cheval_id = 0) {
  $reimport_cheval_id = (int) $reimport_cheval_id;
  ?>
  <div class="wrap">
    <h1><?php esc_html_e('Importer une fiche IFCE', 'gws-core'); ?></h1>
    <?php if ($error !== '') : ?>
      <div class="notice notice-error"><p><?php echo esc_html($error); ?></p></div>
    <?php endif; ?>
    <?php if ($reimport_cheval_id) : ?>
      <div class="notice notice-info"><p><?php echo esc_html(sprintf(
        /* translators: %s: nom de la fiche Cheval déjà existante concernée par ce réimport */
        __('Réimport pour la fiche « %s » — rien ne sera modifié avant votre validation explicite à l’étape suivante.', 'gws-core'),
        get_the_title($reimport_cheval_id)
      )); ?></p></div>
    <?php endif; ?>
    <p><?php esc_html_e('Où trouver cette fiche ? Rendez-vous sur Info Chevaux de l’IFCE, recherchez votre cheval avec son nom ou son numéro SIRE, ouvrez sa fiche puis téléchargez sa fiche de synthèse PDF. Importez ensuite ici le PDF complet.', 'gws-core'); ?></p>
    <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
      <?php wp_nonce_field(GWSEQ_IFCE_IMPORT_NONCE_ACTION, GWSEQ_IFCE_IMPORT_NONCE_FIELD); ?>
      <input type="hidden" name="action" value="gwseq_ifce_import_upload">
      <?php if ($reimport_cheval_id) : ?>
        <input type="hidden" name="gwseq_reimport_cheval_id" value="<?php echo esc_attr($reimport_cheval_id); ?>">
      <?php endif; ?>
      <p><input type="file" name="gwseq_ifce_pdf" accept="application/pdf" required></p>
      <p><?php submit_button(__('Analyser le PDF', 'gws-core'), 'primary', 'submit', false); ?></p>
    </form>
    <p><a href="<?php echo esc_url(admin_url('post-new.php?post_type=' . GWSEQ_CPT_CHEVAL)); ?>"><?php esc_html_e('Ou créer une fiche manuellement', 'gws-core'); ?></a></p>
  </div>
  <?php
}

/**
 * Bloc de décision Père OU Mère sur l'écran de prévisualisation IFCE (§3 de la demande,
 * "rattacher Père/Mère à des chevaux GWS pendant l'import") — UNIQUEMENT pour ce parent DIRECT,
 * jamais les générations suivantes (qui restent gérées par l'arbre externe importé, ou par le
 * pedigree propre du cheval GWS lié une fois la relation créée — voir
 * gwseq_ifce_map_import()/includes/ifce-import-mapper.php). $branch est le nœud {name, race,
 * race_autre, annee_naissance, ...} déjà produit par le parseur (jamais modifié ici, purement une
 * décision sur la DESTINATION de cette donnée) ; $mode_key/$horse_id_key sont les noms de champs
 * `$_POST` lus par gwseq_sanitize_ifce_preview_parent_choice(). Les trois blocs (aucun JavaScript
 * requis, formulaire toujours fonctionnel sans script) restent simultanément visibles — même
 * convention de repli que gwseq_render_cheval_parent_fields() (saisie manuelle du pedigree,
 * includes/cheval-pedigree.php) : le serveur reste seul autoritaire sur le choix réellement soumis.
 */
function gwseq_render_ifce_preview_parent_choice($role, $branch, $label, $mode_key, $horse_id_key, $child_annee_naissance, $reimport_cheval_id = 0) {
  if (empty($branch)) return; // rien de détecté pour ce parent : aucun choix à proposer (§ absence de donnée = absence d'affichage)
  $race_label = gwseq_cheval_race_label($branch['race'] ?? '', $branch['race_autre'] ?? '') ?: __('race non détectée', 'gws-core');
  $annee = $branch['annee_naissance'] ?? '';
  $detected_summary = trim($branch['name'] . ' — ' . $race_label . ($annee !== '' ? ' — ' . $annee : ''));

  // Rapprochement automatique (Lot IFCE — clôture POC, correction du rapprochement pedigree) : voir
  // gwseq_ifce_resolve_parent_proposal() (includes/ifce-import-mapper.php) pour le détail complet
  // des 4 cas (A candidat unique / B ambigu / C aucun / D déjà lié) — cette fonction PURE décide,
  // ce rendu se contente d'en afficher le résultat.
  $proposal = gwseq_ifce_resolve_parent_proposal($role, $branch, $reimport_cheval_id, $child_annee_naissance);
  $default_mode = $proposal['default_mode'];
  $preselected_id = $proposal['preselected_horse_id'];
  ?>
  <div data-gwseq-ifce-parent-choice="<?php echo esc_attr($role); ?>" style="margin: 0.75em 0; padding: 0.75em; border: 1px solid #dcdcde;">
    <p><strong><?php echo esc_html($label); ?></strong> <?php echo esc_html(sprintf(/* translators: %s: nom — race — année détectés pour ce parent */ __('détecté : %s', 'gws-core'), $detected_summary)); ?></p>
    <?php if ($proposal['note'] === 'already_linked') : ?>
      <p class="description"><?php echo esc_html(sprintf(
        /* translators: %s: nom de la fiche Cheval déjà liée */
        __('Déjà lié à %s — ce rattachement sera conservé sauf modification explicite ci-dessous.', 'gws-core'),
        get_the_title($preselected_id)
      )); ?></p>
    <?php elseif ($proposal['note'] === 'unique_match') : ?>
      <p class="description"><?php echo esc_html(sprintf(
        /* translators: %s: nom de la fiche Cheval candidate, %d: année de naissance */
        __('Candidat détecté par nom et année : %1$s (né(e) en %2$s) — présélectionné ci-dessous sur « Lier à un cheval déjà enregistré ». Vérifiez puis validez, ou choisissez une autre option.', 'gws-core'),
        get_the_title($preselected_id),
        gwseq_get_cheval_identity($preselected_id)['annee_naissance']
      )); ?></p>
    <?php endif; ?>
    <p>
      <label>
        <input type="radio" name="<?php echo esc_attr($mode_key); ?>" value="external"<?php checked($default_mode, 'external'); ?>>
        <?php esc_html_e('Importer comme ascendant externe', 'gws-core'); ?>
      </label><br>
      <label>
        <input type="radio" name="<?php echo esc_attr($mode_key); ?>" value="gws"<?php checked($default_mode, 'gws'); ?>>
        <?php
        if ($preselected_id) {
          echo esc_html(sprintf(
            /* translators: %s: nom de la fiche Cheval déjà enregistrée */
            __('Lier à un cheval déjà enregistré → %s', 'gws-core'),
            get_the_title($preselected_id)
          ));
        } else {
          esc_html_e('Lier à un cheval déjà enregistré', 'gws-core');
        }
        ?>
      </label><br>
      <label>
        <input type="radio" name="<?php echo esc_attr($mode_key); ?>" value="skip">
        <?php esc_html_e('Ne pas importer ce parent', 'gws-core'); ?>
      </label>
    </p>
    <p>
      <select name="<?php echo esc_attr($horse_id_key); ?>">
        <option value="0"><?php esc_html_e('— Choisir un cheval —', 'gws-core'); ?></option>
        <?php foreach (gwseq_cheval_parent_candidates(0) as $candidate) :
          // Même règle métier que la saisie manuelle du pedigree (sexe, année), réutilisée SANS LA
          // DUPLIQUER — voir gwseq_ifce_preview_parent_candidate_rejection_reason() ci-dessus
          // (includes/cheval-pedigree.php) pour la raison exacte de son existence à ce stade
          // (la fiche important n'existe pas encore, donc aucune lecture en base possible ici) ;
          // candidats visibles mais désactivés, jamais retirés de la liste.
          $rejection_reason = gwseq_ifce_preview_parent_candidate_rejection_reason($role, $candidate->ID, $child_annee_naissance);
          $option_label = get_the_title($candidate);
          if ($rejection_reason !== '') $option_label .= ' — ' . gwseq_horse_parent_rejection_reason_label($rejection_reason);
        ?>
          <option value="<?php echo esc_attr($candidate->ID); ?>"<?php selected($candidate->ID, $preselected_id); ?> <?php disabled($rejection_reason !== ''); ?>><?php echo esc_html($option_label); ?></option>
        <?php endforeach; ?>
      </select>
      <span class="description"><?php esc_html_e('Utilisé uniquement si « Lier à un cheval déjà enregistré » est sélectionné ci-dessus, et si « Importer le pedigree » reste coché plus bas.', 'gws-core'); ?></span>
    </p>
  </div>
  <?php
}

/**
 * Bloc Production sur l'écran de prévisualisation IFCE (Lot 2B.2, §22) — rendu UNIQUEMENT si des
 * produits directs ont été détectés (identity['sexe'] déjà "female" côté extraction, sinon
 * $production['entries'] est structurellement toujours vide, voir gwseq_process_ifce_import_upload()) :
 * une par une, l'année/le nom/le père/les indices détectés, puis, quand pertinent, le rattachement
 * CERTAIN (déjà acquis, jamais une case à cocher) ou une case à cocher de rattachement PROBABLE
 * (jamais automatique, §14). Le nombre de lignes ignorées (saillie/sans nom/niveau 2+) est affiché à
 * titre purement informatif (§22 "produits ignorés lorsque pertinent"), jamais listées une par une.
 */
function gwseq_render_ifce_preview_production_section($production, $reimport_cheval_id) {
  $entries = $production['entries'] ?? array();
  if (empty($entries)) return;

  $ignored = $production['ignored'] ?? array();
  $ignored_total = ((int) ($ignored['saillie'] ?? 0)) + ((int) ($ignored['sans_nom'] ?? 0)) + ((int) ($ignored['niveau_superieur'] ?? 0));

  echo '<p><strong>' . esc_html(sprintf(
    /* translators: %d: nombre de produits directs détectés */
    _n('Production : %d produit direct détecté.', 'Production : %d produits directs détectés.', count($entries), 'gws-core'),
    count($entries)
  )) . '</strong></p>';

  if ($ignored_total > 0) {
    echo '<p class="description">' . esc_html(sprintf(
      /* translators: %d: nombre de lignes ignorées (saillie en cours, produit sans nom, ou petit-enfant) */
      _n('%d ligne ignorée (saillie en cours, produit sans identification, ou petit-enfant non importé).', '%d lignes ignorées (saillie en cours, produits sans identification, ou petits-enfants non importés).', $ignored_total, 'gws-core'),
      $ignored_total
    )) . '</p>';
  }

  echo '<table class="widefat gwseq-ifce-preview-production"><thead><tr>'
    . '<th>' . esc_html__('Année', 'gws-core') . '</th>'
    . '<th>' . esc_html__('Nom', 'gws-core') . '</th>'
    . '<th>' . esc_html__('Père', 'gws-core') . '</th>'
    . '<th>' . esc_html__('Indices', 'gws-core') . '</th>'
    . '<th>' . esc_html__('Rattachement', 'gws-core') . '</th>'
    . '</tr></thead><tbody>';

  foreach ($entries as $i => $entry) {
    $indices_labels = array();
    foreach (gwseq_cheval_sport_indice_keys() as $key) {
      if (($entry[$key]['valeur'] ?? '') === '') continue;
      $indices_labels[] = strtoupper($key) . ' ' . $entry[$key]['valeur'];
    }

    $certain_id = gwseq_ifce_find_certain_production_match($reimport_cheval_id, $entry['nom'], $entry['annee']);
    $probable_id = $certain_id ? 0 : gwseq_ifce_find_probable_production_match($entry['nom'], $entry['annee']);

    echo '<tr>';
    echo '<td>' . esc_html($entry['annee']) . '</td>';
    echo '<td>' . esc_html($entry['nom']) . '</td>';
    echo '<td>' . esc_html($entry['pere']) . '</td>';
    echo '<td>' . esc_html($indices_labels ? implode(', ', $indices_labels) : '—') . '</td>';
    echo '<td>';
    if ($certain_id) {
      echo '<span>' . esc_html(sprintf(/* translators: %s: nom de la fiche Cheval déjà liée */ __('Rattachement certain : %s', 'gws-core'), get_the_title($certain_id))) . '</span>';
      gwseq_render_ifce_preview_production_indice_diff($certain_id, $entry);
      gwseq_render_ifce_preview_production_maternity_note($certain_id, $reimport_cheval_id);
    } elseif ($probable_id) {
      echo '<label><input type="checkbox" name="gwseq_ifce_production_choice[' . esc_attr($i) . '][mode]" value="link">'
        . ' ' . esc_html(sprintf(/* translators: %s: nom de la fiche Cheval candidate, %d: année de naissance */ __('Rattacher à %1$s (né(e) en %2$s)', 'gws-core'), get_the_title($probable_id), gwseq_get_cheval_identity($probable_id)['annee_naissance'])) . '</label>'
        . '<input type="hidden" name="gwseq_ifce_production_choice[' . esc_attr($i) . '][horse_id]" value="' . esc_attr($probable_id) . '">';
      gwseq_render_ifce_preview_production_indice_diff($probable_id, $entry);
      gwseq_render_ifce_preview_production_maternity_note($probable_id, $reimport_cheval_id);
    } else {
      echo '<span class="description">' . esc_html__('Aucun rapprochement — restera un produit externe', 'gws-core') . '</span>';
    }
    echo '</td>';
    echo '</tr>';
  }

  echo '</tbody></table>';
}

/**
 * Évolution proposée d'un indice sportif sur la fiche Cheval liée $horse_id (§15-19) — "X → Y",
 * jamais affichée quand l'entrée ne porte tout simplement aucune valeur pour cet indice (§ "jamais
 * une valeur inventée"), ni quand la valeur détectée est strictement identique à celle déjà
 * enregistrée (rien à proposer). Purement informatif ici : l'application réelle n'a lieu qu'à la
 * validation globale de l'import (gwseq_ifce_map_production(), includes/ifce-import-mapper.php).
 */
function gwseq_render_ifce_preview_production_indice_diff($horse_id, $entry) {
  $diffs = array();
  foreach (gwseq_cheval_sport_indice_keys() as $key) {
    $new_valeur = $entry[$key]['valeur'] ?? '';
    if ($new_valeur === '') continue;
    $current = gwseq_get_cheval_sport_indice($horse_id, $key);
    if ((string) ($current['valeur'] ?? '') === (string) $new_valeur) continue;
    $current_label = $current['valeur'] !== '' ? $current['valeur'] : __('non renseigné', 'gws-core');
    $diffs[] = strtoupper($key) . ' : ' . $current_label . ' → ' . $new_valeur;
  }
  if ($diffs) echo '<br><span class="description">' . esc_html(implode(' — ', $diffs)) . '</span>';
}

/**
 * Signal de cohérence bidirectionnelle Production -> filiation (correctif de recette, §Cas B/C/D/E,
 * gwseq_ifce_production_maternity_case(), includes/ifce-production-store.php) — purement informatif,
 * l'application réelle n'a lieu qu'à la validation globale de l'import
 * (gwseq_ifce_map_production(), includes/ifce-import-mapper.php). Rendu UNIQUEMENT lorsque la fiche
 * jument existe déjà réellement ($jument_id, un réimport) : pour un tout premier import, elle n'est
 * créée qu'à la confirmation, son identité n'est donc pas encore comparable ici — la conversion/
 * création n'en reste pas moins correctement appliquée à ce moment-là (le mapper reçoit toujours le
 * post_id réel, qu'il vienne d'être créé ou qu'il s'agisse d'un réimport), simplement non
 * prévisualisée pour ce cas précis.
 */
function gwseq_render_ifce_preview_production_maternity_note($linked_id, $jument_id) {
  if (!$jument_id) return;
  $case = gwseq_ifce_production_maternity_case($linked_id, $jument_id);
  if ($case === 'noop') return;

  if ($case === 'convert') {
    echo '<br><span class="description">' . esc_html(sprintf(
      /* translators: %s: nom de la fiche Cheval jument */
      __('La validation convertira aussi sa mère externe déjà enregistrée en relation vers %s.', 'gws-core'),
      get_the_title($jument_id)
    )) . '</span>';
    return;
  }
  if ($case === 'create') {
    echo '<br><span class="description">' . esc_html(sprintf(
      /* translators: %s: nom de la fiche Cheval jument */
      __('La validation créera aussi la relation Mère vers %s.', 'gws-core'),
      get_the_title($jument_id)
    )) . '</span>';
    return;
  }

  $mother = gwseq_get_horse_parent($linked_id, 'mother');
  if ($case === 'conflict_gws') {
    echo '<br><span class="description">' . esc_html(sprintf(
      /* translators: %s: nom de la fiche Cheval déjà enregistrée comme mère */
      __('⚠ possède déjà une autre mère enregistrée (%s) — ne sera jamais remplacée automatiquement.', 'gws-core'),
      get_the_title($mother['horse_id'])
    )) . '</span>';
  } elseif ($case === 'conflict_external') {
    echo '<br><span class="description">' . esc_html(sprintf(
      /* translators: %s: nom de l'ascendant externe déjà enregistré comme mère */
      __('⚠ possède déjà une autre mère externe enregistrée (%s) — ne sera jamais remplacée automatiquement.', 'gws-core'),
      $mother['external']['name'] ?? ''
    )) . '</span>';
  }
}

function gwseq_render_ifce_import_preview($token, $parsed, $reimport_cheval_id = 0) {
  $reimport_cheval_id = (int) $reimport_cheval_id;
  $identity = $parsed['identity'];
  $indices = $parsed['indices'];
  $pedigree = $parsed['pedigree'];
  $production = $parsed['production'] ?? array('found' => false, 'entries' => array(), 'ignored' => array());

  $race_label = $identity['race'] === 'autre'
    ? $identity['race_autre']
    : (gwseq_race_referentiel_display_label($identity['race']) ?: __('non détectée', 'gws-core'));
  $robe_label = $identity['robe'] === 'autre'
    ? $identity['robe_autre']
    : (gwseq_cheval_robe_options()[$identity['robe']] ?? __('non détectée', 'gws-core'));
  $sexe_label = gwseq_cheval_sexe_options()[$identity['sexe']] ?? __('non détecté', 'gws-core');
  $taille_label = $identity['taille_cm'] !== ''
    ? sprintf(/* translators: %s: taille en mètres, ex. "1,68" */ __('%s m', 'gws-core'), number_format($identity['taille_cm'] / 100, 2))
    : __('non détectée', 'gws-core');
  $annee_label = $identity['annee_naissance'] !== '' ? $identity['annee_naissance'] : __('non détectée', 'gws-core');

  // Ajustement UX (recette runtime, §8 de la demande) : chaque valeur détectée était auparavant
  // concaténée sur une seule ligne séparée par des virgules (ex. "KWPN, Mâle, Gris, non détectée,
  // 2001"), sans jamais préciser à QUOI chaque terme correspondait — un "non détectée" isolé ne
  // permettait pas de savoir s'il s'agissait de la taille, de la robe ou d'autre chose. Remplacé par
  // des lignes explicitement étiquetées, une par donnée, strictement à partir des MÊMES variables
  // déjà calculées ci-dessus (aucune donnée ni logique de reconnaissance modifiée, purement
  // l'affichage).
  $identity_rows = array(
    __('Race / Stud-book', 'gws-core') => $race_label,
    __('Sexe', 'gws-core') => $sexe_label,
    __('Robe', 'gws-core') => $robe_label,
    __('Taille', 'gws-core') => $taille_label,
    __('Année de naissance', 'gws-core') => $annee_label,
  );

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
    <?php if ($reimport_cheval_id) : ?>
      <div class="notice notice-info"><p><?php echo esc_html(sprintf(
        /* translators: %s: nom de la fiche Cheval déjà existante concernée par ce réimport */
        __('Réimport pour la fiche « %s » — les sections cochées ci-dessous mettront à jour cette fiche existante, jamais une nouvelle fiche.', 'gws-core'),
        get_the_title($reimport_cheval_id)
      )); ?></p></div>
    <?php endif; ?>
    <p><strong><?php echo esc_html(sprintf(/* translators: %s: nom du cheval détecté */ __('Cheval reconnu : %s', 'gws-core'), $identity['nom'])); ?></strong></p>
    <p><strong><?php esc_html_e('Identité détectée :', 'gws-core'); ?></strong></p>
    <ul class="gwseq-ifce-preview-identity">
      <?php foreach ($identity_rows as $row_label => $row_value) : ?>
        <li><?php echo esc_html($row_label); ?> : <?php echo esc_html($row_value); ?></li>
      <?php endforeach; ?>
    </ul>
    <?php
    // SIRE/UELN/ID IFCE — informatif uniquement (Lot IFCE — clôture POC, §7/§20) : l'application
    // réelle (jamais un écrasement silencieux d'une valeur différente déjà enregistrée) a lieu dans
    // gwseq_ifce_map_import() (includes/ifce-import-mapper.php) et gwseq_set_cheval_ifce_id()
    // (cheval-fields.php), qui appliquent CETTE MÊME règle indépendamment de cet affichage — ce
    // bloc ne fait que rendre le cas visible AVANT confirmation, jamais une décision.
    $sire_ueln_notes = array();
    if ($reimport_cheval_id) {
      $existing_identity_preview = gwseq_get_cheval_identity($reimport_cheval_id);
      foreach (array('sire' => __('SIRE', 'gws-core'), 'ueln' => __('UELN', 'gws-core')) as $key => $field_label) {
        $detected = $identity[$key];
        $existing = $existing_identity_preview[$key];
        if ($detected !== '' && $existing !== '' && $detected !== $existing) {
          $sire_ueln_notes[] = sprintf(
            /* translators: 1: libellé du champ (SIRE/UELN), 2: valeur déjà enregistrée, 3: valeur détectée dans ce PDF */
            __('%1$s : valeur déjà enregistrée (%2$s) différente de celle détectée ici (%3$s) — la valeur déjà enregistrée sera conservée, jamais écrasée automatiquement.', 'gws-core'),
            $field_label, $existing, $detected
          );
        }
      }
    }
    if ($sire_ueln_notes) : ?>
      <div class="notice notice-warning inline"><p><?php echo esc_html(implode(' ', $sire_ueln_notes)); ?></p></div>
    <?php endif; ?>
    <?php
    // N° SIRE trouvé via SHF (Lot SHF, §5 "aucune écriture avant validation finale de
    // l'utilisateur") — purement informatif ici, exactement comme le bloc SIRE/UELN ci-dessus :
    // l'écriture réelle n'a lieu que dans gwseq_ifce_map_import() à la confirmation, jamais ici.
    // Rendu UNIQUEMENT quand $parsed['shf_sire'] est effectivement renseigné — ce qui, par
    // construction (gwseq_process_ifce_import_upload()), ne se produit jamais si un SIRE est déjà
    // autrement disponible : cette ligne n'apparaît donc jamais en même temps qu'un SIRE déjà
    // détecté par le PDF ou déjà enregistré sur la fiche réimportée.
    if (!empty($parsed['shf_sire'])) : ?>
      <p class="description"><?php echo esc_html(sprintf(
        /* translators: %s: numéro SIRE trouvé automatiquement via SHF */
        __('N° SIRE : %s — trouvé automatiquement via SHF.', 'gws-core'),
        $parsed['shf_sire']
      )); ?></p>
    <?php endif; ?>
    <?php
    // UELN dérivé du SIRE — correctif "UELN Selle Français" : même principe purement informatif,
    // écriture réelle uniquement dans gwseq_ifce_map_import() à la confirmation. Rendu UNIQUEMENT
    // quand $parsed['derived_ueln'] est effectivement renseigné (déjà calculé, à l'upload, à partir
    // du SIRE final — qu'il vienne de SHF, du PDF, ou d'une valeur déjà enregistrée — et du
    // stud-book éligible, voir gwseq_process_ifce_import_upload()).
    if (!empty($parsed['derived_ueln'])) : ?>
      <p class="description"><?php echo esc_html(sprintf(
        /* translators: %s: UELN déterminé automatiquement à partir du SIRE */
        __('UELN : %s — déterminé automatiquement à partir du SIRE.', 'gws-core'),
        $parsed['derived_ueln']
      )); ?></p>
    <?php endif; ?>
    <?php
    // ID IFCE déjà extrait UNE FOIS à l'upload (gwseq_process_ifce_import_upload(), depuis le nom
    // de fichier original — le fichier lui-même n'est jamais conservé, §11) et transporté depuis
    // lors dans le transient : seule source utilisée ici, jamais une seconde extraction.
    $detected_ifce_id = $parsed['ifce_id'] ?? '';
    if ($detected_ifce_id !== '') :
      $ifce_id_conflict = $reimport_cheval_id ? gwseq_cheval_ifce_id_conflicts($reimport_cheval_id, $detected_ifce_id) : false;
      ?>
      <p class="description"><?php echo esc_html(sprintf(/* translators: %s: identifiant opaque IFCE détecté */ __('ID IFCE détecté (nom du fichier) : %s', 'gws-core'), $detected_ifce_id)); ?></p>
      <?php if ($ifce_id_conflict) : ?>
        <div class="notice notice-warning inline"><p><?php echo esc_html(sprintf(
          /* translators: %s: identifiant IFCE déjà enregistré sur cette fiche */
          __('⚠ Cette fiche a déjà un ID IFCE différent enregistré (%s) — celui détecté ici ne sera jamais écrasé automatiquement.', 'gws-core'),
          gwseq_get_cheval_ifce_id($reimport_cheval_id)
        )); ?></p></div>
      <?php endif; ?>
    <?php endif; ?>
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
      <?php
      // Choix Père/Mère GWS (§3 de la demande) — UNIQUEMENT les deux parents directs, jamais les 12
      // ascendants suivants (périmètre volontairement limité, un choix par génération rendrait
      // l'écran de prévisualisation beaucoup trop lourd). Sans effet si "Importer le pedigree"
      // ci-dessus reste décoché (voir gwseq_ifce_map_import()).
      gwseq_render_ifce_preview_parent_choice('father', $pedigree['father'], __('Père', 'gws-core'), 'gwseq_ifce_pere_mode', 'gwseq_ifce_pere_gws_id', $identity['annee_naissance'], $reimport_cheval_id);
      gwseq_render_ifce_preview_parent_choice('mother', $pedigree['mother'], __('Mère', 'gws-core'), 'gwseq_ifce_mere_mode', 'gwseq_ifce_mere_gws_id', $identity['annee_naissance'], $reimport_cheval_id);
      ?>
      <?php if (!empty($production['entries'])) : ?>
        <p><label><input type="checkbox" name="gwseq_ifce_import_production" value="1" checked> <?php esc_html_e('Importer la Production (produits directs de cette jument)', 'gws-core'); ?></label></p>
        <?php gwseq_render_ifce_preview_production_section($production, $reimport_cheval_id); ?>
      <?php endif; ?>
      <?php
      // $reimport_cheval_id N'EST JAMAIS resoumis par ce formulaire (Lot 2B.2, §21) : il reste lu
      // UNIQUEMENT depuis le transient serveur déjà validé à l'upload (gwseq_process_ifce_import_confirm()),
      // jamais depuis un champ caché que le client pourrait manipuler — même discipline que le reste
      // de cet écran (§ "un utilisateur ne peut jamais faire écrire une donnée qu'il n'a pas vue").
      submit_button(__('Valider l’import', 'gws-core'));
      ?>
    </form>
    <p><a href="<?php echo esc_url(gwseq_ifce_import_page_url()); ?>"><?php esc_html_e('Annuler', 'gws-core'); ?></a></p>
  </div>
  <?php
}

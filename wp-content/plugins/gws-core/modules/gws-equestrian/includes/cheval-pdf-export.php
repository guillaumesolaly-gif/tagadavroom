<?php
/**
 * Export PDF réel depuis le BO (Lot "PDF Cheval & Catalogue", Lot 3B — génération/téléchargement).
 *
 * PÉRIMÈTRE STRICT (arbitrages client, avant développement) : relie le renderer déjà figé
 * (`gwseq_generate_horse_pdf()`, includes/cheval-pdf.php — Étalon/Poulinière/Sport-Vente validés,
 * plus jamais modifiés ici) à un point d'entrée BO réel. Aucun catalogue, aucune bibliothèque PDF
 * externe/FPDI, aucun stockage permanent : le PDF est TOUJOURS régénéré à la demande depuis les
 * données actuelles du cheval (photo, prix, pedigree, production, indices, textes) — jamais une
 * copie figée, jamais un attachment WordPress créé pour ce besoin.
 *
 * EMPLACEMENT (V1) : uniquement la boîte « Fiche PDF » existante (includes/cheval-pdf-fields.php)
 * sur l'écran d'édition du cheval — deux boutons, « Prévisualiser » (inline, nouvel onglet) et
 * « Télécharger » (attachment). Aucune action rapide depuis la liste des chevaux, aucun lien sur la
 * fiche publique/privée pour ce lot (le QR code du PDF continue de pointer vers elle, jamais le sens
 * inverse pour l'instant — décision différée).
 *
 * SÉCURITÉ — même convention que le reste du module (import IFCE, partage privé, suppression de
 * sélection, voir includes/cheval-share-admin.php) : hook natif `admin_post_{action}` (jamais AJAX,
 * jamais REST — aucun des deux n'est utilisé nulle part ailleurs dans ce plugin), nonce scopé à
 * l'identifiant du cheval, `current_user_can('edit_post', $horse_id)`, `wp_die(..., 403)` en cas
 * d'échec. Un cheval inexistant échoue sur le MÊME contrôle que l'autorisation (comme
 * gwseq_horse_private_share_user_can_manage()) : jamais de distinction 403/404 qui laisserait
 * deviner l'existence d'un identifiant à un utilisateur non autorisé.
 *
 * SERVICE RÉUTILISABLE (§ "le futur Catalogue devra pouvoir réutiliser le moteur sans dépendre du
 * handler BO") : `gwseq_prepare_horse_pdf_export()` (génération + validation) et
 * `gwseq_stream_horse_pdf()` (émission HTTP) ne dépendent d'AUCUNE vérification de capacité — cette
 * dernière reste strictement dans `gwseq_handle_horse_pdf_export_admin_post()`, le seul point de ce
 * fichier propre au déclencheur BO. Un futur Catalogue pourra appeler le service directement (avec
 * sa propre logique d'autorisation, par lot) sans jamais passer par ce handler.
 *
 * AUCUN OCTET PARASITE AVANT LE PDF : `gwseq_prepare_horse_pdf_export()` valide TOUT (cheval
 * existant, bibliothèque PDF disponible, rendu réussi) avant que `gwseq_stream_horse_pdf()` n'envoie
 * le premier en-tête — jamais de fichier partiel envoyé au navigateur. TCPDF::Output() pose déjà
 * lui-même les en-têtes Content-Type/Content-Disposition corrects selon le mode ('I' inline / 'D'
 * attachment) ; ce fichier ne les répète jamais en double, il se contente de vider tout buffering de
 * sortie déjà ouvert avant l'appel (seule source réaliste d'un flux corrompu dans ce contexte).
 */

if (!defined('ABSPATH')) exit;

const GWSEQ_HORSE_PDF_EXPORT_NONCE_ACTION = 'gwseq_horse_pdf_export';

/* -------------------------------------------------------------------------------------------
 * Fonctions PURES — nom de fichier, disposition HTTP. Aucun accès réseau, aucune sortie.
 * ----------------------------------------------------------------------------------------- */

/**
 * `fiche-{slug}.pdf` à partir du slug RÉEL du cheval (déjà nettoyé par WordPress à l'enregistrement
 * — `sanitize_title()` ici est une garde défensive idempotente, jamais une seconde politique de
 * slug). Repli sur `fiche-cheval-{id}.pdf` si le slug est inexploitable (post jamais enregistré,
 * auto-brouillon) — jamais un nom de fichier vide ou invalide envoyé au navigateur.
 */
function gwseq_horse_pdf_filename($horse_id) {
  $horse_id = (int) $horse_id;
  $slug = $horse_id ? sanitize_title((string) get_post_field('post_name', $horse_id)) : '';
  return $slug !== '' ? "fiche-{$slug}.pdf" : "fiche-cheval-{$horse_id}.pdf";
}

/**
 * Whitelist stricte — toute valeur autre que 'attachment' retombe sur 'inline' (jamais une valeur
 * de disposition non maîtrisée transmise plus loin). Seule source de vérité, réutilisée à la fois
 * pour construire l'URL et pour interpréter la requête reçue.
 */
function gwseq_sanitize_horse_pdf_disposition($raw) {
  return $raw === 'attachment' ? 'attachment' : 'inline';
}

/**
 * Mode TCPDF (Output()) correspondant à la disposition HTTP demandée.
 */
function gwseq_horse_pdf_output_mode($disposition) {
  return gwseq_sanitize_horse_pdf_disposition($disposition) === 'attachment' ? 'D' : 'I';
}

/* -------------------------------------------------------------------------------------------
 * Service — génération + validation, SANS vérification de capacité (réutilisable par un futur
 * Catalogue, voir docblock ci-dessus).
 * ----------------------------------------------------------------------------------------- */

/**
 * Valide et génère le PDF d'un cheval — ne renvoie l'objet TCPDF que si TOUT a réussi, sinon un
 * WP_Error explicite (cheval inexistant/mauvais type, bibliothèque PDF indisponible, échec du
 * renderer — y compris une exception levée par un template, jamais laissée remonter telle quelle).
 */
function gwseq_prepare_horse_pdf_export($horse_id) {
  $horse_id = (int) $horse_id;
  if (!$horse_id || get_post_type($horse_id) !== GWSEQ_CPT_CHEVAL) {
    return new WP_Error('gwseq_horse_pdf_invalid_horse', __('Ce cheval est introuvable.', 'gws-core'));
  }
  if (!gws_core_pdf_available()) {
    return new WP_Error('gwseq_horse_pdf_unavailable', __('La génération PDF n’est pas disponible sur cet environnement.', 'gws-core'));
  }
  try {
    $pdf = gwseq_generate_horse_pdf($horse_id);
  } catch (\Throwable $e) {
    $pdf = null;
  }
  if (!$pdf) {
    return new WP_Error('gwseq_horse_pdf_render_failed', __('La fiche PDF n’a pas pu être générée pour ce cheval.', 'gws-core'));
  }
  return $pdf;
}

/**
 * Émet le PDF déjà généré au navigateur — SEULE fonction de ce fichier qui envoie des en-têtes/
 * octets, jamais appelée avant que gwseq_prepare_horse_pdf_export() ait confirmé un rendu valide.
 * Ne termine PAS elle-même le script (contrairement au handler admin_post qui l'appelle, voir plus
 * bas) : une fonction de service réutilisable par un futur Catalogue ne doit jamais imposer un
 * `exit` à son appelant — cette responsabilité reste au seul point d'entrée qui sait qu'il est bien
 * la dernière chose exécutée pour cette requête.
 */
function gwseq_stream_horse_pdf($pdf, $horse_id, $disposition) {
  while (ob_get_level() > 0) ob_end_clean(); // aucun octet WordPress parasite avant le PDF
  if (function_exists('nocache_headers')) nocache_headers();
  $pdf->Output(gwseq_horse_pdf_filename($horse_id), gwseq_horse_pdf_output_mode($disposition));
}

/* -------------------------------------------------------------------------------------------
 * Déclencheur BO — admin_post, nonce + capacité (même convention que le reste du module).
 * ----------------------------------------------------------------------------------------- */

/**
 * Prédicat testable indépendamment (même discipline que
 * gwseq_horse_private_share_user_can_manage()) : la fiche doit exister, être un Cheval, et
 * l'utilisateur courant doit pouvoir l'éditer.
 */
function gwseq_horse_pdf_export_user_can($horse_id) {
  $horse_id = (int) $horse_id;
  return $horse_id > 0 && get_post_type($horse_id) === GWSEQ_CPT_CHEVAL && current_user_can('edit_post', $horse_id);
}

/**
 * Construit l'URL nonce-protégée d'un export — POINT UNIQUE de construction, jamais reconstruite
 * ailleurs (même principe que gwseq_horse_private_share_action_url()). Un seul hook admin_post pour
 * les deux boutons Preview/Download — seule la disposition change, portée dans la requête.
 */
function gwseq_horse_pdf_export_url($disposition, $horse_id) {
  $horse_id = (int) $horse_id;
  $url = add_query_arg(
    array(
      'action' => 'gwseq_horse_pdf_export',
      'cheval_id' => $horse_id,
      'disposition' => gwseq_sanitize_horse_pdf_disposition($disposition),
    ),
    admin_url('admin-post.php')
  );
  return wp_nonce_url($url, GWSEQ_HORSE_PDF_EXPORT_NONCE_ACTION . '_' . $horse_id);
}

function gwseq_handle_horse_pdf_export_admin_post() {
  $horse_id = isset($_REQUEST['cheval_id']) ? absint($_REQUEST['cheval_id']) : 0;
  check_admin_referer(GWSEQ_HORSE_PDF_EXPORT_NONCE_ACTION . '_' . $horse_id);

  if (!gwseq_horse_pdf_export_user_can($horse_id)) {
    wp_die(esc_html__('Action non autorisée.', 'gws-core'), '', array('response' => 403));
  }

  $disposition = gwseq_sanitize_horse_pdf_disposition(isset($_REQUEST['disposition']) ? sanitize_text_field(wp_unslash($_REQUEST['disposition'])) : '');

  $pdf = gwseq_prepare_horse_pdf_export($horse_id);
  if (is_wp_error($pdf)) {
    wp_die(esc_html($pdf->get_error_message()), '', array('response' => 500));
  }

  gwseq_stream_horse_pdf($pdf, $horse_id, $disposition);
  exit;
}
add_action('admin_post_gwseq_horse_pdf_export', 'gwseq_handle_horse_pdf_export_admin_post');

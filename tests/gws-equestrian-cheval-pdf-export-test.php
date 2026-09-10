<?php
/**
 * Vérifie le service d'export PDF réel depuis le BO (includes/cheval-pdf-export.php, Lot "PDF
 * Cheval & Catalogue", Lot 3B) : nom de fichier (slug réel, accents/apostrophes, repli sur l'ID
 * technique), décision inline/attachment, sécurité du déclencheur BO (nonce scopé au cheval,
 * capacité `edit_post`, cheval inexistant traité comme l'autorisation — même convention que
 * includes/cheval-share-admin.php), gestion des échecs (bibliothèque PDF indisponible, renderer
 * renvoyant null ou levant une exception) et l'absence de tout stockage permanent (jamais un
 * attachment WordPress créé pour ce besoin — le PDF est toujours régénéré à la demande).
 *
 * Le renderer réel (includes/cheval-pdf.php, déjà entièrement testé ailleurs — voir
 * gws-equestrian-cheval-pdf-test.php) n'est PAS rechargé ici : gwseq_generate_horse_pdf() et
 * gws_core_pdf_available() sont stubbées pour isoler strictement la logique de ce fichier
 * (nommage, disposition, sécurité, préparation/émission) de celle, déjà couverte, du rendu
 * lui-même.
 *
 * Ne fait pas partie des paquets livrés (gws-core.zip / gws-starter.zip).
 */

$failures = 0;
function gws_test_assert($condition, $label) {
  global $failures;
  if ($condition) { echo "OK   - $label\n"; }
  else { echo "FAIL - $label\n"; $failures++; }
}

// --- Stubs WordPress minimaux (mêmes conventions que le reste de ce dossier) ---
function wp_unslash($value) { return is_array($value) ? array_map('wp_unslash', $value) : (is_string($value) ? stripslashes($value) : $value); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function absint($value) { return abs((int) $value); }
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function esc_url($value) { return $value; }
function __($text, $domain = 'default') { return $text; }
function esc_html__($text, $domain = 'default') { return esc_html($text); }
function esc_html_e($text, $domain = 'default') { echo esc_html__($text, $domain); }
function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {}

// remove_accents() reproduit la transformation réelle de WordPress pour le sous-ensemble de
// caractères utilisé dans ce test (assez pour vérifier fidèlement le comportement attendu du Lot
// 3B, §3 : "fiche-jamerose-de-felines.pdf" à partir de "Jamérose de Félines").
function remove_accents($text) {
  $map = array('à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', 'ÿ' => 'y', 'œ' => 'oe', 'æ' => 'ae', 'ñ' => 'n');
  return strtr((string) $text, $map);
}
function sanitize_title($value) {
  $value = strtolower(remove_accents((string) $value));
  $value = preg_replace('/[^a-z0-9]+/', '-', $value);
  return trim($value, '-');
}

class WP_Error {
  public $code; public $message; public $data;
  public function __construct($code = '', $message = '', $data = null) { $this->code = $code; $this->message = $message; $this->data = $data; }
  public function get_error_message() { return $this->message; }
  public function get_error_code() { return $this->code; }
}
function is_wp_error($thing) { return $thing instanceof WP_Error; }

class Gws_Test_Wp_Die_Exception extends Exception {
  public $status;
  public function __construct($message, $status = 200) { parent::__construct($message); $this->status = $status; }
}
function wp_die($message = '', $title = '', $args = array()) {
  throw new Gws_Test_Wp_Die_Exception(is_string($message) ? $message : '', (int) ($args['response'] ?? 200));
}

$GLOBALS['__gwseq_test_security'] = array('nonce_valid' => true, 'can_edit' => true);
function check_admin_referer($action, $field = '_wpnonce') {
  if (!$GLOBALS['__gwseq_test_security']['nonce_valid']) throw new Exception('check_admin_referer: invalid nonce');
  return true;
}
function current_user_can($cap, $post_id = null) { return $GLOBALS['__gwseq_test_security']['can_edit']; }

function admin_url($path = '') { return 'https://example.test/wp-admin/' . $path; }
function add_query_arg($args, $url) {
  $sep = strpos($url, '?') === false ? '?' : '&';
  return $url . $sep . http_build_query($args);
}
function wp_create_nonce($action) { return 'nonce-' . $action; }
function wp_nonce_url($url, $action = -1, $name = '_wpnonce') {
  $sep = strpos($url, '?') === false ? '?' : '&';
  return $url . $sep . $name . '=' . wp_create_nonce($action);
}
$GLOBALS['__gwseq_test_nocache_headers_called'] = 0;
function nocache_headers() { $GLOBALS['__gwseq_test_nocache_headers_called']++; }

// --- Registre de posts minimal (existence + type + slug) ---
$GLOBALS['__gwseq_test_posts'] = array();
$GLOBALS['__gwseq_test_post_fields'] = array();
function gws_test_make_post($id, $post_type, $slug = '') {
  $GLOBALS['__gwseq_test_posts'][$id] = $post_type;
  $GLOBALS['__gwseq_test_post_fields'][$id]['post_name'] = $slug;
}
function get_post_type($post_id) { return $GLOBALS['__gwseq_test_posts'][$post_id] ?? false; }
function get_post_field($field, $post_id) { return $GLOBALS['__gwseq_test_post_fields'][$post_id][$field] ?? ''; }

// --- Bibliothèque PDF + renderer, stubbés (voir docblock — le rendu réel est testé ailleurs) ---
$GLOBALS['__gwseq_test_pdf_available'] = true;
function gws_core_pdf_available() { return $GLOBALS['__gwseq_test_pdf_available']; }

class Gws_Test_Fake_Pdf {
  public $output_calls = array();
  public function Output($name, $dest) { $this->output_calls[] = array('name' => $name, 'dest' => $dest); }
}
// 'ok' | null | 'throw' — piloté par le test, jamais un vrai rendu TCPDF ici.
$GLOBALS['__gwseq_test_generate_result'] = 'ok';
function gwseq_generate_horse_pdf($horse_id, $context = array()) {
  if ($GLOBALS['__gwseq_test_generate_result'] === 'throw') throw new LengthException('bloc trop haut pour tenir sur la page');
  if ($GLOBALS['__gwseq_test_generate_result'] === null) return null;
  return new Gws_Test_Fake_Pdf();
}

define('ABSPATH', __DIR__ . '/');
const GWSEQ_CPT_CHEVAL = 'gwseq_cheval';

require dirname(__DIR__) . '/wp-content/plugins/gws-core/modules/gws-equestrian/includes/cheval-pdf-export.php';

function gws_test_reset_pdf_export_state() {
  $GLOBALS['__gwseq_test_security'] = array('nonce_valid' => true, 'can_edit' => true);
  $GLOBALS['__gwseq_test_pdf_available'] = true;
  $GLOBALS['__gwseq_test_generate_result'] = 'ok';
}

// =====================================================================================
// 1. Nom de fichier (§3 de la demande) — slug réel, accents/apostrophes, repli sur l'ID
// =====================================================================================

gws_test_make_post(60, GWSEQ_CPT_CHEVAL, 'jamerose-de-felines');
gws_test_assert(gwseq_horse_pdf_filename(60) === 'fiche-jamerose-de-felines.pdf', 'Nom de fichier : slug simple -> "fiche-{slug}.pdf"');

gws_test_make_post(61, GWSEQ_CPT_CHEVAL, "Jamérose d'Aubigny");
gws_test_assert(gwseq_horse_pdf_filename(61) === 'fiche-jamerose-d-aubigny.pdf', 'Nom de fichier : accents et apostrophe nettoyés (garde défensive sanitize_title(), même si WordPress stocke déjà un slug propre)');

gws_test_make_post(62, GWSEQ_CPT_CHEVAL, '');
gws_test_assert(gwseq_horse_pdf_filename(62) === 'fiche-cheval-62.pdf', 'Nom de fichier : slug vide (fiche jamais enregistrée) -> repli exact "fiche-cheval-{id}.pdf"');

gws_test_assert(gwseq_horse_pdf_filename(0) === 'fiche-cheval-0.pdf', 'Nom de fichier : identifiant invalide -> repli sans erreur, jamais un nom de fichier vide');

// =====================================================================================
// 2. Disposition HTTP (§2/§8) — whitelist stricte, jamais une valeur non maîtrisée propagée
// =====================================================================================

gws_test_assert(gwseq_sanitize_horse_pdf_disposition('attachment') === 'attachment', 'Disposition : "attachment" conservé tel quel');
gws_test_assert(gwseq_sanitize_horse_pdf_disposition('inline') === 'inline', 'Disposition : "inline" conservé tel quel');
gws_test_assert(gwseq_sanitize_horse_pdf_disposition('') === 'inline', 'Disposition : valeur absente -> repli "inline"');
gws_test_assert(gwseq_sanitize_horse_pdf_disposition('<script>x</script>') === 'inline', 'Disposition : toute valeur hors whitelist -> repli "inline", jamais propagée telle quelle');

gws_test_assert(gwseq_horse_pdf_output_mode('attachment') === 'D', 'Mode TCPDF : "attachment" -> \'D\' (Output() de TCPDF)');
gws_test_assert(gwseq_horse_pdf_output_mode('inline') === 'I', 'Mode TCPDF : "inline" -> \'I\'');
gws_test_assert(gwseq_horse_pdf_output_mode('valeur-inconnue') === 'I', 'Mode TCPDF : valeur inconnue -> \'I\' (même repli que la disposition elle-même)');

// =====================================================================================
// 3. URL nonce-protégée — même principe que gwseq_horse_private_share_action_url()
// =====================================================================================

$url_preview = gwseq_horse_pdf_export_url('inline', 60);
gws_test_assert(strpos($url_preview, 'action=gwseq_horse_pdf_export') !== false, 'URL : action admin_post correcte');
gws_test_assert(strpos($url_preview, 'cheval_id=60') !== false, 'URL : cheval_id présent');
gws_test_assert(strpos($url_preview, 'disposition=inline') !== false, 'URL : disposition "inline" encodée');
gws_test_assert(strpos($url_preview, '_wpnonce=nonce-' . GWSEQ_HORSE_PDF_EXPORT_NONCE_ACTION . '_60') !== false, 'URL : nonce scopé à CE cheval précisément (jamais un nonce générique réutilisable pour un autre ID)');

$url_download = gwseq_horse_pdf_export_url('attachment', 60);
gws_test_assert(strpos($url_download, 'disposition=attachment') !== false, 'URL : disposition "attachment" encodée pour le bouton Télécharger');

// =====================================================================================
// 4. Autorisation (§6) — cheval inexistant traité EXACTEMENT comme un refus d'autorisation
// =====================================================================================

gws_test_assert(gwseq_horse_pdf_export_user_can(60) === true, 'Autorisation : cheval existant + capacité edit_post -> autorisé');

$GLOBALS['__gwseq_test_security']['can_edit'] = false;
gws_test_assert(gwseq_horse_pdf_export_user_can(60) === false, 'Autorisation : capacité edit_post refusée -> non autorisé');
gws_test_reset_pdf_export_state();

gws_test_assert(gwseq_horse_pdf_export_user_can(999999) === false, 'Autorisation : cheval inexistant -> non autorisé (même chemin que la capacité, jamais un 404 qui distinguerait les deux cas)');

gws_test_make_post(70, 'page');
gws_test_assert(gwseq_horse_pdf_export_user_can(70) === false, 'Autorisation : un post d’un autre type (Page) est toujours refusé, même avec un ID valide');

// =====================================================================================
// 5. Préparation de l'export (§9) — cheval inexistant, bibliothèque indisponible, échec du
//    renderer (null ET exception), jamais un objet PDF partiel renvoyé
// =====================================================================================

$prepared_ok = gwseq_prepare_horse_pdf_export(60);
gws_test_assert(!is_wp_error($prepared_ok) && $prepared_ok instanceof Gws_Test_Fake_Pdf, 'Préparation : cas nominal -> objet PDF renvoyé');

$prepared_invalid = gwseq_prepare_horse_pdf_export(999999);
gws_test_assert(is_wp_error($prepared_invalid) && $prepared_invalid->get_error_code() === 'gwseq_horse_pdf_invalid_horse', 'Préparation : cheval inexistant -> WP_Error explicite, jamais un objet PDF');

$GLOBALS['__gwseq_test_pdf_available'] = false;
$prepared_unavailable = gwseq_prepare_horse_pdf_export(60);
gws_test_assert(is_wp_error($prepared_unavailable) && $prepared_unavailable->get_error_code() === 'gwseq_horse_pdf_unavailable', 'Préparation : bibliothèque PDF indisponible -> WP_Error explicite (§4, jamais un bouton qui échoue silencieusement)');
gws_test_reset_pdf_export_state();

$GLOBALS['__gwseq_test_generate_result'] = null;
$prepared_null = gwseq_prepare_horse_pdf_export(60);
gws_test_assert(is_wp_error($prepared_null) && $prepared_null->get_error_code() === 'gwseq_horse_pdf_render_failed', 'Préparation : renderer renvoyant null -> WP_Error explicite');
gws_test_reset_pdf_export_state();

$GLOBALS['__gwseq_test_generate_result'] = 'throw';
$prepared_throw = gwseq_prepare_horse_pdf_export(60);
gws_test_assert(is_wp_error($prepared_throw) && $prepared_throw->get_error_code() === 'gwseq_horse_pdf_render_failed', 'Préparation : une exception levée par le renderer (ex. LengthException) est capturée proprement, jamais laissée remonter telle quelle');
gws_test_reset_pdf_export_state();

// =====================================================================================
// 6. Émission (§8) — Output() appelé avec le bon nom de fichier et le bon mode, en-têtes
//    anti-cache posés ; ne termine jamais elle-même le script (voir docblock du fichier)
// =====================================================================================

$fake_pdf = new Gws_Test_Fake_Pdf();
$before_nocache = $GLOBALS['__gwseq_test_nocache_headers_called'];
gwseq_stream_horse_pdf($fake_pdf, 60, 'attachment');
gws_test_assert($GLOBALS['__gwseq_test_nocache_headers_called'] === $before_nocache + 1, 'Émission : nocache_headers() appelé (fiche toujours régénérée à la demande, jamais mise en cache)');
gws_test_assert(count($fake_pdf->output_calls) === 1, 'Émission : Output() appelé exactement une fois');
gws_test_assert($fake_pdf->output_calls[0]['name'] === 'fiche-jamerose-de-felines.pdf', 'Émission : nom de fichier transmis à Output() cohérent avec gwseq_horse_pdf_filename()');
gws_test_assert($fake_pdf->output_calls[0]['dest'] === 'D', 'Émission : mode \'D\' (attachment) transmis à Output()');

$fake_pdf_inline = new Gws_Test_Fake_Pdf();
gwseq_stream_horse_pdf($fake_pdf_inline, 60, 'inline');
gws_test_assert($fake_pdf_inline->output_calls[0]['dest'] === 'I', 'Émission : mode \'I\' (inline) transmis à Output() pour la prévisualisation');

// =====================================================================================
// 7. Handler admin_post (§6/§9) — rejets AVANT toute préparation/émission (nonce, capacité) ;
//    le succès n'est jamais exécuté jusqu'au bout ici (il se termine par exit, comme tous les
//    handlers admin_post de ce module — voir includes/cheval-share-admin.php)
// =====================================================================================

$_REQUEST = array('cheval_id' => '60');
$GLOBALS['__gwseq_test_security']['nonce_valid'] = false;
$nonce_rejected = false;
try { gwseq_handle_horse_pdf_export_admin_post(); } catch (Exception $e) { $nonce_rejected = (strpos($e->getMessage(), 'nonce') !== false); }
gws_test_assert($nonce_rejected, 'Handler : nonce invalide -> rejeté avant toute préparation/émission');
gws_test_reset_pdf_export_state();

$_REQUEST = array('cheval_id' => '60');
$GLOBALS['__gwseq_test_security']['can_edit'] = false;
$capability_rejected = false;
try { gwseq_handle_horse_pdf_export_admin_post(); } catch (Gws_Test_Wp_Die_Exception $e) { $capability_rejected = ($e->status === 403); }
gws_test_assert($capability_rejected, 'Handler : capacité refusée -> wp_die(..., 403), jamais une tentative de génération');
gws_test_reset_pdf_export_state();

$_REQUEST = array('cheval_id' => '999999');
$missing_rejected = false;
try { gwseq_handle_horse_pdf_export_admin_post(); } catch (Gws_Test_Wp_Die_Exception $e) { $missing_rejected = ($e->status === 403); }
gws_test_assert($missing_rejected, 'Handler : cheval inexistant -> même wp_die(..., 403) que l’autorisation (§6)');
gws_test_reset_pdf_export_state();

$_REQUEST = array('cheval_id' => '60', 'disposition' => 'attachment');
$GLOBALS['__gwseq_test_generate_result'] = null;
$render_failed_rejected = false;
try { gwseq_handle_horse_pdf_export_admin_post(); } catch (Gws_Test_Wp_Die_Exception $e) { $render_failed_rejected = ($e->status === 500); }
gws_test_assert($render_failed_rejected, 'Handler : préparation en échec (renderer null) -> wp_die(..., 500), jamais une émission partielle');
gws_test_reset_pdf_export_state();
$_REQUEST = array();

// =====================================================================================
// 8. Aucun stockage permanent (§11) — jamais un attachment WordPress créé, jamais une écriture
//    disque pour ce PDF ; contrôle par lecture du code source, même méthodologie que la vérification
//    équivalente pour l'import IFCE (tests/gws-equestrian-ifce-import-test.php)
// =====================================================================================

$export_source = file_get_contents(dirname(__DIR__) . '/wp-content/plugins/gws-core/modules/gws-equestrian/includes/cheval-pdf-export.php');
foreach (array('wp_insert_attachment', 'media_handle_upload', 'wp_handle_upload', 'file_put_contents', 'fopen(') as $storage_marker) {
  gws_test_assert(strpos($export_source, $storage_marker) === false, "Aucun stockage permanent (§11) : \"$storage_marker\" n'apparaît jamais dans cheval-pdf-export.php — le PDF est toujours régénéré à la demande, jamais une copie figée");
}
gws_test_assert(strpos($export_source, "\$pdf->Output(") !== false, 'Émission : Output() bien appelé (sortie directe au navigateur, jamais un fichier écrit sur disque au préalable)');

echo ($failures === 0 ? "Tous les tests sont passés.\n" : "$failures échec(s).\n");
exit($failures === 0 ? 0 : 1);

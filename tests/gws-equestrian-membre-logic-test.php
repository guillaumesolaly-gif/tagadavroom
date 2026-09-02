<?php
/**
 * Vérifie le module Équipe de `gws-equestrian` (nouvel objet métier Membre) : identité, titre
 * technique auto-dérivé de Prénom + Nom, section Profil (dont Langues, seul champ structuré),
 * section Contact (e-mail/URLs sanitisés, téléphone/WhatsApp jamais dénaturés), sauvegarde de
 * tous les champs, sécurité de la sauvegarde, colonnes de la liste d'administration, et absence
 * d'effet de bord sur les autres objets métier GWS (Cheval/Prestation/Groupe). Même méthodologie
 * que les autres suites de ce dossier : on exerce les fonctions avec des données à la forme réelle
 * de $_POST, et on vérifie le comportement réel des hooks WordPress (pas seulement leur présence).
 *
 * Ne fait pas partie des paquets livrés (gws-core.zip / gws-starter.zip).
 */

$failures = 0;
function gws_test_assert($condition, $label) {
  global $failures;
  if ($condition) { echo "OK   - $label\n"; }
  else { echo "FAIL - $label\n"; $failures++; }
}

// --- Stubs WordPress minimaux (même convention que gws-equestrian-cheval-logic-test.php) ---
function wp_unslash($value) { return is_array($value) ? array_map('wp_unslash', $value) : $value; }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function sanitize_textarea_field($value) { return trim((string) $value); }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value)); }
function sanitize_email($value) { $value = trim((string) $value); return filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : ''; }
function esc_url_raw($value) { $value = trim((string) $value); return $value === '' ? '' : $value; }
function absint($value) { return abs((int) $value); }
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function esc_textarea($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
// FIDÈLE au comportement réel (WordPress core echo par défaut) — voir gws-equestrian-cheval-logic-test.php
// pour le détail du bug de stub que cela évite (persistance des cases cochées jamais visible sinon).
function selected($a, $b, $echo = true) { $result = $a == $b ? " selected='selected'" : ''; if ($echo) echo $result; return $result; }
function checked($a, $b = true, $echo = true) { $result = $a == $b ? " checked='checked'" : ''; if ($echo) echo $result; return $result; }
function wp_nonce_field($action, $field) { echo '<input type="hidden" name="' . esc_attr($field) . '" value="stub-nonce">'; }

// i18n : chaîne telle quelle, mais on capture le text domain utilisé pour vérifier sa cohérence.
$GLOBALS['__gwseq_test_domains_used'] = array();
function __($text, $domain = 'default') { $GLOBALS['__gwseq_test_domains_used'][] = $domain; return $text; }
function esc_html__($text, $domain = 'default') { $GLOBALS['__gwseq_test_domains_used'][] = $domain; return esc_html($text); }
function esc_attr__($text, $domain = 'default') { $GLOBALS['__gwseq_test_domains_used'][] = $domain; return esc_attr($text); }
function esc_html_e($text, $domain = 'default') { echo esc_html__($text, $domain); }
function esc_attr_e($text, $domain = 'default') { echo esc_attr__($text, $domain); }

// --- register_post_meta : on capture les arguments réellement passés (type 'array' pour Langues
// en particulier) ---
$GLOBALS['__gwseq_test_registered_meta'] = array();
function register_post_meta($object_type, $meta_key, $args = array()) {
  $GLOBALS['__gwseq_test_registered_meta'][$meta_key] = $args;
}
$GLOBALS['__gwseq_test_actions'] = array();
$GLOBALS['__gwseq_test_filters'] = array();
function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
  $GLOBALS['__gwseq_test_actions'][$hook][] = $callback;
}
function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {
  $GLOBALS['__gwseq_test_filters'][$hook][] = $callback;
}

// --- Registres en mémoire (meta/posts), comme dans les autres tests de ce dossier ---
$GLOBALS['__gwseq_test_meta'] = array();
function update_post_meta($post_id, $key, $value) { $GLOBALS['__gwseq_test_meta'][$post_id][$key] = $value; return true; }
function get_post_meta($post_id, $key, $single = false) { return $GLOBALS['__gwseq_test_meta'][$post_id][$key] ?? ''; }
function get_post_field($field, $post_id) { return $GLOBALS['__gwseq_test_post_fields'][$post_id][$field] ?? ''; }

$GLOBALS['__gwseq_test_thumbnails'] = array();
function get_the_post_thumbnail($post_id, $size = 'post-thumbnail') {
  return $GLOBALS['__gwseq_test_thumbnails'][$post_id] ?? '';
}

// --- Sécurité : registres pilotables par le test, même mécanisme que Cheval/Prestation ---
$GLOBALS['__gwseq_test_security'] = array('nonce_valid' => true, 'can_edit' => true, 'is_revision' => false);
function wp_verify_nonce($nonce, $action) { return $GLOBALS['__gwseq_test_security']['nonce_valid']; }
function current_user_can($cap, $post_id = null) { return $GLOBALS['__gwseq_test_security']['can_edit']; }
function wp_is_post_revision($post_id) { return $GLOBALS['__gwseq_test_security']['is_revision']; }

$GLOBALS['__gwseq_test_meta_boxes'] = array();
function add_meta_box($id, $title, $callback, $post_type = null, $context = 'advanced', $priority = 'default') {
  $GLOBALS['__gwseq_test_meta_boxes'][] = $id;
}

$GLOBALS['__gwseq_test_screen'] = null;
function get_current_screen() { return $GLOBALS['__gwseq_test_screen']; }
$GLOBALS['__gwseq_enqueued'] = array();
function wp_enqueue_script($handle, ...$rest) { $GLOBALS['__gwseq_enqueued'][] = $handle; }
function wp_enqueue_style($handle, ...$rest) { $GLOBALS['__gwseq_enqueued'][] = $handle; }
function wp_script_is($handle, $status = 'enqueued') { return in_array($handle, $GLOBALS['__gwseq_enqueued'], true); }

define('ABSPATH', __DIR__ . '/');
define('GWS_CORE_DIR', dirname(__DIR__) . '/wp-content/plugins/gws-core/');
define('GWS_CORE_URL', 'https://example.test/wp-content/plugins/gws-core/');
const GWSEQ_CPT_MEMBRE = 'gwseq_membre';
const GWSEQ_CPT_CHEVAL = 'gwseq_cheval';
define('GWSEQ_MODULE_URL', GWS_CORE_URL . 'modules/gws-equestrian/');
define('GWSEQ_MODULE_VERSION', '0.0.0-test');

require GWS_CORE_DIR . 'includes/fields.php';
$module_dir = GWS_CORE_DIR . 'modules/gws-equestrian/';
require $module_dir . 'includes/membre-fields.php';
require $module_dir . 'includes/membre-editor.php';
// L'add_action() de ce fichier ne fait qu'enregistrer les callbacks sans les exécuter (voir plus
// bas) : on appelle donc directement la fonction accrochée à 'init', même convention que
// gws-equestrian-cheval-logic-test.php pour gwseq_register_cheval_meta().
gwseq_register_membre_meta();

// =====================================================================================
// Sanitation — Identité
// =====================================================================================

$identity_full = gwseq_sanitize_membre_identity_input(array(
  '_gwseq_membre_prenom' => 'Jean',
  '_gwseq_membre_nom' => 'Dupont',
  '_gwseq_membre_fonction' => 'Moniteur',
  '_gwseq_membre_localisation' => 'Site de Lyon',
));
gws_test_assert($identity_full['prenom'] === 'Jean', 'Identité : prénom conservé');
gws_test_assert($identity_full['nom'] === 'Dupont', 'Identité : nom conservé');
gws_test_assert($identity_full['fonction'] === 'Moniteur', 'Identité : fonction/rôle conservée (texte libre, aucune liste imposée)');
gws_test_assert($identity_full['localisation'] === 'Site de Lyon', 'Identité : localisation conservée (texte libre)');

$identity_empty = gwseq_sanitize_membre_identity_input(array());
gws_test_assert(
  $identity_empty === array('prenom' => '', 'nom' => '', 'fonction' => '', 'localisation' => ''),
  'Identité : payload vide -> tous les champs vides, jamais d\'erreur (tous les champs sont facultatifs)'
);

// =====================================================================================
// Titre technique automatique (§8) : dérivé de Prénom + Nom, jamais saisi séparément
// =====================================================================================

gws_test_assert(gwseq_derive_membre_title('Jean', 'Dupont') === 'Jean Dupont', 'Titre auto : prénom + nom -> "Jean Dupont"');
gws_test_assert(gwseq_derive_membre_title('Jean', '') === 'Jean', 'Titre auto : prénom seul -> "Jean", jamais d\'espace superflu');
gws_test_assert(gwseq_derive_membre_title('', 'Dupont') === 'Dupont', 'Titre auto : nom seul -> "Dupont"');
gws_test_assert(gwseq_derive_membre_title('', '') === '', 'Titre auto : les deux vides -> chaîne vide, jamais un titre inventé');
gws_test_assert(gwseq_derive_membre_title('  Jean  ', '  Dupont  ') === 'Jean Dupont', 'Titre auto : espaces superflus autour de chaque partie retirés');

// --- Mécanisme réel (filtre wp_insert_post_data), pas seulement la fonction pure ---
$_POST = array(
  GWSEQ_MEMBRE_NONCE_FIELD => 'stub-nonce',
  '_gwseq_membre_prenom' => 'Alice',
  '_gwseq_membre_nom' => 'Martin',
);
$data = gwseq_auto_title_membre(array('post_type' => GWSEQ_CPT_MEMBRE, 'post_title' => 'Auto Draft'), array());
gws_test_assert($data['post_title'] === 'Alice Martin', 'Filtre wp_insert_post_data : titre réellement recalculé à partir de $_POST pour une soumission Membre valide');

// --- Jamais appliqué à un autre post type (pas de boucle, pas d'effet de bord sur Cheval/Prestation) ---
$data_other = gwseq_auto_title_membre(array('post_type' => 'gwseq_cheval', 'post_title' => 'JAMEROSE'), array());
gws_test_assert($data_other['post_title'] === 'JAMEROSE', 'Filtre wp_insert_post_data : jamais appliqué à un autre post type (ex. Cheval)');

// --- Nonce absent/invalide : titre déjà enregistré jamais réécrit silencieusement (ex. Quick Edit) ---
$_POST = array('_gwseq_membre_prenom' => 'Ne', '_gwseq_membre_nom' => 'PasAppliquer');
$data_no_nonce = gwseq_auto_title_membre(array('post_type' => GWSEQ_CPT_MEMBRE, 'post_title' => 'Titre Existant'), array());
gws_test_assert($data_no_nonce['post_title'] === 'Titre Existant', 'Filtre wp_insert_post_data : sans le nonce Membre, le titre existant n\'est jamais réécrit (ex. Quick Edit, appel externe)');

$GLOBALS['__gwseq_test_security']['nonce_valid'] = false;
$_POST = array(GWSEQ_MEMBRE_NONCE_FIELD => 'stub-nonce', '_gwseq_membre_prenom' => 'Ne', '_gwseq_membre_nom' => 'PasAppliquer');
$data_invalid_nonce = gwseq_auto_title_membre(array('post_type' => GWSEQ_CPT_MEMBRE, 'post_title' => 'Titre Existant'), array());
gws_test_assert($data_invalid_nonce['post_title'] === 'Titre Existant', 'Filtre wp_insert_post_data : nonce invalide -> titre existant jamais réécrit');
$GLOBALS['__gwseq_test_security']['nonce_valid'] = true;

// --- Câblage réel du hook : wp_insert_post_data, jamais une boucle via save_post/wp_update_post ---
gws_test_assert(
  isset($GLOBALS['__gwseq_test_filters']['wp_insert_post_data']) && in_array('gwseq_auto_title_membre', $GLOBALS['__gwseq_test_filters']['wp_insert_post_data'], true),
  'Titre auto : accroché au filtre wp_insert_post_data (aucun second wp_update_post() dans un hook save_post, donc aucune boucle de sauvegarde possible par construction)'
);
$membre_fields_source = file_get_contents($module_dir . 'includes/membre-fields.php');

// =====================================================================================
// Langues (§Profil) : seul champ structuré, sélection multiple, "Autre" + Préciser
// =====================================================================================

$langues_simple = gwseq_sanitize_membre_langues_input(array('_gwseq_membre_langues' => array('fr', 'en')));
gws_test_assert($langues_simple['langues'] === array('fr', 'en'), 'Langues : sélection multiple conservée (Français + Anglais)');
gws_test_assert($langues_simple['langue_autre_precision'] === '', 'Langues : précision vide quand "Autre" n\'est pas sélectionné');

$langues_autre = gwseq_sanitize_membre_langues_input(array(
  '_gwseq_membre_langues' => array('fr', 'autre'),
  '_gwseq_membre_langue_autre_precision' => 'Russe',
));
gws_test_assert($langues_autre['langues'] === array('fr', 'autre'), 'Langues : "Autre" conservé parmi la sélection');
gws_test_assert($langues_autre['langue_autre_precision'] === 'Russe', 'Langues : précision "Préciser" conservée quand "Autre" est sélectionné');

// --- Suppression de "Autre" : le serveur reste l'autorité, la précision ne doit JAMAIS survivre
// silencieusement même si elle est encore présente dans le payload soumis ---
$langues_autre_retiree = gwseq_sanitize_membre_langues_input(array(
  '_gwseq_membre_langues' => array('fr'),
  '_gwseq_membre_langue_autre_precision' => 'Russe', // encore présent dans le payload, doit être ignoré
));
gws_test_assert(
  $langues_autre_retiree['langue_autre_precision'] === '',
  'Langues : "Autre" retiré de la sélection -> la précision est nettoyée, MÊME si l\'ancienne valeur est encore soumise (le serveur reste l\'autorité)'
);

// --- Revalidation contre le référentiel : une valeur hors enum est ignorée, jamais propagée ---
$langues_invalides = gwseq_sanitize_membre_langues_input(array('_gwseq_membre_langues' => array('fr', 'klingon', '')));
gws_test_assert($langues_invalides['langues'] === array('fr'), 'Langues : une valeur hors référentiel est ignorée, jamais propagée telle quelle');

// --- Déduplication ---
$langues_doublons = gwseq_sanitize_membre_langues_input(array('_gwseq_membre_langues' => array('fr', 'fr', 'en')));
gws_test_assert($langues_doublons['langues'] === array('fr', 'en'), 'Langues : doublons dédupliqués');

// --- Payload malformé (pas un tableau) : aucune erreur, résultat vide ---
$langues_malformees = gwseq_sanitize_membre_langues_input(array('_gwseq_membre_langues' => 'fr'));
gws_test_assert($langues_malformees['langues'] === array(), 'Langues : payload malformé (chaîne au lieu d\'un tableau) -> aucune langue retenue, jamais d\'erreur');

// --- Payload totalement absent ---
$langues_absentes = gwseq_sanitize_membre_langues_input(array());
gws_test_assert($langues_absentes === array('langues' => array(), 'langue_autre_precision' => ''), 'Langues : payload absent -> aucune langue, aucune précision, jamais d\'erreur');

// --- Libellés affichés : noms complets, jamais uniquement des codes FR/EN/DE ---
$langue_options = gwseq_membre_langue_options();
gws_test_assert($langue_options['fr'] === 'Français', 'Langues : libellé complet "Français" (jamais seulement "FR")');
gws_test_assert($langue_options['en'] === 'Anglais', 'Langues : libellé complet "Anglais" (jamais seulement "EN")');
gws_test_assert(count($langue_options) === 12, 'Langues : les 12 valeurs V1 attendues (11 langues + Autre)');
gws_test_assert(array_key_exists('autre', $langue_options), 'Langues : option "Autre" présente');

// --- Représentation compacte pour la colonne de liste (§9) ---
gws_test_assert(
  gwseq_membre_langues_label(array('langues' => array('fr', 'en'), 'langue_autre_precision' => '')) === 'Français, Anglais',
  'Langues : représentation compacte "Français, Anglais" pour la colonne de liste'
);
gws_test_assert(
  gwseq_membre_langues_label(array('langues' => array('autre'), 'langue_autre_precision' => 'Russe')) === 'Russe',
  'Langues : "Autre" affiche la précision saisie dans la représentation compacte'
);
gws_test_assert(
  gwseq_membre_langues_label(array('langues' => array(), 'langue_autre_precision' => '')) === '',
  'Langues : aucune langue -> représentation compacte vide'
);

// =====================================================================================
// Sanitation — Profil (présentation, spécialités, diplômes)
// =====================================================================================

$profil_full = gwseq_sanitize_membre_profil_input(array(
  '_gwseq_membre_presentation' => "Parcours de 15 ans dans l'élevage.",
  '_gwseq_membre_specialites' => 'Jeunes chevaux, Rééducation',
  '_gwseq_membre_diplomes' => 'BPJEPS',
  '_gwseq_membre_langues' => array('fr'),
));
gws_test_assert($profil_full['presentation'] === "Parcours de 15 ans dans l'élevage.", 'Profil : présentation/parcours conservée (texte long libre)');
gws_test_assert($profil_full['specialites'] === 'Jeunes chevaux, Rééducation', 'Profil : spécialités conservées (texte libre, aucune liste imposée)');
gws_test_assert($profil_full['diplomes'] === 'BPJEPS', 'Profil : diplômes/qualifications conservés (texte libre, aucun référentiel français imposé)');

$profil_empty = gwseq_sanitize_membre_profil_input(array());
gws_test_assert(
  $profil_empty['presentation'] === '' && $profil_empty['specialites'] === '' && $profil_empty['diplomes'] === '' && $profil_empty['langues'] === array(),
  'Profil : payload vide -> tous les champs vides, jamais d\'erreur'
);

// =====================================================================================
// Sanitation — Contact (e-mail, URLs, téléphone/WhatsApp jamais dénaturés)
// =====================================================================================

$contact_full = gwseq_sanitize_membre_contact_input(array(
  '_gwseq_membre_telephone' => '+33 6 12 34 56 78',
  '_gwseq_membre_email' => 'jean.dupont@example.com',
  '_gwseq_membre_whatsapp' => '+33 6 98 76 54 32',
  '_gwseq_membre_instagram' => 'https://instagram.com/jean',
  '_gwseq_membre_facebook' => 'https://facebook.com/jean',
  '_gwseq_membre_linkedin' => 'https://linkedin.com/in/jean',
  '_gwseq_membre_tiktok' => 'https://tiktok.com/@jean',
  '_gwseq_membre_site' => 'https://jean-dupont.example.com',
));
gws_test_assert($contact_full['telephone'] === '+33 6 12 34 56 78', 'Contact : téléphone international conservé tel quel, jamais dénaturé, aucun format français imposé');
gws_test_assert($contact_full['email'] === 'jean.dupont@example.com', 'Contact : e-mail valide conservé (sanitation WordPress appropriée)');
gws_test_assert($contact_full['whatsapp'] === '+33 6 98 76 54 32', 'Contact : WhatsApp conservé, donnée INDÉPENDANTE du téléphone principal');
gws_test_assert($contact_full['whatsapp'] !== $contact_full['telephone'], 'Contact : WhatsApp et téléphone peuvent légitimement différer (jamais supposés identiques)');
gws_test_assert($contact_full['instagram'] === 'https://instagram.com/jean', 'Contact : URL Instagram conservée');
gws_test_assert($contact_full['facebook'] === 'https://facebook.com/jean', 'Contact : URL Facebook conservée');
gws_test_assert($contact_full['linkedin'] === 'https://linkedin.com/in/jean', 'Contact : URL LinkedIn conservée');
gws_test_assert($contact_full['tiktok'] === 'https://tiktok.com/@jean', 'Contact : URL TikTok conservée');
gws_test_assert($contact_full['site'] === 'https://jean-dupont.example.com', 'Contact : URL du site/lien externe conservée');

// --- E-mail invalide : jamais enregistré tel quel ---
$contact_bad_email = gwseq_sanitize_membre_contact_input(array('_gwseq_membre_email' => 'pas-un-email'));
gws_test_assert($contact_bad_email['email'] === '', 'Contact : e-mail invalide -> jamais enregistré tel quel');

// --- Numéro international avec espaces/parenthèses/tirets : conservé (aucun format imposé) ---
$contact_intl = gwseq_sanitize_membre_contact_input(array('_gwseq_membre_telephone' => '+1 (415) 555-0132'));
gws_test_assert($contact_intl['telephone'] === '+1 (415) 555-0132', 'Contact : téléphone international avec parenthèses/tirets non détruit');

$contact_empty = gwseq_sanitize_membre_contact_input(array());
gws_test_assert(
  $contact_empty === array('telephone' => '', 'email' => '', 'whatsapp' => '', 'instagram' => '', 'facebook' => '', 'linkedin' => '', 'tiktok' => '', 'site' => ''),
  'Contact : payload vide -> tous les champs vides, jamais d\'erreur (tous facultatifs)'
);

// =====================================================================================
// Sauvegarde réelle et rechargement — membre minimal, membre complet
// =====================================================================================

// --- Membre vide/minimal : peut être enregistré avec (quasiment) aucune information ---
$_POST = array(GWSEQ_MEMBRE_NONCE_FIELD => 'stub-nonce');
gwseq_save_membre_meta(100);
gws_test_assert(gwseq_get_membre_identity(100) === array('prenom' => '', 'nom' => '', 'fonction' => '', 'localisation' => ''), 'Sauvegarde : membre minimal (aucun champ soumis) -> identité entièrement vide, aucune erreur');
gws_test_assert(gwseq_get_membre_langues(100)['langues'] === array(), 'Sauvegarde : membre minimal -> aucune langue, aucune erreur');

// --- Membre complet : tous les champs des trois sections, rechargés à l'identique ---
$_POST = array(
  GWSEQ_MEMBRE_NONCE_FIELD => 'stub-nonce',
  '_gwseq_membre_prenom' => 'Camille',
  '_gwseq_membre_nom' => 'Rousseau',
  '_gwseq_membre_fonction' => "Responsable d'élevage",
  '_gwseq_membre_localisation' => 'Haras principal',
  '_gwseq_membre_presentation' => 'Vingt ans d\'expérience en élevage de chevaux de sport.',
  '_gwseq_membre_specialites' => 'Poulinage, Suivi gynécologique',
  '_gwseq_membre_diplomes' => 'DEJEPS',
  '_gwseq_membre_langues' => array('fr', 'en', 'autre'),
  '_gwseq_membre_langue_autre_precision' => 'Russe',
  '_gwseq_membre_telephone' => '+33 6 11 22 33 44',
  '_gwseq_membre_email' => 'camille.rousseau@example.com',
  '_gwseq_membre_whatsapp' => '+33 7 55 66 77 88',
  '_gwseq_membre_instagram' => 'https://instagram.com/camille',
  '_gwseq_membre_facebook' => 'https://facebook.com/camille',
  '_gwseq_membre_linkedin' => 'https://linkedin.com/in/camille',
  '_gwseq_membre_tiktok' => 'https://tiktok.com/@camille',
  '_gwseq_membre_site' => 'https://camille-elevage.example.com',
);
gwseq_save_membre_meta(101);

$reloaded_identity = gwseq_get_membre_identity(101);
gws_test_assert($reloaded_identity['prenom'] === 'Camille', 'Sauvegarde/rechargement : prénom');
gws_test_assert($reloaded_identity['nom'] === 'Rousseau', 'Sauvegarde/rechargement : nom');
gws_test_assert($reloaded_identity['fonction'] === "Responsable d'élevage", 'Sauvegarde/rechargement : fonction/rôle');
gws_test_assert($reloaded_identity['localisation'] === 'Haras principal', 'Sauvegarde/rechargement : localisation');

$reloaded_profil = gwseq_get_membre_profil(101);
gws_test_assert($reloaded_profil['presentation'] === 'Vingt ans d\'expérience en élevage de chevaux de sport.', 'Sauvegarde/rechargement : présentation/parcours');
gws_test_assert($reloaded_profil['specialites'] === 'Poulinage, Suivi gynécologique', 'Sauvegarde/rechargement : spécialités');
gws_test_assert($reloaded_profil['diplomes'] === 'DEJEPS', 'Sauvegarde/rechargement : diplômes/qualifications');

$reloaded_langues = gwseq_get_membre_langues(101);
gws_test_assert($reloaded_langues['langues'] === array('fr', 'en', 'autre'), 'Sauvegarde/rechargement : langues multiples, y compris "Autre"');
gws_test_assert($reloaded_langues['langue_autre_precision'] === 'Russe', 'Sauvegarde/rechargement : précision "Autre"');

$reloaded_contact = gwseq_get_membre_contact(101);
gws_test_assert($reloaded_contact['telephone'] === '+33 6 11 22 33 44', 'Sauvegarde/rechargement : téléphone');
gws_test_assert($reloaded_contact['email'] === 'camille.rousseau@example.com', 'Sauvegarde/rechargement : e-mail');
gws_test_assert($reloaded_contact['whatsapp'] === '+33 7 55 66 77 88', 'Sauvegarde/rechargement : WhatsApp');
gws_test_assert($reloaded_contact['instagram'] === 'https://instagram.com/camille', 'Sauvegarde/rechargement : Instagram');
gws_test_assert($reloaded_contact['facebook'] === 'https://facebook.com/camille', 'Sauvegarde/rechargement : Facebook');
gws_test_assert($reloaded_contact['linkedin'] === 'https://linkedin.com/in/camille', 'Sauvegarde/rechargement : LinkedIn');
gws_test_assert($reloaded_contact['tiktok'] === 'https://tiktok.com/@camille', 'Sauvegarde/rechargement : TikTok');
gws_test_assert($reloaded_contact['site'] === 'https://camille-elevage.example.com', 'Sauvegarde/rechargement : Site/lien externe');

// --- Suppression de "Autre" au réenregistrement -> la précision est nettoyée en base, pas
// seulement dans une fonction pure isolée. Post dédié (106, distinct de 101) pour ne pas perturber
// les fixtures réutilisées plus loin par les tests de rendu/colonnes. ---
$_POST = array(
  GWSEQ_MEMBRE_NONCE_FIELD => 'stub-nonce',
  '_gwseq_membre_langues' => array('fr', 'autre'),
  '_gwseq_membre_langue_autre_precision' => 'Russe',
);
gwseq_save_membre_meta(106);
gws_test_assert(gwseq_get_membre_langues(106)['langue_autre_precision'] === 'Russe', 'Sauvegarde : précision "Autre" bien enregistrée avant retrait (fixture du test suivant)');

$_POST = array(
  GWSEQ_MEMBRE_NONCE_FIELD => 'stub-nonce',
  '_gwseq_membre_langues' => array('fr'),
  '_gwseq_membre_langue_autre_precision' => 'Russe', // volontairement encore soumis
);
gwseq_save_membre_meta(106);
$reloaded_langues_apres_retrait = gwseq_get_membre_langues(106);
gws_test_assert($reloaded_langues_apres_retrait['langues'] === array('fr'), 'Sauvegarde : "Autre" retiré -> langues mises à jour en base');
gws_test_assert($reloaded_langues_apres_retrait['langue_autre_precision'] === '', 'Sauvegarde : "Autre" retiré -> précision nettoyée EN BASE au réenregistrement, jamais conservée silencieusement');

$_POST = array();

// =====================================================================================
// Sécurité de la sauvegarde
// =====================================================================================

$_POST = array(GWSEQ_MEMBRE_NONCE_FIELD => 'stub-nonce', '_gwseq_membre_prenom' => 'Ne', '_gwseq_membre_nom' => 'PasEnregistrer');

$GLOBALS['__gwseq_test_security']['nonce_valid'] = false;
gwseq_save_membre_meta(102);
gws_test_assert(gwseq_get_membre_identity(102)['prenom'] === '', 'Sécurité : nonce invalide -> aucune sauvegarde');
$GLOBALS['__gwseq_test_security']['nonce_valid'] = true;

$GLOBALS['__gwseq_test_security']['can_edit'] = false;
gwseq_save_membre_meta(102);
gws_test_assert(gwseq_get_membre_identity(102)['prenom'] === '', 'Sécurité : permissions insuffisantes (current_user_can) -> aucune sauvegarde');
$GLOBALS['__gwseq_test_security']['can_edit'] = true;

$GLOBALS['__gwseq_test_security']['is_revision'] = true;
gwseq_save_membre_meta(102);
gws_test_assert(gwseq_get_membre_identity(102)['prenom'] === '', 'Sécurité : révision -> aucune sauvegarde');
$GLOBALS['__gwseq_test_security']['is_revision'] = false;

gwseq_save_membre_meta(102);
gws_test_assert(gwseq_get_membre_identity(102)['prenom'] === 'Ne', 'Sécurité : nonce valide + permissions + non-révision -> sauvegarde réelle effectuée');

$_POST = array();

// =====================================================================================
// Permissions Éditeur (§10) : aucune capacité technique supplémentaire créée pour ce module —
// le post type est enregistré sans 'capability_type' personnalisé (voir includes/post-types.php),
// donc avec le type par défaut 'post', déjà accessible en écriture par le rôle Éditeur natif.
// =====================================================================================

$post_types_source = file_get_contents($module_dir . 'includes/post-types.php');
if (preg_match('/register_post_type\(GWSEQ_CPT_MEMBRE,\s*array\((.*?)\)\);/s', $post_types_source, $membre_registration_match)) {
  gws_test_assert(
    strpos($membre_registration_match[1], 'capability_type') === false,
    'Permissions : le post type Membre est enregistré SANS capability_type personnalisé (héritage direct des capacités standard \'post\', déjà accordées au rôle Éditeur — §10 : aucune capacité technique supplémentaire créée pour ce seul module)'
  );
} else {
  gws_test_assert(false, 'Permissions : enregistrement du post type Membre introuvable dans includes/post-types.php');
}

// =====================================================================================
// Colonnes d'administration (§9) : Photo | Nom | Fonction / rôle | Localisation | Langues | Ordre
// ----- colonne native "Date" retirée
// =====================================================================================

$native_columns = array('cb' => '<input type="checkbox">', 'title' => 'Titre', 'date' => 'Date');
$membre_columns = gwseq_membre_admin_columns($native_columns);

gws_test_assert(array_keys($membre_columns) === array('cb', 'gwseq_membre_photo', 'title', 'gwseq_membre_fonction', 'gwseq_membre_localisation', 'gwseq_membre_langues', 'gwseq_membre_ordre'), 'Colonnes admin : ordre exact demandé — Photo | Nom | Fonction / rôle | Localisation | Langues | Ordre (cb en plus, natif WordPress, toujours en premier)');
gws_test_assert($membre_columns['title'] === 'Nom', 'Colonnes admin : la colonne "title" est relabellée "Nom"');
gws_test_assert(!array_key_exists('date', $membre_columns), 'Colonnes admin (§9) : la colonne native "Date" est bien retirée de cette vue');

// --- Contenu réel des colonnes ---
$GLOBALS['__gwseq_test_thumbnails'][101] = '<img src="https://example.test/photo-40x40.jpg" width="40" height="40">';
ob_start();
gwseq_membre_admin_column_content('gwseq_membre_photo', 101);
$photo_output = ob_get_clean();
gws_test_assert(strpos($photo_output, '40x40') !== false, 'Colonne Photo : miniature WordPress (40x40) affichée, jamais l\'image originale');

ob_start();
gwseq_membre_admin_column_content('gwseq_membre_photo', 999);
$photo_output_absent = ob_get_clean();
gws_test_assert($photo_output_absent === '—', 'Colonne Photo : aucune photo définie -> tiret, jamais d\'erreur');

ob_start();
gwseq_membre_admin_column_content('gwseq_membre_fonction', 101);
gws_test_assert(ob_get_clean() === esc_html("Responsable d'élevage"), 'Colonne Fonction / rôle : valeur réelle affichée (échappée pour la sortie HTML)');

ob_start();
gwseq_membre_admin_column_content('gwseq_membre_fonction', 999);
gws_test_assert(ob_get_clean() === '—', 'Colonne Fonction / rôle : non renseignée -> tiret');

ob_start();
gwseq_membre_admin_column_content('gwseq_membre_localisation', 101);
gws_test_assert(ob_get_clean() === 'Haras principal', 'Colonne Localisation : valeur réelle affichée');

ob_start();
gwseq_membre_admin_column_content('gwseq_membre_localisation', 999);
gws_test_assert(ob_get_clean() === '—', 'Colonne Localisation : non renseignée -> tiret');

ob_start();
gwseq_membre_admin_column_content('gwseq_membre_langues', 101);
gws_test_assert(ob_get_clean() === 'Français, Anglais, Russe', 'Colonne Langues : représentation compacte et lisible ("Français, Anglais", "Autre" affiché via sa précision)');

ob_start();
gwseq_membre_admin_column_content('gwseq_membre_langues', 999);
gws_test_assert(ob_get_clean() === '—', 'Colonne Langues : aucune langue renseignée -> tiret');

$GLOBALS['__gwseq_test_post_fields'][101]['menu_order'] = 3;
ob_start();
gwseq_membre_admin_column_content('gwseq_membre_ordre', 101);
gws_test_assert(ob_get_clean() === '3', 'Colonne Ordre : menu_order natif affiché (même mécanisme que Cheval, aucun glisser-déposer dans ce lot)');

// =====================================================================================
// Meta boxes : trois sections simples (Identité / Profil / Contact), pas le système d'onglets
// couplé à Cheval
// =====================================================================================

gwseq_add_membre_meta_boxes();
gws_test_assert(
  $GLOBALS['__gwseq_test_meta_boxes'] === array('gwseq-membre-identite', 'gwseq-membre-profil', 'gwseq-membre-contact'),
  'Meta boxes : trois sections enregistrées dans l\'ordre Identité / Profil / Contact (§7)'
);

$membre_editor_source = file_get_contents($module_dir . 'includes/membre-editor.php');
gws_test_assert(
  strpos($membre_editor_source, 'gwseqChevalTabs') === false && strpos($membre_editor_source, 'cheval-tabs') === false,
  'Meta boxes : le système d\'onglets de Cheval n\'est pas réutilisé pour Membre (aucun couplage étrange, trois sections simples à la place — §7 de la demande)'
);

// --- Rendu réel de la meta box Identité : structure de flux HTML valide (même garde-fou que
// Cheval/Pedigree) ---
$post_101 = (object) array('ID' => 101);
ob_start();
gwseq_render_membre_identite_box($post_101);
$identite_html = ob_get_clean();
gws_test_assert(strpos($identite_html, 'name="_gwseq_membre_prenom"') !== false, 'Rendu Identité : champ Prénom réellement rendu');
gws_test_assert(strpos($identite_html, 'name="_gwseq_membre_nom"') !== false, 'Rendu Identité : champ Nom réellement rendu');
gws_test_assert(strpos($identite_html, 'name="_gwseq_membre_fonction"') !== false, 'Rendu Identité : champ Fonction / rôle réellement rendu');
gws_test_assert(strpos($identite_html, 'name="_gwseq_membre_localisation"') !== false, 'Rendu Identité : champ Localisation réellement rendu');
gws_test_assert(strpos($identite_html, 'value="Camille"') !== false, 'Rendu Identité : valeur existante bien préremplie (Camille)');

ob_start();
gwseq_render_membre_profil_box($post_101);
$profil_html = ob_get_clean();
gws_test_assert(strpos($profil_html, 'name="_gwseq_membre_presentation"') !== false, 'Rendu Profil : champ Présentation / parcours réellement rendu');
gws_test_assert(strpos($profil_html, 'name="_gwseq_membre_specialites"') !== false, 'Rendu Profil : champ Spécialités réellement rendu');
gws_test_assert(strpos($profil_html, 'name="_gwseq_membre_diplomes"') !== false, 'Rendu Profil : champ Diplômes / qualifications réellement rendu');
gws_test_assert(substr_count($profil_html, 'name="_gwseq_membre_langues[]"') === 12, 'Rendu Profil : les 12 cases à cocher Langues sont réellement rendues');
gws_test_assert(preg_match('/value="fr"[^>]*checked/', $profil_html) === 1, 'Rendu Profil : la langue déjà enregistrée (Français) reste cochée');
gws_test_assert(preg_match('/value="autre"[^>]*checked/', $profil_html) === 1, 'Rendu Profil : "Autre" reste coché quand il fait partie de la sélection enregistrée');
gws_test_assert(strpos($profil_html, 'value="Russe"') !== false, 'Rendu Profil : la précision "Autre" déjà enregistrée est bien préremplie');
gws_test_assert(strpos($profil_html, 'style=""') !== false || preg_match('/langue-autre-precision"\s+style=""/', $profil_html), 'Rendu Profil : le bloc "Préciser" est visible quand "Autre" est déjà sélectionné');

// --- Membre sans "Autre" sélectionné : le bloc "Préciser" reste présent dans le DOM mais masqué
// (comportement sans JavaScript préservé) ---
$_POST = array(
  GWSEQ_MEMBRE_NONCE_FIELD => 'stub-nonce',
  '_gwseq_membre_langues' => array('fr'),
);
gwseq_save_membre_meta(104);
$post_104 = (object) array('ID' => 104);
ob_start();
gwseq_render_membre_profil_box($post_104);
$profil_html_104 = ob_get_clean();
gws_test_assert(strpos($profil_html_104, 'data-gwseq-membre-fields="langue-autre-precision"') !== false, 'Rendu Profil : le bloc "Préciser" reste présent dans le DOM même quand "Autre" n\'est pas sélectionné (fonctionne sans JavaScript)');
gws_test_assert(preg_match('/langue-autre-precision"\s+style="display:none;"/', $profil_html_104) === 1, 'Rendu Profil : le bloc "Préciser" est masqué par défaut quand "Autre" n\'est pas sélectionné');
$_POST = array();

ob_start();
gwseq_render_membre_contact_box($post_101);
$contact_html = ob_get_clean();
foreach (array('_gwseq_membre_telephone', '_gwseq_membre_email', '_gwseq_membre_whatsapp', '_gwseq_membre_instagram', '_gwseq_membre_facebook', '_gwseq_membre_linkedin', '_gwseq_membre_tiktok', '_gwseq_membre_site') as $field_name) {
  gws_test_assert(strpos($contact_html, 'name="' . $field_name . '"') !== false, "Rendu Contact : champ $field_name réellement rendu");
}
gws_test_assert(strpos($contact_html, 'type="email"') !== false, 'Rendu Contact : le champ E-mail utilise bien type="email"');
gws_test_assert(substr_count($contact_html, 'type="url"') === 5, 'Rendu Contact : les cinq champs URL (Instagram/Facebook/LinkedIn/TikTok/Site) utilisent bien type="url"');

// --- Micro-corrections UX post-recette : aide à la saisie des réseaux sociaux/URL et de WhatsApp
// (recette runtime ayant révélé qu'une saisie "www.google.com" sans https:// n'était pas conservée
// — aucun changement de la logique de stockage/sanitation, uniquement du texte d'aide) ---
$expected_url_placeholders = array(
  '_gwseq_membre_instagram' => 'https://www.instagram.com/votrecompte/',
  '_gwseq_membre_facebook' => 'https://www.facebook.com/votrepage/',
  '_gwseq_membre_linkedin' => 'https://www.linkedin.com/in/votreprofil/',
  '_gwseq_membre_tiktok' => 'https://www.tiktok.com/@votrecompte',
  '_gwseq_membre_site' => 'https://www.votresite.fr',
);
foreach ($expected_url_placeholders as $field_name => $expected_placeholder) {
  gws_test_assert(
    strpos($contact_html, 'placeholder="' . $expected_placeholder . '"') !== false,
    "Aide à la saisie : $field_name affiche le placeholder explicite \"$expected_placeholder\""
  );
}
gws_test_assert(
  substr_count($contact_html, esc_html('Saisissez l\'URL complète, avec https://')) === 5,
  'Aide à la saisie : les cinq champs URL affichent tous l\'aide "Saisissez l\'URL complète, avec https://"'
);
gws_test_assert(
  strpos($contact_html, 'Format international recommandé, ex. +33 6 12 34 56 78') !== false,
  'Aide à la saisie : WhatsApp affiche l\'aide "Format international recommandé, ex. +33 6 12 34 56 78"'
);
// Aucun changement de la logique de stockage/sanitation : la même fonction de sanitation pure
// reste seule autorité, revérifiée plus haut dans ce fichier (Contact : URL Instagram/.../
// téléphone international non détruit).
gws_test_assert(
  gwseq_sanitize_membre_contact_input(array('_gwseq_membre_instagram' => 'www.instagram.com/votrecompte'))['instagram'] === 'www.instagram.com/votrecompte',
  'Non-régression : la sanitation ne reconstruit jamais automatiquement une URL à partir de www./@compte (aide visuelle uniquement, aucune logique ajoutée)'
);

// =====================================================================================
// Éditeur par blocs désactivé, titre natif masqué (§8)
// =====================================================================================

gws_test_assert(gwseq_disable_block_editor_for_membre(true, GWSEQ_CPT_MEMBRE) === false, 'Éditeur par blocs : désactivé pour gwseq_membre (fiche 100% structurée)');
gws_test_assert(gwseq_disable_block_editor_for_membre(true, 'post') === true, 'Éditeur par blocs : inchangé pour un autre post type (ex. Article)');
gws_test_assert(gwseq_disable_block_editor_for_membre(true, GWSEQ_CPT_CHEVAL) === true, 'Éditeur par blocs : inchangé pour Cheval (aucune régression croisée entre modules)');

$GLOBALS['__gwseq_test_screen'] = (object) array('post_type' => GWSEQ_CPT_MEMBRE);
ob_start();
gwseq_hide_membre_native_title_box();
$title_css = ob_get_clean();
gws_test_assert(strpos($title_css, '#titlediv') !== false && strpos($title_css, 'display: none') !== false, 'Titre natif : règle CSS masquant #titlediv bien produite sur l\'écran d\'édition d\'un membre');

$GLOBALS['__gwseq_test_screen'] = (object) array('post_type' => GWSEQ_CPT_CHEVAL);
ob_start();
gwseq_hide_membre_native_title_box();
gws_test_assert(ob_get_clean() === '', 'Titre natif : aucun style injecté sur un autre écran que la fiche membre (ex. Cheval)');
$GLOBALS['__gwseq_test_screen'] = null;

// =====================================================================================
// Meta enregistrées : Langues bien typée 'array' (sélection multiple), le reste en chaîne
// =====================================================================================

gws_test_assert(
  ($GLOBALS['__gwseq_test_registered_meta']['_gwseq_membre_langues']['type'] ?? null) === 'array',
  'Meta enregistrées : _gwseq_membre_langues déclarée de type \'array\' (sélection multiple)'
);
gws_test_assert(
  ($GLOBALS['__gwseq_test_registered_meta']['_gwseq_membre_prenom']['type'] ?? null) === 'string',
  'Meta enregistrées : les champs simples restent déclarés en type \'string\''
);
foreach ($GLOBALS['__gwseq_test_registered_meta'] as $meta_key => $args) {
  if (strpos($meta_key, '_gwseq_membre_') !== 0) continue;
  gws_test_assert(($args['show_in_rest'] ?? null) === false, "Meta enregistrées : $meta_key jamais exposée en REST à ce stade");
}

// =====================================================================================
// Autosave — testé en dernier : DOING_AUTOSAVE est une vraie constante PHP, elle ne peut être
// définie qu'une seule fois par processus (même contrainte que gws-equestrian-cheval-logic-test.php).
// Couvre à la fois le titre auto-dérivé et la sauvegarde des meta.
// =====================================================================================

define('DOING_AUTOSAVE', true);

$_POST = array(GWSEQ_MEMBRE_NONCE_FIELD => 'stub-nonce', '_gwseq_membre_prenom' => 'Auto', '_gwseq_membre_nom' => 'Save');
$data_autosave = gwseq_auto_title_membre(array('post_type' => GWSEQ_CPT_MEMBRE, 'post_title' => 'Titre Avant Autosave'), array());
gws_test_assert($data_autosave['post_title'] === 'Titre Avant Autosave', 'Titre auto : jamais recalculé pendant DOING_AUTOSAVE');

gwseq_save_membre_meta(105);
gws_test_assert(gwseq_get_membre_identity(105)['prenom'] === '', 'Sécurité : DOING_AUTOSAVE -> aucune sauvegarde des meta');
$_POST = array();

// =====================================================================================
// Non-régression : aucun effet de bord sur les autres objets métier GWS
// =====================================================================================

foreach (array_keys($GLOBALS['__gwseq_test_meta']) as $post_id) {
  if (!in_array($post_id, array(100, 101, 102, 103, 104, 106), true)) {
    gws_test_assert(false, "Non-régression : un post_id inattendu ($post_id) a été modifié par les tests Membre");
  }
}
gws_test_assert(
  preg_match_all('/\b(?:add_action|add_filter)\([^)]*GWSEQ_CPT_(CHEVAL|PRESTATION|GROUPE)\b/', $membre_fields_source) === 0,
  'Non-régression : includes/membre-fields.php n\'accroche aucun hook sur un autre post type GWS (aucun couplage fonctionnel — Équipe reste un module entièrement indépendant, voir aussi gws-equestrian-foundations-test.php pour la vérification déclarative des post types enregistrés)'
);

echo ($failures === 0 ? 'Tous les tests sont passés.' : "$failures test(s) en échec.") . "\n";
exit($failures === 0 ? 0 : 1);

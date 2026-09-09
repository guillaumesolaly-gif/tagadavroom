<?php
/**
 * Tests de logique autonomes pour les réglages enrichis en v1.4.0 (logo, WhatsApp, réseaux
 * sociaux, sameAs Schema, crédit Tagada Vroom, champ attachment_id). Même esprit que
 * tests/starter-logic-test.php : stubs WordPress minimaux, aucune installation requise.
 *
 * Exécuter : php tests/settings-helpers-logic-test.php
 * Ne fait pas partie des paquets livrés.
 */

$failures = 0;
function gws_test_assert($condition, $label) {
  global $failures;
  if ($condition) { echo "OK   - $label\n"; }
  else { echo "FAIL - $label\n"; $failures++; }
}

// --- Stubs WordPress minimaux (approximations suffisantes pour tester la logique, pas les
// règles exactes d'échappement/sanitization elles-mêmes, déjà du ressort de WordPress) ---
function add_action(...$args) {}
function apply_filters($tag, $value) { return $value; }
function wp_unslash($value) { return $value; }
function sanitize_key($value) { return strtolower(preg_replace('/[^a-z0-9_\-]/', '', (string) $value)); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function sanitize_textarea_field($value) { return trim((string) $value); }
function sanitize_email($value) { $value = trim((string) $value); return strpos($value, '@') !== false ? $value : ''; }
function esc_url_raw($value) { $value = trim((string) $value); return $value === '' ? '' : $value; }
function wp_http_validate_url($url) {
  if (!is_string($url) || $url === '') return false;
  return preg_match('~^https?://[^\s/$.?#][^\s]*$~i', $url) ? $url : false;
}
function absint($value) { return abs((int) $value); }
function wp_parse_args($args, $defaults = array()) { return array_merge((array) $defaults, (array) $args); }

$GLOBALS['__gws_test_image_attachments'] = array();
function wp_attachment_is_image($id) { return in_array((int) $id, $GLOBALS['__gws_test_image_attachments'], true); }

$GLOBALS['__gws_test_options'] = array();
function get_option($name, $default = false) {
  return array_key_exists($name, $GLOBALS['__gws_test_options']) ? $GLOBALS['__gws_test_options'][$name] : $default;
}

$GLOBALS['__gws_test_attachment_urls'] = array();
function wp_get_attachment_image_url($id, $size = 'full') {
  return $GLOBALS['__gws_test_attachment_urls'][$id] ?? false;
}

$GLOBALS['__gws_test_bloginfo_name'] = 'Site de test';
function get_bloginfo($key = '') {
  return $key === 'name' ? $GLOBALS['__gws_test_bloginfo_name'] : '';
}

function __($text, $domain = 'default') { return $text; }

$GLOBALS['__gws_test_settings_errors'] = array();
function add_settings_error($setting, $code, $message, $type = 'error') {
  $GLOBALS['__gws_test_settings_errors'][] = array('setting' => $setting, 'code' => $code, 'message' => $message, 'type' => $type);
}
function get_settings_errors() { return $GLOBALS['__gws_test_settings_errors']; }

define('ABSPATH', __DIR__ . '/');
$repo_root = dirname(__DIR__);
require $repo_root . '/wp-content/plugins/gws-core/includes/fields.php';
require $repo_root . '/wp-content/plugins/gws-core/includes/settings.php';

// =====================================================================================
// attachment_id : n'accepte qu'un ID pointant réellement vers une image
// =====================================================================================
$GLOBALS['__gws_test_image_attachments'] = array(42);
gws_test_assert(gws_core_field_sanitize('attachment_id', '42') === 42, 'attachment_id : un ID valide (image réelle) est conservé');
gws_test_assert(gws_core_field_sanitize('attachment_id', '99') === 0, 'attachment_id : un ID qui n’est pas une image est rejeté (0)');
gws_test_assert(gws_core_field_sanitize('attachment_id', '') === 0, 'attachment_id : une valeur vide donne 0, jamais d’erreur');

// =====================================================================================
// Réglages par défaut : tous les nouveaux champs sont vides sauf le crédit (activé, URL
// pré-remplie) — un nouveau projet a le crédit visible par défaut sans rien configurer.
// =====================================================================================
$GLOBALS['__gws_test_options'] = array(); // aucune option enregistrée : valeurs par défaut pures
gws_test_assert(gws_core_get_setting('logo_id') === 0, 'Par défaut : aucun logo (0)');
gws_test_assert(gws_core_get_setting('whatsapp_number') === '', 'Par défaut : WhatsApp vide');
gws_test_assert(gws_core_get_setting('linkedin_url') === '', 'Par défaut : LinkedIn vide');
gws_test_assert(gws_core_get_setting('credit_enabled') === '1', 'Par défaut : crédit Tagada Vroom activé');
gws_test_assert(gws_core_get_setting('credit_url') === 'https://tagadavroom.fr/', 'Par défaut : URL Tagada Vroom pré-remplie');
gws_test_assert(gws_core_credit_enabled() === true, 'gws_core_credit_enabled() : vrai par défaut');

// =====================================================================================
// Logo : URL calculée uniquement si un ID est effectivement enregistré
// =====================================================================================
gws_test_assert(gws_core_get_logo_url() === '', 'Sans logo enregistré : gws_core_get_logo_url() renvoie une chaîne vide, jamais une erreur');

$GLOBALS['__gws_test_options'] = array('gws_core_settings' => array('logo_id' => 42));
$GLOBALS['__gws_test_attachment_urls'] = array(42 => 'https://example.test/logo.png');
gws_test_assert(gws_core_get_logo_url() === 'https://example.test/logo.png', 'Avec un logo enregistré : gws_core_get_logo_url() renvoie bien son URL');

// =====================================================================================
// WhatsApp : format international obligatoire (v1.5.0), jamais d'indicatif deviné
// =====================================================================================
$GLOBALS['__gws_test_options'] = array();
gws_test_assert(gws_core_whatsapp_url() === '', 'Sans numéro WhatsApp : aucun lien généré');

$GLOBALS['__gws_test_options'] = array('gws_core_settings' => array('whatsapp_number' => '+33 6 12 34 56 78'));
gws_test_assert(gws_core_whatsapp_url() === 'https://wa.me/33612345678', 'Numéro international avec "+" (espaces compris) : lien wa.me correctement construit — exemple exact de la consigne');

$GLOBALS['__gws_test_options'] = array('gws_core_settings' => array('whatsapp_number' => '0033 6 12 34 56 78'));
gws_test_assert(gws_core_whatsapp_url() === 'https://wa.me/33612345678', 'Numéro international avec "00" : équivalent au "+", même résultat');

$GLOBALS['__gws_test_options'] = array('gws_core_settings' => array('whatsapp_number' => '+33 (6) 12-34-56-78'));
gws_test_assert(gws_core_whatsapp_url() === 'https://wa.me/33612345678', 'Parenthèses et tirets ignorés, seuls "+" et les chiffres comptent');

$GLOBALS['__gws_test_options'] = array('gws_core_settings' => array('whatsapp_number' => '06 12 34 56 78'));
gws_test_assert(gws_core_whatsapp_url() === '', 'Numéro national sans indicatif (bug corrigé en v1.5.0) : aucun lien, jamais d’indicatif deviné');

$GLOBALS['__gws_test_options'] = array('gws_core_settings' => array('whatsapp_number' => '0612345678'));
gws_test_assert(gws_core_whatsapp_url() === '', 'Numéro national sans espaces ni indicatif : aucun lien non plus');

// =====================================================================================
// Réseaux sociaux : uniquement les champs réellement renseignés, jamais une entrée vide
// =====================================================================================
$GLOBALS['__gws_test_options'] = array();
gws_test_assert(gws_core_social_links() === array(), 'Sans aucun réseau renseigné : tableau vide, pas d’entrées vides');

$GLOBALS['__gws_test_options'] = array('gws_core_settings' => array(
  'linkedin_url' => 'https://linkedin.com/company/test',
  'facebook_url' => '',
  'instagram_url' => 'https://instagram.com/test',
));
$social = gws_core_social_links();
gws_test_assert(
  $social === array('linkedin' => 'https://linkedin.com/company/test', 'instagram' => 'https://instagram.com/test'),
  'Avec deux réseaux renseignés sur six : seuls les deux non vides apparaissent (facebook absent, pas vide)'
);

// --- X (v1.5.0) : structuré au même titre que les autres, récupérable individuellement ---
$GLOBALS['__gws_test_options'] = array('gws_core_settings' => array('x_url' => 'https://x.com/test'));
gws_test_assert(gws_core_social_links() === array('x' => 'https://x.com/test'), 'X est bien exposé par gws_core_social_links(), comme LinkedIn/Facebook/Instagram/YouTube/TikTok');

// =====================================================================================
// sameAs Schema : fusion réseaux + Google Business Profile, sans doublon, jamais de valeur vide
// =====================================================================================
$GLOBALS['__gws_test_options'] = array();
gws_test_assert(gws_core_schema_same_as() === array(), 'Sans aucune donnée : sameAs vide (jamais ["", "", ...])');

$GLOBALS['__gws_test_options'] = array('gws_core_settings' => array(
  'linkedin_url' => 'https://linkedin.com/company/test',
  'google_business_url' => 'https://maps.google.com/test',
));
gws_test_assert(
  gws_core_schema_same_as() === array('https://linkedin.com/company/test', 'https://maps.google.com/test'),
  'sameAs : combine réseaux structurés et fiche Google Business Profile, uniquement les valeurs renseignées'
);

// =====================================================================================
// sameAs : le champ libre 'social_links' (une URL par ligne) alimente aussi sameAs — lignes
// vides supprimées, URLs sanitizées/validées, dédupliquées avec les réseaux structurés et GBP,
// jamais reprises par gws_core_social_links() (réseaux nommés uniquement, pour le front).
// =====================================================================================
$GLOBALS['__gws_test_options'] = array('gws_core_settings' => array(
  'social_links' => "https://mastodon.social/@test\n\n  \nhttps://bsky.app/profile/test\nceci n'est pas une URL\nhttps://mastodon.social/@test",
));
gws_test_assert(
  gws_core_extra_social_urls() === array('https://mastodon.social/@test', 'https://bsky.app/profile/test'),
  'gws_core_extra_social_urls() : lignes vides et ligne invalide ignorées, doublon interne à social_links supprimé'
);
gws_test_assert(
  gws_core_social_links() === array(),
  'social_links ne fuite jamais dans gws_core_social_links() (réseaux nommés uniquement)'
);
gws_test_assert(
  gws_core_schema_same_as() === array('https://mastodon.social/@test', 'https://bsky.app/profile/test'),
  'sameAs : reprend les URLs valides de social_links quand aucun réseau structuré n’est renseigné'
);

$GLOBALS['__gws_test_options'] = array('gws_core_settings' => array(
  'linkedin_url' => 'https://linkedin.com/company/test',
  'google_business_url' => 'https://maps.google.com/test',
  'social_links' => "https://linkedin.com/company/test\nhttps://mastodon.social/@test",
));
gws_test_assert(
  gws_core_schema_same_as() === array('https://linkedin.com/company/test', 'https://maps.google.com/test', 'https://mastodon.social/@test'),
  'sameAs : fusion réseaux structurés + GBP + social_links, doublon entre LinkedIn structuré et social_links dédupliqué'
);

$GLOBALS['__gws_test_options'] = array('gws_core_settings' => array('social_links' => ''));
gws_test_assert(gws_core_extra_social_urls() === array(), 'social_links vide : aucune URL, tableau vide');

// --- X (v1.5.0) : dédupliqué avec la même URL saisie aussi dans social_links ---
$GLOBALS['__gws_test_options'] = array('gws_core_settings' => array(
  'x_url' => 'https://x.com/test',
  'social_links' => "https://x.com/test\nhttps://bsky.app/profile/test",
));
gws_test_assert(
  gws_core_schema_same_as() === array('https://x.com/test', 'https://bsky.app/profile/test'),
  'sameAs : la même URL X saisie à la fois dans le champ structuré et dans social_links n’apparaît qu’une fois'
);

// =====================================================================================
// Réseaux sociaux dans le header/footer (v1.5.0) : footer activé par défaut, header désactivé
// =====================================================================================
$GLOBALS['__gws_test_options'] = array();
gws_test_assert(gws_core_show_footer_social() === true, 'Pictogrammes sociaux dans le pied de page : activés par défaut');
gws_test_assert(gws_core_show_header_social() === false, 'Pictogrammes sociaux dans l’en-tête : désactivés par défaut');

$GLOBALS['__gws_test_options'] = array('gws_core_settings' => array('header_social_enabled' => '1', 'footer_social_enabled' => ''));
gws_test_assert(gws_core_show_header_social() === true, 'En-tête : activable explicitement');
gws_test_assert(gws_core_show_footer_social() === false, 'Pied de page : désactivable explicitement');

// =====================================================================================
// Crédit Tagada Vroom : désactivable, et son URL suit le même sanitize que les autres URLs
// =====================================================================================
$GLOBALS['__gws_test_options'] = array('gws_core_settings' => array('credit_enabled' => ''));
gws_test_assert(gws_core_credit_enabled() === false, 'Crédit désactivé explicitement : gws_core_credit_enabled() renvoie faux');

$sanitized = gws_core_sanitize_settings(array('credit_enabled' => '1', 'credit_url' => 'https://exemple-agence.test/'));
gws_test_assert(
  $sanitized['credit_enabled'] === '1' && $sanitized['credit_url'] === 'https://exemple-agence.test/',
  'Le formulaire de réglages peut changer l’URL du crédit (pas figée sur tagadavroom.fr)'
);

// =====================================================================================
// Lot 2C — Ma structure & identité de marque (§21 de la demande) : couleurs, contraste,
// présentation, helpers consolidés. Même esprit et mêmes stubs que ci-dessus.
// =====================================================================================

// --- Sanitation de la couleur : uniquement '#rrggbb', jamais un nom CSS ni une forme abrégée ---
gws_test_assert(gws_core_field_sanitize('color', '#1d4ed8') === '#1d4ed8', 'color : une valeur hex valide (minuscules) est conservée telle quelle');
gws_test_assert(gws_core_field_sanitize('color', '#1D4ED8') === '#1d4ed8', 'color : une valeur hex valide en majuscules est normalisée en minuscules');
gws_test_assert(gws_core_field_sanitize('color', '#fff') === '', 'color : la forme abrégée à 3 chiffres est rejetée (jamais devinée)');
gws_test_assert(gws_core_field_sanitize('color', 'blue') === '', 'color : un nom de couleur CSS est rejeté');
gws_test_assert(gws_core_field_sanitize('color', 'javascript:alert(1)') === '', 'color : une valeur arbitraire non hexadécimale est rejetée');
gws_test_assert(gws_core_field_sanitize('color', '') === '', 'color : une valeur vide reste vide (pas d’erreur)');

// --- Réglages par défaut : les nouveaux champs du Lot 2C sont vides par défaut ---
$GLOBALS['__gws_test_options'] = array();
gws_test_assert(gws_core_get_setting('primary_color') === '', 'Par défaut : aucune couleur principale personnalisée enregistrée');
gws_test_assert(gws_core_get_setting('secondary_color') === '', 'Par défaut : aucune couleur secondaire personnalisée enregistrée');
gws_test_assert(gws_core_get_setting('presentation') === '', 'Par défaut : présentation vide');
gws_test_assert(gws_core_get_setting('website_url') === '', 'Par défaut : site web vide');
gws_test_assert(gws_core_get_setting('address_line_2') === '', 'Par défaut : complément d’adresse vide');
gws_test_assert(gws_core_get_setting('country') === '', 'Par défaut : pays vide');

// --- Couleurs effectives : repli sur les couleurs GWS par défaut, jamais une chaîne vide ---
$GLOBALS['__gws_test_options'] = array();
gws_test_assert(gws_core_get_primary_color() === gws_core_default_primary_color(), 'Sans couleur choisie : gws_core_get_primary_color() renvoie la couleur GWS par défaut');
gws_test_assert(gws_core_get_secondary_color() === gws_core_default_secondary_color(), 'Sans couleur choisie : gws_core_get_secondary_color() renvoie la couleur GWS par défaut');
gws_test_assert(gws_core_default_primary_color() !== '#03A9F4' && gws_core_default_primary_color() !== '#03a9f4', 'La couleur par défaut n’est jamais le bleu de branding BO Tagada Vroom (#03A9F4)');

// --- Une couleur personnalisée et valide prend toujours le pas sur la couleur par défaut ---
$GLOBALS['__gws_test_options'] = array('gws_core_settings' => array('primary_color' => '#ff0000', 'secondary_color' => '#00ff00'));
gws_test_assert(gws_core_get_primary_color() === '#ff0000', 'Couleur principale personnalisée : prioritaire sur la couleur par défaut');
gws_test_assert(gws_core_get_secondary_color() === '#00ff00', 'Couleur secondaire personnalisée : prioritaire sur la couleur par défaut');

// --- Aucune écriture automatique de la couleur par défaut dans les réglages enregistrés (§8) ---
$GLOBALS['__gws_test_options'] = array();
$sanitized_empty = gws_core_sanitize_settings(array());
gws_test_assert(
  $sanitized_empty['primary_color'] === '' && $sanitized_empty['secondary_color'] === '',
  'Un enregistrement sans couleur choisie ne persiste JAMAIS la couleur GWS par défaut dans gws_core_settings — le repli reste purement calculé à la lecture'
);
gws_test_assert(
  gws_core_sanitize_settings(array('primary_color' => 'pas-une-couleur'))['primary_color'] === '',
  'Une couleur invalide soumise au formulaire est rejetée (jamais enregistrée telle quelle)'
);

// --- Présentation : enregistrée normalement dans la limite ---
$long_presentation = str_repeat('a', gws_core_structure_presentation_max_length() + 50);
$sanitized_presentation = gws_core_sanitize_settings(array('presentation' => 'Une présentation raisonnable.'));
gws_test_assert($sanitized_presentation['presentation'] === 'Une présentation raisonnable.', 'Présentation : une valeur dans la limite est enregistrée telle quelle');

// --- Présentation trop longue : REJET CIBLÉ (jamais de troncature silencieuse), valeur précédente
// conservée, les AUTRES champs de la même soumission continuent d'être enregistrés, message
// explicite ajouté via add_settings_error() (corrigé après recette réelle) ---
$GLOBALS['__gws_test_options'] = array('gws_core_settings' => array(
  'presentation' => 'Ancienne présentation déjà enregistrée.',
  'entity_name' => 'Ancien nom',
));
$GLOBALS['__gws_test_settings_errors'] = array();
$sanitized_rejected = gws_core_sanitize_settings(array(
  'presentation' => $long_presentation,
  'entity_name' => 'Nouveau nom valide',
));
gws_test_assert(
  $sanitized_rejected['presentation'] === 'Ancienne présentation déjà enregistrée.',
  'Présentation trop longue : la valeur PRÉCÉDENTE est conservée, jamais tronquée ni vidée'
);
gws_test_assert(
  $sanitized_rejected['entity_name'] === 'Nouveau nom valide',
  'Présentation trop longue : les AUTRES champs valides de la même soumission sont malgré tout enregistrés (ici le nom)'
);
$errors_after_rejection = get_settings_errors();
gws_test_assert(count($errors_after_rejection) === 1, 'Présentation trop longue : exactement un message d’erreur explicite est ajouté (add_settings_error)');
gws_test_assert(
  !empty($errors_after_rejection) && strpos($errors_after_rejection[0]['message'], '1500') !== false && strpos($errors_after_rejection[0]['message'], 'Présentation') !== false,
  'Présentation trop longue : le message mentionne explicitement le champ et la limite (1500 caractères), et précise que rien n’a été enregistré pour ce champ'
);

// --- Cas limite : exactement à la limite (jamais rejeté), un caractère au-delà (rejeté) ---
$GLOBALS['__gws_test_options'] = array();
$GLOBALS['__gws_test_settings_errors'] = array();
$exact_length = str_repeat('a', gws_core_structure_presentation_max_length());
gws_test_assert(
  gws_core_sanitize_settings(array('presentation' => $exact_length))['presentation'] === $exact_length,
  'Présentation : un texte exactement à la limite est accepté, pas rejeté'
);
$one_over = str_repeat('a', gws_core_structure_presentation_max_length() + 1);
gws_test_assert(
  gws_core_sanitize_settings(array('presentation' => $one_over))['presentation'] === '',
  'Présentation : un texte d’UN caractère au-delà de la limite est rejeté (aucune valeur précédente ici => champ vide conservé)'
);

// --- Contraste : noir/blanc, couleurs par défaut GWS, et quelques cas limites clair/foncé ---
gws_test_assert(gws_core_contrast_color('#000000') === '#ffffff', 'Contraste : texte blanc sur fond noir');
gws_test_assert(gws_core_contrast_color('#ffffff') === '#000000', 'Contraste : texte noir sur fond blanc');
gws_test_assert(gws_core_contrast_color(gws_core_default_primary_color()) === '#ffffff', 'Contraste : la couleur principale GWS par défaut (bleu foncé) appelle un texte blanc');
gws_test_assert(gws_core_contrast_color(gws_core_default_secondary_color()) === '#ffffff', 'Contraste : la couleur secondaire GWS par défaut (vert-bleu foncé) appelle un texte blanc');
gws_test_assert(gws_core_contrast_color('#ffff00') === '#000000', 'Contraste : jaune vif (clair) appelle un texte noir');
gws_test_assert(gws_core_contrast_color('#0000ff') === '#ffffff', 'Contraste : bleu pur (foncé) appelle un texte blanc');
gws_test_assert(gws_core_contrast_color('#808080') === '#000000', 'Contraste : gris moyen (cas limite) — vérifie que l’algorithme reste déterministe et ne plante pas');
gws_test_assert(gws_core_contrast_color('invalide') === '#000000', 'Contraste : une valeur non hexadécimale retombe sur noir par sécurité, jamais une erreur');

// --- Nom de la structure : repli natif WordPress si le champ est vide ---
$GLOBALS['__gws_test_options'] = array();
$GLOBALS['__gws_test_bloginfo_name'] = 'Site de test';
gws_test_assert(gws_core_structure_name() === 'Site de test', 'Sans nom de structure renseigné : repli sur le nom du site WordPress');
$GLOBALS['__gws_test_options'] = array('gws_core_settings' => array('entity_name' => 'Haras de Test'));
gws_test_assert(gws_core_structure_name() === 'Haras de Test', 'Avec un nom de structure renseigné : celui-ci est utilisé, jamais le nom du site');

// --- API consolidée gws_core_structure_identity() : jamais une clé manquante, jamais d'erreur ---
$GLOBALS['__gws_test_options'] = array('gws_core_settings' => array(
  'entity_name' => 'Haras de Test',
  'primary_color' => '#123456',
  'presentation' => 'Une structure de test.',
  'address_line' => '1 rue de Test',
  'address_line_2' => 'Bâtiment B',
  'postal_code' => '75000',
  'city' => 'Paris',
  'country' => 'France',
  'website_url' => 'https://exemple-structure.test/',
));
$identity = gws_core_structure_identity();
gws_test_assert($identity['name'] === 'Haras de Test', 'gws_core_structure_identity() : nom correctement assemblé');
gws_test_assert($identity['primary_color'] === '#123456', 'gws_core_structure_identity() : couleur principale personnalisée reprise telle quelle');
gws_test_assert($identity['secondary_color'] === gws_core_default_secondary_color(), 'gws_core_structure_identity() : couleur secondaire non choisie => repli par défaut');
gws_test_assert($identity['primary_color_contrast'] === gws_core_contrast_color('#123456'), 'gws_core_structure_identity() : contraste calculé cohérent avec gws_core_contrast_color()');
gws_test_assert($identity['presentation'] === 'Une structure de test.', 'gws_core_structure_identity() : présentation reprise');
gws_test_assert($identity['address_line_2'] === 'Bâtiment B' && $identity['country'] === 'France' && $identity['website_url'] === 'https://exemple-structure.test/', 'gws_core_structure_identity() : nouvelles coordonnées (complément, pays, site web) correctement reprises');

// --- Non-régression : une installation existante SANS les nouvelles clés en base continue de
// fonctionner sans avertissement PHP ni valeur incohérente (§19 — compatibilité champs absents) ---
$GLOBALS['__gws_test_options'] = array('gws_core_settings' => array(
  // Simule une option enregistrée AVANT le Lot 2C : uniquement les anciennes clés.
  'entity_name' => 'Ancienne Structure',
  'phone_display' => '+33 1 23 45 67 89',
  'address_line' => '1 rue Historique',
  'postal_code' => '75001',
  'city' => 'Paris',
));
gws_test_assert(gws_core_get_setting('entity_name') === 'Ancienne Structure', 'Donnée historique (avant Lot 2C) : toujours lue normalement');
gws_test_assert(gws_core_get_setting('primary_color') === '', 'Donnée historique : clé de couleur absente => vide, jamais un avertissement PHP');
gws_test_assert(gws_core_get_primary_color() === gws_core_default_primary_color(), 'Donnée historique : la couleur effective retombe proprement sur la couleur par défaut GWS');
gws_test_assert(gws_core_get_setting('address_line') === '1 rue Historique' && gws_core_get_setting('postal_code') === '75001', 'Donnée historique : coordonnées existantes non régressées par l’ajout des nouveaux champs');
$identity_legacy = gws_core_structure_identity();
gws_test_assert(is_array($identity_legacy) && $identity_legacy['name'] === 'Ancienne Structure', 'Donnée historique : gws_core_structure_identity() reste utilisable sans erreur sur une ancienne installation');

echo "\n" . ($failures === 0 ? 'Tous les tests sont passés.' : "$failures test(s) en échec.") . "\n";
exit($failures === 0 ? 0 : 1);

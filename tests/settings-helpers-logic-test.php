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
// WhatsApp : lien wa.me construit uniquement si un numéro est renseigné
// =====================================================================================
$GLOBALS['__gws_test_options'] = array();
gws_test_assert(gws_core_whatsapp_url() === '', 'Sans numéro WhatsApp : aucun lien généré');

$GLOBALS['__gws_test_options'] = array('gws_core_settings' => array('whatsapp_number' => '33 6 12 34 56 78'));
gws_test_assert(gws_core_whatsapp_url() === 'https://wa.me/33612345678', 'Avec un numéro WhatsApp (espaces compris) : lien wa.me correctement construit');

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
  'Avec deux réseaux renseignés sur cinq : seuls les deux non vides apparaissent (facebook absent, pas vide)'
);

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

echo "\n" . ($failures === 0 ? 'Tous les tests sont passés.' : "$failures test(s) en échec.") . "\n";
exit($failures === 0 ? 0 : 1);

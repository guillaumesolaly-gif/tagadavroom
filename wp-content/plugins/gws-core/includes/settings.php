<?php
/**
 * Réglages génériques de l'entité (identité, identité visuelle, présentation, coordonnées,
 * réseaux sociaux, logo) — seule source de vérité, indépendante du thème actif. Un site métier
 * étend cette liste via le filtre 'gws_core_settings_fields' plutôt qu'en modifiant ce fichier —
 * c'est pourquoi seuls des réseaux réellement universels figurent ici (pas de Viber, Messenger,
 * etc.).
 *
 * BO — Lot 2C : côté interface, cet objet est présenté sous le nom « Ma structure » (menu
 * Réglages > Ma structure, voir includes/admin/settings-page.php) plutôt que « Entité » —
 * changement de VOCABULAIRE UNIQUEMENT. L'option WordPress ('gws_core_settings'), le groupe de
 * réglages ('gws_core_settings_group'), le préfixe des fonctions ('gws_core_') et chaque clé de
 * champ existante restent strictement inchangés : aucune migration de données, aucune rupture de
 * compatibilité pour un site déjà en production. « Ma structure » est la source canonique
 * d'identité/de marque du site : nom, logo, couleurs, présentation et coordonnées ne doivent
 * jamais être dupliqués ailleurs (thème, futur PDF, futur Catalogue) — ces consommateurs lisent
 * cet objet via les helpers ci-dessous, notamment l'API consolidée gws_core_structure_identity().
 */

if (!defined('ABSPATH')) exit;

/**
 * Couleurs par défaut GWS Equestrian (Lot 2C, §8-9) — utilisées UNIQUEMENT en repli d'affichage
 * (gws_core_get_primary_color()/gws_core_get_secondary_color() ci-dessous) quand la structure n'a
 * choisi aucune couleur ; jamais écrites automatiquement dans 'gws_core_settings' (voir le test
 * dédié dans tests/settings-helpers-logic-test.php). Filtrables comme le reste de ce fichier, pour
 * qu'un projet business puisse poser ses propres couleurs par défaut sans toucher ce fichier.
 *
 * CHOIX (documenté ici pour un futur développeur, comme demandé) : #1d4ed8 / #0f766e sont déjà les
 * couleurs --color-primary / --color-accent du design system du thème (assets/css/tokens.css) —
 * un bleu et un vert-bleu professionnels et neutres, sans charte imposée. Le bleu Tagada Vroom
 * (#03A9F4, branding du BO uniquement — introuvable ailleurs dans ce dépôt) n'a volontairement
 * PAS été retenu comme couleur commerciale par défaut du client : rien ne garantit qu'il convient
 * à un projet GWS Equestrian donné, et il appartient à l'identité de l'éditeur (Tagada Vroom), pas
 * à celle du client. Aucun rose Tagada Vroom n'est utilisé.
 */
function gws_core_default_primary_color() {
  return apply_filters('gws_core_default_primary_color', '#1d4ed8');
}

function gws_core_default_secondary_color() {
  return apply_filters('gws_core_default_secondary_color', '#0f766e');
}

/**
 * Limite de longueur du champ « Présentation » (Lot 2C, §5, ajustée après recette) — SEULE
 * source de vérité, lue par la sanitation serveur (gws_core_sanitize_settings() ci-dessous) et
 * par le rendu du formulaire (attribut HTML `maxlength`, includes/admin/settings-page.php),
 * jamais un nombre dupliqué à un second endroit — même principe que
 * gwseq_cheval_editorial_field_max_length() dans le module gws-equestrian (Lot 2A).
 *
 * CHOIX (1500 caractères, documenté comme demandé) : même philosophie que les champs éditoriaux
 * Cheval à limite fixe (texte libre plafonné, pas d'éditeur riche), mais un peu plus généreuse que
 * la « Présentation » d'un Cheval (1200 caractères, un seul sujet) puisque ce texte présente
 * l'ensemble de la structure et peut alimenter une future page éditoriale de Catalogue — « quelques
 * courts paragraphes », pas un article.
 *
 * COMPORTEMENT EN CAS DE DÉPASSEMENT (corrigé après recette — jamais de troncature silencieuse,
 * même principe de rejet ciblé que gwseq_set_cheval_editorial() côté gws-equestrian) : voir
 * gws_core_sanitize_settings() ci-dessous. La contrainte technique évoquée dans une version
 * précédente de ce commentaire ne s'est pas confirmée à l'implémentation — l'API Réglages native
 * de WordPress permet ce comportement proprement, sans fragilité : le sanitize_callback d'une
 * option peut lire `get_option()` (encore à son ancienne valeur à ce stade, `update_option()` ne
 * l'ayant pas encore écrasée) pour retomber sur la valeur précédente d'UN SEUL champ, pendant que
 * WordPress écrit normalement le tableau complet retourné (donc tous les AUTRES champs de la même
 * soumission) en un seul `update_option()`, et `add_settings_error()` permet d'afficher un message
 * explicite sur l'écran de réglages (voir includes/admin/settings-page.php, `settings_errors()`).
 */
function gws_core_structure_presentation_max_length() {
  return apply_filters('gws_core_structure_presentation_max_length', 1500);
}

function gws_core_settings_defaults() {
  $defaults = array(
    'entity_name' => '',
    'primary_color' => '',
    'secondary_color' => '',
    'presentation' => '',
    'phone_display' => '',
    'public_email' => '',
    'whatsapp_number' => '',
    'website_url' => '',
    'address_line' => '',
    'address_line_2' => '',
    'postal_code' => '',
    'city' => '',
    'country' => '',
    'logo_id' => 0,
    'linkedin_url' => '',
    'facebook_url' => '',
    'instagram_url' => '',
    'youtube_url' => '',
    'tiktok_url' => '',
    'x_url' => '',
    'google_business_url' => '',
    'social_links' => '', // une URL par ligne ; libre à un projet de structurer davantage via le filtre ci-dessous
    'header_social_enabled' => '',
    'footer_social_enabled' => '1',
    'credit_enabled' => '1',
    'credit_url' => 'https://tagadavroom.fr/',
  );
  return apply_filters('gws_core_settings_defaults', $defaults);
}

/**
 * Schéma d'affichage de l'écran de réglages. Un module métier peut ajouter ses propres champs
 * de réglages globaux via ce filtre, sans toucher à ce fichier. Chaque champ porte une clé
 * 'group' utilisée uniquement pour le regroupement visuel de l'écran (Identité / Identité
 * visuelle / Présentation / Coordonnées / Réseaux sociaux / Crédit) — un champ ajouté par le
 * filtre sans cette clé atterrit simplement dans un groupe générique, voir
 * includes/admin/settings-page.php.
 */
function gws_core_settings_fields() {
  $fields = array(
    'entity_name' => array('group' => 'identity', 'label' => 'Nom de la structure', 'type' => 'text', 'description' => 'Nom affiché dans l’en-tête du site, les données structurées et les gabarits.'),
    'logo_id' => array('group' => 'identity', 'label' => 'Logo', 'type' => 'attachment_id', 'description' => 'Utilisé dans l’en-tête du site (si le thème le prend en charge) et dans les données structurées. Facultatif : sans logo, le nom de la structure s’affiche en texte.'),

    'primary_color' => array('group' => 'branding', 'label' => 'Couleur principale', 'type' => 'color', 'description' => 'Format hexadécimal (ex. #1d4ed8). Laisser vide pour utiliser la couleur GWS par défaut : ' . gws_core_default_primary_color() . '.'),
    'secondary_color' => array('group' => 'branding', 'label' => 'Couleur secondaire', 'type' => 'color', 'description' => 'Facultative. Laisser vide pour utiliser la couleur GWS par défaut : ' . gws_core_default_secondary_color() . '.'),

    'presentation' => array('group' => 'presentation', 'label' => 'Présentation de la structure', 'type' => 'textarea', 'max_length' => gws_core_structure_presentation_max_length(), 'description' => 'Quelques courts paragraphes présentant la structure — réutilisable sur le site, une future page de Catalogue ou un futur document commercial. ' . gws_core_structure_presentation_max_length() . ' caractères maximum.'),

    'phone_display' => array('group' => 'coordinates', 'label' => 'Téléphone', 'type' => 'text', 'description' => 'Format affiché sur le site.'),
    'public_email' => array('group' => 'coordinates', 'label' => 'E-mail public', 'type' => 'email', 'description' => ''),
    'whatsapp_number' => array('group' => 'coordinates', 'label' => 'Numéro WhatsApp', 'type' => 'text', 'description' => 'Format international obligatoire, avec l’indicatif pays (+ ou 00). Exemple France : +33 6 12 34 56 78 — ne pas ajouter de « (0) » après l’indicatif. Laisser vide pour ne rien afficher : aucun indicatif n’est jamais deviné automatiquement.'),
    'website_url' => array('group' => 'coordinates', 'label' => 'Site web', 'type' => 'url', 'description' => 'Adresse à afficher comme site officiel (utile si distincte de l’adresse de ce site WordPress).'),
    'address_line' => array('group' => 'coordinates', 'label' => 'Adresse', 'type' => 'text', 'description' => ''),
    'address_line_2' => array('group' => 'coordinates', 'label' => 'Complément d’adresse', 'type' => 'text', 'description' => 'Facultatif (bâtiment, lieu-dit...).'),
    'postal_code' => array('group' => 'coordinates', 'label' => 'Code postal', 'type' => 'text', 'description' => ''),
    'city' => array('group' => 'coordinates', 'label' => 'Ville', 'type' => 'text', 'description' => ''),
    'country' => array('group' => 'coordinates', 'label' => 'Pays', 'type' => 'text', 'description' => 'Facultatif — laisser vide pour un site strictement national si ce n’est pas utile.'),

    'linkedin_url' => array('group' => 'social', 'label' => 'LinkedIn', 'type' => 'url', 'description' => ''),
    'facebook_url' => array('group' => 'social', 'label' => 'Facebook', 'type' => 'url', 'description' => ''),
    'instagram_url' => array('group' => 'social', 'label' => 'Instagram', 'type' => 'url', 'description' => ''),
    'youtube_url' => array('group' => 'social', 'label' => 'YouTube', 'type' => 'url', 'description' => ''),
    'tiktok_url' => array('group' => 'social', 'label' => 'TikTok', 'type' => 'url', 'description' => ''),
    'x_url' => array('group' => 'social', 'label' => 'X', 'type' => 'url', 'description' => 'Anciennement Twitter.'),
    'google_business_url' => array('group' => 'social', 'label' => 'Fiche Google Business Profile', 'type' => 'url', 'description' => ''),
    'social_links' => array('group' => 'social', 'label' => 'Autres réseaux sociaux', 'type' => 'textarea', 'description' => 'Une URL par ligne, pour un réseau non listé ci-dessus (Threads, Bluesky, Pinterest...). Alimente le Schema mais pas le composant de pictogrammes sociaux du thème, réservé aux réseaux structurés ci-dessus.'),
    'header_social_enabled' => array('group' => 'social', 'label' => 'Réseaux sociaux — en-tête', 'type' => 'checkbox', 'checkbox_label' => 'Afficher les pictogrammes des réseaux sociaux dans l’en-tête', 'description' => 'Désactivé par défaut.'),
    'footer_social_enabled' => array('group' => 'social', 'label' => 'Réseaux sociaux — pied de page', 'type' => 'checkbox', 'checkbox_label' => 'Afficher les pictogrammes des réseaux sociaux dans le pied de page', 'description' => 'Activé par défaut ; sans effet si aucun réseau structuré n’est renseigné.'),

    'credit_enabled' => array('group' => 'credit', 'label' => 'Crédit de réalisation', 'type' => 'checkbox', 'checkbox_label' => 'Afficher « Site réalisé par Tagada Vroom » dans le pied de page', 'description' => ''),
    'credit_url' => array('group' => 'credit', 'label' => 'URL Tagada Vroom', 'type' => 'url', 'description' => 'Le crédit ci-dessus ne s’affiche que si cette adresse est renseignée.'),
  );
  return apply_filters('gws_core_settings_fields', $fields);
}

function gws_core_settings() {
  return wp_parse_args((array) get_option('gws_core_settings', array()), gws_core_settings_defaults());
}

/**
 * Point d'accès public utilisé par le thème (et tout module) pour lire un réglage.
 * Retourne toujours une chaîne (jamais d'erreur) même si la clé est inconnue.
 */
function gws_core_get_setting($key) {
  $settings = gws_core_settings();
  return isset($settings[$key]) ? $settings[$key] : '';
}

function gws_core_phone_href() {
  $digits = preg_replace('/\D+/', '', gws_core_get_setting('phone_display'));
  return $digits ? '+' . ltrim($digits, '0') : '';
}

/**
 * Logo de l'entité : un seul ID d'attachement, source unique pour le thème et le Schema.
 */
function gws_core_get_logo_id() {
  return (int) gws_core_get_setting('logo_id');
}

function gws_core_get_logo_url($size = 'full') {
  $id = gws_core_get_logo_id();
  if (!$id) return '';
  $url = wp_get_attachment_image_url($id, $size);
  return $url ?: '';
}

/**
 * Lien wa.me construit à partir du numéro renseigné — exige un format international explicite
 * (préfixé par + ou 00) et ne devine ou n'ajoute JAMAIS d'indicatif pays lui-même : un numéro
 * saisi sous forme nationale (ex. « 06 12 34 56 78 ») produirait un lien wa.me non fonctionnel,
 * donc renvoie une chaîne vide plutôt qu'un lien faux. Espaces, tirets et parenthèses dans la
 * saisie sont ignorés ; ne traite pas les notations du type « (0) » après l'indicatif — saisir
 * le numéro sans ce zéro entre parenthèses.
 */
function gws_core_whatsapp_url() {
  $number = trim((string) gws_core_get_setting('whatsapp_number'));
  if ($number === '') return '';
  $digits = preg_replace('/[^0-9+]/', '', $number);
  if (strpos($digits, '+') === 0) {
    $digits = substr($digits, 1);
  } elseif (strpos($digits, '00') === 0) {
    $digits = substr($digits, 2);
  } else {
    return ''; // pas de marqueur international reconnu ('+' ou '00') : indicatif jamais deviné
  }
  $digits = preg_replace('/\D+/', '', $digits);
  return $digits ? 'https://wa.me/' . $digits : '';
}

/**
 * Réseaux sociaux structurés réellement renseignés, sous la forme ['linkedin' => 'https://...'].
 * Un réseau vide n'apparaît simplement pas dans le tableau retourné. C'est cette liste (et
 * uniquement elle) que consomme le composant de pictogrammes sociaux du thème — 'social_links'
 * (champ libre) n'y figure jamais, voir gws_core_extra_social_urls() plus bas.
 */
function gws_core_social_links() {
  $map = array(
    'linkedin' => 'linkedin_url',
    'facebook' => 'facebook_url',
    'instagram' => 'instagram_url',
    'youtube' => 'youtube_url',
    'tiktok' => 'tiktok_url',
    'x' => 'x_url',
  );
  $links = array();
  foreach ($map as $network => $key) {
    $url = gws_core_get_setting($key);
    if ($url) $links[$network] = $url;
  }
  return $links;
}

function gws_core_google_business_url() {
  return gws_core_get_setting('google_business_url');
}

/**
 * Analyse le champ libre 'social_links' (une URL par ligne, extension générique pour un réseau
 * non listé ci-dessus) : lignes vides ignorées, chaque URL restante sanitizée puis validée —
 * jamais de ligne vide ou invalide dans le résultat. Volontairement absent de
 * gws_core_social_links() : les réseaux nommés y restent seuls, pour rester facilement
 * exploitables individuellement en front (icône dédiée, libellé...) ; ce champ libre n'alimente
 * que le Schema, via gws_core_schema_same_as() ci-dessous.
 */
function gws_core_extra_social_urls() {
  $raw = gws_core_get_setting('social_links');
  if (!$raw) return array();
  $urls = array();
  foreach (preg_split('/[\r\n]+/', $raw) as $line) {
    $line = trim($line);
    if ($line === '') continue;
    $url = esc_url_raw($line);
    if ($url && wp_http_validate_url($url)) $urls[] = $url;
  }
  return array_values(array_unique($urls));
}

/**
 * Liste dédupliquée des URLs à publier dans un `sameAs` Schema.org : réseaux structurés, fiche
 * Google Business Profile, et le champ d'extension libre 'social_links'. Ne contient jamais de
 * chaîne vide ou invalide.
 */
function gws_core_schema_same_as() {
  $urls = array_values(gws_core_social_links());
  $gbp = gws_core_google_business_url();
  if ($gbp) $urls[] = $gbp;
  $urls = array_merge($urls, gws_core_extra_social_urls());
  return array_values(array_unique(array_filter($urls)));
}

/**
 * Nom de la structure prêt à afficher, avec repli natif WordPress si le champ est vide (Lot 2C,
 * §11) — centralise une logique auparavant dupliquée à l'identique à 3 endroits du thème
 * (site-header.php, site-footer.php, inc/schema.php) : `gws_get_setting('entity_name') ?:
 * get_bloginfo('name')`. Les trois consommateurs du thème utilisent désormais ce helper via
 * l'enveloppe gws_structure_name() de inc/compat.php.
 */
function gws_core_structure_name() {
  $name = gws_core_get_setting('entity_name');
  return $name !== '' ? $name : get_bloginfo('name');
}

/**
 * Couleur principale EFFECTIVE de la structure (Lot 2C, §8) : la couleur choisie si elle est
 * renseignée et valide, sinon la couleur GWS par défaut — jamais de valeur vide en sortie, jamais
 * d'écriture de la valeur par défaut dans 'gws_core_settings' (le repli reste purement calculé à
 * la lecture). Voir gws_core_default_primary_color() ci-dessus pour le choix de cette couleur.
 */
function gws_core_get_primary_color() {
  $custom = gws_core_get_setting('primary_color');
  return $custom !== '' ? $custom : gws_core_default_primary_color();
}

function gws_core_get_secondary_color() {
  $custom = gws_core_get_setting('secondary_color');
  return $custom !== '' ? $custom : gws_core_default_secondary_color();
}

/**
 * Couleur de texte lisible sur un fond donné (Lot 2C, §10) — jamais stockée, toujours recalculée :
 * calcule la luminance relative sRGB (formule WCAG 2.x) de $hex_color et choisit le blanc ou le
 * noir selon le meilleur ratio de contraste obtenu. Volontairement binaire (noir OU blanc, jamais
 * une teinte intermédiaire) : suffisamment robuste pour un renderer (site, futur PDF) sans
 * construire un moteur de design system. $hex_color est attendu déjà au format '#rrggbb' (voir
 * gws_core_field_sanitize('color', ...)) ; toute valeur qui ne correspond pas à ce format retombe
 * sur '#000000' par sécurité (garde défensive, ne devrait pas se produire avec une valeur déjà
 * validée par ce fichier).
 */
function gws_core_contrast_color($hex_color) {
  $hex = ltrim((string) $hex_color, '#');
  if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) return '#000000';

  $channel_luminance = function ($channel_255) {
    $channel = $channel_255 / 255;
    return $channel <= 0.03928 ? $channel / 12.92 : pow(($channel + 0.055) / 1.055, 2.4);
  };

  $r = $channel_luminance(hexdec(substr($hex, 0, 2)));
  $g = $channel_luminance(hexdec(substr($hex, 2, 2)));
  $b = $channel_luminance(hexdec(substr($hex, 4, 2)));
  $luminance = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;

  $contrast_with_white = 1.05 / ($luminance + 0.05);
  $contrast_with_black = ($luminance + 0.05) / 0.05;

  return $contrast_with_white >= $contrast_with_black ? '#ffffff' : '#000000';
}

/**
 * Petite API métier interne consolidée (Lot 2C, §11 et §13) : un futur renderer (thème, PDF,
 * Catalogue) demande ces valeurs SANS jamais connaître un nom de meta, une clé d'option, ni la
 * logique de repli des couleurs — tout transite par cette seule fonction. Ne duplique aucun
 * getter existant, se contente de les assembler.
 */
function gws_core_structure_identity() {
  $primary = gws_core_get_primary_color();
  $secondary = gws_core_get_secondary_color();
  return array(
    'name' => gws_core_structure_name(),
    'logo_id' => gws_core_get_logo_id(),
    'logo_url' => gws_core_get_logo_url(),
    'primary_color' => $primary,
    'primary_color_contrast' => gws_core_contrast_color($primary),
    'secondary_color' => $secondary,
    'secondary_color_contrast' => gws_core_contrast_color($secondary),
    'presentation' => gws_core_get_setting('presentation'),
    'phone_display' => gws_core_get_setting('phone_display'),
    'phone_href' => gws_core_phone_href(),
    'public_email' => gws_core_get_setting('public_email'),
    'website_url' => gws_core_get_setting('website_url'),
    'address_line' => gws_core_get_setting('address_line'),
    'address_line_2' => gws_core_get_setting('address_line_2'),
    'postal_code' => gws_core_get_setting('postal_code'),
    'city' => gws_core_get_setting('city'),
    'country' => gws_core_get_setting('country'),
  );
}

function gws_core_credit_enabled() {
  return gws_core_get_setting('credit_enabled') === '1';
}

function gws_core_show_header_social() {
  return gws_core_get_setting('header_social_enabled') === '1';
}

function gws_core_show_footer_social() {
  return gws_core_get_setting('footer_social_enabled') === '1';
}

/**
 * Limite de longueur générique optionnelle (voir gws_core_structure_presentation_max_length()
 * pour l'usage actuel, 'presentation') — REJET CIBLÉ, jamais de troncature silencieuse (corrigé
 * après recette, même principe que gwseq_set_cheval_editorial() côté gws-equestrian, Lot 2A) :
 * un champ dont le contenu sanitisé dépasse sa limite N'EST PAS enregistré — sa valeur
 * PRÉCÉDEMMENT enregistrée est conservée à la place, et un message explicite est ajouté via
 * add_settings_error() (affiché par includes/admin/settings-page.php via settings_errors()).
 * Tous les AUTRES champs de la même soumission continuent d'être sanitisés et enregistrés
 * normalement : `update_option()` (déclenché par options.php après ce callback) écrit le tableau
 * complet retourné ici en une seule fois, champ rejeté compris — ce n'est donc jamais une
 * soumission partiellement bloquée, seul CE champ retombe sur sa valeur antérieure.
 *
 * $previous_settings : les réglages actuellement enregistrés (avec valeurs par défaut), lus AVANT
 * que options.php n'écrase l'option — get_option() reste fiable ici car sanitize_callback
 * s'exécute pendant le filtre 'sanitize_option_{$option}', strictement avant l'update_option() qui
 * suit dans options.php.
 */
function gws_core_sanitize_settings($input) {
  $input = is_array($input) ? $input : array();
  $previous_settings = gws_core_settings();
  $clean = array();
  $rejected = array(); // field_key => array('label' => ..., 'max_length' => ...)

  foreach (gws_core_settings_fields() as $key => $field) {
    $raw = $input[$key] ?? '';
    $value = gws_core_field_sanitize($field['type'] ?? 'text', $raw);

    if (isset($field['max_length']) && is_string($value)) {
      $max_length = (int) $field['max_length'];
      $length = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
      if ($length > $max_length) {
        $value = $previous_settings[$key] ?? '';
        $rejected[$key] = array('label' => $field['label'] ?? $key, 'max_length' => $max_length);
      }
    }

    $clean[$key] = $value;
  }

  if ($rejected && function_exists('add_settings_error')) {
    foreach ($rejected as $key => $info) {
      add_settings_error(
        'gws_core_settings',
        'gws_core_field_too_long_' . $key,
        sprintf(
          /* translators: 1: libellé du champ, 2: nombre maximum de caractères autorisés */
          __('%1$s : le contenu dépasse %2$d caractères — rien n’a été enregistré pour ce champ, la valeur précédente est conservée.', 'gws-core'),
          $info['label'],
          $info['max_length']
        ),
        'error'
      );
    }
  }

  return $clean;
}

function gws_core_register_settings() {
  register_setting('gws_core_settings_group', 'gws_core_settings', array(
    'type' => 'array',
    'sanitize_callback' => 'gws_core_sanitize_settings',
    'default' => gws_core_settings_defaults(),
  ));
}
add_action('admin_init', 'gws_core_register_settings');

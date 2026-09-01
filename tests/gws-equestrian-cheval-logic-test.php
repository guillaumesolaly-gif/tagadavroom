<?php
/**
 * Vérifie le socle métier de la fiche Cheval (Étape 4) : identité, robe/race avec "Autre",
 * commercialisation (statut/prix/libellé "sur demande"), Global Horse ID, colonnes
 * d'administration, et sécurité de la sauvegarde. Même méthodologie que les Étapes 2/3 : on
 * exerce les fonctions avec des données à la forme réelle de $_POST, et on vérifie le
 * comportement réel des hooks WordPress (pas seulement leur présence) — voir le CR de l'Étape 3
 * (bug du sélecteur de modèle) pour la raison de cette exigence.
 *
 * Ne fait pas partie des paquets livrés (gws-core.zip / gws-starter.zip).
 */

$failures = 0;
function gws_test_assert($condition, $label) {
  global $failures;
  if ($condition) { echo "OK   - $label\n"; }
  else { echo "FAIL - $label\n"; $failures++; }
}

/**
 * Détecte la cause exacte du bug runtime 0.14.4 ("resultsList=false" au moment de
 * l'initialisation JS, malgré search/codeInput trouvés) : un `<p>` ne peut structurellement JAMAIS
 * contenir un élément de contenu "flow" (spécification HTML5/WHATWG — `<ul>`, `<div>`, `<table>`...
 * liste exhaustive ci-dessous). Un VRAI navigateur ferme IMPLICITEMENT le `<p>` (et tout ce qui est
 * encore ouvert à l'intérieur) dès qu'il rencontre l'un de ces éléments, expulsant tout le reste du
 * contenu prévu hors de la structure attendue — exactement ce qui arrachait le `<ul class="gwseq-
 * race-field__results">` du composant hors de `.gwseq-race-field`. AUCUN parseur PHP disponible ici
 * (`DOMDocument`/libxml2) ne reproduit fidèlement cette règle précise (vérifié : libxml2 laisse le
 * `<ul>` imbriqué sans le fermer, contrairement à un vrai navigateur) — ce scanner reproduit donc
 * directement, à la main, la règle de fermeture implicite du `<p>` telle que définie par la
 * spécification, en suivant littéralement la pile d'éléments ouverts. C'est un test STRUCTUREL sur
 * le HTML source réellement produit par PHP, jamais un test d'exécution navigateur — voir le CR pour
 * les limites de ce qui reste à confirmer manuellement.
 */
function gws_test_assert_no_flow_content_inside_p($html, $label) {
  global $failures;
  // Liste exacte de la spécification WHATWG (élément qui, immédiatement après un <p> ouvert,
  // provoque sa fermeture implicite) : address, article, aside, blockquote, details, div, dl,
  // fieldset, figcaption, figure, footer, form, h1-h6, header, hgroup, hr, main, menu, nav, ol, p,
  // pre, section, table, ul.
  $autoclose_p_tags = array('address','article','aside','blockquote','details','div','dl','fieldset','figcaption','figure','footer','form','h1','h2','h3','h4','h5','h6','header','hgroup','hr','main','menu','nav','ol','p','pre','section','table','ul');
  $violation = null;
  if (preg_match_all('/<(\/)?([a-zA-Z][a-zA-Z0-9]*)\b[^>]*>/', $html, $matches, PREG_OFFSET_CAPTURE)) {
    $p_depth = 0;
    foreach ($matches[0] as $i => $full_match) {
      $is_closing = $matches[1][$i][0] === '/';
      $tag_name = strtolower($matches[2][$i][0]);
      if (!$is_closing && $tag_name === 'p') {
        $p_depth++;
        continue;
      }
      if ($is_closing && $tag_name === 'p') {
        $p_depth = max(0, $p_depth - 1);
        continue;
      }
      if ($p_depth > 0 && !$is_closing && in_array($tag_name, $autoclose_p_tags, true)) {
        $violation = $tag_name;
        break;
      }
    }
  }
  gws_test_assert($violation === null, $violation === null
    ? $label
    : "$label (ÉCHEC : <$violation> trouvé à l'intérieur d'un <p> encore ouvert — un vrai navigateur fermerait ce <p> implicitement avant, expulsant tout son contenu prévu restant)");
}

// --- Stubs WordPress minimaux ---
function wp_unslash($value) { return is_array($value) ? array_map('wp_unslash', $value) : $value; }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function sanitize_textarea_field($value) { return trim((string) $value); }
// FIDÈLE au comportement réel de sanitize_key() (WordPress core) : mise en minuscules AVANT le
// filtrage des caractères (jamais l'inverse) — voir gws-equestrian-pedigree-logic-test.php pour le
// détail du bug de stub que cet ordre évite (codes de race en MAJUSCULES du référentiel).
function sanitize_key($value) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value)); }
function esc_url_raw($value) { $value = trim((string) $value); return $value === '' ? '' : $value; }
function absint($value) { return abs((int) $value); }
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function esc_html($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function selected($a, $b) { return $a == $b ? ' selected' : ''; }
function checked($a, $b = true) { return $a == $b ? ' checked' : ''; }
function wp_parse_args($args, $defaults = array()) { return array_merge((array) $defaults, (array) $args); }
function wp_nonce_field($action, $field) { echo '<input type="hidden" name="' . esc_attr($field) . '" value="stub-nonce">'; }

// --- remove_accents() : natif WordPress, stub couvrant les caractères utilisés par les tests
// (suffisant pour valider le comportement, pas une table de translittération complète) ---
function remove_accents($text) {
  $map = array(
    'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ã' => 'a', 'å' => 'a',
    'À' => 'A', 'Â' => 'A', 'Ä' => 'A', 'Á' => 'A', 'Ã' => 'A', 'Å' => 'A',
    'ç' => 'c', 'Ç' => 'C',
    'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
    'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E',
    'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
    'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
    'ñ' => 'n', 'Ñ' => 'N',
    'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
    'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Ö' => 'O', 'Õ' => 'O',
    'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
    'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U',
    'ý' => 'y', 'ÿ' => 'y', 'Ý' => 'Y',
    'œ' => 'oe', 'Œ' => 'OE', 'æ' => 'ae', 'Æ' => 'AE',
  );
  return strtr($text, $map);
}

// i18n : chaîne telle quelle, mais on capture le text domain utilisé pour vérifier sa cohérence.
$GLOBALS['__gwseq_test_domains_used'] = array();
function __($text, $domain = 'default') { $GLOBALS['__gwseq_test_domains_used'][] = $domain; return $text; }
function _n($single, $plural, $number, $domain = 'default') { $GLOBALS['__gwseq_test_domains_used'][] = $domain; return $number == 1 ? $single : $plural; }
function esc_html__($text, $domain = 'default') { $GLOBALS['__gwseq_test_domains_used'][] = $domain; return esc_html($text); }
function esc_attr__($text, $domain = 'default') { $GLOBALS['__gwseq_test_domains_used'][] = $domain; return esc_attr($text); }
function esc_html_e($text, $domain = 'default') { echo esc_html__($text, $domain); }
function esc_attr_e($text, $domain = 'default') { echo esc_attr__($text, $domain); }

// --- register_post_meta : on capture les arguments réellement passés (show_in_rest en
// particulier) pour vérifier le Global Horse ID, plutôt que de supposer sa configuration ---
$GLOBALS['__gwseq_test_registered_meta'] = array();
function register_post_meta($object_type, $meta_key, $args = array()) {
  $GLOBALS['__gwseq_test_registered_meta'][$meta_key] = $args;
}
function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {}
function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {}

// --- Registres en mémoire (meta), comme dans les autres tests de ce dossier ---
$GLOBALS['__gwseq_test_meta'] = array();
function update_post_meta($post_id, $key, $value) { $GLOBALS['__gwseq_test_meta'][$post_id][$key] = $value; return true; }
function get_post_meta($post_id, $key, $single = false) { return $GLOBALS['__gwseq_test_meta'][$post_id][$key] ?? ''; }
function metadata_exists($type, $post_id, $key) {
  return array_key_exists($post_id, $GLOBALS['__gwseq_test_meta']) && array_key_exists($key, $GLOBALS['__gwseq_test_meta'][$post_id]);
}
function get_post_field($field, $post_id) { return $GLOBALS['__gwseq_test_post_fields'][$post_id][$field] ?? ''; }
$GLOBALS['__gwseq_test_terms'] = array();
function get_the_terms($post_id, $taxonomy) { return $GLOBALS['__gwseq_test_terms'][$post_id] ?? false; }
function wp_list_pluck($list, $field) {
  return array_map(function ($item) use ($field) { return is_object($item) ? $item->$field : $item[$field]; }, $list);
}

// --- Réglages globaux (devise réutilisée depuis l'Étape 3, jamais un réglage propre au cheval) ---
$GLOBALS['__gwseq_test_options'] = array();
function get_option($name, $default = false) {
  return array_key_exists($name, $GLOBALS['__gwseq_test_options']) ? $GLOBALS['__gwseq_test_options'][$name] : $default;
}

// --- Sécurité : registres pilotables par le test, même mécanisme que Prestation ---
$GLOBALS['__gwseq_test_security'] = array('nonce_valid' => true, 'can_edit' => true, 'is_revision' => false);
function wp_verify_nonce($nonce, $action) { return $GLOBALS['__gwseq_test_security']['nonce_valid']; }
function current_user_can($cap, $post_id = null) { return $GLOBALS['__gwseq_test_security']['can_edit']; }
function wp_is_post_revision($post_id) { return $GLOBALS['__gwseq_test_security']['is_revision']; }

// --- Environnement (Global Horse ID dev-only) et méta boxes : on capture ce qui est réellement
// enregistré, pilotable par le test ---
$GLOBALS['__gwseq_test_environment'] = 'production';
function wp_get_environment_type() { return $GLOBALS['__gwseq_test_environment']; }
$GLOBALS['__gwseq_test_meta_boxes'] = array();
function add_meta_box($id, $title, $callback, $post_type = null, $context = 'advanced', $priority = 'default') {
  $GLOBALS['__gwseq_test_meta_boxes'][] = $id;
}

// --- UUID v4 réel (comme la vraie fonction WordPress), pour vérifier le format produit par le
// code réel et l'unicité entre deux fiches — pas une valeur figée. ---
function wp_generate_uuid4() {
  $data = random_bytes(16);
  $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
  $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
  $hex = bin2hex($data);
  return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20, 12));
}

// --- Écran d'administration courant, pilotable, pour les assets et l'affordance de catégories ---
$GLOBALS['__gwseq_test_screen'] = null;
function get_current_screen() { return $GLOBALS['__gwseq_test_screen']; }
$GLOBALS['__gwseq_enqueued'] = array();
function wp_enqueue_script($handle, ...$rest) { $GLOBALS['__gwseq_enqueued'][] = $handle; }
function wp_enqueue_style($handle, ...$rest) { $GLOBALS['__gwseq_enqueued'][] = $handle; }
function wp_script_is($handle, $status = 'enqueued') { return in_array($handle, $GLOBALS['__gwseq_enqueued'], true); }
function wp_localize_script($handle, $object_name, $data) { $GLOBALS['__gwseq_test_localized'][$handle][$object_name] = $data; }

// --- Préférences utilisateur WordPress (Screen Options / ordre des meta boxes), pour le nettoyage
// de l'état hérité sur la boîte Identité — registre en mémoire par utilisateur, comme un vrai
// get_user_meta()/update_user_meta() ---
$GLOBALS['__gwseq_test_current_user_id'] = 1;
function get_current_user_id() { return $GLOBALS['__gwseq_test_current_user_id']; }
$GLOBALS['__gwseq_test_user_meta'] = array();
function get_user_meta($user_id, $key, $single = false) { return $GLOBALS['__gwseq_test_user_meta'][$user_id][$key] ?? ''; }
function update_user_meta($user_id, $key, $value) { $GLOBALS['__gwseq_test_user_meta'][$user_id][$key] = $value; return true; }

define('ABSPATH', __DIR__ . '/');
const GWSEQ_CPT_CHEVAL = 'gwseq_cheval';
const GWSEQ_TAX_CATEGORIE_CHEVAL = 'gwseq_categorie_cheval';
define('GWSEQ_MODULE_URL', 'https://example.test/wp-content/plugins/gws-core/modules/gws-equestrian/');
define('GWSEQ_MODULE_VERSION', 'test');
$repo_root = dirname(__DIR__);
$module_dir = $repo_root . '/wp-content/plugins/gws-core/modules/gws-equestrian/';
require $repo_root . '/wp-content/plugins/gws-core/includes/fields.php';
require $module_dir . 'includes/settings.php';
require $module_dir . 'includes/race-referentiel.php';
require $module_dir . 'includes/cheval-fields.php';
require $module_dir . 'includes/cheval-editor.php';
require $module_dir . 'includes/cheval-categories.php';

// =====================================================================================
// Identité — sexe, année de naissance, âge calculé, robe, race/stud-book, taille, éleveur,
// propriétaire, identifiants officiels
// =====================================================================================

// --- Sexe : valeur technique stable, jamais le libellé traduit ---
$i = gwseq_sanitize_cheval_identity_input(array('_gwseq_sexe' => 'gelding'));
gws_test_assert($i['sexe'] === 'gelding', 'Sexe valide (Hongre) : valeur technique stable conservée');
gws_test_assert(gwseq_cheval_sexe_options()['gelding'] === 'Hongre' && gwseq_cheval_sexe_options()['gelding'] !== 'gelding', 'Sexe : le libellé affiché ("Hongre") reste distinct de la valeur technique stockée ("gelding")');

// --- Sexe invalide (formulaire trafiqué) : jamais accepté tel quel ---
$i = gwseq_sanitize_cheval_identity_input(array('_gwseq_sexe' => 'licorne'));
gws_test_assert($i['sexe'] === '', 'Sexe invalide : rejeté, jamais stocké tel quel');

// --- Année de naissance : valeur plausible conservée ---
$i = gwseq_sanitize_cheval_identity_input(array('_gwseq_annee_naissance' => '2018'));
gws_test_assert($i['annee_naissance'] === 2018, 'Année de naissance valide : conservée en entier');

// --- Année de naissance : bornes raisonnables (§32) — ni trop ancienne, ni absurde ---
gws_test_assert(gwseq_sanitize_cheval_annee_naissance('1899') === '', 'Année de naissance : en dessous de la borne minimale documentée (1900) -> rejetée');
gws_test_assert(gwseq_sanitize_cheval_annee_naissance('1900') === 1900, 'Année de naissance : borne minimale documentée (1900) acceptée');
gws_test_assert(gwseq_sanitize_cheval_annee_naissance('99999') === '', 'Année de naissance : saisie absurde (99999) rejetée');
gws_test_assert(gwseq_sanitize_cheval_annee_naissance('abc') === '', 'Année de naissance : valeur non numérique rejetée, jamais d’erreur');
gws_test_assert(gwseq_sanitize_cheval_annee_naissance('') === '', 'Année de naissance : valeur vide conservée vide (champ optionnel)');
$max_annee = gwseq_cheval_annee_naissance_max();
gws_test_assert(gwseq_sanitize_cheval_annee_naissance((string) $max_annee) === $max_annee, 'Année de naissance : année courante + 1 acceptée (poulain attendu l’an prochain)');
gws_test_assert(gwseq_sanitize_cheval_annee_naissance((string) ($max_annee + 1)) === '', 'Année de naissance : au-delà de année courante + 1, rejetée');

// --- Âge calculé, jamais stocké : approximatif (calendaire), avec année de référence explicite
// pour un test déterministe ---
gws_test_assert(gwseq_cheval_age_from_birth_year(2018, 2026) === 8, 'Âge calculé : 2018 -> 8 ans en 2026');
gws_test_assert(gwseq_cheval_age_from_birth_year('', 2026) === '', 'Âge calculé : aucune année de naissance -> aucun âge (jamais 0 par défaut)');
gws_test_assert(gwseq_cheval_age_from_birth_year('abc', 2026) === '', 'Âge calculé : année invalide -> aucun âge, jamais d’erreur');
gws_test_assert(!array_key_exists('_gwseq_age', $GLOBALS['__gwseq_test_registered_meta']), 'Âge : jamais enregistré comme meta (donnée dérivée uniquement)');

// --- Libellé de l'âge (correction demandée en recette) : "1 an"/"7 ans", jamais "≈ 7 an(s)"
// ni de mention permanente d'approximation — exemples exacts fournis par le client ---
gws_test_assert(gwseq_cheval_age_label(gwseq_cheval_age_from_birth_year(2025, 2026)) === '1 an', 'Libellé âge : 2025 en 2026 -> "1 an" (exemple exact de la demande)');
gws_test_assert(gwseq_cheval_age_label(gwseq_cheval_age_from_birth_year(2019, 2026)) === '7 ans', 'Libellé âge : 2019 en 2026 -> "7 ans" (exemple exact de la demande)');
gws_test_assert(gwseq_cheval_age_label('') === '', 'Libellé âge : aucun âge -> aucun libellé');
gws_test_assert(strpos(gwseq_cheval_age_label(7), '≈') === false, 'Libellé âge : jamais le symbole "≈"');
gws_test_assert(strpos(gwseq_cheval_age_label(7), 'an(s)') === false, 'Libellé âge : jamais la forme non accordée "an(s)"');
gws_test_assert(strpos(gwseq_cheval_age_label(7), 'calendaire') === false && strpos(gwseq_cheval_age_label(7), 'approximatif') === false, 'Libellé âge : aucune mention permanente d’approximation');

// --- Robe : valeur standard, et "Autre" avec précision libre ---
$i = gwseq_sanitize_cheval_identity_input(array('_gwseq_robe' => 'bai_brun'));
gws_test_assert($i['robe'] === 'bai_brun', 'Robe standard (Bai brun) : valeur technique conservée');
$i = gwseq_sanitize_cheval_identity_input(array('_gwseq_robe' => 'autre', '_gwseq_robe_autre' => 'Aubère truité'));
gws_test_assert($i['robe'] === 'autre' && $i['robe_autre'] === 'Aubère truité', 'Robe "Autre" : précision libre conservée telle quelle');
gws_test_assert(gwseq_cheval_robe_label('autre', 'Aubère truité') === 'Aubère truité', 'Libellé robe "Autre" : la précision saisie est restituée telle quelle (jamais traduite)');
gws_test_assert(gwseq_cheval_robe_label('bai', '') === 'Bai', 'Libellé robe standard : résolu depuis la liste');
$i = gwseq_sanitize_cheval_identity_input(array('_gwseq_robe' => 'licorne'));
gws_test_assert($i['robe'] === '', 'Robe invalide : rejetée, jamais stockée telle quelle');

// --- Race / Stud-book / Appellation (référentiel Étape 8) : valeur standard résolue au code
// canonique EXACT du référentiel (toujours en MAJUSCULES, ex. "SF" — jamais "sf" ni un ancien
// identifiant "selle_francais"), et "Autre" en filet de sécurité ---
$i = gwseq_sanitize_cheval_identity_input(array('_gwseq_race' => 'sf'));
gws_test_assert($i['race'] === 'SF', 'Race standard (Selle Français) : résolue au code canonique "SF" quelle que soit la casse saisie');
$i = gwseq_sanitize_cheval_identity_input(array('_gwseq_race' => 'autre', '_gwseq_race_autre' => 'Camargue'));
gws_test_assert($i['race'] === 'autre' && $i['race_autre'] === 'Camargue', 'Race "Autre" : précision libre conservée telle quelle');
gws_test_assert(gwseq_cheval_race_label('autre', 'Camargue') === 'Camargue', 'Libellé race "Autre" : la précision saisie est restituée telle quelle');
$i = gwseq_sanitize_cheval_identity_input(array('_gwseq_race' => 'stud-book-invente'));
gws_test_assert($i['race'] === '', 'Race invalide/inconnue : rejetée, jamais stockée telle quelle, JAMAIS transformée automatiquement en "Autre"');

// --- Taille en centimètres, jamais en notation "1m68" ---
$i = gwseq_sanitize_cheval_identity_input(array('_gwseq_taille_cm' => '168'));
gws_test_assert($i['taille_cm'] === 168, 'Taille valide (168 cm) : conservée en entier, jamais en notation "1m68"');
gws_test_assert(gwseq_sanitize_cheval_taille('39') === '', 'Taille : en dessous de la borne minimale documentée (40 cm) -> rejetée');
gws_test_assert(gwseq_sanitize_cheval_taille('251') === '', 'Taille : au-dessus de la borne maximale documentée (250 cm) -> rejetée');
gws_test_assert(gwseq_sanitize_cheval_taille('1.68') === '', 'Taille : une valeur en mètres saisie par erreur (1.68, arrondie à 2 cm) est rejetée par la borne minimale — jamais confondue avec 168 cm ni convertie automatiquement');
gws_test_assert(gwseq_sanitize_cheval_taille('') === '', 'Taille : champ optionnel, vide conservé vide');

// --- Éleveur / Propriétaire : texte simple, optionnels ---
$i = gwseq_sanitize_cheval_identity_input(array('_gwseq_eleveur' => "Haras de l'Étoile", '_gwseq_proprietaire' => 'Julie Martin'));
gws_test_assert($i['eleveur'] === "Haras de l'Étoile", 'Éleveur : texte libre conservé');
gws_test_assert($i['proprietaire'] === 'Julie Martin', 'Propriétaire : texte libre conservé');
$i = gwseq_sanitize_cheval_identity_input(array());
gws_test_assert($i['eleveur'] === '' && $i['proprietaire'] === '', 'Éleveur/Propriétaire : absents du POST -> chaînes vides, jamais d’erreur');

// --- UELN / SIRE (§21) : simples identifiants texte, aucune validation de format imposée ---
$i = gwseq_sanitize_cheval_identity_input(array('_gwseq_ueln' => '250012345678901', '_gwseq_sire' => '05123456A'));
gws_test_assert($i['ueln'] === '250012345678901', 'UELN : identifiant conservé tel quel, en texte simple');
gws_test_assert($i['sire'] === '05123456A', 'SIRE : identifiant conservé tel quel, en texte simple');

// --- Données mal formées : jamais d’erreur, repli sûr ---
$i = gwseq_sanitize_cheval_identity_input('pas un tableau');
gws_test_assert($i['sexe'] === '' && $i['annee_naissance'] === '' && $i['eleveur'] === '', 'Identité : donnée mal formée (pas un tableau) -> repli sûr sur les valeurs par défaut');

// --- gwseq_set_cheval_identity() : fonction métier pure extraite de gwseq_save_cheval_meta()
// (préparation import IFCE, §7 de la demande — réutilisable hors formulaire, sans $_POST ni
// nonce) ; prend le même tableau à la forme $_POST que gwseq_sanitize_cheval_identity_input(),
// aucune deuxième forme de données inventée ---
$set_result = gwseq_set_cheval_identity(60, array(
  '_gwseq_sexe' => 'female',
  '_gwseq_annee_naissance' => '2019',
  '_gwseq_robe' => 'gris',
  '_gwseq_race' => 'sf',
  '_gwseq_taille_cm' => '168',
  '_gwseq_eleveur' => 'Haras de Félines',
  '_gwseq_sire' => '05123456A',
));
gws_test_assert($set_result === true, 'gwseq_set_cheval_identity() : l’enregistrement réussit et retourne true');
$identity_60 = gwseq_get_cheval_identity(60);
gws_test_assert(
  $identity_60['sexe'] === 'female' && $identity_60['annee_naissance'] === 2019 && $identity_60['robe'] === 'gris'
  && $identity_60['race'] === 'SF' && $identity_60['taille_cm'] === 168
  && $identity_60['eleveur'] === 'Haras de Félines' && $identity_60['sire'] === '05123456A',
  'gwseq_set_cheval_identity() : toutes les données sont bien persistées, relecture exacte via gwseq_get_cheval_identity()'
);
gws_test_assert(gwseq_set_cheval_identity(0, array('_gwseq_sexe' => 'mare')) === false, 'gwseq_set_cheval_identity() : un post_id invalide (0) est refusé, jamais d’écriture');
// Programmatique, sans $_POST ni nonce (même garantie que gwseq_set_horse_parent()/indices) :
// recherche déclarative directe sur le code source de la fonction elle-même.
$cheval_fields_source_for_identity_setter_check = file_get_contents($module_dir . 'includes/cheval-fields.php');
$identity_setter_source = substr($cheval_fields_source_for_identity_setter_check, strpos($cheval_fields_source_for_identity_setter_check, 'function gwseq_set_cheval_identity'));
$identity_setter_source = substr($identity_setter_source, 0, strpos($identity_setter_source, "\nfunction "));
gws_test_assert(strpos($identity_setter_source, '$_POST') === false, 'gwseq_set_cheval_identity() : ne lit jamais $_POST directement (réutilisable par un futur import IFCE/CSV/API, §7)');

// =====================================================================================
// Convention de présentation GWS Equestrian des noms de chevaux (correction post-recette de
// l'Étape 5, §12-15/§29) : majuscules, sans accents — jamais une transformation de la source
// =====================================================================================
gws_test_assert(gwseq_format_horse_name_display('Jamerose') === 'JAMEROSE', 'Présentation du nom : "Jamerose" -> "JAMEROSE"');
gws_test_assert(gwseq_format_horse_name_display('jamerose') === 'JAMEROSE', 'Présentation du nom : "jamerose" -> "JAMEROSE" (indifférent à la casse d’origine)');
gws_test_assert(gwseq_format_horse_name_display('JAMEROSE') === 'JAMEROSE', 'Présentation du nom : "JAMEROSE" reste "JAMEROSE"');
gws_test_assert(gwseq_format_horse_name_display('Étoile du Lys') === 'ETOILE DU LYS', 'Présentation du nom : accents supprimés et majuscules appliquées ("Étoile du Lys" -> "ETOILE DU LYS")');
gws_test_assert(gwseq_format_horse_name_display('étoile-du-lys') === 'ETOILE-DU-LYS', 'Présentation du nom : le trait d’union est conservé');
gws_test_assert(gwseq_format_horse_name_display("L'Arc de Triomphe") === "L'ARC DE TRIOMPHE", 'Présentation du nom : l’apostrophe est conservée');
gws_test_assert(gwseq_format_horse_name_display('Untouchable 27') === 'UNTOUCHABLE 27', 'Présentation du nom : les chiffres et espaces sont conservés');
gws_test_assert(gwseq_format_horse_name_display('') === '', 'Présentation du nom : chaîne vide -> chaîne vide, jamais d’erreur');

// --- Jamais une transformation destructive de la source : la fonction ne fait que calculer une
// représentation, post_title (et le nom d'un ascendant externe côté Pedigree) restent
// enregistrés exactement tels que saisis — vérifié directement dans le code source : cette
// fonction ne doit jamais être appelée par une fonction de sanitation ---
$cheval_fields_source_full = file_get_contents($module_dir . 'includes/cheval-fields.php');
$identity_sanitize_block = substr($cheval_fields_source_full, strpos($cheval_fields_source_full, 'function gwseq_sanitize_cheval_identity_input'), 2000);
gws_test_assert(strpos($identity_sanitize_block, 'gwseq_format_horse_name_display') === false, 'Présentation du nom : jamais utilisée dans la sanitation de l’identité (post_title reste la source, jamais transformée à l’enregistrement)');

// =====================================================================================
// Source de vérité unique (§2-3) : ni Nom ni Photo principale ne créent de meta parallèle
// =====================================================================================
$cheval_fields_source = file_get_contents($module_dir . 'includes/cheval-fields.php');
foreach (array('_gwseq_nom_cheval', '_gwseq_photo_principale', '_gwseq_nom', '_gwseq_titre') as $forbidden_meta_key) {
  gws_test_assert(
    strpos($cheval_fields_source, "'" . $forbidden_meta_key . "'") === false,
    "Source de vérité : aucune meta '$forbidden_meta_key' créée (post_title/image à la une restent l'unique source de vérité)"
  );
}

// --- Aucune logique ne dépend d’un nom de stud-book précis (§4) : depuis le référentiel de
// l'Étape 8, la liste elle-même n'existe plus dans ce fichier (voir includes/race-referentiel.php)
// — l'ancien identifiant technique "selle_francais" ne doit donc plus apparaître nulle part ici ---
gws_test_assert(strpos($cheval_fields_source, 'selle_francais') === false, 'Race/Stud-book : aucune logique du fichier ne dépend de l’ancien identifiant technique "selle_francais" (référentiel désormais dans race-referentiel.php)');

// =====================================================================================
// Catégories de chevaux : interface à cases à cocher (native), affordance de création rapide
// masquée depuis la fiche
// =====================================================================================
$taxonomies_source = file_get_contents($module_dir . 'includes/taxonomies.php');
gws_test_assert(strpos($taxonomies_source, "'meta_box_cb' => 'post_categories_meta_box'") !== false, 'Catégories : interface à cases à cocher activée nativement (meta_box_cb = post_categories_meta_box, aucun rendu personnalisé)');
gws_test_assert(strpos($taxonomies_source, "'hierarchical' => false") !== false, 'Catégories : taxonomie non hiérarchique conservée (compatible multi-valeurs)');

// --- Masquage réel de l'affordance "+ Ajouter" : uniquement sur l'écran d'une fiche cheval ---
$GLOBALS['__gwseq_test_screen'] = (object) array('post_type' => GWSEQ_CPT_CHEVAL);
ob_start();
gwseq_hide_cheval_category_quick_add();
$hide_css = ob_get_clean();
gws_test_assert(strpos($hide_css, 'gwseq_categorie_cheval-adder') !== false && strpos($hide_css, 'display: none') !== false, 'Catégories : le style masquant l’affordance de création rapide est réellement produit sur la fiche cheval');

$GLOBALS['__gwseq_test_screen'] = (object) array('post_type' => 'page');
ob_start();
gwseq_hide_cheval_category_quick_add();
gws_test_assert(ob_get_clean() === '', 'Catégories : aucun style injecté sur un autre écran que la fiche cheval');

// =====================================================================================
// Commercialisation : statut indépendant des catégories, mode de prix, prix fixe/fourchette/sur
// demande, devise globale réutilisée
// =====================================================================================

// --- Statut commercial ---
foreach (array('not_offered', 'for_sale', 'reserved', 'sold') as $statut) {
  $c = gwseq_sanitize_cheval_commercial_input(array('_gwseq_statut_commercial' => $statut));
  gws_test_assert($c['statut_commercial'] === $statut, "Statut commercial : \"$statut\" conservé tel quel");
}
$c = gwseq_sanitize_cheval_commercial_input(array('_gwseq_statut_commercial' => 'valeur-invalide'));
gws_test_assert($c['statut_commercial'] === 'not_offered', 'Statut commercial invalide : repli sûr sur "not_offered", jamais une valeur arbitraire');
$c = gwseq_sanitize_cheval_commercial_input(array());
gws_test_assert($c['statut_commercial'] === 'not_offered', 'Statut commercial absent du POST : repli sur "not_offered" (jamais "à vendre" par défaut)');

// --- Prix fixe ---
$c = gwseq_sanitize_cheval_commercial_input(array('_gwseq_prix_mode' => 'fixed', '_gwseq_prix_fixe' => '25000'));
gws_test_assert($c['prix_mode'] === 'fixed' && $c['prix_fixe'] === 25000.0, 'Prix fixe : mode et montant conservés');

// --- Valeur 0 jamais confondue avec une absence de prix ---
$c = gwseq_sanitize_cheval_commercial_input(array('_gwseq_prix_mode' => 'fixed', '_gwseq_prix_fixe' => '0'));
gws_test_assert($c['prix_fixe'] === 0.0, 'Prix fixe : la valeur 0 explicitement saisie est conservée comme un vrai prix, jamais comme une absence de valeur');
$c = gwseq_sanitize_cheval_commercial_input(array('_gwseq_prix_mode' => 'fixed'));
gws_test_assert($c['prix_fixe'] === '', 'Prix fixe : absent du POST -> chaîne vide, jamais 0 par défaut');

// --- Fourchette ---
$c = gwseq_sanitize_cheval_commercial_input(array('_gwseq_prix_mode' => 'range', '_gwseq_prix_min' => '20000', '_gwseq_prix_max' => '30000'));
gws_test_assert($c['prix_mode'] === 'range' && $c['prix_min'] === 20000.0 && $c['prix_max'] === 30000.0, 'Fourchette : mode et deux bornes conservées');

// --- Sur demande : libellé personnalisé, aucun prix inventé ---
$c = gwseq_sanitize_cheval_commercial_input(array('_gwseq_prix_mode' => 'on_request', '_gwseq_prix_demande_libelle' => 'Nous contacter'));
gws_test_assert($c['prix_mode'] === 'on_request' && $c['prix_demande_libelle'] === 'Nous contacter', 'Sur demande : libellé personnalisé conservé');
gws_test_assert($c['prix_fixe'] === '' && $c['prix_min'] === '' && $c['prix_max'] === '', 'Sur demande : aucun prix numérique inventé pour les autres modes');

// --- Mode de prix invalide : repli sûr ---
$c = gwseq_sanitize_cheval_commercial_input(array('_gwseq_prix_mode' => 'gratuit'));
gws_test_assert($c['prix_mode'] === 'fixed', 'Mode de prix invalide : repli sur "fixed", jamais un mode arbitraire');

// --- Donnée mal formée ---
$c = gwseq_sanitize_cheval_commercial_input('pas un tableau');
gws_test_assert($c['statut_commercial'] === 'not_offered' && $c['prix_mode'] === 'fixed', 'Commercial : donnée mal formée -> repli sûr sur les valeurs par défaut');

// --- Libellé "sur demande" : jamais initialisé / personnalisé / volontairement vidé ---
gws_test_assert(gwseq_get_cheval_prix_demande_libelle(500) === 'Prix sur demande', 'Libellé sur demande : jamais initialisé -> valeur par défaut du logiciel');
$GLOBALS['__gwseq_test_meta'][501] = array('_gwseq_prix_demande_libelle' => 'Contactez-nous au haras');
gws_test_assert(gwseq_get_cheval_prix_demande_libelle(501) === 'Contactez-nous au haras', 'Libellé sur demande : personnalisé conservé tel quel');
$GLOBALS['__gwseq_test_meta'][502] = array('_gwseq_prix_demande_libelle' => '');
gws_test_assert(gwseq_get_cheval_prix_demande_libelle(502) === '', 'Libellé sur demande : volontairement vidé, distinct de "jamais initialisé" (jamais de fallback)');

// --- Résumé de prix (fonction pure, réutilisable admin/futur front/API) ---
gws_test_assert(gwseq_cheval_price_summary(array('prix_mode' => 'fixed', 'prix_fixe' => 25000.0), 'EUR') === '25 000 €', 'Résumé : prix fixe formaté avec devise');
gws_test_assert(gwseq_cheval_price_summary(array('prix_mode' => 'fixed', 'prix_fixe' => ''), 'EUR') === '', 'Résumé : prix fixe non renseigné -> résumé vide, jamais "0 €"');
gws_test_assert(gwseq_cheval_price_summary(array('prix_mode' => 'range', 'prix_min' => 20000.0, 'prix_max' => 30000.0), 'EUR') === '20 000 – 30 000 €', 'Résumé : fourchette complète formatée');
gws_test_assert(gwseq_cheval_price_summary(array('prix_mode' => 'range', 'prix_min' => 20000.0, 'prix_max' => ''), 'EUR') === '20 000 €', 'Résumé : fourchette partiellement renseignée -> seule la borne connue est affichée');
gws_test_assert(gwseq_cheval_price_summary(array('prix_mode' => 'range', 'prix_min' => '', 'prix_max' => ''), 'EUR') === '', 'Résumé : fourchette totalement vide -> résumé vide');
gws_test_assert(gwseq_cheval_price_summary(array('prix_mode' => 'on_request', 'prix_demande_libelle' => 'Nous contacter'), 'EUR') === 'Nous contacter', 'Résumé : mode "Sur demande" -> libellé affiché tel quel');
gws_test_assert(gwseq_cheval_price_summary(array('prix_mode' => 'on_request', 'prix_demande_libelle' => ''), 'EUR') === '', 'Résumé : "Sur demande" volontairement vide -> rien affiché');

// --- Devise globale réutilisée (Étape 3), jamais un second réglage propre au cheval ---
gws_test_assert(gwseq_cheval_price_summary(array('prix_mode' => 'fixed', 'prix_fixe' => 100.0), 'GBP') === '100 £', 'Résumé : devise GBP (globale) appliquée, symbole £ utilisé');
gws_test_assert(strpos($cheval_fields_source, 'gwseq_settings') === false, 'Devise : aucun réglage propre au cheval — la fonction lit uniquement le code devise qu’on lui transmet explicitement (fourni par le réglage global de l’Étape 3)');
gws_test_assert(strpos($cheval_fields_source, '€') === false, 'Aucun symbole € codé en dur dans cheval-fields.php : la devise passe toujours par gwseq_currency_symbol()');

// --- Aucune mention HT/TTC pour le prix du cheval (§14 : limitation documentée, pas de moteur
// fiscal inventé pour ce champ) ---
gws_test_assert(strpos($cheval_fields_source, "'HT'") === false && strpos($cheval_fields_source, "'TTC'") === false, 'Prix du cheval : aucune mention HT/TTC codée (limitation volontaire, voir le CR de livraison)');

// --- Statut/Prix : cohérence — le prix reste toujours visible/enregistré indépendamment du statut
// (aucune donnée effacée par un changement de statut), avec un texte d’aide explicite ---
ob_start();
gwseq_render_cheval_commercialisation_box((object) array('ID' => 900));
$commercial_box_html = ob_get_clean();
gws_test_assert(strpos($commercial_box_html, 'name="_gwseq_prix_fixe"') !== false, 'Cohérence Statut/Prix : le champ de prix fixe reste toujours présent dans le formulaire, quel que soit le statut choisi');
gws_test_assert(strpos($commercial_box_html, 'reste enregistré') !== false, 'Cohérence Statut/Prix : un texte d’aide explicite rappelle que le prix reste enregistré même si le statut change');

// =====================================================================================
// Global Horse ID : génération, idempotence, indépendance du post_id/nom/slug, non exposé
// =====================================================================================

// --- Non exposé en REST (§19) ---
gwseq_register_cheval_meta();
gws_test_assert(($GLOBALS['__gwseq_test_registered_meta']['_gwseq_global_id']['show_in_rest'] ?? null) === false, 'Global Horse ID : jamais exposé en REST (show_in_rest => false)');

// --- Jamais un identifiant utilisateur : la fonction de sanitation de l'identité ne le mentionne
// jamais, et gwseq_save_cheval_meta() ne l'écrit jamais depuis $_POST (seule la fonction
// dédiée, idempotente, peut l'assigner) ---
$identity_test = gwseq_sanitize_cheval_identity_input(array('_gwseq_global_id' => 'valeur-fournie-par-un-attaquant'));
gws_test_assert(!array_key_exists('global_id', $identity_test), 'Global Horse ID : ne peut jamais être fourni via le formulaire d’identité (absent de la sanitation)');
$save_function_block = substr($cheval_fields_source, strpos($cheval_fields_source, 'function gwseq_save_cheval_meta'), strpos($cheval_fields_source, "add_action('save_post_' . GWSEQ_CPT_CHEVAL, 'gwseq_save_cheval_meta')") - strpos($cheval_fields_source, 'function gwseq_save_cheval_meta'));
gws_test_assert(strpos($save_function_block, '_gwseq_global_id') === false, 'Global Horse ID : jamais écrit par la sauvegarde du formulaire (aucune saisie utilisateur possible), uniquement par gwseq_assign_cheval_global_id()');

// --- Absent avant tout enregistrement réel ---
gws_test_assert(gwseq_get_cheval_global_id(600) === '', 'Global Horse ID : absent tant qu’aucun enregistrement réel n’a eu lieu');

// --- Jamais assigné sur un auto-draft ---
$auto_draft = (object) array('post_type' => GWSEQ_CPT_CHEVAL, 'post_status' => 'auto-draft');
gwseq_assign_cheval_global_id(601, $auto_draft, false);
gws_test_assert(gwseq_get_cheval_global_id(601) === '', 'Global Horse ID : jamais assigné sur un auto-draft (pas encore un enregistrement réel)');

// --- Jamais assigné sur un autre post type ---
$other_post = (object) array('post_type' => 'post', 'post_status' => 'publish');
gwseq_assign_cheval_global_id(602, $other_post, false);
gws_test_assert(gwseq_get_cheval_global_id(602) === '', 'Global Horse ID : jamais assigné à un post d’un autre type');

// --- Jamais assigné pendant une révision ---
$GLOBALS['__gwseq_test_security']['is_revision'] = true;
$revision_post = (object) array('post_type' => GWSEQ_CPT_CHEVAL, 'post_status' => 'publish');
gwseq_assign_cheval_global_id(603, $revision_post, false);
gws_test_assert(gwseq_get_cheval_global_id(603) === '', 'Global Horse ID : jamais assigné pendant l’enregistrement d’une révision');
$GLOBALS['__gwseq_test_security']['is_revision'] = false;

// --- Premier enregistrement réel : UUID v4 généré, format valide ---
$real_post = (object) array('post_type' => GWSEQ_CPT_CHEVAL, 'post_status' => 'publish');
gwseq_assign_cheval_global_id(604, $real_post, true);
$global_id = gwseq_get_cheval_global_id(604);
gws_test_assert($global_id !== '', 'Global Horse ID : généré au premier enregistrement réel');
gws_test_assert((bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $global_id), 'Global Horse ID : format UUID v4 valide');

// --- Jamais régénéré (idempotence) : un deuxième enregistrement réel conserve le même
// identifiant — simule un changement de nom/slug, qui ne touche jamais cette meta ---
gwseq_assign_cheval_global_id(604, $real_post, true);
gws_test_assert(gwseq_get_cheval_global_id(604) === $global_id, 'Global Horse ID : jamais régénéré lors d’un enregistrement ultérieur (nom/slug modifié) — même identifiant conservé');

// --- Indépendance du post_id : deux fiches différentes reçoivent deux identifiants distincts,
// aucune dérivation depuis le post_id ---
$another_post = (object) array('post_type' => GWSEQ_CPT_CHEVAL, 'post_status' => 'publish');
gwseq_assign_cheval_global_id(605, $another_post, true);
gws_test_assert(gwseq_get_cheval_global_id(605) !== $global_id, 'Global Horse ID : deux fiches différentes reçoivent deux identifiants distincts');

// --- Meta box de vérification : jamais enregistrée hors environnement local/développement ---
$GLOBALS['__gwseq_test_environment'] = 'production';
$GLOBALS['__gwseq_test_meta_boxes'] = array();
gwseq_add_cheval_meta_boxes();
gws_test_assert(!in_array('gwseq-cheval-global-id-dev', $GLOBALS['__gwseq_test_meta_boxes'], true), 'Global Horse ID : la boîte de vérification n’est jamais enregistrée en production');

$GLOBALS['__gwseq_test_environment'] = 'local';
$GLOBALS['__gwseq_test_meta_boxes'] = array();
gwseq_add_cheval_meta_boxes();
gws_test_assert(in_array('gwseq-cheval-global-id-dev', $GLOBALS['__gwseq_test_meta_boxes'], true), 'Global Horse ID : la boîte de vérification est enregistrée en environnement local');
$GLOBALS['__gwseq_test_environment'] = 'production';

// --- Rendu réel de la boîte de vérification (affiche bien l’identifiant, en lecture seule) ---
ob_start();
gwseq_render_cheval_global_id_dev_box((object) array('ID' => 604));
$dev_box_html = ob_get_clean();
gws_test_assert(strpos($dev_box_html, $global_id) !== false, 'Global Horse ID : la boîte de vérification affiche réellement l’identifiant de la fiche');
gws_test_assert(strpos($dev_box_html, 'readonly') !== false, 'Global Horse ID : le champ de vérification est en lecture seule (jamais modifiable manuellement)');

// =====================================================================================
// Rendu des meta boxes Identité / Commercialisation : champs réellement présents
// =====================================================================================
ob_start();
gwseq_render_cheval_identite_box((object) array('ID' => 700));
$identite_html = ob_get_clean();
foreach (array('_gwseq_sexe', '_gwseq_annee_naissance', '_gwseq_robe', '_gwseq_race', '_gwseq_taille_cm', '_gwseq_eleveur', '_gwseq_proprietaire', '_gwseq_ueln', '_gwseq_sire') as $field_name) {
  gws_test_assert(strpos($identite_html, 'name="' . $field_name . '"') !== false, "Meta box Identité : le champ $field_name est réellement rendu");
}
// --- Correctif runtime 0.14.4 : le champ Race (qui imprime un <ul> de résultats) n'est plus jamais
// enveloppé dans un <p>, ce qui provoquerait sa fermeture implicite par un vrai navigateur avant le
// <ul> — voir gws_test_assert_no_flow_content_inside_p() ci-dessus pour la règle HTML5 exacte ---
gws_test_assert_no_flow_content_inside_p($identite_html, 'Meta box Identité : aucun <p> encore ouvert ne contient d’élément de contenu "flow" (le champ Race n’est plus enveloppé dans un <p>, cause exacte du bug runtime "resultsList=false")');

// --- Rendu réel de l'âge sur une fiche avec année de naissance renseignée : format correct,
// aucune mention interdite, aide discrète présente uniquement en attribut title (pas de texte
// permanent visible qui surchargerait l'interface) ---
$GLOBALS['__gwseq_test_meta'][701] = array('_gwseq_annee_naissance' => (int) gmdate('Y') - 7);
ob_start();
gwseq_render_cheval_identite_box((object) array('ID' => 701));
$identite_html_with_age = ob_get_clean();
gws_test_assert(strpos($identite_html_with_age, '7 ans') !== false, 'Rendu réel : l’âge s’affiche correctement accordé au pluriel ("7 ans")');
gws_test_assert(strpos($identite_html_with_age, '≈') === false, 'Rendu réel : plus aucun symbole "≈" dans la fiche');
gws_test_assert(strpos($identite_html_with_age, 'an(s)') === false, 'Rendu réel : plus aucune forme non accordée "an(s)" dans la fiche');
gws_test_assert(strpos($identite_html_with_age, 'calendaire approximatif') === false, 'Rendu réel : plus de mention permanente d’approximation dans la fiche');
gws_test_assert(strpos($identite_html_with_age, 'title="') !== false && strpos($identite_html_with_age, 'convention équine') !== false, 'Rendu réel : l’explication de la convention équine reste disponible, mais en aide discrète (attribut title), pas en texte permanent visible');

// =====================================================================================
// Éditeur : désactivation de l'éditeur par blocs, espace réservé du titre — comportement réel
// des filtres, pas seulement leur présence
// =====================================================================================
$GLOBALS['__gwseq_test_filters'] = array();
function add_filter_capture($hook, $callback) { $GLOBALS['__gwseq_test_filters'][$hook][] = $callback; }
// Note : add_filter() a été défini plus haut comme no-op pour ne pas perturber l'enregistrement
// des hooks au require ; on invoque directement les fonctions de callback ici pour vérifier leur
// vrai comportement, comme pour gwseq_prestation_title_placeholder() dans les tests de l'Étape 3.
gws_test_assert(gwseq_disable_block_editor_for_cheval(true, GWSEQ_CPT_CHEVAL) === false, 'Éditeur par blocs : désactivé pour gwseq_cheval');
gws_test_assert(gwseq_disable_block_editor_for_cheval(true, 'post') === true, 'Éditeur par blocs : inchangé pour les Articles (pas de désactivation globale)');
gws_test_assert(gwseq_disable_block_editor_for_cheval(true, 'page') === true, 'Éditeur par blocs : inchangé pour un autre post type');

$new_cheval = (object) array('post_type' => GWSEQ_CPT_CHEVAL);
gws_test_assert(gwseq_cheval_title_placeholder('Ajouter un titre', $new_cheval) === 'Nom du cheval', 'UX : l’espace réservé du titre est explicite ("Nom du cheval")');
$page_post = (object) array('post_type' => 'page');
gws_test_assert(gwseq_cheval_title_placeholder('Ajouter un titre', $page_post) === 'Ajouter un titre', 'UX : l’espace réservé du titre est inchangé pour un autre post type');

// --- post-types.php : plus de support 'editor', 'page-attributes' présent, labels Photo
// principale ajoutés (vérification directe du code source, cohérente avec l'arbitrage Gutenberg
// ci-dessus) ---
$post_types_source = file_get_contents($module_dir . 'includes/post-types.php');
$cheval_registration = substr($post_types_source, strpos($post_types_source, 'GWSEQ_CPT_CHEVAL, array('));
gws_test_assert(strpos($cheval_registration, "'title', 'thumbnail', 'page-attributes'") !== false, 'Post type Cheval : supports title/thumbnail/page-attributes, éditeur retiré (aucun contenu éditorial à cette étape)');
gws_test_assert(strpos($cheval_registration, "'featured_image' => __('Photo principale'") !== false, 'Post type Cheval : libellé "Photo principale" appliqué à l’image à la une native (aucune meta parallèle)');

// =====================================================================================
// Colonnes d'administration : Catégories / Statut commercial / Prix / Ordre
// =====================================================================================
$columns = gwseq_cheval_admin_columns(array('cb' => '<input type="checkbox">', 'title' => 'Titre', 'date' => 'Date'));
gws_test_assert(array_key_exists('gwseq_categories', $columns) && array_key_exists('gwseq_statut', $columns) && array_key_exists('gwseq_prix', $columns) && array_key_exists('gwseq_ordre', $columns), 'Colonnes admin : Catégories/Statut commercial/Prix/Ordre toutes ajoutées');

$GLOBALS['__gwseq_test_terms'][800] = array((object) array('name' => 'Chevaux à vendre'), (object) array('name' => 'Chevaux de sport'));
ob_start();
gwseq_cheval_admin_column_content('gwseq_categories', 800);
gws_test_assert(ob_get_clean() === 'Chevaux à vendre, Chevaux de sport', 'Colonne Catégories : plusieurs catégories affichées, séparées par une virgule');

ob_start();
gwseq_cheval_admin_column_content('gwseq_categories', 801);
gws_test_assert(ob_get_clean() === '—', 'Colonne Catégories : aucune catégorie -> tiret, jamais d’erreur');

$GLOBALS['__gwseq_test_meta'][802] = array('_gwseq_statut_commercial' => 'for_sale', '_gwseq_prix_mode' => 'fixed', '_gwseq_prix_fixe' => 15000.0);
ob_start();
gwseq_cheval_admin_column_content('gwseq_statut', 802);
gws_test_assert(ob_get_clean() === 'À vendre', 'Colonne Statut commercial : libellé résolu depuis la valeur technique');

ob_start();
gwseq_cheval_admin_column_content('gwseq_prix', 802);
gws_test_assert(ob_get_clean() === '15 000 €', 'Colonne Prix : résumé formaté avec la devise par défaut (EUR)');

$GLOBALS['__gwseq_test_post_fields'][802]['menu_order'] = 30;
ob_start();
gwseq_cheval_admin_column_content('gwseq_ordre', 802);
gws_test_assert(ob_get_clean() === '30', 'Colonne Ordre : menu_order natif affiché');

// =====================================================================================
// Assets : uniquement sur l'écran d'édition d'une fiche cheval
// =====================================================================================
$GLOBALS['__gwseq_test_screen'] = (object) array('post_type' => 'page');
$GLOBALS['__gwseq_enqueued'] = array();
gwseq_enqueue_cheval_admin_assets('post.php');
gws_test_assert(empty($GLOBALS['__gwseq_enqueued']), 'Assets Cheval : jamais chargés sur l’écran d’un autre post type');

$GLOBALS['__gwseq_test_screen'] = (object) array('post_type' => GWSEQ_CPT_CHEVAL);
$GLOBALS['__gwseq_enqueued'] = array();
gwseq_enqueue_cheval_admin_assets('post-new.php');
gws_test_assert(in_array('gwseq-cheval-admin', $GLOBALS['__gwseq_enqueued'], true), 'Assets Cheval : chargés sur l’écran de création d’une fiche cheval');

$GLOBALS['__gwseq_enqueued'] = array();
gwseq_enqueue_cheval_admin_assets('edit.php');
gws_test_assert(empty($GLOBALS['__gwseq_enqueued']), 'Assets Cheval : jamais chargés sur un écran sans rapport (edit.php)');

// =====================================================================================
// Internationalisation : text domain cohérent, contenu utilisateur jamais traduit
// =====================================================================================
$GLOBALS['__gwseq_test_domains_used'] = array();
gwseq_cheval_sexe_options();
gwseq_cheval_robe_options();
gwseq_race_referentiel_display_label('SF');
gwseq_cheval_statut_commercial_options();
gwseq_cheval_prix_mode_options();
$other_domains = array_diff(array_unique($GLOBALS['__gwseq_test_domains_used']), array('gws-core'));
gws_test_assert(count($GLOBALS['__gwseq_test_domains_used']) > 0, 'i18n : les fonctions de traduction WordPress sont réellement appelées');
gws_test_assert(empty($other_domains), 'i18n : text domain cohérent "gws-core" sur tous les appels rencontrés');

foreach (array_merge(glob($module_dir . 'includes/cheval-*.php'), array($module_dir . 'includes/race-referentiel.php')) as $file) {
  preg_match_all('/\b(?:__|_e|esc_html__|esc_html_e|esc_attr__|esc_attr_e)\(\s*[\'"](?:[^\'"\\\\]|\\\\.)*[\'"]\s*,\s*[\'"]([^\'"]*)[\'"]\s*\)/', file_get_contents($file), $domain_matches);
  $mismatched = array_diff(array_unique($domain_matches[1]), array('gws-core'));
  gws_test_assert(empty($mismatched), basename($file) . ' : aucun appel de traduction n’utilise un text domain autre que "gws-core" (trouvé : ' . implode(', ', $mismatched) . ')');
}

// --- Contenu utilisateur jamais traduit (éleveur, propriétaire, précisions "Autre", libellé
// "sur demande" personnalisé, UELN, SIRE) ---
$GLOBALS['__gwseq_test_meta'][900] = array('_gwseq_prix_demande_libelle' => 'Appelez le haras au 06 00 00 00 00');
gws_test_assert(gwseq_get_cheval_prix_demande_libelle(900) === 'Appelez le haras au 06 00 00 00 00', 'i18n : le libellé "sur demande" personnalisé (donnée du site) est restitué strictement tel quel');

// =====================================================================================
// Sécurité de la sauvegarde : nonce invalide / permissions insuffisantes / autosave / révision —
// chemin réel via $_POST et gwseq_save_cheval_meta()
// =====================================================================================
function gws_test_reset_security() {
  $GLOBALS['__gwseq_test_security'] = array('nonce_valid' => true, 'can_edit' => true, 'is_revision' => false);
}
function gws_test_cheval_post_payload() {
  return array(
    GWSEQ_CHEVAL_NONCE_FIELD => 'nonce',
    '_gwseq_sexe' => 'male',
    '_gwseq_annee_naissance' => '2020',
    '_gwseq_robe' => 'bai',
    '_gwseq_statut_commercial' => 'for_sale',
    '_gwseq_prix_mode' => 'fixed',
    '_gwseq_prix_fixe' => '18000',
  );
}

// --- Nonce invalide ---
gws_test_reset_security();
$GLOBALS['__gwseq_test_security']['nonce_valid'] = false;
$_POST = gws_test_cheval_post_payload();
$GLOBALS['__gwseq_test_meta'][1001] = array();
gwseq_save_cheval_meta(1001);
gws_test_assert($GLOBALS['__gwseq_test_meta'][1001] === array(), 'Nonce invalide : aucune meta écrite');

// --- Permissions insuffisantes ---
gws_test_reset_security();
$GLOBALS['__gwseq_test_security']['can_edit'] = false;
$_POST = gws_test_cheval_post_payload();
$GLOBALS['__gwseq_test_meta'][1002] = array();
gwseq_save_cheval_meta(1002);
gws_test_assert($GLOBALS['__gwseq_test_meta'][1002] === array(), 'Permissions insuffisantes : aucune meta écrite');

// --- Révision ---
gws_test_reset_security();
$GLOBALS['__gwseq_test_security']['is_revision'] = true;
$_POST = gws_test_cheval_post_payload();
$GLOBALS['__gwseq_test_meta'][1003] = array();
gwseq_save_cheval_meta(1003);
gws_test_assert($GLOBALS['__gwseq_test_meta'][1003] === array(), 'Révision : aucune meta écrite');

// --- Cas valide : toutes les gardes passent -> les meta sont bien écrites ---
gws_test_reset_security();
$_POST = gws_test_cheval_post_payload();
$GLOBALS['__gwseq_test_meta'][1004] = array();
gwseq_save_cheval_meta(1004);
gws_test_assert(
  $GLOBALS['__gwseq_test_meta'][1004]['_gwseq_sexe'] === 'male'
    && $GLOBALS['__gwseq_test_meta'][1004]['_gwseq_annee_naissance'] === 2020
    && $GLOBALS['__gwseq_test_meta'][1004]['_gwseq_statut_commercial'] === 'for_sale'
    && $GLOBALS['__gwseq_test_meta'][1004]['_gwseq_prix_fixe'] === 18000.0,
  'Cas valide : nonce/capability/autosave/révision tous corrects -> les meta d’identité et de commercialisation sont bien enregistrées'
);

// --- Autosave : testé en dernier, DOING_AUTOSAVE ne peut être défini qu'une fois par processus
// PHP (resterait sinon "vrai" pour tous les cas suivants) ---
gws_test_reset_security();
define('DOING_AUTOSAVE', true);
$_POST = gws_test_cheval_post_payload();
$GLOBALS['__gwseq_test_meta'][1005] = array();
gwseq_save_cheval_meta(1005);
gws_test_assert($GLOBALS['__gwseq_test_meta'][1005] === array(), 'Autosave : aucune meta écrite');

// =====================================================================================
// Nettoyage de l'état WordPress hérité sur la meta box Identité (correctifs itératifs de la
// régression "onglet Identité vide") — gwseq_cleanup_legacy_identite_metabox_user_state().
// Le contexte d'enregistrement (add_meta_box, 'normal') n'a jamais changé et n'est pas en cause :
// ce nettoyage porte uniquement sur les PRÉFÉRENCES PERSISTÉES PAR UTILISATEUR que WordPress a pu
// accumuler pendant les recettes successives (Screen Options, ordre/colonne des meta boxes).
// =====================================================================================

$cheval_screen = (object) array('id' => GWSEQ_CPT_CHEVAL, 'post_type' => GWSEQ_CPT_CHEVAL);

// --- Écran hors sujet : ne touche jamais aux préférences d'un autre écran ---
$GLOBALS['__gwseq_test_user_meta'] = array(42 => array(
  'metaboxhidden_gwseq_prestation' => array('gwseq-cheval-identite'),
));
$GLOBALS['__gwseq_test_current_user_id'] = 42;
gwseq_cleanup_legacy_identite_metabox_user_state((object) array('id' => 'gwseq_prestation', 'post_type' => 'gwseq_prestation'));
gws_test_assert(
  $GLOBALS['__gwseq_test_user_meta'][42]['metaboxhidden_gwseq_prestation'] === array('gwseq-cheval-identite'),
  'Nettoyage Identité : un écran qui n’est pas celui de la fiche Cheval n’est jamais touché'
);

// --- Aucun utilisateur connecté (ex. contexte CLI/CRON) : jamais d'erreur, rien à faire ---
$GLOBALS['__gwseq_test_current_user_id'] = 0;
gwseq_cleanup_legacy_identite_metabox_user_state($cheval_screen); // ne doit lever aucune erreur

// --- Screen Options : la case "Identité" avait été décochée (cause racine confirmée en 0.12.3,
// masque la boîte ENTIÈRE via .hide-if-js) -> elle doit être retirée de la liste des masquées,
// sans affecter les autres boîtes masquées par ailleurs ---
$GLOBALS['__gwseq_test_current_user_id'] = 7;
$GLOBALS['__gwseq_test_user_meta'] = array(7 => array(
  'metaboxhidden_gwseq_cheval' => array('gwseq-cheval-identite', 'gwseq-cheval-pedigree-preview'),
));
gwseq_cleanup_legacy_identite_metabox_user_state($cheval_screen);
gws_test_assert(
  $GLOBALS['__gwseq_test_user_meta'][7]['metaboxhidden_gwseq_cheval'] === array('gwseq-cheval-pedigree-preview'),
  'Nettoyage Identité : la case "Identité" décochée dans Screen Options est réactivée, sans toucher aux autres boîtes masquées par l’utilisateur'
);

// --- Aucune préférence héritée à nettoyer : jamais de réécriture inutile ---
$GLOBALS['__gwseq_test_current_user_id'] = 8;
$GLOBALS['__gwseq_test_user_meta'] = array(8 => array(
  'metaboxhidden_gwseq_cheval' => array('gwseq-cheval-pedigree-preview'),
));
gwseq_cleanup_legacy_identite_metabox_user_state($cheval_screen);
gws_test_assert(
  $GLOBALS['__gwseq_test_user_meta'][8]['metaboxhidden_gwseq_cheval'] === array('gwseq-cheval-pedigree-preview'),
  'Nettoyage Identité : idempotent — une préférence déjà propre n’est jamais réécrite ni altérée'
);

// --- Ordre des meta boxes : "Identité" avait dérivé dans un ancien glisser-déposer vers la
// colonne latérale ('side') -> l'entrée est retirée de cet ordre pour que WordPress retombe sur
// son enregistrement réel ('normal'), sans jamais toucher à l'ordre du contexte 'normal' lui-même
// ni aux autres identifiants de la colonne latérale ---
$GLOBALS['__gwseq_test_current_user_id'] = 9;
$GLOBALS['__gwseq_test_user_meta'] = array(9 => array(
  'meta-box-order_gwseq_cheval' => array(
    'side' => 'postimagediv,gwseq-cheval-identite,gwseq-cheval-global-id-dev',
    'normal' => 'gwseq-cheval-commercialisation,gwseq-cheval-pedigree',
  ),
));
gwseq_cleanup_legacy_identite_metabox_user_state($cheval_screen);
gws_test_assert(
  $GLOBALS['__gwseq_test_user_meta'][9]['meta-box-order_gwseq_cheval']['side'] === 'postimagediv,gwseq-cheval-global-id-dev',
  'Nettoyage Identité : une entrée héritée dans un contexte autre que "normal" (ex. "side", ancien glisser-déposer) est retirée, sans affecter les autres identifiants de ce contexte'
);
gws_test_assert(
  $GLOBALS['__gwseq_test_user_meta'][9]['meta-box-order_gwseq_cheval']['normal'] === 'gwseq-cheval-commercialisation,gwseq-cheval-pedigree',
  'Nettoyage Identité : l’ordre du contexte "normal" (le seul contexte d’enregistrement réel de la boîte) n’est jamais modifié'
);

// --- "Identité" déjà correctement en 'normal' dans l'ordre mémorisé : jamais de réécriture ---
$GLOBALS['__gwseq_test_current_user_id'] = 10;
$GLOBALS['__gwseq_test_user_meta'] = array(10 => array(
  'meta-box-order_gwseq_cheval' => array('normal' => 'gwseq-cheval-identite,gwseq-cheval-commercialisation'),
));
gwseq_cleanup_legacy_identite_metabox_user_state($cheval_screen);
gws_test_assert(
  $GLOBALS['__gwseq_test_user_meta'][10]['meta-box-order_gwseq_cheval']['normal'] === 'gwseq-cheval-identite,gwseq-cheval-commercialisation',
  'Nettoyage Identité : un ordre déjà correct (Identité dans "normal") n’est jamais modifié'
);

echo ($failures === 0 ? 'Tous les tests sont passés.' : "$failures test(s) en échec.") . "\n";
exit($failures === 0 ? 0 : 1);

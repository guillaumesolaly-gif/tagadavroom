<?php
/**
 * Cheval — socle métier (Étape 4) : identité, catégories (voir includes/taxonomies.php et
 * includes/cheval-categories.php), commercialisation, et Global Horse ID.
 *
 * Source de vérité unique par donnée métier (§2-3 de la demande) : Nom = post_title, Photo
 * principale = image à la une native (voir includes/post-types.php pour son relabelling), Ordre =
 * menu_order natif (voir includes/admin-ui.php). Ce fichier n'ajoute donc que ce que WordPress ne
 * fournit pas nativement : sexe, année de naissance, robe, race/stud-book, taille, éleveur,
 * propriétaire, identifiants officiels éventuels, statut commercial, prix, et l'identifiant
 * technique global de la fiche.
 *
 * Même architecture que Prestation (includes/prestation-fields.php) : fonctions de sanitation
 * pures (aucun accès à $_POST ni à la base), séparées de la glue WordPress (meta boxes,
 * sauvegarde), testées avec des données représentant fidèlement une soumission réelle de
 * formulaire (voir tests/gws-equestrian-cheval-logic-test.php).
 *
 * §20 de la demande — pas d'API générique de lecture en masse des meta : les fonctions
 * gwseq_get_cheval_identity()/gwseq_get_cheval_commercial() ci-dessous renvoient chacune un
 * tableau explicite et fermé de champs nommés, jamais get_post_meta($id) sans filtre. Une future
 * fonction de projection publique (ex. gwseq_get_public_horse_data()) pourra s'appuyer dessus sans
 * qu'aucune décision prise ici n'oblige à exposer les meta en bloc.
 *
 * Internationalisation : même discipline qu'à l'Étape 3 — libellés/options/aides du logiciel
 * traduits via le text domain 'gws-core' ; valeurs techniques stockées ('male', 'bai', 'aqps',
 * 'for_sale', 'fixed'...) jamais traduites ; contenu saisi par le professionnel (éleveur,
 * propriétaire, précisions "Autre", libellé "Prix sur demande" personnalisé, UELN, SIRE) jamais
 * passé dans une fonction de traduction.
 */

if (!defined('ABSPATH')) exit;

const GWSEQ_CHEVAL_NONCE_ACTION = 'gwseq_save_cheval_meta';
const GWSEQ_CHEVAL_NONCE_FIELD = 'gwseq_save_cheval_meta_nonce';

/* -------------------------------------------------------------------------------------------
 * Listes d'options (§4 de la demande) : valeurs techniques stables, libellés traduits.
 * ----------------------------------------------------------------------------------------- */

function gwseq_cheval_sexe_options() {
  return array(
    'male' => __('Mâle', 'gws-core'),
    'female' => __('Femelle', 'gws-core'),
    'gelding' => __('Hongre', 'gws-core'),
  );
}

/**
 * Liste pratique, pas scientifiquement exhaustive (§4) — "Autre" couvre le reste sans construire
 * de nomenclature complète des robes.
 */
function gwseq_cheval_robe_options() {
  return array(
    'bai' => __('Bai', 'gws-core'),
    'bai_brun' => __('Bai brun', 'gws-core'),
    'alezan' => __('Alezan', 'gws-core'),
    'noir' => __('Noir', 'gws-core'),
    'gris' => __('Gris', 'gws-core'),
    'rouan' => __('Rouan', 'gws-core'),
    'isabelle' => __('Isabelle', 'gws-core'),
    'palomino' => __('Palomino', 'gws-core'),
    'pie' => __('Pie', 'gws-core'),
    'autre' => __('Autre (préciser)', 'gws-core'),
  );
}

/**
 * Liste pratique et non figée (§4) : ni exhaustive, ni permanente. Aucune logique du module ne
 * doit jamais dépendre d'un nom de stud-book précis — cette liste ne fait que proposer des valeurs
 * courantes, "Autre" couvrant tout le reste sans jamais bloquer la saisie.
 */
function gwseq_cheval_race_options() {
  return array(
    'selle_francais' => __('Selle Français', 'gws-core'),
    'anglo_arabe' => __('Anglo-Arabe', 'gws-core'),
    'pur_sang' => __('Pur-sang', 'gws-core'),
    'aqps' => __('AQPS', 'gws-core'),
    'kwpn' => __('KWPN', 'gws-core'),
    'bwp' => __('BWP', 'gws-core'),
    'zangersheide' => __('Zangersheide', 'gws-core'),
    'holsteiner' => __('Holsteiner', 'gws-core'),
    'oldenburg' => __('Oldenburg', 'gws-core'),
    'hanovrien' => __('Hanovrien', 'gws-core'),
    'westphalien' => __('Westphalien', 'gws-core'),
    'trakehner' => __('Trakehner', 'gws-core'),
    'lusitanien' => __('Lusitanien', 'gws-core'),
    'pre' => __('PRE', 'gws-core'),
    'connemara' => __('Connemara', 'gws-core'),
    'poney_francais_selle' => __('Poney Français de Selle', 'gws-core'),
    'welsh' => __('Welsh', 'gws-core'),
    'shetland' => __('Shetland', 'gws-core'),
    'autre' => __('Autre (préciser)', 'gws-core'),
  );
}

/**
 * Statut commercial (§11) : champ structuré totalement indépendant des catégories de chevaux —
 * une catégorie éditoriale ("Chevaux à vendre") n'implique jamais ce statut, et réciproquement
 * (voir §10 de la demande et le texte d'aide affiché dans la meta box Commercialisation).
 */
function gwseq_cheval_statut_commercial_options() {
  return array(
    'not_offered' => __('Non proposé', 'gws-core'),
    'for_sale' => __('À vendre', 'gws-core'),
    'reserved' => __('Réservé', 'gws-core'),
    'sold' => __('Vendu', 'gws-core'),
  );
}

function gwseq_cheval_prix_mode_options() {
  return array(
    'fixed' => __('Prix fixe', 'gws-core'),
    'range' => __('Fourchette', 'gws-core'),
    'on_request' => __('Sur demande', 'gws-core'),
  );
}

/* -------------------------------------------------------------------------------------------
 * Enregistrement des meta (jamais exposées en REST — §19 : aucun besoin d'exposition publique à
 * ce stade, une future API GWS travaillera depuis une projection explicite, pas depuis ces meta
 * brutes).
 * ----------------------------------------------------------------------------------------- */

function gwseq_register_cheval_meta() {
  $string_meta = array(
    '_gwseq_sexe', '_gwseq_robe', '_gwseq_robe_autre', '_gwseq_race', '_gwseq_race_autre',
    '_gwseq_eleveur', '_gwseq_proprietaire', '_gwseq_ueln', '_gwseq_sire',
    '_gwseq_statut_commercial', '_gwseq_prix_mode', '_gwseq_prix_demande_libelle',
  );
  foreach ($string_meta as $key) {
    register_post_meta(GWSEQ_CPT_CHEVAL, $key, array('single' => true, 'type' => 'string', 'show_in_rest' => false));
  }
  foreach (array('_gwseq_annee_naissance', '_gwseq_taille_cm') as $key) {
    register_post_meta(GWSEQ_CPT_CHEVAL, $key, array('single' => true, 'type' => 'integer', 'show_in_rest' => false));
  }
  foreach (array('_gwseq_prix_fixe', '_gwseq_prix_min', '_gwseq_prix_max') as $key) {
    register_post_meta(GWSEQ_CPT_CHEVAL, $key, array('single' => true, 'type' => 'number', 'show_in_rest' => false));
  }
  // Global Horse ID (§15-19) : jamais saisi par un utilisateur, jamais exposé en REST, jamais
  // réutilisé comme jeton d'accès (voir gwseq_assign_cheval_global_id() plus bas).
  register_post_meta(GWSEQ_CPT_CHEVAL, '_gwseq_global_id', array('single' => true, 'type' => 'string', 'show_in_rest' => false));
}
add_action('init', 'gwseq_register_cheval_meta');

/* -------------------------------------------------------------------------------------------
 * Fonctions pures : sanitation, validation, lecture. Sans dépendance à $_POST ni à la base pour
 * les fonctions de sanitation — testées directement.
 * ----------------------------------------------------------------------------------------- */

/**
 * Bornes volontairement larges mais raisonnables (§32) : une fiche peut documenter un cheval déjà
 * âgé (élevage, retraités) sans blocage arbitraire, tout en rejetant une saisie absurde (ex.
 * 99999). La borne haute suit l'année courante + 1 pour ne pas bloquer l'enregistrement anticipé
 * d'un poulain attendu l'année suivante (catégories "Poulains 2027" par ex.).
 */
const GWSEQ_CHEVAL_ANNEE_MIN = 1900;

function gwseq_cheval_annee_naissance_max() {
  return ((int) gmdate('Y')) + 1;
}

function gwseq_sanitize_cheval_annee_naissance($raw) {
  if ($raw === '' || $raw === null || !is_numeric($raw)) return '';
  $annee = (int) $raw;
  if ($annee < GWSEQ_CHEVAL_ANNEE_MIN || $annee > gwseq_cheval_annee_naissance_max()) return '';
  return $annee;
}

/**
 * Bornes de taille en centimètres (§32) : de la plus petite race miniature (~40 cm) au plus grand
 * cheval de trait (~250 cm) — large mais pas sans limite, pour écarter une saisie manifestement
 * erronée (ex. taille en mètres saisie par erreur : "168" et non "1.68").
 */
const GWSEQ_CHEVAL_TAILLE_MIN = 40;
const GWSEQ_CHEVAL_TAILLE_MAX = 250;

function gwseq_sanitize_cheval_taille($raw) {
  if ($raw === '' || $raw === null || !is_numeric($raw)) return '';
  $taille = (int) round((float) $raw);
  if ($taille < GWSEQ_CHEVAL_TAILLE_MIN || $taille > GWSEQ_CHEVAL_TAILLE_MAX) return '';
  return $taille;
}

/**
 * Âge calculé, jamais stocké (§4) : donnée dérivée de l'année de naissance, recalculée à chaque
 * lecture. Calcul volontairement simple (année courante - année de naissance) : ce n'est pas une
 * approximation à corriger, c'est la convention métier équine elle-même — un cheval prend
 * conventionnellement un an de plus au 1er janvier, indépendamment de sa date de naissance réelle
 * dans l'année (correction demandée en recette de l'Étape 4, valeur retenue confirmée par le
 * client comme la bonne définition métier, seule la présentation a changé — voir
 * gwseq_cheval_age_label() ci-dessous).
 */
function gwseq_cheval_age_from_birth_year($annee_naissance, $current_year = null) {
  $annee_naissance = gwseq_sanitize_cheval_annee_naissance($annee_naissance);
  if ($annee_naissance === '') return '';
  if ($current_year === null) $current_year = (int) gmdate('Y');
  $age = $current_year - $annee_naissance;
  return $age >= 0 ? $age : '';
}

/**
 * Libellé affiché de l'âge (§ recette Étape 4) : "1 an"/"7 ans", jamais "≈ 7 an(s)" ni de mention
 * permanente d'approximation — le calcul lui-même reste la convention équine assumée (voir
 * gwseq_cheval_age_from_birth_year()), donc rien à en excuser dans l'interface. Accord
 * singulier/pluriel géré nativement par _n(), chaîne du logiciel traductible.
 */
function gwseq_cheval_age_label($age) {
  if ($age === '') return '';
  return sprintf(
    /* translators: %d: âge en années (convention équine : un an de plus au 1er janvier) */
    _n('%d an', '%d ans', $age, 'gws-core'),
    $age
  );
}

/**
 * Transforme un tableau à la forme de $_POST en données d'identité propres. Fonction pure — voir
 * tests/gws-equestrian-cheval-logic-test.php.
 */
function gwseq_sanitize_cheval_identity_input($raw) {
  $raw = is_array($raw) ? $raw : array();

  $sexe = isset($raw['_gwseq_sexe']) ? sanitize_key(wp_unslash($raw['_gwseq_sexe'])) : '';
  if ($sexe !== '' && !array_key_exists($sexe, gwseq_cheval_sexe_options())) $sexe = '';

  $robe = isset($raw['_gwseq_robe']) ? sanitize_key(wp_unslash($raw['_gwseq_robe'])) : '';
  if ($robe !== '' && !array_key_exists($robe, gwseq_cheval_robe_options())) $robe = '';

  $race = isset($raw['_gwseq_race']) ? sanitize_key(wp_unslash($raw['_gwseq_race'])) : '';
  if ($race !== '' && !array_key_exists($race, gwseq_cheval_race_options())) $race = '';

  return array(
    'sexe' => $sexe,
    'annee_naissance' => gwseq_sanitize_cheval_annee_naissance($raw['_gwseq_annee_naissance'] ?? ''),
    'robe' => $robe,
    'robe_autre' => gws_core_field_sanitize('text', $raw['_gwseq_robe_autre'] ?? ''),
    'race' => $race,
    'race_autre' => gws_core_field_sanitize('text', $raw['_gwseq_race_autre'] ?? ''),
    'taille_cm' => gwseq_sanitize_cheval_taille($raw['_gwseq_taille_cm'] ?? ''),
    'eleveur' => gws_core_field_sanitize('text', $raw['_gwseq_eleveur'] ?? ''),
    'proprietaire' => gws_core_field_sanitize('text', $raw['_gwseq_proprietaire'] ?? ''),
    // UELN/SIRE (§21) : simples identifiants texte, aucune validation de format ni d'API
    // distante — voir la limitation documentée dans le CR de livraison.
    'ueln' => gws_core_field_sanitize('text', $raw['_gwseq_ueln'] ?? ''),
    'sire' => gws_core_field_sanitize('text', $raw['_gwseq_sire'] ?? ''),
  );
}

/**
 * Transforme un tableau à la forme de $_POST en données de commercialisation propres. Fonction
 * pure — le mode "Sur demande" ne fabrique jamais de prix, la valeur 0 reste une vraie valeur
 * saisie (jamais confondue avec une absence de prix), symétrique de la tarification Prestation.
 */
function gwseq_sanitize_cheval_commercial_input($raw) {
  $raw = is_array($raw) ? $raw : array();

  $statut = isset($raw['_gwseq_statut_commercial']) ? sanitize_key(wp_unslash($raw['_gwseq_statut_commercial'])) : '';
  if (!array_key_exists($statut, gwseq_cheval_statut_commercial_options())) $statut = 'not_offered';

  $mode = isset($raw['_gwseq_prix_mode']) ? sanitize_key(wp_unslash($raw['_gwseq_prix_mode'])) : '';
  if (!array_key_exists($mode, gwseq_cheval_prix_mode_options())) $mode = 'fixed';

  return array(
    'statut_commercial' => $statut,
    'prix_mode' => $mode,
    'prix_fixe' => gws_core_field_sanitize('number', $raw['_gwseq_prix_fixe'] ?? ''),
    'prix_min' => gws_core_field_sanitize('number', $raw['_gwseq_prix_min'] ?? ''),
    'prix_max' => gws_core_field_sanitize('number', $raw['_gwseq_prix_max'] ?? ''),
    'prix_demande_libelle' => gws_core_field_sanitize('text', $raw['_gwseq_prix_demande_libelle'] ?? ''),
  );
}

/**
 * Même mécanisme "jamais initialisé" vs "volontairement vidé" que pour la Prestation (Étape 3),
 * via metadata_exists() plutôt qu'une simple valeur par défaut sur get_post_meta().
 */
function gwseq_cheval_prix_demande_libelle_default() {
  return __('Prix sur demande', 'gws-core');
}

function gwseq_get_cheval_prix_demande_libelle($post_id) {
  if (!metadata_exists('post', $post_id, '_gwseq_prix_demande_libelle')) {
    return gwseq_cheval_prix_demande_libelle_default();
  }
  return get_post_meta($post_id, '_gwseq_prix_demande_libelle', true);
}

/**
 * Toutes les données d'identité d'une fiche cheval, dans un tableau explicite et fermé (§20 :
 * jamais get_post_meta($id) en bloc).
 */
function gwseq_get_cheval_identity($post_id) {
  return array(
    'sexe' => get_post_meta($post_id, '_gwseq_sexe', true),
    'annee_naissance' => get_post_meta($post_id, '_gwseq_annee_naissance', true),
    'robe' => get_post_meta($post_id, '_gwseq_robe', true),
    'robe_autre' => get_post_meta($post_id, '_gwseq_robe_autre', true),
    'race' => get_post_meta($post_id, '_gwseq_race', true),
    'race_autre' => get_post_meta($post_id, '_gwseq_race_autre', true),
    'taille_cm' => get_post_meta($post_id, '_gwseq_taille_cm', true),
    'eleveur' => get_post_meta($post_id, '_gwseq_eleveur', true),
    'proprietaire' => get_post_meta($post_id, '_gwseq_proprietaire', true),
    'ueln' => get_post_meta($post_id, '_gwseq_ueln', true),
    'sire' => get_post_meta($post_id, '_gwseq_sire', true),
  );
}

function gwseq_get_cheval_commercial($post_id) {
  $statut = get_post_meta($post_id, '_gwseq_statut_commercial', true);
  $mode = get_post_meta($post_id, '_gwseq_prix_mode', true);
  return array(
    'statut_commercial' => $statut !== '' ? $statut : 'not_offered',
    'prix_mode' => $mode !== '' ? $mode : 'fixed',
    'prix_fixe' => get_post_meta($post_id, '_gwseq_prix_fixe', true),
    'prix_min' => get_post_meta($post_id, '_gwseq_prix_min', true),
    'prix_max' => get_post_meta($post_id, '_gwseq_prix_max', true),
    'prix_demande_libelle' => gwseq_get_cheval_prix_demande_libelle($post_id),
  );
}

function gwseq_cheval_robe_label($robe, $robe_autre) {
  if ($robe === '') return '';
  if ($robe === 'autre') return $robe_autre !== '' ? $robe_autre : __('Autre', 'gws-core');
  $options = gwseq_cheval_robe_options();
  return $options[$robe] ?? '';
}

function gwseq_cheval_race_label($race, $race_autre) {
  if ($race === '') return '';
  if ($race === 'autre') return $race_autre !== '' ? $race_autre : __('Autre', 'gws-core');
  $options = gwseq_cheval_race_options();
  return $options[$race] ?? '';
}

/**
 * Résumé texte (jamais de HTML) du prix commercial, réutilisable admin/futur front/API — même
 * philosophie que gwseq_prestation_price_summary(). §14 de la demande : volontairement AUCUN
 * suffixe HT/TTC ici (voir la limitation documentée dans le CR) — seuls le montant et la devise
 * globale sont représentés, jamais un moteur fiscal propre au cheval.
 */
function gwseq_cheval_price_summary($commercial, $currency_code = 'EUR') {
  $mode = $commercial['prix_mode'] ?? 'fixed';
  if ($mode === 'on_request') return $commercial['prix_demande_libelle'] ?? '';

  $currency_symbol = gwseq_currency_symbol($currency_code);

  if ($mode === 'range') {
    $min = $commercial['prix_min'] ?? '';
    $max = $commercial['prix_max'] ?? '';
    if ($min === '' && $max === '') return '';
    if ($min !== '' && $max !== '') {
      return gwseq_format_price_number($min) . ' – ' . gwseq_format_price_number($max) . ' ' . $currency_symbol;
    }
    $only = $min !== '' ? $min : $max;
    return gwseq_format_price_number($only) . ' ' . $currency_symbol;
  }

  if (($commercial['prix_fixe'] ?? '') === '') return '';
  return gwseq_format_price_number($commercial['prix_fixe']) . ' ' . $currency_symbol;
}

/* -------------------------------------------------------------------------------------------
 * Global Horse ID (§15-19) : identifiant technique de la FICHE, indépendant de post_id, du slug,
 * de l'URL, du domaine et du thème — jamais un identifiant biologique de l'animal (deux fiches
 * indépendantes du même cheval réel n'auront jamais automatiquement le même identifiant), jamais
 * un secret (ni jeton, ni clé d'accès).
 *
 * Assigné une seule fois, au premier enregistrement RÉEL (jamais un auto-draft), jamais régénéré
 * une fois présent, jamais copié lors d'une future duplication (Étape 7). Volontairement
 * indépendant du nonce/de la meta box d'identité : la fiche doit recevoir son identifiant dès
 * qu'un enregistrement réel a lieu, y compris via un mécanisme WordPress qui ne soumettrait pas ce
 * nonce précis (ex. Quick Edit) — seules les garde-fous génériques (autosave, révision,
 * auto-draft, idempotence) s'appliquent ici.
 */
function gwseq_assign_cheval_global_id($post_id, $post, $update) {
  if (!$post || $post->post_type !== GWSEQ_CPT_CHEVAL) return;
  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
  if (function_exists('wp_is_post_revision') && wp_is_post_revision($post_id)) return;
  if ($post->post_status === 'auto-draft') return;
  if (metadata_exists('post', $post_id, '_gwseq_global_id')) return;
  update_post_meta($post_id, '_gwseq_global_id', wp_generate_uuid4());
}
add_action('save_post_' . GWSEQ_CPT_CHEVAL, 'gwseq_assign_cheval_global_id', 10, 3);

function gwseq_get_cheval_global_id($post_id) {
  return get_post_meta($post_id, '_gwseq_global_id', true);
}

/* -------------------------------------------------------------------------------------------
 * Meta boxes et sauvegarde (glue WordPress).
 * ----------------------------------------------------------------------------------------- */

function gwseq_add_cheval_meta_boxes() {
  add_meta_box('gwseq-cheval-identite', __('Identité', 'gws-core'), 'gwseq_render_cheval_identite_box', GWSEQ_CPT_CHEVAL, 'normal', 'high');
  add_meta_box('gwseq-cheval-commercialisation', __('Commercialisation', 'gws-core'), 'gwseq_render_cheval_commercialisation_box', GWSEQ_CPT_CHEVAL, 'normal', 'default');

  // §40 : le Global Horse ID n'est jamais affiché aux utilisateurs de production — cette boîte
  // n'est même pas enregistrée en dehors d'un environnement local/développement, pour qu'aucune
  // fuite ne dépende d'un simple masquage visuel.
  if (function_exists('wp_get_environment_type') && in_array(wp_get_environment_type(), array('local', 'development'), true)) {
    add_meta_box('gwseq-cheval-global-id-dev', __('Identifiant technique (visible en local/développement uniquement)', 'gws-core'), 'gwseq_render_cheval_global_id_dev_box', GWSEQ_CPT_CHEVAL, 'side', 'low');
  }
}
add_action('add_meta_boxes_' . GWSEQ_CPT_CHEVAL, 'gwseq_add_cheval_meta_boxes');

function gwseq_render_cheval_identite_box($post) {
  $identity = gwseq_get_cheval_identity($post->ID);
  $age = gwseq_cheval_age_from_birth_year($identity['annee_naissance']);
  wp_nonce_field(GWSEQ_CHEVAL_NONCE_ACTION, GWSEQ_CHEVAL_NONCE_FIELD);
  ?>
  <p>
    <label for="gwseq-cheval-sexe"><strong><?php esc_html_e('Sexe', 'gws-core'); ?></strong></label><br>
    <select id="gwseq-cheval-sexe" name="_gwseq_sexe">
      <option value=""><?php esc_html_e('— Non renseigné —', 'gws-core'); ?></option>
      <?php foreach (gwseq_cheval_sexe_options() as $key => $label) : ?>
        <option value="<?php echo esc_attr($key); ?>" <?php selected($identity['sexe'], $key); ?>><?php echo esc_html($label); ?></option>
      <?php endforeach; ?>
    </select>
  </p>
  <p>
    <label for="gwseq-cheval-annee"><strong><?php esc_html_e('Année de naissance', 'gws-core'); ?></strong></label><br>
    <input type="number" step="1" class="small-text" id="gwseq-cheval-annee" name="_gwseq_annee_naissance" value="<?php echo esc_attr($identity['annee_naissance']); ?>">
    <?php if ($age !== '') : ?>
      <span class="description" title="<?php esc_attr_e('Âge calculé automatiquement à partir de l’année de naissance selon la convention équine.', 'gws-core'); ?>"> <?php echo esc_html(gwseq_cheval_age_label($age)); ?></span>
    <?php endif; ?>
  </p>
  <p>
    <label for="gwseq-cheval-robe"><strong><?php esc_html_e('Robe', 'gws-core'); ?></strong></label><br>
    <select id="gwseq-cheval-robe" name="_gwseq_robe">
      <option value=""><?php esc_html_e('— Non renseignée —', 'gws-core'); ?></option>
      <?php foreach (gwseq_cheval_robe_options() as $key => $label) : ?>
        <option value="<?php echo esc_attr($key); ?>" <?php selected($identity['robe'], $key); ?>><?php echo esc_html($label); ?></option>
      <?php endforeach; ?>
    </select>
  </p>
  <p data-gwseq-cheval-fields="robe-autre" style="<?php echo $identity['robe'] === 'autre' ? '' : 'display:none;'; ?>">
    <label for="gwseq-cheval-robe-autre"><strong><?php esc_html_e('Préciser la robe', 'gws-core'); ?></strong></label><br>
    <input type="text" class="regular-text" id="gwseq-cheval-robe-autre" name="_gwseq_robe_autre" value="<?php echo esc_attr($identity['robe_autre']); ?>">
  </p>
  <p>
    <label for="gwseq-cheval-race"><strong><?php esc_html_e('Race / Stud-book', 'gws-core'); ?></strong></label><br>
    <select id="gwseq-cheval-race" name="_gwseq_race">
      <option value=""><?php esc_html_e('— Non renseignée —', 'gws-core'); ?></option>
      <?php foreach (gwseq_cheval_race_options() as $key => $label) : ?>
        <option value="<?php echo esc_attr($key); ?>" <?php selected($identity['race'], $key); ?>><?php echo esc_html($label); ?></option>
      <?php endforeach; ?>
    </select>
  </p>
  <p data-gwseq-cheval-fields="race-autre" style="<?php echo $identity['race'] === 'autre' ? '' : 'display:none;'; ?>">
    <label for="gwseq-cheval-race-autre"><strong><?php esc_html_e('Préciser la race / le stud-book', 'gws-core'); ?></strong></label><br>
    <input type="text" class="regular-text" id="gwseq-cheval-race-autre" name="_gwseq_race_autre" value="<?php echo esc_attr($identity['race_autre']); ?>">
  </p>
  <p>
    <label for="gwseq-cheval-taille"><strong><?php esc_html_e('Taille (cm)', 'gws-core'); ?></strong></label><br>
    <input type="number" step="1" class="small-text" id="gwseq-cheval-taille" name="_gwseq_taille_cm" value="<?php echo esc_attr($identity['taille_cm']); ?>">
  </p>
  <p>
    <label for="gwseq-cheval-eleveur"><strong><?php esc_html_e('Éleveur', 'gws-core'); ?></strong></label><br>
    <input type="text" class="regular-text" id="gwseq-cheval-eleveur" name="_gwseq_eleveur" value="<?php echo esc_attr($identity['eleveur']); ?>">
  </p>
  <p>
    <label for="gwseq-cheval-proprietaire"><strong><?php esc_html_e('Propriétaire', 'gws-core'); ?></strong></label><br>
    <input type="text" class="regular-text" id="gwseq-cheval-proprietaire" name="_gwseq_proprietaire" value="<?php echo esc_attr($identity['proprietaire']); ?>">
  </p>
  <p>
    <label for="gwseq-cheval-ueln"><strong><?php esc_html_e('UELN', 'gws-core'); ?></strong></label><br>
    <input type="text" class="regular-text" id="gwseq-cheval-ueln" name="_gwseq_ueln" value="<?php echo esc_attr($identity['ueln']); ?>">
  </p>
  <p>
    <label for="gwseq-cheval-sire"><strong><?php esc_html_e('Numéro SIRE', 'gws-core'); ?></strong></label><br>
    <input type="text" class="regular-text" id="gwseq-cheval-sire" name="_gwseq_sire" value="<?php echo esc_attr($identity['sire']); ?>">
  </p>
  <?php
}

function gwseq_render_cheval_commercialisation_box($post) {
  $commercial = gwseq_get_cheval_commercial($post->ID);
  $currency_options = gwseq_currency_options();
  wp_nonce_field(GWSEQ_CHEVAL_NONCE_ACTION, GWSEQ_CHEVAL_NONCE_FIELD);
  ?>
  <p>
    <label for="gwseq-cheval-statut"><strong><?php esc_html_e('Statut commercial', 'gws-core'); ?></strong></label><br>
    <select id="gwseq-cheval-statut" name="_gwseq_statut_commercial">
      <?php foreach (gwseq_cheval_statut_commercial_options() as $key => $label) : ?>
        <option value="<?php echo esc_attr($key); ?>" <?php selected($commercial['statut_commercial'], $key); ?>><?php echo esc_html($label); ?></option>
      <?php endforeach; ?>
    </select>
    <br><span class="description"><?php esc_html_e('Champ indépendant des catégories de chevaux : une catégorie éditoriale comme « Chevaux à vendre » n’implique jamais ce statut, et réciproquement.', 'gws-core'); ?></span>
  </p>
  <p>
    <label for="gwseq-cheval-prix-mode"><strong><?php esc_html_e('Mode de prix', 'gws-core'); ?></strong></label><br>
    <select id="gwseq-cheval-prix-mode" name="_gwseq_prix_mode">
      <?php foreach (gwseq_cheval_prix_mode_options() as $key => $label) : ?>
        <option value="<?php echo esc_attr($key); ?>" <?php selected($commercial['prix_mode'], $key); ?>><?php echo esc_html($label); ?></option>
      <?php endforeach; ?>
    </select>
    <br><span class="description"><?php echo esc_html(sprintf(
      /* translators: %s: libellé de la devise configurée globalement pour le module */
      __('Montants exprimés dans la devise globale du module (%s), réglable dans Prestations → Réglages. Aucun calcul de TVA (HT/TTC) n’est appliqué au prix d’un cheval.', 'gws-core'),
      $currency_options[gwseq_get_currency()] ?? gwseq_get_currency()
    )); ?></span>
  </p>
  <p data-gwseq-cheval-fields="prix-fixed" style="<?php echo $commercial['prix_mode'] === 'fixed' ? '' : 'display:none;'; ?>">
    <label for="gwseq-cheval-prix-fixe"><strong><?php esc_html_e('Prix', 'gws-core'); ?></strong></label><br>
    <input type="number" step="any" class="regular-text" id="gwseq-cheval-prix-fixe" name="_gwseq_prix_fixe" value="<?php echo esc_attr($commercial['prix_fixe']); ?>">
  </p>
  <div data-gwseq-cheval-fields="prix-range" style="<?php echo $commercial['prix_mode'] === 'range' ? '' : 'display:none;'; ?>">
    <p>
      <label for="gwseq-cheval-prix-min"><strong><?php esc_html_e('Prix minimum', 'gws-core'); ?></strong></label><br>
      <input type="number" step="any" class="regular-text" id="gwseq-cheval-prix-min" name="_gwseq_prix_min" value="<?php echo esc_attr($commercial['prix_min']); ?>">
    </p>
    <p>
      <label for="gwseq-cheval-prix-max"><strong><?php esc_html_e('Prix maximum', 'gws-core'); ?></strong></label><br>
      <input type="number" step="any" class="regular-text" id="gwseq-cheval-prix-max" name="_gwseq_prix_max" value="<?php echo esc_attr($commercial['prix_max']); ?>">
    </p>
  </div>
  <p data-gwseq-cheval-fields="prix-on-request" style="<?php echo $commercial['prix_mode'] === 'on_request' ? '' : 'display:none;'; ?>">
    <label for="gwseq-cheval-prix-demande-libelle"><strong><?php esc_html_e('Libellé affiché', 'gws-core'); ?></strong></label><br>
    <input type="text" class="regular-text" id="gwseq-cheval-prix-demande-libelle" name="_gwseq_prix_demande_libelle" value="<?php echo esc_attr($commercial['prix_demande_libelle']); ?>" placeholder="<?php esc_attr_e('Ex. Prix sur demande, Nous contacter...', 'gws-core'); ?>">
    <br><span class="description"><?php esc_html_e('Affiché tel quel à la place d’un montant chiffré. Laisser vide pour n’afficher aucune mention de prix pour ce cheval.', 'gws-core'); ?></span>
  </p>
  <p class="description"><?php esc_html_e('Le prix reste enregistré même si le statut ci-dessus change : un futur rendu public respectera toujours le statut commercial pour décider de son affichage.', 'gws-core'); ?></p>
  <?php
}

function gwseq_render_cheval_global_id_dev_box($post) {
  $global_id = gwseq_get_cheval_global_id($post->ID);
  ?>
  <p class="description"><?php esc_html_e('Identifiant technique global de cette fiche (Global Horse ID). Généré automatiquement, jamais modifiable manuellement, jamais un identifiant de l’animal réel, jamais un secret. Visible ici uniquement parce que l’environnement est local/développement.', 'gws-core'); ?></p>
  <input type="text" class="widefat" readonly onclick="this.select();" value="<?php echo esc_attr($global_id !== '' ? $global_id : __('(sera généré au premier enregistrement réel)', 'gws-core')); ?>">
  <?php
}

function gwseq_save_cheval_meta($post_id) {
  if (!isset($_POST[GWSEQ_CHEVAL_NONCE_FIELD]) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[GWSEQ_CHEVAL_NONCE_FIELD])), GWSEQ_CHEVAL_NONCE_ACTION)) return;
  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
  if (function_exists('wp_is_post_revision') && wp_is_post_revision($post_id)) return;
  if (!current_user_can('edit_post', $post_id)) return;

  $identity = gwseq_sanitize_cheval_identity_input($_POST);
  update_post_meta($post_id, '_gwseq_sexe', $identity['sexe']);
  update_post_meta($post_id, '_gwseq_annee_naissance', $identity['annee_naissance']);
  update_post_meta($post_id, '_gwseq_robe', $identity['robe']);
  update_post_meta($post_id, '_gwseq_robe_autre', $identity['robe_autre']);
  update_post_meta($post_id, '_gwseq_race', $identity['race']);
  update_post_meta($post_id, '_gwseq_race_autre', $identity['race_autre']);
  update_post_meta($post_id, '_gwseq_taille_cm', $identity['taille_cm']);
  update_post_meta($post_id, '_gwseq_eleveur', $identity['eleveur']);
  update_post_meta($post_id, '_gwseq_proprietaire', $identity['proprietaire']);
  update_post_meta($post_id, '_gwseq_ueln', $identity['ueln']);
  update_post_meta($post_id, '_gwseq_sire', $identity['sire']);

  $commercial = gwseq_sanitize_cheval_commercial_input($_POST);
  update_post_meta($post_id, '_gwseq_statut_commercial', $commercial['statut_commercial']);
  update_post_meta($post_id, '_gwseq_prix_mode', $commercial['prix_mode']);
  update_post_meta($post_id, '_gwseq_prix_fixe', $commercial['prix_fixe']);
  update_post_meta($post_id, '_gwseq_prix_min', $commercial['prix_min']);
  update_post_meta($post_id, '_gwseq_prix_max', $commercial['prix_max']);
  update_post_meta($post_id, '_gwseq_prix_demande_libelle', $commercial['prix_demande_libelle']);
}
add_action('save_post_' . GWSEQ_CPT_CHEVAL, 'gwseq_save_cheval_meta');

/* -------------------------------------------------------------------------------------------
 * Liste d'administration : colonnes Catégories / Statut commercial / Prix / Ordre.
 * ----------------------------------------------------------------------------------------- */

function gwseq_cheval_admin_columns($columns) {
  $new = array();
  foreach ($columns as $key => $label) {
    $new[$key] = $label;
    if ($key === 'title') {
      $new['gwseq_categories'] = __('Catégories', 'gws-core');
      $new['gwseq_statut'] = __('Statut commercial', 'gws-core');
      $new['gwseq_prix'] = __('Prix', 'gws-core');
    }
  }
  $new['gwseq_ordre'] = __('Ordre', 'gws-core');
  return $new;
}
add_filter('manage_' . GWSEQ_CPT_CHEVAL . '_posts_columns', 'gwseq_cheval_admin_columns');

function gwseq_cheval_admin_column_content($column, $post_id) {
  if ($column === 'gwseq_categories') {
    $terms = get_the_terms($post_id, GWSEQ_TAX_CATEGORIE_CHEVAL);
    echo (is_array($terms) && $terms) ? esc_html(implode(', ', wp_list_pluck($terms, 'name'))) : '—';
  } elseif ($column === 'gwseq_statut') {
    $commercial = gwseq_get_cheval_commercial($post_id);
    $options = gwseq_cheval_statut_commercial_options();
    echo esc_html($options[$commercial['statut_commercial']] ?? '—');
  } elseif ($column === 'gwseq_prix') {
    $summary = gwseq_cheval_price_summary(gwseq_get_cheval_commercial($post_id), gwseq_get_currency());
    echo $summary !== '' ? esc_html($summary) : '—';
  } elseif ($column === 'gwseq_ordre') {
    echo (int) get_post_field('menu_order', $post_id);
  }
}
add_action('manage_' . GWSEQ_CPT_CHEVAL . '_posts_custom_column', 'gwseq_cheval_admin_column_content', 10, 2);

/* -------------------------------------------------------------------------------------------
 * Assets : uniquement sur l'écran d'édition d'une fiche cheval.
 * ----------------------------------------------------------------------------------------- */

function gwseq_enqueue_cheval_admin_assets($hook) {
  if (!in_array($hook, array('post.php', 'post-new.php'), true)) return;
  $screen = function_exists('get_current_screen') ? get_current_screen() : null;
  if (!$screen || $screen->post_type !== GWSEQ_CPT_CHEVAL) return;
  wp_enqueue_script('gwseq-cheval-admin', GWSEQ_MODULE_URL . 'assets/cheval-admin.js', array(), GWSEQ_MODULE_VERSION, true);
}
add_action('admin_enqueue_scripts', 'gwseq_enqueue_cheval_admin_assets');

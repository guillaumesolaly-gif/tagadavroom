<?php
/**
 * Prestation — relation au Groupe tarifaire et tarification (Étape 3).
 *
 * Nom = post_title, Description = post_content (natifs, déjà supportés depuis l'Étape 1 ; voir
 * includes/prestation-editor.php pour leur présentation admin). Ce fichier ajoute uniquement ce
 * que WordPress ne fournit pas nativement : la relation vers un Groupe tarifaire (référence
 * stable par ID de post, jamais par nom — un groupe peut être renommé sans jamais casser les
 * prestations qui lui sont rattachées) et la tarification (mode/prix/unité/visibilité), gérées
 * par un hand-rolled meta box comme pour toute relation ou champ hors du périmètre du générateur
 * minimal de gws-core (voir _boilerplate-cpt).
 *
 * Séparation logique/glissière WordPress : les fonctions gwseq_sanitize_*() ci-dessous sont des
 * fonctions pures (aucun accès à $_POST ni à la base) — testées directement dans
 * tests/gws-equestrian-prestations-logic-test.php avec des données représentant fidèlement la
 * forme réelle d'une soumission de formulaire, leçon retenue de l'Étape 2. gwseq_save_prestation_meta()
 * ne fait que les gardes de sécurité (nonce, capability, autosave, révision) puis leur délègue la
 * transformation réelle des données.
 *
 * Aucun risque de l'anomalie de regroupement de lignes rencontrée à l'Étape 2 ici : chaque champ
 * a un nom HTML fixe et unique (ex. "_gwseq_tarif_prix"), sans indexation par ligne — ce risque
 * est spécifique aux structures répétables (voir includes/repeater-field.php), pas aux champs
 * simples utilisés ici.
 *
 * Internationalisation : toutes les chaînes d'interface de ce fichier (libellés, options, textes
 * d'aide, résumé de tarif) passent par les fonctions de traduction WordPress avec le text domain
 * 'gws-core' (celui du plugin qui héberge ce module — gws-equestrian n'a pas de domaine propre,
 * voir gws-core.php pour le chargement des traductions). Les valeurs techniques stockées
 * ('unique', 'cheval_poney', 'devis', 'ht', 'ttc', clés d'unité...) restent des identifiants
 * stables, jamais traduits — seul leur libellé affiché l'est. Le contenu saisi par le
 * professionnel (nom, description, libellé "Sur demande" personnalisé, unité "Autre"
 * personnalisée) n'est JAMAIS passé dans une fonction de traduction : ce sont des données du
 * site, pas des chaînes du logiciel.
 */

if (!defined('ABSPATH')) exit;

const GWSEQ_PRESTATION_NONCE_ACTION = 'gwseq_save_prestation_meta';
const GWSEQ_PRESTATION_NONCE_FIELD = 'gwseq_save_prestation_meta_nonce';

/**
 * Modes de tarification retenus (§6-7 de la demande initiale) : un prix unique, deux prix
 * distincts (cheval/poney) pour une seule et même prestation, ou "Sur demande" (aucun prix
 * chiffré requis — 0 reste une vraie valeur numérique, jamais utilisé pour signifier l'absence de
 * prix).
 *
 * Valeur technique 'devis' conservée telle quelle (relecture post-1.6.1) pour éviter toute
 * migration inutile — seul le libellé affiché change ("Sur demande" plutôt que "Sur devis") ;
 * fonctionnellement, ce mode représente désormais "prix sur demande / non communiqué" au sens
 * large (le professionnel choisit lui-même la formulation exacte via le champ Libellé affiché,
 * voir gwseq_get_prestation_demande_libelle() plus bas), pas spécifiquement un devis.
 */
function gwseq_prestation_tarif_mode_options() {
  return array(
    'unique' => __('Prix unique', 'gws-core'),
    'cheval_poney' => __('Cheval / Poney (deux tarifs)', 'gws-core'),
    'devis' => __('Sur demande', 'gws-core'),
  );
}

/**
 * Unités courantes identifiées pour le secteur (pension, cours, reproduction) — liste fermée
 * volontairement limitée aux cas déjà démontrés (§9, complétée après relecture avec récolte/
 * colis/étalon pour les presets semence/expédition/spermogramme) ; "Autre" couvre le reste sans
 * construire de nomenclature supplémentaire.
 */
function gwseq_prestation_unit_options() {
  return array(
    'seance' => __('Séance', 'gws-core'),
    'heure' => __('Heure', 'gws-core'),
    'jour' => __('Jour', 'gws-core'),
    'semaine' => __('Semaine', 'gws-core'),
    'mois' => __('Mois', 'gws-core'),
    'forfait' => __('Forfait', 'gws-core'),
    'chaleur' => __('Chaleur', 'gws-core'),
    'saison' => __('Saison', 'gws-core'),
    'dose' => __('Dose', 'gws-core'),
    'paillette' => __('Paillette', 'gws-core'),
    'recolte' => __('Récolte', 'gws-core'),
    'colis' => __('Colis', 'gws-core'),
    'etalon' => __('Étalon', 'gws-core'),
    'autre' => __('Autre (préciser)', 'gws-core'),
  );
}

function gwseq_register_prestation_meta() {
  $string_meta = array(
    '_gwseq_tarif_mode',
    '_gwseq_tarif_unite',
    '_gwseq_tarif_unite_autre',
    '_gwseq_tarif_prix_public',
    '_gwseq_tarif_demande_libelle',
  );
  foreach ($string_meta as $key) {
    register_post_meta(GWSEQ_CPT_PRESTATION, $key, array('single' => true, 'type' => 'string', 'show_in_rest' => false));
  }
  foreach (array('_gwseq_tarif_prix', '_gwseq_tarif_prix_cheval', '_gwseq_tarif_prix_poney') as $key) {
    register_post_meta(GWSEQ_CPT_PRESTATION, $key, array('single' => true, 'type' => 'number', 'show_in_rest' => false));
  }
  register_post_meta(GWSEQ_CPT_PRESTATION, '_gwseq_prestation_groupe_id', array('single' => true, 'type' => 'integer', 'show_in_rest' => false));
}
add_action('init', 'gwseq_register_prestation_meta');

/* -------------------------------------------------------------------------------------------
 * Fonctions pures : sanitation et lecture, sans dépendance à $_POST. Testées indépendamment.
 * ----------------------------------------------------------------------------------------- */

/**
 * Un ID de groupe n'est conservé que s'il pointe réellement vers un Groupe tarifaire existant —
 * jamais confiance dans une valeur envoyée par le formulaire, même si elle provient du <select>
 * que nous générons nous-mêmes.
 */
function gwseq_sanitize_prestation_groupe_id($raw_value) {
  $groupe_id = absint($raw_value);
  if (!$groupe_id) return 0;
  return (get_post_type($groupe_id) === GWSEQ_CPT_GROUPE) ? $groupe_id : 0;
}

/**
 * Transforme un tableau à la forme de $_POST (clés = noms de champs HTML réels) en données de
 * tarification propres. Fonction pure : ne lit jamais $_POST elle-même, pour rester testable avec
 * des données construites explicitement — voir tests/gws-equestrian-prestations-logic-test.php.
 */
function gwseq_sanitize_prestation_tarification_input($raw) {
  $raw = is_array($raw) ? $raw : array();

  $mode = isset($raw['_gwseq_tarif_mode']) ? sanitize_key(wp_unslash($raw['_gwseq_tarif_mode'])) : '';
  if (!array_key_exists($mode, gwseq_prestation_tarif_mode_options())) $mode = 'unique';

  $unite = isset($raw['_gwseq_tarif_unite']) ? sanitize_key(wp_unslash($raw['_gwseq_tarif_unite'])) : '';
  if ($unite !== '' && !array_key_exists($unite, gwseq_prestation_unit_options())) $unite = '';

  return array(
    'mode' => $mode,
    'prix' => gws_core_field_sanitize('number', $raw['_gwseq_tarif_prix'] ?? ''),
    'prix_cheval' => gws_core_field_sanitize('number', $raw['_gwseq_tarif_prix_cheval'] ?? ''),
    'prix_poney' => gws_core_field_sanitize('number', $raw['_gwseq_tarif_prix_poney'] ?? ''),
    'unite' => $unite,
    'unite_autre' => gws_core_field_sanitize('text', $raw['_gwseq_tarif_unite_autre'] ?? ''),
    'prix_public' => gws_core_field_sanitize('checkbox', $raw['_gwseq_tarif_prix_public'] ?? ''),
    'demande_libelle' => gws_core_field_sanitize('text', $raw['_gwseq_tarif_demande_libelle'] ?? ''),
  );
}

/**
 * Avant tout enregistrement, l'absence totale de la meta signifie "jamais configuré" : le prix
 * est alors considéré affiché par défaut (comportement le plus courant), plutôt que de forcer une
 * case à cocher pour le cas normal. Une fois enregistrée explicitement (case cochée ou non), la
 * valeur stockée fait foi.
 */
function gwseq_prestation_is_prix_public($post_id) {
  if (!metadata_exists('post', $post_id, '_gwseq_tarif_prix_public')) return true;
  return get_post_meta($post_id, '_gwseq_tarif_prix_public', true) === '1';
}

/**
 * Libellé par défaut proposé pour le mode "Sur demande" — appliqué uniquement tant que la
 * prestation n'a jamais été enregistrée avec ce libellé (voir gwseq_get_prestation_demande_libelle()).
 * Chaîne du logiciel (traductible) : devient la valeur de départ proposée à l'utilisateur, qui
 * reste ensuite libre de la modifier — une fois enregistrée, c'est une donnée du site (§11).
 */
function gwseq_prestation_demande_libelle_default() {
  return __('Sur demande', 'gws-core');
}

/**
 * Distingue "jamais initialisé" de "volontairement enregistré vide" avec le même mécanisme déjà
 * utilisé pour _gwseq_tarif_prix_public (metadata_exists()) : tant qu'aucun enregistrement n'a
 * jamais écrit cette meta (prestation neuve, ou prestation créée avant l'introduction de ce champ
 * — compatibilité avec la valeur historique 'devis' sans aucune migration), le libellé par défaut
 * "Sur demande" s'applique. Dès le premier enregistrement du formulaire (le champ est toujours
 * présent dans le formulaire, simplement masqué par CSS si un autre mode est sélectionné), la
 * valeur réellement soumise fait foi — y compris une chaîne vide si l'utilisateur l'a
 * volontairement effacée : aucun texte tarifaire n'est alors affiché pour cette prestation.
 */
function gwseq_get_prestation_demande_libelle($post_id) {
  if (!metadata_exists('post', $post_id, '_gwseq_tarif_demande_libelle')) {
    return gwseq_prestation_demande_libelle_default();
  }
  return get_post_meta($post_id, '_gwseq_tarif_demande_libelle', true);
}

/**
 * Toutes les données de tarification d'une prestation, dans la forme attendue par
 * gwseq_prestation_price_summary() — un seul point de lecture, réutilisable par l'admin
 * aujourd'hui et par un futur rendu front/API sans dépendre de ce fichier au-delà de cette
 * fonction (voir §28 : données indépendantes du rendu).
 */
function gwseq_get_prestation_tarif($post_id) {
  $mode = get_post_meta($post_id, '_gwseq_tarif_mode', true);
  return array(
    'mode' => $mode !== '' ? $mode : 'unique',
    'prix' => get_post_meta($post_id, '_gwseq_tarif_prix', true),
    'prix_cheval' => get_post_meta($post_id, '_gwseq_tarif_prix_cheval', true),
    'prix_poney' => get_post_meta($post_id, '_gwseq_tarif_prix_poney', true),
    'unite' => get_post_meta($post_id, '_gwseq_tarif_unite', true),
    'unite_autre' => get_post_meta($post_id, '_gwseq_tarif_unite_autre', true),
    'prix_public' => gwseq_prestation_is_prix_public($post_id) ? '1' : '',
    'demande_libelle' => gwseq_get_prestation_demande_libelle($post_id),
  );
}

/**
 * Libellé d'affichage d'une unité, y compris le cas "Autre" avec son libellé personnalisé (donnée
 * du site, jamais traduite — seul le mot "Autre" de repli, s'il n'a pas été précisé, est une
 * chaîne du logiciel).
 */
function gwseq_prestation_unit_label($unite, $unite_autre) {
  if ($unite === '') return '';
  if ($unite === 'autre') return $unite_autre !== '' ? $unite_autre : __('Autre', 'gws-core');
  $options = gwseq_prestation_unit_options();
  return $options[$unite] ?? '';
}

/**
 * Résumé texte (jamais de HTML) d'un tarif, à partir des données structurées — utilisé par la
 * liste d'administration aujourd'hui ; conçu pour rester exploitable tel quel par un futur rendu
 * web/API sans jamais avoir à parser du HTML (§28). Fonction pure : $price_display_mode et
 * $currency_code sont passés explicitement (jamais lus depuis les réglages ici) pour rester
 * testable sans dépendre de get_option().
 *
 * Priorité d'affichage (mode global "Prix masqués" ajouté suite à la relecture de l'Étape 3) :
 * 1. Mode "Sur demande" : le libellé déjà résolu (voir gwseq_get_prestation_demande_libelle(),
 *    qui gère le fallback "jamais initialisé" -> "Sur demande") est affiché tel quel, ou rien si
 *    volontairement vide — dans les deux cas, INDÉPENDAMMENT du réglage global d'affichage des
 *    prix : ce n'est pas un montant chiffré à masquer, c'est une mention éditoriale que le
 *    professionnel contrôle lui-même (voir §5 de la demande : "Prix masqués" concerne les
 *    montants chiffrés, jamais ce libellé).
 * 2. Prix masqués globalement (réglage du site) : aucun montant chiffré n'est jamais rendu,
 *    quelle que soit la case individuelle de la prestation.
 * 3. Sinon, case individuelle "Afficher ce tarif publiquement" décochée : cette seule prestation
 *    ne montre pas son tarif.
 * 4. Sinon, rendu normal HT/TTC selon le réglage global.
 * Aucun de ces cas ne modifie ni ne supprime les montants/libellés stockés : c'est uniquement une
 * règle de présentation, réversible à tout moment.
 *
 * i18n (bug signalé en recette avec GBP) : le suffixe HT/TTC est désormais une chaîne traductible,
 * indépendante de la devise — "HT"/"TTC" ne sont que la représentation française des valeurs
 * techniques stables 'ht'/'ttc' ; un site traduit en anglais peut leur substituer "excl. VAT"/
 * "incl. VAT" via une traduction du plugin, sans qu'aucun code ne déduise quoi que ce soit de la
 * devise choisie (GBP ne signifie pas "anglais", EUR ne signifie pas "français" — §10 de la
 * demande). Aucun moteur fiscal : ce ne sont que des libellés.
 */
function gwseq_prestation_price_summary($tarif, $price_display_mode, $currency_code = 'EUR') {
  $mode = $tarif['mode'] ?? 'unique';
  if ($mode === 'devis') return $tarif['demande_libelle'] ?? '';

  if ($price_display_mode === 'hidden') return __('Tarif non affiché publiquement', 'gws-core');
  if (($tarif['prix_public'] ?? '') !== '1') return __('Tarif non affiché publiquement', 'gws-core');

  $currency_symbol = gwseq_currency_symbol($currency_code);
  $suffix = ($price_display_mode === 'ht') ? ' ' . __('HT', 'gws-core') : ' ' . __('TTC', 'gws-core');
  $unit_label = gwseq_prestation_unit_label($tarif['unite'] ?? '', $tarif['unite_autre'] ?? '');
  $unit_suffix = $unit_label !== '' ? ' / ' . $unit_label : '';

  if ($mode === 'cheval_poney') {
    $parts = array();
    if (($tarif['prix_cheval'] ?? '') !== '') $parts[] = __('Cheval', 'gws-core') . ' ' . gwseq_format_price_number($tarif['prix_cheval']) . ' ' . $currency_symbol;
    if (($tarif['prix_poney'] ?? '') !== '') $parts[] = __('Poney', 'gws-core') . ' ' . gwseq_format_price_number($tarif['prix_poney']) . ' ' . $currency_symbol;
    return $parts ? implode(' · ', $parts) . $suffix . $unit_suffix : '';
  }

  if (($tarif['prix'] ?? '') === '') return '';
  return gwseq_format_price_number($tarif['prix']) . ' ' . $currency_symbol . $suffix . $unit_suffix;
}

/* -------------------------------------------------------------------------------------------
 * Meta boxes et sauvegarde (glue WordPress).
 * ----------------------------------------------------------------------------------------- */

function gwseq_add_prestation_meta_boxes() {
  add_meta_box('gwseq-prestation-groupe', __('Groupe tarifaire', 'gws-core'), 'gwseq_render_prestation_groupe_box', GWSEQ_CPT_PRESTATION, 'side', 'high');
  add_meta_box('gwseq-prestation-tarification', __('Tarification', 'gws-core'), 'gwseq_render_prestation_tarification_box', GWSEQ_CPT_PRESTATION, 'normal', 'default');
}
add_action('add_meta_boxes_' . GWSEQ_CPT_PRESTATION, 'gwseq_add_prestation_meta_boxes');

/**
 * Tous les Groupes tarifaires disponibles, dans l'ordre d'affichage — SEULE requête de cette liste
 * (jamais dupliquée) : réutilisée à la fois par le sélecteur de la fiche Prestation ci-dessous et
 * par le filtre de la liste d'administration (voir plus bas), pour ne jamais faire dériver deux
 * énumérations indépendantes du même ensemble de groupes.
 */
function gwseq_get_prestation_groupe_choices() {
  return get_posts(array(
    'post_type' => GWSEQ_CPT_GROUPE,
    'post_status' => array('publish', 'draft', 'pending', 'private'),
    'numberposts' => -1,
    'orderby' => 'menu_order title',
    'order' => 'ASC',
  ));
}

function gwseq_render_prestation_groupe_box($post) {
  wp_nonce_field(GWSEQ_PRESTATION_NONCE_ACTION, GWSEQ_PRESTATION_NONCE_FIELD);
  $current = (int) get_post_meta($post->ID, '_gwseq_prestation_groupe_id', true);
  $groupes = gwseq_get_prestation_groupe_choices();
  echo '<label class="screen-reader-text" for="gwseq-prestation-groupe-id">' . esc_html__('Groupe tarifaire', 'gws-core') . '</label>';
  echo '<select class="widefat" id="gwseq-prestation-groupe-id" name="_gwseq_prestation_groupe_id">';
  echo '<option value="0">' . esc_html__('— Aucun groupe —', 'gws-core') . '</option>';
  foreach ($groupes as $groupe) {
    // get_the_title() est une donnée saisie par le professionnel (nom du groupe) : jamais passée
    // dans une fonction de traduction, seulement échappée.
    echo '<option value="' . esc_attr($groupe->ID) . '"' . selected($current, $groupe->ID, false) . '>' . esc_html(get_the_title($groupe)) . '</option>';
  }
  echo '</select>';
  if (!$groupes) {
    echo '<p class="description">' . sprintf(
      /* translators: %s: lien "Créer un groupe tarifaire" */
      esc_html__('Aucun groupe tarifaire n’existe encore. %s.', 'gws-core'),
      '<a href="' . esc_url(admin_url('post-new.php?post_type=' . GWSEQ_CPT_GROUPE)) . '">' . esc_html__('Créer un groupe tarifaire', 'gws-core') . '</a>'
    ) . '</p>';
  }
}

function gwseq_render_prestation_tarification_box($post) {
  $tarif = gwseq_get_prestation_tarif($post->ID);

  // Préremplissage depuis un modèle (includes/presets.php) : uniquement si l'unité n'a jamais
  // encore été enregistrée pour cette prestation — jamais pour écraser une valeur existante.
  if (!metadata_exists('post', $post->ID, '_gwseq_tarif_unite') && function_exists('gwseq_get_requested_preset_defaults')) {
    $preset = gwseq_get_requested_preset_defaults();
    if ($preset && !empty($preset['unite'])) $tarif['unite'] = $preset['unite'];
  }

  wp_nonce_field(GWSEQ_PRESTATION_NONCE_ACTION, GWSEQ_PRESTATION_NONCE_FIELD);
  ?>
  <p>
    <label for="gwseq-tarif-mode"><strong><?php esc_html_e('Mode de tarification', 'gws-core'); ?></strong></label><br>
    <select class="widefat" id="gwseq-tarif-mode" name="_gwseq_tarif_mode">
      <?php foreach (gwseq_prestation_tarif_mode_options() as $key => $label) : ?>
        <option value="<?php echo esc_attr($key); ?>" <?php selected($tarif['mode'], $key); ?>><?php echo esc_html($label); ?></option>
      <?php endforeach; ?>
    </select>
  </p>
  <p data-gwseq-tarif-fields="unique" style="<?php echo $tarif['mode'] === 'unique' ? '' : 'display:none;'; ?>">
    <label for="gwseq-tarif-prix"><strong><?php esc_html_e('Prix', 'gws-core'); ?></strong></label><br>
    <input class="regular-text" type="number" step="any" id="gwseq-tarif-prix" name="_gwseq_tarif_prix" value="<?php echo esc_attr($tarif['prix']); ?>">
  </p>
  <div data-gwseq-tarif-fields="cheval_poney" style="<?php echo $tarif['mode'] === 'cheval_poney' ? '' : 'display:none;'; ?>">
    <p>
      <label for="gwseq-tarif-prix-cheval"><strong><?php esc_html_e('Prix cheval', 'gws-core'); ?></strong></label><br>
      <input class="regular-text" type="number" step="any" id="gwseq-tarif-prix-cheval" name="_gwseq_tarif_prix_cheval" value="<?php echo esc_attr($tarif['prix_cheval']); ?>">
    </p>
    <p>
      <label for="gwseq-tarif-prix-poney"><strong><?php esc_html_e('Prix poney', 'gws-core'); ?></strong></label><br>
      <input class="regular-text" type="number" step="any" id="gwseq-tarif-prix-poney" name="_gwseq_tarif_prix_poney" value="<?php echo esc_attr($tarif['prix_poney']); ?>">
    </p>
  </div>
  <p data-gwseq-tarif-fields="unique cheval_poney" style="<?php echo $tarif['mode'] === 'devis' ? 'display:none;' : ''; ?>">
    <label><input type="checkbox" id="gwseq-tarif-prix-public" name="_gwseq_tarif_prix_public" value="1" <?php checked($tarif['prix_public'], '1'); ?>> <?php esc_html_e('Afficher ce tarif publiquement', 'gws-core'); ?></label><br>
    <span class="description"><?php esc_html_e('Si décoché, le montant reste enregistré pour votre usage interne mais n’apparaît pas sur le site (le visiteur voit alors « Nous consulter »).', 'gws-core'); ?></span>
  </p>
  <p data-gwseq-tarif-fields="devis" style="<?php echo $tarif['mode'] === 'devis' ? '' : 'display:none;'; ?>">
    <label for="gwseq-tarif-demande-libelle"><strong><?php esc_html_e('Libellé affiché', 'gws-core'); ?></strong></label><br>
    <input class="regular-text" type="text" id="gwseq-tarif-demande-libelle" name="_gwseq_tarif_demande_libelle" value="<?php echo esc_attr($tarif['demande_libelle']); ?>" placeholder="<?php esc_attr_e('Ex. Sur demande, Sur devis, Nous contacter...', 'gws-core'); ?>">
    <br><span class="description"><?php esc_html_e('Affiché tel quel à la place d’un prix chiffré. Laisser vide pour n’afficher aucune mention tarifaire pour cette prestation — ce champ n’est jamais concerné par le réglage global « Prix masqués », qui ne porte que sur les montants chiffrés.', 'gws-core'); ?></span>
  </p>
  <p>
    <label for="gwseq-tarif-unite"><strong><?php esc_html_e('Unité', 'gws-core'); ?></strong></label><br>
    <select class="widefat" id="gwseq-tarif-unite" name="_gwseq_tarif_unite">
      <option value=""><?php esc_html_e('— Aucune —', 'gws-core'); ?></option>
      <?php foreach (gwseq_prestation_unit_options() as $key => $label) : ?>
        <option value="<?php echo esc_attr($key); ?>" <?php selected($tarif['unite'], $key); ?>><?php echo esc_html($label); ?></option>
      <?php endforeach; ?>
    </select>
  </p>
  <p data-gwseq-tarif-fields="unite-autre" style="<?php echo $tarif['unite'] === 'autre' ? '' : 'display:none;'; ?>">
    <label for="gwseq-tarif-unite-autre"><strong><?php esc_html_e('Préciser l’unité', 'gws-core'); ?></strong></label><br>
    <input class="widefat" type="text" id="gwseq-tarif-unite-autre" name="_gwseq_tarif_unite_autre" value="<?php echo esc_attr($tarif['unite_autre']); ?>" placeholder="<?php esc_attr_e('Ex. par cycle, par concours...', 'gws-core'); ?>">
  </p>
  <?php
}

function gwseq_save_prestation_meta($post_id) {
  if (!isset($_POST[GWSEQ_PRESTATION_NONCE_FIELD]) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[GWSEQ_PRESTATION_NONCE_FIELD])), GWSEQ_PRESTATION_NONCE_ACTION)) return;
  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
  if (function_exists('wp_is_post_revision') && wp_is_post_revision($post_id)) return;
  if (!current_user_can('edit_post', $post_id)) return;

  update_post_meta($post_id, '_gwseq_prestation_groupe_id', gwseq_sanitize_prestation_groupe_id($_POST['_gwseq_prestation_groupe_id'] ?? 0));

  $tarif = gwseq_sanitize_prestation_tarification_input($_POST);
  update_post_meta($post_id, '_gwseq_tarif_mode', $tarif['mode']);
  update_post_meta($post_id, '_gwseq_tarif_prix', $tarif['prix']);
  update_post_meta($post_id, '_gwseq_tarif_prix_cheval', $tarif['prix_cheval']);
  update_post_meta($post_id, '_gwseq_tarif_prix_poney', $tarif['prix_poney']);
  update_post_meta($post_id, '_gwseq_tarif_unite', $tarif['unite']);
  update_post_meta($post_id, '_gwseq_tarif_unite_autre', $tarif['unite_autre']);
  update_post_meta($post_id, '_gwseq_tarif_prix_public', $tarif['prix_public']);
  update_post_meta($post_id, '_gwseq_tarif_demande_libelle', $tarif['demande_libelle']);
}
add_action('save_post_' . GWSEQ_CPT_PRESTATION, 'gwseq_save_prestation_meta');

/* -------------------------------------------------------------------------------------------
 * Liste d'administration : colonnes Groupe tarifaire / Tarif / Ordre.
 * ----------------------------------------------------------------------------------------- */

function gwseq_prestation_admin_columns($columns) {
  $new = array();
  foreach ($columns as $key => $label) {
    $new[$key] = $label;
    if ($key === 'title') {
      $new['gwseq_groupe'] = __('Groupe tarifaire', 'gws-core');
      $new['gwseq_tarif'] = __('Tarif', 'gws-core');
    }
  }
  $new['gwseq_ordre'] = __('Ordre', 'gws-core');
  return $new;
}
add_filter('manage_' . GWSEQ_CPT_PRESTATION . '_posts_columns', 'gwseq_prestation_admin_columns');

function gwseq_prestation_admin_column_content($column, $post_id) {
  if ($column === 'gwseq_groupe') {
    $groupe_id = (int) get_post_meta($post_id, '_gwseq_prestation_groupe_id', true);
    echo $groupe_id ? esc_html(get_the_title($groupe_id)) : '—';
  } elseif ($column === 'gwseq_tarif') {
    $summary = gwseq_prestation_price_summary(gwseq_get_prestation_tarif($post_id), gwseq_get_price_display_mode(), gwseq_get_currency());
    echo $summary !== '' ? esc_html($summary) : '—';
  } elseif ($column === 'gwseq_ordre') {
    echo (int) get_post_field('menu_order', $post_id);
  }
}
add_action('manage_' . GWSEQ_CPT_PRESTATION . '_posts_custom_column', 'gwseq_prestation_admin_column_content', 10, 2);

/* -------------------------------------------------------------------------------------------
 * Filtre de la liste d'administration par Groupe tarifaire (demande complémentaire post-recette
 * Équipe) : réutilise EXACTEMENT la relation déjà en place (_gwseq_prestation_groupe_id,
 * gwseq_get_prestation_groupe_choices() ci-dessus) — aucune deuxième logique de classement,
 * aucune donnée ni modèle modifiés. Combinable avec la recherche WordPress native et avec la
 * pagination (les liens de pagination de WP_List_Table conservent nativement les paramètres
 * `$_GET` déjà présents, sans code supplémentaire ici — même mécanisme déjà utilisé pour les
 * filtres de la liste Cheval).
 * ----------------------------------------------------------------------------------------- */

function gwseq_render_prestation_admin_list_filter($post_type) {
  if ($post_type !== GWSEQ_CPT_PRESTATION) return;
  $selected = isset($_GET['gwseq_filter_groupe']) ? sanitize_text_field(wp_unslash($_GET['gwseq_filter_groupe'])) : '';
  $groupes = gwseq_get_prestation_groupe_choices();
  ?>
  <select name="gwseq_filter_groupe">
    <option value=""><?php esc_html_e('Tous les groupes tarifaires', 'gws-core'); ?></option>
    <option value="0" <?php selected($selected, '0'); ?>><?php esc_html_e('Sans groupe tarifaire', 'gws-core'); ?></option>
    <?php foreach ($groupes as $groupe) : ?>
      <option value="<?php echo esc_attr($groupe->ID); ?>" <?php selected($selected, (string) $groupe->ID); ?>><?php echo esc_html(get_the_title($groupe)); ?></option>
    <?php endforeach; ?>
  </select>
  <?php
}
add_action('restrict_manage_posts', 'gwseq_render_prestation_admin_list_filter');

/**
 * Application réelle du filtre à la requête principale de la liste (jamais un second WP_Query,
 * jamais un rebuild de la liste — voir gwseq_apply_cheval_admin_list_filters() pour le même
 * principe déjà appliqué à la liste Chevaux). "Sans groupe tarifaire" couvre PROPREMENT les deux
 * cas réels : une prestation dont la meta vaut explicitement 0 (relation retirée volontairement)
 * ET une prestation créée avant l'existence de cette relation, dont la meta n'existe simplement
 * pas encore (`NOT EXISTS`) — sans ce second cas, ces prestations plus anciennes resteraient
 * invisibles du filtre alors qu'elles n'ont, de fait, aucun groupe.
 */
function gwseq_apply_prestation_admin_list_filter($query) {
  if (!is_admin() || !$query->is_main_query()) return;
  global $pagenow;
  if ($pagenow !== 'edit.php' || $query->get('post_type') !== GWSEQ_CPT_PRESTATION) return;
  if (!isset($_GET['gwseq_filter_groupe']) || $_GET['gwseq_filter_groupe'] === '') return;

  $raw = sanitize_text_field(wp_unslash($_GET['gwseq_filter_groupe']));
  if (!is_numeric($raw)) return;
  $groupe_id = (int) $raw;

  $meta_query = $query->get('meta_query');
  $meta_query = is_array($meta_query) ? $meta_query : array();

  if ($groupe_id === 0) {
    $meta_query[] = array(
      'relation' => 'OR',
      array('key' => '_gwseq_prestation_groupe_id', 'value' => 0, 'compare' => '=', 'type' => 'NUMERIC'),
      array('key' => '_gwseq_prestation_groupe_id', 'compare' => 'NOT EXISTS'),
    );
  } else {
    $meta_query[] = array('key' => '_gwseq_prestation_groupe_id', 'value' => $groupe_id, 'compare' => '=', 'type' => 'NUMERIC');
  }

  $query->set('meta_query', $meta_query);
}
add_action('pre_get_posts', 'gwseq_apply_prestation_admin_list_filter');

/* -------------------------------------------------------------------------------------------
 * Assets : uniquement sur l'écran d'édition d'une Prestation (affichage conditionnel des champs
 * de tarification selon le mode/l'unité choisis — solution locale, pas un moteur de champs
 * conditionnels générique).
 * ----------------------------------------------------------------------------------------- */

function gwseq_enqueue_prestation_admin_assets($hook) {
  if (!in_array($hook, array('post.php', 'post-new.php'), true)) return;
  $screen = function_exists('get_current_screen') ? get_current_screen() : null;
  if (!$screen || $screen->post_type !== GWSEQ_CPT_PRESTATION) return;
  wp_enqueue_script('gwseq-prestation-admin', GWSEQ_MODULE_URL . 'assets/prestation-admin.js', array(), GWSEQ_MODULE_VERSION, true);
}
add_action('admin_enqueue_scripts', 'gwseq_enqueue_prestation_admin_assets');

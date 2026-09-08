<?php
/**
 * Cheval — présentation éditoriale et informations complémentaires (Étape 6, §7-8 de la demande).
 *
 * Champs entièrement facultatifs, séparés des données STRUCTURÉES (identité, indices, pedigree,
 * médias) — un texte éditorial n'est jamais analysé/parsé pour en déduire une donnée structurée,
 * et réciproquement (§7 : ne jamais reconstruire le pedigree à partir du commentaire "Origines",
 * ni l'inverse).
 *
 * DEUX AMBIGUÏTÉS EXPLICITEMENT LEVÉES PAR DES NOMS DE META SANS ÉQUIVOQUE (§7) :
 * - `_gwseq_commentaire_production` (Production éditoriale, texte libre du professionnel sur la
 *   qualité/les résultats de la production) est une meta TOTALEMENT DISTINCTE de la Production
 *   CALCULÉE (gwseq_get_horse_offspring(), includes/cheval-pedigree.php — Étape 5), qui reste une
 *   donnée relationnelle dérivée des fiches Cheval, jamais stockée, jamais éditable ici.
 * - `_gwseq_origines_commentaire` (commentaire éditorial sur l'intérêt d'une lignée) est
 *   totalement distinct du pedigree STRUCTURÉ (`_gwseq_pere_*`/`_gwseq_mere_*`,
 *   includes/cheval-pedigree.php — Étape 5) : ce fichier ne lit ni n'écrit jamais ces meta, et
 *   inversement cheval-pedigree.php ne lit ni n'écrit jamais `_gwseq_origines_commentaire`.
 *
 * "Conseils de croisement" (§7) : disponible pour TOUS les chevaux, jamais conditionné au sexe ou
 * à une catégorie — cohérent avec le principe général de l'Étape 6 (§1) : une seule entité Cheval,
 * tous les champs disponibles pour tous, l'utilisateur choisit ce qui est pertinent.
 *
 * "Ostéo-articulaire" (§8) : texte libre uniquement, volontairement PAS un dossier vétérinaire —
 * aucun champ structuré de soins/traitements/ordonnances/radios n'est ajouté ici, et ne doit
 * jamais l'être dans ce fichier sans une décision explicite revalidant ce périmètre.
 *
 * RÈGLE MÉTIER UNIQUE ET PROGRAMMATIQUE (§11, même architecture que le pedigree — Étape 5) :
 * gwseq_set_cheval_editorial() est une fonction métier pure, jamais couplée à $_POST ni à un
 * nonce/capability — réutilisable telle quelle par un futur importeur CSV/XLSX, une duplication de
 * fiche, une API, ou une synchronisation GWS Network. Le formulaire d'édition
 * (gwseq_save_cheval_editorial_meta()) n'est qu'UN client parmi d'autres possibles.
 *
 * LOT 2A — STRUCTURATION DES CONTENUS COMMERCIAUX (préparation Fiche PDF/Catalogue, suite de
 * l'audit précédent) :
 *
 * 1. « Points forts » (texte libre, `_gwseq_points_forts`) devient « Qualités »
 *    (`_gwseq_qualites`) : une liste ORDONNÉE d'au plus 5 courtes chaînes (25 caractères max
 *    chacune), saisie libre, sans taxonomie ni liste prédéfinie — le vocabulaire reste celui du
 *    professionnel. Retirée de gwseq_cheval_editorial_field_map() (ce n'est plus un simple champ
 *    texte parmi les autres) : `_gwseq_points_forts` n'est donc plus jamais lue ni écrite par ce
 *    fichier à partir de cette version. AUCUNE migration de son contenu vers `_gwseq_qualites`
 *    n'est effectuée (les données de recette actuelles ne sont que des données de test — voir le
 *    CR de ce lot) : toute valeur déjà enregistrée dans `_gwseq_points_forts` reste intégralement
 *    présente en base de données (jamais supprimée par ce fichier), simplement orpheline de toute
 *    interface — récupérable uniquement par accès direct à la base ou WP-CLI si un jour nécessaire.
 * 2. Nouvelle donnée structurée « Faits marquants » (`_gwseq_faits_marquants`) : liste ORDONNÉE
 *    d'au plus 3 courtes chaînes (80 caractères max chacune), saisie libre, aucune obligatoire.
 *    Argument commercial/éditorial choisi par l'utilisateur — JAMAIS une génération automatique,
 *    une IA, ou une déduction depuis une autre donnée (résultats, indices, pedigree...). Distincte
 *    de « Résultats / Performances » (narratif libre, non plafonné, inchangé dans ce lot) et des
 *    indices sportifs structurés (ISO/ICC/IDR, inchangés).
 * 3. Six champs éditoriaux existants reçoivent une limite de longueur (voir
 *    gwseq_cheval_editorial_field_max_length() ci-dessous) : Accroche commerciale (180), Présentation
 *    (1200), Potentiel (500), Commentaire production (600), Conseils de croisement (600),
 *    Commentaire origines (600). Résultats, Conditions de vente et Ostéo-articulaire restent
 *    volontairement SANS limite (hors périmètre de ce lot).
 *
 * ARCHITECTURE DE VALIDATION (§3 de la demande — comportement du save_post existant vérifié avant
 * d'introduire quoi que ce soit) : gwseq_save_cheval_meta()/_indices_meta()/_pedigree_meta() (etc.,
 * cheval-fields.php/cheval-indices.php/cheval-pedigree.php...) sont déjà, chacune, un callback
 * INDÉPENDANT accroché à `save_post_gwseq_cheval` — une erreur dans ce fichier ne peut donc, par
 * construction, jamais affecter l'identité, les indices, le pedigree, les médias, le commercial ou
 * la diffusion : ce sont des hooks totalement séparés. À L'INTÉRIEUR même de ce fichier,
 * gwseq_set_cheval_editorial() persiste DÉJÀ chaque champ indépendamment (une boucle avec un
 * update_post_meta() par champ, jamais un blob unique) — cette granularité préexistante est
 * réutilisée telle quelle, jamais réinventée. WordPress (écran classique, sans REST) n'offre AUCUN
 * mécanisme natif pour bloquer proprement l'enregistrement du POST tout en affichant une erreur
 * bloquante (contrairement à l'éditeur par blocs/REST) : tenter de bloquer `save_post` lui-même
 * (ex. via `wp_insert_post_data`) empêcherait ÉGALEMENT le titre/statut de s'enregistrer et
 * n'empêcherait PAS les autres callbacks `save_post_gwseq_cheval` indépendants de s'exécuter quand
 * même — un comportement plus risqué et plus déroutant que le problème à résoudre. Solution retenue
 * (la moins destructive) : quand un champ dépasse sa limite (texte trop long, ou trop d'éléments
 * pour Qualités/Faits marquants), CE SEUL champ n'est PAS écrit — sa valeur précédemment enregistrée
 * reste strictement inchangée, jamais tronquée — pendant que tous les AUTRES champs (de cette boîte
 * ET des autres boîtes) s'enregistrent normalement. Un message explicite, listant précisément
 * le(s) champ(s) rejeté(s) et pourquoi, est affiché sur l'écran après redirection (voir
 * gwseq_cheval_editorial_rejected_admin_notice() plus bas) via le filtre natif WordPress
 * `redirect_post_location` — aucun transient, aucune écriture en base superflue : l'information
 * transite uniquement le temps de CETTE requête (state statique de fonction, jamais persistée).
 */

if (!defined('ABSPATH')) exit;

const GWSEQ_CHEVAL_QUALITES_MAX_ITEMS = 5;
const GWSEQ_CHEVAL_QUALITES_MAX_LENGTH = 25;
const GWSEQ_CHEVAL_FAITS_MARQUANTS_MAX_ITEMS = 3;
const GWSEQ_CHEVAL_FAITS_MARQUANTS_MAX_LENGTH = 80;

/* -------------------------------------------------------------------------------------------
 * Enregistrement des meta.
 * ----------------------------------------------------------------------------------------- */

/**
 * Association clé logique => nom de meta, SEULE source de vérité pour la liste des champs
 * éditoriaux TEXTE LIBRE — utilisée par l'enregistrement des meta, la sanitation, la lecture, le
 * rendu ET la sauvegarde : ajouter un futur champ éditorial se limite à une ligne ici plus son
 * rendu, jamais une modification dispersée dans plusieurs listes parallèles. « Qualités » et
 * « Faits marquants » (listes structurées, Lot 2A) n'y figurent volontairement PAS : forme de
 * donnée différente (tableau ordonné, pas une chaîne unique), elles ont leurs propres fonctions
 * dédiées ci-dessous — voir gwseq_get_cheval_qualites()/gwseq_get_cheval_faits_marquants().
 */
function gwseq_cheval_editorial_field_map() {
  return array(
    'accroche_commerciale' => '_gwseq_accroche_commerciale',
    'presentation' => '_gwseq_presentation',
    'potentiel' => '_gwseq_potentiel',
    'resultats' => '_gwseq_resultats',
    'origines_commentaire' => '_gwseq_origines_commentaire',
    'commentaire_production' => '_gwseq_commentaire_production',
    'conditions_vente' => '_gwseq_conditions_vente',
    'conseils_croisement' => '_gwseq_conseils_croisement',
    'osteo_articulaire' => '_gwseq_osteo_articulaire',
  );
}

/**
 * Limites de longueur (§3 du Lot 2A) — SEULE source de vérité, lue à la fois par la validation
 * serveur (gwseq_set_cheval_editorial() ci-dessous) et par le rendu du formulaire (attribut HTML
 * `maxlength` + compteur de caractères, voir gwseq_render_cheval_presentation_box()) : jamais un
 * nombre dupliqué en dur à un second endroit. Un champ absent de ce tableau (Résultats, Conditions
 * de vente, Ostéo-articulaire) reste volontairement SANS limite.
 */
function gwseq_cheval_editorial_field_max_length() {
  return array(
    'accroche_commerciale' => 180,
    'presentation' => 1200,
    'potentiel' => 500,
    'commentaire_production' => 600,
    'conseils_croisement' => 600,
    'origines_commentaire' => 600,
  );
}

function gwseq_register_cheval_editorial_meta() {
  foreach (gwseq_cheval_editorial_field_map() as $meta_key) {
    register_post_meta(GWSEQ_CPT_CHEVAL, $meta_key, array('single' => true, 'type' => 'string', 'show_in_rest' => false));
  }
  register_post_meta(GWSEQ_CPT_CHEVAL, '_gwseq_qualites', array('single' => true, 'type' => 'array', 'show_in_rest' => false));
  register_post_meta(GWSEQ_CPT_CHEVAL, '_gwseq_faits_marquants', array('single' => true, 'type' => 'array', 'show_in_rest' => false));
}
add_action('init', 'gwseq_register_cheval_editorial_meta');

/* -------------------------------------------------------------------------------------------
 * Fonctions pures : sanitation, lecture, persistance. Aucune dépendance à $_POST.
 * ----------------------------------------------------------------------------------------- */

/**
 * Transforme un tableau à la forme de $_POST (ou de tout appel programmatique) en données
 * éditoriales propres. Fonction pure — chaque champ est sanitisé et accepté indépendamment des
 * autres (un seul champ renseigné, tous les autres vides, est une saisie parfaitement valide).
 * sanitize_textarea_field() (via gws_core_field_sanitize('textarea', ...)) préserve les sauts de
 * ligne tout en retirant les balises HTML — un texte libre, jamais un contenu riche.
 */
function gwseq_sanitize_cheval_editorial_input($raw) {
  $raw = is_array($raw) ? $raw : array();
  $clean = array();
  foreach (gwseq_cheval_editorial_field_map() as $field_key => $meta_key) {
    $clean[$field_key] = gws_core_field_sanitize('textarea', $raw[$meta_key] ?? '');
  }
  return $clean;
}

/**
 * Persiste l'ensemble des champs éditoriaux — fonction métier réutilisable, jamais couplée à
 * $_POST ni à un nonce (§11). Chaque champ est écrit indépendamment ; un champ absent de $raw est
 * traité comme vide (jamais une erreur), permettant à un futur import partiel de ne fournir que
 * les champs qu'il connaît sans effacer les autres de façon inattendue n'est PAS garanti par
 * cette fonction — comme pour l'identité/la commercialisation (cheval-fields.php), un appel
 * complet est attendu ; un appelant souhaitant ne modifier qu'un seul champ doit d'abord lire
 * gwseq_get_cheval_editorial() et ne changer que la clé voulue avant de rappeler cette fonction.
 */
/**
 * Sanitise une liste ORDONNÉE de courtes chaînes libres (Qualités, Faits marquants — §1-2 du
 * Lot 2A). Fonction pure. Chaque entrée : espaces superflus retirés, HTML/balises retiré
 * (gws_core_field_sanitize('text', ...)) ; une entrée devenue vide après sanitation est retirée
 * SANS compter dans le total (jamais considérée comme "un élément trop long"), l'ordre de saisie
 * des entrées restantes est conservé tel quel (aucun tri).
 *
 * NE TRONQUE JAMAIS SILENCIEUSEMENT (même principe que les champs texte à limite fixe ci-dessous,
 * appliqué ici par cohérence à cette liste bien que non demandé explicitement pour elle) : dès
 * qu'UNE SEULE entrée dépasse $max_length, OU que le nombre d'entrées non vides dépasse $max_items,
 * la fonction retourne 'rejected' => true et 'values' => null — JAMAIS une liste partiellement
 * acceptée (ex. les 5 premières qualités gardées, la 6e silencieusement perdue) ni une chaîne
 * coupée à $max_length caractères. À l'appelant (gwseq_set_cheval_editorial() ci-dessous) de ne
 * PAS persister ce champ dans ce cas — la valeur déjà enregistrée reste intacte.
 */
function gwseq_sanitize_cheval_text_list($raw, $max_items, $max_length) {
  $raw = is_array($raw) ? $raw : array();
  $values = array();
  foreach ($raw as $item) {
    if (is_array($item)) continue; // entrée malformée : jamais une erreur, simplement ignorée
    $value = trim(gws_core_field_sanitize('text', $item));
    if ($value === '') continue;
    $length = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
    if ($length > $max_length) {
      return array('values' => null, 'rejected' => true, 'reason' => 'too_long');
    }
    $values[] = $value;
  }
  if (count($values) > $max_items) {
    return array('values' => null, 'rejected' => true, 'reason' => 'too_many');
  }
  return array('values' => array_values($values), 'rejected' => false, 'reason' => '');
}

/**
 * Persiste l'ensemble des champs éditoriaux — fonction métier réutilisable, jamais couplée à
 * $_POST ni à un nonce (§11). Chaque champ est écrit INDÉPENDAMMENT (déjà le cas avant le Lot 2A) ;
 * un champ absent de $raw est traité comme vide (jamais une erreur).
 *
 * VALIDATION DE LONGUEUR (§3 du Lot 2A — voir le docblock de fichier pour l'analyse complète de
 * l'architecture retenue) : un champ dont le contenu sanitisé dépasse sa limite
 * (gwseq_cheval_editorial_field_max_length()), ou une liste Qualités/Faits marquants rejetée par
 * gwseq_sanitize_cheval_text_list() ci-dessus, N'EST PAS écrit — sa valeur précédente reste
 * strictement inchangée, jamais tronquée. Tous les AUTRES champs de cet appel continuent d'être
 * enregistrés normalement, qu'ils soient valides ou absents. Retourne un tableau
 * `field_key => raison` pour chaque champ rejeté ('too_long' pour les six champs à limite fixe et
 * pour un élément individuel trop long de Qualités/Faits marquants, 'too_many' pour un nombre
 * d'éléments excessif) — tableau VIDE si tout a été enregistré sans réserve. Un appelant
 * programmatique (futur import...) qui ignore cette valeur de retour ne perd rien : c'est
 * exactement le même comportement de rejet ciblé que depuis l'écran d'administration.
 */
function gwseq_set_cheval_editorial($cheval_id, $raw) {
  $cheval_id = (int) $cheval_id;
  if (!$cheval_id) return array();
  $raw = is_array($raw) ? $raw : array();

  $clean = gwseq_sanitize_cheval_editorial_input($raw);
  $max_lengths = gwseq_cheval_editorial_field_max_length();
  $rejected = array();

  foreach (gwseq_cheval_editorial_field_map() as $field_key => $meta_key) {
    $value = $clean[$field_key];
    $max = $max_lengths[$field_key] ?? null;
    if ($max !== null) {
      $length = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
      if ($length > $max) {
        $rejected[$field_key] = 'too_long';
        continue; // valeur déjà enregistrée jamais touchée, jamais tronquée
      }
    }
    update_post_meta($cheval_id, $meta_key, $value);
  }

  $qualites = gwseq_sanitize_cheval_text_list($raw['_gwseq_qualites'] ?? array(), GWSEQ_CHEVAL_QUALITES_MAX_ITEMS, GWSEQ_CHEVAL_QUALITES_MAX_LENGTH);
  if ($qualites['rejected']) {
    $rejected['qualites'] = $qualites['reason'];
  } else {
    update_post_meta($cheval_id, '_gwseq_qualites', $qualites['values']);
  }

  $faits_marquants = gwseq_sanitize_cheval_text_list($raw['_gwseq_faits_marquants'] ?? array(), GWSEQ_CHEVAL_FAITS_MARQUANTS_MAX_ITEMS, GWSEQ_CHEVAL_FAITS_MARQUANTS_MAX_LENGTH);
  if ($faits_marquants['rejected']) {
    $rejected['faits_marquants'] = $faits_marquants['reason'];
  } else {
    update_post_meta($cheval_id, '_gwseq_faits_marquants', $faits_marquants['values']);
  }

  return $rejected;
}

function gwseq_get_cheval_editorial($cheval_id) {
  $data = array();
  foreach (gwseq_cheval_editorial_field_map() as $field_key => $meta_key) {
    $data[$field_key] = get_post_meta($cheval_id, $meta_key, true);
  }
  return $data;
}

/**
 * Qualités (ex-"Points forts", §1 du Lot 2A) — liste ORDONNÉE de courtes chaînes, jamais mêlée aux
 * champs texte libre de gwseq_get_cheval_editorial() (forme de donnée différente : tableau, pas une
 * chaîne). Tableau vide si rien n'est enregistré, jamais une erreur.
 */
function gwseq_get_cheval_qualites($cheval_id) {
  $values = get_post_meta($cheval_id, '_gwseq_qualites', true);
  return is_array($values) ? array_values($values) : array();
}

/**
 * Faits marquants (§2 du Lot 2A) — même forme que Qualités ci-dessus, bornes différentes.
 */
function gwseq_get_cheval_faits_marquants($cheval_id) {
  $values = get_post_meta($cheval_id, '_gwseq_faits_marquants', true);
  return is_array($values) ? array_values($values) : array();
}

/* -------------------------------------------------------------------------------------------
 * Meta boxes et sauvegarde (glue WordPress) — un client parmi d'autres de gwseq_set_cheval_editorial().
 * ----------------------------------------------------------------------------------------- */

/**
 * Libellés affichés, dans l'ordre de saisie voulu (§7) — distinct de gwseq_cheval_editorial_field_map()
 * qui, lui, reste la source de vérité pour les NOMS de meta ; ce tableau ne sert qu'au RENDU.
 * "osteo_articulaire" n'y figure volontairement pas : rendu séparément dans sa propre meta box
 * "Informations complémentaires" (§9 : organisation par blocs cohérents).
 */
function gwseq_cheval_editorial_presentation_field_labels() {
  return array(
    // Champ distinct de la Présentation / Description ci-dessous (§3 du lot Partage) : une
    // accroche COURTE, pensée pour être réutilisée telle quelle sur un support commercial (partage
    // WhatsApp/SMS, futur PDF, sélection, catalogue) — jamais un simple synonyme de la présentation
    // longue. Ni obligatoire, ni pourvue d'un quelconque contenu de repli généré : un champ vide
    // reste vide partout où cette donnée est réutilisée (voir includes/cheval-share.php).
    'accroche_commerciale' => array(__('Accroche commerciale', 'gws-core'), __('Une ou deux phrases courtes pour présenter ce cheval lors d’un partage ou sur un support commercial.', 'gws-core')),
    'presentation' => array(__('Présentation / Description', 'gws-core'), __('Présentation générale du cheval.', 'gws-core')),
    'potentiel' => array(__('Potentiel', 'gws-core'), __('Potentiel sportif, commercial ou d’élevage selon le contexte.', 'gws-core')),
    'resultats' => array(__('Résultats / Performances', 'gws-core'), __('Résultats significatifs à présenter — pas une base structurée de tous les concours.', 'gws-core')),
    'origines_commentaire' => array(__('Origines — commentaire', 'gws-core'), __('Texte libre sur l’intérêt des origines (distinct du pedigree structuré ci-dessus, jamais reconstruit à partir de ce texte).', 'gws-core')),
    'commentaire_production' => array(__('Production — commentaire', 'gws-core'), __('Texte libre sur la production de ce cheval (distinct de la Production calculée à partir des relations GWS, affichée dans la meta box « Production »).', 'gws-core')),
    'conditions_vente' => array(__('Conditions de vente / élevage / reproduction', 'gws-core'), __('Informations commerciales ou conditions particulières pertinentes.', 'gws-core')),
    'conseils_croisement' => array(__('Conseils de croisement', 'gws-core'), __('Disponible pour tous les chevaux — à vous de juger de sa pertinence.', 'gws-core')),
  );
}

/**
 * Libellés humains des champs pouvant être REJETÉS par gwseq_set_cheval_editorial() (§3 du Lot
 * 2A) — réutilisés par le message d'erreur admin (gwseq_cheval_editorial_rejected_admin_notice()
 * ci-dessous). Dérivés de gwseq_cheval_editorial_presentation_field_labels() pour les six champs à
 * limite fixe (jamais un second libellé dupliqué), complétés pour Qualités/Faits marquants qui
 * n'ont pas d'entrée dans ce tableau (rendu séparément, voir gwseq_render_cheval_presentation_box()).
 */
function gwseq_cheval_editorial_rejected_field_labels() {
  $labels = array();
  foreach (gwseq_cheval_editorial_presentation_field_labels() as $field_key => $pair) {
    $labels[$field_key] = $pair[0];
  }
  $labels['qualites'] = __('Qualités', 'gws-core');
  $labels['faits_marquants'] = __('Faits marquants', 'gws-core');
  return $labels;
}

function gwseq_add_cheval_editorial_meta_boxes() {
  add_meta_box('gwseq-cheval-presentation', __('Présentation', 'gws-core'), 'gwseq_render_cheval_presentation_box', GWSEQ_CPT_CHEVAL, 'normal', 'default');
  add_meta_box('gwseq-cheval-infos-complementaires', __('Informations complémentaires', 'gws-core'), 'gwseq_render_cheval_infos_complementaires_box', GWSEQ_CPT_CHEVAL, 'normal', 'default');
}
add_action('add_meta_boxes_' . GWSEQ_CPT_CHEVAL, 'gwseq_add_cheval_editorial_meta_boxes');

/**
 * Un champ texte libre de la boîte « Présentation », avec compteur de caractères visible et
 * `maxlength` HTML natif quand $max_length est fourni (§3 du Lot 2A) — SEULE source de vérité pour
 * ce rendu, jamais un second gabarit dupliqué. `maxlength` empêche déjà la SAISIE d'aller au-delà
 * (confort client) ; un contenu déjà enregistré au-delà de la limite AVANT ce lot reste affiché
 * intégralement (le navigateur n'altère jamais le contenu existant, seule la frappe/le collage
 * futurs sont bornés) — voir gwseq_set_cheval_editorial() pour la garantie réelle côté serveur.
 */
function gwseq_render_cheval_editorial_textarea_field($field_key, $meta_key, $label, $help, $value, $rows, $max_length) {
  $counter_attrs = $max_length !== null ? ' maxlength="' . esc_attr($max_length) . '" data-gwseq-charcount="' . esc_attr($max_length) . '"' : '';
  ?>
  <p>
    <label for="gwseq-cheval-<?php echo esc_attr($field_key); ?>"><strong><?php echo esc_html($label); ?></strong></label><br>
    <textarea class="widefat" rows="<?php echo esc_attr($rows); ?>" id="gwseq-cheval-<?php echo esc_attr($field_key); ?>" name="<?php echo esc_attr($meta_key); ?>"<?php echo $counter_attrs; ?>><?php echo esc_textarea($value); ?></textarea>
    <?php if ($max_length !== null) : ?>
      <span class="gwseq-charcount-display"></span>
    <?php endif; ?>
    <span class="description"><?php echo esc_html($help); ?></span>
  </p>
  <?php
}

/**
 * Rendu d'une ligne d'une liste ordonnée de courtes chaînes (Qualités / Faits marquants).
 * $index_or_template : entier réel pour une ligne existante (non utilisé dans le `name`, voir
 * ci-dessous), ou ignoré pour le gabarit `<template>` du JS (assets/cheval-editorial-admin.js).
 *
 * NOMMAGE HTML : `name="{meta_key}[]"`, JAMAIS un index explicite (contrairement au composant
 * répétable générique multi-colonnes, includes/repeater-field.php, qui EN A besoin pour regrouper
 * plusieurs colonnes d'une même ligne). Une seule colonne ici : un formulaire HTML soumet
 * naturellement les champs `name="x[]"` dans leur ORDRE D'APPARITION DANS LE DOM — le
 * réordonnancement (§1 : "possibilité de réordonner") se réduit donc à déplacer le `<li>` dans le
 * DOM (assets/cheval-editorial-admin.js), SANS jamais renuméroter aucun attribut `name` : plus
 * simple et plus robuste que le schéma à index du composant générique, adapté précisément parce
 * que chaque ligne ne porte ici qu'UNE seule valeur.
 */
function gwseq_text_list_row_markup($meta_key, $value, $max_length) {
  ob_start();
  ?>
  <li class="gwseq-text-list__row">
    <button type="button" class="button-secondary gwseq-text-list__move-up" aria-label="<?php esc_attr_e('Déplacer vers le haut', 'gws-core'); ?>">&#8593;</button>
    <button type="button" class="button-secondary gwseq-text-list__move-down" aria-label="<?php esc_attr_e('Déplacer vers le bas', 'gws-core'); ?>">&#8595;</button>
    <input type="text" class="gwseq-text-list__input" maxlength="<?php echo esc_attr($max_length); ?>" name="<?php echo esc_attr($meta_key); ?>[]" value="<?php echo esc_attr($value); ?>">
    <span class="gwseq-text-list__counter" aria-hidden="true"></span>
    <button type="button" class="button-link-delete gwseq-text-list__remove"><?php esc_html_e('Supprimer', 'gws-core'); ?></button>
  </li>
  <?php
  return ob_get_clean();
}

/**
 * Bloc complet d'une liste ordonnée de courtes chaînes (§1-2 du Lot 2A) : lignes déjà enregistrées
 * + un gabarit `<template>` cloné par le JS pour en ajouter une nouvelle (même principe que
 * includes/repeater-field.php, jamais dupliqué en JS). L'ajout/la suppression/le réordonnancement
 * nécessitent JavaScript (assets/cheval-editorial-admin.js) — même limite déjà assumée par le
 * composant répétable générique pour la Galerie/les Vidéos ; SANS JavaScript, les lignes déjà
 * enregistrées restent visibles et se soumettent normalement dans leur ordre existant.
 * $max_items est une aide UX (désactive « + Ajouter » une fois la limite atteinte) — la garantie
 * réelle reste gwseq_sanitize_cheval_text_list() côté serveur, qui rejette explicitement tout
 * dépassement plutôt que de l'ignorer silencieusement.
 */
function gwseq_render_cheval_text_list_field($meta_key, $values, $max_items, $max_length, $add_label) {
  $values = is_array($values) ? $values : array();
  ?>
  <div class="gwseq-text-list" data-gwseq-text-list="<?php echo esc_attr($meta_key); ?>" data-gwseq-max-items="<?php echo esc_attr($max_items); ?>" data-gwseq-max-length="<?php echo esc_attr($max_length); ?>">
    <ul class="gwseq-text-list__rows">
      <?php foreach ($values as $value) : ?>
        <?php echo gwseq_text_list_row_markup($meta_key, $value, $max_length); ?>
      <?php endforeach; ?>
    </ul>
    <p>
      <button type="button" class="button gwseq-text-list__add"><?php echo esc_html($add_label); ?></button>
      <span class="description"><?php echo esc_html(sprintf(
        /* translators: %d: nombre maximum d'éléments autorisés */
        __('(maximum %d)', 'gws-core'),
        (int) $max_items
      )); ?></span>
    </p>
    <template class="gwseq-text-list__template"><?php echo gwseq_text_list_row_markup($meta_key, '', $max_length); ?></template>
  </div>
  <?php
}

function gwseq_render_cheval_presentation_box($post) {
  wp_nonce_field(GWSEQ_CHEVAL_NONCE_ACTION, GWSEQ_CHEVAL_NONCE_FIELD);
  $editorial = gwseq_get_cheval_editorial($post->ID);
  $labels = gwseq_cheval_editorial_presentation_field_labels();
  $max_lengths = gwseq_cheval_editorial_field_max_length();
  $field_map = gwseq_cheval_editorial_field_map();
  ?>
  <p class="description"><?php esc_html_e('Tous ces champs sont facultatifs : ne renseignez que ce qui est pertinent pour ce cheval.', 'gws-core'); ?></p>
  <?php
  list($accroche_label, $accroche_help) = $labels['accroche_commerciale'];
  gwseq_render_cheval_editorial_textarea_field('accroche_commerciale', $field_map['accroche_commerciale'], $accroche_label, $accroche_help, $editorial['accroche_commerciale'], 2, $max_lengths['accroche_commerciale']);

  list($presentation_label, $presentation_help) = $labels['presentation'];
  gwseq_render_cheval_editorial_textarea_field('presentation', $field_map['presentation'], $presentation_label, $presentation_help, $editorial['presentation'], 4, $max_lengths['presentation']);
  ?>
  <p>
    <label><strong><?php esc_html_e('Qualités', 'gws-core'); ?></strong></label><br>
    <span class="description"><?php esc_html_e('Jusqu’à 5 qualités courtes (25 caractères max chacune), dans l’ordre de votre choix — ex. Respect, Sang, Force, Bon galop, Équilibre. Vocabulaire entièrement libre.', 'gws-core'); ?></span>
    <?php gwseq_render_cheval_text_list_field('_gwseq_qualites', gwseq_get_cheval_qualites($post->ID), GWSEQ_CHEVAL_QUALITES_MAX_ITEMS, GWSEQ_CHEVAL_QUALITES_MAX_LENGTH, __('+ Ajouter une qualité', 'gws-core')); ?>
  </p>
  <p>
    <label><strong><?php esc_html_e('Faits marquants', 'gws-core'); ?></strong></label><br>
    <span class="description"><?php esc_html_e('Jusqu’à 3 arguments commerciaux courts (80 caractères max chacun) — ex. « Finaliste Championnat de France 7 ans ». Aucun n’est obligatoire ; choisissez-les vous-même, rien n’est déduit automatiquement des autres données.', 'gws-core'); ?></span>
    <?php gwseq_render_cheval_text_list_field('_gwseq_faits_marquants', gwseq_get_cheval_faits_marquants($post->ID), GWSEQ_CHEVAL_FAITS_MARQUANTS_MAX_ITEMS, GWSEQ_CHEVAL_FAITS_MARQUANTS_MAX_LENGTH, __('+ Ajouter un fait marquant', 'gws-core')); ?>
  </p>
  <?php
  foreach (array('potentiel', 'resultats', 'origines_commentaire', 'commentaire_production', 'conditions_vente', 'conseils_croisement') as $field_key) {
    list($label, $help) = $labels[$field_key];
    gwseq_render_cheval_editorial_textarea_field($field_key, $field_map[$field_key], $label, $help, $editorial[$field_key], 4, $max_lengths[$field_key] ?? null);
  }
}

function gwseq_render_cheval_infos_complementaires_box($post) {
  wp_nonce_field(GWSEQ_CHEVAL_NONCE_ACTION, GWSEQ_CHEVAL_NONCE_FIELD);
  $editorial = gwseq_get_cheval_editorial($post->ID);
  ?>
  <p>
    <label for="gwseq-cheval-osteo-articulaire"><strong><?php esc_html_e('Ostéo-articulaire', 'gws-core'); ?></strong></label><br>
    <textarea class="widefat" rows="4" id="gwseq-cheval-osteo-articulaire" name="_gwseq_osteo_articulaire"><?php echo esc_textarea($editorial['osteo_articulaire']); ?></textarea>
    <span class="description"><?php esc_html_e('Information synthétique destinée à la fiche commerciale — texte libre, jamais un dossier vétérinaire (pas d’historique de soins, de traitements ni de données médicales complexes).', 'gws-core'); ?></span>
  </p>
  <?php
}

/**
 * Relais des champs éditoriaux rejetés (§3 du Lot 2A, voir le docblock de fichier pour l'analyse
 * complète) entre save_post_gwseq_cheval et le filtre redirect_post_location exécuté juste après,
 * DANS LA MÊME requête PHP — state statique de fonction, jamais persisté (ni transient, ni option,
 * ni meta), disparaît naturellement à la fin de la requête.
 */
function gwseq_cheval_editorial_rejected_fields_store($post_id, $rejected = null) {
  static $store = array();
  if ($rejected !== null) $store[$post_id] = $rejected;
  return $store[$post_id] ?? array();
}

function gwseq_save_cheval_editorial_meta($post_id) {
  if (!isset($_POST[GWSEQ_CHEVAL_NONCE_FIELD]) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[GWSEQ_CHEVAL_NONCE_FIELD])), GWSEQ_CHEVAL_NONCE_ACTION)) return;
  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
  if (function_exists('wp_is_post_revision') && wp_is_post_revision($post_id)) return;
  if (!current_user_can('edit_post', $post_id)) return;

  $rejected = gwseq_set_cheval_editorial($post_id, $_POST);
  if ($rejected) gwseq_cheval_editorial_rejected_fields_store($post_id, $rejected);
}
add_action('save_post_' . GWSEQ_CPT_CHEVAL, 'gwseq_save_cheval_editorial_meta');

/**
 * Ajoute la liste des champs rejetés (le cas échéant) à l'URL de redirection post-enregistrement —
 * mécanisme NATIF WordPress (`redirect_post_location`, appliqué par wp-admin/post.php juste après
 * que tous les callbacks save_post_{cpt} se sont exécutés, DANS LA MÊME requête), jamais un
 * transient ni une écriture en base. Chaque paire "champ:raison" est réduite à des caractères sûrs
 * (sanitize_key()) avant d'entrer dans l'URL.
 */
function gwseq_cheval_editorial_redirect_with_rejected_fields($location, $post_id) {
  $rejected = gwseq_cheval_editorial_rejected_fields_store($post_id);
  if (!$rejected) return $location;
  $pairs = array();
  foreach ($rejected as $field_key => $reason) {
    $pairs[] = sanitize_key($field_key) . ':' . sanitize_key($reason);
  }
  return add_query_arg('gwseq_editorial_rejected', implode(',', $pairs), $location);
}
add_filter('redirect_post_location', 'gwseq_cheval_editorial_redirect_with_rejected_fields', 10, 2);

/**
 * Message d'erreur EXPLICITE (§3 : "message compréhensible si la limite est dépassée") — un item de
 * liste par champ rejeté, nommé précisément, avec la raison exacte. Jamais un message générique
 * ("une erreur est survenue") : l'utilisateur sait immédiatement QUOI corriger et QUE sa saisie
 * précédente reste enregistrée (rien perdu côté serveur, seule la nouvelle valeur proposée n'a pas
 * été retenue).
 */
function gwseq_cheval_editorial_rejected_admin_notice() {
  if (empty($_GET['gwseq_editorial_rejected'])) return;
  $screen = function_exists('get_current_screen') ? get_current_screen() : null;
  if (!$screen || $screen->post_type !== GWSEQ_CPT_CHEVAL) return;

  $raw = sanitize_text_field(wp_unslash($_GET['gwseq_editorial_rejected']));
  $field_labels = gwseq_cheval_editorial_rejected_field_labels();
  $max_lengths = gwseq_cheval_editorial_field_max_length();

  $items = array();
  foreach (array_filter(explode(',', $raw)) as $pair) {
    $parts = array_pad(explode(':', $pair, 2), 2, '');
    $field_key = $parts[0];
    $reason = $parts[1];
    $label = $field_labels[$field_key] ?? $field_key;

    if ($reason === 'too_many') {
      $max_items = $field_key === 'qualites' ? GWSEQ_CHEVAL_QUALITES_MAX_ITEMS : GWSEQ_CHEVAL_FAITS_MARQUANTS_MAX_ITEMS;
      $items[] = sprintf(
        /* translators: 1: nom du champ, 2: nombre maximum d'éléments autorisés */
        __('%1$s : trop d’éléments soumis (maximum %2$d) — rien n’a été enregistré pour ce champ, la version précédente est conservée.', 'gws-core'),
        $label,
        $max_items
      );
    } else {
      $max_length = $max_lengths[$field_key] ?? (in_array($field_key, array('qualites', 'faits_marquants'), true)
        ? ($field_key === 'qualites' ? GWSEQ_CHEVAL_QUALITES_MAX_LENGTH : GWSEQ_CHEVAL_FAITS_MARQUANTS_MAX_LENGTH)
        : null);
      $items[] = $max_length !== null
        ? sprintf(
            /* translators: 1: nom du champ, 2: nombre maximum de caractères autorisés */
            __('%1$s : le contenu dépasse %2$d caractères — rien n’a été enregistré pour ce champ, la version précédente est conservée.', 'gws-core'),
            $label,
            $max_length
          )
        : sprintf(
            /* translators: %s: nom du champ */
            __('%s : le contenu dépasse la longueur autorisée — rien n’a été enregistré pour ce champ, la version précédente est conservée.', 'gws-core'),
            $label
          );
    }
  }
  if (!$items) return;

  echo '<div class="notice notice-error"><p><strong>' . esc_html__('Certains champs éditoriaux n’ont pas pu être enregistrés :', 'gws-core') . '</strong></p><ul style="list-style:disc;margin-left:1.5em;">';
  foreach ($items as $item) {
    echo '<li>' . esc_html($item) . '</li>';
  }
  echo '</ul></div>';
}
add_action('admin_notices', 'gwseq_cheval_editorial_rejected_admin_notice');

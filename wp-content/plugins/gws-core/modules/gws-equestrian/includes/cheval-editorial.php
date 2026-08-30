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
 */

if (!defined('ABSPATH')) exit;

/* -------------------------------------------------------------------------------------------
 * Enregistrement des meta.
 * ----------------------------------------------------------------------------------------- */

/**
 * Association clé logique => nom de meta, SEULE source de vérité pour la liste des champs
 * éditoriaux — utilisée par l'enregistrement des meta, la sanitation, la lecture, le rendu ET la
 * sauvegarde : ajouter un futur champ éditorial se limite à une ligne ici plus son rendu, jamais
 * une modification dispersée dans plusieurs listes parallèles.
 */
function gwseq_cheval_editorial_field_map() {
  return array(
    'presentation' => '_gwseq_presentation',
    'points_forts' => '_gwseq_points_forts',
    'potentiel' => '_gwseq_potentiel',
    'resultats' => '_gwseq_resultats',
    'origines_commentaire' => '_gwseq_origines_commentaire',
    'commentaire_production' => '_gwseq_commentaire_production',
    'conditions_vente' => '_gwseq_conditions_vente',
    'conseils_croisement' => '_gwseq_conseils_croisement',
    'osteo_articulaire' => '_gwseq_osteo_articulaire',
  );
}

function gwseq_register_cheval_editorial_meta() {
  foreach (gwseq_cheval_editorial_field_map() as $meta_key) {
    register_post_meta(GWSEQ_CPT_CHEVAL, $meta_key, array('single' => true, 'type' => 'string', 'show_in_rest' => false));
  }
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
function gwseq_set_cheval_editorial($cheval_id, $raw) {
  $cheval_id = (int) $cheval_id;
  if (!$cheval_id) return false;
  $clean = gwseq_sanitize_cheval_editorial_input($raw);
  foreach (gwseq_cheval_editorial_field_map() as $field_key => $meta_key) {
    update_post_meta($cheval_id, $meta_key, $clean[$field_key]);
  }
  return true;
}

function gwseq_get_cheval_editorial($cheval_id) {
  $data = array();
  foreach (gwseq_cheval_editorial_field_map() as $field_key => $meta_key) {
    $data[$field_key] = get_post_meta($cheval_id, $meta_key, true);
  }
  return $data;
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
    'presentation' => array(__('Présentation / Description', 'gws-core'), __('Présentation générale du cheval.', 'gws-core')),
    'points_forts' => array(__('Points forts', 'gws-core'), __('Qualités ou caractéristiques que vous souhaitez mettre en avant.', 'gws-core')),
    'potentiel' => array(__('Potentiel', 'gws-core'), __('Potentiel sportif, commercial ou d’élevage selon le contexte.', 'gws-core')),
    'resultats' => array(__('Résultats / Performances', 'gws-core'), __('Résultats significatifs à présenter — pas une base structurée de tous les concours.', 'gws-core')),
    'origines_commentaire' => array(__('Origines — commentaire', 'gws-core'), __('Texte libre sur l’intérêt des origines (distinct du pedigree structuré ci-dessus, jamais reconstruit à partir de ce texte).', 'gws-core')),
    'commentaire_production' => array(__('Production — commentaire', 'gws-core'), __('Texte libre sur la production de ce cheval (distinct de la Production calculée à partir des relations GWS, affichée dans la meta box « Production »).', 'gws-core')),
    'conditions_vente' => array(__('Conditions de vente / élevage / reproduction', 'gws-core'), __('Informations commerciales ou conditions particulières pertinentes.', 'gws-core')),
    'conseils_croisement' => array(__('Conseils de croisement', 'gws-core'), __('Disponible pour tous les chevaux — à vous de juger de sa pertinence.', 'gws-core')),
  );
}

function gwseq_add_cheval_editorial_meta_boxes() {
  add_meta_box('gwseq-cheval-presentation', __('Présentation', 'gws-core'), 'gwseq_render_cheval_presentation_box', GWSEQ_CPT_CHEVAL, 'normal', 'default');
  add_meta_box('gwseq-cheval-infos-complementaires', __('Informations complémentaires', 'gws-core'), 'gwseq_render_cheval_infos_complementaires_box', GWSEQ_CPT_CHEVAL, 'normal', 'default');
}
add_action('add_meta_boxes_' . GWSEQ_CPT_CHEVAL, 'gwseq_add_cheval_editorial_meta_boxes');

function gwseq_render_cheval_presentation_box($post) {
  wp_nonce_field(GWSEQ_CHEVAL_NONCE_ACTION, GWSEQ_CHEVAL_NONCE_FIELD);
  $editorial = gwseq_get_cheval_editorial($post->ID);
  ?>
  <p class="description"><?php esc_html_e('Tous ces champs sont facultatifs : ne renseignez que ce qui est pertinent pour ce cheval.', 'gws-core'); ?></p>
  <?php foreach (gwseq_cheval_editorial_presentation_field_labels() as $field_key => list($label, $help)) :
    $meta_key = gwseq_cheval_editorial_field_map()[$field_key];
  ?>
    <p>
      <label for="gwseq-cheval-<?php echo esc_attr($field_key); ?>"><strong><?php echo esc_html($label); ?></strong></label><br>
      <textarea class="widefat" rows="4" id="gwseq-cheval-<?php echo esc_attr($field_key); ?>" name="<?php echo esc_attr($meta_key); ?>"><?php echo esc_textarea($editorial[$field_key]); ?></textarea>
      <span class="description"><?php echo esc_html($help); ?></span>
    </p>
  <?php endforeach; ?>
  <?php
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

function gwseq_save_cheval_editorial_meta($post_id) {
  if (!isset($_POST[GWSEQ_CHEVAL_NONCE_FIELD]) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[GWSEQ_CHEVAL_NONCE_FIELD])), GWSEQ_CHEVAL_NONCE_ACTION)) return;
  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
  if (function_exists('wp_is_post_revision') && wp_is_post_revision($post_id)) return;
  if (!current_user_can('edit_post', $post_id)) return;

  gwseq_set_cheval_editorial($post_id, $_POST);
}
add_action('save_post_' . GWSEQ_CPT_CHEVAL, 'gwseq_save_cheval_editorial_meta');

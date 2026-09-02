<?php
/**
 * Cheval — Labels ANSF (nouveau lot, volontairement minimal).
 *
 * PÉRIMÈTRE V1 ASSUMÉ (§A/§D de la demande) : uniquement les labels Selle Français / ANSF identifiés
 * pour la commercialisation initiale en France — SFO, Étalon SF Génétique Avenir, et les trois
 * familles de labels poulinières (Sport/Élevage/Modèle & Allures). AUCUN moteur générique de
 * distinctions, AUCUN référentiel multi-stud-books, AUCUNE extensibilité anticipée : ajouter un
 * futur label d'un autre stud-book/organisme est un nouveau lot à part entière, jamais une simple
 * entrée de plus dans une liste ici.
 *
 * RÈGLES MÉTIER STRICTEMENT LIMITÉES AU SEXE (§A) :
 * - SFO : disponible pour femelle, mâle ET hongre — jamais restreint, jamais touché par un
 *   changement de sexe (voir gwseq_sanitize_cheval_labels_input() ci-dessous).
 * - Labels poulinières (Sport/Élevage/Modèle & Allures) : UNIQUEMENT femelle.
 * - Étalon SF Génétique Avenir : mâle ET hongre (un hongre peut avoir obtenu ce statut ou eu une
 *   carrière de reproducteur avant castration ; sa semence peut encore être commercialisée).
 * AUCUNE AUTRE règle métier (race/stud-book, âge, pedigree, statut reproducteur...) : GWS empêche
 * seulement les incohérences évidentes liées au sexe, jamais un moteur de certification ANSF.
 *
 * UNE SEULE VALEUR PAR FAMILLE (§A) : chaque famille de label poulinière est un ENUM fermé à quatre
 * valeurs mutuellement exclusives (`none`/`tres_bonne`/`excellente`/`elite`), jamais quatre cases à
 * cocher indépendantes qui permettraient une incohérence ("Sport — Élite" ET "Sport — Très Bonne"
 * simultanément) — rendu via un groupe de boutons radio (§A : "un contrôle adapté à ce
 * fonctionnement exclusif").
 *
 * DONNÉES STRUCTURÉES, JAMAIS DES LIBELLÉS (§B) : les valeurs internes stockées sont des codes
 * techniques stables (booléens `'1'`/`''`, ou l'un des quatre enums ci-dessus) — les libellés
 * traduits affichés dans l'admin n'existent que côté rendu (gwseq_cheval_label_niveau_options()),
 * jamais stockés. Choisis délibérément pour qu'une correspondance future vers un asset (pictogramme
 * officiel ANSF, §C : "sfo -> asset SFO", "sport_elite -> asset correspondant") reste triviale à
 * construire PLUS TARD (simplement `{famille}_{valeur}` pour les labels poulinières, ou le nom du
 * code lui-même pour SFO/SF Génétique Avenir) — AUCUNE fonction de correspondance n'est ajoutée ici
 * (§C/§D : "les pictogrammes feront l'objet d'une évolution séparée", "ne pas intégrer de faux
 * pictogrammes temporaires").
 *
 * SANITATION SERVEUR OBLIGATOIRE (§B) : gwseq_sanitize_cheval_labels_input() est LA SEULE
 * implémentation de ces règles, appliquée que la requête vienne du formulaire d'admin normal (avec
 * JavaScript, sans JavaScript, ou avec un payload délibérément incohérent) — jamais une simple
 * dépendance à l'affichage conditionnel de gwseq_render_cheval_labels_box() ci-dessous, qui n'est
 * qu'un confort de saisie.
 *
 * CHANGEMENT DE SEXE D'UN CHEVAL EXISTANT (§B) : $sexe, passé explicitement à
 * gwseq_sanitize_cheval_labels_input()/gwseq_set_cheval_labels(), est TOUJOURS la valeur déjà
 * sanitisée de CETTE MÊME soumission (gwseq_sanitize_cheval_identity_input($_POST)['sexe'], voir
 * gwseq_save_cheval_labels_meta() plus bas) — jamais relue depuis une meta potentiellement pas
 * encore enregistrée à ce point de l'exécution, jamais l'ancien sexe déjà en base. Un label devenu
 * incompatible avec le sexe fraîchement soumis est donc silencieusement nettoyé (jamais conservé)
 * AU PROCHAIN ENREGISTREMENT volontaire de la fiche : passage vers femelle -> SF Génétique Avenir
 * remis à `''` ; passage vers mâle/hongre -> les trois labels poulinières remis à `'none'`. SFO
 * n'est JAMAIS touché par cette logique, quel que soit le sexe. Un sexe non renseigné (`''`) est
 * traité comme n'étant ni l'un ni l'autre : les DEUX groupes de labels sexe-dépendants sont alors
 * nettoyés (repli prudent, jamais un label affiché pour un sexe qu'on ne peut pas confirmer).
 */

if (!defined('ABSPATH')) exit;

/**
 * Les quatre niveaux, communs aux trois familles de labels poulinières (§A) — valeurs techniques
 * stables en minuscules/underscore (cohérent avec les autres enums du module), libellés traduits.
 */
function gwseq_cheval_label_niveau_options() {
  return array(
    'none' => __('Aucun', 'gws-core'),
    'tres_bonne' => __('Très Bonne', 'gws-core'),
    'excellente' => __('Excellente', 'gws-core'),
    'elite' => __('Élite', 'gws-core'),
  );
}

function gwseq_cheval_label_familles_poulinieres() {
  return array(
    'sport' => __('Label Sport', 'gws-core'),
    'elevage' => __('Label Élevage', 'gws-core'),
    'modele_allures' => __('Label Modèle & Allures', 'gws-core'),
  );
}

function gwseq_register_cheval_labels_meta() {
  foreach (array('_gwseq_label_sfo', '_gwseq_label_sf_genetique_avenir', '_gwseq_label_sport', '_gwseq_label_elevage', '_gwseq_label_modele_allures') as $key) {
    register_post_meta(GWSEQ_CPT_CHEVAL, $key, array('single' => true, 'type' => 'string', 'show_in_rest' => false));
  }
}
add_action('init', 'gwseq_register_cheval_labels_meta');

/**
 * RÈGLE MÉTIER UNIQUE ET CENTRALE des Labels ANSF (§B de la demande) — fonction pure, aucun accès à
 * $_POST ni à la base : voir le docblock de ce fichier pour le détail complet des règles de sexe et
 * du comportement lors d'un changement de sexe. $sexe DOIT être la valeur déjà sanitisée pour la
 * MÊME soumission (jamais une meta relue, voir gwseq_save_cheval_labels_meta() plus bas).
 */
function gwseq_sanitize_cheval_labels_input($raw, $sexe) {
  $raw = is_array($raw) ? $raw : array();
  $is_female = $sexe === 'female';
  $is_male_or_gelding = in_array($sexe, array('male', 'gelding'), true);
  $niveau_options = gwseq_cheval_label_niveau_options();

  $result = array(
    // SFO (§A/§B) : jamais restreint par le sexe, jamais touché par un changement de sexe.
    'sfo' => gws_core_field_sanitize('checkbox', $raw['_gwseq_label_sfo'] ?? ''),
    'sf_genetique_avenir' => '',
    'sport' => 'none',
    'elevage' => 'none',
    'modele_allures' => 'none',
  );

  if ($is_male_or_gelding) {
    $result['sf_genetique_avenir'] = gws_core_field_sanitize('checkbox', $raw['_gwseq_label_sf_genetique_avenir'] ?? '');
  }

  if ($is_female) {
    foreach (array_keys(gwseq_cheval_label_familles_poulinieres()) as $famille) {
      $value = sanitize_key(wp_unslash($raw['_gwseq_label_' . $famille] ?? ''));
      $result[$famille] = array_key_exists($value, $niveau_options) ? $value : 'none';
    }
  }

  return $result;
}

function gwseq_get_cheval_labels($post_id) {
  return array(
    'sfo' => get_post_meta($post_id, '_gwseq_label_sfo', true),
    'sf_genetique_avenir' => get_post_meta($post_id, '_gwseq_label_sf_genetique_avenir', true),
    'sport' => get_post_meta($post_id, '_gwseq_label_sport', true) ?: 'none',
    'elevage' => get_post_meta($post_id, '_gwseq_label_elevage', true) ?: 'none',
    'modele_allures' => get_post_meta($post_id, '_gwseq_label_modele_allures', true) ?: 'none',
  );
}

/**
 * Fonction métier pure d'écriture (même architecture que gwseq_set_cheval_identity()) : sanitise
 * puis persiste — $sexe est relayé tel quel à gwseq_sanitize_cheval_labels_input(), voir son
 * docblock. Ne fait rien si $post_id est invalide.
 */
function gwseq_set_cheval_labels($post_id, $raw, $sexe) {
  $post_id = (int) $post_id;
  if (!$post_id) return false;
  $labels = gwseq_sanitize_cheval_labels_input($raw, $sexe);
  update_post_meta($post_id, '_gwseq_label_sfo', $labels['sfo']);
  update_post_meta($post_id, '_gwseq_label_sf_genetique_avenir', $labels['sf_genetique_avenir']);
  update_post_meta($post_id, '_gwseq_label_sport', $labels['sport']);
  update_post_meta($post_id, '_gwseq_label_elevage', $labels['elevage']);
  update_post_meta($post_id, '_gwseq_label_modele_allures', $labels['modele_allures']);
  return true;
}

/* -------------------------------------------------------------------------------------------
 * Meta box et sauvegarde (glue WordPress) — un client parmi d'autres des fonctions ci-dessus.
 * ----------------------------------------------------------------------------------------- */

function gwseq_add_cheval_labels_meta_box() {
  add_meta_box('gwseq-cheval-labels', __('Labels', 'gws-core'), 'gwseq_render_cheval_labels_box', GWSEQ_CPT_CHEVAL, 'normal', 'default');
}
add_action('add_meta_boxes_' . GWSEQ_CPT_CHEVAL, 'gwseq_add_cheval_labels_meta_box');

/**
 * Rendu conditionné par le sexe COURANT déjà enregistré (§A : "son contenu dépend du sexe du
 * cheval") — purement un confort de saisie, jamais la garantie réelle (voir
 * gwseq_sanitize_cheval_labels_input(), seule autorité). Un sexe pas encore renseigné n'affiche
 * aucun des deux groupes sexe-dépendants (rien à proposer tant que l'information manque) mais
 * garde SFO toujours visible, cohérent avec son indépendance au sexe.
 */
function gwseq_render_cheval_labels_box($post) {
  wp_nonce_field(GWSEQ_CHEVAL_NONCE_ACTION, GWSEQ_CHEVAL_NONCE_FIELD);
  $sexe = gwseq_get_cheval_identity($post->ID)['sexe'];
  $labels = gwseq_get_cheval_labels($post->ID);
  $is_female = $sexe === 'female';
  $is_male_or_gelding = in_array($sexe, array('male', 'gelding'), true);
  ?>
  <p>
    <label>
      <input type="checkbox" name="_gwseq_label_sfo" value="1" <?php checked($labels['sfo'], '1'); ?>>
      <?php esc_html_e('Selle Français Originel (SFO)', 'gws-core'); ?>
    </label>
  </p>
  <?php if ($is_female) : ?>
    <?php foreach (gwseq_cheval_label_familles_poulinieres() as $famille => $famille_label) : ?>
      <p>
        <strong><?php echo esc_html($famille_label); ?></strong><br>
        <?php foreach (gwseq_cheval_label_niveau_options() as $value => $niveau_label) : ?>
          <label>
            <input type="radio" name="_gwseq_label_<?php echo esc_attr($famille); ?>" value="<?php echo esc_attr($value); ?>" <?php checked($labels[$famille], $value); ?>>
            <?php echo esc_html($niveau_label); ?>
          </label>
        <?php endforeach; ?>
      </p>
    <?php endforeach; ?>
  <?php elseif ($is_male_or_gelding) : ?>
    <p>
      <label>
        <input type="checkbox" name="_gwseq_label_sf_genetique_avenir" value="1" <?php checked($labels['sf_genetique_avenir'], '1'); ?>>
        <?php esc_html_e('Étalon SF Génétique Avenir', 'gws-core'); ?>
      </label>
    </p>
  <?php else : ?>
    <p class="description"><?php esc_html_e('Renseignez le sexe du cheval dans l’onglet Identité pour afficher les labels disponibles.', 'gws-core'); ?></p>
  <?php endif; ?>
  <?php
}

function gwseq_save_cheval_labels_meta($post_id) {
  if (!isset($_POST[GWSEQ_CHEVAL_NONCE_FIELD]) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[GWSEQ_CHEVAL_NONCE_FIELD])), GWSEQ_CHEVAL_NONCE_ACTION)) return;
  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
  if (function_exists('wp_is_post_revision') && wp_is_post_revision($post_id)) return;
  if (!current_user_can('edit_post', $post_id)) return;

  // Sexe de CETTE MÊME soumission (jamais relu depuis une meta pas encore enregistrée à ce point,
  // jamais l'ancien sexe déjà en base) — voir le docblock de ce fichier pour le comportement exact
  // lors d'un changement de sexe.
  $sexe = gwseq_sanitize_cheval_identity_input($_POST)['sexe'];
  gwseq_set_cheval_labels($post_id, $_POST, $sexe);
}
add_action('save_post_' . GWSEQ_CPT_CHEVAL, 'gwseq_save_cheval_labels_meta');

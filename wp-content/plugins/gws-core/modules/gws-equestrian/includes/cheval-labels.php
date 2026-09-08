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
 * MISE EN SOMMEIL (lot dédié, voir CHANGELOG.md/README.md de ce dossier) — SOURCE DE VÉRITÉ UNIQUE
 * de l'état de la fonctionnalité Labels : DÉSACTIVÉE PAR DÉFAUT en V1 (le `false` ci-dessous), l'ANSF
 * n'ayant pas autorisé l'usage de ses logos/éléments graphiques officiels sur un site tiers. Décision
 * produit : la fonctionnalité DORT, elle n'est PAS supprimée — modèle métier (ci-dessous), données
 * déjà enregistrées et tests métier sont intégralement conservés (voir gwseq_add_cheval_labels_meta_box()
 * et l'enregistrement conditionnel de gwseq_save_cheval_labels_meta() plus bas, les deux SEULS points
 * de code qui interrogent cette fonction). Volontairement PAS de réglage BO : ce n'est pas une option
 * destinée au client. `apply_filters()` (mécanisme WordPress natif, jamais un filtre métier propre à
 * ce module) est utilisé ici EXCLUSIVEMENT pour permettre au réactivation future de se faire par un
 * simple `add_filter('gwseq_feature_labels_enabled', '__return_true')` (ex. dans un futur mu-plugin
 * ou lors d'un prochain lot dédié) SANS modifier ce fichier, et pour que ce même mécanisme reste
 * testable en process (voir tests/gws-equestrian-cheval-labels-test.php) — le défaut `false` codé ici
 * reste la SEULE valeur qui compte tant qu'aucun filtre n'est ajouté nulle part ailleurs dans le
 * code : aucun autre point du code (fiche cheval publique, partage privé/`/partage/{token}/`, partage
 * d'un cheval, sélection/`/selection/{token}/`, Open Graph, colonnes de la liste d'administration,
 * import IFCE — audités, aucun n'exposait de label avant ce lot) n'ajoute un tel filtre ni ne définit
 * de second test d'activation.
 */
function gwseq_feature_labels_enabled() {
  return (bool) apply_filters('gwseq_feature_labels_enabled', false);
}

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
  // Fonctionnalité en sommeil (voir gwseq_feature_labels_enabled() en tête de fichier) : la boîte
  // n'est simplement jamais enregistrée — WordPress ne l'affiche donc jamais dans l'édition d'un
  // cheval. Conséquence automatique déjà prévue par le système d'onglets existant
  // (includes/cheval-admin-tabs.php : « Un onglet qui ne recueille aucune boîte existante n'est pas
  // affiché. ») : l'onglet « Labels » disparaît lui aussi de lui-même, sans le moindre changement
  // dans ce fichier de configuration ni dans le JavaScript des onglets.
  if (!gwseq_feature_labels_enabled()) return;
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
  // Fonctionnalité en sommeil (voir gwseq_feature_labels_enabled() en tête de fichier) : retour
  // immédiat, AVANT même la vérification du nonce. Vérifié ICI, à l'exécution réelle du hook — jamais
  // au chargement du fichier — pour qu'un futur `add_filter('gwseq_feature_labels_enabled',
  // '__return_true')` ajouté depuis un thème/mu-plugin chargé après gws-core soit systématiquement
  // pris en compte, quel que soit l'ordre de chargement des plugins. La boîte Labels n'étant plus
  // jamais rendue (voir gwseq_add_cheval_labels_meta_box() ci-dessus), les champs `_gwseq_label_*`
  // sont absents de $_POST à chaque sauvegarde réelle tant que le flag est désactivé ; sans cette
  // garde, gwseq_sanitize_cheval_labels_input() traiterait cette absence comme une saisie volontaire
  // (case décochée / « Aucun ») et écraserait silencieusement les labels déjà enregistrés —
  // exactement ce que ce lot interdit (§4/§5 de la demande). Le nonce partagé
  // (GWSEQ_CHEVAL_NONCE_FIELD) continue d'être émis par les AUTRES boîtes de la fiche (identité,
  // indices...) et reste donc présent dans $_POST qu'importe l'état de ce flag — il ne permettrait
  // pas de distinguer « la boîte Labels était masquée » de « l'utilisateur a coché puis décoché tous
  // les labels », d'où la nécessité de cette garde dédiée plutôt que de se fier au seul nonce.
  //
  // CHANGEMENT DE SEXE PENDANT LE SOMMEIL (§5 de la demande) : la règle de nettoyage sexe-dépendante
  // (voir le docblock de gwseq_sanitize_cheval_labels_input() plus bas) n'est déclenchée QUE par
  // cette même fonction, désormais interrompue avant de s'exécuter tant que le flag est désactivé —
  // elle ne s'applique donc plus du tout, y compris lors d'un changement de sexe. Choix délibéré, pas
  // un oubli : cette règle n'est qu'un confort lié à l'interface Labels (elle n'accompagne qu'une
  // saisie faite via sa boîte, désormais inaccessible), jamais une contrainte d'intégrité appliquée
  // indépendamment de l'UI — l'inventer ici reviendrait à ajouter un comportement nouveau que rien ne
  // demande. Les labels déjà enregistrés restent donc en base tels quels, même s'ils deviennent
  // incohérents avec un sexe modifié pendant le sommeil ; à la réactivation de la fonctionnalité,
  // gwseq_render_cheval_labels_box() les affichera de nouveau selon le sexe alors en vigueur, et le
  // PROCHAIN enregistrement volontaire de la fiche via cette boîte réappliquera la règle de nettoyage
  // normalement — aucune migration nécessaire.
  //
  // Fonction exercée DIRECTEMENT par les tests métier existants (voir
  // tests/gws-equestrian-cheval-labels-test.php, qui réactive explicitement le flag via ce même
  // filtre avant de les exécuter) : §4/§6 de la demande imposent de conserver ces tests, ce qui
  // démontre au passage que la réactivation du flag permet bien au code existant, strictement
  // inchangé, de fonctionner exactement comme avant ce lot.
  if (!gwseq_feature_labels_enabled()) return;

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

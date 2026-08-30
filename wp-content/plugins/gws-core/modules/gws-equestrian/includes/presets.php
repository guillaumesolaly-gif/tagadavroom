<?php
/**
 * Modèles de prestations — aide à la création uniquement (§15-23 de la demande).
 *
 * Choisir un modèle ne fait que préremplir, au moment du RENDU, l'écran "Ajouter une prestation"
 * (titre, et éventuellement unité suggérée) via le filtre natif `default_title` plus une lecture
 * directe dans le rendu de la meta box Tarification (includes/prestation-fields.php). Rien n'est
 * jamais écrit en base tant que l'utilisateur n'a pas lui-même enregistré le formulaire, et rien
 * n'identifie ensuite la prestation créée comme provenant d'un modèle : dès l'enregistrement,
 * c'est une prestation GWS ordinaire, en tout point identique à une prestation saisie à la main —
 * la renommer, changer son tarif ou la supprimer n'a aucune incidence sur ce fichier, et une future
 * mise à jour de cette liste de modèles ne réécrira jamais une prestation déjà créée.
 *
 * Aucune création automatique : ce fichier ne crée jamais de contenu lui-même, ni à l'activation du
 * module ni à une mise à jour — recherché explicitement dans les tests (voir
 * tests/gws-equestrian-prestations-logic-test.php, qui vérifie l'absence dans ce fichier de tout
 * appel aux fonctions natives d'insertion de contenu).
 *
 * La nomenclature ci-dessous n'est pas figée métier et peut évoluer librement : aucun nom de
 * modèle n'est testé en dur ailleurs dans le code pour déclencher un comportement particulier —
 * seul le libellé et, pour quelques-uns, une unité suggérée, sont préremplis.
 *
 * DÉPEND de includes/prestation-editor.php pour fonctionner en pratique : le sélecteur ci-dessous
 * s'affiche via le hook `edit_form_after_title`, propre au gabarit d'édition CLASSIQUE de
 * WordPress — voir l'en-tête de ce fichier pour l'explication complète de la cause racine du bug
 * signalé en recette (l'éditeur par blocs, actif par défaut pour ce CPT, ne déclenche jamais ce
 * hook) et de sa correction (désactivation de l'éditeur par blocs pour `gwseq_prestation`).
 *
 * i18n : les libellés de familles et de modèles ci-dessous sont des chaînes du logiciel (la liste
 * de suggestions fournie par GWS Equestrian), donc traductibles comme le reste de l'interface —
 * à distinguer du nom final que l'utilisateur enregistre pour sa prestation, qui devient aussitôt
 * une donnée du site et n'est plus jamais concerné par la traduction du plugin.
 */

if (!defined('ABSPATH')) exit;

/**
 * Identifiants de famille/modèle (clés de tableau) : identifiants techniques STABLES,
 * volontairement non traduits — ce sont eux qui circulent dans l'URL (?gwseq_preset=...) et
 * doivent rester constants quelle que soit la langue de l'administration. Seul 'label' (et le nom
 * de famille) est une chaîne du logiciel, traductible.
 */
function gwseq_prestation_preset_families() {
  return array(
    'pension' => array('label' => __('Pension', 'gws-core'), 'presets' => array(
      'demi_pension' => array('label' => __('Demi-pension', 'gws-core')),
      'pension_pre_sans_infra' => array('label' => __('Pension pré sans infrastructures', 'gws-core')),
      'pension_pre_avec_infra' => array('label' => __('Pension pré avec infrastructures', 'gws-core')),
      'pension' => array('label' => __('Pension', 'gws-core')),
      'pension_complete_cours' => array('label' => __('Pension complète avec cours', 'gws-core')),
      'pension_convalescence' => array('label' => __('Pension convalescence et soins', 'gws-core')),
    )),
    'travail' => array('label' => __('Travail', 'gws-core'), 'presets' => array(
      'pension_travail_entretien' => array('label' => __('Pension travail entretien', 'gws-core')),
      'pension_travail_valorisation' => array('label' => __('Pension travail valorisation', 'gws-core')),
      'seance_travail' => array('label' => __('Séance de travail', 'gws-core'), 'unite' => 'seance'),
      'debourrage' => array('label' => __('Débourrage', 'gws-core'), 'unite' => 'forfait'),
      'forfait_paddock_sortie' => array('label' => __('Forfait paddock / sortie', 'gws-core'), 'unite' => 'forfait'),
    )),
    'cours' => array('label' => __('Cours', 'gws-core'), 'presets' => array(
      'cours_collectif_seance' => array('label' => __('Cours collectif — séance', 'gws-core'), 'unite' => 'seance'),
      'cours_collectif_forfait' => array('label' => __('Cours collectif — forfait X h / semaine', 'gws-core'), 'unite' => 'semaine'),
      'cours_collectif_carte' => array('label' => __('Cours collectif — carte X heures', 'gws-core'), 'unite' => 'forfait'),
      'cours_particulier_seance' => array('label' => __('Cours particulier — séance', 'gws-core'), 'unite' => 'seance'),
      'cours_particulier_forfait' => array('label' => __('Cours particulier — forfait X h / semaine', 'gws-core'), 'unite' => 'semaine'),
      'cours_particulier_carte' => array('label' => __('Cours particulier — carte X heures', 'gws-core'), 'unite' => 'forfait'),
    )),
    'elevage' => array('label' => __('Élevage', 'gws-core'), 'presets' => array(
      'pouliniere_pre' => array('label' => __('Poulinière pré', 'gws-core')),
      'pouliniere_box_paddock' => array('label' => __('Poulinière box + paddock', 'gws-core')),
      'jument_suitee_pre' => array('label' => __('Jument suitée pré', 'gws-core')),
      'jument_suitee_box_paddock' => array('label' => __('Jument suitée box + paddock', 'gws-core')),
      'poulain' => array('label' => __('Poulain', 'gws-core')),
      'etalon_sans_travail' => array('label' => __('Étalon sans travail', 'gws-core')),
      'etalon_avec_travail' => array('label' => __('Étalon avec travail', 'gws-core')),
      'forfait_poulinage' => array('label' => __('Forfait poulinage', 'gws-core'), 'unite' => 'forfait'),
      'forfait_sevrage' => array('label' => __('Forfait sevrage', 'gws-core'), 'unite' => 'forfait'),
      'adoption_jument_nourriciere' => array('label' => __('Adoption jument nourricière', 'gws-core')),
      'location_jument_nourriciere' => array('label' => __('Location jument nourricière', 'gws-core')),
    )),
    'reproduction' => array('label' => __('Reproduction', 'gws-core'), 'presets' => array(
      'iaf_saison' => array('label' => __('IAF / jument / saison', 'gws-core'), 'unite' => 'saison'),
      'iaf_chaleur' => array('label' => __('IAF / chaleur', 'gws-core'), 'unite' => 'chaleur'),
      'iac_saison' => array('label' => __('IAC / saison', 'gws-core'), 'unite' => 'saison'),
      'iac_chaleur' => array('label' => __('IAC / chaleur', 'gws-core'), 'unite' => 'chaleur'),
      'iart_saison' => array('label' => __('IART / saison', 'gws-core'), 'unite' => 'saison'),
      'iart_chaleur' => array('label' => __('IART / chaleur', 'gws-core'), 'unite' => 'chaleur'),
      'suivi_echographique' => array('label' => __('Suivi échographique', 'gws-core')),
      'sexage' => array('label' => __('Sexage', 'gws-core')),
      'recolte_embryon' => array('label' => __('Récolte embryon', 'gws-core')),
      'sexage_embryonnaire' => array('label' => __('Sexage embryonnaire', 'gws-core')),
      'transfert_embryonnaire' => array('label' => __('Transfert embryonnaire', 'gws-core')),
      'location_jument_porteuse' => array('label' => __('Location jument porteuse', 'gws-core')),
      'semence_congelation' => array('label' => __('Semence — congélation', 'gws-core'), 'unite' => 'paillette'),
      'semence_controle_qualite' => array('label' => __('Semence — contrôle qualité', 'gws-core')),
      'semence_stockage' => array('label' => __('Semence — stockage', 'gws-core')),
      'semence_refrigeration' => array('label' => __('Semence — réfrigération', 'gws-core'), 'unite' => 'recolte'),
      'semence_preparation_doses' => array('label' => __('Semence — préparation doses réfrigérées', 'gws-core'), 'unite' => 'dose'),
      'semence_expedition' => array('label' => __('Semence — expédition France / international', 'gws-core'), 'unite' => 'colis'),
      'spermogramme' => array('label' => __('Spermogramme', 'gws-core'), 'unite' => 'etalon'),
    )),
    'autres' => array('label' => __('Autres', 'gws-core'), 'presets' => array(
      'coaching_concours' => array('label' => __('Coaching concours', 'gws-core')),
      'transport' => array('label' => __('Transport', 'gws-core')),
      'tonte' => array('label' => __('Tonte', 'gws-core')),
      'longe' => array('label' => __('Longe', 'gws-core')),
      'marcheur' => array('label' => __('Marcheur', 'gws-core')),
      'soins' => array('label' => __('Soins', 'gws-core')),
      'sorties_paddock' => array('label' => __('Sorties paddock', 'gws-core')),
      'presentation_concours_vente' => array('label' => __('Présentation concours / vente', 'gws-core')),
    )),
  );
}

/** Liste à plat, indexée par l'identifiant technique stable de chaque modèle. */
function gwseq_prestation_preset_flat() {
  $flat = array();
  foreach (gwseq_prestation_preset_families() as $family) {
    foreach ($family['presets'] as $slug => $data) {
      $flat[$slug] = $data;
    }
  }
  return $flat;
}

/**
 * Modèle demandé via l'écran "Ajouter une prestation" (paramètre d'URL, lecture seule, jamais
 * persisté tel quel) — renvoie null si absent ou si le slug ne correspond à aucun modèle connu
 * (jamais d'erreur, jamais de contenu inventé).
 */
function gwseq_get_requested_preset_defaults() {
  if (!isset($_GET['gwseq_preset'])) return null;
  $slug = sanitize_key(wp_unslash($_GET['gwseq_preset']));
  $flat = gwseq_prestation_preset_flat();
  return $flat[$slug] ?? null;
}

function gwseq_prefill_prestation_title($post_title, $post) {
  if (!$post || $post->post_type !== GWSEQ_CPT_PRESTATION) return $post_title;
  $preset = gwseq_get_requested_preset_defaults();
  return $preset ? $preset['label'] : $post_title;
}
add_filter('default_title', 'gwseq_prefill_prestation_title', 10, 2);

/**
 * Affiche le sélecteur de modèle uniquement sur l'écran d'ajout d'une nouvelle prestation
 * (jamais sur une prestation déjà existante, jamais sur un autre post type). Ne fonctionne que si
 * l'écran utilise le gabarit d'édition classique — voir includes/prestation-editor.php (cause
 * racine du bug signalé en recette et sa correction).
 */
function gwseq_render_preset_picker($post) {
  if (!$post || $post->post_type !== GWSEQ_CPT_PRESTATION || $post->post_status !== 'auto-draft') return;
  ?>
  <div class="gwseq-preset-picker" style="margin:12px 0;padding:12px;background:#fff;border:1px solid #dcdcde;">
    <p><strong><?php esc_html_e('Comment souhaitez-vous commencer ?', 'gws-core'); ?></strong></p>
    <p>
      <label for="gwseq-preset-select"><?php esc_html_e('Partir d’un modèle (facultatif — vous pourrez tout modifier ensuite) :', 'gws-core'); ?></label><br>
      <select id="gwseq-preset-select">
        <option value=""><?php esc_html_e('— Créer une prestation personnalisée —', 'gws-core'); ?></option>
        <?php foreach (gwseq_prestation_preset_families() as $family) : ?>
          <optgroup label="<?php echo esc_attr($family['label']); ?>">
            <?php foreach ($family['presets'] as $slug => $preset_data) : ?>
              <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($preset_data['label']); ?></option>
            <?php endforeach; ?>
          </optgroup>
        <?php endforeach; ?>
      </select>
      <button type="button" class="button" id="gwseq-preset-apply"><?php esc_html_e('Préremplir depuis ce modèle', 'gws-core'); ?></button>
    </p>
    <p class="description"><?php esc_html_e('Le modèle ne fait que préremplir le formulaire : une fois enregistrée, la prestation est totalement indépendante et peut être renommée, modifiée ou supprimée librement.', 'gws-core'); ?></p>
  </div>
  <?php
}
add_action('edit_form_after_title', 'gwseq_render_preset_picker');

function gwseq_enqueue_preset_picker_assets($hook) {
  if ($hook !== 'post-new.php') return;
  if (!isset($_GET['post_type']) || sanitize_key(wp_unslash($_GET['post_type'])) !== GWSEQ_CPT_PRESTATION) return;
  wp_enqueue_script('gwseq-presets-admin', GWSEQ_MODULE_URL . 'assets/presets-admin.js', array(), GWSEQ_MODULE_VERSION, true);
}
add_action('admin_enqueue_scripts', 'gwseq_enqueue_preset_picker_assets');

<?php
/**
 * Modèles de prestations — aide à la création uniquement (§15-23 de la demande).
 *
 * Choisir un modèle ne fait que préremplir, au moment du RENDU, l'écran "Ajouter une prestation"
 * (titre, et éventuellement unité suggérée) via les filtres natifs `default_title`/`default_content`
 * plus une lecture directe dans le rendu de la meta box Tarification (includes/prestation-fields.php).
 * Rien n'est jamais écrit en base tant que l'utilisateur n'a pas lui-même enregistré le formulaire,
 * et rien n'identifie ensuite la prestation créée comme provenant d'un modèle : dès l'enregistrement,
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
 */

if (!defined('ABSPATH')) exit;

function gwseq_prestation_preset_families() {
  return array(
    'pension' => array('label' => 'Pension', 'presets' => array(
      'Demi-pension' => array(),
      'Pension pré sans infrastructures' => array(),
      'Pension pré avec infrastructures' => array(),
      'Pension' => array(),
      'Pension complète avec cours' => array(),
      'Pension convalescence et soins' => array(),
    )),
    'travail' => array('label' => 'Travail', 'presets' => array(
      'Pension travail entretien' => array(),
      'Pension travail valorisation' => array(),
      'Séance de travail' => array('unite' => 'seance'),
      'Débourrage' => array('unite' => 'forfait'),
      'Forfait paddock / sortie' => array('unite' => 'forfait'),
    )),
    'cours' => array('label' => 'Cours', 'presets' => array(
      'Cours collectif — séance' => array('unite' => 'seance'),
      'Cours collectif — forfait X h / semaine' => array('unite' => 'semaine'),
      'Cours collectif — carte X heures' => array('unite' => 'forfait'),
      'Cours particulier — séance' => array('unite' => 'seance'),
      'Cours particulier — forfait X h / semaine' => array('unite' => 'semaine'),
      'Cours particulier — carte X heures' => array('unite' => 'forfait'),
    )),
    'elevage' => array('label' => 'Élevage', 'presets' => array(
      'Poulinière pré' => array(),
      'Poulinière box + paddock' => array(),
      'Jument suitée pré' => array(),
      'Jument suitée box + paddock' => array(),
      'Poulain' => array(),
      'Étalon sans travail' => array(),
      'Étalon avec travail' => array(),
      'Forfait poulinage' => array('unite' => 'forfait'),
      'Forfait sevrage' => array('unite' => 'forfait'),
      'Adoption jument nourricière' => array(),
      'Location jument nourricière' => array(),
    )),
    'reproduction' => array('label' => 'Reproduction', 'presets' => array(
      'IAF / jument / saison' => array('unite' => 'saison'),
      'IAF / chaleur' => array('unite' => 'chaleur'),
      'IAC / saison' => array('unite' => 'saison'),
      'IAC / chaleur' => array('unite' => 'chaleur'),
      'IART / saison' => array('unite' => 'saison'),
      'IART / chaleur' => array('unite' => 'chaleur'),
      'Suivi échographique' => array(),
      'Sexage' => array(),
      'Récolte embryon' => array(),
      'Sexage embryonnaire' => array(),
      'Transfert embryonnaire' => array(),
      'Location jument porteuse' => array(),
      'Semence — congélation' => array('unite' => 'dose'),
      'Semence — contrôle qualité' => array(),
      'Semence — stockage' => array(),
      'Semence — réfrigération' => array(),
      'Semence — préparation doses réfrigérées' => array('unite' => 'dose'),
      'Semence — expédition France / international' => array(),
      'Spermogramme' => array(),
    )),
    'autres' => array('label' => 'Autres', 'presets' => array(
      'Coaching concours' => array(),
      'Transport' => array(),
      'Tonte' => array(),
      'Longe' => array(),
      'Marcheur' => array(),
      'Soins' => array(),
      'Sorties paddock' => array(),
      'Présentation concours / vente' => array(),
    )),
  );
}

/** Liste à plat, indexée par un identifiant dérivé du libellé (jamais écrit à la main). */
function gwseq_prestation_preset_flat() {
  $flat = array();
  foreach (gwseq_prestation_preset_families() as $family) {
    foreach ($family['presets'] as $label => $data) {
      $flat[sanitize_title($label)] = array_merge(array('label' => $label), $data);
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
 * (jamais sur une prestation déjà existante, jamais sur un autre post type).
 */
function gwseq_render_preset_picker($post) {
  if (!$post || $post->post_type !== GWSEQ_CPT_PRESTATION || $post->post_status !== 'auto-draft') return;
  ?>
  <div class="gwseq-preset-picker" style="margin:12px 0;padding:12px;background:#fff;border:1px solid #dcdcde;">
    <p><strong>Comment souhaitez-vous commencer ?</strong></p>
    <p>
      <label for="gwseq-preset-select">Partir d’un modèle (facultatif — vous pourrez tout modifier ensuite) :</label><br>
      <select id="gwseq-preset-select">
        <option value="">— Créer une prestation personnalisée —</option>
        <?php foreach (gwseq_prestation_preset_families() as $family) : ?>
          <optgroup label="<?php echo esc_attr($family['label']); ?>">
            <?php foreach (array_keys($family['presets']) as $label) : ?>
              <option value="<?php echo esc_attr(sanitize_title($label)); ?>"><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
          </optgroup>
        <?php endforeach; ?>
      </select>
      <button type="button" class="button" id="gwseq-preset-apply">Préremplir depuis ce modèle</button>
    </p>
    <p class="description">Le modèle ne fait que préremplir le formulaire : une fois enregistrée, la prestation est totalement indépendante et peut être renommée, modifiée ou supprimée librement.</p>
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

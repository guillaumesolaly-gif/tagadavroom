<?php
/**
 * Champs BO spécifiques à la fiche cheval PDF (Lot "PDF Cheval & Catalogue", Lot 3A bis).
 *
 * TYPE DE FICHE PDF (§1 de la demande) : `_gwseq_pdf_template` — 'etalon'|'pouliniere'|'sport_vente',
 * ou '' pour "Automatique" (résolu à partir du sexe, voir gwseq_resolve_cheval_pdf_template()
 * ci-dessous, fonction PURE réutilisée par includes/cheval-pdf.php). L'utilisateur peut toujours
 * forcer un type explicite, y compris à contre-sens du sexe (ex. présenter un hongre de sport avec
 * le template Étalon serait absurde métier mais reste TECHNIQUEMENT permis — aucune validation
 * croisée sexe/template n'est imposée ici, cohérent avec le reste du module qui ne bloque jamais
 * une combinaison de champs indépendants).
 *
 * STATUT OSTÉO-ARTICULAIRE — AUDIT PRÉALABLE (§13) : `_gwseq_osteo_articulaire` (texte libre,
 * `includes/cheval-editorial.php`) existe DÉJÀ mais est un COMMENTAIRE narratif ("Information
 * synthétique destinée à la fiche commerciale"), pas une note chiffrée. La demande veut ICI une
 * note structurée 1-5 affichée en étoiles sur le PDF (`★★★☆☆`) — donnée de nature différente
 * (entier borné vs texte libre), jamais la même chose : nouvelle meta `_gwseq_statut_osteo_articulaire`,
 * le champ texte existant reste totalement inchangé, aucun champ renommé ni migré. Les deux
 * champs coexistent dans le BO sans ambiguïté (libellés distincts).
 *
 * STUD-BOOKS D'APPROBATION (§13) : réutilise le référentiel races/stud-books déjà existant
 * (`includes/race-referentiel.php`, `gwseq_race_referentiel_entries()`) — jamais une seconde liste
 * de codes dupliquée. Seules les entrées `type === 'race'` sont proposées (une "appellation" comme
 * OC/ONC/OE n'est pas un stud-book d'approbation). Valeur stockée : tableau de codes canoniques
 * (`_gwseq_studbooks_approbation`), chaque code validé contre ce référentiel à l'écriture — jamais
 * une chaîne libre qui pourrait diverger du référentiel.
 *
 * WFFS (§13) : texte libre volontairement SANS nomenclature imposée ("N/N", "Non porteur",
 * "Porteur"... au choix de l'utilisateur) — même philosophie que "Ostéo-articulaire" existant :
 * une donnée affichable simplement, jamais un champ structuré médical.
 */

if (!defined('ABSPATH')) exit;

const GWSEQ_CHEVAL_WFFS_MAX_LENGTH = 40;

/* -------------------------------------------------------------------------------------------
 * Type de fiche PDF.
 * ----------------------------------------------------------------------------------------- */

function gwseq_cheval_pdf_template_options() {
  return array(
    '' => __('Automatique', 'gws-core'),
    'etalon' => __('Étalon', 'gws-core'),
    'pouliniere' => __('Poulinière', 'gws-core'),
    'sport_vente' => __('Sport / Vente', 'gws-core'),
  );
}

function gwseq_get_cheval_pdf_template($cheval_id) {
  $value = (string) get_post_meta((int) $cheval_id, '_gwseq_pdf_template', true);
  return array_key_exists($value, gwseq_cheval_pdf_template_options()) ? $value : '';
}

function gwseq_set_cheval_pdf_template($cheval_id, $raw) {
  $cheval_id = (int) $cheval_id;
  if (!$cheval_id) return false;
  $value = sanitize_key(wp_unslash($raw));
  if (!array_key_exists($value, gwseq_cheval_pdf_template_options())) $value = '';
  update_post_meta($cheval_id, '_gwseq_pdf_template', $value);
  return true;
}

/**
 * Résolution EFFECTIVE du template — fonction PURE (aucun accès BDD, aucune dépendance
 * WordPress) : un type explicitement choisi fait toujours foi ; en mode "Automatique" ('' ou toute
 * valeur inconnue), le sexe déjà résolu détermine le repli — mâle -> Étalon, femelle -> Poulinière,
 * hongre (ou sexe non renseigné) -> Sport / Vente (repli sûr : jamais de bloc Production ni de
 * bloc Étalon pour un sexe ambigu/absent).
 */
function gwseq_resolve_cheval_pdf_template($explicit_template, $sexe) {
  if (in_array($explicit_template, array('etalon', 'pouliniere', 'sport_vente'), true)) return $explicit_template;
  if ($sexe === 'male') return 'etalon';
  if ($sexe === 'female') return 'pouliniere';
  return 'sport_vente';
}

/* -------------------------------------------------------------------------------------------
 * Statut ostéo-articulaire (note 1-5, distincte du commentaire texte existant — voir docblock).
 * ----------------------------------------------------------------------------------------- */

/**
 * 0 = non renseigné (jamais confondu avec une note réelle) ; sinon un entier 1-5.
 */
function gwseq_get_cheval_statut_osteo_articulaire($cheval_id) {
  return gwseq_sanitize_cheval_statut_osteo_articulaire(get_post_meta((int) $cheval_id, '_gwseq_statut_osteo_articulaire', true));
}

function gwseq_sanitize_cheval_statut_osteo_articulaire($raw) {
  if ($raw === '' || $raw === null) return 0;
  $value = (int) $raw;
  return ($value >= 1 && $value <= 5) ? $value : 0;
}

function gwseq_set_cheval_statut_osteo_articulaire($cheval_id, $raw) {
  $cheval_id = (int) $cheval_id;
  if (!$cheval_id) return false;
  update_post_meta($cheval_id, '_gwseq_statut_osteo_articulaire', gwseq_sanitize_cheval_statut_osteo_articulaire($raw));
  return true;
}

/* -------------------------------------------------------------------------------------------
 * Stud-book(s) d'approbation — réutilise le référentiel existant, jamais une seconde liste.
 * ----------------------------------------------------------------------------------------- */

/**
 * {code => libellé GWS}, uniquement les entrées 'race' du référentiel existant (jamais les
 * 'appellation' — OC/ONC/OE ne sont pas des stud-books d'approbation). Triée alphabétiquement par
 * libellé pour un multi-sélecteur exploitable (154 entrées au total dans le référentiel, ~152 ici).
 */
function gwseq_cheval_studbook_approbation_options() {
  $options = array();
  foreach (gwseq_race_referentiel_entries() as $entry) {
    if (($entry['type'] ?? '') !== 'race') continue;
    $options[$entry['code']] = $entry['gws'];
  }
  asort($options, SORT_STRING | SORT_FLAG_CASE);
  return $options;
}

function gwseq_get_cheval_studbooks_approbation($cheval_id) {
  $raw = get_post_meta((int) $cheval_id, '_gwseq_studbooks_approbation', true);
  return is_array($raw) ? $raw : array();
}

/**
 * Sanitise une liste brute de codes : chaque code doit exister réellement dans le référentiel
 * (`gwseq_cheval_studbook_approbation_options()` ci-dessus, jamais une chaîne libre inventée),
 * dédupliquée, ordre de sélection préservé (aucun tri imposé à l'écriture — c'est l'affichage PDF,
 * s'il choisit un ordre différent, qui en décide, jamais le stockage).
 */
function gwseq_sanitize_cheval_studbooks_approbation($raw_codes) {
  if (!is_array($raw_codes)) return array();
  $valid_codes = gwseq_cheval_studbook_approbation_options();
  $clean = array();
  foreach ($raw_codes as $raw_code) {
    if (is_array($raw_code)) continue;
    $code = sanitize_text_field(wp_unslash((string) $raw_code));
    if ($code === '' || !array_key_exists($code, $valid_codes)) continue;
    if (in_array($code, $clean, true)) continue;
    $clean[] = $code;
  }
  return $clean;
}

function gwseq_set_cheval_studbooks_approbation($cheval_id, $raw_codes) {
  $cheval_id = (int) $cheval_id;
  if (!$cheval_id) return false;
  update_post_meta($cheval_id, '_gwseq_studbooks_approbation', gwseq_sanitize_cheval_studbooks_approbation($raw_codes));
  return true;
}

/* -------------------------------------------------------------------------------------------
 * WFFS — texte libre court, sans nomenclature imposée.
 * ----------------------------------------------------------------------------------------- */

function gwseq_get_cheval_wffs($cheval_id) {
  return (string) get_post_meta((int) $cheval_id, '_gwseq_wffs', true);
}

function gwseq_sanitize_cheval_wffs($raw) {
  $value = gws_core_field_sanitize('text', $raw);
  return mb_substr($value, 0, GWSEQ_CHEVAL_WFFS_MAX_LENGTH);
}

function gwseq_set_cheval_wffs($cheval_id, $raw) {
  $cheval_id = (int) $cheval_id;
  if (!$cheval_id) return false;
  update_post_meta($cheval_id, '_gwseq_wffs', gwseq_sanitize_cheval_wffs($raw));
  return true;
}

/* -------------------------------------------------------------------------------------------
 * Enregistrement des meta + boîte d'administration.
 * ----------------------------------------------------------------------------------------- */

function gwseq_register_cheval_pdf_fields_meta() {
  register_post_meta(GWSEQ_CPT_CHEVAL, '_gwseq_pdf_template', array('single' => true, 'type' => 'string', 'show_in_rest' => false));
  register_post_meta(GWSEQ_CPT_CHEVAL, '_gwseq_statut_osteo_articulaire', array('single' => true, 'type' => 'integer', 'show_in_rest' => false));
  register_post_meta(GWSEQ_CPT_CHEVAL, '_gwseq_studbooks_approbation', array('single' => true, 'type' => 'array', 'show_in_rest' => false));
  register_post_meta(GWSEQ_CPT_CHEVAL, '_gwseq_wffs', array('single' => true, 'type' => 'string', 'show_in_rest' => false));
}
add_action('init', 'gwseq_register_cheval_pdf_fields_meta');

function gwseq_add_cheval_pdf_fields_meta_box() {
  add_meta_box('gwseq-cheval-pdf-fields', __('Fiche PDF', 'gws-core'), 'gwseq_render_cheval_pdf_fields_box', GWSEQ_CPT_CHEVAL, 'side', 'default');
}
add_action('add_meta_boxes_' . GWSEQ_CPT_CHEVAL, 'gwseq_add_cheval_pdf_fields_meta_box');

function gwseq_render_cheval_pdf_fields_box($post) {
  wp_nonce_field(GWSEQ_CHEVAL_NONCE_ACTION, GWSEQ_CHEVAL_NONCE_FIELD);
  $template = gwseq_get_cheval_pdf_template($post->ID);
  $statut_osteo = gwseq_get_cheval_statut_osteo_articulaire($post->ID);
  $studbooks = gwseq_get_cheval_studbooks_approbation($post->ID);
  $wffs = gwseq_get_cheval_wffs($post->ID);
  ?>
  <p>
    <label for="gwseq-cheval-pdf-template"><strong><?php esc_html_e('Type de fiche PDF', 'gws-core'); ?></strong></label><br>
    <select class="widefat" id="gwseq-cheval-pdf-template" name="_gwseq_pdf_template">
      <?php foreach (gwseq_cheval_pdf_template_options() as $value => $label) : ?>
        <option value="<?php echo esc_attr($value); ?>"<?php selected($template, $value); ?>><?php echo esc_html($label); ?></option>
      <?php endforeach; ?>
    </select>
    <span class="description"><?php esc_html_e('"Automatique" choisit selon le sexe (Mâle → Étalon, Femelle → Poulinière, Hongre → Sport / Vente) — vous pouvez toujours forcer un type précis.', 'gws-core'); ?></span>
  </p>
  <p>
    <label for="gwseq-cheval-statut-osteo"><strong><?php esc_html_e('Statut ostéo-articulaire (note)', 'gws-core'); ?></strong></label><br>
    <select class="widefat" id="gwseq-cheval-statut-osteo" name="_gwseq_statut_osteo_articulaire">
      <option value="0"<?php selected($statut_osteo, 0); ?>><?php esc_html_e('— Non renseigné —', 'gws-core'); ?></option>
      <?php for ($i = 1; $i <= 5; $i++) : ?>
        <option value="<?php echo (int) $i; ?>"<?php selected($statut_osteo, $i); ?>><?php echo esc_html(str_repeat('★', $i) . str_repeat('☆', 5 - $i)); ?></option>
      <?php endfor; ?>
    </select>
    <span class="description"><?php esc_html_e('Note affichée en étoiles sur la fiche Étalon — distinct du commentaire "Ostéo-articulaire" (Informations complémentaires), qui reste inchangé.', 'gws-core'); ?></span>
  </p>
  <p>
    <label for="gwseq-cheval-studbooks"><strong><?php esc_html_e('Stud-book(s) d’approbation', 'gws-core'); ?></strong></label><br>
    <select class="widefat" id="gwseq-cheval-studbooks" name="_gwseq_studbooks_approbation[]" multiple size="8">
      <?php foreach (gwseq_cheval_studbook_approbation_options() as $code => $label) : ?>
        <option value="<?php echo esc_attr($code); ?>"<?php selected(in_array($code, $studbooks, true)); ?>><?php echo esc_html($code . ' — ' . $label); ?></option>
      <?php endforeach; ?>
    </select>
    <span class="description"><?php esc_html_e('Ctrl/Cmd + clic pour sélectionner plusieurs stud-books. Affichés sur la fiche Étalon uniquement.', 'gws-core'); ?></span>
  </p>
  <p>
    <label for="gwseq-cheval-wffs"><strong><?php esc_html_e('WFFS', 'gws-core'); ?></strong></label><br>
    <input type="text" class="widefat" id="gwseq-cheval-wffs" name="_gwseq_wffs" maxlength="<?php echo (int) GWSEQ_CHEVAL_WFFS_MAX_LENGTH; ?>" value="<?php echo esc_attr($wffs); ?>" placeholder="N/N">
    <span class="description"><?php esc_html_e('Texte libre (ex. "N/N", "Non porteur", "Porteur") — affiché sur la fiche Étalon uniquement.', 'gws-core'); ?></span>
  </p>
  <hr>
  <?php gwseq_render_cheval_pdf_export_actions($post); ?>
  <?php
}

/**
 * Zone « Prévisualiser / Télécharger » (Lot 3B, arbitrage client §10) : deux boutons distincts,
 * même service sous-jacent (includes/cheval-pdf-export.php) — seule la disposition HTTP change.
 * Fiche jamais encore enregistrée (auto-brouillon) -> aucun bouton, un cheval sans ID stable n'a pas
 * encore de nom de fichier ni de sens à exporter. Bibliothèque PDF absente (§4, ne jamais cacher
 * silencieusement) -> message explicite à la place des boutons, jamais un bouton qui échouerait.
 */
function gwseq_render_cheval_pdf_export_actions($post) {
  if (empty($post->ID) || ($post->post_status ?? '') === 'auto-draft') return;
  if (!gws_core_pdf_available()) {
    echo '<p class="description">' . esc_html__('La génération PDF n’est pas disponible sur cet environnement.', 'gws-core') . '</p>';
    return;
  }
  ?>
  <p>
    <a class="button" style="width:100%;text-align:center;box-sizing:border-box;" target="_blank" rel="noopener" href="<?php echo esc_url(gwseq_horse_pdf_export_url('inline', $post->ID)); ?>"><?php esc_html_e('Prévisualiser le PDF', 'gws-core'); ?></a>
  </p>
  <p>
    <a class="button button-primary" style="width:100%;text-align:center;box-sizing:border-box;" href="<?php echo esc_url(gwseq_horse_pdf_export_url('attachment', $post->ID)); ?>"><?php esc_html_e('Télécharger le PDF', 'gws-core'); ?></a>
  </p>
  <?php
}

function gwseq_save_cheval_pdf_fields_meta($post_id) {
  if (!isset($_POST[GWSEQ_CHEVAL_NONCE_FIELD]) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[GWSEQ_CHEVAL_NONCE_FIELD])), GWSEQ_CHEVAL_NONCE_ACTION)) return;
  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
  if (function_exists('wp_is_post_revision') && wp_is_post_revision($post_id)) return;
  if (!current_user_can('edit_post', $post_id)) return;

  // Champs de CETTE boîte uniquement — jamais un accès à $_POST au-delà (même discipline que
  // gwseq_save_cheval_meta()/gwseq_save_cheval_editorial_meta(), hooks indépendants).
  if (isset($_POST['_gwseq_pdf_template'])) gwseq_set_cheval_pdf_template($post_id, $_POST['_gwseq_pdf_template']);
  if (isset($_POST['_gwseq_statut_osteo_articulaire'])) gwseq_set_cheval_statut_osteo_articulaire($post_id, wp_unslash($_POST['_gwseq_statut_osteo_articulaire']));
  gwseq_set_cheval_studbooks_approbation($post_id, $_POST['_gwseq_studbooks_approbation'] ?? array());
  if (isset($_POST['_gwseq_wffs'])) gwseq_set_cheval_wffs($post_id, $_POST['_gwseq_wffs']);
}
add_action('save_post_' . GWSEQ_CPT_CHEVAL, 'gwseq_save_cheval_pdf_fields_meta');

<?php
/**
 * Cheval — indices sportifs et génétiques (Étape 6, §2-3 de la demande).
 *
 * INDICES SPORTIFS (ISO, ICC, IDR) : chacun stocké en trois composants séparés — valeur (entier),
 * année (entier) et coefficient de détermination/CD (nombre, décimal, facultatif) — jamais dans
 * une chaîne unique du type "142 (2025)" (§2). Le CD a été ajouté à ce trio lors de l'import IFCE
 * (une fiche IFCE officielle le fournit systématiquement pour ces trois indices, ex. « ISO 115
 * (0.70) (2023) ») — auparavant réservé aux indices génétiques (BSO/BCC/BDR) uniquement. Une seule
 * valeur par indice et par cheval : GWS Equestrian ne conserve JAMAIS d'historique annuel en V1,
 * seul l'indice que le professionnel souhaite présenter (normalement son meilleur) est enregistré
 * — une nouvelle saisie remplace simplement l'ancienne valeur, elle ne s'ajoute jamais à une liste.
 * Les trois indices sont indépendants et tous facultatifs : un cheval peut n'en avoir aucun,
 * un seul, ou les trois, sans qu'aucune combinaison ne soit imposée ou déduite d'une autre.
 *
 * INDICES GÉNÉTIQUES (BSO, BCC, BDR) : structure différente — valeur (nombre, signé, décimal
 * possible) et coefficient de détermination/CD (nombre, décimal) — jamais d'année ici (§3, à la
 * différence des indices sportifs). Le signe positif d'une valeur (ex. "+12") n'est PAS perdu :
 * il est stocké comme un nombre PHP positif (12), le signe restant implicite à cette étape
 * (aucune présentation publique n'est développée maintenant, voir gwseq_cheval_genetic_indice_label()
 * ci-dessous pour l'unique endroit qui ajoute explicitement le "+" à l'affichage — jamais dans la
 * donnée stockée elle-même).
 *
 * RÈGLE MÉTIER UNIQUE ET PROGRAMMATIQUE (§11, même architecture que le pedigree — Étape 5) :
 * gwseq_set_cheval_sport_indice()/gwseq_set_cheval_genetic_indice() sont des fonctions métier
 * pures, jamais couplées à $_POST ni à un nonce/capability — réutilisables telles quelles par un
 * futur importeur CSV/XLSX, une duplication de fiche, une API, ou une synchronisation GWS Network.
 * Le formulaire d'édition (gwseq_save_cheval_indices_meta()) n'est qu'UN client parmi d'autres
 * possibles de ces fonctions.
 */

if (!defined('ABSPATH')) exit;

/* -------------------------------------------------------------------------------------------
 * Enregistrement des meta (jamais exposées en REST, même politique que le reste du module).
 * ----------------------------------------------------------------------------------------- */

function gwseq_cheval_sport_indice_keys() {
  return array('iso', 'icc', 'idr');
}

function gwseq_cheval_genetic_indice_keys() {
  return array('bso', 'bcc', 'bdr');
}

function gwseq_register_cheval_indices_meta() {
  foreach (gwseq_cheval_sport_indice_keys() as $key) {
    register_post_meta(GWSEQ_CPT_CHEVAL, '_gwseq_' . $key . '_valeur', array('single' => true, 'type' => 'integer', 'show_in_rest' => false));
    register_post_meta(GWSEQ_CPT_CHEVAL, '_gwseq_' . $key . '_annee', array('single' => true, 'type' => 'integer', 'show_in_rest' => false));
    register_post_meta(GWSEQ_CPT_CHEVAL, '_gwseq_' . $key . '_cd', array('single' => true, 'type' => 'number', 'show_in_rest' => false));
  }
  foreach (gwseq_cheval_genetic_indice_keys() as $key) {
    register_post_meta(GWSEQ_CPT_CHEVAL, '_gwseq_' . $key . '_valeur', array('single' => true, 'type' => 'number', 'show_in_rest' => false));
    register_post_meta(GWSEQ_CPT_CHEVAL, '_gwseq_' . $key . '_cd', array('single' => true, 'type' => 'number', 'show_in_rest' => false));
  }
}
add_action('init', 'gwseq_register_cheval_indices_meta');

/* -------------------------------------------------------------------------------------------
 * Fonctions pures : sanitation, lecture, persistance. Aucune dépendance à $_POST.
 * ----------------------------------------------------------------------------------------- */

/**
 * Valeur d'un indice sportif (ISO/ICC/IDR) : un entier, sans borne arbitraire (aucune limite
 * connue/documentée pour ces indices, à la différence de l'année de naissance ou de la taille à
 * l'Étape 4) — vide si non numérique, jamais une erreur.
 */
function gwseq_sanitize_cheval_sport_indice_valeur($raw) {
  if ($raw === '' || $raw === null || !is_numeric($raw)) return '';
  return (int) round((float) $raw);
}

/**
 * Année d'un indice sportif : contrairement à l'année de naissance (gwseq_sanitize_cheval_annee_naissance(),
 * cheval-fields.php, qui autorise l'année courante + 1 pour un poulain attendu), un indice est
 * toujours RÉTROSPECTIF — il ne peut jamais concerner une année future. Réutilise la même borne
 * basse documentée (GWSEQ_CHEVAL_ANNEE_MIN, définie dans cheval-fields.php) plutôt que de dupliquer
 * ce nombre magique, mais borne haute propre : l'année en cours, jamais +1.
 */
function gwseq_sanitize_cheval_indice_annee($raw) {
  if ($raw === '' || $raw === null || !is_numeric($raw)) return '';
  $annee = (int) $raw;
  if ($annee < GWSEQ_CHEVAL_ANNEE_MIN || $annee > (int) gmdate('Y')) return '';
  return $annee;
}

/**
 * Transforme un tableau à la forme de $_POST (ou de tout appel programmatique) en {valeur, annee, cd}
 * propres pour un indice sportif. Fonction pure — les trois composants sont sanitisés et acceptés
 * indépendamment les uns des autres (une valeur sans année ni CD, par exemple, reste une saisie
 * valide — §2 : ne jamais imposer qu'ils soient tous renseignés ensemble). Le CD utilise le même
 * sanitiseur générique que pour les indices génétiques (gws_core_field_sanitize('number', ...)) —
 * aucune duplication de logique de validation entre les deux familles d'indices.
 */
function gwseq_sanitize_cheval_sport_indice_input($raw) {
  $raw = is_array($raw) ? $raw : array();
  return array(
    'valeur' => gwseq_sanitize_cheval_sport_indice_valeur($raw['valeur'] ?? ''),
    'annee' => gwseq_sanitize_cheval_indice_annee($raw['annee'] ?? ''),
    'cd' => gws_core_field_sanitize('number', $raw['cd'] ?? ''),
  );
}

/**
 * Transforme un tableau à la forme de $_POST en {valeur, cd} propres pour un indice génétique.
 * Délègue à gws_core_field_sanitize('number', ...) (cœur gws-core) : is_numeric() -> (float),
 * '' sinon — gère nativement le signe et les décimales, jamais de perte du signe positif au
 * niveau du STOCKAGE (voir la note de présentation en tête de fichier pour l'affichage).
 */
function gwseq_sanitize_cheval_genetic_indice_input($raw) {
  $raw = is_array($raw) ? $raw : array();
  return array(
    'valeur' => gws_core_field_sanitize('number', $raw['valeur'] ?? ''),
    'cd' => gws_core_field_sanitize('number', $raw['cd'] ?? ''),
  );
}

/**
 * Persiste UN indice sportif (ISO, ICC ou IDR) — fonction métier réutilisable, jamais couplée à
 * $_POST ni à un nonce (§11). $indice_key doit être l'une des valeurs de
 * gwseq_cheval_sport_indice_keys() ; tout autre appel est refusé (retourne false) sans effet.
 * N'écrit jamais d'historique : chaque appel REMPLACE la valeur/année précédemment enregistrée
 * pour cet indice, exactement comme pour n'importe quelle autre meta simple du module.
 */
function gwseq_set_cheval_sport_indice($cheval_id, $indice_key, $raw_args) {
  $cheval_id = (int) $cheval_id;
  if (!$cheval_id || !in_array($indice_key, gwseq_cheval_sport_indice_keys(), true)) return false;
  $clean = gwseq_sanitize_cheval_sport_indice_input($raw_args);
  update_post_meta($cheval_id, '_gwseq_' . $indice_key . '_valeur', $clean['valeur']);
  update_post_meta($cheval_id, '_gwseq_' . $indice_key . '_annee', $clean['annee']);
  update_post_meta($cheval_id, '_gwseq_' . $indice_key . '_cd', $clean['cd']);
  return true;
}

function gwseq_get_cheval_sport_indice($cheval_id, $indice_key) {
  if (!in_array($indice_key, gwseq_cheval_sport_indice_keys(), true)) return array('valeur' => '', 'annee' => '', 'cd' => '');
  return array(
    'valeur' => get_post_meta($cheval_id, '_gwseq_' . $indice_key . '_valeur', true),
    'annee' => get_post_meta($cheval_id, '_gwseq_' . $indice_key . '_annee', true),
    'cd' => get_post_meta($cheval_id, '_gwseq_' . $indice_key . '_cd', true),
  );
}

/**
 * Persiste UN indice génétique (BSO, BCC ou BDR) — même garanties que gwseq_set_cheval_sport_indice()
 * ci-dessus (fonction métier pure, réutilisable hors formulaire, jamais d'historique).
 */
function gwseq_set_cheval_genetic_indice($cheval_id, $indice_key, $raw_args) {
  $cheval_id = (int) $cheval_id;
  if (!$cheval_id || !in_array($indice_key, gwseq_cheval_genetic_indice_keys(), true)) return false;
  $clean = gwseq_sanitize_cheval_genetic_indice_input($raw_args);
  update_post_meta($cheval_id, '_gwseq_' . $indice_key . '_valeur', $clean['valeur']);
  update_post_meta($cheval_id, '_gwseq_' . $indice_key . '_cd', $clean['cd']);
  return true;
}

function gwseq_get_cheval_genetic_indice($cheval_id, $indice_key) {
  if (!in_array($indice_key, gwseq_cheval_genetic_indice_keys(), true)) return array('valeur' => '', 'cd' => '');
  return array(
    'valeur' => get_post_meta($cheval_id, '_gwseq_' . $indice_key . '_valeur', true),
    'cd' => get_post_meta($cheval_id, '_gwseq_' . $indice_key . '_cd', true),
  );
}

/**
 * Libellés d'affichage (§10 : préparation multicanale — un point d'entrée unique, réutilisable
 * plus tard par le web/PDF/catalogue, jamais une reconstruction ad hoc dans chaque futur
 * renderer). Ne construisent JAMAIS un texte à partir d'une donnée absente (§ principe général du
 * projet : une donnée vide ne doit jamais produire un fragment vide ou trompeur) : chaîne vide si
 * la valeur elle-même est absente, quelle que soit la présence de l'année/du CD.
 */
function gwseq_cheval_sport_indice_label($valeur, $annee) {
  if ($valeur === '' || $valeur === null) return '';
  if ($annee === '' || $annee === null) return (string) $valeur;
  return sprintf('%s (%s)', $valeur, $annee);
}

/**
 * Présentation à deux décimales d'un coefficient de détermination (§1 de l'ajustement UX
 * post-recette : « 0.90 », jamais « 0.9 ») — UNIQUEMENT une présentation, jamais une
 * transformation du stockage : la meta reste le nombre PHP exact tel que sanitisé
 * (gws_core_field_sanitize('number', ...)), cette fonction ne fait que le formater pour
 * l'affichage (formulaire admin, libellé). Séparateur décimal volontairement le point à ce stade
 * (aucune localisation ajoutée maintenant) — un futur renderer pourra localiser cet affichage
 * (« 0,90 » en français) sans qu'aucune donnée n'ait besoin d'être modifiée pour cela.
 */
function gwseq_format_cheval_indice_cd($cd) {
  if ($cd === '' || $cd === null) return '';
  return number_format((float) $cd, 2, '.', '');
}

/**
 * Le signe "+" d'une valeur génétique positive est ajouté UNIQUEMENT ici, à l'affichage — jamais
 * dans la donnée stockée (voir la note en tête de fichier). Une valeur négative garde son "-"
 * natif (le format numérique PHP le fournit déjà) ; une valeur nulle (0) n'est ni "+0" ni "-0",
 * simplement "0". Le CD est présenté à deux décimales via gwseq_format_cheval_indice_cd()
 * ci-dessus — ex. « +12 (0.90) », jamais « +12 (0.9) ».
 */
function gwseq_cheval_genetic_indice_label($valeur, $cd) {
  if ($valeur === '' || $valeur === null) return '';
  $valeur_num = (float) $valeur;
  $valeur_display = (fmod($valeur_num, 1.0) === 0.0) ? (string) (int) $valeur_num : (string) $valeur_num;
  $valeur_label = ($valeur_num > 0 ? '+' : '') . $valeur_display;
  $cd_display = gwseq_format_cheval_indice_cd($cd);
  if ($cd_display === '') return $valeur_label;
  return sprintf('%s (%s)', $valeur_label, $cd_display);
}

/* -------------------------------------------------------------------------------------------
 * Meta box et sauvegarde (glue WordPress) — un client parmi d'autres des fonctions ci-dessus.
 * ----------------------------------------------------------------------------------------- */

function gwseq_add_cheval_indices_meta_box() {
  add_meta_box('gwseq-cheval-indices', __('Indices', 'gws-core'), 'gwseq_render_cheval_indices_box', GWSEQ_CPT_CHEVAL, 'normal', 'default');
}
add_action('add_meta_boxes_' . GWSEQ_CPT_CHEVAL, 'gwseq_add_cheval_indices_meta_box');

function gwseq_cheval_sport_indice_field_labels() {
  return array(
    'iso' => __('ISO', 'gws-core'),
    'icc' => __('ICC', 'gws-core'),
    'idr' => __('IDR', 'gws-core'),
  );
}

function gwseq_cheval_genetic_indice_field_labels() {
  return array(
    'bso' => __('BSO', 'gws-core'),
    'bcc' => __('BCC', 'gws-core'),
    'bdr' => __('BDR', 'gws-core'),
  );
}

function gwseq_render_cheval_indices_box($post) {
  wp_nonce_field(GWSEQ_CHEVAL_NONCE_ACTION, GWSEQ_CHEVAL_NONCE_FIELD);
  ?>
  <h4><?php esc_html_e('Indices sportifs', 'gws-core'); ?></h4>
  <p class="description"><?php esc_html_e('Un seul indice par type et par cheval — normalement le meilleur obtenu, jamais un historique année par année. Exemple : ISO 115 (CD 0,70) (2023).', 'gws-core'); ?></p>
  <?php foreach (gwseq_cheval_sport_indice_field_labels() as $key => $label) :
    $indice = gwseq_get_cheval_sport_indice($post->ID, $key);
  ?>
    <p>
      <label for="gwseq-cheval-<?php echo esc_attr($key); ?>-valeur"><strong><?php echo esc_html($label); ?></strong></label>
      <input type="number" step="1" class="small-text" id="gwseq-cheval-<?php echo esc_attr($key); ?>-valeur" name="_gwseq_<?php echo esc_attr($key); ?>[valeur]" value="<?php echo esc_attr($indice['valeur']); ?>">
      <label for="gwseq-cheval-<?php echo esc_attr($key); ?>-cd"><?php esc_html_e('CD', 'gws-core'); ?></label>
      <input type="number" step="0.01" class="small-text" id="gwseq-cheval-<?php echo esc_attr($key); ?>-cd" name="_gwseq_<?php echo esc_attr($key); ?>[cd]" value="<?php echo esc_attr(gwseq_format_cheval_indice_cd($indice['cd'])); ?>">
      <label for="gwseq-cheval-<?php echo esc_attr($key); ?>-annee"><?php esc_html_e('Année', 'gws-core'); ?></label>
      <input type="number" step="1" class="small-text" id="gwseq-cheval-<?php echo esc_attr($key); ?>-annee" name="_gwseq_<?php echo esc_attr($key); ?>[annee]" value="<?php echo esc_attr($indice['annee']); ?>">
    </p>
  <?php endforeach; ?>

  <h4><?php esc_html_e('Indices génétiques', 'gws-core'); ?></h4>
  <p class="description"><?php esc_html_e('Valeur et coefficient de détermination (CD) enregistrés séparément — un signe négatif est conservé tel quel. Exemple : BSO +12 (0,90).', 'gws-core'); ?></p>
  <?php foreach (gwseq_cheval_genetic_indice_field_labels() as $key => $label) :
    $indice = gwseq_get_cheval_genetic_indice($post->ID, $key);
  ?>
    <p>
      <label for="gwseq-cheval-<?php echo esc_attr($key); ?>-valeur"><strong><?php echo esc_html($label); ?></strong></label>
      <input type="number" step="any" class="small-text" id="gwseq-cheval-<?php echo esc_attr($key); ?>-valeur" name="_gwseq_<?php echo esc_attr($key); ?>[valeur]" value="<?php echo esc_attr($indice['valeur']); ?>">
      <label for="gwseq-cheval-<?php echo esc_attr($key); ?>-cd"><?php esc_html_e('CD', 'gws-core'); ?></label>
      <input type="number" step="0.01" class="small-text" id="gwseq-cheval-<?php echo esc_attr($key); ?>-cd" name="_gwseq_<?php echo esc_attr($key); ?>[cd]" value="<?php echo esc_attr(gwseq_format_cheval_indice_cd($indice['cd'])); ?>">
    </p>
  <?php endforeach; ?>
  <?php
}

function gwseq_save_cheval_indices_meta($post_id) {
  if (!isset($_POST[GWSEQ_CHEVAL_NONCE_FIELD]) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[GWSEQ_CHEVAL_NONCE_FIELD])), GWSEQ_CHEVAL_NONCE_ACTION)) return;
  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
  if (function_exists('wp_is_post_revision') && wp_is_post_revision($post_id)) return;
  if (!current_user_can('edit_post', $post_id)) return;

  foreach (gwseq_cheval_sport_indice_keys() as $key) {
    gwseq_set_cheval_sport_indice($post_id, $key, $_POST['_gwseq_' . $key] ?? array());
  }
  foreach (gwseq_cheval_genetic_indice_keys() as $key) {
    gwseq_set_cheval_genetic_indice($post_id, $key, $_POST['_gwseq_' . $key] ?? array());
  }
}
add_action('save_post_' . GWSEQ_CPT_CHEVAL, 'gwseq_save_cheval_indices_meta');

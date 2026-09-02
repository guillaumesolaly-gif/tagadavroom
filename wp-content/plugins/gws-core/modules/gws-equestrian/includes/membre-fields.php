<?php
/**
 * Équipe — module métier volontairement simple : ni annuaire RH, ni système de comptes
 * utilisateurs, ni CRM. Un Membre est une fiche métier structurée (dirigeant, cavalier, moniteur,
 * soigneur, groom, responsable d'élevage, vétérinaire intégré, personnel administratif...),
 * destinée à être réutilisée plus tard sur le site et potentiellement d'autres supports GWS —
 * aucune donnée n'est donc stockée sous forme de blob HTML ou de champ opaque : chaque information
 * reste individuellement accessible (Prénom, Nom, Fonction, Photo, Localisation, Présentation,
 * Spécialités, Diplômes, Langues, coordonnées de contact).
 *
 * Tous les champs sont facultatifs — un membre doit pouvoir être enregistré avec très peu
 * d'informations. Sauf pour Langues (seul champ réellement structuré de la section Profil), aucun
 * référentiel ni taxonomie n'est créé : Fonction/rôle, Localisation, Spécialités et Diplômes
 * restent volontairement du texte libre, GWS devant fonctionner avec des structures et des
 * qualifications différentes selon les pays.
 *
 * Même architecture que Cheval/Prestation (includes/cheval-fields.php,
 * includes/prestation-fields.php) : fonctions de sanitation pures (aucun accès à $_POST ni à la
 * base), séparées de la glue WordPress (meta boxes, sauvegarde), testées avec des données à la
 * forme réelle d'une soumission de formulaire (voir tests/gws-equestrian-membre-logic-test.php).
 *
 * Source de vérité unique par donnée métier : Photo = image à la une native (aucune meta
 * parallèle, voir includes/post-types.php pour son relabelling en "Photo"), Ordre = menu_order
 * natif (voir includes/admin-ui.php). Titre technique WordPress (post_title) = automatiquement
 * dérivé de Prénom + Nom, voir gwseq_auto_title_membre() plus bas — jamais saisi séparément par le
 * client (voir includes/membre-editor.php pour le masquage du champ Titre natif).
 *
 * Internationalisation : libellés/options du logiciel traduits via le text domain 'gws-core' ;
 * valeurs techniques stockées (codes de langue 'fr'/'en'/'autre'...) jamais traduites ; contenu
 * saisi par le professionnel (prénom, nom, fonction, présentation, précision "Autre"...) jamais
 * passé dans une fonction de traduction.
 *
 * Permissions : ce post type est enregistré sans 'capability_type' personnalisé (voir
 * includes/post-types.php), donc avec le type de capacité par défaut 'post' — exactement la même
 * logique déjà en place pour Prestation/Groupe/Cheval. Un utilisateur Éditeur (qui dispose déjà
 * nativement de edit_posts/edit_others_posts/publish_posts/delete_posts/upload_files pour le type
 * 'post') peut donc consulter/ajouter/modifier/publier/gérer la photo/mettre à la corbeille un
 * Membre sans qu'aucune capacité technique supplémentaire ne soit créée pour ce seul module.
 */

if (!defined('ABSPATH')) exit;

const GWSEQ_MEMBRE_NONCE_ACTION = 'gwseq_save_membre_meta';
const GWSEQ_MEMBRE_NONCE_FIELD = 'gwseq_save_membre_meta_nonce';

/* -------------------------------------------------------------------------------------------
 * Langues : seul champ réellement structuré de cette section — valeurs techniques stables
 * (indépendantes des libellés affichés, eux-mêmes traduits), sélection multiple. "Autre" révèle un
 * champ libre "Préciser" dont le serveur reste l'autorité (voir
 * gwseq_sanitize_membre_langues_input() : si "Autre" n'est plus sélectionné, la précision est
 * systématiquement remise à vide, quel que soit le contenu réellement soumis pour ce champ).
 * ----------------------------------------------------------------------------------------- */

function gwseq_membre_langue_options() {
  return array(
    'fr' => __('Français', 'gws-core'),
    'en' => __('Anglais', 'gws-core'),
    'de' => __('Allemand', 'gws-core'),
    'es' => __('Espagnol', 'gws-core'),
    'it' => __('Italien', 'gws-core'),
    'pt' => __('Portugais', 'gws-core'),
    'nl' => __('Néerlandais', 'gws-core'),
    'sv' => __('Suédois', 'gws-core'),
    'zh' => __('Chinois', 'gws-core'),
    'ja' => __('Japonais', 'gws-core'),
    'ar' => __('Arabe', 'gws-core'),
    'autre' => __('Autre', 'gws-core'),
  );
}

/* -------------------------------------------------------------------------------------------
 * Enregistrement des meta (jamais exposées en REST à ce stade — même choix que Cheval : une future
 * projection publique explicite pourra s'appuyer sur les fonctions gwseq_get_membre_*() ci-dessous
 * sans qu'aucune décision prise ici n'oblige à exposer les meta brutes).
 * ----------------------------------------------------------------------------------------- */

function gwseq_register_membre_meta() {
  $string_meta = array(
    '_gwseq_membre_prenom', '_gwseq_membre_nom', '_gwseq_membre_fonction', '_gwseq_membre_localisation',
    '_gwseq_membre_presentation', '_gwseq_membre_specialites', '_gwseq_membre_diplomes',
    '_gwseq_membre_langue_autre_precision',
    '_gwseq_membre_telephone', '_gwseq_membre_email', '_gwseq_membre_whatsapp',
    '_gwseq_membre_instagram', '_gwseq_membre_facebook', '_gwseq_membre_linkedin', '_gwseq_membre_tiktok', '_gwseq_membre_site',
  );
  foreach ($string_meta as $key) {
    register_post_meta(GWSEQ_CPT_MEMBRE, $key, array('single' => true, 'type' => 'string', 'show_in_rest' => false));
  }
  // Sélection multiple (§Langues) : même mécanisme de stockage qu'une galerie Cheval
  // (includes/cheval-media.php, _gwseq_galerie/_gwseq_videos) — meta unique de type 'array'.
  register_post_meta(GWSEQ_CPT_MEMBRE, '_gwseq_membre_langues', array('single' => true, 'type' => 'array', 'show_in_rest' => false));
}
add_action('init', 'gwseq_register_membre_meta');

/* -------------------------------------------------------------------------------------------
 * Fonctions pures : sanitation, lecture. Sans dépendance à $_POST ni à la base pour les fonctions
 * de sanitation — testées directement.
 * ----------------------------------------------------------------------------------------- */

function gwseq_sanitize_membre_identity_input($raw) {
  $raw = is_array($raw) ? $raw : array();
  return array(
    'prenom' => gws_core_field_sanitize('text', $raw['_gwseq_membre_prenom'] ?? ''),
    'nom' => gws_core_field_sanitize('text', $raw['_gwseq_membre_nom'] ?? ''),
    'fonction' => gws_core_field_sanitize('text', $raw['_gwseq_membre_fonction'] ?? ''),
    'localisation' => gws_core_field_sanitize('text', $raw['_gwseq_membre_localisation'] ?? ''),
  );
}

/**
 * Règle centrale des Langues (voir docblock de ce fichier) — fonction pure, aucun accès à $_POST
 * ni à la base. Revalide chaque code soumis contre le référentiel (jamais une valeur propagée
 * telle quelle), déduplique, et ne conserve la précision "Autre" QUE si "autre" fait partie des
 * langues retenues après revalidation — jamais une simple dépendance à l'affichage conditionnel
 * du formulaire, qui n'est qu'un confort de saisie.
 */
function gwseq_sanitize_membre_langues_input($raw) {
  $raw = is_array($raw) ? $raw : array();
  $options = gwseq_membre_langue_options();
  $submitted = isset($raw['_gwseq_membre_langues']) && is_array($raw['_gwseq_membre_langues']) ? $raw['_gwseq_membre_langues'] : array();

  $langues = array();
  foreach ($submitted as $value) {
    $code = sanitize_key(wp_unslash($value));
    if ($code !== '' && array_key_exists($code, $options) && !in_array($code, $langues, true)) {
      $langues[] = $code;
    }
  }

  $autre_precision = in_array('autre', $langues, true)
    ? gws_core_field_sanitize('text', $raw['_gwseq_membre_langue_autre_precision'] ?? '')
    : '';

  return array('langues' => $langues, 'langue_autre_precision' => $autre_precision);
}

function gwseq_sanitize_membre_profil_input($raw) {
  $raw = is_array($raw) ? $raw : array();
  $langues = gwseq_sanitize_membre_langues_input($raw);
  return array(
    'presentation' => gws_core_field_sanitize('textarea', $raw['_gwseq_membre_presentation'] ?? ''),
    'specialites' => gws_core_field_sanitize('text', $raw['_gwseq_membre_specialites'] ?? ''),
    'diplomes' => gws_core_field_sanitize('text', $raw['_gwseq_membre_diplomes'] ?? ''),
    'langues' => $langues['langues'],
    'langue_autre_precision' => $langues['langue_autre_precision'],
  );
}

function gwseq_sanitize_membre_contact_input($raw) {
  $raw = is_array($raw) ? $raw : array();
  return array(
    // Téléphone/WhatsApp : texte libre, aucun format imposé (§ ne pas imposer un format français) —
    // sanitize_text_field() conserve '+'/chiffres/espaces/tirets, donc un numéro international
    // n'est jamais dénaturé. WhatsApp est une donnée INDÉPENDANTE du téléphone principal (jamais
    // supposée identique) : simple texte libre à ce stade, adapté à une future construction de lien
    // wa.me — cette construction elle-même n'est volontairement pas développée dans ce lot (aucun
    // rendu front, aucune connexion API).
    'telephone' => gws_core_field_sanitize('text', $raw['_gwseq_membre_telephone'] ?? ''),
    'email' => gws_core_field_sanitize('email', $raw['_gwseq_membre_email'] ?? ''),
    'whatsapp' => gws_core_field_sanitize('text', $raw['_gwseq_membre_whatsapp'] ?? ''),
    'instagram' => gws_core_field_sanitize('url', $raw['_gwseq_membre_instagram'] ?? ''),
    'facebook' => gws_core_field_sanitize('url', $raw['_gwseq_membre_facebook'] ?? ''),
    'linkedin' => gws_core_field_sanitize('url', $raw['_gwseq_membre_linkedin'] ?? ''),
    'tiktok' => gws_core_field_sanitize('url', $raw['_gwseq_membre_tiktok'] ?? ''),
    'site' => gws_core_field_sanitize('url', $raw['_gwseq_membre_site'] ?? ''),
  );
}

/**
 * Fonctions métier pures d'écriture (même architecture que gwseq_set_cheval_identity()) : prennent
 * le même tableau à la forme $_POST que leur fonction de sanitation associée — réutilisables telles
 * quelles par un futur appelant programmatique (import, API...).
 */
function gwseq_set_membre_identity($post_id, $raw) {
  $post_id = (int) $post_id;
  if (!$post_id) return false;
  $identity = gwseq_sanitize_membre_identity_input($raw);
  update_post_meta($post_id, '_gwseq_membre_prenom', $identity['prenom']);
  update_post_meta($post_id, '_gwseq_membre_nom', $identity['nom']);
  update_post_meta($post_id, '_gwseq_membre_fonction', $identity['fonction']);
  update_post_meta($post_id, '_gwseq_membre_localisation', $identity['localisation']);
  return true;
}

function gwseq_set_membre_profil($post_id, $raw) {
  $post_id = (int) $post_id;
  if (!$post_id) return false;
  $profil = gwseq_sanitize_membre_profil_input($raw);
  update_post_meta($post_id, '_gwseq_membre_presentation', $profil['presentation']);
  update_post_meta($post_id, '_gwseq_membre_specialites', $profil['specialites']);
  update_post_meta($post_id, '_gwseq_membre_diplomes', $profil['diplomes']);
  update_post_meta($post_id, '_gwseq_membre_langues', $profil['langues']);
  update_post_meta($post_id, '_gwseq_membre_langue_autre_precision', $profil['langue_autre_precision']);
  return true;
}

function gwseq_set_membre_contact($post_id, $raw) {
  $post_id = (int) $post_id;
  if (!$post_id) return false;
  $contact = gwseq_sanitize_membre_contact_input($raw);
  update_post_meta($post_id, '_gwseq_membre_telephone', $contact['telephone']);
  update_post_meta($post_id, '_gwseq_membre_email', $contact['email']);
  update_post_meta($post_id, '_gwseq_membre_whatsapp', $contact['whatsapp']);
  update_post_meta($post_id, '_gwseq_membre_instagram', $contact['instagram']);
  update_post_meta($post_id, '_gwseq_membre_facebook', $contact['facebook']);
  update_post_meta($post_id, '_gwseq_membre_linkedin', $contact['linkedin']);
  update_post_meta($post_id, '_gwseq_membre_tiktok', $contact['tiktok']);
  update_post_meta($post_id, '_gwseq_membre_site', $contact['site']);
  return true;
}

/**
 * Lecture : un tableau explicite et fermé par section (jamais get_post_meta($id) en bloc), même
 * convention que gwseq_get_cheval_identity()/gwseq_get_cheval_commercial().
 */
function gwseq_get_membre_identity($post_id) {
  return array(
    'prenom' => get_post_meta($post_id, '_gwseq_membre_prenom', true),
    'nom' => get_post_meta($post_id, '_gwseq_membre_nom', true),
    'fonction' => get_post_meta($post_id, '_gwseq_membre_fonction', true),
    'localisation' => get_post_meta($post_id, '_gwseq_membre_localisation', true),
  );
}

function gwseq_get_membre_profil($post_id) {
  return array(
    'presentation' => get_post_meta($post_id, '_gwseq_membre_presentation', true),
    'specialites' => get_post_meta($post_id, '_gwseq_membre_specialites', true),
    'diplomes' => get_post_meta($post_id, '_gwseq_membre_diplomes', true),
  );
}

function gwseq_get_membre_langues($post_id) {
  $langues = get_post_meta($post_id, '_gwseq_membre_langues', true);
  return array(
    'langues' => is_array($langues) ? $langues : array(),
    'langue_autre_precision' => get_post_meta($post_id, '_gwseq_membre_langue_autre_precision', true),
  );
}

function gwseq_get_membre_contact($post_id) {
  return array(
    'telephone' => get_post_meta($post_id, '_gwseq_membre_telephone', true),
    'email' => get_post_meta($post_id, '_gwseq_membre_email', true),
    'whatsapp' => get_post_meta($post_id, '_gwseq_membre_whatsapp', true),
    'instagram' => get_post_meta($post_id, '_gwseq_membre_instagram', true),
    'facebook' => get_post_meta($post_id, '_gwseq_membre_facebook', true),
    'linkedin' => get_post_meta($post_id, '_gwseq_membre_linkedin', true),
    'tiktok' => get_post_meta($post_id, '_gwseq_membre_tiktok', true),
    'site' => get_post_meta($post_id, '_gwseq_membre_site', true),
  );
}

/**
 * Représentation compacte des langues pour la colonne de liste admin (§9 : "Français, Anglais").
 * "Autre" affiche la précision saisie quand elle existe (plus lisible qu'un simple "Autre" répété
 * pour chaque membre) — retombe sur le libellé générique si la précision est vide.
 */
function gwseq_membre_langues_label($langues_data) {
  $options = gwseq_membre_langue_options();
  $labels = array();
  foreach ($langues_data['langues'] as $code) {
    if ($code === 'autre') {
      $precision = $langues_data['langue_autre_precision'];
      $labels[] = $precision !== '' ? $precision : $options['autre'];
      continue;
    }
    if (isset($options[$code])) $labels[] = $options[$code];
  }
  return implode(', ', $labels);
}

/* -------------------------------------------------------------------------------------------
 * Titre automatique (§8 de la demande) : le client ne saisit jamais le nom deux fois.
 *
 * MÉCANISME RETENU : un filtre `wp_insert_post_data` (jamais un second wp_update_post() dans un
 * hook save_post, qui obligerait à se dés-accrocher soi-même pour éviter une boucle de sauvegarde)
 * — ce filtre s'exécute une seule fois, AVANT l'écriture en base, et se contente de modifier le
 * tableau `$data` sur le point d'être inséré : aucun appel récursif à wp_insert_post()/
 * wp_update_post() n'est jamais déclenché, donc aucune boucle possible par construction.
 *
 * Le prénom/nom utilisés sont ceux de CETTE MÊME soumission ($_POST, jamais une meta relue depuis
 * la base), sanitisés par gws_core_field_sanitize('text', ...) — la même fonction que
 * gwseq_sanitize_membre_identity_input() utilise pour ces deux champs, aucune deuxième règle de
 * nettoyage inventée. Protégé par le même nonce que la sauvegarde des meta (GWSEQ_MEMBRE_NONCE_*) :
 * seule une soumission réelle du formulaire d'édition Membre dérive le titre — une révision, un
 * autosave, ou tout autre appel de wp_insert_post() sur ce post type qui ne porterait pas ce nonce
 * (ex. Quick Edit, un futur import programmatique) laisse le titre déjà enregistré intact, jamais
 * réécrit silencieusement.
 *
 * Fonctionne correctement dans tous les cas demandés :
 * - brouillon : le filtre s'applique à CHAQUE enregistrement (brouillon compris), le titre reflète
 *   donc déjà Prénom + Nom dès le premier brouillon enregistré avec ces champs renseignés ;
 * - prénom seul / nom seul : gwseq_derive_membre_title() ignore la partie vide plutôt que de
 *   produire un espace superflu ("Jean" et non "Jean ") ;
 * - les deux vides : titre vide (chaîne vide), WordPress affiche alors nativement "(sans titre)"
 *   dans la liste — cohérent avec "un membre doit pouvoir être enregistré avec très peu
 *   d'informations", jamais un titre inventé.
 * ----------------------------------------------------------------------------------------- */

function gwseq_derive_membre_title($prenom, $nom) {
  $parts = array_filter(array(trim((string) $prenom), trim((string) $nom)), function ($part) {
    return $part !== '';
  });
  return implode(' ', $parts);
}

function gwseq_auto_title_membre($data, $postarr) {
  if (($data['post_type'] ?? '') !== GWSEQ_CPT_MEMBRE) return $data;
  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return $data;
  if (!isset($_POST[GWSEQ_MEMBRE_NONCE_FIELD]) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[GWSEQ_MEMBRE_NONCE_FIELD])), GWSEQ_MEMBRE_NONCE_ACTION)) {
    return $data;
  }

  $prenom = gws_core_field_sanitize('text', $_POST['_gwseq_membre_prenom'] ?? '');
  $nom = gws_core_field_sanitize('text', $_POST['_gwseq_membre_nom'] ?? '');
  $data['post_title'] = gwseq_derive_membre_title($prenom, $nom);
  return $data;
}
add_filter('wp_insert_post_data', 'gwseq_auto_title_membre', 10, 2);

/* -------------------------------------------------------------------------------------------
 * Meta boxes et sauvegarde (glue WordPress) — trois sections simples (§7 : "si le système
 * d'onglets de Cheval peut être réutilisé proprement, le réutiliser ; sinon, préférer trois
 * sections simples plutôt que créer une abstraction disproportionnée"). Le système d'onglets de
 * Cheval (includes/cheval-admin-tabs.php) est structurellement couplé à GWSEQ_CPT_CHEVAL (écran
 * ciblé en dur, script `gwseqChevalTabs` dédié, déplacement DOM spécifique à la boîte Médias
 * Cheval) : le généraliser pour un module aussi réduit (trois sections, une dizaine de champs)
 * aurait créé exactement le couplage étrange que la demande met en garde contre — trois meta boxes
 * empilées (Identité, Profil, Contact) restent, elles, immédiatement lisibles sans aucune
 * abstraction supplémentaire.
 * ----------------------------------------------------------------------------------------- */

function gwseq_add_membre_meta_boxes() {
  add_meta_box('gwseq-membre-identite', __('Identité', 'gws-core'), 'gwseq_render_membre_identite_box', GWSEQ_CPT_MEMBRE, 'normal', 'high');
  add_meta_box('gwseq-membre-profil', __('Profil', 'gws-core'), 'gwseq_render_membre_profil_box', GWSEQ_CPT_MEMBRE, 'normal', 'default');
  add_meta_box('gwseq-membre-contact', __('Contact', 'gws-core'), 'gwseq_render_membre_contact_box', GWSEQ_CPT_MEMBRE, 'normal', 'default');
}
add_action('add_meta_boxes_' . GWSEQ_CPT_MEMBRE, 'gwseq_add_membre_meta_boxes');

function gwseq_render_membre_identite_box($post) {
  wp_nonce_field(GWSEQ_MEMBRE_NONCE_ACTION, GWSEQ_MEMBRE_NONCE_FIELD);
  $identity = gwseq_get_membre_identity($post->ID);
  ?>
  <p>
    <label for="gwseq-membre-prenom"><strong><?php esc_html_e('Prénom', 'gws-core'); ?></strong></label><br>
    <input type="text" class="regular-text" id="gwseq-membre-prenom" name="_gwseq_membre_prenom" value="<?php echo esc_attr($identity['prenom']); ?>">
  </p>
  <p>
    <label for="gwseq-membre-nom"><strong><?php esc_html_e('Nom', 'gws-core'); ?></strong></label><br>
    <input type="text" class="regular-text" id="gwseq-membre-nom" name="_gwseq_membre_nom" value="<?php echo esc_attr($identity['nom']); ?>">
  </p>
  <p class="description"><?php esc_html_e('Le titre de la fiche est calculé automatiquement à partir du prénom et du nom — inutile de le saisir séparément.', 'gws-core'); ?></p>
  <p>
    <label for="gwseq-membre-fonction"><strong><?php esc_html_e('Fonction / rôle', 'gws-core'); ?></strong></label><br>
    <input type="text" class="regular-text" id="gwseq-membre-fonction" name="_gwseq_membre_fonction" value="<?php echo esc_attr($identity['fonction']); ?>" placeholder="<?php esc_attr_e('Ex. Gérant, Moniteur, Cavalier, Groom...', 'gws-core'); ?>">
  </p>
  <p>
    <label for="gwseq-membre-localisation"><strong><?php esc_html_e('Localisation', 'gws-core'); ?></strong></label><br>
    <input type="text" class="regular-text" id="gwseq-membre-localisation" name="_gwseq_membre_localisation" value="<?php echo esc_attr($identity['localisation']); ?>" placeholder="<?php esc_attr_e('Ex. Site de Lyon, Haras principal...', 'gws-core'); ?>">
    <br><span class="description"><?php esc_html_e('Utile pour les structures possédant plusieurs sites.', 'gws-core'); ?></span>
  </p>
  <p class="description"><?php esc_html_e('La photo se gère depuis le bloc « Photo » de la colonne latérale.', 'gws-core'); ?></p>
  <?php
}

function gwseq_render_membre_profil_box($post) {
  $profil = gwseq_get_membre_profil($post->ID);
  $langues_data = gwseq_get_membre_langues($post->ID);
  $selected_langues = $langues_data['langues'];
  ?>
  <p>
    <label for="gwseq-membre-presentation"><strong><?php esc_html_e('Présentation / parcours', 'gws-core'); ?></strong></label><br>
    <textarea class="widefat" rows="5" id="gwseq-membre-presentation" name="_gwseq_membre_presentation"><?php echo esc_textarea($profil['presentation']); ?></textarea>
  </p>
  <p>
    <label for="gwseq-membre-specialites"><strong><?php esc_html_e('Spécialités', 'gws-core'); ?></strong></label><br>
    <input type="text" class="widefat" id="gwseq-membre-specialites" name="_gwseq_membre_specialites" value="<?php echo esc_attr($profil['specialites']); ?>" placeholder="<?php esc_attr_e('Ex. Jeunes chevaux, Rééducation, Débourrage, Dressage, CSO...', 'gws-core'); ?>">
  </p>
  <p>
    <label for="gwseq-membre-diplomes"><strong><?php esc_html_e('Diplômes / qualifications', 'gws-core'); ?></strong></label><br>
    <input type="text" class="widefat" id="gwseq-membre-diplomes" name="_gwseq_membre_diplomes" value="<?php echo esc_attr($profil['diplomes']); ?>" placeholder="<?php esc_attr_e('Ex. BPJEPS, DEJEPS, Animateur...', 'gws-core'); ?>">
  </p>
  <p>
    <strong><?php esc_html_e('Langues', 'gws-core'); ?></strong><br>
    <?php foreach (gwseq_membre_langue_options() as $code => $label) : ?>
      <label style="display:inline-block;margin:2px 14px 2px 0;">
        <input type="checkbox" name="_gwseq_membre_langues[]" value="<?php echo esc_attr($code); ?>" <?php checked(in_array($code, $selected_langues, true)); ?>>
        <?php echo esc_html($label); ?>
      </label>
    <?php endforeach; ?>
  </p>
  <p data-gwseq-membre-fields="langue-autre-precision" style="<?php echo in_array('autre', $selected_langues, true) ? '' : 'display:none;'; ?>">
    <label for="gwseq-membre-langue-autre-precision"><strong><?php esc_html_e('Préciser', 'gws-core'); ?></strong></label><br>
    <input type="text" class="regular-text" id="gwseq-membre-langue-autre-precision" name="_gwseq_membre_langue_autre_precision" value="<?php echo esc_attr($langues_data['langue_autre_precision']); ?>">
  </p>
  <?php
}

function gwseq_render_membre_contact_box($post) {
  $contact = gwseq_get_membre_contact($post->ID);
  ?>
  <p>
    <label for="gwseq-membre-telephone"><strong><?php esc_html_e('Téléphone', 'gws-core'); ?></strong></label><br>
    <input type="text" class="regular-text" id="gwseq-membre-telephone" name="_gwseq_membre_telephone" value="<?php echo esc_attr($contact['telephone']); ?>">
  </p>
  <p>
    <label for="gwseq-membre-email"><strong><?php esc_html_e('E-mail', 'gws-core'); ?></strong></label><br>
    <input type="email" class="regular-text" id="gwseq-membre-email" name="_gwseq_membre_email" value="<?php echo esc_attr($contact['email']); ?>">
  </p>
  <p>
    <label for="gwseq-membre-whatsapp"><strong><?php esc_html_e('WhatsApp', 'gws-core'); ?></strong></label><br>
    <input type="text" class="regular-text" id="gwseq-membre-whatsapp" name="_gwseq_membre_whatsapp" value="<?php echo esc_attr($contact['whatsapp']); ?>">
    <br><span class="description"><?php esc_html_e('Peut être différent du téléphone principal.', 'gws-core'); ?> <?php esc_html_e('Format international recommandé, ex. +33 6 12 34 56 78', 'gws-core'); ?></span>
  </p>
  <p>
    <label for="gwseq-membre-instagram"><strong><?php esc_html_e('Instagram', 'gws-core'); ?></strong></label><br>
    <input type="url" class="widefat" id="gwseq-membre-instagram" name="_gwseq_membre_instagram" value="<?php echo esc_attr($contact['instagram']); ?>" placeholder="https://www.instagram.com/votrecompte/">
    <br><span class="description"><?php esc_html_e('Saisissez l\'URL complète, avec https://', 'gws-core'); ?></span>
  </p>
  <p>
    <label for="gwseq-membre-facebook"><strong><?php esc_html_e('Facebook', 'gws-core'); ?></strong></label><br>
    <input type="url" class="widefat" id="gwseq-membre-facebook" name="_gwseq_membre_facebook" value="<?php echo esc_attr($contact['facebook']); ?>" placeholder="https://www.facebook.com/votrepage/">
    <br><span class="description"><?php esc_html_e('Saisissez l\'URL complète, avec https://', 'gws-core'); ?></span>
  </p>
  <p>
    <label for="gwseq-membre-linkedin"><strong><?php esc_html_e('LinkedIn', 'gws-core'); ?></strong></label><br>
    <input type="url" class="widefat" id="gwseq-membre-linkedin" name="_gwseq_membre_linkedin" value="<?php echo esc_attr($contact['linkedin']); ?>" placeholder="https://www.linkedin.com/in/votreprofil/">
    <br><span class="description"><?php esc_html_e('Saisissez l\'URL complète, avec https://', 'gws-core'); ?></span>
  </p>
  <p>
    <label for="gwseq-membre-tiktok"><strong><?php esc_html_e('TikTok', 'gws-core'); ?></strong></label><br>
    <input type="url" class="widefat" id="gwseq-membre-tiktok" name="_gwseq_membre_tiktok" value="<?php echo esc_attr($contact['tiktok']); ?>" placeholder="https://www.tiktok.com/@votrecompte">
    <br><span class="description"><?php esc_html_e('Saisissez l\'URL complète, avec https://', 'gws-core'); ?></span>
  </p>
  <p>
    <label for="gwseq-membre-site"><strong><?php esc_html_e('Site / lien externe', 'gws-core'); ?></strong></label><br>
    <input type="url" class="widefat" id="gwseq-membre-site" name="_gwseq_membre_site" value="<?php echo esc_attr($contact['site']); ?>" placeholder="https://www.votresite.fr">
    <br><span class="description"><?php esc_html_e('Saisissez l\'URL complète, avec https://', 'gws-core'); ?></span>
  </p>
  <?php
}

function gwseq_save_membre_meta($post_id) {
  if (!isset($_POST[GWSEQ_MEMBRE_NONCE_FIELD]) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[GWSEQ_MEMBRE_NONCE_FIELD])), GWSEQ_MEMBRE_NONCE_ACTION)) return;
  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
  if (function_exists('wp_is_post_revision') && wp_is_post_revision($post_id)) return;
  if (!current_user_can('edit_post', $post_id)) return;

  gwseq_set_membre_identity($post_id, $_POST);
  gwseq_set_membre_profil($post_id, $_POST);
  gwseq_set_membre_contact($post_id, $_POST);
}
add_action('save_post_' . GWSEQ_CPT_MEMBRE, 'gwseq_save_membre_meta');

/* -------------------------------------------------------------------------------------------
 * Liste d'administration « Tous les membres » (§9) : Photo | Nom | Fonction / rôle | Localisation
 * | Langues | Ordre — colonne native "Date" retirée (même choix que Cheval, peu de valeur dans ce
 * contexte métier). La recherche WordPress native (par titre, donc par nom puisque le titre EST le
 * nom — voir gwseq_auto_title_membre() ci-dessus) reste pleinement fonctionnelle sans aucun code
 * supplémentaire ici.
 * ----------------------------------------------------------------------------------------- */

function gwseq_membre_admin_columns($columns) {
  $new = array();
  foreach ($columns as $key => $label) {
    if ($key === 'date') continue;
    if ($key === 'title') {
      $new['gwseq_membre_photo'] = __('Photo', 'gws-core');
      $new[$key] = __('Nom', 'gws-core');
      continue;
    }
    $new[$key] = $label;
  }
  $new['gwseq_membre_fonction'] = __('Fonction / rôle', 'gws-core');
  $new['gwseq_membre_localisation'] = __('Localisation', 'gws-core');
  $new['gwseq_membre_langues'] = __('Langues', 'gws-core');
  $new['gwseq_membre_ordre'] = __('Ordre', 'gws-core');
  return $new;
}
add_filter('manage_' . GWSEQ_CPT_MEMBRE . '_posts_columns', 'gwseq_membre_admin_columns');

function gwseq_membre_admin_column_content($column, $post_id) {
  if ($column === 'gwseq_membre_photo') {
    // Miniature WordPress native, JAMAIS l'image originale (§9) — un tableau de dimensions demande
    // à WordPress une taille intermédiaire adaptée, jamais 'full'.
    $thumbnail = get_the_post_thumbnail($post_id, array(40, 40));
    echo $thumbnail !== '' ? $thumbnail : '—';
  } elseif ($column === 'gwseq_membre_fonction') {
    $fonction = gwseq_get_membre_identity($post_id)['fonction'];
    echo $fonction !== '' ? esc_html($fonction) : '—';
  } elseif ($column === 'gwseq_membre_localisation') {
    $localisation = gwseq_get_membre_identity($post_id)['localisation'];
    echo $localisation !== '' ? esc_html($localisation) : '—';
  } elseif ($column === 'gwseq_membre_langues') {
    $label = gwseq_membre_langues_label(gwseq_get_membre_langues($post_id));
    echo $label !== '' ? esc_html($label) : '—';
  } elseif ($column === 'gwseq_membre_ordre') {
    echo (int) get_post_field('menu_order', $post_id);
  }
}
add_action('manage_' . GWSEQ_CPT_MEMBRE . '_posts_custom_column', 'gwseq_membre_admin_column_content', 10, 2);

/* -------------------------------------------------------------------------------------------
 * Assets : affichage conditionnel du champ "Préciser" de Langues, uniquement sur l'écran d'édition
 * d'une fiche membre.
 * ----------------------------------------------------------------------------------------- */

function gwseq_enqueue_membre_admin_assets($hook) {
  if (!in_array($hook, array('post.php', 'post-new.php'), true)) return;
  $screen = function_exists('get_current_screen') ? get_current_screen() : null;
  if (!$screen || $screen->post_type !== GWSEQ_CPT_MEMBRE) return;

  wp_enqueue_script('gwseq-membre-admin', GWSEQ_MODULE_URL . 'assets/membre-admin.js', array(), GWSEQ_MODULE_VERSION, true);
}
add_action('admin_enqueue_scripts', 'gwseq_enqueue_membre_admin_assets');

<?php
/**
 * Composant répétable interne à GWS Equestrian — Étape 2.
 *
 * Rôle : gérer une liste ordonnée de lignes structurées (ex. indices de performance, URLs de
 * vidéos, blocs éditoriaux personnalisés) sans réécrire trois fois la même mécanique
 * d'ajout/suppression/sauvegarde. Ce n'est délibérément PAS un générateur de champs universel
 * (pas de mini-ACF, pas de champs imbriqués, pas de types hypothétiques) : seuls les types déjà
 * identifiés comme nécessaires par la conception validée de GWS Equestrian sont supportés.
 * Ajouter un type supplémentaire plus tard (ex. 'select') reste un ajout local et isolé — un cas
 * de plus dans gwseq_repeater_sanitize_value() et dans gwseq_repeater_row_markup() — jamais une
 * réécriture de l'architecture.
 *
 * Reste dans wp-content/plugins/gws-core/modules/gws-equestrian/ tant qu'aucun deuxième module
 * métier n'a démontré le même besoin : ce n'est pas encore une abstraction générique du cœur
 * (voir AI-AGENT.md et modules/README.md).
 *
 * ---------------------------------------------------------------------------------------------
 * DÉCLARER une structure répétable sur un post type :
 *
 *   gwseq_register_repeater_field(
 *     'mon_post_type',
 *     '_mon_prefixe_mes_lignes',      // clé de meta, préfixée par le module appelant
 *     array(
 *       'libelle' => array('label' => 'Libellé', 'type' => 'text'),
 *       'valeur'  => array('label' => 'Valeur',  'type' => 'number'),
 *       'annee'   => array('label' => 'Année',   'type' => 'integer'),
 *     ),
 *     'Titre de la meta box',
 *     'mon_prefixe_save_mes_lignes'   // action de nonce, unique par repeater
 *   );
 *
 * Ceci enregistre en une fois : la meta (register_post_meta), la meta box (rendu), et la
 * sauvegarde (hook save_post_{post_type}). Voir includes/qa-repeater.php pour un exemple complet
 * d'utilisation.
 *
 * RÉCUPÉRER les données (front, futur export PDF, tout autre rendu) : aucune fonction dédiée —
 * c'est une meta WordPress ordinaire, donc directement :
 *
 *   $lignes = get_post_meta($post_id, '_mon_prefixe_mes_lignes', true); // tableau de lignes
 *
 * C'est un choix délibéré : la logique métier qui consomme ces données (rendu web aujourd'hui,
 * rendu PDF demain) n'a besoin d'aucune connaissance de ce fichier pour les lire.
 *
 * NOMMAGE DES CHAMPS HTML — chaque ligne porte un index explicite, partagé par toutes ses
 * colonnes : name="{meta_key}[{index}][{colonne}]" (ex. "_x_lignes[0][libelle]",
 * "_x_lignes[0][valeur]", "_x_lignes[0][annee]" pour une même ligne 0). C'est indispensable :
 * des noms sans index partagé (ex. "{meta_key}[][colonne]" pour chaque colonne) ne produisent PAS
 * une ligne par groupe de champs — PHP alloue un nouvel index à chaque NOM DE CHAMP distinct
 * rencontré, pas à chaque ligne visuelle, ce qui éclate une ligne de 3 colonnes en 3 lignes d'une
 * seule colonne chacune à la réception. Les lignes déjà enregistrées reçoivent leur position réelle
 * (0, 1, 2...) ; le gabarit `<template>` utilisé par le JS pour une nouvelle ligne porte le jeton
 * littéral "__INDEX__" à la place d'un index, que le JS remplace par un entier garanti unique
 * (compteur porté par l'attribut data-gwseq-next-index du conteneur, initialisé au nombre de
 * lignes existantes, incrémenté à chaque ajout, jamais réutilisé) avant d'insérer la ligne dans le
 * DOM. Les index n'ont pas besoin d'être contigus après une suppression : gwseq_repeater_sanitize_rows()
 * réindexe proprement le résultat final, quels que soient les index bruts reçus.
 *
 * TYPES DE CHAMPS SUPPORTÉS (primitives déjà nécessaires, pas une bibliothèque complète) :
 *   - 'text'     : texte court   (délègue à gws_core_field_sanitize(), cœur gws-core)
 *   - 'textarea' : texte long    (idem)
 *   - 'number'   : nombre décimal (idem — vide si non numérique, jamais une erreur)
 *   - 'url'      : URL           (idem — mêmes règles que le reste de GWS, esc_url_raw())
 *   - 'integer'  : nombre entier (ex. une année) — seul type propre à ce fichier, absent du
 *                  générateur minimal de gws-core ; vide si non numérique, jamais d'erreur.
 *
 * SANITIZATION : chaque colonne est sanitizée selon son propre type déclaré dans le schéma —
 * jamais une sanitization générique unique appliquée à toutes les valeurs. Toute clé présente
 * dans la donnée envoyée mais absente du schéma est ignorée (jamais stockée). Aucune confiance
 * n'est accordée à la structure de $_POST : une valeur qui n'est pas un tableau de lignes, ou une
 * ligne qui n'est pas elle-même un tableau, ou une valeur de colonne qui serait un tableau
 * imbriqué, sont traitées comme absentes plutôt que de provoquer une erreur.
 *
 * LIGNES VIDES ET VALEUR 0 : une ligne est considérée vide, et donc jamais stockée, uniquement
 * si TOUTES ses valeurs sanitizées valent la chaîne vide ''. Une valeur numérique 0 (ou la chaîne
 * "0") n'est jamais confondue avec une valeur vide : elle sanitize vers 0 (nombre) ou "0"
 * (entier), jamais vers '', donc une ligne contenant un 0 légitime est bien conservée.
 *
 * ORDRE ET IDENTITÉ DES LIGNES : l'ordre de saisie est conservé (aucun tri appliqué). Il n'existe
 * volontairement aucun identifiant unique par ligne (pas d'UUID) : rien dans GWS Equestrian ne
 * référence aujourd'hui une ligne individuelle depuis un autre objet — seule la liste ordonnée
 * compte. Si un besoin réel de référencer une ligne précise apparaît plus tard, ajouter un
 * identifiant reste un ajout d'une colonne supplémentaire, pas un changement d'architecture.
 *
 * LIMITATIONS VOLONTAIRES :
 *   - Pas de glisser-déposer (l'ordre suit uniquement l'ordre d'ajout/l'ordre à l'écran) — l'ajout
 *     et la suppression suffisent au besoin identifié pour cette étape.
 *   - Pas d'exposition REST (show_in_rest => false) : non nécessaire pour l'instant.
 *   - Pas de lignes imbriquées (une ligne ne peut pas elle-même contenir un autre repeater).
 *   - Un seul niveau de validation par type ; pas de règles de validation croisée entre colonnes.
 */

if (!defined('ABSPATH')) exit;

/**
 * Sanitize une valeur de colonne selon son type déclaré. Délègue aux types déjà pris en charge
 * par gws-core (text/textarea/number/url) plutôt que de dupliquer cette logique ; n'ajoute que ce
 * que gws-core ne fournit pas encore ('integer').
 */
function gwseq_repeater_sanitize_value($type, $raw_value) {
  if (is_array($raw_value)) return '';

  if ($type === 'integer') {
    $raw_value = wp_unslash($raw_value);
    if ($raw_value === '' || $raw_value === null) return '';
    return is_numeric($raw_value) ? (string) intval($raw_value) : '';
  }

  if (function_exists('gws_core_field_sanitize') && in_array($type, array('text', 'textarea', 'url', 'number'), true)) {
    return gws_core_field_sanitize($type, $raw_value);
  }

  return sanitize_text_field(wp_unslash((string) $raw_value));
}

/**
 * Une ligne est vide si toutes ses valeurs sanitizées sont la chaîne vide — jamais si l'une
 * d'elles vaut 0 ou "0" (voir note en tête de fichier).
 */
function gwseq_repeater_row_is_empty($clean_row) {
  foreach ($clean_row as $value) {
    if ($value !== '') return false;
  }
  return true;
}

/**
 * Fonction pure (aucun appel direct à $_POST/WordPress au-delà des helpers de sanitization) :
 * normalise un tableau brut de lignes selon un schéma, en ne conservant que les clés déclarées,
 * en sanitizant chaque valeur selon son type, en écartant les lignes entièrement vides, et en
 * préservant l'ordre de saisie. Tolère une entrée malformée (pas un tableau, ligne qui n'est pas
 * un tableau) sans erreur.
 */
function gwseq_repeater_sanitize_rows($schema, $raw_rows) {
  if (!is_array($raw_rows)) return array();

  $clean_rows = array();
  foreach ($raw_rows as $raw_row) {
    if (!is_array($raw_row)) continue;

    $clean_row = array();
    foreach ($schema as $column_key => $column) {
      $type = $column['type'] ?? 'text';
      $raw_value = array_key_exists($column_key, $raw_row) ? $raw_row[$column_key] : '';
      $clean_row[$column_key] = gwseq_repeater_sanitize_value($type, $raw_value);
    }

    if (gwseq_repeater_row_is_empty($clean_row)) continue;
    $clean_rows[] = $clean_row;
  }

  return array_values($clean_rows);
}

/**
 * Enregistre en une fois la meta, la meta box et la sauvegarde d'une structure répétable pour un
 * post type donné. Voir l'en-tête de ce fichier pour un exemple d'appel complet.
 */
function gwseq_register_repeater_field($post_type, $meta_key, $schema, $box_title, $nonce_action) {
  register_post_meta($post_type, $meta_key, array(
    'single' => true,
    'type' => 'array',
    'show_in_rest' => false,
  ));

  add_action('add_meta_boxes_' . $post_type, function ($post) use ($post_type, $box_title, $meta_key, $schema, $nonce_action) {
    add_meta_box('gwseq-repeater-' . sanitize_key($meta_key), $box_title, function ($post) use ($meta_key, $schema, $nonce_action) {
      gwseq_render_repeater_field($post, $meta_key, $schema, $nonce_action);
    }, $post_type, 'normal', 'default');
  });

  add_action('save_post_' . $post_type, function ($post_id) use ($meta_key, $schema, $nonce_action) {
    gwseq_save_repeater_field($post_id, $schema, $meta_key, $nonce_action);
  });
}

/**
 * Rendu de la meta box : lignes déjà enregistrées + un gabarit HTML natif <template> (une ligne
 * vide) que le JS clone pour ajouter une ligne, sans dupliquer la logique de rendu des champs
 * entre PHP et JS.
 *
 * $max_rows (optionnel, ajouté pour GWS Equestrian Étape 6 — ex. 10 vidéos maximum par cheval) :
 * UNIQUEMENT une aide UX côté JS (désactive le bouton "+ Ajouter une ligne" une fois la limite
 * atteinte, voir assets/repeater-field.js) — n'est PAS la garantie réelle contre un nombre de
 * lignes excessif, qui reste la responsabilité de la fonction de sanitation appelée à la
 * sauvegarde (chaque appelant applique sa propre borne, ce fichier générique n'impose aucune
 * limite par lui-même). `null` (défaut, comportement inchangé) = aucune limite affichée.
 */
function gwseq_render_repeater_field($post, $meta_key, $schema, $nonce_action, $max_rows = null) {
  wp_nonce_field($nonce_action, $nonce_action . '_nonce');

  $rows = get_post_meta($post->ID, $meta_key, true);
  if (!is_array($rows)) $rows = array();

  $max_attr = $max_rows !== null ? ' data-gwseq-repeater-max="' . esc_attr((string) (int) $max_rows) . '"' : '';
  echo '<div class="gwseq-repeater" data-gwseq-repeater="' . esc_attr($meta_key) . '" data-gwseq-next-index="' . esc_attr((string) count($rows)) . '"' . $max_attr . '>';
  echo '<table class="widefat gwseq-repeater__table"><thead><tr>';
  foreach ($schema as $column) {
    echo '<th>' . esc_html($column['label']) . '</th>';
  }
  echo '<th class="gwseq-repeater__col-actions"><span class="screen-reader-text">' . esc_html__('Actions', 'gws-core') . '</span></th>';
  echo '</tr></thead><tbody class="gwseq-repeater__rows">';
  foreach ($rows as $index => $row) {
    echo gwseq_repeater_row_markup($meta_key, $schema, is_array($row) ? $row : array(), $index);
  }
  echo '</tbody></table>';
  echo '<p><button type="button" class="button gwseq-repeater__add">' . esc_html__('+ Ajouter une ligne', 'gws-core') . '</button>';
  if ($max_rows !== null) {
    echo ' <span class="description">' . esc_html(sprintf(
      /* translators: %d: nombre maximum de lignes autorisées */
      __('(maximum %d)', 'gws-core'),
      (int) $max_rows
    )) . '</span>';
  }
  echo '</p>';
  echo '<template class="gwseq-repeater__template">' . gwseq_repeater_row_markup($meta_key, $schema, array(), '__INDEX__') . '</template>';
  echo '</div>';
}

/**
 * Markup d'une ligne (existante ou vierge pour le gabarit JS). $index est un entier pour une
 * ligne réelle (sa position dans le tableau stocké), ou le jeton littéral "__INDEX__" pour le
 * gabarit du JS — voir la note de nommage en tête de fichier : toutes les colonnes d'une même
 * ligne DOIVENT partager le même index pour que PHP les regroupe correctement à la réception.
 */
function gwseq_repeater_row_markup($meta_key, $schema, $row, $index) {
  ob_start();
  echo '<tr class="gwseq-repeater__row">';
  foreach ($schema as $column_key => $column) {
    $type = $column['type'] ?? 'text';
    $value = $row[$column_key] ?? '';
    $name = $meta_key . '[' . $index . '][' . $column_key . ']';
    echo '<td>';
    if ($type === 'textarea') {
      echo '<textarea class="widefat" rows="2" aria-label="' . esc_attr($column['label']) . '" name="' . esc_attr($name) . '">' . esc_textarea($value) . '</textarea>';
    } else {
      $input_type = ($type === 'url') ? 'url' : (in_array($type, array('number', 'integer'), true) ? 'number' : 'text');
      // step="1" limite volontairement 'integer' aux nombres entiers ; 'number' doit au contraire
      // accepter les décimales, ce que le pas par défaut du navigateur (1 si l'attribut step est
      // absent) empêche silencieusement — step="any" lève cette limite pour ce type uniquement.
      $step = '';
      if ($type === 'integer') $step = ' step="1"';
      elseif ($type === 'number') $step = ' step="any"';
      echo '<input class="widefat" type="' . esc_attr($input_type) . '"' . $step . ' aria-label="' . esc_attr($column['label']) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '">';
    }
    echo '</td>';
  }
  echo '<td class="gwseq-repeater__col-actions"><button type="button" class="button-link-delete gwseq-repeater__remove">' . esc_html__('Supprimer', 'gws-core') . '</button></td>';
  echo '</tr>';
  return ob_get_clean();
}

/**
 * Sauvegarde sécurisée : nonce, capability, garde autosave/révision, aucune confiance dans
 * $_POST — la transformation réelle des données est déléguée à gwseq_repeater_sanitize_rows(),
 * fonction pure testée indépendamment de WordPress (voir tests/gws-equestrian-repeater-logic-test.php).
 */
function gwseq_save_repeater_field($post_id, $schema, $meta_key, $nonce_action) {
  $nonce_field = $nonce_action . '_nonce';
  if (!isset($_POST[$nonce_field]) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[$nonce_field])), $nonce_action)) return;
  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
  if (function_exists('wp_is_post_revision') && wp_is_post_revision($post_id)) return;
  if (!current_user_can('edit_post', $post_id)) return;

  $raw_rows = (isset($_POST[$meta_key]) && is_array($_POST[$meta_key])) ? $_POST[$meta_key] : array();
  $clean_rows = gwseq_repeater_sanitize_rows($schema, $raw_rows);
  update_post_meta($post_id, $meta_key, $clean_rows);
}

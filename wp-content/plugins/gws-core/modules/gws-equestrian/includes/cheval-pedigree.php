<?php
/**
 * Cheval — relations de pedigree (Étape 5, socle uniquement) : Père / Mère, chacun soit une fiche
 * Cheval déjà présente dans GWS ("mode gws"), soit un ascendant hors GWS structuré ("mode
 * external"). Ni père ni mère ne sont jamais requis (§25 : un pedigree incomplet est acceptable).
 *
 * CORRECTIF IMPORTANT (revient sur la première version de cette étape) : un ascendant externe
 * n'est PAS nécessairement une feuille terminale. Un marchand ou un cavalier professionnel dont
 * aucun ascendant n'est géré dans GWS doit pouvoir saisir un pedigree complet sur plusieurs
 * générations sans jamais créer une seule fiche `gwseq_cheval` artificielle pour un ancêtre qu'il
 * ne gère pas comme cheval du client (§6 de la demande). Un ascendant externe peut donc lui-même
 * avoir un père et une mère, eux-mêmes externes, et ainsi de suite jusqu'à la profondeur maximale
 * du pedigree (§2-4) — le tout restant facultatif à chaque niveau (l'utilisateur s'arrête où il
 * veut). AUCUNE création automatique de fiche pour ces ascendants, AUCUNE base globale
 * d'ancêtres, AUCUNE déduplication (un même "Kannan" externe peut être ressaisi dans plusieurs
 * pedigrees sans lien entre les saisies) — un futur Network ou référentiel équin pourra
 * éventuellement améliorer cela, pas cette étape (§7).
 *
 * STOCKAGE DE LA BRANCHE EXTERNE (§11) : un arbre récursif {name, breed, father, mother} —
 * father/mother ayant la même forme, jusqu'à GWSEQ_PEDIGREE_MAX_DEPTH - 1 niveaux supplémentaires
 * — est encodé en JSON dans UNE seule meta (`_gwseq_pere_externe` / `_gwseq_mere_externe`), plutôt
 * que des dizaines de meta à plat du type `pere_pere_pere_nom`. Choix JSON plutôt que
 * `serialize()` PHP : représentation lisible, non opaque, indépendante du langage
 * d'implémentation — donc plus simple à valider, faire évoluer, importer (un futur import
 * CSV/XLSX peut construire directement ce même tableau PHP avant de l'encoder) et projeter vers
 * une future API/Network sans dépendre d'un détail d'implémentation PHP. "Versionable" (§11) :
 * couvert par le mécanisme de migration déjà existant de gws-core (includes/migration.php) si une
 * évolution de forme devenait un jour nécessaire — pas de balise de version ad-hoc ajoutée dans le
 * JSON lui-même, qui dupliquerait cette responsabilité déjà couverte ailleurs.
 *
 * RÈGLE ARCHITECTURALE (§15, décidée après l'Étape 4, appliquée au nouveau code uniquement) : une
 * donnée métier doit pouvoir être créée/modifiée programmatiquement sans dépendre du formulaire
 * admin. gwseq_sanitize_horse_parent_input()/gwseq_sanitize_external_ancestor_tree() sont des
 * fonctions pures (aucun accès à $_POST/nonce). gwseq_set_horse_parent($cheval_id, $role, $args)
 * est LA fonction métier qui persiste une relation, GWS ou externe (arbre complet) : elle ne lit
 * jamais $_POST, ne vérifie aucun nonce ni capability (au même titre que
 * update_post_meta()/wp_insert_post() eux-mêmes ne le font pas) — c'est au code appelant
 * (formulaire admin ci-dessous, ou un futur importeur CSV/XLSX, migration, WP-CLI...) d'assurer sa
 * propre autorisation dans son propre contexte. gwseq_save_cheval_pedigree_meta() n'est qu'UN
 * client de cette fonction parmi d'autres possibles.
 *
 * SOURCE UNIQUE PAR RELATION GWS, JAMAIS DE DUPLICATION (§22, pour la branche GWS uniquement — la
 * branche externe EST elle-même la donnée, il n'y a rien d'autre à dédupliquer) : seule la
 * relation (mode + ID) est stockée pour un parent GWS. Nom, race, Global Horse ID ou pedigree du
 * parent GWS ne sont JAMAIS copiés ici — ils sont récupérés à la source par le resolver
 * (includes/pedigree-resolver.php) à chaque résolution (§24 : un parent modifié est vu
 * automatiquement par tous ses descendants, sans resynchronisation).
 *
 * CHANGEMENT DE MODE (GWS <-> externe) — CONSERVATION NON DESTRUCTIVE (§8-9, corrige le
 * comportement "remplacement intégral" de la première version) : passer d'un mode à l'autre ne
 * touche JAMAIS les meta de l'autre branche — une branche externe précédemment saisie reste
 * stockée mais inactive si l'on bascule vers GWS, et réciproquement. Une seule branche est
 * active à la fois : `_gwseq_pere_mode`/`_gwseq_mere_mode` est l'unique source de vérité sur
 * laquelle des deux branches est active ; le resolver ne lit JAMAIS la branche inactive (voir
 * includes/pedigree-resolver.php). Aucune reconnaissance automatique par nom : le rattachement
 * d'un ascendant externe à une vraie fiche GWS est toujours une action explicite de
 * l'utilisateur (choix dans le `<select>`), jamais une correspondance devinée par le système
 * (§8).
 *
 * SUPPRESSION D'UN CHEVAL RÉFÉRENCÉ (§23) : ce fichier n'installe volontairement AUCUN hook sur la
 * suppression/mise à la corbeille d'une fiche Cheval pour "nettoyer" les relations qui la
 * référencent ailleurs — cela reviendrait à modifier automatiquement d'autres fiches suite à une
 * action sur celle-ci, ce que la demande interdit explicitement depuis l'Étape 4 (§34). Mettre un
 * parent à la corbeille ne supprime jamais ses produits (aucune cascade destructrice) : ses
 * propres données restent en base tant qu'il n'est pas supprimé définitivement, donc la
 * résolution continue de fonctionner normalement pour un parent simplement corbeillé.
 */

if (!defined('ABSPATH')) exit;

/**
 * Préfixe de meta pour un rôle donné — noms de meta en français (cohérent avec le reste du modèle
 * de données du module), valeurs techniques ('father'/'mother'/'gws'/'external') en anglais
 * (cohérent avec les autres enums du module : 'male', 'for_sale', 'fixed'...).
 */
function gwseq_horse_parent_meta_prefix($role) {
  return $role === 'mother' ? '_gwseq_mere_' : '_gwseq_pere_';
}

function gwseq_register_cheval_pedigree_meta() {
  foreach (array('_gwseq_pere_mode', '_gwseq_pere_externe', '_gwseq_mere_mode', '_gwseq_mere_externe') as $key) {
    register_post_meta(GWSEQ_CPT_CHEVAL, $key, array('single' => true, 'type' => 'string', 'show_in_rest' => false));
  }
  foreach (array('_gwseq_pere_id', '_gwseq_mere_id') as $key) {
    register_post_meta(GWSEQ_CPT_CHEVAL, $key, array('single' => true, 'type' => 'integer', 'show_in_rest' => false));
  }
}
add_action('init', 'gwseq_register_cheval_pedigree_meta');

/* -------------------------------------------------------------------------------------------
 * Fonctions pures : sanitation et lecture.
 * ----------------------------------------------------------------------------------------- */

/**
 * Valide un identifiant de cheval GWS pour une relation parentale (§26) : ID numérique, post
 * existant, `post_type = gwseq_cheval`, jamais une auto-référence. $current_post_id sert
 * uniquement à rejeter l'auto-référence directe ; les cycles indirects (A -> B -> A) ne peuvent
 * être détectés qu'à la résolution (voir pedigree-resolver.php), car ils dépendent de l'état
 * d'une autre fiche non consultée ici.
 */
function gwseq_sanitize_horse_parent_gws_id($raw_horse_id, $current_post_id = 0) {
  $horse_id = absint($raw_horse_id);
  if ($horse_id <= 0) return 0;
  if ($horse_id === (int) $current_post_id) return 0;
  if (get_post_type($horse_id) !== GWSEQ_CPT_CHEVAL) return 0;
  return $horse_id;
}

/**
 * Sanitise récursivement un ascendant externe et ses propres ascendants (§2-4, §11) : {name,
 * breed, father, mother}, father/mother de la même forme. Un nœud sans nom n'est pas une donnée
 * exploitable et n'est jamais stocké (§25 : absence de donnée = absence, jamais un marqueur vide
 * ambigu) — y compris si son propre père/mère avait été renseigné : sans nom pour CE nœud, tout
 * son sous-arbre est écarté (rien à rattacher). La race reste facultative à tout niveau.
 *
 * $depth_remaining borne strictement la récursion, quelle que soit la profondeur du tableau
 * fourni en entrée (§16 : une structure malformée ou excessivement profonde ne peut jamais
 * contourner la limite côté serveur — au-delà de la borne, les données fournies pour les
 * générations suivantes sont simplement ignorées, jamais stockées).
 */
function gwseq_sanitize_external_ancestor_tree($raw, $depth_remaining) {
  $raw = is_array($raw) ? $raw : array();
  $name = gws_core_field_sanitize('text', $raw['name'] ?? '');
  if ($name === '') return null;
  $breed = gws_core_field_sanitize('text', $raw['breed'] ?? '');
  $node = array('name' => $name, 'breed' => $breed, 'father' => null, 'mother' => null);
  if ($depth_remaining > 0) {
    $node['father'] = gwseq_sanitize_external_ancestor_tree($raw['father'] ?? array(), $depth_remaining - 1);
    $node['mother'] = gwseq_sanitize_external_ancestor_tree($raw['mother'] ?? array(), $depth_remaining - 1);
  }
  return $node;
}

/**
 * Persiste une relation parentale — voir l'en-tête du fichier : fonction métier réutilisable,
 * jamais couplée à $_POST ni à un nonce. Ne touche QUE la branche correspondant au mode reçu ;
 * l'autre branche (GWS ou externe) reste strictement inchangée en base, conformément à la
 * conservation non destructive décidée en §8-9. Attend $raw_args = {mode, horse_id, external}
 * (external = tableau shaped comme gwseq_sanitize_external_ancestor_tree() l'attend).
 */
function gwseq_set_horse_parent($cheval_id, $role, $raw_args) {
  $cheval_id = (int) $cheval_id;
  if (!$cheval_id || !in_array($role, array('father', 'mother'), true)) return false;
  $raw_args = is_array($raw_args) ? $raw_args : array();
  $prefix = gwseq_horse_parent_meta_prefix($role);

  $mode = isset($raw_args['mode']) ? sanitize_key(wp_unslash($raw_args['mode'])) : '';

  if ($mode === 'gws') {
    $horse_id = gwseq_sanitize_horse_parent_gws_id($raw_args['horse_id'] ?? 0, $cheval_id);
    update_post_meta($cheval_id, $prefix . 'mode', $horse_id ? 'gws' : '');
    if ($horse_id) update_post_meta($cheval_id, $prefix . 'id', $horse_id);
    return true; // _externe volontairement non touché (§9)
  }

  if ($mode === 'external') {
    $tree = gwseq_sanitize_external_ancestor_tree($raw_args['external'] ?? array(), GWSEQ_PEDIGREE_MAX_DEPTH - 1);
    update_post_meta($cheval_id, $prefix . 'mode', $tree !== null ? 'external' : '');
    if ($tree !== null) update_post_meta($cheval_id, $prefix . 'externe', wp_json_encode($tree));
    return true; // _id volontairement non touché (§9)
  }

  update_post_meta($cheval_id, $prefix . 'mode', '');
  return true;
}

/**
 * Lecture brute d'une relation (mode '' = aucune branche active). Renvoie TOUJOURS la branche
 * externe décodée si elle existe en base, même si elle est actuellement inactive (mode = 'gws')
 * — c'est au code appelant (rendu du formulaire, qui doit pouvoir réafficher une saisie
 * précédente non perdue) de décider quoi en faire ; le resolver, lui, ne lit jamais la branche
 * qui ne correspond pas au mode actif (voir pedigree-resolver.php).
 */
function gwseq_get_horse_parent($cheval_id, $role) {
  $prefix = gwseq_horse_parent_meta_prefix($role);
  $mode = get_post_meta($cheval_id, $prefix . 'mode', true);
  if (!in_array($mode, array('gws', 'external'), true)) $mode = '';

  $externe_raw = get_post_meta($cheval_id, $prefix . 'externe', true);
  $externe_tree = null;
  if ($externe_raw !== '') {
    $decoded = json_decode($externe_raw, true);
    if (is_array($decoded) && ($decoded['name'] ?? '') !== '') $externe_tree = $decoded;
  }

  return array(
    'mode' => $mode,
    'horse_id' => (int) get_post_meta($cheval_id, $prefix . 'id', true),
    'external' => $externe_tree,
  );
}

/**
 * Production (§13, inchangé par le correctif) : chevaux référençant $cheval_id comme père OU
 * mère GWS. Calculée à la volée par requête inverse (meta_query), jamais stockée sur la fiche du
 * parent. Seules les relations entre deux vraies fiches `gwseq_cheval` comptent — un même
 * ascendant externe (ex. "Kannan") ressaisi dans plusieurs pedigrees n'est jamais rapproché ni
 * dédupliqué (§7, §13).
 */
function gwseq_get_horse_offspring($cheval_id) {
  $cheval_id = (int) $cheval_id;
  if (!$cheval_id) return array();
  return get_posts(array(
    'post_type' => GWSEQ_CPT_CHEVAL,
    'post_status' => array('publish', 'draft', 'pending', 'private'),
    'numberposts' => -1,
    'orderby' => 'title',
    'order' => 'ASC',
    'meta_query' => array(
      'relation' => 'OR',
      array(
        'relation' => 'AND',
        array('key' => '_gwseq_pere_mode', 'value' => 'gws'),
        array('key' => '_gwseq_pere_id', 'value' => $cheval_id),
      ),
      array(
        'relation' => 'AND',
        array('key' => '_gwseq_mere_mode', 'value' => 'gws'),
        array('key' => '_gwseq_mere_id', 'value' => $cheval_id),
      ),
    ),
  ));
}

/* -------------------------------------------------------------------------------------------
 * Meta box et sauvegarde (glue WordPress) — un client parmi d'autres de gwseq_set_horse_parent().
 * ----------------------------------------------------------------------------------------- */

function gwseq_cheval_parent_candidates($exclude_post_id) {
  return get_posts(array(
    'post_type' => GWSEQ_CPT_CHEVAL,
    'post_status' => array('publish', 'draft', 'pending', 'private'),
    'numberposts' => -1,
    'exclude' => array((int) $exclude_post_id),
    'orderby' => 'title',
    'order' => 'ASC',
  ));
}

function gwseq_add_cheval_pedigree_meta_boxes($post) {
  add_meta_box('gwseq-cheval-pedigree', __('Pedigree', 'gws-core'), 'gwseq_render_cheval_pedigree_box', GWSEQ_CPT_CHEVAL, 'normal', 'default');

  // "Production" (§13) : uniquement si au moins un descendant existe (§27 : absence de donnée =
  // absence d'affichage, y compris pour une meta box entière plutôt que de l'afficher vide).
  if ($post && gwseq_get_horse_offspring($post->ID)) {
    add_meta_box('gwseq-cheval-production', __('Production', 'gws-core'), 'gwseq_render_cheval_offspring_box', GWSEQ_CPT_CHEVAL, 'side', 'default');
  }

  // Aperçu de résolution du pedigree (§34 : rendu admin/développement minimal pour vérifier le
  // resolver, jamais le futur rendu public de l'Étape 8) — même garde d'environnement que la
  // boîte de vérification du Global Horse ID (Étape 4) : jamais enregistrée hors local/dev.
  if (function_exists('wp_get_environment_type') && in_array(wp_get_environment_type(), array('local', 'development'), true)) {
    add_meta_box('gwseq-cheval-pedigree-preview', __('Pedigree résolu (visible en local/développement uniquement)', 'gws-core'), 'gwseq_render_cheval_pedigree_preview_box', GWSEQ_CPT_CHEVAL, 'side', 'low');
  }
}
add_action('add_meta_boxes_' . GWSEQ_CPT_CHEVAL, 'gwseq_add_cheval_pedigree_meta_boxes');

function gwseq_render_cheval_pedigree_box($post) {
  wp_nonce_field(GWSEQ_CHEVAL_NONCE_ACTION, GWSEQ_CHEVAL_NONCE_FIELD);
  gwseq_render_cheval_parent_fields($post, 'father', __('Père', 'gws-core'));
  echo '<hr>';
  gwseq_render_cheval_parent_fields($post, 'mother', __('Mère', 'gws-core'));
}

/**
 * Bloc Père ou Mère : source (§UX progressive disclosure) puis, selon le mode, soit le
 * `<select>` de chevaux GWS, soit l'arbre récursif d'ascendant externe. Les deux blocs de champs
 * restent toujours présents dans le DOM (l'un masqué par défaut selon le mode actif — voir
 * assets/cheval-admin.js) : c'est ce qui permet de retrouver une saisie précédente non perdue
 * après un changement de mode (§9), et garantit un formulaire fonctionnel même sans JavaScript
 * (les deux blocs restent alors simplement visibles ensemble — le serveur reste seul
 * autoritaire sur ce qui est réellement enregistré, voir gwseq_set_horse_parent()).
 */
function gwseq_render_cheval_parent_fields($post, $role, $label) {
  $relation = gwseq_get_horse_parent($post->ID, $role);
  $prefix = gwseq_horse_parent_meta_prefix($role);
  ?>
  <div data-gwseq-parent-block="<?php echo esc_attr($role); ?>">
    <p><strong><?php echo esc_html($label); ?></strong></p>
    <p>
      <label>
        <input type="radio" name="<?php echo esc_attr($prefix); ?>mode" value="" data-gwseq-parent-source="<?php echo esc_attr($role); ?>" <?php checked($relation['mode'], ''); ?>>
        <?php esc_html_e('— Non renseigné —', 'gws-core'); ?>
      </label><br>
      <label>
        <input type="radio" name="<?php echo esc_attr($prefix); ?>mode" value="gws" data-gwseq-parent-source="<?php echo esc_attr($role); ?>" <?php checked($relation['mode'], 'gws'); ?>>
        <?php esc_html_e('Cheval déjà présent dans GWS', 'gws-core'); ?>
      </label><br>
      <label>
        <input type="radio" name="<?php echo esc_attr($prefix); ?>mode" value="external" data-gwseq-parent-source="<?php echo esc_attr($role); ?>" <?php checked($relation['mode'], 'external'); ?>>
        <?php esc_html_e('Ascendant hors GWS', 'gws-core'); ?>
      </label>
    </p>
    <p data-gwseq-parent-fields="<?php echo esc_attr($role); ?>-gws" style="<?php echo $relation['mode'] === 'gws' ? '' : 'display:none;'; ?>">
      <select name="<?php echo esc_attr($prefix); ?>id">
        <option value="0"><?php esc_html_e('— Choisir un cheval —', 'gws-core'); ?></option>
        <?php foreach (gwseq_cheval_parent_candidates($post->ID) as $candidate) : ?>
          <option value="<?php echo esc_attr($candidate->ID); ?>" <?php selected($relation['horse_id'], $candidate->ID); ?>><?php echo esc_html(get_the_title($candidate)); ?></option>
        <?php endforeach; ?>
      </select>
    </p>
    <div data-gwseq-parent-fields="<?php echo esc_attr($role); ?>-external" style="<?php echo $relation['mode'] === 'external' ? '' : 'display:none;'; ?>">
      <?php gwseq_render_external_ancestor_fields($prefix . 'externe', $relation['external'] ?? array(), GWSEQ_PEDIGREE_MAX_DEPTH - 1); ?>
    </div>
  </div>
  <?php
}

/**
 * Rendu récursif d'un nœud d'ascendant externe (§5, progressive disclosure) : Nom + Race
 * toujours visibles, puis — s'il reste de la profondeur disponible — un `<details>` natif
 * (aucun JavaScript nécessaire pour ce repli/dépli, accessible par construction) révélant Père/
 * Mère du même ascendant, eux-mêmes du même type. Ouvert automatiquement si des données existent
 * déjà dessous, pour ne jamais masquer une saisie existante. $field_name porte la notation par
 * crochets (`_gwseq_pere_externe[father][name]`...) : $_POST reconstruit nativement l'arbre
 * complet, sans indexation ni JavaScript de calcul d'index (contrairement au composant répétable
 * de l'Étape 2 — ici l'arbre est de forme et de profondeur fixes, jamais un nombre variable de
 * lignes).
 */
function gwseq_render_external_ancestor_fields($field_name, $node, $depth_remaining, $label = '') {
  $node = is_array($node) ? $node : array();
  ?>
  <div style="margin-left:1em; border-left:2px solid #ddd; padding-left:1em; margin-top:0.5em;">
    <?php if ($label !== '') : ?><p><strong><?php echo esc_html($label); ?></strong></p><?php endif; ?>
    <p>
      <label><?php esc_html_e('Nom', 'gws-core'); ?></label><br>
      <input type="text" class="regular-text" name="<?php echo esc_attr($field_name); ?>[name]" value="<?php echo esc_attr($node['name'] ?? ''); ?>">
    </p>
    <p>
      <label><?php esc_html_e('Race / Stud-book (facultatif)', 'gws-core'); ?></label><br>
      <input type="text" class="regular-text" name="<?php echo esc_attr($field_name); ?>[breed]" value="<?php echo esc_attr($node['breed'] ?? ''); ?>">
    </p>
    <?php if ($depth_remaining > 0) : ?>
      <details <?php echo (!empty($node['father']) || !empty($node['mother'])) ? 'open' : ''; ?>>
        <summary><?php esc_html_e('+ Renseigner ses origines', 'gws-core'); ?></summary>
        <?php
        gwseq_render_external_ancestor_fields($field_name . '[father]', $node['father'] ?? array(), $depth_remaining - 1, __('Père', 'gws-core'));
        gwseq_render_external_ancestor_fields($field_name . '[mother]', $node['mother'] ?? array(), $depth_remaining - 1, __('Mère', 'gws-core'));
        ?>
      </details>
    <?php endif; ?>
  </div>
  <?php
}

function gwseq_render_cheval_offspring_box($post) {
  $offspring = gwseq_get_horse_offspring($post->ID);
  if (!$offspring) return;
  echo '<ul>';
  foreach ($offspring as $child) {
    echo '<li><a href="' . esc_url(get_edit_post_link($child->ID)) . '">' . esc_html(get_the_title($child)) . '</a></li>';
  }
  echo '</ul>';
}

function gwseq_render_cheval_pedigree_preview_box($post) {
  echo '<p class="description">' . esc_html__('Aperçu de la résolution du pedigree, à titre de vérification uniquement — ce n’est pas le futur rendu public (Étape 8).', 'gws-core') . '</p>';
  echo gwseq_render_pedigree_node_preview(gwseq_resolve_horse_pedigree($post->ID));
}

/**
 * Sauvegarde — un client parmi d'autres de gwseq_set_horse_parent() (voir l'en-tête du fichier) :
 * ne fait que sécuriser la requête de formulaire (nonce/capability/autosave/révision), puis
 * délègue entièrement la persistance à la fonction métier réutilisable. `_gwseq_pere_externe`/
 * `_gwseq_mere_externe` arrivent déjà comme des tableaux PHP imbriqués dans $_POST (notation par
 * crochets des champs HTML, reconstruite nativement) : aucune transformation nécessaire ici.
 */
function gwseq_save_cheval_pedigree_meta($post_id) {
  if (!isset($_POST[GWSEQ_CHEVAL_NONCE_FIELD]) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[GWSEQ_CHEVAL_NONCE_FIELD])), GWSEQ_CHEVAL_NONCE_ACTION)) return;
  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
  if (function_exists('wp_is_post_revision') && wp_is_post_revision($post_id)) return;
  if (!current_user_can('edit_post', $post_id)) return;

  gwseq_set_horse_parent($post_id, 'father', array(
    'mode' => $_POST['_gwseq_pere_mode'] ?? '',
    'horse_id' => $_POST['_gwseq_pere_id'] ?? 0,
    'external' => $_POST['_gwseq_pere_externe'] ?? array(),
  ));
  gwseq_set_horse_parent($post_id, 'mother', array(
    'mode' => $_POST['_gwseq_mere_mode'] ?? '',
    'horse_id' => $_POST['_gwseq_mere_id'] ?? 0,
    'external' => $_POST['_gwseq_mere_externe'] ?? array(),
  ));
}
add_action('save_post_' . GWSEQ_CPT_CHEVAL, 'gwseq_save_cheval_pedigree_meta');

<?php
/**
 * Vérifie le bloc Actualités de `gws-equestrian` : réutilisation du post type natif `post` (jamais
 * un nouveau `gwseq_actualite`), vocabulaire "Actualités" via le filtre natif
 * `post_type_labels_post`, masquage NON DESTRUCTIF de l'interface des Étiquettes (`post_tag`),
 * retrait des fonctions commentaires/trackbacks pour les nouvelles Actualités, retrait ciblé de
 * Modification rapide (réutilisation de la fonction déjà existante pour les objets métier GWS), et
 * absence de régression sur les quatre objets métier GWS existants (Chevaux/Prestations/Groupes
 * tarifaires/Équipe).
 *
 * Même méthodologie que les autres suites de ce dossier : on exerce directement les fonctions
 * accrochées aux filtres/actions natifs avec des données à la forme réelle de ce que WordPress leur
 * transmettrait, plutôt que de supposer leur effet.
 *
 * Ne fait pas partie des paquets livrés (gws-core.zip / gws-starter.zip).
 */

$failures = 0;
function gws_test_assert($condition, $label) {
  global $failures;
  if ($condition) { echo "OK   - $label\n"; }
  else { echo "FAIL - $label\n"; $failures++; }
}

// --- Stubs WordPress minimaux ---
function __($text, $domain = 'default') { return $text; }
$GLOBALS['__gwseq_test_actions'] = array();
$GLOBALS['__gwseq_test_filters'] = array();
function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
  $GLOBALS['__gwseq_test_actions'][$hook][] = $callback;
  if ($hook === 'init' && is_callable($callback)) call_user_func($callback);
}
function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {
  $GLOBALS['__gwseq_test_filters'][$hook][] = $callback;
}
$GLOBALS['__gwseq_test_removed_post_type_support'] = array();
function remove_post_type_support($post_type, $feature) {
  $GLOBALS['__gwseq_test_removed_post_type_support'][] = array($post_type, $feature);
}
$GLOBALS['__gwseq_test_post_types'] = array();
function register_post_type($post_type, $args = array()) {
  $GLOBALS['__gwseq_test_post_types'][$post_type] = $args;
}
$GLOBALS['__gwseq_test_taxonomies'] = array();
function register_taxonomy($taxonomy, $object_type, $args = array()) {
  $GLOBALS['__gwseq_test_taxonomies'][$taxonomy] = array('object_type' => $object_type, 'args' => $args);
}
function register_post_meta($object_type, $meta_key, $args = array()) {}
function wp_get_environment_type() { return 'production'; }

define('ABSPATH', __DIR__ . '/');
define('GWS_CORE_DIR', dirname(__DIR__) . '/wp-content/plugins/gws-core/');
define('GWS_CORE_URL', 'https://example.test/wp-content/plugins/gws-core/');

$module_dir = GWS_CORE_DIR . 'modules/gws-equestrian/';
require $module_dir . 'module.php';

// =====================================================================================
// `post` reste `post` : aucun nouveau post type, aucune migration, aucun `gwseq_actualite`
// =====================================================================================

gws_test_assert(
  !array_key_exists('post', $GLOBALS['__gwseq_test_post_types']),
  'Actualités : `post` n\'est jamais réenregistré (register_post_type() n\'est appelé que pour les quatre post types métier GWS existants)'
);
gws_test_assert(
  !array_key_exists('gwseq_actualite', $GLOBALS['__gwseq_test_post_types']),
  'Actualités : aucun post type `gwseq_actualite` n\'est créé'
);
$actualites_source = file_get_contents($module_dir . 'includes/actualites.php');
gws_test_assert(
  strpos($actualites_source, "gwseq_actualite'") === false && strpos($actualites_source, 'gwseq_actualite"') === false,
  'Actualités : aucune trace du slug `gwseq_actualite` dans le fichier'
);

// =====================================================================================
// Vocabulaire "Actualités" (§1) : filtre natif post_type_labels_post
// =====================================================================================

gws_test_assert(
  isset($GLOBALS['__gwseq_test_filters']['post_type_labels_post']) && in_array('gwseq_actualites_post_labels', $GLOBALS['__gwseq_test_filters']['post_type_labels_post'], true),
  'Actualités : le vocabulaire est appliqué via le filtre NATIF `post_type_labels_post` (jamais une réinscription de `post`)'
);

// Labels par défaut représentatifs de ce que get_post_type_labels() calcule réellement pour
// `post` en français AVANT notre filtre (valeurs abrégées, suffisant pour prouver l'écrasement).
$default_labels = (object) array(
  'name' => 'Articles',
  'singular_name' => 'Article',
  'menu_name' => 'Articles',
  'name_admin_bar' => 'Article',
  'all_items' => 'Articles',
  'add_new' => 'Ajouter',
  'add_new_item' => 'Ajouter un article',
  'new_item' => 'Nouvel article',
  'edit_item' => 'Modifier l\'article',
  'view_item' => 'Voir l\'article',
  'view_items' => 'Voir les articles',
  'search_items' => 'Rechercher des articles',
  'not_found' => 'Aucun article trouvé',
  'not_found_in_trash' => 'Aucun article trouvé dans la corbeille',
  'archives' => 'Archives des articles',
  'insert_into_item' => 'Insérer dans l\'article',
  'uploaded_to_this_item' => 'Téléversé sur cet article',
  // Propriété volontairement JAMAIS écrasée par gwseq_actualites_post_labels() — sert à prouver
  // que le filtre ne réinitialise pas tout l'objet, seulement les propriétés listées.
  'parent_item_colon' => null,
);
$actualites_labels = gwseq_actualites_post_labels(clone $default_labels);

$expected_overrides = array(
  'name' => 'Actualités',
  'singular_name' => 'Actualité',
  'menu_name' => 'Actualités',
  'name_admin_bar' => 'Actualité',
  'all_items' => 'Toutes les actualités',
  'add_new' => 'Ajouter une actualité',
  'add_new_item' => 'Ajouter une actualité',
  'new_item' => 'Nouvelle actualité',
  'edit_item' => 'Modifier l\'actualité',
  'view_item' => 'Voir l\'actualité',
  'view_items' => 'Voir les actualités',
  'search_items' => 'Rechercher une actualité',
  'not_found' => 'Aucune actualité trouvée',
  'not_found_in_trash' => 'Aucune actualité trouvée dans la corbeille',
);
foreach ($expected_overrides as $key => $expected_value) {
  gws_test_assert(
    $actualites_labels->$key === $expected_value,
    "Libellé Actualités : \$labels->$key = \"$expected_value\""
  );
}
gws_test_assert(
  $actualites_labels->parent_item_colon === null,
  'Libellé Actualités : une propriété non listée dans la demande (parent_item_colon) n\'est jamais écrasée — le filtre ne réinitialise pas l\'objet entier'
);

// =====================================================================================
// Catégories (§3) : AUCUN code — la taxonomie native `category` n'est jamais touchée par ce
// fichier, aucune catégorie créée automatiquement
// =====================================================================================

gws_test_assert(
  strpos($actualites_source, "'category'") === false && strpos($actualites_source, '"category"') === false,
  'Catégories : includes/actualites.php ne référence jamais la taxonomie `category` — aucune catégorie automatique, celles déjà existantes restent intactes par construction'
);
gws_test_assert(
  strpos($actualites_source, 'wp_insert_term') === false,
  'Catégories : aucune création programmatique de terme (aucune fonction de seed de catégories)'
);

// =====================================================================================
// Étiquettes (§4) : masquage NON DESTRUCTIF de l'interface, jamais un désenregistrement
// =====================================================================================

gws_test_assert(
  isset($GLOBALS['__gwseq_test_filters']['register_taxonomy_args']) && in_array('gwseq_hide_post_tag_ui', $GLOBALS['__gwseq_test_filters']['register_taxonomy_args'], true),
  'Étiquettes : masquage appliqué via le filtre NATIF `register_taxonomy_args` (jamais un unregister_taxonomy())'
);
gws_test_assert(
  strpos($actualites_source, 'unregister_taxonomy') === false,
  'Étiquettes : includes/actualites.php n\'appelle jamais unregister_taxonomy() (la taxonomie reste attachée à `post`, aucune donnée détruite)'
);

$post_tag_default_args = array('public' => true, 'show_ui' => true, 'show_admin_column' => true, 'show_in_rest' => true, 'hierarchical' => false);
$post_tag_filtered = gwseq_hide_post_tag_ui($post_tag_default_args, 'post_tag');
gws_test_assert($post_tag_filtered['show_ui'] === false, 'Étiquettes : show_ui remis à false (boîte "Étiquettes" masquée dans l\'éditeur, classique et par blocs)');
gws_test_assert($post_tag_filtered['show_admin_column'] === false, 'Étiquettes : show_admin_column remis à false (colonne "Étiquettes" masquée dans la liste des Actualités)');
gws_test_assert($post_tag_filtered['show_in_rest'] === true, 'Étiquettes : show_in_rest INCHANGÉ — aucune restriction d\'API, uniquement une simplification d\'écran');
gws_test_assert($post_tag_filtered['public'] === true, 'Étiquettes : public INCHANGÉ — la taxonomie reste pleinement enregistrée, jamais désactivée');

// --- Jamais appliqué à une autre taxonomie : category, et toute taxonomie GWS existante ---
$category_args = array('show_ui' => true, 'show_admin_column' => true);
gws_test_assert(gwseq_hide_post_tag_ui($category_args, 'category') === $category_args, 'Étiquettes : la taxonomie `category` traverse le filtre inchangée');

$gwseq_tax_args = array('show_ui' => true, 'meta_box_cb' => 'post_categories_meta_box');
gws_test_assert(gwseq_hide_post_tag_ui($gwseq_tax_args, GWSEQ_TAX_CATEGORIE_CHEVAL) === $gwseq_tax_args, 'Étiquettes : la taxonomie Catégorie de cheval (module Chevaux) traverse le filtre inchangée — aucune régression croisée');

// =====================================================================================
// Commentaires / trackbacks (§5) : retirés pour les NOUVELLES Actualités, aucune donnée existante
// touchée
// =====================================================================================

gws_test_assert(
  in_array(array('post', 'comments'), $GLOBALS['__gwseq_test_removed_post_type_support'], true),
  'Commentaires : remove_post_type_support(\'post\', \'comments\') bien appelé — retire la boîte Discussion de l\'éditeur (classique et par blocs) et la colonne Commentaires de la liste'
);
gws_test_assert(
  in_array(array('post', 'trackbacks'), $GLOBALS['__gwseq_test_removed_post_type_support'], true),
  'Trackbacks/pings : remove_post_type_support(\'post\', \'trackbacks\') bien appelé'
);
gws_test_assert(
  in_array(array('gwseq_cheval', 'comments'), $GLOBALS['__gwseq_test_removed_post_type_support'], true) === false,
  'Commentaires : jamais retirés d\'un autre post type (ex. Cheval) — ciblé uniquement sur `post`'
);
// L'effet documenté (voir includes/actualites.php) sur get_default_comment_status() est un
// comportement NATIF de WordPress lui-même (fonction non exercée ici, hors périmètre d'un test
// sans le cœur WordPress chargé) — ce test vérifie uniquement que la cause (le retrait du support)
// est bien en place, seule chose relevant réellement de ce module.
gws_test_assert(
  strpos($actualites_source, 'wp_delete_comment') === false && strpos($actualites_source, "'comment_status'") === false,
  'Commentaires : aucune suppression ni modification de données de commentaire existantes — uniquement un retrait de support pour les écrans futurs'
);

// =====================================================================================
// Modification rapide (§6) : réutilisation de la fonction déjà existante, `post` ajouté à sa
// portée sans dupliquer un second filtre
// =====================================================================================

// Compte les occurrences du callback précis de retrait de Quick Edit (jamais le nombre TOTAL de
// filtres `post_row_actions` du module — le lot « Partager un cheval » y ajoute légitimement son
// propre filtre, pour une action de ligne totalement différente, voir includes/cheval-share-admin.php).
gws_test_assert(
  !isset($GLOBALS['__gwseq_test_filters']['post_row_actions']) || count(array_keys($GLOBALS['__gwseq_test_filters']['post_row_actions'], 'gwseq_remove_quick_edit_row_action', true)) === 1,
  'Modification rapide : le filtre `post_row_actions` de retrait de Quick Edit n\'est enregistré qu\'UNE SEULE fois (includes/actualites.php ne redéfinit jamais son propre filtre — la fonction générique déjà existante d\'includes/admin-ui.php est réutilisée telle quelle)'
);
$native_row_actions = array('edit' => '<a>Modifier</a>', 'inline hide-if-no-js' => '<button>Modification rapide</button>', 'trash' => '<a>Corbeille</a>');
$filtered_post = gwseq_remove_quick_edit_row_action($native_row_actions, (object) array('post_type' => 'post'));
gws_test_assert(!array_key_exists('inline hide-if-no-js', $filtered_post), 'Modification rapide : bien retirée pour `post` (Actualités)');
gws_test_assert(array_key_exists('edit', $filtered_post) && array_key_exists('trash', $filtered_post), 'Modification rapide : Modifier et Corbeille restent bien disponibles pour les Actualités');

$filtered_page = gwseq_remove_quick_edit_row_action($native_row_actions, (object) array('post_type' => 'page'));
gws_test_assert(array_key_exists('inline hide-if-no-js', $filtered_page), 'Modification rapide : jamais désactivée globalement — les Pages conservent l\'action');

// =====================================================================================
// Permissions Éditeur (§10) : aucune capacité personnalisée introduite pour `post` — le rôle
// Éditeur dispose déjà nativement de toutes les capacités nécessaires (edit_posts,
// publish_posts...), jamais modifiées par ce module.
// =====================================================================================

gws_test_assert(
  strpos($actualites_source, 'current_user_can') === false && strpos($actualites_source, 'map_meta_cap') === false && strpos($actualites_source, 'add_cap') === false,
  'Permissions Éditeur : includes/actualites.php ne touche à aucune capacité — le rôle Éditeur conserve l\'intégralité de ses droits natifs sur `post` (créer/modifier/publier une actualité), sans capacité technique supplémentaire ni restriction ajoutée'
);

// =====================================================================================
// Cadrage de l'éditeur par blocs (bloc "Actualités : cadrer Gutenberg")
// =====================================================================================

gws_test_assert(
  isset($GLOBALS['__gwseq_test_filters']['allowed_block_types_all']) && in_array('gwseq_restrict_actualites_blocks', $GLOBALS['__gwseq_test_filters']['allowed_block_types_all'], true),
  'Gutenberg : la restriction est appliquée via le filtre NATIF `allowed_block_types_all`, jamais un patch de l\'éditeur'
);

$allowed = gwseq_actualites_allowed_blocks();
$expected_allowed = array('core/paragraph', 'core/heading', 'core/list', 'core/list-item', 'core/image', 'core/gallery', 'core/buttons', 'core/button', 'core/video', 'core/embed');
gws_test_assert($allowed === $expected_allowed, 'Gutenberg : allowlist exacte (Paragraphe, Titre, Liste [+ son bloc interne obligatoire list-item], Image, Galerie, Bouton [+ son conteneur obligatoire buttons], Vidéo, intégration vidéo sûre)');
foreach (array('core/columns', 'core/column', 'core/group', 'core/cover', 'core/html', 'core/code', 'core/freeform', 'core/legacy-widget', 'core/widget-area', 'core/template-part', 'core/site-title', 'core/navigation', 'core/shortcode') as $forbidden) {
  gws_test_assert(!in_array($forbidden, $allowed, true), "Gutenberg : le bloc de mise en page avancée/technique \"$forbidden\" est bien exclu de l'allowlist Actualités");
}

// --- Scopé UNIQUEMENT à `post` (Actualités) : Pages et tout autre contexte (widgets par blocs,
// éditeur de site — reconnaissable ici par l'absence de $context->post) reçoivent la liste REÇUE
// EN ENTRÉE inchangée, jamais recalculée ni un true/false générique qui écraserait un filtre tiers
// déjà appliqué avant celui-ci ---
$true_wide_open = true; // ex. : un thème/plugin tiers a déjà autorisé tous les blocs
gws_test_assert(
  gwseq_restrict_actualites_blocks($true_wide_open, (object) array('post' => (object) array('post_type' => 'post'))) === $expected_allowed,
  'Gutenberg : une Actualité reçoit bien l\'allowlist restreinte'
);
gws_test_assert(
  gwseq_restrict_actualites_blocks($true_wide_open, (object) array('post' => (object) array('post_type' => 'page'))) === true,
  'Gutenberg : une Page n\'est jamais affectée — palette complète (valeur reçue en entrée) préservée telle quelle'
);
gws_test_assert(
  gwseq_restrict_actualites_blocks($true_wide_open, (object) array('post' => (object) array('post_type' => GWSEQ_CPT_CHEVAL))) === true,
  'Gutenberg : aucune régression croisée sur un autre post type GWS (ex. Cheval)'
);
gws_test_assert(
  gwseq_restrict_actualites_blocks($true_wide_open, (object) array('post' => null)) === true,
  'Gutenberg : un contexte sans post (ex. widgets par blocs, éditeur de site) n\'est jamais affecté'
);
$null_context_without_post_property = (object) array();
gws_test_assert(
  gwseq_restrict_actualites_blocks($true_wide_open, $null_context_without_post_property) === true,
  'Gutenberg : un contexte dépourvu même de la propriété `post` (robustesse) n\'est jamais affecté'
);

// =====================================================================================
// Front (§7) : aucun rendu front développé dans ce lot
// =====================================================================================

gws_test_assert(
  strpos($actualites_source, 'WP_Query') === false && strpos($actualites_source, 'get_template_part') === false,
  'Front : includes/actualites.php ne construit aucun rendu front — WP_Query/permaliens/gabarits restent entièrement à la charge du thème, plus tard'
);

// =====================================================================================
// Non-régression : les quatre objets métier GWS existants restent inchangés
// =====================================================================================

$post_types = $GLOBALS['__gwseq_test_post_types'];
gws_test_assert(count($post_types) === 4, 'Non-régression : toujours exactement quatre post types métier GWS enregistrés (Prestation, Groupe, Cheval, Membre)');
foreach (array(GWSEQ_CPT_PRESTATION, GWSEQ_CPT_GROUPE, GWSEQ_CPT_CHEVAL, GWSEQ_CPT_MEMBRE) as $expected) {
  gws_test_assert(array_key_exists($expected, $post_types), "Non-régression : post type '$expected' toujours enregistré");
}
$categorie_cheval = $GLOBALS['__gwseq_test_taxonomies'][GWSEQ_TAX_CATEGORIE_CHEVAL] ?? null;
gws_test_assert($categorie_cheval !== null && $categorie_cheval['object_type'] === GWSEQ_CPT_CHEVAL, 'Non-régression : la taxonomie Catégorie de cheval reste attachée à Cheval, inchangée');

echo ($failures === 0 ? 'Tous les tests sont passés.' : "$failures test(s) en échec.") . "\n";
exit($failures === 0 ? 0 : 1);

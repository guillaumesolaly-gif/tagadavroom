<?php
/**
 * Vérifie la logique de l'Étape 1 (Fondations) du module GWS Equestrian : ce que le module
 * enregistre réellement (post types, taxonomie) et le respect des contraintes techniques et de
 * convention qui ont guidé sa conception (limite de longueur WordPress, préfixe distinct,
 * absence de page publique pour le Groupe tarifaire). Ne remplace pas une recette réelle dans
 * WordPress (voir AI-AGENT.md §7) : ce test porte sur les arguments passés aux fonctions
 * d'enregistrement, pas sur le comportement réel de WordPress une fois ces types enregistrés.
 *
 * Ne fait pas partie des paquets livrés (gws-core.zip / gws-starter.zip).
 */

$failures = 0;
function gws_test_assert($condition, $label) {
  global $failures;
  if ($condition) { echo "OK   - $label\n"; }
  else { echo "FAIL - $label\n"; $failures++; }
}

// --- Stubs WordPress minimaux : on capture les appels plutôt que de simuler un vrai registre ---
$GLOBALS['__gwseq_test_post_types'] = array();
function register_post_type($post_type, $args = array()) {
  $GLOBALS['__gwseq_test_post_types'][$post_type] = $args;
}

$GLOBALS['__gwseq_test_taxonomies'] = array();
function register_taxonomy($taxonomy, $object_type, $args = array()) {
  $GLOBALS['__gwseq_test_taxonomies'][$taxonomy] = array('object_type' => $object_type, 'args' => $args);
}

// Simule le hook 'init' en exécutant immédiatement le callback : suffisant pour les fonctions
// d'enregistrement testées ici. Les autres hooks (ex. admin_enqueue_scripts, admin_menu,
// ajoutés depuis les Étapes 2 et 3) ne sont pas déclenchés par ce stub : hors du périmètre de ce
// test, qui ne porte que sur les fondations (post types/taxonomie).
function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
  if ($hook === 'init' && is_callable($callback)) call_user_func($callback);
}
// add_filter n'a besoin que d'exister (plusieurs fichiers de l'Étape 3 l'appellent au chargement
// pour enregistrer des colonnes de liste d'administration) : jamais déclenché par ce test.
function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {}
// register_post_meta n'a besoin que d'exister : les fichiers de l'Étape 3 l'appellent sur 'init'
// (donc réellement exécuté par ce stub) mais ce test ne porte pas sur les meta enregistrées.
function register_post_meta($object_type, $meta_key, $args = array()) {}
// Actualités (adaptation de `post`) : gwseq_remove_actualites_comments_support() est accrochée sur
// 'init' (donc réellement exécutée par ce stub) — on capture les appels plutôt que de supposer leur
// effet, sans se soucier ici de leur contenu (hors périmètre de ce test sur les fondations).
$GLOBALS['__gwseq_test_removed_post_type_support'] = array();
function remove_post_type_support($post_type, $feature) {
  $GLOBALS['__gwseq_test_removed_post_type_support'][] = array($post_type, $feature);
}
// i18n (Étape 3, relecture) : les libellés de post types/taxonomie passent désormais par __() —
// ce test porte sur les arguments d'enregistrement, pas sur la traduction, donc simple passe-plat.
function __($text, $domain = 'default') { return $text; }

// Depuis l'Étape 2, module.php charge aussi le composant répétable et sa démonstration QA
// (includes/repeater-field.php, includes/qa-repeater.php). Ce test ne porte que sur les
// fondations (post types/taxonomie) : environnement "production" simulé pour que la
// démonstration QA (réservée à local/development) ne s'enregistre pas et n'ajoute donc aucun
// objet supplémentaire aux assertions ci-dessous — voir gws-equestrian-repeater-logic-test.php
// pour les tests dédiés au composant répétable lui-même.
function wp_get_environment_type() { return 'production'; }

define('ABSPATH', __DIR__ . '/');
define('GWS_CORE_DIR', dirname(__DIR__) . '/wp-content/plugins/gws-core/');
define('GWS_CORE_URL', 'https://example.test/wp-content/plugins/gws-core/');
$module_dir = GWS_CORE_DIR . 'modules/gws-equestrian/';

require $module_dir . 'module.php';

$post_types = $GLOBALS['__gwseq_test_post_types'];
$taxonomies = $GLOBALS['__gwseq_test_taxonomies'];

// --- Post types attendus, ni plus ni moins ---
gws_test_assert(
  count($post_types) === 4,
  'Exactement quatre post types enregistrés à cette étape (Prestation, Groupe, Cheval, Membre)'
);
foreach (array(GWSEQ_CPT_PRESTATION, GWSEQ_CPT_GROUPE, GWSEQ_CPT_CHEVAL, GWSEQ_CPT_MEMBRE) as $expected) {
  gws_test_assert(
    array_key_exists($expected, $post_types),
    "Post type attendu enregistré : $expected"
  );
}

// --- Contrainte technique WordPress : 20 caractères max pour un nom de post type ---
foreach ($post_types as $slug => $args) {
  gws_test_assert(
    strlen($slug) <= 20,
    "Post type '$slug' respecte la limite WordPress de 20 caractères (longueur réelle : " . strlen($slug) . ')'
  );
}

// --- Contrainte technique WordPress : 32 caractères max pour un nom de taxonomie ---
foreach ($taxonomies as $slug => $data) {
  gws_test_assert(
    strlen($slug) <= 32,
    "Taxonomie '$slug' respecte la limite WordPress de 32 caractères (longueur réelle : " . strlen($slug) . ')'
  );
}

// --- Préfixe distinct du module (AI-AGENT.md §3 / ARCHITECTURE.md §8) : jamais gws_/gws_core_,
// toujours gwseq_, aussi bien pour les slugs que pour les noms de fonctions déclarées ---
foreach (array_keys($post_types) as $slug) {
  gws_test_assert(strpos($slug, 'gwseq_') === 0, "Post type '$slug' porte bien le préfixe gwseq_");
}
foreach (array_keys($taxonomies) as $slug) {
  gws_test_assert(strpos($slug, 'gwseq_') === 0, "Taxonomie '$slug' porte bien le préfixe gwseq_");
}

$module_files = array(
  $module_dir . 'module.php',
  $module_dir . 'includes/post-types.php',
  $module_dir . 'includes/taxonomies.php',
  $module_dir . 'includes/repeater-field.php',
  $module_dir . 'includes/qa-repeater.php',
  $module_dir . 'includes/settings.php',
  $module_dir . 'includes/admin-ui.php',
  $module_dir . 'includes/groupe-admin.php',
  $module_dir . 'includes/prestation-fields.php',
  $module_dir . 'includes/presets.php',
  $module_dir . 'includes/membre-fields.php',
  $module_dir . 'includes/membre-editor.php',
  $module_dir . 'includes/actualites.php',
);
$prefix_violation_found = false;
$non_gwseq_functions = array();
foreach ($module_files as $file) {
  $contents = file_get_contents($file);
  if (preg_match('/\bfunction\s+(gws_core_|gws_)[a-zA-Z0-9_]*\s*\(/', $contents)) {
    $prefix_violation_found = true;
  }
  if (preg_match_all('/\bfunction\s+([a-zA-Z0-9_]+)\s*\(/', $contents, $matches)) {
    foreach ($matches[1] as $function_name) {
      if (strpos($function_name, 'gwseq_') !== 0) $non_gwseq_functions[] = $function_name;
    }
  }
}
gws_test_assert(
  !$prefix_violation_found,
  'Aucune fonction du module ne réutilise le préfixe réservé au cœur (gws_/gws_core_)'
);
gws_test_assert(
  empty($non_gwseq_functions),
  'Toutes les fonctions déclarées par le module utilisent le préfixe gwseq_ (' . (empty($non_gwseq_functions) ? 'aucune exception' : implode(', ', $non_gwseq_functions)) . ')'
);

// --- Aucune collision de slug avec les post types déjà utilisés par d'autres modules du projet
// (voir le registre de modules/README.md) ---
$other_modules_post_types = array('bp_item', 'gws_qa_item');
foreach (array_keys($post_types) as $slug) {
  gws_test_assert(
    !in_array($slug, $other_modules_post_types, true),
    "Post type '$slug' ne collisionne pas avec un post type d'un autre module du projet"
  );
}

// --- Groupe tarifaire : jamais de page publique (décision de conception validée) ---
$groupe = $post_types[GWSEQ_CPT_GROUPE] ?? array();
gws_test_assert(($groupe['public'] ?? null) === false, 'Groupe tarifaire : public => false');
gws_test_assert(($groupe['has_archive'] ?? null) === false, 'Groupe tarifaire : has_archive => false');
gws_test_assert(($groupe['rewrite'] ?? null) === false, 'Groupe tarifaire : rewrite => false (aucune URL générée)');
gws_test_assert(($groupe['exclude_from_search'] ?? null) === true, 'Groupe tarifaire : exclu de la recherche');

// --- Prestation, Cheval et Membre : publics avec archive, à l'inverse du Groupe tarifaire ---
foreach (array(GWSEQ_CPT_PRESTATION => 'Prestation', GWSEQ_CPT_CHEVAL => 'Cheval', GWSEQ_CPT_MEMBRE => 'Membre') as $slug => $label) {
  $args = $post_types[$slug] ?? array();
  gws_test_assert(($args['public'] ?? null) === true, "$label : public => true");
  gws_test_assert(($args['has_archive'] ?? null) === true, "$label : has_archive => true");
}

// --- Module Équipe (Membre) : ordre natif (menu_order), pas d'éditeur classique de contenu, photo
// via l'image à la une relabellée "Photo" (jamais une meta parallèle) ---
$membre = $post_types[GWSEQ_CPT_MEMBRE] ?? array();
gws_test_assert(
  in_array('page-attributes', $membre['supports'] ?? array(), true),
  'Membre : support \'page-attributes\' présent (ordre natif menu_order, §6 de la demande)'
);
gws_test_assert(
  in_array('thumbnail', $membre['supports'] ?? array(), true),
  'Membre : support \'thumbnail\' présent (Photo = image à la une native)'
);
gws_test_assert(
  !in_array('editor', $membre['supports'] ?? array(), true),
  'Membre : pas de support \'editor\' (fiche 100% structurée, aucun rendu front dans ce lot)'
);
gws_test_assert(
  ($membre['labels']['name'] ?? null) === 'Équipe',
  'Membre : libellé du menu d\'administration "Équipe" (§1 de la demande)'
);
gws_test_assert(
  ($membre['labels']['singular_name'] ?? null) === 'Membre',
  'Membre : libellé de fiche singulier "Membre" (§1 de la demande)'
);
gws_test_assert(
  ($membre['labels']['all_items'] ?? null) === 'Tous les membres',
  'Membre : sous-menu "Tous les membres" (§1 : "Équipe → Tous les membres")'
);
gws_test_assert(
  ($membre['labels']['add_new_item'] ?? null) === 'Ajouter un membre',
  'Membre : sous-menu "Ajouter un membre" (§1 : "Équipe → Ajouter un membre")'
);

// --- Libellé de recherche métier explicite sur chaque CPT (micro-correction post-recette Équipe) :
// sans lui, WordPress replie sur le défaut générique "Rechercher des articles" (search_items n'est
// jamais dérivé automatiquement de 'name'/'singular_name') ---
$expected_search_items = array(
  GWSEQ_CPT_PRESTATION => 'Rechercher une prestation',
  GWSEQ_CPT_GROUPE => 'Rechercher un groupe tarifaire',
  GWSEQ_CPT_CHEVAL => 'Rechercher un cheval',
  GWSEQ_CPT_MEMBRE => 'Rechercher des membres',
);
foreach ($expected_search_items as $slug => $expected_label) {
  gws_test_assert(
    ($post_types[$slug]['labels']['search_items'] ?? null) === $expected_label,
    "Post type '$slug' : libellé de recherche natif explicite \"$expected_label\" (jamais le défaut générique \"Rechercher des articles\")"
  );
}

// --- Action de ligne "Modification rapide" (Quick Edit) retirée sur les quatre objets métier GWS
// Equestrian ET sur `post` (Actualités, §6 de la demande Actualités), jamais globalement ---
foreach (array(GWSEQ_CPT_PRESTATION, GWSEQ_CPT_GROUPE, GWSEQ_CPT_CHEVAL, GWSEQ_CPT_MEMBRE, 'post') as $slug) {
  $native_actions = array('edit' => '<a>Modifier</a>', 'inline hide-if-no-js' => '<button>Modification rapide</button>', 'trash' => '<a>Corbeille</a>');
  $filtered = gwseq_remove_quick_edit_row_action($native_actions, (object) array('post_type' => $slug));
  gws_test_assert(
    !array_key_exists('inline hide-if-no-js', $filtered),
    "Post type '$slug' : action de ligne \"Modification rapide\" bien retirée"
  );
  gws_test_assert(
    array_key_exists('edit', $filtered) && array_key_exists('trash', $filtered),
    "Post type '$slug' : les autres actions de ligne (Modifier, Corbeille) restent intactes"
  );
}
// Contrôle négatif : un post type totalement hors périmètre GWS (Pages, natif WordPress, jamais
// touché par ce module) conserve Quick Edit — la preuve qu'il ne s'agit jamais d'une désactivation
// globale, y compris maintenant que `post` fait partie des post types ciblés.
$native_actions_other = array('edit' => '<a>Modifier</a>', 'inline hide-if-no-js' => '<button>Modification rapide</button>');
$filtered_other = gwseq_remove_quick_edit_row_action($native_actions_other, (object) array('post_type' => 'page'));
gws_test_assert(
  array_key_exists('inline hide-if-no-js', $filtered_other),
  'Quick Edit : jamais désactivé globalement — les Pages conservent leur action "Modification rapide"'
);

// --- Étape 3 : Ordre d'affichage natif (page-attributes) sur Prestation et Groupe, Description
// courte native (excerpt) sur Groupe uniquement ---
foreach (array(GWSEQ_CPT_PRESTATION => 'Prestation', GWSEQ_CPT_GROUPE => 'Groupe tarifaire') as $slug => $label) {
  $supports = $post_types[$slug]['supports'] ?? array();
  gws_test_assert(in_array('page-attributes', $supports, true), "$label : support 'page-attributes' présent (ordre natif menu_order)");
}
gws_test_assert(
  in_array('excerpt', $post_types[GWSEQ_CPT_GROUPE]['supports'] ?? array(), true),
  'Groupe tarifaire : support \'excerpt\' présent (description courte native)'
);
gws_test_assert(
  !in_array('excerpt', $post_types[GWSEQ_CPT_PRESTATION]['supports'] ?? array(), true),
  'Prestation : pas de support \'excerpt\' (la description passe par post_content, pas par un résumé)'
);

// --- Taxonomie catégorie de cheval : attachée au bon post type, multi-valeurs (non hiérarchique) ---
$categorie = $taxonomies[GWSEQ_TAX_CATEGORIE_CHEVAL] ?? null;
gws_test_assert($categorie !== null, 'Taxonomie catégorie de cheval enregistrée');
if ($categorie !== null) {
  gws_test_assert(
    $categorie['object_type'] === GWSEQ_CPT_CHEVAL,
    'Taxonomie catégorie de cheval attachée au post type Cheval'
  );
  gws_test_assert(
    ($categorie['args']['hierarchical'] ?? null) === false,
    'Taxonomie catégorie de cheval non hiérarchique (compatible multi-valeurs, un cheval peut avoir plusieurs catégories)'
  );
}

// --- Actualités (adaptation de `post`) : commentaires/trackbacks bien retirés au chargement du
// module (voir tests/gws-equestrian-actualites-logic-test.php pour la couverture complète du bloc
// Actualités — labels, masquage des Étiquettes, permissions Éditeur) ---
gws_test_assert(
  in_array(array('post', 'comments'), $GLOBALS['__gwseq_test_removed_post_type_support'], true),
  'Actualités : le support des commentaires est bien retiré de `post` au chargement du module'
);
gws_test_assert(
  in_array(array('post', 'trackbacks'), $GLOBALS['__gwseq_test_removed_post_type_support'], true),
  'Actualités : le support des trackbacks/pings est bien retiré de `post` au chargement du module'
);
gws_test_assert(
  !array_key_exists('post', $post_types) && !array_key_exists('page', $post_types),
  'Actualités : `post` (et `page`) ne sont jamais réenregistrés par le module — post_type reste bien le natif WordPress, jamais un nouveau `gwseq_actualite`'
);

// =====================================================================================
// Mises en avant (Pop-in / Sticky bar) : module retiré en 0.21.0 (décision produit, cette
// fonctionnalité périphérique sera couverte le cas échéant par une extension WordPress tierce
// spécialisée — voir CHANGELOG.md). Vérifie que le retrait est bien complet : ni les post types, ni
// le menu, ni aucune trace de meta box/libellé associés ne subsistent.
// =====================================================================================

gws_test_assert(!array_key_exists('gwseq_popin', $post_types), 'Retrait Mises en avant : le post type gwseq_popin n\'est plus enregistré');
gws_test_assert(!array_key_exists('gwseq_sticky_bar', $post_types), 'Retrait Mises en avant : le post type gwseq_sticky_bar n\'est plus enregistré');
gws_test_assert(
  !in_array('Mises en avant', array_column(array_column($post_types, 'labels'), 'name'), true),
  'Retrait Mises en avant : aucun post type restant ne porte le libellé de menu "Mises en avant"'
);

echo ($failures === 0 ? 'Tous les tests sont passés.' : "$failures test(s) en échec.") . "\n";
exit($failures === 0 ? 0 : 1);

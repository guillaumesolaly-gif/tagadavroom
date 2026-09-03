<?php
/**
 * Actualités — adaptation du système NATIF des articles WordPress (`post`) au vocabulaire et aux
 * usages de GWS Equestrian. Volontairement AUCUN nouveau post type (`gwseq_actualite` n'existe
 * pas) : `post` reste `post`, aucune migration, aucun système éditorial parallèle. Cette
 * adaptation appartient au produit GWS Equestrian (back-office), jamais au thème public — aucun
 * fichier de `wp-content/themes/gws-starter/` n'est modifié par ce fichier.
 *
 * AUDIT PRÉALABLE (avant toute modification de ce lot) : aucune personnalisation existante de
 * `post` n'a été trouvée dans `gws-core` ni dans `gws-starter` — ni libellés, ni support retiré,
 * ni réglage de discussion, ni action de menu/liste, ni gestion de `post_tag`. `post` était encore
 * dans son état par défaut WordPress avant ce lot (voir le CR de livraison pour le détail de cet
 * audit).
 *
 * CINQ MÉCANISMES NATIFS DISTINCTS, JAMAIS UNE RÉÉCRITURE DE L'ENREGISTREMENT DE `post`/
 * `post_tag` (impossible d'appeler `register_post_type()`/`register_taxonomy()` une seconde fois
 * sur des identifiants déjà enregistrés par WordPress core sans conflit) :
 *
 * 1. `post_type_labels_post` (filtre natif appliqué par `get_post_type_labels()` — le mécanisme
 *    WordPress prévu précisément pour personnaliser le vocabulaire d'un post type déjà enregistré,
 *    y compris natif) : renomme les libellés visibles du menu et des écrans d'administration.
 *    N'affecte JAMAIS le nom technique `post`, ni les données existantes, ni les URLs/permaliens.
 * 2. `register_taxonomy_args` (filtre natif appliqué par `register_taxonomy()` avant construction
 *    de l'objet `WP_Taxonomy`, scopé ici à `post_tag` uniquement) : masque l'interface des
 *    Étiquettes (§4 de la demande) sans jamais désinscrire la taxonomie de `post` ni toucher aux
 *    relations déjà enregistrées en base (`wp_term_relationships`) — une simplification d'écran,
 *    jamais une migration.
 * 3. `remove_post_type_support('post', ...)` (accroché à `init`, APRÈS l'enregistrement natif de
 *    `post` par `create_initial_post_types()` en priorité 0 — jamais avant, sous peine de n'avoir
 *    aucun effet) : retire les fonctions commentaires/trackbacks de l'écran d'édition. Effet de
 *    bord NATIF ET DOCUMENTÉ de WordPress lui-même, jamais un code ajouté ici : une fois le support
 *    retiré, `get_default_comment_status('post')` (fonction native, voir wp-includes/comment.php)
 *    renvoie systématiquement `'closed'` pour toute NOUVELLE Actualité, quel que soit le réglage
 *    global Réglages → Discussion — ce qui satisfait directement l'exigence « empêcher les
 *    nouvelles Actualités d'être créées avec les commentaires ouverts » sans code supplémentaire.
 *    Le statut déjà enregistré sur une Actualité EXISTANTE n'est jamais modifié (aucune donnée de
 *    commentaire ni aucun article existant n'est touché par cette fonction).
 * 4. Réutilisation de la fonction déjà existante `gwseq_remove_quick_edit_row_action()`
 *    (`includes/admin-ui.php`, déjà partagée par Chevaux/Membres/Prestations/Groupes tarifaires) :
 *    `post` y est ajouté à la liste des post types ciblés, jamais une désactivation globale de
 *    Quick Edit ni un second filtre dupliqué — voir §8 de la demande (« si une fonction générique
 *    déjà existante doit proprement prendre en charge post, conserver son comportement actuel et
 *    couvrir la non-régression »).
 * 5. `allowed_block_types_all` (filtre natif de l'éditeur par blocs, introduit précisément pour
 *    restreindre la palette de blocs d'un contexte d'édition donné — jamais un patch de Gutenberg
 *    lui-même) : limite les blocs insérables sur l'écran d'édition d'une Actualité à une allowlist
 *    volontairement restreinte (voir plus bas). Scopé via `$context->post->post_type === 'post'` —
 *    absent pour tout autre contexte (Pages, widgets par blocs, éditeur de site) qui retombent tous
 *    sur `$allowed_block_types` REÇU EN ENTRÉE, inchangé, jamais recalculé ni un `true`/`false`
 *    générique qui écraserait un éventuel filtre tiers déjà appliqué avant celui-ci.
 *
 * AUDIT DES BLOCS (préalable à #5, comme demandé) : aucun filtre `allowed_block_types`/
 * `allowed_block_types_all` n'existait avant ce lot (ni dans gws-core, ni dans gws-starter) —
 * l'éditeur par blocs tournait donc avec la palette complète de WordPress core, plus le seul bloc
 * personnalisé du thème (`gws/resource-link`, `wp-content/themes/gws-starter/inc/blocks.php`,
 * un lien de ressource avec icône — jamais utilisé par une Actualité existante, aucun contenu en
 * base ne le référence). Aucun bloc "technique invisible" n'est nécessaire au bon fonctionnement de
 * l'éditeur dans ce contexte (site sans contenu hérité, sans blocs réutilisables, sans widgets par
 * blocs déjà construits avec des blocs qui seraient exclus) : l'allowlist ci-dessous peut donc être
 * stricte sans filet de compatibilité supplémentaire.
 *
 * PORTÉE ASSUMÉE DE #2 (masquage des Étiquettes) — signalée avant tout sur-développement, comme
 * demandé : `post_tag` est une taxonomie UNIQUE, partagée par tout le site et attachée uniquement à
 * `post` — WordPress ne permet aucune restriction "par rôle" ou "par module" sur une taxonomie déjà
 * enregistrée sans mécanisme supplémentaire (capacités personnalisées, filtre par écran...). Le
 * masquage ci-dessous s'applique donc à TOUTE édition de `post` dès que GWS Equestrian est actif —
 * exactement le périmètre voulu ("masquer... pour les utilisateurs GWS Equestrian", le module
 * n'étant chargé que lorsque GWS Equestrian est activé), mais IL N'EXISTE PAS de bascule plus fine
 * (ex. par utilisateur) sans un développement plus lourd, volontairement non engagé ici.
 *
 * Catégories (§3 de la demande) : AUCUN code dans ce fichier — la taxonomie `category` native
 * reste strictement inchangée (aucune catégorie créée automatiquement, celles déjà existantes chez
 * un client restent intactes), c'est un no-op assumé.
 * Champs d'édition (§2) : AUCUN code dans ce fichier — titre/contenu/image à la
 * une/date/statut/auteur/catégories restent les mécanismes natifs WordPress, aucun champ
 * métier supplémentaire n'est ajouté en V1.
 */

if (!defined('ABSPATH')) exit;

/**
 * §1 : vocabulaire "Actualités". Les propriétés non modifiées ici gardent leur valeur par défaut
 * WordPress déjà calculée par `get_post_type_labels()` (donc toujours en français si le site est
 * en français) — seules celles listées dans la demande, plus celles clairement visibles dans le
 * même parcours d'écran (résultat de publication, filtre de liste, médiathèque...), sont adaptées.
 */
function gwseq_actualites_post_labels($labels) {
  $labels->name = __('Actualités', 'gws-core');
  $labels->singular_name = __('Actualité', 'gws-core');
  $labels->menu_name = __('Actualités', 'gws-core');
  $labels->name_admin_bar = __('Actualité', 'gws-core');
  $labels->all_items = __('Toutes les actualités', 'gws-core');
  $labels->add_new = __('Ajouter une actualité', 'gws-core');
  $labels->add_new_item = __('Ajouter une actualité', 'gws-core');
  $labels->new_item = __('Nouvelle actualité', 'gws-core');
  $labels->edit_item = __('Modifier l\'actualité', 'gws-core');
  $labels->view_item = __('Voir l\'actualité', 'gws-core');
  $labels->view_items = __('Voir les actualités', 'gws-core');
  $labels->search_items = __('Rechercher une actualité', 'gws-core');
  $labels->not_found = __('Aucune actualité trouvée', 'gws-core');
  $labels->not_found_in_trash = __('Aucune actualité trouvée dans la corbeille', 'gws-core');
  $labels->archives = __('Archives des actualités', 'gws-core');
  $labels->insert_into_item = __('Insérer dans l\'actualité', 'gws-core');
  $labels->uploaded_to_this_item = __('Téléversé sur cette actualité', 'gws-core');
  $labels->filter_items_list = __('Filtrer la liste des actualités', 'gws-core');
  $labels->items_list_navigation = __('Navigation de la liste des actualités', 'gws-core');
  $labels->items_list = __('Liste des actualités', 'gws-core');
  $labels->item_published = __('Actualité publiée.', 'gws-core');
  $labels->item_published_privately = __('Actualité publiée en privé.', 'gws-core');
  $labels->item_reverted_to_draft = __('Actualité repassée en brouillon.', 'gws-core');
  $labels->item_scheduled = __('Actualité planifiée.', 'gws-core');
  $labels->item_updated = __('Actualité mise à jour.', 'gws-core');
  return $labels;
}
add_filter('post_type_labels_post', 'gwseq_actualites_post_labels');

/**
 * §4 : masque l'interface des Étiquettes (`post_tag`) — jamais un désenregistrement, jamais une
 * suppression de la taxonomie ni des relations déjà en base. `show_ui => false` retire la boîte
 * "Étiquettes" de l'éditeur (classique et par blocs, ce dernier lisant `visibility.show_ui` exposé
 * par l'API REST — `show_in_rest` reste volontairement inchangé), le sous-menu "Étiquettes" sous
 * Actualités, et le champ correspondant de Modification rapide. `show_admin_column => false`
 * retire en plus la colonne "Étiquettes" de la liste (gouvernée séparément par WordPress, jamais
 * liée à `show_ui`). Toute autre taxonomie (`category` comprise, et toutes celles des modules GWS
 * existants) traverse ce filtre inchangée.
 */
function gwseq_hide_post_tag_ui($args, $taxonomy) {
  if ($taxonomy !== 'post_tag') return $args;
  $args['show_ui'] = false;
  $args['show_admin_column'] = false;
  return $args;
}
add_filter('register_taxonomy_args', 'gwseq_hide_post_tag_ui', 10, 2);

/**
 * §5 : retire les fonctions commentaires/trackbacks de l'écran d'édition d'une Actualité — voir le
 * docblock de ce fichier pour l'effet natif induit sur `get_default_comment_status()`. Accroché à
 * `init` (priorité par défaut, donc APRÈS `create_initial_post_types()` en priorité 0) : `post`
 * doit déjà exister pour que `remove_post_type_support()` ait un effet réel.
 */
function gwseq_remove_actualites_comments_support() {
  remove_post_type_support('post', 'comments');
  remove_post_type_support('post', 'trackbacks');
}
add_action('init', 'gwseq_remove_actualites_comments_support');

/* -------------------------------------------------------------------------------------------
 * Cadrage de l'éditeur par blocs (bloc "Actualités : cadrer Gutenberg") : conserver Gutenberg
 * techniquement — la demande explique explicitement pourquoi ce n'est PAS un retour à l'éditeur
 * classique comme pour Prestation/Cheval (`gwseq_disable_block_editor_for_*()`) — mais transformer
 * l'édition en expérience éditoriale simple et cadrée, sans outils de mise en page avancée
 * susceptibles de casser la cohérence graphique du site.
 * ----------------------------------------------------------------------------------------- */

/**
 * Allowlist volontairement restreinte (voir l'audit dans le docblock de ce fichier) : exactement
 * la liste proposée dans la demande (§A), sous leurs noms de bloc CORE réels — `core/list-item`
 * est un bloc interne obligatoire du bloc Liste depuis son passage en v2 (chaque élément de liste
 * EST un bloc), jamais un choix éditorial supplémentaire exposé à l'utilisateur ; `core/buttons`
 * est de la même façon le conteneur obligatoire d'un bouton isolé. Aucun bloc de mise en page
 * avancée (colonnes, groupe, couverture, HTML personnalisé, code, widgets, éléments de
 * thème/site...) n'y figure — jamais une liste de blocs à EXCLURE (qui devrait être tenue à jour à
 * chaque nouveau bloc core ajouté par une future version de WordPress), toujours une liste de
 * blocs à INCLURE (sûre par défaut : un futur bloc core inconnu n'apparaît jamais tant qu'il n'a
 * pas été explicitement ajouté ici).
 */
function gwseq_actualites_allowed_blocks() {
  return array(
    'core/paragraph',
    'core/heading',
    'core/list',
    'core/list-item',
    'core/image',
    'core/gallery',
    'core/buttons',
    'core/button',
    'core/video',
    'core/embed',
  );
}

/**
 * Scopé UNIQUEMENT à l'écran d'édition d'une Actualité (`$context->post->post_type === 'post'`) —
 * absent de tout autre contexte (Pages, widgets par blocs, éditeur de site), qui reçoivent
 * `$allowed_block_types` INCHANGÉ, jamais recalculé : la palette complète de Gutenberg reste
 * disponible pour les Pages, exactement comme demandé.
 */
function gwseq_restrict_actualites_blocks($allowed_block_types, $context) {
  if (!isset($context->post) || !$context->post || $context->post->post_type !== 'post') {
    return $allowed_block_types;
  }
  return gwseq_actualites_allowed_blocks();
}
add_filter('allowed_block_types_all', 'gwseq_restrict_actualites_blocks', 10, 2);

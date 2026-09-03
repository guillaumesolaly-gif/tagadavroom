<?php
/**
 * Petits aménagements d'administration partagés par Prestation, Groupe tarifaire, Cheval et Membre
 * (module Équipe) — vocabulaire métier plutôt que jargon WordPress (voir §24 de la demande), sans
 * construire de mécanisme générique : des fonctions ciblées, appelées explicitement pour chaque
 * post type concerné.
 */

if (!defined('ABSPATH')) exit;

/**
 * Renomme la meta box native "Attributs de page" (id historique 'pageparentdiv', fournie par
 * WordPress dès qu'un post type supporte 'page-attributes') en "Ordre d'affichage" — le champ
 * réellement utile ici (Ordre) est conservé tel quel (menu_order natif, aucune meta custom) ;
 * seul le libellé change. Le rendu (page_attributes_meta_box) reste la fonction native de
 * WordPress : aucune duplication de logique de sauvegarde, menu_order est déjà géré par
 * wp_update_post() de façon native dès qu'un champ "order" est soumis dans le formulaire standard.
 */
function gwseq_rename_order_meta_box($post_type) {
  remove_meta_box('pageparentdiv', $post_type, 'side');
  add_meta_box('gwseq-ordre-' . $post_type, __('Ordre d’affichage', 'gws-core'), 'page_attributes_meta_box', $post_type, 'side', 'default');
}

add_action('add_meta_boxes_' . GWSEQ_CPT_PRESTATION, function () {
  gwseq_rename_order_meta_box(GWSEQ_CPT_PRESTATION);
});
add_action('add_meta_boxes_' . GWSEQ_CPT_GROUPE, function () {
  gwseq_rename_order_meta_box(GWSEQ_CPT_GROUPE);
});
add_action('add_meta_boxes_' . GWSEQ_CPT_CHEVAL, function () {
  gwseq_rename_order_meta_box(GWSEQ_CPT_CHEVAL);
});
add_action('add_meta_boxes_' . GWSEQ_CPT_MEMBRE, function () {
  gwseq_rename_order_meta_box(GWSEQ_CPT_MEMBRE);
});

/**
 * Écran d'administration des Prestations et des Groupes tarifaires triés par ordre d'affichage
 * par défaut (menu_order), plutôt que par date de publication (tri par défaut de WordPress) —
 * cohérent avec le fait que menu_order est précisément le mécanisme retenu pour représenter cet
 * ordre. Reste sans effet si l'utilisateur clique explicitement sur un autre tri dans la liste.
 */
function gwseq_admin_default_order_by_menu_order($query) {
  if (!is_admin() || !$query->is_main_query()) return;
  if (!in_array($query->get('post_type'), array(GWSEQ_CPT_PRESTATION, GWSEQ_CPT_GROUPE, GWSEQ_CPT_CHEVAL, GWSEQ_CPT_MEMBRE), true)) return;
  if (!$query->get('orderby')) {
    $query->set('orderby', 'menu_order title');
    $query->set('order', 'ASC');
  }
}
add_action('pre_get_posts', 'gwseq_admin_default_order_by_menu_order');

/**
 * Retire l'action de ligne "Modification rapide" (Quick Edit) des listes d'administration des
 * objets métier GWS Equestrian (micro-correction post-recette Équipe ; étendu à `post` lors de
 * l'adaptation Actualités — §6/§8 de cette demande : « si une fonction générique déjà existante
 * doit proprement prendre en charge post, la réutiliser plutôt que dupliquer un second filtre »).
 * Ces fiches sont suffisamment structurées (meta boxes dédiées, titre parfois auto-dérivé pour
 * Membre) pour que l'édition doive toujours passer par la fiche complète — jamais par le
 * formulaire minimal et générique de Quick Edit. CIBLÉ UNIQUEMENT sur ces post types via le filtre
 * natif `post_row_actions` (jamais une désactivation globale de Quick Edit dans WordPress, qui
 * reste pleinement disponible pour les Pages et tout autre post type hors périmètre GWS). La clé
 * 'inline hide-if-no-js' est l'identifiant natif WordPress de cette action précise (voir
 * `WP_Posts_List_Table::handle_row_actions()`) — seule cette entrée est retirée, aucune autre
 * action de ligne (Modifier, Corbeille, Voir/Aperçu...) n'est touchée.
 */
function gwseq_remove_quick_edit_row_action($actions, $post) {
  // 'post' (natif, présenté comme "Actualités" — voir includes/actualites.php) est ajouté ici
  // volontairement à côté des quatre post types métier GWS, jamais comme une nouvelle constante
  // GWSEQ_CPT_* : ce n'est pas un post type inventé par le module.
  if (in_array($post->post_type, array(GWSEQ_CPT_PRESTATION, GWSEQ_CPT_GROUPE, GWSEQ_CPT_CHEVAL, GWSEQ_CPT_MEMBRE, 'post'), true)) {
    unset($actions['inline hide-if-no-js']);
  }
  return $actions;
}
add_filter('post_row_actions', 'gwseq_remove_quick_edit_row_action', 10, 2);

<?php
/**
 * Présentation de l'écran d'édition d'une Prestation — Nom (post_title) et Description
 * (post_content) restent les seules sources de vérité, sans aucune meta dupliquée : ce fichier
 * ne fait qu'améliorer leur présentation pour qu'elles se lisent comme une fiche métier plutôt
 * que comme un écran WordPress générique (§3-7 du CR de recette de l'Étape 3).
 *
 * CAUSE RACINE du bug des modèles de prestations (§2 du même CR) : le sélecteur de modèle
 * (includes/presets.php) s'accroche au hook `edit_form_after_title`, qui appartient exclusivement
 * au gabarit d'édition CLASSIQUE de WordPress (wp-admin/edit-form-advanced.php). Le CPT
 * `gwseq_prestation` ayant `show_in_rest => true` depuis l'Étape 1, WordPress lui applique
 * l'éditeur par blocs par défaut, qui utilise un gabarit entièrement différent
 * (edit-form-blocks.php, interface React) où cette action n'est JAMAIS déclenchée — d'où
 * l'absence totale et silencieuse du bloc "Partir d'un modèle" en recette, malgré un code
 * fonctionnellement correct et des tests unitaires qui ne pouvaient pas détecter ce problème
 * (ils exerçaient la fonction de rendu directement, jamais le choix réel d'éditeur/gabarit).
 * Les meta boxes classiques (Groupe tarifaire, Tarification, toutes deux enregistrées via
 * add_meta_box()) fonctionnaient malgré tout car WordPress maintient une compatibilité
 * descendante pour cette API précise dans l'éditeur par blocs — mais pas pour les actions du
 * gabarit classique comme `edit_form_after_title`.
 *
 * CORRECTION RETENUE : désactiver l'éditeur par blocs pour ce seul post type
 * (`use_block_editor_for_post_type`, filtre natif WordPress documenté à cet effet), ce qui
 * restaure le gabarit classique et donc le déclenchement réel de `edit_form_after_title`. Choix
 * délibéré plutôt que d'ajouter un second mécanisme d'affichage (meta box dupliquant le
 * sélecteur) : la demande est explicite sur ce point (ne pas contourner sans comprendre la cause).
 * `show_in_rest` reste activé : ce filtre ne change que l'éditeur affiché dans l'administration,
 * jamais l'exposition REST (les deux réglages sont indépendants dans WordPress).
 *
 * Ce choix sert aussi directement le point UX de la demande (§6) : le besoin éditorial réel pour
 * une description de prestation (texte, paragraphes, gras, italique, listes, liens) ne nécessite
 * aucune construction de mise en page par blocs — que la demande souhaite explicitement éviter,
 * le layout public appartenant au thème/GWS, jamais à l'utilisateur. L'éditeur classique
 * (TinyMCE) reste un simple champ de texte enrichi contenu dans une boîte, sans possibilité
 * d'insérer des colonnes, des embeds ou des blocs de mise en page — le risque que la demande
 * signale est donc déjà écarté par ce seul changement d'éditeur, sans qu'il soit nécessaire de
 * restreindre davantage sa barre d'outils (une personnalisation plus fine du TinyMCE natif serait
 * une customisation fragile d'un composant WordPress qui fonctionne déjà correctement, pour un
 * bénéfice marginal — non retenue, conformément à la demande de ne pas remplacer inutilement les
 * composants natifs).
 */

if (!defined('ABSPATH')) exit;

function gwseq_disable_block_editor_for_prestation($use_block_editor, $post_type) {
  if ($post_type === GWSEQ_CPT_PRESTATION) return false;
  return $use_block_editor;
}
add_filter('use_block_editor_for_post_type', 'gwseq_disable_block_editor_for_prestation', 10, 2);

/**
 * "Nom de la prestation" plutôt que le texte générique "Ajouter un titre" — le champ reste
 * post_title, seul son espace réservé change pour ce post type.
 */
function gwseq_prestation_title_placeholder($title, $post) {
  if ($post && $post->post_type === GWSEQ_CPT_PRESTATION) {
    return __('Nom de la prestation', 'gws-core');
  }
  return $title;
}
add_filter('enter_title_here', 'gwseq_prestation_title_placeholder', 10, 2);

/**
 * Libellé "Description" affiché juste au-dessus de l'éditeur natif (post_content), pour qu'il se
 * lise comme un champ de fiche métier identifiable plutôt qu'un éditeur WordPress anonyme —
 * aucune donnée ajoutée, uniquement un intitulé au rendu. Priorité 20 (après le sélecteur de
 * modèle de includes/presets.php, priorité par défaut 10, sur le même hook) pour que l'ordre visuel
 * soit : titre -> "Partir d'un modèle" -> "Description" -> éditeur.
 */
function gwseq_render_prestation_description_label($post) {
  if (!$post || $post->post_type !== GWSEQ_CPT_PRESTATION) return;
  echo '<p style="margin:1.5em 0 0.5em;"><label for="content"><strong>' . esc_html__('Description', 'gws-core') . '</strong></label></p>';
}
add_action('edit_form_after_title', 'gwseq_render_prestation_description_label', 20);

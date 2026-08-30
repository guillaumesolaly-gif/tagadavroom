<?php
/**
 * Présentation de l'écran d'édition d'une fiche cheval — désactivation de l'éditeur par blocs
 * (§29 de la demande).
 *
 * ARBITRAGE SPÉCIFIQUE À CE POST TYPE (volontairement pas un copier-coller de la décision prise
 * pour Prestation dans includes/prestation-editor.php) :
 *
 * - Pour Prestation, l'éditeur par blocs avait été désactivé pour une raison précise et locale :
 *   le sélecteur de modèle s'accroche à un hook (`edit_form_after_title`) qui n'existe que dans le
 *   gabarit classique. Cette raison ne s'applique pas ici — la fiche cheval n'a pas de mécanisme
 *   de préremplissage par modèle à cette étape.
 * - La vraie raison, propre à Cheval : la fiche est actuellement 100% structurée (identité,
 *   catégories, commercialisation) et ne prend plus en charge le support 'editor' (voir
 *   includes/post-types.php — aucun contenu éditorial de type article n'est développé ici, voir
 *   §22 de la demande). Sans contenu à éditer, l'éditeur par blocs n'apporterait aucun bénéfice et
 *   afficherait une interface potentiellement déroutante pour un utilisateur non familier de
 *   WordPress (toile vide, inserteur de blocs sans usage réel) — l'écran classique, purement fait
 *   de meta boxes ordonnées (Identité, Commercialisation, Catégories, Ordre, Image à la une),
 *   correspond davantage à une fiche métier lisible (§28).
 * - `show_in_rest` reste activé pour le post type (inchangé) : ce filtre ne change que l'éditeur
 *   affiché en administration, jamais l'exposition REST — les deux réglages restent indépendants.
 */

if (!defined('ABSPATH')) exit;

function gwseq_disable_block_editor_for_cheval($use_block_editor, $post_type) {
  if ($post_type === GWSEQ_CPT_CHEVAL) return false;
  return $use_block_editor;
}
add_filter('use_block_editor_for_post_type', 'gwseq_disable_block_editor_for_cheval', 10, 2);

/**
 * "Nom du cheval" plutôt que le texte générique "Ajouter un titre" — le champ reste post_title,
 * seul son espace réservé change pour ce post type.
 */
function gwseq_cheval_title_placeholder($title, $post) {
  if ($post && $post->post_type === GWSEQ_CPT_CHEVAL) {
    return __('Nom du cheval', 'gws-core');
  }
  return $title;
}
add_filter('enter_title_here', 'gwseq_cheval_title_placeholder', 10, 2);

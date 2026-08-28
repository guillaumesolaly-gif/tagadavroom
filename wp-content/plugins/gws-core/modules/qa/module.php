<?php
/**
 * Module de développement/recette — JAMAIS à activer sur un site de production.
 *
 * Sert uniquement à vérifier, sur un WordPress vierge, que les briques génériques du starter
 * fonctionnent réellement avant de démarrer un projet client : formulaire de contact, boutons,
 * cartes, champs, modale, icônes, typographie, CPT + champs structurés, persistance du contenu
 * au changement de thème, et le flush automatique des permaliens quand ce module (qui déclare
 * un CPT) est activé ou retiré.
 *
 * Ne contient aucun code métier, aucun contenu propre à un secteur. Entièrement jetable : voir
 * modules/qa/README.md (ce dossier) pour la procédure de retrait — aucune dépendance, aucun
 * résidu si elle est suivie.
 *
 * Préfixe de ce module : gws_qa_.
 */

if (!defined('ABSPATH')) exit;

const GWS_QA_POST_TYPE = 'gws_qa_item';
const GWS_QA_PAGE_TEMPLATE = 'page-templates/qa.php';
const GWS_QA_PAGE_SLUG = 'qa-recette-starter';

function gws_qa_register_post_type() {
  register_post_type(GWS_QA_POST_TYPE, array(
    'labels' => array(
      'name' => 'QA — Éléments de test',
      'singular_name' => 'Élément QA',
      'add_new_item' => 'Ajouter un élément de test',
      'edit_item' => 'Modifier l’élément de test',
    ),
    'public' => true,
    'has_archive' => true,
    'show_in_rest' => true,
    'menu_icon' => 'dashicons-warning',
    'supports' => array('title', 'editor', 'thumbnail'),
    'rewrite' => array('slug' => 'qa-items'),
  ));
}
add_action('init', 'gws_qa_register_post_type');

function gws_qa_field_schema() {
  return array(
    '_gws_qa_note' => array('label' => 'Note de test', 'type' => 'text', 'show_in_rest' => true, 'description' => 'Champ texte simple — vérifie la persistance après enregistrement.'),
    '_gws_qa_description' => array('label' => 'Description', 'type' => 'textarea', 'show_in_rest' => true),
    '_gws_qa_featured' => array('label' => 'Mise en avant', 'type' => 'checkbox', 'show_in_rest' => true, 'description' => 'Vérifie la persistance d’un champ booléen.'),
  );
}

function gws_qa_register_fields() {
  gws_core_register_field_meta_box('gws-qa-fields', 'Champs de test', GWS_QA_POST_TYPE, gws_qa_field_schema(), 'gws_qa_save_fields');
}
add_action('init', 'gws_qa_register_fields');

/**
 * Page de démonstration du design system — créée une seule fois (insert-only), jamais réécrite
 * après coup. Contenu volontairement statique (balisage direct dans le gabarit côté thème) pour
 * les composants du design system, complété ici par de vrais blocs Gutenberg natifs pour
 * vérifier aussi leur rendu.
 */
function gws_qa_seed_demo_page() {
  if (get_page_by_path(GWS_QA_PAGE_SLUG)) return;
  wp_insert_post(array(
    'post_title' => 'QA — Recette du design system (à supprimer)',
    'post_name' => GWS_QA_PAGE_SLUG,
    'post_type' => 'page',
    'post_status' => 'publish',
    'post_content' => gws_qa_demo_page_content(),
    'meta_input' => array('_wp_page_template' => GWS_QA_PAGE_TEMPLATE),
  ));
}
add_action('init', 'gws_qa_seed_demo_page', 20);

function gws_qa_demo_page_content() {
  $blocks = array();
  $blocks[] = '<!-- wp:heading {"level":2} --><h2>Blocs Gutenberg natifs</h2><!-- /wp:heading -->';
  $blocks[] = '<!-- wp:paragraph --><p>Ce paragraphe et les blocs ci-dessous vérifient le rendu des composants d’édition standards avec les styles du thème.</p><!-- /wp:paragraph -->';
  $blocks[] = '<!-- wp:list --><ul><li>Premier élément de liste</li><li>Second élément de liste</li></ul><!-- /wp:list -->';
  $blocks[] = '<!-- wp:quote --><blockquote class="wp-block-quote"><p>Une citation de test.</p></blockquote><!-- /wp:quote -->';
  $blocks[] = '<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button">Bouton Gutenberg natif</a></div><!-- /wp:button --></div><!-- /wp:buttons -->';
  return implode("\n", $blocks);
}

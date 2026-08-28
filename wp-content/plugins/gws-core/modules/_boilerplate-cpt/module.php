<?php
/**
 * Squelette de module métier — CPT + taxonomie + champs structurés + relation simple.
 *
 * À DUPLIQUER, jamais à activer tel quel : ce dossier commence par un underscore pour ne
 * jamais être confondu avec un module réel, et n'est de toute façon jamais chargé tant qu'il
 * n'apparaît pas dans config/modules.php.
 *
 * Exemple concret guidant ce squelette : une fiche "Cheval" pour un site d'élevage — champs
 * structurés (robe, date de naissance) et une relation simple vers deux autres fiches du même
 * CPT (père, mère). Voir modules/_boilerplate-cpt/README.md pour la marche à suivre complète.
 *
 * Convention : tout ce fichier utilise le préfixe fictif "bp_" (boilerplate) — à remplacer
 * partout par le préfixe réel du nouveau module (ex. "elv_" pour un élevage), y compris dans
 * les noms de fonctions, de clés de meta et de post type.
 */

if (!defined('ABSPATH')) exit;

const BP_POST_TYPE = 'bp_item';
const BP_TAXONOMY = 'bp_item_category';

function bp_register_post_type() {
  register_post_type(BP_POST_TYPE, array(
    'labels' => array(
      'name' => 'Fiches',
      'singular_name' => 'Fiche',
      'add_new_item' => 'Ajouter une fiche',
      'edit_item' => 'Modifier la fiche',
    ),
    'public' => true,
    'has_archive' => true,
    'show_in_rest' => true,
    'menu_icon' => 'dashicons-media-default',
    'supports' => array('title', 'editor', 'thumbnail'),
    'rewrite' => array('slug' => 'fiches'),
  ));
}
add_action('init', 'bp_register_post_type');

function bp_register_taxonomy() {
  register_taxonomy(BP_TAXONOMY, BP_POST_TYPE, array(
    'labels' => array('name' => 'Catégories', 'singular_name' => 'Catégorie'),
    'public' => true,
    'show_in_rest' => true,
    'hierarchical' => true,
    'rewrite' => array('slug' => 'fiches-categorie'),
  ));
}
add_action('init', 'bp_register_taxonomy');

/**
 * Champs structurés : deux champs simples (via le générateur de gws-core) + deux relations
 * "père" / "mère" traitées séparément ci-dessous, car une relation (référence vers un autre
 * post) n'est pas un type de champ pris en charge par le générateur minimal — elle se traite
 * avec un <select> peuplé manuellement, ce qui reste suffisant pour une relation simple.
 */
function bp_field_schema() {
  return array(
    '_bp_short_description' => array('label' => 'Description courte', 'type' => 'textarea', 'show_in_rest' => true),
    '_bp_reference' => array('label' => 'Référence interne', 'type' => 'text', 'show_in_rest' => true),
  );
}

function bp_register_fields() {
  gws_core_register_field_meta_box('bp-item-fields', 'Informations', BP_POST_TYPE, bp_field_schema(), 'bp_save_item_fields');
}
add_action('init', 'bp_register_fields');

function bp_register_relation_meta() {
  register_post_meta(BP_POST_TYPE, '_bp_parent_a', array('show_in_rest' => true, 'single' => true, 'type' => 'integer'));
  register_post_meta(BP_POST_TYPE, '_bp_parent_b', array('show_in_rest' => true, 'single' => true, 'type' => 'integer'));
}
add_action('init', 'bp_register_relation_meta');

function bp_add_relation_meta_box() {
  add_meta_box('bp-item-relations', 'Relations', 'bp_render_relation_meta_box', BP_POST_TYPE, 'normal', 'default');
}
add_action('add_meta_boxes_' . BP_POST_TYPE, 'bp_add_relation_meta_box');

function bp_render_relation_meta_box($post) {
  wp_nonce_field('bp_save_item_relations', 'bp_save_item_relations_nonce');
  $others = get_posts(array('post_type' => BP_POST_TYPE, 'exclude' => array($post->ID), 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC'));
  foreach (array('_bp_parent_a' => 'Relation A (ex. père)', '_bp_parent_b' => 'Relation B (ex. mère)') as $key => $label) {
    $current = (int) get_post_meta($post->ID, $key, true);
    echo '<p><label><strong>' . esc_html($label) . '</strong></label><br><select class="widefat" name="' . esc_attr($key) . '"><option value="0">— Aucune —</option>';
    foreach ($others as $other) {
      echo '<option value="' . esc_attr($other->ID) . '"' . selected($current, $other->ID, false) . '>' . esc_html(get_the_title($other)) . '</option>';
    }
    echo '</select></p>';
  }
}

function bp_save_item_relations($post_id) {
  if (!isset($_POST['bp_save_item_relations_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bp_save_item_relations_nonce'])), 'bp_save_item_relations')) return;
  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
  if (!current_user_can('edit_post', $post_id)) return;
  foreach (array('_bp_parent_a', '_bp_parent_b') as $key) {
    if (isset($_POST[$key])) update_post_meta($post_id, $key, absint($_POST[$key]));
  }
}
add_action('save_post_' . BP_POST_TYPE, 'bp_save_item_relations');

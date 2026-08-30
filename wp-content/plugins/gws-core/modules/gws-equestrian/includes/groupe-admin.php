<?php
/**
 * Groupe tarifaire — rendre le CPT créé à l'Étape 1 réellement utilisable (Étape 3, §4).
 *
 * Un groupe tarifaire n'a besoin d'aucune meta custom : Nom = post_title (natif), Ordre =
 * menu_order (natif, via 'page-attributes'), Description courte = post_excerpt (natif, via
 * 'excerpt') — les trois champs natifs sont simplement renommés en vocabulaire métier. Aucune
 * fonction de sauvegarde à écrire : WordPress gère déjà nativement la persistance de ces trois
 * champs dès qu'un post type les supporte.
 */

if (!defined('ABSPATH')) exit;

/**
 * Renomme la meta box native "Extrait" en "Description courte" — même champ (post_excerpt), même
 * sauvegarde native, seul le libellé change. Jugée utile ici (contrairement à une meta
 * supplémentaire) car WordPress fournit déjà exactement ce besoin sans code à écrire.
 */
function gwseq_rename_groupe_excerpt_meta_box() {
  remove_meta_box('postexcerpt', GWSEQ_CPT_GROUPE, 'normal');
  add_meta_box('gwseq-groupe-description', __('Description courte', 'gws-core'), 'post_excerpt_meta_box', GWSEQ_CPT_GROUPE, 'normal', 'default');
}
add_action('add_meta_boxes_' . GWSEQ_CPT_GROUPE, 'gwseq_rename_groupe_excerpt_meta_box');

/**
 * Colonnes de la liste des groupes tarifaires : nombre de prestations qui y sont rattachées (aide
 * à repérer un groupe vide ou à vérifier rapidement son contenu sans ouvrir chaque prestation) et
 * ordre d'affichage.
 */
function gwseq_count_prestations_in_groupe($groupe_id) {
  $query = new WP_Query(array(
    'post_type' => GWSEQ_CPT_PRESTATION,
    'post_status' => array('publish', 'draft', 'pending', 'private'),
    'meta_key' => '_gwseq_prestation_groupe_id',
    'meta_value' => (string) (int) $groupe_id,
    'posts_per_page' => -1,
    'fields' => 'ids',
    'no_found_rows' => true,
  ));
  return count($query->posts);
}

function gwseq_groupe_admin_columns($columns) {
  $columns['gwseq_prestations_count'] = __('Prestations', 'gws-core');
  $columns['gwseq_ordre'] = __('Ordre', 'gws-core');
  return $columns;
}
add_filter('manage_' . GWSEQ_CPT_GROUPE . '_posts_columns', 'gwseq_groupe_admin_columns');

function gwseq_groupe_admin_column_content($column, $post_id) {
  if ($column === 'gwseq_prestations_count') {
    echo (int) gwseq_count_prestations_in_groupe($post_id);
  } elseif ($column === 'gwseq_ordre') {
    echo (int) get_post_field('menu_order', $post_id);
  }
}
add_action('manage_' . GWSEQ_CPT_GROUPE . '_posts_custom_column', 'gwseq_groupe_admin_column_content', 10, 2);

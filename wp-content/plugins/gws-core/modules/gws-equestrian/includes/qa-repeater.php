<?php
/**
 * Démonstration du composant répétable générique (repeater-field.php), en environnement
 * local/développement UNIQUEMENT — jamais activée en production, jamais mêlée aux écrans métier
 * réels (Prestations, Groupes tarifaires, Chevaux). Jeu de données neutre (Libellé/Valeur/Année),
 * aucune donnée métier réelle (pas d'ISO/ICC/IDR/BSO/BCC/BDR ici — voir §14 de la demande).
 *
 * Pourquoi un CPT dédié à GWS Equestrian plutôt que de réutiliser le module `qa` déjà fourni par
 * gws-core : ce module est explicitement documenté comme générique et sans contenu propre à un
 * secteur ("Aucun code métier, aucun contenu propre à un secteur" —
 * wp-content/plugins/gws-core/modules/qa/README.md). Y ajouter une démonstration propre à GWS
 * Equestrian romprait cette règle et créerait une dépendance artificielle entre deux modules
 * métier indépendants (gws-equestrian devrait alors exiger que le module qa soit actif pour
 * tester son propre composant). La solution retenue ici réutilise le même principe de garde
 * d'environnement (wp_get_environment_type()) que includes/admin/qa-tool-page.php, mais
 * appliquée localement, dans son propre module : aucune modification de gws-core ou gws-starter
 * n'a été nécessaire pour cette étape.
 *
 * Ce fichier est toujours chargé par module.php (coût nul si l'environnement n'est pas
 * local/development : chaque fonction se neutralise elle-même en tout début d'exécution).
 */

if (!defined('ABSPATH')) exit;

const GWSEQ_QA_REPEATER_POST_TYPE = 'gwseq_qa_repeater';
const GWSEQ_QA_REPEATER_META_KEY = '_gwseq_qa_repeater_demo';

function gwseq_qa_repeater_enabled() {
  return in_array(wp_get_environment_type(), array('local', 'development'), true);
}

function gwseq_qa_repeater_field_schema() {
  return array(
    'libelle' => array('label' => 'Libellé', 'type' => 'text'),
    'valeur' => array('label' => 'Valeur', 'type' => 'number'),
    'annee' => array('label' => 'Année', 'type' => 'integer'),
  );
}

function gwseq_register_qa_repeater_post_type() {
  if (!gwseq_qa_repeater_enabled()) return;
  register_post_type(GWSEQ_QA_REPEATER_POST_TYPE, array(
    'labels' => array(
      'name' => 'QA — Répétable (Equestrian)',
      'singular_name' => 'Essai répétable',
      'add_new_item' => 'Ajouter un essai',
      'edit_item' => 'Modifier l’essai',
      'not_found' => 'Aucun essai trouvé',
    ),
    'public' => false,
    'publicly_queryable' => false,
    'exclude_from_search' => true,
    'show_in_nav_menus' => false,
    'show_ui' => true,
    'show_in_menu' => true,
    'show_in_rest' => false,
    'menu_icon' => 'dashicons-admin-tools',
    'supports' => array('title'),
    'rewrite' => false,
  ));
}
add_action('init', 'gwseq_register_qa_repeater_post_type');

function gwseq_register_qa_repeater_field() {
  if (!gwseq_qa_repeater_enabled()) return;
  gwseq_register_repeater_field(
    GWSEQ_QA_REPEATER_POST_TYPE,
    GWSEQ_QA_REPEATER_META_KEY,
    gwseq_qa_repeater_field_schema(),
    'Composant répétable — démonstration (Libellé / Valeur / Année)',
    'gwseq_save_qa_repeater_demo'
  );
}
add_action('init', 'gwseq_register_qa_repeater_field');

function gwseq_enqueue_qa_repeater_assets($hook) {
  if (!gwseq_qa_repeater_enabled()) return;
  if (!in_array($hook, array('post.php', 'post-new.php'), true)) return;
  $screen = function_exists('get_current_screen') ? get_current_screen() : null;
  if (!$screen || $screen->post_type !== GWSEQ_QA_REPEATER_POST_TYPE) return;

  wp_enqueue_style('gwseq-repeater-field', GWSEQ_MODULE_URL . 'assets/repeater-field.css', array(), GWSEQ_MODULE_VERSION);
  wp_enqueue_script('gwseq-repeater-field', GWSEQ_MODULE_URL . 'assets/repeater-field.js', array(), GWSEQ_MODULE_VERSION, true);
}
add_action('admin_enqueue_scripts', 'gwseq_enqueue_qa_repeater_assets');

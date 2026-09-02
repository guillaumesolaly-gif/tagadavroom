<?php
/**
 * Cheval — navigation par onglets dans l'admin (ajustement UX post-recette de l'Étape 6, §2-6 de
 * la demande).
 *
 * PRINCIPE CENTRAL, RÉPÉTÉ VOLONTAIREMENT DANS CHAQUE FICHIER TOUCHÉ PAR CET AJUSTEMENT : les
 * onglets sont UNIQUEMENT une couche de PRÉSENTATION. Ce fichier ne modifie AUCUNE meta, ne crée
 * AUCUN second formulaire, ne modifie AUCUNE règle métier, ne modifie AUCUN mécanisme de
 * sauvegarde WordPress, ne charge AUCUNE donnée par AJAX, et ne rend AUCUNE donnée absente du DOM
 * — toutes les meta boxes de la fiche Cheval restent exactement celles déjà enregistrées par
 * cheval-fields.php/cheval-pedigree.php/cheval-indices.php/cheval-media.php/cheval-editorial.php,
 * dans le MÊME formulaire `<form id="post">` natif de WordPress. Ce fichier se contente de :
 * 1. déclarer, en PHP, le regroupement logique des meta boxes existantes par onglet (SEULE source
 *    de vérité, jamais une connaissance dupliquée dans le JavaScript) ;
 * 2. transmettre cette configuration au script via wp_localize_script() (mécanisme natif
 *    WordPress, jamais des données codées en dur côté JavaScript, jamais un endpoint AJAX
 *    inventé) ;
 * 3. charger le script/la feuille de style correspondants, uniquement sur l'écran d'édition d'une
 *    fiche cheval.
 *
 * Le script (assets/cheval-tabs-admin.js) construit lui-même la barre d'onglets au chargement de
 * la page et se contente de masquer/afficher (display none/block) les `<div class="postbox">`
 * déjà présents dans le DOM, SANS JAMAIS LES DÉPLACER : cela préserve intégralement le
 * comportement natif de WordPress sur ces boîtes (repliage au clic sur le titre, éventuel
 * glisser-déposer) puisque leur position réelle dans l'arbre DOM ne change jamais. SANS
 * JAVASCRIPT, aucune boîte n'est jamais masquée : la fiche reste utilisable exactement comme
 * avant cet ajustement, empilée verticalement (§3 : « sans JavaScript, la fiche doit rester
 * utilisable »).
 *
 * PLACEMENT DE LA PHOTO PRINCIPALE (image à la une native, correctif post-recette) : EXCEPTION
 * ASSUMÉE à la règle « jamais déplacer une meta box dans le DOM » ci-dessus — un premier essai en
 * masquant/affichant `postimagediv` EN PLACE (sa colonne latérale native) laissait, dans l'onglet
 * "Médias", un simple texte renvoyant vers une boîte physiquement ailleurs à l'écran, jugé non
 * satisfaisant en recette. La boîte native est donc RÉELLEMENT réinsérée (assets/cheval-tabs-admin.js,
 * un déplacement DOM natif via `appendChild` — le même mécanisme que le glisser-déposer natif de
 * WordPress entre colonnes, jamais une destruction/recréation, donc aucun gestionnaire d'événement
 * perdu) dans un emplacement dédié À L'INTÉRIEUR de la boîte Médias
 * (`#gwseq-cheval-media-photo-principale-slot`, voir `cheval-media.php`) : elle n'apparaît alors
 * plus jamais simultanément dans la colonne latérale, et hérite automatiquement de la visibilité de
 * la boîte Médias (elle en devient un DESCENDANT — aucune logique de visibilité séparée). Restaurée
 * à sa position native si le système d'onglets se désactive (filet de sécurité n°2). Aucun second
 * champ, aucun second attachment ID, aucune synchronisation : c'est le MÊME nœud DOM, donc la
 * Featured Image de WordPress reste l'unique source de vérité, lue/modifiée par sa propre interface
 * native (`wp.media()`), jamais dupliquée. Le Global Horse ID (dev-only) et la boîte "Ordre
 * d'affichage" restent dans la colonne latérale, en dehors du système d'onglets.
 *
 * BOUTON D'ENREGISTREMENT RAPIDE (§4) : le script ajoute, dans la barre d'onglets, un bouton qui
 * ne fait que déclencher un clic PROGRAMMATIQUE sur le vrai bouton de soumission natif de
 * WordPress (`#publish`, présent dans la boîte "Publier" de la colonne latérale) — jamais un
 * second mécanisme de sauvegarde, jamais un appel direct à `form.submit()` qui contournerait les
 * éventuels gestionnaires JavaScript propres à WordPress attachés à ce bouton (verrouillage
 * d'édition, confirmation de fermeture de page...). Une seule soumission possible à la fois, celle
 * du formulaire natif — jamais deux sauvegardes concurrentes.
 */

if (!defined('ABSPATH')) exit;

/**
 * Regroupement onglet => identifiants de meta box (§2 de la demande) — SEULE source de vérité
 * pour cette organisation admin. Un identifiant qui ne correspond à aucune boîte réellement
 * présente sur l'écran (ex. "gwseq-cheval-pedigree-preview", enregistrée uniquement en
 * local/développement) est silencieusement ignoré par le script — jamais une erreur.
 */
function gwseq_cheval_admin_tabs_config() {
  return array(
    array(
      'id' => 'identite',
      'label' => __('Identité', 'gws-core'),
      'boxes' => array('gwseq-cheval-identite'),
    ),
    array(
      'id' => 'commercial',
      'label' => __('Commercial', 'gws-core'),
      'boxes' => array('gwseq-cheval-commercialisation'),
    ),
    array(
      'id' => 'labels',
      'label' => __('Labels', 'gws-core'),
      'boxes' => array('gwseq-cheval-labels'),
    ),
    array(
      'id' => 'pedigree',
      'label' => __('Pedigree', 'gws-core'),
      'boxes' => array('gwseq-cheval-pedigree', 'gwseq-cheval-production', 'gwseq-cheval-pedigree-preview'),
    ),
    array(
      'id' => 'indices',
      'label' => __('Indices', 'gws-core'),
      'boxes' => array('gwseq-cheval-indices'),
    ),
    array(
      'id' => 'medias',
      'label' => __('Médias', 'gws-core'),
      // La boîte NATIVE "postimagediv" (Photo principale) n'apparaît PAS ici : elle n'est plus
      // pilotée par le mécanisme générique de visibilité par onglet (masquer/afficher EN PLACE) —
      // elle est RÉELLEMENT déplacée dans le DOM par assets/cheval-tabs-admin.js jusqu'à un
      // emplacement dédié à l'intérieur même de cette boîte (voir le correctif en tête de fichier
      // et cheval-media.php), et hérite donc automatiquement de la visibilité de "gwseq-cheval-media"
      // en devenant simplement son descendant — aucune entrée séparée n'est nécessaire.
      'boxes' => array('gwseq-cheval-media'),
    ),
    array(
      'id' => 'presentation',
      'label' => __('Présentation', 'gws-core'),
      'boxes' => array('gwseq-cheval-presentation', 'gwseq-cheval-infos-complementaires'),
    ),
  );
}

/**
 * CORRECTIF RÉGRESSION (onglet Identité vide, deuxième round) — §5 de la demande : « éviter deux
 * vérités indépendantes entre la configuration onglets et le DOM WordPress ». Marque chaque meta
 * box gérée par les onglets d'une classe CSS `gwseq-tab-{id}` déclarant EXPLICITEMENT, dans le
 * HTML réellement rendu, son appartenance à un onglet — dérivée de la MÊME configuration
 * (`gwseq_cheval_admin_tabs_config()`) que celle transmise au script, jamais une seconde source.
 * Utilise le filtre natif WordPress `postbox_classes_{page}_{id}`, appliqué par `do_meta_boxes()`
 * pour CHAQUE boîte affichée — y compris une boîte native non enregistrée par ce plugin comme
 * `postimagediv`. Purement un marqueur défensif/diagnostique : le script continue de résoudre les
 * boîtes par leur identifiant réel (SEUL mécanisme nécessaire à son fonctionnement) ; cette classe
 * ne fait que lui permettre de VÉRIFIER que ce qu'il a trouvé par identifiant correspond bien à ce
 * que PHP attendait avant de construire quoi que ce soit (voir la vérification de cohérence dans
 * assets/cheval-tabs-admin.js) — si elle est absente, le script n'engage aucune construction
 * d'onglet plutôt que de risquer de masquer une boîte mal identifiée.
 */
function gwseq_register_cheval_admin_tab_postbox_classes() {
  foreach (gwseq_cheval_admin_tabs_config() as $tab) {
    foreach ($tab['boxes'] as $box_id) {
      add_filter('postbox_classes_' . GWSEQ_CPT_CHEVAL . '_' . $box_id, function ($classes) use ($tab) {
        $classes[] = 'gwseq-tab-' . $tab['id'];
        return $classes;
      });
    }
  }
}
gwseq_register_cheval_admin_tab_postbox_classes();

/**
 * Assets : uniquement sur l'écran d'édition d'une fiche cheval — jamais chargés globalement dans
 * l'administration. wp_localize_script() (mécanisme natif) transmet au script la configuration
 * PHP ci-dessus ainsi que le libellé traduit de repli du bouton d'enregistrement rapide (utilisé
 * seulement si le script ne parvient pas à lire le texte du vrai bouton natif — voir
 * assets/cheval-tabs-admin.js), l'environnement (pour le message de secours dev-only) et le texte
 * de ce message de secours (§4 : « signaler le problème en environnement local/développement »
 * si le filet de sécurité n°2 du script se déclenche).
 */
function gwseq_enqueue_cheval_admin_tabs_assets($hook) {
  if (!in_array($hook, array('post.php', 'post-new.php'), true)) return;
  $screen = function_exists('get_current_screen') ? get_current_screen() : null;
  if (!$screen || $screen->post_type !== GWSEQ_CPT_CHEVAL) return;

  wp_enqueue_style('gwseq-cheval-tabs', GWSEQ_MODULE_URL . 'assets/cheval-tabs.css', array(), GWSEQ_MODULE_VERSION);
  wp_enqueue_script('gwseq-cheval-tabs-admin', GWSEQ_MODULE_URL . 'assets/cheval-tabs-admin.js', array(), GWSEQ_MODULE_VERSION, true);
  wp_localize_script('gwseq-cheval-tabs-admin', 'gwseqChevalTabs', array(
    'tabs' => gwseq_cheval_admin_tabs_config(),
    'saveLabelFallback' => __('Enregistrer', 'gws-core'),
    'tablistLabel' => __('Sections de la fiche cheval', 'gws-core'),
    'isDevEnvironment' => function_exists('wp_get_environment_type') && in_array(wp_get_environment_type(), array('local', 'development'), true),
    'fallbackNotice' => __('Navigation par onglets désactivée automatiquement : un contenu restait invisible après vérification. Toutes les données sont affichées normalement ci-dessous.', 'gws-core'),
  ));
}
add_action('admin_enqueue_scripts', 'gwseq_enqueue_cheval_admin_tabs_assets');

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
 * PLACEMENT DE LA PHOTO PRINCIPALE (image à la une native, correctif régression post-recette) :
 * regroupée avec Galerie/Vidéos sous l'onglet "Médias" — EXACTEMENT comme Production/aperçu
 * pedigree sont déjà regroupés sous "Pedigree" (voir cheval-pedigree.php) : la boîte native
 * `postimagediv` n'est JAMAIS déplacée dans le DOM ni ré-enregistrée par ce plugin (elle reste
 * exactement celle de WordPress, dans sa colonne native), seule sa VISIBILITÉ est désormais gérée
 * par le même mécanisme d'onglets que les autres boîtes — masquée sous tout autre onglet, visible
 * uniquement sous "Médias", aux côtés de la boîte Galerie/Vidéos du plugin. Aucun second champ,
 * aucun second attachment ID, aucune synchronisation : la Featured Image de WordPress reste
 * l'unique source de vérité, lue/modifiée par sa propre interface native (`wp.media()` intégré à
 * WordPress), jamais dupliquée. Le Global Horse ID (dev-only) et la boîte "Ordre d'affichage"
 * restent dans la colonne latérale, en dehors du système d'onglets — ce sont des utilitaires
 * annexes, jamais le cœur du contenu que les onglets cherchent à désencombrer.
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
      // 'postimagediv' : boîte NATIVE WordPress de l'image à la une (Photo principale), jamais
      // enregistrée par ce plugin — voir le correctif ci-dessus pour le détail de ce regroupement
      // (source de vérité unique : Featured Image de WordPress, jamais un second champ).
      'boxes' => array('postimagediv', 'gwseq-cheval-media'),
    ),
    array(
      'id' => 'presentation',
      'label' => __('Présentation', 'gws-core'),
      'boxes' => array('gwseq-cheval-presentation', 'gwseq-cheval-infos-complementaires'),
    ),
  );
}

/**
 * Assets : uniquement sur l'écran d'édition d'une fiche cheval — jamais chargés globalement dans
 * l'administration. wp_localize_script() (mécanisme natif) transmet au script la configuration
 * PHP ci-dessus ainsi que le libellé traduit de repli du bouton d'enregistrement rapide (utilisé
 * seulement si le script ne parvient pas à lire le texte du vrai bouton natif — voir
 * assets/cheval-tabs-admin.js).
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
  ));
}
add_action('admin_enqueue_scripts', 'gwseq_enqueue_cheval_admin_tabs_assets');

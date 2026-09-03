<?php
/**
 * Rendu front des Mises en avant (§L) — contrairement à Actualités, un vrai rendu front est
 * nécessaire ici (impossible de valider autrement le fonctionnement de Pop-in/Sticky bar). Une
 * campagne n'est JAMAIS rendue "au cas où" : l'éligibilité (statut, période, ciblage) est évaluée
 * AVANT tout enqueue de script/style, jamais un chargement systématique sur toutes les pages pour
 * ce qui reste deux petits composants.
 *
 * Résolution de la CONCURRENCE (§I) : au plus une Pop-in ET au plus une Sticky bar par page
 * (jamais les deux Pop-in en même temps, une Pop-in et une Sticky bar peuvent en revanche
 * cohabiter). En cas de plusieurs campagnes du même type éligibles, la plus petite valeur de
 * `menu_order` gagne — réutilisation du système d'ordre déjà existant, jamais un second champ
 * "Priorité".
 *
 * Utilise `gwseq_render_popin_markup()`/`gwseq_render_sticky_bar_markup()` — LES MÊMES fonctions
 * que le preview BO (voir includes/popin-fields.php / includes/sticky-bar-fields.php) : aucun
 * rendu dupliqué, aucune divergence possible entre preview et front.
 */

if (!defined('ABSPATH')) exit;

/**
 * ID du contenu actuellement affiché, pour le ciblage (§H) — 0 si aucun contenu identifiable
 * (résultats de recherche, 404...). Fonction pure une fois `$queried_object_id`/`$is_front_page`
 * fournis (injectables pour les tests), qui ne dépend sinon que du contexte de requête WordPress
 * réel (`get_queried_object_id()`/`is_front_page()`).
 */
function gwseq_campagnes_current_context() {
  return array(
    'queried_post_id' => (int) get_queried_object_id(),
    'is_front_page' => is_front_page(),
  );
}

/**
 * Sélectionne, parmi une liste de campagnes (déjà limitées au statut "publish" WordPress + statut
 * "active" GWS, triées par menu_order croissant), la première réellement éligible pour le contexte
 * de page donné : fenêtre de dates ET ciblage. Fonction pure, réutilisée pour Pop-in et Sticky bar
 * — c'est ici, et seulement ici, que la "priorité" (l'ordre de la liste déjà trié) se traduit en
 * décision "laquelle gagne" : la première qui correspond, jamais un second calcul de priorité.
 */
function gwseq_campagne_choisir_eligible($candidats, $get_diffusion, $contexte, $now_ts = null) {
  foreach ($candidats as $post_id) {
    $diffusion = call_user_func($get_diffusion, $post_id);
    if (!gwseq_campagne_est_dans_la_fenetre($diffusion['debut_ts'], $diffusion['fin_ts'], $now_ts)) continue;
    $ciblage = array('mode' => $diffusion['ciblage_mode'], 'cibles' => $diffusion['ciblage_cibles']);
    if (!gwseq_campagne_page_est_ciblee($ciblage, $contexte['queried_post_id'], $contexte['is_front_page'])) continue;
    return (int) $post_id;
  }
  return 0;
}

function gwseq_query_active_campagne_ids($post_type, $statut_meta_key) {
  return get_posts(array(
    'post_type' => $post_type,
    'post_status' => 'publish',
    'numberposts' => -1,
    'orderby' => 'menu_order',
    'order' => 'ASC',
    'fields' => 'ids',
    'meta_key' => $statut_meta_key,
    'meta_value' => 'active',
  ));
}

/**
 * Mémoïsées (`static`) : au plus une requête par type et par requête HTTP, que l'enqueue de
 * assets et le rendu wp_footer aient tous deux besoin du résultat.
 */
function gwseq_get_eligible_popin_id() {
  static $resolved = null;
  if ($resolved !== null) return $resolved;
  $candidats = gwseq_query_active_campagne_ids(GWSEQ_CPT_POPIN, '_gwseq_popin_statut');
  $resolved = gwseq_campagne_choisir_eligible($candidats, 'gwseq_get_popin_diffusion', gwseq_campagnes_current_context());
  return $resolved;
}

function gwseq_get_eligible_sticky_bar_id() {
  static $resolved = null;
  if ($resolved !== null) return $resolved;
  $candidats = gwseq_query_active_campagne_ids(GWSEQ_CPT_STICKY_BAR, '_gwseq_sticky_bar_statut');
  $resolved = gwseq_campagne_choisir_eligible($candidats, 'gwseq_get_sticky_bar_diffusion', gwseq_campagnes_current_context());
  return $resolved;
}

function gwseq_should_load_campagnes_front_assets() {
  if (is_admin() || wp_doing_ajax() || (function_exists('wp_doing_cron') && wp_doing_cron()) || is_feed()) return false;
  return gwseq_get_eligible_popin_id() || gwseq_get_eligible_sticky_bar_id();
}

function gwseq_enqueue_campagnes_front_assets() {
  if (!gwseq_should_load_campagnes_front_assets()) return;
  wp_enqueue_style('gwseq-campagnes-front', GWSEQ_MODULE_URL . 'assets/campagnes-front.css', array(), GWSEQ_MODULE_VERSION);
  wp_enqueue_script('gwseq-campagnes-front', GWSEQ_MODULE_URL . 'assets/campagnes-front.js', array(), GWSEQ_MODULE_VERSION, true);
}
add_action('wp_enqueue_scripts', 'gwseq_enqueue_campagnes_front_assets');

function gwseq_render_campagnes_wp_footer() {
  if (is_admin() || wp_doing_ajax() || is_feed()) return;

  $popin_id = gwseq_get_eligible_popin_id();
  if ($popin_id) {
    $declenchement = gwseq_get_popin_declenchement($popin_id);
    echo gwseq_render_popin_markup(gwseq_get_popin_config($popin_id), array(
      'data-gwseq-popin-id' => $popin_id,
      'data-gwseq-declenchement' => $declenchement['mode'],
      'data-gwseq-delai' => $declenchement['delai_secondes'],
      'data-gwseq-scroll' => $declenchement['scroll_pourcentage'],
      'data-gwseq-frequence' => $declenchement['frequence_mode'],
      'data-gwseq-jours' => $declenchement['frequence_jours'],
    ));
  }

  $sticky_id = gwseq_get_eligible_sticky_bar_id();
  if ($sticky_id) {
    echo gwseq_render_sticky_bar_markup(gwseq_get_sticky_bar_config($sticky_id), array(
      'data-gwseq-sticky-id' => $sticky_id,
    ));
  }
}
add_action('wp_footer', 'gwseq_render_campagnes_wp_footer');

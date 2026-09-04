<?php
/**
 * Diagnostic instrumenté de performance — écran d'édition d'une fiche Cheval (anomalie de recette
 * Lot 2B : ~38 secondes constatées pour ouvrir une fiche, sur un site n'en comptant qu'une grosse
 * dizaine). Ce fichier NE CORRIGE RIEN et NE MODIFIE AUCUN COMPORTEMENT MÉTIER : il ajoute
 * uniquement des CHRONOMÈTRES en lecture seule autour de ce qui s'exécute déjà, pour mesurer où le
 * temps est réellement consommé avant d'écrire le moindre correctif (demande explicite : "ne pas
 * commencer par modifier ou refactorer le code sur la base d'une hypothèse").
 *
 * MÊME GARDE que le module QA (includes/admin/qa-tool-page.php de gws-core, seul précédent de ce
 * type dans le projet) : entièrement inerte hors environnement local/développement
 * (`wp_get_environment_type()`) — aucun coût, aucun hook enregistré, aucune sortie, y compris en
 * production. Peut être retiré entièrement sans casser quoi que ce soit d'autre : ce fichier ne
 * définit que ses propres fonctions, n'en appelle aucune du reste du module, et ne modifie jamais
 * les callbacks originaux qu'il enveloppe (voir gwseq_perf_diag_wrap_meta_boxes() plus bas — même
 * arguments, même valeur de retour, la mesure se fait strictement AUTOUR de l'appel).
 *
 * CE QUI EST MESURÉ (correspond exactement à la demande de diagnostic) :
 *   1. Le temps RÉELLEMENT passé dans le rendu de CHAQUE boîte méta de la fiche Cheval (Identité,
 *      Commercialisation, Médias, Pedigree, Indices, Labels, Éditorial, Partage, État de diffusion,
 *      Production, boîtes de développement) — la ventilation demandée entre les différents
 *      traitements GWS, sans avoir à deviner lequel est en cause.
 *   2. Les étapes du cycle de chargement WordPress (plugins_loaded/init/admin_init/current_screen/
 *      admin_enqueue_scripts/admin_footer) — pour détecter un coût qui ne serait PAS localisé dans
 *      une boîte précise (ex. chargement des modules, un hook global mal scopé).
 *   3. Une comparaison explicite entre le temps écoulé depuis le début RÉEL de la requête HTTP
 *      (`$_SERVER['REQUEST_TIME_FLOAT']`, mesuré par PHP dès la réception de la requête — antérieur
 *      à toute exécution de gws-core) et le temps effectivement attribuable aux boîtes/étapes GWS
 *      ci-dessus : permet de déterminer si le temps perdu se trouve DANS ce que ce diagnostic peut
 *      voir, ou AILLEURS (cœur WordPress, un autre plugin, le thème, l'environnement Local
 *      lui-même) — une hypothèse que ce fichier ne tranche jamais à la place de la mesure réelle.
 *
 * NE PEUT PAS REMPLACER une mesure sur le site réel de recette (Local) : cette instrumentation
 * s'exécute dans le processus PHP réel de ce site, avec ses vraies données et sa vraie
 * configuration — aucun environnement WordPress+MySQL n'étant disponible pour reproduire cela
 * ailleurs, ce fichier est le seul moyen d'obtenir des chiffres réels plutôt qu'une hypothèse.
 */

if (!defined('ABSPATH')) exit;
if (!in_array(wp_get_environment_type(), array('local', 'development'), true)) return;

$GLOBALS['__gwseq_perf_diag'] = array('boxes' => array(), 'phases' => array());

/**
 * Portée STRICTEMENT limitée à l'écran d'édition d'UNE fiche Cheval précise (`post.php?action=
 * edit&post=X`, jamais la liste `edit-gwseq_cheval`, jamais un autre CPT, jamais le front) — même
 * si ce fichier entier est déjà inerte en production, cette fonction garantit qu'aucun hook ajouté
 * ci-dessous n'a le moindre effet ailleurs dans le BO, y compris en local/développement.
 */
function gwseq_perf_diag_active_screen() {
  if (!is_admin() || !function_exists('get_current_screen')) return false;
  $screen = get_current_screen();
  return (bool) ($screen && $screen->base === 'post' && $screen->id === GWSEQ_CPT_CHEVAL);
}

function gwseq_perf_diag_mark($label) {
  if (!gwseq_perf_diag_active_screen()) return;
  $GLOBALS['__gwseq_perf_diag']['phases'][] = array($label, microtime(true));
}
add_action('plugins_loaded', function () { gwseq_perf_diag_mark('plugins_loaded'); }, 9999);
add_action('init', function () { gwseq_perf_diag_mark('init:début'); }, 0);
add_action('init', function () { gwseq_perf_diag_mark('init:fin'); }, 9999);
add_action('admin_init', function () { gwseq_perf_diag_mark('admin_init:début'); }, 0);
add_action('admin_init', function () { gwseq_perf_diag_mark('admin_init:fin'); }, 9999);
add_action('current_screen', function () { gwseq_perf_diag_mark('current_screen'); });
add_action('admin_enqueue_scripts', function () { gwseq_perf_diag_mark('admin_enqueue_scripts'); }, 9999);

/**
 * Enveloppe CHAQUE callback de boîte déjà enregistrée pour Cheval (natif `$wp_meta_boxes`,
 * WordPress core) d'un chronomètre — greffé sur `add_meta_boxes_{GWSEQ_CPT_CHEVAL}` à une
 * priorité très tardive (9999), donc APRÈS que tous les modules aient enregistré leurs boîtes
 * (chacune sur ce même hook, priorité par défaut 10) mais TOUJOURS AVANT `do_meta_boxes()`
 * (wp-admin/edit-form-advanced.php, qui rend réellement les boîtes) : le moment exact où
 * substituer un callback sans jamais manquer une boîte ni interférer avec son enregistrement.
 *
 * NE MODIFIE JAMAIS le comportement réel : le remplaçant appelle l'ORIGINAL avec exactement les
 * mêmes arguments et renvoie exactement sa valeur de retour — la seule différence observable est
 * l'entrée de chronométrage enregistrée en mémoire, jamais affichée nulle part avant le pied de
 * page (voir gwseq_perf_diag_render_report()) ni écrite dans la fiche elle-même.
 */
function gwseq_perf_diag_wrap_meta_boxes() {
  if (!gwseq_perf_diag_active_screen()) return;
  global $wp_meta_boxes;
  if (empty($wp_meta_boxes[GWSEQ_CPT_CHEVAL]) || !is_array($wp_meta_boxes[GWSEQ_CPT_CHEVAL])) return;

  foreach ($wp_meta_boxes[GWSEQ_CPT_CHEVAL] as $context => $priorities) {
    if (!is_array($priorities)) continue;
    foreach ($priorities as $priority => $boxes) {
      if (!is_array($boxes)) continue;
      foreach ($boxes as $id => $box) {
        if (empty($box['callback']) || !is_callable($box['callback'])) continue;
        $original_callback = $box['callback'];
        $wp_meta_boxes[GWSEQ_CPT_CHEVAL][$context][$priority][$id]['callback'] = function (...$args) use ($original_callback, $id) {
          $start = microtime(true);
          $result = call_user_func_array($original_callback, $args);
          $GLOBALS['__gwseq_perf_diag']['boxes'][$id] = microtime(true) - $start;
          return $result;
        };
      }
    }
  }
}
add_action('add_meta_boxes_' . GWSEQ_CPT_CHEVAL, 'gwseq_perf_diag_wrap_meta_boxes', 9999);

/**
 * Rapport lisible en pied de l'écran d'édition — jamais mêlé au rendu réel de la fiche (bloc fixe
 * distinct, purement un rapport de lecture), et jamais persisté nulle part côté fiche/base. Écrit
 * aussi dans le journal PHP si `WP_DEBUG_LOG` est actif (convention WordPress déjà en place sur ce
 * site pour tout journal de débogage) — pour disposer d'une trace copiable sans dépendre de la
 * lecture visuelle du bloc.
 */
function gwseq_perf_diag_render_report() {
  if (!gwseq_perf_diag_active_screen()) return;
  gwseq_perf_diag_mark('admin_footer');

  $diag = $GLOBALS['__gwseq_perf_diag'];
  $boxes = $diag['boxes'];
  arsort($boxes);
  $phases = $diag['phases'];

  $lines = array();
  $lines[] = 'GWS — Diagnostic performance (fiche Cheval #' . (int) get_the_ID() . ', local/développement uniquement) :';

  $request_start = isset($_SERVER['REQUEST_TIME_FLOAT']) ? (float) $_SERVER['REQUEST_TIME_FLOAT'] : null;
  $now = microtime(true);
  if ($request_start !== null) {
    $lines[] = sprintf('Temps total depuis le début RÉEL de la requête HTTP (avant même WordPress) jusqu’à admin_footer : %.1f ms', ($now - $request_start) * 1000);
  }
  if ($phases) {
    $lines[] = sprintf('Temps depuis le premier point de mesure GWS (plugins_loaded) jusqu’à admin_footer : %.1f ms', ($now - $phases[0][1]) * 1000);
  }
  $boxes_total = array_sum($boxes);
  $lines[] = sprintf('Somme du temps RÉELLEMENT passé dans les boîtes méta Cheval (%d boîtes) : %.1f ms', count($boxes), $boxes_total * 1000);

  $lines[] = '';
  $lines[] = 'Boîtes, du plus lent au plus rapide :';
  foreach ($boxes as $id => $elapsed) {
    $lines[] = sprintf('  %s : %.1f ms', $id, $elapsed * 1000);
  }

  $lines[] = '';
  $lines[] = 'Étapes du cycle de chargement (délai depuis l’étape précédente) :';
  $previous = $request_start;
  foreach ($phases as $phase) {
    list($label, $time) = $phase;
    if ($previous !== null) {
      $lines[] = sprintf('  %s : +%.1f ms', $label, ($time - $previous) * 1000);
    }
    $previous = $time;
  }

  $report_text = implode("\n", $lines);

  if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
    error_log($report_text);
  }

  echo '<div id="gwseq-perf-diag" style="position:fixed;bottom:0;left:0;right:0;z-index:999999;background:#1d2327;color:#f0f0f1;font:12px/1.6 Consolas,Menlo,monospace;padding:10px 16px;max-height:45vh;overflow:auto;white-space:pre-wrap;">';
  echo esc_html($report_text);
  echo '</div>';
}
add_action('admin_footer', 'gwseq_perf_diag_render_report');

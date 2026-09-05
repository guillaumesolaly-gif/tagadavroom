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
 *
 * ITÉRATION 2 (mesure réelle : ~36 s concentrées entre `current_screen` et `admin_enqueue_
 * scripts`, indépendamment du contenu de la fiche et des boîtes méta — voir CHANGELOG.md 0.37.0) :
 * ajoute un PROFILEUR GÉNÉRIQUE PAR CALLBACK sur les hooks WordPress natifs qui s'exécutent dans
 * exactement cette fenêtre (`current_screen`, `admin_init`, `load-post.php`/`load-post-new.php`,
 * `admin_enqueue_scripts`), quelle que soit leur PROVENANCE (GWS, thème, ou N'IMPORTE QUEL plugin
 * tiers déjà installé sur ce site précis — invisibles depuis ce dépôt de code, jamais audités
 * autrement). Technique : `add_action($hook, ..., PHP_INT_MIN)` sur chacun de ces hooks pour
 * s'exécuter EN PREMIER, puis substitution EN PLACE de chaque callback déjà enregistré dans le
 * registre natif `$wp_filter[$hook]->callbacks` par un intermédiaire chronométré qui appelle
 * l'ORIGINAL avec EXACTEMENT les mêmes arguments et renvoie EXACTEMENT sa valeur — jamais une
 * réimplémentation, jamais un changement de comportement, même limite déjà démontrée par
 * gwseq_perf_diag_wrap_meta_boxes() ci-dessous, appliquée ici au registre de hooks natif plutôt
 * qu'aux boîtes méta. La SOURCE de chaque callback (fichier où il est réellement défini) est
 * résolue par réflexion (`ReflectionFunction`/`ReflectionMethod`) et classée automatiquement
 * (plugin précis, thème, cœur WordPress, mu-plugin) — sans connaître à l'avance quels plugins tiers
 * sont installés sur ce site.
 *
 * LIMITE CONNUE, documentée plutôt que masquée : un plugin qui enregistrerait dynamiquement un
 * callback sur `admin_init`/`load-post.php`/etc. DEPUIS L'INTÉRIEUR d'un callback `current_screen`
 * (donc APRÈS le passage du profileur) échapperait à cette mesure — motif rare, mais possible. Si
 * la somme des callbacks mesurés sur un hook n'explique pas tout l'écart entre deux repères de
 * temps, le rapport l'indique explicitement comme "non expliqué" plutôt que de laisser croire à
 * une mesure complète.
 *
 * ITÉRATION 3 (mesure réelle sur Jamerose : `current_screen`, `load-post.php` et
 * `admin_enqueue_scripts` eux-mêmes sont rapides — 18 ms / ~0 ms / 11,9 ms — et le callback le plus
 * lent mesuré par l'itération 2, `wp_enqueue_command_palette_assets`, ne fait que 10,3 ms. Les
 * ~36 secondes se situent donc ENTRE la fin de `load-post.php` et le début de `admin_enqueue_
 * scripts`, dans du code qui ne passe par AUCUN des hooks déjà instrumentés) : le code source réel
 * de WordPress (`wp-admin/post.php`, `wp-admin/edit-form-advanced.php` et `wp-admin/includes/
 * meta-boxes.php`, vérifiés ligne à ligne plutôt que supposés) montre que, pour un type de contenu
 * en éditeur classique — c'est le cas de Cheval, `supports` ne déclare pas `'editor'`, donc
 * `use_block_editor_for_post()` est faux et `wp-admin/edit-form-advanced.php` est bien le fichier
 * chargé — `wp-admin/post.php` inclut directement `edit-form-advanced.php`, qui appelle
 * `register_and_do_post_meta_boxes($post)` (`wp-admin/includes/meta-boxes.php`) ; cette fonction
 * déclenche `do_action('add_meta_boxes', $post_type, $post)` PUIS `do_action("add_meta_boxes_
 * {$post_type}", $post)` — donc `add_meta_boxes_gwseq_cheval`, où les 9 callbacks GWS existants
 * (cheval-fields.php, cheval-pedigree.php, cheval-media.php, cheval-indices.php, cheval-
 * labels.php, cheval-editorial.php, cheval-share-admin.php ×2, admin-ui.php) ENREGISTRENT leurs
 * boîtes — puis SEULEMENT ENSUITE, plus bas dans `edit-form-advanced.php`, `admin-header.php` est
 * chargé (ce qui déclenche `admin_enqueue_scripts`). Autrement dit, la REGISTRATION des boîtes
 * (l'exécution des 9 callbacks eux-mêmes, jamais mesurée jusqu'ici — l'itération 1 ne chronométrait
 * que leur RENDU, bien plus tard) se situe très exactement dans la fenêtre non expliquée. Cette
 * itération ajoute donc `add_meta_boxes` et `add_meta_boxes_{$post_type}` à la liste des hooks
 * profilés par callback (même technique, aucune nouveauté), avec des repères de temps encadrant
 * précisément ces deux hooks, pour savoir si le temps perdu est DANS l'un des 9 callbacks de
 * registration GWS (et lequel), DANS un hook `add_meta_boxes` générique d'un plugin tiers non lié à
 * GWS (candidat plausible pour expliquer l'indépendance au contenu de la fiche), ou encore ENTRE ces
 * repères et `admin_enqueue_scripts` (le reste de `register_and_do_post_meta_boxes()` — boucle des
 * taxonomies, révisions, etc. — et le reste d'`edit-form-advanced.php` avant `admin-header.php`).
 */

if (!defined('ABSPATH')) exit;
if (!in_array(wp_get_environment_type(), array('local', 'development'), true)) return;

$GLOBALS['__gwseq_perf_diag'] = array('boxes' => array(), 'phases' => array(), 'hook_callbacks' => array());

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
add_action('current_screen', function () { gwseq_perf_diag_mark('current_screen:début'); }, PHP_INT_MIN);
add_action('current_screen', function () { gwseq_perf_diag_mark('current_screen:fin'); }, PHP_INT_MAX);
add_action('admin_init', function () { gwseq_perf_diag_mark('admin_init:début'); }, PHP_INT_MIN);
add_action('admin_init', function () { gwseq_perf_diag_mark('admin_init:fin'); }, PHP_INT_MAX);
// `load-post.php` (édition d'une fiche existante) / `load-post-new.php` (création) — l'un ou
// l'autre se déclenche selon le contexte, jamais les deux dans la même requête.
add_action('load-post.php', function () { gwseq_perf_diag_mark('load-post.php:début'); }, PHP_INT_MIN);
add_action('load-post.php', function () { gwseq_perf_diag_mark('load-post.php:fin'); }, PHP_INT_MAX);
add_action('load-post-new.php', function () { gwseq_perf_diag_mark('load-post-new.php:début'); }, PHP_INT_MIN);
add_action('load-post-new.php', function () { gwseq_perf_diag_mark('load-post-new.php:fin'); }, PHP_INT_MAX);
add_action('admin_enqueue_scripts', function () { gwseq_perf_diag_mark('admin_enqueue_scripts:début'); }, PHP_INT_MIN);
add_action('admin_enqueue_scripts', function () { gwseq_perf_diag_mark('admin_enqueue_scripts:fin'); }, PHP_INT_MAX);
// Itération 3 — voir la note de fichier en tête : `register_and_do_post_meta_boxes()` (wp-admin/
// includes/meta-boxes.php), appelée depuis wp-admin/edit-form-advanced.php AVANT `admin-header.php`
// (donc avant `admin_enqueue_scripts`), déclenche ces deux hooks dans cet ordre exact.
add_action('add_meta_boxes', function () { gwseq_perf_diag_mark('add_meta_boxes:début'); }, PHP_INT_MIN);
add_action('add_meta_boxes', function () { gwseq_perf_diag_mark('add_meta_boxes:fin'); }, PHP_INT_MAX);
add_action('add_meta_boxes_' . GWSEQ_CPT_CHEVAL, function () { gwseq_perf_diag_mark('add_meta_boxes_' . GWSEQ_CPT_CHEVAL . ':début'); }, PHP_INT_MIN);
add_action('add_meta_boxes_' . GWSEQ_CPT_CHEVAL, function () { gwseq_perf_diag_mark('add_meta_boxes_' . GWSEQ_CPT_CHEVAL . ':fin'); }, PHP_INT_MAX);

/* -------------------------------------------------------------------------------------------
 * Profileur générique par callback (itération 2, étendu en itération 3) — voir la note de fichier
 * en tête pour la technique et sa limite connue.
 * ----------------------------------------------------------------------------------------- */

const GWSEQ_PERF_DIAG_TARGET_HOOKS = array('current_screen', 'admin_init', 'load-post.php', 'load-post-new.php', 'admin_enqueue_scripts', 'add_meta_boxes', 'add_meta_boxes_' . GWSEQ_CPT_CHEVAL);

/**
 * Résout la PROVENANCE réelle d'un callable par réflexion — jamais une supposition : classe
 * automatiquement n'importe quel plugin/thème déjà installé sur ce site précis, y compris un
 * plugin tiers totalement absent de ce dépôt de code.
 */
function gwseq_perf_diag_describe_callable($callable) {
  try {
    if (is_array($callable)) {
      $reflection = new ReflectionMethod($callable[0], $callable[1]);
      $class_name = is_object($callable[0]) ? get_class($callable[0]) : $callable[0];
      $label = $class_name . '::' . $callable[1];
    } elseif (is_string($callable) && strpos($callable, '::') !== false) {
      $reflection = new ReflectionMethod($callable);
      $label = $callable;
    } else {
      $reflection = new ReflectionFunction($callable);
      $label = is_string($callable) ? $callable : '{closure}';
    }
  } catch (\Throwable $e) {
    return array('label' => is_string($callable) ? $callable : '(callable illisible)', 'source' => 'inconnu');
  }

  $file = $reflection->getFileName();
  if (!$file) {
    return array('label' => $label, 'source' => 'php/wordpress (fonction native)');
  }

  $file = wp_normalize_path($file);
  $content_dir = defined('WP_CONTENT_DIR') ? wp_normalize_path(WP_CONTENT_DIR) : '';
  $abspath = wp_normalize_path(ABSPATH);

  if ($content_dir !== '' && strpos($file, $content_dir . '/plugins/') === 0) {
    $relative = substr($file, strlen($content_dir . '/plugins/'));
    $source = 'plugin:' . strtok($relative, '/');
  } elseif ($content_dir !== '' && strpos($file, $content_dir . '/mu-plugins/') === 0) {
    $source = 'mu-plugin';
  } elseif ($content_dir !== '' && strpos($file, $content_dir . '/themes/') === 0) {
    $relative = substr($file, strlen($content_dir . '/themes/'));
    $source = 'theme:' . strtok($relative, '/');
  } elseif (strpos($file, $abspath . 'wp-admin/') === 0 || strpos($file, $abspath . 'wp-includes/') === 0) {
    $source = 'wordpress-core';
  } else {
    $source = $file;
  }

  return array('label' => $label . ' (' . basename($file) . ':' . $reflection->getStartLine() . ')', 'source' => $source);
}

/**
 * Substitue EN PLACE, dans le registre natif `$wp_filter`, chaque callback déjà enregistré sur
 * $hook par un intermédiaire chronométré — jamais un changement de comportement (mêmes arguments,
 * transmis tels quels ; même valeur de retour ; une exception éventuelle continue de se propager
 * normalement, seulement observée en passant via `finally`).
 */
function gwseq_perf_diag_wrap_hook_callbacks($hook) {
  global $wp_filter;
  if (empty($wp_filter[$hook]) || !is_object($wp_filter[$hook]) || empty($wp_filter[$hook]->callbacks)) return;

  foreach ($wp_filter[$hook]->callbacks as $priority => $entries) {
    if (!is_array($entries)) continue;
    foreach ($entries as $entry_id => $entry) {
      if (empty($entry['function']) || !is_callable($entry['function'])) continue;
      $original = $entry['function'];
      $described = gwseq_perf_diag_describe_callable($original);

      $wp_filter[$hook]->callbacks[$priority][$entry_id]['function'] = function (...$args) use ($original, $hook, $priority, $described) {
        $start = microtime(true);
        try {
          return call_user_func_array($original, $args);
        } finally {
          $GLOBALS['__gwseq_perf_diag']['hook_callbacks'][] = array(
            'hook' => $hook,
            'priority' => $priority,
            'label' => $described['label'],
            'source' => $described['source'],
            'elapsed' => microtime(true) - $start,
          );
        }
      };
    }
  }
}

/**
 * Installé sur `current_screen`, à la priorité la plus basse possible (PHP_INT_MIN) : le premier
 * des hooks cibles à se déclencher dans la séquence réelle de WordPress (`current_screen` ->
 * `admin_init` -> `load-post.php`/`load-post-new.php` -> `add_meta_boxes` ->
 * `add_meta_boxes_{post_type}` -> ... -> `admin_enqueue_scripts`), donc le seul point où l'on peut
 * encore substituer les callbacks de TOUS LES AUTRES hooks cibles avant qu'ils ne se déclenchent
 * (voir la limite connue en tête de fichier).
 */
function gwseq_perf_diag_install_hook_profilers() {
  if (!gwseq_perf_diag_active_screen()) return;
  foreach (GWSEQ_PERF_DIAG_TARGET_HOOKS as $hook) {
    gwseq_perf_diag_wrap_hook_callbacks($hook);
  }
}
add_action('current_screen', 'gwseq_perf_diag_install_hook_profilers', PHP_INT_MIN);

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

  // Somme des callbacks mesurés PAR HOOK (itération 2) — sert au calcul du temps "non expliqué"
  // par étape (voir la boucle des phases plus bas).
  $callback_time_by_hook = array();
  foreach ($diag['hook_callbacks'] as $entry) {
    $callback_time_by_hook[$entry['hook']] = ($callback_time_by_hook[$entry['hook']] ?? 0) + $entry['elapsed'];
  }

  $lines[] = '';
  $lines[] = 'Boîtes, du plus lent au plus rapide :';
  foreach ($boxes as $id => $elapsed) {
    $lines[] = sprintf('  %s : %.1f ms', $id, $elapsed * 1000);
  }

  $lines[] = '';
  $lines[] = 'Étapes du cycle de chargement (délai depuis l’étape précédente ; "non expliqué" =';
  $lines[] = 'délai moins la somme des callbacks mesurés sur le hook qui VIENT DE SE TERMINER, quand';
  $lines[] = 'ce hook fait partie de ceux instrumentés en détail ci-dessous) :';
  $previous = $request_start;
  foreach ($phases as $phase) {
    list($label, $time) = $phase;
    if ($previous !== null) {
      $delta_ms = ($time - $previous) * 1000;
      $explained = null;
      // Le libellé de L'ÉTAPE COURANTE (ex. "current_screen:fin") correspond au hook natif dont le
      // délai qu'on vient de calculer ($delta_ms, depuis son ":début") est exactement la durée
      // totale d'exécution — c'est cette ligne-ci qu'il faut annoter, jamais la suivante.
      if (substr($label, -4) === ':fin') {
        $hook_name = substr($label, 0, -4);
        if (isset($callback_time_by_hook[$hook_name])) {
          $explained = $callback_time_by_hook[$hook_name] * 1000;
        }
      }
      if ($explained !== null) {
        $lines[] = sprintf('  %s : +%.1f ms (dont %.1f ms dans les callbacks mesurés sur ce hook, %.1f ms non expliqué)', $label, $delta_ms, $explained, $delta_ms - $explained);
      } else {
        $lines[] = sprintf('  %s : +%.1f ms', $label, $delta_ms);
      }
    }
    $previous = $time;
  }

  if ($diag['hook_callbacks']) {
    $ranked = $diag['hook_callbacks'];
    usort($ranked, function ($a, $b) { return $b['elapsed'] <=> $a['elapsed']; });
    $lines[] = '';
    $lines[] = 'Callbacks natifs mesurés sur ' . implode('/', GWSEQ_PERF_DIAG_TARGET_HOOKS) . ',';
    $lines[] = 'du plus lent au plus rapide (callback -> source -> durée) :';
    foreach ($ranked as $entry) {
      $lines[] = sprintf('  [%s] %s -> %s -> %.1f ms', $entry['hook'], $entry['label'], $entry['source'], $entry['elapsed'] * 1000);
    }
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

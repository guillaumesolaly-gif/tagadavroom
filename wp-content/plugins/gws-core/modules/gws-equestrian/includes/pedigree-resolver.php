<?php
/**
 * Pedigree — moteur de résolution (Étape 5). Produit une STRUCTURE DE DONNÉES déterministe,
 * indépendante du HTML, à partir des relations stockées par includes/cheval-pedigree.php —
 * jamais de rendu ici. Conçue pour être réutilisée telle quelle par un futur rendu web (Étape 8),
 * export PDF/catalogue, ou projection API, sans aucune donnée privée par défaut (voir la liste
 * fermée de champs exposés par nœud ci-dessous — jamais un get_post_meta($id) en bloc, dans le
 * droit fil du §20 de l'Étape 4).
 *
 * Une branche externe n'est pas une simple feuille. Un ascendant externe (mode 'external') peut
 * lui-même avoir un père et une mère, également externes, jusqu'à la profondeur maximale — voir
 * gwseq_resolve_external_ancestor_node() ci-dessous, qui applique EXACTEMENT la même logique de
 * comptage de génération et d'arrêt que gwseq_resolve_horse_node() pour les fiches GWS : les deux
 * types de branches sont comptés de façon strictement identique, un mélange des deux dans un même
 * pedigree ne crée donc aucune ambiguïté de profondeur.
 *
 * DÉFINITION EXACTE DES "4 GÉNÉRATIONS" : la fiche dont on résout le pedigree est la génération 0
 * (toujours entièrement résolue, ce n'est pas un ascendant). Le paramètre $max_depth (par défaut
 * GWSEQ_PEDIGREE_MAX_DEPTH = 4) est le nombre de générations d'ASCENDANTS résolues au-delà de la
 * fiche elle-même, qu'elles soient GWS ou externes :
 *   génération 1 = parents (2 nœuds max)         | résolus si $max_depth >= 1
 *   génération 2 = grands-parents (4 nœuds max)  | résolus si $max_depth >= 2
 *   génération 3 = arrière-grands-parents (8)    | résolus si $max_depth >= 3
 *   génération 4 = arrière-arrière-grands-parents (16) | résolus si $max_depth >= 4
 * Soit 30 nœuds d'ascendants au maximum (2+4+8+16) en plus de la racine, avec $max_depth = 4.
 *
 * GÉNÉRATION TERMINALE (correctif post-recette — revient sur la première version, qui produisait
 * un nœud sentinelle {type: "depth_limit"} au-delà de la limite) : un nœud de la dernière
 * génération autorisée N'A STRUCTURELLEMENT AUCUN père/mère — les clés `father`/`mother` sont
 * absentes de son tableau, pas seulement `null`. La recette a montré qu'un rendu naïf de `null`
 * (« absence de donnée ») produisait, sous un nœud de génération 4, un « Père : Non renseigné »/
 * « Mère : Non renseigné » qui laisse croire à tort qu'une génération 5 existerait dans le
 * modèle — alors qu'elle est hors périmètre du pedigree V1, jamais saisissable ni stockée. Un
 * nœud à `$depth_remaining <= 0` ne tente donc même plus de lire les relations de père/mère de
 * cette fiche/cet ascendant : ce n'est pas "une donnée absente", c'est "une question qui ne se
 * pose pas à cette profondeur". Toute donnée réelle au-delà (stockage corrompu, ou un futur appel
 * du resolver avec un $max_depth explicitement réduit) est silencieusement ignorée, jamais
 * représentée — voir gwseq_render_pedigree_node_preview() pour le rendu correspondant
 * (n'affiche aucune ligne Père/Mère quand ces clés sont absentes). Principe général à conserver
 * pour un futur rendu public (Étape 8) : une donnée généalogique absente ne doit jamais être
 * remplacée par un texte du type « Non renseigné », sauf besoin explicite futur.
 *
 * Une relation qui n'a simplement jamais été renseignée (à une profondeur où la question se pose
 * légitimement) reste `null` — distinct d'une clé absente, qui signifie "hors du périmètre
 * représentable à cette profondeur".
 *
 * PROTECTION CONTRE LES CYCLES (uniquement pertinent pour les branches GWS — une branche externe
 * ne peut jamais former de cycle, elle n'est composée que de texte structuré, jamais de référence
 * à une autre fiche) : un cycle DIRECT (auto-référence) est déjà rejeté à la sauvegarde par
 * gwseq_sanitize_horse_parent_gws_id(). Un cycle INDIRECT (A -> père B -> père A) ne peut être
 * détecté qu'ici, à la résolution, car il dépend de l'état d'une autre fiche jamais consultée au
 * moment où A a été enregistrée. Le chemin d'ascendance courant (de la racine jusqu'au nœud en
 * cours) est suivi à chaque appel récursif ; si un identifiant déjà présent dans ce chemin est
 * rencontré à nouveau, la résolution de cette branche s'arrête proprement avec un nœud
 * {type: "cycle_detected"} — jamais de boucle infinie, jamais d'erreur fatale.
 *
 * UNE SEULE SOURCE ACTIVE PAR RELATION : `gwseq_get_horse_parent()` (voir cheval-pedigree.php)
 * peut renvoyer une branche externe même quand le mode actif est 'gws' (elle reste stockée,
 * inactive, pour ne rien perdre si l'utilisateur revient en arrière) — ce fichier ne lit JAMAIS
 * cette branche inactive : chaque relation est résolue selon UN SEUL mode (`$relation['mode']`),
 * jamais un mélange des deux.
 *
 * PERFORMANCE : le nombre de requêtes est borné par construction (au plus 31 fiches avec la
 * profondeur par défaut, une branche externe ne coûtant elle-même aucune requête puisqu'elle est
 * déjà entièrement chargée avec la fiche qui la référence). Une mémoïsation locale à UN SEUL appel
 * de gwseq_resolve_horse_pedigree() (tableau passé par référence, jamais un cache statique/
 * persistant entre deux appels — un parent modifié doit être vu immédiatement à la prochaine
 * résolution, sans purge de cache à gérer) évite de re-résoudre une même fiche GWS rencontrée
 * plusieurs fois dans un pedigree (cas fréquent en élevage : un même étalon peut apparaître à la
 * fois comme grand-père paternel et arrière-grand-père maternel). La clé de mémoïsation inclut la
 * profondeur restante (`"{id}:{depth_remaining}"`) et non le seul identifiant, pour rester
 * correcte face à un même ascendant croisé à deux profondeurs différentes dans le même pedigree.
 */

if (!defined('ABSPATH')) exit;

const GWSEQ_PEDIGREE_MAX_DEPTH = 4;

/**
 * Point d'entrée. Structure de nœud "gws_horse" (cheval résolu, présent dans GWS) ou "external"
 * (ascendant hors GWS, structuré) — les deux partagent la même forme :
 *   {type, id?, global_id?, name, breed, father?, mother?}
 * ("id"/"global_id" uniquement pour "gws_horse" — un ascendant externe n'a ni identifiant de post
 * ni Global Horse ID : ce serait confondre l'identité d'une fiche GWS avec l'identité biologique
 * d'un cheval, que ce module ne prétend jamais garantir. "father"/"mother" ABSENTS — pas même
 * `null` — sur un nœud de la dernière génération autorisée : voir "GÉNÉRATION TERMINALE"
 * ci-dessus.)
 * "unavailable" (l'ID référencé ne pointe plus vers une fiche Cheval existante — supprimée
 * définitivement ; un cheval simplement mis à la corbeille reste résolu normalement, ses données
 * n'étant pas perdues) :
 *   {type, id}
 * "cycle_detected" (voir ci-dessus, uniquement possible pour une chaîne GWS) :
 *   {type, id}
 * Une relation jamais renseignée (à une profondeur où la question se pose) reste simplement
 * `null`.
 *
 * Champs volontairement exclus de tout nœud (aucune donnée privée exposée par défaut) : statut
 * commercial, prix, éleveur, propriétaire, UELN/SIRE, catégories — seuls identité/filiation
 * publique et Global Horse ID (pour une fiche GWS uniquement) sont représentés.
 */
function gwseq_resolve_horse_pedigree($cheval_id, $max_depth = null) {
  if ($max_depth === null) $max_depth = GWSEQ_PEDIGREE_MAX_DEPTH;
  $memo = array();
  return gwseq_resolve_horse_node((int) $cheval_id, (int) $max_depth, $memo, array());
}

function gwseq_resolve_horse_node($cheval_id, $depth_remaining, &$memo, $ancestor_path) {
  $memo_key = $cheval_id . ':' . $depth_remaining;
  if (array_key_exists($memo_key, $memo)) return $memo[$memo_key];

  if (in_array($cheval_id, $ancestor_path, true)) {
    return array('type' => 'cycle_detected', 'id' => $cheval_id);
  }

  if (get_post_type($cheval_id) !== GWSEQ_CPT_CHEVAL) {
    return array('type' => 'unavailable', 'id' => $cheval_id);
  }
  if (!get_post($cheval_id)) {
    return array('type' => 'unavailable', 'id' => $cheval_id);
  }

  $identity = gwseq_get_cheval_identity($cheval_id);
  $node = array(
    'type' => 'gws_horse',
    'id' => $cheval_id,
    'global_id' => gwseq_get_cheval_global_id($cheval_id),
    'name' => get_the_title($cheval_id),
    'breed' => gwseq_cheval_race_label($identity['race'], $identity['race_autre']),
  );

  if ($depth_remaining <= 0) {
    // Génération terminale (voir l'en-tête du fichier) : ni père ni mère ne sont même
    // structurellement représentés au-delà — ce nœud est terminal par construction.
    $memo[$memo_key] = $node;
    return $node;
  }

  $node['father'] = null;
  $node['mother'] = null;

  $path = $ancestor_path;
  $path[] = $cheval_id;

  foreach (array('father', 'mother') as $role) {
    $relation = gwseq_get_horse_parent($cheval_id, $role);
    if ($relation['mode'] === '') continue; // jamais renseignée : reste null

    if ($relation['mode'] === 'external') {
      $node[$role] = gwseq_resolve_external_ancestor_node($relation['external'], $depth_remaining - 1);
      continue;
    }

    // $relation['mode'] === 'gws'
    $node[$role] = gwseq_resolve_horse_node($relation['horse_id'], $depth_remaining - 1, $memo, $path);
  }

  $memo[$memo_key] = $node;
  return $node;
}

/**
 * Résout récursivement un ascendant externe et ses propres ascendants (également externes),
 * jusqu'à $depth_remaining niveaux supplémentaires. Aucune requête ici : la branche entière est
 * déjà chargée avec la fiche qui la référence (voir gwseq_get_horse_parent(), qui applique aussi
 * la compatibilité ascendante d'un éventuel ancien format — ce fichier n'a donc jamais à s'en
 * soucier). $tree_node = null ou un tableau sans "name" -> absence de donnée, jamais un nœud
 * fabriqué. Génération terminale : voir l'en-tête du fichier — mêmes règles que pour une chaîne
 * GWS, aucune requête n'est de toute façon économisée puisqu'il n'y en a aucune pour une branche
 * externe (déjà entièrement en mémoire).
 *
 * "breed" dans le nœud produit reste un LIBELLÉ résolu (comme pour un cheval GWS, via
 * gwseq_cheval_race_label() — même référentiel, jamais dupliqué), même si le stockage interne
 * distingue `race` (code technique) et `race_autre` (texte) : le contrat de sortie du resolver ne
 * change pas pour ses consommateurs (front/PDF/catalogue/API futurs).
 *
 * GARDE DÉFENSIVE (§5 du correctif complémentaire post-recette « suppression d'un ascendant
 * externe vide », 0.8.0) : un nœud sans nom (`$tree_node` non tableau, ou "name" absent/vide) n'est
 * jamais résolu en un nœud "external" — il reste `null`, quels que soient les autres champs
 * éventuellement présents. Une branche vide reste donc toujours une absence de branche, jamais un
 * nœud fantôme affiché, y compris pour une donnée antérieure à ce correctif (garde déjà en place
 * avant 0.8.0 — cette version se contente de la documenter et de la tester explicitement).
 */
function gwseq_resolve_external_ancestor_node($tree_node, $depth_remaining) {
  if (!is_array($tree_node) || ($tree_node['name'] ?? '') === '') return null;

  $breed_label = gwseq_cheval_race_label($tree_node['race'] ?? '', $tree_node['race_autre'] ?? '');
  $node = array(
    'type' => 'external',
    'name' => $tree_node['name'],
    'breed' => $breed_label !== '' ? $breed_label : null,
  );

  if ($depth_remaining <= 0) {
    return $node; // génération terminale : aucun père/mère représenté au-delà
  }

  $node['father'] = gwseq_resolve_external_ancestor_node($tree_node['father'] ?? null, $depth_remaining - 1);
  $node['mother'] = gwseq_resolve_external_ancestor_node($tree_node['mother'] ?? null, $depth_remaining - 1);
  return $node;
}

/**
 * Rendu texte/HTML minimal d'un nœud, réservé à la boîte de vérification admin/développement
 * (includes/cheval-pedigree.php) — PAS le futur rendu public de l'Étape 8. Volontairement une
 * simple liste imbriquée, aucun style. "gws_horse" et "external" partagent le même rendu
 * récursif (nom, race, puis père/mère) — seul un petit marqueur distingue leur origine. N'affiche
 * AUCUNE ligne Père/Mère quand ces clés sont structurellement absentes (nœud de génération
 * terminale) — jamais un « Non renseigné » pour une génération hors du modèle V1 (voir l'en-tête
 * du fichier).
 */
function gwseq_render_pedigree_node_preview($node) {
  if ($node === null) return '<em>' . esc_html__('Non renseigné', 'gws-core') . '</em>';

  if ($node['type'] === 'unavailable') {
    return '<em>' . esc_html__('Cheval introuvable', 'gws-core') . ' (#' . (int) $node['id'] . ')</em>';
  }
  if ($node['type'] === 'cycle_detected') {
    return '<em>' . esc_html__('Cycle détecté — résolution interrompue', 'gws-core') . ' (#' . (int) $node['id'] . ')</em>';
  }

  // "gws_horse" ou "external" : même rendu récursif.
  $breed = !empty($node['breed']) ? ' (' . esc_html($node['breed']) . ')' : '';
  $type_marker = $node['type'] === 'external' ? ' <em>(' . esc_html__('externe', 'gws-core') . ')</em>' : '';
  $html = '<strong>' . esc_html($node['name']) . '</strong>' . $breed . $type_marker;

  if (array_key_exists('father', $node) || array_key_exists('mother', $node)) {
    $html .= '<ul style="margin-left:1em;">';
    if (array_key_exists('father', $node)) {
      $html .= '<li>' . esc_html__('Père', 'gws-core') . ' : ' . gwseq_render_pedigree_node_preview($node['father']) . '</li>';
    }
    if (array_key_exists('mother', $node)) {
      $html .= '<li>' . esc_html__('Mère', 'gws-core') . ' : ' . gwseq_render_pedigree_node_preview($node['mother']) . '</li>';
    }
    $html .= '</ul>';
  }
  return $html;
}

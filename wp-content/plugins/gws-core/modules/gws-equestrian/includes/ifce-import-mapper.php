<?php
/**
 * Import IFCE — mapping de la structure normalisée vers le modèle GWS existant (Étape 7, §7 de la
 * demande).
 *
 * RÈGLE ARCHITECTURALE CENTRALE (§7 : "Le parser IFCE ne doit jamais écrire directement dans les
 * post meta") : cette fonction n'appelle JAMAIS update_post_meta() elle-même — elle se contente de
 * relayer la structure déjà produite par gwseq_ifce_parse_text() vers les MÊMES fonctions métier
 * pures que la saisie manuelle admin utilise déjà : gwseq_set_cheval_identity() (cheval-fields.php),
 * gwseq_set_cheval_sport_indice()/gwseq_set_cheval_genetic_indice() (cheval-indices.php),
 * gwseq_set_horse_parent() (cheval-pedigree.php). Un futur import CSV/API/autre fournisseur pourra
 * réutiliser exactement ces mêmes fonctions métier sans qu'aucune n'ait eu besoin d'être créée ou
 * modifiée pour l'occasion.
 *
 * ASCENDANTS TOUJOURS EXTERNES (§8) : chaque relation Père/Mère importée utilise systématiquement
 * mode='external' — aucune fiche gwseq_cheval n'est jamais créée automatiquement pour un ascendant,
 * aucune tentative de rapprochement/déduplication par nom avec une fiche GWS existante.
 *
 * IMPORT PARTIEL (§9) : $sections = {identity, indices, pedigree} (booléens) contrôle
 * indépendamment chaque bloc — omettre une section signifie qu'elle n'est simplement jamais
 * transmise aux fonctions métier correspondantes, sans qu'aucune meta ne soit touchée pour cette
 * section.
 *
 * AUCUNE ÉCRITURE AVANT VALIDATION (§1) : cette fonction elle-même n'est déclenchée par
 * ifce-import-admin.php QU'après confirmation explicite de l'utilisateur sur l'écran de
 * prévisualisation — jamais automatiquement à l'analyse du PDF.
 */

if (!defined('ABSPATH')) exit;

/**
 * Applique la structure normalisée $parsed (produite par gwseq_ifce_parse_text(), doit avoir
 * 'valid' === true) à la fiche Cheval $post_id, pour les sections activées dans $sections. Ne
 * modifie jamais une section non activée. Retourne false si $post_id ou $parsed est invalide,
 * true sinon (même garantie de type que les fonctions métier qu'elle appelle).
 *
 * $parent_choices (§3 de la demande, "rattacher Père/Mère à des chevaux GWS pendant l'import" —
 * PARAMÈTRE OPTIONNEL, comportement 100% inchangé si omis) : {father, mother}, chacun
 * {mode, horse_id} — décision prise par l'utilisateur sur l'écran de prévisualisation, UNIQUEMENT
 * pour les deux parents DIRECTS (jamais les 12 ascendants suivants, qui restent gérés par l'arbre
 * externe importé ou par le pedigree propre du cheval GWS lié, selon la logique déjà existante) :
 * - 'external' (répli par défaut, comportement déjà validé, inchangé) : les données détectées par
 *   l'IFCE pour ce parent sont importées dans la structure externe, exactement comme avant ce
 *   correctif ;
 * - 'gws' : relie ce parent à une fiche Cheval GWS déjà existante (`horse_id`) au lieu de créer un
 *   ascendant externe — AUCUNE copie externe en parallèle (voir plus bas, jamais les deux à la
 *   fois) ;
 * - 'skip' : n'importe aucune relation pour ce parent, quelles que soient les données détectées.
 * Père puis Mère sont TOUJOURS traités DANS CET ORDRE ci-dessous, jamais l'inverse : c'est ce qui
 * permet à gwseq_set_horse_parent() (includes/cheval-pedigree.php, RÈGLE MÉTIER UNIQUE ET CENTRALE,
 * jamais dupliquée ici) d'appliquer, pour la Mère, le contrôle "même cheval comme père ET mère" en
 * relisant la relation Père déjà enregistrée juste avant — exactement le même mécanisme que la
 * saisie manuelle, sans le moindre code de validation supplémentaire ici. Cette fonction reste par
 * ailleurs identique à sa mise en oeuvre précédente : ne construit ni ne recalcule aucune règle
 * métier, ne fait toujours que relayer une décision déjà validée vers gwseq_set_horse_parent().
 */
function gwseq_ifce_map_import($post_id, $parsed, $sections, $parent_choices = array()) {
  $post_id = (int) $post_id;
  if (!$post_id || empty($parsed['valid'])) return false;
  $sections = is_array($sections) ? $sections : array();

  if (!empty($sections['identity'])) {
    $identity = $parsed['identity'];
    gwseq_set_cheval_identity($post_id, array(
      '_gwseq_sexe' => $identity['sexe'],
      '_gwseq_annee_naissance' => $identity['annee_naissance'],
      '_gwseq_robe' => $identity['robe'],
      '_gwseq_robe_autre' => $identity['robe_autre'],
      '_gwseq_race' => $identity['race'],
      '_gwseq_race_autre' => $identity['race_autre'],
      '_gwseq_taille_cm' => $identity['taille_cm'],
      '_gwseq_eleveur' => $identity['eleveur'],
      '_gwseq_ueln' => $identity['ueln'],
      '_gwseq_sire' => $identity['sire'],
    ));
    // Nom officiel IFCE (correctif runtime, §8) : quand un alias existe, `post_title`/`nom` porte
    // désormais le nom d'usage (voir la création de la fiche dans ifce-import-admin.php et
    // gwseq_ifce_parse_identity_from_lines()) — le nom officiel n'est alors jamais perdu, conservé
    // séparément comme donnée technique/source, jamais exposée dans le formulaire manuel.
    if (!empty($identity['nom_officiel'])) {
      gwseq_set_cheval_ifce_nom_officiel($post_id, $identity['nom_officiel']);
    }
  }

  if (!empty($sections['indices'])) {
    foreach (gwseq_cheval_sport_indice_keys() as $key) {
      $indice = $parsed['indices'][$key] ?? array();
      if (($indice['valeur'] ?? '') === '') continue; // rien de détecté pour cet indice : jamais une valeur inventée
      gwseq_set_cheval_sport_indice($post_id, $key, array(
        'valeur' => $indice['valeur'],
        'cd' => $indice['cd'] ?? '',
        'annee' => $indice['annee'] ?? '',
      ));
    }
    foreach (gwseq_cheval_genetic_indice_keys() as $key) {
      $indice = $parsed['indices'][$key] ?? array();
      if (($indice['valeur'] ?? '') === '') continue;
      gwseq_set_cheval_genetic_indice($post_id, $key, array(
        'valeur' => $indice['valeur'],
        'cd' => $indice['cd'] ?? '',
      ));
    }
  }

  if (!empty($sections['pedigree'])) {
    $pedigree = $parsed['pedigree'];
    $parent_choices = is_array($parent_choices) ? $parent_choices : array();
    // Père PUIS Mère, toujours dans cet ordre — voir le docblock ci-dessus.
    foreach (array('father', 'mother') as $role) {
      $branch = $pedigree[$role] ?? null;
      $choice = is_array($parent_choices[$role] ?? null) ? $parent_choices[$role] : array();
      $mode = $choice['mode'] ?? 'external';

      if ($mode === 'skip') continue; // décision explicite de l'utilisateur : aucune relation créée

      if ($mode === 'gws') {
        $horse_id = (int) ($choice['horse_id'] ?? 0);
        // Validation déjà entièrement assurée par gwseq_set_horse_parent() lui-même (auto-référence
        // impossible pour une fiche qui vient d'être créée, sexe/année/conflit avec l'autre rôle) —
        // aucune règle dupliquée ici ; un candidat rejeté ne modifie simplement rien pour ce rôle.
        if ($horse_id) gwseq_set_horse_parent($post_id, $role, array('mode' => 'gws', 'horse_id' => $horse_id));
        continue;
      }

      // 'external' (répli par défaut) : comportement déjà validé, strictement inchangé.
      if (!empty($branch)) {
        gwseq_set_horse_parent($post_id, $role, array('mode' => 'external', 'external' => $branch));
      }
    }
  }

  return true;
}

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
 */
function gwseq_ifce_map_import($post_id, $parsed, $sections) {
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
    if (!empty($pedigree['father'])) {
      gwseq_set_horse_parent($post_id, 'father', array('mode' => 'external', 'external' => $pedigree['father']));
    }
    if (!empty($pedigree['mother'])) {
      gwseq_set_horse_parent($post_id, 'mother', array('mode' => 'external', 'external' => $pedigree['mother']));
    }
  }

  return true;
}

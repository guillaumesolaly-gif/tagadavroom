<?php
/**
 * Exemple de questionnaire — à remplacer par les questions réelles du projet.
 * Chaque réponse porte un score ; le total détermine le niveau (voir gws_diag_level()).
 */

if (!defined('ABSPATH')) exit;

function gws_diag_questions() {
  return array(
    array(
      'id' => 'situation',
      'question' => 'Comment décririez-vous la situation actuelle ?',
      'choices' => array('Stable' => 0, 'Quelques tensions' => 1, 'Difficile' => 2, 'Critique' => 3),
    ),
    array(
      'id' => 'visibility',
      'question' => 'Avez-vous une visibilité claire sur les prochaines semaines ?',
      'choices' => array('Oui' => 0, 'Partiellement' => 1, 'Peu' => 2, 'Aucune' => 3),
    ),
    array(
      'id' => 'actions',
      'question' => 'Des mesures ont-elles déjà été engagées ?',
      'choices' => array('Oui, avec résultats' => 0, 'Elles débutent' => 1, 'Insuffisantes' => 2, 'Aucune' => 3),
    ),
    array(
      'id' => 'urgency',
      'question' => 'Une échéance importante est-elle imminente ?',
      'choices' => array('Non' => 0, 'Dans plus d’un mois' => 1, 'Dans les prochaines semaines' => 2, 'Dans les prochains jours' => 3),
    ),
  );
}

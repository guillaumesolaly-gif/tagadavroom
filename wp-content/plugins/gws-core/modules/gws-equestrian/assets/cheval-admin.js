/**
 * Écran d'édition d'une fiche cheval — affichage conditionnel de trois groupes de champs
 * indépendants : précision "Robe : Autre", précision "Race/Stud-book : Autre", et le bloc de prix
 * correspondant au mode de prix choisi (Prix fixe / Fourchette / Sur demande). Même technique que
 * assets/prestation-admin.js (JavaScript natif, aucune dépendance, la sauvegarde réelle reste
 * entièrement gérée côté serveur — voir includes/cheval-fields.php) : ce script ne fait
 * qu'afficher/masquer des blocs déjà présents dans le DOM.
 */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    var robeSelect = document.getElementById('gwseq-cheval-robe');
    var raceSelect = document.getElementById('gwseq-cheval-race');
    var prixModeSelect = document.getElementById('gwseq-cheval-prix-mode');

    function setVisible(selector, visible) {
      var field = document.querySelector(selector);
      if (field) field.style.display = visible ? '' : 'none';
    }

    function applyRobe() {
      if (!robeSelect) return;
      setVisible('[data-gwseq-cheval-fields="robe-autre"]', robeSelect.value === 'autre');
    }

    function applyRace() {
      if (!raceSelect) return;
      setVisible('[data-gwseq-cheval-fields="race-autre"]', raceSelect.value === 'autre');
    }

    function applyPrixMode() {
      if (!prixModeSelect) return;
      var mode = prixModeSelect.value;
      setVisible('[data-gwseq-cheval-fields="prix-fixed"]', mode === 'fixed');
      setVisible('[data-gwseq-cheval-fields="prix-range"]', mode === 'range');
      setVisible('[data-gwseq-cheval-fields="prix-on-request"]', mode === 'on_request');
    }

    if (robeSelect) {
      robeSelect.addEventListener('change', applyRobe);
      applyRobe();
    }
    if (raceSelect) {
      raceSelect.addEventListener('change', applyRace);
      applyRace();
    }
    if (prixModeSelect) {
      prixModeSelect.addEventListener('change', applyPrixMode);
      applyPrixMode();
    }
  });
})();

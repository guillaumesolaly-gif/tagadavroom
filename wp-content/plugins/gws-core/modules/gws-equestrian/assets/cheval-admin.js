/**
 * Écran d'édition d'une fiche cheval — affichage conditionnel de plusieurs groupes de champs
 * indépendants : précision "Robe : Autre", précision "Race/Stud-book : Autre", le bloc de prix
 * correspondant au mode de prix choisi (Prix fixe / Fourchette / Sur demande), et — depuis
 * l'Étape 5 — la source de chaque parent (Père/Mère : cheval GWS ou ascendant externe). Même
 * technique que assets/prestation-admin.js (JavaScript natif, aucune dépendance, la sauvegarde
 * réelle reste entièrement gérée côté serveur — voir includes/cheval-fields.php et
 * includes/cheval-pedigree.php) : ce script ne fait qu'afficher/masquer des blocs déjà présents
 * dans le DOM. Léger, scopé à cet écran, sans erreur si JavaScript est indisponible : les deux
 * groupes de champs (GWS et externe) restent alors simplement tous deux visibles et le serveur
 * reste seul autoritaire sur ce qui est réellement enregistré (voir la sanitation par mode dans
 * includes/cheval-pedigree.php).
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

    // Source du Père / de la Mère (Étape 5) : deux groupes de radios indépendants, noms de champs
    // distincts (_gwseq_pere_mode / _gwseq_mere_mode) — chacun bascule son propre bloc GWS/externe.
    [
      { role: 'father', name: '_gwseq_pere_mode' },
      { role: 'mother', name: '_gwseq_mere_mode' }
    ].forEach(function (parent) {
      var radios = document.getElementsByName(parent.name);
      if (!radios.length) return;

      function applyParentSource() {
        var selected = '';
        radios.forEach(function (radio) {
          if (radio.checked) selected = radio.value;
        });
        setVisible('[data-gwseq-parent-fields="' + parent.role + '-gws"]', selected === 'gws');
        setVisible('[data-gwseq-parent-fields="' + parent.role + '-external"]', selected === 'external');
      }

      radios.forEach(function (radio) {
        radio.addEventListener('change', applyParentSource);
      });
      applyParentSource();
    });
  });
})();

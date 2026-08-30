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

    // Race/Stud-book d'un ascendant externe (correction post-recette) : jusqu'à 15 nœuds par
    // branche (4 générations), chacun avec son propre sélecteur — une écoute déléguée unique sur
    // le conteneur du pedigree évite d'attacher un gestionnaire par nœud. Le champ "Préciser la
    // race" est toujours l'élément suivant immédiat dans le balisage (voir
    // includes/cheval-pedigree.php), jamais recherché par identifiant.
    document.addEventListener('change', function (e) {
      if (!e.target.classList || !e.target.classList.contains('gwseq-external-race-select')) return;
      var fieldWrap = e.target.closest('p');
      var autreWrap = fieldWrap ? fieldWrap.nextElementSibling : null;
      if (autreWrap && autreWrap.classList.contains('gwseq-external-race-autre-wrap')) {
        autreWrap.style.display = e.target.value === 'autre' ? '' : 'none';
      }
    });

    // Mise à jour EN DIRECT des intitulés contextuels du pedigree (correctif post-recette : un
    // premier essai sans JavaScript s'est révélé insuffisant — "Père de cet ascendant" restait
    // affiché tant que la fiche n'était pas enregistrée, malgré un nom déjà saisi). Ce bloc ne lit
    // ET n'écrit JAMAIS la valeur d'un champ Nom : il ne fait que recalculer le texte affiché
    // ailleurs (le résumé de la divulgation progressive, les libellés Père/Mère du niveau suivant)
    // à partir de sa valeur courante, jamais l'inverse. Les libellés traduits proviennent des
    // attributs data-* du conteneur .gwseq-pedigree-i18n (voir includes/cheval-pedigree.php),
    // jamais codés en dur ici, pour ne jamais désynchroniser cet affichage d'une traduction du
    // plugin. La transformation "majuscules, sans accents" reproduit côté navigateur celle du
    // serveur (gwseq_format_horse_name_display()) à titre de PRÉVISUALISATION uniquement : le
    // rendu réellement autoritaire reste celui produit par le serveur à l'enregistrement suivant.
    function gwseqPedigreeDisplayName(rawName) {
      var trimmed = (rawName || '').trim();
      if (trimmed === '') return '';
      var withoutAccents = trimmed.normalize ? trimmed.normalize('NFD').replace(/[\u0300-\u036f]/g, '') : trimmed;
      return withoutAccents.toUpperCase();
    }

    document.addEventListener('input', function (e) {
      if (!e.target.classList || !e.target.classList.contains('gwseq-external-name-input')) return;

      var i18nContainer = document.querySelector('.gwseq-pedigree-i18n');
      if (!i18nContainer) return;
      var fatherPrefix = i18nContainer.getAttribute('data-father-prefix') || '';
      var motherPrefix = i18nContainer.getAttribute('data-mother-prefix') || '';
      var summaryPrefix = i18nContainer.getAttribute('data-summary-prefix') || '';
      var fallbackName = i18nContainer.getAttribute('data-fallback-name') || '';

      var nodeWrap = e.target.closest('.gwseq-ancestor-node');
      if (!nodeWrap) return;

      var displayName = gwseqPedigreeDisplayName(e.target.value) || fallbackName;

      var summary = nodeWrap.querySelector(':scope > details > summary');
      if (summary) summary.textContent = summaryPrefix + displayName;

      var childNodes = nodeWrap.querySelectorAll(':scope > details > .gwseq-ancestor-node');
      if (childNodes[0]) {
        var fatherLabel = childNodes[0].querySelector(':scope > p > strong');
        if (fatherLabel) fatherLabel.textContent = fatherPrefix + displayName;
      }
      if (childNodes[1]) {
        var motherLabel = childNodes[1].querySelector(':scope > p > strong');
        if (motherLabel) motherLabel.textContent = motherPrefix + displayName;
      }
    });
  });
})();

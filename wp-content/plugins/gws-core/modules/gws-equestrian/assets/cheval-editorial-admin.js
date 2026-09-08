/**
 * Compteurs de caractères et listes ordonnées de courtes chaînes (Qualités, Faits marquants) —
 * Lot 2A, structuration des contenus commerciaux Cheval. JavaScript natif uniquement, aucune
 * dépendance — même style que assets/repeater-field.js (délégation d'événements sur `document`).
 *
 * Purement un confort de saisie : la validation réelle (longueur, nombre d'éléments) reste
 * appliquée côté serveur (includes/cheval-editorial.php, gwseq_set_cheval_editorial()), quel que
 * soit l'état de ce script — sans JavaScript, `maxlength` (attribut HTML natif) empêche déjà de
 * TAPER au-delà de la limite ; un contenu déjà enregistré au-delà de la limite avant ce lot reste
 * intégralement affiché et se soumet tel quel, rejeté proprement côté serveur au besoin (jamais
 * tronqué). L'ajout/la suppression/le réordonnancement des listes nécessitent en revanche ce
 * script pour fonctionner (même limite déjà assumée par le composant répétable générique pour la
 * Galerie/les Vidéos) : sans JavaScript, les lignes déjà enregistrées restent visibles et se
 * soumettent normalement, dans leur ordre existant.
 *
 * Chargé uniquement sur l'écran d'édition d'une fiche Cheval (voir l'enqueue dans
 * includes/cheval-fields.php, gwseq_enqueue_cheval_admin_assets()).
 */
(function () {
  'use strict';

  function updateCharCount(field) {
    var max = parseInt(field.getAttribute('data-gwseq-charcount'), 10);
    if (isNaN(max)) return;
    var display = field.parentElement ? field.parentElement.querySelector('.gwseq-charcount-display') : null;
    if (!display) return;
    var length = field.value.length;
    display.textContent = length + ' / ' + max;
    display.classList.toggle('gwseq-charcount-display--over', length > max);
  }

  function updateTextListCounter(input) {
    var row = input.closest('.gwseq-text-list__row');
    var counter = row ? row.querySelector('.gwseq-text-list__counter') : null;
    var list = input.closest('[data-gwseq-text-list]');
    var max = list ? parseInt(list.getAttribute('data-gwseq-max-length'), 10) : NaN;
    if (!counter || isNaN(max)) return;
    var length = input.value.length;
    counter.textContent = length + '/' + max;
    counter.classList.toggle('gwseq-text-list__counter--over', length > max);
  }

  function updateTextListAddState(list) {
    var max = parseInt(list.getAttribute('data-gwseq-max-items'), 10);
    if (isNaN(max)) return;
    var addButton = list.querySelector('.gwseq-text-list__add');
    if (!addButton) return;
    var count = list.querySelectorAll('.gwseq-text-list__row').length;
    addButton.disabled = count >= max;
  }

  document.addEventListener('input', function (event) {
    if (event.target.hasAttribute && event.target.hasAttribute('data-gwseq-charcount')) {
      updateCharCount(event.target);
    } else if (event.target.classList && event.target.classList.contains('gwseq-text-list__input')) {
      updateTextListCounter(event.target);
    }
  });

  document.addEventListener('click', function (event) {
    var addButton = event.target.closest('.gwseq-text-list__add');
    if (addButton) {
      var list = addButton.closest('[data-gwseq-text-list]');
      var template = list ? list.querySelector('.gwseq-text-list__template') : null;
      var rows = list ? list.querySelector('.gwseq-text-list__rows') : null;
      if (!list || !template || !rows) return;

      var max = parseInt(list.getAttribute('data-gwseq-max-items'), 10);
      if (!isNaN(max) && rows.querySelectorAll('.gwseq-text-list__row').length >= max) {
        event.preventDefault();
        return;
      }

      var fragment = template.content.cloneNode(true);
      rows.appendChild(fragment);
      var newRow = rows.lastElementChild;
      updateTextListAddState(list);
      var newInput = newRow ? newRow.querySelector('.gwseq-text-list__input') : null;
      if (newInput) {
        updateTextListCounter(newInput);
        newInput.focus();
      }

      event.preventDefault();
      return;
    }

    var removeButton = event.target.closest('.gwseq-text-list__remove');
    if (removeButton) {
      var removeList = removeButton.closest('[data-gwseq-text-list]');
      var row = removeButton.closest('.gwseq-text-list__row');
      if (row && row.parentNode) row.parentNode.removeChild(row);
      if (removeList) updateTextListAddState(removeList);
      event.preventDefault();
      return;
    }

    var upButton = event.target.closest('.gwseq-text-list__move-up');
    if (upButton) {
      var upRow = upButton.closest('.gwseq-text-list__row');
      var previous = upRow ? upRow.previousElementSibling : null;
      if (upRow && previous && upRow.parentNode) upRow.parentNode.insertBefore(upRow, previous);
      event.preventDefault();
      return;
    }

    var downButton = event.target.closest('.gwseq-text-list__move-down');
    if (downButton) {
      var downRow = downButton.closest('.gwseq-text-list__row');
      var next = downRow ? downRow.nextElementSibling : null;
      if (downRow && next && downRow.parentNode) downRow.parentNode.insertBefore(next, downRow);
      event.preventDefault();
    }
  });

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-gwseq-charcount]').forEach(updateCharCount);
    document.querySelectorAll('.gwseq-text-list__input').forEach(updateTextListCounter);
    document.querySelectorAll('[data-gwseq-text-list]').forEach(updateTextListAddState);
  });
})();

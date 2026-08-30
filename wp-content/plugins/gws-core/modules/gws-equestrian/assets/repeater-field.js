/**
 * Composant répétable GWS Equestrian — comportement minimal, JavaScript natif uniquement
 * (aucune dépendance, aucun framework). N'écrit jamais lui-même les données : la sauvegarde
 * réelle se fait côté serveur à l'enregistrement du formulaire (voir repeater-field.php). Ce
 * script ne fait qu'ajouter/retirer des lignes dans le DOM avant soumission.
 *
 * Chargé uniquement sur les écrans qui utilisent réellement le composant (voir la fonction
 * d'enqueue correspondante) — jamais globalement dans l'administration.
 */
(function () {
  'use strict';

  // Limite optionnelle (attribut data-gwseq-repeater-max, voir repeater-field.php, ajouté pour
  // GWS Equestrian Étape 6 — ex. 10 vidéos maximum) : UNIQUEMENT une aide UX qui désactive le
  // bouton d'ajout une fois le nombre de lignes atteint — la garantie réelle reste la sanitation
  // serveur, appliquée quel que soit l'état de ce script. Ne fait jamais disparaître une ligne
  // existante, ne modifie jamais son contenu.
  function updateAddButtonState(container) {
    var max = parseInt(container.getAttribute('data-gwseq-repeater-max'), 10);
    if (isNaN(max)) return;
    var addButton = container.querySelector('.gwseq-repeater__add');
    if (!addButton) return;
    var rowCount = container.querySelectorAll('.gwseq-repeater__rows > .gwseq-repeater__row').length;
    addButton.disabled = rowCount >= max;
  }

  document.addEventListener('click', function (event) {
    var addButton = event.target.closest('.gwseq-repeater__add');
    if (addButton) {
      var container = addButton.closest('.gwseq-repeater');
      if (!container) return;
      var template = container.querySelector('.gwseq-repeater__template');
      var rows = container.querySelector('.gwseq-repeater__rows');
      if (!template || !rows) return;

      var maxRows = parseInt(container.getAttribute('data-gwseq-repeater-max'), 10);
      if (!isNaN(maxRows) && rows.querySelectorAll('.gwseq-repeater__row').length >= maxRows) {
        event.preventDefault();
        return;
      }

      // Chaque ligne doit porter un index unique partagé par toutes ses colonnes (voir
      // repeater-field.php) : le gabarit contient le jeton littéral "__INDEX__" à la place de cet
      // index, remplacé ici par un compteur qui ne fait qu'augmenter (jamais réutilisé, même après
      // suppression d'une ligne) pour ne jamais entrer en collision avec une ligne existante.
      var nextIndex = parseInt(container.getAttribute('data-gwseq-next-index'), 10);
      if (isNaN(nextIndex)) nextIndex = 0;

      var fragment = template.content.cloneNode(true);
      fragment.querySelectorAll('[name]').forEach(function (field) {
        field.name = field.name.replace('__INDEX__', String(nextIndex));
      });
      container.setAttribute('data-gwseq-next-index', String(nextIndex + 1));

      rows.appendChild(fragment);
      var newRow = rows.lastElementChild;
      var firstField = newRow ? newRow.querySelector('input, textarea') : null;
      if (firstField) firstField.focus();

      updateAddButtonState(container);
      event.preventDefault();
      return;
    }

    var removeButton = event.target.closest('.gwseq-repeater__remove');
    if (removeButton) {
      var row = removeButton.closest('.gwseq-repeater__row');
      var repeater = removeButton.closest('.gwseq-repeater');
      if (row && row.parentNode) row.parentNode.removeChild(row);
      var addButtonToFocus = repeater ? repeater.querySelector('.gwseq-repeater__add') : null;
      if (repeater) updateAddButtonState(repeater);
      if (addButtonToFocus) addButtonToFocus.focus();

      event.preventDefault();
    }
  });

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.gwseq-repeater[data-gwseq-repeater-max]').forEach(updateAddButtonState);
  });
})();

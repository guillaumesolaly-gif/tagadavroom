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

  document.addEventListener('click', function (event) {
    var addButton = event.target.closest('.gwseq-repeater__add');
    if (addButton) {
      var container = addButton.closest('.gwseq-repeater');
      if (!container) return;
      var template = container.querySelector('.gwseq-repeater__template');
      var rows = container.querySelector('.gwseq-repeater__rows');
      if (!template || !rows) return;

      rows.appendChild(template.content.cloneNode(true));
      var newRow = rows.lastElementChild;
      var firstField = newRow ? newRow.querySelector('input, textarea') : null;
      if (firstField) firstField.focus();

      event.preventDefault();
      return;
    }

    var removeButton = event.target.closest('.gwseq-repeater__remove');
    if (removeButton) {
      var row = removeButton.closest('.gwseq-repeater__row');
      var repeater = removeButton.closest('.gwseq-repeater');
      if (row && row.parentNode) row.parentNode.removeChild(row);
      var addButtonToFocus = repeater ? repeater.querySelector('.gwseq-repeater__add') : null;
      if (addButtonToFocus) addButtonToFocus.focus();

      event.preventDefault();
    }
  });
})();

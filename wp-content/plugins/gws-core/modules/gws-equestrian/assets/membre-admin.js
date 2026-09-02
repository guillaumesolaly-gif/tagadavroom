/**
 * Écran d'édition d'une fiche membre — affichage conditionnel du champ "Préciser" de Langues,
 * révélé uniquement quand la case "Autre" est cochée. Même technique que assets/cheval-admin.js
 * (JavaScript natif, aucune dépendance, purement de l'affichage) : le serveur reste seul
 * autoritaire sur ce qui est réellement enregistré (voir gwseq_sanitize_membre_langues_input()
 * dans includes/membre-fields.php) — sans JavaScript, le champ "Préciser" reste simplement toujours
 * visible, la fiche reste utilisable.
 */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    var autreCheckbox = document.querySelector('input[name="_gwseq_membre_langues[]"][value="autre"]');
    if (!autreCheckbox) return;

    function setVisible(selector, visible) {
      var field = document.querySelector(selector);
      if (field) field.style.display = visible ? '' : 'none';
    }

    function applyLangueAutre() {
      setVisible('[data-gwseq-membre-fields="langue-autre-precision"]', autreCheckbox.checked);
    }

    autreCheckbox.addEventListener('change', applyLangueAutre);
    applyLangueAutre();
  });
})();

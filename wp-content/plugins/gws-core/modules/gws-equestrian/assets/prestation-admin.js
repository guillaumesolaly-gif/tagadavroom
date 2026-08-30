/**
 * Écran d'édition d'une Prestation — affichage conditionnel des champs de tarification selon le
 * mode choisi (prix unique / cheval-poney / sur devis) et l'unité (affiche "Préciser l'unité"
 * uniquement pour "Autre"). Solution locale ciblée sur ces deux sélecteurs précis — volontairement
 * pas un moteur générique de champs conditionnels. JavaScript natif, aucune dépendance. La
 * sauvegarde réelle reste entièrement gérée côté serveur (voir prestation-fields.php) : ce script
 * ne fait qu'afficher/masquer des blocs déjà présents dans le DOM.
 */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    var modeSelect = document.getElementById('gwseq-tarif-mode');
    var uniteSelect = document.getElementById('gwseq-tarif-unite');

    function applyMode() {
      if (!modeSelect) return;
      var mode = modeSelect.value;
      document.querySelectorAll('[data-gwseq-tarif-fields]').forEach(function (el) {
        var modes = el.getAttribute('data-gwseq-tarif-fields').split(' ');
        el.style.display = modes.indexOf(mode) !== -1 ? '' : 'none';
      });
    }

    function applyUnite() {
      if (!uniteSelect) return;
      var field = document.querySelector('[data-gwseq-tarif-fields="unite-autre"]');
      if (field) field.style.display = uniteSelect.value === 'autre' ? '' : 'none';
    }

    if (modeSelect) {
      modeSelect.addEventListener('change', applyMode);
      applyMode();
    }
    if (uniteSelect) {
      uniteSelect.addEventListener('change', applyUnite);
      applyUnite();
    }
  });
})();

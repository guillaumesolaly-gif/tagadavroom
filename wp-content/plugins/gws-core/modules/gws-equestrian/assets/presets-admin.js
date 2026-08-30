/**
 * Écran "Ajouter une prestation" — le bouton "Préremplir depuis ce modèle" recharge simplement la
 * page avec le modèle choisi en paramètre d'URL ; le préremplissage réel (titre, unité suggérée)
 * est fait côté serveur au rendu suivant (voir includes/presets.php). Ce script ne modifie et ne
 * sauvegarde aucune donnée lui-même.
 */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    var applyButton = document.getElementById('gwseq-preset-apply');
    var select = document.getElementById('gwseq-preset-select');
    if (!applyButton || !select) return;

    applyButton.addEventListener('click', function () {
      if (!select.value) return;
      var url = new URL(window.location.href);
      url.searchParams.set('gwseq_preset', select.value);
      window.location.href = url.toString();
    });
  });
})();

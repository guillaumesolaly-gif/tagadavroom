/**
 * Galerie photos de la fiche Cheval (Étape 6) — sélection via la médiathèque native de WordPress
 * (wp.media(), chargée par wp_enqueue_media() côté serveur, voir includes/cheval-media.php).
 * Aucun système d'upload parallèle : ce script ne fait qu'ajouter/retirer/réordonner des
 * références (attachment_id) déjà présentes dans le DOM, exactement comme
 * assets/repeater-field.js le fait pour les vidéos — la sauvegarde réelle reste entièrement gérée
 * côté serveur à l'enregistrement du formulaire (gwseq_sanitize_cheval_galerie()).
 *
 * Retirer une image de la liste ne supprime jamais le média de la médiathèque : ce script ne
 * fait disparaître qu'un <li> et son champ caché, jamais un appel à une quelconque suppression.
 * Réordonner (haut/bas) ne fait que déplacer l'élément dans le DOM — l'ordre de soumission des
 * champs cachés _gwseq_galerie[] suit naturellement l'ordre du DOM, sans JavaScript dédié à la
 * sérialisation de cet ordre.
 *
 * Chargé uniquement sur l'écran d'édition d'une fiche cheval (voir la fonction d'enqueue
 * correspondante) — jamais globalement dans l'administration.
 */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    var container = document.querySelector('.gwseq-galerie');
    if (!container) return;

    var list = container.querySelector('.gwseq-galerie__list');
    var template = container.querySelector('.gwseq-galerie__template');
    var addButton = container.querySelector('.gwseq-galerie__add');
    var max = parseInt(container.getAttribute('data-gwseq-galerie-max'), 10);
    if (isNaN(max)) max = 9;

    function itemCount() {
      return list.querySelectorAll('.gwseq-galerie__item').length;
    }

    function currentIds() {
      return Array.prototype.map.call(list.querySelectorAll('.gwseq-galerie__item'), function (item) {
        return item.getAttribute('data-attachment-id');
      });
    }

    function updateAddButtonState() {
      if (addButton) addButton.disabled = itemCount() >= max;
    }

    // Jamais de doublon dans la galerie : une image déjà présente n'est simplement pas ajoutée
    // une seconde fois — ni erreur, ni duplication silencieuse de la référence.
    function addAttachment(attachment) {
      if (!template || !list) return;
      if (itemCount() >= max) return;
      if (currentIds().indexOf(String(attachment.id)) !== -1) return;

      var fragment = template.content.cloneNode(true);
      var item = fragment.querySelector('.gwseq-galerie__item');
      item.setAttribute('data-attachment-id', String(attachment.id));

      var img = fragment.querySelector('.gwseq-galerie__thumb');
      if (img) {
        var thumbUrl = (attachment.sizes && attachment.sizes.thumbnail) ? attachment.sizes.thumbnail.url : attachment.url;
        img.src = thumbUrl || '';
      }

      var hiddenInput = fragment.querySelector('input[type="hidden"]');
      if (hiddenInput) hiddenInput.value = attachment.id;

      list.appendChild(fragment);
      updateAddButtonState();
    }

    if (addButton) {
      addButton.addEventListener('click', function (event) {
        event.preventDefault();
        if (typeof wp === 'undefined' || !wp.media) return;
        if (itemCount() >= max) return;

        var frame = wp.media({
          title: addButton.getAttribute('data-gwseq-media-title') || addButton.textContent,
          multiple: true,
          library: { type: 'image' }
        });

        frame.on('select', function () {
          var selection = frame.state().get('selection');
          selection.each(function (attachment) {
            addAttachment(attachment.toJSON());
          });
        });

        frame.open();
      });
    }

    // Écoute déléguée (retrait + réordonnancement) : fonctionne aussi pour les items ajoutés
    // dynamiquement après le chargement initial de la page.
    container.addEventListener('click', function (event) {
      var removeButton = event.target.closest('.gwseq-galerie__remove');
      if (removeButton) {
        event.preventDefault();
        var itemToRemove = removeButton.closest('.gwseq-galerie__item');
        if (itemToRemove && itemToRemove.parentNode) itemToRemove.parentNode.removeChild(itemToRemove);
        updateAddButtonState();
        return;
      }

      var upButton = event.target.closest('.gwseq-galerie__move-up');
      if (upButton) {
        event.preventDefault();
        var itemUp = upButton.closest('.gwseq-galerie__item');
        var previousItem = itemUp ? itemUp.previousElementSibling : null;
        if (itemUp && previousItem) itemUp.parentNode.insertBefore(itemUp, previousItem);
        return;
      }

      var downButton = event.target.closest('.gwseq-galerie__move-down');
      if (downButton) {
        event.preventDefault();
        var itemDown = downButton.closest('.gwseq-galerie__item');
        var nextItem = itemDown ? itemDown.nextElementSibling : null;
        if (itemDown && nextItem) itemDown.parentNode.insertBefore(nextItem, itemDown);
        return;
      }
    });

    updateAddButtonState();
  });
})();

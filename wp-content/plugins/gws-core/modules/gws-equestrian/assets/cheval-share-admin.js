/**
 * Écran « Chevaux → Partager » (§5-9/§14-18/§26-28 de la demande) — JavaScript natif, aucune
 * dépendance. Ne construit AUCUN texte commercial lui-même : tous les libellés (identité, origines,
 * accroche, vidéos...) viennent déjà composés du serveur (includes/cheval-share.php via
 * includes/cheval-share-admin.php) — ce script ne fait que les afficher comme des cases à cocher,
 * gérer la sélection, et demander au serveur le message final déjà composé (§4 : une seule fonction
 * de composition, jamais une reconstruction séparée par canal).
 *
 * WhatsApp/SMS/Copier (§17-18) n'envoient jamais rien eux-mêmes : ils ouvrent l'application choisie
 * (ou copient le texte) avec le DERNIER message renvoyé par le serveur — les trois actions
 * consomment donc littéralement le même texte, jamais trois compositions différentes.
 */
(function () {
  'use strict';

  var config = window.gwseqPartager || {};
  var i18n = config.i18n || {};

  var SEARCH_DEBOUNCE_MS = 300;
  var PREVIEW_DEBOUNCE_MS = 350;

  function t(key, fallback) {
    return i18n[key] || fallback || key;
  }

  function debounce(fn, delay) {
    var timer = null;
    return function () {
      var args = arguments;
      var context = this;
      if (timer) window.clearTimeout(timer);
      timer = window.setTimeout(function () { fn.apply(context, args); }, delay);
    };
  }

  function ajaxPost(action, params) {
    var formData = new window.FormData();
    formData.append('action', action);
    formData.append('nonce', config.nonce);
    Object.keys(params || {}).forEach(function (key) {
      var value = params[key];
      if (Array.isArray(value)) {
        value.forEach(function (item) { formData.append(key + '[]', item); });
      } else if (value && typeof value === 'object') {
        Object.keys(value).forEach(function (subKey) {
          var subValue = value[subKey];
          if (Array.isArray(subValue)) {
            subValue.forEach(function (item) { formData.append(key + '[' + subKey + '][]', item); });
          } else {
            formData.append(key + '[' + subKey + ']', subValue);
          }
        });
      } else {
        formData.append(key, value);
      }
    });
    return window.fetch(config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: formData })
      .then(function (response) { return response.json(); });
  }

  function clearNode(node) {
    while (node.firstChild) node.removeChild(node.firstChild);
  }

  function el(tag, className, text) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined && text !== null) node.textContent = text;
    return node;
  }

  /* -------------------------------------------------------------------------------------------
   * Écran de recherche.
   * ----------------------------------------------------------------------------------------- */

  function renderResultRow(row, onShare) {
    var li = el('li', 'gwseq-partager-result');

    var photo = document.createElement('img');
    photo.className = 'gwseq-partager-result__photo';
    photo.alt = '';
    photo.src = row.photo_url || '';
    li.appendChild(photo);

    var info = el('div', 'gwseq-partager-result__info');
    info.appendChild(el('strong', 'gwseq-partager-result__nom', row.nom));
    if (row.sous_titre) info.appendChild(el('span', 'gwseq-partager-result__sous-titre', row.sous_titre));
    li.appendChild(info);

    var button = el('button', 'button button-primary gwseq-partager-result__button', t('share', 'Partager'));
    button.type = 'button';
    button.addEventListener('click', function () { onShare(row.id); });
    li.appendChild(button);

    return li;
  }

  function initSearchScreen(root, onShare) {
    clearNode(root);
    var wrapper = el('div', 'gwseq-partager-search');

    var label = el('label', 'screen-reader-text', t('searchPlaceholder', 'Rechercher un cheval...'));
    label.setAttribute('for', 'gwseq-partager-search-input');
    wrapper.appendChild(label);

    var input = document.createElement('input');
    input.type = 'search';
    input.id = 'gwseq-partager-search-input';
    input.className = 'gwseq-partager-search__input';
    input.placeholder = t('searchPlaceholder', 'Rechercher un cheval...');
    wrapper.appendChild(input);

    var resultsList = el('ul', 'gwseq-partager-results');
    resultsList.setAttribute('aria-live', 'polite');
    wrapper.appendChild(resultsList);

    root.appendChild(wrapper);

    function renderResults(rows) {
      clearNode(resultsList);
      if (!rows.length) {
        resultsList.appendChild(el('li', 'gwseq-partager-no-results', t('noResults', 'Aucun cheval trouvé.')));
        return;
      }
      rows.forEach(function (row) { resultsList.appendChild(renderResultRow(row, onShare)); });
    }

    renderResults(config.recents || []);

    var search = debounce(function () {
      ajaxPost('gwseq_partager_search_cheval', { s: input.value }).then(function (json) {
        if (json && json.success) renderResults(json.data.resultats || []);
      });
    }, SEARCH_DEBOUNCE_MS);

    input.addEventListener('input', search);
  }

  /* -------------------------------------------------------------------------------------------
   * Écran de composition.
   * ----------------------------------------------------------------------------------------- */

  var ITEM_ORDER = ['identite', 'origines', 'taille_indice', 'prix', 'accroche'];

  function currentSelection(root) {
    var items = [];
    root.querySelectorAll('[data-item-key]').forEach(function (input) {
      if (input.checked) items.push(input.getAttribute('data-item-key'));
    });
    var videos = [];
    root.querySelectorAll('[data-video-index]').forEach(function (input) {
      if (input.checked) videos.push(parseInt(input.getAttribute('data-video-index'), 10));
    });
    var ficheInput = root.querySelector('#gwseq-partager-fiche');
    var messageInput = root.querySelector('#gwseq-partager-message-personnel');
    return {
      items: items,
      videos: videos,
      fiche: !!(ficheInput && ficheInput.checked),
      message_personnel: messageInput ? messageInput.value : '',
    };
  }

  function initComposeScreen(root, chevalId, shareable, onBack) {
    clearNode(root);
    var wrapper = el('div', 'gwseq-partager-compose');

    var backButton = el('button', 'gwseq-partager-back', t('back', '← Choisir un autre cheval'));
    backButton.type = 'button';
    backButton.addEventListener('click', onBack);
    wrapper.appendChild(backButton);

    var horseHeader = el('div', 'gwseq-partager-horse');
    var photo = document.createElement('img');
    photo.className = 'gwseq-partager-horse__photo';
    photo.alt = '';
    photo.src = shareable.photo_url || '';
    horseHeader.appendChild(photo);
    horseHeader.appendChild(el('strong', 'gwseq-partager-horse__nom', shareable.nom_affiche || shareable.nom));
    wrapper.appendChild(horseHeader);

    // --- Message personnel ---
    var messageField = el('div', 'gwseq-partager-field');
    var messageLabel = el('label', null, t('personalMessageLabel', 'Message personnel (facultatif)'));
    messageLabel.setAttribute('for', 'gwseq-partager-message-personnel');
    messageField.appendChild(messageLabel);
    var messageInput = document.createElement('textarea');
    messageInput.id = 'gwseq-partager-message-personnel';
    messageInput.rows = 2;
    messageInput.placeholder = t('personalMessagePlaceholder', '');
    messageField.appendChild(messageInput);
    wrapper.appendChild(messageField);

    // --- Informations à envoyer ---
    var itemsFieldset = document.createElement('fieldset');
    itemsFieldset.className = 'gwseq-partager-fieldset';
    itemsFieldset.appendChild(el('legend', null, t('infoToSendLabel', 'Informations à envoyer')));
    var itemsContainer = el('div', 'gwseq-partager-items');
    ITEM_ORDER.forEach(function (key) {
      var item = shareable.items && shareable.items[key];
      if (!item) return;
      var checkboxLabel = el('label', 'gwseq-partager-checkbox');
      var checkbox = document.createElement('input');
      checkbox.type = 'checkbox';
      checkbox.setAttribute('data-item-key', key);
      checkbox.checked = !!item.default_checked;
      checkboxLabel.appendChild(checkbox);
      checkboxLabel.appendChild(el('span', null, item.label));
      itemsContainer.appendChild(checkboxLabel);
    });
    itemsFieldset.appendChild(itemsContainer);
    wrapper.appendChild(itemsFieldset);

    // --- Vidéos ---
    if (shareable.videos && shareable.videos.length) {
      var videosFieldset = document.createElement('fieldset');
      videosFieldset.className = 'gwseq-partager-fieldset';
      videosFieldset.appendChild(el('legend', null, t('videosLabel', 'Vidéos')));
      var videosContainer = el('div', 'gwseq-partager-items');
      shareable.videos.forEach(function (video) {
        var checkboxLabel = el('label', 'gwseq-partager-checkbox');
        var checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.setAttribute('data-video-index', video.index);
        checkbox.checked = !!video.default_checked;
        checkboxLabel.appendChild(checkbox);
        checkboxLabel.appendChild(el('span', null, video.label));
        videosContainer.appendChild(checkboxLabel);
      });
      videosFieldset.appendChild(videosContainer);
      wrapper.appendChild(videosFieldset);
    }

    // --- Fiche complète ---
    if (shareable.fiche_url) {
      var ficheRow = el('div', 'gwseq-partager-fiche-row');
      var ficheLabel = el('label', 'gwseq-partager-checkbox');
      var ficheCheckbox = document.createElement('input');
      ficheCheckbox.type = 'checkbox';
      ficheCheckbox.id = 'gwseq-partager-fiche';
      ficheCheckbox.checked = !!shareable.fiche_default_checked;
      ficheLabel.appendChild(ficheCheckbox);
      ficheLabel.appendChild(el('span', null, t('ficheLabel', 'Ajouter la fiche complète')));
      ficheRow.appendChild(ficheLabel);
      wrapper.appendChild(ficheRow);
    }

    // --- Aperçu ---
    var previewSection = el('div', 'gwseq-partager-preview');
    previewSection.appendChild(el('h2', null, t('previewLabel', 'Aperçu du message')));
    var previewText = document.createElement('pre');
    previewText.className = 'gwseq-partager-preview__text';
    previewText.setAttribute('aria-live', 'polite');
    previewSection.appendChild(previewText);
    wrapper.appendChild(previewSection);

    // --- Actions ---
    var actions = el('div', 'gwseq-partager-actions');
    var actionDefs = [
      { key: 'whatsapp', label: t('whatsapp', 'WhatsApp') },
      { key: 'sms', label: t('sms', 'SMS / Messages') },
      { key: 'copy', label: t('copy', 'Copier') },
    ];
    var actionButtons = {};
    actionDefs.forEach(function (def) {
      var button = el('button', 'button button-hero gwseq-partager-action gwseq-partager-action--' + def.key, def.label);
      button.type = 'button';
      actions.appendChild(button);
      actionButtons[def.key] = button;
    });
    wrapper.appendChild(actions);

    var copyFeedback = el('p', 'gwseq-partager-copy-feedback', t('copied', 'Message copié'));
    copyFeedback.setAttribute('aria-live', 'polite');
    copyFeedback.hidden = true;
    wrapper.appendChild(copyFeedback);

    root.appendChild(wrapper);

    var currentMessage = '';

    function refreshPreview() {
      previewText.textContent = t('loading', 'Chargement…');
      ajaxPost('gwseq_partager_build_message', { cheval_id: chevalId, selection: currentSelection(wrapper) }).then(function (json) {
        if (json && json.success) {
          currentMessage = json.data.message || '';
          previewText.textContent = currentMessage;
        }
      });
    }

    var debouncedRefresh = debounce(refreshPreview, PREVIEW_DEBOUNCE_MS);
    wrapper.addEventListener('input', debouncedRefresh);
    wrapper.addEventListener('change', debouncedRefresh);
    refreshPreview();

    actionButtons.whatsapp.addEventListener('click', function () {
      window.open('https://wa.me/?text=' + encodeURIComponent(currentMessage), '_blank');
    });
    actionButtons.sms.addEventListener('click', function () {
      window.location.href = 'sms:?body=' + encodeURIComponent(currentMessage);
    });
    actionButtons.copy.addEventListener('click', function () {
      copyTextToClipboard(currentMessage).then(function () {
        copyFeedback.hidden = false;
        window.setTimeout(function () { copyFeedback.hidden = true; }, 4000);
      });
    });
  }

  function copyTextToClipboard(text) {
    if (window.navigator.clipboard && window.navigator.clipboard.writeText) {
      return window.navigator.clipboard.writeText(text).catch(function () { return copyTextToClipboardFallback(text); });
    }
    return copyTextToClipboardFallback(text);
  }

  function copyTextToClipboardFallback(text) {
    return new window.Promise(function (resolve) {
      var textarea = document.createElement('textarea');
      textarea.value = text;
      textarea.style.position = 'fixed';
      textarea.style.opacity = '0';
      document.body.appendChild(textarea);
      textarea.focus();
      textarea.select();
      try { document.execCommand('copy'); } catch (e) { /* navigateur trop ancien : geste ignoré, jamais bloquant */ }
      document.body.removeChild(textarea);
      resolve();
    });
  }

  /* -------------------------------------------------------------------------------------------
   * Point d'entrée.
   * ----------------------------------------------------------------------------------------- */

  function init() {
    var root = document.getElementById('gwseq-partager-app');
    if (!root) return;

    function showSearch() {
      initSearchScreen(root, function (id) { loadCheval(id); });
    }

    function loadCheval(id) {
      clearNode(root);
      root.appendChild(el('p', 'gwseq-partager-loading', t('loading', 'Chargement…')));
      ajaxPost('gwseq_partager_get_cheval', { cheval_id: id }).then(function (json) {
        if (json && json.success) {
          initComposeScreen(root, id, json.data.cheval, showSearch);
        } else {
          showSearch();
        }
      });
    }

    var preselectedId = parseInt(root.getAttribute('data-gwseq-preselected-id'), 10);
    if (preselectedId) {
      loadCheval(preselectedId);
    } else {
      showSearch();
    }
  }

  document.addEventListener('DOMContentLoaded', init);
})();

/**
 * Écran « Chevaux → Sélections » (Suite V1 « Partager & vendre », Lot 2A) — JavaScript natif,
 * aucune dépendance, même architecture que assets/cheval-share-admin.js (helpers dupliqués
 * volontairement : chaque écran BO de ce module charge son propre script indépendant, jamais de
 * module JS partagé entre écrans — cohérent avec le reste du projet).
 *
 * Deux vues, jamais deux écrans séparés (une seule page `gwseq-selections-app`) : la LISTE des
 * sélections déjà créées (par défaut), et la CRÉATION d'une nouvelle sélection (recherche/filtres
 * réutilisés de l'écran « Partager », sélection multiple, ordre, titre). Après création réussie,
 * rechargement complet de la page (liste) — le serveur reste la seule source de vérité affichée,
 * jamais un état recomposé côté client.
 */
(function () {
  'use strict';

  var config = window.gwseqSelections || {};
  var i18n = config.i18n || {};

  var SEARCH_DEBOUNCE_MS = 300;

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
        Object.keys(value).forEach(function (subKey) { formData.append(key + '[' + subKey + ']', value[subKey]); });
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

  /**
   * Même vignette de remplacement neutre que l'écran « Partager » (assets/gws-media-placeholder.css,
   * gwseq_render_media_placeholder() côté PHP) — jamais un `<img>` au `src` vide/introuvable.
   */
  function renderHorsePhoto(url, sizeClassName) {
    if (url) {
      var img = document.createElement('img');
      img.className = sizeClassName;
      img.alt = '';
      img.src = url;
      return img;
    }
    var placeholder = el('div', sizeClassName + ' gwseq-media-placeholder');
    placeholder.setAttribute('aria-hidden', 'true');
    placeholder.appendChild(el('span', 'dashicons dashicons-pets'));
    return placeholder;
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
   * Vue liste (§13 de la demande, dans la limite du Lot 2A).
   * ----------------------------------------------------------------------------------------- */

  function renderSelectionRow(row) {
    var tr = document.createElement('tr');

    var tdTitre = document.createElement('td');
    tdTitre.appendChild(el('strong', null, row.titre));
    tr.appendChild(tdTitre);

    var tdDate = document.createElement('td');
    tdDate.textContent = row.date;
    tr.appendChild(tdDate);

    var tdCompte = document.createElement('td');
    tdCompte.textContent = row.chevaux_diffusables + ' / ' + row.total_chevaux;
    tr.appendChild(tdCompte);

    var tdLien = document.createElement('td');
    if (row.token_actif && row.url) {
      var linkInput = document.createElement('input');
      linkInput.type = 'text';
      linkInput.readOnly = true;
      linkInput.value = row.url;
      linkInput.className = 'gwseq-selections-link-input';
      linkInput.addEventListener('click', function () { linkInput.select(); });
      tdLien.appendChild(linkInput);
      var copyButton = el('button', 'button', t('copyLink', 'Copier le lien'));
      copyButton.type = 'button';
      copyButton.addEventListener('click', function () {
        copyTextToClipboard(row.url).then(function () {
          copyButton.textContent = t('copied', 'Lien copié');
          window.setTimeout(function () { copyButton.textContent = t('copyLink', 'Copier le lien'); }, 3000);
        });
      });
      tdLien.appendChild(copyButton);
    } else {
      tdLien.appendChild(el('span', 'description', t('revoked', 'Lien révoqué')));
    }
    tr.appendChild(tdLien);

    var tdActions = document.createElement('td');
    if (row.token_actif) {
      var revokeLink = el('a', 'button', t('revoke', 'Révoquer'));
      revokeLink.href = row.url_revoquer;
      revokeLink.addEventListener('click', function (event) {
        if (!window.confirm(t('confirmRevoke', ''))) event.preventDefault();
      });
      tdActions.appendChild(revokeLink);
    } else {
      var regenerateLink = el('a', 'button button-primary', t('regenerate', 'Régénérer'));
      regenerateLink.href = row.url_regenerer;
      regenerateLink.addEventListener('click', function (event) {
        if (!window.confirm(t('confirmRegenerate', ''))) event.preventDefault();
      });
      tdActions.appendChild(regenerateLink);
    }
    tr.appendChild(tdActions);

    return tr;
  }

  function initListView(root, onCreateNew) {
    clearNode(root);
    var wrapper = el('div', 'gwseq-selections-list');

    var newButton = el('button', 'button button-primary', t('newSelection', '+ Nouvelle sélection'));
    newButton.type = 'button';
    newButton.addEventListener('click', onCreateNew);
    wrapper.appendChild(newButton);

    var rows = config.existantes || [];
    if (!rows.length) {
      wrapper.appendChild(el('p', 'description', t('emptyList', 'Aucune sélection créée pour le moment.')));
    } else {
      var table = document.createElement('table');
      table.className = 'widefat striped gwseq-selections-table';
      var thead = document.createElement('thead');
      var headRow = document.createElement('tr');
      [
        t('columnTitle', 'Titre'),
        t('columnDate', 'Date'),
        t('columnChevaux', 'Chevaux diffusables'),
        t('columnLink', 'Lien'),
        t('columnActions', 'Actions'),
      ].forEach(function (label) { headRow.appendChild(el('th', null, label)); });
      thead.appendChild(headRow);
      table.appendChild(thead);

      var tbody = document.createElement('tbody');
      rows.forEach(function (row) { tbody.appendChild(renderSelectionRow(row)); });
      table.appendChild(tbody);

      wrapper.appendChild(table);
    }

    root.appendChild(wrapper);
  }

  /* -------------------------------------------------------------------------------------------
   * Vue création (§7-8 de la demande) : recherche/filtres réutilisés de l'écran « Partager »,
   * sélection multiple (case à cocher), ordre explicite (Monter/Descendre), titre facultatif.
   * ----------------------------------------------------------------------------------------- */

  function buildSelect(idSuffix, labelText, allLabel, options) {
    var wrapperEl = el('div', 'gwseq-selections-filter');
    var labelEl = el('label', 'gwseq-selections-filter__label', labelText);
    labelEl.setAttribute('for', 'gwseq-selections-filter-' + idSuffix);
    wrapperEl.appendChild(labelEl);

    var select = document.createElement('select');
    select.id = 'gwseq-selections-filter-' + idSuffix;
    select.className = 'gwseq-selections-filter__select';
    var allOption = document.createElement('option');
    allOption.value = '';
    allOption.textContent = allLabel;
    select.appendChild(allOption);
    Object.keys(options || {}).forEach(function (value) {
      var option = document.createElement('option');
      option.value = value;
      option.textContent = options[value];
      select.appendChild(option);
    });
    wrapperEl.appendChild(select);
    return { wrapper: wrapperEl, select: select };
  }

  function formatSelectedCount(count) {
    if (count === 0) return t('selectedCountZero', 'Aucun cheval sélectionné');
    if (count === 1) return t('selectedCountOne', '1 cheval sélectionné');
    return t('selectedCountMany', '%d chevaux sélectionnés').replace('%d', String(count));
  }

  function initCreateView(root, onBackToList) {
    clearNode(root);
    var wrapper = el('div', 'gwseq-selections-create');

    var backButton = el('button', 'gwseq-selections-back', t('backToList', '← Retour aux sélections'));
    backButton.type = 'button';
    backButton.addEventListener('click', onBackToList);
    wrapper.appendChild(backButton);

    // État de la sélection en cours (ordonné, dédoublonné) — l'ordre du tableau EST l'ordre final
    // envoyé au serveur (§8 : "l'ordre peut avoir une importance commerciale").
    var selected = []; // [{id, nom, photo_url, sous_titre}]

    // --- Panneau "sélection en cours" ---
    var selectedPanel = el('div', 'gwseq-selections-selected');
    var selectedCountEl = el('p', 'gwseq-selections-selected__count', formatSelectedCount(0));
    selectedPanel.appendChild(selectedCountEl);
    var selectedList = el('ul', 'gwseq-selections-selected__list');
    selectedPanel.appendChild(selectedList);

    var titleField = el('div', 'gwseq-selections-field');
    var titleLabel = el('label', null, t('titleLabel', 'Titre (facultatif)'));
    titleLabel.setAttribute('for', 'gwseq-selections-title');
    titleField.appendChild(titleLabel);
    var titleInput = document.createElement('input');
    titleInput.type = 'text';
    titleInput.id = 'gwseq-selections-title';
    titleInput.placeholder = t('titlePlaceholder', '');
    titleField.appendChild(titleInput);
    selectedPanel.appendChild(titleField);

    var createButton = el('button', 'button button-primary button-hero', t('createSelection', 'Créer la sélection'));
    createButton.type = 'button';
    createButton.disabled = true;
    selectedPanel.appendChild(createButton);

    var createError = el('p', 'gwseq-selections-create-error');
    createError.hidden = true;
    selectedPanel.appendChild(createError);

    function renderSelectedList() {
      clearNode(selectedList);
      selected.forEach(function (row, index) {
        var li = el('li', 'gwseq-selections-selected__item');
        li.appendChild(renderHorsePhoto(row.photo_url, 'gwseq-selections-selected__photo'));
        var info = el('div', 'gwseq-selections-selected__info');
        info.appendChild(el('strong', null, row.nom));
        if (row.sous_titre) info.appendChild(el('span', 'gwseq-selections-selected__sous-titre', row.sous_titre));
        li.appendChild(info);

        var controls = el('div', 'gwseq-selections-selected__controls');
        var upButton = el('button', 'button', '↑');
        upButton.type = 'button';
        upButton.setAttribute('aria-label', t('up', 'Monter'));
        upButton.disabled = index === 0;
        upButton.addEventListener('click', function () {
          var tmp = selected[index - 1];
          selected[index - 1] = selected[index];
          selected[index] = tmp;
          renderSelectedList();
        });
        controls.appendChild(upButton);

        var downButton = el('button', 'button', '↓');
        downButton.type = 'button';
        downButton.setAttribute('aria-label', t('down', 'Descendre'));
        downButton.disabled = index === selected.length - 1;
        downButton.addEventListener('click', function () {
          var tmp = selected[index + 1];
          selected[index + 1] = selected[index];
          selected[index] = tmp;
          renderSelectedList();
        });
        controls.appendChild(downButton);

        var removeButton = el('button', 'button', t('remove', 'Retirer'));
        removeButton.type = 'button';
        removeButton.addEventListener('click', function () {
          selected = selected.filter(function (item) { return item.id !== row.id; });
          renderSelectedList();
          syncCheckboxes();
        });
        controls.appendChild(removeButton);

        li.appendChild(controls);
        selectedList.appendChild(li);
      });

      selectedCountEl.textContent = formatSelectedCount(selected.length);
      createButton.disabled = selected.length === 0;
    }

    // --- Recherche/filtres (repris de l'écran « Partager », §7) ---
    var searchWrapper = el('div', 'gwseq-selections-search');

    var searchLabel = el('label', 'screen-reader-text', t('searchPlaceholder', 'Rechercher un cheval...'));
    searchLabel.setAttribute('for', 'gwseq-selections-search-input');
    searchWrapper.appendChild(searchLabel);

    var searchInput = document.createElement('input');
    searchInput.type = 'search';
    searchInput.id = 'gwseq-selections-search-input';
    searchInput.className = 'gwseq-selections-search__input';
    searchInput.placeholder = t('searchPlaceholder', 'Rechercher un cheval...');
    searchWrapper.appendChild(searchInput);

    var filtersConfig = config.filters || {};
    var filtersRow = el('div', 'gwseq-selections-filters');

    var diffusionFilter = buildSelect('diffusion', t('diffusionFilterLabel', 'État de diffusion'), t('allDiffusion', 'Tous les états de diffusion'), filtersConfig.diffusion);
    filtersRow.appendChild(diffusionFilter.wrapper);

    var sexeFilter = buildSelect('sexe', t('sexeFilterLabel', 'Sexe'), t('allSexe', 'Tous'), filtersConfig.sexe);
    filtersRow.appendChild(sexeFilter.wrapper);

    var statutFilter = buildSelect('statut', t('statutFilterLabel', 'Statut commercial'), t('allStatut', 'Tous'), filtersConfig.statut);
    filtersRow.appendChild(statutFilter.wrapper);

    var categorieFilter = buildSelect('categorie', t('categorieFilterLabel', 'Catégorie'), t('allCategories', 'Toutes les catégories'), filtersConfig.categories);
    filtersRow.appendChild(categorieFilter.wrapper);

    var yearWrapper = el('div', 'gwseq-selections-filter gwseq-selections-filter--annee');
    var yearGroupLabel = el('label', 'gwseq-selections-filter__label', t('anneeFilterLabel', 'Année de naissance'));
    yearGroupLabel.setAttribute('for', 'gwseq-selections-filter-annee-min');
    yearWrapper.appendChild(yearGroupLabel);
    var yearInputsWrapper = el('div', 'gwseq-selections-filter__annee-inputs');
    yearInputsWrapper.appendChild(el('span', null, t('yearFrom', 'De')));
    var yearMinInput = document.createElement('input');
    yearMinInput.type = 'number';
    yearMinInput.id = 'gwseq-selections-filter-annee-min';
    yearMinInput.setAttribute('aria-label', t('yearFrom', 'De'));
    yearInputsWrapper.appendChild(yearMinInput);
    yearInputsWrapper.appendChild(el('span', null, t('yearTo', 'à')));
    var yearMaxInput = document.createElement('input');
    yearMaxInput.type = 'number';
    yearMaxInput.setAttribute('aria-label', t('yearTo', 'à'));
    yearInputsWrapper.appendChild(yearMaxInput);
    yearWrapper.appendChild(yearInputsWrapper);
    filtersRow.appendChild(yearWrapper);

    var resetButton = el('button', 'gwseq-selections-filters__reset', t('resetFilters', 'Réinitialiser les filtres'));
    resetButton.type = 'button';
    filtersRow.appendChild(resetButton);

    searchWrapper.appendChild(filtersRow);

    var resultsList = el('ul', 'gwseq-selections-results');
    resultsList.setAttribute('aria-live', 'polite');
    searchWrapper.appendChild(resultsList);

    var checkboxesByHorseId = {};

    function syncCheckboxes() {
      var selectedIds = selected.map(function (row) { return row.id; });
      Object.keys(checkboxesByHorseId).forEach(function (id) {
        checkboxesByHorseId[id].checked = selectedIds.indexOf(parseInt(id, 10)) !== -1;
      });
    }

    function renderResultRow(row) {
      var li = el('li', 'gwseq-selections-result');
      var checkboxLabel = el('label', 'gwseq-selections-checkbox');
      var checkbox = document.createElement('input');
      checkbox.type = 'checkbox';
      checkbox.checked = selected.some(function (item) { return item.id === row.id; });
      checkboxesByHorseId[row.id] = checkbox;
      checkbox.addEventListener('change', function () {
        if (checkbox.checked) {
          if (!selected.some(function (item) { return item.id === row.id; })) selected.push(row);
        } else {
          selected = selected.filter(function (item) { return item.id !== row.id; });
        }
        renderSelectedList();
      });
      checkboxLabel.appendChild(checkbox);
      checkboxLabel.appendChild(renderHorsePhoto(row.photo_url, 'gwseq-selections-result__photo'));
      var info = el('div', 'gwseq-selections-result__info');
      info.appendChild(el('strong', null, row.nom));
      if (row.sous_titre) info.appendChild(el('span', 'gwseq-selections-result__sous-titre', row.sous_titre));
      checkboxLabel.appendChild(info);
      li.appendChild(checkboxLabel);
      return li;
    }

    function renderResults(rows) {
      checkboxesByHorseId = {};
      clearNode(resultsList);
      if (!rows.length) {
        resultsList.appendChild(el('li', 'gwseq-selections-no-results', t('noResults', 'Aucun cheval trouvé.')));
        return;
      }
      rows.forEach(function (row) { resultsList.appendChild(renderResultRow(row)); });
    }

    renderResults(config.recents || []);

    var searchRequestId = 0;

    function runSearch() {
      var requestId = ++searchRequestId;
      ajaxPost('gwseq_selection_search_cheval', {
        s: searchInput.value,
        filters: {
          diffusion: diffusionFilter.select.value,
          sexe: sexeFilter.select.value,
          statut: statutFilter.select.value,
          categorie: categorieFilter.select.value,
          annee_min: yearMinInput.value,
          annee_max: yearMaxInput.value,
        },
      }).then(function (json) {
        if (requestId !== searchRequestId) return;
        if (json && json.success) renderResults(json.data.resultats || []);
      });
    }

    var debouncedSearch = debounce(runSearch, SEARCH_DEBOUNCE_MS);
    searchInput.addEventListener('input', debouncedSearch);
    filtersRow.addEventListener('change', runSearch);

    resetButton.addEventListener('click', function () {
      searchInput.value = '';
      diffusionFilter.select.value = '';
      sexeFilter.select.value = '';
      statutFilter.select.value = '';
      categorieFilter.select.value = '';
      yearMinInput.value = '';
      yearMaxInput.value = '';
      runSearch();
    });

    createButton.addEventListener('click', function () {
      createError.hidden = true;
      createButton.disabled = true;
      createButton.textContent = t('creating', 'Création…');
      ajaxPost('gwseq_selection_create', {
        title: titleInput.value,
        cheval_ids: selected.map(function (row) { return row.id; }),
      }).then(function (json) {
        if (json && json.success) {
          window.location.href = (json.data && json.data.redirect) || config.listeUrl;
        } else {
          createError.textContent = (json && json.data && json.data.message) || t('createError', '');
          createError.hidden = false;
          createButton.disabled = selected.length === 0;
          createButton.textContent = t('createSelection', 'Créer la sélection');
        }
      });
    });

    var layout = el('div', 'gwseq-selections-create__layout');
    layout.appendChild(searchWrapper);
    layout.appendChild(selectedPanel);
    wrapper.appendChild(layout);

    root.appendChild(wrapper);
    renderSelectedList();
  }

  /* -------------------------------------------------------------------------------------------
   * Point d'entrée.
   * ----------------------------------------------------------------------------------------- */

  function init() {
    var root = document.getElementById('gwseq-selections-app');
    if (!root) return;

    function showList() {
      initListView(root, showCreate);
    }

    function showCreate() {
      initCreateView(root, showList);
    }

    var params = new window.URLSearchParams(window.location.search);
    if (params.get('vue') === 'nouvelle') {
      showCreate();
    } else {
      showList();
    }
  }

  document.addEventListener('DOMContentLoaded', init);
})();

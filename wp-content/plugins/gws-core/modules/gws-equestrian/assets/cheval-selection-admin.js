/**
 * Écran « Chevaux → Sélections » (Suite V1 « Partager & vendre », Lot 2B) — JavaScript natif,
 * aucune dépendance, même architecture que assets/cheval-share-admin.js (helpers dupliqués
 * volontairement : chaque écran BO de ce module charge son propre script indépendant).
 *
 * Trois vues, jamais trois écrans séparés (une seule page `gwseq-selections-app`) : la LISTE des
 * sélections déjà créées (par défaut), la CRÉATION d'une nouvelle sélection, et la MODIFICATION
 * d'une sélection existante (Lot 2B, §2 de l'ajustement de recette) — les deux dernières partagent
 * le MÊME écran "éditeur" (recherche/filtres/sélection/ordre/titre), seul le mode change (voir
 * initEditorView() ci-dessous), pour ne jamais dupliquer cette interface. AJUSTEMENT DE MODÈLE
 * (recette 2A) : plus de "Révoquer"/"Régénérer" — une sélection existante est active tant qu'elle
 * existe, y mettre fin se fait en la SUPPRIMANT (confirmation obligatoire, message métier explicite).
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
   * Vue liste (§4 de l'ajustement de recette) : Titre (ouvre la modification) | Date | Chevaux
   * diffusables | Lien (copier) | Actions (Supprimer uniquement — plus de Révoquer/Régénérer).
   * ----------------------------------------------------------------------------------------- */

  function renderSelectionRow(row, onOpen) {
    var tr = document.createElement('tr');

    var tdTitre = document.createElement('td');
    var titleButton = el('button', 'gwseq-selections-title-link', row.titre);
    titleButton.type = 'button';
    titleButton.addEventListener('click', function () { onOpen(row.id); });
    tdTitre.appendChild(titleButton);
    tr.appendChild(tdTitre);

    var tdDate = document.createElement('td');
    tdDate.textContent = row.date;
    tr.appendChild(tdDate);

    var tdCompte = document.createElement('td');
    tdCompte.textContent = row.chevaux_diffusables + ' / ' + row.total_chevaux;
    tr.appendChild(tdCompte);

    var tdLien = document.createElement('td');
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
    tr.appendChild(tdLien);

    var tdActions = document.createElement('td');
    var deleteLink = el('a', 'button', t('delete', 'Supprimer'));
    deleteLink.href = row.url_supprimer;
    deleteLink.addEventListener('click', function (event) {
      if (!window.confirm(t('confirmDelete', ''))) event.preventDefault();
    });
    tdActions.appendChild(deleteLink);
    tr.appendChild(tdActions);

    return tr;
  }

  function initListView(root, onCreateNew, onOpen) {
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
      rows.forEach(function (row) { tbody.appendChild(renderSelectionRow(row, onOpen)); });
      table.appendChild(tbody);

      wrapper.appendChild(table);
    }

    root.appendChild(wrapper);
  }

  /* -------------------------------------------------------------------------------------------
   * Vue éditeur, partagée par CRÉATION et MODIFICATION (§7-8 de la demande initiale, §2 de
   * l'ajustement de recette) : recherche/filtres réutilisés de l'écran « Partager », sélection
   * multiple (case à cocher), ordre explicite (Monter/Descendre), titre facultatif.
   *
   * `options`:
   *   mode: 'create' | 'edit'
   *   selectionId: identifiant de la sélection (mode 'edit' uniquement)
   *   initialTitle: titre déjà enregistré (chaîne vide si aucun)
   *   initialChevaux: chevaux déjà présents, dans l'ordre, chacun avec un indicateur `displayable`
   *     (mode 'edit' — vide en mode 'create')
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

  function initEditorView(root, onBack, options) {
    options = options || {};
    var mode = options.mode === 'edit' ? 'edit' : 'create';
    clearNode(root);
    var wrapper = el('div', 'gwseq-selections-create');

    var backButton = el('button', 'gwseq-selections-back', t('backToList', '← Retour aux sélections'));
    backButton.type = 'button';
    backButton.addEventListener('click', onBack);
    wrapper.appendChild(backButton);

    if (mode === 'edit') {
      wrapper.appendChild(el('h2', null, t('editSelectionTitle', 'Modifier la sélection')));
    }

    // État de la sélection en cours (ordonné, dédoublonné) — l'ordre du tableau EST l'ordre final
    // envoyé au serveur (§8 : "l'ordre peut avoir une importance commerciale"). En mode 'edit',
    // initialisé avec les chevaux ACTUELLEMENT enregistrés (§6 : un cheval devenu non diffusable
    // reste affiché tant qu'il n'est pas explicitement retiré, jamais disparu silencieusement).
    var selected = (options.initialChevaux || []).slice();

    // --- Panneau "sélection en cours" ---
    var selectedPanel = el('div', 'gwseq-selections-selected');
    var selectedCountEl = el('p', 'gwseq-selections-selected__count', formatSelectedCount(selected.length));
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
    titleInput.value = options.initialTitle || '';
    titleField.appendChild(titleInput);
    selectedPanel.appendChild(titleField);

    var submitLabel = mode === 'edit' ? t('saveChanges', 'Enregistrer les modifications') : t('createSelection', 'Créer la sélection');
    var submittingLabel = mode === 'edit' ? t('saving', 'Enregistrement…') : t('creating', 'Création…');
    var errorLabel = mode === 'edit' ? t('updateError', '') : t('createError', '');

    var submitButton = el('button', 'button button-primary button-hero', submitLabel);
    submitButton.type = 'button';
    submitButton.disabled = selected.length === 0;
    selectedPanel.appendChild(submitButton);

    var submitError = el('p', 'gwseq-selections-create-error');
    submitError.hidden = true;
    selectedPanel.appendChild(submitError);

    var checkboxesByHorseId = {};

    function syncCheckboxes() {
      var selectedIds = selected.map(function (row) { return row.id; });
      Object.keys(checkboxesByHorseId).forEach(function (id) {
        checkboxesByHorseId[id].checked = selectedIds.indexOf(parseInt(id, 10)) !== -1;
      });
    }

    function renderSelectedList() {
      clearNode(selectedList);
      selected.forEach(function (row, index) {
        var li = el('li', 'gwseq-selections-selected__item');
        li.appendChild(renderHorsePhoto(row.photo_url, 'gwseq-selections-selected__photo'));
        var info = el('div', 'gwseq-selections-selected__info');
        info.appendChild(el('strong', null, row.nom));
        if (row.sous_titre) info.appendChild(el('span', 'gwseq-selections-selected__sous-titre', row.sous_titre));
        if (row.displayable === false) {
          info.appendChild(el('span', 'gwseq-selections-selected__badge', t('notDisplayable', 'actuellement non diffusable')));
        }
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
      submitButton.disabled = selected.length === 0;
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
      syncCheckboxes();
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

    submitButton.addEventListener('click', function () {
      submitError.hidden = true;
      submitButton.disabled = true;
      submitButton.textContent = submittingLabel;
      var payload = {
        title: titleInput.value,
        cheval_ids: selected.map(function (row) { return row.id; }),
      };
      var action = 'gwseq_selection_create';
      if (mode === 'edit') {
        action = 'gwseq_selection_update';
        payload.selection_id = options.selectionId;
      }
      ajaxPost(action, payload).then(function (json) {
        if (json && json.success) {
          window.location.href = (json.data && json.data.redirect) || config.listeUrl;
        } else {
          submitError.textContent = (json && json.data && json.data.message) || errorLabel;
          submitError.hidden = false;
          submitButton.disabled = selected.length === 0;
          submitButton.textContent = submitLabel;
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
      initListView(root, showCreate, showEdit);
    }

    function showCreate() {
      initEditorView(root, showList, { mode: 'create' });
    }

    function showEdit(selectionId) {
      clearNode(root);
      root.appendChild(el('p', 'gwseq-selections-loading', t('loading', 'Chargement…')));
      ajaxPost('gwseq_selection_get', { selection_id: selectionId }).then(function (json) {
        if (json && json.success) {
          initEditorView(root, showList, {
            mode: 'edit',
            selectionId: selectionId,
            initialTitle: json.data.titre || '',
            initialChevaux: json.data.chevaux || [],
          });
        } else {
          clearNode(root);
          root.appendChild(el('p', 'gwseq-selections-load-error', t('loadError', '')));
        }
      });
    }

    var params = new window.URLSearchParams(window.location.search);
    var vue = params.get('vue');
    if (vue === 'nouvelle') {
      showCreate();
    } else if (vue === 'modifier' && params.get('selection_id')) {
      showEdit(parseInt(params.get('selection_id'), 10));
    } else {
      showList();
    }
  }

  document.addEventListener('DOMContentLoaded', init);
})();

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

  /**
   * Photo réelle si elle existe, sinon une vignette de remplacement neutre (correctif de recette
   * §2) — jamais un `<img>` avec un `src` vide ou introuvable (icône "image cassée" du navigateur,
   * trompeuse pour une absence de photo parfaitement légitime). Même classe CSS
   * (`gwseq-media-placeholder`, assets/gws-media-placeholder.css) et même dashicon
   * (`dashicons-pets`, déjà l'icône de menu de "Chevaux") que gwseq_render_media_placeholder()
   * (includes/admin-ui.php) — reproduit ici en DOM plutôt qu'en chaîne HTML analysée côté client,
   * mais visuellement identique partout où l'un ou l'autre est utilisé dans le BO. Élément
   * purement visuel : ne crée jamais de média, ne modifie jamais la fiche cheval.
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

  /* -------------------------------------------------------------------------------------------
   * Écran de recherche.
   * ----------------------------------------------------------------------------------------- */

  function renderResultRow(row, onShare) {
    var li = el('li', 'gwseq-partager-result');
    li.appendChild(renderHorsePhoto(row.photo_url, 'gwseq-partager-result__photo'));

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

  /**
   * Filtres métier (§3-5 du correctif de recette) : sélecteurs natifs (Sexe/Statut commercial/
   * Catégorie), déjà compacts et tactiles sans code supplémentaire, plus deux petits champs
   * numériques pour la plage d'année de naissance. Filtrage DYNAMIQUE (aucun bouton "Appliquer",
   * §5 — l'implémentation la plus simple et robuste) : chaque changement redéclenche la même
   * recherche debouncée que le texte libre, les deux étant nativement cumulables côté serveur.
   */
  function buildSelect(idSuffix, labelText, allLabel, options) {
    var wrapperEl = el('div', 'gwseq-partager-filter');
    var labelEl = el('label', 'gwseq-partager-filter__label', labelText);
    labelEl.setAttribute('for', 'gwseq-partager-filter-' + idSuffix);
    wrapperEl.appendChild(labelEl);

    var select = document.createElement('select');
    select.id = 'gwseq-partager-filter-' + idSuffix;
    select.className = 'gwseq-partager-filter__select';
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

    // --- Filtres ---
    var filtersConfig = config.filters || {};
    var filtersRow = el('div', 'gwseq-partager-filters');

    // Audit UX/métier suivant — même vocabulaire que la liste d'administration (§ "réutiliser
    // exclusivement gwseq_horse_diffusion_state() comme source de vérité") : les options viennent
    // telles quelles de filtersConfig.diffusion (gwseq_horse_share_diffusion_filter_options(),
    // includes/cheval-share-admin.php), jamais un second référentiel codé ici.
    var diffusionFilter = buildSelect('diffusion', t('diffusionFilterLabel', 'État de diffusion'), t('allDiffusion', 'Tous les états de diffusion'), filtersConfig.diffusion);
    filtersRow.appendChild(diffusionFilter.wrapper);

    var sexeFilter = buildSelect('sexe', t('sexeFilterLabel', 'Sexe'), t('allSexe', 'Tous'), filtersConfig.sexe);
    filtersRow.appendChild(sexeFilter.wrapper);

    var statutFilter = buildSelect('statut', t('statutFilterLabel', 'Statut commercial'), t('allStatut', 'Tous'), filtersConfig.statut);
    filtersRow.appendChild(statutFilter.wrapper);

    var categorieFilter = buildSelect('categorie', t('categorieFilterLabel', 'Catégorie'), t('allCategories', 'Toutes les catégories'), filtersConfig.categories);
    filtersRow.appendChild(categorieFilter.wrapper);

    // Groupe "Année de naissance" : un <label> visible unique pour le groupe (associé au premier
    // champ, "De") plus les deux `aria-label` déjà en place sur chaque champ ("De"/"à") — ce sont
    // deux informations complémentaires, pas redondantes : le libellé de groupe identifie le FILTRE
    // ("à quoi sert cette zone ?"), les aria-label distinguent les deux champs ENTRE EUX pour les
    // technologies d'assistance ("De" vs "à").
    var yearWrapper = el('div', 'gwseq-partager-filter gwseq-partager-filter--annee');
    var yearGroupLabel = el('label', 'gwseq-partager-filter__label', t('anneeFilterLabel', 'Année de naissance'));
    yearGroupLabel.setAttribute('for', 'gwseq-partager-filter-annee-min');
    yearWrapper.appendChild(yearGroupLabel);
    var yearInputsWrapper = el('div', 'gwseq-partager-filter__annee-inputs');
    yearInputsWrapper.appendChild(el('span', 'gwseq-partager-filter__annee-label', t('yearFrom', 'De')));
    var yearMinInput = document.createElement('input');
    yearMinInput.type = 'number';
    yearMinInput.id = 'gwseq-partager-filter-annee-min';
    yearMinInput.className = 'gwseq-partager-filter__annee-input';
    yearMinInput.setAttribute('aria-label', t('yearFrom', 'De'));
    if (filtersConfig.anneeMin) yearMinInput.min = filtersConfig.anneeMin;
    if (filtersConfig.anneeMax) yearMinInput.max = filtersConfig.anneeMax;
    yearInputsWrapper.appendChild(yearMinInput);
    yearInputsWrapper.appendChild(el('span', 'gwseq-partager-filter__annee-label', t('yearTo', 'à')));
    var yearMaxInput = document.createElement('input');
    yearMaxInput.type = 'number';
    yearMaxInput.className = 'gwseq-partager-filter__annee-input';
    yearMaxInput.setAttribute('aria-label', t('yearTo', 'à'));
    if (filtersConfig.anneeMin) yearMaxInput.min = filtersConfig.anneeMin;
    if (filtersConfig.anneeMax) yearMaxInput.max = filtersConfig.anneeMax;
    yearInputsWrapper.appendChild(yearMaxInput);
    yearWrapper.appendChild(yearInputsWrapper);
    filtersRow.appendChild(yearWrapper);

    var resetButton = el('button', 'gwseq-partager-filters__reset', t('resetFilters', 'Réinitialiser les filtres'));
    resetButton.type = 'button';
    filtersRow.appendChild(resetButton);

    wrapper.appendChild(filtersRow);

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

    // Même correctif de séquencement que l'aperçu du message (voir initComposeScreen()) : une
    // réponse de recherche plus ANCIENNE ne doit jamais écraser une réponse plus RÉCENTE si le
    // réseau les fait arriver dans le désordre.
    var searchRequestId = 0;

    function runSearch() {
      var requestId = ++searchRequestId;
      ajaxPost('gwseq_partager_search_cheval', {
        s: input.value,
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

    var search = debounce(runSearch, SEARCH_DEBOUNCE_MS);

    input.addEventListener('input', search);
    filtersRow.addEventListener('change', search);

    resetButton.addEventListener('click', function () {
      input.value = '';
      diffusionFilter.select.value = '';
      sexeFilter.select.value = '';
      statutFilter.select.value = '';
      categorieFilter.select.value = '';
      yearMinInput.value = '';
      yearMaxInput.value = '';
      runSearch();
    });
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

  /**
   * Correctif de recette (premier test réel WhatsApp) : le message transmis perdait sa structure
   * (sauts de ligne disparus, texte de plusieurs lignes réduit à une seule) — jamais un problème de
   * COMPOSITION (`currentMessage` contient bien les vrais "\n", l'aperçu BO l'affiche correctement
   * dans une balise `<pre>`) ni d'ENCODAGE ({@link encodeURIComponent} encode déjà correctement
   * "\n" en "%0A" et tout caractère UTF-8, accents/×/•/emoji inclus — vérifié par test dédié). La
   * divergence se situe au dernier maillon, le lien court `wa.me`, dont le comportement de
   * transport du texte pré-rempli s'est montré, sur un appareil réel, moins fiable que le point
   * d'entrée canonique documenté par WhatsApp lui-même, `api.whatsapp.com/send` (ce que `wa.me`
   * résout in fine) : ce dernier est donc utilisé directement pour ce bouton, sans passer par le
   * lien court. Aucun changement du moteur de composition (`gwseq_build_horse_share_message()`) ni
   * de son encodage : uniquement le point de sortie WhatsApp.
   */
  function buildWhatsappUrl(text) {
    return 'https://api.whatsapp.com/send?text=' + encodeURIComponent(text);
  }

  /**
   * `sms:` n'est PAS un standard unique : sans numéro de destinataire, iOS exige un `&` avant
   * `body=` (`sms:&body=...`) alors qu'Android (et la plupart des autres navigateurs) attendent un
   * `?` (`sms:?body=...`) — utiliser le mauvais séparateur sur iOS ouvre l'application Messages
   * SANS pré-remplir le texte, silencieusement (aucune erreur visible). Détection minimale par
   * `navigator.userAgent`, suffisante pour ce cas d'usage (aucune nouvelle dépendance). Même
   * encodage que WhatsApp (`encodeURIComponent`) : seul le séparateur diffère d'une plateforme à
   * l'autre, jamais le contenu ni son encodage.
   */
  function buildSmsUrl(text) {
    var ua = (window.navigator && window.navigator.userAgent) || '';
    var isIOS = /iPad|iPhone|iPod/.test(ua);
    return 'sms:' + (isIOS ? '&' : '?') + 'body=' + encodeURIComponent(text);
  }

  function initComposeScreen(root, chevalId, shareable, onBack) {
    clearNode(root);
    var wrapper = el('div', 'gwseq-partager-compose');

    var backButton = el('button', 'gwseq-partager-back', t('back', '← Choisir un autre cheval'));
    backButton.type = 'button';
    backButton.addEventListener('click', onBack);
    wrapper.appendChild(backButton);

    var horseHeader = el('div', 'gwseq-partager-horse');
    horseHeader.appendChild(renderHorsePhoto(shareable.photo_url, 'gwseq-partager-horse__photo'));
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
      // §3 de la suite « Partager & vendre » : GWS a déjà déterminé le lien approprié côté serveur
      // (gwseq_horse_share_fiche_info()) — seul le LIBELLÉ change selon son type (fiche_type),
      // jamais une case supplémentaire ni un choix de permalink à faire comprendre à l'utilisateur.
      var ficheLabelText = shareable.fiche_type === 'privee'
        ? t('ficheLabelPrivee', 'Inclure le lien privé vers la fiche')
        : t('ficheLabel', 'Inclure le lien vers la fiche');
      ficheLabel.appendChild(el('span', null, ficheLabelText));
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

    // CORRECTIF (recette : le prix apparaissait dans l'aperçu alors que sa case était décochée) —
    // CAUSE RACINE : chaque frappe/coche déclenchait un nouvel appel AJAX indépendant, sans jamais
    // annuler ni ignorer les précédents. Si une réponse plus ANCIENNE arrivait après une réponse
    // plus RÉCENTE (latence réseau variable, tout à fait réaliste hors environnement de test), elle
    // écrasait silencieusement l'aperçu à jour avec un texte reflétant une sélection déjà dépassée —
    // jamais une erreur de construction du message côté serveur (gwseq_build_horse_share_message()
    // reflète toujours fidèlement la sélection qui lui est transmise), un problème de SÉQUENCEMENT
    // des réponses. Un jeton de requête strictement croissant garantit qu'une réponse n'est jamais
    // appliquée si une requête plus récente a depuis été émise — jamais un simple masquage visuel.
    var previewRequestId = 0;

    function refreshPreview() {
      var requestId = ++previewRequestId;
      previewText.textContent = t('loading', 'Chargement…');
      ajaxPost('gwseq_partager_build_message', { cheval_id: chevalId, selection: currentSelection(wrapper) }).then(function (json) {
        if (requestId !== previewRequestId) return; // réponse obsolète, une requête plus récente est déjà en cours
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
      window.open(buildWhatsappUrl(currentMessage), '_blank');
    });
    actionButtons.sms.addEventListener('click', function () {
      window.location.href = buildSmsUrl(currentMessage);
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

/**
 * Composant de recherche/autocomplétion Race / Stud-book / Appellation (Étape 8 de la demande) —
 * JavaScript natif, aucune dépendance. Un SEUL script cible tous les champs
 * `[data-gwseq-race-field]` présents sur l'écran (identité + chaque génération d'ascendant externe,
 * §8 : "le même composant partout"), à partir d'un référentiel et de suggestions chargés UNE SEULE
 * FOIS via `window.gwseqRaceReferentiel` (voir gwseq_enqueue_race_referentiel_assets(),
 * includes/race-referentiel.php).
 *
 * COMPORTEMENT :
 * - Champ vide au focus -> affiche les suggestions (récents de l'utilisateur, ou un repli neutre
 *   si aucun historique, voir PHP) — jamais l'intégralité du référentiel (§1/§5 de la demande).
 *   Champ déjà rempli au focus (import IFCE, valeur déjà enregistrée) : le TEXTE EST SÉLECTIONNÉ
 *   ENTIÈREMENT (comme un champ de recherche classique), pour qu'une frappe immédiate REMPLACE la
 *   valeur affichée plutôt que de s'y concaténer.
 * - Saisie -> recherche LOCALE (aucun aller-retour serveur, le référentiel entier tient en mémoire,
 *   TOUJOURS `config.entries` — les 154 entrées complètes — jamais `config.suggestions`, qui n'est
 *   qu'un repli d'AU PLUS 10 valeurs affiché uniquement quand le champ est VIDE, voir plus bas) sur
 *   le code, le libellé IFCE, le libellé GWS et les alias — accents/casse ignorés — avec les
 *   correspondances en DÉBUT de champ affichées avant les correspondances partielles.
 * - "Autre — préciser" reste TOUJOURS proposé en dernière position (§7 : filet de sécurité, jamais
 *   un repli automatique sur une valeur mal reconnue).
 * - Un choix cliqué, ou validé au clavier (flèches puis Entrée), fixe le CODE réellement soumis
 *   (champ caché) et affiche son libellé GWS dans le champ de recherche ; "Autre" affiche/vide le
 *   champ de précision libre.
 * - Une saisie libre jamais validée par un clic/Entrée (perte de focus sans sélection) retombe
 *   automatiquement sur "Autre" + le texte tapé s'il est non vide, ou sur une valeur vide sinon —
 *   jamais un champ affichant un texte qui ne correspond plus au code réellement mémorisé.
 *
 * `config.suggestions` EXPLIQUÉ (recette runtime — le log "entries: 154, suggestions: 5" a soulevé
 * la question) : ce sont les valeurs RÉCEMMENT UTILISÉES par l'utilisateur courant (ou un repli
 * neutre s'il n'en a aucune, voir gwseq_race_referentiel_suggestions_for_user() côté PHP),
 * affichées UNIQUEMENT quand le champ est vide au focus — un confort pour retrouver vite ses
 * valeurs habituelles, jamais le périmètre de la recherche elle-même. Une frappe non vide
 * (`query.trim() !== ''`) appelle TOUJOURS `searchEntries(config.entries, ...)`, jamais
 * `config.suggestions` — voir `showSuggestionsOrSearch()` plus bas. Le nombre de suggestions
 * n'explique donc structurellement pas une recherche non fonctionnelle sur un texte non vide comme
 * "old" ; l'instrumentation ci-dessous logue explicitement les deux tailles séparément pour le
 * démontrer sur le runtime réel.
 *
 * FILET DE SÉCURITÉ OBLIGATOIRE (correctif runtime, §6 de la demande) : un `<select>` natif complet
 * est TOUJOURS rendu par PHP (voir gwseq_render_race_referentiel_field()), fonctionnel sans
 * JavaScript. `activateField()` ci-dessous ne le désactive/masque, et ne montre ce composant de
 * recherche, qu'à la TOUTE FIN d'une initialisation qui n'a rencontré AUCUNE exception — si le
 * script ne s'exécute pas, échoue à charger, ou qu'une erreur survient n'importe où avant ce point,
 * le `<select>` reste le SEUL contrôle actif et continue de soumettre normalement.
 *
 * SANS JAVASCRIPT (ou en cas d'échec) : le `<select>` de secours reste le contrôle actif et soumis
 * — jamais un champ métier essentiel rendu impossible à renseigner par un souci purement
 * d'affichage/script.
 */
(function () {
  'use strict';

  // Instrumentation TEMPORAIRE (recette runtime, à retirer une fois le composant confirmé
  // fonctionnel en conditions réelles) : traces `console.*` préfixées "[gwseq-race]", couvrant
  // aussi bien l'initialisation (une ligne par étape-clé) que CHAQUE interaction clavier/souris
  // réelle sur un champ (valeur brute reçue, valeur normalisée, nombre de résultats, premiers
  // résultats, code caché avant/après, présence du conteneur de résultats, nombre d'éléments
  // injectés, état de visibilité réel via getComputedStyle) — exactement les dix points demandés
  // en recette pour localiser où le flux diverge entre `input` et la synchronisation finale du code
  // caché. Chaque gestionnaire d'événement est en outre entouré d'un `try`/`catch` DÉDIÉ (distinct
  // de celui de l'initialisation) : une exception survenant PENDANT une frappe réelle — jamais
  // reproduite par un événement synthétique de test — serait sinon silencieusement avalée par le
  // navigateur, laissant croire à tort à une simple absence de résultat. Messages ASCII uniquement
  // (y compris dans les chaînes), cohérent avec la robustesse déjà recherchée dans ce fichier.
  function log() {
    if (window.console && window.console.log) window.console.log.apply(window.console, ['[gwseq-race]'].concat(Array.prototype.slice.call(arguments)));
  }
  function warn() {
    if (window.console && window.console.warn) window.console.warn.apply(window.console, ['[gwseq-race]'].concat(Array.prototype.slice.call(arguments)));
  }
  function visibilityReport(el) {
    if (!el || !window.getComputedStyle) return 'n/a';
    var cs = window.getComputedStyle(el);
    return 'display=' + cs.display + ' visibility=' + cs.visibility + ' opacity=' + cs.opacity + ' overflow=' + cs.overflow + ' zIndex=' + cs.zIndex + ' offsetParent=' + (el.offsetParent ? 'present' : 'null');
  }

  log('script loaded');

  document.addEventListener('DOMContentLoaded', function () {
    log('DOMContentLoaded fired');
    var config = window.gwseqRaceReferentiel;
    if (!config || !Array.isArray(config.entries)) {
      warn('window.gwseqRaceReferentiel missing or invalid - the referential was not correctly passed by wp_localize_script() (check that gwseq_enqueue_race_referentiel_assets() actually runs on this screen):', config);
      return;
    }
    log('referential loaded, entries (full search pool):', config.entries.length, '- suggestions (empty-field default only, NEVER the search pool):', (config.suggestions || []).length);

    var fields = document.querySelectorAll('[data-gwseq-race-field]');
    log('[data-gwseq-race-field] fields found in the DOM:', fields.length);
    if (fields.length === 0) {
      warn('no [data-gwseq-race-field] field found - check that gwseq_render_race_referentiel_field() was actually rendered on this screen (identity and/or pedigree)');
    }
    fields.forEach(function (field, index) {
      // Un champ malformé (structure inattendue) ne doit jamais empêcher l'initialisation des
      // AUTRES champs de la page (Array.prototype.forEach interrompt son parcours à la première
      // exception non rattrapée dans son callback) — chaque champ reste isolé des autres. Le
      // <select> de secours de CE champ reste actif tant que initField() n'a pas explicitement
      // réussi jusqu'à activateField() (voir plus bas) — jamais désactivé par anticipation.
      try {
        initField(field, config, index);
        log('field #' + index + ' (id=' + (field.querySelector('.gwseq-race-field__search') || {}).id + ') initialized successfully, fallback <select> deactivated, search UI active');
      } catch (e) {
        warn('field #' + index + ' - initialization failed, fallback <select> remains the active control:', e);
      }
    });
  });

  function normalize(text) {
    text = String(text == null ? '' : text);
    if (text.normalize) {
      // Diacritiques combinants (U+0300-U+036F) retirés via des ÉCHAPPEMENTS \uXXXX, jamais les
      // caractères Unicode littéraux : un caractère multi-octet écrit en clair dans le fichier
      // source dépend d'un encodage/transfert fidèle (hébergement, CDN, minification...) — corrompu
      // par n'importe quel maillon qui ne le préserverait pas en UTF-8, il produirait une ERREUR DE
      // SYNTAXE qui tuerait silencieusement TOUT le script au chargement. Un échappement \uXXXX est
      // constitué exclusivement de caractères ASCII : il ne peut structurellement plus être corrompu
      // par un problème d'encodage de fichier.
      text = text.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    }
    return text.toLowerCase().replace(/[_-]/g, ' ').replace(/\s+/g, ' ').trim();
  }

  function searchEntries(entries, query, limit) {
    var normalizedQuery = normalize(query);
    if (normalizedQuery === '') return [];
    var prefixMatches = [];
    var substringMatches = [];
    entries.forEach(function (entry) {
      var fields = [entry.code, entry.ifce, entry.label].concat(entry.alias || []);
      var isPrefix = false;
      var isSubstring = false;
      fields.forEach(function (value) {
        var normalizedValue = normalize(value);
        if (normalizedValue === '') return;
        var position = normalizedValue.indexOf(normalizedQuery);
        if (position === 0) isPrefix = true;
        else if (position > 0) isSubstring = true;
      });
      if (isPrefix) prefixMatches.push(entry);
      else if (isSubstring) substringMatches.push(entry);
    });
    return prefixMatches.concat(substringMatches).slice(0, limit || 20);
  }

  /**
   * Active le composant de recherche à la place du <select> de secours — appelée UNIQUEMENT à la
   * toute fin de initField(), une fois tous les éléments trouvés et tous les écouteurs attachés
   * sans exception. Transfère le VRAI attribut `name` du <select> (et de son champ de précision
   * "Autre") vers les champs cachés du composant de recherche (portés jusqu'ici sur
   * `data-gwseq-race-field-name`, jamais `name`, pour ne jamais soumettre deux valeurs à la fois),
   * puis désactive et masque le <select> — qui ne soumettra donc plus rien, le composant de
   * recherche devenant le SEUL contrôle actif. Si cette fonction n'est jamais atteinte (exception
   * plus haut dans initField), le <select> reste tel quel : actif, visible, et c'est LUI qui
   * soumet — jamais un champ sans aucun contrôle fonctionnel.
   */
  function activateField(field, codeInput, autreInput) {
    var wrap = field.parentNode;
    var fallbackWrap = wrap ? wrap.querySelector('.gwseq-race-field__fallback-wrap') : null;
    var fallbackSelect = fallbackWrap ? fallbackWrap.querySelector('.gwseq-race-field__fallback') : null;
    var fallbackAutre = fallbackWrap ? fallbackWrap.querySelector('.gwseq-race-field__fallback-autre') : null;
    if (fallbackSelect) {
      var realName = codeInput.getAttribute('data-gwseq-race-field-name');
      if (realName) codeInput.name = realName;
      fallbackSelect.disabled = true;
      fallbackSelect.removeAttribute('name');
    }
    if (fallbackAutre) {
      var realAutreName = autreInput ? autreInput.getAttribute('data-gwseq-race-field-name') : null;
      if (autreInput && realAutreName) autreInput.name = realAutreName;
      fallbackAutre.disabled = true;
      fallbackAutre.removeAttribute('name');
    }
    if (fallbackWrap) fallbackWrap.style.display = 'none';
    field.style.display = '';
  }

  function initField(field, config, fieldIndex) {
    var search = field.querySelector('.gwseq-race-field__search');
    var codeInput = field.querySelector('.gwseq-race-field__code');
    var resultsList = field.querySelector('.gwseq-race-field__results');
    var autreWrap = field.querySelector('.gwseq-race-field__autre-wrap');
    var autreInput = field.querySelector('.gwseq-race-field__autre');
    if (!search || !codeInput || !resultsList) {
      warn('field #' + fieldIndex + ' - missing expected sub-elements (search=' + !!search + ' codeInput=' + !!codeInput + ' resultsList=' + !!resultsList + '), aborting init for this field only');
      return;
    }

    var tag = '[field #' + fieldIndex + ']';
    // CORRECTIF RUNTIME (bug "Préciser" reproductible à chaque sauvegarde SANS interaction avec ce
    // champ précis — recette complémentaire 0.14.5) : un code déjà présent au chargement (rendu par
    // PHP, ex. "SF") est une sélection DÉJÀ VALIDE — jamais une saisie libre en attente de
    // committement. Initialiser `hasPickedThisSession` à `false` sans condition (comme avant ce
    // correctif) faisait que `commitPendingValue()` (appelée par le filet de sécurité de soumission
    // ci-dessous, sur N'IMPORTE QUEL submit du formulaire — y compris un enregistrement qui ne
    // touche à aucun autre onglet, ni même à ce champ) traitait alors le LIBELLÉ AFFICHÉ ("Selle
    // Français") comme une saisie jamais validée : elle réécrivait le code caché en "autre" et
    // recopiait ce libellé dans "race_autre" — précisément parce qu'aucun clic explicite sur un
    // résultat (`selectEntry()`, seul autre endroit qui met `hasPickedThisSession` à `true`) n'avait
    // eu lieu CETTE session-ci, alors que la valeur était déjà parfaitement correcte. Un champ
    // rechargé avec un code déjà renseigné (canonique OU "autre") démarre donc désormais déjà
    // "validé" ; `focus`/`input` continuent, comme avant, à repasser `hasPickedThisSession` à `false`
    // dès que l'utilisateur touche RÉELLEMENT ce champ précis, pour que commitPendingValue()
    // redevienne actif sur une véritable nouvelle saisie.
    var hasPickedThisSession = codeInput.value !== '';
    var currentEntries = [];
    var activeIndex = -1;

    function closeResults() {
      resultsList.hidden = true;
      resultsList.innerHTML = '';
      currentEntries = [];
      activeIndex = -1;
      search.setAttribute('aria-expanded', 'false');
    }

    function selectEntry(code, label) {
      codeInput.value = code;
      search.value = label;
      hasPickedThisSession = true;
      var isAutre = code === config.autreCode;
      if (autreWrap) autreWrap.style.display = isAutre ? '' : 'none';
      if (isAutre && autreInput) autreInput.focus();
      closeResults();
    }

    function highlight(index) {
      var items = resultsList.children;
      for (var i = 0; i < items.length; i++) {
        items[i].classList.toggle('gwseq-race-field__result--active', i === index);
      }
      activeIndex = index;
    }

    function renderResults(entries) {
      resultsList.innerHTML = '';
      currentEntries = entries;
      activeIndex = -1;
      entries.forEach(function (entry) {
        var item = document.createElement('li');
        item.setAttribute('role', 'option');
        item.tabIndex = -1;
        var typeMark = entry.type === 'appellation' ? ' (' + entry.code + ')' : (entry.label !== entry.code ? ' (' + entry.code + ')' : '');
        item.textContent = entry.label + typeMark;
        item.addEventListener('mousedown', function (event) {
          // mousedown (pas click) AVEC preventDefault() : empêche NATIVEMENT le focus de quitter
          // le champ de recherche, donc `blur` ne se déclenche jamais pour ce clic — jamais de
          // course avec une éventuelle fermeture/committe sur blur.
          try {
            event.preventDefault();
            log(tag, 'result clicked:', entry.code, entry.label);
            selectEntry(entry.code, entry.label);
          } catch (e) {
            warn(tag, 'exception in result mousedown handler:', e);
          }
        });
        resultsList.appendChild(item);
      });

      var autreItem = document.createElement('li');
      autreItem.setAttribute('role', 'option');
      autreItem.tabIndex = -1;
      autreItem.className = 'gwseq-race-field__autre-option';
      autreItem.textContent = config.i18n.autre;
      autreItem.addEventListener('mousedown', function (event) {
        try {
          event.preventDefault();
          selectEntry(config.autreCode, '');
          search.value = '';
        } catch (e) {
          warn(tag, 'exception in "Autre" mousedown handler:', e);
        }
      });
      resultsList.appendChild(autreItem);

      resultsList.hidden = false;
      search.setAttribute('aria-expanded', 'true');
      log(tag, 'results container: created =', !!resultsList, '- items injected:', entries.length + 1, '(including "Autre") - visibility:', visibilityReport(resultsList));
    }

    function showSuggestionsOrSearch() {
      var query = search.value;
      var normalizedQuery = normalize(query);
      if (query.trim() === '') {
        var suggestions = config.suggestions || [];
        log(tag, 'empty field -> showing suggestions (recent/default), count:', suggestions.length, '- NOT searching the 154-entry pool (nothing to search on empty input)');
        renderResults(suggestions);
      } else {
        var results = searchEntries(config.entries, query, 20);
        log(tag, 'raw input value:', JSON.stringify(query), '- normalized for search:', JSON.stringify(normalizedQuery), '- search pool size:', config.entries.length, '- results found:', results.length, '- first results:', results.slice(0, 5).map(function (e) { return e.code + '/' + e.label; }));
        renderResults(results);
      }
    }

    // Committe la saisie courante vers le champ caché — appelée sur `blur`, à la soumission du
    // formulaire, et sur Entrée quand aucun résultat n'est disponible à valider. Idempotente et
    // sûre à appeler plusieurs fois : ne fait rien si un choix a déjà été explicitement validé
    // (`hasPickedThisSession`), jamais de double transformation d'une même saisie.
    function commitPendingValue() {
      if (hasPickedThisSession) return;
      var typed = search.value.trim();
      if (typed === '') {
        codeInput.value = '';
        if (autreWrap) autreWrap.style.display = 'none';
        return;
      }
      // Saisie libre jamais validée par un clic/Entrée : repli honnête sur "Autre" plutôt que de
      // laisser un texte affiché sans rapport avec le code réellement mémorisé.
      codeInput.value = config.autreCode;
      if (autreInput) autreInput.value = typed;
      if (autreWrap) autreWrap.style.display = '';
    }

    search.addEventListener('focus', function () {
      try {
        // Champ déjà rempli (valeur importée/déjà enregistrée) : sélectionne tout le texte pour
        // qu'une frappe immédiate REMPLACE la valeur affichée au lieu de s'y concaténer.
        // `hasPickedThisSession` est réinitialisé : reprendre l'édition d'un champ déjà validé doit
        // de nouveau pouvoir committer un changement au prochain blur/submit.
        hasPickedThisSession = false;
        if (search.value !== '') search.select();
        showSuggestionsOrSearch();
      } catch (e) {
        warn(tag, 'exception in focus handler:', e);
      }
    });
    search.addEventListener('input', function () {
      try {
        var codeBefore = codeInput.value;
        hasPickedThisSession = false;
        showSuggestionsOrSearch();
        log(tag, 'hidden code before this input event:', JSON.stringify(codeBefore), '- after (unchanged until a pick/blur/submit commits it):', JSON.stringify(codeInput.value));
      } catch (e) {
        warn(tag, 'exception in input handler (this is what would silently swallow a keystroke in a real browser):', e);
      }
    });

    search.addEventListener('blur', function () {
      try {
        // Synchrone — AUCUN délai : un clic sur un résultat ne déclenche jamais ce `blur` (voir
        // preventDefault() ci-dessus), donc rien n'a besoin d'attendre ici.
        var codeBefore = codeInput.value;
        closeResults();
        commitPendingValue();
        log(tag, 'blur -> committed. hidden code before:', JSON.stringify(codeBefore), '- after:', JSON.stringify(codeInput.value));
      } catch (e) {
        warn(tag, 'exception in blur handler:', e);
      }
    });

    search.addEventListener('keydown', function (event) {
      try {
        if (event.key === 'ArrowDown') {
          if (resultsList.hidden) { showSuggestionsOrSearch(); return; }
          event.preventDefault();
          highlight(Math.min(activeIndex + 1, resultsList.children.length - 1));
        } else if (event.key === 'ArrowUp') {
          if (resultsList.hidden) return;
          event.preventDefault();
          highlight(Math.max(activeIndex - 1, 0));
        } else if (event.key === 'Enter') {
          // Ne JAMAIS laisser Entrée soumettre le formulaire cheval entier depuis ce champ : soit
          // on valide le résultat mis en évidence (ou le premier de la liste), soit on committe la
          // saisie libre exactement comme une perte de focus.
          event.preventDefault();
          if (!resultsList.hidden && currentEntries.length > 0) {
            var index = activeIndex >= 0 && activeIndex < currentEntries.length ? activeIndex : 0;
            selectEntry(currentEntries[index].code, currentEntries[index].label);
          } else {
            closeResults();
            commitPendingValue();
          }
        } else if (event.key === 'Escape') {
          closeResults();
        }
      } catch (e) {
        warn(tag, 'exception in keydown handler:', e);
      }
    });

    // Filet de sécurité : au moment où le formulaire est réellement soumis (bouton natif
    // Enregistrer/Publier, ou Entrée dans un AUTRE champ du formulaire), committe ce champ s'il ne
    // l'a pas déjà été — couvre tout enchaînement où `blur` n'aurait, pour une raison quelconque,
    // pas eu l'occasion de se déclencher avant la soumission.
    var form = field.closest('form');
    if (form) {
      form.addEventListener('submit', function () {
        try {
          commitPendingValue();
        } catch (e) {
          warn(tag, 'exception in submit safety-net handler:', e);
        }
      });
    }

    // Tout ce qui précède a réussi sans exception : le composant de recherche peut désormais
    // remplacer le <select> de secours en toute sécurité (voir activateField() ci-dessus).
    activateField(field, codeInput, autreInput);
  }
})();

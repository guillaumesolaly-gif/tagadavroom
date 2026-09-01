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
 * - Saisie -> recherche LOCALE (aucun aller-retour serveur, le référentiel entier tient en mémoire)
 *   sur le code, le libellé IFCE, le libellé GWS et les alias — accents/casse ignorés — avec les
 *   correspondances en DÉBUT de champ affichées avant les correspondances partielles.
 * - "Autre — préciser" reste TOUJOURS proposé en dernière position (§7 : filet de sécurité, jamais
 *   un repli automatique sur une valeur mal reconnue).
 * - Un choix cliqué (ou validé au clavier) fixe le CODE réellement soumis (champ caché) et affiche
 *   son libellé GWS dans le champ de recherche ; "Autre" affiche/vide le champ de précision libre.
 * - Une saisie libre jamais validée par un clic (perte de focus sans sélection) retombe
 *   automatiquement sur "Autre" + le texte tapé s'il est non vide, ou sur une valeur vide sinon —
 *   jamais un champ affichant un texte qui ne correspond plus au code réellement mémorisé.
 *
 * SANS JAVASCRIPT : le champ cassé texte + hidden reste soumissible tel quel (le code déjà
 * enregistré, le cas échéant, continue d'être envoyé sans modification) — seule l'interactivité de
 * recherche est absente, jamais un formulaire bloqué.
 */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    var config = window.gwseqRaceReferentiel;
    if (!config || !Array.isArray(config.entries)) return;

    var fields = document.querySelectorAll('[data-gwseq-race-field]');
    fields.forEach(function (field) {
      initField(field, config);
    });
  });

  function normalize(text) {
    text = String(text == null ? '' : text);
    if (text.normalize) {
      text = text.normalize('NFD').replace(/[̀-ͯ]/g, '');
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

  function initField(field, config) {
    var search = field.querySelector('.gwseq-race-field__search');
    var codeInput = field.querySelector('.gwseq-race-field__code');
    var resultsList = field.querySelector('.gwseq-race-field__results');
    var autreWrap = field.querySelector('.gwseq-race-field__autre-wrap');
    var autreInput = field.querySelector('.gwseq-race-field__autre');
    if (!search || !codeInput || !resultsList) return;

    var hasPickedThisSession = false;

    function closeResults() {
      resultsList.hidden = true;
      resultsList.innerHTML = '';
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

    function renderResults(entries) {
      resultsList.innerHTML = '';
      entries.forEach(function (entry) {
        var item = document.createElement('li');
        item.setAttribute('role', 'option');
        item.tabIndex = -1;
        var typeMark = entry.type === 'appellation' ? ' (' + entry.code + ')' : (entry.label !== entry.code ? ' (' + entry.code + ')' : '');
        item.textContent = entry.label + typeMark;
        item.addEventListener('mousedown', function (event) {
          // mousedown (pas click) : se déclenche AVANT le blur du champ de recherche, qui
          // fermerait sinon la liste avant que le clic n'ait eu l'occasion de la cibler.
          event.preventDefault();
          selectEntry(entry.code, entry.label);
        });
        resultsList.appendChild(item);
      });

      var autreItem = document.createElement('li');
      autreItem.setAttribute('role', 'option');
      autreItem.tabIndex = -1;
      autreItem.className = 'gwseq-race-field__autre-option';
      autreItem.textContent = config.i18n.autre;
      autreItem.addEventListener('mousedown', function (event) {
        event.preventDefault();
        selectEntry(config.autreCode, '');
        search.value = '';
      });
      resultsList.appendChild(autreItem);

      resultsList.hidden = false;
      search.setAttribute('aria-expanded', 'true');
    }

    function showSuggestionsOrSearch() {
      var query = search.value;
      if (query.trim() === '') {
        renderResults(config.suggestions || []);
      } else {
        renderResults(searchEntries(config.entries, query, 20));
      }
    }

    search.addEventListener('focus', showSuggestionsOrSearch);
    search.addEventListener('input', function () {
      hasPickedThisSession = false;
      showSuggestionsOrSearch();
    });

    search.addEventListener('blur', function () {
      // Laisse le temps au mousedown d'un résultat de s'exécuter avant de refermer/retomber sur
      // "Autre" — voir la note sur mousedown ci-dessus.
      window.setTimeout(function () {
        closeResults();
        if (hasPickedThisSession) return;
        var typed = search.value.trim();
        if (typed === '') {
          codeInput.value = '';
          if (autreWrap) autreWrap.style.display = 'none';
          return;
        }
        // Saisie libre jamais validée par un clic : repli honnête sur "Autre" plutôt que de
        // laisser un texte affiché sans rapport avec le code réellement mémorisé.
        codeInput.value = config.autreCode;
        if (autreInput) autreInput.value = typed;
        if (autreWrap) autreWrap.style.display = '';
      }, 150);
    });

    document.addEventListener('keydown', function (event) {
      if (resultsList.hidden) return;
      if (!field.contains(document.activeElement)) return;
      if (event.key === 'Escape') closeResults();
    });
  }
})();

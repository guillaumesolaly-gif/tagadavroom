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
 *   valeur affichée plutôt que de s'y concaténer (correctif runtime — voir plus bas).
 * - Saisie -> recherche LOCALE (aucun aller-retour serveur, le référentiel entier tient en mémoire)
 *   sur le code, le libellé IFCE, le libellé GWS et les alias — accents/casse ignorés — avec les
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
 * CORRECTIF RUNTIME (recette post-livraison 0.14.0) — « autocomplétion inutilisable en édition » :
 * deux causes racines distinctes, corrigées ensemble :
 * 1. Le champ de recherche n'était JAMAIS vidé/sélectionné au focus : reprendre la saisie sur un
 *    champ déjà rempli (ex. « Selle Français » importé depuis IFCE) concaténait le texte tapé
 *    (« Selle FrançaisOLD ») au lieu de le remplacer — une chaîne qui ne correspond à RIEN du
 *    référentiel, d'où l'impression qu'« aucune suggestion n'apparaît ». Corrigé par une sélection
 *    intégrale du texte au focus (`search.select()`), comportement standard d'un champ de
 *    recherche.
 * 2. La mise à jour du code cru (`codeInput.value`) après une saisie libre non validée par un clic
 *    était différée de 150 ms APRÈS l'événement `blur` (délai initialement pensé pour laisser un
 *    `mousedown` sur un résultat s'exécuter avant la fermeture de la liste). Or un clic sur le
 *    bouton natif « Enregistrer »/« Publier » de WordPress déclenche la soumission du formulaire
 *    QUASI IMMÉDIATEMENT après le `blur` du champ — largement avant l'écoulement de ces 150 ms — de
 *    sorte que le formulaire partait avec l'ANCIEN code caché, jamais mis à jour : la race importée
 *    « revenait » après enregistrement, et il était impossible de vider le champ. Un `mousedown`
 *    avec `preventDefault()` sur un résultat empêche NATIVEMENT le focus de quitter le champ de
 *    recherche (donc `blur` ne se déclenche JAMAIS lors d'un clic sur un résultat) : le délai n'a
 *    donc plus aucune raison d'exister et la mise à jour est désormais SYNCHRONE sur `blur`. Un
 *    filet de sécurité supplémentaire committe explicitement chaque champ Race au moment de la
 *    SOUMISSION du formulaire (avant même que `blur` ait pu se déclencher dans un enchaînement
 *    clavier inhabituel), et la touche Entrée à l'intérieur du champ de recherche ne soumet plus
 *    jamais le formulaire par accident (elle valide le premier résultat affiché, ou committe la
 *    saisie libre exactement comme une perte de focus).
 *
 * SANS JAVASCRIPT : le champ caché texte + hidden reste soumissible tel quel (le code déjà
 * enregistré, le cas échéant, continue d'être envoyé sans modification) — seule l'interactivité de
 * recherche est absente, jamais un formulaire bloqué.
 */
(function () {
  'use strict';

  // Instrumentation TEMPORAIRE (recette runtime post-0.14.1, à retirer une fois le composant
  // confirmé fonctionnel en conditions réelles) : quelques traces `console.*` à faible volume (une
  // ligne par étape-clé d'initialisation, jamais par frappe/interaction) préfixées "[gwseq-race]"
  // pour permettre de diagnostiquer, directement depuis la console du navigateur sur une vraie
  // fiche WordPress, où l'exécution diverge éventuellement d'un environnement de test — script
  // chargé, configuration présente et son nombre d'entrées, nombre de champs trouvés, résultat de
  // l'initialisation de CHAQUE champ. Sans effet sur le fonctionnement : aucune de ces lignes ne
  // modifie le comportement, uniquement des lectures d'état déjà calculées par ailleurs.
  function log() {
    if (window.console && window.console.log) window.console.log.apply(window.console, ['[gwseq-race]'].concat(Array.prototype.slice.call(arguments)));
  }
  function warn() {
    if (window.console && window.console.warn) window.console.warn.apply(window.console, ['[gwseq-race]'].concat(Array.prototype.slice.call(arguments)));
  }

  // Messages ASCII uniquement, y compris dans les chaines de caracteres (pas seulement dans le
  // code executable lui-meme) : cette instrumentation vise justement a etre fiable dans un
  // environnement de production potentiellement fragile a l'encodage (voir normalize() plus bas) ;
  // un accent dans une simple chaine de log ne casserait pas le script, mais autant ne prendre
  // aucun risque, meme cosmetique, dans ce fichier precis.
  log('script loaded');

  document.addEventListener('DOMContentLoaded', function () {
    log('DOMContentLoaded fired');
    var config = window.gwseqRaceReferentiel;
    if (!config || !Array.isArray(config.entries)) {
      warn('window.gwseqRaceReferentiel missing or invalid - the referential was not correctly passed by wp_localize_script() (check that gwseq_enqueue_race_referentiel_assets() actually runs on this screen):', config);
      return;
    }
    log('referential loaded, entries:', config.entries.length, 'suggestions:', (config.suggestions || []).length);

    var fields = document.querySelectorAll('[data-gwseq-race-field]');
    log('[data-gwseq-race-field] fields found in the DOM:', fields.length);
    if (fields.length === 0) {
      warn('no [data-gwseq-race-field] field found - check that gwseq_render_race_referentiel_field() was actually rendered on this screen (identity and/or pedigree)');
    }
    fields.forEach(function (field, index) {
      // Un champ malformé (structure inattendue) ne doit jamais empêcher l'initialisation des
      // AUTRES champs de la page (Array.prototype.forEach interrompt son parcours à la première
      // exception non rattrapée dans son callback) — chaque champ reste isolé des autres.
      try {
        initField(field, config);
        log('field #' + index + ' (id=' + (field.querySelector('.gwseq-race-field__search') || {}).id + ') initialized successfully');
      } catch (e) {
        warn('field #' + index + ' - initialization failed:', e);
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
      // SYNTAXE qui tuerait silencieusement TOUT le script au chargement (correctif runtime post
      // 0.14.1 : symptôme observé en environnement WordPress réel — "rien ne fonctionne du tout" —
      // jamais reproductible avec ce même fichier exécuté directement, par exemple via Node, où le
      // texte source reste toujours fidèle). Un échappement \uXXXX est constitué exclusivement de
      // caractères ASCII : il ne peut structurellement plus être corrompu par un problème
      // d'encodage de fichier.
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

  function initField(field, config) {
    var search = field.querySelector('.gwseq-race-field__search');
    var codeInput = field.querySelector('.gwseq-race-field__code');
    var resultsList = field.querySelector('.gwseq-race-field__results');
    var autreWrap = field.querySelector('.gwseq-race-field__autre-wrap');
    var autreInput = field.querySelector('.gwseq-race-field__autre');
    if (!search || !codeInput || !resultsList) return;

    var hasPickedThisSession = false;
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
          // course avec une éventuelle fermeture/committe sur blur (voir le correctif documenté en
          // tête de fichier).
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
      // Champ déjà rempli (valeur importée/déjà enregistrée) : sélectionne tout le texte pour
      // qu'une frappe immédiate REMPLACE la valeur affichée au lieu de s'y concaténer (cause
      // racine du bug « aucune suggestion n'apparaît » — voir le correctif documenté en tête de
      // fichier). `hasPickedThisSession` est réinitialisé : reprendre l'édition d'un champ déjà
      // validé doit de nouveau pouvoir committer un changement au prochain blur/submit.
      hasPickedThisSession = false;
      if (search.value !== '') search.select();
      showSuggestionsOrSearch();
    });
    search.addEventListener('input', function () {
      hasPickedThisSession = false;
      showSuggestionsOrSearch();
    });

    search.addEventListener('blur', function () {
      // Synchrone — AUCUN délai : un clic sur un résultat ne déclenche jamais ce `blur` (voir
      // preventDefault() ci-dessus), donc rien n'a besoin d'attendre ici. Un délai a longtemps
      // introduit une course perdue face à un clic sur "Enregistrer"/"Publier", qui soumet le
      // formulaire avant que la valeur ne soit committée (voir le correctif documenté en tête de
      // fichier).
      closeResults();
      commitPendingValue();
    });

    search.addEventListener('keydown', function (event) {
      if (event.key === 'ArrowDown') {
        if (resultsList.hidden) { showSuggestionsOrSearch(); return; }
        event.preventDefault();
        highlight(Math.min(activeIndex + 1, resultsList.children.length - 1));
      } else if (event.key === 'ArrowUp') {
        if (resultsList.hidden) return;
        event.preventDefault();
        highlight(Math.max(activeIndex - 1, 0));
      } else if (event.key === 'Enter') {
        // Ne JAMAIS laisser Entrée soumettre le formulaire cheval entier depuis ce champ : soit on
        // valide le résultat mis en évidence (ou le premier de la liste), soit on committe la
        // saisie libre exactement comme une perte de focus — jamais un enregistrement accidentel
        // de toute la fiche avant que la race n'ait été réellement prise en compte.
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
    });

    // Filet de sécurité : au moment où le formulaire est réellement soumis (bouton natif
    // Enregistrer/Publier, ou Entrée dans un AUTRE champ du formulaire), committe ce champ s'il ne
    // l'a pas déjà été — couvre tout enchaînement où `blur` n'aurait, pour une raison quelconque,
    // pas eu l'occasion de se déclencher avant la soumission.
    var form = field.closest('form');
    if (form) {
      form.addEventListener('submit', function () {
        commitPendingValue();
      });
    }
  }
})();

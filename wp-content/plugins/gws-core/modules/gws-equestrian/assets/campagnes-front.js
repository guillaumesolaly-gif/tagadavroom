/**
 * Rendu front des Mises en avant (§L/§M) — ce script ne construit AUCUN balisage : la Pop-in et la
 * Sticky bar sont déjà présentes dans le DOM (produites côté serveur par
 * gwseq_render_popin_markup()/gwseq_render_sticky_bar_markup(), voir includes/campagnes-front.php,
 * les MÊMES fonctions que l'aperçu BO). Ce script se contente de déclencher leur affichage au bon
 * moment, d'appliquer la logique de fréquence (§F, entièrement côté client, sans identifiant ni
 * tracking), et de gérer la fermeture/l'accessibilité.
 *
 * Fréquence (§F) : une clé de stockage par Pop-in, basée sur son ID.
 * - "À chaque visite" : aucun stockage, jamais de suppression.
 * - "Une fois par session" : `sessionStorage` (effacé à la fermeture de l'onglet/navigateur).
 * - "Une fois tous les X jours" : `localStorage`, horodatage comparé à `Date.now()`.
 * La marque est posée AU MOMENT DE L'AFFICHAGE (couvre le cas où le visiteur ferme la Pop-in
 * comme le cas où il quitte la page sans y toucher) — fermer la Pop-in ne fait que masquer un
 * élément déjà marqué comme "montré", ce qui satisfait naturellement "la fermeture compte comme
 * une exposition" sans double logique.
 *
 * Exit intent (§E) : desktop uniquement, détecté via `matchMedia('(hover: hover) and
 * (pointer: fine)')` — jamais un sniffing de user-agent. Sur un terminal sans hover (mobile/
 * tactile), le déclencheur "Intention de sortie" ne se déclenche simplement jamais (aucun
 * fallback automatique inventé, comme demandé).
 */
(function () {
  'use strict';

  var STORAGE_PREFIX = 'gwseq_popin_';

  function storageKey(popinId) {
    return STORAGE_PREFIX + popinId;
  }

  function frequenceAutorise(popin) {
    var mode = popin.getAttribute('data-gwseq-frequence');
    var popinId = popin.getAttribute('data-gwseq-popin-id');
    if (mode === 'session') {
      try { return !window.sessionStorage.getItem(storageKey(popinId)); } catch (e) { return true; }
    }
    if (mode === 'days') {
      var jours = parseInt(popin.getAttribute('data-gwseq-jours'), 10) || 7;
      try {
        var lastShown = window.localStorage.getItem(storageKey(popinId));
        if (!lastShown) return true;
        var elapsedMs = Date.now() - parseInt(lastShown, 10);
        return elapsedMs >= jours * 24 * 60 * 60 * 1000;
      } catch (e) { return true; }
    }
    return true; // "every_visit" : jamais de suppression
  }

  function marquerExposition(popin) {
    var mode = popin.getAttribute('data-gwseq-frequence');
    var popinId = popin.getAttribute('data-gwseq-popin-id');
    try {
      if (mode === 'session') window.sessionStorage.setItem(storageKey(popinId), '1');
      if (mode === 'days') window.localStorage.setItem(storageKey(popinId), String(Date.now()));
    } catch (e) { /* stockage indisponible (navigation privée stricte...) : jamais bloquant */ }
  }

  function estDesktopAvecSurvol() {
    return window.matchMedia && window.matchMedia('(hover: hover) and (pointer: fine)').matches;
  }

  function initPopin() {
    var popin = document.querySelector('.gwseq-popin');
    if (!popin) return;
    if (!frequenceAutorise(popin)) return;

    var previouslyFocused = null;

    function afficher() {
      if (popin.classList.contains('gwseq-popin--visible')) return;
      marquerExposition(popin);
      previouslyFocused = document.activeElement;
      popin.classList.add('gwseq-popin--visible');
      popin.removeAttribute('aria-hidden');
      var closeButton = popin.querySelector('.gwseq-popin__close');
      if (closeButton) closeButton.focus();
      document.addEventListener('keydown', onKeydown);
    }

    function fermer() {
      popin.classList.remove('gwseq-popin--visible');
      popin.setAttribute('aria-hidden', 'true');
      document.removeEventListener('keydown', onKeydown);
      if (previouslyFocused && typeof previouslyFocused.focus === 'function') previouslyFocused.focus();
    }

    function onKeydown(event) {
      if (event.key === 'Escape' || event.key === 'Esc') {
        fermer();
        return;
      }
      if (event.key === 'Tab') {
        var focusables = popin.querySelectorAll('button, a[href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
        if (!focusables.length) return;
        var first = focusables[0];
        var last = focusables[focusables.length - 1];
        if (event.shiftKey && document.activeElement === first) {
          event.preventDefault();
          last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
          event.preventDefault();
          first.focus();
        }
      }
    }

    var closeButton = popin.querySelector('.gwseq-popin__close');
    if (closeButton) closeButton.addEventListener('click', fermer);

    var mode = popin.getAttribute('data-gwseq-declenchement');
    if (mode === 'immediate') {
      afficher();
    } else if (mode === 'delay') {
      var secondes = parseInt(popin.getAttribute('data-gwseq-delai'), 10) || 5;
      window.setTimeout(afficher, secondes * 1000);
    } else if (mode === 'scroll') {
      var seuil = parseInt(popin.getAttribute('data-gwseq-scroll'), 10) || 50;
      var onScroll = function () {
        var doc = document.documentElement;
        var scrollable = doc.scrollHeight - doc.clientHeight;
        var pourcentage = scrollable > 0 ? (doc.scrollTop / scrollable) * 100 : 100;
        if (pourcentage >= seuil) {
          window.removeEventListener('scroll', onScroll);
          afficher();
        }
      };
      window.addEventListener('scroll', onScroll, { passive: true });
    } else if (mode === 'exit_intent') {
      if (!estDesktopAvecSurvol()) return; // desktop uniquement, aucun fallback mobile automatique
      var onMouseOut = function (event) {
        if (event.clientY > 0 || event.relatedTarget) return;
        document.removeEventListener('mouseout', onMouseOut);
        afficher();
      };
      document.addEventListener('mouseout', onMouseOut);
    }
  }

  function initStickyBar() {
    var bar = document.querySelector('.gwseq-sticky-bar');
    if (!bar) return;
    var barId = bar.getAttribute('data-gwseq-sticky-id');
    var key = 'gwseq_sticky_' + barId;
    try {
      if (barId && window.sessionStorage.getItem(key)) { bar.remove(); return; }
    } catch (e) { /* stockage indisponible : la barre reste affichée */ }

    var closeButton = bar.querySelector('.gwseq-sticky-bar__close');
    if (closeButton) {
      closeButton.addEventListener('click', function () {
        bar.remove();
        try { window.sessionStorage.setItem(key, '1'); } catch (e) { /* non bloquant */ }
      });
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    initPopin();
    initStickyBar();
  });
})();

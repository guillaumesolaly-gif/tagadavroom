/**
 * Navigation par onglets de la fiche Cheval (ajustement UX post-recette de l'Étape 6) —
 * JavaScript natif, aucune dépendance. Construit la barre d'onglets au chargement de la page et
 * se contente de masquer/afficher les meta boxes déjà présentes dans le DOM, SANS JAMAIS LES
 * DÉPLACER : leur position réelle dans l'arbre ne change jamais, ce qui préserve intégralement le
 * comportement natif de WordPress sur ces boîtes (repliage au clic sur le titre, etc.).
 *
 * SANS JAVASCRIPT : ce script ne s'exécute jamais, donc aucune boîte n'est jamais masquée — la
 * fiche reste utilisable exactement comme avant cet ajustement (blocs empilés normalement).
 *
 * Configuration (regroupement onglet -> identifiants de meta box) fournie par PHP via
 * wp_localize_script() (voir includes/cheval-admin-tabs.php) — jamais codée en dur ici, pour
 * qu'une seule source de vérité décide de cette organisation.
 */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    if (typeof window.gwseqChevalTabs === 'undefined') return;
    var config = window.gwseqChevalTabs;
    var tabsConfig = Array.isArray(config.tabs) ? config.tabs : [];
    if (!tabsConfig.length) return;

    // Garde défensive : #post-body-content confirme qu'on est bien sur l'écran classique en deux
    // colonnes (édition par blocs désactivée pour Cheval, voir includes/cheval-editor.php) — mais
    // n'est PAS un ancêtre DOM de #normal-sortables (ce sont deux enfants distincts de #post-body,
    // #normal-sortables vivant dans #postbox-container-2 : voir correctif ci-dessous).
    var postbody = document.getElementById('post-body-content');
    var normalSortables = document.getElementById('normal-sortables');
    if (!postbody || !normalSortables) return; // écran non standard (ex. éditeur par blocs) : rien à faire, blocs empilés normalement

    var STORAGE_KEY = 'gwseq_cheval_active_tab';

    // Résout chaque onglet en boîtes RÉELLEMENT présentes sur cet écran — un identifiant qui ne
    // correspond à aucun élément (ex. la boîte "Pedigree résolu", dev-only) est simplement ignoré,
    // jamais une erreur. Un onglet qui ne recueille aucune boîte existante n'est pas affiché.
    var tabs = [];
    tabsConfig.forEach(function (tabDef) {
      var boxes = (tabDef.boxes || [])
        .map(function (boxId) { return document.getElementById(boxId); })
        .filter(function (el) { return el !== null; });
      if (!boxes.length) return;
      tabs.push({ id: tabDef.id, label: tabDef.label, boxes: boxes });
    });
    if (!tabs.length) return;

    // --- Construction de la barre d'onglets (§5 : de vrais contrôles accessibles, jamais une
    // rangée de <div> cliquables) — réutilise les classes natives .nav-tab-wrapper/.nav-tab de
    // WordPress (déjà chargées en admin, mêmes que les écrans de réglages à onglets du cœur) pour
    // l'apparence, sans feuille de style dédiée à réinventer pour l'essentiel du rendu visuel. Le
    // rôle explicite (tablist/tab) prime sur la sémantique native de la balise pour les
    // technologies d'assistance, quelle que soit la balise choisie. ---
    var wrapper = document.createElement('div');
    wrapper.className = 'gwseq-cheval-tabs';

    var tablist = document.createElement('div');
    tablist.className = 'nav-tab-wrapper gwseq-cheval-tabs__list';
    tablist.setAttribute('role', 'tablist');
    tablist.setAttribute('aria-label', config.tablistLabel || '');
    wrapper.appendChild(tablist);

    var initialActiveId = null;
    try {
      var stored = window.sessionStorage.getItem(STORAGE_KEY);
      if (stored && tabs.some(function (t) { return t.id === stored; })) initialActiveId = stored;
    } catch (e) {
      // sessionStorage indisponible (navigation privée restrictive, etc.) : on continue sans état
      // persistant, jamais une erreur bloquante — l'onglet actif redevient simplement le premier.
    }
    if (!initialActiveId) initialActiveId = tabs[0].id;

    var tabButtons = [];
    tabs.forEach(function (tab, index) {
      var button = document.createElement('button');
      button.type = 'button';
      button.className = 'nav-tab gwseq-cheval-tabs__tab';
      button.id = 'gwseq-tab-' + tab.id;
      button.setAttribute('role', 'tab');
      button.setAttribute('aria-controls', tab.boxes.map(function (box) { return box.id; }).join(' '));
      button.textContent = tab.label;
      tablist.appendChild(button);
      tabButtons.push(button);

      // Chaque boîte contrôlée par cet onglet devient sémantiquement son panneau — aria-controls
      // accepte une liste d'identifiants (type "ID reference list" de la spécification WAI-ARIA),
      // ce qui permet de rattacher plusieurs boîtes à un même onglet sans les regrouper dans un
      // nouvel élément conteneur (qui obligerait à déplacer ces boîtes dans le DOM).
      tab.boxes.forEach(function (box) {
        box.setAttribute('role', 'tabpanel');
        box.setAttribute('aria-labelledby', button.id);
      });
    });

    // --- Bouton d'enregistrement rapide (§4) : reproduit le texte du VRAI bouton natif
    // (#publish, dans la boîte "Publier" de la colonne latérale — jamais un texte codé en dur qui
    // désynchroniserait de ce que WordPress affiche réellement, ex. "Publier" vs "Mettre à jour").
    // Se contente de déclencher un clic sur ce bouton réel : aucun second mécanisme de
    // sauvegarde, aucun appel direct à form.submit() qui contournerait un éventuel gestionnaire
    // natif attaché à ce bouton. ---
    var nativeSubmitButton = document.getElementById('publish');
    var saveButton = document.createElement('button');
    saveButton.type = 'button';
    saveButton.className = 'button button-primary gwseq-cheval-tabs__save';
    saveButton.textContent = (nativeSubmitButton && nativeSubmitButton.value) ? nativeSubmitButton.value : (config.saveLabelFallback || '');
    if (nativeSubmitButton) {
      saveButton.addEventListener('click', function () {
        nativeSubmitButton.click();
      });
    } else {
      saveButton.disabled = true; // aucun bouton natif trouvé (écran non standard) : jamais inventer une soumission de repli
    }
    wrapper.appendChild(saveButton);

    // CORRECTIF BLOQUANT (régression post-livraison) : #post-body-content et #normal-sortables ne
    // sont PAS dans une relation parent/enfant sur l'écran classique de WordPress (ce sont deux
    // enfants distincts de #post-body — #normal-sortables vit dans #postbox-container-2, une boîte
    // séparée de la colonne principale, voir wp-admin/edit-form-advanced.php). Un
    // `postbody.insertBefore(wrapper, normalSortables)` lève donc systématiquement une
    // `DOMException` (le nœud de référence n'est pas un enfant du nœud appelant), ce qui arrêtait
    // l'exécution du script AVANT même l'insertion de la barre d'onglets : aucun onglet
    // n'apparaissait jamais. On insère désormais la barre comme PREMIER enfant de
    // #normal-sortables lui-même — l'élément qui contient réellement toutes les boîtes de la
    // colonne principale que les onglets pilotent — ce qui la place bien en haut de cette colonne,
    // sans dépendre d'une hypothèse de structure DOM erronée.
    normalSortables.insertBefore(wrapper, normalSortables.firstChild);

    function activateTab(tabId, opts) {
      opts = opts || {};
      tabs.forEach(function (tab, index) {
        var isActive = tab.id === tabId;
        var button = tabButtons[index];
        button.classList.toggle('nav-tab-active', isActive);
        button.setAttribute('aria-selected', isActive ? 'true' : 'false');
        button.tabIndex = isActive ? 0 : -1;
        tab.boxes.forEach(function (box) {
          box.style.display = isActive ? '' : 'none';
        });
        if (isActive && opts.focus) button.focus();
      });
      try {
        window.sessionStorage.setItem(STORAGE_KEY, tabId);
      } catch (e) {
        // Persistance best-effort uniquement (§3 : « si cela reste simple ») — jamais bloquant.
      }
    }

    tabButtons.forEach(function (button, index) {
      button.addEventListener('click', function () {
        activateTab(tabs[index].id);
      });
      // Navigation clavier standard du pattern ARIA "tabs" (activation automatique au
      // déplacement) : flèches gauche/droite pour circuler, Début/Fin pour les extrémités.
      button.addEventListener('keydown', function (event) {
        var targetIndex = null;
        if (event.key === 'ArrowRight') targetIndex = (index + 1) % tabButtons.length;
        else if (event.key === 'ArrowLeft') targetIndex = (index - 1 + tabButtons.length) % tabButtons.length;
        else if (event.key === 'Home') targetIndex = 0;
        else if (event.key === 'End') targetIndex = tabButtons.length - 1;
        if (targetIndex === null) return;
        event.preventDefault();
        activateTab(tabs[targetIndex].id, { focus: true });
      });
    });

    activateTab(initialActiveId);
  });
})();

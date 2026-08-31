/**
 * Navigation par onglets de la fiche Cheval (ajustement UX post-recette de l'Étape 6) —
 * JavaScript natif, aucune dépendance. Construit la barre d'onglets au chargement de la page et
 * se contente de masquer/afficher les meta boxes déjà présentes dans le DOM, SANS JAMAIS LES
 * DÉPLACER : leur position réelle dans l'arbre ne change jamais, ce qui préserve intégralement le
 * comportement natif de WordPress sur ces boîtes (repliage au clic sur le titre, etc.). SEULE
 * EXCEPTION, assumée : la boîte native "Image à la une" (`#postimagediv`) est RÉELLEMENT déplacée
 * dans l'onglet Médias (voir plus bas) — un simple masquage/affichage en place laissait un texte
 * renvoyant vers une boîte physiquement ailleurs, jugé non satisfaisant en recette.
 *
 * SANS JAVASCRIPT : ce script ne s'exécute jamais, donc aucune boîte n'est jamais masquée ni
 * déplacée — la fiche reste utilisable exactement comme avant cet ajustement (blocs empilés
 * normalement, Photo principale modifiable dans sa colonne latérale native).
 *
 * Configuration (regroupement onglet -> identifiants de meta box) fournie par PHP via
 * wp_localize_script() (voir includes/cheval-admin-tabs.php) — jamais codée en dur ici, pour
 * qu'une seule source de vérité décide de cette organisation.
 *
 * DEUX FILETS DE SÉCURITÉ (§4/§5 du correctif suite à la régression "onglet Identité vide") —
 * un système d'onglets ne doit JAMAIS pouvoir rendre une donnée existante inaccessible :
 * 1. VÉRIFICATION DE COHÉRENCE PRÉALABLE (une seule vérité) : chaque boîte gérée par un onglet
 *    doit porter la classe CSS `gwseq-tab-{id}` posée côté PHP par
 *    gwseq_register_cheval_admin_tab_postbox_classes() (filtre natif `postbox_classes_{page}_{id}`
 *    de WordPress, dérivé de la MÊME configuration que celle transmise ici) — si un identifiant
 *    résolu par getElementById() ne porte pas cette classe, la configuration PHP et le DOM réel ne
 *    concordent pas (ex. collision d'identifiant) : le script n'engage alors AUCUNE construction
 *    d'onglet, laissant l'écran intégralement dans son état natif empilé.
 * 2. VÉRIFICATION DE VISIBILITÉ RÉELLE APRÈS ACTIVATION : `offsetParent === null` détecte de façon
 *    fiable qu'un élément (ou un ancêtre) reste masqué par une règle CSS quelconque, quelle qu'en
 *    soit l'origine exacte (repli natif `.closed`, masquage Screen Options `.hide-if-js`, tout
 *    autre mécanisme non anticipé) — y compris quand cette règle utilise `!important` et qu'un
 *    simple `style.display = ''` ne suffit donc pas à la emporter. Si une boîte de l'onglet actif
 *    reste invisible malgré une tentative de levée de ces mécanismes connus, le système d'onglets
 *    se désactive intégralement et restaure la visibilité de TOUTES les boîtes gérées.
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
    var consistent = true;
    tabsConfig.forEach(function (tabDef) {
      var boxes = (tabDef.boxes || [])
        .map(function (boxId) { return document.getElementById(boxId); })
        .filter(function (el) { return el !== null; });
      if (!boxes.length) return;
      // Filet de sécurité n°1 (voir en-tête) : chaque boîte trouvée par identifiant doit aussi
      // porter la classe posée côté PHP pour ce même onglet — sinon la configuration transmise et
      // le DOM réellement rendu ne concordent pas, et il est plus sûr de ne construire AUCUN
      // onglet que de risquer de masquer une boîte mal identifiée.
      boxes.forEach(function (box) {
        if (!box.classList.contains('gwseq-tab-' + tabDef.id)) consistent = false;
      });
      tabs.push({ id: tabDef.id, label: tabDef.label, boxes: boxes });
    });
    if (!tabs.length || !consistent) return;

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

    // --- Intégration réelle de la Photo principale dans l'onglet Médias (ajustement demandé après
    // retour de recette) : EXCEPTION ASSUMÉE à la règle générale de ce script (jamais déplacer une
    // boîte, seulement la masquer/afficher en place) — un premier essai en masquant/affichant
    // #postimagediv EN PLACE (sa colonne latérale native) laissait, sous l'onglet Médias, un simple
    // texte renvoyant vers une boîte physiquement ailleurs à l'écran, jugé non satisfaisant. La
    // boîte native est donc RÉELLEMENT réinsérée, une seule fois, dans un emplacement dédié à
    // l'intérieur de la boîte Médias (#gwseq-cheval-media-photo-principale-slot, voir
    // includes/cheval-media.php) — un simple appendChild() qui déplace le nœud EXISTANT (jamais un
    // clone, jamais une recréation) : exactement le même mécanisme que le glisser-déposer natif de
    // WordPress entre colonnes, qui ne perd donc aucun gestionnaire d'événement déjà attaché
    // (wp.media()). AUCUNE DONNÉE DUPLIQUÉE : même nœud DOM, même attachment_id, la Featured Image
    // de WordPress reste l'unique source de vérité. Une fois déplacée, elle n'apparaît plus jamais
    // dans la colonne latérale, et hérite automatiquement de la visibilité de la boîte Médias (elle
    // en devient un DESCENDANT) — aucune logique de visibilité séparée n'est nécessaire pour elle.
    var photoPrincipaleSlot = document.getElementById('gwseq-cheval-media-photo-principale-slot');
    var postimagediv = document.getElementById('postimagediv');
    var postimagedivOriginalParent = null;
    var postimagedivOriginalNextSibling = null;
    if (photoPrincipaleSlot && postimagediv) {
      postimagedivOriginalParent = postimagediv.parentNode;
      postimagedivOriginalNextSibling = postimagediv.nextSibling;
      photoPrincipaleSlot.appendChild(postimagediv);
    }

    var fallbackTriggered = false;

    // Filet de sécurité n°2 (voir en-tête) : en cas d'échec de vérification de visibilité après
    // activation, on désactive intégralement le système d'onglets plutôt que de risquer de
    // laisser une donnée inaccessible — retire la barre injectée et restaure la visibilité de
    // TOUTES les boîtes gérées, exactement comme avant cet ajustement (empilées, jamais masquées).
    function disableTabsFallback() {
      if (fallbackTriggered) return;
      fallbackTriggered = true;
      tabs.forEach(function (tab) {
        tab.boxes.forEach(function (box) {
          box.style.display = '';
          box.classList.remove('closed');
          box.classList.remove('hide-if-js');
          box.removeAttribute('hidden');
          box.removeAttribute('role');
          box.removeAttribute('aria-labelledby');
        });
      });
      // La Photo principale, si réellement déplacée dans l'onglet Médias, est restaurée à sa
      // position native (colonne latérale) — la désactivation du système d'onglets doit rendre
      // l'écran exactement tel qu'avant son intervention, jamais une boîte laissée à un endroit
      // qui n'a de sens que si les onglets fonctionnent.
      if (postimagedivOriginalParent && postimagediv) {
        postimagedivOriginalParent.insertBefore(postimagediv, postimagedivOriginalNextSibling);
      }
      if (wrapper.parentNode) wrapper.parentNode.removeChild(wrapper);
      if (config.isDevEnvironment && config.fallbackNotice) {
        var notice = document.createElement('div');
        notice.className = 'notice notice-error gwseq-cheval-tabs__fallback-notice';
        notice.textContent = config.fallbackNotice;
        normalSortables.parentNode.insertBefore(notice, normalSortables);
      }
    }

    function activateTab(tabId, opts) {
      opts = opts || {};
      var activeTab = null;
      tabs.forEach(function (tab, index) {
        var isActive = tab.id === tabId;
        if (isActive) activeTab = tab;
        var button = tabButtons[index];
        button.classList.toggle('nav-tab-active', isActive);
        button.setAttribute('aria-selected', isActive ? 'true' : 'false');
        button.tabIndex = isActive ? 0 : -1;
        tab.boxes.forEach(function (box) {
          if (isActive) {
            // CORRECTIF (régression signalée en recette, onglet Identité vide) : une boîte que
            // WordPress avait laissée masquée par un mécanisme natif — repli au clic sur le titre
            // (classe `.closed`, qui ne masque en réalité que l'enfant `.inside`, pas la boîte
            // elle-même) OU préférence "Screen Options" mémorisée par utilisateur (classe
            // `.hide-if-js`, qui masque cette fois la boîte ENTIÈRE, en-tête compris, via une
            // règle CSS pouvant être `!important` — un simple `style.display = ''` ne suffit alors
            // JAMAIS à la emporter) — resterait invisible même une fois notre style rétabli sur le
            // conteneur. On lève donc systématiquement ces mécanismes connus pour chaque boîte de
            // l'onglet qui vient de s'activer (jamais pour les autres, de toute façon masquées).
            box.classList.remove('closed');
            box.classList.remove('hide-if-js');
            box.removeAttribute('hidden');
            box.style.display = '';
            var toggle = box.querySelector('.handlediv');
            if (toggle) toggle.setAttribute('aria-expanded', 'true');
          } else {
            box.style.display = 'none';
          }
        });
        if (isActive && opts.focus) button.focus();
      });

      // Vérification RÉELLE de visibilité (offsetParent est null si l'élément ou un ancêtre reste
      // masqué par une règle CSS quelconque, y compris !important — un simple style.display=''
      // n'y change rien) : si un mécanisme de masquage non anticipé persiste malgré la levée des
      // mécanismes connus ci-dessus, on force explicitement l'affichage avec la même priorité
      // qu'une règle !important ; si cela ne suffit toujours pas, filet de sécurité n°2.
      if (activeTab) {
        activeTab.boxes.forEach(function (box) {
          if (box.offsetParent === null) {
            box.style.setProperty('display', 'block', 'important');
          }
        });
        var stillHidden = activeTab.boxes.some(function (box) { return box.offsetParent === null; });
        if (stillHidden) disableTabsFallback();
      }

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

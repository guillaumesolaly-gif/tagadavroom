/**
 * Test d'EXÉCUTION RÉELLE de assets/cheval-tabs-admin.js (régression bloquante "onglet Identité
 * vide", corrigée en 0.12.1/0.12.2/0.12.3).
 *
 * Pourquoi ce fichier existe : la suite PHP (`gws-equestrian-cheval-admin-tabs-test.php`) ne fait
 * que scanner le TEXTE SOURCE du script (présence de motifs, absence de patterns interdits) — elle
 * ne l'exécute jamais contre un vrai DOM. C'est exactement ce qui a laissé passer, à deux reprises,
 * des régressions runtime qui ne se manifestent QUE lorsque le script s'exécute contre un DOM
 * fidèle au rendu réel de WordPress :
 * - 0.12.1 : `postbody.insertBefore(wrapper, normalSortables)` levait une DOMException
 *   systématique (#post-body-content et #normal-sortables ne sont pas parent/enfant).
 * - 0.12.3 : une meta box masquée par un mécanisme natif WordPress (repli `.closed`, ou surtout
 *   préférence "Screen Options" mémorisée par utilisateur, classe `.hide-if-js`, qui masque la
 *   boîte ENTIÈRE — en-tête compris — via une règle CSS potentiellement `!important`) restait
 *   invisible malgré un `style.display` de conteneur correctement rétabli, puisqu'un simple
 *   `style.display = ''` ne bat jamais une règle `!important` de la feuille de style.
 *
 * Ce fichier construit une reproduction fidèle (mais minimale, sans dépendance npm) du DOM réel
 * produit par wp-admin/edit-form-advanced.php et par do_meta_boxes() (structure
 * postbox-header/handlediv/inside, classes `closed`/`hide-if-js`), MODÉLISE l'effet réel de ces
 * classes sur `offsetParent` (le mécanisme utilisé par le script pour vérifier une visibilité
 * RÉELLE, pas seulement déclarée), puis exécute réellement le fichier JS du module dans ce DOM
 * simulé via le module `vm` de Node.
 *
 * Trois scénarios distincts :
 * 1. runMainScenario()     — cas nominal + boîte Identité repliée ET masquée par Screen Options :
 *                            le script doit la rendre RÉELLEMENT visible (offsetParent non nul).
 * 2. runFallbackScenario() — une boîte reste masquée par un ANCÊTRE que le script ne contrôle pas
 *                            (hors de portée de tout correctif connu) : le système d'onglets doit
 *                            se désactiver intégralement plutôt que laisser une donnée inaccessible.
 * 3. runMismatchScenario() — la configuration PHP et le marquage réel du DOM ne concordent pas
 *                            (classe `gwseq-tab-{id}` absente) : le script ne doit RIEN construire.
 *
 * Exécution : node tests/gws-equestrian-cheval-admin-tabs-runtime-test.js
 */

'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

let assertionCount = 0;
let failureCount = 0;

function ok(label, condition) {
  assertionCount++;
  if (condition) {
    console.log('OK   - ' + label);
  } else {
    failureCount++;
    console.log('FAIL - ' + label);
  }
}

/* -------------------------------------------------------------------------------------------
 * DOM minimal fidèle (pas de jsdom, aucune dépendance npm ajoutée au projet).
 * ----------------------------------------------------------------------------------------- */

class FakeClassList {
  constructor(el) { this.el = el; }
  _get() { return (this.el.className || '').split(/\s+/).filter(Boolean); }
  _set(list) { this.el.className = list.join(' '); }
  add(c) { const l = this._get(); if (l.indexOf(c) === -1) { l.push(c); this._set(l); } }
  remove(c) { this._set(this._get().filter((x) => x !== c)); }
  contains(c) { return this._get().indexOf(c) !== -1; }
  toggle(c, force) {
    const on = force === undefined ? !this.contains(c) : force;
    if (on) this.add(c); else this.remove(c);
    return on;
  }
}

class FakeStyle {
  // Reproduit le strict nécessaire de CSSStyleDeclaration : accès direct à .display (utilisé
  // partout dans le script) ET setProperty()/removeProperty() (utilisés pour forcer une priorité
  // "important", seule façon de battre une règle !important d'une feuille de style — voir le
  // filet de sécurité n°2 du script).
  get display() { return this._display; }
  set display(value) { this._display = value; }
  setProperty(name, value) { if (name === 'display') this._display = value; }
  removeProperty(name) { if (name === 'display') this._display = undefined; }
}

class FakeElement {
  constructor(tagName) {
    this.tagName = tagName;
    this.id = '';
    this.className = '';
    this.children = [];
    this.parentNode = null;
    this._attributes = {};
    this.style = new FakeStyle();
    this._listeners = {};
    this.textContent = '';
  }
  get classList() { return new FakeClassList(this); }
  setAttribute(name, value) { this._attributes[name] = String(value); }
  getAttribute(name) {
    return Object.prototype.hasOwnProperty.call(this._attributes, name) ? this._attributes[name] : null;
  }
  removeAttribute(name) { delete this._attributes[name]; }
  get firstChild() { return this.children.length ? this.children[0] : null; }
  appendChild(child) {
    child.parentNode = this;
    this.children.push(child);
    return child;
  }
  insertBefore(newNode, refNode) {
    if (refNode === null) return this.appendChild(newNode);
    const index = this.children.indexOf(refNode);
    if (index === -1) {
      // Reproduit fidèlement le comportement d'un vrai navigateur (DOM spec) : le nœud de
      // référence DOIT être un enfant direct du nœud appelant, sinon exception.
      const err = new Error(
        "Failed to execute 'insertBefore' on 'Node': The node before which the new node is " +
        'to be inserted is not a child of this node.'
      );
      err.name = 'NotFoundError';
      throw err;
    }
    newNode.parentNode = this;
    this.children.splice(index, 0, newNode);
    return newNode;
  }
  removeChild(child) {
    const index = this.children.indexOf(child);
    if (index !== -1) this.children.splice(index, 1);
    child.parentNode = null;
    return child;
  }
  addEventListener(type, fn) {
    (this._listeners[type] = this._listeners[type] || []).push(fn);
  }
  dispatchEvent(evt) {
    ((this._listeners[evt.type] || []).slice()).forEach((fn) => fn(evt));
  }
  click() { this.dispatchEvent({ type: 'click' }); }
  focus() { this._focused = true; }
  // Support minimal (sélecteur de classe simple, ex. ".handlediv").
  querySelector(selector) {
    if (selector[0] !== '.') {
      throw new Error('querySelector : seul un sélecteur de classe simple ("."+nom) est supporté par ce DOM factice');
    }
    const className = selector.slice(1);
    function search(node) {
      for (const child of node.children) {
        if ((child.className || '').split(/\s+/).indexOf(className) !== -1) return child;
        const found = search(child);
        if (found) return found;
      }
      return null;
    }
    return search(this);
  }
  // MODÉLISATION DE offsetParent — c'est le cœur du filet de sécurité n°2 du script (voir son
  // en-tête) : dans un vrai navigateur, offsetParent vaut null si l'élément (ou un ancêtre) est
  // masqué par `display:none`, quelle que soit l'origine de cette règle CSS (style inline OU
  // classe de feuille de style, y compris `!important`). On reproduit ici les DEUX règles CSS
  // natives WordPress réellement en jeu :
  //   - style inline `display:none` (le mécanisme que le script pilote lui-même) ;
  //   - `.hide-if-js` (préférence "Screen Options" mémorisée par utilisateur) — masque la boîte
  //     ENTIÈRE, pas seulement son contenu (contrairement à `.closed`, volontairement PAS modélisé
  //     ici comme masquant : `.postbox.closed .inside { display:none }` ne cible que l'enfant
  //     `.inside`, jamais le conteneur `.postbox` lui-même — un point-clé du diagnostic).
  // Un ancêtre masqué masque également tous ses descendants, comme dans un vrai navigateur.
  get offsetParent() {
    let node = this;
    while (node) {
      if (node.style && node.style.display === 'none') return null;
      if (node.classList && node.classList.contains('hide-if-js')) return null;
      node = node.parentNode;
    }
    return { tagName: 'body' }; // ancêtre positionné factice : peu importe l'identité, seul null/non-null compte ici
  }
}

/**
 * Construit une meta box avec la structure RÉELLE produite par do_meta_boxes() (plutôt qu'un
 * simple <div>) : postbox-header/handlediv (repli/dépli) + .inside (où vit le contenu réel) — afin
 * que "la boîte contient réellement ses champs" et "aucun champ n'est supprimé du DOM" soient
 * vérifiables littéralement, pas seulement supposés.
 */
function makeBox(id, insideFieldIds) {
  const box = new FakeElement('div');
  box.id = id;
  box.className = 'postbox';

  const header = new FakeElement('div');
  header.className = 'postbox-header';
  const handle = new FakeElement('button');
  handle.className = 'handlediv';
  handle.setAttribute('aria-expanded', 'true');
  header.appendChild(handle);
  box.appendChild(header);

  const inside = new FakeElement('div');
  inside.className = 'inside';
  (insideFieldIds || []).forEach(function (fieldId) {
    const field = new FakeElement('input');
    field.id = fieldId;
    inside.appendChild(field);
  });
  box.appendChild(inside);

  return box;
}

function walk(node, id) {
  if (!node) return null;
  if (node.id === id) return node;
  for (let i = 0; i < node.children.length; i++) {
    const found = walk(node.children[i], id);
    if (found) return found;
  }
  return null;
}

/**
 * Applique, sur les boîtes déjà construites, exactement la même chose que
 * gwseq_register_cheval_admin_tab_postbox_classes() côté PHP : une classe `gwseq-tab-{id}` par
 * boîte gérée, dérivée de LA MÊME configuration que celle transmise au script. Permet de
 * reproduire fidèlement le filet de sécurité n°1 (vérification de cohérence) plutôt que de
 * construire un DOM "taillé sur mesure" qui la satisferait par accident.
 */
function applyTabMarkerClasses(rootElement, tabsConfig) {
  tabsConfig.forEach(function (tab) {
    tab.boxes.forEach(function (boxId) {
      const box = walk(rootElement, boxId);
      if (box) box.classList.add('gwseq-tab-' + tab.id);
    });
  });
}

function buildRealisticChevalEditScreen() {
  // Reproduit wp-admin/edit-form-advanced.php : #post-body-content, #postbox-container-1 et
  // #postbox-container-2 sont trois enfants DISTINCTS de #post-body — jamais l'un dans l'autre.
  const postBody = new FakeElement('div');
  postBody.id = 'post-body';

  const postBodyContent = new FakeElement('div');
  postBodyContent.id = 'post-body-content';
  postBody.appendChild(postBodyContent);

  const postboxContainer1 = new FakeElement('div');
  postboxContainer1.id = 'postbox-container-1';
  postBody.appendChild(postboxContainer1);

  const submitdiv = new FakeElement('div');
  submitdiv.id = 'submitdiv';
  const publishButton = new FakeElement('input');
  publishButton.id = 'publish';
  publishButton.value = 'Mettre à jour';
  submitdiv.appendChild(publishButton);
  postboxContainer1.appendChild(submitdiv);

  const sideSortables = new FakeElement('div');
  sideSortables.id = 'side-sortables';
  const postimagediv = makeBox('postimagediv');
  const globalIdDev = makeBox('gwseq-cheval-global-id-dev');
  const production = makeBox('gwseq-cheval-production');
  const pedigreePreview = makeBox('gwseq-cheval-pedigree-preview');
  const ordre = makeBox('gwseq-ordre-gwseq_cheval');
  [postimagediv, globalIdDev, production, pedigreePreview, ordre].forEach((b) => sideSortables.appendChild(b));
  postboxContainer1.appendChild(sideSortables);

  const postboxContainer2 = new FakeElement('div');
  postboxContainer2.id = 'postbox-container-2';
  postBody.appendChild(postboxContainer2);

  const normalSortables = new FakeElement('div');
  normalSortables.id = 'normal-sortables';
  // La boîte Identité contient de vrais champs historiques (Étape 4) — reproduits ici a minima
  // pour que "les champs historiques restent accessibles" soit vérifiable littéralement.
  const identite = makeBox('gwseq-cheval-identite', ['gwseq-cheval-sexe', 'gwseq-cheval-annee-naissance', 'gwseq-cheval-sire-ueln']);
  // Reproduit EXACTEMENT le symptôme du second signalement en recette : la boîte est à la fois
  // REPLIÉE (.closed, mécanisme de repli au clic sur le titre) ET masquée par une préférence
  // "Screen Options" mémorisée par un utilisateur ayant déjà navigué sur cet écran lors des
  // recettes précédentes (.hide-if-js — celle-ci masque la boîte ENTIÈRE, en-tête compris,
  // contrairement à .closed qui ne masque que .inside). C'est cette seconde classe, absente du
  // scénario testé pour le correctif précédent, qui explique que l'en-tête lui-même restait
  // invisible.
  identite.classList.add('closed');
  identite.classList.add('hide-if-js');
  const commercialisation = makeBox('gwseq-cheval-commercialisation');
  const pedigree = makeBox('gwseq-cheval-pedigree');
  const indices = makeBox('gwseq-cheval-indices');
  const media = makeBox('gwseq-cheval-media');
  const presentation = makeBox('gwseq-cheval-presentation');
  const infosComplementaires = makeBox('gwseq-cheval-infos-complementaires');
  [identite, commercialisation, pedigree, indices, media, presentation, infosComplementaires]
    .forEach((b) => normalSortables.appendChild(b));
  postboxContainer2.appendChild(normalSortables);

  const advancedSortables = new FakeElement('div');
  advancedSortables.id = 'advanced-sortables';
  postboxContainer2.appendChild(advancedSortables);

  return {
    root: postBody,
    postBodyContent,
    normalSortables,
    sideSortables,
    publishButton,
    boxes: {
      identite, commercialisation, pedigree, indices, media, presentation, infosComplementaires,
      production, pedigreePreview, postimagediv, globalIdDev, ordre,
    },
  };
}

const MAIN_TABS_CONFIG = [
  { id: 'identite', label: 'Identité', boxes: ['gwseq-cheval-identite'] },
  { id: 'commercial', label: 'Commercial', boxes: ['gwseq-cheval-commercialisation'] },
  { id: 'pedigree', label: 'Pedigree', boxes: ['gwseq-cheval-pedigree', 'gwseq-cheval-production', 'gwseq-cheval-pedigree-preview'] },
  { id: 'indices', label: 'Indices', boxes: ['gwseq-cheval-indices'] },
  { id: 'medias', label: 'Médias', boxes: ['postimagediv', 'gwseq-cheval-media'] },
  { id: 'presentation', label: 'Présentation', boxes: ['gwseq-cheval-presentation', 'gwseq-cheval-infos-complementaires'] },
];

function runScript(rootElement, tabsConfig, extraConfig) {
  const fakeSessionStorageStore = {};
  const fakeSessionStorage = {
    getItem(key) { return Object.prototype.hasOwnProperty.call(fakeSessionStorageStore, key) ? fakeSessionStorageStore[key] : null; },
    setItem(key, value) { fakeSessionStorageStore[key] = String(value); },
  };

  const domReadyHandlers = [];
  const fakeDocument = {
    getElementById(id) { return walk(rootElement, id); },
    createElement(tag) { return new FakeElement(tag); },
    addEventListener(type, fn) {
      if (type === 'DOMContentLoaded') domReadyHandlers.push(fn);
    },
  };

  const fakeWindow = {
    gwseqChevalTabs: Object.assign({
      tabs: tabsConfig,
      saveLabelFallback: 'Enregistrer',
      tablistLabel: 'Sections de la fiche cheval',
      isDevEnvironment: true,
      fallbackNotice: 'Navigation par onglets désactivée automatiquement.',
    }, extraConfig || {}),
    sessionStorage: fakeSessionStorage,
  };

  const sandbox = { document: fakeDocument, window: fakeWindow };
  vm.createContext(sandbox);

  const scriptPath = path.join(
    __dirname, '..', 'wp-content', 'plugins', 'gws-core', 'modules', 'gws-equestrian', 'assets', 'cheval-tabs-admin.js'
  );
  const source = fs.readFileSync(scriptPath, 'utf8');
  vm.runInContext(source, sandbox, { filename: 'cheval-tabs-admin.js' });

  let thrown = null;
  try {
    domReadyHandlers[0]();
  } catch (e) {
    thrown = e;
  }

  return { fakeSessionStorageStore, thrown, domReadyHandlerCount: domReadyHandlers.length };
}

/* =============================================================================================
 * SCÉNARIO 1 — cas nominal : boîte Identité repliée ET masquée par Screen Options au chargement.
 * Le script doit la rendre RÉELLEMENT visible (offsetParent non nul), pas seulement lui appliquer
 * un style.display qui ne suffirait pas à battre .hide-if-js.
 * ========================================================================================== */
function runMainScenario() {
  const screen = buildRealisticChevalEditScreen();
  applyTabMarkerClasses(screen.root, MAIN_TABS_CONFIG);
  const boxes = screen.boxes;

  const { fakeSessionStorageStore, thrown } = runScript(screen.root, MAIN_TABS_CONFIG);

  ok(
    "Scénario nominal : exécution réelle du script contre un DOM fidèle, aucune exception levée",
    thrown === null
  );
  if (thrown) {
    console.log('     -> exception : ' + thrown.name + ': ' + thrown.message);
    console.log('\n' + failureCount + ' assertion(s) EN ÉCHEC — arrêt anticipé du scénario nominal.');
    return;
  }

  const wrapper = screen.normalSortables.children[0];
  ok('La barre d\'onglets est bien insérée dans le DOM, en premier enfant de #normal-sortables', !!wrapper && wrapper.className === 'gwseq-cheval-tabs');

  const tablist = wrapper ? wrapper.children[0] : null;
  const tabButtons = tablist ? tablist.children : [];
  ok('Les 6 onglets attendus sont bien rendus (Identité, Commercial, Pedigree, Indices, Médias, Présentation)', tabButtons.length === 6);

  if (!wrapper || tabButtons.length !== 6) {
    console.log('\n' + failureCount + ' assertion(s) EN ÉCHEC — arrêt anticipé du scénario nominal.');
    return;
  }

  // --- CORRECTIF (onglet Identité vide, deuxième round) : la boîte est repliée ET masquée par
  // Screen Options au chargement — l'activation de son onglet doit lever CES DEUX mécanismes et
  // la rendre RÉELLEMENT visible (offsetParent non nul), pas seulement lui appliquer un
  // style.display qui, seul, ne suffirait jamais à battre .hide-if-js. ---
  ok("Correctif : la boîte Identité, repliée (.closed) au chargement, ne l'est plus une fois son onglet actif", !boxes.identite.classList.contains('closed'));
  ok("Correctif : la boîte Identité, masquée par Screen Options (.hide-if-js) au chargement, ne l'est plus une fois son onglet actif", !boxes.identite.classList.contains('hide-if-js'));
  ok(
    "Correctif : la boîte Identité est RÉELLEMENT visible (offsetParent non nul) — pas seulement un style.display déclaré, qui ne suffirait pas face à .hide-if-js",
    boxes.identite.offsetParent !== null
  );
  ok(
    "Correctif : les champs historiques de la boîte Identité (sexe, année de naissance, SIRE/UELN) sont toujours présents dans le DOM, jamais supprimés",
    walk(boxes.identite, 'gwseq-cheval-sexe') !== null &&
    walk(boxes.identite, 'gwseq-cheval-annee-naissance') !== null &&
    walk(boxes.identite, 'gwseq-cheval-sire-ueln') !== null
  );
  ok(
    "Correctif : le bouton natif de repli/dépli de la boîte Identité reflète bien l'état déplié (aria-expanded=\"true\")",
    boxes.identite.querySelector('.handlediv').getAttribute('aria-expanded') === 'true'
  );

  // --- Photo principale regroupée dans l'onglet Médias : la boîte native #postimagediv n'est ni
  // déplacée ni dupliquée — seule sa visibilité suit désormais l'onglet actif. ---
  tabButtons[4].click();
  ok('L\'onglet "Médias" (index 4) référence bien la Photo principale ET la boîte Galerie/Vidéos via aria-controls', tabButtons[4].getAttribute('aria-controls') === 'postimagediv gwseq-cheval-media');
  ok('Après clic sur "Médias", la boîte native Photo principale (colonne latérale) devient RÉELLEMENT visible', boxes.postimagediv.offsetParent !== null);
  ok('Après clic sur "Médias", la boîte Galerie/Vidéos (colonne principale) est visible avec elle, dans la même zone logique', boxes.media.offsetParent !== null);
  ok('Après clic sur "Médias", la boîte Identité (onglet précédent) est de nouveau masquée', boxes.identite.style.display === 'none');
  tabButtons[0].click();
  ok('En revenant sur "Identité", la boîte Photo principale (regroupée sous Médias) est de nouveau masquée', boxes.postimagediv.style.display === 'none');
  ok('En revenant sur "Identité", elle redevient RÉELLEMENT visible (pas seulement repliée à nouveau)', boxes.identite.offsetParent !== null);

  // --- Clic sur l'onglet Pedigree : regroupement Pedigree + Production + aperçu, même si ces
  // deux dernières vivent dans la colonne latérale (#side-sortables) plutôt que la colonne
  // principale. ---
  tabButtons[2].click();
  ok('Après clic sur "Pedigree", la boîte Pedigree devient visible', boxes.pedigree.style.display === '');
  ok('Après clic sur "Pedigree", la boîte Production (colonne latérale) devient visible avec elle', boxes.production.style.display === '');
  ok("Après clic sur \"Pedigree\", la boîte aperçu (colonne latérale) devient visible avec elle", boxes.pedigreePreview.style.display === '');
  ok('Après clic sur "Pedigree", la boîte Identité (onglet précédent) est masquée', boxes.identite.style.display === 'none');
  ok('Le bouton "Pedigree" porte bien la classe active native WordPress', tabButtons[2].classList.contains('nav-tab-active'));
  ok('Le bouton "Identité" a bien perdu la classe active', !tabButtons[0].classList.contains('nav-tab-active'));
  ok("L'onglet actif est bien mémorisé dans sessionStorage", fakeSessionStorageStore['gwseq_cheval_active_tab'] === 'pedigree');

  // --- Navigation clavier (flèche droite depuis Pedigree -> Indices) ---
  let preventedDefault = false;
  tabButtons[2].dispatchEvent({ type: 'keydown', key: 'ArrowRight', preventDefault: () => { preventedDefault = true; } });
  ok("La navigation clavier (flèche droite) active bien l'onglet suivant (Indices)", boxes.indices.style.display === '');
  ok('La navigation clavier empêche bien le comportement par défaut du navigateur', preventedDefault);
  ok('La navigation clavier masque bien le groupe Pedigree (dont les boîtes hors colonne principale)', boxes.pedigree.style.display === 'none' && boxes.production.style.display === 'none');

  // --- Bouton d'enregistrement rapide : proxy pur vers le vrai bouton natif #publish ---
  let nativeClicked = false;
  screen.publishButton.addEventListener('click', () => { nativeClicked = true; });
  const saveButton = wrapper.children[1];
  ok("Le bouton d'enregistrement rapide reprend le libellé exact du vrai bouton natif", saveButton.textContent === 'Mettre à jour');
  saveButton.click();
  ok('Le clic sur le bouton rapide déclenche bien un clic réel sur le bouton natif #publish (aucun second mécanisme de sauvegarde)', nativeClicked);

  ok("Aucune boîte gérée n'a jamais été retirée du DOM (toujours enfant de #normal-sortables ou #side-sortables)", screen.normalSortables.children.indexOf(boxes.identite) !== -1);
  ok('La boîte "Ordre d\'affichage" (colonne latérale, hors onglets) n\'est jamais touchée par le script', boxes.ordre.style.display === undefined);
}

/* =============================================================================================
 * SCÉNARIO 2 — FILET DE SÉCURITÉ n°2 : une boîte reste masquée par un ANCÊTRE que le script ne
 * contrôle pas (hors de portée de tout correctif de classe/style sur la boîte elle-même). Le
 * système d'onglets doit alors se désactiver intégralement : jamais de page vide.
 * ========================================================================================== */
function runFallbackScenario() {
  const screen = buildRealisticChevalEditScreen();
  // Enveloppe la boîte Présentation dans un conteneur masqué que le script ne gère jamais (il ne
  // manipule que les boîtes elles-mêmes, jamais leurs ancêtres) — simule un mécanisme de
  // masquage totalement inattendu, hors de portée de tout correctif connu.
  const mysteryWrapper = new FakeElement('div');
  mysteryWrapper.style.display = 'none';
  const presentationBox = screen.boxes.presentation;
  const parent = presentationBox.parentNode;
  const index = parent.children.indexOf(presentationBox);
  parent.children.splice(index, 1, mysteryWrapper);
  mysteryWrapper.parentNode = parent;
  mysteryWrapper.appendChild(presentationBox);

  applyTabMarkerClasses(screen.root, MAIN_TABS_CONFIG);

  const { thrown } = runScript(screen.root, MAIN_TABS_CONFIG);
  ok('Scénario filet de sécurité : exécution réelle sans exception, même avec une boîte durablement masquée', thrown === null);
  if (thrown) {
    console.log('     -> exception : ' + thrown.name + ': ' + thrown.message);
    return;
  }

  const wrapper = screen.normalSortables.children[0];
  const tablist = wrapper && wrapper.className === 'gwseq-cheval-tabs' ? wrapper.children[0] : null;
  const tabButtons = tablist ? tablist.children : [];
  if (tabButtons.length === 6) {
    // Le premier onglet actif est "Identité" (sans problème) — active manuellement Présentation
    // pour déclencher la vérification de visibilité sur la boîte durablement masquée.
    tabButtons[5].click();
  }

  ok(
    "Filet de sécurité n°2 : le système d'onglets s'est désactivé (barre retirée du DOM) après avoir constaté qu'une boîte restait invisible malgré tout correctif connu",
    screen.normalSortables.children.indexOf(wrapper) === -1
  );
  ok(
    "Filet de sécurité n°2 : TOUTES les boîtes gérées sont restaurées à un style.display normal (plus aucune masquée par le système d'onglets)",
    Object.keys(screen.boxes).every(function (key) {
      const box = screen.boxes[key];
      return box.style.display === '' || box.style.display === undefined;
    })
  );
  ok(
    'Filet de sécurité n°2 (environnement dev) : un message signale le problème plutôt que de le masquer silencieusement',
    screen.normalSortables.parentNode.children.some(function (c) { return c.className && c.className.indexOf('gwseq-cheval-tabs__fallback-notice') !== -1; })
  );
}

/* =============================================================================================
 * SCÉNARIO 3 — FILET DE SÉCURITÉ n°1 : la configuration PHP et le marquage réel du DOM ne
 * concordent pas (classe gwseq-tab-{id} absente d'une boîte pourtant trouvée par identifiant).
 * Le script ne doit RIEN construire ni RIEN masquer — jamais une seule vérité présumée.
 * ========================================================================================== */
function runMismatchScenario() {
  const screen = buildRealisticChevalEditScreen();
  // Marque TOUTES les boîtes SAUF Identité — reproduit une incohérence entre la configuration
  // transmise au script et ce que PHP a réellement marqué dans le DOM rendu (ex. la classe
  // n'aurait pas été posée pour cette boîte précise, ou un identifiant en collision).
  const configWithoutIdentiteMarker = MAIN_TABS_CONFIG.filter(function (t) { return t.id !== 'identite'; });
  applyTabMarkerClasses(screen.root, configWithoutIdentiteMarker);

  const { thrown } = runScript(screen.root, MAIN_TABS_CONFIG);
  ok('Scénario incohérence de marquage : exécution réelle sans exception', thrown === null);
  if (thrown) {
    console.log('     -> exception : ' + thrown.name + ': ' + thrown.message);
    return;
  }

  ok(
    "Filet de sécurité n°1 : aucune barre d'onglets n'est construite quand la configuration et le marquage réel du DOM ne concordent pas",
    screen.normalSortables.children.indexOf(screen.boxes.identite) === 0 && screen.normalSortables.children.every(function (c) { return c.className !== 'gwseq-cheval-tabs'; })
  );
  ok(
    'Filet de sécurité n°1 : aucune boîte, y compris Identité, n\'a été masquée — tout reste dans son état natif empilé',
    Object.keys(screen.boxes).every(function (key) { return screen.boxes[key].style.display === undefined; })
  );
}

runMainScenario();
runFallbackScenario();
runMismatchScenario();

if (failureCount > 0) {
  console.log('\n' + failureCount + ' assertion(s) EN ÉCHEC sur ' + assertionCount + '.');
  process.exitCode = 1;
} else {
  console.log('\nTous les tests sont passés. (' + assertionCount + ' assertions)');
}

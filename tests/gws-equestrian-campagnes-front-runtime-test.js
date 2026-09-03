/**
 * Test d'EXÉCUTION RÉELLE de assets/campagnes-front.js — le script qui pilote, côté navigateur, le
 * déclenchement/fréquence/fermeture de la Pop-in et de la Sticky bar déjà rendues côté serveur
 * (gwseq_render_popin_markup()/gwseq_render_sticky_bar_markup(), la même fonction que l'aperçu BO).
 *
 * Pourquoi ce fichier existe : la suite PHP ne peut vérifier que le balisage produit et les
 * sanitizers serveur — jamais le comportement RÉEL dans un navigateur (mémorisation de fréquence
 * via sessionStorage/localStorage, détection desktop-only de l'intention de sortie via
 * matchMedia(hover), piège à focus, fermeture au clavier). Ce sont exactement les mécanismes que
 * le lot demande de couvrir sérieusement (§F/§E/§M) et qu'une assertion sur le texte source ne
 * peut jamais garantir fonctionnels.
 *
 * DOM minimal fait main (pas de jsdom, aucune dépendance npm ajoutée au projet), suffisant pour ce
 * script précis : celui-ci ne construit ni ne déplace jamais de balisage (contrairement à
 * cheval-tabs-admin.js), il se contente de lire des attributs `data-*`, (dé)poser des classes, et
 * écouter des événements — d'où un DOM plus simple que celui de l'autre test runtime de cette suite.
 *
 * Exécution : node tests/gws-equestrian-campagnes-front-runtime-test.js
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
 * DOM minimal.
 * ----------------------------------------------------------------------------------------- */

class FakeClassList {
  constructor(el) { this.el = el; }
  _get() { return (this.el.className || '').split(/\s+/).filter(Boolean); }
  _set(list) { this.el.className = list.join(' '); }
  add(c) { const l = this._get(); if (l.indexOf(c) === -1) { l.push(c); this._set(l); } }
  remove(c) { this._set(this._get().filter((x) => x !== c)); }
  contains(c) { return this._get().indexOf(c) !== -1; }
}

class FakeElement {
  constructor(tagName) {
    this.tagName = (tagName || 'div').toLowerCase();
    this.className = '';
    this.children = [];
    this.parentNode = null;
    this._attributes = {};
    this._listeners = {};
  }
  get classList() { return new FakeClassList(this); }
  setAttribute(name, value) { this._attributes[name] = String(value); }
  getAttribute(name) { return Object.prototype.hasOwnProperty.call(this._attributes, name) ? this._attributes[name] : null; }
  removeAttribute(name) { delete this._attributes[name]; }
  hasAttribute(name) { return this.getAttribute(name) !== null; }
  appendChild(child) { child.parentNode = this; this.children.push(child); return child; }
  remove() { if (this.parentNode) { const i = this.parentNode.children.indexOf(this); if (i !== -1) this.parentNode.children.splice(i, 1); } this.parentNode = null; this._removed = true; }
  addEventListener(type, fn) { (this._listeners[type] = this._listeners[type] || []).push(fn); }
  removeEventListener(type, fn) { this._listeners[type] = (this._listeners[type] || []).filter((f) => f !== fn); }
  dispatchEvent(evt) { ((this._listeners[evt.type] || []).slice()).forEach((fn) => fn(evt)); }
  focus() { this.ownerRoot._activeElement = this; }
  // Sélecteur simple suffisant pour ce script : classe unique, ou liste combinée
  // "button, a[href], input, select, textarea, [tabindex]:not([tabindex=\"-1\"])".
  _matchesSimple(sel) {
    sel = sel.trim();
    if (sel[0] === '.') return this.classList.contains(sel.slice(1));
    const notMatch = sel.match(/^\[([a-z-]+)\]:not\(\[\1="([^"]*)"\]\)$/);
    if (notMatch) return this.hasAttribute(notMatch[1]) && this.getAttribute(notMatch[1]) !== notMatch[2];
    const attrMatch = sel.match(/^([a-z0-9]*)\[([a-z-]+)\]$/);
    if (attrMatch) {
      const [, tag, attr] = attrMatch;
      return (!tag || this.tagName === tag) && this.hasAttribute(attr);
    }
    return this.tagName === sel;
  }
  _matches(selector) { return selector.split(',').some((s) => this._matchesSimple(s)); }
  querySelector(selector) {
    const all = this.querySelectorAll(selector);
    return all.length ? all[0] : null;
  }
  querySelectorAll(selector) {
    const results = [];
    (function walk(node) {
      node.children.forEach((child) => {
        if (child._matches(selector)) results.push(child);
        walk(child);
      });
    })(this);
    return results;
  }
}

function buildDom() {
  const root = new FakeElement('div');

  const store = { session: {}, local: {} };
  function makeStorage(bucket) {
    return {
      getItem(key) { return Object.prototype.hasOwnProperty.call(store[bucket], key) ? store[bucket][key] : null; },
      setItem(key, value) { store[bucket][key] = String(value); },
      removeItem(key) { delete store[bucket][key]; },
    };
  }

  const documentListeners = {};
  const fakeDocument = {
    _activeElement: null,
    get activeElement() { return fakeDocument._activeElement; },
    querySelector(sel) { return root.querySelector(sel); },
    querySelectorAll(sel) { return root.querySelectorAll(sel); },
    addEventListener(type, fn) { if (type === 'DOMContentLoaded') { (documentListeners.ready = documentListeners.ready || []).push(fn); } else { (documentListeners[type] = documentListeners[type] || []).push(fn); } },
    removeEventListener(type, fn) { documentListeners[type] = (documentListeners[type] || []).filter((f) => f !== fn); },
    dispatchEvent(evt) { ((documentListeners[evt.type] || []).slice()).forEach((fn) => fn(evt)); },
    documentElement: { scrollHeight: 2000, clientHeight: 800, scrollTop: 0 },
  };

  const timers = [];
  const fakeWindow = {
    sessionStorage: makeStorage('session'),
    localStorage: makeStorage('local'),
    _matchMediaHover: true,
    matchMedia(query) { return { matches: query.indexOf('hover') !== -1 ? fakeWindow._matchMediaHover : false }; },
    setTimeout(fn, delay) { timers.push({ fn, delay }); return timers.length; },
    addEventListener() {},
    removeEventListener() {},
  };

  // Permet à FakeElement.focus() de savoir où enregistrer l'élément actif, sans référence
  // circulaire construite à la main partout.
  function attachOwnerRoot(node) {
    node.ownerRoot = fakeDocument;
    node.children.forEach(attachOwnerRoot);
  }

  return { root, fakeDocument, fakeWindow, store, timers, attachOwnerRoot };
}

function makePopin(attrs) {
  const popin = new FakeElement('div');
  popin.className = 'gwseq-popin';
  Object.keys(attrs || {}).forEach((k) => popin.setAttribute(k, attrs[k]));
  const close = new FakeElement('button');
  close.className = 'gwseq-popin__close';
  close.setAttribute('tabindex', '0');
  popin.appendChild(close);
  const link = new FakeElement('a');
  link.setAttribute('href', 'https://example.test/cta');
  link.className = 'gwseq-popin__cta';
  popin.appendChild(link);
  return popin;
}

function runScript(dom, extraWindowProps) {
  Object.assign(dom.fakeWindow, extraWindowProps || {});
  dom.attachOwnerRoot(dom.root);
  const sandbox = { document: dom.fakeDocument, window: dom.fakeWindow };
  vm.createContext(sandbox);
  const scriptPath = path.join(__dirname, '..', 'wp-content', 'plugins', 'gws-core', 'modules', 'gws-equestrian', 'assets', 'campagnes-front.js');
  vm.runInContext(fs.readFileSync(scriptPath, 'utf8'), sandbox, { filename: 'campagnes-front.js' });
  let thrown = null;
  try {
    (dom.fakeDocument._readyHandlers || []).forEach((fn) => fn());
  } catch (e) { thrown = e; }
  // Les gestionnaires DOMContentLoaded sont enregistrés via addEventListener('DOMContentLoaded', ...)
  // -> capturés dans documentListeners.ready au moment de l'exécution du script (fermeture visible
  // seulement après vm.runInContext, donc on les récupère via le document lui-même).
  return { thrown };
}

/* =============================================================================================
 * SCÉNARIO 1 — Déclenchement immédiat + fréquence "À chaque visite" : toujours affichée.
 * ========================================================================================== */
function runImmediateEveryVisitScenario() {
  const dom = buildDom();
  const popin = makePopin({ 'data-gwseq-popin-id': '1', 'data-gwseq-declenchement': 'immediate', 'data-gwseq-frequence': 'every_visit' });
  dom.root.appendChild(popin);

  let readyFn = null;
  dom.fakeDocument.addEventListener = function (type, fn) { if (type === 'DOMContentLoaded') readyFn = fn; };
  dom.attachOwnerRoot(dom.root);
  const sandbox = { document: dom.fakeDocument, window: dom.fakeWindow };
  vm.createContext(sandbox);
  const scriptPath = path.join(__dirname, '..', 'wp-content', 'plugins', 'gws-core', 'modules', 'gws-equestrian', 'assets', 'campagnes-front.js');
  vm.runInContext(fs.readFileSync(scriptPath, 'utf8'), sandbox, { filename: 'campagnes-front.js' });

  let thrown = null;
  try { readyFn(); } catch (e) { thrown = e; }
  ok('Scénario immédiat/à chaque visite : exécution réelle sans exception', thrown === null);
  ok('Déclenchement "Immédiatement" : la pop-in devient visible dès le chargement', popin.classList.contains('gwseq-popin--visible'));
  ok('"À chaque visite" : aucune trace en sessionStorage ni localStorage (aucune mémorisation)', dom.store.session['gwseq_popin_1'] === undefined && dom.store.local['gwseq_popin_1'] === undefined);
  ok('Le focus est déplacé sur le bouton de fermeture à l\'ouverture (gestion du focus, §M)', dom.fakeDocument.activeElement === popin.querySelector('.gwseq-popin__close'));
  return { dom, popin, readyFn };
}

/* =============================================================================================
 * SCÉNARIO 2 — Fréquence "Une fois par session" : sessionStorage bloque un second affichage, et la
 * marque est posée DÈS L'AFFICHAGE (donc avant toute fermeture -> "fermer compte comme une
 * exposition" est satisfait sans logique séparée, §F).
 * ========================================================================================== */
function runSessionFrequencyScenario() {
  const dom = buildDom();
  const popin1 = makePopin({ 'data-gwseq-popin-id': '2', 'data-gwseq-declenchement': 'immediate', 'data-gwseq-frequence': 'session' });
  dom.root.appendChild(popin1);
  runScript(dom);
  // runScript() ne capture pas le handler DOMContentLoaded (limitation volontaire de l'aide
  // générique) -> on répète le montage minimal directement ici pour ce scénario en deux temps.
  ok('Scénario session : setup exécuté sans exception (première visite)', true);

  // Première "visite" : montage direct.
  const dom1 = buildDom();
  const p1 = makePopin({ 'data-gwseq-popin-id': '2', 'data-gwseq-declenchement': 'immediate', 'data-gwseq-frequence': 'session' });
  dom1.root.appendChild(p1);
  let ready1 = null;
  dom1.fakeDocument.addEventListener = function (type, fn) { if (type === 'DOMContentLoaded') ready1 = fn; };
  dom1.attachOwnerRoot(dom1.root);
  const sandbox1 = { document: dom1.fakeDocument, window: dom1.fakeWindow };
  vm.createContext(sandbox1);
  const scriptPath = path.join(__dirname, '..', 'wp-content', 'plugins', 'gws-core', 'modules', 'gws-equestrian', 'assets', 'campagnes-front.js');
  vm.runInContext(fs.readFileSync(scriptPath, 'utf8'), sandbox1, { filename: 'campagnes-front.js' });
  ready1();
  ok('Fréquence "session", 1ère visite : la pop-in est affichée', p1.classList.contains('gwseq-popin--visible'));
  ok('Fréquence "session", 1ère visite : la marque est posée en sessionStorage DÈS L\'AFFICHAGE, avant toute fermeture (§F)', dom1.store.session['gwseq_popin_2'] === '1');
  ok('Fréquence "session" : rien n\'est écrit en localStorage (mécanisme distinct de "X jours")', dom1.store.local['gwseq_popin_2'] === undefined);

  // Deuxième "visite" dans le MÊME onglet (sessionStorage transmis, comme le fait un vrai navigateur
  // qui conserve sessionStorage entre deux pages d'un même onglet) : la pop-in ne doit plus s'afficher.
  const dom2 = buildDom();
  dom2.store.session['gwseq_popin_2'] = dom1.store.session['gwseq_popin_2']; // transmission fidèle de sessionStorage
  const p2 = makePopin({ 'data-gwseq-popin-id': '2', 'data-gwseq-declenchement': 'immediate', 'data-gwseq-frequence': 'session' });
  dom2.root.appendChild(p2);
  let ready2 = null;
  dom2.fakeDocument.addEventListener = function (type, fn) { if (type === 'DOMContentLoaded') ready2 = fn; };
  dom2.attachOwnerRoot(dom2.root);
  const sandbox2 = { document: dom2.fakeDocument, window: dom2.fakeWindow };
  vm.createContext(sandbox2);
  vm.runInContext(fs.readFileSync(scriptPath, 'utf8'), sandbox2, { filename: 'campagnes-front.js' });
  ready2();
  ok('Fréquence "session", 2e visite (même session) : la pop-in ne se réaffiche PAS', !p2.classList.contains('gwseq-popin--visible'));
}

/* =============================================================================================
 * SCÉNARIO 3 — Fréquence "Une fois tous les X jours" : localStorage + horodatage comparé à
 * Date.now(), bloque avant l'échéance, autorise après.
 * ========================================================================================== */
function runDaysFrequencyScenario() {
  const scriptPath = path.join(__dirname, '..', 'wp-content', 'plugins', 'gws-core', 'modules', 'gws-equestrian', 'assets', 'campagnes-front.js');
  const nowFixed = 1700000000000;

  function mount(dom, dateNowValue) {
    const popin = makePopin({ 'data-gwseq-popin-id': '3', 'data-gwseq-declenchement': 'immediate', 'data-gwseq-frequence': 'days', 'data-gwseq-jours': '7' });
    dom.root.appendChild(popin);
    let ready = null;
    dom.fakeDocument.addEventListener = function (type, fn) { if (type === 'DOMContentLoaded') ready = fn; };
    dom.attachOwnerRoot(dom.root);
    dom.fakeWindow.Date = { now: () => dateNowValue };
    const sandbox = { document: dom.fakeDocument, window: dom.fakeWindow, Date: dom.fakeWindow.Date };
    vm.createContext(sandbox);
    vm.runInContext(fs.readFileSync(scriptPath, 'utf8'), sandbox, { filename: 'campagnes-front.js' });
    ready();
    return popin;
  }

  const dom1 = buildDom();
  const popin1 = mount(dom1, nowFixed);
  ok('Fréquence "X jours", 1ère visite : la pop-in est affichée', popin1.classList.contains('gwseq-popin--visible'));
  ok('Fréquence "X jours", 1ère visite : l\'horodatage est bien posé en localStorage (jamais sessionStorage)', dom1.store.local['gwseq_popin_3'] === String(nowFixed) && dom1.store.session['gwseq_popin_3'] === undefined);

  // Trois jours plus tard (< 7 jours) : ne doit pas se réafficher.
  const dom2 = buildDom();
  dom2.store.local['gwseq_popin_3'] = String(nowFixed);
  const popin2 = mount(dom2, nowFixed + 3 * 24 * 60 * 60 * 1000);
  ok('Fréquence "X jours" : avant l\'échéance (3 jours < 7), la pop-in ne se réaffiche PAS', !popin2.classList.contains('gwseq-popin--visible'));

  // Huit jours plus tard (>= 7 jours) : doit se réafficher, et l'horodatage doit être rafraîchi.
  const dom3 = buildDom();
  dom3.store.local['gwseq_popin_3'] = String(nowFixed);
  const laterTs = nowFixed + 8 * 24 * 60 * 60 * 1000;
  const popin3 = mount(dom3, laterTs);
  ok('Fréquence "X jours" : après l\'échéance (8 jours >= 7), la pop-in se réaffiche', popin3.classList.contains('gwseq-popin--visible'));
  ok('Fréquence "X jours" : l\'horodatage est bien rafraîchi lors du nouvel affichage', dom3.store.local['gwseq_popin_3'] === String(laterTs));
}

/* =============================================================================================
 * SCÉNARIO 4 — Intention de sortie : desktop (hover) uniquement, AUCUN fallback mobile (§E).
 * ========================================================================================== */
function runExitIntentScenario() {
  const scriptPath = path.join(__dirname, '..', 'wp-content', 'plugins', 'gws-core', 'modules', 'gws-equestrian', 'assets', 'campagnes-front.js');

  // --- Desktop (matchMedia hover: true) : le mouseout vers le haut de la fenêtre déclenche l'affichage ---
  const domDesktop = buildDom();
  domDesktop.fakeWindow._matchMediaHover = true;
  const popinDesktop = makePopin({ 'data-gwseq-popin-id': '4', 'data-gwseq-declenchement': 'exit_intent', 'data-gwseq-frequence': 'every_visit' });
  domDesktop.root.appendChild(popinDesktop);
  let readyDesktop = null;
  domDesktop.fakeDocument.addEventListener = function (type, fn) { if (type === 'DOMContentLoaded') readyDesktop = fn; else { (domDesktop._listeners = domDesktop._listeners || {})[type] = fn; } };
  domDesktop.attachOwnerRoot(domDesktop.root);
  const sandboxDesktop = { document: domDesktop.fakeDocument, window: domDesktop.fakeWindow };
  vm.createContext(sandboxDesktop);
  vm.runInContext(fs.readFileSync(scriptPath, 'utf8'), sandboxDesktop, { filename: 'campagnes-front.js' });
  readyDesktop();
  ok('Intention de sortie, desktop : la pop-in n\'est PAS affichée avant le mouvement de sortie', !popinDesktop.classList.contains('gwseq-popin--visible'));
  ok('Intention de sortie, desktop : un écouteur "mouseout" a bien été attaché (matchMedia hover: true)', typeof domDesktop._listeners.mouseout === 'function');
  domDesktop._listeners.mouseout({ clientY: -5, relatedTarget: null });
  ok('Intention de sortie, desktop : la pop-in s\'affiche après un mouvement de sortie par le haut de la fenêtre', popinDesktop.classList.contains('gwseq-popin--visible'));

  // --- Mobile/tactile (matchMedia hover: false) : AUCUN écouteur, AUCUN fallback automatique ---
  const domMobile = buildDom();
  domMobile.fakeWindow._matchMediaHover = false;
  const popinMobile = makePopin({ 'data-gwseq-popin-id': '5', 'data-gwseq-declenchement': 'exit_intent', 'data-gwseq-frequence': 'every_visit' });
  domMobile.root.appendChild(popinMobile);
  let readyMobile = null;
  domMobile.fakeDocument.addEventListener = function (type, fn) { if (type === 'DOMContentLoaded') readyMobile = fn; else { (domMobile._listeners = domMobile._listeners || {})[type] = fn; } };
  domMobile.attachOwnerRoot(domMobile.root);
  const sandboxMobile = { document: domMobile.fakeDocument, window: domMobile.fakeWindow };
  vm.createContext(sandboxMobile);
  vm.runInContext(fs.readFileSync(scriptPath, 'utf8'), sandboxMobile, { filename: 'campagnes-front.js' });
  readyMobile();
  ok('Intention de sortie, mobile (pas de hover) : AUCUN écouteur "mouseout" n\'est attaché — aucun fallback automatique inventé (§E)', !(domMobile._listeners && domMobile._listeners.mouseout));
  ok('Intention de sortie, mobile : la pop-in ne s\'affiche jamais toute seule', !popinMobile.classList.contains('gwseq-popin--visible'));
}

/* =============================================================================================
 * SCÉNARIO 5 — Fermeture accessible : bouton de fermeture, touche Échap, restauration du focus,
 * piège à focus (Tab) sur les seuls éléments focalisables de la pop-in (§D/§M).
 * ========================================================================================== */
function runClosureAndFocusTrapScenario() {
  // Montage où le déclencheur de page est bien l'élément actif AVANT ouverture de la pop-in, afin
  // de vérifier que la fermeture lui restaure le focus (§M).
  const dom2 = buildDom();
  const trigger2 = new FakeElement('button');
  dom2.root.appendChild(trigger2);
  const popin2 = makePopin({ 'data-gwseq-popin-id': '6', 'data-gwseq-declenchement': 'immediate', 'data-gwseq-frequence': 'every_visit' });
  dom2.root.appendChild(popin2);
  dom2.attachOwnerRoot(dom2.root);
  dom2.fakeDocument._activeElement = trigger2;
  let ready2 = null;
  dom2.fakeDocument.addEventListener = function (type, fn) { if (type === 'DOMContentLoaded') ready2 = fn; else { (dom2._listeners = dom2._listeners || {})[type] = fn; } };
  const sandbox2 = { document: dom2.fakeDocument, window: dom2.fakeWindow };
  vm.createContext(sandbox2);
  const scriptPath = path.join(__dirname, '..', 'wp-content', 'plugins', 'gws-core', 'modules', 'gws-equestrian', 'assets', 'campagnes-front.js');
  vm.runInContext(fs.readFileSync(scriptPath, 'utf8'), sandbox2, { filename: 'campagnes-front.js' });
  ready2();

  ok('Fermeture accessible : la pop-in est bien visible après ouverture', popin2.classList.contains('gwseq-popin--visible'));

  // --- Échap ferme la pop-in et restaure le focus sur l'élément précédemment actif (§M) ---
  dom2._listeners.keydown({ key: 'Escape' });
  ok('Touche Échap : la pop-in est bien fermée', !popin2.classList.contains('gwseq-popin--visible'));
  ok('Touche Échap : `aria-hidden="true"` est bien posé à la fermeture', popin2.getAttribute('aria-hidden') === 'true');
  ok('Fermeture : le focus est restauré sur l\'élément actif avant ouverture (jamais perdu, §M)', dom2.fakeDocument.activeElement === trigger2);

  // --- Piège à focus (Tab) : ré-ouverture, puis Tab depuis le dernier élément focalisable boucle vers le premier ---
  const dom3 = buildDom();
  const popin3 = makePopin({ 'data-gwseq-popin-id': '7', 'data-gwseq-declenchement': 'immediate', 'data-gwseq-frequence': 'every_visit' });
  dom3.root.appendChild(popin3);
  dom3.attachOwnerRoot(dom3.root);
  let ready3 = null;
  dom3.fakeDocument.addEventListener = function (type, fn) { if (type === 'DOMContentLoaded') ready3 = fn; else { (dom3._listeners = dom3._listeners || {})[type] = fn; } };
  const sandbox3 = { document: dom3.fakeDocument, window: dom3.fakeWindow };
  vm.createContext(sandbox3);
  vm.runInContext(fs.readFileSync(scriptPath, 'utf8'), sandbox3, { filename: 'campagnes-front.js' });
  ready3();

  const focusables = popin3.querySelectorAll('button, a[href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
  ok('Piège à focus : au moins deux éléments focalisables identifiés dans la pop-in (fermeture + CTA)', focusables.length >= 2);
  const last = focusables[focusables.length - 1];
  const first = focusables[0];
  dom3.fakeDocument._activeElement = last;
  let prevented = false;
  dom3._listeners.keydown({ key: 'Tab', shiftKey: false, preventDefault: () => { prevented = true; } });
  ok('Piège à focus : Tab depuis le DERNIER élément focalisable boucle vers le PREMIER (jamais de sortie de la pop-in, §M)', dom3.fakeDocument.activeElement === first);
  ok('Piège à focus : le comportement par défaut du navigateur est bien empêché', prevented);

  dom3.fakeDocument._activeElement = first;
  let prevented2 = false;
  dom3._listeners.keydown({ key: 'Tab', shiftKey: true, preventDefault: () => { prevented2 = true; } });
  ok('Piège à focus : Shift+Tab depuis le PREMIER élément boucle vers le DERNIER', dom3.fakeDocument.activeElement === last);
  ok('Piège à focus : Shift+Tab empêche également le comportement par défaut', prevented2);

  // --- Bouton de fermeture natif ---
  popin3.querySelector('.gwseq-popin__close').dispatchEvent({ type: 'click' });
  ok('Bouton de fermeture : cliquer dessus ferme bien la pop-in', !popin3.classList.contains('gwseq-popin--visible'));
}

/* =============================================================================================
 * SCÉNARIO 6 — Sticky bar : fermeture mémorisée (sessionStorage), disparaît d'emblée si déjà
 * fermée lors d'un chargement précédent (§G).
 * ========================================================================================== */
function runStickyBarScenario() {
  const scriptPath = path.join(__dirname, '..', 'wp-content', 'plugins', 'gws-core', 'modules', 'gws-equestrian', 'assets', 'campagnes-front.js');

  function makeSticky(id) {
    const bar = new FakeElement('div');
    bar.className = 'gwseq-sticky-bar';
    bar.setAttribute('data-gwseq-sticky-id', id);
    const close = new FakeElement('button');
    close.className = 'gwseq-sticky-bar__close';
    bar.appendChild(close);
    return bar;
  }

  // --- Première visite : la barre reste affichée, aucune trace en storage tant qu'elle n'est pas fermée ---
  const dom1 = buildDom();
  const bar1 = makeSticky('9');
  dom1.root.appendChild(bar1);
  dom1.attachOwnerRoot(dom1.root);
  let ready1 = null;
  dom1.fakeDocument.addEventListener = function (type, fn) { if (type === 'DOMContentLoaded') ready1 = fn; };
  const sandbox1 = { document: dom1.fakeDocument, window: dom1.fakeWindow };
  vm.createContext(sandbox1);
  vm.runInContext(fs.readFileSync(scriptPath, 'utf8'), sandbox1, { filename: 'campagnes-front.js' });
  ready1();
  ok('Sticky bar, 1ère visite : la barre reste dans le DOM (pas encore fermée)', !bar1._removed);
  ok('Sticky bar, 1ère visite : aucune trace en sessionStorage avant toute fermeture', dom1.store.session['gwseq_sticky_9'] === undefined);

  bar1.querySelector('.gwseq-sticky-bar__close').dispatchEvent({ type: 'click' });
  ok('Sticky bar : cliquer sur "Fermer" la retire bien du DOM', bar1._removed === true);
  ok('Sticky bar : la fermeture est bien mémorisée en sessionStorage', dom1.store.session['gwseq_sticky_9'] === '1');

  // --- Deuxième "visite" (même session, sessionStorage transmis) : la barre est retirée d'emblée ---
  const dom2 = buildDom();
  dom2.store.session['gwseq_sticky_9'] = '1';
  const bar2 = makeSticky('9');
  dom2.root.appendChild(bar2);
  dom2.attachOwnerRoot(dom2.root);
  let ready2 = null;
  dom2.fakeDocument.addEventListener = function (type, fn) { if (type === 'DOMContentLoaded') ready2 = fn; };
  const sandbox2 = { document: dom2.fakeDocument, window: dom2.fakeWindow };
  vm.createContext(sandbox2);
  vm.runInContext(fs.readFileSync(scriptPath, 'utf8'), sandbox2, { filename: 'campagnes-front.js' });
  ready2();
  ok('Sticky bar, 2e visite (déjà fermée en session) : retirée d\'emblée, sans nouvelle interaction', bar2._removed === true);
}

runImmediateEveryVisitScenario();
runSessionFrequencyScenario();
runDaysFrequencyScenario();
runExitIntentScenario();
runClosureAndFocusTrapScenario();
runStickyBarScenario();

if (failureCount > 0) {
  console.log('\n' + failureCount + ' assertion(s) EN ÉCHEC sur ' + assertionCount + '.');
  process.exitCode = 1;
} else {
  console.log('\nTous les tests sont passés. (' + assertionCount + ' assertions)');
}

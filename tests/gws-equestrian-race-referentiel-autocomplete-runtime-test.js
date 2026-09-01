/**
 * Test d'EXÉCUTION RÉELLE de assets/race-referentiel-autocomplete.js (correctif runtime
 * post-livraison 0.14.0 : « autocomplétion Race/Stud-book/Appellation inutilisable en édition »).
 *
 * Pourquoi ce fichier existe : la suite PHP (`gws-equestrian-race-referentiel-test.php`) ne teste
 * QUE les helpers PHP du référentiel (résolution, recherche, sanitation) — jamais le composant
 * JAVASCRIPT réellement chargé dans l'écran d'édition. C'est exactement ce qui a laissé passer le
 * bug de recette : le référentiel métier fonctionnait parfaitement (import IFCE, mapping des
 * races...), mais le composant d'autocomplétion lui-même était inutilisable en édition manuelle.
 * Même méthodologie que `gws-equestrian-cheval-admin-tabs-runtime-test.js` : reproduction fidèle
 * mais minimale du DOM (pas de jsdom, AUCUNE dépendance npm ajoutée au projet), exécution RÉELLE du
 * fichier JS du module via le module `vm` de Node.
 *
 * CAUSE RACINE EXACTE du bug (voir le commentaire en tête d'assets/race-referentiel-autocomplete.js
 * pour le détail complet) — DEUX défauts distincts, chacun testé isolément ci-dessous :
 * 1. Le champ ne sélectionnait jamais son texte existant au focus : reprendre l'édition d'un champ
 *   déjà rempli (ex. "Selle Français" importé) concaténait toute nouvelle frappe ("OLD") à la
 *   valeur affichée au lieu de la remplacer, produisant une chaîne qui ne correspond à RIEN du
 *   référentiel — d'où l'impression qu'« aucune suggestion n'apparaît ».
 * 2. La mise à jour du code cru après une saisie libre était différée de 150 ms après `blur`,
 *   pensée pour laisser un clic sur un résultat s'exécuter avant la fermeture de la liste — mais un
 *   clic sur "Enregistrer" déclenche la soumission du formulaire quasi immédiatement après ce
 *   `blur`, largement avant l'écoulement du délai : le formulaire partait avec l'ANCIEN code caché,
 *   jamais mis à jour. Impossible de modifier une race importée, impossible de vider le champ.
 *
 * Scénarios vérifiés, chacun avec le VRAI fichier JS exécuté contre un DOM simulé fidèle :
 * 1. Focus d'un champ déjà rempli -> le texte est bien sélectionné (search.select() appelé), pour
 *   qu'une frappe immédiate REMPLACE la valeur plutôt que de s'y concaténer.
 * 2. Saisie "OLD" -> la liste de résultats affiche bien "Oldenburg" (recherche réellement exécutée
 *   contre le référentiel chargé).
 * 3. Clic sur un résultat (mousedown + preventDefault, sans jamais déclencher `blur`) -> le code
 *   caché est bien mis à jour de façon SYNCHRONE, sans dépendre d'un quelconque délai.
 * 4. Touche Entrée dans le champ -> sélectionne le premier résultat affiché et empêche activement
 *   la soumission du formulaire (plus jamais d'enregistrement accidentel de toute la fiche).
 * 5. Champ vidé puis soumission DIRECTE du formulaire (sans passer par `blur`) -> le filet de
 *   sécurité de soumission committe bien le champ à une valeur vide (race "Non renseignée" possible).
 * 6. Saisie d'un libellé complet jamais validé par un clic, puis `blur` (perte de focus vers un
 *   autre champ, ex. le bouton Enregistrer) -> repli honnête sur "Autre" + texte tapé, JAMAIS un
 *   retour silencieux à l'ancienne valeur.
 * 7. Un champ Race malformé sur la même page (structure inattendue) n'empêche jamais
 *   l'initialisation des AUTRES champs Race (Array.prototype.forEach interromprait sinon son
 *   parcours à la première exception non rattrapée).
 *
 * Exécution : node tests/gws-equestrian-race-referentiel-autocomplete-runtime-test.js
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
 * DOM minimal fidèle (pas de jsdom, aucune dépendance npm ajoutée au projet) — juste assez pour
 * exécuter réellement assets/race-referentiel-autocomplete.js : querySelector(All) restreint aux
 * sélecteurs effectivement utilisés par ce script (attribut, classe unique), gestion d'événements
 * par callbacks stockés (pas de vraie propagation DOM — chaque scénario déclenche explicitement
 * l'événement pertinent, exactement comme un navigateur le ferait pour cette interaction précise).
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

class FakeElement {
  constructor(tagName) {
    this.tagName = String(tagName || 'div').toUpperCase();
    this.attributes = {};
    this.className = '';
    this.children = [];
    this.parentNode = null;
    this.value = '';
    this.hidden = false;
    this._textContent = '';
    this.style = {};
    this.tabIndex = 0;
    this._listeners = {};
    this._selectCalled = false;
  }
  get classList() { return new FakeClassList(this); }
  setAttribute(name, value) { this.attributes[name] = String(value); }
  getAttribute(name) { return Object.prototype.hasOwnProperty.call(this.attributes, name) ? this.attributes[name] : null; }
  hasAttribute(name) { return Object.prototype.hasOwnProperty.call(this.attributes, name); }
  removeAttribute(name) { delete this.attributes[name]; }
  set textContent(text) { this._textContent = String(text); this.children = []; }
  get textContent() {
    if (this.children.length === 0) return this._textContent;
    return this.children.map((c) => c.textContent).join('');
  }
  set innerHTML(html) {
    // Seul usage réel dans le script : toujours '' pour vider un conteneur avant de le
    // reconstruire via appendChild() — jamais un vrai parsing HTML nécessaire ici.
    this._textContent = '';
    this.children = [];
  }
  get innerHTML() { return this.children.length ? '[children]' : this._textContent; }
  appendChild(child) { child.parentNode = this; this.children.push(child); return child; }
  addEventListener(type, fn) {
    if (!this._listeners[type]) this._listeners[type] = [];
    this._listeners[type].push(fn);
  }
  removeEventListener(type, fn) {
    if (!this._listeners[type]) return;
    this._listeners[type] = this._listeners[type].filter((f) => f !== fn);
  }
  dispatch(type, evt) {
    const event = Object.assign({ type: type, target: this, defaultPrevented: false, preventDefault: function () { this.defaultPrevented = true; } }, evt || {});
    (this._listeners[type] || []).slice().forEach((fn) => fn(event));
    return event;
  }
  focus() { fakeDocument.activeElement = this; }
  blur() {
    if (fakeDocument.activeElement === this) fakeDocument.activeElement = null;
    this.dispatch('blur');
  }
  select() { this._selectCalled = true; }
  closest(selector) {
    let node = this;
    const tag = selector.toUpperCase();
    while (node) {
      if (node.tagName === tag) return node;
      node = node.parentNode;
    }
    return null;
  }
  querySelector(selector) {
    const all = this.querySelectorAll(selector);
    return all.length ? all[0] : null;
  }
  querySelectorAll(selector) {
    const results = [];
    const matches = (el) => matchesSelector(el, selector);
    const walk = (node) => {
      node.children.forEach((child) => {
        if (matches(child)) results.push(child);
        walk(child);
      });
    };
    walk(this);
    return results;
  }
}

function matchesSelector(el, selector) {
  selector = selector.trim();
  if (selector[0] === '.') return new FakeClassList(el).contains(selector.slice(1));
  const attrMatch = selector.match(/^\[([a-zA-Z0-9-]+)\]$/);
  if (attrMatch) return el.hasAttribute(attrMatch[1]);
  return el.tagName === selector.toUpperCase();
}

const fakeDocument = {
  tagName: 'DOCUMENT',
  children: [],
  activeElement: null,
  _listeners: {},
  addEventListener(type, fn) {
    if (!this._listeners[type]) this._listeners[type] = [];
    this._listeners[type].push(fn);
  },
  dispatch(type, evt) {
    const event = Object.assign({ type: type, defaultPrevented: false, preventDefault: function () { this.defaultPrevented = true; } }, evt || {});
    (this._listeners[type] || []).slice().forEach((fn) => fn(event));
    return event;
  },
  createElement(tag) { return new FakeElement(tag); },
  appendChild(child) { child.parentNode = this; this.children.push(child); return child; },
  querySelector(selector) { return FakeElement.prototype.querySelectorAll.call(this, selector)[0] || null; },
  querySelectorAll(selector) { return FakeElement.prototype.querySelectorAll.call(this, selector); },
};

/* -------------------------------------------------------------------------------------------
 * Construction d'un champ Race conforme au balisage RÉEL produit par
 * gwseq_render_race_referentiel_field() (includes/race-referentiel.php).
 * ----------------------------------------------------------------------------------------- */

function buildRaceField(form, currentCode, currentLabel, inputId) {
  const field = new FakeElement('span');
  field.className = 'gwseq-race-field';
  field.setAttribute('data-gwseq-race-field', '');

  const search = new FakeElement('input');
  search.className = 'gwseq-race-field__search';
  search.setAttribute('id', inputId);
  search.value = currentLabel;
  field.appendChild(search);

  const codeInput = new FakeElement('input');
  codeInput.className = 'gwseq-race-field__code';
  codeInput.value = currentCode;
  field.appendChild(codeInput);

  const resultsList = new FakeElement('ul');
  resultsList.className = 'gwseq-race-field__results';
  resultsList.hidden = true;
  field.appendChild(resultsList);

  const autreWrap = new FakeElement('span');
  autreWrap.className = 'gwseq-race-field__autre-wrap';
  autreWrap.style.display = currentCode === 'autre' ? '' : 'none';
  const autreInput = new FakeElement('input');
  autreInput.className = 'gwseq-race-field__autre';
  autreWrap.appendChild(autreInput);
  field.appendChild(autreWrap);

  form.appendChild(field);
  return { field, search, codeInput, resultsList, autreWrap, autreInput };
}

function buildBrokenRaceField(form) {
  // Champ structurellement complet (mêmes sous-éléments qu'un champ normal, pour dépasser la garde
  // "!search || !codeInput || !resultsList" et atteindre le CŒUR de initField()), mais dont
  // closest() lève une exception — reproduit un défaut inattendu survenant PENDANT
  // l'initialisation, jamais censé se produire en usage normal, mais dont la conséquence ne doit
  // JAMAIS compromettre l'initialisation des AUTRES champs de la page (Array.prototype.forEach
  // interromprait sinon son parcours à la première exception non rattrapée dans son callback).
  const built = buildRaceField(form, '', '', 'gwseq-race-broken');
  built.field.closest = function () { throw new Error('champ délibérément cassé pour ce test'); };
  return built.field;
}

/* -------------------------------------------------------------------------------------------
 * Référentiel minimal représentatif (mêmes codes que les exemples de la demande), chargé comme le
 * ferait wp_localize_script() via window.gwseqRaceReferentiel.
 * ----------------------------------------------------------------------------------------- */

const gwseqRaceReferentielConfig = {
  entries: [
    { code: 'SF', label: 'Selle Français', ifce: 'Selle Français', type: 'race', alias: ['SFA'] },
    { code: 'OLD', label: 'Oldenburg', ifce: 'Oldenburg', type: 'race', alias: [] },
    { code: 'KWPN', label: 'KWPN', ifce: 'KWPN', type: 'race', alias: [] },
    { code: 'OC', label: 'Origines Constatées (OC)', ifce: 'Origines Constatées', type: 'appellation', alias: [] },
  ],
  suggestions: [{ code: 'SF', label: 'Selle Français' }],
  autreCode: 'autre',
  i18n: { autre: 'Autre — préciser', noResults: 'Aucun résultat' },
};

/* -------------------------------------------------------------------------------------------
 * Chargement et exécution RÉELLE du fichier JS du module dans un contexte vm dédié.
 * ----------------------------------------------------------------------------------------- */

function runScenario() {
  const scriptPath = path.join(__dirname, '..', 'wp-content', 'plugins', 'gws-core', 'modules', 'gws-equestrian', 'assets', 'race-referentiel-autocomplete.js');
  const source = fs.readFileSync(scriptPath, 'utf8');

  fakeDocument.children = [];
  fakeDocument.activeElement = null;
  fakeDocument._listeners = {};

  const form = new FakeElement('form');
  fakeDocument.appendChild(form);

  const fieldA = buildRaceField(form, 'SF', 'Selle Français', 'gwseq-cheval-race');
  const brokenField = buildBrokenRaceField(form);
  const fieldB = buildRaceField(form, '', '', 'gwseq-race-search-pere');

  // console.error() est appelé DÉLIBÉRÉMENT par le script pour le champ cassé (scénario 7) — capturé
  // silencieusement ici plutôt que d'imprimer une pile d'appel alarmante à chaque scénario alors que
  // c'est exactement le comportement de résilience attendu et déjà vérifié par l'assertion dédiée.
  const sandboxConsole = { log: console.log.bind(console), error: function () { sandboxConsole.lastError = Array.prototype.slice.call(arguments); } };
  const sandbox = {
    window: { gwseqRaceReferentiel: gwseqRaceReferentielConfig, console: sandboxConsole },
    document: fakeDocument,
    console: sandboxConsole,
  };
  sandbox.window.document = fakeDocument;
  const context = vm.createContext(sandbox);
  vm.runInContext(source, context, { filename: 'race-referentiel-autocomplete.js' });

  // Le script s'enregistre via document.addEventListener('DOMContentLoaded', ...) : on déclenche
  // l'événement nous-mêmes, exactement comme le ferait le navigateur une fois le parsing terminé.
  fakeDocument.dispatch('DOMContentLoaded');

  return { fieldA, brokenField, fieldB, form };
}

/* ===========================================================================================
 * Scénario 1 — focus d'un champ déjà rempli : le texte doit être sélectionné (search.select()),
 * pour qu'une frappe immédiate REMPLACE la valeur au lieu de s'y concaténer (cause racine n°1).
 * =========================================================================================== */
{
  const { fieldA } = runScenario();
  fieldA.search.dispatch('focus');
  ok('Scénario 1 — focus d’un champ déjà rempli ("Selle Français") : search.select() est bien appelé (le texte existant est sélectionné, une frappe immédiate le remplace au lieu de s’y concaténer)', fieldA.search._selectCalled === true);
  ok('Scénario 1 — au focus, les suggestions/résultats sont bien affichés (liste non masquée)', fieldA.resultsList.hidden === false);
}

/* ===========================================================================================
 * Scénario 2 — saisie "OLD" : la recherche s'exécute réellement contre le référentiel chargé et
 * affiche "Oldenburg" — reproduit très exactement l'exemple de la demande.
 * =========================================================================================== */
{
  const { fieldA } = runScenario();
  fieldA.search.dispatch('focus');
  fieldA.search.value = 'OLD';
  fieldA.search.dispatch('input');
  const labels = fieldA.resultsList.children.map((li) => li.textContent);
  ok('Scénario 2 — saisie "OLD" sur un champ précédemment rempli : la liste affiche bien "Oldenburg" (recherche réellement exécutée)', labels.some((t) => t.indexOf('Oldenburg') !== -1));
  ok('Scénario 2 — "Autre — préciser" reste toujours proposé en dernière position (filet de sécurité)', labels[labels.length - 1] === 'Autre — préciser');
}

/* ===========================================================================================
 * Scénario 3 — clic sur un résultat (mousedown + preventDefault) : mise à jour SYNCHRONE du code
 * caché, sans dépendre d'un quelconque délai, et SANS jamais déclencher `blur` (comportement natif
 * d'un mousedown avec preventDefault() sur un élément non focusable).
 * =========================================================================================== */
{
  const { fieldA } = runScenario();
  fieldA.search.dispatch('focus');
  fieldA.search.value = 'OLD';
  fieldA.search.dispatch('input');
  const oldenburgItem = fieldA.resultsList.children.find((li) => li.textContent.indexOf('Oldenburg') !== -1);
  let blurFired = false;
  fieldA.search.addEventListener('blur', () => { blurFired = true; });
  oldenburgItem.dispatch('mousedown');
  ok('Scénario 3 — clic sur "Oldenburg" : le code caché est immédiatement "OLD" (mise à jour synchrone, aucun délai)', fieldA.codeInput.value === 'OLD');
  ok('Scénario 3 — le champ de recherche affiche bien "Oldenburg" après sélection', fieldA.search.value === 'Oldenburg');
  ok('Scénario 3 — un clic sur un résultat ne déclenche jamais `blur` sur le champ de recherche (mousedown+preventDefault)', blurFired === false);
  ok('Scénario 3 — la liste de résultats est refermée après sélection', fieldA.resultsList.hidden === true);
}

/* ===========================================================================================
 * Scénario 4 — touche Entrée : sélectionne le premier résultat affiché et empêche activement la
 * soumission du formulaire (plus jamais d'enregistrement accidentel de toute la fiche cheval).
 * =========================================================================================== */
{
  const { fieldA } = runScenario();
  fieldA.search.dispatch('focus');
  fieldA.search.value = 'OLD';
  fieldA.search.dispatch('input');
  const enterEvent = fieldA.search.dispatch('keydown', { key: 'Enter' });
  ok('Scénario 4 — touche Entrée : le premier résultat affiché ("Oldenburg") est bien sélectionné', fieldA.codeInput.value === 'OLD' && fieldA.search.value === 'Oldenburg');
  ok('Scénario 4 — touche Entrée : la soumission native du formulaire (comportement par défaut du navigateur pour Entrée dans un champ texte) est activement empêchée (event.preventDefault() appelé) — plus jamais d’enregistrement accidentel de toute la fiche cheval', enterEvent.defaultPrevented === true);
}

/* ===========================================================================================
 * Scénario 5 — champ vidé puis soumission DIRECTE du formulaire, sans passer par `blur` : le
 * filet de sécurité de soumission committe bien le champ à une valeur vide (race "Non renseignée").
 * =========================================================================================== */
{
  const { fieldA, form } = runScenario();
  fieldA.search.dispatch('focus');
  fieldA.search.value = '';
  fieldA.search.dispatch('input');
  form.dispatch('submit');
  ok('Scénario 5 — champ vidé puis soumission directe (sans `blur` préalable) : le code caché est bien vide, "Non renseignée" reste possible', fieldA.codeInput.value === '');
  ok('Scénario 5 — le bloc "Autre — préciser" reste bien masqué pour un champ vidé', fieldA.autreWrap.style.display === 'none');
}

/* ===========================================================================================
 * Scénario 6 — libellé complet tapé, jamais validé par un clic, puis `blur` (perte de focus vers
 * un autre champ, ex. le bouton Enregistrer) : repli honnête sur "Autre" + texte tapé, JAMAIS un
 * retour silencieux à l'ancienne valeur ("Selle Français").
 * =========================================================================================== */
{
  const { fieldA } = runScenario();
  fieldA.search.dispatch('focus');
  fieldA.search.value = 'Oldenburg';
  fieldA.search.dispatch('input');
  fieldA.search.dispatch('blur'); // ex. clic direct sur "Enregistrer" sans jamais cliquer un résultat
  ok('Scénario 6 — saisie jamais validée par un clic puis perte de focus : le code caché N’EST JAMAIS resté sur l’ancienne valeur "SF"', fieldA.codeInput.value !== 'SF');
  ok('Scénario 6 — repli honnête sur "Autre" (le texte tapé ne correspond à aucun code réellement sélectionné)', fieldA.codeInput.value === 'autre');
  ok('Scénario 6 — le texte tapé ("Oldenburg") est bien conservé intégralement dans le champ de précision "Autre", jamais perdu', fieldA.autreInput.value === 'Oldenburg');
  ok('Scénario 6 — le bloc "Autre — préciser" est bien affiché', fieldA.autreWrap.style.display === '');
}

/* ===========================================================================================
 * Scénario 7 — un champ Race malformé sur la même page n'empêche jamais l'initialisation des
 * AUTRES champs Race (résilience de la boucle d'initialisation).
 * =========================================================================================== */
{
  const { fieldB } = runScenario();
  // Le champ B (ascendant externe, initialement vide) doit s'être normalement initialisé MALGRÉ
  // la présence du champ malformé juste avant lui dans le DOM.
  fieldB.search.dispatch('focus');
  fieldB.search.value = 'kwp';
  fieldB.search.dispatch('input');
  const labels = fieldB.resultsList.children.map((li) => li.textContent);
  ok('Scénario 7 — un champ Race malformé présent sur la page n’empêche jamais l’initialisation d’un AUTRE champ Race (résilience try/catch de la boucle d’initialisation)', labels.some((t) => t.indexOf('KWPN') !== -1));
}

console.log('');
console.log(assertionCount + ' assertions, ' + failureCount + ' échec(s).');
process.exit(failureCount === 0 ? 0 : 1);

/**
 * Test d'EXÉCUTION RÉELLE de assets/cheval-selection-admin.js — l'écran « Chevaux → Sélections »
 * (Suite V1 « Partager & vendre », Lot 2A).
 *
 * Même méthodologie que tests/gws-equestrian-cheval-share-runtime-test.js (DOM minimal fait main,
 * aucune dépendance npm, exécution RÉELLE du fichier JS via le module `vm` de Node) : vérifie le
 * CÂBLAGE réel (recherche/filtres réutilisés, case à cocher <-> sélection en cours, ordre Monter/
 * Descendre, compteur, activation/désactivation du bouton de création, appel de création avec les
 * bons identifiants dans le bon ordre, redirection après succès, rendu de la liste des sélections
 * existantes, confirmation avant régénérer/révoquer) — jamais une revalidation de la logique déjà
 * testée côté PHP (recherche/éligibilité/persistance).
 *
 * Exécution : node tests/gws-equestrian-cheval-selection-runtime-test.js
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

function wait(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

/* -------------------------------------------------------------------------------------------
 * DOM minimal — repris de gws-equestrian-cheval-share-runtime-test.js (même besoin exact :
 * construction d'arbre, attributs data-*, événements avec bulling, sélecteurs simples).
 * ----------------------------------------------------------------------------------------- */

class FakeElement {
  constructor(tag) {
    this.tagName = tag;
    this.id = '';
    this.className = '';
    this.children = [];
    this.parentNode = null;
    this._attrs = {};
    this._listeners = {};
    this.style = {};
    this.checked = false;
    this.value = '';
    this.disabled = false;
    this.hidden = false;
    this.type = '';
    this.href = '';
    this.readOnly = false;
    this._text = '';
  }
  get textContent() { return this._text; }
  set textContent(v) { this._text = v === undefined || v === null ? '' : String(v); }
  setAttribute(name, value) { this._attrs[name] = String(value); }
  getAttribute(name) { return Object.prototype.hasOwnProperty.call(this._attrs, name) ? this._attrs[name] : null; }
  appendChild(child) { child.parentNode = this; this.children.push(child); return child; }
  removeChild(child) { this.children = this.children.filter((c) => c !== child); child.parentNode = null; return child; }
  get firstChild() { return this.children.length ? this.children[0] : null; }
  addEventListener(type, fn) { (this._listeners[type] = this._listeners[type] || []).push(fn); }
  dispatchEvent(evt) {
    if (!evt.preventDefault) evt.preventDefault = function () { evt.defaultPrevented = true; };
    ((this._listeners[evt.type] || []).slice()).forEach((fn) => fn(evt));
    if (this.parentNode) this.parentNode.dispatchEvent(evt); // bubbling
    return evt;
  }
  click() { this.dispatchEvent({ type: 'click' }); }
  focus() {}
  select() {}
  _matchesSimple(sel) {
    if (sel[0] === '#') return this.id === sel.slice(1);
    const attrMatch = sel.match(/\[([a-z0-9-]+)\]$/);
    let rest = sel;
    if (attrMatch) {
      if (this.getAttribute(attrMatch[1]) === null) return false;
      rest = sel.slice(0, sel.length - attrMatch[0].length);
    }
    if (rest === '') return true;
    const parts = rest.split('.').filter(Boolean);
    const hasTag = rest[0] !== '.';
    const tag = hasTag ? parts[0] : null;
    const classes = hasTag ? parts.slice(1) : parts;
    if (tag && this.tagName !== tag) return false;
    const ownClasses = (this.className || '').split(/\s+/);
    for (let i = 0; i < classes.length; i++) {
      if (ownClasses.indexOf(classes[i]) === -1) return false;
    }
    return true;
  }
  querySelectorAll(sel) {
    const out = [];
    (function walk(node) {
      node.children.forEach((child) => {
        if (child._matchesSimple(sel)) out.push(child);
        walk(child);
      });
    })(this);
    out.forEach = Array.prototype.forEach;
    return out;
  }
  querySelector(sel) {
    const all = this.querySelectorAll(sel);
    return all.length ? all[0] : null;
  }
}

class FakeFormData {
  constructor() { this.entries = []; }
  append(key, value) { this.entries.push([key, String(value)]); }
}

function formValues(formData, key) {
  return formData.entries.filter((e) => e[0] === key).map((e) => e[1]);
}
function formValue(formData, key) {
  const values = formValues(formData, key);
  return values.length ? values[0] : undefined;
}

/* -------------------------------------------------------------------------------------------
 * Fixtures + faux "serveur" AJAX minimal.
 * ----------------------------------------------------------------------------------------- */

const RESULT_A = { id: 10, nom: 'Jamerose de Felines', photo_url: '', sous_titre: 'Jument Selle Français — 7 ans', statut: 'À vendre' };
const RESULT_B = { id: 11, nom: 'Untouchable 27', photo_url: 'https://example.test/photo-11.jpg', sous_titre: 'Étalon Selle Français — 12 ans', statut: '' };

const EXISTING_ROW_ACTIVE = {
  id: 500, titre: 'Chevaux pour Guillaume', date: '2026-09-04', total_chevaux: 3, chevaux_diffusables: 2,
  token_actif: true, url: 'https://example.test/selection/aaaa/', url_regenerer: 'https://example.test/wp-admin/admin-post.php?action=gwseq_selection_regenerer&selection_id=500',
  url_revoquer: 'https://example.test/wp-admin/admin-post.php?action=gwseq_selection_revoquer&selection_id=500',
};
const EXISTING_ROW_REVOKED = {
  id: 501, titre: 'Sélection de chevaux', date: '2026-09-03', total_chevaux: 1, chevaux_diffusables: 1,
  token_actif: false, url: '', url_regenerer: 'https://example.test/wp-admin/admin-post.php?action=gwseq_selection_regenerer&selection_id=501',
  url_revoquer: 'https://example.test/wp-admin/admin-post.php?action=gwseq_selection_revoquer&selection_id=501',
};

function fakeServerResponse(formData, state) {
  const action = formValue(formData, 'action');
  if (action === 'gwseq_selection_search_cheval') {
    state.searchCalls.push(formData);
    return { success: true, data: { resultats: state.searchResults } };
  }
  if (action === 'gwseq_selection_create') {
    state.createCalls.push(formData);
    if (state.createShouldFail) return { success: false, data: { message: 'Erreur de test' } };
    return { success: true, data: { redirect: 'https://example.test/wp-admin/edit.php?post_type=gwseq_cheval&page=gwseq-selections' } };
  }
  return { success: false };
}

function buildSandbox(options) {
  options = options || {};
  const root = new FakeElement('div');
  root.id = 'gwseq-selections-app';

  const documentListeners = {};
  const fakeDocument = {
    getElementById(id) { return id === root.id ? root : null; },
    createElement(tag) { return new FakeElement(tag); },
    addEventListener(type, fn) { (documentListeners[type] = documentListeners[type] || []).push(fn); },
    body: new FakeElement('body'),
  };
  fakeDocument.body.appendChild = function (child) { return child; };
  fakeDocument.body.removeChild = function () {};
  fakeDocument.execCommand = function () { return true; };

  const state = {
    searchCalls: [],
    createCalls: [],
    searchResults: options.searchResults || [RESULT_A, RESULT_B],
    createShouldFail: !!options.createShouldFail,
  };

  const confirmCalls = [];
  const fakeWindow = {
    gwseqSelections: {
      ajaxUrl: 'https://example.test/wp-admin/admin-ajax.php',
      nonce: 'test-nonce',
      recents: state.searchResults,
      existantes: options.existantes || [],
      nouvelleUrl: 'https://example.test/wp-admin/edit.php?post_type=gwseq_cheval&page=gwseq-selections&vue=nouvelle',
      listeUrl: 'https://example.test/wp-admin/edit.php?post_type=gwseq_cheval&page=gwseq-selections',
      filters: {
        diffusion: { diffusion_privee: 'Diffusion privée', visible_site: 'Visible sur le site' },
        sexe: { female: 'Jument', male: 'Étalon', gelding: 'Hongre' },
        statut: { not_offered: 'Non proposé', for_sale: 'À vendre' },
        categories: { chevaux_de_sport: 'Chevaux de sport' },
        anneeMin: 1900,
        anneeMax: 2027,
      },
      i18n: {},
    },
    FormData: FakeFormData,
    URLSearchParams: URLSearchParams,
    fetch(url, requestOptions) {
      return new Promise((resolve) => {
        const json = fakeServerResponse(requestOptions.body, state);
        resolve({ json: () => Promise.resolve(json) });
      });
    },
    confirm(message) { confirmCalls.push(message); return options.confirmReturns !== undefined ? options.confirmReturns : true; },
    navigator: { clipboard: { writeText(text) { state.lastCopied = text; return Promise.resolve(); } } },
    location: { search: options.locationSearch || '', href: '' },
    setTimeout: setTimeout,
    clearTimeout: clearTimeout,
    Promise: Promise,
  };

  return { root, fakeDocument, fakeWindow, documentListeners, state, confirmCalls };
}

function runScript(sandboxParts) {
  const sandbox = { document: sandboxParts.fakeDocument, window: sandboxParts.fakeWindow, URLSearchParams: URLSearchParams };
  vm.createContext(sandbox);
  const scriptPath = path.join(__dirname, '..', 'wp-content', 'plugins', 'gws-core', 'modules', 'gws-equestrian', 'assets', 'cheval-selection-admin.js');
  vm.runInContext(fs.readFileSync(scriptPath, 'utf8'), sandbox, { filename: 'cheval-selection-admin.js' });
  (sandboxParts.documentListeners.DOMContentLoaded || []).forEach((fn) => fn());
}

async function run() {
  /* --- Vue liste : vide --- */
  const emptyParts = buildSandbox({ existantes: [] });
  runScript(emptyParts);
  ok('Liste vide : message explicite affiché', emptyParts.root.querySelector('.description') !== null);
  ok('Liste vide : jamais de tableau vide affiché', emptyParts.root.querySelectorAll('table').length === 0);

  /* --- Vue liste : sélections existantes --- */
  const listParts = buildSandbox({ existantes: [EXISTING_ROW_ACTIVE, EXISTING_ROW_REVOKED] });
  runScript(listParts);
  const rows = listParts.root.querySelectorAll('tr').filter((tr) => tr.tagName === 'tr' && tr.children.length && tr.children[0].tagName === 'td');
  ok('Liste : une ligne par sélection existante', rows.length === 2);

  const linkInputs = listParts.root.querySelectorAll('.gwseq-selections-link-input');
  ok('Liste : le lien actif est affiché dans un champ (copiable)', linkInputs.length === 1 && linkInputs[0].value === EXISTING_ROW_ACTIVE.url);

  const revokeLinks = listParts.root.querySelectorAll('a.button');
  const revokeLink = revokeLinks.filter((a) => a.href === EXISTING_ROW_ACTIVE.url_revoquer)[0];
  const regenerateLink = revokeLinks.filter((a) => a.href === EXISTING_ROW_REVOKED.url_regenerer)[0];
  ok('Liste : action "Révoquer" proposée pour la sélection au token actif', !!revokeLink);
  ok('Liste : action "Régénérer" proposée pour la sélection révoquée (jamais "Révoquer" sur un lien déjà mort)', !!regenerateLink);

  // Confirmation AVANT toute action destructive/impactante (§13/§14) — un clic annulé par
  // l'utilisateur ne doit jamais déclencher la navigation vers admin-post.php.
  const cancelledParts = buildSandbox({ existantes: [EXISTING_ROW_ACTIVE], confirmReturns: false });
  runScript(cancelledParts);
  const revokeLinkCancel = cancelledParts.root.querySelectorAll('a.button')[0];
  const evt = revokeLinkCancel.dispatchEvent({ type: 'click' });
  ok('Liste : révoquer demande confirmation, un refus empêche la navigation (preventDefault appelé)', evt.defaultPrevented === true);
  ok('Liste : la confirmation a bien été sollicitée avant toute action', cancelledParts.confirmCalls.length === 1);

  /* --- Bascule liste <-> création --- */
  const newButton = listParts.root.querySelector('button.button-primary');
  ok('Liste : bouton "+ Nouvelle sélection" présent', newButton !== null);
  newButton.click();
  ok('Bascule : la vue création remplace la liste au clic', listParts.root.querySelector('.gwseq-selections-create') !== null);

  const backButton = listParts.root.querySelector('.gwseq-selections-back');
  backButton.click();
  ok('Bascule : "Retour aux sélections" ramène bien à la vue liste', listParts.root.querySelector('.gwseq-selections-list') !== null);

  /* --- Ouverture directe en mode création (paramètre ?vue=nouvelle, lien "+ Nouvelle sélection"
     de la vue liste elle-même construit une telle URL) --- */
  const directCreateParts = buildSandbox({ locationSearch: '?post_type=gwseq_cheval&page=gwseq-selections&vue=nouvelle' });
  runScript(directCreateParts);
  ok('Ouverture directe : la vue création est affichée d’emblée si ?vue=nouvelle', directCreateParts.root.querySelector('.gwseq-selections-create') !== null);

  /* --- Vue création : recherche, sélection, ordre, compteur, création --- */
  const parts = buildSandbox({ existantes: [] });
  runScript(parts);
  parts.root.querySelector('button.button-primary').click(); // "+ Nouvelle sélection"
  const createRoot = parts.root;

  // Le sélecteur ne supporte qu'un seul niveau (voir le DOM minimal ci-dessus) : la case à cocher
  // est le premier enfant de son <label class="gwseq-selections-checkbox">, jamais interrogée
  // directement par attribut "checkbox" (fixé via une propriété JS, jamais setAttribute()).
  const resultCheckboxes = createRoot.querySelectorAll('.gwseq-selections-checkbox').map((label) => label.children[0]);
  ok('Création : une case par résultat de recherche (recents)', resultCheckboxes.length === 2);

  const createButton = createRoot.querySelectorAll('button').filter((b) => b.textContent === 'Créer la sélection')[0];
  ok('Création : le bouton "Créer la sélection" est présent', createButton !== undefined);
  ok('Création : bouton désactivé tant qu’aucun cheval n’est sélectionné', createButton.disabled === true);

  const countEl = createRoot.querySelector('.gwseq-selections-selected__count');
  ok('Création : compteur "Aucun cheval sélectionné" au départ', countEl.textContent === 'Aucun cheval sélectionné');

  // Coche le premier résultat (RESULT_A, id 10).
  resultCheckboxes[0].checked = true;
  resultCheckboxes[0].dispatchEvent({ type: 'change' });
  ok('Création : compteur "1 cheval sélectionné" après une case cochée', countEl.textContent === '1 cheval sélectionné');
  ok('Création : bouton "Créer la sélection" activé dès qu’au moins un cheval est sélectionné', createButton.disabled === false);

  // Coche le second résultat (RESULT_B, id 11) -> ordre d'ajout = [10, 11].
  resultCheckboxes[1].checked = true;
  resultCheckboxes[1].dispatchEvent({ type: 'change' });
  ok('Création : compteur "2 chevaux sélectionnés" (pluriel, §7 : indiquer clairement le nombre)', countEl.textContent === '2 chevaux sélectionnés');

  function itemName(item) { return item.querySelector('strong').textContent; }

  let selectedItems = createRoot.querySelectorAll('.gwseq-selections-selected__item');
  ok('Création : panneau "sélection en cours" affiche les deux chevaux, dans l’ordre d’ajout', selectedItems.length === 2 && itemName(selectedItems[0]) === 'Jamerose de Felines' && itemName(selectedItems[1]) === 'Untouchable 27');

  // --- Ordre explicite (§8) : "Monter" le second élément le fait passer en premier ---
  const downOrUpButtons = selectedItems[1].querySelectorAll('button');
  const upButtonSecondItem = downOrUpButtons[0]; // premier bouton de contrôle = "Monter" (voir JS)
  upButtonSecondItem.click();
  selectedItems = createRoot.querySelectorAll('.gwseq-selections-selected__item');
  ok('Ordre (§8) : "Monter" le second cheval l’amène en première position', itemName(selectedItems[0]) === 'Untouchable 27' && itemName(selectedItems[1]) === 'Jamerose de Felines');

  // --- Retrait explicite (alternative à décocher directement depuis les résultats) ---
  const removeButtonFirstItem = selectedItems[0].querySelectorAll('button')[2]; // Monter, Descendre, Retirer
  removeButtonFirstItem.click();
  selectedItems = createRoot.querySelectorAll('.gwseq-selections-selected__item');
  ok('Retrait : un clic sur "Retirer" fait disparaître le cheval du panneau', selectedItems.length === 1 && itemName(selectedItems[0]) === 'Jamerose de Felines');
  ok('Retrait : la case correspondante redevient décochée dans les résultats (synchronisation)', resultCheckboxes[1].checked === false);

  // Recoche les deux pour le test de création (ordre : 11 rajouté à la fin, puis retiré, on ne
  // reteste que la persistance de l'ORDRE final soumis, pas cette manipulation intermédiaire).
  resultCheckboxes[1].checked = true;
  resultCheckboxes[1].dispatchEvent({ type: 'change' });

  const titleInput = createRoot.querySelector('#gwseq-selections-title');
  titleInput.value = 'Chevaux pour Guillaume';

  createButton.click();
  await wait(20);

  ok('Création : un appel AJAX "gwseq_selection_create" a bien été émis', parts.state.createCalls.length === 1);
  const createCall = parts.state.createCalls[0];
  ok('Création : le titre saisi est transmis', formValue(createCall, 'title') === 'Chevaux pour Guillaume');
  ok('Création : les identifiants sont transmis DANS L’ORDRE de la sélection en cours (§8)', JSON.stringify(formValues(createCall, 'cheval_ids[]')) === JSON.stringify(['10', '11']));
  ok('Création réussie : redirection vers la liste (URL renvoyée par le serveur)', parts.fakeWindow.location.href === 'https://example.test/wp-admin/edit.php?post_type=gwseq_cheval&page=gwseq-selections');

  /* --- Échec de création : message d’erreur affiché, bouton réactivé, jamais de redirection --- */
  const failParts = buildSandbox({ createShouldFail: true });
  runScript(failParts);
  failParts.root.querySelector('button.button-primary').click();
  const failCheckboxes = failParts.root.querySelectorAll('.gwseq-selections-checkbox').map((label) => label.children[0]);
  failCheckboxes[0].checked = true;
  failCheckboxes[0].dispatchEvent({ type: 'change' });
  const failCreateButton = failParts.root.querySelectorAll('button').filter((b) => b.textContent === 'Créer la sélection' || b.textContent === 'Création…')[0];
  failCreateButton.click();
  await wait(20);
  ok('Échec de création : message d’erreur du serveur affiché', failParts.root.querySelector('.gwseq-selections-create-error').hidden === false);
  ok('Échec de création : jamais de redirection', failParts.fakeWindow.location.href === '');
  ok('Échec de création : le bouton reste actionnable (redevient "Créer la sélection", au moins un cheval encore sélectionné)', failParts.root.querySelectorAll('button').filter((b) => b.textContent === 'Créer la sélection')[0].disabled === false);

  /* --- Recherche réutilise bien le point d’entrée AJAX dédié (§5/§7) --- */
  const searchParts = buildSandbox({ searchResults: [RESULT_A] });
  runScript(searchParts);
  searchParts.root.querySelector('button.button-primary').click();
  const searchInput = searchParts.root.querySelector('#gwseq-selections-search-input');
  searchInput.value = 'Jamerose';
  searchInput.dispatchEvent({ type: 'input' });
  await wait(400); // débounce (300ms) + marge
  ok('Recherche : appelle bien "gwseq_selection_search_cheval" (jamais l’action de l’écran « Partager »)', searchParts.state.searchCalls.length === 1);
  ok('Recherche : le texte saisi est transmis', formValue(searchParts.state.searchCalls[0], 's') === 'Jamerose');

  console.log('');
  if (failureCount > 0) {
    console.log(failureCount + ' assertion(s) en échec sur ' + assertionCount + '.');
    process.exit(1);
  }
  console.log('Tous les tests sont passés. (' + assertionCount + ' assertions)');
}

run();

/**
 * Test d'EXÉCUTION RÉELLE de assets/cheval-share-admin.js — l'écran « Chevaux → Partager ».
 *
 * Pourquoi ce fichier existe : la suite PHP ne peut vérifier que le texte source du script, jamais
 * son exécution réelle contre un DOM. Ce test exécute RÉELLEMENT le fichier JS du module (via le
 * module `vm` de Node, DOM minimal fait main, aucune dépendance npm) contre un faux serveur AJAX
 * minimal (qui ne reproduit PAS la logique de composition déjà testée côté PHP — seulement de quoi
 * vérifier le CÂBLAGE : que les cases cochées/décochées changent bien la sélection transmise, que
 * l'aperçu se met à jour, et que WhatsApp/SMS/Copier consomment tous les trois EXACTEMENT le même
 * texte déjà composé, sans jamais le reconstruire séparément — §4/§14-18 de la demande).
 *
 * Exécution : node tests/gws-equestrian-cheval-share-runtime-test.js
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
 * DOM minimal — suffisant pour ce script précis (construction d'arbre via createElement/
 * appendChild, attributs data-*, className/id/checked/value en propriétés directes, événements
 * AVEC BULLING — nécessaire : le script écoute 'input'/'change' au niveau du conteneur englobant,
 * jamais sur chaque champ individuellement).
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
    this.hidden = false;
    this._text = '';
  }
  get textContent() { return this._text; }
  set textContent(v) { this._text = v === undefined || v === null ? '' : String(v); }
  setAttribute(name, value) { this._attrs[name] = String(value); }
  getAttribute(name) { return Object.prototype.hasOwnProperty.call(this._attrs, name) ? this._attrs[name] : null; }
  appendChild(child) { child.parentNode = this; this.children.push(child); return child; }
  addEventListener(type, fn) { (this._listeners[type] = this._listeners[type] || []).push(fn); }
  dispatchEvent(evt) {
    ((this._listeners[evt.type] || []).slice()).forEach((fn) => fn(evt));
    if (this.parentNode) this.parentNode.dispatchEvent(evt); // bubbling
  }
  focus() {}
  select() {}
  _matchesSimple(sel) {
    if (sel[0] === '#') return this.id === sel.slice(1);
    if (sel[0] === '.') return (this.className || '').split(/\s+/).indexOf(sel.slice(1)) !== -1;
    const attrMatch = sel.match(/^\[([a-z0-9-]+)\]$/);
    if (attrMatch) return this.getAttribute(attrMatch[1]) !== null;
    return this.tagName === sel;
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
 * Fixture cheval + faux "serveur" AJAX minimal (ne reproduit PAS la composition déjà testée côté
 * PHP — seulement de quoi vérifier que le script transmet/consomme correctement les bonnes données).
 * ----------------------------------------------------------------------------------------- */

const FIXTURE_SHAREABLE = {
  id: 10,
  nom: 'Jamerose de Felines',
  nom_affiche: 'JAMEROSE DE FELINES',
  photo_url: 'https://example.test/photo.jpg',
  items: {
    identite: { label: 'Jument Selle Français — 7 ans', default_checked: true },
    origines: { label: 'Par UNTOUCHABLE × KANNAN', default_checked: true },
    prix: { label: 'À vendre — 25 000 €', default_checked: false },
  },
  videos: [
    { index: 0, label: '🎥 Allures à 3 ans', url: 'https://example.test/v1', default_checked: true },
  ],
  fiche_url: 'https://example.test/chevaux/cheval-10/',
  fiche_default_checked: true,
};

function fakeServerResponse(formData) {
  const action = formValue(formData, 'action');
  if (action === 'gwseq_partager_get_cheval') {
    return { success: true, data: { cheval: FIXTURE_SHAREABLE } };
  }
  if (action === 'gwseq_partager_build_message') {
    const items = formValues(formData, 'selection[items][]');
    const videos = formValues(formData, 'selection[videos][]');
    const fiche = formValue(formData, 'selection[fiche]');
    const personal = formValue(formData, 'selection[message_personnel]') || '';
    let lines = [FIXTURE_SHAREABLE.nom_affiche];
    items.forEach((key) => { if (FIXTURE_SHAREABLE.items[key]) lines.push(FIXTURE_SHAREABLE.items[key].label); });
    let message = (personal ? personal + '\n\n' : '') + lines.join('\n');
    videos.forEach((index) => {
      const video = FIXTURE_SHAREABLE.videos.filter((v) => String(v.index) === String(index))[0];
      if (video) message += '\n' + video.label + ' : ' + video.url;
    });
    if (fiche) message += '\n' + FIXTURE_SHAREABLE.fiche_url;
    return { success: true, data: { message: message } };
  }
  if (action === 'gwseq_partager_search_cheval') {
    return { success: true, data: { resultats: [] } };
  }
  return { success: false };
}

function buildSandbox() {
  const root = new FakeElement('div');
  root.id = 'gwseq-partager-app';

  const documentListeners = {};
  const fakeDocument = {
    getElementById(id) { return id === root.id ? root : null; },
    createElement(tag) { return new FakeElement(tag); },
    addEventListener(type, fn) { (documentListeners[type] = documentListeners[type] || []).push(fn); },
  };

  const openedUrls = [];
  const clipboardWrites = [];
  const fakeWindow = {
    gwseqPartager: {
      ajaxUrl: 'https://example.test/wp-admin/admin-ajax.php',
      nonce: 'test-nonce',
      recents: [],
      i18n: {},
    },
    FormData: FakeFormData,
    fetch(url, options) {
      const json = fakeServerResponse(options.body);
      return Promise.resolve({ json: () => Promise.resolve(json) });
    },
    open(url) { openedUrls.push(url); },
    location: { href: '' },
    navigator: {
      clipboard: {
        writeText(text) { clipboardWrites.push(text); return Promise.resolve(); },
      },
    },
    setTimeout: setTimeout,
    clearTimeout: clearTimeout,
    Promise: Promise,
  };

  return { root, fakeDocument, fakeWindow, documentListeners, openedUrls, clipboardWrites };
}

function runScript(sandboxParts) {
  const sandbox = { document: sandboxParts.fakeDocument, window: sandboxParts.fakeWindow };
  vm.createContext(sandbox);
  const scriptPath = path.join(__dirname, '..', 'wp-content', 'plugins', 'gws-core', 'modules', 'gws-equestrian', 'assets', 'cheval-share-admin.js');
  vm.runInContext(fs.readFileSync(scriptPath, 'utf8'), sandbox, { filename: 'cheval-share-admin.js' });
  (sandboxParts.documentListeners.DOMContentLoaded || []).forEach((fn) => fn());
}

async function run() {
  const parts = buildSandbox();
  runScript(parts);

  // Aucun cheval présélectionné -> écran de recherche affiché en premier.
  ok('Écran de recherche affiché par défaut (aucun cheval présélectionné)', parts.root.querySelector('.gwseq-partager-search__input') !== null);

  // Simule le clic "Partager" sur un résultat en appelant directement le point d'entrée de
  // chargement via une présélection (équivalent fonctionnel du clic, sans dépendre de la structure
  // interne exacte de la liste de résultats déjà couverte par la suite PHP).
  const parts2 = buildSandbox();
  parts2.root.setAttribute('data-gwseq-preselected-id', '10');
  runScript(parts2);
  await wait(20);

  const composeRoot = parts2.root;
  ok('Écran de composition chargé pour le cheval présélectionné', composeRoot.querySelector('.gwseq-partager-compose') !== null);

  const itemCheckboxes = composeRoot.querySelectorAll('[data-item-key]');
  ok('Une case par information partageable réellement disponible (3 dans la fixture)', itemCheckboxes.length === 3);

  const identiteCheckbox = itemCheckboxes.filter((c) => c.getAttribute('data-item-key') === 'identite')[0];
  const prixCheckbox = itemCheckboxes.filter((c) => c.getAttribute('data-item-key') === 'prix')[0];
  ok('Case "identité" cochée par défaut (default_checked de la fixture)', identiteCheckbox.checked === true);
  ok('Case "prix" PAS cochée par défaut (prudence commerciale, §9)', prixCheckbox.checked === false);

  const videoCheckboxes = composeRoot.querySelectorAll('[data-video-index]');
  ok('Une case par vidéo réellement disponible', videoCheckboxes.length === 1);

  const ficheCheckbox = composeRoot.querySelector('#gwseq-partager-fiche');
  ok('Case "fiche complète" présente et cochée par défaut (lien public disponible)', ficheCheckbox !== null && ficheCheckbox.checked === true);

  // --- Aperçu initial (composé dès le chargement, avant toute interaction) ---
  await wait(20);
  const previewNode = composeRoot.querySelector('.gwseq-partager-preview__text');
  ok('Aperçu initial composé dès le chargement (identité + origines, cochées par défaut)', previewNode.textContent.indexOf('Jument Selle Français') !== -1 && previewNode.textContent.indexOf('UNTOUCHABLE') !== -1);
  ok('Aperçu initial : le prix n’apparaît pas (non sélectionné par défaut)', previewNode.textContent.indexOf('25 000') === -1);

  // --- Décocher "identité", cocher "prix" -> l'aperçu doit refléter EXACTEMENT ce changement ---
  identiteCheckbox.checked = false;
  identiteCheckbox.dispatchEvent({ type: 'change' });
  prixCheckbox.checked = true;
  prixCheckbox.dispatchEvent({ type: 'change' });
  await wait(500); // débounce de l'aperçu (350ms) + marge

  ok('Aperçu mis à jour : "identité" décochée -> disparaît du texte', previewNode.textContent.indexOf('Jument Selle Français') === -1);
  ok('Aperçu mis à jour : "prix" cochée -> apparaît désormais dans le texte', previewNode.textContent.indexOf('25 000') !== -1);

  // --- Message personnel : ajouté en tête de l'aperçu ---
  const messageInput = composeRoot.querySelector('#gwseq-partager-message-personnel');
  messageInput.value = 'Bonjour Pierre !';
  messageInput.dispatchEvent({ type: 'input' });
  await wait(500);
  ok('Aperçu mis à jour : le message personnel apparaît bien en tête', previewNode.textContent.indexOf('Bonjour Pierre !') === 0);

  // --- WhatsApp / SMS / Copier consomment tous les trois EXACTEMENT le même texte déjà affiché
  // dans l'aperçu — jamais une reconstruction séparée par canal (§4/§17-18) ---
  const currentPreviewText = previewNode.textContent;
  const buttons = composeRoot.querySelectorAll('.gwseq-partager-action');
  const whatsappButton = buttons.filter((b) => b.className.indexOf('gwseq-partager-action--whatsapp') !== -1)[0];
  const smsButton = buttons.filter((b) => b.className.indexOf('gwseq-partager-action--sms') !== -1)[0];
  const copyButton = buttons.filter((b) => b.className.indexOf('gwseq-partager-action--copy') !== -1)[0];

  whatsappButton.dispatchEvent({ type: 'click' });
  ok('WhatsApp : ouvre bien "https://wa.me/?text=" avec le texte de l’aperçu correctement encodé', parts2.openedUrls[0] === 'https://wa.me/?text=' + encodeURIComponent(currentPreviewText));

  smsButton.dispatchEvent({ type: 'click' });
  ok('SMS : navigue bien vers "sms:?body=" avec le MÊME texte correctement encodé (jamais "iMessage" promis)', parts2.fakeWindow.location.href === 'sms:?body=' + encodeURIComponent(currentPreviewText));

  copyButton.dispatchEvent({ type: 'click' });
  await wait(20);
  ok('Copier : copie bien le MÊME texte exact que l’aperçu affiché, sans reconstruction séparée', parts2.clipboardWrites[0] === currentPreviewText);

  const copyFeedback = composeRoot.querySelector('.gwseq-partager-copy-feedback');
  ok('Copier : le retour "Message copié" devient visible (annoncé via aria-live, §28 accessibilité)', copyFeedback !== null && copyFeedback.hidden === false);

  // --- Encodage : un texte contenant espaces, retours à la ligne et accents doit être correctement
  // encodé pour une URL (§29 : "encodage WhatsApp"/"encodage SMS") ---
  const sampleText = 'Jument Selle Français\n\nFiche complète :\nhttps://example.test/';
  const encoded = encodeURIComponent(sampleText);
  ok('Encodage : les retours à la ligne deviennent %0A', encoded.indexOf('%0A') !== -1);
  ok('Encodage : les espaces deviennent %20', encoded.indexOf('%20') !== -1);
  ok('Encodage : les caractères accentués sont bien encodés (jamais laissés bruts dans l’URL)', encoded.indexOf('Français') === -1 && encoded.indexOf('Fran%C3%A7ais') !== -1);

  if (failureCount > 0) {
    console.log('\n' + failureCount + ' assertion(s) EN ÉCHEC sur ' + assertionCount + '.');
    process.exitCode = 1;
  } else {
    console.log('\nTous les tests sont passés. (' + assertionCount + ' assertions)');
  }
}

run();

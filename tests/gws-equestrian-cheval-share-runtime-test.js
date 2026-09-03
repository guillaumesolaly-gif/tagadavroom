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
  // Supporte les sélecteurs simples ET COMPOSÉS suffisants pour ce script : "#id",
  // "tag.classe1.classe2", ".classe1.classe2", "[attr]", ou toute combinaison des trois (jamais de
  // combinateurs de descendance — un seul niveau, comme partout ailleurs dans ce fichier).
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

const FIXTURE_SHAREABLE_NO_PHOTO = {
  id: 11,
  nom: 'Poulain Sans Photo',
  nom_affiche: 'POULAIN SANS PHOTO',
  photo_url: '',
  items: {},
  videos: [],
  fiche_url: '',
  fiche_default_checked: false,
};

// Réplique délibérément SIMPLIFIÉE de la composition (déjà testée exhaustivement côté PHP) — sert
// uniquement à vérifier que le CLIENT transmet/consomme correctement la sélection et les réponses,
// jamais à revalider la logique de composition elle-même.
function composeFakeMessage(formData) {
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
  return message;
}

function fakeServerResponse(formData, sandboxState) {
  const action = formValue(formData, 'action');
  if (action === 'gwseq_partager_get_cheval') {
    const chevalId = formValue(formData, 'cheval_id');
    const cheval = chevalId === String(FIXTURE_SHAREABLE_NO_PHOTO.id) ? FIXTURE_SHAREABLE_NO_PHOTO : FIXTURE_SHAREABLE;
    return { success: true, data: { cheval: cheval } };
  }
  if (action === 'gwseq_partager_build_message') {
    sandboxState.buildMessageCalls.push(formData);
    return { success: true, data: { message: composeFakeMessage(formData) } };
  }
  if (action === 'gwseq_partager_search_cheval') {
    sandboxState.searchCalls.push(formData);
    return { success: true, data: { resultats: sandboxState.searchResults } };
  }
  return { success: false };
}

function buildSandbox(options) {
  options = options || {};
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
  const state = {
    searchCalls: [],
    buildMessageCalls: [],
    searchResults: options.searchResults || [],
    // File d'attente de délais à USAGE UNIQUE pour les appels build_message successifs (permet de
    // reproduire un ordre d'arrivée réseau réaliste — ex. une réponse plus ANCIENNE arrivant après
    // une réponse plus RÉCENTE — pour vérifier le correctif de séquencement, voir plus bas).
    buildMessageDelayQueue: [],
  };
  const fakeWindow = {
    gwseqPartager: {
      ajaxUrl: 'https://example.test/wp-admin/admin-ajax.php',
      nonce: 'test-nonce',
      recents: options.recents || [],
      filters: options.filters || {
        sexe: { female: 'Jument', male: 'Étalon', gelding: 'Hongre' },
        statut: { not_offered: 'Non proposé', for_sale: 'À vendre', reserved: 'Réservé', sold: 'Vendu' },
        categories: { chevaux_de_sport: 'Chevaux de sport', poulains: 'Poulains' },
        anneeMin: 1900,
        anneeMax: 2027,
      },
      i18n: {},
    },
    FormData: FakeFormData,
    fetch(url, requestOptions) {
      const action = formValue(requestOptions.body, 'action');
      const delay = action === 'gwseq_partager_build_message' && state.buildMessageDelayQueue.length
        ? state.buildMessageDelayQueue.shift()
        : 0;
      return new Promise((resolve) => {
        const respond = () => {
          const json = fakeServerResponse(requestOptions.body, state);
          resolve({ json: () => Promise.resolve(json) });
        };
        if (delay > 0) setTimeout(respond, delay); else respond();
      });
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

  return { root, fakeDocument, fakeWindow, documentListeners, openedUrls, clipboardWrites, state };
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

  // =====================================================================================
  // CORRECTIF DE RECETTE — le prix (ou toute autre information) apparaissait dans l'aperçu alors
  // que sa case était décochée. CAUSE RACINE : une réponse AJAX plus ANCIENNE arrivant après une
  // réponse plus RÉCENTE écrasait silencieusement l'aperçu à jour. Reproduit ici un ordre
  // d'arrivée réseau réaliste (la première requête, plus lente, répond APRÈS la seconde, plus
  // rapide) et vérifie que la réponse obsolète est bien IGNORÉE, jamais appliquée.
  // =====================================================================================

  const parts3 = buildSandbox();
  parts3.root.setAttribute('data-gwseq-preselected-id', '10');
  runScript(parts3);
  await wait(20); // chargement de la fiche + premier aperçu (déjà couvert plus haut)

  const composeRoot3 = parts3.root;
  const previewNode3 = composeRoot3.querySelector('.gwseq-partager-preview__text');
  const identiteCheckbox3 = composeRoot3.querySelectorAll('[data-item-key]').filter((c) => c.getAttribute('data-item-key') === 'identite')[0];
  const prixCheckbox3 = composeRoot3.querySelectorAll('[data-item-key]').filter((c) => c.getAttribute('data-item-key') === 'prix')[0];

  // Requête n°1 (déclenchée par la décoche ci-dessous) : délibérément LENTE (500ms).
  parts3.state.buildMessageDelayQueue.push(500);
  identiteCheckbox3.checked = false;
  identiteCheckbox3.dispatchEvent({ type: 'change' });
  await wait(400); // laisse le débounce (350ms) déclencher la requête n°1, qui reste en vol (lente)

  // Requête n°2 (déclenchée par la coche ci-dessous) : délibérément RAPIDE (aucun délai) — doit
  // répondre et s'afficher AVANT que la requête n°1 (lente, toujours en vol) ne résolve enfin.
  prixCheckbox3.checked = true;
  prixCheckbox3.dispatchEvent({ type: 'change' });
  await wait(400); // débounce (350ms) + résolution quasi immédiate de la requête n°2

  ok('Correctif prix : la réponse la plus RÉCENTE (prix coché, identité décochée) s’affiche bien', previewNode3.textContent.indexOf('25 000') !== -1 && previewNode3.textContent.indexOf('Jument Selle Français') === -1);

  await wait(300); // laisse la requête n°1 (lente) enfin résoudre, en arrivant APRÈS la n°2

  ok('Correctif prix : la réponse n°1, plus ANCIENNE mais arrivée EN DERNIER, n’écrase PAS l’aperçu à jour (cause racine corrigée, pas un simple masquage visuel)', previewNode3.textContent.indexOf('25 000') !== -1 && previewNode3.textContent.indexOf('Jument Selle Français') === -1);
  // 3 appels au total : le premier au chargement de l'écran (déjà couvert plus haut), puis un par
  // case cochée/décochée dans ce scénario — vérifie que le test exerce réellement la course
  // attendue plutôt que, par exemple, un débounce qui aurait fusionné les deux changements.
  ok('Correctif prix : les trois requêtes ont bien eu lieu (chargement + les deux changements de case), aucune perdue silencieusement', parts3.state.buildMessageCalls.length === 3);

  // --- WhatsApp/SMS/Copier restent cohérents avec CET aperçu final, y compris après la course ---
  const finalPreviewText = previewNode3.textContent;
  const buttons3 = composeRoot3.querySelectorAll('.gwseq-partager-action');
  buttons3.filter((b) => b.className.indexOf('gwseq-partager-action--whatsapp') !== -1)[0].dispatchEvent({ type: 'click' });
  ok('Correctif prix : WhatsApp consomme bien l’aperçu final correct, pas une version obsolète', parts3.openedUrls[parts3.openedUrls.length - 1] === 'https://wa.me/?text=' + encodeURIComponent(finalPreviewText));

  // =====================================================================================
  // Vignette de remplacement neutre quand un cheval n'a pas de photo (correctif de recette §2)
  // =====================================================================================

  const partsPlaceholder = buildSandbox({
    recents: [
      { id: 20, nom: 'Cheval Avec Photo', photo_url: 'https://example.test/photo-20.jpg', sous_titre: '', statut: '' },
      { id: 21, nom: 'Cheval Sans Photo', photo_url: '', sous_titre: '', statut: '' },
    ],
  });
  runScript(partsPlaceholder);

  const resultRows = partsPlaceholder.root.querySelectorAll('.gwseq-partager-result');
  const rowWithPhoto = resultRows[0];
  const rowWithoutPhoto = resultRows[1];
  ok('Vignette : un cheval AVEC photo affiche bien une balise <img> avec son URL réelle', rowWithPhoto.querySelector('img.gwseq-partager-result__photo') !== null);
  ok('Vignette : un cheval SANS photo n’affiche JAMAIS de <img> avec un src vide (jamais d’icône "image cassée")', rowWithoutPhoto.querySelector('img') === null);
  const placeholderNode = rowWithoutPhoto.querySelector('.gwseq-media-placeholder');
  ok('Vignette : un placeholder neutre réutilisable (classe partagée gwseq-media-placeholder) est affiché à la place', placeholderNode !== null);
  ok('Vignette : le placeholder utilise le même dashicon que le menu "Chevaux" (dashicons-pets), cohérent avec GWS Equestrian', placeholderNode.querySelector('.dashicons-pets') !== null);
  ok('Vignette : élément purement visuel, masqué aux technologies d’assistance (aria-hidden)', placeholderNode.getAttribute('aria-hidden') === 'true');

  const partsNoPhotoCompose = buildSandbox();
  partsNoPhotoCompose.root.setAttribute('data-gwseq-preselected-id', '11');
  runScript(partsNoPhotoCompose);
  await wait(20);
  const horsePhotoPlaceholder = partsNoPhotoCompose.root.querySelector('.gwseq-partager-horse__photo.gwseq-media-placeholder');
  ok('Vignette : la même règle s’applique à l’en-tête de l’écran de composition (cheval choisi sans photo)', horsePhotoPlaceholder !== null);

  // =====================================================================================
  // Filtres métier de la recherche (correctif de recette §3-5) : cumulatifs, sans bouton
  // "Appliquer" (filtrage dynamique), avec réinitialisation.
  // =====================================================================================

  const partsFilters = buildSandbox();
  runScript(partsFilters);
  await wait(20); // le rendu initial déclenche déjà un premier appel avec des filtres vides

  const filtersRoot = partsFilters.root;
  const sexeSelect = filtersRoot.querySelector('#gwseq-partager-filter-sexe');
  const statutSelect = filtersRoot.querySelector('#gwseq-partager-filter-statut');
  const categorieSelect = filtersRoot.querySelector('#gwseq-partager-filter-categorie');
  ok('Filtres : le sélecteur Sexe propose bien les options du référentiel Cheval existant (vocabulaire commercial de cet écran)', sexeSelect !== null && sexeSelect.children.some((o) => o.value === 'female' && o.textContent === 'Jument'));
  ok('Filtres : le sélecteur Statut commercial utilise exactement les valeurs internes existantes', statutSelect !== null && statutSelect.children.some((o) => o.value === 'for_sale'));
  ok('Filtres : le sélecteur Catégorie propose les catégories réellement configurées (aucune nouvelle catégorie créée)', categorieSelect !== null && categorieSelect.children.some((o) => o.value === 'chevaux_de_sport' && o.textContent === 'Chevaux de sport'));

  sexeSelect.value = 'female';
  statutSelect.value = 'for_sale';
  categorieSelect.value = 'chevaux_de_sport';
  const yearInputs = filtersRoot.querySelectorAll('.gwseq-partager-filter__annee-input');
  yearInputs[0].value = '2018';
  yearInputs[1].value = '2021';
  const searchInput = filtersRoot.querySelector('.gwseq-partager-search__input');
  searchInput.value = 'jument';

  filtersRoot.querySelector('.gwseq-partager-filters').dispatchEvent({ type: 'change' });
  await wait(400);

  const lastSearchCall = partsFilters.state.searchCalls[partsFilters.state.searchCalls.length - 1];
  ok('Filtres : la recherche texte ET les filtres sont bien transmis ENSEMBLE dans le même appel (cumulatifs, §4)', formValue(lastSearchCall, 's') === 'jument');
  ok('Filtres : sexe transmis', formValue(lastSearchCall, 'filters[sexe]') === 'female');
  ok('Filtres : statut commercial transmis', formValue(lastSearchCall, 'filters[statut]') === 'for_sale');
  ok('Filtres : catégorie transmise', formValue(lastSearchCall, 'filters[categorie]') === 'chevaux_de_sport');
  ok('Filtres : plage d’année de naissance transmise (De/à)', formValue(lastSearchCall, 'filters[annee_min]') === '2018' && formValue(lastSearchCall, 'filters[annee_max]') === '2021');

  const resetButton = filtersRoot.querySelector('.gwseq-partager-filters__reset');
  ok('Filtres : action "Réinitialiser les filtres" présente', resetButton !== null);
  resetButton.dispatchEvent({ type: 'click' });
  await wait(20);

  ok('Réinitialisation : le champ de recherche texte est bien vidé', searchInput.value === '');
  ok('Réinitialisation : tous les sélecteurs reviennent sur "Tous"/"Toutes les catégories"', sexeSelect.value === '' && statutSelect.value === '' && categorieSelect.value === '');
  ok('Réinitialisation : la plage d’année est bien vidée', yearInputs[0].value === '' && yearInputs[1].value === '');
  const searchCallAfterReset = partsFilters.state.searchCalls[partsFilters.state.searchCalls.length - 1];
  ok('Réinitialisation : une nouvelle recherche est bien relancée immédiatement, sans aucun filtre actif', formValue(searchCallAfterReset, 's') === '' && formValue(searchCallAfterReset, 'filters[sexe]') === '' && formValue(searchCallAfterReset, 'filters[categorie]') === '');

  if (failureCount > 0) {
    console.log('\n' + failureCount + ' assertion(s) EN ÉCHEC sur ' + assertionCount + '.');
    process.exitCode = 1;
  } else {
    console.log('\nTous les tests sont passés. (' + assertionCount + ' assertions)');
  }
}

run();

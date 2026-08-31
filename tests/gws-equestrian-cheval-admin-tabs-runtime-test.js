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
  // Reproduit la sémantique RÉELLE de re-parentage du DOM : appendChild()/insertBefore() sur un
  // nœud DÉJÀ présent ailleurs dans l'arbre le RETIRENT D'ABORD de son parent actuel (jamais un
  // clone, jamais deux parents à la fois) — indispensable pour modéliser fidèlement le
  // déplacement réel de #postimagediv vers l'onglet Médias (voir cheval-tabs-admin.js).
  _detachFromCurrentParent(node) {
    if (!node.parentNode) return;
    const idx = node.parentNode.children.indexOf(node);
    if (idx !== -1) node.parentNode.children.splice(idx, 1);
  }
  get nextSibling() {
    if (!this.parentNode) return null;
    const idx = this.parentNode.children.indexOf(this);
    return (idx === -1 || idx === this.parentNode.children.length - 1) ? null : this.parentNode.children[idx + 1];
  }
  appendChild(child) {
    this._detachFromCurrentParent(child);
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
    this._detachFromCurrentParent(newNode);
    const indexAfterDetach = this.children.indexOf(refNode);
    newNode.parentNode = this;
    this.children.splice(indexAfterDetach, 0, newNode);
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

/**
 * Reproduit le markup RÉEL produit par WordPress pour la boîte native "Image à la une"
 * (post_thumbnail_meta_box() / _wp_post_thumbnail_html()) dans ses deux états — plutôt qu'une
 * boîte vide générique — afin de vérifier littéralement que le CONTENU RÉEL (lien "Définir"/
 * vignette + lien "Supprimer", nonce) survit au déplacement, dans les deux cas demandés.
 */
function makePostimagediv(hasImage) {
  const box = new FakeElement('div');
  box.id = 'postimagediv';
  box.className = 'postbox';

  const header = new FakeElement('div');
  header.className = 'postbox-header';
  const hndle = new FakeElement('h2');
  hndle.className = 'hndle';
  hndle.textContent = 'Image à la une';
  header.appendChild(hndle);
  box.appendChild(header);

  const inside = new FakeElement('div');
  inside.className = 'inside';

  const nonce = new FakeElement('input');
  nonce.setAttribute('type', 'hidden');
  nonce.setAttribute('name', '_wpnonce_set_post_thumbnail');
  nonce.setAttribute('value', 'stub-nonce');
  inside.appendChild(nonce);

  if (hasImage) {
    const thumbP = new FakeElement('p');
    thumbP.className = 'hide-if-no-js';
    const img = new FakeElement('img');
    img.className = 'attachment-post-thumbnail';
    img.setAttribute('src', 'https://example.test/photo.jpg');
    thumbP.appendChild(img);
    inside.appendChild(thumbP);

    const removeP = new FakeElement('p');
    removeP.className = 'hide-if-no-js';
    const removeLink = new FakeElement('a');
    removeLink.id = 'remove-post-thumbnail';
    removeLink.setAttribute('href', '#');
    removeLink.textContent = 'Supprimer la photo principale';
    removeP.appendChild(removeLink);
    inside.appendChild(removeP);
  } else {
    const setP = new FakeElement('p');
    setP.className = 'hide-if-no-js';
    const setLink = new FakeElement('a');
    setLink.id = 'set-post-thumbnail';
    setLink.setAttribute('href', '#');
    setLink.textContent = 'Définir la photo principale';
    setP.appendChild(setLink);
    inside.appendChild(setP);
  }

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

function buildRealisticChevalEditScreen(options) {
  options = options || {};
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
  const postimagediv = options.postimagediv || makeBox('postimagediv');
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
  // Reproduit l'emplacement réservé par cheval-media.php pour accueillir la véritable boîte
  // native "Image à la une" (voir includes/cheval-media.php) — vide au départ, exactement comme
  // dans le vrai rendu PHP tant que le script ne l'a pas remplie.
  const photoPrincipaleSlot = new FakeElement('div');
  photoPrincipaleSlot.id = 'gwseq-cheval-media-photo-principale-slot';
  media.children[1].appendChild(photoPrincipaleSlot); // media.children[1] = son .inside (voir makeBox)
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
      production, pedigreePreview, postimagediv, globalIdDev, ordre, photoPrincipaleSlot,
    },
  };
}

const MAIN_TABS_CONFIG = [
  { id: 'identite', label: 'Identité', boxes: ['gwseq-cheval-identite'] },
  { id: 'commercial', label: 'Commercial', boxes: ['gwseq-cheval-commercialisation'] },
  { id: 'pedigree', label: 'Pedigree', boxes: ['gwseq-cheval-pedigree', 'gwseq-cheval-production', 'gwseq-cheval-pedigree-preview'] },
  { id: 'indices', label: 'Indices', boxes: ['gwseq-cheval-indices'] },
  // 'postimagediv' n'apparaît PLUS ici (correctif intégration Photo principale) : il n'est plus
  // piloté par le mécanisme générique de visibilité par onglet, mais réellement déplacé dans le
  // DOM par le script jusqu'à l'intérieur même de "gwseq-cheval-media" (voir photoPrincipaleSlot).
  { id: 'medias', label: 'Médias', boxes: ['gwseq-cheval-media'] },
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

  // --- Intégration réelle de la Photo principale dans l'onglet Médias : la vraie boîte native
  // #postimagediv doit être RÉELLEMENT déplacée (une seule fois, dès l'initialisation, jamais
  // seulement masquée/affichée en place dans sa colonne native) dans l'emplacement dédié à
  // l'intérieur de la boîte Médias — et ne plus jamais apparaître dans la colonne latérale. ---
  ok(
    'Intégration Photo principale : la vraie boîte native #postimagediv a été réellement déplacée dans l’emplacement dédié à l’intérieur de la boîte Médias',
    boxes.photoPrincipaleSlot.children.indexOf(boxes.postimagediv) !== -1
  );
  ok(
    'Intégration Photo principale : elle a bien quitté sa colonne latérale native — plus jamais affichée à deux endroits à la fois',
    screen.sideSortables.children.indexOf(boxes.postimagediv) === -1
  );
  ok(
    'Intégration Photo principale : c’est le MÊME nœud DOM (identité d’objet), jamais un clone — aucune donnée dupliquée, aucun second attachment ID',
    boxes.postimagediv.tagName === 'div' && boxes.photoPrincipaleSlot.children[0] === boxes.postimagediv
  );

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

  // --- Photo principale intégrée à l'onglet Médias : #postimagediv, désormais DESCENDANT de la
  // boîte Médias, hérite automatiquement de sa visibilité — aucune logique de visibilité séparée
  // n'est nécessaire, ni testée ici, pour cette boîte précise. ---
  ok('L\'onglet "Médias" (index 4) référence bien la boîte Galerie/Vidéos via aria-controls (qui contient désormais la Photo principale)', tabButtons[4].getAttribute('aria-controls') === 'gwseq-cheval-media');
  ok('Au chargement (onglet Identité actif), la Photo principale — devenue descendante de Médias — est masquée avec elle, EN HÉRITANT de la visibilité de sa boîte hôte', boxes.postimagediv.offsetParent === null);
  tabButtons[4].click();
  ok('Après clic sur "Médias", la Photo principale devient RÉELLEMENT visible EN HÉRITANT de la visibilité de la boîte Médias qui la contient désormais', boxes.postimagediv.offsetParent !== null);
  ok('Après clic sur "Médias", la boîte Galerie/Vidéos (colonne principale) est visible, dans la même zone logique que la Photo principale', boxes.media.offsetParent !== null);
  ok('Après clic sur "Médias", la boîte Identité (onglet précédent) est de nouveau masquée', boxes.identite.style.display === 'none');
  tabButtons[0].click();
  ok('En revenant sur "Identité", la Photo principale (désormais à l’intérieur de Médias) est de nouveau masquée avec sa boîte hôte', boxes.postimagediv.offsetParent === null);
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
  ok(
    'Filet de sécurité n°2 : la Photo principale, réellement déplacée dans l’onglet Médias avant la désactivation, est restaurée à sa position native exacte (colonne latérale)',
    screen.sideSortables.children.indexOf(screen.boxes.postimagediv) !== -1 && screen.boxes.photoPrincipaleSlot.children.indexOf(screen.boxes.postimagediv) === -1
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

/* =============================================================================================
 * SCÉNARIO 4 — CONTENU RÉEL DE LA PHOTO PRINCIPALE APRÈS DÉPLACEMENT (recette : le titre
 * apparaissait dans l'onglet Médias, mais aucun contrôle ni aucune image dessous). Utilise le
 * markup RÉEL de WordPress pour #postimagediv (nonce + lien "Définir"/vignette + lien
 * "Supprimer"), dans les DEUX états demandés, et vérifie que ce contenu survit intact au
 * déplacement DOM et reste réellement visible une fois l'onglet Médias actif — pas seulement
 * supposé, littéralement vérifié champ par champ.
 * ========================================================================================== */
function runPhotoPrincipaleContentScenario(hasImage) {
  const label = hasImage ? 'avec photo principale déjà définie' : 'sans photo principale définie';
  const screen = buildRealisticChevalEditScreen({ postimagediv: makePostimagediv(hasImage) });
  applyTabMarkerClasses(screen.root, MAIN_TABS_CONFIG);

  const { thrown } = runScript(screen.root, MAIN_TABS_CONFIG);
  ok('Scénario contenu Photo principale (' + label + ') : exécution réelle sans exception', thrown === null);
  if (thrown) {
    console.log('     -> exception : ' + thrown.name + ': ' + thrown.message);
    return;
  }

  const postimagediv = screen.boxes.postimagediv;
  ok(
    'Contenu Photo principale (' + label + ') : la boîte déplacée est bien devenue enfant de l’emplacement dédié dans la boîte Médias',
    screen.boxes.photoPrincipaleSlot.children.indexOf(postimagediv) !== -1
  );

  const inside = postimagediv.children.filter(function (c) { return c.className === 'inside'; })[0];
  ok('Contenu Photo principale (' + label + ') : la boîte déplacée conserve bien son .inside (jamais vidé par le déplacement)', !!inside);

  const nonceSurvived = !!inside && inside.children.some(function (c) { return c.getAttribute('name') === '_wpnonce_set_post_thumbnail'; });
  ok('Contenu Photo principale (' + label + ') : le champ nonce natif de .inside est bien préservé', nonceSurvived);

  if (hasImage) {
    const imgSurvived = !!inside && inside.children.some(function (p) {
      return p.children.some(function (c) { return c.tagName === 'img' && c.className === 'attachment-post-thumbnail'; });
    });
    ok('Contenu Photo principale (' + label + ') : la VIGNETTE de la photo déjà définie est bien préservée après déplacement', imgSurvived);
    const removeLinkSurvived = !!inside && inside.children.some(function (p) {
      return p.children.some(function (c) { return c.id === 'remove-post-thumbnail'; });
    });
    ok('Contenu Photo principale (' + label + ') : le contrôle natif "Supprimer la photo principale" est bien préservé après déplacement', removeLinkSurvived);
  } else {
    const setLinkSurvived = !!inside && inside.children.some(function (p) {
      return p.children.some(function (c) { return c.id === 'set-post-thumbnail'; });
    });
    ok('Contenu Photo principale (' + label + ') : le contrôle natif "Définir la photo principale" est bien préservé après déplacement', setLinkSurvived);
  }

  // Activer l'onglet Médias et vérifier que le contenu réel devient RÉELLEMENT visible et
  // utilisable (offsetParent non nul sur .inside elle-même, pas seulement sur le conteneur).
  const wrapper = screen.normalSortables.children[0];
  const tablist = wrapper && wrapper.className === 'gwseq-cheval-tabs' ? wrapper.children[0] : null;
  const tabButtons = tablist ? tablist.children : [];
  if (tabButtons.length === 6) tabButtons[4].click();

  ok(
    'Contenu Photo principale (' + label + ') : une fois l’onglet Médias actif, .inside (le contenu réel) est RÉELLEMENT visible, pas seulement le conteneur',
    !!inside && inside.offsetParent !== null
  );
}

runMainScenario();
runFallbackScenario();
runMismatchScenario();
runPhotoPrincipaleContentScenario(false);
runPhotoPrincipaleContentScenario(true);

if (failureCount > 0) {
  console.log('\n' + failureCount + ' assertion(s) EN ÉCHEC sur ' + assertionCount + '.');
  process.exitCode = 1;
} else {
  console.log('\nTous les tests sont passés. (' + assertionCount + ' assertions)');
}

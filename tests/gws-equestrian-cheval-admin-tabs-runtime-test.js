/**
 * Test d'EXÉCUTION RÉELLE de assets/cheval-tabs-admin.js (correctif régression bloquante,
 * ajustement UX post-recette de l'Étape 6, 0.12.1).
 *
 * Pourquoi ce fichier existe : la suite PHP (`gws-equestrian-cheval-admin-tabs-test.php`) ne fait
 * que scanner le TEXTE SOURCE du script (présence de motifs, absence de patterns interdits) — elle
 * ne l'exécute jamais contre un vrai DOM. C'est exactement ce qui a laissé passer la régression
 * runtime bloquante signalée en recette : `postbody.insertBefore(wrapper, normalSortables)`
 * levait systématiquement une DOMException dans un vrai navigateur (#post-body-content et
 * #normal-sortables ne sont PAS dans une relation parent/enfant sur l'écran classique de
 * WordPress — ce sont deux enfants distincts de #post-body), alors que 73 assertions basées sur du
 * texte restaient vertes.
 *
 * Ce script construit une reproduction fidèle (mais minimale, sans dépendance npm) du DOM réel
 * produit par wp-admin/edit-form-advanced.php pour l'écran d'édition d'une fiche Cheval — colonne
 * latérale (#postbox-container-1 > #side-sortables, avec le vrai bouton #publish) SÉPARÉE de la
 * colonne principale (#postbox-container-2 > #normal-sortables), toutes deux enfants de #post-body
 * au même niveau que #post-body-content — puis exécute réellement le fichier JS du module dans ce
 * DOM simulé via le module `vm` de Node (aucune dépendance externe, seul Node lui-même est requis).
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
 * DOM minimal fidèle (pas de jsdom, aucune dépendance npm ajoutée au projet) : juste assez pour
 * exécuter réellement cheval-tabs-admin.js et vérifier son comportement, notamment le respect
 * strict de la sémantique DOM d'insertBefore (lève une erreur si le nœud de référence n'est pas un
 * enfant du nœud appelant — exactement comme un vrai navigateur), ce qui aurait immédiatement
 * détecté la régression bloquante.
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
    this.tagName = tagName;
    this.id = '';
    this.className = '';
    this.children = [];
    this.parentNode = null;
    this._attributes = {};
    this.style = {};
    this._listeners = {};
    this.textContent = '';
  }
  get classList() { return new FakeClassList(this); }
  setAttribute(name, value) { this._attributes[name] = String(value); }
  getAttribute(name) {
    return Object.prototype.hasOwnProperty.call(this._attributes, name) ? this._attributes[name] : null;
  }
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
      // référence DOIT être un enfant direct du nœud appelant, sinon exception — c'est cette
      // exception, jamais levée par les tests basés sur du texte, qui a masqué la régression.
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
  addEventListener(type, fn) {
    (this._listeners[type] = this._listeners[type] || []).push(fn);
  }
  dispatchEvent(evt) {
    ((this._listeners[evt.type] || []).slice()).forEach((fn) => fn(evt));
  }
  click() { this.dispatchEvent({ type: 'click' }); }
  focus() { this._focused = true; }
}

function makeBox(id) {
  const box = new FakeElement('div');
  box.id = id;
  box.className = 'postbox';
  return box;
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
  const identite = makeBox('gwseq-cheval-identite');
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

function walk(node, id) {
  if (!node) return null;
  if (node.id === id) return node;
  for (let i = 0; i < node.children.length; i++) {
    const found = walk(node.children[i], id);
    if (found) return found;
  }
  return null;
}

function runScenario() {
  const screen = buildRealisticChevalEditScreen();

  const fakeSessionStorageStore = {};
  const fakeSessionStorage = {
    getItem(key) { return Object.prototype.hasOwnProperty.call(fakeSessionStorageStore, key) ? fakeSessionStorageStore[key] : null; },
    setItem(key, value) { fakeSessionStorageStore[key] = String(value); },
  };

  const domReadyHandlers = [];
  const fakeDocument = {
    getElementById(id) { return walk(screen.root, id); },
    createElement(tag) { return new FakeElement(tag); },
    addEventListener(type, fn) {
      if (type === 'DOMContentLoaded') domReadyHandlers.push(fn);
    },
  };

  // Configuration identique, dans sa forme, à gwseq_cheval_admin_tabs_config() (voir
  // includes/cheval-admin-tabs.php) — en particulier le regroupement Pedigree/Production/aperçu
  // sous un même onglet, précisément le cas dont dépendait le changement de contexte
  // add_meta_box() désormais annulé (voir cheval-pedigree.php).
  const tabsConfig = [
    { id: 'identite', label: 'Identité', boxes: ['gwseq-cheval-identite'] },
    { id: 'commercial', label: 'Commercial', boxes: ['gwseq-cheval-commercialisation'] },
    { id: 'pedigree', label: 'Pedigree', boxes: ['gwseq-cheval-pedigree', 'gwseq-cheval-production', 'gwseq-cheval-pedigree-preview'] },
    { id: 'indices', label: 'Indices', boxes: ['gwseq-cheval-indices'] },
    { id: 'medias', label: 'Médias', boxes: ['gwseq-cheval-media'] },
    { id: 'presentation', label: 'Présentation', boxes: ['gwseq-cheval-presentation', 'gwseq-cheval-infos-complementaires'] },
  ];

  const fakeWindow = {
    gwseqChevalTabs: { tabs: tabsConfig, saveLabelFallback: 'Enregistrer', tablistLabel: 'Sections de la fiche cheval' },
    sessionStorage: fakeSessionStorage,
  };

  const sandbox = { document: fakeDocument, window: fakeWindow };
  vm.createContext(sandbox);

  const scriptPath = path.join(
    __dirname, '..', 'wp-content', 'plugins', 'gws-core', 'modules', 'gws-equestrian', 'assets', 'cheval-tabs-admin.js'
  );
  const source = fs.readFileSync(scriptPath, 'utf8');
  vm.runInContext(source, sandbox, { filename: 'cheval-tabs-admin.js' });

  ok('Le script enregistre bien un gestionnaire DOMContentLoaded', domReadyHandlers.length === 1);

  let thrown = null;
  try {
    domReadyHandlers[0]();
  } catch (e) {
    thrown = e;
  }

  ok(
    "Exécution réelle du script contre un DOM fidèle à l'écran classique WordPress : aucune exception levée " +
    '(régression bloquante corrigée — auparavant DOMException sur insertBefore)',
    thrown === null
  );
  if (thrown) {
    console.log('     -> exception : ' + thrown.name + ': ' + thrown.message);
  }

  return { screen, fakeSessionStorageStore, thrown };
}

function main() {
  const { screen, fakeSessionStorageStore, thrown } = runScenario();
  const boxes = screen.boxes;

  const wrapper = screen.normalSortables.children[0];
  ok('La barre d\'onglets est bien insérée dans le DOM, en premier enfant de #normal-sortables', !!wrapper && wrapper.className === 'gwseq-cheval-tabs');

  const tablist = wrapper ? wrapper.children[0] : null;
  ok("La barre d'onglets contient un conteneur avec le rôle ARIA tablist", !!tablist && tablist.getAttribute('role') === 'tablist');

  const tabButtons = tablist ? tablist.children : [];
  ok('Les 6 onglets attendus sont bien rendus (Identité, Commercial, Pedigree, Indices, Médias, Présentation)', tabButtons.length === 6);
  ok('Chaque bouton d\'onglet porte le rôle ARIA "tab"', tabButtons.length > 0 && tabButtons.every((b) => b.getAttribute('role') === 'tab'));

  if (thrown || tabButtons.length !== 6) {
    console.log('\n' + (failureCount) + ' assertion(s) EN ÉCHEC sur ' + assertionCount + ' — arrêt anticipé, le script n\'a pas construit un état exploitable.');
    process.exitCode = 1;
    return;
  }

  ok(
    "L'onglet Pedigree référence bien ses 3 boîtes (Pedigree, Production, aperçu) via aria-controls, même si Production/aperçu vivent physiquement dans la colonne latérale",
    tabButtons[2].getAttribute('aria-controls') === 'gwseq-cheval-pedigree gwseq-cheval-production gwseq-cheval-pedigree-preview'
  );

  // --- État initial : premier onglet (Identité) actif ---
  ok('Au chargement, la boîte Identité est visible (display vide)', boxes.identite.style.display === '');
  ok('Au chargement, la boîte Commercialisation est masquée (un seul onglet actif à la fois)', boxes.commercialisation.style.display === 'none');
  ok("Au chargement, la boîte Identité n'a jamais été retirée du DOM (toujours enfant de #normal-sortables)", screen.normalSortables.children.indexOf(boxes.identite) !== -1);
  ok("La boîte Image à la une (colonne latérale, hors onglets) n'est jamais touchée par le script", boxes.postimagediv.style.display === undefined);
  ok('La boîte "Ordre d\'affichage" (colonne latérale, hors onglets) n\'est jamais touchée par le script', boxes.ordre.style.display === undefined);

  // --- Clic sur l'onglet Pedigree : regroupement Pedigree + Production + aperçu, même si ces
  // deux dernières vivent dans la colonne latérale (#side-sortables) plutôt que la colonne
  // principale --- c'est exactement le point que le changement de contexte annulé cherchait à
  // obtenir en déplaçant les boîtes dans le DOM ; ce test prouve que ce déplacement n'était pas
  // nécessaire pour le regroupement fonctionnel.
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

  if (failureCount > 0) {
    console.log('\n' + failureCount + ' assertion(s) EN ÉCHEC sur ' + assertionCount + '.');
    process.exitCode = 1;
  } else {
    console.log('\nTous les tests sont passés. (' + assertionCount + ' assertions)');
  }
}

main();

/**
 * Écran d'édition d'une fiche cheval — affichage conditionnel de plusieurs groupes de champs
 * indépendants : précision "Robe : Autre", le bloc de prix correspondant au mode de prix choisi
 * (Prix fixe / Fourchette / Sur demande), et — depuis l'Étape 5 — la source de chaque parent
 * (Père/Mère : cheval GWS ou ascendant externe). La précision "Race/Stud-book/Appellation : Autre"
 * est désormais gérée par le composant partagé assets/race-referentiel-autocomplete.js (référentiel
 * Race/Stud-book/Appellation — voir includes/race-referentiel.php), pas par ce fichier. Même
 * technique que assets/prestation-admin.js (JavaScript natif, aucune dépendance, la sauvegarde
 * réelle reste entièrement gérée côté serveur — voir includes/cheval-fields.php et
 * includes/cheval-pedigree.php) : ce script ne fait qu'afficher/masquer des blocs déjà présents
 * dans le DOM. Léger, scopé à cet écran, sans erreur si JavaScript est indisponible : les deux
 * groupes de champs (GWS et externe) restent alors simplement tous deux visibles et le serveur
 * reste seul autoritaire sur ce qui est réellement enregistré (voir la sanitation par mode dans
 * includes/cheval-pedigree.php). Gère aussi la mise à jour en direct des intitulés contextuels du
 * pedigree et le bouton "Supprimer cet ascendant" (correctifs post-recette) : dans les deux cas,
 * uniquement de l'affichage ou une remise à vide de champs déjà présents dans le DOM — jamais un
 * appel serveur, jamais une transformation de la valeur réellement envoyée au serveur.
 */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    var robeSelect = document.getElementById('gwseq-cheval-robe');
    var prixModeSelect = document.getElementById('gwseq-cheval-prix-mode');

    function setVisible(selector, visible) {
      var field = document.querySelector(selector);
      if (field) field.style.display = visible ? '' : 'none';
    }

    function applyRobe() {
      if (!robeSelect) return;
      setVisible('[data-gwseq-cheval-fields="robe-autre"]', robeSelect.value === 'autre');
    }

    function applyPrixMode() {
      if (!prixModeSelect) return;
      var mode = prixModeSelect.value;
      setVisible('[data-gwseq-cheval-fields="prix-fixed"]', mode === 'fixed');
      setVisible('[data-gwseq-cheval-fields="prix-range"]', mode === 'range');
      setVisible('[data-gwseq-cheval-fields="prix-on-request"]', mode === 'on_request');
    }

    if (robeSelect) {
      robeSelect.addEventListener('change', applyRobe);
      applyRobe();
    }
    if (prixModeSelect) {
      prixModeSelect.addEventListener('change', applyPrixMode);
      applyPrixMode();
    }

    // Source du Père / de la Mère (Étape 5) : deux groupes de radios indépendants, noms de champs
    // distincts (_gwseq_pere_mode / _gwseq_mere_mode) — chacun bascule son propre bloc GWS/externe.
    [
      { role: 'father', name: '_gwseq_pere_mode' },
      { role: 'mother', name: '_gwseq_mere_mode' }
    ].forEach(function (parent) {
      var radios = document.getElementsByName(parent.name);
      if (!radios.length) return;

      function applyParentSource() {
        var selected = '';
        radios.forEach(function (radio) {
          if (radio.checked) selected = radio.value;
        });
        setVisible('[data-gwseq-parent-fields="' + parent.role + '-gws"]', selected === 'gws');
        setVisible('[data-gwseq-parent-fields="' + parent.role + '-external"]', selected === 'external');
      }

      radios.forEach(function (radio) {
        radio.addEventListener('change', applyParentSource);
      });
      applyParentSource();
    });

    // Intégrité du pedigree (correctifs complémentaires post-recette, 0.9.0 puis 0.10.0) : un même
    // cheval GWS ne doit jamais pouvoir être choisi à la fois comme père et comme mère (0.9.0), et
    // un candidat au sexe ou à l'année de naissance incompatible avec le rôle ne doit jamais être
    // sélectionnable (0.10.0). UX uniquement — la garantie réelle reste la validation serveur dans
    // gwseq_set_horse_parent() (voir includes/cheval-pedigree.php), qui refuse l'enregistrement
    // quel que soit l'état de ce script. Ce bloc se contente de désactiver, dans CHAQUE sélecteur,
    // l'option correspondant au cheval déjà choisi dans l'AUTRE sélecteur — jamais de changement
    // automatique d'une valeur déjà sélectionnée : désactiver une <option> ne désélectionne rien,
    // elle empêche seulement un choix futur qui créerait l'incohérence. Une option porteuse de
    // l'attribut data-gwseq-locked-disabled (sexe/année incompatible, verrouillé côté rendu
    // serveur — voir gwseq_render_cheval_parent_fields()) n'est JAMAIS réactivée par ce script : ce
    // sont des propriétés fixes du candidat, indépendantes de la sélection courante de l'autre
    // rôle, contrairement au conflit d'autre-parent qui peut légitimement disparaître.
    var gwsParentSelects = document.querySelectorAll('.gwseq-parent-gws-select');
    if (gwsParentSelects.length === 2) {
      var fatherGwsSelect = null;
      var motherGwsSelect = null;
      gwsParentSelects.forEach(function (select) {
        if (select.getAttribute('data-gwseq-parent-role') === 'father') fatherGwsSelect = select;
        if (select.getAttribute('data-gwseq-parent-role') === 'mother') motherGwsSelect = select;
      });

      function excludeSelectedOption(sourceSelect, targetSelect) {
        var sourceValue = sourceSelect.value;
        Array.prototype.forEach.call(targetSelect.options, function (option) {
          if (option.hasAttribute('data-gwseq-locked-disabled')) return;
          if (option.value === '' || option.value === '0') {
            option.disabled = false;
            return;
          }
          option.disabled = (sourceValue !== '' && sourceValue !== '0' && option.value === sourceValue);
        });
      }

      function syncGwsParentSelects() {
        if (!fatherGwsSelect || !motherGwsSelect) return;
        excludeSelectedOption(fatherGwsSelect, motherGwsSelect);
        excludeSelectedOption(motherGwsSelect, fatherGwsSelect);
      }

      if (fatherGwsSelect) fatherGwsSelect.addEventListener('change', syncGwsParentSelects);
      if (motherGwsSelect) motherGwsSelect.addEventListener('change', syncGwsParentSelects);
      syncGwsParentSelects();
    }

    // Race/Stud-book/Appellation (cheval GWS et ascendant externe) : composant de recherche partagé,
    // entièrement autonome — voir assets/race-referentiel-autocomplete.js et
    // includes/race-referentiel.php. Ce fichier n'a plus rien à faire pour son affichage (plus de
    // <select> ni de bascule "Autre" à gérer ici).

    // Mise à jour EN DIRECT des intitulés contextuels du pedigree (correctif post-recette : un
    // premier essai sans JavaScript s'est révélé insuffisant — "Père de cet ascendant" restait
    // affiché tant que la fiche n'était pas enregistrée, malgré un nom déjà saisi). Ce bloc ne lit
    // ET n'écrit JAMAIS la valeur d'un champ Nom : il ne fait que recalculer le texte affiché
    // ailleurs (le résumé de la divulgation progressive, les libellés Père/Mère du niveau suivant)
    // à partir de sa valeur courante, jamais l'inverse. Les libellés traduits proviennent des
    // attributs data-* du conteneur .gwseq-pedigree-i18n (voir includes/cheval-pedigree.php),
    // jamais codés en dur ici, pour ne jamais désynchroniser cet affichage d'une traduction du
    // plugin. La transformation "majuscules, sans accents" reproduit côté navigateur celle du
    // serveur (gwseq_format_horse_name_display()) à titre de PRÉVISUALISATION uniquement : le
    // rendu réellement autoritaire reste celui produit par le serveur à l'enregistrement suivant.
    function gwseqPedigreeDisplayName(rawName) {
      var trimmed = (rawName || '').trim();
      if (trimmed === '') return '';
      var withoutAccents = trimmed.normalize ? trimmed.normalize('NFD').replace(/[\u0300-\u036f]/g, '') : trimmed;
      return withoutAccents.toUpperCase();
    }

    document.addEventListener('input', function (e) {
      if (!e.target.classList || !e.target.classList.contains('gwseq-external-name-input')) return;

      var i18nContainer = document.querySelector('.gwseq-pedigree-i18n');
      if (!i18nContainer) return;
      var fatherPrefix = i18nContainer.getAttribute('data-father-prefix') || '';
      var motherPrefix = i18nContainer.getAttribute('data-mother-prefix') || '';
      var summaryPrefix = i18nContainer.getAttribute('data-summary-prefix') || '';
      var fallbackName = i18nContainer.getAttribute('data-fallback-name') || '';

      var nodeWrap = e.target.closest('.gwseq-ancestor-node');
      if (!nodeWrap) return;

      var displayName = gwseqPedigreeDisplayName(e.target.value) || fallbackName;

      var summary = nodeWrap.querySelector(':scope > details > summary');
      if (summary) summary.textContent = summaryPrefix + displayName;

      var childNodes = nodeWrap.querySelectorAll(':scope > details > .gwseq-ancestor-node');
      if (childNodes[0]) {
        var fatherLabel = childNodes[0].querySelector(':scope > p > strong');
        if (fatherLabel) fatherLabel.textContent = fatherPrefix + displayName;
      }
      if (childNodes[1]) {
        var motherLabel = childNodes[1].querySelector(':scope > p > strong');
        if (motherLabel) motherLabel.textContent = motherPrefix + displayName;
      }
    });

    // Suppression explicite d'un ascendant externe (correctif complémentaire post-recette) : le
    // bouton "Supprimer cet ascendant" (classe gwseq-delete-ancestor, voir
    // includes/cheval-pedigree.php) agit UNIQUEMENT sur le nœud le plus proche et sa sous-branche
    // — jamais une autre branche du pedigree, jamais le cheval principal (aucun bouton de ce type
    // n'existe en dehors d'un bloc .gwseq-ancestor-node). Il ne fait que remettre les champs à
    // vide ; la suppression réelle en base est l'effet du nettoyage automatique appliqué par
    // gwseq_sanitize_external_ancestor_tree() au prochain enregistrement (voir
    // gwseq_is_external_ancestor_node_empty()) — aucun appel serveur, aucune suppression DOM,
    // aucun système de corbeille. Une confirmation n'est demandée que si des origines enfants sont
    // déjà renseignées (recherche récursive de tout champ non vide sous ce nœud) ; le texte de
    // confirmation provient de l'attribut data-delete-confirm de .gwseq-pedigree-i18n, jamais codé
    // en dur ici.
    document.addEventListener('click', function (e) {
      var button = e.target.closest ? e.target.closest('.gwseq-delete-ancestor') : null;
      if (!button) return;
      e.preventDefault();

      var nodeWrap = button.closest('.gwseq-ancestor-node');
      if (!nodeWrap) return;

      var childNodes = nodeWrap.querySelectorAll(':scope > details > .gwseq-ancestor-node');
      var hasChildData = false;
      childNodes.forEach(function (child) {
        child.querySelectorAll('input[type="text"], select').forEach(function (field) {
          if (field.value.trim() !== '') hasChildData = true;
        });
      });

      if (hasChildData) {
        var i18nContainer = document.querySelector('.gwseq-pedigree-i18n');
        var confirmText = i18nContainer ? (i18nContainer.getAttribute('data-delete-confirm') || '') : '';
        if (confirmText && !window.confirm(confirmText)) return;
      }

      nodeWrap.querySelectorAll('input[type="text"], input[type="number"]').forEach(function (input) {
        input.value = '';
      });
      nodeWrap.querySelectorAll('.gwseq-race-field').forEach(function (field) {
        var codeInput = field.querySelector('.gwseq-race-field__code');
        if (codeInput) codeInput.value = '';
        var searchInput = field.querySelector('.gwseq-race-field__search');
        if (searchInput) searchInput.value = '';
        var autreWrap = field.querySelector('.gwseq-race-field__autre-wrap');
        if (autreWrap) autreWrap.style.display = 'none';
        // Filet de sécurité obligatoire (§6, race-referentiel-autocomplete.js) : le <select> de
        // secours peut être le contrôle réellement actif (composant de recherche jamais activé,
        // ex. JavaScript en échec sur cet ascendant précis) — le réinitialiser aussi, sinon sa
        // valeur précédente resterait soumise malgré le clic sur "Supprimer cet ascendant".
        var fallbackWrap = field.parentNode ? field.parentNode.querySelector('.gwseq-race-field__fallback-wrap') : null;
        var fallbackSelect = fallbackWrap ? fallbackWrap.querySelector('.gwseq-race-field__fallback') : null;
        if (fallbackSelect) fallbackSelect.value = '';
      });
      nodeWrap.querySelectorAll('.gwseq-external-name-input').forEach(function (input) {
        input.dispatchEvent(new Event('input', { bubbles: true }));
      });
    });
  });
})();

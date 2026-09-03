/**
 * Écran d'édition d'une Pop-in ou d'une Sticky bar — script UNIQUE partagé par les deux écrans
 * (Mises en avant), car les besoins sont réellement identiques : afficher/masquer des champs
 * conditionnels, gérer les deux sélecteurs d'image de Pop-in (médiathèque native, wp.media()), et
 * piloter l'aperçu temps réel (§J). Jamais un moteur générique de formulaire : chaque règle
 * ci-dessous nomme explicitement le champ/la cible concernés, comme partout ailleurs dans ce
 * module (voir assets/cheval-admin.js).
 *
 * Preview (§J) : sur changement d'un champ du formulaire (saisie ou sélection), l'état courant est
 * envoyé, après un court délai (debounce 350 ms — évite un appel à chaque frappe), au point
 * d'entrée AJAX de l'objet édité (gwseqCampagnePreview.action, localisé côté serveur). Ce point
 * d'entrée applique EXACTEMENT les mêmes sanitizers que la sauvegarde réelle puis appelle LA MÊME
 * fonction de rendu que le front (gwseq_render_popin_markup()/gwseq_render_sticky_bar_markup()) :
 * ce script ne reconstruit donc JAMAIS le balisage lui-même, il se contente de remplacer le
 * contenu du cadre d'aperçu par le HTML retourné.
 */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('post');
    if (!form) return;

    /* ---------------------------------------------------------------------------------------
     * Champs conditionnels : chaque règle nomme le champ déclencheur, la valeur qui révèle la
     * cible, et l'identifiant de la cible (data-gwseq-campagne-fields="...").
     * ------------------------------------------------------------------------------------- */
    var conditionalRules = [
      { name: '_gwseq_popin_cta_active', target: 'cta', checkbox: true },
      { name: '_gwseq_sticky_bar_cta_active', target: 'cta', checkbox: true },
      { name: '_gwseq_popin_style_mode', target: 'style-custom', values: ['custom'] },
      { name: '_gwseq_sticky_bar_style_mode', target: 'style-custom', values: ['custom'] },
      { name: '_gwseq_popin_declenchement_mode', target: 'declenchement-delay', values: ['delay'] },
      { name: '_gwseq_popin_declenchement_mode', target: 'declenchement-scroll', values: ['scroll'] },
      { name: '_gwseq_popin_declenchement_mode', target: 'declenchement-exit', values: ['exit_intent'] },
      { name: '_gwseq_popin_frequence_mode', target: 'frequence-days', values: ['days'] },
      { name: '_gwseq_popin_ciblage_mode', target: 'ciblage-cibles', values: ['include', 'exclude'] },
      { name: '_gwseq_sticky_bar_ciblage_mode', target: 'ciblage-cibles', values: ['include', 'exclude'] }
    ];

    function setVisible(target, visible) {
      var el = form.querySelector('[data-gwseq-campagne-fields="' + target + '"]');
      if (el) el.style.display = visible ? '' : 'none';
    }

    function applyRule(rule) {
      var visible;
      if (rule.checkbox) {
        var checkbox = form.querySelector('input[name="' + rule.name + '"]');
        visible = !!(checkbox && checkbox.checked);
      } else {
        var checked = form.querySelector('input[name="' + rule.name + '"]:checked');
        visible = !!(checked && rule.values.indexOf(checked.value) !== -1);
      }
      setVisible(rule.target, visible);
    }

    conditionalRules.forEach(function (rule) {
      var inputs = form.querySelectorAll('input[name="' + rule.name + '"]');
      if (!inputs.length) return;
      inputs.forEach(function (input) {
        input.addEventListener('change', function () { applyRule(rule); });
      });
      applyRule(rule);
    });

    /* ---------------------------------------------------------------------------------------
     * Sélecteurs d'image (Pop-in uniquement) : médiathèque native wp.media(), une seule image par
     * sélecteur — jamais un système d'upload parallèle.
     * ------------------------------------------------------------------------------------- */
    form.querySelectorAll('[data-gwseq-image-picker]').forEach(function (picker) {
      var hiddenInput = picker.querySelector('input[type="hidden"]');
      var preview = picker.querySelector('.gwseq-campagne-image-picker__preview');
      var chooseButton = picker.querySelector('.gwseq-campagne-image-picker__choose');
      var removeButton = picker.querySelector('.gwseq-campagne-image-picker__remove');

      if (chooseButton) {
        chooseButton.addEventListener('click', function (event) {
          event.preventDefault();
          if (typeof wp === 'undefined' || !wp.media) return;
          var frame = wp.media({
            title: chooseButton.getAttribute('data-gwseq-media-title') || chooseButton.textContent,
            multiple: false,
            library: { type: 'image' }
          });
          frame.on('select', function () {
            var attachment = frame.state().get('selection').first().toJSON();
            if (hiddenInput) hiddenInput.value = attachment.id;
            if (preview) {
              var thumbUrl = (attachment.sizes && attachment.sizes.thumbnail) ? attachment.sizes.thumbnail.url : attachment.url;
              preview.src = thumbUrl || '';
              preview.style.display = thumbUrl ? '' : 'none';
            }
            if (removeButton) removeButton.style.display = '';
            schedulePreview();
          });
          frame.open();
        });
      }

      if (removeButton) {
        removeButton.addEventListener('click', function (event) {
          event.preventDefault();
          if (hiddenInput) hiddenInput.value = '';
          if (preview) { preview.src = ''; preview.style.display = 'none'; }
          removeButton.style.display = 'none';
          schedulePreview();
        });
      }
    });

    /* ---------------------------------------------------------------------------------------
     * Aperçu temps réel (§J) : debounce 350 ms, un seul appel AJAX en vol à la fois (un appel
     * différé pendant qu'une réponse est en attente est réémis après coup, jamais empilé).
     * ------------------------------------------------------------------------------------- */
    var previewConfig = window.gwseqCampagnePreview;
    var previewFrame = previewConfig ? document.querySelector(previewConfig.previewSelector) : null;
    var debounceTimer = null;
    var requestInFlight = false;
    var requestPending = false;

    function schedulePreview() {
      if (!previewConfig || !previewFrame) return;
      if (debounceTimer) window.clearTimeout(debounceTimer);
      debounceTimer = window.setTimeout(runPreview, 350);
    }

    function runPreview() {
      if (requestInFlight) { requestPending = true; return; }
      requestInFlight = true;

      var formData = new FormData(form);
      formData.append('action', previewConfig.action);
      formData.append('nonce', previewConfig.nonce);

      window.fetch(previewConfig.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: formData })
        .then(function (response) { return response.json(); })
        .then(function (json) {
          if (json && json.success && json.data && typeof json.data.html === 'string') {
            previewFrame.innerHTML = json.data.html;
          }
        })
        .catch(function () { /* aperçu best-effort : une erreur réseau ne bloque jamais l'édition */ })
        .then(function () {
          requestInFlight = false;
          if (requestPending) { requestPending = false; runPreview(); }
        });
    }

    if (previewConfig && previewFrame) {
      form.addEventListener('input', schedulePreview);
      form.addEventListener('change', schedulePreview);
      runPreview();
    }

    /* ---------------------------------------------------------------------------------------
     * Ordinateur | Mobile (§J) : ne change QUE la largeur du cadre d'aperçu — une seule
     * configuration responsive, jamais deux réglages indépendants.
     * ------------------------------------------------------------------------------------- */
    var viewport = document.querySelector('[data-gwseq-preview-viewport]');
    document.querySelectorAll('[data-gwseq-preview-device]').forEach(function (button) {
      button.addEventListener('click', function () {
        var device = button.getAttribute('data-gwseq-preview-device');
        if (viewport) viewport.setAttribute('data-gwseq-preview-viewport', device);
        document.querySelectorAll('[data-gwseq-preview-device]').forEach(function (btn) {
          btn.setAttribute('aria-pressed', btn === button ? 'true' : 'false');
        });
      });
    });
  });
})();

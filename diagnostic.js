(() => {
  const form = document.querySelector('[data-diagnostic-form]');
  const start = document.querySelector('[data-diagnostic-start]');
  if (!form || !start || !window.spaDiagnostic) return;
  const questions = spaDiagnostic.questions || [];
  const heroMain = document.querySelector('[data-diagnostic-panel-a]');
  const questionPanel = document.querySelector('[data-diagnostic-panel-b]');
  const stage = form.querySelector('[data-question-stage]');
  const next = form.querySelector('[data-diagnostic-next]');
  const back = form.querySelector('[data-diagnostic-back]');
  const result = form.querySelector('[data-diagnostic-result]');
  const lead = form.querySelector('[data-lead-panel]');
  const anonymous = form.querySelector('[data-anonymous-panel]');
  const answerStore = form.querySelector('[data-answers-store]');
  const answers = {};
  let index = 0;

  const track = (category, action, name) => { if (window.spaTrack) window.spaTrack(category, action, name); };

  // stage.innerHTML est réécrit à chaque question : les radios des questions précédentes
  // disparaissent du DOM et ne seraient jamais postées. On les reconstitue en inputs hidden
  // à partir de l'objet answers, pour toutes les questions sauf celle actuellement affichée
  // dans stage (dont le radio, lui, est bien présent et sera posté normalement).
  function syncAnswerInputs(currentId) {
    if (!answerStore) return;
    answerStore.innerHTML = '';
    questions.forEach(question => {
      if (question.id === currentId || !(question.id in answers)) return;
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = `answers[${question.id}]`;
      input.value = answers[question.id];
      answerStore.appendChild(input);
    });
  }

  const level = (score, critical) => critical || score >= 21 ? {
    key: 'urgent', label: 'Situation potentiellement urgente',
    copy: 'Plusieurs indicateurs appellent une analyse rapide. Un échange avec le cabinet est conseillé afin d’identifier les délais, les risques et les solutions encore disponibles.'
  } : score >= 10 ? {
    key: 'significant', label: 'Tensions significatives',
    copy: 'Certains signaux méritent d’être examinés sans attendre leur aggravation. Une analyse précoce permet généralement de préserver davantage d’options.'
  } : {
    key: 'moderate', label: 'Vigilance modérée',
    copy: 'Les réponses ne font pas apparaître une accumulation importante de signaux d’alerte. Une surveillance régulière et une anticipation des échéances restent recommandées.'
  };

  function renderQuestion() {
    const q = questions[index];
    stage.innerHTML = `<fieldset><legend tabindex="-1"><small>${q.category}</small>${q.question}</legend><div class="diagnostic-choices">${Object.entries(q.choices).map(([label, value]) => `<label><input type="radio" name="answers[${q.id}]" value="${label.replace(/"/g, '&quot;')}" ${answers[q.id] === label ? 'checked' : ''}><span>${label}</span></label>`).join('')}</div></fieldset>`;
    form.querySelector('[data-progress-label]').textContent = `Question ${index + 1} sur ${questions.length}`;
    form.querySelector('[data-progress-bar]').style.width = `${((index + 1) / questions.length) * 100}%`;
    back.hidden = index === 0;
    next.disabled = !answers[q.id];
    next.innerHTML = index === questions.length - 1 ? 'Voir mon résultat <b>→</b>' : 'Suivant <b>→</b>';
    stage.querySelectorAll('input[type="radio"]').forEach(input => input.addEventListener('change', () => {
      answers[q.id] = input.value;
      next.disabled = false;
      syncAnswerInputs(q.id);
    }));
    stage.querySelector('legend').focus?.();
    syncAnswerInputs(q.id);
  }

  // Résultat calculé une seule fois, à la fin réelle du questionnaire — mémorisé (lastLevel)
  // pour pouvoir restituer le même écran après le rechargement complet déclenché par l'envoi du
  // formulaire (POST -> redirection), sans jamais recalculer ni modifier le barème.
  let lastLevel = null;

  function showResult() {
    const score = questions.reduce((sum, q) => sum + Number(q.choices[answers[q.id]] || 0), 0);
    const critical = answers.available_assets === 'Non' || answers.urgency === 'Dans les prochains jours' || answers.tax === 'Des échéances ne peuvent plus être réglées';
    const data = level(score, critical);
    lastLevel = data;
    questionPanel.hidden = true;
    result.hidden = false;
    result.querySelector('[data-result-level]').textContent = data.label;
    result.querySelector('[data-result-level]').className = `result-level is-${data.key}`;
    result.querySelector('[data-result-title]').textContent = data.label;
    result.querySelector('[data-result-copy]').textContent = data.copy;
    result.focus();
    track('Diagnostic', 'diagnostic_result', data.label);
  }

  // --- Transition hero <-> questionnaire : un seul conteneur (.diagnostic-stage), même largeur,
  // aucun scroll, aucun changement de section — le hero devient le questionnaire. Fondu léger
  // uniquement ; présentation seule, aucun effet sur le moteur (renderQuestion() est appelée
  // normalement, l'index et les réponses déjà saisies ne sont jamais réinitialisés).
  const FADE_MS = 220;
  let transitioning = false;

  function showQuestionPanel() {
    if (transitioning) return;
    transitioning = true;
    heroMain.style.opacity = '0';
    setTimeout(() => {
      heroMain.hidden = true;
      form.hidden = false;
      questionPanel.hidden = false;
      renderQuestion();
      questionPanel.style.opacity = '0';
      requestAnimationFrame(() => { questionPanel.style.opacity = '1'; });
      transitioning = false;
    }, FADE_MS);
  }

  function showHeroPanel() {
    if (transitioning) return;
    transitioning = true;
    questionPanel.style.opacity = '0';
    setTimeout(() => {
      form.hidden = true;
      heroMain.hidden = false;
      heroMain.style.opacity = '0';
      requestAnimationFrame(() => { heroMain.style.opacity = '1'; });
      transitioning = false;
    }, FADE_MS);
  }

  start.addEventListener('click', () => {
    form.querySelector('[name="diagnostic_started"]').value = Math.floor(Date.now() / 1000);
    track('Diagnostic', 'diagnostic_start', 'Hero');
    showQuestionPanel();
  });
  document.querySelectorAll('[data-diagnostic-quit]').forEach(link => link.addEventListener('click', event => {
    event.preventDefault();
    showHeroPanel();
  }));
  next.addEventListener('click', () => {
    if (!answers[questions[index].id]) return;
    if (index < questions.length - 1) { index += 1; renderQuestion(); }
    else showResult();
  });
  back.addEventListener('click', () => { if (index > 0) { index -= 1; renderQuestion(); } });
  form.querySelectorAll('[data-show-lead]').forEach(button => button.addEventListener('click', () => {
    result.hidden = true; anonymous.hidden = true; lead.hidden = false;
    lead.querySelector('input').focus();
  }));
  form.querySelectorAll('[data-show-anonymous]').forEach(button => button.addEventListener('click', () => {
    result.hidden = true; lead.hidden = true; anonymous.hidden = false;
    anonymous.setAttribute('tabindex', '-1'); anonymous.focus();
  }));

  // --- Continuité sessionStorage (minimisée), soumission, doublons ---
  const STORAGE_KEY = 'spa_diagnostic_state';
  // true uniquement après une restauration post-erreur : dans ce cas stage est vide (aucune
  // question n'est réellement rendue sur ce rechargement) et les 12 réponses n'existent qu'en
  // inputs hidden reconstitués depuis sessionStorage — aucune ne doit être exclue au moment du
  // second envoi, contrairement au parcours normal où la dernière question reste un radio live.
  let restoredWithoutLiveQuestion = false;

  function readLeadFields() {
    const fields = {};
    ['name', 'company', 'role', 'phone', 'email', 'availability'].forEach(key => {
      const input = lead.querySelector(`[name="lead_${key}"]`);
      if (input) fields[key] = input.value;
    });
    return fields;
  }

  function saveStateBeforeSubmit() {
    try {
      sessionStorage.setItem(STORAGE_KEY, JSON.stringify({
        level: lastLevel,
        lead: readLeadFields(),
        answers: answers,
        started: form.querySelector('[name="diagnostic_started"]').value
      }));
    } catch (e) { /* stockage indisponible (navigation privée, quota…) : dégradation silencieuse */ }
  }

  function consumeStoredState() {
    try {
      const raw = sessionStorage.getItem(STORAGE_KEY);
      sessionStorage.removeItem(STORAGE_KEY);
      return raw ? JSON.parse(raw) : null;
    } catch (e) { return null; }
  }

  form.addEventListener('submit', event => {
    syncAnswerInputs(restoredWithoutLiveQuestion ? undefined : (questions[index] ? questions[index].id : undefined));
    if (!form.checkValidity()) { event.preventDefault(); form.reportValidity(); return; }
    const submitButton = form.querySelector('[data-diagnostic-submit]');
    if (submitButton) submitButton.disabled = true; // empêche un second POST sur double clic
    track('Diagnostic', 'diagnostic_lead_attempt', 'Formulaire autodiagnostic');
    saveStateBeforeSubmit();
  });

  // --- Retour après redirection serveur : bandeau de succès/erreur, modale de confirmation ---
  const serverMessage = document.querySelector('.diagnostic-server-message');
  const resultActions = document.querySelector('[data-result-actions]');
  const resultSubmitted = document.querySelector('[data-result-submitted]');
  const modal = document.querySelector('[data-diagnostic-modal]');
  const modalPanel = document.querySelector('[data-diagnostic-modal-panel]');
  const modalClose = document.querySelector('[data-diagnostic-modal-close]');
  const mainEl = document.querySelector('main');
  let modalReturnFocus = null;

  function showRestoredResult(levelData) {
    if (!levelData || !levelData.label) return false;
    heroMain.hidden = true; form.hidden = false;
    questionPanel.hidden = true;
    result.hidden = false;
    result.querySelector('[data-result-level]').textContent = levelData.label;
    result.querySelector('[data-result-level]').className = `result-level is-${levelData.key}`;
    result.querySelector('[data-result-title]').textContent = levelData.label;
    result.querySelector('[data-result-copy]').textContent = levelData.copy;
    return true;
  }

  function focusableIn(container) {
    // .filter() : ignore les éléments présents dans le DOM mais non affichés (ex. le lien
    // tel: masqué en display:none sur desktop dans .diagnostic-modal-tel-link), pour que le
    // piège à focus ne cible jamais un élément inatteignable au clavier.
    return Array.from(container.querySelectorAll('a[href],button:not([disabled])'))
      .filter(el => el.offsetParent !== null);
  }

  function openModal() {
    if (!modal || !modalPanel) return;
    modal.hidden = false;
    document.body.classList.add('diagnostic-modal-open');
    mainEl?.setAttribute('inert', '');
    modalPanel.focus();
  }

  function closeModal() {
    if (!modal) return;
    modal.hidden = true;
    document.body.classList.remove('diagnostic-modal-open');
    mainEl?.removeAttribute('inert');
    if (modalReturnFocus && document.body.contains(modalReturnFocus)) modalReturnFocus.focus();
  }

  if (modal && modalPanel) {
    modalPanel.addEventListener('keydown', event => {
      if (event.key === 'Escape') { event.preventDefault(); closeModal(); return; }
      if (event.key !== 'Tab') return;
      const items = focusableIn(modalPanel);
      if (!items.length) return;
      const first = items[0], last = items[items.length - 1];
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    });
  }
  modalClose?.addEventListener('click', closeModal);

  if (serverMessage) {
    const statusValue = serverMessage.getAttribute('data-status');
    const stored = consumeStoredState();

    if (statusValue === 'sent') {
      const restored = showRestoredResult(stored && stored.level);
      if (restored && resultActions && resultSubmitted) {
        resultActions.hidden = true;
        resultSubmitted.hidden = false;
      }
      serverMessage.hidden = true; // remplacé par la modale ; conservé dans le DOM (data-status,
      // classes is-success) pour le suivi Matomo existant (diagnostic_submit, theme.js) et comme
      // secours si JavaScript échoue après ce point.
      modalReturnFocus = restored ? result : start;
      openModal();
    } else {
      const navEntry = performance.getEntriesByType ? performance.getEntriesByType('navigation')[0] : null;
      if (!navEntry || navEntry.type !== 'reload') track('Diagnostic', 'diagnostic_error', statusValue || 'inconnu');
      // Cas "limit" : le serveur vient précisément de refuser l'envoi pour rate-limit. Ne pas
      // restaurer le formulaire de rappel (qui donnerait l'impression qu'un nouvel envoi immédiat
      // peut réussir) — le bandeau d'erreur existant ("Trop de tentatives…") reste seul affiché.
      if (statusValue !== 'limit' && stored && stored.lead) {
        heroMain.hidden = true; form.hidden = false;
        questionPanel.hidden = true;
        result.hidden = true; anonymous.hidden = true;
        lead.hidden = false;
        Object.keys(stored.lead).forEach(key => {
          const input = lead.querySelector(`[name="lead_${key}"]`);
          if (input && stored.lead[key]) input.value = stored.lead[key];
        });
        if (stored.answers) {
          Object.assign(answers, stored.answers);
          restoredWithoutLiveQuestion = true;
          syncAnswerInputs();
        }
        if (stored.started) {
          const startedInput = form.querySelector('[name="diagnostic_started"]');
          if (startedInput) startedInput.value = stored.started;
        }
        if (stored.level) lastLevel = stored.level;
        const submitButton = form.querySelector('[data-diagnostic-submit]');
        if (submitButton) submitButton.disabled = false;
        lead.setAttribute('tabindex', '-1');
        lead.focus();
      }
    }
  }

  // --- Clics vers les contenus complémentaires ---
  document.querySelectorAll('.diagnostic-related a').forEach(link => {
    // Texte direct du lien uniquement (exclut le <b>→</b> décoratif de fin de ligne).
    const label = Array.from(link.childNodes).filter(n => n.nodeType === Node.TEXT_NODE).map(n => n.textContent).join('').trim();
    link.addEventListener('click', () => track('Diagnostic', 'diagnostic_related_click', label));
  });
})();

/**
 * Table déclarative CSS → événement analytics. Vide par défaut : un projet ajoute ses propres
 * règles ci-dessous plutôt que de disséminer des appels de tracking dans les gabarits.
 * Attend un outil déjà initialisé par ailleurs (ex. window._paq pour Matomo, window.gtag pour
 * GA4) — ce fichier n'instancie et ne charge lui-même aucun traceur, aucun cookie.
 */
(() => {
  const CLICK_RULES = [
    // { selector: 'a[href^="tel:"]', category: 'Contact', action: 'phone_click' },
  ];
  if (!CLICK_RULES.length) return;

  const track = (category, action, name) => {
    if (window._paq) window._paq.push(['trackEvent', category, action, name]);
    if (window.gtag) window.gtag('event', action, { event_category: category, event_label: name });
  };

  document.addEventListener('click', (event) => {
    const link = event.target.closest('a');
    if (!link) return;
    const rule = CLICK_RULES.find((r) => link.matches(r.selector));
    if (rule) track(rule.category, rule.action, rule.name || link.textContent.trim());
  });
})();

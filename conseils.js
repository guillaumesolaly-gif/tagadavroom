(() => {
  // Tracking Matomo de la rubrique "Conseils aux dirigeants" — réutilise uniquement
  // window.spaTrack déjà exposé par theme.js (aucune modification de theme.js). Catégorie
  // dédiée 'Conseils', sans effet sur les événements existants des autres pages.
  if (!window.spaTrack) return;
  const track = (action, label) => window.spaTrack('Conseils', action, label);

  // Slugs des pages Conseil, exposés par inc/conseils.php via wp_localize_script() — se met à
  // jour automatiquement à mesure que de nouveaux Conseils sont ajoutés, sans liste codée en dur.
  const CONSEIL_SLUGS = Array.isArray(window.spaConseilsSlugs) ? window.spaConseilsSlugs : [];

  const EXPERTISE_PATHS = [
    '/prevention-difficultes-entreprise-saint-etienne/',
    '/sauvegarde-et-redressement-judiciaire/',
    '/liquidation-judiciaire-saint-etienne/',
    '/contentieux-civil-commercial-saint-etienne/',
  ];

  const DIAGNOSTIC_PATH = '/diagnostic-entreprise-en-difficulte/';

  // Libellé du lien : le titre seul (<strong>) pour un lien de rebond .inline-resource, sinon
  // le texte direct du lien (exclut l'icône et le <b>→</b> décoratifs).
  const linkLabel = link => {
    const strong = link.querySelector('strong');
    if (strong) return strong.textContent.trim();
    return Array.from(link.childNodes).filter(n => n.nodeType === Node.TEXT_NODE).map(n => n.textContent).join('').trim() || link.textContent.trim();
  };

  const linkPath = link => {
    try { return new URL(link.href, window.location.href).pathname; } catch (e) { return null; }
  };
  const slugFromPath = path => (path || '').replace(/^\/|\/$/g, '');

  document.addEventListener('click', event => {
    const link = event.target.closest('a[href]');
    if (!link) return;

    // 1. Diagnostic (CTA officiel du gabarit, data-conseil-cta="diagnostic")
    const ctaType = link.getAttribute('data-conseil-cta');
    if (ctaType === 'diagnostic') { track('conseil_diagnostic_click', document.title); return; }
    // 2. Contact (CTA officiel du gabarit, data-conseil-cta="contact")
    if (ctaType === 'contact') { track('conseil_contact_click', document.title); return; }
    // Passerelle "Expertises" du hub (data-conseil-cta="expertise")
    if (ctaType === 'expertise') { track('conseil_expertise_click', linkLabel(link)); return; }
    // 3. Carte du hub vers un Conseil
    const hubCard = link.getAttribute('data-conseil-hub-card');
    if (hubCard) { track('hub_card_click', hubCard); return; }

    // Liens présents dans le corps éditorial d'un Conseil (liens de rebond .conseil-resource-link
    // ou tout autre lien interne) : classement par chemin de destination.
    if (link.closest('.conseil-article')) {
      const path = linkPath(link);
      if (!path) return;
      // 1 (suite). Un lien inline vers le Diagnostic (hors CTA officiel) reste un clic Diagnostic.
      if (path === DIAGNOSTIC_PATH) { track('conseil_diagnostic_click', document.title); return; }
      // 4. Destination = une autre page Conseil
      const slug = slugFromPath(path);
      if (CONSEIL_SLUGS.includes(slug)) { track('conseil_related_click', slug); return; }
      // 5. Destination = une vraie page Expertise (jamais un Guide, classé nulle part ici)
      if (EXPERTISE_PATHS.includes(path)) { track('conseil_expertise_click', linkLabel(link)); return; }
      // 6. Guide existant ou autre lien interne : aucun événement.
    }
  });
})();

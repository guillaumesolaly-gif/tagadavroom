(() => {
  // Les icônes sont des <svg class="material-symbols-outlined"><use href="#icon-NOM"></use></svg>
  // (sprite local, voir inc/icons.php) : on ne peut plus les changer via textContent comme avec
  // l'ancienne police à ligatures. setIcon() met à jour la cible du <use> à la place.
  const setIcon = (container, name) => {
    const use = container?.querySelector('.material-symbols-outlined use');
    if (use) use.setAttribute('href', '#icon-' + name);
  };
  const header = document.querySelector('.topbar');
  const nav = document.querySelector('#main-navigation');
  const toggle = document.querySelector('.menu-toggle');
  let headerIsScrolled = header?.classList.contains('is-scrolled') || false;
  const updateHeader = () => {
    if(!header) return;
    if(!headerIsScrolled && window.scrollY > 80) {
      headerIsScrolled = true;
      header.classList.add('is-scrolled');
    } else if(headerIsScrolled && window.scrollY < 16) {
      headerIsScrolled = false;
      header.classList.remove('is-scrolled');
    }
  };
  requestAnimationFrame(updateHeader); window.addEventListener('scroll', updateHeader, {passive:true});
  toggle?.addEventListener('click', () => {
    const open = !nav?.classList.contains('is-open');
    nav?.classList.toggle('is-open', open);
    toggle.setAttribute('aria-expanded', String(open));
    toggle.setAttribute('aria-label', open ? 'Fermer le menu' : 'Ouvrir le menu');
    document.body.classList.toggle('menu-is-open', open);
    setIcon(toggle, open ? 'close' : 'menu');
    if(open) requestAnimationFrame(() => nav?.querySelector('a')?.focus());
  });
  const closeNavigation = (restoreFocus = false) => {
    if(!nav?.classList.contains('is-open')) return;
    nav.classList.remove('is-open');
    document.body.classList.remove('menu-is-open');
    toggle?.setAttribute('aria-expanded','false');
    toggle?.setAttribute('aria-label','Ouvrir le menu');
    setIcon(toggle, 'menu');
    if(restoreFocus) toggle?.focus();
  };
  nav?.querySelectorAll('a').forEach(a => a.addEventListener('click', () => closeNavigation(false)));
  document.addEventListener('keydown', event => {
    if(!nav?.classList.contains('is-open')) return;
    if(event.key === 'Escape') { event.preventDefault(); closeNavigation(true); return; }
    if(event.key !== 'Tab') return;
    const focusables = [toggle, ...nav.querySelectorAll('a')].filter(Boolean);
    const first = focusables[0], last = focusables[focusables.length - 1];
    if(event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
    else if(!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
  });
  document.querySelectorAll('.back-to-top').forEach(button => button.addEventListener('click', () => window.scrollTo({top:0,behavior:'smooth'})));
  document.querySelectorAll('.contact-float').forEach(panel => {
    const button = panel.querySelector('button');
    if(button && !panel.querySelector('.contact-float-menu')) {
      const menu = document.createElement('div');
      menu.className = 'contact-float-menu';
      menu.id = button.getAttribute('aria-controls') || 'contact-float-menu';
      menu.setAttribute('role', 'group');
      menu.setAttribute('aria-label', 'Modes de contact');
      menu.hidden = true;
      const onlineChoice = document.querySelector('.avocat-consultingwidget') ? '<a href="#services-en-ligne"><svg class="material-symbols-outlined" aria-hidden="true" focusable="false"><use href="#icon-calendar_month"></use></svg><span><strong>Services en ligne</strong><small>Rendez-vous, question ou visio</small></span></a>' : '';
      menu.innerHTML = '<p><strong>Comment souhaitez-vous échanger&nbsp;?</strong><span>Choisissez le mode de contact adapté à votre besoin.</span></p><a href="#contact"><svg class="material-symbols-outlined" aria-hidden="true" focusable="false"><use href="#icon-call"></use></svg><span><strong>Contacter le cabinet</strong><small>Téléphone ou message</small></span></a>' + onlineChoice;
      panel.insertBefore(menu, button);
    }
    const setPanelOpen = (open) => {
      panel.classList.toggle('is-open', open);
      const panelMenu = panel.querySelector('.contact-float-menu'); if(panelMenu) panelMenu.hidden = !open;
      button.setAttribute('aria-expanded', String(open));
      const label = button.querySelector('strong');
      setIcon(button, open ? 'close' : 'chat_bubble');
      if(label) label.textContent = open ? 'Fermer' : 'Échanger avec le cabinet';
      if(open) requestAnimationFrame(() => panel.querySelector('.contact-float-menu a')?.focus());
      if(open) spaTrack('Modale contact', 'contact_modal_open', modalLabel);
    };
    button?.addEventListener('click', () => setPanelOpen(!panel.classList.contains('is-open')));
    panel.querySelectorAll('.contact-float-menu a').forEach(link => link.addEventListener('click', () => setPanelOpen(false)));
    panel.addEventListener('keydown', event => {
      if(event.key === 'Escape' && panel.classList.contains('is-open')) { event.preventDefault(); setPanelOpen(false); button.focus(); }
    });
  });
  const contact = document.querySelector('#contact');
  const floatingAvoidZones = [...document.querySelectorAll('.paid-service, .online-consultation, .widget-panel')];
  const floatingPanels = document.querySelectorAll('.contact-float');
  const backButtons = document.querySelectorAll('.back-to-top');
  if(contact) {
    let inContactArea = false;
    let floatingUpdateQueued = false;
    const updateFloatingControls = () => {
      floatingUpdateQueued = false;
      const contactTop = contact.getBoundingClientRect().top;
      if(!inContactArea && contactTop < window.innerHeight - 140) inContactArea = true;
      else if(inContactArea && contactTop > window.innerHeight - 50) inContactArea = false;
      const inServicesArea = floatingAvoidZones.some(zone => {
        const rect = zone.getBoundingClientRect();
        return rect.top < window.innerHeight - 80 && rect.bottom > 110;
      });
      const hideFloatingPanel = inContactArea || inServicesArea;
      floatingPanels.forEach(panel => {
        panel.classList.toggle('is-hidden', hideFloatingPanel);
        if(hideFloatingPanel) {
          panel.classList.remove('is-open');
          const panelButton = panel.querySelector('button');
          if(panelButton) {
            panelButton.setAttribute('aria-expanded', 'false');
            const label = panelButton.querySelector('strong');
            setIcon(panelButton, 'chat_bubble');
            if(label) label.textContent = 'Échanger avec le cabinet';
          }
        }
      });
      backButtons.forEach(button => button.classList.toggle('is-visible', inContactArea));
    };
    const requestFloatingUpdate = () => {
      if(floatingUpdateQueued) return;
      floatingUpdateQueued = true;
      requestAnimationFrame(updateFloatingControls);
    };
    requestFloatingUpdate();
    window.addEventListener('scroll', requestFloatingUpdate, {passive:true});
    window.addEventListener('resize', requestFloatingUpdate, {passive:true});
  }
  const widget = document.querySelector('.avocat-consultingwidget');
  if(widget) {
    const servicesSection = widget.closest('.paid-service') || widget.closest('section');
    if(servicesSection && !document.getElementById('services-en-ligne')) servicesSection.id = 'services-en-ligne';
    const load = () => { if(document.getElementById('avocat-widget')) return; const s=document.createElement('script'); s.id='avocat-widget'; s.src='https://consultation.avocat.fr/js/consultingwidget.js'; s.async=true; document.body.appendChild(s); };
    if('IntersectionObserver' in window){ const o=new IntersectionObserver(e=>{if(e.some(x=>x.isIntersecting)){load();o.disconnect();}},{rootMargin:'500px'});o.observe(widget); } else load();
  }
  document.querySelectorAll('.video-consent').forEach(player => {
    player.querySelector('button')?.addEventListener('click', () => {
      const src = player.dataset.videoSrc;
      if(!src) return;
      const iframe = document.createElement('iframe');
      iframe.src = src + (src.includes('?') ? '&' : '?') + 'autoplay=1';
      iframe.title = 'Interview de Maître Juliette Saint-Père sur TL7';
      iframe.allow = 'autoplay; fullscreen; picture-in-picture; web-share';
      iframe.allowFullscreen = true;
      iframe.loading = 'lazy';
      player.replaceChildren(iframe);
      player.classList.add('is-loaded');
    }, {once:true});
  });

  // Suivi des conversions Matomo. On se contente de pousser des commandes dans la file _paq déjà
  // initialisée par le plugin Matomo (jamais de tracker instancié ni de cookie posé ici) : le
  // consentement Complianz, déjà branché sur cette file, s'applique donc sans contournement.
  const spaTrack = (category, action, name) => { if (window._paq) window._paq.push(['trackEvent', category, action, name]); };
  window.spaTrack = spaTrack; // réutilisé par diagnostic.js pour les événements propres à cette page, sans dupliquer l'appel _paq.push
  const pageType = window.spaTracking?.pageType || 'expertise';
  const bottomLabel = { home: 'Bas de page — Home', postulation: 'Bas de page — Postulation', diagnostic: 'Bas de page — Diagnostic' }[pageType] || 'Bas de page — Expertise';
  const avocatLabel = { home: 'Home', diagnostic: 'Diagnostic' }[pageType] || 'Page expertise';
  const modalLabel = { home: 'Home', diagnostic: 'Diagnostic', postulation: 'Postulation' }[pageType] || 'Page expertise';
  const CLICK_RULES = [
    { selector: '.phone-switch a[href^="tel:"]', category: 'Contact', action: 'contact_phone_click', name: 'Menu' },
    { selector: '.hero-conversion a[href^="tel:"]', category: 'Contact', action: 'contact_phone_click', name: 'Hero' },
    { selector: '.primary-contact a[href^="tel:"]', category: 'Contact', action: 'contact_phone_click', name: 'Aside contact' },
    { selector: '.anonymous-contact a[href^="tel:"]', category: 'Contact', action: 'contact_phone_click', name: 'Bas de page — Diagnostic' },
    { selector: '.diagnostic-modal-panel a[href^="tel:"]', category: 'Contact', action: 'contact_phone_click', name: 'Modale confirmation diagnostic' },
    { selector: '.contact-card a[href^="tel:"]', category: 'Contact', action: 'contact_phone_click', name: bottomLabel },
    { selector: '.primary-contact a[href^="mailto:"]', category: 'Contact', action: 'contact_email_click', name: 'Aside contact' },
    { selector: '.anonymous-contact a[href^="mailto:"]', category: 'Contact', action: 'contact_email_click', name: 'Bas de page — Diagnostic' },
    { selector: '.contact-card a[href^="mailto:"]', category: 'Contact', action: 'contact_email_click', name: bottomLabel },
    { selector: '.diagnostic-server-message.is-error a[href^="mailto:"]', category: 'Contact', action: 'contact_email_click', name: 'Diagnostic — Erreur formulaire' },
    { selector: '.contact-float-menu a[href="#contact"]', category: 'Modale contact', action: 'contact_modal_contact', name: modalLabel },
  ];
  document.addEventListener('click', event => {
    const link = event.target.closest('a');
    if (!link) return;
    const rule = CLICK_RULES.find(r => link.matches(r.selector));
    if (rule) spaTrack(rule.category, rule.action, rule.name);
  });

  // Avocat.fr : suivi par domaine de destination plutôt que par classe CSS. Le widget
  // (consultingwidget.js, chargé plus haut) remplace/complète le placeholder d'origine par ses
  // propres éléments une fois initialisé — leur structure ne nous appartient pas et ne doit donc
  // pas être présumée. Tout lien vers consultation.avocat.fr est concerné, quel que soit
  // l'élément réellement cliqué, ce qui reproduit le comportement du suivi natif des liens
  // sortants de Matomo (qui fonctionne déjà, lui aussi par domaine) et le complète sans le
  // remplacer : enableLinkTracking() n'est pas touché.
  const avocatLinkHost = link => { try { return new URL(link.href, location.href).hostname; } catch (e) { return null; } };
  // Le clic milieu (ouverture en arrière-plan) ne déclenche jamais 'click' dans un navigateur —
  // seulement 'auxclick'. Sans ce second écouteur, ce cas ne serait tout simplement jamais suivi.
  document.addEventListener('auxclick', event => {
    if (event.button !== 1) return;
    const link = event.target.closest('a[href]');
    if (!link || avocatLinkHost(link) !== 'consultation.avocat.fr') return;
    spaTrack('Avocat.fr', 'avocatfr_click', avocatLabel);
  });
  document.addEventListener('click', event => {
    const link = event.target.closest('a[href]');
    if (!link || avocatLinkHost(link) !== 'consultation.avocat.fr') return;

    // Nouvel onglet/fenêtre voulu par l'utilisateur ou par le lien lui-même : aucune course avec
    // le déchargement de la page, donc aucun blocage ni délai — simple suivi.
    if (link.target === '_blank' || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
      spaTrack('Avocat.fr', 'avocatfr_click', avocatLabel);
      return;
    }

    // Clic simple, navigation dans le même onglet : on retient la navigation le temps strictement
    // nécessaire à l'envoi (callback Matomo), avec un filet de sécurité à 300 ms au cas où le
    // callback ne se déclencherait pas (Matomo indisponible, requête bloquée, etc.), pour ne
    // jamais casser le lien.
    event.preventDefault();
    let navigated = false;
    const proceed = () => { if (navigated) return; navigated = true; window.location.href = link.href; };
    const safetyTimer = setTimeout(proceed, 300);
    if (window._paq) {
      window._paq.push(['trackEvent', 'Avocat.fr', 'avocatfr_click', avocatLabel, undefined, () => { clearTimeout(safetyTimer); proceed(); }]);
    } else {
      proceed();
    }
  });

  document.querySelectorAll('[data-show-lead]').forEach(button => button.addEventListener('click', () => spaTrack('Diagnostic', 'diagnostic_lead_show', 'Résultat diagnostic')));
  if (document.querySelector('.diagnostic-server-message.is-success')) {
    // navEntry.type vaut 'reload' uniquement pour un F5/rechargement explicite du navigateur — ni
    // pour l'arrivée normale après redirection POST→GET, ni pour un nouvel envoi réel du
    // formulaire (qui produit sa propre redirection 'navigate'). Ce test évite donc le double
    // comptage sur simple rechargement sans jamais modifier l'URL ni sous-compter un second envoi
    // légitime dans la même session.
    const navEntry = performance.getEntriesByType ? performance.getEntriesByType('navigation')[0] : null;
    if (!navEntry || navEntry.type !== 'reload') spaTrack('Diagnostic', 'diagnostic_submit', 'Formulaire autodiagnostic');
  }
})();

(() => {
  const nav = document.querySelector('#main-navigation');
  const toggle = document.querySelector('.menu-toggle');

  toggle?.addEventListener('click', () => {
    const open = !nav?.classList.contains('is-open');
    nav?.classList.toggle('is-open', open);
    toggle.setAttribute('aria-expanded', String(open));
    toggle.setAttribute('aria-label', open ? 'Fermer le menu' : 'Ouvrir le menu');
    if (open) requestAnimationFrame(() => nav?.querySelector('a')?.focus());
  });

  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape' || !nav?.classList.contains('is-open')) return;
    nav.classList.remove('is-open');
    toggle?.setAttribute('aria-expanded', 'false');
    toggle?.focus();
  });

  // Modale générique. Balisage attendu (voir assets/css/components.css) : .modal avec
  // role="dialog", aria-modal="true" et aria-labelledby pointant vers un titre à l'intérieur —
  // ce script gère uniquement le comportement, pas ces attributs statiques.
  const modalFocusableSelector = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
  // Ne retient que les éléments réellement visibles (offsetParent est null pour tout élément
  // display:none ou caché par un ancêtre) : sans ce filtre, le focus trap peut tenter de rendre
  // le focus à un élément invisible, qui refuse alors silencieusement le focus.
  const focusableIn = (container) => [...container.querySelectorAll(modalFocusableSelector)].filter((el) => el.offsetParent !== null);

  // Rend inerte tout le contenu de la page en dehors de la chaîne d'ancêtres de `target` (donc
  // en dehors de la modale elle-même), en remontant jusqu'à <body> : fonctionne quelle que soit
  // la profondeur de la modale dans le DOM, sans avoir à déplacer la modale elle-même.
  const setBackgroundInert = (target, isInert) => {
    let node = target;
    while (node && node !== document.body) {
      const parent = node.parentElement;
      if (parent) {
        [...parent.children].forEach((sibling) => {
          if (sibling === node) return;
          if (isInert) sibling.setAttribute('inert', ''); else sibling.removeAttribute('inert');
        });
      }
      node = parent;
    }
  };

  let modalOpener = null;

  const closeModal = (modal, restoreFocus = true) => {
    if (!modal.classList.contains('is-open')) return;
    modal.classList.remove('is-open');
    setBackgroundInert(modal, false);
    modalOpener?.setAttribute('aria-expanded', 'false');
    if (restoreFocus) modalOpener?.focus();
    modalOpener = null;
  };

  const openModal = (modal, opener) => {
    modalOpener = opener;
    modal.classList.add('is-open');
    setBackgroundInert(modal, true);
    requestAnimationFrame(() => focusableIn(modal)[0]?.focus());
  };

  document.querySelectorAll('.modal').forEach((modal) => {
    modal.querySelectorAll('[data-modal-close]').forEach((button) => {
      button.addEventListener('click', () => closeModal(modal));
    });
    modal.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') { closeModal(modal); return; }
      if (event.key !== 'Tab') return;
      const focusables = focusableIn(modal);
      if (!focusables.length) return;
      const first = focusables[0];
      const last = focusables[focusables.length - 1];
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    });
  });
  document.querySelectorAll('[data-modal-open]').forEach((button) => {
    button.addEventListener('click', () => {
      const modal = document.getElementById(button.dataset.modalOpen);
      if (!modal) return;
      button.setAttribute('aria-expanded', 'true');
      openModal(modal, button);
    });
  });
})();

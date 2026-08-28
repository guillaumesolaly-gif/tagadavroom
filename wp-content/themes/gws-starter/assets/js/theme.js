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

  document.querySelectorAll('.modal').forEach((modal) => {
    modal.querySelectorAll('[data-modal-close]').forEach((button) => {
      button.addEventListener('click', () => modal.classList.remove('is-open'));
    });
    modal.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') modal.classList.remove('is-open');
    });
  });
  document.querySelectorAll('[data-modal-open]').forEach((button) => {
    button.addEventListener('click', () => {
      const modal = document.getElementById(button.dataset.modalOpen);
      if (!modal) return;
      modal.classList.add('is-open');
      requestAnimationFrame(() => modal.querySelector('button, a, input, select, textarea')?.focus());
    });
  });
})();

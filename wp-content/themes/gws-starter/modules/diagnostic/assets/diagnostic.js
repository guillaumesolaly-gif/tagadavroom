(() => {
  const form = document.querySelector('.diagnostic-form');
  if (!form) return;
  form.addEventListener('submit', (event) => {
    const unanswered = form.querySelectorAll('.diagnostic-question:not(:has(input:checked))');
    if (unanswered.length) {
      event.preventDefault();
      unanswered[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
  });
})();

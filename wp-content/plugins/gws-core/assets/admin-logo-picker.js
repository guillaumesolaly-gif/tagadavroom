/**
 * Sélecteur de logo (Réglages > Entité) — usage unique à cet écran, s'appuie sur le
 * sélecteur de médias natif de WordPress (wp.media). N'enregistre rien lui-même : se contente
 * de renseigner le champ caché #gws-logo_id, la sauvegarde reste gérée par le formulaire natif.
 */
(() => {
  const selectButton = document.getElementById('gws-logo-select');
  const removeButton = document.getElementById('gws-logo-remove');
  const input = document.getElementById('gws-logo_id');
  const preview = document.getElementById('gws-logo-preview');
  if (!selectButton || !input || !preview || !window.wp || !window.wp.media) return;

  let frame;

  selectButton.addEventListener('click', (event) => {
    event.preventDefault();
    if (!frame) {
      frame = window.wp.media({
        title: 'Choisir un logo',
        button: { text: 'Utiliser ce logo' },
        library: { type: 'image' },
        multiple: false,
      });
      frame.on('select', () => {
        const attachment = frame.state().get('selection').first().toJSON();
        input.value = attachment.id;
        preview.src = (attachment.sizes && attachment.sizes.thumbnail) ? attachment.sizes.thumbnail.url : attachment.url;
        preview.style.display = 'block';
        if (removeButton) removeButton.style.display = 'inline-block';
      });
    }
    frame.open();
  });

  removeButton?.addEventListener('click', (event) => {
    event.preventDefault();
    input.value = '';
    preview.removeAttribute('src');
    preview.style.display = 'none';
    removeButton.style.display = 'none';
  });
})();

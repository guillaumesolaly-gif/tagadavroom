(function (blocks, element, blockEditor, components, i18n) {
  const { registerBlockType } = blocks;
  const { createElement: el } = element;
  const { InspectorControls, URLInput } = blockEditor;
  const { PanelBody, TextControl, TextareaControl } = components;
  const { __ } = i18n;

  registerBlockType('gws/resource-link', {
    title: __('Lien de ressource', 'gws-starter'),
    icon: 'admin-links',
    category: 'common',
    attributes: {
      title: { type: 'string', default: '' },
      description: { type: 'string', default: '' },
      url: { type: 'string', default: '' },
      icon: { type: 'string', default: 'info' },
    },
    edit: function (props) {
      const { attributes, setAttributes } = props;
      return el('div', { className: props.className + ' resource-link-editor' }, [
        el(InspectorControls, { key: 'inspector' },
          el(PanelBody, { title: __('Réglages', 'gws-starter') },
            el(TextControl, {
              label: __('Nom de l’icône (sprite du thème)', 'gws-starter'),
              value: attributes.icon,
              onChange: (icon) => setAttributes({ icon }),
            })
          )
        ),
        el(TextControl, {
          label: __('Titre', 'gws-starter'),
          value: attributes.title,
          onChange: (title) => setAttributes({ title }),
        }),
        el(TextareaControl, {
          label: __('Description', 'gws-starter'),
          value: attributes.description,
          onChange: (description) => setAttributes({ description }),
        }),
        el('div', { className: 'components-base-control' },
          el('label', { className: 'components-base-control__label' }, __('Adresse', 'gws-starter')),
          el(URLInput, {
            value: attributes.url,
            onChange: (url) => setAttributes({ url }),
          })
        ),
      ]);
    },
    save: function () {
      return null; // rendu côté serveur, voir gws_render_resource_block()
    },
  });
})(window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.i18n);

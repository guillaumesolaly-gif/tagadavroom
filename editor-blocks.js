(function(blocks, element, blockEditor, components) {
  const el = element.createElement;
  const InspectorControls = blockEditor.InspectorControls;
  const useBlockProps = blockEditor.useBlockProps;
  const RichText = blockEditor.RichText;
  const PanelBody = components.PanelBody;
  const TextControl = components.TextControl;

  blocks.registerBlockType('spa/resource-link', {
    apiVersion: 3,
    title: 'Encadré ressource',
    icon: 'admin-links',
    category: 'widgets',
    attributes: {
      title: {type:'string', default:''},
      description: {type:'string', default:''},
      url: {type:'string', default:''},
      icon: {type:'string', default:'info'}
    },
    edit: function(props) {
      const a = props.attributes;
      const set = props.setAttributes;
      const blockProps = useBlockProps({className:'spa-resource-editor'});
      return el(element.Fragment, {},
        el(InspectorControls, {},
          el(PanelBody, {title:'Réglages de l’encadré', initialOpen:true},
            el(TextControl, {label:'Titre', value:a.title, onChange:v=>set({title:v})}),
            el(TextControl, {label:'Description', value:a.description, onChange:v=>set({description:v})}),
            el(TextControl, {label:'Adresse du lien', value:a.url, onChange:v=>set({url:v})}),
            el(TextControl, {label:'Icône Material Symbols', value:a.icon, onChange:v=>set({icon:v})})
          )
        ),
        el('div', blockProps,
          el('span', {className:'material-symbols-outlined', 'aria-hidden':'true'}, a.icon || 'info'),
          el('span', {className:'spa-resource-editor-copy'},
            el(RichText, {tagName:'strong', value:a.title, placeholder:'Titre de la ressource', onChange:v=>set({title:v}), allowedFormats:[]}),
            el(RichText, {tagName:'small', value:a.description, placeholder:'Description de la ressource', onChange:v=>set({description:v}), allowedFormats:[]})
          ),
          el('b', {}, '→')
        )
      );
    },
    save: function() { return null; }
  });
})(window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components);

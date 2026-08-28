<?php
/**
 * Contenu d'exemple pour la rubrique Guides — à remplacer intégralement par le contenu réel du
 * projet. Utilisé une seule fois, à la création des pages (voir gws_guides_seed_pages()) : une
 * fois une page créée, ce fichier n'est plus jamais relu pour elle.
 */

if (!defined('ABSPATH')) exit;

function gws_guides_sample_content() {
  return array(
    'exemple-premier-guide' => array(
      'title' => 'Exemple de premier guide',
      'category' => 'Premiers pas',
      'summary' => 'Résumé court de ce guide, affiché dans les listes et sur le hub.',
      'content' => "<!-- wp:paragraph --><p>Contenu d’exemple à remplacer par le texte réel du projet.</p><!-- /wp:paragraph -->\n<!-- wp:heading --><h2>Un premier sous-titre</h2><!-- /wp:heading -->\n<!-- wp:paragraph --><p>Un second paragraphe d’exemple.</p><!-- /wp:paragraph -->",
    ),
    'exemple-second-guide' => array(
      'title' => 'Exemple de second guide',
      'category' => 'Aller plus loin',
      'summary' => 'Résumé court de ce second guide.',
      'content' => "<!-- wp:paragraph --><p>Contenu d’exemple à remplacer par le texte réel du projet.</p><!-- /wp:paragraph -->",
    ),
  );
}

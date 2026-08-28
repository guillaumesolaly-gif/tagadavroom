# Module Diagnostic — présentation (thème)

Pendant du module `diagnostic` de `gws-core` (données/logique). Voir son README pour activer le
module côté plugin (`config/modules.php`) et adapter le questionnaire.

## Activer l'affichage

Aucune copie de fichier : dès que `'diagnostic'` est ajouté à `config/modules.php` côté plugin,
le gabarit « Diagnostic (module) » (`page-templates/diagnostic.php`, resté dans ce dossier)
apparaît automatiquement dans le sélecteur de gabarit de l'éditeur. Il suffit de créer une page
dans wp-admin et de lui assigner ce gabarit.

Les fichiers `assets/diagnostic.css` et `assets/diagnostic.js` restent eux aussi référencés
depuis ce dossier de module. Le gabarit s'auto-enregistre pour ses propres assets via un
`add_action('wp_enqueue_scripts', ...)` placé en tête du fichier, avant `get_header()`.

## Retirer

Retirer `'diagnostic'` de `config/modules.php` : le gabarit disparaît du sélecteur dès la
requête suivante, sans rien à supprimer côté thème.

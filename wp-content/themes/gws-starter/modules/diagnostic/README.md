# Module Diagnostic — présentation (thème)

Pendant du module `diagnostic` de `gws-core` (données/logique). Voir son README pour activer le
module côté plugin (`config/modules.php`) et adapter le questionnaire.

## Activer l'affichage

1. Copier `page-diagnostic.php` vers `page-templates/diagnostic.php` à la racine du thème.
2. Créer une page dans wp-admin, lui assigner le gabarit « Diagnostic (module) ».
3. Les fichiers `assets/diagnostic.css` et `assets/diagnostic.js` restent référencés depuis ce
   dossier de module — inutile de les déplacer, seul le fichier `.php` doit être copié.

Le gabarit s'auto-enregistre pour ses propres assets via un `add_action('wp_enqueue_scripts', ...)`
placé en tête du fichier, avant `get_header()` : aucune modification de `inc/setup.php` n'est
nécessaire pour activer ou désactiver ce module.

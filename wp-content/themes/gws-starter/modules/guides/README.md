# Module Guides — présentation (thème)

Pendant du module `guides` de `gws-core` (données/logique). Voir son README pour activer le
module côté plugin et remplacer le contenu d'exemple.

## Activer l'affichage

Aucune copie de fichier : dès que `'guides'` est ajouté à `config/modules.php` côté plugin, les
gabarits « Guide » et « Guides — Hub » (`page-templates/guide.php` et `page-templates/guides-
hub.php`, restés dans ce dossier) sont automatiquement détectés. Le hub et les pages d'exemple
sont créés automatiquement au premier chargement.

## Retirer

Retirer `'guides'` de `config/modules.php` : les gabarits disparaissent du sélecteur dès la
requête suivante, sans rien à supprimer côté thème.

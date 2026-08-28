# Module Guides (exemple)

Rubrique de contenu éditorial structuré : un hub qui regroupe par catégorie des pages utilisant
un gabarit dédié. Généralisation de la rubrique « Conseils aux dirigeants » d'un cabinet
d'avocat — le contenu fourni (`content.sample.php`) est un exemple à remplacer.

## Activer

1. Ajouter `'guides'` à `config/modules.php`.
2. Remplacer `content.sample.php` par le contenu réel du projet (autant de pages que
   nécessaire, mêmes clés : `title`, `category`, `summary`, `content`).
3. Côté thème, aucune copie n'est nécessaire : les gabarits fournis dans
   `wp-content/themes/gws-starter/modules/guides/page-templates/` deviennent automatiquement
   disponibles dès que ce module est actif.

## Principe conservé : insert-only

Chaque page n'est créée qu'une seule fois (`gws_guides_seed_pages()`, verrouillé par l'option
`gws_guides_seeded`). Une fois créée, son contenu vit en base et devient éditable normalement
depuis l'éditeur — modifier `content.sample.php` après coup n'a plus aucun effet sur les pages
déjà créées. Aucune page existante n'est jamais réécrite automatiquement.

Ajouter un guide après coup : créer une page dans wp-admin, lui assigner le gabarit « Guide »,
renseigner catégorie et résumé — elle apparaît sur le hub sans toucher à aucun fichier.

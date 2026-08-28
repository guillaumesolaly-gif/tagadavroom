# Modules métier — présentation (thème)

Chaque dossier ici est le pendant **présentation** d'un module de données défini dans le plugin
`gws-core` (`wp-content/plugins/gws-core/modules/<même-slug>/`).

Les gabarits fournis par un module restent **physiquement dans son propre dossier** — aucun
fichier n'a besoin d'être copié, déplacé ou supprimé à la racine du thème pour les activer ou
les retirer. C'est `inc/module-templates.php` (chargé en permanence par le cœur du thème, sans
coût quand aucun module n'en a besoin) qui les rend disponibles auprès de WordPress via ses
propres filtres natifs — voir `ARCHITECTURE.md` à la racine du dépôt pour le détail du mécanisme.

## Module basé sur un Custom Post Type (ex. `_boilerplate-cpt`)

Placer `single-{post_type}.php` et/ou `archive-{post_type}.php` dans
`modules/<slug>/templates/` : ils sont automatiquement utilisés dès que le CPT correspondant est
enregistré (module activé côté plugin), exactement comme s'ils étaient à la racine du thème —
sauf qu'ils n'y sont jamais copiés. Un vrai fichier `single-{post_type}.php` déjà présent à la
racine du thème garde toujours la priorité.

## Module basé sur un gabarit de page (ex. `guides`, `diagnostic`, `qa`)

Placer le fichier dans `modules/<slug>/page-templates/`, avec un en-tête `Template Name:` comme
n'importe quel gabarit de page WordPress natif. Il apparaît automatiquement dans le sélecteur de
gabarit de l'éditeur dès que le module est actif, sans jamais être copié dans
`page-templates/` à la racine du thème.

## Activer / retirer un module

Le seul geste nécessaire est côté plugin : ajouter ou retirer le slug dans
`wp-content/plugins/gws-core/config/modules.php`. Les gabarits du module apparaissent ou
disparaissent en conséquence, sans aucune manipulation de fichier côté thème.

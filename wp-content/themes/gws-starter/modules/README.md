# Modules métier — présentation (thème)

Chaque dossier ici est le pendant **présentation** d'un module de données défini dans le plugin
`gws-core` (`wp-content/plugins/gws-core/modules/<même-slug>/`). Rien ici n'est chargé
automatiquement : ces fichiers sont des références prêtes à l'emploi que l'on active à la main,
selon la nature du module.

## Module basé sur un Custom Post Type (ex. `_boilerplate-cpt`)

WordPress détecte nativement `single-{post_type}.php` et `archive-{post_type}.php` **s'ils sont
placés à la racine du thème**. Pour activer l'affichage d'un module CPT :

1. Copier les fichiers de `modules/<slug>/templates/` vers la racine du thème.
2. Renommer `{post_type}` par le vrai nom du post type déclaré côté plugin.

Aucun filtre, aucune configuration côté thème : c'est la hiérarchie de gabarits standard de
WordPress qui fait le travail.

## Module basé sur un gabarit de page (ex. `guides`)

Les fichiers de `modules/<slug>/` avec un en-tête `Template Name:` sont déjà des gabarits de
page WordPress natifs. Il suffit qu'ils soient présents dans `page-templates/` à la racine du
thème (copier depuis le dossier du module) pour apparaître dans le sélecteur de gabarit de
l'éditeur — aucune autre étape.

## Pourquoi ce n'est pas automatique

Garder le thème « nu » par défaut (aucun template de module dans sa racine) rend immédiatement
visible, pour un développeur qui reprend le projet, quels modules sont réellement utilisés :
il lui suffit de regarder la racine du thème plutôt que de déchiffrer un fichier de
configuration supplémentaire.

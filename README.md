# GWS — Generic Web Starter

Base technique réutilisable pour construire des sites WordPress sur mesure, quel que soit le
secteur (avocat, élevage, artisan, PME...). Ce n'est pas un thème « universel » à options ni un
page builder : c'est un socle léger, sécurisé et documenté, pensé pour être repris par n'importe
quel développeur WordPress.

Développé et maintenu par [Tagada Vroom](https://tagadavroom.fr/). Les identifiants techniques
(`gws-core`, `gws-starter`, préfixes `gws_`/`gws_core_`) restent neutres et stables d'un projet
à l'autre — Tagada Vroom est l'éditeur du produit, pas le namespace technique.

## Composition

Ce dépôt contient deux composants installés côte à côte dans un WordPress standard :

- **`wp-content/plugins/gws-core/`** — les données et la logique métier persistantes : réglages
  de l'entité, champs SEO, cadre de migration, modules métier (CPT, taxonomies, champs
  structurés, relations). Doit rester actif quel que soit le thème utilisé.
- **`wp-content/themes/gws-starter/`** — la présentation : templates, design system, assets,
  accessibilité. Ne stocke aucune donnée persistante lui-même.

Cette séparation garantit qu'un futur changement de thème ne fait jamais disparaître les types
de contenus ou les données métier du back-office.

## Démarrage rapide

1. Copier les deux dossiers dans l'installation WordPress cible (`wp-content/plugins/gws-core`
   et `wp-content/themes/gws-starter`).
2. Activer le plugin `GWS Core`, puis le thème `GWS Starter`.
3. Suivre le README de chaque composant pour la suite (réglages, charte visuelle, modules).

## Documentation

- [`ARCHITECTURE.md`](ARCHITECTURE.md) — philosophie du starter, séparation thème/plugin,
  mécanisme des modules métier, pièges WordPress connus à ne pas reproduire.
- [`wp-content/plugins/gws-core/README.md`](wp-content/plugins/gws-core/README.md)
- [`wp-content/themes/gws-starter/README.md`](wp-content/themes/gws-starter/README.md)
- Registre des modules et de leurs préfixes :
  [`wp-content/plugins/gws-core/modules/README.md`](wp-content/plugins/gws-core/modules/README.md)
- [`tests/README.md`](tests/README.md) — tests de logique autonomes (hors WordPress), non
  inclus dans les paquets installables.

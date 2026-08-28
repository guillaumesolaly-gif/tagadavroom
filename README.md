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

## Démarrer un nouveau site — procédure simple

Pas besoin d'être développeur pour lancer un nouveau projet à partir de GWS : l'essentiel du
travail (créer les bonnes fonctionnalités pour le site) revient à un agent IA de développement
(ex. Claude Code), à qui l'on transmet ce dépôt comme point de départ.

1. **Installer `gws-core`** : dans WordPress, Extensions > Ajouter une extension > Téléverser
   `gws-core.zip` > Installer > Activer.
2. **Installer `gws-starter`** : Apparence > Thèmes > Ajouter un thème > Téléverser
   `gws-starter.zip` > Installer > Activer.
3. **Transmettre ce dépôt à son agent IA** (donner accès au code du projet, comme n'importe quel
   dépôt de travail).
4. **Lui demander de lire `AI-AGENT.md`** (et `README.md`/`ARCHITECTURE.md`) avant de toucher au
   code, et de respecter ces règles pendant tout le projet. Exemple de phrase à lui donner telle
   quelle : *« Lis intégralement README.md, ARCHITECTURE.md et AI-AGENT.md avant de toucher au
   code. Respecte ces règles pendant tout le projet. »*
5. **Définir le besoin du nouveau site** avec l'agent : à qui s'adresse le site, quels contenus,
   quelles fonctionnalités (ex. « un site d'élevage avec une fiche par cheval »).
6. **Ne pas commencer par modifier le cœur** (`gws-core`/`gws-starter`) : les besoins propres au
   projet se développent dans un module métier séparé (voir `AI-AGENT.md`) — le cœur ne change
   normalement pas d'un projet à l'autre.

## Documentation

- [`AI-AGENT.md`](AI-AGENT.md) — instructions impératives destinées à l'agent IA qui développera
  le projet : rôle de chaque composant, interdictions explicites, règles de sécurité/SEO,
  méthode de travail, critère de fin de tâche. **À faire lire à l'agent avant tout
  développement.**
- [`ARCHITECTURE.md`](ARCHITECTURE.md) — philosophie du starter, séparation thème/plugin,
  mécanisme des modules métier, pièges WordPress connus à ne pas reproduire.
- [`wp-content/plugins/gws-core/README.md`](wp-content/plugins/gws-core/README.md)
- [`wp-content/themes/gws-starter/README.md`](wp-content/themes/gws-starter/README.md)
- Registre des modules et de leurs préfixes :
  [`wp-content/plugins/gws-core/modules/README.md`](wp-content/plugins/gws-core/modules/README.md)
- [`tests/README.md`](tests/README.md) — tests de logique autonomes (hors WordPress), non
  inclus dans les paquets installables.

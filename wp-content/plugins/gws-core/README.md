# GWS Core

Édité par [Tagada Vroom](https://tagadavroom.fr/). Plugin compagnon du thème `gws-starter`.
Détient tout ce qui doit **survivre à un changement de thème** : réglages de Ma structure, champs
SEO de secours, cadre de migration, et modules métier (CPT, taxonomies, champs structurés,
relations, logique métier persistante).

Ce plugin doit rester actif en permanence sur un site construit avec ce starter, quel que soit
le thème utilisé — y compris si le thème est un jour remplacé.

## Contenu

- `includes/settings.php` — réglages génériques de **« Ma structure »** (identité, identité
  visuelle, présentation, coordonnées, logo, réseaux sociaux, crédit de réalisation), lus par le
  thème via `gws_core_get_setting($key)` et quelques helpers dédiés (`gws_core_get_logo_url()`,
  `gws_core_whatsapp_url()`, `gws_core_social_links()`, `gws_core_schema_same_as()`...). Tous les
  champs au-delà du socle minimal (nom, téléphone, e-mail, adresse, ville) sont facultatifs : un
  champ vide ne génère jamais de balise ni d'entrée Schema vide côté thème. Un projet ajoute ses
  propres réseaux via le filtre `gws_core_settings_fields` plutôt qu'en modifiant ce fichier.

  **Nom BO vs identifiants techniques (Lot 2C)** : dans l'écran d'administration (Réglages), cet
  objet est présenté sous le nom **« Ma structure »** — c'était auparavant « Entité ». Il s'agit
  d'un changement de vocabulaire côté interface UNIQUEMENT : l'option WordPress
  (`gws_core_settings`), le groupe de réglages (`gws_core_settings_group`), le préfixe des
  fonctions (`gws_core_`) et toutes les clés de champ existantes restent strictement inchangés —
  aucune migration, aucune donnée existante affectée.

  **API de marque consolidée (Lot 2C — « Ma structure & identité de marque »)** : « Ma structure »
  est la source canonique d'identité pour le site, une future fiche PDF et un futur Catalogue —
  ces consommateurs ne doivent jamais garder leur propre copie du nom, du logo, des couleurs, de
  la présentation ou des coordonnées. Un futur développement (renderer PDF, page de Catalogue...)
  doit lire ces données via l'API ci-dessous, jamais un nom de meta ou une clé d'option :
  - `gws_core_structure_name()` — nom prêt à afficher, avec repli natif sur le nom du site
    WordPress si le champ « Nom de la structure » est vide.
  - `gws_core_get_logo_id()` / `gws_core_get_logo_url($size)` — logo (déjà existant avant ce
    lot), stocké comme un simple ID de la médiathèque WordPress.
  - `gws_core_get_primary_color()` / `gws_core_get_secondary_color()` — couleur de marque
    EFFECTIVE (`#rrggbb`) : la couleur choisie par la structure si elle est renseignée et valide,
    sinon une couleur GWS par défaut (`gws_core_default_primary_color()` /
    `gws_core_default_secondary_color()`, filtrables). **La couleur par défaut n'est jamais
    écrite automatiquement dans `gws_core_settings`** : ces deux fonctions calculent le repli à
    chaque lecture, sans jamais modifier la donnée enregistrée — un renderer peut donc toujours
    distinguer « couleur choisie par la structure » (via `gws_core_get_setting('primary_color')`,
    vide ou non) de « couleur effectivement à utiliser » (via `gws_core_get_primary_color()`,
    jamais vide).
  - `gws_core_contrast_color($hex_color)` — couleur de texte lisible (`#ffffff` ou `#000000`) sur
    un fond `$hex_color` donné, par luminance relative sRGB (formule de contraste WCAG) —
    volontairement binaire, jamais stockée, toujours recalculée. Utile pour un futur PDF/site qui
    doit poser du texte sur `gws_core_get_primary_color()`/`gws_core_get_secondary_color()`.
  - `gws_core_structure_identity()` — assemble tout ce qui précède (nom, logo, couleurs
    effectives + leur contraste, présentation, coordonnées) en un seul tableau associatif ; c'est
    le point d'entrée à privilégier pour un futur renderer qui a besoin de plusieurs de ces
    valeurs à la fois.
  - Couleurs par défaut retenues (documentées ici comme demandé) : principale `#1d4ed8`,
    secondaire `#0f766e` — reprises du design system du thème (`assets/css/tokens.css`,
    `--color-primary`/`--color-accent`), un bleu et un vert-bleu professionnels et neutres,
    délibérément DISTINCTS du bleu de branding du BO Tagada Vroom (`#03A9F4`, propre à l'éditeur,
    pas au client) et d'un quelconque rose Tagada Vroom.
  - Ce lot ne connecte PAS ces couleurs au design du thème (`assets/css/tokens.css` reste le seul
    fichier à modifier pour l'identité visuelle du thème lui-même) : ce sont des données de marque
    côté client, à la disposition d'un futur thème/PDF/Catalogue qui choisira lui-même comment les
    utiliser (principe : GWS fournit les tokens de marque, le consommateur décide de leur usage).
- `includes/fields.php` — générateur minimal de champs structurés (meta box depuis un schéma),
  volontairement réduit : pas un concurrent d'ACF.
- `includes/security.php` — helpers réutilisables pour sécuriser un formulaire public (nonce,
  pot de miel, délai anti-bot, limite de tentatives par IP).
- `includes/contact-form.php` — traitement du formulaire de contact générique fourni en exemple
  par le thème (`template-parts/forms/contact-form.php`) : e-mail uniquement, rien n'est stocké.
- `includes/seo-meta.php` — champs SEO de secours (titre/description), persistants, indépendants
  du thème actif ; c'est au thème de décider s'il les affiche.
- `includes/migration.php` — cadre générique de migration explicite (sauvegarde, rollback,
  journal), inerte tant qu'aucun module n'y déclare de migration.
- `includes/modules.php` + `config/modules.php` — chargeur de modules métier opt-in.
- `modules/` — modules métier (voir `modules/README.md`).

## Convention de nommage

Fonctions et constantes du cœur du plugin : préfixe `gws_core_`. Chaque module métier a son
propre préfixe, documenté dans `modules/README.md`.

## Dépendance du thème vers ce plugin

Le thème `gws-starter` appelle les fonctions publiques de ce plugin (`gws_core_get_setting()`,
etc.) en les protégeant par `function_exists()`, pour ne jamais provoquer d'erreur fatale si ce
plugin venait à être désactivé par erreur — voir `inc/compat.php` côté thème.

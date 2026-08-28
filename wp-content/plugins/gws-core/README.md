# GWS Core

Édité par [Tagada Vroom](https://tagadavroom.fr/). Plugin compagnon du thème `gws-starter`.
Détient tout ce qui doit **survivre à un changement de thème** : réglages de l'entité, champs
SEO de secours, cadre de migration, et modules métier (CPT, taxonomies, champs structurés,
relations, logique métier persistante).

Ce plugin doit rester actif en permanence sur un site construit avec ce starter, quel que soit
le thème utilisé — y compris si le thème est un jour remplacé.

## Contenu

- `includes/settings.php` — réglages génériques de l'entité (identité, coordonnées, logo,
  réseaux sociaux, crédit de réalisation), lus par le thème via `gws_core_get_setting($key)` et
  quelques helpers dédiés (`gws_core_get_logo_url()`, `gws_core_whatsapp_url()`,
  `gws_core_social_links()`, `gws_core_schema_same_as()`...). Tous les champs au-delà du socle
  minimal (nom, téléphone, e-mail, adresse, ville) sont facultatifs : un champ vide ne génère
  jamais de balise ni d'entrée Schema vide côté thème. Un projet ajoute ses propres réseaux via
  le filtre `gws_core_settings_fields` plutôt qu'en modifiant ce fichier.
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

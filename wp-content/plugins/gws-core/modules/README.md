# Modules métier (gws-core)

Un module de ce dossier détient tout ce qui doit **survivre à un changement de thème** :
types de contenu (CPT), taxonomies, champs structurés, relations, logique métier persistante
(traitement de formulaire, calculs, envoi d'e-mail...).

Il ne détient jamais de gabarit d'affichage : le rendu (HTML, CSS, JS) vit côté thème, dans
`wp-content/themes/gws-starter/modules/<même-slug>/`. Les deux dossiers portant le même nom
sont le module complet — l'un fournit les données, l'autre l'affichage.

## Activer un module

Ajouter son slug (nom du dossier) au tableau retourné par `config/modules.php`. Rien d'autre :
aucun fichier du cœur n'a besoin d'être modifié.

## Créer un nouveau module métier

1. Dupliquer `modules/_boilerplate-cpt/` et le renommer.
2. Remplacer partout le préfixe `bp_` par un préfixe court et unique au module (ex. `elv_` pour
   un élevage). Consigner ce préfixe dans le tableau ci-dessous pour que tout développeur qui
   reprend le projet voie d'un coup d'œil ce qui appartient à quel module.
3. Ajuster le CPT, la taxonomie, le schéma de champs et les relations à votre métier réel.
4. Créer le dossier miroir côté thème (`wp-content/themes/gws-starter/modules/<slug>/`) avec au
   minimum `single-{post_type}.php` et `archive-{post_type}.php` — WordPress les détecte
   nativement s'ils sont copiés à la racine du thème (voir le README du dossier thème).
5. Ajouter le slug à `config/modules.php`.

## Registre des préfixes utilisés sur ce projet

| Module | Préfixe | Post type(s) |
|---|---|---|
| `_boilerplate-cpt` (squelette, jamais actif) | `bp_` | `bp_item` |
| `diagnostic` | `gws_diag_` | — (aucun CPT, formulaire → e-mail uniquement) |
| `guides` | `gws_guides_` | `page` (gabarit dédié) |

Tenir ce tableau à jour à chaque nouveau module évite toute collision de préfixe/meta entre
deux modules d'un même projet.

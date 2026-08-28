# Squelette de module CPT — à dupliquer

Ce dossier n'est jamais chargé (il ne peut de toute façon pas l'être : il ne figure pas dans
`config/modules.php`). Il sert uniquement de point de départ pour un nouveau module métier basé
sur un Custom Post Type.

## Ce qu'il démontre

- Un CPT (`bp_item`) avec une taxonomie associée.
- Des champs structurés simples via le générateur natif de `gws-core` (`includes/fields.php`) :
  aucune dépendance à ACF.
- Une relation simple entre deux fiches du même CPT (ex. « père »/« mère » pour une fiche
  Cheval), traitée par un `<select>` peuplé des autres fiches existantes — suffisant pour une
  relation 1-vers-1 ou 1-vers-2 sans complexité supplémentaire.

## Exemple concret : une fiche « Cheval »

1. Copier `_boilerplate-cpt/` vers `modules/elevage-chevaux/`.
2. Remplacer `bp_` par `elv_`, `BP_POST_TYPE`/`bp_item` par `elv_horse`, `BP_TAXONOMY` par
   `elv_horse_breed` (ou la racée pertinente).
3. Adapter `bp_field_schema()` : robe, date de naissance, numéro SIRE, discipline...
4. Renommer les deux relations génériques `_bp_parent_a`/`_bp_parent_b` en `_elv_sire`/`_elv_dam`
   (père/mère), et leurs libellés dans `bp_render_relation_meta_box()`.
5. Créer `wp-content/themes/gws-starter/modules/elevage-chevaux/templates/single-elv_horse.php`
   et `archive-elv_horse.php` pour l'affichage (fiche cheval, listing des chevaux) — aucune copie
   vers la racine du thème n'est nécessaire, ils sont détectés automatiquement dès que le module
   est actif (voir `inc/module-templates.php` côté thème).
6. Ajouter `'elevage-chevaux'` à `config/modules.php`.

## Ce qu'il ne faut pas faire

- Ne pas ajouter de logique d'affichage ici (HTML, CSS, enqueue) : ça appartient au thème.
- Ne pas essayer de reproduire un repeater riche ou une galerie avec le générateur de champs
  minimal de `gws-core` — au-delà d'un champ simple, évaluer projet par projet une extension
  éprouvée (ex. ACF) ou du code dédié.

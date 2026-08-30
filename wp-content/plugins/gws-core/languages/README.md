# Traductions — GWS Core

Toutes les chaînes d'interface du cœur **et** de ses modules métier (dont `gws-equestrian`)
utilisent le text domain unique `gws-core` — ce sont des sous-fonctionnalités d'un seul plugin,
pas des plugins distincts, donc un seul domaine de traduction pour l'ensemble (voir l'appel à
`load_plugin_textdomain()` dans `gws-core.php`).

## Ajouter une traduction

1. Générer un fichier `.pot` à jour (catalogue des chaînes) avec WP-CLI :
   ```
   wp i18n make-pot . languages/gws-core.pot --domain=gws-core
   ```
2. Traduire ce `.pot` (avec Poedit, Loco Translate ou tout autre outil gettext) vers un fichier
   `.po` nommé selon la locale cible, par exemple `gws-core-en_GB.po` pour l'anglais britannique.
3. Compiler le `.po` en `.mo` du même nom (`gws-core-en_GB.mo`) et placer les deux fichiers dans
   ce dossier `languages/`.
4. WordPress charge automatiquement la traduction correspondant à la langue du site (ou de
   l'utilisateur connecté pour l'admin) dès qu'un fichier `.mo` portant le bon nom est présent ici
   — aucune configuration supplémentaire.

## Ce qui est traduisible

Les libellés, options, messages d'aide et textes de rendu **du logiciel** (menus, champs,
résumés de tarif, modèles de prestations proposés...). Jamais le contenu saisi par un
professionnel sur son site (noms de prestations, descriptions, groupes tarifaires, libellés
personnalisés) — ce sont des données du site, pas des chaînes du plugin, et elles ne passent par
aucune fonction de traduction.

## Terminologie fiscale (HT/TTC) et devise

Les valeurs techniques stockées (`ht`, `ttc`) sont indépendantes de la devise choisie (EUR, GBP,
USD, CHF...) et de la langue du site : une devise ne détermine jamais une langue, et
réciproquement. Une traduction anglaise peut par exemple restituer « HT »/« TTC » par
« excl. VAT »/« incl. VAT » sans qu'aucun code ne fasse ce lien automatiquement — c'est
uniquement la traduction des chaînes `HT` et `TTC` (text domain `gws-core`) qui détermine ce
rendu, jamais la devise sélectionnée.

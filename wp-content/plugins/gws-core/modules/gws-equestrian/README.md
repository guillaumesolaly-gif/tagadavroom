# GWS Equestrian (gws-core)

Module métier pour les professionnels du monde équestre (pension, enseignement, élevage,
reproduction, vente) : gestion des prestations/tarifs et des fiches chevaux. Voir le pendant
présentation dans `wp-content/themes/gws-starter/modules/gws-equestrian/`.

**Préfixe du module : `gwseq_`** (jamais `gws_` ni `gws_core_`, réservés au cœur — voir
`modules/README.md` et `AI-AGENT.md` §3). Consigné dans le registre de `modules/README.md`.

## État actuel : Étape 2 — Composant répétable (Étape 1 validée)

L'Étape 1 (fondations) a été recettée en conditions réelles et validée. L'Étape 2 ajoute une
brique technique interne — le composant répétable — sans toucher aux fondations ni construire
encore de fonctionnalité métier. Voir `CHANGELOG.md` de ce dossier pour l'historique détaillé par
étape, et la proposition de conception validée (modèle fonctionnel, interface, architecture,
complexité, périmètre V1, arbitrages, plan de développement, stratégie de tests) pour le contexte
d'ensemble.

### Composant répétable (Étape 2)

`includes/repeater-field.php` fournit la plus petite abstraction utile pour gérer une liste
ordonnée de lignes structurées (futurs indices de performance, URLs de vidéos, blocs éditoriaux
personnalisés) sans réécrire trois fois la même mécanique — **ce n'est pas un mini-ACF** : pas de
champs imbriqués, pas de types hypothétiques, pas d'exposition REST, pas de registre de types
extensible. Voir l'en-tête de ce fichier pour la documentation complète (comment déclarer une
structure, l'afficher, la sauvegarder, récupérer ses données). Démonstration neutre visible
uniquement en environnement local/développement : `includes/qa-repeater.php` (voir sa procédure
de recette ci-dessous).

Types de colonnes supportés : `text`, `textarea`, `number`, `integer`, `url` — les seules
primitives déjà nécessaires aux besoins identifiés par la conception validée. Stockage : une
seule meta WordPress par structure répétable, valeur = tableau indexé de lignes (chaque ligne un
tableau associatif clé de colonne => valeur sanitizée), lu et écrit avec les fonctions natives
`get_post_meta()`/`update_post_meta()` — aucune fonction de lecture dédiée n'est nécessaire, donc
aucune dépendance à ce fichier pour un futur consommateur (rendu front, export PDF).

### Procédure de recette — Étape 2 (composant répétable)

À réaliser dans WordPress Local, sans écrire de code :

1. Sur un environnement dont le type est `local` ou `development`
   (`wp_get_environment_type()`), un nouveau menu **QA — Répétable (Equestrian)** apparaît dans
   l'administration dès que le module `gws-equestrian` est actif. **Il ne doit apparaître ni
   exister en production.**
2. Ajouter un élément de test : la meta box « Composant répétable — démonstration (Libellé /
   Valeur / Année) » doit s'afficher, initialement vide.
3. Cliquer sur « + Ajouter une ligne » : une ligne de champs doit apparaître, le focus doit se
   poser sur son premier champ.
4. Remplir cette ligne, cliquer une seconde fois sur « + Ajouter une ligne » pour une deuxième
   ligne, la remplir avec des valeurs différentes (tester en particulier `0` dans le champ
   Valeur, et des caractères spéciaux — apostrophe, accents, `&` — dans le champ Libellé).
   Publier ou mettre à jour.
5. Recharger la page d'édition : les deux lignes et leurs valeurs doivent être restituées à
   l'identique, dans le même ordre, y compris le `0` et les caractères spéciaux.
6. Supprimer une des deux lignes via son bouton « Supprimer », enregistrer, recharger : la ligne
   supprimée ne doit plus jamais réapparaître ; l'autre reste intacte.
7. Vérifier la console navigateur sur cet écran : aucune erreur JavaScript.
8. Vérifier qu'aucun fichier CSS/JS de ce composant n'est chargé sur un autre écran WordPress
   sans rapport (Tableau de bord, Articles, une Prestation, un Cheval...) — inspecter l'onglet
   réseau du navigateur.
9. Vérifier qu'aucun élément de test créé ici n'apparaît nulle part sur le site public (le CPT de
   démonstration n'est jamais public).
10. Supprimer les éléments de test créés une fois la recette terminée (aucune suppression
    automatique n'est effectuée par le module).

### Rappel — ce que l'Étape 1 a construit (toujours valide, non modifié depuis)

- Trois Custom Post Types enregistrés : `gwseq_prestation`, `gwseq_groupe` (Groupe tarifaire),
  `gwseq_cheval`.
- Une taxonomie : `gwseq_categorie_cheval`, attachée à `gwseq_cheval`.
- Aucun champ structuré, aucune relation, aucune logique métier, aucun écran d'administration
  dédié : les écrans natifs de WordPress (titre / contenu / image à la une pour Prestation et
  Cheval ; titre seul pour Groupe tarifaire) sont utilisés tels quels.
- Aucun gabarit front dédié : voir le README du dossier thème miroir.

### Décisions de conception déjà actées à ce stade

- **Groupe tarifaire n'est jamais public** (`public` => false, pas d'archive, pas de rewrite,
  exclu de la recherche) : c'est un objet d'organisation interne pour le classement et
  l'affichage des tarifs (étapes 3 et 8), pas une page éditoriale en soi. Éviter une URL sans
  contenu réel.
- **Catégorie métier et groupe tarifaire sont fusionnés** : une Prestation appartient à un seul
  Groupe tarifaire, qui sert à la fois de classement et de regroupement tarifaire. Aucune
  taxonomie de « catégorie métier » distincte n'est prévue.
- **Longueur des identifiants technique vérifiée** : WordPress limite un nom de post type à 20
  caractères. `gwseq_groupe` (et non `gwseq_groupe_tarifaire`, qui dépasserait cette limite) a
  été choisi pour cette raison — voir le test associé, qui verrouille cette contrainte.
- La taxonomie `gwseq_categorie_cheval` utilise pour l'instant l'interface WordPress native
  « étiquettes » (saisie libre). Le remplacement par une interface à cases à cocher (retenu dans
  la conception validée pour un usage multi-valeurs) est différé à l'étape 4, avec le reste du
  formulaire d'édition de la fiche cheval.

### Ce qui n'est délibérément PAS encore construit

Conformément au périmètre strict fixé étape par étape : formulaire complet de Prestation,
modèles de prestations préconfigurées, assistant de première configuration, glisser-déposer,
moteur de tarification, formulaire complet de fiche cheval, indices/vidéos/blocs personnalisés
**métier réels** (le composant répétable qui les portera existe depuis l'Étape 2, mais aucune de
ces données métier n'est encore créée), pedigree/relations, duplication, export PDF, rendu front
définitif. Ces éléments arrivent aux étapes 3 à 9 du plan de développement validé, chacune
soumise à validation avant la suivante.

### Point ouvert à trancher avant l'étape 4

La photo de couverture d'un cheval pourrait réutiliser l'image à la une native de WordPress
(déjà activée via `supports => array(..., 'thumbnail')`) plutôt qu'un champ `attachment_id`
dédié comme envisagé dans la proposition de conception initiale — les deux mécanismes sont
natifs et sans dépendance, mais l'image à la une évite un champ redondant. À arbitrer au moment
de construire le formulaire d'identité du cheval (étape 4), pas avant.

## Activer ce module (pour tester cette étape)

Comme tout module métier GWS, l'activation se fait uniquement via
`wp-content/plugins/gws-core/config/modules.php` — jamais par défaut dans le starter :

```php
return array('gws-equestrian');
```

Aucun autre fichier du cœur n'a besoin d'être modifié. Désactiver le module (retirer le slug du
tableau) ne supprime aucune donnée déjà créée — les fiches restent en base, simplement
inaccessibles tant que le module n'est pas réactivé (voir `AI-AGENT.md`, interdiction n°12).

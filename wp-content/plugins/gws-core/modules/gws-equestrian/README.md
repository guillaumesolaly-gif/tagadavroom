# GWS Equestrian (gws-core)

Module métier pour les professionnels du monde équestre (pension, enseignement, élevage,
reproduction, vente) : gestion des prestations/tarifs et des fiches chevaux. Voir le pendant
présentation dans `wp-content/themes/gws-starter/modules/gws-equestrian/`.

**Préfixe du module : `gwseq_`** (jamais `gws_` ni `gws_core_`, réservés au cœur — voir
`modules/README.md` et `AI-AGENT.md` §3). Consigné dans le registre de `modules/README.md`.

## État actuel : Étape 3 — Prestations / Groupes tarifaires (Étapes 1 et 2 validées)

Les Étapes 1 (fondations) et 2 (composant répétable) ont été recettées en conditions réelles et
validées. L'Étape 3 construit la gestion métier réelle des Prestations et des Groupes tarifaires,
en attente de sa propre recette runtime. Voir `CHANGELOG.md` de ce dossier pour l'historique
détaillé par étape, et la proposition de conception validée pour le contexte d'ensemble.

### Prestations et Groupes tarifaires (Étape 3)

**Groupe tarifaire** : Nom (titre natif), Ordre (menu_order natif, meta box « Ordre
d'affichage »), Description courte (extrait natif, meta box « Description courte ») — aucune
meta custom, WordPress gère nativement la sauvegarde des trois champs.

**Prestation** : Nom (titre) et Description (éditeur natif) inchangés depuis l'Étape 1. Ajoutés
à cette étape :
- **Groupe tarifaire** : sélecteur dans la colonne latérale, référence par ID de post (jamais par
  nom — renommer un groupe ne casse jamais les prestations qui lui sont rattachées).
- **Tarification** (meta box dédiée) : mode Prix unique / Cheval-Poney (deux prix distincts) /
  Sur devis, unité parmi une liste fermée (+ « Autre » avec libellé personnalisé), case « Afficher
  ce tarif publiquement » pour un prix interne non diffusé sans multiplier les états incohérents.
  Aucun prix formaté n'est stocké : chaque composant (montant, unité, visibilité) est une donnée
  séparée, assemblée uniquement au moment du rendu (admin, puis web/API/PDF plus tard).
- **Ordre d'affichage** : identique au Groupe (menu_order natif).
- **Statut** : Brouillon/Publié natifs WordPress — aucun second système Actif/Inactif.
- **Modèles de prestations** : bouton « Partir d'un modèle » sur l'écran d'ajout, organisé par
  famille (Pension/Travail/Cours/Élevage/Reproduction/Autres). Un modèle ne fait que préremplir le
  formulaire ; la prestation créée est immédiatement indépendante, modifiable et supprimable
  librement, jamais réécrite par une évolution future de la liste de modèles.
- **Réglage global HT/TTC** (Prestations > Réglages) : indique uniquement la nature des montants
  déjà saisis, aucun calcul de TVA.

### Arbitrages techniques de l'Étape 3

- **Catégorie métier et groupe tarifaire restent fusionnés** (décision de l'Étape 1 confirmée) :
  une Prestation n'appartient qu'à un seul `gwseq_groupe`.
- **Pas de glisser-déposer** pour l'ordre des groupes/prestations en V1 : champ numérique natif
  (`menu_order`) uniquement, listes d'administration triées par cet ordre par défaut. Priorité à
  la robustesse plutôt qu'au confort visuel, conformément à la demande — pourra être ajouté plus
  tard sans changement de modèle de données (l'ordre est déjà la seule donnée qui compte).
  Aucune donnée créée automatiquement.
- **Aucun QA dédié pour cette étape** : contrairement au composant répétable de l'Étape 2 (brique
  technique neutre nécessitant un jeu de démonstration), Prestations et Groupes tarifaires sont
  déjà les écrans métier réels — la recette utilise directement les menus **Prestations** et
  **Groupes tarifaires**, sans CPT ni contenu de test superflu.
- **Aucun risque de regroupement de lignes** (anomalie corrigée à l'Étape 2) : tous les nouveaux
  champs sont des champs simples à nom HTML fixe, jamais indexés — ce risque ne concerne que les
  structures répétables.

### Procédure de recette — Étape 3

À réaliser dans WordPress Local, sans écrire de code :

1. Menu **Groupes tarifaires** > Ajouter : créer « Pensions » avec une description courte et
   valider qu'il apparaît dans la liste avec son ordre. Créer un second groupe « Travail »,
   modifier son ordre pour qu'il apparaisse avant ou après « Pensions » dans la liste.
2. Menu **Prestations** > Ajouter une prestation : vérifier que le bloc « Partir d'un modèle »
   apparaît, choisir un modèle de la famille Pension (ex. « Pension pré avec infrastructures ») et
   cliquer sur « Préremplir depuis ce modèle » — le titre doit se remplir automatiquement.
3. Choisir le groupe « Pensions », mode « Prix unique », prix `45.50`, unité « Séance ». Publier.
   Vérifier dans la liste des Prestations que les colonnes Groupe tarifaire/Tarif/Ordre
   affichent les bonnes valeurs (« 45,50 € TTC / Séance » avec le réglage par défaut).
4. Modifier cette prestation : changer le mode en « Cheval / Poney », renseigner deux prix
   distincts, enregistrer, recharger — vérifier que les deux prix sont bien restitués séparément
   et que le tarif affiché dans la liste combine les deux.
5. Décocher « Afficher ce tarif publiquement », enregistrer : la colonne Tarif doit afficher
   « Tarif non affiché publiquement ».
6. Créer une seconde prestation en mode « Sur devis » : vérifier qu'aucun champ de prix n'est
   requis et que la colonne Tarif affiche « Sur devis ».
7. Choisir l'unité « Autre » sur une prestation, vérifier que le champ « Préciser l'unité »
   apparaît, le remplir (ex. « par cycle »), enregistrer, recharger.
8. Renommer le groupe « Pensions » en « Nos pensions » : vérifier que la prestation qui lui est
   rattachée continue d'afficher le bon groupe (donc que la relation n'a pas été cassée).
9. Aller dans **Prestations > Réglages**, basculer sur HT, enregistrer, revenir à la liste des
   Prestations : vérifier que les tarifs affichent désormais « HT » au lieu de « TTC ».
10. Passer une prestation en Brouillon : vérifier qu'aucun deuxième champ « Actif/Inactif » n'est
    présent, que le statut natif WordPress suffit.
11. Vérifier la console navigateur sur les écrans Prestation : aucune erreur JavaScript ; les
    champs de tarification s'affichent/masquent correctement selon le mode et l'unité choisis.
12. Vérifier qu'aucun asset du module (`prestation-admin.js`, `presets-admin.js`) n'est chargé
    sur un écran sans rapport (Tableau de bord, Articles, un Groupe tarifaire, un Cheval).
13. Vérifier qu'aucune prestation ni aucun groupe n'a été créé automatiquement par la simple
    activation ou mise à jour du module (liste vide sur un site qui n'en a pas créé lui-même).

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

À réaliser dans WordPress Local, sans écrire de code. Mise à jour suite aux deux anomalies
relevées lors de la première recette (0.2.0 → 0.2.1, voir `CHANGELOG.md`) : les étapes 4 et 5
ci-dessous ciblent explicitement les deux cas qui avaient échoué.

1. Sur un environnement dont le type est `local` ou `development`
   (`wp_get_environment_type()`), un nouveau menu **QA — Répétable (Equestrian)** apparaît dans
   l'administration dès que le module `gws-equestrian` est actif. **Il ne doit apparaître ni
   exister en production.**
2. Ajouter un élément de test : la meta box « Composant répétable — démonstration (Libellé /
   Valeur / Année) » doit s'afficher, initialement vide.
3. Cliquer sur « + Ajouter une ligne » : une ligne de champs doit apparaître, le focus doit se
   poser sur son premier champ.
4. Remplir cette ligne avec `ISO` / `125.5` / `2025` : le champ Valeur doit accepter la décimale
   `125.5` sans blocage du navigateur (anomalie n°1 corrigée). Cliquer une seconde fois sur
   « + Ajouter une ligne » pour une deuxième ligne, la remplir avec `ICC` / `130` / `2026`, puis
   une troisième avec `IDR` / `0` / `2024` (tester en particulier `0` dans le champ Valeur, et
   des caractères spéciaux — apostrophe, accents, `&` — dans un champ Libellé). Publier ou
   mettre à jour.
5. Recharger la page d'édition : les **trois lignes doivent être restituées exactement comme
   saisies, chacune avec ses trois valeurs regroupées sur sa propre ligne** — `ISO`/`125.5`/`2025`,
   `ICC`/`130`/`2026`, `IDR`/`0`/`2024`, dans cet ordre. Vérifier explicitement qu'aucune valeur
   n'a été déplacée sur une ligne différente et qu'aucune ligne supplémentaire n'est apparue
   (anomalie n°2 corrigée).
6. Supprimer la ligne du milieu via son bouton « Supprimer », enregistrer, recharger : elle ne
   doit plus jamais réapparaître ; les deux autres restent intactes et dans leur ordre respectif.
7. Ajouter à nouveau deux nouvelles lignes à la suite : vérifier qu'elles s'enregistrent
   correctement l'une et l'autre (pas de collision d'index entre lignes ajoutées dans la même
   session d'édition).
8. Vérifier la console navigateur sur cet écran : aucune erreur JavaScript.
9. Vérifier qu'aucun fichier CSS/JS de ce composant n'est chargé sur un autre écran WordPress
   sans rapport (Tableau de bord, Articles, une Prestation, un Cheval...) — inspecter l'onglet
   réseau du navigateur.
10. Vérifier qu'aucun élément de test créé ici n'apparaît nulle part sur le site public (le CPT
    de démonstration n'est jamais public).
11. Supprimer les éléments de test créés une fois la recette terminée (aucune suppression
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

# GWS Equestrian (gws-core)

Module métier pour les professionnels du monde équestre (pension, enseignement, élevage,
reproduction, vente) : gestion des prestations/tarifs et des fiches chevaux. Voir le pendant
présentation dans `wp-content/themes/gws-starter/modules/gws-equestrian/`.

**Préfixe du module : `gwseq_`** (jamais `gws_` ni `gws_core_`, réservés au cœur — voir
`modules/README.md` et `AI-AGENT.md` §3). Consigné dans le registre de `modules/README.md`.

## État actuel : Étape 1 — Fondations uniquement

Cette étape ne prouve que l'intégration technique du module dans GWS, avant tout développement
métier. Elle fait suite à une proposition de conception complète (modèle fonctionnel, interface,
architecture, complexité, périmètre V1, arbitrages, plan de développement, stratégie de tests),
validée point par point, dont les décisions structurantes ci-dessous découlent directement.

### Ce qui est réellement construit à cette étape

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

### Ce qui n'est délibérément PAS construit à cette étape

Conformément au périmètre strict fixé pour l'étape 1 : formulaire complet de Prestation, modèles
de prestations préconfigurées, assistant de première configuration, glisser-déposer, moteur de
tarification, composant de champ répétable, formulaire complet de fiche cheval, indices,
galerie, vidéos, pedigree/relations, duplication, blocs personnalisés, export PDF, rendu front
définitif. Ces éléments arrivent aux étapes 2 à 9 du plan de développement validé, chacune
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

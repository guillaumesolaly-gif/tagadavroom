# Changelog — GWS Starter

## 1.3.0

Corrections issues d'une revue indépendante de la v1.2.0, avant recette fonctionnelle réelle.

- **Priorité des gabarits single/archive de module corrigée.** L'ancienne implémentation
  filtrait `single_template`/`archive_template` (le résultat déjà tranché par WordPress), qui
  n'est jamais vide puisque le thème fournit toujours `single.php`/`archive.php` en filet de
  sécurité : un gabarit de module n'était donc en réalité jamais utilisé. Remplacé par une
  intervention sur la hiérarchie elle-même (`single_template_hierarchy`/
  `archive_template_hierarchy`), qui respecte l'ordre voulu : gabarit spécifique au projet >
  gabarit du module actif > fallback générique. Voir `tests/starter-logic-test.php`.
- **Modale : gestion de `inert` rendue défensive.** La fermeture de la modale ne retire plus
  jamais un `inert` déjà présent sur un élément avant l'ouverture — seuls les éléments que la
  modale a elle-même rendus inertes sont mémorisés puis restaurés.
- **Module QA : bascule de développement sans édition de fichier.** Nouvel écran
  **Outils > Recette GWS** (visible uniquement si `wp_get_environment_type()` vaut `local` ou
  `development`, jamais en production), protégé par nonce et par la capability
  `manage_options`. Active/désactive uniquement le module QA, via une option séparée
  (`gws_core_qa_dev_enabled`) simplement ajoutée à la liste des modules actifs déjà calculée par
  `gws_core_active_modules()` — `config/modules.php` reste l'unique source de configuration
  versionnée des modules métier réels. Ne supprime jamais de contenu.
- Le mécanisme de flush automatique des permaliens (`includes/modules.php`) n'a subi aucune
  modification de sa logique propre (détection de changement, drapeau, flush tardif sur
  `init`) : seule la fonction `gws_core_active_modules()` gagne une ligne pour tenir compte de
  la bascule QA.
- Ajout de `tests/` : scripts PHP autonomes (sans WordPress) couvrant la priorité de gabarits et
  la bascule QA — non inclus dans les paquets installables.

## 1.2.0

- Modale générique mise aux standards d'accessibilité : `role="dialog"`/`aria-modal`/
  `aria-labelledby` attendus dans le balisage (documentés dans `assets/css/components.css`),
  vrai focus trap limité aux éléments réellement visibles, restitution du focus au déclencheur
  à la fermeture, et isolement du contenu d'arrière-plan (`inert`) pendant l'ouverture.
- Les gabarits fournis par un module (page template, single, archive) restent désormais
  physiquement dans `modules/<slug>/` : plus aucune copie manuelle de fichier vers la racine du
  thème n'est nécessaire pour les activer, ni à supprimer pour les retirer. Nouveau fichier
  générique `inc/module-templates.php`, qui s'appuie sur les filtres natifs de WordPress
  (`theme_page_templates`, `page_template`, `single_template`, `archive_template`) et sur la
  liste des modules actifs déclarée côté plugin. Le mécanisme de flush automatique des
  permaliens n'a pas changé.
- Modules `diagnostic`, `guides` et `qa` mis à jour pour ce nouveau fonctionnement (chemins de
  gabarit virtuels, documentation) ; le module QA sert de preuve de fonctionnement, y compris
  pour le focus trap de sa modale de démonstration.

## 1.1.0

- Ajout du module `qa` (développement uniquement, désactivé par défaut) : recette du design
  system et des composants génériques, CPT jetable pour vérifier champs structurés/archive/
  single/persistance au changement de thème.
- Ajout du flush automatique des permaliens à l'activation/désactivation d'un module métier
  (`includes/modules.php`) — plus aucune étape manuelle dans Réglages > Permaliens.
- Comportement clavier de la modale générique complété (fermeture par Échap, focus posé à
  l'ouverture).
- Correction de la documentation : les pages classiques n'ont jamais eu besoin d'un flush
  manuel des permaliens après activation du thème (aucun CPT/règle de réécriture n'y est
  déclaré) — la checklist de mise en production a été mise à jour en conséquence.

## 1.0.0

Première version du starter, issue de la transformation d'un thème WordPress sur mesure en base
technique générique. Voir `ARCHITECTURE.md` pour le détail des choix structurants.

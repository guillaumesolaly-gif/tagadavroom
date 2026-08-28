# Tests de logique — GWS Starter

Scripts PHP autonomes (aucune installation WordPress requise), qui stubent le strict minimum
d'API WordPress pour charger les vrais fichiers du starter et vérifier leur logique pure :
manipulation de la hiérarchie de gabarits, détection d'environnement, lecture d'options.

Ce dossier n'est pas inclus dans `gws-core.zip` ni `gws-starter.zip`.

## Exécuter

```
php tests/starter-logic-test.php
```

(`tests/qa-toggle-logic-test.php` est appelé automatiquement par le script ci-dessus, dans un
processus PHP séparé — il peut aussi être lancé seul.)

## Ce qui est couvert

- Priorité gabarit projet > gabarit de module > fallback générique pour `single`/`archive` de
  CPT (`inc/module-templates.php`), avec simulation fidèle de l'algorithme de `locate_template()`
  et création/suppression réelle d'un fichier de test.
- Bascule de développement du module QA (`includes/modules.php`) : ignorée en production,
  effective en local/développement, jamais de doublon avec `config/modules.php`.

## Ce qui n'est PAS couvert ici (à vérifier dans un vrai WordPress)

- Rendu HTML réel des gabarits, comportement de l'éditeur (sélecteur de gabarit de page).
- Comportement navigateur de la modale : focus trap, `inert`, restitution du focus, fermeture
  au clavier — voir la procédure QA pour les étapes de vérification manuelle précises.
- Écran d'administration **Outils > Recette GWS** (rendu, nonce, capability) en conditions
  réelles.
- Flush effectif des permaliens et fonctionnement réel de l'archive `/qa-items/` dans WordPress.

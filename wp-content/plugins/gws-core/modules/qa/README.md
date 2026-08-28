# Module QA (recette technique) — développement uniquement

**Ne jamais activer ce module sur un site de production.** Il ne contient aucun code métier et
aucun contenu propre à un secteur : il sert uniquement à vérifier, sur un WordPress vierge, que
les briques génériques du starter fonctionnent avant de démarrer un vrai projet.

## Ce qu'il crée une fois activé

- Un Custom Post Type jetable `gws_qa_item` (« QA — Éléments de test »), avec 3 champs
  structurés de types différents (texte, zone de texte, case à cocher) et sa propre règle de
  réécriture (`/qa-items/...`) — sert à vérifier concrètement le flush automatique des
  permaliens (voir `includes/modules.php`).
- Une page « QA — Recette du design system (à supprimer) », créée une seule fois (insert-only),
  qui affiche tous les composants génériques du thème dès que le gabarit du module (resté dans
  `wp-content/themes/gws-starter/modules/qa/`, aucune copie nécessaire) est disponible.

## Activer

1. Ajouter `'qa'` à `config/modules.php`.
2. Recharger n'importe quelle page de l'admin : les gabarits du module deviennent disponibles et
   le flush des permaliens se fait tout seul, sans passer par Réglages > Permaliens.

## Retirer intégralement (avant un site réel)

1. Dans **QA — Éléments de test** (menu admin), sélectionner tous les éléments de test créés
   pendant la recette, les mettre à la corbeille, puis vider la corbeille (suppression
   définitive, via l'écran natif de WordPress — aucun outil dédié n'est nécessaire).
2. Faire de même avec la page **QA — Recette du design system** dans **Pages**.
3. Retirer `'qa'` de `config/modules.php`.
4. Recharger une page de l'admin : le flush des permaliens se refait automatiquement, cette fois
   sans les règles du CPT QA, et les gabarits du module disparaissent du sélecteur — sans aucun
   fichier à toucher côté thème.
5. Si ce module ne sera plus jamais utilisé sur ce projet, supprimer entièrement ce dossier
   (`modules/qa/`) des deux côtés (plugin et thème).

Aucune étape ci-dessus n'est automatique ou silencieuse : conforme au principe du starter de
n'écrire ou supprimer du contenu que sur geste explicite.

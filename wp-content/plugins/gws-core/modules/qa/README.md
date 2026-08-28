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

## Activer / désactiver

Sur un environnement dont `wp_get_environment_type()` retourne `local` ou `development` (le cas
par défaut d'une installation Local), un écran **Outils > Recette GWS** permet d'activer ou de
désactiver le module QA en un clic, sans toucher à aucun fichier — voir
`includes/admin/qa-tool-page.php`. Le flush des permaliens se fait tout seul dès la requête
suivante (même mécanisme que pour `config/modules.php`, inchangé).

Cet écran n'apparaît jamais en dehors d'un environnement local/développement, et son
option (`gws_core_qa_dev_enabled`) est complètement distincte de `config/modules.php`, qui
reste l'unique source de vérité versionnée pour les modules métier réels d'un projet.

Alternative sans admin (ex. environnement où `wp_get_environment_type()` ne renvoie pas
`local`/`development`) : ajouter `'qa'` directement à `config/modules.php`, comme n'importe quel
autre module.

## Retirer intégralement (avant un site réel)

1. Dans **QA — Éléments de test** (menu admin), sélectionner tous les éléments de test créés
   pendant la recette, les mettre à la corbeille, puis vider la corbeille (suppression
   définitive, via l'écran natif de WordPress — aucun outil dédié n'est nécessaire).
2. Faire de même avec la page **QA — Recette du design system** dans **Pages**.
3. Désactiver QA (bouton **Outils > Recette GWS**, ou retirer `'qa'` de `config/modules.php`
   selon la méthode utilisée pour l'activer).
4. Recharger une page de l'admin : le flush des permaliens se refait automatiquement, cette fois
   sans les règles du CPT QA, et les gabarits du module disparaissent du sélecteur — sans aucun
   fichier à toucher côté thème.
5. Si ce module ne sera plus jamais utilisé sur ce projet, supprimer entièrement ce dossier
   (`modules/qa/`) des deux côtés (plugin et thème).

Aucune étape ci-dessus n'est automatique ou silencieuse : conforme au principe du starter de
n'écrire ou supprimer du contenu que sur geste explicite. Désactiver QA (par l'un ou l'autre
moyen) ne supprime jamais le contenu de test déjà créé — les étapes 1 et 2 restent volontairement
manuelles.

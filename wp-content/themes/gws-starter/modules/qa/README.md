# Module QA — présentation (thème)

Pendant du module `qa` de `gws-core` (données/logique). **Développement uniquement, jamais sur
un site de production.**

## Activer l'affichage

1. Copier `page-templates/qa.php` de ce dossier vers `page-templates/qa.php` à la racine du
   thème (créer le dossier `page-templates/` à la racine s'il n'existe pas encore).
2. Copier `templates/single-gws_qa_item.php` et `templates/archive-gws_qa_item.php` de ce
   dossier vers la racine du thème (mêmes noms de fichiers, sans le sous-dossier `templates/`).
3. Les fichiers `assets/qa.css` restent référencés depuis ce dossier de module — inutile de le
   déplacer.
4. Activer `'qa'` dans `config/modules.php` du plugin : la page de recette est créée
   automatiquement (voir son README).

## Retirer l'affichage

Supprimer les 3 fichiers `.php` copiés à l'étape ci-dessus (`page-templates/qa.php`,
`single-gws_qa_item.php`, `archive-gws_qa_item.php`) à la racine du thème. Voir le README du
module côté plugin pour l'ordre complet des étapes (y compris le nettoyage du contenu de test).

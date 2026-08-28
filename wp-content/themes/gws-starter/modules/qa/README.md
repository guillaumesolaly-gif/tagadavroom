# Module QA — présentation (thème)

Pendant du module `qa` de `gws-core` (données/logique). **Développement uniquement, jamais sur
un site de production.**

## Activer l'affichage

Aucune copie de fichier. Ajouter `'qa'` à `config/modules.php` côté plugin suffit :

- le gabarit de page « QA — Recette (dev uniquement) » (`page-templates/qa.php`, resté dans ce
  dossier) apparaît dans le sélecteur de gabarit, et la page de recette est créée
  automatiquement ;
- les gabarits `templates/single-gws_qa_item.php` et `templates/archive-gws_qa_item.php`
  s'activent automatiquement pour le CPT de test dès qu'il est enregistré côté plugin.

Les fichiers `assets/qa.css` restent eux aussi référencés depuis ce dossier de module.

## Retirer

Retirer `'qa'` de `config/modules.php` : les trois gabarits disparaissent du sélecteur et de la
hiérarchie de gabarits dès la requête suivante, sans rien à supprimer côté thème. Voir le README
du module côté plugin pour l'ordre complet des étapes (y compris le nettoyage du contenu de
test, indépendant de cette étape).

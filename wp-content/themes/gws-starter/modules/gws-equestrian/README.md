# GWS Equestrian (gws-starter)

Pendant présentation du module métier défini dans
`wp-content/plugins/gws-core/modules/gws-equestrian/`. Voir son README pour l'état d'avancement
du module (actuellement : Étape 1 — Fondations uniquement).

## État actuel

Aucun gabarit n'est fourni par ce dossier à ce stade : `gwseq_prestation` et `gwseq_cheval`
utilisent donc automatiquement les gabarits génériques du thème (`single.php`/`archive.php`),
via le mécanisme déjà en place et inchangé (`inc/module-templates.php`) — aucune modification du
cœur du thème n'a été nécessaire pour cette étape, et aucune ne le sera pour les étapes
suivantes : ce mécanisme est prévu pour accueillir les gabarits de ce module dès qu'ils existeront
(`modules/gws-equestrian/templates/single-gwseq_prestation.php`,
`archive-gwseq_prestation.php`, `single-gwseq_cheval.php`, `archive-gwseq_cheval.php`), sans
copie de fichier ni changement d'activation.

`gwseq_groupe` n'aura jamais de gabarit ici : ce post type n'est pas public (voir le README côté
plugin) et ne dispose donc d'aucune page front — il n'est consommé que par l'écran
d'administration des tarifs (étape 4) et par le rendu de la page tarifs (étape 8), tous deux
lus depuis le plugin, jamais affichés comme une fiche indépendante.

Le rendu front réel (fiche cheval, listing des chevaux, page tarifs par groupe) est prévu à
l'étape 8 du plan de développement validé, une fois le modèle de données complet et stable.

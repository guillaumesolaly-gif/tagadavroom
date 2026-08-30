# Tests de logique — GWS Starter

Scripts PHP autonomes (aucune installation WordPress requise), qui stubent le strict minimum
d'API WordPress pour charger les vrais fichiers du starter et vérifier leur logique pure :
manipulation de la hiérarchie de gabarits, détection d'environnement, lecture d'options.

Ce dossier n'est pas inclus dans `gws-core.zip` ni `gws-starter.zip`.

## Exécuter

```
php tests/starter-logic-test.php
php tests/settings-helpers-logic-test.php
php tests/schema-homepage-logic-test.php
php tests/gws-equestrian-foundations-test.php
php tests/gws-equestrian-repeater-logic-test.php
php tests/gws-equestrian-prestations-logic-test.php
php tests/gws-equestrian-prestation-editor-test.php
php tests/gws-equestrian-cheval-logic-test.php
```

(`tests/qa-toggle-logic-test.php` est appelé automatiquement par `starter-logic-test.php`, dans
un processus PHP séparé — il peut aussi être lancé seul.)

## Ce qui est couvert

- Priorité gabarit projet > gabarit de module > fallback générique pour `single`/`archive` de
  CPT (`inc/module-templates.php`), avec simulation fidèle de l'algorithme de `locate_template()`
  et création/suppression réelle d'un fichier de test.
- Bascule de développement du module QA (`includes/modules.php`) : ignorée en production,
  effective en local/développement, jamais de doublon avec `config/modules.php`.
- Réglages enrichis en v1.4.0/v1.5.0 (`settings-helpers-logic-test.php`) : valeurs par défaut,
  champ `attachment_id` (accepte uniquement une vraie image), logo/WhatsApp/réseaux sociaux
  (dont X)/`sameAs` absents tant qu'ils ne sont pas renseignés (jamais d'entrée vide),
  dédoublonnage entre un réseau structuré et `social_links`, réglages d'affichage des
  pictogrammes sociaux en header/footer (footer activé par défaut, header désactivé), crédit
  Tagada Vroom activé par défaut et désactivable, URL du crédit personnalisable.
- Normalisation WhatsApp (v1.5.0) : format international obligatoire (`+` ou `00`), espaces/
  tirets/parenthèses ignorés, aucun indicatif jamais deviné — une saisie nationale sans
  indicatif renvoie une chaîne vide plutôt qu'un lien wa.me non fonctionnel.
- Schema de la page d'accueil (v1.5.0, `schema-homepage-logic-test.php`) : WebSite + Organization
  toujours présents sur l'accueil qu'il s'agisse d'une page statique ou de l'index natif des
  articles, sans fabriquer de WebPage/Breadcrumb pour ce second cas ; comportement historique
  inchangé sur les autres pages ; aucune sortie si un plugin SEO est actif.
- Module métier `gws-equestrian`, Étape 1 — Fondations (`gws-equestrian-foundations-test.php`) :
  post types et taxonomie réellement enregistrés, respect des limites WordPress de longueur de
  slug (20/32 caractères), préfixe `gwseq_` systématique (slugs et noms de fonctions, absence de
  toute réutilisation de `gws_`/`gws_core_`), absence de collision avec les post types des autres
  modules du projet, Groupe tarifaire jamais public (pas d'archive, pas de rewrite, exclu de la
  recherche), Prestation/Cheval publics avec archive, taxonomie de catégorie de cheval non
  hiérarchique attachée au bon post type.
- Composant répétable générique de `gws-equestrian`, Étape 2
  (`gws-equestrian-repeater-logic-test.php`) : normalisation d'une liste de lignes (texte,
  textarea, nombre, entier, URL), conservation stricte de l'ordre de saisie, suppression des
  lignes entièrement vides, conservation de la valeur `0`/`"0"` sur chaque type numérique, rejet
  silencieux des clés inattendues et des données mal formées (ligne ou tableau de lignes qui
  n'est pas un tableau, valeur de colonne elle-même un tableau), conservation des caractères
  spéciaux (apostrophes, accents, esperluette). Depuis la 0.2.1 : reproduction du chemin réel
  navigateur → `$_POST` en partant du markup HTML effectivement généré (extraction des attributs
  `name`/`value`, passage par `parse_str()` — le mécanisme PHP réel de construction de `$_POST`
  — puis sanitation), incluant la caractérisation de l'ancien bug de regroupement des lignes et
  la vérification des attributs `step` par type (`number` accepte les décimales, `integer` non).
- Prestations / Groupes tarifaires de `gws-equestrian`, Étape 3
  (`gws-equestrian-prestations-logic-test.php`) : sanitation de la tarification (prix unique
  entier/décimal, valeur `0` jamais confondue avec une absence de prix, mode Cheval/Poney à deux
  prix distincts, mode Sur devis sans prix inventé, mode invalide rejeté, unité standard et unité
  « Autre » avec libellé personnalisé, caractères spéciaux, visibilité publique du prix),
  relation Prestation → Groupe par ID (jamais par nom, y compris après renommage du groupe),
  résumé de prix (fonction pure texte, réutilisable admin/front/API), presets (identifiants
  uniques, résolution depuis le paramètre d'URL, absence de toute création automatique de
  contenu vérifiée directement dans le code source), et sécurité de la sauvegarde (nonce
  invalide, permissions insuffisantes, autosave, révision — chacun testé isolément avec le
  chemin réel `$_POST` → sanitation → meta). Complété après relecture : réglage global
  d'affichage des tarifs à trois modes (TTC/HT/Prix masqués, ce dernier prioritaire sur la
  visibilité individuelle sans jamais effacer les montants stockés), réglage de devise (EUR par
  défaut, au moins une autre devise testée, absence de symbole `€` codé en dur vérifiée
  directement dans le code source de la fonction de résumé), unités supplémentaires
  (récolte/colis/étalon) et presets de reproduction corrigés (congélation → paillette,
  réfrigération → récolte, expédition → colis, spermogramme → étalon). Complété à nouveau pour le
  mode « Sur demande » (ex-« Sur devis », valeur technique `devis` inchangée) : libellé affiché
  par défaut/personnalisé/volontairement vide (distinction « jamais initialisé » vs « vidé
  explicitement » via `metadata_exists()`), compatibilité avec les prestations déjà créées en
  1.6.1, absence de prix obligatoire, indépendance vis-à-vis du réglage global « Prix masqués ».
- Correction post-recette runtime de l'Étape 3 (`gws-equestrian-prestation-editor-test.php`) :
  cause racine du bug des modèles de prestations (le filtre `use_block_editor_for_post_type`
  désactive réellement l'éditeur par blocs pour `gwseq_prestation`, jamais globalement), rendu
  réel du sélecteur de modèle (contenu HTML effectivement produit, pas seulement la présence du
  hook), absence d'écriture avant sauvegarde et d'indépendance totale après, UX Nom/Description
  (espace réservé du titre, libellé Description, aucune meta dupliquée), portée des assets, et
  internationalisation (text domain `gws-core` cohérent sur tous les appels de traduction
  rencontrés dans le code source du module, HT/TTC indépendants de la devise choisie — y compris
  le cas GBP signalé en recette —, contenu utilisateur jamais passé dans une fonction de
  traduction).

- Socle métier Cheval de `gws-equestrian`, Étape 4 (`gws-equestrian-cheval-logic-test.php`) :
  identité (sexe/robe/race avec valeurs techniques stables et « Autre » à précision libre, année de
  naissance et taille avec bornes raisonnables documentées, âge calculé jamais stocké, éleveur/
  propriétaire/UELN/SIRE en texte simple), absence de toute meta parallèle à `post_title`/l'image à
  la une, catégories à cases à cocher natives (`meta_box_cb` réutilisant `post_categories_meta_box`)
  avec masquage réel de l'affordance de création rapide sur la fiche, commercialisation (statut
  indépendant des catégories, prix fixe/fourchette/sur demande, valeur `0` jamais confondue avec une
  absence de prix, libellé « sur demande » jamais initialisé/personnalisé/volontairement vidé,
  devise globale de l'Étape 3 réutilisée sans réglage propre au cheval, aucune mention HT/TTC),
  Global Horse ID (`_gwseq_global_id`, UUID v4 réellement généré au premier enregistrement réel,
  jamais sur un auto-draft/une révision/un autre post type, jamais régénéré, jamais accessible
  depuis le formulaire ni exposé en REST, boîte de vérification strictement réservée aux
  environnements local/développement), désactivation réelle de l'éditeur par blocs pour ce post
  type (comportement du filtre, pas seulement sa présence) avec un arbitrage distinct de celui de
  la Prestation, colonnes d'administration (Catégories/Statut commercial/Prix/Ordre), portée des
  assets, et internationalisation (text domain cohérent, contenu utilisateur jamais traduit).

## Ce qui n'est PAS couvert ici (à vérifier dans un vrai WordPress)

- Rendu HTML réel des gabarits, comportement de l'éditeur (sélecteur de gabarit de page).
- Comportement navigateur de la modale : focus trap, `inert`, restitution du focus, fermeture
  au clavier — voir la procédure QA pour les étapes de vérification manuelle précises.
- Écran d'administration **Outils > Recette GWS** (rendu, nonce, capability) en conditions
  réelles.
- Flush effectif des permaliens et fonctionnement réel de l'archive `/qa-items/` dans WordPress.
- Rendu réel des pictogrammes sociaux (composant `template-parts/content/social-links.php`) :
  `currentColor`, focus clavier, nom accessible de chaque lien, ouverture en nouvel onglet.
- Rendu navigateur réel de l'écran d'édition d'une fiche cheval : apparence effective de la liste
  de cases à cocher native de `post_categories_meta_box()` (widget WordPress non réimplémenté ici),
  bascule visuelle des champs conditionnels par `assets/cheval-admin.js` (robe/race « Autre », mode
  de prix), ordre visuel réel des meta boxes (Identité/Commercialisation/Catégories/Ordre/Image à la
  une), et disparition effective de l'affordance de création rapide de catégorie une fois le CSS
  chargé dans une vraie page d'administration.

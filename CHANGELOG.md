# Changelog — GWS Starter

## 1.27.0 (gws-core uniquement — gws-starter reste en 1.5.0, non modifié)

- **Partager un cheval : correctifs de recette avant test des canaux (GWS Equestrian 0.24.0).**
  Bug corrigé : un titre de cheval contenant une entité HTML littérale (ex. « NACELLE D&rsquo;ELLE
  ») s'affichait tel quel au lieu du caractère qu'elle représente — décodage au point unique de
  lecture du titre, jamais de modification des titres existants en base, échappement contextuel
  déjà en place inchangé (texte brut/`esc_attr()`/`textContent`), vérifié sans introduire de
  possibilité d'injection. Libellés désormais VISIBLES pour les quatre filtres de l'écran de
  sélection (Sexe/Statut commercial/Catégorie/Année de naissance), sans aucune modification de la
  logique de filtrage déjà validée. Voir
  `wp-content/plugins/gws-core/modules/gws-equestrian/CHANGELOG.md` (0.24.0) pour le détail
  complet.

## 1.26.0 (gws-core uniquement — gws-starter reste en 1.5.0, non modifié)

- **Partager un cheval : correctifs et améliorations de recette (GWS Equestrian 0.23.0).** Bug
  prioritaire corrigé (une information décochée, notamment le prix, pouvait encore apparaître dans
  l'aperçu — cause racine : une réponse AJAX obsolète pouvant écraser l'aperçu à jour côté client,
  corrigée par un jeton de requête, jamais un simple masquage visuel). Vignette de remplacement
  neutre et réutilisable pour un cheval sans photo (jamais d'icône "image cassée"). Filtres métier
  cumulables sur l'écran de sélection (sexe, statut commercial, plage d'année de naissance,
  catégorie de cheval — réutilisant les référentiels déjà existants), avec réinitialisation et sans
  bouton "Appliquer". Densité de l'écran de composition améliorée. Voir
  `wp-content/plugins/gws-core/modules/gws-equestrian/CHANGELOG.md` (0.23.0) pour le détail
  complet.

## 1.25.0 (gws-core uniquement — gws-starter reste en 1.5.0, non modifié)

- **Partager un cheval (GWS Equestrian 0.22.0).** Nouvel écran métier mobile-first `Chevaux →
  Partager` (accessible aussi depuis chaque fiche cheval) permettant de préparer, à partir des
  seules données déjà renseignées, un message commercial prêt à envoyer par WhatsApp, SMS/Messages
  ou à copier — GWS n'envoie jamais rien lui-même (aucun serveur SMS, aucune API WhatsApp/Meta,
  aucune persistance). Nouveau champ Cheval "Accroche commerciale", couche métier réutilisable
  (`gwseq_get_horse_shareable_data()`/`gwseq_build_horse_share_message()`) pour les futures
  évolutions annoncées (lien privé, PDF, sélections), règle stricte entre statut commercial et
  affichage du prix, aperçu temps réel composé serveur, et Open Graph de la fiche Cheval
  (og:title/description/image, jamais le prix). Voir
  `wp-content/plugins/gws-core/modules/gws-equestrian/CHANGELOG.md` (0.22.0) pour le détail
  complet.

## 1.24.0 (gws-core uniquement — gws-starter reste en 1.5.0, non modifié)

- **Retrait du module Mises en avant (GWS Equestrian 0.21.0) — décision produit, pas une
  régression.** Après recette UX du lot 0.20.0, décision de ne pas conserver de moteur
  propriétaire Pop-in/Sticky bar : fonctionnalité jugée périphérique à la valeur métier de GWS
  Equestrian, à couvrir le cas échéant par une extension WordPress tierce spécialisée
  (probablement Hustle) plutôt que par du code maison. Retrait complet et propre : les deux post
  types (`gwseq_popin`/`gwseq_sticky_bar`), le menu "Mises en avant", les champs/meta, l'aperçu BO
  et son point d'entrée AJAX, le rendu front, les assets JS/CSS dédiés et les fonctions partagées
  devenues inutiles sont supprimés — aucun code mort laissé, aucune suppression destructive de
  données en base. Actualités (cadrage Gutenberg, 0.19.0) inchangé et conservé. Voir
  `wp-content/plugins/gws-core/modules/gws-equestrian/CHANGELOG.md` (0.21.0) pour le détail
  complet.

## 1.23.0 (gws-core uniquement — gws-starter reste en 1.5.0, non modifié)

- **Mises en avant : Pop-in et Sticky bar (GWS Equestrian 0.20.0).** Deux nouveaux objets métier
  BO non publics (`gwseq_popin`/`gwseq_sticky_bar`), regroupés sous une seule entrée de menu
  "Mises en avant" (sous-menus "Pop-ins"/"Sticky bars"), sans Gutenberg. Rendu FRONT réel cette
  fois (contrairement à Actualités) : Pop-in avec déclenchement Immédiat/Délai/Scroll/Intention de
  sortie (desktop uniquement, aucun fallback mobile automatique) et fréquence À chaque visite/Une
  fois par session/Une fois tous les X jours (sessionStorage/localStorage, sans identifiant ni
  tracking) ; Sticky bar Haut/Bas avec fermeture facultative. Diffusion commune : statut propre
  (distinct du statut natif WordPress), période avec fuseau du site, ciblage sur Pages/Chevaux/
  Prestations/Actualités (clé composite post_type + ID, jamais un ID ambigu), priorité par
  `menu_order` en cas de campagnes concurrentes du même type. Aperçu BO temps réel partageant EXACTEMENT
  la même fonction de rendu PHP que le front (aucune divergence possible). Voir
  `wp-content/plugins/gws-core/modules/gws-equestrian/CHANGELOG.md` (0.20.0) pour le détail complet.

## 1.22.0 (gws-core uniquement — gws-starter reste en 1.5.0, non modifié)

- **Actualités : cadrage de l'éditeur par blocs (GWS Equestrian 0.19.0).** Gutenberg reste
  l'éditeur des Actualités, mais sa palette de blocs est désormais restreinte (via le filtre natif
  `allowed_block_types_all`) à Paragraphe, Titre, Liste, Image, Galerie, Bouton, Vidéo et
  intégration vidéo sûre — la mise en page avancée (colonnes, groupes, couverture, HTML
  personnalisé...) reste l'affaire du thème, jamais de l'utilisateur. Scopé strictement à `post` :
  aucun effet sur l'éditeur des Pages ni sur aucun autre post type. Voir
  `wp-content/plugins/gws-core/modules/gws-equestrian/CHANGELOG.md` (0.19.0) pour le détail complet.

## 1.21.0 (gws-core uniquement — gws-starter reste en 1.5.0, non modifié)

- **Bloc Actualités + filtre Prestations par Groupe tarifaire (GWS Equestrian 0.18.0).**
  Adaptation du système NATIF des articles WordPress (`post`) au vocabulaire GWS Equestrian —
  aucun nouveau post type, aucune migration : vocabulaire "Actualités" (menu, écrans, notifications),
  Étiquettes masquées de l'interface (jamais supprimées ni désinscrites), commentaires/trackbacks
  retirés pour les nouvelles Actualités, Modification rapide retirée (réutilisation de la fonction
  déjà existante pour les objets métier GWS). Catégories natives et champs d'édition (titre,
  contenu, image à la une, date, statut, auteur) inchangés, aucun rendu front développé. Ajout
  d'un filtre par Groupe tarifaire dans `Prestations → Toutes les prestations` (dont le cas "Sans
  groupe tarifaire"), réutilisant la relation déjà existante. Voir
  `wp-content/plugins/gws-core/modules/gws-equestrian/CHANGELOG.md` (0.18.0) pour le détail complet.

## 1.20.1 (gws-core uniquement — gws-starter reste en 1.5.0, non modifié)

- **Micro-corrections UX post-recette Équipe (GWS Equestrian 0.17.1).** Placeholders et aides de
  saisie sur les champs réseaux sociaux/URL et WhatsApp de la fiche Membre (aucun changement de la
  logique de stockage/sanitation). Libellé de recherche natif "Rechercher des membres" (et, la même
  anomalie ayant été vérifiée sur les autres CPT du module, "Rechercher un cheval"/"Rechercher une
  prestation"/"Rechercher un groupe tarifaire") à la place du défaut générique WordPress
  "Rechercher des articles". Action de ligne "Modification rapide" retirée sur les quatre objets
  métier GWS Equestrian (Chevaux, Membres, Prestations, Groupes tarifaires) uniquement — Quick
  Edit reste disponible partout ailleurs. Voir
  `wp-content/plugins/gws-core/modules/gws-equestrian/CHANGELOG.md` (0.17.1) pour le détail complet.

## 1.20.0 (gws-core uniquement — gws-starter reste en 1.5.0, non modifié)

- **Module Équipe, nouvel objet métier Membre (GWS Equestrian 0.17.0).** Nouveau post type
  `gwseq_membre`, indépendant de Cheval, pour gérer les personnes qu'une structure équestre
  souhaite présenter (dirigeants, cavaliers, moniteurs, soigneurs, grooms, responsables d'élevage,
  vétérinaires intégrés, personnel administratif...) — volontairement simple, ni annuaire RH, ni
  comptes utilisateurs, ni CRM. Menu d'administration "Équipe" (`Tous les membres`/`Ajouter un
  membre`), fiche organisée en trois sections simples (Identité, Profil, Contact), tous les champs
  facultatifs, aucun référentiel/taxonomie créé hormis Langues (sélection multiple à valeurs
  canoniques stables, "Autre" avec précision libre nettoyée automatiquement par le serveur dès
  qu'elle n'est plus sélectionnée). Titre technique WordPress automatiquement dérivé de Prénom +
  Nom via un filtre `wp_insert_post_data` (aucune boucle de sauvegarde possible), champ Titre natif
  masqué pour éviter une saisie redondante. Liste "Tous les membres" avec colonnes Photo/Nom/
  Fonction/Localisation/Langues/Ordre. Permissions héritées du type de capacité standard `'post'`,
  aucune capacité technique supplémentaire créée. Rendu front volontairement non développé dans ce
  lot. Voir `wp-content/plugins/gws-core/modules/gws-equestrian/CHANGELOG.md` (0.17.0) pour le
  détail complet.

## 1.19.0 (gws-core uniquement — gws-starter reste en 1.5.0, non modifié)

- **Corrections de clôture du back-office Cheval V1 (GWS Equestrian 0.16.0).** Suite à un audit
  fonctionnel en conditions réelles, deux correctifs ciblés avant le gel du back-office Cheval V1.
  (1) Nettoyage des relations père/mère à la suppression DÉFINITIVE d'un cheval : un cheval
  supprimé définitivement pouvait rester référencé comme père/mère "Cheval déjà enregistré" d'un
  autre cheval (sélecteur vide, pedigree affichant "Cheval introuvable (#ID)") — corrigé via un
  nouveau nettoyage accroché uniquement à `before_delete_post` (jamais à la mise à la corbeille,
  qui continue de préserver intégralement la relation, restauration comprise). (2) Liste
  d'administration "Tous les chevaux" : quatre filtres cumulables et combinables avec la recherche
  native (Catégorie, Statut commercial, Sexe, Année de naissance — années construites
  dynamiquement à partir des seules données réellement présentes), et colonnes ramenées à Nom |
  Catégories | Sexe | Année | Statut commercial | Prix | Ordre (colonne "Date" native retirée).
  Voir `wp-content/plugins/gws-core/modules/gws-equestrian/CHANGELOG.md` (0.16.0) pour le détail
  complet.

## 1.18.0 (gws-core uniquement — gws-starter reste en 1.5.0, non modifié)

- **Labels ANSF (GWS Equestrian 0.15.0), nouveau lot volontairement minimal.** Nouvel onglet
  "Labels" dans la fiche Cheval, limité aux labels Selle Français / ANSF identifiés pour la
  commercialisation initiale en France (aucun moteur générique de distinctions) : Selle Français
  Originel (SFO, disponible pour les trois sexes), les trois labels poulinières Sport/Élevage/
  Modèle & Allures (femelle uniquement, un seul niveau par famille via un groupe de boutons radio,
  jamais des cases indépendantes), et Étalon SF Génétique Avenir (mâle et hongre). Sanitation
  serveur obligatoire indépendante de l'affichage admin ; un changement de sexe nettoie
  automatiquement les labels devenus incompatibles au prochain enregistrement, SFO toujours
  préservé. Pictogrammes officiels ANSF pas encore disponibles : le modèle permet une
  correspondance future, sans aucun pictogramme temporaire. La fonctionnalité de duplication d'un
  cheval est retirée de la roadmap V1. Voir
  `wp-content/plugins/gws-core/modules/gws-equestrian/CHANGELOG.md` (0.15.0) pour le détail complet.

## 1.17.6 (gws-core uniquement — gws-starter reste en 1.5.0, non modifié)

- **Correctif complémentaire : cause racine réelle du bug "Préciser" (GWS Equestrian 0.14.6).** Le
  correctif 0.14.5 restait insuffisant : une race canonique correctement affichée réapparaissait
  avec "Préciser" rempli après un simple clic sur "Publier"/"Mettre à jour" sans avoir touché au
  champ Race. Cause exacte : le composant de recherche traitait tout champ jamais explicitement
  "sélectionné" cette session-ci (même déjà correctement rempli au chargement) comme une saisie
  libre en attente, et le filet de sécurité de soumission (déclenché sur n'importe quel
  enregistrement) réécrivait alors le code en "autre" avec le libellé affiché recopié dans
  "Préciser". Corrigé en une ligne : un champ chargé avec un code déjà valide démarre désormais
  comme une sélection déjà validée. Voir
  `wp-content/plugins/gws-core/modules/gws-equestrian/CHANGELOG.md` (0.14.6) pour le détail complet.

## 1.17.5 (gws-core uniquement — gws-starter reste en 1.5.0, non modifié)

- **Correctifs post-recette : reconstruction du pedigree IFCE, bug "Préciser" persistant,
  rattachement Père/Mère GWS pendant l'import (GWS Equestrian 0.14.5).** (A) Deux fiches IFCE
  réelles distinctes (Asb Conquistador, Cornet Obolensky) faisaient reconstruire un pedigree faux :
  un ascendant dont le nom, l'alias, le stud-book et l'année débordaient sur deux lignes du document
  produisait un ASCENDANT FANTÔME, décalant la position de tous les ascendants suivants — corrigé en
  étendant la détection des continuations de ligne, vérifié par des tests structurels sur l'arbre
  réel (pas seulement un décompte du nombre d'ascendants) contre les deux vrais documents. (B) Le
  champ "Préciser" pouvait réapparaître avec une ancienne valeur alors qu'une race canonique était
  sélectionnée (le composant ne vidait jamais son propre champ "Autre" en resélectionnant une race
  canonique, et le `<select>` de secours n'avait aucune condition de visibilité sur son propre bloc
  "Préciser") — corrigé pour l'identité et le pedigree, avec auto-guérison à l'affichage d'une
  donnée déjà enregistrée. (C) L'écran de prévisualisation d'import IFCE permet désormais de relier
  le Père et la Mère détectés à une fiche Cheval GWS déjà existante, plutôt que de systématiquement
  créer un ascendant externe — réutilise les mêmes règles métier que la saisie manuelle du pedigree
  (sexe, année, aucun cheval commun aux deux rôles), sans effet si l'import du pedigree est
  désactivé. Voir `wp-content/plugins/gws-core/modules/gws-equestrian/CHANGELOG.md` (0.14.5) pour
  le détail complet.

## 1.17.4 (gws-core uniquement — gws-starter reste en 1.5.0, non modifié)

- **Correctif runtime : cause exacte de l'échec d'initialisation du champ Race, un `<ul>` ne peut
  être placé dans un `<p>` (GWS Equestrian 0.14.4).** Recette du filet de sécurité 0.14.3 (`<select>`
  de secours) : le `<select>` s'affichait bien, mais confirmait que le composant de recherche
  restait non initialisé (logs : `resultsList=false`, `aborting init for this field only`, malgré
  `search=true codeInput=true`). Cause exacte : le composant imprime un `<ul>` de résultats, or les
  deux appelants (identité et pedigree) l'enveloppaient dans un `<p>` — la spécification HTML5
  interdit tout contenu "flow" (`<ul>` inclus) dans un `<p>`, et un navigateur réel ferme
  implicitement le `<p>` dès qu'il rencontre le `<ul>`, expulsant celui-ci hors de la structure
  attendue. Un défaut qu'aucun test simulé (DOM construit sans vrai parseur HTML) ne pouvait
  révéler. Corrigé en enveloppant ces deux appels dans un `<div>` plutôt qu'un `<p>` — aucune
  modification de la fonction partagée, du parseur IFCE, du référentiel ou du pedigree. Nouveau test
  structurel dédié, vérifié positif contre l'ancien balisage. Voir
  `wp-content/plugins/gws-core/modules/gws-equestrian/CHANGELOG.md` (0.14.4) pour le détail complet
  et les limites de ce qui reste à confirmer dans un vrai navigateur.

## 1.17.3 (gws-core uniquement — gws-starter reste en 1.5.0, non modifié)

- **Correctif runtime : régression de la 0.14.2 réintroduite lors de l'instrumentation, filet de
  sécurité obligatoire sur le champ Race, ajustement UX de la prévisualisation IFCE (GWS Equestrian
  0.14.3).** Recette du correctif 0.14.2 : l'extraction IFCE et la normalisation croisée confirmées
  fonctionnelles, mais l'autocomplétion Race restait totalement non fonctionnelle sur un vrai
  wp-admin (impossible de modifier ou de saisir une race, sans aucun contrôle de repli), malgré des
  logs prouvant un chargement et une initialisation intégralement réussis. Deux volets distincts :
  (A) la réécriture de l'instrumentation avait réintroduit exactement le caractère Unicode littéral
  déjà corrigé en 0.14.2, détecté et corrigé avant livraison par une vérification octet-par-octet du
  fichier ; (B) la cause exacte de la panne runtime observée par l'utilisateur reste non reproduite
  en environnement de test malgré une instrumentation exhaustive désormais en place (dix points de
  diagnostic, `try`/`catch` dédié sur chaque interaction réelle). En complément, indépendamment de la
  résolution de cette cause : un `<select>` natif de secours est désormais TOUJOURS rendu à côté du
  composant de recherche (identité et pedigree), actif et fonctionnel par défaut, et n'est désactivé
  qu'une fois l'initialisation JavaScript confirmée réussie sans exception — garantissant qu'une race
  reste toujours saisissable même si le composant échoue. La prévisualisation d'import IFCE affiche
  désormais l'identité détectée en lignes explicitement étiquetées (Race/Stud-book, Sexe, Robe,
  Taille, Année de naissance) plutôt qu'en un résumé ambigu concaténé par des virgules. Voir
  `wp-content/plugins/gws-core/modules/gws-equestrian/CHANGELOG.md` (0.14.3) pour le détail complet.

## 1.17.2 (gws-core uniquement — gws-starter reste en 1.5.0, non modifié)

- **Correctif runtime : cause racine réelle de l'autocomplétion, robustesse de l'extraction IFCE
  (GWS Equestrian 0.14.2).** L'autocomplétion Race restait non fonctionnelle sur un vrai wp-admin
  malgré le correctif logique 0.14.1 : cause identifiée — un caractère Unicode littéral multi-octet
  directement dans le code exécutable d'une expression régulière du script, fragile à tout maillon
  d'hébergement/transfert qui ne le préserverait pas en UTF-8 (produisant une erreur de syntaxe
  silencieuse). Remplacé par un échappement ASCII strictement équivalent, insensible à ce risque ;
  une instrumentation de diagnostic temporaire a été ajoutée au script. Par ailleurs, l'extraction
  de l'identité IFCE gère désormais un nombre variable de segments (Robe et Taille chacune
  facultatives indépendamment) — deux fiches réelles perdaient leur année de naissance, une
  troisième était intégralement rejetée à l'analyse ; les trois cas sont corrigés et couverts par de
  nouvelles fixtures PDF réelles. Un même stud-book (ex. KWPN) résout désormais vers un code
  canonique unique quel que soit son libellé IFCE rencontré (identité ou pedigree). Voir
  `wp-content/plugins/gws-core/modules/gws-equestrian/CHANGELOG.md` (0.14.2) pour le détail complet.

## 1.17.1 (gws-core uniquement — gws-starter reste en 1.5.0, non modifié)

- **Correctif runtime : autocomplétion Race inutilisable en édition, alias/code pays IFCE (GWS
  Equestrian 0.14.1).** Recette du référentiel 0.14.0 : impossible de modifier ou de vider la
  Race/Stud-book/Appellation d'une fiche déjà importée (aucune suggestion à la frappe, l'ancienne
  valeur revenait à l'enregistrement) — deux causes racines dans le composant JavaScript
  d'autocomplétion (absence de sélection du texte existant au focus, et une mise à jour du code
  différée de 150 ms perdant la course face à un clic sur "Enregistrer"), toutes deux corrigées.
  Par ailleurs, un cheval ou un ascendant IFCE portant un alias voit désormais son alias retenu comme
  nom (au lieu d'être supprimé), le nom officiel restant conservé séparément ; un marqueur pays entre
  parenthèses est retiré via une liste fermée de codes ISO reconnus, jamais une suppression aveugle
  de toute parenthèse. Voir
  `wp-content/plugins/gws-core/modules/gws-equestrian/CHANGELOG.md` (0.14.1) pour le détail complet.

## 1.17.0 (gws-core uniquement — gws-starter reste en 1.5.0, non modifié)

- **Référentiel Race / Stud-book / Appellation, ascendant + année de naissance, pedigree sur 3
  générations (GWS Equestrian 0.14.0).** Nouveau référentiel unique (`includes/race-referentiel.php`,
  154 entrées générées depuis le fichier XLSX IFCE fourni) qui dissocie la richesse technique du
  référentiel de la simplicité de l'interface : plus de `<select>` de plus de 100 valeurs, remplacé
  par un composant de recherche/autocomplétion partagé (identité du cheval ET chaque génération
  d'ascendant externe), avec résolution d'alias historiques (l'alias "SFA" résout désormais vers
  "SF", jamais rangé dans "Autre"), recherche par code IFCE ou libellé, et suggestions "récents"
  propres à chaque utilisateur (jamais un profil métier rigide codé en dur, jamais une modification
  de la donnée Cheval). Le modèle d'ascendant externe gagne un champ "année de naissance" optionnel
  (alimenté automatiquement par l'import IFCE quand disponible, jamais utilisé pour calculer un âge).
  La profondeur standard du pedigree passe de 4 à 3 générations (14 ascendants, alignée sur la fiche
  de synthèse IFCE) ; une éventuelle donnée de génération 4 déjà enregistrée lors de recettes
  précédentes n'est jamais supprimée, simplement plus jamais rendue au-delà de la nouvelle
  profondeur standard. Voir
  `wp-content/plugins/gws-core/modules/gws-equestrian/CHANGELOG.md` (0.14.0) pour le détail complet.

## 1.16.2 (gws-core uniquement — gws-starter reste en 1.5.0, non modifié)

- **Correctif bloquant : « headers already sent » à l'analyse du PDF IFCE (GWS Equestrian 0.13.2).**
  Le lancement réel de l'import IFCE échouait avec « Cannot modify header information - headers
  already sent by ... wp-admin/menu-header.php », sans jamais atteindre l'écran de prévisualisation.
  Cause : le traitement des formulaires (upload, confirmation) s'exécutait depuis le callback de la
  page d'administration, appelé par WordPress seulement APRÈS que le HTML du menu d'administration
  a déjà été émis — une redirection à ce stade échoue systématiquement. Corrigé en confiant ce
  traitement aux hooks natifs `admin_post_{action}` de WordPress (déclenchés depuis
  `wp-admin/admin-post.php`, qui ne rend jamais de HTML avant de les déclencher), et en extrayant la
  logique métier de chaque étape dans des fonctions pures ne redirigeant jamais elles-mêmes — ce qui
  les rend directement testables. Voir
  `wp-content/plugins/gws-core/modules/gws-equestrian/CHANGELOG.md` (0.13.2) pour le détail complet.

## 1.16.1 (gws-core uniquement — gws-starter reste en 1.5.0, non modifié)

- **Recette runtime de l'import IFCE et de la Photo principale — trois correctifs consolidés (GWS
  Equestrian 0.13.1).**
  **A. Compatibilité avec le vrai PDF IFCE (bug bloquant)** : le vrai PDF de Jamerose de Félines
  était rejeté. Diagnostic complet avant correctif : la quasi-totalité des dictionnaires du PDF réel
  (généré par iText/BIRT) sont stockés dans un flux d'objets compressé jamais lu par l'ancien
  extracteur, et le corps du texte utilise une police composite CID (Identity-H) dont les codes ne
  sont interprétables qu'à travers sa table ToUnicode — également jamais résolue. `ifce-pdf-text.php`
  a été réécrit pour résoudre ces deux mécanismes (index d'objets couvrant les flux compressés,
  décodage ToUnicode/WinAnsiEncoding par police, reconstruction de ligne par coordonnée Y plutôt que
  par les opérateurs `Td`/`TD`/`T*`, absents de ce type de générateur). Résultat validé sur le vrai
  PDF : identité, indices (avec CD) et les 14 ascendants du pedigree sont désormais tous extraits et
  reconnus correctement. Ce vrai PDF (`tests/fixtures/ifce-jamerose-de-felines.pdf`) est désormais la
  fixture de référence des tests de reconnaissance/analyse.
  **B. Écran de choix "Ajouter un cheval"** : l'import IFCE, jusqu'ici relégué à un simple bandeau
  sur le formulaire manuel, est désormais présenté à égalité avec la création manuelle sur un écran
  de choix dédié, affiché AVANT tout formulaire — le manuel reste entièrement disponible via un
  second clic explicite.
  **C. Verrouillage de la Photo principale dans Médias (bug bloquant)** : le contrôle natif
  "Descendre" de la boîte Image à la une, restée visible après son intégration dans l'onglet Médias,
  la faisait disparaître une fois cliqué et pouvait corrompre l'état WordPress de l'utilisateur. Les
  contrôles de réordonnancement/repli, devenus obsolètes une fois la boîte fixée dans Médias, sont
  désormais masqués, et un nettoyage automatique répare l'état déjà corrompu par un usage antérieur
  de ce contrôle, sans jamais toucher aux autres préférences de l'utilisateur. Voir
  `wp-content/plugins/gws-core/modules/gws-equestrian/CHANGELOG.md` (0.13.1) et `tests/README.md`
  pour le détail complet des trois correctifs.

## 1.16.0 (gws-core uniquement — gws-starter reste en 1.5.0, non modifié)

- **Étape 7 : premier import intelligent depuis une fiche de synthèse IFCE, au format PDF (GWS
  Equestrian 0.13.0).** Second chemin de création d'une fiche Cheval, « Importer une fiche IFCE »,
  en complément de la création manuelle existante — objectif : supprimer la ressaisie manuelle, en
  particulier pour le pedigree. Téléversement du PDF complet -> analyse -> écran de prévisualisation
  obligatoire (identité/indices/pedigree détectés, avec case à cocher indépendante par section) ->
  validation explicite -> écriture uniquement à ce moment ; un document non reconnu n'écrit
  strictement rien. Le parseur ne touche jamais directement aux post meta : il produit une structure
  normalisée relayée vers les mêmes fonctions métier que la saisie manuelle
  (`gwseq_set_cheval_identity()` — nouvelle extraction pure, sans changement de comportement pour le
  formulaire existant —, `gwseq_set_cheval_sport_indice()`, `gwseq_set_cheval_genetic_indice()`,
  `gwseq_set_horse_parent()`). Extension du modèle de données : ISO/ICC/IDR stockent désormais aussi
  un coefficient de détermination (CD), qu'une fiche IFCE fournit systématiquement pour ces trois
  indices. Le pedigree (objectif principal) est reconstruit automatiquement sur 3 générations en
  réutilisant le mécanisme d'ascendants externes déjà existant (Étape 5) — les ascendants sont
  toujours importés en mode externe, jamais de fiche GWS créée automatiquement pour l'un d'eux.
  **Limitation majeure assumée** : faute d'accès réseau, cet import n'a pu être testé contre aucun
  PDF IFCE réel (seulement un PDF minimal auto-généré et une fixture texte reproduisant l'exemple
  fourni) — la prévisualisation obligatoire reste la garantie réelle contre une donnée mal
  interprétée. Voir `wp-content/plugins/gws-core/modules/gws-equestrian/CHANGELOG.md` (0.13.0) et
  `tests/README.md` pour le détail complet.

## 1.15.6 (gws-core uniquement — gws-starter reste en 1.5.0, non modifié)

- **Diagnostic et correctif : contenu de la Photo principale invisible après déplacement (GWS
  Equestrian 0.12.6).** Le déplacement réel de 0.12.5 restait non fonctionnel : seul le titre
  apparaissait dans l'onglet Médias, sans aucun contrôle ni aucune image en dessous. Diagnostic
  avant correctif : un test d'exécution réelle avec le markup exact de WordPress pour
  `#postimagediv` (nonce, liens natifs, vignette) a confirmé, dans les deux états (avec/sans photo
  déjà définie), que le contenu survit intact au déplacement — écartant avec certitude le code de
  déplacement lui-même comme cause. Cause probable identifiée : WordPress ne prévoit jamais qu'un
  `.postbox` soit imbriqué dans un autre `.postbox` (la forme de DOM inédite créée par le
  déplacement de 0.12.5), une situation que l'administration WordPress est susceptible de masquer
  par une règle CSS défensive. Correctif : une règle CSS scopée (`display: revert !important`)
  réinitialise chaque élément déplacé à sa valeur par défaut, sans hypothèse sur l'identité exacte
  d'une éventuelle règle contraire — aucun changement JavaScript, aucune régression possible pour
  les installations non concernées. Voir
  `wp-content/plugins/gws-core/modules/gws-equestrian/CHANGELOG.md` (0.12.6) pour le détail complet
  de la démarche de diagnostic et des tests renforcés.

## 1.15.5 (gws-core uniquement — gws-starter reste en 1.5.0, non modifié)

- **Intégration réelle de la Photo principale dans l'onglet Médias (GWS Equestrian 0.12.5).** Le
  simple masquage/affichage en place de la boîte native "Image à la une" (`postimagediv`) était
  insuffisant : elle restait visible dans la colonne latérale, et l'onglet Médias ne présentait
  qu'un texte y renvoyant. Elle est désormais RÉELLEMENT déplacée (via `appendChild()` sur le nœud
  existant, jamais un clone) dans un emplacement dédié à l'intérieur de la boîte Médias, aux côtés
  de Galerie/Vidéos — n'apparaît donc plus jamais dans la colonne latérale, hérite automatiquement
  de la visibilité de sa nouvelle boîte hôte, et reste restaurée à sa position native si le système
  d'onglets se désactive. Aucune donnée dupliquée : même nœud DOM, même `attachment_id`, la
  Featured Image de WordPress reste l'unique source de vérité. Le texte devenu inutile a été
  retiré. Voir `wp-content/plugins/gws-core/modules/gws-equestrian/CHANGELOG.md` (0.12.5) pour le
  détail complet.

## 1.15.4 (gws-core uniquement — gws-starter reste en 1.5.0, non modifié)

- **Nettoyage de l'état WordPress hérité sur la meta box Identité (GWS Equestrian 0.12.4).** La
  boîte Identité reste (et est restée depuis l'Étape 4) enregistrée en contexte `'normal'` — jamais
  `'side'`, jamais modifiée dans le code. Ce qui pouvait diverger, c'est l'état PERSISTÉ PAR
  UTILISATEUR accumulé pendant les recettes successives sur cet écran : une case décochée dans le
  panneau "Options de l'écran" (cause racine confirmée en 0.12.3), ou un ordre/colonne de meta
  boxes mémorisé par un ancien glisser-déposer. Une nouvelle fonction, exécutée uniquement sur
  l'écran d'édition d'une fiche Cheval, purge désormais ces deux préférences si elles portent une
  trace incohérente concernant Identité — sans jamais toucher aux autres préférences de
  l'utilisateur ni au registre `add_meta_box()`. Complémentaire (pas un remplacement) des deux
  filets de sécurité runtime livrés en 0.12.3. Voir
  `wp-content/plugins/gws-core/modules/gws-equestrian/CHANGELOG.md` (0.12.4) pour le détail complet.

## 1.15.3 (gws-core uniquement — gws-starter reste en 1.5.0, non modifié)

- **Correctif RÉGRESSION BLOQUANTE — diagnostic complet de l'onglet Identité vide, filets de
  sécurité (GWS Equestrian 0.12.3).** Le correctif 0.12.2 (repli natif `.closed`) était incomplet :
  la recette a montré une boîte Identité ENTIÈREMENT invisible (en-tête compris), symptôme que
  `.closed` seul ne peut pas produire. Cause complète : WordPress peut masquer une meta box entière
  via `.hide-if-js`, posée quand un utilisateur l'a masquée via "Screen Options" — une préférence
  mémorisée par utilisateur, plausible sur une base de recette réutilisée depuis plusieurs versions
  — via une règle CSS potentiellement `!important`, qu'un simple `style.display = ''` ne bat jamais.
  Corrigé : le script lève désormais `.closed` ET `.hide-if-js`, vérifie RÉELLEMENT la visibilité
  obtenue (`offsetParent`), et force l'affichage avec la même priorité `!important` si nécessaire.
  Deux filets de sécurité génériques ajoutés : (1) chaque meta box gérée par un onglet est marquée
  d'une classe dans le HTML réellement rendu (filtre natif `postbox_classes`) — si elle est absente,
  aucun onglet n'est construit (jamais deux vérités indépendantes) ; (2) si une boîte reste
  malgré tout invisible, le système d'onglets se désactive intégralement et restaure la visibilité
  de tout — un échec du système d'onglets ne peut plus jamais rendre une donnée inaccessible. Tests
  renforcés pour modéliser fidèlement l'effet réel de ces mécanismes WordPress plutôt qu'un DOM
  simplifié. Voir `wp-content/plugins/gws-core/modules/gws-equestrian/CHANGELOG.md` (0.12.3) pour
  le détail complet.

## 1.15.2 (gws-core uniquement — gws-starter reste en 1.5.0, non modifié)

- **Correctif RÉGRESSION BLOQUANTE — onglet Identité vide ; Photo principale intégrée à Médias
  (GWS Equestrian 0.12.2).** La reprise de la recette a confirmé la barre d'onglets fonctionnelle,
  mais l'onglet Identité affichait une zone vide (champs sexe, année de naissance, robe,
  race/stud-book, taille, éleveur, propriétaire, SIRE/UELN inaccessibles). Cause exacte : la boîte
  Identité était laissée REPLIÉE par le mécanisme natif WordPress (classe `.closed`, indépendant
  des onglets) — la règle CSS native qui masque le contenu replié cible un enfant de la boîte
  (`.inside`), jamais la boîte elle-même, donc rétablir `style.display` sur le conteneur ne
  suffisait pas à révéler son contenu. L'ID de boîte, le contexte WordPress et la configuration des
  onglets étaient corrects — seul l'état de repli natif ne l'était pas. Corrigé : l'activation d'un
  onglet lève désormais systématiquement ce repli pour chacune de ses boîtes. Ajustement
  complémentaire : la Photo principale (image à la une native) rejoint désormais Galerie/Vidéos
  sous l'onglet Médias, selon le même mécanisme déjà utilisé pour Pedigree/Production — aucun
  second champ, aucun second attachment ID, la Featured Image de WordPress reste l'unique source de
  vérité. Voir `wp-content/plugins/gws-core/modules/gws-equestrian/CHANGELOG.md` (0.12.2) pour le
  détail complet de la cause racine et des tests renforcés.

## 1.15.1 (gws-core uniquement — gws-starter reste en 1.5.0, non modifié)

- **Correctif RÉGRESSION BLOQUANTE — navigation par onglets (GWS Equestrian 0.12.1).** La recette
  runtime de 1.15.0 a échoué immédiatement : la barre d'onglets n'apparaissait jamais (le script
  appelait `insertBefore()` avec un nœud de référence qui n'est pas un enfant réel du nœud
  appelant sur l'écran classique de WordPress — une `DOMException` systématique, jamais détectée
  par des tests qui ne faisaient que scanner le texte source du script sans jamais l'exécuter), et
  un changement de contexte de deux meta boxes (`'side'` → `'normal'`) exposait un risque connu de
  WordPress de perte d'affichage d'une meta box pour un utilisateur ayant déjà un ordre de boîtes
  enregistré sur cet écran. Les deux causes sont corrigées : insertion de la barre au bon endroit
  du DOM, et contexte `'side'` restauré pour ces deux boîtes (le regroupement visuel sous l'onglet
  Pedigree ne dépend jamais de leur position DOM). Nouveau test d'exécution réelle du script contre
  un DOM fidèle à l'écran WordPress (`tests/gws-equestrian-cheval-admin-tabs-runtime-test.js`, 24
  assertions via `node`, aucune dépendance ajoutée). Voir
  `wp-content/plugins/gws-core/modules/gws-equestrian/CHANGELOG.md` (0.12.1) pour le détail complet
  de la cause racine.

## 1.15.0 (gws-core uniquement — gws-starter reste en 1.5.0, non modifié)

- **GWS Equestrian — Étape 6 : ajustements UX post-recette (CD à deux décimales, navigation par
  onglets).** Le coefficient de détermination des indices génétiques (BSO/BCC/BDR) s'affiche
  désormais systématiquement à deux décimales (« 0.90 », jamais « 0.9 ») — le stockage reste un
  nombre exact, seule la présentation change. L'écran d'édition d'une fiche cheval, devenu trop
  long, gagne une navigation par onglets (Identité, Commercial, Pedigree, Indices, Médias,
  Présentation) qui reste une pure couche de présentation : aucune meta modifiée, aucun second
  formulaire, aucun AJAX, aucune donnée jamais absente du DOM — le script masque/affiche les
  meta boxes déjà existantes sans jamais les déplacer, et la fiche reste pleinement utilisable
  sans JavaScript. Un bouton d'enregistrement rapide déclenche un clic sur le vrai bouton natif
  WordPress (aucun second mécanisme de sauvegarde). Navigation clavier et attributs ARIA complets
  (`tablist`/`tab`/`tabpanel`), disposition responsive. Voir
  `wp-content/plugins/gws-core/modules/gws-equestrian/CHANGELOG.md` (0.12.0) pour le détail complet.
- 100 nouvelles assertions automatisées (1 nouveau fichier de tests dédié aux onglets + extension
  du test des indices), suite complète toujours 100 % passante (1051 assertions au total).

## 1.14.0 (gws-core uniquement — gws-starter reste en 1.5.0, non modifié)

- **GWS Equestrian — Étape 6 : indices, médias et contenu de présentation du cheval.** Enrichit la
  fiche Cheval (une seule entité, tous les champs facultatifs) sans toucher au socle de l'Étape 4
  ni au pedigree de l'Étape 5. Indices sportifs ISO/ICC/IDR (valeur + année, séparés, une seule
  valeur par indice, aucun historique) et indices génétiques BSO/BCC/BDR (valeur signée décimale +
  coefficient de détermination, jamais d'année, signe positif ajouté uniquement à l'affichage).
  Galerie photos (jusqu'à 9 attachment IDs ordonnés en plus de la photo principale native,
  sélection via la médiathèque WordPress, aucune suppression de média) et vidéos (URL + titre
  facultatif, jusqu'à 10, réutilisant le composant répétable de l'Étape 2). Présentation
  éditoriale (Présentation, Points forts, Potentiel, Résultats, Origines — commentaire, Production
  — commentaire, Conditions de vente, Conseils de croisement) et Ostéo-articulaire (texte libre,
  jamais un dossier vétérinaire) — avec des noms de meta explicites pour ne jamais confondre le
  commentaire "Production"/"Origines" avec la Production calculée ou le pedigree structuré. Chaque
  nouvelle donnée dispose d'une fonction métier pure réutilisable hors formulaire (futur import,
  duplication, API). Aucune migration destructive, aucun rendu public développé à ce stade. Voir
  `wp-content/plugins/gws-core/modules/gws-equestrian/CHANGELOG.md` (0.11.0) pour le détail complet.
- 250 nouvelles assertions automatisées (3 nouveaux fichiers de tests + extension du test du
  composant répétable), suite complète toujours 100 % passante (950 assertions au total).

## 1.13.0 (gws-core uniquement — gws-starter reste en 1.5.0, non modifié)

- **GWS Equestrian — Étape 5 : correctif intégrité du pedigree — filtrage métier des parents GWS
  (sexe, année).** Deux règles métier supplémentaires identifiées en recette, uniquement pour les
  relations vers un cheval déjà enregistré dans GWS. Sexe : mâle/entier/hongre autorisés comme
  père, seule une femelle autorisée comme mère, sexe inconnu toujours autorisé. Année de
  naissance : un parent à l'année connue doit être né strictement avant son produit (aucun âge
  minimum de reproduction) ; année inconnue (candidat ou produit) = aucun filtre. Une règle métier
  unique et centrale (`gwseq_horse_parent_candidate_rejection_reason()`) combine désormais
  auto-référence, sexe, année et conflit père/mère (0.9.0), réutilisée par le formulaire, la
  validation serveur et le futur chemin d'import. UX admin : réutilise la désactivation d'options
  déjà en place, avec une courte indication de la raison ; sexe/année sont verrouillés contre toute
  réactivation par le script (propriétés fixes, contrairement au conflit avec l'autre rôle). Une
  modification ultérieure des données (ex. un entier castré) ne déclenche jamais de suppression ou
  modification automatique du pedigree — documenté comme piste future (audit d'intégrité). Les
  ascendants externes ne sont pas affectés. Voir
  `wp-content/plugins/gws-core/modules/gws-equestrian/CHANGELOG.md` (0.10.0) pour le détail complet.
- 34 nouvelles assertions automatisées, suite complète toujours 100 % passante (697 assertions au
  total) — aucune assertion affaiblie.

## 1.12.0 (gws-core uniquement — gws-starter reste en 1.5.0, non modifié)

- **GWS Equestrian — Étape 5 : correctif intégrité du pedigree — même cheval GWS comme père et
  mère.** La recette a révélé qu'il était possible de sélectionner le même cheval GWS comme père
  ET comme mère d'un même cheval. Corrigé par une validation serveur dans
  `gwseq_set_horse_parent()` (refuse l'enregistrement, ne modifie aucune meta, ne supprime jamais
  une relation existante) doublée d'une aide UX admin (le cheval déjà choisi dans un sélecteur est
  désactivé dans l'autre, resynchronisé en direct, sans jamais modifier une valeur déjà
  sélectionnée). La validation s'applique identiquement au chemin programmatique prévu pour un
  futur import. L'auto-parenté reste protégée comme avant ; deux ascendants externes portant le
  même nom ne sont jamais rapprochés. Deux corrections lexicales au passage : « Cheval déjà
  présent dans GWS » → « Cheval déjà enregistré », « Ascendant hors GWS » → « Nouvel ascendant »,
  et le texte de l'aperçu développeur simplifié. Voir
  `wp-content/plugins/gws-core/modules/gws-equestrian/CHANGELOG.md` (0.9.0) pour le détail complet.
- 32 nouvelles assertions automatisées, suite complète toujours 100 % passante (663 assertions au
  total) — aucune assertion affaiblie.

## 1.11.0 (gws-core uniquement — gws-starter reste en 1.5.0, non modifié)

- **GWS Equestrian — Étape 5 : correctif complémentaire — suppression d'un ascendant externe
  vide.** La reprise de recette a révélé qu'un ascendant externe créé (nom saisi) puis entièrement
  vidé par l'utilisateur, en restant sur le mode « Ascendant hors GWS », continuait d'exister en
  base et réapparaissait à la réouverture de la fiche. Cause exacte : un nœud sans nom n'a jamais
  pu être stocké (garantie déjà en place, inchangée), mais quand l'arbre entier devenait ainsi
  vide, seule la meta de mode était réinitialisée — l'ancienne structure JSON restait, elle,
  intacte en base (comportement pensé pour un changement de mode GWS ⇄ externe, pas adapté à un
  contenu vidé en restant sur le même mode). Corrigé en supprimant explicitement cette meta dans ce
  cas précis. Ajout d'un bouton explicite « Supprimer cet ascendant » (avec confirmation si des
  origines enfants sont déjà renseignées) pour vider un nœud et sa sous-branche en un clic, sans
  attendre un enregistrement. Une relation vers une fiche GWS n'est pas concernée : le choix « Non
  renseigné » continue de désactiver la relation sans jamais supprimer la fiche Cheval référencée.
  Voir `wp-content/plugins/gws-core/modules/gws-equestrian/CHANGELOG.md` (0.8.0) pour le détail
  complet.
- 21 nouvelles assertions automatisées, suite complète toujours 100 % passante (631 assertions au
  total) — aucune assertion affaiblie.

## 1.10.0 (gws-core uniquement — gws-starter reste en 1.5.0, non modifié)

- **GWS Equestrian — Étape 5 : correctif BLOQUANT (corruption Unicode des noms accentués).** La
  reprise de la recette sur le pedigree de Jamerose a révélé qu'un nom accentué (« Native de
  Félines ») était corrompu en base après enregistrement (« Native de Fu00e9lines »). Cause
  racine exacte : `wp_json_encode()` sans `JSON_UNESCAPED_UNICODE` échappait les caractères
  accentués en séquences `\uXXXX`, qu'`update_post_meta()` corrompait ensuite via son appel
  interne systématique à `wp_unslash()` (comportement natif de WordPress, indépendant de toute
  logique métier) — un antislash légitime confondu avec un artefact des magic quotes. Corrigé en
  ajoutant ce drapeau ; aucun rapport avec le helper de présentation des noms, qui n'était
  qu'un témoin fidèle d'une donnée déjà corrompue en amont. Également corrigés dans cette
  version : mise à jour EN DIRECT (JavaScript léger, sans jamais toucher la valeur saisie) des
  intitulés contextuels du pedigree pendant la frappe (un premier essai sans JavaScript s'étant
  révélé insuffisant en recette), et un nœud de génération 4 désormais strictement terminal
  (plus de « Père : Non renseigné » laissant croire à tort à une génération 5 hors périmètre).
  Voir `wp-content/plugins/gws-core/modules/gws-equestrian/CHANGELOG.md` (0.7.0) pour le détail
  complet, notamment l'analyse de cause racine complète et la découverte méthodologique sur la
  fidélité des stubs de test.
- 40 nouvelles assertions automatisées, suite existante toujours 100 % passante (603 assertions
  au total) — aucune assertion affaiblie.

## 1.9.0 (gws-core uniquement — gws-starter reste en 1.5.0, non modifié)

- **GWS Equestrian — Étape 5 : corrections post-recette runtime.** La saisie réelle d'un pedigree
  complet a révélé des problèmes UX importants, corrigés dans cette version : Race/Stud-book d'un
  ascendant externe harmonisé avec le référentiel de la fiche Cheval (fini le texte libre
  hétérogène « SF »/« Selle Français »...), compatibilité ascendante garantie pour les pedigrees
  déjà saisis (aucune perte, aucune migration destructive), intitulés contextuels à chaque niveau
  (« Père de UNTOUCHABLE 27 », jamais un Père/Mère nu ni une nomenclature généalogique complexe),
  compteur « Génération N sur 4 » avec arrêt visuel strict à la dernière génération, et une
  nouvelle convention de présentation des noms de chevaux (majuscules, sans accents — jamais une
  transformation de la donnée source). Deux pistes futures actées en roadmap sans aucun
  développement : connecteur IFCE/SIRE optionnel et bibliothèque facultative d'étalons/ascendants.
  Voir `wp-content/plugins/gws-core/modules/gws-equestrian/CHANGELOG.md` (0.6.0) pour le détail
  complet.
- 58 nouvelles assertions automatisées, suite existante toujours 100 % passante (563 assertions
  au total).

## 1.8.0 (gws-core uniquement — gws-starter reste en 1.5.0, non modifié)

- **GWS Equestrian — Étape 5 : Pedigree** (relations Père/Mère récursives, resolver, production),
  en attente de sa recette runtime. Chaque parent est soit un cheval déjà présent dans GWS
  (référence par ID, jamais de duplication de ses données), soit un ascendant hors GWS structuré
  qui peut lui-même avoir ses propres ascendants externes jusqu'à 4 générations (arbre encodé en
  JSON, sans jamais créer de fiche artificielle pour un ancêtre non géré comme cheval du client).
  Un resolver produit une structure de données déterministe et indépendante du HTML (protection
  contre les cycles directs et indirects, dégradation propre si un parent est supprimé, une seule
  branche active par relation sans jamais mélanger une source GWS et une source externe),
  réutilisable plus tard par le rendu web, un export PDF/catalogue ou une API. Nouvelle règle
  architecturale appliquée à ce code (décidée après l'Étape 4) : les relations peuvent être
  créées/modifiées programmatiquement sans dépendre du formulaire admin ni fabriquer un faux
  nonce — préparant un futur import en masse sans refactorer les étapes déjà validées. Voir
  `wp-content/plugins/gws-core/modules/gws-equestrian/CHANGELOG.md` (0.5.0) pour le détail
  complet.
- 100 nouvelles assertions automatisées, suite existante toujours 100 % passante (505 assertions
  au total).

## 1.7.1 (gws-core uniquement — gws-starter reste en 1.5.0, non modifié)

- **GWS Equestrian — Étape 4 gelée définitivement** après recette runtime concluante. Seule
  correction : présentation de l'âge du cheval (« 1 an »/« 7 ans » au lieu de « ≈ 7 an(s) (âge
  calendaire approximatif, jamais au jour près) ») — le calcul lui-même (convention métier
  équine : un an de plus au 1er janvier) était déjà correct et reste inchangé.
- **Mini-audit Import/Onboarding** (nouveau besoin produit confirmé pour une future version,
  aucun développement à ce stade) sur Groupe tarifaire/Prestation/Cheval : aucune modification
  nécessaire immédiatement ; une factorisation minimale de la persistance des meta de
  Prestation/Cheval est identifiée et proposée pour le jour où l'import sera engagé, mais
  volontairement non réalisée maintenant. Voir
  `wp-content/plugins/gws-core/modules/gws-equestrian/CHANGELOG.md` (0.4.1) et son `README.md`
  pour le détail complet.
- 11 nouvelles assertions automatisées, suite existante toujours 100 % passante (404 assertions
  au total).

## 1.7.0 (gws-core uniquement — gws-starter reste en 1.5.0, non modifié)

- **GWS Equestrian — Étape 4 : Cheval — socle métier, catégories, commercialisation, Global
  Horse ID.** Identité structurée (sexe/année de naissance/robe/race stud-book/taille/éleveur/
  propriétaire/UELN/SIRE), aucune meta parallèle au natif (Nom = `post_title`, Photo principale =
  image à la une native, Ordre = `menu_order`), catégories de chevaux enfin utilisables (interface
  à cases à cocher native, affordance de création rapide masquée sur la fiche pour éviter les
  doublons), commercialisation structurée et indépendante des catégories (statut/mode de prix/
  libellé « sur demande »), et Global Horse ID (`_gwseq_global_id`, UUID v4 assigné une seule fois
  au premier enregistrement réel, jamais régénéré, jamais exposé en REST, jamais un secret).
  Éditeur par blocs désactivé pour ce post type, avec un arbitrage propre à Cheval expliqué dans
  le CR (pas un copier-coller de celui de Prestation). Deux limitations documentées et assumées :
  aucune mention HT/TTC pour le prix d'un cheval, et l'ambiguïté SIRE (France)/UELN
  (international) en l'absence de réglage de pays/locale. Voir
  `wp-content/plugins/gws-core/modules/gws-equestrian/CHANGELOG.md` (0.4.0) pour le détail
  complet. Étape 4 en attente de sa recette runtime — Étape 5 (Pedigree) non commencée.
- 125 nouvelles assertions automatisées (`gws-equestrian-cheval-logic-test.php`), suite existante
  toujours 100 % passante.

## 1.6.3 (gws-core uniquement — gws-starter reste en 1.5.0, non modifié)

- **GWS Equestrian — Étape 3, corrections post-recette runtime.** Corrige le bug bloquant des
  modèles de prestations (cause racine : éditeur par blocs actif par défaut sur `gwseq_prestation`,
  qui ne déclenche jamais le hook utilisé par le sélecteur de modèle — corrigé en restaurant
  l'éditeur classique pour ce post type via le filtre natif `use_block_editor_for_post_type`),
  améliore la présentation Nom/Description de la fiche Prestation, et internationalise
  l'ensemble des chaînes d'interface du module (text domain `gws-core`, chargement des
  traductions ajouté dans `gws-core.php`). Voir
  `wp-content/plugins/gws-core/modules/gws-equestrian/CHANGELOG.md` (0.3.3). Étape 3 toujours en
  attente de recette runtime ciblée sur ces corrections.
- 37 nouvelles assertions automatisées, suite existante toujours 100 % passante.

## 1.6.2 (gws-core uniquement — gws-starter reste en 1.5.0, non modifié)

- **GWS Equestrian — Étape 3, dernier ajustement fonctionnel** : le mode de tarification `devis`
  est désormais présenté comme « Sur demande » (valeur technique inchangée, sans migration),
  avec un nouveau champ Libellé affiché permettant au professionnel de choisir sa formulation
  (« Sur demande », « Sur devis », « Nous contacter »...) ou de ne rien afficher. Reste
  indépendant du réglage global « Prix masqués ». Voir
  `wp-content/plugins/gws-core/modules/gws-equestrian/CHANGELOG.md` (0.3.2). Étape 3 toujours en
  attente de recette runtime.
- 12 nouvelles assertions automatisées, suite existante toujours 100 % passante.

## 1.6.1 (gws-core uniquement — gws-starter reste en 1.5.0, non modifié)

- **GWS Equestrian — Étape 3, ajustements avant recette runtime** : réglage global d'affichage
  des prix étendu à trois modes (TTC/HT/Prix masqués — prioritaire sur la visibilité individuelle,
  sans jamais supprimer les montants stockés), réglage de devise (EUR par défaut, GBP/USD/CHF
  disponibles, sans bibliothèque externe ni calcul), et correction des unités suggérées par
  plusieurs presets de reproduction. Voir
  `wp-content/plugins/gws-core/modules/gws-equestrian/CHANGELOG.md` (0.3.1). Étape 3 toujours en
  attente de recette runtime.
- 31 nouvelles assertions automatisées, suite existante toujours 100 % passante.

## 1.6.0 (gws-core uniquement — gws-starter reste en 1.5.0, non modifié)

- **GWS Equestrian — Étape 3 : Prestations / Groupes tarifaires.** Gestion métier complète des
  prestations (tarification à trois modes, unités, visibilité du prix, modèles de prestations
  en aide à la création) et des groupes tarifaires (nom/ordre/description, tous natifs
  WordPress). Réglage global d'affichage HT/TTC propre au module. Voir
  `wp-content/plugins/gws-core/modules/gws-equestrian/CHANGELOG.md` (0.3.0) pour le détail
  complet. Étape 3 toujours en attente de recette runtime — non promue Étape 4.
- 43 nouvelles assertions automatisées (`gws-equestrian-prestations-logic-test.php`), suite
  existante toujours 100 % passante.

## 1.5.2 (gws-core uniquement — gws-starter reste en 1.5.0, non modifié)

- **Correction de deux anomalies bloquantes du composant répétable `gws-equestrian`**, révélées
  par la première recette runtime de l'Étape 2 sous WordPress Local : perte de la structure des
  lignes à l'enregistrement (nommage HTML des champs corrigé pour partager un index explicite
  par ligne), et champ `number` limité aux entiers par le navigateur (`step="any"` ajouté). Voir
  `wp-content/plugins/gws-core/modules/gws-equestrian/CHANGELOG.md` (0.2.1) pour le détail
  complet. Étape 2 toujours en attente de validation — non promue Étape 3.

## 1.5.1 (gws-core uniquement — gws-starter reste en 1.5.0, non modifié)

- **Ajout du module métier optionnel `gws-equestrian`** (gestion et publication de contenu pour
  les professionnels du monde équestre), développé de façon incrémentale, chaque étape recettée
  avant la suivante — voir `wp-content/plugins/gws-core/modules/gws-equestrian/README.md` et son
  propre `CHANGELOG.md` pour le détail. Inactif par défaut (comme tout module métier), sans
  aucune conséquence sur un projet qui n'active pas ce module dans `config/modules.php`.
  - Étape 1 — Fondations : trois Custom Post Types (`gwseq_prestation`, `gwseq_groupe`,
    `gwseq_cheval`) et une taxonomie (`gwseq_categorie_cheval`), sans champ ni logique métier.
  - Étape 2 — Composant répétable : brique interne au module pour gérer une liste ordonnée de
    lignes structurées (futurs indices, vidéos, blocs personnalisés), sans dépendance à ACF ;
    démonstration réservée à l'environnement local/développement.
  - Registre des préfixes mis à jour (`wp-content/plugins/gws-core/modules/README.md`) : `gwseq_`
    pour ce module, jamais `gws_`/`gws_core_`.
  - Aucune modification du comportement générique du cœur ou du thème.
- 55 nouvelles assertions automatisées (26 dans `gws-equestrian-foundations-test.php`, 29 dans
  `gws-equestrian-repeater-logic-test.php`), toutes passées, suite existante inchangée et
  toujours 100 % passante.

## 1.5.0

Dernière passe de finition avant gel de la baseline GWS, après recette fonctionnelle réelle de
la v1.4.x dans WordPress Local.

- **X ajouté comme réseau structuré** (`gws-core`), au même niveau que LinkedIn/Facebook/
  Instagram/YouTube/TikTok : sanitizé/validé comme URL, exposé par `gws_core_social_links()`,
  alimente `gws_core_schema_same_as()`, dédupliqué avec une même URL saisie dans `social_links`.
- **Composant générique de pictogrammes sociaux** (`gws-starter`) :
  `template-parts/content/social-links.php`, réutilisable tel quel par tout projet. SVG locaux
  au thème (aucune police, bibliothèque ou requête externe), `currentColor` (aucune couleur de
  marque imposée), nom accessible par lien, aucun conteneur si aucun réseau n'est renseigné,
  liens externes ouverts en nouvel onglet (`rel="noopener noreferrer"`). N'inclut jamais
  WhatsApp (canal de contact) ni Google Business Profile (présence locale) — à afficher
  séparément si besoin. Deux nouveaux réglages : affichage dans l'en-tête (désactivé par
  défaut) et dans le pied de page (activé par défaut).
- **WhatsApp fiabilisé** : un numéro saisi sous forme nationale (ex. `06...`) ne produit plus de
  lien `wa.me` non fonctionnel. `gws_core_whatsapp_url()` exige désormais un format
  international explicite (préfixe `+` ou `00`), accepte espaces/tirets/parenthèses dans la
  saisie, et ne devine ni ne complète jamais d'indicatif pays — retourne une chaîne vide si le
  numéro n'est pas exploitable en l'état. Aide et QA mises à jour en conséquence.
- **Crédit Tagada Vroom** : le lien s'ouvre désormais dans un nouvel onglet
  (`target="_blank" rel="noopener noreferrer"`) ; réglages d'activation et URL inchangés.
- **Schema de l'accueil corrigé** : WebSite + Organization sont désormais émis sur la page
  d'accueil quelle que soit sa configuration WordPress (page statique ou index natif des
  derniers articles) — ce second cas ne recevait auparavant aucune donnée structurée. Aucun
  WebPage ni Breadcrumb n'est fabriqué pour un index qui ne correspond à aucune Page réelle ;
  comportement inchangé pour toutes les autres pages, et toujours aucune sortie si un plugin SEO
  compatible est actif.
- Page QA étendue (toujours un outil technique, pas une vitrine) pour vérifier X, le rendu
  conditionnel des pictogrammes sociaux, `currentColor`, les réglages d'affichage en-tête/pied
  de page, la normalisation WhatsApp, le crédit en nouvel onglet, et l'absence de doublon entre
  X structuré et `social_links` dans `sameAs`.
- **Nouveau document `AI-AGENT.md`** à la racine du dépôt : instructions impératives destinées à
  un agent IA qui développe un nouveau site à partir de GWS (rôle des composants, interdictions
  explicites, règles de sécurité/SEO, migrations, definition of done, méthode de travail,
  transmission à un développeur tiers). `README.md` mis à jour avec une procédure de démarrage
  simple, destinée à un utilisateur non technique, qui y renvoie explicitement.
- 21 nouvelles assertions automatisées (11 ajoutées à `settings-helpers-logic-test.php`, 10
  dans le nouveau `schema-homepage-logic-test.php`), toutes passées, en plus de la suite
  existante inchangée (44 assertions au total sur ces deux fichiers).

## 1.4.1

Le champ libre « Autres réseaux sociaux » (`social_links`, une URL par ligne) alimente
désormais réellement `gws_core_schema_same_as()` : lignes vides ignorées, chaque URL restante
sanitizée (`esc_url_raw()`) puis validée (`wp_http_validate_url()`), dédupliquée en interne et
avec les réseaux structurés/la fiche Google Business Profile — jamais de valeur vide ou
invalide dans le `sameAs` produit. Volontairement absent de `gws_core_social_links()` : les
réseaux nommés y restent seuls, pour rester facilement exploitables individuellement en front ;
`social_links` reste une extension générique réservée au Schema. 4 assertions ajoutées à
`tests/settings-helpers-logic-test.php` (23/23 au total pour ce fichier). Aucun autre
changement.

## 1.4.0

Passe de finition produit, sans changement d'architecture, après validation de la recette
fonctionnelle v1.3.0 dans WordPress Local.

- **Réglages de l'entité enrichis** (`gws-core`) : logo (champ média WordPress natif, ID
  d'attachement, aperçu/remplacement/suppression dans Réglages > Entité), numéro WhatsApp,
  LinkedIn, Facebook, Instagram, YouTube, TikTok, fiche Google Business Profile. Tous
  facultatifs ; un champ vide ne génère jamais de balise ni d'entrée Schema vide. Nouveaux
  helpers : `gws_core_get_logo_url()`, `gws_core_whatsapp_url()`, `gws_core_social_links()`,
  `gws_core_google_business_url()`, `gws_core_schema_same_as()`.
- **Nouveau type de champ `attachment_id`** dans le générateur minimal de champs
  (`includes/fields.php`) : ID de média vérifié comme étant une image, réutilisable par un
  futur module.
- **Logo dans l'en-tête du thème** : affiché s'il est renseigné, sinon secours propre sur le nom
  de l'entité en texte (comportement inchangé). Mise à jour minimale de `layout.css` pour la
  taille du logo, sans modification du reste du gabarit.
- **`sameAs` Schema.org** construit uniquement à partir des URLs réellement renseignées et
  validées à l'enregistrement (`esc_url_raw()`), aussi bien dans le fallback maison
  (`inc/schema.php`) que dans l'enrichissement du graphe Yoast (`inc/seo-yoast-bridge.php`, de
  façon additive — jamais d'écrasement d'un `sameAs`/`logo` déjà fourni par Yoast). Au passage,
  correction d'un défaut préexistant dans ces deux mêmes fonctions : `telephone`/`email`
  pouvaient être émis vides quand les réglages correspondants ne l'étaient pas — désormais omis
  s'ils sont vides, comme les nouveaux champs.
- **Crédit de réalisation Tagada Vroom** dans le pied de page : nouveau réglage « Afficher le
  crédit » (case à cocher, activée par défaut sur un nouveau projet) et « URL Tagada Vroom »
  (pré-remplie à `https://tagadavroom.fr/`). Ne s'affiche que si les deux conditions sont
  réunies ; aucun markup sinon. Ancre naturelle « Tagada Vroom », pas d'ouverture forcée dans un
  nouvel onglet.
- **Signature du produit** : `Author: Tagada Vroom` ajouté aux en-têtes du thème et du plugin
  (noms techniques `gws-starter`/`gws-core` et préfixes `gws_`/`gws_core_` inchangés), ajout
  d'un `screenshot.png` (1200×900) neutre pour le thème dans `Apparence > Thèmes`, documentation
  mise à jour pour mentionner Tagada Vroom comme éditeur.
- Page QA étendue (section technique, pas marketing) pour vérifier logo/fallback, réseaux
  sociaux récupérables, `sameAs`, WhatsApp, et les deux conditions d'affichage du crédit —
  disponible uniquement quand le module QA est actif.

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

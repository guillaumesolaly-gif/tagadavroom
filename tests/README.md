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
php tests/gws-equestrian-pedigree-logic-test.php
php tests/gws-equestrian-cheval-indices-logic-test.php
php tests/gws-equestrian-cheval-media-logic-test.php
php tests/gws-equestrian-cheval-editorial-logic-test.php
php tests/gws-equestrian-cheval-admin-tabs-test.php
node tests/gws-equestrian-cheval-admin-tabs-runtime-test.js
```

(`tests/qa-toggle-logic-test.php` est appelé automatiquement par `starter-logic-test.php`, dans
un processus PHP séparé — il peut aussi être lancé seul.)

**`gws-equestrian-cheval-admin-tabs-runtime-test.js`** est le SEUL fichier de ce dossier qui ne
s'exécute pas via `php` : il nécessite `node` (aucune dépendance npm, aucun `package.json` — un
script Node autonome). Contrairement aux autres fichiers, qui ne peuvent que scanner le TEXTE
SOURCE d'un script JavaScript (présence de motifs, jamais son exécution réelle), celui-ci exécute
réellement `assets/cheval-tabs-admin.js` contre une reproduction fidèle et minimale du DOM produit
par l'écran classique d'édition de WordPress (colonnes latérale/principale bien distinctes, avec le
vrai bouton `#publish`) — c'est ce type de test qui a permis de détecter puis de corriger la
régression bloquante 0.12.1 (voir plus bas), invisible aux 73 assertions basées sur du texte de
`gws-equestrian-cheval-admin-tabs-test.php`.

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
  Depuis l'Étape 6 : paramètre optionnel `$max_rows` de `gwseq_render_repeater_field()` (aide UX
  qui désactive le bouton d'ajout une fois la limite atteinte, jamais la garantie réelle qui reste
  la sanitation propre à chaque appelant) — attribut `data-gwseq-repeater-max` bien rendu quand
  fourni, absent par défaut (comportement historique inchangé), et vérification déclarative que le
  script ne fait que désactiver un bouton ou retirer un élément du DOM, jamais un appel serveur.
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
  Complété après relecture (micro-correction 0.4.1) : présentation de l'âge (« 1 an »/« 7 ans »
  accordés via `_n()`, exemples exacts de la demande, absence du symbole « ≈ », de la forme non
  accordée « an(s) » et de toute mention permanente d'approximation ; aide discrète disponible en
  attribut `title` uniquement) — le calcul sous-jacent (convention métier équine) reste inchangé.

- Pedigree de `gws-equestrian`, Étape 5 (`gws-equestrian-pedigree-logic-test.php`) : relations
  Père/Mère (référence à un cheval GWS existant, jamais par nom, jamais d'auto-référence, jamais
  d'ID d'un autre post type accepté), ascendants externes structurés récursifs (un ascendant
  externe peut lui-même avoir un père et une mère externes jusqu'à 4 générations, arbre borné et
  sanitizé à chaque niveau, une profondeur excessive fournie en entrée est silencieusement
  ignorée), conservation non destructive lors d'un changement de source GWS ⇄ externe (l'ancienne
  branche reste stockée mais inactive, le resolver ne mélange jamais les deux), chemin
  programmatique (une relation, y compris une structure externe imbriquée, peut être créée sans
  `$_POST` ni nonce fabriqué), resolver (structure de données déterministe indépendante du HTML,
  mélange naturel de branches GWS et externes dans un même pedigree, profondeur maximale avec
  troncature même face à une donnée corrompue en base, cycles directs et indirects détectés sans
  boucle infinie, dégradation propre si un parent est supprimé définitivement, mise à jour d'un
  parent GWS répercutée automatiquement sans resynchronisation, mémoïsation d'un ascendant croisé
  deux fois dans un pedigree), production (descendants calculés à la volée, jamais stockés, jamais
  de rapprochement entre ascendants externes identiques), sécurité de la sauvegarde (nonce,
  permissions, autosave, révision, sanitation serveur y compris sur une structure externe profonde
  soumise via un vrai `$_POST`), divulgation progressive de l'interface (élément natif `<details>`,
  aucun JavaScript requis pour son repli/dépli), et internationalisation. Complété après la
  recette runtime (correction 0.6.0) : Race/Stud-book d'un ascendant externe réutilisant le
  référentiel mutualisé de la fiche Cheval (jamais une seconde liste codée en dur, vérifié
  directement dans le code source) avec le mécanisme "Autre" à toutes les générations,
  compatibilité ascendante avec l'ancien format texte libre (valeur canonique reconnue,
  abréviation non reconnue jamais perdue et récupérable via "Autre", pedigree multi-générations
  façon Jamerose converti sans perte, aucune réécriture automatique de la base à la lecture),
  contexte de saisie reproduisant l'exemple exact de la demande (« Père de UNTOUCHABLE 27 », « Père
  de HORS LA LOI II » après divulgation, absence de nomenclature généalogique complexe, repli
  explicite tant qu'un nom n'est pas renseigné), compteur « Génération N sur 4 » et arrêt visuel
  strict à la génération 4 même si une donnée de génération 5 existe déjà en base. Le helper de
  présentation des noms (`gwseq_format_horse_name_display()`) est testé dans
  `gws-equestrian-cheval-logic-test.php` (majuscules, sans accents, apostrophes/traits d'union/
  chiffres conservés, jamais utilisé dans la sanitation — la donnée source n'est jamais modifiée).
  Correctif bloquant 0.7.0 (corruption Unicode des noms accentués) : reproduction exacte du bug
  grâce à des stubs `wp_unslash()`/`update_post_meta()`/`wp_json_encode()` rendus fidèles au
  comportement réel de WordPress (c'est leur infidélité, pas un manque de test, qui laissait
  passer le bug), non-altération de la donnée source sur plusieurs enregistrements consécutifs et
  à travers un changement de mode, vérification directe du JSON stocké (caractère littéral, jamais
  une séquence échappée), séparation source/présentation vérifiée hors commentaires PHP, câblage
  des attributs `data-*`/classes nécessaires à la mise à jour dynamique du contexte, et génération
  terminale (une chaîne GWS ET une branche externe résolues à la génération 4 n'ont plus aucune
  clé père/mère, ni même `null` — le rendu de vérification n'affiche donc plus de « Non renseigné »
  laissant croire à tort à une génération 5 hors périmètre). Correctif complémentaire 0.8.0
  (suppression d'un ascendant externe vide) : un ascendant vidé (nom effacé) en restant sur le mode
  externe supprime bien la meta stockée (`delete_post_meta()`, vérifié directement) au lieu de
  laisser une ancienne structure JSON réapparaître, un nœud partiellement renseigné ou possédant un
  descendant renseigné reste conservé, une autre branche du pedigree (l'autre parent) n'est jamais
  affectée, une relation GWS désactivée ne supprime jamais la fiche Cheval référencée, le resolver
  ne produit jamais de nœud "external" fantôme même sur une donnée héritée d'avant ce correctif, et
  câblage du bouton explicite « Supprimer cet ascendant » (contrôle rendu, texte de confirmation
  fourni en attribut `data-*`, écoute JS ciblant uniquement le nœud le plus proche). Correctif
  intégrité du pedigree 0.9.0 (même cheval GWS impossible comme père ET mère) : auto-parenté
  toujours protégée de bout en bout via `gwseq_set_horse_parent()`, affectation refusée (valeur de
  retour `false`, documentée) dans les deux sens (père déjà mère, mère déjà père), une tentative
  refusée ne supprime ni ne remplace jamais silencieusement une relation existante, deux chevaux
  GWS distincts toujours acceptés, un mélange GWS + externe (même en cas d'homonymie avec un cheval
  GWS) toujours accepté sans rapprochement par nom, validation identique via un appel
  programmatique direct (chemin d'un futur import, sans `$_POST` ni JavaScript), aucune régression
  sur la Production ni sur la conservation non destructive lors d'un changement de mode, câblage de
  l'exclusion mutuelle des deux sélecteurs GWS (classe/attribut de rôle, option déjà rendue
  désactivée dès le serveur, script ne modifiant jamais automatiquement une valeur sélectionnée),
  et les deux corrections lexicales validées (« Cheval déjà enregistré », « Nouvel ascendant »,
  texte de l'aperçu développeur). Correctif filtrage métier 0.10.0 (sexe et année de naissance,
  uniquement pour une relation GWS) : femelle refusée comme père/acceptée comme mère, mâle/entier
  accepté comme père/refusé comme mère, hongre accepté comme père/refusé comme mère, sexe inconnu
  accepté dans les deux rôles, candidat né avant le produit accepté, même année ou année
  postérieure refusée, année du candidat ou du produit inconnue sans filtre, combinaison sexe +
  année (exemple exact de la demande), auto-parenté et conflit père/mère toujours protégés en
  combinaison avec ces nouvelles règles, deux parents compatibles distincts acceptés, ascendants
  externes jamais affectés (aucune comparaison par nom, aucun champ sexe ajouté), validation
  identique par appel programmatique, aucune régression Production/resolver/changement de
  mode/conservation des branches externes, câblage UX (option désactivée avec indication de la
  raison, verrouillage `data-gwseq-locked-disabled` empêchant toute réactivation par le script pour
  le sexe/l'année, contrairement au conflit père/mère qui reste resynchronisé en direct).
- Indices sportifs et génétiques de `gws-equestrian`, Étape 6
  (`gws-equestrian-cheval-indices-logic-test.php`) : ISO/ICC/IDR (valeur et année sanitisées et
  stockées séparément, indépendance totale entre les trois, valeur sans année et année sans valeur
  toutes deux acceptées, aucun historique implicite — un second enregistrement remplace toujours
  le précédent, année bornée à l'année en cours jamais une année future contrairement à l'année de
  naissance) ; BSO/BCC/BDR (valeur signée décimale et coefficient de détermination stockés
  séparément, signe positif jamais perdu au stockage — ajouté uniquement à l'affichage par
  `gwseq_cheval_genetic_indice_label()` — valeur négative et coefficient décimal exacts, aucune
  meta d'année jamais créée pour ces trois indices) ; robustesse (clé d'indice ou cheval_id
  invalide refusés proprement) ; rendu admin (champs valeur/année et valeur/CD bien rendus
  séparément, valeurs pré-remplies) ; persistance et compatibilité avec une fiche jamais
  enregistrée avec ces champs ; chemin programmatique sans `$_POST` ni nonce. Ajustement UX
  post-recette (0.12.0) : le CD est présenté systématiquement à deux décimales
  (`gwseq_format_cheval_indice_cd()` : 0.9 → "0.90", 0.987 → "0.99" pour l'affichage uniquement),
  le STOCKAGE reste le nombre exact (relire la valeur brute après arrondi d'affichage renvoie
  toujours 0.987, jamais de perte de précision en base), le libellé génétique public respecte la
  même précision (exemple exact de la demande : « BSO +12 (0.90) »), et le rendu admin affiche
  bien le champ CD formaté avec un pas de saisie (`step`) cohérent.
- Médias de `gws-equestrian`, Étape 6 (`gws-equestrian-cheval-media-logic-test.php`) : galerie
  (ajout/suppression/réordonnancement, bornée à 9 images complémentaires à la photo principale,
  attachment IDs uniquement — jamais une URL ni une valeur mal formée —, aucun doublon, un ID qui
  n'est pas une image ou qui n'existe pas rejeté, indépendance totale avec la photo principale
  native (`_thumbnail_id`), aucun appel à `wp_delete_attachment()` dans le fichier — vérifié à la
  fois fonctionnellement et par lecture directe du code hors commentaires) ; vidéos (URL + titre
  facultatif, ordre conservé, bornées à 10, URL invalide ou absente entraînant le rejet de la
  ligne entière même avec un titre saisi, réutilisation du composant répétable générique avec une
  sanitation dédiée) ; câblage admin (bouton d'ajout, attributs `data-*` de limite, gabarit
  `<template>` réutilisé, script utilisant `wp.media()` en sélection multiple, aucun uploader
  personnalisé) ; persistance croisée (modifier la galerie ne fait jamais perdre les vidéos, et
  réciproquement) et compatibilité avec une fiche jamais enregistrée ; chemin programmatique.
- Présentation éditoriale et informations complémentaires de `gws-equestrian`, Étape 6
  (`gws-equestrian-cheval-editorial-logic-test.php`) : chacun des 9 champs enregistré/lu
  indépendamment, champ vide accepté, sanitation correcte (une balise `<script>` et son contenu
  retirés, texte conservé), "Conseils de croisement" jamais conditionné au sexe (aucune lecture du
  sexe dans le fichier) ; séparation stricte et vérifiée par lecture de code (hors commentaires)
  entre le commentaire "Production" éditorial et la Production CALCULÉE
  (`gwseq_get_horse_offspring()`, jamais appelée dans ce fichier, et réciproquement
  `cheval-pedigree.php` ne connaît jamais `_gwseq_commentaire_production`), et entre le commentaire
  "Origines" éditorial et le pedigree STRUCTURÉ (aucune meta `_gwseq_pere_*`/`_gwseq_mere_*` jamais
  lue/écrite ici, et réciproquement ni `cheval-pedigree.php` ni le resolver ne connaissent
  `_gwseq_origines_commentaire`) ; Ostéo-articulaire vérifié comme texte libre uniquement (absence
  de tout champ structuré de dossier vétérinaire dans le modèle de données) ; rendu admin réparti
  sur deux meta boxes distinctes (Présentation / Informations complémentaires) ; escaping ;
  persistance et compatibilité avec une fiche jamais enregistrée ; chemin programmatique.
- Navigation par onglets de la fiche Cheval, ajustement UX post-recette de l'Étape 6
  (`gws-equestrian-cheval-admin-tabs-test.php`, 0.12.0 à 0.12.3) : configuration PHP du
  regroupement onglet → meta boxes (les 6 onglets attendus, avec exactement les bonnes boîtes
  chacun — en particulier l'onglet Pedigree qui regroupe Pedigree, Production calculée et l'aperçu
  développeur, et l'onglet Médias qui regroupe la boîte NATIVE "postimagediv" — Photo principale —
  avec Galerie/Vidéos), "postimagediv" vérifié présent dans EXACTEMENT un seul onglet (jamais
  dupliqué), Global ID dev-only/boîte "Ordre" volontairement rattachés à aucun onglet, chargement
  conditionnel des assets, configuration transmise au script via `wp_localize_script()` identique à
  la source de vérité PHP (y compris `isDevEnvironment`/`fallbackNotice`, 0.12.3), contexte `'side'`
  des meta boxes Production/aperçu développeur (restauré par le correctif 0.12.1) vérifié par
  lecture directe de `cheval-pedigree.php`, et — nouveau en 0.12.3 — enregistrement effectif du
  filtre natif WordPress `postbox_classes_{page}_{id}` pour CHAQUE boîte gérée par un onglet
  (`gwseq_register_cheval_admin_tab_postbox_classes()`) : appliquer ce filtre doit retourner
  exactement `array('gwseq-tab-{id}')`, jamais une autre valeur — la même configuration PHP est
  ainsi la source à la fois du script ET du marquage réel du DOM, jamais deux vérités
  indépendantes. Le JavaScript est vérifié par lecture déclarative directe de son code (même
  méthodologie que pour `cheval-admin.js`/`repeater-field.js`, hors commentaires) : aucun appel
  AJAX, aucune meta box jamais déplacée dans le DOM, aucune donnée jamais retirée du DOM, bouton
  d'enregistrement rapide déclenchant un clic sur le vrai `#publish` natif, attributs ARIA
  `tablist`/`tab`/`tabpanel`/`aria-selected`/`aria-controls`/`aria-labelledby`, navigation clavier
  complète, réutilisation des classes natives `.nav-tab-wrapper`/`.nav-tab`, dégradation
  silencieuse si `sessionStorage` ou la structure d'écran attendue sont absents, filtrage silencieux
  d'un identifiant de boîte introuvable, absence du pattern d'insertion DOM fautif (0.12.1), levée
  de `.closed` (0.12.2) ET `.hide-if-js` (0.12.3, la classe qui masque réellement une boîte ENTIÈRE,
  en-tête compris), vérification de visibilité réelle via `offsetParent`, capacité à forcer
  l'affichage avec la priorité `!important` (seule façon de battre une règle `!important` de la
  feuille de style native), présence de la fonction `disableTabsFallback()` et de son appel
  (filet de sécurité n°2), et présence de la vérification de cohérence `gwseq-tab-{id}` avant toute
  construction d'onglet (filet de sécurité n°1).
  **Complété par `gws-equestrian-cheval-admin-tabs-runtime-test.js`** (35 assertions, via `node` —
  voir la section « Exécuter » ci-dessus) : ce fichier va au-delà de la lecture déclarative en
  EXÉCUTANT réellement `cheval-tabs-admin.js` contre une reproduction fidèle du DOM et du markup
  RÉEL d'une meta box WordPress (`postbox-header`/`handlediv`/`inside`, avec de vrais champs à
  l'intérieur), et MODÉLISE l'effet réel des classes `.closed`/`.hide-if-js` sur `offsetParent` —
  c'est ce type de test qui a permis de détecter, puis de corriger, deux régressions bloquantes
  successives (0.12.1 : `DOMException` sur `insertBefore`, jamais de barre d'onglets ; 0.12.3 : une
  boîte masquée par `.hide-if-js` restait invisible malgré un correctif partiel `.closed`-only,
  invisible aux 73+ assertions basées sur du texte). Trois scénarios distincts, chacun vérifié
  indépendamment détecté par régression (désactivation temporaire du correctif testé, confirmation
  de l'échec, restauration) : (1) cas nominal avec une boîte Identité à la fois repliée ET masquée
  par Screen Options, vérifiant une visibilité RÉELLE (`offsetParent`, pas seulement un
  `style.display` déclaré) et la présence continue de champs historiques représentatifs dans le
  DOM ; (2) une boîte durablement masquée par un ancêtre hors de portée de tout correctif connu,
  vérifiant le déclenchement du filet de sécurité n°2 (barre retirée, toutes les boîtes restaurées,
  message de secours en environnement dev) ; (3) une incohérence entre la configuration transmise
  et le marquage réel du DOM, vérifiant qu'aucun onglet n'est alors construit et qu'aucune boîte
  n'est masquée (filet de sécurité n°1).

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
  de prix, source Père/Mère), ordre visuel réel des meta boxes (Identité/Commercialisation/
  Pedigree/Catégories/Ordre/Image à la une), et disparition effective de l'affordance de création
  rapide de catégorie une fois le CSS chargé dans une vraie page d'administration.
- Comportement navigateur réel de la divulgation progressive du pedigree (élément `<details>`
  imbriqué sur plusieurs niveaux) : ouverture/fermeture au clic, accessibilité clavier, lisibilité
  visuelle d'un arbre externe profondément imbriqué.
- Comportement navigateur réel de la mise à jour dynamique des intitulés contextuels du pedigree
  (depuis 0.7.0) : réactivité perçue pendant la frappe, absence d'erreur JavaScript, confirmation
  visuelle qu'un champ Nom garde exactement la valeur tapée (accents compris) quel que soit ce qui
  s'affiche par ailleurs à l'écran.
- Comportement navigateur réel du bouton « Supprimer cet ascendant » (depuis 0.8.0) : apparence de
  la boîte de dialogue native `confirm()`, ressenti de la remise à vide immédiate des champs, et
  confirmation qu'un enregistrement ultérieur reflète bien la suppression (couvert côté logique
  serveur par les tests automatisés, mais jamais observé dans un vrai navigateur).
- Comportement navigateur réel de l'exclusion mutuelle des sélecteurs Père/Mère GWS (depuis
  0.9.0) : apparence effective d'une `<option>` désactivée selon le navigateur/OS, réactivité
  perçue en changeant l'un puis l'autre sélecteur, absence d'erreur JavaScript.
- Lisibilité réelle en conditions d'usage des indications de raison (« sexe incompatible », «
  année incompatible », depuis 0.10.0) au sein du texte d'une `<option>` désactivée — rendu visuel
  selon le navigateur/OS, longueur acceptable dans une liste avec de nombreux chevaux.
- Comportement navigateur réel de la galerie photos (depuis l'Étape 6) : ouverture effective de la
  modale native de la médiathèque WordPress (`wp.media()`), sélection multiple à la souris/au
  clavier, apparence des vignettes et des boutons de réordonnancement (↑/↓), ressenti du bouton
  d'ajout désactivé une fois la limite de 9 atteinte, absence d'erreur JavaScript en conditions
  réelles.
- Comportement navigateur réel du composant répétable Vidéos avec sa nouvelle limite (depuis
  l'Étape 6) : ressenti du bouton d'ajout désactivé à la 10e vidéo, absence d'erreur JavaScript.
- Rendu navigateur réel des nouvelles meta boxes de la fiche Cheval (Indices, Médias, Présentation,
  Informations complémentaires, Étape 6) : ordre visuel des blocs, lisibilité pour un professionnel
  non expert WordPress, largeur des champs `<textarea>`.
- Comportement navigateur RÉEL de la navigation par onglets de la fiche Cheval (ajustement UX
  post-recette, 0.12.0 à 0.12.3) : depuis 0.12.1, `gws-equestrian-cheval-admin-tabs-runtime-test.js`
  exécute réellement le script contre un DOM simulé fidèle et vérifie la bascule de panneau au
  clic/clavier, la mémorisation dans `sessionStorage`, l'absence d'exception, et (depuis 0.12.3) une
  visibilité RÉELLEMENT vérifiée (`offsetParent`) d'une boîte repliée et/ou masquée par Screen
  Options, ainsi que les deux filets de sécurité (mapping incohérent, boîte durablement invisible)
  — mais UN VRAI NAVIGATEUR reste seul juge de :
  l'apparence visuelle effective des onglets natifs `.nav-tab` (couleurs, état actif) telle que
  rendue par le vrai CSS d'administration WordPress, la visibilité concrète du focus clavier à
  l'écran, la persistance de l'onglet actif via `sessionStorage` d'un VRAI rechargement de page à
  l'autre, le ressenti du bouton d'enregistrement rapide (libellé affiché, clic déclenchant bien la
  sauvegarde native), l'ergonomie réelle de la Photo principale regroupée dans l'onglet Médias
  (ouverture de la médiathèque native, remplacement de l'image, cohabitation visuelle avec
  Galerie/Vidéos), la disposition et l'utilisabilité sur un écran étroit (≤ 782 px), et l'absence de
  toute erreur JavaScript en conditions réelles (autres scripts admin, extensions, thème).
  L'utilisabilité complète de la
  fiche sans JavaScript (blocs simplement empilés, formulaire toujours soumissible) reste elle
  aussi à confirmer en conditions réelles (navigateur avec JS désactivé).

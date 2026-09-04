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
php tests/gws-equestrian-race-referentiel-test.php
php tests/gws-equestrian-pedigree-logic-test.php
php tests/gws-equestrian-cheval-indices-logic-test.php
php tests/gws-equestrian-cheval-media-logic-test.php
php tests/gws-equestrian-cheval-editorial-logic-test.php
php tests/gws-equestrian-cheval-admin-tabs-test.php
node tests/gws-equestrian-cheval-admin-tabs-runtime-test.js
php tests/gws-equestrian-ifce-import-test.php
node tests/gws-equestrian-race-referentiel-autocomplete-runtime-test.js
php tests/gws-equestrian-cheval-labels-test.php
php tests/gws-equestrian-membre-logic-test.php
php tests/gws-equestrian-actualites-logic-test.php
php tests/gws-equestrian-cheval-share-logic-test.php
php tests/gws-equestrian-cheval-share-admin-test.php
node tests/gws-equestrian-cheval-share-runtime-test.js
```

(`tests/qa-toggle-logic-test.php` est appelé automatiquement par `starter-logic-test.php`, dans
un processus PHP séparé — il peut aussi être lancé seul.)

**`gws-equestrian-cheval-admin-tabs-runtime-test.js`**,
**`gws-equestrian-race-referentiel-autocomplete-runtime-test.js`** et
**`gws-equestrian-cheval-share-runtime-test.js`** sont les SEULS fichiers de ce
dossier qui ne s'exécutent pas via `php` : ils nécessitent `node` (aucune dépendance npm, aucun
`package.json` — des scripts Node autonomes, DOM minimal fait main via le module `vm`). Contrairement
aux autres fichiers, qui ne peuvent que scanner le TEXTE SOURCE d'un script JavaScript (présence de
motifs, jamais son exécution réelle), ceux-ci exécutent RÉELLEMENT le fichier JS du module contre
une reproduction fidèle et minimale du DOM produit par l'écran d'édition WordPress correspondant —
c'est ce type de test qui a permis de détecter puis de corriger la régression bloquante 0.12.1 (voir
plus bas, `cheval-tabs-admin.js`) et le bug de recette runtime « autocomplétion Race inutilisable en
édition » (correctif référentiel post-livraison, `race-referentiel-autocomplete.js`) — invisibles
tous deux à des assertions basées uniquement sur du texte source ou sur les helpers PHP.

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
  Complété en 0.12.4 : `gwseq_cleanup_legacy_identite_metabox_user_state()`, un nettoyage ciblé de
  préférences WordPress PROPRES à l'utilisateur connecté (jamais une donnée métier) susceptibles
  d'avoir dérivé pendant les recettes successives de l'ajustement onglets — écran hors sujet jamais
  touché, aucune erreur si aucun utilisateur n'est connecté, case "Identité" décochée dans le
  panneau "Options de l'écran" réactivée sans affecter les autres boîtes masquées par l'utilisateur,
  idempotence (aucune réécriture si la préférence est déjà propre), entrée héritée de "Identité"
  dans un contexte de `meta-box-order` autre que `'normal'` (ex. `'side'`, ancien glisser-déposer)
  retirée sans affecter le reste de ce contexte ni l'ordre du contexte `'normal'` lui-même. Le
  contexte d'enregistrement réel (`add_meta_box()`, `'normal'`) n'est jamais modifié — il ne l'a
  jamais été depuis l'Étape 4.

- **Référentiel Race / Stud-book / Appellation de `gws-equestrian` (correctif référentiel,
  `gws-equestrian-race-referentiel-test.php`)** : `includes/race-referentiel.php` — 154 entrées
  (151 races/stud-books + 3 appellations OC/ONC/OE) générées une fois depuis le fichier XLSX fourni
  avec la demande, structure de chaque entrée (code canonique STRUCTURÉ, libellés IFCE/GWS, type
  race/appellation, alias), résolution exacte d'un alias/libellé/code vers le code canonique
  (`gwseq_race_referentiel_resolve_alias()` — couverture minimale SF, l'exemple important SFA->SF,
  OLD, HOLST, KWPN, WESTF, Z, OC, ONC, insensible à la casse), aucun code ou alias CONNU jamais
  transformé en "Autre" (vérifié sur l'intégralité des 154 entrées, pas seulement les exemples),
  recherche partielle pour l'autocomplétion (`gwseq_race_referentiel_search()` — code, libellé,
  accents/casse ignorés, préfixe classé en tête, exemples exacts de la demande "sel"->Selle
  Français, "conn"->Connemara/Connemara Part-Bred, "oc"->Origines Constatées intégrée au même
  moteur que les races), sanitation d'un code brut vers le code canonique
  (`gwseq_sanitize_race_referentiel_code()`, UNIQUE implémentation utilisée à l'identique par
  l'identité du cheval et les ascendants externes — vérifié par lecture directe des deux fichiers
  appelants), "Autre" toujours disponible comme seul filet de sécurité, et récents/suggestions par
  utilisateur (`gwseq_race_referentiel_record_recent_code()`/`_suggestions_for_user()` — préférence
  PROPRE à l'utilisateur en user meta, jamais en post meta donc jamais une modification de la
  donnée Cheval, idempotent, plafonné, un code inconnu ou "autre" jamais enregistré comme récent,
  repli neutre tant qu'aucun historique n'existe, jamais un profil métier rigide CSO/dressage/
  poney codé en dur, enregistrement récursif depuis un arbre d'ascendants externes déjà sanitisé).
  **Correctif runtime post-livraison — « autocomplétion inutilisable en édition »
  (`gws-equestrian-race-referentiel-autocomplete-runtime-test.js`)** : la recette a révélé que si le
  référentiel métier (helpers PHP) fonctionnait parfaitement, le COMPOSANT JAVASCRIPT réellement
  chargé dans l'écran d'édition, lui, ne l'était pas — invisible à la suite PHP, qui ne teste que les
  helpers. DEUX causes racines identifiées et corrigées : (1) le champ ne sélectionnait jamais son
  texte existant au focus, si bien que reprendre l'édition d'un champ déjà rempli (ex. "Selle
  Français" importé) concaténait toute nouvelle frappe ("OLD") à la valeur affichée au lieu de la
  remplacer — d'où l'impression qu'aucune suggestion n'apparaissait ; (2) la mise à jour du code
  caché après une saisie libre était différée de 150 ms après `blur`, largement plus long que le
  délai entre ce `blur` et la soumission native du formulaire déclenchée par un clic sur
  "Enregistrer"/"Publier" — le formulaire partait alors avec l'ANCIEN code, jamais mis à jour,
  rendant impossible toute modification ou suppression d'une race déjà enregistrée. Ce nouveau
  fichier exécute RÉELLEMENT `assets/race-referentiel-autocomplete.js` (via le module `vm` de Node,
  DOM minimal fait main, aucune dépendance npm — même méthodologie que
  `gws-equestrian-cheval-admin-tabs-runtime-test.js`) et vérifie : sélection intégrale du texte au
  focus d'un champ déjà rempli ; recherche réellement exécutée après une frappe sur un champ
  précédemment rempli ; sélection d'un résultat par clic mise à jour de façon SYNCHRONE (aucun délai,
  aucune dépendance à `blur`) ; la touche Entrée valide le premier résultat affiché et empêche
  activement la soumission native du formulaire (plus jamais un enregistrement accidentel de toute
  la fiche) ; un filet de sécurité committe chaque champ Race à la soumission du formulaire même sans
  `blur` préalable (permet de vider le champ, "Non renseignée") ; une saisie libre jamais validée par
  un clic retombe honnêtement sur "Autre" + le texte tapé, JAMAIS un retour silencieux à l'ancienne
  valeur ; un champ Race malformé sur la même page n'empêche jamais l'initialisation des AUTRES
  champs (résilience `try`/`catch` de la boucle d'initialisation). Vérifié positivement contre le
  test lui-même : rejouer ce fichier contre l'ancienne version (pré-correctif) du script fait
  effectivement échouer les scénarios concernés (touche Entrée, filet de sécurité à la soumission),
  la preuve que ce test détecte réellement la régression et n'est pas vacueusement vert.
  **Correctif runtime complémentaire (0.14.2) — cause racine réelle, invisible à ce test simulé** :
  la recette a montré que le composant restait non fonctionnel sur un VRAI wp-admin alors que ce
  test restait vert — la cause était un caractère Unicode LITTÉRAL multi-octet directement dans le
  code exécutable d'une expression régulière du script (plage de diacritiques combinants), fragile à
  tout maillon d'hébergement/transfert qui ne le préserverait pas fidèlement en UTF-8 (produisant une
  erreur de syntaxe qui tue silencieusement tout le script au chargement) — un risque que ce test ne
  pouvait structurellement pas révéler puisqu'il lit toujours le texte source fidèlement via
  `fs.readFileSync()`, comme n'importe quelle exécution directe (Node, `php -l`). Remplacé par un
  échappement ASCII strictement équivalent, vérifié qu'aucun caractère non-ASCII ne subsiste dans le
  code exécutable du fichier. **Nouveau scénario 8** : vérifie qu'une instrumentation de diagnostic
  temporaire ajoutée au script (préfixe console `[gwseq-race]`) émet bien un avertissement explicite
  quand `window.gwseqRaceReferentiel` est absent, au lieu d'un échec silencieux impossible à
  diagnostiquer — pensée pour permettre de confirmer, depuis un vrai navigateur, l'étape exacte où
  l'exécution diverge si un problème similaire devait se reproduire.
  **Correctif runtime 0.14.3 — filet de sécurité obligatoire, régression Unicode réintroduite lors de
  l'instrumentation** : la recette du correctif 0.14.2 a montré que le composant restait totalement
  non fonctionnel sur un vrai wp-admin malgré des logs prouvant un chargement/analyse/initialisation
  intégralement réussis (154 entrées chargées, 15 champs initialisés sans exception) — écartant toute
  cause déjà envisagée et orientant vers l'exécution réelle des interactions (frappe, clic), jamais
  exercée par ce test synthétique. La réécriture de l'instrumentation pour cette recette avait
  elle-même réintroduit EXACTEMENT le défaut Unicode déjà corrigé en 0.14.2 (détecté avant livraison
  par une vérification octet-par-octet, `od -c`, jamais atteint par la version 0.14.2 livrée) —
  reconfirme qu'une réécriture complète d'un fichier JS exige une revérification systématique de ce
  risque. Instrumentation étendue aux dix points de diagnostic demandés en recette (valeur brute,
  valeur normalisée, nombre de résultats, premiers résultats, code caché avant/après, création et
  contenu du conteneur, rapport de visibilité réel via `getComputedStyle`), et `try`/`catch` DÉDIÉ
  désormais posé sur CHAQUE gestionnaire d'événement (et non plus seulement autour de
  l'initialisation) — une exception pendant une interaction réelle est désormais tracée
  (`[gwseq-race] ... exception in ... handler:`) plutôt que silencieusement avalée. **Nouveaux
  scénarios 9 à 12** : le `<select>` de secours obligatoire (`gwseq_render_race_referentiel_field()`,
  `includes/race-referentiel.php`) porte le VRAI nom de champ par défaut et reste actif/visible tant
  que l'initialisation JS n'a pas explicitement réussi (scénario 11, avant toute exécution du script) ;
  `activateField()` ne le désactive/masque, en transférant le nom réel vers le composant de recherche,
  qu'à la toute fin d'une initialisation sans exception (scénario 9) ; si l'initialisation échoue, le
  `<select>` reste le SEUL contrôle actif, visible et nommé, jamais désactivé par anticipation
  (scénario 10) ; l'instrumentation détaillée produit bien, pour une saisie "old" sur un champ
  précédemment rempli, l'ensemble des dix traces attendues (scénario 12).

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
  **Correctif référentiel (profondeur 4 -> 3 générations, ascendant + année de naissance)** :
  `GWSEQ_PEDIGREE_MAX_DEPTH` passé à 3 (désormais 14 ascendants sur 3 générations, aligné sur la
  fiche de synthèse IFCE) — sanitation, resolver et rendu admin ("Génération N sur 3", arrêt visuel
  strict à la génération 3) tous vérifiés sur la nouvelle profondeur ; **compatibilité non
  destructive avec une éventuelle donnée de génération 4 déjà enregistrée avant ce correctif** : le
  paramètre `$previous_node` de `gwseq_sanitize_external_ancestor_tree()` et la relecture préalable
  dans `gwseq_set_horse_parent()` préservent intacte une sous-branche de génération 4 tant que le
  nom de l'ascendant de génération 3 n'a pas changé (le formulaire actuel ne peut plus la soumettre
  ni la modifier, mais ne la supprime jamais silencieusement), le resolver/rendu standard s'arrêtant
  bien à la génération 3 sans jamais l'interroger ni l'afficher — et, à l'inverse, un changement de
  nom de l'ascendant de génération 3 abandonne légitimement l'ancienne sous-branche (elle
  appartenait à un ascendant différent). Un champ `annee_naissance` a été ajouté au modèle
  d'ascendant externe (`{name, race, race_autre, annee_naissance, father, mother}`) — optionnel,
  jamais utilisé pour calculer un âge. La race d'un ascendant externe utilise désormais le
  référentiel Race/Stud-book/Appellation mutualisé (voir plus haut) via un composant de
  recherche/autocomplétion partagé avec l'identité du cheval, plutôt qu'un `<select>` séparé —
  vérifié directement sur les 154 entrées du référentiel, pas seulement quelques exemples.
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
  bien le champ CD formaté avec un pas de saisie (`step`) cohérent. Extension import IFCE (Étape 7,
  0.13.0) : ISO/ICC/IDR stockent désormais également un CD (`_gwseq_{cle}_cd`), même mécanisme de
  sanitation/persistance/lecture/rendu que le CD déjà existant des indices génétiques, exemple exact
  de la demande couvert (« ISO 115 (0.70) (2023) » → valeur 115, CD 0.70, année 2023) ; CD facultatif
  (une saisie manuelle sans CD reste valide) ; toutes les assertions d'égalité stricte de tableau
  préexistantes ont été mises à jour pour inclure la nouvelle clé `cd`.
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
  (`gws-equestrian-cheval-admin-tabs-test.php`, 0.12.0 à 0.12.6) : configuration PHP du
  regroupement onglet → meta boxes (les 6 onglets attendus, avec exactement les bonnes boîtes
  chacun — en particulier l'onglet Pedigree qui regroupe Pedigree, Production calculée et l'aperçu
  développeur). L'onglet Médias ne référence QUE la boîte "gwseq-cheval-media" — "postimagediv"
  (Photo principale) n'apparaît dans AUCUNE configuration d'onglet depuis 0.12.5 : il n'est plus
  piloté par le mécanisme générique de visibilité, mais RÉELLEMENT déplacé dans le DOM par le
  script jusqu'à un emplacement dédié à l'intérieur de "gwseq-cheval-media" — vérifié absent de
  toute configuration d'onglet, jamais dupliqué. Global ID dev-only/boîte "Ordre" volontairement
  rattachés à aucun onglet, chargement conditionnel des assets, configuration transmise au script
  via `wp_localize_script()` identique à la source de vérité PHP (y compris
  `isDevEnvironment`/`fallbackNotice`, 0.12.3), contexte `'side'` des meta boxes Production/aperçu
  développeur (restauré par le correctif 0.12.1) vérifié par lecture directe de
  `cheval-pedigree.php`, et enregistrement effectif du
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
  **Complété par `gws-equestrian-cheval-admin-tabs-runtime-test.js`** (53 assertions, via `node` —
  voir la section « Exécuter » ci-dessus) : ce fichier va au-delà de la lecture déclarative en
  EXÉCUTANT réellement `cheval-tabs-admin.js` contre une reproduction fidèle du DOM et du markup
  RÉEL d'une meta box WordPress (`postbox-header`/`handlediv`/`inside`, avec de vrais champs à
  l'intérieur), et MODÉLISE l'effet réel des classes `.closed`/`.hide-if-js` sur `offsetParent` —
  c'est ce type de test qui a permis de détecter, puis de corriger, plusieurs régressions bloquantes
  successives (0.12.1 : `DOMException` sur `insertBefore`, jamais de barre d'onglets ; 0.12.3 : une
  boîte masquée par `.hide-if-js` restait invisible malgré un correctif partiel `.closed`-only,
  invisible aux assertions basées sur du texte). Trois scénarios distincts, chacun vérifié
  indépendamment détecté par régression (désactivation temporaire du correctif testé, confirmation
  de l'échec, restauration) : (1) cas nominal — boîte Identité à la fois repliée ET masquée par
  Screen Options, vérifiant une visibilité RÉELLE (`offsetParent`, pas seulement un `style.display`
  déclaré) et la présence continue de champs historiques représentatifs dans le DOM ; **déplacement
  réel de la Photo principale (0.12.5)** : `#postimagediv` réellement réinséré dans l'emplacement
  dédié à l'intérieur de la boîte Médias (identité d'objet du nœud DOM vérifiée, jamais un clone),
  absence de toute trace dans sa colonne latérale native, et héritage automatique de la visibilité
  de sa nouvelle boîte hôte au fil des changements d'onglet ; (2) une boîte durablement masquée par
  un ancêtre hors de portée de tout correctif connu, vérifiant le déclenchement du filet de sécurité
  n°2 (barre retirée, toutes les boîtes restaurées, message de secours en environnement dev, **et la
  Photo principale restaurée à sa position native exacte, 0.12.5**) ; (3) une incohérence entre la
  configuration transmise et le marquage réel du DOM, vérifiant qu'aucun onglet n'est alors
  construit et qu'aucune boîte n'est masquée (filet de sécurité n°1) ; **(4) contenu réel de la
  Photo principale après déplacement (0.12.6)**, ajouté après un signalement en recette où seul le
  titre apparaissait dans l'onglet Médias, sans aucun contrôle ni aucune image en dessous — reproduit
  le markup EXACT que WordPress produit pour `#postimagediv` (`post_thumbnail_meta_box()` :
  nonce, lien natif « Définir la photo principale », ou vignette + lien « Supprimer » avec une
  photo déjà définie) dans les DEUX états, et vérifie champ par champ que ce contenu survit intact
  au déplacement DOM et reste RÉELLEMENT visible (`offsetParent` sur `.inside` elle-même, pas
  seulement le conteneur) une fois l'onglet Médias actif — ce test a permis d'écarter avec
  certitude le code de déplacement (`appendChild()`, qui ne peut par construction jamais effacer
  le contenu d'un nœud déplacé) comme cause du signalement, orientant le diagnostic vers une
  cause CSS plutôt que DOM (voir le CHANGELOG du module pour le détail).
- **Import IFCE de `gws-equestrian`, Étape 7 (`gws-equestrian-ifce-import-test.php`)** — DEPUIS LA
  RECETTE RUNTIME (0.13.1) : la fixture de référence pour la reconnaissance/l'analyse est le VRAI
  PDF de la fiche de synthèse IFCE de Jamerose de Félines (`tests/fixtures/ifce-jamerose-de-felines.pdf`,
  tel que téléchargé depuis Info Chevaux), exécutant exactement le même pipeline que le runtime
  WordPress : `gwseq_ifce_extract_pdf_text()` -> `gwseq_ifce_parse_text()`. Extraction de texte PDF
  (`ifce-pdf-text.php`) : mécanique de base (flux `/FlateDecode`, chaînes littérales échappées,
  robustesse — entrée non-PDF, PDF vide, fichier illisible, flux corrompu) contre un PDF minimal
  auto-généré ; PUIS pipeline structuré complet contre le vrai PDF — résolution des objets
  compressés (`/Type/ObjStm`), décodage de la police composite Identity-H via sa table `/ToUnicode`
  (CMap `beginbfchar`/`beginbfrange`), reconstruction de ligne par coordonnée Y. Reconnaissance et
  analyse (`ifce-import-parser.php`) contre le texte réellement extrait de ce vrai PDF : détection
  du document ; identité (nom, race "Selle Francais" mappée au référentiel existant, sexe, robe,
  taille "1m68" → 168 cm, année de naissance « né(e) en 2019 », naisseur/éleveur identifié, SIRE/UELN
  absents de cette fiche réelle -> restent vides, jamais devinés) ; indices sportifs ISO/ICC/IDR avec
  valeur+CD+année stockés séparément (exemple exact « ISO 115 (0.70) (2023) » retrouvé dans le vrai
  document, ICC/IDR absents -> vides), indices génétiques BSO/BCC/BDR (exemple exact « BSO +12
  (0.59) » retrouvé, BCC/BDR absents -> vides) — SEULE la première occurrence d'un indice dans le
  texte est retenue, jamais une occurrence plus tardive appartenant à un ascendant (page de
  production détaillée) ; reconstruction EXACTE des 14 ascendants du pedigree Jamerose (arbre
  vérifié nœud par nœud), avec les cas particuliers constatés sur le vrai document couverts
  spécifiquement (`gwseq_ifce_parse_pedigree_entry_line()`) : mention "Alias ..." retirée, chiffre
  romain final d'un nom JAMAIS confondu avec un code de stud-book, stud-book sans année associée
  toujours reconnu, code pays entre parenthèses écarté du nom comme du stud-book ; aucune sous-branche
  inventée à la dernière génération détectée ; arbre produit accepté sans perte par
  `gwseq_sanitize_external_ancestor_tree()` (même sanitiseur que la saisie manuelle) ; documents non
  reconnus rejetés (texte sans rapport, marqueur IFCE sans ligne d'identité, ligne d'identité sans
  marqueur IFCE, texte vide) — jamais un import "best effort". Mapping (`ifce-import-mapper.php`) :
  import complet vers les MÊMES fonctions métier que la saisie manuelle
  (`gwseq_set_cheval_identity`/`gwseq_set_cheval_sport_indice`/`gwseq_set_cheval_genetic_indice`/
  `gwseq_set_horse_parent`, jamais un accès direct à `update_post_meta()` — vérifié
  déclarativement, hors commentaires) ; import partiel (une section cochée n'affecte jamais les
  autres) ; structure invalide ou `post_id` invalide refusés proprement (`false`, aucune écriture) ;
  ascendants toujours importés en mode `external` (jamais `gws`), aucun appel à `wp_insert_post()`
  dans le fichier de mapping (aucune fiche fantôme créée pour un ascendant). Glue d'administration
  (`ifce-import-admin.php`) : contrôles de forme du fichier téléversé testés directement (code
  d'erreur, taille, extension `.pdf`) ; vérification déclarative (hors commentaires) de la présence
  du sniffing MIME réel (`finfo_open`)/`is_uploaded_file()`, de la suppression du fichier temporaire
  après extraction, capacité `edit_posts` (jamais `manage_options`), absence de toute création de
  pièce jointe/media pour le PDF, absence de toute modification du formulaire d'édition manuel
  existant (`add_meta_box`) ; **aucune écriture avant validation (§1)** vérifiée déclarativement —
  le gestionnaire de téléversement n'appelle jamais `wp_insert_post()`/`gwseq_ifce_map_import()`,
  seul le gestionnaire de confirmation (déclenché uniquement après un clic explicite sur l'écran de
  prévisualisation) les appelle ; enregistrement du sous-menu vérifié. **Écran de choix "Ajouter un
  cheval" (0.13.1, §B)** : page de choix enregistrée en page orpheline (jamais un second point
  d'entrée visible dans le menu) ; la redirection depuis l'écran natif ne se déclenche jamais en
  dehors de `post-new.php`/CPT Cheval, et est bien neutralisée par `gwseq_manual=1` ; le cas nominal
  de redirection (non exécutable ici, `exit()` après `wp_safe_redirect()`) vérifié déclarativement ;
  rendu de l'écran de choix (les deux chemins présentés avec une mise en avant équivalente).
  **Correctif « headers already sent » (0.13.2)** : le traitement des deux formulaires a été
  extrait en fonctions PURES (`gwseq_process_ifce_import_upload()`/`_confirm()`, jamais de HTML ni
  de `wp_safe_redirect()`/`exit` elles-mêmes) — exécutées RÉELLEMENT dans ce test, avec le vrai PDF
  de Jamerose de Félines pour l'upload, vérifiant littéralement : aucune sortie avant la redirection
  (capture de tampon), création réelle du transient de prévisualisation avec la structure
  normalisée effectivement analysée, URL de redirection calculée (vers la prévisualisation en cas de
  succès, vers l'écran d'upload nu sinon), suppression du fichier temporaire, et — pour la
  confirmation — aucune fiche Cheval créée pour un jeton expiré/inexistant, création réelle
  seulement pour un jeton valide (compteur de fiches avant/après), suppression du transient après
  usage. Nonce invalide et capacité insuffisante vérifiés par exécution réelle (ces deux cas lèvent
  une exception AVANT tout `wp_safe_redirect()`/`exit`, donc sûrs à exécuter directement).
  Vérifications déclaratives complémentaires : les deux gestionnaires sont bien accrochés aux hooks
  natifs `admin_post_gwseq_ifce_import_upload`/`_confirm` (exécutés par `wp-admin/admin-post.php`,
  qui ne rend jamais de HTML avant de les déclencher) ; le callback de PAGE
  (`gwseq_render_ifce_import_page()`) ne contient plus jamais ni `$_POST` ni `wp_safe_redirect` —
  la cause exacte du bug — ni d'appel direct aux gestionnaires `admin_post_*` ; les deux formulaires
  soumettent bien vers `admin-post.php` avec le champ caché `action` attendu par WordPress pour
  router vers le bon hook.
  **Correctif référentiel** : le mapping race d'un ascendant IFCE utilise désormais le référentiel
  Race/Stud-book/Appellation mutualisé (154 entrées, alias historiques inclus) plutôt que l'ancienne
  liste d'environ 19 races codées en dur — vérifié sur le VRAI pedigree Jamerose de Félines : l'alias
  historique "SFA" (Hors La Loi II) est reconnu et résolu au code canonique "SF", **jamais** rangé
  dans "Autre" (contrairement au comportement avant ce correctif), et l'alias "OES" (Chablis) résout
  de même vers "OE" (Origine Étrangère) ; KWPN reconnu et normalisé en majuscules. **Année de
  naissance d'un ascendant** : désormais extraite quand le document la porte (même token `\d{4}`
  déjà utilisé pour délimiter la fin du nom) et importée dans le nouveau champ `annee_naissance` du
  modèle d'ascendant externe — vérifiée exacte sur chacun des 6 ascendants du pedigree Jamerose qui
  en portent une, et laissée vide (jamais devinée) pour ceux qui n'en ont pas.
  **Correctif runtime post-livraison — nom officiel, alias et code pays** : la recette sur d'autres
  vraies fiches IFCE a révélé qu'un cheval (ou un ascendant) portant un alias IFCE
  ("NOM_OFFICIEL Alias NOM_D'USAGE") voyait auparavant l'alias intégralement supprimé, ne conservant
  que le nom officiel — inversé par ce correctif : c'est désormais le nom d'usage/alias qui devient
  le nom retenu (`name`/`nom`, utilisé comme nom de la fiche GWS), jamais le mot littéral "Alias",
  le nom officiel restant disponible séparément (`official_name` côté parseur pour un ascendant ;
  `nom_officiel` de l'identité, persisté en donnée technique `_gwseq_ifce_nom_officiel` via la
  nouvelle fonction métier `gwseq_set_cheval_ifce_nom_officiel()`, jamais exposée dans le formulaire
  manuel) — jamais perdu. Vérifié sur les quatre exemples réels exacts de la demande (Untouchable ->
  "UNTOUCHABLE 27", Bush vd Heffinck -> "ASB CONQUISTADOR", Windows vh Costersveld -> "CORNET
  OBOLENSKY", What A Quickstar R -> "BIG STAR"), sur un ascendant du pedigree Jamerose lui-même
  (UNTOUCHABLE, alias de "UNTOUCHABLE 27", avec son stud-book KWPN et son année 2001 correctement
  rattachés à l'alias), sur un cheval sans aucun alias (nom et nom officiel identiques), et sur la
  ligne combinée aussi bien que sur deux lignes consécutives ("NOM" puis "Alias ALIAS"). **Codes
  pays IFCE** (`gwseq_ifce_country_codes()`, liste FERMÉE norme ISO 3166-1 alpha-3, JAMAIS "toute
  séquence de 2-3 lettres majuscules entre parenthèses") : un marqueur reconnu ("(NLD)", "(BEL)",
  "(DEU)"...) est retiré du nom, un contenu parenthésé qui n'en est PAS un reste intact (vérifié
  explicitement) — jamais une suppression aveugle de toute parenthèse. Chiffres romains en fin de
  nom officiel ("CORRADO I") et suffixes courts d'alias ("CARTHAGO Z") toujours conservés, jamais
  confondus avec un stud-book (aucune régression du piège déjà écarté).
  **Correctif runtime complémentaire (0.14.2) — robustesse de l'extraction sur cinq nouvelles
  fiches IFCE réelles** (`tests/fixtures/ifce-quaprice-bois-margot.pdf`, `ifce-iowa-jal.pdf`,
  `ifce-untouchable-27.pdf`, `ifce-asb-conquistador.pdf`, `ifce-cornet-obolensky.pdf`, chacune
  exécutée à travers le pipeline complet réel `gwseq_ifce_extract_pdf_text()` ->
  `gwseq_ifce_parse_text()`) : la ligne d'identité IFCE n'a PAS un nombre de segments fixe — Robe ET
  Taille sont chacune facultatives indépendamment, une position figée perdait l'année de naissance
  sur deux fiches réelles (Untouchable 27, Asb Conquistador — taille absente, l'année se retrouvait
  décalée sur le segment "étalon") et rejetait intégralement une troisième (Quaprice Bois Margot — ni
  robe ni taille, seulement 3 segments) ; la détection repère désormais la position RÉELLE du jeton
  Sexe et reconnaît la mention "né(e) en AAAA" elle-même plutôt qu'une position, non-régression
  vérifiée sur le format standard à 5/6 segments (Iowa Jal, Cornet Obolensky, taille correctement
  extraite quand présente). **Normalisation croisée obligatoire** (cas Untouchable 27 : "Kon. Warm
  Paard Nederland" dans l'identité vs "KWPN" dans le pedigree du même document) — un même
  stud-book/race résout désormais vers un code canonique unique quel que soit le libellé IFCE
  rencontré (alias ajoutés pour KWPN, BWP, HAN, SF, OE ; HOLST et OLD résolvaient déjà correctement),
  vérifié par des tests croisés dédiés dans `gws-equestrian-race-referentiel-test.php` (identité et
  pedigree produisant explicitement la même valeur pour KWPN/BWP/HOLST/OLD/HAN/SF/SFA/OE/OES).
  **Ajustement UX de la prévisualisation (0.14.3)** : l'identité détectée était affichée sur une
  seule ligne concaténée par des virgules (ex. "KWPN, Mâle, Gris, non détectée, 2001"), rendant
  ambigu à quoi un « non détectée » isolé se rapportait — remplacée par des lignes explicitement
  étiquetées (Race / Stud-book, Sexe, Robe, Taille, Année de naissance), vérifiées présentes
  séparément et l'ancien résumé concaténé vérifié absent ; purement l'affichage, `$identity`
  (donnée réellement extraite du vrai PDF) n'est ni modifiée ni recalculée.

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
  post-recette, 0.12.0 à 0.12.6) : depuis 0.12.1, `gws-equestrian-cheval-admin-tabs-runtime-test.js`
  exécute réellement le script contre un DOM simulé fidèle et vérifie la bascule de panneau au
  clic/clavier, la mémorisation dans `sessionStorage`, l'absence d'exception, une visibilité
  RÉELLEMENT vérifiée (`offsetParent`) d'une boîte repliée et/ou masquée par Screen Options, les
  deux filets de sécurité (mapping incohérent, boîte durablement invisible), et (depuis 0.12.5) le
  déplacement RÉEL de `#postimagediv` vers l'intérieur de la boîte Médias — mais UN VRAI NAVIGATEUR
  reste seul juge de : l'apparence visuelle effective des onglets natifs `.nav-tab` (couleurs, état
  actif) telle que rendue par le vrai CSS d'administration WordPress, la visibilité concrète du
  focus clavier à l'écran, la persistance de l'onglet actif via `sessionStorage` d'un VRAI
  rechargement de page à l'autre, le ressenti du bouton d'enregistrement rapide (libellé affiché,
  clic déclenchant bien la sauvegarde native), le rendu visuel réel de la Photo principale une fois
  nichée dans la boîte Médias (ouverture de la médiathèque native `wp.media()`, définition/
  remplacement/retrait de l'image, absence d'erreur JS liée à ce déplacement DOM, apparence de la
  boîte imbriquée sans son propre encadrement — voir la règle CSS dédiée), la disposition et
  l'utilisabilité sur un écran étroit (≤ 782 px), et l'absence de toute erreur JavaScript en
  conditions réelles (autres scripts admin, extensions, thème).
  L'utilisabilité complète de la
  fiche sans JavaScript (blocs simplement empilés, formulaire toujours soumissible) reste elle
  aussi à confirmer en conditions réelles (navigateur avec JS désactivé).
  **Verrouillage de la Photo principale (0.13.1)** : la pose/le retrait réels de la classe
  `gwseq-cheval-media__locked` sont exécutés et vérifiés (runtime JS), et la présence des règles CSS
  masquant les contrôles natifs concernés est vérifiée déclarativement — mais l'EFFET VISUEL réel de
  ces règles (les boutons Monter/Descendre/Replier effectivement invisibles et inatteignables au
  clavier une fois la feuille de style chargée dans un vrai navigateur, l'absence de toute
  possibilité de glisser-déposer la boîte hors de Médias) reste à confirmer en conditions réelles, ce
  DOM factice n'appliquant aucune règle CSS.
- **Import IFCE — validé contre le vrai PDF depuis la recette runtime (0.13.1), limites résiduelles
  assumées (Étape 7)** : la reconnaissance/l'analyse est désormais testée contre le VRAI PDF de
  Jamerose de Félines (`tests/fixtures/ifce-jamerose-de-felines.pdf`), plus seulement une fixture
  texte artificielle — voir `ifce-pdf-text.php` pour le diagnostic complet de la cause exacte de
  l'échec initial (objets compressés `/Type/ObjStm`, police composite Identity-H) et le correctif
  retenu. Limites résiduelles NON couvertes par ce test, assumées et documentées : (1) un seul
  niveau de flux d'objets compressés est résolu (pas de flux imbriqués) ; (2) un `/Resources` hérité
  d'un ancêtre `/Pages` plutôt que porté directement par la page n'est pas résolu (le document réel
  testé porte directement les siens, cas le plus courant chez les générateurs de rapports) ; (3) la
  convention de lecture du pedigree (bloc contigu après le titre de section, ligne composée d'une
  année isolée traitée comme continuation) n'a été confrontée qu'à la mise en page de CE document
  précis — une disposition IFCE différente (colonnes, labels intercalés) n'a pas pu être testée ;
  (4) le parcours complet navigateur (téléversement réel → prévisualisation → confirmation → fiche
  créée, et l'écran de choix "Ajouter un cheval") n'a jamais été exercé dans un vrai WordPress. C'est
  précisément pour cette raison que la prévisualisation obligatoire avant écriture (§9 de la demande
  initiale) reste la garantie réelle contre une donnée mal interprétée — jamais ce test automatisé.
- **Composant d'autocomplétion Race/Stud-book — cause exacte identifiée et corrigée (0.14.4), mais
  NON reproduite en test automatisé** : la recette du filet de sécurité 0.14.3 a fourni la preuve
  déterminante — `search=true codeInput=true` mais `resultsList=false` au moment de
  l'initialisation. Cause exacte, confirmée par analyse de la spécification HTML5/WHATWG (voir
  `gws_test_assert_no_flow_content_inside_p()` dans `gws-equestrian-cheval-logic-test.php` et
  `gws-equestrian-pedigree-logic-test.php`, et le CHANGELOG du module, entrée 0.14.4) : le composant
  imprime un `<ul>` de résultats, or il était enveloppé dans un `<p>` par ses deux appelants — un
  `<p>` ne peut structurellement contenir aucun contenu "flow" (`<ul>` inclus), et un navigateur réel
  ferme implicitement le `<p>` dès qu'il rencontre le `<ul>`, l'expulsant hors de
  `.gwseq-race-field`. PROUVÉ AUTOMATIQUEMENT : le nouveau test structurel reproduit à la main la
  règle exacte de fermeture implicite du `<p>` sur le HTML source réellement produit par PHP,
  vérifié positif contre l'ancien balisage (`<p>`) et négatif contre le nouveau (`<div>`) — c'est un
  test STRUCTUREL sur le HTML source, jamais une exécution de navigateur. DÉDUIT DU CODE : le
  correctif (envelopper les deux appels dans un `<div>` plutôt qu'un `<p>`) supprime la cause
  structurelle avec certitude, puisque `<div>` autorise tout contenu "flow" sans restriction — aucun
  parseur PHP disponible ici (`DOMDocument`/libxml2, vérifié empiriquement) ni le DOM simulé fait
  main du test JS (construit par `appendChild()`, jamais par un vrai parseur HTML) ne peuvent
  reproduire fidèlement la règle WHATWG elle-même, d'où ce contournement par scanner structurel
  dédié plutôt qu'un test d'exécution réelle. RESTE À CONFIRMER DANS UN VRAI NAVIGATEUR : que le
  champ de recherche s'affiche réellement et réagisse à la frappe de bout en bout (suggestion,
  sélection, code caché synchronisé, sauvegarde, rechargement) — sur la fiche identité ET sur un
  ascendant du pedigree ; le filet de sécurité (`<select>` de secours, voir plus haut) reste la
  garantie immédiate tant que cette confirmation n'a pas eu lieu.
- **Correctifs post-recette 0.14.5** : (A) reconstruction du pedigree IFCE — deux VRAIS documents
  distincts (Asb Conquistador, Cornet Obolensky) présentaient le même défaut (un ascendant dont le
  nom/l'alias/le stud-book/l'année débordaient sur deux lignes produisait un ascendant FANTÔME,
  décalant tous les ascendants suivants) ; couvert par des assertions STRUCTURELLES sur l'arbre réel
  (nom, alias, race, année, position généalogique, père, mère) contre les deux vrais PDF dans
  `gws-equestrian-ifce-import-test.php`, vérifiées positives contre le nouveau code et NÉGATIVES
  contre l'ancien (rejeu direct) — un simple décompte du nombre d'ascendants ne suffisait pas à
  détecter ce bug, leçon appliquée ici. (B) bug "Préciser" persistant — couvert dans
  `gws-equestrian-cheval-logic-test.php` et `gws-equestrian-pedigree-logic-test.php` à la fois côté
  sanitation (`race_autre` jamais conservé hors du cas "autre") et côté rendu (auto-guérison d'une
  donnée déjà en base, visibilité du bloc "Préciser" du `<select>` de secours), chaque assertion
  vérifiée positive contre le correctif et négative contre l'ancien code. (C) rattachement Père/Mère
  GWS pendant l'import IFCE — couvert dans `gws-equestrian-ifce-import-test.php` : sanitation du
  choix soumis, absence de copie externe en parallèle d'une relation GWS, "Ne pas importer ce
  parent", conflit "même cheval père ET mère" (réutilise l'intégrité déjà validée pour la saisie
  manuelle, sans la dupliquer), sans effet quand "Importer le pedigree" est décoché, et rendu réel
  des trois choix sur la prévisualisation de Jamerose de Félines. Le stub `get_posts()` de ce fichier
  de test renvoie toujours un tableau vide (base en mémoire minimale) : la liste RÉELLE des candidats
  GWS proposés dans le `<select>` de la prévisualisation (chevaux existants, filtrés par sexe/année)
  n'a pu être vérifiée qu'indirectement, via la fonction pure `gwseq_ifce_preview_parent_candidate_rejection_reason()`
  appelée directement avec des identifiants explicites — reste à confirmer dans un vrai WordPress
  que le `<select>` liste effectivement les bons candidats, désactivés avec la bonne raison.
- **Correctif complémentaire 0.14.6 — cause racine réelle du bug "Préciser" (soumission sans
  interaction avec le champ Race)** : le correctif 0.14.5 (sanitation + visibilité) restait
  insuffisant — un champ chargé avec une race canonique déjà correcte réapparaissait avec "Préciser"
  rempli après N'IMPORTE QUEL submit du formulaire, y compris sans toucher au champ Race. Cause
  exacte : `hasPickedThisSession` (`assets/race-referentiel-autocomplete.js`) démarrait à `false`
  sans condition, y compris pour un champ déjà valide au chargement — le filet de sécurité de
  soumission (`commitPendingValue()`) traitait alors le libellé affiché comme une saisie jamais
  validée. **Scénario 13** (`gws-equestrian-race-referentiel-autocomplete-runtime-test.js`) reproduit
  littéralement ce cas (champ chargé avec "SF"/"Selle Français", AUCUN focus/frappe/clic, formulaire
  soumis directement) et vérifie que le code caché reste "SF", jamais réécrit en "autre" — vérifié
  positif contre le correctif et NÉGATIF contre l'ancien code (rejeu direct). **Scénario 14** vérifie
  le cas inverse (champ chargé avec "autre" + précision libre déjà enregistrée, jamais touché,
  précision préservée), pour prouver que le correctif du Scénario 13 ne régresse pas ce cas légitime.
  Nouveau test PHP dans `gws-equestrian-cheval-logic-test.php` combinant un `race_autre` parasité
  avec la modification simultanée d'un champ sans rapport (robe), pour prouver que l'invariant
  serveur de la 0.14.5 s'applique quel que soit le contenu du reste du formulaire.
- **Labels ANSF, nouveau lot (0.15.0, `gws-equestrian-cheval-labels-test.php`, 34 assertions)** :
  sanitation pure (`gwseq_sanitize_cheval_labels_input($raw, $sexe)`) pour les trois sexes et un
  sexe non renseigné — femelle (SFO + une seule valeur possible par famille de label poulinière,
  jamais deux niveaux simultanés même avec un payload trafiqué en tableau), mâle et hongre (SFO +
  SF Génétique Avenir, labels poulinières jamais retenus même explicitement soumis), payload
  volontairement invalide (valeur hors enum, `null`, tableau malformé) toujours résolu sans erreur.
  Rendu réel de la meta box conditionné par le sexe courant (les bons champs présents/absents selon
  le sexe, exactement quatre boutons radio par famille — jamais des checkboxes indépendantes — un
  seul précoché). Sauvegarde réelle via `gwseq_save_cheval_labels_meta()` (hook `save_post_gwseq_cheval`) :
  persistance initiale, rejeu du même payload sans interaction avec l'onglet (formulaire natif
  WordPress soumettant toujours l'état affiché de tous les champs), modification simultanée d'un
  champ sans rapport (robe) sans effet sur les Labels, changement de sexe dans les DEUX sens avec
  nettoyage des labels devenus incompatibles MALGRÉ leur présence dans le payload soumis (l'onglet
  Labels n'ayant pas été rouvert après le changement de sexe), SFO systématiquement préservé,
  sécurité de la sauvegarde (nonce invalide, permissions insuffisantes, révision). Détecté en
  cours de route : le stub `checked()`/`selected()` déjà utilisé par TOUS les fichiers de test de ce
  dossier ne fait que RETOURNER la chaîne sans jamais l'imprimer, contrairement au comportement réel
  de WordPress (`echo` par défaut) — invisible tant qu'aucune assertion ne vérifie la présence de
  l'attribut `checked`/`selected` dans un rendu, ce qu'aucun test antérieur ne semble avoir fait ;
  corrigé UNIQUEMENT dans ce nouveau fichier (les fichiers de test déjà validés n'ont pas été
  modifiés, hors périmètre de ce lot) — à garder à l'esprit pour un futur test qui voudrait vérifier
  un état coché/sélectionné ailleurs dans la suite.
- **Corrections de clôture du back-office Cheval V1 (0.16.0)** : suite à un audit fonctionnel en
  conditions réelles, deux correctifs ciblés, couverts par de nouvelles sections dans
  `gws-equestrian-pedigree-logic-test.php` et `gws-equestrian-cheval-logic-test.php`.
  - **Nettoyage des relations père/mère à la suppression définitive** (`cheval-pedigree.php`,
    `gwseq_cleanup_horse_parent_references_on_delete()`) : les 8 scénarios demandés — (1) A père de
    B, A mis à la corbeille (`post_status` manuel à `'trash'`), relation inchangée ; (2) A restauré,
    relation toujours inchangée ; (3) A supprimé DÉFINITIVEMENT (appel direct de la fonction, comme
    pour tout hook dans cette suite — `add_action()` est un stub muet ici), le père de B redevient
    « Non renseigné » (mode vidé ET `_id` supprimée) ; (4) même scénario côté mère ; (5) un parent
    utilisé par PLUSIEURS chevaux (père de deux, mère d'un troisième) — les trois relations
    nettoyées en un seul appel, via la réutilisation de `gwseq_get_horse_offspring()` déjà
    existante (jamais une requête dupliquée) ; (6) suppression d'un cheval qui n'est parent
    d'aucun autre — aucun effet de bord sur un cheval tiers avec une relation vers un ID
    différent (comparaison de snapshot meta) ; (7) une branche de pedigree externe (ascendant
    hors GWS) reste totalement intacte quand seule la branche GWS voisine est nettoyée ; (8) aucune
    erreur/notice PHP (le harness de test les aurait fait remonter). Câblage du hook vérifié
    déclarativement (présence de `add_action('before_delete_post', ...)`, absence de tout
    branchement sur `wp_trash_post`) et sécurité de type (post d'un autre post type passé à la
    fonction, aucune erreur). Vérifié par la méthodologie habituelle « retrait puis test » : la
    fonction de nettoyage temporairement neutralisée fait échouer les 13 nouvelles assertions,
    restaurée elles repassent toutes.
  - **Filtres et colonnes de la liste admin** (`cheval-fields.php`) : nouvelle infrastructure de
    stubs propre à `gws-equestrian-cheval-logic-test.php` (`get_terms()`, `is_admin()`,
    `$GLOBALS['pagenow']`, une classe `Gws_Test_Wpdb` réimplémentant directement en mémoire
    l'équivalent de la requête `SELECT DISTINCT ... ORDER BY ... DESC` réelle plutôt que de parser
    du SQL, et une classe `Gws_Test_Query` minimale avec `get()`/`set()`/`is_main_query()`) — ce
    fichier n'avait jusqu'ici jamais eu besoin de suivre `post_type`/`post_status` par ID, d'où
    l'ajout du registre `$GLOBALS['__gwseq_test_posts']` et de l'aide `gws_test_make_post()` déjà
    présents dans les fichiers de test voisins (`pedigree-logic-test.php`, `ifce-import-test.php`).
    Colonnes : les six nouvelles clés présentes dans l'ordre exact demandé, absence de la clé
    `date`, contenu réel de chaque colonne (Sexe et Année avec valeur renseignée et cas « — » non
    renseigné). Filtres : rendu réel des quatre `<select>`, persistance des valeurs déjà
    sélectionnées (`selected()` vérifié dans le HTML produit), liste des années sans doublon et
    triée décroissant, absence de rendu sur un autre post type ; application réelle à une requête
    factice (`tax_query` par slug pour la catégorie, `meta_query` en relation `AND` regroupant
    statut/sexe/année, recherche native `s` jamais altérée), rejet d'une valeur de statut/sexe hors
    référentiel (jamais propagée dans la requête), aucune application sur la liste d'un autre post
    type.
  - **Deux bugs de stub découverts et corrigés en cours de route dans
    `gws-equestrian-cheval-logic-test.php` — jamais dans le code de production, qui reproduisait
    fidèlement le comportement réel de WordPress** : (a) `sanitize_title()` n'existait pas encore
    dans ce fichier (jamais appelée avant ce lot) — ajoutée, fidèle à la vraie fonction pour les
    slugs déjà normalisés utilisés ici (minuscules, tirets) ; (b) `selected()`/`checked()`
    n'imprimaient pas par défaut (même défaut de stub déjà identifié et documenté pour le fichier
    Labels ANSF, jamais corrigé dans les autres fichiers faute de besoin jusqu'ici) — corrigées ici
    aussi pour `echo` par défaut comme le WordPress réel, seul moyen de vérifier la persistance des
    filtres dans le HTML effectivement produit.
  - **Un vrai bug de production détecté PAR le nouveau test, puis corrigé** : `(array)
    $query->get('tax_query')` (et son équivalent pour `meta_query`) provoquait une conversion
    incorrecte quand WordPress n'a pas encore de tax_query/meta_query dans la requête principale —
    `WP_Query::get()` renvoie alors la chaîne vide `''` par défaut (jamais un tableau), et `(array)
    ''` produit `array('')` (un tableau à un élément contenant une chaîne vide), pas un tableau
    vide. Le filtre Catégorie ajoutait alors sa clause à l'index 1 au lieu de 0, corrompant la
    structure attendue par `WP_Tax_Query`/`WP_Meta_Query`. Corrigé en vérifiant explicitement
    `is_array()` avant d'utiliser la valeur (repli sur un tableau vide sinon), plutôt qu'un simple
    cast. Confirmé par retrait du correctif : le test échoue alors avec une erreur PHP réelle
    (`TypeError: Cannot access offset of type string on string`), preuve que ce test couvre un
    risque runtime réel et pas seulement un cas théorique.
  - Intégralité des suites de tests PHP (16 fichiers) et des deux suites JS runtime de ce dossier
    ré-exécutée : aucune régression. Une seule anomalie preexistante et sans rapport avec ce lot
    relevée (`Warning: Array to string conversion` dans `gws-equestrian-cheval-labels-test.php`,
    ligne 35, déjà présente avant ce lot, hors périmètre — non modifiée, conformément à la consigne
    de ne toucher à aucun autre comportement).
- **Module Équipe, nouvel objet métier Membre (0.17.0, `gws-equestrian-membre-logic-test.php`, 140
  assertions)** : fichier de test entièrement nouveau (nouveau post type `gwseq_membre`,
  indépendant de Cheval), avec sa propre infrastructure de stubs minimale — plus légère que celle
  de `gws-equestrian-cheval-logic-test.php` (pas de `$wpdb`/`WP_Query` factices, ce module
  n'exécute aucune requête directe), mais réutilisant les mêmes conventions déjà établies
  (`checked()`/`selected()` échoient par défaut, registre `current_user_can`/`wp_verify_nonce`/
  `wp_is_post_revision` piloté par le test, capture des arguments de `register_post_meta()`).
  - **Identité/Profil/Contact** : sanitation pure de chaque section avec un payload vide (tous les
    champs sont facultatifs — aucune erreur, tout vide) et un payload complet ; Fonction/rôle,
    Localisation, Spécialités et Diplômes vérifiés comme du texte libre pur, sans aucune
    revalidation contre une liste fermée (contrairement à Sexe/Robe côté Cheval).
  - **Titre technique automatique** (§8 de la demande) : la fonction pure
    `gwseq_derive_membre_title()` testée pour les quatre cas explicitement demandés (prénom + nom,
    prénom seul, nom seul, les deux vides) ; le MÉCANISME réel (`gwseq_auto_title_membre()`,
    accroché au filtre `wp_insert_post_data`) testé séparément avec un `$_POST` à la forme réelle
    d'une soumission — recalcul effectif pour une soumission Membre valide, absence totale d'effet
    sur un autre post type (ex. Cheval, non-régression croisée), non-réécriture silencieuse du
    titre déjà enregistré quand le nonce Membre est absent ou invalide (ex. Quick Edit), et
    câblage réel du hook (`wp_insert_post_data`, jamais un second `wp_update_post()` dans un hook
    `save_post` qui aurait exigé de se dés-accrocher soi-même pour éviter une boucle).
  - **Langues** : sélection multiple, revalidation stricte contre le référentiel (une valeur hors
    enum comme `klingon` est ignorée, jamais propagée), déduplication, payload malformé (une chaîne
    au lieu d'un tableau) sans erreur, "Autre" + Préciser conservés ensemble, et surtout la RÈGLE
    CENTRALE demandée : quand "Autre" est retiré de la sélection, la précision est nettoyée MÊME
    si l'ancienne valeur "Préciser" est encore présente dans le payload soumis — vérifié à la fois
    dans la fonction de sanitation pure et dans un cycle complet de sauvegarde/réenregistrement en
    base (sur un post dédié, distinct de la fixture principale, pour ne pas perturber les tests de
    rendu/colonnes qui la réutilisent plus loin dans le fichier).
  - **Contact** : e-mail invalide jamais enregistré tel quel, téléphone international (espaces,
    `+`, parenthèses, tirets) et WhatsApp jamais dénaturés et vérifiés INDÉPENDANTS l'un de
    l'autre (peuvent légitimement différer), les cinq champs URL sanitisés.
  - **Sauvegarde/rechargement complet** : un membre minimal (aucun champ soumis, y compris sans
    nonce d'identité renseigné) s'enregistre sans aucune erreur ; un membre complet avec les trois
    sections intégralement remplies est rechargé à l'identique champ par champ.
  - **Sécurité de la sauvegarde** : nonce absent/invalide, permissions insuffisantes
    (`current_user_can`), révision, et DOING_AUTOSAVE (cette dernière testée en tout dernier dans
    le fichier — même contrainte que `gws-equestrian-cheval-logic-test.php` : `DOING_AUTOSAVE` est
    une vraie constante PHP, définissable une seule fois par processus ; le test couvre en un seul
    bloc final à la fois le titre auto-dérivé ET la sauvegarde des meta sous cette même contrainte).
  - **Colonnes de la liste admin** : ordre exact Photo | Nom | Fonction / rôle | Localisation |
    Langues | Ordre (`cb` natif en plus, toujours en premier), absence de la colonne native
    "Date", contenu réel de chaque colonne avec valeur renseignée et cas "—" pour une donnée
    manquante (y compris la miniature 40×40 de la Photo, jamais l'image originale). Rappel
    méthodologique retrouvé ici aussi : le contenu texte affiché par les colonnes passe par
    `esc_html()`, donc une apostrophe dans une donnée métier ("Responsable d'élevage") ressort
    HTML-échappée (`&#039;`) — l'assertion compare au résultat de `esc_html()`, pas à la chaîne
    brute, pour ne pas re-produire l'erreur de stub déjà documentée ailleurs dans ce dossier
    (`checked()`/`selected()` n'échoyant pas par défaut) sous une forme différente.
  - **Rendu réel des trois meta boxes** (Identité/Profil/Contact) : tous les champs de chaque
    section réellement présents dans le HTML produit, valeurs existantes bien préremplies, les 12
    cases à cocher Langues (11 langues + Autre) réellement rendues avec la persistance de la
    sélection déjà enregistrée, le bloc "Préciser" RESTE PRÉSENT DANS LE DOM même quand "Autre"
    n'est pas sélectionné (juste masqué par un style inline) — condition nécessaire pour que la
    fiche reste utilisable sans JavaScript, exactement la même exigence que pour les champs
    conditionnels de Cheval (`assets/cheval-admin.js`).
  - **Absence de couplage avec Cheval** : le système d'onglets de Cheval
    (`includes/cheval-admin-tabs.php`) n'est pas réutilisé — vérifié par l'absence de toute trace
    de `gwseqChevalTabs`/`cheval-tabs` dans `includes/membre-editor.php`, et par l'absence de tout
    hook (`add_action`/`add_filter`) accroché sur `GWSEQ_CPT_CHEVAL`/`GWSEQ_CPT_PRESTATION`/
    `GWSEQ_CPT_GROUPE` depuis `includes/membre-fields.php` (une simple recherche de texte brut
    aurait produit un faux positif : le docblock du fichier explique délibérément, en prose,
    pourquoi le système d'onglets de Cheval n'a pas été réutilisé, et mentionne donc légitimement
    la constante `GWSEQ_CPT_CHEVAL` en commentaire).
  - **Permissions Éditeur** (§10) : vérifié par inspection déclarative de l'enregistrement du post
    type (`includes/post-types.php`) — absence de toute clé `capability_type` pour
    `GWSEQ_CPT_MEMBRE`, donc héritage direct du type de capacité standard `'post'`, déjà accordé
    en écriture au rôle Éditeur nativement, sans qu'aucune capacité technique supplémentaire ne
    soit créée pour ce seul module.
  - Toutes les régressions attendues ont été confirmées par la méthodologie « retrait puis test »
    (titre auto-dérivé, nettoyage de "Autre", retrait de la colonne "Date" — chacune fait échouer
    les assertions correspondantes une fois neutralisée, puis les fait repasser une fois
    restaurée) ; un vrai bug de test (jamais de production) a été détecté et corrigé au passage :
    `gwseq_register_membre_meta()` est accrochée à `add_action('init', ...)`, mais le stub
    `add_action()` de ce fichier ne fait qu'ENREGISTRER les callbacks sans les exécuter (à la
    différence du stub utilisé par `gws-equestrian-foundations-test.php`, qui simule réellement le
    hook `init`) — la fonction est donc appelée directement après le chargement du fichier, même
    convention que `gwseq_register_cheval_meta()` dans `gws-equestrian-cheval-logic-test.php`.
  - `tests/gws-equestrian-foundations-test.php` mis à jour en conséquence (4ᵉ post type
    enregistré, `gwseq_membre`, ajouté à la liste des fichiers scannés pour le préfixe `gwseq_` et
    aux nouvelles assertions de labels/supports dédiées à Membre) — aucune assertion existante sur
    Prestation/Groupe/Cheval modifiée.
  - Intégralité des suites de tests PHP (18 fichiers) et des deux suites JS runtime de ce dossier
    ré-exécutée : aucune régression.
- **Bloc Actualités, adaptation de `post` (0.18.0, `gws-equestrian-actualites-logic-test.php`, 46
  assertions)** : fichier nouveau, stubs minimaux propres à ce fichier (`register_taxonomy_args`
  et `post_type_labels_post` capturés comme n'importe quel autre filtre, `remove_post_type_support`
  capturé plutôt que simulé). `post` n'est jamais réenregistré (`register_post_type()` toujours
  appelé exactement pour les quatre post types métier GWS, jamais `post` ni `gwseq_actualite`).
  Vocabulaire : `gwseq_actualites_post_labels()` exercée avec un faux objet `$labels` représentant
  fidèlement ce que `get_post_type_labels()` calculerait réellement pour `post` avant filtrage (pas
  seulement un tableau vide) — vérifie chaque libellé demandé ET qu'une propriété non listée
  (`parent_item_colon`) n'est jamais écrasée (le filtre ne réinitialise pas l'objet entier).
  Étiquettes : `gwseq_hide_post_tag_ui()` exercée avec des arguments par défaut réalistes,
  `show_ui`/`show_admin_column` bien remis à `false`, `show_in_rest`/`public` INCHANGÉS, et la
  taxonomie `category` ainsi que la Catégorie de cheval du module Chevaux traversent le filtre sans
  aucune altération (non-régression croisée). Commentaires/trackbacks : les deux appels à
  `remove_post_type_support('post', ...)` bien capturés, jamais appliqués à un autre post type.
  Modification rapide : un seul filtre `post_row_actions` enregistré au total (la fonction déjà
  existante d'`includes/admin-ui.php` est réutilisée, jamais un second filtre dupliqué), `post`
  bien ajouté à sa liste de post types ciblés (recherche par expression régulière dans le code
  source plutôt qu'un texte figé, pour tolérer l'ordre des éléments du tableau), Pages toujours
  épargnées (contrôle négatif). Permissions Éditeur : vérifié par absence de toute manipulation de
  capacité dans le fichier (`current_user_can`/`map_meta_cap`/`add_cap`), le rôle Éditeur conservant
  la totalité de ses droits natifs sur `post`. Non-régression : toujours exactement quatre post
  types métier GWS enregistrés, taxonomie Catégorie de cheval inchangée. Deux vérifications par
  retrait/restauration effectuées en cours de route (vocabulaire, masquage des Étiquettes,
  commentaires/trackbacks) pour prouver que ces assertions détectent réellement une régression.
  `tests/gws-equestrian-foundations-test.php` mis à jour en conséquence : Modification rapide
  étendue à `post` (l'ancienne assertion utilisait justement `post` comme groupe témoin « jamais
  touché » — bascule nécessaire du témoin sur les Pages, seul post type réellement hors périmètre
  maintenant), et nouvelles assertions déclaratives sur le retrait des commentaires/trackbacks.
- **Filtre de la liste Prestations par Groupe tarifaire (demande complémentaire,
  `gws-equestrian-prestations-logic-test.php`)** : nouveaux stubs (`get_posts()` reproduisant
  fidèlement le seul besoin réel — post_type + exclusion de la corbeille —, `is_admin()`,
  `$GLOBALS['pagenow']`, `Gws_Test_Query`) ; `get_the_title()` étendue pour accepter soit un ID
  (déjà utilisé partout ailleurs dans ce fichier) soit un objet post-like, comme la vraie fonction
  WordPress. Détecté et corrigé au passage : le stub `selected()`/`checked()` de ce fichier
  n'échoyait pas par défaut, même défaut déjà rencontré et documenté pour d'autres fichiers de
  cette suite — corrigé ici aussi, seul moyen de vérifier la persistance de la sélection dans le
  HTML réellement produit. Couverture : rendu réel du contrôle (tous les groupes réels proposés,
  un groupe à la corbeille jamais proposé, persistance de la sélection), absence d'affichage sur un
  autre post type, application réelle à la requête (meta_query sur `_gwseq_prestation_groupe_id`,
  recherche native jamais altérée), le cas "Sans groupe tarifaire" couvrant PROPREMENT les deux
  situations réelles (`meta_query` en relation `OR` : valeur explicitement `0` ET meta totalement
  absente pour une prestation créée avant l'existence de cette relation), valeur absente/vide
  laissant la requête inchangée, valeur trafiquée non numérique jamais propagée, non-application à
  un autre post type. Vérifié par retrait/restauration (le cas "Sans groupe tarifaire" en
  particulier). Intégralité des suites existantes (19 fichiers PHP + 2 suites JS runtime)
  ré-exécutée après ce lot : aucune régression.
- **Actualités : cadrage de l'éditeur par blocs (0.19.0, `gws-equestrian-actualites-logic-test.php`,
  20 assertions supplémentaires)** : allowlist Gutenberg exacte (`gwseq_actualites_allowed_blocks()`
  — Paragraphe, Titre, Liste + `core/list-item`, Image, Galerie, Bouton + `core/buttons`, Vidéo,
  `core/embed`), exclusion explicite des blocs de mise en page avancée/techniques (colonnes,
  groupe, couverture, HTML, code, classique, widgets hérités, éléments de thème/site, shortcode),
  scope strictement limité à `post` (une Page, un autre post type GWS, un contexte sans post ou
  dépourvu de `post_type` reçoivent la valeur d'entrée INCHANGÉE, jamais recalculée — vérifié
  littéralement plutôt que supposé). Non-régression : compte de post types toujours à quatre,
  intégralité de la suite existante ré-exécutée.
- **Mises en avant : Pop-in et Sticky bar (0.20.0), retiré en 0.21.0** : les quatre fichiers de
  test PHP (`gws-equestrian-campagnes-shared-test.php`, `-popin-logic-test.php`,
  `-sticky-bar-logic-test.php`, `-campagnes-front-test.php`) et le fichier d'exécution réelle Node
  (`-campagnes-front-runtime-test.js`) qui couvraient ce module ont été supprimés avec le code
  correspondant, retiré à la suite d'une décision produit (fonctionnalité périphérique, pas une
  régression — voir `CHANGELOG.md` du module, 0.21.0). `gws-equestrian-foundations-test.php` porte
  désormais trois assertions confirmant explicitement l'absence des deux post types et du menu
  "Mises en avant" (vérifiées par retrait/restauration). Intégralité de la suite restante
  (18 fichiers PHP + 2 suites JS runtime) ré-exécutée après ce retrait : aucune régression sur
  Cheval, Prestations, Équipe, Actualités, Groupes tarifaires.
- **Partager un cheval (0.22.0)** : deux nouveaux fichiers PHP et un nouveau fichier d'exécution
  réelle Node.
  - `gws-equestrian-cheval-share-logic-test.php` : Accroche commerciale (enregistrement/lecture,
    multiligne préservée, HTML retiré à la sanitation, n'altère jamais Présentation/Description,
    absente par défaut sans fallback) ; `gwseq_get_horse_shareable_data()` — identité (vocabulaire
    commercial "Jument"/"Étalon"/"Hongre", combinaisons partielles sexe/race/âge), origines (Père ×
    Père de la mère résolus via le VRAI pedigree resolver, noms en majuscules, cycle/référence
    cassée jamais exposés), taille + un seul indice sportif mis en avant (priorité ISO > ICC >
    IDR), règle prix/statut (les quatre statuts, prix jamais présélectionné), vidéos (aucune/une/
    plusieurs, avec/sans titre, présélection des deux premières sans jamais limiter la sélection),
    lien de fiche public/non public (brouillon ET protégé par mot de passe, chacun vérifié) ;
    `gwseq_build_horse_share_message()` (bloc identité compact, accroche en paragraphe séparé,
    lignes vidéo, lien de fiche en fin de message, message personnel en tête, sélection/URL
    invalide ignorée sans erreur) ; Open Graph (description = identité + origines + accroche
    jamais le prix, troncature propre, image = dérivée `medium_large` avec dimensions, rien émis
    hors page singulière/cheval non public/plugin SEO tiers détecté). Trois mécanismes critiques
    vérifiés par retrait/restauration (règle prix/statut, visibilité mot de passe).
  - `gws-equestrian-cheval-share-admin-test.php` : menu (capacité `edit_posts`, rattaché au sous-menu
    de Chevaux, jamais un menu top-level séparé), action de ligne "Partager" (ajoutée pour un
    cheval, jamais pour un autre post type, lien vers le même écran que la boîte latérale), boîte
    latérale (bouton, garde-fou auto-draft), les trois points d'entrée AJAX avec leur sécurité
    (nonce, capacité générale ET capacité spécifique à une fiche précise — un auteur sans
    `edit_others_posts` ne peut ni lister ni charger les chevaux d'un autre auteur, WP_Query
    minimal fidèle à la restriction réelle), sanitation de la sélection (clé porteuse de HTML
    neutralisée, index non numérique ignoré, message personnel sanitisé), et la preuve que la
    composition du message relit TOUJOURS l'état réel en base au moment de la requête plutôt qu'un
    contenu que le client aurait pu soumettre. Un mécanisme critique vérifié par
    retrait/restauration (capacité spécifique à la fiche sur l'AJAX de données complètes).
  - `gws-equestrian-cheval-share-runtime-test.js` (19 assertions, exécution RÉELLE via `node`) :
    écran de composition rendu avec les bonnes présélections (identité cochée, prix décoché,
    fiche cochée), aperçu initial et mis à jour en fonction des cases cochées/décochées
    (événements avec BULLING, le script écoutant au niveau du conteneur englobant), message
    personnel répercuté en tête de l'aperçu, et surtout : WhatsApp/SMS/Copier consomment tous les
    trois EXACTEMENT le même texte déjà affiché dans l'aperçu (vérifié par retrait/restauration —
    faire consommer à WhatsApp un texte différent de l'aperçu fait échouer l'assertion comme
    attendu), encodage URL correct des retours à la ligne/espaces/accents.

  Intégralité des suites existantes (21 fichiers PHP + 3 suites JS runtime) ré-exécutée après ce
  lot : aucune régression. Deux tests existants mis à jour pour refléter des changements légitimes
  du module (jamais pour masquer une régression) : le compte de champs éditoriaux
  (`gws-equestrian-cheval-editorial-logic-test.php`, 9 -> 10 avec l'Accroche commerciale) et la
  vérification de non-duplication du filtre `post_row_actions`
  (`gws-equestrian-actualites-logic-test.php`, désormais ciblée sur le callback précis de retrait
  de Quick Edit plutôt que sur le nombre total de filtres `post_row_actions` du module, qui
  augmente légitimement avec la nouvelle action de ligne "Partager").
- **Partager un cheval : correctifs et améliorations de recette (0.23.0)** : couverture étendue
  dans les trois fichiers existants du lot 0.22.0, sans nouveau fichier.
  - `gws-equestrian-cheval-share-logic-test.php` : "va-et-vient" (coché → présent, décoché →
    absent immédiatement) vérifié explicitement pour CHAQUE bloc sélectionnable (prix, identité,
    origines, taille/indice, accroche, chaque vidéo par index, fiche complète) — pas seulement le
    prix signalé en recette, conformément à la demande de vérifier l'absence du même problème
    ailleurs.
  - `gws-equestrian-cheval-share-admin-test.php` : nouveau test du helper de vignette de
    remplacement (`gwseq_render_media_placeholder()` — classe partagée, dashicon réutilisé,
    `aria-hidden`, combinable avec une classe de dimensionnement) ; couverture complète des
    filtres — sanitation (valeurs hors référentiel ignorées, bornes d'année réutilisées de
    `cheval-fields.php`, bornes inversées échangées jamais en erreur, catégorie inexistante jamais
    créée), transformation en arguments de requête (`meta_query`/`tax_query`), cumul réel de
    quatre filtres + recherche texte via l'AJAX (seul le cheval correspondant à TOUS les critères
    est retourné), non-fuite de permission avec des filtres actifs, aucune donnée Cheval modifiée
    par une recherche/un filtrage. `WP_Query` factice étendu avec un `meta_query`
    (`>=`/`<=`/`BETWEEN`) et un `tax_query` minimal fidèles au nécessaire réel.
  - `gws-equestrian-cheval-share-runtime-test.js` (23 assertions supplémentaires, 42 au total) :
    **scénario de la cause racine du bug prioritaire** — reproduit un ordre d'arrivée réseau
    réaliste (une requête d'aperçu lente déclenchée en premier répond APRÈS une requête plus
    rapide déclenchée ensuite) et vérifie que la réponse la plus ancienne, arrivée en dernier, est
    bien ignorée plutôt que d'écraser l'aperçu à jour — vérifié par retrait/restauration (retirer
    le jeton de requête fait échouer exactement cette assertion) ; vignette de remplacement pour
    un cheval sans photo (résultats de recherche ET en-tête de l'écran de composition), jamais de
    `<img>` avec un `src` vide ; filtres (options réellement proposées, transmission cumulée avec
    la recherche texte, réinitialisation complète). Le DOM factice de ce fichier a été étendu pour
    supporter les sélecteurs CSS composés (`tag.classe`, `.classe1.classe2`), nécessaires aux
    nouvelles assertions.
- **Partager un cheval : correctifs de recette avant test des canaux (0.24.0)** : deuxième recette
  runtime — deux correctifs strictement, sans nouveau fichier de test.
  - `gws-equestrian-cheval-share-logic-test.php` (91 assertions au total) : nouveau helper
    `gwseq_horse_share_decode_title()` couvert isolément (apostrophe droite/typographique déjà
    correctes -> inchangées, entité nommée `&rsquo;` et numérique `&#8217;` -> décodées vers le même
    caractère, esperluette `&amp;`, lettres accentuées déjà correctes non sur-décodées, chaîne HTML
    dangereuse encodée -> texte littéral inerte) puis son application réelle dans
    `gwseq_get_horse_shareable_data()` (`nom`/`nom_affiche`), dans un message texte composé
    (aucune entité résiduelle) et dans l'Open Graph (`esc_attr()` réapplique un échappement HTML sûr
    sur la valeur décodée — vérifié qu'aucune balise `<img>`/`<script>` brute n'apparaît jamais dans
    `<head>`), et dans `gwseq_horse_share_origines_label()` (nom d'un parent externe avec entité
    littérale). Section Open Graph réordonnée : le test "plugin SEO actif" (qui pose une constante
    PHP globale, donc définitive une fois posée) est désormais placé en tout dernier, pour ne plus
    neutraliser à tort les assertions Open Graph ajoutées après lui.
  - `gws-equestrian-cheval-share-admin-test.php` (60 assertions au total) : même correctif vérifié
    pour `gwseq_horse_share_lightweight_row()` (ligne légère des résultats de recherche) — un titre
    avec une entité littérale n'apparaît plus jamais tel quel dans les résultats AJAX, un titre
    dangereux reste un texte littéral inerte une fois décodé.
  - `gws-equestrian-cheval-share-runtime-test.js` (48 assertions au total) : nouvelles assertions
    vérifiant que les quatre filtres exposent désormais un `<label>` RÉEL et VISIBLE (jamais
    `screen-reader-text`), correctement associé à son contrôle via `for`/`id` ("Sexe", "Statut
    commercial", "Catégorie", "Année de naissance" pour le groupe De/à), et que le contenu de
    l'option "Tous"/"Toutes les catégories" reste, lui, inchangé et distinct du libellé du champ —
    vérifié par retrait/restauration (remettre `screen-reader-text` fait échouer exactement
    l'assertion de visibilité).

  Correctif du décodage vérifié par retrait/restauration : neutraliser temporairement
  `gwseq_horse_share_decode_title()` (retour de la valeur brute, non décodée) fait échouer
  exactement les 10 assertions dédiées à ce correctif, aucune autre — confirmant qu'aucun autre
  chemin ne compense silencieusement l'absence de décodage.

  Intégralité des suites existantes (21 fichiers PHP + 3 suites JS runtime) ré-exécutée après ce
  lot : aucune régression.

  Quatre mécanismes critiques supplémentaires vérifiés par retrait/restauration (jeton de requête
  côté JS, validation de catégorie inexistante, restriction de permission avec filtres actifs).
  Intégralité de la suite (21 fichiers PHP + 3 suites JS runtime) ré-exécutée : aucune régression.
- **Partager un cheval : correctifs du transport vers les canaux (0.25.0)** : premier test réel
  WhatsApp — sauts de ligne perdus et pictogramme vidéo 🎥 transformé en caractère invalide « � ».
  Trois correctifs strictement (WhatsApp/pictogramme/fiche complète) + audit `sms:`, sans nouveau
  fichier de test.
  - `gws-equestrian-cheval-share-logic-test.php` (96 assertions au total) : `gwseq_horse_share_
    video_label()` vérifié sans pictogramme (avec et sans titre saisi), absence de `🎥` dans le
    message final composé ; bascule explicite de "Ajouter la fiche complète" isolée côté moteur de
    composition (cochée -> intitulé + URL sur deux lignes ; décochée -> bloc entièrement absent,
    intitulé ET URL, jamais un intitulé orphelin).
  - `gws-equestrian-cheval-share-admin-test.php` (63 assertions au total) : régression du booléen
    `fiche` couverte explicitement — la chaîne littérale `"false"` (valeur réelle transmise par un
    booléen JavaScript décoché via `FormData`) est bien interprétée comme faux par `gwseq_sanitize_
    horse_share_selection()` (`filter_var(..., FILTER_VALIDATE_BOOLEAN)`, remplace l'ancien
    `!empty()` qui aurait considéré cette chaîne non vide comme vraie), ainsi que `"0"` ; la chaîne
    `"true"` reste bien interprétée comme vrai.
  - `gws-equestrian-cheval-share-runtime-test.js` (72 assertions au total) : nouveau cheval fixture
    dédié (`FIXTURE_SHAREABLE_ENCODING`) réunissant volontairement TOUS les caractères demandés par
    la recette (apostrophe, accents, `×`, `•`, lignes vides, URL YouTube) pour exercer le pipeline
    complet aperçu → clic → URL finale en un seul scénario. Vérifié : le bouton WhatsApp ouvre
    désormais `https://api.whatsapp.com/send?text=...` (jamais `wa.me`) ; décoder l'URL WhatsApp
    restitue EXACTEMENT le texte source de l'aperçu, caractère pour caractère, y compris les lignes
    vides (`%0A%0A`) ; absence totale de `🎥` et de `�` dans l'aperçu comme dans l'URL encodée ;
    l'adaptateur SMS utilise `sms:?body=` sur Android/générique et `sms:&body=` sur iOS (détection
    par `navigator.userAgent` simulé), avec un contenu source identique des deux côtés ; Copier
    reproduit le texte brut avec de VRAIS retours à la ligne, sans aucun caractère `%`-encodé
    (`%0A`/`%C3%97`/`%E2%80%A2`) dans le presse-papiers ; "Ajouter la fiche complète" vérifiée
    cochée/décochée/recochée avec répercussion immédiate sur l'aperçu ET sur les trois canaux
    externes (WhatsApp/SMS/Copier), qui consomment tous la même source.

  Cause exacte de la perte des sauts de ligne établie par un test de bout en bout reconstituant tout
  le pipeline (message → AJAX/JS → `encodeURIComponent()` → URL) : ni la composition ni l'encodage
  n'étaient en cause (`encodeURIComponent()` transforme déjà correctement `\n` en `%0A` et tout
  caractère UTF-8) — la divergence était isolée au point de sortie WhatsApp (`wa.me`).

  Les trois correctifs vérifiés par retrait/restauration (rétablir `wa.me`, réintroduire le
  pictogramme, revenir à `!empty()` pour "fiche") font chacun échouer exactement les assertions
  dédiées, aucune autre. Intégralité de la suite (21 fichiers PHP + 3 suites JS runtime) ré-exécutée
  après ce lot : aucune régression.
- **Suite V1 « Partager & vendre » — Lot 1 : visibilité public/privé, liens, Open Graph (0.26.0)** :
  sans nouveau fichier de test (la logique de token vit dans `includes/cheval-share.php`, déjà
  couvert par `gws-equestrian-cheval-share-logic-test.php` ; la glue WordPress dans `includes/
  cheval-share-admin.php`, déjà couvert par `gws-equestrian-cheval-share-admin-test.php`).
  - `gws-equestrian-cheval-share-logic-test.php` (130 assertions au total) : génération de token
    (64 caractères hexadécimaux, deux générations successives distinctes), activation/révocation/
    régénération (l'ANCIEN token cesse immédiatement de retrouver le cheval après régénération OU
    révocation, le NOUVEAU fonctionne immédiatement), recherche inverse token -> cheval rejetant
    tout format invalide (trop court, casse différente, chaîne vide) AVANT toute requête, jamais
    l'ID WordPress ni le Global Horse ID comme token (comparaison explicite avec un vrai UUID de
    Global Horse ID). `gwseq_horse_share_fiche_info()` : lien public pour un cheval publiquement
    visible sans partage privé, aucun lien pour un brouillon sans partage privé (§2.C), un partage
    privé explicitement créé pour un brouillon fonctionne, et — cas de sécurité important — le
    partage privé prend TOUJOURS le pas sur un statut publié (un cheval ne "fuit" jamais son URL
    publique dès qu'un partage privé est activé pour lui). `fiche_type` exposé et cohérent avec
    `fiche_url`/`fiche_default_checked` dans `gwseq_get_horse_shareable_data()`. Open Graph sur la
    route de partage privé : émis pour un brouillon (jamais bloqué par la seule visibilité
    publique), `og:url` pointe vers le lien PRIVÉ effectivement visité (jamais l'URL publique),
    balise noindex systématique, aucune fuite de prix, absent si la même fiche est consultée HORS
    de sa route privée (le token seul ne suffit pas, il faut la bonne route), et — exception
    documentée — notre balisage reste actif MÊME si un plugin SEO tiers est détecté sur cette route
    précise. Aucune migration destructive : activer/révoquer ne modifie jamais le titre ni le
    statut du cheval.
  - `gws-equestrian-cheval-share-admin-test.php` (86 assertions au total) : uniquement la glue
    propre à ce fichier (jamais la logique de token déjà testée côté logic-test) — prédicat de
    permission (`gwseq_horse_private_share_user_can_manage()` : propriétaire autorisé, identifiant
    inexistant/zéro/autre post_type refusés, utilisateur sans `edit_others_posts` refusé pour la
    fiche d'un autre auteur) ; clause d'exclusion meta_query réutilisée telle quelle par les filtres
    REST (liste ET accès direct par identifiant, ce dernier bloqué en 404 pour qui ne peut pas
    éditer la fiche, jamais pour l'éditeur lui-même) et sitemap (jamais pour un autre post_type que
    Cheval) ; filtre `pre_get_posts` exercé via un faux `WP_Query` minimal (recherche/archive
    Cheval/taxonomie Catégorie reçoivent la clause, une requête sans rapport ou une sous-requête
    n'est jamais touchée) ; rendu de la boîte latérale "Partage" dans ses trois états (aucun
    partage actif -> bouton de création seul ; actif -> URL affichée + Révoquer/Régénérer, jamais
    le bouton de création en même temps ; utilisateur sans droit d'édition -> aucun contrôle de
    partage privé affiché du tout).
  - `gws-equestrian-cheval-share-runtime-test.js` (74 assertions au total) : nouveau libellé
    "Inclure le lien vers la fiche"/"Inclure le lien privé vers la fiche" vérifié selon `fiche_type`
    (`publique`/`privee`), jamais l'ancien "Ajouter la fiche complète".
  - `gws-equestrian-foundations-test.php` et `gws-equestrian-actualites-logic-test.php` : stubs
    `get_option()`/`update_option()`/`add_rewrite_tag()`/`add_rewrite_rule()` ajoutés (ces deux
    fichiers isolent `module.php` seul et exécutent immédiatement tout hook `init` — le nouveau
    déclencheur de flush par version et l'enregistrement de la règle de réécriture du partage privé
    en ont besoin pour s'exécuter sans erreur), sans ajouter aucune assertion nouvelle : ces deux
    suites restent centrées sur leur périmètre d'origine (fondations/Actualités).

  Deux correctifs vérifiés par retrait/restauration : neutraliser la priorité du partage privé dans
  `gwseq_horse_share_fiche_info()` (revient à ne considérer que le statut public) fait échouer
  exactement les 3 assertions qui en dépendent ; retirer la validation de format dans
  `gwseq_horse_private_share_find_cheval_id()` fait échouer l'assertion "chaîne vide rejetée" (le
  faux `WP_Query` de ce fichier traite alors, à tort, une recherche à vide comme trouvant le premier
  cheval sans token — confirmant la nécessité réelle de ce garde-fou, pas un test qui se contente de
  lui-même). Intégralité de la suite (21 fichiers PHP + 3 suites JS runtime) ré-exécutée après ce
  lot : aucune régression.
- **Lot 1 « Partager & vendre » : deux correctifs suite à revue avant recette (0.27.0)** : sans
  nouveau fichier de test, uniquement `gws-equestrian-cheval-share-admin-test.php` (93 assertions au
  total) — les deux points corrigés vivent tous les deux dans la glue WordPress de ce fichier.
  - Exclusion `/wp/v2/search` : `gwseq_horse_private_share_filter_rest_search_query()` testé comme
    les trois autres filtres d'exclusion déjà en place — la MÊME clause d'exclusion (comparée par
    référence de valeur, jamais une reconstruction séparée) est ajoutée même quand la requête porte
    sur plusieurs post types à la fois (`post`/`page`/Cheval mélangés, cas réel de ce contrôleur
    sans `subtype` explicite), et s'AJOUTE à un `meta_query` déjà présent sans jamais l'écraser.
  - Anti-cache de la route privée : `gwseq_horse_private_share_nocache_header_values()` (données
    pures) vérifié contenant bien `no-store` dans `Cache-Control` et `Pragma: no-cache` ;
    `gwseq_horse_private_share_send_nocache_headers()` (l'envoi réel) vérifié via un stub de
    `nocache_headers()` (comptage d'appels) et `defined('DONOTCACHEPAGE') === true` — sans jamais
    dépendre de l'état réel des en-têtes HTTP du processus PHP (impossible à inspecter fiablement
    en CLI une fois que d'autres assertions ont déjà produit du texte sur la sortie standard,
    d'où la séparation données/envoi). Un appel répété ne tente jamais de redéfinir la constante
    (capturé via `try`/`catch`, aucune exception attendue).

  Les deux correctifs vérifiés par retrait/restauration (retirer l'enregistrement du filtre
  `wp_rest_search_query` ; vider les valeurs d'en-têtes anti-cache puis retirer la garde
  `DONOTCACHEPAGE`) font chacun échouer exactement les assertions dédiées, aucune autre.
  Intégralité de la suite (21 fichiers PHP + 3 suites JS runtime) ré-exécutée après ce lot : aucune
  régression. Seuls deux fichiers touchés dans tout le dépôt pour cette correction (le fichier de
  glue et son test), conformément à la demande de ne rien modifier d'autre dans ce lot.
- **Lot 1 « Partager & vendre » : correctif bloquant, création d'un lien privé (0.28.0)** : premier
  test réel — cliquer sur "Créer un lien de partage privé" redirigeait vers la liste "Actualités"
  au lieu de revenir sur la fiche. Cause racine : des `<form>` imbriqués dans le grand formulaire
  d'édition WordPress (invalide en HTML). Sans nouveau fichier de test.
  - `gws-equestrian-cheval-share-admin-test.php` (109 assertions au total) : régression EXPLICITE
    contre la cause racine — le HTML rendu par la boîte latérale "Partage" est vérifié comme ne
    contenant JAMAIS de balise `<form` (dans les DEUX états, sans/avec partage privé actif), et les
    actions Régénérer/Révoquer sont bien rendues comme des liens `<a>`. `gwseq_horse_private_share_
    action_url()` testé isolément (cible `admin-post.php`, action `activer`/`revoquer` correcte,
    `cheval_id` correctement transmis, nonce présent et propre à L'ACTION PRÉCISE `gwseq_partage_
    prive_{id}` — jamais un nonce générique partagé entre deux chevaux différents).
    `gwseq_horse_private_share_redirect_url_after_action()` — LE point explicitement demandé par la
    recette ("un test couvrant explicitement l'URL de redirection finale") — vérifié ramenant vers
    l'écran d'édition DU MÊME cheval (jamais une autre liste), URL toujours interne à `/wp-admin/`
    (aucun risque d'open redirect, ni `get_edit_post_link()` ni `admin_url()` ne dépendent d'une
    entrée utilisateur), et repli explicite vers la liste des Chevaux si `get_edit_post_link()`
    échoue exceptionnellement (capacité réévaluée différemment entre-temps) — jamais une URL vide,
    jamais le repli WordPress générique vers le Tableau de bord qui a précisément produit le
    symptôme observé en recette.

  Les deux correctifs vérifiés par retrait/restauration : réintroduire un `<form>` imbriqué fait
  échouer exactement l'assertion de régression dédiée ; retirer le repli explicite de `gwseq_horse_
  private_share_redirect_url_after_action()` fait échouer exactement les deux assertions du
  scénario d'échec de `get_edit_post_link()`. Intégralité de la suite (21 fichiers PHP + 3 suites JS
  runtime) ré-exécutée après ce lot : aucune régression. Seuls deux fichiers touchés dans tout le
  dépôt (le fichier de glue et son test), conformément à la demande de ne rien développer d'autre.
- **Lot 1 « Partager & vendre » : ajustement d'architecture, visibilité publique vs lien privé
  (0.29.0)** : sans nouveau fichier de test. La priorité "privé > public" de `gwseq_horse_share_
  fiche_info()` est inversée, et cinq fonctions/filtres devenus incorrects (blocage du permalink
  normal, quatre filtres d'exclusion recherche/sitemap/REST) sont RETIRÉS — leurs tests dédiés le
  sont donc aussi, remplacés par la couverture des huit scénarios explicitement demandés.
  - `gws-equestrian-cheval-share-logic-test.php` (144 assertions au total) : les huit scénarios —
    cheval public sans token -> public ; cheval public AVEC token -> le lien reste PUBLIC et
    `gwseq_horse_is_publicly_viewable()` reste vraie (aucune "404 publique causée uniquement par
    l'existence d'un token") ; cheval non public sans token -> aucun lien ; cheval non public AVEC
    token -> privé ; passage public -> privé (le token existant, jamais révoqué automatiquement,
    redevient utilisable immédiatement) ; passage privé -> public (le partage utilise désormais
    l'URL publique) ; l'ancien token reste techniquement valide après ce passage (`gwseq_horse_
    private_share_find_cheval_id()` le retrouve toujours) ; et le nouveau prédicat `gwseq_horse_is_
    private_share_only()` vérifié dans chacun de ces cas (faux dès que le cheval est public, même
    avec un token, vrai seulement quand ni l'un ni l'autre canal normal n'existe). Cohérence de
    `gwseq_render_horse_og_meta()` également couverte : un cheval public visité via un ancien lien
    privé affiche désormais l'URL PUBLIQUE, jamais de noindex.
  - `gws-equestrian-cheval-share-admin-test.php` (103 assertions au total) : les quatre états de la
    boîte latérale "Partage" adaptés à la visibilité RÉELLE (public sans token : message "fiche
    publique", jamais de bouton "Créer" ; public AVEC un ancien token : même message en premier,
    l'ancien lien signalé avec la seule action "Révoquer", jamais "Créer"/"Régénérer" mis en avant ;
    non public sans token : bouton de création ; non public AVEC token : URL privée + Régénérer/
    Révoquer, inchangé). Les tests des filtres retirés (exclusion meta_query, REST liste/direct/
    recherche transversale, sitemap, `pre_get_posts`) sont supprimés avec le code qu'ils
    couvraient — cette exclusion n'est plus nécessaire, WordPress l'assure déjà nativement dès
    qu'un cheval "partage privé exclusif" est, par construction, toujours non publié.

  Deux correctifs vérifiés par retrait/restauration : rétablir la priorité "privé > public" fait
  échouer exactement les deux assertions qui dépendent de la nouvelle priorité (cheval public avec
  token, passage privé -> public) ; rétablir l'ancienne condition "public seulement si aucun token"
  dans le rendu de la boîte latérale fait échouer exactement les deux assertions "cheval public
  AVEC token" qui vérifient que le message de fiche publique reste affiché en premier. Intégralité
  de la suite (21 fichiers PHP + 3 suites JS runtime) ré-exécutée après ce lot : aucune régression.
- **Lot 1 « Partager & vendre » : ajustement UX/métier, statut de diffusion et sauvegarde
  (0.30.0)** : sans nouveau fichier de test — deux problèmes produit révélés par la recette du
  0.29.0, traités dans les deux fichiers de test déjà existants.
  - `gws-equestrian-cheval-share-logic-test.php` (154 assertions au total, +10) : nouvelle fonction
    centrale `gwseq_horse_diffusion_state()` (+ `gwseq_horse_diffusion_state_label()`) vérifiée sur
    les trois états et leurs transitions — brouillon sans token -> "En préparation" ; token actif
    sur un cheval non public -> "Diffusion privée" ; passage à `publish` -> "Visible sur le site"
    même avec un ancien token toujours actif (la priorité publique de l'ajustement d'architecture
    0.29.0 n'est jamais remise en cause) ; repassage non public avec le même token encore actif ->
    de nouveau "Diffusion privée" ; révocation -> retour à "En préparation" ; cheval publié sans
    aucun token -> "Visible sur le site" directement.
  - `gws-equestrian-cheval-share-admin-test.php` (126 assertions au total, +23) : les quatre tests
    de la boîte latérale "Partage" (états 510/513) étendus pour vérifier la ligne "Statut de
    diffusion :" avec le libellé métier attendu, et que "Créer"/"Régénérer" sont désormais de vrais
    `<button type="submit">` portant le champ `GWSEQ_HORSE_PRIVATE_SHARE_SUBMIT_FIELD` (jamais plus
    un lien `admin-post.php?action=gwseq_partage_prive_activer`), tandis que "Révoquer" reste un
    lien. Le test de régression "aucun `<form>` imbriqué" (bug 0.28.0) est adapté et clarifié : la
    cause racine de ce bug était un `<form>` IMBRIQUÉ, jamais un `<button>` ordinaire du formulaire
    d'édition existant — la nouvelle assertion vérifie l'absence de `<form>` ET la présence du
    nouveau bouton de soumission, sans les confondre. Nouvelle section dédiée à
    `gwseq_horse_private_share_maybe_activate_on_save()` (greffée sur `save_post_{cpt}`) : champ
    absent -> aucune activation ; champ présent -> activation réelle (`gwseq_horse_private_share_
    is_active()` devient vraie) ; ressoumission -> régénération (nouveau token, distinct du
    précédent) ; utilisateur sans droit d'édition, révision, et `DOING_AUTOSAVE` (ce dernier testé
    en tout dernier, même contrainte que le reste de la suite) -> aucune activation dans les trois
    cas. Nouvelle section dédiée à `gwseq_cheval_admin_list_post_states()` (filtre `display_post_
    states`, scopé au CPT Cheval) : brouillon sans token -> "En préparation" remplace intégralement
    "Brouillon" natif ; brouillon avec token -> "Diffusion privée" ; cheval visible sur le site ->
    aucun état affiché (comme WordPress pour un contenu publié) ; un autre type de contenu (Page) ->
    entrée `$post_states` transmise inchangée, jamais altérée.

  Trois correctifs vérifiés par retrait/restauration : réintroduire l'ancienne priorité "public
  d'abord, token ensuite" dans `gwseq_horse_diffusion_state()` (en l'inversant) fait échouer
  exactement l'assertion "passage à publish -> Visible sur le site, même avec un ancien token
  toujours actif" ; neutraliser `gwseq_horse_private_share_maybe_activate_on_save()` (`return;` en
  première ligne) fait échouer exactement les deux assertions d'activation/régénération ;
  neutraliser `gwseq_cheval_admin_list_post_states()` (retour de `$post_states` inchangé) fait
  échouer exactement les trois assertions de remplacement d'état. Intégralité de la suite (21
  fichiers PHP + 3 suites JS runtime) ré-exécutée après chaque restauration : aucune régression.
- **Lot 1 « Partager & vendre » : piloter la diffusion avec le vocabulaire GWS (0.31.0)** : sans
  nouveau fichier de test.
  - `gws-equestrian-cheval-share-logic-test.php` (182 assertions au total, +28) : les trois
    nouvelles fonctions de transition `gwseq_horse_diffusion_set_en_preparation()`/
    `_diffusion_privee()`/`_visible_site()` couvertes sur tous les scénarios demandés — nouvel objet
    = "En préparation" ; préparation -> privé (token créé, statut `draft`, jamais `private` natif) ;
    privé -> préparation (token révoqué) ; préparation -> public ; privé -> public (token existant
    JAMAIS révoqué automatiquement) ; public -> préparation (aucun token, statut `draft`) ; public
    (avec un ancien token) -> privé (le token EXISTANT est réutilisé, jamais régénéré inutilement) ;
    public -> privé sans ancien token (un nouveau token est créé) ; "rendre public" lève
    systématiquement un mot de passe résiduel (sans quoi la transition échouerait silencieusement à
    produire l'état qu'elle annonce) ; un cheval déjà au statut natif `private` reste classé selon
    son état métier réel (jamais un état séparé), avec ou sans token.
  - `gws-equestrian-cheval-share-admin-test.php` (163 assertions au total, +37) : boîte "État de
    diffusion" — `gwseq_replace_cheval_publish_box()` retire bien `submitdiv` scopé au CPT Cheval et
    enregistre la nouvelle boîte (side/high) ; rendu des trois états (champ caché `post_status`
    préservant le statut actuel, libellé "Enregistrer"/"Enregistrer les modifications" selon l'état,
    boutons de transition corrects par état, jamais "Repasser en préparation" depuis "En
    préparation" ni "Activer la diffusion privée" depuis "Diffusion privée", jamais le vocabulaire
    "Brouillon"/"Publier"/"Dépublier", section "Retirer la fiche du site" à deux choix explicites
    pour "Visible sur le site", rien de rendu sans capacité `edit_post`) ; capacité `publish_post`
    vérifiée côté affichage (bouton absent) ET côté hook `gwseq_horse_apply_diffusion_transition_on_
    save()` (transition refusée même si le champ est soumis — défense en profondeur) ; garde de
    réentrance (`remove_action()`/`add_action()` autour de l'appel à `wp_update_post()`, motif
    standard WordPress pour un hook qui se redéclenche lui-même) vérifiée : le hook reste enregistré
    exactement une fois après l'opération ; valeur de transition invalide ignorée ; révision et
    autosave sans effet (autosave testé en tout dernier, même contrainte que le reste de la suite).
    Nouvelle section pour l'audit non destructif `gwseq_cheval_native_visibility_mismatches()`/
    `gwseq_cheval_admin_native_visibility_notice()` (includes/cheval-fields.php) : détecte le statut
    `private` natif ET la protection par mot de passe, jamais un cheval "normal" ; fonction pure
    (aucune écriture) ; notice affichée uniquement sur la liste Chevaux, jamais ailleurs, jamais si
    aucune fiche concernée. Boîte "Partage" : les quatre tests d'état existants adaptés (le bouton
    "Créer" a disparu de cette boîte — centralisé dans "État de diffusion" — remplacé par un renvoi
    explicite vers cette dernière ; "Régénérer"/"Révoquer" y restent inchangés).

  Quatre correctifs vérifiés par retrait/restauration : retirer le contrôle de capacité
  `publish_post` du hook de transition fait échouer exactement l'assertion "visible_site REFUSÉE
  sans la capacité publish_post" ; retirer l'appel à `remove_meta_box('submitdiv', ...)` fait
  échouer exactement l'assertion de retrait scopé de la boîte "Publier" ; neutraliser
  `gwseq_cheval_native_visibility_mismatches()` (retour d'un tableau vide) fait échouer exactement
  les trois assertions qui en dépendent (détection, notice, fiches nommément listées) ; retirer
  `'post_password' => ''` de `gwseq_horse_diffusion_set_visible_site()` fait échouer exactement les
  deux assertions du scénario "rendre public une fiche protégée par mot de passe". Intégralité de la
  suite (24 fichiers) ré-exécutée après chaque restauration : aucune régression.
- **Filtre "État de diffusion" sur les listes Chevaux + correctif import IFCE (indices sportifs)
  (0.32.0)** : deux correctifs indépendants, aucun nouveau fichier de test PHP.
  - `gws-equestrian-cheval-share-logic-test.php` : nouvelle fonction `gwseq_cheval_ids_by_diffusion_
    state()` testée sur les trois états (seul le cheval réellement dans l'état demandé apparaît dans
    la liste correspondante) et sur une transition (déplacement immédiat d'une liste à l'autre,
    jamais une liste figée).
  - `gws-equestrian-cheval-logic-test.php` (requiert désormais aussi `cheval-share.php`, ainsi que
    de nouveaux stubs `get_post()`/`WP_Query` minimaux) : le sélecteur "État de diffusion" est bien
    rendu dans `gwseq_render_cheval_admin_list_filters()` avec les trois options au libellé métier
    centralisé (`gwseq_horse_diffusion_state_label()`, jamais un second vocabulaire) et l'option
    "Tous" par défaut ; persistance de la valeur sélectionnée ; `gwseq_apply_cheval_admin_list_
    filters()` restreint bien la requête via `post__in` à partir de l'état demandé ; "Tous" (valeur
    vide) n'ajoute aucune restriction ; une valeur invalide est ignorée ; cumulable avec le filtre
    Sexe existant (post__in ET meta_query coexistent sur la même requête, jamais l'un n'écrase
    l'autre).
  - `gws-equestrian-cheval-share-admin-test.php` (+13 assertions) : `gwseq_sanitize_horse_share_
    filters()` accepte/rejette la nouvelle clé `diffusion` (même trois valeurs que
    `gwseq_horse_diffusion_states()`) ; `gwseq_horse_share_filters_to_query_args()` la transforme en
    `post__in` (état dérivé, jamais exprimable par un meta_query direct) ; "Tous" n'ajoute aucune
    restriction ; cumul réel via l'AJAX de recherche (`filters.diffusion` + `filters.sexe`) : seul le
    cheval correspondant aux DEUX critères à la fois est retourné.
  - `gws-equestrian-cheval-share-runtime-test.js` (+9 assertions, 77 au total) : le sélecteur "État
    de diffusion" est rendu avec les trois options et son `<label>` visible associé via for/id (même
    exigence d'accessibilité que les filtres existants) ; transmis dans le même appel de recherche
    que les autres filtres et le texte libre (cumulatifs) ; réinitialisé par "Réinitialiser les
    filtres" comme les autres sélecteurs.
  - `gws-equestrian-ifce-import-test.php` (548 assertions au total) : nouvelle fixture réelle
    `tests/fixtures/ifce-aganix-d-aubigny.pdf` — document bien reconnu, ISO/ICC/IDR tous vides (le
    document ne mentionne aucun indice sportif pour ce cheval), BSO +16 (0.51) correctement extrait
    (vérification explicite demandée : le correctif des indices sportifs ne casse pas les indices
    génétiques). Les cinq autres fixtures réelles déjà présentes (`ifce-asb-conquistador`,
    `ifce-cornet-obolensky`, `ifce-untouchable-27`, `ifce-quaprice-bois-margot`, `ifce-iowa-jal`)
    complétées avec des assertions sur leurs indices : chacune s'est avérée porter au moins un ISO ou
    un indice génétique erroné AVANT ce correctif (audité explicitement pendant le développement),
    tous corrigés ; `ifce-iowa-jal` sert de fixture de référence pour l'extraction POSITIVE d'un ISO
    réel (ISO 112 (0.92) (2025) reste correctement extrait, alors que son ICC/BCC erronés
    disparaissent) ; `ifce-cornet-obolensky` prouve qu'un cheval avec DEUX indices génétiques
    légitimes dans sa propre zone (BSO et BCC) les conserve tous les deux. Trois assertions
    unitaires isolées sur du texte synthétique (indépendantes de la fidélité d'extraction PDF) :
    un ISO placé après "Pedigree" n'est jamais retenu, même sans aucun autre ISO ailleurs (aucun
    fallback) ; un ISO placé avant reste retenu ; en l'absence de toute ligne d'en-tête pedigree, la
    totalité du texte reste la zone de recherche (repli explicite). Nouvelle fonction
    `gwseq_ifce_find_pedigree_heading_index()` testée isolément (index exact, reconnaît aussi
    "Généalogie"/"Origines", renvoie `null` si aucune ligne d'en-tête).

  Cinq correctifs vérifiés par retrait/restauration : neutraliser `gwseq_cheval_ids_by_diffusion_
  state()` (retour d'un tableau vide) fait échouer exactement les assertions qui en dépendent dans
  LES TROIS fichiers PHP qui l'utilisent (logic-test, cheval-logic-test, admin-test) ; retirer
  l'application du filtre dans `gwseq_apply_cheval_admin_list_filters()` fait échouer exactement les
  trois assertions de restriction `post__in`/cumul de la liste d'administration ; recroiser
  `gwseq_ifce_parse_text()` sur le texte ENTIER (au lieu de la zone restreinte) fait échouer
  exactement les sept assertions qui dépendent de la délimitation (cinq fixtures réelles + L'Aganix
  + le test synthétique). Intégralité de la suite (25 fichiers, dont la nouvelle fixture PDF)
  ré-exécutée après chaque restauration : aucune régression.
- **Correctifs de clôture Lot 1 : libellé du filtre + import IFCE Naisseur (0.33.0)** : deux
  correctifs indépendants, aucun nouveau fichier de test.
  - `gws-equestrian-cheval-logic-test.php` : l'assertion du libellé par défaut du filtre "État de
    diffusion" attend désormais `>Tous les états de diffusion<` (plus `>Tous<`) ; deux nouvelles
    assertions confirment que le formulaire d'identité affiche bien `>Naisseur<` et plus jamais
    `>Éleveur<`.
  - `gws-equestrian-cheval-share-runtime-test.js` (77 assertions, inchangé en nombre) : l'assertion
    sur les options par défaut des filtres attend désormais "Tous les états de diffusion" pour le
    sélecteur diffusion (Sexe/Catégorie inchangés : "Tous"/"Toutes les catégories").
  - `gws-equestrian-ifce-import-test.php` (601 assertions au total) : sur la fixture L'Aganix,
    nouvelle assertion sur le naisseur correctement extrait ("Docteur Vete Pierre Valette 42600
    CHALAIN LE COMTAL", document utilisant "Naisseur principal :") plus deux assertions de
    non-régression pedigree (14 ascendants, père "AGANIX DU SEIGNEUR"). Nouvelle section dédiée :
    non-régression sur les 4 autres fixtures réelles à "Naisseur :" simple (Asb Conquistador,
    Cornet Obolensky, Untouchable 27, Iowa Jal) ; exclusion du texte d'opposition SIRE sur la
    fixture Quaprice Bois Margot (précédemment importé à tort comme un nom de naisseur) ; trois
    tests unitaires isolés de `gwseq_ifce_is_naisseur_privacy_opt_out()` ; cas synthétique sans
    naisseur dans le document (champ vide, aucune invention) ; cas synthétique avec un naisseur
    mentionné uniquement après l'en-tête "Pedigree" (jamais retenu pour le sujet, même
    délimitation de zone que le correctif indices de la 0.32.0).

  Trois correctifs vérifiés par retrait/restauration : revenir à l'alternative de regex sans le
  qualificatif "principal" fait échouer exactement l'assertion d'extraction du naisseur de
  L'Aganix ; retirer la garde d'opposition SIRE (affectation inconditionnelle de la valeur
  capturée) fait échouer exactement l'assertion d'exclusion Quaprice ; revenir au libellé
  "Éleveur" dans `cheval-fields.php` fait échouer exactement les deux nouvelles assertions de
  libellé dans `gws-equestrian-cheval-logic-test.php`. Intégralité de la suite ré-exécutée après
  chaque restauration : aucune régression.
- **Suite V1 « Partager & vendre », Lot 2A : modèle et persistance de la Sélection de chevaux
  (0.34.0)** — trois NOUVEAUX fichiers de test.
  - `gws-equestrian-cheval-selection-logic-test.php` (couche métier, `includes/cheval-selection.
    php`) : token (génération au format attendu, activation/lecture, régénération invalidant
    immédiatement l'ancien token, révocation NON DESTRUCTIVE — le post et son titre restent
    intacts), recherche inverse token -> sélection (format rejeté avant requête, token inconnu,
    une sélection en corbeille ne résout jamais, strictement `publish` — un statut ni publié ni en
    corbeille ne résout pas non plus, défense en profondeur) ; sanitation/dédoublonnage des IDs de
    chevaux (ordre de première occurrence conservé, entrée non-tableau sans erreur) ; règle
    d'éligibilité (réutilise exclusivement `gwseq_horse_diffusion_state()` — "En préparation"
    jamais éligible, cheval inexistant/autre CPT/en corbeille jamais éligible) et son filtre
    (dédoublonnage + éligibilité combinés, ordre préservé) ; résolution pour affichage (état et
    lien de fiche toujours À JOUR, y compris après un changement de diffusion ultérieur — cheval
    passé de "Diffusion privée" à "Visible sur le site" : le lien devient public ; cheval repassé
    "En préparation" : devient non présentable SANS jamais être retiré de la liste stockée) ;
    titre (libellé neutre calculé à l'affichage seulement, jamais stocké) ; création (ordre
    préservé, dédoublonnage, exclusion automatique d'un ID "En préparation" soumis malgré tout,
    token actif immédiatement, 1 seul cheval accepté, aucune limite haute arbitraire testée
    jusqu'à 50 chevaux, entrée malformée sans erreur fatale, sélection sans plus aucun cheval
    diffusable restant un objet valide et intact).
  - `gws-equestrian-cheval-selection-admin-test.php` (glue wp-admin, `includes/cheval-selection-
    admin.php`) : menu (sous-menu de "Chevaux", capacité `edit_posts`) ; recherche réutilisant
    EXACTEMENT le moteur de l'écran « Partager », avec l'exclusion de "En préparation" vérifiée à
    trois niveaux (résultats par défaut, tentative de forcer le filtre "En préparation" côté
    client sans effet, options du filtre elles-mêmes limitées à deux valeurs) ; sécurité AJAX
    (nonce, capacité, y compris via le point d'entrée réel) ; création AJAX (IDs partiellement
    invalides silencieusement écartés — "En préparation", autre CPT, inexistant, doublon —, cheval
    d'un autre auteur sans `edit_others_posts` écarté, nonce invalide rejeté) ; permission de
    gestion du token (propriétaire vs auteur différent, ID inexistant, ID d'un autre CPT) et
    construction des URLs nonce-protégées de régénération/révocation ; restriction "mes propres
    sélections" ; ligne d'administration (comptage total/diffusable recalculé à la volée après un
    changement de diffusion ultérieur) ; chargement conditionnel des assets et contenu localisé
    (liste des sélections existantes, filtre diffusion à deux valeurs, vocabulaire identique à
    l'écran « Partager »).
  - `gws-equestrian-cheval-selection-runtime-test.js` (exécution RÉELLE de `assets/cheval-
    selection-admin.js`, 32 assertions, même méthodologie que `gws-equestrian-cheval-share-
    runtime-test.js` — DOM minimal fait main, aucune dépendance npm) : vue liste (message explicite
    si vide, une ligne par sélection, lien copiable pour un token actif, action Régénérer/Révoquer
    selon l'état, confirmation obligatoire avant toute action) ; bascule liste <-> création
    (bouton dédié, retour, ouverture directe via `?vue=nouvelle`) ; vue création (une case par
    résultat de recherche, compteur "N chevaux sélectionnés" mis à jour en temps réel, activation/
    désactivation du bouton de création, ordre Monter/Descendre vérifié sur le panneau de
    sélection, retrait synchronisant bien la case décochée dans les résultats, appel AJAX de
    création transmettant le titre et les IDs DANS L'ORDRE de la sélection en cours, redirection
    après succès, message d'erreur affiché et bouton réactivé après un échec serveur, recherche
    appelant bien le point d'entrée AJAX dédié `gwseq_selection_search_cheval`, jamais celui de
    l'écran « Partager »).

  Trois correctifs vérifiés par retrait/restauration : neutraliser la règle d'éligibilité
  (`gwseq_selection_horse_is_eligible()` toujours vraie) fait échouer exactement les assertions
  qui en dépendent dans les deux fichiers PHP (logique + admin) ; assouplir la recherche inverse
  par token de `publish` strict à `any` fait échouer exactement l'assertion dédiée à un statut ni
  publié ni en corbeille (ajoutée précisément pour distinguer les deux, un premier essai avec
  seulement une sélection en corbeille ne suffisant pas à isoler cette règle — `any` excluant déjà
  la corbeille) ; réintroduire "En préparation" dans les états éligibles de l'écran Sélections
  fait échouer exactement les cinq assertions qui dépendent de cette exclusion. Intégralité de la
  suite ré-exécutée après chaque restauration : aucune régression. Deux fichiers de non-régression
  préexistants mis à jour (le nouveau CPT interne porte le nombre total de post types métier GWS
  de quatre à cinq) : `gws-equestrian-foundations-test.php` et
  `gws-equestrian-actualites-logic-test.php`.

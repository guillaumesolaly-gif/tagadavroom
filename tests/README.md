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
```

(`tests/qa-toggle-logic-test.php` est appelé automatiquement par `starter-logic-test.php`, dans
un processus PHP séparé — il peut aussi être lancé seul.)

**`gws-equestrian-cheval-admin-tabs-runtime-test.js`** et
**`gws-equestrian-race-referentiel-autocomplete-runtime-test.js`** sont les SEULS fichiers de ce
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
- **Composant d'autocomplétion Race/Stud-book — cause runtime exacte NON reproduite en test
  automatisé (0.14.3)** : malgré une instrumentation exhaustive (dix points de diagnostic, `try`/
  `catch` dédié sur chaque interaction réelle, voir plus haut) ajoutée précisément pour cette recette,
  ce fichier de test n'a pas reproduit le symptôme signalé sur un vrai wp-admin (aucune suggestion à
  la frappe malgré une initialisation confirmée réussie par les logs) — un DOM simulé fait main, quel
  que soit son degré de fidélité, ne peut pas structurellement garantir l'absence de tout écart avec
  un VRAI moteur de rendu de navigateur. C'est précisément la raison d'être du filet de sécurité
  obligatoire (`<select>` de secours, voir plus haut) : indépendamment de la résolution éventuelle de
  cette cause, la saisie d'une race reste garantie possible. Reste à confirmer en conditions réelles :
  (1) que l'instrumentation ajoutée révèle effectivement, dans la console d'un vrai navigateur, le
  point exact où l'exécution diverge lors d'une frappe réelle ; (2) le comportement visuel réel du
  `<select>` de secours (positionnement, lisibilité) tel que rendu par le vrai CSS d'administration
  WordPress ; (3) la transition visuelle entre `<select>` de secours et composant de recherche une
  fois l'initialisation JS réussie (aucun scintillement, aucun état intermédiaire visible).

# Changelog — GWS Equestrian (module)

Historique propre à ce module, distinct de la version du plugin `gws-core` qui l'héberge (voir
`GWSEQ_MODULE_VERSION` dans `module.php`). Le module atteindra la version `1.0.0` au gel de la V1
(fin de la dernière étape du plan de développement validé). Chaque étape ci-dessous a été livrée
puis recettée en conditions réelles avant validation de la suivante.

## 0.6.0 — Étape 5 : corrections post-recette runtime (Race/Stud-book, contexte de saisie, présentation)

La recette runtime de l'Étape 5 (saisie réelle du pedigree de Jamerose) a validé le modèle
fonctionnel (relations GWS/externe, ascendants externes récursifs, conservation non destructive,
resolver, limite à 4 générations, approche programmatique) mais a révélé des problèmes UX
importants lors de la saisie réelle sur plusieurs générations, corrigés dans cette version.

- **Race/Stud-book d'un ascendant externe harmonisé avec la fiche Cheval.** Était un champ texte
  libre (source constatée d'hétérogénéité : « SF »/« sf »/« Selle Français »...). Réutilise
  désormais EXACTEMENT le référentiel de `gwseq_cheval_race_options()` (défini dans
  `cheval-fields.php`, jamais dupliqué) à chaque génération de chaque branche externe : liste
  fermée + « Autre » avec précision libre. Stockage passé de `breed` (texte libre) à `race` (code
  technique) + `race_autre` (texte, si `race === 'autre'`).
- **Compatibilité ascendante sans perte de données.** Un pedigree déjà enregistré avec l'ancien
  format `breed` n'est jamais perdu : `gwseq_migrate_external_ancestor_node()` reconnaît à la
  LECTURE (jamais une réécriture automatique, jamais une migration destructive) une ancienne
  valeur correspondant à un code ou un libellé canonique du référentiel (comparaison insensible à
  la casse et aux accents) ; sinon elle est conservée intégralement via `race = 'autre'` +
  `race_autre` = texte original (ex. une ancienne abréviation « SF » non reconnue reste
  entièrement récupérable, jamais perdue ni devinée arbitrairement). Le format en base n'est
  réécrit qu'au prochain enregistrement volontaire de cette relation.
- **Contexte de saisie — correction du problème principal constaté en recette (perte de repère
  lors de la saisie sur plusieurs générations).** Chaque niveau affiche désormais un intitulé
  contextuel construit à partir du nom déjà enregistré (« Père de UNTOUCHABLE 27 », « Père de
  HORS LA LOI II »...), jamais une nomenclature généalogique complexe (« grand-père paternel »...)
  ni un Père/Mère nu sans contexte. Le bouton de divulgation progressive devient lui aussi
  contextuel (« + Renseigner les origines de HORS LA LOI II »). Un repli explicite (« cet
  ascendant ») s'applique tant que le nom n'est pas encore renseigné. Volontairement AUCUN
  JavaScript ne met à jour ces intitulés en direct pendant la frappe (jugé suffisant : un
  enregistrement de la fiche les rafraîchit ; un texte d'aide le rappelle à l'écran) — solution
  plus légère qu'un composant de mise à jour dynamique, conformément à la préférence exprimée.
- **Repère de progression — second problème constaté (l'utilisateur ne savait pas jusqu'où
  remonter).** Chaque niveau affiche désormais « Génération N sur 4 », la génération 4 étant
  explicitement identifiée comme « — dernière génération ». À cette génération, plus AUCUN
  contrôle « + Renseigner ses origines » n'est proposé (arrêt visuel strict) — la limite serveur
  déjà existante (`gwseq_sanitize_external_ancestor_tree()`, inchangée dans son principe) reste
  évidemment la garantie réelle contre une requête manipulée.
- **Convention de présentation des noms de chevaux** (nouveau helper partagé,
  `gwseq_format_horse_name_display()` dans `cheval-fields.php`) : majuscules, sans accents, pour
  l'affichage dans l'interface du pedigree (« Étoile du Lys » → « ETOILE DU LYS ») — apostrophes/
  traits d'union/chiffres/espaces conservés. Jamais une transformation de la donnée source
  (`post_title`/`name` d'un ascendant externe restent enregistrés exactement tels que saisis) ;
  jamais utilisée pour Race/Stud-book, qui reste une valeur structurée via référentiel. Réutilisable
  plus tard par le front, PDF, impression, catalogue, Social Kit — utilisée à cette étape
  uniquement là où elle améliore l'interface Pedigree.
- **Nouvelle piste future actée en roadmap, aucun développement** : connecteur IFCE/SIRE optionnel
  (webservices SIRE de l'IFCE pour préremplir une fiche depuis un numéro SIRE/UELN) — GWS
  Equestrian reste entièrement fonctionnel sans lui, la saisie structurée manuelle reste le
  fonctionnement nominal. Compatibilité architecturale déjà vérifiée sans aucune modification :
  un futur connecteur n'aurait qu'à mapper ses propres données vers la forme
  `{mode, horse_id, external}` déjà attendue par `gwseq_set_horse_parent()`. Idem pour une
  bibliothèque facultative d'étalons/ascendants comme aide à la saisie (aucun rapprochement
  automatique par nom, aucune fiche GWS créée automatiquement). Aucun appel API, authentification,
  clé, écran de configuration, cache ou dépendance ajoutés pour l'une ou l'autre de ces pistes.
- Fichiers modifiés : `includes/cheval-pedigree.php` (référentiel Race/Stud-book, compatibilité
  ascendante, contexte de saisie, générations, arrêt à la génération 4),
  `includes/pedigree-resolver.php` (lecture de `race`/`race_autre` au lieu de `breed`, contrat de
  sortie du resolver inchangé), `includes/cheval-fields.php` (nouveau helper
  `gwseq_format_horse_name_display()`), `assets/cheval-admin.js` (bascule de la précision "Autre"
  pour la race d'un ascendant externe, à n'importe quelle profondeur, via une écoute déléguée
  unique).
- 49 nouvelles assertions dans `tests/gws-equestrian-pedigree-logic-test.php` (référentiel
  mutualisé, "Autre", compatibilité ascendante multi-générations façon Jamerose, contexte de
  saisie reproduisant l'exemple exact de la demande, repli sans nom, compteur de génération,
  arrêt strict à la génération 4 y compris si une donnée de génération 5 existe déjà en base) et
  9 nouvelles assertions dans `tests/gws-equestrian-cheval-logic-test.php` (helper de présentation
  des noms). Suite complète 100 % passante (563 assertions), aucune régression.

## 0.5.0 — Étape 5 : Pedigree — relations Père/Mère récursives, resolver, production

En attente de sa propre recette runtime. Construit le socle de filiation de la fiche Cheval, sans
toucher au rendu front (Étape 8) ni aux indices/médias/duplication (étapes suivantes).

- **Relations Père / Mère**, chacune indépendamment soit un cheval déjà présent dans GWS ("mode
  gws", référence par ID de post, jamais par nom), soit un ascendant hors GWS structuré ("mode
  external"). **Un ascendant externe n'est pas une simple feuille** : il peut lui-même avoir un
  père et une mère, également externes, jusqu'à la profondeur maximale du pedigree — un marchand
  ou un cavalier professionnel dont aucun ascendant n'est géré dans GWS peut ainsi saisir un
  pedigree complet sur 4 générations sans jamais créer la moindre fiche `gwseq_cheval`
  artificielle. Chaque niveau reste facultatif (l'utilisateur s'arrête où il veut). Aucun parent
  n'est jamais requis (un pedigree incomplet est parfaitement acceptable). Auto-référence directe
  rejetée à la sanitation ; un ID inexistant ou pointant vers un autre post type est également
  rejeté, jamais fait confiance côté serveur même si le `<select>` d'origine ne proposait que des
  chevaux valides.
- **Stockage de la branche externe** : un arbre récursif `{name, breed, father, mother}` encodé en
  JSON dans une seule meta (`_gwseq_pere_externe`/`_gwseq_mere_externe`), plutôt que des dizaines
  de meta à plat (`pere_pere_pere_nom`...). JSON plutôt que `serialize()` PHP : représentation
  lisible, indépendante du langage, plus simple à valider/faire évoluer/importer/projeter vers une
  future API. Bornée strictement à `GWSEQ_PEDIGREE_MAX_DEPTH - 1` niveaux supplémentaires sous le
  premier ascendant externe — une structure fournie plus profonde (formulaire trafiqué ou import
  malveillant) est silencieusement tronquée à la sanitation, jamais un moyen de contourner la
  limite serveur.
- **Aucune fiche créée pour un ascendant externe, aucune base globale d'ancêtres, aucune
  déduplication** : un même étalon externe (ex. « Kannan ») peut être ressaisi indépendamment dans
  plusieurs pedigrees sans lien entre les saisies — simplicité assumée pour la V1, un futur
  Network ou référentiel équin pourra améliorer cela.
- **Une seule branche active par relation, conservation non destructive lors d'un changement de
  mode** : passer de GWS à externe (ou l'inverse) ne touche JAMAIS les meta de l'autre branche —
  l'ancienne reste stockée mais inactive, récupérable si l'utilisateur revient en arrière. Le
  resolver ne lit jamais la branche inactive (aucun mélange possible). Le rattachement d'un
  ascendant externe à une vraie fiche GWS reste toujours une action explicite de l'utilisateur
  (choix dans le `<select>`), jamais une correspondance devinée automatiquement par nom.
- **Aucune duplication de données pour la branche GWS (§22)** : seule la relation (mode + ID) est
  stockée sur la fiche enfant. Nom, race, Global Horse ID ou pedigree du parent GWS ne sont jamais
  copiés — le resolver les récupère à la source à chaque résolution, donc un changement de
  nom/race/pedigree d'un parent GWS est automatiquement répercuté à tous ses descendants, sans
  aucune resynchronisation manuelle.
- **UX en divulgation progressive** (`includes/cheval-pedigree.php`) : Nom + Race toujours
  visibles pour un ascendant externe, puis — s'il reste de la profondeur disponible — un élément
  natif `<details>` (aucun JavaScript nécessaire pour ce repli/dépli, accessible par construction)
  révèle Père/Mère de cet ascendant. Un utilisateur qui ne connaît que le père et la mère ne
  remplit que le père et la mère ; un utilisateur avec un pedigree complet peut le renseigner sans
  jamais être confronté à un formulaire massif dès le premier écran.
- **Nouvelle règle architecturale appliquée à ce nouveau code (décidée après l'Étape 4)** :
  `gwseq_set_horse_parent($cheval_id, $role, $args)` est une fonction métier pure, jamais couplée
  à `$_POST` ni à un nonce/capability — réutilisable telle quelle par un futur importeur
  CSV/XLSX, une migration ou WP-CLI, y compris pour fournir une structure externe imbriquée sur
  plusieurs générations. Le formulaire admin (`gwseq_save_cheval_pedigree_meta()`) n'en est qu'UN
  client parmi d'autres : il ajoute uniquement les garde-fous de formulaire (nonce/capability/
  autosave/révision) puis délègue entièrement la persistance. Prestation/Cheval-identité/
  Commercialisation (Étapes 3-4) n'ont volontairement PAS été refactorées à cette occasion (voir
  le mini-audit de la version 0.4.1) — seul le nouveau code applique la règle.
- **Resolver** (`includes/pedigree-resolver.php`, nouveau) : produit une structure de données
  déterministe (jamais de HTML), réutilisable plus tard par le rendu web (Étape 8), un export
  PDF/catalogue, ou une projection API — aucune donnée privée exposée par défaut (seuls
  id/global_id [uniquement pour une fiche GWS]/nom/race/filiation apparaissent, jamais statut
  commercial, prix, éleveur, propriétaire, UELN/SIRE, catégories). Traite les branches GWS et
  externes avec exactement la même logique de comptage de génération, un mélange des deux dans un
  même pedigree (ex. une fiche GWS dont un ascendant intermédiaire a lui-même un parent externe)
  ne crée donc aucune ambiguïté de profondeur. Profondeur par défaut : 4 générations d'ascendants
  au-delà de la fiche elle-même (parents/grands-parents/arrière-grands-parents/
  arrière-arrière-grands-parents, 30 nœuds maximum), au-delà desquelles un nœud sentinelle
  `depth_limit` remplace une relation réelle plutôt que de la confondre avec une absence de
  donnée — y compris si une donnée corrompue en base contenait davantage de générations que la
  limite autorisée (le resolver borne sa propre récursion indépendamment de ce qui est stocké).
  Protection contre les cycles directs (déjà rejetés à la sauvegarde pour une relation GWS,
  contrôle redondant au resolver par défense en profondeur) et indirects (impossibles à empêcher
  à la sauvegarde, détectés et interrompus proprement à la résolution, sans boucle infinie ni
  erreur fatale) — une branche externe ne peut jamais former de cycle, elle n'est composée que de
  texte structuré. Mémoïsation locale à un seul appel de résolution (clé = identifiant + profondeur
  restante, pas seulement l'identifiant, pour rester correcte face à un même ascendant GWS croisé
  à deux profondeurs différentes) — aucun cache persistant construit en V1.
- **Suppression d'un parent référencé (§23)** : mise à la corbeille — résolution inchangée, les
  données du parent restent intactes. Suppression définitive — dégradation propre (nœud
  `unavailable`), jamais d'erreur fatale, jamais de casse du reste de la fiche. Aucun hook de
  nettoyage automatique n'a été installé sur la suppression d'un cheval : cela reviendrait à
  modifier automatiquement d'autres fiches suite à une action sur celle-ci, ce que la règle "pas
  de destruction de données" interdit depuis l'Étape 4.
- **Production** (`gwseq_get_horse_offspring()`, inchangée par le correctif) : descendants
  calculés à la volée par requête inverse (jamais une liste stockée sur la fiche du parent), et
  uniquement pour de vraies relations entre deux fiches `gwseq_cheval` — un ascendant externe,
  même ressaisi à l'identique dans plusieurs pedigrees, n'est jamais rapproché ni compté comme une
  production quelconque. Affichée en meta box uniquement si au moins un descendant existe (absence
  de donnée = absence d'affichage, y compris pour une meta box entière).
- **Aperçu de résolution du pedigree** : boîte de vérification en lecture seule, réservée aux
  environnements local/développement (même garde que le Global Horse ID) — simple liste imbriquée
  sans style, rendant désormais correctement les branches externes multi-générations (et non plus
  seulement leur premier niveau), explicitement documentée comme un outil de vérification et non
  le futur rendu public de l'Étape 8.
- Fichiers créés : `includes/pedigree-resolver.php`, `includes/cheval-pedigree.php`.
- Fichiers modifiés : `module.php` (chargement des deux nouveaux fichiers, version) ;
  `assets/cheval-admin.js` (affichage conditionnel de la source Père/Mère, léger, sans erreur si
  JavaScript est indisponible — le serveur reste seul autoritaire).
- 100 nouvelles assertions dans le nouveau fichier `tests/gws-equestrian-pedigree-logic-test.php`
  (relations pures, sanitation récursive d'un arbre externe avec troncature de profondeur,
  persistance et changement de mode sans mélange des branches, chemin programmatique sans
  `$_POST`/nonce pour une structure externe imbriquée, resolver — ascendant externe simple/avec
  parents externes/branche complète/pedigree entièrement externe/mélange GWS+externe/profondeur
  maximale/donnée corrompue en base bornée quand même, cycles direct/indirect, parent supprimé,
  mise à jour répercutée automatiquement, mémoïsation d'un ascendant GWS croisé deux fois —,
  production sans déduplication des externes, sécurité de la sauvegarde y compris sur une
  structure externe profonde soumise via un vrai `$_POST`, escaping admin, divulgation progressive
  via `<details>`, internationalisation). Suite existante (405 assertions) toujours 100 % passante
  — aucune régression sur les étapes précédentes.

## 0.4.1 — Étape 4 : micro-correction post-recette (présentation de l'âge) et gel

La recette runtime de l'Étape 4 (0.4.0) est concluante. Une seule micro-correction demandée
avant gel définitif :

- **Présentation de l'âge** : le calcul (`année courante - année de naissance`,
  `gwseq_cheval_age_from_birth_year()`) était déjà correct — c'est la convention métier équine
  elle-même (un cheval prend un an de plus au 1er janvier), pas une approximation à corriger, et
  reste inchangé. Seule sa présentation change : nouvelle fonction pure
  `gwseq_cheval_age_label()` produisant « 1 an »/« 7 ans » (accord singulier/pluriel via `_n()`,
  text domain `gws-core`), en lieu et place de « ≈ 7 an(s) (âge calendaire approximatif, jamais
  au jour près) ». Une aide discrète (« Âge calculé automatiquement à partir de l'année de
  naissance selon la convention équine. ») reste disponible en attribut `title` (infobulle),
  sans texte permanent visible.
- **Mini-audit Import/Onboarding** (nouveau besoin produit confirmé pour une future version,
  aucun développement à ce stade) : analyse de Groupe tarifaire / Prestation / Cheval pour
  détecter une logique métier essentielle couplée à `$_POST`/`save_post` qu'un futur importeur
  devrait dupliquer. Résultat : Groupe tarifaire n'a aucun couplage (champs 100 % natifs) ; pour
  Prestation et Cheval, la sanitation est déjà pure et réutilisable, mais la persistance (mapping
  valeur sanitizée → clé de meta) vit aujourd'hui dans la même fonction que les garde-fous de
  sécurité liés au formulaire admin, laquelle lit `$_POST` directement. Une factorisation
  minimale (séparer persistance et garde-fous de sécurité, sans nouvelle abstraction générique)
  est **proposée mais volontairement non implémentée dans cette version**, pour ne pas modifier
  des étapes déjà validées en anticipation d'une fonctionnalité qui n'existe pas encore. Le
  Global Horse ID est déjà conforme aux règles d'un futur import sans aucune modification
  nécessaire. Voir `README.md` de ce dossier pour le détail complet de l'audit.
- Fichier modifié : `includes/cheval-fields.php` uniquement (présentation de l'âge — aucun
  changement de comportement ailleurs).
- 11 nouvelles assertions dans `tests/gws-equestrian-cheval-logic-test.php` (136 au total pour ce
  fichier ; 404 au total sur l'ensemble de la suite). Aucune régression.
- **Étape 4 gelée à l'issue de cette version.** Étape 5 (Pedigree) toujours non commencée ; ne
  seront pas non plus développés à ce stade : galerie photos/vidéos (Étape 6), Import/Onboarding,
  guide utilisateur, module Équipe/Membres — tous confirmés pour la roadmap, voir `README.md`.

## 0.4.0 — Étape 4 : Cheval — socle métier, catégories, commercialisation, Global Horse ID

Construction du socle métier réel de la fiche cheval, en attente de sa propre recette runtime.

- **Identité** (meta box « Identité ») : Sexe (Mâle/Femelle/Hongre, valeurs techniques stables
  `male`/`female`/`gelding`), Année de naissance (entier 4 chiffres, bornes 1900 → année courante
  + 1 documentées), Robe et Race/Stud-book (listes pratiques non exhaustives + « Autre » avec
  précision libre, valeurs techniques stables), Taille en centimètres (entier, jamais la notation
  « 1m68 »), Éleveur et Propriétaire (texte simple), UELN et numéro SIRE (texte simple, aucune
  validation de format ni appel à une API distante — voir « Limitations connues » ci-dessous).
  Âge calculé à la volée depuis l'année de naissance (`gwseq_cheval_age_from_birth_year()`),
  jamais stocké — approximatif (calendaire), jamais au jour près.
- **Aucune meta parallèle au natif** : Nom = `post_title`, Photo principale = image à la une
  native (labels ré-étiquetés « Photo principale »/« Définir la photo principale »/« Supprimer la
  photo principale »/« Utiliser comme photo principale », aucun champ dédié créé), Ordre =
  `menu_order` natif (support `page-attributes` ajouté, meta box renommée comme pour
  Prestation/Groupe). Support `editor` retiré : aucun contenu éditorial n'est développé à cette
  étape (blocs génériques prévus à l'Étape 6).
- **Catégories de chevaux enfin utilisables** : interface à cases à cocher native
  (`meta_box_cb => 'post_categories_meta_box'`, le même rendu que la boîte « Catégories » des
  articles, aucun code de rendu personnalisé), un cheval peut appartenir à plusieurs catégories.
  Affordance de création rapide de catégorie masquée directement sur la fiche cheval (règle CSS
  ciblant l'identifiant natif du bloc, chargée uniquement sur cet écran) pour éviter les doublons
  quasi identiques — la création/gestion des catégories reste possible depuis
  Chevaux → Catégories.
- **Commercialisation** (meta box dédiée), structurée et indépendante des catégories : Statut
  commercial (`not_offered`/`for_sale`/`reserved`/`sold`), Mode de prix (Prix fixe / Fourchette /
  Sur demande), montant(s) correspondants, Libellé affiché personnalisable pour le mode « Sur
  demande » (même mécanisme « jamais initialisé » vs « volontairement vidé » que la Prestation,
  via `metadata_exists()`). `0` reste une vraie valeur de prix, jamais confondue avec une absence
  de prix. Devise globale de l'Étape 3 réutilisée telle quelle (`gwseq_settings['currency']`),
  aucun second réglage propre au cheval. Le prix reste toujours visible/enregistré quel que soit
  le statut choisi — un texte d'aide explicite rappelle qu'un futur rendu public respectera le
  statut, sans qu'aucune donnée ne soit jamais effacée par un changement de statut.
- **Global Horse ID** (`_gwseq_global_id`) : UUID v4 (`wp_generate_uuid4()`) assigné une seule
  fois, au premier enregistrement réel de la fiche (jamais sur un auto-draft, une autosave ou une
  révision), jamais régénéré ensuite — indépendant du nom, du slug, de l'URL, du domaine et du
  thème. Jamais exposé en REST (`show_in_rest => false`), jamais saisissable depuis un formulaire,
  jamais réutilisé comme secret ou jeton d'accès. Boîte de vérification en lecture seule
  disponible uniquement en environnement local/développement
  (`wp_get_environment_type()` — jamais enregistrée du tout hors de ces environnements, pas
  seulement masquée visuellement) pour permettre sa vérification pendant la recette sans jamais
  l'exposer à un utilisateur de production.
- **Éditeur par blocs désactivé pour ce post type** (`includes/cheval-editor.php`), avec un
  arbitrage propre à Cheval et non un copier-coller de celui de Prestation : la fiche est
  désormais 100 % structurée (plus de support `editor`), sans mécanisme de préremplissage par
  modèle à faire fonctionner — l'éditeur classique offre simplement un écran de meta boxes plus
  lisible et prévisible pour une fiche métier sans contenu éditorial à cette étape.
- **Colonnes d'administration** : Catégories, Statut commercial, Prix (résumé texte réutilisable),
  Ordre.
- **UELN / SIRE — point analysé, retenu avec une limitation documentée** : les deux champs sont
  de simples identifiants texte, sans validation de format, sans appel SIRE/IFCE, sans
  déduplication. Point d'ambiguïté explicitement signalé : SIRE est un identifiant propre au
  système français, UELN un identifiant international — le module n'a aujourd'hui aucun réglage
  de pays/locale distinguant les deux, ce qui est cohérent avec un produit actuellement pensé pour
  des professionnels francophones mais pourrait devenir ambigu si le module s'internationalise un
  jour. Aucune décision d'architecture n'a été prise qui empêcherait de clarifier ce point plus
  tard (les deux champs restent de simples chaînes indépendantes l'une de l'autre).
- **HT/TTC — non traité pour le prix du cheval (limitation assumée)** : contrairement à la
  tarification des Prestations, aucune mention HT/TTC n'est appliquée au prix commercial d'un
  cheval — construire un moteur fiscal spécifique n'a pas semblé justifié pour ce socle ; ce point
  reste ouvert si un besoin réel apparaît en recette ou plus tard.
- Fichiers créés : `includes/cheval-fields.php`, `includes/cheval-editor.php`,
  `includes/cheval-categories.php`, `assets/cheval-admin.js`.
- Fichiers modifiés : `includes/post-types.php` (Cheval : support `editor` retiré,
  `page-attributes` ajouté, labels Photo principale), `includes/taxonomies.php` (`meta_box_cb`),
  `includes/admin-ui.php` (Cheval ajouté au tri par défaut et au renommage de la meta box Ordre),
  `module.php` (chargement des trois nouveaux fichiers, version) ;
  `includes/settings.php` (relocalisation de `gwseq_format_price_number()`, helper de formatage
  désormais partagé entre Prestation et Cheval plutôt que propre à la Prestation — aucun
  changement de comportement).
- 125 nouvelles assertions dans le nouveau fichier `tests/gws-equestrian-cheval-logic-test.php`
  (identité, catégories, commercialisation, Global Horse ID — génération réelle, idempotence,
  bornes de sécurité —, meta boxes réellement rendues, arbitrage Gutenberg, colonnes
  d'administration, sécurité de la sauvegarde via le chemin réel `$_POST`, internationalisation).
  Suite existante (268 assertions réparties sur les sept autres fichiers de `tests/`) toujours
  100 % passante.

## 0.3.3 — Étape 3 : corrections post-recette runtime (presets, UX, i18n)

Trois corrections suite au CR de recette runtime de GWS Core 1.6.2 / GWS Equestrian 0.3.2 :

- **Cause racine et correction du bug des modèles de prestations (bloquant).** Le CPT
  `gwseq_prestation` a `show_in_rest => true` depuis l'Étape 1, donc WordPress lui applique
  l'éditeur par blocs par défaut. Le sélecteur de modèle (`includes/presets.php`) s'accroche au
  hook `edit_form_after_title`, propre au gabarit d'édition CLASSIQUE
  (`wp-admin/edit-form-advanced.php`) : cette action n'est jamais déclenchée par le gabarit de
  l'éditeur par blocs (`edit-form-blocks.php`), d'où l'absence totale et silencieuse du bloc
  « Partir d'un modèle » en recette. Les meta boxes classiques (Groupe tarifaire, Tarification)
  fonctionnaient malgré tout grâce à la compatibilité descendante de `add_meta_box()` dans
  l'éditeur par blocs — compatibilité qui ne s'étend pas aux actions du gabarit classique.
  Correction : nouveau fichier `includes/prestation-editor.php`, qui désactive l'éditeur par
  blocs pour ce seul post type via le filtre natif `use_block_editor_for_post_type`, ce qui
  restaure le déclenchement réel de `edit_form_after_title`. `show_in_rest` reste activé (les
  deux réglages sont indépendants). Aucun second mécanisme d'affichage ajouté.
- **UX Nom/Description de la fiche Prestation.** `post_title`/`post_content` restent les seules
  sources de vérité (aucune meta dupliquée) ; le retour à l'éditeur classique (ci-dessus) élimine
  la confusion visuelle signalée en recette (bloc pouvant remonter au-dessus du titre) puisque le
  gabarit classique place le titre en premier, de façon prévisible. Espace réservé du titre
  personnalisé (« Nom de la prestation » au lieu du texte générique WordPress) et libellé
  « Description » injecté juste au-dessus de l'éditeur natif — uniquement des ré-étiquetages au
  rendu, aucune donnée ajoutée. Arbitrage explicite : l'éditeur classique (TinyMCE) est conservé
  sans restriction de sa barre d'outils (couvre déjà largement le besoin réel — texte, gras,
  italique, listes, liens — sans permettre de construire un layout, contrairement à l'éditeur par
  blocs ; une personnalisation plus fine serait une customisation fragile d'un composant natif
  pour un bénéfice marginal, non retenue).
- **Internationalisation.** Toutes les chaînes d'interface du module (labels de CPT/taxonomie,
  options de tarification/unités/devise/affichage des prix, meta boxes, résumé de tarif, modèles
  de prestations, composant répétable) passent désormais par les fonctions de traduction
  WordPress (`__()`, `esc_html__()`, `esc_attr__()`, `esc_html_e()`, `esc_attr_e()`) avec le text
  domain unique `gws-core` (cœur et modules métier partagent le même domaine — ce sont des
  sous-fonctionnalités d'un seul plugin). `gws-core.php` charge désormais les traductions
  (`load_plugin_textdomain()`, en-tête `Domain Path: /languages`, voir
  `wp-content/plugins/gws-core/languages/README.md`). Correction du bug signalé (`£ HT`) : le
  suffixe HT/TTC est une chaîne traduisible indépendante de la devise — une devise ne détermine
  jamais une langue et réciproquement ; les valeurs techniques stockées (`ht`, `ttc`) restent
  inchangées. Les identifiants de modèles de prestations (`includes/presets.php`) ont été
  restructurés en identifiants techniques stables non traduits (ex. `pension_pre_avec_infra`),
  distincts de leur libellé affiché désormais traductible — évite qu'un identifiant d'URL dépende
  d'un texte traduit. Le contenu saisi par le professionnel (noms, descriptions, groupes,
  libellés personnalisés) n'est jamais passé dans une fonction de traduction. Le module QA
  (`includes/qa-repeater.php`, jamais actif en production) n'a délibérément pas été
  internationalisé : outil de développement jetable, jamais vu par un utilisateur réel.
- Fichiers créés : `includes/prestation-editor.php`,
  `wp-content/plugins/gws-core/languages/README.md`.
- Fichiers modifiés : `includes/post-types.php`, `includes/taxonomies.php`, `includes/admin-ui.php`,
  `includes/groupe-admin.php`, `includes/settings.php`, `includes/prestation-fields.php`,
  `includes/presets.php`, `includes/repeater-field.php` (i18n uniquement, aucune logique
  changée) ; `wp-content/plugins/gws-core/gws-core.php` (chargement des traductions — changement
  du cœur explicitement justifié : prérequis technique direct et nécessaire pour que les appels
  `__()` du module produisent un jour un effet réel).
- 37 nouvelles assertions dans le nouveau fichier `tests/gws-equestrian-prestation-editor-test.php`,
  dont un test du comportement réel du filtre `use_block_editor_for_post_type` (pas seulement sa
  présence), du HTML effectivement rendu par le sélecteur de modèle, et du text domain utilisé
  par chaque appel de traduction rencontré. Les assertions existantes de
  `gws-equestrian-prestations-logic-test.php` portant sur les presets ont été mises à jour pour
  utiliser les nouveaux identifiants techniques stables (même couverture, pas de régression).

## 0.3.2 — Étape 3 : "Sur devis" devient fonctionnellement "Sur demande"

Dernier ajustement fonctionnel avant recette runtime :

- **Le mode `devis` est présenté à l'utilisateur comme « Sur demande »** (valeur technique
  `devis` conservée telle quelle, aucune migration nécessaire) : ce mode représente désormais
  « prix sur demande / non communiqué » au sens large, pas spécifiquement un devis. Nouveau
  champ **Libellé affiché** (`_gwseq_tarif_demande_libelle`, texte simple sanitizé) permettant au
  professionnel de choisir sa propre formulation (« Sur demande », « Sur devis », « Nous
  contacter »...) ou de ne rien afficher.
- **Distinction « jamais initialisé » vs « volontairement vide »** : même mécanisme que pour
  `_gwseq_tarif_prix_public` (`metadata_exists()`). Tant qu'aucun enregistrement n'a jamais écrit
  cette meta — prestation neuve, ou prestation créée avant ce champ (compatibilité 1.6.1 sans
  aucune migration) — le libellé par défaut « Sur demande » s'applique. Dès le premier
  enregistrement du formulaire, la valeur réellement soumise fait foi, y compris vide (aucun
  texte tarifaire n'est alors affiché).
- **Indépendant du réglage global « Prix masqués »** : ce libellé n'est jamais un montant chiffré
  à masquer — il reste rendu (ou vide s'il a été volontairement effacé) quel que soit le réglage
  global d'affichage des prix, conformément à l'arbitrage déjà posé en 1.6.1.
- Aucun prix numérique requis pour ce mode, `0` jamais utilisé pour représenter l'absence de
  prix — comportement inchangé, confirmé par test.
- Aucun moteur de champs conditionnels ajouté : le nouveau champ réutilise exactement le même
  mécanisme d'affichage conditionnel déjà en place (`data-gwseq-tarif-fields`), sans modification
  du JavaScript existant.
- Fichier modifié : `includes/prestation-fields.php` uniquement.
- 12 nouvelles assertions dans `tests/gws-equestrian-prestations-logic-test.php` (86 au total
  pour ce fichier).

## 0.3.1 — Étape 3 : ajustements avant recette runtime

Trois corrections demandées après relecture du code, avant toute recette runtime :

- **Réglage global d'affichage des prix étendu à trois modes** : TTC / HT / **Prix masqués**
  (`gwseq_settings['price_display_mode']` accepte désormais `ttc`, `ht` ou `hidden`). En mode
  masqué, aucun tarif n'est jamais rendu publiquement, quelle que soit la case individuelle
  « Afficher ce tarif publiquement » d'une prestation — priorité : masque global > masque
  individuel > rendu normal. « Sur devis » reste toujours affiché tel quel (ce n'est pas un prix
  masqué, c'est l'absence de tarif fixe). Aucun montant stocké n'est jamais supprimé ni modifié
  par ce réglage : uniquement une règle de présentation, réversible à tout moment.
- **Réglage de devise** (`gwseq_settings['currency']`, EUR par défaut ; GBP/USD/CHF disponibles)
  avec mapping local code ISO 4217 → symbole (`gwseq_currency_symbol()`), sans bibliothèque
  externe, sans taux de change, sans calcul. `gwseq_prestation_price_summary()` ne code plus
  jamais `€` en dur (vérifié par un test lisant directement le code source de la fonction) et
  accepte désormais un troisième paramètre `$currency_code`.
- **Presets reproduction corrigés** : Congélation semence → paillette (au lieu de dose),
  Réfrigération → récolte (nouveau), Préparation doses réfrigérées → dose (confirmé inchangé),
  Expédition → colis (nouveau), Spermogramme → étalon (nouveau). Trois nouvelles unités
  standards ajoutées à la liste fermée : récolte, colis, étalon (+ « Autre » toujours disponible
  pour le reste). Les autres presets existants ont été relus : aucune autre unité suggérée jugée
  contradictoire avec son libellé.
- Fichiers modifiés : `includes/settings.php` (réécrit), `includes/prestation-fields.php`
  (résumé de prix, unités), `includes/presets.php` (unités suggérées).
- 31 nouvelles assertions dans `tests/gws-equestrian-prestations-logic-test.php` (74 au total
  pour ce fichier).

## 0.3.0 — Étape 3 : Prestations / Groupes tarifaires

- **Groupe tarifaire réellement utilisable** : Nom (`post_title`), Ordre (`menu_order` natif via
  le support `page-attributes`, meta box renommée « Ordre d'affichage »), Description courte
  (`post_excerpt` natif via le support `excerpt`, meta box renommée « Description courte ») —
  trois champs natifs WordPress réutilisés tels quels, aucune meta custom ni sauvegarde à écrire
  pour ces trois champs. Liste d'administration enrichie de deux colonnes : nombre de prestations
  rattachées, ordre.
- **Relation Prestation → Groupe tarifaire** par référence stable (ID de post, jamais par nom) :
  un groupe peut être renommé sans jamais casser les prestations qui lui sont rattachées.
- **Tarification** : mode Prix unique / Cheval-Poney (deux prix distincts pour une même
  prestation) / Sur devis (aucun prix chiffré requis, `0` n'est jamais utilisé pour signifier
  « sur devis ») ; unité parmi une liste fermée (séance, heure, jour, semaine, mois, forfait,
  chaleur, saison, dose, paillette, autre + libellé personnalisé) ; case « Afficher ce tarif
  publiquement » (affichée/masquée par prestation, indépendante du mode) permettant un prix
  interne non publié sans multiplier les états incohérents. Affichage conditionnel des champs
  selon le mode/l'unité choisis (JavaScript natif local, pas de moteur de champs conditionnels).
- **Réglage global HT/TTC** propre au module (`gwseq_settings`, indépendant des réglages
  génériques de gws-core), écran dédié sous Prestations > Réglages. Aucun calcul de TVA : indique
  uniquement la nature des montants déjà saisis.
- **Statut de la prestation** : statuts natifs WordPress (Brouillon/Publié) uniquement — aucun
  second système « Actif/Inactif » créé en parallèle.
- **Modèles de prestations** (aide à la création, jamais une donnée persistante) : familles
  Pension / Travail / Cours / Élevage / Reproduction / Autres, sélecteur sur l'écran « Ajouter une
  prestation », préremplissage du titre (et de l'unité suggérée le cas échéant) au rendu
  uniquement — aucune création automatique de contenu, aucune relation conservée après
  l'enregistrement : une prestation créée depuis un modèle est immédiatement une prestation
  ordinaire, modifiable et supprimable librement, jamais réécrite par une future mise à jour des
  modèles.
- Ordre par défaut des listes d'administration Prestations/Groupes basé sur `menu_order`. Choix
  assumé : pas de glisser-déposer en V1 (champ numérique natif uniquement) — priorité à la
  robustesse, conformément à la demande.
- Fichiers ajoutés : `includes/admin-ui.php`, `includes/groupe-admin.php`,
  `includes/prestation-fields.php`, `includes/presets.php`, `includes/settings.php`,
  `assets/prestation-admin.js`, `assets/presets-admin.js`. `includes/post-types.php` complété
  (supports `page-attributes`/`excerpt`), sans modification des post types/taxonomie eux-mêmes.
- Tests ajoutés (`tests/gws-equestrian-prestations-logic-test.php`, 43 assertions) : sanitation de
  la tarification depuis une forme `$_POST` réelle, relation par ID résistant au renommage,
  résumé de prix, presets, sécurité de sauvegarde (nonce/capability/autosave/révision).

## 0.2.1 — Étape 2 : corrections suite à recette runtime

Deux anomalies bloquantes révélées par la première recette réelle sous WordPress Local (Étape 2
non validée avant correction) :

- **Perte de structure des lignes (bloquante).** Le nommage HTML des champs
  (`{meta_key}[][colonne]`, un index vide partagé en apparence par toutes les colonnes d'une
  ligne) ne produisait en réalité PAS ce regroupement : PHP alloue un nouvel index à chaque nom
  de champ distinct rencontré (`[][libelle]`, `[][valeur]`, `[][annee]` sont trois noms
  différents), pas à chaque ligne visuelle — une ligne de 3 colonnes était donc réceptionnée
  comme 3 lignes d'une seule colonne chacune. Corrigé en donnant à chaque ligne un index
  explicite partagé par toutes ses colonnes (`{meta_key}[0][colonne]`, `{meta_key}[1][colonne]`,
  ...) : les lignes déjà enregistrées reçoivent leur position réelle au rendu ; le gabarit JS
  utilise un jeton `__INDEX__` remplacé par un compteur strictement croissant (jamais réutilisé,
  même après suppression d'une ligne) porté par un attribut `data-gwseq-next-index` sur le
  conteneur.
- **`number` limité aux entiers dans le navigateur.** L'attribut `step` était omis pour ce type,
  or le pas par défaut d'un `<input type="number">` sans `step` explicite est `1` : un nombre
  décimal (ex. `125.5`) était refusé côté navigateur avant même d'atteindre la sanitation
  serveur (déjà correcte). Corrigé en ajoutant `step="any"` pour `number` (décimales autorisées)
  tout en conservant `step="1"` pour `integer` (entiers uniquement).
- Fichiers modifiés : `includes/repeater-field.php` (rendu des lignes et de la meta box),
  `assets/repeater-field.js` (calcul de l'index à l'ajout d'une ligne).
- Tests ajoutés (`tests/gws-equestrian-repeater-logic-test.php`) : reproduction exacte des deux
  anomalies via `parse_str()` sur le markup HTML réellement généré (pas seulement sur un tableau
  PHP déjà bien formé), bout en bout jusqu'à la sanitation finale, vérification des attributs
  `step` par type.

## 0.2.0 — Étape 2 : Composant répétable

- Nouveau composant interne (`includes/repeater-field.php`) : liste ordonnée de lignes
  structurées, types `text`/`textarea`/`number`/`integer`/`url`, stockage en une seule meta
  WordPress (tableau de lignes), sanitization par type, aucune ligne vide stockée, valeur `0`
  jamais confondue avec une ligne vide, aucune dépendance à ACF. Volontairement pas un
  générateur de champs universel — voir l'en-tête du fichier pour le détail et les limitations
  assumées.
- Démonstration neutre en environnement local/développement uniquement
  (`includes/qa-repeater.php`, CPT `gwseq_qa_repeater` non public) : jamais mêlée aux écrans
  métier réels ni au module `qa` générique de gws-core.
- Assets dédiés (`assets/repeater-field.js`, `assets/repeater-field.css`), JavaScript natif sans
  dépendance, chargés uniquement sur l'écran d'édition du CPT de démonstration.
- Aucune modification de l'Étape 1 (post types, taxonomie inchangés).
- Aucune modification de GWS Core ou GWS Starter.

## 0.1.0 — Étape 1 : Fondations

- Structure du module (`gws-core/modules/gws-equestrian/` + pendant thème), préfixe `gwseq_`.
- Trois Custom Post Types : `gwseq_prestation`, `gwseq_groupe` (Groupe tarifaire, jamais public),
  `gwseq_cheval`.
- Une taxonomie : `gwseq_categorie_cheval` (interface WordPress native pour l'instant).
- Activation/désactivation via `config/modules.php`, mécanisme du cœur inchangé.
- Aucun champ, aucune relation, aucun rendu front à ce stade.

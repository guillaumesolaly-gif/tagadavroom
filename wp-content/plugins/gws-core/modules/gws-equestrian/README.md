# GWS Equestrian (gws-core)

Module métier pour les professionnels du monde équestre (pension, enseignement, élevage,
reproduction, vente) : gestion des prestations/tarifs et des fiches chevaux. Voir le pendant
présentation dans `wp-content/themes/gws-starter/modules/gws-equestrian/`.

**Préfixe du module : `gwseq_`** (jamais `gws_` ni `gws_core_`, réservés au cœur — voir
`modules/README.md` et `AI-AGENT.md` §3). Consigné dans le registre de `modules/README.md`.

## État actuel : Étape 4 — Cheval (Étapes 1, 2 et 3 validées)

Les Étapes 1 (fondations), 2 (composant répétable) et 3 (Prestations/Groupes tarifaires) ont été
recettées en conditions réelles et validées — gel à GWS Core 1.6.3 / GWS Equestrian 0.3.3.
L'Étape 4 construit le socle métier réel de la fiche Cheval (identité, catégories,
commercialisation, Global Horse ID), en attente de sa propre recette runtime. Le pedigree
(Étape 5) n'a pas commencé. Voir `CHANGELOG.md` de ce dossier pour l'historique détaillé par
étape, et la proposition de conception validée pour le contexte d'ensemble.

### Cheval (Étape 4)

**Identité** (meta box « Identité ») : Sexe (Mâle/Femelle/Hongre), Année de naissance (âge
calculé à la volée, jamais stocké, toujours approximatif — calendaire, jamais au jour près),
Robe et Race/Stud-book (listes pratiques + « Autre » avec précision libre — aucune logique du
module ne dépend d'un nom de stud-book précis), Taille en centimètres, Éleveur, Propriétaire,
UELN et numéro SIRE (texte simple, sans validation de format ni service distant — voir
« Limitations connues » plus bas). Nom = `post_title`, Photo principale = image à la une native
(ré-étiquetée, aucun champ dédié) : aucune meta ne duplique jamais ces deux sources de vérité.

**Catégories de chevaux** : interface à cases à cocher native (`post_categories_meta_box`, un
cheval peut appartenir à plusieurs catégories), avec l'affordance de création rapide masquée
directement sur la fiche pour éviter les doublons quasi identiques (« Chevaux à vendre » /
« Chevaux a vendre » / « A vendre »...) — la création et la gestion des catégories elles-mêmes
restent possibles depuis **Chevaux → Catégories**, sans aucune restriction.

**Commercialisation** (meta box dédiée), volontairement indépendante des catégories : une
catégorie éditoriale « Chevaux à vendre » n'implique jamais un statut commercial, et
réciproquement. Statut (Non proposé/À vendre/Réservé/Vendu), Mode de prix (Prix fixe/Fourchette/
Sur demande avec libellé personnalisable), devise globale de l'Étape 3 réutilisée telle quelle.
Le prix reste toujours visible et enregistré quel que soit le statut choisi — rien n'est jamais
effacé par un changement de statut, un texte d'aide rappelle qu'un futur rendu public respectera
ce statut.

**Global Horse ID** (`_gwseq_global_id`) : identifiant technique de la fiche (UUID v4), assigné
une seule fois au premier enregistrement réel, jamais régénéré, indépendant du nom/slug/URL/
domaine/thème. Ce n'est ni un identifiant biologique de l'animal (deux fiches indépendantes du
même cheval réel n'auront jamais automatiquement le même identifiant), ni un secret. Jamais
exposé en REST, jamais saisissable par un utilisateur. Une boîte de vérification en lecture seule
existe uniquement en environnement local/développement, pour permettre sa vérification pendant la
recette sans jamais l'exposer à un utilisateur de production.

**Éditeur par blocs désactivé pour ce post type** (`includes/cheval-editor.php`) : arbitrage
propre à Cheval, distinct de celui de Prestation (Étape 3) — la fiche est désormais entièrement
structurée (support `editor` retiré, aucun contenu éditorial à cette étape), sans mécanisme de
préremplissage par modèle à faire fonctionner ; l'éditeur classique offre un écran de meta boxes
plus lisible et prévisible pour une fiche métier à ce stade.

#### Limitations connues (Étape 4)

- **UELN / SIRE** : simples champs texte, sans validation de format, sans appel à un service
  distant (SIRE/IFCE), sans déduplication. SIRE est un identifiant propre au système français,
  UELN un identifiant international — le module n'a aujourd'hui aucun réglage de pays/locale
  distinguant les deux, cohérent avec un produit actuellement pensé pour des professionnels
  francophones, mais un point à garder en tête si une internationalisation est envisagée plus
  tard. Les deux champs restent des chaînes indépendantes, aucune décision actuelle ne bloquerait
  une clarification future.
- **Aucune mention HT/TTC pour le prix d'un cheval** : contrairement à la tarification des
  Prestations, le prix commercial d'un cheval n'affiche aujourd'hui ni HT ni TTC — construire un
  moteur fiscal dédié n'a pas semblé justifié pour ce socle. Point ouvert si un besoin réel
  apparaît en recette.

#### Procédure de recette — Étape 4

À réaliser dans WordPress Local, sans écrire de code :

1. Menu **Chevaux** > Ajouter : vérifier que le champ titre affiche l'espace réservé « Nom du
   cheval », qu'aucun éditeur par blocs n'apparaît (écran classique avec meta boxes), et que les
   sections **Identité**, **Commercialisation**, **Catégories de chevaux** et **Ordre
   d'affichage** sont toutes présentes et lisibles.
2. Renseigner Sexe (Hongre), Année de naissance (une année plausible, ex. `2018`) — vérifier que
   l'âge approximatif calculé s'affiche à côté du champ. Renseigner Robe = Autre avec une
   précision libre (ex. « Aubère truité »), Race/Stud-book = un stud-book de la liste, Taille
   `168`, Éleveur et Propriétaire. Ajouter une image à la une : vérifier que son libellé affiche
   bien « Photo principale » (pas le texte natif WordPress).
3. Cocher deux catégories existantes (en créer au moins deux au préalable depuis
   **Chevaux → Catégories** si besoin) : vérifier l'affichage en cases à cocher, l'absence de
   toute affordance « + Ajouter une nouvelle catégorie » sur cette fiche, et que les deux
   catégories sont bien conservées après enregistrement/rechargement.
4. Statut commercial = « À vendre », Mode de prix = « Prix fixe », Prix `25000`. Publier,
   recharger : vérifier la colonne Prix de la liste des Chevaux (« 25 000 € »), la colonne Statut
   commercial, et la colonne Catégories.
5. Modifier cette fiche : passer en Mode de prix « Fourchette » (`20000` → `30000`), enregistrer,
   recharger — vérifier la restitution exacte des deux bornes et la colonne Prix.
6. Passer en Mode de prix « Sur demande » : vérifier que le champ Libellé affiché apparaît
   pré-rempli avec « Prix sur demande », le modifier en « Nous contacter », enregistrer,
   recharger — la colonne Prix doit refléter le nouveau libellé. Le vider complètement : la
   colonne Prix ne doit plus afficher aucun texte (tiret).
7. Repasser le Statut commercial à « Non proposé » sans modifier le prix : vérifier que le prix
   reste bien enregistré (rien n'est perdu) et que le texte d'aide sous le prix est visible.
8. Renommer le cheval (changer le titre) et enregistrer : vérifier que rien d'autre (catégories,
   commercialisation, identité) n'est affecté.
9. **Global Horse ID** : sur un environnement de type `local` ou `development`
   (`wp_get_environment_type()`), vérifier qu'une boîte « Identifiant technique » apparaît en
   colonne latérale avec un identifiant au format UUID, en lecture seule. Enregistrer la fiche à
   nouveau (sans rien changer) : vérifier que l'identifiant reste strictement identique. Renommer
   le cheval une nouvelle fois : vérifier que l'identifiant ne change toujours pas. Vérifier
   qu'aucune trace de cet identifiant n'apparaît dans une réponse de l'API REP WordPress
   (`/wp-json/wp/v2/gwseq_cheval/<id>`) pour cette fiche.
10. Créer un second cheval sans rien renseigner à part le nom : vérifier qu'aucune information
    n'apparaît en trop dans les colonnes (tirets), et qu'un identifiant technique différent du
    premier cheval lui est attribué dès son premier enregistrement.
11. Vérifier la console navigateur sur l'écran Cheval : aucune erreur JavaScript ; le champ
    « Préciser la robe »/« Préciser la race » n'apparaît que si « Autre » est sélectionné, et les
    champs de prix changent bien selon le mode choisi.
12. Vérifier qu'aucun asset du module (`cheval-admin.js`) n'est chargé sur un écran sans rapport
    (Tableau de bord, Articles, une Prestation, un Groupe tarifaire).
13. Désactiver puis réactiver le module (`config/modules.php`) : vérifier qu'aucun cheval, aucune
    catégorie, aucun statut commercial ni aucun identifiant technique n'a été modifié ou recréé.

### Micro-correction post-recette (0.4.1) — présentation de l'âge

La recette runtime de l'Étape 4 a été concluante ; une seule micro-correction a été demandée :
l'affichage de l'âge (« ≈ 7 an(s) (âge calendaire approximatif, jamais au jour près) ») ne
correspondait pas à la convention métier équine, où un cheval prend un an de plus au
1er janvier — ce n'est pas une approximation, c'est la définition retenue. Le **calcul**
(`année courante - année de naissance`) était déjà correct et reste inchangé
(`gwseq_cheval_age_from_birth_year()`) ; seule sa **présentation** a changé
(`gwseq_cheval_age_label()`, nouveau) : « 1 an » / « 7 ans » (accord singulier/pluriel via
`_n()`), sans le symbole « ≈ », sans la forme non accordée « an(s) », sans mention permanente
d'approximation. Une aide discrète (« Âge calculé automatiquement à partir de l'année de
naissance selon la convention équine. ») reste disponible en attribut `title` (infobulle), sans
surcharger l'écran.

### Décisions de roadmap actées lors de la recette de l'Étape 4 (aucun développement à ce stade)

Quatre besoins produits ont été confirmés pour la suite du plan de développement, sans qu'aucun
code ne soit construit maintenant :

- **Galerie photos et vidéos (Étape 6)** : jusqu'à 9 photos supplémentaires en plus de la Photo
  principale (10 au total), stockées en identifiants d'attachement WordPress ordonnés, sans
  duplication physique des médias, avec exploitation des tailles WordPress/`srcset` (jamais les
  originaux lourds sur le front) ; jusqu'à 10 vidéos par cheval (URL + titre facultatif, pas
  d'upload de fichier vidéo), vraisemblablement portées par le composant répétable de l'Étape 2.
  Médias conçus comme données structurées indépendantes du rendu HTML, réutilisables par
  web/PDF/catalogue/Social Kit.
- **Import / Onboarding en masse (chevaux, groupes/prestations, membres d'équipe)** : aucun
  importeur construit à ce stade, mais nouvelle règle architecturale permanente — toute donnée
  métier doit pouvoir être créée/mise à jour programmatiquement sans dépendre exclusivement du
  formulaire d'administration WordPress ; l'admin est un client du modèle métier parmi d'autres
  futurs clients (import, migrations, API, Network), jamais l'unique moyen valide de
  l'alimenter. Voir « Mini-audit Import/Onboarding » ci-dessous pour l'état actuel du code sur ce
  point précis.
- **Documentation / guide utilisateur** : aides contextuelles, guide utilisateur GWS Equestrian,
  documentation technique séparée pour développeurs/agents IA — à construire une fois les
  fonctionnalités et leur UX stabilisées, jamais pour compenser une UX déficiente.
- **Équipe / Membres** : besoin reconfirmé pour la V1 (déjà noté à l'issue de l'Étape 3), devra
  lui aussi respecter la règle de création programmatique ci-dessus.

#### Mini-audit Import/Onboarding — Groupe tarifaire / Prestation / Cheval

Question posée : existe-t-il une logique métier ou une validation essentielle actuellement si
couplée au formulaire admin / `$_POST` / `save_post` qu'un futur importeur devrait la dupliquer ?

- **Groupe tarifaire** : aucune meta custom, aucun couplage — Nom/Ordre/Description sont des
  champs natifs WordPress (`post_title`/`menu_order`/`post_excerpt`), déjà entièrement
  créables/modifiables via `wp_insert_post()`/`wp_update_post()` sans passer par un formulaire.
  **Rien à signaler.**
- **Prestation et Cheval** (même constat pour les deux, même structure de code) : la
  **sanitation** est déjà pure et réutilisable telle quelle
  (`gwseq_sanitize_prestation_tarification_input()`, `gwseq_sanitize_prestation_groupe_id()`,
  `gwseq_sanitize_cheval_identity_input()`, `gwseq_sanitize_cheval_commercial_input()` — aucune
  ne lit `$_POST` elle-même, toutes acceptent un tableau explicite en paramètre, déjà testées
  ainsi). En revanche, la **persistance** (l'association valeur sanitizée → clé de meta, via une
  série d'appels `update_post_meta()`) est aujourd'hui écrite à l'intérieur même de la fonction
  qui porte aussi les garde-fous de sécurité liés au formulaire (`gwseq_save_prestation_meta()`,
  `gwseq_save_cheval_meta()`), et qui lit `$_POST` directement plutôt que de recevoir un tableau
  en paramètre. Un futur importeur qui voudrait persister ces données devrait donc soit dupliquer
  cette séquence d'appels `update_post_meta()`, soit fabriquer un faux nonce/`$_POST` pour
  appeler la fonction existante — les deux étant fragiles et à éviter.
  **Factorisation minimale proposée (non réalisée à ce stade, en attente de validation)** :
  scinder chaque fonction de sauvegarde en deux — une fonction de persistance pure
  (`gwseq_persist_prestation_meta($post_id, $raw)` / `gwseq_persist_cheval_meta($post_id, $raw)`)
  qui sanitize puis enregistre les meta à partir d'un tableau explicite (réutilisable par un futur
  import, sans nonce ni `$_POST`), et la fonction existante réduite à ses seuls garde-fous de
  sécurité (nonce/capability/autosave/révision) suivie d'un simple appel à cette fonction avec
  `$_POST`. Aucun changement de comportement, aucune nouvelle abstraction générique — seulement
  un découpage en deux de code déjà écrit. **Non implémenté dans cette livraison**,
  conformément à la demande de ne pas modifier trois étapes déjà validées pour anticiper une
  fonctionnalité qui n'existe pas encore ; à réaliser au moment où l'Import/Onboarding sera
  réellement engagé, ou plus tôt si souhaité.
- **Global Horse ID et futur import** : déjà conforme aux règles rappelées en recette sans
  aucune modification nécessaire — `gwseq_assign_cheval_global_id()` génère un nouvel UUID
  uniquement en l'absence de meta existante (`metadata_exists()`), donc une création
  programmatique suivra la même règle qu'un enregistrement admin (nouveau cheval → nouvel UUID,
  cheval existant mis à jour → UUID conservé) sans code supplémentaire ; la meta n'étant jamais
  lue depuis `$_POST` ni exposée en REST, un fichier CSV/XLSX ne peut aujourd'hui imposer aucune
  valeur pour ce champ par construction.

### Prestations et Groupes tarifaires (Étape 3)

**Groupe tarifaire** : Nom (titre natif), Ordre (menu_order natif, meta box « Ordre
d'affichage »), Description courte (extrait natif, meta box « Description courte ») — aucune
meta custom, WordPress gère nativement la sauvegarde des trois champs.

**Prestation** : Nom (titre) et Description (éditeur natif) inchangés depuis l'Étape 1. Ajoutés
à cette étape :
- **Groupe tarifaire** : sélecteur dans la colonne latérale, référence par ID de post (jamais par
  nom — renommer un groupe ne casse jamais les prestations qui lui sont rattachées).
- **Tarification** (meta box dédiée) : mode Prix unique / Cheval-Poney (deux prix distincts) /
  **Sur demande** (ex-« Sur devis », valeur technique `devis` inchangée — représente désormais
  « prix sur demande / non communiqué » au sens large, avec un champ **Libellé affiché** propre
  au professionnel : « Sur demande », « Sur devis », « Nous contacter »... ou vide pour n'afficher
  aucune mention), unité parmi une liste fermée (+ « Autre » avec libellé personnalisé), case
  « Afficher ce tarif publiquement » pour un prix interne non diffusé sans multiplier les états
  incohérents. Aucun prix formaté n'est stocké : chaque composant (montant, unité, visibilité,
  libellé) est une donnée séparée, assemblée uniquement au moment du rendu (admin, puis
  web/API/PDF plus tard).
- **Ordre d'affichage** : identique au Groupe (menu_order natif).
- **Statut** : Brouillon/Publié natifs WordPress — aucun second système Actif/Inactif.
- **Modèles de prestations** : bouton « Partir d'un modèle » sur l'écran d'ajout, organisé par
  famille (Pension/Travail/Cours/Élevage/Reproduction/Autres). Un modèle ne fait que préremplir le
  formulaire ; la prestation créée est immédiatement indépendante, modifiable et supprimable
  librement, jamais réécrite par une évolution future de la liste de modèles.
- **Réglage global d'affichage des tarifs** (Prestations > Réglages) : TTC / HT / **Prix
  masqués** (aucun tarif public quelle que soit la case individuelle de la prestation — priorité
  masque global > masque individuel > rendu normal ; n'efface jamais les montants stockés).
  Indique uniquement la nature des montants déjà saisis, aucun calcul de TVA.
- **Réglage de devise** (même écran) : EUR par défaut, GBP/USD/CHF disponibles. Mapping local
  code → symbole (`gwseq_currency_symbol()`), aucune bibliothèque externe, aucun taux de change.

### Corrections post-recette runtime (0.3.3)

La première recette runtime de l'Étape 3 (GWS Core 1.6.2) a validé l'ensemble du modèle métier,
de la persistance et de la tarification, mais a révélé trois points corrigés dans cette version :

- **Modèles de prestations inaccessibles (bloquant).** Cause racine : `gwseq_prestation` utilise
  l'éditeur par blocs par défaut (`show_in_rest => true` depuis l'Étape 1), qui ne déclenche
  jamais le hook classique (`edit_form_after_title`) utilisé par le sélecteur de modèle — d'où son
  absence totale et silencieuse, malgré un code fonctionnellement correct. Corrigé en désactivant
  l'éditeur par blocs pour ce seul post type (`includes/prestation-editor.php`, filtre natif
  `use_block_editor_for_post_type`), qui restaure le gabarit classique. `show_in_rest` reste
  activé (réglage indépendant de l'éditeur affiché).
- **UX Nom/Description.** Le retour à l'éditeur classique règle aussi la confusion visuelle
  signalée (bloc pouvant remonter au-dessus du titre dans l'éditeur par blocs) : le gabarit
  classique place le titre en premier, de façon prévisible. Espace réservé du titre personnalisé
  (« Nom de la prestation ») et libellé « Description » ajouté au-dessus de l'éditeur natif —
  aucune donnée ajoutée, `post_title`/`post_content` restent les seules sources de vérité.
- **Internationalisation.** Toutes les chaînes d'interface du module passent désormais par les
  fonctions de traduction WordPress avec le text domain `gws-core` (partagé avec le cœur — voir
  `wp-content/plugins/gws-core/languages/README.md`). Le suffixe HT/TTC, signalé produisant
  `£ HT` avec la devise GBP, est désormais une chaîne traduisible indépendante de la devise
  choisie (une devise ne détermine jamais une langue). Le contenu saisi par le professionnel
  n'est jamais traduit.

Voir `CHANGELOG.md` de ce dossier (0.3.3) pour le détail complet.

#### Recette ciblée sur ces trois corrections

1. **Modèles de prestations** : Prestations > Ajouter — vérifier qu'un bloc « Comment
   souhaitez-vous commencer ? » apparaît immédiatement sous le titre, avec un sélecteur organisé
   par familles (Pension/Travail/Cours/Élevage/Reproduction/Autres). Choisir un modèle, cliquer
   « Préremplir depuis ce modèle » : le titre se remplit. Enregistrer, puis modifier librement le
   nom : rien ne rattache la prestation créée au modèle d'origine.
2. **UX Nom/Description** : sur ce même écran, vérifier que le champ titre est immédiatement
   identifiable (espace réservé « Nom de la prestation ») et qu'un libellé « Description »
   apparaît clairement au-dessus de la zone de texte, sans qu'aucun bloc ne les recouvre ou ne les
   fasse disparaître visuellement à l'ouverture de l'écran.
3. **i18n** : si une traduction anglaise du plugin est installée (voir
   `wp-content/plugins/gws-core/languages/README.md`), vérifier que les libellés de l'interface
   (modes de tarification, unités, réglages) s'affichent en anglais et que le suffixe HT/TTC
   affiche sa traduction anglaise, quelle que soit la devise choisie (y compris GBP). Sans
   traduction installée, vérifier au minimum que rien ne s'affiche cassé ou en anglais partiel.
4. Revérifier rapidement un point déjà validé pour confirmer l'absence de régression : créer une
   prestation avec un prix unique, l'enregistrer, vérifier la colonne Tarif de la liste.

### Arbitrages techniques de l'Étape 3

- **Catégorie métier et groupe tarifaire restent fusionnés** (décision de l'Étape 1 confirmée) :
  une Prestation n'appartient qu'à un seul `gwseq_groupe`.
- **Pas de glisser-déposer** pour l'ordre des groupes/prestations en V1 : champ numérique natif
  (`menu_order`) uniquement, listes d'administration triées par cet ordre par défaut. Priorité à
  la robustesse plutôt qu'au confort visuel, conformément à la demande — pourra être ajouté plus
  tard sans changement de modèle de données (l'ordre est déjà la seule donnée qui compte).
  Aucune donnée créée automatiquement.
- **Aucun QA dédié pour cette étape** : contrairement au composant répétable de l'Étape 2 (brique
  technique neutre nécessitant un jeu de démonstration), Prestations et Groupes tarifaires sont
  déjà les écrans métier réels — la recette utilise directement les menus **Prestations** et
  **Groupes tarifaires**, sans CPT ni contenu de test superflu.
- **Aucun risque de regroupement de lignes** (anomalie corrigée à l'Étape 2) : tous les nouveaux
  champs sont des champs simples à nom HTML fixe, jamais indexés — ce risque ne concerne que les
  structures répétables.

### Procédure de recette — Étape 3

À réaliser dans WordPress Local, sans écrire de code :

1. Menu **Groupes tarifaires** > Ajouter : créer « Pensions » avec une description courte et
   valider qu'il apparaît dans la liste avec son ordre. Créer un second groupe « Travail »,
   modifier son ordre pour qu'il apparaisse avant ou après « Pensions » dans la liste.
2. Menu **Prestations** > Ajouter une prestation : vérifier que le bloc « Partir d'un modèle »
   apparaît, choisir un modèle de la famille Pension (ex. « Pension pré avec infrastructures ») et
   cliquer sur « Préremplir depuis ce modèle » — le titre doit se remplir automatiquement.
3. Choisir le groupe « Pensions », mode « Prix unique », prix `45.50`, unité « Séance ». Publier.
   Vérifier dans la liste des Prestations que les colonnes Groupe tarifaire/Tarif/Ordre
   affichent les bonnes valeurs (« 45,50 € TTC / Séance » avec le réglage par défaut).
4. Modifier cette prestation : changer le mode en « Cheval / Poney », renseigner deux prix
   distincts, enregistrer, recharger — vérifier que les deux prix sont bien restitués séparément
   et que le tarif affiché dans la liste combine les deux.
5. Décocher « Afficher ce tarif publiquement », enregistrer : la colonne Tarif doit afficher
   « Tarif non affiché publiquement ».
6. Créer une seconde prestation en mode « Sur demande » : vérifier qu'aucun champ de prix n'est
   requis, que le champ « Libellé affiché » apparaît pré-rempli avec « Sur demande », et que la
   colonne Tarif affiche cette valeur. Le modifier en « Nous contacter », enregistrer, recharger :
   la colonne Tarif doit refléter le nouveau libellé. Le vider complètement et enregistrer :
   la colonne Tarif ne doit plus afficher aucun texte (tiret). Vérifier également que passer le
   réglage global sur « Prix masqués » (Prestations > Réglages) n'affecte pas un libellé non vide
   sur une prestation en mode « Sur demande ».
7. Choisir l'unité « Autre » sur une prestation, vérifier que le champ « Préciser l'unité »
   apparaît, le remplir (ex. « par cycle »), enregistrer, recharger.
8. Renommer le groupe « Pensions » en « Nos pensions » : vérifier que la prestation qui lui est
   rattachée continue d'afficher le bon groupe (donc que la relation n'a pas été cassée).
9. Aller dans **Prestations > Réglages**, basculer sur HT, enregistrer, revenir à la liste des
   Prestations : vérifier que les tarifs affichent désormais « HT » au lieu de « TTC ».
10. Passer une prestation en Brouillon : vérifier qu'aucun deuxième champ « Actif/Inactif » n'est
    présent, que le statut natif WordPress suffit.
11. Vérifier la console navigateur sur les écrans Prestation : aucune erreur JavaScript ; les
    champs de tarification s'affichent/masquent correctement selon le mode et l'unité choisis.
12. Vérifier qu'aucun asset du module (`prestation-admin.js`, `presets-admin.js`) n'est chargé
    sur un écran sans rapport (Tableau de bord, Articles, un Groupe tarifaire, un Cheval).
13. Vérifier qu'aucune prestation ni aucun groupe n'a été créé automatiquement par la simple
    activation ou mise à jour du module (liste vide sur un site qui n'en a pas créé lui-même).

### Composant répétable (Étape 2)

`includes/repeater-field.php` fournit la plus petite abstraction utile pour gérer une liste
ordonnée de lignes structurées (futurs indices de performance, URLs de vidéos, blocs éditoriaux
personnalisés) sans réécrire trois fois la même mécanique — **ce n'est pas un mini-ACF** : pas de
champs imbriqués, pas de types hypothétiques, pas d'exposition REST, pas de registre de types
extensible. Voir l'en-tête de ce fichier pour la documentation complète (comment déclarer une
structure, l'afficher, la sauvegarder, récupérer ses données). Démonstration neutre visible
uniquement en environnement local/développement : `includes/qa-repeater.php` (voir sa procédure
de recette ci-dessous).

Types de colonnes supportés : `text`, `textarea`, `number`, `integer`, `url` — les seules
primitives déjà nécessaires aux besoins identifiés par la conception validée. Stockage : une
seule meta WordPress par structure répétable, valeur = tableau indexé de lignes (chaque ligne un
tableau associatif clé de colonne => valeur sanitizée), lu et écrit avec les fonctions natives
`get_post_meta()`/`update_post_meta()` — aucune fonction de lecture dédiée n'est nécessaire, donc
aucune dépendance à ce fichier pour un futur consommateur (rendu front, export PDF).

### Procédure de recette — Étape 2 (composant répétable)

À réaliser dans WordPress Local, sans écrire de code. Mise à jour suite aux deux anomalies
relevées lors de la première recette (0.2.0 → 0.2.1, voir `CHANGELOG.md`) : les étapes 4 et 5
ci-dessous ciblent explicitement les deux cas qui avaient échoué.

1. Sur un environnement dont le type est `local` ou `development`
   (`wp_get_environment_type()`), un nouveau menu **QA — Répétable (Equestrian)** apparaît dans
   l'administration dès que le module `gws-equestrian` est actif. **Il ne doit apparaître ni
   exister en production.**
2. Ajouter un élément de test : la meta box « Composant répétable — démonstration (Libellé /
   Valeur / Année) » doit s'afficher, initialement vide.
3. Cliquer sur « + Ajouter une ligne » : une ligne de champs doit apparaître, le focus doit se
   poser sur son premier champ.
4. Remplir cette ligne avec `ISO` / `125.5` / `2025` : le champ Valeur doit accepter la décimale
   `125.5` sans blocage du navigateur (anomalie n°1 corrigée). Cliquer une seconde fois sur
   « + Ajouter une ligne » pour une deuxième ligne, la remplir avec `ICC` / `130` / `2026`, puis
   une troisième avec `IDR` / `0` / `2024` (tester en particulier `0` dans le champ Valeur, et
   des caractères spéciaux — apostrophe, accents, `&` — dans un champ Libellé). Publier ou
   mettre à jour.
5. Recharger la page d'édition : les **trois lignes doivent être restituées exactement comme
   saisies, chacune avec ses trois valeurs regroupées sur sa propre ligne** — `ISO`/`125.5`/`2025`,
   `ICC`/`130`/`2026`, `IDR`/`0`/`2024`, dans cet ordre. Vérifier explicitement qu'aucune valeur
   n'a été déplacée sur une ligne différente et qu'aucune ligne supplémentaire n'est apparue
   (anomalie n°2 corrigée).
6. Supprimer la ligne du milieu via son bouton « Supprimer », enregistrer, recharger : elle ne
   doit plus jamais réapparaître ; les deux autres restent intactes et dans leur ordre respectif.
7. Ajouter à nouveau deux nouvelles lignes à la suite : vérifier qu'elles s'enregistrent
   correctement l'une et l'autre (pas de collision d'index entre lignes ajoutées dans la même
   session d'édition).
8. Vérifier la console navigateur sur cet écran : aucune erreur JavaScript.
9. Vérifier qu'aucun fichier CSS/JS de ce composant n'est chargé sur un autre écran WordPress
   sans rapport (Tableau de bord, Articles, une Prestation, un Cheval...) — inspecter l'onglet
   réseau du navigateur.
10. Vérifier qu'aucun élément de test créé ici n'apparaît nulle part sur le site public (le CPT
    de démonstration n'est jamais public).
11. Supprimer les éléments de test créés une fois la recette terminée (aucune suppression
    automatique n'est effectuée par le module).

### Rappel — ce que l'Étape 1 a construit (toujours valide, non modifié depuis)

- Trois Custom Post Types enregistrés : `gwseq_prestation`, `gwseq_groupe` (Groupe tarifaire),
  `gwseq_cheval`.
- Une taxonomie : `gwseq_categorie_cheval`, attachée à `gwseq_cheval`.
- Aucun champ structuré, aucune relation, aucune logique métier, aucun écran d'administration
  dédié : les écrans natifs de WordPress (titre / contenu / image à la une pour Prestation et
  Cheval ; titre seul pour Groupe tarifaire) sont utilisés tels quels.
- Aucun gabarit front dédié : voir le README du dossier thème miroir.

### Décisions de conception déjà actées à ce stade

- **Groupe tarifaire n'est jamais public** (`public` => false, pas d'archive, pas de rewrite,
  exclu de la recherche) : c'est un objet d'organisation interne pour le classement et
  l'affichage des tarifs (étapes 3 et 8), pas une page éditoriale en soi. Éviter une URL sans
  contenu réel.
- **Catégorie métier et groupe tarifaire sont fusionnés** : une Prestation appartient à un seul
  Groupe tarifaire, qui sert à la fois de classement et de regroupement tarifaire. Aucune
  taxonomie de « catégorie métier » distincte n'est prévue.
- **Longueur des identifiants technique vérifiée** : WordPress limite un nom de post type à 20
  caractères. `gwseq_groupe` (et non `gwseq_groupe_tarifaire`, qui dépasserait cette limite) a
  été choisi pour cette raison — voir le test associé, qui verrouille cette contrainte.
- La taxonomie `gwseq_categorie_cheval` utilise désormais une interface à cases à cocher
  (`meta_box_cb => 'post_categories_meta_box'`, Étape 4) — voir la section Cheval ci-dessus.

### Ce qui n'est délibérément PAS encore construit

Conformément au périmètre strict fixé étape par étape : assistant de première configuration,
glisser-déposer, indices/vidéos/blocs personnalisés/galerie/résultats détaillés/origines
éditoriales **métier réels** (le composant répétable qui les portera existe depuis l'Étape 2,
mais aucune de ces données métier n'est encore créée), pedigree/relations (père/mère/ancêtres/
produits/fratrie), duplication, fiche privée/token de partage, export PDF/QR/catalogue, Social
Kit, Network, API publique, module Équipe (besoin identifié en recette de l'Étape 3, retenu pour
la feuille de route sans placement précis encore décidé), rendu front définitif. Ces éléments
arrivent aux étapes 5 à 9+ du plan de développement validé, chacune soumise à validation avant la
suivante.

### Point tranché à l'Étape 4 : photo principale

L'image à la une native de WordPress (déjà activée via `supports => array(..., 'thumbnail')`)
est retenue comme unique source de vérité pour la « Photo principale » du cheval — aucun champ
`attachment_id` dédié créé, seuls ses libellés admin sont ré-étiquetés. Voir la section Cheval
ci-dessus.

## Activer ce module (pour tester cette étape)

Comme tout module métier GWS, l'activation se fait uniquement via
`wp-content/plugins/gws-core/config/modules.php` — jamais par défaut dans le starter :

```php
return array('gws-equestrian');
```

Aucun autre fichier du cœur n'a besoin d'être modifié. Désactiver le module (retirer le slug du
tableau) ne supprime aucune donnée déjà créée — les fiches restent en base, simplement
inaccessibles tant que le module n'est pas réactivé (voir `AI-AGENT.md`, interdiction n°12).

# Changelog — GWS Starter

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

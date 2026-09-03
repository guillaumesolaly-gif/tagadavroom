# Changelog — GWS Equestrian (module)

Historique propre à ce module, distinct de la version du plugin `gws-core` qui l'héberge (voir
`GWSEQ_MODULE_VERSION` dans `module.php`). Le module atteindra la version `1.0.0` au gel de la V1
(fin de la dernière étape du plan de développement validé). Chaque étape ci-dessous a été livrée
puis recettée en conditions réelles avant validation de la suivante.

## 0.21.0 — Retrait du module Mises en avant (décision produit)

**Ceci n'est PAS une correction de bug ni une régression.** Après recette UX du lot 0.20.0
(ci-dessous), décision produit de ne pas conserver de moteur propriétaire Pop-in/Sticky bar dans
GWS Equestrian : cette fonctionnalité est jugée périphérique à la valeur métier du module, et sera
couverte, si l'usage réel le justifie un jour, par une extension WordPress tierce spécialisée
(probablement Hustle) plutôt que par du code maison à maintenir. L'implémentation livrée en 0.20.0
fonctionnait et avait été validée par une suite de tests complète — son retrait est une décision
de périmètre, pas un désaveu technique.

**Décision produit conservée** (documentaire uniquement, aucun code) : *Pop-in / mises en avant
temporaires : fonctionnalité périphérique pouvant être couverte par une extension spécialisée
telle que Hustle.* Aucune dépendance, installation automatique, intégration, template ni logique
spécifique à une extension tierce n'a été ajoutée — ce sujet sera retraité séparément si l'usage
réel le justifie.

**Retrait complet** de tout ce qui avait été introduit spécifiquement pour ce lot :
- Fichiers supprimés : `includes/campagnes-shared.php`, `includes/popin-fields.php`,
  `includes/sticky-bar-fields.php`, `includes/campagnes-front.php`, `assets/campagnes-admin.js`,
  `assets/campagnes-admin.css`, `assets/campagnes-front.js`, `assets/campagnes-front.css`,
  `tests/gws-equestrian-campagnes-shared-test.php`, `tests/gws-equestrian-popin-logic-test.php`,
  `tests/gws-equestrian-sticky-bar-logic-test.php`, `tests/gws-equestrian-campagnes-front-test.php`,
  `tests/gws-equestrian-campagnes-front-runtime-test.js`.
- `module.php` : constantes `GWSEQ_CPT_POPIN`/`GWSEQ_CPT_STICKY_BAR` et les quatre `require_once`
  correspondants retirés.
- `includes/post-types.php` : les deux `register_post_type()` (et le docblock expliquant la
  technique de regroupement de menu) retirés — les deux CPT ne sont plus enregistrés, le menu
  "Mises en avant" a disparu.
- `includes/admin-ui.php` : les deux hooks `add_meta_boxes_gwseq_popin`/`add_meta_boxes_
  gwseq_sticky_bar`, et les deux post types retirés des tableaux de `gwseq_admin_default_order_
  by_menu_order()` et `gwseq_remove_quick_edit_row_action()`.

Aucune suppression de données en base : les CPT ne sont plus enregistrés, mais aucun script de
purge de posts/metas orphelins n'a été écrit (environnement encore en développement — un nettoyage
volontaire des données de test, s'il est souhaité, reste une action séparée et délibérée).

**Tests** : les quatre fichiers de test métier spécifiques (`campagnes-shared`, `popin-logic`,
`sticky-bar-logic`, `campagnes-front`) et le fichier d'exécution réelle Node
(`campagnes-front-runtime-test.js`) sont supprimés avec le code qu'ils couvraient.
`gws-equestrian-foundations-test.php` : le compte de post types revient à quatre (Prestation,
Groupe, Cheval, Membre), toutes les références aux deux CPT retirées, et trois nouvelles
assertions confirment explicitement l'absence des deux post types et du libellé de menu "Mises en
avant" (vérifiées par retrait/restauration : réintroduire un enregistrement minimal du CPT Pop-in
fait échouer ces trois assertions comme attendu, remise en place -> suite de nouveau verte).
`gws-equestrian-actualites-logic-test.php` : assertion de non-régression alignée sur quatre post
types. Cadrage Gutenberg des Actualités inchangé et toujours couvert par sa suite dédiée. Suite
complète (18 fichiers PHP + 2 suites JS runtime) ré-exécutée : aucune régression sur Cheval,
Prestations, Équipe, Actualités, Groupes tarifaires.

## 0.20.0 — Mises en avant : Pop-in et Sticky bar

Nouveau lot « Actualités cadrées + Mises en avant ». La partie Actualités (0.19.0, allowlist
Gutenberg) est conservée à l'identique. Ce lot ajoute deux nouveaux objets métier BO, avec rendu
front RÉEL cette fois (contrairement à Actualités) : impossible de valider autrement le
fonctionnement de déclenchement/fréquence/fermeture d'une Pop-in ou d'une Sticky bar.

**Architecture BO** : deux nouveaux post types non publics, `gwseq_popin` et `gwseq_sticky_bar`
(`includes/post-types.php`), regroupés sous UNE SEULE entrée de menu top-level "Mises en avant"
avec deux sous-menus "Pop-ins"/"Sticky bars" — obtenu nativement, sans aucun `add_menu_page()`
custom : le CPT Pop-in porte `labels->name = 'Mises en avant'` (nom de son propre menu
auto-généré) et `labels->all_items = 'Pop-ins'` (son premier sous-menu automatique) ; le CPT
Sticky bar pointe `show_in_menu` vers le slug exact du menu du premier CPT
(`edit.php?post_type=gwseq_popin`), ce qui déclenche le mécanisme natif
`_add_post_type_submenus()` de WordPress pour l'attacher comme second sous-menu. Ni l'un ni
l'autre n'utilise Gutenberg (fiches structurées en meta boxes, à l'image de Membre/Groupe
tarifaire) ; nom interne = titre natif, jamais affiché publiquement (post types `public => false`,
même précédent que Groupe tarifaire — pas de "Voir"/Aperçu natif).

**Mutualisation ciblée** (`includes/campagnes-shared.php`, nouveau fichier — AUCUNE classe
abstraite, aucun moteur de campagne générique, aucun schema builder : seulement ce qui est
réellement commun aux deux objets) : mode de style (Style du site/Personnaliser) et sa
sanitation, couleurs (`sanitize_hex_color()`), CTA (Libellé + URL, guidage identique à Équipe),
texte enrichi minimal (`wp_kses` restreint + éditeur TinyMCE "teeny" scopé par un indicateur
global pour ne jamais affecter d'autres usages natifs du mode teeny dans wp-admin), dates/fuseau
(`wp_timezone()`, stockage en UTC, jamais de calcul naïf sur le fuseau serveur), statut de
diffusion (Active/Inactive — délibérément DISTINCT du statut natif WordPress : une fiche peut être
"Publiée" mais "Inactive"), ciblage (voir plus bas), fonction de fenêtre de dates, rendus de
formulaire communs (section Diffusion, sélecteur de ciblage, panneau d'aperçu), et le garde-fou de
sécurité de l'aperçu AJAX. Tout le reste (déclenchement, fréquence, taille, position, images, CTA
spécifique) vit dans le fichier propre à chaque objet.

**Pop-in** (`includes/popin-fields.php`) : Contenu (Titre, Texte enrichi minimal, Image
facultative, CTA facultatif), Apparence (Style du site par défaut ou Personnaliser — couleurs
fond/texte/CTA/CTA-texte + image de fond FACULTATIVE, explicitement DISTINCTE de l'image de
contenu ; Taille Compacte/Standard/Large ; toujours centrée, jamais de police/marges/CSS/position
libre exposés), Déclenchement (Immédiatement / Après X secondes / Après X % de scroll / À
l'intention de sortie — bornes serveur sur X, mode invalide replié sur "Immédiatement"),
Fréquence (À chaque visite / Une fois par session / Une fois tous les X jours). La Pop-in est
TOUJOURS fermable (aucune option de blocage) : croix accessible, Échap, gestion de focus (piège à
focus + restauration du focus au déclencheur d'origine à la fermeture).

**Intention de sortie — desktop uniquement, AUCUN fallback mobile automatique.** Détection via
`matchMedia('(hover: hover) and (pointer: fine)')` (jamais de sniffing de user-agent) : sur un
terminal sans survol, le déclencheur ne s'active tout simplement jamais, et une aide explicite
("L'intention de sortie est disponible uniquement sur ordinateur. Pour cibler également les
visiteurs mobiles, utilisez plutôt le délai ou le scroll.") est affichée en BO. Ceci ANNULE la
proposition initiale d'un repli automatique 60 s/50 % de scroll sur mobile, explicitement rejetée
lors de la validation de l'architecture.

**Sticky bar** (`includes/sticky-bar-fields.php`) : objet distinct, volontairement plus simple —
pas de section Déclenchement (une Sticky bar éligible s'affiche immédiatement), pas d'image
(ni de contenu ni de fond), pas de fréquence configurable en base (seule la fermeture, si activée,
est mémorisée côté client). Contenu (Texte court en texte SIMPLE, jamais enrichi — Libellé/URL
CTA facultatif), Apparence (Style du site/Personnaliser — couleurs uniquement, PAS d'image de
fond ; Position Haut/Bas ; case "L'utilisateur peut fermer la barre" — fermeture donc
CONDITIONNELLE, contrairement à la Pop-in qui est toujours fermable).

**Diffusion commune** (Pop-in et Sticky bar) : Statut (Active/Inactive), Période (début/fin
facultatifs, fuseau du site), Ciblage à quatre modes — Tout le site / Page d'accueil uniquement
(toujours via `is_front_page()`, jamais un ID de page particulier) / Certains contenus / Tout le
site sauf certains contenus. Le ciblage couvre Pages, Chevaux, Prestations ET Actualités (jamais
limité aux Pages) : chaque cible est stockée comme une clé composite `post_type:post_id`
(`gwseq_encode_campagne_cible()`/`gwseq_decode_campagne_cible()`), jamais un simple tableau d'ID
ambigu entre post types. La sanitation revalide systématiquement que l'ID soumis correspond bien
au post_type déclaré (protection contre une usurpation de post_type) et rejette tout post type
hors de la liste autorisée.

**Conflits/priorité** : au plus une Pop-in ET au plus une Sticky bar par page (une Pop-in et une
Sticky bar PEUVENT en revanche cohabiter). Plusieurs campagnes du même type éligibles ?
`menu_order` croissant (réutilisation du système d'ordre déjà existant, natif via
`page-attributes`) — jamais un second champ "Priorité".

**Aperçu temps réel BO** (§J) : source de rendu UNIQUE et partagée entre l'aperçu BO et le front —
`gwseq_render_popin_markup($config, $extra_attrs)`/`gwseq_render_sticky_bar_markup($config,
$extra_attrs)`, fonctions PHP pures (aucun accès BDD). L'aperçu passe par `admin-ajax.php` (nonce
dédié) : état de formulaire → mêmes sanitizers que la sauvegarde → même fonction de rendu → HTML
renvoyé et injecté dans le panneau d'aperçu (`assets/campagnes-admin.js`, debounce 350 ms,
coalescence des requêtes en vol). Bascule Ordinateur/Mobile qui ne change QUE la largeur de
l'aperçu — une seule configuration responsive existe.

**Styles du thème** (§K) : en mode "Style du site", les composants utilisent directement les
variables déjà exposées par le thème GWS (`--color-bg`, `--color-text`, `--color-primary`,
`--color-primary-contrast`) avec repli raisonnable. En mode personnalisé, des propriétés
spécifiques au composant (`--gws-popin-bg`, `--gws-sticky-bg`, etc.) sont injectées directement
sur son conteneur, avec repli vers les jetons du thème puis une valeur de secours codée en dur en
tout dernier recours — jamais une couleur client codée en dur en position primaire.

**Rendu front** (§L, `includes/campagnes-front.php`, nouveau fichier) : contrairement à
Actualités, un vrai rendu front est nécessaire ici. Éligibilité évaluée entièrement AVANT tout
enqueue de script/style (statut + fenêtre de dates + ciblage) — aucun chargement systématique sur
toutes les pages, aucun balisage produit "au cas où" pour une campagne non éligible. Rendu via
`wp_footer`, utilisant les MÊMES fonctions de rendu que l'aperçu BO — aucune divergence possible.

**Fréquence** (§F, `assets/campagnes-front.js`, nouveau fichier) : entièrement côté client, sans
identifiant ni tracking. Une clé par Pop-in basée sur son ID : `sessionStorage` pour "session",
`localStorage` avec horodatage comparé à `Date.now()` pour "X jours", rien pour "à chaque visite".
La marque est posée AU MOMENT DE L'AFFICHAGE (pas à la fermeture) : fermer la Pop-in ne fait que
masquer un élément déjà marqué "montré", ce qui satisfait naturellement "fermer compte comme une
exposition" sans logique séparée.

**Tests** : quatre nouveaux fichiers PHP (`gws-equestrian-campagnes-shared-test.php`,
`gws-equestrian-popin-logic-test.php`, `gws-equestrian-sticky-bar-logic-test.php`,
`gws-equestrian-campagnes-front-test.php`) et un nouveau fichier d'exécution réelle Node
(`gws-equestrian-campagnes-front-runtime-test.js`, contrairement au reste de la suite PHP qui ne
peut que scanner du texte source, celui-ci exécute réellement `assets/campagnes-front.js` contre
un DOM minimal fait main — fréquence session/X jours, intention de sortie desktop vs absence sur
mobile, piège à focus, restauration du focus, fermeture Sticky bar). Non-régression complète sur
les CPT/écrans existants (Cheval, Prestations, Équipe, Actualités, Groupes tarifaires). Un bug
réel a été détecté et corrigé pendant l'écriture des tests d'intégration front (voir
`includes/campagnes-front.php`) : `gwseq_campagne_choisir_eligible()` transmettait le tableau de
diffusion brut (clés `ciblage_mode`/`ciblage_cibles`) à `gwseq_campagne_page_est_ciblee()`, qui
attend des clés `mode`/`cibles` — ce qui aurait provoqué une erreur fatale PHP en front sur toute
campagne ciblée en mode "Certains contenus"/"Tout sauf certains contenus". Corrigé par
construction explicite du tableau de ciblage attendu avant l'appel ; revert-and-verify confirmé
(voir tests). Deux autres mécanismes critiques (priorité par `menu_order`, garde desktop-only de
l'intention de sortie) ont également été validés par revert-and-verify.

**Limites connues (V1)** : pas de ciblage sur Équipe, archives, catégories/taxonomies, résultats
de recherche, URL par expression régulière, ni de règles avancées combinées — volontairement hors
périmètre de ce lot. Pas de branding BO multi-thème (réutilisation directe des jetons CSS déjà
exposés par le thème GWS actif).

## 0.19.0 — Actualités : cadrage de l'éditeur par blocs (Gutenberg)

Le bloc Actualités V1 (0.18.0) fonctionne et a été validé en runtime — il n'a pas été reconstruit.
Gutenberg reste techniquement l'éditeur des Actualités, mais sa palette de blocs est désormais
volontairement restreinte à une expérience éditoriale simple et cadrée, adaptée à une cible
d'utilisateurs quasiment débutants en informatique : la mise en page avancée (colonnes, groupes,
couverture, HTML personnalisé...) reste l'affaire du thème/GWS, jamais de l'utilisateur.

**Audit préalable** : aucun filtre `allowed_block_types`/`allowed_block_types_all` n'existait avant
ce lot (ni dans `gws-core`, ni dans `gws-starter`) — la palette complète de blocs core était donc
disponible, plus le seul bloc personnalisé du thème (`gws/resource-link`, jamais utilisé par une
Actualité existante). Aucun bloc "technique invisible" n'est nécessaire au bon fonctionnement de
l'éditeur dans ce contexte (site sans contenu hérité ni blocs réutilisables).

**Mécanisme retenu** (`includes/actualites.php`) : le filtre natif `allowed_block_types_all`
(prévu par WordPress précisément pour restreindre la palette de blocs d'un contexte d'édition
donné), scopé à `$context->post->post_type === 'post'` — tout autre contexte (Pages, widgets par
blocs, éditeur de site) reçoit la valeur `$allowed_block_types` REÇUE EN ENTRÉE, inchangée, jamais
recalculée. Allowlist (toujours une liste à INCLURE, jamais à EXCLURE — sûre par défaut, un futur
bloc core inconnu n'apparaît jamais tant qu'il n'a pas été explicitement ajouté) : Paragraphe,
Titre, Liste (+ `core/list-item`, son bloc interne obligatoire depuis le passage en v2 — jamais un
choix éditorial supplémentaire), Image, Galerie, Bouton (+ `core/buttons`, son conteneur
obligatoire), Vidéo, intégration vidéo sûre (`core/embed`, qui couvre les intégrations YouTube/
Vimeo — de simples variations du même bloc, jamais un second bloc à ajouter séparément).

Déjà validé et conservé à l'identique dans ce lot : titre, contenu, image mise en avant, extrait,
catégorie, auteur, brouillon/publication/planification, tags masqués, commentaires désactivés,
Quick Edit supprimé. Aucun rendu front développé.

**Tests** (`tests/gws-equestrian-actualites-logic-test.php`, 20 nouvelles assertions) : allowlist
exacte, exclusion explicite des blocs de mise en page avancée/techniques (colonnes, groupe,
couverture, HTML, code, classique, widgets hérités, éléments de thème/site, shortcode), scope
strictement limité à `post` (une Page, un autre post type GWS, un contexte sans post ou dépourvu de
la propriété `post` reçoivent tous la valeur d'entrée inchangée). Vérifié par retrait/restauration.
Intégralité des suites existantes (19 fichiers PHP + 2 suites JS runtime) revérifiée, aucune
régression.

## 0.18.0 — Bloc Actualités (adaptation de `post`) + filtre Prestations par Groupe tarifaire

**Audit préalable** (avant toute modification) : aucune personnalisation existante de `post` n'a
été trouvée dans `gws-core` ni dans `gws-starter` — ni libellés, ni support retiré, ni réglage de
discussion, ni action de menu/liste, ni gestion de `post_tag`. `post` était encore dans son état
par défaut WordPress.

**Actualités** (`includes/actualites.php`) : réutilisation intégrale du système NATIF des articles
WordPress (`post`) — AUCUN nouveau post type (`gwseq_actualite` n'existe pas), aucune migration,
aucun système éditorial parallèle. Quatre mécanismes natifs distincts, jamais une réécriture de
l'enregistrement de `post`/`post_tag` :
1. **Vocabulaire "Actualités"** via le filtre natif `post_type_labels_post` (mécanisme WordPress
   prévu pour personnaliser le vocabulaire d'un post type déjà enregistré, y compris natif) :
   Actualités / Toutes les actualités / Ajouter une actualité / Modifier l'actualité / Nouvelle
   actualité / Rechercher une actualité, et les libellés natifs pertinents visibles dans le même
   parcours d'écran (notifications de publication, médiathèque, filtre de liste...).
2. **Étiquettes masquées, jamais supprimées** (`post_tag`) via le filtre natif
   `register_taxonomy_args` (`show_ui => false`, `show_admin_column => false`) : aucune
   désinscription de la taxonomie, aucune donnée détruite (`show_in_rest` inchangé). Portée
   signalée : `post_tag` étant unique et partagée par tout le site, ce masquage s'applique à toute
   édition de `post` dès que GWS Equestrian est actif — le périmètre voulu, sans bascule plus fine
   possible sans développement plus lourd, volontairement non engagé.
3. **Commentaires/trackbacks retirés** pour les nouvelles Actualités via
   `remove_post_type_support('post', 'comments'|'trackbacks')`, accroché après l'enregistrement
   natif de `post`. Effet natif documenté de WordPress lui-même : une fois le support retiré,
   `get_default_comment_status('post')` renvoie systématiquement `'closed'` pour toute NOUVELLE
   Actualité, quel que soit le réglage global Discussion — sans code supplémentaire. Aucune donnée
   de commentaire existante, ni le statut déjà enregistré sur une Actualité existante, n'est
   modifié.
4. **Modification rapide retirée** en réutilisant la fonction déjà existante
   `gwseq_remove_quick_edit_row_action()` (`includes/admin-ui.php`, déjà partagée par
   Chevaux/Membres/Prestations/Groupes tarifaires) — `post` y est simplement ajouté, jamais un
   second filtre dupliqué.

Catégories (§3) et champs d'édition (§2) : AUCUN code — la taxonomie `category` native reste
strictement inchangée (aucune catégorie créée automatiquement, celles existantes préservées),
titre/contenu/image à la une/date/statut/auteur restent les mécanismes natifs, aucun champ métier
supplémentaire. Aucun rendu front développé dans ce lot.

**Filtre de la liste Prestations par Groupe tarifaire** (`includes/prestation-fields.php`,
demande complémentaire) : liste déroulante au-dessus de `Prestations → Toutes les prestations`
(valeur par défaut "Tous les groupes tarifaires"), combinable avec la recherche native et la
pagination. Réutilise EXACTEMENT la relation déjà en place (`_gwseq_prestation_groupe_id`,
`gwseq_get_prestation_groupe_choices()` — nouvelle fonction extraite pour n'avoir qu'UNE SEULE
requête de liste des groupes, partagée avec le sélecteur de la fiche Prestation), aucune deuxième
logique de classement, aucune donnée ni modèle modifiés. "Sans groupe tarifaire" couvre proprement
les deux cas réels via une clause `meta_query` en relation `OR` : une prestation dont la meta vaut
explicitement `0` (relation retirée volontairement) ET une prestation créée avant l'existence de
cette relation, dont la meta n'existe simplement pas (`NOT EXISTS`).

**Tests** : nouveau fichier `tests/gws-equestrian-actualites-logic-test.php` (46 assertions :
`post` reste `post`, vocabulaire, masquage non destructif des Étiquettes, retrait des
commentaires/trackbacks, Modification rapide, permissions Éditeur, non-régression sur les quatre
objets métier GWS) ; `tests/gws-equestrian-foundations-test.php` mis à jour (retrait de
Modification rapide étendu à `post`, contrôle négatif basculé sur les Pages) ; nouvelles
assertions dans `tests/gws-equestrian-prestations-logic-test.php` pour le filtre Groupe tarifaire
(rendu, persistance, application réelle à la requête, cas "Sans groupe tarifaire", non-régression).
Toutes les régressions attendues confirmées par retrait/restauration. Intégralité des suites
existantes (19 fichiers PHP + 2 suites JS runtime) revérifiée, aucune régression.

## 0.17.1 — Micro-corrections UX post-recette Équipe

Suite à la validation runtime du module Équipe (0.17.0), quatre micro-corrections ciblées avant le
gel de la V1 — aucune autre évolution fonctionnelle, aucun changement sur Cheval/Prestations/
Groupes tarifaires hors ce qui suit.

1. **Aide à la saisie des réseaux sociaux/URL** (`includes/membre-fields.php`) : la recette a
   révélé qu'une saisie comme `www.google.com` (sans `https://`) n'était pas conservée — le champ
   HTML `type="url"` refuse nativement, côté navigateur, une valeur sans schéma reconnu avant même
   la soumission du formulaire. Aucune logique de stockage/sanitation modifiée (toujours
   `esc_url_raw()` via `gws_core_field_sanitize('url', ...)`, aucune reconstruction automatique
   d'URL à partir de `@compte`/`www...`) : uniquement des `placeholder` explicites (Instagram,
   Facebook, LinkedIn, TikTok, Site) et une aide "Saisissez l'URL complète, avec https://" sous
   chacun des cinq champs.
2. **WhatsApp** : aide "Format international recommandé, ex. +33 6 12 34 56 78" ajoutée sous le
   champ existant — aucun sélecteur de pays, aucune bibliothèque téléphonique, aucune normalisation
   ajoutée.
3. **Recherche dans Équipe** (`includes/post-types.php`) : le bouton de recherche de
   `Équipe → Tous les membres` affichait le libellé générique WordPress "Rechercher des articles"
   (`search_items` n'est jamais dérivé automatiquement de `name`/`singular_name` par WordPress).
   Corrigé via le libellé natif du CPT (`'search_items' => 'Rechercher des membres'`), jamais un
   remplacement visuel. La même anomalie, vérifiée par la même occasion, existait aussi sur
   Chevaux, Prestations et Groupes tarifaires — corrigée pour les quatre : "Rechercher un cheval",
   "Rechercher une prestation", "Rechercher un groupe tarifaire", "Rechercher des membres".
4. **Suppression de "Modification rapide" (Quick Edit)** (`includes/admin-ui.php`) sur les listes
   d'administration des quatre objets métier GWS Equestrian (Chevaux, Membres, Prestations,
   Groupes tarifaires) : l'édition passe désormais toujours par la fiche complète. Ciblé
   uniquement sur ces quatre post types via le filtre natif `post_row_actions`
   (`gwseq_remove_quick_edit_row_action()`) — Quick Edit reste pleinement disponible pour les
   Articles/Pages et tout autre post type, aucune désactivation globale. Les actions "Voir"/
   "Aperçu" existantes n'ont pas été modifiées dans ce lot (voir le CR de livraison pour l'état des
   lieux constaté : absentes pour Groupe tarifaire, non public ; présentes pour Cheval/Prestation/
   Membre, pointant vers l'URL front du post type — aucun gabarit dédié n'existe encore, rendu via
   le gabarit générique du thème, `single.php`).

**Tests** : nouvelles assertions dans `tests/gws-equestrian-foundations-test.php` (libellés
`search_items` des quatre post types, retrait de Quick Edit vérifié pour les quatre ET son
maintien pour un post type non concerné) et `tests/gws-equestrian-membre-logic-test.php`
(placeholders et aides affichés, non-régression de la sanitation URL). Intégralité des suites
existantes revérifiée, aucune régression.

## 0.17.0 — Module Équipe (nouvel objet métier Membre)

Nouvel objet métier, indépendant de Cheval : **Équipe**, pour gérer les personnes qu'une structure
équestre souhaite présenter (dirigeants, cavaliers, moniteurs, soigneurs, grooms, responsables
d'élevage, vétérinaires intégrés, personnel administratif...). Volontairement simple — ni annuaire
RH, ni système de comptes utilisateurs, ni CRM : un Membre est une fiche métier structurée, chaque
information restant individuellement accessible (jamais un blob HTML), pour une réutilisation
future sur le site et d'autres supports GWS (page Équipe, blocs individuels, catalogues, Social
Kit, API/exports).

**Architecture** (`includes/membre-fields.php`, `includes/membre-editor.php`) : nouveau post type
`gwseq_membre`, menu d'administration "Équipe" (`Équipe → Tous les membres` /
`Équipe → Ajouter un membre`), fiche appelée "Membre". Tous les champs sont facultatifs. Aucun
référentiel ni taxonomie créés, à l'exception des Langues (seul champ réellement structuré) : le
reste (Fonction/rôle, Localisation, Spécialités, Diplômes/qualifications) reste volontairement du
texte libre — GWS doit fonctionner avec des structures et qualifications différentes selon les
pays.

**Trois sections simples** (Identité, Profil, Contact — trois meta boxes empilées, pas un système
d'onglets) : le système d'onglets de Cheval (`includes/cheval-admin-tabs.php`) est structurellement
couplé à ce seul post type (écran ciblé en dur, script dédié, déplacement DOM spécifique à sa boîte
Médias) ; le généraliser pour un module aussi réduit aurait créé exactement le couplage étrange
mis en garde en amont — trois meta boxes empilées restent immédiatement lisibles sans abstraction
supplémentaire.

- **Identité** : Prénom, Nom, Fonction/rôle (texte libre, aucune liste imposée), Photo (image à la
  une native relabellée "Photo", aucune meta parallèle, aucun système d'upload parallèle),
  Localisation (texte libre, utile aux structures multi-sites).
- **Profil** : Présentation/parcours (texte long libre), Spécialités (texte libre), Diplômes/
  qualifications (texte libre, aucun référentiel français imposé), Langues (sélection multiple,
  valeurs canoniques stables `fr/en/de/es/it/pt/nl/sv/zh/ja/ar/autre` indépendantes des libellés
  affichés — les noms complets sont affichés, jamais seulement FR/EN/DE ; "Autre" révèle un champ
  libre "Préciser" dont le serveur reste l'autorité : si "Autre" n'est plus sélectionné, la
  précision est systématiquement nettoyée, même si l'ancienne valeur est encore soumise).
- **Contact** (tous facultatifs) : Téléphone (texte libre, aucun format imposé, un numéro
  international n'est jamais dénaturé), E-mail (sanitation WordPress appropriée), WhatsApp
  (donnée indépendante du téléphone principal, adaptée à une future construction de lien wa.me —
  cette construction elle-même n'est pas développée dans ce lot), Instagram/Facebook/LinkedIn/
  TikTok/Site (URLs sanitisées, aucune connexion aux API des réseaux sociaux).

**Titre technique automatique** (post_title = Prénom + Nom, jamais saisi séparément) : un filtre
`wp_insert_post_data` (jamais un second `wp_update_post()` dans un hook `save_post`, qui obligerait
à se dés-accrocher soi-même pour éviter une boucle) recalcule le titre à chaque enregistrement réel
à partir du prénom/nom de LA MÊME soumission, protégé par le même nonce que la sauvegarde des
meta — une révision, un autosave, ou tout appel de `wp_insert_post()` ne portant pas ce nonce (ex.
Quick Edit) laisse le titre déjà enregistré intact. Fonctionne en brouillon, avec prénom seul, nom
seul, ou les deux vides (titre vide, WordPress affiche alors nativement "(sans titre)"). Le champ
Titre natif est masqué sur l'écran d'édition (même technique CSS ciblée que
`includes/cheval-categories.php`) pour éviter une saisie redondante silencieusement écrasée.

**Liste "Tous les membres"** : colonnes Photo (miniature WordPress 40×40, jamais l'image
originale) | Nom | Fonction/rôle | Localisation | Langues (représentation compacte, ex. "Français,
Anglais") | Ordre (menu_order natif, aucun glisser-déposer dans ce lot) — colonne native "Date"
retirée (même choix que Cheval). Recherche WordPress native pleinement fonctionnelle sans code
supplémentaire (le titre EST le nom).

**Permissions** : post type enregistré sans `capability_type` personnalisé (type par défaut
`'post'`, même logique que Prestation/Groupe/Cheval) — un Éditeur peut consulter/ajouter/modifier/
publier/gérer la photo/mettre à la corbeille un membre sans qu'aucune capacité technique
supplémentaire ne soit créée pour ce seul module.

**Tests** (`tests/gws-equestrian-membre-logic-test.php`, 140 assertions, et mise à jour de
`tests/gws-equestrian-foundations-test.php` pour le 4ᵉ post type) : membre vide/minimal, prénom +
nom, titre automatique (prénom seul, nom seul, les deux vides, jamais recalculé sans le bon nonce
ni pendant un autosave), sauvegarde/rechargement de tous les champs des trois sections, langues
multiples, "Autre" + Préciser, suppression de "Autre" nettoyant la précision EN BASE au
réenregistrement, sanitation e-mail/URLs, téléphone international non détruit, WhatsApp
indépendant du téléphone, colonnes de la liste (contenu réel et cas de données manquantes),
absence de la colonne Date, sécurité de la sauvegarde (nonce/permissions/révision/autosave), et
absence d'effet de bord sur Cheval/Prestation/Groupe. Intégralité des suites existantes GWS Core
et GWS Equestrian revérifiée (18 fichiers PHP + 2 suites JS runtime), aucune régression.

**Non développé dans ce lot** (conformément à la demande) : comptes utilisateurs, login, planning,
horaires, contrats, salaires, RH, disponibilité, réservations, relations avec les chevaux,
affectation à des prestations, catégories/départements/organigramme d'équipe, taxonomie des
fonctions/spécialités/diplômes, CV PDF, import Excel/CSV, génération PDF, rendu front, schema.org
spécifique, connexion aux API des réseaux sociaux.

## 0.16.0 — Corrections de clôture du back-office Cheval V1

Suite à un audit fonctionnel du back-office Cheval en conditions réelles (module jugé
fonctionnellement mature), deux correctifs ciblés avant le gel de la V1 — aucun refactor, aucune
nouvelle fonctionnalité au-delà de ce qui suit, aucun autre comportement modifié.

**1. Nettoyage des relations père/mère lors de la suppression définitive d'un cheval.**

Cause technique exacte du symptôme observé (« Cheval introuvable (#ID) », sélecteur vide) :
`gwseq_get_horse_parent()` lit fidèlement les métadonnées `mode`/`horse_id` enregistrées sans
jamais revérifier, À LA LECTURE, que le post référencé existe encore (comportement volontaire —
une vérification d'existence à chaque lecture serait coûteuse pour un cas qui ne devait survenir
qu'au moment précis d'une suppression définitive). Or aucun hook n'intervenait jusqu'ici sur cette
suppression pour nettoyer la relation en amont : l'identifiant d'un cheval définitivement supprimé
restait donc enregistré comme père/mère "gws" d'un autre cheval, produisant un sélecteur vide
(aucun candidat ne correspond plus à cet ID) et le repli `type === 'unavailable'` déjà existant du
résolveur de pedigree.

**Corbeille ≠ suppression définitive** : mettre un cheval à la corbeille ne déclenche toujours
aucun nettoyage (`wp_trash_post()` ne déclenche jamais `before_delete_post`) — un parent à la
corbeille reste un post réel en base, la relation reste intacte, une restauration la retrouve
automatiquement, exactement comme avant ce correctif. Seule la suppression DÉFINITIVE (bouton
"Supprimer définitivement" ou vidage de la corbeille) déclenche désormais
`gwseq_cleanup_horse_parent_references_on_delete()` (accrochée uniquement à `before_delete_post`,
`includes/cheval-pedigree.php`) : elle recherche, via la fonction déjà existante
`gwseq_get_horse_offspring()` (jamais dupliquée), tous les chevaux référençant l'ID supprimé comme
père ou mère en mode "Cheval déjà enregistré", et réinitialise UNIQUEMENT cette relation précise à
« Non renseigné » (mode vidé, identifiant supprimé) — jamais de reconstruction d'un ascendant
externe de remplacement, jamais une autre branche du pedigree touchée.

**2. Liste d'administration « Tous les chevaux » — filtres et colonnes.**

Quatre filtres cumulables ajoutés au-dessus de la liste (`includes/cheval-fields.php`), tous
combinables entre eux ET avec la recherche WordPress native (jamais remplacée) : Catégorie
(taxonomie existante, aucun second système), Statut commercial et Sexe (référentiels métier déjà
définis, jamais une nouvelle nomenclature), Année de naissance (liste construite dynamiquement à
partir des seules années réellement présentes en base — première requête `$wpdb` directe du
module, `SELECT DISTINCT` trié décroissant, jamais une liste arbitraire d'années inutilisées).
Mécanisme : `restrict_manage_posts` pour le rendu des `<select>` (persistance des valeurs
sélectionnées via `selected()`, bouton « Filtrer » ajouté nativement par WordPress dès qu'un
contenu y est produit), `pre_get_posts` pour l'application réelle (tax_query pour la catégorie,
meta_query en relation `AND` pour statut/sexe/année, chaque valeur revalidée contre son référentiel
avant usage — jamais une valeur `$_GET` propagée telle quelle). La pagination WordPress conserve
nativement ces paramètres, aucun code supplémentaire nécessaire.

Colonnes de la liste ramenées à : Nom | Catégories | Sexe | Année | Statut commercial | Prix |
Ordre — colonne native « Date » retirée (peu de valeur dans ce contexte métier). Sexe affiche le
libellé utilisateur (jamais la valeur technique) ; Année affiche uniquement l'année de naissance
brute ou « — » si non renseignée (jamais un âge calculé) ; Prix et Ordre conservent leur
comportement métier existant sans modification.

**Tests** (`tests/gws-equestrian-pedigree-logic-test.php` et
`tests/gws-equestrian-cheval-logic-test.php`) : les 8 scénarios de nettoyage de relation (corbeille
préservée, restauration, suppression définitive du père/de la mère, parent utilisé par plusieurs
chevaux, cheval sans production, branches externes non affectées, absence d'erreur PHP), le câblage
exact du hook (`before_delete_post`, jamais `wp_trash_post`), les quatre filtres individuellement
et combinés, la combinaison recherche + filtres, la persistance de sélection, la déduplication et
le tri décroissant des années, le rejet des valeurs hors référentiel, les nouvelles colonnes
(contenu et cas de données manquantes) et l'absence de la colonne Date. Intégralité des suites
existantes GWS Core et GWS Equestrian revérifiée, aucune régression.

## 0.15.0 — Labels ANSF (nouveau lot, volontairement minimal)

Modèle métier Cheval complété avant de passer au rendu web : un nouvel onglet **Labels** dans la
fiche Cheval, limité volontairement aux labels Selle Français / ANSF identifiés pour la
commercialisation initiale en France. AUCUN moteur générique de distinctions, AUCUN référentiel
multi-stud-books, AUCUNE extensibilité anticipée — un futur label d'un autre organisme est un
nouveau lot à part entière.

**Contenu de l'onglet, dépendant du sexe** :
- **Selle Français Originel (SFO)** — case à cocher, disponible pour femelle, mâle ET hongre,
  jamais restreint par le sexe.
- **Labels poulinières** (Label Sport / Label Élevage / Label Modèle & Allures) — UNIQUEMENT
  femelle, chaque famille est un ENUM fermé à quatre valeurs mutuellement exclusives
  (`none`/`tres_bonne`/`excellente`/`elite`), rendu via un groupe de boutons radio — jamais quatre
  cases à cocher indépendantes qui permettraient une incohérence ("Sport — Élite" ET "Sport — Très
  Bonne" simultanément).
- **Étalon SF Génétique Avenir** — case à cocher, mâle ET hongre (un hongre a pu obtenir ce statut
  ou avoir une carrière de reproducteur avant castration ; sa semence peut encore être
  commercialisée).

**Données structurées, jamais des libellés** (`includes/cheval-labels.php`) : cinq valeurs
techniques stables (`_gwseq_label_sfo`, `_gwseq_label_sf_genetique_avenir` — booléens `'1'`/`''` ;
`_gwseq_label_sport`, `_gwseq_label_elevage`, `_gwseq_label_modele_allures` — enums), choisies pour
qu'une correspondance future vers un pictogramme officiel ANSF (pas encore disponibles) reste
triviale à construire plus tard — aucune fonction de correspondance ni pictogramme temporaire
ajoutés ici, ce sera une évolution séparée.

**Sanitation serveur obligatoire** (`gwseq_sanitize_cheval_labels_input($raw, $sexe)`), SEULE
autorité — jamais une dépendance à l'affichage conditionnel admin, qui n'est qu'un confort de
saisie : un payload délibérément incohérent (ex. labels poulinières soumis pour un mâle) ne peut
jamais produire une donnée incohérente en base.

**Changement de sexe d'un cheval existant** : les labels devenus incompatibles avec le sexe
fraîchement soumis sont nettoyés au prochain enregistrement — passage vers mâle/hongre remet les
trois labels poulinières à `none` ; passage vers femelle remet Étalon SF Génétique Avenir à vide ;
SFO n'est jamais touché, quel que soit le sexe. Un sexe non renseigné nettoie les deux groupes
sexe-dépendants (repli prudent, jamais un label affiché pour un sexe non confirmé).

**Duplication d'un cheval retirée de la roadmap V1** : avec l'import IFCE, son intérêt est devenu
faible et sa maintenance créerait des risques inutiles à mesure que l'objet Cheval s'enrichit —
aucun développement engagé sur ce sujet.

**Tests** (`tests/gws-equestrian-cheval-labels-test.php`, 34 assertions) : sanitation pure pour les
trois sexes et un sexe non renseigné, exclusivité des familles de labels poulinières, payload
délibérément invalide, rendu réel conditionné par le sexe, sauvegarde/rechargement sans interaction
avec l'onglet, modification simultanée d'un autre champ, nettoyage lors d'un changement de sexe
dans les deux sens (SFO systématiquement préservé), sécurité de la sauvegarde (nonce, permissions,
révision). Nouvel onglet enregistré dans `gwseq_cheval_admin_tabs_config()`
(`tests/gws-equestrian-cheval-admin-tabs-test.php` mis à jour en conséquence). Aucune régression sur
l'IFCE/le référentiel Race/le pedigree, tous revérifiés.

## 0.14.6 — Correctif complémentaire : cause racine réelle du bug "Préciser" (soumission sans interaction)

Recette complémentaire de la 0.14.5 : le correctif du bug "Préciser" restait insuffisant — une race
canonique correctement affichée au chargement (ex. "SF"/"Selle Français") réapparaissait avec
"Préciser = Selle Français" après un simple clic sur "Publier"/"Mettre à jour" SANS avoir touché au
champ Race, y compris en ne modifiant qu'un champ sans rapport (robe, Commercialisation, un autre
onglet). Une resélection manuelle via l'autocomplétion corrigeait temporairement l'état — la piste
explicite demandée en recette ("comparer le payload POST réel, pas seulement la sanitation après
réception") a mené directement à la cause.

**Cause exacte.** `assets/race-referentiel-autocomplete.js` initialisait `hasPickedThisSession` à
`false` INCONDITIONNELLEMENT à chaque `initField()` — y compris pour un champ chargé avec une race
DÉJÀ VALIDE (rendue par PHP). Le filet de sécurité de soumission (`commitPendingValue()`, déclenché
sur N'IMPORTE QUEL submit du formulaire, y compris un enregistrement qui ne touche à aucun autre
champ) ne fait rien tant que `hasPickedThisSession` est `true` ; sinon, il traite le libellé
AFFICHÉ dans le champ de recherche comme une saisie libre jamais validée, et réécrit le code caché
en "autre" + recopie ce libellé dans "race_autre". Comme seul un clic explicite sur un résultat
(`selectEntry()`) met `hasPickedThisSession` à `true`, un champ jamais touché depuis le chargement
de la page (par définition le cas le plus courant — enregistrer une fiche sans modifier sa race)
déclenchait ce filet à CHAQUE soumission, alors que rien n'avait changé.

**Correctif minimal** : `hasPickedThisSession` est désormais initialisé à `codeInput.value !== ''`
— un code déjà présent au chargement est une sélection déjà valide, jamais une saisie en attente de
committement. `focus`/`input` continuent, sans aucun changement, à repasser cette valeur à `false`
dès que l'utilisateur touche réellement le champ, pour que le filet de sécurité redevienne actif sur
une véritable nouvelle saisie (comportement "Autre" sur saisie libre jamais validée, Scénario 6,
non régressé).

**Tests** : deux nouveaux scénarios JS reproduisant littéralement "Cas 1" de la demande (champ
chargé avec une race canonique, jamais touché, formulaire soumis directement) et son inverse (champ
chargé avec "autre" + précision libre déjà enregistrée, jamais touché, précision préservée) —
vérifiés positifs contre le correctif et négatifs contre l'ancien code. Nouveau test PHP combinant
un `race_autre` parasité avec la modification simultanée d'un champ sans rapport (robe), pour
prouver que l'invariant serveur déjà en place depuis la 0.14.5 (`gwseq_sanitize_race_referentiel_autre()`)
s'applique quel que soit le contenu du reste du formulaire. Aucun des autres correctifs 0.14.5 n'a
été modifié.

## 0.14.5 — Correctifs post-recette : reconstruction du pedigree IFCE, bug "Préciser" persistant, rattachement Père/Mère GWS pendant l'import

Recette fonctionnelle de la 0.14.4/Core 1.17.4 largement validée en runtime réel (autocomplétion
Race identité et pedigree opérationnelle de bout en bout, persistance, suppression, "Autre",
indices, médias, catégories, commercialisation, import IFCE sans indices/pedigree — voir la demande
pour la liste complète). Trois sujets ciblés traités dans ce lot, sans aucune autre évolution.

**A — Reconstruction incorrecte de certains pedigrees IFCE (bug important, deux documents réels
distincts : Asb Conquistador et Cornet Obolensky).** Cause exacte : `CORRADO I Alias SAN PATRIGNANO
CORRADO` est suivi, dans les deux VRAIS documents, d'une ligne DISTINCTE `(DEU) HOLST 1985` — le
marqueur pays, le code de stud-book ET l'année ont débordé ENSEMBLE sur une seconde ligne. Seule une
ligne composée UNIQUEMENT d'une année isolée était jusqu'ici reconnue comme continuation visuelle
d'un ascendant ; cette ligne ne l'était pas et devenait un ASCENDANT FANTÔME ("HOLST 1985"), décalant
d'un rang la position généalogique de tous les ascendants suivants dans la file — la mère réelle
héritant à tort du rôle de père, etc. `gwseq_ifce_looks_like_pedigree_continuation_line()`
(`ifce-import-parser.php`) reconnaît désormais toute ligne qui se réduit entièrement à un marqueur
pays/code de stud-book/année (avec ou sans année isolée) comme une continuation, jamais un ascendant
distinct. **Tests** : la détection d'un simple décompte du nombre d'ascendants ne suffit pas — un
parser peut trouver le bon nombre tout en les plaçant aux mauvaises positions — nouvelles assertions
structurelles sur l'arbre RÉEL (nom, alias, race, année, position généalogique, père, mère) contre
les deux VRAIS PDF, vérifiées positives contre le nouveau code et négatives contre l'ancien.

**B — Le champ "Préciser" pouvait réapparaître avec une valeur alors qu'une race canonique était
sélectionnée**, notamment après une sauvegarde touchant d'autres onglets. Cause double : (1) le
composant de recherche ne vide jamais lui-même son propre champ "Autre" quand un choix canonique est
re-sélectionné, un texte libre resté dans ce champ caché pouvait donc être soumis ET enregistré ;
`gwseq_sanitize_cheval_identity_input()`/`gwseq_sanitize_external_ancestor_tree()` sanitisaient
`race_autre` indépendamment de `race`. (2) le bloc "Préciser" du `<select>` de secours (0.14.3)
n'avait jusqu'ici AUCUNE condition de visibilité, contrairement à celui du composant de recherche.
Corrigé par une seule fonction partagée `gwseq_sanitize_race_referentiel_autre($race, $raw_autre)`
(`race-referentiel.php`, force une chaîne vide dès que `$race` n'est pas exactement "autre"),
utilisée à l'identique par l'identité et les ascendants externes ; `gwseq_render_race_referentiel_field()`
masque désormais aussi le bloc "Préciser" du `<select>` de secours selon le code courant (avec un
attribut `onchange` en JavaScript PUR, indépendant du script principal, pour rester utilisable même
si celui-ci échoue) et "auto-guérit" à l'affichage une donnée déjà enregistrée avant ce correctif.

**C — Rattacher Père/Mère à des chevaux GWS pendant l'import IFCE (évolution).** L'écran de
prévisualisation propose désormais, pour les deux parents DIRECTS uniquement (jamais les 12
ascendants suivants), un choix entre "Importer comme ascendant externe" (répli par défaut, inchangé),
"Lier à un cheval déjà enregistré" (sélecteur réutilisant les mêmes règles métier que la saisie
manuelle du pedigree — compatibilité de sexe, année strictement antérieure, aucun cheval commun aux
deux rôles) et "Ne pas importer ce parent". `gwseq_ifce_map_import()` relaie cette décision vers
`gwseq_set_horse_parent()` (MÊME fonction que la saisie manuelle, jamais une règle dupliquée), en
traitant systématiquement Père puis Mère — c'est ce qui permet au conflit "même cheval comme père ET
mère" d'être détecté sans le moindre code de validation supplémentaire. Sans effet si "Importer le
pedigree" reste décoché (comportement déjà validé, non régressé). Paramètre entièrement optionnel
(comportement par défaut strictement identique si omis) : aucun appelant existant n'est affecté.

## 0.14.4 — Correctif runtime : cause exacte de l'échec d'initialisation (ul dans un p), champ de recherche opérationnel

Recette du filet de sécurité 0.14.3 : le `<select>` de secours s'affichait bien (garantie tenue),
mais confirmait que le composant de recherche restait non initialisé — malgré des logs montrant
`search=true codeInput=true` mais **`resultsList=false`** au moment précis de `initField()`, avec
`aborting init for this field only`.

**Cause exacte identifiée.** `gwseq_render_race_referentiel_field()` imprime un
`<ul class="gwseq-race-field__results">` (liste de résultats). Les deux appelants
(`cheval-fields.php` pour l'identité, `cheval-pedigree.php` pour chaque génération d'ascendant
externe) enveloppaient cet appel dans un `<p>...</p>`. Or la spécification HTML5 (WHATWG) est
formelle : un `<p>` ne peut contenir aucun élément de contenu "flow" — `<ul>`, `<div>`, `<table>`
et une liste fermée d'une trentaine d'autres — et un NAVIGATEUR RÉEL ferme IMPLICITEMENT le `<p>`
(et tout ce qui est encore ouvert à l'intérieur, y compris les `<span>` du composant) dès qu'il
rencontre l'un de ces éléments, AVANT de le placer. Le `<ul>` de résultats se retrouvait donc
structurellement expulsé hors de `.gwseq-race-field`, devenant un simple frère du `<p>` refermé de
force — exactement ce que révélait `resultsList=false` : `field.querySelector('.gwseq-race-field__results')`
ne trouvait plus rien, puisque l'élément n'était plus un descendant de `field` une fois interprété
par un vrai moteur de rendu.

**Pourquoi ce défaut était invisible jusqu'ici.** Le test d'exécution JS de ce dépôt construit son
DOM simulé programmatiquement via `appendChild()` (jamais en analysant une chaîne HTML) — il ne
peut donc structurellement jamais exercer cette règle de fermeture implicite d'un VRAI parseur HTML,
et restait vert alors que le vrai wp-admin échouait. `DOMDocument`/libxml2 (PHP) ne reproduit pas
non plus fidèlement cette règle précise (vérifié empiriquement : il laisse le `<ul>` imbriqué),
d'où un nouveau test structurel dédié (`gws_test_assert_no_flow_content_inside_p()`) qui rejoue à la
main, sur le HTML source réellement produit par PHP, la règle de fermeture exacte de la
spécification — vérifié qu'il échoue bien contre l'ancien balisage (`<p>`) et passe contre le
nouveau (`<div>`).

**Correctif minimal.** Les deux appels à `gwseq_render_race_referentiel_field()` sont désormais
enveloppés dans un `<div>` (jamais un `<p>`) — aucune modification de la fonction partagée
elle-même, ni du parseur IFCE, ni du référentiel, ni du pedigree, ni de la logique métier. Le
docblock de `gwseq_render_race_referentiel_field()` documente désormais explicitement cette
contrainte pour empêcher toute régression future.

**Ce qui reste à confirmer dans un vrai navigateur** (voir tests/README.md pour le détail) : que le
champ de recherche s'affiche et réagisse réellement à la frappe sur un vrai wp-admin — le correctif
supprime la cause structurelle identifiée avec certitude, mais seul un test réel en conditions de
production peut confirmer le comportement de bout en bout (suggestion, sélection, sauvegarde,
rechargement).

## 0.14.3 — Correctif runtime : régression de la 0.14.2 réintroduite lors de l'instrumentation, filet de sécurité obligatoire, ajustement UX de la prévisualisation IFCE

Recette en conditions réelles du correctif 0.14.2 : les points B et C (extraction IFCE, normalisation
croisée) confirmés fonctionnels en production, mais l'autocomplétion Race restait totalement non
fonctionnelle sur un vrai wp-admin — impossible de modifier une race déjà renseignée (ex.
UNTOUCHABLE 27, KWPN) et impossible d'en saisir une sur une fiche vide (ex. Jamerose), sans aucun
autre contrôle disponible : un bug bloquant du parcours de création/édition. Les logs navigateur
fournis prouvaient que le script se chargeait, s'analysait et s'initialisait intégralement sans
erreur (référentiel de 154 entrées chargé, 15 champs trouvés et initialisés) — écartant tout défaut
de chargement, de syntaxe ou d'initialisation.

**A — Régression : la réécriture de l'instrumentation (préparée pour cette recette) avait
réintroduit EXACTEMENT le défaut corrigé en 0.14.2**, un caractère Unicode combinant LITTÉRAL
multi-octet (U+0300-U+036F) directement dans le code exécutable de `normalize()`, au lieu de
l'échappement ASCII `\u0300-\u036f`. Détecté avant livraison par une vérification systématique des
octets du fichier (`od -c`) plutôt que par une simple lecture — un fichier JS entièrement réécrit ne
garantit jamais par lui-même l'absence de cette classe de risque, une vérification octet-par-octet
reste nécessaire à chaque réécriture complète. Corrigé de nouveau ; confirmé qu'aucun caractère
non-ASCII ne subsiste plus dans le code exécutable (seuls les commentaires en contiennent encore).
Cette régression, propre à la préparation de ce correctif, n'a jamais atteint la version livrée en
0.14.2 elle-même.

**B — La cause exacte de la panne runtime réellement observée par l'utilisateur reste, à ce stade,
non reproduite en environnement de test** malgré une instrumentation exhaustive désormais en place
(dix points de diagnostic couvrant `input → normalisation → recherche → résultats → rendu DOM →
visibilité → sélection → synchronisation du code caché`, voir le docblock de
`assets/race-referentiel-autocomplete.js`) et un `try`/`catch` dédié autour de CHAQUE gestionnaire
d'événement (focus, saisie, perte de focus, clavier, clic sur un résultat, soumission) — une
exception survenant pendant une interaction réelle serait désormais visible dans la console
(`[gwseq-race] ... exception in ... handler:`) plutôt que silencieusement avalée par le navigateur.
Une valeur du référentiel n'explique structurellement pas le symptôme : `config.suggestions` (le
`5` observé dans les logs) est UNIQUEMENT le repli affiché champ vide au focus (valeurs récentes de
l'utilisateur) — toute saisie non vide comme "old" recherche TOUJOURS dans `config.entries` (les 154
entrées complètes), jamais dans `config.suggestions` ; ce point est désormais tracé explicitement
dans les logs pour le démontrer sur le runtime réel.

**C — Filet de sécurité obligatoire, indépendant de la résolution de B.** Une donnée métier
essentielle comme la race ne doit jamais devenir impossible à saisir, que le composant
JavaScript fonctionne ou non. `gwseq_render_race_referentiel_field()` rend désormais TOUJOURS, en
plus du composant de recherche, un `<select>` natif complet portant le VRAI nom de champ soumis par
défaut — fonctionnel sans JavaScript. `activateField()` ne désactive ce `<select>` (et ne transfère
le nom réel vers le composant de recherche) qu'à la TOUTE FIN d'une initialisation ayant réussi sans
la moindre exception ; si le script échoue à se charger, à s'analyser, ou lève une erreur n'importe
où avant ce point précis, le `<select>` reste le SEUL contrôle actif, visible et réellement soumis —
pour l'identité du cheval comme pour chaque génération d'ascendant externe du pedigree (même
composant partagé). Le gestionnaire de suppression d'un ascendant externe (`cheval-admin.js`) a été
mis à jour pour réinitialiser également ce `<select>` de secours.

**D — Ajustement UX de la prévisualisation IFCE (purement l'affichage, ni le parseur ni les données
ne sont modifiés).** Le résumé d'identité détectée était affiché sur une seule ligne concaténée par
des virgules (ex. "KWPN, Mâle, Gris, non détectée, 2001"), rendant ambigu à quoi un "non détectée"
isolé se rapportait. Remplacé par des lignes explicitement étiquetées : Race / Stud-book, Sexe,
Robe, Taille, Année de naissance — mêmes valeurs déjà calculées, mise en forme uniquement.

**Tests** : nouveaux scénarios dans le test d'exécution JS de l'autocomplétion (état par défaut du
`<select>` de secours avant toute exécution JS, désactivation uniquement après succès complet de
l'initialisation, maintien actif si l'initialisation échoue, vérification détaillée des dix points
d'instrumentation demandés) ; nouvelles assertions dans le test d'import IFCE vérifiant les lignes
étiquetées de la prévisualisation et l'absence de l'ancien résumé concaténé. Aucune régression sur
la suite existante (alias IFCE, années de naissance, reconnaissance Quaprice Bois Margot,
normalisation KWPN/BWP, limite à 3 générations de pedigree — tous revérifiés).

## 0.14.2 — Correctif runtime : cause racine réelle de l'autocomplétion, robustesse de l'extraction IFCE

Recette sur cinq nouvelles fiches IFCE réelles (Iowa Jal, Untouchable 27, Asb Conquistador, Cornet
Obolensky, Quaprice Bois Margot) après le correctif 0.14.1 : l'autocomplétion restait non
fonctionnelle sur un vrai wp-admin malgré un test Node vert, deux fiches perdaient leur année de
naissance malgré sa présence explicite dans le document, et une fiche réelle (Quaprice Bois Margot)
était intégralement rejetée à l'analyse.

**A — Cause racine réelle de l'autocomplétion (le test simulé ne pouvait pas la révéler).** Le
fichier `assets/race-referentiel-autocomplete.js` contenait un caractère Unicode LITTÉRAL
multi-octet directement dans le code exécutable d'une expression régulière (plage de diacritiques
combinants U+0300-U+036F, écrite en clair dans le fichier source plutôt qu'en échappement `\u`).
Un tel caractère dépend d'un encodage/transfert fidèle en UTF-8 à CHAQUE maillon (hébergement, CDN,
extraction d'archive...) ; corrompu par n'importe lequel d'entre eux, il produit une ERREUR DE
SYNTAXE qui empêche le navigateur de parser le fichier — tuant silencieusement TOUT le script, sans
qu'aucune exécution directe (Node, le test simulé qui lit toujours le texte source fidèlement) ne
puisse jamais le révéler. Remplacé par l'échappement ASCII `\u0300-\u036f`, strictement équivalent
mais structurellement insensible à ce risque — vérifié qu'aucun caractère non-ASCII ne subsiste plus
dans le code exécutable du fichier (seuls les commentaires, jamais exécutés, en contiennent encore).
Une instrumentation de diagnostic TEMPORAIRE (préfixe console `[gwseq-race]`, quelques lignes à
faible volume aux étapes-clés d'initialisation) a été ajoutée pour permettre, si le problème
persistait malgré ce correctif, de confirmer directement depuis un vrai navigateur l'étape exacte où
l'exécution diverge — à retirer une fois le composant confirmé fonctionnel en conditions réelles.

**B — Extraction de l'identité non robuste au nombre variable de segments.** La ligne d'identité
IFCE ("Race, Sexe, Robe, Taille, né(e) en AAAA[, étalon]") N'A PAS un nombre de segments fixe sur
toutes les fiches réelles : Robe ET Taille sont chacune FACULTATIVES indépendamment. Une position de
segment figée perdait l'année sur "Kon. Warm Paard Nederland, Mâle, Gris, né(e) en 2001, étalon"
(Untouchable 27) et "Belgian Warmblood, Mâle, Bai, né(e) en 2001, étalon" (Asb Conquistador) — taille
absente, la position attendue de l'année pointait alors sur "étalon". Une fiche à seulement 3
segments ("Holsteiner Warmblut, Mâle, né(e) en 1998", Quaprice Bois Margot — ni robe ni taille)
n'était même pas reconnue comme une ligne d'identité valide, rejetant le document dans son
intégralité. Corrigé par une détection dynamique : la position RÉELLE du jeton Sexe est repérée
(jamais figée), et un segment qui ressemble déjà à la mention "né(e) en AAAA" n'est jamais confondu
avec une robe — la Robe et la Taille sont désormais chacune correctement détectées, qu'elles soient
présentes ou non, quelle que soit leur position réelle.

**C — Normalisation croisée obligatoire d'une race/stud-book (cas Untouchable 27).** Le même
stud-book pouvait produire deux valeurs stockées différentes selon qu'il était rencontré comme
libellé long dans l'identité ("Kon. Warm Paard Nederland") ou comme code court dans le pedigree
("KWPN"). Alias ajoutés au référentiel pour les variantes IFCE réellement rencontrées : KWPN
("Kon. Warm Paard Nederland"), BWP ("Belgian Warmblood"), HAN ("Hannoveraner"), SF ("Selle Français
Section A"), OE ("Origine étrangère selle") — HOLST et OLD résolvaient déjà correctement via leurs
champs `ifce` existants. Un seul code canonique est désormais garanti quel que soit le chemin
d'entrée (identité ou pedigree), vérifié par des tests croisés dédiés.

**Tests** : cinq nouvelles fixtures PDF réelles ajoutées (`tests/fixtures/ifce-quaprice-bois-margot.pdf`,
`ifce-iowa-jal.pdf`, `ifce-untouchable-27.pdf`, `ifce-asb-conquistador.pdf`,
`ifce-cornet-obolensky.pdf`), exécutées à travers le pipeline complet réel ; nouveau scénario 8 dans
le test d'exécution JS validant l'instrumentation de diagnostic ; nouveaux tests de normalisation
croisée dans `gws-equestrian-race-referentiel-test.php`. Aucune régression sur la suite existante.

## 0.14.1 — Correctif runtime : autocomplétion Race inutilisable en édition, alias/code pays IFCE

Recette du référentiel 0.14.0 : le référentiel métier (mapping IFCE, pedigree sur 3 générations)
fonctionnait, mais deux défauts distincts rendaient l'édition manuelle de la Race/Stud-book/
Appellation inutilisable, et le nommage d'un cheval/ascendant portant un alias IFCE était incorrect.

**A — Autocomplétion Race inutilisable en édition (bug bloquant).** Sur une fiche déjà importée
(ex. race "Selle Français"), taper "OLD" n'affichait aucune suggestion, et enregistrer restaurait
l'ancienne valeur — impossible de modifier ou de vider le champ. DEUX causes racines dans
`assets/race-referentiel-autocomplete.js`, corrigées ensemble :
1. Le champ ne sélectionnait jamais son texte existant au focus : reprendre l'édition d'un champ
   déjà rempli concaténait la frappe à la valeur affichée ("Selle FrançaisOLD") au lieu de la
   remplacer — une chaîne qui ne correspond à RIEN du référentiel, d'où l'absence de suggestion.
   Corrigé par une sélection intégrale du texte au focus (`search.select()`).
2. La mise à jour du code caché après une saisie libre non validée par un clic était différée de
   150 ms après `blur` — largement plus long que le délai entre ce `blur` et la soumission native du
   formulaire déclenchée par un clic sur "Enregistrer"/"Publier". Le formulaire partait alors avec
   l'ANCIEN code, jamais mis à jour. Un `mousedown` avec `preventDefault()` sur un résultat empêche
   déjà nativement `blur` de se déclencher lors d'un clic sur ce résultat — le délai n'avait donc
   plus aucune raison d'exister : la mise à jour est désormais SYNCHRONE sur `blur`, complétée par un
   filet de sécurité committant chaque champ Race à la soumission du formulaire (couvre tout
   enchaînement où `blur` n'aurait pas eu l'occasion de se déclencher), et par une touche Entrée qui
   ne soumet plus jamais le formulaire par accident (elle valide le premier résultat affiché, ou
   committe la saisie libre exactement comme une perte de focus). Une boucle d'initialisation
   `try`/`catch` par champ empêche en plus un champ malformé de compromettre les autres champs Race
   de la même page.

**B — Nom officiel, alias et code pays IFCE.** Un cheval ou un ascendant portant un alias IFCE
("NOM_OFFICIEL Alias NOM_D'USAGE") voyait auparavant l'alias intégralement supprimé, ne conservant
que le nom officiel — comportement INVERSÉ par ce correctif : c'est désormais le nom d'usage/alias
qui devient le nom retenu (jamais le mot littéral "Alias", jamais le seul nom officiel qui perdrait
le nom réellement utilisé dans le sport), le nom officiel restant disponible séparément et jamais
perdu (nouvelle fonction métier `gwseq_set_cheval_ifce_nom_officiel()`, meta technique
`_gwseq_ifce_nom_officiel`, jamais exposée dans le formulaire manuel). Un marqueur pays IFCE entre
parenthèses ("(NLD)", "(BEL)", "(DEU)"...) est désormais retiré du nom via une liste FERMÉE de codes
ISO 3166-1 alpha-3 (`gwseq_ifce_country_codes()`) — jamais une suppression aveugle de toute
parenthèse. Chiffres romains et suffixes courts (ex. "CARTHAGO Z") restent conservés, jamais
confondus avec un stud-book.

**Tests** : nouveau fichier
`tests/gws-equestrian-race-referentiel-autocomplete-runtime-test.js` — exécute RÉELLEMENT
`race-referentiel-autocomplete.js` (module `vm` de Node, DOM minimal fait main, même méthodologie
que `gws-equestrian-cheval-admin-tabs-runtime-test.js`, aucune dépendance npm) ; vérifié positivement
contre l'ancienne version du script (fait bien échouer les scénarios concernés). `ifce-import-parser.php`
mis à jour et testé sur les quatre exemples réels exacts de la demande (Untouchable, Bush vd
Heffinck, Windows vh Costersveld, What A Quickstar R) et sur le pedigree Jamerose. Aucune régression
sur la suite existante.

## 0.14.0 — Référentiel Race / Stud-book / Appellation, ascendant + année de naissance, pedigree sur 3 générations

Refonte complète de la gestion de la race/du stud-book/de l'appellation du cheval, à partir du
référentiel `GWS_referentiel_races_appellations_IFCE.xlsx` fourni : dissociation de la richesse
technique du référentiel (154 entrées) et de la simplicité de l'interface (un champ de recherche,
jamais un `<select>` de plus de 100 valeurs).

**Nouveau fichier `includes/race-referentiel.php`** — source de vérité UNIQUE, découplée de toute
UI, réutilisable à l'identique par l'admin, le parseur IFCE, et un futur import CSV/API :
- 154 entrées (151 races/stud-books + 3 appellations OC/ONC/OE), chacune `{code, ifce, gws, type,
  alias, usage}` — le `code` canonique (ex. `SF`, `KWPN`, `OC`) est TOUJOURS la donnée structurée
  stockée en base, jamais un libellé.
- Helpers conceptuels dédiés, jamais de logique dupliquée ailleurs : lecture par code
  (`gwseq_race_referentiel_get()`), résolution exacte d'un alias/libellé vers le code canonique
  (`gwseq_race_referentiel_resolve_alias()` — ex. l'alias historique/import "SFA" résout vers "SF",
  jamais rangé dans "Autre"), recherche partielle pour l'autocomplétion
  (`gwseq_race_referentiel_search()` — code, libellé IFCE/GWS, alias, accents/casse ignorés,
  préfixe classé en tête), sanitation d'un code brut vers le code canonique
  (`gwseq_sanitize_race_referentiel_code()`), libellé d'affichage et type race/appellation.
- Distinction technique `type = race`/`appellation` conservée mais jamais exposée comme deux champs
  séparés côté utilisateur — races et appellations (OC, ONC, OE) partagent le MÊME moteur de
  recherche, sous un unique libellé « Race / Stud-book / Appellation ».
- Récents/suggestions par utilisateur (`_gwseq_race_recent_codes`, user meta) : à l'ouverture d'un
  champ vide, un éleveur voit ses 5 à 10 valeurs récemment utilisées plutôt que les 154 entrées —
  jamais un profil métier rigide CSO/dressage/poney codé en dur, les récents s'adaptent naturellement
  à l'usage réel. Repli neutre (champ "usage" du référentiel source) tant qu'aucun historique
  n'existe. Enregistrés UNIQUEMENT depuis la glue de sauvegarde des formulaires (identité, pedigree),
  jamais depuis les fonctions métier pures — un import ne doit jamais compter comme un choix manuel.
  Préférence propre à l'utilisateur, ne modifie jamais la donnée Cheval.
- "Autre — préciser" reste le seul filet de sécurité quand rien ne correspond — un code déjà connu
  du référentiel n'est plus jamais rangé dedans.

**Nouveau composant partagé** (`assets/race-referentiel-autocomplete.js` +
`.css`, rendu via `gwseq_render_race_referentiel_field()`) : remplace l'ancien `<select>` d'une
vingtaine de races codées en dur, à la fois pour l'identité du cheval (`cheval-fields.php`) ET
chaque génération d'ascendant externe (`cheval-pedigree.php`) — un seul composant, un seul
référentiel, aucune liste divergente dans le module. Recherche par code IFCE, libellé IFCE, libellé
GWS ou alias (ex. "sel"/"sf" -> Selle Français, "kwp" -> KWPN, "old" -> Oldenburg, "conn" ->
Connemara/Connemara Part-Bred, "oc" -> Origines Constatées) ; utilisable sans connaître les codes
IFCE (taper "Oldenburg" fonctionne aussi bien que taper "OLD"). Import IFCE mis à jour pour utiliser
ce même référentiel (voir 0.13.x, mapping race).

**Ascendant externe — année de naissance** : le modèle `{name, race, race_autre, father, mother}`
devient `{name, race, race_autre, annee_naissance, father, mother}` — champ numérique optionnel,
jamais requis, jamais utilisé pour calculer ou stocker un âge. L'import IFCE l'alimente
automatiquement quand la fiche porte l'information (même token `\d{4}` déjà utilisé pour délimiter
la fin du nom d'un ascendant).

**Pedigree : profondeur standard 4 -> 3 générations** (14 ascendants, alignée sur la fiche de
synthèse IFCE) : `GWSEQ_PEDIGREE_MAX_DEPTH` passe de 4 à 3 — sanitation, resolver, aperçu
développeur et compteur « Génération N sur X » suivent tous ce changement symboliquement, sans
valeur codée en dur ailleurs. Aucune génération 4 supplémentaire ne peut plus être proposée depuis
l'interface. **Compatibilité non destructive** : une donnée de génération 4 déjà enregistrée lors de
recettes précédentes n'est jamais supprimée — `gwseq_sanitize_external_ancestor_tree()` relit et
préserve intacte la sous-branche existante tant que l'ascendant de génération 3 concerné n'a pas
changé de nom ; le resolver et le rendu standard s'arrêtent simplement à la génération 3 sans jamais
l'interroger ni l'afficher.

**IFCE import — reconnaissance améliorée** : le mapping de race d'un ascendant utilise désormais le
référentiel complet (154 entrées + alias) au lieu de l'ancienne liste d'une vingtaine de races —
vérifié sur le vrai pedigree Jamerose de Félines, où l'alias "SFA" (Hors La Loi II) et l'alias "OES"
(Chablis) sont désormais reconnus et résolus à leur code canonique, jamais rangés dans "Autre".

**Tests** : nouveau fichier `tests/gws-equestrian-race-referentiel-test.php` (référentiel : entrées,
résolution d'alias dont SFA->SF, recherche partielle, accents/casse, "Autre" toujours disponible,
récents/suggestions) ; `tests/gws-equestrian-cheval-logic-test.php`,
`tests/gws-equestrian-pedigree-logic-test.php` et `tests/gws-equestrian-ifce-import-test.php` mis à
jour pour la profondeur 3 générations, les codes canoniques et l'année de naissance, avec un nouveau
bloc dédié à la compatibilité non destructive de la génération 4 déjà enregistrée. Aucune régression
sur la suite existante.

## 0.13.2 — Correctif bloquant : « headers already sent » à l'analyse du PDF IFCE

Le lancement réel de l'analyse du PDF IFCE (0.13.1) échouait avec `Warning: Cannot modify header
information - headers already sent by ... wp-admin/menu-header.php`, sans jamais atteindre l'écran
de prévisualisation.

**Diagnostic** : le traitement des deux formulaires (upload, confirmation) était exécuté
DIRECTEMENT depuis le callback de la page d'administration
(`gwseq_render_ifce_import_page()`, enregistré via `add_submenu_page()`). Or WordPress n'appelle ce
callback que depuis l'INTÉRIEUR du rendu complet de l'écran — après que
`wp-admin/admin-header.php`/`menu-header.php` ont déjà émis le `<html>` et le HTML du menu
d'administration. Un `wp_safe_redirect()` déclenché à ce stade échoue systématiquement, et le
script continuait silencieusement son exécution sans jamais atteindre la prévisualisation.

**Correctif** (architecture, pas un simple contournement) : le traitement des deux formulaires est
désormais confié aux hooks natifs `admin_post_{action}` de WordPress
(`admin_post_gwseq_ifce_import_upload`/`admin_post_gwseq_ifce_import_confirm`), déclenchés depuis
`wp-admin/admin-post.php` — un point d'entrée dédié qui ne rend JAMAIS de HTML avant de déclencher
le hook, garantissant qu'aucune sortie ne précède la redirection. Le callback de page
(`gwseq_render_ifce_import_page()`) ne traite plus JAMAIS de `$_POST` ni ne redirige lui-même : il
se contente désormais de lire l'état déjà déterminé (jeton de prévisualisation en GET, message
d'erreur éventuel déposé dans un transient scopé à l'utilisateur par le gestionnaire) et de
l'afficher. Les deux formulaires soumettent désormais vers `admin-post.php` avec un champ caché
`action` (`gwseq_ifce_import_upload`/`gwseq_ifce_import_confirm`), le mécanisme natif que WordPress
utilise pour router vers le bon hook.

La logique métier de chaque étape (extraction/analyse/transient pour l'upload ; relecture du
transient puis création de la fiche et mapping pour la confirmation) a été extraite dans deux
fonctions PURES (`gwseq_process_ifce_import_upload()`/`gwseq_process_ifce_import_confirm()`) qui ne
rendent jamais de HTML et n'appellent jamais `wp_safe_redirect()`/`exit` elles-mêmes — elles
retournent simplement `{redirect, notice}`, laissant les gestionnaires `admin_post_*` (fine couche
de glue HTTP) se charger de la redirection réelle. Cette séparation rend le chemin réel directement
testable par appel direct, sans jamais avoir à exécuter une redirection dans un test.

**Aucun warning masqué** : aucune bufferisation artificielle de sortie, aucun `@` sur
`wp_safe_redirect()`, aucune désactivation d'avertissement — le cycle de requête lui-même a été
corrigé en utilisant le point d'entrée WordPress approprié pour un traitement suivi d'une
redirection.

**Tests** : nouvelle suite de vérifications dans `tests/gws-equestrian-ifce-import-test.php`
exécutant le chemin réel via les fonctions pures (avec le vrai PDF de Jamerose de Félines) —
absence de toute sortie avant redirection (capture de tampon), création réelle du transient de
prévisualisation, URL de redirection calculée, aucune écriture métier (fiche/meta) avant
confirmation explicite, jeton expiré/inexistant refusé proprement, et nonce/capability invalides
refusés avant tout traitement — complétées par des vérifications déclaratives (hooks `admin_post_*`
bien enregistrés, callback de page ne contenant plus ni `$_POST` ni `wp_safe_redirect`, formulaires
soumettant bien vers `admin-post.php`).

## 0.13.1 — Recette runtime Étape 7 : vrai PDF IFCE compatible, choix "Ajouter un cheval", verrouillage Photo principale

Trois correctifs consolidés suite à la première recette runtime de l'import IFCE et de
l'intégration de la Photo principale (0.12.5/0.12.6) — livraison unique, aucune nouvelle étape
commencée.

### A. Compatibilité avec le vrai PDF IFCE (bug bloquant)

Le vrai PDF de synthèse de Jamerose de Félines, téléchargé depuis Info Chevaux, était rejeté
(« Ce document n'a pas été reconnu comme une fiche de synthèse IFCE »). Diagnostic complet effectué
AVANT tout correctif (voir `ifce-pdf-text.php` pour le détail exhaustif) :

1. **Objets compressés (`/Type/ObjStm`)** : la quasi-totalité des dictionnaires structurels de ce
   PDF (généré par iText 2.1.7/BIRT) — pages, ressources, dictionnaires de police — sont stockés
   dans un flux d'objets compressé (PDF 1.5+), jamais comme objets classiques directement visibles.
   L'ancien extracteur ne lisait que les objets classiques : il ne pouvait donc jamais découvrir la
   police utilisée par un texte donné, ni sa table `/ToUnicode`.
2. **Police composite CID (`/Type0`, encodage `/Identity-H`)** : le corps de la fiche utilise une
   police "Marianne" intégrée et sous-jeu — chaque code affiché est un identifiant de glyphe interne
   au sous-jeu, pas un octet ASCII/Latin-1. Sans application de la table `/ToUnicode` associée
   (qui existe bien dans ce PDF), l'ancien extracteur recopiait ces codes tels quels, produisant du
   texte sans rapport avec le contenu réel — jamais "IFCE", jamais "Femelle"...
3. **Absence de `Td`/`TD`/`T*`** : ce générateur positionne chaque fragment de texte par une matrice
   absolue (`cm`), jamais par les opérateurs que l'ancienne reconstruction de ligne traitait comme
   des sauts de ligne.

**Conclusion du diagnostic** : l'extracteur PHP minimal initial n'était objectivement pas capable de
traiter ce type de PDF — pas un problème de reconnaissance trop stricte côté parseur. Correctif
retenu, PAS un assouplissement de la reconnaissance :

- `ifce-pdf-text.php` réécrit : index d'objets couvrant les objets classiques ET compressés
  (`/Type/ObjStm`), résolution des polices utilisées par chaque page (`/Type0`+`/ToUnicode` avec
  parsing du CMap `beginbfchar`/`beginbfrange`, ou police simple via une table WinAnsiEncoding
  standard), reconstruction de ligne par changement de coordonnée Y plutôt que par `Td`/`TD`/`T*`.
  Repli automatique sur l'ancien comportement si aucune page exploitable n'est trouvée (PDF minimal
  sans arbre de pages complet, cas des tests). Seule la PREMIÈRE page est décodée (§3 de la demande
  initiale) — choix délibéré et non plus seulement une limite de commodité : les pages suivantes du
  vrai document contiennent le détail de production de chaque ascendant, avec ses propres indices
  ISO/BSO pour d'autres chevaux, qui contamineraient sinon les indices de la fiche importée.
- `ifce-import-parser.php` : la convention de lecture de l'identité s'est révélée exacte une fois le
  texte correctement extrait (aucun changement nécessaire). Le pedigree, en revanche, a nécessité
  trois ajustements constatés sur le vrai document : une mention "Alias ..." (nom d'enregistrement
  alternatif) est désormais retirée ; une ligne composée uniquement d'une année à 4 chiffres est
  reconnue comme la continuation visuelle de la ligne précédente (ascendant dont le libellé a
  débordé sur deux lignes) ; le bloc d'ascendants s'arrête désormais à la première ligne vide
  rencontrée après le premier ascendant, plutôt que de ramasser aveuglément les 14 prochaines
  lignes non vides (qui débordait sur la section "Production" détaillée, hors périmètre V1). La
  reconnaissance du code de stud-book d'un ascendant écarte explicitement les chiffres romains
  isolés (I à X) en fin de ligne — un nom de cheval s'y termine très souvent ("HORS LA LOI II"),
  qui aurait sinon été à tort amputé et classé comme stud-book. Les indices ne retiennent désormais
  que leur PREMIÈRE occurrence dans le texte (jamais la dernière) — un document réel répète le même
  sigle d'indice pour un ascendant plus loin dans le texte, qui ne doit jamais écraser silencieusement
  l'indice du cheval importé lui-même.
- **Résultat sur le vrai PDF de Jamerose** : identité, indices (ISO 115/CD 0.70/2023, BSO +12/CD
  0.59) et les 14 ascendants du pedigree (arbre exact, race de stud-book mappée quand canonique,
  sinon "Autre" + texte d'origine) sont désormais tous extraits et reconnus correctement.
- **Limites résiduelles assumées** (voir `ifce-pdf-text.php`/`README.md` pour le détail) : un seul
  niveau de flux d'objets compressés résolu ; `/Resources` hérité d'un ancêtre `/Pages` non résolu
  (chaque page du document réel testé porte directement les siens) ; SIRE/UELN restent vides sur
  cette fiche réelle (non présents dans sa zone exploitée).
- **Tests** : `tests/fixtures/ifce-jamerose-de-felines.pdf` (le VRAI PDF) est désormais la fixture de
  référence pour la reconnaissance/l'analyse — un texte pré-extrait artificiellement n'est plus
  considéré comme suffisant. Le test appelle exactement le même pipeline que le runtime WordPress.

### B. Écran de choix "Ajouter un cheval" (import IFCE trop secondaire)

Un simple bandeau d'information sur le formulaire manuel reléguait l'import IFCE au second plan.
Toute requête vers l'écran natif "Ajouter un cheval" est désormais interceptée AVANT l'affichage du
formulaire manuel et redirigée vers un écran de choix dédié présentant les deux chemins — Importer
depuis l'IFCE / Créer manuellement — à égalité. Le formulaire manuel n'est atteint qu'après un clic
explicite sur "Créer manuellement" (paramètre `gwseq_manual=1`, neutralise la redirection pour cette
seule requête, jamais persisté). L'aide "Où trouver cette fiche ?" reste inchangée sur l'écran
d'import lui-même. La création manuelle reste entièrement disponible et fonctionnelle.

### C. Verrouillage de la Photo principale dans Médias (bug bloquant)

La recette a révélé que le contrôle natif "Descendre" de `#postimagediv` (resté visible après son
intégration réelle dans Médias, 0.12.5) faisait disparaître la boîte de l'onglet une fois cliqué —
WordPress réordonnant en présumant des frères et sœurs qui sont eux-mêmes des metaboxes de premier
niveau, hypothèse qui ne tient plus une fois la boîte imbriquée dans Médias — et pouvait persister un
ordre/une visibilité incohérents pour les prochains chargements.

- `assets/cheval-tabs-admin.js` : la classe `gwseq-cheval-media__locked` est posée sur
  `#postimagediv` lors de son déplacement dans Médias, retirée par le filet de sécurité n°2 en même
  temps que sa restauration à la position native.
- `assets/cheval-tabs.css` : cette classe masque les trois contrôles interactifs devenus obsolètes
  une fois la boîte fixée dans Médias (Monter/Descendre/Replier) — un bouton non affiché ne peut
  plus être cliqué ni atteint au clavier. Le glisser-déposer natif (jQuery UI Sortable) était déjà
  structurellement impossible (il n'agit que sur les enfants directs de
  #normal-sortables/#side-sortables, que `#postimagediv` n'est plus une fois déplacé) — aucune règle
  supplémentaire n'était nécessaire pour l'empêcher.
- `includes/cheval-media.php` : `gwseq_cleanup_legacy_postimagediv_metabox_user_state()` (même
  mécanisme que le nettoyage Identité de l'Étape 6) répare l'état déjà corrompu par le contrôle
  natif utilisé pendant la recette — retire `postimagediv` de `metaboxhidden_{$screen}` et de TOUS
  les contextes de `meta-box-order_{$screen}` où il apparaîtrait, sans jamais toucher aux autres
  préférences de l'utilisateur ni demander de passer par Options de l'écran.
- Aucun nouveau champ, aucun nouvel attachment ID : la Featured Image native reste l'unique source
  de vérité, seuls la présentation et le comportement d'interaction de la vraie boîte changent.
  Sans JavaScript, la Photo principale reste utilisable normalement dans sa colonne native.
- **Tests** : nouvelles assertions dans `gws-equestrian-cheval-admin-tabs-runtime-test.js` (pose et
  retrait réels de la classe de verrouillage) et `gws-equestrian-cheval-media-logic-test.php`
  (nettoyage de l'état utilisateur hérité, y compris le cas signalé en recette d'une dérive jusque
  dans le contexte "normal").

## 0.13.0 — Étape 7 : premier import intelligent depuis une fiche de synthèse IFCE (PDF)

Nouvelle fonctionnalité (pas un correctif) : un second chemin de création d'une fiche Cheval,
« Importer une fiche IFCE », en complément — jamais en remplacement — de la création manuelle
existante. Objectif : supprimer la ressaisie manuelle, en particulier pour le pedigree.

**Parcours utilisateur** : téléversement du PDF complet tel que téléchargé depuis Info Chevaux ->
analyse -> écran de prévisualisation obligatoire (« Cheval reconnu : ... / Identité détectée : ... /
Indices détectés : ... / Pedigree : N ascendants détectés ») avec case à cocher indépendante par
section (Identité/Indices/Pedigree, import partiel) -> validation explicite -> SEULEMENT à ce
moment, création de la fiche et écriture des données. **Aucun import silencieux** : un document non
reconnu comme fiche IFCE n'écrit strictement rien et affiche un message explicite, la création
manuelle restant toujours disponible.

**Architecture (4 nouveaux fichiers, aucune modification du parcours manuel existant)** :
- `includes/ifce-pdf-text.php` — extracteur PDF minimal en PHP pur (aucune dépendance
  Composer/npm, aucun accès réseau disponible pour en installer une) : localisation des blocs
  `stream...endstream`, décompression `/FlateDecode` via `gzuncompress()` (zlib natif PHP), lecture
  des opérateurs de dessin de texte (`Tj`/`TJ`, `Td`/`TD`/`T*` traités comme sauts de ligne) avec
  décodage des échappements de chaîne PDF.
- `includes/ifce-import-parser.php` — reconnaissance du document (marqueur d'en-tête IFCE/Info
  Chevaux ET ligne d'identité valide, tous deux exigés) puis extraction vers une structure
  normalisée fermée `{valid, identity, indices, pedigree}` — jamais un accès à `$_POST` ni aux
  fonctions métier, uniquement de l'interprétation de texte.
- `includes/ifce-import-mapper.php` — convertit la structure normalisée vers les MÊMES fonctions
  métier que la saisie manuelle admin (`gwseq_set_cheval_identity()` — nouvelle extraction, voir
  plus bas —, `gwseq_set_cheval_sport_indice()`, `gwseq_set_cheval_genetic_indice()`,
  `gwseq_set_horse_parent()`) : **jamais un accès direct à `update_post_meta()`**, un futur import
  CSV/API/autre fournisseur pourra réutiliser ces mêmes fonctions sans aucune modification.
- `includes/ifce-import-admin.php` — écran d'administration (sous-menu du CPT Cheval, capacité
  `edit_posts`) : validation de sécurité du fichier (type MIME réel via `finfo`, extension `.pdf`,
  taille maximale 15 Mo, provenance réelle du téléversement), stockage de la structure analysée dans
  un TRANSIENT WordPress (15 minutes, jamais dans une meta à ce stade), écran de prévisualisation,
  puis écriture différée jusqu'à confirmation explicite. Suppression immédiate du fichier PDF
  temporaire après extraction du texte, que l'analyse réussisse ou non — **le PDF n'est jamais
  conservé après l'import**, ce n'est qu'une source, jamais une nouvelle source de vérité stockée
  sur la fiche.

**Extension du modèle de données (§5 de la demande)** : ISO/ICC/IDR (`includes/cheval-indices.php`)
stockent désormais également un coefficient de détermination (CD, `_gwseq_{cle}_cd`) — jusqu'ici
réservé aux indices génétiques BSO/BCC/BDR — car une fiche IFCE officielle le fournit
systématiquement pour ces trois indices (exemple exact : « ISO 115 (0.70) (2023) »). Même mécanisme
de sanitation/persistance/lecture/rendu que le CD déjà existant pour les indices génétiques,
facultatif pour la saisie manuelle. Toutes les assertions d'égalité stricte de tableau préexistantes
dans `tests/gws-equestrian-cheval-indices-logic-test.php` ont été mises à jour pour inclure cette
nouvelle clé.

**Nouvelle fonction métier pure** : `gwseq_set_cheval_identity($post_id, $raw)` extraite de
`gwseq_save_cheval_meta()` (`includes/cheval-fields.php`) — pure extraction sans changement de
comportement pour le formulaire manuel existant (toute la suite de tests reste verte à l'identique)
— nécessaire pour que l'import IFCE réutilise exactement la même fonction que la saisie manuelle
pour l'Identité (§7 de la demande), à l'image de ce qui existait déjà pour le pedigree et les
indices.

**Pedigree (objectif principal de la demande)** : reconstruction automatique de l'arbre Père/Mère
sur 3 générations (14 ascendants) à partir du tableau généalogique du PDF, réutilisant directement
`gwseq_sanitize_external_ancestor_tree()`/`gwseq_set_horse_parent()`/
`gwseq_match_race_to_canonical_code()` déjà existants (Étape 5) — validé exactement contre l'exemple
Jamerose de Félines fourni dans la demande. **Ascendants toujours importés en mode "externe"** :
aucune fiche `gwseq_cheval` n'est jamais créée automatiquement pour un ascendant, aucune tentative
de rapprochement/déduplication par nom avec une fiche GWS existante (§8).

**LIMITATION MAJEURE ASSUMÉE ET DOCUMENTÉE** : faute d'accès réseau pour télécharger un exemplaire,
**cet import n'a pu être validé contre AUCUN PDF IFCE réel**. L'extracteur PDF est testé contre un
PDF minimal auto-généré ; le parseur est testé contre une fixture TEXTE reproduisant fidèlement
l'exemple fourni dans la demande. En particulier, l'extracteur ne décode aucune table d'encodage de
police (WinAnsiEncoding, Identity-H/CID) — des caractères accentués d'un PDF réel pourraient être
mal extraits — et la convention de lecture assumée pour repérer la ligne d'identité et le tableau de
pedigree n'a pas pu être confrontée à la mise en page réelle d'une fiche IFCE. C'est précisément
pour cette raison que la prévisualisation obligatoire avant écriture reste la garantie réelle contre
une donnée mal interprétée. Voir `tests/README.md` pour le détail complet de cette limitation.

**Tests** : nouveau fichier `tests/gws-equestrian-ifce-import-test.php` (voir `tests/README.md`)
couvrant l'extraction PDF, la reconnaissance/l'analyse (identité, indices avec CD, reconstruction
exacte du pedigree Jamerose), le mapping (import complet, partiel, structure invalide refusée,
aucune fiche fantôme créée pour un ascendant), et une vérification déclarative de la glue
d'administration (sécurité du téléversement, aucune écriture avant validation, capacité
`edit_posts`, aucune conservation du PDF). Suite complète du module toujours verte.

## 0.12.6 — Diagnostic et correctif : contenu de la Photo principale invisible après déplacement

Le déplacement réel de 0.12.5 restait non fonctionnel côté utilisateur : dans l'onglet Médias,
seul le titre « Photo principale » apparaissait, sans aucun contrôle ni aucune image en dessous —
alors que la Galerie, elle, fonctionnait normalement.

**Démarche de diagnostic** : avant tout nouveau correctif, vérification que le déplacement DOM
lui-même (`appendChild()`) ne pouvait PAS être la cause — un déplacement de nœud ne supprime
jamais son contenu, par garantie de la spécification DOM. Un test d'exécution réelle a été écrit
avec le markup EXACT que WordPress produit pour `#postimagediv` (`post_thumbnail_meta_box()` /
`_wp_post_thumbnail_html()` : nonce, lien « Définir la photo principale » à vide, ou vignette +
lien « Supprimer » avec une photo déjà définie) et a confirmé, dans les deux états, que le
contenu de `.inside` survit intact au déplacement effectué par notre script. **Cause écartée avec
certitude : notre code de déplacement ne détruit rien.**

**Cause probable identifiée** : WordPress ne prévoit JAMAIS qu'un `.postbox` soit imbriqué à
l'intérieur d'un autre `.postbox` — cette forme de DOM n'existe nulle part ailleurs dans
l'administration native (notre déplacement de 0.12.5 crée précisément cette situation inédite, en
insérant `#postimagediv`, qui reste un `.postbox` complet, à l'intérieur de `.inside` de la boîte
Médias, elle-même un `.postbox`). L'administration WordPress est susceptible de cibler ce cas avec
une règle CSS défensive masquant les `.postbox` imbriqués — expliquant que le contenu, bien que
réellement déplacé et intact dans le DOM, restait invisible à l'écran.

- **Correctif CSS ciblé** (`assets/cheval-tabs.css`) : une règle scopée à l'emplacement dédié
  (`.gwseq-cheval-media__photo-principale-slot #postimagediv` et tous ses descendants) applique
  `display: revert !important` — cette valeur réinitialise CHAQUE élément à SA PROPRE valeur
  `display` par défaut du navigateur (bloc pour un `<div>`/`<p>`, en ligne pour un `<a>`/`<img>`...),
  sans qu'il soit nécessaire de connaître l'identité exacte d'une éventuelle règle contraire, et
  sans aucun effet si une telle règle n'existait pas réellement sur une installation donnée —
  aucune régression possible pour les installations où le problème ne se manifestait pas.
- **Aucun changement JavaScript** : le mécanisme de déplacement lui-même (0.12.5) était déjà
  correct et reste inchangé — seule une règle CSS défensive supplémentaire a été ajoutée.
- **Tests** : nouveau scénario dans le test d'exécution réelle reproduisant le markup RÉEL de
  `#postimagediv` dans ses deux états (avec/sans photo principale déjà définie) et vérifiant,
  champ par champ (nonce, lien « Définir », vignette, lien « Supprimer »), que le contenu de
  `.inside` survit intact au déplacement ET reste réellement visible (`offsetParent`) une fois
  l'onglet Médias actif — 13 nouvelles assertions Node, 2 nouvelles assertions PHP déclaratives
  vérifiant la présence de la règle CSS. Suite complète : 1104 assertions PHP + 53 assertions Node.

## 0.12.5 — Intégration réelle de la Photo principale dans l'onglet Médias

La recette a montré que le simple masquage/affichage EN PLACE de `postimagediv` (comme pour
Production/aperçu sous Pedigree) était insuffisant pour la Photo principale : la vraie boîte
restait dans la colonne latérale, et l'onglet Médias ne présentait qu'un texte renvoyant vers
elle — pas ce qui était attendu (« la Photo principale, puis Galerie et Vidéos, au même endroit »).

- **`postimagediv` est désormais RÉELLEMENT déplacée** (`assets/cheval-tabs-admin.js`) dans un
  emplacement dédié à l'intérieur même de la boîte Médias
  (`#gwseq-cheval-media-photo-principale-slot`, réservé par `cheval-media.php`) — une SEULE
  exception, explicitement assumée et documentée, à la règle générale de ce script (jamais déplacer
  une boîte, seulement la masquer/afficher en place). Le déplacement utilise `appendChild()` sur le
  nœud EXISTANT (jamais un clone, jamais une recréation) — exactement le mécanisme du
  glisser-déposer natif de WordPress entre colonnes — donc aucun gestionnaire d'événement déjà
  attaché par WordPress (`wp.media()`) n'est perdu. **Aucune donnée dupliquée** : même nœud DOM,
  même `attachment_id`, la Featured Image de WordPress reste l'unique source de vérité.
- **Elle n'apparaît plus jamais dans la colonne latérale** une fois intégrée à Médias : le
  déplacement, pas une simple duplication de visibilité, garantit structurellement qu'elle n'est
  jamais visible à deux endroits à la fois.
- **Héritage automatique de la visibilité** : devenue DESCENDANTE de la boîte Médias, elle suit
  désormais sa visibilité sans logique supplémentaire — masquée avec elle sous tout autre onglet,
  visible avec elle sous "Médias", aux côtés de Galerie/Vidéos, dans la même zone logique. La
  configuration des onglets (`gwseq_cheval_admin_tabs_config()`) ne référence donc plus
  `postimagediv` du tout — elle n'est plus soumise au mécanisme générique de visibilité par onglet.
- **Texte de remplacement retiré** (`cheval-media.php`) : « Utilise l'image à la une de cette fiche
  (voir l'encadré « Photo principale » dans la colonne de droite)... » est devenu inutile et a été
  supprimé, remplacé par l'emplacement d'accueil vide.
- **Restaurée à sa position native** si le système d'onglets se désactive intégralement (filet de
  sécurité n°2, 0.12.3) — jamais laissée à un endroit qui n'a de sens que si les onglets
  fonctionnent réellement.
- **Sans JavaScript**, l'emplacement réservé reste simplement vide et la Photo principale demeure
  modifiable normalement via l'encadré natif de la colonne latérale, à sa place habituelle —
  aucune régression du parcours sans JS.
- **Léger ajustement visuel** (`cheval-tabs.css`) : la boîte native, une fois nichée dans la boîte
  Médias, perd son propre encadrement (bordure/ombre) pour éviter une boîte visuellement imbriquée
  dans une boîte — aucun autre style natif WordPress modifié.
- **Tests** : 5 nouvelles assertions dans le test d'exécution réelle (déplacement effectif, absence
  de duplication, héritage de visibilité, restauration au filet de sécurité n°2) et 5 nouvelles
  assertions déclaratives PHP. Suite complète : 1102 assertions PHP + 40 assertions Node.

## 0.12.4 — Nettoyage de l'état WordPress hérité sur la meta box Identité

Complément demandé après 0.12.3 : au-delà des filets de sécurité runtime (JS), un nettoyage
PHP ciblé purge désormais l'état WordPress persisté par utilisateur qui a pu s'accumuler pendant
les multiples allers-retours de recette sur cet écran — sans jamais toucher au registre
`add_meta_box()` de la boîte Identité, qui reste (et est resté depuis l'Étape 4) en contexte
`'normal'`, jamais `'side'`.

- **`gwseq_cleanup_legacy_identite_metabox_user_state()`** (`includes/cheval-fields.php`, hookée sur
  `current_screen`, exécutée uniquement sur l'écran d'édition d'une fiche Cheval) : purge deux
  préférences WordPress PROPRES à l'utilisateur connecté (jamais une donnée métier, jamais une meta
  de la fiche Cheval elle-même) si elles portent une trace incohérente concernant la boîte
  Identité :
  1. `metaboxhidden_{$screen}` (case décochée dans le panneau "Options de l'écran" — la cause
     racine confirmée en 0.12.3, qui masque la boîte ENTIÈRE via la classe `.hide-if-js`) : la
     boîte Identité est retirée de la liste des boîtes masquées, sans toucher aux autres boîtes que
     l'utilisateur aurait légitimement masquées par ailleurs.
  2. `meta-box-order_{$screen}` (ordre/colonne mémorisés par glisser-déposer) : si "Identité"
     apparaît sous un contexte AUTRE que `'normal'` (ex. `'side'`, à la suite d'un glisser-déposer
     accidentel pendant une recette antérieure), cette entrée est retirée de l'ordre mémorisé —
     WordPress retombe alors sur son enregistrement réel (`'normal'`/`'high'`) plutôt que de
     perpétuer une position héritée incohérente. L'ordre du contexte `'normal'` lui-même, et les
     autres identifiants des autres contextes, ne sont jamais modifiés.
  Idempotent : n'écrit la préférence que si un changement réel est nécessaire ; scopé à cette seule
  boîte, jamais un nettoyage générique de toutes les préférences de l'utilisateur.
- **Complémentaire, pas un remplacement** : les deux filets de sécurité runtime de 0.12.3 (levée de
  `.closed`/`.hide-if-js` à l'activation d'un onglet, vérification `offsetParent`, dégradation sûre
  si une boîte reste invisible) restent en place — ce nettoyage traite la cause probable à la
  racine (l'état persisté), les filets restent la garantie de dernier recours si un autre mécanisme
  venait à en réintroduire une variante.
- **Tests** : 6 nouvelles assertions dans `gws-equestrian-cheval-logic-test.php` — écran hors sujet
  jamais touché, aucun utilisateur connecté (jamais d'erreur), case Screen Options réactivée sans
  affecter les autres boîtes masquées, idempotence (aucune réécriture si déjà propre), entrée
  héritée dans un contexte autre que `'normal'` retirée sans affecter le reste de ce contexte,
  ordre déjà correct jamais modifié. Suite complète : 1100 assertions PHP + 35 assertions Node.

## 0.12.3 — Correctif RÉGRESSION BLOQUANTE — diagnostic complet de l'onglet Identité vide, filets de sécurité

Le correctif 0.12.2 (repli natif `.closed`) ne résolvait PAS le problème en conditions réelles : la
recette a montré que la boîte Identité était ENTIÈREMENT invisible, en-tête compris — un symptôme
que `.closed` seul ne peut pas produire, puisqu'il ne masque que le contenu (`.inside`) d'une boîte,
jamais son en-tête. Le diagnostic a donc été repris entièrement, et deux filets de sécurité
génériques ont été ajoutés pour qu'un problème de ce type ne puisse plus jamais rendre une donnée
inaccessible, quelle qu'en soit la cause exacte.

**CAUSE RACINE COMPLÈTE** : au-delà de `.closed`, WordPress peut masquer une meta box ENTIÈRE
(en-tête compris) via la classe `.hide-if-js`, posée lorsque l'utilisateur a masqué cette boîte via
le panneau "Screen Options" — une préférence mémorisée par utilisateur (`metaboxhidden_{$screen}`),
plausible ici puisque la même base de recette est réutilisée depuis plusieurs versions (un
utilisateur ayant, à un moment, décoché "Identité" dans Screen Options — par exemple en tentant de
diagnostiquer lui-même une version précédente encore bloquante). La règle CSS correspondante peut
être `!important` : un simple `style.display = ''` (ce que faisait le script jusqu'ici) ne suffit
JAMAIS à l'emporter sur une règle `!important` de la feuille de style — la boîte restait donc
invisible même après notre correctif précédent.

**CORRECTIF DIRECT** (`assets/cheval-tabs-admin.js`) : l'activation d'un onglet lève désormais
`.closed` ET `.hide-if-js` pour chacune de ses boîtes, puis VÉRIFIE RÉELLEMENT la visibilité obtenue
via `offsetParent` (qui vaut `null` si l'élément ou un ancêtre reste masqué par une règle CSS
quelconque, quelle qu'en soit l'origine) — si elle reste masquée, l'affichage est forcé avec la même
priorité `!important` (`style.setProperty('display', 'block', 'important')`), la seule façon
garantie de l'emporter sur une règle native, quelle qu'elle soit.

**FILET DE SÉCURITÉ n°1 — cohérence de mapping** (§5 de la demande) : chaque meta box gérée par un
onglet est désormais marquée, dans le HTML RÉELLEMENT rendu par WordPress, d'une classe
`gwseq-tab-{id}` posée via le filtre natif `postbox_classes_{page}_{id}` (nouvelle fonction
`gwseq_register_cheval_admin_tab_postbox_classes()`, `includes/cheval-admin-tabs.php`) — dérivée de
la MÊME configuration transmise au script, jamais une seconde vérité. Avant de construire quoi que
ce soit, le script vérifie que chaque boîte trouvée par identifiant porte bien cette classe ; en cas
d'écart (config PHP et DOM réel ne concordent pas — ex. collision d'identifiant), AUCUN onglet n'est
construit et l'écran reste intégralement dans son état natif empilé.

**FILET DE SÉCURITÉ n°2 — dégradation sûre** (§4 de la demande : « échec du système d'onglets ≠
perte d'accès aux données ») : si, malgré la levée des mécanismes connus et le forçage `!important`,
une boîte de l'onglet actif reste réellement invisible (`offsetParent` toujours `null` — un
mécanisme non anticipé), le système d'onglets se désactive intégralement : la barre injectée est
retirée du DOM, TOUTES les boîtes gérées retrouvent une visibilité normale (jamais une meta box
existante supprimée, uniquement notre propre ajout). En environnement local/développement
uniquement, un message (`.notice.notice-error`, classes natives WordPress) signale le problème
plutôt que de le masquer silencieusement.

**Renforcement méthodologique des tests** : `tests/gws-equestrian-cheval-admin-tabs-runtime-test.js`
reproduit désormais la structure RÉELLE d'une meta box WordPress (`postbox-header`/`handlediv`/
`inside`, avec de vrais champs à l'intérieur) et MODÉLISE l'effet réel de `.closed`/`.hide-if-js`
sur `offsetParent` — plutôt qu'un DOM simplifié construit pour satisfaire le script. Trois scénarios
distincts : (1) cas nominal avec une boîte Identité à la fois repliée ET masquée par Screen Options,
vérifiant une visibilité RÉELLE (offsetParent) et la présence continue des champs historiques dans
le DOM ; (2) une boîte durablement masquée par un ancêtre hors de portée de tout correctif connu,
vérifiant le déclenchement du filet de sécurité n°2 ; (3) une incohérence de marquage entre la
configuration et le DOM réel, vérifiant qu'aucun onglet n'est alors construit (filet de sécurité
n°1). Chaque mécanisme a été vérifié indépendamment détecté par regression (désactivation
temporaire du correctif correspondant, confirmation de l'échec du test, restauration). 35 assertions
Node au total (+11), 8 nouvelles assertions PHP déclaratives (marquage `postbox_classes`,
configuration localisée, présence des nouveaux mécanismes dans le code source).

## 0.12.2 — Correctif RÉGRESSION BLOQUANTE — onglet Identité vide ; intégration Photo principale dans Médias

La reprise de la recette runtime après 0.12.1 a confirmé l'apparition correcte de la barre
d'onglets, mais a révélé un nouveau bloquant : l'onglet Identité affichait une zone vide, rendant
les champs historiques de l'Étape 4 (sexe, année de naissance, robe, race/stud-book, taille,
éleveur, propriétaire, SIRE/UELN) inaccessibles. Un ajustement UX complémentaire a été livré dans
la foulée : intégration de la Photo principale à l'onglet Médias.

- **CAUSE RACINE EXACTE de l'onglet Identité vide** : la meta box Identité était laissée REPLIÉE
  par le mécanisme natif de repli/dépli de WordPress (classe CSS `.closed`, posée par un clic sur
  le titre de la boîte — indépendant de nos onglets, potentiellement hérité d'une manipulation
  antérieure lors de la recette de 0.12.0/0.12.1). La règle CSS native
  `.postbox.closed .inside { display: none; }` cible l'élément `.inside` — un ENFANT de la boîte,
  où vit tout son contenu réel — jamais la boîte elle-même. Notre système d'onglets ne pilotait
  QUE `box.style.display` sur le conteneur `.postbox` : rétablir ce style à `''` rendait bien le
  conteneur visible, mais son `.inside` restait masqué par la règle CSS native, indépendamment de
  notre style inline — d'où une boîte "vide" à l'écran malgré un onglet correctement actif.
  Confirmé : ID de la boîte, contexte WordPress, ordre d'enregistrement et logique du tableau
  d'onglets étaient tous corrects — le mapping onglet → meta box n'était PAS en cause, seul l'état
  de repli natif de la boîte l'était. **Correctif** (`assets/cheval-tabs-admin.js`) : l'activation
  d'un onglet lève désormais systématiquement le repli natif (`classList.remove('closed')`) de
  chacune de ses boîtes, et synchronise l'attribut ARIA `aria-expanded` du bouton natif de
  repli/dépli (`.handlediv`) — un onglet actif affiche toujours un contenu déplié, jamais une
  boîte vide.
- **Photo principale intégrée à l'onglet Médias** (`includes/cheval-admin-tabs.php`) : la boîte
  NATIVE WordPress de l'image à la une (`postimagediv`) rejoint désormais Galerie/Vidéos sous un
  même onglet "Médias" — EXACTEMENT selon le même mécanisme déjà utilisé pour regrouper
  Production/aperçu pedigree sous "Pedigree" (0.12.1) : la boîte native n'est ni déplacée dans le
  DOM ni ré-enregistrée par ce plugin (elle reste dans sa colonne native), seule sa VISIBILITÉ est
  désormais pilotée par le même système d'onglets. **Aucun second champ, aucun second attachment
  ID, aucune synchronisation parallèle, aucun stockage spécifique** : la Featured Image de
  WordPress reste l'unique source de vérité, lue/modifiée par sa propre interface native
  (`wp.media()` intégré à WordPress) — jamais dupliquée. La boîte n'apparaît plus jamais deux fois
  (elle n'a jamais été dupliquée, seule sa colonne d'apparition — latérale, comme toujours —
  devient conditionnée à l'onglet actif). Publier, Catégories, Ordre d'affichage et Global Horse ID
  (dev-only) restent inchangés dans la colonne latérale, toujours visibles.
- **Sans JavaScript**, tous les champs Identité restent accessibles et la Photo principale reste
  modifiable via la mécanique native WordPress — aucune régression de ce côté, le mécanisme reste
  strictement additif (couche de présentation uniquement).
- **Renforcement des tests** : `tests/gws-equestrian-cheval-admin-tabs-runtime-test.js` reproduit
  désormais une boîte Identité déjà repliée (`.closed`) AVANT l'exécution du script, et vérifie que
  l'activation de son onglet la déplie réellement (classe retirée, `aria-expanded="true"`) — ce
  test aurait immédiatement détecté cette régression. Nouvelles assertions couvrant également le
  regroupement Photo principale + Galerie/Vidéos sous Médias (visibilité conjointe, masquage
  conjoint sous tout autre onglet, absence de duplication). 7 nouvelles assertions Node, 4
  nouvelles assertions PHP déclaratives.

## 0.12.1 — Correctif RÉGRESSION BLOQUANTE — navigation par onglets inopérante, meta boxes à risque

La recette runtime de 0.12.0 a échoué immédiatement : la navigation par onglets n'apparaissait pas
du tout, et l'écran d'édition d'une fiche cheval risquait de perdre l'accès visuel à des meta boxes
existantes. Deux causes racines distinctes, corrigées ici, aucun nouveau développement.

- **CAUSE 1 (bloquante, systématique) — mauvaise cible DOM pour l'insertion de la barre
  d'onglets** : le script (`assets/cheval-tabs-admin.js`) appelait
  `postbody.insertBefore(wrapper, normalSortables)`, où `postbody` référence `#post-body-content`.
  Or sur l'écran classique de WordPress (`wp-admin/edit-form-advanced.php`), `#post-body-content`
  et `#normal-sortables` (qui contient les meta boxes de la colonne principale, à l'intérieur de
  `#postbox-container-2`) sont deux enfants DISTINCTS de `#post-body` — jamais l'un dans l'autre.
  Un `insertBefore()` dont le nœud de référence n'est pas un enfant réel du nœud appelant lève
  systématiquement une `DOMException` dans tout navigateur conforme à la spécification DOM : le
  script s'arrêtait donc à cette ligne, AVANT même de construire la barre d'onglets — d'où son
  absence totale et systématique en recette. **Correctif** : la barre est désormais insérée comme
  premier enfant de `#normal-sortables` lui-même, son véritable ancêtre DOM direct, plaçant la
  navigation en haut de la colonne principale sans dépendre d'une hypothèse de structure erronée.
- **CAUSE 2 (risque de disparition de meta boxes existantes) — changement de contexte
  `add_meta_box()` non nécessaire** : 0.12.0 avait fait passer les meta boxes Production (calculée)
  et « Pedigree résolu » (dev-only) du contexte `'side'` à `'normal'` (`includes/cheval-pedigree.php`)
  pour les regrouper visuellement avec Pedigree sous l'onglet correspondant. Ce changement de
  contexte d'une version à l'autre expose un piège connu de WordPress : l'ordre des meta boxes par
  écran est mémorisé par utilisateur (`meta-box-order_{$screen}`), associé à un COUPLE
  identifiant/contexte précis — un changement de contexte peut alors faire perdre le rattachement
  réel d'une boîte lors de la fusion interne de `add_meta_box()` pour un utilisateur ayant déjà
  navigué sur cet écran avant la mise à jour, la boîte concernée n'étant alors plus jamais rendue.
  **Correctif** : contexte `'side'` restauré pour ces deux boîtes, exactement comme avant l'Étape 6
  — sans aucune conséquence sur le regroupement fonctionnel sous l'onglet Pedigree, qui ne dépend
  jamais de la position DOM des boîtes (le script les retrouve par identifiant HTML, où qu'elles
  soient physiquement) : seule leur COLONNE d'apparition change (colonne latérale au lieu de
  colonne principale) quand l'onglet Pedigree est actif.
- **Aucune donnée, règle métier ou mécanisme de sauvegarde affecté par ces deux correctifs** —
  strictement des corrections de câblage DOM/PHP de la couche de présentation ajoutée en 0.12.0.
- **Renforcement des tests** : les 73 assertions de `gws-equestrian-cheval-admin-tabs-test.php`
  n'avaient pas détecté la régression bloquante — elles ne font que scanner le TEXTE SOURCE du
  script, jamais l'exécuter. Nouveau fichier `tests/gws-equestrian-cheval-admin-tabs-runtime-test.js`
  (24 assertions, exécuté via `node`, aucune dépendance npm ajoutée) : reproduit fidèlement la
  structure DOM réelle de l'écran classique d'édition (colonnes latérale/principale bien
  distinctes, avec le vrai bouton `#publish`), exécute réellement `cheval-tabs-admin.js` dans ce
  DOM simulé (module `vm` de Node), et vérifie le câblage effectif : aucune exception levée,
  insertion réelle de la barre au bon endroit, regroupement Pedigree/Production/aperçu même à
  cheval sur deux colonnes physiques, bascule effective de visibilité au clic et au clavier, aucune
  meta box jamais retirée du DOM, bouton rapide déclenchant réellement le bouton natif. Deux
  nouvelles assertions déclaratives complètent aussi le fichier de test existant (contexte `'side'`
  restauré ; absence du pattern d'insertion fautif).

## 0.12.0 — Étape 6 : ajustements UX post-recette — CD à deux décimales, navigation par onglets

Correctifs suite à la première recette runtime de l'Étape 6 : l'écran d'édition d'une fiche cheval
était devenu trop long avec l'ajout des indices/médias/présentation, et le coefficient de
détermination (CD) des indices génétiques s'affichait sans précision fixe.

- **Présentation du CD à deux décimales** (`gwseq_format_cheval_indice_cd()`,
  `includes/cheval-indices.php`) : « 0.90 », jamais « 0.9 » — UNIQUEMENT une présentation, le
  stockage reste le nombre PHP exact (vérifié explicitement par test : relire la valeur après
  arrondi d'affichage renvoie toujours le nombre stocké sans perte de précision). Appliquée au
  champ CD du formulaire admin (avec un pas de saisie `step="0.01"` cohérent) et au libellé
  `gwseq_cheval_genetic_indice_label()` (« BSO +12 (0.90) », conforme à l'exemple exact de la
  demande). Séparateur décimal volontairement le point à ce stade, aucune conversion
  virgule/point ajoutée — un futur renderer pourra localiser l'affichage sans changer la donnée.
- **Navigation par onglets** (`includes/cheval-admin-tabs.php`,
  `assets/cheval-tabs-admin.js`/`cheval-tabs.css`) : 6 onglets (Identité, Commercial, Pedigree,
  Indices, Médias, Présentation) regroupant les meta boxes déjà existantes — **uniquement une
  couche de présentation** : aucune meta modifiée, aucun second formulaire, aucune règle métier ni
  mécanisme de sauvegarde changés, aucun chargement par AJAX, aucune donnée jamais absente du DOM.
  Le script ne fait que masquer/afficher (`style.display`) les `<div class="postbox">` déjà
  présentes, SANS JAMAIS LES DÉPLACER — préserve intégralement le comportement natif de WordPress
  sur ces boîtes (repliage, etc.). Sans JavaScript, la fiche reste utilisable exactement comme
  avant, empilée verticalement. Les meta boxes Production (calculée) et « Pedigree résolu »
  (dev-only) passent du contexte `'side'` à `'normal'` pour rejoindre la colonne principale, seule
  couverte par les onglets, et sont regroupées avec Pedigree sous le même onglet — un changement
  de PLACEMENT visuel uniquement (paramètre de `add_meta_box()`), aucune donnée affectée. La photo
  principale (image à la une native), le Global Horse ID (dev-only) et la boîte "Ordre
  d'affichage" restent volontairement hors du système d'onglets, dans la colonne latérale.
- **Accès rapide à la sauvegarde** : un bouton dans la barre d'onglets déclenche un clic
  PROGRAMMATIQUE sur le vrai bouton natif WordPress (`#publish`) — reproduit même son libellé
  réel (« Publier »/« Mettre à jour ») plutôt qu'un texte codé en dur. Aucun second mécanisme de
  sauvegarde, aucun appel direct à `form.submit()` (qui contournerait d'éventuels gestionnaires
  natifs attachés au bouton) : une seule soumission possible, jamais deux concurrentes.
- **Accessibilité** : pattern ARIA `tablist`/`tab`/`tabpanel` (chaque meta box devient
  sémantiquement le panneau de son onglet via `aria-labelledby`, `aria-controls` — qui accepte
  nativement une liste d'identifiants — regroupe plusieurs boîtes sous un même onglet sans les
  déplacer), navigation clavier complète (flèches gauche/droite, Début/Fin, tabindex mobile),
  réutilisation des classes natives `.nav-tab-wrapper`/`.nav-tab` de WordPress pour l'apparence.
  Disposition responsive (repli sur écran étroit).
- **Persistance légère de l'onglet actif** : `sessionStorage`, accès protégé (jamais une erreur
  bloquante si indisponible) — un choix volontairement simple, comme demandé, sans infrastructure
  dédiée.
- 1 nouveau fichier de tests dédié (`gws-equestrian-cheval-admin-tabs-test.php`, 73 assertions) +
  extension du test des indices (27 nouvelles assertions pour le formatage du CD). Suite complète
  rejouée : 14 fichiers, 1051 assertions, 100 % vertes, zéro avertissement PHP, zéro régression sur
  le pedigree, la Production, les filtres de parents, les indices, la galerie, les vidéos, les
  contenus éditoriaux, le Global ID ou le commercial.
- Versions : GWS Core 1.14.0 → 1.15.0, GWS Equestrian 0.11.0 → 0.12.0.

## 0.11.0 — Étape 6 : indices, médias et contenu de présentation du cheval

Enrichit la fiche Cheval (une seule entité, sans typologie rigide, tous les champs facultatifs)
avec les données nécessaires à sa présentation et sa future commercialisation multicanale, sans
toucher au socle de l'Étape 4 ni aux relations de pedigree de l'Étape 5.

- **Indices sportifs** (ISO, ICC, IDR — `includes/cheval-indices.php`) : valeur et année stockées
  séparément (jamais une chaîne unique du type "142 (2025)"), une seule valeur par indice et par
  cheval (aucun historique annuel — chaque enregistrement remplace le précédent), les trois
  indépendants et facultatifs. Année bornée à 1900..année en cours (jamais une année future,
  contrairement à l'année de naissance qui autorise +1 pour un poulain attendu).
- **Indices génétiques** (BSO, BCC, BDR) : valeur (signée, décimale) et coefficient de
  détermination (CD) stockés séparément, jamais d'année. Le signe positif n'est jamais perdu au
  stockage (nombre PHP positif natif) ; `gwseq_cheval_genetic_indice_label()` ajoute le "+"
  explicite uniquement à l'affichage (ex. "+12 (0.9)"), jamais dans la donnée elle-même.
- **Galerie photos** (`includes/cheval-media.php`) : jusqu'à 9 photos complémentaires à la photo
  principale (qui reste exclusivement l'image à la une native, aucun second champ). Tableau
  ORDONNÉ d'attachment IDs (jamais des URLs), sans doublon, sélection via la médiathèque native
  (`wp.media()`, `assets/cheval-media-admin.js`) — aucun uploader parallèle, aucune suppression du
  média WordPress lors d'un retrait de la galerie. Taille dérivée `gwseq_large` (1600px)
  enregistrée pour un futur grand affichage, sans jamais toucher à l'original.
- **Vidéos** : liste {URL, titre facultatif} ordonnée, jusqu'à 10, réutilisant le composant
  répétable générique de l'Étape 2 (`includes/repeater-field.php`, dont l'en-tête anticipait déjà
  ce cas d'usage) pour le rendu/JS, avec une sanitation dédiée (une ligne sans URL http/https
  valide n'est jamais stockée, même avec un titre). Aucun upload de fichier vidéo.
- **`repeater-field.php`/`.js`** : ajout d'un paramètre optionnel `$max_rows` à
  `gwseq_render_repeater_field()` — désactive le bouton d'ajout une fois la limite atteinte (aide
  UX uniquement, la garantie réelle reste la sanitation serveur propre à chaque appelant).
  Comportement par défaut (sans limite) strictement inchangé.
- **Présentation éditoriale** (`includes/cheval-editorial.php`) : Présentation, Points forts,
  Potentiel, Résultats/Performances, Origines — commentaire, Production — commentaire, Conditions
  de vente/élevage/reproduction, Conseils de croisement (disponible pour tous les chevaux, jamais
  conditionné au sexe/à une catégorie) — tous facultatifs, texte libre sanitisé
  (`sanitize_textarea_field`). Noms de meta explicites pour lever deux ambiguïtés : le commentaire
  "Production" (`_gwseq_commentaire_production`) reste totalement distinct de la Production
  CALCULÉE (relationnelle, Étape 5) ; le commentaire "Origines" (`_gwseq_origines_commentaire`)
  reste totalement distinct du pedigree STRUCTURÉ — ni l'un ni l'autre ne sont jamais reconstruits
  à partir du texte éditorial correspondant, dans un sens ou dans l'autre.
- **Ostéo-articulaire** : champ texte libre unique, volontairement PAS un dossier vétérinaire
  (aucun historique de soins, traitement, ordonnance, radio ni donnée médicale structurée).
- **Organisation admin** (§9) : blocs cohérents (Indices ; Médias ; Présentation ; Informations
  complémentaires) plutôt qu'une succession indifférenciée de champs — jamais de
  masquage/désactivation conditionnelle selon le sexe, la catégorie ou le statut commercial : tous
  les champs restent disponibles pour tous les chevaux.
- **Architecture programmatique** (§11, même principe que le pedigree — Étape 5) : chaque nouvelle
  donnée dispose d'une fonction `gwseq_set_cheval_*()` pure, jamais couplée à `$_POST` ni à un
  nonce — réutilisable telle quelle par un futur import CSV/XLSX, une duplication de fiche, une API
  ou une synchronisation GWS Network.
- **Aucune migration destructive** : tous les nouveaux champs sont absents (donc vides) sur une
  fiche créée avant cette version, sans erreur ni valeur par défaut inventée ; aucune suppression
  de donnée n'est jamais déclenchée par une (dés)activation du module (vérifié explicitement par
  les tests, aucun appel à `delete_post_meta()` dans les trois nouveaux fichiers).
- **Hors périmètre, comme prévu** : aucun rendu public, PDF, QR code, catalogue ou Social Kit ;
  aucun historique annuel des indices ; aucune base structurée exhaustive de résultats sportifs ;
  aucun dossier vétérinaire ; aucune logique conditionnelle selon le type de cheval.
- 3 nouveaux fichiers de tests (indices, médias, éditorial) + extension du test du composant
  répétable : 250 nouvelles assertions (65 indices + 61 médias + 117 éditorial + 7 pour
  `$max_rows`) ; suite complète rejouée : 13 fichiers, 950 assertions, 100 % vertes, zéro
  avertissement PHP.
- Versions : GWS Core 1.13.0 → 1.14.0, GWS Equestrian 0.10.0 → 0.11.0.

## 0.10.0 — Étape 5 : correctif intégrité du pedigree — filtrage métier des parents GWS (sexe, année)

Correctif suite à deux règles métier supplémentaires identifiées en recette runtime, applicables
uniquement aux relations vers un cheval déjà enregistré dans GWS (jamais aux ascendants externes).

- **Filtrage selon le sexe** (`gwseq_horse_sexe_compatible_with_role()`) : mâle/entier et hongre
  autorisés comme père (un cheval a pu reproduire avant sa castration), seule une femelle est
  autorisée comme mère ; un sexe non renseigné reste toujours autorisé pour les deux rôles. Ni
  déduit ni modifié automatiquement à partir de l'usage du cheval comme père ou mère.
- **Filtrage selon l'année de naissance** (`gwseq_horse_birth_year_compatible()`) : un candidat à
  l'année connue doit être né strictement avant le produit (même année ou plus tard = interdit,
  volontairement AUCUN âge minimum de reproduction en V1) ; année du candidat ou du produit
  inconnue = aucun filtre appliqué.
- **Règle métier unique et centrale** (`gwseq_horse_parent_candidate_rejection_reason()`) :
  combine désormais auto-référence, sexe, année de naissance et conflit avec l'autre rôle (0.9.0)
  — le rendu du formulaire, `gwseq_set_horse_parent()` (validation serveur) et tout futur import
  s'appuient tous sur cette même fonction, jamais une règle dupliquée ailleurs. En cas de rejet :
  comportement déterministe (`false`), aucune écriture partielle, relation existante jamais
  supprimée ni remplacée silencieusement.
- **UX admin** : réutilise le mécanisme de désactivation d'options déjà en place pour le conflit
  père/mère, avec une indication courte de la raison (« sexe incompatible », « année
  incompatible »). Sexe et année étant des propriétés fixes du candidat (contrairement au conflit
  avec l'autre rôle, qui dépend de la sélection courante), `assets/cheval-admin.js` ne les
  reconsidère jamais en direct — un attribut `data-gwseq-locked-disabled` les verrouille
  explicitement contre toute réactivation par ce script.
- **Modification ultérieure des données** (cas documenté, non traité automatiquement) : une
  relation valide à sa création restant valide après une modification ultérieure compatible (ex.
  un entier castré devient Hongre — toujours autorisé comme père) n'est jamais affectée ; plus
  généralement, aucun contrôle rétroactif n'est construit en V1 si une modification rendait une
  relation existante réellement incohérente — piste actée pour une amélioration future
  (audit/avertissement d'intégrité).
- **Ascendants externes non affectés** : aucune comparaison par nom, aucun champ sexe ajouté,
  aucune contrainte GWS appliquée, branches externes inactives toujours conservées telles quelles.
- 34 nouvelles assertions dans le test Pedigree (276 au total). Suite complète rejouée : 10
  fichiers, 697 assertions, 100 % vertes, zéro avertissement PHP.
- Versions : GWS Core 1.12.0 → 1.13.0, GWS Equestrian 0.9.0 → 0.10.0.

## 0.9.0 — Étape 5 : correctif intégrité du pedigree — même cheval GWS comme père et mère

Correctif suite à un nouveau défaut observé en recette runtime : il était possible de sélectionner
le même cheval GWS comme père ET comme mère d'un même cheval — une incohérence biologique
distincte de l'auto-parenté (déjà correctement empêchée).

- **Validation serveur** : `gwseq_set_horse_parent()` refuse désormais l'enregistrement d'une
  relation "gws" qui créerait ce conflit — le même cheval GWS déjà actif comme l'autre parent
  (`gwseq_horse_parent_conflicts_with_other_role()`). Retourne `false` (comportement documenté,
  vérifiable par un appel) et ne modifie AUCUNE meta pour ce rôle dans ce cas : la relation
  existante, le cas échéant, n'est jamais supprimée ni remplacée silencieusement. Cette validation
  s'applique identiquement à un appel programmatique direct (le futur chemin d'import), puisque
  c'est exactement la même fonction, sans dépendre de $_POST ni de JavaScript.
- **UX admin** (`assets/cheval-admin.js`) : le cheval déjà actif dans l'autre sélecteur (père ↔
  mère) est désormais désactivé dans le sélecteur courant, et cette exclusion se resynchronise en
  direct si l'autre sélecteur change — sans jamais modifier automatiquement une valeur déjà
  sélectionnée. Une aide à la saisie uniquement ; la validation serveur reste la seule garantie
  réelle, y compris avec JavaScript désactivé (l'option est déjà rendue désactivée dès le serveur,
  défense en profondeur).
- **Ce qui reste inchangé** : l'auto-parenté reste protégée comme avant ; deux ascendants externes
  ne sont jamais comparés par leur nom (aucun rapprochement, même en cas d'homonymie avec un cheval
  GWS) ; les branches externes inactives conservées lors d'un changement de mode ne sont jamais
  affectées ; le resolver et le modèle de pedigree ne sont pas modifiés au-delà de cette contrainte.
- **Corrections lexicales validées** : « Cheval déjà présent dans GWS » → « Cheval déjà
  enregistré » ; « Ascendant hors GWS » → « Nouvel ascendant » ; texte de l'aperçu développeur →
  « Aperçu du pedigree enregistré — actualisé après sauvegarde. ».
- **Compatibilité** : aucune migration automatique d'une éventuelle incohérence déjà enregistrée
  avant cette version (un même cheval déjà stocké comme père et mère d'une fiche resterait dans cet
  état jusqu'à une modification explicite de l'un des deux côtés par l'utilisateur).
- 32 nouvelles assertions dans le test Pedigree (242 au total). Suite complète rejouée : 10
  fichiers, 663 assertions, 100 % vertes, zéro avertissement PHP.
- Versions : GWS Core 1.11.0 → 1.12.0, GWS Equestrian 0.8.0 → 0.9.0.

## 0.8.0 — Étape 5 : correctif complémentaire — suppression d'un ascendant externe vide

Correctif suite à un nouveau défaut observé en reprise de recette runtime : un ascendant externe
créé (nom saisi) puis entièrement vidé par l'utilisateur — en restant sur le mode « Ascendant hors
GWS » — continuait d'exister en base et réapparaissait à la réouverture de la fiche.

- **Cause exacte** : un nœud sans nom n'a jamais pu être stocké (`gwseq_sanitize_external_ancestor_tree()`
  renvoie déjà `null` dès qu'un nom est vide, y compris récursivement pour tout sous-arbre —
  garantie déjà en place, inchangée par ce correctif). Le vrai défaut se situait dans
  `gwseq_set_horse_parent()` : quand l'arbre sanitisé devenait ainsi entièrement `null`, seule la
  meta `..._mode` était réinitialisée — l'ancienne meta `..._externe` restait intacte. Ce
  comportement est correct pour un CHANGEMENT DE MODE (GWS ⇄ externe, où la conservation non
  destructive est volontaire) mais pas ici : l'utilisateur restait sur « externe » et avait
  activement tout effacé, sans que la donnée stockée ne suive.
- **Correctif** : quand l'arbre sanitisé est entièrement vide alors que le mode soumis est
  `external`, `..._externe` est désormais explicitement supprimée (`delete_post_meta()`) plutôt que
  laissée à sa valeur précédente — sans jamais toucher à la branche GWS (`_id`) ni à la relation de
  l'autre parent (père/mère gérés indépendamment).
- **Bouton explicite « Supprimer cet ascendant »** (`assets/cheval-admin.js`) : permet de vider en
  un clic un nœud, à n'importe quelle génération, ainsi que toute sa sous-branche — avec une
  confirmation (« Supprimer cet ascendant et ses origines ? ») si des origines enfants sont déjà
  renseignées. Purement une remise à vide des champs côté client (jamais d'appel serveur, jamais de
  suppression d'élément du DOM) : la suppression réelle en base reste l'effet du mécanisme
  ci-dessus au prochain enregistrement. Le texte de confirmation est fourni par PHP via l'attribut
  `data-delete-confirm`, jamais codé en dur en JavaScript.
- **Resolver inchangé** : la garde qui empêche de résoudre un nœud externe sans nom en un nœud
  fantôme (`gwseq_resolve_external_ancestor_node()`) existait déjà avant ce correctif — elle est
  désormais testée explicitement, y compris sur une donnée héritée d'avant cette version.
- **Relation vers une fiche GWS non concernée** : le choix « Non renseigné » continue de désactiver
  la relation sans jamais supprimer la fiche Cheval référencée (comportement inchangé, revérifié
  par un test dédié).
- 21 nouvelles assertions dans le test Pedigree (210 au total). Suite complète rejouée : 10
  fichiers, 631 assertions, 100 % vertes, zéro avertissement PHP.
- Versions : GWS Core 1.10.0 → 1.11.0, GWS Equestrian 0.7.0 → 0.8.0.

## 0.7.0 — Étape 5 : correctif BLOQUANT (corruption Unicode), contexte dynamique, génération terminale

Correctif urgent suite à la reprise de la recette runtime sur le pedigree de Jamerose. Trois
problèmes distincts, chacun corrigé sans toucher au modèle validé (arbre externe récursif,
relation GWS/externe, resolver, conservation non destructive, limite serveur, production,
API programmatique).

- **Bug bloquant : corruption des noms accentués** (« Native de Félines » devenait
  « Native de Fu00e9lines » après enregistrement). **Cause racine exacte** :
  `gwseq_set_horse_parent()` encodait l'arbre externe avec `wp_json_encode($tree)` sans le
  drapeau `JSON_UNESCAPED_UNICODE`. Sans ce drapeau, `json_encode()` échappe tout caractère
  non-ASCII en séquence littérale `\uXXXX` (« é » → les six caractères `\`, `u`, `0`, `0`, `e`,
  `9`). Cette chaîne — qui contient donc un antislash réel — passe ensuite à
  `update_post_meta()`, laquelle appelle EN INTERNE `wp_unslash()` sur la valeur avant stockage
  (comportement natif de `update_metadata()` dans WordPress, totalement indépendant de ce
  module et de toute logique métier). `wp_unslash()` ne distingue pas un antislash « magic
  quotes » d'un antislash faisant partie du contenu légitime : il retire celui de `é`,
  laissant le texte littéral `u00e9` — une chaîne JSON toujours syntaxiquement valide (donc
  `json_decode()` ne remonte jamais d'erreur), mais dont le contenu est désormais faux. Une fois
  ce nom corrompu réaffiché puis réenregistré, la corruption devient permanente. **Aucun rapport
  avec `gwseq_format_horse_name_display()`** (la fonction de présentation) : elle se contente
  d'afficher fidèlement une donnée déjà corrompue en amont (« u00e9 » en majuscules donne
  « U00E9 », exactement le symptôme observé) ; elle n'est appelée dans aucune fonction de
  sanitation ou de persistance (vérifié directement dans le code source, hors commentaires).
  **Correctif** : `wp_json_encode($tree, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)` — les
  caractères accentués sont désormais écrits tels quels (aucun antislash), donc rien que
  `wp_unslash()` puisse corrompre. **Découverte méthodologique importante** : les stubs de test
  `wp_unslash()`/`update_post_meta()`/`wp_json_encode()` du fichier de tests Pedigree étaient de
  simples passe-plats, non fidèles au comportement réel de WordPress sur ce point précis — c'est
  cette infidélité, et non un manque de couverture, qui a laissé ce bug traverser 563 assertions
  déjà vertes. Les trois stubs ont été rendus fidèles (vrai `stripslashes()`, options JSON
  réellement transmises), rendant le bug reproductible — et donc vérifiable — sans WordPress
  réel. **Aucune migration automatique** des données déjà corrompues (ex. la fiche Jamerose de
  recette) : correction manuelle ponctuelle par re-saisie, une reconstruction automatique du
  type `u00e9 → é` étant jugée insuffisamment sûre pour être tentée à l'aveugle.
- **Contexte de saisie désormais mis à jour EN DIRECT** (`assets/cheval-admin.js`) : un premier
  essai sans JavaScript (version 0.6.0) s'est révélé insuffisant en recette réelle — après avoir
  saisi le nom d'un nouvel ascendant, l'intitulé restait « Père de cet ascendant » tant que la
  fiche n'était pas enregistrée. Une écoute déléguée légère et strictement scopée à l'écran
  Cheval met désormais à jour le résumé de divulgation progressive et les libellés Père/Mère du
  niveau suivant à chaque frappe dans un champ Nom — sans jamais lire ni modifier la valeur de ce
  champ (aucune normalisation de casse, aucune suppression d'accent appliquée à la donnée
  envoyée au serveur). Les libellés traduits sont fournis au script via des attributs `data-*`
  d'un conteneur dédié (`.gwseq-pedigree-i18n`), jamais codés en dur côté JavaScript.
- **Génération terminale — un nœud de génération 4 n'a plus AUCUNE clé père/mère** (ni même
  `null`). La recette a révélé que le rendu de vérification admin/développement affichait, sous
  un ascendant de génération 4, « Père : Non renseigné »/« Mère : Non renseigné » — laissant
  croire à tort qu'une génération 5 existerait dans le modèle, alors qu'elle est hors périmètre
  du pedigree V1, jamais saisissable ni stockée. Le resolver (`pedigree-resolver.php`) ne tente
  plus de lire les relations père/mère d'un nœud à profondeur restante nulle, que la branche
  soit GWS ou externe ; ses clés `father`/`mother` sont absentes plutôt que `null`. Le type de
  nœud `depth_limit` (première version de l'Étape 5) est retiré en conséquence — il ne
  correspondait qu'à ce même cas, désormais traité en amont. Le rendu de vérification
  (`gwseq_render_pedigree_node_preview()`) n'affiche plus aucune ligne Père/Mère pour un nœud
  sans ces clés. Principe conservé pour un futur rendu public (Étape 8) : une donnée
  généalogique absente ne doit jamais être remplacée par un texte « Non renseigné », sauf besoin
  explicite futur.
- Fichiers modifiés : `includes/cheval-pedigree.php` (drapeau JSON, conteneur de libellés
  traduits pour le JavaScript, classes `gwseq-ancestor-node`/`gwseq-external-name-input`),
  `includes/pedigree-resolver.php` (génération terminale, retrait du type `depth_limit`, rendu
  de vérification corrigé), `assets/cheval-admin.js` (mise à jour dynamique du contexte).
- 40 nouvelles assertions dans `tests/gws-equestrian-pedigree-logic-test.php` (reproduction
  exacte du bug via des stubs désormais fidèles au comportement réel de WordPress, non-altération
  de la source sur plusieurs enregistrements consécutifs et à travers un changement de mode,
  non-altération à plusieurs niveaux imbriqués, JSON stocké contenant le caractère littéral,
  helper de présentation élargi — Félines/Hélios/À bientôt/Crème Brûlée —, séparation
  source/présentation vérifiée hors commentaires, câblage des attributs `data-*` et classes pour
  le JavaScript, génération terminale pour une chaîne GWS ET une branche externe, absence de
  « Non renseigné » dans le rendu de vérification). Suite complète 100 % passante (603
  assertions) — aucune assertion existante affaiblie, plusieurs ont été renforcées ou corrigées
  pour refléter fidèlement le nouveau comportement de génération terminale.

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

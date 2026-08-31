# GWS Equestrian (gws-core)

Module métier pour les professionnels du monde équestre (pension, enseignement, élevage,
reproduction, vente) : gestion des prestations/tarifs et des fiches chevaux. Voir le pendant
présentation dans `wp-content/themes/gws-starter/modules/gws-equestrian/`.

**Préfixe du module : `gwseq_`** (jamais `gws_` ni `gws_core_`, réservés au cœur — voir
`modules/README.md` et `AI-AGENT.md` §3). Consigné dans le registre de `modules/README.md`.

## État actuel : Étape 6 — Indices, médias et présentation, ajustement UX 0.12.2 (Étape 5 — Pedigree validée)

Les Étapes 1 (fondations), 2 (composant répétable), 3 (Prestations/Groupes tarifaires) et 4
(Cheval) ont été recettées en conditions réelles et validées — gel à GWS Core 1.7.1 / GWS
Equestrian 0.4.1. L'Étape 5 (relations de pedigree — Père/Mère, cheval GWS ou ascendant externe
récursif, resolver, production) a traversé plusieurs allers-retours de recette runtime (Race/
Stud-book harmonisé, contexte de saisie, correctif bloquant de corruption Unicode, suppression
effective d'un ascendant externe vidé, intégrité père/mère, filtrage sexe/année) et est désormais
**validée** — voir `CHANGELOG.md` de ce dossier pour le détail complet de ces corrections (0.5.0 à
0.10.0). L'Étape 6 enrichit la fiche Cheval avec des indices sportifs/génétiques, une galerie
photos et des vidéos, et du contenu éditorial de présentation — sans toucher au socle des Étapes 4
et 5 (voir plus bas). Une première recette runtime a débuté sur les indices ; elle a révélé une
fiche devenue trop longue à faire défiler, d'où l'ajustement UX post-recette 0.12.0 (présentation
du CD des indices génétiques à deux décimales, navigation par onglets — voir plus bas). La reprise
de la recette sur cette nouvelle interface a immédiatement révélé une régression bloquante
(navigation par onglets inopérante, risque de disparition de meta boxes existantes), corrigée en
0.12.1 (voir « Correctif RÉGRESSION BLOQUANTE 0.12.1 » plus bas) avant nouvelle reprise de la
recette.

### Indices, médias et présentation (Étape 6)

**Un seul principe, aucune typologie rigide** : tous les chevaux disposent exactement des mêmes
champs (indices, galerie, vidéos, présentation éditoriale) — aucune distinction structurelle
étalon/poulinière/cheval de sport/cheval à vendre. L'utilisateur ne renseigne que ce qui est
pertinent pour le cheval concerné ; aucun champ n'est masqué ou désactivé automatiquement selon le
sexe, la catégorie ou le statut commercial (voir déjà ce principe pour le pedigree à l'Étape 5).
Organisation admin en blocs cohérents plutôt qu'une succession indifférenciée de champs : Indices,
Médias, Présentation, Informations complémentaires — en plus des meta boxes déjà existantes
(Identité, Commercialisation, Pedigree, Production, Catégories).

#### Indices sportifs (`includes/cheval-indices.php`)

ISO, ICC, IDR : chacun stocké en deux composants strictement séparés — valeur (entier) et année
(entier) — jamais dans une chaîne unique du type « 142 (2025) ». **Une seule valeur par indice et
par cheval** : GWS Equestrian ne conserve JAMAIS d'historique annuel en V1 — un nouvel
enregistrement REMPLACE simplement l'ancien, normalement le meilleur indice que le professionnel
souhaite présenter. Les trois indices sont indépendants et tous facultatifs : un cheval peut n'en
avoir aucun, un seul, ou les trois, sans combinaison imposée ni déduite. L'année d'un indice est
bornée entre 1900 et l'année EN COURS (jamais l'année courante + 1, contrairement à l'année de
naissance qui autorise cette marge pour un poulain attendu — un indice est par nature rétrospectif,
il ne peut jamais concerner une année future).

#### Indices génétiques (`includes/cheval-indices.php`)

BSO, BCC, BDR : structure différente — valeur (nombre, signé, décimal si nécessaire) et coefficient
de détermination/CD (nombre décimal), stockés séparément, jamais d'année. Le signe positif d'une
valeur (ex. « +12 ») n'est jamais perdu : il est stocké comme un nombre PHP positif natif (12), le
« + » n'étant ajouté qu'à l'affichage par `gwseq_cheval_genetic_indice_label()` (ex. « +12 (0.90) »,
CD présenté à deux décimales depuis l'ajustement 0.12.0 — voir plus bas) — jamais dans la donnée
stockée elle-même, qui reste un nombre exploitable tel quel par un futur calcul ou tri.

#### Médias — galerie et vidéos (`includes/cheval-media.php`)

**Photo principale** : reste exclusivement l'image à la une native de WordPress (déjà en place
depuis l'Étape 4) — aucun second champ créé, `gwseq_get_cheval_photo_principale_id()` n'est qu'un
alias nommé de `get_post_thumbnail_id()`, pour la seule cohérence de nommage avec les autres
accesseurs de médias de ce fichier.

**Galerie** : jusqu'à 9 photos complémentaires (10 au total avec la photo principale). Stockée en
UN SEUL tableau ORDONNÉ d'identifiants d'attachement WordPress (jamais des URLs) dans
`_gwseq_galerie` — un attachment_id reste valide même si les fichiers dérivés sont régénérés ou le
média déplacé. Retirer une image de la galerie ne supprime JAMAIS le média de la médiathèque
(aucun appel à `wp_delete_attachment()` nulle part dans ce fichier, vérifié explicitement par les
tests) — seule la référence disparaît. Aucun système d'upload parallèle : l'ajout passe
exclusivement par la médiathèque native (`wp.media()`, voir `assets/cheval-media-admin.js`) —
sélection multiple, aucun doublon possible, réordonnancement par boutons ↑/↓ (pas de
glisser-déposer, volontairement simple). Aucun redimensionnement destructif de l'original : une
taille dérivée `gwseq_large` (1600×1600, non recadrée) est enregistrée pour un futur grand
affichage, l'original restant toujours disponible pour tout usage nécessitant la pleine résolution
(print, PDF...) — les renderers futurs réutiliseront les mécanismes natifs de tailles dérivées/
srcset de WordPress à partir du même attachment_id, jamais une copie physique par canal.

**Vidéos** : liste répétable {URL, titre facultatif}, ORDONNÉE, jusqu'à 10 entrées. Réutilise le
composant répétable générique déjà construit à l'Étape 2 (`includes/repeater-field.php` — dont
l'en-tête mentionnait déjà explicitement « URLs de vidéos » comme cas d'usage anticipé) pour le
rendu et le JavaScript d'ajout/suppression de lignes, avec une sanitation DÉDIÉE : une ligne sans
URL exploitable (schéma http/https valide) n'est jamais stockée, même si un titre a été saisi seul
— contrairement à la règle générique du composant, qui ne rejette une ligne que si TOUTES ses
colonnes sont vides. Aucun upload direct de fichier vidéo : seule une URL est acceptée, compatible
avec les mécanismes sûrs/oEmbed natifs de WordPress. Le composant répétable générique reçoit à
cette occasion un paramètre optionnel `$max_rows` (désactive le bouton d'ajout une fois la limite
atteinte — aide UX uniquement, la garantie réelle reste la sanitation serveur) sans changer son
comportement par défaut lorsqu'il est omis.

#### Présentation éditoriale (`includes/cheval-editorial.php`)

Huit champs facultatifs, texte libre sanitisé (`sanitize_textarea_field()` — HTML retiré, sauts de
ligne conservés) : Présentation/Description, Points forts, Potentiel, Résultats/Performances (une
zone éditoriale, volontairement PAS une base structurée exhaustive de tous les concours), Origines
— commentaire, Production — commentaire, Conditions de vente/élevage/reproduction, Conseils de
croisement (disponible pour TOUS les chevaux, jamais conditionné au sexe ou à une catégorie).

**Deux ambiguïtés explicitement levées par des noms de meta sans équivoque** :
- `_gwseq_commentaire_production` (texte libre du professionnel sur la qualité/les résultats de la
  production) est une meta TOTALEMENT DISTINCTE de la Production CALCULÉE
  (`gwseq_get_horse_offspring()`, Étape 5), qui reste une donnée relationnelle dérivée des fiches
  Cheval, jamais stockée, jamais éditable ici — vérifié par test que ce fichier n'appelle jamais
  `gwseq_get_horse_offspring()`, et réciproquement que `cheval-pedigree.php` ne connaît jamais
  `_gwseq_commentaire_production`.
- `_gwseq_origines_commentaire` (texte libre sur l'intérêt d'une lignée — ex. « cette jument
  produit particulièrement bien avec des étalons de sang ») est totalement distinct du pedigree
  STRUCTURÉ (`_gwseq_pere_*`/`_gwseq_mere_*`, Étape 5) : jamais reconstruit à partir de ce texte,
  ni l'inverse — vérifié par test dans les deux sens (ni `cheval-pedigree.php`, ni le resolver, ne
  lisent ou n'écrivent jamais `_gwseq_origines_commentaire`).

#### Informations complémentaires — Ostéo-articulaire (`includes/cheval-editorial.php`)

Un unique champ texte libre, rendu dans sa propre meta box (séparée de « Présentation », pour une
organisation par blocs cohérents). Volontairement PAS un dossier vétérinaire : aucun historique de
soins, aucun champ vétérinaire, aucun traitement/ordonnance structuré, aucun stockage de radios,
aucune donnée médicale complexe — uniquement l'information synthétique que le professionnel
souhaite présenter ou conserver dans la fiche commerciale.

#### Architecture programmatique (§11, même principe que le pedigree — Étape 5)

Chaque nouvelle donnée dispose d'une fonction métier PURE (`gwseq_set_cheval_sport_indice()`,
`gwseq_set_cheval_genetic_indice()`, `gwseq_set_cheval_galerie()`, `gwseq_set_cheval_videos()`,
`gwseq_set_cheval_editorial()`), jamais couplée à `$_POST` ni à un nonce/capability — réutilisable
telle quelle par un futur importeur CSV/XLSX, une duplication de fiche, une API, ou une
synchronisation GWS Network. Le formulaire d'édition n'est qu'UN client parmi d'autres possibles de
ces fonctions. Conformément à la décision prise après l'Étape 4, les champs existants (identité,
commercialisation) n'ont pas été refactorés pour appliquer rétroactivement cette règle.

#### Compatibilité et migrations

Aucune migration destructive, aucune réécriture silencieuse de contenu existant, aucune suppression
de données lors d'une désactivation/réactivation du module (vérifié explicitement par les tests :
aucun des trois nouveaux fichiers n'appelle jamais `delete_post_meta()`). Les champs absents sur une
fiche créée avant l'Étape 6 restent simplement vides à la lecture, sans erreur ni valeur par défaut
inventée — aucune migration technique n'a été nécessaire.

#### Ajustement UX post-recette 0.12.0 — CD à deux décimales, navigation par onglets

La première recette runtime de l'Étape 6 (indices) a montré une fiche Cheval devenue trop longue à
faire défiler à mesure que les meta boxes s'accumulent, et une présentation du coefficient de
détermination (CD) trop peu lisible (« 0.9 » plutôt que « 0.90 »). Ajustement livré avant la
poursuite de la recette, strictement UX — aucun modèle de données, aucune règle métier, aucun
mécanisme de sauvegarde modifié.

**Présentation du CD à deux décimales** (`gwseq_format_cheval_indice_cd()`,
`includes/cheval-indices.php`) : uniquement une présentation (`number_format()`), jamais une
transformation du STOCKAGE — la meta reste le nombre PHP exact tel que sanitisé
(`gws_core_field_sanitize('number', ...)`), vérifié explicitement par un test qui relit la valeur
brute après un aller-retour admin et confirme l'absence de toute perte de précision (0.987 reste
0.987 en base, même si affiché « 0.99 »). Utilisée à la fois pour le champ CD du formulaire admin
(`step="0.01"`, cohérent avec cette précision) et pour `gwseq_cheval_genetic_indice_label()` (le
futur libellé public). Séparateur décimal volontairement le point à ce stade — un futur renderer
pourra localiser cet affichage (« 0,90 » en français) sans qu'aucune donnée n'ait besoin d'être
modifiée pour cela.

**Navigation par onglets** (`includes/cheval-admin-tabs.php`,
`assets/cheval-tabs-admin.js`/`cheval-tabs.css`) : Identité, Commercial, Pedigree (regroupe
Pedigree, Production et l'aperçu resolver dev-only), Indices, Médias, Présentation. Conçue comme
une COUCHE DE PRÉSENTATION PURE : aucune meta déplacée ni renommée, un seul et même formulaire WP
natif, aucune règle métier touchée, aucun mécanisme de sauvegarde WordPress modifié, aucun appel
AJAX, aucune donnée absente du DOM. Techniquement, le JavaScript ne déplace JAMAIS les meta boxes
dans le DOM : il bascule uniquement leur `style.display`, chaque bouton d'onglet référençant les
`id` HTML réels des boîtes concernées (`aria-controls`, qui accepte nativement une liste d'IDs
séparés par des espaces — ce qui permet à l'onglet Pedigree de contrôler ses trois boîtes sans
créer de conteneur supplémentaire). Les boîtes Production et aperçu pedigree restent enregistrées dans leur
contexte WordPress d'origine (`'side'`, colonne latérale — voir le correctif 0.12.1 ci-dessous) :
le regroupement fonctionnel sous l'onglet Pedigree ne dépend jamais de leur position DOM, seule
leur COLONNE d'apparition diffère de celle de la boîte Pedigree elle-même quand cet onglet est
actif. Depuis le correctif 0.12.2, l'image à la une native de WordPress (`postimagediv`) est
regroupée de la même façon sous l'onglet « Médias », aux côtés de Galerie/Vidéos — voir « Correctif
RÉGRESSION BLOQUANTE 0.12.2 » plus bas. Restent volontairement HORS du système d'onglets, toujours
visibles dans la colonne latérale : la boîte de développement Global Horse ID et « Ordre
d'affichage ».

Réutilise les classes CSS natives de WordPress (`.nav-tab-wrapper`/`.nav-tab`/`.nav-tab-active`,
déjà utilisées par les écrans de réglages du cœur WP) plutôt que d'inventer un habillage visuel
propre. Un bouton « Enregistrer / Mettre à jour » est ajouté dans la zone de navigation : il ne
crée AUCUN nouveau mécanisme de sauvegarde — il lit le libellé du vrai bouton natif `#publish` et
déclenche un simple `.click()` dessus (jamais `form.submit()`, pour préserver tout comportement
JS déjà attaché par WordPress à ce bouton — heartbeat, confirmation...). Accessibilité : véritable
pattern ARIA `tablist`/`tab`/`tabpanel` (jamais une rangée de `<div>` cliquables), navigation
clavier complète (flèches gauche/droite avec bouclage, Home/End), tabindex flottant (seul l'onglet
actif est atteignable par Tab), état actif exposé via `aria-selected`. L'onglet actif est
mémorisé en session (`sessionStorage`, une seule clé partagée, volontairement simple) pour
persister d'une navigation à l'autre sans infrastructure complexe. **Sans JavaScript**, la fiche
reste entièrement utilisable et enregistrable : toutes les boîtes s'affichent simplement empilées
dans l'ordre natif WordPress, comme avant cet ajustement.

#### Correctif RÉGRESSION BLOQUANTE 0.12.1 — navigation par onglets inopérante

La reprise de la recette runtime a échoué immédiatement après 0.12.0 : la navigation par onglets
n'apparaissait pas du tout, et l'écran risquait de perdre l'accès visuel à des meta boxes
existantes. Deux causes racines distinctes, corrigées sans aucun nouveau développement.

**CAUSE 1 (bloquante, systématique) — mauvaise cible DOM pour l'insertion de la barre** : le
script appelait `postbody.insertBefore(wrapper, normalSortables)`, où `postbody` référence
`#post-body-content`. Sur l'écran classique de WordPress
(`wp-admin/edit-form-advanced.php`), `#post-body-content` et `#normal-sortables` (qui contient les
meta boxes de la colonne principale, dans `#postbox-container-2`) sont deux enfants DISTINCTS de
`#post-body` — jamais l'un dans l'autre. `insertBefore()` exige que son second argument soit un
enfant réel du nœud appelant ; sinon, la spécification DOM impose la levée d'une `DOMException`
dans tout navigateur — ce qui arrêtait le script à cette ligne précise, avant même de construire la
barre d'onglets. **Correctif** : la barre est désormais insérée comme premier enfant de
`#normal-sortables` lui-même, son véritable ancêtre DOM direct.

**CAUSE 2 (risque de disparition de meta boxes) — changement de contexte non nécessaire** : 0.12.0
avait fait passer Production et l'aperçu pedigree du contexte `'side'` à `'normal'` pour les
regrouper visuellement avec Pedigree. Un changement de contexte `add_meta_box()` d'une version à
l'autre expose un piège connu de WordPress : l'ordre des meta boxes par écran est mémorisé par
utilisateur (`meta-box-order_{$screen}`), associé à un COUPLE identifiant/contexte précis — un
changement de contexte peut faire perdre le rattachement réel d'une boîte lors de la fusion interne
de `add_meta_box()` pour un utilisateur ayant déjà navigué sur cet écran avant la mise à jour, la
boîte concernée n'étant alors plus jamais rendue. **Correctif** : contexte `'side'` restauré pour
ces deux boîtes, exactement comme avant l'Étape 6 — sans aucune conséquence sur le regroupement
sous l'onglet Pedigree (voir plus haut).

**Aucune donnée, règle métier ou mécanisme de sauvegarde n'a été affecté par ces deux correctifs** —
strictement des corrections de câblage DOM/PHP de la couche de présentation ajoutée en 0.12.0.

**Renforcement méthodologique des tests** : les 73 assertions dédiées aux onglets n'avaient pas
détecté la régression bloquante, car elles ne font que scanner le TEXTE SOURCE du script JavaScript
— jamais l'exécuter. Nouveau fichier `tests/gws-equestrian-cheval-admin-tabs-runtime-test.js`
(24 assertions, exécuté via `node`, aucune dépendance npm ajoutée au projet) : reproduit fidèlement
la structure DOM réelle de l'écran classique d'édition (colonnes latérale/principale bien
distinctes, avec le vrai bouton `#publish`), exécute réellement le script dans ce DOM simulé
(module `vm` de Node), et vérifie le câblage effectif — absence d'exception, insertion réelle au
bon endroit, regroupement Pedigree/Production/aperçu fonctionnel même à cheval sur deux colonnes
physiques, bascule effective de visibilité au clic et au clavier, aucune meta box jamais retirée du
DOM, bouton rapide déclenchant réellement le bouton natif. Ce type de test, absent avant 0.12.1,
aurait immédiatement détecté cette régression.

#### Correctif RÉGRESSION BLOQUANTE 0.12.2 — onglet Identité vide ; Photo principale dans Médias

La reprise de la recette a confirmé la barre d'onglets fonctionnelle (0.12.1), mais a révélé un
nouveau bloquant : l'onglet Identité affichait une zone vide, rendant tous les champs historiques
de l'Étape 4 (sexe, année de naissance, robe, race/stud-book, taille, éleveur, propriétaire,
SIRE/UELN) inaccessibles.

**CAUSE RACINE EXACTE** : l'ID de la boîte (`gwseq-cheval-identite`), son contexte WordPress
(`'normal'`, inchangé depuis l'Étape 4), son ordre d'enregistrement et la configuration du tableau
d'onglets (`gwseq_cheval_admin_tabs_config()`) étaient TOUS corrects — le mapping onglet → meta box
n'était pas en cause. La boîte était en réalité laissée REPLIÉE par le mécanisme natif de
repli/dépli de WordPress (classe CSS `.closed`, posée par un clic sur son titre — un état
totalement indépendant de nos onglets). Or la règle CSS native qui masque le contenu d'une boîte
repliée, `.postbox.closed .inside { display: none; }`, cible l'élément `.inside` — un ENFANT de la
boîte, où vit tout son contenu réel — jamais le conteneur `.postbox` lui-même. Notre système
d'onglets ne pilotait que `box.style.display` sur ce conteneur : le rétablir à vide le rendait bien
visible, mais son `.inside` restait masqué par la règle CSS native, indépendamment de notre style
inline — d'où une boîte visuellement "vide" malgré un onglet correctement actif.

**Correctif** (`assets/cheval-tabs-admin.js`) : l'activation d'un onglet lève désormais
systématiquement ce repli natif (`classList.remove('closed')`) pour CHACUNE de ses boîtes, et
synchronise l'attribut ARIA `aria-expanded` du bouton natif de repli/dépli (`.handlediv`) sur
`"true"` — un onglet actif affiche toujours un contenu déplié, plus jamais une boîte vide, quel que
soit l'état de repli dans lequel WordPress l'avait laissée.

**Photo principale intégrée à l'onglet Médias** (§2 de la demande, ajustement livré dans la même
passe) : la boîte NATIVE WordPress de l'image à la une (`postimagediv`) rejoint désormais
Galerie/Vidéos sous l'onglet "Médias", selon EXACTEMENT le même mécanisme que Production/aperçu
pedigree sous "Pedigree" (0.12.1) — la boîte native n'est ni déplacée dans le DOM ni ré-enregistrée
par ce plugin (elle reste dans sa colonne native), seule sa VISIBILITÉ est désormais pilotée par le
système d'onglets. **Aucun second champ, aucun second attachment ID, aucune synchronisation
parallèle, aucun stockage spécifique** : la Featured Image de WordPress reste l'unique source de
vérité, lue/modifiée exclusivement par sa propre interface native (`wp.media()`). Elle n'apparaît
jamais deux fois : n'ayant jamais été dupliquée, seule sa colonne d'apparition (latérale, comme
toujours) est désormais conditionnée à l'onglet actif — masquée sous tout autre onglet, visible
uniquement sous "Médias", aux côtés de la boîte Galerie/Vidéos du plugin (dans la colonne
principale). Publier, Catégories, "Ordre d'affichage" et Global Horse ID (dev-only) restent
inchangés, toujours visibles dans la colonne latérale.

**Aucune donnée, règle métier ou mécanisme de sauvegarde n'a été affecté par ces deux correctifs.**
Sans JavaScript, tous les champs Identité restent accessibles et la Photo principale reste
modifiable via la mécanique native WordPress, exactement comme avant tout ajustement onglets.

**Renforcement des tests** : `tests/gws-equestrian-cheval-admin-tabs-runtime-test.js` reproduit
désormais une boîte Identité déjà repliée (`.closed`, avec son bouton `.handlediv`) AVANT
l'exécution du script, et vérifie que l'activation de son onglet la déplie réellement (classe
retirée, `aria-expanded="true"` restauré) — ce test aurait immédiatement détecté cette régression.
Nouvelles assertions couvrant également le regroupement Photo principale + Galerie/Vidéos sous
Médias (visibilité conjointe, masquage conjoint sous tout autre onglet, absence de duplication) :
31 assertions au total pour ce fichier (+ 4 nouvelles assertions déclaratives PHP).

#### Limitations connues (Étape 6)

- **Aucun âge minimum de reproduction** pour les indices sportifs/génétiques ni pour le pedigree —
  volontairement, comme demandé.
- **Galerie sans glisser-déposer** : le réordonnancement se fait par boutons ↑/↓ uniquement, choix
  assumé pour rester simple (pas de bibliothèque de drag-and-drop).
- **Aucune restriction à une liste de fournisseurs oEmbed connus** pour les vidéos : seule la
  validité du schéma (http/https) est vérifiée à ce stade — la compatibilité oEmbed réelle
  (YouTube, Vimeo...) ne sera exercée qu'au futur rendu (hors périmètre de cette étape).
- **Pas de validation croisée entre champs éditoriaux et données structurées** : rien n'empêche un
  professionnel de saisir un texte "Origines" contradictoire avec le pedigree structuré — assumé,
  ce sont deux sources de vérité volontairement indépendantes (§7 de la demande).
- **Onglets sans glisser-déposer ni réordonnancement** (ajustement 0.12.0) : l'ordre et la
  composition des six onglets sont fixes, définis par `gwseq_cheval_admin_tabs_config()` — aucune
  personnalisation par utilisateur n'est prévue à ce stade.
- **Persistance de l'onglet actif limitée à `sessionStorage`** (ajustement 0.12.0) : une seule clé
  partagée pour toutes les fiches Cheval (pas de mémorisation par fiche), propre à l'onglet
  navigateur courant — non synchronisée entre plusieurs onglets/fenêtres ouverts simultanément sur
  des chevaux différents, et perdue à la fermeture de la session navigateur. Choix assumé pour
  rester simple, comme demandé.

#### Pistes hors périmètre (aucun développement à ce stade)

Rendu public final de la fiche, PDF/print (la règle « année de naissance uniquement, jamais l'âge
calculé » pour le print est actée mais non implémentée — aucun PDF n'existe encore), QR code,
catalogue, Social Kit, publication Meta, import Excel/CSV, synchronisation GWS Network, IFCE,
historique annuel des indices, résultats sportifs structurés exhaustifs, dossier vétérinaire,
réservation/paiement, logique conditionnelle selon le type de cheval.

#### Procédure de recette — Étape 6

À réaliser dans WordPress Local, sans écrire de code :

1. Ouvrir une fiche Cheval existante : vérifier la présence des nouvelles meta boxes (Indices,
   Médias, Présentation, Informations complémentaires) sans qu'aucune donnée déjà saisie
   (identité, commercialisation, pedigree) n'ait été perdue ou modifiée.
2. Renseigner un ISO (valeur + année) puis enregistrer : recharger et vérifier la persistance
   exacte des deux valeurs, affichées dans des champs séparés.
3. Renseigner ICC et IDR indépendamment de l'ISO : vérifier qu'aucun des trois n'affecte les
   autres.
4. Enregistrer un nouvel ISO différent sur la même fiche : vérifier que l'ancien est bien remplacé
   (pas d'historique, pas de doublon).
5. Renseigner un BSO avec une valeur positive (ex. 12) et un CD (ex. 0,90) : vérifier la
   persistance exacte des deux valeurs séparément.
6. Renseigner un BSO avec une valeur négative (ex. -8) : vérifier que le signe est bien conservé.
7. Ajouter des images à la galerie depuis le bouton dédié : vérifier l'ouverture de la médiathèque
   WordPress native, la sélection multiple, l'ajout effectif des vignettes.
8. Réordonner les images de la galerie avec les boutons ↑/↓ : vérifier que l'ordre est bien
   conservé après enregistrement.
9. Retirer une image de la galerie, enregistrer, puis vérifier dans Médiathèque que le fichier
   n'a PAS été supprimé.
10. Vérifier qu'ajouter/retirer des images de la galerie ne modifie jamais l'image à la une
    (photo principale), et réciproquement.
11. Tenter d'ajouter une 10e image à la galerie (9 déjà présentes) : vérifier que le bouton
    d'ajout est désactivé.
12. Ajouter une vidéo (URL YouTube ou Vimeo valide + titre facultatif) : enregistrer, recharger,
    vérifier la persistance.
13. Ajouter une vidéo avec une URL invalide (texte quelconque) : vérifier qu'elle disparaît après
    enregistrement (jamais stockée).
14. Ajouter 10 vidéos puis vérifier que le bouton d'ajout d'une 11e est désactivé.
15. Renseigner chacun des 8 champs de la meta box « Présentation » : vérifier la persistance
    exacte de chacun, y compris avec des sauts de ligne.
16. Renseigner le champ « Ostéo-articulaire » (meta box séparée) : vérifier sa persistance.
17. Vérifier que le champ « Production — commentaire » n'affiche ni ne modifie jamais la meta box
    « Production » (calculée) présente par ailleurs sur la fiche.
18. Vérifier que le champ « Origines — commentaire » n'affiche ni ne modifie jamais le pedigree
    structuré (meta box « Pedigree »).
19. Désactiver puis réactiver le module (`config/modules.php`) : vérifier qu'aucune donnée
    d'indice, de galerie, de vidéo ou d'éditorial n'a été modifiée ou supprimée.
20. Ouvrir une fiche cheval créée avant cette version (jamais enregistrée avec ces nouveaux
    champs) : vérifier que tous les nouveaux champs apparaissent simplement vides, sans erreur.

**Étapes ajoutées pour l'ajustement UX post-recette 0.12.0** (CD à deux décimales, navigation par
onglets) :

21. Vérifier l'apparition des six onglets (Identité, Commercial, Pedigree, Indices, Médias,
    Présentation) en haut de la colonne principale de la fiche Cheval, avec le bon regroupement de
    boîtes sous chacun — notamment Production et l'aperçu pedigree (dev/local) sous « Pedigree », et
    désormais la Photo principale (image à la une native) sous « Médias », aux côtés de
    Galerie/Vidéos. Toutes deux apparaissent dans leur COLONNE NATIVE (latérale pour Production/
    aperçu/Photo principale, comme avant l'Étape 6) mais uniquement quand leur onglet respectif est
    actif. Vérifier que la boîte Global Horse ID (dev/local) et « Ordre d'affichage » restent
    visibles en permanence dans la colonne latérale, hors du système d'onglets, quel que soit
    l'onglet actif.
21bis. Cliquer sur l'onglet Identité : vérifier IMMÉDIATEMENT que tous les champs historiques sont
    bien visibles et saisissables (sexe, année de naissance, robe, race/stud-book, taille, éleveur,
    propriétaire, SIRE, UELN) — et non une zone vide. Si la boîte a été repliée manuellement au
    préalable (clic sur son titre), vérifier qu'elle réapparaît bien dépliée en revenant sur cet
    onglet (correctif 0.12.2).
21ter. Cliquer sur l'onglet Médias : vérifier que la Photo principale (aperçu de l'image à la une,
    boutons natifs pour la définir/remplacer/retirer) apparaît bien aux côtés de Galerie et Vidéos.
    Modifier la Photo principale depuis cet onglet, enregistrer, recharger : vérifier la
    persistance, et que l'image à la une du site (front, autres écrans admin) reflète bien ce
    changement — une seule et même donnée, jamais un second champ.
22. Cliquer sur chaque onglet : vérifier que seules les boîtes du groupe correspondant s'affichent,
    sans perte visuelle de contenu déjà saisi, et que les boîtes gardent leur comportement natif
    (repli/dépli au clic sur leur propre titre, y compris après un changement d'onglet).
23. Naviguer au clavier dans la barre d'onglets (Tab pour l'atteindre, flèches gauche/droite pour
    circuler avec bouclage, Home/End pour aller au premier/dernier onglet) : vérifier que le focus
    visuel suit et que l'onglet activé correspond bien au contenu affiché.
24. Cliquer sur le bouton « Enregistrer »/« Mettre à jour » ajouté dans la barre d'onglets :
    vérifier qu'il déclenche exactement la même sauvegarde que le bouton natif de la colonne
    latérale (même libellé, mêmes données enregistrées, aucune double soumission).
25. Changer d'onglet, recharger la page (ou naviguer puis revenir) : vérifier que l'onglet
    précédemment actif reste sélectionné (persistance via `sessionStorage`).
26. Désactiver JavaScript dans le navigateur puis rouvrir la fiche : vérifier que toutes les boîtes
    s'affichent simplement empilées (sans barre d'onglets) et que la fiche reste normalement
    modifiable et enregistrable.
27. Saisir un CD avec plusieurs décimales (ex. 0,987 dans un champ qui accepte le point) : vérifier
    que le formulaire affiche bien deux décimales après enregistrement (« 0.99 »), et confirmer
    dans la base (ou en resaisissant une valeur proche) qu'aucune perte de précision réelle n'a eu
    lieu au stockage.
28. Repasser en revue les points 1 à 20 ci-dessus dans la nouvelle interface à onglets : confirmer
    l'absence de toute régression sur pedigree, Production, filtres parents, indices, galerie,
    vidéos, contenus éditoriaux, Global Horse ID et données commerciales.

### Pedigree (Étape 5)

**Deux types de parent, chacun indépendamment pour le Père et pour la Mère** : soit une fiche
`gwseq_cheval` déjà présente dans GWS (référence par ID, jamais par nom — renommer le parent ne
casse jamais la relation), soit un **ascendant externe structuré** (Nom + Race/Stud-book
facultative). Ni le père ni la mère ne sont jamais requis (un pedigree incomplet est parfaitement
acceptable, voir §25 : l'absence de donnée reste une absence, jamais un « Non renseigné »
affiché artificiellement demain sur le site).

**Un ascendant externe n'est pas une feuille terminale** : il peut lui-même avoir un père et une
mère, également externes, jusqu'à 4 générations — pensé spécifiquement pour un marchand de
chevaux ou un cavalier professionnel dont la quasi-totalité des ascendants ne sont pas des
chevaux qu'il gère dans GWS. Une telle structure permet de saisir un pedigree complet sans jamais
créer une seule fiche `gwseq_cheval` artificielle pour un ancêtre qui n'a aucune raison métier
d'être géré comme un cheval du client. Chaque génération reste facultative : l'utilisateur
s'arrête où il connaît son pedigree.

**Race/Stud-book d'un ascendant externe harmonisé avec la fiche Cheval** (correction post-recette,
0.6.0) : ce champ était initialement un texte libre, source constatée d'hétérogénéité en usage
réel (« SF »/« sf »/« Selle Français »...). Il réutilise désormais très exactement le référentiel
de la fiche Cheval (`gwseq_cheval_race_options()`, jamais dupliqué) à chaque génération de chaque
branche externe : liste fermée + « Autre » avec précision libre.

**Interface en divulgation progressive, désormais contextuelle** : Nom et Race d'un ascendant
externe sont toujours visibles ; un bouton natif « + Renseigner ses origines de KANNAN » (élément
HTML `<details>`, sans JavaScript nécessaire à son fonctionnement) révèle le père et la mère de
cet ascendant, et ainsi de suite jusqu'à la profondeur autorisée. Personne n'est jamais confronté
d'emblée à un immense formulaire listant tous les ascendants possibles. **Correction post-recette
(0.6.0)** : la recette réelle du pedigree de Jamerose a montré qu'un simple « Père »/« Mère »
répété à chaque niveau fait rapidement perdre le fil (erreur de saisie constatée). Chaque niveau
affiche désormais un intitulé construit à partir du nom déjà enregistré du cheval concerné
(« Père de UNTOUCHABLE 27 », puis en développant : « Père de HORS LA LOI II »...) — jamais une
nomenclature généalogique complexe (« grand-père paternel »...), toujours le nom comme repère. Un
repli explicite (« cet ascendant ») s'applique tant que le nom n'est pas encore saisi. **Correction
0.7.0** : un premier essai sans JavaScript (0.6.0) s'est révélé insuffisant en recette réelle — un
nom fraîchement saisi ne se reflétait dans ces intitulés qu'après enregistrement de la fiche. Une
écoute déléguée légère (`assets/cheval-admin.js`), strictement scopée à cet écran, met désormais
ces intitulés à jour EN DIRECT pendant la frappe — sans jamais lire ni modifier la valeur du champ
Nom lui-même (aucune normalisation de casse, aucune suppression d'accent appliquée à la donnée
envoyée au serveur ; la transformation visuelle est une prévisualisation, le rendu réellement
autoritaire restant celui produit par le serveur). Un compteur discret (« Génération N sur 4 »,
« Génération 4 sur 4 — dernière génération ») accompagne chaque niveau — la recette a aussi
montré que l'utilisateur ne savait pas jusqu'où remonter alors que GWS connaît parfaitement cette
limite. À la génération 4, plus aucun contrôle « + Renseigner ses origines » n'est proposé (arrêt
visuel strict) ; la limite serveur, elle, reste inchangée et est la seule garantie réelle contre
une requête manipulée.

**Convention de présentation des noms de chevaux** (nouveau helper partagé,
`gwseq_format_horse_name_display()` dans `cheval-fields.php`) : dans les intitulés contextuels du
pedigree, un nom s'affiche en MAJUSCULES ET SANS ACCENTS (« Étoile du Lys » → « ETOILE DU LYS » ;
apostrophes/traits d'union/chiffres/espaces conservés). Uniquement une présentation : `post_title`
et le nom d'un ascendant externe restent enregistrés exactement tels que saisis, jamais transformés
à l'enregistrement. Ne s'applique jamais à Race/Stud-book, qui reste une valeur structurée via
référentiel. Réutilisable plus tard par le front, un export PDF, l'impression, un catalogue, ou le
Social Kit.

#### Correctif BLOQUANT 0.7.0 — corruption des noms accentués

La reprise de la recette a révélé qu'un nom accentué (« Native de Félines ») était corrompu en
base après enregistrement (« Native de Fu00e9lines »). **Cause racine exacte**, sans rapport avec
le helper de présentation ci-dessus : `gwseq_set_horse_parent()` encodait l'arbre externe avec
`wp_json_encode($tree)` sans le drapeau `JSON_UNESCAPED_UNICODE`. Sans ce drapeau, `json_encode()`
échappe tout caractère non-ASCII en séquence littérale `\uXXXX` (« é » → les six caractères `\`,
`u`, `0`, `0`, `e`, `9` — un antislash réel dans la chaîne). Cette chaîne passe ensuite à
`update_post_meta()`, laquelle appelle EN INTERNE `wp_unslash()` sur la valeur avant stockage
(comportement natif de `update_metadata()` dans WordPress, totalement indépendant de ce module) :
`wp_unslash()` ne distingue pas un antislash « magic quotes » d'un antislash faisant partie du
contenu légitime, et retire celui de `é`, laissant le texte littéral `u00e9` — une chaîne JSON
toujours syntaxiquement valide (`json_decode()` ne remonte donc jamais d'erreur), mais dont le
contenu est désormais faux. Une fois ce nom corrompu réaffiché puis réenregistré, la corruption
devient permanente. Le helper de présentation n'était en rien en cause : il ne fait qu'afficher
fidèlement une donnée déjà corrompue en amont (« u00e9 » en majuscules donne « U00E9 »,
exactement le symptôme observé) et n'est appelé dans aucune fonction de sanitation ou de
persistance.

**Correctif** : `wp_json_encode($tree, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)` — les
caractères accentués sont désormais écrits tels quels (aucun antislash), donc rien que
`wp_unslash()` puisse corrompre.

**Découverte méthodologique** : les stubs de test `wp_unslash()`/`update_post_meta()`/
`wp_json_encode()` du fichier de tests Pedigree étaient de simples passe-plats, non fidèles au
comportement réel de WordPress sur ce point précis — c'est cette infidélité, et non un manque de
couverture, qui a laissé ce bug traverser 563 assertions déjà vertes. Les trois stubs ont été
rendus fidèles (vrai `stripslashes()`, options JSON réellement transmises), rendant le bug
reproductible — et donc vérifiable — sans WordPress réel.

**Compatibilité** : aucune migration automatique des données déjà corrompues (une reconstruction
du type `u00e9 → é` étant jugée insuffisamment sûre pour être tentée à l'aveugle sur l'ensemble
des pedigrees) — la fiche Jamerose de recette sera corrigée manuellement par re-saisie après
déploiement de ce correctif. Une donnée correcte ne peut en revanche plus être corrompue après
cette version.

**Une seule source active, jamais de mélange, aucune destruction accidentelle** : passer d'un
mode à l'autre (GWS ⇄ externe) sur une même relation ne supprime jamais l'autre branche — elle
reste stockée mais inactive, récupérable si l'utilisateur revient sur son choix. Le resolver ne
lit toujours que la branche correspondant au mode actif : aucun mélange possible entre une
donnée GWS et une donnée externe pour une même relation. Le rattachement d'un ascendant externe à
une vraie fiche GWS (« je viens de créer la fiche de Jument X, elle était jusqu'ici externe »)
est toujours un choix explicite de l'utilisateur dans le `<select>` — jamais une correspondance
devinée automatiquement par le système à partir d'un nom identique.

**Aucune fiche fantôme, aucune base globale d'ancêtres** : les ascendants externes ne sont jamais
des Custom Post Types ni des entités éditoriales du site — ce sont des données structurées
propres au pedigree du cheval qui les référence. Un même étalon externe (ex. « Kannan ») peut
être ressaisi indépendamment dans plusieurs pedigrees sans qu'aucun lien ne soit établi entre ces
saisies ni qu'aucune déduplication automatique ne soit tentée — choix de simplicité assumé pour
la V1 (un futur Network ou référentiel équin pourra améliorer cela).

**Resolver** (`includes/pedigree-resolver.php`) : transforme les relations stockées en une
structure de données déterministe (jamais de HTML), réutilisable par le futur rendu web
(Étape 8), un export PDF/catalogue, ou une projection API. Traite les branches GWS et externes de
façon rigoureusement identique du point de vue du comptage des générations — un même pedigree
peut donc mélanger naturellement une fiche GWS dont un ascendant intermédiaire a lui-même un
parent externe, sans aucune ambiguïté de profondeur (voir « Modèle de données » ci-dessous pour
le détail). Protégé contre les cycles (directs, déjà rejetés à la sauvegarde pour une relation
GWS ; indirects, détectables seulement à la résolution) et contre un parent supprimé
définitivement (dégradation propre, jamais d'erreur fatale). **Correction 0.7.0** : un nœud de la
génération 4 (la dernière autorisée) est désormais strictement terminal — ses clés `father`/
`mother` sont absentes, pas seulement `null`. La recette avait révélé qu'un nœud de génération 4
affichait, dans la boîte de vérification, « Père : Non renseigné »/« Mère : Non renseigné »,
laissant croire à tort qu'une génération 5 existerait dans le modèle alors qu'elle est hors
périmètre du pedigree V1, jamais saisissable ni stockée.

**Production** : les chevaux référençant une fiche comme père ou mère GWS sont retrouvés par
requête inverse, jamais stockés sur la fiche du parent — seules de vraies relations entre deux
fiches `gwseq_cheval` comptent, un ascendant externe n'est jamais une source de « production »
calculable.

#### Correctif complémentaire 0.8.0 — suppression d'un ascendant externe vide

La reprise de recette a révélé un dernier défaut : un ascendant externe créé (nom saisi), puis
entièrement vidé par l'utilisateur en restant sur le mode « Ascendant hors GWS », continuait
d'exister en base et réapparaissait à la réouverture de la fiche.

**Cause exacte** : un nœud sans nom n'a jamais pu être stocké — `gwseq_sanitize_external_ancestor_tree()`
renvoie déjà `null` dès qu'un nom est vide, y compris récursivement pour tout sous-arbre (garantie
déjà en place, inchangée par ce correctif). Le vrai défaut se situait dans
`gwseq_set_horse_parent()` : quand l'arbre sanitisé devenait ainsi entièrement `null` (l'ascendant
vidé), seule la meta `..._mode` était réinitialisée à `''` — l'ancienne meta `..._externe`
(contenant encore le nom précédemment saisi) restait, elle, intacte en base. Ce comportement est le
bon pour un CHANGEMENT DE MODE (GWS ⇄ externe, où la conservation non destructive est volontaire,
voir plus haut) mais pas ici : l'utilisateur restait sur « externe » et avait activement tout
effacé, sans que la donnée stockée ne suive — d'où la résurrection de l'ancien ascendant.

**Correctif** : quand l'arbre sanitisé est entièrement vide alors que le mode soumis reste
`external`, `..._externe` est désormais explicitement supprimée (`delete_post_meta()`) plutôt que
laissée à sa valeur précédente — sans jamais toucher à la branche GWS (`_id`) ni à la relation de
l'autre parent (père et mère restent gérés indépendamment).

**Bouton explicite « Supprimer cet ascendant »** (`assets/cheval-admin.js`) : à chaque génération,
un bouton permet de vider en un clic un nœud et toute sa sous-branche, avec une confirmation
(« Supprimer cet ascendant et ses origines ? ») uniquement si des origines enfants sont déjà
renseignées. Purement une remise à vide des champs déjà présents dans le DOM côté client — jamais
un appel serveur, jamais une suppression d'élément du DOM, jamais de système de corbeille : la
suppression réelle en base reste l'effet du mécanisme ci-dessus au prochain enregistrement. Le
texte de confirmation est fourni par PHP via l'attribut `data-delete-confirm` du conteneur
`.gwseq-pedigree-i18n`, jamais codé en dur en JavaScript.

**Ce qui reste inchangé** : un nœud partiellement renseigné (un nom seul, par exemple) est toujours
conservé, tout comme un nœud dont un descendant reste renseigné à un niveau plus profond ; le
resolver ne produisait déjà, et ne produit toujours jamais, de nœud "external" fantôme sans nom
(garde déjà en place, désormais testée explicitement, y compris sur une donnée héritée d'avant ce
correctif) ; une relation vers une fiche GWS continue de se désactiver via le mode « Non renseigné »
sans jamais supprimer la fiche Cheval référencée.

#### Correctif intégrité 0.9.0 — même cheval GWS comme père et mère

La recette confirme que l'auto-parenté est correctement empêchée (le cheval édité n'est jamais
proposé comme son propre parent), mais révèle qu'il restait possible de sélectionner le MÊME cheval
GWS comme père ET comme mère d'un même cheval — une incohérence biologique distincte de
l'auto-parenté.

**Validation serveur** : `gwseq_set_horse_parent()` refuse désormais l'enregistrement d'une
relation `gws` qui créerait ce conflit —
`gwseq_horse_parent_conflicts_with_other_role()` compare la relation soumise à la relation ACTIVE
déjà enregistrée pour l'autre rôle (père ↔ mère). En cas de conflit, la fonction retourne `false`
(comportement déterministe et documenté) et NE MODIFIE AUCUNE meta pour ce rôle : la relation
existante, le cas échéant, n'est jamais supprimée ni remplacée silencieusement par une valeur
incohérente. Cette validation s'applique identiquement à un appel programmatique direct — c'est
exactement la même fonction que celle qu'un futur importeur CSV/XLSX utiliserait — sans dépendre de
`$_POST` ni de JavaScript.

**UX admin** (`assets/cheval-admin.js`) : le cheval déjà actif dans l'autre sélecteur est désormais
désactivé (`disabled`) dans le sélecteur courant — jamais retiré de la liste, jamais une valeur déjà
sélectionnée modifiée automatiquement. Si la sélection de l'un des deux sélecteurs change,
l'exclusion de l'autre se resynchronise en direct. Défense en profondeur : l'option est déjà rendue
désactivée dès le rendu serveur (avant toute exécution de script), et la validation serveur
ci-dessus reste de toute façon la seule garantie réelle, y compris JavaScript désactivé.

**Ce qui reste inchangé** : l'auto-parenté reste protégée exactement comme avant ; deux ascendants
externes ne sont jamais comparés par leur nom — un nom identique (y compris une homonymie avec un
cheval GWS) ne prouve jamais qu'il s'agit du même cheval, aucun rapprochement automatique n'est
tenté ; les branches externes inactives conservées lors d'un changement de mode ne sont jamais
affectées par cette validation ; le resolver et le modèle de pedigree ne sont pas modifiés
au-delà de cette contrainte.

**Deux corrections lexicales validées, profitées de cette passe** : le libellé du mode GWS devient
« Cheval déjà enregistré » (au lieu de « Cheval déjà présent dans GWS ») et celui du mode externe
devient « Nouvel ascendant » (au lieu de « Ascendant hors GWS ») ; le texte de la boîte d'aperçu
développeur devient « Aperçu du pedigree enregistré — actualisé après sauvegarde. ».

#### Correctif filtrage métier 0.10.0 — sexe et année de naissance des parents GWS

La recette a permis d'identifier deux règles métier supplémentaires, applicables UNIQUEMENT à une
relation vers un cheval déjà enregistré dans GWS — jamais à un « Nouvel ascendant » saisi
manuellement.

**Filtrage selon le sexe** : mâle/entier et hongre sont autorisés comme père (un cheval a pu
reproduire avant sa castration), seule une femelle est autorisée comme mère ; un sexe non
renseigné reste toujours autorisé pour les deux rôles — l'absence de donnée n'est jamais une
interdiction. Le sexe d'un cheval n'est jamais déduit ni modifié automatiquement à partir de son
usage comme père ou mère.

**Filtrage selon l'année de naissance** : seule l'année est disponible (pas une date complète),
d'où une règle volontairement simple — un candidat à l'année connue doit être né STRICTEMENT avant
son produit (la même année ou une année postérieure est interdite), sans aucun âge minimum de
reproduction en V1. Année du candidat ou du produit inconnue : aucun filtre appliqué.

**Règle métier unique et centrale** : `gwseq_horse_parent_candidate_rejection_reason()` combine
désormais l'auto-référence, la compatibilité de sexe, la compatibilité d'année de naissance et le
conflit avec l'autre rôle (0.9.0) — le rendu du formulaire, `gwseq_set_horse_parent()` (validation
serveur) et tout futur import s'appuient tous sur cette même fonction, jamais une règle dupliquée
ailleurs. En cas de rejet : comportement déterministe (`false`), aucune écriture partielle, la
relation existante n'est jamais supprimée ni remplacée silencieusement.

**UX admin** : réutilise le mécanisme de désactivation d'options déjà en place pour le conflit
père/mère (0.9.0), avec une indication courte de la raison (« sexe incompatible », « année
incompatible ») directement dans le libellé de l'option — pas de système UX plus lourd que
nécessaire. Sexe et année étant des propriétés FIXES du candidat (contrairement au conflit avec
l'autre rôle, qui dépend de la sélection courante de l'autre sélecteur), `assets/cheval-admin.js`
ne les reconsidère JAMAIS en direct : un attribut `data-gwseq-locked-disabled`, posé côté serveur,
verrouille explicitement ces options contre toute réactivation par le script.

**Modification ultérieure des données** (cas documenté, non traité automatiquement) : une relation
valide à sa création reste enregistrée telle quelle si les données du parent ou du produit changent
ensuite — par exemple un entier enregistré comme père puis castré (sa fiche passe à Hongre) reste
un père valide, puisque Hongre est autorisé comme père. Plus généralement, si une modification
ultérieure du sexe ou de l'année de naissance rendait une relation existante réellement
incohérente, elle ne serait ni supprimée ni modifiée automatiquement — aucun contrôle rétroactif
n'est construit en V1 ; piste actée pour une amélioration future (audit/avertissement d'intégrité).

**Ascendants externes non affectés** : aucune comparaison par nom, aucun champ sexe ajouté
uniquement pour satisfaire cette validation, aucune contrainte des chevaux GWS appliquée, branches
externes inactives conservées lors d'un changement de mode exactement comme avant.

#### Modèle de données (Étape 5)

Pour chaque cheval, deux relations indépendantes : Père et Mère. Chacune est stockée sur trois
meta (préfixe `_gwseq_pere_`/`_gwseq_mere_`) :

| Meta | Type | Rôle |
|---|---|---|
| `..._mode` | string enum | `''` (aucune relation) / `'gws'` / `'external'` — seule source de vérité sur la branche active |
| `..._id` | integer | ID du cheval GWS référencé (branche GWS ; peut rester stocké même inactif) |
| `..._externe` | string (JSON) | Arbre récursif `{name, race, race_autre, father, mother}` (branche externe ; peut rester stocké même inactif) |

L'arbre JSON de la branche externe a la même forme à chaque niveau : `name` (texte, obligatoire
pour qu'un nœud existe), `race` (code technique du référentiel `gwseq_cheval_race_options()`,
toujours facultatif), `race_autre` (texte, uniquement si `race === 'autre'`), `father`/`mother`
(même structure, récursivement, jusqu'à `GWSEQ_PEDIGREE_MAX_DEPTH - 1` = 3 niveaux sous le premier
ascendant externe — soit 4 générations au total pour cette branche, cohérent avec la profondeur du
resolver). Choix JSON plutôt que `serialize()` PHP : lisible, indépendant du langage
d'implémentation, donc plus simple à valider, faire évoluer (le mécanisme de migration déjà
existant de gws-core prendrait le relais si la forme devait un jour changer), importer (un futur
import CSV/XLSX construit directement ce même tableau avant de l'encoder) et projeter vers une
future API/Network.

#### Compatibilité ascendante — ancien format `breed` texte libre (correction 0.6.0)

Un pedigree déjà enregistré avant cette correction (`breed` texte libre plutôt que
`race`/`race_autre`) n'est jamais perdu. À la LECTURE (jamais une réécriture automatique de la
base, jamais une migration destructive), `gwseq_migrate_external_ancestor_node()` reconnaît une
ancienne valeur qui correspond exactement (comparaison insensible à la casse et aux accents) à un
code technique ou à un libellé du référentiel (« Selle Français », « kwpn »...) et la convertit
proprement. Une valeur qui ne correspond à rien de connu (ex. une ancienne abréviation « SF ») est
conservée intégralement via `race = 'autre'` + `race_autre` = texte original — jamais perdue, ni
devinée arbitrairement. Le format en base n'est réécrit qu'au prochain enregistrement volontaire
de cette relation par un utilisateur.

**Définition exacte des « 4 générations »** (identique pour une branche GWS ou externe) :

```
Cheval courant = génération 0 (toujours entièrement résolu)
Parents = génération 1                          (2 nœuds max)
Grands-parents = génération 2                   (4 nœuds max)
Arrière-grands-parents = génération 3           (8 nœuds max)
Arrière-arrière-grands-parents = génération 4   (16 nœuds max)
```

Soit 30 nœuds d'ascendants au maximum. **Correction 0.7.0** : un nœud de la génération 4 (la
dernière autorisée) est strictement terminal — ses clés `father`/`mother` sont totalement
ABSENTES du tableau, pas seulement `null`. Avant cette correction, un nœud sentinelle
`{type: "depth_limit"}` occupait ces clés ; la recette avait révélé que cela laissait croire, dans
la boîte de vérification, qu'une génération 5 existerait dans le modèle (affichage « Père : Non
renseigné »/« Mère : Non renseigné ») — alors qu'elle est hors périmètre du pedigree V1, jamais
saisissable ni stockée. Le type de nœud `depth_limit` n'existe donc plus du tout depuis 0.7.0.

**Structure produite par le resolver** (`gwseq_resolve_horse_pedigree($cheval_id)`), à titre
d'exemple (ici avec seulement 2 générations pour rester lisible ; en génération 4, `father` et
`mother` seraient simplement absents de `Voltaire` et de `Belle`) :

```
{
  type: "gws_horse", id: 123, global_id: "…", name: "Jamerose", breed: "Selle Français",
  father: {
    type: "external", name: "Kannan", breed: "KWPN",
    father: { type: "external", name: "Voltaire", breed: "Hanovrien" },
    mother: null
  },
  mother: { type: "gws_horse", id: 45, global_id: "…", name: "Belle", breed: "AQPS" }
}
```

Autres types de nœud possibles : `unavailable` (parent GWS supprimé définitivement),
`cycle_detected` (cycle indirect détecté). Une relation jamais renseignée reste `null` ; une
génération au-delà de la profondeur autorisée n'est, elle, ni `null` ni un type dédié — c'est
simplement l'absence de la clé `father`/`mother` elle-même sur le nœud terminal. Aucune donnée
privée (statut commercial, prix, éleveur, propriétaire, UELN/SIRE, catégories) n'apparaît jamais
dans cette structure — seuls id/global_id (fiche GWS uniquement)/nom/race/filiation sont exposés.

#### Compatibilité import/programmatique (Étape 5)

`gwseq_set_horse_parent($cheval_id, $role, $args)` (`$role` = `'father'`/`'mother'`) est LA
fonction métier qui persiste une relation — GWS ou externe, arbre complet compris — sans jamais
lire `$_POST` ni vérifier de nonce/capability, exactement comme `update_post_meta()` ou
`wp_insert_post()` eux-mêmes ne le font pas. Un futur importeur CSV/XLSX pourra donc définir un
père ou une mère, y compris une branche externe imbriquée sur plusieurs générations, sans
fabriquer de faux formulaire admin. Le formulaire d'édition (`gwseq_save_cheval_pedigree_meta()`)
n'est qu'UN client de cette fonction parmi d'autres possibles : il se contente d'ajouter les
garde-fous propres à une soumission de formulaire (nonce/capability/autosave/révision) puis lui
délègue entièrement la persistance. Conformément à la décision prise après l'Étape 4, **les
Étapes 3 et 4 n'ont volontairement pas été refactorées** pour appliquer rétroactivement cette
règle — seul ce nouveau code la respecte (voir le mini-audit de la version 0.4.1 ci-dessus pour
l'état des lieux sur Prestation/Cheval-identité/Commercialisation).

#### Limitations connues (Étape 5)

- **Aucune déduplication d'ascendants externes** : un même nom saisi dans plusieurs pedigrees
  (ex. « Kannan ») n'est jamais reconnu comme « le même cheval » par le système. Assumé pour la
  V1 (§7 de la demande) — un futur Network pourrait un jour proposer un rapprochement, jamais
  automatique sans confirmation explicite de l'utilisateur.
- **Aucun Global Horse ID pour un ascendant externe** : il n'identifie qu'une fiche GWS, jamais un
  cheval réel en général (voir déjà l'Étape 4, §17) — un ascendant externe, n'étant pas une fiche,
  n'en reçoit donc jamais un.
- **Cycle indirect non détectable à la sauvegarde** : seule l'auto-référence directe est rejetée
  au moment d'enregistrer une relation GWS ; un cycle formé par deux fiches enregistrées
  séparément (A → père B, puis B → père A) n'est détecté qu'à la résolution, jamais empêché à la
  sauvegarde (cela nécessiterait de parcourir tout le graphe existant à chaque enregistrement,
  hors de proportion pour ce socle).
- **Reconnaissance d'ancienne race limitée aux correspondances exactes** (0.6.0) : une ancienne
  valeur texte est reconnue si elle correspond, après normalisation, à un code ou un libellé du
  référentiel — une abréviation non standard (ex. « SF ») n'est pas devinée automatiquement et
  reste sous « Autre », ce qui est le comportement voulu (jamais d'invention arbitraire) mais peut
  nécessiter une correction manuelle ponctuelle par l'utilisateur s'il souhaite la valeur canonique.
- **Aucune migration automatique des données déjà corrompues par le bug Unicode antérieur à
  0.7.0** : une valeur déjà altérée en base avant cette correction (ex. « Native de Fu00e9lines »
  au lieu de « Native de Félines ») n'est jamais réécrite automatiquement — un correctif global
  par expression régulière sur toute la base a été jugé insuffisamment sûr (risque de faux
  positifs sur une valeur légitimement proche). Une telle valeur reste donc visiblement incorrecte
  tant qu'un utilisateur ne la corrige pas manuellement en resaisissant le nom concerné (une fois
  corrigée et resauvegardée avec le correctif 0.7.0 en place, elle ne peut plus se corrompre à
  nouveau). La fiche de recette Jamerose sera corrigée manuellement après déploiement.
- **Une fiche déjà enregistrée avant le correctif 0.8.0** pourrait avoir conservé, orpheline, une
  ancienne structure externe déjà vidée par l'utilisateur avant cette version (le symptôme exact du
  bug corrigé ici) — elle réapparaîtrait alors une seule fois à l'ouverture de la fiche ; il suffit
  de la revider explicitement (ou d'utiliser le bouton « Supprimer cet ascendant ») et
  d'enregistrer une fois pour qu'elle soit définitivement nettoyée. Aucune migration automatique
  n'a été effectuée sur les données existantes, pour les mêmes raisons de prudence que pour le
  correctif Unicode ci-dessus.
- **Aucune migration automatique d'une incohérence père/mère déjà enregistrée avant le correctif
  0.9.0** : une fiche où le même cheval GWS était déjà stocké comme père ET comme mère avant cette
  version resterait dans cet état tant qu'un utilisateur ne modifie pas explicitement l'un des deux
  côtés (la validation ne s'applique qu'aux NOUVELLES affectations soumises, jamais à une
  réécriture automatique d'une donnée déjà en base).
- **Aucun contrôle rétroactif du filtrage sexe/année (0.10.0)** : une relation déjà enregistrée
  reste valide même si une modification ultérieure de la fiche parent ou produit la rendait
  biologiquement incohérente (ex. un changement de sexe erroné, une correction d'année de
  naissance) — volontairement aucun système de contrôle rétroactif construit en V1 (piste actée
  pour une amélioration future : audit/avertissement d'intégrité). De même, une fiche où le sexe ou
  l'année étaient absents au moment de l'enregistrement d'une relation, puis renseignés après coup,
  ne déclenche aucune revalidation automatique de cette relation.

#### Pistes futures actées en roadmap (aucun développement à ce stade)

- **Connecteur IFCE/SIRE optionnel** : les webservices SIRE de l'IFCE permettraient
  potentiellement de préremplir une fiche (identité, généalogie...) à partir d'un numéro SIRE/
  UELN. Purement optionnel et non garanti (accès contractuel, soumis à droits, potentiellement
  payant) — GWS Equestrian reste entièrement fonctionnel sans lui, la saisie structurée manuelle
  est le fonctionnement nominal et autonome. Si développé un jour, ce serait une couche
  d'import/enrichissement AU-DESSUS du modèle GWS (SIRE/UELN → connecteur → mapping/validation →
  modèle métier GWS Equestrian → web/PDF/catalogue/etc.), jamais une dépendance directe. Aucun
  appel API, authentification, clé, écran de configuration, cache ou stockage particulier
  n'existe à ce stade. **Compatibilité déjà vérifiée** : le chemin programmatique existant
  (`gwseq_set_horse_parent()`) permettrait à un tel connecteur de fonctionner sans aucune
  modification de ce fichier — il n'aurait qu'à mapper ses propres données vers la forme
  `{mode, horse_id, external}` déjà attendue. Avant toute implémentation future, vérifier
  impérativement les conditions contractuelles IFCE, droits d'accès, tarification, services
  réellement disponibles, profondeur de généalogie accessible, droits de stockage/réutilisation/
  affichage public, conditions de cache, obligations d'attribution, limites d'appels — rien de
  tout cela n'est supposé dans le code actuel.
- **Bibliothèque facultative d'étalons/ascendants** : une aide à la saisie qui proposerait par
  exemple « KANNAN — KWPN » en préremplissage en tapant « Kannan ». Purement une aide, jamais
  nécessaire au fonctionnement du pedigree. Aucun rapprochement automatique par nom, aucune fiche
  externe ne deviendrait automatiquement une fiche GWS, aucun identifiant biologique universel
  artificiel ne serait créé. Aucun développement à ce stade.

#### Procédure de recette — Étape 5 (reprise après corrections)

À réaliser dans WordPress Local, sans écrire de code — reprend la recette sur le pedigree déjà
saisi de Jamerose, un test à la fois :

1. Ouvrir la fiche de Jamerose (ou toute fiche dont le pedigree a été saisi avant la correction) :
   vérifier qu'aucune donnée déjà saisie n'a été perdue (noms, races, structure des générations).
2. Vérifier que Race/Stud-book se présente désormais comme un menu déroulant (référentiel de la
   fiche Cheval) à chaque génération, plutôt qu'un champ texte libre.
3. Pour une ancienne race qui correspondait exactement à une valeur du référentiel (ex. « Selle
   Français »), vérifier qu'elle apparaît déjà sélectionnée. Pour une ancienne valeur non reconnue
   (ex. une abréviation), vérifier qu'elle apparaît sous « Autre » avec le texte original intact
   dans le champ de précision.
4. Vérifier la lisibilité du contexte : chaque bloc doit indiquer clairement « Père de… »/« Mère
   de… » avec le nom du cheval concerné, jamais un Père/Mère nu.
5. Développer les origines d'un ascendant externe déjà nommé (bouton « + Renseigner les origines
   de… ») : vérifier que le nom de CET ascendant apparaît bien dans le bouton et dans les
   intitulés du niveau suivant — pas celui du cheval racine.
6. Vérifier que chaque génération affiche son compteur (« Génération 1 sur 4 »... « Génération 4
   sur 4 — dernière génération »), et qu'aucun bouton de divulgation supplémentaire n'apparaît à
   la génération 4.
7. Modifier une branche existante (ajouter une génération supplémentaire, corriger un nom ou une
   race) : enregistrer, recharger, vérifier la persistance exacte.
8. Changer le mode d'une relation entre GWS et externe dans les deux sens : vérifier qu'aucune
   donnée n'est perdue (l'ancienne branche reste récupérable) et qu'aucun mélange n'apparaît.
9. Vérifier le fonctionnement du resolver : sur un environnement `local`/`development`, la boîte
   « Pedigree résolu » reflète fidèlement la structure saisie, y compris sur plusieurs générations
   et un pedigree mêlant GWS et externe.
10. Vérifier la meta box « Production » sur un cheval GWS référencé comme parent par au moins un
    autre cheval.
11. Vérifier la sécurité/absence de régression : nonce, permissions, autosave, révision (déjà
    couverts par les tests automatisés, à confirmer en conditions réelles), et l'absence d'erreur
    dans la console navigateur.
12. Désactiver puis réactiver le module (`config/modules.php`) : vérifier qu'aucune relation de
    pedigree, aucun cheval, aucune donnée n'a été modifiée ou recréée.

**Étapes ajoutées pour le correctif bloquant 0.7.0** (corruption Unicode + contexte dynamique) :

13. Saisir un nom d'ascendant contenant des accents (ex. « Native de Félines », « Crème Brûlée »,
    « Uriél de Félines ») : enregistrer, recharger la fiche, et vérifier au bon endroit dans le
    champ Nom que la valeur affichée est EXACTEMENT celle saisie (accents compris, aucune séquence
    du type « u00e9 » ne doit jamais apparaître). Enregistrer une deuxième puis une troisième fois
    sans rien modifier : vérifier à chaque fois que la valeur reste identique (pas de dégradation
    progressive).
14. Taper le nom d'un nouvel ascendant externe pas encore enregistré (ex. « Uriel ») : vérifier que
    le bouton « + Renseigner les origines de… », ainsi que les intitulés « Père de… »/« Mère de… »
    du niveau suivant une fois développé, se mettent à jour EN DIRECT (sans recharger ni enregistrer
    la fiche), en majuscules sans accent (« URIEL »). Vérifier que tant que le champ Nom est vide,
    le texte de repli « cet ascendant » reste affiché.
15. Avec le même essai, taper un nom accentué (ex. « Uriél de Félines ») et vérifier que le champ
    Nom lui-même ne change jamais de contenu (ni casse, ni accents retirés) alors même que les
    intitulés contextuels affichent la version normalisée (« URIEL DE FELINES ») — le JavaScript ne
    doit jamais réécrire ce que l'utilisateur a tapé.
16. Sur un pedigree comportant une branche allant jusqu'à la génération 4, vérifier dans la boîte
    « Pedigree résolu » que le nœud de génération 4 ne présente plus aucune ligne « Père : Non
    renseigné »/« Mère : Non renseigné » — la génération 4 doit apparaître comme terminale, sans
    laisser croire qu'une génération 5 existerait.

**Étapes ajoutées pour le correctif complémentaire 0.8.0** (suppression d'un ascendant externe
vide) :

17. Créer un ascendant externe (nom saisi, éventuellement une race), enregistrer, puis effacer
    entièrement le champ Nom (en laissant le mode sur « Nouvel ascendant ») et enregistrer à
    nouveau : recharger la fiche et vérifier que l'ascendant NE réapparaît PAS — ni dans le champ
    Nom, ni dans la boîte « Pedigree résolu ».
18. Reproduire le même essai avec un ascendant possédant lui-même un père/une mère renseigné(e) :
    vérifier qu'en vidant l'ascendant du premier niveau, toute sa sous-branche disparaît également
    après enregistrement, sans erreur.
19. Utiliser le bouton « Supprimer cet ascendant » sur un nœud sans origines enfants : vérifier que
    les champs se vident immédiatement, sans confirmation demandée.
20. Utiliser le même bouton sur un nœud dont les origines (père/mère) sont déjà renseignées :
    vérifier qu'une confirmation (« Supprimer cet ascendant et ses origines ? ») s'affiche avant
    tout vidage, et que refuser cette confirmation ne modifie aucun champ.
21. Vérifier que supprimer un ascendant du Père n'affecte jamais les champs déjà saisis côté Mère
    (et réciproquement), et que le reste du pedigree (autres générations non concernées) reste
    intact après enregistrement.
22. Vérifier qu'une relation vers un cheval GWS, une fois désactivée via « Non renseigné », ne
    supprime jamais la fiche Cheval du cheval qui était référencé (elle reste normalement
    consultable et modifiable).

**Étapes ajoutées pour le correctif intégrité 0.9.0** (même cheval GWS impossible comme père et
mère) :

23. Sélectionner un cheval GWS comme Père, puis ouvrir le sélecteur Mère : vérifier que ce même
    cheval y apparaît désactivé (grisé, non sélectionnable), sans avoir été retiré de la liste.
24. Changer la sélection du Père pour un autre cheval : vérifier que le sélecteur Mère se met à
    jour en direct (l'ancien cheval redevient sélectionnable, le nouveau devient désactivé), sans
    recharger la page.
25. Vérifier que si la Mère a déjà une valeur sélectionnée qui n'est pas concernée par le
    changement, elle n'est jamais modifiée automatiquement par ce réajustement.
26. Forcer malgré tout l'enregistrement du même cheval comme père et mère (JavaScript désactivé, ou
    en modifiant la requête) : vérifier que l'enregistrement de la seconde relation identique est
    refusé et que la relation existante n'est ni supprimée ni corrompue — recharger la fiche pour
    confirmer l'état conservé.
27. Vérifier que l'auto-parenté reste impossible comme avant (le cheval édité n'apparaît toujours
    pas dans ses propres sélecteurs Père/Mère).
28. Vérifier qu'un pedigree mêlant un père GWS et une mère externe (ou l'inverse) portant le même
    nom qu'un cheval GWS existant s'enregistre normalement, sans blocage ni confusion.
29. Relire les libellés des radios Père/Mère : « Cheval déjà enregistré » et « Nouvel ascendant » ;
    et le texte de la boîte d'aperçu développeur : « Aperçu du pedigree enregistré — actualisé
    après sauvegarde. ».

**Étapes ajoutées pour le correctif filtrage métier 0.10.0** (sexe et année de naissance des
parents GWS) :

30. Sur le sélecteur Père d'un cheval, vérifier qu'une jument enregistrée apparaît désactivée avec
    l'indication « sexe incompatible » ; vérifier symétriquement qu'un entier ou un hongre apparaît
    désactivé avec la même indication dans le sélecteur Mère.
31. Vérifier qu'un entier ou un hongre reste normalement sélectionnable comme Père, et qu'une
    femelle reste normalement sélectionnable comme Mère.
32. Vérifier qu'un cheval dont le sexe n'est pas renseigné reste sélectionnable dans les deux
    sélecteurs (jamais désactivé pour ce motif).
33. Sur un cheval dont l'année de naissance est renseignée, vérifier qu'un candidat né la même
    année ou après apparaît désactivé avec l'indication « année incompatible », et qu'un candidat
    né strictement avant reste sélectionnable.
34. Vérifier qu'un candidat dont l'année de naissance n'est pas renseignée reste toujours
    sélectionnable, quelle que soit l'année du cheval édité.
35. Sur un cheval DONT l'année de naissance n'est PAS renseignée, vérifier qu'aucun candidat n'est
    désactivé pour un motif d'année (seul le sexe et le conflit père/mère restent appliqués).
36. Vérifier que les options désactivées pour sexe/année ne redeviennent JAMAIS sélectionnables en
    changeant l'autre sélecteur (Père ou Mère) — contrairement au conflit père/mère (0.9.0) qui,
    lui, se met à jour en direct.
37. Tenter de forcer malgré tout l'enregistrement d'une relation incompatible (sexe ou année, via
    une requête modifiée ou JavaScript désactivé) : vérifier que l'enregistrement est refusé et
    qu'aucune relation existante n'est supprimée ni corrompue.
38. Enregistrer un entier comme père, puis modifier sa fiche pour le passer en « Hongre » (post
    castration) : vérifier que la relation de pedigree existante n'est ni supprimée ni modifiée par
    ce changement.
39. Vérifier qu'un pedigree avec un ascendant externe (« Nouvel ascendant ») portant potentiellement
    le même sexe implicite qu'une contrainte de rôle n'est jamais concerné par ce filtrage (aucun
    champ sexe sur un ascendant externe).

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
glisser-déposer (la galerie de l'Étape 6 se réordonne par boutons ↑/↓, jamais par glisser-déposer),
résultats sportifs structurés exhaustifs et historique annuel des indices (l'Étape 6 ne conserve
qu'une seule valeur par indice, jamais un historique), fratrie (relation non stockée — seule la
production, produits d'un même parent GWS, est calculable dès l'Étape 5), duplication, fiche
privée/token de partage, export PDF/QR/catalogue, Social Kit, Network, API publique, import/
onboarding en masse (besoin identifié en recette de l'Étape 4, la règle de création
programmatique est appliquée par avance au nouveau code du pedigree et de l'Étape 6, voir plus
haut), module Équipe (besoin identifié en recette de l'Étape 3, retenu pour la feuille de route
sans placement précis encore décidé), rendu front définitif (y compris pour le pedigree et les
nouvelles données de l'Étape 6 — la boîte de vérification admin/développement de l'Étape 5 n'est
pas ce futur rendu), dossier vétérinaire structuré (l'Étape 6 se limite à un champ texte libre
Ostéo-articulaire). Ces éléments arrivent aux étapes 7 à 9+ du plan de développement validé,
chacune soumise à validation avant la suivante.

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

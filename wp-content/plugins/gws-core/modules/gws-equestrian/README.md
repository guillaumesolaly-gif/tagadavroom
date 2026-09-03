# GWS Equestrian (gws-core)

Module métier pour les professionnels du monde équestre (pension, enseignement, élevage,
reproduction, vente) : gestion des prestations/tarifs, des fiches chevaux, de l'équipe, et des
actualités (adaptation du système natif WordPress). Voir le pendant présentation dans
`wp-content/themes/gws-starter/modules/gws-equestrian/`.

**Préfixe du module : `gwseq_`** (jamais `gws_` ni `gws_core_`, réservés au cœur — voir
`modules/README.md` et `AI-AGENT.md` §3). Consigné dans le registre de `modules/README.md`.

## État actuel : Mises en avant — Pop-in et Sticky bar, GWS Equestrian 0.20.0 — développés, testés (couverture PHP + exécution réelle Node) et livrés, en attente de recette runtime manuelle complète avant toute nouvelle évolution. Actualités — cadrage de l'éditeur par blocs (0.19.0), filtre Prestations par Groupe tarifaire (0.18.0), Module Équipe (0.17.x) et back-office Cheval V1 validés en recette runtime (voir `CHANGELOG.md` de ce dossier). Duplication d'un cheval retirée de la roadmap V1. Prochaine étape : recette runtime complète Pop-in/Sticky bar — aucune autre évolution engagée avant celle-ci.

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
du CD des indices génétiques à deux décimales, navigation par onglets — voir plus bas), suivi de
plusieurs allers-retours de recette (0.12.1 à 0.12.6, voir plus bas) désormais **validés**.

**Étape 7 (0.13.2, EN ATTENTE DE RECETTE)** : premier import intelligent d'une fiche Cheval depuis
une fiche de synthèse IFCE / Info Chevaux au format PDF, pour supprimer la ressaisie manuelle — en
particulier le pedigree. La première recette runtime (0.13.0) a révélé un bug bloquant — le vrai PDF
de Jamerose de Félines était rejeté — diagnostiqué et corrigé en 0.13.1 (voir « Import IFCE (Étape 7)
» plus bas et `CHANGELOG.md` pour le détail complet du diagnostic et du correctif) : l'extraction
PDF a été réécrite pour résoudre les objets compressés (`/Type/ObjStm`) et décoder la police
composite Identity-H via sa table ToUnicode, désormais validée contre le VRAI PDF de Jamerose. Cette
même recette a également porté sur l'écran « Ajouter un cheval » (désormais un choix explicite entre
import IFCE et création manuelle, corrigé en 0.13.1) et sur le verrouillage de la Photo principale
dans Médias (contrôles natifs de réordonnancement/repli, devenus obsolètes une fois la boîte fixée
dans l'onglet, désormais masqués — voir la section Étape 6 plus bas). Conformément à la demande,
cette étape n'a volontairement PAS été suivie d'une étape suivante avant nouvelle recette runtime.

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
actif. **Exception assumée pour la Photo principale** (`postimagediv`, correctif 0.12.5) : un
simple masquage/affichage en place, comme pour Production/aperçu pedigree, s'est révélé
insuffisant en recette — la boîte native restait visible dans la colonne latérale, l'onglet Médias
ne présentant qu'un texte y renvoyant. Elle est donc RÉELLEMENT déplacée dans le DOM jusqu'à un
emplacement dédié à l'intérieur de la boîte Médias (voir « Intégration réelle de la Photo
principale 0.12.5 » plus bas) — la SEULE boîte de tout ce système à subir un déplacement DOM réel.
Restent volontairement HORS du système d'onglets, toujours visibles dans la colonne latérale : la
boîte de développement Global Horse ID et « Ordre d'affichage ».

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

#### Correctif RÉGRESSION BLOQUANTE 0.12.3 — diagnostic complet, filets de sécurité

Le correctif 0.12.2 (lever le repli natif `.closed`) était INCOMPLET : la reprise de la recette a
montré que la boîte Identité restait ENTIÈREMENT invisible, en-tête compris — un symptôme que
`.closed` seul ne peut jamais produire, puisqu'il ne masque que le contenu (`.inside`) d'une boîte,
jamais son en-tête. Le diagnostic a été repris intégralement.

**CAUSE RACINE COMPLÈTE** : WordPress peut masquer une meta box ENTIÈRE via la classe `.hide-if-js`,
posée quand un utilisateur l'a masquée via le panneau "Screen Options" — une préférence mémorisée
par utilisateur (`metaboxhidden_{$screen}`), un scénario plausible et normal sur une base de recette
réutilisée depuis plusieurs versions (par exemple si un utilisateur avait, lors d'une version
précédente encore bloquante, tenté de diagnostiquer lui-même le problème via Screen Options). La
règle CSS correspondante peut être `!important` : un simple `style.display = ''` ne suffit jamais à
l'emporter sur une règle `!important` — la boîte restait donc invisible malgré le correctif 0.12.2.

**Correctif direct** (`assets/cheval-tabs-admin.js`) : l'activation d'un onglet lève désormais
`.closed` ET `.hide-if-js` pour chacune de ses boîtes, puis VÉRIFIE RÉELLEMENT la visibilité
obtenue via `offsetParent` (`null` si l'élément ou un ancêtre reste masqué par une règle CSS
quelconque) — si elle reste masquée, l'affichage est forcé avec la même priorité `!important`
(`style.setProperty('display', 'block', 'important')`), seule façon garantie de l'emporter.

**Filet de sécurité n°1 — cohérence de mapping** (§5 : éviter deux vérités indépendantes) : chaque
meta box gérée par un onglet est désormais marquée, dans le HTML RÉELLEMENT rendu, d'une classe
`gwseq-tab-{id}` posée via le filtre natif WordPress `postbox_classes_{page}_{id}` (nouvelle
fonction `gwseq_register_cheval_admin_tab_postbox_classes()`) — dérivée de la même configuration
que celle transmise au script. Avant de construire quoi que ce soit, le script vérifie que chaque
boîte trouvée par identifiant porte bien cette classe ; en cas d'écart, AUCUN onglet n'est construit
et l'écran reste dans son état natif empilé.

**Filet de sécurité n°2 — dégradation sûre** (§4 : « échec du système d'onglets ≠ perte d'accès aux
données ») : si, malgré tout, une boîte de l'onglet actif reste réellement invisible, le système
d'onglets se désactive intégralement — barre retirée du DOM, toutes les boîtes gérées restaurées à
une visibilité normale (jamais une meta box existante supprimée). En environnement
local/développement, un message natif (`.notice.notice-error`) signale le problème.

**Tests reconstruits pour dériver du markup réel WordPress** : `tests/gws-equestrian-cheval-admin-tabs-runtime-test.js`
reproduit désormais la structure réelle d'une meta box (`postbox-header`/`handlediv`/`inside`, avec
de vrais champs à l'intérieur) et modélise l'effet réel de `.closed`/`.hide-if-js` sur
`offsetParent`. Trois scénarios distincts (cas nominal avec boîte repliée+masquée ; boîte
durablement invisible déclenchant le filet n°2 ; incohérence de marquage déclenchant le filet n°1),
chacun vérifié indépendamment détecté par régression. 35 assertions Node au total (+ 8 nouvelles
assertions déclaratives PHP, dont la vérification du filtre `postbox_classes`).

#### Nettoyage 0.12.4 — état WordPress hérité sur la meta box Identité

Complément demandé après 0.12.3 : au-delà des deux filets de sécurité runtime (JavaScript), un
nettoyage PHP ciblé purge désormais l'état WordPress persisté PAR UTILISATEUR qui a pu s'accumuler
pendant les multiples allers-retours de recette sur cet écran — **sans jamais toucher au registre
`add_meta_box()`** de la boîte Identité, qui reste (et est resté depuis l'Étape 4) enregistrée en
contexte `'normal'`, jamais `'side'` : ce n'était pas ce registre qui posait problème, mais deux
préférences que WordPress mémorise séparément, par utilisateur, indépendamment du code du plugin.

**`gwseq_cleanup_legacy_identite_metabox_user_state()`** (`includes/cheval-fields.php`, hookée sur
`current_screen`, exécutée uniquement sur l'écran d'édition d'une fiche Cheval) purge, si
nécessaire :
1. **`metaboxhidden_{$screen}`** (case décochée dans le panneau "Options de l'écran" — la cause
   racine confirmée en 0.12.3, qui masque la boîte ENTIÈRE via `.hide-if-js`) : Identité est
   retirée de la liste des boîtes masquées, sans toucher aux autres boîtes que l'utilisateur aurait
   légitimement masquées par ailleurs.
2. **`meta-box-order_{$screen}`** (ordre/colonne mémorisés par glisser-déposer) : si Identité
   apparaît sous un contexte AUTRE que `'normal'` (ex. `'side'`, à la suite d'un glisser-déposer
   accidentel pendant une recette antérieure), cette entrée est retirée de l'ordre mémorisé —
   WordPress retombe alors sur son enregistrement réel (`'normal'`/`'high'`) plutôt que de
   perpétuer une position héritée incohérente. L'ordre du contexte `'normal'` lui-même, et les
   autres identifiants des autres contextes, ne sont jamais modifiés.

Idempotent (n'écrit la préférence que si un changement réel est nécessaire) et strictement scopé à
cette seule boîte — aucune autre préférence de l'utilisateur n'est jamais touchée. Purement des
préférences d'AFFICHAGE propres à l'utilisateur connecté, jamais une donnée métier ni une meta de
la fiche Cheval elle-même.

**Complémentaire, pas un remplacement** : les deux filets de sécurité runtime de 0.12.3 (levée de
`.closed`/`.hide-if-js` à l'activation d'un onglet, vérification `offsetParent`, dégradation sûre
si une boîte reste invisible) restent en place — ce nettoyage traite la cause probable à la racine
(l'état persisté), les filets restent la garantie de dernier recours.

**Tests** : 6 nouvelles assertions dans `gws-equestrian-cheval-logic-test.php` (écran hors sujet
jamais touché, absence d'erreur sans utilisateur connecté, réactivation ciblée dans Screen Options,
idempotence, retrait d'une entrée héritée hors `'normal'`, ordre déjà correct jamais modifié).

#### Intégration réelle de la Photo principale 0.12.5

La recette a montré que le simple masquage/affichage EN PLACE de `postimagediv` (le mécanisme déjà
utilisé pour Production/aperçu pedigree sous Pedigree) était insuffisant pour la Photo principale :
la vraie boîte native restait visible dans la colonne latérale, et l'onglet Médias ne présentait
qu'un texte y renvoyant — pas ce qui était demandé (« Photo principale, puis Galerie et Vidéos, au
même endroit »).

**Correctif** (`assets/cheval-tabs-admin.js`) : `postimagediv` est désormais RÉELLEMENT déplacée
dans un emplacement dédié à l'intérieur de la boîte Médias
(`#gwseq-cheval-media-photo-principale-slot`, réservé par `cheval-media.php`) — une SEULE
exception, explicitement assumée et documentée en tête de fichier, à la règle générale de ce
script (jamais déplacer une boîte, seulement la masquer/afficher en place). Le déplacement utilise
`appendChild()` sur le nœud EXISTANT (jamais un clone, jamais une recréation) — exactement le
mécanisme du glisser-déposer natif de WordPress entre colonnes — donc aucun gestionnaire
d'événement déjà attaché par WordPress (`wp.media()`) n'est perdu. **Aucune donnée dupliquée** :
même nœud DOM, même `attachment_id`, la Featured Image de WordPress reste l'unique source de
vérité. Une fois déplacée, elle n'apparaît plus jamais dans la colonne latérale (le déplacement,
pas une simple duplication de visibilité, le garantit structurellement), et hérite automatiquement
de la visibilité de la boîte Médias en en devenant DESCENDANTE — aucune logique de visibilité
séparée n'est nécessaire pour elle ; `gwseq_cheval_admin_tabs_config()` ne référence donc plus
`postimagediv` du tout. Restaurée à sa position native si le système d'onglets se désactive
intégralement (filet de sécurité n°2).

**Texte devenu inutile retiré** (`cheval-media.php`) : « Utilise l'image à la une de cette fiche
(voir l'encadré « Photo principale » dans la colonne de droite)... » a été supprimé, remplacé par
l'emplacement d'accueil vide. **Sans JavaScript**, cet emplacement reste simplement vide et la
Photo principale demeure modifiable normalement via l'encadré natif de la colonne latérale, à sa
place habituelle — aucune régression du parcours sans JS. **Léger ajustement visuel**
(`cheval-tabs.css`) : la boîte native, une fois nichée dans la boîte Médias, perd son propre
encadrement (bordure/ombre) pour éviter une boîte visuellement imbriquée dans une boîte.

**Tests** : 5 nouvelles assertions dans le test d'exécution réelle (déplacement effectif du même
nœud DOM, absence de toute trace dans la colonne latérale, héritage automatique de la visibilité au
fil des changements d'onglet, restauration à la position native au filet de sécurité n°2) et 5
nouvelles assertions déclaratives PHP.

#### Diagnostic et correctif 0.12.6 — contenu de la Photo principale invisible après déplacement

Le déplacement réel de 0.12.5 restait non fonctionnel côté utilisateur : dans l'onglet Médias,
seul le titre « Photo principale » apparaissait, sans aucun contrôle ni aucune image en dessous —
alors que la Galerie, elle, fonctionnait normalement.

**Démarche de diagnostic (avant tout nouveau correctif)** : un déplacement de nœud DOM
(`appendChild()`) ne peut PAS, par garantie de la spécification DOM, effacer son propre contenu —
cette hypothèse a donc été écartée en premier, puis VÉRIFIÉE plutôt que supposée : un test
d'exécution réelle a été écrit avec le markup EXACT que WordPress produit pour `#postimagediv`
(`post_thumbnail_meta_box()`/`_wp_post_thumbnail_html()` : nonce, lien « Définir la photo
principale » à vide, ou vignette + lien « Supprimer » avec une photo déjà définie), dans les DEUX
états demandés, confirmant que le contenu de `.inside` survit intact au déplacement effectué par
notre script — **écartant avec certitude le code de déplacement lui-même comme cause**.

**Cause probable identifiée** : WordPress ne prévoit JAMAIS qu'un `.postbox` soit imbriqué à
l'intérieur d'un autre `.postbox` — cette forme de DOM n'existe nulle part ailleurs dans
l'administration native. Le déplacement de 0.12.5 crée précisément cette situation inédite, en
insérant `#postimagediv` (qui reste un `.postbox` complet) à l'intérieur de `.inside` de la boîte
Médias (elle-même un `.postbox`). L'administration WordPress est susceptible de cibler ce cas avec
une règle CSS défensive masquant les `.postbox` imbriqués, expliquant que le contenu — bien que
réellement déplacé et intact dans le DOM — restait invisible à l'écran.

**Correctif CSS ciblé** (`assets/cheval-tabs.css`), aucun changement JavaScript : une règle scopée
à l'emplacement dédié (`#postimagediv` et tous ses descendants, uniquement à l'intérieur de
`.gwseq-cheval-media__photo-principale-slot`) applique `display: revert !important` — cette valeur
réinitialise CHAQUE élément à SA PROPRE valeur `display` par défaut du navigateur (bloc pour un
`<div>`/`<p>`, en ligne pour un `<a>`/`<img>`...), sans qu'il soit nécessaire de connaître
l'identité exacte d'une éventuelle règle contraire, et sans aucun effet si une telle règle
n'existait pas réellement sur une installation donnée — aucune régression possible ailleurs.

**Tests** : nouveau scénario dans le test d'exécution réelle reproduisant le markup RÉEL de
`#postimagediv` dans ses deux états (avec/sans photo principale déjà définie), vérifiant champ par
champ (nonce, lien « Définir », vignette, lien « Supprimer ») que le contenu de `.inside` survit
intact au déplacement ET reste réellement visible (`offsetParent`) une fois l'onglet Médias actif —
13 nouvelles assertions Node, 2 nouvelles assertions PHP déclaratives.

#### Verrouillage de la Photo principale 0.13.1 — contrôles natifs faisant disparaître la boîte

La recette a montré que la boîte, bien qu'enfin visible avec son contenu (0.12.6), conservait ses
contrôles natifs de réordonnancement (Monter/Descendre, accessibilité clavier de WordPress pour le
glisser-déposer des metaboxes) et de repli/dépli. Utiliser le contrôle « Descendre » faisait
**disparaître la Photo principale de l'onglet Médias** : WordPress réordonne en présumant des
frères et sœurs qui sont eux-mêmes des metaboxes de premier niveau, une hypothèse qui ne tient plus
une fois la boîte imbriquée dans `.inside` de la boîte Médias — et pouvait persister un ordre/une
visibilité incohérents dans les préférences WordPress de l'utilisateur (`meta-box-order_{$screen}`)
pour les prochains chargements.

- **`assets/cheval-tabs-admin.js`** : pose la classe `gwseq-cheval-media__locked` sur
  `#postimagediv` au moment de son déplacement dans Médias ; la retire si le filet de sécurité n°2
  restaure la boîte à sa position native.
- **`assets/cheval-tabs.css`** : cette classe masque les trois contrôles interactifs devenus
  obsolètes (Monter/Descendre/Replier) — un bouton non affiché ne peut plus être cliqué ni atteint
  au clavier. Le glisser-déposer natif (jQuery UI Sortable) était déjà structurellement impossible
  (il n'agit que sur les enfants directs de `#normal-sortables`/`#side-sortables`, que
  `#postimagediv` n'est plus une fois déplacé) — aucune règle supplémentaire n'était nécessaire.
- **`includes/cheval-media.php`** : `gwseq_cleanup_legacy_postimagediv_metabox_user_state()` (même
  mécanisme que le nettoyage Identité de l'Étape 6, hooké sur `current_screen`) répare l'état déjà
  corrompu par le contrôle natif utilisé pendant la recette — retire `postimagediv` de
  `metaboxhidden_{$screen}` et de TOUS les contextes de `meta-box-order_{$screen}` où il
  apparaîtrait, sans jamais toucher aux autres préférences de l'utilisateur, sans jamais demander de
  passer par Options de l'écran.
- **Aucun nouveau champ, aucune duplication** : la Featured Image native reste l'unique source de
  vérité — seuls la présentation et le comportement d'interaction de la vraie boîte changent. Sans
  JavaScript, elle reste utilisable normalement dans sa colonne native (aucun mécanisme de
  verrouillage ne s'applique, puisque le script qui le pose ne s'exécute jamais).

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
- **Si une boîte reste masquée par "Screen Options"** (préférence utilisateur `metaboxhidden_{$screen}`)
  et que le système d'onglets se désactive intégralement pour cette raison (filet de sécurité n°2,
  0.12.3), la boîte concernée réapparaîtra bien empilée avec les autres, mais restera masquée par
  cette préférence WordPress native tant que l'utilisateur ne la réactive pas explicitement via le
  panneau "Options de l'écran" — aucun mécanisme ne modifie automatiquement cette préférence, par
  cohérence avec le principe général de ne jamais toucher aux réglages natifs de WordPress.

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
    propriétaire, SIRE, UELN) — et non une zone vide, ni même une boîte réduite à son seul en-tête.
    Si la boîte a été repliée manuellement au préalable (clic sur son titre), vérifier qu'elle
    réapparaît bien dépliée en revenant sur cet onglet (correctif 0.12.2). Ouvrir le panneau
    « Options de l'écran » (en haut à droite de l'écran) et vérifier que la case « Identité » y est
    bien cochée ; si elle avait été décochée par le passé (ce qui masquerait la boîte ENTIÈRE, en
    plus du système d'onglets), la recocher puis recharger la page pour confirmer que l'onglet
    Identité redevient normalement exploitable (correctif 0.12.3).
21ter. Cliquer sur l'onglet Médias : vérifier que sous le titre « Photo principale » apparaissent
    RÉELLEMENT un contrôle et/ou une image — jamais une zone vide sous ce seul titre (régression
    0.12.5, corrigée en 0.12.6). Sur un cheval SANS photo principale : le lien natif « Définir la
    photo principale » doit être visible et cliquable, ouvrant la médiathèque native. Sur un cheval
    AVEC une photo principale déjà définie : sa vignette doit être visible, avec un contrôle natif
    pour la remplacer/retirer. Vérifier qu'elle a bien disparu de la colonne latérale (jamais
    affichée à deux endroits à la fois) quand l'onglet Médias est actif, et qu'elle y réapparaît en
    changeant d'onglet (Identité, Commercial...). Modifier la Photo principale depuis son nouvel
    emplacement (médiathèque native, remplacement, retrait), enregistrer, recharger : vérifier la
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

## Mises en avant : Pop-in et Sticky bar (0.20.0)

Deux nouveaux objets métier BO, avec — contrairement à Actualités — un vrai rendu FRONT (seule
façon de valider réellement déclenchement/fréquence/fermeture).

**Post types** (`includes/post-types.php`) : `gwseq_popin` et `gwseq_sticky_bar`, tous deux
`public => false` (même précédent que Groupe tarifaire — pas de "Voir"/Aperçu natif), sans
Gutenberg, regroupés sous UNE SEULE entrée de menu top-level "Mises en avant" (sous-menus
"Pop-ins"/"Sticky bars") — obtenu nativement sans `add_menu_page()` : le CPT Pop-in porte
`labels->name = 'Mises en avant'` (nom de son propre menu auto-généré par WordPress) et
`labels->all_items = 'Pop-ins'` (son premier sous-menu) ; le CPT Sticky bar pointe
`show_in_menu` vers `edit.php?post_type=gwseq_popin`, ce qui déclenche le mécanisme natif
`_add_post_type_submenus()` pour l'attacher comme second sous-menu du même menu top-level.

**Mutualisation** (`includes/campagnes-shared.php`) : délibérément bornée à ce qui est réellement
commun — style (site/personnalisé) et couleurs, CTA, texte enrichi minimal (`wp_kses` + éditeur
TinyMCE "teeny" scopé), dates/fuseau (`wp_timezone()`, stockage UTC), statut de diffusion (distinct
du statut natif WordPress), ciblage, fenêtre de dates, rendus de formulaire communs (Diffusion,
sélecteur de ciblage, panneau d'aperçu), garde de sécurité de l'aperçu AJAX. Aucune classe
abstraite, aucun moteur de campagne générique : Pop-in (`includes/popin-fields.php`) et Sticky bar
(`includes/sticky-bar-fields.php`) restent deux objets métier simples avec leur propre logique de
déclenchement/apparence/rendu.

**Pop-in** : Contenu (Titre, Texte enrichi minimal, Image facultative, CTA facultatif), Apparence
(Style du site/Personnaliser — couleurs + image de fond FACULTATIVE, distincte de l'image de
contenu ; Taille Compacte/Standard/Large ; toujours centrée), Déclenchement (Immédiatement/Après
X secondes/Après X % de scroll/À l'intention de sortie), Fréquence (À chaque visite/Une fois par
session/Une fois tous les X jours). TOUJOURS fermable (croix, Échap, piège à focus, restauration
du focus).

**Intention de sortie — desktop uniquement, sans fallback mobile.** Détection via
`matchMedia('(hover: hover) and (pointer: fine)')`, jamais de sniffing de user-agent : sur un
terminal sans survol, le déclencheur ne s'active simplement jamais (aide explicite en BO invitant
à utiliser délai/scroll pour cibler le mobile) — choix explicitement validé, annulant une
proposition initiale de repli automatique 60 s/50 % de scroll sur mobile.

**Sticky bar** : plus simple — pas de Déclenchement (affichage immédiat si éligible), pas
d'image. Contenu (Texte court en texte SIMPLE, CTA facultatif), Apparence (couleurs uniquement,
PAS d'image de fond ; Position Haut/Bas ; fermeture FACULTATIVE via une case à cocher —
contrairement à la Pop-in toujours fermable).

**Diffusion commune** : Statut (Active/Inactive), Période (fuseau du site, stockage UTC), Ciblage
à quatre modes (Tout le site/Page d'accueil uniquement via `is_front_page()`/Certains
contenus/Tout le site sauf certains contenus) couvrant Pages, Chevaux, Prestations ET Actualités —
chaque cible stockée en clé composite `post_type:post_id` (jamais un ID ambigu entre post types),
avec revalidation systématique contre une usurpation de post_type. Priorité par `menu_order`
croissant en cas de campagnes concurrentes du même type (au plus une Pop-in ET une Sticky bar par
page, qui peuvent en revanche cohabiter) — jamais un second champ "Priorité".

**Aperçu et front, source de rendu unique** : `gwseq_render_popin_markup()`/
`gwseq_render_sticky_bar_markup()`, fonctions PHP pures, appelées IDENTIQUEMENT par l'aperçu BO
(`admin-ajax.php`, `assets/campagnes-admin.js`, debounce 350 ms) et par le rendu front
(`includes/campagnes-front.php`, hook `wp_footer`, éligibilité évaluée avant tout enqueue —
aucun chargement systématique). Fréquence entièrement côté client
(`assets/campagnes-front.js`) : `sessionStorage`/`localStorage`, aucun identifiant ni tracking.

**Styles du thème** : mode "Style du site" → variables déjà exposées par le thème GWS
(`--color-bg`, `--color-text`, `--color-primary`, `--color-primary-contrast`) ; mode personnalisé
→ propriétés dédiées au composant (`--gws-popin-bg`, `--gws-sticky-bg`, etc.) avec repli vers les
jetons du thème puis une valeur de secours codée en dur en tout dernier recours.

Voir `tests/gws-equestrian-campagnes-shared-test.php`, `tests/gws-equestrian-popin-logic-test.php`,
`tests/gws-equestrian-sticky-bar-logic-test.php`, `tests/gws-equestrian-campagnes-front-test.php`
et `tests/gws-equestrian-campagnes-front-runtime-test.js` pour la couverture dédiée, et le
`CHANGELOG.md` de ce dossier (0.20.0) pour le détail complet, y compris un bug réel détecté et
corrigé pendant l'écriture des tests d'intégration front.

## Actualités : cadrage de l'éditeur par blocs (0.19.0)

Le bloc Actualités V1 (0.18.0) fonctionne et a été validé en runtime — non reconstruit. Gutenberg
reste techniquement l'éditeur, mais sa palette de blocs est restreinte via le filtre natif
`allowed_block_types_all` (prévu par WordPress pour ce cas d'usage), scopé à
`$context->post->post_type === 'post'` : toute Page ou autre contexte reçoit la valeur d'entrée
inchangée, jamais recalculée. Allowlist (`gwseq_actualites_allowed_blocks()`) : Paragraphe, Titre,
Liste (+ `core/list-item`, obligatoire), Image, Galerie, Bouton (+ `core/buttons`, obligatoire),
Vidéo, intégration vidéo sûre (`core/embed`) — toujours une liste à INCLURE, jamais à EXCLURE, donc
sûre par défaut face à un futur bloc core inconnu. Audit préalable : aucun filtre de ce type
n'existait avant ce lot ; le seul bloc personnalisé du thème (`gws/resource-link`) n'est utilisé
par aucune Actualité existante. Voir `tests/gws-equestrian-actualites-logic-test.php` pour la
couverture dédiée.

## Bloc Actualités + filtre Prestations par Groupe tarifaire (0.18.0)

**Actualités** (`includes/actualites.php`) : réutilisation intégrale du système NATIF des articles
WordPress (`post`) plutôt qu'un nouveau CPT — objectif volontairement simple, adapter le
vocabulaire et les usages plutôt que construire un système éditorial parallèle. Audit préalable :
`post` était encore dans son état par défaut WordPress (aucune personnalisation existante trouvée
dans `gws-core` ni `gws-starter`).

- **Vocabulaire "Actualités"** via le filtre natif `post_type_labels_post` (mécanisme WordPress
  prévu pour personnaliser le vocabulaire d'un post type déjà enregistré, y compris natif — jamais
  une réinscription de `post`) : Actualités / Toutes les actualités / Ajouter une actualité /
  Modifier l'actualité / Nouvelle actualité / Rechercher une actualité, plus les libellés natifs
  visibles dans le même parcours (notifications de publication, médiathèque, filtre de liste...).
- **Étiquettes masquées, jamais supprimées** (`post_tag`) via le filtre natif
  `register_taxonomy_args`, scopé à cette seule taxonomie (`show_ui => false`,
  `show_admin_column => false`) : aucune désinscription, aucune donnée détruite (`show_in_rest`
  inchangé). Portée assumée et signalée : `post_tag` étant unique et partagée par tout le site,
  le masquage s'applique à toute édition de `post` dès que GWS Equestrian est actif — le périmètre
  voulu, sans bascule plus fine possible sans développement plus lourd, volontairement non engagé.
- **Commentaires/trackbacks retirés** pour les NOUVELLES Actualités
  (`remove_post_type_support('post', 'comments'|'trackbacks')`) : effet natif documenté de
  WordPress lui-même — une fois le support retiré, `get_default_comment_status('post')` renvoie
  systématiquement `'closed'`, sans code supplémentaire. Aucune donnée de commentaire existante ni
  aucun article déjà publié n'est modifié.
- **Modification rapide retirée** en réutilisant `gwseq_remove_quick_edit_row_action()`
  (`includes/admin-ui.php`, déjà partagée par Chevaux/Membres/Prestations/Groupes tarifaires) —
  `post` y est ajouté, jamais un second filtre dupliqué.
- **Catégories et champs d'édition : aucun code.** La taxonomie `category` native reste
  strictement inchangée (aucune catégorie créée automatiquement, celles existantes préservées) ;
  titre, contenu, image à la une, date, statut de publication et auteur restent les mécanismes
  natifs, aucun champ métier supplémentaire en V1. Aucun rendu front développé dans ce lot — les
  Actualités restent exploitables plus tard via `WP_Query`/catégories/image à la
  une/contenu/date/permalien, mécanismes WordPress standards jamais compliqués par ce lot.

Voir `tests/gws-equestrian-actualites-logic-test.php` pour la couverture dédiée.

**Filtre de la liste Prestations par Groupe tarifaire** (`includes/prestation-fields.php`) :
liste déroulante au-dessus de `Prestations → Toutes les prestations` (valeur par défaut "Tous les
groupes tarifaires"), combinable avec la recherche native et la pagination. Réutilise
EXACTEMENT la relation déjà en place (`_gwseq_prestation_groupe_id`), via une nouvelle fonction
`gwseq_get_prestation_groupe_choices()` extraite pour n'avoir qu'UNE SEULE requête de liste des
groupes, partagée avec le sélecteur de la fiche Prestation — aucune deuxième logique de
classement. "Sans groupe tarifaire" couvre proprement les deux cas réels via une clause
`meta_query` en relation `OR` : une prestation dont la meta vaut explicitement `0` (relation
retirée volontairement) ET une prestation créée avant l'existence de cette relation, dont la meta
n'existe simplement pas (`NOT EXISTS`).

## Module Équipe (0.17.0)

Nouvel objet métier, indépendant de Cheval : gérer les personnes qu'une structure équestre
souhaite présenter (dirigeants, cavaliers, moniteurs, soigneurs, grooms, responsables d'élevage,
vétérinaires intégrés, personnel administratif...). Volontairement simple — ni annuaire RH, ni
système de comptes utilisateurs, ni CRM : un Membre est une fiche métier structurée, chaque
information restant individuellement accessible (jamais un blob HTML ni un champ opaque), pour une
réutilisation future (page Équipe du site, blocs individuels, catalogues, Social Kit, API/exports).

**Post type `gwseq_membre`** (`includes/post-types.php`) : menu d'administration "Équipe"
(`Équipe → Tous les membres` / `Équipe → Ajouter un membre`), fiche appelée "Membre". Enregistré
sans `capability_type` personnalisé — même logique que Prestation/Groupe/Cheval, un utilisateur
Éditeur peut consulter/ajouter/modifier/publier/gérer la photo/mettre à la corbeille un membre sans
qu'aucune capacité technique supplémentaire ne soit créée pour ce seul module.

**Tous les champs sont facultatifs** (`includes/membre-fields.php`). Aucun référentiel ni taxonomie
créés, à l'exception des Langues : Fonction/rôle, Localisation, Spécialités et Diplômes/
qualifications restent volontairement du texte libre — GWS doit fonctionner avec des structures et
des qualifications différentes selon les pays, jamais une nomenclature française imposée.

**Trois sections simples plutôt qu'un système d'onglets** : le système d'onglets de Cheval
(`includes/cheval-admin-tabs.php`) est structurellement couplé à ce seul post type (écran ciblé en
dur, script `gwseqChevalTabs` dédié, déplacement DOM spécifique à sa boîte Médias) — le généraliser
pour un module aussi réduit (trois sections, une dizaine de champs) aurait créé un couplage
étranger à sa conception. Trois meta boxes empilées (Identité, Profil, Contact) restent
immédiatement lisibles sans abstraction supplémentaire :

- **Identité** — Prénom, Nom, Fonction/rôle (texte libre), Photo (image à la une native relabellée
  "Photo", aucune galerie contrairement à Cheval, aucune meta parallèle), Localisation (texte
  libre, utile aux structures multi-sites).
- **Profil** — Présentation/parcours (texte long libre), Spécialités (texte libre), Diplômes/
  qualifications (texte libre), et **Langues**, seul champ réellement structuré : sélection
  multiple à valeurs canoniques stables (`fr`/`en`/`de`/`es`/`it`/`pt`/`nl`/`sv`/`zh`/`ja`/`ar`/
  `autre`, indépendantes des libellés affichés — les noms complets sont affichés dans l'admin,
  jamais seulement "FR"/"EN"/"DE"). "Autre" révèle un champ libre "Préciser" : le SERVEUR reste
  l'autorité (`gwseq_sanitize_membre_langues_input()`) — si "Autre" n'est plus sélectionné, la
  précision est systématiquement remise à vide, même si l'ancienne valeur est encore soumise dans
  le payload (même discipline que le nettoyage des Labels ANSF lors d'un changement de sexe).
- **Contact** (tous facultatifs) — Téléphone (texte libre, aucun format imposé, un numéro
  international n'est jamais dénaturé), E-mail (sanitation WordPress appropriée via
  `gws_core_field_sanitize('email', ...)`), WhatsApp (donnée INDÉPENDANTE du téléphone principal,
  jamais supposée identique — simple texte libre à ce stade, adapté à une future construction de
  lien wa.me, non développée dans ce lot), Instagram/Facebook/LinkedIn/TikTok/Site (URLs
  sanitisées via `esc_url_raw()`, aucune connexion aux API des réseaux sociaux).

**Titre technique automatique** (§ mécanisme retenu) : le client ne saisit jamais le nom deux
fois. `post_title` est entièrement dérivé de Prénom + Nom (`gwseq_derive_membre_title()`) via un
filtre `wp_insert_post_data` — jamais un second `wp_update_post()` dans un hook `save_post`, ce qui
aurait obligé à se dés-accrocher soi-même pour éviter une boucle de sauvegarde. Ce filtre modifie
uniquement le tableau `$data` avant son écriture en base : aucun appel récursif possible par
construction. Protégé par le même nonce que la sauvegarde des meta — une révision, un autosave, ou
tout appel de `wp_insert_post()` ne portant pas ce nonce précis (ex. Quick Edit, un futur import
programmatique) laisse le titre déjà enregistré intact, jamais réécrit silencieusement. Fonctionne
correctement en brouillon, avec le prénom seul, le nom seul ("Jean", jamais "Jean " avec un espace
superflu), ou les deux vides (titre vide, WordPress affiche alors nativement "(sans titre)"). Le
champ Titre natif est masqué sur l'écran d'édition (`includes/membre-editor.php`, même technique
CSS ciblée que `includes/cheval-categories.php` pour l'affordance de catégorie) — 'title' reste un
support déclaré du post type (stockage/tri/recherche natifs par titre inchangés), seul son bloc de
saisie visuel disparaît, pour éviter qu'une valeur tapée là ne soit silencieusement remplacée au
prochain enregistrement.

**Liste "Tous les membres"** (§9) : colonnes **Photo | Nom | Fonction / rôle | Localisation |
Langues | Ordre** — colonne native "Date" retirée (même choix que Cheval, peu de valeur dans ce
contexte métier). Photo = miniature WordPress native (`get_the_post_thumbnail($id, array(40,
40))`), jamais l'image originale. Langues affiche une représentation compacte ("Français,
Anglais" ; "Autre" affiché via sa précision saisie quand elle existe, plus lisible qu'un simple
"Autre" répété). Ordre = `menu_order` natif (même mécanisme que Cheval, aucun glisser-déposer dans
ce lot). Recherche WordPress native pleinement fonctionnelle sans aucun code supplémentaire — le
titre EST le nom du membre.

**Non développé dans ce lot** (périmètre volontairement limité, conformément à la demande) :
comptes utilisateurs liés, login, planning, horaires, contrats, salaires, RH, disponibilité,
réservations, relations avec les chevaux, affectation à des prestations, catégories/départements/
organigramme d'équipe, taxonomie des fonctions/spécialités/diplômes, CV PDF, import Excel/CSV,
génération PDF, rendu front, schema.org spécifique, connexion aux API des réseaux sociaux. Ces
besoins seront réévalués uniquement s'ils apparaissent dans des usages clients réels.

Voir `tests/gws-equestrian-membre-logic-test.php` pour la couverture dédiée (140 assertions :
membre vide/minimal, titre automatique dans tous les cas demandés, sauvegarde/rechargement de tous
les champs des trois sections, langues multiples, "Autre" + Préciser et son nettoyage au
réenregistrement, sanitation e-mail/URLs, téléphone/WhatsApp jamais dénaturés, colonnes de liste,
sécurité de la sauvegarde, absence d'effet de bord sur Cheval/Prestation/Groupe).

## Labels ANSF (0.15.0)

Nouveau lot volontairement minimal complétant le modèle métier Cheval avant le rendu web : un
onglet **Labels** (`includes/cheval-labels.php`), limité aux labels Selle Français / ANSF identifiés
pour la commercialisation initiale en France. AUCUN moteur générique de distinctions, AUCUN
référentiel multi-stud-books — un futur label d'un autre organisme (KWPN, BWP, HOLST...) est un
nouveau lot à part entière, jamais une simple entrée de plus dans une liste ici.

**Contenu, dépendant du sexe du cheval** :
- **Selle Français Originel (SFO)** — case à cocher, disponible pour femelle, mâle ET hongre,
  jamais restreint ni touché par un changement de sexe.
- **Labels poulinières** (Label Sport / Label Élevage / Label Modèle & Allures) — UNIQUEMENT
  femelle. Chaque famille est un ENUM fermé à quatre valeurs mutuellement exclusives (`none`/
  `tres_bonne`/`excellente`/`elite`), rendu via un groupe de boutons radio — jamais quatre cases à
  cocher indépendantes qui permettraient une incohérence ("Sport — Élite" ET "Sport — Très Bonne"
  simultanément).
- **Étalon SF Génétique Avenir** — case à cocher, mâle ET hongre : un hongre peut avoir obtenu ce
  statut ou eu une carrière de reproducteur avant castration, sa semence pouvant encore être
  commercialisée.

Aucune autre règle métier : GWS empêche seulement les incohérences évidentes liées au sexe, jamais
un moteur de certification ANSF (pas de contrôle de race/stud-book, d'âge, de pedigree, de statut
reproducteur, d'organisme émetteur, d'année d'obtention, d'URL de certification ou de justificatif).

**Données structurées** : cinq valeurs techniques stables — `_gwseq_label_sfo`,
`_gwseq_label_sf_genetique_avenir` (booléens `'1'`/`''`), `_gwseq_label_sport`,
`_gwseq_label_elevage`, `_gwseq_label_modele_allures` (enums) — jamais des libellés destinés à
l'affichage. Choisies pour qu'une correspondance future vers un pictogramme officiel ANSF (pas
encore disponible, demande en cours auprès de l'ANSF) reste triviale à construire plus tard (ex.
`sfo -> asset SFO`, `sport_elite -> asset correspondant`) — aucune fonction de correspondance ni
pictogramme temporaire n'est ajouté ici, ce sera une évolution séparée une fois les fichiers
officiels disponibles ; dans l'admin, les libellés texte suffisent pour l'instant.

**Sanitation serveur obligatoire**, SEULE autorité (`gwseq_sanitize_cheval_labels_input($raw,
$sexe)`) — jamais une dépendance à l'affichage conditionnel admin, qui n'est qu'un confort de
saisie : un payload délibérément incohérent (ex. labels poulinières soumis pour un mâle) ne peut
jamais produire une donnée incohérente en base.

**Changement de sexe d'un cheval existant** : $sexe est TOUJOURS la valeur déjà sanitisée de la
MÊME soumission (`gwseq_sanitize_cheval_identity_input($_POST)['sexe']`), jamais relue depuis une
meta pas encore enregistrée ni l'ancien sexe déjà en base. Les labels devenus incompatibles avec le
sexe fraîchement soumis sont donc nettoyés silencieusement au prochain enregistrement volontaire :
passage vers mâle/hongre remet les trois labels poulinières à `none` ; passage vers femelle remet
Étalon SF Génétique Avenir à vide. **SFO n'est jamais touché**, quel que soit le sexe. Un sexe non
renseigné nettoie les deux groupes sexe-dépendants (repli prudent, jamais un label affiché pour un
sexe qui ne peut pas être confirmé).

**Duplication d'un cheval retirée de la roadmap V1** : avec l'import IFCE, son intérêt est devenu
faible et sa maintenance créerait des risques inutiles à mesure que l'objet Cheval s'enrichit —
aucun développement engagé sur ce sujet.

Voir `tests/gws-equestrian-cheval-labels-test.php` pour la couverture dédiée (34 assertions :
sanitation pure pour les trois sexes, exclusivité des familles, payload invalide, rendu réel,
sauvegarde/rechargement, changement de sexe dans les deux sens, sécurité de la sauvegarde).

## Corrections de clôture du back-office Cheval V1 (0.16.0)

Suite à un audit fonctionnel du back-office Cheval en conditions réelles (module jugé
fonctionnellement mature), deux correctifs ciblés avant le gel de la V1 — aucun refactor, aucune
nouvelle fonctionnalité au-delà de ce qui suit.

**1. Nettoyage des relations père/mère à la suppression définitive d'un cheval**
(`includes/cheval-pedigree.php`). Cause exacte du symptôme observé (sélecteur "Cheval déjà
enregistré" vide, pedigree résolu affichant "Cheval introuvable (#ID)") : `gwseq_get_horse_parent()`
lit fidèlement les métadonnées `mode`/`horse_id` enregistrées sans jamais revérifier, à la lecture,
que le post référencé existe encore — comportement volontaire, une vérification d'existence à
chaque lecture serait coûteuse pour un cas qui ne devrait survenir qu'au moment précis d'une
suppression définitive. Aucun hook n'intervenait jusqu'ici pour nettoyer la relation en amont d'une
telle suppression.

**Corbeille ≠ suppression définitive**, préservé à l'identique : `wp_trash_post()` ne déclenche
jamais `before_delete_post`, donc mettre un parent à la corbeille ne modifie ni ne supprime jamais
ses produits ni la relation stockée — le parent à la corbeille reste un post réel en base, la
relation reste intacte, une restauration la retrouve automatiquement. Seule la suppression
DÉFINITIVE (bouton dédié, ou vidage de la corbeille) déclenche
`gwseq_cleanup_horse_parent_references_on_delete()`, accrochée uniquement à `before_delete_post`.
Elle réutilise `gwseq_get_horse_offspring()` (déjà existante, jamais dupliquée) pour retrouver tous
les chevaux référençant l'ID supprimé comme père ou mère en mode "Cheval déjà enregistré", et
réinitialise UNIQUEMENT cette relation précise à « Non renseigné » — jamais de reconstruction d'un
ascendant externe de remplacement, jamais une autre branche du pedigree touchée.

**2. Liste d'administration « Tous les chevaux » — filtres et colonnes**
(`includes/cheval-fields.php`). Quatre filtres cumulables au-dessus de la liste, tous combinables
entre eux ET avec la recherche WordPress native (jamais remplacée) :
- **Catégorie** — taxonomie `gwseq_categorie_cheval` déjà existante, aucun second système.
- **Statut commercial** et **Sexe** — référentiels métier déjà définis
  (`gwseq_cheval_statut_commercial_options()`/`gwseq_cheval_sexe_options()`), jamais une nouvelle
  nomenclature.
- **Année de naissance** — liste construite dynamiquement à partir des seules années réellement
  présentes en base (`gwseq_cheval_admin_list_annees_naissance_presentes()`, première requête
  `$wpdb` directe du module : `SELECT DISTINCT` sur `_gwseq_annee_naissance`, trié décroissant),
  jamais une liste arbitraire d'années inutilisées.

Mécanisme : `restrict_manage_posts` pour le rendu des `<select>` (les valeurs sélectionnées restent
affichées après application, WordPress ajoute nativement le bouton « Filtrer » dès qu'un contenu y
est produit), `pre_get_posts` pour l'application réelle à la requête (tax_query pour la catégorie,
meta_query en relation `AND` pour statut/sexe/année — chaque valeur revalidée contre son référentiel
avant usage, jamais une valeur `$_GET` propagée telle quelle). La pagination WordPress conserve
nativement ces paramètres, aucun code supplémentaire nécessaire.

Colonnes ramenées à **Nom | Catégories | Sexe | Année | Statut commercial | Prix | Ordre** — colonne
native « Date » retirée (peu de valeur dans ce contexte métier). Sexe affiche le libellé utilisateur
(jamais la valeur technique) ; Année affiche uniquement l'année de naissance brute ou « — » si non
renseignée (jamais un âge calculé) ; Prix et Ordre conservent leur comportement métier existant sans
modification (menu_order natif, aucun glisser-déposer ni nouveau système d'ordre dans ce lot).

Voir `tests/gws-equestrian-pedigree-logic-test.php` et `tests/gws-equestrian-cheval-logic-test.php`
pour la couverture dédiée : les 8 scénarios de nettoyage de relation, le câblage exact du hook, les
quatre filtres individuellement et combinés, la combinaison recherche + filtres, la persistance de
sélection, la déduplication et le tri décroissant des années, le rejet des valeurs hors référentiel,
les nouvelles colonnes et l'absence de la colonne Date.

## Import IFCE (Étape 7)

Second chemin de création d'une fiche Cheval, « Importer une fiche IFCE », en complément — jamais
en remplacement — de la création manuelle existante (Étapes 4-6, toujours disponible). Objectif :
supprimer la ressaisie manuelle, en particulier pour le pedigree.

### Parcours utilisateur

Depuis l'écran « Ajouter un cheval », un écran de choix (0.13.1, correctif post-recette) présente
à égalité les deux chemins — « Importer depuis l'IFCE » et « Créer manuellement » — AVANT tout
formulaire (toute requête vers l'écran natif est interceptée et redirigée vers cet écran de choix ;
« Créer manuellement » y ajoute simplement `gwseq_manual=1` pour atteindre le vrai formulaire) :

1. L'utilisateur téléverse le PDF **complet** tel que téléchargé depuis Info Chevaux (jamais
   seulement la première page) ;
2. GWS analyse le document ;
3. GWS produit une structure intermédiaire normalisée ;
4. Un écran de prévisualisation affiche ce qui a été détecté (« Cheval reconnu : ... / Identité
   détectée : ... / Indices détectés : ... / Pedigree : N ascendants détectés »), avec une case à
   cocher indépendante par section (Identité / Indices / Pedigree — import partiel possible) ;
5. L'utilisateur valide explicitement ;
6. **Seulement à ce moment**, la fiche Cheval est créée et les données sélectionnées sont écrites.

**Aucun import silencieux** : un document non reconnu comme fiche IFCE n'écrit strictement rien et
affiche un message explicite ; la création manuelle reste toujours disponible en alternative.

### Architecture (4 fichiers, aucune modification du parcours manuel existant)

- **`includes/ifce-pdf-text.php`** — extracteur PDF en PHP pur (aucune dépendance Composer/npm
  dans ce projet, aucun accès réseau disponible pour en installer une), réécrit en 0.13.1 après
  diagnostic du rejet du vrai PDF de Jamerose de Félines (voir `CHANGELOG.md` pour le détail exact
  du diagnostic) : index d'objets couvrant à la fois les objets PDF classiques ET ceux compressés
  dans un flux `/Type/ObjStm` (mécanisme PDF 1.5+ très répandu, utilisé par le générateur du vrai
  PDF testé) ; résolution, pour chaque page, des polices utilisées via son `/Resources/Font` —
  police composite `/Type0`/Identity-H décodée via sa table `/ToUnicode` (CMap
  `beginbfchar`/`beginbfrange`), police simple via une table WinAnsiEncoding standard ;
  reconstruction de ligne par changement de coordonnée Y (issue des matrices `cm`/`Tm`) plutôt que
  par les opérateurs `Td`/`TD`/`T*`, jamais utilisés par ce type de générateur de rapports. Seule la
  PREMIÈRE page est décodée (§3 : zone de synthèse principale) — un choix délibéré, les pages
  suivantes du vrai document contenant le détail de production de chaque ascendant avec ses propres
  indices, qui contamineraient sinon ceux de la fiche importée. Repli automatique sur l'ancien
  mécanisme (scan naïf de tous les blocs `stream...endstream`) si aucune page exploitable n'est
  trouvée (cas d'un PDF minimal sans arbre de pages complet, comme celui utilisé pour les tests de
  mécanique de base).
- **`includes/ifce-import-parser.php`** — reconnaissance du document (marqueur d'en-tête
  IFCE/Info Chevaux ET ligne d'identité valide, tous deux exigés — §10 de la demande) puis
  extraction vers une structure normalisée fermée `{valid, identity, indices, pedigree}`. Ne touche
  jamais à `$_POST` ni aux fonctions métier — uniquement de l'interprétation de texte pur.
- **`includes/ifce-import-mapper.php`** — convertit la structure normalisée vers les MÊMES
  fonctions métier que la saisie manuelle admin : `gwseq_set_cheval_identity()` (nouvelle
  extraction pure de `gwseq_save_cheval_meta()`, cheval-fields.php — zéro changement de
  comportement pour le formulaire manuel existant), `gwseq_set_cheval_sport_indice()` /
  `gwseq_set_cheval_genetic_indice()` (cheval-indices.php), `gwseq_set_horse_parent()`
  (cheval-pedigree.php). **Jamais un accès direct à `update_post_meta()`** : un futur import
  CSV/API/autre fournisseur pourra réutiliser ces mêmes fonctions sans aucune modification.
- **`includes/ifce-import-admin.php`** — écran d'administration : sous-menu du CPT Cheval,
  capacité `edit_posts` (cohérente avec la création d'un cheval — le CPT n'a aucune capacité
  personnalisée, à la différence des pages de réglages globales du plugin qui utilisent
  `manage_options`). Validation de sécurité du fichier (type MIME réel via `finfo`, extension
  `.pdf`, taille maximale 15 Mo, provenance réelle du téléversement via `is_uploaded_file()`) ;
  stockage de la structure analysée dans un **transient WordPress** (15 minutes, jamais dans une
  meta de fiche à ce stade) ; écran de prévisualisation relisant ce transient ; écriture
  strictement différée jusqu'à confirmation explicite (nonce vérifié, structure toujours relue
  côté serveur — jamais une donnée structurée resoumise par le client). Suppression immédiate du
  fichier PDF temporaire après extraction du texte, que l'analyse réussisse ou non.
  **Correctif « headers already sent » (0.13.2)** : le traitement des deux formulaires (upload,
  confirmation) est accroché aux hooks natifs `admin_post_{action}` de WordPress, déclenchés depuis
  `wp-admin/admin-post.php` — jamais depuis le callback de la page d'administration elle-même, que
  WordPress n'appelle qu'APRÈS avoir déjà émis le HTML du menu d'administration (une redirection à
  ce stade échoue systématiquement). La logique métier de chaque étape est extraite dans des
  fonctions pures (`gwseq_process_ifce_import_upload()`/`_confirm()`) qui ne rendent jamais de HTML
  ni ne redirigent elles-mêmes — directement testables par appel direct.

### Données extraites en V1

- **Identité** (§4) : nom, race/stud-book/appellation (mappée au référentiel Race/Stud-book/
  Appellation mutualisé, `includes/race-referentiel.php` — 154 entrées avec alias historiques,
  voir « Correctif référentiel » plus bas —, sinon "Autre" + texte libre — jamais une valeur
  inventée), sexe, robe (même principe de correspondance que la race), taille (`1m68` → 168 cm),
  année de naissance, naisseur/éleveur si identifiable clairement, numéro SIRE et UELN si présents.
  **Nom officiel et alias IFCE** (correctif runtime post-livraison, §8-10) : quand la fiche porte un
  alias ("NOM_OFFICIEL Alias NOM_D'USAGE"), c'est le nom d'usage/alias qui devient le nom de la
  fiche GWS (`post_title`) — jamais le mot littéral "Alias", jamais le seul nom officiel qui
  perdrait le nom réellement utilisé dans le sport (ex. "UNTOUCHABLE (NLD) Alias UNTOUCHABLE 27" ->
  nom GWS "UNTOUCHABLE 27"). Le nom officiel reste conservé séparément, jamais perdu, en donnée
  technique (`_gwseq_ifce_nom_officiel`, jamais exposée dans le formulaire manuel). Un marqueur pays
  IFCE entre parenthèses ("(NLD)", "(BEL)", "(DEU)"...) est retiré du nom via une liste FERMÉE de
  codes ISO 3166-1 alpha-3 reconnus (`gwseq_ifce_country_codes()`) — jamais une suppression aveugle
  de toute parenthèse (un contenu parenthésé qui n'est pas un vrai code pays reste intact).
- **Indices** (§5) : sportifs ISO/ICC/IDR — **le modèle existant a été étendu** pour stocker
  désormais aussi le coefficient de détermination (CD, `_gwseq_{cle}_cd`, jusqu'ici réservé aux
  indices génétiques) puisqu'une fiche IFCE officielle le fournit systématiquement (exemple exact :
  « ISO 115 (0.70) (2023) » → valeur 115, CD 0.70, année 2023) ; génétiques BSO/BCC/BDR — valeur +
  CD, jamais d'année (exemple exact : « BSO +12 (0.59) »). Chaque composant reste structuré
  séparément, jamais une chaîne unique.
- **Pedigree** (§6, objectif principal) : reconstruction automatique de l'arbre Père/Mère sur 3
  générations (14 ascendants) à partir du tableau généalogique du PDF, réutilisant directement
  `gwseq_sanitize_external_ancestor_tree()` / `gwseq_set_horse_parent()` /
  `gwseq_match_race_to_canonical_code()` déjà existants (Étape 5) — validé exactement contre
  l'exemple Jamerose de Félines fourni dans la demande (Père : UNTOUCHABLE 27, alias de
  "UNTOUCHABLE" — voir le correctif runtime alias ci-dessous (Père : HORS LA LOI II
  (Père : PAPILLON ROUGE, Mère : ARIANE DU PLESSIS II), Mère : PROMESSE (Père : HEARTBREAKER, Mère :
  CHABLIS)) ; Mère : NATIVE DE FELINES (Père : ROSIRE (Père : URIEL, Mère : EOLIENNE), Mère : FALINE
  GENEVRIS (Père : PEGASE GERBAUX, Mère : LOUVE VARFEUIL))). Race/stud-book des ascendants récupérée
  quand présente (référentiel mutualisé — l'alias historique "SFA" résout par exemple vers "SF",
  jamais rangé dans "Autre"). **Depuis le correctif référentiel : l'année de naissance d'un
  ascendant EST extraite** quand le document la porte et stockée dans le nouveau champ
  `annee_naissance` du modèle d'ascendant externe (`{name, race, race_autre, annee_naissance,
  father, mother}`) — jamais un âge calculé ou stocké, uniquement l'année brute. **Nom officiel et
  alias d'un ascendant** (correctif runtime, même règle que pour l'identité ci-dessus) : un
  ascendant portant un alias (ex. "CARTHAGO Alias CARTHAGO Z (DEU) HOLST 1987") est stocké sous son
  nom d'usage ("CARTHAGO Z") — le nom officiel ("CARTHAGO") est calculé et disponible côté parseur
  IFCE (`official_name`) mais n'est, à ce stade, pas persisté dans l'arbre d'ascendant partagé avec
  la saisie manuelle (choix de portée délibéré, pour ne pas modifier ce modèle commun) ; le marqueur
  pays et le stud-book, quand présents, qualifient l'alias exactement comme pour un ascendant sans
  alias.
- **Ascendants toujours importés en mode "externe"** (§8) : aucune fiche `gwseq_cheval` n'est
  jamais créée automatiquement pour un ascendant, aucune tentative de rapprochement/déduplication
  par nom avec une fiche GWS existante.

### Convention de lecture — validée contre le vrai PDF IFCE de Jamerose de Félines

La convention de lecture assumée, documentée en tête de `includes/ifce-import-parser.php`, a été
confrontée au vrai PDF de Jamerose de Félines lors de la recette 0.13.1 (voir
`tests/fixtures/ifce-jamerose-de-felines.pdf`) et confirmée exacte : la ligne d'identité est repérée
par la présence du jeton Sexe (Mâle/Femelle/Hongre) parmi 5 valeurs séparées par des virgules, le nom
du cheval étant la ligne non vide qui la précède immédiatement ; le pedigree est repéré par un titre
de section (« Généalogie »/« Pedigree »/« Origines ») suivi d'un bloc CONTIGU de lignes non vides
(le bloc s'arrête à la première ligne vide rencontrée après le premier ascendant), plafonné à 14
lignes, dans l'ordre universel de lecture d'un tableau généalogique à 3 générations (branche Père
d'abord, de haut en bas) — confirmé exact (14 ascendants, ordre et comptage exacts). Le vrai document
a également révélé trois cas particuliers désormais gérés explicitement (voir
`gwseq_ifce_parse_pedigree_entry_line()`) : une mention "Alias ..." (nom d'enregistrement alternatif)
est retirée ; une ligne composée uniquement d'une année à 4 chiffres est traitée comme la
continuation visuelle de la ligne précédente (libellé trop long pour tenir sur une ligne) ; un nom se
terminant par un chiffre romain (« HORS LA LOI II ») n'est jamais confondu avec un code de stud-book.

### Limitations connues (Étape 7)

- **Un seul niveau de flux d'objets compressés résolu** : l'extracteur PDF ne gère pas des flux
  `/Type/ObjStm` imbriqués les uns dans les autres — suffisant pour tous les générateurs PDF usuels
  rencontrés (dont celui du vrai PDF testé).
- **`/Resources` hérité d'un ancêtre `/Pages` non résolu** : chaque page doit porter directement ses
  propres `/Resources` (cas du vrai document testé, le plus courant chez les générateurs de
  rapports) — un PDF où les ressources sont héritées d'un nœud `/Pages` ancêtre ne serait pas
  reconnu correctement.
- **Zone de synthèse principale uniquement** (§3/§14) : le PDF complet est accepté, mais seule sa
  PREMIÈRE PAGE est décodée (choix délibéré, voir Architecture ci-dessus) et seule l'information
  explicitement supportée y est exploitée — jamais une donnée devinée. Volontairement hors périmètre
  V1 : production détaillée des ascendants, collatéraux, descendants, résultats exhaustifs, toute
  information sans emplacement dans le modèle GWS actuel.
- **SIRE/UELN non présents sur la fiche réelle testée** : leur extraction est implémentée et testée,
  mais la zone exploitée du vrai PDF de Jamerose ne les affiche pas explicitement — non confirmé sur
  un document qui les présenterait.
- **Aucun rapprochement avec une fiche GWS existante** : une évolution future pourra proposer un
  appariement (par nom, SIRE...) contre les chevaux déjà enregistrés, non nécessaire pour cette V1.
- **Aucune conservation du PDF** (§11) : le fichier temporaire est supprimé immédiatement après
  extraction du texte, que l'analyse réussisse ou échoue — ce n'est qu'une source d'import, jamais
  une nouvelle source de vérité stockée sur la fiche.
- **Parcours navigateur complet non exercé** : le pipeline (extraction → analyse → mapping) est
  testé automatiquement de bout en bout à partir du vrai PDF, mais le vrai parcours de téléversement
  HTTP dans un navigateur (sélection de fichier, écran de choix, écran de prévisualisation réel,
  redirections) n'a pas pu être exercé dans cet environnement — à valider en recette runtime.

Voir `tests/README.md` pour le détail complet de la couverture de tests et de ses limites.

### Pedigree (Étape 5)

**Deux types de parent, chacun indépendamment pour le Père et pour la Mère** : soit une fiche
`gwseq_cheval` déjà présente dans GWS (référence par ID, jamais par nom — renommer le parent ne
casse jamais la relation), soit un **ascendant externe structuré** (Nom + Race/Stud-book
facultative). Ni le père ni la mère ne sont jamais requis (un pedigree incomplet est parfaitement
acceptable, voir §25 : l'absence de donnée reste une absence, jamais un « Non renseigné »
affiché artificiellement demain sur le site).

**Un ascendant externe n'est pas une feuille terminale** : il peut lui-même avoir un père et une
mère, également externes, jusqu'à 3 générations (14 ascendants, alignée sur la fiche de synthèse
IFCE — voir « Correctif référentiel » plus bas, ce standard était de 4 générations avant ce
correctif) — pensé spécifiquement pour un marchand de chevaux ou un cavalier professionnel dont la
quasi-totalité des ascendants ne sont pas des chevaux qu'il gère dans GWS. Une telle structure
permet de saisir un pedigree complet sans jamais créer une seule fiche `gwseq_cheval` artificielle
pour un ancêtre qui n'a aucune raison métier d'être géré comme un cheval du client. Chaque
génération reste facultative : l'utilisateur s'arrête où il connaît son pedigree.

**Race/Stud-book/Appellation d'un ascendant externe harmonisé avec la fiche Cheval** (correction
post-recette 0.6.0, puis référentiel complet depuis le correctif référentiel) : ce champ était
initialement un texte libre, source constatée d'hétérogénéité en usage réel (« SF »/« sf »/« Selle
Français »...). Il réutilise désormais le référentiel Race/Stud-book/Appellation mutualisé
(`includes/race-referentiel.php`, 154 entrées avec alias historiques, jamais dupliqué) via le MÊME
composant de recherche/autocomplétion que l'identité du cheval, à chaque génération de chaque
branche externe — plus un `<select>` fermé, mais toujours « Autre » avec précision libre en filet
de sécurité.

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
autoritaire restant celui produit par le serveur). Un compteur discret (« Génération N sur X »,
« Génération X sur X — dernière génération », X = `GWSEQ_PEDIGREE_MAX_DEPTH`, désormais 3) accompagne
chaque niveau — la recette a aussi montré que l'utilisateur ne savait pas jusqu'où remonter alors
que GWS connaît parfaitement cette limite. À la dernière génération autorisée, plus aucun contrôle
« + Renseigner ses origines » n'est proposé (arrêt visuel strict) ; la limite serveur, elle, reste
inchangée et est la seule garantie réelle contre une requête manipulée.

#### Correctif référentiel — Race / Stud-book / Appellation, ascendant + année de naissance, pedigree sur 3 générations

Refonte livrée à partir du référentiel `GWS_referentiel_races_appellations_IFCE.xlsx` fourni, sans
toucher à la Commercialisation, aux Médias, à la Présentation, ni au reste de l'import IFCE :

- **Référentiel unique et découplé de l'UI** (`includes/race-referentiel.php`, 154 entrées : 151
  races/stud-books + 3 appellations OC/ONC/OE) — le code canonique (`SF`, `KWPN`, `OC`...) reste
  TOUJOURS la donnée structurée stockée, jamais un libellé. Réutilisable à l'identique par l'admin,
  le parseur IFCE, et un futur import CSV/API : lecture par code, résolution d'alias/libellé vers le
  code canonique, recherche partielle, sanitation, libellé d'affichage, type race/appellation.
- **Un seul composant de recherche partout** (`gwseq_render_race_referentiel_field()` +
  `assets/race-referentiel-autocomplete.js`/`.css`) remplace l'ancien `<select>` d'une vingtaine de
  races codées en dur, pour l'identité du cheval ET chaque génération d'ascendant externe. Recherche
  par code IFCE, libellé IFCE, libellé GWS ou alias — utilisable sans connaître les codes IFCE.
- **Alias historiques reconnus** (ex. « SFA » résout vers « SF », jamais rangé dans « Autre ») —
  "Autre — préciser" reste le seul filet de sécurité quand rien ne correspond réellement.
- **Appellations intégrées au même moteur que les races** (OC, ONC, OE) sous un unique libellé
  « Race / Stud-book / Appellation », la distinction technique `type` restant interne.
- **Récents par utilisateur** (user meta, jamais une modification de la donnée Cheval) : à
  l'ouverture d'un champ vide, les valeurs récemment utilisées par CET utilisateur plutôt que les
  154 entrées — jamais un profil métier rigide CSO/dressage/poney codé en dur.
- **Année de naissance d'un ascendant externe** : nouveau champ optionnel du modèle (`{name, race,
  race_autre, annee_naissance, father, mother}`), alimenté automatiquement par l'import IFCE quand
  disponible, jamais utilisé pour calculer ou stocker un âge.
- **Pedigree standard réduit de 4 à 3 générations** (14 ascendants, alignée sur la fiche IFCE) —
  `GWSEQ_PEDIGREE_MAX_DEPTH` référencé symboliquement partout (sanitation, resolver, rendu). Une
  éventuelle donnée de génération 4 déjà enregistrée avant ce correctif n'est **jamais supprimée** :
  elle reste conservée en base tant que l'ascendant de génération 3 concerné garde le même nom, mais
  n'est plus jamais interrogée ni affichée par le resolver/rendu standard, et le formulaire ne peut
  plus la proposer ni la modifier.

Voir `tests/gws-equestrian-race-referentiel-test.php` pour la couverture dédiée au référentiel, et
la section « Compatibilité avec les données de génération 4 » de
`tests/gws-equestrian-pedigree-logic-test.php` pour la non-destructivité de ce changement.

**Convention de présentation des noms de chevaux** (nouveau helper partagé,
`gwseq_format_horse_name_display()` dans `cheval-fields.php`) : dans les intitulés contextuels du
pedigree, un nom s'affiche en MAJUSCULES ET SANS ACCENTS (« Étoile du Lys » → « ETOILE DU LYS » ;
apostrophes/traits d'union/chiffres/espaces conservés). Uniquement une présentation : `post_title`
et le nom d'un ascendant externe restent enregistrés exactement tels que saisis, jamais transformés
à l'enregistrement. Ne s'applique jamais à Race/Stud-book, qui reste une valeur structurée via
référentiel. Réutilisable plus tard par le front, un export PDF, l'impression, un catalogue, ou le
Social Kit.

#### Correctif runtime 0.14.2 — cause racine réelle de l'autocomplétion, robustesse de l'extraction IFCE

Le correctif logique 0.14.1 (sélection au focus, mise à jour synchrone sur `blur`, filet de
sécurité à la soumission) restait sans effet visible sur un vrai wp-admin, alors que le test
d'exécution JS restait vert. **Cause racine réelle** : `assets/race-referentiel-autocomplete.js`
contenait un caractère Unicode LITTÉRAL multi-octet directement dans le code exécutable d'une
expression régulière (plage de diacritiques combinants U+0300-U+036F, écrite en clair dans le
fichier plutôt qu'en échappement `\u`). Un tel caractère dépend d'un encodage/transfert fidèle en
UTF-8 à CHAQUE maillon (hébergement, CDN, extraction d'archive...) ; corrompu par n'importe lequel
d'entre eux, il produit une ERREUR DE SYNTAXE qui empêche le navigateur de parser le fichier — tuant
silencieusement TOUT le script, un risque qu'aucune exécution directe du texte source fidèle (Node,
le test simulé) ne pouvait jamais révéler. Remplacé par l'échappement ASCII `\u0300-\u036f`,
strictement équivalent mais structurellement insensible à ce risque. Une instrumentation de
diagnostic TEMPORAIRE (préfixe console `[gwseq-race]`) a été ajoutée pour permettre, si le problème
persistait malgré ce correctif, de confirmer directement depuis un vrai navigateur l'étape exacte où
l'exécution diverge.

Par ailleurs, l'extraction de l'identité IFCE (`gwseq_ifce_parse_identity_from_lines()`) gérait mal
un nombre variable de segments sur la ligne "Race, Sexe, Robe, Taille, né(e) en AAAA[, étalon]" —
Robe et Taille sont chacune FACULTATIVES indépendamment sur des fiches réelles, une position figée
perdait l'année de naissance sur certaines (Untouchable 27, Asb Conquistador) et rejetait
intégralement une fiche à seulement 3 segments (Quaprice Bois Margot, ni robe ni taille). Corrigé
par une détection dynamique de la position réelle du sexe et de la mention "né(e) en AAAA"
elle-même, plutôt qu'une position supposée. Un même stud-book (KWPN, BWP, HAN, SF, OE) résout
désormais aussi vers un code canonique unique quel que soit son libellé IFCE rencontré (identité ou
pedigree) — voir `includes/race-referentiel.php`.

#### Correctif runtime 0.14.3 — filet de sécurité obligatoire sur le champ Race, régression Unicode réintroduite, ajustement UX de la prévisualisation IFCE

Recette du correctif 0.14.2 : l'extraction IFCE et la normalisation croisée (points B et C
ci-dessus) confirmées fonctionnelles en conditions réelles, mais l'autocomplétion Race restait
totalement non fonctionnelle sur un vrai wp-admin — race non modifiable sur une fiche déjà
renseignée, race non saisissable du tout sur une fiche vide, sans aucun contrôle de repli — malgré
des logs navigateur prouvant un chargement, une analyse et une initialisation intégralement réussis
(référentiel de 154 entrées chargé, 15 champs trouvés et initialisés sans exception). Ces logs
écartaient toute cause de chargement/syntaxe/initialisation déjà envisagée, orientant la recherche
vers l'exécution réelle des gestionnaires d'événement (frappe, clic), jamais exercée par le test
Node synthétique existant.

**Régression détectée avant livraison.** La réécriture de l'instrumentation de diagnostic pour cette
recette avait réintroduit EXACTEMENT le défaut corrigé en 0.14.2 : un caractère Unicode combinant
littéral multi-octet dans le code exécutable de `normalize()`, au lieu de l'échappement ASCII
`\u0300-\u036f`. Détecté par une vérification octet-par-octet du fichier (`od -c`) plutôt qu'une
simple relecture — une réécriture complète d'un fichier JS ne garantit jamais par elle-même
l'absence de cette classe de risque. Corrigé avant toute livraison ; cette régression n'a jamais
atteint la version 0.14.2 elle-même.

**Instrumentation exhaustive ajoutée, cause runtime non encore reproduite en test.** Dix points de
diagnostic (valeur brute reçue, valeur normalisée, nombre de résultats, premiers résultats, code
caché avant/après, création et contenu du conteneur de suggestions, rapport de visibilité réel via
`getComputedStyle`) couvrent désormais tout le flux `input → normalisation → recherche → résultats →
rendu DOM → visibilité → sélection → synchronisation du code caché`. Chaque gestionnaire d'événement
(focus, saisie, perte de focus, clavier, clic sur un résultat, soumission) est désormais entouré de
son propre `try`/`catch` : une exception survenant pendant une interaction réelle serait maintenant
visible dans la console (`[gwseq-race] ... exception in ... handler:`) plutôt que silencieusement
avalée par le navigateur. `config.suggestions` (le "5" observé dans les logs) est explicitement
documenté et tracé comme le repli affiché UNIQUEMENT au focus d'un champ vide (valeurs récentes de
l'utilisateur) — toute saisie non vide recherche TOUJOURS dans les 154 entrées de `config.entries`.

**Filet de sécurité obligatoire, indépendant de la résolution de la cause runtime.** Une donnée
métier essentielle comme la race ne doit jamais devenir impossible à saisir. `includes/race-referentiel.php`
(`gwseq_render_race_referentiel_field()`) rend désormais TOUJOURS, à côté du composant de recherche,
un `<select>` natif complet portant par défaut le VRAI nom de champ soumis — fonctionnel sans
JavaScript. `activateField()` (`assets/race-referentiel-autocomplete.js`) ne transfère ce nom réel
vers le composant de recherche, puis ne désactive et ne masque ce `<select>`, qu'à la toute fin d'une
initialisation ayant réussi sans la moindre exception ; si le script ne s'exécute jamais, échoue à
charger, ou lève une erreur n'importe où avant ce point, le `<select>` reste le SEUL contrôle actif —
pour l'identité du cheval comme pour chaque génération d'ascendant externe du pedigree (même
composant partagé). `assets/cheval-admin.js` (suppression d'un ascendant externe) réinitialise
désormais aussi ce `<select>` de secours.

**Ajustement UX de la prévisualisation IFCE** (`includes/ifce-import-admin.php`, purement
l'affichage — ni le parseur ni les données extraites ne sont modifiés) : l'identité détectée
s'affiche désormais en lignes explicitement étiquetées (Race / Stud-book, Sexe, Robe, Taille, Année
de naissance) plutôt qu'en un résumé unique concaténé par des virgules, où un « non détectée » isolé
ne permettait pas de savoir à quelle donnée il se rapportait.

#### Correctif complémentaire 0.14.6 — cause racine réelle du bug "Préciser" (soumission sans interaction)

Le correctif "Préciser" de la 0.14.5 restait insuffisant : une race canonique correctement affichée
au chargement réapparaissait avec "Préciser" rempli après un simple clic sur "Publier"/"Mettre à
jour", SANS avoir touché au champ Race — y compris en modifiant seulement un champ sans rapport.
**Cause exacte** : `hasPickedThisSession` (`assets/race-referentiel-autocomplete.js`) démarrait à
`false` sans condition, y compris pour un champ déjà correctement rempli au chargement — le filet
de sécurité de soumission (déclenché sur N'IMPORTE QUEL submit du formulaire) traitait alors le
libellé affiché comme une saisie jamais validée et réécrivait le code en "autre" + recopiait ce
libellé dans "Préciser". **Correctif minimal** : un champ chargé avec un code déjà présent démarre
désormais comme une sélection déjà validée (`hasPickedThisSession = codeInput.value !== ''`) ;
`focus`/`input` continuent de la repasser à `false` dès que l'utilisateur touche réellement le
champ. Nouveaux tests JS reproduisant littéralement le scénario "jamais touché, formulaire soumis"
et son inverse, vérifiés positifs contre le correctif et négatifs contre l'ancien code.

#### Correctifs post-recette 0.14.5 — pedigree IFCE, bug "Préciser" persistant, rattachement Père/Mère GWS

**A — Reconstruction incorrecte de certains pedigrees IFCE.** Deux VRAIS documents distincts (Asb
Conquistador, Cornet Obolensky) présentaient EXACTEMENT le même motif : `CORRADO I Alias SAN
PATRIGNANO CORRADO` suivi, sur la ligne suivante, de `(DEU) HOLST 1985` — pays, stud-book et année
débordés ensemble sur une seconde ligne. Seule une ligne composée uniquement d'une année isolée
était jusqu'ici reconnue comme continuation ; cette ligne ne l'était pas et devenait un ASCENDANT
FANTÔME ("HOLST 1985"), décalant d'un rang tous les ascendants suivants. Corrigé par
`gwseq_ifce_looks_like_pedigree_continuation_line()` (`ifce-import-parser.php`), qui reconnaît
désormais toute ligne réduite à un marqueur pays/stud-book/année comme une continuation. Détecter
« 14 ascendants » ne suffit pas — un parser peut trouver le bon nombre en les plaçant aux mauvaises
positions — de nouveaux tests vérifient l'arbre RÉEL (nom, alias, race, année, position, père,
mère) contre les deux vrais PDF.

**B — Bug "Préciser" persistant.** Une race canonique correctement sélectionnée pouvait réafficher
un texte "Préciser" non vide après certaines sauvegardes. Double cause : le composant de recherche
ne vide jamais son propre champ "Autre" en resélectionnant une race canonique (le texte libre reste
soumis) ; le `<select>` de secours (0.14.3) n'avait quant à lui AUCUNE condition de visibilité sur
son propre bloc "Préciser". `gwseq_sanitize_race_referentiel_autre($race, $raw_autre)`
(`race-referentiel.php`, fonction UNIQUE réutilisée par identité et pedigree) force désormais une
chaîne vide dès que `$race` n'est pas "autre" ; `gwseq_render_race_referentiel_field()` masque aussi
le bloc du `<select>` de secours (attribut `onchange` en JavaScript pur, indépendant du script
principal) et auto-guérit à l'affichage une donnée déjà enregistrée avant ce correctif.

**C — Rattacher Père/Mère à des chevaux GWS pendant l'import IFCE (évolution).** L'écran de
prévisualisation propose désormais, pour les deux parents DIRECTS uniquement, un choix entre
"Importer comme ascendant externe" (répli par défaut), "Lier à un cheval déjà enregistré" et "Ne pas
importer ce parent". `gwseq_ifce_map_import()` relaie ce choix vers `gwseq_set_horse_parent()`
(MÊME fonction que la saisie manuelle du pedigree, jamais dupliquée), en traitant Père puis Mère
dans cet ordre — ce qui suffit à appliquer le contrôle "même cheval jamais père ET mère" sans aucun
code de validation supplémentaire. Sans effet si "Importer le pedigree" reste décoché.

#### Correctif runtime 0.14.4 — cause exacte de l'échec d'initialisation : un `<ul>` ne peut être placé dans un `<p>`

Recette du filet de sécurité 0.14.3 : le `<select>` de secours s'affichait bien (garantie tenue),
mais confirmait que le composant de recherche restait non initialisé sur un vrai wp-admin — logs
montrant `search=true codeInput=true` mais **`resultsList=false`** au moment de `initField()`, avec
`aborting init for this field only`.

**Cause exacte** : `gwseq_render_race_referentiel_field()` imprime un `<ul class="gwseq-race-field__results">`.
Les deux appelants (`cheval-fields.php` pour l'identité, `cheval-pedigree.php` pour chaque
génération d'ascendant externe) enveloppaient cet appel dans un `<p>...</p>`. La spécification
HTML5 (WHATWG) interdit tout contenu "flow" (`<ul>`, `<div>`, `<table>`...) dans un `<p>` : un
navigateur réel ferme IMPLICITEMENT le `<p>` (et tout ce qui est encore ouvert à l'intérieur, y
compris les `<span>` du composant) dès qu'il rencontre le `<ul>`, l'expulsant hors de
`.gwseq-race-field` — exactement ce que révélait `resultsList=false`. Le test d'exécution JS de ce
dépôt construit son DOM simulé via `appendChild()`, jamais via un vrai parseur HTML : il ne pouvait
structurellement pas révéler ce défaut, d'où un nouveau test structurel dédié
(`gws_test_assert_no_flow_content_inside_p()` dans `gws-equestrian-cheval-logic-test.php` et
`gws-equestrian-pedigree-logic-test.php`) qui rejoue à la main, sur le HTML source réellement
produit par PHP, la règle exacte de fermeture implicite du `<p>` — vérifié positif contre l'ancien
balisage, négatif contre le nouveau.

**Correctif minimal** : les deux appels sont désormais enveloppés dans un `<div>`, jamais un `<p>` —
aucune modification de la fonction partagée, du parseur IFCE, du référentiel, du pedigree ou de la
logique métier. Le docblock de `gwseq_render_race_referentiel_field()` documente désormais
explicitement cette contrainte.

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
| `..._externe` | string (JSON) | Arbre récursif `{name, race, race_autre, annee_naissance, father, mother}` (branche externe ; peut rester stocké même inactif) |

L'arbre JSON de la branche externe a la même forme à chaque niveau : `name` (texte, obligatoire
pour qu'un nœud existe), `race` (code canonique du référentiel Race/Stud-book/Appellation mutualisé,
`includes/race-referentiel.php`, toujours facultatif), `race_autre` (texte, uniquement si
`race === 'autre'`), `annee_naissance` (entier, toujours facultatif, jamais utilisé pour calculer un
âge), `father`/`mother` (même structure, récursivement, jusqu'à `GWSEQ_PEDIGREE_MAX_DEPTH - 1` = 2
niveaux sous le premier ascendant externe — soit 3 générations au total pour cette branche, cohérent
avec la profondeur du resolver). Choix JSON plutôt que `serialize()` PHP : lisible, indépendant du langage
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

**Définition exacte des « 3 générations »** (identique pour une branche GWS ou externe — profondeur
standard réduite de 4 à 3 par le correctif référentiel, voir plus haut) :

```
Cheval courant = génération 0 (toujours entièrement résolu)
Parents = génération 1                          (2 nœuds max)
Grands-parents = génération 2                   (4 nœuds max)
Arrière-grands-parents = génération 3           (8 nœuds max)
```

Soit 14 nœuds d'ascendants au maximum, alignée sur la fiche de synthèse IFCE. **Correction 0.7.0**,
inchangée par le correctif référentiel : un nœud de la génération 3 (désormais la dernière
autorisée) est strictement terminal — ses clés `father`/`mother` sont totalement ABSENTES du
tableau, pas seulement `null`. Avant la correction 0.7.0, un nœud sentinelle `{type:
"depth_limit"}` occupait ces clés ; la recette avait révélé que cela laissait croire, dans la boîte
de vérification, qu'une génération supplémentaire existerait dans le modèle (affichage « Père : Non
renseigné »/« Mère : Non renseigné ») — alors qu'elle est hors périmètre, jamais saisissable ni
stockée. Le type de nœud `depth_limit` n'existe donc plus du tout depuis 0.7.0. **Compatibilité non
destructive** : une éventuelle donnée de génération 4 déjà enregistrée AVANT le correctif référentiel
n'est jamais supprimée par ce changement — voir « Correctif référentiel » plus haut.

**Structure produite par le resolver** (`gwseq_resolve_horse_pedigree($cheval_id)`), à titre
d'exemple (ici avec seulement 2 générations pour rester lisible ; en génération 3, `father` et
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
6. Vérifier que chaque génération affiche son compteur (« Génération 1 sur 3 »... « Génération 3
   sur 3 — dernière génération »), et qu'aucun bouton de divulgation supplémentaire n'apparaît à
   la génération 3.
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

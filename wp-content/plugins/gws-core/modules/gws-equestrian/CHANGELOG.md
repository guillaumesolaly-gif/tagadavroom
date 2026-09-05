# Changelog — GWS Equestrian (module)

Historique propre à ce module, distinct de la version du plugin `gws-core` qui l'héberge (voir
`GWSEQ_MODULE_VERSION` dans `module.php`). Le module atteindra la version `1.0.0` au gel de la V1
(fin de la dernière étape du plan de développement validé). Chaque étape ci-dessous a été livrée
puis recettée en conditions réelles avant validation de la suivante.

## 0.41.1 — Correctif de recette Lot 2C : double `<title>` sur `/selection/{token}/`

Recette du Lot 2C validée fonctionnellement ; un défaut constaté pendant la recette : le `<head>`
de `/selection/{token}/` contenait DEUX balises `<title>` — le titre GWS écrit à la main
(`Chevaux pour Juliette`/`Sélection de chevaux`), puis un second `<title>` généré par WordPress
(`GWS Equestrian`, le nom du site).

**Cause exacte** : le thème `gws-starter` déclare `add_theme_support('title-tag')`
(inc/setup.php), ce qui accroche `_wp_render_title_tag()` (WordPress core) sur le hook `wp_head` —
un mécanisme NATIF qui échoue lui-même un `<title>` complet dès que `wp_head()` est appelé. Cette
page appelant `wp_head()` (pour charger les assets globaux déjà enregistrés), ce mécanisme
s'exécutait donc EN PLUS du `<title>` déjà écrit à la main juste avant, WordPress ne reconnaissant
pas cette route comme une page/un article réel et retombant sur le nom du site. Vérifié :
`/partage/{token}/` (Cheval) n'a JAMAIS ce problème — cette route réutilise `get_single_template()`
(la hiérarchie de gabarits native de WordPress, `get_header()`/`wp_head()` ne s'exécutent qu'une
fois, dans le contexte d'une vraie requête singulière simulée) et n'écrit elle-même aucun `<title>`
manuel nulle part ; la même cause architecturale ne s'y applique donc pas — rien n'y a été changé.

**Correctif** : retiré le `<title>` écrit à la main dans `gwseq_selection_render_public_html()`
(includes/cheval-selection-front.php), remplacé par le mécanisme WordPress prévu exactement pour
ce cas — le filtre `pre_get_document_title`, qui court-circuite `wp_get_document_title()` avant
tout autre traitement (celui-là même que `_wp_render_title_tag()` appelle) — plutôt que de
supprimer arbitrairement le `<title>` natif ou de filtrer la sortie HTML a posteriori
(bufferisation). Le filtre est ajouté via une fermeture LOCALE à cet appel précis (jamais un
`add_filter()` global gated par une query var) : il n'existe que le temps de cet appel de
fonction, qui ne s'exécute lui-même que pour un token de sélection déjà résolu sur cette route
précise — aucun effet possible sur une autre page/article/fiche Cheval/route du site, aucune
requête supplémentaire (le titre déjà résolu dans `$view['titre']` est réutilisé tel quel).
`og:title`/`og:description`/`og:url`/`og:image`, `noindex`, le nocache, les tokens, les règles de
diffusion, les liens public/privé et le partage WhatsApp/SMS/Copier restent strictement inchangés.

Tests étendus (`tests/gws-equestrian-cheval-selection-front-test.php`) : une simulation fidèle de
`_wp_render_title_tag()` (accrochée sur `wp_head`, reproduisant exactement le mécanisme natif en
cause) est ajoutée à l'environnement de test, avec un titre de repli délibérément DIFFÉRENT
("GWS Equestrian") pour distinguer sans ambiguïté "notre titre" du repli natif. Vérifié : exactement
UN SEUL `<title>` dans le document produit (avec et sans titre de sélection), contenant le bon
texte, sans plus jamais aucune trace du repli natif WordPress. Vérifié par retrait/restauration :
réintroduire le `<title>` manuel fait échouer exactement les quatre assertions dédiées (avec/sans
titre × unicité/absence du repli natif), aucune autre. Intégralité de la suite (24 fichiers PHP +
4 suites JS runtime) ré-exécutée : aucune régression.

## 0.41.0 — Nettoyage du diagnostic de performance + Lot 2C : partage d'une Sélection (WhatsApp/SMS/Copier, Open Graph)

**A. Clôture de l'anomalie performance.** Correctif validé en recette réelle sur Jamerose :
temps total ~35,7 s → 589,3 ms ; `gwseq_add_cheval_pedigree_meta_boxes()` ~35,1 s → 7,8 ms ; les
deux nouvelles requêtes SQL de `gwseq_get_horse_offspring()` : 5,5 ms cumulés. Contrôle
fonctionnel sur Faline : ses deux produits restent bien présents dans Production. L'instrumentation
temporaire de diagnostic, sa mission terminée, est intégralement retirée : `includes/cheval-perf-
diagnostic.php` supprimé, son chargement retiré de `module.php`, `SAVEQUERIES` n'est plus jamais
activé par ce module. Le fichier de test dédié (`tests/gws-equestrian-cheval-perf-diagnostic-
test.php`) est supprimé avec le code qu'il couvrait (même convention que le retrait du module
Mises en avant, 0.21.0). Le correctif de `gwseq_get_horse_offspring()` lui-même (0.40.0) N'EST PAS
modifié. Suite passée à 24 fichiers PHP + 4 suites JS runtime après ce retrait.

**B. Lot 2C — Partager une sélection.** La sélection de plusieurs chevaux (Lot 2B, validé) devient
partageable depuis le BO, sur le même principe que le partage individuel d'un cheval — en
réutilisant, jamais en dupliquant, les mécanismes déjà éprouvés de `includes/cheval-share.php`/
`assets/cheval-share-admin.js`.

**Message de partage WhatsApp/SMS/Copier** (`gwseq_build_selection_share_message()`, includes/
cheval-selection.php) : texte brut ENTIÈREMENT déterministe (titre éventuel + phrase fixe + lien),
volontairement plus simple que le partage Cheval — aucune composition interactive, aucune case à
cocher identité/prix/vidéo : la page de sélection elle-même est le support destiné à cette
information, jamais le message qui y renvoie. Calculé UNE FOIS côté serveur
(`gwseq_selection_admin_row()`) et transmis tel quel au script : contrairement à l'écran
« Partager », aucun nouvel endpoint AJAX n'est nécessaire. Le lien figure toujours explicitement
dans le corps du message, même si une preview Open Graph est disponible (WhatsApp/SMS/iMessage ne
la garantissent jamais). `buildWhatsappUrl()`/`buildSmsUrl()` (assets/cheval-selection-admin.js)
sont une copie verbatim des mêmes fonctions déjà validées de assets/cheval-share-admin.js
(`api.whatsapp.com/send` plutôt que `wa.me`, séparateur `sms:&body=`/`sms:?body=` selon iOS/
Android) — jamais une seconde logique divergente. Interface BO : trois nouveaux boutons WhatsApp/
SMS/Copier (message complet) dans la colonne Actions de chaque sélection, aux côtés de "Supprimer"
déjà existant — la colonne "Lien" (URL + "Copier le lien") reste inchangée, distincte (elle copie
le lien SEUL). Aucun CRM, destinataire, historique d'envoi, tracking commercial ou statut "envoyé"
n'est ajouté.

**Open Graph de `/selection/{token}/`** (`gwseq_selection_get_og_data()`, includes/cheval-
selection.php ; `gwseq_selection_render_og_meta()`, includes/cheval-selection-front.php) : og:title
= titre de la sélection, ou "Sélection de chevaux" sans titre (réutilise exactement
`gwseq_selection_display_title()`, jamais une seconde règle de repli) ; description déterministe
fixe ("Découvrez cette sélection de chevaux."), jamais générée à partir du contenu (contrairement à
Cheval) — §2 interdit toute injection de contenu inventé ou dérivé des chevaux dans les canaux de
partage. Image : photo principale du PREMIER cheval ACTUELLEMENT diffusable qui EN A UNE
(`gwseq_selection_get_og_image()`, recalculée à chaque appel à partir de `gwseq_selection_get_
public_view()` — jamais un second calcul d'éligibilité) ; un cheval diffusable sans photo est
silencieusement ignoré pour ce seul calcul, jamais retiré de l'affichage de la sélection
elle-même. Si aucun cheval diffusable n'a de photo, aucune balise `og:image` n'est émise : ce
projet ne dispose aujourd'hui d'aucun fallback visuel générique GWS/site pour les métadonnées
sociales (vérifié dans gws-core et le thème gws-starter) — §3 est explicite ("ne jamais générer
artificiellement une image"), aucun fallback n'a donc été inventé pour l'occasion. Aucune balise
Twitter/X dédiée, exactement comme le partage individuel Cheval (repli natif de Twitter/X vers
`og:`, une seule architecture, jamais un second système SEO/social parallèle). `noindex`/nocache/
inaccessibilité sans token/absence d'exposition du token restent strictement inchangés — Open
Graph n'est qu'un enrichissement de la même page déjà protégée.

**Sécurité/compatibilité** : le token n'est jamais régénéré par le partage (fonctions de LECTURE
pure) ; modifier une sélection ne change jamais son URL (déjà garanti depuis le Lot 2B, revérifié
ici) ; supprimer une sélection continue d'invalider immédiatement son lien ; aucun changement du
modèle de données Cheval, des règles public/diffusion privée/en préparation, ni du schéma SQL.

Tests étendus sur les quatre fichiers déjà existants de la Sélection (logic/admin/front/runtime) :
message avec/sans titre (conforme à l'exemple exact de la demande, aucune fuite du libellé de
repli d'affichage dans le message), lien présent explicitement, token stable après composition
répétée ET après modification ultérieure, OG avec/sans titre, image du premier cheval diffusable
ayant une photo (le précédent, sans photo, correctement ignoré sans être retiré de l'affichage),
absence de balise si aucune photo, cheval passé "En préparation" qui cesse de fournir l'image OG,
absence de toute balise Twitter/X, sélection inexistante/supprimée toujours inaccessible (déjà
couvert, revérifié), `noindex` conservé, boutons WhatsApp/SMS/Copier réellement exécutés (JS,
liens `href`/copie presse-papier vérifiés, aucun appel AJAX déclenché), et l'intégralité de la
suite « Partager un cheval » (logic/admin/runtime) repassée sans aucune régression. Deux bugs
délibérément introduits (repli d'affichage fuité dans le message, séparateur SMS cassé) : chacun
fait échouer exactement l'assertion dédiée, aucune autre.

## 0.40.0 — Correctif performance : requête Production (gwseq_get_horse_offspring) — anomalie ~35 s résolue

Mesure RÉELLE de l'itération 4 : 1 seule requête SQL, 35 144,1 ms, dans
`gwseq_add_cheval_pedigree_meta_boxes()`, générée par `gwseq_get_horse_offspring()` (production
d'un cheval, calculée à la volée). Cause confirmée : le `meta_query` combinait, en un seul OR, deux
groupes AND portant chacun sur DEUX clés meta différentes (`_gwseq_pere_mode`/`_gwseq_pere_id`,
`_gwseq_mere_mode`/`_gwseq_mere_id`).

**Analyse effectuée avant tout correctif** (conformément à la demande explicite) en lisant le code
source réel non modifié de `WP_Meta_Query` (wp-includes/class-wp-meta-query.php) : sa méthode
`find_compatible_table_alias()` ne peut JAMAIS fusionner deux clauses reliées par AND portant sur
des clés différentes (seules deux clauses partageant EXACTEMENT la même clé, ou reliées par OR
avec un opérateur positif, peuvent partager une jointure) — cette forme de requête générait donc
MÉCANIQUEMENT 4 `INNER JOIN` indépendants sur `wp_postmeta`, un problème de FORME de requête,
jamais un index manquant (`post_id` est déjà indexé nativement dans tout WordPress standard).
Reconstruction complète du JOIN/WHERE réellement généré, documentée dans le CR de cette version.

**Correctif appliqué, strictement applicatif** : `gwseq_get_horse_offspring()` exécute désormais
DEUX requêtes séparées et simples (une par rôle, chacune un AND sur exactement 2 clés -> 2 JOIN,
jamais combinées en un seul OR SQL à 4 JOIN), fusionnées et triées par titre en PHP
(`strcasecmp()`), avec un dédoublonnage défensif par identifiant. AUCUN changement de règle
métier : mêmes deux conditions par rôle (`mode = 'gws' ET id = $cheval_id` — le filtre de mode
reste indispensable, une ancienne valeur `_id` pouvant subsister après un changement de mode vers
"external", voir la conservation non destructive documentée dans le fichier ; le retirer
produirait de faux positifs), même `post_status`, même `post_type`, même contrat de sortie
(tableau de `WP_Post` trié par titre ascendant) pour les trois usages existants (nettoyage à la
suppression définitive d'un cheval, détection de présence pour la boîte Production, rendu de son
contenu) — aucun n'est modifié. AUCUN schéma SQL modifié, AUCUN index personnalisé ajouté, AUCUNE
donnée existante touchée — GWS reste installable sur un WordPress standard.

Le diagnostic instrumenté de performance (`includes/cheval-perf-diagnostic.php`) reste **activé**
en local/développement pour cette version, le temps de mesurer réellement l'effet du correctif sur
le site de recette avant de le retirer.

Tests étendus (`tests/gws-equestrian-pedigree-logic-test.php`) : produit retrouvé via le père,
produit retrouvé via la mère (déjà couverts, désormais exécutés contre la nouvelle implémentation),
plus deux nouveaux scénarios dédiés à ce qui pouvait réellement changer avec deux requêtes
séparées — ordre final identique malgré la fusion de deux résultats désormais distincts (un produit
trouvé via la mère, alphabétiquement antérieur, apparaît bien AVANT un produit trouvé via le père
dans le résultat final, preuve que le tri s'applique réellement et non un simple hasard d'ordre de
fusion), et absence de doublon même face à une donnée volontairement incohérente simulée (un cheval
enregistré comme père ET mère d'un même produit, situation empêchée par l'API métier depuis 0.9.0
mais simulée ici comme une éventuelle donnée antérieure). Vérifiés par retrait/restauration sur
trois bugs délibérément introduits : absence de dédoublonnage, absence de tri final, et
suppression du filtre de mode (réintroduction du risque de faux positif) — chacun fait échouer
exactement l'assertion dédiée, aucune autre. Intégralité de la suite (25 fichiers PHP + 4 suites JS
runtime) ré-exécutée après chaque restauration : aucune régression.

## 0.39.0 — Diagnostic instrumenté de performance, itération 4 (détail SQL par callback — cause principale localisée)

Mesure RÉELLE de l'itération 3, sur Jamerose : `add_meta_boxes_gwseq_cheval` = 36 065,4 ms, dont
36 065,0 ms attribués très exactement à UN SEUL callback, `gwseq_add_cheval_pedigree_meta_boxes()`
(cheval-pedigree.php:670) — le rendu ultérieur de la boîte Pedigree elle-même ne prenant que
49,7 ms. La cause est désormais localisée à un seul callback, dont le corps ne compte que trois
instructions : un premier `add_meta_box()` (jamais coûteux), un appel conditionnel à
`gwseq_get_horse_offspring($post->ID)` (un `get_posts()` avec `meta_query` portant sur l'ensemble
des fiches Cheval), puis un second `add_meta_box()` local/développement uniquement.

**Mesure plutôt que supposition** : PHP ne permet pas d'envelopper un simple appel de fonction
nommée comme on enveloppe un callback de hook (une entrée mutable de `$wp_filter`). Plutôt que de
deviner laquelle des trois instructions est en cause, cette itération réutilise un mécanisme NATIF
de WordPress — `SAVEQUERIES` (`$wpdb->queries`, le même mécanisme qu'utilisent des outils comme
Query Monitor) — pour journaliser CHAQUE requête SQL réellement exécutée, avec son texte et sa
durée réelle. Activé au chargement du plugin (uniquement si rien ne l'a déjà explicitement
désactivé ; le rapport l'indique explicitement si c'est le cas plutôt que de prétendre mesurer des
requêtes qu'il ne peut pas voir). `gwseq_perf_diag_wrap_hook_callbacks()` (inchangée dans son
principe depuis l'itération 2) relève désormais aussi, pour CHAQUE callback qu'elle enveloppe déjà
— pas seulement celui du pedigree, la même mesure généraliste s'applique automatiquement à tout
hook déjà profilé — le nombre de requêtes SQL exécutées pendant cet appel précis et leur temps
cumulé ; au-delà d'un seuil (callback mesuré à plus d'1 seconde), le texte et la durée des requêtes
les plus lentes de ce callback sont conservés (5 au maximum, jamais toutes) et affichés dans le
rapport. Répond directement à la demande de mesurer les requêtes `WP_Query`/`get_posts`, le
parcours de l'ensemble des chevaux, et toute opération exécutée plusieurs fois (un nombre de
requêtes élevé pour un seul callback trahirait une boucle ; une seule requête très longue
trahirait plutôt la requête elle-même ou l'absence d'index) — sans réadapter le mécanisme à ce cas
précis ni trancher à l'avance laquelle des trois instructions est en cause.

**Aucun correctif n'est livré dans cette version** — toujours la même demande explicite : mesurer
avant de corriger. Aucun changement de comportement métier. Tests étendus (nouvelle fonction pure
`gwseq_perf_diag_capture_queries_since()`, intégration avec l'enveloppement générique existant,
affichage du détail SQL dans le rapport), vérifiés par retrait/restauration sur deux bugs
délibérément introduits (attribution de requêtes d'AVANT l'appel, échantillon jamais gated par le
seuil) — chacun fait échouer exactement les assertions dédiées, aucune autre.

## 0.38.0 — Diagnostic instrumenté de performance, itération 3 (add_meta_boxes / add_meta_boxes_gwseq_cheval)

Mesures RÉELLES de l'itération 2, obtenues sur Jamerose : `current_screen` (18 ms), `load-post.php`
(~0 ms) et `admin_enqueue_scripts` (11,9 ms) sont tous rapides — le callback le plus lent mesuré
sur l'ensemble de ces hooks, `wp_enqueue_command_palette_assets`, ne fait que 10,3 ms. Les ~36
secondes se situent donc intégralement entre la FIN de `load-post.php` et le DÉBUT de
`admin_enqueue_scripts`, dans une portion de code qui ne passait par aucun des hooks déjà
instrumentés jusque-là.

**Audit du code source réel de WordPress** (`wp-admin/post.php`, `wp-admin/edit-form-advanced.php`
et `wp-admin/includes/meta-boxes.php`, lus ligne à ligne plutôt que supposés, conformément à la
demande explicite de ne pas ajouter de hooks arbitraires) : pour un type de contenu en éditeur
classique — Cheval ne déclare pas `'editor'` dans `supports`, donc `use_block_editor_for_post()`
est faux et `edit-form-advanced.php` est bien le fichier chargé — `wp-admin/post.php` inclut
directement ce fichier, qui appelle `register_and_do_post_meta_boxes($post)`. Cette fonction
déclenche `do_action('add_meta_boxes', $post_type, $post)` PUIS `do_action("add_meta_boxes_
{$post_type}", $post)` — où les 9 callbacks GWS existants (répartis dans cheval-fields.php,
cheval-pedigree.php, cheval-media.php, cheval-indices.php, cheval-labels.php, cheval-editorial.php,
cheval-share-admin.php ×2 et admin-ui.php) ENREGISTRENT leurs boîtes — et ce n'est QU'ENSUITE, plus
bas dans `edit-form-advanced.php`, qu'`admin-header.php` est chargé (ce qui déclenche
`admin_enqueue_scripts`). Autrement dit : la REGISTRATION des boîtes méta (l'exécution des 9
callbacks eux-mêmes) n'avait encore jamais été chronométrée — l'itération 1 ne mesurait que leur
RENDU, bien plus tard dans la requête, une fois `admin_enqueue_scripts` déjà passé.

**Extension du profileur générique par callback** (même technique que l'itération 2, aucune
nouveauté) : `add_meta_boxes` et `add_meta_boxes_gwseq_cheval` ajoutés à la liste des hooks
profilés, avec de nouveaux repères de temps encadrant précisément ces deux hooks. Permettra de
déterminer si le temps perdu est dans l'un des 9 callbacks de registration GWS (et lequel), dans un
callback `add_meta_boxes` générique d'un plugin tiers sans rapport avec GWS (candidat plausible
pour expliquer l'indépendance déjà observée au contenu de la fiche), ou encore dans le reste de
`register_and_do_post_meta_boxes()`/`edit-form-advanced.php` non couvert par un hook nommé.

**Aucun correctif n'est livré dans cette version** — toujours la même demande explicite. Aucun
changement de comportement métier. Tests étendus : les deux nouveaux hooks sont vérifiés comme
enregistrés en environnement local, comme faisant partie de `GWSEQ_PERF_DIAG_TARGET_HOOKS`, et
comme enveloppés par `gwseq_perf_diag_install_hook_profilers()` au même titre que les hooks déjà
couverts ; vérifié par retrait/restauration (retirer les deux hooks de la liste cible fait échouer
exactement l'assertion dédiée à leur présence, aucune autre).

## 0.37.0 — Diagnostic instrumenté de performance, itération 2 (localisation précise dans la fenêtre current_screen → admin_enqueue_scripts)

Mesures RÉELLES obtenues sur le site de recette (Local) avec l'outil de la version 0.36.0, sur
deux fiches Cheval (Jamerose, très remplie, et une fiche quasi vide) : temps total ~36,6-36,9 s
dans les deux cas, somme des boîtes méta ~95-110 ms dans les deux cas, et l'écart entre les repères
`current_screen` et `admin_enqueue_scripts` mesure à lui seul ~36,1-36,4 s — soit la quasi-totalité
du temps perdu. Conclusion mesurée (pas une hypothèse) : la lenteur est INDÉPENDANTE du contenu de
la fiche, n'est PAS causée par les boîtes méta GWS, et se situe précisément dans cette fenêtre du
cycle de chargement WordPress. Sur demande explicite, cette version poursuit l'instrumentation
UNIQUEMENT dans cette zone, sans toucher au moindre comportement métier ni proposer de correctif.

**Nouveau profileur générique par callback**, installé sur les hooks natifs qui s'exécutent
exactement dans cette fenêtre — `current_screen`, `admin_init`, `load-post.php`/
`load-post-new.php` (l'un ou l'autre selon création/édition), `admin_enqueue_scripts` — quelle que
soit leur PROVENANCE (GWS, thème `gws-starter`, ou n'importe quel plugin tiers déjà installé sur ce
site précis, invisible depuis ce dépôt de code). Technique : un premier `add_action` à priorité
`PHP_INT_MIN` sur `current_screen` (le premier des cinq hooks à se déclencher) substitue EN PLACE,
dans le registre natif `$wp_filter`, chaque callback déjà enregistré sur les cinq hooks cibles par
un intermédiaire chronométré qui appelle l'ORIGINAL avec exactement les mêmes arguments et renvoie
exactement sa valeur — même garantie de non-altération du comportement que l'enveloppement des
boîtes méta de la version 0.36.0, jamais une réimplémentation. La PROVENANCE de chaque callback
(fichier où il est réellement défini) est résolue par réflexion (`ReflectionFunction`/
`ReflectionMethod`) et classée automatiquement (`plugin:<slug>`, `theme:<slug>`, `mu-plugin`,
`wordpress-core`, ou son chemin brut en dernier recours) — sans connaître à l'avance quels plugins
tiers sont installés sur le site du cabinet. Limite documentée : un callback enregistré
dynamiquement DEPUIS L'INTÉRIEUR d'un callback `current_screen` (donc après le passage du
profileur) échappe à cette mesure ; quand la somme des callbacks mesurés n'explique pas tout
l'écart entre deux repères de temps, le rapport l'indique explicitement comme "non expliqué"
plutôt que de laisser croire à une mesure complète.

**Rapport enrichi** : chaque étape "…:fin" du cycle de chargement affiche désormais, en plus du
délai total déjà présent en 0.36.0, la part de ce délai expliquée par les callbacks natifs mesurés
sur le hook correspondant et la part "non expliquée" restante ; une table classée (callback →
source → durée, du plus lent au plus rapide) liste individuellement chaque callback natif mesuré
sur les cinq hooks cibles.

**Aucun correctif n'est livré dans cette version** — même demande explicite qu'en 0.36.0 : cette
itération identifie, elle ne corrige pas. Aucun changement de comportement métier, aucune
migration, aucune modification du modèle Cheval/diffusion/Sélections. Tests dédiés étendus pour
couvrir la résolution de provenance par réflexion (plugin/thème/mu-plugin/cœur WordPress/repli),
la propriété critique de non-altération de l'enveloppement générique par callback (arguments,
valeur de retour, et propagation d'une exception éventuelle strictement identiques à l'original,
vérifié par inversion délibérée du correctif puis restauration), la portée de l'installation des
profileurs (jamais hors de l'écran d'édition Cheval, jamais un hook non listé), et les nouvelles
sections du rapport — un bug réel a d'ailleurs été détecté et corrigé pendant l'écriture de ces
tests : l'annotation "non expliqué" par étape se rattachait par erreur à l'étape SUIVANT celle
réellement terminée plutôt qu'à elle-même, corrigé avant toute livraison.

## 0.36.0 — Diagnostic instrumenté de performance (anomalie de recette : ~38 s à l'ouverture d'une fiche Cheval)

Recette du Lot 2B validée intégralement (voir CR de recette) — le modèle Sélection est confirmé
sans mécanisme supplémentaire de révocation/régénération. Une anomalie de performance INDÉPENDANTE
des Sélections a été signalée pendant cette même recette : l'ouverture d'une fiche Cheval en édition
prend environ 38 secondes, sur un site n'en comptant qu'une grosse dizaine. Aucun correctif n'est
livré dans cette version — sur demande explicite ("ne pas commencer par modifier ou refactorer le
code sur la base d'une hypothèse"), cette version livre UNIQUEMENT l'instrumentation nécessaire à un
diagnostic réel, avant tout correctif.

**Audit statique préalable (sans instrumentation, sur le code source du module)** : aucun appel
HTTP sortant (`wp_remote_*`, `curl_exec`, oEmbed) nulle part dans `gws-core` ni le thème
`gws-starter` ; aucun `sleep()`/`usleep()` ; aucune régénération synchrone d'image
(`wp_generate_attachment_metadata()` absent) ; les quatre seules requêtes `posts_per_page => -1` du
module sont chacune une SEULE requête `WP_Query`/`meta_query`, correctement scopées à leur propre
écran (jamais déclenchées sur l'écran d'édition Cheval) ; la résolution du pedigree est bornée par
construction (au plus 31 fiches à profondeur 3, mémoïsée) et protégée contre les cycles — aucune de
ces pistes n'explique, à elle seule, un ordre de grandeur de 38 secondes sur une grosse dizaine de
chevaux. Cet audit ne remplace pas une mesure réelle : aucun environnement WordPress+MySQL n'étant
disponible pour la reproduire ailleurs, seule une instrumentation exécutée sur le site réel de
recette (Local) peut confirmer la cause exacte.

**Nouveau fichier `includes/cheval-perf-diagnostic.php`** (local/développement uniquement, même
garde que le module QA de gws-core — entièrement inerte en production, aucun coût, aucune sortie) :
chronomètre CHAQUE boîte méta de la fiche Cheval individuellement (enveloppement non invasif du
callback déjà enregistré dans `$wp_meta_boxes` — mêmes arguments, même sortie, même valeur de
retour, seule une mesure est ajoutée autour de l'appel existant), les étapes du cycle de chargement
WordPress (`plugins_loaded`/`init`/`admin_init`/`current_screen`/`admin_enqueue_scripts`/
`admin_footer`), et compare ce total au temps RÉEL écoulé depuis le tout début de la requête HTTP
(`$_SERVER['REQUEST_TIME_FLOAT']`) — pour déterminer si le temps perdu se trouve dans ce que GWS
peut voir, ou ailleurs (cœur WordPress, un autre plugin, le thème, l'environnement Local
lui-même). Rapport affiché en pied de l'écran d'édition et, si `WP_DEBUG_LOG` est actif, journalisé.
Portée strictement limitée à l'écran d'édition d'une fiche Cheval précise (jamais la liste, jamais
un autre CPT, jamais le front) — aucun effet sur le comportement métier déjà validé (Cheval,
diffusion, Sélections).

**Aucun changement de comportement métier dans cette version** : aucune migration, aucune
réécriture de données, aucun changement du modèle Cheval/diffusion/Sélections. Tests dédiés
vérifiant explicitement cette propriété (le callback enveloppé produit une sortie et une valeur de
retour STRICTEMENT identiques à l'original) et le gating local/développement, sur le même modèle que
`qa-toggle-logic-test.php`.

## 0.35.0 — Correctif URL de suppression + Lot 2B (Sélection : modification + page destinataire)

Recette du Lot 2A : la création fonctionne et conserve bien les chevaux sélectionnés. Trois
constats ont amené un correctif immédiat et un ajustement de modèle avant d'engager le Lot 2B.

**Correctif bloquant — URL de révocation cassée.** Cliquer sur "Révoquer" aboutissait à "Le lien
suivi est expiré". Cause racine : `wp_nonce_url()` (WordPress core) échappe son résultat en HTML
par conception (prévu pour un attribut `href="..."` imprimé côté serveur, où le navigateur décode
nativement l'entité au moment de PARSER le document) — mais cette URL transitait en JSON
(`wp_localize_script()`) puis était assignée directement à la propriété JS `.href`, un contexte qui
n'effectue jamais ce décodage : l'entité `&amp;`/`&#038;` restait littéralement dans l'URL
réellement soumise par le navigateur, cassant le nonce. Corrigé en construisant le nonce
manuellement (`add_query_arg('_wpnonce', wp_create_nonce(...), $url)`), sans jamais passer par
`wp_nonce_url()` — aucune protection retirée (capacité, ID de sélection, nonce spécifique, type de
post). Nouveau test de régression qui contrôle l'URL RÉELLEMENT exploitable par le navigateur
(absence d'entité HTML, `parse_url()`/`parse_str()`), pas seulement une sous-chaîne HTML isolée.

**Ajustement de modèle — abandon de "Révoquer".** Pour une sélection, la révocation sans
suppression n'apportait pas de valeur métier et introduisait un état confus. Nouveau
fonctionnement : une sélection existante EST active, avec un lien stable tant qu'elle existe.
Mettre fin à une sélection se fait en la SUPPRIMANT (`gwseq_selection_delete()`, `wp_trash_post()`
— stratégie WordPress native, jamais une perte immédiate irréversible), ce qui rend son
`/selection/{token}/` immédiatement inaccessible sans jamais toucher au moindre cheval référencé.
"Révoquer"/"Régénérer"/"Activer" disparaissent entièrement de l'interface ; le token reste un
mécanisme technique interne (`gwseq_selection_activate()`/`_revoke()` toujours utilisés en
coulisses, par ex. à la création).

**Lot 2B — Modification d'une sélection existante.** Le titre d'une sélection dans la liste
l'ouvre désormais pour modification (`gwseq_selection_update()`) : ajouter/retirer un cheval,
réordonner, changer le titre, enregistrer — sans JAMAIS régénérer le token (le lien déjà envoyé
reste strictement identique). Un cheval déjà présent devenu "En préparation" reste affiché
(signalé non diffusable) tant qu'il n'est pas explicitement retiré, jamais disparu silencieusement
(même principe qu'à la création : un cheval réellement NOUVEAU doit, lui, être éligible).

**Lot 2B — Page destinataire `/selection/{token}/`.** Nouvelle route publique (nouveau fichier
`includes/cheval-selection-front.php`, même architecture que `/partage/{token}` pour Cheval) :
accessible sans compte, `noindex` systématique, aucune mise en cache. Affiche le titre et une
carte par cheval RÉELLEMENT présentable (nom, identité, accroche, prix si le statut commercial
l'autorise, lien de fiche public ou privé selon le cas), en réutilisant EXCLUSIVEMENT les fonctions
déjà existantes (`gwseq_horse_share_identite_label()`, `gwseq_horse_share_prix_label()`,
`gwseq_horse_share_fiche_url()`) — jamais un second calcul. Un cheval "En préparation" disparaît
simplement du rendu sans jamais modifier la liste stockée ; si plus aucun cheval n'est présentable,
un état vide propre s'affiche (jamais une erreur technique). Rendu minimal et réutilisable — pas de
gabarit graphique figé, `wp_head()`/`wp_footer()` appelés normalement pour que le thème puisse un
jour cibler ces mêmes classes stables.

Toujours volontairement absent : message de partage/Open Graph/WhatsApp-SMS-Copier/PDF/QR code/
catalogue/mobile/refonte graphique générale du BO.

## 0.34.0 — Suite V1 « Partager & vendre », Lot 2A : modèle et persistance de la Sélection de chevaux

Première étape du Lot 2 (sélection multi-chevaux), développée par petits lots avec recette réelle
entre chaque étape (méthode explicitement demandée). Ce lot 2A couvre STRICTEMENT : le modèle
métier, la persistance, la création d'une sélection, et la gestion de son token. Volontairement
ABSENTS de ce lot (développements ultérieurs, non engagés) : le rendu public de la page
destinataire, le partage WhatsApp/SMS/Copier, l'Open Graph, la préparation mobile concrète, l'export
PDF, le catalogue, et la MODIFICATION d'une sélection existante (ajout/retrait/réordonnancement/
titre après création).

**Persistance — choix d'architecture.** Nouveau CPT interne/non public `gwseq_selection`
(`GWSEQ_CPT_SELECTION`, includes/post-types.php), sur le modèle déjà en place pour "Groupe
tarifaire" (`public => false`, aucune archive, aucun rewrite natif, absente de la recherche/REST).
Aucune nouvelle table : la liste ORDONNÉE de chevaux d'une sélection tient dans une seule meta
postmeta (`_gwseq_selection_chevaux`, tableau PHP d'IDs — l'ordre du tableau EST l'ordre de
présentation), le titre facultatif est `post_title` natif, la date de création `post_date` natif,
l'identifiant technique l'ID du post. Le token de partage (`_gwseq_selection_token`) suit
exactement la même architecture que le partage privé Cheval (32 octets aléatoires
cryptographiquement sûrs, présence de la meta = partage actif) — même niveau de sécurité demandé.
`includes/cheval-selection.php` porte toute cette couche métier, indépendante de wp-admin
(préparation explicite d'un futur écran mobile) ; `includes/cheval-selection-admin.php` porte la
glue wp-admin (écran `Chevaux → Sélections`, AJAX, actions de gestion du token).

**Règle de diffusion (cœur du lot).** Un cheval "En préparation" n'entre jamais dans une sélection
(exclu à la fois de la recherche de l'écran de création ET par une revérification serveur au
moment de la création elle-même) — réutilise EXCLUSIVEMENT `gwseq_horse_diffusion_state()`
(includes/cheval-share.php), jamais un recalcul depuis `post_status`. Une sélection ne stocke QUE
des identifiants de chevaux, jamais une copie de leurs données : toute résolution pour affichage
(`gwseq_selection_resolve_cheval()`) relit l'état ACTUEL du cheval, y compris son lien de fiche
(public si "Visible sur le site", privé si "Diffusion privée", réutilise
`gwseq_horse_share_fiche_url()`). Si un cheval déjà inclus repasse "En préparation" après coup, il
devient simplement non présentable au calcul suivant — la liste stockée n'est JAMAIS modifiée, la
sélection n'est jamais "cassée".

**Token.** Générer/activer/régénérer/révoquer suivent exactement les mêmes règles que le partage
privé Cheval. Révocation NON DESTRUCTIVE (demande explicite) : seule la meta token disparaît, le
post et la liste de chevaux restent intacts — l'utilisateur peut régénérer un token à tout moment
pour redonner un accès. Recherche inverse token -> sélection strictement limitée au statut
`publish` (aucun autre statut n'est aujourd'hui produit par ce module, défense en profondeur).

**Écran `Chevaux → Sélections`.** Réutilise EXACTEMENT le moteur de recherche/filtrage de l'écran
`Chevaux → Partager` (recherche texte, état de diffusion, sexe, statut commercial, année,
catégorie, tous cumulables), avec l'exclusion supplémentaire des chevaux "En préparation" (jamais
proposé dans les résultats ni dans les options du filtre "État de diffusion" sur cet écran).
Sélection multiple par case à cocher, ordre explicite via boutons Monter/Descendre (accessible,
fonctionnera aussi bien sur smartphone — jamais un drag & drop comme seule solution), compteur
"N chevaux sélectionnés", titre facultatif, aucune limite artificielle de nombre de chevaux (une
seule meta simple, aucun coût de jointure jusqu'à plusieurs centaines d'entrées). La liste des
sélections déjà créées affiche titre/date/nombre de chevaux actuellement diffusables/lien
(copiable)/actions Régénérer ou Révoquer — restreinte aux propres sélections de l'utilisateur sans
`edit_others_posts`, même règle que l'écran « Partager ». Pas de bouton "Modifier"/"Ouvrir" dans ce
lot (Lot 2B).

**Sécurité.** Chaque ID de cheval soumis à la création est revérifié SERVEUR (appartenance au CPT
Cheval, capacité `edit_post` sur ce cheval précis, éligibilité de diffusion) — jamais une confiance
dans ce que le client affirme avoir sélectionné ; tout ID invalide est simplement écarté, jamais
une erreur bloquante. Gestion du token via liens `admin-post.php` nonce-protégés (jamais un
formulaire imbriqué), mêmes mécanismes que le partage privé Cheval. Restriction "mes propres
sélections" pour un auteur sans `edit_others_posts`.

**Tests.** Couverture complète du modèle (token/régénération/révocation non destructive/recherche
inverse stricte, sanitation et dédoublonnage des IDs, éligibilité, résolution pour affichage y
compris cheval supprimé/en corbeille/changement de diffusion ultérieur, création à 1/plusieurs
chevaux/sans limite haute, données malformées, sélection sans plus aucun cheval diffusable) et de
la glue wp-admin (menu, exclusion "En préparation" à tous les niveaux, AJAX de recherche/création
avec sa sanitation serveur, permissions de gestion du token, restriction auteur, chargement
conditionnel des assets). Nouveau test d'exécution réelle du script de l'écran (recherche, case à
cocher, ordre, compteur, création, échec de création, confirmation avant régénérer/révoquer). Trois
correctifs vérifiés par retrait/restauration (isolation confirmée). Deux tests de non-régression
préexistants mis à jour (le nombre total de post types métier GWS passe de quatre à cinq).

## 0.33.0 — Correctifs de clôture Lot 1 : libellé du filtre + import IFCE (Naisseur)

Deux correctifs demandés en recette après validation du correctif indices sportifs (0.32.0),
aucune nouvelle fonctionnalité, aucun changement de logique de diffusion.

**1. Libellé du filtre "État de diffusion"** — L'option par défaut du filtre affichait "Tous",
ambigu à côté des autres filtres de la même barre (sexe, catégorie) qui précisent déjà leur objet
("Toutes les catégories"). Remplacé par "Tous les états de diffusion" sur les deux écrans où ce
filtre existe (Chevaux > Tous les chevaux, Chevaux > Partager). Changement de libellé uniquement
(`cheval-fields.php`, `cheval-share-admin.php`, `cheval-share-admin.js`) : aucune valeur d'option,
aucun nom de paramètre, aucune logique de filtrage modifiée.

**2. Import IFCE — Naisseur non renseigné (cas L'Aganix)** — Après réimport réel de la fiche
L'Aganix D'Aubigny pour valider le correctif indices sportifs, le champ Naisseur (libellé
"Éleveur" jusqu'à cette version) restait vide alors que le document IFCE le renseigne bien.

- *Cause racine* : le document IFCE de L'Aganix utilise le libellé "Naisseur principal :" (SIRE
  déclare plusieurs naisseurs pour ce cheval) alors que l'extraction ne reconnaissait que
  "Naisseur :" ou "Éleveur :" suivis immédiatement du deux-points — la présence du qualificatif
  "principal" entre le mot-clé et le deux-points faisait échouer la correspondance.
- *Règle d'extraction* : audit des 7 fixtures réelles du dépôt — 5 utilisent "Naisseur :", 1
  (L'Aganix) utilise "Naisseur principal :" — les deux formes sont désormais reconnues par une
  seule règle générale (`(?:Naisseur(?:\s+principal)?|[EÉeé]leveur)\s*:\s*(.+)`, insensible à la
  casse), sans aucune règle spécifique à un cheval. L'extraction est également restreinte à la
  "zone sujet" du document (avant l'en-tête Pedigree/Généalogie/Origines, même délimitation que le
  correctif indices sportifs de la 0.32.0) : un naisseur mentionné plus loin dans le document,
  pour un ascendant, n'est jamais retenu comme celui du cheval sujet.
- *Bug additionnel détecté pendant l'audit des fixtures* : la fiche Quaprice Bois Margot fait
  usage du droit d'opposition SIRE — le document affiche la mention légale "s'oppose à la
  diffusion des informations le concernant" à la place d'un nom de naisseur. Cette mention était
  jusqu'ici importée telle quelle comme si elle était un nom de naisseur réel. Ajout d'une
  détection dédiée (`gwseq_ifce_is_naisseur_privacy_opt_out()`) : quand ce cas est identifié, le
  champ reste vide plutôt que d'importer une donnée invalide.
- *Décision Éleveur/Naisseur* : audit de l'usage réel du champ (meta `_gwseq_eleveur`, clé interne
  `eleveur`) : alimenté uniquement par la saisie manuelle et par l'import IFCE, qui y écrit
  précisément la donnée "Naisseur" du document officiel — jamais une notion distincte de qui a
  débourré/entraîné le cheval. La donnée stockée correspond donc bien au Naisseur au sens IFCE.
  Décision : changement du libellé visible "Éleveur" → "Naisseur" dans le formulaire d'identité,
  sans aucun renommage de meta, de clé interne, ni migration de données (changement non
  destructif, conforme à la donnée déjà stockée).
- *Tests* : L'Aganix (naisseur extrait correctement + non-régression indices/pedigree), 4 autres
  fixtures réelles à "Naisseur :" simple (non-régression), Quaprice (opposition SIRE exclue), tests
  unitaires de la détection d'opposition, cas synthétique sans naisseur (champ vide, aucune
  invention), cas synthétique avec naisseur uniquement après l'en-tête Pedigree (jamais retenu
  pour le sujet). Voir `tests/gws-equestrian-ifce-import-test.php` et
  `tests/gws-equestrian-cheval-logic-test.php`.

## 0.32.0 — Filtre "État de diffusion" (Lot 1) + correctif import IFCE (indices sportifs)

Deux correctifs indépendants livrés ensemble.

**1. Filtre métier "État de diffusion" sur les listes Chevaux concernées (Lot 1).** Ajouté aux
deux écrans qui listent des chevaux : `Chevaux → Tous les chevaux` (nouveau sélecteur "Tous/En
préparation/Diffusion privée/Visible sur le site" dans les filtres déjà existants,
`gwseq_render_cheval_admin_list_filters()`/`gwseq_apply_cheval_admin_list_filters()`, includes/
cheval-fields.php) et `Chevaux → Partager` (même sélecteur, réutilisant la logique de recherche/
filtres déjà existante — `gwseq_sanitize_horse_share_filters()`/`gwseq_horse_share_filters_to_
query_args()`, includes/cheval-share-admin.php). Réutilise EXCLUSIVEMENT `gwseq_horse_diffusion_
state()` comme source de vérité (nouvelle fonction `gwseq_cheval_ids_by_diffusion_state()`,
includes/cheval-share.php) — cet état étant dérivé (statut WordPress + présence d'un token), jamais
exprimable par un `meta_query`/`tax_query` direct, le filtre restreint la requête via `post__in`,
cumulable avec les autres filtres (Catégorie/Statut/Sexe/Année/recherche texte). Aucun nouveau
champ/meta de statut créé. L'affichage des suffixes `— En préparation`/`— Diffusion privée` dans la
liste (introduit au 0.30.0) reste inchangé — toujours pas de suffixe `— Visible sur le site` pour ne
pas surcharger visuellement la liste.

**2. Correctif — Import IFCE : indices sportifs du cheval sujet.** Bug réel de recette constaté sur
L'AGANIX D'AUBIGNY, importé à tort avec "ISO 154 — CD 0,93 — 2024" alors qu'il n'a AUCUN ISO — la
valeur appartenait en réalité à AMBASSADOR Z, un ascendant présent plus loin dans le pedigree/la
production de son document. **Cause racine** : `gwseq_ifce_parse_indices_from_text()`
(includes/ifce-import-parser.php) recevait le texte ENTIER du document, sans aucune frontière — sur
une vraie fiche IFCE, la section Pedigree qui suit la zone de synthèse du cheval sujet détaille la
production de chaque ascendant, avec LEURS PROPRES indices ISO/ICC/IDR/BSO/BCC/BDR ; "première
occurrence dans le texte" ne garantissait donc jamais "première occurrence dans la zone du cheval
sujet" dès que celui-ci n'avait lui-même aucun indice sportif. Audit confirmé sur 5 des 6 fixtures
réelles déjà présentes dans `tests/fixtures/` : toutes portaient au moins un indice erroné avant ce
correctif. **Règle de délimitation retenue** : les indices ne sont plus jamais recherchés que dans
la zone STRICTEMENT AVANT la ligne d'en-tête du pedigree (nouvelle fonction `gwseq_ifce_find_
pedigree_heading_index()`, réutilisée à l'identique par `gwseq_ifce_parse_pedigree_from_lines()` —
une seule frontière, jamais deux détections divergentes) ; en l'absence de cette ligne, la totalité
du texte reste la zone de recherche (repli explicite). Appliqué structurellement aux SIX indices
(ISO/ICC/IDR sportifs ET BSO/BCC/BDR génétiques), pas seulement à l'ISO du bug signalé. L'absence
d'un indice reste une donnée valide (aucun fallback sur un indice trouvé ailleurs). Vérifié
explicitement que le BSO +16 (0,51) de L'Aganix continue d'être extrait correctement. Fixture
réelle ajoutée : `tests/fixtures/ifce-aganix-d-aubigny.pdf`. Aucune migration automatique : le
cheval L'Aganix déjà importé, le cas échéant, devra être corrigé manuellement/réimporté en recette.

## 0.31.0 — Lot 1 « Partager & vendre » : piloter la diffusion avec le vocabulaire GWS

Recette du 0.30.0 concluante (sauvegarde implicite, état "Diffusion privée" bien dérivé), mais
l'écran d'édition continuait d'afficher EN PARALLÈLE les contrôles natifs WordPress (boîte
"Publier" : `Brouillon`/`Publier`/`État`/`Visibilité` Publique-Protégée par mot de passe-Privée) —
deux modèles contradictoires pour la même donnée. La diffusion d'une fiche Cheval est désormais
pilotée avec le SEUL vocabulaire métier GWS, `post_status`/`post_password` + token restant la seule
source technique sous-jacente (aucun statut personnalisé créé).

- **Boîte "État de diffusion"** (`includes/cheval-share-admin.php`) : remplace la boîte native
  "Publier", UNIQUEMENT pour le CPT Cheval (`remove_meta_box('submitdiv', GWSEQ_CPT_CHEVAL, 'side')`
  — mécanisme natif scopé, aucun impact sur Pages/Actualités/Prestations/Membres). Affiche "État de
  diffusion : {En préparation|Diffusion privée|Visible sur le site}" puis uniquement les actions
  pertinentes : *En préparation* → Enregistrer / Activer la diffusion privée / Rendre visible sur le
  site (si capacité `publish_post`) ; *Diffusion privée* → Enregistrer les modifications / Rendre
  visible sur le site / Repasser en préparation ; *Visible sur le site* → Enregistrer les
  modifications / "Retirer la fiche du site" avec DEUX choix explicites (Repasser en préparation OU
  Activer la diffusion privée — jamais un "Dépublier" ambigu). Chaque bouton de transition est un
  VRAI `<button type="submit">` du formulaire d'édition existant : la fiche est donc toujours
  réellement enregistrée AVANT la transition, en un seul geste (jamais "Enregistrer le brouillon"
  PUIS changer la diffusion en deux opérations séparées), via le hook natif `save_post_{cpt}`
  (aucune duplication de la logique de sauvegarde).
- **Transitions métier centralisées** (`includes/cheval-share.php`) : trois nouvelles fonctions
  pures, indépendantes de wp-admin — `gwseq_horse_diffusion_set_en_preparation()` (statut `draft`,
  token révoqué), `gwseq_horse_diffusion_set_diffusion_privee()` (statut `draft`, réutilise un token
  déjà actif ou en crée un), `gwseq_horse_diffusion_set_visible_site()` (statut `publish`, mot de
  passe résiduel systématiquement levé). Un futur écran mobile GWS appellera exactement ces mêmes
  fonctions, sans jamais manipuler `post_status`/`post_password`/le token directement.
- **Sécurité** : `edit_post` pour toute transition (défense en profondeur), et `publish_post`
  spécifiquement avant de rendre une fiche visible sur le site — vérifié à la fois côté affichage
  (bouton absent) et côté hook de sauvegarde (refus silencieux si la capacité manque). Les règles du
  token sont conservées à l'identique (privé → public : ancien token peut rester valide ; public →
  non public : token existant conservé ; révocation : token supprimé).
- **Jamais le statut natif `private` de WordPress ni la protection par mot de passe comme
  implémentation de "Diffusion privée"** : elle reste `draft` + token GWS, exactement comme depuis
  le Lot 1 — les deux notions "privé" restent homonymes mais distinctes, la confidentialité
  commerciale étant assurée par GWS (token, route dédiée), jamais par un mécanisme de visibilité
  WordPress natif. Une fiche déjà en `private` natif ou protégée par mot de passe reste classée sans
  risque selon son état métier réel (`gwseq_horse_diffusion_state()` ne regarde jamais ces deux
  éléments séparément).
- **Audit non destructif** (`includes/cheval-fields.php`) : nouvelle notice sur la liste
  `Chevaux → Tous les chevaux`, signalant les fiches existantes utilisant encore un statut `private`
  natif ou une protection par mot de passe (`gwseq_cheval_native_visibility_mismatches()`, fonction
  pure, aucune écriture) — jamais de migration automatique ni silencieuse, seulement un signalement
  avec lien direct vers chaque fiche concernée pour une correction manuelle.
- **Boîte "Partage"** : le bouton "Créer un lien de partage privé" est retiré de cette boîte
  (centralisé dans "État de diffusion" — un seul point d'entrée par transition, jamais deux boutons
  différents pour la même opération) ; "Régénérer"/"Révoquer" un partage déjà actif y restent
  inchangés, ainsi que l'affichage de l'URL et de l'état "Visible sur le site" avec un ancien token.

## 0.30.0 — Lot 1 « Partager & vendre » : ajustement UX/métier, statut de diffusion et sauvegarde

Recette du correctif 0.29.0 : le cheval non public avec token est bien accessible via son lien
privé, mais cette recette révèle deux problèmes produit distincts, traités ici avant de clôturer
le Lot 1.

**1. Vocabulaire métier, pas WordPress.** Le professionnel n'a pas à connaître `Brouillon/Publié`
pour comprendre où en est une fiche. Trois états métier, dérivés des DEUX mêmes mécanismes natifs
déjà en place (`post_status`/`post_password` via `gwseq_horse_is_publicly_viewable()`, le token via
`gwseq_horse_private_share_is_active()`) — aucun statut personnalisé créé (pas de nécessité
démontrée) : **En préparation** (non public, aucun token), **Diffusion privée** (non public, token
actif), **Visible sur le site** (fiche publique — un ancien token qui traînerait ne change jamais
cet état, cohérent avec l'ajustement d'architecture 0.29.0). Nouvelle fonction centrale unique,
`gwseq_horse_diffusion_state()` (+ `gwseq_horse_diffusion_state_label()`), includes/cheval-share.php
— tout consommateur (boîte BO, liste d'administration, un jour un écran mobile) l'appelle telle
quelle, jamais un second calcul.

**2. Risque de données non sauvegardées.** Les boutons "Créer un lien de partage privé"/
"Régénérer" naviguaient directement vers `admin-post.php` (lien `<a>`, simple GET), hors du grand
formulaire d'édition WordPress : toute modification de la fiche saisie mais pas encore enregistrée
était donc PERDUE au moment du clic, sans que l'utilisateur en soit informé — il pouvait croire à
tort que "sa fiche vient d'être mise en diffusion privée" alors que ses dernières modifications
n'avaient jamais atteint la base. Correctif retenu (sauvegarder correctement, plutôt que bloquer
l'action) : ces deux boutons sont désormais de VRAIS `<button type="submit">` du même formulaire
d'édition (jamais une simulation de clic sur "Enregistrer le brouillon", jamais une duplication de
la logique de sauvegarde) — cliquer dessus soumet réellement la fiche vers `post.php`, déclenchant
nativement `save_post_{cpt}` (les mêmes hooks qu'un enregistrement normal) ; une fois la fiche
réellement enregistrée par ce mécanisme natif, `gwseq_horse_private_share_maybe_activate_on_save()`
(includes/cheval-share-admin.php, greffée sur ce même hook) active/régénère le partage privé.
"Révoquer" reste un simple lien `admin-post.php` : révoquer un accès ne prétend jamais refléter des
données à jour, aucun risque de fausse impression pour cette action précise.

**3. Interface.** La boîte "Partage" affiche désormais explicitement le "Statut de diffusion :"
avec son libellé métier, et les boutons "Créer"/"Régénérer" ci-dessus. Pas de refonte du BO (le
futur Lot 3 mobile exploitera cette même logique).

**4. Liste Chevaux.** Le suffixe natif "— Brouillon" à côté du nom, dans `Chevaux → Tous les
chevaux`, ne distinguait pas une fiche simplement inachevée d'une fiche volontairement en Diffusion
privée. Nouveau filtre `display_post_states` scopé au seul CPT Cheval (`gwseq_cheval_admin_list_
post_states()`, includes/cheval-fields.php, jamais un changement pour les autres contenus
WordPress) : remplace intégralement l'état affiché par le seul état métier centralisé — "Visible
sur le site" n'affiche rien, exactement comme WordPress n'affiche déjà rien à côté d'un contenu
publié.

## 0.29.0 — Lot 1 « Partager & vendre » : ajustement d'architecture, visibilité publique vs lien privé

Recette du correctif 0.28.0 : la priorité "privé > public" retenue pour `gwseq_horse_share_fiche_
info()` s'est révélée trop risquée — créer un lien privé sur un cheval déjà publié rendait
IMMÉDIATEMENT son permalink public inaccessible en 404, un utilisateur pouvait donc casser
involontairement une page visible du site en cliquant simplement sur "Créer un lien de partage
privé". Correctif d'architecture, pas un correctif ponctuel : la visibilité publique et
l'existence d'un lien privé sont désormais DÉCOUPLÉES.

**Nouvelle règle (`gwseq_horse_share_fiche_info()`, `includes/cheval-share.php`)** : PUBLIC
d'abord si le cheval est réellement visible (un token existant, même actif, ne masque plus jamais
une fiche publique valide), sinon PRIVÉ si un token est actif, sinon aucun lien. Nouveau prédicat
`gwseq_horse_is_private_share_only($cheval_id)` (= non publiquement visible ET token actif) :
seul ce mode précis justifie désormais un traitement "privé" (route dédiée, noindex) — remplace
l'ancienne logique "un token = toujours privé" partout où elle était utilisée, y compris dans
`gwseq_render_horse_og_meta()` (un cheval redevenu public, visité via son ancien lien
`/partage/{token}`, affiche désormais l'Open Graph PUBLIC — og:url public, jamais de noindex).

**Suppression du blocage du permalink normal** (`gwseq_horse_private_share_block_normal_
permalink()`, `includes/cheval-share-admin.php`) : cette fonction forçait un 404 sur le permalink
normal dès qu'un token était actif, quel que soit le statut réel — exactement le mécanisme à
l'origine du problème. Retirée entièrement : un cheval non public reste déjà nativement
inaccessible par son permalink normal (comportement WordPress natif, statut non "publish"), sans
code supplémentaire.

**Suppression des filtres d'exclusion recherche/sitemap/API REST** (`gwseq_horse_private_share_
exclusion_meta_clause()` et les cinq filtres qui la réutilisaient — `rest_gwseq_cheval_query`,
`rest_prepare_gwseq_cheval`, `wp_rest_search_query`, `wp_sitemaps_posts_query_args`,
`pre_get_posts`) : ces filtres excluaient tout cheval PORTANT UN TOKEN de ces quatre surfaces,
indépendamment de son statut réel — un cheval réellement public avec un ancien token qui traîne
en aurait été exclu à tort, contraire à la nouvelle règle ("le token ne doit plus modifier ni
bloquer la fiche publique"). Un cheval en mode "partage privé exclusif" étant par construction
non publié (brouillon, privé...), WordPress l'exclut déjà NATIVEMENT de la recherche/l'archive/la
taxonomie front-end (post_status restreint à "publish" par défaut), du sitemap natif
(`WP_Sitemaps_Posts` ne requête jamais que les posts "publish"), et de l'API REST (le contrôleur
applique `read_post`/le statut pour tout accès, direct ou listé) — sans code supplémentaire.
Simplification nette du fichier de glue (retrait de ~145 lignes), cohérente avec le principe déjà
établi du projet ("aucune logique parallèle à ce que WordPress fait déjà nativement").

**Boîte latérale "Partage" adaptée à la visibilité RÉELLE du cheval**, quatre états explicites :
public sans token (indique que le partage utilise la fiche publique) ; public AVEC un ancien
token (même message, PLUS une mention discrète que ce vieux lien reste valide, seule action
"Révoquer" — jamais présenté comme le mode principal, jamais de "Créer"/"Régénérer" proposés pour
un cheval déjà public) ; non public sans token (propose "Créer un lien de partage privé") ; non
public avec token (URL privée + Régénérer/Révoquer, inchangé). Le token n'est jamais révoqué
automatiquement lors d'un changement de statut, dans aucun sens (§ "ne pas casser les liens déjà
envoyés").

## 0.28.0 — Lot 1 « Partager & vendre » : correctif bloquant, création d'un lien privé

Premier test réel du Lot 1 (0.27.0) : cliquer sur "Créer un lien de partage privé" dans la boîte
latérale "Partage" d'une fiche cheval publiée redirigeait vers la liste "Actualités" au lieu de
revenir sur la fiche, sans jamais présenter le résultat de la création du lien.

**Cause racine.** Les actions de partage privé (Créer/Régénérer/Révoquer) étaient rendues comme des
`<form method="post" action="admin-post.php">` DANS la boîte latérale "Partage" — elle-même déjà à
l'intérieur du grand `<form id="post" method="post" action="post.php">` qui enveloppe la TOTALITÉ de
l'écran d'édition WordPress (titre, contenu, toutes les boîtes, bouton Publier/Mettre à jour). Un
`<form>` imbriqué dans un autre est INVALIDE en HTML : le navigateur ignore/aplatit la balise
interne, si bien que cliquer sur le bouton soumettait en réalité le formulaire EXTÉRIEUR de l'écran
d'édition (vers `post.php`, avec son propre champ caché `action` en conflit avec le nôtre) — jamais
notre gestionnaire `admin-post.php`. La redirection observée vers "Actualités" est le repli
générique de `post.php` pour une valeur de `$_POST['action']` qu'il ne reconnaît pas, jamais un
comportement de notre code.

**Correctif** (pas une redirection ajoutée pour masquer le symptôme — la cause elle-même est
retirée) : les trois `<form>` imbriqués de `gwseq_render_horse_private_share_controls()`
(`includes/cheval-share-admin.php`) sont remplacés par de simples liens `<a class="button">`
nonce-protégés (`gwseq_horse_private_share_action_url()`, nouvelle fonction, point unique de
construction de ces URL) — exactement le même schéma que les actions de ligne natives de WordPress
("Corbeille", "Restaurer"...), qui ne sont jamais des formulaires imbriqués. `admin-post.php` traite
indifféremment GET et POST (`$_REQUEST['action']`), et `check_admin_referer()` valide un nonce
transmis en GET tout aussi bien qu'en POST — `$_POST['cheval_id']` devient donc `$_REQUEST['cheval_id']`
dans `gwseq_horse_private_share_handle_admin_post()`.

**Robustesse de la redirection de retour** (revue en même temps, même fonction concernée) : extraite
dans `gwseq_horse_private_share_redirect_url_after_action()`, testable isolément, avec un repli
explicite vers la liste des Chevaux si `get_edit_post_link()` ne peut exceptionnellement pas produire
d'URL (capacité réévaluée différemment entre-temps) — jamais une URL vide transmise à
`wp_safe_redirect()`, jamais le repli WordPress générique vers le Tableau de bord qui a précisément
produit le symptôme observé. Aucun risque d'open redirect : `get_edit_post_link()`/`admin_url()` ne
dépendent jamais d'une entrée utilisateur.

**Vérifié pour les trois actions** (créer/régénérer/révoquer) : capacité (`edit_post` sur la fiche
précise), nonce (action précise `gwseq_partage_prive_{id}`, jamais générique — un nonce généré pour
un cheval ne fonctionne jamais pour un autre), validation du `post_id` et du type `gwseq_cheval`
(réutilise `gwseq_horse_private_share_user_can_manage()`, déjà en place, inchangée), comportement
d'erreur (`wp_die()` 403 si non autorisé, inchangé), conservation du token (activer/révoquer restent
strictement les mêmes fonctions métier qu'en 0.26.0, aucune régression sur la logique de token
elle-même — uniquement le TRANSPORT de l'action, de POST-formulaire-imbriqué vers GET-lien, a
changé).

## 0.27.0 — Lot 1 « Partager & vendre » : deux correctifs suite à revue avant recette

Revue du ZIP 0.26.0 avant recette réelle (par le professionnel, sans exécution) : deux failles
potentielles identifiées et corrigées, strictement dans le périmètre déjà livré du Lot 1 — aucune
autre évolution, Lot 2 toujours pas engagé.

**Fuite possible via `/wp/v2/search` (contrôleur REST transversal WordPress, y compris avec
`subtype=gwseq_cheval`).** Les filtres déjà en place (`rest_gwseq_cheval_query`/
`rest_prepare_gwseq_cheval`) ne couvrent que le contrôleur CRUD par post_type
(`WP_REST_Posts_Controller`) — `/wp/v2/search` est un point d'entrée COMPLÈTEMENT SÉPARÉ
(`WP_REST_Search_Controller`), qui interroge par défaut tous les post types publics interrogeables
(Cheval inclus) et force en dur `post_status => 'publish'`. Cette dernière contrainte suffit à
exclure un brouillon, mais PAS un cheval en partage privé actif resté au statut "publish" (cas
volontairement permis par ce module : le partage privé prend le pas sur le statut sans exiger de le
changer). Corrigé par `gwseq_horse_private_share_filter_rest_search_query()`
(`includes/cheval-share-admin.php`), accroché au filtre natif `wp_rest_search_query` et réutilisant
la MÊME clause d'exclusion meta_query que les trois autres points d'accroche déjà en place —
toujours une seule définition de "exclu du public", jamais une reconstruction séparée.

**Absence de directive anti-cache explicite sur la route `/partage/{token}`.** Une révocation ou
une régénération de lien doit être immédiatement effective — un cache plein-page, un reverse proxy
ou un CDN placé devant WordPress aurait pu continuer à servir une fiche PRIVÉE déjà révoquée sans
directive explicite le lui interdisant. Corrigé par `gwseq_horse_private_share_send_nocache_headers()`,
appelée sur les DEUX issues de `gwseq_horse_private_share_render()` (trouvée ET non trouvée) :
`nocache_headers()` native de WordPress, un en-tête `Cache-Control: no-store, no-cache,
must-revalidate, max-age=0, private` explicite (`no-store` étant la seule directive comprise sans
ambiguïté par tout intermédiaire HTTP comme "ne jamais mettre en cache", indépendamment de la
version de WordPress), `Pragma: no-cache`, et la constante `DONOTCACHEPAGE` (convention de facto
reconnue par la plupart des plugins de cache plein-page WordPress — WP Super Cache, W3 Total Cache,
WP Rocket...). Strictement scopé à la route de partage privé : le comportement de cache d'une fiche
PUBLIQUE reste totalement inchangé, ce code n'étant jamais appelé pour elle.

## 0.26.0 — Suite V1 « Partager & vendre » — Lot 1 : visibilité public/privé, liens, Open Graph

Premier lot de la suite « Partager & vendre » (0.25.0 validé en principe : recherche/filtres,
composition, sélection d'informations/vidéos, message personnel, aperçu, WhatsApp/SMS/Copier,
accroche commerciale, Open Graph amorcé, accès BO). Ce lot livre UNIQUEMENT la visibilité
public/privé, les liens qui en découlent et l'Open Graph associé — conformément à la méthode de
développement demandée (par lots, arrêt et recette réelle avant le lot suivant). Sélection
multi-chevaux (Lot 2), point d'entrée mobile GWS (Lot 3) et audit mobile de la fiche Cheval (Lot 4)
ne sont volontairement PAS développés dans ce lot.

**Partage privé (`includes/cheval-share.php`, nouvelles fonctions `gwseq_horse_private_share_*()`).**
Un cheval que le professionnel ne veut pas exposer publiquement peut désormais être envoyé à des
acheteurs précis via un lien secret `/partage/{token}`, sans jamais "publier la fiche puis la
retirer des menus". Le token : 32 octets générés par `random_bytes()` (64 caractères hexadécimaux,
non énumérables) — jamais l'ID WordPress ni le Global Horse ID, qui restent des identifiants
métier prévisibles et ne deviennent jamais un secret d'accès. Stocké dans une seule meta postmeta
déjà existante comme mécanisme (`_gwseq_partage_prive_token`, aucune nouvelle table) : sa seule
présence non vide fait office de drapeau actif. Créer/régénérer/révoquer sont exposés dans la boîte
latérale "Partage" déjà existante de l'écran d'édition d'une fiche (jamais une seconde interface),
via un formulaire classique `admin-post.php` + nonce (action ponctuelle et rare, contrairement aux
interactions fréquentes de l'écran Partager qui, elles, restent en AJAX). Régénérer invalide
immédiatement l'ancien lien (même opération que créer : le nouveau token remplace simplement
l'ancien). Un partage privé actif bloque le permalink public NORMAL du cheval, quel que soit son
post_status réel (`gwseq_horse_private_share_block_normal_permalink()`,
`includes/cheval-share-admin.php`) — sauf pour l'éditeur de la fiche lui-même. La route
`/partage/{token}` (`gwseq_horse_private_share_render()`) réutilise `get_single_template()`, la
hiérarchie de gabarits NATIVE de WordPress : aucun second système de rendu, un futur
`single-gwseq_cheval.php` côté thème s'appliquera automatiquement à cette route sans aucun code
supplémentaire.

**Exclusion recherche/archive/taxonomie/API REST/sitemap.** Une seule clause meta_query
(`gwseq_horse_private_share_exclusion_meta_clause()`) réutilisée aux quatre points d'accroche
natifs de WordPress : `pre_get_posts` (recherche publique, archive Cheval, taxonomie Catégorie de
cheval — jamais une requête sans rapport, pour ne pas ajouter de jointure meta inutile ailleurs sur
le site), `rest_{post_type}_query` (listes REST), `rest_prepare_{post_type}` (accès direct par
identifiant, bloqué en 404 pour qui ne peut pas éditer la fiche), et `wp_sitemaps_posts_query_args`
(sitemap natif WordPress). Limite documentée : un plugin SEO tiers actif construit ses propres
requêtes de sitemap indépendamment de ces filtres WordPress natifs — non couvert par ce mécanisme,
hors de notre contrôle (le partage privé d'un cheval resté en post_status non publié reste, lui,
protégé de la même façon vis-à-vis de tout plugin, publié ou non, puisqu'aucun système ne liste par
défaut des posts non publiés).

**Vocabulaire utilisateur (§3).** L'ancien libellé "Ajouter la fiche complète" laissait entendre à
tort un choix de permalink à comprendre — remplacé par "Inclure le lien vers la fiche"
("Inclure le lien privé vers la fiche" lorsque le lien est un partage privé). GWS détermine
désormais seul le lien approprié (`gwseq_horse_share_fiche_info()`, nouveau champ `fiche_type`
exposé par `gwseq_get_horse_shareable_data()`) : privé en priorité si actif (même si le cheval est
par ailleurs publié), sinon public si réellement publique, sinon aucun lien — jamais à l'utilisateur
de choisir. Comportement de sélection (case à cocher) totalement inchangé.

**Open Graph (§4) : fonctionne aussi sur la route de partage privé.** `gwseq_render_horse_og_meta()`
s'active désormais également sur `/partage/{token}` (en plus d'une fiche publiquement visible) :
`og:url` reflète l'URL RÉELLEMENT visitée (privée ou publique — corrigé, elle pointait auparavant
toujours vers le permalink normal, ce qui aurait affiché la mauvaise URL dans un aperçu WhatsApp
généré depuis un lien privé), une balise `noindex` est systématiquement ajoutée sur cette route
(jamais un mécanisme de sécurité en soi — seulement une indication aux moteurs de recherche,
puisque le blocage réel se fait par `gwseq_horse_private_share_block_normal_permalink()`), et notre
balisage reste actif MÊME si un plugin SEO tiers est détecté (limite documentée : ce dernier peut
malgré tout émettre en plus ses propres balises sur cette route, hors de notre contrôle). Le prix
n'est, comme avant, jamais exposé. `og:title`/`og:description`/`og:image` inchangés (la description
déterministe identité+origines+accroche existait déjà — aucun changement nécessaire pour §4).

**Déclencheur de flush des règles de réécriture par version** (`module.php`,
`gwseq_maybe_flag_rewrite_flush_for_version()`) : le mécanisme existant côté gws-core ne se
déclenche que lorsque la LISTE des modules actifs change, jamais lorsqu'un module déjà actif ajoute
une nouvelle règle de réécriture à une version ultérieure — ce déclencheur générique, comparé à la
version stockée en base, couvre ce cas précis (la nouvelle route `/partage/{token}`) ET tout ajout
futur de règle de réécriture dans ce module.

**Limites connues de ce lot** (documentées, pas des oublis) : la création/révocation d'un lien
privé se fait depuis l'écran d'édition classique de la fiche (boîte latérale "Partage"), pas encore
intégrée dans l'écran mobile "Partager" lui-même (l'écran Partager LIT déjà correctement le lien
privé une fois créé, via `fiche_type`/`fiche_url`) ; un plugin SEO tiers actif peut émettre ses
propres balises en plus des nôtres sur la route privée, et construire son sitemap indépendamment de
nos filtres WordPress natifs. Aucun gabarit `single-gwseq_cheval.php` dédié n'existe encore côté
thème (prévu à une étape ultérieure déjà documentée) : la route privée comme la route publique
utilisent aujourd'hui toutes deux le gabarit générique `single.php`, strictement inchangé par ce
lot.

## 0.25.0 — Partager un cheval : correctifs du transport vers les canaux (premier test réel WhatsApp)

Retour du premier test réel du bouton WhatsApp de `Chevaux → Partager` (0.24.0) : le message était
correctement structuré dans l'aperçu BO, mais perdait ses sauts de ligne et son pictogramme vidéo
🎥 (transformé en caractère de remplacement invalide « � ») une fois reçu dans WhatsApp. Ce lot
corrige uniquement le TRANSPORT vers les canaux externes, sans toucher au moteur de composition
(`gwseq_build_horse_share_message()`), déjà testé et correct.

**Cause exacte de la perte des sauts de ligne.** Le pipeline complet a été vérifié de bout en bout
(message PHP → réponse AJAX/JS → `encodeURIComponent()` → URL WhatsApp) : ni la composition, ni
l'encodage lui-même n'étaient en cause — `encodeURIComponent()` transforme déjà correctement `\n`
en `%0A` et tout caractère UTF-8 (accents/`×`/`•`/emoji) en séquences UTF-8 percent-encodées
valides, vérifié par un test de bout en bout reconstituant le pipeline complet. La divergence se
situe au dernier maillon : le lien court `https://wa.me/?text=...`, dont le transport du texte
pré-rempli s'est montré, sur un appareil réel, moins fiable que le point d'entrée canonique
documenté par WhatsApp lui-même, `https://api.whatsapp.com/send?text=...` (celui que `wa.me`
résout in fine). Le bouton WhatsApp utilise désormais directement ce point d'entrée canonique
(`assets/cheval-share-admin.js`, `buildWhatsappUrl()`) — aucun autre changement de code sur ce
point.

**Pictogramme vidéo 🎥 retiré (§2).** Non fiable à transporter vers un canal externe sur un appareil
réel (transformé en caractère de remplacement invalide), et non essentiel : le titre de la vidéo
suffit seul à l'identifier. Retiré à la source unique du libellé (`gwseq_horse_share_video_label()`,
`includes/cheval-share.php`) — l'aperçu BO ET les trois canaux externes en bénéficient uniformément
sans code supplémentaire, puisqu'ils consomment tous ce même libellé. Non remplacé par un autre
emoji.

**Bug découvert et corrigé en vérifiant explicitement « Ajouter la fiche complète » (§3).** Un
booléen JavaScript `false` transite par `FormData`/`$_POST` comme la CHAÎNE littérale `"false"`,
jamais comme un vrai booléen — `!empty('false')` (l'ancienne sanitation) vaut VRAI (chaîne non
vide), ce qui aurait silencieusement ignoré la case décochée et inclus quand même le lien de fiche.
Corrigé par `filter_var($raw['fiche'] ?? '', FILTER_VALIDATE_BOOLEAN)`
(`gwseq_sanitize_horse_share_selection()`, `includes/cheval-share-admin.php`), qui interprète
correctement `"false"`/`"0"`/`""` comme faux et `"true"`/`"1"` comme vrai. Coché/décoché vérifié
explicitement sur les trois canaux (WhatsApp/SMS/Copier), pas seulement l'aperçu.

**Adaptateur `sms:` audité et corrigé.** `sms:` n'est pas un standard unique entre plateformes :
sans numéro de destinataire, iOS exige `sms:&body=...` (séparateur `&`) alors qu'Android et la
plupart des autres navigateurs attendent `sms:?body=...` (séparateur `?`) — utiliser le mauvais
séparateur sur iOS ouvre l'application Messages SANS pré-remplir le texte, silencieusement, sans
erreur visible. Détection minimale par `navigator.userAgent` (`buildSmsUrl()`) ; même encodage que
WhatsApp (`encodeURIComponent()`) des deux côtés — seul le séparateur diffère d'une plateforme à
l'autre, jamais le contenu ni son encodage.

**Copier** : confirmé qu'il continue de copier le texte brut avec ses vrais retours à la ligne,
sans jamais d'encodage URL dans le presse-papiers (aucun changement de code nécessaire — déjà
correct).

## 0.24.0 — Partager un cheval : correctifs de recette avant test des canaux

Retour de la deuxième recette runtime de `Chevaux → Partager` (0.23.0) : placeholder, recherche,
filtres cumulés et synchronisation du prix avec l'aperçu sont tous confirmés fonctionnels. Ce lot
corrige les deux seuls points restants signalés, SANS développer la sélection multiple, le lien
privé, le PDF, le QR code ni aucune autre évolution du partage.

**Bug corrigé — un titre de cheval contenant une entité HTML littérale (ex. « NACELLE D&rsquo;ELLE
») s'affichait tel quel au lieu du caractère qu'elle représente (« NACELLE D'ELLE »).** Cause
racine : `get_the_title()` renvoie le contenu réel de `post_title` — un titre déjà enregistré avec
une entité sous forme de texte littéral (probablement un résidu d'un import/copier-coller antérieur)
n'est jamais corrigé automatiquement par WordPress à la lecture. Corrigé par un point de décodage
UNIQUE, `gwseq_horse_share_decode_title()` (`includes/cheval-share.php`,
`html_entity_decode(..., ENT_QUOTES | ENT_HTML5, 'UTF-8')`), appliqué aux quatre endroits où un nom
de cheval entre dans ce module : `gwseq_get_horse_shareable_data()` (champs `nom`/`nom_affiche`),
`gwseq_horse_share_lightweight_row()` (résultats de recherche, `includes/cheval-share-admin.php`),
et `gwseq_horse_share_pedigree_node_name()` (nom d'un parent dans les origines, qui peut lui aussi
provenir de `get_the_title()` via le résolveur de pedigree). Décodage à la volée à l'affichage
UNIQUEMENT — aucun titre existant n'est modifié en base. Sécurité : décodage une seule fois à la
source, puis échappement propre à chaque contexte de sortie déjà en place (texte brut pour
WhatsApp/SMS/Copier, `esc_attr()` pour l'Open Graph, `textContent` côté JavaScript qui n'interprète
jamais de HTML) — vérifié qu'un titre décodé contenant des caractères HTML potentiellement dangereux
ne produit jamais de balise exécutable à aucune étape (voir les tests dédiés). Vérifié par
retrait/restauration du décodage (les tests dédiés échouent exactement comme attendu sans le
correctif).

**UX corrigée — les filtres de l'écran de sélection (Sexe/Statut commercial/Catégorie/Année de
naissance) n'affichaient aucun libellé visible** (uniquement des libellés masqués aux technologies
d'assistance) : impossible de deviner à l'écran ce que représentait chaque « Tous ». Corrigé côté
JavaScript uniquement (`assets/cheval-share-admin.js`/`.css`) — aucune modification de la logique ou
de l'architecture de filtrage déjà validée (`gwseq_sanitize_horse_share_filters()`, `gwseq_horse_
share_filters_to_query_args()`, `gwseq_horse_share_search_chevaux()`, les points d'entrée AJAX
restent inchangés). Quatre `<label>` réels et VISIBLES, correctement associés à leur contrôle via
`for`/`id` (« Sexe », « Statut commercial », « Catégorie », « Année de naissance » pour le groupe
De/à) remplacent les précédents libellés `screen-reader-text`. Compact sur desktop (les quatre
groupes restent dans la même zone), empilement naturel sur mobile ; « Réinitialiser les filtres »
reste à sa place, toujours facilement accessible. En creusant ce correctif, une seconde erreur a été
trouvée et corrigée dans le même mouvement : le libellé du champ Sexe/Statut réutilisait par erreur
la même clé d'i18n que le texte de l'option « Tous » (`allSexe`/`allStatut`), ce qui aurait de toute
façon affiché « Tous » au lieu de « Sexe »/« Statut » même en rendant l'ancien libellé masqué
visible — quatre nouvelles clés dédiées (`sexeFilterLabel`, `statutFilterLabel`,
`categorieFilterLabel`, `anneeFilterLabel`) séparent désormais clairement le nom du champ du
contenu de son option "Tous".

## 0.23.0 — Partager un cheval : correctifs et améliorations de recette

Retour de la première recette runtime de `Chevaux → Partager` (0.22.0) : le principe général
(sélection → informations → aperçu → WhatsApp/SMS/Copier) est validé ; ce lot corrige un bug
prioritaire et apporte quatre améliorations demandées, SANS développer le partage multi-chevaux,
le lien privé, le PDF ni le catalogue (annoncés pour des lots ultérieurs distincts).

**Bug prioritaire corrigé — une information décochée (le prix, notamment) apparaissait quand même
dans l'aperçu.** Cause racine identifiée : `assets/cheval-share-admin.js`, `refreshPreview()`
déclenchait un appel AJAX indépendant à chaque frappe/coche, sans jamais annuler ni ignorer les
précédents — si une réponse plus ANCIENNE arrivait après une réponse plus RÉCENTE (latence réseau
variable, réaliste hors environnement de test), elle écrasait silencieusement l'aperçu à jour.
`gwseq_build_horse_share_message()` (PHP) reflétait déjà fidèlement la sélection reçue à chaque
appel isolé — ce n'était jamais un problème de composition, mais de SÉQUENCEMENT des réponses
côté client. Corrigé par un jeton de requête strictement croissant : une réponse n'est appliquée
que si aucune requête plus récente n'a depuis été émise, toute réponse obsolète est ignorée —
jamais un simple masquage visuel. Vérifié par retrait/restauration (le test dédié échoue
exactement comme attendu sans le correctif). Même principe vérifié pour TOUS les autres blocs
sélectionnables (origines, taille/indice, accroche, vidéos, fiche complète), pas seulement le prix.

**Chevaux sans photo — vignette de remplacement neutre réutilisable** (`gwseq_render_media_
placeholder()`, `includes/admin-ui.php`, classe CSS partagée `gwseq-media-placeholder` dans le
nouveau fichier `assets/gws-media-placeholder.css`) : réutilise le dashicon déjà choisi comme icône
de menu de "Chevaux" (`dashicons-pets`) plutôt qu'une nouvelle icône à maintenir. Élément
d'interface uniquement — aucun média créé, aucune image à la une définie, aucune fiche modifiée.
`assets/cheval-share-admin.js` reproduit le même balisage minimal (même classe, même dashicon) pour
ses résultats construits en JavaScript, garantissant une vignette visuellement identique partout.
Réutilisable tel quel par un futur écran BO ayant le même besoin.

**Filtres métier de l'écran de sélection** (`gwseq_sanitize_horse_share_filters()`/
`gwseq_horse_share_filters_to_query_args()`/`gwseq_horse_share_search_chevaux()`, `includes/
cheval-share-admin.php`) : Sexe (vocabulaire commercial déjà retenu pour cet écran), Statut
commercial, plage d'année de naissance (De/à, bornes réutilisées de `cheval-fields.php`), Catégorie
de cheval (taxonomie déjà existante, jamais de nouvelle catégorie créée à la volée) — tous les
quatre cumulables entre eux ET avec la recherche texte, aucun nouveau référentiel créé. Filtrage
DYNAMIQUE (aucun bouton "Appliquer"), action "Réinitialiser les filtres". Restriction de permission
(§21, un auteur sans `edit_others_posts` ne voit que ses propres chevaux) vérifiée applicable même
filtres actifs.

**Préparation du futur usage multi-chevaux, SANS le développer** : `gwseq_horse_share_search_
chevaux()` est désormais LA source de résultats unique (recherche + filtres), volontairement
découplée de tout point d'entrée AJAX précis — un futur écran de sélection multiple pourra la
réutiliser telle quelle, sans réécrire la moindre logique de filtrage ; seule une interface de
sélection multiple resterait à ajouter le moment venu. Pour cette version : toujours un cheval → un
bouton "Partager" → un partage individuel, aucune case à cocher multi-sélection.

**Densité de l'écran de composition** (`assets/cheval-share-admin.css`) : les listes "Informations
à envoyer" et "Vidéos" sont désormais des listes compactes à fines lignes de séparation (plutôt que
des blocs verticaux très espacés), zones tactiles toujours ≥ 40px, accessibilité inchangée.

**Tests** : couverture étendue dans les trois fichiers existants du lot 0.22.0 — nouvelles
assertions de "va-et-vient" (coché → présent, décoché → absent immédiatement, sans trace
résiduelle) pour chaque bloc sélectionnable côté PHP ; nouveau scénario runtime Node reproduisant
fidèlement un ordre d'arrivée réseau réaliste (réponse lente arrivant après une réponse rapide) et
vérifiant que le correctif de séquencement l'ignore bien ; couverture complète des filtres
(sanitation, transformation en requête, cumul réel via l'AJAX, non-fuite de permission, aucune
donnée Cheval modifiée par une recherche/un filtrage) ; couverture de la vignette de remplacement
(PHP et JS, y compris l'en-tête de l'écran de composition). Quatre mécanismes critiques
supplémentaires vérifiés par retrait/restauration. Suite complète (21 fichiers PHP + 3 suites JS
runtime) ré-exécutée : aucune régression.

## 0.22.0 — Partager un cheval

Nouveau lot central : le partage commercial d'un cheval déjà renseigné dans GWS, réutilisable
immédiatement par WhatsApp/SMS/Messages ou copie, sans jamais rien envoyer soi-même (GWS Core ne
dispose d'aucun serveur SMS, d'aucune API WhatsApp/Meta, d'aucun compte, d'aucun historique).

**Principe fondamental (§2)** : aucune invention. Toute donnée absente d'une fiche cheval reste
absente du partage — jamais de fallback généré, jamais d'IA, jamais une qualité/un potentiel/un
niveau sportif inventé.

**Nouveau champ Cheval** : Accroche commerciale (`_gwseq_accroche_commerciale`,
`includes/cheval-editorial.php`), une ou deux phrases courtes, distincte de la Présentation/
Description longue existante — même mécanisme d'enregistrement/lecture déjà en place pour les
champs éditoriaux (aucune duplication), sanitation `sanitize_textarea_field` (texte court/
multiligne, aucun HTML). Vide par défaut, aucune modification des fiches existantes.

**Architecture en couches** (§4, `includes/cheval-share.php`, nouveau fichier — fonctions métier
pures, aucune dépendance à wp-admin) :
```
Cheval (meta déjà existantes)
  -> gwseq_get_horse_shareable_data()   [QUOI est partageable, avec son libellé déjà composé]
  -> sélection utilisateur              [écran BO, éphémère]
  -> gwseq_build_horse_share_message()  [COMMENT ça devient un message, plain-text]
  -> WhatsApp / SMS / Copier            [consomment tous les trois le MÊME texte déjà composé]
```
Aucune logique métier dupliquée : âge (`gwseq_cheval_age_from_birth_year()`), race/stud-book
(`gwseq_cheval_race_label()`), indices sportifs (`gwseq_get_cheval_sport_indice()`), pedigree
(`gwseq_resolve_horse_pedigree()`), prix (`gwseq_cheval_price_summary()`), vidéos
(`gwseq_get_cheval_videos()`) — tous des helpers déjà existants, simplement composés.

**Informations partageables** (§8), regroupées en lignes commercialement lisibles plutôt qu'un
export de meta : identité ("Jument Selle Français — 7 ans", vocabulaire commercial "Jument/Étalon/
Hongre" — décision produit distincte du libellé administratif "Femelle/Mâle/Hongre" utilisé
ailleurs dans le BO, mêmes clés techniques réutilisées), origines ("Par UNTOUCHABLE × KANNAN" —
Père × Père de la mère/"damsire", convention de présentation la plus reconnue du marché du cheval
de sport, noms en majuscules via `gwseq_format_horse_name_display()`), taille + UN SEUL indice
sportif mis en avant ("1,68 m • ISO 135", priorité ISO > ICC > IDR — les indices génétiques ne sont
volontairement PAS inclus dans cette V1), statut/prix, accroche commerciale, vidéos (une case par
vidéo réellement présente, "🎥 {titre}" ou "🎥 Vidéo" si aucun titre — jamais un titre inventé), et
lien vers la fiche complète si publiquement accessible.

**Règle prix/statut (§10)** : le prix n'est proposable au partage QUE si le statut commercial est
"À vendre" ou "Réservé" — jamais pour "Non proposé" ni "Vendu", quel que soit le prix
techniquement enregistré en base. Jamais présélectionné par défaut (prudence commerciale
explicitement demandée).

**Confidentialité (§13/§20)** : un lien de fiche complète n'est jamais proposé pour un cheval non
publiquement visible (`gwseq_horse_is_publicly_viewable()` — statut WordPress `publish` ET aucun
mot de passe, les deux seuls mécanismes natifs déterminant une réelle visibilité publique). Aucune
donnée sensible n'apparaît jamais dans l'Open Graph.

**Écran BO dédié** (§5-7/§21/§27, `includes/cheval-share-admin.php` + `assets/cheval-share-admin.
{js,css}`, nouveaux fichiers) : `Chevaux → Partager`, MOBILE-FIRST (gros contrôles tactiles,
sections courtes, aucun tableau WordPress). Recherche légère (nom, scopée aux chevaux accessibles
à l'utilisateur — capacité `edit_posts` pour l'écran, `edit_post` méta-capacité pour toute donnée
complète d'une fiche précise, mêmes règles natives que la liste `Chevaux → Tous les chevaux`,
aucune capacité inventée) ; les données complètes ne sont chargées qu'une fois un cheval choisi.
Accès également depuis une fiche cheval (action de ligne "Partager" + boîte latérale "Partage") —
les deux mènent au MÊME écran, jamais une seconde interface. Aperçu en temps réel composé
côté serveur via AJAX (debounce ~350ms), WhatsApp (`https://wa.me/?text=`), SMS/Messages
(`sms:?body=`) et Copier (presse-papiers + retour "Message copié" annoncé via `aria-live`)
consomment tous les trois EXACTEMENT le même texte déjà affiché dans l'aperçu.

**Open Graph de la fiche Cheval (§19)** : `og:title`/`og:description`/`og:image`(+dimensions)/
`og:type`/`og:url`, émis uniquement si aucun plugin SEO n'est actif (même détection que le thème
gws-starter, réutilisée si disponible — aucun second système SEO/canonical/schema créé, la balise
canonique native de WordPress n'est jamais dupliquée). Image : une DÉRIVÉE WordPress adaptée
(`medium_large`), jamais l'original. Description : identité + origines + accroche commerciale
uniquement — le prix n'est JAMAIS inclus dans l'Open Graph, même s'il serait sélectionnable par
ailleurs pour un partage privé.

**Aucune persistance (§22)** : ni CPT, ni table, ni historique, ni destinataire, ni statut d'envoi.
Un partage est entièrement éphémère.

**Limites connues (V1)**, documentées, pas des oublis : indices génétiques non inclus dans le
résumé de partage ; pas de jointe automatique d'image au message (dépendance trop fragile aux
plateformes/navigateurs) ; pas de lien privé pour un cheval non public (module « Lien privé »
annoncé pour un lot ultérieur) ; pas de sélection multi-chevaux ni de PDF (également annoncés,
hors périmètre de ce lot).

**Tests** : deux nouveaux fichiers PHP (`gws-equestrian-cheval-share-logic-test.php` — la couche
métier pure, y compris Open Graph — et `gws-equestrian-cheval-share-admin-test.php` — l'écran BO,
les trois points d'entrée AJAX et leurs permissions) et un nouveau fichier d'exécution réelle Node
(`gws-equestrian-cheval-share-runtime-test.js` — câblage réel des cases à cocher, de l'aperçu, et
des trois actions WhatsApp/SMS/Copier). Quatre mécanismes critiques vérifiés par
retrait/restauration (règle prix/statut, visibilité publique/mot de passe, permission spécifique à
une fiche sur les points d'entrée AJAX, unicité du texte consommé par les trois canaux). Deux
tests existants mis à jour pour refléter des changements légitimes du module : le compte de champs
éditoriaux (`gws-equestrian-cheval-editorial-logic-test.php`, 9 -> 10 avec l'Accroche commerciale)
et la vérification de non-duplication du filtre `post_row_actions`
(`gws-equestrian-actualites-logic-test.php`, désormais ciblée sur le callback précis de retrait de
Quick Edit plutôt que sur le nombre total de filtres du module, qui légitimement augmente avec la
nouvelle action de ligne "Partager"). Suite complète (21 fichiers PHP + 3 suites JS runtime)
ré-exécutée : aucune régression sur Cheval, Prestations, Équipe, Actualités, Groupes tarifaires.

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

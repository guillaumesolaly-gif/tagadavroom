# Changelog — thème Saint-Père Avocat

## Version 2.7.4 — ajustement du line-height global des H1 (1 → 1.04)

Le rendu réel en environnement WordPress Local a montré que `line-height:1` (2.7.3) restait
insuffisant sur au moins un cas concret : sur « Entreprise en difficulté : que faire ? », la
descendante du « p » d'« Entreprise » touchait encore les hampes de « difficulté » à la ligne
suivante (texte en gras 600, traits plus épais que sur les fragments accentués testés
précédemment). Confirmé visuellement à la vraie police, viewport 1440×900.

Une seule valeur modifiée dans `style.css` : `/1` → `/1.04` sur la règle H1 globale — valeur déjà
testée comme sûre lors de l'audit de la 2.7.3. Re-contrôle complet effectué après ce changement :
le contact entre « p » et « difficulté » disparaît (marge blanche nette) sur les 6 résolutions ;
aucune collision résiduelle sur les autres grands H1 (Accueil, Liquidation judiciaire, FAQ,
Trouver un avocat, Postulation, Diagnostic) ; titres courts toujours visuellement compacts, pas
d'aération excessive ; aucune régression sur les H1 bicolores (couleur, italique, graisse
inchangés) ; 390 tests automatisés (Conseils + Diagnostic) toujours au vert.

## Version 2.7.3 — correction globale du chevauchement des grands H1

Correction d'une ligne dans `style.css`, sans aucun autre changement : la règle globale
`h1{font:600 clamp(58px,6.2vw,94px)/.9 ...}` passe à `/1`. Le `.9` d'origine provoquait des
collisions de glyphes entre lignes sur tout grand H1 se recomposant sur plusieurs lignes
(Accueil au-dessus de 1100px, Expertises/Guides/FAQ/Trouver un avocat/Postulation au-dessus de
900px, Diagnostic et pages légales à toutes les largeurs — ces dernières n'ayant aucun override
propre). Les Conseils aux dirigeants ne sont pas concernés : leur propre règle `.conseil-hero h1`
(`line-height:1.08`, 2.7.2) reste inchangée et prioritaire.

Valeur choisie après comparaison de `0.92` à `1.08` sur les titres les plus défavorables du site
(phrases courtes à la taille maximale du clamp) : en dessous de `0.94` les lignes se touchent
réellement ; `1` est la plus petite valeur testée qui élimine la collision avec une marge fiable
sur tous les gabarits, tout en restant sensiblement plus resserré que `1.08`. Taille, graisse,
letter-spacing, police et style des H1 bicolores (`h1 em`) inchangés — aucune réduction des
grands titres, aucune régression visuelle ou fonctionnelle sur les H1 bicolores existants (vérifié
: couleur, italique et graisse du fragment accentué identiques après la correction).

Les overrides existants (`.hero-text h1` à 901-1100px et ≤560px, `.expertise-hero h1` ≤900px)
restent inchangés — redondants mais sans risque, laissés en l'état.

## Version 2.7.2 — finition visuelle de « Conseils aux dirigeants » (H1, hub, cartes)

Passe purement visuelle avant mise en production. Aucun changement de contenu, de CTA, de
maillage, de catégories ou de tracking — uniquement `page-conseils.css`,
`page-templates/conseil-dirigeant.php`, `page-templates/conseils-hub.php` (un seul correctif
ciblé), `config/conseils.json` et `inc/conseils.php`.

**Interlignage des H1** (`page-conseils.css`) : `.conseil-hero h1` utilisait un raccourci `font`
sans famille de police, donc invalide et intégralement ignoré par le navigateur — le H1 retombait
sur la règle globale du site (`line-height:.9`), d'où le chevauchement observé sur les titres
longs. Corrigé en alignant taille et graisse sur `.expertise-hero h1` (Guides/Expertises) avec un
`line-height` de `1.08` conservé à toutes les résolutions pour ces titres plus longs. Le H1 global
du site n'est pas modifié.

**H1 bicolores** : deux nouvelles metas `_spa_conseil_h1_main` / `_spa_conseil_h1_accent`,
renseignées uniquement à la création initiale de chaque page (toujours `insert-only`, jamais de
réécriture d'une page existante) et éditables ensuite depuis la boîte « Réglages du Conseil » dans
WordPress. Rendu en `<span class="conseil-h1-main">` + `<em class="conseil-h1-accent">` à
l'intérieur d'un unique `<h1>`, sans `<br>` forcé — le retour à la ligne se fait naturellement
selon la largeur disponible.

**Hub — respiration et cartes** : ajout d'un `padding-top` entre le hero et la première catégorie
(`.conseil-hero+.conseils-hub-category`, valeur reprise du padding du hero lui-même). Les cartes
Conseil retrouvent un fond blanc distinct du fond ivoire de la page, un liseré fin, un padding
intérieur plus généreux, sans arrondi ni ombre. Grille plafonnée à 3 colonnes maximum (jamais plus,
contre un `auto-fit` non borné auparavant), 2 colonnes en dessous de 1100px (vérifié à 1024px :
3 colonnes rendait les titres illisibles), 1 colonne en dessous de 560px — seuils dédiés à la
grille des cartes uniquement, aucun seuil global du site modifié.

**Correctif annexe** (`page-templates/conseils-hub.php`) : les pictogrammes de 2 des 4 catégories
retombaient sur l'icône générique par défaut à cause de clés obsolètes dans `$category_icons`
(restes du nommage des catégories antérieur à la 2.7.0). Corrigé — rien d'autre modifié dans ce
fichier.

## Version 2.7.1 — SEO Yoast au seed, fiabilisation du flag de seed, numéro de la pop-in Diagnostic

Quatre correctifs ciblés avant le premier déploiement WordPress, aucune régression sur le reste.

**SEO au premier seed (`inc/conseils.php`)** : le hub et les 14 pages Conseil renseignent
désormais leur title/meta description au moment de leur création initiale même lorsqu'un plugin
SEO est actif. Si Yoast est actif (`WPSEO_VERSION`), ses metas natives (`_yoast_wpseo_title`,
`_yoast_wpseo_metadesc`) sont écrites directement — Yoast les lit telles quelles, sans appel à son
API. Si aucun plugin SEO n'est actif, le fallback existant (`_spa_seo_title`/`_spa_seo_description`,
`inc/seo.php`) est conservé à l'identique. Un autre plugin SEO (RankMath, SEOPress, AIOSEO)
continue de ne recevoir aucune meta ici, comportement déjà existant avant ce correctif. Toujours
strictement au moment du `wp_insert_post()` initial — jamais de mise à jour ultérieure.

**Fiabilisation du flag de seed (`inc/conseils.php`)** : `spa_conseils_pages_version` n'est
désormais posé qu'après vérification effective que le hub et les 14 pages Conseil existent tous.
Si une création échoue, le flag reste absent et le prochain passage sur `init` ne retente que
les pages manquantes — le principe insert-only (page existante jamais modifiée) reste inchangé.

**Numéro de téléphone de la pop-in de confirmation du Diagnostic** (`page-diagnostic-entreprise-
en-difficulte.php`, `page-diagnostic.css`) : sur desktop (>900px), le numéro est désormais un
texte non interactif (aucun `href="tel:"` atteignable) au lieu d'un lien cliquable — évite
l'ouverture d'un sélecteur d'application sur un poste sans téléphonie. Cliquable normalement sur
tablette et mobile (≤900px), `contact_phone_click` inchangé sur ces tailles d'écran. Deux éléments
distincts (lien réel vs texte simple, comme le fait déjà `.anonymous-contact` du même gabarit),
pas un lien visuellement déguisé. Correctif d'une ligne dans `diagnostic.js`
(`focusableIn()` filtre désormais les éléments non affichés) rendu nécessaire par ce changement :
sans lui, le piège à focus de la pop-in tentait de rendre le focus au lien tel: masqué sur
desktop. Comportement de fermeture, de confirmation serveur et de `sessionStorage` inchangé.

**Documentation** : commentaires obsolètes « 6 pages Conseil » corrigés en « 14 pages Conseil »
dans `inc/conseils.php` (`inc/conseils-content.php`, `config/conseils.json`, `conseils.js`,
`page-templates/*`, `page-conseils.css` non touchés dans cette version).

## Version 2.7.0 — extension de « Conseils aux dirigeants » (6 → 14 pages)

La version 2.6.0 n'ayant jamais été déployée sur un WordPress réel, cette livraison constitue le
premier seed effectif et définitif de la rubrique : hub + 14 pages Conseil en une seule fois, sans
aucun mécanisme de migration. Le principe `insert-only` est inchangé et reste la règle pour toute
évolution future : une page qui n'existe pas encore est créée dans son état définitif ; une page
déjà créée n'est plus jamais modifiée automatiquement (ni `post_content`, ni ses métadonnées).

**Contenu** : 4 catégories définitives — Trésorerie et dettes, Banques et créanciers, Agir avant la
procédure collective, Redressement et liquidation — regroupant les 14 pages (`problemes-tresorerie-
entreprise`, `entreprise-ne-peut-plus-payer-urssaf`, `entreprise-ne-peut-plus-payer-fournisseurs`,
`entreprise-ne-peut-plus-payer-salaires`, `banque-supprime-decouvert-professionnel`, `negocier-
dettes-entreprise`, `entreprise-assignee-creancier`, `mandat-ad-hoc-ou-conciliation`, `cessation-
paiements-delai-45-jours`, `ouverture-redressement-judiciaire`, `redressement-judiciaire-dirigeant`,
`redressement-judiciaire-salaries-fournisseurs-clients`, `liquidation-judiciaire-risques-dirigeant`,
`caution-personnelle-dirigeant-entreprise-difficulte`). Maillage interne enrichi entre Conseils,
vers les Expertises et Guides existants (jamais dupliqués), avec un CTA principal (Diagnostic ou
Contact, parfois mis en avant) déterminé page par page selon le degré d'urgence du sujet traité.

**Fichiers modifiés** (aucun nouveau fichier) : `config/conseils.json` (14 entrées définitives —
catégorie, ordre d'affichage, liens connexes, SEO), `inc/conseils-content.php` (8 nouveaux
générateurs de contenu, les 6 existants inchangés), `inc/conseils.php` (4 catégories, exposition des
slugs Conseil à `conseils.js` via `wp_localize_script()`), `conseils.js` (nouvel événement Matomo).
`page-templates/conseil-dirigeant.php` et `page-templates/conseils-hub.php` n'ont nécessité qu'un
ajustement mineur du premier (bande « Pour aller plus loin » désormais masquée quand elle est
vide) — les deux gabarits restent entièrement génériques, aucun nouveau gabarit requis.

**Tracking** : 5ᵉ événement Matomo `conseil_related_click` (catégorie `Conseils`), déclenché
lorsqu'un lien du corps éditorial pointe vers une autre page Conseil — libellé : le slug de la page
de destination. Classement par priorité : Diagnostic > Contact > carte du hub > Conseil > Expertise
réelle > aucun événement pour un Guide. Les 4 événements existants (`conseil_diagnostic_click`,
`conseil_contact_click`, `conseil_expertise_click`, `hub_card_click`) sont inchangés.

**Menu toujours non activé** : voir 2.6.0 pour la marche à suivre lorsque l'activation sera
demandée — aucune modification de `template-parts/site-header.php` dans cette livraison non plus.

**Non touché, vérifié** : le Diagnostic (2.5.3), le tracking Matomo existant, `theme.js`, le
header/footer et la navigation actuelle, le contact flottant, la page d'accueil, les pages
Expertises/Guides existantes, la FAQ, la Postulation, Complianz, le SEO et les données structurées
existants, les réglages centralisés du cabinet.

## Version 2.6.0 — nouvelle rubrique « Conseils aux dirigeants » (hub + 6 pages)

Livraison strictement additive, validée par architecture avant développement. Nouveaux fichiers
uniquement, à l'exception de deux lignes ajoutées (jamais modifiées) dans `functions.php` et
`inc/setup.php` — voir le rapport de livraison complet pour le détail exact.

**Contenu** : page hub `/conseils-aux-dirigeants/` + 6 pages Conseil (`problemes-tresorerie-
entreprise`, `entreprise-ne-peut-plus-payer-urssaf`, `entreprise-ne-peut-plus-payer-fournisseurs`,
`banque-supprime-decouvert-professionnel`, `cessation-paiements-delai-45-jours`, `ouverture-
redressement-judiciaire`), publiées, indexables, sans `noindex`, avec breadcrumbs et maillage
interne vers les pages existantes (prévention, cessation des paiements, sauvegarde et
redressement, diagnostic).

**Architecture** : un gabarit de page WordPress unique (`page-templates/conseil-dirigeant.php`)
partagé par les 6 Conseils — un futur 7ᵉ Conseil n'exige aucun nouveau fichier, seulement une
page WordPress avec ce gabarit assigné. Création des pages en `insert-only` (`spa_seed_conseils_
pages()`, même principe que `spa_seed_diagnostic_page()`) : `config/conseils.json` ne fournit les
réglages structurels (catégorie, note de hero, liens connexes) qu'au moment de cette création
unique, jamais relu ensuite — `post_content` (le texte) et les métadonnées WordPress
(`_spa_conseil_*`) sont les seules sources de vérité une fois une page créée. Nouvelle meta-box
« Réglages du Conseil » dans l'éditeur pour ces réglages structurels.

**Menu non activé** : aucune modification de `template-parts/site-header.php` dans cette
livraison. Pour activer le lien « Conseils aux dirigeants » dans le menu principal plus tard,
ajouter dans la balise `<nav>` (juste après le lien « Expertises »), sans toucher à rien d'autre :

```php
<a href="<?php echo esc_url(home_url('/conseils-aux-dirigeants/')); ?>">Conseils aux dirigeants</a>
```

**Tracking** : 4 nouveaux événements Matomo (catégorie `Conseils`, `conseils.js`, chargé
uniquement sur ces 7 pages, réutilise `window.spaTrack` sans modifier `theme.js`) —
`conseil_diagnostic_click`, `conseil_contact_click`, `conseil_expertise_click`, `hub_card_click`.

**Non touché, vérifié** : le Diagnostic (2.5.3, moteur, scoring, transmission des leads, pop-in,
sessionStorage), le tracking Matomo existant, le header/footer et la navigation actuelle, le
modal/contact flottant, les pages Expertises/Guides existantes, la FAQ, les réglages centralisés
du cabinet, le SEO et les données structurées existants.

## Version 2.5.3 — correctif bloquant : continuité de la reprise après erreur serveur

Revue indépendante du code 2.5.2 : après une redirection d'erreur serveur (rechargement complet de
la page), le formulaire de rappel était restauré avec les coordonnées mais sans les 12 réponses du
questionnaire, qui n'existaient plus que dans l'objet JS `answers` — vidé par le rechargement. Une
nouvelle soumission ne pouvait donc jamais republier les réponses et `spa_handle_diagnostic_lead()`
renvoyait systématiquement `diagnostic=incomplete`. De plus, `diagnostic_started` repartait à l'heure
du rechargement, exposant une nouvelle tentative immédiate au garde-fou serveur des 8 secondes
(`diagnostic=invalid`). Correctif strictement limité à cette continuité — aucun élément UX validé
(présentation état A/B, transition, pop-in, scoring, tracking, SEO) n'a été modifié.

**État temporaire avant soumission** (`saveStateBeforeSubmit()`) : conserve désormais, en plus du
niveau et des champs du formulaire de rappel déjà présents, les 12 réponses (`answers`) et la valeur
du champ `diagnostic_started` au moment de l'envoi.

**Après redirection, cas `sent`** : comportement inchangé — les réponses ne sont pas restaurées,
seuls le résultat et la pop-in sont affichés, l'état temporaire est supprimé immédiatement du
`sessionStorage` (déjà le cas).

**Après redirection, cas d'erreur permettant une nouvelle tentative** (tous les codes sauf `limit`) :
les 12 réponses sont rechargées dans l'objet JS `answers`, les coordonnées et une valeur valide de
`diagnostic_started` (celle du parcours initial, jamais régénérée) sont restaurées, puis l'état est
supprimé du `sessionStorage` dès qu'il a été rapatrié en mémoire. Correction du bug identifié dans
`syncAnswerInputs()` : après un tel rechargement, aucune question n'est réellement rendue dans
`stage` (contrairement au parcours normal où la dernière question reste un radio visible dans le
DOM) — un nouveau drapeau (`restoredWithoutLiveQuestion`) fait que plus aucune réponse n'est exclue
à tort des champs cachés reconstitués, garantissant que la seconde soumission transporte bien les 12
réponses.

**Cas `limit`** : traité à part et volontairement exclu de la restauration — puisque le serveur vient
de refuser l'envoi pour dépassement du quota, le formulaire de rappel n'est pas représenté (ce qui
laisserait croire qu'un nouvel envoi immédiat pourrait réussir) ; seul le message existant invitant à
contacter directement le cabinet reste affiché. L'état temporaire est tout de même supprimé du
`sessionStorage` pour éviter toute fuite vers une session ultérieure.

**Recette** : nouveau scénario de bout en bout — 12 questions → résultat → formulaire → première
soumission → simulation `mail-error` → restauration → seconde soumission — avec vérification du
corps POST réel de la seconde soumission (12 réponses présentes et non dupliquées, coordonnées,
consentement, `diagnostic_started` accepté) et rejeu de ce corps à travers le véritable
`spa_handle_diagnostic_lead()` (`inc/diagnostic.php` non modifié), confirmant qu'il atteint bien
`wp_mail()` au lieu de retourner `incomplete` ou `invalid`. Complété par les cas `security`,
`missing`, `incomplete` et `limit` (comportement serveur et front vérifiés séparément), et par une
suite de non-régression complète (39 assertions : transition état A→B, 3 niveaux de résultat + règle
critique, tracking Matomo complet, pop-in, anti-double-soumission, visibilité de la carte selon le
viewport). 66/66 tests passés.

## Version 2.5.2 — réintroduction d'une carte visuelle « Votre diagnostic » (état A uniquement)

Suite au retour terrain sur la 2.5.1 : le premier écran à une seule colonne (~760px) a été jugé trop
vide après le retrait complet du composant « Votre diagnostic » — la page perdait la matérialisation
visuelle de l'outil. Ce correctif réintroduit une carte visuelle, mais uniquement sur l'état A (avant
démarrage) et sous une forme compacte à deux colonnes centrée, distincte de la composition large de
la 2.5.0.

**État A (avant clic sur « Commencer »)** : `.diagnostic-hero-main` devient une grille à deux colonnes
(`1.35fr / 1fr`, gap 56px) contenue dans un conteneur global élargi à 1180px maximum, toujours centré
dans la page. Colonne gauche (dominante, inchangée dans son contenu) : fil d'Ariane, kicker, H1,
accroche, statistiques (12 questions / ~3 min / résultat immédiat), CTA « Commencer le diagnostic »
(seul CTA de l'écran), mention de confidentialité. Colonne droite : une carte `.diagnostic-preview`
(`aria-hidden="true"`, purement décorative) — fond ivoire, liseré saumon en haut, ombre portée légère,
cercle saumon pâle en arrière-plan — reprenant « Votre diagnostic », les trois thématiques (Trésorerie
/ Dettes & échéances / Situation juridique), une frise de 12 points et « 12 questions · Résultat
immédiat ». La carte n'est qu'un support visuel : aucun lien, aucun second CTA.

**État B (questionnaire, résultat, formulaire) : strictement inchangé.** `.diagnostic-form` et
`.diagnostic-server-message` conservent leur largeur propre de 760px (`max-width:760px;margin:0 auto`)
indépendamment de l'élargissement du conteneur parent — vérifié au pixel près par comparaison des
dimensions et de la position du formulaire avant/après ce correctif : aucune différence. Au clic sur
« Commencer le diagnostic », c'est toujours l'ensemble de l'état A (colonne gauche **et** carte,
puisque les deux appartiennent au même groupe `[data-diagnostic-panel-a]`) qui s'efface en un seul
fondu, laissant la place à l'état B exactement comme en 2.5.1 — aucune bascule scindée, aucun scroll.

**Responsive** : la carte reste affichée en desktop et tablette paysage. En dessous de 900px de large
(ou de 640px de haut), la grille repasse à une colonne et la carte est masquée (`display:none`), afin
de ne jamais repousser le CTA sur tablette portrait ou mobile — priorité conservée : H1 → promesse →
statistiques → CTA → confidentialité.

**Non touché, vérifié** : l'état B (largeur, position, contenu — identiques au pixel près), la pop-in
de confirmation, le principe de transition A→B, le moteur de calcul et le barème, `sessionStorage`,
`inc/diagnostic.php`, les événements Matomo existants et ceux de la 2.5.0/2.5.1, la logique
anti-double-soumission, la position du bloc « Comprendre avant d'agir », le header et le footer
globaux, le SEO.

**Recette** : 35 assertions Playwright (pop-in : affichage, piège à focus, Échap, retrait d'`inert` ;
restauration `sessionStorage` succès et erreur avec nettoyage du stockage ; anti-double-soumission ;
tous les événements de tracking y compris `diagnostic_lead_attempt` et `diagnostic_related_click`) +
30 assertions (transition A→B sans scroll, largeur de conteneur stable, CTA visible sans scroll et
absence de débordement horizontal sur 6 viewports, 3 niveaux de résultat + règle critique, tracking,
pop-in) + vérification visuelle par capture d'écran sur desktop/tablette paysage/tablette portrait —
100 % de réussite.

## Version 2.5.1 — correctif UX ciblé de la page Diagnostic (conteneur unique hero/questionnaire)

Suite au test de la 2.5.0 sur instance WordPress réelle : la pop-in de confirmation est validée et
n'a pas été retouchée (déclenchement, accessibilité, retour au résultat, tracking — tout inchangé),
seule la phrase d'urgence y est désormais mise en valeur dans un encadré saumon pâle discret.

**Changement principal** : le hero et le questionnaire partagent maintenant un seul conteneur
(`.diagnostic-stage`, largeur stable ~760px) au lieu de deux sections distinctes. Au clic sur
« Commencer le diagnostic », l'état précédent (titre, promesse, CTA) s'efface en fondu léger et la
question 1 apparaît exactement à sa place — plus de second bloc plus bas, plus de `scrollIntoView()`.
Un lien « ← Quitter le diagnostic » ramène à l'état précédent sans rechargement ; comme l'index de
question et les réponses déjà saisies ne sont jamais réinitialisés ailleurs dans le moteur, reprendre
le diagnostic après l'avoir quitté reprend naturellement là où l'utilisateur s'était arrêté.

**Premier écran simplifié** : suppression complète du composant « Votre diagnostic » (thématiques +
frise de progression) et du bloc « Autodiagnostic confidentiel / Quelques minutes pour mieux situer
le niveau de vigilance », tous deux jugés redondants avec le hero une fois celui-ci renforcé. Le texte
« Aucun montant ni commentaire libre ne vous sera demandé » (seule information de ce bloc non répétée
ailleurs) n'a pas été réintégré artificiellement ailleurs sur la page : évalué comme secondaire, sa
suppression a été préférée à un ajout de contenu non naturel, conformément à l'arbitrage demandé. Le
hero se limite désormais à : kicker, H1, accroche, statistiques, CTA (agrandi), confidentialité —
rien d'autre.

**Conteneur centré** : sur desktop et grand desktop, `.diagnostic-hero` centre une colonne de lecture
d'environ 760px (contenu aligné à gauche à l'intérieur) au lieu d'une grille à deux colonnes occupant
le tiers gauche de l'écran. Le cercle décoratif saumon est conservé mais réduit et repositionné pour
ne plus déséquilibrer la composition. CTA agrandi sur desktop (padding renforcé), pleine largeur sur
mobile.

**Ordre de contenu inchangé et confirmé** : Diagnostic → Résultat → Formulaire de rappel / contact
direct → « Comprendre avant d'agir » (3 liens conservés à l'identique) → pied de page — déjà correct
dans la 2.5.0, non modifié ici.

**Non touché, vérifié** : les 12 questions, le barème, le calcul du résultat, `inc/diagnostic.php`
(nonces, honeypot, anti-spam, redirections), les champs du formulaire de rappel, `sessionStorage`
(seuls les noms de conteneurs DOM qu'elle manipule ont changé, sa logique de minimisation et de
nettoyage est identique), les événements Matomo existants et les nouveaux événements de la 2.5.0, la
logique anti-double-soumission, la logique de la pop-in, le header et le footer globaux, le SEO (H1,
title, meta, canonical, breadcrumb, URL — tous identiques).

**Vérification** : 41 nouvelles assertions Playwright sur le rendu PHP réel du template, dont :
absence de scroll et de saut de layout au clic sur le CTA (largeur du conteneur mesurée identique
avant/après), position verticale de la question 1 mesurée dans la même zone que l'ancien hero,
« Quitter le diagnostic » et reprise, CTA visible sans défilement sur 6 résolutions (smartphone
portrait/paysage, tablette portrait/paysage, desktop), les 3 niveaux de résultat + la règle critique,
restitution `sessionStorage` (succès et échec) avec la nouvelle structure de conteneurs, et
non-régression du tracking, de la modale (focus, Escape) et du bouton anti-double-clic. Tous passés.

**Fichiers modifiés :** `page-diagnostic-entreprise-en-difficulte.php`, `page-diagnostic.css`,
`diagnostic.js`, `style.css` (version). `theme.js` non touché dans cette version.

## Version 2.5.0 — refonte UX/UI de la page Diagnostic (landing page de captation)

Évolution de présentation et de parcours uniquement — le moteur du diagnostic (questions, barème,
transmission, protections) n'a pas été réécrit. Détail complet dans le rapport de recette transmis
au cabinet ; résumé ci-dessous.

**Premier écran** : le questionnaire devient l'objet du premier écran (« landing page = diagnostic »)
au lieu d'un simple bloc éditorial suivi d'un CTA plus bas. Le hero conserve le H1, le texte
d'introduction et l'identité graphique existants, et ajoute la promesse chiffrée (12 questions ·
Environ 3 minutes · Résultat immédiat), le CTA « Commencer le diagnostic » et un composant de
prévisualisation (thématiques du questionnaire + frise de progression décorative) qui remplace
l'ancien bloc « Un outil d'orientation ».

**Transition hero → questionnaire** : au clic sur le CTA, le hero se replie (fondu, sans réécriture
du moteur) et la première question apparaît directement en dessous, sans grand saut de défilement.
`renderQuestion()`, `showResult()`, `syncAnswerInputs()` et la validation du formulaire sont
strictement inchangées.

**Responsive** : mobile-first, CTA visible sans défilement significatif sur les résolutions
smartphone courantes (y compris en paysage, cas le plus contraint). La bascule 1/2 colonnes du hero
ne suit plus mécaniquement l'ancien seuil de largeur ≤900px : elle dépend désormais conjointement de
la largeur et de la hauteur disponibles (`(max-width:859px),(max-height:649px)`), pour conserver 2
colonnes sur une tablette en mode paysage tout en repassant à 1 colonne sur tablette portrait et
sur smartphone.

**Modale de confirmation** : après transmission réellement réussie côté serveur (même signal exact
que l'événement `diagnostic_submit` existant : présence de `.diagnostic-server-message.is-success`),
une modale sobre affiche le texte de confirmation exact demandé, avec le numéro du cabinet cliquable.
Piège à focus, fermeture au clavier (Échap) et à la souris, retour du focus sur le résultat à la
fermeture, `inert` appliqué au contenu principal pendant l'ouverture. Aucune modale en cas d'échec.

**Continuité après rechargement** : la transmission reste un cycle POST → redirection → rechargement
complet de la page (inchangé). Une clé `sessionStorage` unique et minimisée (niveau de résultat,
et — uniquement en cas d'échec — les champs de coordonnées déjà saisis) permet de restituer le
résultat et l'état « ✓ Demande transmise » après un succès, ou de pré-remplir le formulaire de
rappel après un échec pour éviter de refaire le questionnaire. Lue une seule fois puis supprimée
immédiatement (aucune donnée conservée au-delà de ce qui est strictement nécessaire, ni au-delà de
la session de l'onglet).

**Anti-doublon et double soumission** : bouton de transmission désactivé dès le premier clic valide
(empêche un second POST en cas de double clic). Aucune modification des protections serveur
existantes (nonce, honeypot, délai anti-bot, rate-limit).

**Tracking — ajouté, rien retiré ni renommé** : cinq nouveaux événements complémentaires aux
événements existants (`diagnostic_submit`, `diagnostic_lead_show`, `contact_phone_click`,
`contact_email_click`, tous inchangés) — `diagnostic_start`, `diagnostic_result` (avec le niveau),
`diagnostic_lead_attempt`, `diagnostic_error` (avec le code d'erreur exact), `diagnostic_related_click`
(avec la destination). Ajout nécessaire d'une règle de suivi téléphonique pour le nouveau numéro
affiché dans la modale (`contact_phone_click` / « Modale confirmation diagnostic »).

**SEO** : aucune modification. H1, introduction textuelle, hiérarchie H2, liens internes
(« Comprendre avant d'agir »), breadcrumb, title/meta/canonical, URL — tous strictement identiques et
présents dans le HTML rendu côté serveur, sans dépendance à l'exécution de JavaScript.

**Fichiers modifiés :** `page-diagnostic-entreprise-en-difficulte.php`, `page-diagnostic.css`,
`diagnostic.js`, `theme.js` (exposition de `spaTrack` + une règle de suivi supplémentaire), `style.css`
(version).

## Version 2.4.15 — correctif du suivi Avocat.fr (avocatfr_click ne se déclenchait pas)

Signalé après test en production de la 2.4.14 : les clics vers `consultation.avocat.fr` étaient bien enregistrés par le suivi natif des liens sortants de Matomo, mais ne déclenchaient jamais notre événement `avocatfr_click`.

**Cause racine** : le placeholder `.avocat-consultingwidget` du thème n'est qu'un point d'ancrage — dès qu'il approche du viewport, `theme.js` charge le script tiers `consultation.avocat.fr/js/consultingwidget.js`, qui transforme ce placeholder en widget interactif réel (menant selon le choix de l'utilisateur vers `/consultation-telephonique/...` ou `/consultation-video/...`). Notre règle de suivi 2.4.14 matchait par classe CSS (`.avocat-consultingwidget`) — une classe propre au thème, sans rapport avec les éléments que le script tiers génère une fois initialisé. Résultat : elle ne matchait plus rien au moment réel du clic. Le suivi natif de Matomo, lui, fonctionne par détection du domaine de destination, indépendamment de la structure DOM — d'où l'écart observé.

**Correctif** :
- Remplacement du matching par classe CSS par un matching par domaine de destination (`consultation.avocat.fr`), dans un écouteur dédié — reproduit le mécanisme de détection de Matomo (par domaine) plutôt que de dépendre de la structure du widget tiers, quelle qu'elle soit.
- Ancienne règle `.avocat-consultingwidget` entièrement supprimée de la table `CLICK_RULES` générique (aucun double comptage possible entre l'ancienne et la nouvelle logique).
- Gestion de la course avec la navigation externe : pour un clic simple dans le même onglet, `preventDefault()` puis envoi de `avocatfr_click` avec callback Matomo ; la navigation n'est déclenchée qu'au premier événement entre ce callback et un filet de sécurité à 300 ms (garantit que le lien fonctionne même si Matomo est indisponible ou bloqué). Aucun blocage ni délai pour Ctrl/Cmd/Shift/clic milieu ou un lien `target="_blank"` : ces cas n'ont aucune course avec le déchargement de la page (nouvel onglet), le suivi y reste strictement fire-and-forget.
- Ajout d'un écouteur `auxclick` dédié : le clic milieu ne déclenche jamais l'événement `click` dans un navigateur (uniquement `auxclick`) — sans cet ajout, ce cas précis serait resté silencieusement non suivi malgré la correction du matching par domaine.

**Non touché, vérifié** : `enableLinkTracking()` de Matomo (suivi natif des liens sortants) n'est ni modifié ni contourné — notre événement s'ajoute à ce suivi, il ne le remplace pas. Les autres trackers validés en production (téléphone, e-mail, diagnostic, modale contact) : aucun changement de code, retestés à l'identique.

**Vérification** : 36 scénarios Playwright (19 de non-régression sur les trackers déjà validés + 17 nouveaux ciblant spécifiquement Avocat.fr), dont : clic simple même onglet avec ordre `trackEvent` → navigation vérifié par horodatage des événements réseau, comportement du filet de sécurité à 300 ms (callback Matomo simulé indisponible), Ctrl+clic, clic milieu (via `auxclick`), lien `target="_blank"`, absence de double événement, et les trois contextes de page (Home / page expertise / diagnostic). Tous passés.

**Fichiers modifiés :** `theme.js`, `style.css` (version).

## Version 2.4.14 — suivi des conversions Matomo (téléphone, e-mail, diagnostic, Avocat.fr, modale contact)

Mise en place du tracking de conversion Matomo, absent jusqu'ici du thème (aucun code `_paq`/`trackEvent` n'existait avant cette version — vérifié par recherche exhaustive dans tout le thème).

Audit préalable du code réel du plugin Matomo officiel (`matomo-org/matomo-for-wordpress`) : le plugin initialise sa propre file `window._paq` (en `wp_head` ou `wp_footer` selon réglage admin, dans les deux cas avant `theme.js`, chargé en pied de page à une priorité postérieure) et l'intégration Complianz pilote le consentement Matomo nativement via cette même file (`_paq.push(['forgetCookieConsentGiven'])`). Conséquence : le tracking ajouté ici se contente de pousser des commandes `trackEvent` dans cette file existante — aucun tracker n'est instancié, aucun cookie n'est posé, le consentement Complianz s'applique donc sans contournement et sans code supplémentaire pour le gérer.

**Événements suivis** (tableau Category/Action/Name complet et procédure de création des objectifs Matomo transmis séparément au cabinet) :
- `contact_phone_click` et `contact_email_click` (Category `Contact`) : clic réel sur un lien `tel:`/`mailto:` dans le menu, le hero, le bloc aside « Échangez d'abord », la section contact de bas de page et le panneau du diagnostic — seul le clic est tracké, jamais le simple affichage. Les liens `mailto:` incidents (mention RGPD, message d'erreur du diagnostic hors cas de repli explicite) sont volontairement exclus.
- `diagnostic_submit` (Category `Diagnostic`) : uniquement quand `.diagnostic-server-message.is-success` est présent au chargement, c'est-à-dire seulement après un envoi serveur réellement réussi (nonce, honeypot, rate-limit et validation des réponses tous passés côté PHP) — jamais sur un simple clic sur le bouton d'envoi. Anti-doublon sur rechargement (F5) via l'API Navigation Timing (`performance.getEntriesByType('navigation')[0].type === 'reload'`), sans modifier l'URL et sans sous-compter un second envoi réellement distinct dans la même session.
- `avocatfr_click` (Category `Avocat.fr`) : clic sur le widget de consultation en ligne.
- `contact_modal_open` / `contact_modal_contact` (Category `Modale contact`, micro-conversions) : ouverture du panneau « Échanger avec le cabinet » et clic sur son lien « Contacter le cabinet » — scopé précisément à ce menu pour ne pas capter le lien `#contact` indépendant du hero desktop.
- `diagnostic_lead_show` (Category `Diagnostic`, micro-conversion) : clic sur « Être recontacté par le cabinet » dans le résultat du diagnostic.

**Implémentation** : centralisée dans `theme.js` (aucun template modifié), avec un contexte de page (`home`/`expertise`/`postulation`/`diagnostic`) transmis une seule fois depuis `inc/setup.php` via `wp_localize_script` pour qualifier l'emplacement (Event Name) sans dupliquer de logique par gabarit.

**Vérification** : 19 scénarios Playwright rejouant le HTML réel de chaque emplacement (clic individuel de chaque lien, exclusions RGPD/erreur, ouverture/fermeture de la modale sans double déclenchement, chargement direct vs rechargement F5 de la page de succès du diagnostic) — tous passés. `php -l` propre sur les fichiers PHP touchés.

## Version 2.4.13 — cadrage du portrait de la home, tête légèrement coupée en desktop large

Signalé sur écran large (desktop) : le haut des cheveux du portrait touchait le bord supérieur de
la photo. Cause : `.hero-photo>img{object-position:center 12%}` — sur les largeurs où `.hero-photo`
prend une forme large/peu haute (le conteneur s'élargit avec le viewport, sa hauteur reste bornée
par le contenu du texte du hero), `object-fit:cover` doit rogner fortement en hauteur, et un ancrage
vertical à 12% ne laissait quasiment aucune marge au-dessus de la tête sur la photo source (environ
4% de marge naturelle au-dessus des cheveux).

**Correctif** : ancrage vertical réduit à `4%` (au lieu de `12%`), pour la règle de base
(desktop, non concernée par les surcharges `≤900px`/`≤560px` déjà distinctes et déjà correctes,
non modifiées). `.hero-photo` n'est utilisé que sur la home (`front-page.php`) — aucune autre page
concernée.

**Vérification** — rendu réel (Chromium, CSS du thème) à 1440/1920/2560px (desktop, y compris la
largeur très large où le rognage était le plus sévère) : marge visible et confortable au-dessus de
la tête sur les trois. Non-régression confirmée à 375px et 768px (surcharges mobile/tablette
inchangées, déjà correctes) : rendu identique à avant.

**Fichiers modifiés :** `style.css` (1 valeur + version).

## Version 2.4.12 — exclusion Smush du portrait LCP + libellé « Sauvegarde & redressement judiciaire »

PageSpeed déjà à 100/100 partout ; pas d'optimisation artificielle recherchée. Diagnostic mené
avant toute modification, comme demandé (voir échange précédent) :

- **Déclaration du portrait dans le thème déjà correcte** : `loading="eager"`,
  `fetchpriority="high"`, dimensions intrinsèques fixées — vérifié inchangé.
- **Smush réécrivait néanmoins l'image en `data-src`/`class="lazyloaded"`**, indépendamment de
  `loading="eager"`, via son mécanisme de lazy-load qui s'applique par défaut à toute image sauf
  exclusion explicite. Mécanisme d'exclusion officiel confirmé (documentation WPMU DEV) : filtre
  `smush_skip_image_from_lazy_load($skip, $src, $image)`.
- **`srcset`/`sizes` volontairement non implémentés** : l'image n'est pas un attachement WordPress
  (fichier statique du thème), donc aucune génération automatique de variantes n'est disponible.
  Gain potentiel chiffré par PageSpeed (~15 Kio, cas mobile le plus défavorable) jugé disproportionné
  par rapport à la complexité et à la charge de maintenance (fichiers supplémentaires à générer et
  tenir à jour) sur un site déjà à 100/100 avec un chemin critique de 197 ms. Implémentation à
  fichier unique conservée.

**Correctif** : nouveau fichier `inc/smush.php`, filtre `smush_skip_image_from_lazy_load` scopé
exclusivement à l'URL exacte de `portrait-saint-pere-tenue-pro-v1.webp` (comparaison stricte,
aucun autre `<img>` du site affecté). Inerte si Smush n'est pas actif (simple `add_filter`, aucune
dépendance à une classe Smush).

**Vérification** — harnais PHP exécutant réellement `inc/smush.php` contre le contrat de filtre
documenté par WPMU DEV : image du portrait exclue (`skip=true`), toute autre image inchangée
(`skip=false`), comparaison stricte du schéma (une URL `http://` n'est pas confondue avec la
véritable URL `https://`), un `$skip` déjà vrai n'est jamais écrasé. Accès au code source réel de
Smush non disponible dans cet environnement (plugin propriétaire non clonable) — vérification
faite contre le contrat documenté, à confirmer empiriquement sur le HTML généré après déploiement.
Rendu vérifié (Chromium, desktop et mobile) : `src` présent directement dans le HTML, aucune
`data-src` ni classe de lazy-load dans le balisage du thème, polices chargées
(`document.fonts` → `loaded`), aucune erreur console attribuable au thème, image affichée
correctement, dimensions intrinsèques inchangées (aucun impact CLS).

**Modification de contenu** : dans « Domaines d'intervention » de la home, « Sauvegarde &
redressement » → « Sauvegarde & redressement judiciaire » (`<h3>` et `aria-label` du lien associé,
mis à jour en cohérence). Lien, destination, icône et reste de la carte inchangés.

**Non touché, vérifié** : le correctif Mixed Content/Hummingbird de la 2.4.11 (`fonts.css`,
`inc/setup.php`) — diff strictement vide sur ces deux fichiers.

**Fichiers modifiés :** `inc/smush.php` (nouveau), `functions.php` (require), `front-page.php`
(2 chaînes de texte), `style.css` (version).

## Version 2.4.11 — cause racine du Mixed Content intermittent localisée et corrigée (fonts.css)

Suite à l'investigation complète (voir échanges précédents) : la cause racine du Mixed Content
intermittent sur les deux fontes locales n'était ni les preloads (corrigés en 2.4.10) ni une
mauvaise détection HTTPS sur les visites réelles (`is_ssl()` confirmé correct par
`diag-headers.php` en production). Elle a été localisée avec certitude par extraction du rapport
Lighthouse brut (`network-requests.details.debugData.initiators`, confirmant que les deux requêtes
HTTP bloquées étaient initiées par le CSS combiné Hummingbird) puis confirmée par récupération
FTP du fichier physique fautif sur le serveur : ses deux `@font-face` contenaient bien
`url("http://saint-pere-avocat.fr/...")`.

**Mécanisme exact, tracé dans le code source de Hummingbird** : lors de la combinaison de
`fonts.css` dans son bundle, Hummingbird (`class-minify-group.php:965`) appelle
`WP_Hummingbird_CSS_UriRewriter::prepend($content, trailingslashit(dirname($src)))`, où `$src` est
l'URL du handle `spa-fonts` telle qu'enregistrée par WordPress au moment du scan — donc dépendante
de `get_template_directory_uri()` et de `is_ssl()` **à cet instant précis**, potentiellement
différent d'une vraie visite HTTPS. La fonction `prepend()` **concatène littéralement** ce préfixe
devant chaque `url()` relatif de `fonts.css` (`_processUriCB()`, `class-uri-rewriter.php`), sans
jamais revalider le schéma — d'où l'URL absolue `http://` figée dans le bundle généré, malgré un
`fonts.css` source parfaitement correct.

Point vérifié dans ce même code source : `_processUriCB()` **ignore intégralement** toute URI
commençant déjà par `/` (`if ('/' !== $uri[0] ...)`) — elle n'est alors ni concaténée ni modifiée,
quel que soit `is_ssl()` au moment du traitement. C'est la base du correctif.

**Correctif (limité à `fonts.css`, comme demandé)** : les deux `url()` relatifs
(`assets/fonts/...`) remplacés par des chemins **root-relative**
(`/wp-content/themes/saint-pere-avocat/assets/fonts/...`). Aucun autre fichier fonctionnel du
thème touché. Le filtre global `set_url_scheme` (qui corrigerait la même classe de défaut pour le
reste du thème) reste volontairement écarté pour l'instant, à n'envisager que si un autre cas réel
l'exige.

**Vérification** — exécution réelle (pas de relecture seule) de la véritable classe
`WP_Hummingbird_CSS_UriRewriter` de Hummingbird (clonée depuis le mirror source déjà utilisé pour
l'investigation) contre le nouveau `fonts.css`, dans les deux scénarios `$src` correct et `$src`
fautif (`http://`, reproduction exacte du bug de production) : dans les deux cas, les deux chemins
root-relative ressortent **strictement inchangés**, aucune occurrence de `http://` ni `https://`
ajoutée — immunité prouvée quel que soit l'état de `is_ssl()` au moment de la génération du bundle.
Rendu vérifié séparément (Chromium, chemins root-relative servis depuis la structure réelle
`/wp-content/themes/saint-pere-avocat/`) : les deux polices chargent (`document.fonts` →
`status: loaded`), rendu visuel identique, aucune police de repli.

**Fichiers modifiés :** `fonts.css` (2 lignes), `style.css` (version).

**Reste à valider en production, sans nouvelle purge entre les deux contrôles** : purge
Hummingbird pour éliminer le bundle actuellement fautif, contrôle PageSpeed immédiat, puis un
second contrôle à J+1/J+2 — le bug ne sera considéré résolu qu'après ce second contrôle sans
réapparition.

## Version 2.4.10 — Mixed Content intermittent sur les fonts locales (défense en profondeur)

PageSpeed instable en production (100 puis 73 en Bonnes pratiques, sans changement du thème) :
Lighthouse remontait les deux fontes locales (`cormorant-garamond-latin.woff2`,
`montserrat-latin.woff2`) parfois demandées en `http://` depuis une page HTTPS (Mixed Content
bloqué par Chrome). Diagnostic mené sur le code source réel (thème + WordPress core 6.7 cloné pour
vérification), pas par supposition — voir le diagnostic complet donné avant ce correctif.

**Cause racine (confirmée dans le code source WordPress, hors thème)** : les deux `<link
rel="preload">` de fonts (`inc/setup.php`) utilisaient `get_template_directory_uri()`, dont le
schéma est résolu par `set_url_scheme(WP_CONTENT_URL)` → `is_ssl() ? 'https' : 'http'`
(`wp-includes/link-template.php`). Or `is_ssl()` (`wp-includes/load.php`) ne vérifie que
`$_SERVER['HTTPS']`/`SERVER_PORT`, jamais `X-Forwarded-Proto` — l'en-tête qu'un proxy/CDN
terminant le TLS en amont (infrastructure OVH) utilise pour signaler le protocole réel. Sans
traduction de cet en-tête, `is_ssl()` peut retourner faux ponctuellement selon la requête, ce qui
explique l'intermittence sans aucun changement du thème.

**Correctif de fond — côté serveur, hors thème** : ajout dans `wp-config.php` (non inclus dans ce
dépôt, fichier serveur) d'un snippet traduisant `X-Forwarded-Proto` vers `$_SERVER['HTTPS']`, à
appliquer par l'hébergeur. Non fourni dans cette version (hors périmètre du thème) — voir la
recette de validation fournie séparément.

**Correctif thème — défense en profondeur, appliqué dans cette version** : les deux `<link
rel="preload">` de fonts utilisent désormais `set_url_scheme(..., 'relative')` (fonction WordPress
native) plutôt qu'une URL absolue — le schéma et l'hôte sont retirés quel que soit ce que `is_ssl()`
a répondu, ne laissant qu'un chemin racine-relatif (`/wp-content/themes/...`) que le navigateur
résout toujours dans le protocole de la page courante. Immunise ces deux ressources même si le
correctif serveur venait à manquer un jour. **Limité strictement à ces deux preloads** — aucun
autre `wp_enqueue_style`/`wp_enqueue_script` n'a été touché, dans l'attente de la validation du
correctif serveur (même défaut theoriquement présent partout où `get_template_directory_uri()` est
utilisé, mais non traité ici sur demande explicite).

**Compatibilité Hummingbird** : ces deux balises sont échoées manuellement dans `wp_head`, jamais
enqueue via `wp_enqueue_style` — Hummingbird (qui combine/minifie les `<link rel="stylesheet">`
enqueue) ne les traite jamais. Aucun risque de conflit avec la minification/combinaison.

**Méthode de vérification** — harnais PHP exécutant la vraie fonction du thème contre une
reproduction fidèle des fonctions WordPress réelles impliquées (`is_ssl`, `set_url_scheme`,
`content_url`, `esc_url`, copiées depuis le code source WordPress 6.7 cloné pour l'occasion), dans
deux scénarios : `is_ssl()` correct, et `is_ssl()` faux à tort (reproduction exacte du bug de
production). 9/9 assertions passées, dont la confirmation que l'ancien code produisait réellement
`http://saint-pere-avocat.fr/...` dans le scénario `is_ssl()` faux (bug reproduit), et que le
nouveau code produit une sortie strictement identique (chemin relatif, aucun schéma) dans les deux
scénarios — immunité prouvée, pas supposée.

**Fichiers modifiés :** `inc/setup.php`, `style.css` (version).

## Version 2.4.9 — correction responsive des H1 éditoriaux (interligne trop serré)

Contrôle sur iPhone/iPad en production : sur les H1 à plusieurs lignes/styles (romain noir +
italique saumon), certaines lignes se touchaient ou quasi-touchaient en mobile — « judiciaire »
sous « Liquidation », « à Saint-Étienne » sur la page Postulation, et la home autour de la partie
italique. Cause identifiée par lecture directe du CSS, pas par supposition : la règle de base
`h1{line-height:.9}` (`style.css`) — pertinente en desktop où les titres tiennent en 1-2 lignes
larges — n'était jamais assouplie pour `.expertise-hero h1` (13 pages) ni `.diagnostic-hero h1`,
et seulement partiellement pour `.hero-text h1` (home, uniquement ≤560px, alors que le hero passe
en deux colonnes resserrées dès ≤1100px). Plus une entête s'enroule sur davantage de lignes en
mobile/tablette, plus l'empilement à interligne 0,9 devient serré, jusqu'au quasi-contact.

Corrigé par interligne responsive, sans toucher aux tailles de police ni au concept graphique
(conformément à la demande — pas de réduction générale de la typographie) :

- `.expertise-hero h1{line-height:1.08}` ajouté dans le point de rupture existant `≤900px`
  (`style.css`) — couvre les 13 pages qui réutilisent ce composant (Postulation, Liquidation,
  Sauvegarde, Contentieux, etc.), aucun correctif page par page.
- `.hero-text h1{line-height:1.08}` ajouté dans le point de rupture `1100px-901px` (home, jusque-là
  sans aucun réglage d'interligne à cette largeur — c'était le cas le plus serré, un « É » de
  « Saint-Étienne » touchait quasiment la ligne du dessus à 1024px).
- `.hero-text h1{line-height:.94→1.1}` (≤560px, home) et `.diagnostic-hero h1{line-height:1.08}`
  ajouté dans le point de rupture `≤900px` de `page-diagnostic.css` (même défaut, même correctif).
- Règle de base desktop (`h1{line-height:.9}`, >1100px) **non modifiée**.

**Non touché, vérifié plutôt que supposé** : `.legal-hero h1` (Mentions légales, CGU, etc.) a son
propre `line-height:.98` déjà généreux et des titres courts qui ne se resserrent pas même à
320px — laissé tel quel plutôt que retouché par principe.

**Méthode de vérification** — rendu réel (CSS du thème + Chromium) reconstituant fidèlement la
mise en page à deux colonnes de `.hero`/`.expertise-hero` (pas un `<h1>` isolé, qui aurait donné
des largeurs de ligne fictives), sur les 6 largeurs demandées (320/375/390/430/768/1024px) et sur
l'ensemble des titres réels du thème (home, Postulation, Liquidation, Sauvegarde, Contentieux,
Prévention, Diagnostic, Mentions légales/CGU — pas seulement les 3 pages des captures). Zéro
dépassement horizontal détecté (`scrollWidth` vs `clientWidth`) sur aucun cas testé, avant comme
après. Non-régression desktop confirmée strictement : rendu à 1440px comparé avant/après,
**pixel pour pixel identique** (bbox de diff vide), pas seulement « visuellement similaire ».

**Fichiers modifiés :** `style.css`, `page-diagnostic.css` (version dans `style.css`).

## Version 2.4.8 — correction de domaine Schema.org : serviceType déplacé sur une entité Service

SEMrush remontait sur toutes les pages : « `serviceType` n'est pas reconnue par le vocabulaire
Schema.org » sur le nœud `Organization`/`LegalService`. Vérifié contre le vocabulaire source
officiel (`schemaorg/schemaorg`, `data/schema.ttl`, dépôt de référence cloné pour l'occasion) :
`serviceType` a pour `domainIncludes` exclusivement `Service` — jamais `Organization` ni
`LegalService`. Le message de SEMrush était fondé, l'implémentation 2.4.5 était sémantiquement
incorrecte.

La définition officielle de `LegalService` documente elle-même la solution : *« As a LocalBusiness
it can be described as a provider of one or more Service(s) »*. Corrigé en conséquence :

- **`serviceType` retiré** du nœud `Organization`/`LegalService`.
- **Un unique nœud `Service`** ajouté au graphe (`inc/schema-yoast.php`, même mécanisme que la
  `Person` — pièce `wpseo_schema_graph_pieces`), `@id` stable et déterministe
  (`#service-juridique`), portant les 6 valeurs métier `serviceType` conservées à l'identique (une
  seule entité `Service` avec un `serviceType` en liste, pas 6-7 entités séparées : `serviceType`
  accepte un `Text` en range, rien n'impose une entité par prestation, et le graphe reste simple).
  Relié par `Service.provider` → `@id` exact du nœud `Organization`/`LegalService` (`provider` a
  `Service` dans son `domainIncludes` et `Organization` dans son `rangeIncludes` — vérifié).
- **`areaServed` non touché**, conservé uniquement sur `Organization`/`LegalService` (son
  `domainIncludes` couvre `Organization` *et* `Service` : pas de faute à corriger, et pas dupliqué
  sur le nouveau nœud pour ne pas alourdir le graphe inutilement).
- **Non touché, vérifié** : `Person`, `founder`, `worksFor`, `sameAs` (Person et
  Organization/LegalService), `addressRegion`, tout le reste du graphe Yoast (`WebSite`,
  `WebPage`, `BreadcrumbList`, metas, canonicals).

**Méthode de vérification** — même approche qu'en 2.4.5 (exécution réelle, pas de simulation) :
harness PHP qui charge pour de vrai `inc/settings.php` et `inc/schema-yoast.php`, rejoue
`wpseo_schema_organization` et `wpseo_schema_graph_pieces` avec de vrais `add_filter`/
`apply_filters`, contre le nœud `Organization` réel de production. 24 assertions automatisées :
un seul `Organization`/`LegalService`, une seule `Person`, un seul `Service` ; `serviceType`
absent d'`Organization` et présent uniquement sur `Service` avec les 6 valeurs métier identiques ;
`Service.provider.@id` égal exactement à `Organization.@id` ; aucune autre clé du graphe modifiée.
Complété par un contrôle automatisé indépendant : les 19 propriétés du graphe cible (tous nœuds
confondus) vérifiées une à une contre le `domainIncludes` réel de `schema.ttl` et la hiérarchie de
classes RDF (`rdfs:subClassOf`) — zéro propriété hors domaine détectée.

Restent à confirmer après déploiement en production (nécessitent le crawl réel) : que SEMrush ne
relève plus l'alerte, et validation croisée Schema.org Validator / Google Rich Results Test sur
l'URL en production.

**Fichiers modifiés :** `inc/schema-yoast.php`, `style.css` (version).

## Version 2.4.7 — correction du cadrage de la photo sur la page postulation

En production, la photo ajoutée en 2.4.6 tronquait le haut de la tête sur desktop, et le cadrage
était trop serré sur mobile. Cause : le composant partagé `.cabinet-photo` (utilisé aussi sur la
page d'accueil) recadre en `object-fit:cover` avec un centrage vertical, dans un cadre `.cabinet-
visual` beaucoup plus large que haut (jusqu'à 46% de largeur d'écran pour seulement 660px de haut
sur desktop) — pensé pour la photo, plus « paysage », de la page d'accueil. La nouvelle photo,
cadrée serré près du haut du visage, ne laissait quasiment aucune marge au-dessus de la tête : un
recadrage centré rognait directement dans les cheveux/le front sur les écrans larges.

Correctif ciblé, sans toucher au composant partagé ni à la photo de la page d'accueil : nouvelle
classe `cabinet-photo-postulation` sur cette seule image, avec `object-position` ancré en haut
(`54% top`) plutôt que centré — le recadrage se fait désormais toujours à partir du bas (mains,
dossier) et ne rogne jamais la tête. Vérifié par rendu réel (CSS du thème + capture d'écran) à
1920px, 1440px, 834px et 375/430px : tête entièrement visible avec marge sur tous les gabarits.

## Version 2.4.6 — photo de Juliette Saint-Père sur la page postulation

Ajout d'une section photo sur la page « Avocat postulant à Saint-Étienne », entre le hero et le
contenu de l'article, pour renforcer le réassurance auprès des confrères plaidants qui confient un
dossier de postulation. Réutilise tel quel le composant `.cabinet` / `.cabinet-visual` /
`.cabinet-copy` déjà défini globalement dans `style.css` pour la page d'accueil — aucune CSS ni
enqueue spécifique à la page n'a été ajoutée.

La photo fournie représentait Juliette Saint-Père en robe d'avocate, tenue dont la représentation
est interdite sur le site d'un avocat ; le cabinet a fourni une version retouchée en tenue
professionnelle (blazer, dossier sous le bras), visage inchangé, convertie en WebP
(`assets/juliette-saint-pere-postulation-v1.webp`, 900×919, ~50 Ko) à partir du fichier original.

## Version 2.4.5 — enrichissement du graphe Schema.org de Yoast SEO

Suite à l'audit des données structurées du 20/08/2026 : le cabinet est représenté par Yoast SEO
(actif en production) comme une simple `Organization`, sans entité `Person` distincte pour
Juliette Saint-Père, sans relation `founder`/`worksFor`. Nouveau fichier `inc/schema-yoast.php`,
inerte tant que Yoast (ou un plugin compatible exposant les mêmes filtres) n'est pas actif —
n'introduit jamais de graphe parallèle, enrichit exclusivement les pièces existantes de Yoast via
ses filtres officiels (`wpseo_schema_organization`, `wpseo_schema_graph_pieces`) :

- **Double typage `["Organization", "LegalService"]`** sur le nœud existant — Yoast continue de
  le reconnaître comme `Organization` (aucune relation interne cassée), Google et les
  consommateurs schema.org lisent en plus le typage spécialisé.
- **`Person` distincte pour Juliette Saint-Père**, reliée par `founder` (sur le cabinet) et
  `worksFor` (sur la personne). `url` pointe vers l'accueil (page qui contient sa seule
  biographie complète sur le site — aucune page dédiée n'existe, et la page candidate
  « Pourquoi faire appel au cabinet » s'est révélée rédigée à la troisième personne
  institutionnelle après relecture de son contenu réel). `alumniOf` : Université Jean Moulin
  Lyon III, National University of Ireland Maynooth, HEC Paris — données déjà publiées sur la
  page d'accueil, aucune invention.
- **`addressRegion: Auvergne-Rhône-Alpes`**, **`areaServed`** (Saint-Étienne + Loire, entités
  typées), **`serviceType`** (6 domaines d'intervention, en liste plate — pas d'entités `Service`
  séparées, choix argumenté pour ne pas complexifier le graphe sans bénéfice Google mesurable).
- **`sameAs` séparés** : `Person.sameAs` conserve LinkedIn personnel + Avocat.fr ; nouveau réglage
  admin *Fiche Google Business Profile* pour `LegalService.sameAs` uniquement (aucune identité
  personnelle réutilisée sur l'entité cabinet).

**Non touché, vérifié** : `SearchAction` du `WebSite`, logo/image existants, `BreadcrumbList`,
metas et canonicals Yoast, `@id` existants — le nouveau code n'ajoute que des clés à un nœud
existant et une pièce entièrement nouvelle, sans jamais réécrire une propriété déjà présente.
Aucun changement de rendu front (le JSON-LD ne s'affiche jamais visuellement) ni de performance.

**Méthode de vérification** — le boot complet du plugin Yoast 28.3 s'est révélé bloqué dans cet
environnement de test par son conteneur d'injection de dépendances Symfony, compilé lors du
build de release de Yoast et non reproductible sans son outillage interne. Vérification réalisée
autrement, sans rien deviner : code source exact de Yoast SEO 28.3 obtenu (tag exact cloné depuis
son dépôt public), noms de filtres et contrat de `Abstract_Schema_Piece` confirmés contre ce code
réel ; le nouveau fichier exécuté pour de vrai contre WordPress réel (fonctions du thème réelles,
pas de simulation) et contre l'algorithme réel de fusion/validation de Yoast (`validate_type()`,
copié à l'identique depuis le code source) appliqué au nœud `Organization` exact extrait du code
source de production. Un bug réel a été détecté et corrigé pendant cette vérification : les
fonctions du fichier se liaient à la compilation PHP indépendamment du retour anticipé prévu pour
les désactiver quand Yoast est inactif (la classe, elle, en était protégée) — corrigé en
enveloppant tout le fichier dans un bloc conditionnel explicite plutôt qu'un retour anticipé.
Confirmé : fichier totalement inerte (aucune fonction, aucune classe définie) quand Yoast est
inactif, sans risque d'erreur fatale si Yoast est un jour désactivé.

**Fichiers modifiés :** `inc/schema-yoast.php` (nouveau), `functions.php`, `inc/settings.php`
(nouveau réglage `google_business_url`), `style.css` (version).

## Version 2.4.4 — audit de performance complet : forced reflow, logos vectorisés, packaging stabilisé

Suite à l'audit de performance complet des 18 pages publiques (desktop + mobile), demandé en
préparation de cette version. Trois correctifs vérifiés, ciblés sur des causes racines réelles
(voir le rapport d'audit pour la méthodologie, l'inventaire complet des problèmes et les points
volontairement laissés de côté) :

- **Forced reflow (theme.js)** — l'appel initial `updateHeader()` (mise à jour de la classe
  `.is-scrolled` de l'en-tête) s'exécutait de façon synchrone dès le chargement du script, avant
  que la mise en page de la page n'ait fini de se stabiliser. La lecture de `window.scrollY` à cet
  instant précis forçait un recalcul de mise en page synchrone mesuré à ~57 ms sur la page
  Prévention — chiffre qui correspond exactement à celui remonté par PageSpeed sur le bundle
  agrégé Hummingbird. Remonté jusqu'au code source réel (Hummingbird n'était qu'un agrégateur, pas
  la cause), confirmé par trace Chrome brute (367 objets de mise en page invalidés au moment de
  l'appel). Corrigé en différant cet appel initial à la frame d'animation suivante
  (`requestAnimationFrame`), exactement comme le fait déjà tout appel ultérieur de la même
  fonction sur scroll. Aucun changement de comportement : l'en-tête réagit toujours au scroll de
  façon identique, un cran plus tard (~16 ms, imperceptible). Vérifié éliminé (score de l'audit
  Lighthouse `forced-reflow-insight` passé de partiellement en échec à 1/1 sur toutes les pages
  testées, trace brute sans pile JS forçant la mise en page restante).
- **Logo `logo-saint-pere.png` vectorisé en SVG** — fichier de 500×500 px (49,9 Kio) affiché entre
  58×58 et 102×102 px selon les points de rupture (jusqu'à 8,6× surdimensionné). Le monogramme
  étant en aplat d'une seule couleur, il a été vectorisé fidèlement (7,2 Kio, écart pixel moyen
  0,4/255 par rapport à l'original, accent du « È » compris) et rendu net à n'importe quelle
  résolution/DPR. Utilisé dans l'en-tête et le pied de page ; l'URL du logo dans les données
  structurées (JSON-LD) reste en PNG, jamais chargée par le navigateur et préférée par les outils
  de validation Google.
- **Logo partenaire `logo-seneque.png` vectorisé en SVG** — même constat (1938×660 px/18,6 Kio
  affiché à 150–180 px de large, ~11× surdimensionné), même méthode (aplat vert unique, écart
  pixel moyen 0,57/255), fichier PNG supprimé après vérification qu'il n'était plus référencé nulle
  part.
- **Packaging du thème** — le dossier racine du ZIP de livraison reste strictement
  `saint-pere-avocat/`, sans jamais inclure le numéro de version, pour toutes les versions futures.
  Mécanisme de mise à jour WordPress testé réellement (installation d'une version antérieure du
  même thème puis remplacement via le même flux que Apparence → Thèmes → Ajouter → Téléverser) :
  remplacement en place confirmé, aucune donnée perdue (pages, menus, réglages, métadonnées de
  migration).

**Non corrigé volontairement** — `wp-block-library` (CSS cœur de WordPress, ~110 Kio, jamais mis en
cache par la suite) a été testé pour un dequeue global : régression visuelle réelle et mesurée
constatée sur `.wp-block-separator.has-alpha-channel-opacity` (rendu du séparateur visuellement
différent, un simple `<hr>` plein au lieu du filet fin d'origine). Corriger cela proprement
nécessiterait de maintenir une extraction manuelle du sous-ensemble de règles réellement utilisées
(risque de dérive silencieuse à chaque évolution du contenu) pour un gain marginal et mis en cache
par le navigateur — laissé de côté conformément à la consigne de ne pas dégrader le rendu pour
gagner quelques points de score. Voir le rapport d'audit pour le détail complet des points
classés A/B/C/D.

**Fichiers modifiés :** `theme.js`, `template-parts/site-header.php`,
`template-parts/site-footer.php`, `assets/logo-saint-pere.svg` (nouveau),
`assets/logo-seneque.svg` (nouveau, remplace `assets/logo-seneque.png`, supprimé), `style.css`
(version).

Testé : recette Playwright desktop/tablette/mobile (52/53 vérifications automatiques, l'unique
échec restant est un appel réseau externe vers `consultation.avocat.fr` bloqué par le pare-feu de
sortie de l'environnement de test, sans lien avec le thème ni avec les correctifs de cette
version) — navigation, menu burger, accordéon FAQ, CTA de contact flottant, parcours complet du
diagnostic en 12 questions avec retour arrière et modification de réponse, absence d'erreur
JS/réseau, absence de débordement horizontal. Comparaison pixel par avant/après sur les pages
d'accueil et Prévention (desktop/tablette/mobile) : écarts résiduels à 0,002–0,005/255,
exclusivement localisés aux deux logos remplacés (anticrénelage vecteur vs. matriciel), aucune
autre différence sur la page.

## Version 2.4.3 — correction des 4 derniers cercles d'icône surdimensionnés

Suite du correctif d'icônes de la 2.4.2 : même effet de bord (taille CSS en pixels qui
dimensionnait auparavant le cercle autour d'un glyphe texte, et dimensionne désormais
directement le SVG) corrigé sur les 4 derniers cercles concernés, avec la même méthode
(`padding` restituant la proportion d'origine, aucun changement de balisage) :

- `.faq-cta .material-symbols-outlined` (accueil, encart FAQ) — icône ramenée de 42px à 24px
  dans un cercle de 42px (padding 9px).
- `.compare-icon .material-symbols-outlined` — icône ramenée de 48px à 24px dans un cercle de
  48px (padding 11px). Règle actuellement inutilisée dans le thème (aucun gabarit ni contenu
  n'y fait appel) : corrigée par cohérence, sans page à vérifier visuellement.
- `.video-consent button>.material-symbols-outlined` (bouton de lecture de la vidéo TL7, page
  « Des solutions... ») — icône ramenée de 76px à 42px dans un cercle de 76px (desktop,
  padding 17px) et de 64px à 36px dans un cercle de 64px (variante mobile ≤560px, padding 14px).

**Fichiers modifiés :** `style.css`, `page-video.css`.

Testé par comparaison pixel avec le rendu d'origine (police, avant le passage au SVG de la
2.4.2) : différences résiduelles limitées à l'anticrénelage (SVG légèrement plus contrasté
qu'un glyphe de police, phénomène déjà documenté en 2.4.2, sans impact visuel réel), aucune
différence de taille ni de position. Aucun débordement horizontal ni erreur JS constaté sur
l'accueil et la page vidéo en desktop/tablette/mobile.

## Version 2.4.2 — pictogrammes en SVG local (fin de la dépendance à une police d'icônes)

**Fichiers modifiés :** `inc/icons.php` (nouveau), `inc/blocks.php`, `inc/page-fields.php`,
`functions.php`, `theme.js`, `fonts.css`, les 18 gabarits `page-*.php` et `front-page.php` qui
affichent des icônes, `template-parts/site-header.php`, `style.css` (numéro de version).
Suppression de `assets/fonts/material-symbols-outlined.woff2` (devenu inutile).

- **Cause du bug signalé** : le thème dépendait d'une police à ligatures auto-hébergée
  (« Material Symbols Outlined », `assets/fonts/material-symbols-outlined.woff2`) qui
  transformait un nom d'icône en texte (`shield`, `call`...) en pictogramme. Un nettoyage de
  dépendances a supprimé ce fichier en production, faisant réapparaître le texte brut à la
  place des icônes sur toutes les pages.
- **Correctif** : les 21 pictogrammes utilisés dans le thème sont désormais un sprite SVG
  local (`inc/icons.php`), injecté une seule fois en tête de page. Chaque icône est un
  `<svg class="material-symbols-outlined"><use href="#icon-NOM"></use></svg>` — la classe est
  conservée à l'identique, donc toutes les règles CSS existantes qui la ciblent (couleur via
  `color`, taille via `font-size`, selon le contexte) continuent de s'appliquer sans
  modification. Les tracés SVG sont les contours exacts de l'ancienne police, extraits une
  fois pour toutes avant sa suppression (fontTools), afin de conserver un rendu visuel
  strictement identique.
- Plus aucune police n'est chargée pour les icônes : aucune requête réseau, aucun risque de
  dépendance manquante à l'avenir. Ne réintroduit ni Colibri ni aucune autre dépendance
  externe.
- `theme.js` mis à jour : les échanges d'icône pilotés en JavaScript (menu ↔ fermer, bulle de
  contact ↔ fermer, icônes générées dans le panneau de contact flottant) changent maintenant
  la cible du `<use>` au lieu du texte de l'ancien `<span>`.
- Aucun changement de texte, de structure de bloc ni de comportement autre que le rendu des
  icônes.
- Testé : comparaison visuelle avant/après par capture d'écran (icônes rendues à l'identique,
  position et taille inchangées) sur les pages d'expertise, l'accueil, l'autodiagnostic et les
  pages légales, en desktop/tablette/mobile ; bascule menu/fermeture et panneau de contact
  flottant vérifiées ; absence totale de nom d'icône en texte confirmée même en bloquant
  explicitement tous les fichiers de police (woff/woff2/ttf) au niveau réseau.

### Complément 2.4.2 — adaptation au nouveau slug de la page Autodiagnostic + correctif d'icône

**Cause du blocage** : le slug de la page a été volontairement changé en base
(`autodiagnostic-entreprise-difficulte` → `diagnostic-entreprise-en-difficulte`, pour le SEO).
Comme le thème n'a ni `page.php` ni `singular.php`, WordPress est retombé sur `index.php`
(gabarit générique affichant seulement le titre) dès que le gabarit dédié
`page-autodiagnostic-entreprise-difficulte.php` ne correspondait plus au slug, faisant
disparaître tout le questionnaire ; la même condition de slug empêchait aussi le chargement de
`page-diagnostic.css` et `diagnostic.js`. Diagnostic confirmé avant toute modification :
`index.php`, `inc/setup.php` et `inc/diagnostic.php` étaient strictement identiques à la
version 2.4.1 qui fonctionnait — aucune régression du thème, aucune dépendance Colibri ou
Forminator en cause.

**Fichiers modifiés :** `page-autodiagnostic-entreprise-difficulte.php` renommé en
`page-diagnostic-entreprise-en-difficulte.php`, `inc/setup.php`, `inc/diagnostic.php`,
`inc/content-seed.php`, `inc/page-fields.php`, `front-page.php`,
`template-parts/site-header.php`, `style.css` (icône « domaines d'intervention »).

- Toutes les références au slug (`is_page()`, création de la page, lien du menu « Diagnostic »,
  liens « Évaluer la situation », redirection après envoi du formulaire) pointent désormais
  vers `diagnostic-entreprise-en-difficulte`.
- Redirection 301 permanente ajoutée (`inc/diagnostic.php`) de l'ancienne URL vers la nouvelle,
  pour préserver le référencement et les liens déjà en circulation.
- Correctif d'un effet de bord du passage aux icônes SVG (2.4.2) : les règles CSS qui fixaient
  une taille en pixels sur `.material-symbols-outlined` servaient, avec l'ancienne police, à
  dimensionner uniquement le cercle autour d'un glyphe texte dont la taille réelle restait
  pilotée par `font-size` — les deux étaient indépendants. Avec un `<svg>`, ces dimensions
  pilotent directement le rendu de l'icône : elle remplissait tout le cercle des « domaines
  d'intervention » de l'accueil et touchait le cerclage. Corrigé en restituant la même
  proportion visuelle par un `padding` (icône ~23 px dans un cercle de 42 px, comme avant),
  sans toucher au balisage. **Corrigé uniquement pour cette section**, à la demande explicite :
  le même effet de bord existe potentiellement sur d'autres icônes en cercle du thème (FAQ de
  l'accueil, tableaux de comparaison, bouton de lecture vidéo) mais n'a pas été touché ici et
  reste à traiter séparément si besoin.
- Testé : nouvelle URL en 200 avec `page-diagnostic.css`/`diagnostic.js` chargés, ancienne URL
  en 301, parcours complet 12 questions → résultat → coordonnées → consentement → envoi rejoué
  sur la nouvelle URL (12 réponses reçues, e-mail généré avec les 12 questions/réponses),
  section « domaines d'intervention » vérifiée en desktop/tablette/mobile.

## Version 2.4.1 — correctif formulaire Autodiagnostic + cohérence visuelle

**Fichiers modifiés :** `diagnostic.js`, `page-autodiagnostic-entreprise-difficulte.php`,
`page-diagnostic.css`, `style.css` (numéro de version).

- **Correctif critique** : la soumission du formulaire Autodiagnostic
  redirigeait systématiquement vers `?diagnostic=incomplete` et
  `wp_mail()` n'était jamais atteint. Cause : `renderQuestion()`
  (`diagnostic.js`) remplace `stage.innerHTML` à chaque question, ce qui
  supprime du DOM les radios des questions précédentes — leurs réponses
  restaient dans l'objet JS `answers` mais n'étaient jamais postées. Les 12
  réponses sont désormais sérialisées en inputs hidden (générés depuis
  `answers`, sans doublon avec le radio de la question affichée) avant
  chaque changement de question et une dernière fois à la soumission. Le
  score reste recalculé côté PHP (`spa_handle_diagnostic_lead()`, inchangé)
  à partir des réponses reçues, jamais du calcul client.
- Ajout du disque décoratif saumon (`.diagnostic-hero:after`, identique en
  teinte, taille et position à celui des pages d'expertise) sur la page
  Autodiagnostic, pour la cohérence visuelle avec le reste du site :
  purement décoratif (`pointer-events:none`), sans changement de contenu ni
  de structure, sans déplacement du bloc « Un outil d'orientation ».
- Aucun autre comportement du thème ou de la migration modifié.
- Testé : parcours complet des 12 questions → résultat → coordonnées →
  consentement → soumission (Playwright), vérification que les 12 réponses
  atteignent `$_POST['answers']` et que `wp_mail()` est appelé avec les 12
  questions/réponses, modification d'une réponse après retour arrière
  (la valeur transmise est bien la nouvelle), absence de débordement
  horizontal et rendu cohérent avec la page Prévention en desktop/tablette/
  mobile. La vérification de la réception effective dans FluentSMTP et la
  boîte de réception n'a pas pu être testée dans cet environnement
  (ni FluentSMTP ni le relais SMTP Microsoft n'y sont disponibles) — à
  valider une dernière fois en production après déploiement.

## Version 2.4.0 — correctif suite à l'incident de production du 2.3.1

**Contexte.** L'installation de la 2.3.1 sur le WordPress historique de
production (Colibri WP) a provoqué des écritures destructrices silencieuses en
base et rendu le retour à Colibri inopérant. Diagnostiqué et corrigé à partir
du dump SQL réel de production. Détail complet de la cause racine, des données
touchées, des tests et des procédures de migration/rollback dans le rapport
d'incident livré séparément et dans `spa-technical-notes.md`.

- **Correctif critique** : l'activation du thème ne réaffecte plus jamais
  `show_on_front`/`page_on_front` (options WordPress globales) quand le site a
  déjà une page d'accueil statique valide — c'est ce qui empêchait le retour à
  Colibri WP de restaurer le site après l'incident.
- **Correctif critique** : suppression des remplacements de contenu automatiques
  et silencieux sur `init` (`spa_seed_expertise_content()`,
  `spa_add_diagnostic_privacy_information()`, `spa_migrate_internal_links()`,
  `spa_remove_inactive_cookie_preferences()`), qui s'appliquaient à tort dès le
  premier contact avec n'importe quelle page, y compris étrangère.
- **Nouveau** : outil d'administration explicite « Outils > Migration de
  contenu » pour remplacer le contenu historique d'une page, avec
  identification par slug, sauvegarde de l'ancien contenu avant remplacement,
  verrouillage anti-double-application, restauration (rollback) page par page
  et journal de chaque action.
- Le thème n'écrit plus `_spa_seo_title`/`_spa_seo_description` quand un plugin
  SEO (Yoast, Rank Math, SEOPress, AIOSEO) est actif.
- Aucun changement visuel ni de gabarit. Aucune page, ID, slug ou métadonnée
  SEO Yoast existante n'est modifié par la simple activation du thème.
- Tests validés sur une copie du dump SQL réel de production (WordPress +
  MariaDB réels) : cycle complet Colibri actif → activation → migration
  explicite → contrôle des 17 pages publiques (200, sans résidu Colibri, sans
  erreur PHP/JS, slugs et métadonnées Yoast inchangés) → rollback → nouvelle
  migration → réactivation de Colibri WP confirmée saine.

## Version 2.3.1 — release candidate production

- Corrections d'accessibilité WCAG AA :
  - contraste texte/fond insuffisant sur le saumon (boutons, titres, petits
    titres) — 11 paires en échec sur 18 pages, 0 après correction ;
  - attributs `aria-label` ne correspondant pas au texte visible du lien
    (widget Avocat.fr sur 12 pages, lien Sénéque en pied de page).
- Aucune modification fonctionnelle.
- Aucun changement SEO (aucun texte, URL ou balise meta modifié).
- Tests validés : syntaxe PHP sur l'ensemble du thème, chargement réel de
  `functions.php` et des 9 modules `/inc` sans erreur fatale, 41/41
  vérifications automatisées (responsive sur 5 gabarits, navigation
  clavier, parcours JS du diagnostic et de la vidéo), 0 violation de
  contraste WCAG2 A/AA (axe-core) sur les 18 pages.

## Version 2.3.0

- Nettoyage architectural (suppression du remplacement global de HTML,
  centralisation des coordonnées du cabinet, découpage de `functions.php`
  en modules) et corrections de contenu SEO ciblées (H1 de la homepage,
  détails juridiques réintégrés, bio de Maître Saint-Père). Détail complet
  dans l'historique Git et le rapport d'audit fourni séparément.

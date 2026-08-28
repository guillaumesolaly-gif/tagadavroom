# Notes techniques — thème Saint-Père Avocat

Notes destinées à quiconque reprend ce thème après la Phase 1 de nettoyage
architectural. Ne documente que ce qui n'est pas déjà évident en lisant le
code.

## Gabarits `page-{slug}.php` : renommer un slug en base casse le rendu sans renommer aussi les fichiers

Le thème n'a ni `page.php` ni `singular.php`. La sélection de gabarit WordPress pour une page
repose donc entièrement sur la correspondance exacte entre son slug (`post_name`) et un fichier
`page-{slug}.php`. Si un slug est changé en base (renommage manuel dans wp-admin, ou par un
plugin de nettoyage/redirection) sans renommer le fichier de gabarit correspondant, WordPress
retombe sur `index.php` — un gabarit générique qui n'affiche que `the_title()` + `the_content()`
— faisant disparaître tout le contenu réel de la page (celui-ci vit dans le fichier de gabarit,
jamais en base, pour ces pages hors contenu Gutenberg). C'est exactement ce qui s'est produit
lors du renommage de `autodiagnostic-entreprise-difficulte` en `diagnostic-entreprise-en-
difficulte` (page Autodiagnostic) : le questionnaire a disparu, et `page-diagnostic.css` /
`diagnostic.js` ont cessé de se charger, car `inc/setup.php` les conditionne aussi via
`is_page('ancien-slug')`.

**En cas de nouveau changement de slug volontaire**, mettre à jour dans le même mouvement :
1. le nom du fichier `page-{slug}.php` ;
2. toute condition `is_page('...')` (`inc/setup.php`, `inc/page-fields.php`) ;
3. toute création/recherche par slug (`get_page_by_path()`, `wp_insert_post()` dans
   `inc/content-seed.php` et le fichier de la page concernée) ;
4. tout lien codé en dur vers l'ancienne URL (`home_url('/ancien-slug/')`) ;
5. une redirection 301 permanente de l'ancienne URL vers la nouvelle (voir
   `spa_redirect_old_diagnostic_slug()` dans `inc/diagnostic.php` pour le modèle).

## Buffering HTML : un seul est conservé, volontairement

Le thème utilisait deux mécanismes de `ob_start()` / remplacement de chaîne
sur le HTML déjà rendu :

- **`spa_apply_global_navigation()`** (ancien `header.php`/`footer.php`) —
  supprimé. Il faisait 15 `str_replace`/`preg_replace` sur le HTML final de
  **toute page**, y compris du contenu Gutenberg, JSON-LD et scripts futurs.
  Aucune fonctionnalité d'administration n'en dépendait : les valeurs
  qu'il patchait sont maintenant générées directement dans les templates
  via `spa_get_cabinet_setting()` / `home_url()`.

- **`spa_apply_home_fields()`** (`inc/home-fields.php`, appelé uniquement
  depuis `front-page.php`) — **conservé délibérément**. Il alimente une
  vraie fonctionnalité d'administration : la meta box "Contenu de la
  homepage" permet de modifier le texte de la page d'accueil depuis
  wp-admin sans toucher au code. Sa portée est limitée à la sortie HTML de
  `front-page.php` (jamais le contenu Gutenberg d'une autre page, jamais le
  JSON-LD généré dans `wp_head`), donc le risque qui justifiait de retirer
  `spa_apply_global_navigation()` ne s'applique pas de la même façon ici.
  Le supprimer casserait l'édition de la homepage depuis l'admin. Un futur
  refactor plus poussé (LOT 4 approfondi) pourrait remplacer le
  buffer+remplacement par une interpolation directe des ~50 champs dans le
  template, en gardant la même fonctionnalité — non fait ici pour rester
  strictement structurel.

## Génération des liens internes — une seule méthode partout

Tout lien interne dans les templates PHP passe par
`esc_url(home_url('/chemin/'))`, sans exception : breadcrumbs, liens vers
les pages légales, ancre `#expertises` depuis les pages d'expertise,
liens des cartes de la homepage. Un reliquat de liens relatifs écrits en
dur (`href="/chemin/"`, hérité du thème d'origine) a été corrigé après la
Phase 1.

À cette occasion, huit `search` de `spa_home_fields()`
(`guide_1_url`…`guide_4_url`, `faq_url`, `postulation_url`, `profile_url`,
`solutions_url`) qui comparaient encore contre l'ancien domaine en dur
(`https://saint-pere-avocat.fr/...`) ont été alignés sur le même chemin
relatif que les quatre champs `expertise_*_url`. Avant ce correctif, une
personnalisation de ces champs depuis wp-admin aurait pu silencieusement
ne plus s'appliquer sur un environnement dont le domaine réel diffère de
`saint-pere-avocat.fr` (préproduction, staging) — vérifié par test avant
et après.

Seules deux occurrences du domaine restent hors de `inc/settings.php` et
`inc/migrations.php` : le nom du site cité en toutes lettres dans
l'introduction des CGU (texte légal, pas un lien) et les seeds de
contenu (`seed/*.html`), où des liens relatifs en dur sont normaux — ce
sont des fragments Gutenberg stockés en base, pas du code PHP exécuté,
`home_url()` n'y est pas utilisable.

## Coordonnées dans les seeds (`seed/*.blocks-v2.html`)

Les fichiers de seed sont du contenu Gutenberg destiné à être stocké tel
quel dans `post_content` : ils ne peuvent pas contenir de PHP, donc pas de
`spa_get_cabinet_setting()` à l'intérieur. Deux d'entre eux
(`mentions-legales`, `politique-de-confidentialite`) contiennent le
téléphone, l'adresse et les e-mails du cabinet en texte littéral — c'est
normal et volontaire, il faut bien une valeur écrite quelque part.

Ce qui ne doit **pas** arriver : que ce texte littéral reste figé à la
valeur par défaut si le cabinet change un jour de téléphone ou d'e-mail
depuis Réglages > Cabinet, alors que le reste du site s'actualiserait.
`spa_seed_content()` (`inc/content-seed.php`) résout ça en substituant les
valeurs par défaut par les réglages réels **une seule fois, au moment où
le contenu est écrit en base** — pas à chaque affichage comme le faisait
l'ancien `spa_apply_global_navigation()`. La différence est fondamentale :
ça ne touche que le contenu que le thème lui-même s'apprête à écrire,
jamais une page déjà publiée ou modifiée à la main.

Les liens relatifs en dur dans ces mêmes seeds (`href="/gestion-de-cookies/"`,
etc.) sont volontairement laissés tels quels : une URL relative dans du
contenu Gutenberg est portable et correcte quel que soit le domaine, et
`home_url()` n'y est de toute façon pas utilisable.

## Contraste du saumon (WCAG AA)

Le saumon `--salmon` (`#df8d82`) était trop clair pour porter du texte —
que ce soit comme couleur de texte (le mot en italique dans les H1) ou
comme fond derrière du texte clair (bande « urgence » de la homepage,
boutons `.btn`). Confirmé par audit automatisé (axe-core) : 11 paires
texte/fond en échec sur 18 pages, dont certaines à 2,5:1 quand WCAG AA en
exige 4,5:1.

Corrigé sans changer l'identité visuelle : ajout de `--salmon-ink`
(`#a0655d`, un saumon plus soutenu) utilisé uniquement là où le saumon
sert de texte ou de fond de texte (`h1 em`, `.btn`, `.alert`). Les usages
purement décoratifs (puces, bordures, soulignements) gardent `--salmon`
tel quel — ils n'ont pas besoin de ce niveau de contraste.

Un bug de spécificité CSS a aussi été corrigé au passage :
`accessibility.css` contenait une règle
(`body:not(.home) .kicker,.home section:not(.support-band) .kicker`)
plus spécifique que prévu, qui écrasait silencieusement plusieurs
couleurs de kicker propres à chaque section (`.alert .kicker`,
`.contact .kicker`, `.observation-title .kicker`...) définies dans
`style.css`, et qui ne protégeait les kickers sur fond sombre
(`.support-band`, `footer`) que sur la page d'accueil — sur les autres
pages, ils héritaient du kicker sombre par défaut sur un fond noir, donc
illisibles. Cette règle a été supprimée ; les couleurs par section de
`style.css` s'appliquent de nouveau normalement, avec les quelques
ajustements de teinte nécessaires pour repasser sous les 4,5:1 requis.

Revérifié après coup : 0 violation de contraste (axe-core, WCAG2 A/AA)
sur les 18 pages, et les 41 vérifications fonctionnelles/responsive/
clavier toujours au vert.

## Migrations versionnées (`inc/migrations.php`)

Chaque fonction est verrouillée par un numéro de version stocké en
`option` (`get_option('spa_..._version')`). Une fois la version courante
atteinte, la fonction ne rejoue plus, même à chaque chargement de `init`.
**Ne pas** faire évoluer un numéro de version sans relire la fonction
correspondante : la relancer réexécute des `wp_update_post()` qui peuvent
écraser un contenu modifié à la main dans WordPress depuis.

## Vidéo TL7 (page « difficultés financières »)

`spa_render_video_feature()` (`inc/page-fields.php`) charge désormais la
vidéo uniquement au clic (`.video-consent`, géré par `theme.js`), et non
plus automatiquement au scroll. Le motif `.video-consent` existait déjà
dans le CSS/JS du thème mais n'était appelé par aucun template avant ce
correctif.

## Incident de production du 2.3.1 — cause racine et correctif (2.4.0)

Le 19/08/2026, l'installation de la version 2.3.1 sur le WordPress historique de
production (Colibri WP + Colibri Page Builder PRO, sans désactivation de plugin)
a mélangé sur les pages internes du contenu Colibri ancien et le nouveau thème,
et le retour à Colibri WP n'a plus restauré le site — il a fallu une restauration
de sauvegarde OVH. Reproduit et diagnostiqué de bout en bout sur une copie du
dump SQL réel de production (`saintpp326.sql`, WordPress + MariaDB réels, pas le
faux noyau WP des tests de la Phase 3).

**Cause racine n°1 — `page_on_front` réaffecté silencieusement.**
`spa_create_initial_pages()` (`inc/content-seed.php`, sur `after_switch_theme`)
cherchait une page de slug `accueil` et, si elle existait, réaffectait
inconditionnellement `show_on_front`/`page_on_front` vers son ID — y compris
quand cette page n'existait pas encore et venait d'être créée vide par la même
fonction. Or **aucune page de production ne porte le slug `accueil`** : la vraie
page d'accueil de production a le slug `avocat-entreprise-en-difficulte-saint-etienne`
(ID 1105). Le thème a donc créé une page « accueil » vide et a fait pointer
`page_on_front` dessus (1105 → une nouvelle page, ID 5428 en reproduction).
`show_on_front`/`page_on_front` sont des options WordPress **globales,
indépendantes du thème** : réactiver Colibri WP ne les restaure jamais. C'est
exactement ce qui a rendu le retour à Colibri inopérant — sa propre page
d'accueil pointait désormais vers une page vide et bricolée par le nouveau thème.
`front-page.php` du nouveau thème, lui, n'a jamais utilisé cette valeur (contenu
statique, jamais de `the_content()`) — d'où l'apparence trompeuse d'une homepage
« qui marchait ».

**Cause racine n°2 — remplacement de contenu déclenché par des indicateurs
« premier seed » vrais par défaut sur n'importe quelle page.** Trois fonctions
hookées sur `init` remplaçaient ou complétaient le `post_content` de pages
existantes dès qu'un post-meta `_spa_..._seeded` était absent :
`spa_seed_expertise_content()` (`inc/content-seed.php`, 6 pages : mentions-legales,
cgu, politique-de-confidentialite, gestion-de-cookies, trouver-avocat-droit-
entreprises-saint-etienne, faq-avocat-droit-entreprises-saint-etienne) et
`spa_add_diagnostic_privacy_information()` (`inc/diagnostic.php`, politique-de-
confidentialite). Ce post-meta est absent par défaut sur **n'importe quelle
page**, y compris une page étrangère (Colibri) qui n'a jamais vu ce thème : la
condition censée protéger un contenu « déjà migré » était donc vraie au premier
contact avec n'importe quel site, pas seulement sur une installation neuve.
Résultat mesuré sur le dump réel : 6 pages de production écrasées silencieusement.
Les 11 autres pages (dont `/des-solutions-en-cas-de-difficultes-financieres/`,
signalée par la cliente) n'ont pas eu leur `post_content` modifié : leur apparence
cassée venait uniquement du nouveau `header`/`footer`/CSS habillant un
`the_content()` qui rendait fidèlement le HTML Colibri d'origine (balisage
`data-colibri-*`, classes `h-section`, shortcode `[colibri_video_player]` non
interprété par ce thème).

**Ce qui n'a pas été touché.** Les métadonnées SEO Yoast (`_yoast_wpseo_*`,
plugin confirmé actif en production) sont intactes : `wp_update_post()` ne
touche jamais la table postmeta. Vérifié par comparaison d'empreintes avant/après
sur les 17 pages réelles.

**Correctif (2.4.0).**
1. `spa_create_initial_pages()` ne touche plus jamais `show_on_front`/
   `page_on_front` si le site a déjà une page d'accueil statique valide — il ne
   crée même plus de page « accueil » surnuméraire dans ce cas.
2. `spa_seed_expertise_content()` et `spa_add_diagnostic_privacy_information()`
   sont supprimées. Le paragraphe qu'ajoutait la seconde fait maintenant partie
   du seed `politique-de-confidentialite.blocks-v2.html` (source unique).
3. Le remplacement de contenu de page existante passe désormais uniquement par
   un outil d'administration explicite (Outils > Migration de contenu,
   `inc/migration-tool.php`) : identification par slug, sauvegarde de l'ancien
   contenu en postmeta avant tout remplacement, verrouillage par version pour
   empêcher une double application accidentelle, restauration possible page par
   page, journal de chaque action. Détail du fonctionnement et de la procédure
   de production dans le rapport d'incident livré séparément.
4. `spa_migrate_internal_links()` et `spa_remove_inactive_cookie_preferences()`
   (`inc/migrations.php`) ne sont plus hookées sur `init` : elles restent
   verrouillées par version et sont appelées depuis le même outil explicite.
5. Les écritures `_spa_seo_title`/`_spa_seo_description` du thème sont
   désormais suspendues quand un plugin SEO est actif (`spa_has_seo_plugin()`),
   pour éviter des écritures postmeta inutiles (Yoast confirmé actif en
   production).

**Piège WordPress rencontré en construisant l'outil de migration : `wp_slash()`.**
`update_post_meta()` et `wp_update_post()` appellent en interne `wp_unslash()`
sur toute valeur reçue (WordPress suppose que l'appelant transmet des données
« slashées » comme le ferait `$_POST`). Sans `wp_slash()` en amont, tout
backslash littéral présent dans le contenu d'origine est silencieusement
supprimé — mesuré : 250 backslashes sur la seule page politique-de-confidentialite
(JS/JSON intégré par Colibri), qui auraient corrompu la sauvegarde censée
garantir un rollback fidèle. Toutes les écritures de contenu dans
`inc/migration-tool.php` et `inc/migrations.php` passent maintenant par
`wp_slash()`. Autre point vérifié en testant la restauration : WordPress
applique toujours, via `wp_update_post()`, son filtre `content_save_pre` natif
`wp_targeted_link_rel()` (ajout de `rel="noopener"` sur les liens `target="_blank"`
qui ne l'ont pas déjà) — comportement de sécurité intégré à WordPress, universel,
sans rapport avec ce thème : la sauvegarde stockée en postmeta reste, elle,
strictement identique à l'octet près à l'original.

**Test de non-régression.** Cycle complet rejoué sur une copie fraîche du dump
réel : Colibri actif → activation 2.4.0 (aucune écriture destructive automatique,
vérifié par empreintes avant/après) → migration explicite des 16 pages (contenu
restauré à l'octet près en sauvegarde, vérifié par comparaison MD5 avec le dump
d'origine) → 17 URLs publiques en 200, sans résidu Colibri, sans erreur PHP →
test de rollback sur une page (fidélité confirmée, à l'exception du
`rel="noopener"` natif documenté ci-dessus) → nouvelle migration après rollback
(non bloquée) → réactivation de Colibri WP : sa page d'accueil s'affiche à
nouveau normalement (`page_on_front` toujours à 1105, jamais modifié).

## Rate limiting de l'autodiagnostic

`spa_handle_diagnostic_lead()` utilise `$_SERVER['REMOTE_ADDR']` pour la
limitation de débit. Sur l'hébergement final (OVH ou tout proxy/CDN devant
le site), vérifier que cette valeur représente bien l'IP du visiteur avant
mise en production — ne pas ajouter de lecture de `X-Forwarded-For` sans
connaître l'environnement réseau réel, un en-tête client n'est pas fiable
sans confiance explicite dans le proxy qui le pose.

# AI-AGENT.md — Instructions pour un agent IA de développement

Ce document s'adresse à un agent IA (Claude Code ou équivalent) qui reçoit **GWS** comme point
de départ d'un nouveau site WordPress. Il est impératif : les règles ci-dessous ne sont pas des
suggestions, elles conditionnent la maintenabilité du produit final.

**Avant de toucher au code**, lire intégralement, dans cet ordre : `README.md`,
`ARCHITECTURE.md`, puis ce fichier. Respecter ces règles pendant toute la durée du projet, pas
seulement à la première lecture.

---

## 1. Rôle de chaque composant

### GWS Core (`wp-content/plugins/gws-core/`)

Détient tout ce qui doit survivre à un changement de thème :
- réglages persistants de l'entité (coordonnées, logo, réseaux sociaux...) ;
- Custom Post Types et taxonomies ;
- champs structurés (meta) ;
- relations entre contenus métier ;
- logique serveur persistante (traitement de formulaire, calcul, envoi d'e-mail) ;
- sécurité des formulaires (nonce, anti-bot, limite de tentatives) ;
- migrations de données.

Doit rester actif quel que soit le thème utilisé. **Aucune logique strictement graphique n'y a
sa place.**

### GWS Starter (`wp-content/themes/gws-starter/`)

Détient uniquement la présentation :
- design (tokens, design system) ;
- gabarits WordPress (page/single/archive/search/404/index, gabarits de page) ;
- composants visuels (cartes, boutons, modales, pictogrammes...) ;
- CSS/JS ;
- responsive ;
- accessibilité ;
- affichage des données fournies par GWS Core.

**Le thème ne stocke jamais lui-même une donnée persistante.** Il consomme les helpers de
GWS Core (`gws_core_get_setting()`, `gws_core_social_links()`...), toujours via une enveloppe
protégée par `function_exists()` (voir `inc/compat.php`), pour ne jamais produire d'erreur
fatale si GWS Core est absent — avec un repli propre (texte au lieu d'un logo, rien au lieu
d'un crédit), jamais une page blanche.

### Modules métier (`wp-content/plugins/gws-core/modules/<slug>/` + pendant thème)

Fonctionnalités propres à un secteur ou à un projet donné (ex. un CPT « Cheval » pour un
élevage) : CPT, taxonomies, champs, logique — côté plugin ; gabarits et assets — côté thème.
Voir `wp-content/plugins/gws-core/modules/_boilerplate-cpt/` comme squelette de départ et
`wp-content/plugins/gws-core/modules/README.md` pour le mécanisme d'activation
(`config/modules.php`) et la convention de préfixe.

**Règle absolue : aucune dépendance inverse du cœur vers un module.** Le cœur (thème ou plugin)
ne référence jamais un module par son nom, ni ne suppose son existence. Un module peut appeler
les fonctions publiques du cœur ; jamais l'inverse.

---

## 2. Interdictions explicites

L'agent ne doit **jamais** :

1. Déplacer une donnée persistante dans le thème (elle appartient à GWS Core).
2. Coder en dur une donnée cliente qui doit être administrable (coordonnées, textes propres au
   client, liens...).
3. Multiplier des `page-{slug}.php` pour une famille de contenus répétables — utiliser un CPT +
   champs structurés (voir `modules/_boilerplate-cpt/`), jamais un fichier par entrée.
4. Utiliser un seed pour réécrire une page existante. Un seed ne fait que créer un contenu
   **absent** (`wp_insert_post()` gardé par une vérification d'existence) — jamais un
   remplacement automatique.
5. Modifier automatiquement `post_content` ou une métadonnée éditoriale existante lors d'une
   mise à jour de thème/plugin/module.
6. Créer une migration silencieuse déclenchée sur `init`. Toute migration passe par le cadre
   explicite de `includes/migration.php` (`gws_core_register_migration()`), lancée à la main
   depuis Outils > Migrations.
7. Faire un `str_replace()` fragile sur du HTML déjà rendu pour injecter ou modifier du contenu.
   Interpoler les variables directement dans le gabarit PHP au moment du rendu.
8. Ajouter un page builder ou un framework front lourd sans besoin explicite et validé.
9. Recréer ACF (ou équivalent) à l'intérieur du starter. Le générateur de champs
   (`includes/fields.php`) reste volontairement minimal ; pour un besoin complexe (repeater
   riche, galerie, relation many-to-many), décider projet par projet entre code dédié et
   extension éprouvée — jamais réinventer un framework de champs générique dans le cœur.
10. Créer un second graphe Schema.org concurrent d'un plugin SEO actif. Si un plugin SEO
    compatible (Yoast, Rank Math, SEOPress, AIOSEO) est actif, le fallback maison s'efface
    (`gws_has_seo_plugin()`) ; tout enrichissement se fait via les filtres officiels du plugin
    SEO (voir `inc/seo-yoast-bridge.php` comme référence), jamais par un graphe parallèle.
11. Désactiver ou contourner la sanitation, la validation ou l'échappement, même temporairement
    « pour tester ».
12. Supprimer automatiquement du contenu à la désactivation d'un module. Le contenu créé par un
    module reste en base tant qu'il n'est pas supprimé explicitement par un humain.
13. Modifier GWS Core pour une fonctionnalité strictement métier alors qu'un module suffit. Le
    cœur reste générique ; le métier vit dans les modules.
14. Considérer ses propres tests (automatisés ou statiques) comme une preuve suffisante de
    qualité. Une recette fonctionnelle réelle dans WordPress reste obligatoire (voir §5 et §6).

---

## 3. Règles de développement

L'agent doit :

- **Auditer l'architecture existante avant toute modification.** Lire les fichiers concernés en
  entier, comprendre le mécanisme en place, avant d'écrire une ligne de code.
- **Proposer un plan avant un développement significatif** (nouveau module, nouveau CPT,
  changement de comportement partagé) et obtenir une validation avant de coder.
- Utiliser les API WordPress natives autant que possible plutôt que du code maison — voir
  `ARCHITECTURE.md` §7 pour les pièges déjà rencontrés et à ne pas reproduire.
- Conserver les conventions de nommage : préfixe `gws_core_` pour le cœur du plugin, `gws_`
  pour le cœur du thème.
- Donner à chaque module métier son propre préfixe court et distinct, jamais `gws_`/`gws_core_`
  — consigner ce préfixe dans `wp-content/plugins/gws-core/modules/README.md`.
- Utiliser un CPT + taxonomies + champs structurés dès qu'un contenu est répétable et structuré
  (fiches, biens, réalisations...) ; garder le contenu éditorial classique en base/Gutenberg
  (pages, articles) pour tout le reste.
- Garder les gabarits responsables uniquement du **rendu** — jamais de la source de vérité d'une
  donnée. Un gabarit lit, il n'invente pas.
- Charger les styles/scripts uniquement là où ils sont nécessaires (voir le pattern
  `add_action('wp_enqueue_scripts', ...)` conditionnel utilisé par les modules de gabarit de
  page) — jamais un asset chargé globalement « au cas où ».
- Développer mobile-first (le design system du starter est déjà construit ainsi, voir
  `assets/css/layout.css`).
- Respecter l'accessibilité par défaut du starter (focus visible, structure sémantique, noms
  accessibles) plutôt que de la retirer ou de la contourner.
- Utiliser les composants et design tokens existants (`assets/css/tokens.css`,
  `assets/css/components.css`, `template-parts/content/*.php`) avant d'en créer de nouveaux.
  Ne dupliquer un composant que si le besoin diffère réellement.
- Documenter tout nouveau module (README dédié, comme les modules existants).
- Mettre à jour `CHANGELOG.md` et le numéro de version du composant modifié lorsque c'est
  pertinent.

---

## 4. Sécurité — exigences minimales

Pour toute nouvelle fonctionnalité touchant à la persistance ou à un formulaire :

- **Capability** : `manage_options` pour tout réglage global ; la capability appropriée
  (`edit_post` généralement) pour une action sur un contenu.
- **Nonce** : systématique sur tout formulaire d'administration ou tout traitement déclenché par
  une action utilisateur (voir `wp_nonce_field()`/`check_admin_referer()` dans
  `includes/admin/*.php`, ou les helpers `gws_core_security_fields()`/
  `gws_core_verify_form_security()` pour un formulaire public).
- **Validation** avant tout traitement métier (types attendus, formats, bornes).
- **Sanitation à l'entrée** : systématique, par type (voir `includes/fields.php` — étendre ce
  fichier plutôt que de sanitizer à la main ailleurs si le besoin est générique).
- **Échappement à la sortie** : systématique (`esc_html()`, `esc_attr()`, `esc_url()`...),
  jamais de confiance dans une donnée déjà « sanitizée à l'entrée » pour l'échapper à nouveau à
  la sortie — les deux étapes sont indépendantes et toutes deux obligatoires.
- **Logique métier critique côté serveur**, jamais seulement côté client (JavaScript). Le
  JavaScript améliore l'expérience, il ne remplace jamais une vérification serveur.
- **Ne jamais faire confiance à une donnée venant du navigateur** (POST, GET, cookies, headers)
  sans la revalider côté serveur, y compris si le JavaScript l'a déjà validée.
- **Protection anti-abus** pour tout formulaire public exposé sans authentification (pot de
  miel, délai anti-bot, limite de tentatives par IP — voir `includes/security.php`).

---

## 5. SEO / Schema

- Compatibilité systématique avec les plugins SEO courants (Yoast, Rank Math, SEOPress,
  AIOSEO) : voir `gws_has_seo_plugin()`.
- Le fallback SEO/Schema maison du starter ne s'active **que** si aucun plugin SEO compatible
  n'est actif.
- Aucune duplication de graphe Schema.org — un seul graphe, celui du plugin SEO actif s'il y en
  a un, enrichi via ses filtres officiels si nécessaire.
- Canonical propre (une seule URL canonique par page, gérée par WordPress ou le plugin SEO —
  ne jamais la dupliquer ou la contredire).
- Un seul `<h1>` par page.
- Hiérarchie de titres (`Hn`) cohérente, sans saut de niveau arbitraire.
- Réfléchir aux URLs et slugs avant la mise en production (les changer après coup casse des
  liens externes et le référencement déjà acquis).
- Maillage interne pertinent entre les contenus du site.
- Texte alternatif (`alt`) approprié sur chaque image porteuse de sens ; vide (`alt=""`) pour
  une image strictement décorative.

---

## 6. Migrations et données

**Règle absolue : une mise à jour de thème, de plugin ou de module ne doit jamais réécrire
silencieusement un contenu éditorial existant.**

Toute migration de données existantes doit :
- être **explicitement déclenchée** par un administrateur (jamais automatique sur `init` ou à
  l'activation) ;
- être **limitée au périmètre annoncé** (ne toucher que ce qui a été explicitement décrit) ;
- **sauvegarder les valeurs précédentes** avant modification ;
- être **relançable sans effet destructif** (rejouer une migration déjà appliquée ne doit rien
  casser) ;
- permettre un **rollback** quand c'est pertinent ;
- **produire un compte rendu** consultable (journal).

Voir `includes/migration.php` (cadre générique déjà fourni) — l'utiliser plutôt que d'écrire un
script de migration ad hoc.

---

## 7. Definition of Done

Avant d'annoncer qu'un développement est terminé, vérifier au minimum :

- [ ] Syntaxe PHP (`php -l` sur chaque fichier modifié).
- [ ] Syntaxe JS (`node --check` sur chaque fichier modifié).
- [ ] Absence d'erreur dans la console navigateur.
- [ ] Rendu correct aux largeurs : mobile 375px et 390px, tablette 768px, 1024px, 1440px,
      1920px.
- [ ] Absence de débordement horizontal (`overflow-x`) à ces largeurs.
- [ ] Navigation complète au clavier (Tab/Maj+Tab) sur les éléments interactifs ajoutés.
- [ ] Focus visible sur chaque élément interactif ajouté.
- [ ] Formulaires : soumission, validation, message d'erreur/succès.
- [ ] Validation côté serveur effective (pas seulement côté client).
- [ ] Title, meta description, canonical corrects sur les pages concernées.
- [ ] Schema.org généré sans champ vide, sans doublon avec un plugin SEO actif.
- [ ] Fallbacks WordPress toujours fonctionnels (page/single/archive/search/404/index).
- [ ] Page 404 correcte.
- [ ] Recherche native fonctionnelle.
- [ ] Lighthouse mobile et desktop passés en revue (pas nécessairement un score parfait, mais
      aucune régression injustifiée).
- [ ] Non-régression des fonctionnalités existantes déjà validées.

**Les tests statiques et automatisés de l'agent (`php -l`, `node --check`, scripts de
`tests/`) ne remplacent jamais une recette fonctionnelle réelle dans un WordPress qui tourne
(Local ou équivalent).** Un test qui passe en isolation ne garantit pas un rendu correct, une
interaction clavier fonctionnelle, ou une absence d'erreur console en conditions réelles.

---

## 8. Méthode attendue pour un nouveau site

1. Lire la documentation GWS (`README.md`, `ARCHITECTURE.md`, ce fichier).
2. Analyser le besoin métier avec l'utilisateur (secteur, contenus, fonctionnalités attendues).
3. Identifier ce qui relève du cœur (rien, en principe — le cœur ne change pas d'un projet à
   l'autre), du thème (charte visuelle, gabarits spécifiques au projet) et d'un module métier
   (tout le reste : CPT, champs, logique propres au secteur).
4. Proposer une architecture : arborescence des modules à créer, modèle de données (CPT,
   taxonomies, champs), gabarits nécessaires.
5. Obtenir la validation de l'utilisateur avant de développer.
6. Développer, en respectant les règles de ce document.
7. Exécuter les tests automatisés pertinents (existants + nouveaux si le comportement le
   justifie).
8. Effectuer une recette réelle dans un WordPress local (Local ou équivalent) — jamais sautée.
9. Réaliser un audit final (Definition of Done, §7).
10. Déployer.
11. Effectuer une recette en production (les conditions réelles diffèrent toujours un peu du
    local : e-mail, HTTPS, cache, CDN...).

---

## 9. Transmission à un autre développeur

Le code produit doit rester compréhensible par un développeur WordPress tiers qui n'a **pas**
participé aux échanges avec l'agent IA. Concrètement :

- Aucun choix important (nommage, architecture, décision de sécurité) ne doit dépendre
  uniquement de l'historique de conversation — il doit être lisible et justifié dans le code
  (commentaire sur le *pourquoi*, pas sur le *quoi*) ou dans la documentation du module.
- Un module métier a son propre `README.md` expliquant ce qu'il fait, comment l'activer, et son
  préfixe.
- Le nommage suit les conventions déjà en place, pas une convention inventée pour l'occasion.
- Toute limitation connue ou tout compromis assumé est écrit quelque part (README du module,
  commentaire), pas seulement mentionné à l'utilisateur en conversation.

Un projet livré doit pouvoir être repris par un développeur qui ouvre seulement le dépôt, sans
accès à la conversation qui l'a produit.

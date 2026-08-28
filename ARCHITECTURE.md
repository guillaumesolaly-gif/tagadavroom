# Architecture — GWS Starter

Ce document s'adresse à tout développeur WordPress qui reprend un projet construit sur ce
starter, sans connaître son historique. Il explique les choix structurants et les pièges à ne
pas reproduire.

## 1. Pourquoi deux composants (thème + plugin) ?

WordPress lie les Custom Post Types, taxonomies et champs déclarés dans un thème au thème actif.
Si ce thème est un jour remplacé (refonte visuelle, changement de prestataire), ces types de
contenu disparaissent du back-office — le contenu reste en base mais devient invisible et
inéditable tant qu'aucun code ne les redéclare.

Pour éviter cela, ce starter sépare strictement :

- **`wp-content/plugins/gws-core/`** : tout ce qui doit survivre à un changement de thème —
  réglages, CPT, taxonomies, champs structurés, relations, logique métier persistante
  (formulaires, migrations).
- **`wp-content/themes/gws-starter/`** : tout ce qui est visuel — templates, CSS, JS, blocs
  Gutenberg d'authoring (leur contenu est stocké dans `post_content`, donc reste portable même
  si le rendu change).

**Règle de dépendance à sens unique** : le thème peut appeler les fonctions publiques du plugin
(`gws_core_get_setting()`...), toujours protégées par `function_exists()` pour ne jamais
provoquer d'erreur fatale si le plugin est désactivé par erreur. Le plugin ne doit jamais appeler
de fonction du thème.

## 2. Contenu éditorial vs contenu métier répétable

- **Page éditoriale classique** (à propos, mentions légales, une page de service...) : contenu
  Gutenberg standard dans `post_content`, gabarit générique (`page.php`) ou gabarit de mise en
  page (`page-templates/*.php`, choisi depuis l'éditeur). Jamais de texte en dur dans un fichier
  PHP nommé d'après un slug.
- **Contenu métier répétable** (une fiche produit, un bien, un cheval...) : un Custom Post Type
  avec des champs structurés, dans le plugin. Voir
  `wp-content/plugins/gws-core/modules/_boilerplate-cpt/README.md` pour la marche à suivre.

## 3. Système de champs structurés

`gws-core` fournit un générateur minimal de champs (`includes/fields.php`) : un schéma
déclaratif PHP produit une meta box + une sauvegarde sécurisée, sans dépendance à ACF. Il gère
des champs simples (texte, nombre, e-mail, url, select, case à cocher, zone de texte,
`attachment_id` pour référencer un média de la médiathèque). **Ce n'est volontairement pas un
concurrent d'ACF** : pour un besoin complexe (repeater riche, galerie, relation many-to-many),
la décision (code dédié ou extension éprouvée) se prend projet par projet — ne pas étendre ce
générateur pour l'y forcer.

`attachment_id` ne couvre que la sanitization (vérifie que l'ID pointe vers une vraie image) :
un sélecteur avec aperçu (comme celui du logo de l'entité, `includes/admin/settings-page.php` +
`assets/admin-logo-picker.js`) reste un rendu dédié à écrire au cas par cas — ce générateur ne
fournit pas de widget média générique.

**Réglages de l'entité — discipline « rien de vide ».** Au-delà du socle minimal (nom,
téléphone, e-mail, adresse, ville), tous les champs (logo, WhatsApp, réseaux sociaux, fiche
Google Business Profile, crédit de réalisation) sont facultatifs. Toute fonction qui consomme
ces réglages (rendu du thème, Schema.org maison, enrichissement Yoast) doit omettre entièrement
la clé correspondante si la donnée est vide — jamais une balise ou une entrée de tableau vide.
Voir `gws_schema_organization_node()` (thème, `inc/schema.php`) pour le patron à suivre.

## 4. Mécanisme des modules métier

Un module métier a deux moitiés portant le même slug :

- `wp-content/plugins/gws-core/modules/<slug>/` — CPT, taxonomies, champs, logique.
- `wp-content/themes/gws-starter/modules/<slug>/` — gabarits de référence, assets.

**Activation** : ajouter le slug à `wp-content/plugins/gws-core/config/modules.php`. C'est
l'unique interrupteur, pour les deux moitiés du module — aucun fichier n'a besoin d'être créé,
copié ou modifié côté thème. `config/modules.php` reste l'unique source de vérité versionnée
pour les modules métier réels d'un projet.

Exception volontairement isolée : le module `qa` (développement uniquement) peut en plus être
basculé depuis **Outils > Recette GWS** dans l'admin, sans édition de fichier — voir
`includes/admin/qa-tool-page.php`. Cet écran n'existe et n'agit que si
`wp_get_environment_type()` vaut `local` ou `development`, et sa bascule
(`gws_core_qa_dev_enabled`) est une option séparée, uniquement OR-ée avec `config/modules.php`
dans `gws_core_active_modules()` — ce n'est pas un mécanisme générique d'activation des modules
métier depuis l'admin, et le retirer ne casse rien du fonctionnement normal du plugin.

**Gabarits côté thème, sans copie.** Un gabarit de module reste physiquement dans
`wp-content/themes/gws-starter/modules/<slug>/` :

- `page-templates/*.php` (avec un en-tête `Template Name:`) pour un gabarit de page ;
- `templates/single-{post_type}.php` / `archive-{post_type}.php` pour un CPT.

`inc/module-templates.php` (chargé en permanence par le cœur du thème, sans coût quand aucun
module n'en a besoin) les rend disponibles auprès de WordPress via ses filtres natifs prévus à
cet effet, en ne considérant que les modules effectivement listés dans `config/modules.php`
(interrogé via `gws_core_active_modules()`, la seule fonction du plugin que ce fichier appelle).
Retirer un module de la configuration fait disparaître ses gabarits du sélecteur de l'éditeur
et de la hiérarchie de gabarits dès la requête suivante, sans aucune trace côté thème.

- **Gabarit de page** : `theme_page_templates` (liste le gabarit dans le sélecteur de
  l'éditeur) + `page_template` (fournit le chemin réel une fois ce gabarit assigné à une page).
  Ici l'assignation est un choix explicite de l'admin (la meta `_wp_page_template`), donc aucune
  question de priorité entre plusieurs fichiers ne se pose.
- **Single / archive de CPT** : priorité voulue = 1) un gabarit spécifique déjà présent à la
  racine du thème (`single-{post_type}.php` / `archive-{post_type}.php`) ; 2) le gabarit fourni
  par le module actif ; 3) le fallback générique du thème (`single.php` / `archive.php`).
  WordPress résout déjà ce genre de priorité via les filtres `single_template_hierarchy` /
  `archive_template_hierarchy` (liste ordonnée de noms de fichiers, la plus spécifique en
  premier) puis `locate_template()`, qui retourne le premier fichier réellement présent dans
  cette liste. **Piège à éviter** : les filtres `single_template`/`archive_template` (qui
  reçoivent le résultat déjà tranché) ne conviennent pas ici, car le thème fournissant toujours
  `single.php`/`archive.php` en filet de sécurité, ce résultat n'est jamais vide — un module ne
  serait donc jamais consulté. La bonne approche, utilisée ici, consiste à insérer le gabarit du
  module dans la hiérarchie elle-même (`gws_insert_module_template_in_hierarchy()`), juste après
  l'entrée spécifique et avant le fallback générique, pour que `locate_template()` choisisse
  naturellement le bon fichier dans le bon ordre.

Voir `wp-content/themes/gws-starter/modules/README.md`.

Un gabarit de module qui a besoin de ses propres CSS/JS les enregistre lui-même, en tête de
fichier, via `add_action('wp_enqueue_scripts', ...)` placé **avant** l'appel à `get_header()`
(le hook n'a pas encore été déclenché à ce stade de l'exécution : cela fonctionne, contrairement
à un enregistrement fait après `get_header()`). Ce pattern évite de toucher au fichier central
d'enqueue du thème (`inc/setup.php`) pour chaque module.

## 5. Flush automatique des permaliens

Un module s'active ou se désactive en éditant `config/modules.php`, pas via un écran Extensions
classique — aucun événement d'activation/désactivation WordPress standard ne se déclenche donc
tout seul. `includes/modules.php` compare à chaque chargement la liste des modules actifs à
celle mémorisée lors du chargement précédent ; en cas d'écart, un flush des règles de réécriture
est programmé et exécuté en fin de traitement de `init`, une fois que les modules encore actifs
ont eu l'occasion d'enregistrer leurs propres post types/taxonomies. **Aucune étape manuelle
dans Réglages > Permaliens n'est donc jamais nécessaire**, ni pour un module qui déclare un CPT,
ni pour les pages WordPress classiques (le thème lui-même ne déclare aucun CPT ni règle de
réécriture — les pages standards n'ont jamais eu besoin de flush après l'activation du thème).

## 6. Cadre de migration

`wp-content/plugins/gws-core/includes/migration.php` fournit un cadre générique — versionné,
avec sauvegarde et rollback — pour tout remplacement explicite de contenu existant. Il est
**inerte tant qu'aucun module n'y déclare de migration** via `gws_core_register_migration()`.
Utiliser ce cadre plutôt que d'écrire un script de migration ad hoc à chaque fois.

## 7. Pièges WordPress connus — à ne jamais reproduire

Ces règles viennent d'un incident de production réel sur un projet ayant précédé ce starter.

1. **Ne jamais faire dépendre le rendu d'une page de son slug d'URL.** Un gabarit
   `page-{slug}.php` à la racine casse silencieusement (retombée sur `index.php`) si le slug
   change en base sans renommer le fichier. Ce starter n'a **aucun** gabarit nommé d'après un
   slug — seulement des gabarits génériques et des `page-templates/` choisis explicitement.
2. **Ne jamais écrire automatiquement sur du contenu existant.** Un indicateur « pas encore
   migré » (postmeta absente) est vrai par défaut sur n'importe quel contenu, y compris un
   contenu étranger sans rapport avec le projet. La seule écriture automatique tolérée est la
   création d'un contenu **absent** (`wp_insert_post()` gardé par une vérification d'existence).
   Tout remplacement de contenu existant passe par le cadre de migration explicite (§6),
   jamais par un hook `init` inconditionnel.
3. **Ne jamais transformer du HTML déjà rendu par recherche/remplacement** (`ob_start()` +
   `str_replace()` sur la sortie). Interpoler les variables directement dans le gabarit PHP au
   moment du rendu.
4. **Ne jamais coder un chemin de thème en dur** (ex. `/wp-content/themes/nom-du-thème/...`).
   Toujours `get_template_directory_uri()` côté PHP ; en CSS, un chemin relatif à l'intérieur du
   thème (`url("fonts/...")`) se résout par rapport au fichier CSS, pas au domaine — donc jamais
   fragile à un renommage du dossier du thème.
5. **Ne jamais coder un domaine en dur** dans une comparaison ou un lien interne. Toujours
   `home_url()` / `site_url()`.
6. **Ne jamais dépendre d'une police à ligatures pour des icônes.** Le sprite SVG inline
   (`inc/icons.php`) ne dépend d'aucun fichier de police externe.
7. **`wp_update_post()` et `update_post_meta()` appellent `wp_unslash()` en interne.** Toujours
   passer par `wp_slash()` avant d'écrire un contenu qui peut contenir des antislashs littéraux
   (JS/JSON intégré, notamment) — voir les helpers de sauvegarde du cadre de migration.
8. **`$_SERVER['REMOTE_ADDR']` n'est fiable que si l'hébergement/CDN le transmet correctement.**
   Vérifier au cas par cas avant de l'utiliser pour une limite de tentatives ; ne jamais lire
   `X-Forwarded-For` sans confiance explicite dans le proxy qui le pose.

## 8. Conventions de nommage

- Cœur du plugin : préfixe `gws_core_`.
- Cœur du thème : préfixe `gws_`.
- Chaque module métier a son propre préfixe court, consigné dans
  `wp-content/plugins/gws-core/modules/README.md` — jamais `gws_` ni `gws_core_`, pour qu'un
  développeur distingue immédiatement le socle du code spécifique au projet.

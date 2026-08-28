# GWS Starter (thème)

Thème de présentation du starter GWS : templates WordPress, design system, accessibilité,
assets. Aucune donnée persistante n'est stockée par ce thème — voir le plugin compagnon
`gws-core` (`wp-content/plugins/gws-core/`), qui doit rester actif en permanence.

## Démarrer un nouveau projet

1. Activer le plugin `gws-core` puis ce thème.
2. Renseigner Réglages > Entité (coordonnées, réseaux sociaux).
3. Personnaliser `assets/css/tokens.css` avec la charte du projet (couleurs, typographies,
   espacements) — c'est le seul fichier CSS à modifier pour l'identité visuelle ; ne jamais
   coder une couleur en dur ailleurs.
4. Créer les pages du site dans wp-admin (contenu Gutenberg standard).
5. Pour un contenu métier répétable (fiches produit, réalisations, biens...), dupliquer
   `wp-content/plugins/gws-core/modules/_boilerplate-cpt/` plutôt que de créer des pages
   éditoriales — voir son README.

## Modules optionnels fournis en exemple

`diagnostic` et `guides` (voir `modules/README.md`) sont désactivés par défaut. Le cœur reste
volontairement minimal tant qu'aucun module n'est activé.

## Checklist avant mise en production

- [ ] Permaliens enregistrés (Réglages > Permaliens > Enregistrer).
- [ ] Réglages de l'entité renseignés (Réglages > Entité).
- [ ] Envoi d'e-mail testé depuis l'environnement réel (`wp_mail()` ne délivre pas forcément en
      local) ; configurer un SMTP authentifié si l'hébergeur ne délivre pas correctement.
- [ ] `tokens.css` reflète bien la charte du projet, pas les valeurs neutres par défaut.
- [ ] Aucune référence au nom de dossier du thème codée en dur (rechercher le nom du thème dans
      le code : tout chemin doit passer par `get_template_directory_uri()`).
- [ ] Aucun domaine codé en dur (rechercher le nom de domaine : tout lien interne doit passer
      par `home_url()`).
- [ ] Si un plugin SEO est installé, vérifier qu'aucune sortie du thème ne fait doublon
      (title, meta description, JSON-LD).
- [ ] Vérifier la fiabilité de `$_SERVER['REMOTE_ADDR']` pour la limite de tentatives des
      formulaires selon l'hébergement/CDN réel (voir `gws_core_rate_limit_check()`).

Voir `ARCHITECTURE.md` à la racine du dépôt pour la philosophie complète du starter et les
pièges WordPress connus à ne pas reproduire.

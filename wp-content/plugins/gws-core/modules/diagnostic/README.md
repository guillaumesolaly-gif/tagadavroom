# Module Diagnostic (exemple)

Formulaire d'auto-évaluation scoré, envoyé par e-mail sur demande explicite du visiteur.
**Aucune donnée n'est stockée en base** (ni réponses, ni coordonnées) : c'est un choix de
confidentialité à conserver si le projet en hérite, pas une limite technique.

## Activer

1. Ajouter `'diagnostic'` à `config/modules.php`.
2. Remplacer `questions.sample.php` par le questionnaire réel du projet (même structure :
   `id`, `question`, `choices` avec un score par réponse).
3. Ajuster les seuils de `gws_diag_level()` au nombre de questions retenu.
4. Côté thème, copier `wp-content/themes/gws-starter/modules/diagnostic/` vers un gabarit de
   page réel (voir le README de ce dossier thème).

## Ce que fait ce fichier

- Vérifie nonce + pot de miel + délai anti-bot + limite de tentatives par IP (transient, pas de
  table dédiée).
- Calcule un score et un niveau, envoie un e-mail formaté à l'adresse « E-mail public » des
  réglages de l'entité.
- Redirige vers l'URL d'origine avec un paramètre `?diagnostic=...` que le gabarit du thème
  utilise pour afficher un message de confirmation ou d'erreur.

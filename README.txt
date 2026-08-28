INSTALLATION

1. WordPress > Apparence > Thèmes > Ajouter un thème > Téléverser un thème.
2. Sélectionner saint-pere-avocat.zip puis Installer et Activer.
3. Réglages > Permaliens > Titre de la publication > Enregistrer.
4. Vérifier la homepage, les pages internes et la page Autodiagnostic créée automatiquement.

AUTODIAGNOSTIC

- Les réponses restent dans le navigateur tant que le visiteur ne demande pas à être recontacté.
- Lors d’une demande de rappel, WordPress transmet les coordonnées, le résultat et les douze réponses à juliette@saint-pere-avocat.fr avec wp_mail().
- Aucun prospect ni aucune réponse ne sont stockés dans la base WordPress.
- Tester impérativement l’envoi depuis le site de préproduction. L’installation locale Local n’est généralement pas configurée pour envoyer de vrais e-mails.
- En production, configurer un envoi SMTP authentifié avec l’adresse contact@saint-pere-avocat.fr si la fonction mail de l’hébergement ne délivre pas correctement les messages.
- Supprimer les demandes sans suite de la boîte de réception au terme de la durée annoncée de trois mois.

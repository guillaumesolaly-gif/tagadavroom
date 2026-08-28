# Changelog — GWS Starter

## 1.1.0

- Ajout du module `qa` (développement uniquement, désactivé par défaut) : recette du design
  system et des composants génériques, CPT jetable pour vérifier champs structurés/archive/
  single/persistance au changement de thème.
- Ajout du flush automatique des permaliens à l'activation/désactivation d'un module métier
  (`includes/modules.php`) — plus aucune étape manuelle dans Réglages > Permaliens.
- Comportement clavier de la modale générique complété (fermeture par Échap, focus posé à
  l'ouverture).
- Correction de la documentation : les pages classiques n'ont jamais eu besoin d'un flush
  manuel des permaliens après activation du thème (aucun CPT/règle de réécriture n'y est
  déclaré) — la checklist de mise en production a été mise à jour en conséquence.

## 1.0.0

Première version du starter, issue de la transformation d'un thème WordPress sur mesure en base
technique générique. Voir `ARCHITECTURE.md` pour le détail des choix structurants.

# Changelog — GWS Equestrian (module)

Historique propre à ce module, distinct de la version du plugin `gws-core` qui l'héberge (voir
`GWSEQ_MODULE_VERSION` dans `module.php`). Le module atteindra la version `1.0.0` au gel de la V1
(fin de l'Étape 9 du plan de développement validé). Chaque étape ci-dessous a été livrée puis
recettée en conditions réelles avant validation de la suivante.

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

# Changelog — GWS Equestrian (module)

Historique propre à ce module, distinct de la version du plugin `gws-core` qui l'héberge (voir
`GWSEQ_MODULE_VERSION` dans `module.php`). Le module atteindra la version `1.0.0` au gel de la V1
(fin de la dernière étape du plan de développement validé). Chaque étape ci-dessous a été livrée
puis recettée en conditions réelles avant validation de la suivante.

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

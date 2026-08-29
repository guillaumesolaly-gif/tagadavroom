<?php
/**
 * GWS Equestrian — module métier pour les professionnels du monde équestre (pension,
 * enseignement, élevage, reproduction, vente).
 *
 * ÉTAPE 1 — FONDATIONS UNIQUEMENT. Voir README.md de ce dossier pour le détail de ce qui est
 * couvert par cette étape et ce qui reste volontairement à construire aux étapes suivantes
 * (formulaires métier, tarification, pedigree, médias, duplication, rendu front...). Ce fichier
 * n'enregistre que la structure minimale nécessaire pour prouver que le module s'intègre
 * proprement au mécanisme d'activation de GWS Core — aucun champ, aucune relation, aucune
 * logique métier ici.
 *
 * Préfixe du module (voir wp-content/plugins/gws-core/modules/README.md) : gwseq_ — jamais
 * gws_ ni gws_core_, réservés au cœur.
 */

if (!defined('ABSPATH')) exit;

const GWSEQ_CPT_PRESTATION = 'gwseq_prestation';
const GWSEQ_CPT_GROUPE = 'gwseq_groupe';
const GWSEQ_CPT_CHEVAL = 'gwseq_cheval';
const GWSEQ_TAX_CATEGORIE_CHEVAL = 'gwseq_categorie_cheval';

require_once __DIR__ . '/includes/post-types.php';
require_once __DIR__ . '/includes/taxonomies.php';

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
const GWSEQ_CPT_MEMBRE = 'gwseq_membre';
const GWSEQ_TAX_CATEGORIE_CHEVAL = 'gwseq_categorie_cheval';

// Version propre au module (distincte de la version du plugin gws-core qui l'héberge) : suit
// l'avancement des étapes du plan de développement, voir CHANGELOG.md de ce dossier. Atteindra
// 1.0.0 au gel de la V1 (fin de l'étape 9).
define('GWSEQ_MODULE_VERSION', '0.29.0');
define('GWSEQ_MODULE_URL', GWS_CORE_URL . 'modules/gws-equestrian/');

/**
 * Le mécanisme de flush existant (gws_core_flag_rewrite_flush(), includes/modules.php de gws-core)
 * ne se déclenche que lorsque la LISTE des modules actifs change — jamais lorsqu'un module déjà
 * actif ajoute une nouvelle règle de réécriture à une version ultérieure (cas du lien de partage
 * privé, `/partage/{token}`, introduit en 0.26.0 — voir includes/cheval-share-admin.php). Ce
 * déclencheur générique, comparé à la version stockée en base, couvre ce cas ET tout ajout futur
 * de règle de réécriture dans ce module, sans geste manuel dans Réglages > Permaliens.
 */
function gwseq_maybe_flag_rewrite_flush_for_version() {
  if (get_option('gwseq_module_version_for_flush') === GWSEQ_MODULE_VERSION) return;
  // Garde defensive volontaire (même raisonnement que gwseq_horse_share_has_seo_plugin() pour
  // gws_has_seo_plugin()) : cette fonction appartient à gws-core (includes/modules.php), un
  // fichier distinct de ce module — jamais garanti chargé par un test qui isole ce seul module.php.
  if (function_exists('gws_core_flag_rewrite_flush')) gws_core_flag_rewrite_flush();
  update_option('gwseq_module_version_for_flush', GWSEQ_MODULE_VERSION, false);
}
add_action('init', 'gwseq_maybe_flag_rewrite_flush_for_version', 1);

require_once __DIR__ . '/includes/post-types.php';
require_once __DIR__ . '/includes/taxonomies.php';
require_once __DIR__ . '/includes/repeater-field.php';
require_once __DIR__ . '/includes/qa-repeater.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/admin-ui.php';
require_once __DIR__ . '/includes/groupe-admin.php';
require_once __DIR__ . '/includes/prestation-fields.php';
require_once __DIR__ . '/includes/prestation-editor.php';
require_once __DIR__ . '/includes/presets.php';
require_once __DIR__ . '/includes/race-referentiel.php';
require_once __DIR__ . '/includes/cheval-fields.php';
require_once __DIR__ . '/includes/cheval-labels.php';
require_once __DIR__ . '/includes/cheval-editor.php';
require_once __DIR__ . '/includes/cheval-categories.php';
require_once __DIR__ . '/includes/pedigree-resolver.php';
require_once __DIR__ . '/includes/cheval-pedigree.php';
require_once __DIR__ . '/includes/cheval-indices.php';
require_once __DIR__ . '/includes/cheval-media.php';
require_once __DIR__ . '/includes/cheval-editorial.php';
require_once __DIR__ . '/includes/cheval-admin-tabs.php';
require_once __DIR__ . '/includes/ifce-pdf-text.php';
require_once __DIR__ . '/includes/ifce-import-parser.php';
require_once __DIR__ . '/includes/ifce-import-mapper.php';
require_once __DIR__ . '/includes/ifce-import-admin.php';
require_once __DIR__ . '/includes/membre-fields.php';
require_once __DIR__ . '/includes/membre-editor.php';
require_once __DIR__ . '/includes/actualites.php';
require_once __DIR__ . '/includes/cheval-share.php';
require_once __DIR__ . '/includes/cheval-share-admin.php';

<?php
/**
 * Commodité de développement : bascule le module QA (Outils > Recette GWS) sans édition
 * manuelle de fichier. N'apparaît et n'agit JAMAIS en dehors d'un environnement local/de
 * développement (wp_get_environment_type()) — la ligne ci-dessous rend ce fichier entièrement
 * inerte ailleurs, y compris en production.
 *
 * Ce n'est pas un mécanisme générique d'activation des modules métier depuis l'admin :
 * config/modules.php reste l'unique source de vérité versionnée pour les vrais modules. Ce
 * fichier ne fait que basculer une option dédiée, lue uniquement par
 * gws_core_qa_dev_toggle_enabled() (includes/modules.php) — le retirer entièrement ne casse
 * rien du fonctionnement normal du plugin, il désactive seulement cette commodité.
 *
 * Ne supprime jamais de contenu : basculer QA ne fait qu'activer/désactiver son module (donc
 * son CPT et ses gabarits), jamais les posts déjà créés, qui restent en base à l'identique.
 */

if (!defined('ABSPATH')) exit;
if (!in_array(wp_get_environment_type(), array('local', 'development'), true)) return;

function gws_core_add_qa_tool_page() {
  add_management_page('Recette GWS', 'Recette GWS', 'manage_options', 'gws-core-qa-tool', 'gws_core_render_qa_tool_page');
}
add_action('admin_menu', 'gws_core_add_qa_tool_page');

function gws_core_handle_qa_toggle() {
  if (!is_admin() || !current_user_can('manage_options')) return;
  if (empty($_POST['gws_qa_toggle_action']) || empty($_GET['page']) || $_GET['page'] !== 'gws-core-qa-tool') return;
  check_admin_referer('gws_core_qa_toggle');
  $enable = sanitize_text_field(wp_unslash($_POST['gws_qa_toggle_action'])) === 'enable';
  update_option('gws_core_qa_dev_enabled', $enable, false);
  // Aucun appel manuel au flush ici : gws_core_detect_module_change() (includes/modules.php,
  // mécanisme inchangé) le détecte tout seul à la requête suivante, exactement comme pour un
  // module basculé via config/modules.php.
  wp_safe_redirect(add_query_arg(array('page' => 'gws-core-qa-tool', 'gws_qa_toggled' => $enable ? '1' : '0'), admin_url('tools.php')));
  exit;
}
add_action('admin_init', 'gws_core_handle_qa_toggle');

function gws_core_render_qa_tool_page() {
  if (!current_user_can('manage_options')) return;
  $enabled = gws_core_qa_dev_toggle_enabled();
  ?>
  <div class="wrap">
    <h1>Recette GWS</h1>
    <p>Bascule de développement pour le module QA (recette du design system et des composants
      du starter). Visible uniquement dans un environnement local/de développement — jamais en
      production.</p>

    <?php if (isset($_GET['gws_qa_toggled'])) : ?>
      <div class="notice notice-success"><p>Module QA <?php echo $_GET['gws_qa_toggled'] === '1' ? 'activé' : 'désactivé'; ?>.</p></div>
    <?php endif; ?>

    <p><strong>État actuel :</strong> <?php echo $enabled ? 'Activé' : 'Désactivé'; ?></p>

    <form method="post">
      <?php wp_nonce_field('gws_core_qa_toggle'); ?>
      <input type="hidden" name="gws_qa_toggle_action" value="<?php echo $enabled ? 'disable' : 'enable'; ?>">
      <?php submit_button($enabled ? 'Désactiver QA' : 'Activer QA'); ?>
    </form>

    <p>Basculer QA n'efface jamais de contenu : le contenu de test déjà créé (page de recette,
      éléments QA) reste en base après une désactivation ici, jusqu'à ce que vous le supprimiez
      vous-même — voir <code>modules/qa/README.md</code>.</p>
  </div>
  <?php
}

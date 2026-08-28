<?php
/**
 * Écran d'administration générique des migrations déclarées (Outils > Migrations).
 * N'affiche que ce que des modules ont explicitement enregistré via gws_core_register_migration().
 */

if (!defined('ABSPATH')) exit;

function gws_core_add_migration_tool_page() {
  add_management_page('Migrations', 'Migrations', 'manage_options', 'gws-core-migrations', 'gws_core_render_migration_tool_page');
}
add_action('admin_menu', 'gws_core_add_migration_tool_page');

function gws_core_handle_migration_actions() {
  if (!is_admin() || !current_user_can('manage_options')) return;
  if (empty($_POST['gws_migration_action']) || empty($_GET['page']) || $_GET['page'] !== 'gws-core-migrations') return;
  check_admin_referer('gws_core_migration_tool');
  $slug = isset($_POST['gws_migration_slug']) ? sanitize_key(wp_unslash($_POST['gws_migration_slug'])) : '';
  $action = sanitize_text_field(wp_unslash($_POST['gws_migration_action']));
  $done = false;
  if ($slug && $action === 'run') $done = gws_core_run_migration($slug);
  if ($slug && $action === 'rollback') $done = gws_core_rollback_migration($slug);
  wp_safe_redirect(add_query_arg(array('page' => 'gws-core-migrations', 'gws_migration_done' => $done ? 1 : 0), admin_url('tools.php')));
  exit;
}
add_action('admin_init', 'gws_core_handle_migration_actions');

function gws_core_render_migration_tool_page() {
  if (!current_user_can('manage_options')) return;
  $migrations = gws_core_get_registered_migrations();
  ?>
  <div class="wrap">
    <h1>Migrations</h1>
    <p>Chaque migration ci-dessous n'a été déclarée que par un module explicitement activé. Rien ne s'exécute automatiquement : une migration ne s'applique qu'au clic sur « Lancer », une seule fois par version.</p>
    <?php if (isset($_GET['gws_migration_done'])) : ?>
      <div class="notice notice-<?php echo $_GET['gws_migration_done'] === '1' ? 'success' : 'error'; ?>"><p><?php echo $_GET['gws_migration_done'] === '1' ? 'Action effectuée.' : 'Rien à faire (déjà appliquée, ou aucune action possible).'; ?></p></div>
    <?php endif; ?>

    <?php if (!$migrations) : ?>
      <p><em>Aucune migration déclarée pour l’instant.</em></p>
    <?php else : ?>
      <table class="widefat striped">
        <thead><tr><th>Migration</th><th>Description</th><th>Version cible</th><th>État</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($migrations as $slug => $migration) :
          $applied_version = gws_core_migration_applied_version($slug);
          $is_applied = $applied_version === $migration['version'];
          ?>
          <tr>
            <td><code><?php echo esc_html($slug); ?></code><br><?php echo esc_html($migration['label']); ?></td>
            <td><?php echo esc_html($migration['description']); ?></td>
            <td><?php echo esc_html($migration['version']); ?></td>
            <td><?php echo $is_applied ? 'Appliquée' : ($applied_version ? 'Appliquée en version ' . esc_html($applied_version) . ' (différente)' : 'Non appliquée'); ?></td>
            <td>
              <?php if (!$is_applied) : ?>
                <form method="post" style="display:inline">
                  <?php wp_nonce_field('gws_core_migration_tool'); ?>
                  <input type="hidden" name="gws_migration_action" value="run">
                  <input type="hidden" name="gws_migration_slug" value="<?php echo esc_attr($slug); ?>">
                  <?php submit_button('Lancer', 'primary', 'submit', false); ?>
                </form>
              <?php endif; ?>
              <?php if ($is_applied && is_callable($migration['rollback'])) : ?>
                <form method="post" style="display:inline" onsubmit="return confirm('Restaurer l’état précédent cette migration ?');">
                  <?php wp_nonce_field('gws_core_migration_tool'); ?>
                  <input type="hidden" name="gws_migration_action" value="rollback">
                  <input type="hidden" name="gws_migration_slug" value="<?php echo esc_attr($slug); ?>">
                  <?php submit_button('Restaurer', 'secondary', 'submit', false); ?>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

    <h2>Journal</h2>
    <?php $log = get_option('gws_core_migration_log', array()); if (!$log) : ?>
      <p>Aucune action journalisée pour l’instant.</p>
    <?php else : ?>
      <table class="widefat striped">
        <thead><tr><th>Date</th><th>Utilisateur</th><th>Migration</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach (array_reverse($log) as $entry) : ?>
          <tr>
            <td><?php echo esc_html($entry['date']); ?></td>
            <td><?php echo esc_html($entry['user']); ?></td>
            <td><code><?php echo esc_html($entry['slug']); ?></code></td>
            <td><?php echo esc_html($entry['action']); ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
  <?php
}

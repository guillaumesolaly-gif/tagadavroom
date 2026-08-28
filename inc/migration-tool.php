<?php
// Outil d'administration explicite de migration de contenu (Outils > Migration de contenu).
//
// Remplace l'ancienne spa_seed_expertise_content(), qui s'exécutait automatiquement sur
// chaque requête `init` et écrasait le post_content d'une page dès qu'un indicateur
// "premier seed" (postmeta) était absent — vrai par défaut sur n'importe quelle page,
// y compris une page historique étrangère (Colibri) qui n'a jamais vu ce thème. C'est ce
// mécanisme qui a détruit silencieusement 6 pages lors de l'incident de mise en production
// du 2.3.1 (voir spa-technical-notes.md pour le détail complet de la cause racine).
//
// Principes de cet outil (cahier des charges du 19/08/2026, suite à l'incident) :
// 1. L'activation du thème ne détruit plus aucun contenu existant (voir inc/content-seed.php).
// 2. La migration ne s'exécute que sur clic explicite, page par page (case à cocher).
// 3. L'ancien contenu est sauvegardé en postmeta avant tout remplacement.
// 4. Idempotente : une page déjà migrée à la version courante est ignorée même si elle est
//    (accidentellement) recochée — la protection est faite côté serveur, pas seulement dans l'UI.
// 5. Chaque migration et chaque restauration est journalisée (option spa_migration_log).
// 6. Restauration possible indépendamment pour chaque page tant que sa sauvegarde existe —
//    la sauvegarde n'est jamais supprimée automatiquement.

function spa_migration_schema_version() {
  return '2.0.0';
}

function spa_migration_page_catalog() {
  return array(
    'prevention-difficultes-entreprise-saint-etienne' => array(
      'title' => 'Prévention des difficultés des entreprises',
      'seo_title' => 'Prévention des difficultés d’entreprise à Saint-Étienne | Saint-Père',
      'seo_description' => 'Avocat à Saint-Étienne pour anticiper les difficultés d’entreprise : mandat ad hoc, conciliation et négociation avec les créanciers.',
    ),
    'sauvegarde-et-redressement-judiciaire' => array(
      'title' => 'Sauvegarde et redressement judiciaire',
      'seo_title' => 'Sauvegarde et redressement judiciaire à Saint-Étienne | Saint-Père',
      'seo_description' => 'Avocat à Saint-Étienne pour préparer et suivre une procédure de sauvegarde ou de redressement judiciaire et préserver l’activité de l’entreprise.',
    ),
    'liquidation-judiciaire-saint-etienne' => array(
      'title' => 'Liquidation judiciaire',
      'seo_title' => 'Avocat en liquidation judiciaire à Saint-Étienne | Saint-Père',
      'seo_description' => 'Accompagnement de l’entreprise et du dirigeant lors d’une liquidation judiciaire à Saint-Étienne : ouverture, actifs, cession et responsabilité.',
    ),
    'contentieux-civil-commercial-saint-etienne' => array(
      'title' => 'Contentieux civil et commercial',
      'seo_title' => 'Avocat contentieux civil et commercial à Saint-Étienne | Saint-Père',
      'seo_description' => 'Avocat en contentieux commercial et civil à Saint-Étienne : contrats, créances, relations commerciales, associés, responsabilité et représentation.',
    ),
    'avocat-postulation-saint-etienne' => array(
      'title' => 'Avocat postulant à Saint-Étienne',
      'seo_title' => 'Avocat postulant à Saint-Étienne | Saint-Père Avocat',
      'seo_description' => 'Postulation et représentation devant les juridictions de Saint-Étienne : suivi de procédure, audiences, communication des actes et information du confrère.',
    ),
    'cessation-de-paiement-que-faire-dirigeant-saint-etienne' => array(
      'title' => 'Cessation des paiements : que faire ?',
      'seo_title' => 'Cessation des paiements : que faire à Saint-Étienne ? | Saint-Père',
      'seo_description' => 'Définition, délai de 45 jours, déclaration et procédures possibles : les démarches du dirigeant face à une cessation des paiements à Saint-Étienne.',
    ),
    'des-solutions-en-cas-de-difficultes-financieres' => array(
      'title' => 'Des solutions en cas de difficultés financières',
      'seo_title' => 'Entreprise en difficulté : les solutions possibles | Saint-Père Avocat',
      'seo_description' => 'Mandat ad hoc, conciliation, sauvegarde, redressement ou liquidation : comprendre les solutions avec l’interview TL7 de Juliette Saint-Père.',
    ),
    'entreprise-en-difficulte-que-faire-saint-etienne' => array(
      'title' => 'Entreprise en difficulté : que faire ?',
      'seo_title' => 'Entreprise en difficulté à Saint-Étienne : que faire ? | Saint-Père',
      'seo_description' => 'Signaux d’alerte, premières démarches et solutions juridiques pour une entreprise en difficulté à Saint-Étienne, dans la Loire et en région AURA.',
    ),
    'eviter-liquidation-judiciaire-entreprise-saint-etienne' => array(
      'title' => 'Comment éviter la liquidation judiciaire ?',
      'seo_title' => 'Comment éviter une liquidation judiciaire à Saint-Étienne ? | Saint-Père',
      'seo_description' => 'Prévention, négociation, conciliation ou redressement : les solutions à examiner pour éviter une liquidation judiciaire lorsque la situation le permet.',
    ),
    'reprise-entreprise-difficulte-investisseur' => array(
      'title' => 'Reprise d’entreprise en difficulté',
      'seo_title' => 'Reprise d’entreprise en difficulté : avocat investisseurs | Saint-Père',
      'seo_description' => 'Accompagnement juridique des investisseurs pour analyser, structurer, déposer et défendre une offre de reprise d’entreprise en difficulté.',
    ),
    'faq-avocat-droit-entreprises-saint-etienne' => array(
      'title' => 'Questions fréquentes',
      'seo_title' => 'FAQ avocat en droit des entreprises à Saint-Étienne | Saint-Père',
      'seo_description' => 'Réponses aux questions des dirigeants sur les difficultés financières, procédures collectives, créanciers, responsabilité, reprise et honoraires.',
    ),
    'trouver-avocat-droit-entreprises-saint-etienne' => array(
      'title' => 'Trouver un avocat en droit des entreprises',
      'seo_title' => 'Avocat en droit des entreprises à Saint-Étienne | Saint-Père',
      'seo_description' => 'Choisir un avocat à Saint-Étienne pour les difficultés d’entreprise, procédures collectives, contentieux commercial et responsabilité du dirigeant.',
    ),
    'mentions-legales' => array(
      'title' => 'Mentions légales',
      'seo_title' => 'Mentions légales | Saint-Père Avocat',
      'seo_description' => 'Informations légales concernant l’éditeur, l’hébergement, la propriété intellectuelle et la médiation de la consommation du site Saint-Père Avocat.',
    ),
    'politique-de-confidentialite' => array(
      'title' => 'Politique de confidentialité',
      'seo_title' => 'Politique de confidentialité | Saint-Père Avocat',
      'seo_description' => 'Traitement des données personnelles, finalités, destinataires, conservation, cookies et exercice de vos droits auprès du cabinet Saint-Père Avocat.',
    ),
    'cgu' => array(
      'title' => 'Conditions générales d’utilisation',
      'seo_title' => 'Conditions générales d’utilisation | Saint-Père Avocat',
      'seo_description' => 'Conditions d’accès et d’utilisation du site Saint-Père Avocat : information juridique, disponibilité, liens externes, propriété intellectuelle et responsabilité.',
    ),
    'gestion-de-cookies' => array(
      'title' => 'Gestion des cookies',
      'seo_title' => 'Gestion des cookies | Saint-Père Avocat',
      'seo_description' => 'Comprendre les cookies utilisés par le site Saint-Père Avocat et modifier à tout moment vos préférences de consentement avec Complianz.',
    ),
  );
}

function spa_migration_looks_like_colibri($content) {
  if (!$content) return false;
  foreach (array('[colibri_', 'data-colibri', 'class="h-section', 'h-section', 'wp-block-colibri', 'data-scroll-effect') as $signal) {
    if (strpos($content, $signal) !== false) return true;
  }
  return false;
}

function spa_migration_log_append($entry) {
  $log = get_option('spa_migration_log', array());
  if (!is_array($log)) $log = array();
  $log[] = $entry;
  if (count($log) > 200) $log = array_slice($log, -200);
  update_option('spa_migration_log', $log, false);
}

// Identification exclusivement par slug (jamais par ID supposé), sauvegarde de l'ancien
// contenu avant tout remplacement, verrouillage par _spa_migration_applied pour empêcher
// une double application accidentelle, journalisation de chaque page traitée.
function spa_run_migration_batch($selected_slugs) {
  $catalog = spa_migration_page_catalog();
  $version = spa_migration_schema_version();
  $timestamp = current_time('mysql');
  $user = wp_get_current_user();
  $results = array();

  foreach ($selected_slugs as $slug) {
    if (!isset($catalog[$slug])) continue;
    $page = get_page_by_path($slug);
    if (!$page) { $results[] = array('slug' => $slug, 'id' => 0, 'status' => 'skipped_missing_page'); continue; }

    if (get_post_meta($page->ID, '_spa_migration_applied', true) === $version) {
      $results[] = array('slug' => $slug, 'id' => $page->ID, 'status' => 'skipped_already_applied');
      continue;
    }

    $seed_file = get_template_directory() . '/seed/' . $slug . '.blocks-v2.html';
    if (!file_exists($seed_file)) { $results[] = array('slug' => $slug, 'id' => $page->ID, 'status' => 'skipped_no_seed_file'); continue; }

    // wp_slash() est indispensable ici : update_post_meta()/wp_update_post() appellent en
    // interne wp_unslash() sur toute valeur reçue (WordPress suppose que l'appelant transmet
    // des données "slashées" comme le ferait $_POST). Sans ce wp_slash(), tout backslash
    // littéral présent dans le contenu Colibri d'origine (fréquent dans le JS/JSON intégré par
    // le page builder — 250 occurrences mesurées sur une seule page réelle) est silencieusement
    // supprimé par stripslashes(), corrompant la sauvegarde censée garantir un rollback fidèle.
    $before_content = (string) $page->post_content;
    if (!metadata_exists('post', $page->ID, '_spa_migration_backup_content')) {
      update_post_meta($page->ID, '_spa_migration_backup_content', wp_slash($before_content));
      update_post_meta($page->ID, '_spa_migration_backup_date', $timestamp);
    }

    $new_content = spa_seed_content($seed_file);
    wp_update_post(array('ID' => $page->ID, 'post_content' => wp_slash($new_content)));
    update_post_meta($page->ID, '_spa_migration_applied', $version);
    update_post_meta($page->ID, '_spa_migration_date', $timestamp);

    if (!spa_has_seo_plugin()) {
      if (!get_post_meta($page->ID, '_spa_seo_title', true)) update_post_meta($page->ID, '_spa_seo_title', $catalog[$slug]['seo_title']);
      if (!get_post_meta($page->ID, '_spa_seo_description', true)) update_post_meta($page->ID, '_spa_seo_description', $catalog[$slug]['seo_description']);
    }

    $results[] = array(
      'slug' => $slug,
      'id' => $page->ID,
      'status' => 'migrated',
      'before_length' => strlen($before_content),
      'after_length' => strlen($new_content),
    );
  }

  if ($results) {
    // Fixes historiques du thème sur son propre contenu (liens internes, page cookies) :
    // version-gated, sans effet si déjà appliqués, ne touchent jamais un contenu étranger.
    spa_migrate_internal_links();
    spa_remove_inactive_cookie_preferences();
    spa_migration_log_append(array(
      'date' => $timestamp,
      'user' => $user ? $user->user_login : '',
      'pages' => $results,
    ));
  }

  return $results;
}

function spa_rollback_migration_page($slug) {
  $page = get_page_by_path($slug);
  if (!$page) return false;
  if (!get_post_meta($page->ID, '_spa_migration_applied', true)) return false;
  if (!metadata_exists('post', $page->ID, '_spa_migration_backup_content')) return false;

  $backup = get_post_meta($page->ID, '_spa_migration_backup_content', true);
  wp_update_post(array('ID' => $page->ID, 'post_content' => wp_slash($backup)));
  delete_post_meta($page->ID, '_spa_migration_applied');
  delete_post_meta($page->ID, '_spa_migration_date');
  // La sauvegarde elle-même n'est jamais supprimée : elle reste disponible si la page est
  // remigrée puis restaurée à nouveau plus tard.

  spa_migration_log_append(array(
    'date' => current_time('mysql'),
    'user' => wp_get_current_user() ? wp_get_current_user()->user_login : '',
    'pages' => array(array('slug' => $slug, 'id' => $page->ID, 'status' => 'rolled_back')),
  ));
  return true;
}

function spa_add_migration_tool_page() {
  add_management_page('Migration de contenu', 'Migration de contenu', 'manage_options', 'spa-migration-tool', 'spa_render_migration_tool_page');
}
add_action('admin_menu', 'spa_add_migration_tool_page');

// Traité sur admin_init (avant tout envoi de sortie) pour pouvoir rediriger proprement après
// l'action, plutôt que dans le callback d'affichage de la page (sortie déjà commencée).
function spa_handle_migration_actions() {
  if (!is_admin() || !current_user_can('manage_options')) return;
  if (empty($_POST['spa_migration_action']) || empty($_GET['page']) || $_GET['page'] !== 'spa-migration-tool') return;
  check_admin_referer('spa_migration_tool');

  $action = sanitize_text_field(wp_unslash($_POST['spa_migration_action']));

  if ($action === 'migrate') {
    $selected = array();
    if (!empty($_POST['spa_migrate_slugs']) && is_array($_POST['spa_migrate_slugs'])) {
      $selected = array_map('sanitize_title', wp_unslash($_POST['spa_migrate_slugs']));
    }
    $results = spa_run_migration_batch($selected);
    wp_safe_redirect(add_query_arg(array('page' => 'spa-migration-tool', 'spa_migration_done' => count($results)), admin_url('tools.php')));
    exit;
  }

  if ($action === 'rollback') {
    $slug = !empty($_POST['spa_rollback_slug']) ? sanitize_title(wp_unslash($_POST['spa_rollback_slug'])) : '';
    $ok = $slug ? spa_rollback_migration_page($slug) : false;
    wp_safe_redirect(add_query_arg(array('page' => 'spa-migration-tool', 'spa_rollback_done' => $ok ? 1 : 0), admin_url('tools.php')));
    exit;
  }
}
add_action('admin_init', 'spa_handle_migration_actions');

function spa_render_migration_tool_page() {
  if (!current_user_can('manage_options')) return;
  $catalog = spa_migration_page_catalog();
  $version = spa_migration_schema_version();
  ?>
  <div class="wrap">
    <h1>Migration de contenu</h1>
    <p>Remplace le contenu historique des pages ci-dessous par le contenu validé du nouveau thème,
      page par page. <strong>L'activation du thème ne modifie plus aucun contenu existant</strong> —
      rien ne se passe ici tant que vous ne cochez pas une page et ne cliquez pas sur « Lancer la
      migration ». L'ancien contenu de chaque page migrée est sauvegardé et peut être restauré
      individuellement ci-dessous.</p>

    <?php if (isset($_GET['spa_migration_done'])) : ?>
      <div class="notice notice-success"><p><?php echo esc_html((int) $_GET['spa_migration_done']); ?> page(s) traitée(s) — voir le détail dans le journal en bas de page.</p></div>
    <?php endif; ?>
    <?php if (isset($_GET['spa_rollback_done'])) : ?>
      <?php if ($_GET['spa_rollback_done'] === '1') : ?>
        <div class="notice notice-success"><p>Contenu restauré à sa valeur précédant la migration.</p></div>
      <?php else : ?>
        <div class="notice notice-error"><p>Rien à restaurer pour cette page (aucune migration appliquée ou aucune sauvegarde disponible).</p></div>
      <?php endif; ?>
    <?php endif; ?>

    <form method="post">
      <?php wp_nonce_field('spa_migration_tool'); ?>
      <input type="hidden" name="spa_migration_action" value="migrate">
      <table class="widefat striped">
        <thead><tr><th style="width:2em"></th><th>Page</th><th>Slug</th><th>ID</th><th>État actuel</th><th>Alerte</th></tr></thead>
        <tbody>
        <?php foreach ($catalog as $slug => $info) :
          $page = get_page_by_path($slug);
          $seed_file = get_template_directory() . '/seed/' . $slug . '.blocks-v2.html';
          $checkbox_disabled = true;
          if (!$page) {
            $state = 'Page absente — sera créée (vide) à l’activation du thème, à migrer ensuite.';
          } else {
            $current = trim((string) $page->post_content);
            $seed_content = file_exists($seed_file) ? trim(spa_seed_content($seed_file)) : '';
            $applied = get_post_meta($page->ID, '_spa_migration_applied', true);
            if ($applied === $version) {
              $state = 'Déjà migrée le ' . esc_html(get_post_meta($page->ID, '_spa_migration_date', true)) . '.';
            } elseif ($current !== '' && $current === $seed_content) {
              $state = 'Contenu déjà identique au contenu validé — rien à migrer.';
            } elseif ($current === '') {
              $state = 'Page vide — aucun contenu existant à perdre.';
              $checkbox_disabled = false;
            } else {
              $state = 'Contenu différent détecté (' . number_format_i18n(strlen($current)) . ' caractères) — à valider avant migration.';
              $checkbox_disabled = false;
            }
          }
          $colibri_alert = $page && spa_migration_looks_like_colibri($page->post_content);
          ?>
          <tr>
            <td><?php if ($page && !$checkbox_disabled) : ?><input type="checkbox" name="spa_migrate_slugs[]" value="<?php echo esc_attr($slug); ?>"><?php endif; ?></td>
            <td><?php echo esc_html($info['title']); ?></td>
            <td><code><?php echo esc_html($slug); ?></code></td>
            <td><?php echo $page ? (int) $page->ID : '—'; ?></td>
            <td><?php echo esc_html($state); ?></td>
            <td><?php if ($colibri_alert) : ?><span style="color:#a00;font-weight:600">⚠ markup Colibri détecté</span><?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <p><?php submit_button('Lancer la migration sur les pages cochées', 'primary', 'submit', false); ?></p>
    </form>

    <h2>Restaurer une page migrée</h2>
    <p>Disponible tant qu'une sauvegarde existe pour la page, même après validation.</p>
    <table class="widefat striped">
      <thead><tr><th>Slug</th><th>Migrée le</th><th>Sauvegarde du</th><th></th></tr></thead>
      <tbody>
      <?php $has_any_backup = false; foreach ($catalog as $slug => $info) :
        $page = get_page_by_path($slug);
        if (!$page || !metadata_exists('post', $page->ID, '_spa_migration_backup_content')) continue;
        $has_any_backup = true;
        $applied = get_post_meta($page->ID, '_spa_migration_applied', true);
        ?>
        <tr>
          <td><code><?php echo esc_html($slug); ?></code></td>
          <td><?php echo $applied ? esc_html(get_post_meta($page->ID, '_spa_migration_date', true)) : '—'; ?></td>
          <td><?php echo esc_html(get_post_meta($page->ID, '_spa_migration_backup_date', true)); ?></td>
          <td>
            <?php if ($applied) : ?>
            <form method="post" onsubmit="return confirm('Restaurer le contenu de cette page à sa valeur précédant la migration ?');" style="display:inline">
              <?php wp_nonce_field('spa_migration_tool'); ?>
              <input type="hidden" name="spa_migration_action" value="rollback">
              <input type="hidden" name="spa_rollback_slug" value="<?php echo esc_attr($slug); ?>">
              <?php submit_button('Restaurer', 'secondary', 'submit', false); ?>
            </form>
            <?php else : ?>
              <em>Non migrée actuellement.</em>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; if (!$has_any_backup) : ?>
        <tr><td colspan="4">Aucune sauvegarde pour l’instant.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>

    <h2>Journal des migrations</h2>
    <?php $log = get_option('spa_migration_log', array()); if (!$log) : ?>
      <p>Aucune migration effectuée pour l’instant.</p>
    <?php else : ?>
      <table class="widefat striped">
        <thead><tr><th>Date</th><th>Utilisateur</th><th>Page</th><th>Action</th><th>Avant / après</th></tr></thead>
        <tbody>
        <?php foreach (array_reverse($log) as $batch) : foreach ($batch['pages'] as $entry) : ?>
          <tr>
            <td><?php echo esc_html($batch['date']); ?></td>
            <td><?php echo esc_html($batch['user']); ?></td>
            <td><code><?php echo esc_html($entry['slug']); ?></code> (#<?php echo (int) $entry['id']; ?>)</td>
            <td><?php echo esc_html($entry['status']); ?></td>
            <td><?php echo isset($entry['before_length']) ? esc_html($entry['before_length'] . ' → ' . $entry['after_length'] . ' caractères') : '—'; ?></td>
          </tr>
        <?php endforeach; endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
  <?php
}

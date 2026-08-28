<?php
// Création des pages initiales (insert-only, jamais de remplacement de contenu existant) et
// SEO par défaut de la homepage. Le remplacement de contenu de page existante est réservé à
// l'outil d'administration explicite : inc/migration-tool.php.

function spa_create_initial_pages() {
  $pages = array(
    'accueil' => 'Accueil',
    'prevention-difficultes-entreprise-saint-etienne' => 'Prévention des difficultés des entreprises',
    'sauvegarde-et-redressement-judiciaire' => 'Sauvegarde et redressement judiciaire',
    'liquidation-judiciaire-saint-etienne' => 'Liquidation judiciaire',
    'contentieux-civil-commercial-saint-etienne' => 'Contentieux civil et commercial',
    'avocat-postulation-saint-etienne' => 'Avocat postulant à Saint-Étienne',
    'cessation-de-paiement-que-faire-dirigeant-saint-etienne' => 'Cessation des paiements : que faire ?',
    'des-solutions-en-cas-de-difficultes-financieres' => 'Des solutions en cas de difficultés financières',
    'entreprise-en-difficulte-que-faire-saint-etienne' => 'Entreprise en difficulté : que faire ?',
    'eviter-liquidation-judiciaire-entreprise-saint-etienne' => 'Comment éviter la liquidation judiciaire ?',
    'reprise-entreprise-difficulte-investisseur' => 'Reprise d’entreprise en difficulté',
    'faq-avocat-droit-entreprises-saint-etienne' => 'Questions fréquentes',
    'trouver-avocat-droit-entreprises-saint-etienne' => 'Trouver un avocat en droit des entreprises',
    'mentions-legales' => 'Mentions légales',
    'politique-de-confidentialite' => 'Politique de confidentialité',
    'cgu' => 'Conditions générales d’utilisation',
    'gestion-de-cookies' => 'Gestion des cookies',
    'diagnostic-entreprise-en-difficulte' => 'Autodiagnostic des difficultés de l’entreprise',
  );
  // Un site qui a déjà une page d'accueil statique valide (activation sur un
  // WordPress existant, ex. migration depuis Colibri) n'a pas besoin — et ne
  // doit surtout pas recevoir — d'une page "accueil" créée par le thème : la
  // repointer écraserait silencieusement show_on_front/page_on_front, qui
  // sont des options WordPress globales, indépendantes du thème, que la
  // réactivation d'un thème précédent ne restaure jamais. C'est exactement
  // ce qui a rendu le retour à Colibri inopérant lors de l'incident de mise
  // en production du 2.3.1 (voir spa-technical-notes.md).
  $front_page_id = (int) get_option('page_on_front');
  $front_page = $front_page_id ? get_post($front_page_id) : null;
  $has_valid_front_page = $front_page && $front_page->post_type === 'page' && $front_page->post_status !== 'trash';

  foreach ($pages as $slug => $title) {
    if ($slug === 'accueil' && $has_valid_front_page) continue;
    if (!get_page_by_path($slug)) {
      wp_insert_post(array('post_title' => $title, 'post_name' => $slug, 'post_type' => 'page', 'post_status' => 'publish'));
    }
  }
  if (!$has_valid_front_page) {
    $home = get_page_by_path('accueil');
    if ($home) {
      update_option('show_on_front', 'page');
      update_option('page_on_front', $home->ID);
    }
  }
  flush_rewrite_rules();
}
add_action('after_switch_theme', 'spa_create_initial_pages');

// Les fichiers seed/*.html deviennent du contenu Gutenberg stocké en base : ils ne
// peuvent pas contenir de PHP, donc pas de spa_get_cabinet_setting() à l'intérieur.
// On garde les valeurs par défaut du cabinet comme texte dans les seeds, et on les
// substitue une fois, ici, au moment de l'écriture en base — pas à chaque affichage
// (contrairement à l'ancien spa_apply_global_navigation) — pour qu'il n'existe qu'une
// seule source de vérité pour une donnée configurable (téléphone, e-mails, adresse).
function spa_seed_content($file) {
  $html = (string) file_get_contents($file);
  $defaults = spa_cabinet_defaults();
  $html = str_replace($defaults['phone_display'], spa_get_cabinet_setting('phone_display'), $html);
  $html = str_replace('+33477417615', spa_cabinet_phone_href(), $html);
  $html = str_replace($defaults['diagnostic_email'], spa_get_cabinet_setting('diagnostic_email'), $html);
  $html = str_replace($defaults['public_email'], spa_get_cabinet_setting('public_email'), $html);
  $html = str_replace($defaults['address_line'], spa_get_cabinet_setting('address_line'), $html);
  $html = str_replace($defaults['postal_code'] . ' ' . $defaults['city'], spa_get_cabinet_setting('postal_code') . ' ' . spa_get_cabinet_setting('city'), $html);
  return $html;
}

// L'ancienne spa_seed_expertise_content(), exécutée automatiquement sur chaque
// requête `init`, écrasait le post_content de ces 16 pages dès qu'un
// indicateur "premier seed" (postmeta _spa_..._seeded) était absent — or cet
// indicateur est absent par défaut sur N'IMPORTE QUELLE page, y compris une
// page historique étrangère (Colibri) qui n'a jamais vu ce thème. C'est ce
// qui a détruit silencieusement 6 pages de production lors de l'incident de
// mise en production du 2.3.1. Le remplacement du contenu se fait désormais
// uniquement via l'outil d'administration explicite (inc/migration-tool.php,
// menu Outils > Migration de contenu), qui sauvegarde l'ancien contenu, ne
// s'applique qu'aux pages cochées à la main et journalise chaque action.
// Voir spa-technical-notes.md pour le détail de l'incident et du mécanisme.

function spa_seed_home_seo() {
  $home_id = (int) get_option('page_on_front');
  if (!$home_id || spa_has_seo_plugin()) return;
  if (!get_post_meta($home_id, '_spa_seo_title', true)) update_post_meta($home_id, '_spa_seo_title', 'Avocat entreprises en difficulté à Saint-Étienne | Saint-Père');
  if (!get_post_meta($home_id, '_spa_seo_description', true)) update_post_meta($home_id, '_spa_seo_description', 'Maître Juliette Saint-Père accompagne à Saint-Étienne les entreprises, dirigeants et investisseurs en prévention, procédures collectives et contentieux.');
}
add_action('init', 'spa_seed_home_seo', 25);

function spa_complianz_document_fallback() {
  if (!shortcode_exists('cmplz-document')) {
    add_shortcode('cmplz-document', function () {
      return '<div class="complianz-fallback"><strong>Document Complianz non disponible.</strong><p>Activez et configurez l’extension Complianz pour afficher automatiquement l’inventaire des cookies.</p></div>';
    });
  }
}
add_action('init', 'spa_complianz_document_fallback', 99);


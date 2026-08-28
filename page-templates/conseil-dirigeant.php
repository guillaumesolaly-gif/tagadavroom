<?php
/**
 * Template Name: Conseil aux dirigeants
 *
 * Gabarit unique, partagé par toutes les pages de la rubrique "Conseils aux dirigeants".
 * Un nouveau Conseil = une nouvelle page WordPress utilisant ce même gabarit, sans code
 * supplémentaire. N'affecte aucune page existante : ce fichier n'est chargé que pour les
 * pages qui lui sont explicitement assignées.
 */
get_header();
$post_id = get_queried_object_id();
$category = spa_conseil_value($post_id, '_spa_conseil_category', 'Conseils aux dirigeants');
$short_title = spa_conseil_value($post_id, '_spa_conseil_short_title', get_the_title($post_id));
$hero_icon = spa_conseil_value($post_id, '_spa_conseil_hero_icon', 'info');
$hero_title = spa_conseil_value($post_id, '_spa_conseil_hero_title');
$hero_text = spa_conseil_value($post_id, '_spa_conseil_hero_text');
$aside_kicker = spa_conseil_value($post_id, '_spa_conseil_aside_kicker', 'Une question sur votre situation ?');
$aside_title = spa_conseil_value($post_id, '_spa_conseil_aside_title', 'Échangez d’abord avec le cabinet.');
$aside_text = spa_conseil_value($post_id, '_spa_conseil_aside_text');
$h1_main = spa_conseil_value($post_id, '_spa_conseil_h1_main', get_the_title($post_id));
$h1_accent = spa_conseil_value($post_id, '_spa_conseil_h1_accent', '');
?>
<main id="contenu" tabindex="-1" class="conseil-page">
<?php get_template_part('template-parts/site-header'); ?>
<section class="conseil-hero">
  <div>
    <div class="breadcrumb" aria-label="Fil d’Ariane"><a href="<?php echo esc_url(home_url('/')); ?>">Accueil</a><span>→</span><a href="<?php echo esc_url(home_url('/' . SPA_CONSEILS_HUB_SLUG . '/')); ?>">Conseils aux dirigeants</a><span>→</span><span><?php echo esc_html($short_title); ?></span></div>
    <p class="kicker"><?php echo esc_html($category); ?></p>
    <h1><span class="conseil-h1-main"><?php echo esc_html($h1_main); ?></span><?php if ($h1_accent) : ?> <em class="conseil-h1-accent"><?php echo esc_html($h1_accent); ?></em><?php endif; ?></h1>
  </div>
  <aside class="conseil-hero-note">
    <?php echo spa_icon($hero_icon); ?>
    <?php if ($hero_title) : ?><strong><?php echo esc_html($hero_title); ?></strong><?php endif; ?>
    <?php if ($hero_text) : ?><p><?php echo esc_html($hero_text); ?></p><?php endif; ?>
  </aside>
</section>
<section class="reading-layout">
  <article class="conseil-article legal-article">
    <?php while (have_posts()) : the_post(); the_content(); endwhile; ?>
  </article>
  <aside class="conversion-aside">
    <p class="kicker"><?php echo esc_html($aside_kicker); ?></p>
    <h2><?php echo esc_html($aside_title); ?></h2>
    <?php if ($aside_text) : ?><p><?php echo esc_html($aside_text); ?></p><?php endif; ?>
    <?php spa_render_contact_card(get_the_title($post_id)); ?>
  </aside>
</section>
<?php
  $related_items = array();
  for ($i = 0; $i < 3; $i++) {
    $label = spa_conseil_value($post_id, '_spa_conseil_related_label_' . $i);
    $url = spa_conseil_value($post_id, '_spa_conseil_related_url_' . $i);
    if ($label && $url) $related_items[] = array('label' => $label, 'url' => $url);
  }
?>
<?php if ($related_items) : ?>
<section class="related-pages compact-related">
  <div><p class="kicker">Pour aller plus loin</p><h2>Approfondir votre situation.</h2></div>
  <div class="related-links">
    <?php foreach ($related_items as $item) : ?><a href="<?php echo esc_url($item['url']); ?>"><span><?php echo esc_html($item['label']); ?></span><b>→</b></a><?php endforeach; ?>
  </div>
</section>
<?php endif; ?>
<section class="contact" id="contact">
  <div><p class="kicker">Contacter le cabinet</p><h2>Évaluons rapidement<br>votre situation.</h2><p>Contactez directement le cabinet par téléphone ou par e-mail.</p></div>
  <?php spa_render_contact_card(get_the_title($post_id)); ?>
</section>
<div class="contact-float" aria-hidden="false">
  <button type="button" aria-expanded="false" aria-controls="conseil-contact-menu"><?php echo spa_icon('chat_bubble'); ?><strong>Échanger avec le cabinet</strong></button>
</div>
<button class="back-to-top" type="button" aria-label="Retour en haut"><?php echo spa_icon('arrow_upward'); ?></button>
<?php get_template_part('template-parts/site-footer'); ?>
</main>
<?php get_footer(); ?>

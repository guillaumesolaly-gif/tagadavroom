<?php
/**
 * Template Name: Conseils aux dirigeants — hub
 *
 * Page hub unique de la rubrique. Regroupe automatiquement toute page publiée utilisant le
 * gabarit "Conseil aux dirigeants" (spa_conseils_by_category()) : ajouter un futur Conseil
 * n'implique aucune modification de ce fichier.
 */
get_header();
$grouped = spa_conseils_by_category();
$category_icons = array(
  'Trésorerie et dettes' => 'monitoring',
  'Banques et créanciers' => 'account_balance',
  'Agir avant la procédure collective' => 'shield',
  'Redressement et liquidation' => 'gavel',
);
?>
<main id="contenu" tabindex="-1" class="conseils-hub-page">
<?php get_template_part('template-parts/site-header'); ?>
<section class="conseil-hero">
  <div>
    <div class="breadcrumb" aria-label="Fil d’Ariane"><a href="<?php echo esc_url(home_url('/')); ?>">Accueil</a><span>→</span><span>Conseils aux dirigeants</span></div>
    <p class="kicker">Comprendre avant d’agir</p>
    <h1>Conseils aux dirigeants.</h1>
    <p class="conseil-hero-lead">Vous rencontrez des difficultés avec votre entreprise ?</p>
    <p class="conseil-hero-intro">Retards de paiement, trésorerie insuffisante, pression des créanciers, difficultés bancaires… Retrouvez des réponses concrètes aux principales situations auxquelles peut être confronté un dirigeant, et les solutions qui peuvent être envisagées.</p>
  </div>
  <aside class="conseil-hero-note">
    <?php echo spa_icon('route'); ?>
    <strong>Trois façons d’avancer</strong>
    <p>Vous savez déjà quelle solution vous recherchez ? Consultez <a href="<?php echo esc_url(home_url('/#expertises')); ?>">les expertises du cabinet</a>. Vous connaissez votre problème mais pas encore la solution ? Consultez les conseils aux dirigeants. Vous ne savez pas précisément où en est votre entreprise ? <a href="<?php echo esc_url(home_url('/diagnostic-entreprise-en-difficulte/')); ?>">Faites le diagnostic</a>.</p>
  </aside>
</section>

<?php foreach ($grouped as $category => $pages) : ?>
<section class="conseils-hub-category">
  <div class="conseils-hub-category-heading">
    <span class="icon-badge"><?php echo spa_icon(isset($category_icons[$category]) ? $category_icons[$category] : 'info'); ?></span>
    <h2><?php echo esc_html($category); ?></h2>
  </div>
  <div class="conseils-hub-cards">
    <?php foreach ($pages as $page) :
      $matomo_label = esc_attr(get_the_title($page));
    ?><a class="conseil-card" href="<?php echo esc_url(get_permalink($page)); ?>" data-conseil-hub-card="<?php echo $matomo_label; ?>">
      <h3><?php echo esc_html(get_the_title($page)); ?></h3>
      <span class="conseil-card-go">Lire le conseil <b>→</b></span>
    </a><?php endforeach; ?>
  </div>
</section>
<?php endforeach; ?>

<section class="diagnostic-inline-cta">
  <div>
    <p class="kicker">Vous ne savez pas par où commencer ?</p>
    <h2>Identifiez les principaux signaux d’alerte.</h2>
    <p>Quelques questions permettent de faire un premier point sur la situation de votre entreprise. Le résultat est immédiat et vos réponses ne sont transmises que si vous demandez à être recontacté.</p>
  </div>
  <a class="btn" href="<?php echo esc_url(home_url('/diagnostic-entreprise-en-difficulte/')); ?>" data-conseil-cta="diagnostic">Évaluer la situation de mon entreprise <b>→</b></a>
</section>

<section class="related-pages compact-related">
  <div><p class="kicker">Pour aller plus loin</p><h2>Vous savez déjà ce que vous recherchez ?</h2></div>
  <div class="related-links">
    <a href="<?php echo esc_url(home_url('/#expertises')); ?>" data-conseil-cta="expertise"><span>Consulter nos expertises (prévention, sauvegarde, liquidation, contentieux)</span><b>→</b></a>
    <a href="<?php echo esc_url(home_url('/faq-avocat-droit-entreprises-saint-etienne/')); ?>"><span>Une question précise et ponctuelle ? Consultez la FAQ</span><b>→</b></a>
  </div>
</section>

<section class="contact" id="contact">
  <div><p class="kicker">Contacter le cabinet</p><h2>Votre situation nécessite<br>un premier échange ?</h2><p>Contactez directement le cabinet par téléphone ou par e-mail.</p></div>
  <?php spa_render_contact_card('Conseils aux dirigeants'); ?>
</section>
<div class="contact-float" aria-hidden="false">
  <button type="button" aria-expanded="false" aria-controls="conseils-hub-contact-menu"><?php echo spa_icon('chat_bubble'); ?><strong>Échanger avec le cabinet</strong></button>
</div>
<button class="back-to-top" type="button" aria-label="Retour en haut"><?php echo spa_icon('arrow_upward'); ?></button>
<?php get_template_part('template-parts/site-footer'); ?>
</main>
<?php get_footer(); ?>

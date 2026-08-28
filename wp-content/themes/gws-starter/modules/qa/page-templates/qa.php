<?php
/**
 * Template Name: QA — Recette (dev uniquement)
 *
 * Page de recette technique du design system et des composants du starter — À NE JAMAIS
 * UTILISER SUR UN SITE EN PRODUCTION. Copier ce fichier vers page-templates/qa.php à la racine
 * du thème pour l'activer (voir modules/README.md du thème). Ne modifie jamais page.php.
 */

add_action('wp_enqueue_scripts', function () {
  if (!is_page_template('page-templates/qa.php')) return;
  wp_enqueue_style('gws-qa', GWS_THEME_URI . '/modules/qa/assets/qa.css', array('gws-components'), GWS_THEME_VERSION);
});

get_header();
?>
<main id="contenu" tabindex="-1">
  <?php get_template_part('template-parts/site-header'); ?>
  <div class="container qa-page">
    <p class="qa-warning">Page de recette technique — à supprimer avant la mise en production d’un site réel (voir modules/qa/README.md).</p>

    <h1><?php the_title(); ?></h1>

    <section>
      <h2>Titres</h2>
      <h1>Titre H1 de test</h1>
      <h2>Titre H2 de test</h2>
      <h3>Titre H3 de test</h3>
    </section>

    <section>
      <h2>Liens</h2>
      <p><a href="#">Lien de test</a> — vérifier la couleur et le style au survol et au focus clavier.</p>
    </section>

    <section>
      <h2>Boutons</h2>
      <p>
        <button class="btn btn-primary" type="button">Bouton principal</button>
        <button class="btn btn-secondary" type="button">Bouton secondaire</button>
      </p>
    </section>

    <section>
      <h2>Cartes</h2>
      <div class="card-grid">
        <article class="card">
          <div class="card-body">
            <h3 class="card-title"><a href="#">Titre de carte</a></h3>
            <p class="card-excerpt">Texte d’exemple pour vérifier la mise en page d’une carte.</p>
            <a class="card-link" href="#">Lire <?php echo gws_icon('arrow_forward'); ?></a>
          </div>
        </article>
        <article class="card">
          <div class="card-body">
            <h3 class="card-title"><a href="#">Seconde carte</a></h3>
            <p class="card-excerpt">Une deuxième carte pour vérifier la grille responsive.</p>
            <a class="card-link" href="#">Lire <?php echo gws_icon('arrow_forward'); ?></a>
          </div>
        </article>
      </div>
      <p><small>La véritable partition de carte (<code>template-parts/content/card.php</code>) est testée en conditions réelles sur l’archive du CPT QA, pas ici.</small></p>
    </section>

    <section>
      <h2>Icônes</h2>
      <div class="qa-icon-grid">
        <?php foreach (gws_icon_glyphs() as $name => $path) : ?>
          <div class="qa-icon-item"><?php echo gws_icon($name); ?><span><?php echo esc_html($name); ?></span></div>
        <?php endforeach; ?>
      </div>
    </section>

    <section>
      <h2>Champs simples</h2>
      <form class="form" onsubmit="return false;">
        <p class="form-field"><label for="qa-text">Champ texte</label><input type="text" id="qa-text"></p>
        <p class="form-field"><label for="qa-select">Champ liste</label><select id="qa-select"><option>Option A</option><option>Option B</option></select></p>
        <p class="form-field"><label><input type="checkbox"> Case à cocher</label></p>
      </form>
    </section>

    <section>
      <h2>Formulaire de contact (fonctionnel)</h2>
      <?php get_template_part('template-parts/forms/contact-form'); ?>
    </section>

    <section>
      <h2>Modale</h2>
      <button class="btn btn-secondary" type="button" data-modal-open="qa-modal">Ouvrir la modale de test</button>
      <div class="modal" id="qa-modal">
        <div class="modal-panel">
          <h3>Modale de test</h3>
          <p>Vérifier l’ouverture, la fermeture au clic, et la fermeture au clavier (touche Échap).</p>
          <button class="btn btn-primary" type="button" data-modal-close>Fermer</button>
        </div>
      </div>
    </section>

    <section>
      <h2>États focus clavier</h2>
      <p>Depuis ce paragraphe, naviguer avec la touche Tab et vérifier qu’un contour de focus visible apparaît sur chaque élément :</p>
      <p>
        <a href="#">Lien</a> —
        <button class="btn btn-primary" type="button">Bouton</button> —
        <input type="text" placeholder="Champ texte">
      </p>
    </section>

    <?php while (have_posts()) : the_post(); ?>
      <?php the_content(); ?>
    <?php endwhile; ?>
  </div>
  <?php get_template_part('template-parts/site-footer'); ?>
</main>
<?php get_footer(); ?>

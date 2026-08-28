<?php
/**
 * Template Name: QA — Recette (dev uniquement)
 *
 * Page de recette technique du design system et des composants du starter — À NE JAMAIS
 * UTILISER SUR UN SITE EN PRODUCTION. Reste physiquement dans ce dossier : le chargeur
 * générique du thème (inc/module-templates.php) le rend disponible sans copie. Ne modifie
 * jamais page.php.
 */

add_action('wp_enqueue_scripts', function () {
  if (!is_page_template('modules/qa/page-templates/qa.php')) return;
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
      <p>À tester : le focus doit se placer dans la modale à l’ouverture, Tab/Maj+Tab doivent
        rester à l’intérieur (essayer de sortir avec Tab depuis le dernier élément, ou Maj+Tab
        depuis le premier), Échap et le bouton « Fermer » doivent tous deux fermer la modale, et
        le focus doit revenir sur ce bouton après fermeture.</p>
      <p id="qa-pre-existing-inert" inert>Élément volontairement <code>inert</code> avant même
        l’ouverture de la modale (inspecter son attribut dans les outils de développement). Il
        doit conserver cet attribut après une ouverture puis une fermeture de la modale
        ci-dessous — la modale ne doit jamais retirer un <code>inert</code> qu’elle n’a pas
        posé elle-même.</p>
      <button class="btn btn-secondary" type="button" data-modal-open="qa-modal" aria-expanded="false">Ouvrir la modale de test</button>
      <div class="modal" id="qa-modal" role="dialog" aria-modal="true" aria-labelledby="qa-modal-title">
        <div class="modal-panel">
          <h3 id="qa-modal-title">Modale de test</h3>
          <p>Vérifier l’ouverture, la fermeture au clic, et la fermeture au clavier (touche Échap).</p>
          <p><a href="#">Lien de test dans la modale</a></p>
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

    <section>
      <h2>Réglages génériques de l’entité</h2>
      <p>Vérifie que les réglages ajoutés en v1.4.0 (Réglages &gt; Entité) sont bien
        récupérables via les helpers de <code>gws-core</code>, et qu’aucune valeur vide n’est
        jamais produite en façade ou dans le Schema quand un champ est laissé vide.</p>

      <h3>Logo</h3>
      <p>
        <?php if (gws_get_logo_url()) : ?>
          Logo renseigné — il doit s’afficher dans l’en-tête de cette page (tout en haut), à la
          place du nom de l’entité en texte.
        <?php else : ?>
          Aucun logo renseigné — l’en-tête de cette page doit afficher le nom de l’entité en
          texte (secours normal, pas une erreur).
        <?php endif; ?>
      </p>

      <h3>Contact</h3>
      <ul>
        <li>Téléphone : <?php echo gws_get_setting('phone_display') ? esc_html(gws_get_setting('phone_display')) : '(non renseigné)'; ?></li>
        <li>E-mail : <?php echo gws_get_setting('public_email') ? esc_html(gws_get_setting('public_email')) : '(non renseigné)'; ?></li>
        <li>WhatsApp (<code>gws_core_whatsapp_url()</code>) :
          <?php $gws_qa_wa = function_exists('gws_core_whatsapp_url') ? gws_core_whatsapp_url() : ''; ?>
          <?php echo $gws_qa_wa ? esc_html($gws_qa_wa) : '(non renseigné, ou numéro saisi sans indicatif international)'; ?>
        </li>
      </ul>
      <p><small>Le numéro doit être saisi au format international (ex. <code>+33 6 12 34 56 78</code>).
        Tester aussi une saisie nationale sans indicatif (ex. <code>06 12 34 56 78</code>) : le
        résultat ci-dessus doit alors être « non renseigné » — aucun indicatif n’est jamais
        deviné automatiquement, contrairement au comportement corrigé en v1.5.0.</small></p>

      <h3>Réseaux sociaux structurés (<code>gws_core_social_links()</code>, inclut X)</h3>
      <?php $gws_qa_social = function_exists('gws_core_social_links') ? gws_core_social_links() : array(); ?>
      <?php if ($gws_qa_social) : ?>
        <ul>
          <?php foreach ($gws_qa_social as $gws_qa_network => $gws_qa_url) : ?>
            <li><?php echo esc_html(ucfirst($gws_qa_network)); ?> : <a href="<?php echo esc_url($gws_qa_url); ?>"><?php echo esc_html($gws_qa_url); ?></a></li>
          <?php endforeach; ?>
        </ul>
      <?php else : ?>
        <p>Aucun réseau social renseigné pour l’instant (tester en particulier le champ X).</p>
      <?php endif; ?>

      <h3>Google Business Profile (<code>gws_core_google_business_url()</code>)</h3>
      <?php $gws_qa_gbp = function_exists('gws_core_google_business_url') ? gws_core_google_business_url() : ''; ?>
      <p><?php echo $gws_qa_gbp ? '<a href="' . esc_url($gws_qa_gbp) . '">' . esc_html($gws_qa_gbp) . '</a>' : '(non renseigné)'; ?></p>

      <h3><code>sameAs</code> Schema.org (<code>gws_core_schema_same_as()</code>)</h3>
      <?php $gws_qa_same_as = function_exists('gws_core_schema_same_as') ? gws_core_schema_same_as() : array(); ?>
      <?php if ($gws_qa_same_as) : ?>
        <ul><?php foreach ($gws_qa_same_as as $gws_qa_same_as_url) : ?><li><?php echo esc_html($gws_qa_same_as_url); ?></li><?php endforeach; ?></ul>
      <?php else : ?>
        <p>Vide — aucune URL sociale renseignée pour l’instant.</p>
      <?php endif; ?>
      <p><small>Vérification réelle : afficher le code source de cette page (et de l’accueil du
        site) et contrôler le bloc <code>application/ld+json</code> — les clés
        <code>telephone</code>, <code>email</code>, <code>logo</code> et <code>sameAs</code> ne
        doivent jamais y apparaître vides, y compris quand les champs ci-dessus sont vides
        (elles doivent alors être totalement absentes du JSON, pas présentes avec une valeur
        vide). Tester aussi la même URL X dans le champ « X » et dans « Autres réseaux sociaux »
        (<code>social_links</code>) : elle ne doit apparaître qu’une seule fois dans la liste
        ci-dessus.</small></p>

      <h3>Pictogrammes des réseaux sociaux</h3>
      <p>
        En-tête : <?php echo (function_exists('gws_core_show_header_social') && gws_core_show_header_social()) ? 'activé' : 'désactivé'; ?>
        (désactivé par défaut) — pied de page :
        <?php echo (function_exists('gws_core_show_footer_social') && gws_core_show_footer_social()) ? 'activé' : 'désactivé'; ?>
        (activé par défaut). Si aucun réseau structuré n’est renseigné, aucune liste ne doit
        apparaître ni dans l’en-tête ni dans le pied de page — pas de conteneur vide.
      </p>
      <p>Aperçu du composant (<code>template-parts/content/social-links.php</code>), ici sur
        fond de couleur pour vérifier qu’il hérite bien <code>currentColor</code> plutôt qu’une
        couleur de marque figée :</p>
      <div style="color:#fff;background:var(--color-primary);padding:var(--space-3);display:inline-block">
        <?php get_template_part('template-parts/content/social-links'); ?>
      </div>
      <p><small>Vérifier au clavier (Tab) que chaque icône est atteignable et a un contour de
        focus visible, et via les outils de développement que chaque lien a un nom accessible
        (LinkedIn, Facebook...) et non un texte vide — et qu’il s’ouvre dans un nouvel onglet
        (<code>target="_blank" rel="noopener noreferrer"</code>).</small></p>

      <h3>Crédit Tagada Vroom</h3>
      <p>
        Option activée (<code>gws_core_credit_enabled()</code>) :
        <?php echo (function_exists('gws_core_credit_enabled') && gws_core_credit_enabled()) ? 'Oui' : 'Non'; ?><br>
        URL renseignée : <?php echo gws_get_setting('credit_url') ? esc_html(gws_get_setting('credit_url')) : '(vide)'; ?><br>
        Le crédit ne doit apparaître dans le pied de page de cette page (tout en bas) que si les
        deux conditions ci-dessus sont vraies simultanément, et son lien doit s’ouvrir dans un
        nouvel onglet (vérifier <code>target="_blank" rel="noopener noreferrer"</code> dans le
        code source).
      </p>
    </section>

    <?php while (have_posts()) : the_post(); ?>
      <?php the_content(); ?>
    <?php endwhile; ?>
  </div>
  <?php get_template_part('template-parts/site-footer'); ?>
</main>
<?php get_footer(); ?>

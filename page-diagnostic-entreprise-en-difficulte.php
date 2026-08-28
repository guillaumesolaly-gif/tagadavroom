<?php get_header(); ?>
<main id="contenu" tabindex="-1" class="diagnostic-page">
<?php get_template_part('template-parts/site-header'); ?>
<section class="diagnostic-hero" id="autodiagnostic" data-diagnostic-hero>
  <div class="diagnostic-stage">
    <div class="diagnostic-hero-main" data-diagnostic-panel-a>
      <div class="diagnostic-hero-copy">
        <div class="breadcrumb" aria-label="Fil d’Ariane"><a href="<?php echo esc_url(home_url('/')); ?>">Accueil</a><span>→</span><span>Autodiagnostic</span></div>
        <p class="kicker">Un premier niveau de lecture</p>
        <h1>Évaluez la situation<br><em>de votre entreprise.</em></h1>
        <p class="diagnostic-lead">Douze questions permettent d’identifier certains signaux économiques, financiers et juridiques qui peuvent justifier une analyse plus approfondie.</p>
        <ul class="diagnostic-stats"><li>12 questions</li><li>Environ 3 minutes</li><li>Résultat immédiat</li></ul>
        <div class="diagnostic-hero-cta">
          <button class="btn" type="button" data-diagnostic-start>Commencer le diagnostic <b>→</b></button>
          <p class="diagnostic-confidential">Les réponses ne sont ni enregistrées ni transmises tant que vous ne demandez pas au cabinet de vous recontacter.</p>
        </div>
      </div>
      <aside class="diagnostic-preview" aria-hidden="true">
        <p class="diagnostic-preview-kicker">Votre diagnostic</p>
        <ul class="diagnostic-preview-themes"><li>Trésorerie</li><li>Dettes &amp; échéances</li><li>Situation juridique</li></ul>
        <div class="diagnostic-preview-dots"><?php echo str_repeat('<i></i>', 12); ?></div>
        <p class="diagnostic-preview-note">12 questions · Résultat immédiat</p>
      </aside>
    </div>

    <form class="diagnostic-form" data-diagnostic-form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" novalidate hidden>
      <input type="hidden" name="action" value="spa_diagnostic_lead">
      <input type="hidden" name="diagnostic_started" value="<?php echo esc_attr(time()); ?>">
      <?php wp_nonce_field('spa_submit_diagnostic', 'spa_diagnostic_nonce'); ?>
      <div class="diagnostic-honeypot" aria-hidden="true"><label>Votre site internet<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
      <div hidden data-answers-store></div>

      <div class="diagnostic-question-panel" data-diagnostic-panel-b>
        <p class="diagnostic-quit"><a href="#" data-diagnostic-quit>← Quitter le diagnostic</a></p>
        <p class="kicker">Diagnostic entreprise en difficulté</p>
        <div class="diagnostic-progress"><span data-progress-label>Question 1 sur 12</span><div><i data-progress-bar></i></div></div>
        <div data-question-stage aria-live="polite"></div>
        <div class="diagnostic-navigation"><button type="button" class="diagnostic-back" data-diagnostic-back>← Précédent</button><button type="button" class="btn" data-diagnostic-next disabled>Suivant <b>→</b></button></div>
        <p class="diagnostic-confidential">Les réponses ne sont ni enregistrées ni transmises tant que vous ne demandez pas au cabinet de vous recontacter.</p>
      </div>

      <section class="diagnostic-result" id="diagnostic-result" data-diagnostic-result hidden tabindex="-1">
      <p class="kicker">Votre résultat</p>
      <div class="result-level" data-result-level></div>
      <h2 data-result-title></h2>
      <p class="result-copy" data-result-copy></p>
      <p class="result-disclaimer">Ce résultat est indicatif. Seule l’analyse des documents et de la situation particulière de l’entreprise permet de déterminer les démarches appropriées.</p>
      <div class="result-actions" data-result-actions>
        <button type="button" class="btn" data-show-lead>Être recontacté par le cabinet <b>→</b></button>
        <button type="button" class="secondary-action" data-show-anonymous>Continuer sans laisser mes coordonnées</button>
      </div>
      <p class="result-submitted" data-result-submitted hidden>✓ Demande transmise</p>
    </section>

    <section class="diagnostic-lead-form" data-lead-panel hidden>
      <p class="kicker">Demande de rappel</p><h2>Le cabinet peut vous recontacter.</h2>
      <p>Juliette Saint-Père recevra vos coordonnées, votre niveau de vigilance et les réponses données afin de préparer ce premier échange.</p>
      <div class="lead-fields">
        <label><span>Nom et prénom *</span><input type="text" name="lead_name" autocomplete="name" required></label>
        <label><span>Entreprise *</span><input type="text" name="lead_company" autocomplete="organization" required></label>
        <label><span>Fonction</span><input type="text" name="lead_role" autocomplete="organization-title"></label>
        <label><span>Téléphone *</span><input type="tel" name="lead_phone" autocomplete="tel" required></label>
        <label><span>Adresse électronique *</span><input type="email" name="lead_email" autocomplete="email" required></label>
        <label><span>Moment préférable pour être rappelé</span><select name="lead_availability"><option value="">Sans préférence</option><option>Le matin</option><option>Entre 12 h et 14 h</option><option>L’après-midi</option><option>En fin de journée</option></select></label>
      </div>
      <label class="lead-request"><input type="checkbox" name="lead_request" value="1" required><span>Je demande à être recontacté par le cabinet Saint-Père Avocat au sujet de la situation de mon entreprise. *</span></label>
      <p class="privacy-notice">Ces informations sont utilisées uniquement pour traiter votre demande et préparer le premier échange. Elles sont destinées au cabinet Saint-Père Avocat et conservées pendant trois mois si aucun dossier n’est ouvert. Vous pouvez exercer vos droits en écrivant à <a href="mailto:<?php echo esc_attr(spa_get_cabinet_setting('public_email')); ?>"><?php echo esc_attr(spa_get_cabinet_setting('public_email')); ?></a>. <a href="<?php echo esc_url(home_url('/politique-de-confidentialite/')); ?>">En savoir plus</a>.</p>
      <button type="submit" class="btn btn-dark" data-diagnostic-submit>Transmettre ma demande <b>→</b></button>
      <button type="button" class="secondary-action" data-show-anonymous>Continuer sans transmettre mes réponses</button>
    </section>

    <section class="diagnostic-anonymous" data-anonymous-panel hidden>
      <p class="kicker">Sans transmission de vos réponses</p><h2>Vous restez libre de contacter directement le cabinet.</h2>
      <div class="anonymous-contact">
        <div><span>Téléphone</span><strong class="desktop-only"><?php echo esc_html(spa_get_cabinet_setting('phone_display')); ?></strong><a class="mobile-only" href="tel:<?php echo esc_attr(spa_cabinet_phone_href()); ?>"><?php echo esc_html(spa_get_cabinet_setting('phone_display')); ?></a></div>
        <a href="mailto:<?php echo esc_attr(spa_get_cabinet_setting('public_email')); ?>?subject=Autodiagnostic%20entreprise"><span>Écrire au cabinet</span><strong>Par e-mail →</strong></a>
      </div>
      <div class="anonymous-online"><p class="paid-kicker">Pour une demande ponctuelle</p><p>Vous pouvez également demander un rendez-vous, une consultation téléphonique ou une visioconférence depuis la plateforme officielle Avocat.fr.</p><a class="avocat-consultingwidget" href="<?php echo esc_url(spa_get_cabinet_setting('avocat_url')); ?>" data-widget-id="<?php echo esc_attr(spa_get_cabinet_setting('avocat_widget_id')); ?>">Accéder aux services en ligne</a><noscript><a class="btn btn-dark" href="<?php echo esc_url(spa_get_cabinet_setting('avocat_url')); ?>">Accéder au profil Avocat.fr →</a></noscript><small>Le créneau proposé n’est définitif qu’après confirmation par le cabinet.</small></div>
    </section>
    </form>

    <?php $status = isset($_GET['diagnostic']) ? sanitize_key(wp_unslash($_GET['diagnostic'])) : ''; if ($status) : ?>
      <div class="diagnostic-server-message <?php echo $status === 'sent' ? 'is-success' : 'is-error'; ?>" data-status="<?php echo esc_attr($status); ?>" role="status">
        <?php if ($status === 'sent') : ?><strong>Votre demande a été transmise.</strong><p>Le cabinet dispose des informations nécessaires pour vous recontacter.</p>
        <?php elseif ($status === 'limit') : ?><strong>Trop de tentatives ont été effectuées.</strong><p>Contactez directement le cabinet au <?php echo esc_html(spa_get_cabinet_setting('phone_display')); ?>.</p>
        <?php else : ?><strong>La demande n’a pas pu être transmise.</strong><p>Vérifiez les champs ou écrivez directement à <a href="mailto:<?php echo esc_attr(spa_get_cabinet_setting('public_email')); ?>"><?php echo esc_attr(spa_get_cabinet_setting('public_email')); ?></a>.</p><?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<section class="diagnostic-related"><div><p class="kicker">Pour aller plus loin</p><h2>Comprendre avant d’agir.</h2></div><div><a href="<?php echo esc_url(home_url('/entreprise-en-difficulte-que-faire-saint-etienne/')); ?>">Entreprise en difficulté : que faire ?<b>→</b></a><a href="<?php echo esc_url(home_url('/prevention-difficultes-entreprise-saint-etienne/')); ?>">Prévention des difficultés<b>→</b></a><a href="<?php echo esc_url(home_url('/cessation-de-paiement-que-faire-dirigeant-saint-etienne/')); ?>">Comprendre la cessation des paiements<b>→</b></a></div></section>
<?php get_template_part('template-parts/site-footer'); ?>
</main>
<div class="diagnostic-modal" data-diagnostic-modal hidden role="dialog" aria-modal="true" aria-labelledby="diagnostic-modal-title">
  <div class="diagnostic-modal-panel" data-diagnostic-modal-panel tabindex="-1">
    <h2 id="diagnostic-modal-title">Votre demande a bien été transmise.</h2>
    <p>Au regard de votre diagnostic, vous avez souhaité être recontacté par le cabinet.</p>
    <p>Maître Juliette Saint-Père dispose désormais de vos coordonnées.</p>
    <p class="diagnostic-modal-urgent">En cas de situation urgente, vous pouvez également contacter directement le cabinet au <a class="diagnostic-modal-tel-link" href="tel:<?php echo esc_attr(spa_cabinet_phone_href()); ?>"><?php echo esc_html(spa_get_cabinet_setting('phone_display')); ?></a><span class="diagnostic-modal-tel-text"><?php echo esc_html(spa_get_cabinet_setting('phone_display')); ?></span>.</p>
    <button type="button" class="btn" data-diagnostic-modal-close>Fermer</button>
  </div>
</div>
<?php get_footer(); ?>

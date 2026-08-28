<?php
/**
 * Bloc d'appel à l'action générique. Paramètres attendus dans $args, passés par
 * get_template_part('template-parts/content/cta', null, $args) :
 *   'kicker', 'title', 'text', 'button_label', 'button_url'.
 */
$args = $args ?? array();
$kicker = $args['kicker'] ?? '';
$title = $args['title'] ?? '';
$text = $args['text'] ?? '';
$button_label = $args['button_label'] ?? '';
$button_url = $args['button_url'] ?? '';
?>
<section class="cta">
  <?php if ($kicker) : ?><p class="kicker"><?php echo esc_html($kicker); ?></p><?php endif; ?>
  <?php if ($title) : ?><h2><?php echo esc_html($title); ?></h2><?php endif; ?>
  <?php if ($text) : ?><p><?php echo esc_html($text); ?></p><?php endif; ?>
  <?php if ($button_label && $button_url) : ?>
    <a class="btn btn-primary" href="<?php echo esc_url($button_url); ?>"><?php echo esc_html($button_label); ?> <?php echo gws_icon('arrow_forward'); ?></a>
  <?php endif; ?>
</section>

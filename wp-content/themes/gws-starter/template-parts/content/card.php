<?php
/**
 * Carte générique pour une liste/archive : titre, extrait, lien. Utilisable pour n'importe quel
 * post type sans modification.
 */
?>
<article class="card">
  <?php if (has_post_thumbnail()) : ?>
    <a href="<?php the_permalink(); ?>" class="card-media"><?php the_post_thumbnail('medium'); ?></a>
  <?php endif; ?>
  <div class="card-body">
    <h3 class="card-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
    <p class="card-excerpt"><?php echo esc_html(wp_trim_words(get_the_excerpt(), 24)); ?></p>
    <a class="card-link" href="<?php the_permalink(); ?>">Lire <?php echo gws_icon('arrow_forward'); ?></a>
  </div>
</article>

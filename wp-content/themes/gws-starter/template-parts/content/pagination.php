<?php
$links = paginate_links(array(
  'mid_size' => 2,
  'prev_text' => 'Précédent',
  'next_text' => 'Suivant',
  'type' => 'array',
));
if (!$links) return;
?>
<nav class="pagination" aria-label="Pagination">
  <ul>
    <?php foreach ($links as $link) : ?>
      <li><?php echo wp_kses_post($link); ?></li>
    <?php endforeach; ?>
  </ul>
</nav>

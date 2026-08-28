<?php
// Exclut le portrait LCP de la home du lazy-load Smush, via le filtre officiel documenté par
// WPMU DEV (smush_skip_image_from_lazy_load). Le thème déclare déjà cette image en eager +
// fetchpriority="high" (voir front-page.php) ; Smush réécrivait néanmoins son <img> en
// data-src/class="lazyloaded" indépendamment de cet attribut, ce qui rendait la ressource LCP
// dépendante du JavaScript de Smush pour être restaurée. Inerte si Smush n'est pas actif.

function spa_skip_lcp_portrait_from_smush_lazy_load($skip, $src, $image) {
  if ($skip) return $skip;
  return $src === get_template_directory_uri() . '/assets/portrait-saint-pere-tenue-pro-v1.webp';
}
add_filter('smush_skip_image_from_lazy_load', 'spa_skip_lcp_portrait_from_smush_lazy_load', 99, 3);

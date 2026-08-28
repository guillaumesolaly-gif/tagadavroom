<?php
/**
 * Pictogrammes du thème : sprite SVG local injecté une seule fois après <body>, sans dépendance
 * à une police externe. Jeu d'icônes volontairement réduit au socle générique ; un projet
 * ajoute les siennes via le filtre 'gws_icon_glyphs'.
 */

if (!defined('ABSPATH')) exit;

function gws_icon_glyphs() {
  $glyphs = array(
    'menu' => 'M120 720V640H840V720ZM120 520V440H840V520ZM120 320V240H840V320Z',
    'close' => 'M256 760 200 704 424 480 200 256 256 200 480 424 704 200 760 256 536 480 760 704 704 760 480 536Z',
    'arrow_upward' => 'M440 800V313L216 537L160 480L480 160L800 480L744 537L520 313V800Z',
    'arrow_forward' => 'M480 800 424 744 664 504H160V424H664L424 184L480 128L800 448Z',
    'call' => 'M798 840Q673 840 551.0 785.5Q429 731 329 631Q229 531 174.5 409.0Q120 287 120 162Q120 144 132.0 132.0Q144 120 162 120H324Q338 120 349.0 129.5Q360 139 362 152L388 292Q390 308 387.0 319.0Q384 330 376 338L279 436Q299 473 326.5 507.5Q354 542 387 574Q418 605 452.0 631.5Q486 658 524 680L618 586Q627 577 641.5 572.5Q656 568 670 570L808 598Q822 602 831.0 612.5Q840 623 840 636V798Q840 816 828.0 828.0Q816 840 798 840Z',
    'mail' => 'M160 800Q127 800 103.5 776.5Q80 753 80 720V240Q80 207 103.5 183.5Q127 160 160 160H800Q833 160 856.5 183.5Q880 207 880 240V720Q880 753 856.5 776.5Q833 800 800 800ZM480 520 160 320V720H800V320ZM480 440 800 240H160Z',
    'chat_bubble' => 'M80 880V160Q80 127 103.5 103.5Q127 80 160 80H800Q833 80 856.5 103.5Q880 127 880 160V640Q880 673 856.5 696.5Q833 720 800 720H240ZM206 640H800V160H160V685Z',
    'check' => 'M382 720 154 492 210 436 382 608 810 180 866 236Z',
    'info' => 'M440 680H520V440H440ZM520 320Q520 303 508.5 291.5Q497 280 480 280Q463 280 451.5 291.5Q440 303 440 320Q440 337 451.5 348.5Q463 360 480.0 360.0Q497 360 508.5 348.5Q520 337 520 320ZM480 880Q397 880 324.0 848.5Q251 817 197.0 763.0Q143 709 111.5 636.0Q80 563 80 480Q80 397 111.5 324.0Q143 251 197.0 197.0Q251 143 324.0 111.5Q397 80 480 80Q563 80 636.0 111.5Q709 143 763.0 197.0Q817 251 848.5 324.0Q880 397 880 480Q880 563 848.5 636.0Q817 709 763.0 763.0Q709 817 636.0 848.5Q563 880 480 880Z',
  );
  return apply_filters('gws_icon_glyphs', $glyphs);
}

function gws_render_icon_sprite() {
  echo '<svg aria-hidden="true" style="position:absolute;width:0;height:0;overflow:hidden" xmlns="http://www.w3.org/2000/svg">';
  foreach (gws_icon_glyphs() as $name => $path) {
    echo '<symbol id="icon-' . esc_attr($name) . '" viewBox="0 0 960 960"><path d="' . esc_attr($path) . '"/></symbol>';
  }
  echo '</svg>';
}
add_action('wp_body_open', 'gws_render_icon_sprite', 1);

function gws_icon($name, $extra_class = '') {
  $class = trim('icon ' . $extra_class);
  return '<svg class="' . esc_attr($class) . '" aria-hidden="true" focusable="false"><use href="#icon-' . esc_attr($name) . '"></use></svg>';
}

/**
 * Pictogrammes des réseaux sociaux structurés — sprite séparé de gws_icon_glyphs() ci-dessus :
 * ses tracés utilisent une viewBox 0 0 24 24 (convention habituelle des logos de marque),
 * différente de la viewBox 0 0 960 960 du sprite générique. Tracés dessinés localement pour ce
 * thème (silhouettes simplifiées, un seul chemin par icône) : aucune police, bibliothèque ou
 * requête externe. Un projet peut ajouter un réseau via le filtre 'gws_social_icon_glyphs'.
 */
function gws_social_icon_glyphs() {
  $glyphs = array(
    'linkedin' => 'M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667h-3.554V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z',
    'facebook' => 'M9.101 23.691v-7.98H6.627v-3.667h2.474v-1.58c0-4.085 1.848-5.978 5.858-5.978.401 0 .955.042 1.468.103a8.68 8.68 0 0 1 1.141.195v3.325a8.623 8.623 0 0 0-.653-.036 26.805 26.805 0 0 0-.733-.009c-.707 0-1.259.096-1.675.309a1.686 1.686 0 0 0-.679.622c-.258.42-.374.995-.374 1.752v1.297h3.919l-.386 1.913-.287 1.754h-3.246v7.985C19.396 23.203 24 18.107 24 11.99 24 5.367 18.627 0 12 0S0 5.367 0 11.99c0 6.107 4.505 11.19 10.379 11.995z',
    'instagram' => 'M12 0C8.74 0 8.333.014 7.053.072 5.775.132 4.905.333 4.14.63a5.883 5.883 0 0 0-2.126 1.384A5.868 5.868 0 0 0 .63 4.14C.333 4.905.131 5.775.072 7.053.014 8.333 0 8.74 0 12s.014 3.667.072 4.947c.06 1.277.261 2.148.558 2.913.306.789.717 1.459 1.384 2.126.667.666 1.336 1.079 2.126 1.384.766.296 1.636.499 2.913.558C8.333 23.986 8.74 24 12 24s3.667-.014 4.947-.072c1.277-.06 2.148-.262 2.913-.558a5.89 5.89 0 0 0 2.126-1.384 5.86 5.86 0 0 0 1.384-2.126c.296-.765.499-1.636.558-2.913.058-1.28.072-1.687.072-4.947s-.014-3.667-.072-4.947c-.06-1.277-.262-2.149-.558-2.913a5.89 5.89 0 0 0-1.384-2.126A5.847 5.847 0 0 0 19.86.63c-.765-.297-1.636-.499-2.913-.558C15.667.014 15.26 0 12 0zm0 2.16c3.203 0 3.585.016 4.85.071 1.17.055 1.805.249 2.227.415.562.217.96.477 1.382.896.419.42.679.819.896 1.381.164.422.36 1.057.413 2.227.057 1.266.07 1.646.07 4.85s-.015 3.585-.074 4.85c-.061 1.17-.256 1.805-.421 2.227a3.81 3.81 0 0 1-.899 1.382 3.744 3.744 0 0 1-1.381.896c-.42.164-1.065.36-2.235.413-1.274.057-1.649.07-4.859.07-3.211 0-3.586-.015-4.859-.074-1.171-.061-1.816-.256-2.236-.421a3.716 3.716 0 0 1-1.379-.899 3.644 3.644 0 0 1-.9-1.381c-.165-.42-.359-1.065-.42-2.235-.045-1.26-.061-1.649-.061-4.844 0-3.196.016-3.586.061-4.861.061-1.17.255-1.814.42-2.234.21-.57.479-.96.9-1.381.419-.42.81-.689 1.379-.898.42-.166 1.051-.361 2.221-.421 1.275-.045 1.65-.06 4.859-.06zm0 3.678a6.162 6.162 0 1 0 .002 12.324A6.162 6.162 0 0 0 12 5.838zm0 10.162a4 4 0 1 1 0-8 4 4 0 0 1 0 8zm7.846-10.405a1.441 1.441 0 1 1-2.883-.001 1.441 1.441 0 0 1 2.883.001z',
    'youtube' => 'M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z',
    'tiktok' => 'M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z',
    'x' => 'M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z',
  );
  return apply_filters('gws_social_icon_glyphs', $glyphs);
}

function gws_render_social_icon_sprite() {
  echo '<svg aria-hidden="true" style="position:absolute;width:0;height:0;overflow:hidden" xmlns="http://www.w3.org/2000/svg">';
  foreach (gws_social_icon_glyphs() as $name => $path) {
    echo '<symbol id="icon-social-' . esc_attr($name) . '" viewBox="0 0 24 24"><path d="' . esc_attr($path) . '"/></symbol>';
  }
  echo '</svg>';
}
add_action('wp_body_open', 'gws_render_social_icon_sprite', 1);

function gws_social_icon($network, $extra_class = '') {
  $class = trim('icon icon-social ' . $extra_class);
  return '<svg class="' . esc_attr($class) . '" aria-hidden="true" focusable="false"><use href="#icon-social-' . esc_attr($network) . '"></use></svg>';
}

/**
 * Libellés affichés (nom accessible du lien) pour chaque réseau structuré. Un projet qui étend
 * gws_social_icon_glyphs() avec un nouveau réseau doit aussi étendre ce filtre pour lui donner
 * un nom accessible correct — à défaut, le nom de la clé est utilisé tel quel.
 */
function gws_social_network_labels() {
  $labels = array(
    'linkedin' => 'LinkedIn',
    'facebook' => 'Facebook',
    'instagram' => 'Instagram',
    'youtube' => 'YouTube',
    'tiktok' => 'TikTok',
    'x' => 'X',
  );
  return apply_filters('gws_social_network_labels', $labels);
}

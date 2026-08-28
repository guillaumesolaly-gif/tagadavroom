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

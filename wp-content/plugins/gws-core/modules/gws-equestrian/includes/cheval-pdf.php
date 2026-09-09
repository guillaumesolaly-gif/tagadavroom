<?php
/**
 * Fiche cheval PDF A4 (Lot "PDF Cheval & Catalogue", Lot 3A bis — refonte visuelle + templates).
 *
 * ARCHITECTURE INCHANGÉE DEPUIS LE LOT 3A (§ demande "ne réécris pas cette architecture") :
 * TCPDF, `includes/pdf-engine.php` (gws-core, générique), `gwseq_build_horse_pdf_data()`,
 * `gwseq_render_horse_pdf_page($pdf, $horse_id, $context)` comme renderer PARTAGÉ unique,
 * `gwseq_generate_horse_pdf()`. Ce qui change dans ce lot : le CONTENU de
 * `gwseq_render_horse_pdf_page()` se réorganise désormais en 3 TEMPLATES métier
 * (`gwseq_render_horse_pdf_template_etalon()` / `_pouliniere()` / `_sport_vente()`) qui partagent
 * tous les mêmes composants de dessin ci-dessous (header, footer+QR, galerie, performances,
 * qualités, pedigree, présentation) — jamais trois moteurs différents, seulement trois
 * COMPOSITIONS différentes des mêmes briques (§1 de la demande).
 *
 * DONNÉES CONSOMMÉES — AUCUNE DUPLICATION : voir includes/cheval-pdf-fields.php pour les nouveaux
 * champs BO (type de fiche, statut ostéo-articulaire, stud-books d'approbation, WFFS) et l'audit
 * qui les distingue des champs éditoriaux existants. Le reste transite toujours par les fonctions
 * métier déjà existantes et déjà testées ailleurs (identité, commercial, éditorial, indices,
 * pedigree, Production, médias) et par gws_core_structure_identity() pour le branding.
 */

if (!defined('ABSPATH')) exit;

const GWSEQ_PDF_HEADER_BANNER_H = 20;
const GWSEQ_PDF_FOOTER_BANNER_H = 20;
const GWSEQ_PDF_CONTENT_MARGIN = 14;

/* -------------------------------------------------------------------------------------------
 * Assemblage des données — séparé du dessin (testable indépendamment de TCPDF, voir tests).
 * ----------------------------------------------------------------------------------------- */

/**
 * Rassemble TOUTES les données nécessaires au rendu d'une fiche cheval. $horse_id invalide ->
 * null, jamais un tableau à moitié rempli.
 */
function gwseq_build_horse_pdf_data($horse_id) {
  $horse_id = (int) $horse_id;
  if (!$horse_id || get_post_type($horse_id) !== GWSEQ_CPT_CHEVAL) return null;

  $identity = gwseq_get_cheval_identity($horse_id);
  $commercial = gwseq_get_cheval_commercial($horse_id);
  $editorial = gwseq_get_cheval_editorial($horse_id);

  $sport_indices = array();
  foreach (gwseq_cheval_sport_indice_keys() as $key) {
    $indice = gwseq_get_cheval_sport_indice($horse_id, $key);
    if (($indice['valeur'] ?? '') !== '') $sport_indices[$key] = $indice;
  }
  $genetic_indices = array();
  foreach (gwseq_cheval_genetic_indice_keys() as $key) {
    $indice = gwseq_get_cheval_genetic_indice($horse_id, $key);
    if (($indice['valeur'] ?? '') !== '') $genetic_indices[$key] = $indice;
  }

  $photo_id = gwseq_get_cheval_photo_principale_id($horse_id);
  $gallery_paths = array();
  foreach (gwseq_get_cheval_galerie($horse_id) as $attachment_id) {
    $path = get_attached_file($attachment_id);
    if ($path) $gallery_paths[] = $path;
  }

  $production = array();
  if (($identity['sexe'] ?? '') === 'female') {
    $production = gwseq_get_horse_direct_production($horse_id);
  }

  $studbook_options = gwseq_cheval_studbook_approbation_options();
  $studbooks_labels = array();
  foreach (gwseq_get_cheval_studbooks_approbation($horse_id) as $code) {
    if (isset($studbook_options[$code])) $studbooks_labels[] = $code;
  }

  return array(
    'id' => $horse_id,
    'name' => get_the_title($horse_id),
    'identity' => $identity,
    'sexe_label' => gwseq_cheval_sexe_options()[$identity['sexe']] ?? '',
    'robe_label' => $identity['robe'] === 'autre' ? $identity['robe_autre'] : (gwseq_cheval_robe_options()[$identity['robe']] ?? ''),
    'race_label' => gwseq_cheval_race_label($identity['race'], $identity['race_autre']),
    'commercial' => $commercial,
    'price_summary' => gwseq_cheval_price_summary($commercial),
    'editorial' => $editorial,
    'qualites' => gwseq_get_cheval_qualites($horse_id),
    'faits_marquants' => gwseq_get_cheval_faits_marquants($horse_id),
    'sport_indices' => $sport_indices,
    'genetic_indices' => $genetic_indices,
    'photo_path' => $photo_id ? get_attached_file($photo_id) : '',
    'gallery_paths' => $gallery_paths,
    'pedigree' => gwseq_resolve_horse_pedigree($horse_id, 2),
    'production' => $production,
    'structure' => gws_core_structure_identity(),
    'statut_osteo' => gwseq_get_cheval_statut_osteo_articulaire($horse_id),
    'studbooks_labels' => $studbooks_labels,
    'wffs' => gwseq_get_cheval_wffs($horse_id),
    'pdf_template' => gwseq_resolve_cheval_pdf_template(gwseq_get_cheval_pdf_template($horse_id), $identity['sexe']),
    'public_url' => function_exists('gwseq_horse_share_fiche_url') ? gwseq_horse_share_fiche_url($horse_id) : '',
  );
}

/* -------------------------------------------------------------------------------------------
 * Production — fonctions PURES, testables sans TCPDF (§20-21 de la demande).
 * ----------------------------------------------------------------------------------------- */

function gwseq_horse_pdf_best_sport_value($indices) {
  $best = null;
  foreach (array('iso', 'icc', 'idr') as $key) {
    $valeur = $indices[$key]['valeur'] ?? '';
    if ($valeur === '') continue;
    $numeric = (float) $valeur;
    if ($best === null || $numeric > $best) $best = $numeric;
  }
  return $best;
}

function gwseq_horse_pdf_select_production_entries($entries, $max) {
  if (!is_array($entries)) return array();
  $indexed = array();
  foreach ($entries as $i => $entry) {
    $indexed[] = array('entry' => $entry, 'score' => gwseq_horse_pdf_best_sport_value($entry), 'original_order' => $i);
  }
  usort($indexed, function ($a, $b) {
    if ($a['score'] === $b['score']) return $a['original_order'] <=> $b['original_order'];
    if ($a['score'] === null) return 1;
    if ($b['score'] === null) return -1;
    return $b['score'] <=> $a['score'];
  });
  $kept = array_slice($indexed, 0, max(0, (int) $max));
  usort($kept, function ($a, $b) { return $a['original_order'] <=> $b['original_order']; });
  return array_map(function ($item) { return $item['entry']; }, $kept);
}

/**
 * Formate une ligne de Production (direction de design du Lot 3A bis, §20) : "Nom · Père · Année —
 * ISO Valeur · ICC Valeur" — TOUS les indices réellement renseignés sont affichés (correctif par
 * rapport au Lot 3A, qui n'affichait que le meilleur) ; chaque segment absent est omis proprement.
 * Jamais le BLUP (bso/bcc/bdr) d'un produit (§21) — cette fonction ne les lit même pas.
 */
function gwseq_horse_pdf_production_line($entry) {
  $left = array();
  $left[] = (string) ($entry['nom'] ?? '');
  if (($entry['pere'] ?? '') !== '') $left[] = (string) $entry['pere'];
  if (($entry['annee'] ?? '') !== '') $left[] = (string) $entry['annee'];

  $indices = array();
  foreach (array('iso', 'icc', 'idr') as $key) {
    $valeur = $entry[$key]['valeur'] ?? '';
    if ($valeur === '') continue;
    $indices[] = strtoupper($key) . ' ' . $valeur;
  }

  $line = implode(' · ', $left);
  if ($indices) $line .= ' — ' . implode(' · ', $indices);
  return $line;
}

/* -------------------------------------------------------------------------------------------
 * Ajustement de texte — fonctions PURES/quasi-pures (mesure TCPDF, jamais de police réduite à
 * l'extrême — §15 de la demande : "jamais de réduction extrême de police pour faire rentrer le
 * contenu").
 * ----------------------------------------------------------------------------------------- */

/**
 * Réduit la taille de police par pas de 0.5 (jamais sous $min_size) jusqu'à ce que $text tienne sur
 * UNE SEULE ligne dans $max_w. Laisse $pdf positionné sur la police finale.
 */
function gwseq_horse_pdf_fit_font_size($pdf, $text, $max_w, $bold, $start_size, $min_size) {
  $size = $start_size;
  $style = $bold ? 'B' : '';
  while ($size > $min_size) {
    $pdf->SetFont('helvetica', $style, $size);
    if ($pdf->GetStringWidth($text) <= $max_w) break;
    $size -= 0.5;
  }
  $pdf->SetFont('helvetica', $style, $size);
  return $size;
}

/**
 * Tronque $text (avec « … ») jusqu'à ce qu'il tienne dans $max_h à largeur $w, à police déjà
 * définie sur $pdf — jamais un chevauchement de bloc suivant (§15 : "aucun chevauchement... n'est
 * acceptable"), jamais une réduction de police pour y parvenir (la police reste celle déjà
 * choisie par l'appelant). Mots entiers uniquement (jamais coupé au milieu d'un mot). Retourne le
 * texte (éventuellement tronqué) à passer tel quel à MultiCell().
 */
function gwseq_horse_pdf_fit_text_to_height($pdf, $text, $w, $max_h) {
  $text = trim((string) $text);
  if ($text === '' || $max_h <= 0) return '';
  if ($pdf->getStringHeight($w, $text) <= $max_h) return $text;

  $words = preg_split('/\s+/', $text);
  $low = 0;
  $high = count($words);
  // Recherche dichotomique du plus grand préfixe de mots qui tient — borné, jamais de boucle
  // infinie (au plus ~log2(nombre de mots) itérations).
  while ($low < $high) {
    $mid = (int) ceil(($low + $high) / 2);
    $candidate = implode(' ', array_slice($words, 0, $mid)) . '…';
    if ($pdf->getStringHeight($w, $candidate) <= $max_h) {
      $low = $mid;
    } else {
      $high = $mid - 1;
    }
  }
  if ($low <= 0) return ''; // même le premier mot + "…" ne tient pas : rien à afficher plutôt qu'un débordement
  return implode(' ', array_slice($words, 0, $low)) . '…';
}

/* -------------------------------------------------------------------------------------------
 * Composants partagés — header, footer+QR, galerie, performances, qualités, à retenir, pedigree,
 * bloc éditorial. Réutilisés IDENTIQUEMENT par les 3 templates (§1 de la demande).
 * ----------------------------------------------------------------------------------------- */

/**
 * Bandeau d'en-tête plein cadre (§2 de la demande) : logo si disponible, sinon le nom de la
 * structure en repli (§2, "si aucun logo : afficher le nom de la structure") ; couleur PRINCIPALE
 * de "Ma structure" en fond (repli sur la couleur par défaut de Core déjà géré par
 * gws_core_structure_identity(), jamais recalculé ici) ; contraste calculé via
 * gws_core_contrast_color() (déjà inclus dans $structure). Badge de type de fiche à droite. AUCUN
 * slogan (§2).
 */
function gwseq_horse_pdf_draw_header($pdf, $structure, $badge_label) {
  $primary_rgb = gws_core_pdf_hex_to_rgb($structure['primary_color']);
  $contrast_rgb = gws_core_pdf_hex_to_rgb($structure['primary_color_contrast']);

  $pdf->SetFillColor($primary_rgb[0], $primary_rgb[1], $primary_rgb[2]);
  $pdf->Rect(0, 0, $pdf->getPageWidth(), GWSEQ_PDF_HEADER_BANNER_H, 'F');

  $inner_x = GWSEQ_PDF_CONTENT_MARGIN;
  $inner_y = 3.5;
  $inner_h = GWSEQ_PDF_HEADER_BANNER_H - (2 * $inner_y);
  $text_x = $inner_x;

  if ($structure['logo_url'] !== '' && $structure['logo_id']) {
    $logo_path = get_attached_file($structure['logo_id']);
    $logo_box = $logo_path ? gws_core_pdf_fit_image_box($logo_path, 26, $inner_h) : null;
    if ($logo_box) {
      $pdf->Image($logo_path, $inner_x, $inner_y + (($inner_h - $logo_box['h']) / 2), $logo_box['w'], $logo_box['h']);
      $text_x = $inner_x + $logo_box['w'] + 4;
    }
  }

  $pdf->SetTextColor($contrast_rgb[0], $contrast_rgb[1], $contrast_rgb[2]);
  $pdf->SetFont('helvetica', 'B', 13);
  $pdf->SetXY($text_x, $inner_y + ($inner_h / 2) - 3.2);
  $pdf->Cell($pdf->getPageWidth() - $text_x - GWSEQ_PDF_CONTENT_MARGIN - 40, 6.4, $structure['name'], 0, 0, 'L');

  if ($badge_label !== '') {
    $pdf->SetFont('helvetica', '', 8.5);
    $pdf->SetXY($pdf->getPageWidth() - GWSEQ_PDF_CONTENT_MARGIN - 40, $inner_y + ($inner_h / 2) - 2.4);
    $pdf->Cell(40, 4.8, mb_strtoupper($badge_label), 0, 0, 'R');
  }

  return GWSEQ_PDF_HEADER_BANNER_H;
}

/**
 * Bandeau de pied de page plein cadre (§11/§28 de la demande) : coordonnées utiles + QR code
 * pointant vers la fiche web publique (jamais une URL d'admin/privée non autorisée — voir
 * gwseq_horse_share_fiche_url(), cheval-share.php, seule source utilisée). AUCUN slogan. Si aucune
 * URL publique n'est disponible, le QR est simplement absent — le bandeau se réorganise sans lui
 * (§28, "le footer se réorganise").
 */
function gwseq_horse_pdf_draw_footer($pdf, $structure, $public_url) {
  $page_h = $pdf->getPageHeight();
  $page_w = $pdf->getPageWidth();
  $banner_y = $page_h - GWSEQ_PDF_FOOTER_BANNER_H;
  $primary_rgb = gws_core_pdf_hex_to_rgb($structure['primary_color']);
  $contrast_rgb = gws_core_pdf_hex_to_rgb($structure['primary_color_contrast']);

  $pdf->SetFillColor($primary_rgb[0], $primary_rgb[1], $primary_rgb[2]);
  $pdf->Rect(0, $banner_y, $page_w, GWSEQ_PDF_FOOTER_BANNER_H, 'F');

  $qr_size = 0;
  if ($public_url !== '') {
    $qr_size = GWSEQ_PDF_FOOTER_BANNER_H - 6;
    $qr_x = $page_w - GWSEQ_PDF_CONTENT_MARGIN - $qr_size;
    $qr_y = $banner_y + 3;
    // Fond blanc sous le QR (un module clair sur fond de couleur reste scannable, mais un vrai
    // fond blanc est plus sûr et plus lisible à l'impression — voir §13 du Lot 3A, même prudence).
    $pdf->SetFillColor(255, 255, 255);
    $pdf->Rect($qr_x - 1, $qr_y - 1, $qr_size + 2, $qr_size + 2, 'F');
    $pdf->write2DBarcode($public_url, 'QRCODE,M', $qr_x, $qr_y, $qr_size, $qr_size, array(
      'border' => false, 'padding' => 0, 'fgcolor' => array(0, 0, 0), 'bgcolor' => false,
    ), 'N');
  }

  $text_w = $page_w - (2 * GWSEQ_PDF_CONTENT_MARGIN) - ($qr_size ? $qr_size + 4 : 0);
  $lines = array_values(array_filter(array($structure['name'])));
  $coords = array_values(array_filter(array($structure['phone_display'], $structure['public_email'], $structure['website_url'])));

  $pdf->SetTextColor($contrast_rgb[0], $contrast_rgb[1], $contrast_rgb[2]);
  $pdf->SetFont('helvetica', 'B', 9);
  $pdf->SetXY(GWSEQ_PDF_CONTENT_MARGIN, $banner_y + 4);
  $pdf->Cell($text_w, 4.5, implode(' · ', $lines), 0, 1, 'L');
  if ($coords) {
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetXY(GWSEQ_PDF_CONTENT_MARGIN, $banner_y + 9.5);
    $pdf->Cell($text_w, 4.5, implode('   ·   ', $coords), 0, 0, 'L');
  }
}

/**
 * Galerie photo adaptative (§8/§9 de la demande) : grande photo principale, puis 1 à 3 miniatures
 * SEULEMENT si elles existent réellement — jamais de placeholder pour une miniature absente (§8,
 * "si une seule photo existe : ne jamais afficher de placeholders secondaires"). Ratio TOUJOURS
 * préservé (gws_core_pdf_fit_image_box()) : la grande photo est ajustée SANS crop agressif
 * (bandes/marges légères acceptées plutôt que couper le cheval, §9) ; les miniatures peuvent être
 * recadrées en `cover` centré (§9, "crop cover centré acceptable" — TCPDF n'offre pas nativement un
 * crop, simulé ici par un cadrage de la zone source via les paramètres w/h/x/y de la miniature déjà
 * dimensionnée proportionnellement puis centrée dans sa case, débordement masqué par la case
 * elle-même — pas un vrai crop pixel mais un rendu visuellement équivalent pour une petite
 * vignette).
 *
 * POINT FOCAL (§9, "prépare le renderer à pouvoir l'utiliser plus tard, ne développe pas cette UX
 * ici") : le paramètre $focal_point, actuellement toujours null (aucun champ BO ne l'alimente dans
 * ce lot), est accepté par cette fonction pour la photo principale — un futur point focal
 * {x: 0-1, y: 0-1} déplacerait simplement le centrage au lieu du centrage géométrique par défaut,
 * sans changer la signature de cette fonction.
 */
function gwseq_horse_pdf_draw_gallery($pdf, $x, $y, $w, $h, $main_path, $secondary_paths, $focal_point = null) {
  $secondary_paths = array_slice(array_filter((array) $secondary_paths, 'is_readable'), 0, 3);
  $has_secondary = !empty($secondary_paths);
  $thumb_h = $has_secondary ? min(24, $h * 0.24) : 0;
  $thumb_gap = 2.5;
  $main_h = $h - ($has_secondary ? $thumb_h + $thumb_gap : 0);

  gwseq_horse_pdf_draw_photo_box($pdf, $x, $y, $w, $main_h, $main_path, false);

  if ($has_secondary) {
    $thumb_y = $y + $main_h + $thumb_gap;
    $count = count($secondary_paths);
    $thumb_w = ($w - (($count - 1) * $thumb_gap)) / $count;
    $tx = $x;
    foreach ($secondary_paths as $path) {
      gwseq_horse_pdf_draw_photo_box($pdf, $tx, $thumb_y, $thumb_w, $thumb_h, $path, true);
      $tx += $thumb_w + $thumb_gap;
    }
  }

  return $y + $h;
}

/**
 * Une case photo unique — image manquante/illisible -> fond clair sobre, jamais une icône criarde
 * (§19 du Lot 3A, inchangé). $cover=true simule un recadrage centré (miniatures, §9) ; $cover=false
 * ajuste sans jamais déformer ni recadrer agressivement (grande photo, §9).
 */
function gwseq_horse_pdf_draw_photo_box($pdf, $x, $y, $w, $h, $path, $cover) {
  if ($path === '' || !is_readable($path)) {
    $pdf->SetFillColor(240, 237, 233);
    $pdf->Rect($x, $y, $w, $h, 'F');
    return;
  }

  if (!$cover) {
    $box = gws_core_pdf_fit_image_box($path, $w, $h);
    if (!$box) { $pdf->SetFillColor(240, 237, 233); $pdf->Rect($x, $y, $w, $h, 'F'); return; }
    $pdf->Image($path, $x + (($w - $box['w']) / 2), $y + (($h - $box['h']) / 2), $box['w'], $box['h']);
    return;
  }

  // Cover centré (miniatures) : agrandit l'image jusqu'à ce qu'elle couvre entièrement la case
  // (ratio préservé), puis affiche à la taille de la case — le débordement de part et d'autre du
  // centre est simplement hors case, TCPDF ne dessinant que dans les dimensions w/h indiquées.
  $size = @getimagesize($path);
  if (!$size || empty($size[0]) || empty($size[1])) { $pdf->SetFillColor(240, 237, 233); $pdf->Rect($x, $y, $w, $h, 'F'); return; }
  $src_ratio = $size[0] / $size[1];
  $box_ratio = $w / $h;
  if ($src_ratio > $box_ratio) {
    $draw_h = $h; $draw_w = $h * $src_ratio;
  } else {
    $draw_w = $w; $draw_h = $w / $src_ratio;
  }
  $pdf->StartTransform();
  $pdf->Rect($x, $y, $w, $h, 'CNZ'); // définit la case comme zone de découpe (clip) pour l'image ci-dessous
  $pdf->Image($path, $x - (($draw_w - $w) / 2), $y - (($draw_h - $h) / 2), $draw_w, $draw_h);
  $pdf->StopTransform();
}

function gwseq_horse_pdf_draw_chip($pdf, $x, $y, $text, $bg_rgb, $text_rgb, $font_size = 8, $bold = false) {
  $pdf->SetFont('helvetica', $bold ? 'B' : '', $font_size);
  $pad_x = 2.6;
  $text_w = $pdf->GetStringWidth($text);
  $chip_w = $text_w + ($pad_x * 2);
  $chip_h = 6.2;

  $pdf->SetFillColor($bg_rgb[0], $bg_rgb[1], $bg_rgb[2]);
  if (method_exists($pdf, 'RoundedRect')) {
    $pdf->RoundedRect($x, $y, $chip_w, $chip_h, 1.4, '1111', 'F');
  } else {
    $pdf->Rect($x, $y, $chip_w, $chip_h, 'F');
  }
  $pdf->SetTextColor($text_rgb[0], $text_rgb[1], $text_rgb[2]);
  $pdf->SetXY($x, $y + 1.1);
  $pdf->Cell($chip_w, $chip_h - 1.6, $text, 0, 0, 'C');
  return $chip_w;
}

function gwseq_horse_pdf_draw_chip_row($pdf, $x, $y, $max_w, $chips, $gap = 2.5, $line_gap = 2.2) {
  if (!$chips) return $y;
  $cursor_x = $x;
  $cursor_y = $y;
  $row_h = 6.2;
  foreach ($chips as $chip) {
    $pdf->SetFont('helvetica', !empty($chip['bold']) ? 'B' : '', $chip['font_size'] ?? 8);
    $probe_w = $pdf->GetStringWidth($chip['text']) + 5.2;
    if ($cursor_x !== $x && ($cursor_x - $x + $probe_w) > $max_w) {
      $cursor_x = $x;
      $cursor_y += $row_h + $line_gap;
    }
    $used = gwseq_horse_pdf_draw_chip($pdf, $cursor_x, $cursor_y, $chip['text'], $chip['bg'], $chip['color'], $chip['font_size'] ?? 8, !empty($chip['bold']));
    $cursor_x += $used + $gap;
  }
  return $cursor_y + $row_h;
}

/**
 * Composant "PERFORMANCES" (direction de design du Lot 3A bis, §5) : tuiles compactes, largeur de
 * ligne déterminée par le nombre d'indices RÉELLEMENT présents — jamais un emplacement réservé pour
 * un indice absent ("le second cas ne doit surtout pas conserver l'espace de cinq cases"). Chaque
 * tuile : libellé en petites capitales, valeur en gras dessous, fine bordure. Retourne la position Y
 * après le composant, ou $y inchangé si aucun indice n'est disponible (rien dessiné).
 */
function gwseq_horse_pdf_draw_performance_tiles($pdf, $x, $y, $sport_indices, $genetic_indices, $primary_rgb) {
  $tiles = array();
  $best_sport = gwseq_horse_pdf_best_sport_value($sport_indices);
  foreach ($sport_indices as $key => $indice) {
    $tiles[] = array('label' => strtoupper($key), 'value' => (string) $indice['valeur'], 'highlight' => $best_sport !== null && (float) $indice['valeur'] === $best_sport);
  }
  foreach ($genetic_indices as $key => $indice) {
    $tiles[] = array('label' => strtoupper($key), 'value' => gwseq_cheval_genetic_indice_label($indice['valeur'], ''), 'highlight' => false);
  }
  if (!$tiles) return $y;

  $tile_w = 26;
  $tile_h = 15;
  $gap = 3;
  $cursor_x = $x;
  $cursor_y = $y;
  $max_w_probe = $pdf->getPageWidth() - GWSEQ_PDF_CONTENT_MARGIN - $x; // rarement dépassé (6 tuiles max), garde-fou de repli à la ligne malgré tout
  foreach ($tiles as $tile) {
    if ($cursor_x !== $x && ($cursor_x - $x + $tile_w) > $max_w_probe) {
      $cursor_x = $x;
      $cursor_y += $tile_h + $gap;
    }
    $border_rgb = $tile['highlight'] ? $primary_rgb : array(222, 218, 210);
    $pdf->SetDrawColor($border_rgb[0], $border_rgb[1], $border_rgb[2]);
    $pdf->SetLineWidth($tile['highlight'] ? 0.5 : 0.25);
    $pdf->Rect($cursor_x, $cursor_y, $tile_w, $tile_h, 'D');

    $pdf->SetFont('helvetica', '', 7);
    $pdf->SetTextColor(130, 125, 115);
    $pdf->SetXY($cursor_x, $cursor_y + 2);
    $pdf->Cell($tile_w, 3.2, $tile['label'], 0, 0, 'C');

    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetTextColor($primary_rgb[0], $primary_rgb[1], $primary_rgb[2]);
    $pdf->SetXY($cursor_x, $cursor_y + 6.2);
    $pdf->Cell($tile_w, 6, $tile['value'], 0, 0, 'C');

    $cursor_x += $tile_w + $gap;
  }
  return $cursor_y + $tile_h;
}

/**
 * Titre de section sobre — texte en gras couleur principale + filet fin dessous (composant réutilisé
 * par toutes les sections nommées : Pedigree, Présentation, Production...).
 */
function gwseq_horse_pdf_draw_section_title($pdf, $x, $y, $w, $title, $primary_rgb) {
  $pdf->SetFont('helvetica', 'B', 10);
  $pdf->SetTextColor($primary_rgb[0], $primary_rgb[1], $primary_rgb[2]);
  $pdf->SetXY($x, $y);
  $pdf->Cell($w, 5, mb_strtoupper($title), 0, 1, 'L');
  $pdf->SetDrawColor($primary_rgb[0], $primary_rgb[1], $primary_rgb[2]);
  $pdf->SetLineWidth(0.2);
  $pdf->Line($x, $y + 5, $x + $w, $y + 5);
  return $y + 7;
}

/**
 * Bloc éditorial générique (Présentation / Conseil de croisement / Conditions de monte, §11/§12/§15
 * de la demande) — titre + paragraphe, texte tronqué proprement plutôt que chevauché
 * (gwseq_horse_pdf_fit_text_to_height()) si $max_h est fourni. $emphasize ajoute un fond très léger
 * (couleur principale éclaircie) pour donner "une vraie importance visuelle" (§12/§15) sans jamais
 * de grande surface saturée (§12 du Lot 3A, toujours valable).
 */
function gwseq_horse_pdf_draw_paragraph_block($pdf, $x, $y, $w, $title, $body, $primary_rgb, $primary_light_rgb, $max_h = null, $emphasize = false) {
  $body = trim((string) $body);
  if ($body === '') return $y;

  $y_before_title = $y;
  $y = gwseq_horse_pdf_draw_section_title($pdf, $x, $y, $w, $title, $primary_rgb);
  $title_consumed = $y - $y_before_title;

  $pdf->SetFont('helvetica', '', 9);
  $pad = $emphasize ? 3 : 0;
  $text_w = $w - (2 * $pad);
  $available_h = $max_h !== null ? max(0, $max_h - $title_consumed - (2 * $pad)) : null;
  $fitted = $available_h !== null ? gwseq_horse_pdf_fit_text_to_height($pdf, $body, $text_w, $available_h) : $body;
  if ($fitted === '') return $y;

  $needed_h = $pdf->getStringHeight($text_w, $fitted);
  if ($emphasize) {
    $primary_tint = gws_core_pdf_lighten_color(sprintf('#%02x%02x%02x', $primary_rgb[0], $primary_rgb[1], $primary_rgb[2]), 0.9);
    $pdf->SetFillColor($primary_tint[0], $primary_tint[1], $primary_tint[2]);
    $pdf->Rect($x, $y, $w, $needed_h + (2 * $pad), 'F');
  }
  $pdf->SetTextColor(60, 55, 48);
  $pdf->SetXY($x + $pad, $y + $pad);
  $pdf->MultiCell($text_w, 4.3, $fitted, 0, 'L', false, 1);
  return $y + $needed_h + (2 * $pad) + 3;
}

/* -------------------------------------------------------------------------------------------
 * Pedigree — vrai arbre à 3 générations avec branches (§10 de la demande, refonte complète).
 * ----------------------------------------------------------------------------------------- */

function gwseq_horse_pdf_pedigree_node_label($node) {
  if (!is_array($node)) return null;
  if (!in_array($node['type'] ?? '', array('gws_horse', 'external'), true)) return null;
  $name = $node['name'] ?? '';
  if ($name === '') return null;
  return array('name' => $name, 'breed' => $node['breed'] ?? '');
}

function gwseq_horse_pdf_draw_pedigree_label($pdf, $x, $y, $w, $text, $rgb, $bold, $start_size, $min_size, $align = 'L') {
  gwseq_horse_pdf_fit_font_size($pdf, $text, $w, $bold, $start_size, $min_size);
  $pdf->SetTextColor($rgb[0], $rgb[1], $rgb[2]);
  $pdf->SetXY($x, $y);
  $pdf->Cell($w, 4.2, $text, 0, 0, $align, false, '', 1, true);
}

/**
 * Arbre pedigree complet : sujet (case pleine couleur principale) -> père/mère -> grands-parents,
 * reliés par de fines branches (§10, "branches fines ; noms clairement alignés"). Retourne la
 * position Y après le bloc ; $y inchangé si aucun parent n'est renseigné.
 */
function gwseq_horse_pdf_draw_pedigree_tree($pdf, $x, $y, $w, $h, $subject_name, $pedigree, $primary_rgb, $muted_rgb) {
  $father = gwseq_horse_pdf_pedigree_node_label($pedigree['father'] ?? null);
  $mother = gwseq_horse_pdf_pedigree_node_label($pedigree['mother'] ?? null);
  if (!$father && !$mother) return $y;

  $row_h = $h / 4;
  $line_rgb = array(200, 195, 185);

  // Colonnes : sujet | branches | père/mère | branches | grands-parents.
  $col0_w = $w * 0.24;
  $bracket1_w = $w * 0.05;
  $col1_x = $x + $col0_w + $bracket1_w;
  $col1_w = $w * 0.30;
  $bracket2_w = $w * 0.05;
  $col2_x = $col1_x + $col1_w + $bracket2_w;
  $col2_w = $x + $w - $col2_x;

  $y_gp = array($y + ($row_h * 0.5), $y + ($row_h * 1.5), $y + ($row_h * 2.5), $y + ($row_h * 3.5));
  $y_father = ($y_gp[0] + $y_gp[1]) / 2;
  $y_mother = ($y_gp[2] + $y_gp[3]) / 2;
  $y_subject = ($y_father + $y_mother) / 2;

  $pdf->SetDrawColor($line_rgb[0], $line_rgb[1], $line_rgb[2]);
  $pdf->SetLineWidth(0.2);

  // Case sujet (pleine couleur principale, blanc dessus) — pas de dépendance au contraste calculé
  // de "Ma structure" ici (toujours blanc sur la couleur principale, cohérent avec le header/footer).
  $box_h = min(12, $h * 0.22);
  $pdf->SetFillColor($primary_rgb[0], $primary_rgb[1], $primary_rgb[2]);
  if (method_exists($pdf, 'RoundedRect')) {
    $pdf->RoundedRect($x, $y_subject - ($box_h / 2), $col0_w, $box_h, 1.2, '1111', 'F');
  } else {
    $pdf->Rect($x, $y_subject - ($box_h / 2), $col0_w, $box_h, 'F');
  }
  gwseq_horse_pdf_fit_font_size($pdf, mb_strtoupper($subject_name), $col0_w - 4, true, 9, 6.5);
  $pdf->SetTextColor(255, 255, 255);
  $pdf->SetXY($x + 2, $y_subject - 2.1);
  $pdf->Cell($col0_w - 4, 4.2, mb_strtoupper($subject_name), 0, 0, 'C');

  // Branche sujet -> père/mère.
  $bracket1_x = $x + $col0_w + ($bracket1_w / 2);
  $pdf->Line($x + $col0_w, $y_subject, $bracket1_x, $y_subject);
  if ($father && $mother) $pdf->Line($bracket1_x, $y_father, $bracket1_x, $y_mother);
  if ($father) $pdf->Line($bracket1_x, $y_father, $col1_x, $y_father);
  if ($mother) $pdf->Line($bracket1_x, $y_mother, $col1_x, $y_mother);

  $draw_parent = function ($label, $y_center) use ($pdf, $col1_x, $col1_w, $primary_rgb) {
    if (!$label) return;
    gwseq_horse_pdf_draw_pedigree_label($pdf, $col1_x, $y_center - 4.6, $col1_w, mb_strtoupper($label['name']), $primary_rgb, true, 9, 7);
    if ($label['breed'] !== '') {
      $pdf->SetFont('helvetica', '', 6.5);
      $pdf->SetTextColor(150, 145, 135);
      $pdf->SetXY($col1_x, $y_center - 0.3);
      $pdf->Cell($col1_w, 3, $label['breed'], 0, 0, 'L');
    }
  };
  $draw_parent($father, $y_father);
  $draw_parent($mother, $y_mother);

  // Branches père -> grands-parents paternels, mère -> grands-parents maternels.
  $bracket2_x = $col1_x + $col1_w + ($bracket2_w / 2);
  $draw_gp_branch = function ($parent_label, $y_parent, $y_gp_top, $y_gp_bottom) use ($pdf, $col1_x, $col1_w, $bracket2_x, $col2_x) {
    if (!$parent_label) return;
    $pdf->Line($col1_x + $col1_w, $y_parent, $bracket2_x, $y_parent);
    $pdf->Line($bracket2_x, $y_gp_top, $bracket2_x, $y_gp_bottom);
    $pdf->Line($bracket2_x, $y_gp_top, $col2_x, $y_gp_top);
    $pdf->Line($bracket2_x, $y_gp_bottom, $col2_x, $y_gp_bottom);
  };
  $draw_gp_branch($father, $y_father, $y_gp[0], $y_gp[1]);
  $draw_gp_branch($mother, $y_mother, $y_gp[2], $y_gp[3]);

  $draw_gp = function ($node, $y_center) use ($pdf, $col2_x, $col2_w, $muted_rgb) {
    $label = gwseq_horse_pdf_pedigree_node_label($node);
    if (!$label) return;
    gwseq_horse_pdf_draw_pedigree_label($pdf, $col2_x, $y_center - 3.6, $col2_w, mb_strtoupper($label['name']), $muted_rgb, false, 7.5, 6);
    if ($label['breed'] !== '') {
      $pdf->SetFont('helvetica', '', 6);
      $pdf->SetTextColor(170, 165, 155);
      $pdf->SetXY($col2_x, $y_center + 0.4);
      $pdf->Cell($col2_w, 2.8, $label['breed'], 0, 0, 'L');
    }
  };
  $draw_gp($pedigree['father']['father'] ?? null, $y_gp[0]);
  $draw_gp($pedigree['father']['mother'] ?? null, $y_gp[1]);
  $draw_gp($pedigree['mother']['father'] ?? null, $y_gp[2]);
  $draw_gp($pedigree['mother']['mother'] ?? null, $y_gp[3]);

  return $y + $h;
}

/* -------------------------------------------------------------------------------------------
 * Composants spécifiques Étalon — notation en étoiles (vectorielle, jamais un glyphe de police,
 * voir CR : les polices cœur PDF n'ont pas de caractère étoile fiable), stud-books, WFFS, bloc
 * reproduction adaptatif, conditions de monte.
 * ----------------------------------------------------------------------------------------- */

/**
 * Sommets d'une étoile à 5 branches centrée sur ($cx,$cy) — géométrie PURE, réutilisée par
 * gwseq_horse_pdf_draw_star_rating() ci-dessous. Retourne un tableau plat [x1,y1,x2,y2,...] au
 * format attendu par TCPDF::Polygon().
 */
function gwseq_horse_pdf_star_points($cx, $cy, $r_outer, $r_inner) {
  $points = array();
  for ($i = 0; $i < 10; $i++) {
    $r = ($i % 2 === 0) ? $r_outer : $r_inner;
    $angle = (M_PI / 2) + ($i * M_PI / 5); // pointe vers le haut
    $points[] = $cx + ($r * cos($angle));
    $points[] = $cy - ($r * sin($angle));
  }
  return $points;
}

/**
 * Notation 1-5 en étoiles VECTORIELLES (jamais un caractère "★" — les polices cœur standard PDF,
 * seules embarquées dans ce projet, ne garantissent pas ce glyphe, voir includes/pdf-engine.php).
 * Retourne la largeur totale utilisée.
 */
function gwseq_horse_pdf_draw_star_rating($pdf, $x, $y, $rating, $primary_rgb, $size = 3.4) {
  $gap = 1.2;
  $r_outer = $size / 2;
  $r_inner = $r_outer * 0.42;
  $empty_rgb = array(222, 218, 210);
  for ($i = 0; $i < 5; $i++) {
    $cx = $x + $r_outer + ($i * ($size + $gap));
    $cy = $y + $r_outer;
    $filled = $i < $rating;
    $color = $filled ? $primary_rgb : $empty_rgb;
    $pdf->Polygon(gwseq_horse_pdf_star_points($cx, $cy, $r_outer, $r_inner), 'F', array(), $color);
  }
  return (5 * $size) + (4 * $gap);
}

/**
 * Liste de stud-books avec retour à la ligne propre (§14 : "le composant doit gérer les retours à
 * la ligne proprement... pas de débordement", "maximum deux lignes"). Retourne le texte final
 * (déjà tronqué si nécessaire au-delà de 2 lignes) — l'appelant le passe à MultiCell().
 */
function gwseq_horse_pdf_studbooks_text($pdf, $codes, $w) {
  $text = implode(' · ', $codes);
  if ($text === '') return '';
  $max_h = $pdf->getStringHeight($w, ' ') * 2 + 0.5; // budget de 2 lignes à la police déjà active
  return gwseq_horse_pdf_fit_text_to_height($pdf, $text, $w, $max_h);
}

/**
 * Bloc "Reproduction" adaptatif (§13/§14 de la demande) : statut ostéo (étoiles) / stud-books
 * d'approbation / WFFS — SEULS les éléments réellement renseignés occupent une zone, largeur
 * répartie également entre eux (jamais un emplacement fixe pour un champ absent). Rien dessiné
 * (retourne $y inchangé) si les trois sont vides.
 */
function gwseq_horse_pdf_draw_reproduction_block($pdf, $x, $y, $w, $data, $primary_rgb) {
  $items = array();
  if ($data['statut_osteo'] > 0) $items[] = 'osteo';
  if ($data['studbooks_labels']) $items[] = 'studbooks';
  if (trim((string) $data['wffs']) !== '') $items[] = 'wffs';
  if (!$items) return $y;

  $y = gwseq_horse_pdf_draw_section_title($pdf, $x, $y, $w, __('Reproduction', 'gws-core'), $primary_rgb);

  $gap = 6;
  $count = count($items);
  $cell_w = ($w - (($count - 1) * $gap)) / $count;
  $cx = $x;
  $max_row_h = 0;
  $label_rgb = array(130, 125, 115);

  foreach ($items as $item) {
    $pdf->SetFont('helvetica', '', 7.5);
    $pdf->SetTextColor($label_rgb[0], $label_rgb[1], $label_rgb[2]);
    $pdf->SetXY($cx, $y);
    $row_h = 4;

    if ($item === 'osteo') {
      $pdf->Cell($cell_w, 4, mb_strtoupper(__('Statut ostéo-articulaire', 'gws-core')), 0, 1, 'L');
      gwseq_horse_pdf_draw_star_rating($pdf, $cx, $y + 5, $data['statut_osteo'], $primary_rgb);
      $row_h = 5 + 3.4 + 1;
    } elseif ($item === 'studbooks') {
      $pdf->Cell($cell_w, 4, mb_strtoupper(__('Stud-book(s) d’approbation', 'gws-core')), 0, 1, 'L');
      $pdf->SetFont('helvetica', 'B', 9.5);
      $pdf->SetTextColor($primary_rgb[0], $primary_rgb[1], $primary_rgb[2]);
      $pdf->SetXY($cx, $y + 5);
      $text = gwseq_horse_pdf_studbooks_text($pdf, $data['studbooks_labels'], $cell_w);
      $pdf->MultiCell($cell_w, 4.2, $text, 0, 'L', false, 1);
      $row_h = 5 + $pdf->getStringHeight($cell_w, $text) + 1;
    } elseif ($item === 'wffs') {
      $pdf->Cell($cell_w, 4, mb_strtoupper('WFFS'), 0, 1, 'L');
      $pdf->SetFont('helvetica', 'B', 9.5);
      $pdf->SetTextColor($primary_rgb[0], $primary_rgb[1], $primary_rgb[2]);
      $pdf->SetXY($cx, $y + 5);
      $pdf->Cell($cell_w, 5, $data['wffs'], 0, 0, 'L');
      $row_h = 5 + 5 + 1;
    }

    $max_row_h = max($max_row_h, $row_h);
    $cx += $cell_w + $gap;
  }

  return $y + $max_row_h + 3;
}

/* -------------------------------------------------------------------------------------------
 * Production (jument) — compacte, adaptative (§20/§21 de la demande).
 * ----------------------------------------------------------------------------------------- */

function gwseq_horse_pdf_draw_production_block($pdf, $x, $y, $w, $production, $commentaire, $primary_rgb, $max_entries) {
  if (!$production) return $y;

  $title = sprintf(
    /* translators: %d: nombre de produits directs détectés */
    _n('Production (%d produit)', 'Production (%d produits)', count($production), 'gws-core'),
    count($production)
  );
  $y = gwseq_horse_pdf_draw_section_title($pdf, $x, $y, $w, $title, $primary_rgb);

  $commentaire = trim((string) $commentaire);
  if ($commentaire !== '') {
    $pdf->SetFont('helvetica', 'I', 8.5);
    $pdf->SetTextColor(110, 105, 95);
    $pdf->SetXY($x, $y);
    $fitted = gwseq_horse_pdf_fit_text_to_height($pdf, $commentaire, $w, $pdf->getStringHeight($w, ' ') * 2);
    $pdf->MultiCell($w, 4.2, $fitted, 0, 'L', false, 1);
    $y = $pdf->GetY() + 1.5;
  }

  $selected = gwseq_horse_pdf_select_production_entries($production, $max_entries);
  $hidden_count = count($production) - count($selected);

  $pdf->SetFont('helvetica', '', 8.5);
  $pdf->SetTextColor(70, 65, 58);
  $pdf->SetDrawColor(230, 226, 218);
  $pdf->SetLineWidth(0.15);
  foreach ($selected as $entry) {
    $pdf->SetXY($x, $y);
    $pdf->Cell($w, 4.6, gwseq_horse_pdf_production_line($entry), 0, 1, 'L');
    $pdf->Line($x, $y + 4.7, $x + $w, $y + 4.7);
    $y += 5.1;
  }

  if ($hidden_count > 0) {
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->SetTextColor(140, 135, 125);
    $pdf->SetXY($x, $y + 0.5);
    $pdf->Cell($w, 4, sprintf(
      /* translators: %d: nombre de produits non affichés faute de place */
      _n('+ %d autre produit', '+ %d autres produits', $hidden_count, 'gws-core'),
      $hidden_count
    ), 0, 1, 'L');
    $y = $pdf->GetY();
  }

  return $y + 2;
}

/* -------------------------------------------------------------------------------------------
 * Templates métier — 3 compositions des mêmes composants (§1 de la demande).
 * ----------------------------------------------------------------------------------------- */

/**
 * Bloc hero commun (photo + identité) — partagé par les 3 templates, seul le contenu affiché à
 * droite de la photo varie légèrement (statut/prix, naisseur...) via $extra_lines.
 */
function gwseq_horse_pdf_draw_hero($pdf, $x, $y, $content_w, $data, $primary_rgb, $secondary_rgb, $show_price, $show_naisseur) {
  $gallery_w = $content_w * 0.56;
  $identity_x = $x + $gallery_w + 6;
  $identity_w = $content_w - $gallery_w - 6;
  $gallery_h = 78;

  gwseq_horse_pdf_draw_gallery($pdf, $x, $y, $gallery_w, $gallery_h, $data['photo_path'], $data['gallery_paths']);

  $iy = $y;
  $pdf->SetFont('helvetica', 'B', 22);
  $pdf->SetTextColor($primary_rgb[0], $primary_rgb[1], $primary_rgb[2]);
  $pdf->SetXY($identity_x, $iy);
  $pdf->MultiCell($identity_w, 9, mb_strtoupper($data['name']), 0, 'L', false, 1);
  $iy = $pdf->GetY() + 1;

  $subline = array_values(array_filter(array($data['sexe_label'], $data['identity']['annee_naissance'] ?: '', $data['race_label'], $data['robe_label'])));
  if ($subline) {
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetTextColor(90, 85, 78);
    $pdf->SetXY($identity_x, $iy);
    $pdf->MultiCell($identity_w, 4.6, implode(' · ', $subline), 0, 'L', false, 1);
    $iy = $pdf->GetY() + 0.5;
  }
  if (($data['identity']['taille_cm'] ?? '') !== '') {
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor(130, 125, 115);
    $pdf->SetXY($identity_x, $iy);
    $pdf->Cell($identity_w, 4, number_format(((float) $data['identity']['taille_cm']) / 100, 2, ',', '') . ' m', 0, 1, 'L');
    $iy = $pdf->GetY();
  }
  if ($show_naisseur && ($data['identity']['eleveur'] ?? '') !== '') {
    $pdf->SetFont('helvetica', '', 8.5);
    $pdf->SetTextColor(140, 135, 125);
    $pdf->SetXY($identity_x, $iy);
    $pdf->Cell($identity_w, 4, __('Naisseur : ', 'gws-core') . $data['identity']['eleveur'], 0, 1, 'L');
    $iy = $pdf->GetY();
  }
  $iy += 2;

  if ($show_price) {
    $statut = $data['commercial']['statut_commercial'] ?? 'not_offered';
    if ($statut !== 'not_offered') {
      $statut_label = gwseq_cheval_statut_commercial_options()[$statut] ?? '';
      if ($statut_label !== '') {
        $chip_w = gwseq_horse_pdf_draw_chip($pdf, $identity_x, $iy, mb_strtoupper($statut_label), $secondary_rgb, array(255, 255, 255), 8.5, true);
        if ($data['price_summary'] !== '') {
          $pdf->SetFont('helvetica', 'B', 14);
          $pdf->SetTextColor($secondary_rgb[0], $secondary_rgb[1], $secondary_rgb[2]);
          $pdf->SetXY($identity_x + $chip_w + 4, $iy);
          $pdf->Cell($identity_w - $chip_w - 4, 7, $data['price_summary'], 0, 0, 'L');
        }
        $iy += 8.5;
      }
    } elseif ($data['price_summary'] !== '') {
      $pdf->SetFont('helvetica', 'B', 14);
      $pdf->SetTextColor($secondary_rgb[0], $secondary_rgb[1], $secondary_rgb[2]);
      $pdf->SetXY($identity_x, $iy);
      $pdf->Cell($identity_w, 7, $data['price_summary'], 0, 1, 'L');
      $iy = $pdf->GetY();
    }
  }

  return array('y' => $y + $gallery_h, 'identity_x' => $identity_x, 'identity_w' => $identity_w, 'identity_y' => $iy);
}

function gwseq_horse_pdf_draw_qualites_and_retenir($pdf, $x, $y, $w, $data, $primary_rgb) {
  if ($data['qualites']) {
    $chips = array();
    foreach (array_slice($data['qualites'], 0, 5) as $qualite) {
      $chips[] = array('text' => $qualite, 'bg' => array(243, 240, 235), 'color' => array(90, 85, 78), 'font_size' => 7.5);
    }
    $y = gwseq_horse_pdf_draw_chip_row($pdf, $x, $y, $w, $chips) + 3;
  }
  if ($data['faits_marquants']) {
    $pdf->SetDrawColor($primary_rgb[0], $primary_rgb[1], $primary_rgb[2]);
    $pdf->SetLineWidth(0.7);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor(70, 65, 58);
    foreach (array_slice($data['faits_marquants'], 0, 3) as $fait) {
      $pdf->Line($x, $y + 0.5, $x, $y + 4.5);
      $pdf->SetXY($x + 3, $y);
      $pdf->Cell($w - 3, 5, $fait, 0, 1, 'L');
      $y += 5.3;
    }
    $y += 2;
  }
  return $y;
}

function gwseq_render_horse_pdf_template_etalon($pdf, $data) {
  $structure = $data['structure'];
  $primary_rgb = gws_core_pdf_hex_to_rgb($structure['primary_color']);
  $secondary_rgb = gws_core_pdf_hex_to_rgb($structure['secondary_color']);
  $muted_rgb = array(150, 145, 135);
  $margin = GWSEQ_PDF_CONTENT_MARGIN;
  $content_w = $pdf->getPageWidth() - (2 * $margin);

  gwseq_horse_pdf_draw_header($pdf, $structure, __('Fiche étalon', 'gws-core'));
  $y = GWSEQ_PDF_HEADER_BANNER_H + 6;

  $hero = gwseq_horse_pdf_draw_hero($pdf, $margin, $y, $content_w, $data, $primary_rgb, $secondary_rgb, false, true);
  $iy = $hero['identity_y'];
  $iy = gwseq_horse_pdf_draw_performance_tiles($pdf, $hero['identity_x'], $iy, $data['sport_indices'], $data['genetic_indices'], $primary_rgb) + 3;
  $iy = gwseq_horse_pdf_draw_qualites_and_retenir($pdf, $hero['identity_x'], $iy, $hero['identity_w'], $data, $primary_rgb);
  $y = max($hero['y'], $iy) + 4;

  $tree_h = 46;
  $has_tree = !empty($data['pedigree']['father']) || !empty($data['pedigree']['mother']);
  if ($has_tree) {
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetTextColor($primary_rgb[0], $primary_rgb[1], $primary_rgb[2]);
    $pdf->SetXY($margin, $y);
    $pdf->Cell($content_w, 5, mb_strtoupper(__('Pedigree', 'gws-core')), 0, 1, 'L');
    $y = gwseq_horse_pdf_draw_pedigree_tree($pdf, $margin, $y + 7, $content_w, $tree_h, $data['name'], $data['pedigree'], $primary_rgb, $muted_rgb);
    $y += 3;
  }

  $footer_top = $pdf->getPageHeight() - GWSEQ_PDF_FOOTER_BANNER_H - 6;
  $col_gap = 8;
  $col_w = ($content_w - $col_gap) / 2;
  $presentation_y = gwseq_horse_pdf_draw_paragraph_block($pdf, $margin, $y, $col_w, __('Présentation', 'gws-core'), $data['editorial']['presentation'] ?? '', $primary_rgb, null, $footer_top - $y, false);
  $conseil_y = gwseq_horse_pdf_draw_paragraph_block($pdf, $margin + $col_w + $col_gap, $y, $col_w, __('Conseil de croisement', 'gws-core'), $data['editorial']['conseils_croisement'] ?? '', $primary_rgb, null, $footer_top - $y, true);
  $y = max($presentation_y, $conseil_y);

  $y = gwseq_horse_pdf_draw_reproduction_block($pdf, $margin, $y, $content_w, $data, $primary_rgb);

  $year = (int) date('Y') + 1;
  $y = gwseq_horse_pdf_draw_paragraph_block($pdf, $margin, $y, $content_w, sprintf(__('Conditions de monte %d', 'gws-core'), $year), $data['editorial']['conditions_vente'] ?? '', $primary_rgb, null, $footer_top - $y, true);

  // Identifiants officiels minimaux (naisseur déjà affiché dans le hero, §4 — jamais répété ici).
  $ids = array();
  if (($data['identity']['sire'] ?? '') !== '') $ids[] = 'SIRE : ' . $data['identity']['sire'];
  if (($data['identity']['ueln'] ?? '') !== '') $ids[] = 'UELN : ' . $data['identity']['ueln'];
  if (($data['identity']['proprietaire'] ?? '') !== '') $ids[] = __('Propriétaire : ', 'gws-core') . $data['identity']['proprietaire'];
  if ($ids && $y < $footer_top) {
    $pdf->SetFont('helvetica', '', 7.5);
    $pdf->SetTextColor(150, 145, 135);
    $pdf->SetXY($margin, min($y, $footer_top - 4));
    $pdf->Cell($content_w, 4, implode('   ·   ', $ids), 0, 0, 'L');
  }

  gwseq_horse_pdf_draw_footer($pdf, $structure, $data['public_url']);
  return true;
}

function gwseq_render_horse_pdf_template_pouliniere($pdf, $data) {
  $structure = $data['structure'];
  $primary_rgb = gws_core_pdf_hex_to_rgb($structure['primary_color']);
  $secondary_rgb = gws_core_pdf_hex_to_rgb($structure['secondary_color']);
  $muted_rgb = array(150, 145, 135);
  $margin = GWSEQ_PDF_CONTENT_MARGIN;
  $content_w = $pdf->getPageWidth() - (2 * $margin);

  gwseq_horse_pdf_draw_header($pdf, $structure, __('Fiche poulinière', 'gws-core'));
  $y = GWSEQ_PDF_HEADER_BANNER_H + 6;

  $hero = gwseq_horse_pdf_draw_hero($pdf, $margin, $y, $content_w, $data, $primary_rgb, $secondary_rgb, true, false);
  $iy = $hero['identity_y'];
  $iy = gwseq_horse_pdf_draw_performance_tiles($pdf, $hero['identity_x'], $iy, $data['sport_indices'], $data['genetic_indices'], $primary_rgb) + 3;
  $iy = gwseq_horse_pdf_draw_qualites_and_retenir($pdf, $hero['identity_x'], $iy, $hero['identity_w'], $data, $primary_rgb);
  $y = max($hero['y'], $iy) + 4;

  $tree_h = 44;
  $has_tree = !empty($data['pedigree']['father']) || !empty($data['pedigree']['mother']);
  if ($has_tree) {
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetTextColor($primary_rgb[0], $primary_rgb[1], $primary_rgb[2]);
    $pdf->SetXY($margin, $y);
    $pdf->Cell($content_w, 5, mb_strtoupper(__('Pedigree', 'gws-core')), 0, 1, 'L');
    $y = gwseq_horse_pdf_draw_pedigree_tree($pdf, $margin, $y + 7, $content_w, $tree_h, $data['name'], $data['pedigree'], $primary_rgb, $muted_rgb);
    $y += 3;
  }

  $footer_top = $pdf->getPageHeight() - GWSEQ_PDF_FOOTER_BANNER_H - 6;
  $remaining_h = $footer_top - $y;
  // Résiste à une poulinière très prolifique (§21) : budget de lignes calculé sur l'espace
  // RÉELLEMENT restant plutôt qu'un nombre fixe — jamais un débordement, "+N autres produits" pour
  // le reste.
  $max_entries = max(3, (int) floor($remaining_h / 5.4) - 1);
  $y = gwseq_horse_pdf_draw_production_block($pdf, $margin, $y, $content_w, $data['production'], $data['editorial']['commentaire_production'] ?? '', $primary_rgb, $max_entries);

  gwseq_horse_pdf_draw_footer($pdf, $structure, $data['public_url']);
  return true;
}

function gwseq_render_horse_pdf_template_sport_vente($pdf, $data) {
  $structure = $data['structure'];
  $primary_rgb = gws_core_pdf_hex_to_rgb($structure['primary_color']);
  $secondary_rgb = gws_core_pdf_hex_to_rgb($structure['secondary_color']);
  $muted_rgb = array(150, 145, 135);
  $margin = GWSEQ_PDF_CONTENT_MARGIN;
  $content_w = $pdf->getPageWidth() - (2 * $margin);

  gwseq_horse_pdf_draw_header($pdf, $structure, __('Fiche cheval de sport', 'gws-core'));
  $y = GWSEQ_PDF_HEADER_BANNER_H + 6;

  $hero = gwseq_horse_pdf_draw_hero($pdf, $margin, $y, $content_w, $data, $primary_rgb, $secondary_rgb, true, false);
  $iy = $hero['identity_y'];
  $iy = gwseq_horse_pdf_draw_performance_tiles($pdf, $hero['identity_x'], $iy, $data['sport_indices'], $data['genetic_indices'], $primary_rgb) + 3;
  $iy = gwseq_horse_pdf_draw_qualites_and_retenir($pdf, $hero['identity_x'], $iy, $hero['identity_w'], $data, $primary_rgb);
  $y = max($hero['y'], $iy) + 4;

  $footer_top = $pdf->getPageHeight() - GWSEQ_PDF_FOOTER_BANNER_H - 6;
  $has_tree = !empty($data['pedigree']['father']) || !empty($data['pedigree']['mother']);
  // Priorité §26 : pedigree (7) puis présentation (8) sont les deux derniers éléments — s'il ne
  // reste plus assez de place pour un arbre lisible, il est simplement omis plutôt que compressé
  // ou chevauché (jamais de débordement, §15) ; la présentation, elle, se tronque proprement
  // (gwseq_horse_pdf_draw_paragraph_block() gère déjà ce cas).
  $tree_h = 44;
  if ($has_tree && ($footer_top - $y) > ($tree_h + 20)) {
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetTextColor($primary_rgb[0], $primary_rgb[1], $primary_rgb[2]);
    $pdf->SetXY($margin, $y);
    $pdf->Cell($content_w, 5, mb_strtoupper(__('Pedigree', 'gws-core')), 0, 1, 'L');
    $y = gwseq_horse_pdf_draw_pedigree_tree($pdf, $margin, $y + 7, $content_w, $tree_h, $data['name'], $data['pedigree'], $primary_rgb, $muted_rgb);
    $y += 3;
  }

  $y = gwseq_horse_pdf_draw_paragraph_block($pdf, $margin, $y, $content_w, __('Présentation', 'gws-core'), $data['editorial']['presentation'] ?? '', $primary_rgb, null, $footer_top - $y, false);

  // §27 : aucun grand visuel paysager, aucun SIRE/UELN/naisseur/propriétaire — footer directement.
  gwseq_horse_pdf_draw_footer($pdf, $structure, $data['public_url']);
  return true;
}

/* -------------------------------------------------------------------------------------------
 * Renderer partagé — dispatche vers le template résolu (§1/§15 de la demande : un seul point
 * d'entrée, jamais un second moteur).
 * ----------------------------------------------------------------------------------------- */

function gwseq_render_horse_pdf_page($pdf, $horse_id, $context = array()) {
  $data = gwseq_build_horse_pdf_data($horse_id);
  if ($data === null) return false;

  switch ($data['pdf_template']) {
    case 'etalon':
      return gwseq_render_horse_pdf_template_etalon($pdf, $data);
    case 'pouliniere':
      return gwseq_render_horse_pdf_template_pouliniere($pdf, $data);
    default:
      return gwseq_render_horse_pdf_template_sport_vente($pdf, $data);
  }
}

/* -------------------------------------------------------------------------------------------
 * Orchestration — document complet à une page (PDF individuel, §16 du Lot 3A, inchangé).
 * ----------------------------------------------------------------------------------------- */

function gwseq_generate_horse_pdf($horse_id, $context = array()) {
  if (!gws_core_pdf_available()) return null;
  $structure_name = function_exists('gws_core_structure_name') ? gws_core_structure_name() : 'GWS';
  $pdf = gws_core_pdf_new_document('P', $structure_name);
  $title = get_the_title((int) $horse_id);
  if ($title) $pdf->SetTitle($title);
  $pdf->AddPage();
  $ok = gwseq_render_horse_pdf_page($pdf, $horse_id, $context);
  if (!$ok) return null;
  return $pdf;
}

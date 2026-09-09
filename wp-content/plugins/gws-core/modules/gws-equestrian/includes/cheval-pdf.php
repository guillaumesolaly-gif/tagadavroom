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
const GWSEQ_PDF_FOOTER_BANNER_H = 28;
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
 * Adresse condensée d'une ligne pour le footer — ne fabrique jamais une adresse complète si seuls
 * certains champs de "Ma structure" sont renseignés (ex. ville/pays seuls -> "Félines · France",
 * jamais une chaîne "· ·" avec des segments vides).
 */
function gwseq_horse_pdf_structure_address_line($structure) {
  $street = trim(implode(' ', array_filter(array($structure['address_line'] ?? '', $structure['address_line_2'] ?? ''))));
  $locality = trim(implode(' ', array_filter(array($structure['postal_code'] ?? '', $structure['city'] ?? ''))));
  $parts = array_values(array_filter(array($street, $locality, $structure['country'] ?? '')));
  return implode(' · ', $parts);
}

/**
 * Icônes vectorielles minimalistes du footer (jamais une police d'icônes — même principe que les
 * étoiles de notation, voir gwseq_horse_pdf_star_points()) : repère/adresse, téléphone, e-mail,
 * site web. Dessinées dans un carré ($cx,$cy) = centre, $s = côté du carré, toujours dans la
 * couleur de contraste du bandeau (jamais une couleur qui dépendrait du contenu).
 */
function gwseq_horse_pdf_draw_icon_pin($pdf, $cx, $cy, $s, $rgb) {
  $pdf->SetDrawColor($rgb[0], $rgb[1], $rgb[2]);
  $pdf->SetFillColor($rgb[0], $rgb[1], $rgb[2]);
  $r = $s * 0.32;
  $head_cy = $cy - ($s * 0.12);
  $pdf->Circle($cx, $head_cy, $r, 0, 360, 'F');
  $pdf->Polygon(array($cx - ($r * 0.62), $head_cy + ($r * 0.55), $cx + ($r * 0.62), $head_cy + ($r * 0.55), $cx, $cy + ($s * 0.5)), 'F', array(), $rgb);
  $pdf->SetFillColor(255, 255, 255);
  $pdf->Circle($cx, $head_cy, $r * 0.4, 0, 360, 'F');
}

function gwseq_horse_pdf_draw_icon_phone($pdf, $cx, $cy, $s, $rgb) {
  $pdf->SetFillColor($rgb[0], $rgb[1], $rgb[2]);
  $w = $s * 0.42;
  $h = $s * 0.85;
  $pdf->StartTransform();
  $pdf->Rotate(-28, $cx, $cy);
  if (method_exists($pdf, 'RoundedRect')) {
    $pdf->RoundedRect($cx - ($w / 2), $cy - ($h / 2), $w, $h, $w * 0.4, '1111', 'F');
  } else {
    $pdf->Rect($cx - ($w / 2), $cy - ($h / 2), $w, $h, 'F');
  }
  $pdf->StopTransform();
}

function gwseq_horse_pdf_draw_icon_envelope($pdf, $cx, $cy, $s, $rgb) {
  $pdf->SetDrawColor($rgb[0], $rgb[1], $rgb[2]);
  $pdf->SetLineWidth(0.25);
  $w = $s * 0.9;
  $h = $s * 0.62;
  $x = $cx - ($w / 2);
  $y = $cy - ($h / 2);
  $pdf->Rect($x, $y, $w, $h, 'D');
  $pdf->Line($x, $y, $cx, $y + ($h * 0.55));
  $pdf->Line($cx, $y + ($h * 0.55), $x + $w, $y);
}

function gwseq_horse_pdf_draw_icon_globe($pdf, $cx, $cy, $s, $rgb) {
  $pdf->SetDrawColor($rgb[0], $rgb[1], $rgb[2]);
  $pdf->SetLineWidth(0.22);
  $r = $s * 0.42;
  $pdf->Circle($cx, $cy, $r, 0, 360, 'D');
  $pdf->Ellipse($cx, $cy, $r * 0.42, $r, 0, 0, 360, 0, 'D');
  $pdf->Line($cx - $r, $cy, $cx + $r, $cy);
}

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

  $qr_block_w = 0;
  if ($public_url !== '') {
    $qr_size = GWSEQ_PDF_FOOTER_BANNER_H - 12;
    $qr_x = $page_w - GWSEQ_PDF_CONTENT_MARGIN - $qr_size;
    $qr_y = $banner_y + 9;
    $qr_block_w = $qr_size + 6;

    $pdf->SetFont('helvetica', '', 6.5);
    $pdf->SetTextColor($contrast_rgb[0], $contrast_rgb[1], $contrast_rgb[2]);
    $pdf->SetXY($qr_x - 6, $banner_y + 3);
    $pdf->Cell($qr_size + 6, 3.5, mb_strtoupper(__('Voir la fiche en ligne', 'gws-core')), 0, 0, 'C');

    // Fond blanc sous le QR (un module clair sur fond de couleur reste scannable, mais un vrai
    // fond blanc est plus sûr et plus lisible à l'impression — voir §13 du Lot 3A, même prudence).
    $pdf->SetFillColor(255, 255, 255);
    $pdf->Rect($qr_x - 1, $qr_y - 1, $qr_size + 2, $qr_size + 2, 'F');
    $pdf->write2DBarcode($public_url, 'QRCODE,M', $qr_x, $qr_y, $qr_size, $qr_size, array(
      'border' => false, 'padding' => 0, 'fgcolor' => array(0, 0, 0), 'bgcolor' => false,
    ), 'N');
  }

  $icon_col_w = 6.5;
  $text_x = GWSEQ_PDF_CONTENT_MARGIN + $icon_col_w;
  $text_w = $page_w - (2 * GWSEQ_PDF_CONTENT_MARGIN) - $icon_col_w - ($qr_block_w ? $qr_block_w + 6 : 0);

  $coord_lines = array();
  $address_line = gwseq_horse_pdf_structure_address_line($structure);
  if ($address_line !== '') $coord_lines[] = array('icon' => 'pin', 'text' => $address_line);
  if (($structure['phone_display'] ?? '') !== '') $coord_lines[] = array('icon' => 'phone', 'text' => $structure['phone_display']);
  if (($structure['public_email'] ?? '') !== '') $coord_lines[] = array('icon' => 'envelope', 'text' => $structure['public_email']);
  if (($structure['website_url'] ?? '') !== '') $coord_lines[] = array('icon' => 'globe', 'text' => $structure['website_url']);

  $line_h = 4.6;
  $block_h = 6 + (count($coord_lines) * $line_h);
  $block_y = $banner_y + (GWSEQ_PDF_FOOTER_BANNER_H - $block_h) / 2; // centré verticalement : moins de coordonnées -> plus d'air, jamais un bloc collé en haut

  $pdf->SetTextColor($contrast_rgb[0], $contrast_rgb[1], $contrast_rgb[2]);
  $pdf->SetFont('helvetica', 'B', 10.5);
  $pdf->SetXY($text_x, $block_y);
  $pdf->Cell($text_w, 5, $structure['name'], 0, 1, 'L');

  $cy = $block_y + 6 + ($line_h / 2);
  $pdf->SetFont('helvetica', '', 8);
  foreach ($coord_lines as $line) {
    $icon_cx = GWSEQ_PDF_CONTENT_MARGIN + ($icon_col_w / 2) - 1.2;
    switch ($line['icon']) {
      case 'pin': gwseq_horse_pdf_draw_icon_pin($pdf, $icon_cx, $cy, 3.4, $contrast_rgb); break;
      case 'phone': gwseq_horse_pdf_draw_icon_phone($pdf, $icon_cx, $cy, 3.2, $contrast_rgb); break;
      case 'envelope': gwseq_horse_pdf_draw_icon_envelope($pdf, $icon_cx, $cy, 3.6, $contrast_rgb); break;
      case 'globe': gwseq_horse_pdf_draw_icon_globe($pdf, $icon_cx, $cy, 3.6, $contrast_rgb); break;
    }
    $pdf->SetXY($text_x, $cy - 2.1);
    $pdf->Cell($text_w, 4.2, $line['text'], 0, 0, 'L');
    $cy += $line_h;
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
  foreach ($sport_indices as $key => $indice) {
    $tiles[] = array('label' => strtoupper($key), 'value' => (string) $indice['valeur']);
  }
  foreach ($genetic_indices as $key => $indice) {
    $tiles[] = array('label' => strtoupper($key), 'value' => gwseq_cheval_genetic_indice_label($indice['valeur'], ''));
  }
  if (!$tiles) return $y;

  // Tuiles compactes et légères (référence graphique) : fond crème uni, jamais de bordure ni de
  // mise en évidence d'un indice par rapport aux autres — tous les indices présents ont la même
  // importance visuelle (§3 : "pas de grandes cartes fixes... aucun emplacement vide").
  $tile_w = 22;
  $tile_h = 13.5;
  $gap = 2.5;
  $tile_bg = array(244, 240, 233);
  $cursor_x = $x;
  $cursor_y = $y;
  $max_w_probe = $pdf->getPageWidth() - GWSEQ_PDF_CONTENT_MARGIN - $x; // rarement dépassé (6 tuiles max), garde-fou de repli à la ligne malgré tout
  foreach ($tiles as $tile) {
    if ($cursor_x !== $x && ($cursor_x - $x + $tile_w) > $max_w_probe) {
      $cursor_x = $x;
      $cursor_y += $tile_h + $gap;
    }
    $pdf->SetFillColor($tile_bg[0], $tile_bg[1], $tile_bg[2]);
    if (method_exists($pdf, 'RoundedRect')) {
      $pdf->RoundedRect($cursor_x, $cursor_y, $tile_w, $tile_h, 1, '1111', 'F');
    } else {
      $pdf->Rect($cursor_x, $cursor_y, $tile_w, $tile_h, 'F');
    }

    $pdf->SetFont('helvetica', '', 6.3);
    $pdf->SetTextColor(140, 133, 120);
    $pdf->SetXY($cursor_x, $cursor_y + 1.8);
    $pdf->Cell($tile_w, 3, $tile['label'], 0, 0, 'C');

    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->SetTextColor($primary_rgb[0], $primary_rgb[1], $primary_rgb[2]);
    $pdf->SetXY($cursor_x, $cursor_y + 5.4);
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

  // Aucune place, même pour une seule ligne : le bloc entier est omis plutôt que de dessiner un
  // titre suivi de rien (§12/§15 — jamais un composant à moitié vide qui donnerait l'impression
  // d'un bug). Le titre fixe consomme toujours 7 mm (gwseq_horse_pdf_draw_section_title()).
  $pad_probe = $emphasize ? 3 : 0;
  if ($max_h !== null && $max_h < (7 + 4.3 + (2 * $pad_probe))) return $y;

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

/* -------------------------------------------------------------------------------------------
 * Templates métier — 3 compositions des mêmes composants (§1 de la demande).
 * ----------------------------------------------------------------------------------------- */

/**
 * Bloc hero commun (photo + identité) — partagé par les 3 templates, seul le contenu affiché à
 * droite de la photo varie légèrement (statut/prix, naisseur...) via $extra_lines.
 */
function gwseq_horse_pdf_draw_hero($pdf, $x, $y, $content_w, $data, $primary_rgb, $secondary_rgb, $show_price, $show_naisseur) {
  $gallery_w = $content_w * 0.56;
  $identity_x = $x + $gallery_w + 8;
  $identity_w = $content_w - $gallery_w - 8;
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

function gwseq_horse_pdf_draw_qualites_and_retenir($pdf, $x, $y, $w, $data, $primary_rgb, $accent_rgb = null) {
  $accent_rgb = $accent_rgb ?? $primary_rgb;
  if ($data['qualites']) {
    $y = gwseq_horse_pdf_draw_section_title($pdf, $x, $y, $w, __('Qualités', 'gws-core'), $primary_rgb) - 1;
    $chips = array();
    foreach (array_slice($data['qualites'], 0, 5) as $qualite) {
      $chips[] = array('text' => $qualite, 'bg' => array(244, 240, 233), 'color' => array(90, 85, 78), 'font_size' => 7.5);
    }
    $y = gwseq_horse_pdf_draw_chip_row($pdf, $x, $y, $w, $chips) + 3;
  }
  if ($data['faits_marquants']) {
    $y = gwseq_horse_pdf_draw_section_title($pdf, $x, $y, $w, __('À retenir', 'gws-core'), $primary_rgb) - 1;
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor(70, 65, 58);
    // Puce étoile (jamais un pictogramme trophée/coupe, §5) — même géométrie vectorielle que la
    // notation ostéo-articulaire, taille réduite, toujours dans la couleur d'accent.
    foreach (array_slice($data['faits_marquants'], 0, 3) as $fait) {
      $star_pts = gwseq_horse_pdf_star_points($x + 1.4, $y + 2.6, 1.4, 0.58);
      $pdf->Polygon($star_pts, 'F', array(), $accent_rgb);
      $pdf->SetXY($x + 4.2, $y);
      $pdf->Cell($w - 4.2, 5, $fait, 0, 1, 'L');
      $y += 5.3;
    }
    $y += 2;
  }
  return $y;
}

/**
 * Composition RÉSERVÉE à Étalon (repris du correctif client — voir CR du lot : "ne réimplémente pas
 * ces mécanismes depuis ton ancienne version"). Les composants des autres fiches (Poulinière,
 * Sport/Vente) restent inchangés et n'appellent aucune des fonctions `gwseq_etalon_*` ci-dessous.
 * Mesure et dessin utilisent les mêmes métriques TCPDF, sans fit/troncature de texte — la
 * composition se mesure d'abord (mode aéré puis compact), se dessine ensuite ; le débordement réel
 * est géré par pagination explicite (voir `$flow` dans le renderer), jamais par une coupe silencieuse.
 */
function gwseq_etalon_text($pdf, $x, $y, $w, $text, $size = 10, $style = '', $draw = true, $rgb = array(45, 49, 47), $font = 'helvetica') {
  $pdf->SetFont($font, $style, $size);
  $h = $pdf->getStringHeight($w, (string) $text);
  if ($draw) {
    $pdf->SetTextColor($rgb[0], $rgb[1], $rgb[2]);
    $pdf->SetXY($x, $y);
    $pdf->MultiCell($w, $h, (string) $text, 0, 'L', false, 1);
  }
  return $h;
}

function gwseq_etalon_section($pdf, $x, $y, $w, $title, $rgb, $draw) {
  $h = gwseq_etalon_text($pdf, $x, $y, $w, $title, 10.5, 'B', $draw, array(35, 45, 40), 'times');
  if ($draw) {
    $pdf->SetDrawColor($rgb[0], $rgb[1], $rgb[2]);
    $pdf->SetLineWidth(0.2);
    $pdf->Line($x, $y + $h + 0.6, $x + $w, $y + $h + 0.6);
  }
  return $h + 2;
}

function gwseq_etalon_footer($pdf, $data, $draw = true) {
  $s = $data['structure'];
  $rgb = gws_core_pdf_hex_to_rgb($s['primary_color']);
  $ink = gws_core_pdf_hex_to_rgb($s['primary_color_contrast']);
  $url = (string) ($data['public_url'] ?? '');
  $has_qr = filter_var($url, FILTER_VALIDATE_URL) && in_array(strtolower(parse_url($url, PHP_URL_SCHEME) ?? ''), array('http', 'https'), true);
  $w = $pdf->getPageWidth() - 28 - ($has_qr ? 25 : 0);
  $coords = implode('  ·  ', array_filter(array($s['phone_display'] ?? '', $s['public_email'] ?? '', $s['website_url'] ?? '')));
  $name_h = gwseq_etalon_text($pdf, 14, 0, $w, $s['name'], 9, 'B', false);
  $coords_h = $coords === '' ? 0 : gwseq_etalon_text($pdf, 14, 0, $w, $coords, 8, '', false);
  $h = max($has_qr ? 22 : 15, $name_h + $coords_h + 6);
  if (!$draw) return $h;
  $y = $pdf->getPageHeight() - $h;
  $pdf->SetFillColor($rgb[0], $rgb[1], $rgb[2]);
  $pdf->Rect(0, $y, $pdf->getPageWidth(), $h, 'F');
  gwseq_etalon_text($pdf, 14, $y + 3, $w, $s['name'], 9, 'B', true, $ink);
  if ($coords !== '') gwseq_etalon_text($pdf, 14, $y + 3 + $name_h, $w, $coords, 8, '', true, $ink);
  if ($has_qr) {
    $pdf->write2DBarcode($url, 'QRCODE,M', $pdf->getPageWidth() - 33, $y + ($h - 19) / 2, 19, 19,
      array('border' => false, 'padding' => 2, 'fgcolor' => array(0, 0, 0), 'bgcolor' => array(255, 255, 255)), 'N');
  }
  return $h;
}

function gwseq_etalon_header($pdf, $data, $continued = false) {
  $s = $data['structure'];
  $rgb = gws_core_pdf_hex_to_rgb($s['primary_color']);
  $ink = gws_core_pdf_hex_to_rgb($s['primary_color_contrast']);
  $pdf->SetFillColor($rgb[0], $rgb[1], $rgb[2]);
  $pdf->Rect(0, 0, $pdf->getPageWidth(), 20, 'F');
  $path = !empty($s['logo_id']) ? get_attached_file($s['logo_id']) : '';
  $box = $path ? gws_core_pdf_fit_image_box($path, 95, 13) : null;
  if ($box) $pdf->Image($path, 14, (20 - $box['h']) / 2, $box['w'], $box['h']);
  else {
    $h = gwseq_etalon_text($pdf, 14, 0, 132, $s['name'], 13, 'B', false, $ink, 'times');
    if ($h > 17) throw new LengthException('Nom de structure trop long pour le bandeau Étalon.');
    gwseq_etalon_text($pdf, 14, (20 - $h) / 2, 132, $s['name'], 13, 'B', true, $ink, 'times');
  }
  gwseq_etalon_text($pdf, $pdf->getPageWidth() - 58, 7, 44, $continued ? 'FICHE ÉTALON · SUITE' : 'FICHE ÉTALON', 8, '', true, $ink);
}

/**
 * Bloc éditorial "À retenir" (direction artistique — passe graphique demandée) : teinte légère
 * dérivée de la couleur de structure, jamais un pictogramme, un vrai point d'accroche plutôt qu'une
 * section technique identique aux autres. Absent -> 0 (aucun emplacement réservé).
 */
function gwseq_etalon_callout($pdf, $x, $y, $w, $lines, $rgb, $draw) {
  if (!$lines) return 0;
  // Passe graphique V4 : teinte à peine perceptible, aucun angle arrondi (jamais l'air d'une carte
  // d'interface), padding resserré — un point d'accroche éditorial, pas un encart.
  $tint = gws_core_pdf_lighten_color(sprintf('#%02x%02x%02x', $rgb[0], $rgb[1], $rgb[2]), 0.94);
  $pad = 2.6;
  $tw = $w - (2 * $pad);
  $label_h = gwseq_etalon_text($pdf, 0, 0, $tw, 'À RETENIR', 7, 'B', false);
  $body = implode("\n", $lines);
  $body_h = gwseq_etalon_text($pdf, 0, 0, $tw, $body, 9.5, 'I', false, array(40, 42, 38), 'times');
  $box_h = (2 * $pad) + $label_h + 1 + $body_h;
  if ($draw) {
    $pdf->SetFillColor($tint[0], $tint[1], $tint[2]);
    $pdf->Rect($x, $y, $w, $box_h, 'F');
    gwseq_etalon_text($pdf, $x + $pad, $y + $pad, $tw, 'À RETENIR', 7, 'B', true, $rgb);
    gwseq_etalon_text($pdf, $x + $pad, $y + $pad + $label_h + 1, $tw, $body, 9.5, 'I', true, array(40, 42, 38), 'times');
  }
  return $box_h;
}

/**
 * $extra_photo_h (passe graphique, cas pauvre) : supplément ajouté au SEUL minimum de la grande
 * photo, pour qu'une fiche à peu de contenu ne laisse jamais deviner "des blocs manquants" — le
 * blanc restant en bas de page reste volontaire (agrandissement de la photo dominante, respiration
 * accrue), jamais une donnée inventée pour combler.
 */
/**
 * Colonne identité du hero (nom, sous-ligne, naisseur, indices, qualités, "à retenir") —
 * extraite pour être rejouée avec un espacement interne étiré de $extra_gap (correctif V4,
 * point 3 : équilibre visuel avec la colonne photo), jamais par une donnée ajoutée. Retourne la
 * hauteur consommée et le nombre d'intervalles réellement utilisés (dépend des champs présents).
 */
function gwseq_etalon_hero_identity($pdf, $data, $ix, $y, $iw, $rgb, $compact, $extra_gap, $draw) {
  $iy = $y;
  $gaps = 0;
  $iy += gwseq_etalon_text($pdf, $ix, $iy, $iw, mb_strtoupper($data['name']), $compact ? 22 : 24, 'B', $draw, array(30, 53, 45), 'times') + 2.5 + $extra_gap;
  $gaps++;
  $id = $data['identity'];
  $parts = array($data['sexe_label'] ?? '', $id['annee_naissance'] ?? '', $data['race_label'] ?? '', $data['robe_label'] ?? '');
  if (($id['taille_cm'] ?? '') !== '') $parts[] = number_format((float) $id['taille_cm'] / 100, 2, ',', '') . ' m';
  $parts = array_filter($parts, function ($v) { return (string) $v !== ''; });
  if ($parts) { $iy += gwseq_etalon_text($pdf, $ix, $iy, $iw, implode(' · ', $parts), 9.5, '', $draw) + 1.5 + $extra_gap; $gaps++; }
  // Naisseur discret (passe graphique V4) : plus petit, gris atténué — jamais au même niveau que
  // l'identité elle-même.
  if (!empty($id['eleveur'])) { $iy += gwseq_etalon_text($pdf, $ix, $iy, $iw, 'Naisseur : ' . $id['eleveur'], 8.3, '', $draw, array(128, 124, 116)) + 2.5 + $extra_gap; $gaps++; }

  // Indices et qualités : plus de titres "PERFORMANCES"/"QUALITÉS" ni de filets techniques (passe
  // graphique) — hiérarchie typographique seule : indices en gras dans la couleur de structure,
  // qualités juste dessous en italique discrète. Chaque ligne absente -> rien, aucune hauteur
  // réservée.
  $indices = array();
  foreach ((array) ($data['sport_indices'] ?? array()) as $key => $item) {
    if (($item['valeur'] ?? '') !== '') $indices[] = strtoupper($key) . "\u{00A0}" . $item['valeur'];
  }
  foreach ((array) ($data['genetic_indices'] ?? array()) as $key => $item) {
    if (($item['valeur'] ?? '') !== '') $indices[] = strtoupper($key) . "\u{00A0}" . gwseq_cheval_genetic_indice_label($item['valeur'], '');
  }
  if ($indices) { $iy += gwseq_etalon_text($pdf, $ix, $iy, $iw, implode('   ·   ', $indices), $compact ? 10.5 : 11, 'B', $draw, $rgb) + 2.2 + $extra_gap; $gaps++; }
  $qualites = implode('   ·   ', array_slice(array_filter((array) ($data['qualites'] ?? array()), 'strlen'), 0, 5));
  if ($qualites !== '') { $iy += gwseq_etalon_text($pdf, $ix, $iy, $iw, $qualites, 9, 'I', $draw, array(120, 124, 114)) + 1.5 + $extra_gap; $gaps++; }

  $faits = array_slice(array_filter((array) ($data['faits_marquants'] ?? array()), 'strlen'), 0, 3);
  if ($faits) { $iy += gwseq_etalon_callout($pdf, $ix, $iy + 2.5 + $extra_gap, $iw, $faits, $rgb, $draw) + 2 + $extra_gap; $gaps++; }

  return array('h' => $iy - $y, 'gaps' => $gaps);
}

function gwseq_etalon_hero($pdf, $data, $x, $y, $w, $rgb, $compact, $draw, $extra_photo_h = 0) {
  $paths = array_values(array_unique(array_filter(array_merge(array($data['photo_path'] ?? ''), (array) ($data['gallery_paths'] ?? array())), function ($p) {
    return is_string($p) && $p !== '' && is_readable($p) && @getimagesize($p);
  })));
  $photo = $paths ? array_shift($paths) : '';
  $photos = array_slice($paths, 0, 3);
  $pw = $photo ? $w * 0.52 : 0;
  $ix = $photo ? $x + $pw + 8 : $x;
  $iw = $w - ($ix - $x);

  $measure = gwseq_etalon_hero_identity($pdf, $data, $ix, $y, $iw, $rgb, $compact, 0, false);
  $identity_h = $measure['h'];

  // Galerie (correctif V6, point 1 seul modifié) : à partir de 2 photos secondaires, des
  // miniatures côte à côte occupent ENSEMBLE toute la largeur de la grande photo (ratio ~4:3 dérivé
  // de cette largeur). Avec UNE seule photo secondaire : jamais étirée pleine largeur ni centrée —
  // une vignette de taille raisonnable (~45-50 % de la largeur de la photo principale, ratio ~4:3),
  // alignée à GAUCHE ; le blanc laissé à droite est volontaire (effet éditorial, pas un trou).
  // L'image est toujours rendue en `cover` (ratio conservé, recadrage centré, jamais de
  // déformation).
  $thumb_gap = 3;
  $thumb_h = 0;
  $thumb_w = 0;
  $n = count($photos);
  if ($n === 1) {
    $thumb_w = $pw * 0.475;
    // Ratio légèrement plus large en mode compact (contenu déjà dense) pour rester dans le budget
    // d'une page — "ratio 4:3 environ" reste respecté en mode aéré, cas normal de cette vignette.
    $thumb_h = $thumb_w / ($compact ? 1.7 : 1.333);
  } elseif ($n > 1) {
    $thumb_w = ($pw - (($n - 1) * $thumb_gap)) / $n;
    $thumb_h = $thumb_w / 1.34;
  }
  $thumb_strip = $photos ? ($thumb_gap + $thumb_h) : 0;
  $min_main_photo_h = $photo ? (($compact ? 75 : 86) + $extra_photo_h) : 0;
  $photo_col_h = $photo ? max($min_main_photo_h + $thumb_strip, $identity_h) : 0;
  $main_photo_h = $photo_col_h - $thumb_strip;
  $hero_h = max($identity_h, $photo_col_h);

  // Correctif V4 (point 3) : équilibre visuel entre colonne photo et colonne identité dans le cas
  // riche — jamais appliqué quand $extra_photo_h agrandit déjà la photo pour le cas pauvre (celui-ci
  // reste inchangé, déjà validé). Aucune donnée ajoutée : seul l'espacement interne déjà présent
  // s'étire pour occuper la même hauteur que la colonne photo.
  $extra_gap = ($extra_photo_h == 0 && $photo && $photo_col_h > $identity_h && $measure['gaps'] > 0)
    ? ($photo_col_h - $identity_h) / $measure['gaps']
    : 0;

  if ($draw) {
    gwseq_etalon_hero_identity($pdf, $data, $ix, $y, $iw, $rgb, $compact, $extra_gap, true);
  }
  if ($draw && $photo) {
    gwseq_horse_pdf_draw_photo_box($pdf, $x, $y, $pw, $main_photo_h, $photo, false);
    if ($photos) {
      // Une seule vignette : alignée à gauche (jamais centrée, jamais pleine largeur) — voir
      // commentaire ci-dessus. Deux ou trois : côte à côte depuis la gauche, comme avant.
      $tx = $x;
      $ty = $y + $main_photo_h + $thumb_gap;
      foreach ($photos as $path) {
        gwseq_horse_pdf_draw_photo_box($pdf, $tx, $ty, $thumb_w, $thumb_h, $path, true);
        $tx += $thumb_w + $thumb_gap;
      }
    }
  }
  return $hero_h;
}

/** Nœuds mesurés et multilignes ; les branches absentes n'ont aucun emplacement réservé. */
function gwseq_etalon_tree($pdf, $data, $x, $y, $w, $rgb, $compact, $draw) {
  $parents = array();
  foreach (array('father', 'mother') as $side) {
    $raw = $data['pedigree'][$side] ?? null;
    $label = gwseq_horse_pdf_pedigree_node_label($raw);
    if (!$label) continue;
    $children = array();
    foreach (array('father', 'mother') as $key) {
      $child = gwseq_horse_pdf_pedigree_node_label($raw[$key] ?? null);
      if ($child) $children[] = $child;
    }
    $parents[] = array('label' => $label, 'children' => $children);
  }
  if (!$parents) return 0;
  $top = $y;
  $y += gwseq_etalon_section($pdf, $x, $y, $w, 'PEDIGREE', $rgb, $draw);
  // Passe graphique V4 (point 4) : le sujet (déjà nommé dans le hero juste au-dessus) ne consomme
  // plus qu'une colonne minimale, au profit des parents (hiérarchie encore plus nette) et des
  // grands-parents (parfaitement lisibles, jamais le maillon faible du pedigree).
  $widths = array($w * 0.13, $w * 0.34, $w * 0.44);
  $xs = array($x, $x + $w * 0.17, $x + $w * 0.57);
  $node = function ($label, $col, $cy, $paint) use ($pdf, $widths, $xs, $compact) {
    if ($col === 1) $size = $compact ? 12.5 : 13.5;
    elseif ($col === 2) $size = $compact ? 10 : 10.5;
    else $size = $compact ? 9.5 : 10;
    $name = mb_strtoupper($label['name']);
    // Réduction locale modérée, puis retour à la ligne sans compression horizontale.
    $pdf->SetFont('times', 'B', $size);
    if ($pdf->GetStringWidth($name) > $widths[$col]) $size -= 0.5;
    $nh = gwseq_etalon_text($pdf, 0, 0, $widths[$col], $name, $size, 'B', false, array(35, 45, 40), 'times');
    $bh = empty($label['breed']) ? 0 : gwseq_etalon_text($pdf, 0, 0, $widths[$col], $label['breed'], 8.5, '', false);
    if ($paint) {
      gwseq_etalon_text($pdf, $xs[$col], $cy - ($nh + $bh) / 2, $widths[$col], $name, $size, 'B', true, array(35, 45, 40), 'times');
      if ($bh) gwseq_etalon_text($pdf, $xs[$col], $cy - ($nh + $bh) / 2 + $nh, $widths[$col], $label['breed'], 8.5, '', true, array(90, 95, 90));
    }
    return $nh + $bh;
  };
  $centers = array();
  $cursor = $y;
  foreach ($parents as $parent) {
    $ph = $node($parent['label'], 1, 0, false);
    $heights = array();
    foreach ($parent['children'] as $child) $heights[] = max($compact ? 15 : 18, $node($child, 2, 0, false) + ($compact ? 4 : 4.5));
    $group_h = max($ph + ($compact ? 6 : 7), array_sum($heights), $compact ? 32 : 41);
    $cy = $cursor + $group_h / 2;
    $centers[] = $cy;
    $node($parent['label'], 1, $cy, $draw);
    $child_y = $cursor + ($group_h - array_sum($heights)) / 2;
    foreach ($parent['children'] as $i => $child) {
      $gy = $child_y + $heights[$i] / 2;
      if ($draw) {
        $pdf->SetDrawColor(178, 187, 181);
        $pdf->SetLineWidth(0.18);
        $bx = $xs[2] - $w * 0.025;
        $pdf->Line($xs[1] + $widths[1], $cy, $bx, $cy);
        $pdf->Line($bx, $cy, $bx, $gy);
        $pdf->Line($bx, $gy, $xs[2] - 1, $gy);
      }
      $node($child, 2, $gy, $draw);
      $child_y += $heights[$i];
    }
    $cursor += $group_h;
  }
  $subject = array('name' => $data['name'], 'breed' => '');
  $sh = $node($subject, 0, 0, false);
  $cy = ($centers[0] + end($centers)) / 2;
  if ($draw) {
    $pdf->SetDrawColor(160, 172, 166);
    $pdf->SetLineWidth(0.2);
    $bx = $xs[1] - $w * 0.025;
    $pdf->Line($xs[0] + $widths[0], $cy, $bx, $cy);
    foreach ($centers as $py) {
      $pdf->Line($bx, $cy, $bx, $py);
      $pdf->Line($bx, $py, $xs[1] - 1, $py);
    }
    $node($subject, 0, $cy, true);
  }
  // Un nom sujet exceptionnel ne doit pas empiéter sur le bloc suivant.
  return max($cursor, $cy + $sh / 2 + 2) - $top;
}

function gwseq_render_horse_pdf_template_etalon($pdf, $data) {
  // Cette version vendorisée ajoute son crédit à Close(), même setPrintFooter(false).
  // Pas d'API publique : désactivation sur cette instance seulement, sans modifier Core/vendor.
  $disable_credit = Closure::bind(function () { $this->tcpdflink = false; }, $pdf, 'TCPDF');
  $disable_credit();
  $old_padding = $pdf->getCellPaddings();
  $old_ratio = $pdf->getCellHeightRatio();
  $pdf->setCellPaddings(0, 0, 0, 0);
  $pdf->setCellHeightRatio(1.2);
  try {
    $rgb = gws_core_pdf_hex_to_rgb($data['structure']['primary_color']);
    $ink = gws_core_pdf_hex_to_rgb($data['structure']['primary_color_contrast']);
    $x = 14;
    $w = $pdf->getPageWidth() - 28;
    $limit = $pdf->getPageHeight() - gwseq_etalon_footer($pdf, $data, false) - 4;
    $body_size = 10;
    $gap = 4;
    $editorial = array();
    foreach (array('presentation' => 'PRÉSENTATION', 'conseils_croisement' => 'CONSEIL DE CROISEMENT') as $key => $title) {
      $body = trim((string) ($data['editorial'][$key] ?? ''));
      if ($body !== '') $editorial[] = array($title, $body);
    }
    // Reproduction (passe graphique, point 6) : composition typographique plutôt qu'une liste
    // "Libellé : valeur" — osteo (étoiles vectorielles) et WFFS partagent une ligne, les
    // approbations forment une seconde ligne "APPROUVÉ ...". Un champ absent -> son segment
    // disparaît entièrement (jamais un intitulé sans valeur).
    $has_osteo = (int) ($data['statut_osteo'] ?? 0) > 0;
    $wffs_val = trim((string) ($data['wffs'] ?? ''));
    $repro_line1 = array();
    if ($has_osteo) $repro_line1[] = 'OSTÉO-ARTICULAIRE'; // texte de mesure uniquement : les vraies étoiles sont dessinées au tracé
    if ($wffs_val !== '') $repro_line1[] = 'WFFS ' . $wffs_val;
    $repro_line2 = !empty($data['studbooks_labels']) ? 'APPROUVÉ ' . implode(' · ', $data['studbooks_labels']) : '';
    $repro = array_values(array_filter(array(implode('   ·   ', $repro_line1), $repro_line2), 'strlen'));
    $conditions = trim((string) ($data['editorial']['conditions_vente'] ?? ''));
    $ids = array();
    foreach (array('sire' => 'SIRE', 'ueln' => 'UELN', 'proprietaire' => 'Propriétaire') as $key => $label) {
      if (($data['identity'][$key] ?? '') !== '') $ids[] = $label . ' : ' . $data['identity'][$key];
    }
    $block_h = function ($title, $body, $width, $size) use ($pdf, $rgb) {
      return gwseq_etalon_section($pdf, 0, 0, $width, $title, $rgb, false) + gwseq_etalon_text($pdf, 0, 0, $width, $body, $size, '', false);
    };
    // Deux budgets, calculés AVANT tout dessin. La police métier reste >= 9.5 pt.
    foreach (array(false, true) as $compact) {
      $body_size = $compact ? 9.5 : 10;
      $gap = $compact ? 3 : 4;
      $hero_h = gwseq_etalon_hero($pdf, $data, $x, 24, $w, $rgb, $compact, false);
      $tree_h = gwseq_etalon_tree($pdf, $data, $x, 0, $w, $rgb, $compact, false);
      $ew = count($editorial) === 2 ? ($w - 7) / 2 : $w;
      $eh = 0;
      foreach ($editorial as $block) $eh = max($eh, $block_h($block[0], $block[1], $ew, $body_size));
      $rh = $repro ? $block_h('REPRODUCTION', implode("\n", $repro), $w, $body_size) : 0;
      $ch = $conditions !== '' ? $block_h('CONDITIONS DE MONTE', $conditions, $w - 8, $body_size) + 8 : 0;
      $ih = $ids ? $block_h('IDENTIFICATION', implode(' · ', $ids), $w, 8) : 0;
      $total = 24 + $hero_h + $gap;
      foreach (array($tree_h, $eh, $rh, $ch, $ih) as $height) if ($height > 0) $total += $height + $gap;
      if ($total <= $limit) break;
    }
    // Cas pauvre (passe graphique V4, point 2) : du blanc en trop en mode aéré ne doit jamais
    // donner l'impression que des blocs manquent — la photo/le hero dominants sont nettement
    // agrandis et les respirations augmentées pour absorber ce blanc volontairement (jamais les
    // mêmes proportions que le cas riche), jamais une donnée fabriquée. Sans effet si le mode
    // compact a dû être choisi (contenu déjà dense) ou sans photo valide (gwseq_etalon_hero()
    // ignore alors $extra_photo_h).
    $extra_photo_h = 0;
    if (!$compact) {
      $slack = $limit - $total;
      if ($slack > 8) {
        $extra_photo_h = min($slack - 4, 55);
        $boosted_hero_h = gwseq_etalon_hero($pdf, $data, $x, 24, $w, $rgb, $compact, false, $extra_photo_h);
        if ($boosted_hero_h > $hero_h) {
          $hero_h = $boosted_hero_h;
          $gap += 1.5;
        } else {
          $extra_photo_h = 0;
        }
      }
    }
    gwseq_etalon_header($pdf, $data);
    $y = 24;
    $new_page = function () use ($pdf, $data, &$y) {
      gwseq_etalon_footer($pdf, $data);
      $pdf->AddPage();
      gwseq_etalon_header($pdf, $data, true);
      $y = 24;
    };
    $room = function ($h) use (&$y, $limit, $new_page) {
      if ($h > $limit - 24) throw new LengthException('Bloc Étalon trop haut : vérifiez les noms ou les données de pedigree.');
      if ($y + $h > $limit) $new_page();
    };
    $room($hero_h);
    $y += gwseq_etalon_hero($pdf, $data, $x, $y, $w, $rgb, $compact, true, $extra_photo_h) + $gap;
    if ($tree_h > 0) {
      $room($tree_h);
      $y += gwseq_etalon_tree($pdf, $data, $x, $y, $w, $rgb, $compact, true) + $gap;
    }
    // Flux de secours : découpe mesurée, conserve chaque caractère, titre « suite ».
    $flow = function ($title, $body, $colored = false, $size = null) use ($pdf, $x, $w, $rgb, $ink, $limit, $new_page, &$y, $gap, $body_size, $block_h) {
      $size = $size ?? $body_size;
      $pad = $colored ? 4 : 0;
      $tw = $w - 2 * $pad;
      $full_h = $block_h($title, $body, $tw, $size) + 2 * $pad;
      if ($y + $full_h > $limit && $full_h <= $limit - 24) $new_page();
      $continued = false;
      while ($body !== '') {
        $heading = $title . ($continued ? ' · SUITE' : '');
        $hh = gwseq_etalon_section($pdf, 0, 0, $tw, $heading, $rgb, false);
        $available = $limit - $y - $hh - 2 * $pad;
        $line_h = gwseq_etalon_text($pdf, 0, 0, $tw, 'Ag', $size, '', false);
        if ($available < 2 * $line_h) { $new_page(); continue; }
        $length = mb_strlen($body);
        $low = 0; $high = $length;
        while ($low < $high) {
          $mid = (int) ceil(($low + $high) / 2);
          $h = gwseq_etalon_text($pdf, 0, 0, $tw, mb_substr($body, 0, $mid), $size, '', false);
          if ($h <= $available) $low = $mid; else $high = $mid - 1;
        }
        if ($low < 1) throw new LengthException('Texte Étalon impossible à composer.');
        if ($low < $length) {
          $prefix = mb_substr($body, 0, $low);
          $paragraph = mb_strrpos($prefix, "\n\n");
          if ($paragraph !== false && $paragraph > $low * 0.65) {
            $low = $paragraph + 2;
          } else {
            $space = mb_strrpos($prefix, ' ');
            if ($space !== false && $space > 0) $low = $space + 1;
          }
        }
        $chunk = mb_substr($body, 0, $low);
        $h = gwseq_etalon_text($pdf, 0, 0, $tw, $chunk, $size, '', false);
        if ($colored) {
          $pdf->SetFillColor($rgb[0], $rgb[1], $rgb[2]);
          $pdf->Rect($x, $y, $w, $hh + $h + 2 * $pad, 'F');
          gwseq_etalon_text($pdf, $x + $pad, $y + $pad, $tw, $heading, 11, 'B', true, $ink, 'times');
        } else gwseq_etalon_section($pdf, $x, $y, $w, $heading, $rgb, true);
        gwseq_etalon_text($pdf, $x + $pad, $y + $pad + $hh, $tw, $chunk, $size, '', true, $colored ? $ink : array(45, 49, 47));
        $y += $hh + $h + 2 * $pad + $gap;
        $body = mb_substr($body, $low);
        if ($body !== '') { $new_page(); $continued = true; }
      }
    };
    if ($editorial && $y + $eh <= $limit) {
      foreach ($editorial as $i => $block) {
        $bx = $x + $i * ($ew + 7);
        $hh = gwseq_etalon_section($pdf, $bx, $y, $ew, $block[0], $rgb, true);
        gwseq_etalon_text($pdf, $bx, $y + $hh, $ew, $block[1], $body_size, '', true);
      }
      $y += $eh + $gap;
    } else foreach ($editorial as $block) $flow($block[0], $block[1]);
    if ($repro) {
      $room($rh);
      // Passe graphique V4 (point 6) : intitulé discret sans filet pleine largeur — moins
      // "section technique", plus proche du traitement éditorial de "À RETENIR".
      $y += gwseq_etalon_text($pdf, $x, $y, $w, 'REPRODUCTION', 8, 'B', true, array(115, 120, 110)) + 1.8;
      if ($repro_line1) {
        $line_h = gwseq_etalon_text($pdf, 0, 0, $w, 'Ag', $body_size, 'B', false);
        $cx = $x;
        if ($has_osteo) {
          $cx += gwseq_horse_pdf_draw_star_rating($pdf, $cx, $y + ($line_h - 3.4) / 2, $data['statut_osteo'], $rgb, 3.4) + 3;
          $pdf->SetFont('helvetica', 'B', $body_size);
          $pdf->SetTextColor($rgb[0], $rgb[1], $rgb[2]);
          $pdf->SetXY($cx, $y);
          $pdf->Cell($pdf->GetStringWidth('OSTÉO-ARTICULAIRE') + 1, $line_h, 'OSTÉO-ARTICULAIRE', 0, 0, 'L');
          $cx += $pdf->GetStringWidth('OSTÉO-ARTICULAIRE') + 1;
        }
        if ($wffs_val !== '') {
          $wffs_text = ($has_osteo ? '   ·   ' : '') . 'WFFS ' . $wffs_val;
          gwseq_etalon_text($pdf, $cx, $y, $w - ($cx - $x), $wffs_text, $body_size, $has_osteo ? '' : 'B', true, $has_osteo ? array(45, 49, 47) : $rgb);
        }
        $y += $line_h + 1.8;
      }
      if ($repro_line2 !== '') $y += gwseq_etalon_text($pdf, $x, $y, $w, $repro_line2, $body_size, '', true);
      $y += $gap;
    }
    if ($conditions !== '') $flow('CONDITIONS DE MONTE', $conditions, true);
    if ($ids) $flow('IDENTIFICATION', implode(' · ', $ids), false, 8);
    gwseq_etalon_footer($pdf, $data);
    return true;
  } finally {
    $pdf->setCellPaddings($old_padding['L'], $old_padding['T'], $old_padding['R'], $old_padding['B']);
    $pdf->setCellHeightRatio($old_ratio);
  }
}

/**
 * Composition RÉSERVÉE à Poulinière (même langage graphique et mêmes principes de robustesse que
 * le master Étalon désormais figé — voir CR du lot : la Production y prend la place métier occupée
 * par Reproduction + Conditions de monte sur l'Étalon). Fonctions dupliquées à dessein, jamais
 * partagées avec gwseq_etalon_* : le master Étalon ne doit plus jamais être modifié par ce travail.
 */
function gwseq_pouliniere_text($pdf, $x, $y, $w, $text, $size = 10, $style = '', $draw = true, $rgb = array(45, 49, 47), $font = 'helvetica') {
  $pdf->SetFont($font, $style, $size);
  $h = $pdf->getStringHeight($w, (string) $text);
  if ($draw) {
    $pdf->SetTextColor($rgb[0], $rgb[1], $rgb[2]);
    $pdf->SetXY($x, $y);
    $pdf->MultiCell($w, $h, (string) $text, 0, 'L', false, 1);
  }
  return $h;
}

function gwseq_pouliniere_section($pdf, $x, $y, $w, $title, $rgb, $draw) {
  $h = gwseq_pouliniere_text($pdf, $x, $y, $w, $title, 10.5, 'B', $draw, array(35, 45, 40), 'times');
  if ($draw) {
    $pdf->SetDrawColor($rgb[0], $rgb[1], $rgb[2]);
    $pdf->SetLineWidth(0.2);
    $pdf->Line($x, $y + $h + 0.6, $x + $w, $y + $h + 0.6);
  }
  return $h + 2;
}

function gwseq_pouliniere_footer($pdf, $data, $draw = true) {
  $s = $data['structure'];
  $rgb = gws_core_pdf_hex_to_rgb($s['primary_color']);
  $ink = gws_core_pdf_hex_to_rgb($s['primary_color_contrast']);
  $url = (string) ($data['public_url'] ?? '');
  $has_qr = filter_var($url, FILTER_VALIDATE_URL) && in_array(strtolower(parse_url($url, PHP_URL_SCHEME) ?? ''), array('http', 'https'), true);
  $w = $pdf->getPageWidth() - 28 - ($has_qr ? 25 : 0);
  $coords = implode('  ·  ', array_filter(array($s['phone_display'] ?? '', $s['public_email'] ?? '', $s['website_url'] ?? '')));
  $name_h = gwseq_pouliniere_text($pdf, 14, 0, $w, $s['name'], 9, 'B', false);
  $coords_h = $coords === '' ? 0 : gwseq_pouliniere_text($pdf, 14, 0, $w, $coords, 8, '', false);
  $h = max($has_qr ? 22 : 15, $name_h + $coords_h + 6);
  if (!$draw) return $h;
  $y = $pdf->getPageHeight() - $h;
  $pdf->SetFillColor($rgb[0], $rgb[1], $rgb[2]);
  $pdf->Rect(0, $y, $pdf->getPageWidth(), $h, 'F');
  gwseq_pouliniere_text($pdf, 14, $y + 3, $w, $s['name'], 9, 'B', true, $ink);
  if ($coords !== '') gwseq_pouliniere_text($pdf, 14, $y + 3 + $name_h, $w, $coords, 8, '', true, $ink);
  if ($has_qr) {
    $pdf->write2DBarcode($url, 'QRCODE,M', $pdf->getPageWidth() - 33, $y + ($h - 19) / 2, 19, 19,
      array('border' => false, 'padding' => 2, 'fgcolor' => array(0, 0, 0), 'bgcolor' => array(255, 255, 255)), 'N');
  }
  return $h;
}

function gwseq_pouliniere_header($pdf, $data, $continued = false) {
  $s = $data['structure'];
  $rgb = gws_core_pdf_hex_to_rgb($s['primary_color']);
  $ink = gws_core_pdf_hex_to_rgb($s['primary_color_contrast']);
  $pdf->SetFillColor($rgb[0], $rgb[1], $rgb[2]);
  $pdf->Rect(0, 0, $pdf->getPageWidth(), 20, 'F');
  $path = !empty($s['logo_id']) ? get_attached_file($s['logo_id']) : '';
  $box = $path ? gws_core_pdf_fit_image_box($path, 95, 13) : null;
  if ($box) $pdf->Image($path, 14, (20 - $box['h']) / 2, $box['w'], $box['h']);
  else {
    $h = gwseq_pouliniere_text($pdf, 14, 0, 132, $s['name'], 13, 'B', false, $ink, 'times');
    if ($h > 17) throw new LengthException('Nom de structure trop long pour le bandeau Poulinière.');
    gwseq_pouliniere_text($pdf, 14, (20 - $h) / 2, 132, $s['name'], 13, 'B', true, $ink, 'times');
  }
  gwseq_pouliniere_text($pdf, $pdf->getPageWidth() - 58, 7, 44, $continued ? 'FICHE POULINIÈRE · SUITE' : 'FICHE POULINIÈRE', 8, '', true, $ink);
}

function gwseq_pouliniere_callout($pdf, $x, $y, $w, $lines, $rgb, $draw) {
  if (!$lines) return 0;
  $tint = gws_core_pdf_lighten_color(sprintf('#%02x%02x%02x', $rgb[0], $rgb[1], $rgb[2]), 0.94);
  $pad = 2.6;
  $tw = $w - (2 * $pad);
  $label_h = gwseq_pouliniere_text($pdf, 0, 0, $tw, 'À RETENIR', 7, 'B', false);
  $body = implode("\n", $lines);
  $body_h = gwseq_pouliniere_text($pdf, 0, 0, $tw, $body, 9.5, 'I', false, array(40, 42, 38), 'times');
  $box_h = (2 * $pad) + $label_h + 1 + $body_h;
  if ($draw) {
    $pdf->SetFillColor($tint[0], $tint[1], $tint[2]);
    $pdf->Rect($x, $y, $w, $box_h, 'F');
    gwseq_pouliniere_text($pdf, $x + $pad, $y + $pad, $tw, 'À RETENIR', 7, 'B', true, $rgb);
    gwseq_pouliniere_text($pdf, $x + $pad, $y + $pad + $label_h + 1, $tw, $body, 9.5, 'I', true, array(40, 42, 38), 'times');
  }
  return $box_h;
}

/**
 * Colonne identité du hero — même mécanique d'étirement de l'espacement interne que le master
 * Étalon ($extra_gap/$gaps, jamais une donnée ajoutée pour équilibrer la colonne photo). Deux
 * différences métier voulues par le client : jamais le naisseur ; une zone commerciale (statut +
 * prix) uniquement si RÉELLEMENT renseignée, jamais un libellé inventé, qui disparaît sans laisser
 * de trou si aucune donnée commerciale n'existe.
 */
function gwseq_pouliniere_hero_identity($pdf, $data, $ix, $y, $iw, $rgb, $compact, $extra_gap, $draw) {
  $iy = $y;
  $gaps = 0;
  $iy += gwseq_pouliniere_text($pdf, $ix, $iy, $iw, mb_strtoupper($data['name']), $compact ? 22 : 24, 'B', $draw, array(30, 53, 45), 'times') + 2.5 + $extra_gap;
  $gaps++;
  $id = $data['identity'];
  $parts = array($data['sexe_label'] ?? '', $id['annee_naissance'] ?? '', $data['race_label'] ?? '', $data['robe_label'] ?? '');
  if (($id['taille_cm'] ?? '') !== '') $parts[] = number_format((float) $id['taille_cm'] / 100, 2, ',', '') . ' m';
  $parts = array_filter($parts, function ($v) { return (string) $v !== ''; });
  if ($parts) { $iy += gwseq_pouliniere_text($pdf, $ix, $iy, $iw, implode(' · ', $parts), 9.5, '', $draw) + 1.5 + $extra_gap; $gaps++; }

  // Zone commerciale (arbitrage client) : jamais le naisseur sur la fiche Poulinière ; statut
  // commercial affiché seulement si != "not_offered" et son libellé existe ; prix affiché seulement
  // s'il est renseigné ; aucun des deux -> la zone entière disparaît, $gaps n'est pas incrémenté et
  // le hero se rééquilibre naturellement sur les autres intervalles.
  $statut = $data['commercial']['statut_commercial'] ?? 'not_offered';
  $statut_label = $statut !== 'not_offered' ? (gwseq_cheval_statut_commercial_options()[$statut] ?? '') : '';
  $price = (string) ($data['price_summary'] ?? '');
  if ($statut_label !== '' || $price !== '') {
    if ($draw) {
      $cx = $ix;
      if ($statut_label !== '') $cx += gwseq_horse_pdf_draw_chip($pdf, $cx, $iy, mb_strtoupper($statut_label), $rgb, array(255, 255, 255), 8.5, true) + 4;
      if ($price !== '') {
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->SetTextColor($rgb[0], $rgb[1], $rgb[2]);
        $pdf->SetXY($cx, $iy - ($statut_label !== '' ? 0.6 : 0));
        $pdf->Cell($iw - ($cx - $ix), 7, $price, 0, 0, 'L');
      }
    }
    $iy += 8.5 + $extra_gap;
    $gaps++;
  }

  $indices = array();
  foreach ((array) ($data['sport_indices'] ?? array()) as $key => $item) {
    if (($item['valeur'] ?? '') !== '') $indices[] = strtoupper($key) . "\u{00A0}" . $item['valeur'];
  }
  foreach ((array) ($data['genetic_indices'] ?? array()) as $key => $item) {
    if (($item['valeur'] ?? '') !== '') $indices[] = strtoupper($key) . "\u{00A0}" . gwseq_cheval_genetic_indice_label($item['valeur'], '');
  }
  if ($indices) { $iy += gwseq_pouliniere_text($pdf, $ix, $iy, $iw, implode('   ·   ', $indices), $compact ? 10.5 : 11, 'B', $draw, $rgb) + 2.2 + $extra_gap; $gaps++; }
  $qualites = implode('   ·   ', array_slice(array_filter((array) ($data['qualites'] ?? array()), 'strlen'), 0, 5));
  if ($qualites !== '') { $iy += gwseq_pouliniere_text($pdf, $ix, $iy, $iw, $qualites, 9, 'I', $draw, array(120, 124, 114)) + 1.5 + $extra_gap; $gaps++; }

  $faits = array_slice(array_filter((array) ($data['faits_marquants'] ?? array()), 'strlen'), 0, 3);
  if ($faits) { $iy += gwseq_pouliniere_callout($pdf, $ix, $iy + 2.5 + $extra_gap, $iw, $faits, $rgb, $draw) + 2 + $extra_gap; $gaps++; }

  return array('h' => $iy - $y, 'gaps' => $gaps);
}

function gwseq_pouliniere_hero($pdf, $data, $x, $y, $w, $rgb, $compact, $draw, $extra_photo_h = 0) {
  $paths = array_values(array_unique(array_filter(array_merge(array($data['photo_path'] ?? ''), (array) ($data['gallery_paths'] ?? array())), function ($p) {
    return is_string($p) && $p !== '' && is_readable($p) && @getimagesize($p);
  })));
  $photo = $paths ? array_shift($paths) : '';
  $photos = array_slice($paths, 0, 3);
  $pw = $photo ? $w * 0.52 : 0;
  $ix = $photo ? $x + $pw + 8 : $x;
  $iw = $w - ($ix - $x);

  $measure = gwseq_pouliniere_hero_identity($pdf, $data, $ix, $y, $iw, $rgb, $compact, 0, false);
  $identity_h = $measure['h'];

  // Galerie : mêmes règles que le master Étalon (0/1/2/3 photo(s) secondaire(s)) — voir ses
  // commentaires pour la justification détaillée, non répétée ici (duplication volontaire, jamais
  // partagée avec gwseq_etalon_*).
  $thumb_gap = 3;
  $thumb_h = 0;
  $thumb_w = 0;
  $n = count($photos);
  if ($n === 1) {
    $thumb_w = $pw * 0.475;
    $thumb_h = $thumb_w / ($compact ? 1.7 : 1.333);
  } elseif ($n > 1) {
    $thumb_w = ($pw - (($n - 1) * $thumb_gap)) / $n;
    $thumb_h = $thumb_w / 1.34;
  }
  $thumb_strip = $photos ? ($thumb_gap + $thumb_h) : 0;
  $min_main_photo_h = $photo ? (($compact ? 75 : 86) + $extra_photo_h) : 0;
  $photo_col_h = $photo ? max($min_main_photo_h + $thumb_strip, $identity_h) : 0;
  $main_photo_h = $photo_col_h - $thumb_strip;
  $hero_h = max($identity_h, $photo_col_h);

  $extra_gap = ($extra_photo_h == 0 && $photo && $photo_col_h > $identity_h && $measure['gaps'] > 0)
    ? ($photo_col_h - $identity_h) / $measure['gaps']
    : 0;

  if ($draw) {
    gwseq_pouliniere_hero_identity($pdf, $data, $ix, $y, $iw, $rgb, $compact, $extra_gap, true);
  }
  if ($draw && $photo) {
    gwseq_horse_pdf_draw_photo_box($pdf, $x, $y, $pw, $main_photo_h, $photo, false);
    if ($photos) {
      $tx = $x;
      $ty = $y + $main_photo_h + $thumb_gap;
      foreach ($photos as $path) {
        gwseq_horse_pdf_draw_photo_box($pdf, $tx, $ty, $thumb_w, $thumb_h, $path, true);
        $tx += $thumb_w + $thumb_gap;
      }
    }
  }
  return $hero_h;
}

/** Duplication volontaire de gwseq_etalon_tree() — même arbre 3 générations, jamais partagée. */
function gwseq_pouliniere_tree($pdf, $data, $x, $y, $w, $rgb, $compact, $draw) {
  $parents = array();
  foreach (array('father', 'mother') as $side) {
    $raw = $data['pedigree'][$side] ?? null;
    $label = gwseq_horse_pdf_pedigree_node_label($raw);
    if (!$label) continue;
    $children = array();
    foreach (array('father', 'mother') as $key) {
      $child = gwseq_horse_pdf_pedigree_node_label($raw[$key] ?? null);
      if ($child) $children[] = $child;
    }
    $parents[] = array('label' => $label, 'children' => $children);
  }
  if (!$parents) return 0;
  $top = $y;
  $y += gwseq_pouliniere_section($pdf, $x, $y, $w, 'PEDIGREE', $rgb, $draw);
  $widths = array($w * 0.13, $w * 0.34, $w * 0.44);
  $xs = array($x, $x + $w * 0.17, $x + $w * 0.57);
  $node = function ($label, $col, $cy, $paint) use ($pdf, $widths, $xs, $compact) {
    if ($col === 1) $size = $compact ? 12.5 : 13.5;
    elseif ($col === 2) $size = $compact ? 10 : 10.5;
    else $size = $compact ? 9.5 : 10;
    $name = mb_strtoupper($label['name']);
    $pdf->SetFont('times', 'B', $size);
    if ($pdf->GetStringWidth($name) > $widths[$col]) $size -= 0.5;
    $nh = gwseq_pouliniere_text($pdf, 0, 0, $widths[$col], $name, $size, 'B', false, array(35, 45, 40), 'times');
    $bh = empty($label['breed']) ? 0 : gwseq_pouliniere_text($pdf, 0, 0, $widths[$col], $label['breed'], 8.5, '', false);
    if ($paint) {
      gwseq_pouliniere_text($pdf, $xs[$col], $cy - ($nh + $bh) / 2, $widths[$col], $name, $size, 'B', true, array(35, 45, 40), 'times');
      if ($bh) gwseq_pouliniere_text($pdf, $xs[$col], $cy - ($nh + $bh) / 2 + $nh, $widths[$col], $label['breed'], 8.5, '', true, array(90, 95, 90));
    }
    return $nh + $bh;
  };
  $centers = array();
  $cursor = $y;
  foreach ($parents as $parent) {
    $ph = $node($parent['label'], 1, 0, false);
    $heights = array();
    foreach ($parent['children'] as $child) $heights[] = max($compact ? 15 : 18, $node($child, 2, 0, false) + ($compact ? 4 : 4.5));
    $group_h = max($ph + ($compact ? 6 : 7), array_sum($heights), $compact ? 32 : 41);
    $cy = $cursor + $group_h / 2;
    $centers[] = $cy;
    $node($parent['label'], 1, $cy, $draw);
    $child_y = $cursor + ($group_h - array_sum($heights)) / 2;
    foreach ($parent['children'] as $i => $child) {
      $gy = $child_y + $heights[$i] / 2;
      if ($draw) {
        $pdf->SetDrawColor(178, 187, 181);
        $pdf->SetLineWidth(0.18);
        $bx = $xs[2] - $w * 0.025;
        $pdf->Line($xs[1] + $widths[1], $cy, $bx, $cy);
        $pdf->Line($bx, $cy, $bx, $gy);
        $pdf->Line($bx, $gy, $xs[2] - 1, $gy);
      }
      $node($child, 2, $gy, $draw);
      $child_y += $heights[$i];
    }
    $cursor += $group_h;
  }
  $subject = array('name' => $data['name'], 'breed' => '');
  $sh = $node($subject, 0, 0, false);
  $cy = ($centers[0] + end($centers)) / 2;
  if ($draw) {
    $pdf->SetDrawColor(160, 172, 166);
    $pdf->SetLineWidth(0.2);
    $bx = $xs[1] - $w * 0.025;
    $pdf->Line($xs[0] + $widths[0], $cy, $bx, $cy);
    foreach ($centers as $py) {
      $pdf->Line($bx, $cy, $bx, $py);
      $pdf->Line($bx, $py, $xs[1] - 1, $py);
    }
    $node($subject, 0, $cy, true);
  }
  return max($cursor, $cy + $sh / 2 + 2) - $top;
}

/**
 * Bloc Production — arbitrage client : bloc MAJEUR de la fiche Poulinière (prend la place occupée
 * par Reproduction + Conditions de monte sur l'Étalon), jamais un tableau ni une colonne fixe
 * ISO/ICC/IDR — une ligne compacte par produit via la fonction pure déjà testée
 * gwseq_horse_pdf_production_line() (jamais son BLUP). Ne fait JAMAIS passer la fiche sur plusieurs
 * pages (contrairement à Présentation/Commentaire production, qui peuvent paginer via $flow comme
 * sur l'Étalon) : affiche autant de produits que $available_h le permet réellement, priorité aux
 * produits indexés puis aux meilleurs indices, non-indexés retirés en premier
 * (gwseq_horse_pdf_select_production_entries(), déjà testée) ; l'affichage final reste dans l'ordre
 * d'origine. Débordement -> ligne exacte "+ N autres produits", jamais une troncature silencieuse.
 */
function gwseq_pouliniere_production($pdf, $x, $y, $w, $entries, $rgb, $available_h, $draw) {
  $entries = is_array($entries) ? array_values($entries) : array();
  $total = count($entries);
  if (!$total) return 0;
  $body_size = 9;
  $row_gap = 1.8;
  $title_h = gwseq_pouliniere_section($pdf, 0, 0, $w, 'PRODUCTION', $rgb, false);
  $line_h = gwseq_pouliniere_text($pdf, 0, 0, $w, 'Ag', $body_size, '', false);
  $row_h = $line_h + $row_gap;
  $overflow_text = function ($n) { return $n === 1 ? '+ 1 autre produit' : ('+ ' . $n . ' autres produits'); };

  $max = $total;
  while ($max > 0) {
    $kept = count(gwseq_horse_pdf_select_production_entries($entries, $max));
    $remainder = $total - $kept;
    $h = $title_h + ($kept * $row_h) + ($remainder > 0 ? $row_h : 0);
    if ($h <= $available_h) break;
    $max--;
  }
  if ($max === 0 && ($title_h + $row_h) > $available_h) return 0;

  $selected = gwseq_horse_pdf_select_production_entries($entries, $max);
  $remainder = $total - count($selected);
  $block_h = $title_h + (count($selected) * $row_h) + ($remainder > 0 ? $row_h : 0);
  if (!$draw) return $block_h;

  $iy = $y;
  $iy += gwseq_pouliniere_section($pdf, $x, $iy, $w, 'PRODUCTION', $rgb, true);
  foreach ($selected as $entry) {
    $full = gwseq_horse_pdf_production_line($entry);
    $name_raw = (string) ($entry['nom'] ?? '');
    $rest = mb_substr($full, mb_strlen($name_raw));
    $pdf->SetFont('helvetica', 'B', $body_size);
    $pdf->SetTextColor(45, 49, 47);
    $pdf->SetXY($x, $iy);
    $name_w = $pdf->GetStringWidth($name_raw) + 1;
    $pdf->Cell($name_w, $line_h, $name_raw, 0, 0, 'L');
    if ($rest !== '') {
      $pdf->SetFont('helvetica', '', $body_size);
      $pdf->SetTextColor(70, 65, 58);
      $pdf->SetXY($x + $name_w, $iy);
      $pdf->Cell($w - $name_w, $line_h, $rest, 0, 0, 'L');
    }
    $iy += $row_h;
  }
  if ($remainder > 0) {
    gwseq_pouliniere_text($pdf, $x, $iy, $w, $overflow_text($remainder), $body_size, 'I', true, array(128, 124, 116));
    $iy += $row_h;
  }
  return $iy - $y;
}

function gwseq_render_horse_pdf_template_pouliniere($pdf, $data) {
  $disable_credit = Closure::bind(function () { $this->tcpdflink = false; }, $pdf, 'TCPDF');
  $disable_credit();
  $old_padding = $pdf->getCellPaddings();
  $old_ratio = $pdf->getCellHeightRatio();
  $pdf->setCellPaddings(0, 0, 0, 0);
  $pdf->setCellHeightRatio(1.2);
  try {
    $rgb = gws_core_pdf_hex_to_rgb($data['structure']['primary_color']);
    $ink = gws_core_pdf_hex_to_rgb($data['structure']['primary_color_contrast']);
    $x = 14;
    $w = $pdf->getPageWidth() - 28;
    $limit = $pdf->getPageHeight() - gwseq_pouliniere_footer($pdf, $data, false) - 4;
    $body_size = 10;
    $gap = 4;
    $presentation = trim((string) ($data['editorial']['presentation'] ?? ''));
    // Arbitrage client : jamais le « Conseil de croisement » (spécifique à l'Étalon) sur la
    // Poulinière ; à sa place, le « Commentaire production » (champ métier existant dédié), affiché
    // seulement s'il est renseigné.
    $commentaire_production = trim((string) ($data['editorial']['commentaire_production'] ?? ''));
    $production = is_array($data['production'] ?? null) ? array_values($data['production']) : array();
    $block_h = function ($title, $body, $width, $size) use ($pdf, $rgb) {
      return gwseq_pouliniere_section($pdf, 0, 0, $width, $title, $rgb, false) + gwseq_pouliniere_text($pdf, 0, 0, $width, $body, $size, '', false);
    };
    // Deux budgets, calculés AVANT tout dessin (comme le master Étalon). La Production reste un
    // bloc autonome placé en dernier, qui ne force jamais une deuxième page — mais un minimum
    // (titre + quelques lignes) est réservé dans ce budget pour que le mode aéré ne l'écrase pas :
    // sans cette réserve, Présentation/Commentaire production/Pedigree pourraient occuper tout
    // l'espace restant et ne laisser presque rien au bloc "majeur" de la fiche (arbitrage client).
    $production_reserve_h = 0;
    if ($production) {
      $reserve_n = min(count($production), 6);
      $reserve_kept = count(gwseq_horse_pdf_select_production_entries($production, $reserve_n));
      $prod_line_h = gwseq_pouliniere_text($pdf, 0, 0, $w, 'Ag', 9, '', false) + 1.8;
      $production_reserve_h = gwseq_pouliniere_section($pdf, 0, 0, $w, 'PRODUCTION', $rgb, false) + ($reserve_kept + 1) * $prod_line_h;
    }
    foreach (array(false, true) as $compact) {
      $body_size = $compact ? 9.5 : 10;
      $gap = $compact ? 3 : 4;
      $hero_h = gwseq_pouliniere_hero($pdf, $data, $x, 24, $w, $rgb, $compact, false);
      $tree_h = gwseq_pouliniere_tree($pdf, $data, $x, 0, $w, $rgb, $compact, false);
      $ph = $presentation !== '' ? $block_h('PRÉSENTATION', $presentation, $w, $body_size) : 0;
      $cph = $commentaire_production !== '' ? $block_h('COMMENTAIRE PRODUCTION', $commentaire_production, $w, $body_size) : 0;
      $total = 24 + $hero_h + $gap;
      foreach (array($tree_h, $ph, $cph) as $height) if ($height > 0) $total += $height + $gap;
      if ($production) $total += $production_reserve_h + $gap;
      if ($total <= $limit) break;
    }
    // Cas pauvre : même mécanisme d'agrandissement de la photo dominante que le master Étalon.
    $extra_photo_h = 0;
    if (!$compact) {
      $slack = $limit - $total;
      if ($slack > 8) {
        $extra_photo_h = min($slack - 4, 55);
        $boosted_hero_h = gwseq_pouliniere_hero($pdf, $data, $x, 24, $w, $rgb, $compact, false, $extra_photo_h);
        if ($boosted_hero_h > $hero_h) {
          $hero_h = $boosted_hero_h;
          $gap += 1.5;
        } else {
          $extra_photo_h = 0;
        }
      }
    }
    gwseq_pouliniere_header($pdf, $data);
    $y = 24;
    $new_page = function () use ($pdf, $data, &$y) {
      gwseq_pouliniere_footer($pdf, $data);
      $pdf->AddPage();
      gwseq_pouliniere_header($pdf, $data, true);
      $y = 24;
    };
    $room = function ($h) use (&$y, $limit, $new_page) {
      if ($h > $limit - 24) throw new LengthException('Bloc Poulinière trop haut : vérifiez les noms ou les données de pedigree.');
      if ($y + $h > $limit) $new_page();
    };
    $room($hero_h);
    $y += gwseq_pouliniere_hero($pdf, $data, $x, $y, $w, $rgb, $compact, true, $extra_photo_h) + $gap;
    if ($tree_h > 0) {
      $room($tree_h);
      $y += gwseq_pouliniere_tree($pdf, $data, $x, $y, $w, $rgb, $compact, true) + $gap;
    }
    // Flux de secours : découpe mesurée, conserve chaque caractère, titre « suite » (duplication
    // volontaire du mécanisme du master Étalon, adaptée à des blocs séquentiels et non appariés).
    $flow = function ($title, $body, $colored = false, $size = null) use ($pdf, $x, $w, $rgb, $ink, $limit, $new_page, &$y, $gap, $body_size, $block_h) {
      $size = $size ?? $body_size;
      $pad = $colored ? 4 : 0;
      $tw = $w - 2 * $pad;
      $full_h = $block_h($title, $body, $tw, $size) + 2 * $pad;
      if ($y + $full_h > $limit && $full_h <= $limit - 24) $new_page();
      $continued = false;
      while ($body !== '') {
        $heading = $title . ($continued ? ' · SUITE' : '');
        $hh = gwseq_pouliniere_section($pdf, 0, 0, $tw, $heading, $rgb, false);
        $available = $limit - $y - $hh - 2 * $pad;
        $line_h = gwseq_pouliniere_text($pdf, 0, 0, $tw, 'Ag', $size, '', false);
        if ($available < 2 * $line_h) { $new_page(); continue; }
        $length = mb_strlen($body);
        $low = 0; $high = $length;
        while ($low < $high) {
          $mid = (int) ceil(($low + $high) / 2);
          $h = gwseq_pouliniere_text($pdf, 0, 0, $tw, mb_substr($body, 0, $mid), $size, '', false);
          if ($h <= $available) $low = $mid; else $high = $mid - 1;
        }
        if ($low < 1) throw new LengthException('Texte Poulinière impossible à composer.');
        if ($low < $length) {
          $prefix = mb_substr($body, 0, $low);
          $paragraph = mb_strrpos($prefix, "\n\n");
          if ($paragraph !== false && $paragraph > $low * 0.65) {
            $low = $paragraph + 2;
          } else {
            $space = mb_strrpos($prefix, ' ');
            if ($space !== false && $space > 0) $low = $space + 1;
          }
        }
        $chunk = mb_substr($body, 0, $low);
        $h = gwseq_pouliniere_text($pdf, 0, 0, $tw, $chunk, $size, '', false);
        if ($colored) {
          $pdf->SetFillColor($rgb[0], $rgb[1], $rgb[2]);
          $pdf->Rect($x, $y, $w, $hh + $h + 2 * $pad, 'F');
          gwseq_pouliniere_text($pdf, $x + $pad, $y + $pad, $tw, $heading, 11, 'B', true, $ink, 'times');
        } else gwseq_pouliniere_section($pdf, $x, $y, $w, $heading, $rgb, true);
        gwseq_pouliniere_text($pdf, $x + $pad, $y + $pad + $hh, $tw, $chunk, $size, '', true, $colored ? $ink : array(45, 49, 47));
        $y += $hh + $h + 2 * $pad + $gap;
        $body = mb_substr($body, $low);
        if ($body !== '') { $new_page(); $continued = true; }
      }
    };
    if ($presentation !== '') $flow('PRÉSENTATION', $presentation);
    if ($commentaire_production !== '') $flow('COMMENTAIRE PRODUCTION', $commentaire_production);
    if ($production) {
      $available_h = $limit - $y;
      $y += gwseq_pouliniere_production($pdf, $x, $y, $w, $production, $rgb, $available_h, true) + $gap;
    }
    gwseq_pouliniere_footer($pdf, $data);
    return true;
  } finally {
    $pdf->setCellPaddings($old_padding['L'], $old_padding['T'], $old_padding['R'], $old_padding['B']);
    $pdf->setCellHeightRatio($old_ratio);
  }
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

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

/**
 * Encre partagée par les 3 templates pour le nom du cheval et les titres de section (passe
 * graphique globale — design system PDF, arbitrage client : "le nom du cheval et les titres
 * doivent rester dans une encre neutre sombre ou une couleur dérivée de Ma structure, mais pas
 * dans une couleur arbitraire commune à tous les clients"). Choix retenu : une encre NEUTRE
 * (composantes quasi égales, aucune teinte dominante) plutôt qu'une dérivation de la couleur de
 * marque — un nom/titre reste net et lisible quelle que soit la couleur choisie par le client,
 * jamais soumis au même risque de pâleur qu'un accent coloré (voir gws_core_pdf_accent_color(),
 * réservée elle aux indices/prix/étoiles/accroche). Business-agnostique et purement graphique :
 * partagée entre les 3 jeux de fonctions dupliqués (gwseq_etalon_, gwseq_pouliniere_,
 * gwseq_sport_vente_) au même titre que GWSEQ_PDF_HEADER_BANNER_H ci-dessus, jamais une brique de
 * composition métier.
 */
const GWSEQ_PDF_INK_DISPLAY = array(26, 26, 24);

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
 * Composants génériques réutilisés — image/photo, puce, étoiles, libellé de nœud de pedigree.
 * Business-agnostiques, réutilisés à l'identique par les 3 templates (§1 de la demande). Le header,
 * le footer+QR, la galerie hero, les indices/qualités/à retenir et l'arbre de pedigree sont, eux,
 * dupliqués dans chaque jeu gwseq_etalon_*, gwseq_pouliniere_* et gwseq_sport_vente_* ci-dessous.
 * ----------------------------------------------------------------------------------------- */

/**
 * Version affichable d'une URL de site web (footer, correctif de recette réelle) : sans protocole,
 * jamais de slash final — l'URL COMPLÈTE d'origine (telle qu'enregistrée dans « Ma structure »)
 * reste utilisée partout ailleurs où un vrai lien/QR est nécessaire, jamais cette version tronquée.
 */
function gwseq_horse_pdf_display_url($url) {
  $url = trim((string) $url);
  if ($url === '') return '';
  $url = preg_replace('#^[a-z][a-z0-9+.-]*://#i', '', $url);
  return rtrim($url, '/');
}

/**
 * Petite plaque de fond claire dédiée derrière le logo (correctif de recette réelle) : uniquement
 * quand la couleur principale de « Ma structure » est elle-même claire (même calcul de contraste
 * WCAG que le texte du bandeau, gws_core_contrast_color() de gws-core) — un logo qui ne ressortirait
 * pas sur un fond clair reçoit un petit fond blanc derrière lui, jamais une recoloration arbitraire
 * du logo lui-même (le fichier image n'est jamais modifié).
 */
function gwseq_horse_pdf_draw_logo_backing($pdf, $x, $y, $w, $h) {
  $pad = 2;
  $pdf->SetFillColor(255, 255, 255);
  if (method_exists($pdf, 'RoundedRect')) {
    $pdf->RoundedRect($x - $pad, $y - $pad, $w + (2 * $pad), $h + (2 * $pad), 1.2, '1111', 'F');
  } else {
    $pdf->Rect($x - $pad, $y - $pad, $w + (2 * $pad), $h + (2 * $pad), 'F');
  }
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

/* -------------------------------------------------------------------------------------------
 * Pedigree — libellé de nœud, fonction PURE réutilisée par les 3 arbres dupliqués
 * (gwseq_etalon_tree()/gwseq_pouliniere_tree()/gwseq_sport_vente_tree()).
 * ----------------------------------------------------------------------------------------- */

function gwseq_horse_pdf_pedigree_node_label($node) {
  if (!is_array($node)) return null;
  if (!in_array($node['type'] ?? '', array('gws_horse', 'external'), true)) return null;
  $name = $node['name'] ?? '';
  if ($name === '') return null;
  return array('name' => $name, 'breed' => $node['breed'] ?? '');
}

/* -------------------------------------------------------------------------------------------
 * Notation en étoiles (vectorielle, jamais un glyphe de police — voir CR : les polices cœur PDF
 * n'ont pas de caractère étoile fiable) : réutilisée par le bloc Reproduction de l'Étalon ET, plus
 * discrètement, par la ligne État ostéo-articulaire de Sport/Vente (même donnée structurée, jamais
 * un texte libre concurrent créé pour ce second usage).
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
 * Composition RÉSERVÉE à Étalon (repris du correctif client — voir CR du lot : "ne réimplémente pas
 * ces mécanismes depuis ton ancienne version"). Les trois templates (Étalon/Poulinière/Sport-Vente)
 * ont chacun leur propre jeu de fonctions dupliqué (gwseq_etalon_*, gwseq_pouliniere_* et
 * gwseq_sport_vente_*), jamais partagé entre eux, pour que la mise au point de l'un ne puisse
 * jamais modifier le rendu déjà figé d'un autre. Mesure et dessin utilisent les mêmes métriques
 * TCPDF, sans fit/troncature de texte — la
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

/**
 * $rule (passe graphique globale — design system PDF, arbitrage client "le filet doit être réservé
 * aux grandes ruptures de lecture ; les autres sections doivent être distinguées par la
 * typographie et l'espacement") : faux par défaut — seul PEDIGREE (rupture structurelle évidente
 * de la fiche, unique arbre généalogique) le passe à vrai ; toute autre section (Présentation,
 * Conseil de croisement, Identification, Production, Résultats, Potentiel, Origines...) reste
 * distinguée par la taille/graisse/police du titre et l'espacement seuls, jamais par un filet.
 */
/**
 * $squeeze (pagination adaptative, priorité 4 : marges avant/après Présentation/Conseil/
 * Reproduction) : resserre légèrement l'espace SOUS un titre non-rulé à partir de $squeeze>=4 —
 * jamais celui de PEDIGREE (seule section rulée, dont la respiration relève de la priorité 3, voir
 * gwseq_etalon_tree()), jamais la taille du titre lui-même.
 */
function gwseq_etalon_section($pdf, $x, $y, $w, $title, $rgb, $draw, $rule = false, $squeeze = 0) {
  $h = gwseq_etalon_text($pdf, $x, $y, $w, $title, 10.5, 'B', $draw, GWSEQ_PDF_INK_DISPLAY, 'times');
  if ($draw && $rule) {
    $pdf->SetDrawColor($rgb[0], $rgb[1], $rgb[2]);
    $pdf->SetLineWidth(0.2);
    $pdf->Line($x, $y + $h + 0.6, $x + $w, $y + $h + 0.6);
  }
  return $h + ($rule ? 2.4 : 1.6 * ($squeeze >= 4 ? 0.75 : 1));
}

function gwseq_etalon_footer($pdf, $data, $draw = true) {
  $s = $data['structure'];
  $rgb = gws_core_pdf_hex_to_rgb($s['primary_color']);
  $ink = gws_core_pdf_hex_to_rgb($s['primary_color_contrast']);
  $url = (string) ($data['public_url'] ?? '');
  $has_qr = filter_var($url, FILTER_VALIDATE_URL) && in_array(strtolower(parse_url($url, PHP_URL_SCHEME) ?? ''), array('http', 'https'), true);
  $w = $pdf->getPageWidth() - 28 - ($has_qr ? 25 : 0);
  $coords = implode('  ·  ', array_filter(array($s['phone_display'] ?? '', $s['public_email'] ?? '', gwseq_horse_pdf_display_url($s['website_url'] ?? ''))));
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
  if ($box) {
    $logo_y = (20 - $box['h']) / 2;
    if (function_exists('gws_core_contrast_color') && gws_core_contrast_color($s['primary_color']) === '#000000') {
      gwseq_horse_pdf_draw_logo_backing($pdf, 14, $logo_y, $box['w'], $box['h']);
    }
    $pdf->Image($path, 14, $logo_y, $box['w'], $box['h']);
  }
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
  $hex = sprintf('#%02x%02x%02x', $rgb[0], $rgb[1], $rgb[2]);
  $tint = gws_core_pdf_lighten_color($hex, 0.94);
  // Étiquette "À RETENIR" en couleur de marque ACCENT-SAFE (design system PDF, §accent sur blanc) :
  // le fond ici est presque blanc (teinte 0.94), donc le même risque de pâleur qu'un accent sur
  // blanc pur — jamais la couleur brute telle quelle.
  $accent_rgb = gws_core_pdf_hex_to_rgb(gws_core_pdf_accent_color($hex));
  $pad = 2.6;
  $tw = $w - (2 * $pad);
  $label_h = gwseq_etalon_text($pdf, 0, 0, $tw, 'À RETENIR', 7, 'B', false);
  $body = implode("\n", $lines);
  $body_h = gwseq_etalon_text($pdf, 0, 0, $tw, $body, 9.5, 'I', false, array(40, 42, 38), 'times');
  $box_h = (2 * $pad) + $label_h + 1 + $body_h;
  if ($draw) {
    $pdf->SetFillColor($tint[0], $tint[1], $tint[2]);
    $pdf->Rect($x, $y, $w, $box_h, 'F');
    gwseq_etalon_text($pdf, $x + $pad, $y + $pad, $tw, 'À RETENIR', 7, 'B', true, $accent_rgb);
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
/**
 * $squeeze (pagination adaptative — correctif recette réelle Kado, jamais une taille de police
 * touchée ici) : niveau 0 = inchangé. Niveau >= 1 = priorité 1 de la compression demandée par le
 * client ("réduire légèrement les espacements verticaux non essentiels") — réduit UNIQUEMENT les
 * petits intervalles entre lignes de la colonne identité (jamais la hauteur du texte lui-même, ni
 * $extra_gap qui reste le mécanisme, distinct et déjà validé, du cas pauvre). Voir
 * gwseq_render_horse_pdf_template_etalon() pour la recherche du plus petit niveau suffisant avant
 * de créer une page 2.
 */
function gwseq_etalon_hero_identity($pdf, $data, $ix, $y, $iw, $rgb, $compact, $extra_gap, $draw, $squeeze = 0) {
  $iy = $y;
  $gaps = 0;
  $g1 = $squeeze >= 1 ? 0.72 : 1;
  // Indices en couleur de marque ACCENT-SAFE (design system PDF, §accent sur blanc) : la couleur
  // brute reste l'aplat de "Ma structure" (bandeau, puces) ; en TEXTE sur fond blanc elle est
  // assombrie si besoin pour rester lisible — voir gws_core_pdf_accent_color() (gws-core).
  $accent_rgb = gws_core_pdf_hex_to_rgb(gws_core_pdf_accent_color($data['structure']['primary_color']));
  $iy += gwseq_etalon_text($pdf, $ix, $iy, $iw, mb_strtoupper($data['name']), $compact ? 23 : 25, 'B', $draw, GWSEQ_PDF_INK_DISPLAY, 'times') + 0.5 * $g1 + $extra_gap;
  $gaps++;
  $id = $data['identity'];
  $parts = array($data['sexe_label'] ?? '', $id['annee_naissance'] ?? '', $data['race_label'] ?? '', $data['robe_label'] ?? '');
  if (($id['taille_cm'] ?? '') !== '') $parts[] = number_format((float) $id['taille_cm'] / 100, 2, ',', '') . ' m';
  $parts = array_filter($parts, function ($v) { return (string) $v !== ''; });
  if ($parts) { $iy += gwseq_etalon_text($pdf, $ix, $iy, $iw, implode(' · ', $parts), 9.5, '', $draw) + 0.5 * $g1 + $extra_gap; $gaps++; }
  // Naisseur discret (passe graphique V4) : plus petit, gris atténué — jamais au même niveau que
  // l'identité elle-même. Espacement resserré (correctif recette réelle, §hero compact) : le nom,
  // l'identité, le naisseur, les indices et les qualités doivent se lire comme UN SEUL ensemble
  // vertical, jamais cinq blocs espacés.
  if (!empty($id['eleveur'])) { $iy += gwseq_etalon_text($pdf, $ix, $iy, $iw, 'Naisseur : ' . $id['eleveur'], 8.3, '', $draw, array(128, 124, 116)) + 0.8 * $g1 + $extra_gap; $gaps++; }

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
  if ($indices) { $iy += gwseq_etalon_text($pdf, $ix, $iy, $iw, implode('   ·   ', $indices), $compact ? 11 : 12, 'B', $draw, $accent_rgb) + 0.5 * $g1 + $extra_gap; $gaps++; }
  $qualites = implode('   ·   ', array_slice(array_filter((array) ($data['qualites'] ?? array()), 'strlen'), 0, 5));
  if ($qualites !== '') { $iy += gwseq_etalon_text($pdf, $ix, $iy, $iw, $qualites, $compact ? 10 : 10.5, 'BI', $draw, array(70, 68, 60)) + 1.5 * $g1 + $extra_gap; $gaps++; }

  $faits = array_slice(array_filter((array) ($data['faits_marquants'] ?? array()), 'strlen'), 0, 3);
  if ($faits) { $iy += gwseq_etalon_callout($pdf, $ix, $iy + 2.5 * $g1 + $extra_gap, $iw, $faits, $rgb, $draw) + 2 * $g1 + $extra_gap; $gaps++; }

  return array('h' => $iy - $y, 'gaps' => $gaps);
}

function gwseq_etalon_hero($pdf, $data, $x, $y, $w, $rgb, $compact, $draw, $extra_photo_h = 0, $squeeze = 0) {
  $paths = array_values(array_unique(array_filter(array_merge(array($data['photo_path'] ?? ''), (array) ($data['gallery_paths'] ?? array())), function ($p) {
    return is_string($p) && $p !== '' && is_readable($p) && @getimagesize($p);
  })));
  $photo = $paths ? array_shift($paths) : '';
  $photos = array_slice($paths, 0, 3);
  $pw = $photo ? $w * 0.52 : 0;
  $ix = $photo ? $x + $pw + 8 : $x;
  $iw = $w - ($ix - $x);

  $measure = gwseq_etalon_hero_identity($pdf, $data, $ix, $y, $iw, $rgb, $compact, 0, false, $squeeze);
  $identity_h = $measure['h'];

  // Galerie (correctif V6, point 1 seul modifié) : à partir de 2 photos secondaires, des
  // miniatures côte à côte occupent ENSEMBLE toute la largeur de la grande photo (ratio ~4:3 dérivé
  // de cette largeur). Avec UNE seule photo secondaire : jamais étirée pleine largeur ni centrée —
  // une vignette de taille raisonnable (~45-50 % de la largeur de la photo principale, ratio ~4:3),
  // alignée à GAUCHE ; le blanc laissé à droite est volontaire (effet éditorial, pas un trou).
  // L'image est toujours rendue en `cover` (ratio conservé, recadrage centré, jamais de
  // déformation). $g2 (pagination adaptative, priorité 2 : hauteur des miniatures secondaires
  // uniquement, jamais la photo principale) réduit légèrement cette hauteur à partir de $squeeze>=2.
  $g2 = $squeeze >= 2 ? 0.85 : 1;
  // Écart resserré à quelques mm (correctif recette réelle, §galerie) : les photos secondaires
  // doivent se lire comme visuellement rattachées à la photo principale, jamais comme un second
  // bloc séparé.
  $thumb_gap = 1.5;
  $thumb_h = 0;
  $thumb_w = 0;
  $n = count($photos);
  if ($n === 1) {
    $thumb_w = $pw * 0.475;
    // Ratio légèrement plus large en mode compact (contenu déjà dense) pour rester dans le budget
    // d'une page — "ratio 4:3 environ" reste respecté en mode aéré, cas normal de cette vignette.
    $thumb_h = ($thumb_w / ($compact ? 1.7 : 1.333)) * $g2;
  } elseif ($n > 1) {
    $thumb_w = ($pw - (($n - 1) * $thumb_gap)) / $n;
    $thumb_h = ($thumb_w / 1.34) * $g2;
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
    gwseq_etalon_hero_identity($pdf, $data, $ix, $y, $iw, $rgb, $compact, $extra_gap, true, $squeeze);
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

/**
 * Nœuds mesurés et multilignes ; les branches absentes n'ont aucun emplacement réservé. $squeeze
 * (pagination adaptative, priorité 3 : respiration verticale du pedigree, jamais sa lisibilité) —
 * à partir de $squeeze>=3, resserre les planchers d'espacement entre générations SANS jamais
 * changer une taille de police ($size dans $node ci-dessous reste intact).
 */
function gwseq_etalon_tree($pdf, $data, $x, $y, $w, $rgb, $compact, $draw, $squeeze = 0) {
  $g3 = $squeeze >= 3 ? 0.85 : 1;
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
  $y += gwseq_etalon_section($pdf, $x, $y, $w, 'PEDIGREE', $rgb, $draw, true, 0);
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
    $nh = gwseq_etalon_text($pdf, 0, 0, $widths[$col], $name, $size, 'B', false, GWSEQ_PDF_INK_DISPLAY, 'times');
    $bh = empty($label['breed']) ? 0 : gwseq_etalon_text($pdf, 0, 0, $widths[$col], $label['breed'], 8.5, '', false);
    if ($paint) {
      gwseq_etalon_text($pdf, $xs[$col], $cy - ($nh + $bh) / 2, $widths[$col], $name, $size, 'B', true, GWSEQ_PDF_INK_DISPLAY, 'times');
      if ($bh) gwseq_etalon_text($pdf, $xs[$col], $cy - ($nh + $bh) / 2 + $nh, $widths[$col], $label['breed'], 8.5, '', true, array(90, 95, 90));
    }
    return $nh + $bh;
  };
  $centers = array();
  $cursor = $y;
  foreach ($parents as $parent) {
    $ph = $node($parent['label'], 1, 0, false);
    $heights = array();
    foreach ($parent['children'] as $child) $heights[] = max(($compact ? 15 : 18) * $g3, $node($child, 2, 0, false) + ($compact ? 4 : 4.5) * $g3);
    $group_h = max($ph + ($compact ? 6 : 7) * $g3, array_sum($heights), ($compact ? 32 : 41) * $g3);
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
    // Étoiles/texte accent en couleur ACCENT-SAFE (design system PDF) — voir gwseq_etalon_hero_identity().
    $accent_rgb = gws_core_pdf_hex_to_rgb(gws_core_pdf_accent_color($data['structure']['primary_color']));
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
    $block_h = function ($title, $body, $width, $size, $squeeze = 0) use ($pdf, $rgb) {
      return gwseq_etalon_section($pdf, 0, 0, $width, $title, $rgb, false, false, $squeeze) + gwseq_etalon_text($pdf, 0, 0, $width, $body, $size, '', false);
    };
    // Mesure EXACTE (mêmes appels que le dessin réel plus bas, §point 6) — jamais l'estimation
    // générique $block_h, qui sous-évaluait la hauteur réelle de Reproduction (mise en page
    // typographique dédiée, pas un simple titre + paragraphe) et laissait passer un budget que le
    // dessin réel ne tenait finalement pas (cause du correctif Kado : CONDITIONS DE MONTE envoyée
    // page 2 malgré un budget mesuré comme suffisant).
    $repro_h = function ($body_size, $squeeze) use ($pdf, $w, $has_osteo, $wffs_val, $repro_line1, $repro_line2) {
      $g4 = $squeeze >= 4 ? 0.75 : 1;
      $h = gwseq_etalon_text($pdf, 0, 0, $w, 'REPRODUCTION', 8, 'B', false) + 1.8 * $g4;
      if ($repro_line1) {
        $h += gwseq_etalon_text($pdf, 0, 0, $w, 'Ag', $body_size, 'B', false) + 1.8 * $g4;
      }
      if ($repro_line2 !== '') $h += gwseq_etalon_text($pdf, 0, 0, $w, $repro_line2, $body_size, '', false);
      return $h;
    };
    // Recherche du budget le plus léger AVANT toute page 2 (correctif recette réelle Kado, §
    // "détecter qu'il manque seulement quelques millimètres et passer dans un mode plus compact").
    // Ordre EXACT : aéré (§squeeze toujours 0, inchangé) puis compact (§squeeze toujours 0 —
    // "passer dans un mode plus compact", mode déjà figé, jamais modifié ici) — ces deux premières
    // tentatives restent STRICTEMENT identiques au comportement déjà validé (même
    // condition `<= $limit`, sans marge), pour qu'AUCUNE fiche qui tenait déjà ne change de rendu.
    // Seulement si même le compact "de base" ne suffit pas, 5 niveaux de compression croissants
    // supplémentaires ($squeeze 1..5) sont essayés, TOUJOURS en mode compact (jamais en aéré — un
    // essai en aéré+squeeze ferait courir le risque de retenir des polices plus grandes que le
    // compact "de base", au prix d'un habillage moins dense ailleurs sur la page, ex. Production
    // Poulinière), dans l'ordre de priorité demandé par le client (espacements -> miniatures ->
    // pedigree -> marges de section -> interlignage, en tout dernier recours) — jamais le nom, les
    // indices, les qualités ni un titre de section. Si même le maximum de compression ne suffit pas,
    // la pagination de secours ($flow) reste le filet de sécurité final pour un contenu réellement
    // trop volumineux pour une page A4.
    $squeeze = 0;
    foreach (array(false, true) as $compact) {
      $body_size = $compact ? 9.5 : 10;
      $squeeze_levels = $compact ? array(0, 1, 2, 3, 4, 5) : array(0);
      foreach ($squeeze_levels as $squeeze) {
        $gap = ($compact ? 3 : 4) * ($squeeze >= 1 ? 0.8 : 1);
        $pdf->setCellHeightRatio($squeeze >= 5 ? 1.14 : 1.2);
        $hero_h = gwseq_etalon_hero($pdf, $data, $x, 24, $w, $rgb, $compact, false, 0, $squeeze);
        $tree_h = gwseq_etalon_tree($pdf, $data, $x, 0, $w, $rgb, $compact, false, $squeeze);
        $ew = count($editorial) === 2 ? ($w - 7) / 2 : $w;
        $eh = 0;
        foreach ($editorial as $block) $eh = max($eh, $block_h($block[0], $block[1], $ew, $body_size, $squeeze));
        $rh = $repro ? $repro_h($body_size, $squeeze) : 0;
        $ch = $conditions !== '' ? $block_h('CONDITIONS DE MONTE', $conditions, $w - 8, $body_size, $squeeze) + 8 : 0;
        $ih = $ids ? $block_h('IDENTIFICATION', implode(' · ', $ids), $w, 8, $squeeze) : 0;
        $total = 24 + $hero_h + $gap;
        foreach (array($tree_h, $eh, $rh, $ch, $ih) as $height) if ($height > 0) $total += $height + $gap;
        // Marge de sécurité de 3 mm UNIQUEMENT pour les niveaux de compression ajoutés ($squeeze >=
        // 1) : la mesure ci-dessus reste une estimation, un budget qui ne tient qu'à quelques
        // dixièmes de mm près se révèle parfois insuffisant au dessin réel (arrondis TCPDF) — sans
        // cette marge, un budget "tout juste suffisant" pouvait encore renvoyer un bloc en page 2
        // (cause du correctif Kado). Jamais appliquée aux deux premières tentatives ($squeeze === 0,
        // condition `<= $limit` d'origine, sans marge) ni à $limit lui-même.
        if ($total <= ($squeeze === 0 ? $limit : $limit - 3)) break 2;
      }
    }
    // Cas pauvre (passe graphique V4, point 2) : du blanc en trop en mode aéré ne doit jamais
    // donner l'impression que des blocs manquent — la photo/le hero dominants sont nettement
    // agrandis et les respirations augmentées pour absorber ce blanc volontairement (jamais les
    // mêmes proportions que le cas riche), jamais une donnée fabriquée. Sans effet si le mode
    // compact a dû être choisi, si la moindre compression a été nécessaire pour tenir (§squeeze >
    // 0 : ce n'est alors plus "trop de blanc", juste assez) ou sans photo valide (gwseq_etalon_hero()
    // ignore alors $extra_photo_h).
    $extra_photo_h = 0;
    if (!$compact && $squeeze === 0) {
      $slack = $limit - $total;
      if ($slack > 8) {
        $extra_photo_h = min($slack - 4, 55);
        $boosted_hero_h = gwseq_etalon_hero($pdf, $data, $x, 24, $w, $rgb, $compact, false, $extra_photo_h, 0);
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
    $y += gwseq_etalon_hero($pdf, $data, $x, $y, $w, $rgb, $compact, true, $extra_photo_h, $squeeze) + $gap;
    if ($tree_h > 0) {
      $room($tree_h);
      $y += gwseq_etalon_tree($pdf, $data, $x, $y, $w, $rgb, $compact, true, $squeeze) + $gap;
    }
    // Flux de secours : découpe mesurée, conserve chaque caractère, titre « suite ».
    $flow = function ($title, $body, $colored = false, $size = null) use ($pdf, $x, $w, $rgb, $ink, $limit, $new_page, &$y, $gap, $body_size, $block_h, $squeeze) {
      $size = $size ?? $body_size;
      $pad = $colored ? 4 : 0;
      $tw = $w - 2 * $pad;
      $full_h = $block_h($title, $body, $tw, $size, $squeeze) + 2 * $pad;
      if ($y + $full_h > $limit && $full_h <= $limit - 24) $new_page();
      $continued = false;
      while ($body !== '') {
        $heading = $title . ($continued ? ' · SUITE' : '');
        $hh = gwseq_etalon_section($pdf, 0, 0, $tw, $heading, $rgb, false, false, $squeeze);
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
        } else gwseq_etalon_section($pdf, $x, $y, $w, $heading, $rgb, true, false, $squeeze);
        gwseq_etalon_text($pdf, $x + $pad, $y + $pad + $hh, $tw, $chunk, $size, '', true, $colored ? $ink : array(45, 49, 47));
        $y += $hh + $h + 2 * $pad + $gap;
        $body = mb_substr($body, $low);
        if ($body !== '') { $new_page(); $continued = true; }
      }
    };
    // $g4 (priorité 4, voir gwseq_etalon_section()) : resserre également les marges propres à
    // Reproduction (intitulé + lignes), qui ne passe pas par gwseq_etalon_section() pour son
    // dessin réel (traitement typographique dédié, §point 6).
    $g4 = $squeeze >= 4 ? 0.75 : 1;
    if ($editorial && $y + $eh <= $limit) {
      foreach ($editorial as $i => $block) {
        $bx = $x + $i * ($ew + 7);
        $hh = gwseq_etalon_section($pdf, $bx, $y, $ew, $block[0], $rgb, true, false, $squeeze);
        gwseq_etalon_text($pdf, $bx, $y + $hh, $ew, $block[1], $body_size, '', true);
      }
      $y += $eh + $gap;
    } else foreach ($editorial as $block) $flow($block[0], $block[1]);
    if ($repro) {
      $room($rh);
      // Passe graphique V4 (point 6) : intitulé discret sans filet pleine largeur — moins
      // "section technique", plus proche du traitement éditorial de "À RETENIR".
      $y += gwseq_etalon_text($pdf, $x, $y, $w, 'REPRODUCTION', 8, 'B', true, array(115, 120, 110)) + 1.8 * $g4;
      if ($repro_line1) {
        $line_h = gwseq_etalon_text($pdf, 0, 0, $w, 'Ag', $body_size, 'B', false);
        $cx = $x;
        if ($has_osteo) {
          $cx += gwseq_horse_pdf_draw_star_rating($pdf, $cx, $y + ($line_h - 3.4) / 2, $data['statut_osteo'], $accent_rgb, 3.4) + 3;
          $pdf->SetFont('helvetica', 'B', $body_size);
          $pdf->SetTextColor($accent_rgb[0], $accent_rgb[1], $accent_rgb[2]);
          $pdf->SetXY($cx, $y);
          $pdf->Cell($pdf->GetStringWidth('OSTÉO-ARTICULAIRE') + 1, $line_h, 'OSTÉO-ARTICULAIRE', 0, 0, 'L');
          $cx += $pdf->GetStringWidth('OSTÉO-ARTICULAIRE') + 1;
        }
        if ($wffs_val !== '') {
          $wffs_text = ($has_osteo ? '   ·   ' : '') . 'WFFS ' . $wffs_val;
          gwseq_etalon_text($pdf, $cx, $y, $w - ($cx - $x), $wffs_text, $body_size, $has_osteo ? '' : 'B', true, $has_osteo ? array(45, 49, 47) : $accent_rgb);
        }
        $y += $line_h + 1.8 * $g4;
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

/** $rule/$squeeze : voir gwseq_etalon_section() — même règle, dupliquée à dessein (§ architecture du fichier). */
function gwseq_pouliniere_section($pdf, $x, $y, $w, $title, $rgb, $draw, $rule = false, $squeeze = 0) {
  $h = gwseq_pouliniere_text($pdf, $x, $y, $w, $title, 10.5, 'B', $draw, GWSEQ_PDF_INK_DISPLAY, 'times');
  if ($draw && $rule) {
    $pdf->SetDrawColor($rgb[0], $rgb[1], $rgb[2]);
    $pdf->SetLineWidth(0.2);
    $pdf->Line($x, $y + $h + 0.6, $x + $w, $y + $h + 0.6);
  }
  return $h + ($rule ? 2.4 : 1.6 * ($squeeze >= 4 ? 0.75 : 1));
}

function gwseq_pouliniere_footer($pdf, $data, $draw = true) {
  $s = $data['structure'];
  $rgb = gws_core_pdf_hex_to_rgb($s['primary_color']);
  $ink = gws_core_pdf_hex_to_rgb($s['primary_color_contrast']);
  $url = (string) ($data['public_url'] ?? '');
  $has_qr = filter_var($url, FILTER_VALIDATE_URL) && in_array(strtolower(parse_url($url, PHP_URL_SCHEME) ?? ''), array('http', 'https'), true);
  $w = $pdf->getPageWidth() - 28 - ($has_qr ? 25 : 0);
  $coords = implode('  ·  ', array_filter(array($s['phone_display'] ?? '', $s['public_email'] ?? '', gwseq_horse_pdf_display_url($s['website_url'] ?? ''))));
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
  if ($box) {
    $logo_y = (20 - $box['h']) / 2;
    if (function_exists('gws_core_contrast_color') && gws_core_contrast_color($s['primary_color']) === '#000000') {
      gwseq_horse_pdf_draw_logo_backing($pdf, 14, $logo_y, $box['w'], $box['h']);
    }
    $pdf->Image($path, 14, $logo_y, $box['w'], $box['h']);
  }
  else {
    $h = gwseq_pouliniere_text($pdf, 14, 0, 132, $s['name'], 13, 'B', false, $ink, 'times');
    if ($h > 17) throw new LengthException('Nom de structure trop long pour le bandeau Poulinière.');
    gwseq_pouliniere_text($pdf, 14, (20 - $h) / 2, 132, $s['name'], 13, 'B', true, $ink, 'times');
  }
  gwseq_pouliniere_text($pdf, $pdf->getPageWidth() - 58, 7, 44, $continued ? 'FICHE POULINIÈRE · SUITE' : 'FICHE POULINIÈRE', 8, '', true, $ink);
}

function gwseq_pouliniere_callout($pdf, $x, $y, $w, $lines, $rgb, $draw) {
  if (!$lines) return 0;
  $hex = sprintf('#%02x%02x%02x', $rgb[0], $rgb[1], $rgb[2]);
  $tint = gws_core_pdf_lighten_color($hex, 0.94);
  // Étiquette accent-safe : voir gwseq_etalon_callout() — même règle, dupliquée à dessein.
  $accent_rgb = gws_core_pdf_hex_to_rgb(gws_core_pdf_accent_color($hex));
  $pad = 2.6;
  $tw = $w - (2 * $pad);
  $label_h = gwseq_pouliniere_text($pdf, 0, 0, $tw, 'À RETENIR', 7, 'B', false);
  $body = implode("\n", $lines);
  $body_h = gwseq_pouliniere_text($pdf, 0, 0, $tw, $body, 9.5, 'I', false, array(40, 42, 38), 'times');
  $box_h = (2 * $pad) + $label_h + 1 + $body_h;
  if ($draw) {
    $pdf->SetFillColor($tint[0], $tint[1], $tint[2]);
    $pdf->Rect($x, $y, $w, $box_h, 'F');
    gwseq_pouliniere_text($pdf, $x + $pad, $y + $pad, $tw, 'À RETENIR', 7, 'B', true, $accent_rgb);
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
function gwseq_pouliniere_hero_identity($pdf, $data, $ix, $y, $iw, $rgb, $compact, $extra_gap, $draw, $squeeze = 0) {
  $iy = $y;
  $gaps = 0;
  $g1 = $squeeze >= 1 ? 0.72 : 1;
  // Accent-safe : voir gwseq_etalon_hero_identity() — même règle, dupliquée à dessein.
  $accent_rgb = gws_core_pdf_hex_to_rgb(gws_core_pdf_accent_color($data['structure']['primary_color']));
  $iy += gwseq_pouliniere_text($pdf, $ix, $iy, $iw, mb_strtoupper($data['name']), $compact ? 23 : 25, 'B', $draw, GWSEQ_PDF_INK_DISPLAY, 'times') + 0.5 * $g1 + $extra_gap;
  $gaps++;
  $id = $data['identity'];
  $parts = array($data['sexe_label'] ?? '', $id['annee_naissance'] ?? '', $data['race_label'] ?? '', $data['robe_label'] ?? '');
  if (($id['taille_cm'] ?? '') !== '') $parts[] = number_format((float) $id['taille_cm'] / 100, 2, ',', '') . ' m';
  $parts = array_filter($parts, function ($v) { return (string) $v !== ''; });
  // Espacement resserré (correctif recette réelle, §hero compact) : voir gwseq_etalon_hero_identity().
  if ($parts) { $iy += gwseq_pouliniere_text($pdf, $ix, $iy, $iw, implode(' · ', $parts), 9.5, '', $draw) + 0.5 * $g1 + $extra_gap; $gaps++; }

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
      if ($statut_label !== '') $cx += gwseq_horse_pdf_draw_chip($pdf, $cx, $iy, mb_strtoupper($statut_label), $rgb, gws_core_pdf_hex_to_rgb($data['structure']['primary_color_contrast']), 8.5, true) + 4;
      if ($price !== '') {
        $pdf->SetFont('helvetica', 'B', 15);
        $pdf->SetTextColor($accent_rgb[0], $accent_rgb[1], $accent_rgb[2]);
        $pdf->SetXY($cx, $iy - ($statut_label !== '' ? 0.6 : 0));
        $pdf->Cell($iw - ($cx - $ix), 7, $price, 0, 0, 'L');
      }
    }
    $iy += 9 + $extra_gap;
    $gaps++;
  }

  $indices = array();
  foreach ((array) ($data['sport_indices'] ?? array()) as $key => $item) {
    if (($item['valeur'] ?? '') !== '') $indices[] = strtoupper($key) . "\u{00A0}" . $item['valeur'];
  }
  foreach ((array) ($data['genetic_indices'] ?? array()) as $key => $item) {
    if (($item['valeur'] ?? '') !== '') $indices[] = strtoupper($key) . "\u{00A0}" . gwseq_cheval_genetic_indice_label($item['valeur'], '');
  }
  if ($indices) { $iy += gwseq_pouliniere_text($pdf, $ix, $iy, $iw, implode('   ·   ', $indices), $compact ? 11 : 12, 'B', $draw, $accent_rgb) + 0.5 * $g1 + $extra_gap; $gaps++; }
  $qualites = implode('   ·   ', array_slice(array_filter((array) ($data['qualites'] ?? array()), 'strlen'), 0, 5));
  if ($qualites !== '') { $iy += gwseq_pouliniere_text($pdf, $ix, $iy, $iw, $qualites, $compact ? 10 : 10.5, 'BI', $draw, array(70, 68, 60)) + 1.5 * $g1 + $extra_gap; $gaps++; }

  $faits = array_slice(array_filter((array) ($data['faits_marquants'] ?? array()), 'strlen'), 0, 3);
  if ($faits) { $iy += gwseq_pouliniere_callout($pdf, $ix, $iy + 2.5 * $g1 + $extra_gap, $iw, $faits, $rgb, $draw) + 2 * $g1 + $extra_gap; $gaps++; }

  return array('h' => $iy - $y, 'gaps' => $gaps);
}

function gwseq_pouliniere_hero($pdf, $data, $x, $y, $w, $rgb, $compact, $draw, $extra_photo_h = 0, $squeeze = 0) {
  $paths = array_values(array_unique(array_filter(array_merge(array($data['photo_path'] ?? ''), (array) ($data['gallery_paths'] ?? array())), function ($p) {
    return is_string($p) && $p !== '' && is_readable($p) && @getimagesize($p);
  })));
  $photo = $paths ? array_shift($paths) : '';
  $photos = array_slice($paths, 0, 3);
  $pw = $photo ? $w * 0.52 : 0;
  $ix = $photo ? $x + $pw + 8 : $x;
  $iw = $w - ($ix - $x);

  $measure = gwseq_pouliniere_hero_identity($pdf, $data, $ix, $y, $iw, $rgb, $compact, 0, false, $squeeze);
  $identity_h = $measure['h'];

  // Galerie : mêmes règles que le master Étalon (0/1/2/3 photo(s) secondaire(s)) — voir ses
  // commentaires pour la justification détaillée, non répétée ici (duplication volontaire, jamais
  // partagée avec gwseq_etalon_*). $g2 : voir gwseq_etalon_hero().
  $g2 = $squeeze >= 2 ? 0.85 : 1;
  // Écart resserré à quelques mm (correctif recette réelle, §galerie) : voir gwseq_etalon_hero().
  $thumb_gap = 1.5;
  $thumb_h = 0;
  $thumb_w = 0;
  $n = count($photos);
  if ($n === 1) {
    $thumb_w = $pw * 0.475;
    $thumb_h = ($thumb_w / ($compact ? 1.7 : 1.333)) * $g2;
  } elseif ($n > 1) {
    $thumb_w = ($pw - (($n - 1) * $thumb_gap)) / $n;
    $thumb_h = ($thumb_w / 1.34) * $g2;
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
    gwseq_pouliniere_hero_identity($pdf, $data, $ix, $y, $iw, $rgb, $compact, $extra_gap, true, $squeeze);
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

/** Duplication volontaire de gwseq_etalon_tree() — même arbre 3 générations, jamais partagée. $squeeze : voir gwseq_etalon_tree(). */
function gwseq_pouliniere_tree($pdf, $data, $x, $y, $w, $rgb, $compact, $draw, $squeeze = 0) {
  $g3 = $squeeze >= 3 ? 0.85 : 1;
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
  $y += gwseq_pouliniere_section($pdf, $x, $y, $w, 'PEDIGREE', $rgb, $draw, true, 0);
  $widths = array($w * 0.13, $w * 0.34, $w * 0.44);
  $xs = array($x, $x + $w * 0.17, $x + $w * 0.57);
  $node = function ($label, $col, $cy, $paint) use ($pdf, $widths, $xs, $compact) {
    if ($col === 1) $size = $compact ? 12.5 : 13.5;
    elseif ($col === 2) $size = $compact ? 10 : 10.5;
    else $size = $compact ? 9.5 : 10;
    $name = mb_strtoupper($label['name']);
    $pdf->SetFont('times', 'B', $size);
    if ($pdf->GetStringWidth($name) > $widths[$col]) $size -= 0.5;
    $nh = gwseq_pouliniere_text($pdf, 0, 0, $widths[$col], $name, $size, 'B', false, GWSEQ_PDF_INK_DISPLAY, 'times');
    $bh = empty($label['breed']) ? 0 : gwseq_pouliniere_text($pdf, 0, 0, $widths[$col], $label['breed'], 8.5, '', false);
    if ($paint) {
      gwseq_pouliniere_text($pdf, $xs[$col], $cy - ($nh + $bh) / 2, $widths[$col], $name, $size, 'B', true, GWSEQ_PDF_INK_DISPLAY, 'times');
      if ($bh) gwseq_pouliniere_text($pdf, $xs[$col], $cy - ($nh + $bh) / 2 + $nh, $widths[$col], $label['breed'], 8.5, '', true, array(90, 95, 90));
    }
    return $nh + $bh;
  };
  $centers = array();
  $cursor = $y;
  foreach ($parents as $parent) {
    $ph = $node($parent['label'], 1, 0, false);
    $heights = array();
    foreach ($parent['children'] as $child) $heights[] = max(($compact ? 15 : 18) * $g3, $node($child, 2, 0, false) + ($compact ? 4 : 4.5) * $g3);
    $group_h = max($ph + ($compact ? 6 : 7) * $g3, array_sum($heights), ($compact ? 32 : 41) * $g3);
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
function gwseq_pouliniere_production($pdf, $x, $y, $w, $entries, $rgb, $available_h, $draw, $squeeze = 0) {
  $entries = is_array($entries) ? array_values($entries) : array();
  $total = count($entries);
  if (!$total) return 0;
  $body_size = 9;
  $row_gap = 1.8;
  $title_h = gwseq_pouliniere_section($pdf, 0, 0, $w, 'PRODUCTION', $rgb, false, false, $squeeze);
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
  $iy += gwseq_pouliniere_section($pdf, $x, $iy, $w, 'PRODUCTION', $rgb, true, false, $squeeze);
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
    $block_h = function ($title, $body, $width, $size, $squeeze = 0) use ($pdf, $rgb) {
      return gwseq_pouliniere_section($pdf, 0, 0, $width, $title, $rgb, false, false, $squeeze) + gwseq_pouliniere_text($pdf, 0, 0, $width, $body, $size, '', false);
    };
    // Recherche du budget le plus léger AVANT toute page 2 : voir gwseq_render_horse_pdf_template_etalon()
    // pour le détail de la démarche (correctif recette réelle Kado) — même mécanique dupliquée ici.
    // La Production reste un bloc autonome placé en dernier, qui ne force jamais une deuxième page —
    // mais un minimum (titre + quelques lignes) est réservé dans ce budget pour que le mode aéré ne
    // l'écrase pas : sans cette réserve, Présentation/Commentaire production/Pedigree pourraient
    // occuper tout l'espace restant et ne laisser presque rien au bloc "majeur" de la fiche
    // (arbitrage client).
    // Ordre EXACT (aéré puis compact "de base", tous deux inchangés, puis compact+squeeze croissant
    // seulement si besoin) : voir gwseq_render_horse_pdf_template_etalon() pour le détail complet.
    $squeeze = 0;
    foreach (array(false, true) as $compact) {
      $body_size = $compact ? 9.5 : 10;
      $squeeze_levels = $compact ? array(0, 1, 2, 3, 4, 5) : array(0);
      foreach ($squeeze_levels as $squeeze) {
        $gap = ($compact ? 3 : 4) * ($squeeze >= 1 ? 0.8 : 1);
        $pdf->setCellHeightRatio($squeeze >= 5 ? 1.14 : 1.2);
        $production_reserve_h = 0;
        if ($production) {
          $reserve_n = min(count($production), 6);
          $reserve_kept = count(gwseq_horse_pdf_select_production_entries($production, $reserve_n));
          $prod_line_h = gwseq_pouliniere_text($pdf, 0, 0, $w, 'Ag', 9, '', false) + 1.8;
          $production_reserve_h = gwseq_pouliniere_section($pdf, 0, 0, $w, 'PRODUCTION', $rgb, false, false, $squeeze) + ($reserve_kept + 1) * $prod_line_h;
        }
        $hero_h = gwseq_pouliniere_hero($pdf, $data, $x, 24, $w, $rgb, $compact, false, 0, $squeeze);
        $tree_h = gwseq_pouliniere_tree($pdf, $data, $x, 0, $w, $rgb, $compact, false, $squeeze);
        $ph = $presentation !== '' ? $block_h('PRÉSENTATION', $presentation, $w, $body_size, $squeeze) : 0;
        $cph = $commentaire_production !== '' ? $block_h('COMMENTAIRE PRODUCTION', $commentaire_production, $w, $body_size, $squeeze) : 0;
        $total = 24 + $hero_h + $gap;
        foreach (array($tree_h, $ph, $cph) as $height) if ($height > 0) $total += $height + $gap;
        if ($production) $total += $production_reserve_h + $gap;
        // Marge de sécurité de 3 mm UNIQUEMENT pour $squeeze >= 1 : voir gwseq_render_horse_pdf_template_etalon().
        if ($total <= ($squeeze === 0 ? $limit : $limit - 3)) break 2;
      }
    }
    // Cas pauvre : même mécanisme d'agrandissement de la photo dominante que le master Étalon,
    // sans effet dès que la moindre compression ($squeeze > 0) a été nécessaire pour tenir.
    $extra_photo_h = 0;
    if (!$compact && $squeeze === 0) {
      $slack = $limit - $total;
      if ($slack > 8) {
        $extra_photo_h = min($slack - 4, 55);
        $boosted_hero_h = gwseq_pouliniere_hero($pdf, $data, $x, 24, $w, $rgb, $compact, false, $extra_photo_h, 0);
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
    $y += gwseq_pouliniere_hero($pdf, $data, $x, $y, $w, $rgb, $compact, true, $extra_photo_h, $squeeze) + $gap;
    if ($tree_h > 0) {
      $room($tree_h);
      $y += gwseq_pouliniere_tree($pdf, $data, $x, $y, $w, $rgb, $compact, true, $squeeze) + $gap;
    }
    // Flux de secours : découpe mesurée, conserve chaque caractère, titre « suite » (duplication
    // volontaire du mécanisme du master Étalon, adaptée à des blocs séquentiels et non appariés).
    $flow = function ($title, $body, $colored = false, $size = null) use ($pdf, $x, $w, $rgb, $ink, $limit, $new_page, &$y, $gap, $body_size, $block_h, $squeeze) {
      $size = $size ?? $body_size;
      $pad = $colored ? 4 : 0;
      $tw = $w - 2 * $pad;
      $full_h = $block_h($title, $body, $tw, $size, $squeeze) + 2 * $pad;
      if ($y + $full_h > $limit && $full_h <= $limit - 24) $new_page();
      $continued = false;
      while ($body !== '') {
        $heading = $title . ($continued ? ' · SUITE' : '');
        $hh = gwseq_pouliniere_section($pdf, 0, 0, $tw, $heading, $rgb, false, false, $squeeze);
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
        } else gwseq_pouliniere_section($pdf, $x, $y, $w, $heading, $rgb, true, false, $squeeze);
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
      $y += gwseq_pouliniere_production($pdf, $x, $y, $w, $production, $rgb, $available_h, true, $squeeze) + $gap;
    }
    gwseq_pouliniere_footer($pdf, $data);
    return true;
  } finally {
    $pdf->setCellPaddings($old_padding['L'], $old_padding['T'], $old_padding['R'], $old_padding['B']);
    $pdf->setCellHeightRatio($old_ratio);
  }
}

/**
 * Composition RÉSERVÉE à Sport/Vente (même langage graphique et mêmes principes de robustesse que
 * les masters Étalon/Poulinière désormais figés). Fonctions dupliquées à dessein, jamais partagées
 * avec gwseq_etalon_* ni gwseq_pouliniere_* : ces deux masters ne doivent plus jamais être modifiés
 * par ce travail.
 */
function gwseq_sport_vente_text($pdf, $x, $y, $w, $text, $size = 10, $style = '', $draw = true, $rgb = array(45, 49, 47), $font = 'helvetica') {
  $pdf->SetFont($font, $style, $size);
  $h = $pdf->getStringHeight($w, (string) $text);
  if ($draw) {
    $pdf->SetTextColor($rgb[0], $rgb[1], $rgb[2]);
    $pdf->SetXY($x, $y);
    $pdf->MultiCell($w, $h, (string) $text, 0, 'L', false, 1);
  }
  return $h;
}

/** $rule/$squeeze : voir gwseq_etalon_section() — même règle, dupliquée à dessein (§ architecture du fichier). */
function gwseq_sport_vente_section($pdf, $x, $y, $w, $title, $rgb, $draw, $rule = false, $squeeze = 0) {
  $h = gwseq_sport_vente_text($pdf, $x, $y, $w, $title, 10.5, 'B', $draw, GWSEQ_PDF_INK_DISPLAY, 'times');
  if ($draw && $rule) {
    $pdf->SetDrawColor($rgb[0], $rgb[1], $rgb[2]);
    $pdf->SetLineWidth(0.2);
    $pdf->Line($x, $y + $h + 0.6, $x + $w, $y + $h + 0.6);
  }
  return $h + ($rule ? 2.4 : 1.6 * ($squeeze >= 4 ? 0.75 : 1));
}

function gwseq_sport_vente_footer($pdf, $data, $draw = true) {
  $s = $data['structure'];
  $rgb = gws_core_pdf_hex_to_rgb($s['primary_color']);
  $ink = gws_core_pdf_hex_to_rgb($s['primary_color_contrast']);
  $url = (string) ($data['public_url'] ?? '');
  $has_qr = filter_var($url, FILTER_VALIDATE_URL) && in_array(strtolower(parse_url($url, PHP_URL_SCHEME) ?? ''), array('http', 'https'), true);
  $w = $pdf->getPageWidth() - 28 - ($has_qr ? 25 : 0);
  $coords = implode('  ·  ', array_filter(array($s['phone_display'] ?? '', $s['public_email'] ?? '', gwseq_horse_pdf_display_url($s['website_url'] ?? ''))));
  $name_h = gwseq_sport_vente_text($pdf, 14, 0, $w, $s['name'], 9, 'B', false);
  $coords_h = $coords === '' ? 0 : gwseq_sport_vente_text($pdf, 14, 0, $w, $coords, 8, '', false);
  $h = max($has_qr ? 22 : 15, $name_h + $coords_h + 6);
  if (!$draw) return $h;
  $y = $pdf->getPageHeight() - $h;
  $pdf->SetFillColor($rgb[0], $rgb[1], $rgb[2]);
  $pdf->Rect(0, $y, $pdf->getPageWidth(), $h, 'F');
  gwseq_sport_vente_text($pdf, 14, $y + 3, $w, $s['name'], 9, 'B', true, $ink);
  if ($coords !== '') gwseq_sport_vente_text($pdf, 14, $y + 3 + $name_h, $w, $coords, 8, '', true, $ink);
  if ($has_qr) {
    $pdf->write2DBarcode($url, 'QRCODE,M', $pdf->getPageWidth() - 33, $y + ($h - 19) / 2, 19, 19,
      array('border' => false, 'padding' => 2, 'fgcolor' => array(0, 0, 0), 'bgcolor' => array(255, 255, 255)), 'N');
  }
  return $h;
}

function gwseq_sport_vente_header($pdf, $data, $continued = false) {
  $s = $data['structure'];
  $rgb = gws_core_pdf_hex_to_rgb($s['primary_color']);
  $ink = gws_core_pdf_hex_to_rgb($s['primary_color_contrast']);
  $pdf->SetFillColor($rgb[0], $rgb[1], $rgb[2]);
  $pdf->Rect(0, 0, $pdf->getPageWidth(), 20, 'F');
  $path = !empty($s['logo_id']) ? get_attached_file($s['logo_id']) : '';
  $box = $path ? gws_core_pdf_fit_image_box($path, 95, 13) : null;
  if ($box) {
    $logo_y = (20 - $box['h']) / 2;
    if (function_exists('gws_core_contrast_color') && gws_core_contrast_color($s['primary_color']) === '#000000') {
      gwseq_horse_pdf_draw_logo_backing($pdf, 14, $logo_y, $box['w'], $box['h']);
    }
    $pdf->Image($path, 14, $logo_y, $box['w'], $box['h']);
  }
  else {
    $h = gwseq_sport_vente_text($pdf, 14, 0, 132, $s['name'], 13, 'B', false, $ink, 'times');
    if ($h > 17) throw new LengthException('Nom de structure trop long pour le bandeau Sport/Vente.');
    gwseq_sport_vente_text($pdf, 14, (20 - $h) / 2, 132, $s['name'], 13, 'B', true, $ink, 'times');
  }
  gwseq_sport_vente_text($pdf, $pdf->getPageWidth() - 58, 7, 44, $continued ? 'FICHE SPORT/VENTE · SUITE' : 'FICHE SPORT/VENTE', 8, '', true, $ink);
}

function gwseq_sport_vente_callout($pdf, $x, $y, $w, $lines, $rgb, $draw) {
  if (!$lines) return 0;
  $hex = sprintf('#%02x%02x%02x', $rgb[0], $rgb[1], $rgb[2]);
  $tint = gws_core_pdf_lighten_color($hex, 0.94);
  // Étiquette accent-safe : voir gwseq_etalon_callout() — même règle, dupliquée à dessein.
  $accent_rgb = gws_core_pdf_hex_to_rgb(gws_core_pdf_accent_color($hex));
  $pad = 2.6;
  $tw = $w - (2 * $pad);
  $label_h = gwseq_sport_vente_text($pdf, 0, 0, $tw, 'À RETENIR', 7, 'B', false);
  $body = implode("\n", $lines);
  $body_h = gwseq_sport_vente_text($pdf, 0, 0, $tw, $body, 9.5, 'I', false, array(40, 42, 38), 'times');
  $box_h = (2 * $pad) + $label_h + 1 + $body_h;
  if ($draw) {
    $pdf->SetFillColor($tint[0], $tint[1], $tint[2]);
    $pdf->Rect($x, $y, $w, $box_h, 'F');
    gwseq_sport_vente_text($pdf, $x + $pad, $y + $pad, $tw, 'À RETENIR', 7, 'B', true, $accent_rgb);
    gwseq_sport_vente_text($pdf, $x + $pad, $y + $pad + $label_h + 1, $tw, $body, 9.5, 'I', true, array(40, 42, 38), 'times');
  }
  return $box_h;
}

/**
 * Colonne identité du hero — même mécanique d'étirement de l'espacement interne que les masters
 * Étalon/Poulinière ($extra_gap/$gaps). Deux différences métier voulues par le client : une
 * accroche commerciale courte (jamais un bloc titré) juste sous l'identité, avant le statut/prix ;
 * le naisseur est conservé (contrairement à la Poulinière) mais après le statut/prix.
 */
function gwseq_sport_vente_hero_identity($pdf, $data, $ix, $y, $iw, $rgb, $compact, $extra_gap, $draw, $squeeze = 0) {
  $iy = $y;
  $gaps = 0;
  $g1 = $squeeze >= 1 ? 0.72 : 1;
  // Accent-safe : voir gwseq_etalon_hero_identity() — même règle, dupliquée à dessein. La secondaire
  // a son propre calcul (l'accroche est la seule zone du gabarit à utiliser cette couleur).
  $accent_rgb = gws_core_pdf_hex_to_rgb(gws_core_pdf_accent_color($data['structure']['primary_color']));
  $accent_secondary_rgb = gws_core_pdf_hex_to_rgb(gws_core_pdf_accent_color($data['structure']['secondary_color']));
  $iy += gwseq_sport_vente_text($pdf, $ix, $iy, $iw, mb_strtoupper($data['name']), $compact ? 23 : 25, 'B', $draw, GWSEQ_PDF_INK_DISPLAY, 'times') + 0.5 * $g1 + $extra_gap;
  $gaps++;
  $id = $data['identity'];
  $parts = array($data['sexe_label'] ?? '', $id['annee_naissance'] ?? '', $data['race_label'] ?? '', $data['robe_label'] ?? '');
  if (($id['taille_cm'] ?? '') !== '') $parts[] = number_format((float) $id['taille_cm'] / 100, 2, ',', '') . ' m';
  $parts = array_filter($parts, function ($v) { return (string) $v !== ''; });
  // Espacement resserré (correctif recette réelle, §hero compact) : voir gwseq_etalon_hero_identity().
  if ($parts) { $iy += gwseq_sport_vente_text($pdf, $ix, $iy, $iw, implode(' · ', $parts), 9.5, '', $draw) + 0.5 * $g1 + $extra_gap; $gaps++; }

  // Accroche commerciale (arbitrage client) : courte phrase éditoriale de vente, jamais un bloc
  // titré — un simple paragraphe stylé qui disparaît sans laisser de trou si non renseigné.
  $accroche = trim((string) ($data['editorial']['accroche_commerciale'] ?? ''));
  if ($accroche !== '') { $iy += gwseq_sport_vente_text($pdf, $ix, $iy, $iw, $accroche, 10.5, 'I', $draw, $accent_secondary_rgb, 'times') + 2 * $g1 + $extra_gap; $gaps++; }

  // Statut commercial + prix : jamais inventés (même garde que la Poulinière) ; le naisseur
  // (conservé, arbitrage client) vient après, discret, jamais au même niveau visuel.
  $statut = $data['commercial']['statut_commercial'] ?? 'not_offered';
  $statut_label = $statut !== 'not_offered' ? (gwseq_cheval_statut_commercial_options()[$statut] ?? '') : '';
  $price = (string) ($data['price_summary'] ?? '');
  if ($statut_label !== '' || $price !== '') {
    if ($draw) {
      $cx = $ix;
      if ($statut_label !== '') $cx += gwseq_horse_pdf_draw_chip($pdf, $cx, $iy, mb_strtoupper($statut_label), $rgb, gws_core_pdf_hex_to_rgb($data['structure']['primary_color_contrast']), 8.5, true) + 4;
      if ($price !== '') {
        $pdf->SetFont('helvetica', 'B', 15);
        $pdf->SetTextColor($accent_rgb[0], $accent_rgb[1], $accent_rgb[2]);
        $pdf->SetXY($cx, $iy - ($statut_label !== '' ? 0.6 : 0));
        $pdf->Cell($iw - ($cx - $ix), 7, $price, 0, 0, 'L');
      }
    }
    $iy += 9 + $extra_gap;
    $gaps++;
  }

  if (!empty($id['eleveur'])) { $iy += gwseq_sport_vente_text($pdf, $ix, $iy, $iw, 'Naisseur : ' . $id['eleveur'], 8.3, '', $draw, array(128, 124, 116)) + 0.8 * $g1 + $extra_gap; $gaps++; }

  $indices = array();
  foreach ((array) ($data['sport_indices'] ?? array()) as $key => $item) {
    if (($item['valeur'] ?? '') !== '') $indices[] = strtoupper($key) . "\u{00A0}" . $item['valeur'];
  }
  foreach ((array) ($data['genetic_indices'] ?? array()) as $key => $item) {
    if (($item['valeur'] ?? '') !== '') $indices[] = strtoupper($key) . "\u{00A0}" . gwseq_cheval_genetic_indice_label($item['valeur'], '');
  }
  if ($indices) { $iy += gwseq_sport_vente_text($pdf, $ix, $iy, $iw, implode('   ·   ', $indices), $compact ? 11 : 12, 'B', $draw, $accent_rgb) + 0.5 * $g1 + $extra_gap; $gaps++; }
  $qualites = implode('   ·   ', array_slice(array_filter((array) ($data['qualites'] ?? array()), 'strlen'), 0, 5));
  if ($qualites !== '') { $iy += gwseq_sport_vente_text($pdf, $ix, $iy, $iw, $qualites, $compact ? 10 : 10.5, 'BI', $draw, array(70, 68, 60)) + 1.5 * $g1 + $extra_gap; $gaps++; }

  $faits = array_slice(array_filter((array) ($data['faits_marquants'] ?? array()), 'strlen'), 0, 3);
  if ($faits) { $iy += gwseq_sport_vente_callout($pdf, $ix, $iy + 2.5 * $g1 + $extra_gap, $iw, $faits, $rgb, $draw) + 2 * $g1 + $extra_gap; $gaps++; }

  return array('h' => $iy - $y, 'gaps' => $gaps);
}

function gwseq_sport_vente_hero($pdf, $data, $x, $y, $w, $rgb, $compact, $draw, $extra_photo_h = 0, $squeeze = 0) {
  $paths = array_values(array_unique(array_filter(array_merge(array($data['photo_path'] ?? ''), (array) ($data['gallery_paths'] ?? array())), function ($p) {
    return is_string($p) && $p !== '' && is_readable($p) && @getimagesize($p);
  })));
  $photo = $paths ? array_shift($paths) : '';
  $photos = array_slice($paths, 0, 3);
  $pw = $photo ? $w * 0.52 : 0;
  $ix = $photo ? $x + $pw + 8 : $x;
  $iw = $w - ($ix - $x);

  $measure = gwseq_sport_vente_hero_identity($pdf, $data, $ix, $y, $iw, $rgb, $compact, 0, false, $squeeze);
  $identity_h = $measure['h'];

  // Galerie : mêmes règles que les masters Étalon/Poulinière (0/1/2/3 photo(s) secondaire(s)) —
  // duplication volontaire, jamais partagée. $g2 : voir gwseq_etalon_hero().
  $g2 = $squeeze >= 2 ? 0.85 : 1;
  // Écart resserré à quelques mm (correctif recette réelle, §galerie) : voir gwseq_etalon_hero().
  $thumb_gap = 1.5;
  $thumb_h = 0;
  $thumb_w = 0;
  $n = count($photos);
  if ($n === 1) {
    $thumb_w = $pw * 0.475;
    $thumb_h = ($thumb_w / ($compact ? 1.7 : 1.333)) * $g2;
  } elseif ($n > 1) {
    $thumb_w = ($pw - (($n - 1) * $thumb_gap)) / $n;
    $thumb_h = ($thumb_w / 1.34) * $g2;
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
    gwseq_sport_vente_hero_identity($pdf, $data, $ix, $y, $iw, $rgb, $compact, $extra_gap, true, $squeeze);
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

/** Duplication volontaire de gwseq_etalon_tree()/gwseq_pouliniere_tree() — même arbre à 6
 * ascendants (sujet -> 2 parents -> 4 grands-parents), jamais simplifié pour gagner de la place :
 * une branche absente disparaît, les branches renseignées sont toutes affichées (arbitrage client).
 */
function gwseq_sport_vente_tree($pdf, $data, $x, $y, $w, $rgb, $compact, $draw, $squeeze = 0) {
  $g3 = $squeeze >= 3 ? 0.85 : 1;
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
  $y += gwseq_sport_vente_section($pdf, $x, $y, $w, 'PEDIGREE', $rgb, $draw, true, 0);
  $widths = array($w * 0.13, $w * 0.34, $w * 0.44);
  $xs = array($x, $x + $w * 0.17, $x + $w * 0.57);
  $node = function ($label, $col, $cy, $paint) use ($pdf, $widths, $xs, $compact) {
    if ($col === 1) $size = $compact ? 12.5 : 13.5;
    elseif ($col === 2) $size = $compact ? 10 : 10.5;
    else $size = $compact ? 9.5 : 10;
    $name = mb_strtoupper($label['name']);
    $pdf->SetFont('times', 'B', $size);
    if ($pdf->GetStringWidth($name) > $widths[$col]) $size -= 0.5;
    $nh = gwseq_sport_vente_text($pdf, 0, 0, $widths[$col], $name, $size, 'B', false, GWSEQ_PDF_INK_DISPLAY, 'times');
    $bh = empty($label['breed']) ? 0 : gwseq_sport_vente_text($pdf, 0, 0, $widths[$col], $label['breed'], 8.5, '', false);
    if ($paint) {
      gwseq_sport_vente_text($pdf, $xs[$col], $cy - ($nh + $bh) / 2, $widths[$col], $name, $size, 'B', true, GWSEQ_PDF_INK_DISPLAY, 'times');
      if ($bh) gwseq_sport_vente_text($pdf, $xs[$col], $cy - ($nh + $bh) / 2 + $nh, $widths[$col], $label['breed'], 8.5, '', true, array(90, 95, 90));
    }
    return $nh + $bh;
  };
  $centers = array();
  $cursor = $y;
  foreach ($parents as $parent) {
    $ph = $node($parent['label'], 1, 0, false);
    $heights = array();
    foreach ($parent['children'] as $child) $heights[] = max(($compact ? 15 : 18) * $g3, $node($child, 2, 0, false) + ($compact ? 4 : 4.5) * $g3);
    $group_h = max($ph + ($compact ? 6 : 7) * $g3, array_sum($heights), ($compact ? 32 : 41) * $g3);
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
 * Ligne discrète « État ostéo-articulaire » (arbitrage client, §6) : réutilise la notation
 * structurée à 5 étoiles déjà introduite pour l'Étalon — jamais un texte libre concurrent créé pour
 * ce besoin, jamais une nouvelle donnée. Absente si non renseignée ; jamais un bloc titré, une
 * simple ligne dans la partie commerciale basse de la fiche, avant Conditions de vente.
 */
function gwseq_sport_vente_osteo_line($pdf, $x, $y, $w, $rating, $rgb, $draw) {
  if ($rating <= 0) return 0;
  $size = 3.2;
  $label = 'État ostéo-articulaire';
  $line_h = max($size, gwseq_sport_vente_text($pdf, 0, 0, $w, $label, 8.5, '', false));
  if ($draw) {
    $cx = $x + gwseq_horse_pdf_draw_star_rating($pdf, $x, $y + ($line_h - $size) / 2, $rating, $rgb, $size) + 3;
    gwseq_sport_vente_text($pdf, $cx, $y, $w - ($cx - $x), $label, 8.5, '', true, array(120, 124, 114));
  }
  return $line_h;
}

function gwseq_render_horse_pdf_template_sport_vente($pdf, $data) {
  $disable_credit = Closure::bind(function () { $this->tcpdflink = false; }, $pdf, 'TCPDF');
  $disable_credit();
  $old_padding = $pdf->getCellPaddings();
  $old_ratio = $pdf->getCellHeightRatio();
  $pdf->setCellPaddings(0, 0, 0, 0);
  $pdf->setCellHeightRatio(1.2);
  try {
    $rgb = gws_core_pdf_hex_to_rgb($data['structure']['primary_color']);
    $ink = gws_core_pdf_hex_to_rgb($data['structure']['primary_color_contrast']);
    // Étoiles accent-safe (état ostéo-articulaire) : voir gwseq_etalon_hero_identity().
    $accent_rgb = gws_core_pdf_hex_to_rgb(gws_core_pdf_accent_color($data['structure']['primary_color']));
    $x = 14;
    $w = $pdf->getPageWidth() - 28;
    $limit = $pdf->getPageHeight() - gwseq_sport_vente_footer($pdf, $data, false) - 4;
    $body_size = 10;
    $gap = 4;

    $origines = trim((string) ($data['editorial']['origines_commentaire'] ?? ''));
    $presentation = trim((string) ($data['editorial']['presentation'] ?? ''));
    $resultats = trim((string) ($data['editorial']['resultats'] ?? ''));
    $potentiel = trim((string) ($data['editorial']['potentiel'] ?? ''));
    $conditions = trim((string) ($data['editorial']['conditions_vente'] ?? ''));
    $osteo = (int) ($data['statut_osteo'] ?? 0);

    $block_h = function ($title, $body, $width, $size, $squeeze = 0) use ($pdf, $rgb) {
      return gwseq_sport_vente_section($pdf, 0, 0, $width, $title, $rgb, false, false, $squeeze) + gwseq_sport_vente_text($pdf, 0, 0, $width, $body, $size, '', false);
    };
    // Blocs séquentiels, chacun indépendamment optionnel — aucun n'est jamais forcé, aucun
    // emplacement n'est jamais réservé (arbitrage client, §9) : Origines -> Présentation ->
    // Résultats -> Potentiel, dans cet ordre métier.
    $sequential = array();
    if ($origines !== '') $sequential[] = array('ORIGINES', $origines);
    if ($presentation !== '') $sequential[] = array('PRÉSENTATION', $presentation);
    if ($resultats !== '') $sequential[] = array('RÉSULTATS', $resultats);
    if ($potentiel !== '') $sequential[] = array('POTENTIEL', $potentiel);

    // Recherche du budget le plus léger AVANT toute page 2 : voir gwseq_render_horse_pdf_template_etalon()
    // pour le détail de la démarche (correctif recette réelle Kado) — même mécanique dupliquée ici.
    // Contrairement à la Production de la Poulinière, aucun bloc ici n'est exempté de ce budget ni
    // tronqué pour rester sur 1 page : si un cas extrême l'impose réellement même au maximum de
    // compression, la pagination ($room()/$flow(), déjà validée) prend le relais plutôt qu'une
    // coupe silencieuse (arbitrage client, §9).
    // Ordre EXACT (aéré puis compact "de base", tous deux inchangés, puis compact+squeeze croissant
    // seulement si besoin) : voir gwseq_render_horse_pdf_template_etalon() pour le détail complet.
    $squeeze = 0;
    foreach (array(false, true) as $compact) {
      $body_size = $compact ? 9.5 : 10;
      $squeeze_levels = $compact ? array(0, 1, 2, 3, 4, 5) : array(0);
      foreach ($squeeze_levels as $squeeze) {
        $gap = ($compact ? 3 : 4) * ($squeeze >= 1 ? 0.8 : 1);
        $pdf->setCellHeightRatio($squeeze >= 5 ? 1.14 : 1.2);
        $hero_h = gwseq_sport_vente_hero($pdf, $data, $x, 24, $w, $rgb, $compact, false, 0, $squeeze);
        $tree_h = gwseq_sport_vente_tree($pdf, $data, $x, 0, $w, $rgb, $compact, false, $squeeze);
        $osteo_h = gwseq_sport_vente_osteo_line($pdf, 0, 0, $w, $osteo, $accent_rgb, false);
        $ch = $conditions !== '' ? $block_h('CONDITIONS DE VENTE', $conditions, $w - 8, $body_size, $squeeze) + 8 : 0;
        $total = 24 + $hero_h + $gap;
        if ($tree_h > 0) $total += $tree_h + $gap;
        foreach ($sequential as $block) $total += $block_h($block[0], $block[1], $w, $body_size, $squeeze) + $gap;
        if ($osteo_h > 0) $total += $osteo_h + $gap;
        if ($ch > 0) $total += $ch + $gap;
        // Marge de sécurité de 3 mm UNIQUEMENT pour $squeeze >= 1 : voir gwseq_render_horse_pdf_template_etalon().
        if ($total <= ($squeeze === 0 ? $limit : $limit - 3)) break 2;
      }
    }
    // Cas pauvre : même mécanisme d'agrandissement de la photo dominante que les masters
    // Étalon/Poulinière, sans effet dès que la moindre compression ($squeeze > 0) a été nécessaire.
    $extra_photo_h = 0;
    if (!$compact && $squeeze === 0) {
      $slack = $limit - $total;
      if ($slack > 8) {
        $extra_photo_h = min($slack - 4, 55);
        $boosted_hero_h = gwseq_sport_vente_hero($pdf, $data, $x, 24, $w, $rgb, $compact, false, $extra_photo_h, 0);
        if ($boosted_hero_h > $hero_h) {
          $hero_h = $boosted_hero_h;
          $gap += 1.5;
        } else {
          $extra_photo_h = 0;
        }
      }
    }
    gwseq_sport_vente_header($pdf, $data);
    $y = 24;
    $new_page = function () use ($pdf, $data, &$y) {
      gwseq_sport_vente_footer($pdf, $data);
      $pdf->AddPage();
      gwseq_sport_vente_header($pdf, $data, true);
      $y = 24;
    };
    $room = function ($h) use (&$y, $limit, $new_page) {
      if ($h > $limit - 24) throw new LengthException('Bloc Sport/Vente trop haut : vérifiez les noms ou les données de pedigree.');
      if ($y + $h > $limit) $new_page();
    };
    $room($hero_h);
    $y += gwseq_sport_vente_hero($pdf, $data, $x, $y, $w, $rgb, $compact, true, $extra_photo_h, $squeeze) + $gap;
    if ($tree_h > 0) {
      $room($tree_h);
      $y += gwseq_sport_vente_tree($pdf, $data, $x, $y, $w, $rgb, $compact, true, $squeeze) + $gap;
    }
    // Flux de secours : découpe mesurée, conserve chaque caractère, titre « suite » (duplication
    // volontaire du mécanisme des masters Étalon/Poulinière, blocs séquentiels non appariés).
    $flow = function ($title, $body, $colored = false, $size = null) use ($pdf, $x, $w, $rgb, $ink, $limit, $new_page, &$y, $gap, $body_size, $block_h, $squeeze) {
      $size = $size ?? $body_size;
      $pad = $colored ? 4 : 0;
      $tw = $w - 2 * $pad;
      $full_h = $block_h($title, $body, $tw, $size, $squeeze) + 2 * $pad;
      if ($y + $full_h > $limit && $full_h <= $limit - 24) $new_page();
      $continued = false;
      while ($body !== '') {
        $heading = $title . ($continued ? ' · SUITE' : '');
        $hh = gwseq_sport_vente_section($pdf, 0, 0, $tw, $heading, $rgb, false, false, $squeeze);
        $available = $limit - $y - $hh - 2 * $pad;
        $line_h = gwseq_sport_vente_text($pdf, 0, 0, $tw, 'Ag', $size, '', false);
        if ($available < 2 * $line_h) { $new_page(); continue; }
        $length = mb_strlen($body);
        $low = 0; $high = $length;
        while ($low < $high) {
          $mid = (int) ceil(($low + $high) / 2);
          $h = gwseq_sport_vente_text($pdf, 0, 0, $tw, mb_substr($body, 0, $mid), $size, '', false);
          if ($h <= $available) $low = $mid; else $high = $mid - 1;
        }
        if ($low < 1) throw new LengthException('Texte Sport/Vente impossible à composer.');
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
        $h = gwseq_sport_vente_text($pdf, 0, 0, $tw, $chunk, $size, '', false);
        if ($colored) {
          $pdf->SetFillColor($rgb[0], $rgb[1], $rgb[2]);
          $pdf->Rect($x, $y, $w, $hh + $h + 2 * $pad, 'F');
          gwseq_sport_vente_text($pdf, $x + $pad, $y + $pad, $tw, $heading, 11, 'B', true, $ink, 'times');
        } else gwseq_sport_vente_section($pdf, $x, $y, $w, $heading, $rgb, true, false, $squeeze);
        gwseq_sport_vente_text($pdf, $x + $pad, $y + $pad + $hh, $tw, $chunk, $size, '', true, $colored ? $ink : array(45, 49, 47));
        $y += $hh + $h + 2 * $pad + $gap;
        $body = mb_substr($body, $low);
        if ($body !== '') { $new_page(); $continued = true; }
      }
    };
    foreach ($sequential as $block) $flow($block[0], $block[1]);
    if ($osteo_h > 0) {
      $room($osteo_h);
      $y += gwseq_sport_vente_osteo_line($pdf, $x, $y, $w, $osteo, $accent_rgb, true) + $gap;
    }
    if ($conditions !== '') $flow('CONDITIONS DE VENTE', $conditions, true);
    gwseq_sport_vente_footer($pdf, $data);
    return true;
  } finally {
    $pdf->setCellPaddings($old_padding['L'], $old_padding['T'], $old_padding['R'], $old_padding['B']);
    $pdf->setCellHeightRatio($old_ratio);
  }
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

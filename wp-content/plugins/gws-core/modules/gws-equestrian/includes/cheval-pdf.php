<?php
/**
 * Fiche cheval PDF A4 (Lot "PDF Cheval & Catalogue", Lot 3A — audit + prototype de renderer).
 *
 * RENDERER PARTAGÉ (§15 de la demande) : gwseq_render_horse_pdf_page($pdf, $horse_id, $context)
 * dessine une fiche cheval complète sur la page COURANTE d'un document TCPDF déjà ouvert (voir
 * includes/pdf-engine.php de gws-core pour la création du document — moteur générique, aucune
 * connaissance du cheval). C'est la SEULE fonction de mise en page de fiche cheval de tout le
 * projet : le PDF individuel (gwseq_generate_horse_pdf() ci-dessous) l'appelle sur un document
 * neuf d'une page ; un futur Catalogue (Lot 3D/3E) l'appellera exactement de la même façon sur
 * une page ajoutée à un document partagé de plusieurs chevaux — jamais une seconde implémentation
 * de mise en page.
 *
 * DONNÉES CONSOMMÉES — AUCUNE DUPLICATION (§ toute la demande) : ce fichier n'accède JAMAIS
 * directement à une post meta. Toute donnée transite par les fonctions métier déjà existantes et
 * déjà testées ailleurs dans ce module (gwseq_get_cheval_identity(), gwseq_get_cheval_commercial(),
 * gwseq_get_cheval_editorial(), gwseq_get_cheval_sport_indice()/gwseq_get_cheval_genetic_indice(),
 * gwseq_resolve_horse_pedigree(), gwseq_get_horse_direct_production(),
 * gwseq_get_cheval_photo_principale_id()) et par gws_core_structure_identity() (gws-core,
 * includes/settings.php) pour tout ce qui est branding — jamais un champ de marque ré-inventé ou
 * dupliqué ici (§2 de la demande).
 *
 * $context (réservé, §15/§16) : tableau optionnel, actuellement seule la clé 'mode' est définie
 * ('standalone' — défaut, PDF individuel téléchargé seul — ou 'catalogue' — future intégration
 * Lot 3D/3E). Sans effet observable dans ce lot 3A (aucune branche de code ne teste encore
 * $context['mode']) : le paramètre existe dès maintenant pour que l'appel depuis un futur Catalogue
 * n'ait jamais à changer la signature de cette fonction, conformément à la demande explicite d'un
 * seul renderer partagé.
 */

if (!defined('ABSPATH')) exit;

/* -------------------------------------------------------------------------------------------
 * Assemblage des données — séparé du dessin (testable indépendamment de TCPDF, voir tests).
 * ----------------------------------------------------------------------------------------- */

/**
 * Rassemble TOUTES les données nécessaires au rendu d'une fiche cheval, dans une structure fermée
 * unique — jamais un accès direct à une fonction métier depuis les fonctions de dessin ci-dessous,
 * qui reçoivent uniquement ce tableau déjà assemblé (sépare "quelles données" de "comment les
 * dessiner", et rend cette étape testable sans TCPDF). $horse_id invalide (pas une vraie fiche
 * Cheval) -> retourne null, jamais un tableau à moitié rempli.
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

  $production = array();
  if (($identity['sexe'] ?? '') === 'female') {
    // Jamais de bloc Production structuré pour un mâle/hongre (§3/§8 de la demande) — la garde de
    // sexe est ICI, à l'assemblage, la même discipline que gwseq_get_horse_direct_production()
    // elle-même (qui retourne déjà un tableau vide pour un mâle) : défense en profondeur, jamais
    // fait confiance à une seule couche.
    $production = gwseq_get_horse_direct_production($horse_id);
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
    'pedigree' => gwseq_resolve_horse_pedigree($horse_id, 2), // 3 générations : sujet + parents + grands-parents (voir pedigree-resolver.php)
    'production' => $production,
    'structure' => gws_core_structure_identity(),
  );
}

/* -------------------------------------------------------------------------------------------
 * Production (§3/§8 de la demande, direction de design §8-9) — fonctions PURES, testables sans
 * TCPDF.
 * ----------------------------------------------------------------------------------------- */

/**
 * Meilleure valeur numérique parmi ISO/ICC/IDR d'une entrée de production (ou d'indices bruts) —
 * sert à la fois au tri de la Production (priorité aux produits les plus indicés, §8) et à la mise
 * en avant du meilleur indice sportif du cheval lui-même sur le hero (direction de design §4). null
 * si aucun indice n'est renseigné (produit "non indicé", trié en dernier).
 */
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

/**
 * Sélectionne et ordonne les produits à afficher (direction de design §8) : trie par meilleur
 * indice décroissant (produits non indicés en dernier, jamais mélangés arbitrairement — un tri
 * stable préserve l'ordre d'origine entre produits à égalité, y compris entre deux non-indicés).
 * Si $max est dépassé, retire d'ABORD les non-indicés, puis les indices les plus faibles — jamais
 * les petits-enfants (déjà structurellement absents de gwseq_get_horse_direct_production(), qui ne
 * retourne que la filiation DIRECTE) ni un tri qui perdrait un produit mieux indicé au profit d'un
 * moins bon.
 */
function gwseq_horse_pdf_select_production_entries($entries, $max) {
  if (!is_array($entries)) return array();
  $indexed = array();
  foreach ($entries as $i => $entry) {
    $indexed[] = array('entry' => $entry, 'score' => gwseq_horse_pdf_best_sport_value($entry), 'original_order' => $i);
  }
  usort($indexed, function ($a, $b) {
    if ($a['score'] === $b['score']) return $a['original_order'] <=> $b['original_order'];
    if ($a['score'] === null) return 1; // non-indicé toujours après un indicé, quelle que soit sa valeur
    if ($b['score'] === null) return -1;
    return $b['score'] <=> $a['score']; // décroissant : meilleur indice d'abord
  });
  $kept = array_slice($indexed, 0, max(0, (int) $max));
  // Ordre de présentation final (direction de design, exemples présentés par année croissante) :
  // remis dans l'ordre d'origine du document une fois la sélection par indice effectuée — le tri
  // par score ci-dessus ne sert qu'à DÉCIDER qui garder, jamais l'ordre d'affichage final.
  usort($kept, function ($a, $b) { return $a['original_order'] <=> $b['original_order']; });
  return array_map(function ($item) { return $item['entry']; }, $kept);
}

/**
 * Formate une ligne de Production (direction de design §8) : "Nom (Père) · Année · ISO Valeur" —
 * chaque segment absent (père inconnu, année inconnue, aucun indice sportif) est omis proprement,
 * jamais un fragment vide ("()", "· ·"...). Le MEILLEUR indice sportif seul est affiché par ligne
 * (jamais les trois, la ligne resterait compacte comme demandé) ; jamais le BLUP d'un produit
 * (§8/§3 : "ne jamais afficher le BLUP des produits" — cette fonction ne lit d'ailleurs même pas
 * les indices génétiques de l'entrée).
 */
function gwseq_horse_pdf_production_line($entry) {
  $parts = array();
  $parts[] = (string) ($entry['nom'] ?? '');
  if (($entry['pere'] ?? '') !== '') $parts[0] .= ' (' . $entry['pere'] . ')';

  $meta = array();
  if (($entry['annee'] ?? '') !== '') $meta[] = (string) $entry['annee'];

  $best_key = null;
  $best_value = null;
  foreach (array('iso', 'icc', 'idr') as $key) {
    $valeur = $entry[$key]['valeur'] ?? '';
    if ($valeur === '') continue;
    if ($best_value === null || (float) $valeur > $best_value) {
      $best_value = (float) $valeur;
      $best_key = $key;
    }
  }
  if ($best_key !== null) $meta[] = strtoupper($best_key) . ' ' . $entry[$best_key]['valeur'];

  $line = $parts[0];
  if ($meta) $line .= ' · ' . implode(' · ', $meta);
  return $line;
}

/* -------------------------------------------------------------------------------------------
 * Dessin — chips/badges, pedigree mini-arbre. Fonctions d'aide privées à ce renderer.
 * ----------------------------------------------------------------------------------------- */

/**
 * Dessine un chip (petit badge arrondi) à la position courante et retourne la largeur utilisée en
 * mm — direction de design §4/§5 : "pas de tableau administratif", indices/qualités présentés en
 * petits blocs, jamais une liste à puces ou un tableau quadrillé.
 */
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

/**
 * Enchaîne des chips horizontalement avec un espacement régulier, revenant à la ligne si la
 * largeur disponible ($max_w) est dépassée. Retourne la position Y après le dernier chip dessiné.
 */
function gwseq_horse_pdf_draw_chip_row($pdf, $x, $y, $max_w, $chips, $gap = 2.5, $line_gap = 2.2) {
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
 * Un nœud "affichable" du pedigree (nom + race le cas échéant) ou null — un ascendant non renseigné,
 * supprimé définitivement ("unavailable") ou en cycle n'est jamais affiché comme s'il s'agissait
 * d'une vraie donnée (même discipline que gwseq_render_pedigree_node_preview(), cheval-pedigree.php,
 * jamais un "Non renseigné" imprimé dans le PDF commercial — direction de design §6 : compact,
 * jamais un ascendant fantôme).
 */
function gwseq_horse_pdf_pedigree_node_label($node) {
  if (!is_array($node)) return null;
  if (!in_array($node['type'] ?? '', array('gws_horse', 'external'), true)) return null;
  $name = $node['name'] ?? '';
  if ($name === '') return null;
  return array('name' => $name, 'breed' => $node['breed'] ?? '');
}

/**
 * Réduit la taille de police (par pas de 0.5, jamais sous $min_size) jusqu'à ce que $text tienne
 * sur UNE SEULE ligne dans $max_w — plutôt qu'un retour à la ligne (§6 de la direction de design :
 * "compact", un nom d'ascendant qui se replie sur 2 lignes casserait l'alignement des générations
 * suivantes). Positionne $pdf sur la police finale ; l'appelant dessine ensuite avec Cell() (jamais
 * MultiCell ici). Si $text ne tient toujours pas à $min_size, reste à $min_size — TCPDF tronque
 * alors proprement via Cell(..., true) (voir gwseq_horse_pdf_draw_pedigree_label() ci-dessous),
 * jamais un débordement hors de sa colonne.
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
 * Dessine un nom d'ascendant sur une seule ligne, ajusté pour tenir dans $w (voir
 * gwseq_horse_pdf_fit_font_size() ci-dessus) — jamais un MultiCell qui pourrait wrapper sur 2
 * lignes et chevaucher la génération suivante (bug identifié pendant le prototype : "TELDAME DE LA
 * NUTRIA" à largeur fixe recouvrait la ligne de stud-book sous elle).
 */
function gwseq_horse_pdf_draw_pedigree_label($pdf, $x, $y, $w, $text, $rgb, $bold, $start_size, $min_size) {
  gwseq_horse_pdf_fit_font_size($pdf, $text, $w, $bold, $start_size, $min_size);
  $pdf->SetTextColor($rgb[0], $rgb[1], $rgb[2]);
  $pdf->SetXY($x, $y);
  // $stretch=1 (compression horizontale de la police SI nécessaire) : filet de sécurité si, malgré
  // la réduction de taille ci-dessus, le nom ne tient toujours pas à $min_size — jamais un
  // débordement visuel hors de sa colonne, même dans ce cas limite.
  $pdf->Cell($w, 4.4, $text, 0, 0, 'L', false, '', 1, true);
}

/**
 * Mini pedigree 3 générations (direction de design §6) : père/mère en évidence, grands-parents plus
 * petits/atténués, sujet jamais répété (déjà visible dans le hero). Chaque nom tient sur une seule
 * ligne (voir gwseq_horse_pdf_draw_pedigree_label() ci-dessus) — jamais de chevauchement entre un
 * nom long et la ligne suivante. Retourne la position Y après le bloc.
 */
function gwseq_horse_pdf_draw_pedigree($pdf, $x, $y, $w, $h, $pedigree, $primary_rgb) {
  $father = gwseq_horse_pdf_pedigree_node_label($pedigree['father'] ?? null);
  $mother = gwseq_horse_pdf_pedigree_node_label($pedigree['mother'] ?? null);
  if (!$father && !$mother) return $y;

  $row_h = $h / 4;
  $muted_rgb = array(110, 110, 110);
  $parent_col_w = $w * 0.4;
  $gp_col_x = $x + ($w * 0.42);
  $gp_col_w = $w - ($w * 0.42);

  $draw_grandparent = function ($node, $row_index) use ($pdf, $gp_col_x, $gp_col_w, $row_h, $y, $muted_rgb) {
    $label = gwseq_horse_pdf_pedigree_node_label($node);
    if (!$label) return;
    $gp_y = $y + ($row_h * $row_index) + ($row_h / 2) - 2.1;
    gwseq_horse_pdf_draw_pedigree_label($pdf, $gp_col_x, $gp_y, $gp_col_w, mb_strtoupper($label['name']), $muted_rgb, false, 7.5, 6);
  };

  $draw_parent = function ($node, $row_from, $row_to) use ($pdf, $x, $parent_col_w, $row_h, $y, $primary_rgb) {
    $label = gwseq_horse_pdf_pedigree_node_label($node);
    if (!$label) return;
    $p_y = $y + ($row_h * $row_from) + ($row_h * ($row_to - $row_from) / 2) - 2.6;
    gwseq_horse_pdf_draw_pedigree_label($pdf, $x, $p_y, $parent_col_w, mb_strtoupper($label['name']), $primary_rgb, true, 9, 7);
    if ($label['breed'] !== '') {
      $pdf->SetFont('helvetica', '', 6.8);
      $pdf->SetTextColor(140, 140, 140);
      $pdf->SetXY($x, $p_y + 4.4);
      $pdf->Cell($parent_col_w, 3.2, $label['breed'], 0, 0, 'L');
    }
  };

  $draw_grandparent($pedigree['father']['father'] ?? null, 0);
  $draw_grandparent($pedigree['father']['mother'] ?? null, 1);
  $draw_grandparent($pedigree['mother']['father'] ?? null, 2);
  $draw_grandparent($pedigree['mother']['mother'] ?? null, 3);
  $draw_parent($father ? ($pedigree['father'] ?? null) : null, 0, 2);
  $draw_parent($mother ? ($pedigree['mother'] ?? null) : null, 2, 4);

  return $y + $h;
}

/* -------------------------------------------------------------------------------------------
 * Renderer principal.
 * ----------------------------------------------------------------------------------------- */

/**
 * Dessine la fiche complète du cheval $horse_id sur la page COURANTE de $pdf (voir docblock de
 * fichier). Se replie proprement section par section quand une donnée manque (§18 de la demande) :
 * chaque bloc de dessin ci-dessous teste sa propre donnée et avance le curseur Y UNIQUEMENT s'il a
 * réellement dessiné quelque chose — jamais un espace vide laissé pour un bloc absent.
 */
function gwseq_render_horse_pdf_page($pdf, $horse_id, $context = array()) {
  $data = gwseq_build_horse_pdf_data($horse_id);
  if ($data === null) return false;

  $structure = $data['structure'];
  $primary_rgb = gws_core_pdf_hex_to_rgb($structure['primary_color']);
  $secondary_rgb = gws_core_pdf_hex_to_rgb($structure['secondary_color']);
  $primary_light_rgb = gws_core_pdf_lighten_color($structure['primary_color'], 0.88);
  $secondary_light_rgb = gws_core_pdf_lighten_color($structure['secondary_color'], 0.85);

  $margin = GWS_CORE_PDF_MARGIN_MM;
  $page_w = $pdf->getPageWidth();
  $content_w = $page_w - (2 * $margin);
  $y = $margin;

  // --- 1. Header (direction de design §2) : logo à gauche, identité structure à droite, filet
  // couleur principale en bas de zone — jamais un gros bandeau lourd. ---
  $header_h = 20;
  if ($structure['logo_url'] !== '' && $structure['logo_id']) {
    $logo_path = get_attached_file($structure['logo_id']);
    $logo_box = $logo_path ? gws_core_pdf_fit_image_box($logo_path, 30, $header_h - 2) : null;
    if ($logo_box) {
      $pdf->Image($logo_path, $margin, $y, $logo_box['w'], $logo_box['h']);
    }
  }
  $pdf->SetFont('helvetica', 'B', 11);
  gws_core_pdf_set_text_color($pdf, $structure['primary_color']);
  $pdf->SetXY($margin, $y + 1);
  $pdf->Cell($content_w, 5, $structure['name'], 0, 2, 'R');
  $header_meta = array_values(array_filter(array($structure['website_url'], trim($structure['city'] . ($structure['country'] !== '' ? ' · ' . $structure['country'] : '')))));
  if ($header_meta) {
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetTextColor(120, 120, 120);
    $pdf->SetXY($margin, $y + 6.5);
    $pdf->Cell($content_w, 4, implode('  ·  ', $header_meta), 0, 0, 'R');
  }
  gws_core_pdf_set_draw_color($pdf, $structure['primary_color']);
  $pdf->SetLineWidth(0.5);
  $pdf->Line($margin, $y + $header_h, $margin + $content_w, $y + $header_h);
  $y += $header_h + 6;

  // --- 2. Hero (direction de design §3) : photo à gauche (~58%), identité à droite (~42%). ---
  $hero_h = 82;
  $photo_w = $content_w * 0.58;
  $identity_x = $margin + $photo_w + 6;
  $identity_w = $content_w - $photo_w - 6;

  if ($data['photo_path'] !== '' && is_readable($data['photo_path'])) {
    $box = gws_core_pdf_fit_image_box($data['photo_path'], $photo_w, $hero_h);
    if ($box) {
      $ox = $margin + (($photo_w - $box['w']) / 2);
      $oy = $y + (($hero_h - $box['h']) / 2);
      $pdf->Image($data['photo_path'], $ox, $oy, $box['w'], $box['h']);
    }
  } else {
    // Fallback sobre (§19 de la demande, "fallback si aucune image") : cadre discret, jamais une
    // icône criarde ni un espace blanc qui donnerait l'impression d'un défaut d'affichage.
    $pdf->SetFillColor($primary_light_rgb[0], $primary_light_rgb[1], $primary_light_rgb[2]);
    $pdf->Rect($margin, $y, $photo_w, $hero_h, 'F');
  }

  $id_y = $y;
  $pdf->SetFont('helvetica', 'B', 24);
  $pdf->SetTextColor($primary_rgb[0], $primary_rgb[1], $primary_rgb[2]);
  $pdf->SetXY($identity_x, $id_y);
  $pdf->MultiCell($identity_w, 10, mb_strtoupper($data['name']), 0, 'L', false, 1);
  $id_y = $pdf->GetY() + 1;

  $subline = array_values(array_filter(array($data['sexe_label'], $data['identity']['annee_naissance'] ?: '', $data['race_label'], $data['robe_label'])));
  if ($subline) {
    $pdf->SetFont('helvetica', '', 10.5);
    $pdf->SetTextColor(70, 70, 70);
    $pdf->SetXY($identity_x, $id_y);
    $pdf->MultiCell($identity_w, 5, implode(' · ', $subline), 0, 'L', false, 1);
    $id_y = $pdf->GetY() + 1;
  }
  if (($data['identity']['taille_cm'] ?? '') !== '') {
    $pdf->SetFont('helvetica', '', 9.5);
    $pdf->SetTextColor(120, 120, 120);
    $pdf->SetXY($identity_x, $id_y);
    $pdf->Cell($identity_w, 4.5, number_format(((float) $data['identity']['taille_cm']) / 100, 2, ',', '') . ' m', 0, 1, 'L');
    $id_y = $pdf->GetY() + 2;
  } else {
    $id_y += 2;
  }

  // Statut commercial + prix (direction de design §3).
  $statut = $data['commercial']['statut_commercial'] ?? 'not_offered';
  if ($statut !== 'not_offered') {
    $statut_label = gwseq_cheval_statut_commercial_options()[$statut] ?? '';
    if ($statut_label !== '') {
      gwseq_horse_pdf_draw_chip($pdf, $identity_x, $id_y, mb_strtoupper($statut_label), $secondary_rgb, array(255, 255, 255), 8.5, true);
      $id_y += 8.5;
    }
  }
  if ($data['price_summary'] !== '') {
    $pdf->SetFont('helvetica', 'B', 15);
    gws_core_pdf_set_text_color($pdf, $structure['secondary_color']);
    $pdf->SetXY($identity_x, $id_y);
    $pdf->Cell($identity_w, 7, $data['price_summary'], 0, 1, 'L');
    $id_y = $pdf->GetY() + 2;
  }

  // Indices sportifs + BLUP (direction de design §4) — mêmes chips, sport en avant (couleur
  // principale ou mise en évidence pour le meilleur), génétique/BLUP en secondaire.
  $chips = array();
  $best_sport = gwseq_horse_pdf_best_sport_value($data['sport_indices']);
  foreach ($data['sport_indices'] as $key => $indice) {
    $is_best = $best_sport !== null && (float) $indice['valeur'] === $best_sport;
    $chips[] = array(
      'text' => strtoupper($key) . ' ' . $indice['valeur'],
      'bg' => $is_best ? $primary_rgb : $primary_light_rgb,
      'color' => $is_best ? array(255, 255, 255) : $primary_rgb,
      'bold' => $is_best,
    );
  }
  foreach ($data['genetic_indices'] as $key => $indice) {
    $chips[] = array(
      'text' => strtoupper($key) . ' ' . gwseq_cheval_genetic_indice_label($indice['valeur'], ''),
      'bg' => $secondary_light_rgb,
      'color' => $secondary_rgb,
    );
  }
  if ($chips) {
    $id_y = gwseq_horse_pdf_draw_chip_row($pdf, $identity_x, $id_y, $identity_w, $chips) + 2;
  }

  // Qualités (direction de design §5) : tags sobres, 5 maximum (déjà garanti côté saisie).
  if ($data['qualites']) {
    $quality_chips = array();
    foreach (array_slice($data['qualites'], 0, 5) as $qualite) {
      $quality_chips[] = array('text' => $qualite, 'bg' => array(240, 240, 240), 'color' => array(80, 80, 80), 'font_size' => 7.5);
    }
    $id_y = gwseq_horse_pdf_draw_chip_row($pdf, $identity_x, $id_y, $identity_w, $quality_chips) + 1;
  }

  $y += $hero_h + 8;

  // --- 3. Faits marquants (direction de design §5) : 1 à 3 lignes courtes, filet vertical. ---
  if ($data['faits_marquants']) {
    gws_core_pdf_set_draw_color($pdf, $structure['secondary_color']);
    $pdf->SetLineWidth(0.8);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor(60, 60, 60);
    foreach (array_slice($data['faits_marquants'], 0, 3) as $fait) {
      $pdf->Line($margin, $y + 0.5, $margin, $y + 4.5);
      $pdf->SetXY($margin + 3, $y);
      $pdf->Cell($content_w - 3, 5, $fait, 0, 1, 'L');
      $y += 5.5;
    }
    $y += 3;
  }

  // --- 4. Pedigree + Présentation, deux colonnes (direction de design §6/§7). ---
  $col_gap = 8;
  $pedigree_w = $content_w * 0.46;
  $presentation_w = $content_w - $pedigree_w - $col_gap;
  $presentation_x = $margin + $pedigree_w + $col_gap;
  $block_h = 46;
  $block_top = $y;
  $has_pedigree = !empty($data['pedigree']['father']) || !empty($data['pedigree']['mother']);
  $presentation_text = trim((string) ($data['editorial']['presentation'] ?? ''));

  if ($has_pedigree) {
    $pdf->SetFont('helvetica', 'B', 9.5);
    gws_core_pdf_set_text_color($pdf, $structure['primary_color']);
    $pdf->SetXY($margin, $block_top);
    $pdf->Cell($pedigree_w, 5, mb_strtoupper('Pedigree'), 0, 1, 'L');
    gwseq_horse_pdf_draw_pedigree($pdf, $margin, $block_top + 6, $pedigree_w, $block_h - 6, $data['pedigree'], $primary_rgb);
  }
  if ($presentation_text !== '') {
    $pdf->SetFont('helvetica', 'B', 9.5);
    gws_core_pdf_set_text_color($pdf, $structure['primary_color']);
    $pdf->SetXY($presentation_x, $block_top);
    $pdf->Cell($presentation_w, 5, mb_strtoupper('Présentation'), 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor(60, 60, 60);
    $pdf->SetXY($presentation_x, $block_top + 6);
    $pdf->MultiCell($presentation_w, 4.4, $presentation_text, 0, 'L', false, 1);
  }
  $y = ($has_pedigree || $presentation_text !== '') ? max($block_top + $block_h, $pdf->GetY() + 2) : $block_top;

  // --- 5. Production (jument uniquement, direction de design §8/§9) — jamais pour mâle/hongre
  // (déjà garanti à l'assemblage, $data['production'] structurellement vide dans ce cas). ---
  if ($data['production']) {
    $pdf->SetFont('helvetica', 'B', 9.5);
    gws_core_pdf_set_text_color($pdf, $structure['primary_color']);
    $pdf->SetXY($margin, $y);
    $pdf->Cell($content_w, 5, mb_strtoupper('Production'), 0, 1, 'L');
    $y = $pdf->GetY() + 1;

    $commentaire = trim((string) ($data['editorial']['commentaire_production'] ?? ''));
    if ($commentaire !== '') {
      $pdf->SetFont('helvetica', 'I', 8.5);
      $pdf->SetTextColor(90, 90, 90);
      $pdf->SetXY($margin, $y);
      $pdf->MultiCell($content_w, 4.2, $commentaire, 0, 'L', false, 1);
      $y = $pdf->GetY() + 1.5;
    }

    // Budget de lignes conservateur pour rester sur une page (§ V1 "privilégier le contenu
    // essentiel" de la direction de design, §7) — jamais les petits-enfants (structurellement
    // absents en amont), jamais le BLUP des produits (gwseq_horse_pdf_production_line() ne le lit
    // même pas).
    $max_entries = 8;
    $selected = gwseq_horse_pdf_select_production_entries($data['production'], $max_entries);
    $pdf->SetFont('helvetica', '', 8.5);
    $pdf->SetTextColor(60, 60, 60);
    $pdf->SetDrawColor(225, 225, 225);
    foreach ($selected as $entry) {
      $pdf->SetXY($margin, $y);
      $pdf->Cell($content_w, 4.6, gwseq_horse_pdf_production_line($entry), 0, 1, 'L');
      $pdf->Line($margin, $y + 4.8, $margin + $content_w, $y + 4.8);
      $y += 5.4;
    }
    $y += 2;
  }

  // --- 6. Identifiants officiels (discrets, direction de design §10) ---
  $ids = array();
  if (($data['identity']['sire'] ?? '') !== '') $ids[] = 'SIRE : ' . $data['identity']['sire'];
  if (($data['identity']['ueln'] ?? '') !== '') $ids[] = 'UELN : ' . $data['identity']['ueln'];
  if (($data['identity']['eleveur'] ?? '') !== '') $ids[] = 'Naisseur : ' . $data['identity']['eleveur'];
  if (($data['identity']['proprietaire'] ?? '') !== '') $ids[] = 'Propriétaire : ' . $data['identity']['proprietaire'];
  if ($ids) {
    $pdf->SetFont('helvetica', '', 7.5);
    $pdf->SetTextColor(140, 140, 140);
    $pdf->SetXY($margin, $y);
    $pdf->Cell($content_w, 4, implode('   ·   ', $ids), 0, 1, 'L');
    $y = $pdf->GetY() + 1;
  }

  // --- 7. Footer structure (direction de design §11), ancré en bas de page, jamais empilé après
  // le contenu (position absolue depuis le bas — reste lisible même si le contenu ci-dessus est
  // court, jamais un footer "flottant" au milieu d'une page presque vide). ---
  $footer_y = $pdf->getPageHeight() - $margin - 8;
  gws_core_pdf_set_draw_color($pdf, $structure['primary_color']);
  $pdf->SetLineWidth(0.3);
  $pdf->Line($margin, $footer_y, $margin + $content_w, $footer_y);
  $footer_parts = array_values(array_filter(array(
    $structure['name'],
    $structure['website_url'],
    $structure['public_email'],
    $structure['phone_display'],
  )));
  if ($footer_parts) {
    $pdf->SetFont('helvetica', '', 7.5);
    $pdf->SetTextColor(130, 130, 130);
    $pdf->SetXY($margin, $footer_y + 1.6);
    $pdf->Cell($content_w, 4, implode('   ·   ', $footer_parts), 0, 0, 'C');
  }

  return true;
}

/* -------------------------------------------------------------------------------------------
 * Orchestration — document complet à une page (PDF individuel, §16 de la demande).
 * ----------------------------------------------------------------------------------------- */

/**
 * Génère un document PDF complet (une page) pour $horse_id — jamais enregistré sur disque de façon
 * permanente par cette fonction (§16, "génération à la demande") : retourne l'instance TCPDF,
 * l'appelant choisit lui-même le mode de sortie ($pdf->Output('nom.pdf', 'D') pour un
 * téléchargement navigateur, 'S' pour obtenir les octets, 'F' pour un fichier temporaire —
 * ifce-import-admin.php n'a d'ailleurs jamais fait autrement pour le PDF IFCE téléversé : jamais
 * conservé au-delà de son traitement). Retourne null si $horse_id est invalide ou si la
 * bibliothèque PDF n'est pas disponible (voir gws_core_pdf_available()) — jamais un fatal error.
 */
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

<?php
/**
 * Import IFCE — extraction de texte POSITIONNÉ (x, y) pour la Zone Production (Lot 2B.2).
 *
 * ZONE STRICTEMENT SÉPARÉE DE LA ZONE SUJET (audits 2B.1/2B.1 bis/2B.1 ter) : ce fichier ne modifie
 * JAMAIS `gwseq_ifce_pdf_extract_structured_text()` (ifce-pdf-text.php, page 1 uniquement, X jamais
 * conservé) — il réutilise SANS LES DUPLIQUER les primitives bas niveau déjà éprouvées de ce même
 * fichier (index d'objets, résolution de police, décompression de flux), mais ajoute un chemin de
 * décodage entièrement distinct qui (a) conserve la coordonnée X de chaque ligne, indispensable pour
 * distinguer un produit direct d'un petit-enfant, et (b) décode TOUTES les pages du document — la
 * Zone Production peut se trouver n'importe où après la page 1 et se poursuivre sur plusieurs pages
 * (confirmé sur Teldame de la Nutria : pages 16 puis 17, sans second titre "Production").
 *
 * Ces deux fonctions ne sont JAMAIS utilisées par le pipeline Sujet existant, et réciproquement.
 */

if (!defined('ABSPATH')) exit;

/**
 * Variante positionnée de gwseq_ifce_pdf_decode_content_stream_lines() (ifce-pdf-text.php) : conserve
 * la coordonnée X de départ de chaque ligne en plus du texte — jamais utilisée par le pipeline Sujet,
 * qui n'a besoin que du texte. Retourne un tableau de {x, y, text}, dans l'ordre de lecture du flux.
 */
function gwseq_ifce_pdf_decode_content_stream_positioned_lines($stream, $font_name_to_objnum, $plain, $compressed, &$font_cache) {
  $lines = array();
  $current_line = '';
  $line_x = null;
  $line_y = null;
  $last_y = null;
  $current_font = array('bytes' => 1, 'map' => gwseq_ifce_pdf_winansi_table());

  preg_match_all(
    '/1\s+0\s+0\s+1\s+([\d.]+)\s+([\d.]+)\s+cm|\/(\w+)\s+[\d.]+\s+Tf|\((?:\\\\.|[^\\\\()])*\)\s*Tj|\[(?:\\\\.|[^\[\]])*\]\s*TJ/',
    $stream,
    $tokens,
    PREG_SET_ORDER
  );

  foreach ($tokens as $tok) {
    $full = $tok[0];

    if (isset($tok[2]) && $tok[2] !== '') { // "1 0 0 1 X Y cm"
      $x = round((float) $tok[1], 1);
      $y = round((float) $tok[2], 1);
      if ($last_y !== null && abs($y - $last_y) > GWSEQ_IFCE_PDF_LINE_Y_TOLERANCE) {
        $lines[] = array('x' => $line_x, 'y' => $line_y, 'text' => trim($current_line));
        $current_line = '';
        $line_x = null;
      }
      if ($line_x === null) { $line_x = $x; $line_y = $y; }
      $last_y = $y;
      continue;
    }

    if (isset($tok[3]) && $tok[3] !== '') { // "/Fx size Tf"
      if (isset($font_name_to_objnum[$tok[3]])) {
        $current_font = gwseq_ifce_pdf_resolve_font($font_name_to_objnum[$tok[3]], $plain, $compressed, $font_cache);
      }
      continue;
    }

    $raw_strings = array();
    if (substr($full, -2) === 'Tj') {
      $open = strpos($full, '(');
      $close = strrpos($full, ')');
      if ($open !== false && $close !== false && $close > $open) {
        $raw_strings[] = gwseq_ifce_decode_pdf_literal_string(substr($full, $open + 1, $close - $open - 1));
      }
    } else { // TJ
      preg_match_all('/\((?:\\\\.|[^\\\\()])*\)/', $full, $strs);
      foreach ($strs[0] as $s) $raw_strings[] = gwseq_ifce_decode_pdf_literal_string(substr($s, 1, -1));
    }

    foreach ($raw_strings as $raw) {
      if ($current_font['bytes'] === 2) {
        for ($i = 0; $i + 1 < strlen($raw); $i += 2) {
          $code = (ord($raw[$i]) << 8) | ord($raw[$i + 1]);
          $current_line .= $current_font['map'][$code] ?? '?';
        }
      } else {
        for ($i = 0; $i < strlen($raw); $i++) {
          $code = ord($raw[$i]);
          $current_line .= $current_font['map'][$code] ?? '';
        }
      }
    }
  }

  if (trim($current_line) !== '') $lines[] = array('x' => $line_x, 'y' => $line_y, 'text' => trim($current_line));
  return $lines;
}

/**
 * Décode TOUTES les pages du document (jamais seulement la première — voir la note en tête de
 * fichier) en lignes positionnées {x, y, text}, une liste par page, dans l'ordre physique des objets
 * `/Type/Page` (même convention que gwseq_ifce_pdf_extract_structured_text(), déjà validée réelle sur
 * tous les documents IFCE testés). Une page sans contenu exploitable produit simplement une liste
 * vide — jamais une erreur, jamais une interruption des pages suivantes.
 */
function gwseq_ifce_pdf_extract_all_pages_positioned_lines($data) {
  $plain = gwseq_ifce_pdf_find_plain_objects($data);
  $compressed = gwseq_ifce_pdf_expand_object_streams($plain);
  $pages = gwseq_ifce_pdf_find_pages($plain, $compressed);
  if (empty($pages)) return array();

  $font_cache = array();
  $result = array();

  foreach ($pages as $page_body) {
    if (!preg_match('/\/Contents\s+(\d+)\s+0\s+R/', $page_body, $m)) { $result[] = array(); continue; }
    $content_body = gwseq_ifce_pdf_get_object_body((int) $m[1], $plain, $compressed);
    if ($content_body === null) { $result[] = array(); continue; }
    $split = gwseq_ifce_pdf_split_stream($content_body);
    if ($split === null) { $result[] = array(); continue; }
    $stream = gwseq_ifce_pdf_decompress_stream($split['dict'], $split['raw']);
    if ($stream === null) { $result[] = array(); continue; }

    $font_map = gwseq_ifce_pdf_page_font_map($page_body);
    $result[] = gwseq_ifce_pdf_decode_content_stream_positioned_lines($stream, $font_map, $plain, $compressed, $font_cache);
  }

  return $result;
}

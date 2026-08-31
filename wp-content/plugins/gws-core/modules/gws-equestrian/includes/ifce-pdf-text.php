<?php
/**
 * Import IFCE — extraction de texte brut depuis un PDF (Étape 7, §3/§11 de la demande).
 *
 * RÉÉCRITURE POST-RECETTE (Étape 7, recette runtime avec le vrai PDF IFCE de Jamerose de Félines) :
 * la première version de ce fichier (extraction naïve : chaque chaîne littérale `(...)` d'un
 * opérateur `Tj`/`TJ` était considérée comme du texte déjà en clair) rejetait la VRAIE fiche IFCE
 * — jamais reconnue comme document IFCE. Diagnostic complet effectué AVANT tout correctif (voir
 * ci-dessous « CAUSE EXACTE »), confirmant que l'extracteur précédent n'était objectivement PAS
 * capable de traiter ce type de PDF — pas un problème de reconnaissance trop stricte côté parseur.
 *
 * CAUSE EXACTE DE L'ÉCHEC (diagnostic détaillé, PDF réel produit par iText 2.1.7/BIRT) :
 * 1. **Objets compressés (`/Type/ObjStm`)** : la quasi-totalité des dictionnaires structurels du
 *    PDF (Pages, Ressources, dictionnaires de police) sont stockés à l'intérieur d'un flux d'objets
 *    compressé (mécanisme PDF 1.5+, très répandu chez les générateurs modernes), jamais comme
 *    objets `N 0 obj` classiques directement visibles dans le fichier. L'ancien extracteur ne
 *    lisait QUE les objets classiques : il ne pouvait donc jamais découvrir la police utilisée par
 *    un texte donné, ni son éventuelle table `/ToUnicode`.
 * 2. **Polices composites CID (`/Type0`, encodage `/Identity-H`)** : le texte du corps de la fiche
 *    est dessiné avec une police "Marianne" (police officielle de l'État) intégrée et sous-jeu
 *    (subset), en encodage Identity-H — chaque code affiché par `Tj` est un identifiant de glyphe
 *    interne à CE sous-jeu de police, PAS un octet ASCII/Latin-1. Sans la table `/ToUnicode`
 *    associée (qui, elle, EXISTE bien dans ce PDF — object plein, hors ObjStm), ces codes ne
 *    peuvent PAS être interprétés comme du texte : l'ancien extracteur les recopiait tels quels,
 *    produisant des octets sans rapport avec le texte réel (jamais “IFCE”, jamais “Femelle”...),
 *    d'où l'échec de la reconnaissance du document (§10).
 * 3. **Absence de sauts de ligne explicites** : ce générateur ne positionne JAMAIS le texte avec
 *    `Td`/`TD`/`T*` (les opérateurs que l'ancienne version traitait comme des sauts de ligne) — CHAQUE
 *    fragment de texte est positionné indépendamment par une matrice de transformation absolue
 *    (`cm`). Même une fois le texte correctement décodé, une reconstruction de ligne fiable exige de
 *    suivre la coordonnée Y de chaque fragment, pas les opérateurs `Td`/`TD`/`T*` (inexistants ici).
 *
 * SOLUTION RETENUE (pas un assouplissement de la reconnaissance — une extraction réellement
 * correcte) :
 * 1. Un index d'objets couvrant à la fois les objets classiques ET les objets compressés dans
 *    chaque flux `/Type/ObjStm` du fichier (`gwseq_ifce_pdf_build_object_index()`).
 * 2. Localisation des objets `/Type/Page`, résolution de leur `/Contents` et de leur
 *    `/Resources/Font` (association nom de police "/F1"... -> objet de police).
 * 3. Résolution de chaque police utilisée : `/Type0` + `/ToUnicode` -> table de correspondance
 *    code (2 octets, Identity-H) -> caractère Unicode, construite en analysant le CMap ToUnicode
 *    (`beginbfchar`/`beginbfrange`) ; police simple -> table WinAnsiEncoding standard (1 octet).
 * 4. Reconstruction de ligne par changement de coordonnée Y (issue des matrices `cm`/`Tm`) entre
 *    fragments consécutifs, plutôt que par les opérateurs `Td`/`TD`/`T*` — validé empiriquement
 *    contre le vrai PDF de Jamerose (voir `tests/fixtures/ifce-jamerose-de-felines.pdf` et
 *    `tests/gws-equestrian-ifce-import-test.php`).
 *
 * RÉSULTAT SUR LE VRAI PDF DE JAMEROSE (voir le CR de livraison pour le détail complet) : identité,
 * indices (avec CD) et les 14 ascendants du pedigree sont désormais tous extraits et reconnus
 * correctement — validés par un test automatisé partant du fichier PDF réel lui-même (et non plus
 * d'un texte pré-extrait), exécutant exactement le même pipeline que le runtime WordPress.
 *
 * LIMITES RÉSIDUELLES ASSUMÉES (voir CHANGELOG.md / README.md du module pour le détail) :
 * - Un seul niveau de flux d'objets compressés est résolu (pas de flux d'objets imbriqués les uns
 *   dans les autres) — suffisant pour tous les générateurs PDF usuels rencontrés.
 * - Les dictionnaires `/Resources` hérités d'un ancêtre `/Pages` (plutôt que portés directement par
 *   la page) ne sont pas résolus — chaque page du PDF réel testé porte directement ses propres
 *   `/Resources`, cas le plus courant chez les générateurs de rapports (iText/BIRT, wkhtmltopdf...).
 * - Le CMap ToUnicode ne gère que les caractères du plan multilingue de base (BMP) sans
 *   décodage manuel des paires de substitution UTF-16 lorsque l'extension `mbstring` (native à
 *   WordPress) est indisponible — cas résiduel très rare, jamais rencontré sur le PDF réel testé.
 * - Une police sans `/ToUnicode` et qui n'est ni du texte simple ASCII/WinAnsi reste décodée en
 *   repli par simple passage des octets (comportement de l'ancienne version) — un caractère "?"
 *   remplace un code Identity-H non résolu plutôt que produire un octet trompeur.
 *
 * Fonction métier pure : ne touche jamais $_POST, ne connaît rien du CPT Cheval ni de ses meta —
 * seul point d'entrée : gwseq_ifce_extract_pdf_text($file_path) / _from_string($binary).
 */

if (!defined('ABSPATH')) exit;

/* -------------------------------------------------------------------------------------------
 * Décodage bas niveau : chaînes littérales PDF, points de code Unicode.
 * ----------------------------------------------------------------------------------------- */

/**
 * Décode une chaîne littérale PDF `(...)` (contenu déjà sans les parenthèses englobantes) selon
 * les règles d'échappement de la spécification PDF — retourne des OCTETS BRUTS (1 ou 2 octets par
 * caractère affiché selon la police, voir plus bas), jamais encore un décodage d'encodage de
 * police à ce stade.
 */
function gwseq_ifce_decode_pdf_literal_string($raw) {
  return preg_replace_callback('/\\\\([nrtbf()\\\\]|[0-7]{1,3}|\r\n|\r|\n)/', function ($m) {
    $esc = $m[1];
    switch ($esc) {
      case 'n': return "\n";
      case 'r': return "\r";
      case 't': return "\t";
      case 'b': return "\x08";
      case 'f': return "\x0C";
      case '(': return '(';
      case ')': return ')';
      case '\\': return '\\';
      case "\r\n":
      case "\r":
      case "\n":
        return ''; // retour à la ligne échappé dans la source PDF : simple continuation, aucun caractère
      default:
        return chr(octdec($esc) % 256);
    }
  }, $raw);
}

/**
 * Encode un point de code Unicode en UTF-8 — utilisé comme repli si l'extension `mbstring` (native
 * à WordPress, mais on évite d'en faire une dépendance stricte) est indisponible. Se limite au plan
 * multilingue de base (BMP), largement suffisant pour du texte en français (voir limite documentée
 * en tête de fichier).
 */
function gwseq_ifce_pdf_codepoint_to_utf8($codepoint) {
  if ($codepoint < 0x80) return chr($codepoint);
  if ($codepoint < 0x800) return chr(0xC0 | ($codepoint >> 6)) . chr(0x80 | ($codepoint & 0x3F));
  return chr(0xE0 | ($codepoint >> 12)) . chr(0x80 | (($codepoint >> 6) & 0x3F)) . chr(0x80 | ($codepoint & 0x3F));
}

/**
 * Convertit une chaîne hexadécimale UTF-16BE (telle qu'utilisée dans un CMap ToUnicode) en UTF-8.
 */
function gwseq_ifce_pdf_hex_utf16be_to_utf8($hex) {
  $bytes = @hex2bin($hex);
  if ($bytes === false) return '';
  if (function_exists('mb_convert_encoding')) {
    $decoded = @mb_convert_encoding($bytes, 'UTF-8', 'UTF-16BE');
    if ($decoded !== false) return $decoded;
  }
  $out = '';
  for ($i = 0; $i + 1 < strlen($bytes); $i += 2) {
    $out .= gwseq_ifce_pdf_codepoint_to_utf8((ord($bytes[$i]) << 8) | ord($bytes[$i + 1]));
  }
  return $out;
}

/**
 * Table WinAnsiEncoding standard (Annexe D de la spécification PDF) pour les polices simples (1
 * octet par caractère) sans `/ToUnicode` — 0x20-0x7E identique à l'ASCII, 0xA0-0xFF identique à
 * Latin-1/ISO-8859-1, et la zone 0x80-0x9F spécifique à WinAnsi (guillemets typographiques, tiret
 * demi-cadratin, Œ/œ, Euro...) qui DIFFÈRE de Latin-1 sur cette plage précise.
 */
function gwseq_ifce_pdf_winansi_table() {
  static $table = null;
  if ($table !== null) return $table;
  $table = array();
  for ($i = 0x20; $i <= 0x7E; $i++) $table[$i] = chr($i);
  $special = array(
    0x80 => "\xE2\x82\xAC", 0x82 => "\xE2\x80\x9A", 0x83 => "\xC6\x92", 0x84 => "\xE2\x80\x9E",
    0x85 => "\xE2\x80\xA6", 0x86 => "\xE2\x80\xA0", 0x87 => "\xE2\x80\xA1", 0x88 => "\xCB\x86",
    0x89 => "\xE2\x80\xB0", 0x8A => "\xC5\xA0", 0x8B => "\xE2\x80\xB9", 0x8C => "\xC5\x92",
    0x8E => "\xC5\xBD", 0x91 => "\xE2\x80\x98", 0x92 => "\xE2\x80\x99", 0x93 => "\xE2\x80\x9C",
    0x94 => "\xE2\x80\x9D", 0x95 => "\xE2\x80\xA2", 0x96 => "\xE2\x80\x93", 0x97 => "\xE2\x80\x94",
    0x98 => "\xCB\x9C", 0x99 => "\xE2\x84\xA2", 0x9A => "\xC5\xA1", 0x9B => "\xE2\x80\xBA",
    0x9C => "\xC5\x93", 0x9E => "\xC5\xBE", 0x9F => "\xC5\xB8",
  );
  foreach ($special as $code => $utf8) $table[$code] = $utf8;
  for ($i = 0xA0; $i <= 0xFF; $i++) $table[$i] = gwseq_ifce_pdf_codepoint_to_utf8($i);
  return $table;
}

/* -------------------------------------------------------------------------------------------
 * Index d'objets PDF : objets classiques ET objets compressés dans un flux `/Type/ObjStm`.
 * ----------------------------------------------------------------------------------------- */

/**
 * Sépare le dictionnaire d'un flux de son contenu brut, à partir du corps d'un objet (texte entre
 * "N 0 obj" et "endobj"). Retourne null si l'objet ne porte pas de flux.
 */
function gwseq_ifce_pdf_split_stream($object_body) {
  if (!preg_match('/(<<.*?>>)\s*stream\r?\n(.*?)\r?\nendstream/s', $object_body, $m)) return null;
  return array('dict' => $m[1], 'raw' => $m[2]);
}

/**
 * Décompresse un flux annoncé `/FlateDecode` (tolère un octet de fin de ligne superflu avant
 * "endstream", certains générateurs n'étant pas stricts) ; laisse les autres flux tels quels.
 * Retourne null si la décompression échoue (flux corrompu — jamais une erreur fatale).
 */
function gwseq_ifce_pdf_decompress_stream($dict, $raw) {
  if (stripos($dict, '/FlateDecode') === false) return $raw;
  $decompressed = @gzuncompress($raw);
  if ($decompressed === false) $decompressed = @gzuncompress(rtrim($raw, "\r\n"));
  return $decompressed === false ? null : $decompressed;
}

/**
 * Étape 1 : repère chaque objet PDF classique ("N 0 obj ... endobj") du fichier. Ne gère pas les
 * dictionnaires imbriqués au-delà de ce que `(<<.*?>>)` peut capturer paresseusement — suffisant
 * pour retrouver le dictionnaire de chaque flux, la seule utilisation qui en est faite ici.
 */
function gwseq_ifce_pdf_find_plain_objects($data) {
  $plain = array();
  if (preg_match_all('/(\d+)\s+0\s+obj(.*?)endobj/s', $data, $matches, PREG_SET_ORDER)) {
    foreach ($matches as $m) $plain[(int) $m[1]] = $m[2];
  }
  return $plain;
}

/**
 * Étape 2 : développe chaque flux d'objets compressés (`/Type/ObjStm`, mécanisme PDF 1.5+ — voir
 * la cause exacte documentée en tête de fichier) trouvé parmi les objets classiques, en un tableau
 * plat numéro d'objet -> corps d'objet (dictionnaire seul : un objet compressé ne porte jamais son
 * propre flux, par construction de la spécification PDF). Format du flux : un en-tête de N paires
 * "numéro décalage" (les N premiers octets, longueur donnée par `/First`), suivi des corps d'objet
 * concaténés à partir de l'offset `/First`.
 */
function gwseq_ifce_pdf_expand_object_streams($plain_objects) {
  $compressed = array();
  foreach ($plain_objects as $body) {
    if (stripos($body, '/Type/ObjStm') === false && stripos($body, '/Type /ObjStm') === false) continue;
    $split = gwseq_ifce_pdf_split_stream($body);
    if ($split === null) continue;
    $stream = gwseq_ifce_pdf_decompress_stream($split['dict'], $split['raw']);
    if ($stream === null) continue;

    if (!preg_match('/\/N\s+(\d+)/', $split['dict'], $mn)) continue;
    if (!preg_match('/\/First\s+(\d+)/', $split['dict'], $mf)) continue;
    $first = (int) $mf[1];

    $header = substr($stream, 0, $first);
    preg_match_all('/(\d+)\s+(\d+)/', trim($header), $pairs, PREG_SET_ORDER);
    $offsets = array();
    foreach ($pairs as $p) $offsets[(int) $p[1]] = (int) $p[2];
    asort($offsets);
    $object_numbers = array_keys($offsets);
    foreach ($object_numbers as $i => $objnum) {
      $start = $first + $offsets[$objnum];
      $end = isset($object_numbers[$i + 1]) ? $first + $offsets[$object_numbers[$i + 1]] : strlen($stream);
      if ($end > $start) $compressed[$objnum] = substr($stream, $start, $end - $start);
    }
  }
  return $compressed;
}

function gwseq_ifce_pdf_get_object_body($objnum, $plain, $compressed) {
  if (isset($plain[$objnum])) return $plain[$objnum];
  if (isset($compressed[$objnum])) return $compressed[$objnum];
  return null;
}

/**
 * Repère chaque objet `/Type/Page` (jamais `/Type/Pages`, le nœud de l'arbre) parmi les objets
 * classiques ET compressés — pas besoin de parcourir l'arbre `/Root -> /Pages -> /Kids` : chaque
 * page du PDF réel testé porte directement son propre `/Contents` et `/Resources` (voir limite
 * documentée en tête de fichier pour le cas d'un `/Resources` hérité d'un ancêtre).
 */
function gwseq_ifce_pdf_find_pages($plain, $compressed) {
  $pages = array();
  foreach ($plain + $compressed as $objnum => $body) {
    if (preg_match('/\/Type\s*\/Page\b(?!s)/', $body)) $pages[$objnum] = $body;
  }
  return $pages;
}

/* -------------------------------------------------------------------------------------------
 * Résolution des polices : Identity-H + ToUnicode (2 octets/code), ou police simple (1 octet).
 * ----------------------------------------------------------------------------------------- */

/**
 * Analyse un CMap ToUnicode (`beginbfchar`/`beginbfrange`) en table code -> caractère UTF-8. Gère
 * la forme `<lo><hi><dst>` (plage linéaire, incrémentée caractère par caractère) et la forme
 * `<lo><hi>[<d1><d2>...]` (liste explicite) de `beginbfrange`, ainsi que `beginbfchar` (paires
 * simples) — les trois formes documentées par la spécification PDF pour ce type de CMap.
 */
function gwseq_ifce_pdf_parse_tounicode_cmap($cmap_text) {
  $map = array();

  if (preg_match_all('/beginbfchar(.*?)endbfchar/s', $cmap_text, $blocks)) {
    foreach ($blocks[1] as $block) {
      preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>/', $block, $pairs, PREG_SET_ORDER);
      foreach ($pairs as $p) $map[hexdec($p[1])] = gwseq_ifce_pdf_hex_utf16be_to_utf8($p[2]);
    }
  }

  if (preg_match_all('/beginbfrange(.*?)endbfrange/s', $cmap_text, $blocks)) {
    foreach ($blocks[1] as $block) {
      $remaining = $block;
      preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>\s*\[(.*?)\]/s', $block, $array_form, PREG_SET_ORDER);
      foreach ($array_form as $p) {
        $start_code = hexdec($p[1]);
        preg_match_all('/<([0-9A-Fa-f]+)>/', $p[3], $items);
        foreach ($items[1] as $i => $hex) $map[$start_code + $i] = gwseq_ifce_pdf_hex_utf16be_to_utf8($hex);
        $remaining = str_replace($p[0], '', $remaining);
      }
      preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>/', $remaining, $linear_form, PREG_SET_ORDER);
      foreach ($linear_form as $p) {
        $lo = hexdec($p[1]);
        $hi = hexdec($p[2]);
        $dst_start = hexdec($p[3]);
        for ($code = $lo; $code <= $hi; $code++) {
          $map[$code] = gwseq_ifce_pdf_codepoint_to_utf8($dst_start + ($code - $lo));
        }
      }
    }
  }

  return $map;
}

/**
 * Résout une police (objet `/Type/Font`) vers {bytes, map} : `bytes` est la largeur d'un code
 * affiché (2 pour une police composite Identity-H, 1 pour une police simple), `map` la table
 * code -> caractère UTF-8 (ToUnicode pour une police composite, WinAnsiEncoding pour une police
 * simple — jamais null pour ces deux cas connus ; reste null seulement pour une police composite
 * SANS ToUnicode, cas résiduel non résolvable — voir limite documentée en tête de fichier).
 */
function gwseq_ifce_pdf_resolve_font($fontnum, $plain, $compressed, &$font_cache) {
  if (isset($font_cache[$fontnum])) return $font_cache[$fontnum];

  $body = gwseq_ifce_pdf_get_object_body($fontnum, $plain, $compressed);
  $info = array('bytes' => 1, 'map' => gwseq_ifce_pdf_winansi_table());

  if ($body !== null && strpos($body, '/Type0') !== false) {
    $info['bytes'] = 2;
    $info['map'] = null;
    if (preg_match('/\/ToUnicode\s+(\d+)\s+0\s+R/', $body, $m)) {
      $tounicode_body = gwseq_ifce_pdf_get_object_body((int) $m[1], $plain, $compressed);
      $split = $tounicode_body !== null ? gwseq_ifce_pdf_split_stream($tounicode_body) : null;
      if ($split !== null) {
        $cmap_text = gwseq_ifce_pdf_decompress_stream($split['dict'], $split['raw']);
        if ($cmap_text !== null) $info['map'] = gwseq_ifce_pdf_parse_tounicode_cmap($cmap_text);
      }
    }
  }

  $font_cache[$fontnum] = $info;
  return $info;
}

/* -------------------------------------------------------------------------------------------
 * Décodage d'une page : reconstruction de ligne par coordonnée Y, texte par police active.
 * ----------------------------------------------------------------------------------------- */

/**
 * Tolérance (en unités PDF, 1/72 pouce) sous laquelle deux fragments de texte consécutifs sont
 * considérés comme faisant partie de la MÊME ligne visuelle malgré une coordonnée Y légèrement
 * différente (arrondis internes du générateur). Calibrée empiriquement contre le vrai PDF IFCE de
 * Jamerose de Félines (voir `tests/fixtures/`) : un écart réel de ligne à ligne y est TOUJOURS
 * supérieur à plusieurs unités, jamais aussi fin.
 */
const GWSEQ_IFCE_PDF_LINE_Y_TOLERANCE = 0.5;

/**
 * Décode le texte d'UN flux de contenu déjà décompressé, en reconstruisant les lignes par
 * changement de coordonnée Y plutôt que par les opérateurs `Td`/`TD`/`T*` (ce générateur ne les
 * utilise jamais — voir la cause exacte documentée en tête de fichier) : suit la police active via
 * `/Fx taille Tf`, et la position via `1 0 0 1 X Y cm` (la forme de matrice systématiquement émise
 * par ce type de générateur pour positionner chaque fragment de texte).
 */
function gwseq_ifce_pdf_decode_content_stream_lines($stream, $font_name_to_objnum, $plain, $compressed, &$font_cache) {
  $lines = array();
  $current_line = '';
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
      $y = round((float) $tok[2], 1);
      if ($last_y !== null && abs($y - $last_y) > GWSEQ_IFCE_PDF_LINE_Y_TOLERANCE) {
        $lines[] = trim($current_line);
        $current_line = '';
      }
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

  if (trim($current_line) !== '') $lines[] = trim($current_line);
  return $lines;
}

/**
 * Extrait, pour une page donnée (corps de l'objet `/Type/Page`), la correspondance nom de police
 * (tel qu'utilisé par `Tf` dans son flux de contenu) -> numéro d'objet de police, depuis son
 * `/Resources/Font`.
 */
function gwseq_ifce_pdf_page_font_map($page_body) {
  $map = array();
  if (preg_match('/\/Font\s*<<(.*?)>>/s', $page_body, $m)) {
    preg_match_all('/\/(\w+)\s+(\d+)\s+0\s+R/', $m[1], $pairs, PREG_SET_ORDER);
    foreach ($pairs as $p) $map[$p[1]] = (int) $p[2];
  }
  return $map;
}

/**
 * Pipeline structuré complet : index d'objets -> pages -> flux de contenu décodé ligne par ligne
 * avec la bonne police. Retourne null (jamais une chaîne vide silencieuse) si aucune page
 * exploitable n'a été trouvée, pour laisser `gwseq_ifce_extract_pdf_text_from_string()` basculer
 * sur le repli.
 *
 * SEULE LA PREMIÈRE PAGE est décodée (§3 : « il est acceptable de n'exploiter que la zone de
 * synthèse principale, en particulier la première page ») — CHOIX DÉLIBÉRÉ, pas seulement une
 * limite de commodité : sur le vrai PDF IFCE de Jamerose de Félines, les pages suivantes
 * contiennent le détail de la PRODUCTION de chaque ascendant (hors périmètre V1, §14), avec ses
 * propres indices ISO/BSO pour d'AUTRES chevaux — les inclure ferait remonter les indices d'un
 * autre cheval que celui de la fiche de synthèse, une erreur d'interprétation bien plus grave
 * qu'une simple donnée manquante. L'ordre des objets `/Type/Page` dans le fichier (tel qu'ils
 * apparaissent physiquement, sans reconstruction de l'arbre `/Pages`) correspond à l'ordre réel des
 * pages pour tout générateur PDF linéaire usuel (confirmé sur le document réel testé) : le premier
 * objet Page trouvé est bien la page 1.
 */
function gwseq_ifce_pdf_extract_structured_text($data) {
  $plain = gwseq_ifce_pdf_find_plain_objects($data);
  $compressed = gwseq_ifce_pdf_expand_object_streams($plain);
  $pages = gwseq_ifce_pdf_find_pages($plain, $compressed);
  if (empty($pages)) return null;

  $first_page_body = reset($pages);
  if (!preg_match('/\/Contents\s+(\d+)\s+0\s+R/', $first_page_body, $m)) return null;
  $content_body = gwseq_ifce_pdf_get_object_body((int) $m[1], $plain, $compressed);
  if ($content_body === null) return null;
  $split = gwseq_ifce_pdf_split_stream($content_body);
  if ($split === null) return null;
  $stream = gwseq_ifce_pdf_decompress_stream($split['dict'], $split['raw']);
  if ($stream === null) return null;

  $font_cache = array();
  $font_map = gwseq_ifce_pdf_page_font_map($first_page_body);
  $lines = gwseq_ifce_pdf_decode_content_stream_lines($stream, $font_map, $plain, $compressed, $font_cache);

  if (empty($lines)) return null;
  return implode("\n", $lines);
}

/* -------------------------------------------------------------------------------------------
 * Repli (ancien comportement) : utilisé uniquement si le pipeline structuré ci-dessus ne trouve
 * aucune page exploitable (ex. un PDF minimal sans arbre de pages complet, comme celui construit
 * par les tests pour valider isolément la mécanique de décompression/déséchappement).
 * ----------------------------------------------------------------------------------------- */

function gwseq_ifce_extract_text_from_content_stream($content) {
  $output = '';
  preg_match_all('/\((?:\\\\.|[^\\\\()])*\)\s*Tj|\[(?:\\\\.|[^\[\]])*\]\s*TJ|\bT\*|\bTd\b|\bTD\b/s', $content, $tokens);

  foreach ($tokens[0] as $token) {
    if (substr($token, -2) === 'Tj') {
      $open = strpos($token, '(');
      $close = strrpos($token, ')');
      if ($open !== false && $close !== false && $close > $open) {
        $output .= gwseq_ifce_decode_pdf_literal_string(substr($token, $open + 1, $close - $open - 1));
      }
    } elseif (substr($token, -2) === 'TJ') {
      preg_match_all('/\((?:\\\\.|[^\\\\()])*\)/', $token, $strings);
      foreach ($strings[0] as $s) {
        $output .= gwseq_ifce_decode_pdf_literal_string(substr($s, 1, -1));
      }
    } else {
      $output .= "\n"; // Td / TD / T*
    }
  }

  return $output;
}

function gwseq_ifce_extract_pdf_text_fallback($pdf_binary) {
  $text = '';
  if (!preg_match_all('/<<([^<>]*)>>\s*stream\r?\n(.*?)\r?\nendstream/s', $pdf_binary, $matches, PREG_SET_ORDER)) {
    return '';
  }
  foreach ($matches as $match) {
    $decompressed = gwseq_ifce_pdf_decompress_stream($match[1], $match[2]);
    if ($decompressed === null) continue;
    $text .= gwseq_ifce_extract_text_from_content_stream($decompressed) . "\n";
  }
  return trim($text);
}

/* -------------------------------------------------------------------------------------------
 * Points d'entrée publics (signatures inchangées).
 * ----------------------------------------------------------------------------------------- */

function gwseq_ifce_extract_pdf_text_from_string($pdf_binary) {
  if (!is_string($pdf_binary) || $pdf_binary === '') return '';
  if (strpos($pdf_binary, '%PDF-') === false) return '';

  $structured = gwseq_ifce_pdf_extract_structured_text($pdf_binary);
  if ($structured !== null && trim($structured) !== '') return trim($structured);

  return gwseq_ifce_extract_pdf_text_fallback($pdf_binary);
}

/**
 * Point d'entrée fichier : lit $file_path et délègue à la fonction pure ci-dessus. Retourne une
 * chaîne vide si le fichier est illisible ou ne contient aucun texte exploitable — jamais une
 * erreur PHP, le code appelant (gwseq_ifce_parse_text()) traite déjà l'absence de contenu comme un
 * document non reconnu (§10).
 */
function gwseq_ifce_extract_pdf_text($file_path) {
  if (!is_string($file_path) || $file_path === '' || !is_readable($file_path)) return '';
  $data = file_get_contents($file_path);
  if ($data === false) return '';
  return gwseq_ifce_extract_pdf_text_from_string($data);
}

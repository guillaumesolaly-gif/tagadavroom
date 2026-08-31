<?php
/**
 * Import IFCE — extraction de texte brut depuis un PDF (Étape 7, §3/§11 de la demande).
 *
 * Aucune dépendance Composer/npm n'existe dans ce projet et aucun accès réseau n'est disponible
 * pour installer une bibliothèque PDF tierce (ex. pdftotext, Smalot/PdfParser). Ce fichier
 * implémente donc un extracteur minimal, en PHP pur, suffisant pour la zone de synthèse
 * principale d'une fiche IFCE (§3 : « il est acceptable de n'exploiter que la zone de synthèse
 * principale, en particulier la première page ») :
 *
 * 1. Localise les blocs `stream ... endstream` du fichier PDF (format objet PDF standard) ;
 * 2. Décompresse chaque flux annoncé `/FlateDecode` via `gzuncompress()` (zlib natif PHP, aucune
 *    extension supplémentaire requise — le filtre FlateDecode de la spécification PDF est
 *    précisément un flux zlib/RFC1950, le même format que celui produit par `gzcompress()`) ;
 * 3. Repère les opérateurs de dessin de texte du langage de contenu PDF (`Tj`, `TJ`, et les
 *    opérateurs de positionnement `Td`/`TD`/`T*` traités comme des sauts de ligne) et décode les
 *    chaînes littérales `(...)` selon les règles d'échappement PDF (`\n`, `\r`, `\t`, `\b`, `\f`,
 *    `\(`, `\)`, `\\`, et les séquences octales `\ddd`).
 *
 * LIMITATIONS ASSUMÉES ET DOCUMENTÉES (voir CHANGELOG.md / README.md du module pour le détail
 * destiné à l'utilisateur) :
 * - Aucun décodage des tables d'encodage de police (WinAnsiEncoding, Identity-H/CID, ToUnicode) :
 *   les caractères accentués d'un PDF réel utilisant un encodage de police non trivial peuvent
 *   être mal extraits. C'est précisément pour cette raison que la prévisualisation (§9) reste
 *   obligatoire avant toute écriture — jamais un import silencieux.
 * - Les dictionnaires d'objet PDF imbriqués (`<<...<<...>>...>>`) ne sont pas gérés : seuls des
 *   dictionnaires "simples" précédant `stream` sont reconnus. Suffisant pour les flux de contenu
 *   de page d'un PDF généré par un outil standard (le cas des fiches IFCE visées), pas pour des
 *   structures PDF avancées (flux d'objets compressés, PDF chiffrés...).
 * - N'a PAS pu être validé contre un PDF IFCE réel (aucun accès réseau pour en télécharger un) —
 *   validé uniquement contre un PDF minimal auto-généré pour les tests de ce module (voir
 *   tests/gws-equestrian-ifce-import-test.php).
 *
 * Fonction métier pure : ne touche jamais $_POST, ne connaît rien du CPT Cheval ni de ses meta —
 * seul point d'entrée : gwseq_ifce_extract_pdf_text($file_path) / _from_string($binary).
 */

if (!defined('ABSPATH')) exit;

/**
 * Décode une chaîne littérale PDF `(...)` (contenu déjà sans les parenthèses englobantes) selon
 * les règles d'échappement de la spécification PDF — jamais un décodage d'encodage de police
 * (voir la limitation documentée en tête de fichier).
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
 * Extrait le texte affiché par un flux de contenu PDF déjà décompressé : repère chaque `(...) Tj`,
 * chaque `[...] TJ` (dont seules les chaînes littérales comptent, les décalages numériques entre
 * elles sont ignorés — suffisant pour reconstituer le texte, pas le positionnement précis), et
 * traite `Td`/`TD`/`T*` comme un saut de ligne (nouvelle ligne de texte dans le flux de contenu).
 */
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
      // Td / TD / T* : nouvelle ligne dans le flux de contenu
      $output .= "\n";
    }
  }

  return $output;
}

/**
 * Localise chaque bloc `stream ... endstream` du PDF et son dictionnaire d'objet immédiatement
 * précédent (non imbriqué — voir la limitation documentée en tête de fichier), décompresse ceux
 * annoncés `/FlateDecode`, laisse les autres tels quels (flux déjà en texte clair — rare pour du
 * contenu réel, mais pas exclu pour un PDF minimal).
 */
function gwseq_ifce_extract_pdf_text_from_string($pdf_binary) {
  if (!is_string($pdf_binary) || $pdf_binary === '') return '';
  if (strpos($pdf_binary, '%PDF-') === false) return '';

  $text = '';
  if (!preg_match_all('/<<([^<>]*)>>\s*stream\r?\n(.*?)\r?\nendstream/s', $pdf_binary, $matches, PREG_SET_ORDER)) {
    return '';
  }

  foreach ($matches as $match) {
    $dict = $match[1];
    $stream = $match[2];

    if (stripos($dict, '/FlateDecode') !== false) {
      $decompressed = @gzuncompress($stream);
      if ($decompressed === false) {
        // Tolérance à un octet de fin de ligne superflu avant "endstream" (writers non stricts).
        $decompressed = @gzuncompress(rtrim($stream, "\r\n"));
      }
      if ($decompressed === false) continue; // flux corrompu/non supporté : ignoré, jamais une erreur fatale
      $stream = $decompressed;
    }

    $text .= gwseq_ifce_extract_text_from_content_stream($stream) . "\n";
  }

  return trim($text);
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

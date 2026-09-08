<?php
/**
 * Import IFCE — reconnaissance de la Zone Production et extraction des produits directs d'une
 * jument (Lot 2B.2, audits 2B.1/2B.1 bis/2B.1 ter).
 *
 * PÉRIMÈTRE MÉTIER STRICT : la Production structurée ne concerne QUE les juments — fils et filles
 * DIRECTS uniquement, jamais les petits-enfants ni au-delà (§1-2 de la demande). L'appelant
 * (ifce-import-admin.php) est seul responsable de la garde de sexe AVANT même d'appeler ce fichier
 * (§5 : la Zone Production n'est ni recherchée ni décodée pour un sujet mâle/hongre — garde de
 * performance ET garde métier au même endroit) ; ce fichier reste néanmoins une fonction pure,
 * réutilisable indépendamment de ce contrôle.
 *
 * DEUX SIGNAUX DE HIÉRARCHIE, jamais un seuil X absolu (audits 2B.1/2B.1 bis/2B.1 ter) :
 * 1. SIGNAL PRIMAIRE : une vraie entrée de Production commence TOUJOURS par une année à 4 chiffres —
 *    une ligne de continuation (nom/pedigree replié sur la ligne suivante) n'en porte jamais.
 * 2. SIGNAL SECONDAIRE : parmi les lignes qui commencent par une année, la coordonnée X, CALCULÉE
 *    RELATIVEMENT AU DOCUMENT COURANT (jamais une constante globale — Nacelle et Teldame utilisent
 *    des paliers totalement différents), sépare le niveau 1 (produits directs, le X le plus petit
 *    observé dans la Zone Production) des niveaux 2+ (petits-enfants, exclus). Teldame a révélé une
 *    variation de quelques dixièmes d'unité PDF entre deux lignes du MÊME palier (20.2 à 20.6) :
 *    GWSEQ_IFCE_PRODUCTION_X_TOLERANCE regroupe ces variations sans jamais fusionner deux paliers
 *    réellement différents (l'écart Nacelle niveau 1 -> niveau 2, comme celui de Teldame, est
 *    toujours largement supérieur à cette tolérance).
 *
 * TROISIÈME RÈGLE, découverte sur Teldame (2B.1 ter) : une ligne "AAAAsaillie par [ÉTALON]" commence
 * elle aussi par une année, parfois au même palier X qu'un produit réel — mais annonce une saillie
 * en cours (gestation), jamais une naissance. Exclue par mot-clé, à N'IMPORTE QUELLE profondeur.
 *
 * PRODUITS SANS NOM (règle métier arrêtée, §9) : un produit totalement dépourvu de nom OU
 * d'identifiant provisoire (ex. "QZ") n'est jamais importé. Un identifiant provisoire, lui, EST
 * importable (traité comme un nom comme un autre). Jamais un nom inventé, jamais un identifiant
 * artificiel construit pour combler une entrée anonyme.
 */

if (!defined('ABSPATH')) exit;

/**
 * Tolérance de regroupement par X (unités PDF, 1/72 pouce) — voir la note en tête de fichier.
 * Calibrée sur la variation réellement observée à profondeur identique (Teldame, 20.2 à 20.6, soit
 * 0.4) avec une marge, très en-deçà du plus petit écart niveau 1 -> niveau 2 réellement rencontré
 * (Nacelle comme Teldame : plusieurs unités au minimum) — jamais une constante de profondeur, une
 * simple tolérance de bruit de positionnement.
 */
const GWSEQ_IFCE_PRODUCTION_X_TOLERANCE = 1.5;

/**
 * Titre de section recherché tel quel (même convention que "Pedigree" côté Zone Sujet,
 * gwseq_ifce_find_pedigree_heading_index()) — une ligne réduite EXACTEMENT à ce mot, jamais une
 * correspondance partielle qui risquerait de capturer une occurrence du mot ailleurs dans le texte.
 */
function gwseq_ifce_production_heading_matches($text) {
  return (bool) preg_match('/^production$/iu', trim((string) $text));
}

/**
 * Repère la Zone Production dans les pages positionnées déjà décodées par
 * gwseq_ifce_pdf_extract_all_pages_positioned_lines() (Lot 2B.2, §6) : recherche du titre
 * "Production" à partir de la DEUXIÈME page (jamais la page 1, strictement réservée à la Zone Sujet
 * — indépendance totale entre les deux zones, cf. la note d'anti-contamination du 2B.1 bis).
 * Une fois trouvé, retourne TOUTES les lignes qui suivent CE titre sur sa page, PUIS l'intégralité
 * de chaque page suivante jusqu'à la fin du document — sans jamais exiger un second titre
 * "Production" (Teldame : page 16 -> 17 sans répétition, §6) ni imposer de numéro de page maximum.
 * Retourne un tableau plat de lignes {x, y, text} en ordre de lecture, vide si le titre n'a jamais
 * été trouvé (absence de section : état valide, jamais une erreur — §6).
 */
function gwseq_ifce_production_zone_lines($all_pages_positioned_lines) {
  $zone_lines = array();
  $heading_found = false;

  foreach ($all_pages_positioned_lines as $page_index => $page_lines) {
    if ($page_index === 0) continue; // page 1 : Zone Sujet exclusivement, jamais scannée ici

    if (!$heading_found) {
      $heading_line_index = null;
      foreach ($page_lines as $i => $line) {
        if (gwseq_ifce_production_heading_matches($line['text'])) { $heading_line_index = $i; break; }
      }
      if ($heading_line_index === null) continue; // titre pas encore trouvé sur cette page
      $heading_found = true;
      for ($i = $heading_line_index + 1; $i < count($page_lines); $i++) $zone_lines[] = $page_lines[$i];
      continue;
    }

    // Titre déjà trouvé sur une page précédente : la page entière poursuit la Zone Production.
    foreach ($page_lines as $line) $zone_lines[] = $line;
  }

  return $heading_found ? $zone_lines : array();
}

/**
 * Regroupe les lignes de la Zone Production en entrées candidates : une entrée commence à CHAQUE
 * ligne débutant par une année à 4 chiffres (signal primaire), toute ligne suivante qui n'en porte
 * pas est une continuation visuelle (texte replié) rattachée à l'entrée en cours, QUELLE QUE SOIT sa
 * propre coordonnée X (ex. Teldame : "...DIAMANT DE" à x=20.6 puis "SEMILLY sfa" à x=42.7 — la
 * seconde ligne n'est pas un petit-enfant, seulement la suite du nom du père replié). Les lignes sans
 * texte exploitable (colonnes vides, en-têtes "Année"/"Nom") sont ignorées, qu'elles interrompent ou
 * non une entrée en cours. Retourne un tableau de {x, text}, dans l'ordre de lecture du document.
 */
function gwseq_ifce_production_group_candidate_entries($zone_lines) {
  $entries = array();
  $current = null;

  foreach ($zone_lines as $line) {
    $text = trim((string) ($line['text'] ?? ''));
    if ($text === '') continue;

    if (preg_match('/^\d{4}/', $text)) {
      if ($current !== null) $entries[] = $current;
      $current = array('x' => $line['x'], 'text' => $text);
    } elseif ($current !== null) {
      $current['text'] .= ' ' . $text;
    }
    // une ligne non vide rencontrée AVANT la toute première entrée (résidu d'en-tête) est ignorée
  }
  if ($current !== null) $entries[] = $current;

  return $entries;
}

/**
 * Une ligne "AAAAsaillie par [ÉTALON]" (annonce de gestation en cours, jamais une naissance — voir
 * la note en tête de fichier) — insensible à la casse et aux variations d'espacement issues de la
 * reconstruction PDF (ex. plusieurs espaces entre l'année collée et le mot "saillie").
 */
function gwseq_ifce_production_entry_is_saillie($entry_text_without_year) {
  return (bool) preg_match('/^\s*saillie\b/iu', (string) $entry_text_without_year);
}

/**
 * Nettoie un segment de nom (produit ou père) déjà isolé : retire un marqueur pays IFCE reconnu
 * (même liste fermée que la Zone Sujet, gwseq_ifce_strip_country_markers() — jamais dupliquée ici),
 * puis un unique code de stud-book/qualificatif final en MINUSCULES ("oes", "sf", "ri", "kwpn",
 * "westf"...) — convention réellement observée sur Nacelle et Teldame : un nom de produit IFCE est
 * toujours en MAJUSCULES (apostrophes/chiffres compris), seul ce qui le SUIT en minuscules est un
 * code technique, jamais une partie du nom lui-même. Défaut assumé si cette convention ne tenait pas
 * sur un futur document : le code reste simplement collé au nom (donnée moins propre, jamais une
 * perte de donnée).
 */
function gwseq_ifce_production_clean_name_segment($text) {
  $text = gwseq_ifce_strip_country_markers((string) $text);
  // Un code de stud-book peut être composé de PLUSIEURS mots en minuscules ("selle francais",
  // "belgian warmblood") — retire toute la SUITE finale de mots en minuscules, jamais un seul mot
  // isolé qui laisserait la moitié du code collée au nom.
  $text = preg_replace('/(?:\s+[a-z][a-z\x27.]{0,10})+$/u', '', $text);
  return trim(preg_replace('/\s+/', ' ', (string) $text));
}

/**
 * Analyse le texte d'une entrée (année déjà retirée) en {nom, pere} — voir la note en tête de fichier
 * pour les deux formes réellement rencontrées :
 * - avec nom : "NOM [STUDBOOK], sexe robe de PERE [STUDBOOK] [par GRAND-PERE...]" — le nom est tout
 *   ce qui précède la première virgule (frontière structurelle fiable, jamais ambiguë même quand le
 *   nom contient lui-même "DE", ex. "KINGSLEY DE REUX") ; le père suit "de " jusqu'à un éventuel
 *   " par " (le père DU père, hors périmètre ici) ou la fin ;
 * - sans nom (poulain sans identification, ex. Teldame 2026) : "sexe par PERE [STUDBOOK] [par
 *   GRAND-PERE...]" — aucune virgule, aucun "de", le père suit directement le premier " par ".
 * Un identifiant provisoire (ex. "QZ") est traité comme un nom à part entière — voir §9 : seule une
 * entrée dont AUCUN texte n'est isolable avant la virgule (ou qui n'a pas de virgule du tout) reste
 * sans nom, et c'est alors à l'appelant (jamais ici) de décider de l'exclure.
 */
function gwseq_ifce_parse_production_entry_text($entry_text_without_year) {
  $rest = trim((string) $entry_text_without_year);
  $nom = '';
  $pere = '';

  $comma_pos = strpos($rest, ',');
  if ($comma_pos !== false) {
    $name_segment = trim(substr($rest, 0, $comma_pos));
    $after_comma = trim(substr($rest, $comma_pos + 1));
    $nom = gwseq_ifce_production_clean_name_segment($name_segment);
    if (preg_match('/\bde\s+(.+?)(?:\s+par\s+.*)?$/iu', $after_comma, $m)) {
      $pere = gwseq_ifce_production_clean_name_segment(trim($m[1]));
    }
  } elseif (preg_match('/\bpar\s+(.+?)(?:\s+par\s+.*)?$/iu', $rest, $m)) {
    $pere = gwseq_ifce_production_clean_name_segment(trim($m[1]));
  }

  return array('nom' => $nom, 'pere' => $pere);
}

/**
 * Indices sportifs (ISO/ICC/IDR) d'UNE entrée, à partir de son propre texte accumulé uniquement —
 * réutilise SANS LA DUPLIQUER gwseq_ifce_parse_indices_from_text() (ifce-import-parser.php, même
 * expression régulière déjà validée sur la Zone Sujet), dont seules les clés sportives sont
 * conservées ici : les indices génétiques (BSO/BCC/BDR) d'un produit ne sont JAMAIS importés (§11 —
 * BSO/BCC/BDR ne concernent que le sujet lui-même, dans sa propre Zone Sujet, pipeline inchangé).
 */
function gwseq_ifce_parse_production_entry_indices($entry_text) {
  $all = gwseq_ifce_parse_indices_from_text($entry_text);
  $result = array();
  foreach (gwseq_cheval_sport_indice_keys() as $key) {
    $result[$key] = $all[$key];
  }
  return $result;
}

/**
 * Point d'entrée unique du Lot 2B.2 : extrait la Production directe (niveau 1 uniquement) d'un
 * document IFCE déjà lu en octets bruts ($pdf_binary) — jamais le texte déjà extrait de la Zone
 * Sujet, jamais partagé avec elle (§4 de la demande). Retourne toujours une structure fermée :
 * {found, entries, ignored: {saillie, sans_nom, niveau_superieur}} — 'found' à false signifie
 * "aucune section Production dans ce document", un état valide et non une erreur (§6). Chaque entrée
 * de 'entries' : {annee, nom, pere, iso, icc, idr}, jamais un produit sans nom NI un produit de
 * niveau 2+ (comptés séparément dans 'ignored', à titre purement informatif pour la prévisualisation
 * — §22 "produits ignorés lorsque pertinent" — jamais utilisés pour reconstruire quoi que ce soit).
 */
function gwseq_ifce_extract_production_from_pdf_string($pdf_binary) {
  $result = array('found' => false, 'entries' => array(), 'ignored' => array('saillie' => 0, 'sans_nom' => 0, 'niveau_superieur' => 0));
  if (!is_string($pdf_binary) || $pdf_binary === '') return $result;

  $all_pages = gwseq_ifce_pdf_extract_all_pages_positioned_lines($pdf_binary);
  if (count($all_pages) < 2) return $result; // aucune page au-delà de la Zone Sujet -> rien à chercher

  $zone_lines = gwseq_ifce_production_zone_lines($all_pages);
  if (empty($zone_lines)) return $result; // titre "Production" jamais trouvé : absence de section, valide

  $result['found'] = true;
  $candidates = gwseq_ifce_production_group_candidate_entries($zone_lines);
  if (empty($candidates)) return $result;

  // Signal secondaire (X) : le niveau 1 est celui dont le X est le plus proche du minimum observé
  // dans CE document, jamais une constante globale (voir la note en tête de fichier).
  $min_x = null;
  foreach ($candidates as $c) {
    if ($c['x'] === null) continue;
    if ($min_x === null || $c['x'] < $min_x) $min_x = $c['x'];
  }

  foreach ($candidates as $candidate) {
    if (!preg_match('/^(\d{4})(.*)$/s', $candidate['text'], $m)) continue; // défense en profondeur, ne devrait jamais arriver
    $annee = (int) $m[1];
    $remainder = $m[2];

    if (gwseq_ifce_production_entry_is_saillie($remainder)) {
      $result['ignored']['saillie']++;
      continue;
    }

    $is_level_one = ($candidate['x'] === null) || ($min_x === null) || (abs($candidate['x'] - $min_x) <= GWSEQ_IFCE_PRODUCTION_X_TOLERANCE);
    if (!$is_level_one) {
      $result['ignored']['niveau_superieur']++;
      continue;
    }

    $parsed = gwseq_ifce_parse_production_entry_text($remainder);
    if ($parsed['nom'] === '') {
      $result['ignored']['sans_nom']++;
      continue;
    }

    $indices = gwseq_ifce_parse_production_entry_indices($remainder);
    $result['entries'][] = array(
      'annee' => $annee,
      'nom' => $parsed['nom'],
      'pere' => $parsed['pere'],
      'iso' => $indices['iso'],
      'icc' => $indices['icc'],
      'idr' => $indices['idr'],
    );
  }

  return $result;
}

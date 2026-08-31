<?php
/**
 * Import IFCE — reconnaissance et extraction structurée (Étape 7, §3-6 de la demande).
 *
 * Transforme le TEXTE brut déjà extrait d'un PDF (voir ifce-pdf-text.php) en une structure
 * normalisée fermée `{valid, identity, indices, pedigree}` — jamais un accès direct à $_POST, aux
 * meta d'une fiche Cheval, ni aucun appel à update_post_meta()/wp_insert_post() (§7 : ce fichier ne
 * fait qu'INTERPRÉTER un texte, jamais écrire quoi que ce soit ; voir ifce-import-mapper.php pour
 * la conversion vers les fonctions métier existantes).
 *
 * PÉRIMÈTRE V1 (§3/§14) : seule la zone de synthèse principale (typiquement la première page)
 * est exploitée ; le reste du document est ignoré, jamais deviné. Aucune donnée ambiguë n'est
 * importée — un champ non reconnu reste simplement vide plutôt que de risquer une valeur fausse.
 *
 * CONVENTION DE LECTURE ASSUMÉE (documentée comme limitation, non validée contre un PDF IFCE réel
 * faute d'accès à un exemplaire) :
 * - Identité : une "ligne d'identité" à 5 valeurs séparées par des virgules
 *   (Race, Sexe, Robe, Taille, "née en AAAA") est repérée par la présence du jeton Sexe
 *   (Mâle/Femelle/Hongre, insensible à la casse/aux accents) ; le nom du cheval est la ligne non
 *   vide qui la précède immédiatement (correspond exactement à l'exemple fourni : "JAMEROSE DE
 *   FELINES" au-dessus de "Selle Français, Femelle, Gris, 1m68, née en 2019").
 * - Pedigree : repéré par un titre de section ("Généalogie"/"Pedigree"/"Origines"), suivi d'au
 *   plus 14 lignes non vides consécutives, une par ascendant, dans l'ORDRE UNIVERSEL de lecture
 *   d'un tableau généalogique à 3 générations (profondeur, branche Père en premier, de haut en
 *   bas) : Père, PP, PPP, PPM, PM, PMP, PMM, Mère, MP, MPP, MPM, MM, MMP, MMM — convention vérifiée
 *   comme correspondant exactement à l'exemple Jamerose de Félines fourni (14 ascendants).
 *
 * L'ANNÉE DE NAISSANCE D'UN ASCENDANT n'est volontairement PAS extraite/stockée en V1 (§6 : "quand
 * il existe un emplacement de donnée adapté") : le modèle d'ascendant externe existant
 * (cheval-pedigree.php, `{name, race, race_autre, father, mother}`) ne prévoit aucun champ année,
 * et ce fichier n'a pas vocation à modifier ce modèle déjà testé pour ce premier import — une
 * évolution future pourra l'ajouter si un emplacement dédié est créé.
 */

if (!defined('ABSPATH')) exit;

/**
 * Normalisation générique d'un texte pour comparaison — délègue à gwseq_normalize_race_text()
 * (cheval-pedigree.php), dont l'implémentation (minuscules, sans accents, tirets/underscores
 * traités comme des espaces) est déjà générique malgré son nom, jamais dupliquée ici.
 */
function gwseq_ifce_normalize_plain_text($text) {
  return gwseq_normalize_race_text($text);
}

/**
 * Reconnaissance du document (§10) : refuse tout PDF qui ne présente pas à la fois un marqueur
 * d'en-tête IFCE/Info Chevaux ET une ligne d'identité plausible (jeton Sexe reconnu) — les deux
 * signaux combinés évitent de reconnaître à tort un PDF sans rapport qui contiendrait par hasard
 * l'un des deux isolément.
 */
function gwseq_ifce_detect_document($text) {
  $normalized = gwseq_ifce_normalize_plain_text((string) $text);
  if ($normalized === '') return false;

  $has_header_marker = (bool) preg_match('/\b(ifce|info chevaux|fiche de synthese)\b/', $normalized);
  $has_identity_token = (bool) preg_match('/\b(male|femelle|hongre)\b/', $normalized);

  return $has_header_marker && $has_identity_token;
}

function gwseq_ifce_split_text_lines($text) {
  $text = str_replace(array("\r\n", "\r"), "\n", (string) $text);
  return explode("\n", $text);
}

/**
 * Tente de faire correspondre un texte de robe à un code canonique de gwseq_cheval_robe_options()
 * (cheval-fields.php) — même principe que gwseq_match_race_to_canonical_code() pour la race, mais
 * appliqué au référentiel des robes ; aucune seconde liste de robes n'est créée ici.
 */
function gwseq_ifce_match_robe_to_canonical_code($text) {
  $normalized = gwseq_ifce_normalize_plain_text($text);
  if ($normalized === '') return '';
  foreach (gwseq_cheval_robe_options() as $code => $label) {
    if ($code === 'autre') continue;
    if (gwseq_ifce_normalize_plain_text($code) === $normalized || gwseq_ifce_normalize_plain_text($label) === $normalized) {
      return $code;
    }
  }
  return '';
}

/**
 * Extraction de l'identité (§4) à partir des lignes de texte déjà découpées — voir la convention
 * de lecture documentée en tête de fichier. Retourne une structure fermée dont TOUS les champs
 * sont potentiellement vides (aucune valeur n'est jamais devinée) ; 'nom' vide signale l'échec de
 * la reconnaissance de la ligne d'identité et est utilisé par gwseq_ifce_parse_text() pour refuser
 * le document (§10).
 */
function gwseq_ifce_parse_identity_from_lines($lines) {
  $result = array(
    'nom' => '', 'race' => '', 'race_autre' => '', 'sexe' => '', 'robe' => '', 'robe_autre' => '',
    'taille_cm' => '', 'annee_naissance' => '', 'eleveur' => '', 'sire' => '', 'ueln' => '',
  );

  $identity_line_index = null;
  $identity_parts = array();
  foreach ($lines as $i => $line) {
    if (strpos($line, ',') === false) continue;
    $parts = array_map('trim', explode(',', $line));
    if (count($parts) < 5) continue;
    // La ligne d'identité peut contenir des virgules supplémentaires dans son dernier segment
    // ("née en 2019") : on ne fige que les 4 premiers segments, le reste forme le 5e.
    $parts = array_map('trim', explode(',', $line, 5));
    if (count($parts) !== 5) continue;
    $sexe_token = gwseq_ifce_normalize_plain_text($parts[1]);
    if (in_array($sexe_token, array('male', 'femelle', 'hongre'), true)) {
      $identity_line_index = $i;
      $identity_parts = $parts;
      break;
    }
  }
  if ($identity_line_index === null) return $result;

  for ($j = $identity_line_index - 1; $j >= 0; $j--) {
    if (trim($lines[$j]) !== '') {
      $result['nom'] = trim($lines[$j]);
      break;
    }
  }
  if ($result['nom'] === '') return $result; // sans nom, rien d'exploitable (§10)

  $race_text = trim($identity_parts[0]);
  if ($race_text !== '') {
    $matched_race = gwseq_match_race_to_canonical_code($race_text);
    if ($matched_race !== '') {
      $result['race'] = $matched_race;
    } else {
      $result['race'] = 'autre';
      $result['race_autre'] = $race_text;
    }
  }

  $sexe_map = array('male' => 'male', 'femelle' => 'female', 'hongre' => 'gelding');
  $result['sexe'] = $sexe_map[gwseq_ifce_normalize_plain_text($identity_parts[1])] ?? '';

  $robe_text = trim($identity_parts[2]);
  if ($robe_text !== '') {
    $matched_robe = gwseq_ifce_match_robe_to_canonical_code($robe_text);
    if ($matched_robe !== '') {
      $result['robe'] = $matched_robe;
    } else {
      $result['robe'] = 'autre';
      $result['robe_autre'] = $robe_text;
    }
  }

  if (preg_match('/(\d+)\s*m\s*(\d{2})\b/i', $identity_parts[3], $mt)) {
    $result['taille_cm'] = (int) ($mt[1] . $mt[2]);
  } elseif (preg_match('/(\d+)[.,](\d{2})\s*m\b/i', $identity_parts[3], $mt)) {
    $result['taille_cm'] = (int) ($mt[1] . $mt[2]);
  }

  if (preg_match('/(\d{4})/', $identity_parts[4], $my)) {
    $result['annee_naissance'] = (int) $my[1];
  }

  foreach ($lines as $line) {
    if ($result['eleveur'] === '' && preg_match('/(?:Naisseur|[EÉeé]leveur)\s*:\s*(.+)/u', $line, $me)) {
      $result['eleveur'] = trim($me[1]);
    }
    if ($result['sire'] === '' && preg_match('/\bSIRE\b\s*:?\s*(?:n°?\s*)?([0-9A-Za-z]{5,})/', $line, $ms)) {
      $result['sire'] = trim($ms[1]);
    }
    if ($result['ueln'] === '' && preg_match('/\bUELN\b\s*:?\s*([0-9A-Za-z]{5,})/', $line, $mu)) {
      $result['ueln'] = trim($mu[1]);
    }
  }

  return $result;
}

/**
 * Extraction des indices (§5). Sportifs (ISO/ICC/IDR) : valeur + CD + année, ex. exact de la
 * demande "ISO 115 (0.70) (2023)". Génétiques (BSO/BCC/BDR) : valeur + CD, jamais d'année, ex.
 * exact "BSO +12 (0.59)". Chaque composant reste structuré séparément (§5 : "ne jamais stocker une
 * chaîne unique lorsque les composants peuvent rester structurés").
 */
function gwseq_ifce_parse_indices_from_text($text) {
  $result = array();
  foreach (gwseq_cheval_sport_indice_keys() as $key) {
    $result[$key] = array('valeur' => '', 'cd' => '', 'annee' => '');
  }
  foreach (gwseq_cheval_genetic_indice_keys() as $key) {
    $result[$key] = array('valeur' => '', 'cd' => '');
  }

  if (preg_match_all('/\b(ISO|ICC|IDR)\s+([+-]?\d+(?:[.,]\d+)?)\s*\(\s*([0-9]+(?:[.,][0-9]+)?)\s*\)\s*\(\s*(\d{4})\s*\)/i', (string) $text, $matches, PREG_SET_ORDER)) {
    foreach ($matches as $m) {
      $key = strtolower($m[1]);
      $result[$key] = array(
        'valeur' => (int) round((float) str_replace(',', '.', $m[2])),
        'cd' => (float) str_replace(',', '.', $m[3]),
        'annee' => (int) $m[4],
      );
    }
  }

  if (preg_match_all('/\b(BSO|BCC|BDR)\s+([+-]?\d+(?:[.,]\d+)?)\s*\(\s*([0-9]+(?:[.,][0-9]+)?)\s*\)/i', (string) $text, $matches, PREG_SET_ORDER)) {
    foreach ($matches as $m) {
      $key = strtolower($m[1]);
      $result[$key] = array(
        'valeur' => (float) str_replace(',', '.', $m[2]),
        'cd' => (float) str_replace(',', '.', $m[3]),
      );
    }
  }

  return $result;
}

/**
 * Construit récursivement une branche d'ascendants externes en dépilant $queue dans l'ordre déjà
 * établi (préordre, branche Père d'abord) — forme directement compatible avec
 * gwseq_sanitize_external_ancestor_tree()/gwseq_set_horse_parent() (cheval-pedigree.php) : {name,
 * race, race_autre, father, mother}. $levels borne la profondeur exactement comme
 * GWSEQ_PEDIGREE_MAX_DEPTH le fait déjà côté saisie manuelle.
 */
function gwseq_ifce_build_ancestor_subtree(&$queue, $levels) {
  if ($levels < 1 || empty($queue)) return null;
  $entry = array_shift($queue);
  $name = trim($entry['name'] ?? '');
  if ($name === '') return null;

  $race = '';
  $race_autre = '';
  $race_text = trim($entry['race_text'] ?? '');
  if ($race_text !== '') {
    $matched = gwseq_match_race_to_canonical_code($race_text);
    if ($matched !== '') {
      $race = $matched;
    } else {
      $race = 'autre';
      $race_autre = $race_text;
    }
  }

  $node = array('name' => $name, 'race' => $race, 'race_autre' => $race_autre, 'father' => null, 'mother' => null);
  if ($levels > 1) {
    $node['father'] = gwseq_ifce_build_ancestor_subtree($queue, $levels - 1);
    $node['mother'] = gwseq_ifce_build_ancestor_subtree($queue, $levels - 1);
  }
  return $node;
}

/**
 * Extraction du pedigree (§6) — voir la convention de lecture documentée en tête de fichier.
 * Retourne {father, mother, count} : 'count' est le nombre brut de lignes d'ascendant reconnues
 * (utilisé tel quel par la prévisualisation, §9 : "Pedigree : 14 ascendants détectés"), 'father'/
 * 'mother' les arbres déjà à la forme attendue par gwseq_set_horse_parent(mode: 'external').
 */
function gwseq_ifce_parse_pedigree_from_lines($lines) {
  $result = array('father' => null, 'mother' => null, 'count' => 0);

  $heading_index = null;
  foreach ($lines as $i => $line) {
    if (preg_match('/g[eé]n[eé]alogie|pedigree|origines/iu', $line)) {
      $heading_index = $i;
      break;
    }
  }
  if ($heading_index === null) return $result;

  $entries = array();
  for ($i = $heading_index + 1; $i < count($lines) && count($entries) < 14; $i++) {
    $line = trim($lines[$i]);
    if ($line === '') continue;
    if (preg_match('/^(.+?)\s*\(\s*([^,()]+?)\s*(?:,\s*\d{4}\s*)?\)\s*$/u', $line, $m)) {
      $entries[] = array('name' => trim($m[1]), 'race_text' => trim($m[2]));
    } else {
      $entries[] = array('name' => $line, 'race_text' => '');
    }
  }

  $result['count'] = count($entries);
  if (empty($entries)) return $result;

  $queue = $entries;
  $result['father'] = gwseq_ifce_build_ancestor_subtree($queue, 3);
  $result['mother'] = gwseq_ifce_build_ancestor_subtree($queue, 3);

  return $result;
}

/**
 * Point d'entrée unique du parseur — texte brut vers structure normalisée fermée. `valid` à false
 * signifie un document non reconnu (§10) : le code appelant (ifce-import-admin.php) n'écrit alors
 * strictement rien et affiche un message explicite, jamais un import "best effort".
 */
function gwseq_ifce_parse_text($text) {
  if (!gwseq_ifce_detect_document($text)) {
    return array('valid' => false);
  }

  $lines = gwseq_ifce_split_text_lines($text);
  $identity = gwseq_ifce_parse_identity_from_lines($lines);
  if ($identity['nom'] === '') {
    return array('valid' => false);
  }

  return array(
    'valid' => true,
    'identity' => $identity,
    'indices' => gwseq_ifce_parse_indices_from_text($text),
    'pedigree' => gwseq_ifce_parse_pedigree_from_lines($lines),
  );
}

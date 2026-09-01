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
 * CONVENTION DE LECTURE — VALIDÉE CONTRE LE VRAI PDF IFCE DE JAMEROSE DE FÉLINES (Étape 7, recette
 * runtime, voir `tests/fixtures/ifce-jamerose-de-felines.pdf` et `ifce-pdf-text.php` pour le détail
 * du correctif d'extraction qui a rendu cette validation possible — le texte ligne par ligne qui
 * alimente ce fichier n'était, avant ce correctif, jamais reconnaissable) :
 * - Identité : une "ligne d'identité" à 5 valeurs séparées par des virgules
 *   (Race, Sexe, Robe, Taille, "né(e) en AAAA") est repérée par la présence du jeton Sexe
 *   (Mâle/Femelle/Hongre, insensible à la casse/aux accents) ; le nom du cheval est la ligne non
 *   vide qui la précède immédiatement — confirmé EXACTEMENT sur le vrai document ("JAMEROSE DE
 *   FELINES" au-dessus de "Selle Francais, Femelle, Gris, 1m68, né(e) en 2019").
 * - Pedigree : repéré par un titre de section ("Généalogie"/"Pedigree"/"Origines"), suivi d'un bloc
 *   CONTIGU de lignes non vides (le bloc s'arrête à la première ligne vide rencontrée APRÈS le
 *   premier ascendant, jamais avant — le vrai document alterne juste après ce bloc vers une section
 *   "Production" hors périmètre V1, voir §14), plafonné à 14 lignes/ascendants, dans l'ORDRE
 *   UNIVERSEL de lecture d'un tableau généalogique à 3 générations (profondeur, branche Père en
 *   premier, de haut en bas) : Père, PP, PPP, PPM, PM, PMP, PMM, Mère, MP, MPP, MPM, MM, MMP, MMM —
 *   convention CONFIRMÉE exacte sur le vrai document (14 ascendants, même ordre que l'exemple
 *   fourni dans la demande).
 *   Chaque ligne d'ascendant réelle porte le nom suivi, quand présent, d'un code de stud-book en
 *   MAJUSCULES et d'une année à 4 chiffres (ex. "HORS LA LOI II SFA 1995"), parfois précédés d'un
 *   code pays IFCE reconnu entre parenthèses (ex. "HEARTBREAKER (NLD) KWPN 1989", voir
 *   gwseq_ifce_country_codes() — liste FERMÉE, jamais une suppression aveugle de toute parenthèse)
 *   — voir gwseq_ifce_parse_pedigree_entry_line(). Un ascendant dont le libellé complet dépasse la
 *   largeur de sa case peut se poursuivre sur la ligne suivante par la seule année sur une ligne
 *   isolée (rencontré sur le vrai document) : une ligne composée uniquement de 4 chiffres est alors
 *   rattachée à la ligne précédente plutôt que comptée comme un ascendant séparé.
 * - **Alias IFCE** (§7 du correctif runtime post-recette) : quand un cheval — l'ascendant lui-même
 *   OU le cheval de la fiche — possède un alias ("NOM_OFFICIEL Alias NOM_D'USAGE", rencontré aussi
 *   bien sur une ligne combinée d'ascendant que sur deux lignes consécutives dans la zone
 *   d'identité), c'est désormais le NOM D'USAGE (l'alias) qui devient le nom retenu (`name`/`nom`
 *   — jamais le mot littéral "Alias" ni le nom officiel, qui perdrait le nom réellement utilisé
 *   dans le sport). Le nom officiel reste disponible séparément (`official_name`/`nom_officiel`),
 *   jamais perdu — voir gwseq_ifce_parse_pedigree_entry_line() et
 *   gwseq_ifce_parse_identity_from_lines(). AVANT ce correctif, la mention "Alias ..." et tout ce
 *   qui la suivait étaient simplement retirés, ne conservant que le nom officiel — comportement
 *   inversé par ce correctif.
 *
 * L'ANNÉE DE NAISSANCE D'UN ASCENDANT (correctif référentiel, §9 de la demande) EST désormais
 * extraite quand elle figure dans la fiche IFCE (même token `\d{4}` déjà repéré pour délimiter la
 * fin du nom, voir gwseq_ifce_parse_pedigree_entry_line()) et stockée dans le champ `annee_naissance`
 * du modèle d'ascendant externe (cheval-pedigree.php, `{name, race, race_autre, annee_naissance,
 * father, mother}`) — jamais un âge calculé ou stocké, uniquement l'année brute, exactement comme
 * pour l'identité du cheval lui-même. Absente du document, elle reste simplement vide, jamais
 * devinée. Le code de stud-book de chaque ascendant est mappé au référentiel canonique existant
 * exactement comme pour l'identité (gwseq_match_race_to_canonical_code(), sinon "Autre" + texte
 * d'origine, jamais deviné).
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
 * Codes pays reconnus par la convention IFCE pour marquer l'origine d'un cheval entre parenthèses
 * juste après son nom (ex. « HEARTBREAKER (NLD) », « CARTHAGO Z (DEU) ») — correctif runtime
 * post-recette (§9-10 de la demande) : liste FERMÉE (norme ISO 3166-1 alpha-3), jamais "toute
 * séquence de 2-3 lettres majuscules entre parenthèses", pour ne jamais confondre un vrai marqueur
 * pays avec un autre contenu parenthésé qui n'en serait pas un.
 */
function gwseq_ifce_country_codes() {
  static $codes = null;
  if ($codes !== null) return $codes;
  $codes = array(
    'AFG', 'ALB', 'DZA', 'ASM', 'AND', 'AGO', 'AIA', 'ATA', 'ATG', 'ARG', 'ARM', 'ABW', 'AUS', 'AUT',
    'AZE', 'BHS', 'BHR', 'BGD', 'BRB', 'BLR', 'BEL', 'BLZ', 'BEN', 'BMU', 'BTN', 'BOL', 'BIH', 'BWA',
    'BRA', 'BRN', 'BGR', 'BFA', 'BDI', 'CPV', 'KHM', 'CMR', 'CAN', 'CYM', 'CAF', 'TCD', 'CHL', 'CHN',
    'COL', 'COM', 'COG', 'COD', 'COK', 'CRI', 'CIV', 'HRV', 'CUB', 'CUW', 'CYP', 'CZE', 'DNK', 'DJI',
    'DMA', 'DOM', 'ECU', 'EGY', 'SLV', 'GNQ', 'ERI', 'EST', 'SWZ', 'ETH', 'FJI', 'FIN', 'FRA', 'GUF',
    'PYF', 'GAB', 'GMB', 'GEO', 'DEU', 'GHA', 'GIB', 'GRC', 'GRL', 'GRD', 'GLP', 'GUM', 'GTM', 'GGY',
    'GIN', 'GNB', 'GUY', 'HTI', 'HND', 'HKG', 'HUN', 'ISL', 'IND', 'IDN', 'IRN', 'IRQ', 'IRL', 'IMN',
    'ISR', 'ITA', 'JAM', 'JPN', 'JEY', 'JOR', 'KAZ', 'KEN', 'KIR', 'PRK', 'KOR', 'KWT', 'KGZ', 'LAO',
    'LVA', 'LBN', 'LSO', 'LBR', 'LBY', 'LIE', 'LTU', 'LUX', 'MAC', 'MDG', 'MWI', 'MYS', 'MDV', 'MLI',
    'MLT', 'MHL', 'MTQ', 'MRT', 'MUS', 'MYT', 'MEX', 'FSM', 'MDA', 'MCO', 'MNG', 'MNE', 'MSR', 'MAR',
    'MOZ', 'MMR', 'NAM', 'NRU', 'NPL', 'NLD', 'NCL', 'NZL', 'NIC', 'NER', 'NGA', 'NIU', 'MKD', 'NOR',
    'OMN', 'PAK', 'PLW', 'PAN', 'PNG', 'PRY', 'PER', 'PHL', 'POL', 'PRT', 'PRI', 'QAT', 'ROU', 'RUS',
    'RWA', 'KNA', 'LCA', 'VCT', 'WSM', 'SMR', 'STP', 'SAU', 'SEN', 'SRB', 'SYC', 'SLE', 'SGP', 'SVK',
    'SVN', 'SLB', 'SOM', 'ZAF', 'ESP', 'LKA', 'SDN', 'SUR', 'SWE', 'CHE', 'SYR', 'TWN', 'TJK', 'TZA',
    'THA', 'TLS', 'TGO', 'TON', 'TTO', 'TUN', 'TUR', 'TKM', 'TUV', 'UGA', 'UKR', 'ARE', 'GBR', 'USA',
    'URY', 'UZB', 'VUT', 'VAT', 'VEN', 'VNM', 'YEM', 'ZMB', 'ZWE',
  );
  return $codes;
}

/**
 * Retire un marqueur pays IFCE (`(NLD)`, `(BEL)`, `(DEU)`...) de $text — UNIQUEMENT s'il s'agit
 * d'un vrai code reconnu (voir gwseq_ifce_country_codes()), jamais une suppression aveugle de
 * toute parenthèse (§9 du correctif runtime : "ne pas supprimer arbitrairement toutes les
 * parenthèses"). Espaces multiples résultant du retrait recollés proprement.
 */
function gwseq_ifce_strip_country_markers($text) {
  $stripped = preg_replace_callback('/\(([A-Za-z]{2,3})\)/u', function ($m) {
    return in_array(strtoupper($m[1]), gwseq_ifce_country_codes(), true) ? '' : $m[0];
  }, (string) $text);
  return trim(preg_replace('/\s+/', ' ', $stripped));
}

/**
 * Analyse un segment "NOM [(PAYS)] [CODE_STUDBOOK] [ANNEE]" déjà isolé (sans mention "Alias" — voir
 * gwseq_ifce_parse_pedigree_entry_line() pour la gestion de l'alias) : cœur commun factorisé entre
 * le nom officiel et le nom d'usage (alias) d'une même entrée, chacun pouvant porter sa propre
 * mention de pays/stud-book/année. Le marqueur pays est retiré AVANT la détection du stud-book —
 * jamais confondu avec lui, jamais laissé dans le nom. Voir le piège du chiffre romain final
 * documenté sur gwseq_ifce_parse_pedigree_entry_line() : la même protection s'applique ici, et le
 * suffixe court "Z" (ex. "ESCAPE Z") n'est jamais confondu avec un stud-book (le groupe de
 * stud-book exige au moins 2 lettres).
 */
function gwseq_ifce_parse_name_studbook_year($text) {
  $text = gwseq_ifce_strip_country_markers($text);
  if ($text === '') return array('name' => '', 'race_text' => '', 'annee_naissance' => '');
  $roman_numerals = 'I|II|III|IV|V|VI|VII|VIII|IX|X';
  if (preg_match('/^(.+?)\s+(?!(?:' . $roman_numerals . ')(?:\s+\d{4})?$)([A-Z]{2,6})(?:\s+(\d{4}))?$/u', $text, $m)) {
    return array(
      'name' => trim($m[1]),
      'race_text' => trim($m[2]),
      'annee_naissance' => (isset($m[3]) && $m[3] !== '') ? (int) $m[3] : '',
    );
  }
  return array('name' => $text, 'race_text' => '', 'annee_naissance' => '');
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
 * Reconnaît la mention "né(e) en AAAA" elle-même (plutôt qu'une position de segment fixe) —
 * correctif runtime : la robe est FACULTATIVE sur certaines fiches IFCE réelles (ex. "Holsteiner
 * Warmblut, Mâle, né(e) en 1998", aucune robe), et un segment qui porte déjà cette mention ne doit
 * alors jamais être pris pour une robe. Insensible à la casse et tolère les deux variantes
 * d'accent rencontrées ("né" / "née").
 */
function gwseq_ifce_looks_like_birth_year_segment($text) {
  return (bool) preg_match('/n[ée]\(?e?\)?\s*en\s*\d{4}/iu', (string) $text);
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
    'nom' => '', 'nom_officiel' => '', 'race' => '', 'race_autre' => '', 'sexe' => '', 'robe' => '', 'robe_autre' => '',
    'taille_cm' => '', 'annee_naissance' => '', 'eleveur' => '', 'sire' => '', 'ueln' => '',
  );

  // Correctif runtime (recette sur des fiches IFCE réelles supplémentaires) : le nombre de segments
  // séparés par des virgules sur la ligne d'identité N'EST PAS FIXE. Le format à 5 segments (Race,
  // Sexe, Robe, Taille, "né(e) en AAAA", éventuellement suivi de ", étalon") observé sur Jamerose/
  // Iowa Jal n'est qu'UNE variante parmi d'autres réellement rencontrées :
  // - "Kon. Warm Paard Nederland, Mâle, Gris, né(e) en 2001, étalon" (UNTOUCHABLE 27) : PAS de
  //   taille — la robe est directement suivie de l'année.
  // - "Holsteiner Warmblut, Mâle, né(e) en 1998" (Quaprice Bois Margot) : NI taille NI robe.
  // Une position FIGÉE ("le 4e segment est toujours la taille, le 5e toujours l'année") casse dans
  // ces deux cas — Quaprice n'était même pas reconnu comme une ligne d'identité (rejeté avant tout
  // examen, moins de 5 segments), et Untouchable 27/Bush vd Heffinck avaient leur robe et leur
  // "né(e) en AAAA" mal alignés sur les positions attendues, perdant taille ET année. Détection
  // robuste : on repère la position RÉELLE du jeton Sexe (jamais figée), la race est tout ce qui la
  // précède, puis on distingue la robe (facultative) de la suite en reconnaissant la mention
  // "né(e) en AAAA" ELLE-MÊME plutôt qu'une position — un segment qui y ressemble n'est jamais
  // confondu avec une robe.
  $identity_line_index = null;
  $race_text = '';
  $sexe_token = '';
  $robe_text = '';
  $remainder_text = '';
  foreach ($lines as $i => $line) {
    if (strpos($line, ',') === false) continue;
    $segments = array_map('trim', explode(',', $line));
    if (count($segments) < 2) continue;
    $sexe_index = null;
    foreach ($segments as $idx => $segment) {
      if ($idx === 0) continue; // le premier segment est toujours la race, jamais le sexe
      if (in_array(gwseq_ifce_normalize_plain_text($segment), array('male', 'femelle', 'hongre'), true)) {
        $sexe_index = $idx;
        break;
      }
    }
    if ($sexe_index === null) continue;
    $identity_line_index = $i;
    $race_text = trim(implode(',', array_slice($segments, 0, $sexe_index)));
    $sexe_token = gwseq_ifce_normalize_plain_text($segments[$sexe_index]);
    $after_sexe = array_slice($segments, $sexe_index + 1);
    if (!empty($after_sexe) && !gwseq_ifce_looks_like_birth_year_segment($after_sexe[0])) {
      $robe_text = trim($after_sexe[0]);
      $remainder_text = trim(implode(',', array_slice($after_sexe, 1)));
    } else {
      $robe_text = '';
      $remainder_text = trim(implode(',', $after_sexe));
    }
    break;
  }
  if ($identity_line_index === null) return $result;

  $name_line_index = null;
  for ($j = $identity_line_index - 1; $j >= 0; $j--) {
    if (trim($lines[$j]) !== '') { $name_line_index = $j; break; }
  }
  if ($name_line_index === null) return $result;

  // Alias IFCE du cheval lui-même (correctif runtime, §7 de la demande) : « le nom officiel puis
  // l'alias » peut apparaître sur une seule ligne combinée ("NOM Alias ALIAS", même convention que
  // pour un ascendant du pedigree) OU sur deux lignes distinctes consécutives ("NOM" puis, juste
  // en dessous, "Alias ALIAS" seule) — les deux formes sont gérées. Quand un alias existe, c'est
  // LUI qui devient le nom d'usage (`nom`, utilisé comme nom de la fiche GWS) ; le nom officiel
  // reste disponible séparément dans `nom_officiel`, jamais perdu.
  $name_line = trim($lines[$name_line_index]);
  $official_name = '';
  $usage_name = '';
  if (preg_match('/^Alias\s+(.+)$/iu', $name_line, $am)) {
    $usage_name = gwseq_ifce_strip_country_markers(trim($am[1]));
    for ($k = $name_line_index - 1; $k >= 0; $k--) {
      if (trim($lines[$k]) !== '') { $official_name = gwseq_ifce_strip_country_markers(trim($lines[$k])); break; }
    }
  } elseif (preg_match('/^(.+?)\bAlias\b\s*(.*)$/iu', $name_line, $am)) {
    $official_name = gwseq_ifce_strip_country_markers(trim($am[1]));
    $usage_name = gwseq_ifce_strip_country_markers(trim($am[2]));
  } else {
    $official_name = gwseq_ifce_strip_country_markers($name_line);
  }
  if ($official_name === '' && $usage_name === '') return $result; // sans nom, rien d'exploitable (§10)
  $result['nom'] = $usage_name !== '' ? $usage_name : $official_name;
  $result['nom_officiel'] = $official_name !== '' ? $official_name : $usage_name;

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
  $result['sexe'] = $sexe_map[$sexe_token] ?? '';

  if ($robe_text !== '') {
    $matched_robe = gwseq_ifce_match_robe_to_canonical_code($robe_text);
    if ($matched_robe !== '') {
      $result['robe'] = $matched_robe;
    } else {
      $result['robe'] = 'autre';
      $result['robe_autre'] = $robe_text;
    }
  }

  if (preg_match('/(\d+)\s*m\s*(\d{2})\b/i', $remainder_text, $mt)) {
    $result['taille_cm'] = (int) ($mt[1] . $mt[2]);
  } elseif (preg_match('/(\d+)[.,](\d{2})\s*m\b/i', $remainder_text, $mt)) {
    $result['taille_cm'] = (int) ($mt[1] . $mt[2]);
  }

  if (preg_match('/n[ée]\(?e?\)?\s*en\s*(\d{4})/iu', $remainder_text, $my)) {
    $result['annee_naissance'] = (int) $my[1];
  } elseif (preg_match('/(\d{4})/', $remainder_text, $my)) {
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

  // PREMIÈRE occurrence uniquement pour chaque indice (jamais la dernière) : un vrai document
  // multi-pages peut répéter le même sigle d'indice pour un ASCENDANT plus loin dans le texte
  // (production détaillée, hors périmètre V1, §14) — seule la mention la plus proche du début,
  // dans la zone de synthèse du cheval lui-même, doit être retenue ; ne jamais laisser un indice
  // d'un autre cheval écraser silencieusement celui de la fiche importée.
  if (preg_match_all('/\b(ISO|ICC|IDR)\s+([+-]?\d+(?:[.,]\d+)?)\s*\(\s*([0-9]+(?:[.,][0-9]+)?)\s*\)\s*\(\s*(\d{4})\s*\)/i', (string) $text, $matches, PREG_SET_ORDER)) {
    foreach ($matches as $m) {
      $key = strtolower($m[1]);
      if ($result[$key]['valeur'] !== '') continue;
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
      if ($result[$key]['valeur'] !== '') continue;
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
 * race, race_autre, annee_naissance, father, mother}. $levels borne la profondeur exactement comme
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

  $node = array(
    'name' => $name,
    'race' => $race,
    'race_autre' => $race_autre,
    'annee_naissance' => $entry['annee_naissance'] ?? '',
    'father' => null,
    'mother' => null,
  );
  if ($levels > 1) {
    $node['father'] = gwseq_ifce_build_ancestor_subtree($queue, $levels - 1);
    $node['mother'] = gwseq_ifce_build_ancestor_subtree($queue, $levels - 1);
  }
  return $node;
}

/**
 * Analyse UNE ligne d'ascendant déjà isolée (une fois les continuations d'année déjà fusionnées,
 * voir gwseq_ifce_parse_pedigree_from_lines()) en {name, official_name, race_text,
 * annee_naissance} — voir la convention de lecture documentée en tête de fichier pour les formes
 * réelles rencontrées :
 * - "NOM" (aucune information de stud-book/année — cas le plus simple, ex. saisie manuelle) ;
 * - "NOM CODE_STUDBOOK" ou "NOM CODE_STUDBOOK ANNEE" (cas réel le plus courant) ;
 * - "NOM (CODE_PAYS) CODE_STUDBOOK ANNEE" (code pays IFCE reconnu entre parenthèses avant le
 *   stud-book — voir gwseq_ifce_country_codes(), jamais une suppression aveugle de toute
 *   parenthèse) ;
 * - "NOM_OFFICIEL Alias NOM_D'USAGE [(PAYS)] [CODE_STUDBOOK] [ANNEE]" — CORRECTIF RUNTIME (§7-10 de
 *   la demande) : quand un cheval possède un alias IFCE, c'est ce nom d'usage qui doit apparaître
 *   comme `name` (utilisé tel quel comme nom affiché/nom de la fiche GWS, jamais le mot littéral
 *   "Alias"), le nom officiel restant disponible séparément dans `official_name` (donnée
 *   source/technique, jamais perdue). Le pays/stud-book/année éventuels, quand présents,
 *   qualifient le nom d'usage (ils suivent l'alias dans le document réel) — voir
 *   gwseq_ifce_parse_name_studbook_year(), appliqué indépendamment à chaque partie.
 *
 * PIÈGE ÉCARTÉ (constaté en développement) : un nom de cheval se termine très souvent par un
 * chiffre romain ("HORS LA LOI II", "CORRADO I"...), qui a exactement la forme d'un code de
 * stud-book (lettres majuscules) — le groupe de stud-book exclut donc explicitement les chiffres
 * romains isolés (I à X) en fin de ligne. Un suffixe court de nom ("ESCAPE Z") n'est pas non plus
 * confondu avec un stud-book (le groupe de stud-book exige au moins 2 lettres).
 */
function gwseq_ifce_parse_pedigree_entry_line($line) {
  $line = trim((string) $line);
  if (preg_match('/^(.*?)\bAlias\b\s*(.*)$/iu', $line, $am)) {
    $official_raw = trim($am[1]);
    $usage_raw = trim($am[2]);
    $official_parsed = gwseq_ifce_parse_name_studbook_year($official_raw);
    $usage_parsed = gwseq_ifce_parse_name_studbook_year($usage_raw);
    // Le nom d'usage (alias) porte le pays/stud-book/année dans le document réel ; à défaut (alias
    // manquant après un "Alias" isolé), on retombe sur le nom officiel plutôt que de perdre la ligne.
    $chosen = $usage_parsed['name'] !== '' ? $usage_parsed : $official_parsed;
    return array(
      'name' => $chosen['name'],
      'official_name' => $official_parsed['name'] !== '' ? $official_parsed['name'] : $chosen['name'],
      'race_text' => $chosen['race_text'],
      'annee_naissance' => $chosen['annee_naissance'],
    );
  }
  $parsed = gwseq_ifce_parse_name_studbook_year($line);
  return array(
    'name' => $parsed['name'],
    'official_name' => $parsed['name'],
    'race_text' => $parsed['race_text'],
    'annee_naissance' => $parsed['annee_naissance'],
  );
}

/**
 * Extraction du pedigree (§6) — voir la convention de lecture documentée en tête de fichier.
 * Retourne {father, mother, count} : 'count' est le nombre d'ascendants reconnus (utilisé tel quel
 * par la prévisualisation, §9 : "Pedigree : 14 ascendants détectés"), 'father'/'mother' les arbres
 * déjà à la forme attendue par gwseq_set_horse_parent(mode: 'external').
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

  // Bloc CONTIGU de lignes non vides (voir convention de lecture en tête de fichier) : on ignore
  // d'éventuelles lignes vides de mise en forme entre le titre et le premier ascendant, mais on
  // s'arrête à la première ligne vide rencontrée UNE FOIS ce premier ascendant trouvé — jamais un
  // ramassage aveugle des 14 prochaines lignes non vides, qui déborderait sur la section
  // "Production" détaillée du vrai document (hors périmètre V1, §14).
  // Plafond large sur les lignes BRUTES (avant fusion des continuations d'année, qui consomment
  // chacune un rang sans représenter un ascendant supplémentaire) — la vraie limite du bloc reste
  // la première ligne vide rencontrée ci-dessous ; ce plafond ne fait qu'éviter un parcours sans
  // fin sur une entrée malformée qui ne rencontrerait jamais de ligne vide.
  $raw_lines = array();
  $started = false;
  for ($i = $heading_index + 1; $i < count($lines) && count($raw_lines) < 40; $i++) {
    $line = trim($lines[$i]);
    if ($line === '') {
      if ($started) break;
      continue;
    }
    $started = true;
    $raw_lines[] = $line;
  }

  // Une ligne composée uniquement d'une année à 4 chiffres est la continuation visuelle de la ligne
  // précédente (ascendant dont le libellé complet a débordé sur deux lignes — rencontré sur le vrai
  // document, voir la convention de lecture), jamais un ascendant distinct.
  $merged_lines = array();
  foreach ($raw_lines as $line) {
    if (preg_match('/^\d{4}$/', $line) && !empty($merged_lines)) {
      $merged_lines[count($merged_lines) - 1] .= ' ' . $line;
    } else {
      $merged_lines[] = $line;
    }
  }
  $merged_lines = array_slice($merged_lines, 0, 14);

  $entries = array_map('gwseq_ifce_parse_pedigree_entry_line', $merged_lines);

  $result['count'] = count($entries);
  if (empty($entries)) return $result;

  $queue = $entries;
  $result['father'] = gwseq_ifce_build_ancestor_subtree($queue, GWSEQ_PEDIGREE_MAX_DEPTH);
  $result['mother'] = gwseq_ifce_build_ancestor_subtree($queue, GWSEQ_PEDIGREE_MAX_DEPTH);

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

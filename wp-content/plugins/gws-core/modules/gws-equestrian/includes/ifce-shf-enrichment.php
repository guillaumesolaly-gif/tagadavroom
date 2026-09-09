<?php
/**
 * Enrichissement opportuniste du N° SIRE via SHF (Société Hippique Française) — Lot SHF.
 *
 * PRINCIPE (§ demande) : PDF IFCE -> ID IFCE -> éventuellement SHF -> N° SIRE. SHF reste une
 * source SECONDAIRE et FACULTATIVE : ce fichier n'ajoute AUCUNE fonctionnalité générale de
 * connexion à SHF, uniquement un unique point d'enrichissement du workflow d'import PDF IFCE déjà
 * existant (voir les points d'appel dans ifce-import-admin.php et ifce-import-mapper.php — jamais
 * ailleurs : ni à l'ouverture d'une fiche, ni au chargement du BO/front, ni en cron, ni en batch).
 *
 * ORIGINE : reprend la logique réseau et l'extraction ciblée validées en conditions réelles par le
 * POC autonome `poc-shf-panel.php` (8 chevaux réels, 8/8 SIRE extraits correctement, témoin négatif
 * avec ID fictif correctement classé "ID inconnu" — POC jamais versionné avec GWS, non repris ici
 * tel quel mais intégré à cette architecture).
 *
 * GARANTIE CENTRALE (§6, "SHF ne doit jamais devenir une condition de succès de l'import IFCE") :
 * gwseq_ifce_shf_lookup_sire_by_id(), point d'entrée UNIQUE utilisé par le reste du module, ne lève
 * JAMAIS d'exception et ne retourne QUE '' (chaîne vide) au moindre doute — ID syntaxiquement
 * invalide, hôte/schéma refusé, timeout, erreur curl, HTTP différent de 200, contenu non HTML, page
 * non reconnue comme une fiche cheval, libellé "N° SIRE" absent, ou valeur trouvée ne respectant pas
 * la forme stricte attendue. Aucun de ces cas n'est une erreur bloquante pour l'appelant : c'est
 * précisément ce que "fail closed pour l'enrichissement uniquement" (§7) signifie ici.
 *
 * TESTABILITÉ SANS RÉSEAU RÉEL (§10, "Mocke le réseau dans la suite automatisée") : la seule
 * fonction de ce fichier qui touche réellement curl est gwseq_ifce_shf_fetch_final_response_real()
 * — jamais appelée directement, uniquement via gwseq_ifce_shf_fetch_final_response(), qui consulte
 * D'ABORD le filtre `gwseq_ifce_shf_fetch_override` (aucun filtre enregistré en dehors des tests :
 * comportement réseau réel inchangé en production). Toute la logique de décision — classification
 * HTTP/Content-Type, reconnaissance de fiche, extraction ciblée du SIRE, validation de forme — est
 * séparée dans des fonctions PURES (gwseq_ifce_shf_extract_valid_sire(), etc.), directement
 * testables avec de simples chaînes/tableaux synthétiques, sans le moindre mock.
 *
 * CORRECTIF "UELN Selle Français" — dérivation de l'UELN à partir du SIRE (fonctions PURES en fin de
 * fichier, gwseq_ifce_derive_ueln_from_sire() et gwseq_ifce_ueln_eligible_race_codes()) : SANS AUCUN
 * RAPPORT AVEC LE RÉSEAU (le SIRE utilisé peut venir de SHF, du PDF lui-même, ou être déjà enregistré
 * sur la fiche) — regroupée ici car elle appartient au même enrichissement d'identité pendant
 * l'import IFCE, jamais un fichier séparé pour une règle aussi ciblée. Audit préalable (voir CR) :
 * aucun champ GWS/IFCE actuel ne permet d'établir avec certitude la nationalité française d'un
 * cheval — SEULE exception confirmée et volontairement retenue ici, le stud-book Selle Français
 * (code référentiel 'SF'), dont l'UELN utilise la racine `250001` MÊME pour un cheval né à
 * l'étranger (donc jamais conditionné au pays de naissance). Liste FERMÉE, volontairement restreinte
 * à ce seul cas — n'importe quel autre stud-book/race reste sans dérivation tant qu'une règle
 * équivalente n'a pas été explicitement confirmée.
 */

if (!defined('ABSPATH')) exit;

const GWSEQ_IFCE_SHF_HOST = 'www.shf.eu';
const GWSEQ_IFCE_SHF_SCHEME = 'https';
const GWSEQ_IFCE_SHF_URL_TEMPLATE = 'https://www.shf.eu/fr/cheval/test,I%s.html';
const GWSEQ_IFCE_SHF_MAX_REDIRECTS = 5;
const GWSEQ_IFCE_SHF_MAX_BYTES = 2 * 1024 * 1024;
const GWSEQ_IFCE_SHF_CONNECT_TIMEOUT = 5;
const GWSEQ_IFCE_SHF_TOTAL_TIMEOUT = 10;
const GWSEQ_IFCE_SHF_USER_AGENT = 'GWS-Equestrian-IFCE-Import/1 (+enrichissement SIRE opportuniste, non-navigateur)';

/* -------------------------------------------------------------------------------------------
 * Garde-fous réseau (§2/§3 de la demande) — fonctions PURES, directement testables.
 * ----------------------------------------------------------------------------------------- */

/**
 * Refuse tout hôte/schéma autre que https://www.shf.eu — appelée avant CHAQUE requête, y compris
 * chaque saut de redirection (jamais uniquement sur l'URL de départ, voir
 * gwseq_ifce_shf_fetch_final_response_real() plus bas : CURLOPT_FOLLOWLOCATION reste désactivé,
 * ce garde-fou est ce qui empêche réellement de suivre une redirection ailleurs).
 */
function gwseq_ifce_shf_assert_allowed_url($url) {
  $parts = parse_url((string) $url);
  if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
    throw new RuntimeException('URL SHF invalide.');
  }
  if (strtolower($parts['scheme']) !== GWSEQ_IFCE_SHF_SCHEME || strtolower($parts['host']) !== GWSEQ_IFCE_SHF_HOST) {
    throw new RuntimeException('Hôte ou schéma SHF non autorisé.');
  }
}

function gwseq_ifce_shf_resolve_redirect_url($base, $location) {
  $location = (string) $location;
  if (preg_match('#^https?://#i', $location)) return $location;
  $baseParts = parse_url((string) $base);
  $scheme = $baseParts['scheme'] ?? GWSEQ_IFCE_SHF_SCHEME;
  $host = $baseParts['host'] ?? GWSEQ_IFCE_SHF_HOST;
  if (strpos($location, '/') === 0) return "$scheme://$host" . $location;
  $basePath = $baseParts['path'] ?? '/';
  $dir = substr($basePath, 0, strrpos($basePath, '/') + 1);
  return "$scheme://$host" . $dir . $location;
}

/* -------------------------------------------------------------------------------------------
 * Extraction ciblée du N° SIRE et reconnaissance de fiche — fonctions PURES, directement
 * testables avec du HTML synthétique (§3/§7 de la demande : jamais un motif "ressemble à un SIRE"
 * recherché n'importe où sur la page, toujours ancré sur le libellé "N° SIRE").
 * ----------------------------------------------------------------------------------------- */

/**
 * Repère le libellé "N° SIRE" (variantes °/º/&deg;/&#176; et balises/espaces intercalés tolérées
 * dans le libellé lui-même) puis n'examine qu'une fenêtre de texte bornée immédiatement après —
 * jamais toute la page. Les balises de cette fenêtre sont remplacées par un espace (jamais
 * simplement supprimées) avant nettoyage : les retirer sans séparateur recollerait deux noeuds de
 * texte adjacents (ex. "19369410S</strong></td><td>Code postal" -> "19369410SCode postal"), ce qui
 * casserait la vérification de frontière de mot juste après une valeur valide et pourrait laisser
 * gagner un nombre sans rapport situé plus loin dans la fenêtre — bug identifié et corrigé pendant
 * le développement du POC `poc-shf-panel.php`, reproduit ici à l'identique.
 *
 * Ne transforme, ne complète ni ne déduit jamais la valeur trouvée (§3) : seuls le nettoyage
 * HTML/entités et les espaces superflus sont retirés de cette fenêtre.
 */
function gwseq_ifce_shf_extract_sire_from_html($html) {
  $html = (string) $html;
  $tagOrSpace = '(?:\s|<[^>]*>|&nbsp;)*';
  $degree = '(?:°|º|&deg;|&#176;)?';
  $labelPattern = '/N' . $tagOrSpace . $degree . $tagOrSpace . 'SIRE/iu';

  if (!preg_match($labelPattern, $html, $m, PREG_OFFSET_CAPTURE)) {
    return array('found_label' => false, 'sire' => null);
  }

  $labelEnd = $m[0][1] + strlen($m[0][0]);
  $window = substr($html, $labelEnd, 400);
  $withSpaces = preg_replace('/<[^>]+>/', ' ', $window);
  $clean = html_entity_decode($withSpaces, ENT_QUOTES | ENT_HTML5, 'UTF-8');
  $clean = trim(preg_replace('/\s+/', ' ', $clean));

  if (preg_match('/\b(\d{8}[A-Za-z])\b/', $clean, $sireMatch)) {
    return array('found_label' => true, 'sire' => $sireMatch[1]);
  }
  return array('found_label' => true, 'sire' => null);
}

/**
 * Décision finale, pure : à partir d'une réponse HTTP DÉJÀ obtenue (jamais elle-même responsable
 * de la requête), retourne soit un N° SIRE valide (forme stricte 8 chiffres + 1 lettre, §4 — cette
 * exigence reste PROPRE à cet extracteur SHF, elle ne resserre jamais la validation générale déjà
 * existante et volontairement permissive du champ `_gwseq_sire`, voir cheval-fields.php), soit ''
 * pour absolument tout autre cas (§7, "fail closed pour l'enrichissement uniquement" : ID inconnu,
 * page générique, HTML inattendu, libellé absent ou valeur invalide à proximité).
 *
 * Reconnaissance de fiche (§7, "ne pas considérer un simple 200 + motif SIRE comme suffisant") :
 * exige HTTP 200 ET un contenu HTML ET (le nom attendu trouvé dans la page OU le libellé "N° SIRE"
 * détecté) — un simple 200 générique sans aucun de ces deux signaux n'est jamais traité comme une
 * fiche reconnue.
 */
function gwseq_ifce_shf_extract_valid_sire($http_code, $content_type, $body, $expected_name = '') {
  if ((int) $http_code !== 200) return '';

  $body = (string) $body;
  $is_html = (stripos((string) $content_type, 'html') !== false) || (strpos(ltrim($body), '<') === 0);
  if (!$is_html) return '';

  $sire_info = gwseq_ifce_shf_extract_sire_from_html($body);
  $expected_name = trim((string) $expected_name);
  $name_found = ($expected_name !== '') && (stripos($body, $expected_name) !== false);
  $recognized = $name_found || $sire_info['found_label'];
  if (!$recognized) return '';

  $sire = $sire_info['sire'];
  if ($sire === null || $sire === '') return '';
  if (!preg_match('/^\d{8}[A-Za-z]$/', $sire)) return '';
  return $sire;
}

/* -------------------------------------------------------------------------------------------
 * Requête réseau réelle — jamais appelée directement (voir gwseq_ifce_shf_fetch_final_response()
 * ci-dessous, le seul point d'entrée réseau de ce fichier, et sa note de testabilité en tête de
 * fichier). Non unitairement testable hors d'un vrai réseau (même limitation déjà documentée et
 * acceptée pour la validation MIME réelle du PDF, voir gwseq_ifce_validate_uploaded_pdf(),
 * ifce-import-admin.php) — sa correction a été vérifiée manuellement sur le POC `poc-shf-panel.php`
 * en conditions réelles (8/8 fiches trouvées, 8/8 SIRE extraits, témoin négatif correctement classé
 * "ID inconnu", voir CR de ce lot).
 * ----------------------------------------------------------------------------------------- */

function gwseq_ifce_shf_single_curl_get($url, $cookie_jar) {
  $ch = curl_init();
  $body = '';
  $truncated = false;
  $headers = array();

  curl_setopt_array($ch, array(
    CURLOPT_URL => $url,
    CURLOPT_HTTPGET => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_RETURNTRANSFER => false,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_CONNECTTIMEOUT => GWSEQ_IFCE_SHF_CONNECT_TIMEOUT,
    CURLOPT_TIMEOUT => GWSEQ_IFCE_SHF_TOTAL_TIMEOUT,
    CURLOPT_USERAGENT => GWSEQ_IFCE_SHF_USER_AGENT,
    CURLOPT_COOKIEJAR => $cookie_jar,
    CURLOPT_COOKIEFILE => $cookie_jar,
    CURLOPT_HEADERFUNCTION => function ($curl, $headerLine) use (&$headers) {
      $headers[] = $headerLine;
      return strlen($headerLine);
    },
    CURLOPT_WRITEFUNCTION => function ($curl, $chunk) use (&$body, &$truncated) {
      if (!$truncated && strlen($body) < GWSEQ_IFCE_SHF_MAX_BYTES) {
        $body .= $chunk;
        if (strlen($body) >= GWSEQ_IFCE_SHF_MAX_BYTES) $truncated = true;
      }
      return strlen($chunk);
    },
  ));

  curl_exec($ch);
  $errno = curl_errno($ch);
  $error = curl_error($ch);
  $http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
  curl_close($ch);

  if ($errno !== 0) throw new RuntimeException('Erreur réseau SHF : ' . $error);

  $location = null;
  foreach ($headers as $h) {
    if (preg_match('/^location:\s*(.+)$/i', trim($h), $m)) $location = trim($m[1]);
  }

  return array('http_code' => $http_code, 'content_type' => $content_type, 'body' => $body, 'location' => $location);
}

/**
 * Suit les redirections manuellement (jamais CURLOPT_FOLLOWLOCATION), en revalidant l'hôte à
 * chaque saut, jusqu'à obtenir une réponse non-redirection ou épuiser GWSEQ_IFCE_SHF_MAX_REDIRECTS.
 * Jarre de cookies temporaire créée puis supprimée dans le même appel (§9, "ne conserve pas de
 * cookie/session entre deux imports") — jamais partagée entre deux appels.
 */
function gwseq_ifce_shf_fetch_final_response_real($url) {
  if (!function_exists('curl_init')) throw new RuntimeException('Extension curl indisponible.');

  $cookie_jar = tempnam(sys_get_temp_dir(), 'gwseq_ifce_shf_');
  try {
    $current = (string) $url;
    for ($i = 0; $i <= GWSEQ_IFCE_SHF_MAX_REDIRECTS; $i++) {
      gwseq_ifce_shf_assert_allowed_url($current);
      $resp = gwseq_ifce_shf_single_curl_get($current, $cookie_jar);
      $is_redirect = in_array($resp['http_code'], array(301, 302, 303, 307, 308), true);
      if (!$is_redirect || empty($resp['location'])) {
        return array('http_code' => $resp['http_code'], 'content_type' => $resp['content_type'], 'body' => $resp['body']);
      }
      if ($i === GWSEQ_IFCE_SHF_MAX_REDIRECTS) throw new RuntimeException('Trop de redirections SHF.');
      $next = gwseq_ifce_shf_resolve_redirect_url($current, $resp['location']);
      gwseq_ifce_shf_assert_allowed_url($next);
      $current = $next;
    }
    throw new RuntimeException('Redirections SHF épuisées.');
  } finally {
    if (is_file($cookie_jar)) @unlink($cookie_jar);
  }
}

/**
 * Point d'entrée réseau UNIQUE (voir note de testabilité en tête de fichier) : consulte d'abord le
 * filtre `gwseq_ifce_shf_fetch_override` (jamais enregistré en production, uniquement par la suite
 * de tests) — un filtre peut retourner soit un tableau `{http_code, content_type, body}` tout fait
 * (simule une réponse HTTP), soit un objet Throwable (simule un échec réseau — timeout, hôte
 * refusé...), relancé tel quel pour que l'appelant final (gwseq_ifce_shf_lookup_sire_by_id()) le
 * traite exactement comme une vraie exception réseau.
 */
function gwseq_ifce_shf_fetch_final_response($url) {
  $override = apply_filters('gwseq_ifce_shf_fetch_override', null, $url);
  if ($override !== null) {
    if ($override instanceof Throwable) throw $override;
    return $override;
  }
  return gwseq_ifce_shf_fetch_final_response_real($url);
}

/* -------------------------------------------------------------------------------------------
 * Point d'entrée métier — seule fonction appelée depuis ifce-import-admin.php.
 * ----------------------------------------------------------------------------------------- */

/**
 * Tente de récupérer le N° SIRE d'un cheval via SHF, à partir de son seul ID IFCE (§2 : jamais un
 * slug construit depuis une donnée GWS — le slug fixe "test" de l'URL suffit, SHF redirige
 * lui-même vers sa fiche canonique). Ne lève JAMAIS d'exception (§6) : retourne '' pour absolument
 * tout cas d'échec ou de doute, un N° SIRE valide sinon. $expected_name (nom officiel si connu,
 * sinon nom d'usage détecté par le PDF) sert uniquement à la reconnaissance de fiche (§7) — jamais
 * réinjecté dans le SIRE retourné.
 */
function gwseq_ifce_shf_lookup_sire_by_id($ifce_id, $expected_name = '') {
  try {
    $ifce_id = trim((string) $ifce_id);
    if (!preg_match('/^[A-Za-z0-9_-]{22}$/', $ifce_id)) return '';

    $url = sprintf(GWSEQ_IFCE_SHF_URL_TEMPLATE, $ifce_id);
    $response = gwseq_ifce_shf_fetch_final_response($url);
    if (!is_array($response)) return '';

    return gwseq_ifce_shf_extract_valid_sire(
      $response['http_code'] ?? 0,
      $response['content_type'] ?? '',
      $response['body'] ?? '',
      $expected_name
    );
  } catch (Throwable $e) {
    // §6 : une indisponibilité ou une modification de SHF ne doit jamais empêcher un import IFCE —
    // aucune exception ne remonte jamais au-delà de cette fonction, quelle qu'en soit la cause.
    return '';
  }
}

/* -------------------------------------------------------------------------------------------
 * Dérivation de l'UELN à partir du SIRE — correctif "UELN Selle Français" (fonctions PURES, aucun
 * rapport avec le réseau — voir la note en tête de fichier).
 * ----------------------------------------------------------------------------------------- */

const GWSEQ_IFCE_UELN_FR_ROOT = '250001';

/**
 * Liste FERMÉE des codes de race/stud-book (référentiel `gwseq_race_referentiel_data()`,
 * race-referentiel.php) pour lesquels la racine UELN française `250001` est confirmée s'appliquer
 * MÊME à un cheval né à l'étranger — Selle Français (`SF`) UNIQUEMENT pour l'instant. Volontairement
 * restreinte : un autre stud-book n'est ajouté ici qu'après validation explicite au cas par cas,
 * jamais par extrapolation ("stud-book français" n'est pas une catégorie fiable en l'état des
 * données GWS/IFCE — voir l'audit documenté dans le CR de ce correctif).
 */
function gwseq_ifce_ueln_eligible_race_codes() {
  return array('SF');
}

/**
 * Dérive l'UELN d'un cheval Selle Français à partir de son SIRE : `250001` + SIRE (ex. SIRE
 * `16398915R` -> UELN `25000116398915R`). Retourne '' pour absolument tout cas non éligible —
 * jamais une valeur partielle ou déduite au-delà de cette seule concaténation (§ "ne transforme, ne
 * complète et n'infère jamais" — même discipline que l'extraction SIRE ci-dessus). Ne vérifie PAS
 * elle-même qu'un UELN existant serait écrasé : c'est la responsabilité de l'appelant (voir
 * gwseq_ifce_map_import(), includes/ifce-import-mapper.php, qui ne l'appelle QUE lorsque l'UELN
 * final — après la fusion non destructive déjà existante — est encore vide).
 */
function gwseq_ifce_derive_ueln_from_sire($race_code, $sire) {
  $sire = trim((string) $sire);
  if (!preg_match('/^\d{8}[A-Za-z]$/', $sire)) return '';
  if (!in_array((string) $race_code, gwseq_ifce_ueln_eligible_race_codes(), true)) return '';
  return GWSEQ_IFCE_UELN_FR_ROOT . $sire;
}

<?php
/**
 * Moteur PDF partagé (Lot "PDF Cheval & Catalogue", Lot 3A — audit + prototype).
 *
 * PRINCIPE ARCHITECTURAL CENTRAL (§15 de la demande, "un seul renderer, jamais deux moteurs") : ce
 * fichier ne contient QUE l'infrastructure PDF générique, indépendante de toute donnée métier —
 * format de page, marges, police, conversion de couleurs, ajustement d'image dans une boîte. Il ne
 * connaît ni "cheval" ni "catalogue" : ces notions vivent exclusivement dans les modules qui
 * consomment ce moteur (includes/cheval-pdf.php de gws-equestrian pour la fiche cheval ; un futur
 * module Catalogue réutilisera EXACTEMENT les mêmes fonctions ci-dessous, jamais une seconde
 * implémentation). Vit dans gws-core (jamais gws-equestrian) pour la même raison que
 * gws_core_structure_identity() : une infrastructure partagée entre modules métier présents et
 * futurs, jamais dupliquée.
 *
 * AUDIT — BIBLIOTHÈQUES PDF DÉJÀ PRÉSENTES (voir CR du lot pour le détail complet) : aucune. Ni
 * gws-core, ni gws-equestrian, ni le thème starter ne contiennent de bibliothèque de GÉNÉRATION PDF
 * — les seuls fichiers du dépôt mentionnant "PDF" avant ce lot concernent l'EXTRACTION de texte
 * depuis un PDF IFCE déjà existant (includes/ifce-pdf-text.php, includes/ifce-production-pdf-text.php,
 * module gws-equestrian) — un besoin totalement différent (lecture, pas écriture) et sans aucun
 * chevauchement de code réutilisable ici.
 *
 * CHOIX TECHNIQUE — TCPDF (tecnickcom/tcpdf, licence LGPL) : vendorisé via Composer
 * (voir composer.json à la racine de ce plugin), jamais un stockage/duplication manuelle de son
 * code source. Retenu plutôt que :
 * - un moteur HTML->PDF (Dompdf/mPDF) : plus simple à mettre en page via des templates HTML/CSS
 *   existants du projet, mais rendu typographique moins maîtrisable pour un document "premium"
 *   (§17 de la demande — hiérarchie fine, chips/badges, mini-arbre de pedigree positionné au pixel
 *   près) et surtout AUCUN de ces deux moteurs ne sait importer/fusionner des PAGES PDF externes
 *   déjà mises en page (besoin explicite du Lot 3C/3E, catalogue "PDF externes") — il aurait fallu
 *   de toute façon ajouter TCPDF (ou FPDF) plus tard pour cette seule fonctionnalité, donc DEUX
 *   moteurs PDF au lieu d'un (interdit explicitement, §15) ;
 * - TCPDF permet un dessin PROCÉDURAL précis (position/taille en mm, comme ce lot en a besoin pour
 *   le hero photo+identité, les chips d'indices, le mini pedigree) tout en restant du texte VECTORIEL
 *   natif (jamais une image rasterisée de la page — recherche/sélection de texte, impression nette,
 *   poids de fichier faible) ;
 * - migration future vers la fusion de PDF externes (Lot 3C/3E) : `setasign/fpdi` (licence MIT,
 *   version communautaire) s'installe par le même Composer, dans le même vendor/, et s'utilise page
 *   par page en import direct sur une instance TCPDF (`Fpdi` étend `TCPDF` — aucune réécriture du
 *   moteur ci-dessous) — VÉRIFIÉ INSTALLABLE dans cet environnement pendant l'audit (non requis
 *   par ce lot 3A, donc non ajouté à composer.json avant d'en avoir réellement besoin, §"pas de
 *   développement prématuré"). Limite connue à documenter/tester au Lot 3C : la version
 *   communautaire de FPDI importe fiablement les PDF jusqu'à la version 1.7 (la vaste majorité des
 *   exports Canva/InDesign/Illustrator), avec des restrictions sur les PDF chiffrés/signés — à
 *   vérifier avec de vrais fichiers utilisateur avant de choisir entre "refuser" et "avertir" (§13
 *   de la demande).
 *
 * POLICES (§13/§17 de la demande, "pas de dépendance de police complexe") : UNIQUEMENT les polices
 * CŒUR standard PDF (Helvetica/Times/Courier — les 14 polices "Base-14", disponibles nativement dans
 * tout lecteur PDF, JAMAIS embarquées dans le fichier généré : poids quasi nul, rendu net à toute
 * résolution). Le dossier fonts/ vendorisé a été volontairement réduit à ces 14 fichiers de métrique
 * (56 Ko au total) — les polices Unicode/CJK embarquables de TCPDF (dejavusans, freeserif, cid0*...)
 * ne sont pas nécessaires ici et auraient ajouté ~25 Mo sans usage. Le jeu de caractères WinAnsi de
 * ces polices cœur couvre nativement le français (accents, œ/æ, €) — vérifié pendant l'audit (voir
 * CR). Une police non-latine (cyrillique, CJK...) dans un nom de cheval resterait hors périmètre V1,
 * à documenter si le cas se présente réellement en recette.
 *
 * TESTABILITÉ (même discipline que le reste du projet) : ce fichier ne fait AUCUN accès disque/
 * réseau au chargement — `gws_core_pdf_load_library()` est appelée explicitement par l'appelant
 * (jamais automatique via un hook), donc absente de tout coût sur une page qui ne génère aucun PDF.
 * `gws_core_pdf_available()` permet à un appelant de dégrader proprement (message d'erreur explicite)
 * si `composer install` n'a jamais été exécuté sur cet environnement (site cloné sans son vendor/,
 * jamais un fatal error WordPress).
 */

if (!defined('ABSPATH')) exit;

const GWS_CORE_PDF_PAGE_FORMAT = 'A4';
const GWS_CORE_PDF_MARGIN_MM = 14; // "marges extérieures généreuses, environ 12-15 mm" (direction de design)

/**
 * true si la bibliothèque PDF vendorisée est présente et chargeable — jamais un fatal error si
 * `composer install` n'a pas été exécuté sur cet environnement (site cloné, vendor/ absent car
 * gitignoré, voir .gitignore à la racine du dépôt et composer.json de ce plugin).
 */
function gws_core_pdf_available() {
  if (class_exists('TCPDF')) return true;
  $autoload = GWS_CORE_DIR . 'vendor/autoload.php';
  return is_readable($autoload);
}

/**
 * Charge la bibliothèque vendorisée — idempotente, sans effet si déjà chargée. Ne lève jamais
 * d'exception : retourne simplement false si le vendor/ est absent, à l'appelant de décider
 * (message d'erreur explicite, jamais un fatal error WordPress sur un site qui n'a jamais exécuté
 * `composer install`).
 */
function gws_core_pdf_load_library() {
  if (class_exists('TCPDF')) return true;
  $autoload = GWS_CORE_DIR . 'vendor/autoload.php';
  if (!is_readable($autoload)) return false;
  require_once $autoload;
  return class_exists('TCPDF');
}

/**
 * Convertit une couleur hexadécimale '#rrggbb' (format déjà garanti par
 * gws_core_field_sanitize('color', ...), voir includes/fields.php) en triplet RGB [0-255] attendu
 * par les méthodes SetTextColor()/SetFillColor()/SetDrawColor() de TCPDF. Repli sur le noir pour
 * toute valeur qui ne respecte pas ce format — même garde défensive que gws_core_contrast_color()
 * (includes/settings.php), jamais dupliquée en logique, seulement en forme de sortie (tableau RGB
 * plutôt qu'un choix noir/blanc).
 */
function gws_core_pdf_hex_to_rgb($hex_color) {
  $hex = ltrim((string) $hex_color, '#');
  if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) return array(0, 0, 0);
  return array(hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)));
}

/**
 * Applique une couleur de texte RGB (voir gws_core_pdf_hex_to_rgb() ci-dessus) sur $pdf — simple
 * enveloppe évitant à chaque appelant de ré-écrire `list($r, $g, $b) = ...; $pdf->SetTextColor(...)`.
 */
function gws_core_pdf_set_text_color($pdf, $hex_color) {
  list($r, $g, $b) = gws_core_pdf_hex_to_rgb($hex_color);
  $pdf->SetTextColor($r, $g, $b);
}

function gws_core_pdf_set_draw_color($pdf, $hex_color) {
  list($r, $g, $b) = gws_core_pdf_hex_to_rgb($hex_color);
  $pdf->SetDrawColor($r, $g, $b);
}

function gws_core_pdf_set_fill_color($pdf, $hex_color) {
  list($r, $g, $b) = gws_core_pdf_hex_to_rgb($hex_color);
  $pdf->SetFillColor($r, $g, $b);
}

/**
 * Crée un nouveau document PDF A4 portrait configuré pour un rendu procédural "propre" (§1 de la
 * demande) — aucune page ajoutée ici (à l'appelant d'ajouter ses pages via $pdf->AddPage(), qui
 * peut différer selon le contexte : une fiche cheval individuelle ajoute une seule page, un futur
 * catalogue en ajoute plusieurs à la suite, TOUJOURS sur ce même document). En-tête/pied de page
 * automatiques de TCPDF désactivés : chaque renderer dessine intégralement sa propre page (§2/§11,
 * header et footer entièrement pilotés par l'identité de "Ma structure"), jamais un gabarit
 * générique superposé.
 *
 * $creator_name : nom affiché dans les métadonnées du PDF (Créateur/Auteur) — l'appelant passe
 * typiquement gws_core_structure_name() (includes/settings.php), jamais une valeur codée en dur
 * ici (ce fichier ignore tout ce qui est "Ma structure").
 */
function gws_core_pdf_new_document($orientation = 'P', $creator_name = 'GWS') {
  if (!gws_core_pdf_load_library()) {
    throw new RuntimeException('Bibliothèque PDF indisponible (vendor/ absent — exécuter `composer install --no-dev` dans wp-content/plugins/gws-core/).');
  }

  $pdf = new TCPDF($orientation, 'mm', GWS_CORE_PDF_PAGE_FORMAT, true, 'UTF-8', false);
  $pdf->SetCreator((string) $creator_name);
  $pdf->SetAuthor((string) $creator_name);
  $pdf->setPrintHeader(false);
  $pdf->setPrintFooter(false);
  $pdf->SetMargins(GWS_CORE_PDF_MARGIN_MM, GWS_CORE_PDF_MARGIN_MM, GWS_CORE_PDF_MARGIN_MM);
  $pdf->SetAutoPageBreak(false, 0); // débordement contrôlé explicitement par chaque renderer (§18 : "aucun débordement hors page n'est acceptable" — jamais laissé à un saut de page automatique implicite)
  $pdf->setFontSubsetting(false); // polices cœur uniquement (voir docblock de fichier), jamais de sous-ensembre à calculer
  $pdf->SetFont('helvetica', '', 10);
  return $pdf;
}

/**
 * Éclaircit une couleur hexadécimale vers le blanc selon $amount (0 = couleur d'origine, 1 =
 * blanc pur) — utilisée pour des fonds très légers (chips, bandeaux discrets, §12 de la direction
 * de design : "jamais de grande surface saturée"), jamais pour du texte (toujours
 * gws_core_pdf_hex_to_rgb() telle quelle pour un texte, qui doit rester lisible).
 */
function gws_core_pdf_lighten_color($hex_color, $amount = 0.85) {
  list($r, $g, $b) = gws_core_pdf_hex_to_rgb($hex_color);
  $amount = max(0, min(1, (float) $amount));
  return array(
    (int) round($r + (255 - $r) * $amount),
    (int) round($g + (255 - $g) * $amount),
    (int) round($b + (255 - $b) * $amount),
  );
}

/**
 * Dimensions (largeur, hauteur en mm) d'une image existante sur disque, mises à l'échelle pour
 * tenir dans une boîte $max_w x $max_h SANS déformation (ratio conservé, §19 "recadrage
 * raisonnable" — ceci ne recadre pas, seul un ajustement proportionnel ; un recadrage éventuel
 * reste la responsabilité de l'appelant AVANT résolution du chemin, ex. un futur usage de
 * wp_get_attachment_image_src() avec une taille déjà recadrée par WordPress). Retourne
 * `['w' => ..., 'h' => ...]` en mm, ou null si l'image est illisible/invalide — jamais une
 * exception, un renderer doit pouvoir se replier proprement (§19 "fallback si aucune image").
 */
function gws_core_pdf_fit_image_box($image_path, $max_w, $max_h) {
  if (!is_readable($image_path)) return null;
  $size = @getimagesize($image_path);
  if (!$size || empty($size[0]) || empty($size[1])) return null;

  $ratio = $size[0] / $size[1];
  $box_ratio = $max_w / $max_h;

  if ($ratio > $box_ratio) {
    $w = $max_w;
    $h = $max_w / $ratio;
  } else {
    $h = $max_h;
    $w = $max_h * $ratio;
  }
  return array('w' => $w, 'h' => $h);
}

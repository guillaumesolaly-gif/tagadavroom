<?php
/**
 * Partager un cheval — couche métier (Étape 8, lot « Partager un cheval »).
 *
 * PRINCIPE FONDAMENTAL (§2 de la demande) : aucune invention. Ce fichier ne fait QUE lire des
 * données déjà existantes ailleurs dans le module (identité, commercialisation, médias, indices,
 * pedigree, éditorial) et les composer en un texte commercial lisible — jamais de contenu généré
 * automatiquement (pas d'IA, pas de qualité/potentiel/niveau sportif inventé), jamais un
 * `get_post_meta($id)` en bloc. Une information absente ne produit jamais de ligne vide ni de
 * texte de repli — elle est simplement absente du résultat (voir chaque helper `..._label()`
 * ci-dessous, qui renvoie toujours '' plutôt que fabriquer un texte).
 *
 * SÉPARATION DES RESPONSABILITÉS (§4 de la demande) :
 *   Cheval (meta déjà existantes, réutilisées SANS DUPLICATION de logique métier)
 *     -> gwseq_get_horse_shareable_data()   [ce fichier — QUOI peut être partagé, et son libellé]
 *     -> sélection utilisateur              [écran BO — includes/cheval-share-admin.php]
 *     -> gwseq_build_horse_share_message()  [ce fichier — COMMENT ça devient un message]
 *     -> WhatsApp / SMS / Copier            [assets/cheval-share-admin.js — n'ouvre/ne copie
 *                                             qu'un texte déjà entièrement composé ici, jamais une
 *                                             reconstruction séparée par canal]
 * Ce découplage est délibérément pensé pour être réutilisé tel quel par les futures évolutions
 * annoncées (lien privé, PDF commercial, sélection de plusieurs chevaux, catalogue) — sans qu'aucune
 * de ces deux fonctions n'ait besoin de connaître wp-admin, WhatsApp, ou un quelconque canal de
 * sortie. Volontairement PAS de moteur marketing générique ni d'abstraction supplémentaire : deux
 * fonctions, une structure de données explicite, rien de plus que ce que ce lot exige réellement.
 *
 * AUCUNE PERSISTANCE (§22 de la demande) : ni CPT, ni table, ni historique, ni destinataire, ni
 * statut d'envoi. Un partage est entièrement éphémère — ce fichier ne contient donc AUCUN
 * `update_post_meta()`, AUCUN `wp_insert_post()`. La sélection de l'utilisateur et son éventuel
 * message personnel ne transitent que par la requête AJAX de composition (voir
 * includes/cheval-share-admin.php), jamais enregistrés nulle part.
 *
 * CONFIDENTIALITÉ (§13/§20) : un cheval non publiquement visible (brouillon, protégé par mot de
 * passe, corbeille...) n'expose jamais de lien de fiche — voir gwseq_horse_is_publicly_viewable().
 * Aucune donnée sensible (prix, statut commercial, éleveur/propriétaire, UELN/SIRE) n'est jamais
 * incluse dans les métadonnées Open Graph (voir gwseq_horse_og_description() plus bas) : seules des
 * données déjà publiques une fois la fiche visible (identité, origines, accroche) y figurent.
 */

if (!defined('ABSPATH')) exit;

/**
 * Décode les entités HTML (nommées et numériques, ex. `&rsquo;`, `&#8217;`, `&amp;`) d'un titre de
 * cheval AVANT toute utilisation dans ce module — correctif recette : certains titres contiennent
 * l'entité sous forme de texte littéral (et non le caractère qu'elle représente), ce que
 * `get_the_title()` ne corrige pas systématiquement. Point de décodage UNIQUE (principe
 * "décoder une fois, échapper au bon endroit ensuite") : chaque consommateur applique ensuite son
 * propre échappement contextuel (texte brut pour WhatsApp/SMS/Copier — aucun échappement
 * supplémentaire nécessaire ; `esc_attr()` pour Open Graph ; `textContent` côté JS, qui n'interprète
 * jamais de HTML). Volontairement `html_entity_decode()` (pas `wp_specialchars_decode()`, qui ne
 * couvre que les 5 entités XML de base et ne décoderait pas `&rsquo;`) avec `ENT_HTML5` pour la
 * table complète des entités nommées HTML5. Décodage à la volée à l'affichage seulement — ne
 * modifie JAMAIS le titre en base (aucun `wp_update_post()` ici).
 */
function gwseq_horse_share_decode_title($raw_title) {
  return html_entity_decode((string) $raw_title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/* -------------------------------------------------------------------------------------------
 * Vocabulaire commercial du sexe (§8 de la demande — "Jument Selle Français — 7 ans").
 *
 * Distinct du libellé ADMINISTRATIF déjà utilisé ailleurs dans le BO (gwseq_cheval_sexe_options(),
 * "Mâle"/"Femelle"/"Hongre" — colonnes de liste, filtres, formulaire d'identité) : un partage
 * commercial adressé à un client utilise le vocabulaire naturel du métier équin ("Jument"/
 * "Étalon"/"Hongre"), jamais le terme générique "Femelle"/"Mâle". Décision produit assumée, pas une
 * incohérence — RÉUTILISE exactement les mêmes clés techniques ('male'/'female'/'gelding') que
 * l'enum existante, aucun nouveau champ, aucune duplication de la logique de stockage/sanitation de
 * l'identité (cheval-fields.php).
 * ----------------------------------------------------------------------------------------- */
function gwseq_horse_share_sexe_commercial_label($sexe) {
  $labels = array(
    'male' => __('Étalon', 'gws-core'),
    'female' => __('Jument', 'gws-core'),
    'gelding' => __('Hongre', 'gws-core'),
  );
  return $labels[$sexe] ?? '';
}

/**
 * "Jument Selle Français — 7 ans" : sexe (vocabulaire commercial) + race/stud-book + âge, chacun
 * INDÉPENDAMMENT facultatif — n'importe quelle combinaison présente/absente reste une composition
 * valide (ex. "Jument — 7 ans" sans race connue, ou "Selle Français" seul sans sexe ni âge connus).
 * Réutilise gwseq_cheval_race_label()/gwseq_cheval_age_from_birth_year()/gwseq_cheval_age_label()
 * (cheval-fields.php) — jamais un second calcul de race ou d'âge.
 */
function gwseq_horse_share_identite_label($identity) {
  $sexe_label = gwseq_horse_share_sexe_commercial_label($identity['sexe'] ?? '');
  $race_label = gwseq_cheval_race_label($identity['race'] ?? '', $identity['race_autre'] ?? '');

  $tete = trim(implode(' ', array_filter(array($sexe_label, $race_label), function ($v) { return $v !== ''; })));

  $age = gwseq_cheval_age_from_birth_year($identity['annee_naissance'] ?? '');
  $age_label = $age !== '' ? gwseq_cheval_age_label($age) : '';

  if ($tete === '' && $age_label === '') return '';
  if ($tete === '') return $age_label;
  if ($age_label === '') return $tete;
  return $tete . ' — ' . $age_label;
}

/**
 * "Par UNTOUCHABLE × KANNAN" : Père (génération 1) × Père de la mère (génération 2, "damsire" —
 * §8 de la demande : "père ; père de mère / origines directes pertinentes") — PAS mère × père,
 * volontairement : c'est la convention de présentation la plus lisible et la plus reconnue du
 * marché du cheval de sport ("Par [étalon], mère par [étalon]"), qui met en avant les deux
 * étalons plutôt qu'un nom de poulinière souvent moins identifiable commercialement. Réutilise
 * gwseq_resolve_horse_pedigree() (pedigree-resolver.php, max_depth=2 suffit : génération 1 = père/
 * mère, génération 2 = leurs propres parents) et gwseq_format_horse_name_display() (convention de
 * présentation déjà en place pour tout nom de cheval dans le pedigree) — jamais un second calcul de
 * filiation. Un nœud "unavailable"/"cycle_detected" (référence cassée) n'est JAMAIS présenté dans
 * un message commercial : traité comme une absence de donnée, pas comme une erreur à afficher.
 */
function gwseq_horse_share_pedigree_node_name($node) {
  if (!is_array($node)) return '';
  if (!in_array($node['type'] ?? '', array('gws_horse', 'external'), true)) return '';
  return gwseq_format_horse_name_display(gwseq_horse_share_decode_title($node['name'] ?? ''));
}

function gwseq_horse_share_origines_label($cheval_id) {
  $pedigree = gwseq_resolve_horse_pedigree($cheval_id, 2);

  $sire = gwseq_horse_share_pedigree_node_name($pedigree['father'] ?? null);
  $mother = $pedigree['mother'] ?? null;
  $damsire = is_array($mother) ? gwseq_horse_share_pedigree_node_name($mother['father'] ?? null) : '';

  if ($sire === '' && $damsire === '') return '';
  if ($sire !== '' && $damsire !== '') return sprintf('%s %s × %s', __('Par', 'gws-core'), $sire, $damsire);
  return sprintf('%s %s', __('Par', 'gws-core'), $sire !== '' ? $sire : $damsire);
}

/**
 * "1,68 m • ISO 135" : taille (convertie en mètres, virgule française) + UN SEUL indice sportif
 * mis en avant (priorité ISO > ICC > IDR, le premier réellement renseigné) — chacun indépendamment
 * facultatif. Volontairement UN SEUL indice, pas les trois systématiquement : §8 de la demande
 * ("composition humaine et concise, pas un export de base de données") — présenter mécaniquement
 * ISO+ICC+IDR à chaque partage alourdirait une ligne pensée pour rester lisible en une seconde.
 * Décision produit documentée (voir CHANGELOG.md/CR de livraison), pas un oubli : les indices
 * génétiques (BSO/BCC/BDR) ne sont volontairement PAS inclus dans cette V1 du partage (§8 : "si
 * cela a réellement du sens" — leur lecture nécessite un minimum d'expertise, hors du public
 * généraliste ciblé par un message WhatsApp/SMS ; à réévaluer si un besoin réel apparaît).
 * Seule la VALEUR de l'indice est montrée (jamais l'année ni le CD) — cohérent avec l'exemple de
 * référence du lot ("ISO 135", jamais "ISO 135 (2023)").
 */
function gwseq_horse_share_taille_indice_label($cheval_id, $identity) {
  $taille_cm = $identity['taille_cm'] ?? '';
  $taille_label = $taille_cm !== '' ? number_format(((float) $taille_cm) / 100, 2, ',', '') . ' ' . __('m', 'gws-core') : '';

  $indice_label = '';
  foreach (array('iso', 'icc', 'idr') as $key) {
    $indice = gwseq_get_cheval_sport_indice($cheval_id, $key);
    if (($indice['valeur'] ?? '') !== '') {
      $indice_label = strtoupper($key) . ' ' . $indice['valeur'];
      break;
    }
  }

  if ($taille_label === '' && $indice_label === '') return '';
  if ($taille_label === '') return $indice_label;
  if ($indice_label === '') return $taille_label;
  return $taille_label . ' • ' . $indice_label;
}

/**
 * Règle statut/prix (§10 de la demande) : un prix techniquement enregistré ne signifie jamais qu'il
 * doit être proposé au partage. Seuls les statuts commerciaux "À vendre" et "Réservé" — un contexte
 * commercial réellement ACTIF — rendent cette information pertinente à transmettre ; "Non proposé"
 * et "Vendu" ne l'exposent JAMAIS, quel que soit le prix enregistré en base (le partage ne contourne
 * jamais le statut commercial, il le respecte strictement). Libellé : "{Statut} — {prix résumé}",
 * réutilise gwseq_cheval_price_summary() (cheval-fields.php) — jamais un second calcul de prix.
 */
function gwseq_horse_share_prix_eligible_statuts() {
  return array('for_sale', 'reserved');
}

function gwseq_horse_share_prix_label($commercial) {
  $statut = $commercial['statut_commercial'] ?? 'not_offered';
  if (!in_array($statut, gwseq_horse_share_prix_eligible_statuts(), true)) return '';

  $summary = gwseq_cheval_price_summary($commercial, gwseq_get_currency());
  if ($summary === '') return '';

  $statut_label = gwseq_cheval_statut_commercial_options()[$statut] ?? '';
  return $statut_label !== '' ? ($statut_label . ' — ' . $summary) : $summary;
}

/**
 * Libellé d'une vidéo (§11 de la demande) : jamais un titre inventé — "{titre}" si un titre a été
 * saisi par le professionnel, sinon "Vidéo" — jamais "Parcours"/"Modèle"/"Allures"/"Travail" par
 * défaut, qui supposerait le contenu réel de la vidéo.
 *
 * Correctif de recette (test réel WhatsApp) : le pictogramme 🎥 qui préfixait ce libellé n'était
 * pas transporté de façon fiable vers WhatsApp sur un appareil réel (transformé en caractère de
 * remplacement invalide "�") — retiré purement et simplement pour cette V1, sans le remplacer par
 * un autre emoji (le titre de la vidéo suffit à lui seul à identifier la ligne). Cette fonction
 * étant l'unique source du libellé pour l'aperçu BO ET les trois canaux externes (WhatsApp/SMS/
 * Copier), ce retrait s'applique uniformément partout sans aucun autre changement de code : l'aperçu
 * représente ainsi fidèlement le message réellement envoyé (§2 de ce correctif).
 */
function gwseq_horse_share_video_label($video) {
  $titre = trim((string) ($video['titre'] ?? ''));
  return $titre !== '' ? $titre : __('Vidéo', 'gws-core');
}

/**
 * Nombre de vidéos présélectionnées par défaut (§11 : "on peut présélectionner seulement les
 * premières vidéos par défaut, mais ne pas imposer une limite artificielle au nombre de vidéos que
 * l'utilisateur peut choisir") — une PRÉSÉLECTION, jamais un plafond : toute vidéo réellement
 * présente sur la fiche reste sélectionnable dans gwseq_get_horse_shareable_data() ci-dessous, quel
 * que soit leur nombre (jusqu'à GWSEQ_CHEVAL_VIDEOS_MAX, la seule vraie limite, fixée par
 * cheval-media.php et totalement indépendante de ce réglage de présélection).
 */
const GWSEQ_HORSE_SHARE_VIDEOS_PRESELECTED = 2;

/**
 * Une fiche est publiquement visible (§13/§20) si et seulement si son statut WordPress est
 * "publish" ET qu'elle n'est pas protégée par un mot de passe — jamais un brouillon, une révision,
 * une fiche en attente/privée, ni un contenu protégé, quel que soit son ID ou son ancienneté.
 * Aucun nouveau statut ni verrou n'est inventé : ce sont les deux seuls mécanismes NATIFS WordPress
 * qui déterminent réellement si un visiteur non connecté peut consulter cette page.
 */
function gwseq_horse_is_publicly_viewable($cheval_id) {
  $post = get_post($cheval_id);
  if (!$post || $post->post_type !== GWSEQ_CPT_CHEVAL) return false;
  if ($post->post_status !== 'publish') return false;
  if ($post->post_password !== '') return false;
  return true;
}

/* -------------------------------------------------------------------------------------------
 * Partage privé (suite V1 « Partager & vendre », Lot 1 — visibilité public/privé).
 *
 * Un cheval que le professionnel ne veut PAS exposer publiquement (catalogue, recherche, sitemap)
 * doit pouvoir être envoyé quand même à des acheteurs précis, sans jamais "publier la fiche puis la
 * retirer des menus" : un vrai mécanisme de lien secret, distinct du statut WordPress natif.
 *
 * Le TOKEN est l'unique clé d'accès : 32 octets générés par random_bytes() (source
 * cryptographiquement sûre du PHP natif — 64 caractères hexadécimaux, non énumérable), JAMAIS
 * l'ID WordPress ni le Global Horse ID (identifiants métier prévisibles, qui ne doivent jamais
 * devenir un secret d'accès). Stocké dans UNE SEULE meta postmeta déjà existante comme mécanisme
 * de stockage (aucune nouvelle table) : sa seule PRÉSENCE non vide fait office de drapeau
 * "partage privé actif" — aucune meta booléenne séparée à garder synchronisée avec elle.
 * Révoquer = supprimer la meta. Régénérer = simplement écraser l'ancien token par un nouveau :
 * la même opération sert les deux besoins, l'ancien lien cesse instantanément de fonctionner
 * puisque plus aucune fiche ne porte alors cette valeur (voir
 * gwseq_horse_private_share_find_cheval_id()).
 * ----------------------------------------------------------------------------------------- */
const GWSEQ_HORSE_PRIVATE_SHARE_META_KEY = '_gwseq_partage_prive_token';

function gwseq_horse_private_share_generate_token() {
  return bin2hex(random_bytes(32));
}

function gwseq_horse_private_share_token($cheval_id) {
  return (string) get_post_meta((int) $cheval_id, GWSEQ_HORSE_PRIVATE_SHARE_META_KEY, true);
}

function gwseq_horse_private_share_is_active($cheval_id) {
  return gwseq_horse_private_share_token($cheval_id) !== '';
}

/**
 * AJUSTEMENT D'ARCHITECTURE (recette) : la visibilité publique et l'existence d'un lien privé sont
 * désormais DÉCOUPLÉES — un token ne doit jamais dégrader ni masquer une fiche publique valide (la
 * priorité "privé > public" initiale s'est révélée trop risquée : créer un lien privé sur un cheval
 * déjà publié rendait involontairement son permalink public inaccessible). Un cheval est en mode
 * "partage privé EXCLUSIF" si et seulement si il n'est PAS publiquement visible ET qu'un partage
 * privé est actif pour lui : c'est ce mode précis, et lui seul, qui justifie tout traitement
 * "privé" (route dédiée avec noindex...). Un cheval RÉELLEMENT public avec un ancien token qui
 * traîne reste un cheval public à tous égards — le token n'existe alors que pour ne pas casser un
 * lien déjà envoyé (voir gwseq_horse_share_fiche_info() ci-dessous), jamais pour rien retirer à sa
 * présence publique (recherche, catalogue, sitemap, API REST — tous déjà nativement limités aux
 * fiches réellement publiées par WordPress, sans code supplémentaire nécessaire ici).
 */
function gwseq_horse_is_private_share_only($cheval_id) {
  return !gwseq_horse_is_publicly_viewable($cheval_id) && gwseq_horse_private_share_is_active($cheval_id);
}

/**
 * AUDIT UX/MÉTIER (recette suivante) : le client ne doit jamais avoir à comprendre le vocabulaire
 * WordPress `Brouillon/Publié` pour savoir où en est une fiche. En interne, on conserve strictement
 * les DEUX SEULS mécanismes natifs déjà en place (`post_status`/`post_password` via
 * gwseq_horse_is_publicly_viewable(), et le token via gwseq_horse_private_share_is_active()) —
 * aucun statut personnalisé créé pour ça (`register_post_status()` volontairement absent : pas de
 * nécessité démontrée, cf. gwseq_horse_is_private_share_only() ci-dessus qui suffisait déjà à couvrir
 * le même besoin). Trois états MÉTIER, dérivés de ces deux mécanismes, exactement dans cet ordre de
 * priorité (une fiche publique reste "Visible sur le site" même si un ancien token traîne encore —
 * §5 de l'ajustement d'architecture précédent, jamais remis en cause ici) :
 *   1. Visible sur le site   -> gwseq_horse_is_publicly_viewable() vrai.
 *   2. Diffusion privée      -> pas publiquement visible, MAIS un token actif existe
 *                                (= gwseq_horse_is_private_share_only()).
 *   3. En préparation        -> ni l'un ni l'autre (brouillon jamais partagé, à aucun titre).
 * Fonction UNIQUE et centralisée (§ de la demande : "le futur écran mobile puisse utiliser
 * exactement la même logique") — tout consommateur (boîte BO, liste d'administration, un jour REST/
 * mobile) doit appeler CETTE fonction, jamais recalculer indépendamment ces conditions.
 */
const GWSEQ_HORSE_DIFFUSION_EN_PREPARATION = 'en_preparation';
const GWSEQ_HORSE_DIFFUSION_PRIVEE = 'diffusion_privee';
const GWSEQ_HORSE_DIFFUSION_VISIBLE_SITE = 'visible_site';

function gwseq_horse_diffusion_state($cheval_id) {
  if (gwseq_horse_is_publicly_viewable($cheval_id)) return GWSEQ_HORSE_DIFFUSION_VISIBLE_SITE;
  if (gwseq_horse_private_share_is_active($cheval_id)) return GWSEQ_HORSE_DIFFUSION_PRIVEE;
  return GWSEQ_HORSE_DIFFUSION_EN_PREPARATION;
}

/**
 * Libellé métier affiché au professionnel — jamais "Brouillon"/"Publié" (vocabulaire WordPress),
 * jamais recomposé ailleurs qu'ici (BO et liste d'administration réutilisent ce SEUL libellé).
 */
function gwseq_horse_diffusion_state_label($state) {
  $labels = array(
    GWSEQ_HORSE_DIFFUSION_EN_PREPARATION => __('En préparation', 'gws-core'),
    GWSEQ_HORSE_DIFFUSION_PRIVEE => __('Diffusion privée', 'gws-core'),
    GWSEQ_HORSE_DIFFUSION_VISIBLE_SITE => __('Visible sur le site', 'gws-core'),
  );
  return $labels[$state] ?? '';
}

/**
 * Crée OU régénère le partage privé d'un cheval — toujours la même opération (voir le commentaire
 * de section ci-dessus) : un appelant n'a jamais besoin de distinguer les deux cas.
 */
function gwseq_horse_private_share_activate($cheval_id) {
  $token = gwseq_horse_private_share_generate_token();
  update_post_meta((int) $cheval_id, GWSEQ_HORSE_PRIVATE_SHARE_META_KEY, $token);
  return $token;
}

function gwseq_horse_private_share_revoke($cheval_id) {
  delete_post_meta((int) $cheval_id, GWSEQ_HORSE_PRIVATE_SHARE_META_KEY);
}

/**
 * Chemin technique de la route de partage privé — centralisé ICI (jamais reconstruit ailleurs,
 * y compris côté règle de réécriture, voir includes/cheval-share-admin.php, qui réutilise cette
 * même constante de base). Slash de fin systématique, cohérent avec les permaliens WordPress
 * existants ("chevaux/xxx/").
 */
const GWSEQ_HORSE_PRIVATE_SHARE_REWRITE_BASE = 'partage';

function gwseq_horse_private_share_url($cheval_id) {
  $token = gwseq_horse_private_share_token($cheval_id);
  return $token !== '' ? home_url('/' . GWSEQ_HORSE_PRIVATE_SHARE_REWRITE_BASE . '/' . $token . '/') : '';
}

/**
 * Recherche inverse token -> cheval (§ "accessible sans compte par quelqu'un possédant le lien").
 * Le format est vérifié AVANT toute requête (64 hexadécimaux minuscules, exactement ce que produit
 * gwseq_horse_private_share_generate_token()) : rejette immédiatement toute valeur qui ne pourra de
 * toute façon jamais correspondre à un token réel — défense en profondeur, jamais une requête pour
 * une chaîne à l'évidence invalide. `post_status => 'any'` volontairement : un partage privé doit
 * fonctionner MÊME pour un cheval resté en brouillon (§2.C — le brouillon reste inaccessible par son
 * permalink normal, mais un partage privé explicitement créé pour lui doit, lui, fonctionner).
 */
function gwseq_horse_private_share_find_cheval_id($token) {
  $token = (string) $token;
  if (!preg_match('/^[a-f0-9]{64}$/', $token)) return 0;

  $query = new WP_Query(array(
    'post_type' => GWSEQ_CPT_CHEVAL,
    'post_status' => 'any',
    'fields' => 'ids',
    'posts_per_page' => 1,
    'meta_query' => array(
      array('key' => GWSEQ_HORSE_PRIVATE_SHARE_META_KEY, 'value' => $token, 'compare' => '='),
    ),
  ));
  return $query->posts ? (int) $query->posts[0] : 0;
}

/**
 * Détermine le lien de fiche approprié SANS jamais faire porter ce choix à l'utilisateur (§3 de la
 * demande — "GWS doit déterminer le lien approprié selon la visibilité du cheval") : lien PUBLIC en
 * priorité dès que la fiche est réellement publique — un token privé existant, même actif, ne doit
 * JAMAIS masquer une fiche publique valide (ajustement d'architecture, voir gwseq_horse_is_private_
 * share_only() ci-dessus) — sinon lien PRIVÉ si un partage privé est actif (le cheval n'a alors
 * aucun autre canal), sinon aucun lien. Le TYPE renvoyé ('publique'/'privee'/'') sert uniquement à
 * adapter le VOCABULAIRE de l'écran BO (§3) — jamais le contenu du message lui-même, strictement
 * identique quel que soit le type de lien inclus.
 */
function gwseq_horse_share_fiche_info($cheval_id) {
  if (gwseq_horse_is_publicly_viewable($cheval_id)) {
    return array('url' => get_permalink($cheval_id), 'type' => 'publique');
  }
  if (gwseq_horse_private_share_is_active($cheval_id)) {
    return array('url' => gwseq_horse_private_share_url($cheval_id), 'type' => 'privee');
  }
  return array('url' => '', 'type' => '');
}

function gwseq_horse_share_fiche_url($cheval_id) {
  return gwseq_horse_share_fiche_info($cheval_id)['url'];
}

/**
 * Point d'entrée central (§4/§8/§9 de la demande) : à partir des SEULES données déjà existantes
 * d'une fiche cheval, détermine ce qui est réellement partageable, avec son libellé déjà composé et
 * sa présélection par défaut. Un consommateur (écran BO aujourd'hui, PDF/lien privé/sélection
 * demain) ne lit JAMAIS de meta brute : uniquement cette structure fermée. Une entrée n'apparaît
 * dans 'items'/'videos' QUE si une donnée réelle existe (§2 : "champ absent = information
 * absente") — jamais une case présente mais vide.
 *
 * Présélection par défaut (§9) : identité/origines/taille_indice/accroche cochées par défaut
 * lorsqu'elles existent, prix JAMAIS coché par défaut (prudence commerciale explicitement demandée,
 * y compris pour tout futur item qui serait un jour considéré sensible), fiche complète cochée par
 * défaut lorsqu'un lien public existe. Les N premières vidéos (GWSEQ_HORSE_SHARE_VIDEOS_PRESELECTED)
 * sont cochées par défaut, sans jamais limiter le nombre de vidéos sélectionnables.
 */
function gwseq_get_horse_shareable_data($cheval_id) {
  $cheval_id = (int) $cheval_id;
  $identity = gwseq_get_cheval_identity($cheval_id);
  $commercial = gwseq_get_cheval_commercial($cheval_id);
  $editorial = gwseq_get_cheval_editorial($cheval_id);

  $items = array();

  $identite_label = gwseq_horse_share_identite_label($identity);
  if ($identite_label !== '') $items['identite'] = array('label' => $identite_label, 'default_checked' => true);

  $origines_label = gwseq_horse_share_origines_label($cheval_id);
  if ($origines_label !== '') $items['origines'] = array('label' => $origines_label, 'default_checked' => true);

  $taille_indice_label = gwseq_horse_share_taille_indice_label($cheval_id, $identity);
  if ($taille_indice_label !== '') $items['taille_indice'] = array('label' => $taille_indice_label, 'default_checked' => true);

  $prix_label = gwseq_horse_share_prix_label($commercial);
  if ($prix_label !== '') $items['prix'] = array('label' => $prix_label, 'default_checked' => false);

  $accroche = trim((string) ($editorial['accroche_commerciale'] ?? ''));
  if ($accroche !== '') $items['accroche'] = array('label' => $accroche, 'default_checked' => true);

  $videos = array();
  foreach (gwseq_get_cheval_videos($cheval_id) as $index => $video) {
    $videos[] = array(
      'index' => $index,
      'label' => gwseq_horse_share_video_label($video),
      'url' => $video['url'],
      'default_checked' => $index < GWSEQ_HORSE_SHARE_VIDEOS_PRESELECTED,
    );
  }

  $fiche_info = gwseq_horse_share_fiche_info($cheval_id);
  $fiche_url = $fiche_info['url'];

  return array(
    'id' => $cheval_id,
    'nom' => gwseq_horse_share_decode_title(get_the_title($cheval_id)),
    'nom_affiche' => gwseq_format_horse_name_display(gwseq_horse_share_decode_title(get_the_title($cheval_id))),
    'photo_url' => wp_get_attachment_image_url(gwseq_get_cheval_photo_principale_id($cheval_id), 'medium') ?: '',
    'items' => $items,
    'videos' => $videos,
    'fiche_url' => $fiche_url,
    'fiche_type' => $fiche_info['type'],
    'fiche_default_checked' => $fiche_url !== '',
  );
}

/**
 * Compose le message final (§14 de la demande) — plain-text uniquement, JAMAIS de HTML. `$selection`
 * (fournie par l'écran BO, jamais du contenu libre pour les lignes structurées — seule
 * 'message_personnel' est un texte libre de l'utilisateur) :
 *   [
 *     'items' => ['identite', 'origines', ...],   // clés de gwseq_get_horse_shareable_data()['items']
 *     'videos' => [0, 2, ...],                     // index de gwseq_get_horse_shareable_data()['videos']
 *     'fiche' => true|false,
 *     'message_personnel' => '...',                // déjà sanitisé par l'appelant (texte simple)
 *   ]
 * Construit par BLOCS, joints par une ligne vide ("\n\n") : (1) message personnel éventuel,
 * (2) nom de la fiche + lignes structurées sélectionnées (identite/origines/taille_indice/prix,
 * dans cet ordre, sans ligne vide entre elles — un seul bloc compact), (3) accroche commerciale
 * si sélectionnée (son propre paragraphe), (4) vidéos sélectionnées (une ligne par vidéo,
 * "{libellé} : {url}"), (5) lien vers la fiche complète si sélectionné, précédé d'une phrase
 * d'intitulé fixe. Le nom de la fiche (bloc 2) est TOUJOURS inclus, indépendamment de toute
 * sélection : un partage sans même le nom du cheval n'aurait aucun sens commercial.
 */
function gwseq_build_horse_share_message($shareable, $selection) {
  $selection = is_array($selection) ? $selection : array();
  $selected_items = is_array($selection['items'] ?? null) ? $selection['items'] : array();
  $selected_videos = is_array($selection['videos'] ?? null) ? $selection['videos'] : array();
  $want_fiche = !empty($selection['fiche']);
  $message_personnel = trim((string) ($selection['message_personnel'] ?? ''));

  $blocks = array();

  if ($message_personnel !== '') $blocks[] = $message_personnel;

  $identite_lines = array($shareable['nom_affiche'] ?? '');
  foreach (array('identite', 'origines', 'taille_indice', 'prix') as $key) {
    if (!in_array($key, $selected_items, true)) continue;
    $item = $shareable['items'][$key] ?? null;
    if ($item && $item['label'] !== '') $identite_lines[] = $item['label'];
  }
  $blocks[] = implode("\n", array_filter($identite_lines, function ($line) { return $line !== ''; }));

  if (in_array('accroche', $selected_items, true)) {
    $accroche = $shareable['items']['accroche'] ?? null;
    if ($accroche && $accroche['label'] !== '') $blocks[] = $accroche['label'];
  }

  $video_lines = array();
  foreach ($shareable['videos'] ?? array() as $video) {
    if (!in_array($video['index'], $selected_videos, true)) continue;
    $video_lines[] = $video['label'] . ' : ' . $video['url'];
  }
  if ($video_lines) $blocks[] = implode("\n", $video_lines);

  if ($want_fiche && !empty($shareable['fiche_url'])) {
    $blocks[] = __('Fiche complète, photos et pedigree :', 'gws-core') . "\n" . $shareable['fiche_url'];
  }

  $blocks = array_filter($blocks, function ($block) { return trim($block) !== ''; });
  return implode("\n\n", $blocks);
}

/* -------------------------------------------------------------------------------------------
 * Open Graph de la fiche Cheval (§19 de la demande initiale ; §4 de la suite « Partager & vendre »).
 *
 * Ne crée AUCUN second système SEO/canonical/schema parallèle (principe déjà établi du projet) :
 * ne s'active QUE si aucun plugin SEO n'est actif (même détection que le thème gws-starter,
 * réutilisée si disponible — voir gwseq_horse_share_has_seo_plugin() ci-dessous), et ne touche
 * jamais au <title>/à la balise canonique (déjà gérés nativement par WordPress/le thème/un plugin
 * SEO). Émis sur la page singulière d'un cheval PUBLIQUEMENT VISIBLE, OU sur la route de partage
 * privé d'un cheval dont le partage privé est actif (voir gwseq_horse_private_share_is_active()) —
 * jamais pour un brouillon/un cheval protégé consulté par un autre chemin, même prévisualisé par un
 * utilisateur connecté. Sur la route privée, une balise "noindex" est systématiquement ajoutée : un
 * plugin SEO tiers, lui, continue d'être court-circuité normalement (il ignore ce contexte), SAUF
 * précisément sur cette route privée, où l'on ne peut pas garantir qu'il gère correctement le
 * noindex/l'absence de prix pour une URL qu'il ne connaît pas nativement — notre balisage y reste
 * donc actif en toutes circonstances (limite documentée : un plugin SEO tiers actif peut malgré
 * tout émettre EN PLUS ses propres balises sur cette route, hors de notre contrôle).
 * ----------------------------------------------------------------------------------------- */

/**
 * Réutilise la détection déjà en place côté thème (wp-content/themes/gws-starter/inc/seo.php) si
 * elle est chargée (cas normal de ce projet), avec un repli théoriquement indépendant du thème
 * (§ "ce plugin doit rester actif quel que soit le thème utilisé", gws-core.php) — les mêmes
 * quatre constantes, aucune nouvelle règle de détection inventée.
 */
function gwseq_horse_share_has_seo_plugin() {
  if (function_exists('gws_has_seo_plugin')) return gws_has_seo_plugin();
  return defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION') || defined('SEOPRESS_VERSION') || defined('AIOSEO_VERSION');
}

/**
 * "identité courte + origines + accroche commerciale lorsqu'elle existe" (§19) — réutilise
 * EXACTEMENT les mêmes libellés que le message de partage (gwseq_get_horse_shareable_data()),
 * jamais une seconde composition parallèle. Le prix et toute autre donnée commerciale ne sont
 * jamais inclus ici, y compris si l'utilisateur les aurait sélectionnés pour un partage — l'Open
 * Graph est un contenu PUBLIC, exposé à quiconque partage ce lien, jamais soumis à une sélection.
 * Tronqué proprement (jamais au milieu d'un mot) à une longueur raisonnable pour une méta-description.
 */
const GWSEQ_HORSE_OG_DESCRIPTION_MAX_LENGTH = 200;

function gwseq_horse_og_description($shareable) {
  $parts = array();
  foreach (array('identite', 'origines', 'accroche') as $key) {
    $item = $shareable['items'][$key] ?? null;
    if ($item && $item['label'] !== '') $parts[] = $item['label'];
  }
  $description = implode(' — ', $parts);
  if ($description === '' || function_exists('mb_strlen') && mb_strlen($description) <= GWSEQ_HORSE_OG_DESCRIPTION_MAX_LENGTH) {
    return $description;
  }
  return function_exists('mb_substr')
    ? rtrim(mb_substr($description, 0, GWSEQ_HORSE_OG_DESCRIPTION_MAX_LENGTH)) . '…'
    : rtrim(substr($description, 0, GWSEQ_HORSE_OG_DESCRIPTION_MAX_LENGTH)) . '…';
}

/**
 * Émet og:title/og:description/og:image(+dimensions)/og:type/og:url(+noindex sur la route privée).
 * Image : une DÉRIVÉE WordPress adaptée ('medium_large', ~768px) plutôt que l'original
 * potentiellement lourd — réutilise le pipeline média déjà existant
 * (wp_get_attachment_image_src(), aucun nouveau redimensionnement créé). Aucune balise si la fiche
 * n'a pas de photo principale — jamais une image de remplacement fabriquée. `og:url` reflète
 * l'URL RÉELLEMENT visitée (publique OU privée) — jamais figée sur le seul permalink normal, sinon
 * un aperçu WhatsApp/iMessage généré depuis un lien privé pointerait à tort vers l'URL publique.
 * "Vue privée" est déterminée via gwseq_horse_is_private_share_only() (ajustement d'architecture) :
 * un cheval RÉELLEMENT public visité via un ancien lien `/partage/{token}` est traité comme la fiche
 * PUBLIQUE qu'il est devenu (og:url public, jamais de noindex) — la publication prend toujours le
 * pas sur un token qui traîne, cohérent avec gwseq_horse_share_fiche_info().
 */
function gwseq_render_horse_og_meta() {
  if (!is_singular(GWSEQ_CPT_CHEVAL)) return;

  $cheval_id = get_queried_object_id();
  $is_private_view = get_query_var('gwseq_partage_token', '') !== '' && gwseq_horse_is_private_share_only($cheval_id);

  if (gwseq_horse_share_has_seo_plugin() && !$is_private_view) return;
  if (!gwseq_horse_is_publicly_viewable($cheval_id) && !$is_private_view) return;

  $shareable = gwseq_get_horse_shareable_data($cheval_id);
  $description = gwseq_horse_og_description($shareable);
  $current_url = $is_private_view ? gwseq_horse_private_share_url($cheval_id) : get_permalink($cheval_id);

  echo '<meta property="og:type" content="website">' . "\n";
  echo '<meta property="og:title" content="' . esc_attr($shareable['nom']) . '">' . "\n";
  if ($description !== '') {
    echo '<meta property="og:description" content="' . esc_attr($description) . '">' . "\n";
  }
  echo '<meta property="og:url" content="' . esc_url($current_url) . '">' . "\n";
  // Jamais un mécanisme de sécurité en soi (le blocage réel du permalink normal se fait dans
  // includes/cheval-share-admin.php) — seulement une indication aux moteurs de recherche pour un
  // contenu qu'ils ne devraient de toute façon jamais pouvoir découvrir par eux-mêmes (§16).
  if ($is_private_view) {
    echo '<meta name="robots" content="noindex, nofollow">' . "\n";
  }

  $thumbnail_id = gwseq_get_cheval_photo_principale_id($cheval_id);
  if ($thumbnail_id) {
    $image = wp_get_attachment_image_src($thumbnail_id, 'medium_large');
    if (is_array($image)) {
      echo '<meta property="og:image" content="' . esc_url($image[0]) . '">' . "\n";
      if (!empty($image[1])) echo '<meta property="og:image:width" content="' . (int) $image[1] . '">' . "\n";
      if (!empty($image[2])) echo '<meta property="og:image:height" content="' . (int) $image[2] . '">' . "\n";
    }
  }
}
add_action('wp_head', 'gwseq_render_horse_og_meta', 4);

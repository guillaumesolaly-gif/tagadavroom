<?php
/**
 * Sélection de plusieurs chevaux — couche métier (Suite V1 « Partager & vendre », Lot 2A).
 *
 * OBJECTIF (§1-2 de la demande) : un professionnel identifie plusieurs chevaux susceptibles de
 * convenir à un acheteur et veut lui envoyer un SEUL lien, qui continue de fonctionner après sa
 * création (contrairement à la sélection éphémère de « Partager un cheval », jamais persistée —
 * voir includes/cheval-share.php). Ce n'est PAS un CRM : aucun prospect, aucun pipeline, aucune
 * opportunité, aucun historique commercial, aucun matching automatique.
 *
 * PERSISTANCE — CHOIX D'ARCHITECTURE (audit demandé §2) : un nouveau CPT interne/non public
 * (`GWSEQ_CPT_SELECTION`, voir includes/post-types.php), exactement le même modèle déjà en place
 * pour "Groupe tarifaire" (objet d'organisation interne, jamais une page publique — aucune
 * archive, aucun rewrite natif, absent de la recherche/REST). Justification, pas un choix
 * mécanique :
 *   - Un CPT donne gratuitement un identifiant technique stable (l'ID du post), une date de
 *     création (`post_date`, natif), un titre facultatif (`post_title`, natif) et le cycle de vie
 *     WordPress standard (corbeille/suppression) — sans réinventer aucun de ces mécanismes.
 *   - AUCUNE NOUVELLE TABLE (§2 : "éviter une nouvelle table si WordPress permet une architecture
 *     propre") : la liste ORDONNÉE de chevaux tient dans UNE SEULE meta postmeta (voir
 *     GWSEQ_SELECTION_CHEVAUX_META_KEY plus bas) — un tableau PHP sérialisé d'IDs, dont l'ordre du
 *     tableau EST l'ordre de présentation (§8). Une table de jonction dédiée (sélection, cheval,
 *     position) serait la solution "relationnelle" classique, mais serait ici une sur-ingénierie :
 *     le volume attendu par sélection (quelques chevaux à quelques dizaines, jamais un catalogue
 *     entier) ne justifie ni les jointures ni la maintenance d'un schéma supplémentaire, alors
 *     qu'une meta unique couvre exactement le même besoin avec les outils déjà connus du module
 *     (update_post_meta()/get_post_meta(), comme le pedigree structuré de cheval-pedigree.php).
 *   - Le TOKEN suit exactement la même architecture que le partage privé Cheval (une seule meta,
 *     sa présence = partage actif — voir GWSEQ_SELECTION_TOKEN_META_KEY plus bas), pour le même
 *     niveau de sécurité que demandé (§4 : "le même niveau de sécurité que les liens privés
 *     Cheval").
 *
 * RÉFÉRENCE, JAMAIS COPIE (§6 de la demande) : une sélection ne stocke QUE des identifiants de
 * chevaux (`GWSEQ_SELECTION_CHEVAUX_META_KEY`) — aucune donnée Cheval n'est jamais dupliquée ici.
 * Toute lecture affichable (gwseq_selection_resolve_cheval() ci-dessous) relit les données
 * ACTUELLES du cheval au moment de l'appel : une photo corrigée, un prix mis à jour, un indice
 * modifié apparaissent donc automatiquement, sans jamais avoir besoin de "recréer" la sélection.
 *
 * DIFFUSION — RÈGLE CENTRALE (§5-6 de la demande) : ce fichier NE RECALCULE JAMAIS un état de
 * diffusion depuis `post_status` — il réutilise EXCLUSIVEMENT gwseq_horse_diffusion_state() et
 * gwseq_horse_share_fiche_url() (includes/cheval-share.php, déjà seules sources de vérité pour
 * Cheval) pour décider si un cheval référencé est actuellement présentable et quel lien utiliser
 * (public si "Visible sur le site", privé si "Diffusion privée", jamais présentable si "En
 * préparation"). Un cheval "En préparation" n'entre jamais dans une sélection à la création
 * (gwseq_selection_filter_eligible_cheval_ids() ci-dessous) ; s'il le devient APRÈS (changement
 * d'état posté à une sélection déjà envoyée), il n'est PAS retiré de la liste stockée — il devient
 * simplement non "displayable" au prochain calcul (gwseq_selection_resolve_cheval()), exactement
 * comme demandé (§6 : "ne pas casser toute la sélection : le cheval doit simplement être absent/
 * non accessible dans le rendu"). Le rendu destiné au destinataire lui-même (qui doit appliquer ce
 * filtre à l'affichage) est un développement du Lot 2B, volontairement non construit ici.
 *
 * CONFIDENTIALITÉ (§4) : cette sélection n'est JAMAIS un contenu du site — le CPT est enregistré
 * `public => false` (includes/post-types.php, même traitement que "Groupe tarifaire") : absente
 * nativement de la navigation, des archives, de la recherche, des sitemaps et de l'API REST
 * publique, sans code d'exclusion supplémentaire à écrire (même raisonnement déjà validé pour le
 * partage privé Cheval une fois son statut natif non-`publish` — ici, `public => false` couvre
 * strictement le même besoin de façon encore plus directe, dès l'enregistrement du post type).
 * Aucun compte n'est nécessaire pour le destinataire — l'accès tokenisé public
 * (`/selection/{token}`) et son rendu (`noindex` systématique) sont un développement du Lot 2B :
 * ce fichier fournit déjà tout l'appareil de token (générer/lire/activer/révoquer/URL/recherche
 * inverse), mais N'ENREGISTRE PAS la route web correspondante — construire une route qui
 * aboutirait aujourd'hui à un simple 404 (aucun gabarit de rendu) n'apporte rien à la recette de
 * ce lot et anticiperait sur le rendu public explicitement exclu du périmètre 2A.
 *
 * LOT 2A — PÉRIMÈTRE EXPLICITE : modèle métier, persistance, création d'une sélection, gestion de
 * son token (générer/activer/régénérer/révoquer/URL/recherche inverse). Volontairement ABSENT de
 * ce fichier (développements ultérieurs, non validés) : modification d'une sélection existante
 * (ajout/retrait/réordonnancement/titre après création — Lot 2B, §14 de la demande), rendu public
 * de la page destinataire (Lot 2B, §9), composition du message de partage/Open Graph (§11-12).
 *
 * PRÉPARATION MOBILE (§16) : toutes les fonctions ci-dessous sont volontairement INDÉPENDANTES de
 * wp-admin (aucun `current_user_can()`, aucun nonce ici) — un futur écran mobile pourra les
 * appeler telles quelles, exactement comme gwseq_horse_diffusion_set_*() (cheval-share.php) déjà
 * réutilisables sans connaître wp-admin. La glue wp-admin (includes/cheval-selection-admin.php)
 * vérifie les droits AVANT d'appeler ces fonctions.
 */

if (!defined('ABSPATH')) exit;

/* -------------------------------------------------------------------------------------------
 * Token — §4 de la demande : même architecture, même niveau de sécurité que le partage privé
 * Cheval (gwseq_horse_private_share_*(), includes/cheval-share.php) : 32 octets aléatoires
 * cryptographiquement sûrs (random_bytes()), jamais l'ID WordPress de la sélection, stockés dans
 * UNE SEULE meta dont la seule PRÉSENCE fait office de drapeau "lien actif" — révoquer = supprimer
 * la meta, régénérer = l'écraser par une nouvelle valeur (l'ancien lien cesse alors instantanément
 * de résoudre quoi que ce soit, voir gwseq_selection_find_by_token()).
 *
 * RÉVOCATION NON DESTRUCTIVE (§13 de la demande : "je préfère une révocation non destructive...
 * l'utilisateur peut conserver son travail sans que le lien fonctionne") : révoquer ne touche
 * JAMAIS au post ni à la liste de chevaux — seule la meta token disparaît. La sélection reste
 * entièrement visible/modifiable en interne (BO), seul son URL publique cesse de résoudre. Un
 * professionnel qui change d'avis peut redonner un accès en régénérant simplement un token.
 * ----------------------------------------------------------------------------------------- */

const GWSEQ_SELECTION_TOKEN_META_KEY = '_gwseq_selection_token';

function gwseq_selection_generate_token() {
  return bin2hex(random_bytes(32));
}

function gwseq_selection_token($selection_id) {
  return (string) get_post_meta((int) $selection_id, GWSEQ_SELECTION_TOKEN_META_KEY, true);
}

function gwseq_selection_is_active($selection_id) {
  return gwseq_selection_token($selection_id) !== '';
}

/**
 * Crée OU régénère le token — toujours la même opération (voir le commentaire de section
 * ci-dessus) : un appelant n'a jamais besoin de distinguer les deux cas, exactement comme
 * gwseq_horse_private_share_activate().
 */
function gwseq_selection_activate($selection_id) {
  $token = gwseq_selection_generate_token();
  update_post_meta((int) $selection_id, GWSEQ_SELECTION_TOKEN_META_KEY, $token);
  return $token;
}

function gwseq_selection_revoke($selection_id) {
  delete_post_meta((int) $selection_id, GWSEQ_SELECTION_TOKEN_META_KEY);
}

/**
 * Chemin conceptuel de la future route de partage (§4 : `/selection/{token}`) — constante
 * centralisée ICI dès maintenant (jamais recomposée ailleurs), même si la route web elle-même
 * (add_rewrite_rule()/template_redirect, sur le modèle de GWSEQ_HORSE_PRIVATE_SHARE_REWRITE_BASE)
 * n'est pas encore enregistrée (voir note de fichier en tête — Lot 2B). Visiter cette URL renvoie
 * donc un 404 tant que le Lot 2B n'a pas construit le rendu de la page destinataire : ce calcul
 * existe déjà pour que la couche métier soit complète (§16 : "obtenir l'URL") et testable, pas pour
 * promettre un rendu qui n'existe pas encore.
 */
const GWSEQ_SELECTION_REWRITE_BASE = 'selection';

function gwseq_selection_url($selection_id) {
  $token = gwseq_selection_token($selection_id);
  return $token !== '' ? home_url('/' . GWSEQ_SELECTION_REWRITE_BASE . '/' . $token . '/') : '';
}

/**
 * Recherche inverse token -> sélection (§4 : "accessible sans compte par quelqu'un possédant le
 * lien"), même défense en profondeur que gwseq_horse_private_share_find_cheval_id() : le format
 * est vérifié AVANT toute requête (64 hexadécimaux minuscules, exactement ce que produit
 * gwseq_selection_generate_token()). `post_status => 'publish'` volontairement strict (contrairement
 * au partage privé Cheval, qui doit fonctionner même pour un cheval en brouillon) : une sélection
 * n'a pas d'équivalent "brouillon" distinct de son état publié — voir gwseq_selection_create()
 * ci-dessous, qui crée toujours directement en `publish` (ce statut ne pilote ici aucune
 * visibilité publique, le CPT étant `public => false` — il ne fait que distinguer un enregistrement
 * réel d'une éventuelle corbeille, qu'une recherche par token ne doit jamais résoudre).
 */
function gwseq_selection_find_by_token($token) {
  $token = (string) $token;
  if (!preg_match('/^[a-f0-9]{64}$/', $token)) return 0;

  $query = new WP_Query(array(
    'post_type' => GWSEQ_CPT_SELECTION,
    'post_status' => 'publish',
    'fields' => 'ids',
    'posts_per_page' => 1,
    'meta_query' => array(
      array('key' => GWSEQ_SELECTION_TOKEN_META_KEY, 'value' => $token, 'compare' => '='),
    ),
  ));
  return $query->posts ? (int) $query->posts[0] : 0;
}

/* -------------------------------------------------------------------------------------------
 * Liste ordonnée de chevaux (§3/§8 de la demande) — une seule meta, un tableau PHP d'IDs entiers,
 * dont l'ORDRE DU TABLEAU est directement l'ordre de présentation (§8 : "le professionnel peut
 * vouloir présenter son meilleur choix en premier") — aucun champ d'ordre séparé à maintenir en
 * cohérence, aucun risque de désynchronisation entre une position stockée et la liste elle-même.
 * ----------------------------------------------------------------------------------------- */

const GWSEQ_SELECTION_CHEVAUX_META_KEY = '_gwseq_selection_chevaux';

/**
 * Sanitation pure (§17 : "validation des IDs chevaux") — entiers positifs uniquement, DÉDOUBLONNÉS
 * en conservant la position de la PREMIÈRE occurrence (un même cheval ne doit jamais apparaître
 * deux fois dans une même sélection) — ne vérifie PAS ici l'existence/l'éligibilité du cheval
 * (voir gwseq_selection_filter_eligible_cheval_ids() ci-dessous pour cette étape, appliquée
 * uniquement à la CRÉATION) : cette fonction reste une sanitation de FORME, réutilisable aussi
 * bien pour un stockage direct (déjà validé par ailleurs) que comme première étape d'un filtrage.
 */
function gwseq_selection_sanitize_cheval_ids($raw_ids) {
  $ids = array();
  foreach ((array) $raw_ids as $raw_id) {
    $id = (int) $raw_id;
    if ($id > 0 && !in_array($id, $ids, true)) $ids[] = $id;
  }
  return $ids;
}

/**
 * Règle d'éligibilité (§5 de la demande, cœur architectural de ce lot) : un cheval "En
 * préparation" n'est jamais sélectionnable — réutilise EXCLUSIVEMENT gwseq_horse_diffusion_state()
 * (includes/cheval-share.php), jamais un recalcul depuis `post_status`. Un cheval inexistant, d'un
 * autre type de contenu, ou déjà en corbeille n'est jamais éligible non plus (§17 : "appartenance
 * au bon CPT" ; §19 : "cheval supprimé/corbeille").
 */
function gwseq_selection_horse_is_eligible($cheval_id) {
  $cheval_id = (int) $cheval_id;
  if ($cheval_id <= 0) return false;
  $post = get_post($cheval_id);
  if (!$post || $post->post_type !== GWSEQ_CPT_CHEVAL || $post->post_status === 'trash') return false;
  return gwseq_horse_diffusion_state($cheval_id) !== GWSEQ_HORSE_DIFFUSION_EN_PREPARATION;
}

/**
 * Filtre d'éligibilité APPLIQUÉ UNE FOIS, À LA CONSTITUTION de la liste (gwseq_selection_create()
 * ci-dessous) — §5 : "si un cheval est en préparation, l'utilisateur doit d'abord choisir
 * explicitement de le mettre en diffusion privée" (un cheval non éligible n'entre donc jamais dans
 * la liste stockée). Ne doit JAMAIS être réappliqué en lecture pour retirer silencieusement un
 * cheval devenu inéligible APRÈS coup (§6 : la liste stockée doit rester intacte quel que soit le
 * changement d'état ultérieur — voir gwseq_selection_resolve_cheval() pour le calcul, non
 * destructif, de ce qui est actuellement présentable).
 */
function gwseq_selection_filter_eligible_cheval_ids($raw_ids) {
  return array_values(array_filter(gwseq_selection_sanitize_cheval_ids($raw_ids), 'gwseq_selection_horse_is_eligible'));
}

function gwseq_selection_get_cheval_ids($selection_id) {
  $ids = get_post_meta((int) $selection_id, GWSEQ_SELECTION_CHEVAUX_META_KEY, true);
  return is_array($ids) ? array_values(array_map('intval', $ids)) : array();
}

/**
 * Stocke la liste TELLE QUELLE (sanitation de forme uniquement — voir gwseq_selection_sanitize_
 * cheval_ids()) : cette fonction ne filtre JAMAIS par éligibilité, afin de rester utilisable pour
 * relire/réécrire un état déjà validé par l'appelant sans jamais faire disparaître silencieusement
 * un cheval dont l'état de diffusion aurait changé entre-temps (§6). La politique d'éligibilité
 * appartient exclusivement à l'appelant qui CONSTITUE une nouvelle liste (voir
 * gwseq_selection_create() ci-dessous), jamais à ce setter générique.
 */
function gwseq_selection_set_cheval_ids($selection_id, $ids) {
  update_post_meta((int) $selection_id, GWSEQ_SELECTION_CHEVAUX_META_KEY, gwseq_selection_sanitize_cheval_ids($ids));
}

/* -------------------------------------------------------------------------------------------
 * Résolution pour affichage (§6/§13 de la demande) — RELIT toujours les données actuelles, jamais
 * une copie stockée. Réutilise gwseq_horse_diffusion_state()/gwseq_horse_share_fiche_url()
 * (includes/cheval-share.php) comme seules sources de vérité pour l'état et le lien de fiche
 * approprié (public si "Visible sur le site", privé si "Diffusion privée") — jamais un second
 * calcul. Ne retire JAMAIS un ID de la liste stockée : signale seulement, pour l'affichage, si ce
 * cheval est actuellement "displayable" (présentable) ou non.
 * ----------------------------------------------------------------------------------------- */

function gwseq_selection_resolve_cheval($cheval_id) {
  $cheval_id = (int) $cheval_id;
  $post = get_post($cheval_id);
  $exists = (bool) ($post && $post->post_type === GWSEQ_CPT_CHEVAL && $post->post_status !== 'trash');
  $state = $exists ? gwseq_horse_diffusion_state($cheval_id) : '';
  $displayable = $exists && $state !== GWSEQ_HORSE_DIFFUSION_EN_PREPARATION;

  return array(
    'id' => $cheval_id,
    'exists' => $exists,
    'diffusion_state' => $state,
    'displayable' => $displayable,
    'fiche_url' => $displayable ? gwseq_horse_share_fiche_url($cheval_id) : '',
  );
}

function gwseq_selection_resolve_chevaux($selection_id) {
  return array_map('gwseq_selection_resolve_cheval', gwseq_selection_get_cheval_ids($selection_id));
}

/**
 * "Nombre de chevaux actuellement diffusables" (§13 de la demande, colonne de l'écran de gestion) —
 * calculé à la volée à chaque appel, jamais mis en cache/stocké : reflète toujours l'état RÉEL au
 * moment de la consultation de l'écran.
 */
function gwseq_selection_diffusable_count($selection_id) {
  $count = 0;
  foreach (gwseq_selection_resolve_chevaux($selection_id) as $resolved) {
    if ($resolved['displayable']) $count++;
  }
  return $count;
}

/* -------------------------------------------------------------------------------------------
 * Titre (§3 de la demande) — `post_title` natif, FACULTATIF : aucun nom d'acheteur n'est jamais
 * obligatoire. Le libellé neutre de repli n'est JAMAIS écrit en base (§ principe déjà établi dans
 * tout ce module : "aucune invention" — voir par ex. gwseq_horse_diffusion_state_label()) :
 * gwseq_selection_display_title() le calcule uniquement à l'AFFICHAGE, jamais stocké comme donnée.
 * ----------------------------------------------------------------------------------------- */

function gwseq_selection_display_title($selection_id) {
  $title = get_the_title($selection_id);
  return $title !== '' ? $title : __('Sélection de chevaux', 'gws-core');
}

/* -------------------------------------------------------------------------------------------
 * Création (§7 de la demande) — point d'entrée UNIQUE, réutilisable par un futur écran mobile
 * (§16) sans connaître wp-admin. Applique lui-même l'éligibilité (§5) : un appelant ne peut jamais
 * créer une sélection contenant un cheval "En préparation", même s'il essaie de forcer l'ID
 * (défense en profondeur — même posture que le reste du module, ex. gwseq_ajax_partager_get_
 * cheval() qui revérifie `edit_post` côté serveur plutôt que de faire confiance au client).
 * ----------------------------------------------------------------------------------------- */

function gwseq_selection_create($args) {
  $args = wp_parse_args(is_array($args) ? $args : array(), array(
    'title' => '',
    'cheval_ids' => array(),
    'author' => 0,
  ));

  $title = gws_core_field_sanitize('text', $args['title']);
  $cheval_ids = gwseq_selection_filter_eligible_cheval_ids($args['cheval_ids']);

  $postarr = array(
    'post_type' => GWSEQ_CPT_SELECTION,
    'post_title' => $title,
    'post_status' => 'publish',
  );
  $author = (int) $args['author'];
  if ($author > 0) $postarr['post_author'] = $author;

  $selection_id = wp_insert_post($postarr, true);
  if (!$selection_id || is_wp_error($selection_id)) return 0;

  gwseq_selection_set_cheval_ids($selection_id, $cheval_ids);
  // §3 : le token fait partie des propriétés MINIMALES d'une sélection, toujours présent dès la
  // création (contrairement au partage privé Cheval, activé sur demande explicite — une sélection
  // n'a de raison d'exister que pour être partagée : aucun état "créée mais non partageable" n'a
  // de sens métier ici).
  gwseq_selection_activate($selection_id);

  return $selection_id;
}

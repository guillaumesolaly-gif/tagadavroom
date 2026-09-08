<?php
/**
 * Cheval — Production directe structurée des juments (Lot 2B.2, GO avec réserves des audits
 * 2B.1/2B.1 bis, READY FOR 2B.2 du 2B.1 ter).
 *
 * PÉRIMÈTRE MÉTIER STRICT (§1) : concerne UNIQUEMENT une fiche dont le sexe est "femelle". Pour un
 * mâle ou un hongre, aucune donnée structurée de Production n'est jamais créée ni restituée — ses
 * éventuels produits ne peuvent être mentionnés que dans les champs éditoriaux existants
 * (Présentation, Commentaire production, Faits marquants — Lot 2A, déjà disponibles).
 *
 * MODÈLE HYBRIDE (§12) : `gwseq_get_horse_direct_production($cheval_id)` fusionne, sans jamais
 * doublonner un même cheval :
 * 1. Produits GWS certains — `gwseq_get_horse_offspring()` (cheval-pedigree.php, déjà existant,
 *    inchangé), calculé à la volée depuis une relation de filiation GWS déjà déclarée ;
 * 2. Produits externes structurés (IFCE) sans rattachement — snapshot IFCE tel quel ;
 * 3. Produits externes structurés RATTACHÉS à une fiche GWS (`cheval_gws_id`) qui n'apparaît pas
 *    déjà dans (1) : la fiche GWS liée devient alors la source runtime de ses propres données
 *    (nom, indices) — le snapshot IFCE stocké sur l'entrée n'est plus jamais affiché en concurrence,
 *    uniquement conservé pour traçabilité/réimport (§20).
 *
 * RATTACHEMENT (§13-14) :
 * - CERTAIN : la fiche GWS candidate a déjà, elle-même, une relation Père/Mère GWS pointant vers CE
 *   cheval sujet (déjà dans le résultat de `gwseq_get_horse_offspring($cheval_id)`) — reconnu par
 *   nom normalisé + année de naissance, jamais par nom seul, jamais de confirmation demandée
 *   puisque la relation existe déjà et a déjà été validée par ailleurs.
 * - PROBABLE : nom normalisé + année correspondent à une fiche GWS existante SANS qu'aucune relation
 *   de filiation ne l'atteste — proposé en prévisualisation IFCE, jamais appliqué automatiquement.
 * - Le nom seul n'est JAMAIS suffisant (ambiguïté -> aucun rattachement proposé).
 * - Confirmer un rattachement (probable ou certain) n'écrit JAMAIS, en effet de bord, une relation de
 *   filiation sur la fiche tierce liée (§14) — action strictement distincte, non construite ici.
 *
 * ACTUALISATION ISO/ICC/IDR D'UN PRODUIT GWS LIÉ (§15-19) : "la dernière actualisation validée
 * gagne" — chaque import IFCE validé par l'utilisateur, lorsqu'un produit est rattaché, réécrit
 * simplement l'indice sportif du Cheval GWS lié via `gwseq_set_cheval_sport_indice()` (MÊME fonction
 * que la saisie manuelle, jamais un accès direct à `update_post_meta()`) — aucun système de verrou
 * "manuel", aucune priorité de source permanente : une modification manuelle ultérieure de la fiche
 * liée reste ensuite la donnée courante jusqu'au prochain import validé qui la modifierait à son
 * tour. UNIQUEMENT ISO/ICC/IDR (+ CD/année) sont jamais propagés par ce mécanisme — jamais nom, sexe,
 * année de naissance, robe, race, taille, père/mère, pedigree, BSO/BCC/BDR, données commerciales,
 * contenus éditoriaux, médias, diffusion, ou Global Horse ID (§18) : ce fichier n'appelle jamais
 * aucune autre fonction métier d'écriture qu'un des trois setters d'indices sportifs.
 *
 * GARDE DE SEXE ET NON-DESTRUCTIVITÉ (§5) : la Production stockée d'une jument n'est JAMAIS supprimée
 * si son sexe est ensuite corrigé vers mâle/hongre — `gwseq_get_cheval_production_externe()` (et donc
 * le resolver) redevient simplement vide tant que le sexe courant n'est pas "femelle", sans qu'aucune
 * donnée ne soit jamais effacée en base.
 *
 * RÉIMPORT NON DESTRUCTIF ET IDEMPOTENT (§21) : `gwseq_ifce_merge_production_entries()` rapproche par
 * année + nom normalisé (même principe que la déduplication déjà actée au 2B.1) — un produit déjà
 * présent voit son snapshot IFCE actualisé et son éventuel rattachement PRÉSERVÉ (jamais écrasé), un
 * produit absent du nouveau PDF n'est JAMAIS supprimé, un nouveau produit est simplement ajouté.
 */

if (!defined('ABSPATH')) exit;

function gwseq_register_cheval_production_meta() {
  register_post_meta(GWSEQ_CPT_CHEVAL, '_gwseq_production_externe', array('single' => true, 'type' => 'string', 'show_in_rest' => false));
}
add_action('init', 'gwseq_register_cheval_production_meta');

/* -------------------------------------------------------------------------------------------
 * Comparaison de noms (rattachement UNIQUEMENT) — jamais utilisée pour la sanitation/le stockage
 * (gwseq_format_horse_name_display(), cheval-fields.php, reste seule fonction de PRÉSENTATION).
 * ----------------------------------------------------------------------------------------- */

function gwseq_ifce_normalize_horse_name_for_match($name) {
  $name = (string) $name;
  if (function_exists('remove_accents')) $name = remove_accents($name);
  return trim(preg_replace('/\s+/', ' ', strtoupper($name)));
}

/* -------------------------------------------------------------------------------------------
 * Sanitation, lecture, écriture — fonctions métier pures (aucun accès à $_POST).
 * ----------------------------------------------------------------------------------------- */

function gwseq_sanitize_production_indice_snapshot($raw) {
  $raw = is_array($raw) ? $raw : array();
  if (($raw['valeur'] ?? '') === '' || !is_numeric($raw['valeur'])) return array('valeur' => '', 'cd' => '', 'annee' => '');
  return array(
    'valeur' => (int) round((float) $raw['valeur']),
    'cd' => is_numeric($raw['cd'] ?? '') ? (float) $raw['cd'] : '',
    'annee' => is_numeric($raw['annee'] ?? '') ? (int) $raw['annee'] : '',
  );
}

/**
 * Sanitise UNE entrée de Production externe : {annee, nom, pere, iso, icc, idr, source,
 * cheval_gws_id}. `nom` reste potentiellement vide en sortie de cette fonction — c'est à l'appelant
 * (`gwseq_set_cheval_production_externe()` ci-dessous) de rejeter une entrée sans nom (§9), jamais à
 * cette fonction de sanitation pure de décider d'une règle métier de ce niveau.
 */
function gwseq_sanitize_production_entry($raw) {
  $raw = is_array($raw) ? $raw : array();
  $annee = $raw['annee'] ?? '';
  return array(
    'annee' => (is_numeric($annee)) ? (int) $annee : '',
    'nom' => trim((string) ($raw['nom'] ?? '')),
    'pere' => trim((string) ($raw['pere'] ?? '')),
    'iso' => gwseq_sanitize_production_indice_snapshot($raw['iso'] ?? array()),
    'icc' => gwseq_sanitize_production_indice_snapshot($raw['icc'] ?? array()),
    'idr' => gwseq_sanitize_production_indice_snapshot($raw['idr'] ?? array()),
    'source' => 'ifce', // seule provenance existante à ce stade — champ prévu pour une future saisie manuelle
    'cheval_gws_id' => absint($raw['cheval_gws_id'] ?? 0),
  );
}

/**
 * Lecture brute, SANS garde de sexe — réservée aux besoins internes de ce fichier (fusion au
 * réimport, détection de rattachement certain sur un réimport). Le code appelant hors de ce fichier
 * doit systématiquement passer par `gwseq_get_cheval_production_externe()` ci-dessous, qui applique
 * la garde de sexe (§5).
 */
function gwseq_get_cheval_production_externe_raw($cheval_id) {
  $raw = get_post_meta((int) $cheval_id, '_gwseq_production_externe', true);
  if ($raw === '') return array();
  $decoded = json_decode($raw, true);
  if (!is_array($decoded)) return array();
  $entries = array();
  foreach ($decoded as $item) {
    $clean = gwseq_sanitize_production_entry($item);
    if ($clean['nom'] === '') continue; // donnée corrompue/héritée sans nom : jamais restituée (§9)
    $entries[] = $clean;
  }
  return $entries;
}

/**
 * Fusion non destructive au réimport (§21) : $existing/$incoming sont déjà des tableaux d'entrées
 * sanitisées avec `nom` non vide. Clé de rapprochement année + nom normalisé — un produit déjà
 * présent voit son snapshot remplacé par la donnée la plus récente MAIS conserve son éventuel
 * rattachement déjà confirmé (`cheval_gws_id`), jamais écrasé silencieusement par une nouvelle
 * détection ; un produit absent de $incoming reste intact dans le résultat (jamais supprimé) ; un
 * nouveau produit est simplement ajouté à la suite, sans jamais réordonner les entrées existantes.
 */
function gwseq_ifce_merge_production_entries($existing, $incoming) {
  $by_key = array();
  foreach ($existing as $i => $entry) {
    $key = gwseq_ifce_normalize_horse_name_for_match($entry['nom']) . '|' . $entry['annee'];
    $by_key[$key] = $i;
  }

  $result = $existing;
  foreach ($incoming as $new_entry) {
    $key = gwseq_ifce_normalize_horse_name_for_match($new_entry['nom']) . '|' . $new_entry['annee'];
    if (isset($by_key[$key])) {
      $idx = $by_key[$key];
      $preserved_gws_id = (int) $result[$idx]['cheval_gws_id'];
      $result[$idx] = $new_entry;
      $result[$idx]['cheval_gws_id'] = $preserved_gws_id ?: (int) $new_entry['cheval_gws_id'];
    } else {
      $result[] = $new_entry;
    }
  }
  return $result;
}

/**
 * Persiste la liste fusionnée de Production externe d'une jument — fonction métier pure et
 * réutilisable (jamais couplée à $_POST/nonce), même architecture que le reste du module. N'écrit
 * strictement rien pour un `$cheval_id` invalide ; une entrée sans nom exploitable est silencieusement
 * écartée (§9), jamais stockée sous quelque forme que ce soit.
 */
function gwseq_set_cheval_production_externe($cheval_id, $incoming_entries) {
  $cheval_id = (int) $cheval_id;
  if (!$cheval_id) return false;

  $incoming = array();
  foreach ((is_array($incoming_entries) ? $incoming_entries : array()) as $item) {
    $clean = gwseq_sanitize_production_entry($item);
    if ($clean['nom'] === '') continue;
    $incoming[] = $clean;
  }

  $existing = gwseq_get_cheval_production_externe_raw($cheval_id);
  $merged = gwseq_ifce_merge_production_entries($existing, $incoming);
  update_post_meta($cheval_id, '_gwseq_production_externe', wp_json_encode($merged, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
  return true;
}

/**
 * Lecture avec garde de sexe (§5) : jamais de suppression de donnée, mais une Production déjà
 * enregistrée pour une fiche qui n'est PLUS une femelle (sexe corrigé après coup) redevient
 * simplement invisible tant qu'elle ne l'est pas de nouveau — jamais un état intermédiaire à
 * réconcilier manuellement.
 */
function gwseq_get_cheval_production_externe($cheval_id) {
  $identity = gwseq_get_cheval_identity((int) $cheval_id);
  if (($identity['sexe'] ?? '') !== 'female') return array();
  return gwseq_get_cheval_production_externe_raw($cheval_id);
}

/* -------------------------------------------------------------------------------------------
 * Rattachement (§13-14) : certain (filiation GWS déjà déclarée) vs probable (nom + année, à
 * confirmer) — jamais le nom seul.
 * ----------------------------------------------------------------------------------------- */

/**
 * Rattachement CERTAIN (§13) : une fiche GWS parmi les produits DÉJÀ déclarés de ce cheval sujet
 * (`gwseq_get_horse_offspring()`, relation de filiation GWS déjà validée par ailleurs) dont le nom
 * normalisé et l'année de naissance correspondent exactement à $nom/$annee. Ne peut structurellement
 * jamais rien trouver pour une fiche qui vient tout juste d'être créée (aucune relation ne peut
 * encore pointer vers un identifiant qui n'existait pas) — c'est le cas normal d'un tout premier
 * import ; ce n'est qu'au réimport d'une jument déjà existante que ce rattachement peut jouer.
 */
function gwseq_ifce_find_certain_production_match($cheval_id, $nom, $annee) {
  $cheval_id = (int) $cheval_id;
  if (!$cheval_id || trim((string) $nom) === '' || $annee === '') return 0;
  $target = gwseq_ifce_normalize_horse_name_for_match($nom);

  foreach (gwseq_get_horse_offspring($cheval_id) as $offspring) {
    if (gwseq_ifce_normalize_horse_name_for_match(get_the_title($offspring)) !== $target) continue;
    $identity = gwseq_get_cheval_identity($offspring->ID);
    if ((string) $identity['annee_naissance'] !== (string) $annee) continue;
    return (int) $offspring->ID;
  }
  return 0;
}

/**
 * Rattachement PROBABLE (§14) : nom normalisé + année de naissance correspondent à EXACTEMENT une
 * fiche GWS existante, quelle qu'elle soit — jamais le nom seul (§ "le nom seul n'est jamais
 * suffisant"). En cas d'ambiguïté (plusieurs fiches correspondent également), aucun rapprochement
 * n'est proposé plutôt que de deviner lequel est le bon.
 */
function gwseq_ifce_find_probable_production_match($nom, $annee) {
  if (trim((string) $nom) === '' || $annee === '') return 0;
  $target = gwseq_ifce_normalize_horse_name_for_match($nom);

  $matches = array();
  foreach (get_posts(array(
    'post_type' => GWSEQ_CPT_CHEVAL,
    'post_status' => array('publish', 'draft', 'pending', 'private'),
    'numberposts' => -1,
  )) as $post) {
    if (gwseq_ifce_normalize_horse_name_for_match(get_the_title($post)) !== $target) continue;
    $identity = gwseq_get_cheval_identity($post->ID);
    if ((string) $identity['annee_naissance'] !== (string) $annee) continue;
    $matches[] = (int) $post->ID;
  }

  return count($matches) === 1 ? $matches[0] : 0;
}

/* -------------------------------------------------------------------------------------------
 * Resolver métier — fusion GWS + externe (§12), source de vérité unique.
 * ----------------------------------------------------------------------------------------- */

/**
 * Production directe fusionnée d'une jument — point d'entrée métier unique du Lot 2B.2. Toujours un
 * tableau, jamais autre chose : vide pour un cheval invalide, pour un mâle/hongre (§5), ou pour une
 * femelle sans aucun produit connu. Chaque élément : {source: 'gws'|'ifce'|'ifce_linked',
 * cheval_gws_id, nom, annee, pere, iso, icc, idr}. Ordre : produits GWS d'abord (relation certaine),
 * puis produits externes dans leur ordre de stockage — jamais un tri par nom/année qui perdrait
 * l'ordre du document source.
 */
function gwseq_get_horse_direct_production($cheval_id) {
  $cheval_id = (int) $cheval_id;
  if (!$cheval_id) return array();
  $identity = gwseq_get_cheval_identity($cheval_id);
  if (($identity['sexe'] ?? '') !== 'female') return array();

  $result = array();
  $linked_gws_ids = array();

  foreach (gwseq_get_horse_offspring($cheval_id) as $offspring) {
    $linked_gws_ids[$offspring->ID] = true;
    $offspring_identity = gwseq_get_cheval_identity($offspring->ID);
    $result[] = array(
      'source' => 'gws',
      'cheval_gws_id' => (int) $offspring->ID,
      'nom' => get_the_title($offspring),
      'annee' => $offspring_identity['annee_naissance'],
      'pere' => '',
      'iso' => gwseq_get_cheval_sport_indice($offspring->ID, 'iso'),
      'icc' => gwseq_get_cheval_sport_indice($offspring->ID, 'icc'),
      'idr' => gwseq_get_cheval_sport_indice($offspring->ID, 'idr'),
    );
  }

  foreach (gwseq_get_cheval_production_externe($cheval_id) as $entry) {
    $linked_id = (int) $entry['cheval_gws_id'];
    if ($linked_id && isset($linked_gws_ids[$linked_id])) continue; // déjà représenté via la filiation GWS (1)

    if ($linked_id) {
      // Rattaché (certain ou probable confirmé) sans filiation GWS déclarée pour autant (§14 : le
      // rattachement n'écrit jamais cette filiation en effet de bord) -> la fiche liée devient la
      // source runtime de ses propres nom/indices (§20), le snapshot IFCE n'est jamais affiché ici.
      $linked_identity = gwseq_get_cheval_identity($linked_id);
      $result[] = array(
        'source' => 'ifce_linked',
        'cheval_gws_id' => $linked_id,
        'nom' => get_the_title($linked_id) ?: $entry['nom'],
        'annee' => $linked_identity['annee_naissance'] !== '' ? $linked_identity['annee_naissance'] : $entry['annee'],
        'pere' => $entry['pere'],
        'iso' => gwseq_get_cheval_sport_indice($linked_id, 'iso'),
        'icc' => gwseq_get_cheval_sport_indice($linked_id, 'icc'),
        'idr' => gwseq_get_cheval_sport_indice($linked_id, 'idr'),
      );
    } else {
      $result[] = array(
        'source' => 'ifce',
        'cheval_gws_id' => 0,
        'nom' => $entry['nom'],
        'annee' => $entry['annee'],
        'pere' => $entry['pere'],
        'iso' => $entry['iso'],
        'icc' => $entry['icc'],
        'idr' => $entry['idr'],
      );
    }
  }

  return $result;
}

/* -------------------------------------------------------------------------------------------
 * Restitution admin minimale, en lecture seule (§23 : pas de mini-ERP) — boîte visible uniquement
 * pour une jument ayant au moins un produit à afficher, même convention que la boîte "Production"
 * (GWS relationnelle) déjà existante dans cheval-pedigree.php.
 * ----------------------------------------------------------------------------------------- */

function gwseq_cheval_production_indice_summary($indice) {
  if (($indice['valeur'] ?? '') === '') return '';
  return trim(($indice['valeur'] ?? '') . (($indice['annee'] ?? '') !== '' ? ' (' . $indice['annee'] . ')' : ''));
}

function gwseq_add_cheval_production_meta_box($post) {
  if (!$post) return;
  $production = gwseq_get_horse_direct_production($post->ID);
  if (empty($production)) return;
  add_meta_box('gwseq-cheval-production-juments', __('Production (jument)', 'gws-core'), 'gwseq_render_cheval_production_box', GWSEQ_CPT_CHEVAL, 'side', 'low');
}
add_action('add_meta_boxes_' . GWSEQ_CPT_CHEVAL, 'gwseq_add_cheval_production_meta_box');

/**
 * Point d'entrée du RÉIMPORT (Lot 2B.2, §21) : un simple lien vers l'écran d'import IFCE existant,
 * avec l'identifiant de CETTE fiche déjà présente dans l'URL — voir
 * gwseq_sanitize_ifce_reimport_cheval_id() (ifce-import-admin.php), qui le revalide à chaque étape.
 * Visible sur toute fiche déjà enregistrée (jamais un auto-brouillon), quel que soit son sexe : un
 * réimport peut mettre à jour identité/indices/pedigree même pour un mâle/hongre, seule la section
 * Production reste alors absente (garde de sexe, §5). Aucune logique métier ici — le mini-ERP
 * explicitement écarté (§23) resterait l'écran de prévisualisation lui-même, pas ce simple lien.
 */
function gwseq_add_cheval_ifce_reimport_meta_box($post) {
  if (!$post || $post->post_status === 'auto-draft') return;
  add_meta_box('gwseq-cheval-ifce-reimport', __('Import IFCE', 'gws-core'), 'gwseq_render_cheval_ifce_reimport_box', GWSEQ_CPT_CHEVAL, 'side', 'low');
}
add_action('add_meta_boxes_' . GWSEQ_CPT_CHEVAL, 'gwseq_add_cheval_ifce_reimport_meta_box');

function gwseq_render_cheval_ifce_reimport_box($post) {
  $url = gwseq_ifce_import_page_url(array('gwseq_reimport_cheval_id' => $post->ID));
  echo '<p><a href="' . esc_url($url) . '" class="button">' . esc_html__('Réimporter depuis un nouveau PDF IFCE', 'gws-core') . '</a></p>';
  echo '<p class="description">' . esc_html__('Analyse un nouveau PDF IFCE et propose de mettre à jour cette fiche — rien n’est modifié avant votre validation explicite.', 'gws-core') . '</p>';
}

function gwseq_render_cheval_production_box($post) {
  $production = gwseq_get_horse_direct_production($post->ID);
  echo '<ul class="gwseq-production-list">';
  foreach ($production as $entry) {
    $label = trim(($entry['annee'] !== '' ? $entry['annee'] . ' — ' : '') . $entry['nom']);
    $indices = array();
    foreach (array('iso', 'icc', 'idr') as $key) {
      $summary = gwseq_cheval_production_indice_summary($entry[$key]);
      if ($summary !== '') $indices[] = strtoupper($key) . ' ' . $summary;
    }
    echo '<li>';
    if ($entry['cheval_gws_id']) {
      echo '<a href="' . esc_url(get_edit_post_link($entry['cheval_gws_id'])) . '">' . esc_html($label) . '</a>';
    } else {
      echo esc_html($label);
    }
    if ($indices) echo ' <span class="description">(' . esc_html(implode(', ', $indices)) . ')</span>';
    echo '</li>';
  }
  echo '</ul>';
}

<?php
/**
 * Cheval — relations de pedigree (Étape 5, socle uniquement) : Père / Mère, chacun soit une fiche
 * Cheval déjà présente dans GWS ("mode gws"), soit un ascendant hors GWS structuré ("mode
 * external"). Ni père ni mère ne sont jamais requis (§25 : un pedigree incomplet est acceptable).
 *
 * Un ascendant externe n'est PAS nécessairement une feuille terminale : il peut lui-même avoir un
 * père et une mère, eux-mêmes externes, jusqu'à la profondeur maximale du pedigree — un marchand
 * ou un cavalier professionnel dont aucun ascendant n'est géré dans GWS peut ainsi saisir un
 * pedigree complet sans jamais créer une seule fiche `gwseq_cheval` artificielle. AUCUNE création
 * automatique de fiche, AUCUNE base globale d'ancêtres, AUCUNE déduplication.
 *
 * CORRECTIONS SUITE À LA RECETTE RUNTIME (saisie réelle du pedigree de Jamerose) :
 *
 * 1. Race/Stud-book d'un ascendant externe (§1 de la demande de correction) : c'était un champ
 *    texte libre, source d'hétérogénéité constatée en usage réel (« SF »/« sf »/« Selle
 *    Français »...). Utilise désormais EXACTEMENT le même référentiel que la fiche Cheval
 *    (`gwseq_cheval_race_options()`/`gwseq_cheval_race_label()`, définis dans cheval-fields.php,
 *    jamais dupliqués ici) : liste fermée + "Autre" avec précision libre, à chaque génération de
 *    chaque branche externe. Stocké désormais en `race` (code technique stable) + `race_autre`
 *    (texte, uniquement si `race === 'autre'`) plutôt qu'un champ `breed` texte libre.
 * 2. Compatibilité ascendante (§2) : une fiche déjà enregistrée avec l'ancien format `breed` texte
 *    libre n'est jamais perdue. gwseq_migrate_external_ancestor_node() reconnaît à la LECTURE
 *    (jamais une réécriture automatique en base, jamais une migration destructive) une ancienne
 *    valeur qui correspond à un code ou un libellé canonique du référentiel (comparaison
 *    insensible à la casse et aux accents) ; sinon, elle est conservée intégralement via
 *    `race = 'autre'` + `race_autre` = texte original. Le format en base n'est réécrit qu'au
 *    prochain enregistrement volontaire de cette relation par un utilisateur.
 * 3-11. Contexte de saisie (§3-11) : chaque niveau de la branche externe affiche désormais un
 *    intitulé contextuel (« Père de UNTOUCHABLE 27 », « Mère de KANNAN »...) construit à partir du
 *    nom déjà enregistré du cheval concerné — jamais une nomenclature généalogique complexe
 *    (« grand-père paternel »...), jamais un Père/Mère nu sans contexte. Un repli explicite
 *    (« cet ascendant ») s'applique tant que le nom n'est pas encore renseigné. Un compteur
 *    « Génération N sur 4 » accompagne chaque niveau, et la génération 4 n'affiche plus AUCUN
 *    contrôle « + Renseigner ses origines » (arrêt visuel strict) — la limite serveur
 *    (gwseq_sanitize_external_ancestor_tree(), inchangée dans son principe) reste de toute façon
 *    la garantie réelle, une requête manipulée ne peut pas la contourner. **Correctif BLOQUANT
 *    (post-reprise de recette)** : un premier essai sans JavaScript s'est révélé insuffisant en
 *    conditions réelles (un nom fraîchement saisi ne se reflétait dans ces intitulés qu'après
 *    enregistrement) ; une écoute déléguée légère (assets/cheval-admin.js), strictement scopée à
 *    cet écran, met désormais ces intitulés à jour EN DIRECT pendant la frappe, sans jamais lire
 *    ni modifier la valeur réellement envoyée au serveur (voir plus bas §16 pour le même fichier
 *    JS étendu par le correctif complémentaire de nettoyage).
 * 12-15. Convention de présentation des noms (§12-15) : `post_title`/`name` restent enregistrés
 *    exactement tels que saisis (aucune transformation destructive de la source) ; seule leur
 *    PRÉSENTATION dans l'interface du pedigree passe par gwseq_format_horse_name_display()
 *    (majuscules, sans accents — voir cheval-fields.php), jamais Race/Stud-book qui reste une
 *    valeur structurée via référentiel, jamais une transformation de casse.
 * 16. Nettoyage automatique des ascendants externes vidés (correctif complémentaire post-recette,
 *    0.8.0) : la recette a révélé qu'un ascendant externe créé puis vidé par l'utilisateur (nom
 *    effacé, tout en restant sur le mode "Ascendant hors GWS") continuait d'exister en base et
 *    réapparaissait à la réouverture de la fiche. CAUSE : un nœud sans nom n'a JAMAIS été stockable
 *    (gwseq_sanitize_external_ancestor_tree() renvoie déjà `null` dès qu'un nom est vide, y compris
 *    récursivement pour tout sous-arbre — cette garantie existait déjà et n'a pas changé) ; mais
 *    quand l'arbre entier devenait ainsi entièrement `null`, gwseq_set_horse_parent() ne
 *    réinitialisait QUE la meta "..._mode" et laissait l'ANCIENNE meta "..._externe" intacte —
 *    correct pour un CHANGEMENT DE MODE (gws <-> externe, où cette conservation est voulue), mais pas
 *    ici : l'utilisateur restait sur "externe" et avait activement tout effacé, sans que la donnée
 *    stockée ne suive. CORRECTIF : quand l'arbre sanitisé est entièrement vide alors que le mode
 *    soumis est "external", "..._externe" est désormais explicitement supprimée
 *    (`delete_post_meta()`) plutôt que laissée à sa valeur précédente — sans toucher à la branche
 *    GWS (`_id`) ni à la relation de l'autre parent. Un bouton explicite « Supprimer cet ascendant »
 *    (assets/cheval-admin.js) permet en complément à l'utilisateur de vider en un clic un nœud (à
 *    n'importe quelle génération) et toute sa sous-branche — avec confirmation si des origines
 *    enfants sont déjà renseignées — sans attendre un enregistrement pour voir le formulaire se
 *    vider : purement une remise à vide des champs côté client, la suppression réelle en base
 *    restant l'effet du mécanisme ci-dessus au moment de la sauvegarde. Le resolver
 *    (includes/pedigree-resolver.php) ne produisait déjà, et continue de ne jamais produire, de
 *    nœud "external" sans nom — une branche vide y reste toujours une absence de branche. Ce
 *    correctif ne concerne QUE les ascendants externes ; une relation vers une fiche GWS continue
 *    de se désactiver via le mode « Non renseigné » sans jamais supprimer la fiche Cheval liée.
 * 17. Intégrité du pedigree — un même cheval GWS ne peut jamais être à la fois père ET mère d'un
 *    même cheval (correctif complémentaire post-recette, 0.9.0). Distinct de l'auto-référence
 *    (déjà rejetée par gwseq_sanitize_horse_parent_gws_id()) : ici, deux relations GWS valides
 *    prises séparément créeraient ensemble une incohérence biologique.
 *    gwseq_horse_parent_conflicts_with_other_role() compare UNIQUEMENT deux relations GWS ACTIVES
 *    (deux identifiants de fiche) — jamais deux ascendants externes par leur nom (un nom identique
 *    ne prouve rien, voir l'absence de déduplication déjà actée §7), et jamais une branche externe
 *    inactive conservée lors d'un changement de mode. gwseq_set_horse_parent() refuse
 *    l'enregistrement d'une relation "gws" qui créerait ce conflit (retourne `false`, AUCUNE meta
 *    modifiée pour ce rôle — la relation existante, le cas échéant, n'est jamais supprimée ni
 *    remplacée silencieusement) ; cette validation s'applique identiquement au chemin
 *    programmatique (voir la règle architecturale ci-dessous) puisque c'est la même fonction.
 *    Côté UX admin (assets/cheval-admin.js), le cheval déjà actif dans l'autre sélecteur est
 *    désactivé (jamais supprimé de la liste, jamais une valeur déjà choisie modifiée
 *    automatiquement) et cette exclusion se resynchronise en direct si l'autre sélecteur change —
 *    une aide à la saisie uniquement, la validation serveur ci-dessus restant la seule garantie
 *    réelle, y compris JavaScript désactivé. Profité de cette passe pour deux corrections
 *    lexicales validées : « Cheval déjà présent dans GWS » → « Cheval déjà enregistré » et
 *    « Ascendant hors GWS » → « Nouvel ascendant » (radios de mode), ainsi que le texte de l'aperçu
 *    développeur : « Aperçu du pedigree enregistré — actualisé après sauvegarde. ». Aucune
 *    migration automatique d'une éventuelle incohérence déjà enregistrée avant cette version.
 * 18. Filtrage métier des parents GWS — sexe et année de naissance (correctif complémentaire
 *    post-recette, 0.10.0), UNIQUEMENT pour une relation GWS (jamais un ascendant externe, §7 :
 *    pas de champ sexe ajouté pour l'occasion, pas de comparaison par nom, pas des contraintes des
 *    chevaux GWS). RÈGLE MÉTIER UNIQUE : gwseq_horse_parent_candidate_rejection_reason() ci-dessous
 *    centralise DÉSORMAIS l'ensemble des contraintes (auto-référence, sexe, année de naissance,
 *    conflit avec l'autre rôle) — le rendu du formulaire, gwseq_set_horse_parent() et tout futur
 *    import s'appuient tous sur cette même fonction, jamais une règle dupliquée ailleurs. Sexe :
 *    mâle/entier et hongre autorisés comme père (un cheval a pu reproduire avant sa castration),
 *    seule une femelle est autorisée comme mère ; un sexe non renseigné reste toujours autorisé
 *    pour les deux rôles. Année de naissance : un candidat à l'année connue doit être né
 *    STRICTEMENT avant le produit (même année ou plus tard = interdit, volontairement AUCUN âge
 *    minimum de reproduction en V1) ; année du candidat ou du produit inconnue = aucun filtre.
 *    Ni le sexe ni l'année d'un cheval ne sont jamais déduits ou modifiés automatiquement à partir
 *    de son usage comme père ou mère. Côté UX admin, les mêmes désactivations d'options que pour le
 *    conflit père/mère (0.9.0) sont réutilisées, avec une indication courte de la raison (« sexe
 *    incompatible », « année incompatible ») ; sexe/année étant des propriétés FIXES du candidat
 *    (contrairement au conflit avec l'autre rôle, qui dépend de la sélection courante),
 *    assets/cheval-admin.js ne les reconsidère JAMAIS en direct — un attribut
 *    `data-gwseq-locked-disabled` les verrouille explicitement contre toute réactivation par ce
 *    script. MODIFICATION ULTÉRIEURE DES DONNÉES (cas documenté, non traité automatiquement) : une
 *    relation valide à sa création (ex. un entier enregistré comme père) qui deviendrait
 *    incohérente suite à une modification ultérieure de la fiche parent ou produit (ex. l'entier
 *    est castré, sa fiche passe à Hongre — la relation reste valide dans ce cas précis puisque
 *    Hongre est autorisé comme père ; mais un changement de sexe ou d'année rendant réellement une
 *    relation existante incohérente resterait, lui, en base) N'EST JAMAIS supprimée ni modifiée
 *    automatiquement — aucun contrôle rétroactif construit en V1, piste actée pour une amélioration
 *    future (audit/avertissement d'intégrité), volontairement pas de système complexe maintenant.
 *
 * STOCKAGE (inchangé dans son principe, JSON par branche externe plutôt que des dizaines de meta
 * à plat) : arbre récursif `{name, race, race_autre, father, mother}`, encodé en JSON dans une
 * seule meta (`_gwseq_pere_externe`/`_gwseq_mere_externe`) par relation.
 *
 * RÈGLE ARCHITECTURALE (décidée après l'Étape 4, appliquée au nouveau code) :
 * gwseq_set_horse_parent($cheval_id, $role, $args) reste une fonction métier pure, jamais couplée
 * à $_POST ni à un nonce/capability — réutilisable telle quelle par un futur importeur CSV/XLSX,
 * une migration, WP-CLI, ou un futur connecteur IFCE/SIRE (voir §18-23 de la demande : GWS
 * Equestrian reste entièrement fonctionnel sans IFCE, la saisie structurée manuelle est le
 * fonctionnement nominal ; SI un connecteur existe un jour, il n'aurait qu'à mapper ses propres
 * données vers la forme {mode, horse_id, external} déjà attendue ici, sans aucune modification de
 * ce fichier — compatibilité déjà vérifiée, rien à changer maintenant).
 *
 * CONSERVATION NON DESTRUCTIVE (inchangée) : changer de mode (GWS <-> externe) ne touche jamais
 * les meta de l'autre branche — restent stockées mais inactives. Le resolver ne lit jamais la
 * branche inactive (voir includes/pedigree-resolver.php). Aucune reconnaissance automatique par
 * nom entre un ascendant externe et une fiche GWS : toujours une action explicite de
 * l'utilisateur.
 *
 * SUPPRESSION D'UN CHEVAL RÉFÉRENCÉ : aucun hook de nettoyage automatique sur la suppression
 * d'une fiche Cheval (cela reviendrait à modifier automatiquement d'autres fiches, interdit
 * depuis l'Étape 4). Mettre un parent à la corbeille ne supprime jamais ses produits.
 */

if (!defined('ABSPATH')) exit;

/**
 * Préfixe de meta pour un rôle donné — noms de meta en français (cohérent avec le reste du modèle
 * de données du module), valeurs techniques ('father'/'mother'/'gws'/'external') en anglais
 * (cohérent avec les autres enums du module : 'male', 'for_sale', 'fixed'...).
 */
function gwseq_horse_parent_meta_prefix($role) {
  return $role === 'mother' ? '_gwseq_mere_' : '_gwseq_pere_';
}

function gwseq_register_cheval_pedigree_meta() {
  foreach (array('_gwseq_pere_mode', '_gwseq_pere_externe', '_gwseq_mere_mode', '_gwseq_mere_externe') as $key) {
    register_post_meta(GWSEQ_CPT_CHEVAL, $key, array('single' => true, 'type' => 'string', 'show_in_rest' => false));
  }
  foreach (array('_gwseq_pere_id', '_gwseq_mere_id') as $key) {
    register_post_meta(GWSEQ_CPT_CHEVAL, $key, array('single' => true, 'type' => 'integer', 'show_in_rest' => false));
  }
}
add_action('init', 'gwseq_register_cheval_pedigree_meta');

/* -------------------------------------------------------------------------------------------
 * Fonctions pures : sanitation, compatibilité ascendante, lecture.
 * ----------------------------------------------------------------------------------------- */

/**
 * Valide un identifiant de cheval GWS pour une relation parentale (§26) : ID numérique, post
 * existant, `post_type = gwseq_cheval`, jamais une auto-référence. $current_post_id sert
 * uniquement à rejeter l'auto-référence directe ; les cycles indirects (A -> B -> A) ne peuvent
 * être détectés qu'à la résolution (voir pedigree-resolver.php).
 */
function gwseq_sanitize_horse_parent_gws_id($raw_horse_id, $current_post_id = 0) {
  $horse_id = absint($raw_horse_id);
  if ($horse_id <= 0) return 0;
  if ($horse_id === (int) $current_post_id) return 0;
  if (get_post_type($horse_id) !== GWSEQ_CPT_CHEVAL) return 0;
  return $horse_id;
}

function gwseq_horse_parent_other_role($role) {
  return $role === 'father' ? 'mother' : 'father';
}

/**
 * Intégrité du pedigree (correctif complémentaire post-recette, 0.9.0) : un même cheval GWS ne
 * peut jamais être à la fois père ET mère d'un même cheval — une incohérence biologique distincte
 * de l'auto-référence (déjà rejetée par gwseq_sanitize_horse_parent_gws_id() ci-dessus). Compare
 * UNIQUEMENT deux relations GWS actives (deux fiches, deux identifiants) : un ascendant externe
 * n'a pas d'identifiant de fiche et n'est JAMAIS comparé par son nom — deux ascendants externes
 * portant le même nom ne prouvent en rien qu'il s'agit du même cheval (voir l'absence de
 * déduplication déjà actée pour la production/le resolver). Lit la relation ACTIVE de l'autre rôle
 * telle que déjà enregistrée ; ne touche à aucune donnée, purement une fonction de lecture.
 */
function gwseq_horse_parent_conflicts_with_other_role($cheval_id, $role, $horse_id) {
  $other_relation = gwseq_get_horse_parent($cheval_id, gwseq_horse_parent_other_role($role));
  return $other_relation['mode'] === 'gws' && $other_relation['horse_id'] === (int) $horse_id;
}

/**
 * Filtrage métier selon le sexe (correctif complémentaire post-recette, 0.10.0), UNIQUEMENT pour une
 * relation GWS (§7 : jamais appliqué à un ascendant externe, qui n'a pas de champ sexe et n'en
 * reçoit jamais un rien que pour satisfaire cette règle). Un sexe non renseigné (`''`) reste
 * TOUJOURS autorisé pour les deux rôles — l'absence de donnée n'est jamais une interdiction. Un
 * hongre reste autorisé comme père (un cheval a pu reproduire avant sa castration) mais jamais
 * comme mère.
 */
function gwseq_horse_sexe_compatible_with_role($sexe, $role) {
  if ($sexe === '') return true;
  return $role === 'father' ? in_array($sexe, array('male', 'gelding'), true) : $sexe === 'female';
}

/**
 * Filtrage métier selon l'année de naissance (§2). Seule l'année est disponible (pas une date
 * complète), d'où une règle volontairement simple, SANS âge minimum de reproduction en V1 : un
 * parent dont l'année est connue doit être né STRICTEMENT avant son produit — la même année ou une
 * année postérieure est interdite. Année du produit inconnue -> aucun filtre. Année du candidat
 * inconnue -> toujours autorisé (l'absence de donnée n'est jamais une interdiction).
 */
function gwseq_horse_birth_year_compatible($candidate_annee_naissance, $child_annee_naissance) {
  if ($child_annee_naissance === '' || $candidate_annee_naissance === '') return true;
  return (int) $candidate_annee_naissance < (int) $child_annee_naissance;
}

/**
 * RÈGLE MÉTIER UNIQUE ET CENTRALE (§3 et §5 de la demande) : la seule fonction qui décide si
 * $candidate_id peut être utilisé comme $role de $cheval_id — chaîne vide si valide, sinon un code
 * de raison stable (`'self'`, `'other_role'`, `'sexe'`, `'annee'`). Le rendu du formulaire
 * (désactivation + indication de la raison), gwseq_set_horse_parent() (validation serveur) et tout
 * futur import s'appuient tous sur cette MÊME fonction, jamais une règle dupliquée ailleurs.
 * Combine, dans cet ordre, l'auto-référence, la compatibilité de sexe, la compatibilité d'année de
 * naissance (les deux nouvelles règles, 0.10.0) puis le conflit avec l'autre rôle (0.9.0) — §3 : un
 * candidat doit être compatible avec l'ENSEMBLE de ces contraintes. L'ordre place volontairement
 * sexe/année AVANT le conflit avec l'autre rôle : sexe et année sont des propriétés FIXES du
 * candidat (indépendantes de la sélection courante de l'autre rôle), alors que le conflit peut
 * disparaître dès que l'utilisateur change l'autre sélecteur — assets/cheval-admin.js s'appuie sur
 * cette distinction pour ne resynchroniser EN DIRECT que la désactivation liée au conflit, jamais
 * celle liée au sexe/à l'année (verrouillée côté rendu serveur, voir plus bas). Ne s'applique
 * jamais à un ascendant externe (voir §7 : uniquement des relations GWS, deux identifiants de
 * fiche réels).
 */
function gwseq_horse_parent_candidate_rejection_reason($cheval_id, $role, $candidate_id) {
  $cheval_id = (int) $cheval_id;
  $candidate_id = (int) $candidate_id;
  if (!$candidate_id) return '';
  if ($candidate_id === $cheval_id) return 'self';

  $candidate_identity = gwseq_get_cheval_identity($candidate_id);
  if (!gwseq_horse_sexe_compatible_with_role($candidate_identity['sexe'], $role)) return 'sexe';

  $child_annee_naissance = gwseq_get_cheval_identity($cheval_id)['annee_naissance'];
  if (!gwseq_horse_birth_year_compatible($candidate_identity['annee_naissance'], $child_annee_naissance)) return 'annee';

  if (gwseq_horse_parent_conflicts_with_other_role($cheval_id, $role, $candidate_id)) return 'other_role';

  return '';
}

/**
 * Libellé court affiché à côté d'un candidat désactivé dans le `<select>` (§4 : « une indication
 * courte de la raison », sans système UX lourd). N'est qu'une aide à la compréhension — la
 * désactivation elle-même (et surtout la validation serveur) reste la garantie réelle.
 */
function gwseq_horse_parent_rejection_reason_label($reason) {
  switch ($reason) {
    case 'self': return __('lui-même', 'gws-core');
    case 'other_role': return __('déjà l’autre parent', 'gws-core');
    case 'sexe': return __('sexe incompatible', 'gws-core');
    case 'annee': return __('année incompatible', 'gws-core');
    default: return '';
  }
}

/**
 * Sanitise récursivement un ascendant externe et ses propres ascendants : {name, race,
 * race_autre, father, mother}, father/mother de la même forme. Race/Stud-book réutilise
 * EXACTEMENT le référentiel de la fiche Cheval (gwseq_cheval_race_options(), défini dans
 * cheval-fields.php — jamais une seconde liste dupliquée ici) : un code inconnu est rejeté comme
 * n'importe quel autre champ enum du module, jamais stocké tel quel.
 *
 * Un nœud sans nom n'est pas une donnée exploitable et n'est jamais stocké (§25) — y compris son
 * éventuel sous-arbre, qui n'a alors rien à quoi se rattacher (un nœud, à quelque génération que
 * ce soit, N'EXISTE QUE s'il porte un nom ; cette règle, à elle seule, garantit déjà qu'aucun nœud
 * « totalement vide » ne peut jamais être stocké nulle part dans l'arbre — voir le correctif
 * complémentaire post-recette plus bas dans l'en-tête de ce fichier pour le SEUL cas qui échappait
 * réellement à cette garantie). $depth_remaining borne strictement la récursion quelle que soit la
 * profondeur du tableau fourni en entrée (§16 : une structure malformée ou excessivement profonde
 * ne peut jamais contourner la limite côté serveur).
 */
function gwseq_sanitize_external_ancestor_tree($raw, $depth_remaining) {
  $raw = is_array($raw) ? $raw : array();
  $name = gws_core_field_sanitize('text', $raw['name'] ?? '');
  if ($name === '') return null;

  $race = isset($raw['race']) ? sanitize_key(wp_unslash($raw['race'])) : '';
  if ($race !== '' && !array_key_exists($race, gwseq_cheval_race_options())) $race = '';
  $race_autre = gws_core_field_sanitize('text', $raw['race_autre'] ?? '');

  $node = array('name' => $name, 'race' => $race, 'race_autre' => $race_autre, 'father' => null, 'mother' => null);
  if ($depth_remaining > 0) {
    $node['father'] = gwseq_sanitize_external_ancestor_tree($raw['father'] ?? array(), $depth_remaining - 1);
    $node['mother'] = gwseq_sanitize_external_ancestor_tree($raw['mother'] ?? array(), $depth_remaining - 1);
  }
  return $node;
}

/**
 * Normalise un texte pour comparaison (minuscules, sans accents, underscores/tirets traités comme
 * des espaces, espaces multiples réduits) — uniquement pour reconnaître une ancienne valeur de
 * race texte libre face au référentiel, jamais utilisé pour l'affichage ni pour Race/Stud-book
 * d'une fiche Cheval (qui reste toujours une sélection dans le référentiel, jamais un texte
 * deviné).
 */
function gwseq_normalize_race_text($text) {
  $text = (string) $text;
  if (function_exists('remove_accents')) $text = remove_accents($text);
  $text = strtolower($text);
  $text = str_replace(array('_', '-'), ' ', $text);
  $text = trim(preg_replace('/\s+/', ' ', $text));
  return $text;
}

/**
 * Tente de reconnaître un ancien texte libre de race/stud-book comme une valeur canonique connue
 * (§2 : « Si une ancienne valeur texte correspond à une valeur canonique connue, elle peut être
 * reconnue proprement »). Comparaison exacte, après normalisation, contre le code technique OU le
 * libellé de chaque entrée du référentiel — reconnaît donc "KWPN"/"kwpn" et "Selle
 * Français"/"selle français", mais PAS une abréviation non canonique comme "SF" (qui reste alors
 * récupérable via `race = 'autre'`, jamais perdue ni devinée arbitrairement — voir
 * gwseq_migrate_external_ancestor_node()).
 */
function gwseq_match_race_to_canonical_code($text) {
  $normalized = gwseq_normalize_race_text($text);
  if ($normalized === '') return '';
  foreach (gwseq_cheval_race_options() as $code => $label) {
    if ($code === 'autre') continue;
    if (gwseq_normalize_race_text($code) === $normalized || gwseq_normalize_race_text($label) === $normalized) {
      return $code;
    }
  }
  return '';
}

/**
 * Compatibilité ascendante (§2) : convertit à la LECTURE un nœud stocké à l'ancien format (champ
 * `breed` texte libre, sans `race`/`race_autre`) vers le nouveau format, récursivement sur tout
 * l'arbre — jamais une réécriture de la base, jamais une migration destructive. Un nœud déjà au
 * nouveau format traverse cette fonction sans aucun changement.
 */
function gwseq_migrate_external_ancestor_node($node) {
  if (!is_array($node)) return $node;

  if (!array_key_exists('race', $node) && array_key_exists('breed', $node)) {
    $old_text = trim((string) $node['breed']);
    $matched_code = $old_text !== '' ? gwseq_match_race_to_canonical_code($old_text) : '';
    if ($matched_code !== '') {
      $node['race'] = $matched_code;
      $node['race_autre'] = '';
    } elseif ($old_text !== '') {
      $node['race'] = 'autre';
      $node['race_autre'] = $old_text; // texte d'origine conservé intégralement, jamais perdu
    } else {
      $node['race'] = '';
      $node['race_autre'] = '';
    }
    unset($node['breed']);
  }
  if (!array_key_exists('race', $node)) $node['race'] = '';
  if (!array_key_exists('race_autre', $node)) $node['race_autre'] = '';

  $node['father'] = isset($node['father']) && is_array($node['father']) ? gwseq_migrate_external_ancestor_node($node['father']) : null;
  $node['mother'] = isset($node['mother']) && is_array($node['mother']) ? gwseq_migrate_external_ancestor_node($node['mother']) : null;

  return $node;
}

/**
 * Persiste une relation parentale — fonction métier réutilisable, jamais couplée à $_POST ni à un
 * nonce. Ne touche QUE la branche correspondant au mode reçu ; l'autre branche (GWS ou externe)
 * reste strictement inchangée en base (conservation non destructive). Attend
 * $raw_args = {mode, horse_id, external} (external = tableau shaped comme
 * gwseq_sanitize_external_ancestor_tree() l'attend, avec race/race_autre).
 *
 * VALEUR DE RETOUR (comportement déterministe, correctifs complémentaires post-recette « intégrité
 * du pedigree ») : `false` si l'appel est malformé (cheval/rôle invalide) OU si la relation "gws"
 * demandée est rejetée par gwseq_horse_parent_candidate_rejection_reason() — RÈGLE MÉTIER UNIQUE
 * ci-dessus couvrant, dans l'ordre : l'auto-référence (déjà rejetée séparément et en amont par
 * gwseq_sanitize_horse_parent_gws_id()), le conflit avec l'autre rôle (0.9.0 : le même cheval GWS
 * déjà père ET mère), la compatibilité de sexe et la compatibilité d'année de naissance (0.10.0).
 * Dans tous ces cas de rejet, AUCUNE meta n'est modifiée pour ce rôle — la relation existante (le
 * cas échéant) reste telle quelle, jamais supprimée ni remplacée silencieusement par une valeur
 * incohérente : ni écriture partielle, ni suppression implicite. `true` dans tous les autres cas,
 * y compris un identifiant GWS invalide/auto-référencé simplement ramené à '' (comportement
 * inchangé, déjà existant). Cette même fonction est le point d'entrée programmatique (voir plus
 * haut) : un futur importeur CSV/XLSX ne peut donc jamais créer une relation que l'interface
 * WordPress aurait refusée — exactement la même garantie qu'un enregistrement passé par le
 * formulaire d'administration.
 */
function gwseq_set_horse_parent($cheval_id, $role, $raw_args) {
  $cheval_id = (int) $cheval_id;
  if (!$cheval_id || !in_array($role, array('father', 'mother'), true)) return false;
  $raw_args = is_array($raw_args) ? $raw_args : array();
  $prefix = gwseq_horse_parent_meta_prefix($role);

  $mode = isset($raw_args['mode']) ? sanitize_key(wp_unslash($raw_args['mode'])) : '';

  if ($mode === 'gws') {
    $horse_id = gwseq_sanitize_horse_parent_gws_id($raw_args['horse_id'] ?? 0, $cheval_id);
    if ($horse_id && gwseq_horse_parent_candidate_rejection_reason($cheval_id, $role, $horse_id) !== '') {
      return false; // candidat rejeté (conflit, sexe ou année incompatible) — rien n'est modifié
    }
    update_post_meta($cheval_id, $prefix . 'mode', $horse_id ? 'gws' : '');
    if ($horse_id) update_post_meta($cheval_id, $prefix . 'id', $horse_id);
    return true; // _externe volontairement non touché
  }

  if ($mode === 'external') {
    $tree = gwseq_sanitize_external_ancestor_tree($raw_args['external'] ?? array(), GWSEQ_PEDIGREE_MAX_DEPTH - 1);
    update_post_meta($cheval_id, $prefix . 'mode', $tree !== null ? 'external' : '');
    // JSON_UNESCAPED_UNICODE (+ JSON_UNESCAPED_SLASHES) — CORRECTIF BLOQUANT post-recette : sans ce
    // drapeau, json_encode() échappe tout caractère non-ASCII en séquence "\uXXXX" (ex. "é" ->
    // "é"). update_post_meta()/update_metadata() appelle en interne wp_unslash() sur la
    // valeur avant stockage (comportement natif de WordPress, indépendant de ce module) : ce
    // wp_unslash() traite alors le antislash de "é" comme un artefact des magic quotes et le
    // retire, corrompant silencieusement la chaîne stockée en "u00e9" (json_decode() reste
    // syntaxiquement valide ensuite, donc AUCUNE erreur ne remonte — juste une valeur devenue
    // fausse). Avec JSON_UNESCAPED_UNICODE, "é" est écrit tel quel (aucun antislash), donc rien
    // que wp_unslash() puisse corrompre. N'a rien à voir avec gwseq_format_horse_name_display()
    // (fonction de présentation) : celle-ci ne fait qu'afficher fidèlement une donnée déjà
    // corrompue en amont — elle n'est jamais appelée ici, ni dans aucune fonction de sanitation.
    if ($tree !== null) {
      update_post_meta($cheval_id, $prefix . 'externe', wp_json_encode($tree, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    } else {
      // CORRECTIF COMPLÉMENTAIRE post-recette (0.8.0) : $tree === null signifie que l'utilisateur a
      // vidé la totalité de l'arbre externe tout en restant sur le mode "Ascendant hors GWS" (le
      // nom du premier ascendant, seul champ qui conditionne l'existence même du nœud — voir
      // gwseq_sanitize_external_ancestor_tree() — est vide). AVANT ce correctif, seule la meta
      // "..._mode" était réinitialisée ('') ; "..._externe" restait volontairement intacte pour la
      // conservation non destructive d'un CHANGEMENT DE MODE (gws <-> externe) — mais ici il ne
      // s'agit pas d'un changement de mode, l'utilisateur est resté sur "externe" et a activement
      // vidé son contenu : laisser l'ancienne donnée en base faisait réapparaître un ascendant
      // pourtant explicitement effacé dès que l'utilisateur rouvrait ou rebasculait sur ce mode —
      // c'est le bug rapporté en recette (« un ascendant vidé continue d'exister »). Supprimer la
      // meta ici ne touche jamais la branche GWS (`_id`), ni la branche externe d'une AUTRE
      // relation (père vs mère, gérées indépendamment), ni le cheval principal.
      delete_post_meta($cheval_id, $prefix . 'externe');
    }
    return true; // _id volontairement non touché
  }

  update_post_meta($cheval_id, $prefix . 'mode', '');
  return true;
}

/**
 * Lecture brute d'une relation (mode '' = aucune branche active). Renvoie TOUJOURS la branche
 * externe décodée si elle existe en base (même inactive), déjà passée par
 * gwseq_migrate_external_ancestor_node() pour une compatibilité transparente avec l'ancien
 * format — le code appelant (rendu du formulaire, resolver) n'a jamais à se soucier du format de
 * stockage historique.
 */
function gwseq_get_horse_parent($cheval_id, $role) {
  $prefix = gwseq_horse_parent_meta_prefix($role);
  $mode = get_post_meta($cheval_id, $prefix . 'mode', true);
  if (!in_array($mode, array('gws', 'external'), true)) $mode = '';

  $externe_raw = get_post_meta($cheval_id, $prefix . 'externe', true);
  $externe_tree = null;
  if ($externe_raw !== '') {
    $decoded = json_decode($externe_raw, true);
    if (is_array($decoded) && ($decoded['name'] ?? '') !== '') {
      $externe_tree = gwseq_migrate_external_ancestor_node($decoded);
    }
  }

  return array(
    'mode' => $mode,
    'horse_id' => (int) get_post_meta($cheval_id, $prefix . 'id', true),
    'external' => $externe_tree,
  );
}

/**
 * Production : chevaux référençant $cheval_id comme père OU mère GWS. Calculée à la volée,
 * jamais stockée. Seules les relations entre deux vraies fiches `gwseq_cheval` comptent.
 */
function gwseq_get_horse_offspring($cheval_id) {
  $cheval_id = (int) $cheval_id;
  if (!$cheval_id) return array();
  return get_posts(array(
    'post_type' => GWSEQ_CPT_CHEVAL,
    'post_status' => array('publish', 'draft', 'pending', 'private'),
    'numberposts' => -1,
    'orderby' => 'title',
    'order' => 'ASC',
    'meta_query' => array(
      'relation' => 'OR',
      array(
        'relation' => 'AND',
        array('key' => '_gwseq_pere_mode', 'value' => 'gws'),
        array('key' => '_gwseq_pere_id', 'value' => $cheval_id),
      ),
      array(
        'relation' => 'AND',
        array('key' => '_gwseq_mere_mode', 'value' => 'gws'),
        array('key' => '_gwseq_mere_id', 'value' => $cheval_id),
      ),
    ),
  ));
}

/* -------------------------------------------------------------------------------------------
 * Meta box et sauvegarde (glue WordPress) — un client parmi d'autres de gwseq_set_horse_parent().
 * ----------------------------------------------------------------------------------------- */

function gwseq_cheval_parent_candidates($exclude_post_id) {
  return get_posts(array(
    'post_type' => GWSEQ_CPT_CHEVAL,
    'post_status' => array('publish', 'draft', 'pending', 'private'),
    'numberposts' => -1,
    'exclude' => array((int) $exclude_post_id),
    'orderby' => 'title',
    'order' => 'ASC',
  ));
}

/**
 * Contexte 'normal' pour les trois boîtes (au lieu de 'side' pour Production/aperçu avant
 * l'ajustement UX post-recette de l'Étape 6) : uniquement pour qu'elles rejoignent la colonne
 * principale, seule visible par la navigation par onglets ajoutée dans
 * includes/cheval-admin-tabs.php, qui les regroupe visuellement avec la boîte Pedigree elle-même
 * sous un même onglet "Pedigree" (voir gwseq_cheval_admin_tabs_config()). Un changement de
 * PLACEMENT visuel uniquement (paramètre de add_meta_box()) — aucune donnée, aucun mécanisme de
 * sauvegarde, aucune règle métier n'est affecté. Sans JavaScript, ces boîtes restent simplement
 * visibles dans la colonne principale, empilées comme n'importe quelle autre — toujours aussi
 * fonctionnelles.
 */
function gwseq_add_cheval_pedigree_meta_boxes($post) {
  add_meta_box('gwseq-cheval-pedigree', __('Pedigree', 'gws-core'), 'gwseq_render_cheval_pedigree_box', GWSEQ_CPT_CHEVAL, 'normal', 'default');

  if ($post && gwseq_get_horse_offspring($post->ID)) {
    add_meta_box('gwseq-cheval-production', __('Production', 'gws-core'), 'gwseq_render_cheval_offspring_box', GWSEQ_CPT_CHEVAL, 'normal', 'low');
  }

  if (function_exists('wp_get_environment_type') && in_array(wp_get_environment_type(), array('local', 'development'), true)) {
    add_meta_box('gwseq-cheval-pedigree-preview', __('Pedigree résolu (visible en local/développement uniquement)', 'gws-core'), 'gwseq_render_cheval_pedigree_preview_box', GWSEQ_CPT_CHEVAL, 'normal', 'low');
  }
}
add_action('add_meta_boxes_' . GWSEQ_CPT_CHEVAL, 'gwseq_add_cheval_pedigree_meta_boxes');

/**
 * Fallback textuel tant qu'un nom n'est pas encore renseigné (§7) : jamais "Père de" suivi de
 * rien, jamais "Origines de" suivi de rien.
 */
function gwseq_pedigree_display_name($raw_name) {
  $display = gwseq_format_horse_name_display($raw_name);
  return $display !== '' ? $display : __('cet ascendant', 'gws-core');
}

function gwseq_render_cheval_pedigree_box($post) {
  wp_nonce_field(GWSEQ_CHEVAL_NONCE_ACTION, GWSEQ_CHEVAL_NONCE_FIELD);
  $cheval_name = gwseq_pedigree_display_name(get_the_title($post));
  ?>
  <div class="gwseq-pedigree-i18n"
    data-father-prefix="<?php echo esc_attr__('Père de ', 'gws-core'); ?>"
    data-mother-prefix="<?php echo esc_attr__('Mère de ', 'gws-core'); ?>"
    data-summary-prefix="<?php echo esc_attr__('+ Renseigner les origines de ', 'gws-core'); ?>"
    data-fallback-name="<?php echo esc_attr__('cet ascendant', 'gws-core'); ?>"
    data-delete-confirm="<?php echo esc_attr__('Supprimer cet ascendant et ses origines ?', 'gws-core'); ?>">
    <?php
    echo '<p><strong>' . esc_html(sprintf(
      /* translators: %s: nom du cheval, en présentation GWS (majuscules, sans accents) */
      __('Origines de %s', 'gws-core'),
      $cheval_name
    )) . '</strong></p>';
    echo '<p class="description">' . esc_html__('Les intitulés « Père de… »/« Mère de… » se mettent à jour automatiquement pendant la saisie du nom d’un ascendant, sans jamais modifier ce que vous avez tapé dans le champ Nom.', 'gws-core') . '</p>';

    gwseq_render_cheval_parent_fields($post, 'father', sprintf(
      /* translators: %s: nom du cheval, en présentation GWS */
      __('Père de %s', 'gws-core'), $cheval_name
    ));
    echo '<hr>';
    gwseq_render_cheval_parent_fields($post, 'mother', sprintf(
      /* translators: %s: nom du cheval, en présentation GWS */
      __('Mère de %s', 'gws-core'), $cheval_name
    ));
    ?>
  </div>
  <?php
}

/**
 * Bloc Père ou Mère : source (GWS/externe), puis soit le `<select>` de chevaux GWS, soit l'arbre
 * récursif d'ascendant externe. Les deux blocs de champs restent toujours présents dans le DOM
 * (l'un masqué par défaut selon le mode actif — voir assets/cheval-admin.js) : c'est ce qui
 * permet de retrouver une saisie précédente non perdue après un changement de mode, et garantit
 * un formulaire fonctionnel même sans JavaScript (les deux blocs restent alors simplement
 * visibles ensemble — le serveur reste seul autoritaire sur ce qui est réellement enregistré).
 * $label est déjà l'intitulé contextuel complet (« Père de UNTOUCHABLE 27 »).
 */
function gwseq_render_cheval_parent_fields($post, $role, $label) {
  $relation = gwseq_get_horse_parent($post->ID, $role);
  $prefix = gwseq_horse_parent_meta_prefix($role);
  ?>
  <div data-gwseq-parent-block="<?php echo esc_attr($role); ?>">
    <p><strong><?php echo esc_html($label); ?></strong></p>
    <p>
      <label>
        <input type="radio" name="<?php echo esc_attr($prefix); ?>mode" value="" data-gwseq-parent-source="<?php echo esc_attr($role); ?>" <?php checked($relation['mode'], ''); ?>>
        <?php esc_html_e('— Non renseigné —', 'gws-core'); ?>
      </label><br>
      <label>
        <input type="radio" name="<?php echo esc_attr($prefix); ?>mode" value="gws" data-gwseq-parent-source="<?php echo esc_attr($role); ?>" <?php checked($relation['mode'], 'gws'); ?>>
        <?php esc_html_e('Cheval déjà enregistré', 'gws-core'); ?>
      </label><br>
      <label>
        <input type="radio" name="<?php echo esc_attr($prefix); ?>mode" value="external" data-gwseq-parent-source="<?php echo esc_attr($role); ?>" <?php checked($relation['mode'], 'external'); ?>>
        <?php esc_html_e('Nouvel ascendant', 'gws-core'); ?>
      </label>
    </p>
    <p data-gwseq-parent-fields="<?php echo esc_attr($role); ?>-gws" style="<?php echo $relation['mode'] === 'gws' ? '' : 'display:none;'; ?>">
      <select class="gwseq-parent-gws-select" data-gwseq-parent-role="<?php echo esc_attr($role); ?>" name="<?php echo esc_attr($prefix); ?>id">
        <option value="0"><?php esc_html_e('— Choisir un cheval —', 'gws-core'); ?></option>
        <?php foreach (gwseq_cheval_parent_candidates($post->ID) as $candidate) :
          // Filtrage métier des candidats (§1-3 UX admin) : chaque candidat est évalué avec
          // gwseq_horse_parent_candidate_rejection_reason() — LA MÊME fonction que celle utilisée
          // pour la validation serveur, jamais une règle dupliquée ici. Les chevaux incompatibles
          // restent visibles mais désactivés, avec une indication courte de la raison — jamais
          // retirés de la liste, jamais une sélection déjà faite modifiée automatiquement. Le
          // conflit avec l'autre rôle (0.9.0) est la SEULE raison resynchronisée EN DIRECT par
          // assets/cheval-admin.js si l'autre sélecteur change (marquée par l'absence de l'attribut
          // ci-dessous) ; sexe et année sont des propriétés fixes du candidat, verrouillées côté
          // serveur (`data-gwseq-locked-disabled`) — ce script ne les modifie jamais.
          $rejection_reason = gwseq_horse_parent_candidate_rejection_reason($post->ID, $role, $candidate->ID);
          $is_locked_reason = $rejection_reason !== '' && $rejection_reason !== 'other_role';
          $option_label = get_the_title($candidate);
          if ($rejection_reason !== '') {
            $option_label .= ' — ' . gwseq_horse_parent_rejection_reason_label($rejection_reason);
          }
        ?>
          <option value="<?php echo esc_attr($candidate->ID); ?>" <?php selected($relation['horse_id'], $candidate->ID); ?> <?php disabled($rejection_reason !== ''); ?> <?php echo $is_locked_reason ? 'data-gwseq-locked-disabled="1"' : ''; ?>><?php echo esc_html($option_label); ?></option>
        <?php endforeach; ?>
      </select>
    </p>
    <div data-gwseq-parent-fields="<?php echo esc_attr($role); ?>-external" style="<?php echo $relation['mode'] === 'external' ? '' : 'display:none;'; ?>">
      <?php gwseq_render_external_ancestor_fields($prefix . 'externe', $relation['external'] ?? array(), GWSEQ_PEDIGREE_MAX_DEPTH - 1, ''); ?>
    </div>
  </div>
  <?php
}

/**
 * Rendu récursif d'un nœud d'ascendant externe — progressive disclosure contextuelle :
 * - $context_label est l'intitulé déjà résolu pour CE nœud (« Père de UNTOUCHABLE 27 » au premier
 *   niveau, vide pour ce premier niveau puisque le bloc appelant l'affiche déjà juste au-dessus —
 *   voir gwseq_render_cheval_parent_fields()) ;
 * - un compteur « Génération N sur 4 » accompagne chaque niveau, calculé depuis $depth_remaining
 *   (générique, ne dépend d'aucune valeur codée en dur) ;
 * - à la dernière génération autorisée, AUCUN contrôle « + Renseigner ses origines » n'est
 *   proposé — arrêt visuel strict, la limite serveur restant de toute façon la garantie réelle ;
 * - le bouton de divulgation progressive (`<details>` natif) porte un intitulé contextualisé avec
 *   le nom DÉJÀ enregistré de l'ascendant en cours (jamais un Père/Mère nu), avec un repli
 *   explicite tant que ce nom n'est pas renseigné.
 * - CORRECTIF post-recette (bug bloquant) : les intitulés contextuels sont désormais mis à jour
 *   EN DIRECT pendant la frappe par `assets/cheval-admin.js` (écoute déléguée sur la classe
 *   `gwseq-external-name-input`, mise à jour du `<summary>` et des `<strong>` des blocs
 *   père/mère enfants via la classe `gwseq-ancestor-node`) — un premier essai sans JavaScript
 *   s'est révélé insuffisant en recette réelle (l'utilisateur ne voyait le contexte se mettre à
 *   jour qu'après un enregistrement complet). Ce JavaScript ne lit et n'écrit JAMAIS la valeur du
 *   champ Nom lui-même : il ne fait que recalculer le texte affiché ailleurs (résumé, libellés
 *   Père/Mère du niveau suivant) à partir de sa valeur COURANTE, jamais l'inverse — le serveur
 *   reste seul autoritaire sur ce qui est réellement enregistré. Les libellés traduits
 *   (« Père de », « Mère de », « + Renseigner les origines de », le repli « cet ascendant ») sont
 *   fournis au script via les attributs `data-*` du conteneur `.gwseq-pedigree-i18n` (voir
 *   gwseq_render_cheval_pedigree_box()), jamais codés en dur côté JavaScript — le texte de départ
 *   à l'affichage (avant toute frappe) reste toujours celui rendu par PHP.
 * - Race/Stud-book réutilise le référentiel de la fiche Cheval, avec le même mécanisme
 *   "Autre + précision" ; $field_name porte la notation par crochets, $_POST reconstruit
 *   nativement l'arbre complet.
 * - CORRECTIF COMPLÉMENTAIRE (nettoyage des ascendants vides, 0.8.0) : un bouton « Supprimer cet
 *   ascendant » (classe `gwseq-delete-ancestor`) permet de vider intentionnellement CE nœud et
 *   toute sa sous-branche (avec confirmation par `assets/cheval-admin.js` si des origines enfants
 *   sont déjà renseignées) sans attendre un enregistrement — il agit uniquement sur les champs de
 *   ce nœud et de ses descendants (jamais une autre branche, jamais le cheval principal), en
 *   remettant simplement leurs valeurs à vide : la suppression réelle en base reste l'effet du
 *   nettoyage automatique de gwseq_sanitize_external_ancestor_tree() au moment de la sauvegarde.
 */
function gwseq_render_external_ancestor_fields($field_name, $node, $depth_remaining, $context_label) {
  $node = is_array($node) ? $node : array();
  $generation = GWSEQ_PEDIGREE_MAX_DEPTH - $depth_remaining;
  $is_last_generation = $depth_remaining <= 0;
  $generation_note = $is_last_generation
    ? sprintf(
        /* translators: %1$d: numéro de génération (toujours 4 ici), %2$d: profondeur maximale du pedigree */
        __('Génération %1$d sur %2$d — dernière génération', 'gws-core'),
        $generation, GWSEQ_PEDIGREE_MAX_DEPTH
      )
    : sprintf(
        /* translators: %1$d: numéro de génération, %2$d: profondeur maximale du pedigree */
        __('Génération %1$d sur %2$d', 'gws-core'),
        $generation, GWSEQ_PEDIGREE_MAX_DEPTH
      );
  ?>
  <div class="gwseq-ancestor-node" style="margin-left:1em; border-left:2px solid #ddd; padding-left:1em; margin-top:0.5em;">
    <p>
      <?php if ($context_label !== '') : ?><strong><?php echo esc_html($context_label); ?></strong> <?php endif; ?>
      <span class="description"><?php echo $context_label !== '' ? '— ' : ''; ?><?php echo esc_html($generation_note); ?></span>
    </p>
    <p>
      <label><?php esc_html_e('Nom', 'gws-core'); ?></label><br>
      <input type="text" class="regular-text gwseq-external-name-input" name="<?php echo esc_attr($field_name); ?>[name]" value="<?php echo esc_attr($node['name'] ?? ''); ?>">
    </p>
    <p>
      <label><?php esc_html_e('Race / Stud-book', 'gws-core'); ?></label><br>
      <select class="gwseq-external-race-select" name="<?php echo esc_attr($field_name); ?>[race]">
        <option value=""><?php esc_html_e('— Non renseignée —', 'gws-core'); ?></option>
        <?php foreach (gwseq_cheval_race_options() as $key => $race_label) : ?>
          <option value="<?php echo esc_attr($key); ?>" <?php selected($node['race'] ?? '', $key); ?>><?php echo esc_html($race_label); ?></option>
        <?php endforeach; ?>
      </select>
    </p>
    <p class="gwseq-external-race-autre-wrap" style="<?php echo ($node['race'] ?? '') === 'autre' ? '' : 'display:none;'; ?>">
      <label><?php esc_html_e('Préciser la race / le stud-book', 'gws-core'); ?></label><br>
      <input type="text" class="regular-text" name="<?php echo esc_attr($field_name); ?>[race_autre]" value="<?php echo esc_attr($node['race_autre'] ?? ''); ?>">
    </p>
    <p>
      <button type="button" class="button-link-delete gwseq-delete-ancestor"><?php esc_html_e('Supprimer cet ascendant', 'gws-core'); ?></button>
    </p>
    <?php if (!$is_last_generation) :
      $node_display_name = gwseq_pedigree_display_name($node['name'] ?? '');
      $has_children_data = !empty($node['father']) || !empty($node['mother']);
    ?>
      <details <?php echo $has_children_data ? 'open' : ''; ?>>
        <summary><?php echo esc_html(sprintf(
          /* translators: %s: nom de l'ascendant dont on va renseigner les origines, en présentation GWS, ou repli si pas encore saisi */
          __('+ Renseigner les origines de %s', 'gws-core'), $node_display_name
        )); ?></summary>
        <?php
        gwseq_render_external_ancestor_fields(
          $field_name . '[father]', $node['father'] ?? array(), $depth_remaining - 1,
          sprintf(/* translators: %s: nom de l'ascendant */ __('Père de %s', 'gws-core'), $node_display_name)
        );
        gwseq_render_external_ancestor_fields(
          $field_name . '[mother]', $node['mother'] ?? array(), $depth_remaining - 1,
          sprintf(/* translators: %s: nom de l'ascendant */ __('Mère de %s', 'gws-core'), $node_display_name)
        );
        ?>
      </details>
    <?php endif; ?>
  </div>
  <?php
}

function gwseq_render_cheval_offspring_box($post) {
  $offspring = gwseq_get_horse_offspring($post->ID);
  if (!$offspring) return;
  echo '<ul>';
  foreach ($offspring as $child) {
    echo '<li><a href="' . esc_url(get_edit_post_link($child->ID)) . '">' . esc_html(get_the_title($child)) . '</a></li>';
  }
  echo '</ul>';
}

function gwseq_render_cheval_pedigree_preview_box($post) {
  echo '<p class="description">' . esc_html__('Aperçu du pedigree enregistré — actualisé après sauvegarde.', 'gws-core') . '</p>';
  echo gwseq_render_pedigree_node_preview(gwseq_resolve_horse_pedigree($post->ID));
}

/**
 * Sauvegarde — un client parmi d'autres de gwseq_set_horse_parent() : ne fait que sécuriser la
 * requête de formulaire (nonce/capability/autosave/révision), puis délègue entièrement la
 * persistance à la fonction métier réutilisable. `_gwseq_pere_externe`/`_gwseq_mere_externe`
 * arrivent déjà comme des tableaux PHP imbriqués dans $_POST (notation par crochets des champs
 * HTML, reconstruite nativement).
 */
function gwseq_save_cheval_pedigree_meta($post_id) {
  if (!isset($_POST[GWSEQ_CHEVAL_NONCE_FIELD]) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[GWSEQ_CHEVAL_NONCE_FIELD])), GWSEQ_CHEVAL_NONCE_ACTION)) return;
  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
  if (function_exists('wp_is_post_revision') && wp_is_post_revision($post_id)) return;
  if (!current_user_can('edit_post', $post_id)) return;

  gwseq_set_horse_parent($post_id, 'father', array(
    'mode' => $_POST['_gwseq_pere_mode'] ?? '',
    'horse_id' => $_POST['_gwseq_pere_id'] ?? 0,
    'external' => $_POST['_gwseq_pere_externe'] ?? array(),
  ));
  gwseq_set_horse_parent($post_id, 'mother', array(
    'mode' => $_POST['_gwseq_mere_mode'] ?? '',
    'horse_id' => $_POST['_gwseq_mere_id'] ?? 0,
    'external' => $_POST['_gwseq_mere_externe'] ?? array(),
  ));
}
add_action('save_post_' . GWSEQ_CPT_CHEVAL, 'gwseq_save_cheval_pedigree_meta');

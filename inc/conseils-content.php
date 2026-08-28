<?php
// Contenu éditorial initial des 14 pages Conseil — utilisé une seule fois, à la création de
// chaque page (voir spa_seed_conseils_pages() dans inc/conseils.php). Une fois une page créée,
// ce fichier n'est plus jamais relu pour elle : post_content (WordPress) devient la seule
// source de vérité de son texte, modifiable normalement dans l'éditeur.

function spa_conseil_body_html($slug) {
  switch ($slug) {
    case 'problemes-tresorerie-entreprise': return spa_conseil_body_tresorerie();
    case 'entreprise-ne-peut-plus-payer-urssaf': return spa_conseil_body_urssaf();
    case 'entreprise-ne-peut-plus-payer-fournisseurs': return spa_conseil_body_fournisseurs();
    case 'entreprise-ne-peut-plus-payer-salaires': return spa_conseil_body_salaires();
    case 'banque-supprime-decouvert-professionnel': return spa_conseil_body_banque();
    case 'negocier-dettes-entreprise': return spa_conseil_body_negocier_dettes();
    case 'entreprise-assignee-creancier': return spa_conseil_body_assignation();
    case 'mandat-ad-hoc-ou-conciliation': return spa_conseil_body_mandat_ad_hoc();
    case 'cessation-paiements-delai-45-jours': return spa_conseil_body_45_jours();
    case 'ouverture-redressement-judiciaire': return spa_conseil_body_redressement();
    case 'redressement-judiciaire-dirigeant': return spa_conseil_body_redressement_dirigeant();
    case 'redressement-judiciaire-salaries-fournisseurs-clients': return spa_conseil_body_redressement_parties();
    case 'liquidation-judiciaire-risques-dirigeant': return spa_conseil_body_liquidation_risques();
    case 'caution-personnelle-dirigeant-entreprise-difficulte': return spa_conseil_body_caution_personnelle();
    default: return '';
  }
}

function spa_conseil_body_tresorerie() {
  $b = array();
  $b[] = spa_conseil_block_paragraph('Une tension de trésorerie n’est pas nécessairement le signe qu’une entreprise est en situation critique.');
  $b[] = spa_conseil_block_paragraph('Un décalage entre les encaissements et les décaissements, un investissement important ou le retard de paiement d’un client peuvent provoquer une difficulté ponctuelle.');
  $b[] = spa_conseil_block_paragraph('La situation devient en revanche plus préoccupante lorsque le manque de trésorerie s’installe et commence à modifier le fonctionnement normal de l’entreprise : échéances reportées, fournisseurs payés en retard, découvert bancaire utilisé en permanence, dettes sociales ou fiscales qui s’accumulent…');
  $b[] = spa_conseil_block_paragraph('Plus les difficultés sont identifiées tôt, plus le dirigeant conserve de possibilités pour agir.');

  $b[] = spa_conseil_block_heading('Une difficulté ponctuelle ou une dégradation plus profonde ?');
  $b[] = spa_conseil_block_paragraph('La première question n’est pas seulement de connaître le montant disponible sur le compte bancaire.');
  $b[] = spa_conseil_block_paragraph('Il faut comprendre pourquoi la trésorerie manque et comment elle devrait évoluer dans les prochaines semaines et les prochains mois. Une tension temporaire peut parfois être absorbée par les encaissements attendus. Une situation dans laquelle l’entreprise dépend continuellement de nouveaux reports de paiement pour honorer les échéances précédentes appelle une analyse différente.');

  $b[] = spa_conseil_block_heading('Quels signaux doivent alerter ?');
  $b[] = spa_conseil_block_paragraph('Plusieurs événements méritent une attention particulière lorsqu’ils deviennent récurrents ou se cumulent :');
  $b[] = spa_conseil_block_list(array(
    'Les fournisseurs sont réglés de plus en plus tard',
    'Certaines échéances URSSAF ou fiscales ne peuvent plus être honorées normalement',
    'Le découvert bancaire est utilisé en permanence',
    'La banque refuse d’augmenter les concours ou envisage de les réduire',
    'Des créanciers commencent à relancer régulièrement l’entreprise',
    'Le dirigeant doit choisir quelles dettes payer en priorité',
    'Les prochaines échéances ne pourront être réglées qu’à condition que certains encaissements arrivent à temps',
    'La visibilité sur la trésorerie des prochaines semaines devient insuffisante',
  ));
  $b[] = spa_conseil_block_paragraph('Un seul de ces éléments ne suffit pas nécessairement à caractériser une situation grave. Leur accumulation doit en revanche conduire à regarder la situation dans son ensemble.');

  $b[] = spa_conseil_block_heading('Pourquoi ne faut-il pas attendre que la trésorerie soit épuisée ?');
  $b[] = spa_conseil_block_paragraph('Parce que certaines solutions sont précisément conçues pour intervenir avant que la situation ne devienne critique. Lorsque l’entreprise dispose encore de marges de manœuvre, différentes négociations peuvent notamment être envisagées avec ses partenaires ou ses créanciers.');
  $b[] = spa_conseil_block_paragraph('Des procédures confidentielles de prévention, comme le mandat ad hoc ou la conciliation, peuvent également permettre d’organiser les discussions dans un cadre juridique adapté.');
  $b[] = spa_conseil_block_paragraph('Attendre peut au contraire réduire progressivement le nombre de solutions disponibles.');

  $b[] = spa_conseil_block_heading('Problèmes de trésorerie et cessation des paiements : ce n’est pas la même chose');
  $b[] = spa_conseil_block_paragraph('Une entreprise qui rencontre des difficultés de trésorerie n’est pas nécessairement en cessation des paiements.');
  $b[] = spa_conseil_block_paragraph('La cessation des paiements répond à une définition juridique précise : l’entreprise doit être dans l’impossibilité de faire face à son passif exigible avec son actif disponible.');
  $b[] = spa_conseil_block_paragraph('Cette distinction est importante car elle influence directement les solutions qui peuvent être envisagées.');
  $b[] = spa_conseil_block_resource_link('Comprendre la cessation des paiements', 'Définition, délai de 45 jours et obligations du dirigeant.', 'cessation-de-paiement-que-faire-dirigeant-saint-etienne', 'info');

  $b[] = spa_conseil_block_diagnostic_cta('Vous ne savez pas comment situer votre entreprise ?', 'Des difficultés commencent parfois par des événements qui semblent indépendants les uns des autres. Le diagnostic proposé par le cabinet permet d’examiner plusieurs signaux concernant la trésorerie, les dettes et la situation de l’entreprise.', 'Évaluer la situation de mon entreprise');

  $b[] = spa_conseil_block_heading('Quand demander conseil ?');
  $b[] = spa_conseil_block_paragraph('Il n’est pas nécessaire d’attendre de ne plus pouvoir payer les échéances pour consulter.');
  $b[] = spa_conseil_block_paragraph('Une analyse suffisamment précoce permet justement de déterminer si la difficulté est ponctuelle, si des négociations doivent être engagées ou si une solution juridique particulière mérite d’être envisagée.');
  $b[] = spa_conseil_block_contact_cta('Une analyse rapide vous semble utile ?', 'Un premier échange permet de préciser la situation de l’entreprise et la démarche la plus adaptée.', 'Échanger avec le cabinet');

  return implode('', $b);
}

function spa_conseil_body_urssaf() {
  $b = array();
  $b[] = spa_conseil_block_paragraph('Une échéance URSSAF que l’entreprise ne peut pas régler constitue un signal de tension financière, mais elle ne signifie pas à elle seule que l’entreprise est en cessation des paiements.');
  $b[] = spa_conseil_block_paragraph('Il faut cependant éviter de considérer cette dette isolément.');
  $b[] = spa_conseil_block_paragraph('La véritable question est de savoir si l’entreprise rencontre un décalage ponctuel de trésorerie ou si cette échéance impayée est l’un des symptômes d’une dégradation plus générale.');

  $b[] = spa_conseil_block_heading('Commencez par regarder la situation dans son ensemble');
  $b[] = spa_conseil_block_paragraph('Lorsqu’une échéance sociale ne peut plus être réglée, il faut notamment examiner :');
  $b[] = spa_conseil_block_list(array(
    'La trésorerie immédiatement disponible',
    'Les encaissements réellement attendus',
    'Les autres échéances sociales et fiscales',
    'Les fournisseurs déjà en retard de paiement',
    'Les échéances bancaires',
    'Les salaires à venir',
    'Les lignes de crédit encore disponibles',
    'Les dettes qui arriveront à échéance dans les prochaines semaines',
  ));
  $b[] = spa_conseil_block_paragraph('Le montant de la dette URSSAF est donc important, mais la capacité de l’entreprise à faire face à l’ensemble de ses obligations l’est davantage encore.');

  $b[] = spa_conseil_block_heading('Peut-on demander un délai pour payer l’URSSAF ?');
  $b[] = spa_conseil_block_paragraph('Selon la situation de l’entreprise, des démarches permettant de solliciter un délai de paiement peuvent être envisagées.');
  $b[] = spa_conseil_block_paragraph('Lorsque les difficultés concernent plusieurs dettes fiscales et sociales, d’autres dispositifs peuvent également devoir être étudiés, notamment la Commission des chefs de services financiers (CCSF).');
  $b[] = spa_conseil_block_paragraph('Mais obtenir un délai sur une dette ne règle pas nécessairement le problème de fond. Si l’entreprise génère chaque mois de nouvelles dettes qu’elle ne parvient plus à absorber, reporter les échéances peut simplement repousser la difficulté.');

  $b[] = spa_conseil_block_heading('Une dette URSSAF signifie-t-elle que l’entreprise est en cessation des paiements ?');
  $b[] = spa_conseil_block_paragraph('Pas nécessairement.');
  $b[] = spa_conseil_block_paragraph('L’existence d’une dette, même importante, ne permet pas à elle seule de répondre à cette question. Il faut notamment déterminer si l’entreprise peut faire face à son passif exigible avec son actif disponible et examiner les éventuels délais ou réserves de crédit dont elle bénéficie.');
  $b[] = spa_conseil_block_resource_link('Comprendre la cessation des paiements', 'Définition, délai de 45 jours et obligations du dirigeant.', 'cessation-de-paiement-que-faire-dirigeant-saint-etienne', 'info');

  $b[] = spa_conseil_block_heading('Quand faut-il réellement s’inquiéter ?');
  $b[] = spa_conseil_block_paragraph('La vigilance doit notamment augmenter lorsque la dette URSSAF s’accompagne d’autres difficultés :');
  $b[] = spa_conseil_block_paragraph('URSSAF impayée + fournisseurs en retard + découvert saturé + prochaines échéances sans solution identifiée.');
  $b[] = spa_conseil_block_paragraph('À ce stade, traiter chaque créancier séparément risque de ne plus suffire. Il devient nécessaire d’avoir une vision globale de la situation.');

  $b[] = spa_conseil_block_heading('Quelles solutions peuvent être envisagées ?');
  $b[] = spa_conseil_block_paragraph('Elles dépendent du niveau de difficulté rencontré. Une entreprise qui dispose encore de marges de manœuvre ne se trouve pas dans la même situation qu’une entreprise qui ne peut plus faire face à ses dettes exigibles.');
  $b[] = spa_conseil_block_paragraph('Selon les circonstances, l’analyse peut conduire à envisager :');
  $b[] = spa_conseil_block_list(array(
    'Des négociations ou demandes de délais',
    'Une restructuration des échéances',
    'Une procédure de prévention',
    'Un mandat ad hoc ou une conciliation',
    'Ou, lorsque les conditions sont réunies, une procédure collective',
  ));
  $b[] = spa_conseil_block_paragraph('Il n’existe donc pas une solution unique à une dette URSSAF.');

  $b[] = spa_conseil_block_diagnostic_cta('Faites un premier point sur votre situation', 'Vous avez une dette URSSAF et vous ne savez pas si elle traduit une difficulté ponctuelle ou une situation plus préoccupante ?', 'Faire le diagnostic');
  $b[] = spa_conseil_block_contact_cta('Vous préférez en parler directement ?', 'Un premier échange permet de préciser la situation de l’entreprise et la démarche la plus adaptée.', 'Échanger avec le cabinet');

  return implode('', $b);
}

function spa_conseil_body_fournisseurs() {
  $b = array();
  $b[] = spa_conseil_block_paragraph('Commencer à payer certains fournisseurs en retard est souvent l’un des premiers signes visibles d’une tension de trésorerie.');
  $b[] = spa_conseil_block_paragraph('Cela ne signifie pas nécessairement que l’entreprise est en situation irrémédiablement compromise.');
  $b[] = spa_conseil_block_paragraph('Mais lorsque le dirigeant doit régulièrement décider quel fournisseur payer et lequel faire attendre, la difficulté mérite d’être analysée dans son ensemble.');

  $b[] = spa_conseil_block_heading('Tous les retards de paiement n’ont pas la même signification');
  $b[] = spa_conseil_block_paragraph('Une facture ponctuellement réglée en retard en raison d’un décalage d’encaissement n’a pas la même portée qu’une accumulation progressive d’impayés. Plusieurs questions doivent être posées :');
  $b[] = spa_conseil_block_list(array(
    'Combien de fournisseurs sont concernés ?',
    'Depuis combien de temps ?',
    'Le montant des retards augmente-t-il ?',
    'L’entreprise peut-elle résorber ces dettes avec les encaissements attendus ?',
    'De nouvelles dettes apparaissent-elles chaque mois ?',
    'Certains fournisseurs menacent-ils d’interrompre les livraisons ?',
    'Des mises en demeure ou procédures de recouvrement ont-elles commencé ?',
  ));

  $b[] = spa_conseil_block_heading('Attention à l’effet domino');
  $b[] = spa_conseil_block_paragraph('Les difficultés fournisseurs peuvent rapidement affecter l’exploitation. Un fournisseur stratégique peut :');
  $b[] = spa_conseil_block_list(array(
    'Modifier ses conditions de paiement',
    'Exiger un paiement comptant',
    'Suspendre certaines livraisons',
    'Engager une procédure de recouvrement',
  ));
  $b[] = spa_conseil_block_paragraph('L’entreprise peut alors rencontrer des difficultés pour produire ou servir ses propres clients, ce qui peut à son tour dégrader ses encaissements. Une difficulté financière peut ainsi devenir une difficulté opérationnelle.');

  $b[] = spa_conseil_block_heading('Faut-il négocier avec les fournisseurs ?');
  $b[] = spa_conseil_block_paragraph('Lorsque la difficulté reste maîtrisable, des discussions peuvent permettre d’aménager certaines échéances.');
  $b[] = spa_conseil_block_paragraph('Mais négocier individuellement avec chaque fournisseur atteint ses limites lorsque l’entreprise accumule simultanément les retards auprès de nombreux créanciers.');
  $b[] = spa_conseil_block_paragraph('Il faut alors se demander si le problème n’est plus seulement celui de quelques factures, mais celui de la structure financière globale de l’entreprise.');

  $b[] = spa_conseil_block_heading('Les fournisseurs impayés signifient-ils que je suis en cessation des paiements ?');
  $b[] = spa_conseil_block_paragraph('Pas automatiquement.');
  $b[] = spa_conseil_block_paragraph('Mais lorsque des dettes exigibles ne peuvent plus être réglées avec les ressources disponibles, la question doit être examinée précisément. La date de cessation des paiements peut avoir des conséquences juridiques importantes pour le dirigeant et l’entreprise.');
  $b[] = spa_conseil_block_resource_link('Comprendre la cessation des paiements', 'Définition, délai de 45 jours et obligations du dirigeant.', 'cessation-de-paiement-que-faire-dirigeant-saint-etienne', 'info');

  $b[] = spa_conseil_block_heading('Plusieurs créanciers sont concernés ?');
  $b[] = spa_conseil_block_paragraph('Lorsque banques, fournisseurs, organismes sociaux ou fiscaux sont simultanément concernés, certaines procédures de prévention peuvent permettre d’organiser les discussions de manière plus globale.');
  $b[] = spa_conseil_block_resource_link('Découvrir la prévention des difficultés', 'Mandat ad hoc et conciliation : comment ça fonctionne.', 'prevention-difficultes-entreprise-saint-etienne', 'shield');

  $b[] = spa_conseil_block_diagnostic_cta('Votre situation dépasse-t-elle un simple retard fournisseur ?', 'Le diagnostic proposé par le cabinet permet d’examiner plusieurs signaux concernant la trésorerie, les dettes et la situation de l’entreprise.', 'Faire le diagnostic');
  $b[] = spa_conseil_block_contact_cta('Des fournisseurs menacent déjà l’activité de l’entreprise ?', 'Un premier échange permet de préciser la situation et la démarche la plus adaptée.', 'Échanger avec le cabinet');

  return implode('', $b);
}

function spa_conseil_body_banque() {
  $b = array();
  $b[] = spa_conseil_block_paragraph('Pour certaines entreprises, le découvert bancaire n’est pas seulement utilisé exceptionnellement : il fait partie de l’équilibre quotidien de la trésorerie.');
  $b[] = spa_conseil_block_paragraph('Sa réduction ou sa suppression peut donc provoquer une difficulté immédiate. La première urgence consiste à mesurer précisément ce que l’entreprise pourra encore payer sans cette ressource.');

  $b[] = spa_conseil_block_heading('Quel sera l’impact réel sur les prochaines semaines ?');
  $b[] = spa_conseil_block_paragraph('Il faut rapidement établir les principales échéances :');
  $b[] = spa_conseil_block_list(array(
    'Salaires',
    'Cotisations sociales',
    'Impôts',
    'Fournisseurs',
    'Loyers',
    'Remboursements d’emprunts',
    'Autres charges indispensables à l’activité',
  ));
  $b[] = spa_conseil_block_paragraph('Puis les confronter aux encaissements réellement prévisibles et aux ressources encore disponibles.');
  $b[] = spa_conseil_block_paragraph('L’objectif est simple : savoir si la suppression du découvert crée une tension temporaire ou remet en cause la capacité de l’entreprise à faire face à ses échéances.');

  $b[] = spa_conseil_block_heading('Pourquoi la réaction de la banque doit-elle être prise au sérieux ?');
  $b[] = spa_conseil_block_paragraph('Une réduction des concours bancaires peut être à la fois une cause et un révélateur des difficultés. Elle peut créer elle-même une tension de trésorerie.');
  $b[] = spa_conseil_block_paragraph('Mais elle peut également signifier que la banque a constaté une dégradation qu’il faut analyser : utilisation permanente du découvert, incidents, résultats en baisse, augmentation de l’endettement…');
  $b[] = spa_conseil_block_paragraph('Le problème ne doit donc pas être traité uniquement sous l’angle de la relation bancaire.');

  $b[] = spa_conseil_block_heading('Peut-on négocier avec la banque ?');
  $b[] = spa_conseil_block_paragraph('Selon la situation et les engagements existants, une discussion avec l’établissement bancaire peut naturellement être nécessaire. Mais la qualité de cette négociation dépend en grande partie de la capacité du dirigeant à présenter :');
  $b[] = spa_conseil_block_list(array(
    'La situation financière réelle',
    'Les causes des difficultés',
    'Les besoins à court terme',
    'Les perspectives',
    'Un scénario crédible permettant de rétablir la situation',
  ));
  $b[] = spa_conseil_block_paragraph('Lorsque plusieurs créanciers sont concernés, une négociation isolée avec la banque n’est parfois plus suffisante.');

  $b[] = spa_conseil_block_heading('Mandat ad hoc ou conciliation : lorsque la négociation doit être organisée');
  $b[] = spa_conseil_block_paragraph('Les procédures de prévention peuvent notamment permettre d’organiser des discussions avec les principaux créanciers de l’entreprise dans un cadre confidentiel.');
  $b[] = spa_conseil_block_paragraph('Elles peuvent présenter un intérêt lorsque l’entreprise rencontre des difficultés mais qu’une négociation reste possible.');
  $b[] = spa_conseil_block_resource_link('Découvrir les solutions de prévention des difficultés', 'Mandat ad hoc et conciliation : comment ça fonctionne.', 'prevention-difficultes-entreprise-saint-etienne', 'shield');

  $b[] = spa_conseil_block_heading('Et si la suppression du découvert rend certaines dettes impossibles à payer ?');
  $b[] = spa_conseil_block_paragraph('Il faut alors déterminer rapidement si la situation de l’entreprise répond ou non aux critères de la cessation des paiements. Cette analyse ne doit pas être faite uniquement à partir du solde bancaire du jour.');
  $b[] = spa_conseil_block_resource_link('Comprendre la cessation des paiements', 'Définition, délai de 45 jours et obligations du dirigeant.', 'cessation-de-paiement-que-faire-dirigeant-saint-etienne', 'info');

  $b[] = spa_conseil_block_diagnostic_cta('Évaluez les autres signaux de difficulté', 'Une banque qui réduit ses concours est rarement une information à examiner seule.', 'Évaluer la situation de mon entreprise');
  $b[] = spa_conseil_block_contact_cta('Besoin d’examiner rapidement les conséquences de la décision bancaire ?', 'Un premier échange permet de préciser la situation et la démarche la plus adaptée.', 'Échanger avec le cabinet');

  return implode('', $b);
}

function spa_conseil_body_45_jours() {
  $b = array();
  $b[] = spa_conseil_block_paragraph('Le délai de 45 jours est régulièrement associé au « dépôt de bilan ». Mais il est parfois mal compris.');
  $b[] = spa_conseil_block_paragraph('Il ne s’agit pas d’une période de 45 jours pendant laquelle un dirigeant pourrait simplement attendre pour voir si la situation s’améliore.');
  $b[] = spa_conseil_block_paragraph('Lorsqu’une entreprise se trouve en cessation des paiements, son dirigeant doit réagir dans le cadre prévu par la loi.');

  $b[] = spa_conseil_block_heading('D’abord : qu’est-ce que la cessation des paiements ?');
  $b[] = spa_conseil_block_paragraph('Une entreprise est en cessation des paiements lorsqu’elle est dans l’impossibilité de faire face à son passif exigible avec son actif disponible. Cette définition ne correspond donc pas simplement :');
  $b[] = spa_conseil_block_list(array(
    'À un compte bancaire négatif',
    'À l’existence de dettes',
    'À un exercice déficitaire',
    'À une échéance ponctuellement impayée',
  ));
  $b[] = spa_conseil_block_paragraph('L’analyse doit être réalisée à partir de la situation concrète de l’entreprise.');
  $b[] = spa_conseil_block_resource_link('Lire le guide complet sur la cessation des paiements', 'Définition, délai de 45 jours et obligations du dirigeant.', 'cessation-de-paiement-que-faire-dirigeant-saint-etienne', 'info');

  $b[] = spa_conseil_block_heading('À partir de quand courent les 45 jours ?');
  $b[] = spa_conseil_block_paragraph('Le point essentiel est la date réelle de cessation des paiements. C’est à partir de cette date que le délai doit être apprécié.');
  $b[] = spa_conseil_block_paragraph('La difficulté est donc parfois moins de compter 45 jours que de déterminer à quel moment précis l’entreprise est devenue incapable de faire face à son passif exigible avec son actif disponible.');
  $b[] = spa_conseil_block_paragraph('Cette date peut nécessiter une véritable analyse de la trésorerie et des dettes de l’entreprise.');

  $b[] = spa_conseil_block_heading('Faut-il attendre 45 jours avant d’agir ?');
  $b[] = spa_conseil_block_paragraph('Non.');
  $b[] = spa_conseil_block_paragraph('Le délai constitue une limite, pas un objectif. Plus le dirigeant agit rapidement, plus il est possible d’examiner sereinement la situation, de préparer les éléments nécessaires et de déterminer la procédure adaptée.');
  $b[] = spa_conseil_block_paragraph('Attendre le dernier moment peut au contraire conduire à agir dans l’urgence alors que les difficultés se sont encore aggravées.');

  $b[] = spa_conseil_block_heading('Pourquoi cette date est-elle importante ?');
  $b[] = spa_conseil_block_paragraph('La date de cessation des paiements intervient dans plusieurs aspects de la procédure et doit donc être appréciée sérieusement.');
  $b[] = spa_conseil_block_paragraph('Lors de l’ouverture d’une procédure, le tribunal peut notamment être amené à examiner la situation de l’entreprise et à fixer cette date.');

  $b[] = spa_conseil_block_heading('Et si je ne sais pas si mon entreprise est déjà en cessation des paiements ?');
  $b[] = spa_conseil_block_paragraph('C’est précisément une situation dans laquelle il ne faut pas raisonner uniquement à partir du solde du compte bancaire.');
  $b[] = spa_conseil_block_paragraph('Il faut examiner notamment les dettes exigibles, les ressources immédiatement mobilisables, les délais dont bénéficie effectivement l’entreprise et l’ensemble de la situation financière.');

  $b[] = spa_conseil_block_diagnostic_cta('Faites un premier point', 'Le diagnostic du cabinet permet d’identifier plusieurs signaux pouvant justifier une analyse plus approfondie. Il ne détermine pas juridiquement une date de cessation des paiements, mais peut aider à identifier le niveau de vigilance.', 'Évaluer la situation de mon entreprise');
  $b[] = spa_conseil_block_contact_cta('Vous pensez que la cessation des paiements est déjà caractérisée ?', 'Sur ce type de situation, un échange rapide avec le cabinet est la démarche la plus utile — le diagnostic n’est jamais une étape obligatoire.', 'Échanger rapidement avec le cabinet', true);

  return implode('', $b);
}

function spa_conseil_body_redressement() {
  $b = array();
  $b[] = spa_conseil_block_paragraph('Pour un dirigeant qui n’a jamais été confronté à une procédure collective, l’ouverture d’un redressement judiciaire peut être particulièrement anxiogène.');
  $b[] = spa_conseil_block_paragraph('Qui sera présent au tribunal ? Vais-je perdre le contrôle de mon entreprise ? Que se passe-t-il après l’audience ? Mes salariés et mes clients seront-ils informés ?');
  $b[] = spa_conseil_block_paragraph('Comprendre les principales étapes permet d’aborder la procédure plus sereinement.');

  $b[] = spa_conseil_block_heading('Pourquoi un redressement judiciaire peut-il être ouvert ?');
  $b[] = spa_conseil_block_paragraph('Le redressement judiciaire concerne une entreprise en cessation des paiements lorsque son redressement apparaît possible.');
  $b[] = spa_conseil_block_paragraph('L’objectif de la procédure n’est donc pas, par principe, de fermer l’entreprise. Elle doit permettre d’examiner les possibilités de poursuite de l’activité et de traitement de ses difficultés dans un cadre collectif.');
  $b[] = spa_conseil_block_resource_link('Comprendre le redressement judiciaire', 'Ouvrir, préparer et suivre la procédure collective.', 'sauvegarde-et-redressement-judiciaire', 'account_balance');

  $b[] = spa_conseil_block_heading('Que regarde le tribunal ?');
  $b[] = spa_conseil_block_paragraph('Lors de l’ouverture de la procédure, la situation économique, financière et sociale de l’entreprise est examinée.');
  $b[] = spa_conseil_block_paragraph('Le dirigeant doit être en mesure de présenter son activité, l’origine des difficultés, sa situation actuelle et les perspectives de l’entreprise.');
  $b[] = spa_conseil_block_paragraph('Ce moment ne doit donc pas être abordé comme une simple formalité administrative. La qualité et la cohérence des informations présentées sont importantes pour permettre au tribunal de comprendre la situation.');

  $b[] = spa_conseil_block_heading('Le dirigeant perd-il immédiatement le contrôle de son entreprise ?');
  $b[] = spa_conseil_block_paragraph('L’ouverture d’un redressement judiciaire ne signifie pas automatiquement que le dirigeant cesse de gérer son entreprise.');
  $b[] = spa_conseil_block_paragraph('Les modalités de poursuite de la gestion dépendent notamment des décisions prises dans le cadre de la procédure et des missions éventuellement confiées aux différents intervenants.');

  $b[] = spa_conseil_block_heading('Qui intervient dans la procédure ?');
  $b[] = spa_conseil_block_paragraph('Différents professionnels et organes peuvent intervenir selon la procédure et la situation de l’entreprise, notamment le tribunal, le juge-commissaire et le mandataire judiciaire, ainsi que, dans certains dossiers, un administrateur judiciaire.');
  $b[] = spa_conseil_block_paragraph('Pour le dirigeant, comprendre le rôle de chacun est important : tous les intervenants n’ont pas la même mission.');

  $b[] = spa_conseil_block_heading('Que se passe-t-il après le jugement d’ouverture ?');
  $b[] = spa_conseil_block_paragraph('Lorsqu’une période d’observation est ouverte, elle permet notamment d’analyser la situation de l’entreprise et les possibilités de poursuite de l’activité.');
  $b[] = spa_conseil_block_paragraph('Pendant cette période, l’entreprise continue à fonctionner dans le cadre fixé par la procédure.');
  $b[] = spa_conseil_block_paragraph('La situation financière, l’activité et les perspectives sont examinées afin de déterminer quelle issue peut être envisagée.');
  $b[] = spa_conseil_block_paragraph('Selon l’évolution du dossier, la procédure peut notamment conduire à un plan lorsque les conditions le permettent ou à une autre issue si le redressement n’apparaît pas possible.');

  $b[] = spa_conseil_block_heading('Et les dettes antérieures ?');
  $b[] = spa_conseil_block_paragraph('L’ouverture d’une procédure collective modifie profondément la manière dont les dettes antérieures sont traitées.');
  $b[] = spa_conseil_block_paragraph('Le dirigeant ne doit donc pas continuer à gérer ses créanciers comme il le faisait avant le jugement.');

  $b[] = spa_conseil_block_heading('Faut-il préparer l’audience ?');
  $b[] = spa_conseil_block_paragraph('Oui.');
  $b[] = spa_conseil_block_paragraph('Le dirigeant doit notamment être en mesure d’expliquer clairement :');
  $b[] = spa_conseil_block_list(array(
    'L’activité de son entreprise',
    'L’origine des difficultés',
    'L’évolution récente de la situation',
    'Sa trésorerie',
    'Les principales dettes',
    'Les perspectives de poursuite de l’activité',
    'Les mesures déjà prises',
  ));
  $b[] = spa_conseil_block_paragraph('L’accompagnement en amont permet de préparer cette présentation et d’identifier les éléments utiles à l’examen de la situation par le tribunal.');

  $b[] = spa_conseil_block_contact_cta('Vous envisagez un redressement judiciaire ?', 'Sur ce type de situation, l’intention est déjà suffisamment avancée pour privilégier un échange direct avec le cabinet.', 'Échanger avec le cabinet', true);
  $b[] = spa_conseil_block_resource_link('Tout comprendre sur la sauvegarde et le redressement judiciaire', 'Ouvrir, préparer et suivre la procédure collective.', 'sauvegarde-et-redressement-judiciaire', 'account_balance');

  return implode('', $b);
}

function spa_conseil_body_salaires() {
  $b = array();
  $b[] = spa_conseil_block_paragraph('Ne pas pouvoir assurer la prochaine paie constitue un signal particulièrement sérieux pour une entreprise.');
  $b[] = spa_conseil_block_paragraph('La priorité est de déterminer immédiatement s’il s’agit d’un décalage temporaire de trésorerie ou si l’entreprise ne dispose plus des ressources suffisantes pour faire face à ses échéances.');
  $b[] = spa_conseil_block_paragraph('Dans cette situation, attendre la date de paiement des salaires avant d’agir réduit généralement les possibilités d’anticipation.');

  $b[] = spa_conseil_block_heading('Commencez par établir la trésorerie réellement disponible');
  $b[] = spa_conseil_block_paragraph('Il faut disposer d’une vision précise des sommes que l’entreprise peut effectivement mobiliser à très court terme. Examinez notamment :');
  $b[] = spa_conseil_block_list(array(
    'Les soldes bancaires',
    'Les autorisations de découvert réellement disponibles',
    'Les encaissements certains attendus avant la paie',
    'Les échéances bancaires',
    'Les dettes fiscales et sociales',
    'Les fournisseurs devant être réglés',
    'Le montant exact de la prochaine paie',
  ));
  $b[] = spa_conseil_block_paragraph('Il ne suffit pas de regarder le chiffre d’affaires attendu. La question est de savoir quelles ressources sont réellement disponibles au moment où les échéances doivent être payées.');

  $b[] = spa_conseil_block_heading('Le problème concerne-t-il uniquement les salaires ?');
  $b[] = spa_conseil_block_paragraph('Une difficulté de paie intervient rarement isolément. Il faut vérifier si l’entreprise rencontre également des retards concernant :');
  $b[] = spa_conseil_block_list(array(
    'L’URSSAF',
    'Les fournisseurs',
    'Les échéances bancaires',
    'La TVA ou d’autres dettes fiscales',
    'Le loyer',
    'D’autres charges devenues exigibles',
  ));
  $b[] = spa_conseil_block_paragraph('Lorsque plusieurs échéances ne peuvent plus être honorées, il faut examiner la situation financière de l’entreprise dans son ensemble.');
  $b[] = spa_conseil_block_resource_link('Comprendre la cessation des paiements', 'Définition, délai de 45 jours et obligations du dirigeant.', 'cessation-de-paiement-que-faire-dirigeant-saint-etienne', 'info');

  $b[] = spa_conseil_block_heading('Pourquoi faut-il agir avant l’impayé ?');
  $b[] = spa_conseil_block_paragraph('Plus le dirigeant intervient tôt, plus il conserve de possibilités pour analyser la situation et déterminer la réponse appropriée. Selon les circonstances, il peut être nécessaire d’examiner :');
  $b[] = spa_conseil_block_list(array(
    'Les possibilités immédiates de financement',
    'Les négociations avec certains créanciers',
    'Les procédures de prévention',
    'Ou, si la situation est plus avancée, l’ouverture d’une procédure collective',
  ));
  $b[] = spa_conseil_block_paragraph('La bonne solution dépend de la situation réelle de l’entreprise.');

  $b[] = spa_conseil_block_heading('L’impossibilité de payer les salaires signifie-t-elle nécessairement une cessation des paiements ?');
  $b[] = spa_conseil_block_paragraph('Pas automatiquement.');
  $b[] = spa_conseil_block_paragraph('La cessation des paiements répond à une définition juridique précise : l’entreprise doit être dans l’impossibilité de faire face à son passif exigible avec son actif disponible. La seule existence d’une difficulté importante de trésorerie ne suffit donc pas à tirer une conclusion sans examiner l’ensemble de la situation.');
  $b[] = spa_conseil_block_paragraph('En revanche, l’impossibilité annoncée de payer les prochains salaires justifie une analyse rapide.');
  $b[] = spa_conseil_block_resource_link('Cessation des paiements : que signifie réellement le délai de 45 jours ?', 'Comprendre le délai et pourquoi il ne faut pas attendre son expiration.', 'cessation-paiements-delai-45-jours', 'calendar_month');

  $b[] = spa_conseil_block_heading('Que se passe-t-il si l’entreprise doit être placée en redressement judiciaire ?');
  $b[] = spa_conseil_block_paragraph('Le redressement judiciaire n’entraîne pas automatiquement l’arrêt de l’entreprise. Lorsque son redressement reste possible, l’activité peut se poursuivre dans le cadre d’une période d’observation.');
  $b[] = spa_conseil_block_paragraph('La situation des salariés et des créances salariales obéit alors à des règles spécifiques.');
  $b[] = spa_conseil_block_resource_link('Comprendre la sauvegarde et le redressement judiciaire', 'Ouvrir, préparer et suivre la procédure collective.', 'sauvegarde-et-redressement-judiciaire', 'account_balance');

  $b[] = spa_conseil_block_contact_cta('Vous savez déjà que la prochaine paie est compromise ?', 'Cette situation justifie une analyse rapide de la trésorerie, des échéances et des solutions encore disponibles.', 'Échanger avec le cabinet', true);

  return implode('', $b);
}

function spa_conseil_body_negocier_dettes() {
  $b = array();
  $b[] = spa_conseil_block_paragraph('Lorsqu’une entreprise rencontre une tension de trésorerie, obtenir des délais auprès de ses créanciers peut permettre d’éviter une aggravation de la situation.');
  $b[] = spa_conseil_block_paragraph('Mais négocier efficacement ne consiste pas simplement à repousser les échéances.');
  $b[] = spa_conseil_block_paragraph('Il faut déterminer ce que l’entreprise est réellement capable de payer et vérifier que les délais obtenus permettront de retrouver une situation viable.');

  $b[] = spa_conseil_block_heading('Commencez par avoir une vision globale des dettes');
  $b[] = spa_conseil_block_paragraph('Avant toute négociation, établissez précisément :');
  $b[] = spa_conseil_block_list(array(
    'Les sommes dues',
    'Les dates d’échéance',
    'Les créanciers concernés',
    'Les éventuelles garanties',
    'Les retards déjà accumulés',
    'Les prochaines charges',
    'Les encaissements attendus',
  ));
  $b[] = spa_conseil_block_paragraph('Cette vision globale est essentielle. Obtenir trois mois auprès d’un fournisseur ne résout pas le problème si l’entreprise doit simultanément faire face à une dette URSSAF, une échéance bancaire et un découvert supprimé.');

  $b[] = spa_conseil_block_heading('Peut-on négocier directement avec ses créanciers ?');
  $b[] = spa_conseil_block_paragraph('Oui.');
  $b[] = spa_conseil_block_paragraph('Lorsqu’une difficulté reste ponctuelle et concerne un nombre limité de créanciers, une négociation directe peut parfois suffire. L’entreprise peut notamment rechercher :');
  $b[] = spa_conseil_block_list(array('Un délai', 'Un échéancier', 'Un report', 'Une adaptation temporaire des modalités de règlement'));
  $b[] = spa_conseil_block_paragraph('La proposition doit néanmoins être réaliste. Un échéancier impossible à respecter ne fait que différer la difficulté.');

  $b[] = spa_conseil_block_heading('Que faire lorsque plusieurs créanciers doivent accepter des efforts ?');
  $b[] = spa_conseil_block_paragraph('La situation devient plus complexe lorsque la survie de l’entreprise dépend simultanément d’un effort de plusieurs partenaires. Une banque peut accepter un délai à condition que les fournisseurs en accordent également. Un fournisseur peut refuser de patienter s’il pense que les autres créanciers seront payés avant lui.');
  $b[] = spa_conseil_block_paragraph('Dans ce type de situation, une négociation organisée peut devenir préférable à une succession de discussions isolées.');
  $b[] = spa_conseil_block_resource_link('Mandat ad hoc ou conciliation : quelle procédure choisir ?', 'Organiser les négociations avec plusieurs créanciers dans un cadre confidentiel.', 'mandat-ad-hoc-ou-conciliation', 'shield');

  $b[] = spa_conseil_block_heading('Faut-il négocier avec l’URSSAF de la même manière qu’avec un fournisseur ?');
  $b[] = spa_conseil_block_paragraph('Non.');
  $b[] = spa_conseil_block_paragraph('La nature du créancier compte. Les modalités de discussion avec un organisme social, une administration fiscale, une banque ou un fournisseur ne sont pas identiques.');
  $b[] = spa_conseil_block_resource_link('Mon entreprise ne peut plus payer l’URSSAF : que faire ?', 'Les premières questions à se poser face à une dette sociale.', 'entreprise-ne-peut-plus-payer-urssaf', 'schedule');

  $b[] = spa_conseil_block_heading('Quand la négociation ne suffit-elle plus ?');
  $b[] = spa_conseil_block_paragraph('Si l’entreprise accumule les accords et les échéanciers mais reste incapable de payer les charges courantes, le problème est probablement plus profond.');
  $b[] = spa_conseil_block_paragraph('Il faut alors vérifier si l’entreprise peut encore faire face à son passif exigible avec son actif disponible.');
  $b[] = spa_conseil_block_resource_link('Comprendre la cessation des paiements', 'Définition, délai de 45 jours et obligations du dirigeant.', 'cessation-de-paiement-que-faire-dirigeant-saint-etienne', 'info');

  $b[] = spa_conseil_block_contact_cta('Vous devez négocier avec plusieurs créanciers ?', 'Une analyse globale permet de déterminer s’il est préférable de négocier directement ou d’organiser les discussions dans le cadre d’une procédure de prévention.', 'Échanger avec le cabinet');

  return implode('', $b);
}

function spa_conseil_body_assignation() {
  $b = array();
  $b[] = spa_conseil_block_paragraph('Recevoir une assignation d’un fournisseur, d’une banque ou d’un autre créancier ne doit pas être ignoré.');
  $b[] = spa_conseil_block_paragraph('La première étape consiste à comprendre précisément ce qui est demandé, à vérifier la dette invoquée et à déterminer si le litige concerne une créance isolée ou révèle des difficultés financières plus générales.');

  $b[] = spa_conseil_block_heading('Commencez par identifier précisément la procédure engagée');
  $b[] = spa_conseil_block_paragraph('Une assignation est un acte de procédure. Il faut notamment identifier :');
  $b[] = spa_conseil_block_list(array(
    'Le créancier',
    'La somme réclamée',
    'Le fondement de sa demande',
    'La juridiction saisie',
    'La date de l’audience',
    'Les pièces invoquées',
    'Ce que le créancier demande exactement au tribunal',
  ));
  $b[] = spa_conseil_block_paragraph('Toutes les assignations n’ont pas les mêmes conséquences.');

  $b[] = spa_conseil_block_heading('La dette est-elle contestée ?');
  $b[] = spa_conseil_block_paragraph('Il faut distinguer deux situations. L’entreprise peut contester :');
  $b[] = spa_conseil_block_list(array('L’existence de la dette', 'Son montant', 'L’exécution du contrat', 'Certaines factures', 'Ou les conditions dans lesquelles le paiement est réclamé'));
  $b[] = spa_conseil_block_paragraph('Dans ce cas, la question relève notamment du contentieux commercial.');
  $b[] = spa_conseil_block_resource_link('Découvrir l’accompagnement en contentieux civil et commercial', 'Contrats, créances, relations commerciales et représentation devant les juridictions.', 'contentieux-civil-commercial-saint-etienne', 'gavel');
  $b[] = spa_conseil_block_paragraph('À l’inverse, l’entreprise peut reconnaître la dette mais ne plus être capable de la régler. La problématique devient alors également financière.');

  $b[] = spa_conseil_block_heading('Le créancier demande-t-il l’ouverture d’une procédure collective ?');
  $b[] = spa_conseil_block_paragraph('Un créancier peut, dans certaines conditions, demander au tribunal l’ouverture d’un redressement ou d’une liquidation judiciaire. Une telle assignation nécessite une analyse particulièrement rapide.');
  $b[] = spa_conseil_block_paragraph('Il faut notamment déterminer la situation réelle de trésorerie de l’entreprise et vérifier si elle se trouve ou non en cessation des paiements.');
  $b[] = spa_conseil_block_resource_link('Comprendre la cessation des paiements', 'Définition, délai de 45 jours et obligations du dirigeant.', 'cessation-de-paiement-que-faire-dirigeant-saint-etienne', 'info');

  $b[] = spa_conseil_block_heading('Quels éléments faut-il préparer ?');
  $b[] = spa_conseil_block_paragraph('Selon la procédure, il peut être nécessaire de réunir rapidement :');
  $b[] = spa_conseil_block_list(array(
    'L’assignation',
    'Les contrats concernés',
    'Les factures',
    'Les échanges avec le créancier',
    'Les mises en demeure',
    'Les justificatifs de paiement',
    'Les comptes récents',
    'La situation de trésorerie',
    'L’état des principales dettes et créances',
  ));
  $b[] = spa_conseil_block_paragraph('La réponse doit être préparée à partir de la situation réelle de l’entreprise.');

  $b[] = spa_conseil_block_heading('Et si l’entreprise est réellement en difficulté ?');
  $b[] = spa_conseil_block_paragraph('L’assignation ne doit pas empêcher le dirigeant de reprendre l’initiative. Si la cessation des paiements est caractérisée, il faut également examiner les obligations du dirigeant et les procédures susceptibles de correspondre à la situation.');
  $b[] = spa_conseil_block_resource_link('Cessation des paiements : comprendre le délai de 45 jours', 'Pourquoi ce délai ne doit pas être considéré comme une période d’attente.', 'cessation-paiements-delai-45-jours', 'calendar_month');

  $b[] = spa_conseil_block_contact_cta('Votre entreprise vient de recevoir une assignation ?', 'Le délai avant l’audience doit être utilisé pour comprendre la procédure, réunir les éléments nécessaires et déterminer la stratégie adaptée.', 'Contacter le cabinet', true);

  return implode('', $b);
}

function spa_conseil_body_mandat_ad_hoc() {
  $b = array();
  $b[] = spa_conseil_block_paragraph('Lorsqu’une entreprise commence à rencontrer des difficultés, il n’est pas toujours nécessaire d’attendre une procédure collective pour agir.');
  $b[] = spa_conseil_block_paragraph('Le mandat ad hoc et la conciliation permettent d’organiser la recherche de solutions dans un cadre confidentiel.');
  $b[] = spa_conseil_block_paragraph('Ces deux procédures poursuivent des objectifs proches, mais ne répondent pas exactement aux mêmes situations.');

  $b[] = spa_conseil_block_heading('Pourquoi agir avant que la situation ne devienne critique ?');
  $b[] = spa_conseil_block_paragraph('Les difficultés peuvent commencer par un événement apparemment isolé :');
  $b[] = spa_conseil_block_list(array(
    'Suppression d’un découvert',
    'Dette fiscale ou sociale',
    'Fournisseur stratégique devenu impatient',
    'Remboursement bancaire devenu difficile',
    'Conflit avec un partenaire',
    'Baisse temporaire d’activité',
  ));
  $b[] = spa_conseil_block_paragraph('Lorsque l’entreprise dispose encore de marges de manœuvre, intervenir suffisamment tôt facilite généralement la recherche de solutions.');
  $b[] = spa_conseil_block_resource_link('Découvrir les solutions de prévention des difficultés', 'Mandat ad hoc et conciliation : comment ça fonctionne.', 'prevention-difficultes-entreprise-saint-etienne', 'shield');

  $b[] = spa_conseil_block_heading('Qu’est-ce qu’un mandat ad hoc ?');
  $b[] = spa_conseil_block_paragraph('Le mandat ad hoc est une procédure de prévention souple et confidentielle.');
  $b[] = spa_conseil_block_paragraph('À la demande du dirigeant, le président du tribunal désigne un mandataire ad hoc et définit sa mission en fonction des difficultés rencontrées. Il peut notamment faciliter les discussions avec certains créanciers ou partenaires.');
  $b[] = spa_conseil_block_paragraph('Le dirigeant reste à la tête de son entreprise.');

  $b[] = spa_conseil_block_heading('Qu’est-ce qu’une conciliation ?');
  $b[] = spa_conseil_block_paragraph('La conciliation est également une procédure confidentielle destinée à favoriser la recherche d’un accord avec les principaux créanciers et partenaires de l’entreprise.');
  $b[] = spa_conseil_block_paragraph('Elle est plus encadrée dans le temps et peut être utilisée dans certaines conditions lorsque l’entreprise rencontre des difficultés juridiques, économiques ou financières.');

  $b[] = spa_conseil_block_heading('Quelle est la principale différence ?');
  $b[] = spa_conseil_block_paragraph('Il n’existe pas de réponse universelle. Le choix dépend notamment :');
  $b[] = spa_conseil_block_list(array(
    'De la nature des difficultés',
    'De leur ancienneté',
    'Du nombre de créanciers concernés',
    'De l’état des négociations',
    'De l’existence éventuelle d’une cessation des paiements',
    'De l’objectif recherché',
  ));
  $b[] = spa_conseil_block_paragraph('Le mandat ad hoc est particulièrement souple.');
  $b[] = spa_conseil_block_paragraph('La conciliation offre un cadre différent, notamment lorsque la recherche d’un accord plus structuré devient nécessaire.');

  $b[] = spa_conseil_block_heading('Peut-on encore demander une conciliation en cessation des paiements ?');
  $b[] = spa_conseil_block_paragraph('La conciliation peut, sous certaines conditions, être ouverte lorsque l’entreprise est en cessation des paiements depuis moins de 45 jours.');
  $b[] = spa_conseil_block_paragraph('Cette date devient donc déterminante.');
  $b[] = spa_conseil_block_resource_link('Cessation des paiements : que signifie réellement le délai de 45 jours ?', 'Comprendre le délai et pourquoi il ne faut pas attendre son expiration.', 'cessation-paiements-delai-45-jours', 'calendar_month');

  $b[] = spa_conseil_block_heading('Faut-il attendre que les négociations soient bloquées ?');
  $b[] = spa_conseil_block_paragraph('Non.');
  $b[] = spa_conseil_block_paragraph('L’intérêt des procédures de prévention réside précisément dans l’anticipation. Plus l’entreprise conserve de trésorerie, de partenaires disposés à négocier et de perspectives d’activité, plus les discussions peuvent être constructives.');
  $b[] = spa_conseil_block_resource_link('Comment négocier les dettes de mon entreprise ?', 'Vision globale des dettes, négociation directe ou organisée.', 'negocier-dettes-entreprise', 'balance');

  $b[] = spa_conseil_block_contact_cta('Mandat ad hoc ou conciliation ?', 'Le choix dépend de la situation financière de l’entreprise, des créanciers concernés et de l’objectif des négociations.', 'Échanger avec le cabinet');
  $b[] = spa_conseil_block_resource_link('Vous hésitez encore sur la marche à suivre ?', 'Le diagnostic du cabinet permet d’objectiver la situation en quelques questions.', 'diagnostic-entreprise-en-difficulte', 'route');

  return implode('', $b);
}

function spa_conseil_body_redressement_dirigeant() {
  $b = array();
  $b[] = spa_conseil_block_paragraph('L’ouverture d’un redressement judiciaire ne signifie pas automatiquement que le chef d’entreprise perd la direction de sa société.');
  $b[] = spa_conseil_block_paragraph('L’activité continue et le dirigeant conserve un rôle central. La procédure modifie néanmoins le cadre dans lequel certaines décisions sont prises.');

  $b[] = spa_conseil_block_heading('Le dirigeant reste-t-il à la tête de l’entreprise ?');
  $b[] = spa_conseil_block_paragraph('Oui, en principe.');
  $b[] = spa_conseil_block_paragraph('Le dirigeant reste en fonction pendant la période d’observation. Selon la situation de l’entreprise et la décision du tribunal, un administrateur judiciaire peut cependant être désigné.');
  $b[] = spa_conseil_block_paragraph('Sa mission peut notamment consister à surveiller ou à assister le dirigeant dans la gestion.');

  $b[] = spa_conseil_block_heading('Le dirigeant peut-il continuer à prendre toutes les décisions ?');
  $b[] = spa_conseil_block_paragraph('La gestion de l’entreprise se poursuit, mais elle s’inscrit désormais dans le cadre de la procédure collective.');
  $b[] = spa_conseil_block_paragraph('Certaines décisions peuvent nécessiter l’intervention des organes de la procédure.');
  $b[] = spa_conseil_block_paragraph('Le fonctionnement concret dépend notamment du jugement d’ouverture et de la mission éventuellement confiée à l’administrateur judiciaire.');
  $b[] = spa_conseil_block_resource_link('Que se passe-t-il lors de l’ouverture d’un redressement judiciaire ?', 'Audience, jugement, période d’observation, mandataire.', 'ouverture-redressement-judiciaire', 'gavel');

  $b[] = spa_conseil_block_heading('Que devient la rémunération du dirigeant ?');
  $b[] = spa_conseil_block_paragraph('L’ouverture d’un redressement judiciaire n’entraîne pas automatiquement la disparition de toute rémunération du dirigeant.');
  $b[] = spa_conseil_block_paragraph('Sa situation doit cependant être appréciée dans le cadre de la procédure et de la situation économique de l’entreprise.');

  $b[] = spa_conseil_block_heading('Quel est le rôle du dirigeant pendant la période d’observation ?');
  $b[] = spa_conseil_block_paragraph('Il doit notamment participer activement à l’analyse de la situation et aux perspectives de redressement. Cela suppose de disposer d’informations fiables concernant :');
  $b[] = spa_conseil_block_list(array('La trésorerie', 'L’activité', 'Les charges', 'Les contrats', 'Les salariés', 'Les créanciers', 'Les perspectives commerciales'));
  $b[] = spa_conseil_block_paragraph('L’objectif est de déterminer si l’entreprise peut retrouver une exploitation durable et envisager un plan.');

  $b[] = spa_conseil_block_heading('Le redressement judiciaire signifie-t-il que le dirigeant a commis une faute ?');
  $b[] = spa_conseil_block_paragraph('Non.');
  $b[] = spa_conseil_block_paragraph('Une entreprise peut rencontrer des difficultés pour de nombreuses raisons économiques ou commerciales. L’ouverture d’un redressement judiciaire n’est pas, en elle-même, une sanction du dirigeant.');
  $b[] = spa_conseil_block_paragraph('Sa responsabilité personnelle constitue une question distincte.');

  $b[] = spa_conseil_block_heading('Et si le dirigeant s’est porté caution ?');
  $b[] = spa_conseil_block_paragraph('Les engagements personnels du dirigeant doivent être distingués des dettes de la société.');
  $b[] = spa_conseil_block_resource_link('Caution personnelle du dirigeant : que se passe-t-il si l’entreprise ne peut plus payer ?', 'Distinguer la dette de la société et l’engagement personnel du dirigeant.', 'caution-personnelle-dirigeant-entreprise-difficulte', 'info');
  $b[] = spa_conseil_block_resource_link('Comprendre la sauvegarde et le redressement judiciaire', 'Ouvrir, préparer et suivre la procédure collective.', 'sauvegarde-et-redressement-judiciaire', 'account_balance');

  $b[] = spa_conseil_block_contact_cta('Votre entreprise est concernée par un redressement judiciaire ?', 'Chaque procédure dépend de la situation financière de l’entreprise, du jugement d’ouverture et des perspectives de redressement.', 'Échanger avec le cabinet', true);

  return implode('', $b);
}

function spa_conseil_body_redressement_parties() {
  $b = array();
  $b[] = spa_conseil_block_paragraph('L’ouverture d’un redressement judiciaire ne signifie pas l’arrêt immédiat de l’entreprise. La période d’observation doit au contraire permettre, lorsque cela reste possible, la poursuite de l’activité et la recherche d’une solution de redressement.');
  $b[] = spa_conseil_block_paragraph('Cette nouvelle situation modifie néanmoins les relations avec les salariés, fournisseurs, créanciers et partenaires.');

  $b[] = spa_conseil_block_heading('Les salariés continuent-ils à travailler ?');
  $b[] = spa_conseil_block_paragraph('En principe, l’activité se poursuit pendant la période d’observation. Les contrats de travail ne disparaissent donc pas automatiquement du fait de l’ouverture du redressement.');
  $b[] = spa_conseil_block_paragraph('Selon la situation économique de l’entreprise, des adaptations peuvent néanmoins intervenir au cours de la procédure.');

  $b[] = spa_conseil_block_heading('Que deviennent les salaires impayés ?');
  $b[] = spa_conseil_block_paragraph('Les créances salariales bénéficient d’un régime spécifique dans les procédures collectives. Leur traitement ne doit pas être confondu avec celui d’une facture fournisseur ordinaire.');
  $b[] = spa_conseil_block_paragraph('Si des salaires étaient déjà impayés avant l’ouverture, leur prise en charge doit être examinée dans le cadre de la procédure.');

  $b[] = spa_conseil_block_heading('Que deviennent les anciennes factures fournisseurs ?');
  $b[] = spa_conseil_block_paragraph('Le jugement d’ouverture modifie le traitement des dettes antérieures. Les créanciers concernés doivent en principe déclarer leurs créances dans le cadre de la procédure.');
  $b[] = spa_conseil_block_paragraph('L’entreprise ne doit donc pas gérer une ancienne dette fournisseur comme elle le faisait avant l’ouverture du redressement.');

  $b[] = spa_conseil_block_heading('Les fournisseurs peuvent-ils arrêter immédiatement leurs contrats ?');
  $b[] = spa_conseil_block_paragraph('L’ouverture d’un redressement judiciaire n’entraîne pas automatiquement la disparition des contrats en cours. Le droit des procédures collectives prévoit un régime spécifique pour leur poursuite.');
  $b[] = spa_conseil_block_paragraph('Pour l’entreprise, conserver les fournisseurs indispensables à son activité peut devenir un enjeu majeur de la période d’observation.');

  $b[] = spa_conseil_block_heading('Et les clients ?');
  $b[] = spa_conseil_block_paragraph('L’entreprise continue son activité. Ses relations commerciales avec ses clients peuvent donc se poursuivre.');
  $b[] = spa_conseil_block_paragraph('Le maintien du chiffre d’affaires et de la confiance des clients constitue souvent un élément essentiel de la réussite du redressement.');

  $b[] = spa_conseil_block_resource_link('Que se passe-t-il lors de l’ouverture d’un redressement judiciaire ?', 'Audience, jugement, période d’observation, mandataire.', 'ouverture-redressement-judiciaire', 'gavel');
  $b[] = spa_conseil_block_resource_link('Comprendre la sauvegarde et le redressement judiciaire', 'Ouvrir, préparer et suivre la procédure collective.', 'sauvegarde-et-redressement-judiciaire', 'account_balance');

  $b[] = spa_conseil_block_contact_cta('Vous devez anticiper les conséquences du redressement sur l’activité ?', 'La gestion des salariés, contrats, fournisseurs et créanciers doit être intégrée à la stratégie globale de la procédure.', 'Échanger avec le cabinet', true);

  return implode('', $b);
}

function spa_conseil_body_liquidation_risques() {
  $b = array();
  $b[] = spa_conseil_block_paragraph('La liquidation judiciaire d’une société ne signifie pas automatiquement que son dirigeant doit personnellement rembourser toutes les dettes de l’entreprise.');
  $b[] = spa_conseil_block_paragraph('Il faut distinguer la situation de la société de celle de son dirigeant. Certains engagements ou comportements peuvent néanmoins créer un risque personnel.');

  $b[] = spa_conseil_block_heading('Les dettes de la société deviennent-elles celles du dirigeant ?');
  $b[] = spa_conseil_block_paragraph('Pas automatiquement.');
  $b[] = spa_conseil_block_paragraph('Lorsque l’activité est exercée par l’intermédiaire d’une société dont la responsabilité des associés est limitée, les dettes professionnelles restent en principe celles de la société. La liquidation ne suffit donc pas, à elle seule, à transférer toutes ces dettes au dirigeant.');
  $b[] = spa_conseil_block_paragraph('Il faut néanmoins examiner la forme juridique de l’entreprise et la situation personnelle du dirigeant.');

  $b[] = spa_conseil_block_heading('Le dirigeant s’est-il porté caution ?');
  $b[] = spa_conseil_block_paragraph('C’est une question distincte. Lorsqu’un dirigeant garantit personnellement une dette de la société, son engagement peut l’exposer personnellement. Il faut alors analyser l’acte de cautionnement et la procédure concernant la société.');
  $b[] = spa_conseil_block_resource_link('Caution personnelle du dirigeant : que se passe-t-il si l’entreprise ne peut plus payer ?', 'Distinguer la dette de la société et l’engagement personnel du dirigeant.', 'caution-personnelle-dirigeant-entreprise-difficulte', 'info');

  $b[] = spa_conseil_block_heading('Une faute de gestion peut-elle engager la responsabilité du dirigeant ?');
  $b[] = spa_conseil_block_paragraph('Dans certaines situations prévues par la loi, le comportement du dirigeant peut être examiné dans le cadre de la procédure. Mais l’échec économique de l’entreprise ne constitue pas, à lui seul, une faute de gestion.');
  $b[] = spa_conseil_block_paragraph('Il faut donc éviter de confondre :');
  $b[] = spa_conseil_block_list(array('Difficultés économiques', 'Liquidation de la société', 'Responsabilité personnelle du dirigeant'));
  $b[] = spa_conseil_block_paragraph('Ce sont trois questions différentes.');

  $b[] = spa_conseil_block_heading('Existe-t-il un risque de sanction personnelle ?');
  $b[] = spa_conseil_block_paragraph('Certaines situations peuvent conduire à l’examen de sanctions prévues par le droit des procédures collectives. Elles ne résultent pas automatiquement de l’ouverture d’une liquidation judiciaire.');
  $b[] = spa_conseil_block_paragraph('La situation doit être appréciée au regard des faits du dossier et du comportement du dirigeant.');

  $b[] = spa_conseil_block_heading('Le patrimoine personnel est-il menacé ?');
  $b[] = spa_conseil_block_paragraph('Cela dépend notamment :');
  $b[] = spa_conseil_block_list(array('De la forme juridique', 'Des cautions consenties', 'Des garanties personnelles', 'Des éventuelles sûretés', 'Et, dans certaines situations, de la responsabilité susceptible d’être recherchée'));
  $b[] = spa_conseil_block_paragraph('Une analyse précise est donc nécessaire avant de conclure que le patrimoine personnel est ou n’est pas exposé.');
  $b[] = spa_conseil_block_resource_link('Comprendre la liquidation judiciaire', 'Ouverture, actifs, cession et responsabilité.', 'liquidation-judiciaire-saint-etienne', 'gavel');

  $b[] = spa_conseil_block_contact_cta('Votre entreprise risque une liquidation judiciaire ?', 'Il est utile d’identifier précisément ce qui relève de la société et ce qui peut éventuellement concerner personnellement le dirigeant.', 'Échanger avec le cabinet', true);

  return implode('', $b);
}

function spa_conseil_body_caution_personnelle() {
  $b = array();
  $b[] = spa_conseil_block_paragraph('Lorsqu’une banque accorde un financement à une société, elle peut demander au dirigeant de garantir personnellement tout ou partie de la dette.');
  $b[] = spa_conseil_block_paragraph('Tant que l’entreprise rembourse normalement, cet engagement reste souvent en arrière-plan. Lorsque les difficultés apparaissent, il devient essentiel de savoir précisément ce qui a été signé.');

  $b[] = spa_conseil_block_heading('Qu’est-ce qu’une caution personnelle ?');
  $b[] = spa_conseil_block_paragraph('Le cautionnement est un engagement par lequel une personne garantit auprès d’un créancier le paiement de la dette d’une autre personne. Dans le cadre d’une entreprise, le dirigeant peut notamment avoir garanti :');
  $b[] = spa_conseil_block_list(array('Un prêt professionnel', 'Un découvert bancaire', 'Un crédit', 'Certaines obligations locatives', 'Ou un autre engagement de la société'));
  $b[] = spa_conseil_block_paragraph('La dette de la société et l’engagement personnel du dirigeant restent juridiquement distincts.');

  $b[] = spa_conseil_block_heading('La banque peut-elle demander immédiatement au dirigeant de payer ?');
  $b[] = spa_conseil_block_paragraph('Cela dépend de la situation et de l’acte signé. Il faut notamment examiner :');
  $b[] = spa_conseil_block_list(array('La dette garantie', 'Le montant de l’engagement', 'Sa durée', 'Les éventuelles limitations', 'Les incidents de paiement', 'La procédure éventuellement ouverte concernant l’entreprise'));
  $b[] = spa_conseil_block_paragraph('Il ne faut donc pas conclure à partir du seul montant de la dette de la société.');

  $b[] = spa_conseil_block_heading('Que faut-il vérifier dans l’acte de cautionnement ?');
  $b[] = spa_conseil_block_paragraph('Le document signé constitue le point de départ de l’analyse. Il faut notamment retrouver :');
  $b[] = spa_conseil_block_list(array('L’acte de cautionnement', 'Le contrat de prêt ou de financement', 'Les éventuels avenants', 'Les courriers du créancier', 'Les mises en demeure', 'Le montant restant dû', 'Les autres garanties éventuellement consenties'));

  $b[] = spa_conseil_block_heading('Que se passe-t-il si l’entreprise entre en procédure collective ?');
  $b[] = spa_conseil_block_paragraph('L’ouverture d’une procédure collective concernant la société peut avoir des conséquences sur les modalités dans lesquelles la caution peut être poursuivie. Ces conséquences diffèrent notamment selon la procédure et son évolution.');
  $b[] = spa_conseil_block_paragraph('Il faut donc examiner simultanément la situation de l’entreprise et celle de la caution.');
  $b[] = spa_conseil_block_resource_link('Redressement judiciaire : que devient le dirigeant ?', 'Le rôle et les pouvoirs du dirigeant pendant la procédure.', 'redressement-judiciaire-dirigeant', 'route');

  $b[] = spa_conseil_block_heading('Et en cas de liquidation judiciaire ?');
  $b[] = spa_conseil_block_paragraph('La situation personnelle du dirigeant doit être distinguée de celle de la société. Les garanties personnelles font partie des principaux éléments à examiner.');
  $b[] = spa_conseil_block_resource_link('Liquidation judiciaire : quels sont les risques pour le dirigeant ?', 'Dettes de la société, caution, faute de gestion : ce qu’il faut distinguer.', 'liquidation-judiciaire-risques-dirigeant', 'gavel');

  $b[] = spa_conseil_block_heading('Peut-on contester une caution ?');
  $b[] = spa_conseil_block_paragraph('La portée ou l’efficacité d’un cautionnement peut dépendre de nombreux éléments propres au document et aux circonstances dans lesquelles il a été conclu. Il n’est donc pas possible de déterminer la situation du dirigeant sans examiner l’acte concerné.');

  $b[] = spa_conseil_block_contact_cta('Votre banque ou un créancier invoque votre caution personnelle ?', 'L’analyse doit porter sur l’engagement que vous avez réellement signé et sur la situation actuelle de l’entreprise.', 'Échanger avec le cabinet', true);

  return implode('', $b);
}

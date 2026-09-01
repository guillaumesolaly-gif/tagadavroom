<?php
/**
 * Cheval — référentiel Race / Stud-book / Appellation (Étape 8 de la demande).
 *
 * Données issues de GWS_referentiel_races_appellations_IFCE.xlsx (fourni avec la demande),
 * lui-même dérivé des tables IFCE/SIRE officielles (abréviations des races, table
 * races/appellations). Généré une seule fois depuis ce fichier source — voir
 * tests/README.md pour le décompte exact des entrées et la procédure de mise à jour.
 *
 * STRUCTURE PAR ENTRÉE : {code, ifce, gws, type, alias, usage}
 * - code  : code canonique GWS/IFCE (donnée STRUCTURÉE stockée en base, jamais un libellé) ;
 * - ifce  : libellé officiel IFCE ;
 * - gws   : libellé GWS recommandé (affichage) ;
 * - type  : 'race' ou 'appellation' — distinction technique conservée (§3 de la demande),
 *           jamais exposée comme deux champs séparés côté utilisateur ;
 * - alias : codes historiques/import supplémentaires résolvant vers CE code canonique
 *           (ex. 'SFA' -> 'SF') — jamais stockés en base, uniquement une aide à la
 *           RECONNAISSANCE (saisie libre, import IFCE) ;
 * - usage : indication informative reprise du référentiel source ('courant', 'très
 *           courant sport'...) — sert uniquement de repli neutre pour les premières
 *           suggestions d'un utilisateur sans historique (voir
 *           gwseq_race_referentiel_default_suggestions()), jamais une règle métier.
 */

if (!defined('ABSPATH')) exit;

function gwseq_race_referentiel_raw_entries() {
  return array(
    array('code' => 'AA', 'ifce' => 'Anglo-Arabe', 'gws' => 'Anglo-Arabe', 'type' => 'race', 'alias' => array(), 'usage' => 'courant'),
    array('code' => 'AB', 'ifce' => 'Arabe-Barbe', 'gws' => 'Arabe-Barbe', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'AC', 'ifce' => 'Anglo-Arabe De Complément', 'gws' => 'Anglo-Arabe de complément', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'ACO', 'ifce' => 'Ane Du Cotentin', 'gws' => 'Âne du Cotentin', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'AES', 'ifce' => 'Anglo European Stud-Book', 'gws' => 'AES', 'type' => 'race', 'alias' => array(), 'usage' => 'courant sport'),
    array('code' => 'AGNB', 'ifce' => 'Ane Grand Noir Du Berry', 'gws' => 'Âne Grand Noir du Berry', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'AMHR', 'ifce' => 'American Miniature Horse Registry', 'gws' => 'American Miniature Horse', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'AN', 'ifce' => 'Ane Normand', 'gws' => 'Âne Normand', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'ANBO', 'ifce' => 'Ane Bourbonnais', 'gws' => 'Âne Bourbonnais', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'ANC', 'ifce' => 'Ane Corse', 'gws' => 'Âne Corse', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'APPAL', 'ifce' => 'Appaloosa', 'gws' => 'Appaloosa', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'APRO', 'ifce' => 'Ane De Provence', 'gws' => 'Âne de Provence', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'APY', 'ifce' => 'Ane Des Pyrénées', 'gws' => 'Âne des Pyrénées', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'AQPS', 'ifce' => 'Autre Que Pur-Sang', 'gws' => 'AQPS', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'AR', 'ifce' => 'Arabe', 'gws' => 'Arabe', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'ARD', 'ifce' => 'Trait Ardennais', 'gws' => 'Ardennais', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'ARFR', 'ifce' => 'Arabo-Frison', 'gws' => 'Arabo-Frison', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'ASPC', 'ifce' => 'American Shetland Pony', 'gws' => 'American Shetland Pony', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'AT', 'ifce' => 'Akhal Teke De Pur-Sang', 'gws' => 'Akhal-Téké', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'ATDS', 'ifce' => 'Akhal Teke De Demi Sang', 'gws' => 'Demi-sang Akhal-Téké', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'AUV', 'ifce' => 'Auvergne', 'gws' => 'Auvergne', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'AUX', 'ifce' => 'Trait Auxois', 'gws' => 'Auxois', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'AWB', 'ifce' => 'Australian Warmblood', 'gws' => 'Australian Warmblood', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'AWR', 'ifce' => 'American Warmblood', 'gws' => 'American Warmblood', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'AZT', 'ifce' => 'Cheval Aztèque', 'gws' => 'Aztèque', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'BA', 'ifce' => 'Barbe', 'gws' => 'Barbe', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'BARD', 'ifce' => 'Bardot', 'gws' => 'Bardot', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'BAWUE', 'ifce' => 'Würtemberger Warmblut', 'gws' => 'Württemberger', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'BDP', 'ifce' => 'Baudet Du Poitou', 'gws' => 'Baudet du Poitou', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'BOUL', 'ifce' => 'Trait Boulonnais', 'gws' => 'Boulonnais', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'BR', 'ifce' => 'Trait Breton', 'gws' => 'Breton', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'BRDB', 'ifce' => 'Brandenburger Warmblut', 'gws' => 'Brandenburger', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'BRP', 'ifce' => 'British Riding Pony', 'gws' => 'British Riding Pony', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'BW', 'ifce' => 'British Warmblood', 'gws' => 'British Warmblood', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'BWP', 'ifce' => 'Belgisch Warmbloedpaard', 'gws' => 'BWP', 'type' => 'race', 'alias' => array(), 'usage' => 'courant sport'),
    array('code' => 'BYWBL', 'ifce' => 'Bayerisches Warmblut', 'gws' => 'Bavarois', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'CAM', 'ifce' => 'Camargue', 'gws' => 'Camargue', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'CASP', 'ifce' => 'Caspian Horse', 'gws' => 'Caspian', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'CAST', 'ifce' => 'Castillonnais', 'gws' => 'Castillonnais', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'CB', 'ifce' => 'Cleveland Bay', 'gws' => 'Cleveland Bay', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'CC', 'ifce' => 'Cheval Corse', 'gws' => 'Cheval Corse', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'CD', 'ifce' => 'Clydesdale', 'gws' => 'Clydesdale', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'CDE', 'ifce' => 'Cheval de Sport Espagnol', 'gws' => 'Cheval de Sport Espagnol', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'CDF', 'ifce' => 'Cheval Dressage Français', 'gws' => 'Cheval de Dressage Français', 'type' => 'race', 'alias' => array(), 'usage' => 'courant dressage'),
    array('code' => 'CDM', 'ifce' => 'Cheval de Sport Mexicain', 'gws' => 'Cheval de Sport Mexicain', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'CH', 'ifce' => 'Cheval de Sport Suisse', 'gws' => 'Cheval de Sport Suisse', 'type' => 'race', 'alias' => array(), 'usage' => 'sport'),
    array('code' => 'CMF', 'ifce' => 'Cheval Miniature Français', 'gws' => 'Cheval Miniature Français', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'CO', 'ifce' => 'Connemara', 'gws' => 'Connemara', 'type' => 'race', 'alias' => array(), 'usage' => 'courant poney'),
    array('code' => 'COBND', 'ifce' => 'Cob Normand', 'gws' => 'Cob Normand', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'COMT', 'ifce' => 'Trait Comtois', 'gws' => 'Comtois', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'COPB', 'ifce' => 'Connemara Part Bred', 'gws' => 'Connemara Part-Bred', 'type' => 'race', 'alias' => array(), 'usage' => 'poney'),
    array('code' => 'CPD', 'ifce' => 'Cheval de Sport Portugais', 'gws' => 'Cheval de Sport Portugais', 'type' => 'race', 'alias' => array(), 'usage' => 'sport/dressage'),
    array('code' => 'CREME', 'ifce' => 'Cheval Crème', 'gws' => 'Crème', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'CRIO', 'ifce' => 'Criollo', 'gws' => 'Criollo', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'CSAN', 'ifce' => 'Cheval de Sport Anglo-Normand', 'gws' => 'Cheval de Sport Anglo-Normand', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'CSHA', 'ifce' => 'Canadian Sport Horse', 'gws' => 'Canadian Sport Horse', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'CURLY', 'ifce' => 'Curly', 'gws' => 'Curly', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'CVB', 'ifce' => 'Cheval du Vercors De Barraquand', 'gws' => 'Vercors de Barraquand', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'CW', 'ifce' => 'Canadian Warmblood', 'gws' => 'Canadian Warmblood', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'DA', 'ifce' => 'Dartmoor', 'gws' => 'Dartmoor', 'type' => 'race', 'alias' => array(), 'usage' => 'poney'),
    array('code' => 'DALES', 'ifce' => 'Dales Pony', 'gws' => 'Dales', 'type' => 'race', 'alias' => array(), 'usage' => 'poney'),
    array('code' => 'DP', 'ifce' => 'Deutsche Pferde', 'gws' => 'Deutsche Pferde', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'DRPON', 'ifce' => 'Deutsches Reitpony', 'gws' => 'Deutsches Reitpony', 'type' => 'race', 'alias' => array(), 'usage' => 'poney/dressage'),
    array('code' => 'DSA', 'ifce' => 'Demi-Sang Arabe', 'gws' => 'Demi-Sang Arabe', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'DSAA', 'ifce' => 'Demi Sang Anglo-Arabe', 'gws' => 'Demi-Sang Anglo-Arabe', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'DSSH', 'ifce' => 'Demi-Sang Shagya', 'gws' => 'Demi-Sang Shagya', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'DWB', 'ifce' => 'Danish Warmblood', 'gws' => 'Danish Warmblood', 'type' => 'race', 'alias' => array(), 'usage' => 'sport/dressage'),
    array('code' => 'EX', 'ifce' => 'Exmoor Pony', 'gws' => 'Exmoor', 'type' => 'race', 'alias' => array(), 'usage' => 'poney'),
    array('code' => 'FELL', 'ifce' => 'Fell Pony', 'gws' => 'Fell Pony', 'type' => 'race', 'alias' => array(), 'usage' => 'poney'),
    array('code' => 'FJ', 'ifce' => 'Fjord', 'gws' => 'Fjord', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'FRI', 'ifce' => 'Frison', 'gws' => 'Frison', 'type' => 'race', 'alias' => array(), 'usage' => 'dressage/loisir'),
    array('code' => 'FRMON', 'ifce' => 'Franches-Montagnes', 'gws' => 'Franches-Montagnes', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'FWB', 'ifce' => 'Finnish Warmblood', 'gws' => 'Finnish Warmblood', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'GC', 'ifce' => 'Gypsy Cob', 'gws' => 'Gypsy Cob', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'H', 'ifce' => 'Hessen Horse', 'gws' => 'Hessen', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'HACK', 'ifce' => 'Hackney', 'gws' => 'Hackney', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'HAF', 'ifce' => 'Haflinger', 'gws' => 'Haflinger', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'HAN', 'ifce' => 'Hanovrien', 'gws' => 'Hanovrien', 'type' => 'race', 'alias' => array(), 'usage' => 'courant sport/dressage'),
    array('code' => 'HEN', 'ifce' => 'Henson', 'gws' => 'Henson', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'HIG', 'ifce' => 'Highland', 'gws' => 'Highland', 'type' => 'race', 'alias' => array(), 'usage' => 'poney'),
    array('code' => 'HOLST', 'ifce' => 'Holsteiner Warmblut', 'gws' => 'Holsteiner', 'type' => 'race', 'alias' => array(), 'usage' => 'courant sport'),
    array('code' => 'HSH', 'ifce' => 'Hungarian Sport Horse', 'gws' => 'Hungarian Sport Horse', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'IC', 'ifce' => 'Irish Cob', 'gws' => 'Irish Cob', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'ICPB', 'ifce' => 'Irish Cob Part Bred', 'gws' => 'Irish Cob Part-Bred', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'IDH', 'ifce' => 'Trait Irlandais', 'gws' => 'Trait Irlandais', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'IPSA', 'ifce' => 'Irish Piebald-Skewbald', 'gws' => 'Irish Piebald-Skewbald', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'IS', 'ifce' => 'Islandais', 'gws' => 'Islandais', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'ISH', 'ifce' => 'Irish Sport Horse', 'gws' => 'Irish Sport Horse', 'type' => 'race', 'alias' => array(), 'usage' => 'courant sport'),
    array('code' => 'KNAB', 'ifce' => 'Knabstrupper', 'gws' => 'Knabstrupper', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'KWPN', 'ifce' => 'Koninklijke Vereniging Warmbloed Paardenstamboek Nederland', 'gws' => 'KWPN', 'type' => 'race', 'alias' => array(), 'usage' => 'courant sport/dressage'),
    array('code' => 'LAND', 'ifce' => 'Landais', 'gws' => 'Landais', 'type' => 'race', 'alias' => array(), 'usage' => 'poney'),
    array('code' => 'LIP', 'ifce' => 'Lipizzan', 'gws' => 'Lipizzan', 'type' => 'race', 'alias' => array(), 'usage' => 'dressage'),
    array('code' => 'LUS', 'ifce' => 'Pure Race Lusitanienne', 'gws' => 'Lusitanien', 'type' => 'race', 'alias' => array(), 'usage' => 'dressage'),
    array('code' => 'MALOP', 'ifce' => 'Malopolska', 'gws' => 'Malopolska', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'MECKL', 'ifce' => 'Mecklenburger', 'gws' => 'Mecklenburger', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'MER', 'ifce' => 'Mérens', 'gws' => 'Mérens', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'MINI', 'ifce' => 'Cheval Miniature Americain', 'gws' => 'Cheval Miniature Américain', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'MORGH', 'ifce' => 'Morgan Horse', 'gws' => 'Morgan', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'MUPOI', 'ifce' => 'Mule Poitevine', 'gws' => 'Mule Poitevine', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'MUPYR', 'ifce' => 'Mule Des Pyrénées', 'gws' => 'Mule des Pyrénées', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'NF', 'ifce' => 'New Forest', 'gws' => 'New Forest', 'type' => 'race', 'alias' => array(), 'usage' => 'courant poney'),
    array('code' => 'NFC', 'ifce' => 'New Forest De Croisement', 'gws' => 'New Forest de croisement', 'type' => 'race', 'alias' => array(), 'usage' => 'poney'),
    array('code' => 'NKT', 'ifce' => 'Nokota', 'gws' => 'Nokota', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'NRPS', 'ifce' => 'Nederlands Rijpaarden en Pony Stamboek', 'gws' => 'NRPS', 'type' => 'race', 'alias' => array(), 'usage' => 'poney/sport'),
    array('code' => 'NZSH', 'ifce' => 'New Zealand Sporthorse', 'gws' => 'New Zealand Sporthorse', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'OLD', 'ifce' => 'Oldenburg', 'gws' => 'Oldenburg', 'type' => 'race', 'alias' => array(), 'usage' => 'courant sport/dressage'),
    array('code' => 'PAINT', 'ifce' => 'Paint Horse', 'gws' => 'Paint Horse', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'PBD', 'ifce' => 'Poney Part Bred Dartmoor', 'gws' => 'Dartmoor Part-Bred', 'type' => 'race', 'alias' => array(), 'usage' => 'poney'),
    array('code' => 'PER', 'ifce' => 'Percheron', 'gws' => 'Percheron', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'PFS', 'ifce' => 'Poney Français De Selle', 'gws' => 'Poney Français de Selle', 'type' => 'race', 'alias' => array(), 'usage' => 'courant poney'),
    array('code' => 'PINTO', 'ifce' => 'Pinto', 'gws' => 'Pinto', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'POA', 'ifce' => 'Pony Of The Americas', 'gws' => 'Pony of the Americas', 'type' => 'race', 'alias' => array(), 'usage' => 'poney'),
    array('code' => 'POIT', 'ifce' => 'Trait Poitevin Mulassier', 'gws' => 'Poitevin Mulassier', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'POT', 'ifce' => 'Pottok', 'gws' => 'Pottok', 'type' => 'race', 'alias' => array(), 'usage' => 'poney'),
    array('code' => 'PRE', 'ifce' => 'Pure Races Espagnole', 'gws' => 'Pure Race Espagnole (PRE)', 'type' => 'race', 'alias' => array(), 'usage' => 'dressage'),
    array('code' => 'PRM', 'ifce' => 'Pure Race Minorquine', 'gws' => 'Minorquin', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'PS', 'ifce' => 'Pur-Sang', 'gws' => 'Pur-Sang', 'type' => 'race', 'alias' => array(), 'usage' => 'courant'),
    array('code' => 'QH', 'ifce' => 'Quarter Horse', 'gws' => 'Quarter Horse', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'QHX', 'ifce' => 'Quarter Horse Appendix', 'gws' => 'Quarter Horse Appendix', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'RHDL', 'ifce' => 'Rheinisches Warmblut', 'gws' => 'Rhénan', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'RM', 'ifce' => 'Rocky Mountain', 'gws' => 'Rocky Mountain Horse', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'SAHNA', 'ifce' => 'Sachsen-anhaltiner Warmblut', 'gws' => 'Sachsen-Anhaltiner', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'SAW', 'ifce' => 'South African Warmblood', 'gws' => 'South African Warmblood', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'SBS', 'ifce' => 'Cheval De Sport Belge', 'gws' => 'SBS', 'type' => 'race', 'alias' => array(), 'usage' => 'courant sport'),
    array('code' => 'SF', 'ifce' => 'Selle Français', 'gws' => 'Selle Français', 'type' => 'race', 'alias' => array('SFA'), 'usage' => 'très courant sport'),
    array('code' => 'SH', 'ifce' => 'Shire', 'gws' => 'Shire', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'SHA', 'ifce' => 'Shagya', 'gws' => 'Shagya', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'SHB', 'ifce' => 'British Sport Horse', 'gws' => 'British Sport Horse', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'SHE', 'ifce' => 'Shetland', 'gws' => 'Shetland', 'type' => 'race', 'alias' => array(), 'usage' => 'courant poney'),
    array('code' => 'SI', 'ifce' => 'Selle Italien', 'gws' => 'Selle Italien', 'type' => 'race', 'alias' => array(), 'usage' => 'sport'),
    array('code' => 'SL', 'ifce' => 'Selle Luxembourgeois', 'gws' => 'Selle Luxembourgeois', 'type' => 'race', 'alias' => array(), 'usage' => 'sport'),
    array('code' => 'SP', 'ifce' => 'British Spotted Pony', 'gws' => 'British Spotted Pony', 'type' => 'race', 'alias' => array(), 'usage' => 'poney'),
    array('code' => 'SSH', 'ifce' => 'Scottish Sport Horse', 'gws' => 'Scottish Sport Horse', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'SW', 'ifce' => 'Sächsisches Warmblut', 'gws' => 'Saxon Warmblood', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'SWB', 'ifce' => 'Swedish Warmblood', 'gws' => 'Swedish Warmblood', 'type' => 'race', 'alias' => array(), 'usage' => 'sport/dressage'),
    array('code' => 'TB', 'ifce' => 'Cheval De Trait Belge', 'gws' => 'Trait Belge', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'TDN', 'ifce' => 'Trait Du Nord', 'gws' => 'Trait du Nord', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'TE', 'ifce' => 'Trotteur Etranger', 'gws' => 'Trotteur Étranger', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'TF', 'ifce' => 'Trotteur Francais', 'gws' => 'Trotteur Français', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'TGC', 'ifce' => 'Traditional Gypsy Cob', 'gws' => 'Traditional Gypsy Cob', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'THU', 'ifce' => 'Thüringer Warmblut', 'gws' => 'Thüringer', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'TRAK', 'ifce' => 'Trakehner', 'gws' => 'Trakehner', 'type' => 'race', 'alias' => array(), 'usage' => 'sport/dressage'),
    array('code' => 'WA', 'ifce' => 'Welsh Mountain Pony', 'gws' => 'Welsh Mountain Pony', 'type' => 'race', 'alias' => array(), 'usage' => 'poney'),
    array('code' => 'WB', 'ifce' => 'Welsh Pony', 'gws' => 'Welsh Pony', 'type' => 'race', 'alias' => array(), 'usage' => 'poney'),
    array('code' => 'WD', 'ifce' => 'Welsh Cob', 'gws' => 'Welsh Cob', 'type' => 'race', 'alias' => array(), 'usage' => 'poney'),
    array('code' => 'WESTF', 'ifce' => 'Westfalen Riding Horse', 'gws' => 'Westphalien', 'type' => 'race', 'alias' => array(), 'usage' => 'courant sport/dressage'),
    array('code' => 'WPB', 'ifce' => 'Welsh Part-Bred', 'gws' => 'Welsh Part-Bred', 'type' => 'race', 'alias' => array(), 'usage' => 'poney'),
    array('code' => 'WTC', 'ifce' => 'Welsh Pony Type Cob', 'gws' => 'Welsh Pony Type Cob', 'type' => 'race', 'alias' => array(), 'usage' => 'poney'),
    array('code' => 'WX', 'ifce' => 'Welsh Section X', 'gws' => 'Welsh Section X', 'type' => 'race', 'alias' => array(), 'usage' => 'poney'),
    array('code' => 'Z', 'ifce' => 'Zangersheide', 'gws' => 'Zangersheide', 'type' => 'race', 'alias' => array(), 'usage' => 'très courant sport'),
    array('code' => 'ZW', 'ifce' => 'Zweibrücker', 'gws' => 'Zweibrücker', 'type' => 'race', 'alias' => array(), 'usage' => ''),
    array('code' => 'OC', 'ifce' => 'Origines Constatées', 'gws' => 'Origines Constatées (OC)', 'type' => 'appellation', 'alias' => array(), 'usage' => 'très courant'),
    array('code' => 'ONC', 'ifce' => 'Origines Non Constatées', 'gws' => 'Origines Non Constatées (ONC)', 'type' => 'appellation', 'alias' => array('ONCS', 'ONCP', 'ONCT', 'ONCA'), 'usage' => 'courant'),
    array('code' => 'OE', 'ifce' => 'Origine Étrangère', 'gws' => 'Origine Étrangère', 'type' => 'appellation', 'alias' => array('OES', 'OEP'), 'usage' => 'courant import'),
  );
}

/* -------------------------------------------------------------------------------------------
 * Accès aux entrées : lecture, résolution d'alias, recherche, libellé, type. Fonctions PURES,
 * jamais couplées à $_POST/nonce — le même point d'entrée sert l'autocomplétion admin, le parseur
 * IFCE, et tout futur import/API (§13 de la demande).
 * ----------------------------------------------------------------------------------------- */

/**
 * Liste complète, mémoïsée pour la durée de la requête (154 entrées, coût négligeable — aucune
 * base de données, aucun fichier externe : uniquement le tableau PHP généré ci-dessus).
 */
function gwseq_race_referentiel_entries() {
  static $entries = null;
  if ($entries === null) $entries = gwseq_race_referentiel_raw_entries();
  return $entries;
}

/**
 * Normalisation générique d'un texte pour comparaison (minuscules, sans accents, tirets/
 * underscores traités comme des espaces, espaces multiples réduits) — SEULE implémentation du
 * module pour ce besoin ; cheval-pedigree.php délègue désormais à cette fonction plutôt que de la
 * dupliquer (voir gwseq_normalize_race_text(), conservée par compatibilité de nom).
 */
function gwseq_race_referentiel_normalize_text($text) {
  $text = (string) $text;
  if (function_exists('remove_accents')) $text = remove_accents($text);
  $text = strtolower($text);
  $text = str_replace(array('_', '-'), ' ', $text);
  $text = trim(preg_replace('/\s+/', ' ', $text));
  return $text;
}

/**
 * Une entrée par son CODE CANONIQUE exact (insensible à la casse) — jamais par alias/libellé ici
 * (voir gwseq_race_referentiel_resolve_alias() pour cet usage). Retourne null si le code est
 * inconnu du référentiel — jamais une entrée fabriquée.
 */
function gwseq_race_referentiel_get($code) {
  $code = strtoupper(trim((string) $code));
  if ($code === '') return null;
  foreach (gwseq_race_referentiel_entries() as $entry) {
    if (strtoupper($entry['code']) === $code) return $entry;
  }
  return null;
}

/**
 * Sanitise une valeur brute de champ "race" ($_POST-shaped, ex. `_gwseq_race` ou
 * `..._externe[race]`) vers le CODE CANONIQUE exact du référentiel (toujours la forme stockée dans
 * `gwseq_race_referentiel_raw_entries()`, ex. "SF" — jamais "sf" ni une autre casse), le sentinel
 * "autre", ou une chaîne vide. SEULE implémentation de cette règle dans le module — utilisée à
 * l'identique par l'identité du cheval (cheval-fields.php) et les ascendants externes
 * (cheval-pedigree.php), pour ne jamais stocker deux casses différentes du même code selon le
 * chemin de saisie (formulaire manuel vs import IFCE, qui produit déjà la forme canonique via
 * gwseq_race_referentiel_resolve_alias()).
 */
function gwseq_sanitize_race_referentiel_code($raw) {
  $key = sanitize_key(wp_unslash($raw ?? ''));
  if ($key === 'autre') return 'autre';
  if ($key === '') return '';
  $entry = gwseq_race_referentiel_get($key);
  return $entry !== null ? $entry['code'] : '';
}

function gwseq_race_referentiel_type($code) {
  $entry = gwseq_race_referentiel_get($code);
  return $entry !== null ? $entry['type'] : '';
}

/**
 * Libellé d'affichage GWS d'un code déjà connu — chaîne vide si le code est inconnu (jamais une
 * valeur devinée) ; le repli "Autre" + texte libre reste géré par l'appelant (gwseq_cheval_race_label()
 * dans cheval-fields.php), ce fichier ne connaît rien de ce cas particulier.
 */
function gwseq_race_referentiel_display_label($code) {
  $entry = gwseq_race_referentiel_get($code);
  return $entry !== null ? $entry['gws'] : '';
}

/**
 * Résout un texte quelconque (code IFCE, libellé IFCE, libellé GWS, ou alias historique/import —
 * ex. "SFA") vers son code canonique GWS — comparaison exacte après normalisation, jamais une
 * correspondance partielle (une correspondance partielle relèverait de la recherche/autocomplétion,
 * gwseq_race_referentiel_search() ci-dessous, jamais d'une résolution automatique qui risquerait de
 * mal deviner). Retourne '' si rien ne correspond — c'est alors, et alors seulement, que l'appelant
 * doit se replier sur "Autre" + texte d'origine (§7 de la demande : "Autre" reste un filet de
 * sécurité, jamais un repli sur un code connu mal interprété).
 *
 * REMPLACE l'ancien mécanisme (référentiel de ~19 races codées en dur, sans alias) : un code IFCE
 * connu — y compris un alias historique comme "SFA" -> "SF" — n'est désormais plus JAMAIS rangé
 * dans "Autre" (§2/§14 de la demande).
 */
function gwseq_race_referentiel_resolve_alias($text) {
  $normalized = gwseq_race_referentiel_normalize_text($text);
  if ($normalized === '') return '';
  foreach (gwseq_race_referentiel_entries() as $entry) {
    if (gwseq_race_referentiel_normalize_text($entry['code']) === $normalized) return $entry['code'];
    if (gwseq_race_referentiel_normalize_text($entry['ifce']) === $normalized) return $entry['code'];
    if (gwseq_race_referentiel_normalize_text($entry['gws']) === $normalized) return $entry['code'];
    foreach ($entry['alias'] as $alias) {
      if (gwseq_race_referentiel_normalize_text($alias) === $normalized) return $entry['code'];
    }
  }
  return '';
}

/**
 * Recherche par texte PARTIEL (autocomplétion, §4 de la demande) : sur le code, le libellé IFCE,
 * le libellé GWS, ET les alias — accents/casse ignorés. Classe en premier les entrées dont un champ
 * COMMENCE par la requête (ex. "sf" -> code "SF" en tête), puis les correspondances partielles
 * ailleurs dans un champ (ex. "arab" -> "Anglo-Arabe" au milieu du libellé) — un simple confort de
 * tri, jamais une pertinence approximative/floue. $limit borne le nombre de résultats retournés,
 * jamais le nombre d'entrées consultées (une correspondance en fin de référentiel reste trouvée).
 */
function gwseq_race_referentiel_search($query, $limit = 20) {
  $normalized_query = gwseq_race_referentiel_normalize_text($query);
  if ($normalized_query === '') return array();

  $prefix_matches = array();
  $substring_matches = array();
  foreach (gwseq_race_referentiel_entries() as $entry) {
    $fields = array_merge(array($entry['code'], $entry['ifce'], $entry['gws']), $entry['alias']);
    $is_prefix = false;
    $is_substring = false;
    foreach ($fields as $field) {
      $normalized_field = gwseq_race_referentiel_normalize_text($field);
      if ($normalized_field === '') continue;
      $position = strpos($normalized_field, $normalized_query);
      if ($position === 0) $is_prefix = true;
      elseif ($position !== false) $is_substring = true;
    }
    if ($is_prefix) $prefix_matches[] = $entry;
    elseif ($is_substring) $substring_matches[] = $entry;
  }

  return array_slice(array_merge($prefix_matches, $substring_matches), 0, max(0, (int) $limit));
}

/**
 * Repli NEUTRE (jamais un "profil métier" CSO/dressage/poney, §5 de la demande : "ne pas créer de
 * profil métier rigide") pour un utilisateur SANS AUCUN historique de saisie — seulement utilisé
 * tant que gwseq_race_referentiel_recent_codes() est vide. Reprend tel quel le champ "usage" du
 * référentiel source (fourni par la demande elle-même), "très courant" passant avant "courant" —
 * aucun jugement de valeur ajouté ici, uniquement l'information déjà présente dans la donnée.
 */
function gwseq_race_referentiel_default_suggestions($limit = 10) {
  $flagged = array();
  foreach (gwseq_race_referentiel_entries() as $entry) {
    if ($entry['usage'] !== '') $flagged[] = $entry;
  }
  usort($flagged, function ($a, $b) {
    $a_rank = (strpos($a['usage'], 'très courant') === 0) ? 0 : 1;
    $b_rank = (strpos($b['usage'], 'très courant') === 0) ? 0 : 1;
    return $a_rank <=> $b_rank;
  });
  return array_slice($flagged, 0, max(0, (int) $limit));
}

/* -------------------------------------------------------------------------------------------
 * Récents par utilisateur (§5/§6 de la demande) : PRÉFÉRENCE PROPRE À L'UTILISATEUR (user meta),
 * ne modifie JAMAIS la donnée Cheval elle-même. Enregistrée uniquement depuis la glue
 * d'enregistrement des formulaires (gwseq_save_cheval_meta()/gwseq_save_cheval_pedigree_meta()),
 * jamais depuis une fonction métier pure (gwseq_set_cheval_identity()/gwseq_set_horse_parent()) :
 * un import IFCE ou un futur import CSV/API écrit un code sans que ce soit un choix manuel de
 * l'utilisateur courant dans l'interface — cela ne doit jamais compter comme un "récent".
 */
const GWSEQ_RACE_REFERENTIEL_RECENT_MAX = 15;

function gwseq_race_referentiel_recent_codes($user_id, $limit = 10) {
  $codes = get_user_meta((int) $user_id, '_gwseq_race_recent_codes', true);
  $codes = is_array($codes) ? $codes : array();
  return array_slice($codes, 0, max(0, (int) $limit));
}

/**
 * Enregistre UN code comme "récemment utilisé" par CET utilisateur — idempotent (une occurrence
 * déjà présente est déplacée en tête, jamais dupliquée), plafonné, et REFUSE silencieusement tout
 * code qui n'existe pas dans le référentiel (jamais "Autre", jamais une faute de frappe mémorisée
 * comme si c'était une vraie valeur).
 */
function gwseq_race_referentiel_record_recent_code($user_id, $code) {
  $user_id = (int) $user_id;
  $code = strtoupper(trim((string) $code));
  if (!$user_id || $code === '' || gwseq_race_referentiel_get($code) === null) return;

  $codes = get_user_meta($user_id, '_gwseq_race_recent_codes', true);
  $codes = is_array($codes) ? $codes : array();
  $codes = array_values(array_diff($codes, array($code)));
  array_unshift($codes, $code);
  $codes = array_slice($codes, 0, GWSEQ_RACE_REFERENTIEL_RECENT_MAX);
  update_user_meta($user_id, '_gwseq_race_recent_codes', $codes);
}

/**
 * Suggestions à afficher à l'ouverture d'un champ VIDE (§5) : les récents de l'utilisateur s'ils
 * existent, sinon le repli neutre. Ne mélange jamais les deux — un utilisateur avec ne serait-ce
 * qu'UN récent voit déjà ses propres usages, jamais complétés par des valeurs génériques qui n'ont
 * rien à voir avec son élevage.
 */
function gwseq_race_referentiel_suggestions_for_user($user_id, $limit = 10) {
  $recent_codes = gwseq_race_referentiel_recent_codes($user_id, $limit);
  if (!empty($recent_codes)) {
    $entries = array();
    foreach ($recent_codes as $code) {
      $entry = gwseq_race_referentiel_get($code);
      if ($entry !== null) $entries[] = $entry;
    }
    if (!empty($entries)) return $entries;
  }
  return gwseq_race_referentiel_default_suggestions($limit);
}

/**
 * Parcourt récursivement un arbre d'ascendant externe déjà SANITISÉ ({name, race, race_autre,
 * annee_naissance, father, mother}) et enregistre chaque code de race valide rencontré comme
 * "récent" pour cet utilisateur — un seul point d'appel (gwseq_save_cheval_pedigree_meta()) couvre
 * ainsi tous les ascendants saisis manuellement en une fois, à n'importe quelle génération.
 */
function gwseq_race_referentiel_record_recent_codes_from_external_tree($node, $user_id) {
  if (!is_array($node)) return;
  if (!empty($node['race']) && $node['race'] !== 'autre') {
    gwseq_race_referentiel_record_recent_code($user_id, $node['race']);
  }
  if (!empty($node['father'])) gwseq_race_referentiel_record_recent_codes_from_external_tree($node['father'], $user_id);
  if (!empty($node['mother'])) gwseq_race_referentiel_record_recent_codes_from_external_tree($node['mother'], $user_id);
}

/* -------------------------------------------------------------------------------------------
 * Composant de saisie partagé (§8 de la demande : "le même composant partout") — un champ de
 * recherche/autocomplétion, jamais un <select>. Utilisé identiquement par l'identité du cheval
 * (cheval-fields.php) et par chaque génération d'ascendant externe (cheval-pedigree.php). Rendu
 * PHP minimal (texte affiché + code cru en hidden + repli "Autre") ; toute l'interactivité vit dans
 * assets/race-referentiel-autocomplete.js, qui cible cette structure par attributs data-*
 * génériques, jamais par un identifiant codé en dur — un même script sert un nombre arbitraire de
 * champs sur la même page (identité + N ascendants).
 */
function gwseq_render_race_referentiel_field($args) {
  $args = wp_parse_args($args, array(
    'field_name' => '',
    'autre_field_name' => '',
    'input_id' => '',
    'current_code' => '',
    'current_autre' => '',
  ));

  $current_code = (string) $args['current_code'];
  $display_value = '';
  if ($current_code === 'autre') {
    $display_value = (string) $args['current_autre'];
  } elseif ($current_code !== '') {
    $display_value = gwseq_race_referentiel_display_label($current_code);
  }
  ?>
  <span class="gwseq-race-field" data-gwseq-race-field>
    <input
      type="text"
      class="regular-text gwseq-race-field__search"
      id="<?php echo esc_attr($args['input_id']); ?>"
      value="<?php echo esc_attr($display_value); ?>"
      autocomplete="off"
      role="combobox"
      aria-expanded="false"
      aria-autocomplete="list"
      placeholder="<?php esc_attr_e('Rechercher une race, un stud-book ou une appellation…', 'gws-core'); ?>"
    >
    <input type="hidden" class="gwseq-race-field__code" name="<?php echo esc_attr($args['field_name']); ?>" value="<?php echo esc_attr($current_code); ?>">
    <ul class="gwseq-race-field__results" role="listbox" hidden></ul>
    <span class="gwseq-race-field__autre-wrap" style="<?php echo $current_code === 'autre' ? '' : 'display:none;'; ?>">
      <label><?php esc_html_e('Préciser', 'gws-core'); ?></label>
      <input type="text" class="regular-text gwseq-race-field__autre" name="<?php echo esc_attr($args['autre_field_name']); ?>" value="<?php echo esc_attr($args['current_autre']); ?>">
    </span>
  </span>
  <?php
}

/**
 * Charge le référentiel + les suggestions de l'utilisateur courant vers le JavaScript, UNE SEULE
 * FOIS par écran (peu importe le nombre de champs "race" présents dessus) — jamais un second jeu
 * de données divergent. Enregistrement/enqueue scopés à l'écran d'édition Cheval uniquement (voir
 * l'appel dans cheval-fields.php et cheval-pedigree.php).
 */
function gwseq_enqueue_race_referentiel_assets() {
  if (wp_script_is('gwseq-race-referentiel-autocomplete', 'enqueued')) return; // déjà chargé sur cet écran
  wp_enqueue_style('gwseq-race-referentiel-autocomplete', GWSEQ_MODULE_URL . 'assets/race-referentiel-autocomplete.css', array(), GWSEQ_MODULE_VERSION);
  wp_enqueue_script('gwseq-race-referentiel-autocomplete', GWSEQ_MODULE_URL . 'assets/race-referentiel-autocomplete.js', array(), GWSEQ_MODULE_VERSION, true);

  $entries_for_js = array();
  foreach (gwseq_race_referentiel_entries() as $entry) {
    $entries_for_js[] = array(
      'code' => $entry['code'],
      'label' => $entry['gws'],
      'ifce' => $entry['ifce'],
      'type' => $entry['type'],
      'alias' => $entry['alias'],
    );
  }
  $suggestions_for_js = array();
  foreach (gwseq_race_referentiel_suggestions_for_user(get_current_user_id()) as $entry) {
    $suggestions_for_js[] = array('code' => $entry['code'], 'label' => $entry['gws']);
  }

  wp_localize_script('gwseq-race-referentiel-autocomplete', 'gwseqRaceReferentiel', array(
    'entries' => $entries_for_js,
    'suggestions' => $suggestions_for_js,
    'autreCode' => 'autre',
    'i18n' => array(
      'autre' => __('Autre — préciser', 'gws-core'),
      'noResults' => __('Aucun résultat', 'gws-core'),
    ),
  ));
}

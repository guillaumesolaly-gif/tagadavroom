<?php
// Enrichissement du graphe Schema.org généré par Yoast SEO — n'agit que si Yoast (ou un
// plugin compatible exposant les mêmes filtres) est actif. N'ajoute jamais de graphe
// parallèle : complète les pièces existantes de Yoast via ses filtres officiels
// (wpseo_schema_organization, wpseo_schema_graph_pieces), conformément au diagnostic Schema.org
// du 20/08/2026 (cabinet représenté comme Organization générique, sans entité Person, sans
// founder/worksFor). Ne touche jamais WebSite, WebPage, BreadcrumbList, ni aucune sortie
// meta/canonical de Yoast.
//
// Tout le corps de ce fichier est enveloppé dans la condition ci-dessous plutôt que dans un
// retour anticipé : en PHP, une fonction déclarée au niveau racine d'un fichier est liée à la
// compilation, avant même l'exécution d'un éventuel `return` qui la précède — seule une
// déclaration réellement imbriquée dans un bloc conditionnel est différée à l'exécution et ne
// se lie que si la condition est vraie. Vérifié : la classe SPA_Person_Schema_Piece (qui étend
// une classe de Yoast) n'aurait de toute façon jamais pu se lier sans Yoast actif, mais les deux
// fonctions ci-dessous se seraient sinon retrouvées définies même Yoast désactivé — sans risque
// direct puisque jamais appelées hors des add_filter() eux-mêmes conditionnés, mais ce n'est pas
// la sémantique voulue par le commentaire ci-dessus. Corrigé avec ce bloc conditionnel explicite.

if (defined('WPSEO_VERSION')) {

  // --- Étape 1 : enrichir le nœud Organization existant (double typage LegalService) ---
  function spa_enrich_yoast_organization($data, $context) {
    $data['@type'] = array('Organization', 'LegalService');
    $data['telephone'] = spa_cabinet_phone_href();
    $data['email'] = spa_get_cabinet_setting('public_email');
    $data['address'] = array(
      '@type' => 'PostalAddress',
      'streetAddress' => spa_get_cabinet_setting('address_line'),
      'postalCode' => spa_get_cabinet_setting('postal_code'),
      'addressLocality' => spa_get_cabinet_setting('city'),
      'addressRegion' => 'Auvergne-Rhône-Alpes',
      'addressCountry' => 'FR',
    );
    $data['areaServed'] = array(
      array('@type' => 'City', 'name' => spa_get_cabinet_setting('city')),
      array('@type' => 'AdministrativeArea', 'name' => 'Loire'),
    );
    $data['founder'] = array('@id' => $context->site_url . '#juliette-saint-pere');

    $gbp_url = spa_get_cabinet_setting('google_business_url');
    if ($gbp_url) {
      $data['sameAs'] = array($gbp_url);
    }

    return $data;
  }
  add_filter('wpseo_schema_organization', 'spa_enrich_yoast_organization', 11, 2);

  // --- Étape 2 : ajouter l'entité Person pour Juliette Saint-Père et l'entité Service du
  // cabinet (serviceType a été retiré d'Organization/LegalService le 20/08/2026 : son domaine
  // Schema.org officiel est exclusivement Service, jamais Organization ni LegalService — voir
  // CHANGELOG 2.4.8) ---
  add_filter('wpseo_schema_graph_pieces', 'spa_add_person_and_service_schema_pieces', 11, 2);
  function spa_add_person_and_service_schema_pieces($pieces, $context) {
    $pieces[] = new SPA_Person_Schema_Piece();
    $pieces[] = new SPA_Service_Schema_Piece();
    return $pieces;
  }

  class SPA_Person_Schema_Piece extends \Yoast\WP\SEO\Generators\Schema\Abstract_Schema_Piece {

    // Identifiant explicite et distinct de 'person', réservé en interne par la pièce
    // Schema\Person de Yoast (inactive tant que le site est représenté comme une
    // "organisation" plutôt qu'une "personne") — évite toute collision de clé si ce
    // réglage change un jour.
    public $identifier = 'spa_person';

    public function is_needed() {
      return true;
    }

    public function generate() {
      return array(
        '@type' => 'Person',
        '@id' => $this->context->site_url . '#juliette-saint-pere',
        'name' => 'Juliette Saint-Père',
        'url' => home_url('/'),
        'jobTitle' => 'Avocate au Barreau de Saint-Étienne',
        'image' => get_template_directory_uri() . '/assets/portrait-saint-pere-tenue-pro-v1.webp',
        'worksFor' => array('@id' => $this->context->site_url . '#organization'),
        'sameAs' => array_values(array_filter(array(
          spa_get_cabinet_setting('linkedin_url'),
          spa_get_cabinet_setting('avocat_url'),
        ))),
        'alumniOf' => array(
          array('@type' => 'EducationalOrganization', 'name' => 'Université Jean Moulin Lyon III'),
          array('@type' => 'EducationalOrganization', 'name' => 'National University of Ireland Maynooth'),
          array('@type' => 'EducationalOrganization', 'name' => 'HEC Paris'),
        ),
      );
    }
  }

  // --- Étape 3 : entité Service unique portant serviceType (domaine Schema.org officiel de
  // serviceType : Service exclusivement — jamais Organization ni LegalService) ---
  class SPA_Service_Schema_Piece extends \Yoast\WP\SEO\Generators\Schema\Abstract_Schema_Piece {

    public $identifier = 'spa_service';

    public function is_needed() {
      return true;
    }

    public function generate() {
      return array(
        '@type' => 'Service',
        '@id' => $this->context->site_url . '#service-juridique',
        'name' => 'Services juridiques du cabinet Saint-Père Avocat',
        'serviceType' => array(
          'Prévention des difficultés (mandat ad hoc, conciliation)',
          'Sauvegarde et redressement judiciaire',
          'Liquidation judiciaire',
          'Contentieux commercial',
          'Reprise d’entreprise en difficulté',
          'Postulation devant les juridictions',
        ),
        'provider' => array('@id' => $this->context->site_url . '#organization'),
      );
    }
  }

}

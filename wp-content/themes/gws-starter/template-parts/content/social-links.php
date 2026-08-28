<?php
/**
 * Liste de pictogrammes pour les réseaux sociaux structurés (gws_core_social_links() : LinkedIn,
 * Facebook, Instagram, YouTube, TikTok, X) — composant générique, prêt à l'emploi, à appeler
 * depuis n'importe quel gabarit :
 *
 *   get_template_part('template-parts/content/social-links');
 *
 * Ne produit strictement rien (aucun conteneur) si aucun réseau structuré n'est renseigné.
 * N'inclut jamais WhatsApp (canal de contact) ni la fiche Google Business Profile (présence
 * locale) : ce ne sont pas des réseaux sociaux au même sens, à afficher séparément si besoin
 * via gws_core_whatsapp_url() / gws_core_google_business_url().
 *
 * Liens externes : ouverture dans un nouvel onglet avec les attributs de sécurité appropriés
 * (rel="noopener noreferrer"). Chaque lien a un nom accessible (aria-label avec le nom du
 * réseau) ; l'icône elle-même reste décorative (aria-hidden, portée par gws_social_icon()).
 * Les pictogrammes utilisent currentColor : ils héritent la couleur de leur contexte d'appel,
 * sans imposer de couleur de marque.
 */

if (!function_exists('gws_core_social_links')) return;

$gws_social_links = gws_core_social_links();
if (!$gws_social_links) return;

$gws_social_labels = gws_social_network_labels();
?>
<nav class="social-links" aria-label="Réseaux sociaux">
  <ul>
    <?php foreach ($gws_social_links as $gws_network => $gws_url) :
      $gws_label = $gws_social_labels[$gws_network] ?? ucfirst($gws_network);
      ?>
      <li>
        <a href="<?php echo esc_url($gws_url); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr($gws_label); ?>">
          <?php echo gws_social_icon($gws_network); ?>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>
</nav>

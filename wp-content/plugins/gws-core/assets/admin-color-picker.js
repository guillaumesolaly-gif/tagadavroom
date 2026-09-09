/**
 * Sélecteur de couleur (Réglages > Ma structure) — usage unique à cet écran, s'appuie sur le
 * composant natif WordPress wp-color-picker (Iris), aucune dépendance nouvelle. N'enregistre rien
 * lui-même : transforme le champ texte en sélecteur, la sauvegarde reste gérée par le formulaire
 * natif. Laisse volontairement le champ VIDE quand aucune couleur n'est enregistrée (voir
 * includes/admin/settings-page.php) : ce script ne préremplit jamais une couleur par défaut dans
 * le champ, pour ne jamais laisser croire qu'elle a été choisie par la structure.
 */
(($) => {
  if (!$ || !$.fn || !$.fn.wpColorPicker) return;
  $(() => {
    $('.gws-color-field').wpColorPicker();
  });
})(window.jQuery);

jQuery(document).ready(function($) {
    // Initialisation du sélecteur de couleur
    $('.csp-color-field').wpColorPicker();
    
    // Confirmation avant réinitialisation
    $('.csp-reset-settings').on('click', function(e) {
        if (!confirm('Êtes-vous sûr de vouloir réinitialiser tous les réglages ? Cette action est irréversible.')) {
            e.preventDefault();
        }
    });
    
    // Aide contextuelle
    $('.csp-help-trigger').on('click', function() {
        $(this).next('.csp-help-text').slideToggle();
    });
});
<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * CSP_Popup_Render Class
 *
 * Cette classe est responsable de l'affichage du template du popup
 * dans le plugin Custom Size Plugin. Elle utilise le pattern Singleton
 * pour garantir une seule instance de la classe.
 *
 * Gère le rendu du popup de mensurations sur les pages produits WooCommerce.
 * Elle inclut dynamiquement le fichier `templates/popup-form.php` si celui-ci existe.
 *
 * Exemple d'utilisation :
 * ```php
 * CSP_Popup_Render::render_popup_template();
 * ```
 *
 * 
 * @package    Custom_Size_Plugin
 * @subpackage Includes
 * @since      1.0.0
 */


class CSP_Popup_Render {

    private static $instance = null;

    private function __construct() {
        
    }

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function render_popup_template() {
        $template = CSP_PLUGIN_DIR . 'templates/popup-form.php';
        if ( file_exists( $template ) ) {
            include $template;
        }
    }
}


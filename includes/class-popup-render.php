<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * CSP_Popup_Render
 * - Responsible to render the popup template when needed
 */
class CSP_Popup_Render {

    private static $instance = null;

    private function __construct() {
        // no init logic required
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

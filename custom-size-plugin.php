<?php
/**
 * Plugin Name: Custom Size Plugin
 * Description: Gestion des mensurations clients — popup multi-step, sauvegarde, ajout automatique au panier, profils réutilisables et page Mon Compte.
 * Version:     1.2.0
 * Author:      Aristide Ghislain
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* déclaration de constantes relatives au plugin */
define( 'CSP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CSP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CSP_DB_VERSION', '1.0' );

/* inclusion des fichiers de classes */
require_once CSP_PLUGIN_DIR . 'includes/class-save-data.php';
require_once CSP_PLUGIN_DIR . 'includes/class-popup-render.php';
require_once CSP_PLUGIN_DIR . 'includes/class-admin-settings.php';
require_once CSP_PLUGIN_DIR . 'includes/class-widget.php';

/* Initialisation des classes */
function csp_init_plugin() {
    CSP_Save_Data::get_instance();
    CSP_Popup_Render::get_instance();
    CSP_Admin_Settings::get_instance();
}
add_action( 'plugins_loaded', 'csp_init_plugin' );

/* enregistrement du widget du plugin */
function csp_register_widget() {
    register_widget( 'CSP_Widget' );
}
add_action( 'widgets_init', 'csp_register_widget' );

/* activation et désactivation du plugin */
function csp_activate() {
    CSP_Save_Data::create_table();
    // flush endpoints in case rewrite endpoints added
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'csp_activate' );

function csp_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'csp_deactivate' );

/* Ajouter menu dans l’administration */
function csp_register_admin_menu() {
    // Menu principal
    add_menu_page(
        __( 'Custom Size', 'custom-size-plugin' ), // Titre menu
        __( 'Custom Size', 'custom-size-plugin' ), // Libellé menu
        'manage_options',                          // Capacité requise
        'csp-dashboard',                           // Slug
        'csp_render_admin_dashboard',              // Fonction de rendu
        'dashicons-ruler',                         // Icône
        25                                         // Position
    );

    // Sous-menu : Tableau de bord
    add_submenu_page(
        'csp-dashboard',
        __( 'Tableau de bord', 'custom-size-plugin' ),
        __( 'Tableau de bord', 'custom-size-plugin' ),
        'manage_options',
        'csp-dashboard',
        'csp_render_admin_dashboard'
    );

    // Sous-menu : Profils clients (vue BDD)
    add_submenu_page(
        'csp-dashboard',
        __( 'Profils clients', 'custom-size-plugin' ),
        __( 'Profils clients', 'custom-size-plugin' ),
        'manage_options',
        'csp-profiles',
        'csp_render_admin_profiles'
    );

    // Sous-menu : Réglages
    add_submenu_page(
        'csp-dashboard',
        __( 'Réglages', 'custom-size-plugin' ),
        __( 'Réglages', 'custom-size-plugin' ),
        'manage_options',
        'csp-settings',
        'csp_render_admin_settings'
    );
}
add_action( 'admin_menu', 'csp_register_admin_menu' );


/* Enqueue frontend assets */
function csp_enqueue_assets() {
    // always enqueue minimal CSS/JS since popup can be inserted by shortcode/widget too
    wp_enqueue_style( 'csp-popup-css', CSP_PLUGIN_URL . 'assets/css/popup.css', array(), '1.0' );
    wp_enqueue_script( 'csp-popup-js', CSP_PLUGIN_URL . 'assets/js/popup.js', array(), '1.0', true );

    wp_localize_script( 'csp-popup-js', 'csp_ajax_obj', array(
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'csp_save_measurements' ),
    ) );
}
add_action( 'wp_enqueue_scripts', 'csp_enqueue_assets' );

/* Inject button on product page */
function csp_inject_button() {
    // Display button and popup (only on product or when shortcode/widget used)
    echo '<div class="csp-add-measurements-wrap">';
    echo '<div class="csp-add-measurements-wrap">';
    echo '<h5>SUR-MESURE: VOS MENSURATIONS, NOTRE PRECISION</h5>';
    echo '<span>Indiquez vos mensurations et nous ajusterons ce vêtement avec précision pour un fit parfait</span>';
    echo '</div>';
    echo '<button type="button" class="csp-open-modal button">Ajouter vos mensurations</button>';
    echo '</div>';
    CSP_Popup_Render::render_popup_template();
}
add_action( 'woocommerce_after_add_to_cart_form', 'csp_inject_button' );

/* Shortcode for button */
function csp_shortcode_button( $atts ) {
    ob_start();
    echo '<div class="csp-add-measurements-wrap">';
    echo '<button type="button" class="csp-open-modal button">Ajouter vos mensurations</button>';
    echo '</div>';
    CSP_Popup_Render::render_popup_template();
    return ob_get_clean();
}
add_shortcode( 'csp_add_measurements', 'csp_shortcode_button' );

/* Prevent merging cart items with different measurement data */
add_filter( 'woocommerce_add_cart_item_data', function( $cart_item_data, $product_id ) {
    if ( isset( $cart_item_data['unique_key'] ) ) {
        // ensure unique key always exists and is unique enough
        $cart_item_data['unique_key'] = md5( (string) $cart_item_data['unique_key'] . microtime() . rand() );
    }
    return $cart_item_data;
}, 10, 2 );

add_filter( 'woocommerce_get_cart_item_from_session', function( $cart_item, $values ) {
    if ( isset( $values['csp_measurement_id'] ) ) {
        $cart_item['csp_measurement_id'] = $values['csp_measurement_id'];
    }
    if ( isset( $values['unique_key'] ) ) {
        $cart_item['unique_key'] = $values['unique_key'];
    }
    return $cart_item;
}, 10, 2 );

/* Show measurement data in cart (under product name) */
add_filter( 'woocommerce_get_item_data', function( $item_data, $cart_item ) {
    if ( isset( $cart_item['csp_measurement_id'] ) ) {
        global $wpdb;
        $table = $wpdb->prefix . 'csp_measurements';
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT raw_data FROM {$table} WHERE id = %d", intval( $cart_item['csp_measurement_id'] ) ), ARRAY_A );

        if ( $row && ! empty( $row['raw_data'] ) ) {
            $data = maybe_unserialize( $row['raw_data'] );
            if ( is_array( $data ) ) {
                foreach ( $data as $k => $v ) {
                    $item_data[] = array(
                        'name'  => ucfirst( str_replace( array('_','-'), ' ', $k ) ),
                        'value' => esc_html( $v ),
                    );
                }
            }
        }
    }
    return $item_data;
}, 10, 2 );

/* Save measurement id to order line item during checkout */
add_action( 'woocommerce_checkout_create_order_line_item', function( $item, $cart_item_key, $values, $order ) {
    if ( isset( $values['csp_measurement_id'] ) ) {
        $item->add_meta_data( '_csp_measurement_id', $values['csp_measurement_id'], true );
    }
}, 10, 4 );

/* Add "Mes mensurations" endpoint to My Account */
add_filter( 'woocommerce_account_menu_items', function( $items ) {
    if ( isset( $items['customer-logout'] ) ) {
        $logout = $items['customer-logout'];
        unset( $items['customer-logout'] );
    } else {
        $logout = null;
    }
    $items['csp-measurements'] = __( 'Mes mensurations', 'custom-size-plugin' );
    if ( $logout ) {
        $items['customer-logout'] = $logout;
    }
    return $items;
}, 20 );

add_action( 'init', function() {
    add_rewrite_endpoint( 'csp-measurements', EP_ROOT | EP_PAGES );
});

/* Load template for account endpoint */
add_action( 'woocommerce_account_csp-measurements_endpoint', function() {
    if ( ! is_user_logged_in() ) {
        echo '<p>' . esc_html__( 'Vous devez être connecté pour gérer vos mensurations.', 'custom-size-plugin' ) . '</p>';
        return;
    }
    include CSP_PLUGIN_DIR . 'templates/account-measurements.php';
});

/* Handle add/delete profile requests from account page */
add_action( 'template_redirect', function() {
    if ( ! is_user_logged_in() ) {
        return;
    }

    // Add profile
    if ( isset( $_POST['csp_add_profile'] ) && isset( $_POST['csp_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['csp_nonce'] ) ), 'csp_add_profile' ) ) {
        global $wpdb;
        $table = $wpdb->prefix . 'csp_measurements';

        $data = array(
            'taille'        => sanitize_text_field( wp_unslash( $_POST['taille'] ?? '' ) ),
            'poids'         => sanitize_text_field( wp_unslash( $_POST['poids'] ?? '' ) ),
            'poitrine'      => sanitize_text_field( wp_unslash( $_POST['poitrine'] ?? '' ) ),
            'taille_manche' => sanitize_text_field( wp_unslash( $_POST['taille_manche'] ?? '' ) ),
            'taille_hauteur'=> sanitize_text_field( wp_unslash( $_POST['taille_hauteur'] ?? '' ) ),
        );

        $wpdb->insert(
            $table,
            array(
                'user_id'  => get_current_user_id(),
                'raw_data' => maybe_serialize( $data ),
            ),
            array( '%d', '%s' )
        );

        wc_add_notice( __( 'Profil ajouté avec succès.', 'custom-size-plugin' ), 'success' );
        wp_safe_redirect( wc_get_account_endpoint_url( 'csp-measurements' ) );
        exit;
    }

    // Delete profile
    if ( isset( $_GET['action'] ) && 'csp_delete' === $_GET['action'] && isset( $_GET['id'] ) ) {
        $id = intval( $_GET['id'] );
        if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( wp_unslash( $_GET['_wpnonce'] ), 'csp_delete_' . $id ) ) {
            global $wpdb;
            $table = $wpdb->prefix . 'csp_measurements';
            $wpdb->delete( $table, array( 'id' => $id, 'user_id' => get_current_user_id() ), array( '%d', '%d' ) );
            wc_add_notice( __( 'Profil supprimé.', 'custom-size-plugin' ), 'success' );
        } else {
            wc_add_notice( __( 'Échec de la suppression (sécurité).', 'custom-size-plugin' ), 'error' );
        }
        wp_safe_redirect( wc_get_account_endpoint_url( 'csp-measurements' ) );
        exit;
    }
});


function csp_render_admin_dashboard() {
    echo '<div class="wrap"><h1>Custom Size – Tableau de bord</h1>';
    echo '<p>Bienvenue dans le plugin de gestion des mensurations.</p>';
    echo '</div>';
}

function csp_render_admin_profiles() {
    global $wpdb;
    $table = $wpdb->prefix . 'csp_measurements';
    $rows  = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC LIMIT 20", ARRAY_A);

    echo '<div class="wrap"><h1>Profils clients</h1>';
    if ( $rows ) {
        echo '<table class="widefat striped"><thead><tr><th>ID</th><th>User</th><th>Données</th><th>Date</th></tr></thead><tbody>';
        foreach ( $rows as $row ) {
            $data = maybe_unserialize( $row['raw_data'] );
            echo '<tr>';
            echo '<td>'.esc_html($row['id']).'</td>';
            echo '<td>'.esc_html($row['user_id']).'</td>';
            echo '<td><pre>'.print_r($data, true).'</pre></td>';
            echo '<td>'.esc_html($row['created_at']).'</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    } else {
        echo '<p>Aucun profil enregistré pour le moment.</p>';
    }
    echo '</div>';
}

function csp_render_admin_settings() {
    echo '<div class="wrap"><h1>Réglages du plugin Custom Size</h1>';
    echo '<p>Ici, vous pourrez définir les paramètres du plugin (à développer selon besoins).</p>';
    echo '</div>';
}

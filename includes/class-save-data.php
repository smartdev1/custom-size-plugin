<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * CSP_Save_Data
 * - création de table
 * - gestion AJAX: save measurements, get profile
 * - ajout auto au panier avec données individuelles
 * - attachement des mesures à la commande lors du checkout (backup)
 */
class CSP_Save_Data {

    private static $instance = null;
    public $table_name;

    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'csp_measurements';

        // AJAX endpoints
        add_action( 'wp_ajax_csp_save_measurements', array( $this, 'ajax_save_measurements' ) );
        add_action( 'wp_ajax_nopriv_csp_save_measurements', array( $this, 'ajax_save_measurements' ) );

        add_action( 'wp_ajax_csp_get_profile', array( $this, 'ajax_get_profile' ) );
        add_action( 'wp_ajax_nopriv_csp_get_profile', array( $this, 'ajax_get_profile' ) );

        // Link measurements to order in case session fallback is used
        add_action( 'woocommerce_checkout_create_order', array( $this, 'attach_measurements_to_order_on_checkout' ), 20, 2 );
    }

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Create database table for measurements
     */
    public static function create_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'csp_measurements';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) NULL,
            product_id BIGINT(20) NULL,
            order_id BIGINT(20) NULL,
            raw_data LONGTEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        add_option( 'csp_db_version', CSP_DB_VERSION );
    }

    /**
     * AJAX: save measurements and add to cart with measurement id
     */
    public function ajax_save_measurements() {
        check_ajax_referer( 'csp_save_measurements', 'security' );

        $raw = isset( $_POST['measurements'] ) ? wp_unslash( $_POST['measurements'] ) : '';
        $product_id = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;

        if ( empty( $raw ) ) {
            wp_send_json_error( array( 'message' => __( 'Aucune donnée reçue', 'custom-size-plugin' ) ) );
        }

        $data = json_decode( $raw, true );
        if ( ! is_array( $data ) ) {
            wp_send_json_error( array( 'message' => __( 'Format invalide', 'custom-size-plugin' ) ) );
        }

        global $wpdb;
        $table = $this->table_name;
        $user_id = get_current_user_id() ? get_current_user_id() : null;

        $inserted = $wpdb->insert(
            $table,
            array(
                'user_id'   => $user_id,
                'product_id'=> $product_id,
                'raw_data'  => maybe_serialize( $data ),
            ),
            array( '%d', '%d', '%s' )
        );

        if ( false === $inserted ) {
            wp_send_json_error( array( 'message' => __( 'Erreur base de données', 'custom-size-plugin' ) ) );
        }

        $insert_id = $wpdb->insert_id;

        // Add to cart and attach measurement id to the cart item
        if ( function_exists( 'WC' ) && WC()->cart ) {
            // ensure the cart line is unique even for identical products
            $unique_key = md5( $insert_id . microtime() . wp_generate_password( 8, false ) );

            $added = WC()->cart->add_to_cart(
                $product_id,
                1,
                0,
                array(),
                array(
                    'csp_measurement_id' => $insert_id,
                    'unique_key'         => $unique_key,
                )
            );

            if ( false === $added ) {
                // fallback: still return success (profile saved) but indicate not added to cart
                wp_send_json_success( array(
                    'message' => __( 'Mensurations enregistrées mais le produit n\'a pas été ajouté au panier.', 'custom-size-plugin' ),
                    'id'      => $insert_id,
                ) );
            }
        }

        wp_send_json_success( array(
            'message' => __( 'Mensurations enregistrées', 'custom-size-plugin' ),
            'id'      => $insert_id,
        ) );
    }

    /**
     * AJAX: return profile data for a given profile id (must belong to current user)
     */
    public function ajax_get_profile() {
        check_ajax_referer( 'csp_save_measurements', 'security' );

        $profile_id = isset( $_POST['profile_id'] ) ? intval( $_POST['profile_id'] ) : 0;
        if ( ! $profile_id ) {
            wp_send_json_error( array( 'message' => __( 'Profil invalide', 'custom-size-plugin' ) ) );
        }

        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT raw_data, user_id FROM {$this->table_name} WHERE id = %d", $profile_id ), ARRAY_A );

        if ( ! $row || intval( $row['user_id'] ) !== intval( get_current_user_id() ) ) {
            wp_send_json_error( array( 'message' => __( 'Accès refusé', 'custom-size-plugin' ) ) );
        }

        $data = maybe_unserialize( $row['raw_data'] );
        if ( ! is_array( $data ) ) {
            wp_send_json_error( array( 'message' => __( 'Format invalide', 'custom-size-plugin' ) ) );
        }

        wp_send_json_success( $data );
    }

    /**
     * Attach measurements referenced in session to order as fallback
     * (safe backup if cart/session flow differs)
     */
    public function attach_measurements_to_order_on_checkout( $order, $data ) {
        if ( ! function_exists( 'WC' ) ) {
            return;
        }

        if ( ! WC()->cart ) {
            return;
        }

        // iterate cart items to ensure measurement IDs saved to order items (should be done by hook in main plugin)
        foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
            if ( isset( $cart_item['csp_measurement_id'] ) ) {
                // nothing to do here because main hook saves to order line item
            }
        }
    }

    // Vérifier si l'utilisateur a déjà des mensurations
public function handle_check_measurements() {
    check_ajax_referer('csp_measurements_nonce', 'security');

    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'Utilisateur non connecté.'));
    }

    global $wpdb;
    $table = $wpdb->prefix . 'csp_measurements';
    $user_id = intval($_POST['user_id']);
    $exists = $wpdb->get_row($wpdb->prepare("SELECT id, raw_data FROM $table WHERE user_id = %d ORDER BY created_at DESC LIMIT 1", $user_id));

    if ($exists) {
        wp_send_json_success(array(
            'exists' => true,
            'measurements' => maybe_unserialize($exists->raw_data)
        ));
    } else {
        wp_send_json_success(array('exists' => false));
    }
}

// Sauvegarder les mensurations dans la base de données
public function handle_save_measurements() {
    check_ajax_referer('csp_measurements_nonce', 'security');

    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'Utilisateur non connecté.'));
    }

    global $wpdb;
    $table = $wpdb->prefix . 'csp_measurements';
    $user_id = intval($_POST['user_id']);
    $measurements = json_decode(stripslashes($_POST['measurements']), true);

    // Valider et nettoyer les données
    $sanitized_data = array();
    foreach ($measurements as $key => $value) {
        $sanitized_data[$key] = sanitize_text_field($value);
    }

    $wpdb->insert(
        $table,
        array(
            'user_id'   => $user_id,
            'raw_data'  => maybe_serialize($sanitized_data),
            'created_at'=> current_time('mysql'),
        ),
        array('%d', '%s', '%s')
    );

    wp_send_json_success();
}

// Mettre à jour les mensurations existantes
public function handle_update_measurements() {
    check_ajax_referer('csp_measurements_nonce', 'security');

    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'Utilisateur non connecté.'));
    }

    global $wpdb;
    $table = $wpdb->prefix . 'csp_measurements';
    $user_id = intval($_POST['user_id']);
    $measurements = json_decode(stripslashes($_POST['measurements']), true);

    // Valider et nettoyer les données
    $sanitized_data = array();
    foreach ($measurements as $key => $value) {
        $sanitized_data[$key] = sanitize_text_field($value);
    }

    // Récupérer l'ID du dernier enregistrement
    $last_entry = $wpdb->get_row($wpdb->prepare("SELECT id FROM $table WHERE user_id = %d ORDER BY created_at DESC LIMIT 1", $user_id));

    if ($last_entry) {
        $wpdb->update(
            $table,
            array('raw_data' => maybe_serialize($sanitized_data)),
            array('id' => $last_entry->id),
            array('%s'),
            array('%d')
        );
        wp_send_json_success();
    } else {
        wp_send_json_error(array('message' => 'Aucune mensuration existante trouvée.'));
    }
}

}

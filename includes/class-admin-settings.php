<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * CSP_Admin_Settings
 * - Display metabox in order admin to see measurements
 * - Display measurement data in the order details area
 */
class CSP_Admin_Settings {

    private static $instance = null;

    private function __construct() {
        add_action( 'add_meta_boxes', array( $this, 'add_order_meta_box' ) );
        add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'display_measurements_in_order' ), 10, 1 );

        // Add column header and values for order items table within admin order details
        add_action( 'woocommerce_admin_order_item_headers', array( $this, 'add_order_item_header' ) );
        add_action( 'woocommerce_admin_order_item_values', array( $this, 'add_order_item_values' ), 10, 3 );
    }

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function add_order_meta_box() {
        add_meta_box( 'csp_order_measurements', __( 'Mensurations client', 'custom-size-plugin' ), array( $this, 'render_order_meta_box' ), 'shop_order', 'side', 'default' );
    }

    public function render_order_meta_box( $post ) {
        $order_id = $post->ID;
        global $wpdb;
        $table = $wpdb->prefix . 'csp_measurements';

        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE order_id = %d ORDER BY created_at DESC", $order_id ), ARRAY_A );

        if ( empty( $rows ) ) {
            echo '<p>' . esc_html__( 'Aucune mensuration liée à cette commande.', 'custom-size-plugin' ) . '</p>';
            return;
        }

        echo '<div style="max-height:300px;overflow:auto">';
        foreach ( $rows as $r ) {
            echo '<div style="border-bottom:1px solid #eee;padding:8px 0;">';
            echo '<strong>' . esc_html( sprintf( __( 'Profil #%d', 'custom-size-plugin' ), $r['id'] ) ) . '</strong><br/>';
            if ( ! empty( $r['raw_data'] ) ) {
                $raw = maybe_unserialize( $r['raw_data'] );
                if ( is_array( $raw ) ) {
                    echo '<ul style="list-style:none;padding-left:0;margin:0;">';
                    foreach ( $raw as $k => $v ) {
                        echo '<li><strong>' . esc_html( ucfirst( str_replace( array('_','-'), ' ', $k ) ) ) . ':</strong> ' . esc_html( $v ) . '</li>';
                    }
                    echo '</ul>';
                } else {
                    echo esc_html( $r['raw_data'] );
                }
            }
            echo '<small>' . esc_html( $r['created_at'] ) . '</small>';
            echo '</div>';
        }
        echo '</div>';
    }

    public function display_measurements_in_order( $order ) {
        // This displays under the order data area in admin
        $order_id = $order->get_id();
        global $wpdb;
        $table = $wpdb->prefix . 'csp_measurements';
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE order_id = %d ORDER BY created_at DESC", $order_id ), ARRAY_A );

        if ( $rows ) {
            echo '<div class="order_data_column">';
            echo '<h4>' . esc_html__( 'Mensurations client', 'custom-size-plugin' ) . '</h4>';
            foreach ( $rows as $r ) {
                if ( ! empty( $r['raw_data'] ) ) {
                    $raw = maybe_unserialize( $r['raw_data'] );
                    if ( is_array( $raw ) ) {
                        echo '<ul style="list-style:none;padding-left:0;margin:0 0 10px 0;">';
                        foreach ( $raw as $k => $v ) {
                            echo '<li><strong>' . esc_html( ucfirst( str_replace( array('_','-'), ' ', $k ) ) ) . ':</strong> ' . esc_html( $v ) . '</li>';
                        }
                        echo '</ul>';
                    } else {
                        echo esc_html( $r['raw_data'] );
                    }
                }
            }
            echo '</div>';
        }
    }

    /**
     * Add header column for order items measurement (admin order edit page)
     */
    public function add_order_item_header() {
        echo '<th class="csp-col">' . esc_html__( 'Mensurations', 'custom-size-plugin' ) . '</th>';
    }

    /**
     * Add values per order item in admin order items table
     *
     * @param WC_Product $product
     * @param WC_Order_Item_Product $item
     * @param int $item_id
     */
    public function add_order_item_values( $product, $item, $item_id ) {
        $mid = $item->get_meta( '_csp_measurement_id' );
        echo '<td>';
        if ( $mid ) {
            global $wpdb;
            $table = $wpdb->prefix . 'csp_measurements';
            $row = $wpdb->get_row( $wpdb->prepare( "SELECT raw_data FROM {$table} WHERE id = %d", intval( $mid ) ), ARRAY_A );
            if ( $row && ! empty( $row['raw_data'] ) ) {
                $data = maybe_unserialize( $row['raw_data'] );
                if ( is_array( $data ) ) {
                    echo '<ul style="margin:0;padding-left:10px;">';
                    foreach ( $data as $k => $v ) {
                        echo '<li><strong>' . esc_html( ucfirst( str_replace( array('_','-'), ' ', $k ) ) ) . ':</strong> ' . esc_html( $v ) . '</li>';
                    }
                    echo '</ul>';
                } else {
                    echo esc_html( $row['raw_data'] );
                }
            } else {
                echo '&mdash;';
            }
        } else {
            echo '&mdash;';
        }
        echo '</td>';
    }
}

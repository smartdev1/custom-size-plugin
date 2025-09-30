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

/* ajout du menu dans l'interface d'administration */
function csp_register_admin_menu() {
    // menu principal
    add_menu_page(
        __( 'Custom Size', 'custom-size-plugin' ), 
        __( 'Custom Size', 'custom-size-plugin' ),
        'manage_options',                         
        'csp-dashboard',                           
        'csp_render_admin_dashboard',              
        'dashicons-ruler',                         
        25                                        
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

/* Helper function to get plugin settings with defaults */
function csp_get_setting($key = '', $default = null) {
    $settings = get_option('csp_settings', array());
    
    if (empty($key)) {
        return $settings;
    }
    
    // Valeurs par défaut complètes
    $defaults = array(
        'enable_popup' => 1,
        'auto_button' => 1,
        'required_fields' => array('taille', 'poids'),
        'allowed_roles' => array('customer', 'subscriber'),
        'popup_title' => 'Vos mensurations',
        'button_text' => 'Ajouter vos mensurations',
        'primary_color' => '#2271b1',
        'custom_css' => '',
        'cart_behavior' => 'separate',
        'show_in_cart' => 1,
        'show_in_orders' => 1,
        'data_retention' => '365',
        'auto_cleanup' => 0
    );
    
    $value = isset($settings[$key]) ? $settings[$key] : $default;
    
    // Si pas de valeur et pas de default personnalisé, utiliser les defaults
    if (null === $value && null === $default) {
        $value = isset($defaults[$key]) ? $defaults[$key] : null;
    }
    
    return $value;
}

/* Enqueue frontend assets avec vérification des réglages */
function csp_enqueue_assets() {
    // Vérifier si le plugin est activé
    if (!csp_get_setting('enable_popup', 1)) {
        return;
    }

    // Vérifier si l'utilisateur a le droit d'utiliser le plugin
    $user = wp_get_current_user();
    $allowed_roles = csp_get_setting('allowed_roles', array('customer', 'subscriber'));
    $has_allowed_role = array_intersect($allowed_roles, $user->roles);
    
    // Permettre aux administrateurs de voir le popup même s'ils ne sont pas dans les rôles autorisés
    $is_admin = in_array('administrator', $user->roles);
    
    if (empty($has_allowed_role) && !$is_admin && !is_admin()) {
        return;
    }

    // Charger APRÈS WooCommerce pour éviter les conflits de style
    $dependencies = array();
    if (class_exists('WooCommerce')) {
        $dependencies[] = 'woocommerce-general';
    }
    
    wp_enqueue_style( 
        'csp-popup-css', 
        CSP_PLUGIN_URL . 'assets/css/popup.css', 
        $dependencies, 
        '1.0' 
    );
    
    wp_enqueue_script( 
        'csp-popup-js', 
        CSP_PLUGIN_URL . 'assets/js/popup.js', 
        array('jquery'), 
        '1.0', 
        true 
    );

    // Appliquer la couleur principale via CSS personnalisé
    $primary_color = csp_get_setting('primary_color', '#8b6f47');
    $custom_css = csp_get_setting('custom_css', '');
    
    $css = "
        /* Boutons principaux */
        .csp-open-modal.button, 
        .csp-next, 
        .csp-submit {
            background-color: {$primary_color} !important;
            border-color: {$primary_color} !important;
            color: #fff !important;
        }
        
        .csp-open-modal.button:hover, 
        .csp-next:hover, 
        .csp-submit:hover {
            background-color: {$primary_color} !important;
            border-color: {$primary_color} !important;
            opacity: 0.9;
        }
        
        /* Barre de progression */
        .csp-progress-fill {
            background-color: {$primary_color} !important;
        }
        
        /* Étapes actives */
        .csp-step.active {
            background-color: {$primary_color} !important;
            border-color: {$primary_color} !important;
        }
        
        /* CSS personnalisé utilisateur */
        {$custom_css}
    ";
    
    wp_add_inline_style('csp-popup-css', $css);

    // Préparer les données pour JavaScript
    $ajax_data = array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('csp_save_measurements'),
        'required_fields' => csp_get_setting('required_fields', array('taille', 'poids')),
        'popup_title' => csp_get_setting('popup_title', 'Vos mensurations'),
        'button_text' => csp_get_setting('button_text', 'Ajouter vos mensurations')
    );

    wp_localize_script('csp-popup-js', 'csp_ajax_obj', $ajax_data);
}
add_action( 'wp_enqueue_scripts', 'csp_enqueue_assets' );

/* Inject button on product page avec vérification des réglages */
function csp_inject_button() {
    // Vérifier si le bouton automatique est activé
    if (!csp_get_setting('auto_button', 1)) {
        return;
    }

    // Vérifier si le plugin est activé
    if (!csp_get_setting('enable_popup', 1)) {
        return;
    }

    // Vérifier si on est sur une page produit
    if (!is_product()) {
        return;
    }

    $button_text = csp_get_setting('button_text', 'Ajouter vos mensurations');
    
    // Display button and popup
    echo '<div class="csp-add-measurements-wrap">';
    echo '<div class="csp-add-measurements-wrap">';
    echo '<h5>SUR-MESURE: VOS MENSURATIONS, NOTRE PRECISION</h5>';
    echo '<span>Indiquez vos mensurations et nous ajusterons ce vêtement avec précision pour un fit parfait</span>';
    echo '</div>';
    echo '<button type="button" class="csp-open-modal button">' . esc_html($button_text) . '</button>';
    echo '</div>';
    CSP_Popup_Render::render_popup_template();
}

add_action( 'woocommerce_after_add_to_cart_form', 'csp_inject_button' );

/* Shortcode for button avec vérification des réglages */
function csp_shortcode_button( $atts ) {
    // Vérifier si le plugin est activé
    if (!csp_get_setting('enable_popup', 1)) {
        return '';
    }

    $button_text = csp_get_setting('button_text', 'Ajouter vos mensurations');
    
    ob_start();
    echo '<div class="csp-add-measurements-wrap">';
    echo '<button type="button" class="csp-open-modal button">' . esc_html($button_text) . '</button>';
    echo '</div>';
    CSP_Popup_Render::render_popup_template();
    return ob_get_clean();
}
add_shortcode( 'csp_add_measurements', 'csp_shortcode_button' );

/* Prevent merging cart items with different measurement data */
add_filter( 'woocommerce_add_cart_item_data', function( $cart_item_data, $product_id ) {
    $cart_behavior = csp_get_setting('cart_behavior', 'separate');
    
    if ($cart_behavior === 'separate' && isset( $cart_item_data['unique_key'] )) {
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

/* Show measurement data in cart (under product name) avec vérification des réglages */
add_filter( 'woocommerce_get_item_data', function( $item_data, $cart_item ) {
    // Vérifier si l'affichage dans le panier est activé
    if (!csp_get_setting('show_in_cart', 1)) {
        return $item_data;
    }

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
    // Vérifier si l'affichage dans les commandes est activé
    if (!csp_get_setting('show_in_orders', 1)) {
        return;
    }

    if ( isset( $values['csp_measurement_id'] ) ) {
        $item->add_meta_data( '_csp_measurement_id', $values['csp_measurement_id'], true );
    }
}, 10, 4 );

/* Add "Mes mensurations" endpoint to My Account */
add_filter( 'woocommerce_account_menu_items', function( $items ) {
    // Vérifier si le plugin est activé
    if (!csp_get_setting('enable_popup', 1)) {
        return $items;
    }

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

        // Récupérer les champs requis depuis les réglages
        $required_fields = csp_get_setting('required_fields', array('taille', 'poids'));
        
        $data = array();
        $available_fields = array('taille', 'poids', 'poitrine', 'taille_manche', 'taille_hauteur', 'tour_taille', 'tour_hanches');
        
        foreach ($available_fields as $field) {
            if (isset($_POST[$field])) {
                $data[$field] = sanitize_text_field(wp_unslash($_POST[$field]));
            }
        }

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

/* Tableau de bord amélioré */
function csp_render_admin_dashboard() {
    global $wpdb;
    
    $table = $wpdb->prefix . 'csp_measurements';
    
    // Récupération des statistiques
    $total_profiles = $wpdb->get_var("SELECT COUNT(*) FROM $table");
    $today_profiles = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table WHERE DATE(created_at) = %s", 
        current_time('Y-m-d')
    ));
    $month_profiles = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table WHERE MONTH(created_at) = %d AND YEAR(created_at) = %d", 
        current_time('m'), current_time('Y')
    ));
    $users_with_profiles = $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM $table");
    
    // Derniers profils
    $recent_profiles = $wpdb->get_results(
        "SELECT * FROM $table ORDER BY created_at DESC LIMIT 5", 
        ARRAY_A
    );
    
    ?>
    <div class="wrap">
        <h1>📊 Custom Size – Tableau de bord</h1>
        
        <!-- Cartes stats -->
        <div class="csp-stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 25px 0;">
            <?php 
            $stat_cards = array(
                array(
                    'title' => 'Profils totaux',
                    'value' => $total_profiles,
                    'color' => '#2271b1',
                    'icon' => '👥'
                ),
                array(
                    'title' => 'Aujourd\'hui', 
                    'value' => $today_profiles,
                    'color' => '#4CAF50',
                    'icon' => '📈'
                ),
                array(
                    'title' => 'Ce mois',
                    'value' => $month_profiles, 
                    'color' => '#FF9800',
                    'icon' => '📅'
                ),
                array(
                    'title' => 'Utilisateurs actifs',
                    'value' => $users_with_profiles,
                    'color' => '#9C27B0', 
                    'icon' => '👤'
                )
            );
            
            foreach ($stat_cards as $card) : ?>
                <div class="csp-stat-card" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); border-left: 4px solid <?php echo $card['color']; ?>;">
                    <div style="font-size: 2em; margin-bottom: 10px;"><?php echo $card['icon']; ?></div>
                    <h3 style="margin: 0 0 5px 0; color: #666; font-size: 14px;"><?php echo $card['title']; ?></h3>
                    <div style="font-size: 2em; font-weight: bold; color: <?php echo $card['color']; ?>;"><?php echo $card['value']; ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <!-- Derniers profils -->
            <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                <h2>🆕 Derniers profils</h2>
                <?php if ($recent_profiles) : ?>
                    <table class="widefat striped" style="width: 100%;">
                        <thead>
                            <tr>
                                <th>Utilisateur</th>
                                <th>Taille</th>
                                <th>Poids</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_profiles as $profile) : 
                                $data = maybe_unserialize($profile['raw_data']);
                                $user_info = get_userdata($profile['user_id']);
                                $username = $user_info ? $user_info->display_name : 'Invité';
                            ?>
                                <tr>
                                    <td>
                                        <?php if ($user_info) : ?>
                                            <strong><?php echo esc_html($username); ?></strong>
                                        <?php else : ?>
                                            <?php echo esc_html($username); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html($data['taille'] ?? '--'); ?> cm</td>
                                    <td><?php echo esc_html($data['poids'] ?? '--'); ?> kg</td>
                                    <td><?php echo esc_html(date('d/m', strtotime($profile['created_at']))); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p style="text-align: center; margin-top: 15px;">
                        <a href="<?php echo admin_url('admin.php?page=csp-profiles'); ?>" class="button">
                            Voir tous les profils →
                        </a>
                    </p>
                <?php else : ?>
                    <p style="text-align: center; padding: 20px; color: #666;">
                        Aucun profil créé pour le moment.
                    </p>
                <?php endif; ?>
            </div>

            <!-- Configuration actuelle -->
            <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                <h2>⚙️ Configuration actuelle</h2>
                <div style="line-height: 2;">
                    <p><strong>Plugin:</strong> <?php echo csp_get_setting('enable_popup') ? '✅ Activé' : '❌ Désactivé'; ?></p>
                    <p><strong>Bouton auto:</strong> <?php echo csp_get_setting('auto_button') ? '✅ Activé' : '❌ Désactivé'; ?></p>
                    <p><strong>Champs requis:</strong> <?php echo count(csp_get_setting('required_fields', array())); ?></p>
                    <p><strong>Couleur:</strong> <span style="display: inline-block; width: 15px; height: 15px; background: <?php echo csp_get_setting('primary_color', '#2271b1'); ?>; border-radius: 3px; vertical-align: middle;"></span> <?php echo csp_get_setting('primary_color', '#2271b1'); ?></p>
                </div>
                <p style="text-align: center; margin-top: 15px;">
                    <a href="<?php echo admin_url('admin.php?page=csp-settings'); ?>" class="button button-primary">
                        Modifier les réglages →
                    </a>
                </p>
            </div>
        </div>

        <!-- Actions rapides -->
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-top: 20px;">
            <h2>🚀 Actions rapides</h2>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <a href="<?php echo admin_url('admin.php?page=csp-settings'); ?>" class="button button-primary">
                    ⚙️ Paramètres
                </a>
                <a href="<?php echo admin_url('widgets.php'); ?>" class="button">
                    🎯 Widgets
                </a>
                <a href="<?php echo admin_url('admin.php?page=csp-profiles'); ?>" class="button">
                    👥 Tous les profils
                </a>
                <a href="<?php echo home_url(); ?>" class="button" target="_blank">
                    👀 Voir le site
                </a>
            </div>
        </div>

    </div>
    <?php
}

function csp_render_admin_profiles() {
    global $wpdb;
    $table = $wpdb->prefix . 'csp_measurements';
    
    // Gestion de la pagination
    $per_page = 20;
    $current_page = max(1, isset($_GET['paged']) ? intval($_GET['paged']) : 1);
    $offset = ($current_page - 1) * $per_page;
    
    // Compter le total
    $total_profiles = $wpdb->get_var("SELECT COUNT(*) FROM $table");
    $total_pages = ceil($total_profiles / $per_page);
    
    // Récupérer les données avec pagination
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table ORDER BY created_at DESC LIMIT %d OFFSET %d",
        $per_page, $offset
    ), ARRAY_A);

    echo '<div class="wrap">';
    echo '<h1>👥 Profils clients</h1>';
    
    // En-tête avec statistiques
    echo '<div style="background: #fff; padding: 20px; margin-bottom: 20px; border-radius: 4px; border-left: 4px solid #2271b1;">';
    echo '<div style="display: flex; justify-content: space-between; align-items: center;">';
    echo '<div>';
    echo '<h3 style="margin: 0;">' . esc_html($total_profiles) . ' profils enregistrés</h3>';
    echo '<p style="margin: 5px 0 0 0; color: #666;">Gérez les mensurations de vos clients</p>';
    echo '</div>';
    echo '<a href="' . admin_url('admin.php?page=csp-dashboard') . '" class="button">📊 Tableau de bord</a>';
    echo '</div>';
    echo '</div>';
    
    if ( $rows ) {
        echo '<table class="widefat striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>ID</th>';
        echo '<th>Utilisateur</th>';
        echo '<th> Sexe</th>';
        echo '<th> Taille</th>';
        echo '<th> Poids</th>';
        echo '<th> Poitrine</th>';
        echo '<th> Epaule</th>';
        echo '<th> Cou</th>';
        echo '<th> Hanche</th>';
        echo '<th> Longueur Jambe</th>';
        echo '<th> Tour de cuisse</th>';
        echo '<th> Date </th>';
        echo '<th>Action</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach ( $rows as $row ) {
            $data = maybe_unserialize( $row['raw_data'] );
            $user_info = get_userdata($row['user_id']);
            $username = $user_info ? $user_info->user_login : 'Invité';
            $user_display = $user_info ? $user_info->display_name : 'Non connecté';
            $user_email = $user_info ? $user_info->user_email : '--';
            
            echo '<tr>';
            echo '<td><strong>#' . esc_html($row['id']) . '</strong></td>';
            echo '<td>';
            echo '<div style="min-width: 200px;">';
            echo '<strong>' . esc_html($user_display) . '</strong><br>';
            echo '<small style="color:#666">' . esc_html($user_email) . '</small><br>';
            echo '<small style="color:#999">ID: ' . esc_html($row['user_id']) . '</small>';
            echo '</div>';
            echo '</td>';
            echo '<td>' . ($data['genre'] ? esc_html($data['genre'])  : '<span style="color:#ccc">--</span>') . '</td>';
            echo '<td>' . ($data['taille'] ? esc_html($data['taille']) . ' kg' : '<span style="color:#ccc">--</span>') . '</td>';
            echo '<td>' . ($data['poids'] ? esc_html($data['poids']) . ' cm' : '<span style="color:#ccc">--</span>') . '</td>';
            echo '<td>' . ($data['poitrine'] ? esc_html($data['poitrine']) . ' cm' : '<span style="color:#ccc">--</span>') . '</td>';
            echo '<td>' . ($data['epaules'] ? esc_html($data['epaules']) . ' cm' : '<span style="color:#ccc">--</span>') . '</td>';
            echo '<td>' . ($data['tour_de_cou'] ? esc_html($data['tour_de_cou']) . ' cm' : '<span style="color:#ccc">--</span>') . '</td>';
            echo '<td>' . ($data['hanches'] ? esc_html($data['hanches']) . ' cm' : '<span style="color:#ccc">--</span>') . '</td>';
            echo '<td>' . ($data['longueur_jambe'] ? esc_html($data['longueur_jambe']) . ' cm' : '<span style="color:#ccc">--</span>') . '</td>';
            echo '<td>' . ($data['cuisse'] ? esc_html($data['cuisse']) . ' cm' : '<span style="color:#ccc">--</span>') . '</td>';
            echo '<td><small>' . esc_html(date('d/m/Y', strtotime($row['created_at']))) . '<br>' . esc_html(date('H:i', strtotime($row['created_at']))) . '</small></td>';
            echo '<td>';
            echo '<button type="button" class="button button-small" onclick="alert(\'' . addslashes(print_r($data, true)) . '\')">👁️ Détails</button>';
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
        
        // Pagination
        if ($total_pages > 1) {
            echo '<div class="tablenav">';
            echo '<div class="tablenav-pages">';
            echo paginate_links(array(
                'base' => add_query_arg('paged', '%#%'),
                'format' => '',
                'prev_text' => '&laquo;',
                'next_text' => '&raquo;',
                'total' => $total_pages,
                'current' => $current_page
            ));
            echo '</div>';
            echo '</div>';
        }
        
    } else {
        echo '<div class="notice notice-info">';
        echo '<p>ℹ️ Aucun profil enregistré pour le moment.</p>';
        echo '<p>Les profils apparaîtront ici lorsque les clients utiliseront le système de mensurations.</p>';
        echo '</div>';
    }
    echo '</div>';
    
    // Style additionnel
    echo '<style>
        .csp-profiles-table th { font-weight: 600; }
        .csp-profiles-table td { vertical-align: top; }
        .tablenav { margin: 20px 0; }
        .tablenav-pages { float: right; }
    </style>';
}

function csp_render_admin_settings() {
    // Vérifier les permissions
    if (!current_user_can('manage_options')) {
        wp_die(__('Vous n\'avez pas les permissions nécessaires pour accéder à cette page.', 'custom-size-plugin'));
    }
    
    ?>
    <div class="wrap csp-settings-wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <?php 
        // Afficher les messages de succès/erreur
        if (isset($_GET['settings-updated'])) {
            add_settings_error('csp_messages', 'csp_message', 
                __('Réglages sauvegardés avec succès.', 'custom-size-plugin'), 'success');
        }
        settings_errors('csp_messages'); 
        ?>
        
        <form action="options.php" method="post">
            <?php
            // Ces deux fonctions sont ESSENTIELLES pour afficher les champs
            settings_fields('csp_settings_group');
            do_settings_sections('csp-settings');
            submit_button(__('Sauvegarder les réglages', 'custom-size-plugin'));
            ?>
        </form>
        
        <div class="csp-settings-section" style="background: #fff; padding: 20px; margin-top: 20px; border-radius: 4px; border-left: 4px solid #2271b1;">
            <h2>📋 Aperçu de la configuration</h2>
            <p>Plugin: <strong><?php echo csp_get_setting('enable_popup') ? '✅ Activé' : '❌ Désactivé'; ?></strong></p>
            <p>Bouton automatique: <strong><?php echo csp_get_setting('auto_button') ? '✅ Activé' : '❌ Désactivé'; ?></strong></p>
            <p>Champs requis: <strong><?php echo implode(', ', csp_get_setting('required_fields', array())); ?></strong></p>
        </div>
    </div>
    <?php
}
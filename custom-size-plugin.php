<?php
/**
 * Plugin Name: Custom Size Plugin
 * Description: Gestion des mensurations clients — popup multi-step, sauvegarde, ajout automatique au panier, profils réutilisables et page Mon Compte.
 * Version:     1.3.0
 * Author:      Aristide Ghislain
 */

if (!defined('ABSPATH')) {
    exit;
}

/* Déclaration des constantes */
define('CSP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CSP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CSP_DB_VERSION', '1.1');
define('CSP_TRANSIENT_PREFIX', 'csp_');

/* Inclusion des classes */
require_once CSP_PLUGIN_DIR . 'includes/class-save-data.php';
require_once CSP_PLUGIN_DIR . 'includes/class-popup-render.php';
require_once CSP_PLUGIN_DIR . 'includes/class-admin-settings.php';
require_once CSP_PLUGIN_DIR . 'includes/class-widget.php';

/* Initialisation des classes */
function csp_init_plugin()
{
    CSP_Save_Data::get_instance();
    CSP_Popup_Render::get_instance();
    CSP_Admin_Settings::get_instance();
}
add_action('plugins_loaded', 'csp_init_plugin');

/* Enregistrement du widget */
function csp_register_widget()
{
    register_widget('CSP_Widget');
}
add_action('widgets_init', 'csp_register_widget');

/* Activation et désactivation */
function csp_activate()
{
    CSP_Save_Data::create_table();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'csp_activate');

function csp_deactivate()
{
    flush_rewrite_rules();
    // Nettoyage des transients à la désactivation
    delete_transient(CSP_TRANSIENT_PREFIX . 'dashboard_stats');
}
register_deactivation_hook(__FILE__, 'csp_deactivate');

/* Menu admin */
function csp_register_admin_menu()
{
    add_menu_page(
        __('Custom Size', 'custom-size-plugin'),
        __('Custom Size', 'custom-size-plugin'),
        'manage_options',
        'csp-dashboard',
        'csp_render_admin_dashboard',
        'dashicons-ruler',
        25
    );

    add_submenu_page(
        'csp-dashboard',
        __('Tableau de bord', 'custom-size-plugin'),
        __('Tableau de bord', 'custom-size-plugin'),
        'manage_options',
        'csp-dashboard',
        'csp_render_admin_dashboard'
    );

    add_submenu_page(
        'csp-dashboard',
        __('Profils clients', 'custom-size-plugin'),
        __('Profils clients', 'custom-size-plugin'),
        'manage_options',
        'csp-profiles',
        'csp_render_admin_profiles'
    );

    add_submenu_page(
        'csp-dashboard',
        __('Réglages', 'custom-size-plugin'),
        __('Réglages', 'custom-size-plugin'),
        'manage_options',
        'csp-settings',
        'csp_render_admin_settings'
    );
}
add_action('admin_menu', 'csp_register_admin_menu');

/* Helper: Récupère un réglage avec valeur par défaut */
function csp_get_setting($key = '', $default = null)
{
    $settings = get_option('csp_settings', array());
    $defaults = array(
        'enable_popup'    => 1,
        'auto_button'     => 1,
        'required_fields' => array('taille', 'poids'),
        'allowed_roles'   => array('customer', 'subscriber'),
        'popup_title'     => 'Vos mensurations',
        'button_text'     => 'Ajouter vos mensurations',
        'primary_color'   => '#2271b1',
        'custom_css'      => '',
        'cart_behavior'   => 'separate',
        'show_in_cart'    => 1,
        'show_in_orders'  => 1,
        'data_retention'  => '365',
        'auto_cleanup'    => 0,
    );

    if (empty($key)) {
        return array_merge($defaults, $settings);
    }

    return $settings[$key] ?? ($default ?? ($defaults[$key] ?? null));
}

/* Chargement conditionnel des assets */
function csp_enqueue_assets()
{
    // Ne pas charger si le plugin est désactivé
    if (!csp_get_setting('enable_popup', 1)) {
        return;
    }

    // Charger uniquement sur les pages produits WooCommerce ou les pages avec le shortcode
    $should_load = false;

    // Cas 1 : Page produit WooCommerce
    if (function_exists('is_product') && is_product()) {
        $should_load = true;
    }
    // Cas 2 : Shortcode présent sur la page
    elseif (is_singular() && has_shortcode(get_the_content(), 'csp_add_measurements')) {
        $should_load = true;
    }
    // Cas 3 : Page "Mon Compte" (si vous voulez aussi le style là-bas)
    elseif (function_exists('is_account_page') && is_account_page()) {
        $should_load = true;
    }

    if (!$should_load) {
        return;
    }

    // Charger les assets
    $dependencies = array();
    if (class_exists('WooCommerce')) {
        $dependencies[] = 'woocommerce-general';
    }

    wp_enqueue_style(
        'csp-popup-css',
        CSP_PLUGIN_URL . 'assets/css/popup.css',
        $dependencies,
        '1.1'
    );

    // CSS dynamique pour la couleur principale
    $primary_color = esc_attr(csp_get_setting('primary_color', '#2271b1'));
    $custom_css = esc_textarea(csp_get_setting('custom_css', ''));

    $inline_css = "
        :root {
            --csp-primary-color: {$primary_color};
        }
        .csp-open-modal.button,
        .csp-next,
        .csp-submit {
            background-color: var(--csp-primary-color) !important;
            border-color: var(--csp-primary-color) !important;
            color: #fff !important;
        }
        .csp-progress-fill {
            background-color: var(--csp-primary-color) !important;
        }
        .csp-step.active {
            background-color: var(--csp-primary-color) !important;
            border-color: var(--csp-primary-color) !important;
        }
        {$custom_css}
    ";

    wp_add_inline_style('csp-popup-css', $inline_css);

    // Charger le JS uniquement si nécessaire
    wp_enqueue_script(
        'csp-popup-js',
        CSP_PLUGIN_URL . 'assets/js/popup.js',
        array('jquery'),
        '1.1',
        true
    );

    // Localisation pour AJAX
    wp_localize_script('csp-popup-js', 'csp_ajax_obj', array(
        'ajax_url'       => admin_url('admin-ajax.php'),
        'nonce'          => wp_create_nonce('csp_save_measurements'),
        'required_fields'=> csp_get_setting('required_fields', array('taille', 'poids')),
        'popup_title'    => esc_html(csp_get_setting('popup_title', 'Vos mensurations')),
        'button_text'    => esc_html(csp_get_setting('button_text', 'Ajouter vos mensurations')),
        'is_logged_in'   => is_user_logged_in() ? 1 : 0,
    ));
}
add_action('wp_enqueue_scripts', 'csp_enqueue_assets');

add_action('wp_footer', function() {
    if (is_user_logged_in()) {
        ?>
        <script>
        document.addEventListener("DOMContentLoaded", function() {
            const saved = localStorage.getItem("csp_measurements");
            if (saved) {
                const data = JSON.parse(saved);
                if (Object.keys(data).length > 0) {
                    if (confirm("Nous avons trouvé des mensurations enregistrées sur cet appareil. Voulez-vous les sauvegarder dans votre compte ?")) {
                        fetch(window.csp_ajax_obj.ajax_url, {
                            method: "POST",
                            credentials: "same-origin",
                            headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
                            body: new URLSearchParams({
                                action: "csp_save_measurements",
                                security: window.csp_ajax_obj.nonce,
                                measurements: JSON.stringify(data)
                            }).toString()
                        })
                        .then(r => r.json())
                        .then(res => {
                            if (res.success) {
                                alert("Mensurations synchronisées avec votre compte !");
                                localStorage.removeItem("csp_measurements");
                            }
                        });
                    }
                }
            }
        });
        </script>
        <?php
    }
});


/* Injection du bouton sur la page produit */
function csp_inject_button()
{
    if (!is_user_logged_in()) {
        echo '<div class="csp-add-measurements-wrap">';
        
        echo '<div class="csp-add-measurements-wrap">';
        echo '<h5>SUR-MESURE: VOS MENSURATIONS, NOTRE PRECISION</h5>';
        echo '<span>Indiquez vos mensurations et nous ajusterons ce vêtement avec précision pour un fit parfait</span>';
        echo '</div>';
        echo '<button type="button" class="csp-open-modal button">' . esc_html__('Ajouter vos mensurations', 'custom-size-plugin') . '</button>';
        echo '</div>';
        CSP_Popup_Render::render_popup_template();
        return;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'csp_measurements';
    $user_id = get_current_user_id();

    $rows = $wpdb->get_results(
        $wpdb->prepare("SELECT id, raw_data FROM {$table} WHERE user_id = %d ORDER BY created_at DESC LIMIT 1", $user_id),
        ARRAY_A
    );

    echo '<div class="csp-add-measurements-wrap">';
    if (!empty($rows)) {
        $last = $rows[0];
        $data = maybe_unserialize($last['raw_data']);
        if (is_array($data)) {
            echo '<div class="csp-measurements-summary">';
            echo '<h5>' . esc_html__('Vos mensurations enregistrées', 'custom-size-plugin') . '</h5>';
            echo '<ul>';
            foreach ($data as $k => $v) {
                echo '<li><strong>' . esc_html(ucfirst(str_replace('_', ' ', $k))) . ':</strong> ' . esc_html($v) . '</li>';
            }
            echo '</ul>';
            echo '</div>';
        }
        echo '<button type="button" class="csp-open-modal button">' . esc_html__('Modifier mes mensurations', 'custom-size-plugin') . '</button>';
    } else {
        
        echo '<button type="button" class="csp-open-modal button">' . esc_html__('Ajouter vos mensurations', 'custom-size-plugin') . '</button>';
    }
    echo '</div>';
    CSP_Popup_Render::render_popup_template();
}
add_action('woocommerce_after_add_to_cart_form', 'csp_inject_button');

/* Shortcode pour le bouton */
function csp_shortcode_button($atts)
{
    if (!csp_get_setting('enable_popup', 1)) {
        return '';
    }
    $button_text = esc_html(csp_get_setting('button_text', 'Ajouter vos mensurations'));
    ob_start();
    echo '<div class="csp-add-measurements-wrap">';
    echo '<button type="button" class="csp-open-modal button">' . $button_text . '</button>';
    echo '</div>';
    CSP_Popup_Render::render_popup_template();
    return ob_get_clean();
}
add_shortcode('csp_add_measurements', 'csp_shortcode_button');

/* Empêcher la fusion des articles du panier avec des mensurations différentes */
add_filter('woocommerce_add_cart_item_data', function ($cart_item_data, $product_id) {
    $cart_behavior = csp_get_setting('cart_behavior', 'separate');
    if ($cart_behavior === 'separate' && isset($cart_item_data['unique_key'])) {
        $cart_item_data['unique_key'] = md5((string) $cart_item_data['unique_key'] . microtime() . rand());
    }
    return $cart_item_data;
}, 10, 2);

add_filter('woocommerce_get_cart_item_from_session', function ($cart_item, $values) {
    if (isset($values['csp_measurement_id'])) {
        $cart_item['csp_measurement_id'] = $values['csp_measurement_id'];
    }
    if (isset($values['unique_key'])) {
        $cart_item['unique_key'] = $values['unique_key'];
    }
    return $cart_item;
}, 10, 2);

/* Afficher les mensurations dans le panier */
add_filter('woocommerce_get_item_data', function ($item_data, $cart_item) {
    if (!csp_get_setting('show_in_cart', 1)) {
        return $item_data;
    }
    if (isset($cart_item['csp_measurement_id'])) {
        global $wpdb;
        $table = $wpdb->prefix . 'csp_measurements';
        $row = $wpdb->get_row($wpdb->prepare("SELECT raw_data FROM {$table} WHERE id = %d", intval($cart_item['csp_measurement_id'])), ARRAY_A);
        if ($row && !empty($row['raw_data'])) {
            $data = maybe_unserialize($row['raw_data']);
            if (is_array($data)) {
                foreach ($data as $k => $v) {
                    $item_data[] = array(
                        'name'  => ucfirst(str_replace(array('_', '-'), ' ', $k)),
                        'value' => esc_html($v),
                    );
                }
            }
        }
    }
    return $item_data;
}, 10, 2);

/* Sauvegarder l'ID de mensuration dans les lignes de commande */
add_action('woocommerce_checkout_create_order_line_item', function ($item, $cart_item_key, $values, $order) {
    if (!csp_get_setting('show_in_orders', 1)) {
        return;
    }
    if (isset($values['csp_measurement_id'])) {
        $item->add_meta_data('_csp_measurement_id', $values['csp_measurement_id'], true);
    }
}, 10, 4);

/* Ajouter l'endpoint "Mes mensurations" */
add_filter('woocommerce_account_menu_items', function ($items) {
    if (!csp_get_setting('enable_popup', 1)) {
        return $items;
    }
    $logout = $items['customer-logout'] ?? null;
    unset($items['customer-logout']);
    $items['csp-measurements'] = __('Mes mensurations', 'custom-size-plugin');
    if ($logout) {
        $items['customer-logout'] = $logout;
    }
    return $items;
}, 20);

add_action('init', function () {
    add_rewrite_endpoint('csp-measurements', EP_ROOT | EP_PAGES);
});

/* Charger le template pour l'endpoint */
add_action('woocommerce_account_csp-measurements_endpoint', function () {
    if (!is_user_logged_in()) {
        echo '<p>' . esc_html__('Vous devez être connecté pour gérer vos mensurations.', 'custom-size-plugin') . '</p>';
        return;
    }
    include CSP_PLUGIN_DIR . 'templates/account-measurements.php';
});

/* Gérer les requêtes d'ajout/suppression de profils */
add_action('template_redirect', function () {
    if (!is_user_logged_in()) {
        return;
    }

    // Ajout de profil
    if (isset($_POST['csp_add_profile'], $_POST['csp_nonce']) &&
        wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['csp_nonce'])), 'csp_add_profile')) {
        global $wpdb;
        $table = $wpdb->prefix . 'csp_measurements';
        $required_fields = csp_get_setting('required_fields', array('taille', 'poids'));
        $available_fields = array('taille', 'poids', 'poitrine', 'taille_manche', 'taille_hauteur', 'tour_taille', 'tour_hanches', 'genre', 'epaules', 'tour_de_cou', 'hanches', 'longueur_jambe', 'cuisse');

        $data = array();
        foreach ($available_fields as $field) {
            if (isset($_POST[$field])) {
                $sanitized_value = sanitize_text_field(wp_unslash($_POST[$field]));
                // Validation basique pour les champs numériques
                if (in_array($field, array('taille', 'poids', 'poitrine', 'tour_taille', 'tour_hanches')) && !is_numeric($sanitized_value)) {
                    wc_add_notice(__('Valeur invalide pour ' . $field, 'custom-size-plugin'), 'error');
                    wp_safe_redirect(wc_get_account_endpoint_url('csp-measurements'));
                    exit;
                }
                $data[$field] = $sanitized_value;
            } elseif (in_array($field, $required_fields)) {
                wc_add_notice(__('Le champ ' . $field . ' est requis.', 'custom-size-plugin'), 'error');
                wp_safe_redirect(wc_get_account_endpoint_url('csp-measurements'));
                exit;
            }
        }

        $wpdb->insert($table, array(
            'user_id'   => get_current_user_id(),
            'raw_data'  => maybe_serialize($data),
            'created_at'=> current_time('mysql'),
        ), array('%d', '%s', '%s'));

        // Nettoyage du cache après ajout
        delete_transient(CSP_TRANSIENT_PREFIX . 'dashboard_stats');

        wc_add_notice(__('Profil ajouté avec succès.', 'custom-size-plugin'), 'success');
        wp_safe_redirect(wc_get_account_endpoint_url('csp-measurements'));
        exit;
    }

    // Suppression de profil
    if (isset($_GET['action'], $_GET['id']) && 'csp_delete' === $_GET['action']) {
        $id = intval($_GET['id']);
        if (isset($_GET['_wpnonce']) && wp_verify_nonce(wp_unslash($_GET['_wpnonce']), 'csp_delete_' . $id)) {
            global $wpdb;
            $table = $wpdb->prefix . 'csp_measurements';
            $deleted = $wpdb->delete($table, array('id' => $id, 'user_id' => get_current_user_id()), array('%d', '%d'));

            // Nettoyage du cache après suppression
            delete_transient(CSP_TRANSIENT_PREFIX . 'dashboard_stats');

            if ($deleted) {
                wc_add_notice(__('Profil supprimé.', 'custom-size-plugin'), 'success');
            } else {
                wc_add_notice(__('Aucun profil trouvé.', 'custom-size-plugin'), 'error');
            }
        } else {
            wc_add_notice(__('Échec de la suppression (sécurité).', 'custom-size-plugin'), 'error');
        }
        wp_safe_redirect(wc_get_account_endpoint_url('csp-measurements'));
        exit;
    }
});

/* Tableau de bord admin avec cache */
function csp_get_dashboard_stats()
{
    $stats = get_transient(CSP_TRANSIENT_PREFIX . 'dashboard_stats');
    if (false !== $stats) {
        return $stats;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'csp_measurements';

    $stats = array(
        'total_profiles'    => $wpdb->get_var("SELECT COUNT(*) FROM $table"),
        'today_profiles'    => $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE DATE(created_at) = %s", current_time('Y-m-d'))),
        'month_profiles'    => $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE MONTH(created_at) = %d AND YEAR(created_at) = %d", current_time('m'), current_time('Y'))),
        'users_with_profiles'=> $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM $table"),
    );

    set_transient(CSP_TRANSIENT_PREFIX . 'dashboard_stats', $stats, HOUR_IN_SECONDS);
    return $stats;
}

function csp_render_admin_dashboard()
{
    $stats = csp_get_dashboard_stats();
    global $wpdb;
    $table = $wpdb->prefix . 'csp_measurements';
    $recent_profiles = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC LIMIT 5", ARRAY_A);

    ?>
    <div class="wrap">
        <h1>📊 <?php echo esc_html__('Custom Size – Tableau de bord', 'custom-size-plugin'); ?></h1>

        <div class="csp-stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 25px 0;">
            <?php
            $stat_cards = array(
                array('title' => __('Profils totaux', 'custom-size-plugin'), 'value' => $stats['total_profiles'], 'color' => '#2271b1', 'icon' => '👥'),
                array('title' => __('Aujourd\'hui', 'custom-size-plugin'), 'value' => $stats['today_profiles'], 'color' => '#4CAF50', 'icon' => '📈'),
                array('title' => __('Ce mois', 'custom-size-plugin'), 'value' => $stats['month_profiles'], 'color' => '#FF9800', 'icon' => '📅'),
                array('title' => __('Utilisateurs actifs', 'custom-size-plugin'), 'value' => $stats['users_with_profiles'], 'color' => '#9C27B0', 'icon' => '👤'),
            );

            foreach ($stat_cards as $card) :
            ?>
                <div class="csp-stat-card" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); border-left: 4px solid <?php echo esc_attr($card['color']); ?>;">
                    <div style="font-size: 2em; margin-bottom: 10px;"><?php echo esc_html($card['icon']); ?></div>
                    <h3 style="margin: 0 0 5px 0; color: #666; font-size: 14px;"><?php echo esc_html($card['title']); ?></h3>
                    <div style="font-size: 2em; font-weight: bold; color: <?php echo esc_attr($card['color']); ?>;"><?php echo esc_html($card['value']); ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                <h2>🆕 <?php echo esc_html__('Derniers profils', 'custom-size-plugin'); ?></h2>
                <?php if ($recent_profiles) : ?>
                    <table class="widefat striped" style="width: 100%;">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__('Utilisateur', 'custom-size-plugin'); ?></th>
                                <th><?php echo esc_html__('Taille', 'custom-size-plugin'); ?></th>
                                <th><?php echo esc_html__('Poids', 'custom-size-plugin'); ?></th>
                                <th><?php echo esc_html__('Date', 'custom-size-plugin'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_profiles as $profile) :
                                $data = maybe_unserialize($profile['raw_data']);
                                $user_info = get_userdata($profile['user_id']);
                                $username = $user_info ? $user_info->display_name : __('Invité', 'custom-size-plugin');
                            ?>
                                <tr>
                                    <td><strong><?php echo esc_html($username); ?></strong></td>
                                    <td><?php echo esc_html($data['taille'] ?? '--'); ?> cm</td>
                                    <td><?php echo esc_html($data['poids'] ?? '--'); ?> kg</td>
                                    <td><?php echo esc_html(date('d/m', strtotime($profile['created_at']))); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p style="text-align: center; margin-top: 15px;">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=csp-profiles')); ?>" class="button">
                            <?php echo esc_html__('Voir tous les profils →', 'custom-size-plugin'); ?>
                        </a>
                    </p>
                <?php else : ?>
                    <p style="text-align: center; padding: 20px; color: #666;">
                        <?php echo esc_html__('Aucun profil créé pour le moment.', 'custom-size-plugin'); ?>
                    </p>
                <?php endif; ?>
            </div>

            <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                <h2>⚙️ <?php echo esc_html__('Configuration actuelle', 'custom-size-plugin'); ?></h2>
                <div style="line-height: 2;">
                    <p><strong><?php echo esc_html__('Plugin:', 'custom-size-plugin'); ?></strong> <?php echo csp_get_setting('enable_popup') ? '✅ ' . esc_html__('Activé', 'custom-size-plugin') : '❌ ' . esc_html__('Désactivé', 'custom-size-plugin'); ?></p>
                    <p><strong><?php echo esc_html__('Bouton auto:', 'custom-size-plugin'); ?></strong> <?php echo csp_get_setting('auto_button') ? '✅ ' . esc_html__('Activé', 'custom-size-plugin') : '❌ ' . esc_html__('Désactivé', 'custom-size-plugin'); ?></p>
                    <p><strong><?php echo esc_html__('Champs requis:', 'custom-size-plugin'); ?></strong> <?php echo esc_html(count(csp_get_setting('required_fields', array()))); ?></p>
                    <p><strong><?php echo esc_html__('Couleur:', 'custom-size-plugin'); ?></strong>
                        <span style="display: inline-block; width: 15px; height: 15px; background: <?php echo esc_attr(csp_get_setting('primary_color', '#2271b1')); ?>; border-radius: 3px; vertical-align: middle;"></span>
                        <?php echo esc_html(csp_get_setting('primary_color', '#2271b1')); ?>
                    </p>
                </div>
                <p style="text-align: center; margin-top: 15px;">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=csp-settings')); ?>" class="button button-primary">
                        <?php echo esc_html__('Modifier les réglages →', 'custom-size-plugin'); ?>
                    </a>
                </p>
            </div>
        </div>

        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-top: 20px;">
            <h2>🚀 <?php echo esc_html__('Actions rapides', 'custom-size-plugin'); ?></h2>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <a href="<?php echo esc_url(admin_url('admin.php?page=csp-settings')); ?>" class="button button-primary">
                    ⚙️ <?php echo esc_html__('Paramètres', 'custom-size-plugin'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('widgets.php')); ?>" class="button">
                    🎯 <?php echo esc_html__('Widgets', 'custom-size-plugin'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=csp-profiles')); ?>" class="button">
                    👥 <?php echo esc_html__('Tous les profils', 'custom-size-plugin'); ?>
                </a>
                <a href="<?php echo esc_url(home_url()); ?>" class="button" target="_blank">
                    👀 <?php echo esc_html__('Voir le site', 'custom-size-plugin'); ?>
                </a>
            </div>
        </div>
    </div>
    <?php
}

/* Liste des profils clients avec pagination et sélection optimisée des champs */
function csp_render_admin_profiles()
{
    global $wpdb;
    $table = $wpdb->prefix . 'csp_measurements';

    $per_page = 20;
    $current_page = max(1, isset($_GET['paged']) ? intval($_GET['paged']) : 1);
    $offset = ($current_page - 1) * $per_page;

    $total_profiles = $wpdb->get_var("SELECT COUNT(*) FROM $table");
    $total_pages = ceil($total_profiles / $per_page);

    // Sélection optimisée des champs
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT id, user_id, raw_data, created_at FROM $table ORDER BY created_at DESC LIMIT %d OFFSET %d",
        $per_page, $offset
    ), ARRAY_A);

    ?>
    <div class="wrap">
        <h1>👥 <?php echo esc_html__('Profils clients', 'custom-size-plugin'); ?></h1>

        <div style="background: #fff; padding: 20px; margin-bottom: 20px; border-radius: 4px; border-left: 4px solid #2271b1;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h3 style="margin: 0;"><?php echo esc_html($total_profiles); ?> <?php echo esc_html__('profils enregistrés', 'custom-size-plugin'); ?></h3>
                    <p style="margin: 5px 0 0 0; color: #666;"><?php echo esc_html__('Gérez les mensurations de vos clients', 'custom-size-plugin'); ?></p>
                </div>
                <a href="<?php echo esc_url(admin_url('admin.php?page=csp-dashboard')); ?>" class="button"><?php echo esc_html__('📊 Tableau de bord', 'custom-size-plugin'); ?></a>
            </div>
        </div>

        <?php if ($rows) : ?>
            <table class="widefat striped csp-profiles-table">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('ID', 'custom-size-plugin'); ?></th>
                        <th><?php echo esc_html__('Utilisateur', 'custom-size-plugin'); ?></th>
                        <th><?php echo esc_html__('Sexe', 'custom-size-plugin'); ?></th>
                        <th><?php echo esc_html__('Taille', 'custom-size-plugin'); ?></th>
                        <th><?php echo esc_html__('Poids', 'custom-size-plugin'); ?></th>
                        <th><?php echo esc_html__('Poitrine', 'custom-size-plugin'); ?></th>
                        <th><?php echo esc_html__('Épaules', 'custom-size-plugin'); ?></th>
                        <th><?php echo esc_html__('Cou', 'custom-size-plugin'); ?></th>
                        <th><?php echo esc_html__('Hanche', 'custom-size-plugin'); ?></th>
                        <th><?php echo esc_html__('Longueur jambe', 'custom-size-plugin'); ?></th>
                        <th><?php echo esc_html__('Cuisse', 'custom-size-plugin'); ?></th>
                        <th><?php echo esc_html__('Date', 'custom-size-plugin'); ?></th>
                        <th><?php echo esc_html__('Action', 'custom-size-plugin'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row) :
                        $data = maybe_unserialize($row['raw_data']);
                        $user_info = get_userdata($row['user_id']);
                        $username = $user_info ? $user_info->user_login : __('Invité', 'custom-size-plugin');
                        $user_display = $user_info ? $user_info->display_name : __('Non connecté', 'custom-size-plugin');
                        $user_email = $user_info ? $user_info->user_email : '--';
                    ?>
                        <tr>
                            <td><strong>#<?php echo esc_html($row['id']); ?></strong></td>
                            <td>
                                <div style="min-width: 200px;">
                                    <strong><?php echo esc_html($user_display); ?></strong><br>
                                    <small style="color:#666"><?php echo esc_html($user_email); ?></small><br>
                                    <small style="color:#999">ID: <?php echo esc_html($row['user_id']); ?></small>
                                </div>
                            </td>
                            <td><?php echo esc_html($data['genre'] ?? '--'); ?></td>
                            <td><?php echo esc_html($data['taille'] ?? '--'); ?> cm</td>
                            <td><?php echo esc_html($data['poids'] ?? '--'); ?> kg</td>
                            <td><?php echo esc_html($data['poitrine'] ?? '--'); ?> cm</td>
                            <td><?php echo esc_html($data['epaules'] ?? '--'); ?> cm</td>
                            <td><?php echo esc_html($data['tour_de_cou'] ?? '--'); ?> cm</td>
                            <td><?php echo esc_html($data['hanches'] ?? '--'); ?> cm</td>
                            <td><?php echo esc_html($data['longueur_jambe'] ?? '--'); ?> cm</td>
                            <td><?php echo esc_html($data['cuisse'] ?? '--'); ?> cm</td>
                            <td>
                                <small><?php echo esc_html(date('d/m/Y', strtotime($row['created_at']))); ?><br><?php echo esc_html(date('H:i', strtotime($row['created_at']))); ?></small>
                            </td>
                            <td>
                                <button type="button" class="button button-small csp-view-details"
                                    data-details="<?php echo esc_attr(wp_json_encode($data)); ?>">
                                    👁️ <?php echo esc_html__('Détails', 'custom-size-plugin'); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($total_pages > 1) : ?>
                <div class="tablenav">
                    <div class="tablenav-pages">
                        <?php
                        echo paginate_links(array(
                            'base'      => add_query_arg('paged', '%#%'),
                            'format'    => '',
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;',
                            'total'     => $total_pages,
                            'current'   => $current_page,
                        ));
                        ?>
                    </div>
                </div>
            <?php endif; ?>

        <?php else : ?>
            <div class="notice notice-info">
                <p>ℹ️ <?php echo esc_html__('Aucun profil enregistré pour le moment.', 'custom-size-plugin'); ?></p>
                <p><?php echo esc_html__('Les profils apparaîtront ici lorsque les clients utiliseront le système de mensurations.', 'custom-size-plugin'); ?></p>
            </div>
        <?php endif; ?>
    </div>

    <style>
        .csp-profiles-table th { font-weight: 600; }
        .csp-profiles-table td { vertical-align: top; }
        .tablenav { margin: 20px 0; }
        .tablenav-pages { float: right; }
        .csp-view-details { cursor: pointer; }
    </style>

    <script>
    jQuery(document).ready(function($) {
        $('.csp-view-details').on('click', function() {
            var details = $(this).data('details');
            var message = "<?php echo esc_html__('Détails du profil:', 'custom-size-plugin'); ?>\n\n";
            for (var key in details) {
                message += key + ": " + details[key] + "\n";
            }
            alert(message);
        });
    });
    </script>
    <?php
}

/* Page des réglages administration */
function csp_render_admin_settings()
{
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('Vous n\'avez pas les permissions nécessaires pour accéder à cette page.', 'custom-size-plugin'));
    }

    ?>
    <div class="wrap csp-settings-wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

        <?php
        if (isset($_GET['settings-updated'])) {
            add_settings_error('csp_messages', 'csp_message', __('Réglages sauvegardés avec succès.', 'custom-size-plugin'), 'success');
            
            // Nettoyage du cache après mise à jour des réglages
            delete_transient(CSP_TRANSIENT_PREFIX . 'dashboard_stats');
        }
        settings_errors('csp_messages');
        ?>

        <form action="options.php" method="post">
            <?php
            settings_fields('csp_settings_group');
            do_settings_sections('csp-settings');
            submit_button(__('Sauvegarder les réglages', 'custom-size-plugin'));
            ?>
        </form>

        <div class="csp-settings-section" style="background: #fff; padding: 20px; margin-top: 20px; border-radius: 4px; border-left: 4px solid #2271b1;">
            <h2>📋 <?php echo esc_html__('Aperçu de la configuration', 'custom-size-plugin'); ?></h2>
            <p><?php echo esc_html__('Plugin:', 'custom-size-plugin'); ?> <strong><?php echo csp_get_setting('enable_popup') ? '✅ ' . esc_html__('Activé', 'custom-size-plugin') : '❌ ' . esc_html__('Désactivé', 'custom-size-plugin'); ?></strong></p>
            <p><?php echo esc_html__('Bouton automatique:', 'custom-size-plugin'); ?> <strong><?php echo csp_get_setting('auto_button') ? '✅ ' . esc_html__('Activé', 'custom-size-plugin') : '❌ ' . esc_html__('Désactivé', 'custom-size-plugin'); ?></strong></p>
            <p><?php echo esc_html__('Champs requis:', 'custom-size-plugin'); ?> <strong><?php echo esc_html(implode(', ', csp_get_setting('required_fields', array()))); ?></strong></p>
            <p><?php echo esc_html__('Couleur principale:', 'custom-size-plugin'); ?>
                <span style="display: inline-block; width: 15px; height: 15px; background: <?php echo esc_attr(csp_get_setting('primary_color', '#2271b1')); ?>; border-radius: 3px; vertical-align: middle;"></span>
                <?php echo esc_html(csp_get_setting('primary_color', '#2271b1')); ?>
            </p>
        </div>
    </div>
    <?php
}



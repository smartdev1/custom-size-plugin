<?php

/**
 * Enqueue scripts and styles for the plugin
 */
function csp_enqueue_scripts() {
    // Enqueue CSS
    wp_enqueue_style(
        'csp-popup-style',
        plugin_dir_url(__FILE__) . 'assets/css/popup.css',
        array(),
        '1.0.0'
    );

    // Enqueue JavaScript
    wp_enqueue_script(
        'csp-popup-script',
        plugin_dir_url(__FILE__) . 'assets/js/popup.js',
        array('jquery'),
        '1.0.0',
        true
    );

    // Localiser le script pour AJAX
    wp_localize_script('csp-popup-script', 'csp_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('csp_ajax_nonce')
    ));
}
add_action('wp_enqueue_scripts', 'csp_enqueue_scripts');


/**
 * AJAX handler to load profile data
 */
function csp_ajax_load_profile() {
    check_ajax_referer('csp_ajax_nonce', 'nonce');

    if (!isset($_POST['profile_id'])) {
        wp_send_json_error('ID de profil manquant');
    }

    global $wpdb;
    $table = $wpdb->prefix . 'csp_measurements';
    $profile_id = intval($_POST['profile_id']);
    $user_id = get_current_user_id();

    $profile = $wpdb->get_row($wpdb->prepare(
        "SELECT raw_data FROM {$table} WHERE id = %d AND user_id = %d",
        $profile_id,
        $user_id
    ));

    if ($profile) {
        $data = maybe_unserialize($profile->raw_data);
        wp_send_json_success($data);
    } else {
        wp_send_json_error('Profil introuvable');
    }
}
add_action('wp_ajax_csp_load_profile', 'csp_ajax_load_profile');


/**
 * AJAX handler to save measurements
 */
function csp_ajax_save_measurements() {
    check_ajax_referer('csp_save_measurements', 'csp_nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error('Vous devez être connecté pour enregistrer vos mensurations');
    }

    global $wpdb;
    $table = $wpdb->prefix . 'csp_measurements';
    $user_id = get_current_user_id();

    // Récupérer toutes les données du formulaire
    $measurements = array();
    $fields = array(
        'taille_cou' => 'tour_de_cou',
        'epaules' => 'epaules',
        'poitrine' => 'poitrine',
        'hanches' => 'hanches',
        'longueur_jambe' => 'longueur_jambe',
        'cuisse' => 'cuisse',
        'poids' => 'poids',
        'taille' => 'taille',
        'genre' => 'genre'
    );

    foreach ($fields as $post_key => $data_key) {
        if (isset($_POST[$post_key]) && !empty($_POST[$post_key])) {
            $measurements[$data_key] = sanitize_text_field($_POST[$post_key]);
        }
    }

    if (empty($measurements)) {
        wp_send_json_error('Aucune mesure fournie');
    }

    // Insérer dans la base de données
    $result = $wpdb->insert(
        $table,
        array(
            'user_id' => $user_id,
            'product_id' => isset($_POST['product_id']) ? intval($_POST['product_id']) : 0,
            'raw_data' => maybe_serialize($measurements),
            'created_at' => current_time('mysql')
        ),
        array('%d', '%d', '%s', '%s')
    );

    if ($result) {
        wp_send_json_success(array(
            'message' => 'Mensurations enregistrées avec succès',
            'profile_id' => $wpdb->insert_id
        ));
    } else {
        wp_send_json_error('Erreur lors de l\'enregistrement');
    }
}
add_action('wp_ajax_csp_save_measurements', 'csp_ajax_save_measurements');


/**
 * Create database table on plugin activation
 */
function csp_create_measurements_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'csp_measurements';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        product_id bigint(20) DEFAULT 0,
        raw_data longtext NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY product_id (product_id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'csp_create_measurements_table');
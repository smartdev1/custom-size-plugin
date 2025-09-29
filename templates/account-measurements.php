<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;
$user_id = get_current_user_id();
$table   = $wpdb->prefix . 'csp_measurements';

$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC", $user_id ), ARRAY_A );
?>

<div class="woocommerce-MyAccount-content">
    <h3><?php esc_html_e( 'Mes mensurations enregistrées', 'custom-size-plugin' ); ?></h3>

    <?php if ( $rows ) : ?>
        <table class="shop_table shop_table_responsive my_account_orders">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Profil', 'custom-size-plugin' ); ?></th>
                    <th><?php esc_html_e( 'Détails', 'custom-size-plugin' ); ?></th>
                    <th><?php esc_html_e( 'Date', 'custom-size-plugin' ); ?></th>
                    <th><?php esc_html_e( 'Actions', 'custom-size-plugin' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $rows as $row ) :
                    $data = maybe_unserialize( $row['raw_data'] );
                ?>
                    <tr>
                        <td>#<?php echo esc_html( $row['id'] ); ?></td>
                        <td>
                            <?php if ( is_array( $data ) ) : ?>
                                <ul style="margin:0;padding-left:18px;">
                                    <?php foreach ( $data as $k => $v ) : ?>
                                        <li><strong><?php echo esc_html( ucfirst( str_replace( array('_','-'), ' ', $k ) ) ); ?>:</strong> <?php echo esc_html( $v ); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else : ?>
                                <?php echo esc_html( $row['raw_data'] ); ?>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html( $row['created_at'] ); ?></td>
                        <td>
                            <?php
                            $delete_url = wp_nonce_url( add_query_arg( array( 'action' => 'csp_delete', 'id' => $row['id'] ) ), 'csp_delete_' . $row['id'] );
                            ?>
                            <a href="<?php echo esc_url( $delete_url ); ?>" class="button"><?php esc_html_e( 'Supprimer', 'custom-size-plugin' ); ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else : ?>
        <p><?php esc_html_e( 'Vous n’avez encore enregistré aucun profil.', 'custom-size-plugin' ); ?></p>
    <?php endif; ?>

    <hr>

    <h3><?php esc_html_e( 'Ajouter un nouveau profil', 'custom-size-plugin' ); ?></h3>

    <form method="post">
        <?php wp_nonce_field( 'csp_add_profile', 'csp_nonce' ); ?>
        <p><label><?php esc_html_e( 'Taille', 'custom-size-plugin' ); ?> <input type="text" name="taille"></label></p>
        <p><label><?php esc_html_e( 'Poids', 'custom-size-plugin' ); ?> <input type="text" name="poids"></label></p>
        <p><label><?php esc_html_e( 'Tour de poitrine', 'custom-size-plugin' ); ?> <input type="text" name="poitrine"></label></p>
        <p><label><?php esc_html_e( 'Tour de taille / manche', 'custom-size-plugin' ); ?> <input type="text" name="taille_manche"></label></p>
        <p><label><?php esc_html_e( 'Hauteur', 'custom-size-plugin' ); ?> <input type="text" name="taille_hauteur"></label></p>
        <p><button type="submit" name="csp_add_profile" class="button"><?php esc_html_e( 'Enregistrer', 'custom-size-plugin' ); ?></button></p>
    </form>
</div>

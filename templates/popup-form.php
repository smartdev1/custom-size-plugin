<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div id="csp-overlay" style="display:none;"></div>
<div id="csp-modal" class="csp-modal" aria-hidden="true" style="display:none;">
  <div class="csp-modal-overlay"></div>
  <div class="csp-modal-content" role="dialog" aria-modal="true">
    <button class="csp-modal-close" aria-label="<?php esc_attr_e( 'Fermer', 'custom-size-plugin' ); ?>">&times;</button>

    <div class="csp-modal-inner">
      <h2><?php esc_html_e( 'VOS MESURES', 'custom-size-plugin' ); ?></h2>

      <?php if ( is_user_logged_in() ) : 
          global $wpdb;
          $table = $wpdb->prefix . 'csp_measurements';
          $user_id = get_current_user_id();
          $rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, raw_data FROM {$table} WHERE user_id = %d ORDER BY created_at DESC", $user_id ), ARRAY_A );
          if ( $rows ) : ?>
            <div class="csp-profile-select">
                <label for="csp-profile"><?php esc_html_e( 'Profil de mensurations', 'custom-size-plugin' ); ?></label>
                <select id="csp-profile">
                    <option value=""><?php esc_html_e( 'Par défaut', 'custom-size-plugin' ); ?></option>
                    <?php foreach ( $rows as $r ) : 
                        $raw = maybe_unserialize( $r['raw_data'] );
                        $label = sprintf( __( 'Profil #%d', 'custom-size-plugin' ), $r['id'] );
                        if ( is_array( $raw ) && isset( $raw['taille'] ) ) {
                            $label .= ' (T:' . esc_html( $raw['taille'] ) . ')';
                        }
                    ?>
                        <option value="<?php echo esc_attr( $r['id'] ); ?>"><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
          <?php endif; 
      endif; ?>

      <div class="csp-progress">
        <div class="csp-progressbar">
          <div class="csp-progress-fill" style="width:0%"></div>
        </div>
        <div class="csp-steps">
          <span class="csp-step active" data-label="<?php esc_attr_e( 'Haut du corps', 'custom-size-plugin' ); ?>">1</span>
          <span class="csp-step" data-label="<?php esc_attr_e( 'Bas du corps', 'custom-size-plugin' ); ?>">2</span>
          <span class="csp-step" data-label="<?php esc_attr_e( 'Général', 'custom-size-plugin' ); ?>">3</span>
          <span class="csp-step" data-label="<?php esc_attr_e( 'Confirmation', 'custom-size-plugin' ); ?>">4</span>
        </div>
      </div>

      <form id="csp-measurements-form" class="csp-form" method="post" novalidate>
        <?php wp_nonce_field( 'csp_save_measurements', 'csp_nonce' ); ?>

        <input type="hidden" name="product_id" id="csp-product-id" value="<?php echo esc_attr( get_the_ID() ); ?>">

        <!-- Body Diagram Section -->
        <div class="csp-body-diagram">
          <div class="csp-body-figures">
            <!-- Front View -->
            <div class="csp-body-figure front">
              <svg viewBox="0 0 100 300" xmlns="http://www.w3.org/2000/svg">
                <!-- Head -->
                <ellipse cx="50" cy="25" rx="12" ry="15" fill="none" stroke="#ddd" stroke-width="1.5"/>
                <!-- Body -->
                <line x1="50" y1="40" x2="50" y2="120" stroke="#ddd" stroke-width="1.5"/>
                <!-- Arms -->
                <line x1="50" y1="60" x2="25" y2="90" stroke="#ddd" stroke-width="1.5"/>
                <line x1="50" y1="60" x2="75" y2="90" stroke="#ddd" stroke-width="1.5"/>
                <!-- Legs -->
                <line x1="50" y1="120" x2="35" y2="200" stroke="#ddd" stroke-width="1.5"/>
                <line x1="50" y1="120" x2="65" y2="200" stroke="#ddd" stroke-width="1.5"/>
                <line x1="35" y1="200" x2="35" y2="280" stroke="#ddd" stroke-width="1.5"/>
                <line x1="65" y1="200" x2="65" y2="280" stroke="#ddd" stroke-width="1.5"/>
              </svg>
              <div class="csp-measurement-point">1</div>
              <div class="csp-measurement-point">2</div>
              <div class="csp-measurement-point">3</div>
              <div class="csp-measurement-point">4</div>
              <div class="csp-measurement-point">5</div>
              <div class="csp-measurement-point">6</div>
              <div class="csp-measurement-point">7</div>
              <div class="csp-measurement-point">8</div>
            </div>

            <!-- Back View -->
            <div class="csp-body-figure back">
              <svg viewBox="0 0 100 300" xmlns="http://www.w3.org/2000/svg">
                <!-- Head -->
                <ellipse cx="50" cy="25" rx="12" ry="15" fill="none" stroke="#ddd" stroke-width="1.5"/>
                <!-- Body -->
                <line x1="50" y1="40" x2="50" y2="120" stroke="#ddd" stroke-width="1.5"/>
                <!-- Arms -->
                <line x1="50" y1="60" x2="25" y2="90" stroke="#ddd" stroke-width="1.5"/>
                <line x1="50" y1="60" x2="75" y2="90" stroke="#ddd" stroke-width="1.5"/>
                <!-- Legs -->
                <line x1="50" y1="120" x2="35" y2="200" stroke="#ddd" stroke-width="1.5"/>
                <line x1="50" y1="120" x2="65" y2="200" stroke="#ddd" stroke-width="1.5"/>
                <line x1="35" y1="200" x2="35" y2="280" stroke="#ddd" stroke-width="1.5"/>
                <line x1="65" y1="200" x2="65" y2="280" stroke="#ddd" stroke-width="1.5"/>
              </svg>
              <div class="csp-measurement-point">9</div>
              <div class="csp-measurement-point">10</div>
              <div class="csp-measurement-point">11</div>
              <div class="csp-measurement-point">12</div>
              <div class="csp-measurement-point">13</div>
              <div class="csp-measurement-point">14</div>
            </div>
          </div>

          <div class="csp-info-box">
            <h3><?php esc_html_e( 'Dimensions et valeurs de couture', 'custom-size-plugin' ); ?></h3>
            <p><?php esc_html_e( 'Pour assurer un ajustement correct, veuillez fournir vos mesures en remplissant le formulaire', 'custom-size-plugin' ); ?></p>
          </div>
        </div>

        <!-- Form Section -->
        <div class="csp-form-section">
          <div class="csp-step-content active" data-step="1">
            <h3><?php esc_html_e( 'Sélectionner les informations sur vos mesures', 'custom-size-plugin' ); ?></h3>
            <label>
              <?php esc_html_e( 'Tour de cou', 'custom-size-plugin' ); ?>
              <input type="text" name="taille_cou" data-key="tour_de_cou" placeholder="<?php esc_attr_e( 'Entrez les mesures de votre tour de cou', 'custom-size-plugin' ); ?>" />
            </label>
            <label>
              <?php esc_html_e( 'Largeur d\'épaules', 'custom-size-plugin' ); ?>
              <input type="text" name="epaules" data-key="epaules" placeholder="<?php esc_attr_e( 'Entrez la largeur d\'épaules', 'custom-size-plugin' ); ?>" />
            </label>
            <label>
              <?php esc_html_e( 'Tour de poitrine', 'custom-size-plugin' ); ?>
              <input type="text" name="poitrine" data-key="poitrine" placeholder="<?php esc_attr_e( 'Entrez votre tour de poitrine', 'custom-size-plugin' ); ?>" />
            </label>
          </div>

          <div class="csp-step-content" data-step="2">
            <h3><?php esc_html_e( 'Bas du corps', 'custom-size-plugin' ); ?></h3>
            <label>
              <?php esc_html_e( 'Tour de hanches', 'custom-size-plugin' ); ?>
              <input type="text" name="hanches" data-key="hanches" placeholder="<?php esc_attr_e( 'Entrez votre tour de hanches', 'custom-size-plugin' ); ?>" />
            </label>
            <label>
              <?php esc_html_e( 'Longueur jambe', 'custom-size-plugin' ); ?>
              <input type="text" name="longueur_jambe" data-key="longueur_jambe" placeholder="<?php esc_attr_e( 'Entrez la longueur de jambe', 'custom-size-plugin' ); ?>" />
            </label>
            <label>
              <?php esc_html_e( 'Tour de cuisse', 'custom-size-plugin' ); ?>
              <input type="text" name="cuisse" data-key="cuisse" placeholder="<?php esc_attr_e( 'Entrez votre tour de cuisse', 'custom-size-plugin' ); ?>" />
            </label>
          </div>

          <div class="csp-step-content" data-step="3">
            <h3><?php esc_html_e( 'Général', 'custom-size-plugin' ); ?></h3>
            <label>
              <?php esc_html_e( 'Poids (kg)', 'custom-size-plugin' ); ?>
              <input type="text" name="poids" data-key="poids" placeholder="<?php esc_attr_e( 'Entrez votre poids', 'custom-size-plugin' ); ?>" />
            </label>
            <label>
              <?php esc_html_e( 'Taille totale (cm)', 'custom-size-plugin' ); ?>
              <input type="text" name="taille" data-key="taille" placeholder="<?php esc_attr_e( 'Entrez votre taille', 'custom-size-plugin' ); ?>" />
            </label>
            <label>
              <?php esc_html_e( 'Genre / sexe', 'custom-size-plugin' ); ?>
              <select name="genre" data-key="genre">
                <option value=""><?php esc_html_e( '-- Sélectionner --', 'custom-size-plugin' ); ?></option>
                <option value="femme"><?php esc_html_e( 'Femme', 'custom-size-plugin' ); ?></option>
                <option value="homme"><?php esc_html_e( 'Homme', 'custom-size-plugin' ); ?></option>
                <option value="autre"><?php esc_html_e( 'Autre', 'custom-size-plugin' ); ?></option>
              </select>
            </label>
          </div>

          <div class="csp-step-content" data-step="4">
            <h3><?php esc_html_e( 'Confirmation', 'custom-size-plugin' ); ?></h3>
            <div id="csp-summary"></div>
            <p><?php esc_html_e( 'Cliquer sur Valider pour enregistrer vos mensurations.', 'custom-size-plugin' ); ?></p>
          </div>

          <div class="csp-nav">
            <button type="button" class="csp-prev button" style="display:none;"><?php esc_html_e( 'Précédent', 'custom-size-plugin' ); ?></button>
            <button type="button" class="csp-next button"><?php esc_html_e( 'Suivant', 'custom-size-plugin' ); ?></button>
            <button type="button" class="csp-submit button" style="display:none;"><?php esc_html_e( 'Valider mes mensurations', 'custom-size-plugin' ); ?></button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>
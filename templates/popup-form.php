<?php
if (! defined('ABSPATH')) {
  exit;
}
?>
<div id="csp-overlay" style="display:none;"></div>
<div id="csp-modal" class="csp-modal" aria-hidden="true" style="display:none;">
  <div class="csp-modal-overlay"></div>
  <div class="csp-modal-content" role="dialog" aria-modal="true">
    <button class="csp-modal-close" aria-label="<?php esc_attr_e('Fermer', 'custom-size-plugin'); ?>">&times;</button>

    <div class="csp-modal-inner">
      <h2><?php esc_html_e('VOS MESURES', 'custom-size-plugin'); ?></h2>

      <div class="csp-progress">
        <div class="csp-progressbar">
          <div class="csp-progress-fill" style="width:0%"></div>
        </div>
        <div class="csp-steps">
          <span class="csp-step active" data-label="<?php esc_attr_e('Haut du corps', 'custom-size-plugin'); ?>">1</span>
          <span class="csp-step" data-label="<?php esc_attr_e('Bas du corps', 'custom-size-plugin'); ?>">2</span>
          <span class="csp-step" data-label="<?php esc_attr_e('Général', 'custom-size-plugin'); ?>">3</span>
          <span class="csp-step" data-label="<?php esc_attr_e('Confirmation', 'custom-size-plugin'); ?>">4</span>
        </div>
      </div>

      <form id="csp-measurements-form" class="csp-form" method="post" novalidate>
        <?php wp_nonce_field('csp_save_measurements', 'csp_nonce'); ?>
        <input type="hidden" name="product_id" id="csp-product-id" value="<?php echo esc_attr(get_the_ID()); ?>">

        <!-- Section du diagramme corporel -->
        <div class="csp-body-diagram">
          <div class="csp-body-figures">

            <!-- Vue de face -->
            <div class="csp-body-figure front">
              <img src="<?php echo esc_url(CSP_PLUGIN_URL . 'assets/img/corps-back.png'); ?>" alt="Vue avant" class="csp-body-image" />
              <!-- <div class="csp-measurement-point p1" data-point="1">1</div>
              <div class="csp-measurement-point p2" data-point="2">2</div>
              <div class="csp-measurement-point p3" data-point="3">3</div>
              <div class="csp-measurement-point p4" data-point="4">4</div>
              <div class="csp-measurement-point p5" data-point="5">5</div>
              <div class="csp-measurement-point p6" data-point="6">6</div>
              <div class="csp-measurement-point p7" data-point="7">7</div>
              <div class="csp-measurement-point p8" data-point="8">8</div> -->
            </div>

            <!-- Vue de dos -->
            <div class="csp-body-figure back">
              <img src="<?php echo esc_url(CSP_PLUGIN_URL . 'assets/img/corps-front.png'); ?>" alt="Vue arrière" class="csp-body-image" />
              <!-- <div class="csp-measurement-point p9" data-point="9">9</div>
              <div class="csp-measurement-point p10" data-point="10">10</div>
              <div class="csp-measurement-point p11" data-point="11">11</div>
              <div class="csp-measurement-point p12" data-point="12">12</div>
              <div class="csp-measurement-point p13" data-point="13">13</div>
              <div class="csp-measurement-point p14" data-point="14">14</div> -->
            </div>

          </div>

          <div class="csp-info-box">
            <h3><?php esc_html_e('Dimensions et valeurs de couture', 'custom-size-plugin'); ?></h3>
            <p><?php esc_html_e('Pour assurer un ajustement correct, veuillez fournir vos mesures en remplissant le formulaire', 'custom-size-plugin'); ?></p>
          </div>
        </div>

        <!-- Étapes du formulaire -->
        <div class="csp-form-section">
          <div class="csp-step-content active" data-step="1">
            <h3><?php esc_html_e('Haut du corps', 'custom-size-plugin'); ?></h3>
            <label><?php esc_html_e('Tour de cou', 'custom-size-plugin'); ?>
              <input type="text" name="taille_cou" data-key="tour_de_cou" />
            </label>
            <label><?php esc_html_e('Tour de poitrine', 'custom-size-plugin'); ?>
              <input type="text" name="poitrine" data-key="poitrine" />
            </label>
            <label><?php esc_html_e('Largeur d\'épaules', 'custom-size-plugin'); ?>
              <input type="text" name="epaules" data-key="epaules" />
            </label>
          </div>

          <div class="csp-step-content" data-step="2">
            <h3><?php esc_html_e('Bas du corps', 'custom-size-plugin'); ?></h3>
            <label><?php esc_html_e('Tour de hanches', 'custom-size-plugin'); ?>
              <input type="text" name="hanches" data-key="hanches" />
            </label>
            <label><?php esc_html_e('Tour de cuisse', 'custom-size-plugin'); ?>
              <input type="text" name="cuisse" data-key="cuisse" />
            </label>
            <label><?php esc_html_e('Longueur jambe', 'custom-size-plugin'); ?>
              <input type="text" name="longueur_jambe" data-key="longueur_jambe" />
            </label>
          </div>

          <div class="csp-step-content" data-step="3">
            <h3><?php esc_html_e('Général', 'custom-size-plugin'); ?></h3>
            <label><?php esc_html_e('Poids (kg)', 'custom-size-plugin'); ?>
              <input type="text" name="poids" data-key="poids" />
            </label>
            <label><?php esc_html_e('Taille totale (cm)', 'custom-size-plugin'); ?>
              <input type="text" name="taille" data-key="taille" />
            </label>
            <label><?php esc_html_e('Genre / sexe', 'custom-size-plugin'); ?>
              <select name="genre" data-key="genre">
                <option value=""><?php esc_html_e('-- Sélectionner --', 'custom-size-plugin'); ?></option>
                <option value="femme"><?php esc_html_e('Femme', 'custom-size-plugin'); ?></option>
                <option value="homme"><?php esc_html_e('Homme', 'custom-size-plugin'); ?></option>
              </select>
            </label>
          </div>

          <div class="csp-step-content" data-step="4">
            <h3><?php esc_html_e('Confirmation', 'custom-size-plugin'); ?></h3>
            <div id="csp-summary"></div>
            <p><?php esc_html_e('Cliquer sur Valider pour enregistrer vos mensurations.', 'custom-size-plugin'); ?></p>
          </div>

          <div class="csp-nav">
            <button type="button" class="csp-prev button" style="display:none;"><?php esc_html_e('Précédent', 'custom-size-plugin'); ?></button>
            <button type="button" class="csp-next button" disabled><?php esc_html_e('Suivant', 'custom-size-plugin'); ?></button>
            <button type="button" class="csp-submit button" style="display:none;"><?php esc_html_e('Valider mes mensurations', 'custom-size-plugin'); ?></button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    // Sélection des éléments
    const nextButtons = document.querySelectorAll('.csp-next');
    const prevButtons = document.querySelectorAll('.csp-prev');
    const submitButton = document.querySelector('.csp-submit');
    const stepContents = document.querySelectorAll('.csp-step-content');
    const errorMessage = document.getElementById('csp-error-message') || document.createElement('div');

    // Désactiver le bouton "Suivant" par défaut
    nextButtons.forEach(button => {
      button.disabled = true;
    });

    // Fonction pour vérifier si tous les champs d'une étape sont remplis
    function areAllFieldsFilled(stepIndex) {
      const currentStep = stepContents[stepIndex];
      const inputs = currentStep.querySelectorAll('input[type="text"], select');
      let allFilled = true;

      inputs.forEach(input => {
        if (!input.value.trim() && input.hasAttribute('name')) {
          allFilled = false;
        }
      });

      return allFilled;
    }

    // Fonction pour activer/désactiver le bouton "Suivant"
    function toggleNextButton(stepIndex) {
      const nextButton = document.querySelector('.csp-next');
      if (areAllFieldsFilled(stepIndex)) {
        nextButton.disabled = false;
      } else {
        nextButton.disabled = true;
      }
    }

    // Écouter les changements dans les champs de chaque étape
    stepContents.forEach((step, stepIndex) => {
      const inputs = step.querySelectorAll('input[type="text"], select');
      inputs.forEach(input => {
        input.addEventListener('input', () => {
          toggleNextButton(stepIndex);
        });
      });
    });

    // Gestion du clic sur le bouton "Suivant"
    nextButtons.forEach(button => {
      button.addEventListener('click', function() {
        const currentStepIndex = Array.from(stepContents).findIndex(step => step.classList.contains('active'));

        // Passer à l'étape suivante
        stepContents[currentStepIndex].classList.remove('active');
        stepContents[currentStepIndex + 1].classList.add('active');

        // Mettre à jour la barre de progression
        updateProgressBar(currentStepIndex + 1);

        // Gérer l'affichage des boutons "Précédent" et "Suivant"
        if (currentStepIndex + 1 === stepContents.length - 1) {
          nextButtons.forEach(btn => btn.style.display = 'none');
          submitButton.style.display = 'inline-block';
        }
        prevButtons.forEach(btn => btn.style.display = 'inline-block');

        // Désactiver le bouton "Suivant" de la nouvelle étape par défaut
        toggleNextButton(currentStepIndex + 1);
      });
    });

    // Gestion du clic sur le bouton "Précédent"
    prevButtons.forEach(button => {
      button.addEventListener('click', function() {
        const currentStepIndex = Array.from(stepContents).findIndex(step => step.classList.contains('active'));
        stepContents[currentStepIndex].classList.remove('active');
        stepContents[currentStepIndex - 1].classList.add('active');

        // Mettre à jour la barre de progression
        updateProgressBar(currentStepIndex - 1);

        // Gérer l'affichage des boutons "Précédent" et "Suivant"
        if (currentStepIndex - 1 === 0) {
          prevButtons.forEach(btn => btn.style.display = 'none');
        }
        nextButtons.forEach(btn => btn.style.display = 'inline-block');
        submitButton.style.display = 'none';

        // Réactiver le bouton "Suivant" si tous les champs de l'étape précédente sont remplis
        toggleNextButton(currentStepIndex - 1);
      });
    });

    // Fonction pour mettre à jour la barre de progression
    function updateProgressBar(currentStepIndex) {
      const progressFill = document.querySelector('.csp-progress-fill');
      const steps = document.querySelectorAll('.csp-step');
      const progressPercentage = (currentStepIndex / (steps.length - 1)) * 100;
      progressFill.style.width = progressPercentage + '%';

      steps.forEach((step, index) => {
        if (index <= currentStepIndex) {
          step.classList.add('active');
        } else {
          step.classList.remove('active');
        }
      });
    }

    // Initialiser l'état du bouton "Suivant" pour la première étape
    toggleNextButton(0);
  });
</script>
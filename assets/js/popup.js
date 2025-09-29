document.addEventListener("DOMContentLoaded", function () {
    const overlay = document.getElementById("csp-overlay");
    const modal = document.getElementById("csp-modal");
    const closeBtn = document.querySelector(".csp-modal-close");
    const form = document.getElementById("csp-measurements-form");
    const stepContents = document.querySelectorAll(".csp-step-content");
    const stepCircles = document.querySelectorAll(".csp-steps .csp-step");
    const progressFill = document.querySelector(".csp-progress-fill");
    const prevBtn = document.querySelector(".csp-prev");
    const nextBtn = document.querySelector(".csp-next");
    const submitBtn = document.querySelector(".csp-submit");
    const summaryDiv = document.getElementById("csp-summary");
    
    let currentStep = 1;
    const totalSteps = 4;

    // Fonction pour afficher une étape
    function showStep(step) {
        currentStep = step;

        // Masquer tous les contenus d'étape
        stepContents.forEach(content => {
            content.classList.remove("active");
            content.style.display = "none";
        });

        // Afficher le contenu de l'étape actuelle
        const currentContent = document.querySelector(`.csp-step-content[data-step="${step}"]`);
        if (currentContent) {
            currentContent.classList.add("active");
            currentContent.style.display = "flex";
        }

        // Mettre à jour les cercles de progression
        stepCircles.forEach((circle, index) => {
            circle.classList.remove("active", "completed");
            if (index + 1 < step) {
                circle.classList.add("completed");
            } else if (index + 1 === step) {
                circle.classList.add("active");
            }
        });

        // Mettre à jour la barre de progression
        const progressPercent = ((step - 1) / (totalSteps - 1)) * 100;
        if (progressFill) {
            progressFill.style.width = progressPercent + "%";
        }

        // Gérer l'affichage des boutons
        if (step === 1) {
            prevBtn.style.display = "none";
            nextBtn.style.display = "inline-block";
            submitBtn.style.display = "none";
        } else if (step === totalSteps) {
            prevBtn.style.display = "inline-block";
            nextBtn.style.display = "none";
            submitBtn.style.display = "inline-block";
            generateSummary();
        } else {
            prevBtn.style.display = "inline-block";
            nextBtn.style.display = "inline-block";
            submitBtn.style.display = "none";
        }
    }

    // Fonction pour générer le résumé
    function generateSummary() {
        if (!summaryDiv) return;

        let html = '<ul>';
        const inputs = form.querySelectorAll("[data-key]");
        
        inputs.forEach(input => {
            const value = input.value.trim();
            if (value) {
                const label = input.closest('label');
                let labelText = '';
                
                if (label) {
                    // Extraire le texte du label sans le contenu de l'input
                    labelText = label.textContent.replace(value, '').trim();
                } else {
                    labelText = input.getAttribute('data-key');
                }
                
                html += `<li><strong>${labelText}</strong> <span>${value}</span></li>`;
            }
        });
        
        html += '</ul>';
        summaryDiv.innerHTML = html;
    }

    // Fonction pour ouvrir le modal
    function openModal() {
        overlay.style.display = "block";
        modal.style.display = "block";
        document.body.style.overflow = "hidden";
        currentStep = 1;
        showStep(currentStep);
    }

    // Fonction pour fermer le modal
    function closeModal() {
        overlay.style.display = "none";
        modal.style.display = "none";
        document.body.style.overflow = "";
    }

    // Fonction de validation basique
    function validateStep(step) {
        const currentContent = document.querySelector(`.csp-step-content[data-step="${step}"]`);
        if (!currentContent) return true;

        let isValid = true;
        const requiredInputs = currentContent.querySelectorAll('input[required], select[required]');
        
        requiredInputs.forEach(input => {
            if (!input.value.trim()) {
                input.classList.add('error');
                isValid = false;
            } else {
                input.classList.remove('error');
            }
        });

        return isValid;
    }

    // Enlever les erreurs lors de la saisie
    document.addEventListener('input', function(e) {
        if (e.target.matches('input, select')) {
            e.target.classList.remove('error');
        }
    });

    // Ouvrir modal
    document.addEventListener("click", function(e) {
        if (e.target.matches(".csp-open-modal")) {
            e.preventDefault();
            openModal();
        }
    });

    // Fermer modal - Bouton X
    if (closeBtn) {
        closeBtn.addEventListener("click", function(e) {
            e.preventDefault();
            closeModal();
        });
    }

    // Fermer modal - Clic sur overlay
    if (overlay) {
        overlay.addEventListener("click", function(e) {
            if (e.target === overlay) {
                closeModal();
            }
        });
    }

    // Fermer modal - Touche Escape
    document.addEventListener("keydown", function(e) {
        if (e.key === "Escape" && modal.style.display === "block") {
            closeModal();
        }
    });

    // Bouton Suivant
    if (nextBtn) {
        nextBtn.addEventListener("click", function(e) {
            e.preventDefault();
            
            if (validateStep(currentStep) && currentStep < totalSteps) {
                showStep(currentStep + 1);
            }
        });
    }

    // Bouton Précédent
    if (prevBtn) {
        prevBtn.addEventListener("click", function(e) {
            e.preventDefault();
            
            if (currentStep > 1) {
                showStep(currentStep - 1);
            }
        });
    }

    // Charger un profil existant
    document.addEventListener('change', function(e) {
        if (e.target.id === 'csp-profile') {
            const profileId = e.target.value;
            
            if (!profileId) {
                form.querySelectorAll('[data-key]').forEach(inp => inp.value = '');
                return;
            }

            fetch(window.csp_ajax_obj.ajax_url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: new URLSearchParams({
                    action: 'csp_get_profile',
                    security: window.csp_ajax_obj.nonce,
                    profile_id: profileId
                }).toString()
            })
            .then(r => r.json())
            .then(res => {
                if (res.success && res.data) {
                    const data = res.data;
                    form.querySelectorAll('[data-key]').forEach(inp => {
                        const key = inp.getAttribute('data-key');
                        if (data[key]) {
                            inp.value = data[key];
                        }
                    });
                }
            })
            .catch(err => {
                console.error('Erreur lors du chargement du profil:', err);
            });
        }
    });

    // Soumission du formulaire
    if (submitBtn) {
        submitBtn.addEventListener("click", function(e) {
            e.preventDefault();

            const data = {};
            form.querySelectorAll("[data-key]").forEach(input => {
                const key = input.getAttribute("data-key");
                const value = input.value.trim();
                if (value) {
                    data[key] = value;
                }
            });

            const productId = document.getElementById("csp-product-id")?.value || "";

            // Désactiver le bouton pendant l'envoi
            submitBtn.disabled = true;
            submitBtn.textContent = "Enregistrement...";

            fetch(window.csp_ajax_obj.ajax_url, {
                method: "POST",
                credentials: "same-origin",
                headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
                body: new URLSearchParams({
                    action: "csp_save_measurements",
                    security: window.csp_ajax_obj.nonce,
                    product_id: productId,
                    measurements: JSON.stringify(data)
                }).toString()
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    alert("Vos mensurations ont été enregistrées avec succès !");
                    closeModal();
                    form.reset();
                } else {
                    alert("Erreur : " + (res.data?.message || "Une erreur est survenue"));
                }
            })
            .catch(err => {
                alert("Erreur réseau : " + err.message);
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.textContent = "Valider mes mensurations";
            });
        });
    }

    // Initialiser la première étape
    showStep(1);
});
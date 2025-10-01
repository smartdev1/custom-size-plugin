# Custom Size Plugin

**Custom Size Plugin** est une extension WordPress/WooCommerce qui permet aux clients de saisir, enregistrer et gérer leurs mensurations pour des vêtements sur mesure.  
Il améliore l’expérience utilisateur grâce à un popup multi-étapes, la sauvegarde automatique des mesures et une intégration complète avec WooCommerce.

---

##  Fonctionnalités

### Front-Office (Clients)
- Popup **multi-étapes** (haut du corps, bas du corps, général, confirmation).
- Pré-remplissage automatique si des mesures existent déjà.
- Sauvegarde **locale (localStorage)** pour les utilisateurs non connectés.
- Synchronisation avec le compte lors de la connexion ou création d’un compte.
- Intégration WooCommerce :
  - Bouton automatique sur les fiches produits.
  - Affichage des mensurations dans le panier et les commandes.
  - Page **Mon Compte → Mes mensurations**.

### Back-Office (Admin)
- **Tableau de bord** avec statistiques (profils totaux, du jour, du mois, utilisateurs actifs).
- **Liste des profils clients** avec pagination, détails rapides et suppression.
- **Réglages du plugin** :
  - Activer/désactiver le popup.
  - Texte du bouton et titre du popup.
  - Champs obligatoires.
  - Couleur principale.
  - Comportement du panier (fusion ou séparation).
  - Affichage des mesures dans panier et commandes.

---

##  Installation

1. Télécharger `custom-size-plugin.zip`.
2. Dans WordPress : **Extensions → Ajouter → Téléverser une extension**.
3. Sélectionner le fichier ZIP et cliquer sur **Installer**.
4. Activer le plugin.
5. Le menu **Custom Size** apparaît dans l’administration.

---

##  Utilisation

### Pour les clients :
1. Sur une fiche produit, cliquer sur **Ajouter vos mensurations**.
2. Remplir le formulaire en 4 étapes.
3. Valider les mensurations.
4. Les mesures sont associées au produit dans le panier et sauvegardées pour les prochaines commandes.

### Pour les administrateurs :
- **Custom Size → Tableau de bord** : Vue d’ensemble.
- **Custom Size → Profils clients** : Gestion des mensurations.
- **Custom Size → Réglages** : Configuration du plugin.

---

##  Dépendances

- WordPress **6.0+**
- WooCommerce **7.0+**
- PHP **7.4 – 8.2**
- Compatible multisite

---

##  Sécurité & Performance

- Toutes les requêtes AJAX protégées par **nonce**.
- Données utilisateurs stockées dans une table dédiée : `wp_csp_measurements`.
- Validation et nettoyage des champs utilisateur.
- Mise en cache des statistiques admin avec **transients**.

---

##  Hooks & Extensibilité

### Actions
- `csp_after_save_measurements` — exécuté après la sauvegarde d’un profil.

### Filtres
- `csp_get_setting` — surcharge des réglages par défaut.
- `woocommerce_get_item_data` — personnalisation de l’affichage dans le panier.

---

##  FAQ

**Q : Que se passe-t-il si un client n’est pas connecté ?**  
 Ses mesures sont sauvegardées dans le localStorage et proposées pour synchronisation lors de sa connexion.

**Q : Peut-on gérer plusieurs profils de mensurations ?**  
 Par défaut, le dernier profil est utilisé. L’option multi-profils est prévue pour de futures évolutions.

**Q : Est-ce que le plugin modifie le checkout WooCommerce ?**  
 Non, il enrichit uniquement les données liées aux produits.

---


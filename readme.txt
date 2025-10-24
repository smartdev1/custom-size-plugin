=== Custom Size Plugin ===
Contributors: aristideghislain
Tags: woocommerce, measurements, customization, user-profiles
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Gestion avancée des mensurations clients pour WooCommerce : popup multi-étapes, sauvegarde, profils réutilisables, et intégration avec Mon Compte.

== Description ==

Ce plugin permet aux clients de saisir et sauvegarder leurs mensurations (taille, poids, tour de poitrine, etc.) via un popup interactif. Les données sont associées à leur compte et peuvent être réutilisées pour des commandes futures.

**Fonctionnalités :**
- Popup multi-étapes pour une saisie guidée.
- Sauvegarde des mensurations dans la base de données.
- Intégration avec WooCommerce (affichage dans le panier et les commandes).
- Gestion des profils dans "Mon Compte".
- Stockage temporaire pour les visiteurs non connectés (via localStorage).
- Synchronisation automatique à la connexion.

== Installation ==

1. Téléchargez le dossier `custom-size-plugin` depuis le dépôt.
2. Dans votre admin WordPress, allez dans **Extensions > Ajouter**.
3. Cliquez sur **Télécharger une extension** et sélectionnez le fichier ZIP du plugin.
4. Activez le plugin via le menu **Extensions**.

**OU**

1. Décompressez le dossier `custom-size-plugin` dans `/wp-content/plugins/`.
2. Activez le plugin via le menu **Extensions** dans WordPress.

== Frequently Asked Questions ==

= Comment ajouter le bouton sur une page personnalisée ? =
Utilisez le shortcode `[csp_add_measurements]`.

= Les mensurations sont-elles sécurisées ? =
Oui, les données sont stockées dans la base de données WordPress et protégées par les mécanismes de sécurité standard.

= Puis-je personnaliser les champs de mensuration ? =
Oui, via les réglages du plugin dans **Custom Size > Réglages**.

== Screenshots ==

1. Popup de saisie des mensurations.
2. Affichage des mensurations dans le panier WooCommerce.
3. Page "Mes mensurations" dans Mon Compte.
4. Tableau de bord admin avec statistiques.

== Changelog ==

= 1.3.0 =
- Ajout du stockage temporaire pour les visiteurs non connectés.
- Synchronisation automatique à la connexion.
- Amélioration de l'interface utilisateur.

= 1.2.0 =
- Intégration avec WooCommerce 7.0+.
- Correction de bugs mineurs.

= 1.0.0 =
- Version initiale.

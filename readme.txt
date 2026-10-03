=== Websource Précommandes ===
Contributors: websource
Tags: woocommerce, précommande, pre-order, compte à rebours, alertes e-mail
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: Licence Websource, usage libre non commercial
License URI: https://github.com/Websource-fr/wp-websource-preorder/blob/main/LICENSE
Requires Plugins: woocommerce

Système complet de précommandes pour WooCommerce : compte à rebours, prix de précommande, quotas, bandeau défilant, alertes e-mail et récapitulatif périodique.

== Description ==

Websource Précommandes permet de vendre un produit **avant sa sortie**. Pour chaque produit (ou variation), vous créez une règle de précommande avec une date de sortie. Tant que la règle est en cours, le produit peut être commandé hors stock, le prix de précommande est appliqué, le bouton devient « Précommander » et chaque ligne de commande est enregistrée comme réservation. À la sortie, l'état d'origine du produit est restauré et les acheteurs reçoivent l'e-mail « produit disponible ».

**Ce plugin nécessite WooCommerce.** Sans WooCommerce actif, il affiche une notice et ne fait rien d'autre.

**Fonctionnalités**

* Règles par produit ou variation : début, date de sortie, clôture automatique, fin anticipée, statuts Programmée / En cours / Terminée, bouton « Disponible maintenant ».
* Prix de précommande (prix fixe, remise en pourcentage ou en montant) appliqué par les filtres de prix WooCommerce : le prix du produit n'est jamais modifié.
* Commande hors stock pendant la précommande, puis restauration exacte de l'état d'origine (précommandes autorisées, statut de stock).
* Quota total, maximum par commande et par client, option pour interdire le mélange précommandes / produits en stock.
* Réservations enregistrées dans une table dédiée, libération du quota en cas d'annulation ou de remboursement, export CSV, renvoi d'e-mail.
* Compte à rebours (automatique, jours, jours+heures, jours+heures+minutes, avec secondes), barre de progression, quantité restante.
* Couleurs entièrement paramétrables (9 couleurs).
* Produit à la une en page d'accueil, bandeau défilant (haut du site, avant le pied de page ou haut de l'accueil), page « Toutes les précommandes » groupée par mois.
* Blocs sur les pages connexion, inscription et mot de passe perdu (Mon compte WooCommerce et wp-login.php).
* Alertes e-mail avec double opt-in, case RGPD, anti-spam (honeypot, délai, nonce) et désinscription en un clic.
* Récapitulatif périodique par WP-Cron ou cron serveur (URL sécurisée par jeton), envoi par lots avec reprise.
* E-mails personnalisables par locale, variables {firstname}, {order_reference}, {products_html}…, bouton d'envoi de test.
* Page « Mes précommandes » dans Mon compte.
* Compatible thèmes de blocs et thèmes classiques, panier et commande classiques ou en blocs. Gabarits surchargeables dans `votre-theme/websource-preorder/`.
* Shortcodes `[websource_preorders]`, `[websource_preorder_featured]`, `[websource_preorder_ticker]`, `[websource_preorder_subscribe]`, blocs Gutenberg et widget.

= Encart d'accompagnement =

L'écran principal affiche aux administrateurs un encart discret (masquable 30 jours) proposant l'accompagnement de Websource. Aucune requête externe : au clic seulement, le nom du plugin, sa version, la version de WordPress et le domaine du site sont ajoutés à l'adresse du lien.

== Installation ==

1. Installez et activez WooCommerce.
2. Téléversez le dossier `websource-preorder` dans `wp-content/plugins/` (ou installez le zip depuis Extensions > Ajouter) puis activez le plugin.
3. Ouvrez **Précommandes > Nouvelle précommande**, choisissez un produit, renseignez la date de sortie et enregistrez.
4. Ajustez l'apparence et les options dans **Précommandes > Réglages**.

== Frequently Asked Questions ==

= Le prix du produit est-il modifié ? =

Non. Le prix de précommande est appliqué dynamiquement par les filtres de prix de WooCommerce et disparaît automatiquement à la clôture.

= Que devient le stock à la clôture ? =

Les réglages d'origine du produit (gestion du stock, précommandes autorisées, statut de stock) sont mémorisés au démarrage puis restaurés exactement à la clôture.

= Faut-il un vrai cron ? =

Pas obligatoirement : WP-Cron suffit. Pour une exécution à l'heure exacte, utilisez l'URL de cron affichée dans Réglages > Cron & récapitulatif.

= Comment supprimer toutes les données ? =

Décochez « Conserver les données à la désinstallation » dans Réglages > Général avant de supprimer le plugin.

== Changelog ==

= 1.0.0 =
* Première version : port WordPress / WooCommerce du module PrestaShop Websource Précommandes.
* Encart « accompagnement Websource » pour les administrateurs.

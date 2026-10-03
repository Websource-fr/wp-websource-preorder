# Changelog

## 1.0.0 — 2026-10-03

Première version, port WordPress / WooCommerce du module PrestaShop Websource Précommandes.

- Règles de précommande par produit et variation (début, sortie, clôture automatique, fin anticipée, statuts, « Disponible maintenant »).
- Prix de précommande via les filtres de prix WooCommerce (fixe, remise %, remise montant).
- Commande hors stock avec restauration exacte de l'état d'origine à la clôture.
- Quota total, maximum par commande et par client, interdiction optionnelle du mélange avec les produits en stock (panier classique et blocs Panier / Commande).
- Réservations (table dédiée), libération du quota à l'annulation / au remboursement, export CSV, renvoi d'e-mail.
- Compte à rebours multi-modes, barre de progression, quantité restante, badges, bouton renommé, notice panier.
- Produit à la une, bandeau défilant, page « Toutes les précommandes », blocs sur les pages de compte et wp-login.php.
- Alertes e-mail (double opt-in, RGPD, anti-spam), récapitulatif périodique par WP-Cron ou cron serveur.
- E-mails personnalisables par locale avec envoi de test.
- Page « Mes précommandes » dans Mon compte, shortcodes, blocs Gutenberg, widget.
- Encart « accompagnement Websource » (écran principal, administrateurs uniquement, masquable 30 jours, aucune requête externe ; seuls les paramètres des liens `utm_*` et `ws_*` sont transmis au clic).

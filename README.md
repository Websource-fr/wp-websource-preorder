# Websource Précommandes — plugin WordPress / WooCommerce

Système **complet et paramétrable** de précommandes pour WooCommerce : date de sortie, compte à rebours, prix de précommande, quotas, produit à la une, bandeau défilant, page « Toutes les précommandes », alertes e-mail avec double opt-in, récapitulatif périodique par cron et e-mails entièrement personnalisables.

Port du module PrestaShop [Websource Précommandes](https://github.com/Websource-fr/websourcepreorder).

- **Éditeur** : [Websource](https://www.websource.fr)
- **Version** : 1.0.0 — **Licence** : usage libre non commercial (voir [LICENSE](LICENSE))
- **Guide utilisateur** : joint à chaque [release](../../releases) (PDF)

## Compatibilité

| | |
|---|---|
| WordPress | 6.0 et plus |
| WooCommerce | 7.0 et plus (testé avec WooCommerce 11) |
| PHP | 7.4 à 8.5 |
| Thèmes | thèmes de blocs (Twenty Twenty-Five) et thèmes classiques (Twenty Twenty-One) |
| Panier / Commande | classiques (shortcodes) et blocs |

**Niveau de validation** : le plugin a été exécuté de bout en bout sur WordPress 6.9 / WooCommerce 11.1 avec Twenty Twenty-Five (thème de blocs, panier et commande en blocs) et Twenty Twenty-One (thème classique, panier et commande classiques). Les versions plus anciennes de WordPress/WooCommerce ne sont pas testées.

## Fonctionnalités

- **Règles par produit ou variation** : début, date de sortie, clôture automatique, fin anticipée, statuts Programmée / En cours / Terminée, « Disponible maintenant ».
- **Prix de précommande** (fixe, remise %, remise en montant) via les filtres de prix WooCommerce : le produit n'est jamais modifié.
- **Stock** : commande hors stock pendant la précommande, état d'origine restauré à la clôture.
- **Limites** : quota total, maximum par commande et par client, interdiction optionnelle du mélange avec les produits en stock.
- **Réservations** : table dédiée, libération du quota à l'annulation, export CSV, renvoi d'e-mail, page « Mes précommandes ».
- **Affichage** : badge, bloc fiche produit, compte à rebours (auto / jours / heures / minutes / secondes), barre de progression, quantité restante, bouton « Précommander », badge sur les vignettes, notice panier.
- **Couleurs** : 9 couleurs paramétrables.
- **Accueil** : produit à la une, bandeau défilant (haut du site, avant le pied de page, haut de l'accueil), respect de `prefers-reduced-motion`.
- **Pages de compte** : bloc (produits + alerte) sur connexion, inscription, mot de passe perdu, Mon compte et `wp-login.php`.
- **Page liste** : slug configurable, groupée par mois, shortcode `[websource_preorders]`.
- **Alertes e-mail** : double opt-in, RGPD, anti-spam (honeypot, délai, nonce, limite par IP), désinscription en un clic ; pied de page, accueil, fiche produit, barres latérales, widget, shortcode, bloc.
- **Récapitulatif** : x fois par jour / semaine / mois, toutes les précommandes ou seulement les nouveautés, envoi par lots avec reprise, jamais d'envoi à vide ; WP-Cron ou URL de cron serveur à jeton.
- **E-mails** personnalisables par locale, envoi de test.

## Encart « accompagnement Websource »

Sur l'écran principal *Précommandes*, les administrateurs (`manage_options`) voient un encart discret « Besoin d'aller plus loin ? » (boutons *Nous contacter* / *Prendre rendez-vous*). Il n'apparaît jamais côté boutique ni dans les e-mails, se masque 30 jours par utilisateur, et ne fait aucune requête externe : au clic seulement, le nom du plugin, sa version, la version de WordPress et le domaine du site sont transmis dans l'adresse du lien (`utm_*`, `ws_*`). Composant autonome : `admin/class-wspo-support-box.php`.

## Installation

1. Installez et activez WooCommerce.
2. Téléchargez `wp-websource-preorder.zip` (voir [Releases](../../releases)) puis *Extensions → Ajouter → Téléverser*, ou copiez le dossier `websource-preorder` dans `wp-content/plugins/`.
3. Activez le plugin, puis *Précommandes → Nouvelle précommande*.
4. Réglez l'apparence et les options dans *Précommandes → Réglages*.
5. (Recommandé) Planifiez le cron serveur (adresse dans *Réglages → Cron & récapitulatif*) :

```
*/5 * * * * curl -s "https://votre-site.tld/?wspo_cron=VOTRE_JETON" > /dev/null
```

## Shortcodes et blocs

| Shortcode | Rôle |
|---|---|
| `[websource_preorders]` | Liste complète des précommandes |
| `[websource_preorder_featured]` | Produit à la une |
| `[websource_preorder_ticker]` | Bandeau défilant |
| `[websource_preorder_subscribe title="" text=""]` | Formulaire d'alerte |

Les mêmes éléments existent sous forme de blocs Gutenberg (catégorie *Widgets*) ; un widget « Alertes précommandes » est disponible pour les barres latérales.

## Personnaliser l'apparence

Chaque gabarit est surchargeable depuis le thème, comme dans WooCommerce : copiez `templates/<nom>.php` vers `votre-theme/websource-preorder/<nom>.php`. Gabarits : `product-box`, `ticker`, `featured`, `subscribe-form`, `list`, `account-block`, `my-preorders`.

## Limites connues

- Pas de paiement d'un acompte puis d'un solde : paiement intégral à la commande.
- Une règle non terminée par produit / variation. Une règle « toutes les déclinaisons » et une règle sur une déclinaison peuvent coexister (la déclinaison est prioritaire).
- La limite « par client » s'applique aux clients connectés.
- Le prix de précommande se calcule à partir du **prix normal** du produit (une promotion WooCommerce programmée n'est pas cumulée).
- Placement en barres latérales gauche / droite : thèmes classiques uniquement (reconnaissance de l'identifiant de la zone de widgets) ; sur les thèmes de blocs, utilisez le bloc ou le shortcode.
- Sur Mon compte WooCommerce, connexion et inscription partagent la même page : le bloc n'y apparaît qu'une fois.
- Les e-mails sans WP-Cron actif ni cron serveur ne partent qu'à la visite suivante du site.

## Structure

```
websource-preorder.php   en-tête, chargement, notice WooCommerce
includes/                règles et réservations, prix, panier, e-mails, abonnés, affichage
admin/                   menus, règles, réservations, abonnés, réglages
templates/               gabarits surchargeables par le thème
assets/                  CSS / JS (front, admin, blocs)
languages/               fichier .pot (domaine websource-preorder)
uninstall.php            désinstallation (option « conserver les données »)
```

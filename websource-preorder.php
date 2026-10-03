<?php
/**
 * Plugin Name:       Websource Précommandes
 * Plugin URI:        https://www.websource.fr/modules-wordpress/module-precommandes-woocommerce-compte-a-rebours-alertes
 * Description:       Système complet de précommandes pour WooCommerce : date de sortie, compte à rebours, prix de précommande, quotas, bandeau défilant, alertes e-mail et récapitulatif périodique.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Websource
 * Author URI:        https://www.websource.fr/
 * License:           Licence Websource, usage libre non commercial
 * License URI:       https://github.com/Websource-fr/wp-websource-preorder/blob/main/LICENSE
 * Text Domain:       websource-preorder
 * Domain Path:       /languages
 * WC requires at least: 7.0
 * Requires Plugins:  woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WSPO_VERSION', '1.0.0' );
define( 'WSPO_FILE', __FILE__ );
define( 'WSPO_DIR', plugin_dir_path( __FILE__ ) );
define( 'WSPO_URL', plugin_dir_url( __FILE__ ) );

require_once WSPO_DIR . 'includes/class-wspo-settings.php';
require_once WSPO_DIR . 'includes/class-wspo-install.php';

register_activation_hook( __FILE__, array( 'WSPO_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WSPO_Install', 'deactivate' ) );

/**
 * Notice affichée si WooCommerce est absent : le plugin ne fait alors rien d'autre.
 */
function wspo_missing_wc_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	echo '<div class="notice notice-warning"><p>' .
		esc_html__( 'Websource Précommandes nécessite WooCommerce. Le plugin est actif mais ne fait rien tant que WooCommerce n’est pas installé et activé.', 'websource-preorder' ) .
		'</p></div>';
}

function wspo_boot() {
	load_plugin_textdomain( 'websource-preorder', false, dirname( plugin_basename( WSPO_FILE ) ) . '/languages' );

	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'wspo_missing_wc_notice' );
		return;
	}

	require_once WSPO_DIR . 'includes/functions.php';
	require_once WSPO_DIR . 'includes/class-wspo-service.php';
	require_once WSPO_DIR . 'includes/class-wspo-mail.php';
	require_once WSPO_DIR . 'includes/class-wspo-subscribers.php';
	require_once WSPO_DIR . 'includes/class-wspo-cart.php';
	require_once WSPO_DIR . 'includes/class-wspo-front.php';
	require_once WSPO_DIR . 'includes/class-wspo-widget.php';

	WSPO_Install::maybe_upgrade();
	WSPO_Service::init();
	WSPO_Cart::init();
	WSPO_Subscribers::init();
	WSPO_Front::init();

	if ( is_admin() ) {
		require_once WSPO_DIR . 'admin/class-wspo-admin.php';
		WSPO_Admin::init();
	}
}
add_action( 'plugins_loaded', 'wspo_boot', 20 );

// Compatibilité HPOS et blocs WooCommerce.
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', WSPO_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', WSPO_FILE, true );
		}
	}
);

<?php
/**
 * Installation, mise à jour du schéma et planification.
 *
 * @package WebsourcePreorder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSPO_Install {

	const DB_VERSION_OPTION = 'wspo_db_version';
	const CRON_HOOK         = 'wspo_cron_tick';

	public static function activate() {
		self::create_tables();
		if ( false === get_option( WSPO_Settings::OPTION ) ) {
			add_option( WSPO_Settings::OPTION, WSPO_Settings::defaults(), '', true );
		}
		WSPO_Settings::cron_token();
		self::schedule();
		update_option( 'wspo_flush_rewrite', 1 );
		update_option( self::DB_VERSION_OPTION, WSPO_VERSION );
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
		flush_rewrite_rules();
	}

	public static function maybe_upgrade() {
		if ( get_option( self::DB_VERSION_OPTION ) !== WSPO_VERSION ) {
			self::create_tables();
			update_option( 'wspo_flush_rewrite', 1 );
			update_option( self::DB_VERSION_OPTION, WSPO_VERSION );
		}
		self::schedule();
	}

	public static function schedule() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 60, 'wspo_five_minutes', self::CRON_HOOK );
		}
	}

	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();

		$sql = array();

		$sql[] = "CREATE TABLE {$wpdb->prefix}wspo_rules (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  product_id bigint(20) unsigned NOT NULL,
  variation_id bigint(20) unsigned NOT NULL DEFAULT 0,
  active tinyint(1) NOT NULL DEFAULT 1,
  date_start datetime NULL DEFAULT NULL,
  date_release datetime NULL DEFAULT NULL,
  end_on_release tinyint(1) NOT NULL DEFAULT 1,
  date_end datetime NULL DEFAULT NULL,
  price_mode tinyint(1) NOT NULL DEFAULT 0,
  price_value decimal(20,6) NOT NULL DEFAULT 0,
  quota int(11) NOT NULL DEFAULT 0,
  max_order int(11) NOT NULL DEFAULT 0,
  max_customer int(11) NOT NULL DEFAULT 0,
  label varchar(190) NOT NULL DEFAULT '',
  message text NULL,
  in_list tinyint(1) NOT NULL DEFAULT 1,
  state tinyint(1) NOT NULL DEFAULT 0,
  orig_state longtext NULL,
  date_add datetime NOT NULL,
  date_upd datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY product_id (product_id),
  KEY state (state)
) $charset;";

		$sql[] = "CREATE TABLE {$wpdb->prefix}wspo_bookings (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  rule_id bigint(20) unsigned NOT NULL,
  order_id bigint(20) unsigned NOT NULL,
  order_item_id bigint(20) unsigned NOT NULL DEFAULT 0,
  product_id bigint(20) unsigned NOT NULL,
  variation_id bigint(20) unsigned NOT NULL DEFAULT 0,
  customer_id bigint(20) unsigned NOT NULL DEFAULT 0,
  email varchar(190) NOT NULL DEFAULT '',
  firstname varchar(100) NOT NULL DEFAULT '',
  lastname varchar(100) NOT NULL DEFAULT '',
  quantity int(11) NOT NULL DEFAULT 1,
  unit_price decimal(20,6) NOT NULL DEFAULT 0,
  status tinyint(1) NOT NULL DEFAULT 0,
  notified tinyint(1) NOT NULL DEFAULT 0,
  date_notified datetime NULL DEFAULT NULL,
  date_add datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY rule_id (rule_id),
  KEY order_id (order_id),
  KEY customer_id (customer_id),
  KEY order_item_id (order_item_id)
) $charset;";

		$sql[] = "CREATE TABLE {$wpdb->prefix}wspo_subscribers (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  email varchar(190) NOT NULL,
  customer_id bigint(20) unsigned NOT NULL DEFAULT 0,
  locale varchar(20) NOT NULL DEFAULT '',
  source varchar(32) NOT NULL DEFAULT '',
  ip varchar(45) NOT NULL DEFAULT '',
  token varchar(40) NOT NULL,
  confirmed tinyint(1) NOT NULL DEFAULT 0,
  date_confirm datetime NULL DEFAULT NULL,
  last_sent bigint(20) unsigned NOT NULL DEFAULT 0,
  date_add datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY email (email),
  KEY token (token)
) $charset;";

		foreach ( $sql as $q ) {
			dbDelta( $q );
		}
	}
}

// Fréquence de la tâche planifiée interne.
add_filter(
	'cron_schedules',
	function ( $schedules ) {
		$schedules['wspo_five_minutes'] = array(
			'interval' => 300,
			'display'  => __( 'Toutes les 5 minutes (Websource Précommandes)', 'websource-preorder' ),
		);
		return $schedules;
	}
);

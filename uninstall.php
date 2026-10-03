<?php
/**
 * Désinstallation : supprime les données sauf si « Conserver les données » est coché.
 *
 * @package WebsourcePreorder
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$wspo_settings = get_option( 'wspo_settings', array() );
$wspo_keep     = ! is_array( $wspo_settings ) || ! array_key_exists( 'keep_data', $wspo_settings ) || ! empty( $wspo_settings['keep_data'] );

wp_clear_scheduled_hook( 'wspo_cron_tick' );

if ( $wspo_keep ) {
	return;
}

global $wpdb;
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wspo_rules" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wspo_bookings" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wspo_subscribers" );
// phpcs:enable

foreach ( array( 'wspo_settings', 'wspo_mails', 'wspo_db_version', 'wspo_next_sync', 'wspo_last_sync', 'wspo_digest_run', 'wspo_digest_last', 'wspo_flush_rewrite' ) as $wspo_opt ) {
	delete_option( $wspo_opt );
}
delete_transient( 'wspo_sync_lock' );

// Page « Toutes les précommandes » créée par le plugin.
$wspo_page = isset( $wspo_settings['list_page_id'] ) ? (int) $wspo_settings['list_page_id'] : 0;
if ( $wspo_page && false !== strpos( (string) get_post_field( 'post_content', $wspo_page ), '[websource_preorders]' ) ) {
	wp_delete_post( $wspo_page, true );
}

// Métas de commande.
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_wspo_mail_sent'" ); // phpcs:ignore

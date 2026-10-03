<?php
/**
 * Réglages du plugin (une seule option sérialisée : wspo_settings).
 *
 * @package WebsourcePreorder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSPO_Settings {

	const OPTION = 'wspo_settings';

	/** @var array|null */
	private static $cache = null;

	public static function defaults() {
		return array(
			// Général.
			'badge_label'       => __( 'Précommande', 'websource-preorder' ),
			'btn_label'         => __( 'Précommander', 'websource-preorder' ),
			'cart_notice'       => 1,
			'mixed_cart'        => 'allow',
			'show_flag'         => 1,
			'admin_email'       => '',
			'mail_order_on'     => 1,
			'mail_admin_on'     => 1,
			'mail_available_on' => 1,
			'keep_data'         => 1,
			// Compteur.
			'countdown_mode'    => 'auto',
			'show_countdown'    => 1,
			'show_progress'     => 1,
			'show_quota'        => 1,
			// Couleurs.
			'c_accent'          => '#e8590c',
			'c_on_accent'       => '#ffffff',
			'c_box_bg'          => '#fff7ed',
			'c_border'          => '#fdba74',
			'c_count_bg'        => '#1f2937',
			'c_count_text'      => '#ffffff',
			'c_badge_bg'        => '#e8590c',
			'c_ticker_bg'       => '#1f2937',
			'c_ticker_text'     => '#ffffff',
			// Accueil & bandeau.
			'home_featured'       => 1,
			'home_featured_title' => __( 'À ne pas manquer : en précommande', 'websource-preorder' ),
			'ticker_on'           => 0,
			'ticker_pos'          => 'top',
			'ticker_speed'        => 60,
			'ticker_pages'        => 'all',
			'ticker_max'          => 10,
			'ticker_prefix'       => __( 'Précommandes ouvertes :', 'websource-preorder' ),
			// Blocs comptes.
			'blk_login'    => 0,
			'blk_register' => 0,
			'blk_password' => 0,
			'blk_title'    => __( 'Bientôt disponibles en précommande', 'websource-preorder' ),
			'blk_text'     => __( 'Recevez une alerte e-mail dès qu’une nouvelle précommande est ouverte.', 'websource-preorder' ),
			'blk_products' => 3,
			'blk_form'     => 1,
			// Page liste.
			'list_page_id' => 0,
			'list_slug'    => 'precommandes',
			'list_title'   => __( 'Toutes les précommandes', 'websource-preorder' ),
			'list_intro'   => __( 'Réservez dès maintenant les produits avant leur sortie officielle.', 'websource-preorder' ),
			'list_group'   => 'month',
			// Alertes.
			'sub_title'       => __( 'Alertes précommandes', 'websource-preorder' ),
			'sub_text'        => __( 'Soyez prévenu par e-mail des prochaines précommandes.', 'websource-preorder' ),
			'sub_consent'     => __( 'J’accepte de recevoir des e-mails d’information sur les précommandes et je peux me désinscrire à tout moment.', 'websource-preorder' ),
			'sub_doubleoptin' => 1,
			'sub_footer'      => 0,
			'sub_home'        => 0,
			'sub_product'     => 0,
			'sub_left'        => 0,
			'sub_right'       => 0,
			// Cron & récapitulatif.
			'digest_on'    => 0,
			'digest_freq'  => 'week',
			'digest_times' => 1,
			'digest_mode'  => 'all',
			'digest_max'   => 10,
			'cron_token'   => '',
		);
	}

	public static function all() {
		if ( null === self::$cache ) {
			$saved       = get_option( self::OPTION, array() );
			self::$cache = wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
		}
		return self::$cache;
	}

	public static function get( $key, $fallback = '' ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : $fallback;
	}

	public static function update( array $values ) {
		$all = array_merge( self::all(), $values );
		update_option( self::OPTION, $all, true );
		self::$cache = $all;
	}

	public static function reset_cache() {
		self::$cache = null;
	}

	public static function admin_email() {
		$e = self::get( 'admin_email' );
		return is_email( $e ) ? $e : get_option( 'admin_email' );
	}

	/** Jeton du cron serveur (généré à la demande). */
	public static function cron_token() {
		$t = (string) self::get( 'cron_token' );
		if ( '' === $t ) {
			$t = wp_generate_password( 32, false, false );
			self::update( array( 'cron_token' => $t ) );
		}
		return $t;
	}
}

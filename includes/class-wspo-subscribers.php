<?php
/**
 * Abonnés aux alertes e-mail, double opt-in, désinscription, récapitulatif périodique et cron.
 *
 * @package WebsourcePreorder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSPO_Subscribers {

	public static function init() {
		add_action( 'admin_post_nopriv_wspo_subscribe', array( __CLASS__, 'handle_subscribe' ) );
		add_action( 'admin_post_wspo_subscribe', array( __CLASS__, 'handle_subscribe' ) );
		add_action( 'init', array( __CLASS__, 'handle_cron_url' ), 5 );
		add_action( 'template_redirect', array( __CLASS__, 'handle_links' ), 1 );
	}

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'wspo_subscribers';
	}

	/* ------------------------------------------------------------ jeton anti-spam */

	/** Jeton horodaté et signé pour le délai minimum de saisie. */
	public static function time_token() {
		$t = time();
		return $t . '.' . substr( wp_hash( 'wspo_t' . $t ), 0, 16 );
	}

	private static function time_token_valid( $token ) {
		$parts = explode( '.', (string) $token );
		if ( 2 !== count( $parts ) || ! ctype_digit( $parts[0] ) ) {
			return false;
		}
		if ( ! hash_equals( substr( wp_hash( 'wspo_t' . $parts[0] ), 0, 16 ), $parts[1] ) ) {
			return false;
		}
		$age = time() - (int) $parts[0];
		return $age >= 3 && $age <= DAY_IN_SECONDS;
	}

	/* ------------------------------------------------------------ inscription */

	public static function handle_subscribe() {
		$back = wp_get_referer() ? wp_get_referer() : home_url( '/' );
		$back = remove_query_arg( array( 'wspo_msg', 'wspo_src' ), $back );
		$src  = isset( $_POST['wspo_source'] ) ? sanitize_key( wp_unslash( $_POST['wspo_source'] ) ) : 'form';
		$go   = function ( $msg ) use ( $back, $src ) {
			wp_safe_redirect( add_query_arg( array( 'wspo_msg' => $msg, 'wspo_src' => $src ), $back ) . '#wspo-sub-' . $src );
			exit;
		};

		if ( ! isset( $_POST['_wspo_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wspo_nonce'] ) ), 'wspo_subscribe' ) ) {
			$go( 'spam' );
		}
		// Honeypot et délai minimum.
		if ( ! empty( $_POST['wspo_website'] ) || ! isset( $_POST['wspo_t'] ) || ! self::time_token_valid( sanitize_text_field( wp_unslash( $_POST['wspo_t'] ) ) ) ) {
			$go( 'spam' );
		}
		// Limite par adresse IP : 8 demandes par heure.
		$ip  = self::ip();
		$key = 'wspo_rl_' . md5( $ip );
		$n   = (int) get_transient( $key );
		if ( $n >= 8 ) {
			$go( 'spam' );
		}
		set_transient( $key, $n + 1, HOUR_IN_SECONDS );

		if ( empty( $_POST['wspo_consent'] ) ) {
			$go( 'consent' );
		}
		$email = isset( $_POST['wspo_email'] ) ? sanitize_email( wp_unslash( $_POST['wspo_email'] ) ) : '';
		$go( self::subscribe( $email, $src ) );
	}

	private static function ip() {
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	}

	public static function get_by_email( $email ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE email = %s', strtolower( $email ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	public static function get_by_token( $token ) {
		global $wpdb;
		if ( ! preg_match( '/^[a-zA-Z0-9]{20,40}$/', (string) $token ) ) {
			return null;
		}
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE token = %s', $token ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * Inscrit une adresse. @return string invalid|exists|confirmed|sent|error
	 */
	public static function subscribe( $email, $source = 'form', $confirmed = null ) {
		global $wpdb;
		$email = strtolower( trim( (string) $email ) );
		if ( ! is_email( $email ) ) {
			return 'invalid';
		}
		$sub = self::get_by_email( $email );
		if ( $sub && (int) $sub['confirmed'] ) {
			return 'exists';
		}
		$double = null === $confirmed ? (bool) WSPO_Settings::get( 'sub_doubleoptin' ) : ! $confirmed;
		$user   = get_user_by( 'email', $email );
		$data   = array(
			'locale'       => get_locale(),
			'source'       => substr( (string) $source, 0, 32 ),
			'ip'           => substr( self::ip(), 0, 45 ),
			'confirmed'    => $double ? 0 : 1,
			'date_confirm' => $double ? null : current_time( 'mysql', true ),
			'customer_id'  => $user ? (int) $user->ID : 0,
		);
		$fmt    = array( '%s', '%s', '%s', '%d', '%s', '%d' );
		if ( $sub ) {
			$wpdb->update( self::table(), $data, array( 'id' => (int) $sub['id'] ), $fmt, array( '%d' ) );
			$token = $sub['token'];
		} else {
			$token = wp_generate_password( 32, false, false );
			$wpdb->insert(
				self::table(),
				$data + array( 'email' => $email, 'token' => $token, 'date_add' => current_time( 'mysql', true ) ),
				array_merge( $fmt, array( '%s', '%s', '%s' ) )
			);
			if ( ! $wpdb->insert_id ) {
				return 'error';
			}
		}
		if ( ! $double ) {
			return 'confirmed';
		}
		$ok = WSPO_Mail::send( 'optin', $email, get_locale(), array( 'confirm_url' => self::link( 'confirm', $token ) ) );
		return $ok ? 'sent' : 'error';
	}

	public static function link( $action, $token ) {
		return add_query_arg( array( 'wspo_action' => $action, 'token' => $token ), home_url( '/' ) );
	}

	public static function confirm( $id ) {
		global $wpdb;
		$wpdb->update( self::table(), array( 'confirmed' => 1, 'date_confirm' => current_time( 'mysql', true ) ), array( 'id' => (int) $id ), array( '%d', '%s' ), array( '%d' ) );
	}

	public static function delete( $id ) {
		global $wpdb;
		$wpdb->delete( self::table(), array( 'id' => (int) $id ), array( '%d' ) );
	}

	/** Liens e-mail : confirmation et désinscription en un clic. */
	public static function handle_links() {
		if ( ! isset( $_GET['wspo_action'], $_GET['token'] ) ) {
			return;
		}
		$action = sanitize_key( wp_unslash( $_GET['wspo_action'] ) );
		$token  = sanitize_text_field( wp_unslash( $_GET['token'] ) );
		if ( ! in_array( $action, array( 'confirm', 'unsubscribe' ), true ) ) {
			return;
		}
		$sub = self::get_by_token( $token );
		if ( 'confirm' === $action ) {
			$title = __( 'Inscription aux alertes', 'websource-preorder' );
			if ( $sub ) {
				self::confirm( $sub['id'] );
				$msg = __( 'Merci, votre inscription aux alertes précommandes est confirmée.', 'websource-preorder' );
			} else {
				$msg = __( 'Ce lien de confirmation n’est plus valide.', 'websource-preorder' );
			}
		} else {
			$title = __( 'Désinscription', 'websource-preorder' );
			if ( $sub ) {
				self::delete( $sub['id'] );
				$msg = __( 'Vous êtes désinscrit des alertes précommandes. Vous ne recevrez plus d’e-mails.', 'websource-preorder' );
			} else {
				$msg = __( 'Cette adresse n’est plus inscrite aux alertes.', 'websource-preorder' );
			}
		}
		wp_die(
			'<p>' . esc_html( $msg ) . '</p><p><a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Retour à la boutique', 'websource-preorder' ) . '</a></p>',
			esc_html( $title ),
			array( 'response' => 200 )
		);
	}

	/* ------------------------------------------------------------ cron serveur */

	public static function handle_cron_url() {
		if ( ! isset( $_GET['wspo_cron'] ) ) {
			return;
		}
		$token = sanitize_text_field( wp_unslash( $_GET['wspo_cron'] ) );
		nocache_headers();
		header( 'Content-Type: text/plain; charset=utf-8' );
		if ( ! hash_equals( WSPO_Settings::cron_token(), $token ) ) {
			status_header( 403 );
			echo 'forbidden';
			exit;
		}
		$changed = WSPO_Service::sync_all();
		$result  = array( 0, 'disabled' );
		if ( WSPO_Settings::get( 'digest_on' ) ) {
			$result = self::run_digest();
		}
		echo 'OK sync=' . (int) $changed . ' digest=' . esc_html( $result[1] ) . ' sent=' . (int) $result[0];
		exit;
	}

	/* ------------------------------------------------------------ récapitulatif */

	public static function digest_interval() {
		$times  = max( 1, (int) WSPO_Settings::get( 'digest_times' ) );
		$period = array( 'day' => DAY_IN_SECONDS, 'week' => WEEK_IN_SECONDS, 'month' => 30 * DAY_IN_SECONDS );
		$f      = (string) WSPO_Settings::get( 'digest_freq' );
		return (int) floor( ( isset( $period[ $f ] ) ? $period[ $f ] : WEEK_IN_SECONDS ) / $times );
	}

	private static function digest_rules() {
		$rules = array();
		foreach ( WSPO_Service::running() as $r ) {
			if ( (int) $r['in_list'] ) {
				$rules[] = $r;
			}
		}
		return $rules;
	}

	public static function digest_products_html( array $rules ) {
		$html = '<table width="100%" cellpadding="8" cellspacing="0" style="border-collapse:collapse;font-size:14px">';
		foreach ( $rules as $r ) {
			$p = wc_get_product( $r['variation_id'] ? $r['variation_id'] : $r['product_id'] );
			if ( ! $p ) {
				continue;
			}
			$img_id = $p->get_image_id() ? $p->get_image_id() : ( $p->get_parent_id() ? wc_get_product( $p->get_parent_id() )->get_image_id() : 0 );
			$img    = $img_id ? wp_get_attachment_image_url( $img_id, 'thumbnail' ) : '';
			$price  = trim( html_entity_decode( wp_strip_all_tags( $p->get_price_html() ), ENT_QUOTES, 'UTF-8' ) );
			$date   = wspo_format_date( $r['date_release'] );
			$html  .= '<tr style="border-bottom:1px solid #eee"><td width="90">'
				. ( $img ? '<a href="' . esc_url( $p->get_permalink() ) . '"><img src="' . esc_url( $img ) . '" width="80" alt=""></a>' : '' )
				. '</td><td><a href="' . esc_url( $p->get_permalink() ) . '" style="color:#222;font-weight:bold;text-decoration:none">' . esc_html( $p->get_name() ) . '</a><br>'
				. ( $date ? '<span style="color:#666">' . esc_html__( 'Sortie', 'websource-preorder' ) . ' ' . esc_html( $date ) . '</span><br>' : '' )
				. '<strong>' . esc_html( $price ) . '</strong></td></tr>';
		}
		return $html . '</table>';
	}

	/**
	 * Envoie le récapitulatif si l'échéance est atteinte, par lots avec reprise.
	 *
	 * @return array( int envoyés, string statut ) statut : disabled|not_due|no_preorder|nothing_new|partial|done
	 */
	public static function run_digest( $force = false, $max_mails = 100 ) {
		global $wpdb;
		if ( ! WSPO_Settings::get( 'digest_on' ) && ! $force ) {
			return array( 0, 'disabled' );
		}
		$now  = time();
		$run  = (int) get_option( 'wspo_digest_run', 0 );
		$last = (int) get_option( 'wspo_digest_last', 0 );

		if ( ! $run ) {
			if ( ! $force && $now - $last < self::digest_interval() ) {
				return array( 0, 'not_due' );
			}
			$rules = self::digest_rules();
			if ( ! $rules ) {
				return array( 0, 'no_preorder' ); // Jamais d'envoi s'il n'y a rien.
			}
			if ( 'new' === WSPO_Settings::get( 'digest_mode' ) && ! $force ) {
				$has_new = false;
				foreach ( $rules as $r ) {
					if ( wspo_ts( $r['date_add'] ) > $last ) {
						$has_new = true;
					}
				}
				if ( ! $has_new ) {
					return array( 0, 'nothing_new' );
				}
			}
			$run = $now;
			update_option( 'wspo_digest_run', $run, false );
		}

		$rules = self::digest_rules();
		$max   = (int) WSPO_Settings::get( 'digest_max' );
		if ( $max > 0 ) {
			$rules = array_slice( $rules, 0, $max );
		}
		$subs = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE confirmed = 1 AND last_sent < %d LIMIT %d', $run, $max_mails ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
		$sent = 0;
		$html = $rules ? self::digest_products_html( $rules ) : '';
		foreach ( (array) $subs as $s ) {
			$ok = true;
			if ( $rules ) {
				$ok = WSPO_Mail::send(
					'digest',
					$s['email'],
					$s['locale'] ? $s['locale'] : get_locale(),
					array(
						'products_html'   => $html,
						'count'           => count( $rules ),
						'unsubscribe_url' => self::link( 'unsubscribe', $s['token'] ),
					)
				);
			}
			// On marque l'abonné même en cas d'échec : pas de boucle infinie.
			$wpdb->update( self::table(), array( 'last_sent' => $run ), array( 'id' => (int) $s['id'] ), array( '%d' ), array( '%d' ) );
			if ( $ok ) {
				++$sent;
			}
		}
		$remaining = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table() . ' WHERE confirmed = 1 AND last_sent < %d', $run ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		if ( 0 === $remaining ) {
			update_option( 'wspo_digest_last', $run, false );
			update_option( 'wspo_digest_run', 0, false );
			return array( $sent, 'done' );
		}
		return array( $sent, 'partial' );
	}
}

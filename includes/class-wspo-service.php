<?php
/**
 * Logique métier : règles, états, prix, stock, quotas, réservations.
 *
 * @package WebsourcePreorder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSPO_Service {

	const S_PENDING = 0; // Programmée (ou désactivée).
	const S_RUNNING = 1; // En cours.
	const S_ENDED   = 2; // Terminée.

	const B_ACTIVE    = 0;
	const B_CANCELLED = 1;

	/** @var array|null */
	private static $running = null;

	public static function init() {
		// Synchronisation (stock, états, e-mails de sortie).
		add_action( 'init', array( __CLASS__, 'maybe_sync' ), 20 );
		add_action( WSPO_Install::CRON_HOOK, array( __CLASS__, 'cron_tick' ) );

		// Prix de précommande (aucune écriture sur le produit).
		add_filter( 'woocommerce_product_get_price', array( __CLASS__, 'filter_price' ), 20, 2 );
		add_filter( 'woocommerce_product_get_sale_price', array( __CLASS__, 'filter_price' ), 20, 2 );
		add_filter( 'woocommerce_product_variation_get_price', array( __CLASS__, 'filter_price' ), 20, 2 );
		add_filter( 'woocommerce_product_variation_get_sale_price', array( __CLASS__, 'filter_price' ), 20, 2 );
		add_filter( 'woocommerce_variation_prices_price', array( __CLASS__, 'filter_variation_price' ), 20, 3 );
		add_filter( 'woocommerce_variation_prices_sale_price', array( __CLASS__, 'filter_variation_price' ), 20, 3 );
		add_filter( 'woocommerce_get_variation_prices_hash', array( __CLASS__, 'variation_hash' ), 20, 1 );

		// Réservations.
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'tag_order_item' ), 20, 3 );
		add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'on_order_created' ), 20, 1 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'on_order_created_obj' ), 20, 1 );
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'on_status_changed' ), 20, 3 );
		add_action( 'woocommerce_order_status_on-hold', array( __CLASS__, 'on_order_paid' ), 30, 1 );
		add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'on_order_paid' ), 30, 1 );
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'on_order_paid' ), 30, 1 );
		add_action( 'before_delete_post', array( __CLASS__, 'on_product_deleted' ) );
	}

	/* ---------------------------------------------------------------- table */

	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'wspo_' . $name;
	}

	/* ---------------------------------------------------------------- états */

	public static function effective_end( array $r ) {
		$ends = array();
		if ( wspo_ts( $r['date_end'] ) ) {
			$ends[] = wspo_ts( $r['date_end'] );
		}
		if ( ! empty( $r['end_on_release'] ) && wspo_ts( $r['date_release'] ) ) {
			$ends[] = wspo_ts( $r['date_release'] );
		}
		return $ends ? min( $ends ) : null;
	}

	public static function desired_state( array $r, $now = null ) {
		$now   = $now ? $now : time();
		$start = wspo_ts( $r['date_start'] );
		$end   = self::effective_end( $r );
		if ( null !== $end && $now >= $end ) {
			return self::S_ENDED;
		}
		if ( empty( $r['active'] ) ) {
			return self::S_PENDING;
		}
		if ( null !== $start && $now < $start ) {
			return self::S_PENDING;
		}
		return self::S_RUNNING;
	}

	public static function state_label( $state ) {
		$labels = array(
			self::S_PENDING => __( 'Programmée', 'websource-preorder' ),
			self::S_RUNNING => __( 'En cours', 'websource-preorder' ),
			self::S_ENDED   => __( 'Terminée', 'websource-preorder' ),
		);
		return isset( $labels[ (int) $state ] ) ? $labels[ (int) $state ] : '';
	}

	public static function reset_cache() {
		self::$running = null;
	}

	/** Règles actuellement en précommande (calcul au fil du temps), triées par date de sortie. */
	public static function running() {
		global $wpdb;
		if ( null === self::$running ) {
			self::$running = array();
			$rows          = $wpdb->get_results( 'SELECT * FROM ' . self::table( 'rules' ) . ' WHERE active = 1 AND state < 2', ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
			$list          = array();
			foreach ( (array) $rows as $r ) {
				if ( self::S_RUNNING === self::desired_state( $r ) ) {
					$list[] = $r;
				}
			}
			usort(
				$list,
				function ( $a, $b ) {
					$ta = wspo_ts( $a['date_release'] ) ? wspo_ts( $a['date_release'] ) : PHP_INT_MAX;
					$tb = wspo_ts( $b['date_release'] ) ? wspo_ts( $b['date_release'] ) : PHP_INT_MAX;
					return $ta <=> $tb;
				}
			);
			foreach ( $list as $r ) {
				self::$running[ (int) $r['id'] ] = $r;
			}
		}
		return self::$running;
	}

	/**
	 * Règle en cours pour un produit / une variation. $variation_id = null : n'importe laquelle du produit.
	 */
	public static function find_rule( $product_id, $variation_id = 0 ) {
		$found = null;
		foreach ( self::running() as $r ) {
			if ( (int) $r['product_id'] !== (int) $product_id ) {
				continue;
			}
			if ( $variation_id && (int) $r['variation_id'] === (int) $variation_id ) {
				return $r;
			}
			if ( null === $found && ( 0 === (int) $r['variation_id'] || null === $variation_id ) ) {
				$found = $r;
			}
		}
		return $found;
	}

	/** Règle en cours pour un objet produit (produit simple ou variation). */
	public static function rule_for_product( $product ) {
		if ( ! $product instanceof WC_Product ) {
			return null;
		}
		if ( $product->is_type( 'variation' ) ) {
			return self::find_rule( $product->get_parent_id(), $product->get_id() );
		}
		return self::find_rule( $product->get_id(), 0 );
	}

	/* ---------------------------------------------------------------- règles : CRUD */

	public static function get_rule( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'rules' ) . ' WHERE id = %d', $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * Crée ou met à jour une règle. @return int|WP_Error identifiant
	 */
	public static function save_rule( array $d, $id = 0 ) {
		global $wpdb;
		$product_id   = absint( $d['product_id'] );
		$variation_id = absint( $d['variation_id'] );
		if ( ! $product_id || ! wc_get_product( $product_id ) ) {
			return new WP_Error( 'wspo_product', __( 'Choisissez un produit.', 'websource-preorder' ) );
		}
		if ( empty( $d['date_release'] ) ) {
			return new WP_Error( 'wspo_release', __( 'La date de sortie est obligatoire.', 'websource-preorder' ) );
		}
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM ' . self::table( 'rules' ) . ' WHERE product_id = %d AND variation_id = %d AND state < 2 AND id != %d LIMIT 1', // phpcs:ignore WordPress.DB.PreparedSQL
				$product_id,
				$variation_id,
				$id
			)
		);
		if ( $exists ) {
			return new WP_Error( 'wspo_dup', __( 'Une précommande non terminée existe déjà pour ce produit / cette déclinaison.', 'websource-preorder' ) );
		}
		$now  = current_time( 'mysql', true );
		$row  = array(
			'product_id'     => $product_id,
			'variation_id'   => $variation_id,
			'active'         => empty( $d['active'] ) ? 0 : 1,
			'date_start'     => $d['date_start'],
			'date_release'   => $d['date_release'],
			'end_on_release' => empty( $d['end_on_release'] ) ? 0 : 1,
			'date_end'       => $d['date_end'],
			'price_mode'     => max( 0, min( 3, (int) $d['price_mode'] ) ),
			'price_value'    => max( 0, (float) $d['price_value'] ),
			'quota'          => max( 0, (int) $d['quota'] ),
			'max_order'      => max( 0, (int) $d['max_order'] ),
			'max_customer'   => max( 0, (int) $d['max_customer'] ),
			'label'          => sanitize_text_field( $d['label'] ),
			'message'        => sanitize_textarea_field( $d['message'] ),
			'in_list'        => empty( $d['in_list'] ) ? 0 : 1,
			'date_upd'       => $now,
		);
		$fmt  = array( '%d', '%d', '%d', '%s', '%s', '%d', '%s', '%d', '%f', '%d', '%d', '%d', '%s', '%s', '%d', '%s' );
		if ( $id ) {
			$old = self::get_rule( $id );
			// Si la règle tournait, on annule d'abord ses effets, puis la synchro les réapplique.
			if ( $old && (int) $old['state'] === self::S_RUNNING ) {
				self::revert( $old );
			}
			$wpdb->update( self::table( 'rules' ), $row + array( 'state' => self::S_PENDING ), array( 'id' => $id ), array_merge( $fmt, array( '%d' ) ), array( '%d' ) );
		} else {
			$row['date_add'] = $now;
			$row['state']    = self::S_PENDING;
			$wpdb->insert( self::table( 'rules' ), $row, array_merge( $fmt, array( '%s', '%d' ) ) );
			$id = (int) $wpdb->insert_id;
		}
		self::reset_cache();
		self::sync_all();
		return (int) $id;
	}

	public static function delete_rule( $id ) {
		global $wpdb;
		$r = self::get_rule( $id );
		if ( ! $r ) {
			return;
		}
		if ( (int) $r['state'] === self::S_RUNNING ) {
			self::revert( $r );
		}
		$wpdb->delete( self::table( 'rules' ), array( 'id' => $id ), array( '%d' ) );
		self::reset_cache();
		self::schedule_next_sync();
	}

	public static function on_product_deleted( $post_id ) {
		global $wpdb;
		$type = get_post_type( $post_id );
		if ( 'product' !== $type && 'product_variation' !== $type ) {
			return;
		}
		$col = 'product' === $type ? 'product_id' : 'variation_id';
		$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . self::table( 'rules' ) . " WHERE $col = %d", $post_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		foreach ( $ids as $rid ) {
			$wpdb->delete( self::table( 'rules' ), array( 'id' => (int) $rid ), array( '%d' ) );
		}
		self::reset_cache();
	}

	/* ---------------------------------------------------------------- synchronisation */

	public static function maybe_sync() {
		$next = (int) get_option( 'wspo_next_sync', 0 );
		if ( time() >= $next ) {
			self::sync_all();
		}
	}

	public static function cron_tick() {
		self::sync_all();
		if ( WSPO_Settings::get( 'digest_on' ) ) {
			WSPO_Subscribers::run_digest();
		}
	}

	public static function sync_all() {
		global $wpdb;
		// Verrou léger contre les exécutions simultanées.
		if ( get_transient( 'wspo_sync_lock' ) ) {
			return 0;
		}
		set_transient( 'wspo_sync_lock', 1, 30 );
		$n    = 0;
		$rows = $wpdb->get_results( 'SELECT * FROM ' . self::table( 'rules' ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
		foreach ( (array) $rows as $r ) {
			if ( self::sync_rule( $r ) ) {
				++$n;
			}
		}
		self::reset_cache();
		update_option( 'wspo_last_sync', time(), false );
		self::schedule_next_sync();
		delete_transient( 'wspo_sync_lock' );
		return $n;
	}

	/** Calcule le prochain changement d'état pour éviter de resynchroniser à chaque requête. */
	public static function schedule_next_sync() {
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT date_start, date_release, date_end, end_on_release, active, state FROM ' . self::table( 'rules' ) . ' WHERE state < 2', ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
		$now  = time();
		$next = $now + 3600;
		foreach ( (array) $rows as $r ) {
			$cands = array( wspo_ts( $r['date_start'] ), wspo_ts( $r['date_end'] ) );
			if ( ! empty( $r['end_on_release'] ) ) {
				$cands[] = wspo_ts( $r['date_release'] );
			}
			foreach ( $cands as $c ) {
				if ( $c && $c > $now && $c < $next ) {
					$next = $c;
				}
			}
		}
		update_option( 'wspo_next_sync', $next, true );
	}

	/** @return bool true si l'état a changé. */
	public static function sync_rule( array $rule ) {
		global $wpdb;
		$desired = self::desired_state( $rule );
		$old     = (int) $rule['state'];
		if ( $desired === $old ) {
			return false;
		}
		if ( self::S_RUNNING === $old ) {
			self::revert( $rule );
		}
		if ( self::S_RUNNING === $desired ) {
			self::apply( $rule );
		}
		$wpdb->update( self::table( 'rules' ), array( 'state' => $desired, 'date_upd' => current_time( 'mysql', true ) ), array( 'id' => (int) $rule['id'] ), array( '%d', '%s' ), array( '%d' ) );
		self::reset_cache();

		if ( self::S_RUNNING === $old && self::S_ENDED === $desired ) {
			$rel = wspo_ts( $rule['date_release'] );
			if ( null !== $rel && $rel <= time() + 60 ) {
				self::notify_available( self::get_rule( (int) $rule['id'] ) );
			}
		}
		do_action( 'wspo_rule_state_changed', (int) $rule['id'], $old, $desired );
		return true;
	}

	/** Produits concernés par une règle (produit + déclinaisons, ou une seule déclinaison). */
	private static function target_ids( array $rule ) {
		if ( (int) $rule['variation_id'] ) {
			return array( (int) $rule['variation_id'] );
		}
		$ids = array( (int) $rule['product_id'] );
		$p   = wc_get_product( (int) $rule['product_id'] );
		if ( $p && $p->is_type( 'variable' ) ) {
			$ids = array_merge( $ids, array_map( 'intval', $p->get_children() ) );
		}
		return $ids;
	}

	/** Autorise la commande hors stock et mémorise l'état d'origine. */
	public static function apply( array $rule ) {
		global $wpdb;
		$orig = array();
		// État d'origine déjà mémorisé par une autre règle en cours sur les mêmes produits.
		$others = $wpdb->get_results( $wpdb->prepare( 'SELECT orig_state FROM ' . self::table( 'rules' ) . ' WHERE state = 1 AND id != %d AND orig_state IS NOT NULL', (int) $rule['id'] ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
		$known  = array();
		foreach ( (array) $others as $o ) {
			$j = json_decode( $o['orig_state'], true );
			if ( is_array( $j ) ) {
				$known += $j;
			}
		}
		foreach ( self::target_ids( $rule ) as $pid ) {
			$p = wc_get_product( $pid );
			if ( ! $p ) {
				continue;
			}
			$orig[ $pid ] = isset( $known[ $pid ] ) ? $known[ $pid ] : array(
				'backorders'   => $p->get_backorders( 'edit' ),
				'stock_status' => $p->get_stock_status( 'edit' ),
				'manage_stock' => $p->get_manage_stock( 'edit' ),
			);
			$p->set_backorders( 'yes' );
			if ( ! $p->get_manage_stock( 'edit' ) && 'outofstock' === $p->get_stock_status( 'edit' ) ) {
				$p->set_stock_status( 'onbackorder' );
			}
			$p->save();
		}
		$wpdb->update( self::table( 'rules' ), array( 'orig_state' => wp_json_encode( $orig ) ), array( 'id' => (int) $rule['id'] ), array( '%s' ), array( '%d' ) );
		self::flush_product_caches( $rule );
	}

	/** Restaure exactement l'état d'origine. */
	public static function revert( array $rule ) {
		global $wpdb;
		$orig = ! empty( $rule['orig_state'] ) ? json_decode( $rule['orig_state'], true ) : array();
		// Produits encore couverts par une autre règle en cours : on ne les restaure pas tout de suite.
		$still  = array();
		$others = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'rules' ) . ' WHERE state = 1 AND id != %d', (int) $rule['id'] ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
		foreach ( (array) $others as $o ) {
			$still = array_merge( $still, self::target_ids( $o ) );
		}
		if ( is_array( $orig ) ) {
			foreach ( $orig as $pid => $o ) {
				if ( in_array( (int) $pid, $still, true ) ) {
					continue;
				}
				$p = wc_get_product( (int) $pid );
				if ( ! $p ) {
					continue;
				}
				$p->set_backorders( $o['backorders'] );
				$p->set_manage_stock( $o['manage_stock'] );
				$p->set_stock_status( $o['stock_status'] );
				$p->save();
			}
		}
		$wpdb->update( self::table( 'rules' ), array( 'orig_state' => null ), array( 'id' => (int) $rule['id'] ), array( '%s' ), array( '%d' ) );
		self::flush_product_caches( $rule );
	}

	private static function flush_product_caches( array $rule ) {
		$pid = (int) $rule['product_id'];
		$p   = wc_get_product( $pid );
		if ( $p && $p->is_type( 'variable' ) ) {
			WC_Product_Variable::sync( $pid );
		}
		wc_delete_product_transients( $pid );
	}

	/** « Disponible maintenant » : termine la règle et prévient les acheteurs. */
	public static function release_now( $id ) {
		global $wpdb;
		$r = self::get_rule( $id );
		if ( ! $r ) {
			return false;
		}
		$wpdb->update(
			self::table( 'rules' ),
			array( 'date_release' => wspo_mysql( time() ), 'end_on_release' => 1 ),
			array( 'id' => (int) $id ),
			array( '%s', '%d' ),
			array( '%d' )
		);
		self::reset_cache();
		return self::sync_rule( self::get_rule( $id ) );
	}

	/* ---------------------------------------------------------------- prix */

	/** Prix de précommande calculé à partir du prix normal. */
	public static function compute_price( array $rule, $regular ) {
		$regular = (float) $regular;
		$v       = (float) $rule['price_value'];
		switch ( (int) $rule['price_mode'] ) {
			case 1:
				$p = $v;
				break;
			case 2:
				$p = $regular * ( 1 - min( 100, $v ) / 100 );
				break;
			case 3:
				$p = max( 0, $regular - $v );
				break;
			default:
				return null;
		}
		return round( $p, wc_get_price_decimals() );
	}

	public static function filter_price( $value, $product ) {
		if ( $product->is_type( 'variable' ) ) {
			return $value;
		}
		$rule = self::rule_for_product( $product );
		if ( ! $rule || (int) $rule['price_mode'] < 1 ) {
			return $value;
		}
		$regular = $product->get_regular_price( 'edit' );
		if ( '' === $regular ) {
			return $value;
		}
		$p = self::compute_price( $rule, $regular );
		return null === $p ? $value : (string) $p;
	}

	public static function filter_variation_price( $price, $variation, $product ) {
		$rule = self::rule_for_product( $variation );
		if ( ! $rule || (int) $rule['price_mode'] < 1 ) {
			return $price;
		}
		$regular = $variation->get_regular_price( 'edit' );
		if ( '' === $regular ) {
			return $price;
		}
		$p = self::compute_price( $rule, $regular );
		return null === $p ? $price : (string) $p;
	}

	public static function variation_hash( $hash ) {
		$sig = array();
		foreach ( self::running() as $r ) {
			$sig[] = array( $r['id'], $r['product_id'], $r['variation_id'], $r['price_mode'], $r['price_value'] );
		}
		$hash[] = md5( wp_json_encode( $sig ) );
		return $hash;
	}

	/* ---------------------------------------------------------------- quotas */

	public static function booked( $rule_id, $customer_id = null ) {
		global $wpdb;
		$sql = 'SELECT COALESCE(SUM(quantity),0) FROM ' . self::table( 'bookings' ) . ' WHERE rule_id = %d AND status != %d'; // phpcs:ignore WordPress.DB.PreparedSQL
		$arg = array( (int) $rule_id, self::B_CANCELLED );
		if ( null !== $customer_id ) {
			$sql  .= ' AND customer_id = %d';
			$arg[] = (int) $customer_id;
		}
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $arg ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/** @return int|null null = illimité */
	public static function remaining( array $rule ) {
		if ( ! (int) $rule['quota'] ) {
			return null;
		}
		return max( 0, (int) $rule['quota'] - self::booked( $rule['id'] ) );
	}

	/* ---------------------------------------------------------------- réservations */

	/** Marque la ligne de commande comme précommande (méta cachée + méta visible). */
	public static function tag_order_item( $item, $cart_item_key, $values ) {
		$rule = self::find_rule( $values['product_id'], isset( $values['variation_id'] ) ? (int) $values['variation_id'] : 0 );
		if ( ! $rule ) {
			return;
		}
		$item->add_meta_data( '_wspo_rule_id', (int) $rule['id'], true );
		$rel = wspo_ts( $rule['date_release'] );
		/* translators: %s: release date */
		$info = $rel ? sprintf( __( 'Sortie prévue le %s', 'websource-preorder' ), wspo_format_date( $rule['date_release'] ) ) : __( 'Oui', 'websource-preorder' );
		$item->add_meta_data( __( 'Précommande', 'websource-preorder' ), $info, true );
	}

	public static function on_order_created( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( $order ) {
			self::create_bookings( $order );
		}
	}

	public static function on_order_created_obj( $order ) {
		if ( $order instanceof WC_Order ) {
			self::create_bookings( $order );
		}
	}

	/** Crée les réservations d'une commande (idempotent). @return array nouvelles réservations (lignes). */
	public static function create_bookings( WC_Order $order ) {
		global $wpdb;
		$new = array();
		foreach ( $order->get_items() as $item_id => $item ) {
			$rule_id = (int) $item->get_meta( '_wspo_rule_id' );
			if ( ! $rule_id ) {
				continue;
			}
			$exists = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::table( 'bookings' ) . ' WHERE order_item_id = %d', $item_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL
			if ( $exists ) {
				continue;
			}
			$qty   = max( 1, (int) $item->get_quantity() );
			$wpdb->insert(
				self::table( 'bookings' ),
				array(
					'rule_id'       => $rule_id,
					'order_id'      => $order->get_id(),
					'order_item_id' => $item_id,
					'product_id'    => (int) $item->get_product_id(),
					'variation_id'  => (int) $item->get_variation_id(),
					'customer_id'   => (int) $order->get_customer_id(),
					'email'         => (string) $order->get_billing_email(),
					'firstname'     => (string) $order->get_billing_first_name(),
					'lastname'      => (string) $order->get_billing_last_name(),
					'quantity'      => $qty,
					'unit_price'    => (float) $order->get_item_subtotal( $item, false, false ),
					'status'        => in_array( $order->get_status(), array( 'cancelled', 'refunded', 'failed' ), true ) ? self::B_CANCELLED : self::B_ACTIVE,
					'date_add'      => current_time( 'mysql', true ),
				),
				array( '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%f', '%d', '%s' )
			);
			$new[] = (int) $wpdb->insert_id;
		}
		return $new;
	}

	public static function order_bookings( $order_id, $only_active = true ) {
		global $wpdb;
		$sql = 'SELECT * FROM ' . self::table( 'bookings' ) . ' WHERE order_id = %d' . ( $only_active ? ' AND status = 0' : '' ) . ' ORDER BY id'; // phpcs:ignore WordPress.DB.PreparedSQL
		return (array) $wpdb->get_results( $wpdb->prepare( $sql, (int) $order_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	public static function on_status_changed( $order_id, $from, $to ) {
		global $wpdb;
		$released = array( 'cancelled', 'refunded', 'failed' );
		if ( in_array( $to, $released, true ) ) {
			$wpdb->update( self::table( 'bookings' ), array( 'status' => self::B_CANCELLED ), array( 'order_id' => (int) $order_id ), array( '%d' ), array( '%d' ) );
		} elseif ( in_array( $from, $released, true ) ) {
			$wpdb->update( self::table( 'bookings' ), array( 'status' => self::B_ACTIVE ), array( 'order_id' => (int) $order_id ), array( '%d' ), array( '%d' ) );
		}
	}

	/** Commande validée : réservations (au cas où) puis e-mails client + administrateur, une seule fois. */
	public static function on_order_paid( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || $order->get_meta( '_wspo_mail_sent' ) ) {
			return;
		}
		self::create_bookings( $order );
		$bookings = self::order_bookings( $order_id );
		if ( ! $bookings ) {
			return;
		}
		$order->update_meta_data( '_wspo_mail_sent', 1 );
		$order->save();

		$locale   = $order->get_customer_id() ? get_user_locale( $order->get_customer_id() ) : get_locale();
		$products = self::bookings_html( $bookings );
		$release  = self::release_info( $bookings );
		$vars     = array(
			'firstname'       => $order->get_billing_first_name(),
			'lastname'        => $order->get_billing_last_name(),
			'order_reference' => $order->get_order_number(),
			'products_html'   => $products,
			'release_info'    => $release,
		);
		if ( WSPO_Settings::get( 'mail_order_on' ) && is_email( $order->get_billing_email() ) ) {
			WSPO_Mail::send( 'order', $order->get_billing_email(), $locale, $vars );
		}
		if ( WSPO_Settings::get( 'mail_admin_on' ) ) {
			WSPO_Mail::send(
				'admin',
				WSPO_Settings::admin_email(),
				get_locale(),
				array(
					'customer'        => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
					'customer_email'  => $order->get_billing_email(),
					'order_reference' => $order->get_order_number(),
					'products_html'   => $products,
				)
			);
		}
	}

	private static function booking_name( array $b ) {
		$p = wc_get_product( $b['variation_id'] ? $b['variation_id'] : $b['product_id'] );
		return $p ? $p->get_name() : '#' . $b['product_id'];
	}

	public static function bookings_html( array $bookings ) {
		$html = '<table width="100%" cellpadding="6" cellspacing="0" style="border-collapse:collapse;font-size:14px">';
		foreach ( $bookings as $b ) {
			$rule = self::get_rule( (int) $b['rule_id'] );
			$rel  = $rule ? wspo_format_date( $rule['date_release'] ) : '';
			$html .= '<tr style="border-bottom:1px solid #eee"><td><strong>' . esc_html( self::booking_name( $b ) ) . '</strong>'
				. ( $rel ? '<br><span style="color:#666">' . esc_html__( 'Sortie prévue le', 'websource-preorder' ) . ' ' . esc_html( $rel ) . '</span>' : '' )
				. '</td><td align="right" style="white-space:nowrap">&times; ' . (int) $b['quantity'] . '</td></tr>';
		}
		return $html . '</table>';
	}

	public static function release_info( array $bookings ) {
		$dates = array();
		foreach ( $bookings as $b ) {
			$rule = self::get_rule( (int) $b['rule_id'] );
			if ( $rule && wspo_ts( $rule['date_release'] ) ) {
				$dates[] = wspo_ts( $rule['date_release'] );
			}
		}
		if ( ! $dates ) {
			return '';
		}
		/* translators: %s: date */
		return sprintf( __( 'Date de sortie prévue : %s.', 'websource-preorder' ), wp_date( get_option( 'date_format' ), min( $dates ) ) );
	}

	/** Prévient les acheteurs qu'une précommande est disponible. */
	public static function notify_available( $rule ) {
		global $wpdb;
		if ( ! $rule ) {
			return 0;
		}
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'bookings' ) . ' WHERE rule_id = %d AND status = 0 AND notified = 0', (int) $rule['id'] ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
		$n    = 0;
		foreach ( (array) $rows as $b ) {
			if ( self::notify_booking( $b, $rule ) ) {
				++$n;
			}
		}
		return $n;
	}

	public static function notify_booking( array $b, $rule = null, $force = false ) {
		global $wpdb;
		if ( ! $force && ! WSPO_Settings::get( 'mail_available_on' ) ) {
			return false;
		}
		if ( ! is_email( $b['email'] ) ) {
			return false;
		}
		$rule   = $rule ? $rule : self::get_rule( (int) $b['rule_id'] );
		$pid    = $b['variation_id'] ? $b['variation_id'] : $b['product_id'];
		$p      = wc_get_product( $pid );
		$order  = wc_get_order( $b['order_id'] );
		$locale = $b['customer_id'] ? get_user_locale( $b['customer_id'] ) : get_locale();
		$ok     = WSPO_Mail::send(
			'available',
			$b['email'],
			$locale,
			array(
				'firstname'       => $b['firstname'],
				'lastname'        => $b['lastname'],
				'order_reference' => $order ? $order->get_order_number() : '#' . $b['order_id'],
				'product_name'    => $p ? $p->get_name() : '',
				'product_url'     => $p ? $p->get_permalink() : home_url( '/' ),
				'release_date'    => $rule ? wspo_format_date( $rule['date_release'] ) : '',
			)
		);
		if ( $ok && ( ! $rule || (int) $rule['state'] === self::S_ENDED ) ) {
			$wpdb->update( self::table( 'bookings' ), array( 'notified' => 1, 'date_notified' => current_time( 'mysql', true ) ), array( 'id' => (int) $b['id'] ), array( '%d', '%s' ), array( '%d' ) );
		}
		return $ok;
	}

	/* ---------------------------------------------------------------- données d'affichage */

	public static function rule_label( array $rule ) {
		return '' !== trim( (string) $rule['label'] ) ? $rule['label'] : '';
	}

	/** Éléments affichables (règles en cours dont le produit est publié). */
	public static function get_items( array $args = array() ) {
		$args  = wp_parse_args( $args, array( 'in_list_only' => false, 'limit' => 0 ) );
		$items = array();
		foreach ( self::running() as $r ) {
			if ( $args['in_list_only'] && ! (int) $r['in_list'] ) {
				continue;
			}
			$pid = (int) ( $r['variation_id'] ? $r['variation_id'] : $r['product_id'] );
			$p   = wc_get_product( $pid );
			if ( ! $p || 'publish' !== get_post_status( $r['product_id'] ) ) {
				continue;
			}
			if ( $p->is_type( 'variation' ) ) {
				$parent = wc_get_product( $r['product_id'] );
				if ( ! $parent || ! $parent->is_visible() ) {
					continue;
				}
			} elseif ( ! $p->is_visible() ) {
				continue;
			}
			$items[] = array(
				'rule'      => $r,
				'product'   => $p,
				'name'      => $p->get_name(),
				'url'       => $p->get_permalink(),
				'release'   => wspo_ts( $r['date_release'] ),
				'remaining' => self::remaining( $r ),
			);
			if ( $args['limit'] && count( $items ) >= $args['limit'] ) {
				break;
			}
		}
		return $items;
	}
}

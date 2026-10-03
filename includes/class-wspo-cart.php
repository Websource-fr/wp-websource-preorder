<?php
/**
 * Contrôles du panier : quotas, limites, mélange précommandes / stock.
 * Fonctionne avec le panier classique et les blocs Panier / Commande (Store API).
 *
 * @package WebsourcePreorder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSPO_Cart {

	public static function init() {
		add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'validate_add' ), 20, 5 );
		add_filter( 'woocommerce_update_cart_validation', array( __CLASS__, 'validate_update' ), 20, 4 );
		add_action( 'woocommerce_check_cart_items', array( __CLASS__, 'check_cart' ), 20 );
		add_action( 'woocommerce_store_api_cart_errors', array( __CLASS__, 'store_api_errors' ), 20, 2 );
	}

	/** Quantités par règle dans le panier, hors ligne $skip_key. */
	private static function cart_quantities( $skip_key = null ) {
		$q = array();
		if ( ! WC()->cart ) {
			return $q;
		}
		foreach ( WC()->cart->get_cart() as $key => $item ) {
			if ( null !== $skip_key && $key === $skip_key ) {
				continue;
			}
			$rule = WSPO_Service::find_rule( $item['product_id'], isset( $item['variation_id'] ) ? (int) $item['variation_id'] : 0 );
			if ( $rule ) {
				$id       = (int) $rule['id'];
				$q[ $id ] = ( isset( $q[ $id ] ) ? $q[ $id ] : 0 ) + (int) $item['quantity'];
			}
		}
		return $q;
	}

	/**
	 * Contrôle les limites d'une règle pour une quantité totale demandée.
	 *
	 * @return string|null message d'erreur
	 */
	public static function check_rule( array $rule, $qty_total, $name ) {
		$remaining = WSPO_Service::remaining( $rule );
		if ( null !== $remaining && $qty_total > $remaining ) {
			if ( $remaining <= 0 ) {
				/* translators: %s: product name */
				return sprintf( __( '« %s » : les précommandes sont complètes, il n’y a plus de place disponible.', 'websource-preorder' ), $name );
			}
			/* translators: 1: product name, 2: remaining quantity */
			return sprintf( _n( '« %1$s » : il ne reste que %2$d exemplaire disponible en précommande.', '« %1$s » : il ne reste que %2$d exemplaires disponibles en précommande.', $remaining, 'websource-preorder' ), $name, $remaining );
		}
		$max = (int) $rule['max_order'];
		if ( $max > 0 && $qty_total > $max ) {
			/* translators: 1: product name, 2: max quantity */
			return sprintf( __( '« %1$s » : maximum %2$d par commande.', 'websource-preorder' ), $name, $max );
		}
		$maxc = (int) $rule['max_customer'];
		if ( $maxc > 0 && is_user_logged_in() ) {
			$already = WSPO_Service::booked( $rule['id'], get_current_user_id() );
			if ( $already + $qty_total > $maxc ) {
				if ( $already > 0 ) {
					/* translators: 1: product name, 2: max quantity, 3: already booked */
					return sprintf( __( '« %1$s » : maximum %2$d par client (vous en avez déjà précommandé %3$d).', 'websource-preorder' ), $name, $maxc, $already );
				}
				/* translators: 1: product name, 2: max quantity */
				return sprintf( __( '« %1$s » : maximum %2$d par client.', 'websource-preorder' ), $name, $maxc );
			}
		}
		return null;
	}

	private static function mixed_message() {
		return __( 'Les précommandes doivent être commandées séparément des produits en stock. Videz ou finalisez d’abord votre panier actuel.', 'websource-preorder' );
	}

	public static function validate_add( $passed, $product_id, $quantity, $variation_id = 0, $variations = null ) {
		if ( ! $passed || ! WC()->cart ) {
			return $passed;
		}
		$rule = WSPO_Service::find_rule( $product_id, (int) $variation_id );
		if ( 'forbid' === WSPO_Settings::get( 'mixed_cart' ) && ! WC()->cart->is_empty() ) {
			$cart_pre = self::cart_has_preorder_and_regular();
			// Le panier contient déjà quelque chose : on exige la même nature que l'article ajouté.
			if ( $rule && $cart_pre['regular'] ) {
				wc_add_notice( self::mixed_message(), 'error' );
				return false;
			}
			if ( ! $rule && $cart_pre['preorder'] ) {
				wc_add_notice( self::mixed_message(), 'error' );
				return false;
			}
		}
		if ( ! $rule ) {
			return $passed;
		}
		$qty  = self::cart_quantities();
		$have = isset( $qty[ (int) $rule['id'] ] ) ? $qty[ (int) $rule['id'] ] : 0;
		$p    = wc_get_product( $variation_id ? $variation_id : $product_id );
		$msg  = self::check_rule( $rule, $have + (int) $quantity, $p ? $p->get_name() : '' );
		if ( $msg ) {
			wc_add_notice( $msg, 'error' );
			return false;
		}
		return $passed;
	}

	public static function validate_update( $passed, $cart_item_key, $values, $quantity ) {
		if ( ! $passed ) {
			return $passed;
		}
		$rule = WSPO_Service::find_rule( $values['product_id'], isset( $values['variation_id'] ) ? (int) $values['variation_id'] : 0 );
		if ( ! $rule ) {
			return $passed;
		}
		$qty  = self::cart_quantities( $cart_item_key );
		$have = isset( $qty[ (int) $rule['id'] ] ) ? $qty[ (int) $rule['id'] ] : 0;
		$msg  = self::check_rule( $rule, $have + (int) $quantity, $values['data']->get_name() );
		if ( $msg ) {
			wc_add_notice( $msg, 'error' );
			return false;
		}
		return $passed;
	}

	private static function cart_has_preorder_and_regular() {
		$r = array( 'preorder' => false, 'regular' => false );
		foreach ( WC()->cart->get_cart() as $item ) {
			if ( WSPO_Service::find_rule( $item['product_id'], isset( $item['variation_id'] ) ? (int) $item['variation_id'] : 0 ) ) {
				$r['preorder'] = true;
			} else {
				$r['regular'] = true;
			}
		}
		return $r;
	}

	/** Messages d'erreur du panier complet. @return string[] */
	public static function cart_errors() {
		$errors = array();
		if ( ! WC()->cart ) {
			return $errors;
		}
		if ( 'forbid' === WSPO_Settings::get( 'mixed_cart' ) ) {
			$c = self::cart_has_preorder_and_regular();
			if ( $c['preorder'] && $c['regular'] ) {
				$errors['mixed'] = self::mixed_message();
			}
		}
		$qty = self::cart_quantities();
		foreach ( $qty as $rule_id => $total ) {
			$rule = WSPO_Service::get_rule( $rule_id );
			if ( ! $rule ) {
				continue;
			}
			$p    = wc_get_product( $rule['variation_id'] ? $rule['variation_id'] : $rule['product_id'] );
			$name = $p ? $p->get_name() : '';
			$msg  = self::check_rule( $rule, $total, $name );
			if ( $msg ) {
				$errors[ 'rule' . $rule_id ] = $msg;
			}
		}
		return $errors;
	}

	public static function check_cart() {
		foreach ( self::cart_errors() as $msg ) {
			wc_add_notice( $msg, 'error' );
		}
	}

	public static function store_api_errors( $errors, $cart ) {
		foreach ( self::cart_errors() as $code => $msg ) {
			$errors->add( 'wspo_' . $code, $msg );
		}
	}
}

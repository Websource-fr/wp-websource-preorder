<?php
/**
 * Affichage côté boutique : fiche produit, boucle, panier, accueil, bandeau, comptes, liste, alertes.
 *
 * @package WebsourcePreorder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSPO_Front {

	const ENDPOINT = 'wspo-bookings';

	private static $done = array();

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'login_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'init', array( __CLASS__, 'register_shortcodes_and_blocks' ) );
		add_action( 'widgets_init', function () {
			register_widget( 'WSPO_Widget' );
		} );

		// Fiche produit (thèmes classiques ET thèmes de blocs : le hook est dans les gabarits d'ajout au panier).
		add_action( 'woocommerce_before_add_to_cart_form', array( __CLASS__, 'product_box' ), 5 );
		add_action( 'woocommerce_after_add_to_cart_form', array( __CLASS__, 'product_subscribe' ), 20 );
		add_filter( 'woocommerce_product_single_add_to_cart_text', array( __CLASS__, 'button_text' ), 20, 2 );
		add_filter( 'woocommerce_product_add_to_cart_text', array( __CLASS__, 'loop_button_text' ), 20, 2 );
		add_filter( 'woocommerce_available_variation', array( __CLASS__, 'variation_data' ), 20, 3 );
		add_filter( 'woocommerce_quantity_input_args', array( __CLASS__, 'quantity_args' ), 20, 2 );

		// Boucle (classique + bloc « Image du produit »).
		add_action( 'woocommerce_before_shop_loop_item_title', array( __CLASS__, 'loop_badge' ), 9 );
		add_filter( 'render_block_woocommerce/product-image', array( __CLASS__, 'block_loop_badge' ), 20, 3 );

		// Panier.
		add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'cart_item_data' ), 20, 2 );
		add_action( 'woocommerce_before_cart', array( __CLASS__, 'cart_notice' ) );
		add_action( 'woocommerce_before_checkout_form', array( __CLASS__, 'cart_notice' ), 5 );
		add_filter( 'render_block', array( __CLASS__, 'cart_block_notice' ), 20, 2 );

		// Accueil, bandeau, pied de page.
		add_action( 'wp_body_open', array( __CLASS__, 'ticker_top' ), 5 );
		add_action( 'wp_footer', array( __CLASS__, 'footer_fallbacks' ), 5 );
		add_filter( 'the_content', array( __CLASS__, 'home_content' ), 5 );
		add_action( 'loop_start', array( __CLASS__, 'home_loop_start' ) );
		add_filter( 'render_block_core/query', array( __CLASS__, 'home_query_block' ), 5 );
		add_action( 'get_footer', array( __CLASS__, 'classic_before_footer' ) );
		add_filter( 'render_block_core/template-part', array( __CLASS__, 'block_before_footer' ), 5, 2 );
		add_action( 'dynamic_sidebar_before', array( __CLASS__, 'sidebar_form' ), 10, 2 );

		// Pages de comptes.
		add_action( 'woocommerce_before_customer_login_form', array( __CLASS__, 'account_login_block' ) );
		add_action( 'woocommerce_before_lost_password_form', array( __CLASS__, 'account_password_block' ) );
		add_filter( 'login_message', array( __CLASS__, 'wp_login_block' ) );

		// Mon compte.
		add_filter( 'woocommerce_get_query_vars', array( __CLASS__, 'query_vars' ) );
		add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'account_menu' ) );
		add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', array( __CLASS__, 'account_endpoint' ) );
		add_filter( 'woocommerce_endpoint_' . self::ENDPOINT . '_title', function () {
			return __( 'Mes précommandes', 'websource-preorder' );
		} );
		add_action( 'init', array( __CLASS__, 'maybe_flush' ), 99 );
	}

	public static function maybe_flush() {
		if ( get_option( 'wspo_flush_rewrite' ) ) {
			delete_option( 'wspo_flush_rewrite' );
			flush_rewrite_rules( false );
		}
	}

	/* ---------------------------------------------------------------- ressources */

	public static function assets() {
		wp_enqueue_style( 'wspo-front', WSPO_URL . 'assets/css/front.css', array(), WSPO_VERSION );
		$s   = WSPO_Settings::all();
		$css = ':root{--wspo-accent:' . sanitize_hex_color( $s['c_accent'] ) . ';--wspo-on-accent:' . sanitize_hex_color( $s['c_on_accent'] )
			. ';--wspo-box-bg:' . sanitize_hex_color( $s['c_box_bg'] ) . ';--wspo-border:' . sanitize_hex_color( $s['c_border'] )
			. ';--wspo-count-bg:' . sanitize_hex_color( $s['c_count_bg'] ) . ';--wspo-count-text:' . sanitize_hex_color( $s['c_count_text'] )
			. ';--wspo-badge-bg:' . sanitize_hex_color( $s['c_badge_bg'] ) . ';--wspo-ticker-bg:' . sanitize_hex_color( $s['c_ticker_bg'] )
			. ';--wspo-ticker-text:' . sanitize_hex_color( $s['c_ticker_text'] ) . ';}';
		wp_add_inline_style( 'wspo-front', $css );
		wp_enqueue_script( 'wspo-front', WSPO_URL . 'assets/js/front.js', array(), WSPO_VERSION, true );
		wp_localize_script(
			'wspo-front',
			'WSPO',
			array(
				'serverNow' => time(),
				'speed'     => max( 10, (int) $s['ticker_speed'] ),
				'done'      => __( 'Disponible maintenant !', 'websource-preorder' ),
				'units'     => array(
					'd' => array( __( 'jour', 'websource-preorder' ), __( 'jours', 'websource-preorder' ) ),
					'h' => array( __( 'heure', 'websource-preorder' ), __( 'heures', 'websource-preorder' ) ),
					'm' => array( __( 'minute', 'websource-preorder' ), __( 'minutes', 'websource-preorder' ) ),
					's' => array( __( 'seconde', 'websource-preorder' ), __( 'secondes', 'websource-preorder' ) ),
				),
			)
		);
	}

	/* ---------------------------------------------------------------- utilitaires */

	public static function list_url() {
		$id = (int) WSPO_Settings::get( 'list_page_id' );
		if ( $id && 'publish' === get_post_status( $id ) ) {
			return get_permalink( $id );
		}
		return home_url( '/' );
	}

	/** Crée ou met à jour la page publique de la liste (slug configurable). */
	public static function ensure_list_page() {
		$s     = WSPO_Settings::all();
		$id    = (int) $s['list_page_id'];
		$slug  = sanitize_title( $s['list_slug'] ? $s['list_slug'] : 'precommandes' );
		$title = $s['list_title'];
		if ( $id && get_post( $id ) && 'trash' !== get_post_status( $id ) ) {
			wp_update_post( array( 'ID' => $id, 'post_name' => $slug, 'post_title' => $title ) );
		} else {
			$id = wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_title'   => $title,
					'post_name'    => $slug,
					'post_content' => '[websource_preorders]',
				)
			);
			if ( is_wp_error( $id ) ) {
				return;
			}
		}
		WSPO_Settings::update( array( 'list_page_id' => (int) $id, 'list_slug' => get_post_field( 'post_name', $id ) ) );
	}

	private static function once( $key ) {
		if ( isset( self::$done[ $key ] ) ) {
			return false;
		}
		self::$done[ $key ] = true;
		return true;
	}

	public static function badge_label( $rule = null ) {
		if ( $rule && '' !== trim( (string) $rule['label'] ) ) {
			return $rule['label'];
		}
		return WSPO_Settings::get( 'badge_label' );
	}

	public static function btn_label( $rule = null ) {
		if ( $rule && '' !== trim( (string) $rule['label'] ) ) {
			return $rule['label'];
		}
		return WSPO_Settings::get( 'btn_label' );
	}

	/** Pourcentage de progression vers la sortie. */
	public static function progress( array $rule ) {
		$end   = wspo_ts( $rule['date_release'] );
		$start = wspo_ts( $rule['date_start'] ) ? wspo_ts( $rule['date_start'] ) : wspo_ts( $rule['date_add'] );
		if ( ! $end || ! $start || $end <= $start ) {
			return 0;
		}
		return (int) max( 0, min( 100, round( ( time() - $start ) / ( $end - $start ) * 100 ) ) );
	}

	/** Rend un élément (règle + produit) via le gabarit demandé. */
	public static function render( $template, array $args ) {
		return wspo_get_template( $template, $args, false );
	}

	public static function countdown_html( array $rule ) {
		$ts = wspo_ts( $rule['date_release'] );
		if ( ! $ts || ! WSPO_Settings::get( 'show_countdown' ) ) {
			return '';
		}
		return '<div class="wspo-countdown" data-release="' . (int) $ts . '" data-mode="' . esc_attr( WSPO_Settings::get( 'countdown_mode' ) ) . '" role="timer">'
			. '<span class="wspo-countdown__fallback">' . esc_html( wspo_format_date( $rule['date_release'], true ) ) . '</span></div>';
	}

	/* ---------------------------------------------------------------- fiche produit */

	public static function product_box() {
		global $product;
		if ( ! $product instanceof WC_Product ) {
			return;
		}
		$rule = null;
		if ( $product->is_type( 'variable' ) ) {
			$rule = WSPO_Service::find_rule( $product->get_id(), null );
		} else {
			$rule = WSPO_Service::rule_for_product( $product );
		}
		if ( ! $rule || ! self::once( 'box_' . $product->get_id() ) ) {
			return;
		}
		wspo_get_template(
			'product-box',
			array(
				'rule'      => $rule,
				'product'   => $product,
				'remaining' => WSPO_Service::remaining( $rule ),
				'progress'  => self::progress( $rule ),
				'badge'     => self::badge_label( $rule ),
			)
		);
	}

	public static function product_subscribe() {
		global $product;
		if ( ! $product instanceof WC_Product || ! WSPO_Settings::get( 'sub_product' ) || ! self::once( 'psub_' . $product->get_id() ) ) {
			return;
		}
		echo self::subscribe_form( 'product' ); // phpcs:ignore WordPress.Security.EscapeOutput
	}

	public static function button_text( $text, $product ) {
		if ( $product->is_type( 'variable' ) ) {
			$rule = WSPO_Service::find_rule( $product->get_id(), 0 );
			return ( $rule && 0 === (int) $rule['variation_id'] ) ? self::btn_label( $rule ) : $text;
		}
		$rule = WSPO_Service::rule_for_product( $product );
		return $rule ? self::btn_label( $rule ) : $text;
	}

	public static function loop_button_text( $text, $product ) {
		if ( ! $product->is_type( 'simple' ) ) {
			return $text;
		}
		$rule = WSPO_Service::rule_for_product( $product );
		return $rule ? self::btn_label( $rule ) : $text;
	}

	public static function variation_data( $data, $product, $variation ) {
		$rule = WSPO_Service::rule_for_product( $variation );
		if ( $rule ) {
			$data['wspo_btn'] = self::btn_label( $rule );
			$data['wspo_rel'] = wspo_ts( $rule['date_release'] ) ? sprintf( /* translators: %s: date */ __( 'Sortie prévue le %s', 'websource-preorder' ), wspo_format_date( $rule['date_release'] ) ) : '';
		}
		return $data;
	}

	public static function quantity_args( $args, $product ) {
		$rule = WSPO_Service::rule_for_product( $product );
		if ( ! $rule ) {
			return $args;
		}
		$caps = array();
		$rem  = WSPO_Service::remaining( $rule );
		if ( null !== $rem ) {
			$caps[] = max( 1, $rem );
		}
		if ( (int) $rule['max_order'] > 0 ) {
			$caps[] = (int) $rule['max_order'];
		}
		if ( (int) $rule['max_customer'] > 0 && is_user_logged_in() ) {
			$caps[] = max( 1, (int) $rule['max_customer'] - WSPO_Service::booked( $rule['id'], get_current_user_id() ) );
		}
		if ( $caps ) {
			$max = min( $caps );
			if ( empty( $args['max_value'] ) || $args['max_value'] < 0 || $args['max_value'] > $max ) {
				$args['max_value'] = $max;
			}
		}
		return $args;
	}

	/* ---------------------------------------------------------------- boucle */

	public static function badge_html( $rule ) {
		if ( ! WSPO_Settings::get( 'show_flag' ) ) {
			return '';
		}
		return '<span class="wspo-badge wspo-badge--loop">' . esc_html( self::badge_label( $rule ) ) . '</span>';
	}

	public static function loop_badge() {
		global $product;
		if ( $product instanceof WC_Product ) {
			$rule = $product->is_type( 'variable' ) ? WSPO_Service::find_rule( $product->get_id(), null ) : WSPO_Service::rule_for_product( $product );
			if ( $rule ) {
				echo self::badge_html( $rule ); // phpcs:ignore WordPress.Security.EscapeOutput
			}
		}
	}

	public static function block_loop_badge( $html, $parsed, $block ) {
		$pid = isset( $block->context['postId'] ) ? (int) $block->context['postId'] : 0;
		$p   = $pid ? wc_get_product( $pid ) : null;
		if ( ! $p ) {
			return $html;
		}
		$rule = $p->is_type( 'variable' ) ? WSPO_Service::find_rule( $p->get_id(), null ) : WSPO_Service::rule_for_product( $p );
		if ( ! $rule ) {
			return $html;
		}
		$badge = self::badge_html( $rule );
		$out   = preg_replace( '~</div>\s*$~', $badge . '</div>', $html, 1 );
		return $out ? $out : $html;
	}

	/* ---------------------------------------------------------------- panier */

	public static function cart_item_data( $data, $cart_item ) {
		$rule = WSPO_Service::find_rule( $cart_item['product_id'], isset( $cart_item['variation_id'] ) ? (int) $cart_item['variation_id'] : 0 );
		if ( $rule ) {
			$data[] = array(
				'key'     => __( 'Précommande', 'websource-preorder' ),
				'value'   => wspo_ts( $rule['date_release'] ) ? sprintf( /* translators: %s: date */ __( 'Sortie prévue le %s', 'websource-preorder' ), wspo_format_date( $rule['date_release'] ) ) : __( 'Oui', 'websource-preorder' ),
				'display' => '',
			);
		}
		return $data;
	}

	private static function cart_notice_text() {
		if ( ! WSPO_Settings::get( 'cart_notice' ) || ! WC()->cart ) {
			return '';
		}
		$dates = array();
		foreach ( WC()->cart->get_cart() as $item ) {
			$rule = WSPO_Service::find_rule( $item['product_id'], isset( $item['variation_id'] ) ? (int) $item['variation_id'] : 0 );
			if ( $rule ) {
				$dates[] = wspo_ts( $rule['date_release'] );
			}
		}
		if ( ! $dates ) {
			return '';
		}
		$dates = array_filter( $dates );
		$msg   = __( 'Votre panier contient des précommandes : elles seront expédiées à leur date de sortie.', 'websource-preorder' );
		if ( $dates ) {
			/* translators: %s: date */
			$msg .= ' ' . sprintf( __( 'Prochaine sortie prévue le %s.', 'websource-preorder' ), wp_date( get_option( 'date_format' ), min( $dates ) ) );
		}
		return $msg;
	}

	public static function cart_notice() {
		$msg = self::cart_notice_text();
		if ( $msg && self::once( 'cart_notice_classic' ) ) {
			wc_print_notice( esc_html( $msg ), 'notice' );
		}
	}

	public static function cart_block_notice( $html, $block ) {
		if ( empty( $block['blockName'] ) || ! in_array( $block['blockName'], array( 'woocommerce/cart', 'woocommerce/checkout' ), true ) ) {
			return $html;
		}
		$msg = self::cart_notice_text();
		if ( ! $msg ) {
			return $html;
		}
		return '<div class="wspo-cart-notice">' . esc_html( $msg ) . '</div>' . $html;
	}

	/* ---------------------------------------------------------------- bandeau, à la une */

	public static function ticker_html() {
		$s     = WSPO_Settings::all();
		$items = WSPO_Service::get_items( array( 'limit' => max( 1, (int) $s['ticker_max'] ) ) );
		if ( ! $items ) {
			return '';
		}
		return self::render( 'ticker', array( 'items' => $items, 'prefix' => $s['ticker_prefix'] ) );
	}

	private static function ticker_allowed_here() {
		return 'home' === WSPO_Settings::get( 'ticker_pos' ) ? false : ( 'all' === WSPO_Settings::get( 'ticker_pages' ) || is_front_page() );
	}

	public static function ticker_top() {
		if ( is_admin() || ! WSPO_Settings::get( 'ticker_on' ) || 'top' !== WSPO_Settings::get( 'ticker_pos' ) || ! self::ticker_allowed_here() ) {
			return;
		}
		if ( self::once( 'ticker' ) ) {
			echo self::ticker_html(); // phpcs:ignore WordPress.Security.EscapeOutput
		}
	}

	/** Thèmes sans wp_body_open : on injecte quand même le bandeau en haut via JS. */
	public static function footer_fallbacks() {
		if ( is_admin() || ! WSPO_Settings::get( 'ticker_on' ) || 'top' !== WSPO_Settings::get( 'ticker_pos' ) || ! self::ticker_allowed_here() ) {
			return;
		}
		if ( self::once( 'ticker' ) ) {
			$html = self::ticker_html();
			if ( $html ) {
				echo '<div id="wspo-ticker-late">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
				echo '<script>(function(){var e=document.getElementById("wspo-ticker-late");if(e&&document.body.firstChild){document.body.insertBefore(e,document.body.firstChild);}})();</script>';
			}
		}
	}

	public static function featured_html() {
		$items = WSPO_Service::get_items( array( 'limit' => 1 ) );
		if ( ! $items ) {
			return '';
		}
		$it = $items[0];
		return self::render(
			'featured',
			array(
				'item'     => $it,
				'title'    => WSPO_Settings::get( 'home_featured_title' ),
				'progress' => self::progress( $it['rule'] ),
				'badge'    => self::badge_label( $it['rule'] ),
			)
		);
	}

	/** Contenu du haut de l'accueil : bandeau (position « accueil »), produit à la une, alertes. */
	private static function home_html() {
		$s    = WSPO_Settings::all();
		$html = '';
		if ( $s['ticker_on'] && 'home' === $s['ticker_pos'] && self::once( 'ticker' ) ) {
			$html .= self::ticker_html();
		}
		if ( $s['home_featured'] ) {
			$html .= self::featured_html();
		}
		if ( $s['sub_home'] ) {
			$html .= self::subscribe_form( 'home' );
		}
		return $html;
	}

	public static function home_content( $content ) {
		if ( is_admin() || ! is_front_page() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		if ( get_queried_object_id() !== get_the_ID() || ! self::once( 'home' ) ) {
			return $content;
		}
		return self::home_html() . $content;
	}

	public static function home_loop_start( $query ) {
		if ( is_admin() || wp_is_block_theme() || ! $query->is_main_query() || ! is_front_page() || ! is_home() ) {
			return;
		}
		if ( self::once( 'home' ) ) {
			echo self::home_html(); // phpcs:ignore WordPress.Security.EscapeOutput
		}
	}

	public static function home_query_block( $html ) {
		if ( is_admin() || ! is_front_page() || ! is_home() || ! self::once( 'home' ) ) {
			return $html;
		}
		return self::home_html() . $html;
	}

	/** Avant le pied de page : bandeau (si choisi) et formulaire d'alerte. */
	private static function before_footer_html() {
		$s    = WSPO_Settings::all();
		$html = '';
		if ( $s['ticker_on'] && 'footer' === $s['ticker_pos'] && self::ticker_allowed_here() && self::once( 'ticker' ) ) {
			$html .= self::ticker_html();
		}
		if ( $s['sub_footer'] && self::once( 'sub_footer' ) ) {
			$html .= '<div class="wspo-footer-sub">' . self::subscribe_form( 'footer' ) . '</div>';
		}
		return $html;
	}

	public static function classic_before_footer() {
		if ( ! is_admin() ) {
			echo self::before_footer_html(); // phpcs:ignore WordPress.Security.EscapeOutput
		}
	}

	public static function block_before_footer( $html, $block ) {
		if ( is_admin() || empty( $block['attrs']['slug'] ) || 'footer' !== $block['attrs']['slug'] ) {
			return $html;
		}
		return self::before_footer_html() . $html;
	}

	public static function sidebar_form( $index, $has_widgets ) {
		$s    = WSPO_Settings::all();
		$id   = strtolower( (string) $index );
		$name = isset( $GLOBALS['wp_registered_sidebars'][ $index ]['name'] ) ? strtolower( $GLOBALS['wp_registered_sidebars'][ $index ]['name'] ) : '';
		$hay  = $id . ' ' . $name;
		$left = (bool) preg_match( '/left|gauche/', $hay );
		if ( $left && $s['sub_left'] && self::once( 'sub_left' ) ) {
			echo self::subscribe_form( 'left' ); // phpcs:ignore WordPress.Security.EscapeOutput
		} elseif ( ! $left && $s['sub_right'] && ( preg_match( '/right|droite|sidebar-1|primary/', $hay ) ) && self::once( 'sub_right' ) ) {
			echo self::subscribe_form( 'right' ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
	}

	/* ---------------------------------------------------------------- formulaire d'alerte */

	public static function subscribe_form( $source = 'form', $args = array() ) {
		$s = WSPO_Settings::all();
		$a = wp_parse_args(
			$args,
			array(
				'title' => $s['sub_title'],
				'text'  => $s['sub_text'],
			)
		);
		$msg = '';
		if ( isset( $_GET['wspo_msg'], $_GET['wspo_src'] ) && sanitize_key( wp_unslash( $_GET['wspo_src'] ) ) === $source ) { // phpcs:ignore WordPress.Security.NonceVerification
			$code = sanitize_key( wp_unslash( $_GET['wspo_msg'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			$map  = array(
				'sent'      => array( 'ok', __( 'Merci ! Consultez votre boîte mail pour confirmer votre inscription.', 'websource-preorder' ) ),
				'confirmed' => array( 'ok', __( 'Merci, votre inscription est enregistrée.', 'websource-preorder' ) ),
				'exists'    => array( 'ok', __( 'Cette adresse est déjà inscrite aux alertes.', 'websource-preorder' ) ),
				'invalid'   => array( 'err', __( 'Adresse e-mail invalide.', 'websource-preorder' ) ),
				'consent'   => array( 'err', __( 'Merci de cocher la case de consentement.', 'websource-preorder' ) ),
				'spam'      => array( 'err', __( 'Envoi refusé (protection anti-spam). Réessayez dans quelques secondes.', 'websource-preorder' ) ),
				'error'     => array( 'err', __( 'Une erreur est survenue, veuillez réessayer.', 'websource-preorder' ) ),
			);
			if ( isset( $map[ $code ] ) ) {
				$msg = '<div class="wspo-sub__msg wspo-sub__msg--' . esc_attr( $map[ $code ][0] ) . '" role="status">' . esc_html( $map[ $code ][1] ) . '</div>';
			}
		}
		return self::render(
			'subscribe-form',
			array(
				'source'  => $source,
				'title'   => $a['title'],
				'text'    => $a['text'],
				'consent' => $s['sub_consent'],
				'message' => $msg,
				'token'   => WSPO_Subscribers::time_token(),
			)
		);
	}

	/* ---------------------------------------------------------------- liste */

	public static function list_html( $args = array() ) {
		$s      = WSPO_Settings::all();
		$items  = WSPO_Service::get_items( array( 'in_list_only' => true ) );
		$groups = array();
		foreach ( $items as $it ) {
			$label = ( 'month' === $s['list_group'] && $it['release'] ) ? ucfirst( wp_date( 'F Y', $it['release'] ) ) : '';
			if ( 'month' === $s['list_group'] && ! $it['release'] ) {
				$label = __( 'Date à venir', 'websource-preorder' );
			}
			$groups[ $label ][] = $it;
		}
		return self::render( 'list', array( 'groups' => $groups, 'intro' => $s['list_intro'], 'empty' => ! $items ) );
	}

	/* ---------------------------------------------------------------- blocs des pages de compte */

	private static function account_html() {
		$s     = WSPO_Settings::all();
		$items = WSPO_Service::get_items( array( 'in_list_only' => true, 'limit' => max( 0, (int) $s['blk_products'] ) ) );
		if ( ! $items && ! $s['blk_form'] ) {
			return '';
		}
		return self::render(
			'account-block',
			array(
				'title' => $s['blk_title'],
				'text'  => $s['blk_text'],
				'items' => (int) $s['blk_products'] > 0 ? $items : array(),
				'form'  => $s['blk_form'] ? self::subscribe_form( 'account', array( 'title' => '', 'text' => '' ) ) : '',
			)
		);
	}

	public static function account_login_block() {
		if ( ( WSPO_Settings::get( 'blk_login' ) || WSPO_Settings::get( 'blk_register' ) ) && self::once( 'account' ) ) {
			echo self::account_html(); // phpcs:ignore WordPress.Security.EscapeOutput
		}
	}

	public static function account_password_block() {
		if ( WSPO_Settings::get( 'blk_password' ) && self::once( 'account' ) ) {
			echo self::account_html(); // phpcs:ignore WordPress.Security.EscapeOutput
		}
	}

	/** Page wp-login.php : connexion, inscription, mot de passe perdu. */
	public static function wp_login_block( $message ) {
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : 'login'; // phpcs:ignore WordPress.Security.NonceVerification
		$map    = array( 'login' => 'blk_login', 'register' => 'blk_register', 'lostpassword' => 'blk_password', 'retrievepassword' => 'blk_password' );
		if ( isset( $map[ $action ] ) && WSPO_Settings::get( $map[ $action ] ) && self::once( 'account_wplogin' ) ) {
			$message .= self::account_html();
		}
		return $message;
	}

	/* ---------------------------------------------------------------- Mon compte */

	public static function query_vars( $vars ) {
		$vars[ self::ENDPOINT ] = 'mes-precommandes';
		return $vars;
	}

	public static function account_menu( $items ) {
		$out = array();
		foreach ( $items as $k => $v ) {
			if ( 'customer-logout' === $k ) {
				$out[ self::ENDPOINT ] = __( 'Mes précommandes', 'websource-preorder' );
			}
			$out[ $k ] = $v;
		}
		if ( ! isset( $out[ self::ENDPOINT ] ) ) {
			$out[ self::ENDPOINT ] = __( 'Mes précommandes', 'websource-preorder' );
		}
		return $out;
	}

	public static function account_endpoint() {
		global $wpdb;
		$uid  = get_current_user_id();
		$user = wp_get_current_user();
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . WSPO_Service::table( 'bookings' ) . ' WHERE (customer_id = %d OR (customer_id = 0 AND email = %s)) ORDER BY id DESC', // phpcs:ignore WordPress.DB.PreparedSQL
				$uid,
				$user->user_email
			),
			ARRAY_A
		);
		wspo_get_template( 'my-preorders', array( 'bookings' => (array) $rows ) );
	}

	/* ---------------------------------------------------------------- shortcodes et blocs */

	public static function register_shortcodes_and_blocks() {
		add_shortcode( 'websource_preorders', function () {
			return self::list_html();
		} );
		add_shortcode( 'websource_preorder_featured', function () {
			return self::featured_html();
		} );
		add_shortcode( 'websource_preorder_ticker', function () {
			return self::ticker_html();
		} );
		add_shortcode( 'websource_preorder_subscribe', function ( $atts ) {
			$a = shortcode_atts( array( 'title' => WSPO_Settings::get( 'sub_title' ), 'text' => WSPO_Settings::get( 'sub_text' ) ), $atts );
			return self::subscribe_form( 'shortcode', $a );
		} );

		wp_register_script(
			'wspo-blocks',
			WSPO_URL . 'assets/js/blocks.js',
			array( 'wp-blocks', 'wp-element', 'wp-server-side-render', 'wp-block-editor', 'wp-i18n' ),
			WSPO_VERSION,
			true
		);
		$blocks = array(
			'featured'  => array( __CLASS__, 'featured_html' ),
			'ticker'    => array( __CLASS__, 'ticker_html' ),
			'list'      => array( __CLASS__, 'list_html' ),
			'subscribe' => function () {
				return self::subscribe_form( 'block' );
			},
		);
		foreach ( $blocks as $name => $cb ) {
			register_block_type(
				'websource-preorder/' . $name,
				array(
					'editor_script'   => 'wspo-blocks',
					'render_callback' => function () use ( $cb ) {
						return call_user_func( $cb );
					},
				)
			);
		}
	}
}

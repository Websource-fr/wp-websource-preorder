<?php
/**
 * Administration : menus, tableau de bord, règles, réservations, abonnés, réglages, e-mails.
 *
 * @package WebsourcePreorder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSPO_Admin {

	const CAP = 'manage_woocommerce';

	public static function init() {
		require_once WSPO_DIR . 'admin/class-wspo-support-box.php';
		WSPO_Support_Box::init();
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ), 20 );
		add_filter( 'plugin_action_links_' . plugin_basename( WSPO_FILE ), array( __CLASS__, 'action_links' ) );

		$actions = array( 'save_settings', 'save_mails', 'test_mail', 'save_rule', 'rule_action', 'booking_action', 'export_bookings', 'sub_action', 'export_subs', 'run_digest', 'regen_token' );
		foreach ( $actions as $a ) {
			add_action( 'admin_post_wspo_' . $a, array( __CLASS__, 'do_' . $a ) );
		}
	}

	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=wspo-settings' ) ) . '">' . esc_html__( 'Réglages', 'websource-preorder' ) . '</a>' );
		return $links;
	}

	public static function menu() {
		add_menu_page( __( 'Précommandes', 'websource-preorder' ), __( 'Précommandes', 'websource-preorder' ), self::CAP, 'wspo', array( __CLASS__, 'page_rules' ), 'dashicons-calendar-alt', 56 );
		add_submenu_page( 'wspo', __( 'Précommandes', 'websource-preorder' ), __( 'Précommandes', 'websource-preorder' ), self::CAP, 'wspo', array( __CLASS__, 'page_rules' ) );
		add_submenu_page( 'wspo', __( 'Réservations', 'websource-preorder' ), __( 'Réservations', 'websource-preorder' ), self::CAP, 'wspo-bookings', array( __CLASS__, 'page_bookings' ) );
		add_submenu_page( 'wspo', __( 'Abonnés aux alertes', 'websource-preorder' ), __( 'Abonnés', 'websource-preorder' ), self::CAP, 'wspo-subscribers', array( __CLASS__, 'page_subscribers' ) );
		add_submenu_page( 'wspo', __( 'Réglages', 'websource-preorder' ), __( 'Réglages', 'websource-preorder' ), self::CAP, 'wspo-settings', array( __CLASS__, 'page_settings' ) );
	}

	public static function assets( $hook ) {
		if ( false === strpos( (string) $hook, 'wspo' ) ) {
			return;
		}
		wp_enqueue_style( 'wspo-admin', WSPO_URL . 'assets/css/admin.css', array(), WSPO_VERSION );
		wp_enqueue_style( 'woocommerce_admin_styles' );
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wc-enhanced-select' );
		wp_enqueue_script( 'wspo-admin', WSPO_URL . 'assets/js/admin.js', array( 'jquery', 'wp-color-picker' ), WSPO_VERSION, true );
	}

	/* ---------------------------------------------------------------- utilitaires */

	private static function guard( $nonce_action ) {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Droits insuffisants.', 'websource-preorder' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( $nonce_action );
	}

	private static function back( $page, $args = array() ) {
		wp_safe_redirect( add_query_arg( array_merge( array( 'page' => $page ), $args ), admin_url( 'admin.php' ) ) );
		exit;
	}

	private static function notices() {
		// phpcs:disable WordPress.Security.NonceVerification
		if ( isset( $_GET['wspo_n'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['wspo_n'] ) ) ) . '</p></div>';
		}
		if ( isset( $_GET['wspo_e'] ) ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['wspo_e'] ) ) ) . '</p></div>';
		}
		// phpcs:enable
	}

	private static function csv_send( $filename, array $header, array $rows ) {
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" );
		fputcsv( $out, $header, ';', '"', '\\' );
		foreach ( $rows as $r ) {
			// Neutralise les formules (injection CSV).
			$r = array_map(
				function ( $v ) {
					$v = (string) $v;
					return ( '' !== $v && in_array( $v[0], array( '=', '+', '-', '@' ), true ) ) ? "'" . $v : $v;
				},
				$r
			);
			fputcsv( $out, $r, ';', '"', '\\' );
		}
		fclose( $out );
		exit;
	}

	/* ---------------------------------------------------------------- tableau de bord + règles */

	private static function dashboard() {
		global $wpdb;
		$running  = count( WSPO_Service::running() );
		$sched    = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . WSPO_Service::table( 'rules' ) . ' WHERE state = 0 AND active = 1' ); // phpcs:ignore WordPress.DB.PreparedSQL
		$bookings = (int) $wpdb->get_var( 'SELECT COALESCE(SUM(quantity),0) FROM ' . WSPO_Service::table( 'bookings' ) . ' WHERE status = 0' ); // phpcs:ignore WordPress.DB.PreparedSQL
		$subs     = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . WSPO_Subscribers::table() . ' WHERE confirmed = 1' ); // phpcs:ignore WordPress.DB.PreparedSQL
		$cards    = array(
			array( $running, __( 'Précommandes en cours', 'websource-preorder' ) ),
			array( $sched, __( 'Programmées', 'websource-preorder' ) ),
			array( $bookings, __( 'Unités réservées', 'websource-preorder' ) ),
			array( $subs, __( 'Abonnés aux alertes', 'websource-preorder' ) ),
		);
		echo '<div class="wspo-cards">';
		foreach ( $cards as $c ) {
			echo '<div class="wspo-card-a"><b>' . (int) $c[0] . '</b><span>' . esc_html( $c[1] ) . '</span></div>';
		}
		echo '</div>';
	}

	public static function page_rules() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification
		if ( isset( $_GET['edit'] ) ) {
			self::rule_form( absint( $_GET['edit'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		global $wpdb;
		echo '<div class="wrap"><h1 class="wp-heading-inline">' . esc_html__( 'Précommandes', 'websource-preorder' ) . '</h1> <a class="page-title-action" href="' . esc_url( admin_url( 'admin.php?page=wspo&edit=0' ) ) . '">' . esc_html__( 'Nouvelle précommande', 'websource-preorder' ) . '</a><hr class="wp-header-end">';
		self::notices();
		WSPO_Support_Box::render();
		self::dashboard();
		$rows = $wpdb->get_results( 'SELECT * FROM ' . WSPO_Service::table( 'rules' ) . ' ORDER BY state ASC, date_release ASC', ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
		echo '<table class="widefat striped wspo-table"><thead><tr><th>' . esc_html__( 'Produit', 'websource-preorder' ) . '</th><th>' . esc_html__( 'Statut', 'websource-preorder' ) . '</th><th>' . esc_html__( 'Début', 'websource-preorder' ) . '</th><th>' . esc_html__( 'Sortie', 'websource-preorder' ) . '</th><th>' . esc_html__( 'Prix', 'websource-preorder' ) . '</th><th>' . esc_html__( 'Réservé / quota', 'websource-preorder' ) . '</th><th>' . esc_html__( 'Actions', 'websource-preorder' ) . '</th></tr></thead><tbody>';
		if ( ! $rows ) {
			echo '<tr><td colspan="7">' . esc_html__( 'Aucune précommande. Cliquez sur « Nouvelle précommande » pour commencer.', 'websource-preorder' ) . '</td></tr>';
		}
		$modes = array( 0 => '—', 1 => __( 'Prix fixe', 'websource-preorder' ), 2 => __( 'Remise', 'websource-preorder' ), 3 => __( 'Remise', 'websource-preorder' ) );
		foreach ( $rows as $r ) {
			$p    = wc_get_product( $r['variation_id'] ? $r['variation_id'] : $r['product_id'] );
			$name = $p ? $p->get_formatted_name() : '#' . $r['product_id'] . ' (' . __( 'supprimé', 'websource-preorder' ) . ')';
			$st   = WSPO_Service::desired_state( $r );
			$st_l = WSPO_Service::state_label( $st );
			if ( ! (int) $r['active'] && $st !== WSPO_Service::S_ENDED ) {
				$st_l = __( 'Désactivée', 'websource-preorder' );
			}
			$booked = WSPO_Service::booked( $r['id'] );
			$price  = (int) $r['price_mode'] ? $modes[ (int) $r['price_mode'] ] . ' ' . ( 2 === (int) $r['price_mode'] ? (float) $r['price_value'] . ' %' : wp_strip_all_tags( wc_price( $r['price_value'] ) ) ) : '—';
			$edit   = admin_url( 'admin.php?page=wspo&edit=' . (int) $r['id'] );
			$rel    = wp_nonce_url( admin_url( 'admin-post.php?action=wspo_rule_action&do=release&id=' . (int) $r['id'] ), 'wspo_rule_' . (int) $r['id'] );
			$del    = wp_nonce_url( admin_url( 'admin-post.php?action=wspo_rule_action&do=delete&id=' . (int) $r['id'] ), 'wspo_rule_' . (int) $r['id'] );
			echo '<tr><td><a href="' . esc_url( $edit ) . '"><strong>' . esc_html( wp_strip_all_tags( $name ) ) . '</strong></a></td>'
				. '<td><span class="wspo-state wspo-state-' . (int) $st . '">' . esc_html( $st_l ) . '</span></td>'
				. '<td>' . esc_html( wspo_format_date( $r['date_start'], true ) ? wspo_format_date( $r['date_start'], true ) : '—' ) . '</td>'
				. '<td>' . esc_html( wspo_format_date( $r['date_release'], true ) ) . '</td>'
				. '<td>' . esc_html( $price ) . '</td>'
				. '<td>' . (int) $booked . ( (int) $r['quota'] ? ' / ' . (int) $r['quota'] : ' / ∞' ) . '</td>'
				. '<td><a href="' . esc_url( $edit ) . '">' . esc_html__( 'Modifier', 'websource-preorder' ) . '</a>';
			if ( $st !== WSPO_Service::S_ENDED ) {
				echo ' | <a href="' . esc_url( $rel ) . '" onclick="return confirm(\'' . esc_js( __( 'Rendre le produit disponible maintenant et prévenir les acheteurs ?', 'websource-preorder' ) ) . '\')">' . esc_html__( 'Disponible maintenant', 'websource-preorder' ) . '</a>';
			}
			echo ' | <a class="submitdelete" href="' . esc_url( $del ) . '" onclick="return confirm(\'' . esc_js( __( 'Supprimer cette précommande ?', 'websource-preorder' ) ) . '\')">' . esc_html__( 'Supprimer', 'websource-preorder' ) . '</a></td></tr>';
		}
		echo '</tbody></table></div>';
	}

	private static function rule_form( $id ) {
		$r = $id ? WSPO_Service::get_rule( $id ) : null;
		if ( $id && ! $r ) {
			echo '<div class="wrap"><p>' . esc_html__( 'Précommande introuvable.', 'websource-preorder' ) . '</p></div>';
			return;
		}
		$d = wp_parse_args(
			$r ? $r : array(),
			array(
				'product_id'     => 0,
				'variation_id'   => 0,
				'active'         => 1,
				'date_start'     => '',
				'date_release'   => '',
				'end_on_release' => 1,
				'date_end'       => '',
				'price_mode'     => 0,
				'price_value'    => 0,
				'quota'          => 0,
				'max_order'      => 0,
				'max_customer'   => 0,
				'label'          => '',
				'message'        => '',
				'in_list'        => 1,
			)
		);
		$sel_id = $d['variation_id'] ? $d['variation_id'] : $d['product_id'];
		$sel    = $sel_id ? wc_get_product( $sel_id ) : null;
		echo '<div class="wrap"><h1>' . esc_html( $id ? __( 'Modifier la précommande', 'websource-preorder' ) : __( 'Nouvelle précommande', 'websource-preorder' ) ) . '</h1>';
		self::notices();
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="wspo-form">';
		echo '<input type="hidden" name="action" value="wspo_save_rule"><input type="hidden" name="rule_id" value="' . (int) $id . '">';
		wp_nonce_field( 'wspo_save_rule' );
		echo '<table class="form-table" role="presentation">';

		echo '<tr><th><label for="wspo_product">' . esc_html__( 'Produit / déclinaison', 'websource-preorder' ) . '</label></th><td>'
			. '<select class="wc-product-search" style="width:50%" id="wspo_product" name="wspo_product" data-placeholder="' . esc_attr__( 'Rechercher un produit…', 'websource-preorder' ) . '" data-action="woocommerce_json_search_products_and_variations" data-allow_clear="true">';
		if ( $sel ) {
			echo '<option value="' . (int) $sel_id . '" selected>' . esc_html( wp_strip_all_tags( $sel->get_formatted_name() ) ) . '</option>';
		}
		echo '</select><p class="description">' . esc_html__( 'Un produit variable = toutes ses déclinaisons ; choisissez une déclinaison pour ne cibler qu’elle.', 'websource-preorder' ) . '</p></td></tr>';

		self::row_check( 'wspo_active', __( 'Activée', 'websource-preorder' ), $d['active'], __( 'Une précommande désactivée n’est jamais appliquée.', 'websource-preorder' ) );
		self::row_input( 'wspo_date_start', __( 'Début des précommandes', 'websource-preorder' ), wspo_utc_to_input( $d['date_start'] ), 'datetime-local', __( 'Vide = dès l’activation. Heure du site.', 'websource-preorder' ) );
		self::row_input( 'wspo_date_release', __( 'Date de sortie', 'websource-preorder' ), wspo_utc_to_input( $d['date_release'] ), 'datetime-local', __( 'Obligatoire. Alimente le compte à rebours et l’e-mail « produit disponible ».', 'websource-preorder' ), true );
		self::row_check( 'wspo_end_on_release', __( 'Clore automatiquement à la date de sortie', 'websource-preorder' ), $d['end_on_release'], __( 'Recommandé : la précommande s’arrête à la sortie, le stock et le prix d’origine sont restaurés.', 'websource-preorder' ) );
		self::row_input( 'wspo_date_end', __( 'Fin anticipée (optionnel)', 'websource-preorder' ), wspo_utc_to_input( $d['date_end'] ), 'datetime-local', __( 'Clôture des ventes avant la sortie.', 'websource-preorder' ) );

		echo '<tr><th><label for="wspo_price_mode">' . esc_html__( 'Prix de précommande', 'websource-preorder' ) . '</label></th><td><select id="wspo_price_mode" name="wspo_price_mode">';
		$modes = array( 0 => __( 'Prix normal du produit', 'websource-preorder' ), 1 => __( 'Prix fixe', 'websource-preorder' ), 2 => __( 'Remise en pourcentage', 'websource-preorder' ), 3 => __( 'Remise en montant', 'websource-preorder' ) );
		foreach ( $modes as $k => $l ) {
			echo '<option value="' . (int) $k . '"' . selected( (int) $d['price_mode'], $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select> <input type="number" step="0.01" min="0" name="wspo_price_value" value="' . esc_attr( (float) $d['price_value'] ) . '" class="small-text"><p class="description">' . esc_html__( 'Appliqué dynamiquement sur le prix normal (le produit n’est jamais modifié). Montants saisis comme les prix de votre catalogue (HT ou TTC selon votre réglage WooCommerce).', 'websource-preorder' ) . '</p></td></tr>';

		self::row_input( 'wspo_quota', __( 'Quota total', 'websource-preorder' ), (int) $d['quota'], 'number', __( '0 = illimité.', 'websource-preorder' ) );
		self::row_input( 'wspo_max_order', __( 'Maximum par commande', 'websource-preorder' ), (int) $d['max_order'], 'number', __( '0 = illimité.', 'websource-preorder' ) );
		self::row_input( 'wspo_max_customer', __( 'Maximum par client', 'websource-preorder' ), (int) $d['max_customer'], 'number', __( '0 = illimité. S’applique aux clients connectés.', 'websource-preorder' ) );
		self::row_input( 'wspo_label', __( 'Libellé personnalisé (badge et bouton)', 'websource-preorder' ), $d['label'], 'text', __( 'Vide = libellés des réglages.', 'websource-preorder' ) );
		echo '<tr><th><label for="wspo_message">' . esc_html__( 'Message sur la fiche produit', 'websource-preorder' ) . '</label></th><td><textarea id="wspo_message" name="wspo_message" rows="3" class="large-text">' . esc_textarea( (string) $d['message'] ) . '</textarea></td></tr>';
		self::row_check( 'wspo_in_list', __( 'Afficher dans les listes', 'websource-preorder' ), $d['in_list'], __( 'Page « Toutes les précommandes », bandeau, à la une, blocs de comptes et récapitulatif.', 'websource-preorder' ) );
		echo '</table>';
		submit_button( $id ? __( 'Enregistrer', 'websource-preorder' ) : __( 'Créer la précommande', 'websource-preorder' ) );
		echo ' <a href="' . esc_url( admin_url( 'admin.php?page=wspo' ) ) . '">' . esc_html__( '← Retour à la liste', 'websource-preorder' ) . '</a></form></div>';
	}

	private static function row_input( $name, $label, $value, $type = 'text', $desc = '', $required = false ) {
		echo '<tr><th><label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></th><td><input type="' . esc_attr( $type ) . '" id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"' . ( 'number' === $type ? ' min="0"' : '' ) . ( $required ? ' required' : '' ) . ' class="' . ( 'text' === $type ? 'regular-text' : '' ) . '">';
		if ( $desc ) {
			echo '<p class="description">' . esc_html( $desc ) . '</p>';
		}
		echo '</td></tr>';
	}

	private static function row_check( $name, $label, $value, $desc = '' ) {
		echo '<tr><th>' . esc_html( $label ) . '</th><td><label><input type="checkbox" name="' . esc_attr( $name ) . '" value="1"' . checked( (int) $value, 1, false ) . '> ' . esc_html__( 'Oui', 'websource-preorder' ) . '</label>';
		if ( $desc ) {
			echo '<p class="description">' . esc_html( $desc ) . '</p>';
		}
		echo '</td></tr>';
	}

	public static function do_save_rule() {
		self::guard( 'wspo_save_rule' );
		// phpcs:disable WordPress.Security.NonceVerification -- vérifié dans guard().
		$id       = isset( $_POST['rule_id'] ) ? absint( $_POST['rule_id'] ) : 0;
		$selected = isset( $_POST['wspo_product'] ) ? absint( $_POST['wspo_product'] ) : 0;
		$product  = $selected;
		$variation = 0;
		if ( $selected && 'product_variation' === get_post_type( $selected ) ) {
			$variation = $selected;
			$product   = (int) wp_get_post_parent_id( $selected );
		}
		$g = function ( $k ) {
			return isset( $_POST[ $k ] ) ? wp_unslash( $_POST[ $k ] ) : '';
		};
		$data = array(
			'product_id'     => $product,
			'variation_id'   => $variation,
			'active'         => ! empty( $_POST['wspo_active'] ),
			'date_start'     => wspo_local_to_utc( sanitize_text_field( $g( 'wspo_date_start' ) ) ),
			'date_release'   => wspo_local_to_utc( sanitize_text_field( $g( 'wspo_date_release' ) ) ),
			'end_on_release' => ! empty( $_POST['wspo_end_on_release'] ),
			'date_end'       => wspo_local_to_utc( sanitize_text_field( $g( 'wspo_date_end' ) ) ),
			'price_mode'     => absint( $g( 'wspo_price_mode' ) ),
			'price_value'    => (float) str_replace( ',', '.', sanitize_text_field( $g( 'wspo_price_value' ) ) ),
			'quota'          => absint( $g( 'wspo_quota' ) ),
			'max_order'      => absint( $g( 'wspo_max_order' ) ),
			'max_customer'   => absint( $g( 'wspo_max_customer' ) ),
			'label'          => $g( 'wspo_label' ),
			'message'        => $g( 'wspo_message' ),
			'in_list'        => ! empty( $_POST['wspo_in_list'] ),
		);
		// phpcs:enable
		$res = WSPO_Service::save_rule( $data, $id );
		if ( is_wp_error( $res ) ) {
			self::back( 'wspo', array( 'edit' => $id, 'wspo_e' => $res->get_error_message() ) );
		}
		self::back( 'wspo', array( 'wspo_n' => __( 'Précommande enregistrée.', 'websource-preorder' ) ) );
	}

	public static function do_rule_action() {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		self::guard( 'wspo_rule_' . $id );
		$do = isset( $_GET['do'] ) ? sanitize_key( wp_unslash( $_GET['do'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( 'delete' === $do ) {
			WSPO_Service::delete_rule( $id );
			self::back( 'wspo', array( 'wspo_n' => __( 'Précommande supprimée.', 'websource-preorder' ) ) );
		}
		if ( 'release' === $do ) {
			WSPO_Service::release_now( $id );
			self::back( 'wspo', array( 'wspo_n' => __( 'Le produit est disponible, les acheteurs ont été prévenus.', 'websource-preorder' ) ) );
		}
		self::back( 'wspo' );
	}

	/* ---------------------------------------------------------------- réservations */

	private static function bookings_where() {
		global $wpdb;
		// phpcs:disable WordPress.Security.NonceVerification
		$where = array( '1=1' );
		$args  = array();
		if ( ! empty( $_GET['rule'] ) ) {
			$where[] = 'rule_id = %d';
			$args[]  = absint( $_GET['rule'] );
		}
		if ( isset( $_GET['status'] ) && '' !== $_GET['status'] ) {
			$where[] = 'status = %d';
			$args[]  = absint( $_GET['status'] );
		}
		if ( ! empty( $_GET['s'] ) ) {
			$like    = '%' . $wpdb->esc_like( sanitize_text_field( wp_unslash( $_GET['s'] ) ) ) . '%';
			$where[] = '(email LIKE %s OR lastname LIKE %s OR firstname LIKE %s OR order_id LIKE %s)';
			array_push( $args, $like, $like, $like, $like );
		}
		// phpcs:enable
		return array( implode( ' AND ', $where ), $args );
	}

	public static function page_bookings() {
		global $wpdb;
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		list( $where, $args ) = self::bookings_where();
		$t       = WSPO_Service::table( 'bookings' );
		$page    = max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 ); // phpcs:ignore WordPress.Security.NonceVerification
		$per     = 30;
		$total   = (int) ( $args ? $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE $where", $args ) ) : $wpdb->get_var( "SELECT COUNT(*) FROM $t" ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		$q_args  = array_merge( $args, array( $per, ( $page - 1 ) * $per ) );
		$rows    = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE $where ORDER BY id DESC LIMIT %d OFFSET %d", $q_args ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
		$rules   = $wpdb->get_results( 'SELECT id, product_id, variation_id FROM ' . WSPO_Service::table( 'rules' ) . ' ORDER BY id DESC', ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
		$cur_rule = isset( $_GET['rule'] ) ? absint( $_GET['rule'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		$cur_st   = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$cur_s    = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

		echo '<div class="wrap"><h1>' . esc_html__( 'Réservations de précommande', 'websource-preorder' ) . '</h1>';
		self::notices();
		echo '<form method="get" class="wspo-filter"><input type="hidden" name="page" value="wspo-bookings">';
		echo '<select name="rule"><option value="0">' . esc_html__( 'Toutes les précommandes', 'websource-preorder' ) . '</option>';
		foreach ( $rules as $r ) {
			$p = wc_get_product( $r['variation_id'] ? $r['variation_id'] : $r['product_id'] );
			echo '<option value="' . (int) $r['id'] . '"' . selected( $cur_rule, (int) $r['id'], false ) . '>#' . (int) $r['id'] . ' ' . esc_html( $p ? wp_strip_all_tags( $p->get_name() ) : '?' ) . '</option>';
		}
		echo '</select> <select name="status"><option value="">' . esc_html__( 'Tous les statuts', 'websource-preorder' ) . '</option><option value="0"' . selected( $cur_st, '0', false ) . '>' . esc_html__( 'Actives', 'websource-preorder' ) . '</option><option value="1"' . selected( $cur_st, '1', false ) . '>' . esc_html__( 'Annulées', 'websource-preorder' ) . '</option></select> ';
		echo '<input type="search" name="s" value="' . esc_attr( $cur_s ) . '" placeholder="' . esc_attr__( 'Client, e-mail, n° commande', 'websource-preorder' ) . '"> ';
		submit_button( __( 'Filtrer', 'websource-preorder' ), 'secondary', '', false );
		$export = wp_nonce_url( add_query_arg( array( 'action' => 'wspo_export_bookings', 'rule' => $cur_rule, 'status' => $cur_st, 's' => $cur_s ), admin_url( 'admin-post.php' ) ), 'wspo_export_bookings' );
		echo ' <a class="button" href="' . esc_url( $export ) . '">' . esc_html__( 'Exporter en CSV', 'websource-preorder' ) . '</a></form>';

		echo '<table class="widefat striped wspo-table"><thead><tr><th>#</th><th>' . esc_html__( 'Date', 'websource-preorder' ) . '</th><th>' . esc_html__( 'Commande', 'websource-preorder' ) . '</th><th>' . esc_html__( 'Client', 'websource-preorder' ) . '</th><th>' . esc_html__( 'Produit', 'websource-preorder' ) . '</th><th>' . esc_html__( 'Qté', 'websource-preorder' ) . '</th><th>' . esc_html__( 'Statut', 'websource-preorder' ) . '</th><th>' . esc_html__( 'Actions', 'websource-preorder' ) . '</th></tr></thead><tbody>';
		if ( ! $rows ) {
			echo '<tr><td colspan="8">' . esc_html__( 'Aucune réservation.', 'websource-preorder' ) . '</td></tr>';
		}
		foreach ( $rows as $b ) {
			$p      = wc_get_product( $b['variation_id'] ? $b['variation_id'] : $b['product_id'] );
			$o      = wc_get_order( $b['order_id'] );
			$status = (int) $b['status'] === WSPO_Service::B_CANCELLED ? __( 'Annulée', 'websource-preorder' ) : ( (int) $b['notified'] ? __( 'Prévenue', 'websource-preorder' ) : __( 'Réservée', 'websource-preorder' ) );
			$cancel = wp_nonce_url( admin_url( 'admin-post.php?action=wspo_booking_action&do=cancel&id=' . (int) $b['id'] ), 'wspo_booking_' . (int) $b['id'] );
			$resend = wp_nonce_url( admin_url( 'admin-post.php?action=wspo_booking_action&do=resend&id=' . (int) $b['id'] ), 'wspo_booking_' . (int) $b['id'] );
			echo '<tr><td>' . (int) $b['id'] . '</td><td>' . esc_html( wspo_format_date( $b['date_add'], true ) ) . '</td><td>'
				. ( $o ? '<a href="' . esc_url( $o->get_edit_order_url() ) . '">#' . esc_html( $o->get_order_number() ) . '</a>' : '#' . (int) $b['order_id'] )
				. '</td><td>' . esc_html( trim( $b['firstname'] . ' ' . $b['lastname'] ) ) . '<br><small>' . esc_html( $b['email'] ) . '</small></td><td>'
				. esc_html( $p ? wp_strip_all_tags( $p->get_name() ) : '#' . $b['product_id'] ) . '</td><td>' . (int) $b['quantity'] . '</td><td>' . esc_html( $status ) . '</td><td>';
			if ( (int) $b['status'] === WSPO_Service::B_ACTIVE ) {
				echo '<a href="' . esc_url( $resend ) . '">' . esc_html__( 'Renvoyer l’e-mail « disponible »', 'websource-preorder' ) . '</a> | <a href="' . esc_url( $cancel ) . '" onclick="return confirm(\'' . esc_js( __( 'Annuler cette réservation et libérer le quota ?', 'websource-preorder' ) ) . '\')">' . esc_html__( 'Annuler', 'websource-preorder' ) . '</a>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
		$pages = (int) ceil( $total / $per );
		if ( $pages > 1 ) {
			echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post( paginate_links( array( 'base' => add_query_arg( 'paged', '%#%' ), 'format' => '', 'current' => $page, 'total' => $pages ) ) ) . '</div></div>';
		}
		echo '</div>';
	}

	public static function do_booking_action() {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		self::guard( 'wspo_booking_' . $id );
		global $wpdb;
		$b  = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . WSPO_Service::table( 'bookings' ) . ' WHERE id = %d', $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
		$do = isset( $_GET['do'] ) ? sanitize_key( wp_unslash( $_GET['do'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! $b ) {
			self::back( 'wspo-bookings', array( 'wspo_e' => __( 'Réservation introuvable.', 'websource-preorder' ) ) );
		}
		if ( 'cancel' === $do ) {
			$wpdb->update( WSPO_Service::table( 'bookings' ), array( 'status' => WSPO_Service::B_CANCELLED ), array( 'id' => $id ), array( '%d' ), array( '%d' ) );
			self::back( 'wspo-bookings', array( 'wspo_n' => __( 'Réservation annulée, quota libéré.', 'websource-preorder' ) ) );
		}
		if ( 'resend' === $do ) {
			$ok = WSPO_Service::notify_booking( $b, null, true );
			self::back( 'wspo-bookings', $ok ? array( 'wspo_n' => __( 'E-mail envoyé.', 'websource-preorder' ) ) : array( 'wspo_e' => __( 'Échec de l’envoi.', 'websource-preorder' ) ) );
		}
		self::back( 'wspo-bookings' );
	}

	public static function do_export_bookings() {
		self::guard( 'wspo_export_bookings' );
		global $wpdb;
		list( $where, $args ) = self::bookings_where();
		$t    = WSPO_Service::table( 'bookings' );
		$rows = $args ? $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE $where ORDER BY id DESC", $args ), ARRAY_A ) : $wpdb->get_results( "SELECT * FROM $t ORDER BY id DESC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
		$out  = array();
		foreach ( (array) $rows as $b ) {
			$p     = wc_get_product( $b['variation_id'] ? $b['variation_id'] : $b['product_id'] );
			$out[] = array( $b['id'], $b['date_add'], $b['order_id'], $b['firstname'], $b['lastname'], $b['email'], $p ? $p->get_name() : '', $b['quantity'], $b['unit_price'], $b['status'] ? 'annulee' : 'active', $b['notified'] ? 'oui' : 'non' );
		}
		self::csv_send( 'reservations-precommandes.csv', array( 'id', 'date_utc', 'commande', 'prenom', 'nom', 'email', 'produit', 'quantite', 'prix_unitaire', 'statut', 'prevenu' ), $out );
	}

	/* ---------------------------------------------------------------- abonnés */

	public static function page_subscribers() {
		global $wpdb;
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		$t     = WSPO_Subscribers::table();
		$s     = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$page  = max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 ); // phpcs:ignore WordPress.Security.NonceVerification
		$per   = 30;
		$like  = '%' . $wpdb->esc_like( $s ) . '%';
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE email LIKE %s", $like ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE email LIKE %s ORDER BY id DESC LIMIT %d OFFSET %d", $like, $per, ( $page - 1 ) * $per ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL

		echo '<div class="wrap"><h1>' . esc_html__( 'Abonnés aux alertes précommandes', 'websource-preorder' ) . '</h1>';
		self::notices();
		echo '<form method="get" class="wspo-filter"><input type="hidden" name="page" value="wspo-subscribers"><input type="search" name="s" value="' . esc_attr( $s ) . '" placeholder="' . esc_attr__( 'Rechercher une adresse', 'websource-preorder' ) . '"> ';
		submit_button( __( 'Rechercher', 'websource-preorder' ), 'secondary', '', false );
		echo ' <a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wspo_export_subs' ), 'wspo_export_subs' ) ) . '">' . esc_html__( 'Exporter en CSV', 'websource-preorder' ) . '</a></form>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="wspo-addsub"><input type="hidden" name="action" value="wspo_sub_action"><input type="hidden" name="do" value="add">';
		wp_nonce_field( 'wspo_sub_add' );
		echo '<h2>' . esc_html__( 'Ajouter un abonné', 'websource-preorder' ) . '</h2><input type="email" name="email" required placeholder="email@exemple.fr" class="regular-text"> <label><input type="checkbox" name="confirmed" value="1" checked> ' . esc_html__( 'Déjà confirmé (consentement recueilli)', 'websource-preorder' ) . '</label> ';
		submit_button( __( 'Ajouter', 'websource-preorder' ), 'primary', '', false );
		echo '</form>';

		echo '<table class="widefat striped wspo-table"><thead><tr><th>' . esc_html__( 'E-mail', 'websource-preorder' ) . '</th><th>' . esc_html__( 'Statut', 'websource-preorder' ) . '</th><th>' . esc_html__( 'Source', 'websource-preorder' ) . '</th><th>' . esc_html__( 'Inscrit le', 'websource-preorder' ) . '</th><th>' . esc_html__( 'Actions', 'websource-preorder' ) . '</th></tr></thead><tbody>';
		if ( ! $rows ) {
			echo '<tr><td colspan="5">' . esc_html__( 'Aucun abonné.', 'websource-preorder' ) . '</td></tr>';
		}
		foreach ( $rows as $r ) {
			$id   = (int) $r['id'];
			$conf = wp_nonce_url( admin_url( 'admin-post.php?action=wspo_sub_action&do=confirm&id=' . $id ), 'wspo_sub_' . $id );
			$del  = wp_nonce_url( admin_url( 'admin-post.php?action=wspo_sub_action&do=delete&id=' . $id ), 'wspo_sub_' . $id );
			echo '<tr><td>' . esc_html( $r['email'] ) . '</td><td>' . esc_html( $r['confirmed'] ? __( 'Confirmé', 'websource-preorder' ) : __( 'En attente', 'websource-preorder' ) ) . '</td><td>' . esc_html( $r['source'] ) . '</td><td>' . esc_html( wspo_format_date( $r['date_add'], true ) ) . '</td><td>';
			if ( ! $r['confirmed'] ) {
				echo '<a href="' . esc_url( $conf ) . '">' . esc_html__( 'Confirmer', 'websource-preorder' ) . '</a> | ';
			}
			echo '<a class="submitdelete" href="' . esc_url( $del ) . '" onclick="return confirm(\'' . esc_js( __( 'Supprimer cet abonné ?', 'websource-preorder' ) ) . '\')">' . esc_html__( 'Supprimer', 'websource-preorder' ) . '</a></td></tr>';
		}
		echo '</tbody></table>';
		$pages = (int) ceil( $total / $per );
		if ( $pages > 1 ) {
			echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post( paginate_links( array( 'base' => add_query_arg( 'paged', '%#%' ), 'format' => '', 'current' => $page, 'total' => $pages ) ) ) . '</div></div>';
		}
		echo '</div>';
	}

	public static function do_sub_action() {
		$do = isset( $_REQUEST['do'] ) ? sanitize_key( wp_unslash( $_REQUEST['do'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( 'add' === $do ) {
			self::guard( 'wspo_sub_add' );
			$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
			$conf  = ! empty( $_POST['confirmed'] ); // phpcs:ignore WordPress.Security.NonceVerification
			$res   = WSPO_Subscribers::subscribe( $email, 'admin', $conf );
			$msgs  = array( 'invalid' => __( 'Adresse invalide.', 'websource-preorder' ), 'exists' => __( 'Cette adresse est déjà inscrite.', 'websource-preorder' ), 'error' => __( 'Erreur lors de l’ajout.', 'websource-preorder' ) );
			if ( isset( $msgs[ $res ] ) ) {
				self::back( 'wspo-subscribers', array( 'wspo_e' => $msgs[ $res ] ) );
			}
			self::back( 'wspo-subscribers', array( 'wspo_n' => __( 'Abonné ajouté.', 'websource-preorder' ) ) );
		}
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		self::guard( 'wspo_sub_' . $id );
		if ( 'confirm' === $do ) {
			WSPO_Subscribers::confirm( $id );
			self::back( 'wspo-subscribers', array( 'wspo_n' => __( 'Abonné confirmé.', 'websource-preorder' ) ) );
		}
		if ( 'delete' === $do ) {
			WSPO_Subscribers::delete( $id );
			self::back( 'wspo-subscribers', array( 'wspo_n' => __( 'Abonné supprimé.', 'websource-preorder' ) ) );
		}
		self::back( 'wspo-subscribers' );
	}

	public static function do_export_subs() {
		self::guard( 'wspo_export_subs' );
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT * FROM ' . WSPO_Subscribers::table() . ' ORDER BY id DESC', ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
		$out  = array();
		foreach ( (array) $rows as $r ) {
			$out[] = array( $r['email'], $r['confirmed'] ? 'confirme' : 'en_attente', $r['source'], $r['locale'], $r['date_add'], $r['date_confirm'] );
		}
		self::csv_send( 'abonnes-precommandes.csv', array( 'email', 'statut', 'source', 'locale', 'inscrit_le_utc', 'confirme_le_utc' ), $out );
	}

	/* ---------------------------------------------------------------- réglages */

	public static function tabs() {
		return array(
			'general'  => __( 'Général', 'websource-preorder' ),
			'counter'  => __( 'Compteur', 'websource-preorder' ),
			'colors'   => __( 'Couleurs', 'websource-preorder' ),
			'home'     => __( 'Accueil & bandeau', 'websource-preorder' ),
			'accounts' => __( 'Blocs comptes', 'websource-preorder' ),
			'list'     => __( 'Page liste', 'websource-preorder' ),
			'alerts'   => __( 'Alertes', 'websource-preorder' ),
			'cron'     => __( 'Cron & récapitulatif', 'websource-preorder' ),
			'emails'   => __( 'E-mails', 'websource-preorder' ),
		);
	}

	/** Schéma des champs : [onglet][clé] = [type, libellé, description, options]. */
	public static function schema() {
		$cb = 'checkbox';
		return array(
			'general'  => array(
				'badge_label'       => array( 'text', __( 'Libellé du badge', 'websource-preorder' ), __( 'Affiché sur les vignettes et la fiche produit.', 'websource-preorder' ) ),
				'btn_label'         => array( 'text', __( 'Libellé du bouton d’achat', 'websource-preorder' ), __( 'Remplace « Ajouter au panier ».', 'websource-preorder' ) ),
				'show_flag'         => array( $cb, __( 'Afficher le badge', 'websource-preorder' ) ),
				'cart_notice'       => array( $cb, __( 'Notice dans le panier et la commande', 'websource-preorder' ), __( 'Prévient le client que son panier contient des précommandes.', 'websource-preorder' ) ),
				'mixed_cart'        => array( 'select', __( 'Mélange précommandes / produits en stock', 'websource-preorder' ), '', array( 'allow' => __( 'Autorisé', 'websource-preorder' ), 'forbid' => __( 'Interdit dans un même panier', 'websource-preorder' ) ) ),
				'admin_email'       => array( 'email', __( 'E-mail de l’administrateur', 'websource-preorder' ), __( 'Destinataire des alertes de nouvelle précommande. Vide = e-mail du site.', 'websource-preorder' ) ),
				'mail_order_on'     => array( $cb, __( 'Envoyer l’e-mail de précommande au client', 'websource-preorder' ) ),
				'mail_admin_on'     => array( $cb, __( 'Prévenir l’administrateur à chaque précommande', 'websource-preorder' ) ),
				'mail_available_on' => array( $cb, __( 'Envoyer l’e-mail « produit disponible » à la sortie', 'websource-preorder' ) ),
				'keep_data'         => array( $cb, __( 'Conserver les données à la désinstallation', 'websource-preorder' ), __( 'Décochez pour supprimer tables et réglages quand vous supprimez le plugin.', 'websource-preorder' ) ),
			),
			'counter'  => array(
				'show_countdown' => array( $cb, __( 'Afficher le compte à rebours', 'websource-preorder' ) ),
				'countdown_mode' => array(
					'select',
					__( 'Unités du compte à rebours', 'websource-preorder' ),
					'',
					array(
						'auto' => __( 'Automatique (selon le temps restant)', 'websource-preorder' ),
						'd'    => __( 'Jours', 'websource-preorder' ),
						'dh'   => __( 'Jours + heures', 'websource-preorder' ),
						'dhm'  => __( 'Jours + heures + minutes', 'websource-preorder' ),
						'dhms' => __( 'Jours + heures + minutes + secondes', 'websource-preorder' ),
					),
				),
				'show_progress'  => array( $cb, __( 'Afficher la barre de progression', 'websource-preorder' ) ),
				'show_quota'     => array( $cb, __( 'Afficher la quantité restante', 'websource-preorder' ) ),
			),
			'colors'   => array(
				'c_accent'     => array( 'color', __( 'Couleur principale', 'websource-preorder' ) ),
				'c_on_accent'  => array( 'color', __( 'Texte sur la couleur principale', 'websource-preorder' ) ),
				'c_box_bg'     => array( 'color', __( 'Fond du bloc', 'websource-preorder' ) ),
				'c_border'     => array( 'color', __( 'Bordure du bloc', 'websource-preorder' ) ),
				'c_count_bg'   => array( 'color', __( 'Fond du compteur', 'websource-preorder' ) ),
				'c_count_text' => array( 'color', __( 'Texte du compteur', 'websource-preorder' ) ),
				'c_badge_bg'   => array( 'color', __( 'Fond du badge', 'websource-preorder' ) ),
				'c_ticker_bg'  => array( 'color', __( 'Fond du bandeau', 'websource-preorder' ) ),
				'c_ticker_text' => array( 'color', __( 'Texte du bandeau', 'websource-preorder' ) ),
			),
			'home'     => array(
				'home_featured'       => array( $cb, __( 'Produit à la une en page d’accueil', 'websource-preorder' ), __( 'La précommande qui sort le plus tôt. Aussi disponible via le shortcode [websource_preorder_featured] et le bloc.', 'websource-preorder' ) ),
				'home_featured_title' => array( 'text', __( 'Titre du bloc à la une', 'websource-preorder' ) ),
				'ticker_on'           => array( $cb, __( 'Activer le bandeau défilant', 'websource-preorder' ), __( 'Shortcode [websource_preorder_ticker] et bloc disponibles pour un placement manuel.', 'websource-preorder' ) ),
				'ticker_pos'          => array( 'select', __( 'Emplacement du bandeau', 'websource-preorder' ), '', array( 'top' => __( 'Tout en haut du site', 'websource-preorder' ), 'footer' => __( 'Avant le pied de page', 'websource-preorder' ), 'home' => __( 'Haut de la page d’accueil', 'websource-preorder' ) ) ),
				'ticker_pages'        => array( 'select', __( 'Pages concernées', 'websource-preorder' ), '', array( 'all' => __( 'Toutes les pages', 'websource-preorder' ), 'home' => __( 'Accueil uniquement', 'websource-preorder' ) ) ),
				'ticker_speed'        => array( 'number', __( 'Vitesse (pixels par seconde)', 'websource-preorder' ), __( 'Entre 10 et 300. Désactivée si le visiteur demande « moins d’animations ».', 'websource-preorder' ) ),
				'ticker_max'          => array( 'number', __( 'Nombre maximum de produits', 'websource-preorder' ) ),
				'ticker_prefix'       => array( 'text', __( 'Texte d’introduction', 'websource-preorder' ) ),
			),
			'accounts' => array(
				'blk_login'    => array( $cb, __( 'Page de connexion', 'websource-preorder' ), __( 'Mon compte WooCommerce et wp-login.php.', 'websource-preorder' ) ),
				'blk_register' => array( $cb, __( 'Page d’inscription', 'websource-preorder' ), __( 'Sur Mon compte WooCommerce, connexion et inscription partagent la même page : le bloc n’y apparaît qu’une fois.', 'websource-preorder' ) ),
				'blk_password' => array( $cb, __( 'Page mot de passe perdu', 'websource-preorder' ) ),
				'blk_title'    => array( 'text', __( 'Titre du bloc', 'websource-preorder' ) ),
				'blk_text'     => array( 'textarea', __( 'Texte d’introduction', 'websource-preorder' ) ),
				'blk_products' => array( 'number', __( 'Nombre de produits affichés', 'websource-preorder' ), __( '0 = aucun produit.', 'websource-preorder' ) ),
				'blk_form'     => array( $cb, __( 'Afficher le formulaire d’alerte', 'websource-preorder' ) ),
			),
			'list'     => array(
				'list_slug'  => array( 'text', __( 'Adresse (slug) de la page', 'websource-preorder' ), __( 'La page est créée automatiquement avec le shortcode [websource_preorders].', 'websource-preorder' ) ),
				'list_title' => array( 'text', __( 'Titre de la page', 'websource-preorder' ) ),
				'list_intro' => array( 'textarea', __( 'Texte d’introduction', 'websource-preorder' ) ),
				'list_group' => array( 'select', __( 'Regroupement', 'websource-preorder' ), '', array( 'month' => __( 'Par mois de sortie', 'websource-preorder' ), 'none' => __( 'Aucun', 'websource-preorder' ) ) ),
			),
			'alerts'   => array(
				'sub_title'       => array( 'text', __( 'Titre du formulaire', 'websource-preorder' ) ),
				'sub_text'        => array( 'textarea', __( 'Texte d’introduction', 'websource-preorder' ) ),
				'sub_consent'     => array( 'textarea', __( 'Texte de la case RGPD', 'websource-preorder' ) ),
				'sub_doubleoptin' => array( $cb, __( 'Double opt-in (e-mail de confirmation)', 'websource-preorder' ) ),
				'sub_footer'      => array( $cb, __( 'Afficher en pied de page', 'websource-preorder' ) ),
				'sub_home'        => array( $cb, __( 'Afficher sur l’accueil', 'websource-preorder' ) ),
				'sub_product'     => array( $cb, __( 'Afficher sur la fiche produit', 'websource-preorder' ) ),
				'sub_left'        => array( $cb, __( 'Barre latérale gauche', 'websource-preorder' ), __( 'Thèmes classiques dont la zone de widgets porte « left » / « gauche » dans son identifiant.', 'websource-preorder' ) ),
				'sub_right'       => array( $cb, __( 'Barre latérale droite', 'websource-preorder' ), __( 'Thèmes classiques : zone « right », « droite » ou barre latérale principale. Vous pouvez aussi utiliser le widget « Alertes précommandes », le shortcode [websource_preorder_subscribe] ou le bloc.', 'websource-preorder' ) ),
			),
			'cron'     => array(
				'digest_on'    => array( $cb, __( 'Activer le récapitulatif périodique', 'websource-preorder' ) ),
				'digest_times' => array( 'number', __( 'Nombre d’envois', 'websource-preorder' ), __( 'Ex. 2 fois par semaine.', 'websource-preorder' ) ),
				'digest_freq'  => array( 'select', __( 'Par', 'websource-preorder' ), '', array( 'day' => __( 'Jour', 'websource-preorder' ), 'week' => __( 'Semaine', 'websource-preorder' ), 'month' => __( 'Mois', 'websource-preorder' ) ) ),
				'digest_mode'  => array( 'select', __( 'Contenu', 'websource-preorder' ), '', array( 'all' => __( 'Toutes les précommandes ouvertes', 'websource-preorder' ), 'new' => __( 'Seulement les nouveautés depuis le dernier envoi', 'websource-preorder' ) ) ),
				'digest_max'   => array( 'number', __( 'Nombre maximum de produits par e-mail', 'websource-preorder' ), __( '0 = tous.', 'websource-preorder' ) ),
			),
		);
	}

	public static function page_settings() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		$tabs = self::tabs();
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'general';
		}
		echo '<div class="wrap"><h1>' . esc_html__( 'Précommandes : réglages', 'websource-preorder' ) . '</h1>';
		self::notices();
		echo '<nav class="nav-tab-wrapper wspo-tabs">';
		foreach ( $tabs as $k => $l ) {
			echo '<a class="nav-tab' . ( $k === $tab ? ' nav-tab-active' : '' ) . '" href="' . esc_url( admin_url( 'admin.php?page=wspo-settings&tab=' . $k ) ) . '">' . esc_html( $l ) . '</a>';
		}
		echo '</nav>';
		if ( 'emails' === $tab ) {
			self::tab_emails();
		} else {
			self::tab_fields( $tab );
		}
		echo '</div>';
	}

	private static function tab_fields( $tab ) {
		$schema = self::schema();
		$s      = WSPO_Settings::all();
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="wspo_save_settings"><input type="hidden" name="tab" value="' . esc_attr( $tab ) . '">';
		wp_nonce_field( 'wspo_settings_' . $tab );
		echo '<table class="form-table" role="presentation">';
		foreach ( $schema[ $tab ] as $key => $f ) {
			$type = $f[0];
			$name = 'wspo[' . $key . ']';
			echo '<tr><th><label for="wspo_' . esc_attr( $key ) . '">' . esc_html( $f[1] ) . '</label></th><td>';
			switch ( $type ) {
				case 'checkbox':
					echo '<label><input type="checkbox" id="wspo_' . esc_attr( $key ) . '" name="' . esc_attr( $name ) . '" value="1"' . checked( (int) $s[ $key ], 1, false ) . '> ' . esc_html__( 'Oui', 'websource-preorder' ) . '</label>';
					break;
				case 'select':
					echo '<select id="wspo_' . esc_attr( $key ) . '" name="' . esc_attr( $name ) . '">';
					foreach ( $f[3] as $v => $l ) {
						echo '<option value="' . esc_attr( $v ) . '"' . selected( (string) $s[ $key ], (string) $v, false ) . '>' . esc_html( $l ) . '</option>';
					}
					echo '</select>';
					break;
				case 'textarea':
					echo '<textarea id="wspo_' . esc_attr( $key ) . '" name="' . esc_attr( $name ) . '" rows="3" class="large-text">' . esc_textarea( $s[ $key ] ) . '</textarea>';
					break;
				case 'number':
					echo '<input type="number" min="0" id="wspo_' . esc_attr( $key ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $s[ $key ] ) . '" class="small-text">';
					break;
				case 'color':
					echo '<input type="text" class="wspo-color" id="wspo_' . esc_attr( $key ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $s[ $key ] ) . '" data-default-color="' . esc_attr( WSPO_Settings::defaults()[ $key ] ) . '">';
					break;
				default:
					echo '<input type="' . ( 'email' === $type ? 'email' : 'text' ) . '" id="wspo_' . esc_attr( $key ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $s[ $key ] ) . '" class="regular-text">';
			}
			if ( ! empty( $f[2] ) ) {
				echo '<p class="description">' . esc_html( $f[2] ) . '</p>';
			}
			echo '</td></tr>';
		}
		echo '</table>';
		if ( 'list' === $tab ) {
			echo '<p>' . esc_html__( 'Adresse publique actuelle :', 'websource-preorder' ) . ' <a href="' . esc_url( WSPO_Front::list_url() ) . '" target="_blank" rel="noopener">' . esc_html( WSPO_Front::list_url() ) . '</a></p>';
		}
		if ( 'alerts' === $tab ) {
			echo '<p class="description">' . esc_html__( 'Shortcode : [websource_preorder_subscribe title="…" text="…"]', 'websource-preorder' ) . '</p>';
		}
		submit_button();
		echo '</form>';
		if ( 'cron' === $tab ) {
			self::cron_panel();
		}
	}

	private static function cron_panel() {
		$url  = add_query_arg( 'wspo_cron', WSPO_Settings::cron_token(), home_url( '/' ) );
		$next = wp_next_scheduled( WSPO_Install::CRON_HOOK );
		echo '<hr><h2>' . esc_html__( 'Tâche planifiée', 'websource-preorder' ) . '</h2>';
		echo '<p>' . esc_html__( 'WP-Cron vérifie les précommandes et envoie le récapitulatif toutes les 5 minutes (lorsque le site reçoit des visites).', 'websource-preorder' ) . '</p>';
		echo '<p>' . esc_html__( 'Prochain passage WP-Cron :', 'websource-preorder' ) . ' <strong>' . esc_html( $next ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next ) : '—' ) . '</strong></p>';
		echo '<h3>' . esc_html__( 'Vrai cron serveur (recommandé)', 'websource-preorder' ) . '</h3><p>' . esc_html__( 'Ajoutez cette ligne à votre crontab, et définissez DISABLE_WP_CRON à true dans wp-config.php :', 'websource-preorder' ) . '</p>';
		echo '<pre class="wspo-pre">*/5 * * * * curl -s "' . esc_html( $url ) . '" &gt; /dev/null</pre>';
		echo '<p>' . esc_html__( 'Dernier récapitulatif complet :', 'websource-preorder' ) . ' <strong>' . esc_html( (int) get_option( 'wspo_digest_last' ) ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) get_option( 'wspo_digest_last' ) ) : '—' ) . '</strong></p>';
		echo '<p><a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wspo_run_digest' ), 'wspo_run_digest' ) ) . '">' . esc_html__( 'Envoyer le récapitulatif maintenant', 'websource-preorder' ) . '</a> ';
		echo '<a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wspo_regen_token' ), 'wspo_regen_token' ) ) . '" onclick="return confirm(\'' . esc_js( __( 'Régénérer le jeton ? L’ancienne adresse de cron cessera de fonctionner.', 'websource-preorder' ) ) . '\')">' . esc_html__( 'Régénérer le jeton', 'websource-preorder' ) . '</a></p>';
	}

	public static function do_run_digest() {
		self::guard( 'wspo_run_digest' );
		$res = WSPO_Subscribers::run_digest( true );
		$map = array(
			'no_preorder' => __( 'Aucune précommande ouverte : rien n’a été envoyé.', 'websource-preorder' ),
			'done'        => __( 'Récapitulatif envoyé.', 'websource-preorder' ),
			'partial'     => __( 'Lot envoyé, la suite partira au prochain passage du cron.', 'websource-preorder' ),
		);
		$msg = ( isset( $map[ $res[1] ] ) ? $map[ $res[1] ] : $res[1] ) . ' (' . (int) $res[0] . ')';
		self::back( 'wspo-settings', array( 'tab' => 'cron', 'wspo_n' => $msg ) );
	}

	public static function do_regen_token() {
		self::guard( 'wspo_regen_token' );
		WSPO_Settings::update( array( 'cron_token' => wp_generate_password( 32, false, false ) ) );
		self::back( 'wspo-settings', array( 'tab' => 'cron', 'wspo_n' => __( 'Jeton régénéré.', 'websource-preorder' ) ) );
	}

	public static function do_save_settings() {
		$tab = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification
		self::guard( 'wspo_settings_' . $tab );
		$schema = self::schema();
		if ( ! isset( $schema[ $tab ] ) ) {
			self::back( 'wspo-settings' );
		}
		$in  = isset( $_POST['wspo'] ) && is_array( $_POST['wspo'] ) ? wp_unslash( $_POST['wspo'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput
		$def = WSPO_Settings::defaults();
		$out = array();
		foreach ( $schema[ $tab ] as $key => $f ) {
			$raw = isset( $in[ $key ] ) && ! is_array( $in[ $key ] ) ? $in[ $key ] : null;
			switch ( $f[0] ) {
				case 'checkbox':
					$out[ $key ] = null !== $raw ? 1 : 0;
					break;
				case 'select':
					$out[ $key ] = ( null !== $raw && isset( $f[3][ $raw ] ) ) ? $raw : $def[ $key ];
					break;
				case 'number':
					$out[ $key ] = max( 0, (int) $raw );
					if ( 'ticker_speed' === $key ) {
						$out[ $key ] = max( 10, min( 300, $out[ $key ] ) );
					}
					if ( 'digest_times' === $key ) {
						$out[ $key ] = max( 1, min( 24, $out[ $key ] ) );
					}
					break;
				case 'color':
					$c           = sanitize_hex_color( (string) $raw );
					$out[ $key ] = $c ? $c : $def[ $key ];
					break;
				case 'email':
					$e           = sanitize_email( (string) $raw );
					$out[ $key ] = is_email( $e ) ? $e : '';
					break;
				case 'textarea':
					$out[ $key ] = sanitize_textarea_field( (string) $raw );
					break;
				default:
					$out[ $key ] = sanitize_text_field( (string) $raw );
			}
		}
		WSPO_Settings::update( $out );
		if ( 'list' === $tab ) {
			WSPO_Front::ensure_list_page();
		}
		self::back( 'wspo-settings', array( 'tab' => $tab, 'wspo_n' => __( 'Réglages enregistrés.', 'websource-preorder' ) ) );
	}

	/* ---------------------------------------------------------------- onglet e-mails */

	private static function locales() {
		$list = array_merge( array( get_locale(), 'fr_FR', 'en_US' ), get_available_languages() );
		return array_values( array_unique( $list ) );
	}

	private static function tab_emails() {
		$locales = self::locales();
		$loc     = isset( $_GET['locale'] ) ? sanitize_text_field( wp_unslash( $_GET['locale'] ) ) : get_locale(); // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! in_array( $loc, $locales, true ) ) {
			$loc = get_locale();
		}
		echo '<form method="get" class="wspo-filter"><input type="hidden" name="page" value="wspo-settings"><input type="hidden" name="tab" value="emails"><label>' . esc_html__( 'Langue / locale :', 'websource-preorder' ) . ' <select name="locale" onchange="this.form.submit()">';
		foreach ( $locales as $l ) {
			echo '<option value="' . esc_attr( $l ) . '"' . selected( $loc, $l, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select></label></form>';
		echo '<p class="description">' . esc_html__( 'Laissez un champ vide pour revenir au texte par défaut. L’enveloppe HTML reprend la couleur principale de vos réglages.', 'websource-preorder' ) . '</p>';
		$saved = get_option( WSPO_Mail::OPTION, array() );
		foreach ( WSPO_Mail::labels() as $key => $label ) {
			$cur = isset( $saved[ $key ][ $loc ] ) ? $saved[ $key ][ $loc ] : array( 'subject' => '', 'body' => '' );
			$def = WSPO_Mail::defaults( $key, WSPO_Mail::iso( $loc ) );
			echo '<div class="wspo-mail-box"><h2>' . esc_html( $label ) . '</h2>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="wspo_save_mails"><input type="hidden" name="mail_key" value="' . esc_attr( $key ) . '"><input type="hidden" name="locale" value="' . esc_attr( $loc ) . '">';
			wp_nonce_field( 'wspo_mail_' . $key );
			echo '<p><label>' . esc_html__( 'Objet', 'websource-preorder' ) . '<br><input type="text" class="large-text" name="subject" value="' . esc_attr( $cur['subject'] ) . '" placeholder="' . esc_attr( $def['subject'] ) . '"></label></p>';
			echo '<p><label>' . esc_html__( 'Corps (HTML)', 'websource-preorder' ) . '<br><textarea name="body" rows="8" class="large-text code" placeholder="' . esc_attr( $def['body'] ) . '">' . esc_textarea( $cur['body'] ) . '</textarea></label></p>';
			echo '<p class="description">' . esc_html__( 'Variables :', 'websource-preorder' ) . ' <code>' . esc_html( implode( '</code> <code>', WSPO_Mail::keys()[ $key ] ) ) . '</code></p>';
			echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Enregistrer', 'websource-preorder' ) . '</button> ';
			echo '<a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wspo_test_mail&mail_key=' . $key . '&locale=' . rawurlencode( $loc ) ), 'wspo_test_' . $key ) ) . '">' . esc_html__( 'Envoyer un test à', 'websource-preorder' ) . ' ' . esc_html( wp_get_current_user()->user_email ) . '</a></p></form></div>';
		}
	}

	private static function mail_kses() {
		$allowed = wp_kses_allowed_html( 'post' );
		foreach ( array( 'a', 'p', 'table', 'tr', 'td', 'th', 'span', 'div', 'strong', 'em', 'img', 'h1', 'h2', 'h3', 'br', 'ul', 'ol', 'li' ) as $t ) {
			if ( ! isset( $allowed[ $t ] ) ) {
				$allowed[ $t ] = array();
			}
			$allowed[ $t ] += array( 'style' => true, 'width' => true, 'align' => true, 'cellpadding' => true, 'cellspacing' => true, 'href' => true, 'src' => true, 'alt' => true );
		}
		return $allowed;
	}

	public static function do_save_mails() {
		$key = isset( $_POST['mail_key'] ) ? sanitize_key( wp_unslash( $_POST['mail_key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		self::guard( 'wspo_mail_' . $key );
		$loc = isset( $_POST['locale'] ) ? sanitize_text_field( wp_unslash( $_POST['locale'] ) ) : get_locale();
		if ( ! isset( WSPO_Mail::labels()[ $key ] ) || ! in_array( $loc, self::locales(), true ) ) {
			self::back( 'wspo-settings', array( 'tab' => 'emails' ) );
		}
		$css = function ( $props ) {
			return array_merge( $props, array( 'display', 'background', 'background-color', 'color', 'padding', 'border-radius', 'text-decoration', 'font-weight', 'font-size', 'border-collapse', 'border-bottom', 'border', 'margin', 'text-align' ) );
		};
		add_filter( 'safe_style_css', $css );
		$subject = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
		$body    = isset( $_POST['body'] ) ? wp_kses( wp_unslash( $_POST['body'] ), self::mail_kses() ) : '';
		remove_filter( 'safe_style_css', $css );
		$saved                  = get_option( WSPO_Mail::OPTION, array() );
		$saved[ $key ][ $loc ] = array( 'subject' => $subject, 'body' => $body );
		update_option( WSPO_Mail::OPTION, $saved, false );
		self::back( 'wspo-settings', array( 'tab' => 'emails', 'locale' => $loc, 'wspo_n' => __( 'E-mail enregistré.', 'websource-preorder' ) ) );
	}

	public static function do_test_mail() {
		$key = isset( $_GET['mail_key'] ) ? sanitize_key( wp_unslash( $_GET['mail_key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		self::guard( 'wspo_test_' . $key );
		$loc = isset( $_GET['locale'] ) ? sanitize_text_field( wp_unslash( $_GET['locale'] ) ) : get_locale(); // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! isset( WSPO_Mail::labels()[ $key ] ) ) {
			self::back( 'wspo-settings', array( 'tab' => 'emails' ) );
		}
		$to   = wp_get_current_user()->user_email;
		$fake = array(
			'<table width="100%"><tr><td><strong>' . esc_html__( 'Produit d’exemple', 'websource-preorder' ) . '</strong><br><span style="color:#666">' . esc_html__( 'Sortie prévue le', 'websource-preorder' ) . ' ' . esc_html( wp_date( get_option( 'date_format' ), time() + 30 * DAY_IN_SECONDS ) ) . '</span></td><td align="right">&times; 1</td></tr></table>',
		);
		$vars = array(
			'firstname'       => 'Camille',
			'lastname'        => 'Dupont',
			'order_reference' => '1234',
			'products_html'   => $fake[0],
			'release_info'    => sprintf( /* translators: %s: date */ __( 'Date de sortie prévue : %s.', 'websource-preorder' ), wp_date( get_option( 'date_format' ), time() + 30 * DAY_IN_SECONDS ) ),
			'product_name'    => __( 'Produit d’exemple', 'websource-preorder' ),
			'product_url'     => home_url( '/' ),
			'release_date'    => wp_date( get_option( 'date_format' ), time() ),
			'customer'        => 'Camille Dupont',
			'customer_email'  => 'camille@example.com',
			'confirm_url'     => home_url( '/' ),
			'unsubscribe_url' => home_url( '/' ),
			'count'           => 1,
		);
		$ok   = WSPO_Mail::send( $key, $to, $loc, $vars );
		self::back( 'wspo-settings', array( 'tab' => 'emails', 'locale' => $loc ) + ( $ok ? array( 'wspo_n' => sprintf( /* translators: %s: email */ __( 'E-mail de test envoyé à %s.', 'websource-preorder' ), $to ) ) : array( 'wspo_e' => __( 'Échec de l’envoi du test (vérifiez la configuration e-mail du site).', 'websource-preorder' ) ) ) );
	}
}

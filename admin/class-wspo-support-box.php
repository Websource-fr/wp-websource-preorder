<?php
/**
 * Encart « accompagnement Websource » (composant réutilisable).
 *
 * Visible uniquement dans l'admin WordPress pour les utilisateurs ayant la capacité manage_options,
 * une seule fois par page, masquable 30 jours par utilisateur. Aucune requête externe : les seuls
 * éléments transmis sont les paramètres des liens (utm_* et ws_*) quand l'utilisateur clique.
 *
 * Pour réutiliser dans un autre plugin Websource : copier ce fichier, adapter SLUG, VERSION_CONST
 * et le préfixe de classe, puis appeler render() sur l'écran principal du plugin.
 *
 * @package WebsourcePreorder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSPO_Support_Box {

	const SLUG       = 'websource-preorder';
	const META_KEY   = 'wspo_support_hidden_until';
	const AJAX       = 'wspo_hide_support';
	const CONTACT    = 'https://www.websource.fr/contact';
	const HIDE_DAYS  = 30;

	private static $rendered = false;

	public static function init() {
		add_action( 'wp_ajax_' . self::AJAX, array( __CLASS__, 'ajax_hide' ) );
	}

	/** Version du plugin (constante unique). */
	private static function version() {
		return defined( 'WSPO_VERSION' ) ? WSPO_VERSION : '';
	}

	/** URL de contact avec les paramètres de contexte. */
	public static function url( $rdv = false ) {
		$parts = wp_parse_url( home_url( '/' ) );
		$site  = ( isset( $parts['scheme'] ) ? $parts['scheme'] : 'https' ) . '://' . ( isset( $parts['host'] ) ? $parts['host'] : '' );
		$args  = array(
			'utm_source'   => self::SLUG,
			'utm_medium'   => 'admin-plugin',
			'utm_campaign' => 'accompagnement',
		);
		if ( $rdv ) {
			$args['utm_content'] = 'rdv';
		}
		$args += array(
			'ws_module' => self::SLUG,
			'ws_mv'     => self::version(),
			'ws_cms'    => 'wordpress',
			'ws_cmsv'   => get_bloginfo( 'version' ),
			'ws_site'   => $site,
		);
		return add_query_arg( array_map( 'rawurlencode', $args ), self::CONTACT );
	}

	private static function is_hidden() {
		$until = (int) get_user_meta( get_current_user_id(), self::META_KEY, true );
		return $until > time();
	}

	/** Affiche l'encart (admin uniquement, administrateurs, une fois par page). */
	public static function render() {
		if ( ! is_admin() || self::$rendered || ! current_user_can( 'manage_options' ) || self::is_hidden() ) {
			return;
		}
		self::$rendered = true;
		$nonce          = wp_create_nonce( self::AJAX );
		?>
		<div class="postbox wspo-support" id="wspo-support" style="max-width:900px;margin:16px 0">
			<div class="postbox-header" style="display:flex;justify-content:space-between;align-items:center">
				<h2 class="hndle" style="padding:8px 12px;margin:0"><?php esc_html_e( 'Besoin d’aller plus loin ?', 'websource-preorder' ); ?></h2>
				<button type="button" class="button-link" id="wspo-support-hide" style="margin-right:12px" data-nonce="<?php echo esc_attr( $nonce ); ?>"><?php esc_html_e( 'Masquer', 'websource-preorder' ); ?></button>
			</div>
			<div class="inside">
				<p><?php esc_html_e( 'Websource, l’agence éditrice de ce plugin, peut vous accompagner plus en profondeur : personnalisation sur mesure, adaptation à votre thème, développements spécifiques. Parlons de votre besoin.', 'websource-preorder' ); ?></p>
				<p>
					<a class="button button-primary" href="<?php echo esc_url( self::url( false ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Nous contacter', 'websource-preorder' ); ?></a>
					<a class="button" href="<?php echo esc_url( self::url( true ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Prendre rendez-vous', 'websource-preorder' ); ?></a>
				</p>
				<p class="description" style="font-size:12px"><?php esc_html_e( 'En cliquant, le nom du plugin et l’adresse de votre site sont transmis à Websource pour faciliter notre réponse.', 'websource-preorder' ); ?></p>
			</div>
		</div>
		<script>
		(function () {
			var b = document.getElementById('wspo-support-hide');
			if (!b) { return; }
			b.addEventListener('click', function () {
				var box = document.getElementById('wspo-support');
				var d = new FormData();
				d.append('action', <?php echo wp_json_encode( self::AJAX ); ?>);
				d.append('_wpnonce', b.getAttribute('data-nonce'));
				if (box) { box.style.display = 'none'; }
				fetch(<?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>, { method: 'POST', credentials: 'same-origin', body: d });
			});
		})();
		</script>
		<?php
	}

	public static function ajax_hide() {
		check_ajax_referer( self::AJAX );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( null, 403 );
		}
		update_user_meta( get_current_user_id(), self::META_KEY, time() + self::HIDE_DAYS * DAY_IN_SECONDS );
		wp_send_json_success();
	}
}

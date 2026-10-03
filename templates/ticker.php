<?php
/**
 * Bandeau défilant des précommandes.
 * Surchargeable : votre-theme/websource-preorder/ticker.php
 *
 * @var array  $items
 * @var string $prefix
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$render_group = function ( $hidden ) use ( $items, $prefix ) {
	echo '<div class="wspo-ticker__group"' . ( $hidden ? ' aria-hidden="true"' : '' ) . '>';
	if ( $prefix ) {
		echo '<span class="wspo-ticker__prefix">' . esc_html( $prefix ) . '</span>';
	}
	foreach ( $items as $it ) {
		echo '<a class="wspo-ticker__item" href="' . esc_url( $it['url'] ) . '"' . ( $hidden ? ' tabindex="-1"' : '' ) . '>' . esc_html( $it['name'] );
		if ( $it['release'] ) {
			echo ' <span class="wspo-ticker__date">' . esc_html( wp_date( get_option( 'date_format' ), $it['release'] ) ) . '</span>';
		}
		echo '</a>';
	}
	echo '</div>';
};
?>
<div class="wspo-ticker" role="region" aria-label="<?php esc_attr_e( 'Produits en précommande', 'websource-preorder' ); ?>">
	<div class="wspo-ticker__track">
		<?php $render_group( false ); ?>
		<?php $render_group( true ); ?>
	</div>
</div>

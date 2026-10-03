<?php
/**
 * Page « Mes précommandes » dans Mon compte.
 * Surchargeable : votre-theme/websource-preorder/my-preorders.php
 *
 * @var array $bookings
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<h2><?php esc_html_e( 'Mes précommandes', 'websource-preorder' ); ?></h2>
<?php if ( ! $bookings ) : ?>
	<div class="woocommerce-info"><?php esc_html_e( 'Vous n’avez aucune précommande.', 'websource-preorder' ); ?></div>
<?php else : ?>
	<table class="woocommerce-orders-table shop_table shop_table_responsive wspo-my-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Produit', 'websource-preorder' ); ?></th>
				<th><?php esc_html_e( 'Qté', 'websource-preorder' ); ?></th>
				<th><?php esc_html_e( 'Commande', 'websource-preorder' ); ?></th>
				<th><?php esc_html_e( 'Sortie', 'websource-preorder' ); ?></th>
				<th><?php esc_html_e( 'Statut', 'websource-preorder' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php
			foreach ( $bookings as $b ) :
				$p    = wc_get_product( $b['variation_id'] ? $b['variation_id'] : $b['product_id'] );
				$rule = WSPO_Service::get_rule( (int) $b['rule_id'] );
				$ord  = wc_get_order( $b['order_id'] );
				if ( (int) $b['status'] === WSPO_Service::B_CANCELLED ) {
					$status = __( 'Annulée', 'websource-preorder' );
				} elseif ( (int) $b['notified'] ) {
					$status = __( 'Disponible', 'websource-preorder' );
				} elseif ( $rule && (int) $rule['state'] === WSPO_Service::S_ENDED ) {
					$status = __( 'Disponible', 'websource-preorder' );
				} else {
					$status = __( 'En attente de sortie', 'websource-preorder' );
				}
				?>
				<tr>
					<td data-title="<?php esc_attr_e( 'Produit', 'websource-preorder' ); ?>"><?php echo $p ? '<a href="' . esc_url( $p->get_permalink() ) . '">' . esc_html( $p->get_name() ) . '</a>' : esc_html( '#' . $b['product_id'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
					<td data-title="<?php esc_attr_e( 'Qté', 'websource-preorder' ); ?>"><?php echo (int) $b['quantity']; ?></td>
					<td data-title="<?php esc_attr_e( 'Commande', 'websource-preorder' ); ?>"><?php echo $ord && $b['customer_id'] ? '<a href="' . esc_url( $ord->get_view_order_url() ) . '">#' . esc_html( $ord->get_order_number() ) . '</a>' : esc_html( '#' . $b['order_id'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
					<td data-title="<?php esc_attr_e( 'Sortie', 'websource-preorder' ); ?>"><?php echo $rule ? esc_html( wspo_format_date( $rule['date_release'] ) ) : '—'; ?></td>
					<td data-title="<?php esc_attr_e( 'Statut', 'websource-preorder' ); ?>"><?php echo esc_html( $status ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>

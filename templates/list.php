<?php
/**
 * Page « Toutes les précommandes ».
 * Surchargeable : votre-theme/websource-preorder/list.php
 *
 * @var array  $groups mois => éléments
 * @var string $intro
 * @var bool   $empty
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wspo-list">
	<?php if ( $intro ) : ?>
		<p class="wspo-list__intro"><?php echo esc_html( $intro ); ?></p>
	<?php endif; ?>
	<?php if ( $empty ) : ?>
		<p><?php esc_html_e( 'Aucune précommande n’est ouverte pour le moment.', 'websource-preorder' ); ?></p>
	<?php endif; ?>
	<?php foreach ( $groups as $label => $items ) : ?>
		<?php if ( '' !== $label ) : ?>
			<h2 class="wspo-list__month"><?php echo esc_html( $label ); ?></h2>
		<?php endif; ?>
		<div class="wspo-grid">
			<?php foreach ( $items as $it ) : ?>
				<article class="wspo-card">
					<a class="wspo-card__image" href="<?php echo esc_url( $it['url'] ); ?>"><?php echo wp_kses_post( $it['product']->get_image( 'woocommerce_thumbnail' ) ); ?></a>
					<?php if ( WSPO_Settings::get( 'show_flag' ) ) : ?>
						<span class="wspo-badge wspo-badge--loop"><?php echo esc_html( WSPO_Front::badge_label( $it['rule'] ) ); ?></span>
					<?php endif; ?>
					<h3 class="wspo-card__title"><a href="<?php echo esc_url( $it['url'] ); ?>"><?php echo esc_html( $it['name'] ); ?></a></h3>
					<p class="wspo-card__price"><?php echo wp_kses_post( $it['product']->get_price_html() ); ?></p>
					<?php if ( $it['release'] ) : ?>
						<p class="wspo-card__date">
							<?php
							/* translators: %s: release date */
							printf( esc_html__( 'Sortie prévue le %s', 'websource-preorder' ), esc_html( wspo_format_date( $it['rule']['date_release'] ) ) );
							?>
						</p>
					<?php endif; ?>
					<?php if ( WSPO_Settings::get( 'show_quota' ) && null !== $it['remaining'] ) : ?>
						<p class="wspo-box__quota">
							<?php
							/* translators: %d: remaining */
							echo esc_html( sprintf( _n( 'Il reste %d exemplaire.', 'Il reste %d exemplaires.', $it['remaining'], 'websource-preorder' ), $it['remaining'] ) );
							?>
						</p>
					<?php endif; ?>
					<a class="wspo-button" href="<?php echo esc_url( $it['url'] ); ?>"><?php echo esc_html( WSPO_Front::btn_label( $it['rule'] ) ); ?></a>
				</article>
			<?php endforeach; ?>
		</div>
	<?php endforeach; ?>
</div>

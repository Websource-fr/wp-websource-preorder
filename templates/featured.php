<?php
/**
 * Produit à la une en précommande.
 * Surchargeable : votre-theme/websource-preorder/featured.php
 *
 * @var array  $item
 * @var string $title
 * @var int    $progress
 * @var string $badge
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$p = $item['product'];
?>
<section class="wspo-featured">
	<?php if ( $title ) : ?>
		<h2 class="wspo-featured__title"><?php echo esc_html( $title ); ?></h2>
	<?php endif; ?>
	<div class="wspo-featured__body">
		<a class="wspo-featured__image" href="<?php echo esc_url( $item['url'] ); ?>"><?php echo wp_kses_post( $p->get_image( 'woocommerce_single' ) ); ?></a>
		<div class="wspo-featured__info">
			<span class="wspo-badge"><?php echo esc_html( $badge ); ?></span>
			<h3><a href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['name'] ); ?></a></h3>
			<p class="wspo-featured__price"><?php echo wp_kses_post( $p->get_price_html() ); ?></p>
			<?php if ( $item['release'] ) : ?>
				<p class="wspo-box__release">
					<?php
					/* translators: %s: release date */
					printf( esc_html__( 'Sortie prévue le %s', 'websource-preorder' ), '<strong>' . esc_html( wspo_format_date( $item['rule']['date_release'] ) ) . '</strong>' );
					?>
				</p>
				<?php echo WSPO_Front::countdown_html( $item['rule'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<?php if ( WSPO_Settings::get( 'show_progress' ) ) : ?>
					<div class="wspo-progress"><span style="width:<?php echo (int) $progress; ?>%"></span></div>
				<?php endif; ?>
			<?php endif; ?>
			<?php if ( WSPO_Settings::get( 'show_quota' ) && null !== $item['remaining'] ) : ?>
				<p class="wspo-box__quota">
					<?php
					/* translators: %d: remaining quantity */
					echo esc_html( sprintf( _n( 'Il reste %d exemplaire.', 'Il reste %d exemplaires.', $item['remaining'], 'websource-preorder' ), $item['remaining'] ) );
					?>
				</p>
			<?php endif; ?>
			<a class="wspo-button" href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( WSPO_Front::btn_label( $item['rule'] ) ); ?></a>
		</div>
	</div>
</section>

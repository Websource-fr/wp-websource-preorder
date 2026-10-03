<?php
/**
 * Bloc précommande de la fiche produit.
 * Surchargeable : votre-theme/websource-preorder/product-box.php
 *
 * @var array      $rule
 * @var WC_Product $product
 * @var int|null   $remaining
 * @var int        $progress
 * @var string     $badge
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$settings = WSPO_Settings::all();
?>
<div class="wspo-box" data-wspo-rule="<?php echo (int) $rule['id']; ?>">
	<?php if ( $settings['show_flag'] ) : ?>
		<span class="wspo-badge"><?php echo esc_html( $badge ); ?></span>
	<?php endif; ?>
	<?php if ( wspo_ts( $rule['date_release'] ) ) : ?>
		<p class="wspo-box__release">
			<?php
			/* translators: %s: release date */
			printf( esc_html__( 'Sortie prévue le %s', 'websource-preorder' ), '<strong>' . esc_html( wspo_format_date( $rule['date_release'] ) ) . '</strong>' );
			?>
		</p>
		<?php echo WSPO_Front::countdown_html( $rule ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<?php if ( $settings['show_progress'] ) : ?>
			<div class="wspo-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo (int) $progress; ?>">
				<span style="width:<?php echo (int) $progress; ?>%"></span>
			</div>
		<?php endif; ?>
	<?php endif; ?>
	<?php if ( $settings['show_quota'] && null !== $remaining ) : ?>
		<p class="wspo-box__quota">
			<?php
			/* translators: %d: remaining quantity */
			echo esc_html( sprintf( _n( 'Il reste %d exemplaire en précommande.', 'Il reste %d exemplaires en précommande.', $remaining, 'websource-preorder' ), $remaining ) );
			?>
		</p>
	<?php endif; ?>
	<?php if ( '' !== trim( (string) $rule['message'] ) ) : ?>
		<p class="wspo-box__message"><?php echo esc_html( $rule['message'] ); ?></p>
	<?php endif; ?>
</div>

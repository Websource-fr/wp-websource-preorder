<?php
/**
 * Formulaire d'alerte e-mail.
 * Surchargeable : votre-theme/websource-preorder/subscribe-form.php
 *
 * @var string $source
 * @var string $title
 * @var string $text
 * @var string $consent
 * @var string $message HTML déjà échappé
 * @var string $token
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$uid = 'wspo-sub-' . $source;
?>
<div class="wspo-sub wspo-sub--<?php echo esc_attr( $source ); ?>" id="<?php echo esc_attr( $uid ); ?>">
	<?php if ( $title ) : ?>
		<h3 class="wspo-sub__title"><?php echo esc_html( $title ); ?></h3>
	<?php endif; ?>
	<?php if ( $text ) : ?>
		<p class="wspo-sub__text"><?php echo esc_html( $text ); ?></p>
	<?php endif; ?>
	<?php echo $message; // phpcs:ignore WordPress.Security.EscapeOutput ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wspo-sub__form">
		<input type="hidden" name="action" value="wspo_subscribe">
		<input type="hidden" name="wspo_source" value="<?php echo esc_attr( $source ); ?>">
		<input type="hidden" name="wspo_t" value="<?php echo esc_attr( $token ); ?>">
		<?php wp_nonce_field( 'wspo_subscribe', '_wspo_nonce', false ); ?>
		<div class="wspo-sub__hp" aria-hidden="true"><label>Website <input type="text" name="wspo_website" value="" tabindex="-1" autocomplete="off"></label></div>
		<label class="screen-reader-text wspo-sr" for="<?php echo esc_attr( $uid ); ?>-email"><?php esc_html_e( 'Votre adresse e-mail', 'websource-preorder' ); ?></label>
		<div class="wspo-sub__row">
			<input type="email" id="<?php echo esc_attr( $uid ); ?>-email" name="wspo_email" required placeholder="<?php esc_attr_e( 'Votre adresse e-mail', 'websource-preorder' ); ?>">
			<button type="submit" class="wspo-button"><?php esc_html_e( 'Me prévenir', 'websource-preorder' ); ?></button>
		</div>
		<label class="wspo-sub__consent"><input type="checkbox" name="wspo_consent" value="1" required> <span><?php echo esc_html( $consent ); ?></span></label>
	</form>
</div>

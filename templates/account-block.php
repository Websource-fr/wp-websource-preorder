<?php
/**
 * Bloc des pages connexion / inscription / mot de passe perdu.
 * Surchargeable : votre-theme/websource-preorder/account-block.php
 *
 * @var string $title
 * @var string $text
 * @var array  $items
 * @var string $form HTML du formulaire d'alerte (déjà échappé)
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wspo-account">
	<?php if ( $title ) : ?>
		<h3 class="wspo-account__title"><?php echo esc_html( $title ); ?></h3>
	<?php endif; ?>
	<?php if ( $text ) : ?>
		<p><?php echo esc_html( $text ); ?></p>
	<?php endif; ?>
	<?php if ( $items ) : ?>
		<ul class="wspo-account__list">
			<?php foreach ( $items as $it ) : ?>
				<li>
					<a href="<?php echo esc_url( $it['url'] ); ?>"><?php echo esc_html( $it['name'] ); ?></a>
					<?php if ( $it['release'] ) : ?>
						<span class="wspo-account__date"><?php echo esc_html( wp_date( get_option( 'date_format' ), $it['release'] ) ); ?></span>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
	<?php echo $form; // phpcs:ignore WordPress.Security.EscapeOutput ?>
</div>

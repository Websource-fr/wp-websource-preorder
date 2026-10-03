<?php
/**
 * Widget « Alertes précommandes » pour les barres latérales des thèmes classiques.
 *
 * @package WebsourcePreorder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSPO_Widget extends WP_Widget {

	public function __construct() {
		parent::__construct( 'wspo_subscribe', __( 'Alertes précommandes (Websource)', 'websource-preorder' ), array( 'description' => __( 'Formulaire d’inscription aux alertes e-mail de précommandes.', 'websource-preorder' ) ) );
	}

	public function widget( $args, $instance ) {
		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput
		echo WSPO_Front::subscribe_form( 'widget' ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	public function form( $instance ) {
		echo '<p>' . esc_html__( 'Les textes se règlent dans Précommandes > Réglages > Alertes.', 'websource-preorder' ) . '</p>';
	}
}

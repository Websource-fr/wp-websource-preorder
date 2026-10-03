<?php
/**
 * Fonctions utilitaires (dates, gabarits, formats).
 *
 * @package WebsourcePreorder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Date MySQL UTC -> timestamp (ou null). */
function wspo_ts( $mysql_utc ) {
	if ( empty( $mysql_utc ) || 0 === strpos( (string) $mysql_utc, '0000-00-00' ) ) {
		return null;
	}
	$t = strtotime( $mysql_utc . ' UTC' );
	return $t ? $t : null;
}

/** Timestamp -> date MySQL UTC. */
function wspo_mysql( $ts ) {
	return gmdate( 'Y-m-d H:i:s', (int) $ts );
}

/** Saisie datetime-local (heure du site) -> MySQL UTC ou null. */
function wspo_local_to_utc( $value ) {
	$value = trim( (string) $value );
	if ( '' === $value ) {
		return null;
	}
	try {
		$d = new DateTime( str_replace( 'T', ' ', $value ), wp_timezone() );
	} catch ( Exception $e ) {
		return null;
	}
	return gmdate( 'Y-m-d H:i:s', $d->getTimestamp() );
}

/** MySQL UTC -> valeur pour input datetime-local (heure du site). */
function wspo_utc_to_input( $mysql_utc ) {
	$ts = wspo_ts( $mysql_utc );
	return $ts ? wp_date( 'Y-m-d\TH:i', $ts ) : '';
}

function wspo_format_date( $mysql_utc, $with_time = false ) {
	$ts = wspo_ts( $mysql_utc );
	if ( ! $ts ) {
		return '';
	}
	$fmt = get_option( 'date_format' ) . ( $with_time ? ' ' . get_option( 'time_format' ) : '' );
	return wp_date( $fmt, $ts );
}

/**
 * Charge un gabarit, surchargeable par le thème : votre-theme/websource-preorder/{nom}.php
 */
function wspo_get_template( $name, $args = array(), $echo = true ) {
	$located = locate_template( array( 'websource-preorder/' . $name . '.php' ) );
	if ( ! $located ) {
		$located = WSPO_DIR . 'templates/' . $name . '.php';
	}
	$located = apply_filters( 'wspo_locate_template', $located, $name, $args );
	if ( ! file_exists( $located ) ) {
		return '';
	}
	if ( ! $echo ) {
		ob_start();
	}
	// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
	extract( $args, EXTR_SKIP );
	include $located;
	if ( ! $echo ) {
		return ob_get_clean();
	}
	return '';
}

/** Identifiant de locale courant, ex. fr_FR. */
function wspo_current_locale() {
	return function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
}

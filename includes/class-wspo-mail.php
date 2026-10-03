<?php
/**
 * E-mails personnalisables (objet + corps HTML par locale, variables {xxx}).
 *
 * @package WebsourcePreorder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WSPO_Mail {

	const OPTION = 'wspo_mails';

	/** Variables disponibles par e-mail. */
	public static function keys() {
		return array(
			'order'     => array( '{firstname}', '{lastname}', '{order_reference}', '{products_html}', '{release_info}', '{shop_name}', '{shop_url}', '{account_url}' ),
			'available' => array( '{firstname}', '{lastname}', '{order_reference}', '{product_name}', '{product_url}', '{release_date}', '{shop_name}', '{shop_url}', '{account_url}' ),
			'admin'     => array( '{customer}', '{customer_email}', '{order_reference}', '{products_html}', '{shop_name}' ),
			'optin'     => array( '{confirm_url}', '{shop_name}', '{shop_url}' ),
			'digest'    => array( '{products_html}', '{count}', '{unsubscribe_url}', '{shop_name}', '{shop_url}', '{list_url}' ),
		);
	}

	public static function labels() {
		return array(
			'order'     => __( 'Commande de précommande (client)', 'websource-preorder' ),
			'available' => __( 'Produit disponible (client)', 'websource-preorder' ),
			'admin'     => __( 'Nouvelle précommande (administrateur)', 'websource-preorder' ),
			'optin'     => __( 'Confirmation d’inscription aux alertes', 'websource-preorder' ),
			'digest'    => __( 'Récapitulatif périodique', 'websource-preorder' ),
		);
	}

	/** Textes par défaut (fr / en). */
	public static function defaults( $key, $iso ) {
		$btn = 'display:inline-block;padding:10px 18px;background:#222;color:#fff;text-decoration:none;border-radius:4px';
		$d   = array(
			'order'     => array(
				'fr' => array( 'Votre précommande {order_reference} est bien enregistrée', '<p>Bonjour {firstname},</p><p>Merci pour votre précommande <strong>{order_reference}</strong> sur {shop_name}.</p>{products_html}<p>{release_info}</p><p>Nous vous préviendrons par e-mail dès que vos articles seront disponibles. Suivez vos précommandes ici : <a href="{account_url}">mes précommandes</a>.</p>' ),
				'en' => array( 'Your pre-order {order_reference} is confirmed', '<p>Hello {firstname},</p><p>Thank you for your pre-order <strong>{order_reference}</strong> at {shop_name}.</p>{products_html}<p>{release_info}</p><p>We will email you as soon as your items are available. Follow your pre-orders here: <a href="{account_url}">my pre-orders</a>.</p>' ),
			),
			'available' => array(
				'fr' => array( '{product_name} est disponible !', '<p>Bonjour {firstname},</p><p>Bonne nouvelle : <strong>{product_name}</strong> (commande {order_reference}) est maintenant disponible. Votre commande va être préparée.</p><p><a href="{product_url}">Voir le produit</a></p>' ),
				'en' => array( '{product_name} is now available!', '<p>Hello {firstname},</p><p>Good news: <strong>{product_name}</strong> (order {order_reference}) is now available. Your order is being prepared.</p><p><a href="{product_url}">View the product</a></p>' ),
			),
			'admin'     => array(
				'fr' => array( 'Nouvelle précommande {order_reference}', '<p>Nouvelle précommande <strong>{order_reference}</strong> de {customer} ({customer_email}).</p>{products_html}' ),
				'en' => array( 'New pre-order {order_reference}', '<p>New pre-order <strong>{order_reference}</strong> from {customer} ({customer_email}).</p>{products_html}' ),
			),
			'optin'     => array(
				'fr' => array( 'Confirmez votre inscription aux alertes précommandes', '<p>Bonjour,</p><p>Pour recevoir les alertes de précommandes de {shop_name}, confirmez votre adresse e-mail :</p><p><a href="{confirm_url}" style="' . $btn . '">Confirmer mon inscription</a></p><p>Si vous n’êtes pas à l’origine de cette demande, ignorez simplement ce message.</p>' ),
				'en' => array( 'Confirm your pre-order alerts subscription', '<p>Hello,</p><p>To receive pre-order alerts from {shop_name}, please confirm your email address:</p><p><a href="{confirm_url}" style="' . $btn . '">Confirm my subscription</a></p><p>If you did not request this, just ignore this message.</p>' ),
			),
			'digest'    => array(
				'fr' => array( '{count} précommande(s) à découvrir chez {shop_name}', '<p>Bonjour,</p><p>Voici les précommandes actuellement ouvertes :</p>{products_html}<p><a href="{list_url}">Voir toutes les précommandes</a></p><p style="font-size:12px;color:#888">Vous recevez ce message car vous êtes inscrit aux alertes. <a href="{unsubscribe_url}">Se désinscrire</a></p>' ),
				'en' => array( '{count} pre-order(s) to discover at {shop_name}', '<p>Hello,</p><p>Here are the pre-orders currently open:</p>{products_html}<p><a href="{list_url}">See all pre-orders</a></p><p style="font-size:12px;color:#888">You receive this because you subscribed to alerts. <a href="{unsubscribe_url}">Unsubscribe</a></p>' ),
			),
		);
		$lang = 'en' === $iso ? 'en' : 'fr';
		$v    = $d[ $key ][ $lang ];
		return array( 'subject' => $v[0], 'body' => $v[1] );
	}

	public static function iso( $locale ) {
		return strtolower( substr( (string) $locale, 0, 2 ) );
	}

	/** Objet + corps pour une clé et une locale (personnalisé > défaut). */
	public static function get( $key, $locale ) {
		$saved = get_option( self::OPTION, array() );
		if ( isset( $saved[ $key ][ $locale ] ) && '' !== trim( (string) $saved[ $key ][ $locale ]['body'] ) ) {
			return $saved[ $key ][ $locale ];
		}
		// Même langue, autre région (fr_BE -> fr_FR personnalisé).
		if ( isset( $saved[ $key ] ) ) {
			foreach ( $saved[ $key ] as $loc => $v ) {
				if ( self::iso( $loc ) === self::iso( $locale ) && '' !== trim( (string) $v['body'] ) ) {
					return $v;
				}
			}
		}
		return self::defaults( $key, self::iso( $locale ) );
	}

	public static function render( $text, array $vars ) {
		$out = (string) $text;
		foreach ( $vars as $k => $v ) {
			$out = str_replace( '{' . $k . '}', (string) $v, $out );
		}
		return $out;
	}

	/** Variables communes à tous les e-mails. */
	public static function common_vars() {
		$account = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/' );
		return array(
			'shop_name'   => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'shop_url'    => home_url( '/' ),
			'account_url' => $account,
			'list_url'    => WSPO_Front::list_url(),
		);
	}

	/** Enveloppe HTML aux couleurs du site. */
	public static function wrap( $title, $content ) {
		$accent = esc_attr( WSPO_Settings::get( 'c_accent' ) );
		$on     = esc_attr( WSPO_Settings::get( 'c_on_accent' ) );
		$shop   = esc_html( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );
		return '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"></head>'
			. '<body style="margin:0;padding:0;background:#f4f4f5;font-family:Arial,Helvetica,sans-serif;color:#1f2937">'
			. '<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;padding:24px 0"><tr><td align="center">'
			. '<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#fff;border-radius:6px;overflow:hidden">'
			. '<tr><td style="background:' . $accent . ';color:' . $on . ';padding:18px 24px;font-size:20px;font-weight:bold">' . $shop . '</td></tr>'
			. '<tr><td style="padding:24px;font-size:15px;line-height:1.55">' . $content . '</td></tr>'
			. '<tr><td style="padding:14px 24px;background:#fafafa;color:#888;font-size:12px;border-top:1px solid #eee"><a href="' . esc_url( home_url( '/' ) ) . '" style="color:#888">' . esc_html( preg_replace( '#^https?://#', '', home_url() ) ) . '</a></td></tr>'
			. '</table></td></tr></table></body></html>';
	}

	/**
	 * Envoie un e-mail. @return bool
	 */
	public static function send( $key, $to, $locale, array $vars ) {
		if ( ! is_email( $to ) ) {
			return false;
		}
		$tpl  = self::get( $key, $locale ? $locale : get_locale() );
		$vars = array_merge( self::common_vars(), $vars );
		$safe = array();
		foreach ( $vars as $k => $v ) {
			if ( '_html' === substr( $k, -5 ) ) {
				$safe[ $k ] = $v; // Déjà construit et échappé par le plugin.
			} elseif ( '_url' === substr( $k, -4 ) ) {
				$safe[ $k ] = esc_url( $v );
			} else {
				$safe[ $k ] = esc_html( $v );
			}
		}
		$body = self::render( $tpl['body'], $safe );
		$subj = wp_strip_all_tags( self::render( $tpl['subject'], array_map( 'wp_strip_all_tags', $vars ) ) );
		$html = self::wrap( $subj, $body );
		$alt  = trim( preg_replace( "/\n{3,}/", "\n\n", html_entity_decode( wp_strip_all_tags( preg_replace( '#<(br|/p|/li|/tr|/h[1-6])\s*/?>#i', "\n", $body ) ), ENT_QUOTES, 'UTF-8' ) ) );

		$ct = function () {
			return 'text/html';
		};
		$alt_cb = function ( $phpmailer ) use ( $alt ) {
			$phpmailer->AltBody = $alt; // phpcs:ignore WordPress.NamingConventions.ValidVariableName
		};
		add_filter( 'wp_mail_content_type', $ct );
		add_action( 'phpmailer_init', $alt_cb, 99 );
		$ok = wp_mail( $to, $subj, $html );
		remove_filter( 'wp_mail_content_type', $ct );
		remove_action( 'phpmailer_init', $alt_cb, 99 );
		return (bool) $ok;
	}
}

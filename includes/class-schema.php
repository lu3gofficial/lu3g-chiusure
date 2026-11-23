<?php
/**
 * Dati strutturati JSON-LD: specialOpeningHoursSpecification con le chiusure dei prossimi 12 mesi.
 *
 * Per Schema.org un giorno di chiusura si indica con opens = closes = "00:00" nel periodo validFrom → validThrough.
 * Stampato solo nella home (dove di solito sta l'attività), disattivato di default: si attiva in Aspetto e comportamento.
 *
 * @package LU3G_Chiusure
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * JSON-LD.
 */
final class LU3G_Chiusure_Schema {

	public static function init() {
		add_action( 'wp_head', array( __CLASS__, 'output' ), 30 );
	}

	/**
	 * Oggetto Schema.org (null se disattivato o senza chiusure nei prossimi 12 mesi).
	 *
	 * @param int|null $today Giorno di riferimento.
	 */
	public static function build( ?int $today = null ): ?array {
		$s = LU3G_Chiusure_Plugin::settings();
		if ( empty( $s['schema_enabled'] ) ) {
			return null;
		}
		$today = $today ?? LU3G_Chiusure_Closures::today();
		$specs = array();
		foreach ( LU3G_Chiusure_Closures::upcoming( $today, 365, false ) as $item ) {
			$o   = $item['occ'];
			$key = $o['s'] . '-' . $o['e'];
			if ( isset( $specs[ $key ] ) ) {
				continue; // Due chiusure con le stesse date: una sola voce.
			}
			$specs[ $key ] = array(
				'@type'        => 'OpeningHoursSpecification',
				'opens'        => '00:00',
				'closes'       => '00:00',
				'validFrom'    => LU3G_Chiusure_Closures::ymd( $o['s'] ),
				'validThrough' => LU3G_Chiusure_Closures::ymd( $o['e'] ),
			);
		}
		if ( ! $specs ) {
			return null;
		}
		$name = LU3G_Chiusure_Plugin::translate( (string) $s['schema_name'], 'schema_name' );
		$data = array(
			'@context'                        => 'https://schema.org',
			'@type'                           => $s['schema_type'] ? $s['schema_type'] : 'LocalBusiness',
			'@id'                             => home_url( '/#lu3g-chiusure-business' ),
			'name'                            => '' !== $name ? $name : get_bloginfo( 'name' ),
			'url'                             => home_url( '/' ),
			'specialOpeningHoursSpecification' => array_values( $specs ),
		);
		return (array) apply_filters( 'lu3g_chiusure_schema', $data );
	}

	/**
	 * Stampa il JSON-LD nella home.
	 */
	public static function output() {
		if ( ! apply_filters( 'lu3g_chiusure_schema_on_page', is_front_page() ) ) {
			return;
		}
		$data = self::build();
		if ( $data ) {
			echo "\n" . '<script type="application/ld+json" class="lu3g-chiusure-schema">' . wp_json_encode( $data, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON codificato con JSON_HEX_TAG
		}
	}
}

<?php
/**
 * Chiusure: tipologie, pulizia dei dati, date, ricorrenze, fase corrente.
 *
 * Le date sono trattate come "giorni interi": numero di giorni dal 1/1/1970 (giorno 0), senza ore e senza fuso.
 * "Oggi" si calcola nel fuso orario del sito (Impostazioni → Generali). Lo stesso confronto lo fa popup.js nel browser.
 *
 * @package LU3G_Chiusure
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Logica delle chiusure.
 */
final class LU3G_Chiusure_Closures {

	/** Opzione unica (array serializzato) con l'elenco delle chiusure. */
	const OPTION = 'lu3g_chiusure_closures';

	/** Secondi in un giorno. */
	const DAY = 86400;

	/* ================================================================ tipologie */

	/**
	 * Tipologie: etichetta, icona SVG e testi predefiniti per le due fasi.
	 */
	public static function types(): array {
		$one = __( 'Il {inizio} saremo chiusi. Riapriamo il {riapertura}.', 'lu3g-chiusure' );
		return array(
			'summer'    => array(
				'label'          => __( 'Estiva', 'lu3g-chiusure' ),
				'notice_title'   => __( 'Chiusura estiva', 'lu3g-chiusure' ),
				'notice_message' => __( 'Dal {inizio} al {fine} saremo chiusi per le ferie estive. Riapriamo il {riapertura}.', 'lu3g-chiusure' ),
				'active_title'   => __( 'Siamo in ferie', 'lu3g-chiusure' ),
				'active_message' => __( 'Siamo chiusi per le ferie estive: riapriamo il {riapertura}. Grazie per la pazienza!', 'lu3g-chiusure' ),
				'one_day'        => $one,
			),
			'christmas' => array(
				'label'          => __( 'Natalizia', 'lu3g-chiusure' ),
				'notice_title'   => __( 'Chiusura per le feste', 'lu3g-chiusure' ),
				'notice_message' => __( 'Dal {inizio} al {fine} saremo chiusi per le festività natalizie. Riapriamo il {riapertura}.', 'lu3g-chiusure' ),
				'active_title'   => __( 'Buone feste!', 'lu3g-chiusure' ),
				'active_message' => __( 'Siamo chiusi per le festività natalizie: riapriamo il {riapertura}.', 'lu3g-chiusure' ),
				'one_day'        => $one,
			),
			'easter'    => array(
				'label'          => __( 'Pasqua', 'lu3g-chiusure' ),
				'notice_title'   => __( 'Chiusura per Pasqua', 'lu3g-chiusure' ),
				'notice_message' => __( 'Dal {inizio} al {fine} saremo chiusi per le festività pasquali. Riapriamo il {riapertura}.', 'lu3g-chiusure' ),
				'active_title'   => __( 'Buona Pasqua!', 'lu3g-chiusure' ),
				'active_message' => __( 'Siamo chiusi per le festività pasquali: riapriamo il {riapertura}.', 'lu3g-chiusure' ),
				'one_day'        => $one,
			),
			'bridge'    => array(
				'label'          => __( 'Ponte', 'lu3g-chiusure' ),
				'notice_title'   => __( 'Chiusura per ponte', 'lu3g-chiusure' ),
				'notice_message' => __( 'Dal {inizio} al {fine} saremo chiusi per il ponte. Riapriamo il {riapertura}.', 'lu3g-chiusure' ),
				'active_title'   => __( 'Oggi siamo chiusi', 'lu3g-chiusure' ),
				'active_message' => __( 'Siamo chiusi per il ponte: riapriamo il {riapertura}.', 'lu3g-chiusure' ),
				'one_day'        => $one,
			),
			'extra'     => array(
				'label'          => __( 'Straordinaria', 'lu3g-chiusure' ),
				'notice_title'   => __( 'Chiusura straordinaria', 'lu3g-chiusure' ),
				'notice_message' => __( 'Dal {inizio} al {fine} saremo chiusi. Riapriamo il {riapertura}.', 'lu3g-chiusure' ),
				'active_title'   => __( 'Siamo chiusi', 'lu3g-chiusure' ),
				'active_message' => __( 'Siamo temporaneamente chiusi: riapriamo il {riapertura}.', 'lu3g-chiusure' ),
				'one_day'        => $one,
			),
		);
	}

	/** Icona predefinita di ogni tipologia (chiave della libreria). */
	const TYPE_ICONS = array(
		'summer'    => 'sun',
		'christmas' => 'snowflake',
		'easter'    => 'egg',
		'bridge'    => 'bridge',
		'extra'     => 'calendar-x',
	);

	/**
	 * Libreria di icone (disegnate per il plugin, 24×24, solo tratto): chiave => [ nome, contenuto SVG ].
	 */
	public static function icon_library(): array {
		return array(
			'sun'        => array( __( 'Sole', 'lu3g-chiusure' ), '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>' ),
			'umbrella'   => array( __( 'Ombrellone', 'lu3g-chiusure' ), '<path d="M12 3a9 9 0 0 1 9 9H3a9 9 0 0 1 9-9z"/><path d="M12 12v7a2 2 0 0 0 4 0"/>' ),
			'plane'      => array( __( 'Viaggio', 'lu3g-chiusure' ), '<path d="M2.5 12.5L21.5 4l-5.5 16-4-6.5z"/><path d="M12 13.5L21.5 4"/>' ),
			'leaf'       => array( __( 'Foglia', 'lu3g-chiusure' ), '<path d="M5 20c0-9 6-15 15-15 0 9-5.5 15-14.5 15"/><path d="M5 20c3-4.5 6.5-7.5 10.5-9.5"/>' ),
			'snowflake'  => array( __( 'Fiocco di neve', 'lu3g-chiusure' ), '<path d="M12 2v20M3.3 7l17.4 10M3.3 17l17.4-10M9 3.5l3 2 3-2M9 20.5l3-2 3 2M3.7 10.5l3-1.4-.3-3.5M20.3 13.5l-3 1.4.3 3.5M3.7 13.5l3 1.4-.3 3.5M20.3 10.5l-3-1.4.3-3.5"/>' ),
			'tree'       => array( __( 'Albero di Natale', 'lu3g-chiusure' ), '<path d="M12 2.5l5 6.5h-3l4 6h-3.5l3 4h-11l3-4H6l4-6H7z"/><path d="M12 19v3"/>' ),
			'gift'       => array( __( 'Regalo', 'lu3g-chiusure' ), '<rect x="3" y="8" width="18" height="4" rx="1"/><path d="M5 12v9h14v-9M12 8v13M12 8S10.5 3.5 8 4.2 7 8 12 8zM12 8s1.5-4.5 4-3.8S17 8 12 8z"/>' ),
			'star'       => array( __( 'Stella', 'lu3g-chiusure' ), '<path d="M12 3l2.8 5.7 6.2.9-4.5 4.4 1.1 6.2L12 17.3l-5.6 2.9 1.1-6.2L3 9.6l6.2-.9z"/>' ),
			'egg'        => array( __( 'Uovo', 'lu3g-chiusure' ), '<path d="M12 2.5c-3.6 0-6.8 5.6-6.8 10.6a6.8 6.8 0 0 0 13.6 0c0-5-3.2-10.6-6.8-10.6z"/><path d="M5.6 12.5l2.1-1.6 2.1 1.6 2.2-1.6 2.1 1.6 2.1-1.6 2.1 1.6"/>' ),
			'flower'     => array( __( 'Fiore', 'lu3g-chiusure' ), '<circle cx="12" cy="9.5" r="1.6"/><circle cx="12" cy="6" r="2.2"/><circle cx="15.3" cy="8.4" r="2.2"/><circle cx="14.1" cy="12.3" r="2.2"/><circle cx="9.9" cy="12.3" r="2.2"/><circle cx="8.7" cy="8.4" r="2.2"/><path d="M12 14.5V22M12 19c1.5-1.5 3-2 4.5-1.6"/>' ),
			'party'      => array( __( 'Festa', 'lu3g-chiusure' ), '<path d="M4 20l4.5-12.5 8 8z"/><path d="M14 3.5v2M19.5 9h2M17.5 5.5L19 4M14.5 9.5c1-1 2.5-1.2 3.5-.5"/>' ),
			'heart'      => array( __( 'Cuore', 'lu3g-chiusure' ), '<path d="M12 20s-7.5-4.6-9-9.3C2 7.4 4.2 4.5 7.3 4.5c2 0 3.6 1.1 4.7 2.7 1.1-1.6 2.7-2.7 4.7-2.7 3.1 0 5.3 2.9 4.3 6.2-1.5 4.7-9 9.3-9 9.3z"/>' ),
			'bridge'     => array( __( 'Ponte', 'lu3g-chiusure' ), '<path d="M2 9h20M5 9v10M19 9v10M5 19a7 7 0 0 1 14 0M9 9v3.2M15 9v3.2M12 9v2.4"/>' ),
			'calendar-x' => array( __( 'Calendario', 'lu3g-chiusure' ), '<rect x="3" y="4.5" width="18" height="16.5" rx="2.5"/><path d="M3 9.5h18M8 2.5v4M16 2.5v4M10 13l4 4M14 13l-4 4"/>' ),
			'clock'      => array( __( 'Orologio', 'lu3g-chiusure' ), '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.2 2"/>' ),
			'store'      => array( __( 'Negozio', 'lu3g-chiusure' ), '<path d="M3 9l1.5-5h15L21 9M3 9v11h18V9M3 9h18"/><path d="M9.5 20v-6h5v6"/>' ),
			'wrench'     => array( __( 'Manutenzione', 'lu3g-chiusure' ), '<path d="M15 3.5a5 5 0 0 0-4.6 6.9l-6.9 6.9a2 2 0 0 0 2.8 2.8l6.9-6.9A5 5 0 0 0 20.5 9l-3 1-2.5-2.5 1-3z"/>' ),
			'alert'      => array( __( 'Avviso', 'lu3g-chiusure' ), '<path d="M12 3.5l9.5 16.5h-19z"/><path d="M12 10v4.5M12 17.5v.01"/>' ),
			'info'       => array( __( 'Informazione', 'lu3g-chiusure' ), '<circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7.5v.01"/>' ),
		);
	}

	/**
	 * Icona SVG in linea (24×24, colore dal testo: currentColor). Accetta una chiave della libreria o una tipologia.
	 *
	 * @param string $key Chiave dell'icona o tipologia.
	 */
	public static function icon( string $key ): string {
		$lib = self::icon_library();
		$key = self::TYPE_ICONS[ $key ] ?? $key;
		$p   = $lib[ $key ][1] ?? $lib['calendar-x'][1];
		return '<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $p . '</svg>';
	}

	/**
	 * Icona da mostrare per una chiusura.
	 * Campo "icon": '' = quella della tipologia, 'none' = nessuna, 'image' = immagine della libreria media, altrimenti una chiave.
	 *
	 * @param array $c    Chiusura.
	 * @param bool  $show Icone attive nelle impostazioni (Aspetto → Mostra l'icona).
	 * @return array [ 'key' => chiave della libreria o '', 'img' => URL dell'immagine o '' ].
	 */
	public static function resolve_icon( array $c, bool $show = true ): array {
		$none = array( 'key' => '', 'img' => '' );
		$icon = (string) ( $c['icon'] ?? '' );
		if ( ! $show || 'none' === $icon ) {
			return $none;
		}
		if ( 'image' === $icon ) {
			$url = ! empty( $c['icon_image'] ) ? wp_get_attachment_image_url( (int) $c['icon_image'], 'thumbnail' ) : '';
			return $url ? array( 'key' => '', 'img' => (string) $url ) : array( 'key' => self::TYPE_ICONS[ $c['type'] ] ?? 'calendar-x', 'img' => '' );
		}
		if ( '' !== $icon && isset( self::icon_library()[ $icon ] ) ) {
			return array( 'key' => $icon, 'img' => '' );
		}
		return array( 'key' => self::TYPE_ICONS[ $c['type'] ] ?? 'calendar-x', 'img' => '' );
	}

	/* ================================================================ dati */

	/**
	 * Chiusura vuota con i valori predefiniti.
	 */
	public static function blank(): array {
		return array(
			'id'             => '',
			'enabled'        => true,
			'name'           => '',
			'type'           => 'summer',
			'icon'           => '',  // '' = della tipologia | 'none' | 'image' | chiave della libreria.
			'icon_image'     => 0,   // ID dell'allegato quando icon = 'image'.
			'start'          => '',
			'end'            => '',
			'reopen'         => '',
			'recurring'      => false,
			'notice_days'    => 7,
			'notice_title'   => '',
			'notice_message' => '',
			'active_title'   => '',
			'active_message' => '',
		);
	}

	/**
	 * Chiusure salvate (senza filtri): per il pannello.
	 */
	public static function saved(): array {
		$list = get_option( self::OPTION, array() );
		$out  = array();
		foreach ( is_array( $list ) ? $list : array() as $row ) {
			if ( is_array( $row ) ) {
				$out[] = array_merge( self::blank(), $row );
			}
		}
		return $out;
	}

	/**
	 * Chiusure per il sito, modificabili dalle estensioni con il filtro lu3g_chiusure_closures.
	 */
	public static function all(): array {
		$list = (array) apply_filters( 'lu3g_chiusure_closures', self::saved() );
		$out  = array();
		foreach ( $list as $row ) {
			if ( is_array( $row ) ) {
				$out[] = array_merge( self::blank(), $row );
			}
		}
		return $out;
	}

	/**
	 * Pulisce una chiusura arrivata dal modulo.
	 *
	 * @param array $in Dati non fidati (già senza slash).
	 */
	public static function sanitize( array $in ): array {
		$c       = self::blank();
		$id      = strtolower( preg_replace( '/[^A-Za-z0-9]/', '', (string) ( $in['id'] ?? '' ) ) );
		$c['id'] = '' !== $id ? substr( $id, 0, 20 ) : 'c' . strtolower( wp_generate_password( 8, false, false ) );

		$c['enabled']   = ! empty( $in['enabled'] );
		$c['recurring'] = ! empty( $in['recurring'] );
		$c['name']      = sanitize_text_field( $in['name'] ?? '' );
		$c['type']      = LU3G_Chiusure_Plugin::choice( $in['type'] ?? '', array_keys( self::types() ), 'summer' );
		$icon           = sanitize_key( (string) ( $in['icon'] ?? '' ) );
		$c['icon']      = in_array( $icon, array( 'none', 'image' ), true ) || isset( self::icon_library()[ $icon ] ) ? $icon : '';
		$c['icon_image'] = absint( $in['icon_image'] ?? 0 );
		if ( 'image' === $c['icon'] && ( ! $c['icon_image'] || ! wp_attachment_is_image( $c['icon_image'] ) ) ) {
			$c['icon'] = ''; // Immagine non scelta o non valida: torna all'icona della tipologia.
		}

		foreach ( array( 'start', 'end', 'reopen' ) as $k ) {
			$v       = sanitize_text_field( $in[ $k ] ?? '' );
			$c[ $k ] = null !== self::day( $v ) ? $v : '';
		}
		// Chiusura non ricorrente con le date invertite: le scambia invece di perderla.
		if ( '' !== $c['start'] && '' !== $c['end'] && $c['end'] < $c['start'] && ( ! $c['recurring'] || 'easter' === $c['type'] ) ) {
			list( $c['start'], $c['end'] ) = array( $c['end'], $c['start'] );
		}

		$c['notice_days']    = max( 0, min( 365, absint( $in['notice_days'] ?? 0 ) ) );
		$c['notice_title']   = sanitize_text_field( $in['notice_title'] ?? '' );
		$c['notice_message'] = sanitize_textarea_field( $in['notice_message'] ?? '' );
		$c['active_title']   = sanitize_text_field( $in['active_title'] ?? '' );
		$c['active_message'] = sanitize_textarea_field( $in['active_message'] ?? '' );
		return $c;
	}

	/**
	 * Registra in WPML i testi scritti dal cliente.
	 *
	 * @param array $c Chiusura pulita.
	 */
	public static function register_strings( array $c ) {
		foreach ( array( 'name', 'notice_title', 'notice_message', 'active_title', 'active_message' ) as $k ) {
			LU3G_Chiusure_Plugin::register_string( 'closure-' . $c['id'] . '-' . $k, (string) $c[ $k ] );
		}
	}

	/**
	 * Testi definitivi di una chiusura: quelli del cliente (tradotti con WPML) o i predefiniti della tipologia.
	 *
	 * @param array $c       Chiusura.
	 * @param bool  $one_day Chiusura di un solo giorno (cambia il messaggio predefinito del preavviso).
	 */
	public static function texts( array $c, bool $one_day = false ): array {
		$types = self::types();
		$t     = $types[ $c['type'] ] ?? $types['extra'];
		$out   = array();
		foreach ( array( 'name', 'notice_title', 'notice_message', 'active_title', 'active_message' ) as $k ) {
			$own       = LU3G_Chiusure_Plugin::translate( (string) $c[ $k ], 'closure-' . $c['id'] . '-' . $k );
			$fallback  = 'name' === $k ? $t['label'] : ( 'notice_message' === $k && $one_day ? $t['one_day'] : $t[ $k ] );
			$out[ $k ] = '' !== $own ? $own : $fallback;
		}
		return $out;
	}

	/* ================================================================ date */

	/**
	 * "AAAA-MM-GG" → numero del giorno (null se la data non è valida).
	 *
	 * @param string $ymd Data.
	 */
	public static function day( string $ymd ): ?int {
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $ymd, $m ) || ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
			return null;
		}
		return self::make( (int) $m[1], (int) $m[2], (int) $m[3] );
	}

	/**
	 * Numero del giorno da anno/mese/giorno. Il 29 febbraio negli anni non bisestili diventa il 28.
	 *
	 * @param int $y Anno.
	 * @param int $m Mese.
	 * @param int $d Giorno.
	 */
	public static function make( int $y, int $m, int $d ): int {
		if ( 2 === $m && 29 === $d && ! checkdate( 2, 29, $y ) ) {
			$d = 28;
		}
		return intdiv( gmmktime( 0, 0, 0, $m, $d, $y ), self::DAY );
	}

	/**
	 * Numero del giorno → "AAAA-MM-GG".
	 *
	 * @param int $day Giorno.
	 */
	public static function ymd( int $day ): string {
		return gmdate( 'Y-m-d', $day * self::DAY );
	}

	/**
	 * Oggi nel fuso orario del sito.
	 */
	public static function today(): int {
		$now = new DateTimeImmutable( 'now', wp_timezone() );
		return (int) self::day( $now->format( 'Y-m-d' ) );
	}

	/**
	 * Data formattata con il formato di WordPress (Impostazioni → Generali) nella lingua corrente.
	 * date_i18n() con un timestamp "con offset" formatta in UTC: con la mezzanotte UTC esce il giorno giusto.
	 *
	 * @param int $day Giorno.
	 */
	public static function format( int $day ): string {
		return date_i18n( (string) get_option( 'date_format', 'j F Y' ), $day * self::DAY );
	}

	/**
	 * Domenica di Pasqua (calendario gregoriano, algoritmo anonimo di Meeus/Jones/Butcher).
	 *
	 * @param int $y Anno.
	 */
	public static function easter( int $y ): int {
		$a = $y % 19;
		$b = intdiv( $y, 100 );
		$c = $y % 100;
		$d = intdiv( $b, 4 );
		$e = $b % 4;
		$f = intdiv( $b + 8, 25 );
		$g = intdiv( $b - $f + 1, 3 );
		$h = ( 19 * $a + $b - $d - $g + 15 ) % 30;
		$i = intdiv( $c, 4 );
		$k = $c % 4;
		$l = ( 32 + 2 * $e + 2 * $i - $h - $k ) % 7;
		$m = intdiv( $a + 11 * $h + 22 * $l, 451 );
		$n = $h + $l - 7 * $m + 114;
		return self::make( $y, intdiv( $n, 31 ), ( $n % 31 ) + 1 );
	}

	/**
	 * Periodi concreti di una chiusura: array di [ 's' => inizio, 'e' => fine, 'r' => riapertura ] (giorni).
	 *
	 * - Non ricorrente: un solo periodo; riapertura vuota (o non successiva alla fine) = giorno dopo la fine.
	 * - Ricorrente: conta solo giorno e mese, per gli anni da (anno di $today - 1) a (+2). Se la fine cade prima
	 *   dell'inizio nel calendario (es. 24/12 → 06/01) il periodo finisce nell'anno dopo. La riapertura segue la fine
	 *   (stesso anno, o quello dopo se cade prima della fine nel calendario).
	 * - Pasqua ricorrente: le date si spostano con la Pasqua di ogni anno (stessa distanza dalla domenica di Pasqua).
	 *
	 * @param array $c     Chiusura.
	 * @param int   $today Giorno di riferimento.
	 */
	public static function occurrences( array $c, int $today ): array {
		$s = self::day( (string) $c['start'] );
		$e = self::day( (string) $c['end'] );
		if ( null === $s || null === $e ) {
			return array();
		}
		$r = '' !== (string) $c['reopen'] ? self::day( (string) $c['reopen'] ) : null;

		if ( empty( $c['recurring'] ) ) {
			if ( $e < $s ) {
				list( $s, $e ) = array( $e, $s );
			}
			return array( array( 's' => $s, 'e' => $e, 'r' => ( null !== $r && $r > $e ) ? $r : $e + 1 ) );
		}

		$year = (int) gmdate( 'Y', $today * self::DAY );
		$out  = array();

		if ( 'easter' === $c['type'] ) {
			if ( $e < $s ) {
				list( $s, $e ) = array( $e, $s );
			}
			$base = self::easter( (int) gmdate( 'Y', $s * self::DAY ) );
			$ds   = $s - $base;
			$de   = $e - $base;
			$dr   = ( null !== $r && $r > $e ) ? $r - $base : $de + 1;
			for ( $y = $year - 1; $y <= $year + 2; $y++ ) {
				$p     = self::easter( $y );
				$out[] = array( 's' => $p + $ds, 'e' => $p + $de, 'r' => $p + $dr );
			}
			return $out;
		}

		list( $sm, $sd ) = array_map( 'intval', explode( '-', gmdate( 'n-j', $s * self::DAY ) ) );
		list( $em, $ed ) = array_map( 'intval', explode( '-', gmdate( 'n-j', $e * self::DAY ) ) );
		$cross           = ( $em * 100 + $ed ) < ( $sm * 100 + $sd ); // A cavallo d'anno.
		if ( null !== $r ) {
			list( $rm, $rd ) = array_map( 'intval', explode( '-', gmdate( 'n-j', $r * self::DAY ) ) );
		}
		for ( $y = $year - 1; $y <= $year + 2; $y++ ) {
			$ey = $cross ? $y + 1 : $y;
			$os = self::make( $y, $sm, $sd );
			$oe = self::make( $ey, $em, $ed );
			$or = $oe + 1;
			if ( null !== $r ) {
				$or = self::make( ( $rm * 100 + $rd ) <= ( $em * 100 + $ed ) ? $ey + 1 : $ey, $rm, $rd );
			}
			$out[] = array( 's' => $os, 'e' => $oe, 'r' => $or );
		}
		return $out;
	}

	/**
	 * Fase di un periodo in un giorno: 'active' (in corso), 'notice' (preavviso) o null.
	 *
	 * @param array $c     Chiusura.
	 * @param array $o     Periodo (da occurrences()).
	 * @param int   $today Giorno.
	 */
	public static function phase( array $c, array $o, int $today ): ?string {
		if ( $today >= $o['s'] && $today <= $o['e'] ) {
			return 'active';
		}
		$n = (int) $c['notice_days'];
		if ( $n > 0 && $today >= $o['s'] - $n && $today < $o['s'] ) {
			return 'notice';
		}
		return null;
	}

	/**
	 * Chiusura da mostrare in un giorno, con la stessa regola di popup.js: prima quelle in corso, poi la più vicina.
	 * Serve per il pannello e per future integrazioni non in cache (es. carrello WooCommerce).
	 *
	 * @param int|null $today Giorno (predefinito: oggi nel fuso del sito).
	 * @return array|null [ 'closure' => array, 'occ' => array, 'phase' => string ]
	 */
	public static function current( ?int $today = null ): ?array {
		$today = $today ?? self::today();
		$best  = null;
		$score = PHP_INT_MAX;
		foreach ( self::all() as $c ) {
			if ( empty( $c['enabled'] ) ) {
				continue;
			}
			foreach ( self::occurrences( $c, $today ) as $o ) {
				$ph = self::phase( $c, $o, $today );
				if ( null === $ph ) {
					continue;
				}
				$sc = 'active' === $ph ? $o['s'] - 1000000 : $o['s'];
				if ( $sc < $score ) {
					$score = $sc;
					$best  = array( 'closure' => $c, 'occ' => $o, 'phase' => $ph );
				}
			}
		}
		return $best;
	}

	/**
	 * Periodi delle chiusure attive che toccano i prossimi $days giorni (anche solo con il preavviso).
	 *
	 * @param int  $today       Giorno.
	 * @param int  $days        Finestra in giorni.
	 * @param bool $with_notice Conta anche i giorni di preavviso (popup) o solo la chiusura (dati strutturati).
	 * @return array Elenco di [ 'closure' => array, 'occ' => array ].
	 */
	public static function upcoming( int $today, int $days = 365, bool $with_notice = true ): array {
		$out = array();
		foreach ( self::all() as $c ) {
			if ( empty( $c['enabled'] ) ) {
				continue;
			}
			$n = $with_notice ? (int) $c['notice_days'] : 0;
			foreach ( self::occurrences( $c, $today ) as $o ) {
				if ( $o['e'] >= $today && $o['s'] - $n <= $today + $days ) {
					$out[] = array( 'closure' => $c, 'occ' => $o );
				}
			}
		}
		usort(
			$out,
			function ( $a, $b ) {
				return $a['occ']['s'] <=> $b['occ']['s'];
			}
		);
		return $out;
	}

	/**
	 * Stato di una chiusura per il pannello.
	 *
	 * @param array $c     Chiusura.
	 * @param int   $today Giorno.
	 * @return array [ stato, testo ] con stato off | error | active | notice | scheduled | past.
	 */
	public static function status( array $c, int $today ): array {
		$occ = self::occurrences( $c, $today );
		if ( ! $occ ) {
			return array( 'error', __( 'Date mancanti', 'lu3g-chiusure' ) );
		}
		if ( empty( $c['enabled'] ) ) {
			return array( 'off', __( 'Disattivata', 'lu3g-chiusure' ) );
		}
		foreach ( $occ as $o ) {
			$ph = self::phase( $c, $o, $today );
			if ( 'active' === $ph ) {
				/* translators: %s: data di riapertura */
				return array( 'active', sprintf( __( 'In corso · riapre il %s', 'lu3g-chiusure' ), self::format( $o['r'] ) ) );
			}
			if ( 'notice' === $ph ) {
				/* translators: %s: data di inizio */
				return array( 'notice', sprintf( __( 'Preavviso in corso · chiude il %s', 'lu3g-chiusure' ), self::format( $o['s'] ) ) );
			}
		}
		foreach ( $occ as $o ) {
			if ( $o['s'] > $today ) {
				/* translators: 1: data di inizio, 2: data di fine */
				return array( 'scheduled', sprintf( __( 'Prossima: %1$s – %2$s', 'lu3g-chiusure' ), self::format( $o['s'] ), self::format( $o['e'] ) ) );
			}
		}
		return array( 'past', __( 'Conclusa', 'lu3g-chiusure' ) );
	}
}

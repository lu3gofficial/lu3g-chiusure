<?php
/**
 * Sito pubblico: dati del popup in JSON nel footer e caricamento condizionale di popup.js / popup.css.
 *
 * Compatibilità con la cache: qui NON si decide se mostrare il popup (la pagina può restare in cache per giorni).
 * PHP stampa tutte le chiusure con i loro periodi già calcolati e le date già formattate; è popup.js a confrontarle
 * con la data di oggi nel browser. In PHP si decide solo ciò che non dipende dal giorno: la pagina (home, ID) e
 * se esiste almeno una chiusura nei prossimi 12 mesi (altrimenti niente file da scaricare).
 *
 * @package LU3G_Chiusure
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Parte pubblica.
 */
final class LU3G_Chiusure_Frontend {

	/** @var bool Script accodato in questa pagina. */
	private static $enqueued = false;

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'wp_footer', array( __CLASS__, 'print_data' ), 5 ); // Prima degli script del footer (priorità 20).
		add_filter( 'script_loader_tag', array( __CLASS__, 'script_tag' ), 10, 2 );
	}

	/**
	 * Accoda CSS e JS solo se servono in questa pagina.
	 */
	public static function enqueue() {
		if ( is_feed() || is_embed() || ! self::page_allowed( LU3G_Chiusure_Plugin::settings() ) ) {
			return;
		}
		// Almeno una chiusura attiva in preavviso o in corso nei prossimi 12 mesi.
		if ( ! LU3G_Chiusure_Closures::upcoming( LU3G_Chiusure_Closures::today(), 365, true ) ) {
			return;
		}
		$debug = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG;
		wp_enqueue_style( 'lu3g-chiusure', LU3G_CHIUSURE_URL . 'assets/css/popup.css', array(), LU3G_CHIUSURE_VERSION );
		wp_enqueue_script(
			'lu3g-chiusure',
			LU3G_CHIUSURE_URL . 'assets/js/' . ( $debug ? 'popup.js' : 'popup.min.js' ),
			array(),
			LU3G_CHIUSURE_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
		self::$enqueued = true;
	}

	/**
	 * Esclude lo script dai "ritarda JS fino all'interazione" dei plugin di cache: il popup deve apparire da solo
	 * dopo il ritardo scelto. nowprocket = WP Rocket, data-no-optimize/data-no-defer = LiteSpeed, data-cfasync = Cloudflare.
	 *
	 * @param string $tag    Tag <script>.
	 * @param string $handle Handle.
	 */
	public static function script_tag( $tag, $handle ) {
		if ( 'lu3g-chiusure' === $handle && false === strpos( $tag, 'nowprocket' ) ) {
			$tag = str_replace( '<script ', '<script nowprocket data-no-optimize="1" data-no-defer="1" data-cfasync="false" ', $tag );
		}
		return $tag;
	}

	/**
	 * Pagina ammessa dalle impostazioni (tutto il sito, solo home, ID inclusi/esclusi). Non dipende dalla data.
	 *
	 * @param array $s Impostazioni.
	 */
	public static function page_allowed( array $s ): bool {
		$allowed = true;
		if ( 'home' === $s['pages'] ) {
			$allowed = is_front_page();
		} elseif ( 'include' === $s['pages'] || 'exclude' === $s['pages'] ) {
			$ids = LU3G_Chiusure_Plugin::parse_ids( (string) $s['page_ids'] );
			// Solo pagine/articoli singoli e pagina degli articoli: negli archivi l'ID interrogato è quello di un termine.
			$id = ( is_singular() || is_home() || is_front_page() ) ? (int) get_queried_object_id() : 0;
			// Con WPML conta anche l'ID della pagina nella lingua predefinita.
			$orig = $id ? (int) apply_filters( 'wpml_object_id', $id, get_post_type( $id ) ?: 'page', true, apply_filters( 'wpml_default_language', null ) ) : 0;
			$hit  = $id && ( in_array( $id, $ids, true ) || in_array( $orig, $ids, true ) );
			$allowed = 'include' === $s['pages'] ? $hit : ! $hit;
		}
		return (bool) apply_filters( 'lu3g_chiusure_show_on_page', $allowed, $s );
	}

	/**
	 * Dati per popup.js (anche per l'anteprima del pannello).
	 *
	 * @param int|null $today Giorno di riferimento (predefinito oggi).
	 */
	public static function data( ?int $today = null ): array {
		$today = $today ?? LU3G_Chiusure_Closures::today();
		$s     = LU3G_Chiusure_Plugin::settings();
		$tz    = wp_timezone_string();

		$closures = array();
		$icons    = array();
		foreach ( LU3G_Chiusure_Closures::all() as $c ) {
			if ( empty( $c['enabled'] ) ) {
				continue;
			}
			$occ = array();
			foreach ( LU3G_Chiusure_Closures::occurrences( $c, $today ) as $o ) {
				if ( $o['e'] < $today - 1 ) {
					continue; // Già passato (un giorno di margine per i fusi orari).
				}
				$occ[] = array(
					's'  => $o['s'],
					'e'  => $o['e'],
					'fs' => LU3G_Chiusure_Closures::format( $o['s'] ),
					'fe' => LU3G_Chiusure_Closures::format( $o['e'] ),
					'fr' => LU3G_Chiusure_Closures::format( $o['r'] ),
				);
			}
			if ( ! $occ ) {
				continue;
			}
			$t  = LU3G_Chiusure_Closures::texts( $c, $occ[0]['s'] === $occ[0]['e'] );
			$ic = LU3G_Chiusure_Closures::resolve_icon( $c, ! empty( $s['show_icons'] ) );
			if ( '' !== $ic['key'] ) {
				$icons[ $ic['key'] ] = LU3G_Chiusure_Closures::icon( $ic['key'] ); // Solo le icone usate.
			}
			$closures[] = array(
				'id'     => $c['id'],
				'type'   => $c['type'],
				'icon'   => $ic['key'],   // Chiave in "icons" ('' = nessuna icona SVG).
				'img'    => $ic['img'],   // Immagine della libreria media ('' = nessuna): decorativa, alt vuoto.
				'notice' => (int) $c['notice_days'],
				'name'   => $t['name'],
				'nt'     => $t['notice_title'],
				'nm'     => $t['notice_message'],
				'at'     => $t['active_title'],
				'am'     => $t['active_message'],
				'occ'    => $occ,
			);
		}

		$data = array(
			'v'        => 1,
			// Fuso del sito: nome IANA (gestisce l'ora legale anche con la pagina in cache) e scarto in minuti di riserva.
			'tz'       => ( '' !== $tz && ! in_array( $tz[0], array( '+', '-' ), true ) ) ? $tz : '',
			'offset'   => (int) ( wp_timezone()->getOffset( new DateTime( 'now' ) ) / 60 ),
			'settings' => self::public_settings( $s ),
			'i18n'     => array(
				'close' => __( 'Chiudi', 'lu3g-chiusure' ),
			),
			'icons'    => $icons,
			'closures' => $closures,
		);
		return (array) apply_filters( 'lu3g_chiusure_popup_data', $data );
	}

	/**
	 * Solo le impostazioni che servono al browser, con i testi tradotti.
	 *
	 * @param array $s Impostazioni.
	 */
	public static function public_settings( array $s ): array {
		$close   = LU3G_Chiusure_Plugin::translate( (string) $s['close_text'], 'close_text' );
		$primary = LU3G_Chiusure_Plugin::primary_color( $s ); // Colore del sito in modalità automatica.
		return array(
			'layout'    => $s['layout'],
			'primary'   => $primary,
			'onPrimary' => self::contrast( $primary ),
			// Icona: colore scelto, oppure (automatico) il principale se si legge sullo sfondo (contrasto ≥ 3:1), altrimenti
			// il colore del testo. Il riquadro dietro l'icona è una tinta leggera del colore scelto o del principale.
			'accent'    => 'custom' === $s['icon_mode'] ? (string) $s['icon_color'] : ( self::ratio( $primary, (string) $s['bg'] ) >= 3 ? $primary : (string) $s['text'] ),
			'tint'      => 'custom' === $s['icon_mode'] ? (string) $s['icon_color'] : $primary,
			'iconBg'    => $s['icon_bg'],
			'icons'     => ! empty( $s['show_icons'] ),
			'text'      => $s['text'],
			'bg'        => $s['bg'],
			'radius'    => (int) $s['radius'],
			'close'     => '' !== $close ? $close : __( 'Ho capito', 'lu3g-chiusure' ),
			'ctaText'   => LU3G_Chiusure_Plugin::translate( (string) $s['cta_text'], 'cta_text' ),
			'ctaUrl'    => esc_url( LU3G_Chiusure_Plugin::translate( (string) $s['cta_url'], 'cta_url' ), array( 'http', 'https', 'mailto', 'tel' ) ),
			'frequency' => $s['frequency'],
			'delay'     => (int) $s['delay'],
			'devices'   => $s['devices'],
		);
	}

	/**
	 * Luminanza relativa (WCAG) di un colore #rrggbb.
	 *
	 * @param string $hex Colore.
	 */
	public static function luminance( string $hex ): float {
		$hex = LU3G_Chiusure_Plugin::hex( $hex );
		if ( '' === $hex ) {
			return 1.0;
		}
		$lin = function ( $c ) {
			$c = hexdec( $c ) / 255;
			return $c <= 0.03928 ? $c / 12.92 : ( ( $c + 0.055 ) / 1.055 ) ** 2.4;
		};
		return 0.2126 * $lin( substr( $hex, 1, 2 ) ) + 0.7152 * $lin( substr( $hex, 3, 2 ) ) + 0.0722 * $lin( substr( $hex, 5, 2 ) );
	}

	/**
	 * Rapporto di contrasto WCAG tra due colori (da 1 a 21).
	 *
	 * @param string $a Colore.
	 * @param string $b Colore.
	 */
	public static function ratio( string $a, string $b ): float {
		$x = self::luminance( $a );
		$y = self::luminance( $b );
		return ( max( $x, $y ) + 0.05 ) / ( min( $x, $y ) + 0.05 );
	}

	/**
	 * Colore leggibile sopra il colore principale (testo del pulsante CTA): nero o bianco.
	 *
	 * @param string $hex Colore #rrggbb.
	 */
	public static function contrast( string $hex ): string {
		$hex = ltrim( $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( 6 !== strlen( $hex ) ) {
			return '#ffffff';
		}
		$lin = function ( $c ) {
			$c = hexdec( $c ) / 255;
			return $c <= 0.03928 ? $c / 12.92 : ( ( $c + 0.055 ) / 1.055 ) ** 2.4;
		};
		$l = 0.2126 * $lin( substr( $hex, 0, 2 ) ) + 0.7152 * $lin( substr( $hex, 2, 2 ) ) + 0.0722 * $lin( substr( $hex, 4, 2 ) );
		return $l > 0.179 ? '#111111' : '#ffffff';
	}

	/**
	 * Stampa il JSON nel footer (solo se lo script è stato accodato).
	 */
	public static function print_data() {
		if ( ! self::$enqueued ) {
			return;
		}
		$data = self::data();
		/**
		 * Prima di stampare i dati del popup (es. per aggiungere altro output o registrare statistiche).
		 *
		 * @param array $data Dati che verranno stampati.
		 */
		do_action( 'lu3g_chiusure_before_popup_data', $data );
		// JSON_HEX_TAG: "<" e ">" diventano < >, quindi il JSON non può chiudere il tag <script>.
		echo "\n" . '<script type="application/json" id="lu3g-chiusure-data">' . wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON codificato con JSON_HEX_TAG
	}
}

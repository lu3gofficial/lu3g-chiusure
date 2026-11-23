<?php
/**
 * Avvio del plugin: caricamento automatico delle classi, impostazioni, traduzioni (WPML) e hook.
 *
 * @package LU3G_Chiusure
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe principale (singleton).
 */
final class LU3G_Chiusure_Plugin {

	/** Opzione con le impostazioni di aspetto e comportamento. */
	const SETTINGS = 'lu3g_chiusure_settings';

	/** Contesto delle stringhe registrate in WPML (Traduzione stringhe). */
	const WPML_CONTEXT = 'LU3G Chiusure';

	/** @var LU3G_Chiusure_Plugin|null */
	private static $instance = null;

	/**
	 * Istanza unica.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
		add_action( 'plugins_loaded', array( $this, 'boot' ) );
	}

	/**
	 * Caricamento automatico: LU3G_Chiusure_Nome_Classe → includes/class-nome-classe.php.
	 *
	 * @param string $class Nome della classe richiesta.
	 */
	public static function autoload( $class ) {
		if ( 0 !== strpos( $class, 'LU3G_Chiusure_' ) ) {
			return;
		}
		$file = LU3G_CHIUSURE_PATH . 'includes/class-' . strtolower( str_replace( '_', '-', substr( $class, 14 ) ) ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}

	/**
	 * Aggancia le parti del plugin. Il pannello solo in amministrazione, popup e dati strutturati solo nel sito.
	 */
	public function boot() {
		load_plugin_textdomain( 'lu3g-chiusure', false, dirname( plugin_basename( LU3G_CHIUSURE_FILE ) ) . '/languages' );

		if ( is_admin() ) {
			LU3G_Chiusure_Admin::init();
		} else {
			LU3G_Chiusure_Frontend::init();
			LU3G_Chiusure_Schema::init();
		}

		/*
		 * Predisposizione per un futuro avviso nel carrello/checkout di WooCommerce (NON implementato).
		 * Il carrello e il checkout non vanno mai in cache, quindi lì la fase si può calcolare in PHP con
		 * LU3G_Chiusure_Closures::current(). Un'estensione potrà agganciarsi così:
		 *
		 *   add_filter( 'lu3g_chiusure_woocommerce_notice', '__return_true' );
		 *   add_action( 'lu3g_chiusure_woocommerce_init', function () {
		 *       add_action( 'woocommerce_before_cart', ... ); // mostra LU3G_Chiusure_Closures::current()
		 *   } );
		 */
		if ( class_exists( 'WooCommerce' ) && apply_filters( 'lu3g_chiusure_woocommerce_notice', false ) ) {
			do_action( 'lu3g_chiusure_woocommerce_init', $this );
		}
	}

	/* ================================================================ impostazioni */

	/**
	 * Valori predefiniti delle impostazioni.
	 */
	public static function default_settings(): array {
		return array(
			'layout'         => 'modal',   // modal | top | bottom | corner.
			'primary_mode'   => 'auto',    // auto = colore principale del sito (Elementor, tema) | custom = quello scelto.
			'primary'        => '#0f766e',
			'show_icons'     => true,
			'text'           => '#18181b',
			'bg'             => '#ffffff',
			'radius'         => 18,
			'close_text'     => '',        // Vuoto = "Ho capito" (traducibile).
			'cta_text'       => '',
			'cta_url'        => '',
			'frequency'      => 'session', // session | day | always.
			'delay'          => 1,
			'devices'        => 'all',     // all | desktop | mobile.
			'pages'          => 'all',     // all | home | include | exclude.
			'page_ids'       => '',
			'schema_enabled' => false,
			'schema_name'    => '',
			'schema_type'    => 'LocalBusiness',
		);
	}

	/**
	 * Impostazioni salvate unite ai predefiniti, filtrabili con lu3g_chiusure_settings.
	 */
	public static function settings(): array {
		$saved = get_option( self::SETTINGS, array() );
		$saved = is_array( $saved ) ? $saved : array();
		// Impostazioni salvate con la 1.0.0 (senza primary_mode): il colore scelto allora resta quello.
		if ( $saved && ! isset( $saved['primary_mode'] ) ) {
			$saved['primary_mode'] = 'custom';
		}
		$s = array_merge( self::default_settings(), $saved );
		return (array) apply_filters( 'lu3g_chiusure_settings', $s );
	}

	/**
	 * Pulisce le impostazioni in arrivo dal modulo.
	 *
	 * @param array $in Dati non fidati (già senza slash).
	 */
	public static function sanitize_settings( array $in ): array {
		$d   = self::default_settings();
		$out = array();

		$out['layout']    = self::choice( $in['layout'] ?? '', array( 'modal', 'top', 'bottom', 'corner' ), $d['layout'] );
		// Interruttore "Usa il colore principale del sito": spuntato = auto, assente = colore scelto.
		$out['primary_mode'] = ! empty( $in['primary_mode'] ) && 'custom' !== $in['primary_mode'] ? 'auto' : 'custom';
		$out['primary']   = sanitize_hex_color( $in['primary'] ?? '' ) ?: $d['primary'];
		$out['show_icons'] = ! empty( $in['show_icons'] );
		$out['text']      = sanitize_hex_color( $in['text'] ?? '' ) ?: $d['text'];
		$out['bg']        = sanitize_hex_color( $in['bg'] ?? '' ) ?: $d['bg'];
		$out['radius']    = max( 0, min( 40, absint( $in['radius'] ?? $d['radius'] ) ) );
		$out['close_text'] = sanitize_text_field( $in['close_text'] ?? '' );
		$out['cta_text']  = sanitize_text_field( $in['cta_text'] ?? '' );
		$out['cta_url']   = esc_url_raw( trim( (string) ( $in['cta_url'] ?? '' ) ), array( 'http', 'https', 'mailto', 'tel' ) );
		$out['frequency'] = self::choice( $in['frequency'] ?? '', array( 'session', 'day', 'always' ), $d['frequency'] );
		$out['delay']     = max( 0, min( 120, (int) round( (float) ( $in['delay'] ?? 0 ) ) ) );
		$out['devices']   = self::choice( $in['devices'] ?? '', array( 'all', 'desktop', 'mobile' ), $d['devices'] );
		$out['pages']     = self::choice( $in['pages'] ?? '', array( 'all', 'home', 'include', 'exclude' ), $d['pages'] );
		$out['page_ids']  = implode( ', ', self::parse_ids( (string) ( $in['page_ids'] ?? '' ) ) );

		$out['schema_enabled'] = ! empty( $in['schema_enabled'] );
		$out['schema_name']    = sanitize_text_field( $in['schema_name'] ?? '' );
		// Tipo Schema.org: solo lettere (LocalBusiness, Restaurant, Store, ...).
		$type                = preg_replace( '/[^A-Za-z]/', '', (string) ( $in['schema_type'] ?? '' ) );
		$out['schema_type']  = '' !== $type ? $type : $d['schema_type'];

		return $out;
	}

	/**
	 * "12, 34 56" → array( 12, 34, 56 ).
	 *
	 * @param string $raw Elenco di ID separati da virgole o spazi.
	 */
	public static function parse_ids( string $raw ): array {
		$ids = array_filter( array_map( 'absint', preg_split( '/[\s,;]+/', $raw ) ) );
		return array_values( array_unique( $ids ) );
	}

	/**
	 * Valore ammesso o predefinito.
	 *
	 * @param mixed  $value   Valore ricevuto.
	 * @param array  $allowed Valori ammessi.
	 * @param string $default Predefinito.
	 */
	public static function choice( $value, array $allowed, string $default ): string {
		$value = sanitize_key( (string) $value );
		return in_array( $value, $allowed, true ) ? $value : $default;
	}

	/* ================================================================ colore del sito */

	/**
	 * Colore principale del sito, cercato in quest'ordine:
	 * 1. Elementor: colore "Primario" del kit attivo (Sito → Colori globali);
	 * 2. tema a blocchi / theme.json: colore della tavolozza con slug primary, accent, accent-1, brand;
	 * 3. personalizzazioni dei temi più usati (Astra, JupiterX, theme_mod primary_color/accent_color...).
	 * Il colore predefinito di Elementor (#6EC1E4, mai personalizzato) vale solo se non c'è altro.
	 * Filtrabile con lu3g_chiusure_site_color.
	 *
	 * @return array|null [ 'color' => '#rrggbb', 'source' => testo per il pannello ] o null.
	 */
	public static function site_color(): ?array {
		static $cache = false;
		if ( false !== $cache ) {
			return $cache;
		}
		$found = array();

		// 1. Elementor.
		if ( defined( 'ELEMENTOR_VERSION' ) ) {
			$kit  = (int) get_option( 'elementor_active_kit' );
			$meta = $kit ? get_post_meta( $kit, '_elementor_page_settings', true ) : array();
			foreach ( (array) ( $meta['system_colors'] ?? array() ) as $row ) {
				if ( 'primary' === ( $row['_id'] ?? '' ) && ( $hex = self::hex( (string) ( $row['color'] ?? '' ) ) ) ) {
					$found[] = array( 'color' => $hex, 'source' => __( 'Elementor, colore Primario del kit', 'lu3g-chiusure' ), 'weak' => '#6ec1e4' === $hex );
				}
			}
		}

		// 2. theme.json (temi a blocchi e classici con theme.json), anche la tavolozza personalizzata nell'editor del sito.
		if ( function_exists( 'wp_get_global_settings' ) ) {
			$palette = (array) wp_get_global_settings( array( 'color', 'palette' ) );
			foreach ( array( 'custom', 'theme' ) as $origin ) {
				$by_slug = array();
				foreach ( (array) ( $palette[ $origin ] ?? array() ) as $row ) {
					if ( isset( $row['slug'] ) && ( $hex = self::hex( (string) ( $row['color'] ?? '' ) ) ) ) {
						$by_slug[ $row['slug'] ] = $hex;
					}
				}
				foreach ( array( 'primary', 'accent', 'accent-1', 'brand' ) as $slug ) {
					if ( isset( $by_slug[ $slug ] ) ) {
						/* translators: %s: slug del colore */
						$found[] = array( 'color' => $by_slug[ $slug ], 'source' => sprintf( __( 'tavolozza del tema (%s)', 'lu3g-chiusure' ), $slug ), 'weak' => false );
						break 2;
					}
				}
			}
		}

		// 3. Personalizzazioni dei temi.
		$astra = get_option( 'astra-settings' );
		if ( is_array( $astra ) ) {
			$hex = self::hex( (string) ( $astra['global-color-palette']['palette'][0] ?? $astra['theme-color'] ?? '' ) );
			if ( $hex ) {
				$found[] = array( 'color' => $hex, 'source' => __( 'tema Astra, colore principale', 'lu3g-chiusure' ), 'weak' => false );
			}
		}
		foreach ( array( 'jupiterx_primary_color', 'primary_color', 'accent_color', 'theme_color', 'link_color' ) as $mod ) {
			$hex = self::hex( (string) get_theme_mod( $mod, '' ) );
			if ( $hex ) {
				/* translators: %s: nome dell'opzione del tema */
				$found[] = array( 'color' => $hex, 'source' => sprintf( __( 'personalizzazione del tema (%s)', 'lu3g-chiusure' ), $mod ), 'weak' => false );
				break;
			}
		}

		$pick = null;
		foreach ( $found as $f ) {
			if ( ! $f['weak'] ) {
				$pick = $f;
				break;
			}
		}
		$pick = $pick ?? ( $found[0] ?? null );
		if ( $pick ) {
			unset( $pick['weak'] );
		}
		$cache = apply_filters( 'lu3g_chiusure_site_color', $pick );
		return $cache;
	}

	/**
	 * "#abc", "#aabbcc" o "#aabbccdd" (trasparenza ignorata) → "#aabbcc"; altrimenti stringa vuota.
	 *
	 * @param string $v Colore.
	 */
	public static function hex( string $v ): string {
		$v = strtolower( trim( $v ) );
		if ( preg_match( '/^#([0-9a-f]{6})(?:[0-9a-f]{2})?$/', $v, $m ) ) {
			return '#' . $m[1];
		}
		if ( preg_match( '/^#([0-9a-f])([0-9a-f])([0-9a-f])$/', $v, $m ) ) {
			return '#' . $m[1] . $m[1] . $m[2] . $m[2] . $m[3] . $m[3];
		}
		return '';
	}

	/**
	 * Colore principale effettivo: quello del sito in modalità automatica (se trovato), altrimenti quello scelto.
	 *
	 * @param array $s Impostazioni.
	 */
	public static function primary_color( array $s ): string {
		if ( 'auto' === ( $s['primary_mode'] ?? 'custom' ) ) {
			$site = self::site_color();
			if ( $site ) {
				return $site['color'];
			}
		}
		return (string) $s['primary'];
	}

	/* ================================================================ WPML */

	/**
	 * Registra un testo inserito dal cliente in WPML → Traduzione stringhe (nessun effetto senza WPML).
	 *
	 * @param string $name  Nome univoco della stringa.
	 * @param string $value Testo nella lingua predefinita.
	 */
	public static function register_string( string $name, string $value ) {
		if ( '' !== $value ) {
			do_action( 'wpml_register_single_string', self::WPML_CONTEXT, $name, $value );
		}
	}

	/**
	 * Traduzione di un testo del cliente nella lingua corrente (senza WPML torna il testo originale).
	 *
	 * @param string $value Testo originale.
	 * @param string $name  Nome usato in register_string().
	 */
	public static function translate( string $value, string $name ): string {
		if ( '' === $value ) {
			return '';
		}
		return (string) apply_filters( 'wpml_translate_single_string', $value, self::WPML_CONTEXT, $name );
	}

	/* ================================================================ cache */

	/**
	 * Svuota le cache di pagina più diffuse dopo un salvataggio: le pagine in cache contengono i dati del popup.
	 * Disattivabile con add_filter( 'lu3g_chiusure_purge_cache', '__return_false' ).
	 */
	public static function purge_page_cache() {
		if ( ! apply_filters( 'lu3g_chiusure_purge_cache', true ) ) {
			return;
		}
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain(); // WP Rocket.
		}
		do_action( 'litespeed_purge_all' ); // LiteSpeed Cache.
		if ( function_exists( 'w3tc_flush_all' ) ) {
			w3tc_flush_all(); // W3 Total Cache.
		}
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache(); // WP Super Cache.
		}
		if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
			sg_cachepress_purge_cache(); // SiteGround Optimizer.
		}
		do_action( 'wpfc_clear_all_cache' ); // WP Fastest Cache.
		do_action( 'breeze_clear_all_cache' ); // Breeze.
	}
}

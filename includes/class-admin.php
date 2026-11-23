<?php
/**
 * Pannello "Chiusure": menu, due schede (Elenco chiusure, Aspetto e comportamento), salvataggio.
 *
 * Il salvataggio passa da admin-post.php con nonce e permesso manage_options; ogni campo è pulito in
 * LU3G_Chiusure_Closures::sanitize() e LU3G_Chiusure_Plugin::sanitize_settings(). Stile comune ai plugin LU3G.
 *
 * @package LU3G_Chiusure
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Amministrazione.
 */
final class LU3G_Chiusure_Admin {

	const PAGE = 'lu3g-chiusure';
	const CAP  = 'manage_options';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_lu3g_chiusure_save', array( __CLASS__, 'save' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( LU3G_CHIUSURE_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Indirizzo della pagina.
	 *
	 * @param array $args Parametri aggiuntivi.
	 */
	public static function url( array $args = array() ): string {
		return add_query_arg( array_merge( array( 'page' => self::PAGE ), $args ), admin_url( 'admin.php' ) );
	}

	public static function menu() {
		$hook = add_menu_page(
			__( 'Chiusure', 'lu3g-chiusure' ),
			__( 'Chiusure', 'lu3g-chiusure' ),
			self::CAP,
			self::PAGE,
			array( __CLASS__, 'render' ),
			'dashicons-calendar-alt',
			58
		);
		add_action( 'load-' . $hook, array( __CLASS__, 'load' ) );
	}

	/**
	 * Link "Impostazioni" nella pagina Plugin.
	 *
	 * @param array $links Link esistenti.
	 */
	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Impostazioni', 'lu3g-chiusure' ) . '</a>' );
		return $links;
	}

	public static function load() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter(
			'admin_body_class',
			function ( $c ) {
				return $c . ' lu3g-chiusure-page';
			}
		);
	}

	/**
	 * CSS e JS del pannello. popup.css/popup.js servono all'anteprima, identica al sito.
	 */
	public static function assets() {
		global $wp_locale;
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_media(); // Libreria media per le icone personalizzate.
		wp_enqueue_style( 'lu3g-chiusure-popup', LU3G_CHIUSURE_URL . 'assets/css/popup.css', array(), LU3G_CHIUSURE_VERSION );
		wp_enqueue_style( 'lu3g-chiusure-admin', LU3G_CHIUSURE_URL . 'assets/css/admin.css', array(), LU3G_CHIUSURE_VERSION );
		wp_add_inline_style( 'lu3g-chiusure-admin', '@font-face{font-family:"LU3G Manrope";src:url(' . esc_url( LU3G_CHIUSURE_URL . 'assets/fonts/manrope-latin-wght.woff2' ) . ') format("woff2");font-weight:200 800;font-display:swap}' );
		wp_enqueue_script( 'lu3g-chiusure-popup', LU3G_CHIUSURE_URL . 'assets/js/popup.js', array(), LU3G_CHIUSURE_VERSION, true );
		wp_enqueue_script( 'lu3g-chiusure-admin', LU3G_CHIUSURE_URL . 'assets/js/admin.js', array( 'wp-color-picker', 'lu3g-chiusure-popup' ), LU3G_CHIUSURE_VERSION, true );

		$types = array();
		foreach ( LU3G_Chiusure_Closures::types() as $k => $t ) {
			$t['icon']   = LU3G_Chiusure_Closures::icon( $k );
			$types[ $k ] = $t;
		}
		$notice = array();
		if ( isset( $_GET['saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- solo messaggio
			$notice[] = array( __( 'Impostazioni salvate.', 'lu3g-chiusure' ), false );
		}
		$bad = isset( $_GET['bad'] ) ? absint( $_GET['bad'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- solo messaggio
		if ( $bad ) {
			/* translators: %d: numero di chiusure */
			$notice[] = array( sprintf( _n( '%d chiusura senza date: non verrà mostrata.', '%d chiusure senza date: non verranno mostrate.', $bad, 'lu3g-chiusure' ), $bad ), true );
		}

		wp_localize_script(
			'lu3g-chiusure-admin',
			'LU3G_CH',
			array(
				'types'      => $types,
				'icons'      => array_map(
					function ( $k ) {
						return LU3G_Chiusure_Closures::icon( $k );
					},
					array_combine( array_keys( LU3G_Chiusure_Closures::icon_library() ), array_keys( LU3G_Chiusure_Closures::icon_library() ) )
				),
				'typeIcons'  => LU3G_Chiusure_Closures::TYPE_ICONS,
				'siteColor'  => LU3G_Chiusure_Plugin::site_color(),
				'settings'   => LU3G_Chiusure_Frontend::public_settings( LU3G_Chiusure_Plugin::settings() ),
				'preview'    => self::preview_items(),
				'closeLabel' => __( 'Chiudi', 'lu3g-chiusure' ),
				'closeText'  => __( 'Ho capito', 'lu3g-chiusure' ),
				'dateFormat' => (string) get_option( 'date_format', 'j F Y' ),
				'months'     => array_values( $wp_locale->month ),
				'monthsAbbr' => array_values( $wp_locale->month_abbrev ),
				'days'       => array_values( $wp_locale->weekday ),
				'daysAbbr'   => array_values( $wp_locale->weekday_abbrev ),
				'notice'     => $notice,
				'i18n'       => array(
					'confirmDelete' => __( 'Eliminare questa chiusura? Diventa definitivo quando salvi.', 'lu3g-chiusure' ),
					'noName'        => __( 'Nuova chiusura', 'lu3g-chiusure' ),
					'everyYear'     => __( 'ogni anno', 'lu3g-chiusure' ),
					'noDates'       => __( 'Inserisci le date di inizio e fine.', 'lu3g-chiusure' ),
					'unsaved'       => __( 'Modifiche non salvate', 'lu3g-chiusure' ),
					'pickImage'     => __( 'Scegli l\'immagine dell\'icona', 'lu3g-chiusure' ),
					'useImage'      => __( 'Usa questa immagine', 'lu3g-chiusure' ),
				),
			)
		);
	}

	/**
	 * Chiusure per l'anteprima della scheda Aspetto: il periodo in corso o il prossimo (anche se disattivate).
	 */
	private static function preview_items(): array {
		$today = LU3G_Chiusure_Closures::today();
		$out   = array();
		foreach ( LU3G_Chiusure_Closures::saved() as $c ) {
			$occ = LU3G_Chiusure_Closures::occurrences( $c, $today );
			if ( ! $occ ) {
				continue;
			}
			$o = end( $occ );
			foreach ( $occ as $x ) {
				if ( $x['e'] >= $today ) {
					$o = $x;
					break;
				}
			}
			$t     = LU3G_Chiusure_Closures::texts( $c, $o['s'] === $o['e'] );
			$ic    = LU3G_Chiusure_Closures::resolve_icon( $c );
			$out[] = array(
				'id'     => $c['id'],
				'type'   => $c['type'],
				'icon'   => $ic['key'],
				'img'    => $ic['img'],
				'notice' => (int) $c['notice_days'],
				'name'   => $t['name'],
				'nt'     => $t['notice_title'],
				'nm'     => $t['notice_message'],
				'at'     => $t['active_title'],
				'am'     => $t['active_message'],
				'occ'    => array(
					array(
						's'  => $o['s'],
						'e'  => $o['e'],
						'fs' => LU3G_Chiusure_Closures::format( $o['s'] ),
						'fe' => LU3G_Chiusure_Closures::format( $o['e'] ),
						'fr' => LU3G_Chiusure_Closures::format( $o['r'] ),
					),
				),
			);
		}
		return $out;
	}

	/* ================================================================ salvataggio */

	public static function save() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Non hai i permessi per modificare le chiusure.', 'lu3g-chiusure' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'lu3g_chiusure_save' );

		$tab  = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : '';
		$args = array( 'saved' => 1 );

		if ( 'closures' === $tab ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- ogni campo è pulito in sanitize()
			$rows = isset( $_POST['closures'] ) && is_array( $_POST['closures'] ) ? wp_unslash( $_POST['closures'] ) : array();
			$out  = array();
			$ids  = array();
			$bad  = 0;
			foreach ( $rows as $row ) { // L'ordine delle righe nel modulo è l'ordine salvato.
				if ( ! is_array( $row ) ) {
					continue;
				}
				$c = LU3G_Chiusure_Closures::sanitize( $row );
				if ( '' === $c['name'] && '' === $c['start'] && '' === $c['end'] && '' === $c['notice_message'] && '' === $c['active_message'] ) {
					continue; // Riga aggiunta e lasciata vuota.
				}
				if ( isset( $ids[ $c['id'] ] ) ) {
					$c['id'] = 'c' . strtolower( wp_generate_password( 8, false, false ) ); // ID duplicato (riga copiata).
				}
				$ids[ $c['id'] ] = true;
				if ( '' === $c['start'] || '' === $c['end'] ) {
					++$bad;
				}
				LU3G_Chiusure_Closures::register_strings( $c );
				$out[] = $c;
			}
			update_option( LU3G_Chiusure_Closures::OPTION, $out, true );
			if ( $bad ) {
				$args['bad'] = $bad;
			}
		} else {
			$tab = 'appearance';
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- pulito in sanitize_settings()
			$in = isset( $_POST['settings'] ) && is_array( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : array();
			$s  = LU3G_Chiusure_Plugin::sanitize_settings( $in );
			foreach ( array( 'close_text', 'cta_text', 'cta_url', 'schema_name' ) as $k ) {
				LU3G_Chiusure_Plugin::register_string( $k, (string) $s[ $k ] );
			}
			update_option( LU3G_Chiusure_Plugin::SETTINGS, $s, true );
		}

		LU3G_Chiusure_Plugin::purge_page_cache();
		$args['tab'] = $tab;
		wp_safe_redirect( self::url( $args ) );
		exit;
	}

	/* ================================================================ pagina */

	public static function render() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		$tab   = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'closures'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tabs  = array(
			'closures'   => __( 'Elenco chiusure', 'lu3g-chiusure' ),
			'appearance' => __( 'Aspetto e comportamento', 'lu3g-chiusure' ),
		);
		$tab   = isset( $tabs[ $tab ] ) ? $tab : 'closures';
		$list  = LU3G_Chiusure_Closures::saved();
		$today = LU3G_Chiusure_Closures::today();
		$cur   = LU3G_Chiusure_Closures::current( $today );
		$on    = count(
			array_filter(
				$list,
				function ( $c ) {
					return ! empty( $c['enabled'] );
				}
			)
		);
		if ( $cur ) {
			$now = 'active' === $cur['phase'] ? __( 'Chiuso', 'lu3g-chiusure' ) : __( 'Preavviso', 'lu3g-chiusure' );
		} else {
			$now = __( 'Aperto', 'lu3g-chiusure' );
		}
		$logo = (string) file_get_contents( LU3G_CHIUSURE_PATH . 'assets/lu3g-logo.svg' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		echo '<div class="wrap lu3g-chiusure"><h1 class="screen-reader-text">' . esc_html__( 'Chiusure', 'lu3g-chiusure' ) . '</h1>';
		echo '<header class="lch-hero"><div class="lch-hero__glow" aria-hidden="true"></div><div class="lch-hero__brand"><div class="lch-hero__logo">' . $logo . '</div><div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG del plugin
		echo '<p class="lch-hero__eyebrow">LU3G Chiusure</p><h2 class="lch-hero__title">' . esc_html__( 'Chiusure dell\'attività', 'lu3g-chiusure' ) . '</h2>';
		echo '<p class="lch-hero__lead">' . esc_html__( 'Ferie, feste, ponti e chiusure straordinarie: i visitatori le vedono in un popup leggero, con il preavviso che scegli. Funziona anche con le pagine in cache.', 'lu3g-chiusure' ) . '</p></div></div>';
		echo '<dl class="lch-stats"><div><dt>' . esc_html__( 'Chiusure', 'lu3g-chiusure' ) . '</dt><dd>' . count( $list ) . '</dd></div><div><dt>' . esc_html__( 'Attive', 'lu3g-chiusure' ) . '</dt><dd>' . (int) $on . '</dd></div><div><dt>' . esc_html__( 'Oggi', 'lu3g-chiusure' ) . '</dt><dd>' . esc_html( $now ) . '</dd></div></dl>';
		echo '</header>';

		echo '<nav class="lch-tabs" aria-label="' . esc_attr__( 'Sezioni', 'lu3g-chiusure' ) . '">';
		foreach ( $tabs as $k => $label ) {
			printf( '<a href="%1$s" class="lch-tab%2$s"%3$s>%4$s</a>', esc_url( self::url( array( 'tab' => $k ) ) ), $k === $tab ? ' is-active' : '', $k === $tab ? ' aria-current="page"' : '', esc_html( $label ) );
		}
		echo '</nav><div class="lch-body">';
		if ( 'appearance' === $tab ) {
			self::tab_appearance( LU3G_Chiusure_Plugin::settings() );
		} else {
			self::tab_closures( $list, $today );
		}
		echo '</div><div class="lch-toasts" data-toasts aria-live="polite"></div><p class="lch-foot">LU3G Chiusure ' . esc_html( LU3G_CHIUSURE_VERSION ) . '</p></div>';
	}

	/**
	 * Apertura del modulo con nonce.
	 *
	 * @param string $tab Scheda.
	 */
	private static function form_open( string $tab ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="lch-form" data-form="' . esc_attr( $tab ) . '" novalidate>';
		echo '<input type="hidden" name="action" value="lu3g_chiusure_save"><input type="hidden" name="tab" value="' . esc_attr( $tab ) . '">';
		wp_nonce_field( 'lu3g_chiusure_save' );
	}

	private static function savebar() {
		echo '<div class="lch-savebar"><span class="lch-dirty" data-dirty hidden>' . esc_html__( 'Modifiche non salvate', 'lu3g-chiusure' ) . '</span><button type="submit" class="lch-btn lch-btn--primary">' . esc_html__( 'Salva', 'lu3g-chiusure' ) . '</button></div>';
	}

	/**
	 * Interruttore.
	 *
	 * @param string $name  Nome del campo.
	 * @param string $label Etichetta.
	 * @param bool   $on    Attivo.
	 * @param string $help  Aiuto.
	 * @param string $attrs Attributi aggiuntivi (costanti).
	 */
	private static function toggle( string $name, string $label, bool $on, string $help = '', string $attrs = '' ) {
		printf(
			'<label class="lch-toggle"><input type="checkbox" name="%1$s" value="1" %2$s %5$s><span class="lch-toggle__ui" aria-hidden="true"></span><span><strong>%3$s</strong>%4$s</span></label>',
			esc_attr( $name ),
			checked( $on, true, false ),
			esc_html( $label ),
			$help ? '<small>' . esc_html( $help ) . '</small>' : '',
			$attrs // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- costanti
		);
	}

	/**
	 * Menu a tendina.
	 *
	 * @param string $name    Nome.
	 * @param string $label   Etichetta.
	 * @param array  $options Valore => testo.
	 * @param string $value   Selezionato.
	 * @param string $help    Aiuto.
	 * @param string $attrs   Attributi (costanti).
	 */
	private static function select( string $name, string $label, array $options, string $value, string $help = '', string $attrs = '' ) {
		echo '<label class="lch-field"><span>' . esc_html( $label ) . '</span><select name="' . esc_attr( $name ) . '" ' . $attrs . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- costanti
		foreach ( $options as $k => $text ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $value, $k, false ) . '>' . esc_html( $text ) . '</option>';
		}
		echo '</select>' . ( $help ? '<small>' . esc_html( $help ) . '</small>' : '' ) . '</label>';
	}

	/**
	 * Campo di testo.
	 *
	 * @param string $name  Nome.
	 * @param string $label Etichetta.
	 * @param string $value Valore.
	 * @param string $type  Tipo di input.
	 * @param string $help  Aiuto.
	 * @param string $attrs Attributi (costanti o già escapati).
	 */
	private static function input( string $name, string $label, string $value, string $type = 'text', string $help = '', string $attrs = '' ) {
		printf(
			'<label class="lch-field"><span>%1$s</span><input type="%2$s" class="lch-input" name="%3$s" value="%4$s" %6$s>%5$s</label>',
			esc_html( $label ),
			esc_attr( $type ),
			esc_attr( $name ),
			esc_attr( $value ),
			$help ? '<small>' . esc_html( $help ) . '</small>' : '',
			$attrs // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- costanti o escapati dal chiamante
		);
	}

	/* ---------------------------------------------------------------- scheda 1: elenco */

	private static function tab_closures( array $list, int $today ) {
		self::form_open( 'closures' );
		echo '<div class="lch-card"><div class="lch-card__head"><div><h3>' . esc_html__( 'Elenco chiusure', 'lu3g-chiusure' ) . '</h3>';
		echo '<p class="lch-help">' . esc_html__( 'Il popup mostra prima una chiusura in corso, altrimenti il preavviso della più vicina. Trascina le righe o usa le frecce per riordinarle.', 'lu3g-chiusure' ) . '</p></div>';
		echo '<button type="button" class="lch-btn lch-btn--primary" data-add><span aria-hidden="true">+</span> ' . esc_html__( 'Aggiungi chiusura', 'lu3g-chiusure' ) . '</button></div>';

		echo '<div class="lch-rows" data-rows>';
		foreach ( $list as $i => $c ) {
			self::row( 'r' . $i, $c, $today, false );
		}
		echo '</div>';
		echo '<p class="lch-empty" data-empty' . ( $list ? ' hidden' : '' ) . '>' . esc_html__( 'Nessuna chiusura. Aggiungi la prima: ferie estive, Natale, un ponte...', 'lu3g-chiusure' ) . '</p>';
		echo '<template data-template>';
		self::row( '__KEY__', LU3G_Chiusure_Closures::blank(), $today, true );
		echo '</template>';
		echo '</div>';

		echo '<div class="lch-card lch-card--soft"><h3>' . esc_html__( 'Segnaposto nei testi', 'lu3g-chiusure' ) . '</h3><p class="lch-help">';
		echo esc_html__( 'Nei titoli e nei messaggi puoi usare:', 'lu3g-chiusure' ) . ' <code>{inizio}</code> <code>{fine}</code> <code>{riapertura}</code> <code>{nome}</code>. ';
		/* translators: %s: link a Impostazioni → Generali */
		printf( esc_html__( 'Le date usano il formato di WordPress (%s). Lascia un campo vuoto per usare il testo predefinito della tipologia, mostrato in grigio.', 'lu3g-chiusure' ), '<a href="' . esc_url( admin_url( 'options-general.php' ) ) . '">' . esc_html__( 'Impostazioni → Generali', 'lu3g-chiusure' ) . '</a>' );
		echo '</p></div>';
		self::savebar();
		echo '</form>';
	}

	/**
	 * Una riga del ripetitore.
	 *
	 * @param string $key   Chiave della riga nel modulo.
	 * @param array  $c     Chiusura.
	 * @param int    $today Oggi.
	 * @param bool   $open  Aperta (riga nuova).
	 */
	private static function row( string $key, array $c, int $today, bool $open ) {
		$types = LU3G_Chiusure_Closures::types();
		$t     = $types[ $c['type'] ] ?? $types['summer'];
		$n     = 'closures[' . $key . ']';
		$st    = $open ? array( 'off', __( 'Nuova', 'lu3g-chiusure' ) ) : LU3G_Chiusure_Closures::status( $c, $today );
		$body  = 'lch-body-' . $key;

		echo '<div class="lch-row' . ( $open ? ' is-open' : '' ) . '" data-row>';
		echo '<div class="lch-row__head">';
		echo '<span class="lch-handle" data-handle title="' . esc_attr__( 'Trascina per riordinare', 'lu3g-chiusure' ) . '" aria-hidden="true"><svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><circle cx="9" cy="6" r="1.6"/><circle cx="15" cy="6" r="1.6"/><circle cx="9" cy="12" r="1.6"/><circle cx="15" cy="12" r="1.6"/><circle cx="9" cy="18" r="1.6"/><circle cx="15" cy="18" r="1.6"/></svg></span>';
		echo '<span class="lch-row__icon" data-icon>' . LU3G_Chiusure_Closures::icon( $c['type'] ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG del plugin (aggiornata da admin.js)
		echo '<button type="button" class="lch-row__title" data-expand aria-expanded="' . ( $open ? 'true' : 'false' ) . '" aria-controls="' . esc_attr( $body ) . '"><strong data-title>' . esc_html( '' !== $c['name'] ? $c['name'] : __( 'Nuova chiusura', 'lu3g-chiusure' ) ) . '</strong><small data-summary></small></button>';
		echo '<span class="lch-st lch-st--' . esc_attr( $st[0] ) . '" data-status>' . esc_html( $st[1] ) . '</span>';
		echo '<label class="lch-toggle lch-toggle--compact" title="' . esc_attr__( 'Attiva o disattiva', 'lu3g-chiusure' ) . '"><input type="checkbox" name="' . esc_attr( $n ) . '[enabled]" value="1" ' . checked( ! empty( $c['enabled'] ), true, false ) . ' data-f="enabled"><span class="lch-toggle__ui" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html__( 'Chiusura attiva', 'lu3g-chiusure' ) . '</span></label>';
		echo '<span class="lch-row__tools">';
		echo '<button type="button" class="lch-icobtn" data-up aria-label="' . esc_attr__( 'Sposta su', 'lu3g-chiusure' ) . '"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 15l6-6 6 6"/></svg></button>';
		echo '<button type="button" class="lch-icobtn" data-down aria-label="' . esc_attr__( 'Sposta giù', 'lu3g-chiusure' ) . '"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg></button>';
		echo '<button type="button" class="lch-icobtn lch-icobtn--danger" data-remove aria-label="' . esc_attr__( 'Elimina', 'lu3g-chiusure' ) . '"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/></svg></button>';
		echo '</span></div>';

		echo '<div class="lch-row__body" id="' . esc_attr( $body ) . '"' . ( $open ? '' : ' hidden' ) . '>';
		echo '<input type="hidden" name="' . esc_attr( $n ) . '[id]" value="' . esc_attr( $c['id'] ) . '">';
		echo '<div class="lch-grid lch-grid--3">';
		self::input( $n . '[name]', __( 'Nome interno', 'lu3g-chiusure' ), $c['name'], 'text', __( 'Es. "Ferie estive". Usato anche dal segnaposto {nome}.', 'lu3g-chiusure' ), 'data-f="name" placeholder="' . esc_attr__( 'Ferie estive', 'lu3g-chiusure' ) . '"' );
		$opts = array();
		foreach ( $types as $k => $type ) {
			$opts[ $k ] = $type['label'];
		}
		self::select( $n . '[type]', __( 'Tipologia', 'lu3g-chiusure' ), $opts, $c['type'], __( 'Sceglie icona e testi predefiniti.', 'lu3g-chiusure' ), 'data-f="type"' );
		self::input( $n . '[notice_days]', __( 'Giorni di preavviso', 'lu3g-chiusure' ), (string) $c['notice_days'], 'number', __( 'Quanti giorni prima mostrare l\'avviso. 0 = nessun preavviso.', 'lu3g-chiusure' ), 'data-f="notice_days" min="0" max="365" step="1" inputmode="numeric"' );
		self::input( $n . '[start]', __( 'Data inizio', 'lu3g-chiusure' ), $c['start'], 'date', '', 'data-f="start"' );
		self::input( $n . '[end]', __( 'Data fine', 'lu3g-chiusure' ), $c['end'], 'date', __( 'Ultimo giorno di chiusura.', 'lu3g-chiusure' ), 'data-f="end"' );
		self::input( $n . '[reopen]', __( 'Data di riapertura (facoltativa)', 'lu3g-chiusure' ), $c['reopen'], 'date', __( 'Vuota = il giorno dopo la fine.', 'lu3g-chiusure' ), 'data-f="reopen"' );
		echo '</div><div class="lch-recurring">';
		self::toggle( $n . '[recurring]', __( 'Ricorrenza annuale', 'lu3g-chiusure' ), ! empty( $c['recurring'] ), __( 'Vale ogni anno: contano solo giorno e mese, anche a cavallo d\'anno (es. 24/12 → 06/01). Con la tipologia Pasqua le date seguono la Pasqua di ogni anno.', 'lu3g-chiusure' ), 'data-f="recurring"' );
		echo '</div>';
		self::icon_picker( $n, $key, $c );

		echo '<div class="lch-phases">';
		foreach ( array(
			'notice' => __( 'Fase "preavviso"', 'lu3g-chiusure' ),
			'active' => __( 'Fase "in corso"', 'lu3g-chiusure' ),
		) as $ph => $label ) {
			echo '<fieldset class="lch-phase lch-phase--' . esc_attr( $ph ) . '"><legend>' . esc_html( $label ) . '</legend>';
			self::input( $n . '[' . $ph . '_title]', __( 'Titolo', 'lu3g-chiusure' ), $c[ $ph . '_title' ], 'text', '', 'data-f="' . $ph . '_title" placeholder="' . esc_attr( $t[ $ph . '_title' ] ) . '"' );
			printf(
				'<label class="lch-field"><span>%1$s</span><textarea class="lch-input" name="%2$s" rows="3" data-f="%3$s_message" placeholder="%4$s">%5$s</textarea></label>',
				esc_html__( 'Messaggio', 'lu3g-chiusure' ),
				esc_attr( $n . '[' . $ph . '_message]' ),
				esc_attr( $ph ),
				esc_attr( $t[ $ph . '_message' ] ),
				esc_textarea( $c[ $ph . '_message' ] )
			);
			echo '<button type="button" class="lch-btn lch-btn--small" data-preview="' . esc_attr( $ph ) . '"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>' . esc_html__( 'Anteprima', 'lu3g-chiusure' ) . '</button>';
			echo '</fieldset>';
		}
		echo '</div></div></div>';
	}

	/**
	 * Scelta dell'icona di una chiusura: della tipologia, nessuna, una della libreria o un'immagine.
	 *
	 * @param string $n   Prefisso del nome dei campi.
	 * @param string $key Chiave della riga.
	 * @param array  $c   Chiusura.
	 */
	private static function icon_picker( string $n, string $key, array $c ) {
		$cur   = (string) $c['icon'];
		$img   = ! empty( $c['icon_image'] ) ? (string) wp_get_attachment_image_url( (int) $c['icon_image'], 'thumbnail' ) : '';
		$tile  = function ( $value, $title, $ui, $extra = '' ) use ( $n, $cur ) {
			printf(
				'<label class="lch-ico%5$s" title="%2$s"><input type="radio" name="%1$s[icon]" value="%3$s" data-f="icon" %4$s><span class="lch-ico__ui">%6$s</span><span class="screen-reader-text">%2$s</span></label>',
				esc_attr( $n ),
				esc_attr( $title ),
				esc_attr( $value ),
				checked( $cur, $value, false ),
				$extra ? ' ' . esc_attr( $extra ) : '',
				$ui // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG del plugin o testo escapato
			);
		};
		echo '<div class="lch-icons" role="radiogroup" aria-labelledby="lch-iconlbl-' . esc_attr( $key ) . '"><div class="lch-icons__head"><strong id="lch-iconlbl-' . esc_attr( $key ) . '">' . esc_html__( 'Icona', 'lu3g-chiusure' ) . '</strong><small>' . esc_html__( 'Quella della tipologia, un\'altra della libreria, un\'immagine tua o nessuna.', 'lu3g-chiusure' ) . '</small></div><div class="lch-icongrid">';
		$tile( '', __( 'Della tipologia', 'lu3g-chiusure' ), '<span data-auto-icon>' . LU3G_Chiusure_Closures::icon( $c['type'] ) . '</span><em>' . esc_html__( 'Auto', 'lu3g-chiusure' ) . '</em>', 'lch-ico--wide' );
		$tile( 'none', __( 'Nessuna icona', 'lu3g-chiusure' ), '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="8.5"/><path d="M6 18L18 6"/></svg><em>' . esc_html__( 'Nessuna', 'lu3g-chiusure' ) . '</em>', 'lch-ico--wide' );
		foreach ( LU3G_Chiusure_Closures::icon_library() as $k => $icon ) {
			$tile( $k, $icon[0], LU3G_Chiusure_Closures::icon( $k ) );
		}
		$tile( 'image', __( 'Immagine dalla libreria media', 'lu3g-chiusure' ), '<img data-icon-thumb src="' . esc_url( $img ) . '" alt=""' . ( $img ? '' : ' hidden' ) . '><svg data-icon-noimg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"' . ( $img ? ' hidden' : '' ) . '><rect x="3" y="4" width="18" height="16" rx="2.5"/><circle cx="9" cy="10" r="1.8"/><path d="M21 16l-5-5-9 9"/></svg><em>' . esc_html__( 'Immagine', 'lu3g-chiusure' ) . '</em>', 'lch-ico--wide' );
		echo '</div>';
		echo '<div class="lch-iconimg" data-icon-image' . ( 'image' === $cur ? '' : ' hidden' ) . '><input type="hidden" name="' . esc_attr( $n ) . '[icon_image]" value="' . (int) $c['icon_image'] . '" data-f="icon_image">';
		echo '<button type="button" class="lch-btn lch-btn--small" data-pick-image>' . esc_html__( 'Scegli o carica l\'immagine', 'lu3g-chiusure' ) . '</button><small>' . esc_html__( 'Meglio quadrata (PNG o JPG): nel popup si vede di circa 40 × 40 px.', 'lu3g-chiusure' ) . '</small></div>';
		echo '</div>';
	}

	/* ---------------------------------------------------------------- scheda 2: aspetto */

	private static function tab_appearance( array $s ) {
		$n = 'settings';
		self::form_open( 'appearance' );
		echo '<div class="lch-split"><div class="lch-split__main">';

		// Layout.
		echo '<div class="lch-card"><h3>' . esc_html__( 'Layout', 'lu3g-chiusure' ) . '</h3><div class="lch-layouts">';
		$layouts = array(
			'modal'  => array( __( 'Modale centrato', 'lu3g-chiusure' ), __( 'Su mobile diventa un pannello dal basso.', 'lu3g-chiusure' ) ),
			'top'    => array( __( 'Barra in alto', 'lu3g-chiusure' ), __( 'Non copre la pagina.', 'lu3g-chiusure' ) ),
			'bottom' => array( __( 'Barra in basso', 'lu3g-chiusure' ), __( 'Non copre la pagina.', 'lu3g-chiusure' ) ),
			'corner' => array( __( 'Card nell\'angolo', 'lu3g-chiusure' ), __( 'In basso a destra, discreta.', 'lu3g-chiusure' ) ),
		);
		foreach ( $layouts as $k => $l ) {
			printf(
				'<label class="lch-layout"><input type="radio" name="%1$s[layout]" value="%2$s" %3$s data-s="layout"><span class="lch-layout__pic lch-layout__pic--%2$s" aria-hidden="true"><i></i></span><strong>%4$s</strong><small>%5$s</small></label>',
				esc_attr( $n ),
				esc_attr( $k ),
				checked( $s['layout'], $k, false ),
				esc_html( $l[0] ),
				esc_html( $l[1] )
			);
		}
		echo '</div></div>';

		// Colori e forma.
		echo '<div class="lch-card"><h3>' . esc_html__( 'Colori, forma e icone', 'lu3g-chiusure' ) . '</h3>';
		$site = LU3G_Chiusure_Plugin::site_color();
		if ( $site ) {
			/* translators: 1: colore esadecimale, 2: da dove viene */
			$found = sprintf( __( 'Colore trovato: %1$s, da %2$s.', 'lu3g-chiusure' ), $site['color'], $site['source'] );
		} else {
			$found = __( 'Non ho trovato un colore principale in Elementor o nel tema: finché non c\'è, il popup usa il colore scelto qui sotto.', 'lu3g-chiusure' );
		}
		echo '<div class="lch-auto">';
		self::toggle( $n . '[primary_mode]', __( 'Usa il colore principale del sito', 'lu3g-chiusure' ), 'auto' === $s['primary_mode'], __( 'Lo prende dai colori globali di Elementor o dal tema, e segue le loro modifiche. Disattivalo per scegliere un colore diverso.', 'lu3g-chiusure' ), 'data-primary-auto' );
		echo '<p class="lch-found">' . ( $site ? '<span class="lch-swatch" style="background:' . esc_attr( $site['color'] ) . '"></span>' : '' ) . esc_html( $found ) . '</p></div>';
		echo '<div class="lch-grid lch-grid--3">';
		foreach ( array(
			'primary' => __( 'Colore principale (personalizzato)', 'lu3g-chiusure' ),
			'text'    => __( 'Colore del testo', 'lu3g-chiusure' ),
			'bg'      => __( 'Colore di sfondo', 'lu3g-chiusure' ),
		) as $k => $label ) {
			// Etichetta fuori dal campo: wpColorPicker avvolge il genitore dell'input.
			$hide = 'primary' === $k && 'auto' === $s['primary_mode'] ? ' data-primary-custom hidden' : ( 'primary' === $k ? ' data-primary-custom' : '' );
			echo '<div class="lch-field"' . $hide . '><label for="lch-color-' . esc_attr( $k ) . '">' . esc_html( $label ) . '</label><div><input type="text" class="lch-color" id="lch-color-' . esc_attr( $k ) . '" name="' . esc_attr( $n . '[' . $k . ']' ) . '" value="' . esc_attr( $s[ $k ] ) . '" data-default-color="' . esc_attr( LU3G_Chiusure_Plugin::default_settings()[ $k ] ) . '" data-s="' . esc_attr( $k ) . '"></div></div>';
		}
		echo '</div><label class="lch-field lch-range"><span>' . esc_html__( 'Arrotondamento degli angoli', 'lu3g-chiusure' ) . ' <output data-radius-out>' . (int) $s['radius'] . ' px</output></span><input type="range" name="' . esc_attr( $n ) . '[radius]" min="0" max="40" step="1" value="' . (int) $s['radius'] . '" data-s="radius"></label>';
		echo '<div class="lch-auto">';
		self::toggle( $n . '[show_icons]', __( 'Mostra l\'icona nel popup', 'lu3g-chiusure' ), ! empty( $s['show_icons'] ), __( 'Spento: nessuna icona per nessuna chiusura. Acceso: ogni chiusura usa l\'icona scelta nell\'Elenco chiusure (della tipologia, un\'altra, un\'immagine o nessuna).', 'lu3g-chiusure' ), 'data-s="show_icons"' );
		echo '</div></div>';

		// Pulsanti.
		echo '<div class="lch-card"><h3>' . esc_html__( 'Pulsanti', 'lu3g-chiusure' ) . '</h3><div class="lch-grid lch-grid--3">';
		self::input( $n . '[close_text]', __( 'Testo del pulsante di chiusura', 'lu3g-chiusure' ), $s['close_text'], 'text', '', 'placeholder="' . esc_attr__( 'Ho capito', 'lu3g-chiusure' ) . '" data-s="close_text"' );
		self::input( $n . '[cta_text]', __( 'Pulsante CTA: testo (facoltativo)', 'lu3g-chiusure' ), $s['cta_text'], 'text', '', 'placeholder="' . esc_attr__( 'Contattaci', 'lu3g-chiusure' ) . '" data-s="cta_text"' );
		self::input( $n . '[cta_url]', __( 'Pulsante CTA: link', 'lu3g-chiusure' ), $s['cta_url'], 'text', __( 'Pagina, mailto: o tel:. Il pulsante appare solo con testo e link.', 'lu3g-chiusure' ), 'placeholder="https://" inputmode="url" spellcheck="false" data-s="cta_url"' );
		echo '</div></div>';

		// Comportamento.
		echo '<div class="lch-card"><h3>' . esc_html__( 'Comportamento', 'lu3g-chiusure' ) . '</h3><div class="lch-grid lch-grid--3">';
		self::select(
			$n . '[frequency]',
			__( 'Frequenza', 'lu3g-chiusure' ),
			array(
				'session' => __( 'Una volta per sessione', 'lu3g-chiusure' ),
				'day'     => __( 'Una volta al giorno', 'lu3g-chiusure' ),
				'always'  => __( 'A ogni pagina', 'lu3g-chiusure' ),
			),
			$s['frequency'],
			__( 'Preavviso e chiusura in corso contano separatamente.', 'lu3g-chiusure' )
		);
		self::input( $n . '[delay]', __( 'Ritardo di apparizione (secondi)', 'lu3g-chiusure' ), (string) $s['delay'], 'number', '', 'min="0" max="120" step="1" inputmode="numeric"' );
		self::select(
			$n . '[devices]',
			__( 'Dispositivi', 'lu3g-chiusure' ),
			array(
				'all'     => __( 'Tutti', 'lu3g-chiusure' ),
				'desktop' => __( 'Solo desktop', 'lu3g-chiusure' ),
				'mobile'  => __( 'Solo mobile', 'lu3g-chiusure' ),
			),
			$s['devices'],
			__( 'Mobile = schermo fino a 767 px.', 'lu3g-chiusure' )
		);
		self::select(
			$n . '[pages]',
			__( 'Pagine', 'lu3g-chiusure' ),
			array(
				'all'     => __( 'Tutto il sito', 'lu3g-chiusure' ),
				'home'    => __( 'Solo la homepage', 'lu3g-chiusure' ),
				'include' => __( 'Solo le pagine indicate', 'lu3g-chiusure' ),
				'exclude' => __( 'Tutte tranne le pagine indicate', 'lu3g-chiusure' ),
			),
			$s['pages'],
			'',
			'data-pages'
		);
		echo '<div class="lch-field--wide" data-page-ids' . ( in_array( $s['pages'], array( 'include', 'exclude' ), true ) ? '' : ' hidden' ) . '>';
		self::input( $n . '[page_ids]', __( 'ID delle pagine', 'lu3g-chiusure' ), $s['page_ids'], 'text', __( 'Separati da virgola, es. 12, 345. L\'ID si legge nell\'indirizzo di modifica della pagina (post=12).', 'lu3g-chiusure' ), 'spellcheck="false" placeholder="12, 345"' );
		echo '</div></div></div>';

		// Dati strutturati.
		echo '<div class="lch-card"><h3>' . esc_html__( 'Dati strutturati per Google', 'lu3g-chiusure' ) . '</h3>';
		echo '<p class="lch-help">' . esc_html__( 'Aggiunge alla homepage un JSON-LD con gli orari speciali (specialOpeningHoursSpecification) per le chiusure dei prossimi 12 mesi. Se un plugin SEO pubblica già la scheda dell\'attività, controlla che i dati non si contraddicano.', 'lu3g-chiusure' ) . '</p>';
		self::toggle( $n . '[schema_enabled]', __( 'Pubblica le chiusure nei dati strutturati', 'lu3g-chiusure' ), ! empty( $s['schema_enabled'] ) );
		echo '<div class="lch-grid" style="margin-top:14px">';
		self::input( $n . '[schema_name]', __( 'Nome dell\'attività', 'lu3g-chiusure' ), $s['schema_name'], 'text', '', 'placeholder="' . esc_attr( get_bloginfo( 'name' ) ) . '"' );
		self::input( $n . '[schema_type]', __( 'Tipo Schema.org', 'lu3g-chiusure' ), $s['schema_type'], 'text', __( 'LocalBusiness va bene per tutti; più preciso: Restaurant, Store, MedicalBusiness...', 'lu3g-chiusure' ), 'list="lch-schema-types" spellcheck="false"' );
		echo '<datalist id="lch-schema-types">';
		foreach ( array( 'LocalBusiness', 'Store', 'Restaurant', 'CafeOrCoffeeShop', 'BarOrPub', 'Bakery', 'LodgingBusiness', 'Hotel', 'MedicalBusiness', 'Dentist', 'Pharmacy', 'ProfessionalService', 'LegalService', 'AccountingService', 'AutoRepair', 'BeautySalon', 'HairSalon', 'HealthAndBeautyBusiness', 'SportsActivityLocation', 'HomeAndConstructionBusiness', 'TravelAgency', 'RealEstateAgent' ) as $type ) {
			echo '<option value="' . esc_attr( $type ) . '">';
		}
		echo '</datalist></div></div>';

		echo '</div>';

		// Anteprima live.
		echo '<aside class="lch-split__side"><div class="lch-card lch-preview"><div class="lch-card__head"><h3>' . esc_html__( 'Anteprima', 'lu3g-chiusure' ) . '</h3>';
		echo '<div class="lch-seg" role="group" aria-label="' . esc_attr__( 'Fase', 'lu3g-chiusure' ) . '"><button type="button" class="is-active" data-phase="notice" aria-pressed="true">' . esc_html__( 'Preavviso', 'lu3g-chiusure' ) . '</button><button type="button" data-phase="active" aria-pressed="false">' . esc_html__( 'In corso', 'lu3g-chiusure' ) . '</button></div></div>';
		echo '<label class="lch-field"><span class="screen-reader-text">' . esc_html__( 'Chiusura da mostrare', 'lu3g-chiusure' ) . '</span><select data-preview-pick></select></label>';
		echo '<div class="lch-screen" data-screen aria-hidden="true"><div class="lch-screen__bar"><i></i><i></i><i></i></div><div class="lch-screen__page"><b></b><span></span><span></span><span></span><em></em><em></em></div></div>';
		echo '<button type="button" class="lch-btn" data-preview-real>' . esc_html__( 'Prova a tutto schermo', 'lu3g-chiusure' ) . '</button>';
		echo '<p class="lch-help">' . esc_html__( 'L\'anteprima si aggiorna mentre modifichi. "Prova a tutto schermo" apre il popup vero in questa pagina: provalo anche con Tab ed Esc.', 'lu3g-chiusure' ) . '</p>';
		echo '</div></aside></div>';

		self::savebar();
		echo '</form>';
	}
}

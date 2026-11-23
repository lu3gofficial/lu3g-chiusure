/**
 * LU3G Chiusure — popup nel sito. JavaScript vanilla (ES6), nessuna dipendenza, nessun jQuery.
 *
 * Funziona anche con la pagina in cache: PHP stampa TUTTE le chiusure in <script id="lu3g-chiusure-data">,
 * con i periodi già calcolati (anche le ricorrenze a cavallo d'anno) come giorni interi e le date già formattate.
 * Qui si confronta il giorno di oggi (nel fuso del sito) con quei periodi e si decide se e cosa mostrare.
 *
 * Il file servito è popup.min.js (generato con bin/minify.sh); con SCRIPT_DEBUG si carica questo.
 */
( () => {
	'use strict';

	const doc = document;
	const W = window;
	let last = null; // Elemento che aveva il focus prima dell'apertura: lo riprende alla chiusura.

	/**
	 * Oggi come giorno intero (giorni dal 1/1/1970) nel fuso orario del sito.
	 * Con il nome del fuso (es. Europe/Rome) segue anche l'ora legale; altrimenti usa lo scarto in minuti.
	 */
	const today = ( D ) => {
		const now = new Date();
		if ( D.tz ) {
			try {
				const p = {};
				new Intl.DateTimeFormat( 'en-US', { timeZone: D.tz, year: 'numeric', month: 'numeric', day: 'numeric' } )
					.formatToParts( now )
					.forEach( ( x ) => {
						p[ x.type ] = +x.value;
					} );
				return Date.UTC( p.year, p.month - 1, p.day ) / 864e5;
			} catch ( e ) {
				// Fuso non riconosciuto dal browser: si usa lo scarto.
			}
		}
		return Math.floor( ( now.getTime() + D.offset * 6e4 ) / 864e5 );
	};

	/**
	 * Chiusura da mostrare oggi: prima una in corso, altrimenti il preavviso della più vicina.
	 * Restituisce { c: chiusura, o: periodo, ph: 'active' | 'notice' } oppure null.
	 */
	const pick = ( D, t ) => {
		let best = null;
		D.closures.forEach( ( c ) =>
			c.occ.forEach( ( o ) => {
				let ph = '';
				if ( t >= o.s && t <= o.e ) {
					ph = 'active';
				} else if ( c.notice > 0 && t >= o.s - c.notice && t < o.s ) {
					ph = 'notice';
				}
				const sc = 'active' === ph ? o.s - 1e6 : o.s;
				if ( ph && ( ! best || sc < best.sc ) ) {
					best = { c, o, ph, sc };
				}
			} )
		);
		return best;
	};

	/** sessionStorage (una volta per sessione), localStorage (una volta al giorno) o niente (sempre). */
	const store = ( f ) => {
		try {
			if ( 'session' === f ) {
				return W.sessionStorage;
			}
			return 'day' === f ? W.localStorage : null;
		} catch ( e ) {
			return null; // Archiviazione bloccata (navigazione privata, cookie disattivati): il popup si mostra.
		}
	};

	/**
	 * Crea e apre il popup. Con host (anteprima del pannello) lo mette dentro host, senza focus né tastiera.
	 */
	const render = ( D, p, host ) => {
		const S = D.settings;
		const modal = 'modal' === S.layout;
		const c = p.c;
		const o = p.o;
		const notice = 'notice' === p.ph;
		const vals = { inizio: o.fs, fine: o.fe, riapertura: o.fr, nome: c.name };
		const fill = ( s ) => String( s || '' ).replace( /\{(inizio|fine|riapertura|nome)\}/g, ( m, k ) => vals[ k ] );

		const root = doc.createElement( 'div' );
		// Icona: immagine della libreria media, SVG della libreria del plugin, oppure nessuna.
		const svg = D.icons[ c.icon ] || '';
		const hasIcon = false !== S.icons && ( c.img || svg );
		root.className = 'lu3g-ch lu3g-ch--' + S.layout + ' lu3g-ch--' + c.type + ( hasIcon ? '' : ' lu3g-ch--noicon' ) + ( host ? ' lu3g-ch--preview' : '' );
		// Variabili CSS dalle impostazioni.
		root.style.cssText = '--lu3g-primary:' + S.primary + ';--lu3g-on-primary:' + S.onPrimary + ';--lu3g-accent:' + ( S.accent || S.primary ) + ';--lu3g-text:' + S.text +
			';--lu3g-bg:' + S.bg + ';--lu3g-radius:' + S.radius + 'px';
		root.innerHTML = ( modal ? '<div class="lu3g-ch-overlay"></div>' : '' ) +
			'<div class="lu3g-ch-box" role="dialog" aria-modal="' + modal + '" aria-labelledby="lu3g-ch-t" aria-describedby="lu3g-ch-m" tabindex="-1">' +
			'<button type="button" class="lu3g-ch-x"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6 6 18"/></svg></button>' +
			( hasIcon ? '<span class="lu3g-ch-icon" aria-hidden="true">' + ( c.img ? '' : svg ) + '</span>' : '' ) +
			'<div class="lu3g-ch-body"><p class="lu3g-ch-title" id="lu3g-ch-t"></p><p class="lu3g-ch-msg" id="lu3g-ch-m"></p><div class="lu3g-ch-actions"></div></div></div>';

		const q = ( s ) => root.querySelector( s );
		const box = q( '.lu3g-ch-box' );
		const x = q( '.lu3g-ch-x' );
		// Testi sempre con textContent: niente HTML dai campi del pannello.
		q( '.lu3g-ch-title' ).textContent = fill( notice ? c.nt : c.at );
		q( '.lu3g-ch-msg' ).textContent = fill( notice ? c.nm : c.am );
		x.setAttribute( 'aria-label', D.i18n.close );
		if ( hasIcon && c.img ) {
			const im = doc.createElement( 'img' );
			im.src = c.img;
			im.alt = '';
			q( '.lu3g-ch-icon' ).appendChild( im );
		}
		const acts = q( '.lu3g-ch-actions' );
		if ( S.ctaText && S.ctaUrl ) {
			const a = doc.createElement( 'a' );
			a.className = 'lu3g-ch-btn lu3g-ch-btn--cta';
			a.href = S.ctaUrl;
			a.textContent = S.ctaText;
			acts.appendChild( a );
		}
		const b = doc.createElement( 'button' );
		b.type = 'button';
		b.className = 'lu3g-ch-btn lu3g-ch-close';
		b.textContent = S.close;
		acts.appendChild( b );

		const parent = host || doc.body;
		if ( 'top' === S.layout && ! host ) {
			parent.insertBefore( root, parent.firstChild ); // Barra in alto: prima nell'ordine di tabulazione.
		} else {
			parent.appendChild( root );
		}
		void root.offsetWidth; // Applica lo stato iniziale prima della transizione di entrata.
		root.classList.add( 'is-open' );
		if ( host ) {
			return root;
		}

		const key = ( e ) => {
			const inside = box.contains( doc.activeElement );
			if ( 'Escape' === e.key && ( modal || inside || doc.activeElement === doc.body ) ) {
				e.preventDefault();
				close();
			} else if ( 'Tab' === e.key && modal ) {
				// Trappola del focus: il Tab gira solo tra i pulsanti del popup.
				const f = box.querySelectorAll( 'a[href],button' );
				const first = f[ 0 ];
				const end = f[ f.length - 1 ];
				if ( ! inside ) {
					e.preventDefault();
					first.focus();
				} else if ( e.shiftKey && ( doc.activeElement === first || doc.activeElement === box ) ) {
					e.preventDefault();
					end.focus();
				} else if ( ! e.shiftKey && doc.activeElement === end ) {
					e.preventDefault();
					first.focus();
				}
			}
		};
		const close = () => {
			root.classList.remove( 'is-open' );
			doc.removeEventListener( 'keydown', key, true );
			doc.documentElement.classList.remove( 'lu3g-ch-lock' );
			setTimeout( () => root.remove(), 400 );
			if ( modal && last && last.focus ) {
				last.focus( { preventScroll: true } );
			}
		};
		x.onclick = close;
		b.onclick = close;
		if ( modal ) {
			q( '.lu3g-ch-overlay' ).onclick = close;
			last = doc.activeElement;
			doc.documentElement.classList.add( 'lu3g-ch-lock' );
			box.focus( { preventScroll: true } ); // Il lettore di schermo legge titolo e messaggio.
		}
		doc.addEventListener( 'keydown', key, true );
		return root;
	};

	/** Avvio: legge i dati, sceglie la chiusura, controlla dispositivo e frequenza, poi apre dopo il ritardo. */
	const run = () => {
		const el = doc.getElementById( 'lu3g-chiusure-data' );
		if ( ! el ) {
			return;
		}
		let D;
		try {
			D = JSON.parse( el.textContent );
		} catch ( e ) {
			return;
		}
		const S = D.settings;
		const t = today( D );
		const p = pick( D, t );
		if ( ! p ) {
			return;
		}
		const mob = W.matchMedia( '(max-width: 767px)' ).matches;
		if ( ( 'desktop' === S.devices && mob ) || ( 'mobile' === S.devices && ! mob ) ) {
			return;
		}
		// Chiave con ID, fase e inizio del periodo: preavviso e chiusura in corso (e ogni anno) contano a parte.
		const k = 'lu3g-ch-' + p.c.id + '-' + p.ph + '-' + p.o.s;
		const st = store( S.frequency );
		try {
			const v = st && st.getItem( k );
			if ( null !== v && undefined !== v && ( 'session' === S.frequency || +v === t ) ) {
				return;
			}
		} catch ( e ) {}
		setTimeout( () => {
			try {
				if ( st ) {
					st.setItem( k, t );
				}
			} catch ( e ) {}
			render( D, p );
		}, S.delay * 1e3 );
	};

	// Usato dall'anteprima del pannello (render) e dai test (pick, today).
	W.LU3GChiusure = { render, pick, today };

	if ( 'loading' === doc.readyState ) {
		doc.addEventListener( 'DOMContentLoaded', run );
	} else {
		run();
	}
} )();

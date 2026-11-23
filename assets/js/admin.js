/**
 * LU3G Chiusure — pannello. JavaScript vanilla; jQuery solo per il color picker di WordPress (wpColorPicker).
 *
 * Scheda "Elenco chiusure": aggiunta, eliminazione e riordino delle righe (frecce o trascinamento), riepilogo e
 * testi predefiniti aggiornati mentre si scrive, controllo delle date, anteprima del popup vero.
 * Scheda "Aspetto e comportamento": anteprima live nel riquadro e a tutto schermo, con popup.js e popup.css del sito.
 */
( () => {
	'use strict';

	const cfg = window.LU3G_CH;
	const page = document.querySelector( '.lu3g-chiusure' );
	if ( ! cfg || ! page ) {
		return;
	}
	const P = window.LU3GChiusure; // Da popup.js: render(), pick(), today().
	const $ = ( s, el ) => ( el || document ).querySelector( s );
	const $$ = ( s, el ) => Array.from( ( el || document ).querySelectorAll( s ) );

	/* ------------------------------------------------------------ avvisi */

	const toasts = $( '[data-toasts]', page );
	const toast = ( text, bad ) => {
		if ( ! toasts || ! text ) {
			return;
		}
		const el = document.createElement( 'div' );
		el.className = 'lch-toast' + ( bad ? ' is-error' : '' );
		el.setAttribute( 'role', bad ? 'alert' : 'status' );
		el.textContent = text;
		toasts.appendChild( el );
		setTimeout( () => {
			el.classList.add( 'is-out' );
			setTimeout( () => el.remove(), 300 );
		}, bad ? 7000 : 3500 );
	};
	( cfg.notice || [] ).forEach( ( n ) => toast( n[ 0 ], n[ 1 ] ) );
	// Toglie saved/bad dall'indirizzo: ricaricando la pagina l'avviso non ricompare.
	if ( window.history.replaceState && /[?&](saved|bad)=/.test( location.search ) ) {
		const u = new URL( location.href );
		u.searchParams.delete( 'saved' );
		u.searchParams.delete( 'bad' );
		window.history.replaceState( null, '', u );
	}

	/* ------------------------------------------------------------ date */

	/** "AAAA-MM-GG" → giorno intero (o null). */
	const toDay = ( v ) => {
		const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec( v || '' );
		return m ? Date.UTC( +m[ 1 ], m[ 2 ] - 1, +m[ 3 ] ) / 864e5 : null;
	};

	/** Giorno → testo con il formato data di WordPress (stessi caratteri di PHP date() più usati). */
	const fmt = ( day ) => {
		const d = new Date( day * 864e5 );
		const Y = d.getUTCFullYear();
		const m = d.getUTCMonth();
		const j = d.getUTCDate();
		const w = d.getUTCDay();
		const pad = ( n ) => String( n ).padStart( 2, '0' );
		const f = cfg.dateFormat || 'j F Y';
		let out = '';
		for ( let i = 0; i < f.length; i++ ) {
			const ch = f[ i ];
			const map = {
				d: pad( j ), j, D: cfg.daysAbbr[ w ], l: cfg.days[ w ], N: w || 7, w,
				S: ( j % 10 === 1 && j !== 11 ) ? 'st' : ( j % 10 === 2 && j !== 12 ) ? 'nd' : ( j % 10 === 3 && j !== 13 ) ? 'rd' : 'th',
				m: pad( m + 1 ), n: m + 1, F: cfg.months[ m ], M: cfg.monthsAbbr[ m ], y: String( Y ).slice( -2 ), Y,
			};
			if ( '\\' === ch ) {
				out += f[ ++i ] || '';
			} else {
				out += Object.prototype.hasOwnProperty.call( map, ch ) ? map[ ch ] : ch;
			}
		}
		return out;
	};

	const todayLocal = () => {
		const n = new Date();
		return Date.UTC( n.getFullYear(), n.getMonth(), n.getDate() ) / 864e5;
	};

	/** Colore leggibile sopra il colore principale (come in PHP). */
	const contrast = ( hex ) => {
		let h = ( hex || '' ).replace( '#', '' );
		if ( 3 === h.length ) {
			h = h.replace( /./g, '$&$&' );
		}
		if ( 6 !== h.length ) {
			return '#ffffff';
		}
		const lin = ( i ) => {
			const c = parseInt( h.substr( i, 2 ), 16 ) / 255;
			return c <= 0.03928 ? c / 12.92 : Math.pow( ( c + 0.055 ) / 1.055, 2.4 );
		};
		return 0.2126 * lin( 0 ) + 0.7152 * lin( 2 ) + 0.0722 * lin( 4 ) > 0.179 ? '#111111' : '#ffffff';
	};

	/** Apre il popup vero a tutto schermo (anteprima): i link non portano via dalla pagina. */
	const openReal = ( D, p ) => {
		$$( '.lu3g-ch:not(.lu3g-ch--preview)' ).forEach( ( el ) => el.remove() );
		const root = P.render( D, p );
		$$( 'a', root ).forEach( ( a ) => a.addEventListener( 'click', ( e ) => e.preventDefault() ) );
	};

	/* ------------------------------------------------------------ modifiche non salvate */

	let dirty = false;
	$$( '[data-form]', page ).forEach( ( form ) => {
		const badge = $( '[data-dirty]', form );
		const mark = () => {
			dirty = true;
			if ( badge ) {
				badge.hidden = false;
			}
		};
		form.addEventListener( 'input', mark );
		form.addEventListener( 'change', mark );
		form.lch_mark = mark;
		form.addEventListener( 'submit', ( e ) => {
			if ( form.lch_validate && ! form.lch_validate() ) {
				e.preventDefault();
				return;
			}
			dirty = false;
		} );
	} );
	window.addEventListener( 'beforeunload', ( e ) => {
		if ( dirty ) {
			e.preventDefault();
			e.returnValue = '';
		}
	} );

	/* ============================================================ scheda Elenco chiusure */

	const list = $( '[data-rows]', page );
	if ( list ) {
		const form = list.closest( 'form' );
		const tpl = $( '[data-template]', page );
		const empty = $( '[data-empty]', page );
		const f = ( row, name ) => $( '[data-f="' + name + '"]', row );
		const val = ( row, name ) => {
			const el = f( row, name );
			if ( ! el ) {
				return '';
			}
			return 'checkbox' === el.type ? el.checked : el.value.trim();
		};

		/** Periodo come lo vede il visitatore (per riepilogo e anteprima). Null se mancano le date. */
		const period = ( row ) => {
			let s = toDay( val( row, 'start' ) );
			let e = toDay( val( row, 'end' ) );
			if ( null === s || null === e ) {
				return null;
			}
			const rec = val( row, 'recurring' );
			if ( e < s && rec && 'easter' !== val( row, 'type' ) ) {
				// Ricorrente a cavallo d'anno (24/12 → 06/01): la fine cade nell'anno dopo l'inizio.
				const de = new Date( e * 864e5 );
				e = Date.UTC( new Date( s * 864e5 ).getUTCFullYear() + 1, de.getUTCMonth(), de.getUTCDate() ) / 864e5;
			} else if ( e < s ) {
				[ s, e ] = [ e, s ];
			}
			let r = toDay( val( row, 'reopen' ) );
			r = null !== r && r > e ? r : e + 1;
			// Ricorrente già passata: mostra il prossimo anno (Pasqua esclusa, la calcola solo il server).
			const shift = ( d, k ) => {
				const x = new Date( d * 864e5 );
				return Date.UTC( x.getUTCFullYear() + k, x.getUTCMonth(), x.getUTCDate() ) / 864e5;
			};
			let k = 0;
			while ( rec && 'easter' !== val( row, 'type' ) && shift( e, k ) < todayLocal() && k < 200 ) {
				k++;
			}
			return { s: shift( s, k ), e: shift( e, k ), r: shift( r, k ) };
		};

		/** Aggiorna intestazione, icona, testi predefiniti (segnaposto grigi) e controllo date della riga. */
		const refreshRow = ( row ) => {
			const type = val( row, 'type' ) || 'summer';
			const t = cfg.types[ type ] || cfg.types.summer;
			$( '[data-title]', row ).textContent = val( row, 'name' ) || cfg.i18n.noName;
			$( '[data-icon]', row ).innerHTML = t.icon;
			const p = period( row );
			let sum = t.label;
			if ( p ) {
				sum += ' · ' + fmt( p.s ) + ( p.e !== p.s ? ' → ' + fmt( p.e ) : '' );
			}
			if ( val( row, 'recurring' ) ) {
				sum += ' · ' + cfg.i18n.everyYear;
			}
			$( '[data-summary]', row ).textContent = sum;
			const one = p && p.s === p.e;
			[ 'notice_title', 'active_title', 'active_message' ].forEach( ( k ) => {
				f( row, k ).placeholder = t[ k ];
			} );
			f( row, 'notice_message' ).placeholder = one ? t.one_day : t.notice_message;
			[ 'start', 'end' ].forEach( ( k ) => f( row, k ).classList.remove( 'is-invalid' ) );
		};

		const refresh = () => {
			const rows = $$( '[data-row]', list );
			rows.forEach( ( row, i ) => {
				$( '[data-up]', row ).disabled = 0 === i;
				$( '[data-down]', row ).disabled = i === rows.length - 1;
			} );
			empty.hidden = rows.length > 0;
		};

		const setOpen = ( row, open ) => {
			row.classList.toggle( 'is-open', open );
			$( '.lch-row__body', row ).hidden = ! open;
			$( '[data-expand]', row ).setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		};

		$$( '[data-row]', list ).forEach( refreshRow );
		refresh();

		// Aggiungi.
		$( '[data-add]', page ).addEventListener( 'click', () => {
			const key = 'n' + Date.now().toString( 36 ) + Math.random().toString( 36 ).slice( 2, 6 );
			const box = document.createElement( 'div' );
			box.innerHTML = tpl.innerHTML.replace( /__KEY__/g, key );
			const row = box.firstElementChild;
			row.classList.add( 'is-new' );
			list.appendChild( row );
			refreshRow( row );
			refresh();
			form.lch_mark();
			f( row, 'name' ).focus();
		} );

		list.addEventListener( 'click', ( e ) => {
			const row = e.target.closest( '[data-row]' );
			if ( ! row ) {
				return;
			}
			const btn = e.target.closest( 'button' );
			if ( ! btn ) {
				return;
			}
			if ( btn.matches( '[data-expand]' ) ) {
				setOpen( row, ! row.classList.contains( 'is-open' ) );
			} else if ( btn.matches( '[data-up]' ) && row.previousElementSibling ) {
				list.insertBefore( row, row.previousElementSibling );
				form.lch_mark();
				refresh();
				btn.disabled ? $( '[data-down]', row ).focus() : btn.focus();
			} else if ( btn.matches( '[data-down]' ) && row.nextElementSibling ) {
				list.insertBefore( row.nextElementSibling, row );
				form.lch_mark();
				refresh();
				btn.disabled ? $( '[data-up]', row ).focus() : btn.focus();
			} else if ( btn.matches( '[data-remove]' ) ) {
				// eslint-disable-next-line no-alert
				if ( window.confirm( cfg.i18n.confirmDelete ) ) {
					const next = row.nextElementSibling || row.previousElementSibling;
					row.remove();
					form.lch_mark();
					refresh();
					( next ? $( '[data-expand]', next ) : $( '[data-add]', page ) ).focus();
				}
			} else if ( btn.matches( '[data-preview]' ) ) {
				preview( row, btn.getAttribute( 'data-preview' ) );
			}
		} );

		list.addEventListener( 'input', ( e ) => {
			const row = e.target.closest( '[data-row]' );
			if ( row ) {
				refreshRow( row );
			}
		} );
		list.addEventListener( 'change', ( e ) => {
			const row = e.target.closest( '[data-row]' );
			if ( row ) {
				refreshRow( row );
			}
		} );

		// Trascinamento: la riga diventa trascinabile solo afferrando la maniglia (i campi restano selezionabili).
		let drag = null;
		list.addEventListener( 'pointerdown', ( e ) => {
			const row = e.target.closest( '[data-row]' );
			if ( row ) {
				row.draggable = !! e.target.closest( '[data-handle]' );
			}
		} );
		list.addEventListener( 'dragstart', ( e ) => {
			drag = e.target.closest && e.target.closest( '[data-row]' );
			if ( ! drag ) {
				return;
			}
			drag.classList.add( 'is-dragging' );
			e.dataTransfer.effectAllowed = 'move';
			try {
				e.dataTransfer.setData( 'text/plain', '' );
			} catch ( err ) {}
		} );
		list.addEventListener( 'dragover', ( e ) => {
			if ( ! drag ) {
				return;
			}
			e.preventDefault();
			const over = e.target.closest( '[data-row]' );
			if ( ! over || over === drag ) {
				return;
			}
			const r = over.getBoundingClientRect();
			list.insertBefore( drag, e.clientY > r.top + r.height / 2 ? over.nextSibling : over );
		} );
		list.addEventListener( 'drop', ( e ) => e.preventDefault() );
		list.addEventListener( 'dragend', () => {
			if ( drag ) {
				drag.classList.remove( 'is-dragging' );
				drag.draggable = false;
				drag = null;
				form.lch_mark();
				refresh();
			}
		} );

		/** Anteprima del popup vero con i testi della riga e le impostazioni salvate. */
		const preview = ( row, ph ) => {
			const p = period( row );
			if ( ! p ) {
				setOpen( row, true );
				[ 'start', 'end' ].forEach( ( k ) => ! val( row, k ) && f( row, k ).classList.add( 'is-invalid' ) );
				toast( cfg.i18n.noDates, true );
				return;
			}
			const type = val( row, 'type' ) || 'summer';
			const t = cfg.types[ type ];
			const c = {
				id: 'preview',
				type,
				notice: +val( row, 'notice_days' ) || 0,
				name: val( row, 'name' ) || t.label,
				nt: val( row, 'notice_title' ) || t.notice_title,
				nm: val( row, 'notice_message' ) || ( p.s === p.e ? t.one_day : t.notice_message ),
				at: val( row, 'active_title' ) || t.active_title,
				am: val( row, 'active_message' ) || t.active_message,
			};
			const o = { s: p.s, e: p.e, fs: fmt( p.s ), fe: fmt( p.e ), fr: fmt( p.r ) };
			const icons = {};
			icons[ type ] = t.icon;
			openReal( { settings: cfg.settings, icons, i18n: { close: cfg.closeLabel } }, { c, o, ph } );
		};

		/** Prima di salvare: una chiusura attiva deve avere inizio e fine. */
		form.lch_validate = () => {
			let first = null;
			$$( '[data-row]', list ).forEach( ( row ) => {
				const blank = ! val( row, 'name' ) && ! val( row, 'start' ) && ! val( row, 'end' );
				if ( blank || ! val( row, 'enabled' ) ) {
					return;
				}
				[ 'start', 'end' ].forEach( ( k ) => {
					if ( ! val( row, k ) ) {
						f( row, k ).classList.add( 'is-invalid' );
						first = first || row;
					}
				} );
			} );
			if ( first ) {
				setOpen( first, true );
				$( '.is-invalid', first ).focus();
				toast( cfg.i18n.noDates, true );
				return false;
			}
			return true;
		};
	}

	/* ============================================================ scheda Aspetto e comportamento */

	const screen = $( '[data-screen]', page );
	if ( screen ) {
		const form = screen.closest( 'form' );
		const g = ( n ) => $( '[name="settings[' + n + ']"]', form );
		const pickSel = $( '[data-preview-pick]', page );
		let phase = 'notice';

		// Chiusure salvate; senza nessuna, un esempio estivo tra 10 giorni.
		let items = cfg.preview || [];
		if ( ! items.length ) {
			const t = cfg.types.summer;
			const s = todayLocal() + 10;
			items = [ { id: 'sample', type: 'summer', notice: 7, name: t.label, nt: t.notice_title, nm: t.notice_message, at: t.active_title, am: t.active_message, occ: [ { s, e: s + 20, fs: fmt( s ), fe: fmt( s + 20 ), fr: fmt( s + 21 ) } ] } ];
		}
		items.forEach( ( c, i ) => {
			const o = document.createElement( 'option' );
			o.value = i;
			o.textContent = c.name + ' — ' + c.occ[ 0 ].fs;
			pickSel.appendChild( o );
		} );

		const hex = ( v, d ) => ( /^#([0-9a-f]{3}){1,2}$/i.test( v ) ? v : d );
		const live = () => {
			const s = cfg.settings;
			const layout = ( $( '[name="settings[layout]"]:checked', form ) || {} ).value || 'modal';
			const primary = hex( g( 'primary' ).value, s.primary );
			const url = g( 'cta_url' ).value.trim();
			return Object.assign( {}, s, {
				layout,
				primary,
				onPrimary: contrast( primary ),
				text: hex( g( 'text' ).value, s.text ),
				bg: hex( g( 'bg' ).value, s.bg ),
				radius: +g( 'radius' ).value || 0,
				close: g( 'close_text' ).value.trim() || cfg.closeText,
				ctaText: g( 'cta_text' ).value.trim(),
				ctaUrl: /^(https?:|mailto:|tel:|\/)/i.test( url ) ? url : '',
			} );
		};
		const data = () => {
			const c = items[ +pickSel.value || 0 ];
			const icons = {};
			icons[ c.type ] = cfg.types[ c.type ] ? cfg.types[ c.type ].icon : '';
			return { D: { settings: live(), icons, i18n: { close: cfg.closeLabel } }, p: { c, o: c.occ[ 0 ], ph: phase } };
		};

		let timer = 0;
		const update = () => {
			clearTimeout( timer );
			timer = setTimeout( () => {
				$$( '.lu3g-ch', screen ).forEach( ( el ) => el.remove() );
				const x = data();
				P.render( x.D, x.p, screen );
				$( '[data-radius-out]', page ).textContent = g( 'radius' ).value + ' px';
			}, 60 );
		};

		// I clic dentro l'anteprima non fanno niente (né link né chiusura).
		screen.addEventListener( 'click', ( e ) => e.preventDefault(), true );
		form.addEventListener( 'input', update );
		form.addEventListener( 'change', update );
		pickSel.addEventListener( 'change', update );
		$$( '[data-phase]', page ).forEach( ( b ) =>
			b.addEventListener( 'click', () => {
				phase = b.getAttribute( 'data-phase' );
				$$( '[data-phase]', page ).forEach( ( x ) => {
					x.classList.toggle( 'is-active', x === b );
					x.setAttribute( 'aria-pressed', x === b ? 'true' : 'false' );
				} );
				update();
			} )
		);
		$( '[data-preview-real]', page ).addEventListener( 'click', () => {
			const x = data();
			x.D.settings.delay = 0;
			openReal( x.D, x.p );
		} );

		// "ID delle pagine" solo per Solo/Tutte tranne.
		const pages = $( '[data-pages]', form );
		pages.addEventListener( 'change', () => {
			$( '[data-page-ids]', form ).hidden = ! [ 'include', 'exclude' ].includes( pages.value );
		} );

		// Color picker di WordPress (richiede jQuery, solo in amministrazione).
		if ( window.jQuery && window.jQuery.fn.wpColorPicker ) {
			window.jQuery( '.lch-color' ).wpColorPicker( {
				change: () => {
					setTimeout( update, 0 );
					form.lch_mark();
				},
				clear: () => setTimeout( update, 0 ),
			} );
		}
		update();
	}
} )();

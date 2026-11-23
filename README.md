# LU3G Chiusure

Le **chiusure dell'attività** (ferie estive, Natale, Pasqua, ponti, chiusure straordinarie) inserite dal backend di
WordPress e mostrate ai visitatori con un **popup moderno, leggero e compatibile con la cache delle pagine**.
Indipendente dai page builder: niente widget, shortcode o integrazioni con Elementor.

Plugin WordPress di LU3G Agenzia Web. Requisiti: PHP 8.0+, WordPress 6.0+.

## Struttura
```
lu3g-chiusure/
├─ lu3g-chiusure.php        intestazione (Update URI), costanti, avvio
├─ uninstall.php            rimuove le opzioni (anche in ogni sito di un Multisite)
├─ includes/
│  ├─ class-plugin.php      caricamento automatico delle classi, impostazioni, WPML, svuotamento cache, hook
│  ├─ class-admin.php       menu "Chiusure", schede Elenco chiusure / Aspetto e comportamento, salvataggio
│  ├─ class-closures.php    tipologie, pulizia dati, date come giorni interi, ricorrenze, Pasqua, fase corrente
│  ├─ class-frontend.php    JSON nel footer + caricamento condizionale di popup.js / popup.css
│  ├─ class-schema.php      JSON-LD specialOpeningHoursSpecification
│  └─ class-updater.php     GENERATO da lu3gofficial/lu3g-updater (.lu3g-updater): non modificarlo a mano
├─ assets/
│  ├─ css/admin.css, css/popup.css
│  ├─ js/admin.js, js/popup.js, js/popup.min.js (generato con bin/minify.sh: è il file servito)
│  ├─ fonts/manrope-latin-wght.woff2, lu3g-logo.svg (pannello)
└─ languages/lu3g-chiusure.pot
```

## Come funziona (cache)
In PHP non si decide **se** mostrare il popup: la pagina può restare in cache per giorni. PHP stampa nel footer
`<script type="application/json" id="lu3g-chiusure-data">` con tutte le chiusure attive, i loro periodi già calcolati
(ricorrenze comprese, dall'anno scorso a fra due anni) come **giorni interi**, le date già formattate con il formato di
WordPress e le stringhe già tradotte. `popup.js` calcola "oggi" nel fuso del sito (nome IANA, quindi anche con l'ora
legale; scarto in minuti di riserva), sceglie la chiusura (prima quella in corso, poi il preavviso più vicino), sostituisce
i segnaposto e apre il popup.

In PHP si decide solo ciò che non dipende dal giorno: la pagina (home, ID inclusi/esclusi) e se esiste almeno una
chiusura attiva in preavviso o in corso nei prossimi 12 mesi: altrimenti CSS e JS non vengono caricati.
Lo script ha `nowprocket`, `data-no-optimize`, `data-no-defer`, `data-cfasync="false"` per non essere ritardato fino
all'interazione da WP Rocket, LiteSpeed e Cloudflare. Al salvataggio il plugin svuota le cache di WP Rocket, LiteSpeed,
W3 Total Cache, WP Super Cache, SiteGround, WP Fastest Cache e Breeze (`lu3g_chiusure_purge_cache` per disattivarlo).

## Regole delle date
- Non ricorrente: un periodo; riapertura vuota = giorno dopo la fine.
- Ricorrente: contano giorno e mese. Se la fine cade prima dell'inizio nel calendario (24/12 → 06/01) il periodo
  finisce l'anno dopo. Il 29 febbraio negli anni non bisestili diventa il 28.
- Pasqua ricorrente: le date mantengono la distanza dalla domenica di Pasqua e si spostano ogni anno.
- Preavviso: i N giorni prima dell'inizio (0 = nessun preavviso).
- Frequenza: `sessionStorage` (una volta per sessione) o `localStorage` (una volta al giorno), chiave
  `lu3g-ch-<id>-<fase>-<inizio>`: preavviso, chiusura in corso e ogni anno contano separatamente.

## Hook
- `lu3g_chiusure_closures` (filtro): elenco delle chiusure usato da sito, schema e fase corrente.
- `lu3g_chiusure_settings` (filtro): impostazioni.
- `lu3g_chiusure_before_popup_data` (azione): prima di stampare il JSON, riceve i dati.
- `lu3g_chiusure_popup_data`, `lu3g_chiusure_show_on_page`, `lu3g_chiusure_schema`, `lu3g_chiusure_schema_on_page`,
  `lu3g_chiusure_purge_cache` (filtri).
- WooCommerce (predisposto, **non implementato**): `lu3g_chiusure_woocommerce_notice` (filtro, false) e
  `lu3g_chiusure_woocommerce_init` (azione). Nel carrello la fase si può calcolare in PHP con
  `LU3G_Chiusure_Closures::current()`.

## WPML
I testi scritti nel pannello (nome, titoli, messaggi, pulsanti, link CTA, nome attività) si registrano in
WPML → Traduzione stringhe, contesto "LU3G Chiusure", al salvataggio. I testi predefiniti e le date seguono la lingua
della pagina. Con "solo/tranne le pagine indicate" vale anche l'ID della pagina nella lingua predefinita.

## Sviluppo
```sh
sh bin/minify.sh          # dopo ogni modifica a assets/js/popup.js (esbuild; controlla i 5 KB)
sh bin/build.sh           # dist/lu3g-chiusure.zip
sh ../lu3g-updater/bin/sync.sh .   # rigenera includes/class-updater.php
```
Release: Version + `LU3G_CHIUSURE_VERSION` + readme.txt, sezione nel CHANGELOG, commit, `git tag vX.Y.Z`, push del tag
(oppure Actions → Release → Run workflow).

## Installazione
1. Scarica `lu3g-chiusure.zip` dall'ultima release (o installalo da LU3G Hub).
2. Plugin → Aggiungi nuovo → Carica plugin → Attiva.
3. Bacheca → **Chiusure**: aggiungi le chiusure e salva; in **Aspetto e comportamento** scegli layout, colori e regole.
4. Con WP Rocket/LiteSpeed: il plugin svuota la cache al salvataggio; se usi un'altra cache svuotala a mano.
5. Facoltativo: attiva i dati strutturati e verifica la home con il Test dei risultati multimediali di Google.

## Checklist di test
- **Ricorrenza a cavallo d'anno**: chiusura ricorrente 24/12 → 06/01; lo stato nel pannello mostra il prossimo periodo
  (24 dicembre di quest'anno → 6 gennaio dell'anno dopo); con la data del computer al 3 gennaio il popup è "in corso" e
  la riapertura è il 7 gennaio.
- **Preavviso**: chiusura che inizia fra 5 giorni con 7 giorni di preavviso → popup con i testi "preavviso"; con
  preavviso 0 nessun popup prima dell'inizio; con una chiusura in corso, quella ha la precedenza.
- **Pagina in cache**: con la cache attiva visita la home, poi cambia la data del computer (o aspetta il giorno
  dell'inizio): senza svuotare la cache il popup passa da preavviso a in corso, e sparisce dopo la fine.
- **Frequenza**: per sessione (non ricompare cambiando pagina, ricompare in una nuova finestra anonima); al giorno
  (ricompare il giorno dopo); sempre (a ogni pagina). Preavviso e chiusura in corso contano separatamente.
- **Mobile**: il modale diventa un pannello dal basso; "solo desktop"/"solo mobile" (fino a 767 px) rispettati.
- **Accessibilità da tastiera**: all'apertura il focus va sul popup; Tab e Maiusc+Tab restano dentro (×, CTA, Ho
  capito); Esc chiude; clic sull'overlay chiude; dopo la chiusura il focus torna dov'era. Barre e card non bloccano la
  pagina e non spostano il focus.
- **Pagine**: solo home, solo/tranne gli ID indicati (CSS e JS assenti dove il popup non serve).
- **Riduci movimento** (impostazione del sistema): il popup compare con una sola dissolvenza.
- **Dati strutturati**: con l'opzione attiva la home contiene `specialOpeningHoursSpecification` con le chiusure dei
  prossimi 12 mesi.

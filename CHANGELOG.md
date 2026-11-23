# Changelog

## 1.0.0
- Prima versione: elenco delle chiusure (estiva, natalizia, Pasqua, ponte, straordinaria) con date, riapertura,
  ricorrenza annuale anche a cavallo d'anno (Pasqua che segue la data della Pasqua di ogni anno), giorni di preavviso,
  testi per le fasi "preavviso" e "in corso" con i segnaposto {inizio} {fine} {riapertura} {nome}.
- Popup compatibile con la cache delle pagine: la fase si calcola nel browser nel fuso orario del sito.
- Layout modale (bottom sheet su mobile), barra in alto, barra in basso, card nell'angolo; colori, angoli, pulsante CTA,
  frequenza, ritardo, dispositivi, pagine; anteprima live nel pannello.
- Accessibilità: dialog con aria-modal, trappola del focus, Esc, clic sull'overlay, ritorno del focus, prefers-reduced-motion.
- Dati strutturati JSON-LD (specialOpeningHoursSpecification) per le chiusure dei prossimi 12 mesi.
- Testi compatibili con WPML, svuotamento delle cache di pagina più diffuse al salvataggio, disinstallazione pulita.

# Changelog

## 1.1.0
- Colore principale automatico: "Usa il colore principale del sito" lo prende dal colore Primario del kit di Elementor,
  dalla tavolozza del tema (primary, accent, accent-1, brand) o dalle personalizzazioni di Astra/JupiterX e simili.
  Si può spegnere per scegliere un colore diverso; chi aveva già salvato le impostazioni con la 1.0.0 mantiene il suo colore.
- Icone: per ogni chiusura quella della tipologia, un'altra tra 20 icone della libreria, un'immagine dalla libreria
  media oppure nessuna; interruttore generale "Mostra l'icona nel popup".
- Se il colore principale è troppo chiaro sullo sfondo (contrasto sotto 3:1) l'icona usa il colore del testo.

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

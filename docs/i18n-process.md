# Processo i18n

## Stato attuale

Il plugin `datrooster-partial-payments` usa l'inglese come lingua sorgente e include gia questi file lingua nel pacchetto:

- `it_IT`
- `es_ES`
- `de_DE`

La lingua inglese resta quella di partenza del codice e non richiede un file dedicato.

## Catalogo sorgente

Il template delle stringhe si trova in:

```text
plugins/datrooster-partial-payments/languages/datrooster-partial-payments.pot
```

## Rigenerazione traduzioni

Per rigenerare il file `.pot`, aggiornare i `.po` esistenti e ricompilare i `.mo`:

```bash
bash scripts/build-plugin-translations.sh datrooster-partial-payments
```

Lo script:

1. legge la versione corrente del plugin;
2. estrae tutte le stringhe PHP e template email;
3. aggiorna il catalogo `.pot`;
4. fa `msgmerge` sui `.po` gia presenti;
5. compila i file `.mo` usati direttamente da WordPress.

## Note operative

- quando aggiungiamo nuove stringhe, vanno sempre wrappate con le API i18n di WordPress;
- placeholder come `%s`, `%1$s` e token email come `{site_title}` vanno mantenuti identici nelle traduzioni;
- per WordPress.org potremo in futuro affidarci anche a translate.wordpress.org, ma per le release GitHub il bundle locale resta utile per installazioni immediate.

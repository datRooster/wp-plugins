# WP Plugins

Monorepo per lo sviluppo di plugin WordPress moderni, con focus iniziale su estensioni WooCommerce.

## Obiettivi

- mantenere piu plugin nello stesso workspace con convenzioni condivise;
- usare pratiche attuali WordPress e WooCommerce;
- partire da una base pronta per HPOS, compatibilita e QA;
- sviluppare feature per clienti in modo incrementale e documentato.

## Struttura

```text
wp-plugins/
├── docs/
├── plugins/
│   └── datrooster-partial-payments/
├── .github/
├── composer.json
└── phpcs.xml.dist
```

## Plugin attuali

- `datrooster-partial-payments`: base del plugin WooCommerce per acconti, depositi e pagamenti rateali.

## Convenzioni di lavoro

- ogni plugin vive in `plugins/<slug-plugin>`;
- namespace e prefissi devono essere unici per evitare collisioni;
- niente accesso diretto ai dati ordine via `wp_posts` o `postmeta`: usare le API WooCommerce CRUD;
- la compatibilita con HPOS e le superfici Woo moderne va dichiarata e testata a ogni milestone;
- documentazione funzionale e roadmap restano dentro `docs/`.

## Prossimi passi

1. implementare regole deposito globali e per prodotto;
2. collegare il calcolo acconto al carrello e al checkout;
3. aggiungere gestione saldo residuo, email e automazioni;
4. aggiungere test, CI e pacchettizzazione di rilascio.

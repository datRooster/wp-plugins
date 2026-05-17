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

- `datrooster-partial-payments`: base del plugin WooCommerce per acconti, depositi e pagamenti rateali, con impostazioni globali, override per prodotto e primo flusso storefront.

## Convenzioni di lavoro

- ogni plugin vive in `plugins/<slug-plugin>`;
- namespace e prefissi devono essere unici per evitare collisioni;
- niente accesso diretto ai dati ordine via `wp_posts` o `postmeta`: usare le API WooCommerce CRUD;
- la compatibilita con HPOS e le superfici Woo moderne va dichiarata e testata a ogni milestone;
- documentazione funzionale, roadmap e processo release restano dentro `docs/`.

## Stato attuale

- scelta tra pagamento completo e deposito sulla pagina prodotto per prodotti supportati;
- riuso automatico dei gateway WooCommerce esistenti, salvo esclusioni configurate dall'admin;
- gestione iniziale di coupon su articoli con deposito, tasse prodotto proporzionali e spedizione upfront o proporzionale;
- riepilogo del saldo residuo stimato per prodotti, tasse e spedizione in carrello e checkout classici, con metadati ordine dedicati;
- niente gateway custom in questa fase: il plugin si affianca ai metodi gia presenti;
- compatibilita HPOS dichiarata; Cart & Checkout Blocks marcati come non ancora supportati finche non completiamo l'integrazione dedicata.

## Prossimi passi

1. aggiungere gestione del saldo residuo da area cliente, link sicuri e stati ordine dedicati;
2. aggiungere email transazionali, reminder e automazioni;
3. estendere le regole a variazioni, categorie, ruoli e condizioni carrello;
4. integrare Cart & Checkout Blocks e preparare la checklist finale per WordPress.org.

## Release

- i pacchetti installabili vengono pubblicati tramite GitHub Releases;
- la convenzione tag del monorepo e `plugins/<slug>/vX.Y.Z`;
- il flusso completo e documentato in `docs/release-process.md`.

# Partial Payments Roadmap

## Fase 1

- scaffold repository e plugin;
- Settings API con opzioni base;
- verifica dipendenza WooCommerce;
- dichiarazioni iniziali di compatibilita WooCommerce;
- documentazione funzionale e tecnica.

## Fase 2

- metabox o product editor per regole deposito per singolo prodotto;
- override per variazioni;
- regole globali e fallback di priorita.

## Fase 3

- calcolo deposito nel carrello;
- supporto checkout classico;
- gateway consentiti o esclusi;
- creazione ordine con metadati chiari per saldo residuo;
- prime politiche configurabili per coupon, tasse e spedizione.

## Fase 4

- pagamento saldo da area cliente;
- email transazionali e reminder;
- scadenze, cron e automazioni;
- storico pagamenti e note ordine;
- integrazione Cart & Checkout Blocks.

## Fase 5

- piani di pagamento multipli;
- fee, tasse, spedizione e coupon con matrici piu granulari;
- regole per ruolo, categoria e totale carrello;
- reportistica e dashboard merchant.

## Vincoli di qualita

- WordPress coding standards;
- compatibilita HPOS;
- accessibilita WCAG 2.2 AA;
- niente dipendenze da API WooCommerce interne;
- test regressione su flussi ordine, saldo e refund.

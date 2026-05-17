# Partial Payments Benchmark

Aggiornato il 2026-05-17.

## Plugin di riferimento analizzato

- Acowebs: `Deposits & Partial Payments for WooCommerce`
- Directory WordPress: https://wordpress.org/plugins/deposits-partial-payments-for-woocommerce/
- Stato osservato il 2026-05-17:
  - versione `1.2.8`
  - aggiornato circa 3 settimane prima
  - compatibile fino a WordPress `6.9.4`
  - changelog con supporto WooCommerce `10.7`

## Feature rilevate nella versione libera

- attivazione o disattivazione deposito a livello store;
- scelta cliente tra pagamento intero e acconto;
- limitazione o controllo per utenti non autenticati;
- acconto in cifra fissa o percentuale;
- impostazioni globali e impostazioni per singolo prodotto;
- abilitazione o disabilitazione deposito per prodotti specifici;
- pagamento del saldo residuo dall'account cliente;
- disabilitazione di gateway specifici per ordini con deposito;
- personalizzazione testi ed etichette;
- supporto traduzioni.

## Feature rilevate nella versione premium

- piani di pagamento flessibili;
- deposito anche al checkout;
- email reminder e notifiche per cliente e admin;
- regole per categoria;
- regole per ruolo utente;
- deposito obbligatorio;
- restrizioni per ruolo;
- gestione fee, tasse, spedizioni e coupon;
- gestione stock con ordini a deposito;
- piu piani per singolo prodotto;
- scheduling rate per giorni, settimane, mesi o anni;
- addebito automatico futuro via Stripe;
- dashboard analytics;
- ulteriori opzioni commerciali minori.

## Principi tecnici per la nostra implementazione

- usare namespace e prefissi unici, evitando collisioni con WordPress e WooCommerce;
- mantenere una struttura plugin chiara con file root, `uninstall.php`, `languages/` e cartelle applicative;
- non usare classi WooCommerce marcate `@internal` o namespace `Automattic\WooCommerce\Internal`;
- dichiarare e testare compatibilita per HPOS e altre superfici WooCommerce rilevanti;
- usare WooCommerce CRUD per ordini e meta, senza dipendere da `wp_posts` o `postmeta` per i dati ordine;
- progettare le impostazioni con default sensati, gerarchia chiara e senza pannelli troppo densi;
- mirare a WCAG `2.2` livello `AA` per front-end e back-office.

## Gap da colmare per superare il plugin di riferimento

- nessuna distinzione free/premium nel codice del progetto;
- regole composabili su prodotto, categoria, carrello, ruolo e metodo di pagamento;
- gestione saldo residuo piu chiara per merchant e cliente;
- automazioni email e scadenze con storicizzazione;
- compatibilita nativa con HPOS, blocchi checkout e flussi WooCommerce recenti;
- architettura interna pensata per estendersi senza riscrivere il core.

# WordPress.org Distribution Notes

Aggiornato il 2026-05-19.

## Punto chiave

La WordPress.org Plugin Directory non e un marketplace a pagamento. E una directory pubblica per plugin distribuiti in modo open source e revisionati dal team WordPress.

## Implicazioni pratiche per questo progetto

- la licenza attuale `GPL-2.0-or-later` va bene ed e in linea con le raccomandazioni ufficiali;
- il plugin header con licenza GPL e una nota GPL nel file principale sono una base corretta per la distribuzione;
- se pubblicheremo il plugin su WordPress.org, tutto il codice distribuito nella directory dovra restare GPL-compatible;
- non possiamo caricare trialware o codice ofuscato;
- il plugin inviato deve essere completo e pronto all'uso al momento della submission;
- upsell e servizi esterni sono ammessi solo entro i limiti delle linee guida, senza invadere la dashboard;
- la distribuzione su WordPress.org usa SVN per i rilasci, anche se lo sviluppo puo restare su GitHub;
- le dichiarazioni di compatibilita WooCommerce vanno allineate a cio che e stato davvero testato: se Cart & Checkout Blocks non sono ancora integrati, e meglio dichiarare incompatibilita.
- il campo `Contributors` del `readme.txt` deve corrispondere a uno username WordPress.org reale che abbia accesso al plugin.

## Esito smoke test locale

- il flusso classico WooCommerce e stato verificato con successo su WordPress locale;
- la soglia minima deposito sul totale carrello ora abilita correttamente la scelta globale tra pagamento completo e acconto;
- ordine deposito, stato `Partially paid`, ordine saldo collegato e pagamento finale sono risultati coerenti;
- il principale punto operativo da comunicare ai merchant e che questa release richiede ancora carrello e checkout classici, non i Blocks.

## Strategia consigliata

- mantenere questo repository GitHub come sorgente di sviluppo;
- preparare in seguito una build di distribuzione pulita;
- usare WordPress.org per visibilita, installazione e aggiornamenti del core gratuito;
- vendere supporto, customizzazioni o add-on esterni solo se davvero necessari e sempre in modo compatibile con le linee guida.

## Checklist finale prima della submission

- fare uno smoke test completo su WordPress + WooCommerce reali, inclusi ordine con acconto, ordine saldo, reminder email e pagamento finale;
- eseguire PHPCS/WPCS e correggere eventuali warning bloccanti;
- verificare che `readme.txt` sia allineato, chiaro e senza promesse di feature non ancora implementate;
- verificare che il campo `Contributors` nel `readme.txt` corrisponda allo username WordPress.org che fara la submission;
- preparare asset WordPress.org come icona, banner e screenshot coerenti con il flusso plugin;
- confermare che tutte le dichiarazioni di compatibilita WooCommerce riflettano solo cio che e davvero testato;
- decidere il naming finale da usare nella directory, evitando collisioni o problemi trademark;
- preparare il pacchetto ZIP definitivo e poi il rilascio via SVN per WordPress.org.

## Note sul naming

- evitare slug che iniziano con marchi di terzi;
- se il naming finale resta commerciale, verificare prima che non violi trademark o nomi gia usati.

## Riferimenti ufficiali

- Detailed Plugin Guidelines: https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/
- Header Requirements: https://developer.wordpress.org/plugins/plugin-basics/header-requirements/
- Planning, Submitting, and Maintaining Plugins: https://developer.wordpress.org/plugins/wordpress-org/planning-submitting-and-maintaining-plugins/

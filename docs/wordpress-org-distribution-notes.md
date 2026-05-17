# WordPress.org Distribution Notes

Aggiornato il 2026-05-17.

## Punto chiave

La WordPress.org Plugin Directory non e un marketplace a pagamento. E una directory pubblica per plugin distribuiti in modo open source e revisionati dal team WordPress.

## Implicazioni pratiche per questo progetto

- la licenza attuale `GPL-2.0-or-later` va bene ed e in linea con le raccomandazioni ufficiali;
- se pubblicheremo il plugin su WordPress.org, tutto il codice distribuito nella directory dovra restare GPL-compatible;
- non possiamo caricare trialware o codice ofuscato;
- il plugin inviato deve essere completo e pronto all'uso al momento della submission;
- upsell e servizi esterni sono ammessi solo entro i limiti delle linee guida, senza invadere la dashboard;
- la distribuzione su WordPress.org usa SVN per i rilasci, anche se lo sviluppo puo restare su GitHub.

## Strategia consigliata

- mantenere questo repository GitHub come sorgente di sviluppo;
- preparare in seguito una build di distribuzione pulita;
- usare WordPress.org per visibilita, installazione e aggiornamenti del core gratuito;
- vendere supporto, customizzazioni o add-on esterni solo se davvero necessari e sempre in modo compatibile con le linee guida.

## Note sul naming

- evitare slug che iniziano con marchi di terzi;
- se il naming finale resta commerciale, verificare prima che non violi trademark o nomi gia usati.

## Riferimenti ufficiali

- Detailed Plugin Guidelines: https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/
- Header Requirements: https://developer.wordpress.org/plugins/plugin-basics/header-requirements/
- Planning, Submitting, and Maintaining Plugins: https://developer.wordpress.org/plugins/wordpress-org/planning-submitting-and-maintaining-plugins/

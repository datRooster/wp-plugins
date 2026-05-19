# WordPress.org Submission Checklist

Aggiornato il 2026-05-19.

## Prima della submission

- conferma lo slug finale del plugin e verifica che non violi trademark o nomi gia usati;
- verifica che il campo `Contributors` in `readme.txt` corrisponda allo username WordPress.org reale che fara la submission;
- prepara un file ZIP completo e installabile, identico a quello che un utente caricherebbe da `Plugin > Aggiungi plugin > Carica plugin`;
- conferma che `Version` nel file principale e `Stable tag` nel `readme.txt` coincidano;
- controlla che il plugin descriva solo feature gia presenti;
- se il negozio usa WooCommerce Cart o Checkout Blocks, comunica che questa release richiede ancora le pagine classiche con `[woocommerce_cart]` e `[woocommerce_checkout]`.

## Submission iniziale

1. accedi con un account WordPress.org e una email monitorata regolarmente;
2. invia il file ZIP completo tramite il form di submission;
3. attendi la review del team plugin;
4. dopo l'approvazione, usa il repository SVN assegnato da WordPress.org per pubblicare `trunk` e i `tags` stabili.

## Cosa ricordare

- WordPress.org non e un marketplace a pagamento diretto: distribuisce il plugin GPL gratuito;
- il codice pubblicato nella directory deve restare leggibile, completo e GPL-compatible;
- trialware, codice offuscato e admin nag invasivi non sono ammessi;
- SVN e il canale ufficiale di distribuzione anche se lo sviluppo continua su GitHub.

## Riferimenti ufficiali

- https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/
- https://developer.wordpress.org/plugins/wordpress-org/planning-submitting-and-maintaining-plugins/
- https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/
- https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/

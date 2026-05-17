# Release Process

## Obiettivo

Ogni release GitHub deve allegare uno zip installabile del plugin, cosi da poter scaricare direttamente il pacchetto da `Releases` senza clonare il repository.

## Convenzione tag

Per questo monorepo usiamo tag nel formato:

```text
plugins/<plugin-slug>/vX.Y.Z
```

Esempio:

```text
plugins/datrooster-partial-payments/v0.3.0
```

## Cosa succede

Quando viene pubblicato un tag che rispetta questo formato:

1. GitHub Actions legge slug e versione dal tag;
2. esegue `scripts/package-plugin.sh`;
3. verifica che `Version:` del file principale e `Stable tag:` del `readme.txt` corrispondano alla versione richiesta;
4. costruisce uno zip installabile con la cartella del plugin come root;
5. crea la GitHub Release e allega il file `<plugin-slug>-<versione>.zip`.

## Procedura pratica

1. aggiornare nel plugin almeno:
   - header `Version:` del file principale;
   - `Stable tag:` del `readme.txt`;
   - changelog;
2. fare commit e push su `main`;
3. creare il tag:

```bash
git tag plugins/datrooster-partial-payments/v0.3.0
git push origin plugins/datrooster-partial-payments/v0.3.0
```

4. attendere il workflow `Release Plugin`;
5. scaricare lo zip dalla sezione `Releases` del repository.

## Note

- il repository e un monorepo: ogni plugin potra avere release separate usando il proprio slug;
- lo zip allegato e il pacchetto da installare in WordPress;
- se in futuro vorremo una build ancora piu pulita, possiamo introdurre esclusioni dedicate per file di sviluppo.

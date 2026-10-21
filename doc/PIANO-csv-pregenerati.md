# Piano: export CSV pre-generati (link stabile, file divisi per anno)

Stato: **bozza di piano, nessuna modifica al codice**. Data: 2026-10-20.
Contesto: issue "Dataset dinamici" cms#513 (gitlab.com/opencity-labs/sito-istituzionale/cms).

## 1. Problema

`/exportas/csv/<classe>` genera il CSV dentro la richiesta HTTP (`CSVExporter::handleDownload`,
`classes/csvexporter.php`): legge a pagine di 50 nodi con `subTree` (Offset/Limit), carica tutti
gli attributi di ogni oggetto e fa `fputcsv` + `flush()` riga per riga.

Misure sul SaaS (2026-10-20, sito di produzione di un ente, classe `document`, 30.864 record, richiesta anonima):

- primo byte dopo ~21,7 s, poi ~6-7 righe/s;
- in 150 s: 828 righe complete (poi interrotto dal client);
- stima lineare per l'intero file: **75-80 minuti** (da confermare: non misurato oltre 150 s);
- DB `rds-saasopenpa-prod`: CPU ~15% -> ~26% per un minuto, connessioni invariate. Il collo di bottiglia e' il tempo PHP per riga, non il DB;
- Varnish risponde 503 "Page Unavailable" se il primo byte non arriva entro ~60 s (osservato su un sito QA, classe con 177 record: causa del blocco da chiarire).

Conseguenze: oltre alcune migliaia di righe il download si interrompe e il file risulta **troncato**
(sembra riuscito, ma e' incompleto). I dataset pubblicati con link CSV non possono usare una URL con
anno fisso (non si possono aggiornare a mano su molti tenant).

## 2. Obiettivo

1. Link **stabile** `/exportas/csv/<classe>` che serve il CSV **completo**, indipendentemente dal volume.
2. In piu', file **per anno di pubblicazione** `/exportas/csv/<classe>/(anno)/<YYYY>`, anch'essi pre-generati.
3. Generazione in **background**, a carico controllato, fuori dalla richiesta HTTP.
4. Nessuna regressione: se il file pre-generato non c'e', si comporta come oggi (export live).

## 3. Disegno

### 3.1 Script di generazione (nuovo)

Comando CLI eZ (es. `bin/php/exportas_generate.php`, nome da decidere), per un siteaccess:

- argomenti: classe/i (o da `exportas.ini`), `--years=all|current`, `--pause=<secondi>`, `--dry-run`;
- calcola l'intervallo anni da `MIN/MAX(published)` della classe, con le stesse regole di fetch dell'export live (`DefaultFetchParams`, `MainNodeOnly`, ...);
- per ogni anno genera `<classe>_<YYYY>.csv`, poi il completo `<classe>.csv`;
- riusa la logica di `CSVExporter` (`transformNode`, intestazioni, delimitatori da `exportas.ini`); da estrarre in un metodo che scrive su un handle di file invece di `php://output`;
- scrittura **atomica**: file temporaneo + `rename`;
- pausa configurabile tra un anno e l'altro;
- a regime rigenera solo l'anno corrente + il completo; gli anni passati solo con `--years=all` o se richiesto;
- `ParentNodeID` e filtri futuri: stessa chiave nel nome del file, da definire.

### 3.2 Modulo `exportas/csv`

In `modules/exportas/csv.php`, prima di `handleDownload()`:

- se la classe e' abilitata (`exportas.ini`, nuova chiave es. `[Pregenerated] Classes[]=document`) **e** esiste il file corrispondente alla richiesta (classe + anno opzionale), lo serve con gli stessi header di oggi (`Content-Type: text/csv; charset=utf-8`, `Content-Disposition`), piu' `Last-Modified`;
- altrimenti prosegue con l'export live attuale (compatibilita').
- controlli di accesso invariati: `checkAccess` deve restare prima della lettura del file.

### 3.3 Storage (da decidere)

I file devono essere leggibili da **tutte** le istanze nginx del SaaS (9 `arm-nginx-saasopenpa` + il server cron che li genera).
Candidati: file cluster eZ (DFS/S3 gia' usato dal prodotto), filesystem condiviso, bucket S3.
Stima dimensione: ~1,1 KB/riga -> ~35 MB per 30.000 righe.

### 3.4 Scheduling

Voce in `saasopenpa-distribution-prod/cron/crontab` con `ezcron` sull'elenco istanze (come i job esistenti),
eseguita sul server `arm-cron-saasopenpa-prod`, non sui nginx che servono gli utenti. Solo per i tenant e le
classi abilitate. Frequenza iniziale: notturna per anno corrente + completo.

## 4. Fasi

1. **Prototipo** su un tenant, una classe (es. una classe `document` con decine di migliaia di record): script con output su cartella locale + modulo che serve il file. Misurare tempi e carico reali.
2. Decisione storage; scrittura atomica; gestione errori e log.
3. Configurazione per tenant/classe (`exportas.ini`), integrazione cron.
4. Test (vedi sotto), documentazione (guida Gitbook del dataset: cosa entra nei file, aggiornamento "a ieri").
5. Rilascio graduale: pochi tenant, poi estensione.

## 5. Test (da allineare ai test di cms#513)

- righe CSV == `totalCount` JSON della classe (verificare la differenza gia' vista: `article` su un sito con 13 record, CSV 15 righe vs totalCount 13);
- ultima riga completa, file non troncato;
- anno corrente aggiornato dopo la rigenerazione; contenuti privati/bozze/eliminati assenti;
- file mancante o corrotto -> fallback all'export live;
- accessi: utente senza permesso `exportas/csv` non legge il file;
- carico: tempo di generazione, CPU sul server cron, nessun impatto visibile sul sito durante la generazione.

## 6. Rischi e punti aperti

- **Storage condiviso**: e' l'incognita principale; non verificato come funziona il file cluster nel SaaS.
- **Anni sproporzionati**: un anno con 20.000 documenti costa ~50 min di PHP; ok in background, da schedulare fuori orario.
- **Dati non in tempo reale**: i file sono aggiornati all'ultima esecuzione; va dichiarato nella guida.
- **Moltiplicazione per tenant**: limitare a classi con un dataset reale (522 istanze nell'elenco cron).
- **Data di riferimento**: l'anno e' sulla data di **pubblicazione**, non di modifica.
- **Versioni**: `master` locale = 1.3.6; il SaaS usa 1.3.5 (`composer.lock` di saasopenpa-distribution-prod); `csvexporter.php` e `csv.php` sono identici, differiscono solo commenti PHPStan in `abstractexporter.php`.
- **503 di Varnish su `place` (QA)**: causa non chiarita; va capito se e' un problema distinto dal volume.
- Altri exporter (XML, ANAC, SICOPAT) restano fuori scopo.

## 7. Prerequisiti

- Branch di lavoro creato da `master` (regola del profilo: niente commit diretti su master in questo repo).
- Conferma del disegno prima di scrivere codice.

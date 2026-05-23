# Documents-Modul: Mietvertragsdokumente und PDF-Workflow

Dieses Dokument beschreibt das Documents-Modul am ersten Anwendungsfall
Mietvertragsdokumente. Es ist eine Produkt- und Frontend-Uebergabe fuer den
Dokument-Workflow. Die aktuelle technische HTTP-API bleibt `docs/openapi.yaml`.

## Ziel

Aus einem strukturierten `RentalAgreement` soll ein verstaendliches
Mietvertragsdokument entstehen. Vermieter und Mieter sollen ohne Fachwissen
sehen koennen:

- welcher Vertrag gerade bearbeitet wird
- aus welcher Vorlage das Dokument erzeugt wurde
- ob ein PDF bereits erzeugt wurde
- ob eine unterschriebene Version hochgeladen wurde
- welche Datei die aktuell gueltige Dokumentversion ist

Digitale Signaturen werden nicht als erster Schritt gebaut. Der erste Schritt
ist ein PDF mit klassischen Unterschriftsfeldern und der Moeglichkeit, eine
unterschriebene Version wieder hochzuladen.

## Architekturentscheidung

Dokumente sollen als eigenes Modul gedacht werden. Das Modul bleibt vorerst im
bestehenden Laravel-Projekt, soll aber so geschnitten werden, dass es spaeter
bei Bedarf in ein eigenes Repository oder Paket verschoben werden kann.

Das bedeutet:

- Documents verwaltet Vorlagen, Dokumentversionen, Dateien, PDF-Erzeugung,
  unterschriebene Uploads und spaeter Signaturintegration.
- Rental Agreements liefern nur die fachlichen Vertragsdaten.
- Rental Agreements sollen nicht selbst wissen, wie PDFs erzeugt, gespeichert
  oder signiert werden.
- Documents darf am Anfang `RentalAgreement` als ersten Anwendungsfall kennen,
  sollte aber nicht ausschliesslich darauf zugeschnitten werden.
- Die API darf fuer gute UX kontextbezogene Routen anbieten, z. B.
  `/rental-agreements/{rentalAgreement}/documents`, intern sollte das Dokument
  aber generisch modelliert werden.

Der Schnitt ist bewusst modular, aber noch kein Microservice. Es ist ein
interner Modul-Schnitt im Monolith.

## Grundidee

`RentalAgreement` bleibt der strukturierte Mietvertrag mit Daten wie Objekt,
Vermieter, Mieter, Laufzeit, Miete, Nebenkosten, Kaution und Status.

Zusatzlich soll es generische Dokument-Konzepte geben:

- `DocumentTemplate`: editierbare Vorlage fuer Dokumente
- `Document`: konkrete Dokumentakte zu einem fachlichen Objekt
- `DocumentVersion`: konkrete erzeugte Version, z. B. ein PDF-Snapshot
- `DocumentFile`: gespeicherte Datei, z. B. Original-PDF oder unterschriebener Upload
- `DocumentReminder`: Frist oder Wiedervorlage zu einer Dokumentakte

Ein Mietvertragsdokument ist dann nur ein Spezialfall:

- `documentable_type`: `RentalAgreement`
- `documentable_id`: ID des Mietvertrags
- `document_type`: z. B. `rental_agreement_contract`
- `template_type`: z. B. `rental_agreement`

Die Vorlage ist flexibel und kann Text, Abschnitte, Platzhalter und spaeter
optionale Klauseln enthalten. Eine erzeugte Dokumentversion ist ein Snapshot:
Wenn ein PDF erzeugt wird, bleiben Vorlage, Vertragsdaten und Branding in dieser
Version eingefroren.

## Aktueller Backend-Stand

Implementiert ist die generische Datenbasis im bestehenden Laravel-Projekt
inklusive HTTP-Endpunkten fuer Dokument-Metadaten, PDF-Erzeugung, Download und
Upload/Download unterschriebener Dokumentdateien. Dokumente koennen ausserdem
freigegeben oder verworfen werden. Fristen und Erinnerungen koennen an eine
Dokumentakte gehaengt werden. Die technische API ist in `openapi.yaml`
dokumentiert.

Angelegt sind:

- `document_templates`: Vorlagen mit `name`, `document_type`,
  `template_type`, `locale`, `version`, `status`, `content`, `placeholders`,
  `metadata` und optionalem `created_by_id`
- `documents`: generische Dokumentakte mit polymorphem `documentable`,
  optionaler Vorlage, `document_type`, Dokumentstatus, Titel und Metadaten
- `document_versions`: versionierte Snapshots mit `version_number`, Status,
  Inhaltssnapshot, Template-Snapshot, Datensnapshot, Metadaten,
  `generated_by_id` und `generated_at`
- `document_files`: Storage-Metadaten fuer Dateien einer Dokumentversion,
  z. B. erzeugtes PDF, unterschriebener Upload oder Anhang
- `document_reminders`: Fristen/Wiedervorlagen mit `due_at`, optionalem
  `remind_at`, Status, Zuweisung und Metadaten

`RentalAgreement` hat eine polymorphe `documents`-Relation. Dadurch kann ein
Mietvertrag Dokumente bekommen, ohne PDF-Pfade oder Dokument-Interna direkt im
Mietvertrag zu speichern.

Aktuell implementierte Endpunkte:

- `GET /rental-agreements/{rentalAgreement}/documents`: Dokumente eines Mietvertrags listen
- `POST /rental-agreements/{rentalAgreement}/documents`: Dokumentakte im Status `draft` am Mietvertrag anlegen
- `GET /documents/{document}`: einzelne Dokument-Metadaten anzeigen
- `POST /documents/{document}/generate`: Snapshot-Version und PDF aus Vorlage erzeugen
- `POST /documents/{document}/share`: erzeugte Dokumentversion freigeben
- `POST /documents/{document}/void`: Dokument und neueste Version verwerfen
- `GET /documents/{document}/download`: aktuell erzeugtes PDF herunterladen
- `POST /documents/{document}/signed-upload`: unterschriebene Datei hochladen
- `GET /documents/{document}/signed-download`: unterschriebene Datei herunterladen
- `GET /documents/{document}/reminders`: Fristen/Erinnerungen listen
- `POST /documents/{document}/reminders`: Frist/Erinnerung anlegen
- `PATCH /document-reminders/{documentReminder}`: Frist/Erinnerung aktualisieren
- `DELETE /document-reminders/{documentReminder}`: Frist/Erinnerung loeschen

Fuer getrennte Mieter- und Vermieter-Sichten gilt aktuell:

- Vermieter sehen und verwalten die vollstaendige Dokumentakte eigener
  Mietvertraege.
- Mieter sehen Dokumente eigener Mietvertraege erst ab `shared` oder
  `signed_uploaded`.
- `draft`, `generated` und `void` werden in Mieter-Listen und bei
  `GET /documents/{document}` verborgen.
- Mieter duerfen erzeugte PDFs erst nach Freigabe herunterladen.
- Mieter duerfen eine unterschriebene Datei nur bei `shared` hochladen.
- Reminder werden fuer Mieter auf eigene `assigned_to_id`-Zuweisungen
  reduziert; interne Vermieter-Wiedervorlagen werden nicht ausgeliefert.
- Dokument-Responses enthalten `actions` fuer Frontend-Buttons:
  `generate`, `share`, `void`, `download`, `upload_signed`,
  `download_signed` und `create_reminder`.
- Reine Mieter-Sichten bekommen keine internen Template-, Snapshot-, Storage-
  oder Creator-Felder in Dokument-, Versions- und File-Responses.
- Mietvertrags-Responses enthalten `actions` fuer `update`, `delete`,
  `create_document` und `create_payment`; interne `notes` werden fuer reine
  Mieter-Sichten nicht ausgeliefert.

Beim Erzeugen entsteht eine `DocumentVersion` mit Snapshots der Vorlage und
Mietvertragsdaten. Zusaetzlich wird eine einfache PDF-Datei als `DocumentFile`
mit `file_type=generated_pdf` gespeichert. Beim Upload wird eine Datei als
`file_type=signed_upload` an die neueste Dokumentversion gehaengt und der
Status von `Document` und `DocumentVersion` auf `signed_uploaded` gesetzt.
`void` ist final; verworfene Versionen werden nicht mehr per Download
ausgeliefert.

Reminder sind zunaechst sichtbare Workflow-Hilfen. Sie haben die Status
`pending`, `done` und `cancelled`; eine automatische Benachrichtigung per Job
ist noch nicht Teil des Pakets.

## Modulgrenzen

Das Documents-Modul sollte spaeter fachlich wiederverwendbar sein, z. B. fuer:

- Mietvertrag
- Uebergabeprotokoll
- Kuendigung
- Betriebskostenabrechnung
- Mahnung
- Vollmacht
- allgemeine Anhaenge

Deshalb sollte der Mietvertrag nicht direkt ein `pdf_path` oder alle
Dokumentdetails bekommen. Stattdessen verweist Documents polymorph auf das
fachliche Objekt.

Geplante Kernverantwortungen:

- `DocumentTemplate`: Vorlagen und Template-Metadaten
- `Document`: Dokumentakte mit Bezug auf ein fachliches Objekt
- `DocumentVersion`: erzeugte Version inklusive Snapshots
- `DocumentFile`: konkrete Dateien im Storage
- `DocumentGenerator`: erzeugt Dokumentversionen aus Vorlage und Daten
- `DocumentRenderer`: rendert HTML/PDF aus Snapshot-Daten
- `DocumentReminder`: haelt Fristen und Wiedervorlagen an der Dokumentakte

Die Rental-Agreement-Seite sollte nur eine klare Schnittstelle nutzen. Die
Workflow-Logik fuer Erzeugen, Freigeben, Verwerfen und Upload liegt im
Documents-Service; der API-Controller bleibt duenn und uebersetzt nur HTTP in
Modulaufrufe. Die konkrete PDF-Erzeugung, Storage-Pfade und Upload-Regeln
bleiben im Documents-Modul.

## Warum Snapshot?

Ein Vertragsdokument darf sich nicht rueckwirkend veraendern, nur weil spaeter
der Mietvertrag, die Vorlage oder ein Firmenlogo geaendert wird.

Darum speichert eine erzeugte Dokumentversion spaeter voraussichtlich:

- verwendete Vorlage
- Vorlagenversion
- eingefuegte Vertragsdaten
- verwendetes Branding
- erzeugte PDF-Datei
- Erstellzeitpunkt
- Benutzer, der das Dokument erzeugt hat

Wenn sich Vertragsdaten danach aendern, sollte das Frontend anzeigen, dass das
vorhandene Dokument eventuell veraltet ist und eine neue Version erzeugt werden
kann.

## Dokumentvorlagen

Vorlagen sollen spaeter nicht nur fest im Code liegen, sondern als Daten
verwaltet werden koennen. Dadurch kann man Mustermietvertraege anpassen oder
verschiedene Dokumenttypen anbieten.

Geplante Eigenschaften einer Vorlage:

- `name`, z. B. `Wohnraummietvertrag Standard`
- `document_type`, z. B. `rental_agreement_contract`, `handover_protocol`
- `template_type`, z. B. `residential`, `commercial`, `short_term`, `parking`
- `locale`, z. B. `de-DE`
- `version`
- `status`, z. B. `draft`, `active`, `archived`
- editierbarer Inhalt
- Platzhalter fuer Vertragsdaten
- optionale Hinweise fuer das Frontend

Beispiele fuer Platzhalter:

- `{{ landlord.name }}`
- `{{ tenant.name }}`
- `{{ property.address }}`
- `{{ rental_agreement.date_from }}`
- `{{ rental_agreement.rent_cold }}`
- `{{ rental_agreement.deposit }}`

Das Frontend sollte Vorlagen spaeter nicht als beliebige Datei behandeln,
sondern als editierbares Vertragsmodell mit Abschnitten. Fuer den Start reicht
aber eine einfache Text- oder HTML-basierte Bearbeitung, solange Platzhalter
klar sichtbar sind.

## Erkenntnisse aus echter Vertragsvorlage

Eine erste laengere Vorlage liegt unter
`docs/templates/wohnraummietvertrag-template.de.txt`. Sie ist bewusst als
Arbeitsvorlage gedacht: Damit sehen Backend und Frontend nicht nur abstrakte
Felder, sondern welche Daten ein echter Wohnraummietvertrag tatsaechlich
braucht.

Daraus ergeben sich klare Datenluecken und naechste Schritte:

- Bankverbindungen fehlen noch. Fuer echte Vertraege braucht es ein sauberes
  Modell fuer Zahlungsempfaenger oder Bankkonten, z. B. `account_holder`,
  `iban`, optional `bic`, `bank_name` und `is_default`.
- Die Bankverbindung sollte nicht nur als Text in der Vorlage stehen. Beim
  Erzeugen eines Dokuments muss sie wie Vertragsdaten als Snapshot gespeichert
  werden, damit alte Vertraege stabil bleiben, wenn sich spaeter ein Konto
  aendert.
- Fuer Mietvertraege sollte es optional eine vertragsbezogene Zahlungsadresse
  bzw. ein `bank_account_id` geben. Standard kann das Vermieter- oder
  Organisationskonto sein, der Vertrag darf aber bewusst ein anderes Konto
  referenzieren.
- Die Placeholder-Liste und die Snapshot-Erzeugung muessen erweitert werden,
  bevor Bankdaten in Templates als `{{ ... }}`-Platzhalter genutzt werden
  koennen.
- Objekt- und Vertragsdaten reichen fuer einen realen Vertrag noch nicht ganz:
  Wohnflaeche, Zimmer, Etage, mitvermietete Raeume/Stellplaetze,
  Uebergabeprotokoll, Schluessel, Zaehlerstaende, Anlagen, Hausordnung,
  Energieausweis und individuelle Klauseln sollten fachlich eingeordnet werden.
- Die aktuelle einfache PDF-Erzeugung reicht fuer die technische Pipeline, aber
  nicht fuer echte mehrseitige Vertragsdokumente. Fuer produktive
  Mietvertraege braucht das Documents-Modul einen robusten mehrseitigen
  HTML/PDF-Renderer.

Diese Punkte sind kein Widerspruch zum bestehenden Modell. Die Vorlage zeigt
vielmehr, welche fachlichen Daten als naechstes strukturiert werden muessen,
damit Templates spaeter professionell und wiederverwendbar bleiben.

## Branding und Logo

Wenn der Vermieter zu einer Firma oder Organisation gehoert, soll das Dokument
optional mit Branding erzeugt werden.

Geplante Branding-Daten:

- Firmenname
- Firmenadresse
- Logo
- Kontaktangaben
- optional Farben oder Briefkopf

Das Branding sollte beim Erzeugen eines Dokuments ebenfalls als Snapshot
gespeichert werden. Wenn die Organisation spaeter ein neues Logo hochlaedt,
bleiben alte Vertrags-PDFs unveraendert.

## Unterschrift im ersten Schritt

Der erste Schritt soll ohne komplizierte digitale Signatur funktionieren.

Das erzeugte PDF enthaelt klassische Unterschriftsbereiche:

- Ort und Datum fuer Vermieter
- Unterschrift Vermieter
- Ort und Datum fuer Mieter
- Unterschrift Mieter

Danach gibt es einen einfachen Ablauf:

1. Vermieter erzeugt PDF.
2. Vermieter oder Mieter laedt das PDF herunter.
3. Die Parteien unterschreiben klassisch.
4. Eine unterschriebene Version wird als PDF, Scan oder Foto hochgeladen.
5. Das System markiert das Dokument als `signed_uploaded`.

Das ist keine digitale Signatur im rechtlichen Spezial-Sinn. Es ist ein
praktischer Upload-Workflow fuer unterschriebene Dokumente.

## Spaetere digitale Signatur

Eine echte digitale Signatur sollte spaeter ueber einen externen Anbieter
angebunden werden, nicht selbst nachgebaut werden.

Moegliche Anbieter waeren z. B. DocuSign, Dropbox Sign, Yousign, Skribble oder
Adobe Sign. Der Anbieter wuerde dann Einladungen, Signaturprozess, Audit-Log und
rechtliche Anforderungen uebernehmen.

Fuer den ersten Schritt ist das bewusst nicht Teil des Umfangs.

## Geplanter Dokumentstatus

Fuer `Document` oder `DocumentVersion` sind diese Status sinnvoll:

- `draft`: Dokumentversion ist vorbereitet, aber noch nicht final erzeugt
- `generated`: PDF wurde erzeugt
- `shared`: PDF wurde fuer die andere Partei freigegeben oder versendet
- `signed_uploaded`: unterschriebene Version wurde hochgeladen
- `void`: Dokumentversion wurde verworfen oder ersetzt

Das Frontend sollte diese Status als Dokumentstatus behandeln, nicht als
`RentalAgreement.status`. Der Mietvertrag selbst kann weiterhin `draft`,
`active`, `terminated` oder `ended` sein.

## Geplanter Vermieter-Workflow

Frontend-Ablauf fuer Vermieter:

1. Mietvertrag im Status `draft` oeffnen.
2. Vertragsdaten pruefen.
3. Vorlage auswaehlen.
4. Vorschau ansehen.
5. PDF erzeugen.
6. PDF herunterladen oder teilen.
7. Unterschriebene Version hochladen.
8. Dokument als unterschrieben hochgeladen anzeigen.

Wichtig fuer die UI:

- Vor der PDF-Erzeugung sollte klar sein, welche Vorlage verwendet wird.
- Nach der PDF-Erzeugung sollte das PDF als feste Version angezeigt werden.
- Wenn Vertragsdaten danach geaendert werden, sollte ein Hinweis erscheinen,
  dass eine neue Dokumentversion erzeugt werden sollte.

## Geplanter Mieter-Workflow

Frontend-Ablauf fuer Mieter:

1. Mietvertrag ansehen.
2. Freigegebenes PDF ansehen oder herunterladen.
3. Falls erlaubt: unterschriebene Version hochladen.
4. Status der Dokumentversion sehen.

Mieter sollten Vorlagen und Vertragstexte nicht bearbeiten. Sie sehen nur die
freigegebene oder unterschrieben hochgeladene Dokumentversion und den
Upload-Status.

## Vorgeschlagene API

Diese Liste beschreibt die Richtung fuer Backend und Frontend. Metadaten-,
PDF- und Upload-Endpunkte fuer den einfachen Dokumentworkflow sind bereits
implementiert.

- `GET /document-templates`: Vorlagenliste
- `GET /document-templates/{template}`: Vorlage anzeigen
- `POST /document-templates`: implementiert fuer Admin-Vorlagenanlage
- `PUT/PATCH /document-templates/{template}`: implementiert fuer Admin-Vorlagenbearbeitung
- `POST /document-templates/{template}/activate`: implementiert fuer Aktivierung
  inklusive Archivierung konkurrierender aktiver Vorlagen
- `DELETE /document-templates/{template}`: implementiert fuer nicht aktive Vorlagen
- `POST /rental-agreements/{rentalAgreement}/documents`: implementiert fuer Dokument-Metadaten
- `GET /rental-agreements/{rentalAgreement}/documents`: implementiert fuer Dokument-Metadaten eines Vertrags
- `GET /documents/{document}`: implementiert fuer Dokument-Metadaten
- `POST /documents/{document}/generate`: implementiert fuer erste Snapshot-/PDF-Erzeugung
- `POST /documents/{document}/share`: implementiert fuer Freigabe
- `POST /documents/{document}/void`: implementiert fuer Verwerfen
- `GET /documents/{document}/download`: implementiert fuer erzeugtes PDF
- `POST /documents/{document}/signed-upload`: implementiert fuer unterschriebene Uploads
- `GET /documents/{document}/signed-download`: implementiert fuer unterschriebene Uploads
- `GET /documents/{document}/reminders`: implementiert fuer Fristen/Erinnerungen
- `POST /documents/{document}/reminders`: implementiert fuer Fristen/Erinnerungen
- `PATCH /document-reminders/{documentReminder}`: implementiert fuer Fristen/Erinnerungen
- `DELETE /document-reminders/{documentReminder}`: implementiert fuer Fristen/Erinnerungen

Fuer eine HTML-Vorschau kann spaeter zusaetzlich ein Preview-Endpunkt sinnvoll
sein:

- `POST /rental-agreements/{rentalAgreement}/document-preview`

## Frontend-Hinweise

- `openapi.yaml` beschreibt die aktuell implementierten Dokument-Endpunkte.
- Diese Datei bleibt die fachliche Roadmap fuer den weiteren Dokumentworkflow.
- Das Frontend sollte mit Dokumentversionen rechnen, nicht nur mit einem
  einzelnen PDF pro Mietvertrag.
- Das Frontend sollte unterscheiden zwischen erzeugtem Original-PDF und
  hochgeladener unterschriebener Datei.
- Das Frontend sollte nicht davon ausgehen, dass Documents nur fuer
  Mietvertraege existiert. Mietvertrag ist der erste Dokumentkontext.
- Das Frontend sollte `signed_uploaded` nicht als vollwertige digitale Signatur
  darstellen.
- Vertragstexte sollten editierbar sein, aber Platzhalter muessen fuer Benutzer
  klar erkennbar bleiben.
- Eine Template-Verwaltung sollte zunaechst Admin-only sein.
- Vermieter brauchen fuer die Dokumentanlage nur aktive Vorlagen.
- Unbekannte Platzhalter werden serverseitig abgelehnt; das Frontend sollte
  Validierungsfehler direkt an der Vorlage anzeigen.
- Beim Aktivieren einer Vorlage archiviert die API andere aktive Vorlagen
  derselben Kombination aus `document_type`, `template_type` und `locale`.
- Bereits erzeugte Dokumentversionen bleiben durch ihren `template_snapshot`
  stabil, auch wenn Vorlagen spaeter geaendert oder archiviert werden.

## Nicht im ersten Schritt

- rechtssichere digitale Signatur
- automatische Identitaetspruefung
- Signatur-Audit-Log
- Zahlungs- oder Kautionsverwaltung
- juristische Pruefung der Mustermietvertraege

## Erste Backend-Umsetzung

Die ersten Backend-Schritte sind umgesetzt:

1. Modulare Documents-Struktur im bestehenden Laravel-Projekt anlegen.
2. Tabelle und Modell fuer `DocumentTemplate`.
3. Tabelle und Modell fuer `Document`.
4. Tabellen und Modelle fuer `DocumentVersion` und `DocumentFile`.
5. Dokument-Metadaten per API an Mietvertraege haengen und abrufen.
6. Standard-Mietvertragsvorlage bereitstellen.
7. PDF-Snapshot per API erzeugen und herunterladen.
8. Unterschriebene Datei hochladen und herunterladen.
9. Dokumentworkflow fuer Freigabe, Verwerfen und Statusuebergaenge schaerfen.
10. Fristen/Erinnerungen an Dokumentakten modellieren und per API verwalten.
11. Admin-API fuer Dokumentvorlagen anlegen, bearbeiten, aktivieren, archivieren
    und loeschen.

Naechste sinnvolle Backend-Schritte:

1. Bankverbindungen bzw. Zahlungsempfaenger modellieren und in
   Mietvertrag-/Dokument-Snapshots aufnehmen.
2. Placeholder und Snapshot-Daten fuer echte Vertragsvorlagen erweitern.
3. Frontend-Hinweise fuer veraltete Dokumentversionen ermoeglichen.
4. PDF-Renderer spaeter durch eine robuste Library oder einen dedizierten Service ersetzen.
5. Faellige Reminder spaeter per Command/Job automatisch melden.

Danach kann das Frontend den einfachen Workflow bauen: Vorlage waehlen,
Vorschau ansehen, PDF erzeugen, PDF herunterladen, unterschriebene Datei
hochladen.

## Roadmap ab Paket 3

Diese Roadmap soll die naechsten Arbeitspakete festhalten, damit Backend und
Frontend denselben Stand haben. `openapi.yaml` wird jeweils nur fuer tatsaechlich
implementierte Endpunkte angepasst.

### Paket 3: PDF aus Vorlage erzeugen

Ziel: Aus einem bestehenden `Document` am Mietvertrag soll eine erste
`DocumentVersion` entstehen. Diese Version ist ein Snapshot und darf sich
spaeter nicht rueckwirkend aendern.

Umgesetzt:

- Standard-Mietvertragsvorlage als Seeder
- Daten aus `RentalAgreement`, `Property`, `landlord` und `tenant` in einen
  stabilen Snapshot uebernehmen
- `DocumentVersion` mit `version_number`, `template_snapshot`,
  `data_snapshot`, `content_snapshot`, `generated_by_id` und `generated_at`
  erzeugen
- `Document.status` und `DocumentVersion.status` auf `generated` setzen, sobald
  die Version erzeugt wurde
- PDF-Datei speichern und als `DocumentFile` mit `file_type=generated_pdf`
  verknuepfen

Implementierte API:

- `POST /documents/{document}/generate`: Version/PDF aus Vorlage erzeugen
- `GET /documents/{document}/download`: aktuell erzeugtes PDF herunterladen

Hinweis: Die erste PDF-Erzeugung ist bewusst einfach gehalten und nutzt noch
keine externe PDF-Bibliothek. Fuer komplexere Layouts sollte spaeter ein
robuster Renderer hinter den Documents-Schnitt gesetzt werden.

### Paket 4: Unterschriebenes Dokument hochladen

Ziel: Ein unterschriebenes PDF, Scan oder Foto soll zu einer vorhandenen
Dokumentversion hochgeladen werden koennen.

Umgesetzt:

- Upload validieren: PDF, JPG, JPEG, PNG bis 10 MB
- Datei im Storage ablegen
- `DocumentFile` mit `file_type=signed_upload` erzeugen
- `Document.status` und `DocumentVersion.status` auf `signed_uploaded` setzen
- Berechtigungen: Admin, berechtigter Vermieter und eigener Mieter duerfen
  unterschriebene Dateien hochladen/herunterladen

Implementierte API:

- `POST /documents/{document}/signed-upload`
- `GET /documents/{document}/signed-download`

### Paket 5: Workflow schärfen

Ziel: Dokument- und Mietvertragsstatus sollen klar zusammenspielen, ohne ihre
fachlichen Bedeutungen zu vermischen.

Umgesetzt:

- Dokumentstatus als eigene Workflow-Regeln definieren:
  `draft`, `generated`, `shared`, `signed_uploaded`, `void`
- erlaubte Dokumentstatuswechsel testen
- Verwerfen/Ersetzen alter Versionen klaeren: erneute Erzeugung verwirft die
  vorherige neueste erzeugte Version
- `void` als finaler Status; Downloads und weitere Uploads/Erzeugung werden
  blockiert

Noch offen:

- Frontend-Hinweise fuer veraltete Dokumentversionen ermoeglichen, wenn
  Vertragsdaten nach PDF-Erzeugung geaendert wurden
- Mietvertragsworkflow fuer Aktivierung, Beendigung und Kuendigung getrennt
  weiter ausbauen

Implementierte API:

- `POST /documents/{document}/void`
- `POST /documents/{document}/share`

### Paket 6: Fristen und Erinnerungen

Ziel: Relevante Termine sollen im System sichtbar und spaeter automatisiert
erinnerbar sein.

Umgesetzt:

- `DocumentReminder` als generisches Reminder-Modell an der Dokumentakte
- Felder fuer Titel, Notizen, `due_at`, optionales `remind_at`, Status,
  Zuweisung, Metadaten und Abschlusszeitpunkt
- API zum Listen, Anlegen, Aktualisieren und Loeschen
- Berechtigungen: Admin und berechtigter Vermieter verwalten; eigener Mieter
  kann lesen
- Tests fuer API, Berechtigungen, Validierung und Modellrelationen

Noch offen:

- Artisan Command oder Job fuer faellige automatische Benachrichtigungen
- fachliche Standard-Reminder, z. B. beim Teilen eines Dokuments automatisch
  eine Unterschriftsfrist anlegen

### Paket 7: Kaution und Zahlungen

Ziel: Kaution und Zahlungen sollen fachlich getrennt vom Documents-Modul
modelliert werden.

Geplanter Umfang:

- klaeren, ob es nur Kautionsstatus oder echte Zahlungsvorgaenge braucht
- Modelle fuer Kaution, Zahlungsplan oder Zahlungseintrag planen
- Rollen- und Sichtbarkeitsregeln fuer Vermieter und Mieter definieren
- keine direkte Kopplung an PDF-Dateien; Dokumente duerfen hoechstens Berichte
  oder Belege referenzieren

### Paket 8: Mieter- und Vermieter-Sichten verfeinern

Ziel: API-Responses und Berechtigungen sollen die unterschiedlichen
Frontend-Ansichten klar unterstuetzen.

Umgesetzt:

- Mieter sehen nur Dokumente eigener Mietvertraege mit Status `shared` oder
  `signed_uploaded`.
- Mieter bekommen fuer `draft`, `generated` und `void` auch bei direktem
  Dokumentabruf keinen Zugriff.
- Mieter duerfen erzeugte PDFs erst nach Freigabe herunterladen.
- Mieter duerfen eine unterschriebene Datei nur bei `shared` hochladen.
- Mieter sehen bei Dokument-Remindern nur eigene `assigned_to_id`-Zuweisungen.
- Vermieter- und Admin-Sichten bleiben vollstaendige Arbeitsakten.
- Reine Mieter-Sichten bekommen keine internen Template-, Snapshot-, Storage-
  oder Creator-Felder in Dokument-, Versions- und File-Responses.
- Mietvertrags-Responses blenden interne `notes` fuer reine Mieter-Sichten aus.
- Dokument-Responses liefern `actions` fuer rollen- und statusabhaengige
  Frontend-Buttons.
- Mietvertrags-Responses liefern `actions` fuer wiederkehrende
  Verwaltungsbuttons.

Weiterer geplanter Umfang:

- Listenfilter und Response-Includes fuer wiederkehrende Frontend-Ansichten
  schaerfen
- OpenAPI-Beispiele fuer typische Vermieter- und Mieter-Responses ergaenzen,
  wenn die Endpunkte stabil sind

## Frontend-Uebergabe

Fuer die Uebergabe ans Frontend gelten diese Quellen:

- `docs/openapi.yaml`: technische Wahrheit fuer implementierte Endpunkte,
  Request-Body, Response-Body und Fehlercodes
- `docs/api-overview.md`: Rollen, Berechtigungen und fachliche Kurzfassung
- diese Datei: geplanter Dokumentworkflow, Roadmap und fachliche Entscheidungen

Wichtig: Roadmap-Endpunkte duerfen im Frontend nicht als implementiert behandelt
werden, solange sie nicht in `openapi.yaml` stehen.

## Spaetere Herausloesbarkeit

Damit Documents spaeter in ein eigenes Repository verschoben werden kann,
sollten diese Regeln gelten:

- Documents hat eigene Models, Services, Policies und Tests.
- Andere Domains greifen ueber klare Services oder Actions auf Documents zu.
- Storage-Pfade, PDF-Renderer und Upload-Regeln sind im Documents-Modul
  gekapselt.
- Dokumente referenzieren fachliche Objekte generisch ueber `documentable`.
- Rental Agreements speichern keine direkten PDF-Interna.
- Events koennen spaeter helfen, harte Kopplung zu reduzieren, z. B.
  `RentalAgreementDocumentRequested` oder `DocumentGenerated`.
- Externe Signaturanbieter werden ueber ein Interface angebunden, nicht direkt
  in Controller oder Models eingebaut.

So bleibt die erste Umsetzung pragmatisch im Monolith, aber das Modul hat einen
klaren Rand fuer eine spaetere Extraktion.

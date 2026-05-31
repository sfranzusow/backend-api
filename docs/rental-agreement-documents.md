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
  `/rental-agreements/{rental_agreement}/documents`, intern sollte das Dokument
  aber generisch modelliert werden.

Der Schnitt ist bewusst modular, aber noch kein Microservice. Es ist ein
interner Modul-Schnitt im Monolith.

## Grundidee

`RentalAgreement` bleibt der strukturierte Mietvertrag mit Daten wie Objekt,
Vermieter, Mieter, Laufzeit, Miete, Nebenkosten, Kaution und Status.

Zusatzlich soll es generische Dokument-Konzepte geben:

- `DocumentTemplate`: editierbare Vorlage fuer Dokumente
- `DocumentLayoutTemplate`: optionaler Header/Footer/Briefkopf pro Owner und
  Dokumenttyp
- `Document`: konkrete Dokumentakte zu einem fachlichen Objekt
- `DocumentVersion`: konkrete erzeugte Version, z. B. ein PDF-Snapshot
- `DocumentFile`: gespeicherte Datei, z. B. Original-PDF oder unterschriebener Upload
- `Reminder`: generische Aufgabe/Erinnerung an einem Vorgang, z. B. Dokument,
  Mietvertrag oder Zahlung

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
freigegeben oder verworfen werden. Fristen und Erinnerungen koennen an ein
Dokument, einen Mietvertrag oder eine Zahlung gehaengt werden. Die technische
API ist in `openapi.yaml`
dokumentiert.

Angelegt sind:

- `document_templates`: Vorlagen mit `name`, `document_type`,
  `template_type`, `locale`, `version`, `status`, `content`, `placeholders`,
  `metadata` und optionalem `created_by_id`
- `documents`: generische Dokumentakte mit polymorphem `documentable`,
  optionaler Vorlage, `document_type`, Dokumentstatus, Titel und Metadaten
- `document_versions`: versionierte Snapshots mit `version_number`, Status,
  Inhaltssnapshot, Template-Snapshot, optionalem Layout-Snapshot,
  Datensnapshot, Metadaten, `generated_by_id` und `generated_at`
- `document_layout_templates`: optionale Header-/Footer-Layouts fuer
  Organisationen oder einzelne Vermieter, mit Inhalt, Banner-Pfaden,
  Seitenzahlsteuerung, Status, Version und Platzhaltern
- `document_files`: Storage-Metadaten fuer Dateien einer Dokumentversion,
  z. B. erzeugtes PDF, unterschriebener Upload oder Anhang
- `reminders`: polymorphe Aufgaben/Erinnerungen mit `remindable_type`,
  `remindable_id`, `due_at`, optionalem `remind_at`, Status, Zuweisung und
  Metadaten

`RentalAgreement` hat eine polymorphe `documents`-Relation. Dadurch kann ein
Mietvertrag Dokumente bekommen, ohne PDF-Pfade oder Dokument-Interna direkt im
Mietvertrag zu speichern.

Aktuell implementierte Endpunkte:

- `GET /bank-accounts`: Bankkonten/Zahlungsempfaenger listen
- `POST /bank-accounts`: Bankkonto/Zahlungsempfaenger anlegen
- `GET /bank-accounts/{bank_account}`: Bankkonto/Zahlungsempfaenger anzeigen
- `PUT/PATCH /bank-accounts/{bank_account}`: Bankkonto/Zahlungsempfaenger aktualisieren
- `DELETE /bank-accounts/{bank_account}`: Bankkonto/Zahlungsempfaenger loeschen
- `GET /rental-agreements/{rental_agreement}/documents`: Dokumente eines Mietvertrags listen
- `POST /rental-agreements/{rental_agreement}/documents`: Dokumentakte im Status `draft` am Mietvertrag anlegen
- `GET /rental-agreements/{rental_agreement}/payments`: Zahlungen eines Mietvertrags listen
- `POST /rental-agreements/{rental_agreement}/payments`: Zahlung am Mietvertrag anlegen
- `GET /payments/{payment}`: einzelne Zahlung anzeigen
- `PATCH /payments/{payment}`: Zahlung aktualisieren
- `DELETE /payments/{payment}`: Zahlung loeschen
- `GET /documents/{document}`: einzelne Dokument-Metadaten anzeigen
- `POST /documents/{document}/generate`: Snapshot-Version und PDF aus Vorlage erzeugen
- `POST /documents/{document}/share`: erzeugte Dokumentversion freigeben
- `POST /documents/{document}/void`: Dokument und neueste Version verwerfen
- `GET /documents/{document}/download`: aktuell erzeugtes PDF herunterladen
- `POST /documents/{document}/signed-upload`: unterschriebene Datei hochladen
- `GET /documents/{document}/signed-download`: unterschriebene Datei herunterladen
- `GET /documents/{document}/reminders`: Aufgaben/Erinnerungen eines Dokuments listen
- `POST /documents/{document}/reminders`: Aufgabe/Erinnerung am Dokument anlegen
- `GET /rental-agreements/{rental_agreement}/reminders`: Aufgaben/Erinnerungen eines Mietvertrags listen
- `POST /rental-agreements/{rental_agreement}/reminders`: Aufgabe/Erinnerung am Mietvertrag anlegen
- `GET /payments/{payment}/reminders`: Aufgaben/Erinnerungen einer Zahlung listen
- `POST /payments/{payment}/reminders`: Aufgabe/Erinnerung an einer Zahlung anlegen
- `GET /reminders/summary`: persönliche Dashboard-Zähler nach Vorgangsart und Status
- `GET /reminders`: persönliche, filterbare Reminder-Inbox über alle Vorgänge
- `PATCH /reminders/{reminder}`: Frist/Erinnerung aktualisieren
- `DELETE /reminders/{reminder}`: Frist/Erinnerung loeschen

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
  reduziert; interne Vermieter-Erinnerungen werden nicht ausgeliefert.
- Dokument-Responses enthalten `actions` fuer Frontend-Buttons:
  `generate`, `share`, `void`, `download`, `upload_signed`,
  `download_signed` und `create_reminder`.
- Reine Mieter-Sichten bekommen keine internen Template-, Snapshot-, Storage-
  oder Creator-Felder in Dokument-, Versions- und File-Responses.
- Mietvertrags-Responses enthalten `actions` fuer `update`, `delete`,
  `create_document` und `create_payment`; interne `notes` werden fuer reine
  Mieter-Sichten nicht ausgeliefert.
- Dokument-Responses enthalten `snapshot_status`. Das Feld zeigt dem Frontend,
  ob die neueste nutzbare Dokumentversion `current`, `outdated`,
  `not_generated` oder `unknown` ist. `outdated` bedeutet: Vertrags-, Objekt-,
  Parteien-, Organisations- oder Bankdaten wurden nach der PDF-Erzeugung
  geaendert.

Beim Erzeugen entsteht eine `DocumentVersion` mit Snapshots der Vorlage und
Mietvertragsdaten. Falls fuer den Vermieter ein aktives Layout existiert,
wird dieses ebenfalls als `layout_snapshot` eingefroren. Zusaetzlich wird eine
PDF-Datei als `DocumentFile` mit `file_type=generated_pdf` gespeichert. Beim
Upload wird eine Datei als `file_type=signed_upload` an die neueste
Dokumentversion gehaengt und der Status von `Document` und `DocumentVersion`
auf `signed_uploaded` gesetzt. `void` ist final; verworfene Versionen werden
nicht mehr per Download ausgeliefert.

Reminder sind zunaechst sichtbare Workflow-Hilfen. Sie haben die Status
`pending`, `done` und `cancelled`; fuer die Oberflaeche wird daraus
`display_status` mit `open`, `reminder_due`, `overdue`, `done` oder
`cancelled` abgeleitet. `GET /reminders/summary` zaehlt die dem aktuellen
Benutzer zugewiesenen Reminder fuer Dashboard-Kacheln, `GET /reminders`
liefert die klickbare Liste dazu. Eine automatische Benachrichtigung per Job
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
- `DocumentLayoutTemplate`: optionale Briefkopf-, Header- und Footer-Layouts
  pro Organisation oder Vermieter
- `Document`: Dokumentakte mit Bezug auf ein fachliches Objekt
- `DocumentVersion`: erzeugte Version inklusive Snapshots
- `DocumentFile`: konkrete Dateien im Storage
- `DocumentGenerator`: erzeugt Dokumentversionen aus Vorlage und Daten
- `DocumentRenderer`: rendert HTML/PDF aus Snapshot-Daten
- `Reminder`: haelt Fristen und Erinnerungen an Dokumenten, Mietvertraegen oder Zahlungen

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
- `{{ bank_account.account_holder }}`
- `{{ bank_account.iban }}`

Das Frontend kann die erlaubten Platzhalter pro Dokumenttyp ueber
`GET /document-template-placeholders?document_type=rental_agreement_contract`
abrufen. Die Antwort liefert neben dem technischen Pfad auch Label, Gruppe,
Typ, Nullable-Information und ein einfuegbares Beispiel wie
`{{ rental_agreement.notes }}`. Damit bleibt das Backend die Quelle der
Wahrheit fuer Template-Editor, Autocomplete und serverseitige Validierung.

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

- Bankverbindungen sind jetzt als `bank_accounts` modelliert. Ein Konto gehoert
  entweder zu einem Benutzer oder zu einer Organisation und enthaelt
  `account_holder`, `iban`, optional `bic`, `bank_name` und `is_default`.
- Mietvertraege koennen optional `bank_account_id` referenzieren. Dieses Konto
  muss zum Vermieter oder dessen Organisation gehoeren.
- Beim Erzeugen eines Dokuments werden Bankdaten wie Vertragsdaten als Snapshot
  gespeichert, damit alte Vertraege stabil bleiben, wenn sich spaeter ein Konto
  aendert.
- Die Placeholder-Liste und Snapshot-Erzeugung enthalten Bankdaten, z. B.
  `bank_account.account_holder`, `bank_account.iban`, `bank_account.bic` und
  `bank_account.bank_name`.
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

Umgesetzt ist dafuer `DocumentLayoutTemplate`. Ein Layout gehoert entweder zu
einer Organisation (`owner_type=organization`) oder zu einem einzelnen
Vermieter (`owner_type=user`) und gilt fuer einen `document_type`, z. B.
`rental_agreement_contract`.

Aktuelle Layout-Daten:

- `header_enabled` und `footer_enabled`
- `header_content` und `footer_content` mit Platzhaltern wie
  `{{ organization.name }}`, `{{ landlord.name }}` oder
  `{{ document.version_number }}`
- optionale `header_banner_path` und `footer_banner_path`
- `page_numbers_enabled` fuer `Seite X von Y` im Footer
- `status`, `version`, `locale`, `metadata` und automatisch extrahierte
  `placeholders`

Beim Generieren sucht das Backend zuerst ein aktives Organisationslayout des
Vermieters und danach ein aktives persoenliches Layout. Wenn kein aktives
Layout vorhanden ist oder Header/Footer deaktiviert sind, wird dieser Bereich
nicht ins PDF gerendert. Das verwendete Layout wird als `layout_snapshot`
gespeichert. Wenn die Organisation spaeter den Briefkopf aendert, bleiben alte
Vertrags-PDFs unveraendert.

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
- `GET /document-template-placeholders?document_type=rental_agreement_contract`:
  implementiert fuer Platzhalter-Metadaten je Dokumenttyp
- `GET /document-templates/{document_template}`: Vorlage anzeigen
- `POST /document-templates`: implementiert fuer Admin-Vorlagenanlage
- `PUT/PATCH /document-templates/{document_template}`: implementiert fuer Admin-Vorlagenbearbeitung
- `POST /document-templates/{document_template}/activate`: implementiert fuer Aktivierung
  inklusive Archivierung konkurrierender aktiver Vorlagen
- `DELETE /document-templates/{document_template}`: implementiert fuer nicht aktive Vorlagen
- `GET /document-layout-templates`: implementiert fuer Layout-Verwaltung
- `POST /document-layout-templates`: implementiert fuer Layout-Anlage
- `GET /document-layout-templates/{document_layout_template}`: implementiert fuer einzelne Layouts
- `PUT/PATCH /document-layout-templates/{document_layout_template}`: implementiert fuer Layout-Bearbeitung
- `POST /document-layout-templates/{document_layout_template}/activate`: implementiert fuer Aktivierung
  inklusive Archivierung konkurrierender aktiver Layouts
- `DELETE /document-layout-templates/{document_layout_template}`: implementiert fuer nicht aktive Layouts
- `POST /rental-agreements/{rental_agreement}/documents`: implementiert fuer Dokument-Metadaten
- `GET /rental-agreements/{rental_agreement}/documents`: implementiert fuer Dokument-Metadaten eines Vertrags
- `GET /rental-agreements/{rental_agreement}/payments`: implementiert fuer Zahlungen eines Vertrags
- `POST /rental-agreements/{rental_agreement}/payments`: implementiert fuer Zahlungen eines Vertrags
- `GET /payments/{payment}`: implementiert fuer einzelne Zahlungen
- `PATCH /payments/{payment}`: implementiert fuer Zahlungsaktualisierung
- `DELETE /payments/{payment}`: implementiert fuer Zahlungsloeschung
- `GET /documents/{document}`: implementiert fuer Dokument-Metadaten
- `POST /documents/{document}/generate`: implementiert fuer erste Snapshot-/PDF-Erzeugung
- `POST /documents/{document}/share`: implementiert fuer Freigabe
- `POST /documents/{document}/void`: implementiert fuer Verwerfen
- `GET /documents/{document}/download`: implementiert fuer erzeugtes PDF
- `POST /documents/{document}/signed-upload`: implementiert fuer unterschriebene Uploads
- `GET /documents/{document}/signed-download`: implementiert fuer unterschriebene Uploads
- `GET /documents/{document}/reminders`: implementiert fuer Fristen/Erinnerungen
- `POST /documents/{document}/reminders`: implementiert fuer Fristen/Erinnerungen
- `GET /rental-agreements/{rental_agreement}/reminders`: implementiert fuer Fristen/Erinnerungen
- `POST /rental-agreements/{rental_agreement}/reminders`: implementiert fuer Fristen/Erinnerungen
- `GET /payments/{payment}/reminders`: implementiert fuer Fristen/Erinnerungen
- `POST /payments/{payment}/reminders`: implementiert fuer Fristen/Erinnerungen
- `GET /reminders/summary`: implementiert fuer persoenliche Dashboard-Zaehler
- `GET /reminders`: implementiert fuer persoenliche Reminder-Inbox
- `PATCH /reminders/{reminder}`: implementiert fuer Fristen/Erinnerungen
- `DELETE /reminders/{reminder}`: implementiert fuer Fristen/Erinnerungen

Fuer eine HTML-Vorschau kann spaeter zusaetzlich ein Preview-Endpunkt sinnvoll
sein:

- `POST /rental-agreements/{rental_agreement}/document-preview`

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
- Eine Layout-Verwaltung kann Admins und Vermietern angeboten werden:
  Admins sehen alle Layouts, Vermieter nur Layouts der eigenen Organisation
  oder eigene persoenliche Layouts.
- Das Frontend sollte bei Layouts `owner_type` (`organization` oder `user`)
  und `owner_id` speichern. Wenn ein Vermieter beim Anlegen keine Owner-Felder
  sendet, setzt das Backend automatisch die eigene Organisation oder den
  Vermieter selbst.
- Header/Footer sind optional. Ohne aktives Layout oder bei deaktiviertem
  Header/Footer wird kein Bereich gerendert; das Frontend muss also keinen
  leeren Briefkopf simulieren.
- Layouts verwenden dieselbe Placeholder-Liste wie Dokumentvorlagen. Fuer
  Briefkopf/Fusszeile sind besonders `organization.*`, `landlord.*`,
  `document.title`, `document.version_number` und `document.generated_at`
  relevant.
- Vermieter brauchen fuer die Dokumentanlage nur aktive Vorlagen.
- Unbekannte Platzhalter werden serverseitig abgelehnt; das Frontend sollte
  Validierungsfehler direkt an der Vorlage anzeigen.
- Beim Aktivieren einer Vorlage archiviert die API andere aktive Vorlagen
  derselben Kombination aus `document_type`, `template_type` und `locale`.
- Beim Aktivieren eines Layouts archiviert die API andere aktive Layouts
  desselben Owners fuer dieselbe Kombination aus `document_type` und `locale`.
- Bereits erzeugte Dokumentversionen bleiben durch ihren `template_snapshot`
  und optionalen `layout_snapshot` stabil, auch wenn Vorlagen oder Layouts
  spaeter geaendert oder archiviert werden.

## Nicht im ersten Schritt

- rechtssichere digitale Signatur
- automatische Identitaetspruefung
- Signatur-Audit-Log
- echte Zahlungsabwicklung, Kautionskonto-Fuehrung oder Banking-Integration
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
10. Fristen/Erinnerungen an Dokumenten, Mietvertraegen und Zahlungen modellieren und per API verwalten.
11. Admin-API fuer Dokumentvorlagen anlegen, bearbeiten, aktivieren, archivieren
    und loeschen.
12. Header-/Footer-Layouts fuer Organisationen und Vermieter modellieren,
    verwalten und beim PDF-Snapshot optional rendern.

Naechste sinnvolle Backend-Schritte:

1. Such- und Listenfilter fuer operative Frontend-Ansichten ausbauen:
   Mietvertraege nach Mietername oder Objektadresse, Objekte nach Adresse,
   sowie Zahlungen global und nach `paid_at` filtern.
2. Weitere Placeholder und Snapshot-Daten fuer echte Vertragsvorlagen erweitern.
3. PDF-Renderer spaeter durch eine robuste Library oder einen dedizierten Service ersetzen.
4. Faellige Reminder spaeter per Command/Job automatisch melden.

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

- Mietvertragsworkflow fuer Aktivierung, Beendigung und Kuendigung getrennt
  weiter ausbauen

Implementiert fuer Frontend-Hinweise:

- `Document.snapshot_status.state` ist `not_generated`, `current`,
  `outdated` oder `unknown`.
- `Document.snapshot_status.is_outdated=true` bedeutet, dass seit
  `latest_version.generated_at` mindestens eine Snapshot-Quelle neuer ist.
- Gepruefte Snapshot-Quellen fuer Mietvertragsdokumente sind aktuell:
  `RentalAgreement`, `Property`, `Address`, Vermieter, Vermieter-Organisation,
  Mieter und `BankAccount`.
- Das Frontend sollte bei `outdated` eine unaufdringliche Warnung anzeigen,
  z. B. "Vertragsdaten wurden seit der PDF-Erzeugung geaendert". Einen Button
  zur Neuerzeugung nur anzeigen, wenn `actions.generate=true` ist.

Implementierte API:

- `POST /documents/{document}/void`
- `POST /documents/{document}/share`

### Paket 6: Fristen und Erinnerungen

Ziel: Relevante Termine sollen im System sichtbar und spaeter automatisiert
erinnerbar sein.

Umgesetzt:

- `Reminder` als generisches Reminder-Modell an Dokument, Mietvertrag oder Zahlung
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

Umgesetzt:

- `Payment` als generische Zahlungsstruktur fuer Miete, Kaution,
  Kautionsrueckzahlung, Nebenkosten und sonstige Zahlungen.
- Zahlungen haengen polymorph an einem Vorgang; fuer Mietvertraege aktuell an
  `RentalAgreement`.
- `GET /rental-agreements/{rental_agreement}/payments` und
  `POST /rental-agreements/{rental_agreement}/payments` sind implementiert.
- `GET /payments/{payment}`, `PATCH /payments/{payment}` und
  `DELETE /payments/{payment}` sind implementiert.
- Rollen- und Sichtbarkeitsregeln fuer Vermieter, Mieter und Admins sind
  umgesetzt.
- Zahlungen bleiben fachlich getrennt vom Documents-Modul; Dokumente duerfen
  spaeter Belege oder Berichte referenzieren, koppeln aber nicht direkt an die
  Zahlungslogik.

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
- Mieter sehen bei Remindern nur eigene `assigned_to_id`-Zuweisungen an
  Vorgaengen, die fuer sie sichtbar sind.
- Vermieter- und Admin-Sichten bleiben vollstaendige Arbeitsakten.
- Reine Mieter-Sichten bekommen keine internen Template-, Snapshot-, Storage-
  oder Creator-Felder in Dokument-, Versions- und File-Responses.
- Mietvertrags-Responses blenden interne `notes` fuer reine Mieter-Sichten aus.
- Dokument-Responses liefern `actions` fuer rollen- und statusabhaengige
  Frontend-Buttons.
- Mietvertrags-Responses liefern `actions` fuer wiederkehrende
  Verwaltungsbuttons.
- Dokumentlisten am Mietvertrag koennen nach `status` und `document_type`
  gefiltert werden.
- `GET /rental-agreements/{rental_agreement}/documents?include=reminders`
  liefert Reminder gezielt fuer Frontend-Listenkarten mit. Fuer reine
  Mieter-Sichten werden dabei nur eigene `assigned_to_id`-Zuweisungen
  ausgeliefert.
- Mietvertragslisten koennen nach Vertragsbeginn ueber `starts_from` und
  `starts_until` gefiltert werden und mit
  `include=documents,payments,reminders` typische Uebersichts-Karten in einer
  Antwort bedienen.
- Zahlungslisten am Mietvertrag koennen nach `due_from` und `due_until`
  gefiltert werden und mit `include=reminders` faellige Zahlungserinnerungen
  fuer Listenansichten mitliefern.

Weiterer geplanter Umfang:

- OpenAPI-Beispiele fuer typische Vermieter- und Mieter-Responses ergaenzen,
  wenn die Endpunkte stabil sind

### Paket 9: Bankverbindungen und Zahlungsempfaenger

Ziel: Mietvertragsdokumente sollen echte Zahlungsdaten aus strukturierten
Backend-Daten erhalten, ohne IBAN/BIC als freien Template-Text zu pflegen.

Umgesetzt:

- `BankAccount` als Zahlungsempfaenger fuer Benutzer oder Organisationen
- Admin-/Landlord-API zum Listen, Anlegen, Anzeigen, Aktualisieren und Loeschen
- `is_default` pro Besitzer, wobei neue Defaults andere Default-Konten
  desselben Besitzers deaktivieren
- optionale `bank_account_id` am Mietvertrag
- Validierung, dass ein Mietvertragskonto zum Vermieter oder dessen
  Organisation gehoert
- Dokument-Snapshot mit `bank_account.account_holder`, `iban`, `bic` und
  `bank_name`
- Placeholder-Whitelist fuer Bankkonto-Platzhalter erweitert

Noch offen:

- Frontend-Auswahl und Vorbelegung fuer Bankkonten
- Produktentscheidung, ob beim Anlegen eines Mietvertrags automatisch ein
  Default-Konto vorausgewaehlt oder nur frontendseitig vorgeschlagen wird

### Paket 10: Suche und operative Listenfilter

Ziel: Frontend-Ansichten sollen nicht nur ueber bekannte IDs navigieren,
sondern typische Arbeitsfragen direkt beantworten koennen: "Welcher
Mietvertrag gehoert zu diesem Objekt?", "Welche Verträge hat Mieter Max
Mustermann?", "Welche Zahlungen waren im Juni faellig oder bezahlt?".

Aktueller Stand:

- Mietvertraege koennen nach `status`, `property_id`, `landlord_id`,
  `tenant_id`, `starts_from` und `starts_until` gefiltert werden.
- Zahlungen koennen innerhalb eines Mietvertrags nach `type`, `direction`,
  `status`, `due_from` und `due_until` gefiltert werden.
- Benutzer koennen in der Admin-/User-Verwaltung nach `name`, `email`,
  `phone_number`, `organization_id` und `role` gefiltert werden.
- Objekte koennen nach `status`, `type` und `address_id` gefiltert werden.

Geplanter Backend-Umfang:

- `GET /rental-agreements` um direkte Suchfilter erweitern, z. B.
  `tenant_name`, `property_q` oder ein allgemeines `q`.
- `GET /properties` um Adress-/Einheitensuche erweitern, z. B. Strasse,
  Hausnummer, PLZ, Stadt und `unit_number`.
- Optional eine globale Zahlungsliste `GET /payments` fuer Dashboard und
  Buchhaltungsansichten anbieten; Filter: `property_id`, `tenant_id`,
  `rental_agreement_id`, `type`, `status`, `due_from`, `due_until`,
  `paid_from`, `paid_until`.
- Zahlungslisten um `paid_at`-Filter ergaenzen.
- OpenAPI erst erweitern, wenn die Endpunkte tatsaechlich implementiert sind.

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

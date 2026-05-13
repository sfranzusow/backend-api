# Documents-Modul: Mietvertragsdokumente und PDF-Workflow

Dieses Dokument beschreibt das geplante Documents-Modul am ersten Anwendungsfall
Mietvertragsdokumente. Es ist eine Produkt- und Frontend-Uebergabe, keine
Beschreibung bereits implementierter API-Endpunkte. Die aktuelle technische API
bleibt `docs/openapi.yaml`.

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

Ein Mietvertragsdokument ist dann nur ein Spezialfall:

- `documentable_type`: `RentalAgreement`
- `documentable_id`: ID des Mietvertrags
- `document_type`: z. B. `rental_agreement_contract`
- `template_type`: z. B. `rental_agreement`

Die Vorlage ist flexibel und kann Text, Abschnitte, Platzhalter und spaeter
optionale Klauseln enthalten. Eine erzeugte Dokumentversion ist ein Snapshot:
Wenn ein PDF erzeugt wird, bleiben Vorlage, Vertragsdaten und Branding in dieser
Version eingefroren.

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

Die Rental-Agreement-Seite sollte nur eine klare Schnittstelle nutzen, z. B.
`DocumentGenerator::generateFor($rentalAgreement, $template)`. Die konkrete
PDF-Erzeugung, Storage-Pfade und Upload-Regeln bleiben im Documents-Modul.

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
2. Zugehoeriges PDF ansehen oder herunterladen.
3. Falls erlaubt: unterschriebene Version hochladen.
4. Status der Dokumentversion sehen.

Mieter sollten Vorlagen und Vertragstexte nicht bearbeiten. Sie sehen nur die
finalisierte Dokumentversion und den Upload-Status.

## Vorgeschlagene API

Diese Endpunkte sind noch nicht implementiert. Sie beschreiben die geplante
Richtung fuer Backend und Frontend.

- `GET /document-templates`: Vorlagenliste
- `GET /document-templates/{template}`: Vorlage anzeigen
- `POST /rental-agreements/{rentalAgreement}/documents`: Dokumentversion/PDF erzeugen
- `GET /rental-agreements/{rentalAgreement}/documents`: Dokumentversionen eines Vertrags
- `GET /documents/{document}`: Dokument-Metadaten anzeigen
- `GET /documents/{document}/download`: erzeugtes PDF herunterladen
- `POST /documents/{document}/signed-upload`: unterschriebene Version hochladen
- `GET /documents/{document}/signed-download`: unterschriebene Version herunterladen
- `POST /documents/{document}/void`: Dokumentversion verwerfen

Fuer eine HTML-Vorschau kann spaeter zusaetzlich ein Preview-Endpunkt sinnvoll
sein:

- `POST /rental-agreements/{rentalAgreement}/document-preview`

## Frontend-Hinweise

- `openapi.yaml` soll erst angepasst werden, wenn die Endpunkte implementiert
  sind.
- Diese Datei ist bis dahin die fachliche Roadmap fuer die Frontend-Planung.
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

## Nicht im ersten Schritt

- rechtssichere digitale Signatur
- automatische Identitaetspruefung
- Signatur-Audit-Log
- Zahlungs- oder Kautionsverwaltung
- juristische Pruefung der Mustermietvertraege

## Erste Backend-Umsetzung

Ein sinnvoller erster Backend-Schritt waere:

1. Modulare Documents-Struktur im bestehenden Laravel-Projekt anlegen.
2. Tabelle und Modell fuer `DocumentTemplate`.
3. Tabelle und Modell fuer `Document`.
4. Tabelle und Modell fuer `DocumentVersion` oder `DocumentFile`.
5. Ein Standard-Mustermietvertrag als Seed-Daten.
6. PDF-Erzeugung aus Vorlage und Mietvertragsdaten.
7. Download-Endpunkt fuer erzeugte PDFs.
8. Upload-Endpunkt fuer unterschriebene Versionen.

Danach kann das Frontend den einfachen Workflow bauen: Vorlage waehlen,
Vorschau ansehen, PDF erzeugen, PDF herunterladen, unterschriebene Datei
hochladen.

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

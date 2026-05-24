# API-Ueberblick

Dieses Dokument beschreibt die API in menschlicher Sprache.
Die technische Detailquelle bleibt [openapi.yaml](/home/slavik/project/backend-api/docs/openapi.yaml:1), aber hier steht kurz und klar, was die API fachlich tut und welche Rolle was darf.

## Grundidee

Die API verwaltet eine kleine Immobilien-Domaene mit diesen Kernobjekten:

- `users`: Benutzerkonten und Rollen
- `organizations`: Organisationen wie Verwaltungen oder Firmen, denen Benutzer optional zugeordnet sind
- `bank_accounts`: Bankverbindungen/Zahlungsempfaenger von Benutzern oder Organisationen
- `addresses`: Postadressen
- `properties`: Immobilien bzw. Einheiten
- `property_user`: Zuordnung von Benutzern zu Objekten mit Rollen wie `landlord`, `tenant`, `manager`
- `rental_agreements`: echte Mietvertraege zwischen Vermieter und Mieter
- `document_templates`, `documents`, `document_versions`, `document_files`, `document_reminders`: interne Documents-Datenbasis fuer Vertragsdokumente, Dateien und Fristen
- `payments`: generische Zahlungen/Forderungen, z. B. Miete, Kaution und Kautionsrueckzahlung

Wichtig ist die Trennung zwischen:

- `property_user`: beschreibt die Beziehung eines Benutzers zu einem Objekt
- `rental_agreements`: beschreibt einen konkreten Mietvertrag

Ein Benutzer kann also einem Objekt zugeordnet sein, ohne dass das allein schon den Mietvertrag ersetzt.

## Authentifizierung

- `POST /login`: meldet einen Benutzer an und gibt ein Sanctum-Token zurueck
- `POST /logout`: widerruft den aktuellen Token
- Alle anderen API-Endpunkte ausser `/ping` und `/login` brauchen einen gueltigen Bearer-Token

## Rollen

Aktuell gibt es diese Rollen:

- `admin`
- `landlord`
- `tenant`
- `user`

Die Rollen steuern jetzt `users`, `properties`, `addresses` und `rental-agreements` ueber Policies und serverseitige Filter.

## Was die API macht

### Health und Login

- `GET /ping`: einfacher Gesundheitscheck
- `POST /login`: Login mit E-Mail und Passwort
- `POST /logout`: Logout des aktuellen Tokens
- `GET /user`: Profil des aktuell eingeloggten Benutzers

### Benutzerverwaltung

Die Benutzer-API dient zur Verwaltung von Accounts und Rollen.
Sie enthaelt jetzt auch einfache Kontaktdaten: Telefonnummer, aktuelle Adresse und optional eine Organisation.

- `GET /users`: Benutzerliste
- `POST /users`: neuen Benutzer anlegen
- `GET /users/{user}`: einzelnen Benutzer ansehen
- `PUT/PATCH /users/{user}`: Benutzerdaten aktualisieren
- `DELETE /users/{user}`: Benutzer loeschen

Besonderheiten:

- Beim Anlegen eines Users wird ohne Rollenangabe automatisch die Rolle `user` gesetzt.
- Beim Anlegen und durch administrative User-Verwaltung kann `organization_id` gesetzt werden.
- Benutzer koennen im eigenen Profil Kontaktdaten wie `phone_number` und aktuelle Adressfelder pflegen.
- Ein Benutzer darf sein eigenes Profil aktualisieren, wenn seine Rolle das erlaubt.
- Eigene Rollen duerfen nicht ueber die API geaendert werden.
- Admins duerfen fast alles, koennen aber nicht den eigenen Account loeschen oder die eigenen Rollen aendern.

### Adressen

Die Adress-API verwaltet Postadressen.

- `GET /addresses`: Liste mit optionalen Filtern wie `city`, `country`, `per_page`
- `POST /addresses`: neue Adresse anlegen
- `GET /addresses/{address}`: einzelne Adresse ansehen
- `PUT/PATCH /addresses/{address}`: Adresse aendern
- `DELETE /addresses/{address}`: Adresse loeschen

Aktueller Stand:

- `admin` darf alle Adressen sehen und bearbeiten
- `landlord` darf Adressen anlegen und nur die Adressen eigener Objekte sehen, aendern und loeschen
- `tenant` und `user` duerfen keine Adressen ueber die API sehen

### Bankkonten / Zahlungsempfaenger

Die Bankkonto-API verwaltet Zahlungsempfaenger fuer Mietvertraege und
Dokument-Snapshots.

- `GET /bank-accounts`: Bankkonten listen
- `POST /bank-accounts`: Bankkonto anlegen
- `GET /bank-accounts/{bank_account}`: Bankkonto anzeigen
- `PUT/PATCH /bank-accounts/{bank_account}`: Bankkonto aktualisieren
- `DELETE /bank-accounts/{bank_account}`: Bankkonto loeschen

Ein Bankkonto gehoert genau zu einem Besitzer:

- entweder `user_id`
- oder `organization_id`

Aktueller Stand:

- `admin` darf alle Bankkonten sehen und verwalten
- `landlord` darf Bankkonten des eigenen Benutzers oder der eigenen
  Organisation sehen und verwalten
- `tenant` und `user` haben keinen Zugriff auf die Bankkonto-Verwaltung
- `iban` und `bic` werden normalisiert und gross geschrieben
- `is_default=true` deaktiviert andere Default-Konten desselben Besitzers
- beim Loeschen eines Bankkontos wird die Live-Referenz am Mietvertrag auf
  `null` gesetzt; erzeugte Dokumentversionen behalten ihre Snapshot-Daten

### Objekte / Immobilien

Die Property-API ist der Kern der Immobilienlogik.

- `GET /properties`: Objektliste mit Filtern wie `status`, `type`, `address_id`
- `POST /properties`: neues Objekt anlegen
- `GET /properties/{property}`: einzelnes Objekt ansehen
- `PUT/PATCH /properties/{property}`: Objekt aendern
- `DELETE /properties/{property}`: Objekt loeschen
- `PUT /properties/{property}/members`: Mitglieder und Rollen eines Objekts synchronisieren

Ein Objekt gehoert genau zu einer Adresse.
Ein Objekt kann mehrere zugeordnete Benutzer haben, zum Beispiel als Vermieter oder Mieter.

### Mietvertraege

Die Rental-Agreement-API verwaltet echte Mietvertraege.

- `GET /rental-agreements`: Liste mit Filtern wie `status`, `property_id`, `landlord_id`, `tenant_id`
- `POST /rental-agreements`: Mietvertrag anlegen
- `GET /rental-agreements/{rentalAgreement}`: Mietvertrag ansehen
- `PUT/PATCH /rental-agreements/{rentalAgreement}`: Mietvertrag aendern
- `DELETE /rental-agreements/{rentalAgreement}`: Mietvertrag loeschen
- `GET /rental-agreements/{rentalAgreement}/documents`: Dokumente des Mietvertrags listen
- `POST /rental-agreements/{rentalAgreement}/documents`: Dokument-Metadaten an Mietvertrag haengen
- `GET /rental-agreements/{rentalAgreement}/payments`: Zahlungen/Forderungen des Mietvertrags listen
- `POST /rental-agreements/{rentalAgreement}/payments`: Zahlung/Forderung am Mietvertrag anlegen
- `GET /payments/{payment}`: einzelne Zahlung anzeigen
- `PATCH /payments/{payment}`: Zahlung aktualisieren
- `DELETE /payments/{payment}`: Zahlung loeschen

Ein Mietvertrag:

- gehoert zu genau einem Objekt
- referenziert genau einen `landlord`
- referenziert genau einen `tenant`
- kann optional ein `bank_account_id` als Zahlungsempfaenger referenzieren
- kann Laufzeiten, Miete, Kaution und Status enthalten
- kann generische Dokumentakten haben
- kann generische Zahlungen/Forderungen haben

Aktueller Stand:

- `admin` darf alle Mietvertraege sehen und bearbeiten
- `landlord` darf eigene Mietvertraege anlegen, sehen, aendern und loeschen, wenn er das zugehoerige Objekt als Vermieter verwaltet
- `tenant` darf nur eigene Mietvertraege sehen
- `user` hat keinen Zugriff

Wichtige Validierungsregeln fuer das Frontend:

- neue Mietvertraege starten als `draft`; beim Anlegen ist `status` optional, aber nur `draft` erlaubt
- fuer `landlord` muss `landlord_id` die ID des authentifizierten Benutzers sein
- fuer `landlord` muss `property_id` auf ein Objekt zeigen, das dieser Benutzer als Vermieter verwaltet
- `bank_account_id` ist optional, muss aber zum Vermieter oder dessen Organisation gehoeren
- beim Aktualisieren darf `landlord` den Vertrag nicht auf einen anderen Vermieter oder ein fremd verwaltetes Objekt verschieben
- beim Aktualisieren darf `bank_account_id` nicht auf ein fremdes Konto zeigen
- erlaubte Statuswechsel sind `draft` -> `active`, `active` -> `terminated` oder `ended`; bereits finale Status bleiben final

### Zahlungen

Die Payments-API verwaltet Geldbewegungen und geplante Forderungen generisch.
Sie ist am Mietvertrag angebunden, aber als polymorphes Modell vorbereitet.

Aktuelle Beispiele:

- monatliche Miete: `type=rent`, `direction=incoming`
- Kaution vom Mieter an Vermieter: `type=deposit`, `direction=incoming`
- Kautionsrueckzahlung vom Vermieter an Mieter: `type=deposit_refund`, `direction=outgoing`
- Nebenkosten/Nachzahlung: `type=service_charge`, meist `direction=incoming`

Wichtige Bedeutung:

- `direction=incoming` bedeutet Zahlung Richtung Vermieter/Vertragsseite
- `direction=outgoing` bedeutet Auszahlung vom Vermieter Richtung Mieter
- `status` kann `planned`, `pending`, `paid`, `overdue` oder `cancelled` sein
- `rental_agreements.deposit` bleibt der vertraglich vereinbarte Kautionsbetrag
- `payments` zeigen, ob und wann Kaution, Miete oder Rueckzahlung geplant oder gezahlt wurden
- wenn `payer_id`/`payee_id` fehlen, setzt der Server bei Mietvertraegen Defaults aus Vermieter und Mieter

Berechtigungen:

- `admin` darf alle Zahlungen sehen und verwalten
- `landlord` darf Zahlungen eigener Mietvertraege sehen und verwalten
- `tenant` darf Zahlungen eigener Mietvertraege sehen, aber nicht anlegen, aendern oder loeschen
- `user` hat keinen Zugriff

### Dokumente

Die Documents-API verwaltet Dokument-Metadaten und kann fuer
Mietvertragsdokumente PDF-Snapshots aus einer Vorlage erzeugen. Unterschriebene
Dokumentdateien koennen als PDF, Scan oder Foto hochgeladen und wieder
heruntergeladen werden.

- `GET /document-templates`: Dokumentvorlagen listen
- `POST /document-templates`: Dokumentvorlage anlegen
- `GET /document-templates/{documentTemplate}`: einzelne Dokumentvorlage anzeigen
- `PUT/PATCH /document-templates/{documentTemplate}`: Dokumentvorlage aktualisieren
- `DELETE /document-templates/{documentTemplate}`: Dokumentvorlage loeschen
- `POST /document-templates/{documentTemplate}/activate`: Dokumentvorlage aktivieren
- `GET /rental-agreements/{rentalAgreement}/documents`: Dokumente eines Mietvertrags listen
- `POST /rental-agreements/{rentalAgreement}/documents`: Dokumentakte am Mietvertrag anlegen
- `GET /documents/{document}`: einzelne Dokument-Metadaten anzeigen
- `POST /documents/{document}/generate`: neue Dokumentversion mit PDF erzeugen
- `POST /documents/{document}/share`: erzeugtes Dokument freigeben
- `POST /documents/{document}/void`: Dokument verwerfen
- `GET /documents/{document}/download`: aktuell erzeugtes PDF herunterladen
- `POST /documents/{document}/signed-upload`: unterschriebene Datei hochladen
- `GET /documents/{document}/signed-download`: unterschriebene Datei herunterladen
- `GET /documents/{document}/reminders`: Fristen/Erinnerungen eines Dokuments listen
- `POST /documents/{document}/reminders`: Frist/Erinnerung anlegen
- `PATCH /document-reminders/{documentReminder}`: Frist/Erinnerung aktualisieren
- `DELETE /document-reminders/{documentReminder}`: Frist/Erinnerung loeschen

Bei Vorlagen gilt:

- `admin` darf Vorlagen anlegen, bearbeiten, aktivieren, archivieren und nicht aktive Vorlagen loeschen
- `landlord` darf aktive Vorlagen sehen und fuer die Dokumentanlage auswaehlen
- `tenant` und `user` haben keinen Zugriff auf die Vorlagenverwaltung
- `content` nutzt Platzhalter im Format `{{ tenant.name }}`
- wenn `placeholders` nicht uebergeben wird, extrahiert der Server die Platzhalter aus `content`
- unbekannte Platzhalter werden abgelehnt; fuer Mietvertragsvorlagen sind aktuell nur Pfade aus dem Mietvertrag-Snapshot erlaubt, z. B. `document.title`, `tenant.name`, `landlord.name`, `property.address`, `rental_agreement.rent_cold`
- Bankkonto-Platzhalter wie `bank_account.account_holder`, `bank_account.iban`, `bank_account.bic` und `bank_account.bank_name` sind fuer Mietvertragsvorlagen erlaubt
- die Kombination `document_type`, `template_type`, `locale` und `version` muss eindeutig sein
- beim Aktivieren einer Vorlage werden andere aktive Vorlagen derselben Kombination aus `document_type`, `template_type` und `locale` automatisch archiviert
- aktive Vorlagen koennen nicht direkt geloescht werden; sie muessen vorher archiviert werden
- erzeugte Dokumentversionen behalten ihren `template_snapshot`, auch wenn die Vorlage spaeter geaendert oder archiviert wird

Beim Anlegen gilt:

- `document_type` ist erforderlich, z. B. `rental_agreement_contract`
- `document_template_id` ist optional
- wenn `document_template_id` gesetzt ist, muss die Vorlage denselben `document_type` haben
- neue Dokumente starten immer als `draft`

Beim Erzeugen gilt:

- die Vorlage muss aktiv sein; ohne zugewiesene Vorlage wird eine aktive Vorlage zum `document_type` gesucht
- die erzeugte `DocumentVersion` speichert `content_snapshot`, `template_snapshot`, `data_snapshot`, `generated_by_id` und `generated_at`
- `Document.status` und `DocumentVersion.status` werden auf `generated` gesetzt
- die PDF-Datei wird als `DocumentFile` mit `file_type=generated_pdf` gespeichert
- wenn ein bereits erzeugtes Dokument erneut erzeugt wird, wird die vorherige neueste Version auf `void` gesetzt

Beim Workflow gilt:

- erlaubte Status sind `draft`, `generated`, `shared`, `signed_uploaded`, `void`
- `POST /documents/{document}/share` setzt `generated` auf `shared`
- `POST /documents/{document}/void` setzt das Dokument und die neueste Version auf `void`
- `void` ist final; Downloads, Uploads und erneute Erzeugung liefern dann keinen neuen Workflow-Fortschritt mehr
- `tenant` sieht Dokumente erst ab `shared` oder `signed_uploaded`; `draft`,
  `generated` und `void` bleiben in Mieter-Responses verborgen

Beim Upload gilt:

- es muss bereits eine Dokumentversion geben
- erlaubt sind PDF, JPG, JPEG und PNG bis 10 MB
- die Datei wird als `DocumentFile` mit `file_type=signed_upload` gespeichert
- `Document.status` und `DocumentVersion.status` werden auf `signed_uploaded` gesetzt
- `tenant` darf eine unterschriebene Datei nur bei eigenen freigegebenen
  Dokumenten (`shared`) hochladen; `landlord` und `admin` folgen dem allgemeinen
  Dokumentworkflow

Bei Fristen und Erinnerungen gilt:

- Reminder gehoeren zu einer `Document`-Akte, nicht direkt zum Mietvertrag
- ein Reminder hat `title`, `due_at`, optional `remind_at`, `assigned_to_id`, `metadata` und Status
- erlaubte Reminder-Status sind `pending`, `done`, `cancelled`
- beim Wechsel auf `done` setzt der Server `completed_at`, wenn kein eigener Wert uebergeben wird
- `tenant` darf nur eigene, ueber `assigned_to_id` zugewiesene Erinnerungen bei
  sichtbaren Dokumenten sehen, aber nicht anlegen, aendern oder loeschen

Berechtigungen:

- `admin` darf Dokumente fuer alle Mietvertraege sehen, anlegen, erzeugen, freigeben, verwerfen, hochladen, herunterladen und Erinnerungen verwalten
- `landlord` darf Dokumente eigener Mietvertraege sehen, anlegen, erzeugen, freigeben, verwerfen, hochladen, herunterladen und Erinnerungen verwalten, wenn er das zugehoerige Objekt als Vermieter verwaltet
- `tenant` darf freigegebene oder unterschrieben hochgeladene Dokumente eigener
  Mietvertraege sehen/herunterladen und bei `shared` eine unterschriebene Datei
  hochladen, aber keine Dokumentakte anlegen, PDF-Version erzeugen oder
  Erinnerung verwalten
- `user` hat keinen Zugriff

Geplante Vertragsdokumente und PDF-Erzeugung sind in
[`rental-agreement-documents.md`](/home/slavik/project/backend-api/docs/rental-agreement-documents.md:1)
beschrieben. Die implementierten Dokument-Endpunkte stehen in `openapi.yaml`.

## Rechte nach Rolle

### Admin

`admin` hat die meisten Rechte.

- darf Benutzerlisten sehen
- darf Benutzer anlegen
- darf andere Benutzer sehen und bearbeiten
- darf anderen Benutzern Rollen zuweisen
- darf andere Benutzer loeschen
- darf alle Properties sehen
- darf alle Properties anlegen, bearbeiten und loeschen
- darf Property-Mitglieder bei jedem Objekt verwalten
- darf alle Bankkonten sehen und verwalten
- darf alle Address-, Rental-Agreement- und Document-Endpunkte nutzen

Einschraenkungen:

- darf die eigenen Rollen nicht ueber die API aendern
- darf den eigenen Benutzer nicht loeschen

### Landlord

`landlord` ist im aktuellen Stand die wichtigste fachliche Rolle fuer Properties.

- darf das eigene Profil sehen und aktualisieren
- darf die Property-Liste sehen
- darf neue Properties anlegen
- darf einzelne Properties nur dann sehen, wenn der Benutzer dem Objekt als `landlord` zugeordnet ist
- darf eine Property nur dann aendern oder loeschen, wenn der Benutzer genau bei diesem Objekt als `landlord` eingetragen ist
- darf Mitglieder eines Objekts nur dann verwalten, wenn der Benutzer bei diesem Objekt als `landlord` eingetragen ist
- darf Adressen eigener Objekte sehen, aendern und loeschen
- darf neue Adressen anlegen
- darf eigene und organisationsbezogene Bankkonten sehen und verwalten
- darf nur eigene Mietvertraege sehen, anlegen, aendern und loeschen
- darf Dokumente eigener Mietvertraege sehen, anlegen, erzeugen, freigeben, verwerfen, hochladen und herunterladen, wenn er das zugehoerige Objekt als Vermieter verwaltet
- darf Fristen/Erinnerungen fuer diese Dokumente verwalten
- darf Zahlungen eigener Mietvertraege sehen und verwalten

### Tenant

`tenant` ist im Property-Bereich lesend auf die eigene Objektzuordnung beschraenkt.

- darf das eigene Profil sehen und aktualisieren
- darf die Property-Liste sehen
- darf einzelne Properties nur dann sehen, wenn der Benutzer dem Objekt als `tenant` zugeordnet ist
- darf keine neuen Properties anlegen
- darf Properties nicht bearbeiten oder loeschen
- darf Property-Mitglieder nicht verwalten
- darf keine Adressen sehen
- darf keine Bankkonten ueber die Bankkonto-Verwaltung sehen
- darf eigene Mietvertraege sehen
- darf freigegebene oder unterschrieben hochgeladene Dokumente eigener Mietvertraege sehen/herunterladen
- darf fuer eigene freigegebene Dokumente eine unterschriebene Datei hochladen
- darf nur eigene, zugewiesene Fristen/Erinnerungen sichtbarer Dokumente sehen, aber nicht verwalten
- darf Zahlungen eigener Mietvertraege sehen, aber nicht verwalten

### User

`user` ist die allgemeinste Basisrolle.

- darf das eigene Profil sehen und aktualisieren
- darf keine Benutzerliste sehen
- darf keine Benutzer anlegen
- darf keine Properties sehen oder anlegen
- darf keine Property-Mitglieder verwalten
- darf keine Adressen sehen
- darf keine Bankkonten sehen
- darf keine Mietvertraege sehen
- darf keine Dokumente sehen
- darf keine Zahlungen sehen

## Wichtige fachliche Hinweise

- `tenant` kann ein Property sehen, ohne dass dabei automatisch die zugehoerige Adresse im JSON erscheint
- Mietvertraege enthalten fuer `tenant` weiterhin das Property, aber die verschachtelte Adresse wird ausgeblendet
- Mietvertraege koennen fuer `tenant` ein verknuepftes `bank_account` enthalten, damit Zahlungsdaten sichtbar sind; die separate Bankkonto-Verwaltung bleibt trotzdem gesperrt
- Dokumentlisten fuer `tenant` enthalten nur `shared` und `signed_uploaded`; das
  Frontend sollte `generated` nicht als Mieter-verfuegbar interpretieren
- Reminder in Tenant-Responses sind eine persoenliche Aufgaben-/Hinweisliste,
  keine vollstaendige Vermieter-Wiedervorlage
- Dokument-Responses enthalten `actions` als Button-Hinweise fuer das Frontend.
  Reine `tenant`-Sichten bekommen keine internen Template-, Snapshot-, Storage-
  oder Creator-Felder.
- Mietvertrags-Responses enthalten `actions` fuer wiederkehrende
  Verwaltungsbuttons. Reine `tenant`-Sichten bekommen keine internen `notes`.
- die Sichtbarkeit von Adressen, Mietvertraegen, Dokumenten und Zahlungen wird nicht nur ueber `show`, sondern auch ueber die Listen serverseitig gefiltert

## Typische Frontend-Responses

Vermieter sehen eine vollstaendige Dokumentakte mit internen Arbeitsdaten und
Workflow-Aktionen:

```json
{
  "data": {
    "id": 12,
    "status": "generated",
    "document_template_id": 3,
    "metadata": { "source": "manual" },
    "latest_version": {
      "version_number": 1,
      "status": "generated",
      "content_snapshot": "<h1>Wohnraummietvertrag</h1>",
      "files": [
        { "file_type": "generated_pdf", "path": "documents/12/versions/1/generated.pdf" }
      ]
    },
    "actions": {
      "generate": true,
      "share": true,
      "void": true,
      "download": true,
      "upload_signed": true,
      "download_signed": false,
      "create_reminder": true
    }
  }
}
```

Mieter sehen nur freigegebene Arbeitsprodukte und koennen daraus ihre erlaubten
Buttons ableiten:

```json
{
  "data": {
    "id": 12,
    "status": "shared",
    "latest_version": {
      "version_number": 1,
      "status": "shared",
      "files": [
        { "file_type": "generated_pdf", "original_name": "document-12-v1.pdf" }
      ]
    },
    "actions": {
      "generate": false,
      "share": false,
      "void": false,
      "download": true,
      "upload_signed": true,
      "download_signed": false,
      "create_reminder": false
    }
  }
}
```

## Empfehlung fuer das Frontend

Wenn du diese API an ein Frontend uebergibst, ist wichtig:

- `openapi.yaml` beschreibt Request- und Response-Formate im Detail
- dieses Dokument erklaert die Fachlogik und Rechte einfacher
- `rental-agreement-documents.md` beschreibt den geplanten PDF- und Dokumentworkflow
- `rental-agreement-payments.md` beschreibt Miete, Kaution, Rueckzahlungen und Payment-Status
- `TODO.md` enthaelt die kurze Paket-Roadmap fuer die naechsten Backend-Schritte
- bei `properties` sollte das Frontend mit `403 Forbidden` rechnen, wenn ein Benutzer fachlich keinen Zugriff auf ein Objekt hat
- bei `addresses`, `properties` und `rental-agreements` sollte das Frontend mit `403 Forbidden` rechnen, wenn ein Benutzer fachlich keinen Zugriff hat
- bei `bank-accounts` duerfen Landlords nur eigene oder organisationsbezogene Konten verwalten; fremde Konten liefern `403` oder Validierungsfehler
- bei `documents` sollte das Frontend ebenfalls mit `403 Forbidden` rechnen, wenn der Benutzer keinen Zugriff auf das verknuepfte Fachobjekt hat
- bei `payments` sollte das Frontend zwischen `type`, `direction` und `status` unterscheiden; Kaution, Rueckzahlung und Miete sind derselbe technische Kern
- Roadmap-Endpunkte aus `rental-agreement-documents.md` sind erst nutzbar, wenn sie auch in `openapi.yaml` stehen

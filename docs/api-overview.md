# API-Ueberblick

Dieses Dokument beschreibt die API in menschlicher Sprache.
Die technische Detailquelle bleibt [openapi.yaml](/home/slavik/project/backend-api/docs/openapi.yaml:1), aber hier steht kurz und klar, was die API fachlich tut und welche Rolle was darf.

## Grundidee

Die API verwaltet eine kleine Immobilien-Domaene mit diesen Kernobjekten:

- `users`: Benutzerkonten und Rollen
- `organizations`: Organisationen wie Verwaltungen oder Firmen, denen Benutzer optional zugeordnet sind
- `addresses`: Postadressen
- `properties`: Immobilien bzw. Einheiten
- `property_user`: Zuordnung von Benutzern zu Objekten mit Rollen wie `landlord`, `tenant`, `manager`
- `rental_agreements`: echte Mietvertraege zwischen Vermieter und Mieter
- `document_templates`, `documents`, `document_versions`, `document_files`: interne Documents-Datenbasis fuer spaetere Vertragsdokumente

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

Ein Mietvertrag:

- gehoert zu genau einem Objekt
- referenziert genau einen `landlord`
- referenziert genau einen `tenant`
- kann Laufzeiten, Miete, Kaution und Status enthalten
- kann generische Dokumentakten haben

Aktueller Stand:

- `admin` darf alle Mietvertraege sehen und bearbeiten
- `landlord` darf eigene Mietvertraege anlegen, sehen, aendern und loeschen, wenn er das zugehoerige Objekt als Vermieter verwaltet
- `tenant` darf nur eigene Mietvertraege sehen
- `user` hat keinen Zugriff

Wichtige Validierungsregeln fuer das Frontend:

- neue Mietvertraege starten als `draft`; beim Anlegen ist `status` optional, aber nur `draft` erlaubt
- fuer `landlord` muss `landlord_id` die ID des authentifizierten Benutzers sein
- fuer `landlord` muss `property_id` auf ein Objekt zeigen, das dieser Benutzer als Vermieter verwaltet
- beim Aktualisieren darf `landlord` den Vertrag nicht auf einen anderen Vermieter oder ein fremd verwaltetes Objekt verschieben
- erlaubte Statuswechsel sind `draft` -> `active`, `active` -> `terminated` oder `ended`; bereits finale Status bleiben final

### Dokumente

Die Documents-API verwaltet Dokument-Metadaten und kann fuer
Mietvertragsdokumente PDF-Snapshots aus einer Vorlage erzeugen. Unterschriebene
Dokumentdateien koennen als PDF, Scan oder Foto hochgeladen und wieder
heruntergeladen werden.

- `GET /rental-agreements/{rentalAgreement}/documents`: Dokumente eines Mietvertrags listen
- `POST /rental-agreements/{rentalAgreement}/documents`: Dokumentakte am Mietvertrag anlegen
- `GET /documents/{document}`: einzelne Dokument-Metadaten anzeigen
- `POST /documents/{document}/generate`: neue Dokumentversion mit PDF erzeugen
- `POST /documents/{document}/share`: erzeugtes Dokument freigeben
- `POST /documents/{document}/void`: Dokument verwerfen
- `GET /documents/{document}/download`: aktuell erzeugtes PDF herunterladen
- `POST /documents/{document}/signed-upload`: unterschriebene Datei hochladen
- `GET /documents/{document}/signed-download`: unterschriebene Datei herunterladen

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

Beim Upload gilt:

- es muss bereits eine Dokumentversion geben
- erlaubt sind PDF, JPG, JPEG und PNG bis 10 MB
- die Datei wird als `DocumentFile` mit `file_type=signed_upload` gespeichert
- `Document.status` und `DocumentVersion.status` werden auf `signed_uploaded` gesetzt

Berechtigungen:

- `admin` darf Dokumente fuer alle Mietvertraege sehen, anlegen, erzeugen, freigeben, verwerfen, hochladen und herunterladen
- `landlord` darf Dokumente eigener Mietvertraege sehen, anlegen, erzeugen, freigeben, verwerfen, hochladen und herunterladen, wenn er das zugehoerige Objekt als Vermieter verwaltet
- `tenant` darf Dokumente, erzeugte PDFs und unterschriebene Uploads eigener Mietvertraege sehen/herunterladen und eine unterschriebene Datei hochladen, aber keine Dokumentakte anlegen oder PDF-Version erzeugen
- `user` hat keinen Zugriff

Geplante Vertragsdokumente und PDF-Erzeugung sind in
[`rental-agreement-documents.md`](/home/slavik/project/backend-api/docs/rental-agreement-documents.md:1)
beschrieben. Die implementierten Dokument-Endpunkte stehen in `openapi.yaml`.
Weitere Workflow-Aktionen wie Verwerfen oder Freigabe sind noch Folgepakete.

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
- darf nur eigene Mietvertraege sehen, anlegen, aendern und loeschen
- darf Dokumente eigener Mietvertraege sehen, anlegen, erzeugen, freigeben, verwerfen, hochladen und herunterladen, wenn er das zugehoerige Objekt als Vermieter verwaltet

### Tenant

`tenant` ist im Property-Bereich lesend auf die eigene Objektzuordnung beschraenkt.

- darf das eigene Profil sehen und aktualisieren
- darf die Property-Liste sehen
- darf einzelne Properties nur dann sehen, wenn der Benutzer dem Objekt als `tenant` zugeordnet ist
- darf keine neuen Properties anlegen
- darf Properties nicht bearbeiten oder loeschen
- darf Property-Mitglieder nicht verwalten
- darf keine Adressen sehen
- darf eigene Mietvertraege sehen
- darf Dokumente, erzeugte PDFs und unterschriebene Uploads eigener Mietvertraege sehen/herunterladen
- darf fuer eigene Mietvertraege eine unterschriebene Datei hochladen

### User

`user` ist die allgemeinste Basisrolle.

- darf das eigene Profil sehen und aktualisieren
- darf keine Benutzerliste sehen
- darf keine Benutzer anlegen
- darf keine Properties sehen oder anlegen
- darf keine Property-Mitglieder verwalten
- darf keine Adressen sehen
- darf keine Mietvertraege sehen
- darf keine Dokumente sehen

## Wichtige fachliche Hinweise

- `tenant` kann ein Property sehen, ohne dass dabei automatisch die zugehoerige Adresse im JSON erscheint
- Mietvertraege enthalten fuer `tenant` weiterhin das Property, aber die verschachtelte Adresse wird ausgeblendet
- die Sichtbarkeit von Adressen, Mietvertraegen und Dokumenten wird nicht nur ueber `show`, sondern auch ueber die Listen serverseitig gefiltert

## Empfehlung fuer das Frontend

Wenn du diese API an ein Frontend uebergibst, ist wichtig:

- `openapi.yaml` beschreibt Request- und Response-Formate im Detail
- dieses Dokument erklaert die Fachlogik und Rechte einfacher
- `rental-agreement-documents.md` beschreibt den geplanten PDF- und Dokumentworkflow
- `TODO.md` enthaelt die kurze Paket-Roadmap fuer die naechsten Backend-Schritte
- bei `properties` sollte das Frontend mit `403 Forbidden` rechnen, wenn ein Benutzer fachlich keinen Zugriff auf ein Objekt hat
- bei `addresses`, `properties` und `rental-agreements` sollte das Frontend mit `403 Forbidden` rechnen, wenn ein Benutzer fachlich keinen Zugriff hat
- bei `documents` sollte das Frontend ebenfalls mit `403 Forbidden` rechnen, wenn der Benutzer keinen Zugriff auf das verknuepfte Fachobjekt hat
- Roadmap-Endpunkte aus `rental-agreement-documents.md` sind erst nutzbar, wenn sie auch in `openapi.yaml` stehen

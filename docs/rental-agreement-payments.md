# Payments-Modul: Miete, Kaution und Rueckzahlungen

Dieses Dokument beschreibt den aktuellen Payments-Schnitt fuer Mietvertraege.
Die technische HTTP-API bleibt `docs/openapi.yaml`.

## Ziel

Kaution, Kautionsrueckzahlung, Miete und Nebenkosten sollen nicht als getrennte
Spezialmodelle starten. Sie sind fachlich unterschiedliche Zahlungsfaelle, aber
technisch dieselbe Grundidee: eine geplante oder tatsaechliche Geldbewegung.

## Grundmodell

`Payment` ist generisch und verweist polymorph auf ein fachliches Objekt:

- `payable_type`: aktuell `RentalAgreement`
- `payable_id`: ID des Mietvertrags
- `type`: fachliche Art der Zahlung
- `direction`: Richtung der Geldbewegung
- `status`: aktueller Stand
- `amount` und `currency`
- optional `due_date` und `paid_at`
- optional `payer_id` und `payee_id`
- optional `description` und `metadata`

Aktuelle Typen:

- `rent`: Miete
- `deposit`: Kaution vom Mieter an Vermieter
- `deposit_refund`: Kautionsrueckzahlung vom Vermieter an Mieter
- `service_charge`: Nebenkosten/Nachzahlung
- `other`: sonstige Zahlung

Aktuelle Richtungen:

- `incoming`: Zahlung Richtung Vermieter/Vertragsseite
- `outgoing`: Auszahlung vom Vermieter Richtung Mieter

Aktuelle Status:

- `planned`: geplant, aber noch nicht faellig
- `pending`: offen/faellig
- `paid`: bezahlt
- `overdue`: ueberfaellig
- `cancelled`: storniert oder nicht mehr relevant

## Kaution

`rental_agreements.deposit` bleibt der vertraglich vereinbarte Kautionsbetrag.
Eine Kautionszahlung selbst ist ein `Payment`:

- `type=deposit`
- `direction=incoming`
- typischer Zahler: Mieter
- typischer Empfaenger: Vermieter

Eine spaetere Rueckzahlung ist ebenfalls ein `Payment`:

- `type=deposit_refund`
- `direction=outgoing`
- typischer Zahler: Vermieter
- typischer Empfaenger: Mieter

Einbehalte koennen spaeter ueber weitere Payments, Metadaten oder ein eigenes
Folgemodell abgebildet werden. Das ist noch nicht Teil dieses Pakets.

## API

Implementierte Endpunkte:

- `GET /rental-agreements/{rentalAgreement}/payments`
- `POST /rental-agreements/{rentalAgreement}/payments`
- `GET /payments/{payment}`
- `PATCH /payments/{payment}`
- `DELETE /payments/{payment}`

Die Listenroute kann nach `type`, `direction` und `status` filtern.

Beim Anlegen setzt der Server sinnvolle Defaults:

- ohne `status`: `pending`
- ohne `currency`: Vertragswaehrung, sonst `EUR`
- bei `incoming` ohne `payer_id`/`payee_id`: Mieter zahlt an Vermieter
- bei `outgoing` ohne `payer_id`/`payee_id`: Vermieter zahlt an Mieter
- bei `status=paid` ohne `paid_at`: aktueller Zeitpunkt

## Rollen

- `admin` darf alle Zahlungen sehen und verwalten.
- `landlord` darf Zahlungen eigener Mietvertraege sehen und verwalten.
- `tenant` darf Zahlungen eigener Mietvertraege sehen, aber nicht verwalten.
- `user` hat keinen Zugriff.

## Nicht Teil des ersten Payments-Pakets

- automatische monatliche Mietforderungen
- Bankintegration oder Kontoabgleich
- Rechnungsnummern, Mahnungen oder echtes Accounting
- Kautionskonto, Zinsen oder detaillierte Einbehalte
- automatische Ueberfaelligkeits-Jobs

Diese Punkte koennen spaeter auf dem generischen Payment-Kern aufbauen.

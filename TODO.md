# TODO

## Mietvertrag

### Erledigt

- Bestehende API-Basis weiter genutzt: `RentalAgreement` unter `/api/rental-agreements`.
- Vermieter können Mietverträge nur für sich selbst und selbst verwaltete Objekte anlegen/ändern.
- Neue Mietverträge starten als `draft`.
- Statuswechsel sind eingeschränkt: `draft` -> `active`, `active` -> `terminated`/`ended`; finale Status bleiben final.
- API-Dokumentation für Frontend-Übergabe aktualisiert: `docs/openapi.yaml` und `docs/api-overview.md`.
- Vorhaben für Vertragsdokumente, PDF-Erzeugung, Vorlagen, Branding und einfachen Unterschriften-Upload dokumentiert: `docs/rental-agreement-documents.md`.
- Architekturentscheidung festgehalten: Documents als generisches internes Modul planen, damit es später in ein eigenes Repository oder Paket herausgelöst werden kann.
- Generische Documents-Struktur angelegt: `DocumentTemplate`, `Document`, `DocumentVersion`, `DocumentFile`.
- Dokumente können per API an Mietverträge gehängt, gelistet und einzeln abgefragt werden.
- Paket 3 umgesetzt: Standard-Mietvertragsvorlage als Seeder, PDF-Snapshot per `POST /documents/{document}/generate`, Download per `GET /documents/{document}/download`.
- Paket 4 umgesetzt: unterschriebene Datei per `POST /documents/{document}/signed-upload` hochladen, per `GET /documents/{document}/signed-download` herunterladen.
- Paket 5 umgesetzt: Dokumentworkflow mit `share`, `void`, Statusübergängen und Ersetzen alter erzeugter Versionen geschärft.
- Paket 6 umgesetzt: Fristen/Erinnerungen als `DocumentReminder` an Dokumentakten modelliert und per API verwaltbar gemacht.
- Paket 7 umgesetzt: generische `Payment`-Struktur für Miete, Kaution, Rückzahlungen und Nebenkosten samt API und Doku ergänzt.
- Paket 8 erster Schnitt umgesetzt: Mieter sehen Dokumente erst ab `shared`/`signed_uploaded`, dürfen signierte Dateien nur bei freigegebenen Dokumenten hochladen und sehen nur eigene Reminder-Zuweisungen; Vermieter/Admin behalten die vollständige Arbeitsakte.
- Paket 8 vertieft: Dokument- und Mietvertrags-Responses liefern `actions` für Frontend-Buttons; reine Mieter-Sichten blenden interne Notizen, Template-/Snapshot-/Storage- und Creator-Felder aus.
- Template-Verwaltung umgesetzt: Admin-API für Dokumentvorlagen mit CRUD, Aktivierung, Archivierung konkurrierender aktiver Vorlagen und Platzhaltervalidierung.
- Bankverbindungen/Zahlungsempfänger umgesetzt: `BankAccount` für Benutzer oder Organisationen, CRUD-API für Admin/Landlord, optionale `bank_account_id` am Mietvertrag, Validierung gegen Vermieter/Organisation, Snapshot- und Placeholder-Erweiterung für Vertragsdokumente.
- AGENTS.md erweitert: Laravel-Best-Practice-Regeln und class-basierter Teststil für Pest/PHPUnit-kompatible Tests festgehalten.

### Nächste mögliche Schritte

- Schlanken Neustart-Kontext für Frontend/KI festlegen: zuerst `TODO.md`, dann `docs/api-overview.md`, `docs/openapi.yaml` und bei Bedarf die Modulnotizen.
- Datenlücken aus echter Mietvertragsvorlage weiter schließen: zusätzliche Objekt-/Übergabedaten, Anlagen, Hausordnung und Energieausweis fachlich modellieren.
- Frontend-Oberfläche für Bankkonten/Zahlungsempfänger und Auswahl am Mietvertrag bauen.
- Placeholder-Whitelist und Dokument-Snapshot für weitere echte Vertragsvorlagen erweitern.
- Robusten mehrseitigen PDF-Renderer für echte Vertragsdokumente vorbereiten; der aktuelle Renderer reicht nur für die technische Pipeline.
- Frontend-Admin-Oberfläche für Dokumentvorlagen bauen.
- Paket 8 abrunden: Listenfilter und optionale Response-Includes für wiederkehrende Frontend-Ansichten schärfen.

### Frontend-Übergabe

- `docs/openapi.yaml`: technische Quelle für aktuell implementierte Endpunkte, Requests und Responses.
- `docs/api-overview.md`: fachliche Zusammenfassung, Rollen und Berechtigungen.
- `docs/rental-agreement-documents.md`: Roadmap für PDF-, Upload-, Signatur- und Dokumentworkflow.
- `docs/rental-agreement-payments.md`: fachliche Übergabe für Miete, Kaution, Rückzahlungen und Zahlungsstatus.
- `TODO.md`: kurze Paketliste für die nächste Backend-Planung.

### Neustart-Kontext für KI/Frontend

- Aktueller Stand steht bewusst kompakt in `TODO.md`.
- Für fachliche Rechte und Rollen `docs/api-overview.md` lesen.
- Für technische Contracts immer `docs/openapi.yaml` als Quelle nehmen.
- Für Dokumentworkflow-Entscheidungen `docs/rental-agreement-documents.md` nutzen.
- Für Payments `docs/rental-agreement-payments.md` nutzen.

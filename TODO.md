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
- Paket 8 abgerundet: Dokumentlisten am Mietvertrag können nach `status` und `document_type` gefiltert werden; Mietvertragslisten unterstützen `starts_from`/`starts_until` und `include=documents,payments,reminders`; Zahlungslisten unterstützen `due_from`/`due_until` und `include=reminders`. Tenant-seitig bleiben Dokumente und Reminder auf sichtbare bzw. eigene Zuweisungen begrenzt.
- Template-Verwaltung umgesetzt: Admin-API für Dokumentvorlagen mit CRUD, Aktivierung, Archivierung konkurrierender aktiver Vorlagen und Platzhaltervalidierung.
- Bankverbindungen/Zahlungsempfänger umgesetzt: `BankAccount` für Benutzer oder Organisationen, CRUD-API für Admin/Landlord, optionale `bank_account_id` am Mietvertrag, Validierung gegen Vermieter/Organisation, Snapshot- und Placeholder-Erweiterung für Vertragsdokumente.
- Dokument-Responses liefern `snapshot_status`, damit das Frontend sieht, ob die aktuelle PDF-/Upload-Version noch zu den aktuellen Mietvertrags-, Objekt-, Parteien- und Bankdaten passt.
- Vertragsdetails aus der echten Wohnraummietvertragsvorlage ergänzt: mitvermietete Räume/Stellplätze, Gemeinschaftsflächen, Befristungsgrund, Übergabetermin, Umlageschlüssel, Renovierungszustand, Kleinreparaturgrenzen, Anlagen und öffentliche individuelle Vereinbarungen inklusive Snapshot und Placeholdern.
- AGENTS.md erweitert: Laravel-Best-Practice-Regeln und class-basierter Teststil für Pest/PHPUnit-kompatible Tests festgehalten.

### Nächste mögliche Backend-Pakete

- Schlanken Neustart-Kontext für Frontend/KI festlegen: zuerst `TODO.md`, dann `docs/api-overview.md`, `docs/openapi.yaml` und bei Bedarf die Modulnotizen.
- Paket 11 Such- und Listenfilter: operative Suche für Mietverträge, Objekte, Mieter und Zahlungen ausbauen. Aktuell gibt es viele ID-/Status-/Datumsfilter, aber noch keine direkte Suche nach Objektadresse, Mietername oder globale Zahlungsliste.
- Datenlücken aus echter Mietvertragsvorlage weiter schließen: Schlüssel, Zählerstände, konkrete Übergabeprotokollpositionen, Energieausweis-Metadaten und detaillierte Anlagen fachlich modellieren.
- Placeholder-Whitelist und Dokument-Snapshot für weitere echte Vertragsvorlagen erweitern.
- Robusten mehrseitigen PDF-Renderer für echte Vertragsdokumente vorbereiten; der aktuelle Renderer reicht nur für die technische Pipeline.

### Nächste mögliche Frontend-Punkte

- Oberfläche für Bankkonten/Zahlungsempfänger und Auswahl am Mietvertrag fertig anbinden; Backend liefert dafür bereits `BankAccount` und `rental_agreements.bank_account_id`.
- Frontend-Admin-Oberfläche für Dokumentvorlagen bauen.

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

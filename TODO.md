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

### Nächste mögliche Schritte

- Paket 5: Workflow weiter ausbauen: Entwurf, Erzeugung, Freigabe, Upload, Verwerfen, Kündigung/Beendigung.
- Paket 6: Fristen/Erinnerungen ergänzen.
- Paket 7: Kaution/Zahlungen modellieren.
- Paket 8: Getrennte Mieter- und Vermieter-Sichten weiter verfeinern.

### Frontend-Übergabe

- `docs/openapi.yaml`: technische Quelle für aktuell implementierte Endpunkte, Requests und Responses.
- `docs/api-overview.md`: fachliche Zusammenfassung, Rollen und Berechtigungen.
- `docs/rental-agreement-documents.md`: Roadmap für PDF-, Upload-, Signatur- und Dokumentworkflow.
- `TODO.md`: kurze Paketliste für die nächste Backend-Planung.

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

### Nächste mögliche Schritte

- Generische Documents-Struktur planen: `DocumentTemplate`, `Document`, `DocumentVersion`/`DocumentFile`.
- Vertragsdokument/PDF aus Vorlage erzeugen.
- Workflow weiter ausbauen: Entwurf, Aktivierung, Beendigung, Kündigung.
- Einfachen Upload für unterschriebene Dokumente bauen.
- Digitale Signatur später über externen Anbieter bewerten.
- Fristen/Erinnerungen ergänzen.
- Kaution/Zahlungen modellieren.
- Getrennte Mieter- und Vermieter-Sichten weiter verfeinern.

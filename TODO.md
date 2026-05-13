# TODO

## Mietvertrag

### Erledigt

- Bestehende API-Basis weiter genutzt: `RentalAgreement` unter `/api/rental-agreements`.
- Vermieter können Mietverträge nur für sich selbst und selbst verwaltete Objekte anlegen/ändern.
- Neue Mietverträge starten als `draft`.
- Statuswechsel sind eingeschränkt: `draft` -> `active`, `active` -> `terminated`/`ended`; finale Status bleiben final.
- API-Dokumentation für Frontend-Übergabe aktualisiert: `docs/openapi.yaml` und `docs/api-overview.md`.

### Nächste mögliche Schritte

- Vertragsdokument/PDF erzeugen.
- Workflow weiter ausbauen: Entwurf, Aktivierung, Beendigung, Kündigung.
- Digitale Signatur bewerten.
- Fristen/Erinnerungen ergänzen.
- Kaution/Zahlungen modellieren.
- Getrennte Mieter- und Vermieter-Sichten weiter verfeinern.

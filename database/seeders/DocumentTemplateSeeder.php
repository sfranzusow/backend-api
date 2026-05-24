<?php

namespace Database\Seeders;

use App\Models\DocumentTemplate;
use Illuminate\Database\Seeder;

class DocumentTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DocumentTemplate::query()->updateOrCreate(
            [
                'document_type' => 'rental_agreement_contract',
                'template_type' => 'residential',
                'locale' => 'de-DE',
                'version' => 1,
            ],
            [
                'name' => 'Wohnraummietvertrag Standard',
                'status' => DocumentTemplate::STATUS_ACTIVE,
                'content' => <<<'HTML'
<h1>{{ document.title }}</h1>
<p>Vermieter: {{ landlord.name }}</p>
<p>Mieter: {{ tenant.name }}</p>
<p>Objekt: {{ property.address }}</p>
<p>Einheit: {{ property.unit_number }}</p>
<p>Beginn: {{ rental_agreement.date_from }}</p>
<p>Ende: {{ rental_agreement.date_to }}</p>
<p>Kaltmiete: {{ rental_agreement.rent_cold }} {{ rental_agreement.currency }}</p>
<p>Warmmiete: {{ rental_agreement.rent_warm }} {{ rental_agreement.currency }}</p>
<p>Kaution: {{ rental_agreement.deposit }} {{ rental_agreement.currency }}</p>
<p>Zahlungsempfänger: {{ bank_account.account_holder }}</p>
<p>IBAN: {{ bank_account.iban }}</p>
<p>BIC: {{ bank_account.bic }}</p>
<p>Ort und Datum Vermieter: ______________________________</p>
<p>Unterschrift Vermieter: _______________________________</p>
<p>Ort und Datum Mieter: _________________________________</p>
<p>Unterschrift Mieter: __________________________________</p>
HTML,
                'placeholders' => [
                    'document.title',
                    'landlord.name',
                    'tenant.name',
                    'property.address',
                    'property.unit_number',
                    'rental_agreement.date_from',
                    'rental_agreement.date_to',
                    'rental_agreement.rent_cold',
                    'rental_agreement.rent_warm',
                    'rental_agreement.deposit',
                    'rental_agreement.currency',
                    'bank_account.account_holder',
                    'bank_account.iban',
                    'bank_account.bic',
                ],
                'metadata' => [
                    'description' => 'Erste Standardvorlage fuer Mietvertragsdokumente.',
                ],
            ]
        );
    }
}

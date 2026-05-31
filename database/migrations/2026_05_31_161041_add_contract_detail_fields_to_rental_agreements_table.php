<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('rental_agreements', function (Blueprint $table) {
            $table->text('lease_subject_description')->nullable();
            $table->text('additional_spaces')->nullable();
            $table->text('shared_facilities')->nullable();
            $table->text('fixed_term_reason')->nullable();
            $table->date('handover_due_at')->nullable();
            $table->text('operating_costs_allocation_key')->nullable();
            $table->string('renovation_condition', 32)->nullable();
            $table->text('renovation_condition_notes')->nullable();
            $table->text('cosmetic_repairs_agreement')->nullable();
            $table->decimal('small_repairs_single_limit', 12, 2)->nullable();
            $table->decimal('small_repairs_annual_limit', 12, 2)->nullable();
            $table->boolean('handover_protocol_attached')->default(false);
            $table->boolean('house_rules_attached')->default(false);
            $table->boolean('operating_costs_overview_attached')->default(false);
            $table->boolean('energy_certificate_attached')->default(false);
            $table->boolean('self_disclosure_attached')->default(false);
            $table->text('other_attachments')->nullable();
            $table->text('individual_agreements')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rental_agreements', function (Blueprint $table) {
            $table->dropColumn([
                'lease_subject_description',
                'additional_spaces',
                'shared_facilities',
                'fixed_term_reason',
                'handover_due_at',
                'operating_costs_allocation_key',
                'renovation_condition',
                'renovation_condition_notes',
                'cosmetic_repairs_agreement',
                'small_repairs_single_limit',
                'small_repairs_annual_limit',
                'handover_protocol_attached',
                'house_rules_attached',
                'operating_costs_overview_attached',
                'energy_certificate_attached',
                'self_disclosure_attached',
                'other_attachments',
                'individual_agreements',
            ]);
        });
    }
};

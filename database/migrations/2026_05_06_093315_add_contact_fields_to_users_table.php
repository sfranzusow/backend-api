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
        Schema::table('users', function (Blueprint $table) {
            $table->after('email', function (Blueprint $table) {
                $table->string('phone_number', 50)->nullable();
                $table->string('address_street')->nullable();
                $table->string('address_house_number', 20)->nullable();
                $table->string('address_zip_code', 20)->nullable();
                $table->string('address_city')->nullable();
                $table->string('address_country', 2)->nullable();
                $table->foreignId('organization_id')
                    ->nullable()
                    ->constrained('organizations')
                    ->nullOnDelete();
            });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_id');
            $table->dropColumn([
                'phone_number',
                'address_street',
                'address_house_number',
                'address_zip_code',
                'address_city',
                'address_country',
            ]);
        });
    }
};

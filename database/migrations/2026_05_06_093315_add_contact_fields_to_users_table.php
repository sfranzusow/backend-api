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
            $table->string('phone_number', 50)->nullable()->after('email');
            $table->string('address_street')->nullable()->after('phone_number');
            $table->string('address_house_number', 20)->nullable()->after('address_street');
            $table->string('address_zip_code', 20)->nullable()->after('address_house_number');
            $table->string('address_city')->nullable()->after('address_zip_code');
            $table->string('address_country', 2)->nullable()->after('address_city');
            $table->foreignId('organization_id')
                ->nullable()
                ->after('address_country')
                ->constrained()
                ->nullOnDelete();
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

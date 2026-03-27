<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->string('street');
            $table->string('house_number', 20);
            $table->string('zip_code', 20);
            $table->string('city');
            $table->string('country', 2)->default('DE');
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->timestamps();
        });

        Schema::create('properties', function (Blueprint $table) {
            $table->id();

            $table->foreignId('address_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('unit_number')->nullable()->comment('z.B. Top 4 oder 2. OG links');
            $table->enum('type', ['apartment', 'office', 'penthouse', 'studio']);

            $table->decimal('area_living', 8, 2);
            $table->unsignedTinyInteger('rooms');
            $table->unsignedSmallInteger('floor')->nullable();

            $table->year('build_year')->nullable();
            $table->string('energy_class', 3)->nullable();

            $table->decimal('price', 15, 2)->nullable();
            $table->json('features')->nullable();

            $table->enum('status', ['available', 'rented', 'sold'])->default('available');

            $table->timestamps();
        });

        Schema::create('property_user', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('property_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->enum('role', ['landlord', 'tenant', 'manager'])->default('tenant');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'property_id', 'role']);
        });

        Schema::create('rental_agreements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('property_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('landlord_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('tenant_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->date('date_from');
            $table->date('date_to')->nullable();

            $table->decimal('rent_cold', 12, 2);
            $table->decimal('rent_warm', 12, 2)->nullable();
            $table->decimal('service_charges', 12, 2)->nullable();
            $table->decimal('deposit', 12, 2)->nullable();

            $table->string('currency', 3)->default('EUR');

            $table->enum('status', ['draft', 'active', 'terminated', 'ended'])->default('draft');
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_agreements');
        Schema::dropIfExists('property_user');
        Schema::dropIfExists('properties');
        Schema::dropIfExists('addresses');
    }
};
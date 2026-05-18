<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('document_type');
            $table->string('template_type')->default('default');
            $table->string('locale', 10)->default('de-DE');
            $table->unsignedInteger('version')->default(1);
            $table->enum('status', ['draft', 'active', 'archived'])->default('draft');
            $table->longText('content')->nullable();
            $table->json('placeholders')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['document_type', 'template_type', 'locale', 'version'],
                'document_templates_lookup_unique'
            );
            $table->index(['document_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_templates');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('document_template_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->unsignedInteger('version_number');
            $table->enum('status', ['draft', 'generated', 'shared', 'signed_uploaded', 'void'])->default('draft');
            $table->string('title')->nullable();
            $table->longText('content_snapshot')->nullable();
            $table->json('template_snapshot')->nullable();
            $table->json('data_snapshot')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('generated_by_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->unique(['document_id', 'version_number']);
            $table->index(['status', 'generated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_versions');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->morphs('documentable');
            $table->foreignId('document_template_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->string('document_type');
            $table->enum('status', ['draft', 'generated', 'shared', 'signed_uploaded', 'void'])->default('draft');
            $table->string('title')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['document_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};

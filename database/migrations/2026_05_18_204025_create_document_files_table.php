<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_version_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('file_type');
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('checksum', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('uploaded_by_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();

            $table->index(['document_version_id', 'file_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_files');
    }
};

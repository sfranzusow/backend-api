<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('title');
            $table->text('notes')->nullable();
            $table->timestamp('due_at');
            $table->timestamp('remind_at')->nullable();
            $table->enum('status', ['pending', 'done', 'cancelled'])->default('pending');
            $table->json('metadata')->nullable();
            $table->foreignId('assigned_to_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('created_by_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['document_id', 'status', 'due_at']);
            $table->index(['assigned_to_id', 'status', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_reminders');
    }
};

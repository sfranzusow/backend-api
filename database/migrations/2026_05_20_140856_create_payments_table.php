<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->morphs('payable');
            $table->enum('type', ['rent', 'deposit', 'deposit_refund', 'service_charge', 'other']);
            $table->enum('direction', ['incoming', 'outgoing']);
            $table->enum('status', ['planned', 'pending', 'paid', 'overdue', 'cancelled'])->default('pending');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('EUR');
            $table->date('due_date')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('payer_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('payee_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['payable_type', 'payable_id', 'type', 'status', 'due_date']);
            $table->index(['payer_id', 'status', 'due_date']);
            $table->index(['payee_id', 'status', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

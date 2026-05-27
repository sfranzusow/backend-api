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
        Schema::create('document_layout_templates', function (Blueprint $table) {
            $table->id();
            $table->morphs('owner');
            $table->string('name');
            $table->string('document_type');
            $table->string('locale', 10)->default('de-DE');
            $table->unsignedInteger('version')->default(1);
            $table->enum('status', ['draft', 'active', 'archived'])->default('draft');
            $table->boolean('header_enabled')->default(false);
            $table->boolean('footer_enabled')->default(false);
            $table->boolean('page_numbers_enabled')->default(true);
            $table->longText('header_content')->nullable();
            $table->longText('footer_content')->nullable();
            $table->string('header_banner_path', 2048)->nullable();
            $table->string('footer_banner_path', 2048)->nullable();
            $table->json('placeholders')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['owner_type', 'owner_id', 'document_type', 'locale', 'version'],
                'document_layout_templates_lookup_unique'
            );
            $table->index(['owner_type', 'owner_id', 'document_type', 'status'], 'document_layout_templates_owner_status_index');
        });

        Schema::table('document_versions', function (Blueprint $table) {
            $table->foreignId('document_layout_template_id')
                ->nullable()
                ->after('document_template_id')
                ->constrained()
                ->nullOnDelete();
            $table->json('layout_snapshot')->nullable()->after('template_snapshot');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_versions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('document_layout_template_id');
            $table->dropColumn('layout_snapshot');
        });

        Schema::dropIfExists('document_layout_templates');
    }
};

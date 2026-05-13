<?php

namespace Tests\Feature\Console\Commands;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SyncApiDocsCommandTest extends TestCase
{
    private string $frontendPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->frontendPath = storage_path('framework/testing/frontend-spa');

        File::deleteDirectory($this->frontendPath);
        File::ensureDirectoryExists($this->frontendPath);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->frontendPath);

        parent::tearDown();
    }

    public function test_syncs_api_docs_to_frontend_project(): void
    {
        $this->artisan('dev:sync-api-docs', [
            '--frontend-path' => $this->frontendPath,
        ])
            ->expectsOutputToContain('Synced API docs to')
            ->assertSuccessful();

        $this->assertFileEquals(
            base_path('docs/openapi.yaml'),
            $this->frontendPath.'/docs/openapi.yaml',
        );

        $this->assertFileEquals(
            base_path('docs/api-overview.md'),
            $this->frontendPath.'/docs/api-overview.md',
        );

        $this->assertFileEquals(
            base_path('docs/rental-agreement-documents.md'),
            $this->frontendPath.'/docs/rental-agreement-documents.md',
        );
    }

    public function test_fails_when_frontend_path_does_not_exist(): void
    {
        $missingFrontendPath = storage_path('framework/testing/missing-frontend-spa');

        File::deleteDirectory($missingFrontendPath);

        $this->artisan('dev:sync-api-docs', [
            '--frontend-path' => $missingFrontendPath,
        ])
            ->expectsOutputToContain('Frontend path not found')
            ->assertFailed();
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

#[Signature('dev:sync-api-docs {--frontend-path=../frontend-spa : Path to the frontend project}')]
#[Description('Copy API documentation files to the frontend project')]
class SyncApiDocsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $frontendPath = $this->option('frontend-path');

        if (! is_string($frontendPath) || trim($frontendPath) === '') {
            $this->components->error('The frontend path must not be empty.');

            return self::FAILURE;
        }

        $frontendRoot = realpath($this->pathFromProjectRoot($frontendPath));

        if ($frontendRoot === false || ! File::isDirectory($frontendRoot)) {
            $this->components->error('Frontend path not found: '.$frontendPath);

            return self::FAILURE;
        }

        $frontendDocsPath = $frontendRoot.DIRECTORY_SEPARATOR.'docs';

        File::ensureDirectoryExists($frontendDocsPath);

        foreach ($this->apiDocs() as $apiDoc) {
            File::copy(base_path($apiDoc), $frontendDocsPath.DIRECTORY_SEPARATOR.basename($apiDoc));
        }

        $this->components->info('Synced API docs to '.$frontendDocsPath);

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function apiDocs(): array
    {
        return [
            'docs/openapi.yaml',
            'docs/api-overview.md',
            'docs/rental-agreement-documents.md',
        ];
    }

    private function pathFromProjectRoot(string $path): string
    {
        $path = trim($path);

        if ($this->isAbsolutePath($path)) {
            return $path;
        }

        return base_path($path);
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR)
            || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
    }
}

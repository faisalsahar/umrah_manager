<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Copies any KEY=value line that exists in .env.example but not in .env,
 * without ever touching a line that's already there — so pulling in new
 * config (e.g. a new OAuth provider) never means hand-diffing the two files
 * to see what you're missing.
 */
class SyncEnvExample extends Command
{
    protected $signature = 'env:sync
        {--example=.env.example : Path to the example file, relative to the project root}
        {--target=.env : Path to the env file to update, relative to the project root}
        {--dry-run : Show what would be added without writing anything}';

    protected $description = 'Add any new keys from .env.example to .env, leaving existing values untouched';

    public function handle(): int
    {
        $examplePath = base_path($this->option('example'));
        $targetPath = base_path($this->option('target'));

        if (! File::exists($examplePath)) {
            $this->error("Example file not found: {$examplePath}");

            return self::FAILURE;
        }

        if (! File::exists($targetPath)) {
            if ($this->option('dry-run')) {
                $this->info("{$this->option('target')} does not exist — would be created from {$this->option('example')}.");

                return self::SUCCESS;
            }

            File::copy($examplePath, $targetPath);
            $this->info("{$this->option('target')} did not exist — created it from {$this->option('example')}.");

            return self::SUCCESS;
        }

        $existingKeys = self::keysIn(File::get($targetPath));

        $missingLines = [];

        foreach (explode("\n", File::get($examplePath)) as $line) {
            $key = self::keyOf($line);

            if ($key !== null && ! in_array($key, $existingKeys, true)) {
                $missingLines[] = $line;
                $existingKeys[] = $key; // guard against the same key appearing twice in the example
            }
        }

        if ($missingLines === []) {
            $this->info('Already in sync — no new keys to add.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info('Would add the following to '.$this->option('target').':');
            foreach ($missingLines as $line) {
                $this->line("  {$line}");
            }

            return self::SUCCESS;
        }

        $addition = "\n# Added by `php artisan env:sync` from {$this->option('example')} — ".now()->toDateTimeString()."\n"
            .implode("\n", $missingLines)."\n";

        File::append($targetPath, $addition);

        $this->info(sprintf('Added %d new key(s) to %s:', count($missingLines), $this->option('target')));
        foreach ($missingLines as $line) {
            $this->line("  {$line}");
        }

        return self::SUCCESS;
    }

    /**
     * Every variable name already set (active, non-comment) in a .env file's contents.
     *
     * @return list<string>
     */
    private static function keysIn(string $contents): array
    {
        $keys = [];

        foreach (explode("\n", $contents) as $line) {
            $key = self::keyOf($line);

            if ($key !== null) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * The variable name on a `KEY=value` line, or null for a comment/blank/malformed line.
     */
    private static function keyOf(string $line): ?string
    {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            return null;
        }

        if (! str_contains($line, '=')) {
            return null;
        }

        return trim(explode('=', $line, 2)[0]);
    }
}

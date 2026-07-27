<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SyncEnvExampleTest extends TestCase
{
    private string $exampleRelative = 'storage/framework/testing/envsync/example.txt';

    private string $targetRelative = 'storage/framework/testing/envsync/target.txt';

    protected function setUp(): void
    {
        parent::setUp();

        File::ensureDirectoryExists(dirname(base_path($this->exampleRelative)));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(dirname(base_path($this->exampleRelative)));

        parent::tearDown();
    }

    public function test_it_adds_missing_keys_without_touching_existing_values(): void
    {
        File::put(base_path($this->exampleRelative), <<<'ENV'
            APP_NAME=Laravel
            APP_ENV=local
            # a comment
            DB_CONNECTION=sqlite

            GOOGLE_CLIENT_ID=
            GOOGLE_CLIENT_SECRET=
            NEW_FEATURE_FLAG=false
            ENV);

        File::put(base_path($this->targetRelative), <<<'ENV'
            APP_NAME="My Real App"
            APP_ENV=local
            DB_CONNECTION=mysql
            ENV);

        $this->artisan('env:sync', ['--example' => $this->exampleRelative, '--target' => $this->targetRelative])
            ->assertExitCode(0);

        $result = File::get(base_path($this->targetRelative));

        $this->assertStringContainsString('APP_NAME="My Real App"', $result);
        $this->assertStringContainsString('DB_CONNECTION=mysql', $result);
        $this->assertStringContainsString('GOOGLE_CLIENT_ID=', $result);
        $this->assertStringContainsString('GOOGLE_CLIENT_SECRET=', $result);
        $this->assertStringContainsString('NEW_FEATURE_FLAG=false', $result);
        $this->assertStringNotContainsString('APP_NAME=Laravel', $result);
    }

    public function test_dry_run_reports_missing_keys_without_writing(): void
    {
        File::put(base_path($this->exampleRelative), "APP_NAME=Laravel\nNEW_KEY=value\n");
        File::put(base_path($this->targetRelative), "APP_NAME=Real\n");

        $this->artisan('env:sync', ['--example' => $this->exampleRelative, '--target' => $this->targetRelative, '--dry-run' => true])
            ->expectsOutputToContain('NEW_KEY=value')
            ->assertExitCode(0);

        $this->assertSame("APP_NAME=Real\n", File::get(base_path($this->targetRelative)));
    }

    public function test_it_reports_already_in_sync_when_nothing_is_missing(): void
    {
        File::put(base_path($this->exampleRelative), "APP_NAME=Laravel\n");
        File::put(base_path($this->targetRelative), "APP_NAME=Real\n");

        $this->artisan('env:sync', ['--example' => $this->exampleRelative, '--target' => $this->targetRelative])
            ->expectsOutputToContain('Already in sync')
            ->assertExitCode(0);
    }

    public function test_it_creates_the_target_from_the_example_when_missing(): void
    {
        File::put(base_path($this->exampleRelative), "APP_NAME=Laravel\n");

        $this->artisan('env:sync', ['--example' => $this->exampleRelative, '--target' => $this->targetRelative])
            ->assertExitCode(0);

        $this->assertSame("APP_NAME=Laravel\n", File::get(base_path($this->targetRelative)));
    }

    public function test_dry_run_does_not_create_a_missing_target(): void
    {
        File::put(base_path($this->exampleRelative), "APP_NAME=Laravel\n");

        $this->artisan('env:sync', ['--example' => $this->exampleRelative, '--target' => $this->targetRelative, '--dry-run' => true])
            ->assertExitCode(0);

        $this->assertFileDoesNotExist(base_path($this->targetRelative));
    }

    public function test_it_fails_when_the_example_file_is_missing(): void
    {
        $this->artisan('env:sync', ['--example' => 'storage/framework/testing/envsync/does-not-exist.txt', '--target' => $this->targetRelative])
            ->assertExitCode(1);
    }
}

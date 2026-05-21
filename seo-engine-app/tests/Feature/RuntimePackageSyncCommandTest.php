<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class RuntimePackageSyncCommandTest extends TestCase
{
    public function test_sync_local_package_command_copies_and_updates_package_files(): void
    {
        $root = storage_path('framework/testing/package-sync-'.uniqid('', true));
        $source = $root.DIRECTORY_SEPARATOR.'source';
        $target = $root.DIRECTORY_SEPARATOR.'target';

        File::ensureDirectoryExists($source.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'Services');
        File::ensureDirectoryExists($target.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'Services');

        File::put($source.DIRECTORY_SEPARATOR.'composer.json', '{"name":"ofyre/seo-engine"}');
        File::put($source.DIRECTORY_SEPARATOR.'README.md', 'source-readme');
        File::put($source.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'Services'.DIRECTORY_SEPARATOR.'Demo.php', '<?php echo "fresh";');

        File::put($target.DIRECTORY_SEPARATOR.'README.md', 'stale-readme');
        File::put($target.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'Services'.DIRECTORY_SEPARATOR.'Demo.php', '<?php echo "stale";');

        try {
            $this->artisan('seo:sync-local-package', [
                '--source' => $source,
                '--target' => $target,
            ])
                ->expectsOutputToContain('Local package sync complete.')
                ->assertSuccessful();

            $this->assertSame('source-readme', File::get($target.DIRECTORY_SEPARATOR.'README.md'));
            $this->assertSame('<?php echo "fresh";', File::get($target.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'Services'.DIRECTORY_SEPARATOR.'Demo.php'));
            $this->assertSame('{"name":"ofyre/seo-engine"}', File::get($target.DIRECTORY_SEPARATOR.'composer.json'));
        } finally {
            File::deleteDirectory($root);
        }
    }
}

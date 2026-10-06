<?php

declare(strict_types=1);

namespace ControlAir\LaravelIntacct\Tests\Feature;

use ControlAir\LaravelIntacct\Facades\Intacct;
use ControlAir\LaravelIntacct\IntacctServiceProvider;
use ControlAir\LaravelIntacct\Tests\TestCase;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\PackageManifest;

final class PackageDiscoveryTest extends TestCase
{
    public function test_laravel_discovers_the_provider_and_alias_from_composer_json(): void
    {
        $files = new Filesystem();
        $basePath = sys_get_temp_dir() . '/laravel-intacct-discovery-' . uniqid();
        $files->ensureDirectoryExists($basePath . '/vendor/composer');

        $composer = $files->json(__DIR__ . '/../../composer.json');
        $files->put($basePath . '/vendor/composer/installed.json', (string) json_encode(['packages' => [$composer]]));

        $manifest = new PackageManifest($files, $basePath, $basePath . '/packages.php');
        $manifest->vendorPath = $basePath . '/vendor';

        $this->assertContains(IntacctServiceProvider::class, $manifest->providers());
        $this->assertSame(['Intacct' => Intacct::class], $manifest->aliases());

        $files->deleteDirectory($basePath);
    }
}

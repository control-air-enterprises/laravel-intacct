<?php

declare(strict_types=1);

namespace ControlAir\LaravelIntacct\Tests;

use ControlAir\LaravelIntacct\IntacctServiceProvider;
use Illuminate\Encryption\Encrypter;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [IntacctServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app->make('config')->set('app.key', 'base64:' . base64_encode(Encrypter::generateKey('AES-256-CBC')));
    }
}

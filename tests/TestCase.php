<?php

declare(strict_types=1);

namespace ControlAir\LaravelIntacct\Tests;

use ControlAir\LaravelIntacct\IntacctServiceProvider;
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
}

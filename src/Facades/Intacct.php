<?php

declare(strict_types=1);

namespace ControlAir\LaravelIntacct\Facades;

use ControlAir\Intacct\Auth\OAuth\OAuthClient;
use ControlAir\Intacct\Auth\Tokens\TokenKey;
use ControlAir\Intacct\Auth\Tokens\TokenManager;
use ControlAir\Intacct\Configuration\OAuthApplication;
use ControlAir\Intacct\IntacctClient;
use ControlAir\LaravelIntacct\IntacctManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static IntacctClient connection(?string $name = null)
 * @method static OAuthApplication application(?string $name = null)
 * @method static OAuthClient oauthClient(?string $name = null)
 * @method static TokenManager tokenManager(?string $name = null)
 * @method static TokenKey tokenKey(?string $name = null)
 * @method static string getDefaultConnection()
 *
 * @see IntacctManager
 */
final class Intacct extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return IntacctManager::class;
    }
}

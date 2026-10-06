# Laravel Intacct

Laravel integration for the [Sage Intacct PHP SDK](https://github.com/control-air-enterprises/intacct-php-sdk).

This package does not wrap or duplicate the SDK. It builds the SDK objects from Laravel configuration, stores OAuth tokens encrypted in the database, and lets the application inject `IntacctClient` directly.

## Requirements

- PHP 8.5+
- Laravel 13+

## Installation

```bash
composer require control-air/laravel-intacct
```

The service provider and the `Intacct` facade are registered automatically through Laravel package discovery.

Publish and run the token table migration:

```bash
php artisan vendor:publish --tag=intacct-migrations
php artisan migrate
```

## Configuration

A single Sage Intacct company only needs environment variables:

```env
SAGE_INTACCT_CLIENT_ID=
SAGE_INTACCT_CLIENT_SECRET=
SAGE_INTACCT_COMPANY_ID=
SAGE_INTACCT_USER_ID=
SAGE_INTACCT_ENTITY_ID=
```

| Variable | Required | Description |
|---|---|---|
| `SAGE_INTACCT_CLIENT_ID` | Yes | OAuth client ID of the Sage application |
| `SAGE_INTACCT_CLIENT_SECRET` | Yes | OAuth client secret of the Sage application |
| `SAGE_INTACCT_COMPANY_ID` | Yes | Intacct company ID |
| `SAGE_INTACCT_USER_ID` | Yes | Intacct Web Services user ID |
| `SAGE_INTACCT_ENTITY_ID` | No | Entity to work in, for multi-entity companies |
| `SAGE_INTACCT_TOKEN_KEY` | No | Row key used to store the tokens. Defaults to `default` |
| `SAGE_INTACCT_CONNECTION` | No | Name of the default connection. Defaults to `default` |
| `SAGE_INTACCT_TOKEN_STORE` | No | Token store driver. Only `database` is supported |

These variables feed the connection named `default`. Publishing the configuration file is only needed to add more connections:

```bash
php artisan vendor:publish --tag=intacct-config
```

## Usage

Inject `IntacctClient`. It resolves the default connection:

```php
use ControlAir\Intacct\IntacctClient;
use ControlAir\Intacct\Resources\Projects\Project;
use ControlAir\Intacct\ValueObjects\ObjectKey;

final class ProjectService
{
    public function __construct(
        private readonly IntacctClient $intacct,
    ) {}

    public function find(string $key): Project
    {
        return $this->intacct->projects->get(new ObjectKey($key));
    }
}
```

The client exposes the SDK API unchanged:

```php
$intacct->projects->get(...);
$intacct->projects->query(...);
$intacct->construction->projectContracts->query(...);
$intacct->accountsPayable->vendors->query(...);
```

Resources, queries and DTOs are documented in the [SDK repository](https://github.com/control-air-enterprises/intacct-php-sdk).

## Multiple connections

Each connection has its own OAuth application, company, user, entity and stored tokens.

1. Publish the configuration file:

    ```bash
    php artisan vendor:publish --tag=intacct-config
    ```

2. Add the connection to `config/intacct.php`:

    ```php
    'connections' => [
        'default' => [
            // ...
        ],

        'company-b' => [
            'client_id' => env('SAGE_INTACCT_B_CLIENT_ID'),
            'client_secret' => env('SAGE_INTACCT_B_CLIENT_SECRET'),
            'company_id' => env('SAGE_INTACCT_B_COMPANY_ID'),
            'user_id' => env('SAGE_INTACCT_B_USER_ID'),
            'entity_id' => env('SAGE_INTACCT_B_ENTITY_ID'),
            'token_key' => 'company-b',
        ],
    ],
    ```

    Every connection needs its own `token_key`. Two connections sharing a key overwrite each other's tokens.

3. Add the new variables to `.env`.

Resolve a named connection through the manager:

```php
use ControlAir\LaravelIntacct\IntacctManager;

$intacct = app(IntacctManager::class)->connection('company-b');
```

Calling `connection()` without a name returns the default connection. Each connection is built on first use and reused afterwards.

## Facade

The `Intacct` facade proxies `IntacctManager`. Dependency injection is preferred.

```php
use ControlAir\LaravelIntacct\Facades\Intacct;

Intacct::connection()->projects->query(...);
Intacct::connection('company-b')->projects->query(...);
```

## Token storage

OAuth tokens are stored in the `intacct_tokens` table, one row per `token_key`. Access and refresh tokens are encrypted with the application's `APP_KEY`, so rotating `APP_KEY` invalidates the stored tokens.

## Reference

### `IntacctManager`

Every method takes an optional connection name. Without one, it uses the default connection from `intacct.default`. Objects are built on first use and cached per connection.

| Method | Returns | Description |
|---|---|---|
| `connection(?string $name = null)` | `IntacctClient` | The SDK client for the connection |
| `application(?string $name = null)` | `OAuthApplication` | The OAuth client ID and secret |
| `oauthClient(?string $name = null)` | `OAuthClient` | The SDK OAuth client, for token, revoke and introspection calls |
| `tokenManager(?string $name = null)` | `TokenManager` | The SDK token manager bound to the database token store |
| `tokenKey(?string $name = null)` | `TokenKey` | The key the connection's tokens are stored under |
| `getDefaultConnection()` | `string` | The name of the default connection |

```php
use ControlAir\LaravelIntacct\IntacctManager;

$manager = app(IntacctManager::class);

$manager->connection();                      // default connection
$manager->connection('company-b');           // named connection
$manager->tokenManager('company-b');         // TokenManager of company-b
$manager->tokenKey('company-b')->value;      // 'company-b'
```

### Container bindings

| Abstract | Resolves to |
|---|---|
| `IntacctManager` | Singleton manager |
| `IntacctClient` | `IntacctManager::connection()` |
| `OAuthApplication` | `IntacctManager::application()` |
| `OAuthClient` | `IntacctManager::oauthClient()` |
| `TokenManager` | `IntacctManager::tokenManager()` |
| `ControlAir\Intacct\Auth\Contracts\TokenStore` | `DatabaseTokenStore` |
| `Psr\Http\Client\ClientInterface` | `GuzzleHttp\Client` |
| `Psr\Http\Message\RequestFactoryInterface` | `GuzzleHttp\Psr7\HttpFactory` |
| `Psr\Http\Message\StreamFactoryInterface` | `GuzzleHttp\Psr7\HttpFactory` |
| `Psr\Clock\ClockInterface` | `ControlAir\Intacct\Support\SystemClock` |

The SDK objects in this table always belong to the default connection. Use `IntacctManager` for any other connection.

### Publishable assets

| Tag | Publishes |
|---|---|
| `intacct-config` | `config/intacct.php` |
| `intacct-migrations` | The `intacct_tokens` table migration |

### Errors

| Exception | When |
|---|---|
| `ControlAir\Intacct\Exceptions\ConfigurationException` | The connection does not exist, `client_id` or `token_key` is missing, or the token store driver is not `database` |
| `ControlAir\Intacct\Exceptions\MissingTokenException` | No tokens are stored for the connection, or the access token expired and there is no refresh token |

## Testing

```bash
composer test
composer lint
```

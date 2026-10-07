<?php

declare(strict_types=1);

namespace ControlAir\LaravelIntacct\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * OAuth token set stored for one Intacct token key.
 *
 * @property int $id
 * @property string $key
 * @property string $access_token
 * @property string|null $refresh_token
 * @property string $token_type
 * @property CarbonImmutable $expires_at
 * @property list<string> $scopes
 */
final class IntacctToken extends Model
{
    protected $table = 'intacct_tokens';

    protected $fillable = [
        'key',
        'access_token',
        'refresh_token',
        'token_type',
        'expires_at',
        'scopes',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'expires_at' => 'immutable_datetime',
            'scopes' => 'array',
        ];
    }
}

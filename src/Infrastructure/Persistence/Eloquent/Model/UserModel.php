<?php

declare(strict_types=1);

namespace Shortwave\Infrastructure\Persistence\Eloquent\Model;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Persistence and authentication record for an account.
 *
 * This is not the Account entity. It exists because Sanctum and the auth guard
 * need an Eloquent model, and keeping that requirement here is what lets the
 * domain entity stay free of framework base classes. Nothing outside the
 * Infrastructure namespace should reference it.
 *
 * @property string $id
 * @property string $email
 * @property string $name
 * @property string $plan
 * @property string $password
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 */
final class UserModel extends Authenticatable
{
    use HasApiTokens;
    use HasUlids;

    public $incrementing = false;

    protected $table = 'accounts';

    protected $keyType = 'string';

    protected $fillable = ['id', 'email', 'name', 'plan', 'password'];

    protected $hidden = ['password', 'remember_token'];

    /**
     * @return HasMany<LinkModel, $this>
     */
    public function links(): HasMany
    {
        return $this->hasMany(LinkModel::class, 'account_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}

<?php

declare(strict_types=1);

namespace Shortwave\Infrastructure\Support;

use Illuminate\Contracts\Hashing\Hasher;
use Shortwave\Application\Account\Port\PasswordHasher;

final readonly class BcryptPasswordHasher implements PasswordHasher
{
    public function __construct(private Hasher $hasher) {}

    public function hash(string $plain): string
    {
        return $this->hasher->make($plain);
    }

    public function verify(string $plain, string $hash): bool
    {
        return $this->hasher->check($plain, $hash);
    }
}

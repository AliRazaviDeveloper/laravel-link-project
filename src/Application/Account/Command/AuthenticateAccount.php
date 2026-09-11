<?php

declare(strict_types=1);

namespace Shortwave\Application\Account\Command;

final readonly class AuthenticateAccount
{
    public function __construct(
        public string $email,
        public string $password,
        public string $tokenName = 'default',
    ) {}
}

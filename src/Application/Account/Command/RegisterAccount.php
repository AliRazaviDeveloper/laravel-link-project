<?php

declare(strict_types=1);

namespace Shortwave\Application\Account\Command;

final readonly class RegisterAccount
{
    public function __construct(
        public string $email,
        public string $name,
        public string $password,
        public string $tokenName = 'default',
    ) {}
}

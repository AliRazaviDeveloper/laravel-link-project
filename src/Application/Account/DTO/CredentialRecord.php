<?php

declare(strict_types=1);

namespace Shortwave\Application\Account\DTO;

use Shortwave\Domain\Account\Entity\Account;

final readonly class CredentialRecord
{
    public function __construct(
        public Account $account,
        public string $hashedPassword,
    ) {}
}

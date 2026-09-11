<?php

declare(strict_types=1);

namespace Shortwave\Application\Account\Port;

use Shortwave\Application\Account\DTO\IssuedToken;
use Shortwave\Domain\Account\ValueObject\AccountId;

/**
 * Issues and revokes API credentials. Backed by Sanctum, but the application
 * layer only ever sees a plaintext token it must hand over exactly once.
 */
interface TokenIssuer
{
    /**
     * @param  list<string>  $abilities
     */
    public function issue(AccountId $accountId, string $name, array $abilities): IssuedToken;

    public function revokeCurrent(AccountId $accountId, string $tokenId): void;

    public function revokeAll(AccountId $accountId): int;
}

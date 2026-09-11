<?php

declare(strict_types=1);

namespace Shortwave\Application\Account\Port;

use Shortwave\Application\Account\DTO\CredentialRecord;
use Shortwave\Domain\Account\ValueObject\EmailAddress;

/**
 * Reads an account together with its password hash.
 *
 * Kept off AccountRepository on purpose: the Account entity has no business
 * holding a credential, and only the login use case ever needs the pair.
 */
interface CredentialStore
{
    public function findCredentials(EmailAddress $email): ?CredentialRecord;
}

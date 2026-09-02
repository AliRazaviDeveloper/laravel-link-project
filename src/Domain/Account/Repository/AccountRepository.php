<?php

declare(strict_types=1);

namespace Shortwave\Domain\Account\Repository;

use Shortwave\Domain\Account\Entity\Account;
use Shortwave\Domain\Account\ValueObject\AccountId;
use Shortwave\Domain\Account\ValueObject\EmailAddress;

interface AccountRepository
{
    public function findById(AccountId $id): ?Account;

    public function findByEmail(EmailAddress $email): ?Account;

    /**
     * @throws \Shortwave\Domain\Account\Exception\EmailAlreadyRegistered
     */
    public function add(Account $account, string $hashedPassword): void;

    public function save(Account $account): void;
}

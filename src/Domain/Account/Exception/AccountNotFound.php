<?php

declare(strict_types=1);

namespace Shortwave\Domain\Account\Exception;

use Shortwave\Domain\Account\ValueObject\AccountId;
use Shortwave\Domain\Shared\Exception\DomainException;

final class AccountNotFound extends DomainException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function withId(AccountId $id): self
    {
        return new self(sprintf('No account exists with id "%s".', $id->value));
    }

    public function errorCode(): string
    {
        return 'account_not_found';
    }
}

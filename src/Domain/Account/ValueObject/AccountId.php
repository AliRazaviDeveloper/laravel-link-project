<?php

declare(strict_types=1);

namespace Shortwave\Domain\Account\ValueObject;

use Shortwave\Domain\Shared\ValueObject\Identifier;

final readonly class AccountId extends Identifier
{
    protected static function label(): string
    {
        return 'account id';
    }

    protected static function field(): string
    {
        return 'account_id';
    }
}

<?php

declare(strict_types=1);

namespace Shortwave\Infrastructure\Persistence\Eloquent\Mapper;

use Shortwave\Domain\Account\Entity\Account;
use Shortwave\Domain\Account\ValueObject\AccountId;
use Shortwave\Domain\Account\ValueObject\EmailAddress;
use Shortwave\Domain\Account\ValueObject\Plan;
use Shortwave\Infrastructure\Persistence\Eloquent\Model\UserModel;

final readonly class AccountMapper
{
    public function toDomain(UserModel $model): Account
    {
        return Account::reconstitute(
            id: AccountId::fromString($model->id),
            email: EmailAddress::fromString($model->email),
            name: $model->name,
            // An unrecognised plan string means the row predates a plan rename.
            // Falling back to Free would silently grant the wrong limits, so the
            // column is treated as authoritative and a bad value fails loudly.
            plan: Plan::from($model->plan),
            createdAt: $model->created_at->toDateTimeImmutable(),
        );
    }

    /**
     * @return array<string, string>
     */
    public function toColumns(Account $account): array
    {
        return [
            'id' => $account->id()->value,
            'email' => $account->email()->value,
            'name' => $account->name(),
            'plan' => $account->plan()->value,
        ];
    }
}

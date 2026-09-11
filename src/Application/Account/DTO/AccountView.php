<?php

declare(strict_types=1);

namespace Shortwave\Application\Account\DTO;

use DateTimeImmutable;
use Shortwave\Domain\Account\Entity\Account;
use Shortwave\Domain\Account\ValueObject\Plan;

final readonly class AccountView
{
    public function __construct(
        public string $id,
        public string $email,
        public string $name,
        public Plan $plan,
        public int $linkAllowance,
        public int $requestsPerMinute,
        public int $analyticsRetentionDays,
        public DateTimeImmutable $createdAt,
    ) {}

    public static function fromEntity(Account $account): self
    {
        $plan = $account->plan();

        return new self(
            id: $account->id()->value,
            email: $account->email()->value,
            name: $account->name(),
            plan: $plan,
            linkAllowance: $plan->linkAllowance(),
            requestsPerMinute: $plan->requestsPerMinute(),
            analyticsRetentionDays: $plan->analyticsRetentionDays(),
            createdAt: $account->createdAt(),
        );
    }
}

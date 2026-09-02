<?php

declare(strict_types=1);

namespace Shortwave\Domain\Account\Entity;

use DateTimeImmutable;
use Shortwave\Domain\Account\Exception\LinkAllowanceExceeded;
use Shortwave\Domain\Account\ValueObject\AccountId;
use Shortwave\Domain\Account\ValueObject\EmailAddress;
use Shortwave\Domain\Account\ValueObject\Plan;
use Shortwave\Domain\Shared\Exception\InvariantViolation;

final class Account
{
    private const int MAX_NAME_LENGTH = 120;

    private function __construct(
        private readonly AccountId $id,
        private readonly EmailAddress $email,
        private string $name,
        private Plan $plan,
        private readonly DateTimeImmutable $createdAt,
    ) {}

    public static function register(
        AccountId $id,
        EmailAddress $email,
        string $name,
        Plan $plan,
        DateTimeImmutable $now,
    ): self {
        return new self($id, $email, self::normaliseName($name), $plan, $now);
    }

    public static function reconstitute(
        AccountId $id,
        EmailAddress $email,
        string $name,
        Plan $plan,
        DateTimeImmutable $createdAt,
    ): self {
        return new self($id, $email, $name, $plan, $createdAt);
    }

    /**
     * @throws LinkAllowanceExceeded
     */
    public function assertCanCreateLinks(int $currentLinkCount, int $additional = 1): void
    {
        $allowance = $this->plan->linkAllowance();

        if ($currentLinkCount + $additional > $allowance) {
            throw LinkAllowanceExceeded::forPlan($this->plan, $allowance, $currentLinkCount);
        }
    }

    public function switchTo(Plan $plan): void
    {
        $this->plan = $plan;
    }

    public function id(): AccountId
    {
        return $this->id;
    }

    public function email(): EmailAddress
    {
        return $this->email;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function plan(): Plan
    {
        return $this->plan;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    private static function normaliseName(string $name): string
    {
        $trimmed = trim($name);

        if ($trimmed === '') {
            throw InvariantViolation::for('name', 'A name is required.');
        }

        if (mb_strlen($trimmed) > self::MAX_NAME_LENGTH) {
            throw InvariantViolation::for('name', sprintf(
                'A name may not exceed %d characters.',
                self::MAX_NAME_LENGTH,
            ));
        }

        return $trimmed;
    }
}

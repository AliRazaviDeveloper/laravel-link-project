<?php

declare(strict_types=1);

namespace Shortwave\Domain\Account\Exception;

use Shortwave\Domain\Account\ValueObject\Plan;
use Shortwave\Domain\Shared\Exception\DomainException;

final class LinkAllowanceExceeded extends DomainException
{
    private function __construct(
        private readonly Plan $plan,
        private readonly int $allowance,
        private readonly int $used,
    ) {
        parent::__construct(sprintf(
            'The %s plan allows %d links and %d are already in use.',
            $plan->value,
            $allowance,
            $used,
        ));
    }

    public static function forPlan(Plan $plan, int $allowance, int $used): self
    {
        return new self($plan, $allowance, $used);
    }

    public function errorCode(): string
    {
        return 'link_allowance_exceeded';
    }

    public function context(): array
    {
        return [
            'plan' => $this->plan->value,
            'allowance' => $this->allowance,
            'used' => $this->used,
        ];
    }
}

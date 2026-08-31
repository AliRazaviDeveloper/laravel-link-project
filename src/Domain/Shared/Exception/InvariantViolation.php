<?php

declare(strict_types=1);

namespace Shortwave\Domain\Shared\Exception;

/**
 * A value object or entity was handed input it can never accept.
 *
 * These signal a caller mistake rather than a business rule rejection, which is
 * why they surface as 422 rather than 409.
 */
final class InvariantViolation extends DomainException
{
    private function __construct(
        string $message,
        private readonly string $field,
    ) {
        parent::__construct($message);
    }

    public static function for(string $field, string $message): self
    {
        return new self($message, $field);
    }

    public function errorCode(): string
    {
        return 'invariant_violation';
    }

    public function field(): string
    {
        return $this->field;
    }

    public function context(): array
    {
        return ['field' => $this->field];
    }
}

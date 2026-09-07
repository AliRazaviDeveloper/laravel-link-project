<?php

declare(strict_types=1);

namespace Shortwave\Application\Link\DTO;

use Shortwave\Domain\Shared\Exception\DomainException;

/**
 * What happened to one row of a bulk import. The index is carried so a client can
 * line the result up against the array it sent.
 */
final readonly class ImportOutcome
{
    /**
     * @param  array<string, scalar|array<int|string, scalar>|null>  $context
     */
    private function __construct(
        public int $index,
        public ?LinkView $link,
        public ?string $errorCode,
        public ?string $errorMessage,
        public array $context = [],
    ) {}

    public static function created(int $index, LinkView $link): self
    {
        return new self($index, $link, null, null);
    }

    public static function rejected(int $index, DomainException $exception): self
    {
        return new self(
            $index,
            null,
            $exception->errorCode(),
            $exception->getMessage(),
            $exception->context(),
        );
    }

    public function failed(): bool
    {
        return $this->link === null;
    }
}

<?php

declare(strict_types=1);

namespace Shortwave\Application\Link\DTO;

final readonly class ImportReport
{
    /**
     * @param  list<ImportOutcome>  $outcomes
     */
    public function __construct(public array $outcomes) {}

    public function createdCount(): int
    {
        return count(array_filter($this->outcomes, static fn (ImportOutcome $o): bool => ! $o->failed()));
    }

    public function rejectedCount(): int
    {
        return count(array_filter($this->outcomes, static fn (ImportOutcome $o): bool => $o->failed()));
    }

    /**
     * A mixed batch answers 207 rather than 201, so a client cannot read a 2xx as
     * "everything landed".
     */
    public function isPartial(): bool
    {
        return $this->createdCount() > 0 && $this->rejectedCount() > 0;
    }

    public function isCompleteFailure(): bool
    {
        return $this->createdCount() === 0;
    }
}

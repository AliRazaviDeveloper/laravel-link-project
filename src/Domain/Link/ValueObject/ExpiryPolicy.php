<?php

declare(strict_types=1);

namespace Shortwave\Domain\Link\ValueObject;

use DateTimeImmutable;
use Shortwave\Domain\Shared\Exception\InvariantViolation;

/**
 * When a link should stop resolving.
 *
 * Both limits are optional and independent: a campaign link might expire on a
 * date, a beta invite after a fixed number of uses, and a one-off share on
 * whichever comes first.
 */
final readonly class ExpiryPolicy
{
    private function __construct(
        public ?DateTimeImmutable $expiresAt,
        public ?int $maxClicks,
    ) {}

    public static function never(): self
    {
        return new self(null, null);
    }

    public static function of(?DateTimeImmutable $expiresAt, ?int $maxClicks, DateTimeImmutable $now): self
    {
        if ($expiresAt !== null && $expiresAt <= $now) {
            throw InvariantViolation::for('expires_at', 'The expiry date must be in the future.');
        }

        if ($maxClicks !== null && $maxClicks < 1) {
            throw InvariantViolation::for('max_clicks', 'The click limit must be at least 1.');
        }

        return new self($expiresAt, $maxClicks);
    }

    /**
     * Rebuilds a stored policy without re-checking the future-date rule, which
     * would make every already-expired row unreadable.
     */
    public static function reconstitute(?DateTimeImmutable $expiresAt, ?int $maxClicks): self
    {
        return new self($expiresAt, $maxClicks);
    }

    public function hasExpiredAt(DateTimeImmutable $moment): bool
    {
        return $this->expiresAt !== null && $moment >= $this->expiresAt;
    }

    public function isExhaustedBy(int $clickCount): bool
    {
        return $this->maxClicks !== null && $clickCount >= $this->maxClicks;
    }

    public function isUnlimited(): bool
    {
        return $this->expiresAt === null && $this->maxClicks === null;
    }

    /**
     * How long a resolution may stay in cache before the policy could change the
     * answer. Null means "cache for the default window".
     */
    public function secondsUntilExpiry(DateTimeImmutable $now): ?int
    {
        if ($this->expiresAt === null) {
            return null;
        }

        return max(0, $this->expiresAt->getTimestamp() - $now->getTimestamp());
    }
}

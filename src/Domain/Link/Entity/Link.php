<?php

declare(strict_types=1);

namespace Shortwave\Domain\Link\Entity;

use DateTimeImmutable;
use Shortwave\Domain\Account\ValueObject\AccountId;
use Shortwave\Domain\Link\Enum\LinkStatus;
use Shortwave\Domain\Link\Enum\UnresolvableReason;
use Shortwave\Domain\Link\Event\LinkWasArchived;
use Shortwave\Domain\Link\Event\LinkWasCreated;
use Shortwave\Domain\Link\Event\LinkWasRetargeted;
use Shortwave\Domain\Link\Exception\LinkNotResolvable;
use Shortwave\Domain\Link\ValueObject\Destination;
use Shortwave\Domain\Link\ValueObject\ExpiryPolicy;
use Shortwave\Domain\Link\ValueObject\LinkId;
use Shortwave\Domain\Link\ValueObject\Slug;
use Shortwave\Domain\Shared\Event\RecordsEvents;
use Shortwave\Domain\Shared\Exception\InvariantViolation;

/**
 * The aggregate root.
 *
 * `clickCount` lives here because the expiry policy needs it to decide whether a
 * link may still resolve. It is a counter the aggregate trusts rather than owns:
 * the authoritative per-click record is a document in the analytics store, and
 * Redis holds the hot increment. Postgres is reconciled from Redis, so this
 * number can lag by the flush interval — acceptable, since a click cap is a
 * business guardrail rather than a billing boundary.
 */
final class Link
{
    use RecordsEvents;

    private const int MAX_TITLE_LENGTH = 160;

    private function __construct(
        private readonly LinkId $id,
        private readonly AccountId $accountId,
        private readonly Slug $slug,
        private Destination $destination,
        private ?string $title,
        private LinkStatus $status,
        private ExpiryPolicy $expiry,
        private int $clickCount,
        private readonly DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
    ) {}

    public static function create(
        LinkId $id,
        AccountId $accountId,
        Slug $slug,
        Destination $destination,
        ?string $title,
        ExpiryPolicy $expiry,
        DateTimeImmutable $now,
    ): self {
        $link = new self(
            id: $id,
            accountId: $accountId,
            slug: $slug,
            destination: $destination,
            title: self::normaliseTitle($title),
            status: LinkStatus::Active,
            expiry: $expiry,
            clickCount: 0,
            createdAt: $now,
            updatedAt: $now,
        );

        $link->recordEvent(new LinkWasCreated($id, $accountId, $slug, $destination, $now));

        return $link;
    }

    /**
     * Rebuilds an aggregate from storage. Skips the creation event and every
     * "must be in the future" rule, which only apply to fresh input.
     */
    public static function reconstitute(
        LinkId $id,
        AccountId $accountId,
        Slug $slug,
        Destination $destination,
        ?string $title,
        LinkStatus $status,
        ExpiryPolicy $expiry,
        int $clickCount,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            $id,
            $accountId,
            $slug,
            $destination,
            $title,
            $status,
            $expiry,
            $clickCount,
            $createdAt,
            $updatedAt,
        );
    }

    public function retargetTo(Destination $destination, DateTimeImmutable $now): void
    {
        if ($this->destination->equals($destination)) {
            return;
        }

        $previous = $this->destination;
        $this->destination = $destination;
        $this->touch($now);

        $this->recordEvent(new LinkWasRetargeted($this->id, $this->slug, $previous, $destination, $now));
    }

    public function rename(?string $title, DateTimeImmutable $now): void
    {
        $normalised = self::normaliseTitle($title);

        if ($normalised === $this->title) {
            return;
        }

        $this->title = $normalised;
        $this->touch($now);
    }

    public function applyExpiry(ExpiryPolicy $expiry, DateTimeImmutable $now): void
    {
        $this->expiry = $expiry;
        $this->touch($now);
    }

    public function archive(DateTimeImmutable $now): void
    {
        if ($this->status === LinkStatus::Archived) {
            return;
        }

        $this->status = LinkStatus::Archived;
        $this->touch($now);

        $this->recordEvent(new LinkWasArchived($this->id, $this->slug, $now));
    }

    public function restore(DateTimeImmutable $now): void
    {
        if ($this->status === LinkStatus::Active) {
            return;
        }

        $this->status = LinkStatus::Active;
        $this->touch($now);
    }

    /**
     * The single place that decides whether traffic may follow this link.
     *
     * @throws LinkNotResolvable
     */
    public function resolve(DateTimeImmutable $now): Destination
    {
        $reason = $this->blockingReason($now);

        if ($reason !== null) {
            throw LinkNotResolvable::because($this->slug, $reason);
        }

        return $this->destination;
    }

    public function isResolvableAt(DateTimeImmutable $now): bool
    {
        return $this->blockingReason($now) === null;
    }

    /**
     * Counts a click locally so a cached aggregate stays consistent with the
     * cap it enforces. Durable counting happens in the analytics pipeline.
     */
    public function registerClick(): void
    {
        $this->clickCount++;
    }

    public function belongsTo(AccountId $accountId): bool
    {
        return $this->accountId->equals($accountId);
    }

    public function id(): LinkId
    {
        return $this->id;
    }

    public function accountId(): AccountId
    {
        return $this->accountId;
    }

    public function slug(): Slug
    {
        return $this->slug;
    }

    public function destination(): Destination
    {
        return $this->destination;
    }

    public function title(): ?string
    {
        return $this->title;
    }

    public function status(): LinkStatus
    {
        return $this->status;
    }

    public function expiry(): ExpiryPolicy
    {
        return $this->expiry;
    }

    public function clickCount(): int
    {
        return $this->clickCount;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function blockingReason(DateTimeImmutable $now): ?UnresolvableReason
    {
        return match (true) {
            ! $this->status->isActive() => UnresolvableReason::Archived,
            $this->expiry->hasExpiredAt($now) => UnresolvableReason::Expired,
            $this->expiry->isExhaustedBy($this->clickCount) => UnresolvableReason::ClickLimitReached,
            default => null,
        };
    }

    private function touch(DateTimeImmutable $now): void
    {
        $this->updatedAt = $now;
    }

    private static function normaliseTitle(?string $title): ?string
    {
        if ($title === null) {
            return null;
        }

        $trimmed = trim($title);

        if ($trimmed === '') {
            return null;
        }

        if (mb_strlen($trimmed) > self::MAX_TITLE_LENGTH) {
            throw InvariantViolation::for('title', sprintf(
                'A title may not exceed %d characters.',
                self::MAX_TITLE_LENGTH,
            ));
        }

        return $trimmed;
    }
}

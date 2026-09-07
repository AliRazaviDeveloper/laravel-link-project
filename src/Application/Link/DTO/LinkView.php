<?php

declare(strict_types=1);

namespace Shortwave\Application\Link\DTO;

use DateTimeImmutable;
use Shortwave\Domain\Link\Entity\Link;
use Shortwave\Domain\Link\Enum\LinkStatus;

/**
 * Read-side shape of a link.
 *
 * The HTTP layer never receives an aggregate, which keeps entity getters free to
 * change without breaking a serialiser somewhere downstream.
 */
final readonly class LinkView
{
    public function __construct(
        public string $id,
        public string $slug,
        public string $shortUrl,
        public string $destinationUrl,
        public ?string $title,
        public LinkStatus $status,
        public ?DateTimeImmutable $expiresAt,
        public ?int $maxClicks,
        public int $clickCount,
        public bool $resolvable,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {}

    public static function fromEntity(Link $link, string $baseUrl, DateTimeImmutable $now): self
    {
        return new self(
            id: $link->id()->value,
            slug: $link->slug()->value,
            shortUrl: rtrim($baseUrl, '/').'/'.$link->slug()->value,
            destinationUrl: $link->destination()->value,
            title: $link->title(),
            status: $link->status(),
            expiresAt: $link->expiry()->expiresAt,
            maxClicks: $link->expiry()->maxClicks,
            clickCount: $link->clickCount(),
            resolvable: $link->isResolvableAt($now),
            createdAt: $link->createdAt(),
            updatedAt: $link->updatedAt(),
        );
    }
}

<?php

declare(strict_types=1);

namespace Shortwave\Presentation\Http\Resource\V1;

use DateTimeInterface;
use Shortwave\Application\Link\DTO\LinkView;

/**
 * Serialises a link for the v1 API.
 *
 * A plain class rather than a JsonResource: these take an application DTO, not an
 * Eloquent model, and none of the framework's lazy-relation machinery applies. The
 * upside is that the wire format for v1 is a single readable array — which is what
 * a versioned contract needs to be.
 */
final readonly class LinkResource
{
    /**
     * @return array<string, mixed>
     */
    public static function one(LinkView $link): array
    {
        return [
            'id' => $link->id,
            'slug' => $link->slug,
            'short_url' => $link->shortUrl,
            'destination_url' => $link->destinationUrl,
            'title' => $link->title,
            'status' => $link->status->value,
            'resolvable' => $link->resolvable,
            'expires_at' => $link->expiresAt?->format(DateTimeInterface::RFC3339),
            'max_clicks' => $link->maxClicks,
            'click_count' => $link->clickCount,
            'created_at' => $link->createdAt->format(DateTimeInterface::RFC3339),
            'updated_at' => $link->updatedAt->format(DateTimeInterface::RFC3339),
        ];
    }

    /**
     * @param  list<LinkView>  $links
     * @return list<array<string, mixed>>
     */
    public static function many(array $links): array
    {
        return array_map(self::one(...), $links);
    }
}

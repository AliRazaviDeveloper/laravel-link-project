<?php

declare(strict_types=1);

namespace Shortwave\Application\Link\DTO;

/**
 * The redirect hot path's answer.
 *
 * Deliberately tiny and free of value objects: it is what gets serialised into
 * Redis, so every field here is a cache-format decision. Changing the shape means
 * bumping the cache namespace version.
 */
final readonly class Resolution
{
    public function __construct(
        public string $linkId,
        public string $accountId,
        public string $destinationUrl,
        public bool $cacheHit = false,
    ) {}

    public function asCacheHit(): self
    {
        return new self($this->linkId, $this->accountId, $this->destinationUrl, true);
    }

    /**
     * @return array{link_id: string, account_id: string, destination_url: string}
     */
    public function toCacheArray(): array
    {
        return [
            'link_id' => $this->linkId,
            'account_id' => $this->accountId,
            'destination_url' => $this->destinationUrl,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromCacheArray(array $payload): ?self
    {
        $linkId = $payload['link_id'] ?? null;
        $accountId = $payload['account_id'] ?? null;
        $destination = $payload['destination_url'] ?? null;

        if (! is_string($linkId) || ! is_string($accountId) || ! is_string($destination)) {
            return null;
        }

        return new self($linkId, $accountId, $destination, true);
    }
}

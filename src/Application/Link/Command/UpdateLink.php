<?php

declare(strict_types=1);

namespace Shortwave\Application\Link\Command;

use Shortwave\Application\Shared\Support\Input;

/**
 * Partial update. Every field is nullable, so `null` cannot mean "clear this" —
 * the presence flags below carry that intent instead, which is what lets a client
 * remove an expiry date by sending `"expires_at": null`.
 */
final readonly class UpdateLink
{
    public function __construct(
        public string $accountId,
        public string $linkId,
        public ?string $destinationUrl = null,
        public ?string $title = null,
        public bool $titleProvided = false,
        public ?string $expiresAt = null,
        public bool $expiresAtProvided = false,
        public ?int $maxClicks = null,
        public bool $maxClicksProvided = false,
    ) {}

    /**
     * @param  array<string, mixed>  $payload  the validated request body
     */
    public static function fromPayload(string $accountId, string $linkId, array $payload): self
    {
        $string = static fn (string $key): ?string => is_string($payload[$key] ?? null)
            ? $payload[$key]
            : null;

        return new self(
            accountId: $accountId,
            linkId: $linkId,
            destinationUrl: $string('destination_url'),
            title: $string('title'),
            titleProvided: array_key_exists('title', $payload),
            expiresAt: $string('expires_at'),
            expiresAtProvided: array_key_exists('expires_at', $payload),
            maxClicks: Input::nullableInt($payload, 'max_clicks'),
            maxClicksProvided: array_key_exists('max_clicks', $payload),
        );
    }

    public function touchesExpiry(): bool
    {
        return $this->expiresAtProvided || $this->maxClicksProvided;
    }
}

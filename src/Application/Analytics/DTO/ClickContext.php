<?php

declare(strict_types=1);

namespace Shortwave\Application\Analytics\DTO;

use DateTimeImmutable;
use Shortwave\Domain\Analytics\ValueObject\DeviceProfile;
use Shortwave\Domain\Analytics\ValueObject\GeoLocation;
use Shortwave\Domain\Analytics\ValueObject\Referrer;
use Shortwave\Domain\Analytics\ValueObject\VisitorFingerprint;

/**
 * Everything worth keeping about one inbound request.
 *
 * Built by the HTTP layer before the link is known — the redirect handler learns
 * the link id only after resolving the slug — so the context arrives incomplete
 * and is completed with `forLink()`. Raw IPs and User-Agent strings are already
 * gone by the time an instance exists: they are reduced to a fingerprint and a
 * device profile at the edge of the system, which is the only place they appear.
 */
final readonly class ClickContext
{
    public function __construct(
        public DateTimeImmutable $occurredAt,
        public VisitorFingerprint $visitor,
        public DeviceProfile $device,
        public Referrer $referrer,
        public GeoLocation $geo,
        public ?string $linkId = null,
        public ?string $accountId = null,
    ) {}

    public function forLink(string $linkId, string $accountId): self
    {
        return new self(
            $this->occurredAt,
            $this->visitor,
            $this->device,
            $this->referrer,
            $this->geo,
            $linkId,
            $accountId,
        );
    }

    public function isAttributed(): bool
    {
        return $this->linkId !== null && $this->accountId !== null;
    }

    /**
     * Wire format for the Redis buffer that sits between the redirect and the
     * analytics store.
     *
     * @return array<string, string|int>
     */
    public function toArray(): array
    {
        return [
            'link_id' => (string) $this->linkId,
            'account_id' => (string) $this->accountId,
            'occurred_at' => $this->occurredAt->format(DateTimeImmutable::RFC3339_EXTENDED),
            'visitor' => $this->visitor->hash,
            'device_type' => $this->device->type->value,
            'browser' => $this->device->browser,
            'platform' => $this->device->platform,
            'referrer' => $this->referrer->host,
            'country' => $this->geo->country,
        ];
    }
}

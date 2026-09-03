<?php

declare(strict_types=1);

namespace Shortwave\Domain\Analytics\Entity;

use DateTimeImmutable;
use Shortwave\Domain\Account\ValueObject\AccountId;
use Shortwave\Domain\Analytics\ValueObject\ClickEventId;
use Shortwave\Domain\Analytics\ValueObject\DeviceProfile;
use Shortwave\Domain\Analytics\ValueObject\GeoLocation;
use Shortwave\Domain\Analytics\ValueObject\Referrer;
use Shortwave\Domain\Analytics\ValueObject\VisitorFingerprint;
use Shortwave\Domain\Link\ValueObject\LinkId;

/**
 * One redirect served.
 *
 * Immutable by construction: the analytics store is append-only, and correcting
 * a click means writing a compensating document rather than editing history.
 */
final readonly class ClickEvent
{
    public function __construct(
        public ClickEventId $id,
        public LinkId $linkId,
        public AccountId $accountId,
        public DateTimeImmutable $occurredAt,
        public VisitorFingerprint $visitor,
        public DeviceProfile $device,
        public Referrer $referrer,
        public GeoLocation $geo,
    ) {}

    public function isHumanTraffic(): bool
    {
        return $this->device->type->countsAsHuman();
    }
}

<?php

declare(strict_types=1);

namespace Shortwave\Domain\Analytics\Enum;

enum DeviceType: string
{
    case Desktop = 'desktop';
    case Mobile = 'mobile';
    case Tablet = 'tablet';
    case Bot = 'bot';
    case Unknown = 'unknown';

    /**
     * Bot traffic is stored but excluded from headline counts, so a crawler
     * sweep does not read as a campaign spike.
     */
    public function countsAsHuman(): bool
    {
        return $this !== self::Bot;
    }
}

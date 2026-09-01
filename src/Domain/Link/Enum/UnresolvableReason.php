<?php

declare(strict_types=1);

namespace Shortwave\Domain\Link\Enum;

enum UnresolvableReason: string
{
    case Archived = 'archived';
    case Expired = 'expired';
    case ClickLimitReached = 'click_limit_reached';

    public function describe(): string
    {
        return match ($this) {
            self::Archived => 'This link has been archived.',
            self::Expired => 'This link is past its expiry date.',
            self::ClickLimitReached => 'This link has reached its click limit.',
        };
    }
}

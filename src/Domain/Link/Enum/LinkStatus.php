<?php

declare(strict_types=1);

namespace Shortwave\Domain\Link\Enum;

enum LinkStatus: string
{
    case Active = 'active';
    case Archived = 'archived';

    public function isActive(): bool
    {
        return $this === self::Active;
    }
}

<?php

declare(strict_types=1);

namespace Shortwave\Domain\Analytics\ValueObject;

use Shortwave\Domain\Shared\ValueObject\Identifier;

final readonly class ClickEventId extends Identifier
{
    protected static function label(): string
    {
        return 'click event id';
    }

    protected static function field(): string
    {
        return 'click_event_id';
    }
}

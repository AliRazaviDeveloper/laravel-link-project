<?php

declare(strict_types=1);

namespace Shortwave\Domain\Link\ValueObject;

use Shortwave\Domain\Shared\ValueObject\Identifier;

final readonly class LinkId extends Identifier
{
    protected static function label(): string
    {
        return 'link id';
    }

    protected static function field(): string
    {
        return 'link_id';
    }
}

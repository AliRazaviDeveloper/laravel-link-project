<?php

declare(strict_types=1);

namespace Shortwave\Application\Shared\Contract;

use Shortwave\Domain\Link\ValueObject\Slug;

interface SlugFactory
{
    /**
     * Produces a slug that is free at the moment of the call.
     *
     * The unique index still has the final say — two generators can race — so
     * callers must be ready for SlugAlreadyTaken regardless.
     *
     * @throws \Shortwave\Application\Shared\Exception\SlugExhausted when no free
     *                                                               slug was found within the attempt budget
     */
    public function generate(): Slug;
}

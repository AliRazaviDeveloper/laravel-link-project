<?php

declare(strict_types=1);

namespace Shortwave\Domain\Link\Exception;

use Shortwave\Domain\Link\ValueObject\Slug;
use Shortwave\Domain\Shared\Exception\DomainException;

final class SlugAlreadyTaken extends DomainException
{
    private function __construct(private readonly Slug $slug)
    {
        parent::__construct(sprintf('The slug "%s" is already in use.', $slug->value));
    }

    public static function for(Slug $slug): self
    {
        return new self($slug);
    }

    public function errorCode(): string
    {
        return 'slug_already_taken';
    }

    public function context(): array
    {
        return ['slug' => $this->slug->value];
    }
}

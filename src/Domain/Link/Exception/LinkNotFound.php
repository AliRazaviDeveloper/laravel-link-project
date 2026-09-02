<?php

declare(strict_types=1);

namespace Shortwave\Domain\Link\Exception;

use Shortwave\Domain\Link\ValueObject\LinkId;
use Shortwave\Domain\Link\ValueObject\Slug;
use Shortwave\Domain\Shared\Exception\DomainException;

final class LinkNotFound extends DomainException
{
    /**
     * @param  array<string, scalar|null>  $context
     */
    private function __construct(string $message, private readonly array $context)
    {
        parent::__construct($message);
    }

    public static function withId(LinkId $id): self
    {
        return new self(
            sprintf('No link exists with id "%s".', $id->value),
            ['link_id' => $id->value],
        );
    }

    public static function withSlug(Slug $slug): self
    {
        return new self(
            sprintf('No link exists with slug "%s".', $slug->value),
            ['slug' => $slug->value],
        );
    }

    public function errorCode(): string
    {
        return 'link_not_found';
    }

    public function context(): array
    {
        return $this->context;
    }
}

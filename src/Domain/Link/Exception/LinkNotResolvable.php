<?php

declare(strict_types=1);

namespace Shortwave\Domain\Link\Exception;

use Shortwave\Domain\Link\Enum\UnresolvableReason;
use Shortwave\Domain\Link\ValueObject\Slug;
use Shortwave\Domain\Shared\Exception\DomainException;

/**
 * The link exists but is no longer serving traffic. Distinct from LinkNotFound
 * so the redirect endpoint can answer 410 instead of 404.
 */
final class LinkNotResolvable extends DomainException
{
    private function __construct(
        public readonly Slug $slug,
        public readonly UnresolvableReason $reason,
    ) {
        parent::__construct($reason->describe());
    }

    public static function because(Slug $slug, UnresolvableReason $reason): self
    {
        return new self($slug, $reason);
    }

    public function errorCode(): string
    {
        return 'link_not_resolvable';
    }

    public function context(): array
    {
        return [
            'slug' => $this->slug->value,
            'reason' => $this->reason->value,
        ];
    }
}

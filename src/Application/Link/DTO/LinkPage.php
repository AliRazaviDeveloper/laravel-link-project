<?php

declare(strict_types=1);

namespace Shortwave\Application\Link\DTO;

final readonly class LinkPage
{
    /**
     * @param  list<LinkView>  $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
    ) {}

    public function lastPage(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }

    public function hasMore(): bool
    {
        return $this->page < $this->lastPage();
    }
}

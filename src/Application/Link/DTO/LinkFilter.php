<?php

declare(strict_types=1);

namespace Shortwave\Application\Link\DTO;

use Shortwave\Application\Shared\Support\Input;
use Shortwave\Domain\Link\Enum\LinkStatus;

final readonly class LinkFilter
{
    public const int MAX_PER_PAGE = 100;

    public const array SORTABLE = ['created_at', 'updated_at', 'click_count', 'slug'];

    public function __construct(
        public ?LinkStatus $status = null,
        public ?string $search = null,
        public string $sortBy = 'created_at',
        public bool $descending = true,
        public int $page = 1,
        public int $perPage = 25,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromArray(array $input): self
    {
        $status = isset($input['status']) && is_string($input['status'])
            ? LinkStatus::tryFrom($input['status'])
            : null;

        $sortBy = is_string($input['sort_by'] ?? null) && in_array($input['sort_by'], self::SORTABLE, true)
            ? $input['sort_by']
            : 'created_at';

        $search = is_string($input['search'] ?? null) ? trim($input['search']) : null;

        return new self(
            status: $status,
            search: $search === '' ? null : $search,
            sortBy: $sortBy,
            descending: ($input['sort_dir'] ?? 'desc') !== 'asc',
            page: max(1, Input::nullableInt($input, 'page') ?? 1),
            perPage: min(self::MAX_PER_PAGE, max(1, Input::nullableInt($input, 'per_page') ?? 25)),
        );
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }
}

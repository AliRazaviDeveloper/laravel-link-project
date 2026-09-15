<?php

declare(strict_types=1);

namespace Shortwave\Presentation\Http\Request\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Shortwave\Application\Link\DTO\LinkFilter;
use Shortwave\Domain\Link\Enum\LinkStatus;

final class ListLinksRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::enum(LinkStatus::class)],
            'search' => ['sometimes', 'string', 'max:120'],
            // Whitelisted rather than passed through: an arbitrary column name in an
            // ORDER BY is both an injection surface and an easy way to ask for an
            // unindexed sort over a large table.
            'sort_by' => ['sometimes', Rule::in(LinkFilter::SORTABLE)],
            'sort_dir' => ['sometimes', Rule::in(['asc', 'desc'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.LinkFilter::MAX_PER_PAGE],
        ];
    }

    public function toFilter(): LinkFilter
    {
        return LinkFilter::fromArray($this->validated());
    }
}

<?php

declare(strict_types=1);

namespace Shortwave\Presentation\Http\Request\V1;

use Illuminate\Foundation\Http\FormRequest;
use Shortwave\Domain\Link\ValueObject\Destination;
use Shortwave\Domain\Link\ValueObject\Slug;

final class StoreLinkRequest extends FormRequest
{
    /**
     * Shape and size only.
     *
     * The real rules — which schemes are allowed, which hosts are refused, which
     * slugs are reserved — live in the value objects, so they hold for a CLI import
     * and a queued job too, not just for requests that came through this class.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'destination_url' => ['required', 'string', 'max:'.Destination::MAX_LENGTH],
            'slug' => ['sometimes', 'nullable', 'string', 'min:'.Slug::MIN_LENGTH, 'max:'.Slug::MAX_LENGTH],
            'title' => ['sometimes', 'nullable', 'string', 'max:160'],
            'expires_at' => ['sometimes', 'nullable', 'date'],
            'max_clicks' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100000000'],
        ];
    }
}

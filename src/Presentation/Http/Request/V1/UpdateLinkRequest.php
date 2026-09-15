<?php

declare(strict_types=1);

namespace Shortwave\Presentation\Http\Request\V1;

use Illuminate\Foundation\Http\FormRequest;
use Shortwave\Domain\Link\ValueObject\Destination;

/**
 * The slug is deliberately not updatable: changing it would break every already
 * shared copy of the link. A caller who wants a different slug creates a new link.
 */
final class UpdateLinkRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'destination_url' => ['sometimes', 'string', 'max:'.Destination::MAX_LENGTH],
            'title' => ['sometimes', 'nullable', 'string', 'max:160'],
            'expires_at' => ['sometimes', 'nullable', 'date'],
            'max_clicks' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100000000'],
        ];
    }

    /**
     * Only the keys the client actually sent.
     *
     * The update command distinguishes "omitted" from "sent as null" — that is how a
     * client clears an expiry date — so this must not be `validated()` with defaults
     * filled in, or every PATCH would silently clear the fields it left out.
     *
     * @return array<string, mixed>
     */
    public function changedFields(): array
    {
        // `keys()` covers JSON and form-encoded bodies alike, unlike reading
        // `json()` directly.
        return array_intersect_key($this->validated(), array_flip($this->keys()));
    }
}

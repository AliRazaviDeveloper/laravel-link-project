<?php

declare(strict_types=1);

namespace Shortwave\Presentation\Http\Request\V1;

use Illuminate\Foundation\Http\FormRequest;
use Shortwave\Application\Link\Command\CreateLink;
use Shortwave\Application\Link\Command\ImportLinks;
use Shortwave\Application\Shared\Support\Input;
use Shortwave\Domain\Link\ValueObject\Destination;
use Shortwave\Domain\Link\ValueObject\Slug;

final class ImportLinksRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'links' => ['required', 'array', 'min:1', 'max:'.ImportLinks::MAX_BATCH],
            'links.*.destination_url' => ['required', 'string', 'max:'.Destination::MAX_LENGTH],
            'links.*.slug' => ['sometimes', 'nullable', 'string', 'min:'.Slug::MIN_LENGTH, 'max:'.Slug::MAX_LENGTH],
            'links.*.title' => ['sometimes', 'nullable', 'string', 'max:160'],
            'links.*.expires_at' => ['sometimes', 'nullable', 'date'],
            'links.*.max_clicks' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100000000'],
            'stop_on_first_error' => ['sometimes', 'boolean'],
        ];
    }

    public function toCommand(string $accountId): ImportLinks
    {
        /** @var array{links: list<array<string, mixed>>, stop_on_first_error?: bool} $payload */
        $payload = $this->validated();

        $rows = array_map(
            static fn (array $row): CreateLink => new CreateLink(
                accountId: $accountId,
                destinationUrl: Input::string($row, 'destination_url'),
                slug: Input::nullableString($row, 'slug'),
                title: Input::nullableString($row, 'title'),
                expiresAt: Input::nullableString($row, 'expires_at'),
                maxClicks: Input::nullableInt($row, 'max_clicks'),
            ),
            $payload['links'],
        );

        return new ImportLinks(
            accountId: $accountId,
            links: array_values($rows),
            stopOnFirstError: $payload['stop_on_first_error'] ?? false,
        );
    }
}

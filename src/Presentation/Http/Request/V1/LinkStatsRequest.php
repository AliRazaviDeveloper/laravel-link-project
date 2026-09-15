<?php

declare(strict_types=1);

namespace Shortwave\Presentation\Http\Request\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Shortwave\Domain\Analytics\Enum\BreakdownDimension;
use Shortwave\Domain\Analytics\Enum\Granularity;

final class LinkStatsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from' => ['sometimes', 'date'],
            'until' => ['sometimes', 'date', 'after:from'],
            'granularity' => ['sometimes', Rule::enum(Granularity::class)],
            'dimensions' => ['sometimes', 'array', 'max:5'],
            'dimensions.*' => [Rule::enum(BreakdownDimension::class)],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }

    public function granularity(): Granularity
    {
        $value = $this->validated('granularity');

        return is_string($value)
            ? Granularity::from($value)
            : Granularity::Day;
    }

    /**
     * @return list<BreakdownDimension>
     */
    public function dimensions(): array
    {
        $values = $this->validated('dimensions');

        if (! is_array($values)) {
            return [];
        }

        return array_values(array_map(
            static fn (string $value): BreakdownDimension => BreakdownDimension::from($value),
            array_filter($values, is_string(...)),
        ));
    }
}

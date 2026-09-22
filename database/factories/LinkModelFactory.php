<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Shortwave\Domain\Link\Enum\LinkStatus;
use Shortwave\Infrastructure\Persistence\Eloquent\Model\LinkModel;

/**
 * Rows for tests that need many links without caring about their contents.
 *
 * Deliberately not a substitute for the API: anything asserting on behaviour creates
 * links through the endpoint, so the aggregate's invariants and the slug generator
 * stay in the path. This exists for the cases where only the row count matters —
 * approaching a plan allowance, or filling a page of a list.
 *
 * @extends Factory<LinkModel>
 */
final class LinkModelFactory extends Factory
{
    protected $model = LinkModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $host = $this->faker->domainName();

        return [
            'id' => strtoupper((string) Str::ulid()),
            'slug' => Str::lower(Str::random(7)),
            'destination_url' => 'https://'.$host.'/'.$this->faker->slug(),
            'destination_host' => $host,
            'title' => $this->faker->boolean() ? $this->faker->sentence(3) : null,
            'status' => LinkStatus::Active->value,
            'expires_at' => null,
            'max_clicks' => null,
            'click_count' => 0,
        ];
    }

    public function archived(): self
    {
        return $this->state(fn (): array => ['status' => LinkStatus::Archived->value]);
    }

    public function expired(): self
    {
        return $this->state(fn (): array => ['expires_at' => now()->subDay()]);
    }

    public function capped(int $maxClicks = 1): self
    {
        return $this->state(fn (): array => ['max_clicks' => $maxClicks]);
    }
}

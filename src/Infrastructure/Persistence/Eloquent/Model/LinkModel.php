<?php

declare(strict_types=1);

namespace Shortwave\Infrastructure\Persistence\Eloquent\Model;

use Database\Factories\LinkModelFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Row mapping for links. Holds no behaviour on purpose — the rules live in the
 * Link aggregate, and a model with logic in it is how they end up in two places.
 *
 * @property string $id
 * @property string $account_id
 * @property string $slug
 * @property string $destination_url
 * @property string|null $title
 * @property string $status
 * @property string $destination_host
 * @property \Carbon\CarbonImmutable|null $expires_at
 * @property int|null $max_clicks
 * @property int $click_count
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 */
final class LinkModel extends Model
{
    /** @use HasFactory<LinkModelFactory> */
    use HasFactory;

    public $incrementing = false;

    protected $table = 'links';

    protected $keyType = 'string';

    protected $guarded = [];

    /**
     * Named explicitly rather than guessed. The framework's resolver derives a factory
     * class name from an `App\Models`-shaped namespace, which this project does not use.
     *
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return LinkModelFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_datetime',
            'max_clicks' => 'integer',
            'click_count' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}

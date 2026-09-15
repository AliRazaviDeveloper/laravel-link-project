<?php

declare(strict_types=1);

namespace Shortwave\Presentation\Http\Resource\V1;

use DateTimeInterface;
use Shortwave\Application\Analytics\DTO\LinkStatsView;
use Shortwave\Domain\Analytics\ValueObject\BreakdownSlice;
use Shortwave\Domain\Analytics\ValueObject\TimeBucket;

final readonly class LinkStatsResource
{
    /**
     * @return array<string, mixed>
     */
    public static function one(LinkStatsView $stats): array
    {
        $peak = $stats->peak();

        return [
            'link_id' => $stats->linkId,
            'window' => [
                'from' => $stats->from->format(DateTimeInterface::RFC3339),
                'until' => $stats->until->format(DateTimeInterface::RFC3339),
                'granularity' => $stats->granularity->value,
            ],
            'totals' => [
                'clicks' => $stats->totals->clicks,
                'unique_visitors' => $stats->totals->uniqueVisitors,
                'bot_clicks' => $stats->totals->botClicks,
                'returning_share_percent' => $stats->totals->returningShare(),
            ],
            'peak' => $peak === null ? null : [
                'starts_at' => $peak->startsAt->format(DateTimeInterface::RFC3339),
                'clicks' => $peak->clicks,
            ],
            'series' => array_map(
                static fn (TimeBucket $bucket): array => [
                    'starts_at' => $bucket->startsAt->format(DateTimeInterface::RFC3339),
                    'clicks' => $bucket->clicks,
                    'unique_visitors' => $bucket->uniqueVisitors,
                ],
                $stats->series,
            ),
            'breakdowns' => array_map(
                static fn (array $slices): array => array_map(
                    static fn (BreakdownSlice $slice): array => [
                        'label' => $slice->label,
                        'clicks' => $slice->clicks,
                        'share_percent' => $slice->shareOf($stats->totals->clicks),
                    ],
                    $slices,
                ),
                $stats->breakdowns,
            ),
            // Surfaced so a client can tell a cached report from a fresh one without
            // guessing from latency.
            'meta' => ['cached' => $stats->fromCache],
        ];
    }
}

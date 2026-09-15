<?php

declare(strict_types=1);

namespace Shortwave\Presentation\Http\Resource\V1;

use DateTimeInterface;
use Shortwave\Application\Analytics\DTO\AccountOverviewView;

final readonly class OverviewResource
{
    /**
     * @return array<string, mixed>
     */
    public static function one(AccountOverviewView $overview): array
    {
        return [
            'window' => [
                'from' => $overview->from->format(DateTimeInterface::RFC3339),
                'until' => $overview->until->format(DateTimeInterface::RFC3339),
            ],
            'totals' => [
                'clicks' => $overview->totals->clicks,
                'unique_visitors' => $overview->totals->uniqueVisitors,
                'bot_clicks' => $overview->totals->botClicks,
                'returning_share_percent' => $overview->totals->returningShare(),
            ],
            'inventory' => [
                'links' => $overview->linkCount,
                'link_allowance' => $overview->planLinkAllowance,
                'allowance_used_percent' => $overview->allowanceUsedPercent(),
            ],
            'top_links' => $overview->topLinks,
        ];
    }
}

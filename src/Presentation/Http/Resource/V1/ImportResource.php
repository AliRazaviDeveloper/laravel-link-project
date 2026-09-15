<?php

declare(strict_types=1);

namespace Shortwave\Presentation\Http\Resource\V1;

use Shortwave\Application\Link\DTO\ImportOutcome;
use Shortwave\Application\Link\DTO\ImportReport;

final readonly class ImportResource
{
    /**
     * @return array<string, mixed>
     */
    public static function one(ImportReport $report): array
    {
        return [
            'summary' => [
                'created' => $report->createdCount(),
                'rejected' => $report->rejectedCount(),
            ],
            'results' => array_map(self::outcome(...), $report->outcomes),
        ];
    }

    /**
     * 207 when the batch was mixed, so a client cannot read a flat 201 as "all of
     * them landed"; 422 when nothing was created at all.
     */
    public static function statusFor(ImportReport $report): int
    {
        return match (true) {
            $report->isCompleteFailure() => 422,
            $report->isPartial() => 207,
            default => 201,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private static function outcome(ImportOutcome $outcome): array
    {
        if ($outcome->link === null) {
            return [
                'index' => $outcome->index,
                'status' => 'rejected',
                'error' => [
                    'code' => $outcome->errorCode,
                    'detail' => $outcome->errorMessage,
                    ...$outcome->context,
                ],
            ];
        }

        return [
            'index' => $outcome->index,
            'status' => 'created',
            'link' => LinkResource::one($outcome->link),
        ];
    }
}

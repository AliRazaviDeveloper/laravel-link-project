<?php

declare(strict_types=1);

namespace Shortwave\Infrastructure\Persistence\Mongo\Repository;

use DateTimeImmutable;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;
use MongoDB\Laravel\Connection;
use Shortwave\Domain\Account\ValueObject\AccountId;
use Shortwave\Domain\Analytics\Entity\ClickEvent;
use Shortwave\Domain\Analytics\Enum\BreakdownDimension;
use Shortwave\Domain\Analytics\Enum\DeviceType;
use Shortwave\Domain\Analytics\Enum\Granularity;
use Shortwave\Domain\Analytics\Repository\ClickEventRepository;
use Shortwave\Domain\Analytics\ValueObject\BreakdownSlice;
use Shortwave\Domain\Analytics\ValueObject\ClickTotals;
use Shortwave\Domain\Analytics\ValueObject\TimeBucket;
use Shortwave\Domain\Link\ValueObject\LinkId;
use Shortwave\Domain\Shared\ValueObject\DateRange;
use Shortwave\Infrastructure\Persistence\Mongo\Mapper\ClickEventMapper;
use Shortwave\Infrastructure\Persistence\Mongo\Model\ClickEventDocument;

/**
 * Aggregation-backed implementation of the click log.
 *
 * Two conventions run through every pipeline here:
 *
 *  - `$match` comes first and always includes `link_id` or `account_id` plus the
 *    date range, so the compound indexes created by `shortwave:mongo-sync` can
 *    serve it without a collection scan;
 *  - bot traffic is excluded from headline numbers but counted separately, so a
 *    crawler sweep does not read as a campaign spike while still being visible.
 *
 * Unique visitors come from `$addToSet` over the daily fingerprint. That is exact
 * rather than estimated, which matters for small numbers where a HyperLogLog sketch
 * would be visibly wrong; the memory cost is bounded by the `$limit` on cardinality
 * that the index and date window impose.
 */
final readonly class MongoClickEventRepository implements ClickEventRepository
{
    public function __construct(private Connection $connection) {}

    public function appendMany(array $events): void
    {
        if ($events === []) {
            return;
        }

        $documents = array_map(
            static fn (ClickEvent $event): array => ClickEventMapper::toDocument($event),
            $events,
        );

        // Unordered so one malformed document cannot abort the rest of the batch,
        // and unacknowledged durability is deliberately *not* used: losing a whole
        // batch silently is worse than the small write-concern latency.
        $this->collection()->insertMany($documents, ['ordered' => false]);
    }

    public function totalsForLink(LinkId $linkId, DateRange $range): ClickTotals
    {
        return $this->totals(['link_id' => $linkId->value], $range);
    }

    public function totalsForAccount(AccountId $accountId, DateRange $range): ClickTotals
    {
        return $this->totals(['account_id' => $accountId->value], $range);
    }

    public function timeseriesForLink(LinkId $linkId, DateRange $range, Granularity $granularity): array
    {
        $rows = $this->collection()->aggregate([
            ['$match' => $this->matchStage(['link_id' => $linkId->value], $range, humanOnly: true)],
            ['$group' => [
                '_id' => [
                    '$dateTrunc' => [
                        'date' => '$occurred_at',
                        'unit' => $granularity->mongoUnit(),
                        'startOfWeek' => 'monday',
                    ],
                ],
                'clicks' => ['$sum' => 1],
                'visitors' => ['$addToSet' => '$visitor'],
            ]],
            ['$project' => [
                '_id' => 1,
                'clicks' => 1,
                'unique' => ['$size' => '$visitors'],
            ]],
            ['$sort' => ['_id' => 1]],
        ], ['allowDiskUse' => true])->toArray();

        $counted = [];

        foreach ($rows as $row) {
            /** @var UTCDateTime $bucketStart */
            $bucketStart = $row['_id'];
            $counted[$bucketStart->toDateTime()->getTimestamp()] = new TimeBucket(
                DateTimeImmutable::createFromMutable($bucketStart->toDateTime()),
                (int) $row['clicks'],
                (int) $row['unique'],
            );
        }

        return $this->fillGaps($counted, $range, $granularity);
    }

    public function breakdownForLink(
        LinkId $linkId,
        DateRange $range,
        BreakdownDimension $dimension,
        int $limit = 10,
    ): array {
        $field = '$'.$dimension->documentField();

        $rows = $this->collection()->aggregate([
            ['$match' => $this->matchStage(['link_id' => $linkId->value], $range, humanOnly: true)],
            ['$group' => [
                // A document written before a dimension existed has no value for
                // it; coalescing keeps those clicks in the total instead of
                // grouping them under a null key.
                '_id' => ['$ifNull' => [$field, $dimension->fallbackLabel()]],
                'clicks' => ['$sum' => 1],
            ]],
            ['$sort' => ['clicks' => -1, '_id' => 1]],
            ['$limit' => max(1, $limit)],
        ], ['allowDiskUse' => true])->toArray();

        return array_values(array_map(
            static fn (array $row): BreakdownSlice => new BreakdownSlice(
                (string) $row['_id'],
                (int) $row['clicks'],
            ),
            array_map(static fn (object|array $row): array => (array) $row, $rows),
        ));
    }

    public function topLinksForAccount(AccountId $accountId, DateRange $range, int $limit = 5): array
    {
        $rows = $this->collection()->aggregate([
            ['$match' => $this->matchStage(['account_id' => $accountId->value], $range, humanOnly: true)],
            ['$group' => ['_id' => '$link_id', 'clicks' => ['$sum' => 1]]],
            ['$sort' => ['clicks' => -1, '_id' => 1]],
            ['$limit' => max(1, $limit)],
        ], ['allowDiskUse' => true])->toArray();

        $ranking = [];

        foreach ($rows as $row) {
            $row = (array) $row;
            $ranking[(string) $row['_id']] = (int) $row['clicks'];
        }

        return $ranking;
    }

    public function purgeOlderThan(DateTimeImmutable $threshold): int
    {
        $result = $this->collection()->deleteMany([
            'occurred_at' => ['$lt' => new UTCDateTime($threshold)],
        ]);

        return $result->getDeletedCount();
    }

    /**
     * @param  array<string, string>  $scope
     */
    private function totals(array $scope, DateRange $range): ClickTotals
    {
        $rows = $this->collection()->aggregate([
            ['$match' => $this->matchStage($scope, $range, humanOnly: false)],
            ['$facet' => [
                // One pass over the matched documents produces both figures; two
                // separate queries would scan the same range twice.
                'human' => [
                    ['$match' => ['device.type' => ['$ne' => DeviceType::Bot->value]]],
                    ['$group' => [
                        '_id' => null,
                        'clicks' => ['$sum' => 1],
                        'visitors' => ['$addToSet' => '$visitor'],
                    ]],
                    ['$project' => ['clicks' => 1, 'unique' => ['$size' => '$visitors']]],
                ],
                'bots' => [
                    ['$match' => ['device.type' => DeviceType::Bot->value]],
                    ['$count' => 'clicks'],
                ],
            ]],
        ], ['allowDiskUse' => true])->toArray();

        if ($rows === []) {
            return ClickTotals::zero();
        }

        $facets = (array) $rows[0];
        $human = $this->firstOf($facets['human'] ?? []);
        $bots = $this->firstOf($facets['bots'] ?? []);

        return new ClickTotals(
            clicks: self::asInt($human['clicks'] ?? null),
            uniqueVisitors: self::asInt($human['unique'] ?? null),
            botClicks: self::asInt($bots['clicks'] ?? null),
        );
    }

    /**
     * @param  array<string, string>  $scope
     * @return array<string, mixed>
     */
    private function matchStage(array $scope, DateRange $range, bool $humanOnly): array
    {
        $match = $scope;

        // Half-open interval: `$gte` on the start, `$lt` on the end, so adjacent
        // windows never double count the boundary document.
        $match['occurred_at'] = [
            '$gte' => new UTCDateTime($range->from),
            '$lt' => new UTCDateTime($range->until),
        ];

        if ($humanOnly) {
            $match['device.type'] = ['$ne' => DeviceType::Bot->value];
        }

        return $match;
    }

    /**
     * Zero-fills every interval the aggregation did not return, so a chart shows a
     * flat line through quiet periods instead of joining two distant points.
     *
     * @param  array<int, TimeBucket>  $counted  keyed by bucket-start timestamp
     * @return list<TimeBucket>
     */
    private function fillGaps(array $counted, DateRange $range, Granularity $granularity): array
    {
        $series = [];
        $cursor = $granularity->floor($range->from);

        while ($cursor < $range->until) {
            $series[] = $counted[$cursor->getTimestamp()] ?? TimeBucket::empty($cursor);
            $cursor = $granularity->advance($cursor);
        }

        return $series;
    }

    /**
     * Aggregation results arrive as BSON documents, so every scalar read out of one is
     * `mixed` until it is checked. A missing field means the branch matched nothing,
     * which is a zero rather than an error.
     */
    private static function asInt(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * First row of a `$facet` branch, as a keyed array.
     *
     * A branch that matched nothing is absent rather than empty, so this collapses
     * both cases to an empty array and lets the caller read defaults out of it.
     *
     * @return array<string, mixed>
     */
    private function firstOf(mixed $facet): array
    {
        $rows = array_values((array) $facet);

        if ($rows === []) {
            return [];
        }

        /** @var array<string, mixed> $first */
        $first = (array) $rows[0];

        return $first;
    }

    private function collection(): Collection
    {
        return $this->connection->getCollection(ClickEventDocument::COLLECTION);
    }
}

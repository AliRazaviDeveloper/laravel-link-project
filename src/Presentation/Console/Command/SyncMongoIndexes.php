<?php

declare(strict_types=1);

namespace Shortwave\Presentation\Console\Command;

use Illuminate\Console\Command;
use Illuminate\Database\DatabaseManager;
use MongoDB\Collection;
use MongoDB\Laravel\Connection as MongoConnection;
use Shortwave\Infrastructure\Persistence\Mongo\Model\ClickEventDocument;

/**
 * Creates the analytics indexes.
 *
 * MongoDB has no migration story of its own, and index creation is idempotent, so
 * this runs on every boot from the container entrypoint rather than being tracked
 * like a schema migration. The alternative — hoping someone remembers to create
 * them — produces a system that works perfectly in development and collapses at
 * the first million documents.
 *
 * Every index here answers a specific query in MongoClickEventRepository; an index
 * with no matching query is pure write overhead, so the list is deliberately short.
 */
final class SyncMongoIndexes extends Command
{
    /**
     * Field order matters: equality first, then the range field. A
     * `{occurred_at, link_id}` index cannot serve a per-link date scan efficiently,
     * while `{link_id, occurred_at}` serves both it and a link-only lookup.
     *
     * @var array<string, array{key: array<string, int>, options?: array<string, mixed>}>
     */
    private const array INDEXES = [
        'link_time' => [
            'key' => ['link_id' => 1, 'occurred_at' => -1],
        ],
        'account_time' => [
            'key' => ['account_id' => 1, 'occurred_at' => -1],
        ],
        // Covers the unique-visitor grouping without touching documents.
        'link_visitor' => [
            'key' => ['link_id' => 1, 'visitor' => 1, 'occurred_at' => -1],
        ],
        // Retention. A TTL index lets the server expire documents on its own, so the
        // pruning command is only a backstop for shortening the window.
        'ttl' => [
            'key' => ['occurred_at' => 1],
            'options' => ['expireAfterSeconds' => 400 * 86_400],
        ],
    ];

    protected $signature = 'shortwave:mongo-sync {--drop-stale : remove indexes this command does not define}';

    protected $description = 'Create the MongoDB indexes the analytics queries depend on';

    public function handle(DatabaseManager $database): int
    {
        $connection = $database->connection('mongodb');

        if (! $connection instanceof MongoConnection) {
            $this->components->error('The "mongodb" connection is not a MongoDB connection.');

            return self::FAILURE;
        }

        $collection = $connection->getCollection(ClickEventDocument::COLLECTION);
        $existing = [];

        foreach ($collection->listIndexes() as $index) {
            $existing[$index->getName()] = true;
        }

        foreach (self::INDEXES as $name => $definition) {
            if (isset($existing[$name])) {
                $this->components->twoColumnDetail($name, '<fg=gray>present</>');

                continue;
            }

            $collection->createIndex($definition['key'], ['name' => $name, ...$definition['options'] ?? []]);
            $this->components->twoColumnDetail($name, '<fg=green>created</>');
        }

        if ($this->option('drop-stale')) {
            $this->dropStale($collection, array_keys($existing));
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $existing
     */
    private function dropStale(Collection $collection, array $existing): void
    {
        foreach ($existing as $name) {
            // `_id_` is created by the server and cannot be dropped.
            if ($name === '_id_' || isset(self::INDEXES[$name])) {
                continue;
            }

            $collection->dropIndex($name);
            $this->components->twoColumnDetail($name, '<fg=yellow>dropped</>');
        }
    }
}

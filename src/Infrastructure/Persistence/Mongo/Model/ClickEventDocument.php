<?php

declare(strict_types=1);

namespace Shortwave\Infrastructure\Persistence\Mongo\Model;

use MongoDB\Laravel\Eloquent\Model;

/**
 * Document shape for the click log.
 *
 * Field names are short because they are repeated on every one of potentially
 * hundreds of millions of documents, and MongoDB stores keys per document rather
 * than once per collection. The nesting (`device.*`, `geo.*`) matches the
 * BreakdownDimension mapping so a dimension is one indexed path.
 *
 * @property string $_id
 * @property string $link_id
 * @property string $account_id
 * @property \MongoDB\BSON\UTCDateTime $occurred_at
 * @property string $visitor
 * @property array{type: string, browser: string, platform: string} $device
 * @property array{host: string} $referrer
 * @property array{country: string} $geo
 */
final class ClickEventDocument extends Model
{
    public const string COLLECTION = 'click_events';

    public $incrementing = false;

    /**
     * Documents are written once and never touched again, so a pair of timestamp
     * columns would be dead weight on every insert.
     */
    public $timestamps = false;

    protected $connection = 'mongodb';

    protected string $collection = self::COLLECTION;

    protected $primaryKey = '_id';

    protected $keyType = 'string';

    protected $guarded = [];
}

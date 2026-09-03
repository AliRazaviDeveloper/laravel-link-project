<?php

declare(strict_types=1);

namespace Shortwave\Domain\Analytics\Enum;

/**
 * The dimensions a caller may group clicks by.
 *
 * Each case names the stored document field explicitly: the analytics store is
 * schemaless, so an unmapped dimension would silently aggregate nulls instead of
 * raising an error.
 */
enum BreakdownDimension: string
{
    case Country = 'country';
    case Referrer = 'referrer';
    case Browser = 'browser';
    case Platform = 'platform';
    case DeviceType = 'device_type';

    public function documentField(): string
    {
        return match ($this) {
            self::Country => 'geo.country',
            self::Referrer => 'referrer.host',
            self::Browser => 'device.browser',
            self::Platform => 'device.platform',
            self::DeviceType => 'device.type',
        };
    }

    public function fallbackLabel(): string
    {
        return match ($this) {
            self::Country => 'unknown',
            self::Referrer => 'direct',
            self::Browser, self::Platform => 'unknown',
            self::DeviceType => DeviceType::Unknown->value,
        };
    }
}

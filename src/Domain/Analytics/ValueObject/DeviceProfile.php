<?php

declare(strict_types=1);

namespace Shortwave\Domain\Analytics\ValueObject;

use Shortwave\Domain\Analytics\Enum\DeviceType;

/**
 * What we could tell about the client from its User-Agent.
 *
 * Deliberately coarse. Storing a full UA string per click is a liability with no
 * reporting value, so the raw header is classified on the way in and dropped.
 */
final readonly class DeviceProfile
{
    private function __construct(
        public DeviceType $type,
        public string $browser,
        public string $platform,
    ) {}

    public static function of(DeviceType $type, ?string $browser, ?string $platform): self
    {
        return new self(
            $type,
            self::label($browser),
            self::label($platform),
        );
    }

    public static function unknown(): self
    {
        return new self(DeviceType::Unknown, 'unknown', 'unknown');
    }

    private static function label(?string $value): string
    {
        $trimmed = strtolower(trim($value ?? ''));

        return $trimmed === '' ? 'unknown' : $trimmed;
    }
}

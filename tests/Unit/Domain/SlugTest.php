<?php

declare(strict_types=1);

use Shortwave\Domain\Link\ValueObject\Slug;
use Shortwave\Domain\Shared\Exception\InvariantViolation;

it('lower-cases and trims', function (): void {
    // Slugs are transcribed from print and screenshots, so case is normalised rather
    // than treated as meaningful.
    expect(Slug::fromString('  PromoCode  ')->value)->toBe('promocode');
});

it('treats slugs differing only in case as equal', function (): void {
    expect(Slug::fromString('AbC12')->equals(Slug::fromString('abc12')))->toBeTrue();
});

it('accepts letters, digits, hyphens and underscores', function (string $value): void {
    expect(Slug::fromString($value)->value)->toBe(strtolower($value));
})->with(['abc', 'a1b', 'spring-2026', 'my_link', 'a-b_c-1']);

it('rejects malformed slugs', function (string $value): void {
    expect(fn (): Slug => Slug::fromString($value))->toThrow(InvariantViolation::class);
})->with([
    'too short' => 'ab',
    'empty' => '',
    'leading hyphen' => '-abc',
    'trailing hyphen' => 'abc-',
    'leading underscore' => '_abc',
    'contains a space' => 'a b c',
    'contains a slash' => 'a/bc',
    'contains a dot' => 'a.bc',
    'non-ascii' => 'café12',
    'too long' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
]);

it('refuses reserved paths', function (string $value): void {
    expect(fn (): Slug => Slug::fromString($value))->toThrow(InvariantViolation::class, 'is reserved');
})->with(['api', 'API', 'health', 'docs', 'admin', 'metrics']);

it('reports reserved words without constructing a slug', function (): void {
    // Used by the generator, which must not throw on a collision with a reserved
    // word — it just tries again.
    expect(Slug::isReserved('  ADMIN '))->toBeTrue()
        ->and(Slug::isReserved('not-reserved'))->toBeFalse();
});

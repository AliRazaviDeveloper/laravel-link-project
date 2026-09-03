<?php

declare(strict_types=1);

use Shortwave\Domain\Link\ValueObject\ExpiryPolicy;
use Shortwave\Domain\Shared\Exception\InvariantViolation;

it('never expires by default', function (): void {
    $policy = ExpiryPolicy::never();

    expect($policy->isUnlimited())->toBeTrue()
        ->and($policy->hasExpiredAt(at('2099-01-01 00:00:00')))->toBeFalse()
        ->and($policy->isExhaustedBy(1_000_000))->toBeFalse()
        ->and($policy->secondsUntilExpiry(testNow()))->toBeNull();
});

it('rejects an expiry date in the past', function (): void {
    expect(fn (): ExpiryPolicy => ExpiryPolicy::of(at('2026-02-28 12:00:00'), null, testNow()))
        ->toThrow(InvariantViolation::class, 'must be in the future');
});

it('rejects a click limit below one', function (): void {
    expect(fn (): ExpiryPolicy => ExpiryPolicy::of(null, 0, testNow()))
        ->toThrow(InvariantViolation::class, 'at least 1');
});

it('treats the expiry instant itself as expired', function (): void {
    $policy = ExpiryPolicy::of(at('2026-03-02 00:00:00'), null, testNow());

    expect($policy->hasExpiredAt(at('2026-03-01 23:59:59')))->toBeFalse()
        ->and($policy->hasExpiredAt(at('2026-03-02 00:00:00')))->toBeTrue();
});

it('is exhausted once the count reaches the limit', function (): void {
    $policy = ExpiryPolicy::of(null, 3, testNow());

    expect($policy->isExhaustedBy(2))->toBeFalse()
        ->and($policy->isExhaustedBy(3))->toBeTrue()
        // A cap can be crossed by a racing flush, so past the limit must also read
        // as exhausted rather than wrapping around.
        ->and($policy->isExhaustedBy(9))->toBeTrue();
});

it('reports the seconds left so the cache entry can be capped', function (): void {
    $policy = ExpiryPolicy::of(at('2026-03-01 12:01:00'), null, testNow());

    expect($policy->secondsUntilExpiry(testNow()))->toBe(60)
        // Never negative: a negative TTL would be read as "no expiry" by most cache
        // clients, which is the opposite of what is meant.
        ->and($policy->secondsUntilExpiry(at('2026-03-02 00:00:00')))->toBe(0);
});

it('reconstitutes a policy that has already lapsed', function (): void {
    $policy = ExpiryPolicy::reconstitute(at('2020-01-01 00:00:00'), 5);

    expect($policy->hasExpiredAt(testNow()))->toBeTrue()
        ->and($policy->maxClicks)->toBe(5);
});

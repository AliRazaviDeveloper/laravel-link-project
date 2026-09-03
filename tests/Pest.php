<?php

declare(strict_types=1);

use Tests\Support\FeatureTestCase;

/*
|--------------------------------------------------------------------------
| Test case binding
|--------------------------------------------------------------------------
|
| Only the feature suite boots the framework. Unit tests deliberately get no base
| class and no container: a domain test that needs a booted application is a sign
| the code under test has reached for infrastructure it should not know about, and
| keeping the suites apart makes that failure loud.
|
*/

pest()->extend(FeatureTestCase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

/**
 * Asserts an RFC 9457 problem response, since almost every failure path in the API
 * is checked this way.
 */
expect()->extend('toBeProblem', function (int $status, ?string $type = null) {
    /** @var Illuminate\Testing\TestResponse $this */
    $this->value->assertStatus($status);
    $this->value->assertHeader('Content-Type', 'application/problem+json');
    $this->value->assertJsonStructure(['type', 'title', 'status', 'detail']);

    if ($type !== null) {
        expect($this->value->json('type'))->toEndWith($type);
    }

    return $this;
});

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * A UTC timestamp from a readable string. Tests state times explicitly rather than
 * using "now", so an assertion about an expiry boundary cannot pass or fail
 * depending on when the suite runs.
 */
function at(string $moment): DateTimeImmutable
{
    return new DateTimeImmutable($moment, new DateTimeZone('UTC'));
}

/**
 * The moment every test treats as the present.
 */
function testNow(): DateTimeImmutable
{
    return at('2026-03-01 12:00:00');
}

/**
 * A Link aggregate with sensible defaults, so each test names only the one property
 * it is about.
 */
function makeLink(
    ?Shortwave\Domain\Link\ValueObject\ExpiryPolicy $expiry = null,
    string $slug = 'promo24',
    string $destination = 'https://example.com/landing',
    ?string $title = null,
    ?DateTimeImmutable $now = null,
): Shortwave\Domain\Link\Entity\Link {
    return Shortwave\Domain\Link\Entity\Link::create(
        id: Shortwave\Domain\Link\ValueObject\LinkId::fromString('01HQ8V3M9XKPWT7CZR4NFGD2AB'),
        accountId: Shortwave\Domain\Account\ValueObject\AccountId::fromString('01HQ8V3M9XKPWT7CZR4NFGD2CD'),
        slug: Shortwave\Domain\Link\ValueObject\Slug::fromString($slug),
        destination: Shortwave\Domain\Link\ValueObject\Destination::fromString($destination),
        title: $title,
        expiry: $expiry ?? Shortwave\Domain\Link\ValueObject\ExpiryPolicy::never(),
        now: $now ?? testNow(),
    );
}

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Redis;
use Shortwave\Application\Link\Port\PendingClicks;
use Shortwave\Domain\Link\ValueObject\LinkId;

/*
|--------------------------------------------------------------------------
| Redirect hot path
|--------------------------------------------------------------------------
|
| The most important endpoint in the system, and the one with the most moving parts:
| Postgres for the first lookup, Redis for the cache and the counter, and a buffered
| write on its way to MongoDB. All three are real here — a mocked cache would let a
| broken invalidation pass, which is exactly the bug this suite exists to catch.
|
*/

it('redirects to the destination', function (): void {
    $account = $this->registerAccount();
    $slug = $this->createLink($account['token'], ['slug' => 'go-here'])->json('data.slug');

    $this->followSlug($slug)
        ->assertStatus(302)
        ->assertRedirect('https://example.com/landing');
});

it('uses 302 and forbids caching so every click is counted', function (): void {
    // A 301 would be cached by browsers and proxies indefinitely: the link could never
    // be retargeted, and every later visit would be invisible to analytics.
    $account = $this->registerAccount();
    $slug = $this->createLink($account['token'])->json('data.slug');

    $response = $this->followSlug($slug)->assertStatus(302);

    expect($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->headers->get('Referrer-Policy'))->toBe('no-referrer')
        ->and($response->headers->get('X-Robots-Tag'))->toContain('noindex');
});

it('answers 404 for a slug that never existed', function (): void {
    expect($this->followSlug('nothinghere'))->toBeProblem(404, 'link_not_found');
});

it('caches a resolution so the second hit needs no relational query', function (): void {
    $account = $this->registerAccount();
    $slug = $this->createLink($account['token'], ['slug' => 'cached1'])->json('data.slug');

    $this->followSlug($slug)->assertStatus(302);

    // The entry is namespace-versioned, so the assertion is on the suffix rather than a
    // literal key.
    $keys = Redis::connection('cache')->keys('*resolution*slug*cached1*');

    expect($keys)->not->toBeEmpty();
});

it('caches an absent slug so scanners do not reach the database repeatedly', function (): void {
    $this->followSlug('scanner-probe');

    $keys = Redis::connection('cache')->keys('*resolution*slug*scanner-probe*');

    expect($keys)->not->toBeEmpty();
});

it('serves the new destination immediately after a retarget', function (): void {
    $account = $this->registerAccount();
    $created = $this->createLink($account['token'], ['slug' => 'movable'])->json('data');

    $this->followSlug('movable')->assertRedirect('https://example.com/landing');

    $this->asAccount($account['token'])
        ->patchJson('/api/v1/links/'.$created['id'], ['destination_url' => 'https://example.com/moved'])
        ->assertOk();

    // Fails if the cache is not invalidated on write — the single most consequential
    // bug this design can have.
    $this->followSlug('movable')->assertRedirect('https://example.com/moved');
});

it('stops resolving an archived link and resumes after a restore', function (): void {
    $account = $this->registerAccount();
    $created = $this->createLink($account['token'], ['slug' => 'toggle1'])->json('data');

    $this->asAccount($account['token'])->deleteJson('/api/v1/links/'.$created['id'])->assertNoContent();

    // 404 rather than 410: archiving is reversible, so the link is not "gone".
    expect($this->followSlug('toggle1'))->toBeProblem(404);

    $this->asAccount($account['token'])->postJson('/api/v1/links/'.$created['id'].'/restore')->assertOk();

    $this->followSlug('toggle1')->assertStatus(302);
});

it('answers 410 once a click cap is reached', function (): void {
    $account = $this->registerAccount();
    $this->createLink($account['token'], ['slug' => 'limited', 'max_clicks' => 2]);

    $this->followSlug('limited')->assertStatus(302);
    $this->followSlug('limited')->assertStatus(302);

    // 410 tells crawlers to stop asking; the reason is machine-readable so a client can
    // distinguish a cap from an expiry.
    expect($this->followSlug('limited'))->toBeProblem(410, 'link_not_resolvable');
});

// The API adds buffered counts on top of the stored total, so a client never sees the lag.
it('counts clicks in Redis rather than on the row', function (): void {
    $account = $this->registerAccount();
    $created = $this->createLink($account['token'], ['slug' => 'counted'])->json('data');

    $this->followSlug('counted');
    $this->followSlug('counted');
    $this->followSlug('counted');

    $pending = app(PendingClicks::class)->pendingFor(LinkId::fromString($created['id']));

    expect($pending)->toBe(3)
        // The relational counter is still untouched: it moves only when the flush runs.
        ->and((int) $this->asAccount($account['token'])
            ->getJson('/api/v1/links/'.$created['id'])
            ->json('data.click_count'))
        ->toBe(3);
});

it('folds buffered counts into the row when the flush runs', function (): void {
    $account = $this->registerAccount();
    $created = $this->createLink($account['token'], ['slug' => 'flushed'])->json('data');

    $this->followSlug('flushed');
    $this->followSlug('flushed');

    $this->artisan('shortwave:flush-clicks')->assertSuccessful();

    $this->assertDatabaseHas('links', ['id' => $created['id'], 'click_count' => 2]);

    // And the buffer is empty afterwards, so the next read does not double count.
    expect(app(PendingClicks::class)->pendingFor(LinkId::fromString($created['id'])))->toBe(0);
});

it('rejects a malformed slug at the router', function (): void {
    // The route pattern mirrors the Slug value object, so junk never reaches the handler
    // and cannot cost a cache lookup.
    $this->get('/a')->assertNotFound();
    $this->get('/has spaces')->assertNotFound();
});

it('does not shadow the API or health routes', function (): void {
    $this->getJson('/api/v1/health')->assertOk();
    $this->get('/up')->assertOk();
});

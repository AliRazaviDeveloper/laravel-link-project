<?php

declare(strict_types=1);

use Shortwave\Domain\Account\ValueObject\Plan;
use Shortwave\Domain\Link\ValueObject\Slug;

/*
|--------------------------------------------------------------------------
| Link management API
|--------------------------------------------------------------------------
*/

describe('creating', function (): void {
    it('generates a slug when none is supplied', function (): void {
        $account = $this->registerAccount();

        $data = $this->createLink($account['token'])->assertCreated()->json('data');

        expect($data['slug'])->toHaveLength(7)
            // Must survive the domain's own rules, since the generator and the
            // validator have to agree.
            ->and(Slug::fromString($data['slug'])->value)->toBe($data['slug'])
            ->and($data['short_url'])->toBe('https://swv.test/'.$data['slug']);
    });

    it('accepts a vanity slug and returns a Location header', function (): void {
        $account = $this->registerAccount();

        $this->createLink($account['token'], ['slug' => 'Launch-2026'])
            ->assertCreated()
            // Slugs are normalised, because people transcribe them by hand.
            ->assertJsonPath('data.slug', 'launch-2026')
            ->assertHeader('Location', 'https://swv.test/launch-2026');
    });

    it('refuses a slug that is already taken', function (): void {
        $account = $this->registerAccount();
        $this->createLink($account['token'], ['slug' => 'taken01'])->assertCreated();

        expect($this->createLink($account['token'], ['slug' => 'taken01']))
            ->toBeProblem(409, 'slug_already_taken');
    });

    it('refuses a slug taken by another account', function (): void {
        $first = $this->registerAccount('first@example.com');
        $second = $this->registerAccount('second@example.com');

        $this->createLink($first['token'], ['slug' => 'shared1'])->assertCreated();

        // The slug namespace is global, because the redirect path has only the slug to
        // work from — there is no tenant in the short URL.
        expect($this->createLink($second['token'], ['slug' => 'shared1']))->toBeProblem(409);
    });

    it('refuses a reserved slug', function (): void {
        $account = $this->registerAccount();

        expect($this->createLink($account['token'], ['slug' => 'health']))->toBeProblem(422);
    });

    it('refuses destinations that are not publicly routable', function (string $url): void {
        $account = $this->registerAccount();

        expect($this->createLink($account['token'], ['destination_url' => $url]))->toBeProblem(422);
    })->with([
        'loopback' => 'http://127.0.0.1/admin',
        'cloud metadata' => 'http://169.254.169.254/latest/meta-data/',
        'private range' => 'http://10.1.2.3/',
        'javascript' => 'javascript:alert(1)',
    ]);

    it('refuses an expiry date in the past', function (): void {
        $account = $this->registerAccount();

        expect($this->createLink($account['token'], ['expires_at' => '2020-01-01T00:00:00Z']))
            ->toBeProblem(422);
    });

    it('enforces the plan link allowance', function (): void {
        $account = $this->registerAccount();

        // The free allowance is 50; seeding via the API would be slow, so the ceiling is
        // approached directly and the last create is the one under test.
        Shortwave\Infrastructure\Persistence\Eloquent\Model\LinkModel::factory()
            ->count(Plan::Free->linkAllowance())
            ->create(['account_id' => $account['account_id']]);

        expect($this->createLink($account['token']))->toBeProblem(403, 'link_allowance_exceeded');
    });
});

describe('reading', function (): void {
    it('lists only the authenticated account\'s links', function (): void {
        $mine = $this->registerAccount('mine@example.com');
        $theirs = $this->registerAccount('theirs@example.com');

        $this->createLink($mine['token'], ['slug' => 'mine001']);
        $this->createLink($theirs['token'], ['slug' => 'their01']);

        $response = $this->asAccount($mine['token'])->getJson('/api/v1/links')->assertOk();

        expect($response->json('data'))->toHaveCount(1)
            ->and($response->json('data.0.slug'))->toBe('mine001')
            ->and($response->json('meta.total'))->toBe(1);
    });

    it('paginates with stable ordering', function (): void {
        $account = $this->registerAccount();

        foreach (range(1, 5) as $index) {
            $this->createLink($account['token'], ['slug' => 'page'.$index.'xx']);
        }

        $first = $this->asAccount($account['token'])->getJson('/api/v1/links?per_page=2&page=1')->assertOk();
        $second = $this->asAccount($account['token'])->getJson('/api/v1/links?per_page=2&page=2')->assertOk();

        expect($first->json('data'))->toHaveCount(2)
            ->and($first->json('meta.last_page'))->toBe(3)
            ->and($first->json('meta.has_more'))->toBeTrue()
            // No row may appear on two pages: that is what the id tiebreak is for.
            ->and(array_intersect(
                array_column($first->json('data'), 'id'),
                array_column($second->json('data'), 'id'),
            ))->toBe([]);
    });

    it('filters by status', function (): void {
        $account = $this->registerAccount();
        $kept = $this->createLink($account['token'], ['slug' => 'kept001'])->json('data');
        $gone = $this->createLink($account['token'], ['slug' => 'gone001'])->json('data');

        $this->asAccount($account['token'])->deleteJson('/api/v1/links/'.$gone['id']);

        $active = $this->asAccount($account['token'])->getJson('/api/v1/links?status=active')->assertOk();
        $archived = $this->asAccount($account['token'])->getJson('/api/v1/links?status=archived')->assertOk();

        expect(array_column($active->json('data'), 'id'))->toBe([$kept['id']])
            ->and(array_column($archived->json('data'), 'id'))->toBe([$gone['id']]);
    });

    it('rejects an unlisted sort column', function (): void {
        $account = $this->registerAccount();

        // An arbitrary column name in an ORDER BY is both an injection surface and an
        // easy way to ask for an unindexed sort.
        expect($this->asAccount($account['token'])->getJson('/api/v1/links?sort_by=password'))
            ->toBeProblem(422, 'validation_failed');
    });

    it('hides another account\'s link behind a 404', function (): void {
        $mine = $this->registerAccount('a@example.com');
        $theirs = $this->registerAccount('b@example.com');
        $link = $this->createLink($theirs['token'])->json('data');

        // 404, not 403: confirming that an id exists is itself a leak.
        expect($this->asAccount($mine['token'])->getJson('/api/v1/links/'.$link['id']))
            ->toBeProblem(404, 'link_not_found');
    });
});

describe('updating', function (): void {
    it('retargets without touching other fields', function (): void {
        $account = $this->registerAccount();
        $link = $this->createLink($account['token'], ['title' => 'Original'])->json('data');

        $this->asAccount($account['token'])
            ->patchJson('/api/v1/links/'.$link['id'], ['destination_url' => 'https://example.com/next'])
            ->assertOk()
            ->assertJsonPath('data.destination_url', 'https://example.com/next')
            ->assertJsonPath('data.title', 'Original')
            ->assertJsonPath('data.slug', $link['slug']);
    });

    it('clears an expiry when the field is sent as null', function (): void {
        $account = $this->registerAccount();
        $link = $this->createLink($account['token'], [
            'expires_at' => now()->addDays(5)->toIso8601String(),
        ])->json('data');

        expect($link['expires_at'])->not->toBeNull();

        // Sending null must clear it, while omitting it must not — the distinction the
        // update command's presence flags exist for.
        $this->asAccount($account['token'])
            ->patchJson('/api/v1/links/'.$link['id'], ['expires_at' => null])
            ->assertOk()
            ->assertJsonPath('data.expires_at', null);
    });

    it('leaves an omitted field untouched', function (): void {
        $account = $this->registerAccount();
        $link = $this->createLink($account['token'], [
            'title' => 'Keep me',
            'max_clicks' => 10,
        ])->json('data');

        $this->asAccount($account['token'])
            ->patchJson('/api/v1/links/'.$link['id'], ['destination_url' => 'https://example.com/z'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Keep me')
            ->assertJsonPath('data.max_clicks', 10);
    });

    it('refuses to update another account\'s link', function (): void {
        $mine = $this->registerAccount('c@example.com');
        $theirs = $this->registerAccount('d@example.com');
        $link = $this->createLink($theirs['token'])->json('data');

        expect($this->asAccount($mine['token'])
            ->patchJson('/api/v1/links/'.$link['id'], ['destination_url' => 'https://evil.example.com']))
            ->toBeProblem(404);

        $this->assertDatabaseHas('links', [
            'id' => $link['id'],
            'destination_url' => 'https://example.com/landing',
        ]);
    });
});

describe('archiving', function (): void {
    it('archives rather than deletes, preserving the row', function (): void {
        $account = $this->registerAccount();
        $link = $this->createLink($account['token'])->json('data');

        $this->asAccount($account['token'])->deleteJson('/api/v1/links/'.$link['id'])->assertNoContent();

        // Click history outlives the link, so a hard delete would orphan documents in
        // the analytics store that nothing could label afterwards.
        $this->assertDatabaseHas('links', ['id' => $link['id'], 'status' => 'archived']);
    });

    it('is idempotent', function (): void {
        $account = $this->registerAccount();
        $link = $this->createLink($account['token'])->json('data');

        $this->asAccount($account['token'])->deleteJson('/api/v1/links/'.$link['id'])->assertNoContent();
        $this->asAccount($account['token'])->deleteJson('/api/v1/links/'.$link['id'])->assertNoContent();

        $this->assertDatabaseHas('links', ['id' => $link['id'], 'status' => 'archived']);
    });
});

describe('bulk import', function (): void {
    it('is unavailable on the free plan', function (): void {
        $account = $this->registerAccount();

        expect($this->asAccount($account['token'])->postJson('/api/v1/links/import', [
            'links' => [['destination_url' => 'https://example.com/a']],
        ]))->toBeProblem(422);
    });

    it('creates every valid row on a paid plan', function (): void {
        $account = $this->registerAccount('pro@example.com', Plan::Pro);

        $response = $this->asAccount($account['token'])->postJson('/api/v1/links/import', [
            'links' => [
                ['destination_url' => 'https://example.com/a', 'slug' => 'bulk-aa'],
                ['destination_url' => 'https://example.com/b', 'slug' => 'bulk-bb'],
            ],
        ])->assertCreated();

        expect($response->json('summary'))->toBe(['created' => 2, 'rejected' => 0]);
    });

    it('reports a mixed batch as 207 and keeps the valid rows', function (): void {
        $account = $this->registerAccount('pro2@example.com', Plan::Pro);

        $response = $this->asAccount($account['token'])->postJson('/api/v1/links/import', [
            'links' => [
                ['destination_url' => 'https://example.com/ok', 'slug' => 'mixed-ok'],
                ['destination_url' => 'http://127.0.0.1/private'],
                ['destination_url' => 'https://example.com/ok2', 'slug' => 'mixed-k2'],
            ],
        ])->assertStatus(207);

        // Partial success is the contract: one bad row in a CSV of hundreds must not
        // discard the rest, and a flat 201 would let a client read it as complete.
        expect($response->json('summary'))->toBe(['created' => 2, 'rejected' => 1])
            ->and($response->json('results.1.status'))->toBe('rejected')
            ->and($response->json('results.1.error.code'))->toBe('invariant_violation');

        $this->assertDatabaseHas('links', ['slug' => 'mixed-ok']);
        $this->assertDatabaseHas('links', ['slug' => 'mixed-k2']);
    });
});

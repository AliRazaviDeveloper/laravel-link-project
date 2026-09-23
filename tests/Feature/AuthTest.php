<?php

declare(strict_types=1);

use Shortwave\Domain\Account\ValueObject\Plan;

/*
|--------------------------------------------------------------------------
| Authentication and token abilities
|--------------------------------------------------------------------------
*/

describe('registration', function (): void {
    it('creates an account and returns a usable token', function (): void {
        $response = $this->postJson('/api/v1/auth/register', [
            'email' => 'New.User@Example.com',
            'name' => 'New User',
            'password' => 'correct-horse-battery-99',
        ])->assertCreated();

        // Addresses are normalised, so the same person cannot register twice by
        // changing capitalisation.
        expect($response->json('account.email'))->toBe('new.user@example.com')
            ->and($response->json('account.plan.name'))->toBe(Plan::Free->value)
            // The id prefix is whatever the sequence produced; only the shape and the
            // configured prefix are part of the contract.
            ->and($response->json('token.value'))->toMatch('/^\d+\|sw_/')
            // Non-expiring by default: a key that stops working overnight is a worse
            // failure mode for a machine client than a long-lived one.
            ->and($response->json('token.expires_at'))->toBeNull();

        $this->asAccount((string) $response->json('token.value'))
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('account.id', $response->json('account.id'));
    });

    it('never returns the password hash', function (): void {
        $response = $this->postJson('/api/v1/auth/register', [
            'email' => 'hash@example.com',
            'name' => 'Hash',
            'password' => 'correct-horse-battery-99',
        ])->assertCreated();

        expect($response->json())->not->toHaveKey('password')
            ->and(json_encode($response->json(), JSON_THROW_ON_ERROR))
            ->not->toContain('correct-horse-battery-99');
    });

    it('rejects a duplicate address with a conflict', function (): void {
        $this->registerAccount('taken@example.com');

        expect($this->postJson('/api/v1/auth/register', [
            'email' => 'taken@example.com',
            'name' => 'Someone Else',
            'password' => 'correct-horse-battery-99',
        ]))->toBeProblem(409, 'email_already_registered');
    });

    it('rejects invalid payloads', function (array $payload): void {
        expect($this->postJson('/api/v1/auth/register', $payload))
            ->toBeProblem(422, 'validation_failed');
    })->with([
        'missing email' => [['name' => 'A', 'password' => 'correct-horse-battery-99']],
        'malformed email' => [['email' => 'nope', 'name' => 'A', 'password' => 'correct-horse-battery-99']],
        'short password' => [['email' => 'a@example.com', 'name' => 'A', 'password' => 'short']],
        'blank name' => [['email' => 'a@example.com', 'name' => '', 'password' => 'correct-horse-battery-99']],
    ]);
});

describe('login', function (): void {
    it('issues a token for correct credentials', function (): void {
        $this->registerAccount('login@example.com');

        $this->postJson('/api/v1/auth/login', [
            'email' => 'login@example.com',
            'password' => 'correct-horse-battery-99',
        ])->assertOk()->assertJsonStructure(['account' => ['id'], 'token' => ['value']]);
    });

    it('gives the same answer for a wrong password and an unknown address', function (): void {
        $this->registerAccount('known@example.com');

        $wrongPassword = $this->postJson('/api/v1/auth/login', [
            'email' => 'known@example.com',
            'password' => 'not-the-password-at-all',
        ]);

        $unknownEmail = $this->postJson('/api/v1/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'not-the-password-at-all',
        ]);

        // Identical responses, so the endpoint cannot be used to discover which
        // addresses hold accounts.
        expect($wrongPassword)->toBeProblem(401, 'invalid_credentials')
            ->and($unknownEmail)->toBeProblem(401, 'invalid_credentials')
            ->and($unknownEmail->json('detail'))->toBe($wrongPassword->json('detail'));
    });

    it('does not apply password policy to existing credentials', function (): void {
        $this->registerAccount('policy@example.com');

        // A complexity rule on login would reject accounts created before a policy
        // change, and would leak the current policy to an attacker.
        expect($this->postJson('/api/v1/auth/login', [
            'email' => 'policy@example.com',
            'password' => 'x',
        ]))->toBeProblem(401);
    });
});

describe('tokens', function (): void {
    it('rejects requests with no token', function (): void {
        expect($this->getJson('/api/v1/links'))->toBeProblem(401, 'unauthenticated');
    });

    it('rejects a fabricated token', function (): void {
        expect($this->asAccount('1|sw_thisisnotarealtokenvalueatall')->getJson('/api/v1/links'))
            ->toBeProblem(401);
    });

    it('revokes only the token that logged out', function (): void {
        $account = $this->registerAccount('logout@example.com');

        $second = $this->postJson('/api/v1/auth/login', [
            'email' => 'logout@example.com',
            'password' => 'correct-horse-battery-99',
        ])->assertOk()->json('token.value');

        $this->asAccount($account['token'])->postJson('/api/v1/auth/logout')->assertNoContent();

        expect($this->asAccount($account['token'])->getJson('/api/v1/links'))->toBeProblem(401);

        // Signing out of one client must not sign the account out everywhere.
        $this->asAccount((string) $second)->getJson('/api/v1/links')->assertOk();
    });

    it('enforces abilities per endpoint', function (): void {
        $account = $this->registerAccount('abilities@example.com');

        // A read-only token: useful for a CI job that should not be able to read the
        // account's analytics or create links.
        $readOnly = Shortwave\Infrastructure\Persistence\Eloquent\Model\UserModel::query()
            ->findOrFail($account['account_id'])
            ->createToken('read-only', ['links:read'])
            ->plainTextToken;

        $this->asAccount($readOnly)->getJson('/api/v1/links')->assertOk();

        expect($this->asAccount($readOnly)->postJson('/api/v1/links', [
            'destination_url' => 'https://example.com/nope',
        ]))->toBeProblem(403);

        expect($this->asAccount($readOnly)->getJson('/api/v1/overview'))->toBeProblem(403);
    });
});

describe('idempotency', function (): void {
    it('replays the original response for a repeated key', function (): void {
        $account = $this->registerAccount();

        $first = $this->asAccount($account['token'])
            ->withHeader('Idempotency-Key', 'retry-001')
            ->postJson('/api/v1/links', ['destination_url' => 'https://example.com/once'])
            ->assertCreated();

        $second = $this->asAccount($account['token'])
            ->withHeader('Idempotency-Key', 'retry-001')
            ->postJson('/api/v1/links', ['destination_url' => 'https://example.com/once'])
            ->assertCreated()
            ->assertHeader('Idempotent-Replay', 'true');

        // One link, not two: a client that timed out and retried must not end up with a
        // duplicate.
        expect($second->json('data.id'))->toBe($first->json('data.id'))
            ->and(Shortwave\Infrastructure\Persistence\Eloquent\Model\LinkModel::query()->count())->toBe(1);
    });

    it('treats the same key with a different body as a new request', function (): void {
        $account = $this->registerAccount();

        $this->asAccount($account['token'])
            ->withHeader('Idempotency-Key', 'retry-002')
            ->postJson('/api/v1/links', ['destination_url' => 'https://example.com/a'])
            ->assertCreated();

        // The body is fingerprinted alongside the key, so reusing a key for different
        // content cannot return the wrong link.
        $this->asAccount($account['token'])
            ->withHeader('Idempotency-Key', 'retry-002')
            ->postJson('/api/v1/links', ['destination_url' => 'https://example.com/b'])
            ->assertCreated()
            ->assertHeaderMissing('Idempotent-Replay');
    });

    it('scopes keys to the account', function (): void {
        $first = $this->registerAccount('idem-a@example.com');
        $second = $this->registerAccount('idem-b@example.com');

        $this->asAccount($first['token'])
            ->withHeader('Idempotency-Key', 'shared-key')
            ->postJson('/api/v1/links', ['destination_url' => 'https://example.com/x'])
            ->assertCreated();

        // One tenant's key must not collide with another's.
        $this->asAccount($second['token'])
            ->withHeader('Idempotency-Key', 'shared-key')
            ->postJson('/api/v1/links', ['destination_url' => 'https://example.com/x'])
            ->assertCreated()
            ->assertHeaderMissing('Idempotent-Replay');
    });
});

describe('error format', function (): void {
    it('answers every failure as a problem document with a request id', function (): void {
        $response = $this->getJson('/api/v1/links/not-a-ulid')
            ->assertHeader('Content-Type', 'application/problem+json');

        expect($response->json())->toHaveKeys(['type', 'title', 'status', 'detail'])
            ->and($response->headers->get('X-Request-Id'))->not->toBeEmpty();
    });

    it('echoes a client-supplied request id back', function (): void {
        // Lets a trace span services; sanitised on the way in because the value lands in
        // log files and response headers.
        $this->getJson('/api/v1/health', ['X-Request-Id' => 'trace-abc-123'])
            ->assertHeader('X-Request-Id', 'trace-abc-123');
    });

    it('strips unusual characters from a supplied request id', function (): void {
        $response = $this->getJson('/api/v1/health', ['X-Request-Id' => "bad\r\nvalue: injected"]);

        expect($response->headers->get('X-Request-Id'))->toBe('badvalueinjected');
    });
});

describe('health', function (): void {
    it('reports every backing store', function (): void {
        $response = $this->getJson('/api/v1/health')->assertOk();

        expect($response->json('status'))->toBe('ok')
            ->and($response->json('checks'))->toHaveKeys(['postgres', 'mongodb', 'redis'])
            ->and($response->json('checks.postgres.ok'))->toBeTrue()
            ->and($response->json('checks.mongodb.ok'))->toBeTrue()
            ->and($response->json('checks.redis.ok'))->toBeTrue();
    });

    it('needs no authentication', function (): void {
        $this->getJson('/api/v1/health')->assertOk();
    });
});

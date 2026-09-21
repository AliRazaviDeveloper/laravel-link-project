<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Response;
use Illuminate\Testing\TestResponse;
use Shortwave\Application\Shared\Support\Input;
use Shortwave\Domain\Account\ValueObject\Plan;
use Shortwave\Infrastructure\Persistence\Eloquent\Model\UserModel;

/**
 * Account and request helpers for feature tests.
 *
 * Accounts are created through the real registration endpoint rather than by inserting
 * rows, so the token a test authenticates with is one the API actually issued, with the
 * abilities it actually grants. A factory that wrote rows directly would let a broken
 * registration flow go unnoticed by every other test.
 */
trait ApiHelpers
{
    /**
     * @return array{token: string, account_id: string}
     */
    protected function registerAccount(string $email = 'owner@example.com', Plan $plan = Plan::Free): array
    {
        $this->app->make(AuthFactory::class)->forgetGuards();

        $response = $this->postJson('/api/v1/auth/register', [
            'email' => $email,
            'name' => 'Test Owner',
            'password' => 'correct-horse-battery-99',
        ])->assertCreated();

        /** @var array<string, mixed> $account */
        $account = Input::shape($response->json('account'));
        $accountId = Input::string($account, 'id');

        if ($plan !== Plan::Free) {
            // Plans are not settable through the API — there is no billing surface — so
            // the column is updated directly for tests that need a paid tier.
            UserModel::query()->whereKey($accountId)->update(['plan' => $plan->value]);
        }

        /** @var array<string, mixed> $issued */
        $issued = Input::shape($response->json('token'));

        return [
            'token' => Input::string($issued, 'value'),
            'account_id' => $accountId,
        ];
    }

    /**
     * Authenticates the next request as the holder of this token.
     *
     * The guard is forgotten first, and that is not optional. Laravel resolves the
     * token guard once and caches the user on it, and the container survives every
     * request inside a single test — so a second request with a different token would
     * still be served as the first token's account. In production each request gets a
     * fresh container, so this is a harness artefact rather than an authorization bug;
     * without it, a test for cross-account isolation would pass no matter what the
     * application did.
     *
     * @return $this
     */
    protected function asAccount(string $token): static
    {
        $this->app->make(AuthFactory::class)->forgetGuards();

        return $this->withToken($token);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return TestResponse<Response>
     */
    protected function createLink(string $token, array $payload = []): TestResponse
    {
        return $this->asAccount($token)->postJson('/api/v1/links', [
            'destination_url' => 'https://example.com/landing',
            ...$payload,
        ]);
    }

    /**
     * @param  array<string, string>  $headers
     * @return TestResponse<Response>
     */
    protected function followSlug(string $slug, array $headers = []): TestResponse
    {
        return $this->get('/'.$slug, $headers);
    }

    /**
     * Pushes buffered clicks through the queue into MongoDB and folds the counters into
     * Postgres, so a test can assert on analytics without waiting for the scheduler.
     */
    protected function drainClickPipeline(): void
    {
        $this->artisan('shortwave:flush-buffer');
        $this->artisan('shortwave:flush-clicks');
    }
}

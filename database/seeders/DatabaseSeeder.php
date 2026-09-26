<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Shortwave\Domain\Account\ValueObject\Plan;
use Shortwave\Domain\Link\Enum\LinkStatus;
use Shortwave\Infrastructure\Persistence\Eloquent\Model\LinkModel;
use Shortwave\Infrastructure\Persistence\Eloquent\Model\UserModel;

/**
 * A demo account with links covering each interesting state.
 *
 * Deliberately not random: the slugs are the ones the README's examples use, so
 * following the documentation has to produce the documented result.
 *
 * Re-running is safe, and specifically never rewrites a primary key. An account id is
 * referenced by its links, and a link id is referenced by click documents in the
 * analytics store that no foreign key protects — so replacing one would silently
 * orphan history that nothing could label afterwards.
 *
 * Clicks are not seeded at all. They belong to the redirect path, and inserting
 * documents directly would skip the fingerprinting and device classification that give
 * them meaning; `curl localhost:8080/demo-docs` a few times instead.
 */
final class DatabaseSeeder extends Seeder
{
    private const string DEMO_EMAIL = 'demo@shortwave.test';

    private const string DEMO_PASSWORD = 'correct-horse-battery-99';

    public function run(): void
    {
        $account = $this->demoAccount();

        $this->seedLinks($account->id);

        // Previous seed tokens are revoked so repeated runs do not pile up credentials
        // that are all printed to a terminal and then forgotten.
        $account->tokens()->where('name', 'seeded')->delete();
        $token = $account->createToken('seeded', ['links:read', 'links:write', 'analytics:read']);

        $this->report($token->plainTextToken);
    }

    private function demoAccount(): UserModel
    {
        $account = UserModel::query()->firstOrCreate(
            ['email' => self::DEMO_EMAIL],
            [
                // The id is supplied only on insert. HasUlids would generate one, but
                // being explicit keeps the format identical to the application's own
                // identity generator.
                'id' => strtoupper((string) Str::ulid()),
                'name' => 'Demo Account',
                'plan' => Plan::Pro->value,
                'password' => Hash::make(self::DEMO_PASSWORD),
            ],
        );

        // Mutable fields are refreshed on a re-run; the key is not part of this update.
        $account->forceFill([
            'name' => 'Demo Account',
            'plan' => Plan::Pro->value,
            'password' => Hash::make(self::DEMO_PASSWORD),
        ])->save();

        return $account;
    }

    private function seedLinks(string $accountId): void
    {
        foreach ($this->demoLinks() as $link) {
            $model = LinkModel::query()->firstOrCreate(
                ['slug' => $link['slug']],
                ['id' => strtoupper((string) Str::ulid()), 'account_id' => $accountId, 'click_count' => 0],
            );

            $model->forceFill([
                'account_id' => $accountId,
                'destination_url' => $link['url'],
                'destination_host' => (string) parse_url($link['url'], PHP_URL_HOST),
                'title' => $link['title'],
                'status' => ($link['status'] ?? LinkStatus::Active)->value,
                'expires_at' => $link['expires_at'] ?? null,
                'max_clicks' => $link['max_clicks'] ?? null,
            ])->save();
        }
    }

    /**
     * One link per redirect outcome, so every response the endpoint can produce is
     * reachable immediately after seeding.
     *
     * @return list<array{slug: string, url: string, title: string, status?: LinkStatus, expires_at?: \Illuminate\Support\Carbon, max_clicks?: int}>
     */
    private function demoLinks(): array
    {
        return [
            ['slug' => 'demo-docs', 'url' => 'https://laravel.com/docs', 'title' => 'Laravel documentation'],
            ['slug' => 'demo-blog', 'url' => 'https://blog.laravel.com/', 'title' => 'Laravel blog'],
            ['slug' => 'demo-gone', 'url' => 'https://example.com/retired', 'title' => 'Archived example', 'status' => LinkStatus::Archived],
            ['slug' => 'demo-past', 'url' => 'https://example.com/expired', 'title' => 'Expired example', 'expires_at' => now()->subDay()],
            ['slug' => 'demo-cap', 'url' => 'https://example.com/limited', 'title' => 'Two clicks only', 'max_clicks' => 2],
        ];
    }

    private function report(string $token): void
    {
        $command = $this->command;

        if ($command === null) {
            return;
        }

        // Plain lines rather than the `components` helpers: those live on a protected
        // property of Command and are not reachable from a seeder.
        $command->newLine();
        $command->line('  <fg=green;options=bold>Demo account</>  '.self::DEMO_EMAIL);
        $command->line('  <fg=gray>Password</>      '.self::DEMO_PASSWORD);
        $command->line('  <fg=gray>API token</>     '.$token);
        $command->newLine();
        $command->line('  Follow a link:   <fg=gray>curl -i localhost:8080/demo-docs</>');
        $command->line('  Read the stats:  <fg=gray>curl -s -H "Authorization: Bearer '.$token.'" localhost:8080/api/v1/overview</>');
        $command->newLine();
    }
}

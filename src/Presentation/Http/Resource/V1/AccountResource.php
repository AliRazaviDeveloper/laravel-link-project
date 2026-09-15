<?php

declare(strict_types=1);

namespace Shortwave\Presentation\Http\Resource\V1;

use DateTimeInterface;
use Shortwave\Application\Account\DTO\AccountView;
use Shortwave\Application\Account\DTO\IssuedToken;

final readonly class AccountResource
{
    /**
     * @return array<string, mixed>
     */
    public static function one(AccountView $account): array
    {
        return [
            'id' => $account->id,
            'email' => $account->email,
            'name' => $account->name,
            'plan' => [
                'name' => $account->plan->value,
                'link_allowance' => $account->linkAllowance,
                'requests_per_minute' => $account->requestsPerMinute,
                'analytics_retention_days' => $account->analyticsRetentionDays,
            ],
            'created_at' => $account->createdAt->format(DateTimeInterface::RFC3339),
        ];
    }

    /**
     * The only response that ever carries the plaintext token.
     *
     * @return array<string, mixed>
     */
    public static function withToken(AccountView $account, IssuedToken $token): array
    {
        return [
            'account' => self::one($account),
            'token' => [
                'id' => $token->id,
                'name' => $token->name,
                'value' => $token->plainTextToken,
                'expires_at' => $token->expiresAt?->format(DateTimeInterface::RFC3339),
            ],
        ];
    }
}

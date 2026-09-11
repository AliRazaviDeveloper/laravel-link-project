<?php

declare(strict_types=1);

namespace Shortwave\Infrastructure\Support;

use Illuminate\Support\Carbon;
use RuntimeException;
use Shortwave\Application\Account\DTO\IssuedToken;
use Shortwave\Application\Account\Port\TokenIssuer;
use Shortwave\Domain\Account\ValueObject\AccountId;
use Shortwave\Infrastructure\Persistence\Eloquent\Model\UserModel;

final readonly class SanctumTokenIssuer implements TokenIssuer
{
    public function __construct(private ?int $expiryMinutes = null) {}

    public function issue(AccountId $accountId, string $name, array $abilities): IssuedToken
    {
        $user = UserModel::query()->find($accountId->value);

        if ($user === null) {
            // The caller has an AccountId in hand, so a missing row means the
            // account was deleted mid-request rather than bad input.
            throw new RuntimeException(sprintf('Cannot issue a token for missing account "%s".', $accountId->value));
        }

        $expiresAt = $this->expiryMinutes === null
            ? null
            : Carbon::now()->addMinutes($this->expiryMinutes);

        $token = $user->createToken($name, $abilities, $expiresAt);

        return new IssuedToken(
            id: self::identifier($token->accessToken->getAttribute('id')),
            plainTextToken: $token->plainTextToken,
            name: $name,
            expiresAt: $expiresAt?->toDateTimeImmutable(),
        );
    }

    public function revokeCurrent(AccountId $accountId, string $tokenId): void
    {
        $user = UserModel::query()->find($accountId->value);

        // Scoped to the owner so a token id from another account cannot be revoked
        // by guessing it.
        $user?->tokens()->where('id', $tokenId)->delete();
    }

    public function revokeAll(AccountId $accountId): int
    {
        $user = UserModel::query()->find($accountId->value);

        if ($user === null) {
            return 0;
        }

        $deleted = $user->tokens()->delete();

        return is_numeric($deleted) ? (int) $deleted : 0;
    }

    /**
     * Token ids are auto-incrementing integers, but the port speaks in strings so the
     * key type never leaks into the application layer.
     */
    private static function identifier(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}

<?php

declare(strict_types=1);

namespace Shortwave\Application\Account\Handler;

use Shortwave\Application\Account\Command\AuthenticateAccount;
use Shortwave\Application\Account\DTO\AccountView;
use Shortwave\Application\Account\DTO\IssuedToken;
use Shortwave\Application\Account\Exception\InvalidCredentials;
use Shortwave\Application\Account\Port\CredentialStore;
use Shortwave\Application\Account\Port\PasswordHasher;
use Shortwave\Application\Account\Port\TokenIssuer;
use Shortwave\Domain\Account\ValueObject\EmailAddress;

/**
 * @phpstan-type Session array{account: AccountView, token: IssuedToken}
 */
final readonly class AuthenticateAccountHandler
{
    private const array DEFAULT_ABILITIES = ['links:read', 'links:write', 'analytics:read'];

    /**
     * A hash of the right shape and cost, compared against when no account
     * matches. Without it the endpoint answers noticeably faster for unknown
     * emails than for wrong passwords, which is an enumeration oracle.
     */
    private const string TIMING_DECOY = '$2y$12$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';

    public function __construct(
        private CredentialStore $credentials,
        private PasswordHasher $hasher,
        private TokenIssuer $tokens,
    ) {}

    /**
     * @return Session
     */
    public function handle(AuthenticateAccount $command): array
    {
        $record = $this->credentials->findCredentials(EmailAddress::fromString($command->email));

        if ($record === null) {
            $this->hasher->verify($command->password, self::TIMING_DECOY);

            throw InvalidCredentials::create();
        }

        if (! $this->hasher->verify($command->password, $record->hashedPassword)) {
            throw InvalidCredentials::create();
        }

        return [
            'account' => AccountView::fromEntity($record->account),
            'token' => $this->tokens->issue(
                $record->account->id(),
                $command->tokenName,
                self::DEFAULT_ABILITIES,
            ),
        ];
    }
}

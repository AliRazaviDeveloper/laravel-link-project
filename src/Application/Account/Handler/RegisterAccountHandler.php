<?php

declare(strict_types=1);

namespace Shortwave\Application\Account\Handler;

use Shortwave\Application\Account\Command\RegisterAccount;
use Shortwave\Application\Account\DTO\AccountView;
use Shortwave\Application\Account\DTO\IssuedToken;
use Shortwave\Application\Account\Port\PasswordHasher;
use Shortwave\Application\Account\Port\TokenIssuer;
use Shortwave\Application\Shared\Contract\IdentityGenerator;
use Shortwave\Application\Shared\Contract\TransactionManager;
use Shortwave\Domain\Account\Entity\Account;
use Shortwave\Domain\Account\Exception\EmailAlreadyRegistered;
use Shortwave\Domain\Account\Repository\AccountRepository;
use Shortwave\Domain\Account\ValueObject\AccountId;
use Shortwave\Domain\Account\ValueObject\EmailAddress;
use Shortwave\Domain\Account\ValueObject\Plan;
use Shortwave\Domain\Shared\Contract\Clock;

/**
 * @phpstan-type Registration array{account: AccountView, token: IssuedToken}
 */
final readonly class RegisterAccountHandler
{
    /**
     * A new account can do everything to its own data; narrower tokens are
     * created explicitly afterwards.
     */
    private const array DEFAULT_ABILITIES = ['links:read', 'links:write', 'analytics:read'];

    public function __construct(
        private AccountRepository $accounts,
        private PasswordHasher $hasher,
        private TokenIssuer $tokens,
        private IdentityGenerator $identities,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @return Registration
     */
    public function handle(RegisterAccount $command): array
    {
        $email = EmailAddress::fromString($command->email);

        if ($this->accounts->findByEmail($email) !== null) {
            throw EmailAlreadyRegistered::for($email);
        }

        $account = Account::register(
            id: AccountId::fromString($this->identities->next()),
            email: $email,
            name: $command->name,
            plan: Plan::Free,
            now: $this->clock->now(),
        );

        // The insert is what actually enforces uniqueness — the check above only
        // turns the common case into a tidy error — so both live in one transaction
        // with the token that must not outlive a failed registration.
        $token = $this->transactions->transactional(
            function () use ($account, $command): IssuedToken {
                $this->accounts->add($account, $this->hasher->hash($command->password));

                return $this->tokens->issue(
                    $account->id(),
                    $command->tokenName,
                    self::DEFAULT_ABILITIES,
                );
            },
        );

        return [
            'account' => AccountView::fromEntity($account),
            'token' => $token,
        ];
    }
}

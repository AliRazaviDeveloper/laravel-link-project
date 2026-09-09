<?php

declare(strict_types=1);

namespace Shortwave\Infrastructure\Persistence\Eloquent\Repository;

use Illuminate\Database\UniqueConstraintViolationException;
use Shortwave\Application\Account\DTO\CredentialRecord;
use Shortwave\Application\Account\Port\CredentialStore;
use Shortwave\Domain\Account\Entity\Account;
use Shortwave\Domain\Account\Exception\EmailAlreadyRegistered;
use Shortwave\Domain\Account\Repository\AccountRepository;
use Shortwave\Domain\Account\ValueObject\AccountId;
use Shortwave\Domain\Account\ValueObject\EmailAddress;
use Shortwave\Infrastructure\Persistence\Eloquent\Mapper\AccountMapper;
use Shortwave\Infrastructure\Persistence\Eloquent\Model\UserModel;

/**
 * Serves both the domain repository and the login-only credential port. One class
 * because both read the same table; two interfaces because the domain must not be
 * able to reach a password hash through the repository it uses everywhere else.
 */
final readonly class EloquentAccountRepository implements AccountRepository, CredentialStore
{
    public function __construct(private AccountMapper $mapper) {}

    public function findById(AccountId $id): ?Account
    {
        $model = UserModel::query()->find($id->value);

        return $model === null ? null : $this->mapper->toDomain($model);
    }

    public function findByEmail(EmailAddress $email): ?Account
    {
        $model = $this->modelByEmail($email);

        return $model === null ? null : $this->mapper->toDomain($model);
    }

    public function add(Account $account, string $hashedPassword): void
    {
        try {
            $model = new UserModel($this->mapper->toColumns($account));

            // Assigned rather than mass-filled: the `hashed` cast means handing it
            // an already-hashed value must not run it through bcrypt twice.
            $model->password = $hashedPassword;
            $model->save();
        } catch (UniqueConstraintViolationException) {
            throw EmailAlreadyRegistered::for($account->email());
        }
    }

    public function save(Account $account): void
    {
        UserModel::query()
            ->whereKey($account->id()->value)
            ->update($this->mapper->toColumns($account));
    }

    public function findCredentials(EmailAddress $email): ?CredentialRecord
    {
        $model = $this->modelByEmail($email);

        if ($model === null) {
            return null;
        }

        return new CredentialRecord($this->mapper->toDomain($model), $model->password);
    }

    private function modelByEmail(EmailAddress $email): ?UserModel
    {
        return UserModel::query()->where('email', $email->value)->first();
    }
}

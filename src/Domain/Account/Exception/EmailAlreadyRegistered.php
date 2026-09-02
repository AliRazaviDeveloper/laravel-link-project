<?php

declare(strict_types=1);

namespace Shortwave\Domain\Account\Exception;

use Shortwave\Domain\Account\ValueObject\EmailAddress;
use Shortwave\Domain\Shared\Exception\DomainException;

final class EmailAlreadyRegistered extends DomainException
{
    private function __construct(private readonly EmailAddress $email)
    {
        parent::__construct(sprintf('The email "%s" is already registered.', $email->value));
    }

    public static function for(EmailAddress $email): self
    {
        return new self($email);
    }

    public function errorCode(): string
    {
        return 'email_already_registered';
    }

    public function context(): array
    {
        return ['email' => $this->email->value];
    }
}

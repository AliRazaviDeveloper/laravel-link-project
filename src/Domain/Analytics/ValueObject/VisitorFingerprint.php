<?php

declare(strict_types=1);

namespace Shortwave\Domain\Analytics\ValueObject;

use Shortwave\Domain\Shared\Exception\InvariantViolation;

/**
 * A rotating, non-reversible stand-in for the visitor.
 *
 * Unique-visitor counts need a stable key per person per day, but keeping raw IP
 * addresses to get one turns the click log into personal data. The hash is built
 * from IP + User-Agent + a server secret + the calendar date, so it is stable for
 * a day, useless as a cross-day identifier, and not brute-forceable without the
 * secret.
 */
final readonly class VisitorFingerprint
{
    private function __construct(public string $hash) {}

    public static function fromHash(string $hash): self
    {
        if (preg_match('/^[a-f0-9]{64}$/', $hash) !== 1) {
            throw InvariantViolation::for('fingerprint', 'A fingerprint must be a 64-character hex digest.');
        }

        return new self($hash);
    }
}

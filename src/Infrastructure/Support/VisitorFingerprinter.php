<?php

declare(strict_types=1);

namespace Shortwave\Infrastructure\Support;

use DateTimeImmutable;
use Shortwave\Domain\Analytics\ValueObject\VisitorFingerprint;

/**
 * Derives the daily visitor hash.
 *
 * Unique-visitor counts need a key that is stable for one person for one day.
 * Keeping raw IP addresses to get one would turn the click log into a store of
 * personal data with indefinite retention, so the identifying inputs never leave
 * this class.
 *
 * The date is part of the digest, which is what makes the result useless as a
 * cross-day identifier: the same person tomorrow hashes to something unrelated, so
 * the log cannot be mined for a per-person history even with the secret in hand.
 * The secret itself is what stops the hash being reversed by walking the IPv4 space,
 * which is small enough to brute-force in minutes otherwise.
 */
final readonly class VisitorFingerprinter
{
    public function __construct(private string $secret) {}

    public function fingerprint(?string $ipAddress, ?string $userAgent, DateTimeImmutable $moment): VisitorFingerprint
    {
        $material = implode('|', [
            $this->normaliseIp($ipAddress),
            trim($userAgent ?? ''),
            $moment->format('Y-m-d'),
        ]);

        return VisitorFingerprint::fromHash(hash_hmac('sha256', $material, $this->secret));
    }

    /**
     * Truncates the address before it is hashed: the last octet of an IPv4 address
     * (and the interface half of an IPv6 one) identifies a device rather than a
     * network, and a household or office reads as one visitor either way.
     */
    private function normaliseIp(?string $ipAddress): string
    {
        $ip = trim($ipAddress ?? '');

        if ($ip === '') {
            return 'unknown';
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            $octets = explode('.', $ip);

            return implode('.', [$octets[0], $octets[1], $octets[2], '0']);
        }

        $packed = @inet_pton($ip);

        if ($packed === false) {
            return 'unknown';
        }

        // Keep the routing prefix (/64), discard the interface identifier.
        return bin2hex(substr($packed, 0, 8));
    }
}

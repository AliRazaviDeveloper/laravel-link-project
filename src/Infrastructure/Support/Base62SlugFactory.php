<?php

declare(strict_types=1);

namespace Shortwave\Infrastructure\Support;

use Random\Randomizer;
use Shortwave\Application\Shared\Contract\SlugFactory;
use Shortwave\Application\Shared\Exception\SlugExhausted;
use Shortwave\Domain\Link\Repository\LinkRepository;
use Shortwave\Domain\Link\ValueObject\Slug;

/**
 * Generates random slugs from an unambiguous alphabet.
 *
 * Random rather than sequential on purpose: a counter-derived slug lets anyone walk
 * the whole system by incrementing, which leaks both volume and other people's
 * destinations.
 *
 * The alphabet is lower-case only because slugs are compared case-insensitively —
 * mixing cases in would add characters that collapse onto each other rather than
 * keyspace. `0/o`, `1/l` are dropped on top of that: these strings get read aloud,
 * typed off printed material and transcribed from screenshots, and those are the
 * pairs people get wrong. What remains is 32 symbols, or about 3.4e10 combinations
 * at seven characters, which keeps guessing a live slug impractical.
 */
final readonly class Base62SlugFactory implements SlugFactory
{
    private const string ALPHABET = 'abcdefghijkmnpqrstuvwxyz23456789';

    private const int MAX_ATTEMPTS = 6;

    public function __construct(
        private LinkRepository $links,
        private Randomizer $randomizer = new Randomizer,
        private int $length = 7,
    ) {}

    public function generate(): Slug
    {
        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $candidate = $this->randomString();

            // Reserved words are filtered before the lookup: the check is free and
            // saves a query on the rare collision.
            if (Slug::isReserved($candidate) || $this->links->slugExists(Slug::fromString($candidate))) {
                continue;
            }

            return Slug::fromString($candidate);
        }

        // Repeated collisions at this length mean the keyspace is saturating, which
        // is an operational signal rather than a user error.
        throw SlugExhausted::after(self::MAX_ATTEMPTS, $this->length);
    }

    private function randomString(): string
    {
        $alphabet = self::ALPHABET;
        $max = strlen($alphabet) - 1;
        $slug = '';

        for ($i = 0; $i < $this->length; $i++) {
            $slug .= $alphabet[$this->randomizer->getInt(0, $max)];
        }

        return $slug;
    }
}

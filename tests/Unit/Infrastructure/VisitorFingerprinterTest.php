<?php

declare(strict_types=1);

use Shortwave\Infrastructure\Support\VisitorFingerprinter;

/*
|--------------------------------------------------------------------------
| Visitor fingerprinting
|--------------------------------------------------------------------------
|
| This is the privacy boundary of the whole analytics pipeline: raw IPs and
| User-Agents go in, and nothing that identifies a person comes out. The properties
| below are what make that claim true, so each is asserted rather than assumed.
|
*/

beforeEach(function (): void {
    $this->fingerprinter = new VisitorFingerprinter('a-test-secret');
});

it('produces a 64-character hex digest', function (): void {
    $fingerprint = $this->fingerprinter->fingerprint('203.0.113.9', 'Mozilla/5.0', testNow());

    expect($fingerprint->hash)->toMatch('/^[a-f0-9]{64}$/');
});

it('is stable for the same visitor within a day', function (): void {
    // Unique-visitor counts depend on this: an unstable key would count every request
    // as a new person.
    $morning = $this->fingerprinter->fingerprint('203.0.113.9', 'Mozilla/5.0', at('2026-03-01 08:00:00'));
    $evening = $this->fingerprinter->fingerprint('203.0.113.9', 'Mozilla/5.0', at('2026-03-01 22:00:00'));

    expect($morning->hash)->toBe($evening->hash);
});

it('changes the next day', function (): void {
    // What makes the stored hash useless as a cross-day identifier: the click log
    // cannot be mined for one person's history even with the secret in hand.
    $today = $this->fingerprinter->fingerprint('203.0.113.9', 'Mozilla/5.0', at('2026-03-01 23:59:59'));
    $tomorrow = $this->fingerprinter->fingerprint('203.0.113.9', 'Mozilla/5.0', at('2026-03-02 00:00:01'));

    expect($today->hash)->not->toBe($tomorrow->hash);
});

it('changes with the secret', function (): void {
    // Without a secret the IPv4 space is small enough to brute-force in minutes, so
    // the hash must be unreproducible without it.
    $other = new VisitorFingerprinter('a-different-secret');

    expect($this->fingerprinter->fingerprint('203.0.113.9', 'UA', testNow())->hash)
        ->not->toBe($other->fingerprint('203.0.113.9', 'UA', testNow())->hash);
});

it('ignores the last octet of an IPv4 address', function (): void {
    // A household or office shares a visitor identity by design: the final octet
    // identifies a device, which is more than a click count needs.
    $a = $this->fingerprinter->fingerprint('203.0.113.9', 'UA', testNow());
    $b = $this->fingerprinter->fingerprint('203.0.113.200', 'UA', testNow());

    expect($a->hash)->toBe($b->hash);
});

it('separates different networks', function (): void {
    $a = $this->fingerprinter->fingerprint('203.0.113.9', 'UA', testNow());
    $b = $this->fingerprinter->fingerprint('198.51.100.9', 'UA', testNow());

    expect($a->hash)->not->toBe($b->hash);
});

it('keeps only the routing prefix of an IPv6 address', function (): void {
    $a = $this->fingerprinter->fingerprint('2001:db8:1234:5678:aaaa:bbbb:cccc:dddd', 'UA', testNow());
    $b = $this->fingerprinter->fingerprint('2001:db8:1234:5678:1111:2222:3333:4444', 'UA', testNow());
    $c = $this->fingerprinter->fingerprint('2001:db8:1234:9999:aaaa:bbbb:cccc:dddd', 'UA', testNow());

    expect($a->hash)->toBe($b->hash)
        ->and($a->hash)->not->toBe($c->hash);
});

it('distinguishes visitors behind one address by User-Agent', function (): void {
    $a = $this->fingerprinter->fingerprint('203.0.113.9', 'Chrome', testNow());
    $b = $this->fingerprinter->fingerprint('203.0.113.9', 'Firefox', testNow());

    expect($a->hash)->not->toBe($b->hash);
});

it('handles a missing or unparseable address without failing', function (): void {
    // A redirect must never fail because the address was absent behind a misconfigured
    // proxy.
    expect($this->fingerprinter->fingerprint(null, 'UA', testNow())->hash)->toMatch('/^[a-f0-9]{64}$/')
        ->and($this->fingerprinter->fingerprint('not-an-ip', 'UA', testNow())->hash)->toMatch('/^[a-f0-9]{64}$/');
});

it('never exposes the inputs in the output', function (): void {
    $hash = $this->fingerprinter->fingerprint('203.0.113.9', 'Mozilla/5.0 (Macintosh)', testNow())->hash;

    expect($hash)->not->toContain('203.0.113')
        ->and($hash)->not->toContain('Macintosh');
});

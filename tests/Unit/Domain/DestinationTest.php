<?php

declare(strict_types=1);

use Shortwave\Domain\Link\ValueObject\Destination;
use Shortwave\Domain\Shared\Exception\InvariantViolation;

it('accepts absolute http and https URLs', function (string $url): void {
    expect(Destination::fromString($url)->value)->toBe($url);
})->with([
    'https://example.com',
    'http://example.com/path?utm_source=x#frag',
    'https://sub.domain.example.co.uk/a/b',
]);

it('extracts the host and scheme', function (): void {
    $destination = Destination::fromString('HTTPS://Example.COM/Path');

    expect($destination->host)->toBe('example.com')
        ->and($destination->scheme)->toBe('https')
        // The path keeps its original case: only the host is case-insensitive.
        ->and($destination->value)->toBe('HTTPS://Example.COM/Path');
});

it('groups subdomains under an apex host for reporting', function (string $url, string $expected): void {
    expect(Destination::fromString($url)->apexHost())->toBe($expected);
})->with([
    ['https://example.com/a', 'example.com'],
    ['https://blog.example.com/a', 'example.com'],
    ['https://a.b.c.example.com/', 'example.com'],
]);

it('rejects schemes that are not http or https', function (string $url): void {
    expect(fn (): Destination => Destination::fromString($url))->toThrow(InvariantViolation::class);
})->with([
    'javascript scheme' => 'javascript:alert(1)',
    'data scheme' => 'data:text/html;base64,PHNjcmlwdD4=',
    'file scheme' => 'file:///etc/passwd',
    'ftp scheme' => 'ftp://example.com',
    'no scheme' => 'example.com/path',
    'protocol relative' => '//example.com/path',
]);

/*
 * A shortener is an open redirector by design, which makes it a convenient way to
 * reach hosts the caller cannot reach directly. These are refused at write time so
 * such a link never exists to be followed.
 */
it('refuses hosts that are not publicly routable', function (string $url): void {
    expect(fn (): Destination => Destination::fromString($url))
        ->toThrow(InvariantViolation::class, 'not publicly routable');
})->with([
    'loopback name' => 'http://localhost/admin',
    'loopback subdomain' => 'http://api.localhost/',
    'loopback v4' => 'http://127.0.0.1/',
    'private 10/8' => 'http://10.0.0.5/',
    'private 172.16/12' => 'http://172.16.4.9/',
    'private 192.168/16' => 'http://192.168.1.1/',
    'link local' => 'http://169.254.169.254/latest/meta-data/',
    'cloud metadata host' => 'http://metadata.google.internal/',
    'ipv6 loopback' => 'http://[::1]/',
]);

it('allows public IP literals', function (): void {
    expect(Destination::fromString('https://93.184.216.34/')->host)->toBe('93.184.216.34');
});

it('rejects an empty or oversized URL', function (): void {
    expect(fn (): Destination => Destination::fromString('   '))
        ->toThrow(InvariantViolation::class)
        ->and(fn (): Destination => Destination::fromString('https://example.com/'.str_repeat('a', 2100)))
        ->toThrow(InvariantViolation::class);
});

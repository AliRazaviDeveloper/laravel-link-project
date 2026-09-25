<?php

declare(strict_types=1);

use Shortwave\Domain\Analytics\Enum\DeviceType;
use Shortwave\Infrastructure\Support\UserAgentClassifier;

/*
|--------------------------------------------------------------------------
| User-Agent classification
|--------------------------------------------------------------------------
|
| The ordering in this classifier is the whole algorithm, and it is the kind of thing
| that quietly regresses: Edge announces itself as Chrome, Chrome as Safari, and most
| crawlers carry a complete browser UA. These are real strings, so a reordering that
| would misattribute traffic fails here rather than in a report.
|
*/

beforeEach(function (): void {
    $this->classifier = new UserAgentClassifier;
});

it('identifies desktop browsers', function (string $ua, string $browser, string $platform): void {
    $profile = $this->classifier->classify($ua);

    expect($profile->type)->toBe(DeviceType::Desktop)
        ->and($profile->browser)->toBe($browser)
        ->and($profile->platform)->toBe($platform);
})->with([
    'chrome on windows' => [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36',
        'chrome', 'windows',
    ],
    'safari on macos' => [
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.3 Safari/605.1.15',
        'safari', 'macos',
    ],
    'firefox on linux' => [
        'Mozilla/5.0 (X11; Linux x86_64; rv:135.0) Gecko/20100101 Firefox/135.0',
        'firefox', 'linux',
    ],
    // Edge sends "Chrome/..." and "Safari/..." too, so it must be matched first.
    'edge on windows' => [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36 Edg/134.0.0.0',
        'edge', 'windows',
    ],
]);

it('identifies mobile devices', function (string $ua, string $platform): void {
    $profile = $this->classifier->classify($ua);

    expect($profile->type)->toBe(DeviceType::Mobile)
        ->and($profile->platform)->toBe($platform);
})->with([
    'iphone' => [
        'Mozilla/5.0 (iPhone; CPU iPhone OS 18_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.3 Mobile/15E148 Safari/604.1',
        'ios',
    ],
    'android phone' => [
        'Mozilla/5.0 (Linux; Android 15; Pixel 9) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Mobile Safari/537.36',
        'android',
    ],
]);

it('separates tablets from phones', function (string $ua): void {
    // An iPad and an Android tablet are otherwise indistinguishable from phones;
    // "Mobile" being absent is the only signal.
    expect($this->classifier->classify($ua)->type)->toBe(DeviceType::Tablet);
})->with([
    'ipad' => 'Mozilla/5.0 (iPad; CPU OS 18_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.3 Safari/604.1',
    'android tablet' => 'Mozilla/5.0 (Linux; Android 15; Tab S10) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36',
]);

it('flags automated traffic as a bot even when it claims to be a browser', function (string $ua): void {
    expect($this->classifier->classify($ua)->type)->toBe(DeviceType::Bot);
})->with([
    'googlebot' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
    'bingbot' => 'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)',
    'curl' => 'curl/8.7.1',
    'python' => 'python-requests/2.32.3',
    'headless chrome' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/134.0.0.0 Safari/537.36',
    'link preview' => 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)',
    'uptime monitor' => 'Mozilla/5.0 (compatible; Pingdom.com_bot_version_1.4)',
]);

it('treats a missing User-Agent as a bot', function (): void {
    // A stripped header is far more often a script than a browser, and counting it as
    // human would inflate every campaign that gets scraped.
    expect($this->classifier->classify(null)->type)->toBe(DeviceType::Bot)
        ->and($this->classifier->classify('   ')->type)->toBe(DeviceType::Bot);
});

it('falls back to unknown rather than guessing', function (): void {
    $profile = $this->classifier->classify('SomeCustomClient/1.0');

    expect($profile->type)->toBe(DeviceType::Unknown)
        ->and($profile->browser)->toBe('unknown')
        ->and($profile->platform)->toBe('unknown');
});

it('excludes bots from human traffic and keeps everything else', function (): void {
    expect(DeviceType::Bot->countsAsHuman())->toBeFalse()
        ->and(DeviceType::Unknown->countsAsHuman())->toBeTrue()
        ->and(DeviceType::Mobile->countsAsHuman())->toBeTrue();
});

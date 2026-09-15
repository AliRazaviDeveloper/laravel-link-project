<?php

declare(strict_types=1);

namespace Shortwave\Infrastructure\Support;

use Shortwave\Domain\Analytics\Enum\DeviceType;
use Shortwave\Domain\Analytics\ValueObject\DeviceProfile;

/**
 * Coarse User-Agent classification.
 *
 * A full device database (matomo/device-detector and friends) carries thousands of
 * regexes and needs regular updates to stay accurate. We report device type, browser
 * family and platform — nothing more — so the trade lands on the side of a small
 * table we can read, test and reason about, at the cost of lumping unusual clients
 * into "unknown". The ordering below is the whole algorithm: bots first, because
 * plenty of crawlers also claim to be Chrome on Windows, then the browser engines
 * that impersonate each other, most specific first.
 */
final readonly class UserAgentClassifier
{
    /**
     * Substrings that mark automated traffic. Matched before anything else, since
     * most crawlers carry a full browser UA alongside their own name.
     *
     * @var list<string>
     */
    private const array BOT_MARKERS = [
        'bot', 'crawler', 'spider', 'curl', 'wget', 'python-requests', 'httpclient',
        'headlesschrome', 'phantomjs', 'slurp', 'facebookexternalhit', 'preview',
        'monitoring', 'pingdom', 'uptime', 'scrapy', 'go-http-client', 'okhttp',
    ];

    /**
     * Browser family => markers, ordered so impersonators are caught before the
     * engine they claim. Edge says "Chrome", Chrome says "Safari", so a naive pass
     * would report almost everything as Safari.
     *
     * @var array<string, list<string>>
     */
    private const array BROWSERS = [
        'edge' => ['edg/', 'edge/', 'edga/', 'edgios/'],
        'opera' => ['opr/', 'opera', 'opios/'],
        'samsung' => ['samsungbrowser'],
        'firefox' => ['firefox/', 'fxios/'],
        'chrome' => ['chrome/', 'crios/', 'chromium/'],
        'safari' => ['safari/'],
    ];

    /**
     * @var array<string, list<string>>
     */
    private const array PLATFORMS = [
        'android' => ['android'],
        'ios' => ['iphone', 'ipad', 'ipod'],
        'macos' => ['macintosh', 'mac os x'],
        'windows' => ['windows'],
        'chromeos' => ['cros'],
        'linux' => ['linux', 'ubuntu', 'fedora'],
    ];

    public function classify(?string $userAgent): DeviceProfile
    {
        $ua = strtolower(trim($userAgent ?? ''));

        if ($ua === '') {
            // No User-Agent at all is far more often a script than a browser with
            // the header stripped, so it counts as a bot rather than as unknown.
            return DeviceProfile::of(DeviceType::Bot, 'unknown', 'unknown');
        }

        $platform = $this->firstMatch($ua, self::PLATFORMS);
        $browser = $this->firstMatch($ua, self::BROWSERS);

        return DeviceProfile::of($this->deviceType($ua, $platform), $browser, $platform);
    }

    private function deviceType(string $ua, ?string $platform): DeviceType
    {
        if ($this->containsAny($ua, self::BOT_MARKERS)) {
            return DeviceType::Bot;
        }

        // A tablet is a mobile device that says so; iPads and Android tablets both
        // otherwise look exactly like phones.
        if (str_contains($ua, 'ipad') || (str_contains($ua, 'android') && ! str_contains($ua, 'mobile'))) {
            return DeviceType::Tablet;
        }

        if (str_contains($ua, 'mobile') || $platform === 'ios' || $platform === 'android') {
            return DeviceType::Mobile;
        }

        return $platform === null ? DeviceType::Unknown : DeviceType::Desktop;
    }

    /**
     * @param  array<string, list<string>>  $table
     */
    private function firstMatch(string $ua, array $table): ?string
    {
        foreach ($table as $label => $markers) {
            if ($this->containsAny($ua, $markers)) {
                return $label;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $needles
     */
    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}

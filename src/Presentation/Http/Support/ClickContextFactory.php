<?php

declare(strict_types=1);

namespace Shortwave\Presentation\Http\Support;

use Illuminate\Http\Request;
use Shortwave\Application\Analytics\DTO\ClickContext;
use Shortwave\Domain\Analytics\ValueObject\GeoLocation;
use Shortwave\Domain\Analytics\ValueObject\Referrer;
use Shortwave\Domain\Shared\Contract\Clock;
use Shortwave\Infrastructure\Support\UserAgentClassifier;
use Shortwave\Infrastructure\Support\VisitorFingerprinter;

/**
 * The boundary where request data becomes analytics data.
 *
 * This is the only place the raw IP address and User-Agent are read. They are
 * reduced to a rotating fingerprint and a coarse device profile here and never
 * travel further, which is what keeps them out of the queue payload, the analytics
 * documents and the logs.
 *
 * Country comes from an edge header rather than a lookup: the CDN in front of this
 * service already resolved it, and repeating the work per redirect would add a
 * database hit to the hot path for a value we were handed.
 */
final readonly class ClickContextFactory
{
    /**
     * In the order we trust them. A locally-run instance behind no CDN simply gets
     * `unknown`, which is correct rather than guessed.
     *
     * @var list<string>
     */
    private const array COUNTRY_HEADERS = [
        'CF-IPCountry',
        'CloudFront-Viewer-Country',
        'X-Vercel-IP-Country',
        'Fly-Client-Country',
    ];

    public function __construct(
        private VisitorFingerprinter $fingerprinter,
        private UserAgentClassifier $classifier,
        private Clock $clock,
    ) {}

    public function fromRequest(Request $request): ClickContext
    {
        $now = $this->clock->now();
        $userAgent = $request->userAgent();

        return new ClickContext(
            occurredAt: $now,
            visitor: $this->fingerprinter->fingerprint($request->ip(), $userAgent, $now),
            device: $this->classifier->classify($userAgent),
            referrer: Referrer::fromHeader($request->header('Referer')),
            geo: GeoLocation::fromCountryCode($this->country($request)),
        );
    }

    private function country(Request $request): ?string
    {
        foreach (self::COUNTRY_HEADERS as $header) {
            $value = $request->header($header);

            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        return null;
    }
}

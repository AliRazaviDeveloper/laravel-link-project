<?php

declare(strict_types=1);

namespace Shortwave\Presentation\Http\Controller;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Shortwave\Application\Link\Handler\ResolveSlugHandler;
use Shortwave\Application\Link\Query\ResolveSlug;
use Shortwave\Presentation\Http\Support\ClickContextFactory;

/**
 * The public redirect endpoint — the only unauthenticated write path in the system.
 *
 * 302 rather than 301: a permanent redirect is cached by browsers and intermediaries
 * indefinitely, which both breaks retargeting a link and makes the click invisible to
 * us on every subsequent visit. Analytics is the product here, so the cacheable status
 * code is the wrong one.
 */
final class RedirectController extends Controller
{
    public function __invoke(
        Request $request,
        string $slug,
        ResolveSlugHandler $handler,
        ClickContextFactory $contexts,
    ): RedirectResponse {
        $resolution = $handler->handle(
            new ResolveSlug($slug),
            $contexts->fromRequest($request),
        );

        return new RedirectResponse($resolution->destinationUrl, 302, [
            // Belt and braces alongside the 302: some intermediaries cache
            // redirects that carry no explicit policy.
            'Cache-Control' => 'private, no-store, max-age=0',
            // Keeps the destination from learning which short link referred the
            // visitor, and the slug out of third-party analytics.
            'Referrer-Policy' => 'no-referrer',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}

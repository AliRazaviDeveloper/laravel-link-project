<?php

declare(strict_types=1);

namespace Shortwave\Presentation\Http\Controller;

use Illuminate\Http\Request;
use Shortwave\Application\Shared\Exception\AuthorizationFailed;

/**
 * Shared base for API controllers.
 *
 * Holds only the account-id lookup. The auth guard has already rejected anonymous
 * requests by the time a controller runs, so a missing user here is a routing or
 * middleware mistake rather than a client error — hence the exception instead of a
 * nullable return that every caller would have to handle.
 */
abstract class Controller
{
    protected function accountId(Request $request): string
    {
        $user = $request->user();

        if ($user === null) {
            throw AuthorizationFailed::forResource('account', 'unknown');
        }

        $identifier = $user->getAuthIdentifier();

        if (! is_string($identifier)) {
            throw AuthorizationFailed::forResource('account', 'non-string identifier');
        }

        return $identifier;
    }
}

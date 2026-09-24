<?php

declare(strict_types=1);

namespace Shortwave\Presentation\Http\Problem;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Shortwave\Application\Account\Exception\InvalidCredentials;
use Shortwave\Application\Shared\Exception\AuthorizationFailed;
use Shortwave\Application\Shared\Exception\SlugExhausted;
use Shortwave\Domain\Account\Exception\AccountNotFound;
use Shortwave\Domain\Account\Exception\EmailAlreadyRegistered;
use Shortwave\Domain\Account\Exception\LinkAllowanceExceeded;
use Shortwave\Domain\Link\Enum\UnresolvableReason;
use Shortwave\Domain\Link\Exception\LinkNotFound;
use Shortwave\Domain\Link\Exception\LinkNotResolvable;
use Shortwave\Domain\Link\Exception\SlugAlreadyTaken;
use Shortwave\Domain\Shared\Exception\DomainException;
use Shortwave\Domain\Shared\Exception\InvariantViolation;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

/**
 * Single place where a thrown exception becomes an HTTP status.
 *
 * Keeping the mapping here rather than in controllers is what lets the layers below
 * throw meaningful exceptions instead of returning responses, and it makes the whole
 * error contract of the API readable in one screen.
 */
final readonly class ProblemDetailsRenderer
{
    /**
     * Domain failures and their status codes.
     *
     * A resource owned by someone else answers 404, not 403: confirming that an id
     * exists is itself a leak, and a client that cannot see a link has no use for
     * the distinction.
     *
     * @var array<class-string<Throwable>, array{int, string}>
     */
    private const array DOMAIN_STATUSES = [
        InvariantViolation::class => [422, 'Unprocessable Entity'],
        SlugAlreadyTaken::class => [409, 'Conflict'],
        EmailAlreadyRegistered::class => [409, 'Conflict'],
        LinkAllowanceExceeded::class => [403, 'Plan Limit Reached'],
        LinkNotFound::class => [404, 'Not Found'],
        AccountNotFound::class => [404, 'Not Found'],
    ];

    public static function register(Exceptions $exceptions): void
    {
        $renderer = new self;

        // Every route, not just the API prefix. The public redirect endpoint is hit by
        // browsers that send no Accept header, and there are no HTML error views in
        // this service — without this, an expired short link answers 500 while trying
        // to render a view that does not exist.
        $exceptions->render(
            fn (Throwable $exception, Request $request) => $renderer->render($exception, $request),
        );

        // Reporting is noise for anything the client caused; only genuine faults
        // should page anyone.
        $exceptions->dontReport([
            DomainException::class,
            InvalidCredentials::class,
            AuthorizationFailed::class,
        ]);
    }

    public function render(Throwable $exception, Request $request): JsonResponse
    {
        $problem = $this->toProblem($exception);
        $requestId = $request->attributes->get('request_id');

        return is_string($requestId)
            ? $problem->withInstance($requestId)->toResponse()
            : $problem->toResponse();
    }

    /**
     * Total by construction: the `default` arm turns anything unrecognised into a 500,
     * so there is no exception this cannot describe.
     */
    private function toProblem(Throwable $exception): ProblemDetails
    {
        return match (true) {
            $exception instanceof ValidationException => $this->validation($exception),
            $exception instanceof LinkNotResolvable => $this->unresolvable($exception),
            $exception instanceof DomainException => $this->domain($exception),
            $exception instanceof InvalidCredentials => ProblemDetails::make(
                'invalid_credentials',
                'Unauthorized',
                401,
                $exception->getMessage(),
            ),
            $exception instanceof AuthenticationException => ProblemDetails::make(
                'unauthenticated',
                'Unauthorized',
                401,
                'A valid API token is required.',
            ),
            $exception instanceof AuthorizationFailed,
            $exception instanceof AuthorizationException => ProblemDetails::make(
                'forbidden',
                'Forbidden',
                403,
                'This token may not perform that action.',
            ),
            $exception instanceof SlugExhausted => ProblemDetails::make(
                'slug_unavailable',
                'Service Unavailable',
                503,
                'Could not allocate a short link; please retry.',
            ),
            $exception instanceof ModelNotFoundException,
            $exception instanceof NotFoundHttpException => ProblemDetails::make(
                'not_found',
                'Not Found',
                404,
                'The requested resource does not exist.',
            ),
            $exception instanceof TooManyRequestsHttpException => ProblemDetails::make(
                'rate_limited',
                'Too Many Requests',
                429,
                'Rate limit exceeded; slow down and retry after the indicated delay.',
            ),
            $exception instanceof HttpExceptionInterface => ProblemDetails::make(
                'http_error',
                'Request Failed',
                $exception->getStatusCode(),
                $exception->getMessage() === '' ? 'The request could not be completed.' : $exception->getMessage(),
            ),
            // Anything unrecognised is a bug. The message is withheld on purpose:
            // stack traces and driver errors routinely contain credentials and
            // internal hostnames. The request id is the way back to the log entry.
            default => ProblemDetails::make(
                'internal_error',
                'Internal Server Error',
                500,
                'An unexpected error occurred.',
            ),
        };
    }

    private function validation(ValidationException $exception): ProblemDetails
    {
        return ProblemDetails::make(
            'validation_failed',
            'Unprocessable Entity',
            422,
            'The request payload failed validation.',
            ['errors' => $exception->errors()],
        );
    }

    private function unresolvable(LinkNotResolvable $exception): ProblemDetails
    {
        // 410 says "this existed and is deliberately gone", which is the honest
        // answer for an expired or exhausted link and tells crawlers to stop asking.
        // An archived link answers 404 instead, because archiving is reversible.
        $status = $exception->reason === UnresolvableReason::Archived ? 404 : 410;

        return ProblemDetails::make(
            $exception->errorCode(),
            $status === 404 ? 'Not Found' : 'Gone',
            $status,
            $exception->getMessage(),
            $exception->context(),
        );
    }

    private function domain(DomainException $exception): ProblemDetails
    {
        [$status, $title] = self::DOMAIN_STATUSES[$exception::class] ?? [422, 'Unprocessable Entity'];

        return ProblemDetails::make(
            $exception->errorCode(),
            $title,
            $status,
            $exception->getMessage(),
            $exception->context(),
        );
    }
}

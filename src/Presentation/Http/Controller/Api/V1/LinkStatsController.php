<?php

declare(strict_types=1);

namespace Shortwave\Presentation\Http\Controller\Api\V1;

use Illuminate\Http\JsonResponse;
use Shortwave\Application\Analytics\Handler\GetLinkStatsHandler;
use Shortwave\Application\Analytics\Query\GetLinkStats;
use Shortwave\Application\Shared\Support\Input;
use Shortwave\Presentation\Http\Controller\Controller;
use Shortwave\Presentation\Http\Request\V1\LinkStatsRequest;
use Shortwave\Presentation\Http\Resource\V1\LinkStatsResource;

final class LinkStatsController extends Controller
{
    public function __invoke(
        LinkStatsRequest $request,
        string $link,
        GetLinkStatsHandler $handler,
    ): JsonResponse {
        /** @var array<string, mixed> $input */
        $input = $request->validated();

        $stats = $handler->handle(new GetLinkStats(
            accountId: $this->accountId($request),
            linkId: $link,
            from: Input::nullableString($input, 'from'),
            until: Input::nullableString($input, 'until'),
            granularity: $request->granularity(),
            dimensions: $request->dimensions(),
            breakdownLimit: Input::nullableInt($input, 'limit') ?? 10,
        ));

        return new JsonResponse(['data' => LinkStatsResource::one($stats)]);
    }
}

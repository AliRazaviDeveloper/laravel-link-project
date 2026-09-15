<?php

declare(strict_types=1);

namespace Shortwave\Presentation\Http\Controller\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Shortwave\Application\Analytics\Handler\GetAccountOverviewHandler;
use Shortwave\Application\Analytics\Query\GetAccountOverview;
use Shortwave\Presentation\Http\Controller\Controller;
use Shortwave\Presentation\Http\Resource\V1\OverviewResource;

final class OverviewController extends Controller
{
    public function __invoke(Request $request, GetAccountOverviewHandler $handler): JsonResponse
    {
        $days = (int) $request->integer('days', 30);

        $overview = $handler->handle(new GetAccountOverview(
            accountId: $this->accountId($request),
            days: max(1, min($days, 365)),
        ));

        return new JsonResponse(['data' => OverviewResource::one($overview)]);
    }
}

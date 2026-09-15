<?php

declare(strict_types=1);

namespace Shortwave\Presentation\Http\Controller\Api\V1;

use Illuminate\Http\JsonResponse;
use Shortwave\Application\Link\Handler\ImportLinksHandler;
use Shortwave\Presentation\Http\Controller\Controller;
use Shortwave\Presentation\Http\Request\V1\ImportLinksRequest;
use Shortwave\Presentation\Http\Resource\V1\ImportResource;

final class LinkImportController extends Controller
{
    public function __invoke(ImportLinksRequest $request, ImportLinksHandler $handler): JsonResponse
    {
        $report = $handler->handle($request->toCommand($this->accountId($request)));

        return new JsonResponse(
            ImportResource::one($report),
            ImportResource::statusFor($report),
        );
    }
}

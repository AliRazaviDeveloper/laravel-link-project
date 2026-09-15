<?php

declare(strict_types=1);

namespace Shortwave\Presentation\Http\Controller\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Shortwave\Application\Link\Command\ArchiveLink;
use Shortwave\Application\Link\Command\CreateLink;
use Shortwave\Application\Link\Command\RestoreLink;
use Shortwave\Application\Link\Command\UpdateLink;
use Shortwave\Application\Link\Handler\ArchiveLinkHandler;
use Shortwave\Application\Link\Handler\CreateLinkHandler;
use Shortwave\Application\Link\Handler\ListLinksHandler;
use Shortwave\Application\Link\Handler\RestoreLinkHandler;
use Shortwave\Application\Link\Handler\ShowLinkHandler;
use Shortwave\Application\Link\Handler\UpdateLinkHandler;
use Shortwave\Application\Link\Query\ListLinks;
use Shortwave\Application\Link\Query\ShowLink;
use Shortwave\Application\Shared\Support\Input;
use Shortwave\Presentation\Http\Controller\Controller;
use Shortwave\Presentation\Http\Request\V1\ListLinksRequest;
use Shortwave\Presentation\Http\Request\V1\StoreLinkRequest;
use Shortwave\Presentation\Http\Request\V1\UpdateLinkRequest;
use Shortwave\Presentation\Http\Resource\V1\LinkResource;

/**
 * Controllers here do three things and nothing else: read the request, call one
 * handler, shape the response. No business rule and no store access lives at this
 * level, which is what keeps the same use cases reachable from a console command or
 * a future gRPC surface without rewriting them.
 */
final class LinkController extends Controller
{
    public function index(ListLinksRequest $request, ListLinksHandler $handler): JsonResponse
    {
        $page = $handler->handle(new ListLinks(
            accountId: $this->accountId($request),
            filter: $request->toFilter(),
        ));

        return new JsonResponse([
            'data' => LinkResource::many($page->items),
            'meta' => [
                'total' => $page->total,
                'page' => $page->page,
                'per_page' => $page->perPage,
                'last_page' => $page->lastPage(),
                'has_more' => $page->hasMore(),
            ],
        ]);
    }

    public function store(StoreLinkRequest $request, CreateLinkHandler $handler): JsonResponse
    {
        /** @var array<string, mixed> $input */
        $input = $request->validated();

        $link = $handler->handle(new CreateLink(
            accountId: $this->accountId($request),
            destinationUrl: Input::string($input, 'destination_url'),
            slug: Input::nullableString($input, 'slug'),
            title: Input::nullableString($input, 'title'),
            expiresAt: Input::nullableString($input, 'expires_at'),
            maxClicks: Input::nullableInt($input, 'max_clicks'),
        ));

        return new JsonResponse(
            ['data' => LinkResource::one($link)],
            201,
            ['Location' => $link->shortUrl],
        );
    }

    public function show(Request $request, string $link, ShowLinkHandler $handler): JsonResponse
    {
        $view = $handler->handle(new ShowLink($this->accountId($request), $link));

        return new JsonResponse(['data' => LinkResource::one($view)]);
    }

    public function update(UpdateLinkRequest $request, string $link, UpdateLinkHandler $handler): JsonResponse
    {
        $view = $handler->handle(UpdateLink::fromPayload(
            $this->accountId($request),
            $link,
            $request->changedFields(),
        ));

        return new JsonResponse(['data' => LinkResource::one($view)]);
    }

    /**
     * Archives rather than deletes. Click history outlives the link it describes, and
     * a hard delete would orphan documents in the analytics store that nothing can
     * label afterwards. `POST .../restore` is the inverse.
     */
    public function destroy(Request $request, string $link, ArchiveLinkHandler $handler): JsonResponse
    {
        $handler->handle(new ArchiveLink($this->accountId($request), $link));

        return new JsonResponse(status: 204);
    }

    public function restore(Request $request, string $link, RestoreLinkHandler $handler): JsonResponse
    {
        $view = $handler->handle(new RestoreLink($this->accountId($request), $link));

        return new JsonResponse(['data' => LinkResource::one($view)]);
    }
}

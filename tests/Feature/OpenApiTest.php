<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Symfony\Component\Yaml\Yaml;

/*
|--------------------------------------------------------------------------
| OpenAPI specification
|--------------------------------------------------------------------------
|
| The spec is written by hand, which buys explanations that generated documentation
| cannot express and costs the risk of drift. These tests are what make that trade
| safe: the document cannot fall behind the router without CI noticing.
|
*/

/**
 * @return array<string, mixed>
 */
function spec(): array
{
    /** @var array<string, mixed> $parsed */
    $parsed = Yaml::parseFile(base_path('docs/openapi.yaml'));

    return $parsed;
}

/**
 * Registered routes as `METHOD /path`, in the shape the spec uses.
 *
 * @return list<string>
 */
function registeredOperations(): array
{
    $operations = [];

    foreach (Route::getRoutes() as $route) {
        $uri = '/'.ltrim($route->uri(), '/');

        // Laravel's own health endpoint and the docs pages are not part of the API
        // contract, so they are not expected in the document.
        if (in_array($uri, ['/up', '/docs', '/docs/openapi.yaml'], true)) {
            continue;
        }

        foreach ($route->methods() as $method) {
            if (in_array($method, ['HEAD', 'OPTIONS'], true)) {
                continue;
            }

            $operations[] = strtolower($method).' '.$uri;
        }
    }

    sort($operations);

    return array_values(array_unique($operations));
}

/**
 * Operations the document declares, in the same shape.
 *
 * @return list<string>
 */
function documentedOperations(): array
{
    $operations = [];

    /** @var array<string, array<string, mixed>> $paths */
    $paths = spec()['paths'];

    foreach ($paths as $path => $methods) {
        foreach (array_keys($methods) as $method) {
            // `parameters` is a sibling of the verbs, not a verb.
            if (! in_array($method, ['get', 'post', 'put', 'patch', 'delete'], true)) {
                continue;
            }

            $operations[] = $method.' '.$path;
        }
    }

    sort($operations);

    return $operations;
}

it('is valid YAML with the required top-level members', function (): void {
    $spec = spec();

    expect($spec)->toHaveKeys(['openapi', 'info', 'paths', 'components'])
        ->and($spec['openapi'])->toStartWith('3.1')
        ->and($spec['info'])->toHaveKeys(['title', 'version', 'description']);
});

it('documents every route the application registers', function (): void {
    $undocumented = array_diff(registeredOperations(), documentedOperations());

    expect($undocumented)->toBe(
        [],
        'These routes exist but are missing from docs/openapi.yaml: '.implode(', ', $undocumented),
    );
});

it('documents no route the application does not register', function (): void {
    $phantom = array_diff(documentedOperations(), registeredOperations());

    // Catches the other direction of drift: an endpoint removed from the code but left in
    // the document, which sends clients to a 404 the docs promised would work.
    expect($phantom)->toBe(
        [],
        'These operations are documented but not routed: '.implode(', ', $phantom),
    );
});

it('gives every operation an id, a summary and a tag', function (): void {
    /** @var array<string, array<string, mixed>> $paths */
    $paths = spec()['paths'];
    $incomplete = [];

    foreach ($paths as $path => $methods) {
        foreach ($methods as $method => $operation) {
            if (! in_array($method, ['get', 'post', 'put', 'patch', 'delete'], true)) {
                continue;
            }

            /** @var array<string, mixed> $operation */
            foreach (['operationId', 'summary', 'tags', 'responses'] as $required) {
                if (! array_key_exists($required, $operation)) {
                    $incomplete[] = sprintf('%s %s is missing "%s"', strtoupper($method), $path, $required);
                }
            }
        }
    }

    // Client generators name methods after operationId, so a missing one produces an
    // unusable SDK rather than a cosmetic gap.
    expect($incomplete)->toBe([], implode('; ', $incomplete));
});

it('resolves every internal reference', function (): void {
    $spec = spec();
    $encoded = json_encode($spec, JSON_THROW_ON_ERROR);

    preg_match_all('~"#/components/(\w+)/(\w+)"~', $encoded, $matches, PREG_SET_ORDER);

    $missing = [];

    foreach ($matches as [$ref, $section, $name]) {
        if (! isset($spec['components'][$section][$name])) {
            $missing[] = $ref;
        }
    }

    // A dangling $ref renders as an empty box in Swagger UI rather than an error, so it
    // is the kind of mistake that survives a visual check.
    expect(array_unique($missing))->toBe([], 'Unresolved references: '.implode(', ', array_unique($missing)));
});

it('declares every problem type the application can return', function (): void {
    $encoded = json_encode(spec(), JSON_THROW_ON_ERROR);

    // The error contract is the part clients branch on, so each identifier has to appear
    // somewhere in the document.
    foreach ([
        'link_not_found',
        'link_not_resolvable',
        'slug_already_taken',
        'email_already_registered',
        'link_allowance_exceeded',
        'invariant_violation',
        'validation_failed',
        'unauthenticated',
    ] as $type) {
        expect($encoded)->toContain($type);
    }
});

it('serves the specification over HTTP', function (): void {
    $response = $this->get('/docs/openapi.yaml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/yaml; charset=utf-8');

    /** @var array<string, mixed> $parsed */
    $parsed = Yaml::parse($response->getContent() ?: '');

    expect($parsed['info']['title'])->toBe('Shortwave API');
});

it('serves the reference UI', function (): void {
    $response = $this->get('/docs')->assertOk();

    expect($response->getContent())->toContain('swagger-ui')
        ->toContain('/docs/openapi.yaml');
});

it('keeps the docs paths off the redirect handler', function (): void {
    // `docs` is a reserved slug, so it can never be claimed, but the route order matters
    // too — the catch-all would otherwise swallow it.
    expect(Route::getRoutes()->match(
        Illuminate\Http\Request::create('/docs', 'GET'),
    )->getName())->toBe('docs.ui');
});

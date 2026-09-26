<?php

declare(strict_types=1);

namespace Shortwave\Presentation\Http\Controller;

use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Serves the OpenAPI document and a Swagger UI around it.
 *
 * The spec is a hand-written YAML file rather than something generated from annotations.
 * That is a deliberate choice: annotation-driven generation puts several hundred lines of
 * attributes into controllers whose whole job is to stay three lines long, and it tends to
 * describe the shape of the code rather than the contract — the parts of an API that
 * actually need explaining (why a mixed import answers 207, why an archived link is 404
 * and an expired one 410) have nowhere to live.
 *
 * The risk of a hand-written spec is drift, so a test asserts that every registered API
 * route appears in the document and vice versa. The spec cannot silently fall behind the
 * router without failing CI.
 */
final class DocsController extends Controller
{
    /**
     * Pinned rather than floating: a new major of Swagger UI arriving unannounced is how a
     * documentation page breaks on a Friday.
     *
     * This has to be a version cdnjs actually carries, which trails the project's own
     * releases. Bumping it means checking the asset URLs resolve, not just the tag.
     */
    private const string SWAGGER_UI_VERSION = '5.29.1';

    public function spec(): Response
    {
        return new Response($this->read(), 200, [
            'Content-Type' => 'application/yaml; charset=utf-8',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    public function ui(): Response
    {
        $base = sprintf(
            'https://cdnjs.cloudflare.com/ajax/libs/swagger-ui/%s',
            self::SWAGGER_UI_VERSION,
        );

        $html = <<<HTML
            <!doctype html>
            <html lang="en">
            <head>
                <meta charset="utf-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <title>Shortwave API reference</title>
                <link rel="stylesheet" href="{$base}/swagger-ui.min.css">
                <style>
                    body { margin: 0; background: #fafafa; }
                    .topbar { display: none; }
                    .swagger-ui .info { margin: 2rem 0; }
                </style>
            </head>
            <body>
                <div id="swagger"></div>
                <script src="{$base}/swagger-ui-bundle.min.js"></script>
                <script src="{$base}/swagger-ui-standalone-preset.min.js"></script>
                <script>
                    window.ui = SwaggerUIBundle({
                        url: '/docs/openapi.yaml',
                        dom_id: '#swagger',
                        deepLinking: true,
                        displayRequestDuration: true,
                        // Alphabetical ordering would scatter the auth flow across the
                        // page; the document's own order walks a reader through it.
                        tryItOutEnabled: true,
                        persistAuthorization: true,
                        defaultModelsExpandDepth: 1,
                        presets: [SwaggerUIBundle.presets.apis, SwaggerUIStandalonePreset],
                        layout: 'BaseLayout',
                    });
                </script>
            </body>
            </html>
            HTML;

        return new Response($html, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            // The page loads scripts and styles from a CDN and fetches only its own spec.
            'Content-Security-Policy' => implode('; ', [
                "default-src 'none'",
                "script-src 'unsafe-inline' https://cdnjs.cloudflare.com",
                "style-src 'unsafe-inline' https://cdnjs.cloudflare.com",
                'font-src https://cdnjs.cloudflare.com',
                "img-src 'self' data:",
                "connect-src 'self'",
            ]),
        ]);
    }

    private function read(): string
    {
        $path = base_path('docs/openapi.yaml');
        $contents = is_readable($path) ? file_get_contents($path) : false;

        if ($contents === false) {
            // The spec ships with the application, so a missing file means a broken build
            // rather than a bad request.
            throw new NotFoundHttpException('The API specification is not available.');
        }

        return $contents;
    }
}

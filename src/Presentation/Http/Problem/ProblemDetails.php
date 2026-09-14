<?php

declare(strict_types=1);

namespace Shortwave\Presentation\Http\Problem;

use Illuminate\Http\JsonResponse;

/**
 * An RFC 9457 `application/problem+json` document.
 *
 * Every error this API returns has this shape, so a client can write one error
 * handler instead of branching on which endpoint failed. `type` is the stable,
 * machine-readable part and comes from the domain's own error codes; `detail` is
 * prose and may be reworded at any time.
 */
final readonly class ProblemDetails
{
    public const string CONTENT_TYPE = 'application/problem+json';

    private const string TYPE_BASE = 'https://shortwave.dev/problems/';

    /**
     * @param  array<string, mixed>  $extensions
     */
    private function __construct(
        public string $type,
        public string $title,
        public int $status,
        public string $detail,
        public array $extensions = [],
    ) {}

    /**
     * @param  array<string, mixed>  $extensions
     */
    public static function make(
        string $type,
        string $title,
        int $status,
        string $detail,
        array $extensions = [],
    ): self {
        return new self($type, $title, $status, $detail, $extensions);
    }

    public function withInstance(string $requestId): self
    {
        return new self(
            $this->type,
            $this->title,
            $this->status,
            $this->detail,
            [...$this->extensions, 'request_id' => $requestId],
        );
    }

    public function toResponse(): JsonResponse
    {
        return new JsonResponse(
            $this->toArray(),
            $this->status,
            ['Content-Type' => self::CONTENT_TYPE],
            // Slashes in a `type` URI read badly when escaped, and this response is
            // as often read by a person debugging as by a client.
            JsonResponse::DEFAULT_ENCODING_OPTIONS | JSON_UNESCAPED_SLASHES,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => str_starts_with($this->type, 'https://')
                ? $this->type
                : self::TYPE_BASE.$this->type,
            'title' => $this->title,
            'status' => $this->status,
            'detail' => $this->detail,
            ...$this->extensions,
        ];
    }
}

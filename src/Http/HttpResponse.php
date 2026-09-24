<?php

declare(strict_types=1);

namespace Nowo\Redsys\Http;

final readonly class HttpResponse
{
    public function __construct(
        public int $statusCode,
        public string $body,
        /** @var array<string, list<string>> */
        public array $headers = [],
    ) {
    }
}

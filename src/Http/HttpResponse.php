<?php

declare(strict_types=1);

namespace Nowo\Redsys\Http;

final class HttpResponse
{
    public function __construct(
        public readonly int $statusCode,
        public readonly string $body,
        /** @var array<string, list<string>> */
        public readonly array $headers = [],
    ) {
    }
}

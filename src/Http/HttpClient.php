<?php

declare(strict_types=1);

namespace Nowo\Redsys\Http;

/**
 * Minimal HTTP transport for REST SIS calls (injectable for tests).
 */
interface HttpClient
{
    /**
     * @param array<string, string> $headers
     */
    public function postJson(string $url, string $jsonBody, array $headers = []): HttpResponse;
}

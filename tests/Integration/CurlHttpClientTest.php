<?php

declare(strict_types=1);

namespace Nowo\Redsys\Tests\Integration;

use Nowo\Redsys\Http\CurlHttpClient;
use Nowo\Redsys\Http\HttpResponse;
use PHPUnit\Framework\TestCase;

/**
 * Hits a public HTTPS echo endpoint to exercise CurlHttpClient (timeouts + headers).
 */
final class CurlHttpClientTest extends TestCase
{
    public function testPostJsonAgainstHttpsEcho(): void
    {
        $client = new CurlHttpClient(5, 20);
        $response = $client->postJson(
            'https://httpbin.org/post',
            '{"hello":"redsys"}',
            ['X-Test' => 'nowo']
        );

        self::assertInstanceOf(HttpResponse::class, $response);
        self::assertSame(200, $response->statusCode);
        self::assertStringContainsString('hello', $response->body);
        self::assertNotEmpty($response->headers);
    }
}

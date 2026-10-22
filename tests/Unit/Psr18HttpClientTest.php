<?php

declare(strict_types=1);

namespace Nowo\Redsys\Tests\Unit;

use Nowo\Redsys\Exception\RedsysException;
use Nowo\Redsys\Http\Psr18HttpClient;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

final class Psr18HttpClientTest extends TestCase
{
    public function testPostsJsonAndMapsTheResponse(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->expects(self::exactly(3))->method('withHeader')->willReturnSelf();
        $request->expects(self::once())->method('withBody')->willReturnSelf();
        $requests = $this->createMock(RequestFactoryInterface::class);
        $requests->expects(self::once())->method('createRequest')->with('POST', 'https://sis-t.redsys.es/rest')->willReturn($request);
        $streams = $this->createMock(StreamFactoryInterface::class);
        $streams->expects(self::once())->method('createStream')->with('{"a":1}')->willReturn($this->createStub(StreamInterface::class));

        $body = $this->createStub(StreamInterface::class);
        $body->method('__toString')->willReturn('{"ok":true}');
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn($body);
        $response->method('getHeaders')->willReturn(['Content-Type' => [1 => 'application/json']]);
        $client = $this->createMock(ClientInterface::class);
        $client->expects(self::once())->method('sendRequest')->with($request)->willReturn($response);

        $result = (new Psr18HttpClient($client, $requests, $streams))->postJson('https://sis-t.redsys.es/rest', '{"a":1}', ['X-Trace' => 't']);

        self::assertSame(200, $result->statusCode);
        self::assertSame('{"ok":true}', $result->body);
        self::assertSame(['Content-Type' => ['application/json']], $result->headers);
    }

    public function testClientFailuresBecomeRedsysExceptions(): void
    {
        $request = $this->createStub(RequestInterface::class);
        $request->method('withHeader')->willReturnSelf();
        $request->method('withBody')->willReturnSelf();
        $requests = $this->createStub(RequestFactoryInterface::class);
        $requests->method('createRequest')->willReturn($request);
        $streams = $this->createStub(StreamFactoryInterface::class);
        $streams->method('createStream')->willReturn($this->createStub(StreamInterface::class));
        $client = $this->createStub(ClientInterface::class);
        $client->method('sendRequest')->willThrowException(new class('timeout') extends \RuntimeException implements ClientExceptionInterface {});

        $this->expectException(RedsysException::class);
        $this->expectExceptionMessage('Redsys HTTP request failed: timeout');

        (new Psr18HttpClient($client, $requests, $streams))->postJson('https://sis-t.redsys.es/rest', '{}');
    }
}

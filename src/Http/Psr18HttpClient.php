<?php

declare(strict_types=1);

namespace Nowo\Redsys\Http;

use Nowo\Redsys\Exception\RedsysException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * {@see HttpClient} adapter over any PSR-18 client (REQ-CS-007): lets hosts reuse their own client (Symfony
 * `Psr18Client`, Guzzle, …) instead of the built-in cURL one. Timeouts belong to the wrapped client: configure
 * connect / total deadlines there (REQ-RUNTIME-001). Requires `psr/http-client` and `psr/http-factory`.
 */
final readonly class Psr18HttpClient implements HttpClient
{
    public function __construct(
        private ClientInterface $client,
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
    ) {
    }

    /**
     * Sends the JSON body as a POST through the PSR-18 client; transport failures become {@see RedsysException}.
     *
     * @param array<string, string> $headers
     */
    public function postJson(string $url, string $jsonBody, array $headers = []): HttpResponse
    {
        $request = $this->requestFactory->createRequest('POST', $url)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'application/json')
            ->withBody($this->streamFactory->createStream($jsonBody));
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        try {
            $response = $this->client->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new RedsysException('Redsys HTTP request failed: '.$e->getMessage(), 0, $e);
        }

        return new HttpResponse(
            $response->getStatusCode(),
            (string) $response->getBody(),
            array_map(\array_values(...), $response->getHeaders()),
        );
    }
}

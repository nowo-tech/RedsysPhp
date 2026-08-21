<?php

declare(strict_types=1);

namespace Nowo\Redsys\Http;

use Nowo\Redsys\Exception\RedsysException;

/**
 * cURL JSON POST with connect/request timeouts (REQ-RUNTIME-001).
 */
final class CurlHttpClient implements HttpClient
{
    public function __construct(
        private readonly int $connectTimeoutSeconds = 5,
        private readonly int $timeoutSeconds = 30,
    ) {
    }

    public function postJson(string $url, string $jsonBody, array $headers = []): HttpResponse
    {
        $ch = curl_init($url);
        // @codeCoverageIgnoreStart
        if (false === $ch) {
            throw new RedsysException('Unable to initialize cURL.');
        }
        // @codeCoverageIgnoreEnd

        $headerLines = ['Content-Type: application/json', 'Accept: application/json'];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name.': '.$value;
        }

        curl_setopt_array($ch, [
            \CURLOPT_POST => true,
            \CURLOPT_POSTFIELDS => $jsonBody,
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_HEADER => true,
            \CURLOPT_HTTPHEADER => $headerLines,
            \CURLOPT_CONNECTTIMEOUT => $this->connectTimeoutSeconds,
            \CURLOPT_TIMEOUT => $this->timeoutSeconds,
        ]);

        $raw = curl_exec($ch);
        // @codeCoverageIgnoreStart
        if (!\is_string($raw)) {
            throw new RedsysException('Redsys HTTP request failed: '.curl_error($ch));
        }
        // @codeCoverageIgnoreEnd

        $status = (int) curl_getinfo($ch, \CURLINFO_RESPONSE_CODE);
        $headerSize = (int) curl_getinfo($ch, \CURLINFO_HEADER_SIZE);

        $headerBlob = substr($raw, 0, $headerSize);
        $body = substr($raw, $headerSize);

        return new HttpResponse($status, $body, self::parseHeaders($headerBlob));
    }

    /** @return array<string, list<string>> */
    private static function parseHeaders(string $headerBlob): array
    {
        $headers = [];
        foreach (explode("\r\n", $headerBlob) as $line) {
            if (!str_contains($line, ':')) {
                continue;
            }
            [$name, $value] = explode(':', $line, 2);
            $name = strtolower(trim($name));
            $headers[$name][] = trim($value);
        }

        return $headers;
    }
}

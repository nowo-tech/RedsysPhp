<?php

declare(strict_types=1);

namespace Nowo\Redsys\Http;

use Nowo\Redsys\Exception\RedsysException;
use Nowo\Redsys\Exception\TransportException;

/**
 * cURL JSON POST with connect/request timeouts (REQ-RUNTIME-001) and explicit transport hardening (since 1.2.0, not
 * left to libcurl / php.ini defaults): TLS peer and host verification on, HTTPS only, no redirects, no CR/LF in header
 * names or values. Failures of the call throw {@see TransportException}.
 */
final readonly class CurlHttpClient implements HttpClient
{
    public function __construct(
        private int $connectTimeoutSeconds = 5,
        private int $timeoutSeconds = 30,
    ) {
    }

    public function postJson(string $url, string $jsonBody, array $headers = []): HttpResponse
    {
        $headerLines = ['Content-Type: application/json', 'Accept: application/json'];
        foreach ($headers as $name => $value) {
            if (1 === preg_match('/[\r\n\0]/', $name.$value)) {
                throw new RedsysException('HTTP header names and values must not contain CR, LF or NUL.');
            }
            $headerLines[] = $name.': '.$value;
        }

        $ch = curl_init($url);
        // @codeCoverageIgnoreStart
        if (false === $ch) {
            throw new TransportException('Unable to initialize cURL.');
        }
        // @codeCoverageIgnoreEnd

        curl_setopt_array($ch, [
            \CURLOPT_POST => true,
            \CURLOPT_POSTFIELDS => $jsonBody,
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_HEADER => true,
            \CURLOPT_HTTPHEADER => $headerLines,
            \CURLOPT_CONNECTTIMEOUT => $this->connectTimeoutSeconds,
            \CURLOPT_TIMEOUT => $this->timeoutSeconds,
        ] + self::securityOptions());

        $raw = curl_exec($ch);
        if (!\is_string($raw)) {
            throw new TransportException('Redsys HTTP request failed: '.curl_error($ch));
        }

        $status = curl_getinfo($ch, \CURLINFO_RESPONSE_CODE);
        $headerSize = curl_getinfo($ch, \CURLINFO_HEADER_SIZE);

        $headerBlob = substr($raw, 0, $headerSize);
        $body = substr($raw, $headerSize);

        return new HttpResponse($status, $body, $this->parseHeaders($headerBlob));
    }

    /**
     * The transport options set on every request: TLS verification, HTTPS only (also for redirects), no redirects.
     *
     * @return array<int, int|bool>
     */
    public static function securityOptions(): array
    {
        return [
            \CURLOPT_SSL_VERIFYPEER => true,
            \CURLOPT_SSL_VERIFYHOST => 2,
            \CURLOPT_FOLLOWLOCATION => false,
            \CURLOPT_MAXREDIRS => 0,
            \CURLOPT_PROTOCOLS => \CURLPROTO_HTTPS,
            \CURLOPT_REDIR_PROTOCOLS => \CURLPROTO_HTTPS,
        ];
    }

    /** @return array<string, list<string>> */
    private function parseHeaders(string $headerBlob): array
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

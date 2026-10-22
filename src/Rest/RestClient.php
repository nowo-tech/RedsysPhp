<?php

declare(strict_types=1);

namespace Nowo\Redsys\Rest;

use Nowo\Redsys\Http\HttpClient;
use Nowo\Redsys\Merchant;
use Nowo\Redsys\MerchantParameters;
use Nowo\Redsys\SignedPayload;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * REST SIS client: iniciaPeticionREST / trataPeticionREST.
 */
final readonly class RestClient
{
    public function __construct(
        private Merchant $merchant,
        private HttpClient $http,
        private LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function init(MerchantParameters $parameters): RestResponse
    {
        return $this->send('init', $this->merchant->environment()->restInitUrl(), $parameters);
    }

    public function treat(MerchantParameters $parameters): RestResponse
    {
        return $this->send('treat', $this->merchant->environment()->restTreatUrl(), $parameters);
    }

    /**
     * Logs the outcome without merchant data, card data, signatures or bodies (REQ-OBS-001).
     */
    private function send(string $operation, string $url, MerchantParameters $parameters): RestResponse
    {
        $context = ['library' => 'redsys-php', 'action' => 'rest_'.$operation];
        $payload = SignedPayload::from($this->merchant, $parameters);
        try {
            $httpResponse = $this->http->postJson($url, $payload->toJson());
        } catch (\Throwable $e) {
            $this->logger->error('Redsys REST request failed.', $context + ['exception' => $e::class]);

            throw $e;
        }
        $response = RestResponse::fromHttp($httpResponse, $this->merchant);
        $this->logger->info('Redsys REST request completed.', $context + [
            'http_status' => $response->httpStatus,
            'signature_valid' => $response->signatureValid,
            'error_code' => $response->errorCode,
        ]);

        return $response;
    }
}

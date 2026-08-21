<?php

declare(strict_types=1);

namespace Nowo\Redsys\Rest;

use Nowo\Redsys\Http\HttpClient;
use Nowo\Redsys\Merchant;
use Nowo\Redsys\MerchantParameters;
use Nowo\Redsys\SignedPayload;

/**
 * REST SIS client: iniciaPeticionREST / trataPeticionREST.
 */
final class RestClient
{
    public function __construct(
        private readonly Merchant $merchant,
        private readonly HttpClient $http,
    ) {
    }

    public function init(MerchantParameters $parameters): RestResponse
    {
        return $this->send($this->merchant->environment()->restInitUrl(), $parameters);
    }

    public function treat(MerchantParameters $parameters): RestResponse
    {
        return $this->send($this->merchant->environment()->restTreatUrl(), $parameters);
    }

    private function send(string $url, MerchantParameters $parameters): RestResponse
    {
        $payload = SignedPayload::from($this->merchant, $parameters);
        $httpResponse = $this->http->postJson($url, $payload->toJson());

        return RestResponse::fromHttp($httpResponse, $this->merchant);
    }
}

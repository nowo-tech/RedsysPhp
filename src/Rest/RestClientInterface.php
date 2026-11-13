<?php

declare(strict_types=1);

namespace Nowo\Redsys\Rest;

use Nowo\Redsys\Exception\TransportException;
use Nowo\Redsys\MerchantParameters;

/**
 * REST SIS port: iniciaPeticionREST / trataPeticionREST (since 1.4.0).
 *
 * Type your adapters and use cases against this interface, not {@see RestClient}, so a test double or a decorator
 * (retry, metrics, circuit breaker) can replace the default client. Implementations sign the parameters with the
 * merchant key, send them and return the response; they verify the response signature before the caller reads
 * {@see RestResponse::verifiedParameters()}.
 */
interface RestClientInterface
{
    /**
     * Sends an iniciaPeticionREST request.
     *
     * @throws TransportException on network or HTTP transport failure
     */
    public function init(MerchantParameters $parameters): RestResponse;

    /**
     * Sends a trataPeticionREST request.
     *
     * @throws TransportException on network or HTTP transport failure
     */
    public function treat(MerchantParameters $parameters): RestResponse;
}

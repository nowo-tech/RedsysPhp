<?php

declare(strict_types=1);

namespace Nowo\Redsys\Rest;

use Nowo\Redsys\Exception\RedsysException;
use Nowo\Redsys\Http\HttpResponse;
use Nowo\Redsys\Merchant;
use Nowo\Redsys\MerchantParameters;
use Nowo\Redsys\Signature\Signer;
use Nowo\Redsys\SignatureVersion;

/**
 * Parsed REST response (optionally signature-checked when Redsys returns the signed trio).
 */
final class RestResponse
{
    /**
     * @param array<string, mixed>|null $decodedParameters
     */
    public function __construct(
        public readonly int $httpStatus,
        public readonly string $rawBody,
        public readonly ?array $decodedParameters,
        public readonly bool $signatureValid,
        public readonly ?string $errorCode,
    ) {
    }

    public static function fromHttp(HttpResponse $response, Merchant $merchant): self
    {
        $json = json_decode($response->body, true);
        if (!\is_array($json)) {
            return new self($response->statusCode, $response->body, null, false, null);
        }

        $errorCode = isset($json['errorCode']) && \is_scalar($json['errorCode'])
            ? (string) $json['errorCode']
            : null;

        $encoded = $json['Ds_MerchantParameters'] ?? null;
        $signature = $json['Ds_Signature'] ?? null;
        $versionRaw = $json['Ds_SignatureVersion'] ?? null;

        if (!\is_string($encoded) || !\is_string($signature) || !\is_string($versionRaw)) {
            return new self($response->statusCode, $response->body, $json, false, $errorCode);
        }

        try {
            $params = MerchantParameters::decode($encoded);
            $fields = $params->toArray();
            $order = $fields['Ds_Order'] ?? $fields['DS_ORDER'] ?? $fields['DS_MERCHANT_ORDER'] ?? null;
            if (!\is_string($order) || '' === $order) {
                throw new RedsysException('REST response missing order.');
            }

            $version = SignatureVersion::from($versionRaw);
            $valid = (new Signer($merchant->secretKey(), $version))
                ->verify($encoded, $order, $signature);

            return new self($response->statusCode, $response->body, $fields, $valid, $errorCode);
        } catch (\Throwable) {
            return new self(
                $response->statusCode,
                $response->body,
                ['Ds_MerchantParameters' => $encoded],
                false,
                $errorCode
            );
        }
    }
}

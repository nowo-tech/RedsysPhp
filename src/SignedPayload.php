<?php

declare(strict_types=1);

namespace Nowo\Redsys;

/**
 * Signed trio sent to Redsys (redirect form or REST body).
 */
final class SignedPayload
{
    public function __construct(
        public readonly string $merchantParameters,
        public readonly string $signature,
        public readonly SignatureVersion $signatureVersion,
    ) {
    }

    public static function from(Merchant $merchant, MerchantParameters $parameters): self
    {
        $encoded = $parameters->encode();
        $signature = $merchant->signer()->sign($encoded, $parameters->orderNumber());

        return new self($encoded, $signature, $merchant->signatureVersion());
    }

    /** @return array{Ds_MerchantParameters: string, Ds_Signature: string, Ds_SignatureVersion: string} */
    public function toArray(): array
    {
        return [
            'Ds_MerchantParameters' => $this->merchantParameters,
            'Ds_Signature' => $this->signature,
            'Ds_SignatureVersion' => $this->signatureVersion->value,
        ];
    }

    public function toJson(): string
    {
        $json = json_encode($this->toArray(), \JSON_UNESCAPED_SLASHES);
        // @codeCoverageIgnoreStart
        if (false === $json) {
            throw new Exception\RedsysException('Unable to encode signed payload.');
        }
        // @codeCoverageIgnoreEnd

        return $json;
    }
}

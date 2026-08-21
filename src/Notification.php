<?php

declare(strict_types=1);

namespace Nowo\Redsys;

use Nowo\Redsys\Exception\RedsysException;

/**
 * Online notification / browser return payload verifier.
 */
final class Notification
{
    public function __construct(
        private readonly string $encodedParameters,
        private readonly string $signature,
        private readonly SignatureVersion $signatureVersion,
        private readonly MerchantParameters $decoded,
    ) {
    }

    /**
     * @param array<string, mixed> $input typically $_POST / $_GET from Redsys
     */
    public static function fromRequest(array $input, Merchant $merchant): self
    {
        $encoded = $input['Ds_MerchantParameters'] ?? null;
        $signature = $input['Ds_Signature'] ?? null;
        $versionRaw = $input['Ds_SignatureVersion'] ?? $merchant->signatureVersion()->value;

        if (!\is_string($encoded) || '' === $encoded) {
            throw new RedsysException('Missing Ds_MerchantParameters.');
        }
        if (!\is_string($signature) || '' === $signature) {
            throw new RedsysException('Missing Ds_Signature.');
        }
        if (!\is_string($versionRaw) || '' === $versionRaw) {
            throw new RedsysException('Missing Ds_SignatureVersion.');
        }

        $version = SignatureVersion::tryFrom($versionRaw);
        if (null === $version) {
            throw new RedsysException(\sprintf('Unsupported Ds_SignatureVersion "%s".', $versionRaw));
        }

        $decoded = MerchantParameters::decode($encoded);
        $order = self::extractOrder($decoded);

        $signer = new Signature\Signer($merchant->secretKey(), $version);
        if (!$signer->verify($encoded, $order, $signature)) {
            throw new RedsysException('Invalid Redsys notification signature.');
        }

        return new self($encoded, $signature, $version, $decoded);
    }

    public function parameters(): MerchantParameters
    {
        return $this->decoded;
    }

    public function encodedParameters(): string
    {
        return $this->encodedParameters;
    }

    public function signature(): string
    {
        return $this->signature;
    }

    public function signatureVersion(): SignatureVersion
    {
        return $this->signatureVersion;
    }

    /**
     * DS_RESPONSE / Ds_Response — authorized when numeric value is in 0..99.
     */
    public function responseCode(): ?string
    {
        $fields = $this->decoded->toArray();
        $value = $fields['Ds_Response'] ?? $fields['DS_RESPONSE'] ?? null;

        return \is_string($value) || \is_int($value) ? (string) $value : null;
    }

    public function isAuthorized(): bool
    {
        $code = $this->responseCode();
        if (null === $code || !ctype_digit($code)) {
            return false;
        }

        $numeric = (int) $code;

        return $numeric >= 0 && $numeric <= 99;
    }

    private static function extractOrder(MerchantParameters $parameters): string
    {
        $fields = $parameters->toArray();
        $order = $fields['Ds_Order'] ?? $fields['DS_ORDER'] ?? $fields['DS_MERCHANT_ORDER'] ?? null;
        if (!\is_string($order) || '' === $order) {
            throw new RedsysException('Notification payload is missing Ds_Order.');
        }

        return $order;
    }
}

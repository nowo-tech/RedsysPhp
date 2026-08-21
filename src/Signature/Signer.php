<?php

declare(strict_types=1);

namespace Nowo\Redsys\Signature;

use Nowo\Redsys\Encoding\Base64Url;
use Nowo\Redsys\Exception\RedsysException;
use Nowo\Redsys\SignatureVersion;

/**
 * Clean-room signer for Redsys TPV payloads.
 *
 * Algorithms documented at:
 * - https://pagosonline.redsys.es/…/firmar-una-operacion/ (HMAC_SHA512_V2)
 * - Redsys “Guía de migración a firma HMAC SHA256” (HMAC_SHA256_V1)
 */
final class Signer
{
    public function __construct(
        private readonly string $merchantKey,
        private readonly SignatureVersion $version = SignatureVersion::HmacSha512V2,
    ) {
        if ('' === $this->merchantKey) {
            throw new RedsysException('Merchant signature key must not be empty.');
        }
    }

    public function version(): SignatureVersion
    {
        return $this->version;
    }

    /**
     * Sign encoded merchant parameters using the order as diversifier.
     */
    public function sign(string $encodedMerchantParameters, string $order): string
    {
        $hmacKey = $this->diversify($order);

        return match ($this->version) {
            SignatureVersion::HmacSha256V1 => Base64Url::encodeStandard(
                hash_hmac('sha256', $encodedMerchantParameters, $hmacKey, true)
            ),
            SignatureVersion::HmacSha512V1,
            SignatureVersion::HmacSha512V2 => Base64Url::encode(
                hash_hmac('sha512', $encodedMerchantParameters, $hmacKey, true)
            ),
        };
    }

    public function verify(string $encodedMerchantParameters, string $order, string $receivedSignature): bool
    {
        $expected = $this->sign($encodedMerchantParameters, $order);

        return hash_equals(
            $this->normalizeSignature($expected),
            $this->normalizeSignature($receivedSignature)
        );
    }

    /**
     * Operation-specific key (public algorithm).
     *
     * V2/V1 SHA-512: AES-128-CBC(order, first-16-chars key, IV=0) → Base64 string used as HMAC key.
     * V1 SHA-256: Base64-decode merchant key → 3DES-EDE-CBC(order padded, IV=0) → raw HMAC key.
     */
    public function diversify(string $order): string
    {
        if ('' === $order) {
            throw new RedsysException('Order number is required to diversify the signature key.');
        }

        return match ($this->version) {
            SignatureVersion::HmacSha256V1 => $this->diversify3Des($order),
            SignatureVersion::HmacSha512V1,
            SignatureVersion::HmacSha512V2 => $this->diversifyAes($order),
        };
    }

    private function diversifyAes(string $order): string
    {
        $aesKey = str_pad(substr($this->merchantKey, 0, 16), 16, '0');
        $encrypted = openssl_encrypt(
            $order,
            'aes-128-cbc',
            $aesKey,
            \OPENSSL_RAW_DATA,
            str_repeat("\0", 16)
        );
        // @codeCoverageIgnoreStart
        if (false === $encrypted) {
            throw new RedsysException('AES key diversification failed.');
        }
        // @codeCoverageIgnoreEnd

        // Public docs: HMAC uses the Base64 encoding of the AES ciphertext as the key material.
        return Base64Url::encodeStandard($encrypted);
    }

    private function diversify3Des(string $order): string
    {
        $key = Base64Url::decodeStandard($this->merchantKey);
        $length = (int) (ceil(\strlen($order) / 8.0) * 8);
        $padded = $order.str_repeat("\0", $length - \strlen($order));

        $encrypted = openssl_encrypt(
            $padded,
            'des-ede3-cbc',
            $key,
            \OPENSSL_RAW_DATA,
            str_repeat("\0", 8)
        );
        // @codeCoverageIgnoreStart
        if (false === $encrypted) {
            throw new RedsysException('3DES key diversification failed.');
        }
        // @codeCoverageIgnoreEnd

        return substr($encrypted, 0, $length);
    }

    private function normalizeSignature(string $signature): string
    {
        return rtrim(strtr($signature, '-_', '+/'), '=');
    }
}

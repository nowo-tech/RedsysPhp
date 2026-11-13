<?php

declare(strict_types=1);

namespace Nowo\Redsys\Signature;

use Nowo\Redsys\Notification;
use Nowo\Redsys\Rest\RestResponse;

/**
 * Library-side signature check (since 1.4.0): recomputes the expected signature with
 * {@see SignerInterface::sign()} and compares it in constant time, so a custom signer whose
 * {@see SignerInterface::verify()} is wrong (always true, loose comparison, no Base64 normalisation)
 * cannot make the library accept a forged notification or REST response.
 *
 * Standard and URL-safe Base64, with or without padding, are accepted on both sides.
 *
 * @internal used by {@see Notification} and {@see RestResponse}
 */
final class SignatureCheck
{
    public static function matches(
        SignerInterface $signer,
        string $encodedMerchantParameters,
        string $order,
        string $receivedSignature,
    ): bool {
        $expected = self::normalize($signer->sign($encodedMerchantParameters, $order));
        $received = self::normalize($receivedSignature);

        if ('' === $expected || '' === $received) {
            return false;
        }

        return hash_equals($expected, $received);
    }

    private static function normalize(string $signature): string
    {
        return rtrim(strtr($signature, '-_', '+/'), '=');
    }
}

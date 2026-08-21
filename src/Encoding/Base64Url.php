<?php

declare(strict_types=1);

namespace Nowo\Redsys\Encoding;

use Nowo\Redsys\Exception\RedsysException;

/**
 * Base64 / Base64URL helpers as used by Redsys TPV payloads and signatures.
 *
 * Spec sources (public): Redsys developer docs — “Firmar una operación”,
 * HMAC SHA-256 migration guides.
 */
final class Base64Url
{
    public static function encode(string $binary): string
    {
        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }

    public static function decode(string $data): string
    {
        $remainder = \strlen($data) % 4;
        if ($remainder > 0) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($data, '-_', '+/'), true);
        if (false === $decoded) {
            throw new RedsysException('Invalid Base64URL payload.');
        }

        return $decoded;
    }

    public static function encodeStandard(string $binary): string
    {
        return base64_encode($binary);
    }

    public static function decodeStandard(string $data): string
    {
        $decoded = base64_decode($data, true);
        if (false === $decoded) {
            throw new RedsysException('Invalid Base64 payload.');
        }

        return $decoded;
    }
}

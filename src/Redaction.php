<?php

declare(strict_types=1);

namespace Nowo\Redsys;

/**
 * Debug views (var_dump / print_r) without the card reference (token) or the COF transaction id in clear.
 *
 * @internal
 */
final class Redaction
{
    public const string MASK = '[redacted]';

    /** Matched case-insensitively: request (DS_MERCHANT_*) and response (Ds_Merchant_*) casing. */
    private const array SENSITIVE_KEYS = ['DS_MERCHANT_IDENTIFIER', 'DS_MERCHANT_COF_TXNID'];

    /**
     * The fields with the token and the COF transaction id masked (the "REQUIRED" request marker is kept), and a nested
     * Base64 Ds_MerchantParameters reduced to its length (it carries the same values encoded).
     *
     * @param array<array-key, mixed> $fields
     *
     * @return array<array-key, mixed>
     */
    public static function fields(array $fields): array
    {
        foreach ($fields as $key => $value) {
            $upper = strtoupper((string) $key);
            if (\in_array($upper, self::SENSITIVE_KEYS, true) && MerchantParameters::IDENTIFIER_REQUIRED !== $value) {
                $fields[$key] = self::MASK;
            } elseif ('DS_MERCHANTPARAMETERS' === $upper && \is_string($value)) {
                $fields[$key] = self::opaque($value);
            }
        }

        return $fields;
    }

    /**
     * An opaque payload (Base64 parameters, raw body) reduced to its length.
     */
    public static function opaque(string $value): string
    {
        return \sprintf('[redacted: %d bytes]', \strlen($value));
    }
}

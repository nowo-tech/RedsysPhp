<?php

declare(strict_types=1);

namespace Nowo\Redsys;

/**
 * Values for Ds_SignatureVersion (public Redsys protocol constants).
 */
enum SignatureVersion: string
{
    /** 3DES diversify + HMAC-SHA256 + Base64 signature. */
    case HmacSha256V1 = 'HMAC_SHA256_V1';

    /** AES diversify + HMAC-SHA512 + Base64URL signature (legacy SHA-512 path). */
    case HmacSha512V1 = 'HMAC_SHA512_V1';

    /** AES diversify + HMAC-SHA512 + Base64URL signature (current default). */
    case HmacSha512V2 = 'HMAC_SHA512_V2';
}

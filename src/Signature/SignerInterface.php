<?php

declare(strict_types=1);

namespace Nowo\Redsys\Signature;

use Nowo\Redsys\Notification;
use Nowo\Redsys\Rest\RestResponse;
use Nowo\Redsys\SignatureVersion;

/**
 * Redsys signature port (since 1.4.0): signs encoded merchant parameters and verifies a received signature.
 *
 * {@see Signer} is the default, clean-room implementation of the public Redsys algorithms. Type your own code against
 * this interface when you need to replace it (for example a signer whose key lives in an HSM or a KMS).
 *
 * The library relies on {@see sign()} only: it verifies received signatures by itself (since 1.4.0, audit OI-R1).
 */
interface SignerInterface
{
    public function version(): SignatureVersion;

    /**
     * Signs the Base64-encoded merchant parameters, using the order number as the key diversifier.
     */
    public function sign(string $encodedMerchantParameters, string $order): string;

    /**
     * Whether $receivedSignature is the signature of the encoded parameters for that order (constant-time compare).
     *
     * For hosts that check a signature themselves. The library does not call it: {@see Notification}
     * and {@see RestResponse} recompute the signature with {@see sign()} and compare it with
     * hash_equals() (standard or URL-safe Base64), so a wrong verify() cannot make them accept a forged payload.
     */
    public function verify(string $encodedMerchantParameters, string $order, string $receivedSignature): bool;
}

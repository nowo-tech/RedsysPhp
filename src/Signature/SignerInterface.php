<?php

declare(strict_types=1);

namespace Nowo\Redsys\Signature;

use Nowo\Redsys\SignatureVersion;

/**
 * Redsys signature port (since 1.4.0): signs encoded merchant parameters and verifies a received signature.
 *
 * {@see Signer} is the default, clean-room implementation of the public Redsys algorithms. Type your own code against
 * this interface when you need to replace it (for example a signer whose key lives in an HSM or a KMS).
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
     */
    public function verify(string $encodedMerchantParameters, string $order, string $receivedSignature): bool;
}

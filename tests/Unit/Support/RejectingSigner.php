<?php

declare(strict_types=1);

namespace Nowo\Redsys\Tests\Unit\Support;

use Nowo\Redsys\Signature\SignerInterface;
use Nowo\Redsys\SignatureVersion;

/**
 * Rejects every signature (stands in for a signer whose key does not match).
 */
final class RejectingSigner implements SignerInterface
{
    public function version(): SignatureVersion
    {
        return SignatureVersion::HmacSha512V2;
    }

    public function sign(string $encodedMerchantParameters, string $order): string
    {
        return 'rejecting';
    }

    public function verify(string $encodedMerchantParameters, string $order, string $receivedSignature): bool
    {
        return false;
    }
}

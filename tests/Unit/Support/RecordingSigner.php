<?php

declare(strict_types=1);

namespace Nowo\Redsys\Tests\Unit\Support;

use Nowo\Redsys\Signature\SignerInterface;
use Nowo\Redsys\SignatureVersion;

/**
 * Delegates to the built-in signer and records the calls (stands in for an HSM / KMS signer).
 */
final class RecordingSigner implements SignerInterface
{
    /** @var list<string> */
    public array $calls = [];

    public function __construct(private readonly SignerInterface $inner)
    {
    }

    public function version(): SignatureVersion
    {
        return $this->inner->version();
    }

    public function sign(string $encodedMerchantParameters, string $order): string
    {
        $this->calls[] = 'sign';

        return $this->inner->sign($encodedMerchantParameters, $order);
    }

    public function verify(string $encodedMerchantParameters, string $order, string $receivedSignature): bool
    {
        $this->calls[] = 'verify';

        return $this->inner->verify($encodedMerchantParameters, $order, $receivedSignature);
    }
}

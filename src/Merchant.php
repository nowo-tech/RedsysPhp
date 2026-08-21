<?php

declare(strict_types=1);

namespace Nowo\Redsys;

use Nowo\Redsys\Exception\RedsysException;
use Nowo\Redsys\Signature\Signer;

/**
 * Merchant terminal credentials (FUC / terminal / signature key / environment).
 */
final class Merchant
{
    public function __construct(
        private readonly string $merchantCode,
        private readonly string $terminal,
        private readonly string $secretKey,
        private readonly Environment $environment = Environment::Test,
        private readonly SignatureVersion $signatureVersion = SignatureVersion::HmacSha512V2,
    ) {
        if ('' === $this->merchantCode || '' === $this->terminal || '' === $this->secretKey) {
            throw new RedsysException('Merchant code, terminal and secret key are required.');
        }
    }

    public function merchantCode(): string
    {
        return $this->merchantCode;
    }

    public function terminal(): string
    {
        return $this->terminal;
    }

    public function secretKey(): string
    {
        return $this->secretKey;
    }

    public function environment(): Environment
    {
        return $this->environment;
    }

    public function signatureVersion(): SignatureVersion
    {
        return $this->signatureVersion;
    }

    public function signer(): Signer
    {
        return new Signer($this->secretKey, $this->signatureVersion);
    }
}

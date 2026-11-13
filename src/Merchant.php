<?php

declare(strict_types=1);

namespace Nowo\Redsys;

use Nowo\Redsys\Exception\RedsysException;
use Nowo\Redsys\Signature\Signer;
use Nowo\Redsys\Signature\SignerInterface;

/**
 * Merchant terminal credentials (FUC / terminal / signature key / environment).
 */
final readonly class Merchant
{
    public function __construct(
        private string $merchantCode,
        private string $terminal,
        #[\SensitiveParameter]
        private string $secretKey,
        private Environment $environment = Environment::Test,
        private SignatureVersion $signatureVersion = SignatureVersion::HmacSha512V2,
        private ?SignerInterface $customSigner = null,
    ) {
        if (\in_array('', [$this->merchantCode, $this->terminal, $this->secretKey], true)) {
            throw new RedsysException('Merchant code, terminal and secret key are required.');
        }
        if (null !== $this->customSigner && $this->customSigner->version() !== $this->signatureVersion) {
            throw new RedsysException('The custom signer must use the merchant signature version.');
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

    /**
     * The built-in {@see Signer} for this merchant's key and version. It ignores a custom signer passed to the
     * constructor: the library signs and verifies through {@see signatureSigner()}.
     */
    public function signer(): Signer
    {
        return new Signer($this->secretKey, $this->signatureVersion);
    }

    /**
     * The signer the library uses to sign payloads and verify notifications and REST responses (since 1.4.0): the
     * custom signer passed to the constructor, or the built-in {@see Signer}.
     */
    public function signatureSigner(): SignerInterface
    {
        return $this->customSigner ?? $this->signer();
    }
}

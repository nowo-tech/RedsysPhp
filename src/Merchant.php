<?php

declare(strict_types=1);

namespace Nowo\Redsys;

use Nowo\Redsys\Exception\RedsysException;
use Nowo\Redsys\Signature\Signer;
use Nowo\Redsys\Signature\SignerInterface;

/**
 * Merchant terminal credentials (FUC / terminal / signature key / environment).
 *
 * With a custom signer (HSM / KMS), the secret key is optional and {@see Signer()} is unavailable: the key never
 * leaves the custom signer, so no built-in signer is ever built on a placeholder key.
 */
final readonly class Merchant
{
    public function __construct(
        private string $merchantCode,
        private string $terminal,
        #[\SensitiveParameter]
        private string $secretKey = '',
        private Environment $environment = Environment::Test,
        private SignatureVersion $signatureVersion = SignatureVersion::HmacSha512V2,
        private ?SignerInterface $customSigner = null,
    ) {
        if (null !== $this->customSigner) {
            if ('' === $this->merchantCode || '' === $this->terminal) {
                throw new RedsysException('Merchant code and terminal are required.');
            }
        } elseif (\in_array('', [$this->merchantCode, $this->terminal, $this->secretKey], true)) {
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

    /**
     * The configured secret key: empty when a custom signer was passed without one.
     */
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
     * constructor: with one, verify through {@see signatureSigner()} (the library always does).
     *
     * @throws RedsysException when a custom signer was passed without a secret key: there is no key to build a
     *                         built-in signer on
     */
    public function signer(): Signer
    {
        if (null !== $this->customSigner && '' === $this->secretKey) {
            throw new RedsysException('Merchant::signer() needs a secret key; this merchant only has a custom signer: use Merchant::signatureSigner().');
        }

        return new Signer($this->secretKey, $this->signatureVersion);
    }

    /**
     * The signer the library uses to sign payloads and verify notifications and REST responses (since 1.4.0): the
     * custom signer passed to the constructor, or the built-in {@see Signer}.
     */
    public function signatureSigner(): SignerInterface
    {
        return $this->customSigner ?? new Signer($this->secretKey, $this->signatureVersion);
    }
}

<?php

declare(strict_types=1);

namespace Nowo\Redsys;

use Nowo\Redsys\Exception\InvalidResponseException;
use Nowo\Redsys\Exception\SignatureInvalidException;

/**
 * Online notification / browser return payload verifier.
 */
final readonly class Notification
{
    /**
     * Use {@see fromRequest()}: the constructor does not verify the signature, so an instance built with it carries
     * unverified fields and none of its getters can be trusted.
     */
    public function __construct(
        private string $encodedParameters,
        private string $signature,
        private SignatureVersion $signatureVersion,
        private MerchantParameters $decoded,
    ) {
    }

    /**
     * Verifies the signature with the merchant's configured algorithm.
     *
     * @param array<string, mixed> $input Redsys fields from the current request
     *                                    (Symfony: `$request->request->all()` / `$request->query->all()`;
     *                                    avoid `$_POST` / `$_GET` under FrankenPHP worker mode)
     *
     * @throws SignatureInvalidException the signature does not verify or Ds_SignatureVersion is not the merchant's
     * @throws InvalidResponseException  a field is missing, the version is unknown or the payload cannot be decoded
     */
    public static function fromRequest(array $input, Merchant $merchant): self
    {
        $encoded = $input['Ds_MerchantParameters'] ?? null;
        $signature = $input['Ds_Signature'] ?? null;
        $versionRaw = $input['Ds_SignatureVersion'] ?? $merchant->signatureVersion()->value;

        if (!\is_string($encoded) || '' === $encoded) {
            throw new InvalidResponseException('Missing Ds_MerchantParameters.');
        }
        if (!\is_string($signature) || '' === $signature) {
            throw new InvalidResponseException('Missing Ds_Signature.');
        }
        if (!\is_string($versionRaw) || '' === $versionRaw) {
            throw new InvalidResponseException('Missing Ds_SignatureVersion.');
        }

        $version = SignatureVersion::tryFrom($versionRaw);
        if (null === $version) {
            throw new InvalidResponseException(\sprintf('Unsupported Ds_SignatureVersion "%s".', $versionRaw));
        }

        // The algorithm is the merchant's configured one: Ds_SignatureVersion is attacker-controlled,
        // so honouring it would allow a downgrade to a weaker scheme.
        if ($version !== $merchant->signatureVersion()) {
            throw new SignatureInvalidException(\sprintf('Ds_SignatureVersion "%s" does not match the merchant signature version "%s".', $versionRaw, $merchant->signatureVersion()->value));
        }

        $decoded = MerchantParameters::decode($encoded);
        $order = self::extractOrder($decoded);

        if (!$merchant->signatureSigner()->verify($encoded, $order, $signature)) {
            throw new SignatureInvalidException('Invalid Redsys notification signature.');
        }

        return new self($encoded, $signature, $version, $decoded);
    }

    public function parameters(): MerchantParameters
    {
        return $this->decoded;
    }

    public function encodedParameters(): string
    {
        return $this->encodedParameters;
    }

    public function signature(): string
    {
        return $this->signature;
    }

    public function signatureVersion(): SignatureVersion
    {
        return $this->signatureVersion;
    }

    /**
     * DS_RESPONSE / Ds_Response as sent by Redsys (e.g. "0000", "0900", "0190").
     */
    public function responseCode(): ?string
    {
        return $this->outcome()->responseCode();
    }

    /**
     * Ds_TransactionType of the notified operation, or null when absent / not a known type.
     */
    public function transactionType(): ?TransactionType
    {
        return $this->outcome()->transactionType();
    }

    /**
     * Payment / pre-authorization success: Ds_Response in 0000..0099.
     *
     * Refunds, confirmations and cancellations report success with other codes
     * (0900, 0400): use {@see isSuccessfulForTransactionType()} for those.
     */
    public function isAuthorized(): bool
    {
        return $this->outcome()->isAuthorized();
    }

    /**
     * Success check that understands the response code of each transaction type:
     *
     * - Refund (3) and Confirmation (2): Ds_Response 0900;
     * - Cancellation (9): Ds_Response 0400;
     * - any other type: Ds_Response 0000..0099 (same as {@see isAuthorized()}).
     *
     * @param TransactionType|null $type Expected type; null uses the notification's Ds_TransactionType
     */
    public function isSuccessfulForTransactionType(?TransactionType $type = null): bool
    {
        return $this->outcome()->isSuccessfulForTransactionType($type);
    }

    /**
     * Ds_Merchant_Identifier from the verified payload: the card reference (token) Redsys stored after a request with
     * DS_MERCHANT_IDENTIFIER=REQUIRED; null when absent. Store it like a credential and never log it.
     */
    public function merchantIdentifier(): ?string
    {
        return $this->outcome()->merchantIdentifier();
    }

    /**
     * Ds_Merchant_Cof_Txnid from the verified payload: the id of the first COF operation, to send as
     * DS_MERCHANT_COF_TXNID on later merchant-initiated charges; null when absent. Never log it.
     */
    public function cofTxnId(): ?string
    {
        return $this->outcome()->cofTxnId();
    }

    /**
     * var_dump() / print_r() view: the Base64 payload is reduced to its length, and the token and COF transaction id
     * are redacted in the decoded parameters.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'encodedParameters' => Redaction::opaque($this->encodedParameters),
            'signature' => $this->signature,
            'signatureVersion' => $this->signatureVersion,
            'decoded' => $this->decoded,
        ];
    }

    private function outcome(): ResponseOutcome
    {
        return new ResponseOutcome($this->decoded->toArray());
    }

    private static function extractOrder(MerchantParameters $parameters): string
    {
        $fields = $parameters->toArray();
        $order = $fields['Ds_Order'] ?? $fields['DS_ORDER'] ?? $fields['DS_MERCHANT_ORDER'] ?? null;
        if (!\is_string($order) || '' === $order) {
            throw new InvalidResponseException('Notification payload is missing Ds_Order.');
        }

        return $order;
    }
}

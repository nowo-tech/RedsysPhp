<?php

declare(strict_types=1);

namespace Nowo\Redsys;

use Nowo\Redsys\Exception\RedsysException;

/**
 * Online notification / browser return payload verifier.
 */
final readonly class Notification
{
    public function __construct(
        private string $encodedParameters,
        private string $signature,
        private SignatureVersion $signatureVersion,
        private MerchantParameters $decoded,
    ) {
    }

    /**
     * @param array<string, mixed> $input Redsys fields from the current request
     *                                    (Symfony: `$request->request->all()` / `$request->query->all()`;
     *                                    avoid `$_POST` / `$_GET` under FrankenPHP worker mode)
     */
    public static function fromRequest(array $input, Merchant $merchant): self
    {
        $encoded = $input['Ds_MerchantParameters'] ?? null;
        $signature = $input['Ds_Signature'] ?? null;
        $versionRaw = $input['Ds_SignatureVersion'] ?? $merchant->signatureVersion()->value;

        if (!\is_string($encoded) || '' === $encoded) {
            throw new RedsysException('Missing Ds_MerchantParameters.');
        }
        if (!\is_string($signature) || '' === $signature) {
            throw new RedsysException('Missing Ds_Signature.');
        }
        if (!\is_string($versionRaw) || '' === $versionRaw) {
            throw new RedsysException('Missing Ds_SignatureVersion.');
        }

        $version = SignatureVersion::tryFrom($versionRaw);
        if (null === $version) {
            throw new RedsysException(\sprintf('Unsupported Ds_SignatureVersion "%s".', $versionRaw));
        }

        // The algorithm is the merchant's configured one: Ds_SignatureVersion is attacker-controlled,
        // so honouring it would allow a downgrade to a weaker scheme.
        if ($version !== $merchant->signatureVersion()) {
            throw new RedsysException(\sprintf('Ds_SignatureVersion "%s" does not match the merchant signature version "%s".', $versionRaw, $merchant->signatureVersion()->value));
        }

        $decoded = MerchantParameters::decode($encoded);
        $order = self::extractOrder($decoded);

        if (!$merchant->signer()->verify($encoded, $order, $signature)) {
            throw new RedsysException('Invalid Redsys notification signature.');
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
        $fields = $this->decoded->toArray();
        $value = $fields['Ds_Response'] ?? $fields['DS_RESPONSE'] ?? null;

        return \is_string($value) || \is_int($value) ? (string) $value : null;
    }

    /**
     * Ds_TransactionType of the notified operation, or null when absent / not a known type.
     */
    public function transactionType(): ?TransactionType
    {
        $fields = $this->decoded->toArray();
        $value = $fields['Ds_TransactionType'] ?? $fields['DS_TRANSACTIONTYPE'] ?? null;
        if (!\is_string($value) && !\is_int($value)) {
            return null;
        }

        return TransactionType::tryFrom((string) $value);
    }

    /**
     * Payment / pre-authorization success: Ds_Response in 0000..0099.
     *
     * Refunds, confirmations and cancellations report success with other codes
     * (0900, 0400): use {@see isSuccessfulForTransactionType()} for those.
     */
    public function isAuthorized(): bool
    {
        $numeric = $this->numericResponseCode();

        return null !== $numeric && $numeric >= 0 && $numeric <= 99;
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
        $type ??= $this->transactionType();

        return match ($type) {
            TransactionType::Refund, TransactionType::Confirmation => 900 === $this->numericResponseCode(),
            TransactionType::Cancellation => 400 === $this->numericResponseCode(),
            default => $this->isAuthorized(),
        };
    }

    private function numericResponseCode(): ?int
    {
        $code = $this->responseCode();
        if (null === $code || !ctype_digit($code)) {
            return null;
        }

        return (int) $code;
    }

    private static function extractOrder(MerchantParameters $parameters): string
    {
        $fields = $parameters->toArray();
        $order = $fields['Ds_Order'] ?? $fields['DS_ORDER'] ?? $fields['DS_MERCHANT_ORDER'] ?? null;
        if (!\is_string($order) || '' === $order) {
            throw new RedsysException('Notification payload is missing Ds_Order.');
        }

        return $order;
    }
}

<?php

declare(strict_types=1);

namespace Nowo\Redsys;

/**
 * The Ds_Response rules shared by {@see Notification} and {@see Rest\RestResponse}, over already verified fields.
 *
 * @internal
 */
final readonly class ResponseOutcome
{
    /**
     * @param array<string, mixed> $fields verified decoded parameters
     */
    public function __construct(private array $fields)
    {
    }

    /**
     * Ds_Response / DS_RESPONSE as sent by Redsys (e.g. "0000", "0900", "0190").
     */
    public function responseCode(): ?string
    {
        $value = $this->fields['Ds_Response'] ?? $this->fields['DS_RESPONSE'] ?? null;

        return \is_string($value) || \is_int($value) ? (string) $value : null;
    }

    /**
     * Ds_TransactionType, or null when absent / not a known type.
     */
    public function transactionType(): ?TransactionType
    {
        $value = $this->fields['Ds_TransactionType'] ?? $this->fields['DS_TRANSACTIONTYPE'] ?? null;
        if (!\is_string($value) && !\is_int($value)) {
            return null;
        }

        return TransactionType::tryFrom((string) $value);
    }

    /**
     * Ds_Response in 0000..0099.
     */
    public function isAuthorized(): bool
    {
        $numeric = $this->numericResponseCode();

        return null !== $numeric && $numeric >= 0 && $numeric <= 99;
    }

    /**
     * Refund / Confirmation: 0900; Cancellation: 0400; anything else: {@see isAuthorized()}.
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

    /**
     * Ds_Merchant_Identifier: the card reference (token) Redsys returned, or null when absent / empty.
     */
    public function merchantIdentifier(): ?string
    {
        return $this->firstString('Ds_Merchant_Identifier', 'DS_MERCHANT_IDENTIFIER');
    }

    /**
     * Ds_Merchant_Cof_Txnid: the COF transaction id Redsys returned, or null when absent / empty.
     */
    public function cofTxnId(): ?string
    {
        return $this->firstString('Ds_Merchant_Cof_Txnid', 'DS_MERCHANT_COF_TXNID', 'Ds_Merchant_COF_TXNID');
    }

    private function firstString(string ...$keys): ?string
    {
        foreach ($keys as $key) {
            $value = $this->fields[$key] ?? null;
            if ((\is_string($value) && '' !== $value) || \is_int($value)) {
                return (string) $value;
            }
        }

        return null;
    }

    private function numericResponseCode(): ?int
    {
        $code = $this->responseCode();
        if (null === $code || !ctype_digit($code)) {
            return null;
        }

        return (int) $code;
    }
}

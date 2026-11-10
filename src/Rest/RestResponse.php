<?php

declare(strict_types=1);

namespace Nowo\Redsys\Rest;

use Nowo\Redsys\Exception\InvalidResponseException;
use Nowo\Redsys\Exception\SignatureInvalidException;
use Nowo\Redsys\Http\HttpResponse;
use Nowo\Redsys\Merchant;
use Nowo\Redsys\MerchantParameters;
use Nowo\Redsys\ResponseOutcome;
use Nowo\Redsys\SignatureVersion;
use Nowo\Redsys\TransactionType;

/**
 * Parsed REST response (signature-checked when Redsys returns the signed trio).
 *
 * **Only {@see verifiedParameters()} and the outcome helpers are trusted.** `$decodedParameters` is filled even when the
 * signature is missing or does not verify (the raw JSON, or the decoded but unverified fields) for diagnostics: never
 * act on it without checking {@see isSignatureValid()}, or call {@see assertSignatureValid()} first.
 * {@see responseCode()}, {@see isAuthorized()} and {@see isSuccessfulForTransactionType()} read verified fields only:
 * on an unverified response they return null / false.
 */
final readonly class RestResponse
{
    /**
     * @param array<string, mixed>|null $decodedParameters UNTRUSTED unless $signatureValid (see the class doc)
     */
    public function __construct(
        public int $httpStatus,
        public string $rawBody,
        public ?array $decodedParameters,
        public bool $signatureValid,
        public ?string $errorCode,
    ) {
    }

    public static function fromHttp(HttpResponse $response, Merchant $merchant): self
    {
        $json = json_decode($response->body, true);
        if (!\is_array($json)) {
            return new self($response->statusCode, $response->body, null, false, null);
        }

        $errorCode = isset($json['errorCode']) && \is_scalar($json['errorCode'])
            ? (string) $json['errorCode']
            : null;

        $encoded = $json['Ds_MerchantParameters'] ?? null;
        $signature = $json['Ds_Signature'] ?? null;
        $versionRaw = $json['Ds_SignatureVersion'] ?? null;

        if (!\is_string($encoded) || !\is_string($signature) || !\is_string($versionRaw)) {
            return new self($response->statusCode, $response->body, $json, false, $errorCode);
        }

        try {
            $params = MerchantParameters::decode($encoded);
            $fields = $params->toArray();
            $order = $fields['Ds_Order'] ?? $fields['DS_ORDER'] ?? $fields['DS_MERCHANT_ORDER'] ?? null;
            if (!\is_string($order) || '' === $order) {
                throw new InvalidResponseException('REST response missing order.');
            }

            // Verify with the merchant's configured algorithm only; a different (attacker-supplied)
            // Ds_SignatureVersion is a downgrade attempt and never validates.
            $valid = SignatureVersion::tryFrom($versionRaw) === $merchant->signatureVersion()
                && $merchant->signer()->verify($encoded, $order, $signature);

            return new self($response->statusCode, $response->body, $fields, $valid, $errorCode);
        } catch (\Throwable) {
            return new self(
                $response->statusCode,
                $response->body,
                ['Ds_MerchantParameters' => $encoded],
                false,
                $errorCode
            );
        }
    }

    /**
     * Whether Redsys's signature over the decoded parameters verified with the merchant's configured algorithm.
     */
    public function isSignatureValid(): bool
    {
        return $this->signatureValid;
    }

    /**
     * Throws unless the signature verified; call it before using the response as a payment outcome.
     *
     * @throws SignatureInvalidException
     */
    public function assertSignatureValid(): void
    {
        if (!$this->signatureValid) {
            throw new SignatureInvalidException('Redsys REST response is not signed or its signature does not verify.');
        }
    }

    /**
     * The decoded parameters when the signature verified; null otherwise (use this, not `$decodedParameters`).
     *
     * @return array<string, mixed>|null
     */
    public function verifiedParameters(): ?array
    {
        return $this->signatureValid ? $this->decodedParameters : null;
    }

    /**
     * Verified Ds_Response (e.g. "0000"); null when absent or when the signature did not verify.
     */
    public function responseCode(): ?string
    {
        return $this->outcome()?->responseCode();
    }

    /**
     * Verified Ds_TransactionType; null when absent, unknown or when the signature did not verify.
     */
    public function transactionType(): ?TransactionType
    {
        return $this->outcome()?->transactionType();
    }

    /**
     * Payment / pre-authorization success (verified Ds_Response 0000..0099); false when the signature did not verify.
     */
    public function isAuthorized(): bool
    {
        return $this->outcome()?->isAuthorized() ?? false;
    }

    /**
     * Success for the transaction type (Refund / Confirmation 0900, Cancellation 0400, else 0000..0099), on verified
     * fields only; false when the signature did not verify.
     *
     * @param TransactionType|null $type Expected type; null uses the response's Ds_TransactionType
     */
    public function isSuccessfulForTransactionType(?TransactionType $type = null): bool
    {
        return $this->outcome()?->isSuccessfulForTransactionType($type) ?? false;
    }

    private function outcome(): ?ResponseOutcome
    {
        $fields = $this->verifiedParameters();

        return null === $fields ? null : new ResponseOutcome($fields);
    }
}

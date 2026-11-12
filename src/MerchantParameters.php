<?php

declare(strict_types=1);

namespace Nowo\Redsys;

use Nowo\Redsys\Encoding\Base64Url;
use Nowo\Redsys\Exception\InvalidResponseException;
use Nowo\Redsys\Exception\RedsysException;

/**
 * DS_MERCHANT_* request map → Base64URL JSON for Ds_MerchantParameters.
 *
 * Immutable copy-on-write: every mutator returns a new instance (FrankenPHP worker-safe).
 */
final readonly class MerchantParameters implements \JsonSerializable
{
    /** DS_MERCHANT_IDENTIFIER value that asks Redsys to store the card and return a reference (token). */
    public const string IDENTIFIER_REQUIRED = 'REQUIRED';

    /** @param array<string, mixed> $fields */
    public function __construct(
        private array $fields = [],
    ) {
    }

    public static function create(): self
    {
        return new self();
    }

    public function with(string $key, mixed $value): self
    {
        $fields = $this->fields;
        $fields[$key] = $value;

        return new self($fields);
    }

    public function amount(int|string $amount): self
    {
        return $this->with('DS_MERCHANT_AMOUNT', (string) $amount);
    }

    public function order(string $order): self
    {
        return $this->with('DS_MERCHANT_ORDER', $order);
    }

    public function merchantCode(string $code): self
    {
        return $this->with('DS_MERCHANT_MERCHANTCODE', $code);
    }

    public function currency(Currency|string $currency): self
    {
        return $this->with(
            'DS_MERCHANT_CURRENCY',
            $currency instanceof Currency ? $currency->value : $currency
        );
    }

    public function transactionType(TransactionType|string $type): self
    {
        return $this->with(
            'DS_MERCHANT_TRANSACTIONTYPE',
            $type instanceof TransactionType ? $type->value : $type
        );
    }

    public function terminal(string $terminal): self
    {
        return $this->with('DS_MERCHANT_TERMINAL', $terminal);
    }

    public function merchantUrl(?string $url): self
    {
        return null === $url ? $this : $this->with('DS_MERCHANT_MERCHANTURL', $url);
    }

    public function urlOk(?string $url): self
    {
        return null === $url ? $this : $this->with('DS_MERCHANT_URLOK', $url);
    }

    public function urlKo(?string $url): self
    {
        return null === $url ? $this : $this->with('DS_MERCHANT_URLKO', $url);
    }

    public function productDescription(?string $description): self
    {
        return null === $description ? $this : $this->with('DS_MERCHANT_PRODUCTDESCRIPTION', $description);
    }

    public function payMethods(?string $methods): self
    {
        return null === $methods ? $this : $this->with('DS_MERCHANT_PAYMETHODS', $methods);
    }

    /**
     * DS_MERCHANT_IDENTIFIER: {@see IDENTIFIER_REQUIRED} to ask Redsys for a card reference (token), or a reference it
     * returned earlier ({@see Notification::merchantIdentifier()}, {@see Rest\RestResponse::merchantIdentifier()}).
     *
     * Redsys documents the field as up to 40 characters (its references are 40 hexadecimal characters); this accepts 1
     * to 40 ASCII letters or digits. A lower- or mixed-case "required" is refused instead of being sent as a reference.
     *
     * @throws RedsysException the value is empty, too long or has other characters (the value is never in the message)
     */
    public function identifier(#[\SensitiveParameter] string $value): self
    {
        if (self::IDENTIFIER_REQUIRED !== $value && 0 === strcasecmp($value, self::IDENTIFIER_REQUIRED)) {
            throw new RedsysException(\sprintf('DS_MERCHANT_IDENTIFIER must be "%s" in upper case to request a reference.', self::IDENTIFIER_REQUIRED));
        }
        if (1 !== preg_match('/\A[A-Za-z0-9]{1,40}\z/', $value)) {
            throw new RedsysException('DS_MERCHANT_IDENTIFIER must be 1 to 40 ASCII letters or digits.');
        }

        return $this->withField('DS_MERCHANT_IDENTIFIER', $value);
    }

    /**
     * DS_MERCHANT_COF_INI: "S" on the first credential-on-file operation (the one that stores the credentials), "N"
     * otherwise.
     */
    public function cofInit(bool $init = true): self
    {
        return $this->withField('DS_MERCHANT_COF_INI', $init ? 'S' : 'N');
    }

    /**
     * DS_MERCHANT_COF_TYPE: the kind of COF operation (R recurring, I installments, …).
     *
     * @throws RedsysException the string is not a {@see CofType} value
     */
    public function cofType(CofType|string $type): self
    {
        if (\is_string($type)) {
            $type = CofType::tryFrom($type)
                ?? throw new RedsysException(\sprintf('Unknown DS_MERCHANT_COF_TYPE (a value of %d bytes).', \strlen($type)));
        }

        return $this->withField('DS_MERCHANT_COF_TYPE', $type->value);
    }

    /**
     * DS_MERCHANT_EXCEP_SCA: the PSD2 exemption requested (MIT for merchant-initiated charges on stored credentials).
     *
     * @throws RedsysException the string is not a {@see ScaExemption} value
     */
    public function excepSca(ScaExemption|string $exemption): self
    {
        if (\is_string($exemption)) {
            $exemption = ScaExemption::tryFrom($exemption)
                ?? throw new RedsysException(\sprintf('Unknown DS_MERCHANT_EXCEP_SCA (a value of %d bytes).', \strlen($exemption)));
        }

        return $this->withField('DS_MERCHANT_EXCEP_SCA', $exemption->value);
    }

    /**
     * DS_MERCHANT_DIRECTPAYMENT: true (or {@see DirectPayment::WithoutAuthentication}) sends "true", {@see
     * DirectPayment::Moto} sends "moto"; a string is matched case-insensitively against those two values and sent in
     * the documented lower case. false removes the field.
     *
     * @throws RedsysException the string is neither "true" nor "moto"
     */
    public function directPayment(bool|DirectPayment|string $mode = true): self
    {
        if (false === $mode) {
            return new self($this->fieldsWithout('DS_MERCHANT_DIRECTPAYMENT'));
        }
        if (true === $mode) {
            $mode = DirectPayment::WithoutAuthentication;
        } elseif (\is_string($mode)) {
            $mode = DirectPayment::tryFrom(strtolower($mode))
                ?? throw new RedsysException(\sprintf('Unknown DS_MERCHANT_DIRECTPAYMENT (a value of %d bytes).', \strlen($mode)));
        }

        return $this->withField('DS_MERCHANT_DIRECTPAYMENT', $mode->value);
    }

    /**
     * DS_MERCHANT_COF_TXNID: the id Redsys returned on the first COF operation ({@see Notification::cofTxnId()},
     * {@see Rest\RestResponse::cofTxnId()}). Redsys documents it as up to 15 alphanumeric characters.
     *
     * @throws RedsysException the value is empty, longer than 15 or not ASCII letters / digits (never in the message)
     */
    public function cofTxnId(#[\SensitiveParameter] string $txnId): self
    {
        if (1 !== preg_match('/\A[A-Za-z0-9]{1,15}\z/', $txnId)) {
            throw new RedsysException('DS_MERCHANT_COF_TXNID must be 1 to 15 ASCII letters or digits.');
        }

        return $this->withField('DS_MERCHANT_COF_TXNID', $txnId);
    }

    /**
     * Apply merchant identity fields from credentials (code + terminal).
     */
    public function forMerchant(Merchant $merchant): self
    {
        return $this
            ->merchantCode($merchant->merchantCode())
            ->terminal($merchant->terminal());
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->fields;
    }

    public function orderNumber(): string
    {
        $order = $this->fields['DS_MERCHANT_ORDER'] ?? null;
        if (!\is_string($order) || '' === $order) {
            throw new RedsysException('DS_MERCHANT_ORDER is required.');
        }

        return $order;
    }

    public function encode(): string
    {
        $json = json_encode($this->fields, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        // @codeCoverageIgnoreStart
        if (false === $json) {
            throw new RedsysException('Unable to encode merchant parameters as JSON.');
        }
        // @codeCoverageIgnoreEnd

        return Base64Url::encode($json);
    }

    /**
     * Decode a Ds_MerchantParameters / notification payload.
     */
    public static function decode(string $encoded): self
    {
        $json = Base64Url::decode($encoded);
        $data = json_decode($json, true);
        if (!\is_array($data)) {
            throw new InvalidResponseException('Merchant parameters JSON is invalid.');
        }

        /* @var array<string, mixed> $data */
        return new self($data);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->fields;
    }

    /**
     * var_dump() / print_r() view: the card reference (except "REQUIRED") and the COF transaction id are redacted, in
     * request (DS_MERCHANT_*) and response (Ds_Merchant_*) casing alike. {@see toArray()} and {@see encode()} keep them.
     *
     * @return array{fields: array<array-key, mixed>}
     */
    public function __debugInfo(): array
    {
        return ['fields' => Redaction::fields($this->fields)];
    }

    /**
     * Writes a field after removing every existing key that differs only in case, so a value set earlier with another
     * spelling (Ds_Merchant_Cof_Txnid, DS_MERCHANT_COF_TXNID, …) cannot travel next to the typed one.
     */
    private function withField(string $key, string $value): self
    {
        $fields = $this->fieldsWithout($key);
        $fields[$key] = $value;

        return new self($fields);
    }

    /**
     * @return array<string, mixed> the fields without any key equal to $key ignoring case
     */
    private function fieldsWithout(string $key): array
    {
        $fields = $this->fields;
        foreach (array_keys($fields) as $existing) {
            if (strtoupper((string) $existing) === $key) {
                unset($fields[$existing]);
            }
        }

        return $fields;
    }
}

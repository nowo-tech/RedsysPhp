<?php

declare(strict_types=1);

namespace Nowo\Redsys;

use Nowo\Redsys\Encoding\Base64Url;
use Nowo\Redsys\Exception\RedsysException;

/**
 * DS_MERCHANT_* request map → Base64URL JSON for Ds_MerchantParameters.
 */
final class MerchantParameters implements \JsonSerializable
{
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
        $clone = clone $this;
        $clone->fields[$key] = $value;

        return $clone;
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
            throw new RedsysException('Merchant parameters JSON is invalid.');
        }

        /* @var array<string, mixed> $data */
        return new self($data);
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->fields;
    }
}

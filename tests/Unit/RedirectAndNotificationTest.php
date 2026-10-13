<?php

declare(strict_types=1);

namespace Nowo\Redsys\Tests\Unit;

use Nowo\Redsys\Currency;
use Nowo\Redsys\Environment;
use Nowo\Redsys\Merchant;
use Nowo\Redsys\MerchantParameters;
use Nowo\Redsys\Notification;
use Nowo\Redsys\RedirectForm;
use Nowo\Redsys\SignatureVersion;
use Nowo\Redsys\SignedPayload;
use Nowo\Redsys\TransactionType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RedirectAndNotificationTest extends TestCase
{
    private Merchant $merchant;

    protected function setUp(): void
    {
        $this->merchant = new Merchant(
            '999008881',
            '1',
            'sq7HjrUOBfKmC576ILgskD5srU870gJ7',
            Environment::Test,
            SignatureVersion::HmacSha512V2,
        );
    }

    public function testRedirectFormReturnsHtmlWithoutSideEffects(): void
    {
        $params = MerchantParameters::create()
            ->forMerchant($this->merchant)
            ->amount(145)
            ->order('1444904795')
            ->currency(Currency::Eur)
            ->transactionType(TransactionType::Authorization)
            ->merchantUrl('https://example.test/notify')
            ->urlOk('https://example.test/ok')
            ->urlKo('https://example.test/ko');

        $html = RedirectForm::forMerchant($this->merchant, $params);

        self::assertStringContainsString('method="POST"', $html);
        self::assertStringContainsString('Ds_MerchantParameters', $html);
        self::assertStringContainsString('Ds_Signature', $html);
        self::assertStringContainsString('HMAC_SHA512_V2', $html);
        self::assertStringContainsString('sis-t.redsys.es', $html);
        self::assertStringNotContainsString('exit(', $html);
    }

    public function testNotificationRoundTrip(): void
    {
        $request = MerchantParameters::create()
            ->amount(999)
            ->order('1234567890')
            ->merchantCode('999008881')
            ->currency('978')
            ->transactionType('0')
            ->terminal('1');

        $payload = SignedPayload::from($this->merchant, $request);

        // Simulate a notification using the same signed trio + Ds_Order in decoded body.
        $notifyBody = MerchantParameters::decode($payload->merchantParameters)
            ->with('Ds_Order', '1234567890')
            ->with('Ds_Response', '0000');
        $encoded = $notifyBody->encode();
        $signature = $this->merchant->signer()->sign($encoded, '1234567890');

        $notification = Notification::fromRequest([
            'Ds_MerchantParameters' => $encoded,
            'Ds_Signature' => $signature,
            'Ds_SignatureVersion' => SignatureVersion::HmacSha512V2->value,
        ], $this->merchant);

        self::assertTrue($notification->isAuthorized());
        self::assertSame('0000', $notification->responseCode());
    }

    /**
     * @return iterable<string, array{0: array<string, string>, 1: ?TransactionType, 2: bool, 3: bool}>
     */
    public static function successCodeProvider(): iterable
    {
        yield 'payment 0000' => [['Ds_TransactionType' => '0', 'Ds_Response' => '0000'], null, true, true];
        yield 'payment 0190 denied' => [['Ds_TransactionType' => '0', 'Ds_Response' => '0190'], null, false, false];
        yield 'refund 0900' => [['Ds_TransactionType' => '3', 'Ds_Response' => '0900'], null, false, true];
        yield 'refund 0000 is not a refund success' => [['Ds_TransactionType' => '3', 'Ds_Response' => '0000'], null, true, false];
        yield 'confirmation 0900' => [['Ds_TransactionType' => '2', 'Ds_Response' => '0900'], null, false, true];
        yield 'cancellation 0400' => [['Ds_TransactionType' => '9', 'Ds_Response' => '0400'], null, false, true];
        yield 'cancellation 0900 rejected' => [['Ds_TransactionType' => '9', 'Ds_Response' => '0900'], null, false, false];
        yield 'explicit refund type overrides payload' => [['Ds_Response' => '0900'], TransactionType::Refund, false, true];
        yield 'no type falls back to 0..99' => [['Ds_Response' => '0050'], null, true, true];
        yield 'unknown type falls back to 0..99' => [['Ds_TransactionType' => 'X', 'Ds_Response' => '0900'], null, false, false];
        yield 'uppercase keys' => [['DS_TRANSACTIONTYPE' => '3', 'DS_RESPONSE' => '0900'], null, false, true];
    }

    /**
     * Regression: isAuthorized() only knows 0..99, so successful refunds (0900) and cancellations (0400)
     * looked failed; isSuccessfulForTransactionType() understands those codes.
     *
     * @param array<string, string> $fields
     */
    #[DataProvider('successCodeProvider')]
    public function testIsSuccessfulForTransactionType(array $fields, ?TransactionType $expected, bool $authorized, bool $successful): void
    {
        $body = MerchantParameters::create()->with('Ds_Order', '1234567890');
        foreach ($fields as $key => $value) {
            $body = $body->with($key, $value);
        }
        $encoded = $body->encode();

        $notification = Notification::fromRequest([
            'Ds_MerchantParameters' => $encoded,
            'Ds_Signature' => $this->merchant->signer()->sign($encoded, '1234567890'),
            'Ds_SignatureVersion' => SignatureVersion::HmacSha512V2->value,
        ], $this->merchant);

        self::assertSame($authorized, $notification->isAuthorized());
        self::assertSame($successful, $notification->isSuccessfulForTransactionType($expected));
    }

    public function testTransactionTypeIsExposed(): void
    {
        $encoded = MerchantParameters::create()->with('Ds_Order', '1234567890')->with('Ds_TransactionType', 3)->encode();
        $notification = Notification::fromRequest([
            'Ds_MerchantParameters' => $encoded,
            'Ds_Signature' => $this->merchant->signer()->sign($encoded, '1234567890'),
            'Ds_SignatureVersion' => SignatureVersion::HmacSha512V2->value,
        ], $this->merchant);

        self::assertSame(TransactionType::Refund, $notification->transactionType());
    }
}

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
}

<?php

declare(strict_types=1);

namespace Nowo\Redsys\Tests\Unit;

use Nowo\Redsys\Encoding\Base64Url;
use Nowo\Redsys\Environment;
use Nowo\Redsys\Exception\RedsysException;
use Nowo\Redsys\Merchant;
use Nowo\Redsys\MerchantParameters;
use Nowo\Redsys\Notification;
use Nowo\Redsys\Signature\Signer;
use Nowo\Redsys\SignatureVersion;
use Nowo\Redsys\Version;
use PHPUnit\Framework\TestCase;

final class ProtocolExtrasTest extends TestCase
{
    public function testHmacSha256V1RoundTrip(): void
    {
        $key = 'sq7HjrUOBfKmC576ILgskD5srU870gJ7';
        $signer = new Signer($key, SignatureVersion::HmacSha256V1);
        $params = 'eyJ0ZXN0IjoxfQ';
        $order = '1234567890';

        $signature = $signer->sign($params, $order);
        self::assertNotSame('', $signature);
        self::assertTrue($signer->verify($params, $order, $signature));
        self::assertNotSame('', $signer->diversify($order));
    }

    public function testBase64UrlRoundTrip(): void
    {
        $raw = '{"DS_MERCHANT_ORDER":"1"}';
        $encoded = Base64Url::encode($raw);
        self::assertSame($raw, Base64Url::decode($encoded));
    }

    public function testEnvironmentLiveUrls(): void
    {
        self::assertStringContainsString('sis.redsys.es', Environment::Live->redirectUrl());
        self::assertStringContainsString('iniciaPeticionREST', Environment::Live->restInitUrl());
        self::assertStringContainsString('trataPeticionREST', Environment::Live->restTreatUrl());
    }

    public function testInvalidNotificationSignatureThrows(): void
    {
        $merchant = new Merchant('999008881', '1', 'sq7HjrUOBfKmC576ILgskD5srU870gJ7');
        $params = MerchantParameters::create()->with('Ds_Order', '1')->with('Ds_Response', '0000');
        $encoded = $params->encode();

        $this->expectException(RedsysException::class);
        Notification::fromRequest([
            'Ds_MerchantParameters' => $encoded,
            'Ds_Signature' => 'not-a-valid-signature',
            'Ds_SignatureVersion' => SignatureVersion::HmacSha512V2->value,
        ], $merchant);
    }

    public function testVersionConstant(): void
    {
        self::assertSame('1.0.0', Version::VERSION);
    }
}

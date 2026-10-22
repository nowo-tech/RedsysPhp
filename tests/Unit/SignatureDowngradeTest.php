<?php

declare(strict_types=1);

namespace Nowo\Redsys\Tests\Unit;

use Nowo\Redsys\Environment;
use Nowo\Redsys\Exception\RedsysException;
use Nowo\Redsys\Http\HttpResponse;
use Nowo\Redsys\Merchant;
use Nowo\Redsys\MerchantParameters;
use Nowo\Redsys\Notification;
use Nowo\Redsys\Rest\RestResponse;
use Nowo\Redsys\Signature\Signer;
use Nowo\Redsys\SignatureVersion;
use PHPUnit\Framework\TestCase;

/**
 * Ds_SignatureVersion is attacker-controlled: verification must always use the merchant's configured version.
 */
final class SignatureDowngradeTest extends TestCase
{
    private const string SECRET = 'sq7HjrUOBfKmC576ILgskD5srU870gJ7';

    public function testNotificationRejectsDowngradeToSha256(): void
    {
        $merchant = $this->merchant(SignatureVersion::HmacSha512V2);
        $encoded = MerchantParameters::create()->with('Ds_Order', '1234')->with('Ds_Response', '0000')->encode();
        // Correctly signed with the weaker scheme: must still be rejected because the merchant uses SHA-512 V2.
        $signature = (new Signer(self::SECRET, SignatureVersion::HmacSha256V1))->sign($encoded, '1234');

        $this->expectException(RedsysException::class);
        $this->expectExceptionMessage('does not match the merchant signature version');

        Notification::fromRequest([
            'Ds_MerchantParameters' => $encoded,
            'Ds_Signature' => $signature,
            'Ds_SignatureVersion' => SignatureVersion::HmacSha256V1->value,
        ], $merchant);
    }

    public function testNotificationRejectsMismatchedVersionEvenWithMerchantAlgorithmSignature(): void
    {
        $merchant = $this->merchant(SignatureVersion::HmacSha256V1);
        $encoded = MerchantParameters::create()->with('Ds_Order', '1234')->encode();
        $signature = $merchant->signer()->sign($encoded, '1234');

        $this->expectException(RedsysException::class);

        Notification::fromRequest([
            'Ds_MerchantParameters' => $encoded,
            'Ds_Signature' => $signature,
            'Ds_SignatureVersion' => SignatureVersion::HmacSha512V2->value,
        ], $merchant);
    }

    public function testNotificationUsesMerchantVersionWhenFieldIsAbsent(): void
    {
        $merchant = $this->merchant(SignatureVersion::HmacSha256V1);
        $encoded = MerchantParameters::create()->with('Ds_Order', '1234')->with('Ds_Response', '0000')->encode();

        $notification = Notification::fromRequest([
            'Ds_MerchantParameters' => $encoded,
            'Ds_Signature' => $merchant->signer()->sign($encoded, '1234'),
        ], $merchant);

        self::assertSame(SignatureVersion::HmacSha256V1, $notification->signatureVersion());
        self::assertTrue($notification->isAuthorized());
    }

    public function testRestResponseRejectsDowngradeToSha256(): void
    {
        $merchant = $this->merchant(SignatureVersion::HmacSha512V2);
        $encoded = MerchantParameters::create()->with('Ds_Order', '1444904795')->with('Ds_Response', '0000')->encode();
        $body = json_encode([
            'Ds_MerchantParameters' => $encoded,
            'Ds_Signature' => (new Signer(self::SECRET, SignatureVersion::HmacSha256V1))->sign($encoded, '1444904795'),
            'Ds_SignatureVersion' => SignatureVersion::HmacSha256V1->value,
        ], \JSON_THROW_ON_ERROR);

        $response = RestResponse::fromHttp(new HttpResponse(200, $body), $merchant);

        self::assertFalse($response->signatureValid);
    }

    public function testRestResponseRejectsUnknownVersion(): void
    {
        $merchant = $this->merchant(SignatureVersion::HmacSha512V2);
        $encoded = MerchantParameters::create()->with('Ds_Order', '1444904795')->encode();
        $body = json_encode([
            'Ds_MerchantParameters' => $encoded,
            'Ds_Signature' => $merchant->signer()->sign($encoded, '1444904795'),
            'Ds_SignatureVersion' => 'HMAC_SHA1_LEGACY',
        ], \JSON_THROW_ON_ERROR);

        self::assertFalse(RestResponse::fromHttp(new HttpResponse(200, $body), $merchant)->signatureValid);
    }

    public function testRestResponseAcceptsMerchantVersion(): void
    {
        $merchant = $this->merchant(SignatureVersion::HmacSha256V1);
        $encoded = MerchantParameters::create()->with('Ds_Order', '1444904795')->encode();
        $body = json_encode([
            'Ds_MerchantParameters' => $encoded,
            'Ds_Signature' => $merchant->signer()->sign($encoded, '1444904795'),
            'Ds_SignatureVersion' => SignatureVersion::HmacSha256V1->value,
        ], \JSON_THROW_ON_ERROR);

        self::assertTrue(RestResponse::fromHttp(new HttpResponse(200, $body), $merchant)->signatureValid);
    }

    public function testSecretKeyIsRedactedFromStackTraces(): void
    {
        $merchantParameter = (new \ReflectionMethod(Merchant::class, '__construct'))->getParameters()[2];
        $signerParameter = (new \ReflectionMethod(Signer::class, '__construct'))->getParameters()[0];

        self::assertNotSame([], $merchantParameter->getAttributes(\SensitiveParameter::class));
        self::assertNotSame([], $signerParameter->getAttributes(\SensitiveParameter::class));
    }

    private function merchant(SignatureVersion $version): Merchant
    {
        return new Merchant('999008881', '1', self::SECRET, Environment::Test, $version);
    }
}

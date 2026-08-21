<?php

declare(strict_types=1);

namespace Nowo\Redsys\Tests\Unit;

use Nowo\Redsys\Currency;
use Nowo\Redsys\Encoding\Base64Url;
use Nowo\Redsys\Environment;
use Nowo\Redsys\Exception\RedsysException;
use Nowo\Redsys\Http\HttpClient;
use Nowo\Redsys\Http\HttpResponse;
use Nowo\Redsys\Merchant;
use Nowo\Redsys\MerchantParameters;
use Nowo\Redsys\Notification;
use Nowo\Redsys\Rest\RestClient;
use Nowo\Redsys\Rest\RestResponse;
use Nowo\Redsys\Signature\Signer;
use Nowo\Redsys\SignatureVersion;
use Nowo\Redsys\SignedPayload;
use Nowo\Redsys\TransactionType;
use PHPUnit\Framework\TestCase;

final class FullSurfaceTest extends TestCase
{
    public function testMerchantAccessorsAndEmptyRejection(): void
    {
        $merchant = new Merchant('999008881', '1', 'sq7HjrUOBfKmC576ILgskD5srU870gJ7', Environment::Live, SignatureVersion::HmacSha512V1);
        self::assertSame('999008881', $merchant->merchantCode());
        self::assertSame('1', $merchant->terminal());
        self::assertSame('sq7HjrUOBfKmC576ILgskD5srU870gJ7', $merchant->secretKey());
        self::assertSame(Environment::Live, $merchant->environment());
        self::assertSame(SignatureVersion::HmacSha512V1, $merchant->signatureVersion());
        self::assertSame(SignatureVersion::HmacSha512V1, $merchant->signer()->version());

        $this->expectException(RedsysException::class);
        new Merchant('', '1', 'k');
    }

    public function testSignerEmptyKeyAndEmptyOrder(): void
    {
        $this->expectException(RedsysException::class);
        new Signer('');
    }

    public function testSignerEmptyOrderThrows(): void
    {
        $signer = new Signer('sq7HjrUOBfKmC576ILgskD5srU870gJ7');
        $this->expectException(RedsysException::class);
        $signer->diversify('');
    }

    public function testHmacSha512V1Signs(): void
    {
        $signer = new Signer('sq7HjrUOBfKmC576ILgskD5srU870gJ7', SignatureVersion::HmacSha512V1);
        $sig = $signer->sign('abc', '1234567890');
        self::assertTrue($signer->verify('abc', '1234567890', $sig));
    }

    public function testMerchantParametersBuildersAndJson(): void
    {
        $params = MerchantParameters::create()
            ->amount(100)
            ->order('ord1')
            ->merchantCode('999008881')
            ->currency(Currency::Usd)
            ->transactionType(TransactionType::Refund)
            ->terminal('001')
            ->merchantUrl(null)
            ->urlOk(null)
            ->urlKo(null)
            ->productDescription('Test')
            ->payMethods('z')
            ->merchantUrl('https://n.test')
            ->urlOk('https://ok.test')
            ->urlKo('https://ko.test');

        $arr = $params->toArray();
        self::assertSame('100', $arr['DS_MERCHANT_AMOUNT']);
        self::assertSame('840', $arr['DS_MERCHANT_CURRENCY']);
        self::assertSame('3', $arr['DS_MERCHANT_TRANSACTIONTYPE']);
        self::assertSame($arr, $params->jsonSerialize());

        $encoded = $params->encode();
        $decoded = MerchantParameters::decode($encoded);
        self::assertSame('ord1', $decoded->orderNumber());
    }

    public function testMerchantParametersOrderRequired(): void
    {
        $this->expectException(RedsysException::class);
        MerchantParameters::create()->orderNumber();
    }

    public function testBase64UrlInvalid(): void
    {
        $this->expectException(RedsysException::class);
        Base64Url::decode('!!!');
    }

    public function testBase64StandardInvalid(): void
    {
        $this->expectException(RedsysException::class);
        Base64Url::decodeStandard('!!!');
    }

    public function testSignedPayloadJsonAndNotificationAccessors(): void
    {
        $merchant = new Merchant('999008881', '1', 'sq7HjrUOBfKmC576ILgskD5srU870gJ7');
        $params = MerchantParameters::create()
            ->forMerchant($merchant)
            ->amount(1)
            ->order('1234')
            ->currency(Currency::Gbp)
            ->transactionType(TransactionType::Preauthorization);

        $payload = SignedPayload::from($merchant, $params);
        $json = $payload->toJson();
        self::assertStringContainsString('Ds_Signature', $json);

        $notifyParams = MerchantParameters::decode($payload->merchantParameters)
            ->with('Ds_Order', '1234')
            ->with('Ds_Response', '0100');
        $encoded = $notifyParams->encode();
        $sig = $merchant->signer()->sign($encoded, '1234');
        $notification = Notification::fromRequest([
            'Ds_MerchantParameters' => $encoded,
            'Ds_Signature' => $sig,
            'Ds_SignatureVersion' => SignatureVersion::HmacSha512V2->value,
        ], $merchant);

        self::assertFalse($notification->isAuthorized());
        self::assertSame('0100', $notification->responseCode());
        self::assertSame($encoded, $notification->encodedParameters());
        self::assertSame($sig, $notification->signature());
        self::assertSame(SignatureVersion::HmacSha512V2, $notification->signatureVersion());
        self::assertSame('1234', $notification->parameters()->toArray()['Ds_Order']);
    }

    public function testNotificationMissingFields(): void
    {
        $merchant = new Merchant('999008881', '1', 'sq7HjrUOBfKmC576ILgskD5srU870gJ7');
        $this->expectException(RedsysException::class);
        Notification::fromRequest([], $merchant);
    }

    public function testNotificationUnsupportedVersion(): void
    {
        $merchant = new Merchant('999008881', '1', 'sq7HjrUOBfKmC576ILgskD5srU870gJ7');
        $this->expectException(RedsysException::class);
        Notification::fromRequest([
            'Ds_MerchantParameters' => Base64Url::encode('{"Ds_Order":"1"}'),
            'Ds_Signature' => 'x',
            'Ds_SignatureVersion' => 'HMAC_SHA1_LEGACY',
        ], $merchant);
    }

    public function testRestResponseNonJsonAndErrorCode(): void
    {
        $merchant = new Merchant('999008881', '1', 'sq7HjrUOBfKmC576ILgskD5srU870gJ7');
        $plain = RestResponse::fromHttp(new HttpResponse(500, 'not-json'), $merchant);
        self::assertFalse($plain->signatureValid);
        self::assertNull($plain->decodedParameters);

        $err = RestResponse::fromHttp(new HttpResponse(200, '{"errorCode":"SIS0001"}'), $merchant);
        self::assertSame('SIS0001', $err->errorCode);
        self::assertFalse($err->signatureValid);
    }

    public function testRestClientInit(): void
    {
        $merchant = new Merchant('999008881', '1', 'sq7HjrUOBfKmC576ILgskD5srU870gJ7');
        $http = new class implements HttpClient {
            public function postJson(string $url, string $jsonBody, array $headers = []): HttpResponse
            {
                TestCase::assertStringContainsString('iniciaPeticionREST', $url);

                return new HttpResponse(200, '{"errorCode":"0"}');
            }
        };
        $client = new RestClient($merchant, $http);
        $params = MerchantParameters::create()
            ->forMerchant($merchant)
            ->amount(1)
            ->order('99')
            ->currency(Currency::Eur)
            ->transactionType(TransactionType::Authorization);
        $response = $client->init($params);
        self::assertSame(200, $response->httpStatus);
    }

    public function testRestResponseMissingOrderAndCorruptPayload(): void
    {
        $merchant = new Merchant('999008881', '1', 'sq7HjrUOBfKmC576ILgskD5srU870gJ7');

        $noOrder = MerchantParameters::create()->with('Ds_Response', '0000')->encode();
        $bodyNoOrder = json_encode([
            'Ds_MerchantParameters' => $noOrder,
            'Ds_Signature' => 'x',
            'Ds_SignatureVersion' => SignatureVersion::HmacSha512V2->value,
        ], \JSON_THROW_ON_ERROR);
        $r1 = RestResponse::fromHttp(new HttpResponse(200, $bodyNoOrder), $merchant);
        self::assertFalse($r1->signatureValid);

        $bodyBad = json_encode([
            'Ds_MerchantParameters' => '!!!not-base64!!!',
            'Ds_Signature' => 'x',
            'Ds_SignatureVersion' => SignatureVersion::HmacSha512V2->value,
        ], \JSON_THROW_ON_ERROR);
        $r2 = RestResponse::fromHttp(new HttpResponse(200, $bodyBad), $merchant);
        self::assertFalse($r2->signatureValid);
        self::assertArrayHasKey('Ds_MerchantParameters', $r2->decodedParameters ?? []);
    }

    public function testNotificationEdgeCases(): void
    {
        $merchant = new Merchant('999008881', '1', 'sq7HjrUOBfKmC576ILgskD5srU870gJ7');

        try {
            Notification::fromRequest([
                'Ds_MerchantParameters' => 'abc',
                'Ds_Signature' => '',
            ], $merchant);
            self::fail('expected exception');
        } catch (RedsysException) {
            self::assertTrue(true);
        }

        try {
            Notification::fromRequest([
                'Ds_MerchantParameters' => 'abc',
                'Ds_Signature' => 'sig',
                'Ds_SignatureVersion' => '',
            ], $merchant);
            self::fail('expected exception');
        } catch (RedsysException) {
            self::assertTrue(true);
        }

        $params = MerchantParameters::create()->with('foo', 'bar');
        $encoded = $params->encode();
        try {
            Notification::fromRequest([
                'Ds_MerchantParameters' => $encoded,
                'Ds_Signature' => 'sig',
                'Ds_SignatureVersion' => SignatureVersion::HmacSha512V2->value,
            ], $merchant);
            self::fail('expected exception');
        } catch (RedsysException $e) {
            self::assertStringContainsString('Ds_Order', $e->getMessage());
        }

        $ok = MerchantParameters::create()
            ->with('Ds_Order', '9')
            ->with('Ds_Response', 'AUTHORIZED');
        $enc = $ok->encode();
        $sig = $merchant->signer()->sign($enc, '9');
        $n = Notification::fromRequest([
            'Ds_MerchantParameters' => $enc,
            'Ds_Signature' => $sig,
            'Ds_SignatureVersion' => SignatureVersion::HmacSha512V2->value,
        ], $merchant);
        self::assertSame('AUTHORIZED', $n->responseCode());
        self::assertFalse($n->isAuthorized());

        $noCode = MerchantParameters::create()->with('Ds_Order', '9');
        $enc2 = $noCode->encode();
        $sig2 = $merchant->signer()->sign($enc2, '9');
        $n2 = Notification::fromRequest([
            'Ds_MerchantParameters' => $enc2,
            'Ds_Signature' => $sig2,
            'Ds_SignatureVersion' => SignatureVersion::HmacSha512V2->value,
        ], $merchant);
        self::assertNull($n2->responseCode());
        self::assertFalse($n2->isAuthorized());
    }

    public function testDecodeInvalidJsonParameters(): void
    {
        $this->expectException(RedsysException::class);
        MerchantParameters::decode(Base64Url::encode('null'));
    }
}

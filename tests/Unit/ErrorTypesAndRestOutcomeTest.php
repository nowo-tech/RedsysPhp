<?php

declare(strict_types=1);

namespace Nowo\Redsys\Tests\Unit;

use Nowo\Redsys\Environment;
use Nowo\Redsys\Exception\InvalidResponseException;
use Nowo\Redsys\Exception\RedsysException;
use Nowo\Redsys\Exception\SignatureInvalidException;
use Nowo\Redsys\Exception\TransportException;
use Nowo\Redsys\Http\CurlHttpClient;
use Nowo\Redsys\Http\HttpResponse;
use Nowo\Redsys\Merchant;
use Nowo\Redsys\MerchantParameters;
use Nowo\Redsys\Notification;
use Nowo\Redsys\Rest\RestResponse;
use Nowo\Redsys\SignatureVersion;
use Nowo\Redsys\TransactionType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A forged signature, a malformed payload and a transport failure have their own exception types (all RedsysException),
 * and a REST response exposes its outcome only from verified fields.
 */
final class ErrorTypesAndRestOutcomeTest extends TestCase
{
    private Merchant $merchant;

    protected function setUp(): void
    {
        $this->merchant = new Merchant('999008881', '1', 'sq7HjrUOBfKmC576ILgskD5srU870gJ7', Environment::Test, SignatureVersion::HmacSha512V2);
    }

    public function testAForgedNotificationSignatureIsASignatureInvalidException(): void
    {
        $encoded = MerchantParameters::create()->with('Ds_Order', '1234')->with('Ds_Response', '0000')->encode();

        $e = $this->notificationError(['Ds_MerchantParameters' => $encoded, 'Ds_Signature' => 'forged', 'Ds_SignatureVersion' => 'HMAC_SHA512_V2']);

        self::assertInstanceOf(SignatureInvalidException::class, $e);
        self::assertInstanceOf(RedsysException::class, $e, 'catch (RedsysException) keeps working');
    }

    public function testADowngradeIsASignatureInvalidException(): void
    {
        $encoded = MerchantParameters::create()->with('Ds_Order', '1234')->encode();

        self::assertInstanceOf(SignatureInvalidException::class, $this->notificationError(['Ds_MerchantParameters' => $encoded, 'Ds_Signature' => 'x', 'Ds_SignatureVersion' => 'HMAC_SHA256_V1']));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function malformedNotifications(): iterable
    {
        $noOrder = MerchantParameters::create()->with('Ds_Response', '0000')->encode();

        yield 'no parameters' => [['Ds_Signature' => 'x']];
        yield 'no signature' => [['Ds_MerchantParameters' => 'eyJ9']];
        yield 'empty version' => [['Ds_MerchantParameters' => 'eyJ9', 'Ds_Signature' => 'x', 'Ds_SignatureVersion' => '']];
        yield 'unknown version' => [['Ds_MerchantParameters' => 'eyJ9', 'Ds_Signature' => 'x', 'Ds_SignatureVersion' => 'MD5']];
        yield 'not base64' => [['Ds_MerchantParameters' => '%%%', 'Ds_Signature' => 'x']];
        yield 'not json' => [['Ds_MerchantParameters' => 'bm90IGpzb24', 'Ds_Signature' => 'x']];
        yield 'no order' => [['Ds_MerchantParameters' => $noOrder, 'Ds_Signature' => 'x']];
    }

    /**
     * @param array<string, mixed> $input
     */
    #[DataProvider('malformedNotifications')]
    public function testAMalformedNotificationIsAnInvalidResponseException(array $input): void
    {
        self::assertInstanceOf(InvalidResponseException::class, $this->notificationError($input));
    }

    public function testATransportFailureIsATransportException(): void
    {
        $this->expectException(TransportException::class);

        // Plain HTTP is refused by the client (HTTPS only) before any connection is made.
        (new CurlHttpClient(1, 1))->postJson('http://127.0.0.1:9/', '{}');
    }

    public function testHeadersWithLineBreaksAreRefused(): void
    {
        $this->expectException(RedsysException::class);
        $this->expectExceptionMessage('CR, LF or NUL');

        (new CurlHttpClient(1, 1))->postJson('https://127.0.0.1:9/', '{}', ['X-Test' => "a\r\nInjected: 1"]);
    }

    public function testTheCurlClientVerifiesTlsAndRefusesRedirects(): void
    {
        self::assertSame([
            \CURLOPT_SSL_VERIFYPEER => true,
            \CURLOPT_SSL_VERIFYHOST => 2,
            \CURLOPT_FOLLOWLOCATION => false,
            \CURLOPT_MAXREDIRS => 0,
            \CURLOPT_PROTOCOLS => \CURLPROTO_HTTPS,
            \CURLOPT_REDIR_PROTOCOLS => \CURLPROTO_HTTPS,
        ], CurlHttpClient::securityOptions());
    }

    public function testAVerifiedRestResponseExposesItsOutcome(): void
    {
        $response = $this->rest(['Ds_Order' => '1234', 'Ds_Response' => '0000', 'Ds_TransactionType' => '0'], true);

        self::assertTrue($response->isSignatureValid());
        $response->assertSignatureValid();
        self::assertSame('0000', $response->responseCode());
        self::assertSame(TransactionType::Authorization, $response->transactionType());
        self::assertTrue($response->isAuthorized());
        self::assertTrue($response->isSuccessfulForTransactionType());
        self::assertSame('1234', $response->verifiedParameters()['Ds_Order'] ?? null);

        $refund = $this->rest(['Ds_Order' => '1234', 'Ds_Response' => '0900', 'Ds_TransactionType' => '3'], true);
        self::assertFalse($refund->isAuthorized());
        self::assertTrue($refund->isSuccessfulForTransactionType());
        self::assertFalse($refund->isSuccessfulForTransactionType(TransactionType::Cancellation));
    }

    public function testAForgedRestResponseIsNeverAuthorized(): void
    {
        $response = $this->rest(['Ds_Order' => '1234', 'Ds_Response' => '0000', 'Ds_TransactionType' => '0'], false);

        self::assertFalse($response->isSignatureValid());
        self::assertSame('0000', $response->decodedParameters['Ds_Response'] ?? null, 'kept for diagnostics, untrusted');
        self::assertNull($response->verifiedParameters());
        self::assertNull($response->responseCode());
        self::assertNull($response->transactionType());
        self::assertFalse($response->isAuthorized());
        self::assertFalse($response->isSuccessfulForTransactionType());

        $this->expectException(SignatureInvalidException::class);
        $response->assertSignatureValid();
    }

    public function testAnUnsignedRestErrorIsNotTrusted(): void
    {
        $response = RestResponse::fromHttp(new HttpResponse(200, '{"errorCode":"SIS0042"}'), $this->merchant);

        self::assertSame('SIS0042', $response->errorCode);
        self::assertNull($response->verifiedParameters());
        self::assertFalse($response->isAuthorized());
    }

    /**
     * @param array<string, mixed> $input
     */
    private function notificationError(array $input): RedsysException
    {
        try {
            Notification::fromRequest($input, $this->merchant);
        } catch (RedsysException $e) {
            return $e;
        }
        self::fail('No exception thrown.');
    }

    /**
     * @param array<string, string> $fields
     */
    private function rest(array $fields, bool $signed): RestResponse
    {
        $params = MerchantParameters::create();
        foreach ($fields as $name => $value) {
            $params = $params->with($name, $value);
        }
        $encoded = $params->encode();
        $signature = $signed ? $this->merchant->signer()->sign($encoded, '1234') : 'forged';
        $body = json_encode(['Ds_MerchantParameters' => $encoded, 'Ds_Signature' => $signature, 'Ds_SignatureVersion' => 'HMAC_SHA512_V2'], \JSON_THROW_ON_ERROR);

        return RestResponse::fromHttp(new HttpResponse(200, $body), $this->merchant);
    }
}

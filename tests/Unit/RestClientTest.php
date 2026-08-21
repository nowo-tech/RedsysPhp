<?php

declare(strict_types=1);

namespace Nowo\Redsys\Tests\Unit;

use Nowo\Redsys\Currency;
use Nowo\Redsys\Environment;
use Nowo\Redsys\Http\HttpClient;
use Nowo\Redsys\Http\HttpResponse;
use Nowo\Redsys\Merchant;
use Nowo\Redsys\MerchantParameters;
use Nowo\Redsys\Rest\RestClient;
use Nowo\Redsys\SignatureVersion;
use Nowo\Redsys\SignedPayload;
use Nowo\Redsys\TransactionType;
use PHPUnit\Framework\TestCase;

final class RestClientTest extends TestCase
{
    public function testTreatPostsSignedJsonAndParsesVerifiedResponse(): void
    {
        $merchant = new Merchant(
            '999008881',
            '1',
            'sq7HjrUOBfKmC576ILgskD5srU870gJ7',
            Environment::Test,
            SignatureVersion::HmacSha512V2,
        );

        $request = MerchantParameters::create()
            ->forMerchant($merchant)
            ->amount(145)
            ->order('1444904795')
            ->currency(Currency::Eur)
            ->transactionType(TransactionType::Authorization);

        $responseParams = MerchantParameters::create()
            ->with('Ds_Order', '1444904795')
            ->with('Ds_Response', '0000');
        $encoded = $responseParams->encode();
        $signature = $merchant->signer()->sign($encoded, '1444904795');
        $body = json_encode([
            'Ds_MerchantParameters' => $encoded,
            'Ds_Signature' => $signature,
            'Ds_SignatureVersion' => SignatureVersion::HmacSha512V2->value,
        ], \JSON_THROW_ON_ERROR);

        $http = new class($body) implements HttpClient {
            public function __construct(private string $body)
            {
            }

            public function postJson(string $url, string $jsonBody, array $headers = []): HttpResponse
            {
                TestCase::assertStringContainsString('trataPeticionREST', $url);
                $decoded = json_decode($jsonBody, true);
                TestCase::assertIsArray($decoded);
                TestCase::assertArrayHasKey('Ds_Signature', $decoded);

                return new HttpResponse(200, $this->body);
            }
        };

        $client = new RestClient($merchant, $http);
        $response = $client->treat($request);

        self::assertSame(200, $response->httpStatus);
        self::assertTrue($response->signatureValid);
        self::assertSame('0000', $response->decodedParameters['Ds_Response'] ?? null);
        self::assertInstanceOf(SignedPayload::class, SignedPayload::from($merchant, $request));
    }
}

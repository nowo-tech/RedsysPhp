<?php

declare(strict_types=1);

namespace Nowo\Redsys\Tests\Unit;

use Nowo\Redsys\Currency;
use Nowo\Redsys\Environment;
use Nowo\Redsys\Exception\RedsysException;
use Nowo\Redsys\Exception\SignatureInvalidException;
use Nowo\Redsys\Http\HttpClient;
use Nowo\Redsys\Http\HttpResponse;
use Nowo\Redsys\Merchant;
use Nowo\Redsys\MerchantParameters;
use Nowo\Redsys\Notification;
use Nowo\Redsys\Rest\RestClient;
use Nowo\Redsys\Rest\RestClientInterface;
use Nowo\Redsys\Rest\RestResponse;
use Nowo\Redsys\Signature\Signer;
use Nowo\Redsys\Signature\SignerInterface;
use Nowo\Redsys\SignatureVersion;
use Nowo\Redsys\SignedPayload;
use Nowo\Redsys\Tests\Unit\Support\RecordingSigner;
use Nowo\Redsys\Tests\Unit\Support\RejectingSigner;
use Nowo\Redsys\TransactionType;
use PHPUnit\Framework\TestCase;

/**
 * REQ-ARCH-001 rule 1: RestClient and Signer are reachable through interfaces, and a custom signer replaces the
 * built-in one everywhere the library signs or verifies, without changing the default behaviour.
 */
final class PortsTest extends TestCase
{
    private const string KEY = 'sq7HjrUOBfKmC576ILgskD5srU870gJ7';

    public function testDefaultClassesImplementThePorts(): void
    {
        $merchant = $this->merchant();

        self::assertInstanceOf(SignerInterface::class, $merchant->signer());
        self::assertInstanceOf(RestClientInterface::class, new RestClient($merchant, $this->http('{}')));
    }

    public function testSignatureSignerIsTheBuiltInSignerByDefault(): void
    {
        $merchant = $this->merchant();
        $params = $this->params($merchant);
        $encoded = $params->encode();

        $signer = $merchant->signatureSigner();
        self::assertInstanceOf(Signer::class, $signer);
        // Same output as the pre-1.4.0 path ($merchant->signer()).
        self::assertSame($merchant->signer()->sign($encoded, '1444904795'), $signer->sign($encoded, '1444904795'));
        self::assertSame($merchant->signer()->sign($encoded, '1444904795'), SignedPayload::from($merchant, $params)->signature);
    }

    public function testCustomSignerSignsPayloadsAndVerifiesNotifications(): void
    {
        $custom = new RecordingSigner(new Signer(self::KEY));
        $merchant = new Merchant('999008881', '1', self::KEY, Environment::Test, SignatureVersion::HmacSha512V2, $custom);
        $params = $this->params($merchant);

        $payload = SignedPayload::from($merchant, $params);
        self::assertSame((new Signer(self::KEY))->sign($params->encode(), '1444904795'), $payload->signature);
        self::assertSame(['sign'], $custom->calls);

        $notification = Notification::fromRequest($payload->toArray(), $merchant);
        self::assertSame('1444904795', $notification->parameters()->orderNumber());
        // The library recomputes the signature with sign() and compares it itself; verify() is never called.
        self::assertSame(['sign', 'sign'], $custom->calls);
    }

    public function testCustomSignerDecidesVerification(): void
    {
        $merchant = $this->merchant();
        $payload = SignedPayload::from($merchant, $this->params($merchant));

        $rejecting = new Merchant('999008881', '1', self::KEY, Environment::Test, SignatureVersion::HmacSha512V2, new RejectingSigner());

        $this->expectException(SignatureInvalidException::class);
        Notification::fromRequest($payload->toArray(), $rejecting);
    }

    public function testCustomSignerVerifiesRestResponses(): void
    {
        $merchant = $this->merchant();
        $encoded = MerchantParameters::create()->with('Ds_Order', '1444904795')->with('Ds_Response', '0000')->encode();
        $body = json_encode([
            'Ds_MerchantParameters' => $encoded,
            'Ds_Signature' => $merchant->signer()->sign($encoded, '1444904795'),
            'Ds_SignatureVersion' => SignatureVersion::HmacSha512V2->value,
        ], \JSON_THROW_ON_ERROR);
        $rejecting = new Merchant('999008881', '1', self::KEY, Environment::Test, SignatureVersion::HmacSha512V2, new RejectingSigner());

        $client = new RestClient($merchant, $this->http($body));
        self::assertTrue($client->treat($this->params($merchant))->isSignatureValid());

        $response = RestResponse::fromHttp(new HttpResponse(200, $body), $rejecting);
        self::assertFalse($response->isSignatureValid());
    }

    public function testCustomSignerMustUseTheMerchantVersion(): void
    {
        $this->expectException(RedsysException::class);
        new Merchant('999008881', '1', self::KEY, Environment::Test, SignatureVersion::HmacSha256V1, new Signer(self::KEY));
    }

    public function testSecretKeyIsOptionalWithACustomSigner(): void
    {
        $custom = new RecordingSigner(new Signer(self::KEY));
        $merchant = new Merchant('999008881', '1', customSigner: $custom);
        $params = $this->params($merchant);

        self::assertSame('', $merchant->secretKey());
        self::assertSame($custom, $merchant->signatureSigner());

        $payload = SignedPayload::from($merchant, $params);
        self::assertSame((new Signer(self::KEY))->sign($params->encode(), '1444904795'), $payload->signature);
        self::assertSame('1444904795', Notification::fromRequest($payload->toArray(), $merchant)->parameters()->orderNumber());
    }

    public function testSecretKeyIsStillRequiredWithoutACustomSigner(): void
    {
        $this->expectException(RedsysException::class);
        new Merchant('999008881', '1');
    }

    public function testMerchantCodeAndTerminalAreStillRequiredWithACustomSigner(): void
    {
        $custom = new Signer(self::KEY);
        foreach ([['', '1'], ['999008881', '']] as [$code, $terminal]) {
            try {
                new Merchant($code, $terminal, customSigner: $custom);
                self::fail('Expected RedsysException for an empty merchant code or terminal.');
            } catch (RedsysException $e) {
                self::assertSame('Merchant code and terminal are required.', $e->getMessage(), 'no secret key mention with a custom signer');
            }
        }
    }

    /**
     * RF3-L1: a custom-signer merchant needs no placeholder key, and signer() refuses to build a built-in signer
     * over the missing key; the library verifies through signatureSigner(), so a forgery is rejected.
     */
    public function testSignerThrowsWithACustomSignerAndNoSecretKey(): void
    {
        $merchant = new Merchant('999008881', '1', customSigner: new RejectingSigner());
        $encoded = $this->params($this->merchant())->encode();
        $forged = (new Signer('unused'))->sign($encoded, '1444904795');

        try {
            $merchant->signer();
            self::fail('Merchant::signer() must not build a signer without a secret key.');
        } catch (RedsysException $e) {
            self::assertStringContainsString('signatureSigner()', $e->getMessage());
        }

        self::assertFalse($merchant->signatureSigner()->verify($encoded, '1444904795', $forged));
        $this->expectException(SignatureInvalidException::class);
        Notification::fromRequest([
            'Ds_MerchantParameters' => $encoded,
            'Ds_Signature' => $forged,
            'Ds_SignatureVersion' => SignatureVersion::HmacSha512V2->value,
        ], $merchant);
    }

    /**
     * RM1-L1 (no BC break): a custom signer with a real key (e.g. a logging decorator over the built-in Signer)
     * keeps getting the built-in Signer from signer().
     */
    public function testSignerKeepsWorkingWithACustomSignerAndASecretKey(): void
    {
        $custom = new RecordingSigner(new Signer(self::KEY));
        $merchant = new Merchant('999008881', '1', self::KEY, Environment::Test, SignatureVersion::HmacSha512V2, $custom);
        $encoded = $this->params($merchant)->encode();

        self::assertInstanceOf(Signer::class, $merchant->signer());
        self::assertSame($custom->sign($encoded, '1444904795'), $merchant->signer()->sign($encoded, '1444904795'));
        self::assertSame($custom, $merchant->signatureSigner());
    }

    public function testSignerStillWorksWithoutACustomSigner(): void
    {
        $merchant = $this->merchant();

        self::assertInstanceOf(Signer::class, $merchant->signer());
        self::assertSame(SignatureVersion::HmacSha512V2, $merchant->signer()->version());
    }

    public function testHostCanTypeAgainstRestClientInterface(): void
    {
        $merchant = $this->merchant();
        $fake = new class implements RestClientInterface {
            public int $calls = 0;

            public function init(MerchantParameters $parameters): RestResponse
            {
                ++$this->calls;

                return new RestResponse(200, '{}', null, false, null);
            }

            public function treat(MerchantParameters $parameters): RestResponse
            {
                return $this->init($parameters);
            }
        };

        $gateway = new readonly class($fake) {
            public function __construct(private RestClientInterface $client)
            {
            }

            public function pay(MerchantParameters $parameters): bool
            {
                return $this->client->treat($parameters)->isAuthorized();
            }
        };

        self::assertFalse($gateway->pay($this->params($merchant)));
        self::assertSame(1, $fake->calls);
    }

    private function merchant(): Merchant
    {
        return new Merchant('999008881', '1', self::KEY, Environment::Test, SignatureVersion::HmacSha512V2);
    }

    private function params(Merchant $merchant): MerchantParameters
    {
        return MerchantParameters::create()
            ->forMerchant($merchant)
            ->amount(145)
            ->order('1444904795')
            ->currency(Currency::Eur)
            ->transactionType(TransactionType::Authorization);
    }

    private function http(string $body): HttpClient
    {
        return new readonly class($body) implements HttpClient {
            public function __construct(private string $body)
            {
            }

            public function postJson(string $url, string $jsonBody, array $headers = []): HttpResponse
            {
                return new HttpResponse(200, $this->body);
            }
        };
    }
}

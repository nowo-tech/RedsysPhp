<?php

declare(strict_types=1);

namespace Nowo\Redsys\Tests\Unit;

use Nowo\Redsys\Environment;
use Nowo\Redsys\Exception\SignatureInvalidException;
use Nowo\Redsys\Http\HttpResponse;
use Nowo\Redsys\Merchant;
use Nowo\Redsys\MerchantParameters;
use Nowo\Redsys\Notification;
use Nowo\Redsys\Rest\RestResponse;
use Nowo\Redsys\Signature\SignatureCheck;
use Nowo\Redsys\Signature\Signer;
use Nowo\Redsys\Signature\SignerInterface;
use Nowo\Redsys\SignatureVersion;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Audit OI-R1: the library verifies signatures itself (sign() + hash_equals), so a custom signer whose verify() is
 * wrong cannot make Notification or RestResponse accept a forged payload.
 */
final class SignatureCheckTest extends TestCase
{
    private const string KEY = 'sq7HjrUOBfKmC576ILgskD5srU870gJ7';
    private const string ORDER = '1444904795';

    /**
     * @return iterable<string, array{SignatureVersion}>
     */
    public static function versions(): iterable
    {
        foreach (SignatureVersion::cases() as $version) {
            yield $version->value => [$version];
        }
    }

    #[DataProvider('versions')]
    public function testAlwaysTrueVerifyDoesNotAcceptAForgedNotification(SignatureVersion $version): void
    {
        $merchant = $this->merchant($version, $this->alwaysTrueSigner($version));
        $encoded = $this->encoded('0000');

        $this->expectException(SignatureInvalidException::class);
        Notification::fromRequest([
            'Ds_MerchantParameters' => $encoded,
            'Ds_Signature' => 'forged',
            'Ds_SignatureVersion' => $version->value,
        ], $merchant);
    }

    #[DataProvider('versions')]
    public function testAlwaysTrueVerifyDoesNotAcceptATamperedNotification(SignatureVersion $version): void
    {
        $merchant = $this->merchant($version, $this->alwaysTrueSigner($version));
        // Genuine signature of a declined payment, replayed over an authorised one.
        $signature = $merchant->signer()->sign($this->encoded('0190'), self::ORDER);

        $this->expectException(SignatureInvalidException::class);
        Notification::fromRequest([
            'Ds_MerchantParameters' => $this->encoded('0000'),
            'Ds_Signature' => $signature,
            'Ds_SignatureVersion' => $version->value,
        ], $merchant);
    }

    #[DataProvider('versions')]
    public function testAlwaysTrueVerifyDoesNotAcceptAForgedRestResponse(SignatureVersion $version): void
    {
        $merchant = $this->merchant($version, $this->alwaysTrueSigner($version));
        $body = json_encode([
            'Ds_MerchantParameters' => $this->encoded('0000'),
            'Ds_Signature' => 'forged',
            'Ds_SignatureVersion' => $version->value,
        ], \JSON_THROW_ON_ERROR);

        $response = RestResponse::fromHttp(new HttpResponse(200, $body), $merchant);

        self::assertFalse($response->isSignatureValid());
        self::assertFalse($response->isAuthorized());
        self::assertNull($response->verifiedParameters());
    }

    #[DataProvider('versions')]
    public function testAlwaysTrueVerifyStillAcceptsTheGenuineSignature(SignatureVersion $version): void
    {
        $merchant = $this->merchant($version, $this->alwaysTrueSigner($version));
        $encoded = $this->encoded('0000');
        $signature = $merchant->signer()->sign($encoded, self::ORDER);

        $notification = Notification::fromRequest([
            'Ds_MerchantParameters' => $encoded,
            'Ds_Signature' => $signature,
            'Ds_SignatureVersion' => $version->value,
        ], $merchant);
        self::assertTrue($notification->isAuthorized());

        $body = json_encode([
            'Ds_MerchantParameters' => $encoded,
            'Ds_Signature' => $signature,
            'Ds_SignatureVersion' => $version->value,
        ], \JSON_THROW_ON_ERROR);
        self::assertTrue(RestResponse::fromHttp(new HttpResponse(200, $body), $merchant)->isSignatureValid());
    }

    /**
     * The built-in signer is unchanged: the library check gives the same answer as Signer::verify() for the genuine
     * signature in standard and URL-safe Base64 (with and without padding) and for tampered ones, for every version.
     */
    #[DataProvider('versions')]
    public function testDefaultSignerSignsAndVerifiesIdentically(SignatureVersion $version): void
    {
        $merchant = $this->merchant($version);
        $signer = $merchant->signatureSigner();
        self::assertInstanceOf(Signer::class, $signer);

        $encoded = $this->encoded('0000');
        $signature = $signer->sign($encoded, self::ORDER);
        self::assertSame($merchant->signer()->sign($encoded, self::ORDER), $signature);

        $standard = strtr($signature, '-_', '+/');
        $urlSafe = rtrim(strtr($signature, '+/', '-_'), '=');
        $padded = $urlSafe.str_repeat('=', (4 - \strlen($urlSafe) % 4) % 4);
        $candidates = [
            'genuine' => [$signature, true],
            'standard' => [$standard, true],
            'url-safe' => [$urlSafe, true],
            'url-safe padded' => [$padded, true],
            'tampered' => [('A' === $signature[0] ? 'B' : 'A').substr($signature, 1), false],
            'truncated' => [substr($signature, 0, -2), false],
            'empty' => ['', false],
        ];

        foreach ($candidates as $label => [$candidate, $expected]) {
            self::assertSame($expected, $signer->verify($encoded, self::ORDER, $candidate), $label.' (Signer::verify)');
            self::assertSame($expected, SignatureCheck::matches($signer, $encoded, self::ORDER, $candidate), $label.' (library check)');
        }

        foreach (['genuine' => $signature, 'standard' => $standard, 'url-safe' => $urlSafe] as $label => $candidate) {
            $notification = Notification::fromRequest([
                'Ds_MerchantParameters' => $encoded,
                'Ds_Signature' => $candidate,
                'Ds_SignatureVersion' => $version->value,
            ], $merchant);
            self::assertSame(self::ORDER, $notification->parameters()->toArray()['Ds_Order'] ?? null, $label);

            $body = json_encode([
                'Ds_MerchantParameters' => $encoded,
                'Ds_Signature' => $candidate,
                'Ds_SignatureVersion' => $version->value,
            ], \JSON_THROW_ON_ERROR);
            self::assertTrue(RestResponse::fromHttp(new HttpResponse(200, $body), $merchant)->isSignatureValid(), $label);
        }
    }

    public function testEmptyExpectedSignatureNeverMatches(): void
    {
        $signer = new class implements SignerInterface {
            public function version(): SignatureVersion
            {
                return SignatureVersion::HmacSha512V2;
            }

            public function sign(string $encodedMerchantParameters, string $order): string
            {
                return '';
            }

            public function verify(string $encodedMerchantParameters, string $order, string $receivedSignature): bool
            {
                return true;
            }
        };

        self::assertFalse(SignatureCheck::matches($signer, 'e30', self::ORDER, ''));
        self::assertFalse(SignatureCheck::matches($signer, 'e30', self::ORDER, '=='));
    }

    private function alwaysTrueSigner(SignatureVersion $version): SignerInterface
    {
        return new readonly class(new Signer(self::KEY, $version)) implements SignerInterface {
            public function __construct(private Signer $inner)
            {
            }

            public function version(): SignatureVersion
            {
                return $this->inner->version();
            }

            public function sign(string $encodedMerchantParameters, string $order): string
            {
                return $this->inner->sign($encodedMerchantParameters, $order);
            }

            public function verify(string $encodedMerchantParameters, string $order, string $receivedSignature): bool
            {
                return true;
            }
        };
    }

    private function merchant(SignatureVersion $version, ?SignerInterface $signer = null): Merchant
    {
        return new Merchant('999008881', '1', self::KEY, Environment::Test, $version, $signer);
    }

    private function encoded(string $responseCode): string
    {
        return MerchantParameters::create()
            ->with('Ds_Order', self::ORDER)
            ->with('Ds_Response', $responseCode)
            ->encode();
    }
}

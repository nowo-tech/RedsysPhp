<?php

declare(strict_types=1);

namespace Nowo\Redsys\Tests\Unit;

use Nowo\Redsys\CofType;
use Nowo\Redsys\Currency;
use Nowo\Redsys\DirectPayment;
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
use Nowo\Redsys\ScaExemption;
use Nowo\Redsys\SignatureVersion;
use Nowo\Redsys\SignedPayload;
use Nowo\Redsys\TransactionType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

/**
 * COF / MIT helpers: each one encodes exactly like the raw with(), the token and COF txn id are read from verified
 * fields only, and they never appear in clear in debug output or logs.
 */
final class CofMitHelpersTest extends TestCase
{
    // Low-entropy fake card reference (40 ASCII letters / digits, like a Redsys reference); never a real one.
    private const string TOKEN = 'TESTTOKEN0000000000000000000000000000001';
    private const string TXN_ID = '2006031152000';

    private Merchant $merchant;

    protected function setUp(): void
    {
        // Public Redsys sandbox key (SANDBOX.md).
        $this->merchant = new Merchant('999008881', '1', 'sq7HjrUOBfKmC576ILgskD5srU870gJ7', Environment::Test, SignatureVersion::HmacSha512V2);
    }

    /**
     * @return iterable<string, array{\Closure(MerchantParameters): MerchantParameters, string, string}>
     */
    public static function equivalences(): iterable
    {
        yield 'identifier REQUIRED' => [static fn (MerchantParameters $p): MerchantParameters => $p->identifier('REQUIRED'), 'DS_MERCHANT_IDENTIFIER', 'REQUIRED'];
        yield 'identifier REQUIRED constant' => [static fn (MerchantParameters $p): MerchantParameters => $p->identifier(MerchantParameters::IDENTIFIER_REQUIRED), 'DS_MERCHANT_IDENTIFIER', 'REQUIRED'];
        yield 'identifier token' => [static fn (MerchantParameters $p): MerchantParameters => $p->identifier(self::TOKEN), 'DS_MERCHANT_IDENTIFIER', self::TOKEN];
        yield 'cofInit default' => [static fn (MerchantParameters $p): MerchantParameters => $p->cofInit(), 'DS_MERCHANT_COF_INI', 'S'];
        yield 'cofInit true' => [static fn (MerchantParameters $p): MerchantParameters => $p->cofInit(true), 'DS_MERCHANT_COF_INI', 'S'];
        yield 'cofInit false' => [static fn (MerchantParameters $p): MerchantParameters => $p->cofInit(false), 'DS_MERCHANT_COF_INI', 'N'];
        foreach (CofType::cases() as $type) {
            yield 'cofType enum '.$type->name => [static fn (MerchantParameters $p): MerchantParameters => $p->cofType($type), 'DS_MERCHANT_COF_TYPE', $type->value];
            yield 'cofType string '.$type->value => [static fn (MerchantParameters $p): MerchantParameters => $p->cofType($type->value), 'DS_MERCHANT_COF_TYPE', $type->value];
        }
        foreach (ScaExemption::cases() as $exemption) {
            yield 'excepSca enum '.$exemption->name => [static fn (MerchantParameters $p): MerchantParameters => $p->excepSca($exemption), 'DS_MERCHANT_EXCEP_SCA', $exemption->value];
            yield 'excepSca string '.$exemption->value => [static fn (MerchantParameters $p): MerchantParameters => $p->excepSca($exemption->value), 'DS_MERCHANT_EXCEP_SCA', $exemption->value];
        }
        yield 'directPayment default' => [static fn (MerchantParameters $p): MerchantParameters => $p->directPayment(), 'DS_MERCHANT_DIRECTPAYMENT', 'true'];
        yield 'directPayment true' => [static fn (MerchantParameters $p): MerchantParameters => $p->directPayment(true), 'DS_MERCHANT_DIRECTPAYMENT', 'true'];
        yield 'directPayment enum' => [static fn (MerchantParameters $p): MerchantParameters => $p->directPayment(DirectPayment::WithoutAuthentication), 'DS_MERCHANT_DIRECTPAYMENT', 'true'];
        yield 'directPayment moto enum' => [static fn (MerchantParameters $p): MerchantParameters => $p->directPayment(DirectPayment::Moto), 'DS_MERCHANT_DIRECTPAYMENT', 'moto'];
        yield 'directPayment MOTO string' => [static fn (MerchantParameters $p): MerchantParameters => $p->directPayment('MOTO'), 'DS_MERCHANT_DIRECTPAYMENT', 'moto'];
        yield 'directPayment true string' => [static fn (MerchantParameters $p): MerchantParameters => $p->directPayment('true'), 'DS_MERCHANT_DIRECTPAYMENT', 'true'];
        yield 'cofTxnId' => [static fn (MerchantParameters $p): MerchantParameters => $p->cofTxnId(self::TXN_ID), 'DS_MERCHANT_COF_TXNID', self::TXN_ID];
        yield 'cofTxnId 15 alphanumeric' => [static fn (MerchantParameters $p): MerchantParameters => $p->cofTxnId('ABC123def456GHI'), 'DS_MERCHANT_COF_TXNID', 'ABC123def456GHI'];
    }

    /**
     * @param \Closure(MerchantParameters): MerchantParameters $helper
     */
    #[DataProvider('equivalences')]
    public function testEachHelperEncodesExactlyLikeTheRawWith(\Closure $helper, string $key, string $value): void
    {
        $base = $this->base();

        $typed = $helper($base);
        $raw = $base->with($key, $value);

        self::assertSame($raw->toArray(), $typed->toArray());
        self::assertSame($raw->encode(), $typed->encode());
        self::assertSame(
            SignedPayload::from($this->merchant, $raw)->toArray(),
            SignedPayload::from($this->merchant, $typed)->toArray(),
            'same signed trio'
        );
        self::assertArrayNotHasKey($key, $base->toArray(), 'the original instance is unchanged');
    }

    public function testAFullInitialCofRequestMatchesTheRawBuilder(): void
    {
        $typed = $this->base()->identifier('REQUIRED')->cofInit()->cofType(CofType::Recurring);
        $raw = $this->base()
            ->with('DS_MERCHANT_IDENTIFIER', 'REQUIRED')
            ->with('DS_MERCHANT_COF_INI', 'S')
            ->with('DS_MERCHANT_COF_TYPE', 'R');

        self::assertSame($raw->encode(), $typed->encode());
    }

    public function testAFullMitRequestMatchesTheRawBuilder(): void
    {
        $typed = $this->base()
            ->identifier(self::TOKEN)
            ->cofTxnId(self::TXN_ID)
            ->cofType(CofType::Recurring)
            ->excepSca(ScaExemption::MerchantInitiated)
            ->directPayment();
        $raw = $this->base()
            ->with('DS_MERCHANT_IDENTIFIER', self::TOKEN)
            ->with('DS_MERCHANT_COF_TXNID', self::TXN_ID)
            ->with('DS_MERCHANT_COF_TYPE', 'R')
            ->with('DS_MERCHANT_EXCEP_SCA', 'MIT')
            ->with('DS_MERCHANT_DIRECTPAYMENT', 'true');

        self::assertSame($raw->encode(), $typed->encode());
    }

    public function testDirectPaymentFalseRemovesTheField(): void
    {
        $base = $this->base();

        self::assertSame($base->toArray(), $base->directPayment()->directPayment(false)->toArray());
        self::assertSame($base->toArray(), $base->directPayment(false)->toArray());
    }

    /**
     * @return iterable<string, array{\Closure(MerchantParameters): MerchantParameters, string}>
     */
    public static function invalidValues(): iterable
    {
        yield 'identifier empty' => [static fn (MerchantParameters $p): MerchantParameters => $p->identifier(''), 'DS_MERCHANT_IDENTIFIER must be 1 to 40'];
        yield 'identifier 41 chars' => [static fn (MerchantParameters $p): MerchantParameters => $p->identifier(str_repeat('a', 41)), 'DS_MERCHANT_IDENTIFIER must be 1 to 40'];
        yield 'identifier space' => [static fn (MerchantParameters $p): MerchantParameters => $p->identifier('abc def'), 'DS_MERCHANT_IDENTIFIER must be 1 to 40'];
        yield 'identifier newline' => [static fn (MerchantParameters $p): MerchantParameters => $p->identifier("abc\n"), 'DS_MERCHANT_IDENTIFIER must be 1 to 40'];
        yield 'identifier lower required' => [static fn (MerchantParameters $p): MerchantParameters => $p->identifier('required'), 'in upper case'];
        yield 'identifier mixed required' => [static fn (MerchantParameters $p): MerchantParameters => $p->identifier('Required'), 'in upper case'];
        yield 'cofTxnId empty' => [static fn (MerchantParameters $p): MerchantParameters => $p->cofTxnId(''), 'DS_MERCHANT_COF_TXNID must be 1 to 15'];
        yield 'cofTxnId 16 chars' => [static fn (MerchantParameters $p): MerchantParameters => $p->cofTxnId(str_repeat('1', 16)), 'DS_MERCHANT_COF_TXNID must be 1 to 15'];
        yield 'cofTxnId dash' => [static fn (MerchantParameters $p): MerchantParameters => $p->cofTxnId('2006-031'), 'DS_MERCHANT_COF_TXNID must be 1 to 15'];
        yield 'cofType unknown' => [static fn (MerchantParameters $p): MerchantParameters => $p->cofType('X'), 'Unknown DS_MERCHANT_COF_TYPE "X"'];
        yield 'cofType lower' => [static fn (MerchantParameters $p): MerchantParameters => $p->cofType('r'), 'Unknown DS_MERCHANT_COF_TYPE "r"'];
        yield 'excepSca unknown' => [static fn (MerchantParameters $p): MerchantParameters => $p->excepSca('Y'), 'Unknown DS_MERCHANT_EXCEP_SCA "Y"'];
        yield 'excepSca control chars truncated' => [static fn (MerchantParameters $p): MerchantParameters => $p->excepSca("MIT\nINJECTED-LONG"), 'Unknown DS_MERCHANT_EXCEP_SCA "MIT?INJE"'];
        yield 'directPayment unknown' => [static fn (MerchantParameters $p): MerchantParameters => $p->directPayment('yes'), 'Unknown DS_MERCHANT_DIRECTPAYMENT "yes"'];
    }

    /**
     * @param \Closure(MerchantParameters): MerchantParameters $helper
     */
    #[DataProvider('invalidValues')]
    public function testInvalidValuesAreRefused(\Closure $helper, string $message): void
    {
        $this->expectException(RedsysException::class);
        $this->expectExceptionMessage($message);

        $helper($this->base());
    }

    public function testAnInvalidTokenIsNeverInTheMessage(): void
    {
        $secretish = str_repeat('f', 39).'-';
        try {
            $this->base()->identifier($secretish);
            self::fail('Expected RedsysException.');
        } catch (RedsysException $e) {
            self::assertStringNotContainsString($secretish, $e->getMessage());
            self::assertStringNotContainsString('ffff', $e->getMessage());
        }
    }

    public function testNotificationExposesTheVerifiedTokenAndTxnId(): void
    {
        $notification = Notification::fromRequest($this->signed([
            'Ds_Order' => '1234',
            'Ds_Response' => '0000',
            'Ds_Merchant_Identifier' => self::TOKEN,
            'Ds_Merchant_Cof_Txnid' => self::TXN_ID,
        ]), $this->merchant);

        self::assertSame(self::TOKEN, $notification->merchantIdentifier());
        self::assertSame(self::TXN_ID, $notification->cofTxnId());
    }

    public function testNotificationReadsTheUpperCaseVariantsAndIntegers(): void
    {
        $notification = Notification::fromRequest($this->signed([
            'Ds_Order' => '1234',
            'DS_MERCHANT_IDENTIFIER' => self::TOKEN,
            'Ds_Merchant_COF_TXNID' => 2006031152000,
        ]), $this->merchant);

        self::assertSame(self::TOKEN, $notification->merchantIdentifier());
        self::assertSame(self::TXN_ID, $notification->cofTxnId());
    }

    public function testNotificationReturnsNullWhenAbsentOrEmptyOrNotScalar(): void
    {
        $notification = Notification::fromRequest($this->signed([
            'Ds_Order' => '1234',
            'Ds_Merchant_Identifier' => '',
            'Ds_Merchant_Cof_Txnid' => ['x'],
        ]), $this->merchant);

        self::assertNull($notification->merchantIdentifier());
        self::assertNull($notification->cofTxnId());

        $plain = Notification::fromRequest($this->signed(['Ds_Order' => '1234']), $this->merchant);
        self::assertNull($plain->merchantIdentifier());
        self::assertNull($plain->cofTxnId());
    }

    public function testRestResponseExposesTheTokenAndTxnIdOnlyWhenVerified(): void
    {
        $fields = ['Ds_Order' => '1234', 'Ds_Response' => '0000', 'Ds_Merchant_Identifier' => self::TOKEN, 'Ds_Merchant_Cof_Txnid' => self::TXN_ID];

        $verified = $this->rest($fields, true);
        self::assertTrue($verified->isSignatureValid());
        self::assertSame(self::TOKEN, $verified->merchantIdentifier());
        self::assertSame(self::TXN_ID, $verified->cofTxnId());

        $forged = $this->rest($fields, false);
        self::assertFalse($forged->isSignatureValid());
        self::assertSame(self::TOKEN, $forged->decodedParameters['Ds_Merchant_Identifier'] ?? null, 'still in the untrusted view');
        self::assertNull($forged->merchantIdentifier());
        self::assertNull($forged->cofTxnId());

        $unsigned = RestResponse::fromHttp(new HttpResponse(200, (string) json_encode($fields)), $this->merchant);
        self::assertNull($unsigned->merchantIdentifier());
        self::assertNull($unsigned->cofTxnId());
    }

    public function testDebugOutputRedactsTheTokenAndTxnId(): void
    {
        $params = $this->base()->identifier(self::TOKEN)->cofTxnId(self::TXN_ID)
            ->with('Ds_Merchant_Identifier', self::TOKEN)->with('ds_merchant_cof_txnid', self::TXN_ID);
        $dump = $this->dump($params);
        self::assertStringNotContainsString(self::TOKEN, $dump);
        self::assertStringNotContainsString(self::TXN_ID, $dump);
        self::assertStringContainsString('[redacted]', $dump);
        self::assertStringContainsString('1444904795', $dump, 'other fields stay visible');
        self::assertStringNotContainsString(self::TOKEN, print_r($params, true));

        self::assertStringContainsString('REQUIRED', $this->dump($this->base()->identifier('REQUIRED')), 'the request marker is not a secret');
        self::assertStringContainsString('list value', $this->dump(MerchantParameters::decode(Base64Url::encode('["list value"]'))), 'integer keys');

        $fields = ['Ds_Order' => '1234', 'Ds_Merchant_Identifier' => self::TOKEN, 'Ds_Merchant_Cof_Txnid' => self::TXN_ID];
        $input = $this->signed($fields);
        $notification = Notification::fromRequest($input, $this->merchant);
        $dump = $this->dump($notification);
        self::assertStringNotContainsString(self::TOKEN, $dump);
        self::assertStringNotContainsString(self::TXN_ID, $dump);
        self::assertStringNotContainsString($input['Ds_MerchantParameters'], $dump);
        self::assertStringContainsString('redacted: '.\strlen($input['Ds_MerchantParameters']).' bytes', $dump);

        foreach ([true, false] as $signed) {
            $dump = $this->dump($this->rest($fields, $signed));
            self::assertStringNotContainsString(self::TOKEN, $dump);
            self::assertStringNotContainsString(self::TXN_ID, $dump);
            self::assertStringContainsString('[redacted]', $dump);
        }

        $broken = RestResponse::fromHttp(new HttpResponse(200, (string) json_encode([
            'Ds_MerchantParameters' => MerchantParameters::create()->with('Ds_Merchant_Identifier', self::TOKEN)->encode(),
            'Ds_Signature' => 'x',
        ])), $this->merchant);
        $dump = $this->dump($broken);
        self::assertStringNotContainsString(MerchantParameters::create()->with('Ds_Merchant_Identifier', self::TOKEN)->encode(), $dump);
        self::assertStringContainsString('redacted:', $dump);

        $empty = RestResponse::fromHttp(new HttpResponse(502, 'Bad gateway'), $this->merchant);
        self::assertStringContainsString('redacted: 11 bytes', $this->dump($empty));
    }

    public function testRestClientLogsNeverCarryTheTokenOrTxnId(): void
    {
        $logger = new class extends AbstractLogger {
            /** @var list<array{mixed, string|\Stringable, array<mixed>}> */
            public array $records = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->records[] = [$level, $message, $context];
            }
        };
        $fields = ['Ds_Order' => '1444904795', 'Ds_Response' => '0000', 'Ds_Merchant_Identifier' => self::TOKEN, 'Ds_Merchant_Cof_Txnid' => self::TXN_ID];
        $encoded = MerchantParameters::create()->with('Ds_Order', '1444904795')->with('Ds_Response', '0000')
            ->with('Ds_Merchant_Identifier', self::TOKEN)->with('Ds_Merchant_Cof_Txnid', self::TXN_ID)->encode();
        $body = (string) json_encode(['Ds_MerchantParameters' => $encoded, 'Ds_Signature' => $this->merchant->signer()->sign($encoded, '1444904795'), 'Ds_SignatureVersion' => 'HMAC_SHA512_V2']);
        $http = $this->createStub(HttpClient::class);
        $http->method('postJson')->willReturn(new HttpResponse(200, $body));

        $response = (new RestClient($this->merchant, $http, $logger))->treat(
            $this->base()->identifier(self::TOKEN)->cofTxnId(self::TXN_ID)->excepSca(ScaExemption::MerchantInitiated)->directPayment()
        );

        self::assertSame(self::TOKEN, $response->merchantIdentifier());
        self::assertNotSame([], $logger->records);
        $logged = (string) json_encode($logger->records);
        self::assertStringNotContainsString(self::TOKEN, $logged);
        self::assertStringNotContainsString(self::TXN_ID, $logged);
        self::assertSame($fields['Ds_Order'], $response->verifiedParameters()['Ds_Order'] ?? null);
    }

    private function base(): MerchantParameters
    {
        return MerchantParameters::create()
            ->forMerchant($this->merchant)
            ->amount(145)
            ->order('1444904795')
            ->currency(Currency::Eur)
            ->transactionType(TransactionType::Authorization);
    }

    /**
     * @param array<string, mixed> $fields
     *
     * @return array{Ds_MerchantParameters: string, Ds_Signature: string, Ds_SignatureVersion: string}
     */
    private function signed(array $fields): array
    {
        $params = MerchantParameters::create();
        foreach ($fields as $name => $value) {
            $params = $params->with($name, $value);
        }
        $encoded = $params->encode();

        return [
            'Ds_MerchantParameters' => $encoded,
            'Ds_Signature' => $this->merchant->signer()->sign($encoded, '1234'),
            'Ds_SignatureVersion' => 'HMAC_SHA512_V2',
        ];
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function rest(array $fields, bool $signed): RestResponse
    {
        $input = $this->signed($fields);
        if (!$signed) {
            $input['Ds_Signature'] = 'forged';
        }

        return RestResponse::fromHttp(new HttpResponse(200, (string) json_encode($input)), $this->merchant);
    }

    private function dump(object $value): string
    {
        ob_start();
        var_dump($value);

        return (string) ob_get_clean();
    }
}

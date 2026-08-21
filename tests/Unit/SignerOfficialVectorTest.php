<?php

declare(strict_types=1);

namespace Nowo\Redsys\Tests\Unit;

use Nowo\Redsys\Signature\Signer;
use Nowo\Redsys\SignatureVersion;
use PHPUnit\Framework\TestCase;

/**
 * Official vector from Redsys “Firmar una operación” (HMAC_SHA512_V2).
 */
final class SignerOfficialVectorTest extends TestCase
{
    private const string SECRET = 'sq7HjrUOBfKmC576ILgskD5srU870gJ7';
    private const string ORDER = '1234567890';
    private const string MERCHANT_PARAMETERS = 'eyJEU19NRVJDSEFOVF9BTU9VTlQiOiI5OTkiLCJEU19NRVJDSEFOVF9PUkRFUiI6IjEyMzQ1Njc4OTAiLCJEU19NRVJDSEFOVF9NRVJDSEFOVENPREUiOiI5OTkwMDg4ODEiLCJEU19NRVJDSEFOVF9DVVJSRU5DWSI6Ijk3OCIsIkRTX01FUkNIQU5UX1RSQU5TQUNUSU9OVFlQRSI6IjAiLCJEU19NRVJDSEFOVF9URVJNSU5BTCI6IjEiLCJEU19NRVJDSEFOVF9NRVJDSEFOVFVSTCI6Imh0dHA6XC9cL3d3dy5wcnVlYmEuY29tXC91cmxOb3RpZmljYWNpb24ucGhwIiwiRFNfTUVSQ0hBTlRfVVJMT0siOiJodHRwOlwvXC93d3cucHJ1ZWJhLmNvbVwvdXJsT0sucGhwIiwiRFNfTUVSQ0hBTlRfVVJMS08iOiJodHRwOlwvXC93d3cucHJ1ZWJhLmNvbVwvdXJsS08ucGhwIn0';
    private const string EXPECTED_DIVERSIFIED = 'RWt3/IPTzYRMXsQtkiGRKg==';
    private const string EXPECTED_SIGNATURE = 'Vjo02eSWq249IeZZp3R-ArFnGLhKY0OuzDDlx1BuVtZDC2yhczA7_11uZhsYzLZBCMFAz8u8uzGDX3AErHKmmw';

    public function testDiversifyMatchesOfficialExample(): void
    {
        $signer = new Signer(self::SECRET, SignatureVersion::HmacSha512V2);

        self::assertSame(self::EXPECTED_DIVERSIFIED, $signer->diversify(self::ORDER));
    }

    public function testSignMatchesOfficialExample(): void
    {
        $signer = new Signer(self::SECRET, SignatureVersion::HmacSha512V2);
        $signature = $signer->sign(self::MERCHANT_PARAMETERS, self::ORDER);

        self::assertSame(self::EXPECTED_SIGNATURE, $signature);
        self::assertTrue($signer->verify(self::MERCHANT_PARAMETERS, self::ORDER, self::EXPECTED_SIGNATURE));
    }
}

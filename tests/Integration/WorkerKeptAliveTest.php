<?php

declare(strict_types=1);

namespace Nowo\Redsys\Tests\Integration;

use Nowo\Redsys\Currency;
use Nowo\Redsys\Environment;
use Nowo\Redsys\Exception\SignatureInvalidException;
use Nowo\Redsys\Http\HttpClient;
use Nowo\Redsys\Http\HttpResponse;
use Nowo\Redsys\Merchant;
use Nowo\Redsys\MerchantParameters;
use Nowo\Redsys\Notification;
use Nowo\Redsys\RedirectForm;
use Nowo\Redsys\Rest\RestClient;
use Nowo\Redsys\SignatureVersion;
use Nowo\Redsys\SignedPayload;
use Nowo\Redsys\TransactionType;
use PHPUnit\Framework\TestCase;

/**
 * REQ-RUNTIME-002: the library has no kernel, so this is one long-lived PHP process (a FrankenPHP worker that never
 * resets anything) serving N payments for two merchants (tenants) in alternation with the SAME shared objects.
 *
 * Every signature, redirect form, notification and REST response must hold only its own merchant's data and verify
 * only with its own key, nothing may carry over between iterations, and memory must stay flat.
 */
final class WorkerKeptAliveTest extends TestCase
{
    private const ITERATIONS = 1000;

    private const WARM_UP = 50;

    private const MEMORY_MARGIN_BYTES = 4 * 1024 * 1024;

    public function testAlternatingMerchantsInOneProcessKeepDataSeparatedAndMemoryFlat(): void
    {
        $merchants = [
            'alpha' => new Merchant('999008881', '1', 'sq7HjrUOBfKmC576ILgskD5srU870gJ7', Environment::Test, SignatureVersion::HmacSha512V2),
            'beta' => new Merchant('999008882', '2', 'Mk9m98IfEblmPfrpsawt7BmxObt98Jev', Environment::Test, SignatureVersion::HmacSha512V2),
        ];

        // Shared objects, created once and kept, as a worker would; nothing resets them.
        $http = new class implements HttpClient {
            public function postJson(string $url, string $jsonBody, array $headers = []): HttpResponse
            {
                $request = json_decode($jsonBody, true, 512, \JSON_THROW_ON_ERROR);
                $params = MerchantParameters::decode($request['Ds_MerchantParameters']);
                $order = $params->orderNumber();
                // The fake bank signs with the key of the merchant in the request (looked up by its code).
                $code = $params->toArray()['DS_MERCHANT_MERCHANTCODE'];
                $key = '999008881' === $code ? 'sq7HjrUOBfKmC576ILgskD5srU870gJ7' : 'Mk9m98IfEblmPfrpsawt7BmxObt98Jev';
                $merchant = new Merchant($code, '1', $key, Environment::Test, SignatureVersion::HmacSha512V2);
                $encoded = MerchantParameters::create()->with('Ds_Order', $order)->with('Ds_MerchantCode', $code)->with('Ds_Response', '0000')->encode();

                return new HttpResponse(200, json_encode([
                    'Ds_MerchantParameters' => $encoded,
                    'Ds_Signature' => $merchant->signer()->sign($encoded, $order),
                    'Ds_SignatureVersion' => SignatureVersion::HmacSha512V2->value,
                ], \JSON_THROW_ON_ERROR));
            }
        };
        $rest = [];
        foreach ($merchants as $name => $merchant) {
            $rest[$name] = new RestClient($merchant, $http);
        }

        $baseline = 0;

        for ($i = 0; $i < self::ITERATIONS; ++$i) {
            if (self::WARM_UP === $i) {
                gc_collect_cycles();
                $baseline = memory_get_usage();
            }

            $name = 0 === $i % 2 ? 'alpha' : 'beta';
            $otherName = 'alpha' === $name ? 'beta' : 'alpha';
            $merchant = $merchants[$name];
            $other = $merchants[$otherName];
            $order = \sprintf('%s%08d', 'alpha' === $name ? '1' : '2', $i);
            $at = \sprintf('iteration %d (%s)', $i, $name);

            $params = MerchantParameters::create()
                ->forMerchant($merchant)
                ->amount(100 + $i)
                ->order($order)
                ->currency(Currency::Eur)
                ->transactionType(TransactionType::Authorization);

            // Redirect form: own merchant code and order only, signature verifies with own key only.
            $payload = SignedPayload::from($merchant, $params);
            $form = RedirectForm::render($merchant->environment()->redirectUrl(), $payload);
            self::assertStringContainsString($payload->signature, $form, $at);
            $decoded = MerchantParameters::decode($payload->merchantParameters)->toArray();
            self::assertSame($merchant->merchantCode(), $decoded['DS_MERCHANT_MERCHANTCODE'], $at);
            self::assertSame($order, $decoded['DS_MERCHANT_ORDER'], $at);
            self::assertTrue($merchant->signer()->verify($payload->merchantParameters, $order, $payload->signature), $at);
            self::assertFalse($other->signer()->verify($payload->merchantParameters, $order, $payload->signature), $at);

            // Notification: verified with the own merchant, refused by the other one.
            $input = $payload->toArray();
            $notification = Notification::fromRequest($input, $merchant);
            self::assertSame($order, $notification->parameters()->orderNumber(), $at);
            try {
                Notification::fromRequest($input, $other);
                self::fail('The other merchant must not verify this notification.');
            } catch (SignatureInvalidException) {
            }

            // REST: the response of this order verifies for this merchant and carries only its data.
            $response = $rest[$name]->treat($params);
            self::assertTrue($response->signatureValid, $at);
            self::assertSame($order, $response->decodedParameters['Ds_Order'] ?? null, $at);
            self::assertSame($merchant->merchantCode(), $response->decodedParameters['Ds_MerchantCode'] ?? null, $at);
            self::assertStringNotContainsString($other->merchantCode(), $response->rawBody.json_encode($response->decodedParameters), $at);
        }

        gc_collect_cycles();
        $growth = memory_get_usage() - $baseline;
        self::assertLessThan(self::MEMORY_MARGIN_BYTES, $growth, \sprintf('memory grew by %d bytes over %d iterations', $growth, self::ITERATIONS - self::WARM_UP));
    }
}

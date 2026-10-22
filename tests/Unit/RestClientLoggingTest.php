<?php

declare(strict_types=1);

namespace Nowo\Redsys\Tests\Unit;

use Nowo\Redsys\Environment;
use Nowo\Redsys\Exception\RedsysException;
use Nowo\Redsys\Http\HttpClient;
use Nowo\Redsys\Http\HttpResponse;
use Nowo\Redsys\Merchant;
use Nowo\Redsys\MerchantParameters;
use Nowo\Redsys\Rest\RestClient;
use Nowo\Redsys\SignatureVersion;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

final class RestClientLoggingTest extends TestCase
{
    public function testSuccessfulCallLogsOutcomeWithoutPayload(): void
    {
        $logger = $this->logger();
        $http = $this->createStub(HttpClient::class);
        $http->method('postJson')->willReturn(new HttpResponse(200, '{"errorCode":"SIS0042"}'));

        (new RestClient($this->merchant(), $http, $logger))->init($this->parameters());

        self::assertSame('info', $logger->records[0][0]);
        self::assertSame('rest_init', $logger->records[0][2]['action']);
        self::assertSame(200, $logger->records[0][2]['http_status']);
        self::assertSame('SIS0042', $logger->records[0][2]['error_code']);
        self::assertStringNotContainsString('1444904795', (string) json_encode($logger->records));
    }

    public function testTransportFailureIsLoggedAndRethrown(): void
    {
        $logger = $this->logger();
        $http = $this->createStub(HttpClient::class);
        $http->method('postJson')->willThrowException(new RedsysException('boom'));

        try {
            (new RestClient($this->merchant(), $http, $logger))->treat($this->parameters());
            self::fail('Expected the transport failure to propagate.');
        } catch (RedsysException) {
        }

        self::assertSame(['error', 'Redsys REST request failed.'], [$logger->records[0][0], $logger->records[0][1]]);
        self::assertSame(RedsysException::class, $logger->records[0][2]['exception']);
        self::assertSame('rest_treat', $logger->records[0][2]['action']);
    }

    private function merchant(): Merchant
    {
        return new Merchant('999008881', '1', 'sq7HjrUOBfKmC576ILgskD5srU870gJ7', Environment::Test, SignatureVersion::HmacSha512V2);
    }

    private function parameters(): MerchantParameters
    {
        return MerchantParameters::create()->forMerchant($this->merchant())->amount(145)->order('1444904795');
    }

    private function logger(): RestClientRecordingLogger
    {
        return new RestClientRecordingLogger();
    }
}

/**
 * PSR-3 spy keeping level, message and context.
 */
final class RestClientRecordingLogger extends AbstractLogger
{
    /** @var list<array{string, string, array<string, mixed>}> */
    public array $records = [];

    /**
     * @param array<string, mixed> $context
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = [\is_string($level) ? $level : 'unknown', (string) $message, $context];
    }
}

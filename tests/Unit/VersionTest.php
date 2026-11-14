<?php

declare(strict_types=1);

namespace Nowo\Redsys\Tests\Unit;

use Nowo\Redsys\Version;
use PHPUnit\Framework\TestCase;

/**
 * Release check: Version::VERSION (sent in the User-Agent) must match composer.json extra.nowo-package-version.
 */
final class VersionTest extends TestCase
{
    public function testVersionIsSemver(): void
    {
        self::assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', Version::VERSION);
    }

    public function testVersionMatchesComposerExtra(): void
    {
        $composer = json_decode((string) file_get_contents(\dirname(__DIR__, 2).'/composer.json'), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($composer);

        $extra = $composer['extra'] ?? [];
        self::assertIsArray($extra);
        if (!isset($extra['nowo-package-version'])) {
            self::markTestSkipped('composer.json has no extra.nowo-package-version.');
        }

        self::assertSame($extra['nowo-package-version'], Version::VERSION, 'Bump src/Version.php and composer.json extra.nowo-package-version together.');
    }
}

<?php

declare(strict_types=1);

namespace Nowo\Redsys;

/**
 * SIS environments and public endpoint URLs (Redsys developer documentation).
 */
enum Environment: string
{
    case Test = 'test';
    case Live = 'live';

    public function redirectUrl(): string
    {
        return match ($this) {
            self::Test => 'https://sis-t.redsys.es:25443/sis/realizarPago',
            self::Live => 'https://sis.redsys.es/sis/realizarPago',
        };
    }

    public function restInitUrl(): string
    {
        return match ($this) {
            self::Test => 'https://sis-t.redsys.es:25443/sis/rest/iniciaPeticionREST',
            self::Live => 'https://sis.redsys.es/sis/rest/iniciaPeticionREST',
        };
    }

    public function restTreatUrl(): string
    {
        return match ($this) {
            self::Test => 'https://sis-t.redsys.es:25443/sis/rest/trataPeticionREST',
            self::Live => 'https://sis.redsys.es/sis/rest/trataPeticionREST',
        };
    }
}

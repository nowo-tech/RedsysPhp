<?php

declare(strict_types=1);

namespace Nowo\Redsys;

/**
 * Common ISO-4217 numeric codes used with DS_MERCHANT_CURRENCY.
 */
enum Currency: string
{
    case Eur = '978';
    case Usd = '840';
    case Gbp = '826';
}

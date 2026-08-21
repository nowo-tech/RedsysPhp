<?php

declare(strict_types=1);

namespace Nowo\Redsys;

/**
 * DS_MERCHANT_TRANSACTIONTYPE values from public Redsys integration manuals.
 */
enum TransactionType: string
{
    case Authorization = '0';
    case Preauthorization = '1';
    case Confirmation = '2';
    case Refund = '3';
    case SeparateAuth = '7';
    case ConfirmationAuth = '8';
    case Cancellation = '9';
    case Paygold = '15';
}

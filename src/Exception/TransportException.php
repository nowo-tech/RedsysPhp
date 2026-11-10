<?php

declare(strict_types=1);

namespace Nowo\Redsys\Exception;

/**
 * The HTTP call to Redsys failed before a response arrived (connection, DNS, TLS verification, timeout, refused
 * redirect). Nothing was received; the operation may be retried (check the order's state first for `treat`).
 */
final class TransportException extends RedsysException
{
}

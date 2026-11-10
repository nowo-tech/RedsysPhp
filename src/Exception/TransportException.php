<?php

declare(strict_types=1);

namespace Nowo\Redsys\Exception;

/**
 * The HTTP call to Redsys failed before a response arrived (connection, DNS, TLS verification, timeout, a non-HTTPS
 * URL). Network failures and timeouts may be retried (check the order's state first for `treat`); a non-HTTPS URL or
 * a TLS verification failure is a configuration error. A redirect is not followed: it comes back as an unsigned
 * response, not as this exception.
 */
final class TransportException extends RedsysException
{
}

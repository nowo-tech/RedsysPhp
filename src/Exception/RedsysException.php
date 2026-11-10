<?php

declare(strict_types=1);

namespace Nowo\Redsys\Exception;

/**
 * Base of every exception the library throws: `catch (RedsysException)` catches them all.
 *
 * Since 1.2.0 the cause has its own subtype (catch the most specific one you act on):
 *
 * - {@see SignatureInvalidException}: the Redsys signature does not verify, or the signature version is not the
 *   merchant's (downgrade attempt) — a forged or tampered payload, a security event, never retry it;
 * - {@see InvalidResponseException}: a payload that is missing fields or cannot be decoded (malformed, not forged);
 * - {@see TransportException}: the HTTP call to Redsys failed (network, TLS, timeout) — retryable.
 *
 * Errors of the caller's own input (empty merchant key, missing order, JSON encoding) stay plain `RedsysException`.
 */
class RedsysException extends \RuntimeException
{
}

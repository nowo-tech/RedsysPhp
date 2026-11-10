<?php

declare(strict_types=1);

namespace Nowo\Redsys\Exception;

/**
 * The Redsys signature of a notification or REST response does not verify, or its Ds_SignatureVersion is not the
 * merchant's configured one (a downgrade attempt). Treat it as a forged or tampered payload: log a security event,
 * answer with an error and never trust or retry the payload.
 */
final class SignatureInvalidException extends RedsysException
{
}

<?php

declare(strict_types=1);

namespace Nowo\Redsys\Exception;

/**
 * A Redsys payload (notification, browser return or REST response) is missing a required field, carries an unsupported
 * Ds_SignatureVersion or cannot be decoded (Base64 / JSON). Malformed rather than forged: nothing in it is trusted.
 */
final class InvalidResponseException extends RedsysException
{
}

<?php

declare(strict_types=1);

namespace Nowo\Redsys;

/**
 * DS_MERCHANT_DIRECTPAYMENT values.
 *
 * From the Redsys "Parámetros de entrada y salida" table (pagosonline.redsys.es, consulted 2026-11-12): «true» asks to
 * run the operation without authentication (ignored when the card is subject to PSD2 SCA: use it with an exemption such
 * as {@see ScaExemption::MerchantInitiated}); «moto» marks a mail / telephone order. Redsys documents both in lower case.
 *
 * @see https://pagosonline.redsys.es/desarrolladores-inicio/integrate-con-nosotros/parametros-de-entrada-y-salida/
 */
enum DirectPayment: string
{
    case WithoutAuthentication = 'true';
    case Moto = 'moto';
}

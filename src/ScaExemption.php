<?php

declare(strict_types=1);

namespace Nowo\Redsys;

/**
 * DS_MERCHANT_EXCEP_SCA values accepted in the payment request (trataPeticion), PSD2 exemptions.
 *
 * Values from the Redsys "Parámetros de entrada y salida" table (pagosonline.redsys.es, consulted 2026-11-12). Every
 * exemption except MIT must be enabled for the merchant by the acquirer. The Y / N values of iniciaPeticion (asking
 * which exemptions apply) are not exemptions and are not part of this enum: send them with
 * {@see MerchantParameters::with()}.
 *
 * @see https://pagosonline.redsys.es/desarrolladores-inicio/integrate-con-nosotros/parametros-de-entrada-y-salida/
 */
enum ScaExemption: string
{
    /** Merchant-initiated transaction on stored credentials (COF). */
    case MerchantInitiated = 'MIT';
    /** Low value. */
    case LowValue = 'LWV';
    /** Transaction risk analysis. */
    case TransactionRiskAnalysis = 'TRA';
    /** Secure corporate payment. */
    case Corporate = 'COR';
    /** Delegated authentication. */
    case DelegatedAuthentication = 'ATD';
}

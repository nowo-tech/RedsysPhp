<?php

declare(strict_types=1);

namespace Nowo\Redsys;

/**
 * DS_MERCHANT_COF_TYPE: the kind of credential-on-file operation (Visa / Mastercard COF).
 *
 * Values from the Redsys "Parámetros de entrada y salida" table and the "Tipos de operativas COF" table of the Redsys
 * tokenisation guide (pagosonline.redsys.es, consulted 2026-11-12). When the field is omitted Redsys uses C (other).
 *
 * @see https://pagosonline.redsys.es/desarrolladores-inicio/integrate-con-nosotros/parametros-de-entrada-y-salida/
 * @see https://pagosonline.redsys.es/desarrolladores-inicio/documentacion-funcionalidades-avanzadas/tokenizacion/
 */
enum CofType: string
{
    case Installments = 'I';
    case Recurring = 'R';
    case Reauthorization = 'H';
    case Resubmission = 'E';
    case Delayed = 'D';
    case Incremental = 'M';
    case NoShow = 'N';
    case Other = 'C';
}

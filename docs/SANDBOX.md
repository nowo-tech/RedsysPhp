# Sandbox: test credentials and cards

**Public** Redsys test environment (SIS test) data. Valid only in sandbox (`sis-t.redsys.es`). No accounting value.

Official source: [Test cards and environments](https://pagosonline.redsys.es/desarrolladores-inicio/integrate-con-nosotros/tarjetas-y-entornos-de-prueba/) (Redsys TPVV developers).

## Environment

| Use | URL |
|-----|-----|
| Redirect (payment) | `https://sis-t.redsys.es:25443/sis/realizarPago` |
| REST `iniciaPeticion` | `https://sis-t.redsys.es:25443/sis/rest/iniciaPeticionREST` |
| REST `trataPeticion` | `https://sis-t.redsys.es:25443/sis/rest/trataPeticionREST` |

In this SDK: `Environment::Test` points to those URLs.

## Generic credentials (no registration)

To try the gateway without creating an account in the administration portal:

| Field | Value |
|-------|-------|
| Merchant code (FUC) / `Ds_Merchant_MerchantCode` | `999008881` |
| Terminal / `Ds_Merchant_Terminal` | `001` (if it fails, try `1` or `049`) |
| Signature key | `sq7HjrUOBfKmC576ILgskD5srU870gJ7` |

If the flow asks for an authentication code (OTP / CIP), use **`123456`**.

FrankenPHP demo variables (`demo/symfony8/.env`):

```env
REDSYS_MERCHANT_CODE=999008881
REDSYS_TERMINAL=1
REDSYS_SECRET_KEY=sq7HjrUOBfKmC576ILgskD5srU870gJ7
REDSYS_ENV=test
```

### Own test merchant (portal)

You can also [create a test account](https://pagosonline.redsys.es/desarrolladores-inicio/integrate-con-nosotros/tarjetas-y-entornos-de-prueba/) in the sandbox Administration Portal (expires in ~7 days). You get your own FUC and terminal; the sandbox signature key is usually the same generic key above. In production the key is different and unique.

## Test cards

Work only in sandbox. Expiry: any future date (commonly `12/49`). CVV: any value except `999`, unless noted (commonly `123`).

### General use

| Brand / protocol | PAN | Expiry | CVV |
|----------------|-----|--------|-----|
| VISA · EMV3DS 2.2 | `4548810000000003` | 12/49 | 123 |
| Mastercard · EMV3DS 2.1 | `5576441563045037` | 12/49 | 123 |

For most redirect integrations, the generic VISA card is enough.

### Other brands

| Brand | PAN | Expiry | CVV |
|-------|-----|--------|-----|
| American Express | `376674000000008` | 12/49 | 123 |
| Diners Club | `36849800000018` | 12/49 | 123 |
| JCB | `3587870000000001` | 12/49 | 123 |

### Specific EMV3DS cases

| Case | PAN | Expiry | CVV |
|------|-----|--------|-----|
| VISA frictionless (2.1) | `4548814479727229` | 12/49 | 123 |
| VISA frictionless + threeDSMethodURL | `4918019160034602` | 12/49 | 123 |
| VISA challenge (2.1) | `4548817212493017` | 12/49 | 123 |
| VISA challenge + threeDSMethodURL | `4918019199883839` | 12/49 | 123 |

### DCC (foreign currency)

The terminal must have DCC enabled.

| Case | PAN | Expiry | CVV |
|------|-----|--------|-----|
| Mastercard USA (USD) | `5424180805648190` | 12/49 | 123 |
| VISA USA (USD) | `4117731234567891` | 12/49 | 123 |
| Mastercard Norway (NOK) | `5409960031405146` | 12/49 | 123 |

### Denials and errors (CVV or amount)

With the generic VISA card (`4548810000000003`), these CVVs simulate denial (after successful authentication in the simulator):

| CVV | Simulation |
|-----|------------|
| `999` | Denied |
| `172` | Denied 172 (do not retry) |
| `173` | Denied 173 (do not retry without updating data) |
| `174` | Denied 174 (do not retry for 72 h) |

Amounts ending in **`,96`**, **`,72`**, **`,73`**, or **`,74`** EUR also trigger equivalent sandbox denials.

## Warnings

- Do not use `Environment::Test` or these cards on a public-facing site: a customer could “pay” with a test PAN and your shop would treat the order as valid.
- **Production** credentials are provided by your bank; do not reuse sandbox FUC/key.
- This document summarizes public Redsys material; if there is a discrepancy, the official documentation linked above prevails.

<?php

declare(strict_types=1);

namespace Nowo\Redsys;

/**
 * HTML auto-submit form for redirect integration.
 * Returns a string only — never echoes or exits (FrankenPHP / worker safe).
 */
final class RedirectForm
{
    public static function render(string $actionUrl, SignedPayload $payload, string $formId = 'redsys_payment_form'): string
    {
        $fields = $payload->toArray();
        $html = '<form id="'.self::escape($formId).'" name="'.self::escape($formId).'" action="'
            .self::escape($actionUrl).'" method="POST">'."\n";

        foreach ($fields as $name => $value) {
            $html .= '  <input type="hidden" name="'.self::escape($name).'" value="'
                .self::escape($value)."\" />\n";
        }

        $html .= "</form>\n";
        $html .= '<script type="text/javascript">document.getElementById("'
            .self::escape($formId).'").submit();</script>'."\n";

        return $html;
    }

    public static function forMerchant(Merchant $merchant, MerchantParameters $parameters): string
    {
        return self::render(
            $merchant->environment()->redirectUrl(),
            SignedPayload::from($merchant, $parameters)
        );
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
    }
}

<?php

namespace App\Rules;

final class AmountRules
{
    public const MONEY_MAX = '9999999999.99';

    public const CURRENCY_PRICE_MAX = '9999999999999999.99';

    public const EXCHANGE_RATE_MAX = '999999999999.999999';

    public static function money(): array
    {
        return ['numeric', 'min:0', 'max:'.self::MONEY_MAX, 'decimal:0,2'];
    }

    public static function currencyPrice(): array
    {
        return ['numeric', 'min:0', 'max:'.self::CURRENCY_PRICE_MAX, 'decimal:0,2'];
    }

    public static function exchangeRate(): array
    {
        return ['numeric', 'gt:0', 'max:'.self::EXCHANGE_RATE_MAX, 'decimal:0,6'];
    }
}

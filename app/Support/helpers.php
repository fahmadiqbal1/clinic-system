<?php

if (!function_exists('currency')) {
    /**
     * Format a numeric amount with the configured currency symbol.
     *
     * @param  float|int|string  $amount
     * @return string
     */
    function currency($amount): string
    {
        return config('app.currency_symbol', '₨') . number_format((float) $amount, 2);
    }
}

if (!function_exists('currency_symbol')) {
    /**
     * Get the configured currency symbol.
     *
     * @return string
     */
    function currency_symbol(): string
    {
        return config('app.currency_symbol', '₨');
    }
}
